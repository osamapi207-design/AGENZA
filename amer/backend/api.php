<?php
/* AGENZA Amer — HTTP entry: POST {message, lang, page} → {reply, ...}
   Pipeline nodes: receive → intent → knowledge → memory → generate → persist → respond */
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/Config.php';
require __DIR__ . '/Store.php';
require __DIR__ . '/Logger.php';
require __DIR__ . '/Knowledge.php';
require __DIR__ . '/Memory.php';
require __DIR__ . '/Intent.php';
require __DIR__ . '/OpenAIClient.php';
require __DIR__ . '/Validator.php';
require __DIR__ . '/Agent.php';
require __DIR__ . '/Workflow.php';

amer_load_env();

// ---------- CORS: same-origin + configured origins (GitHub Pages site) ----------
$allowedOrigins = array_filter(array_map('trim', explode(',', (string)AmerConfig::get('ALLOWED_ORIGINS', ''))));
$reqOrigin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$originOk = ($reqOrigin === '');
if ($reqOrigin !== '') {
  $reqHost = strtolower((string)parse_url($reqOrigin, PHP_URL_HOST));
  $srvHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
  if ($reqHost !== '' && ($reqHost === $srvHost
      || in_array($reqOrigin, $allowedOrigins, true)
      || in_array($reqHost, $allowedOrigins, true))) {
    $originOk = true;
  }
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
  if ($originOk && $reqOrigin !== '') {
    header('Access-Control-Allow-Origin: ' . $reqOrigin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');
  }
  exit(0);
}
if (!$originOk) {
  amer_json_out(['error' => 'forbidden'], 403);
}
if ($reqOrigin !== '') {
  header('Access-Control-Allow-Origin: ' . $reqOrigin);
  header('Vary: Origin');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  amer_json_out(['error' => 'method_not_allowed'], 405);
}

// ---------- rate limit ----------
$ip = amer_client_ip();
$rlf = sys_get_temp_dir() . '/amer_rl_' . md5($ip) . '.json';
$now = time();
$hits = [];
if (is_file($rlf)) {
  $hits = json_decode((string)file_get_contents($rlf), true) ?: [];
  $hits = array_values(array_filter($hits, function ($t) use ($now) { return ($now - (int)$t) < 60; }));
}
if (count($hits) >= AmerConfig::ratePerMin()) {
  amer_json_out(['error' => 'rate_limited'], 429);
}
$hits[] = $now;
@file_put_contents($rlf, json_encode($hits));

// ---------- input ----------
$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body)) $body = [];
$msg = isset($body['message']) ? trim((string)$body['message']) : '';
$msg = strip_tags($msg);
$msg = (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $msg);
$msg = amer_cut($msg, 800);
$lang = (isset($body['lang']) && $body['lang'] === 'en') ? 'en' : 'ar';
$page = isset($body['page']) ? preg_replace('/[^a-z0-9\-\.]/i', '', (string)$body['page']) : '';
if ($msg === '') amer_json_out(['error' => 'empty_message'], 400);

// ---------- conversation / customer via cookie ----------
$cid = '';
if (isset($_COOKIE['amer_cid']) && preg_match('/^[a-f0-9]{16}$/', (string)$_COOKIE['amer_cid'])) {
  $cid = (string)$_COOKIE['amer_cid'];
}
$newSession = false;
if ($cid === '') {
  $cid = amer_uid(8);
  $newSession = true;
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  setcookie('amer_cid', $cid, time() + 365 * 86400, '/', '', $secure, true);
}

$ctx = [
  'cid' => $cid, 'ip' => $ip, 'lang' => $lang, 'page' => $page,
  'message' => $msg, 'new_session' => $newSession,
];

$pagesMap = [
  'index.html' => 'الصفحة الرئيسية', 'index-en.html' => 'Home',
  'about.html' => 'من نحن', 'about-en.html' => 'About',
  'builder.html' => 'ابني وكيلك', 'builder-en.html' => 'Build Your Agent',
  'policy.html' => 'السياسات', 'policy-en.html' => 'Policies',
];

