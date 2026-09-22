# Amer — AI Sales Agent (AGENZA)

موظف مبيعات افتراضي بشخصية **"عامر"**: يفهم العميل، يرد كإنسان محترف،
يستخرج بيانات الـ Lead، يتعامل مع الاعتراضات، ولا يخترع أسعاراً أبداً.
مبني على نفس Stack الموقع الحالي (PHP 7.4+ بدون مكتبات) ليعمل على أي استضافة مشتركة.

## البنية

```
amer/
├── knowledge/            # المعرفة (Markdown يقرأها الوكيل)
│   ├── communication.md  # أسلوب عامر وقواعده  ← عدّل من هنا
│   ├── pricing.md        # الأسعار والباقات (PLACEHOLDERS — ضع أرقامك)
│   ├── sales-principles.md
│   └── website.md        # خلاصة الموقع (Retrieval للمقاطع المناسبة فقط)
├── backend/              # محرك الـ Workflow (Modular Nodes)
│   ├── api.php           # نقطة الدخول: receive→intent→knowledge→memory→generate→persist→respond
│   ├── Agent.php         # بناء البرومبت + استدعاء OpenAI + إعادة المحاولة
│   ├── OpenAIClient.php  # عميل OpenAI (المفتاح من env فقط)
│   ├── Validator.php     # فحص الرد: أسعار/أسرار/أسلوب/طول/تكرار
│   ├── Intent.php        # كشف النية + Handoff + هجمات Prompt Injection
│   ├── Memory.php        # ذاكرة قصيرة/طويلة + Lead Score (cold→converted)
│   ├── Knowledge.php     # تحميل وتقطيع واسترجاع المعرفة
│   ├── Workflow.php      # مشغّل الـ Nodes (أضف Nodes جديدة هنا)
│   ├── Store.php         # قاعدة JSON بملفات (تُستبدل بـ SQLite لاحقاً)
│   ├── Logger.php        # requests/errors/usage (بدون أسرار)
│   ├── Config.php        # إعدادات من Environment فقط
│   └── bootstrap.php
├── admin/                # لوحة التحكم (جلسات + باسورد من .env)
├── database/             # تُنشأ تلقائياً (محادثات/عملاء/استخدام) — محمية
├── .env.example          # انسخه إلى .env واملأ القيم
└── .gitignore
```

## التشغيل (5 دقائق)

1. ارفع مجلد `amer/` على الاستضافة بجانب صفحات الموقع.
2. انسخ `amer/.env.example` إلى `amer/.env` واملأ:
   `OPENAI_API_KEY` ، `ADMIN_USER` ، `ADMIN_PASS` ، `SITE_URL`.
   ⚠️ المفتاح كان ظاهراً في الشات — **اعمل Rotate من OpenAI Dashboard** قبل التشغيل.
3. افتح `yourdomain.com/amer/admin/` وتأكد من تبويب **API** (اختبار الاتصال ✔).
4. ضع أسعارك الحقيقية في `amer/knowledge/pricing.md` (أو من تبويب **المعرفة**).
5. الويدجت في الموقع يتحدث تلقائياً مع `amer/backend/api.php`.

## الأمان

- المفتاح في `.env` على السيرفر فقط — ممنوع في HTML/JS/GitHub.
- `database/` محمية بـ `.htaccess` ولا تُعرض للويب.
- Rate limiting + تحقق مدخلات + CORS same-origin + مصادقة الأدمن.
- حماية Prompt Injection: كشف + رفض مهذب + فلتر مخرجات يمنع تسريب (مفاتيح/برومبت/ملفات).
- بعد التأكد من عمل النظام: احذف `api/test.php` القديم، وبدّل `api/agent.php` (يحمل مفتاحاً حقيقياً قديماً) ثم اعمل Rotate للمفاتيح.

## إضافة Node جديدة (مثال WhatsApp لاحقاً)

```php
// في amer/backend/api.php ضمن مصفوفة الـ Workflow:
['name' => 'whatsapp_notify', 'run' => function (&$c) {
  if ($c['lead_status'] === 'hot') AmerWhatsApp::notify($c); // ملف جديد لاحقاً
}],
```

## التطوير مستقبلاً

WhatsApp / Telegram / CRM / Google Sheets: ملفات جديدة في `backend/` + Node في الـ Workflow —
البنية جاهزة بدون تغيير الأساس.
