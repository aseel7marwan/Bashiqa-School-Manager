-- ═══════════════════════════════════════════════════════════════════════════════
-- Demo data — SANITIZED generic placeholders (no real students, grades, or emails)
-- Run only on a disposable database. Keeps admin row (username = admin).
-- ═══════════════════════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

DELETE FROM `monthly_grades` WHERE 1=1;
DELETE FROM `grades` WHERE 1=1;
DELETE FROM `attendance` WHERE 1=1;
DELETE FROM `teacher_attendance` WHERE 1=1;
DELETE FROM `teacher_absences` WHERE 1=1;
DELETE FROM `leaves` WHERE 1=1;
DELETE FROM `schedules` WHERE 1=1;
DELETE FROM `teacher_assignments` WHERE 1=1;
DELETE FROM `users` WHERE `username` <> 'admin';
DELETE FROM `students` WHERE 1=1;
DELETE FROM `teachers` WHERE 1=1;

ALTER TABLE `teachers` AUTO_INCREMENT = 1;
ALTER TABLE `students` AUTO_INCREMENT = 1;

SET @demo_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

INSERT INTO `teachers` (`id`, `full_name`, `phone`, `specialization`, `status`) VALUES
(1, 'Teacher A (Demo)', '00000000001', 'Subject A', 'active'),
(2, 'Teacher B (Demo)', '00000000002', 'Subject B', 'active');

INSERT INTO `users` (`username`, `password_hash`, `full_name`, `role`, `status`, `plain_password`) VALUES
('assistant.demo', @demo_hash, 'Assistant Demo', 'assistant', 'active', NULL);

INSERT INTO `users` (`username`, `password_hash`, `full_name`, `role`, `status`, `teacher_id`, `plain_password`) VALUES
('teacher.demo1', @demo_hash, 'Teacher A (Demo)', 'teacher', 'active', 1, NULL),
('teacher.demo2', @demo_hash, 'Teacher B (Demo)', 'teacher', 'active', 2, NULL);

UPDATE `teachers` t JOIN `users` u ON u.`teacher_id` = t.`id` SET t.`user_id` = u.`id` WHERE u.`username` IN ('teacher.demo1', 'teacher.demo2');

INSERT INTO `students` (`id`, `full_name`, `class_id`, `section`, `birth_date`, `gender`, `parent_name`, `parent_phone`) VALUES
(1, 'Student A (Demo)', 1, 'أ', '2017-01-01', 'male', 'Parent A (Demo)', '00000000000'),
(2, 'Student B (Demo)', 1, 'أ', '2017-01-02', 'male', 'Parent B (Demo)', '00000000000');

INSERT INTO `users` (`username`, `password_hash`, `full_name`, `role`, `status`, `student_id`, `plain_password`) VALUES
('student.demo0001', @demo_hash, 'Student A (Demo)', 'student', 'active', 1, NULL),
('student.demo0002', @demo_hash, 'Student B (Demo)', 'student', 'active', 2, NULL);

UPDATE `students` s JOIN `users` u ON u.`student_id` = s.`id` SET s.`user_id` = u.`id` WHERE u.`username` LIKE 'student.demo%';

INSERT INTO `teacher_assignments` (`teacher_id`, `subject_name`, `class_id`, `section`, `can_enter_grades`, `is_active`)
SELECT u.`id`, 'Demo Subject', 1, 'أ', 1, 1 FROM `users` u WHERE u.`username` = 'teacher.demo1' LIMIT 1;

SET FOREIGN_KEY_CHECKS = 1;

-- Demo logins (change immediately): teacher.demo1 / teacher.demo2 / student.demo0001 / assistant.demo
-- Password for @demo_hash in Laravel test fixtures is the string "password" — use only in local dev.
