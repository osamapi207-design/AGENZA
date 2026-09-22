<?php
/* AGENZA Amer — Sales Agent: builds the prompt from
   Personality + Context + Memory + Knowledge + Pricing + Intent,
   calls OpenAI, validates (1 retry), returns the reply. */
declare(strict_types=1);

class AmerAgent {
  public static function buildSystem(array $p): string {
    // $p: lang, pageName, intent, memorySummary, knowledgeText
    $lang = ($p['lang'] === 'en') ? 'en' : 'ar';
    $persona = AmerKnowledge::load('communication.md');
    $sales   = AmerKnowledge::load('sales-principles.md');
    if ($persona === '') $persona = 'You are Amer, a formal polite human sales rep at AGENZA. Mirror customer language. Never mention AI. Plain text, 2-5 lines.';
    if ($sales === '') $sales = 'Sales flow: Understand → Qualify → Recommend → Handle Objections → Convert. Ask one natural question at a time.';

    $kb = isset($p['knowledgeText']) ? (string)$p['knowledgeText'] : '';
    $mem = isset($p['memorySummary']) ? (string)$p['memorySummary'] : '';
    $intent = isset($p['intent']) ? (string)$p['intent'] : 'general';
    $page = isset($p['pageName']) ? (string)$p['pageName'] : '';

    $sys = $persona . "\n\n===== SALES PLAYBOOK =====\n" . $sales;
    if ($kb !== '') $sys .= "\n\n===== RETRIEVED KNOWLEDGE (answer from this) =====\n" . $kb;
    $sys .= "\n\n===== CUSTOMER MEMORY =====\n" . $mem;
    $sys .= "\n\n===== CURRENT CONTEXT =====\nlang=" . $lang . ' | intent=' . $intent . ' | page=' . $page;
    $sys .= "\n\n[Binding order: answer STRICTLY from Retrieved Knowledge + pricing rules above. "
      . "Any company fact (service/plan/price/term/number) must exist verbatim in the knowledge — "
      . "otherwise apologize briefly and route to WhatsApp or a free demo. Never guess.]";
    return $sys;
  }

  /**
   * @return array ['reply'=>string,'validated'=>bool,'fail'=>?string,'in'=>int,'out'=>int]
   */
  public static function generate(string $message, array $history, array $promptParts, string $lang): array {
    $system = self::buildSystem($promptParts);
    $messages = [['role' => 'system', 'content' => $system]];
    foreach ($history as $h) {
      if (!isset($h['role'], $h['content'])) continue;
      $messages[] = [
        'role' => ($h['role'] === 'assistant') ? 'assistant' : 'user',
        'content' => amer_cut((string)$h['content'], 600),
      ];
    }
    $messages[] = ['role' => 'user', 'content' => $message];

    $in = 0; $out = 0;
    for ($attempt = 0; $attempt < 2; $attempt++) {
      $res = AmerOpenAI::chat($messages);
      if (!$res['ok']) {
        return ['reply' => '', 'validated' => false, 'fail' => 'upstream:' . ($res['error'] ?? '?'),
                'in' => $in, 'out' => $out, 'error' => $res['error'] ?? 'upstream_failed'];
      }
      $in += (int)$res['in']; $out += (int)$res['out'];
      $reply = trim($res['reply']);
      $chk = AmerValidator::check($reply, [
        'kb_numbers' => AmerKnowledge::pricingNumbers(),
        'last_assistant' => $promptParts['lastAssistant'] ?? '',
      ]);
      if ($chk['ok']) {
        return ['reply' => $reply, 'validated' => true, 'fail' => null, 'in' => $in, 'out' => $out];
      }
      AmerLogger::error('validation', 'fail=' . $chk['fail'], ['intent' => $promptParts['intent'] ?? '']);
      // one tightening retry
      $messages[] = ['role' => 'assistant', 'content' => $reply];
      $messages[] = ['role' => 'user', 'content' => AmerValidator::tighteningNote((string)$chk['fail'], $lang)];
      if ($attempt === 1) {
        return ['reply' => '', 'validated' => false, 'fail' => $chk['fail'], 'in' => $in, 'out' => $out];
      }
    }
    return ['reply' => '', 'validated' => false, 'fail' => 'unknown', 'in' => $in, 'out' => $out];
  }
}
