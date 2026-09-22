<?php
/* AGENZA Amer — AI dispatcher: OpenAI or Gemini, switched by AI_PROVIDER.
   Usage: AmerAI::chat($messages, $opts) / AmerAI::ping() / AmerAI::label() */
declare(strict_types=1);

class AmerAI {
  public static function provider(): string {
    $p = strtolower(trim((string)AmerConfig::get('AI_PROVIDER', 'openrouter')));
    if ($p === 'gemini') return 'gemini';
    if ($p === 'openai') return 'openai';
    return 'openrouter'; // default: one key → OpenAI + Gemini
  }

  public static function model(): string {
    $p = self::provider();
    if ($p === 'gemini') return (string)AmerConfig::get('GEMINI_MODEL', 'gemini-2.0-flash');
    if ($p === 'openai') return AmerConfig::model();
    return (string)AmerConfig::get('OPENROUTER_MODEL', 'google/gemini-2.0-flash-001');
  }

  public static function keyPresent(): bool {
    $p = self::provider();
    $k = ($p === 'gemini') ? (string)AmerConfig::get('GEMINI_API_KEY', '')
       : (($p === 'openai') ? AmerConfig::openaiKey()
       : (string)AmerConfig::get('OPENROUTER_API_KEY', ''));
    return $k !== '' && strpos($k, 'your_') !== 0;
  }

  public static function chat(array $messages, array $opts = []) {
    $p = self::provider();
    if ($p === 'gemini') return AmerGemini::chat($messages, $opts);
    if ($p === 'openai') return AmerOpenAI::chat($messages, $opts);
    return AmerOpenRouter::chat($messages, $opts);
  }

  public static function label(): string {
    $p = self::provider();
    if ($p === 'gemini') return ('Gemini (مجاني) — ' . self::model());
    if ($p === 'openai') return ('OpenAI (مدفوع) — ' . self::model());
    return ('OpenRouter (OpenAI + Gemini) — ' . self::model());
  }

  public static function ping(): array {
    if (!self::keyPresent()) {
      return ['ok' => false, 'error' => 'المفتاح ناقص — ضعه في Environment Variables'];
    }
    if (self::provider() === 'openrouter') {
      $r = AmerOpenRouter::chat(
        [['role' => 'user', 'content' => 'Reply with exactly: ok']],
        ['max_tokens' => 5, 'temperature' => 0, 'timeout' => 30]
      );
      if (!empty($r['ok'])) {
        $used = isset($r['model_used']) ? (' [الموديل: ' . $r['model_used'] . ']') : '';
        return ['ok' => true, 'reply' => $r['reply'] . $used];
      }
      $http = isset($r['http']) ? (int)$r['http'] : 0;
      $hints = [
        400 => 'الطلب مرفوض — راجع اسم الموديل في OPENROUTER_MODEL',
        401 => 'مفتاح OpenRouter غلط أو ملغي — انسخ واحداً جديداً من openrouter.ai/keys',
        402 => 'مفيش رصيد في OpenRouter — اشحن من openrouter.ai (يبدأ من $5) أو استخدم موديل :free',
        429 => 'ضغط مؤقت — استنى دقيقة وجرب',
      ];
      $hint = isset($hints[$http]) ? $hints[$http] : ('راجع Render ← Logs, HTTP ' . $http);
      return ['ok' => false, 'error' => ((string)($r['error'] ?? 'unknown')) . ' (HTTP ' . $http . ') — ' . $hint];
    }
    if (self::provider() === 'gemini') {
      $r = AmerGemini::chat(
        [['role' => 'user', 'content' => 'Reply with exactly: ok']],
        ['max_tokens' => 5, 'temperature' => 0, 'timeout' => 20]
      );
    } else {
      return AmerOpenAI::ping();
    }
    if (!empty($r['ok'])) {
      $used = isset($r['model_used']) ? (' [الموديل: ' . $r['model_used'] . ']') : '';
      return ['ok' => true, 'reply' => $r['reply'] . $used];
    }
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
