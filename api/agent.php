<?php
/* ============================================================
   AGENZA agent "Amer" — secure backend (OpenAI ChatGPT)
   ------------------------------------------------------------
   - Amer's speaking style  → api/persona-ar.txt + persona-en.txt
   - Company knowledge      → api/knowledge-ar.txt + knowledge-en.txt
     (EDIT THESE .txt FILES to change answers or prices —
      no code changes needed.)
   - The API key below NEVER reaches the browser.
   - Needs PHP 7.4+ with cURL. Chats saved per IP in api/logs/.
   - SECURITY: this key was shared in chat — regenerate it in
     OpenAI dashboard after testing + set a spending limit.
   ============================================================ */

header('Content-Type: application/json; charset=utf-8');

// ---------- CONFIG ----------
const OPENAI_KEY   = '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';
const MODEL        = 'gpt-4o-mini';
const MAX_TOKENS   = 500;
const TIMEOUT_SEC  = 25;
const RATE_PER_MIN = 12;

function logError($msg) {
  try {
    $d = __DIR__ . '/logs';
    if (!is_dir($d)) {
      @mkdir($d, 0750, true);
      @file_put_contents($d . '/.htaccess', "Require all denied\nDeny from all\n");
      @file_put_contents($d . '/index.html', '');
    }
    @file_put_contents($d . '/error.log',
      date('c') . ' | ' . $msg . "\n", FILE_APPEND | LOCK_EX);
  } catch (Throwable $t) {}
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'method_not_allowed']);
  exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
$msg  = isset($data['message']) ? trim(mb_substr((string)$data['message'], 0, 600)) : '';
$lang = (isset($data['lang']) && $data['lang'] === 'en') ? 'en' : 'ar';
$page = isset($data['page']) ? preg_replace('/[^a-z0-9\-\.]/i', '', (string)$data['page']) : '';
$hist = (isset($data['history']) && is_array($data['history'])) ? array_slice($data['history'], -8) : [];

if ($msg === '') {
  http_response_code(400);
  echo json_encode(['error' => 'empty_message']);
  exit;
}

// ---------- rate limit per IP ----------
$ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$now  = time();
$rlf  = sys_get_temp_dir() . '/agenza_rl_' . md5($ip) . '.json';
$hits = [];
if (is_file($rlf)) {
  $hits = json_decode((string)file_get_contents($rlf), true) ?: [];
  $hits = array_values(array_filter($hits, fn($t) => ($now - (int)$t) < 60));
}
if (count($hits) >= RATE_PER_MIN) {
  http_response_code(429);
  echo json_encode(['error' => 'rate_limited']);
  exit;
}
$hits[] = $now;
@file_put_contents($rlf, json_encode($hits));

// ---------- load persona + knowledge from .txt files ----------
function loadTxt($name, $fallback) {
  $p = __DIR__ . '/' . $name;
  if (is_file($p)) {
    $c = trim((string)@file_get_contents($p));
    if ($c !== '') return mb_substr($c, 0, 12000);
  }
  return $fallback;
}

$personaFb = ($lang === 'en')
  ? 'You are "Amer", a formal polite human sales rep at AGENZA. Mirror the customer language. Never mention AI. Plain text, 2-5 lines.'
  : 'أنت "عامر"، موظف مبيعات بشري رسمي مهذب في AGENZA. رد بلغة العميل. ممنوع ذكر الذكاء الاصطناعي. نص عادي من سطرين لخمسة.';
$knowFb = ($lang === 'en')
  ? 'AGENZA: Egyptian software company. 3 agents: Smart Reply (social+comments like human), Order Confirmation (WhatsApp/email), Data Entry (Sheets/Excel). Plans: Launch/Growth/Business. Payment 50% upfront + monthly fee. Free 30-min demo.'
  : 'AGENZA شركة برمجيات مصرية. 3 وكلاء: الرد (سوشيال وكومنتات كبشري)، تأكيد الأوردرات (واتساب/إيميل)، إدخال البيانات (شيتات). الباقات: الانطلاقة/النمو/الشركات. الدفع 50% مقدم + شهري. ديمو مجاني 30 دقيقة.';

