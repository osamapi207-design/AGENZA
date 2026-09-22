<?php
/* AGENZA Amer — Store: tiny JSON-file database with locking.
   Tables: conversations / customers. (Swap with SQLite later if needed.) */
declare(strict_types=1);

class AmerStore {
  private static function file(string $table): string {
    $allowed = ['conversations', 'customers', 'usage'];
    if (!in_array($table, $allowed, true)) throw new InvalidArgumentException('bad table');
    return AMER_DB . '/' . $table . '.json';
  }

  public static function read(string $table): array {
    $f = self::file($table);
    if (!is_file($f)) return [];
    $h = @fopen($f, 'r');
    if (!$h) return [];
    $data = [];
    if (flock($h, LOCK_SH)) {
      $raw = stream_get_contents($h);
      flock($h, LOCK_UN);
      $d = json_decode((string)$raw, true);
      if (is_array($d)) $data = $d;
    }
    fclose($h);
    return $data;
  }

  public static function write(string $table, array $data): void {
    $f = self::file($table);
    $h = @fopen($f, 'c+');
    if (!$h) throw new RuntimeException('store unavailable');
    if (!flock($h, LOCK_EX)) { fclose($h); throw new RuntimeException('store lock failed'); }
    ftruncate($h, 0);
    rewind($h);
    fwrite($h, json_encode($data, JSON_UNESCAPED_UNICODE));
    fflush($h);
    flock($h, LOCK_UN);
    fclose($h);
  }

  /** Read → mutate via callback → write, atomically */
  public static function update(string $table, callable $fn): array {
    $data = self::read($table);
    $data = $fn($data);
    if (!is_array($data)) $data = [];
    self::write($table, $data);
    return $data;
  }
}
