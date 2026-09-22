<?php
/* AGENZA Amer — Memory: short-term (recent turns) + long-term profile
   (name/phone/business/budget/objections) + lead scoring. No sensitive
   data stored beyond what the customer willingly shares for the sale. */
declare(strict_types=1);

class AmerMemory {
  const STATUSES = ['cold', 'warm', 'hot', 'qualified', 'converted', 'lost'];

  public static function blankCustomer(string $id, string $ip, string $lang): array {
    return [
      'id' => $id, 'ip' => $ip, 'lang' => $lang,
      'name' => null, 'phone' => null, 'business' => null, 'budget' => null,
      'service' => null, 'timeline' => null,
      'objections' => [], 'lead_status' => 'cold',
      'handoff' => false, 'created_at' => date('c'), 'updated_at' => date('c'),
    ];
  }

  /** Extract durable facts from one customer message (rule-based) */
  public static function extract(string $msg, array $profile): array {
    $m = $msg;
    // name: اسمي X / أنا X (2-3 words max, avoid swallowing sentences)
    if (empty($profile['name'])) {
      if (preg_match('/اسمي\s+([^\s،,.!؟?]{2,20})(?:\s+([^\s،,.!؟?]{2,20}))?/u', $m, $mm)) {
        $profile['name'] = trim($mm[1] . (isset($mm[2]) ? ' ' . $mm[2] : ''));
      } elseif (preg_match('/my name is\s+([a-zA-Z]{2,20})(?:\s+([a-zA-Z]{2,20}))?/i', $m, $mm)) {
        $profile['name'] = trim($mm[1] . (isset($mm[2]) ? ' ' . $mm[2] : ''));
      } elseif (preg_match("/i['’]m\s+([a-zA-Z]{2,20})(?:\s+([a-zA-Z]{2,20}))?/i", $m, $mm)) {
        $w = strtolower($mm[1]);
        if (!in_array($w, ['fine','good','ok','okay','here','looking','interested'], true)) {
          $profile['name'] = trim($mm[1] . (isset($mm[2]) ? ' ' . $mm[2] : ''));
        }
      }
    }
    // egyptian mobile
    if (empty($profile['phone']) && preg_match('/((?:\+?2)?01[0-9]{9})/', $m, $mm)) {
      $profile['phone'] = $mm[1];
    }
    // business type
    if (empty($profile['business'])) {
      $map = [
        'متجر أونلاين' => ['متجر','ملابس','ازياء','أزياء','احذية','أحذية','اكسسوار','منتجات','اونلاين','أونلاين','store','shop','fashion','ecommerce','e-commerce'],
        'مطعم/كافيه' => ['مطعم','كافيه','كافي','اكل','أكل','restaurant','cafe','food'],
        'عيادة/تجميل' => ['عيادة','تجميل','اسنان','أسنان','جلدية','clinic','beauty','dental'],
        'عقارات' => ['عقار','شقق','شقة','فيلا','real estate','property'],
        'تعليم/كورسات' => ['كورس','كورسات','تعليم','اكاديمية','أكاديمية','مدرسة','course','academy','school'],
        'خدمات' => ['شركة','خدمات','صيانة','شحن','services','company','agency'],
      ];
      $low = function_exists('mb_strtolower') ? mb_strtolower($m, 'UTF-8') : strtolower($m);
      foreach ($map as $label => $keys) {
        foreach ($keys as $k) {
          if (strpos($low, function_exists('mb_strtolower') ? mb_strtolower($k, 'UTF-8') : strtolower($k)) !== false) {
            $profile['business'] = $label;
            break 2;
          }
        }
      }
    }
    // budget mention
    if (empty($profile['budget']) && preg_match('/(\d[\d,\.]*)\s*(جنيه|ج\.م|EGP|LE|دولار|\$|ريال|درهم)/u', $m, $mm)) {
      $profile['budget'] = $mm[1] . ' ' . $mm[2];
    }
    // objections memory
    $objMap = [
      'price' => ['غالي','غالية','سعر عالي','expensive','too much','costly'],
      'think' => ['هفكر','هرد عليك','افكر','think about','later','بعدين'],
      'competitor' => ['ارخص','أرخص','منافس','حد تاني','cheaper','competitor'],
      'discount' => ['خصم','تخفيض','discount'],
      'fit' => ['مش مناسبة','مش متأكد','مناسبة ليا','not sure','fit me'],
    ];
    foreach ($objMap as $k => $keys) {
      foreach ($keys as $w) {
        if (stripos($m, $w) !== false && !in_array($k, $profile['objections'], true)) {
          $profile['objections'][] = $k;
          break;
        }
      }
    }
    $profile['updated_at'] = date('c');
    return $profile;
  }

  /** Lead score → status (internal only, never shown to customer) */
  public static function score(array $profile, string $intent, string $msg): string {
    $low = $msg; // raw for phrase checks
    $noWords = ['مش عايز','مش مهتم','لا شكرا','لا شكراً','exactly no','not interested','stop'];
    foreach ($noWords as $w) {
      if (stripos($low, $w) !== false) return 'lost';
    }
    $yesWords = ['موافق','تمام ابدأ','احجز','يلا نبدأ','هدفع','yes start','book now','let’s start','lets start'];
    foreach ($yesWords as $w) {
      if (stripos($low, $w) !== false) return 'converted';
    }
    if (in_array($intent, ['demo', 'human'], true)) return 'hot';
    $filled = 0;
    foreach (['name', 'phone', 'business'] as $f) { if (!empty($profile[$f])) $filled++; }
    if ($filled >= 2) return 'qualified';
    if (in_array($intent, ['pricing', 'objection_price', 'objection_think', 'competitor', 'discount', 'service_info'], true)
        || $filled >= 1 || !empty($profile['objections'])) return 'warm';
    return 'cold';
  }

  /** Short human-readable memory summary injected into the prompt */
  public static function summarize(array $profile): string {
    $bits = [];
    if (!empty($profile['name'])) $bits[] = 'الاسم: ' . $profile['name'];
    if (!empty($profile['business'])) $bits[] = 'النشاط: ' . $profile['business'];
    if (!empty($profile['phone'])) $bits[] = 'هاتف مسجل: نعم';
    if (!empty($profile['budget'])) $bits[] = 'ميزانية مذكورة: ' . $profile['budget'];
    if (!empty($profile['objections'])) $bits[] = 'اعتراضات سابقة: ' . implode('، ', $profile['objections']);
    if (!empty($profile['lead_status'])) $bits[] = 'الحالة الداخلية: ' . $profile['lead_status'];
    if (empty($bits)) return 'لا توجد معلومات سابقة عن هذا العميل.';
    return implode(' | ', $bits);
  }
}