$persona   = loadTxt($lang === 'en' ? 'persona-en.txt' : 'persona-ar.txt', $personaFb);
$knowledge = loadTxt($lang === 'en' ? 'knowledge-en.txt' : 'knowledge-ar.txt', $knowFb);

$pagesMap = [
  'index.html' => 'الصفحة الرئيسية', 'index-en.html' => 'Home',
  'about.html' => 'من نحن', 'about-en.html' => 'About',
  'builder.html' => 'ابني وكيلك', 'builder-en.html' => 'Build Your Agent',
  'policy.html' => 'السياسات', 'policy-en.html' => 'Policies',
];
$pageName = $pagesMap[$page] ?? $page;

$system = $persona . "\n\n" . $knowledge .
  "\n\n[معلومة سياقية: العميل يتصفح الآن صفحة: {$pageName}]" .
  "\n\n[أمر تقيد إجباري: أجب حصراً من نص الشخصية والمعرفة أعلاه. أي معلومة عن الشركة (خدمات/باقات/أسعار/شروط/أرقام) يجب أن تكون موجودة نصاً في ملف المعرفة — وإن لم تكن موجودة فاعتذر ووجّه العميل للواتساب أو الديمو المجاني. ممنوع الاستنتاج أو التأليف.]";

$messages = [['role' => 'system', 'content' => $system]];
foreach ($hist as $h) {
  if (!isset($h['role'], $h['content'])) continue;
  $messages[] = [
    'role'    => ($h['role'] === 'assistant') ? 'assistant' : 'user',
    'content' => mb_substr((string)$h['content'], 0, 600),
  ];
}
$messages[] = ['role' => 'user', 'content' => $msg];

// ---------- call OpenAI ----------
$payload = json_encode([
  'model'             => MODEL,
  'messages'          => $messages,
  'max_tokens'        => MAX_TOKENS,
  'temperature'       => 0.6,
  'frequency_penalty' => 0.3,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => $payload,
  CURLOPT_TIMEOUT        => TIMEOUT_SEC,
  CURLOPT_HTTPHEADER     => [
    'Content-Type: application/json',
    'Authorization: Bearer ' . OPENAI_KEY,
  ],
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($resp === false || $code < 200 || $code >= 300) {
  logError('upstream http_' . $code . ' curl:' . $err . ' body:' . mb_substr((string)$resp, 0, 300));
  http_response_code(502);
  echo json_encode(['error' => 'upstream_failed']);
  exit;
}

$json  = json_decode($resp, true);
$reply = trim((string)($json['choices'][0]['message']['content'] ?? ''));

if ($reply === '') {
  logError('empty_reply body:' . mb_substr((string)$resp, 0, 300));
  http_response_code(502);
  echo json_encode(['error' => 'empty_reply']);
  exit;
}

// ---------- save conversation by IP ----------
try {
  $logDir = __DIR__ . '/logs';
  if (!is_dir($logDir)) {
    @mkdir($logDir, 0750, true);
    @file_put_contents($logDir . '/.htaccess', "Require all denied\nDeny from all\n");
    @file_put_contents($logDir . '/index.html', '');
  }
  $logFile = $logDir . '/chat-' . md5($ip) . '.jsonl';
  if (!is_file($logFile) || filesize($logFile) < 2097152) {
    @file_put_contents($logFile, json_encode([
      't'    => date('c'),
      'ip'   => $ip,
      'lang' => $lang,
      'page' => $page,
      'user' => mb_substr($msg, 0, 600),
      'amer' => mb_substr($reply, 0, 1000),
    ], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
  }
} catch (Throwable $t) {}

echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);
