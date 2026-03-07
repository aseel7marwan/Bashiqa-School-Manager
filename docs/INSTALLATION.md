# 🚀 دليل التثبيت - Installation Guide
## نظام إدارة المدرسة - School Manager v3.0.0

---

## 📋 المتطلبات

### البرمجيات المطلوبة:

| المتطلب | الإصدار | ملاحظات |
|---------|---------|---------|
| PHP | 7.4 أو أعلى | PDO, mysqli, mbstring |
| MySQL | 5.7 أو أعلى | أو MariaDB 10.3+ |
| Apache | 2.4 أو أعلى | mod_rewrite مفعّل |
| XAMPP | أي إصدار | موصى به للتطوير |

### Extensions المطلوبة في PHP:
- `pdo_mysql`
- `mysqli`
- `mbstring`
- `json`
- `session`

---

## 🖥️ التثبيت المحلي (XAMPP)

### الخطوة 1: تحميل XAMPP

1. حمّل XAMPP من: https://www.apachefriends.org
2. ثبّت البرنامج (الإعدادات الافتراضية كافية)
3. تأكد من تثبيت Apache و MySQL

### الخطوة 2: نسخ المشروع

```
انسخ مجلد المشروع إلى:
C:\xampp\htdocs\School-Manager
```

### الخطوة 3: تشغيل الخدمات

1. افتح **XAMPP Control Panel**
2. اضغط **Start** بجانب **Apache**
3. اضغط **Start** بجانب **MySQL**

### الخطوة 4: إعداد قاعدة البيانات

**الخيار 1: التثبيت التلقائي (موصى به)**
```
افتح في المتصفح:
http://localhost/School-Manager/install.php
```

**الخيار 2: التثبيت اليدوي**
1. افتح phpMyAdmin: `http://localhost/phpmyadmin`
2. أنشئ قاعدة بيانات: `school_manager`
3. استورد: `database/unified_schema.sql`

### الخطوة 5: إعداد الاتصال

أنشئ ملف `config/database.php`:

```php
<?php
function getConnection() {
    static $conn = null;
    if ($conn === null) {
        try {
            $conn = new PDO(
                "mysql:host=localhost;dbname=school_manager;charset=utf8mb4",
                "root",        // اسم المستخدم
                "",            // كلمة المرور (فارغة في XAMPP)
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch (PDOException $e) {
            die("خطأ في الاتصال: " . $e->getMessage());
        }
    }
    return $conn;
}
```

### الخطوة 6: تسجيل الدخول

```
افتح في المتصفح:
http://localhost/School-Manager/login

الحساب الافتراضي:
اسم المستخدم: admin
كلمة المرور: admin123
```

---

## 🌐 التثبيت على استضافة خارجية

### الخطوة 1: رفع الملفات

1. ارفع جميع الملفات عبر FTP أو cPanel File Manager
2. تأكد من رفع ملف `.htaccess`
3. تأكد من صلاحيات الملفات:
   - الملفات: `644`
   - المجلدات: `755`
   - مجلد uploads: `775`

### الخطوة 2: إنشاء قاعدة البيانات

1. افتح cPanel → MySQL Databases
2. أنشئ قاعدة بيانات جديدة
3. أنشئ مستخدم جديد
4. اربط المستخدم بقاعدة البيانات (All Privileges)

### الخطوة 3: استيراد البيانات

1. افتح phpMyAdmin
2. اختر قاعدة البيانات
3. استورد `database/unified_schema.sql`

### الخطوة 4: تعديل إعدادات الاتصال

عدّل `config/database.php`:

```php
<?php
function getConnection() {
    static $conn = null;
    if ($conn === null) {
        try {
            $conn = new PDO(
                "mysql:host=localhost;dbname=اسم_قاعدة_البيانات;charset=utf8mb4",
                "اسم_المستخدم",
                "كلمة_المرور",
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch (PDOException $e) {
            die("خطأ في الاتصال");
        }
    }
    return $conn;
}
```

### الخطوة 5: تفعيل HTTPS

في `.htaccess`، أزل التعليق:

```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## ⚙️ إعدادات ما بعد التثبيت

### 1. تغيير كلمة المرور الافتراضية

⚠️ **مهم جداً!** غيّر كلمة المرور الافتراضية فوراً:

1. سجّل الدخول بـ admin/admin123
2. اذهب إلى إدارة المستخدمين
3. عدّل كلمة مرور المدير

### 2. تعطيل وضع التطوير

في `config/constants.php`:

```php
define('DEBUG_MODE', false);  // ⚠️ مهم!
```

### 3. حذف install.php

```bash
rm install.php
```

أو احذفه من مدير الملفات.

---

## 🔧 استكشاف الأخطاء

### ❌ صفحة بيضاء أو Error 500

**الأسباب المحتملة:**
1. mod_rewrite غير مفعّل
2. خطأ في `.htaccess`
3. خطأ في إعدادات PHP

**الحل:**
1. تأكد من تفعيل mod_rewrite في Apache
2. راجع سجل الأخطاء: `C:\xampp\apache\logs\error.log`
3. تأكد من صحة `.htaccess`

### ❌ خطأ في الاتصال بقاعدة البيانات

**الأسباب المحتملة:**
1. MySQL غير شغّال
2. بيانات الاتصال خاطئة
3. قاعدة البيانات غير موجودة

**الحل:**
1. شغّل MySQL من XAMPP
2. راجع `config/database.php`
3. تأكد من إنشاء قاعدة البيانات

### ❌ Clean URLs لا تعمل

**الأسباب المحتملة:**
1. mod_rewrite غير مفعّل
2. AllowOverride غير مضبوط

**الحل:**
1. في `httpd.conf`، فعّل:
   ```
   LoadModule rewrite_module modules/mod_rewrite.so
   ```
2. تأكد من:
   ```
   AllowOverride All
   ```
3. أعد تشغيل Apache

### ❌ مشكلة في الترميز (حروف غريبة)

**الحل:**
1. تأكد من `charset=utf8mb4` في الاتصال
2. تأكد من charset في HTML: `<meta charset="UTF-8">`
3. تأكد من حفظ الملفات بترميز UTF-8

---

## 📁 هيكل قاعدة البيانات

### الجداول الرئيسية:

| الجدول | الوصف |
|--------|-------|
| `users` | المستخدمين |
| `students` | التلاميذ |
| `teachers` | المعلمين |
| `attendance` | حضور التلاميذ |
| `teacher_attendance` | حضور المعلمين |
| `grades` | الدرجات |
| `leaves` | الإجازات |
| `subjects` | المواد الدراسية |
| `teacher_assignments` | توزيع المواد |
| `classroom_equipment` | أثاث الصفوف |
| `school_events` | الفعاليات |
| `activity_logs` | سجل العمليات |
| `schedules` | جدول الحصص |

---

## 📞 المساعدة

إذا واجهت مشاكل:

1. راجع مجلد `docs/` للتوثيق
2. راجع ملف `TECHNICAL_REFERENCE.md` للمعلومات التقنية
3. تحقق من سجل الأخطاء في Apache

---

**الإصدار:** 3.0.0
**آخر تحديث:** يناير 2026

© 2026 - نظام إدارة المدرسة
