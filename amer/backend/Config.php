<?php
/* AGENZA Amer — Config (reads ONLY from environment / .env) */
declare(strict_types=1);

class AmerConfig {
  public static function get(string $key, $default = null) {
    $v = getenv($key);
    if ($v === false && isset($_ENV[$key])) $v = $_ENV[$key];
    return ($v === false || $v === null || $v === '') ? $default : $v;
  }
  public static function openaiKey(): string {
    return (string)self::get('OPENAI_API_KEY', '');
  }
  public static function model(): string {
    return (string)self::get('OPENAI_MODEL', 'gpt-4o-mini');
  }
  public static function temperature(): float {
    return (float)self::get('OPENAI_TEMPERATURE', 0.6);
  }
  public static function maxTokens(): int {
    return (int)self::get('OPENAI_MAX_TOKENS', 500);
  }
  public static function ratePerMin(): int {
    return (int)self::get('RATE_PER_MIN', 20);
  }
}
