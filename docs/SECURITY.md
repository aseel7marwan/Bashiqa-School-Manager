# 🔐 دليل الأمان المتقدم - Advanced Security Guide

## ✅ ممارسات الأمان المُطبقة في النظام

### 1. 🔒 حماية الجلسات المتقدمة (Advanced Session Security)

#### إعدادات الجلسة الآمنة:
- ✅ **HTTPOnly Cookies** - يمنع JavaScript من الوصول للكوكيز
- ✅ **SameSite=Strict** - يمنع هجمات CSRF عبر المواقع
- ✅ **Session Timeout** - انتهاء الجلسة بعد ساعة من عدم النشاط
- ✅ **Session Regeneration** - إعادة توليد ID كل 15 دقيقة
- ✅ **Session Fingerprinting** - بصمة المتصفح للتحقق من هوية الجلسة
- ✅ **Cookie Lifetime = 0** - تنتهي الجلسة عند إغلاق المتصفح
- ✅ **No trans_sid** - لا يتم تمرير session ID في URL

#### حماية ضد سرقة الجلسات:
```php
// التحقق من بصمة المتصفح
function generateSessionFingerprint() {
    $fingerprint = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $fingerprint .= $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $fingerprint .= $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    return hash('sha256', $fingerprint);
}
```

### 2. 🛡️ حماية قاعدة البيانات (SQL Injection Prevention)
```php
// ✅ صحيح - استخدام Prepared Statements
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);

// ❌ خطأ - استعلام مباشر
$conn->query("SELECT * FROM users WHERE id = " . $userId);
```

### 3. 🌐 حماية XSS (Cross-Site Scripting)
```php
// ✅ تنظيف المخرجات
<?= htmlspecialchars($data, ENT_QUOTES, 'UTF-8') ?>
<?= sanitize($data) ?>
```

### 4. 🔄 حماية CSRF (Cross-Site Request Forgery)
```php
// في النماذج
<form method="POST">
    <?= csrfField() ?>
</form>

// في المعالجات
if (!validateCSRFToken($_POST['csrf_token'])) {
    alert('خطأ في التحقق الأمني', 'error');
    redirect('/...');
}
```

### 5. 📋 التحقق من الصلاحيات
```php
requireLogin();            // إجبار تسجيل الدخول
isAdmin()                  // التحقق من المدير/المعاون
isTeacher()                // التحقق من المعلم
isStudent()                // التحقق من الطالب
isAccountActive()          // التحقق من نشاط الحساب دورياً
```

### 6. 🚫 منع التخزين المؤقت (Cache Prevention)
```php
header('Cache-Control: private, no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
```

### 7. 🔐 تسجيل الخروج الآمن
```php
function logout() {
    // 1. مسح كل متغيرات الجلسة
    $_SESSION = [];
    
    // 2. حذف كوكي الجلسة
    setcookie(session_name(), '', time() - 42000, ...);
    
    // 3. تدمير الجلسة
    session_destroy();
}
```

### 8. ✅ التحقق الدوري من حالة الحساب
- يتم التحقق من حالة الحساب في قاعدة البيانات كل 5 دقائق
- إذا تم تعطيل الحساب، يتم تسجيل خروج المستخدم تلقائياً

---

## 🔧 صلاحيات المستخدمين

| الدور | الصلاحيات |
|-------|----------|
| **مدير (admin)** | جميع الصلاحيات + إدارة المستخدمين |
| **معاون (assistant)** | معظم الصلاحيات (عدا إدارة المستخدمين) |
| **معلم (teacher)** | تسجيل الحضور ورصد الدرجات (لصفوفه ومواده فقط) |
| **طالب (student)** | عرض بياناته ودرجاته فقط |

---

## 📄 الصفحات المحمية

### صفحات عامة (لا تتطلب تسجيل دخول):
- `login.php` - صفحة تسجيل الدخول
- `install.php` - صفحة التثبيت (تعمل مرة واحدة فقط)

### صفحات محمية (تتطلب تسجيل دخول):
| الصفحة | الصلاحية المطلوبة |
|--------|------------------|
| `dashboard.php` | أي مستخدم مسجل |
| `students.php` | مدير أو معاون |
| `teachers.php` | مدير أو معاون |
| `attendance.php` | معلم أو مدير |
| `grades.php` | معلم أو مدير |
| `users.php` | مدير فقط |
| `student_users.php` | مدير أو معاون |
| `backup.php` | مدير أو معاون |
| `student_profile.php` | الطالب نفسه |
| `teacher_profile.php` | المعلم نفسه |

---

## 🛠️ ميزات الأمان المتقدمة

### 1. Session Fingerprinting
- يتم إنشاء بصمة فريدة للمتصفح عند تسجيل الدخول
- أي تغيير في بصمة المتصفح يؤدي لتسجيل الخروج التلقائي
- يحمي ضد سرقة كوكيز الجلسة

### 2. Session ID Regeneration
- يتم إعادة توليد معرف الجلسة كل 15 دقيقة
- يتم إعادة التوليد أيضاً عند تسجيل الدخول
- يحمي ضد هجمات Session Fixation

### 3. Real-time Account Validation
- التحقق الدوري من حالة الحساب في قاعدة البيانات
- إذا تم تعطيل/حذف الحساب، يتم قطع الجلسة فوراً
- يمكن للمدير تعطيل أي حساب وسيتم تسجيل خروجه تلقائياً

### 4. Forced Session Destruction
- عند تسجيل الخروج يتم تدمير الجلسة بالكامل
- لا يمكن العودة للصفحات المحمية باستخدام زر الرجوع
- يتم مسح كل الكوكيز المتعلقة بالجلسة

---

## ⚠️ توصيات مهمة

1. **غيّر كلمة المرور الافتراضية فوراً** بعد أول تسجيل دخول
2. **احذف ملفات التثبيت** (`install.php`) بعد التثبيت الناجح
3. **استخدم HTTPS** في بيئة الإنتاج لتشفير الاتصالات
4. **قم بعمل نسخ احتياطية** منتظمة لقاعدة البيانات
5. **راقب سجلات الوصول** للكشف عن أي نشاط مشبوه
6. **حدّث كلمات المرور** بشكل دوري

---

## 🔍 كيفية اختبار الأمان

### اختبار تسجيل الخروج:
1. سجل دخول للنظام
2. انتقل لأي صفحة محمية
3. سجل خروج
4. اضغط زر الرجوع في المتصفح
5. يجب أن تظهر صفحة تسجيل الدخول

### اختبار انتهاء الجلسة:
1. سجل دخول للنظام
2. انتظر ساعة بدون نشاط
3. حاول الوصول لأي صفحة
4. يجب أن تظهر رسالة انتهاء الجلسة

### اختبار تعطيل الحساب:
1. سجل دخول بحساب معين
2. من حساب المدير، قم بتعطيل ذلك الحساب
3. في الحساب الأول، انتظر 5 دقائق أو قم بأي إجراء
4. يجب أن يتم تسجيل خروج الحساب المعطل تلقائياً
