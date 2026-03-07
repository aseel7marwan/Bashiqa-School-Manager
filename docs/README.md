<div align="center">

# 🏫 نظام إدارة المدرسة
# School Manager System

[![Version](https://img.shields.io/badge/Version-3.0.0-blue.svg)](https://github.com)
[![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-orange.svg)](https://mysql.com)
[![License](https://img.shields.io/badge/License-Proprietary-red.svg)](LICENSE)
[![Status](https://img.shields.io/badge/Status-Production-green.svg)](https://github.com)

**نظام متكامل لإدارة المدرسة والطلاب والحضور والإجازات**

*Comprehensive School Management System for Students, Attendance & Leaves*

🇮🇶 مصمم خصيصاً للمدارس العراقية | Designed for Iraqi Schools

---

[🚀 البدء السريع](#-التثبيت) •
[📖 التوثيق](#-هيكل-المشروع) •
[🔒 الأمان](#-أمان-متقدم) •
[🌐 اللغات](#-دعم-اللغات)

</div>

---

## ✨ المميزات الرئيسية

<table>
<tr>
<td width="50%">

### 👨‍🎓 إدارة الطلاب
- ✅ بطاقات مدرسية شاملة مع صور
- ✅ سجلات أكاديمية متكاملة
- ✅ حسابات دخول للطلاب
- ✅ ربط حسابات الطلاب بأولياء الأمور
- ✅ تصدير البيانات (PDF/Excel)

</td>
<td width="50%">

### ✅ تسجيل الحضور
- ✅ تسجيل سريع وسهل
- ✅ إحصائيات دقيقة ومفصلة
- ✅ تقارير يومية وأسبوعية وشهرية
- ✅ أداء محسّن للتحميل السريع

</td>
</tr>
<tr>
<td width="50%">

### 👔 إدارة الكادر التعليمي
- ✅ ملفات المعلمين الكاملة
- ✅ تسجيل الدوام والغيابات
- ✅ إدارة الإجازات
- ✅ توزيع الحصص والمواد

</td>
<td width="50%">

### 📝 رصد الدرجات
- ✅ نظام رصد حسب المنهج العراقي
- ✅ **نظام الدرجات الشهرية** للصفوف 5 و 6
- ✅ كشوفات النتائج التفصيلية
- ✅ حساب المعدلات تلقائياً
- ✅ تقارير الأداء الأكاديمي
- ✅ صلاحية رصد للمعلم حسب مواده

</td>
</tr>
<tr>
<td width="50%">

### 🪑 جرد أثاث المدرسة
- ✅ جرد أثاث الصفوف والمرافق
- ✅ تقارير مفصلة بالحالة
- ✅ تتبع الإضافات والتلفيات

</td>
<td width="50%">

### 📊 التقارير والإحصائيات
- ✅ تقارير PDF احترافية
- ✅ تصدير Excel
- ✅ إحصائيات شاملة ومفصلة
- ✅ **🔍 البحث الشامل** (بحث فوري في الطلاب والمعلمين)

</td>
</tr>
<tr>
<td width="50%">

### � جدول الحصص
- ✅ جداول الحصص التفاعلية
- ✅ توزيع المعلمين على المواد
- ✅ إدارة الفصول الدراسية

</td>
<td width="50%">

### 💾 النسخ الاحتياطي
- ✅ نسخ احتياطي تلقائي
- ✅ استعادة البيانات
- ✅ تحميل نسخ يدوية

</td>
</tr>
</table>

---

## 🔒 أمان متقدم

| الميزة | الوصف | الحالة |
|--------|-------|--------|
| **SQL Injection** | Prepared Statements لجميع الاستعلامات | ✅ مفعّل |
| **XSS Protection** | Output Escaping + sanitize() | ✅ مفعّل |
| **CSRF Protection** | Token Validation لجميع النماذج | ✅ مفعّل |
| **Password Hashing** | bcrypt | ✅ مفعّل |
| **Rate Limiting** | حماية من Brute Force (5 محاولات/15 دقيقة) | ✅ مفعّل |
| **Session Security** | Fingerprinting + Regeneration | ✅ مفعّل |
| **HTTP Headers** | X-Frame-Options, X-XSS-Protection, etc. | ✅ مفعّل |
| **Audit Logs** | سجل كامل لجميع العمليات | ✅ مفعّل |

---

## 🌐 دعم اللغات

| اللغة | الحالة | الاتجاه |
|:-----:|:------:|:-------:|
| 🇮🇶 العربية | ✅ الافتراضية | RTL ← |
| 🇬🇧 English | ✅ مدعومة | LTR → |

### 🔄 نظام الترجمة:
- ملف JSON موحد للترجمات (`translations.json`)
- تبديل فوري بدون إعادة تحميل
- زر تبديل اللغة 🌐 في جميع الصفحات

---

## 📱 التوافق مع الأجهزة

| الجهاز | الحالة |
|:------:|:------:|
| 🖥️ Desktop | ✅ ممتاز |
| 💻 Laptop | ✅ ممتاز |
| 📱 Mobile | ✅ ممتاز |
| 📱 Tablet | ✅ ممتاز |

### ✨ مميزات الموبايل:
- 📱 تصميم متجاوب 100%
- 🍔 قائمة همبرغر أنيقة
- 👆 أزرار كبيرة سهلة اللمس

---

## 🔗 روابط نظيفة (Clean URLs)

```
❌ القديم: /School-Manager/students.php
✅ الجديد: /School-Manager/students
```

---

## 👥 أنواع المستخدمين والصلاحيات

| الدور | الصلاحيات | الوصول |
|:-----:|-----------|:------:|
| 👑 **المدير** | إدارة كاملة + رصد جميع الدرجات | 🔓 كامل |
| 👔 **المعاون** | معظم الصلاحيات + رصد جميع الدرجات | 🔓 عالي |
| 👨‍🏫 **المعلم** | رصد درجات مواده المعيّنة فقط | 🔒 محدود |
| 👨‍🎓 **الطالب** | عرض بياناته ودرجاته فقط | 🔒 محدود جداً |

> **ملاحظة:** المعلم يمكنه رصد درجات المواد المعيّنة له فقط عبر "توزيع المواد".

---

## 🔐 حساب تسجيل الدخول الافتراضي

| الدور | اسم المستخدم | كلمة المرور |
|:-----:|:------------:|:-----------:|
| 👑 مدير | `admin` | `password` |

> ⚠️ **تحذير أمني:** غيّر كلمة المرور الافتراضية فوراً بعد أول تسجيل دخول!

---

## 📂 هيكل المشروع

```
School-Manager/
│
├── 📁 api/                     # API (1 ملف)
│   └── api.php
│
├── 📁 assets/                  # الموارد الثابتة
│   ├── 📁 css/                 # ملفات التنسيق (5 ملفات)
│   │   ├── layout.css
│   │   ├── main.css
│   │   ├── ajax.css
│   │   ├── attendance.css
│   │   └── print.css
│   ├── 📁 js/                  # ملفات JavaScript (3 ملفات)
│   │   ├── main.js
│   │   ├── ajax.js
│   │   └── attendance.js
│   └── 📁 images/
│
├── 📁 backups/                 # النسخ الاحتياطية
│
├── 📁 config/                  # الإعدادات (3 ملفات)
│   ├── constants.php           # الثوابت والإعدادات
│   ├── database.php            # اتصال قاعدة البيانات
│   └── permissions.php         # الصلاحيات
│
├── 📁 controllers/             # المعالجات (17 ملف)
│   ├── attendance_handler.php
│   ├── grade_handler.php
│   ├── language_handler.php
│   ├── login_handler.php
│   ├── student_handler.php
│   ├── teacher_handler.php
│   ├── user_handler.php
│   └── ...
│
├── 📁 cron/                    # المهام المجدولة (2 ملف)
│   ├── auto_backup.php
│   └── run_backup.bat
│
├── 📁 database/                # قاعدة البيانات
│   └── unified_schema.sql      # ملف واحد شامل (16 جدول + فهارس + حساب المدير)
│
├── 📁 docs/                    # التوثيق (4 ملفات)
│   ├── README.md
│   ├── INSTALLATION.md
│   ├── TECHNICAL_REFERENCE.md
│   └── demo_data.sql
│
├── 📁 includes/                # الملفات المشتركة (6 ملفات)
│   ├── auth.php                # نظام المصادقة
│   ├── functions.php           # الدوال العامة
│   ├── translations.php        # نظام الترجمات
│   ├── translations.json       # ملف الترجمات
│   ├── cache.php               # نظام التخزين المؤقت
│   └── export_helper.php       # مساعد التصدير
│
├── 📁 models/                  # نماذج البيانات (13 ملف)
│   ├── Student.php
│   ├── Teacher.php
│   ├── Attendance.php
│   ├── Grade.php
│   ├── User.php
│   └── ...
│
├── 📁 uploads/                 # الملفات المرفوعة
│   └── 📁 students/
│
├── 📁 views/                   # القوالب
│   └── 📁 components/          # (2 ملف)
│       ├── header.php
│       └── footer.php
│
├── 📄 .htaccess                # إعدادات Apache + Clean URLs
├── 📄 index.php                # نقطة الدخول
├── 📄 welcome.php              # صفحة الترحيب
├── 📄 login.php                # تسجيل الدخول
├── 📄 dashboard.php            # لوحة التحكم
├── 📄 students.php             # إدارة الطلاب
├── 📄 teachers.php             # إدارة المعلمين
├── 📄 attendance.php           # تسجيل الحضور
├── 📄 grades.php               # رصد الدرجات
├── 📄 schedule.php             # جدول الحصص
├── 📄 classroom_equipment.php  # جرد الأثاث
├── 📄 error.php                # صفحة الأخطاء
└── 📄 +30 صفحة PHP أخرى
```

---

## ⚙️ التثبيت

### 📋 المتطلبات:

| المتطلب | الإصدار المطلوب |
|---------|-----------------|
| PHP | 7.4+ (PDO, mysqli, mbstring) |
| MySQL | 5.7+ أو MariaDB 10.3+ |
| Apache | 2.4+ (mod_rewrite مفعّل) |

### 🚀 خطوات التثبيت:

1️⃣ **نسخ المشروع**
```bash
C:\xampp\htdocs\School-Manager
```

2️⃣ **تشغيل XAMPP**
```
✅ شغل Apache
✅ شغل MySQL
```

3️⃣ **إعداد قاعدة البيانات**
```
http://localhost/School-Manager/install
```

4️⃣ **تسجيل الدخول**
```
http://localhost/School-Manager/login
```

> 📖 لمزيد من التفاصيل، راجع `docs/INSTALLATION.md`

---

## 🏷️ سجل التحديثات

### 📦 الإصدار 3.0.0 (يناير 2026)

<details>
<summary><b>✨ الميزات الجديدة</b></summary>

- 🔗 روابط نظيفة (Clean URLs)
- 📱 شريط تنقل متجاوب للموبايل
- 🍔 قائمة همبرغر
- 📄 مخطط قاعدة بيانات موحد (ملف واحد شامل)
- 📊 **نظام الدرجات الشهرية** للصفوف 5 و 6
- 🔍 **البحث الشامل** (Global Search) للطلاب والمعلمين
- 👨‍🏫 **صلاحية رصد للمعلم** حسب مواده المعيّنة
- 🔄 **نظام توزيع المواد** على المعلمين

</details>

<details>
<summary><b>🔧 التحسينات</b></summary>

- ⚡ تحسين الأداء وسرعة التحميل
- 🌐 تحسين نظام الترجمة (عربي/إنجليزي)
- 🛡️ تحسين HTTP Security Headers
- 🔐 إضافة header UTF-8 للترميز العربي
- 📁 دمج ملفات قاعدة البيانات في ملف واحد

</details>

---

## �️ استكشاف الأخطاء

<details>
<summary><b>❓ صفحة بيضاء أو Error 500</b></summary>

1. تأكد من تفعيل mod_rewrite
2. راجع سجل الأخطاء: `C:\xampp\apache\logs\error.log`

</details>

<details>
<summary><b>❓ خطأ في الاتصال بقاعدة البيانات</b></summary>

1. تأكد من تشغيل MySQL
2. راجع `config/database.php`

</details>

<details>
<summary><b>❓ Clean URLs لا تعمل</b></summary>

1. تأكد من تفعيل mod_rewrite
2. تأكد من AllowOverride All

</details>

---

## 📞 الدعم

<div align="center">

**تم تطوير هذا النظام لـ**

### 🏫 مدرسة بعشيقة الابتدائية للبنين

</div>

---

## 📜 الترخيص

<div align="center">

```
جميع الحقوق محفوظة © 2024-2026

هذا النظام مرخص للاستخدام الداخلي فقط
يُمنع إعادة التوزيع أو البيع بدون إذن مسبق
```

</div>

---

<div align="center">

**صُنع بـ ❤️ للتعليم العراقي**

[![Made with Love](https://img.shields.io/badge/Made%20with-❤️-red.svg)](https://github.com)
[![Iraq](https://img.shields.io/badge/🇮🇶-Iraq-green.svg)](https://iraq.com)

</div>
