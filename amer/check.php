<?php
/* AGENZA Amer — public diagnostic page.
   Upload amer/ then visit: yourdomain.com/amer/check.php
   Shows step-by-step what works and what fails. NEVER prints the API key. */
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');

$results = [];
function chk($name, $ok, $detail = '') {
  global $results;
  $results[] = ['name' => $name, 'ok' => (bool)$ok, 'detail' => (string)$detail];
}

// 1) PHP
chk('إصدار PHP', version_compare(PHP_VERSION, '7.0.0', '>='), PHP_VERSION);
chk('مكتبة cURL (للاتصال بـ OpenAI)', function_exists('curl_init'), function_exists('curl_init') ? 'موجودة' : 'ناقصة — فعّلها من cPanel → Select PHP Version');
chk('مكتبة mbstring (للغة العربية)', function_exists('mb_substr'), function_exists('mb_substr') ? 'موجودة' : 'ناقصة — فعّلها من الاستضافة');

// 2) files
$need = [
  'backend/bootstrap.php', 'backend/Config.php', 'backend/Store.php',
  'backend/Logger.php', 'backend/Knowledge.php', 'backend/Memory.php',
  'backend/Intent.php', 'backend/OpenAIClient.php', 'backend/Validator.php',
  'backend/Agent.php', 'backend/Workflow.php', 'backend/api.php',
  'knowledge/communication.md', 'knowledge/pricing.md',
  'knowledge/sales-principles.md', 'knowledge/website.md',
];
$missing = [];
foreach ($need as $f) { if (!is_file(__DIR__ . '/' . $f)) $missing[] = $f; }
chk('ملفات النظام (16 ملف)', empty($missing), empty($missing) ? 'كلها مرفوعة ✔' : 'ناقص: ' . implode('، ', $missing));

// 3) .env + key (masked only)
$envFile = __DIR__ . '/.env';
$hasEnv = is_file($envFile);
chk('ملف .env موجود', $hasEnv, $hasEnv ? 'موجود' : 'انسخ amer/.env.example إلى amer/.env واملأ المفتاح');
$key = '';
if ($hasEnv) {
  foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if (strpos($line, 'OPENAI_API_KEY=') === 0) { $key = trim(substr($line, strlen('OPENAI_API_KEY='))); $key = trim($key, "\"'"); break; }
  }
}
$masked = $key !== '' ? (substr($key, 0, 7) . '...' . substr($key, -4)) : 'فارغ';
chk('مفتاح OpenAI مضبوط', $key !== '' && strpos($key, 'your_') !== 0, $key === '' ? 'المفتاح فارغ' : ('يبدأ بـ: ' . htmlspecialchars($masked)));

// 4) live OpenAI test (tiny, costs ~zero)
$aiOk = false; $aiDetail = 'تخطي — لا يوجد مفتاح';
$replyText = '';
if ($key !== '' && strpos($key, 'your_') !== 0 && function_exists('curl_init')) {
  $ch = curl_init('https://api.openai.com/v1/chat/completions');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 20,
    CURLOPT_POSTFIELDS => json_encode(['model' => 'gpt-4o-mini',
      'messages' => [['role' => 'user', 'content' => 'قل: شغال']],
      'max_tokens' => 10]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
  ]);
  $resp = curl_exec($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  if ($code === 200) {
    $j = json_decode((string)$resp, true);
    $replyText = trim((string)($j['choices'][0]['message']['content'] ?? ''));
    $aiOk = $replyText !== '';
    $aiDetail = $aiOk ? ('رد OpenAI الفعلي: "' . $replyText . '"') : 'رد فارغ';
  } else {
    $aiDetail = 'HTTP ' . $code . ' — ' . mb_substr((string)$resp, 0, 200) . ($err ? (' | curl: ' . $err) : '');
  }
  chk('اتصال OpenAI حي ويرد', $aiOk, $aiDetail);
}

// 5) database writable?
$dbDir = __DIR__ . '/database';
if (!is_dir($dbDir)) @mkdir($dbDir, 0750, true);
chk('مجلد قاعدة البيانات قابل للكتابة', is_writable($dbDir), $dbDir);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>فحص نظام عامر</title>
<style>body{background:#060e16;color:#eaf4fb;font-family:Tahoma,sans-serif;padding:20px;max-width:720px;margin:0 auto}
h2{color:#38e1ff}.row{background:#0a1a28;border:1px solid #38e1ff33;border-radius:12px;padding:12px 16px;margin-bottom:10px}
.ok{color:#22ff88;font-weight:800}.bad{color:#ff6b6b;font-weight:800}
small{color:#93a9bb;display:block;margin-top:4px;word-break:break-all}
.big{font-size:1.2rem;text-align:center;padding:18px;border-radius:14px;margin:18px 0}
</style></head>
<body>
<h2>🔍 فحص نظام عامر (AI Agent)</h2>
<?php foreach ($results as $r): ?>
<div class="row"><span class="<?= $r['ok'] ? 'ok' : 'bad' ?>"><?= $r['ok'] ? '✔' : '✘' ?> <?= htmlspecialchars($r['name']) ?></span>
<?php if ($r['detail'] !== ''): ?><small><?= $r['detail'] ?></small><?php endif; ?></div>
<?php endforeach; ?>
<?php
$allOk = true;
foreach ($results as $r) { if (!$r['ok']) { $allOk = false; break; } }
if ($allOk) echo '<div class="big" style="background:#22ff8822;border:1px solid #22ff88">✅ النظام سليم 100% — عامر يرد بالذكاء الاصطناعي الحقيقي، وأي رد محفوظ تراه يعني أنك تفتح نسخة قديمة (امسح الكاش Ctrl+Shift+R)</div>';
else echo '<div class="big" style="background:#ff6b6b22;border:1px solid #ff6b6b">❌ يوجد عطل — أصلح البنود الحمراء أعلاه (السبب والعلاج مكتوبان تحت كل بند)</div>';
?>
<p><small>ملاحظة أمان: هذه الصفحة لا تعرض المفتاح أبداً. احذفها بعد الفحص لو أحببت.</small></p>
</body>
</html>
