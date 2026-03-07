-- ═══════════════════════════════════════════════════════════════════════════════
-- 📋 بيانات تجريبية كاملة لنظام إدارة المدرسة
-- School Manager Complete Demo Data
-- ═══════════════════════════════════════════════════════════════════════════════
-- 
-- 📌 استخدام الملف:
-- 1. افتح phpMyAdmin
-- 2. اختر قاعدة البيانات
-- 3. انتقل إلى تبويب SQL
-- 4. الصق محتوى هذا الملف
-- 5. اضغط "Go" لتنفيذه
--
-- ⚠️ تحذير: هذا الملف سيحذف البيانات القديمة!
-- ═══════════════════════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ═══════════════════════════════════════════════════════════════════════════════
-- 🗑️ تنظيف البيانات القديمة
-- ═══════════════════════════════════════════════════════════════════════════════

DELETE FROM `monthly_grades` WHERE 1=1;
DELETE FROM `grades` WHERE 1=1;
DELETE FROM `attendance` WHERE 1=1;
DELETE FROM `teacher_attendance` WHERE 1=1;
DELETE FROM `teacher_absences` WHERE 1=1;
DELETE FROM `leaves` WHERE 1=1;
DELETE FROM `schedules` WHERE 1=1;
DELETE FROM `teacher_assignments` WHERE 1=1;
DELETE FROM `users` WHERE role != 'admin';
DELETE FROM `students` WHERE 1=1;
DELETE FROM `teachers` WHERE 1=1;

ALTER TABLE `teachers` AUTO_INCREMENT = 1;
ALTER TABLE `students` AUTO_INCREMENT = 1;

-- ═══════════════════════════════════════════════════════════════════════════════
-- 1. 👨‍🏫 المعلمين (6 معلمين)
-- ═══════════════════════════════════════════════════════════════════════════════

INSERT INTO `teachers` (`id`, `full_name`, `phone`, `specialization`, `status`) VALUES
(1, 'خالد ناظم الجبوري', '07701234501', 'اللغة العربية', 'active'),
(2, 'سامي عبدالواحد البياتي', '07701234502', 'الرياضيات', 'active'),
(3, 'فاروق حميد الربيعي', '07701234503', 'العلوم', 'active'),
(4, 'عبدالستار حسن الدليمي', '07701234504', 'التربية الإسلامية', 'active'),
(5, 'مازن رشيد السعدي', '07701234505', 'اللغة الإنجليزية', 'active'),
(6, 'نبيل كريم الشمري', '07701234506', 'الاجتماعيات', 'active')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

-- ═══════════════════════════════════════════════════════════════════════════════
-- 2. 👤 حسابات المعاون والمعلمين
-- ═══════════════════════════════════════════════════════════════════════════════

