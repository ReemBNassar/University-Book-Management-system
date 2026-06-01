# UniBook — نظام تبادل الكتب الجامعية (الباك إند)

دليل تشغيل الباك إند المبني بـ **PHP + PostgreSQL**، ومربوط بالفرونت إند (HTML/CSS/Bootstrap) الموجود في المشروع.

---

## توزيع المهام

| الجزء | المسؤول عنه |
|------|-------------|
| **الباك إند (PHP + PostgreSQL)** | Bissan Walid Al-Shaer (220203673) — Heba Hatem Al-Massri (220210421) |

> الباك إند (مجلد `api/` بالكامل، وربط صفحات `login.html` و `student.html` و `admin_dashboard.html` بقاعدة البيانات) من إعداد **بيسان والشاعر** و **هبة الحطاب**.

---

## محتويات المشروع

```
unibook/
├── login.html              ← صفحة الدخول/التسجيل (معدّلة لتتصل بالباك الحقيقي)
├── student.html            ← لوحة الطالب (تصفح/استعارة/طلبات/سجل/إشعارات)
├── admin_dashboard.html    ← لوحة المشرف (طلبات/كتب/تقارير)
├── schema.sql              ← هيكل قاعدة البيانات (نفس ملفكم الأصلي)
├── create_admin.sql        ← إنشاء مشرف يدويًا (طريقة بديلة)
├── reem.png                ← صورة الواجهة (موجودة عندكم)
└── api/                    ← الباك إند (كل ملفات PHP)
    ├── db.php              ← إعدادات الاتصال بقاعدة البيانات ⚙️
    ├── api.js              ← دوال الاتصال للفرونت
    ├── helpers.php         ← صلاحيات + إعدادات مشتركة
    ├── make_admin.php      ← إنشاء حساب مشرف بسهولة (لمرة واحدة)
    ├── signup.php          login.php   me.php   logout.php
    ├── books.php           book_add.php  book_update_status.php  book_delete.php
    ├── borrow_request.php  my_requests.php  request_cancel.php
    ├── requests_all.php    request_decide.php
    ├── notifications.php   my_history.php   reports.php
    └── cron_tasks.php      ← مهمة مجدولة (تذكيرات + المتأخرات)
```

---

## خطوات التشغيل

### 1) المتطلبات
- PHP 8 أو أحدث (مع إضافة `php-pgsql`)
- PostgreSQL

تأكدي إن إضافة pgsql مفعّلة:
```bash
php -m | grep pgsql
```

### 2) إنشاء قاعدة البيانات
```bash
# ادخلي على Postgres
psql -U postgres

# داخل psql:
CREATE DATABASE library_borrowing_management;
\q

# حمّلي الهيكل
psql -U postgres -d library_borrowing_management -f schema.sql
```

### 3) ضبط بيانات الاتصال
انسخي ملف `api/config.sample.php` وسمّي النسخة `api/config.php`، ثم عدّلي القيم حسب جهازك:
```php
return [
    'DB_HOST' => 'localhost',
    'DB_PORT' => '5432',
    'DB_NAME' => 'library_borrowing_management',
    'DB_USER' => 'postgres',
    'DB_PASS' => 'كلمة_سر_بوستجرس_عندك',
];
```
> ملاحظة أمان: ملف `config.php` غير مرفوع على GitHub (محمي بـ `.gitignore`)، فكل واحد ينشئ نسخته على جهازه.

### 4) تشغيل السيرفر
من **داخل مجلد المشروع** (مش مجلد api):
```bash
php -S localhost:8000
```
ثم افتحي في المتصفح:
```
http://localhost:8000/login.html
```

### 5) إنشاء حساب المشرف (أول مرة فقط)
صفحة التسجيل بتنشئ **طلاب** فقط. لإنشاء المشرف:

**الطريقة السهلة:**
1. افتحي `api/make_admin.php` وعدّلي البريد وكلمة السر فيها.
2. افتحي في المتصفح: `http://localhost:8000/api/make_admin.php`
3. **‼️ احذفي الملف `make_admin.php` فورًا بعد نجاحه** (لأسباب أمنية).

بعدها سجّلي دخول بالمشرف من نفس صفحة `login.html` وهيوجّهك للوحة المشرف تلقائيًا.

---

## المهمة المجدولة (التذكيرات + الكتب المتأخرة)

ملف `api/cron_tasks.php` بيعمل حاجتين يوميًا:
- تذكير الطالب قبل موعد الإرجاع بـ 24 ساعة.
- رصد الكتب المتأخرة وإرسال تنبيه.

للتجربة يدويًا:
```bash
php api/cron_tasks.php
```

للجدولة اليومية على لينكس (8 صباحًا مثلاً) عبر crontab:
```
0 8 * * * /usr/bin/php /المسار/الكامل/api/cron_tasks.php
```

---

## ملخص نقاط الـ API

| الملف | الطريقة | الوظيفة | الصلاحية |
|------|---------|---------|----------|
| signup.php | POST | تسجيل طالب جديد | عام |
| login.php | POST | تسجيل الدخول | عام |
| logout.php | POST | تسجيل الخروج | مسجّل |
| me.php | GET | بيانات المستخدم الحالي | مسجّل |
| books.php | GET | عرض/بحث/فلترة الكتب (`?q=&department=&status=`) | عام |
| book_add.php | POST | إضافة كتاب | مشرف |
| book_update_status.php | POST | تغيير حالة كتاب | مشرف |
| book_delete.php | POST | حذف كتاب | مشرف |
| borrow_request.php | POST | طلب استعارة (أو دخول قائمة الانتظار) | طالب |
| my_requests.php | GET | طلبات الطالب | طالب |
| request_cancel.php | POST | إلغاء طلب معلّق | طالب |
| requests_all.php | GET | كل الطلبات (`?status=`) | مشرف |
| request_decide.php | POST | موافقة/رفض/إرجاع (`action: approve\|reject\|return`) | مشرف |
| notifications.php | GET/POST | عرض/تعليم الإشعارات | مسجّل |
| my_history.php | GET | سجل استعارة الطالب | طالب |
| reports.php | GET | تقارير وإحصائيات | مشرف |

---

## ملاحظات مهمة

- **حد الـ 3 كتب** محروس على مستويين: تحقق مبكر في `borrow_request.php`، وعبر الـ trigger في قاعدة البيانات (`handle_borrowing_limit`) عند الموافقة.
- **حالة الكتاب** (Available ↔ Borrowed) بيغيّرها الـ trigger تلقائيًا عند الموافقة/الإرجاع — الباك بيغيّر حالة الطلب فقط.
- **قائمة الانتظار**: لو طلبتي كتابًا مُعارًا، بتتسجّلي في الانتظار، وأول ما يرجع بيوصل إشعار لأول شخص في القائمة.
- **تسجيل Google** في الواجهة غير مفعّل في الباك (يحتاج OAuth وإعداد منفصل) — مستخدم للعرض فقط.
- **استعادة كلمة المرور**: الواجهة تعرض رسالة توضيحية فقط؛ الإرسال الفعلي للبريد يحتاج إعداد خادم بريد (SMTP).
