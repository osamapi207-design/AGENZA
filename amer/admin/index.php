<?php
/* AGENZA Amer — Admin Dashboard (session auth, server-side only) */
declare(strict_types=1);
session_start();

require __DIR__ . '/../backend/bootstrap.php';
require __DIR__ . '/../backend/Config.php';
require __DIR__ . '/../backend/Store.php';
require __DIR__ . '/../backend/Logger.php';
require __DIR__ . '/../backend/Knowledge.php';
require __DIR__ . '/../backend/OpenAIClient.php';
amer_load_env();

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function isAuthed(): bool { return !empty($_SESSION['amer_admin']); }

$ADMIN_USER = (string)AmerConfig::get('ADMIN_USER', 'admin');
$ADMIN_PASS = (string)AmerConfig::get('ADMIN_PASS', '');

// login / logout
if (isset($_GET['logout'])) { unset($_SESSION['amer_admin']); header('Location: index.php'); exit; }
$loginErr = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['do_login'])) {
  $u = (string)($_POST['u'] ?? '');
  $p = (string)($_POST['p'] ?? '');
  if ($ADMIN_PASS !== '' && hash_equals($ADMIN_USER, $u) && hash_equals($ADMIN_PASS, $p)) {
    $_SESSION['amer_admin'] = true;
    header('Location: index.php');
    exit;
  }
  $loginErr = 'بيانات الدخول غير صحيحة.';
}
if (!isAuthed()) {
  echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Amer Admin</title><style>body{background:#060e16;color:#eaf4fb;font-family:sans-serif;display:grid;place-items:center;min-height:100vh;margin:0}form{background:#0a1a28;border:1px solid #38e1ff55;padding:30px;border-radius:16px;width:min(360px,92vw)}input{width:100%;padding:11px;margin:7px 0;border-radius:10px;border:1px solid #ffffff22;background:#ffffff0d;color:#fff;box-sizing:border-box}button{width:100%;padding:12px;border:0;border-radius:10px;background:linear-gradient(135deg,#4fe3ff,#0b6fa0);font-weight:800;cursor:pointer}.err{color:#ff9a9a;font-size:.85rem}</style></head><body><form method="post"><h3>🔐 Amer Admin</h3>' .
    ($loginErr ? '<p class="err">' . e($loginErr) . '</p>' : '') .
    '<input name="u" placeholder="Username" autocomplete="username"><input name="p" type="password" placeholder="Password" autocomplete="current-password"><button name="do_login" value="1">دخول</button></form></body></html>';
  exit;
}

$tab = (string)($_GET['tab'] ?? 'overview');
$msg = '';

// knowledge save (whitelisted files, with .bak)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_kb'])) {
  $f = (string)($_POST['file'] ?? '');
  $allowed = ['communication.md', 'pricing.md', 'sales-principles.md', 'website.md'];
  if (in_array($f, $allowed, true)) {
    $p = AMER_KNOWLEDGE . '/' . $f;
    if (is_file($p)) @copy($p, $p . '.bak');
    @file_put_contents($p, (string)($_POST['content'] ?? ''));
    $msg = 'تم حفظ ' . $f . ' (ونسخة احتياطية .bak).';
  }
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['clear_log'])) {
  AmerLogger::clear((string)$_POST['clear_log']);
  $msg = 'تم مسح السجل.';
}

$convs = AmerStore::read('conversations');
$custs = AmerStore::read('customers');
$usage = AmerStore::read('usage');
$today = date('Y-m-d');
$apiStatus = null;
if ($tab === 'api' && isset($_GET['ping'])) {
  $apiStatus = AmerOpenAI::ping();
}

