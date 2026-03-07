<?php
/**
 * معالج التلاميذ - Student Handler
 */

// ضبط الترميز
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/permissions.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/ActivityLog.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/students.php');
}

if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    alert('خطأ في التحقق الأمني', 'error');
    redirect('/students.php');
}

$action = $_POST['action'] ?? '';
$studentModel = new Student();

switch ($action) {
    case 'add':
        if (!hasPermission('manage_students')) {
            alert('ليس لديك صلاحية لإضافة تلاميذ', 'error');
            redirect('/students.php');
        }
        
        $data = [
            'full_name' => sanitize($_POST['full_name'] ?? ''),
            'class_id' => (int)($_POST['class_id'] ?? 0),
            'section' => sanitize($_POST['section'] ?? ''),
            'birth_date' => $_POST['birth_date'] ?? null,
            'gender' => $_POST['gender'] ?? 'male',
            'parent_name' => sanitize($_POST['parent_name'] ?? ''),
            'parent_phone' => sanitize($_POST['parent_phone'] ?? ''),
            'address' => sanitize($_POST['address'] ?? ''),
            'enrollment_date' => !empty($_POST['enrollment_date']) ? $_POST['enrollment_date'] : null,
            // الحقول الجديدة
            'province' => sanitize($_POST['province'] ?? ''),
            'city_village' => sanitize($_POST['city_village'] ?? ''),
            'birth_place' => sanitize($_POST['birth_place'] ?? ''),
            'sibling_order' => !empty($_POST['sibling_order']) ? (int)$_POST['sibling_order'] : null,
            'guardian_job' => sanitize($_POST['guardian_job'] ?? ''),
            'guardian_relation' => sanitize($_POST['guardian_relation'] ?? ''),
            'father_alive' => $_POST['father_alive'] ?? 'نعم',
            'mother_alive' => $_POST['mother_alive'] ?? 'نعم',
            'father_education' => sanitize($_POST['father_education'] ?? ''),
            'mother_education' => sanitize($_POST['mother_education'] ?? ''),
            'father_age_at_registration' => !empty($_POST['father_age_at_registration']) ? (int)$_POST['father_age_at_registration'] : null,
            'mother_age_at_registration' => !empty($_POST['mother_age_at_registration']) ? (int)$_POST['mother_age_at_registration'] : null,
            'parents_kinship' => sanitize($_POST['parents_kinship'] ?? ''),
            'mother_name' => sanitize($_POST['mother_name'] ?? ''),
            'nationality_number' => sanitize($_POST['nationality_number'] ?? ''),
            'previous_schools' => $_POST['previous_schools'] ?? '',
            'social_status' => $_POST['social_status'] ?? '',
            'health_status' => $_POST['health_status'] ?? '',
            'academic_records' => $_POST['academic_records'] ?? '',
            'attendance_records' => $_POST['attendance_records'] ?? '',
            'registration_number' => sanitize($_POST['registration_number'] ?? ''),
            'data_changes' => sanitize($_POST['data_changes'] ?? ''),
            'notes' => sanitize($_POST['notes'] ?? '')
        ];
        
        $errors = [];
        if (empty($data['full_name'])) $errors[] = '⚠️ اسم التلميذ مطلوب';
        if (empty($data['class_id']) || !array_key_exists($data['class_id'], CLASSES)) $errors[] = '⚠️ يجب اختيار الصف';
        if (empty($data['section']) || !in_array($data['section'], SECTIONS)) $errors[] = '⚠️ يجب اختيار الشعبة';
        
        if (!empty($errors)) {
            alert(implode('<br>', $errors), 'error');
            redirect('/students.php?action=add');
        }
        
        $studentId = $studentModel->create($data);
        
        if ($studentId) {
            // رفع الصورة الرئيسية
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $result = uploadPhoto($_FILES['photo'], $studentId);
                if ($result['success']) {
                    $studentModel->update($studentId, ['photo' => $result['filename']]);
                }
            }
            // رفع صور المراحل
            $photoFields = ['photo_primary', 'photo_intermediate', 'photo_secondary'];
            foreach ($photoFields as $field) {
                if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                    $result = uploadPhoto($_FILES[$field], $studentId, $field);
                    if ($result['success']) {
                        $studentModel->update($studentId, [$field => $result['filename']]);
                    }
                }
            }
        }
        
        if ($studentId) {
            // ═══════════════════════════════════════════════════════════════
            // 🔄 إنشاء حساب المستخدم تلقائياً
            // اسم المستخدم = الاسم الأول + رقم عشوائي (4 أرقام)
            // كلمة المرور = رقم عشوائي (4 أرقام)
            // ═══════════════════════════════════════════════════════════════
            require_once __DIR__ . '/../models/User.php';
            $userModel = new User();
            
            // استخراج الاسم الأول (الكلمة الأولى) وتحويله للإنجليزي باستخدام الدالة المركزية
            $nameParts = explode(' ', trim($data['full_name']));
            $firstName = arabicToEnglish($nameParts[0]);
            
            // توليد رقم عشوائي 4 أرقام
            $randomNum = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $username = $firstName . $randomNum;
            
            // توليد رمز دخول عشوائي 4 أحرف (سهلة الاستخدام)
            $chars = 'abcdefghjkmnpqrstuvwxyz23456789'; // حروف وأرقام سهلة القراءة (بدون l,i,o,0,1)
            $password = '';
            for ($i = 0; $i < 4; $i++) {
                $password .= $chars[rand(0, strlen($chars) - 1)];
            }
            
            // التأكد من عدم تكرار اسم المستخدم
            $attempts = 0;
            while ($userModel->findByUsernameIncludingInactive($username) && $attempts < 10) {
                $randomNum = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                $username = $firstName . $randomNum;
                $attempts++;
            }
            
            // إنشاء حساب المستخدم
            $userData = [
                'username' => $username,
                'password' => $password,
                'full_name' => $data['full_name'],
                'role' => 'student'
            ];
            
            $userId = $userModel->createAndGetId($userData);
            
            if ($userId) {
                // ربط الحساب بالطالب - استخدام SQL مباشر للتأكد من نجاح الربط
                require_once __DIR__ . '/../config/database.php';
                $conn = getConnection();
                
                // 1. ربط students.user_id -> users.id
                try {
                    $stmt = $conn->prepare("UPDATE students SET user_id = ? WHERE id = ?");
                    $stmt->execute([$userId, $studentId]);
                } catch (Exception $e) { error_log('student_handler update user_id: ' . $e->getMessage()); }
                
                // 2. ربط users.student_id -> students.id (ربط مزدوج للحماية)
                try {
                    // إضافة العمود إذا لم يكن موجوداً
                    $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS student_id INT NULL DEFAULT NULL");
                } catch (Exception $e) { /* Column may already exist */ }
                
                try {
                    $stmt = $conn->prepare("UPDATE users SET student_id = ? WHERE id = ?");
                    $stmt->execute([$studentId, $userId]);
                } catch (Exception $e) { error_log('student_handler update student_id: ' . $e->getMessage()); }
                
                // تسجيل العملية
                try {
                    $classId = $data['class_id'];
                    $className = isset(CLASSES[$classId]) ? CLASSES[$classId] : $classId;
                    $newData = [
                        'الاسم' => $data['full_name'],
                        'الصف' => $className,
                        'الشعبة' => $data['section'],
                        'اسم المستخدم' => $username
                    ];
                    logActivity('إضافة طالب جديد مع حساب', 'add', 'student', $studentId, $data['full_name'], 
                        'الصف: ' . $className . ' - الشعبة: ' . $data['section'] . ' - المستخدم: ' . $username,
                        null, $newData);
                } catch (Exception $e) {}
                
                // حفظ بيانات الحساب في Session لعرض البطاقة تلقائياً
                $_SESSION['new_account'] = [
                    'username' => $username,
                    'password' => $password,
                    'full_name' => $data['full_name'],
                    'class' => isset(CLASSES[$data['class_id']]) ? CLASSES[$data['class_id']] : '',
                    'section' => $data['section'],
                    'class_id' => $data['class_id'],
                    'student_id' => $studentId
                ];
                
                alert('✅ تم إضافة التلميذ وإنشاء حسابه بنجاح!<br>📍 الصف: ' . CLASSES[$data['class_id']] . ' - شعبة ' . $data['section'], 'success');
                // التوجيه لصفحة الحسابات مع فتح البطاقة تلقائياً
                redirect('/student_users.php?show_card=1');
            } else {
                // فشل إنشاء الحساب - لكن الطالب تم إضافته
                try {
                    $classId = $data['class_id'];
                    $className = isset(CLASSES[$classId]) ? CLASSES[$classId] : $classId;
                    logActivity('إضافة طالب جديد', 'add', 'student', $studentId, $data['full_name'], 
                        'الصف: ' . $className . ' - الشعبة: ' . $data['section']);
                } catch (Exception $e) {}
                
                alert('✅ تم إضافة التلميذ بنجاح!<br>⚠️ لم يتم إنشاء الحساب تلقائياً. يمكنك إنشائه لاحقاً.', 'warning');
                redirect('/students.php');
            }
        } else {
            alert('حدث خطأ أثناء إضافة التلميذ', 'error');
            redirect('/students.php');
        }
        break;
        
    // ═══════════════════════════════════════════════════════════════
    // 🔄 تحديث الصف والشعبة من التسلسل الإجباري
    // ═══════════════════════════════════════════════════════════════
    case 'update_class':
        if (!hasPermission('manage_students')) {
            alert('ليس لديك صلاحية لتعديل التلاميذ', 'error');
            redirect('/students.php');
        }
        
        $id = (int)($_POST['id'] ?? 0);
        $classId = (int)($_POST['class_id'] ?? 0);
        $section = sanitize($_POST['section'] ?? '');
        $redirectTo = $_POST['redirect_to'] ?? '/students.php';
        
        $student = $studentModel->findById($id);
        if (!$student) {
            alert('التلميذ غير موجود', 'error');
            redirect('/students.php');
        }
        
        // التحقق من صحة البيانات
        if (!array_key_exists($classId, CLASSES)) {
            alert('الصف غير صحيح', 'error');
            redirect($redirectTo);
        }
        
        if (!in_array($section, SECTIONS)) {
            alert('الشعبة غير صحيحة', 'error');
            redirect($redirectTo);
        }
        
        // تحديث الصف والشعبة
        if ($studentModel->update($id, ['class_id' => $classId, 'section' => $section])) {
            try {
                $className = CLASSES[$classId] ?? $classId;
                logActivity('تحديث صف تلميذ', 'edit', 'student', $id, $student['full_name'],
                    'الصف الجديد: ' . $className . ' - الشعبة: ' . $section);
            } catch (Exception $e) {}
            
            alert('✅ تم تحديد الصف والشعبة بنجاح!', 'success');
        } else {
            alert('حدث خطأ أثناء تحديث البيانات', 'error');
        }
        redirect($redirectTo);
        break;
        
    case 'edit':
        if (!hasPermission('manage_students')) {
            alert('ليس لديك صلاحية لتعديل التلاميذ', 'error');
            redirect('/students.php');
        }
        
        $id = (int)($_POST['id'] ?? 0);
        
        // الحصول على البيانات القديمة قبل التعديل
        $oldStudent = $studentModel->getById($id);
        $oldClassId = $oldStudent['class_id'] ?? 0;
        $oldData = $oldStudent ? [
            'الاسم' => $oldStudent['full_name'] ?? '',
            'الصف' => isset(CLASSES[$oldClassId]) ? CLASSES[$oldClassId] : $oldClassId,
            'الشعبة' => $oldStudent['section'] ?? '',
            'ولي الأمر' => $oldStudent['parent_name'] ?? ''
        ] : null;
        $data = [
            'full_name' => sanitize($_POST['full_name'] ?? ''),
            'class_id' => (int)($_POST['class_id'] ?? 0),
            'section' => sanitize($_POST['section'] ?? ''),
            'birth_date' => $_POST['birth_date'] ?? null,
            'gender' => $_POST['gender'] ?? 'male',
            'parent_name' => sanitize($_POST['parent_name'] ?? ''),
            'parent_phone' => sanitize($_POST['parent_phone'] ?? ''),
            'address' => sanitize($_POST['address'] ?? ''),
            'enrollment_date' => !empty($_POST['enrollment_date']) ? $_POST['enrollment_date'] : null,
            // الحقول الجديدة
            'province' => sanitize($_POST['province'] ?? ''),
            'city_village' => sanitize($_POST['city_village'] ?? ''),
            'birth_place' => sanitize($_POST['birth_place'] ?? ''),
            'sibling_order' => !empty($_POST['sibling_order']) ? (int)$_POST['sibling_order'] : null,
            'guardian_job' => sanitize($_POST['guardian_job'] ?? ''),
            'guardian_relation' => sanitize($_POST['guardian_relation'] ?? ''),
            'father_alive' => $_POST['father_alive'] ?? 'نعم',
            'mother_alive' => $_POST['mother_alive'] ?? 'نعم',
            'father_education' => sanitize($_POST['father_education'] ?? ''),
            'mother_education' => sanitize($_POST['mother_education'] ?? ''),
            'father_age_at_registration' => !empty($_POST['father_age_at_registration']) ? (int)$_POST['father_age_at_registration'] : null,
            'mother_age_at_registration' => !empty($_POST['mother_age_at_registration']) ? (int)$_POST['mother_age_at_registration'] : null,
            'parents_kinship' => sanitize($_POST['parents_kinship'] ?? ''),
            'mother_name' => sanitize($_POST['mother_name'] ?? ''),
            'nationality_number' => sanitize($_POST['nationality_number'] ?? ''),
            'previous_schools' => $_POST['previous_schools'] ?? '',
            'social_status' => $_POST['social_status'] ?? '',
            'health_status' => $_POST['health_status'] ?? '',
            'academic_records' => $_POST['academic_records'] ?? '',
            'attendance_records' => $_POST['attendance_records'] ?? '',
            'registration_number' => sanitize($_POST['registration_number'] ?? ''),
            'data_changes' => sanitize($_POST['data_changes'] ?? ''),
            'notes' => sanitize($_POST['notes'] ?? '')
        ];
        
        // رفع الصورة الرئيسية
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $result = uploadPhoto($_FILES['photo'], $id);
            if ($result['success']) {
                $data['photo'] = $result['filename'];
            }
        }
        
        // رفع صور المراحل
        $photoFields = ['photo_primary', 'photo_intermediate', 'photo_secondary'];
        foreach ($photoFields as $field) {
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                $result = uploadPhoto($_FILES[$field], $id, $field);
                if ($result['success']) {
                    $data[$field] = $result['filename'];
                }
            }
        }
        
        if ($studentModel->update($id, $data)) {
            // ═══════════════════════════════════════════════════════════════
            // 🔄 تحديث اسم الطالب في جدول users إذا كان لديه حساب
            // ═══════════════════════════════════════════════════════════════
            $updatedStudent = $studentModel->findById($id);
            if ($updatedStudent && !empty($updatedStudent['user_id'])) {
                require_once __DIR__ . '/../models/User.php';
                $userModel = new User();
                $userModel->update($updatedStudent['user_id'], ['full_name' => $data['full_name']]);
            }
            
            // تسجيل العملية مع البيانات قبل وبعد
            try {
                $classId = $data['class_id'];
                $newData = [
                    'الاسم' => $data['full_name'],
                    'الصف' => isset(CLASSES[$classId]) ? CLASSES[$classId] : $classId,
                    'الشعبة' => $data['section'],
                    'ولي الأمر' => $data['parent_name'] ?? ''
                ];
                $className = isset(CLASSES[$classId]) ? CLASSES[$classId] : $classId;
                logActivity('تعديل بيانات طالب', 'edit', 'student', $id, $data['full_name'],
                    'الصف: ' . $className . ' - الشعبة: ' . $data['section'],
                    $oldData, $newData);
            } catch (Exception $e) {
                // تجاهل أخطاء التسجيل
            }
            alert('تم تحديث بيانات التلميذ بنجاح', 'success');
        } else {
            alert('حدث خطأ أثناء تحديث البيانات', 'error');
        }
        redirect('/students.php');
        break;
        
    case 'delete':
        if (!hasPermission('manage_students')) {
            alert('ليس لديك صلاحية لحذف التلاميذ', 'error');
            redirect('/students.php');
        }
        
        $id = (int)($_POST['id'] ?? 0);
        // الحصول على بيانات الطالب قبل الحذف
        $student = $studentModel->getById($id);
        
        if (!$student) {
            alert('الطالب غير موجود', 'error');
            redirect('/students.php');
        }
        
        $studentName = $student['full_name'] ?? 'غير معروف';
        $studentClassId = $student['class_id'] ?? 0;
        $userId = $student['user_id'] ?? null;
        
        $deletedData = [
            'الاسم' => $student['full_name'] ?? '',
            'الصف' => isset(CLASSES[$studentClassId]) ? CLASSES[$studentClassId] : $studentClassId,
            'الشعبة' => $student['section'] ?? '',
            'ولي الأمر' => $student['parent_name'] ?? '',
            'لديه حساب' => $userId ? 'نعم' : 'لا'
        ];
        
        // ═══════════════════════════════════════════════════════════════
        // 🗑️ الحذف المتسلسل (Cascading Delete)
        // ═══════════════════════════════════════════════════════════════
        $conn = getConnection();
        
        // ⚡ حذف حساب المستخدم أولاً (خارج Transaction لضمان التنفيذ)
        $userDeleted = false;
        $userToDelete = null;
        
        // البحث عن الحساب المرتبط
        if ($userId && $userId > 0) {
            $userToDelete = $userId;
        }
        
        // طريقة إضافية 1: البحث عبر student_id في جدول users
        if (!$userToDelete) {
            try {
                $stmt = $conn->prepare("SELECT id FROM users WHERE student_id = ? LIMIT 1");
                $stmt->execute([$id]);
                $found = $stmt->fetch();
                if ($found && !empty($found['id'])) {
                    $userToDelete = $found['id'];
                }
            } catch (Exception $e) { /* student_id column may not exist */ }
        }
        
        // طريقة إضافية 2: البحث عبر الاسم (مع الدور student)
        if (!$userToDelete && !empty($studentName)) {
            try {
                $stmt = $conn->prepare("SELECT id FROM users WHERE full_name = ? AND role = 'student' LIMIT 1");
                $stmt->execute([$studentName]);
                $found = $stmt->fetch();
                if ($found && !empty($found['id'])) {
                    $userToDelete = $found['id'];
                }
            } catch (Exception $e) {}
        }
        
        // طريقة إضافية 3: البحث عبر الاسم بدون تحديد الدور (للحالات الاستثنائية)
        if (!$userToDelete && !empty($studentName)) {
            try {
                $stmt = $conn->prepare("SELECT id FROM users WHERE full_name = ? AND role IN ('student', 'teacher') LIMIT 1");
                $stmt->execute([$studentName]);
                $found = $stmt->fetch();
                if ($found && !empty($found['id'])) {
                    $userToDelete = $found['id'];
                }
            } catch (Exception $e) {}
        }
        
        // طريقة إضافية 4: البحث الجزئي بالاسم (إذا كان الاسم يحتوي على اختلافات بسيطة)
        if (!$userToDelete && !empty($studentName)) {
            try {
                $namePart = explode(' ', trim($studentName))[0]; // الاسم الأول فقط
                $stmt = $conn->prepare("SELECT id FROM users WHERE full_name LIKE ? AND role = 'student' LIMIT 1");
                $stmt->execute([$namePart . '%']);
                $found = $stmt->fetch();
                if ($found && !empty($found['id'])) {
                    $userToDelete = $found['id'];
                }
            } catch (Exception $e) {}
        }
        
        // حذف الحساب مباشرة (خارج Transaction)
        $deleteLog = [];
        if ($userToDelete) {
            try {
                $deleteLog[] = "محاولة حذف الحساب ID: {$userToDelete}";
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userToDelete]);
                $affected = $stmt->rowCount();
                $deleteLog[] = "الصفوف المحذوفة: {$affected}";
                $userDeleted = ($affected > 0);
            } catch (Exception $e) {
                $deleteLog[] = "خطأ: " . $e->getMessage();
            }
        } else {
            $deleteLog[] = "لم يتم العثور على حساب مرتبط";
        }
        
        try {
            $conn->beginTransaction();
            
            // 1. حذف سجلات الحضور المرتبطة (إن وجد الجدول)
            try {
                $stmt = $conn->prepare("DELETE FROM attendance WHERE student_id = ?");
                $stmt->execute([$id]);
            } catch (Exception $e) { /* attendance table may not exist */ }
            
            // 2. حذف الدرجات المرتبطة (إن وجد الجدول)
            try {
                $stmt = $conn->prepare("DELETE FROM grades WHERE student_id = ?");
                $stmt->execute([$id]);
            } catch (Exception $e) { /* grades table may not exist */ }
            
            // 2.5. حذف الدرجات الشهرية المرتبطة (للصفوف 5 و 6)
            try {
                $stmt = $conn->prepare("DELETE FROM monthly_grades WHERE student_id = ?");
                $stmt->execute([$id]);
            } catch (Exception $e) { /* monthly_grades table may not exist */ }
            
            // 3. حذف الإجازات المرتبطة (جدول leaves لا يحتوي على FOREIGN KEY)
            try {
                $stmt = $conn->prepare("DELETE FROM leaves WHERE person_type = 'student' AND person_id = ?");
                $stmt->execute([$id]);
            } catch (Exception $e) { /* leaves table may not exist */ }
            
            // ℹ️ حذف الحساب تم مسبقاً (قبل بداية Transaction)
            
            // 5. حذف سجل الطالب الأساسي
            $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
            $stmt->execute([$id]);
            
            $conn->commit();
            
            // تسجيل العملية
            try {
                $logDetails = $userDeleted ? 'تم حذف الحساب المرتبط أيضاً' : ($userToDelete ? 'فشل حذف الحساب المرتبط' : 'لا يوجد حساب مرتبط');
                logActivity('حذف طالب وحسابه', 'delete', 'student', $id, $studentName, 
                    $logDetails, $deletedData, null);
            } catch (Exception $e) {}
            
            // رسالة النجاح مع توضيح حالة الحساب
            $logInfo = implode(' | ', $deleteLog);
            if ($userDeleted) {
                alert('✅ تم حذف التلميذ وحسابه المرتبط وجميع بياناته بنجاح<br><small style="opacity:0.7">' . $logInfo . '</small>', 'success');
            } elseif ($userToDelete) {
                alert('✅ تم حذف التلميذ وبياناته.<br>⚠️ تعذر حذف الحساب المرتبط تلقائياً.<br><small style="opacity:0.7">' . $logInfo . '</small>', 'warning');
            } else {
                alert('✅ تم حذف التلميذ وجميع بياناته بنجاح<br><small style="opacity:0.7">' . $logInfo . '</small>', 'success');
            }
            
        } catch (Exception $e) {
            $conn->rollBack();
            alert('حدث خطأ أثناء حذف التلميذ: ' . $e->getMessage(), 'error');
        }
        
        redirect('/students.php');
        break;
        
    default:
        redirect('/students.php');
}
