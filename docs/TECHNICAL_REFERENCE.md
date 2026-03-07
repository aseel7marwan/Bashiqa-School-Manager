# 📚 المرجع التقني - Technical Reference
## نظام إدارة المدرسة v3.0.0

---

## 🔒 الأمان

### الحمايات المُطبَّقة

| الحماية | الوصف | الحالة |
|---------|-------|--------|
| SQL Injection | Prepared Statements (PDO) | ✅ |
| XSS | `sanitize()` + `htmlspecialchars()` | ✅ |
| CSRF | Token Validation | ✅ |
| Password | bcrypt hashing | ✅ |
| Rate Limiting | 5 محاولات / 15 دقيقة | ✅ |
| Session | Fingerprint + Timeout | ✅ |
| HTTP Headers | Security Headers | ✅ |

### إعدادات الأمان (`config/constants.php`)

```php
define('DEBUG_MODE', false);           // ⚠️ false في الإنتاج
define('PASSWORD_MIN_LENGTH', 8);
define('REQUIRE_STRONG_PASSWORD', true);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);     // 15 دقيقة
```

### قبل النشر

- [ ] تغيير `DEBUG_MODE` إلى `false`
- [ ] تغيير كلمة مرور المدير الافتراضية
- [ ] تفعيل HTTPS
- [ ] التأكد من حماية مجلد `backups/`

---

## 📡 واجهة API

### المعالجات (Controllers)

| الملف | الوصف |
|-------|-------|
| `attendance_handler.php` | تسجيل الحضور |
| `grade_handler.php` | رصد الدرجات |
| `student_handler.php` | إدارة الطلاب |
| `teacher_handler.php` | إدارة المعلمين |
| `user_handler.php` | إدارة المستخدمين |
| `language_handler.php` | تبديل اللغة |

### المصادقة

جميع الطلبات تتطلب:
1. جلسة نشطة (مستخدم مسجل الدخول)
2. CSRF Token لطلبات POST

```javascript
// مثال
fetch('/controllers/handler.php', {
    method: 'POST',
    body: `action=add&csrf_token=${csrfToken}&data=value`
});
```

### صيغة الاستجابة

```json
// نجاح
{ "success": true, "message": "تمت العملية بنجاح", "data": {} }

// فشل
{ "success": false, "error": "رسالة الخطأ" }
```

---

## 👥 الصلاحيات

| الدور | الوصول |
|-------|--------|
| **admin** | كامل (إدارة + رصد درجات + إدارة جميع المستخدمين) |
| **assistant** | مثل المدير تماماً **عدا**: لا يستطيع تعديل/حذف حساب المدير |
| **teacher** | تسجيل الحضور + مشاهدة بياناته وصفوفه فقط |
| **student** | عرض بياناته ودرجاته فقط |

### قيود خاصة

| القيد | الوصف |
|-------|-------|
| رصد الدرجات | **المدير والمعاون فقط** - المعلم لا يستطيع رصد درجات |
| تعديل حساب المدير | **المدير فقط** - المعاون لا يستطيع التعديل على حسابات المدراء |
| حذف حساب المدير | **المدير فقط** - المعاون لا يستطيع حذف حسابات المدراء |

> **ملاحظة:** دالة `canManageUser($userId)` تتحقق من صلاحية التعديل/الحذف على مستخدم معين.

---

## 📊 قاعدة البيانات

### الجداول الرئيسية

| الجدول | الوصف |
|--------|-------|
| `users` | حسابات المستخدمين |
| `students` | بيانات الطلاب |
| `teachers` | بيانات المعلمين |
| `attendance` | حضور الطلاب |
| `grades` | الدرجات |
| `leaves` | الإجازات |
| `teacher_assignments` | توزيع المواد |
| `activity_logs` | سجل العمليات |

### العلاقات

```
students.id → attendance.student_id
students.id → grades.student_id
teachers.id → users.teacher_id
users.id → teacher_assignments.teacher_id
```

### سلوك الحذف

- **ON DELETE CASCADE**: حذف تلقائي للبيانات المرتبطة
- **ON DELETE SET NULL**: إبقاء السجلات التاريخية

---

## 🌐 Clean URLs

تم تفعيلها عبر `.htaccess`:

```apache
RewriteCond %{REQUEST_FILENAME}.php -f
RewriteRule ^([^/]+)/?$ $1.php [L]
```

**مثال:**
- ❌ `/School-Manager/students.php`
- ✅ `/School-Manager/students`

---

## ⚡ تحسينات الأداء

### 1. Gzip Compression
تم تفعيل ضغط Gzip في `.htaccess` للملفات التالية:
- HTML, CSS, JavaScript, JSON
- XML, SVG, Fonts (WOFF, WOFF2, TTF)
- PHP Output

### 2. PJAX Navigation
تنقل سريع للقائمة الجانبية بدون إعادة تحميل الصفحة:
- **الملف:** `assets/js/pjax.js`
- **الهدف:** روابط القائمة الجانبية `#sidebarNav a.nav-item`
- **المميزات:**
  - كاش ذكي (5 دقائق)
  - شريط تقدم علوي
  - إعادة تهيئة المكونات تلقائياً (Flatpickr, Forms, etc.)
  - دعم زر الرجوع/التقدم

### 3. Lazy Loading
تحميل كسول للصور لتحسين سرعة التحميل:
- **CSS:** `assets/css/lazy.css`
- **JS:** كائن `LazyLoader` في `assets/js/main.js`
- **المميزات:**
  - IntersectionObserver للأداء الأفضل
  - دعم الصور والخلفيات و iframes
  - تأثيرات shimmer للتحميل
  - دعم Dark Mode

---

## 📝 ملاحظات للمطورين

1. **HTTPS**: فعّله في الإنتاج
2. **DEBUG_MODE**: أوقفه في الإنتاج
3. **Backups**: مجدول تلقائياً عبر Cron
4. **Activity Log**: يحتفظ بالسجلات 7 أيام
5. **PJAX**: يعمل فقط على القائمة الجانبية - لا يتعارض مع AJAX

---

**الإصدار:** 3.1.0
**آخر تحديث:** 11 يناير 2026

© 2026 - نظام إدارة المدرسة