function leadBadge($s) {
  $c = ['cold' => '#93a9bb', 'warm' => '#ffce3a', 'hot' => '#ff7a59', 'qualified' => '#38e1ff', 'converted' => '#22ff88', 'lost' => '#ff6b6b'];
  return '<span style="background:' . ($c[$s] ?? '#888') . '22;color:' . ($c[$s] ?? '#888') . ';border:1px solid ' . ($c[$s] ?? '#888') . ';padding:2px 10px;border-radius:99px;font-size:.75rem;font-weight:700">' . htmlspecialchars((string)$s) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Amer Admin — AGENZA</title>
<style>
*{box-sizing:border-box}body{background:#060e16;color:#eaf4fb;font-family:'Segoe UI',Tahoma,sans-serif;margin:0}
.top{display:flex;gap:10px;align-items:center;padding:14px 20px;border-bottom:1px solid #38e1ff33;background:#0a1a28;position:sticky;top:0;z-index:5;flex-wrap:wrap}
.top b{font-size:1.1rem}.top nav{display:flex;gap:6px;flex-wrap:wrap;margin-inline-start:auto}
.top a{color:#cfe0ec;text-decoration:none;font-size:.82rem;padding:8px 13px;border-radius:99px;border:1px solid transparent}
.top a.on,.top a:hover{background:#38e1ff1c;border-color:#38e1ff55;color:#fff}
.wrap{padding:22px;max-width:1100px;margin:0 auto}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:18px}
.card{background:#0d2233;border:1px solid #38e1ff2e;border-radius:14px;padding:16px;text-align:center}
.card b{font-size:1.7rem;color:#38e1ff;display:block}
.card small{color:#93a9bb}
table{width:100%;border-collapse:collapse;font-size:.85rem;background:#0a1a28;border-radius:12px;overflow:hidden}
th,td{padding:10px 12px;border-bottom:1px solid #ffffff12;text-align:start;vertical-align:top}
th{color:#38e1ff;background:#38e1ff0d}
a{color:#7de9ff}
textarea{width:100%;min-height:420px;background:#04090f;color:#d6f7ff;border:1px solid #38e1ff33;border-radius:12px;padding:14px;font-family:monospace;font-size:.82rem;direction:ltr}
.btn{background:linear-gradient(135deg,#4fe3ff,#0b6fa0);border:0;border-radius:10px;padding:10px 20px;font-weight:800;cursor:pointer;color:#03161f}
.note{background:#38e1ff12;border:1px solid #38e1ff33;border-radius:12px;padding:12px 16px;font-size:.85rem;color:#cfe0ec;margin-bottom:14px}
pre{background:#04090f;border:1px solid #ffffff14;border-radius:12px;padding:14px;overflow:auto;font-size:.75rem;max-height:420px}
.ok{color:#22ff88}.bad{color:#ff6b6b}
@media(max-width:640px){table{font-size:.75rem}th,td{padding:7px}}
</style>
</head>
<body>
<div class="top"><b>🤖 Amer Admin</b><nav>
<a href="?tab=overview" class="<?= $tab==='overview'?'on':'' ?>">نظرة</a>
<a href="?tab=conversations" class="<?= in_array($tab,['conversations','conv'],true)?'on':'' ?>">المحادثات</a>
<a href="?tab=customers" class="<?= $tab==='customers'?'on':'' ?>">العملاء</a>
<a href="?tab=leads" class="<?= $tab==='leads'?'on':'' ?>">الـ Leads</a>
<a href="?tab=knowledge" class="<?= $tab==='knowledge'?'on':'' ?>">المعرفة</a>
<a href="?tab=usage" class="<?= $tab==='usage'?'on':'' ?>">الاستخدام</a>
<a href="?tab=logs" class="<?= $tab==='logs'?'on':'' ?>">السجلات</a>
<a href="?tab=api" class="<?= $tab==='api'?'on':'' ?>">الـ API</a>
<a href="?logout=1">خروج</a></nav></div>
<div class="wrap">
<?php if ($msg): ?><div class="note"><?= e($msg) ?></div><?php endif; ?>

<?php if ($tab === 'overview'):
  $hot = 0; foreach ($custs as $c) { if (in_array($c['lead_status'] ?? '', ['hot','qualified','converted'], true)) $hot++; }
  $t = $usage[$today] ?? ['req'=>0,'ok'=>0,'err'=>0];
?>
<div class="cards">
<div class="card"><b><?= count($convs) ?></b><small>محادثات</small></div>
<div class="card"><b><?= count($custs) ?></b><small>عملاء</small></div>
<div class="card"><b><?= $hot ?></b><small>Leads ساخنة+</small></div>
<div class="card"><b><?= (int)$t['req'] ?></b><small>طلبات اليوم</small></div>
<div class="card"><b><?= (int)$t['err'] ?></b><small>أخطاء اليوم</small></div>
</div>
<div class="note">💡 عدّل أسلوب عامر من <b>المعرفة ← communication.md</b>، والأسعار من <b>pricing.md</b> — بدون لمس أي كود. بعد التجربة احذف <b>api/test.php</b> من الاستضافة.</div>

<?php elseif ($tab === 'conversations'):
  $list = array_values($convs);
  usort($list, function ($a,$b){ return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''); });
?>
<table><tr><th>ID</th><th>العميل</th><th>رسائل</th><th>آخر تحديث</th><th></th></tr>
<?php foreach (array_slice($list, 0, 100) as $c):
  $cu = $custs[$c['customer_id']] ?? null; ?>
<tr><td><code><?= e(substr($c['id'],0,8)) ?></code></td>
<td><?= e($cu['name'] ?? '—') ?> <?= $cu ? leadBadge($cu['lead_status'] ?? 'cold') : '' ?></td>
<td><?= count($c['turns'] ?? []) ?></td><td><?= e($c['updated_at'] ?? '') ?></td>
<td><a href="?tab=conv&id=<?= e($c['id']) ?>">عرض</a></td></tr>
<?php endforeach; ?></table>

<?php elseif ($tab === 'conv'):
  $c = $convs[(string)($_GET['id'] ?? '')] ?? null;
  if (!$c) { echo '<div class="note">محادثة غير موجودة.</div>'; }
  else { $cu = $custs[$c['customer_id']] ?? [];
    echo '<div class="note">👤 ' . e($cu['name'] ?? 'بدون اسم') . ' ' . leadBadge($cu['lead_status'] ?? 'cold') .
      ' • 📞 ' . e($cu['phone'] ?? '—') . ' • 🏢 ' . e($cu['business'] ?? '—') .
      ' • اعتراضات: ' . e(implode('، ', $cu['objections'] ?? []) ?: '—') . '</div>';
    foreach (($c['turns'] ?? []) as $t) {
      $who = ($t['role'] ?? '') === 'assistant' ? 'عامر 🤖' : 'العميل 👤';
      echo '<div style="background:#0d2233;border:1px solid #38e1ff22;border-radius:10px;padding:10px 14px;margin-bottom:8px;font-size:.88rem"><b>' . $who . '</b><br>' . nl2br(e($t['content'] ?? '')) . '</div>';
    }
  } ?>

<?php elseif ($tab === 'customers' || $tab === 'leads'):
  $onlyLeads = ($tab === 'leads');
  $list = array_values($custs);
  usort($list, function ($a,$b){ return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''); }); ?>
<table><tr><th>الاسم</th><th>الهاتف</th><th>النشاط</th><th>الميزانية</th><th>الحالة</th><th>تحديث</th></tr>
<?php foreach ($list as $c) {
  if ($onlyLeads && in_array($c['lead_status'] ?? 'cold', ['cold'], true)) continue; ?>
<tr><td><?= e($c['name'] ?? '—') ?></td><td><code><?= e($c['phone'] ?? '—') ?></code></td>
<td><?= e($c['business'] ?? '—') ?></td><td><?= e($c['budget'] ?? '—') ?></td>
<td><?= leadBadge($c['lead_status'] ?? 'cold') ?></td><td><?= e(substr($c['updated_at'] ?? '', 0, 16)) ?></td></tr>
<?php } ?></table>

<?php elseif ($tab === 'knowledge'):
  $files = ['communication.md'=>'🎙️ الأسلوب','pricing.md'=>'💰 الأسعار','sales-principles.md'=>'📈 البيع','website.md'=>'🌐 الموقع'];
  $f = (string)($_GET['f'] ?? 'pricing.md');
  if (!isset($files[$f])) $f = 'pricing.md';
  foreach ($files as $k => $label) echo '<a class="btn" style="margin:0 4px 10px 0;text-decoration:none;display:inline-block" href="?tab=knowledge&f=' . $k . '">' . $label . '</a>';
  $content = is_file(AMER_KNOWLEDGE . '/' . $f) ? file_get_contents(AMER_KNOWLEDGE . '/' . $f) : ''; ?>
<form method="post"><input type="hidden" name="file" value="<?= e($f) ?>">
<textarea name="content"><?= e($content) ?></textarea><br><br>
<button class="btn" name="save_kb" value="1">💾 حفظ (مع نسخة .bak)</button></form>

<?php elseif ($tab === 'usage'):
  $rows = $usage; krsort($rows); ?>
<table><tr><th>اليوم</th><th>طلبات</th><th>ناجح</th><th>أخطاء</th><th>tokens in/out</th></tr>
<?php foreach (array_slice($rows, 0, 30, true) as $d => $u): ?>
<tr><td><?= e($d) ?></td><td><?= (int)$u['req'] ?></td><td class="ok"><?= (int)$u['ok'] ?></td><td class="bad"><?= (int)$u['err'] ?></td><td><?= (int)$u['in'] ?> / <?= (int)$u['out'] ?></td></tr>
<?php endforeach; ?></table>

<?php elseif ($tab === 'logs'):
  $lf = (string)($_GET['f'] ?? 'requests.log');
  if (!in_array($lf, ['requests.log','errors.log'], true)) $lf = 'requests.log'; ?>
<a class="btn" style="text-decoration:none" href="?tab=logs&f=requests.log">requests</a>
<a class="btn" style="text-decoration:none" href="?tab=logs&f=errors.log">errors</a>
<form method="post" style="display:inline"><button class="btn" name="clear_log" value="<?= e($lf) ?>">🗑 مسح</button></form>
<pre><?php foreach (AmerLogger::tail($lf) as $r) echo e(json_encode($r, JSON_UNESCAPED_UNICODE)) . "\n"; ?></pre>

<?php elseif ($tab === 'api'): ?>
<div class="note">الموديل: <b><?= e(AmerConfig::model()) ?></b> • الحرارة: <?= e((string)AmerConfig::temperature()) ?> • حد الرسائل: <?= e((string)AmerConfig::ratePerMin()) ?>/دقيقة • المفتاح: <?= AmerConfig::openaiKey() !== '' ? '<span class="ok">موجود ✔</span>' : '<span class="bad">ناقص — ضعه في amer/.env</span>' ?></div>
<a class="btn" style="text-decoration:none" href="?tab=api&ping=1">🔌 اختبار الاتصال بـ OpenAI</a><br><br>
<?php if ($apiStatus): ?>
<div class="note"><?= $apiStatus['ok'] ? '<span class="ok">✔ متصل — رد: ' . e($apiStatus['reply']) . '</span>' : '<span class="bad">✘ فشل: ' . e($apiStatus['error']) . '</span>' ?></div>
<?php endif;
  $kf = AmerKnowledge::listFiles();
  echo '<table><tr><th>ملف المعرفة</th><th>الحجم</th></tr>';
  foreach ($kf as $k => $s) echo '<tr><td>' . e($k) . '</td><td>' . ($s === false ? '<span class="bad">ناقص!</span>' : number_format($s) . ' bytes') . '</td></tr>';
  echo '</table>';
endif; ?>
</div>
</body>
</html>
