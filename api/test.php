<?php
/* AGENZA diagnostics — visit https://yourdomain.com/api/test.php
   Shows server checks WITHOUT exposing the API key.
   Delete this file after debugging if you prefer. */
header('Content-Type: application/json; charset=utf-8');

$out = [
  'php'      => PHP_VERSION,
  'curl'     => function_exists('curl_init'),
  'mbstring' => function_exists('mb_substr'),
  'tmp_writable' => is_writable(sys_get_temp_dir()),
  'logs_writable' => is_writable(__DIR__),
  'method'   => $_SERVER['REQUEST_METHOD'],
];

// key check: only test validity, never print the key
$agent = __DIR__ . '/agent.php';
$key = null;
if (is_file($agent)) {
  $src = file_get_contents($agent);
  if (preg_match("/OPENAI_KEY\\s*=\\s*'([^']+)'/", $src, $m)) $key = $m[1];
}
$out['key_found'] = $key ? (substr($key, 0, 7) . '...' . substr($key, -4)) : false;

if ($key) {
  // real end-to-end test: a tiny chat call (costs a fraction of a cent)
  $ch = curl_init('https://api.openai.com/v1/chat/completions');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_POSTFIELDS => json_encode([
      'model' => 'gpt-4o-mini',
      'messages' => [['role' => 'user', 'content' => 'Reply with exactly: ok']],
      'max_tokens' => 5,
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
  ]);
  $resp = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);
  $out['openai_http'] = $code;
  $out['openai_error'] = $err ?: null;
  $out['openai_key_valid'] = ($code === 200);
  if ($code === 200) {
    $j = json_decode($resp, true);
    $out['chat_reply'] = trim((string)($j['choices'][0]['message']['content'] ?? ''));
    $out['conclusion'] = 'EVERYTHING WORKS — Amer should reply with AI on the site.';
  } else {
    $out['openai_says'] = mb_substr((string)$resp, 0, 400);
    $out['conclusion'] = 'KEY OR QUOTA PROBLEM — check openai_says (usually insufficient_quota or invalid key).';
  }

  // 1) prove the knowledge/persona files are readable on hosting
  $out['files'] = [];
  foreach (['persona-ar.txt','persona-en.txt','knowledge-ar.txt','knowledge-en.txt'] as $f) {
    $p = __DIR__ . '/' . $f;
    $out['files'][$f] = is_file($p) ? (filesize($p) . ' bytes') : 'MISSING — upload it!';
  }

  // 2) FULL end-to-end: call agent.php exactly like the site widget does
  $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/api')), '/');
  $agentUrl = $scheme . '://' . $host . $dir . '/agent.php';
  $ch2 = curl_init($agentUrl);
  curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_TIMEOUT => 35,
    CURLOPT_POSTFIELDS => json_encode([
      'message' => 'ما هي الباقات المتاحة عندكم؟',
      'lang' => 'ar', 'history' => [], 'page' => 'index.html',
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
  ]);
  $resp2 = curl_exec($ch2);
  $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
  $err2  = curl_error($ch2);
  curl_close($ch2);
  $out['e2e_url'] = $agentUrl;
  $out['e2e_http'] = $code2;
  $out['e2e_curl_error'] = $err2 ?: null;
  $j2 = json_decode((string)$resp2, true);
  if ($code2 === 200 && isset($j2['reply'])) {
    $out['e2e_reply_preview'] = mb_substr(trim((string)$j2['reply']), 0, 200);
    $out['e2e_conclusion'] = 'AMER IS LIVE — ChatGPT answered from the knowledge files.';
  } else {
    $out['e2e_raw'] = mb_substr((string)$resp2, 0, 300);
    $out['e2e_conclusion'] = 'AGENT.PHP FAILED — check e2e_http/e2e_raw and api/logs/error.log on hosting.';
  }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
