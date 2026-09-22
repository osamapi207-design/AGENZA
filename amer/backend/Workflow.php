<?php
/* AGENZA Amer — Workflow engine (n8n-inspired, modular nodes).
   Pipeline: receive → intent → knowledge → memory → generate →
             validate(in Agent) → persist → respond.
   Future nodes (webhook, whatsapp, sheets…) plug in the same way. */
declare(strict_types=1);

class AmerWorkflow {
  /** @param array $nodes each: ['name'=>string,'run'=>callable(&$ctx)] */
  public static function run(array $nodes, array &$ctx): array {
    $ctx['__steps'] = [];
    $t0 = microtime(true);
    foreach ($nodes as $n) {
      $name = $n['name'] ?? 'node';
      $s = microtime(true);
      try {
        $fn = $n['run'];
        $fn($ctx);
        $ctx['__steps'][] = ['node' => $name, 'ms' => (int)((microtime(true) - $s) * 1000), 'ok' => true];
      } catch (Throwable $e) {
        $ctx['__steps'][] = ['node' => $name, 'ms' => (int)((microtime(true) - $s) * 1000), 'ok' => false];
        $ctx['error'] = $name . ':' . $e->getMessage();
        AmerLogger::error('workflow', $ctx['error']);
        break;
      }
      if (!empty($ctx['__halt'])) break;
    }
    $ctx['__total_ms'] = (int)((microtime(true) - $t0) * 1000);
    return $ctx;
  }
}