INSERT INTO `users` (`username`, `password_hash`, `full_name`, `role`, `status`, `plain_password`) VALUES
('assistant1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'محمد صالح الربيعي', 'assistant', 'active', 'password')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

INSERT INTO `users` (`username`, `password_hash`, `full_name`, `role`, `status`, `teacher_id`, `plain_password`) VALUES
('khaled_t', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'خالد ناظم الجبوري', 'teacher', 'active', 1, 'password'),
('sami_t', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'سامي عبدالواحد البياتي', 'teacher', 'active', 2, 'password'),
('farouq_t', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'فاروق حميد الربيعي', 'teacher', 'active', 3, 'password'),
('abdulsattar_t', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'عبدالستار حسن الدليمي', 'teacher', 'active', 4, 'password'),
('mazen_t', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مازن رشيد السعدي', 'teacher', 'active', 5, 'password'),
('nabil_t', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'نبيل كريم الشمري', 'teacher', 'active', 6, 'password')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

UPDATE `teachers` t JOIN `users` u ON u.teacher_id = t.id SET t.user_id = u.id;

-- ═══════════════════════════════════════════════════════════════════════════════
-- 3. 👨‍🎓 الطلاب (48 طالب - 8 لكل صف)
-- ═══════════════════════════════════════════════════════════════════════════════

INSERT INTO `students` (`id`, `full_name`, `class_id`, `section`, `birth_date`, `gender`, `parent_name`, `parent_phone`) VALUES
-- الصف الأول
(1, 'أحمد محمد علي', 1, 'أ', '2017-03-15', 'male', 'محمد علي حسين', '07701234567'),
(2, 'يوسف عمر حسين', 1, 'أ', '2017-05-20', 'male', 'عمر حسين كاظم', '07701234568'),
(3, 'علي كريم صالح', 1, 'ب', '2017-01-10', 'male', 'كريم صالح محمود', '07701234569'),
(4, 'حسن عبدالله جاسم', 1, 'ب', '2017-07-25', 'male', 'عبدالله جاسم نور', '07701234570'),
(5, 'محمود سعد رشيد', 1, 'ج', '2017-02-18', 'male', 'سعد رشيد عبدالكريم', '07701234571'),
(6, 'زيد خالد إبراهيم', 1, 'ج', '2017-09-05', 'male', 'خالد إبراهيم أحمد', '07701234572'),
(7, 'عمار حسين علي', 1, 'د', '2017-04-12', 'male', 'حسين علي محمد', '07701234573'),
(8, 'مصطفى أمير سالم', 1, 'د', '2017-11-28', 'male', 'أمير سالم فاضل', '07701234574'),
-- الصف الثاني
(9, 'عمر سالم ناصر', 2, 'أ', '2016-07-22', 'male', 'سالم ناصر كريم', '07712345678'),
(10, 'محمد أيوب خالد', 2, 'أ', '2016-03-14', 'male', 'أيوب خالد حسن', '07712345679'),
(11, 'زيد فاضل كاظم', 2, 'ب', '2016-08-05', 'male', 'فاضل كاظم رشيد', '07712345680'),
(12, 'مصطفى جابر عبدالكريم', 2, 'ب', '2016-12-17', 'male', 'جابر عبدالكريم صالح', '07712345681'),
(13, 'أنس رائد محمود', 2, 'ج', '2016-05-30', 'male', 'رائد محمود سعيد', '07712345682'),
(14, 'يحيى عادل فارس', 2, 'ج', '2016-10-08', 'male', 'عادل فارس نبيل', '07712345683'),
(15, 'إبراهيم ثامر داود', 2, 'د', '2016-02-19', 'male', 'ثامر داود عبدالرحمن', '07712345684'),
(16, 'حمزة وائل شاكر', 2, 'د', '2016-06-25', 'male', 'وائل شاكر عباس', '07712345685'),
-- الصف الثالث
(17, 'حسين علاء رعد', 3, 'أ', '2015-11-10', 'male', 'علاء رعد محمود', '07723456789'),
(18, 'عباس صباح نزار', 3, 'أ', '2015-04-22', 'male', 'صباح نزار حسين', '07723456790'),
(19, 'قاسم طارق سعد', 3, 'ب', '2015-09-15', 'male', 'طارق سعد كريم', '07723456791'),
(20, 'ياسر غالب فاروق', 3, 'ب', '2015-01-28', 'male', 'غالب فاروق علي', '07723456792'),
(21, 'عامر صادق رياض', 3, 'ج', '2015-07-03', 'male', 'صادق رياض محمد', '07723456793'),
(22, 'باسم نبيل عبدالله', 3, 'ج', '2015-12-11', 'male', 'نبيل عبدالله جاسم', '07723456794'),
(23, 'سامر حاتم رشيد', 3, 'د', '2015-03-19', 'male', 'حاتم رشيد سالم', '07723456795'),
(24, 'ليث جمال حسن', 3, 'د', '2015-08-27', 'male', 'جمال حسن فاضل', '07723456796'),
-- الصف الرابع
(25, 'كرار مهدي جواد', 4, 'أ', '2014-01-05', 'male', 'مهدي جواد حسن', '07734567890'),
(26, 'رضا نبيل هاشم', 4, 'أ', '2014-06-18', 'male', 'نبيل هاشم كاظم', '07734567891'),
(27, 'أنس وليد سامر', 4, 'ب', '2014-10-25', 'male', 'وليد سامر علي', '07734567892'),
(28, 'بلال خضير عادل', 4, 'ب', '2014-03-09', 'male', 'خضير عادل محمود', '07734567893'),
(29, 'فراس طالب كامل', 4, 'ج', '2014-08-14', 'male', 'طالب كامل سعيد', '07734567894'),
(30, 'نوفل عماد رائد', 4, 'ج', '2014-11-30', 'male', 'عماد رائد نعمان', '07734567895'),
(31, 'أيمن زهير حسام', 4, 'د', '2014-05-07', 'male', 'زهير حسام جلال', '07734567896'),
(32, 'مؤمل سعيد باسم', 4, 'د', '2014-09-21', 'male', 'سعيد باسم عمر', '07734567897'),
-- الصف الخامس
(33, 'نور الدين صادق رياض', 5, 'أ', '2013-05-20', 'male', 'صادق رياض عبدالله', '07745678901'),
(34, 'سيف أسامة توفيق', 5, 'أ', '2013-02-12', 'male', 'أسامة توفيق عبدالرزاق', '07745678902'),
(35, 'حيدر ماجد عبدالرحمن', 5, 'ب', '2013-07-28', 'male', 'ماجد عبدالرحمن حميد', '07745678903'),
(36, 'مروان ثامر داود', 5, 'ب', '2013-12-03', 'male', 'ثامر داود سامي', '07745678904'),
(37, 'ضرغام عبدالكريم طارق', 5, 'ج', '2013-04-16', 'male', 'عبدالكريم طارق فهد', '07745678905'),
(38, 'علاء محسن كامل', 5, 'ج', '2013-09-09', 'male', 'محسن كامل جابر', '07745678906'),
(39, 'حسام عبدالحميد صالح', 5, 'د', '2013-01-22', 'male', 'عبدالحميد صالح ناصر', '07745678907'),
(40, 'رامي فيصل عباس', 5, 'د', '2013-06-15', 'male', 'فيصل عباس راشد', '07745678908'),
-- الصف السادس
(41, 'عبدالرحمن كامل إبراهيم', 6, 'أ', '2012-09-08', 'male', 'كامل إبراهيم سعيد', '07756789012'),
(42, 'إياد منصور يونس', 6, 'أ', '2012-03-25', 'male', 'منصور يونس رفيق', '07756789013'),
(43, 'طه فيصل راشد', 6, 'ب', '2012-08-17', 'male', 'فيصل راشد كريم', '07756789014'),
(44, 'وسام باسم عبدالجبار', 6, 'ب', '2012-11-04', 'male', 'باسم عبدالجبار نوري', '07756789015'),
(45, 'صهيب غسان حيدر', 6, 'ج', '2012-02-28', 'male', 'غسان حيدر علي', '07756789016'),
(46, 'معاذ سالم عبدالله', 6, 'ج', '2012-07-10', 'male', 'سالم عبدالله محمود', '07756789017'),
(47, 'براء نزار حمدي', 6, 'د', '2012-05-19', 'male', 'نزار حمدي ياسين', '07756789018'),
(48, 'أسامة ريان عمار', 6, 'د', '2012-10-31', 'male', 'ريان عمار مهدي', '07756789019')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

-- ═══════════════════════════════════════════════════════════════════════════════
-- 3.5. 👤 حسابات الطلاب (ربط student_id و user_id)
-- ═══════════════════════════════════════════════════════════════════════════════
-- إنشاء حسابات للطلاب: اسم المستخدم = أول اسم بالإنجليزية + رقم عشوائي

INSERT INTO `users` (`username`, `password_hash`, `full_name`, `role`, `status`, `student_id`, `plain_password`)
SELECT 
    CONCAT(
        CASE s.id
            WHEN 1 THEN 'ahmad' WHEN 2 THEN 'yousef' WHEN 3 THEN 'ali' WHEN 4 THEN 'hassan'
            WHEN 5 THEN 'mahmoud' WHEN 6 THEN 'zaid' WHEN 7 THEN 'ammar' WHEN 8 THEN 'mustafa'
            WHEN 9 THEN 'omar' WHEN 10 THEN 'mohammed' WHEN 11 THEN 'zaid2' WHEN 12 THEN 'mustafa2'
            WHEN 13 THEN 'anas' WHEN 14 THEN 'yahya' WHEN 15 THEN 'ibrahim' WHEN 16 THEN 'hamza'
            WHEN 17 THEN 'hussein' WHEN 18 THEN 'abbas' WHEN 19 THEN 'qasim' WHEN 20 THEN 'yasser'
            WHEN 21 THEN 'amer' WHEN 22 THEN 'basim' WHEN 23 THEN 'samer' WHEN 24 THEN 'laith'
            WHEN 25 THEN 'karrar' WHEN 26 THEN 'ridha' WHEN 27 THEN 'anas3' WHEN 28 THEN 'bilal'
            WHEN 29 THEN 'firas' WHEN 30 THEN 'nawfal' WHEN 31 THEN 'ayman' WHEN 32 THEN 'muamal'
            WHEN 33 THEN 'nourdin' WHEN 34 THEN 'saif' WHEN 35 THEN 'haider' WHEN 36 THEN 'marwan'
            WHEN 37 THEN 'dhirgham' WHEN 38 THEN 'alaa' WHEN 39 THEN 'hussam' WHEN 40 THEN 'rami'
            WHEN 41 THEN 'abdulrahman' WHEN 42 THEN 'eyad' WHEN 43 THEN 'taha' WHEN 44 THEN 'wissam'
            WHEN 45 THEN 'suhaib' WHEN 46 THEN 'muath' WHEN 47 THEN 'baraa' WHEN 48 THEN 'usama'
            ELSE CONCAT('student', s.id)
        END,
        LPAD(s.id, 4, '0')
    ),
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    s.full_name,
    'student',
    'active',
    s.id,
    'password'
FROM `students` s
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), student_id = VALUES(student_id);

-- ربط حسابات الطلاب بسجلاتهم (تحديث user_id في جدول students)
UPDATE `students` st 
JOIN `users` u ON u.student_id = st.id 
SET st.user_id = u.id;

-- ═══════════════════════════════════════════════════════════════════════════════
-- 4. 📚 تعيينات المعلمين
-- ═══════════════════════════════════════════════════════════════════════════════

-- خالد - القراءة (1-3) واللغة العربية (4-6)
INSERT INTO `teacher_assignments` (`teacher_id`, `subject_name`, `class_id`, `section`, `can_enter_grades`, `is_active`)
SELECT u.id, 'القراءة', c.class_id, c.section, 1, 1
FROM `users` u, (SELECT 1 as class_id, 'أ' as section UNION SELECT 1,'ب' UNION SELECT 1,'ج' UNION SELECT 1,'د' UNION SELECT 2,'أ' UNION SELECT 2,'ب' UNION SELECT 2,'ج' UNION SELECT 2,'د' UNION SELECT 3,'أ' UNION SELECT 3,'ب' UNION SELECT 3,'ج' UNION SELECT 3,'د') c
WHERE u.username = 'khaled_t' ON DUPLICATE KEY UPDATE is_active = 1;

INSERT INTO `teacher_assignments` (`teacher_id`, `subject_name`, `class_id`, `section`, `can_enter_grades`, `is_active`)
SELECT u.id, 'اللغة العربية', c.class_id, c.section, 1, 1
FROM `users` u, (SELECT 4 as class_id, 'أ' as section UNION SELECT 4,'ب' UNION SELECT 4,'ج' UNION SELECT 4,'د' UNION SELECT 5,'أ' UNION SELECT 5,'ب' UNION SELECT 5,'ج' UNION SELECT 5,'د' UNION SELECT 6,'أ' UNION SELECT 6,'ب' UNION SELECT 6,'ج' UNION SELECT 6,'د') c
WHERE u.username = 'khaled_t' ON DUPLICATE KEY UPDATE is_active = 1;

-- سامي - الرياضيات (جميع الصفوف)
INSERT INTO `teacher_assignments` (`teacher_id`, `subject_name`, `class_id`, `section`, `can_enter_grades`, `is_active`)
SELECT u.id, 'الرياضيات', c.class_id, c.section, 1, 1
FROM `users` u, (SELECT 1 as class_id, 'أ' as section UNION SELECT 1,'ب' UNION SELECT 1,'ج' UNION SELECT 1,'د' UNION SELECT 2,'أ' UNION SELECT 2,'ب' UNION SELECT 2,'ج' UNION SELECT 2,'د' UNION SELECT 3,'أ' UNION SELECT 3,'ب' UNION SELECT 3,'ج' UNION SELECT 3,'د' UNION SELECT 4,'أ' UNION SELECT 4,'ب' UNION SELECT 4,'ج' UNION SELECT 4,'د' UNION SELECT 5,'أ' UNION SELECT 5,'ب' UNION SELECT 5,'ج' UNION SELECT 5,'د' UNION SELECT 6,'أ' UNION SELECT 6,'ب' UNION SELECT 6,'ج' UNION SELECT 6,'د') c
WHERE u.username = 'sami_t' ON DUPLICATE KEY UPDATE is_active = 1;

-- فاروق - العلوم (جميع الصفوف)
INSERT INTO `teacher_assignments` (`teacher_id`, `subject_name`, `class_id`, `section`, `can_enter_grades`, `is_active`)
SELECT u.id, 'العلوم', c.class_id, c.section, 1, 1
FROM `users` u, (SELECT 1 as class_id, 'أ' as section UNION SELECT 1,'ب' UNION SELECT 1,'ج' UNION SELECT 1,'د' UNION SELECT 2,'أ' UNION SELECT 2,'ب' UNION SELECT 2,'ج' UNION SELECT 2,'د' UNION SELECT 3,'أ' UNION SELECT 3,'ب' UNION SELECT 3,'ج' UNION SELECT 3,'د' UNION SELECT 4,'أ' UNION SELECT 4,'ب' UNION SELECT 4,'ج' UNION SELECT 4,'د' UNION SELECT 5,'أ' UNION SELECT 5,'ب' UNION SELECT 5,'ج' UNION SELECT 5,'د' UNION SELECT 6,'أ' UNION SELECT 6,'ب' UNION SELECT 6,'ج' UNION SELECT 6,'د') c
WHERE u.username = 'farouq_t' ON DUPLICATE KEY UPDATE is_active = 1;

-- عبدالستار - التربية الدينية (جميع الصفوف)
INSERT INTO `teacher_assignments` (`teacher_id`, `subject_name`, `class_id`, `section`, `can_enter_grades`, `is_active`)
SELECT u.id, 'التربية الدينية', c.class_id, c.section, 1, 1
FROM `users` u, (SELECT 1 as class_id, 'أ' as section UNION SELECT 1,'ب' UNION SELECT 1,'ج' UNION SELECT 1,'د' UNION SELECT 2,'أ' UNION SELECT 2,'ب' UNION SELECT 2,'ج' UNION SELECT 2,'د' UNION SELECT 3,'أ' UNION SELECT 3,'ب' UNION SELECT 3,'ج' UNION SELECT 3,'د' UNION SELECT 4,'أ' UNION SELECT 4,'ب' UNION SELECT 4,'ج' UNION SELECT 4,'د' UNION SELECT 5,'أ' UNION SELECT 5,'ب' UNION SELECT 5,'ج' UNION SELECT 5,'د' UNION SELECT 6,'أ' UNION SELECT 6,'ب' UNION SELECT 6,'ج' UNION SELECT 6,'د') c
WHERE u.username = 'abdulsattar_t' ON DUPLICATE KEY UPDATE is_active = 1;

-- مازن - اللغة الإنجليزية (جميع الصفوف)
INSERT INTO `teacher_assignments` (`teacher_id`, `subject_name`, `class_id`, `section`, `can_enter_grades`, `is_active`)
SELECT u.id, 'اللغة الإنجليزية', c.class_id, c.section, 1, 1
FROM `users` u, (SELECT 1 as class_id, 'أ' as section UNION SELECT 1,'ب' UNION SELECT 1,'ج' UNION SELECT 1,'د' UNION SELECT 2,'أ' UNION SELECT 2,'ب' UNION SELECT 2,'ج' UNION SELECT 2,'د' UNION SELECT 3,'أ' UNION SELECT 3,'ب' UNION SELECT 3,'ج' UNION SELECT 3,'د' UNION SELECT 4,'أ' UNION SELECT 4,'ب' UNION SELECT 4,'ج' UNION SELECT 4,'د' UNION SELECT 5,'أ' UNION SELECT 5,'ب' UNION SELECT 5,'ج' UNION SELECT 5,'د' UNION SELECT 6,'أ' UNION SELECT 6,'ب' UNION SELECT 6,'ج' UNION SELECT 6,'د') c
WHERE u.username = 'mazen_t' ON DUPLICATE KEY UPDATE is_active = 1;

-- نبيل - الاجتماعيات (4-6 فقط)
INSERT INTO `teacher_assignments` (`teacher_id`, `subject_name`, `class_id`, `section`, `can_enter_grades`, `is_active`)
SELECT u.id, 'الاجتماعيات', c.class_id, c.section, 1, 1
FROM `users` u, (SELECT 4 as class_id, 'أ' as section UNION SELECT 4,'ب' UNION SELECT 4,'ج' UNION SELECT 4,'د' UNION SELECT 5,'أ' UNION SELECT 5,'ب' UNION SELECT 5,'ج' UNION SELECT 5,'د' UNION SELECT 6,'أ' UNION SELECT 6,'ب' UNION SELECT 6,'ج' UNION SELECT 6,'د') c
WHERE u.username = 'nabil_t' ON DUPLICATE KEY UPDATE is_active = 1;

-- ═══════════════════════════════════════════════════════════════════════════════
-- 5. 📅 الجدول الأسبوعي (جميع الصفوف - شعبة أ)
-- ═══════════════════════════════════════════════════════════════════════════════

-- الصف الأول - شعبة أ
INSERT INTO `schedules` (`class_id`, `section`, `day_of_week`, `lesson_number`, `subject_name`, `teacher_id`, `academic_year`)
SELECT 1, 'أ', d.day, p.num, s.subj, (SELECT id FROM users WHERE username = s.teacher), '2025'
FROM (SELECT 'الأحد' day UNION SELECT 'الاثنين' UNION SELECT 'الثلاثاء' UNION SELECT 'الأربعاء' UNION SELECT 'الخميس') d,
     (SELECT 1 num UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) p,
     (SELECT 'القراءة' subj, 'khaled_t' teacher, 1 ord UNION SELECT 'الرياضيات','sami_t',2 UNION SELECT 'العلوم','farouq_t',3 UNION SELECT 'التربية الدينية','abdulsattar_t',4 UNION SELECT 'اللغة الإنجليزية','mazen_t',5) s
WHERE p.num = s.ord
ON DUPLICATE KEY UPDATE subject_name = VALUES(subject_name);

-- الصف الثاني - شعبة أ
INSERT INTO `schedules` (`class_id`, `section`, `day_of_week`, `lesson_number`, `subject_name`, `teacher_id`, `academic_year`)
SELECT 2, 'أ', d.day, p.num, s.subj, (SELECT id FROM users WHERE username = s.teacher), '2025'
FROM (SELECT 'الأحد' day UNION SELECT 'الاثنين' UNION SELECT 'الثلاثاء' UNION SELECT 'الأربعاء' UNION SELECT 'الخميس') d,
     (SELECT 1 num UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) p,
     (SELECT 'القراءة' subj, 'khaled_t' teacher, 1 ord UNION SELECT 'الرياضيات','sami_t',2 UNION SELECT 'العلوم','farouq_t',3 UNION SELECT 'التربية الدينية','abdulsattar_t',4 UNION SELECT 'اللغة الإنجليزية','mazen_t',5) s
WHERE p.num = s.ord
ON DUPLICATE KEY UPDATE subject_name = VALUES(subject_name);

-- الصف الثالث - شعبة أ
INSERT INTO `schedules` (`class_id`, `section`, `day_of_week`, `lesson_number`, `subject_name`, `teacher_id`, `academic_year`)
SELECT 3, 'أ', d.day, p.num, s.subj, (SELECT id FROM users WHERE username = s.teacher), '2025'
FROM (SELECT 'الأحد' day UNION SELECT 'الاثنين' UNION SELECT 'الثلاثاء' UNION SELECT 'الأربعاء' UNION SELECT 'الخميس') d,
     (SELECT 1 num UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) p,
     (SELECT 'القراءة' subj, 'khaled_t' teacher, 1 ord UNION SELECT 'الرياضيات','sami_t',2 UNION SELECT 'العلوم','farouq_t',3 UNION SELECT 'التربية الدينية','abdulsattar_t',4 UNION SELECT 'اللغة الإنجليزية','mazen_t',5) s
WHERE p.num = s.ord
ON DUPLICATE KEY UPDATE subject_name = VALUES(subject_name);

-- الصف الرابع - شعبة أ (مع الاجتماعيات)
INSERT INTO `schedules` (`class_id`, `section`, `day_of_week`, `lesson_number`, `subject_name`, `teacher_id`, `academic_year`)
SELECT 4, 'أ', d.day, p.num, s.subj, (SELECT id FROM users WHERE username = s.teacher), '2025'
FROM (SELECT 'الأحد' day UNION SELECT 'الاثنين' UNION SELECT 'الثلاثاء' UNION SELECT 'الأربعاء' UNION SELECT 'الخميس') d,
     (SELECT 1 num UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) p,
     (SELECT 'اللغة العربية' subj, 'khaled_t' teacher, 1 ord UNION SELECT 'الرياضيات','sami_t',2 UNION SELECT 'العلوم','farouq_t',3 UNION SELECT 'التربية الدينية','abdulsattar_t',4 UNION SELECT 'اللغة الإنجليزية','mazen_t',5 UNION SELECT 'الاجتماعيات','nabil_t',6) s
WHERE p.num = s.ord
ON DUPLICATE KEY UPDATE subject_name = VALUES(subject_name);

-- الصف الخامس - شعبة أ
INSERT INTO `schedules` (`class_id`, `section`, `day_of_week`, `lesson_number`, `subject_name`, `teacher_id`, `academic_year`)
SELECT 5, 'أ', d.day, p.num, s.subj, (SELECT id FROM users WHERE username = s.teacher), '2025'
FROM (SELECT 'الأحد' day UNION SELECT 'الاثنين' UNION SELECT 'الثلاثاء' UNION SELECT 'الأربعاء' UNION SELECT 'الخميس') d,
     (SELECT 1 num UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) p,
     (SELECT 'اللغة العربية' subj, 'khaled_t' teacher, 1 ord UNION SELECT 'الرياضيات','sami_t',2 UNION SELECT 'العلوم','farouq_t',3 UNION SELECT 'التربية الدينية','abdulsattar_t',4 UNION SELECT 'اللغة الإنجليزية','mazen_t',5 UNION SELECT 'الاجتماعيات','nabil_t',6) s
WHERE p.num = s.ord
ON DUPLICATE KEY UPDATE subject_name = VALUES(subject_name);

-- الصف السادس - شعبة أ
INSERT INTO `schedules` (`class_id`, `section`, `day_of_week`, `lesson_number`, `subject_name`, `teacher_id`, `academic_year`)
SELECT 6, 'أ', d.day, p.num, s.subj, (SELECT id FROM users WHERE username = s.teacher), '2025'
FROM (SELECT 'الأحد' day UNION SELECT 'الاثنين' UNION SELECT 'الثلاثاء' UNION SELECT 'الأربعاء' UNION SELECT 'الخميس') d,
     (SELECT 1 num UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) p,
     (SELECT 'اللغة العربية' subj, 'khaled_t' teacher, 1 ord UNION SELECT 'الرياضيات','sami_t',2 UNION SELECT 'العلوم','farouq_t',3 UNION SELECT 'التربية الدينية','abdulsattar_t',4 UNION SELECT 'اللغة الإنجليزية','mazen_t',5 UNION SELECT 'الاجتماعيات','nabil_t',6) s
WHERE p.num = s.ord
ON DUPLICATE KEY UPDATE subject_name = VALUES(subject_name);

-- ═══════════════════════════════════════════════════════════════════════════════
-- 6. 📝 درجات الصفوف 1-4 (الفترة الأولى - 2025)
-- ═══════════════════════════════════════════════════════════════════════════════

INSERT INTO `grades` (`student_id`, `subject_name`, `class_id`, `section`, `term`, `grade`, `max_grade`, `teacher_id`, `academic_year`)
SELECT s.id, subj.name, s.class_id, s.section, 'first', 
       ROUND(50 + RAND() * 50, 0), 100, 
       (SELECT id FROM users WHERE username = subj.teacher), '2025'
FROM students s
CROSS JOIN (
    SELECT 'القراءة' name, 'khaled_t' teacher UNION
    SELECT 'الرياضيات', 'sami_t' UNION
    SELECT 'العلوم', 'farouq_t' UNION
    SELECT 'التربية الدينية', 'abdulsattar_t' UNION
    SELECT 'اللغة الإنجليزية', 'mazen_t'
) subj
WHERE s.class_id BETWEEN 1 AND 3
ON DUPLICATE KEY UPDATE grade = VALUES(grade);

-- درجات الصف الرابع (مع اللغة العربية والاجتماعيات)
INSERT INTO `grades` (`student_id`, `subject_name`, `class_id`, `section`, `term`, `grade`, `max_grade`, `teacher_id`, `academic_year`)
SELECT s.id, subj.name, s.class_id, s.section, 'first', 
       ROUND(50 + RAND() * 50, 0), 100, 
       (SELECT id FROM users WHERE username = subj.teacher), '2025'
FROM students s
CROSS JOIN (
    SELECT 'اللغة العربية' name, 'khaled_t' teacher UNION
    SELECT 'الرياضيات', 'sami_t' UNION
    SELECT 'العلوم', 'farouq_t' UNION
    SELECT 'التربية الدينية', 'abdulsattar_t' UNION
    SELECT 'اللغة الإنجليزية', 'mazen_t' UNION
    SELECT 'الاجتماعيات', 'nabil_t'
) subj
WHERE s.class_id = 4
ON DUPLICATE KEY UPDATE grade = VALUES(grade);

-- ═══════════════════════════════════════════════════════════════════════════════
-- 7. 📝 الدرجات الشهرية للصفوف 5-6 (2025)
-- ═══════════════════════════════════════════════════════════════════════════════
-- النظام الشهري: درجات من 50-100 لكل شهر + معدلات + نتيجة نهائية

INSERT INTO `monthly_grades` (
    `student_id`, `class_id`, `section`, `subject_name`, `academic_year`,
    `oct`, `nov`, `dec`, `first_avg`, `mid_exam`,
    `mar`, `apr`, `second_avg`, `yearly_avg`, `final_exam`,
    `final_grade`, `final_result`
)
SELECT 
    s.id, s.class_id, s.section, subj.name, '2025',
    -- النصف الأول (درجات من 50-100)
    @oct := ROUND(50 + RAND() * 50, 0),
    @nov := ROUND(50 + RAND() * 50, 0),
    @dec := ROUND(50 + RAND() * 50, 0),
    @first_avg := ROUND((@oct + @nov + @dec) / 3, 0),
    @mid_exam := ROUND(50 + RAND() * 50, 0),
    -- النصف الثاني
    @mar := ROUND(50 + RAND() * 50, 0),
    @apr := ROUND(50 + RAND() * 50, 0),
    @second_avg := ROUND((@mar + @apr) / 2, 0),
    -- معدل السعي السنوي = (معدل النصف الأول + امتحان نصف السنة + معدل النصف الثاني) / 3
    @yearly_avg := ROUND((@first_avg + @mid_exam + @second_avg) / 3, 0),
    @final_exam := ROUND(50 + RAND() * 50, 0),
    -- الدرجة النهائية = (معدل السعي السنوي + الامتحان النهائي) / 2 (50% + 50%)
    @final_grade := ROUND((@yearly_avg + @final_exam) / 2, 0),
    -- نتيجة الحالة
    CASE 
        WHEN @final_grade >= 50 THEN 'ناجح'
        WHEN @final_grade >= 40 THEN 'مكمل'
        ELSE 'راسب'
    END
FROM students s
CROSS JOIN (
    SELECT 'اللغة العربية' name UNION
    SELECT 'الرياضيات' UNION
    SELECT 'العلوم' UNION
    SELECT 'التربية الدينية' UNION
    SELECT 'اللغة الإنجليزية' UNION
    SELECT 'الاجتماعيات'
) subj
WHERE s.class_id IN (5, 6)
ON DUPLICATE KEY UPDATE 
    `oct` = VALUES(`oct`), `nov` = VALUES(`nov`), `dec` = VALUES(`dec`),
    `first_avg` = VALUES(`first_avg`), `mid_exam` = VALUES(`mid_exam`),
    `mar` = VALUES(`mar`), `apr` = VALUES(`apr`), `second_avg` = VALUES(`second_avg`),
    `yearly_avg` = VALUES(`yearly_avg`), `final_exam` = VALUES(`final_exam`),
    `final_grade` = VALUES(`final_grade`), `final_result` = VALUES(`final_result`);

-- ═══════════════════════════════════════════════════════════════════════════════
-- 8. 📊 حضور الطلاب (آخر 3 أيام)
-- ═══════════════════════════════════════════════════════════════════════════════

INSERT INTO `attendance` (`student_id`, `date`, `lesson_number`, `status`, `notes`) VALUES
(1, CURDATE(), 1, 'present', NULL), (2, CURDATE(), 1, 'present', NULL),
(3, CURDATE(), 1, 'absent', 'غياب'), (4, CURDATE(), 1, 'present', NULL),
(5, CURDATE(), 1, 'late', 'تأخر 10 دقائق'), (6, CURDATE(), 1, 'present', NULL),
(9, CURDATE(), 1, 'present', NULL), (10, CURDATE(), 1, 'late', 'تأخر'),
(17, CURDATE(), 1, 'present', NULL), (18, CURDATE(), 1, 'absent', 'مريض'),
(25, CURDATE(), 1, 'present', NULL), (26, CURDATE(), 1, 'present', NULL),
(33, CURDATE(), 1, 'present', NULL), (34, CURDATE(), 1, 'present', NULL),
(41, CURDATE(), 1, 'present', NULL), (42, CURDATE(), 1, 'absent', 'غياب')
ON DUPLICATE KEY UPDATE status = VALUES(status);

-- ═══════════════════════════════════════════════════════════════════════════════
-- 9. 🏖️ الإجازات
-- ═══════════════════════════════════════════════════════════════════════════════

INSERT INTO `leaves` (`person_type`, `person_id`, `leave_type`, `start_date`, `end_date`, `days_count`, `reason`) VALUES
('student', 3, 'sick', DATE_SUB(CURDATE(), INTERVAL 1 DAY), CURDATE(), 2, 'مرض'),
('student', 18, 'sick', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 2, 'حمى'),
('student', 42, 'regular', CURDATE(), CURDATE(), 1, 'سفر'),
('teacher', 6, 'emergency', CURDATE(), CURDATE(), 1, 'ظرف عائلي طارئ')
ON DUPLICATE KEY UPDATE reason = VALUES(reason);

SET FOREIGN_KEY_CHECKS = 1;

-- ═══════════════════════════════════════════════════════════════════════════════
-- ✅ ملخص البيانات:
-- 👑 المدير: admin / password
-- 👔 المعاون: assistant1 / password
-- 👨‍🏫 المعلمين: khaled_t, sami_t, farouq_t, abdulsattar_t, mazen_t, nabil_t (كلمة المرور: password)
-- 👨‍🎓 الطلاب: 48 طالب (8 لكل صف) مع حسابات مرتبطة
--    • أمثلة: ahmad0001, yousef0002, ali0003... (كلمة المرور: password)
-- 📅 الجدول: 6 صفوف × 5 أيام × 5-6 حصص
-- 📝 الدرجات:
--    • الصفوف 1-4: نظام الفصول (grades table) - الفترة الأولى 2025
--    • الصفوف 5-6: نظام شهري كامل (monthly_grades table) - كل الأشهر + النتيجة النهائية
-- ═══════════════════════════════════════════════════════════════════════════════
