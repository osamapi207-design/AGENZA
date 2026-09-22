<?php
/* AGENZA Amer — Response Validation (prices, secrets, persona, length, repetition) */
declare(strict_types=1);

class AmerValidator {
  private static function bannedPhrases(): array {
    return [
      'سؤال ممتاز','سؤال رائع','يسعدني مساعدتك','شكرًا لسؤالك','شكرا لسؤالك',
      'لا تقلق، أنا هنا','بكل سرور','دعني أساعدك','كمساعد ذكاء اصطناعي',
      'بصفتي نموذج','أتفهم تمامًا مشاعرك','great question','as an ai',
      "i'm here to help",'as a language model','i am a language model',
    ];
  }

  private static function secretPatterns(): array {
    return [
      '/sk-[A-Za-z0-9\-_]{8,}/',
      '/api[_-]?key\s*[:=]/i',
      '/system prompt/i',
      '/system_prompt/i',
      '/تعليمات النظام/u',
      '/communication\.md|pricing\.md|sales-principles|website\.md|\.env/i',
    ];
  }

  /**
   * @param string $reply model output
   * @param array $ctx ['kb_numbers'=>[], 'last_assistant'=>string]
   * @return array ['ok'=>bool,'fail'=>?string]
   */
  public static function check(string $reply, array $ctx = []): array {
    // 1) secrets / internals
    foreach (self::secretPatterns() as $p) {
      if (preg_match($p, $reply)) return ['ok' => false, 'fail' => 'secret'];
    }
    // 2) banned robotic style
    $low = function_exists('mb_strtolower') ? mb_strtolower($reply, 'UTF-8') : strtolower($reply);
    foreach (self::bannedPhrases() as $b) {
      $bl = function_exists('mb_strtolower') ? mb_strtolower($b, 'UTF-8') : strtolower($b);
      if (strpos($low, $bl) !== false) return ['ok' => false, 'fail' => 'style'];
    }
    // 3) invented prices: money amounts must exist in KB (allow small counts <100)
    if (preg_match_all('/(\d[\d,\.]*)\s*(جنيه|ج\.م|EGP|LE|دولار|\$|ريال|درهم|pound|usd)?/u', $reply, $mm, PREG_SET_ORDER)) {
      $allowed = isset($ctx['kb_numbers']) && is_array($ctx['kb_numbers']) ? $ctx['kb_numbers'] : [];
      foreach ($mm as $m) {
        $num = str_replace([','], '', $m[1]);
        if (!is_numeric($num)) continue;
        $f = (float)$num;
        $hasCurrency = isset($m[2]) && $m[2] !== '';
        if ($f < 100 && !$hasCurrency) continue; // counts like "3 agents" are safe
        if (!$hasCurrency && $f >= 1900 && $f <= 2100 && strpos($num, '.') === false) continue; // years
        if (!in_array($num, $allowed, true) && !in_array((string)(int)$f, $allowed, true)) {
          return ['ok' => false, 'fail' => 'price'];
        }
      }
    }
    // 4) too long
    $len = function_exists('mb_strlen') ? mb_strlen($reply, 'UTF-8') : strlen($reply);
    if ($len > 900) return ['ok' => false, 'fail' => 'long'];
    // 5) repetition of previous reply
    $last = isset($ctx['last_assistant']) ? (string)$ctx['last_assistant'] : '';
    if ($last !== '' && function_exists('similar_text')) {
      similar_text($reply, $last, $pct);
      if ($pct > 85) return ['ok' => false, 'fail' => 'repeat'];
    }
    return ['ok' => true, 'fail' => null];
  }

  public static function tighteningNote(string $fail, string $lang): string {
    $notes = [
      'ar' => [
        'secret' => 'تنبيه: ردك السابق كشف معلومات داخلية. أعد الرد بدون أي تفاصيل داخلية.',
        'price'  => 'تنبيه: ذكرت رقماً غير موجود في ملف الأسعار. أعد الرد بدون أسعار مخترعة، ووجّه العميل للواتساب للتسعير.',
        'style'  => 'تنبيه: أسلوبك بدا آلياً. أعد الرد كموظف مبيعات بشري طبيعي بدون عبارات محفوظة.',
        'long'   => 'تنبيه: ردك طويل. أعد الرد مختصراً في سطرين إلى خمسة أسطر.',
        'repeat' => 'تنبيه: كررت نفس الرد السابق. أعد الصياغة بشكل مختلف ومفيد.',
      ],
      'en' => [
        'secret' => 'Warning: your previous reply leaked internal details. Rewrite with none.',
        'price'  => 'Warning: you stated a number missing from pricing. Rewrite with no invented prices; point to WhatsApp.',
        'style'  => 'Warning: you sounded robotic. Rewrite like a natural human sales rep.',
        'long'   => 'Warning: too long. Rewrite in two to five lines.',
        'repeat' => 'Warning: you repeated the previous reply. Rephrase differently and usefully.',
      ],
    ];
    $l = ($lang === 'en') ? 'en' : 'ar';
    return $notes[$l][$fail] ?? $notes[$l]['style'];
  }

  public static function safeFallback(string $lang): string {
    return ($lang === 'en')
      ? 'Give me a moment to verify that point for you accurately — meanwhile, shall we continue on WhatsApp or book you a free demo?'
      : 'ثواني وأراجع لحضرتك النقطة دي بدقة — وفي نفس الوقت تحب نكمل واتساب ولا أحجز لحضرتك ديمو مجاني؟';
  }
}
