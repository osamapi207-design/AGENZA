<?php
/* AGENZA Amer — OpenRouter client (one key → OpenAI + Gemini + more).
   OpenAI-compatible API. Same return shape: ['ok','reply','in','out',...] */
declare(strict_types=1);

class AmerOpenRouter {
  public static function chat(array $messages, array $opts = []): array {
    $key = (string)AmerConfig::get('OPENROUTER_API_KEY', '');
    if ($key === '') {
      return ['ok' => false, 'error' => 'missing_key'];
    }
    $requested = (string)AmerConfig::get('OPENROUTER_MODEL', 'google/gemini-2.0-flash-001');
    // auto-fallback chain across providers (ends guessing forever)
    $models = array_values(array_unique([
      $requested,
      'google/gemini-2.0-flash-001',
      'google/gemini-flash-1.5',
      'openai/gpt-4o-mini',
    ]));
    $clean = [];
    foreach ($messages as $m) {
      if (!isset($m['role'], $m['content'])) continue;
      $clean[] = ['role' => ($m['role'] === 'assistant' ? 'assistant' : 'user'),
                  'content' => amer_cut((string)$m['content'], 600)];
    }
    $lastErr = ['ok' => false, 'error' => 'upstream_failed', 'http' => 0];
    foreach ($models as $model) {
      $r = self::callModel($key, $model, $clean, $opts);
      if (!empty($r['ok'])) { $r['model_used'] = $model; return $r; }
      if ((int)($r['http'] ?? 0) !== 404) return $r; // real error → stop
      $lastErr = $r;
    }
    return $lastErr;
  }

  private static function callModel(string $key, string $model, array $messages, array $opts): array {
    $payload = json_encode([
      'model' => $model,
      'messages' => $messages,
      'max_tokens' => $opts['max_tokens'] ?? AmerConfig::maxTokens(),
      'temperature' => (float)($opts['temperature'] ?? AmerConfig::temperature()),
      'frequency_penalty' => 0.3,
    ], JSON_UNESCAPED_UNICODE);
    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $payload,
      CURLOPT_TIMEOUT => $opts['timeout'] ?? 30,
      CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $key,
        'HTTP-Referer: ' . (string)AmerConfig::get('SITE_URL', 'https://localhost/'),
        'X-Title: AGENZA Amer Agent',
      ],
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $code < 200 || $code >= 300) {
      return ['ok' => false, 'error' => 'upstream_failed', 'http' => $code, 'detail' => $err];
    }
    $j = json_decode((string)$resp, true);
    $reply = trim((string)($j['choices'][0]['message']['content'] ?? ''));
    if ($reply === '') {
      return ['ok' => false, 'error' => 'empty_reply', 'http' => $code];
    }
    return [
      'ok' => true,
      'reply' => $reply,
      'in'  => (int)($j['usage']['prompt_tokens'] ?? 0),
      'out' => (int)($j['usage']['completion_tokens'] ?? 0),
    ];
  }
}
