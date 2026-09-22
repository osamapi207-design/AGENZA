<?php
/* AGENZA Amer — OpenAI client (backend only; key never leaves server) */
declare(strict_types=1);

class AmerOpenAI {
  public static function chat(array $messages, array $opts = []): array {
    $key = AmerConfig::openaiKey();
    if ($key === '') {
      return ['ok' => false, 'error' => 'missing_key'];
    }
    $payload = json_encode([
      'model'             => $opts['model'] ?? AmerConfig::model(),
      'messages'          => $messages,
      'max_tokens'        => $opts['max_tokens'] ?? AmerConfig::maxTokens(),
      'temperature'       => $opts['temperature'] ?? AmerConfig::temperature(),
      'frequency_penalty' => 0.3,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => $payload,
      CURLOPT_TIMEOUT        => $opts['timeout'] ?? 25,
      CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $key,
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
      return ['ok' => false, 'error' => 'empty_reply'];
    }
    return [
      'ok' => true,
      'reply' => $reply,
      'in'  => (int)($j['usage']['prompt_tokens'] ?? 0),
      'out' => (int)($j['usage']['completion_tokens'] ?? 0),
    ];
  }

  /** Tiny key/status check for admin (no secrets in output) */
  public static function ping(): array {
    $r = self::chat(
      [['role' => 'user', 'content' => 'Reply with exactly: ok']],
      ['max_tokens' => 5, 'temperature' => 0, 'timeout' => 20]
    );
    if ($r['ok']) return ['ok' => true, 'reply' => $r['reply']];
    return ['ok' => false, 'error' => $r['error'] ?? 'unknown'];
  }
}
