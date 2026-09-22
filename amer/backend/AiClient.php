<?php
/* AGENZA Amer — AI dispatcher: OpenAI or Gemini, switched by AI_PROVIDER.
   Usage: AmerAI::chat($messages, $opts) / AmerAI::ping() / AmerAI::label() */
declare(strict_types=1);

class AmerAI {
  public static function provider(): string {
    $p = strtolower(trim((string)AmerConfig::get('AI_PROVIDER', 'openai')));
    return ($p === 'gemini') ? 'gemini' : 'openai';
  }

  public static function model(): string {
    return self::provider() === 'gemini'
      ? (string)AmerConfig::get('GEMINI_MODEL', 'gemini-2.5-flash')
      : AmerConfig::model();
  }

  public static function keyPresent(): bool {
    $k = self::provider() === 'gemini'
      ? (string)AmerConfig::get('GEMINI_API_KEY', '')
      : AmerConfig::openaiKey();
    return $k !== '' && strpos($k, 'your_') !== 0;
  }

  public static function chat(array $messages, array $opts = []) {
    return self::provider() === 'gemini'
      ? AmerGemini::chat($messages, $opts)
      : AmerOpenAI::chat($messages, $opts);
  }

  public static function label(): string {
    return self::provider() === 'gemini'
      ? ('Gemini (مجاني) — ' . self::model())
      : ('OpenAI (مدفوع) — ' . self::model());
  }

  public static function ping(): array {
    if (!self::keyPresent()) {
      return ['ok' => false, 'error' => 'المفتاح ناقص — ضعه في Environment Variables'];
    }
    if (self::provider() === 'gemini') {
      $r = AmerGemini::chat(
        [['role' => 'user', 'content' => 'Reply with exactly: ok']],
        ['max_tokens' => 5, 'temperature' => 0, 'timeout' => 20]
      );
    } else {
      return AmerOpenAI::ping();
    }
    if (!empty($r['ok'])) return ['ok' => true, 'reply' => $r['reply']];
    $http = isset($r['http']) ? (int)$r['http'] : 0;
    $hints = [
      400 => 'المفتاح غلط — انسخ مفتاحاً جديداً من Google AI Studio',
      403 => 'المفتاح محظور أو مقيد — راجع إعداداته',
      404 => 'اسم الموديل غلط — استخدم gemini-2.0-flash',
      429 => 'عديت الحد المجاني للدقيقة — استنى دقيقة وجرب',
    ];
    $hint = isset($hints[$http]) ? $hints[$http] : ('راجع Render ← Logs, HTTP ' . $http);
    $err = (string)($r['error'] ?? 'unknown');
    if (strpos($err, 'blocked:') === 0) $hint = 'رد تجريبي بسيط — على الموقع الحقيقي هيشتغل عادي';
    return ['ok' => false, 'error' => $err . ' (HTTP ' . $http . ') — ' . $hint];
  }
}