AmerWorkflow::run([
  ['name' => 'receive', 'run' => function (&$c) {
    $convs = AmerStore::read('conversations');
    if (!isset($convs[$c['cid']])) {
      $custId = amer_uid(8);
      $convs[$c['cid']] = [
        'id' => $c['cid'], 'customer_id' => $custId, 'lang' => $c['lang'],
        'page' => $c['page'], 'turns' => [],
        'created_at' => date('c'), 'updated_at' => date('c'),
      ];
      AmerStore::write('conversations', $convs);
      $custs = AmerStore::read('customers');
      $custs[$custId] = AmerMemory::blankCustomer($custId, $c['ip'], $c['lang']);
      AmerStore::write('customers', $custs);
    }
    $c['conversation'] = $convs[$c['cid']];
  }],
  ['name' => 'intent', 'run' => function (&$c) {
    $d = AmerIntent::detect($c['message']);
    $c['intent'] = $d['intent'];
    $c['handoff'] = $d['handoff'];
    $c['attack'] = $d['attack'];
  }],
  ['name' => 'knowledge', 'run' => function (&$c) {
    $r = AmerKnowledge::retrieve($c['message'], $c['intent']);
    $c['knowledge_text'] = $r['text'];
    $c['sources'] = $r['sources'];
  }],
  ['name' => 'memory_load', 'run' => function (&$c) {
    $custs = AmerStore::read('customers');
    $custId = $c['conversation']['customer_id'];
    $c['profile'] = isset($custs[$custId])
      ? $custs[$custId]
      : AmerMemory::blankCustomer($custId, $c['ip'], $c['lang']);
    $turns = isset($c['conversation']['turns']) ? $c['conversation']['turns'] : [];
    $hist = [];
    foreach (array_slice($turns, -8) as $t) {
      if (isset($t['role'], $t['content'])) $hist[] = ['role' => $t['role'], 'content' => $t['content']];
    }
    $c['history'] = $hist;
    $lastA = '';
    for ($i = count($turns) - 1; $i >= 0; $i--) {
      if (($turns[$i]['role'] ?? '') === 'assistant') { $lastA = (string)$turns[$i]['content']; break; }
    }
    $c['last_assistant'] = $lastA;
    $c['memory_summary'] = AmerMemory::summarize($c['profile']);
  }],
  ['name' => 'generate', 'run' => function (&$c) use ($pagesMap) {
    $pageName = isset($pagesMap[$c['page']]) ? $pagesMap[$c['page']] : $c['page'];
    $gen = AmerAgent::generate($c['message'], $c['history'], [
      'lang' => $c['lang'],
      'pageName' => $pageName,
      'intent' => $c['intent'],
      'memorySummary' => $c['memory_summary'],
      'knowledgeText' => $c['knowledge_text'],
      'lastAssistant' => $c['last_assistant'],
    ], $c['lang']);
    $c['gen'] = $gen;
    $c['in_tokens'] = isset($gen['in']) ? (int)$gen['in'] : 0;
    $c['out_tokens'] = isset($gen['out']) ? (int)$gen['out'] : 0;
    if ($gen['reply'] !== '') {
      $c['reply'] = $gen['reply'];
    } else {
      // graceful degradation: never expose internal errors
      AmerLogger::error('generate', 'fail=' . ($gen['fail'] ?? '?'), ['intent' => $c['intent']]);
      $c['reply'] = ($c['lang'] === 'en')
        ? 'One moment while I check that for you.'
        : 'ثواني وأراجع لحضرتك الموضوع.';
      $c['degraded'] = true;
    }
  }],
  ['name' => 'persist', 'run' => function (&$c) {
    $profile = AmerMemory::extract($c['message'], $c['profile']);
    $status = AmerMemory::score($profile, $c['intent'], $c['message']);
    $profile['lead_status'] = $status;
    if ($c['handoff']) $profile['handoff'] = true;

    AmerStore::update('customers', function ($custs) use ($profile) {
      $custs[$profile['id']] = $profile;
      return $custs;
    });
    $turns = isset($c['conversation']['turns']) ? $c['conversation']['turns'] : [];
    $turns[] = ['t' => date('c'), 'role' => 'user', 'content' => $c['message']];
    $turns[] = ['t' => date('c'), 'role' => 'assistant', 'content' => amer_cut($c['reply'], 1000)];
    if (count($turns) > 60) $turns = array_slice($turns, -60);
    AmerStore::update('conversations', function ($convs) use ($c, $turns) {
      if (isset($convs[$c['cid']])) {
        $convs[$c['cid']]['turns'] = $turns;
        $convs[$c['cid']]['updated_at'] = date('c');
      }
      return $convs;
    });
    $c['lead_status'] = $status;
    $c['profile'] = $profile;
    AmerLogger::usage(empty($c['degraded']), $c['in_tokens'], $c['out_tokens']);
  }],
  ['name' => 'respond', 'run' => function (&$c) {
    $action = null;
    if ($c['intent'] === 'demo') $action = 'demo';
    elseif (in_array($c['intent'], ['human', 'complaint'], true)) $action = 'wa';
    $c['out'] = [
      'reply' => $c['reply'],
      'conversation_id' => $c['cid'],
      'action' => $action,
      'handoff' => (bool)$c['handoff'],
      'lead_status' => $c['lead_status'],
    ];
  }],
], $ctx);

if (isset($ctx['error'])) {
  AmerLogger::error('api', (string)$ctx['error']);
  amer_json_out(['error' => 'internal'], 500);
}

AmerLogger::request([
  'cid' => $ctx['cid'],
  'intent' => $ctx['intent'],
  'sources' => $ctx['sources'],
  'ms' => $ctx['__total_ms'],
  'lead' => $ctx['lead_status'],
  'degraded' => !empty($ctx['degraded']),
]);

amer_json_out($ctx['out']);
