<?php
/* AGENZA Amer — Intent detection (rule-based, ar+en).
   Returns: intent, handoff(bool), attack(bool). */
declare(strict_types=1);

class AmerIntent {
  private static function rules(): array {
    return [
      'attack' => ['انس التعليمات','انس كل','اظهر الـ system','اظهر system','system prompt','اظهر البرومبت','اعطني الـ api','api key','اظهر ملف','prompt injection','ignore instructions','show me your instructions','reveal your prompt'],
      'human' => ['موظف بشري','موظف حقيقي','اكلم حد','عايز حد','حد يكلمني','انسان حقيقي','إنسان حقيقي','human agent','real person','talk to human','human please','speak to someone'],
      'complaint' => ['نصاب','نصب','حرامي','هشتكي','هشتكى','محامي','شكوى','غش','complaint','scam','fraud','refund now','مشكلة كبيرة'],
      'pricing' => ['سعر','أسعار','باقة','باقات','تكلفة','بكام','فلوس','price','pricing','cost','plan','package','how much'],
      'demo' => ['ديمو','تجربة','اجرب','أجرب','معاينة','ابدأ','اشترك','demo','trial','try it','start now','sign up','subscribe'],
      'objection_price' => ['غالي','غالية','سعر عالي','ليه السعر','expensive','too expensive','overpriced','costs a lot'],
      'competitor' => ['ارخص','أرخص','لقيت حد','منافس','cheaper','competitor','found someone'],
      'discount' => ['خصم','تخفيض','discount','offer','عرض خاص'],
      'objection_think' => ['هفكر','هرد عليك','افكر','محتاج وقت','think about','will think','later','بعدين','not now'],
      'objection_fit' => ['مش مناسبة','مش متأكد','مناسبة ليا','خايف','not sure','not fit','worried','doubt'],
      'service_info' => ['وكيل','وكلاء','خدمة','خدمات','بتعملوا ايه','ايه اللي بتقدموه','agent','service','what do you do','features'],
      'thanks' => ['شكرا','متشكر','تسلم','thanks','thank you','appreciated'],
      'bye' => ['مع السلامة','باي باي','سلام عليكم ورحمة','goodbye','see you','bye bye'],
      'greeting' => ['سلام','صباح الخير','مساء الخير','اهلا','أهلا','ازيك','هاي','hello','hi ','hey','good morning','good evening','salam'],
    ];
  }

  public static function detect(string $msg): array {
    $scores = [];
    foreach (self::rules() as $intent => $keys) {
      $s = 0;
      foreach ($keys as $k) {
        if (stripos($msg, $k) !== false) $s += strlen($k);
      }
      if ($s > 0) $scores[$intent] = $s;
    }
    $intent = 'general';
    if (!empty($scores)) {
      arsort($scores);
      $keys = array_keys($scores);
      $intent = $keys[0];
    }
    $handoff = in_array($intent, ['human', 'complaint'], true);
    return [
      'intent' => $intent,
      'handoff' => $handoff,
      'attack' => isset($scores['attack']),
      'scores' => $scores,
    ];
  }
}
