<?php
/* AGENZA Amer — Google Gemini client (FREE tier via AI Studio).
   Same return shape as AmerOpenAI: ['ok','reply','in','out',...] */
declare(strict_types=1);

class AmerGemini {
  public static function chat(array $messages, array $opts = []): array {
    $key = (string)AmerConfig::get('GEMINI_API_KEY', '');
    if ($key === '') {
      return ['ok' => false, 'error' => 'missing_key'];
    }
    $model = (string)AmerConfig::get('GEMINI_MODEL', 'gemini-2.0-flash');

    // OpenAI-style messages → Gemini format
    $system = '';
    $contents = [];
    foreach ($messages as $m) {
      $role = (string)($m['role'] ?? 'user');
      $text = (string)($m['content'] ?? '');
      if ($text === '') continue;
      if ($role === 'system') { $system .= ($system === '' ? '' : "\n\n") . $text; continue; }
      $contents[] = ['role' => ($role === 'assistant' ? 'model' : 'user'),
                     'parts' => [['text' => $text]]];
    }
    $body = ['contents' => $contents,
             'generationConfig' => [
               'maxOutputTokens' => $opts['max_tokens'] ?? AmerConfig::maxTokens(),
               'temperature' => (float)($opts['temperature'] ?? AmerConfig::temperature()),
             ]];
    if ($system !== '') $body['systemInstruction'] = ['parts' => [['text' => $system]]];

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
         . rawurlencode($model) . ':generateContent?key=' . urlencode($key);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
      CURLOPT_TIMEOUT => $opts['timeout'] ?? 25,
      CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $code < 200 || $code >= 300) {
      return ['ok' => false, 'error' => 'upstream_failed', 'http' => $code, 'detail' => $err];
    }
    $j = json_decode((string)$resp, true);
    $parts = $j['candidates'][0]['content']['parts'] ?? [];
    $reply = '';
    foreach ((array)$parts as $p) { $reply .= (string)($p['text'] ?? ''); }
    $reply = trim($reply);
    if ($reply === '') {
      $block = (string)($j['promptFeedback']['blockReason'] ?? '');
      return ['ok' => false, 'error' => $block !== '' ? ('blocked:' . $block) : 'empty_reply'];
    }
    return [
      'ok' => true,
      'reply' => $reply,
      'in'  => (int)($j['usageMetadata']['promptTokenCount'] ?? 0),
      'out' => (int)($j['usageMetadata']['candidatesTokenCount'] ?? 0),
    ];
  }
}
