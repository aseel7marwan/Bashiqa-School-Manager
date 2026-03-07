-- ═══════════════════════════════════════════════════════════════════════════════
-- School Manager - Unified Database Schema (Complete)
-- قاعدة بيانات موحدة شاملة لنظام إدارة المدرسة
-- ═══════════════════════════════════════════════════════════════════════════════
-- 
-- 📋 هذا الملف يحتوي على:
-- - جميع الجداول الأساسية (16 جدول)
-- - حقول الربط SSOT (Single Source of Truth)
-- - جدول الدرجات الشهرية للصفوف 5 و 6
-- - الفهارس والعلاقات (Foreign Keys)
-- - فهارس تحسين الأداء (Optimization Indexes)
-- - حساب المدير الافتراضي
--
-- 📌 استخدام الملف:
-- 1. افتح phpMyAdmin
-- 2. أنشئ قاعدة البيانات (إذا لم تكن موجودة)
-- 3. انتقل إلى تبويب Import
-- 4. ارفع هذا الملف
-- 5. اضغط "Go" لتنفيذه
--
-- ═══════════════════════════════════════════════════════════════════════════════

-- ═══════════════════════════════════════════════════════════════════════════════
-- اختر قاعدة البيانات المناسبة (غيّر حسب البيئة)
-- ═══════════════════════════════════════════════════════════════════════════════

-- للاستضافة:
-- USE `your_hosting_db_name`;

-- للخادم المحلي XAMPP:
-- USE `school_db`;

-- ═══════════════════════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ═══════════════════════════════════════════════════════════════════════════════
-- 1. جدول المستخدمين (الحسابات)
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `role` VARCHAR(20) NOT NULL DEFAULT 'teacher',
    `status` VARCHAR(20) DEFAULT 'active',
    `theme` VARCHAR(20) DEFAULT 'light',
    
    -- حقول الربط SSOT
    `teacher_id` INT NULL DEFAULT NULL COMMENT 'ربط بسجل المعلم الأساسي',
    `student_id` INT NULL DEFAULT NULL COMMENT 'ربط بسجل الطالب الأساسي',
    `plain_password` VARCHAR(255) NULL DEFAULT NULL COMMENT 'كلمة المرور للطباعة',
    
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_role` (`role`),
    INDEX `idx_status` (`status`),
    INDEX `idx_teacher_id` (`teacher_id`),
    INDEX `idx_student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='جدول حسابات المستخدمين';

