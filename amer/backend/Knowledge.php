<?php
/* AGENZA Amer — Knowledge: loads .md files, chunks by ## headings,
   retrieves only the most relevant chunks (never sends everything). */
declare(strict_types=1);

class AmerKnowledge {
  private static $cache = [];

  public static function load(string $file): string {
    if (isset(self::$cache[$file])) return self::$cache[$file];
    $p = AMER_KNOWLEDGE . '/' . basename($file);
    $c = is_file($p) ? trim((string)@file_get_contents($p)) : '';
    self::$cache[$file] = $c;
    return $c;
  }

  /** Split markdown into ## sections */
  public static function chunks(string $md): array {
    $parts = preg_split('/^##\s+/mu', $md);
    $out = [];
    foreach ($parts as $i => $p) {
      $p = trim((string)$p);
      if ($p === '') continue;
      $title = strtok($p, "\n");
      $out[] = ['title' => amer_cut((string)$title, 80), 'text' => $p];
    }
    return $out;
  }

  /** Significant tokens for overlap scoring (ar+en, drops stopwords) */
  private static function tokens(string $s): array {
    $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    $words = preg_split('/[^\p{L}\p{N}]+/u', (string)$s, -1, PREG_SPLIT_NO_EMPTY);
    $stop = ['ايه','اي','ده','دي','دا','في','من','على','علي','الي','إلى','مع','انا','أنا','انت','هو','هي','احنا','كده','كدا','او','أو','و','لا','ما','مش','عايز','عاوز','لو','كان','يكون','the','a','an','is','are','to','of','for','and','or','in','on','do','you','your','me','my','what','how','much','with','have','has','i','we','it','this','that','there','here','please','pls'];
    $out = [];
    foreach ((array)$words as $w) {
      if (function_exists('mb_strlen')) { if (mb_strlen($w, 'UTF-8') < 3) continue; }
      elseif (strlen($w) < 3) continue;
      if (in_array($w, $stop, true)) continue;
      $out[$w] = true;
    }
    return $out;
  }

  /**
   * Retrieve top chunks from website.md (+ always include pricing when
   * money is discussed). Returns text capped at $maxChars.
   */
  public static function retrieve(string $message, string $intent, int $maxChars = 1600): array {
    $used = [];
    $q = self::tokens($message);
    $scored = [];
    foreach (self::chunks(self::load('website.md')) as $c) {
      $ct = self::tokens($c['title'] . ' ' . $c['text']);
      $score = 0;
      foreach ($q as $w => $_) { if (isset($ct[$w])) $score += (function_exists('mb_strlen') ? mb_strlen($w, 'UTF-8') : strlen($w)); }
      // intent boosts
      if (in_array($intent, ['pricing', 'objection_price', 'discount', 'demo'], true)
          && stripos($c['title'], 'تواصل') === false) { $score += 0; }
      if ($score > 0) $scored[] = ['s' => $score, 'c' => $c];
    }
    usort($scored, function ($a, $b) { return $b['s'] <=> $a['s']; });

    $text = '';
    foreach (array_slice($scored, 0, 3) as $s) {
      $t = '### ' . $s['c']['title'] . "\n" . $s['c']['text'] . "\n\n";
      if ((function_exists('mb_strlen') ? mb_strlen($text . $t, 'UTF-8') : strlen($text . $t)) > $maxChars) break;
      $text .= $t;
      $used[] = 'website.md#' . $s['c']['title'];
    }

    // pricing is the single source of truth for money → include on money intents
    $moneyIntents = ['pricing', 'objection_price', 'discount', 'demo'];
    if (in_array($intent, $moneyIntents, true)
        || preg_match('/\d/', $message)
        || stripos($message, 'سعر') !== false || stripos($message, 'price') !== false) {
      $pricing = trim(self::load('pricing.md'));
      if ($pricing !== '') {
        $text .= "### pricing.md (المصدر الوحيد للأسعار)\n" . amer_cut($pricing, 1500) . "\n";
        $used[] = 'pricing.md';
      }
    }
    return ['text' => trim($text), 'sources' => $used];
  }

  /** Numbers present in pricing (for validator allowlist) */
  public static function pricingNumbers(): array {
    $t = self::load('pricing.md');
    preg_match_all('/\d[\d,\.]*/', $t, $m);
    $out = [];
    foreach ($m[0] as $n) { $out[] = str_replace([','], '', $n); }
    // also allow website stats numbers
    $w = self::load('website.md');
    preg_match_all('/\d[\d,\.]*/', $w, $m2);
    foreach ($m2[0] as $n) { $out[] = str_replace([','], '', $n); }
    return array_values(array_unique($out));
  }

  public static function listFiles(): array {
    $files = ['communication.md', 'pricing.md', 'sales-principles.md', 'website.md'];
    $out = [];
    foreach ($files as $f) {
      $p = AMER_KNOWLEDGE . '/' . $f;
      $out[$f] = is_file($p) ? filesize($p) : false;
    }
    return $out;
  }
}
