<?php
/* AGENZA Amer — bootstrap: env loader, paths, shared helpers (PHP >= 7.4) */
declare(strict_types=1);

define('AMER_ROOT', dirname(__DIR__));
define('AMER_BACKEND', __DIR__);
define('AMER_KNOWLEDGE', AMER_ROOT . '/knowledge');
define('AMER_DB', AMER_ROOT . '/database');

if (!is_dir(AMER_DB)) {
  @mkdir(AMER_DB, 0750, true);
  @file_put_contents(AMER_DB . '/.htaccess', "Require all denied\nDeny from all\n");
  @file_put_contents(AMER_DB . '/index.html', '');
}

/** Load KEY=VALUE pairs from amer/.env (never commit .env) */
function amer_load_env(): void {
  static $done = false;
  if ($done) return;
  $done = true;
  $f = AMER_ROOT . '/.env';
  if (!is_file($f)) return;
  foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    $pos = strpos($line, '=');
    if ($pos === false) continue;
    $k = trim(substr($line, 0, $pos));
    $v = trim(substr($line, $pos + 1));
    if (strlen($v) >= 2 && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) {
      $v = substr($v, 1, -1);
    }
    if (getenv($k) === false) putenv($k . '=' . $v);
    if (!isset($_ENV[$k])) $_ENV[$k] = $v;
  }
}

function amer_json_out(array $data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

function amer_client_ip(): string {
  return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function amer_uid(int $bytes = 8): string {
  try { return bin2hex(random_bytes($bytes)); }
  catch (Throwable $t) { return md5(uniqid((string)mt_rand(), true)); }
}

/** Multibyte-safe truncate */
function amer_cut(string $s, int $n): string {
  $s = trim($s);
  if (function_exists('mb_substr')) return mb_substr($s, 0, $n);
  return substr($s, 0, $n);
}

function amer_log_path(string $name): string {
  $d = AMER_DB . '/logs';
  if (!is_dir($d)) {
    @mkdir($d, 0750, true);
    @file_put_contents($d . '/.htaccess', "Require all denied\nDeny from all\n");
    @file_put_contents($d . '/index.html', '');
  }
  return $d . '/' . $name;
}