-- ═══════════════════════════════════════════════════════════════════════════════
-- 2. جدول الطلاب (السجل الأساسي - مصدر الحقيقة)
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `class_id` INT NOT NULL,
    `section` VARCHAR(5) NOT NULL,
    `photo` VARCHAR(255),
    `birth_date` DATE,
    `gender` VARCHAR(10) DEFAULT 'male',
    `parent_name` VARCHAR(100),
    `parent_phone` VARCHAR(20),
    `address` TEXT,
    `user_id` INT DEFAULT NULL COMMENT 'ربط بحساب الدخول',
    `enrollment_date` DATE DEFAULT (CURDATE()),
    
    -- حقول البطاقة المدرسية الإضافية
    `province` VARCHAR(100) NULL,
    `city_village` VARCHAR(100) NULL,
    `birth_place` VARCHAR(100) NULL,
    `sibling_order` VARCHAR(50) NULL,
    `guardian_job` VARCHAR(100) NULL,
    `guardian_relation` VARCHAR(50) NULL,
    `father_alive` VARCHAR(10) DEFAULT 'نعم',
    `mother_alive` VARCHAR(10) DEFAULT 'نعم',
    `father_education` VARCHAR(100) NULL,
    `mother_education` VARCHAR(100) NULL,
    `father_age_at_registration` INT NULL,
    `mother_age_at_registration` INT NULL,
    `parents_kinship` VARCHAR(100) NULL,
    `mother_name` VARCHAR(100) NULL,
    `nationality_number` VARCHAR(50) NULL,
    `previous_schools` TEXT NULL,
    `social_status` VARCHAR(100) NULL,
    `health_status` TEXT NULL,
    `academic_records` TEXT NULL,
    `attendance_records` TEXT NULL,
    `registration_number` VARCHAR(50) NULL,
    `data_changes` TEXT NULL,
    `notes` TEXT NULL,
    `photo_primary` VARCHAR(255) NULL,
    `photo_intermediate` VARCHAR(255) NULL,
    `photo_secondary` VARCHAR(255) NULL,
    
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_students_class` (`class_id`, `section`),
    INDEX `idx_students_name` (`full_name`),
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='جدول الطلاب - السجل الأساسي (مصدر الحقيقة)';

-- ═══════════════════════════════════════════════════════════════════════════════
-- 3. جدول المعلمين (السجل الأساسي - مصدر الحقيقة)
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `teachers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    
    -- البيانات الشخصية الأساسية
    `full_name` VARCHAR(100) NOT NULL,
    `birth_place` VARCHAR(100),
    `birth_date` DATE,
    `mother_name` VARCHAR(100),
    `phone` VARCHAR(20),
    `email` VARCHAR(100),
    `blood_type` VARCHAR(10),
    
    -- الشهادة والتخصص
    `certificate` VARCHAR(100),
    `specialization` VARCHAR(100),
    `institute_name` VARCHAR(200),
    `graduation_year` VARCHAR(10),
    
    -- بيانات التعيين والوظيفة
    `hire_date` DATE DEFAULT (CURDATE()),
    `first_job_date` DATE,
    `current_school_date` DATE,
    `hire_order_number` VARCHAR(50),
    `hire_order_date` DATE,
    `school_order_number` VARCHAR(50),
    `transfer_order_number` VARCHAR(50),
    `transfer_date` DATE,
    `interruption_date` DATE,
    `interruption_reason` VARCHAR(255),
    `return_date` DATE,
    `job_grade` VARCHAR(50),
    `career_stage` VARCHAR(50),
    
    -- الوثائق والهويات
    `national_id` VARCHAR(50),
    `national_id_date` DATE,
    `record_number` VARCHAR(50),
    `page_number` VARCHAR(50),
    `nationality_cert_number` VARCHAR(50),
    `nationality_cert_date` DATE,
    `nationality_folder_number` VARCHAR(50),
    `residence_card` VARCHAR(100),
    `form_number` VARCHAR(50),
    `ration_card_number` VARCHAR(50),
    `agent_info` VARCHAR(200),
    `ration_center` VARCHAR(100),
    
    -- الحالة الاجتماعية
    `marital_status` VARCHAR(50),
    `marriage_date` DATE,
    `spouse_name` VARCHAR(100),
    `spouse_job` VARCHAR(100),
    `marriage_contract_info` VARCHAR(200),
    
    -- التقاعد
    `retirement_order_number` VARCHAR(50),
    `retirement_date` DATE,
    
    -- معلومات إضافية
    `courses` TEXT,
    `notes` TEXT,
    `photo` VARCHAR(255),
    `data_writers` TEXT,
    
    -- معلومات النظام
    `user_id` INT DEFAULT NULL COMMENT 'ربط بحساب الدخول',
    `status` VARCHAR(20) DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_teachers_name` (`full_name`),
    INDEX `idx_teachers_status` (`status`),
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='جدول المعلمين - السجل الأساسي (مصدر الحقيقة)';

-- ═══════════════════════════════════════════════════════════════════════════════
-- 4. جدول تعيينات المعلمين (ربط المعلمين بالمواد)
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `teacher_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` INT NOT NULL COMMENT 'معرف المعلم (من جدول users)',
    `teacher_db_id` INT NULL COMMENT 'ربط بسجل المعلم في جدول teachers',
    `subject_name` VARCHAR(100) NOT NULL COMMENT 'اسم المادة',
    `class_id` INT NOT NULL COMMENT 'رقم الصف (1-6)',
    `section` VARCHAR(10) NOT NULL COMMENT 'الشعبة',
    `can_enter_grades` TINYINT(1) DEFAULT 1 COMMENT 'يمكنه إدخال درجات',
    `assigned_by` INT COMMENT 'من قام بالتعيين (المدير)',
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `is_active` TINYINT(1) DEFAULT 1 COMMENT 'هل التعيين فعال',
    `notes` TEXT COMMENT 'ملاحظات',
    
    INDEX `idx_teacher` (`teacher_id`),
    INDEX `idx_teacher_db_id` (`teacher_db_id`),
    INDEX `idx_class_section` (`class_id`, `section`),
    INDEX `idx_subject` (`subject_name`),
    UNIQUE KEY `unique_assignment` (`teacher_id`, `subject_name`, `class_id`, `section`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='جدول تعيينات المعلمين للمواد والصفوف';

-- ═══════════════════════════════════════════════════════════════════════════════
-- 5. جدول التعيينات المؤقتة (قبل إنشاء الحساب)
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `teacher_assignments_temp` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_db_id` INT NOT NULL COMMENT 'معرف المعلم من جدول teachers',
    `subject_name` VARCHAR(100) NOT NULL,
    `class_id` INT NOT NULL,
    `section` VARCHAR(10) NOT NULL,
    `can_enter_grades` TINYINT(1) DEFAULT 1,
    `assigned_by` INT,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT,
    
    INDEX `idx_teacher_db` (`teacher_db_id`),
    UNIQUE KEY `unique_temp_assignment` (`teacher_db_id`, `subject_name`, `class_id`, `section`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='جدول التعيينات المؤقتة قبل إنشاء الحساب';

-- ═══════════════════════════════════════════════════════════════════════════════
-- 6. جدول الجدول الأسبوعي
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_id` INT NOT NULL,
    `section` VARCHAR(5) NOT NULL,
    `day_of_week` VARCHAR(20) NOT NULL,
    `lesson_number` INT NOT NULL,
    `subject_name` VARCHAR(100) NOT NULL,
    `teacher_id` INT COMMENT 'معرف المعلم من جدول users',
    `teacher_db_id` INT NULL COMMENT 'ربط بسجل المعلم في جدول teachers',
    `academic_year` VARCHAR(10) DEFAULT '2025' COMMENT 'السنة الدراسية',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY `unique_schedule` (`class_id`, `section`, `day_of_week`, `lesson_number`, `academic_year`),
    INDEX `idx_schedules_class` (`class_id`, `section`),
    INDEX `idx_teacher_db` (`teacher_db_id`),
    INDEX `idx_academic_year` (`academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='جدول الجدول الأسبوعي - محفوظ بالسنة الدراسية';

-- ═══════════════════════════════════════════════════════════════════════════════
-- 7. جدول حضور الطلاب
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL COMMENT 'ربط بسجل الطالب الأساسي',
    `date` DATE NOT NULL,
    `lesson_number` INT NOT NULL,
    `status` VARCHAR(20) NOT NULL,
    `recorded_by` INT,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY `unique_attendance` (`student_id`, `date`, `lesson_number`),
    INDEX `idx_attendance_date` (`date`),
    INDEX `idx_attendance_student` (`student_id`),
    
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='جدول حضور الطلاب';

-- ═══════════════════════════════════════════════════════════════════════════════
-- 8. جدول حضور المعلمين للحصص
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `teacher_attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` INT NOT NULL COMMENT 'معرف المعلم من جدول users',
    `teacher_db_id` INT NULL COMMENT 'ربط بسجل المعلم في جدول teachers',
    `date` DATE NOT NULL,
    `lesson_number` INT NOT NULL,
    `class_id` INT,
    `section` VARCHAR(10),
    `subject_name` VARCHAR(100),
    `status` ENUM('present', 'late', 'absent') DEFAULT 'present',
    `recorded_by` INT,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY `unique_attendance` (`teacher_id`, `date`, `lesson_number`, `class_id`, `section`),
    INDEX `idx_teacher` (`teacher_id`),
    INDEX `idx_teacher_db` (`teacher_db_id`),
    INDEX `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='جدول حضور المعلمين للحصص';

-- ═══════════════════════════════════════════════════════════════════════════════
-- 9. جدول غيابات المعلمين الإدارية
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `teacher_absences` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` INT NOT NULL COMMENT 'معرف المعلم من جدول users',
    `teacher_db_id` INT NULL COMMENT 'ربط بسجل المعلم في جدول teachers',
    `date` DATE NOT NULL,
    `lesson_number` INT DEFAULT NULL,
    `reason` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY `unique_absence` (`teacher_id`, `date`, `lesson_number`),
    INDEX `idx_teacher_db` (`teacher_db_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='جدول غيابات المعلمين الإدارية';

-- ═══════════════════════════════════════════════════════════════════════════════
-- 10. جدول أحداث المدرسة
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `school_events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `event_date` DATE NOT NULL,
    `event_type` VARCHAR(50) DEFAULT 'event',
    `is_holiday` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='جدول أحداث المدرسة';

-- ═══════════════════════════════════════════════════════════════════════════════
-- 11. جدول الإجازات (للطلاب والمعلمين)
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `leaves` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `person_type` ENUM('teacher', 'student') NOT NULL,
    `person_id` INT NOT NULL COMMENT 'معرف الشخص من جدوله الأساسي (students.id أو teachers.id)',
    `leave_type` ENUM('sick', 'regular', 'emergency') NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `days_count` INT NOT NULL DEFAULT 1,
    `reason` TEXT NULL,
    `notes` TEXT NULL,
    `recorded_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_person` (`person_type`, `person_id`),
    INDEX `idx_leave_type` (`leave_type`),
    INDEX `idx_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='جدول الإجازات - يربط بالسجل الأساسي (students.id أو teachers.id)';

-- ═══════════════════════════════════════════════════════════════════════════════
-- 12. جدول الدرجات
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `grades` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL COMMENT 'ربط بسجل الطالب الأساسي',
    `subject_name` VARCHAR(100) NOT NULL,
    `class_id` INT NOT NULL,
    `section` VARCHAR(10) NOT NULL,
    `term` VARCHAR(20) NOT NULL DEFAULT 'first',
    `grade` DECIMAL(5,2) NOT NULL,
    `max_grade` DECIMAL(5,2) DEFAULT 100,
    `teacher_id` INT COMMENT 'معرف المعلم من جدول users',
    `academic_year` VARCHAR(20),
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY `unique_grade` (`student_id`, `subject_name`, `class_id`, `section`, `term`, `academic_year`),
    INDEX `idx_student` (`student_id`),
    INDEX `idx_class` (`class_id`, `section`),
    INDEX `idx_term` (`term`),
    
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='جدول درجات الطلاب';

-- ═══════════════════════════════════════════════════════════════════════════════
-- 13. جدول الدرجات الشهرية (للصفوف 5 و 6)
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `monthly_grades` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL COMMENT 'ربط بسجل الطالب الأساسي',
    `class_id` INT NOT NULL,
    `section` VARCHAR(10) NOT NULL,
    `subject_name` VARCHAR(100) NOT NULL,
    `academic_year` VARCHAR(10) NOT NULL,
    
    -- النصف الأول
    `oct` DECIMAL(5,2) DEFAULT NULL COMMENT 'تشرين الأول',
    `nov` DECIMAL(5,2) DEFAULT NULL COMMENT 'تشرين الثاني',
    `dec` DECIMAL(5,2) DEFAULT NULL COMMENT 'كانون الأول',
    `first_avg` DECIMAL(5,2) DEFAULT NULL COMMENT 'معدل النصف الأول',
    `mid_exam` DECIMAL(5,2) DEFAULT NULL COMMENT 'امتحان نصف السنة',
    
    -- النصف الثاني
    `mar` DECIMAL(5,2) DEFAULT NULL COMMENT 'آذار',
    `apr` DECIMAL(5,2) DEFAULT NULL COMMENT 'نيسان',
    `second_avg` DECIMAL(5,2) DEFAULT NULL COMMENT 'معدل النصف الثاني',
    `yearly_avg` DECIMAL(5,2) DEFAULT NULL COMMENT 'معدل السعي السنوي',
    `final_exam` DECIMAL(5,2) DEFAULT NULL COMMENT 'الامتحان النهائي',
    
    -- النتائج
    `final_grade` DECIMAL(5,2) DEFAULT NULL COMMENT 'الدرجة النهائية',
    `final_result` VARCHAR(50) DEFAULT NULL COMMENT 'ناجح/راسب/مكمل',
    `notes` TEXT DEFAULT NULL,
    
    -- التدقيق
    `updated_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY `unique_student_subject` (`student_id`, `subject_name`, `academic_year`),
    INDEX `idx_class_section` (`class_id`, `section`),
    INDEX `idx_academic_year` (`academic_year`),
    
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='جدول الدرجات الشهرية للصفوف 5 و 6';

-- ═══════════════════════════════════════════════════════════════════════════════
-- 14. جدول سجل العمليات
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `user_name` VARCHAR(100) NOT NULL,
    `user_role` VARCHAR(50) NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `action_type` ENUM('add', 'edit', 'delete', 'login', 'other') NOT NULL,
    `target_type` VARCHAR(50) NOT NULL,
    `target_id` INT DEFAULT NULL,
    `target_name` VARCHAR(200) DEFAULT NULL,
    `old_value` TEXT NULL,
    `new_value` TEXT NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_user` (`user_id`),
    INDEX `idx_action` (`action_type`),
    INDEX `idx_target` (`target_type`),
    INDEX `idx_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='جدول سجل العمليات';

-- ═══════════════════════════════════════════════════════════════════════════════
-- 15. جدول معدات الصفوف
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `classroom_equipment` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_id` INT NOT NULL,
    `section` VARCHAR(10) NOT NULL,
    `item_name` VARCHAR(100) NOT NULL,
    `quantity` INT DEFAULT 1,
    `condition_status` ENUM('new', 'good', 'fair', 'poor', 'broken') DEFAULT 'good',
    `notes` TEXT,
    `last_checked` DATE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_class` (`class_id`, `section`),
    INDEX `idx_condition` (`condition_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='جدول معدات الصفوف';

-- ═══════════════════════════════════════════════════════════════════════════════
-- 16. جدول محاولات تسجيل الدخول (للأمان)
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `username` VARCHAR(50) DEFAULT NULL,
    `attempt_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `success` TINYINT(1) DEFAULT 0,
    `user_agent` TEXT NULL,
    
    INDEX `idx_ip` (`ip_address`),
    INDEX `idx_time` (`attempt_time`),
    INDEX `idx_ip_time` (`ip_address`, `attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='جدول محاولات تسجيل الدخول';

-- ═══════════════════════════════════════════════════════════════════════════════
-- إعادة تفعيل Foreign Keys
-- ═══════════════════════════════════════════════════════════════════════════════
SET FOREIGN_KEY_CHECKS = 1;

-- ═══════════════════════════════════════════════════════════════════════════════
-- إنشاء حساب المدير الافتراضي (كلمة المرور: password)
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO `users` (`username`, `password_hash`, `full_name`, `role`, `status`) 
SELECT 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدير النظام', 'admin', 'active'
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `username` = 'admin');

-- ═══════════════════════════════════════════════════════════════════════════════
-- فهارس تحسين الأداء الإضافية (اختياري - قد تظهر أخطاء Duplicate إذا موجودة مسبقاً)
-- ═══════════════════════════════════════════════════════════════════════════════

-- جدول الطلاب
CREATE INDEX IF NOT EXISTS idx_students_full_name ON students(full_name);
CREATE INDEX IF NOT EXISTS idx_students_class_section ON students(class_id, section);
CREATE INDEX IF NOT EXISTS idx_students_user_id ON students(user_id);

-- جدول الحضور
CREATE INDEX IF NOT EXISTS idx_attendance_lesson ON attendance(lesson_number);
CREATE INDEX IF NOT EXISTS idx_attendance_status ON attendance(status);

-- جدول الجداول
CREATE INDEX IF NOT EXISTS idx_schedules_day ON schedules(day_of_week);
CREATE INDEX IF NOT EXISTS idx_schedules_teacher ON schedules(teacher_id);
CREATE INDEX IF NOT EXISTS idx_schedules_lesson ON schedules(lesson_number);

-- جدول المستخدمين
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);

-- جدول غيابات المعلمين
CREATE INDEX IF NOT EXISTS idx_teacher_absences_date ON teacher_absences(date);
CREATE INDEX IF NOT EXISTS idx_teacher_absences_teacher ON teacher_absences(teacher_id);

-- جدول المعلمين
CREATE INDEX IF NOT EXISTS idx_teachers_full_name ON teachers(full_name);

-- ═══════════════════════════════════════════════════════════════════════════════
-- 📊 ملخص الجداول (16 جدول):
-- ═══════════════════════════════════════════════════════════════════════════════
-- 
-- الجداول الأساسية (مصادر الحقيقة):
--   1. users              - حسابات المستخدمين
--   2. students           - سجلات الطلاب (مصدر الحقيقة)
--   3. teachers           - سجلات المعلمين (مصدر الحقيقة)
--
-- جداول العمليات:
--   4. teacher_assignments      - تعيينات المعلمين للمواد
--   5. teacher_assignments_temp - تعيينات مؤقتة
--   6. schedules                - الجدول الأسبوعي
--   7. attendance               - حضور الطلاب
--   8. teacher_attendance       - حضور المعلمين
--   9. teacher_absences         - غيابات المعلمين
--  10. school_events            - أحداث المدرسة
--  11. leaves                   - الإجازات
--  12. grades                   - الدرجات
--  13. monthly_grades           - الدرجات الشهرية
--
-- جداول النظام:
--  14. activity_logs            - سجل العمليات
--  15. classroom_equipment      - معدات الصفوف
--  16. login_attempts           - محاولات الدخول
--
-- ═══════════════════════════════════════════════════════════════════════════════
-- بيانات الدخول الافتراضية:
-- 👤 اسم المستخدم: admin
-- 🔑 كلمة المرور: password
-- ═══════════════════════════════════════════════════════════════════════════════
-- نظام إدارة المدرسة v3.0.0 - Unified Complete Schema
-- آخر تحديث: 2026-01-11
-- ═══════════════════════════════════════════════════════════════════════════════
