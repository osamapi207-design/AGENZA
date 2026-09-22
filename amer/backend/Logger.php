<?php
/* AGENZA Amer — Logger: requests / errors / usage (never logs secrets) */
declare(strict_types=1);

class AmerLogger {
  private static function append(string $file, array $row): void {
    try {
      $row['t'] = date('c');
      @file_put_contents(
        amer_log_path($file),
        json_encode($row, JSON_UNESCAPED_UNICODE) . "\n",
        FILE_APPEND | LOCK_EX
      );
    } catch (Throwable $t) {}
  }

  public static function request(array $row): void {
    unset($row['api_key'], $row['key'], $row['secret']);
    self::append('requests.log', $row);
  }

  public static function error(string $where, string $msg, array $ctx = []): void {
    unset($ctx['api_key'], $ctx['key'], $ctx['secret']);
    self::append('errors.log', ['where' => $where, 'msg' => amer_cut($msg, 400), 'ctx' => $ctx]);
  }

  public static function usage(bool $ok, int $inTokens = 0, int $outTokens = 0): void {
    try {
      AmerStore::update('usage', function ($u) use ($ok, $inTokens, $outTokens) {
        $d = date('Y-m-d');
        if (!isset($u[$d])) $u[$d] = ['req' => 0, 'ok' => 0, 'err' => 0, 'in' => 0, 'out' => 0];
        $u[$d]['req']++;
        $u[$d][($ok ? 'ok' : 'err')]++;
        $u[$d]['in'] += $inTokens;
        $u[$d]['out'] += $outTokens;
        // keep last 60 days
        ksort($u);
        if (count($u) > 60) $u = array_slice($u, -60, null, true);
        return $u;
      });
    } catch (Throwable $t) {}
  }

  public static function tail(string $file, int $lines = 200): array {
    $p = amer_log_path($file);
    if (!is_file($p)) return [];
    $all = file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$all) return [];
    $sel = array_slice($all, -$lines);
    $out = [];
    foreach ($sel as $l) {
      $d = json_decode($l, true);
      $out[] = is_array($d) ? $d : ['raw' => $l];
    }
    return array_reverse($out);
  }

  public static function clear(string $file): void {
    $allowed = ['requests.log', 'errors.log'];
    if (in_array($file, $allowed, true)) @file_put_contents(amer_log_path($file), '');
  }
}
