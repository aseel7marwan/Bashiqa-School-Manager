<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/ActivityLog.php';

requireLogin();

if (!isAdmin()) {
    alert('ليس لديك صلاحية لهذا الإجراء', 'error');
    redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/student_users.php');
}

if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    alert('خطأ في التحقق الأمني', 'error');
    redirect('/student_users.php');
}

$action = $_POST['action'] ?? '';
$userModel = new User();
$studentModel = new Student();

switch ($action) {
    case 'create_account':
        // Create user account for existing student
        $studentId = (int)($_POST['student_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validate inputs
        $errors = [];
        
        if (empty($username)) {
            $errors[] = 'اسم المستخدم مطلوب';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = 'اسم المستخدم يجب أن يحتوي على أحرف إنجليزية وأرقام فقط';
        } elseif (strlen($username) < 3) {
            $errors[] = 'اسم المستخدم يجب أن يكون 3 أحرف على الأقل';
        }
        
        if (empty($password)) {
            $errors[] = 'كلمة المرور مطلوبة';
        } elseif (strlen($password) < 4) {
            $errors[] = 'كلمة المرور يجب أن تكون 4 أحرف على الأقل';
        }
        
        $student = $studentModel->findById($studentId);
        if (!$student) {
            $errors[] = 'التلميذ غير موجود';
        }
        
        // Check if student already has an account
        if ($student && !empty($student['user_id'])) {
            $errors[] = 'هذا التلميذ لديه حساب بالفعل';
        }
        
        // Check if username exists
        if ($userModel->findByUsernameIncludingInactive($username)) {
            $errors[] = 'اسم المستخدم موجود مسبقاً، اختر اسماً آخر';
        }
        
        if (!empty($errors)) {
            alert(implode('<br>', $errors), 'error');
            redirect('/students.php');
        }
        
        $data = [
            'username' => $username,
            'full_name' => $student['full_name'],
            'password' => $password,
            'role' => 'student',
            'plain_password' => $password // حفظ كلمة المرور الأصلية
        ];
        
        $userId = $userModel->createStudentAccount($data);
        
        if ($userId) {
            // Link student to user
            $studentModel->linkToUser($studentId, $userId);
            
            // تسجيل العملية
            try {
                $className = isset(CLASSES[$student['class_id']]) ? CLASSES[$student['class_id']] : '';
                logActivity('إنشاء حساب تلميذ', 'add', 'student_account', $userId, $student['full_name'],
                    "اسم المستخدم: $username - الصف: $className");
            } catch (Exception $e) {}
            
            // حفظ بيانات الحساب الجديد في Session لعرض البطاقة
            $studentClassId = $student['class_id'] ?? 0;
            $_SESSION['new_account'] = [
                'username' => $username,
                'password' => $password,
                'full_name' => $student['full_name'],
                'class' => isset(CLASSES[$studentClassId]) ? CLASSES[$studentClassId] : '',
                'section' => $student['section'] ?? ''
            ];
            
            alert("✅ تم إنشاء حساب التلميذ بنجاح!", 'success');
            redirect('/student_users.php?show_card=1');
        } else {
            alert('حدث خطأ أثناء إنشاء الحساب', 'error');
            redirect('/students.php');
        }
        break;
    
    // ═══════════════════════════════════════════════════════════════
    // 🔄 إنشاء حساب سريع (تلقائي) - الاسم الأول + رقم عشوائي
    // ═══════════════════════════════════════════════════════════════
    case 'quick_create':
        $studentId = (int)($_POST['student_id'] ?? 0);
        
        $student = $studentModel->findById($studentId);
        if (!$student) {
            alert('التلميذ غير موجود', 'error');
            redirect('/students.php');
        }
        
        // التحقق من عدم وجود حساب
        if (!empty($student['user_id'])) {
            alert('هذا التلميذ لديه حساب بالفعل', 'warning');
            redirect('/students.php');
        }
        
        // استخراج الاسم الأول وتحويله للإنجليزي باستخدام الدالة المركزية
        $nameParts = explode(' ', trim($student['full_name']));
        $firstName = arabicToEnglish($nameParts[0]);
        
        // توليد رقم عشوائي 4 أرقام
        $randomNum = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $username = $firstName . $randomNum;
        
        // توليد رمز دخول عشوائي 4 أحرف (سهلة الاستخدام)
        $chars = 'abcdefghjkmnpqrstuvwxyz23456789'; // حروف وأرقام سهلة القراءة
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
        
        // إنشاء الحساب
        $data = [
            'username' => $username,
            'full_name' => $student['full_name'],
            'password' => $password,
            'role' => 'student',
            'plain_password' => $password
        ];
        
        $userId = $userModel->createStudentAccount($data);
        
        if ($userId) {
            // ربط الحساب بالطالب
            $studentModel->linkToUser($studentId, $userId);
            
            // تسجيل العملية
            try {
                $className = isset(CLASSES[$student['class_id']]) ? CLASSES[$student['class_id']] : '';
                logActivity('إنشاء حساب تلميذ (سريع)', 'add', 'student_account', $userId, $student['full_name'],
                    "اسم المستخدم: $username - الصف: $className");
            } catch (Exception $e) {}
            
            // حفظ بيانات الحساب الجديد في Session لعرض البطاقة
            $_SESSION['new_account'] = [
                'username' => $username,
                'password' => $password,
                'full_name' => $student['full_name'],
                'class' => isset(CLASSES[$student['class_id']]) ? CLASSES[$student['class_id']] : '',
                'section' => $student['section'] ?? ''
            ];
            
            alert('✅ تم إنشاء حساب التلميذ بنجاح!<br><br>' .
                  '<strong>بيانات الدخول:</strong><br>' .
                  '👤 اسم المستخدم: <code>' . $username . '</code><br>' .
                  '🔑 كلمة المرور: <code>' . $password . '</code><br><br>' .
                  '⚠️ احفظ هذه البيانات!', 'success');
            redirect('/students.php');
        } else {
            alert('حدث خطأ أثناء إنشاء الحساب', 'error');
            redirect('/students.php');
        }
        break;
    
    // ═══════════════════════════════════════════════════════════════
    // 🚀 إنشاء حساب سريع عبر AJAX - يُرجع JSON
    // ═══════════════════════════════════════════════════════════════
    case 'quick_create_ajax':
        header('Content-Type: application/json; charset=utf-8');
        
        $studentId = (int)($_POST['student_id'] ?? 0);
        
        $student = $studentModel->findById($studentId);
        if (!$student) {
            echo json_encode(['success' => false, 'error' => 'التلميذ غير موجود']);
            exit;
        }
        
        // التحقق من عدم وجود حساب
        if (!empty($student['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'هذا التلميذ لديه حساب بالفعل']);
            exit;
        }
        
        // استخراج الاسم الأول وتحويله للإنجليزي باستخدام الدالة المركزية
        $nameParts = explode(' ', trim($student['full_name']));
        $firstName = arabicToEnglish($nameParts[0]);
        
        // توليد رقم عشوائي 4 أرقام
        $randomNum = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $username = $firstName . $randomNum;
        
        // توليد رمز دخول عشوائي 4 أحرف (سهلة الاستخدام)
        $chars = 'abcdefghjkmnpqrstuvwxyz23456789'; // حروف وأرقام سهلة القراءة
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
        
        // إنشاء الحساب
        $data = [
            'username' => $username,
            'full_name' => $student['full_name'],
            'password' => $password,
            'role' => 'student',
            'plain_password' => $password
        ];
        
        $userId = $userModel->createStudentAccount($data);
        
        if ($userId) {
            // ربط الحساب بالطالب
            $studentModel->linkToUser($studentId, $userId);
            
            // تسجيل العملية
            try {
                $className = isset(CLASSES[$student['class_id']]) ? CLASSES[$student['class_id']] : '';
                logActivity('إنشاء حساب تلميذ (سريع)', 'add', 'student_account', $userId, $student['full_name'],
                    "اسم المستخدم: $username - الصف: $className");
            } catch (Exception $e) {}
            
            echo json_encode([
                'success' => true,
                'username' => $username,
                'password' => $password,
                'full_name' => $student['full_name'],
                'class' => isset(CLASSES[$student['class_id']]) ? CLASSES[$student['class_id']] : '',
                'section' => $student['section'] ?? ''
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'حدث خطأ أثناء إنشاء الحساب']);
        }
        exit;
        
    case 'edit':
        $id = (int)($_POST['id'] ?? 0);
        
        $user = $userModel->findById($id);
        if (!$user || $user['role'] !== 'student') {
            alert('المستخدم غير موجود أو ليس تلميذاً', 'error');
            redirect('/student_users.php');
        }
        
        $data = [
            'full_name' => sanitize($_POST['full_name'] ?? ''),
            'status' => $_POST['status'] ?? 'active'
        ];
        
        $newPassword = null;
        if (!empty($_POST['password'])) {
            $data['password'] = $_POST['password'];
            $newPassword = $_POST['password'];
        }
        
        if ($userModel->update($id, $data)) {
            // Also update student's full_name if linked
            $student = $studentModel->findByUserId($id);
            if ($student) {
                $studentModel->update($student['id'], ['full_name' => $data['full_name']]);
            }
            
            // إذا تم تغيير كلمة المرور، تحديث plain_password وعرض بطاقة الدخول
            if ($newPassword) {
                $userModel->updatePlainPassword($id, $newPassword);
                
                // حفظ بيانات الحساب في Session لعرض بطاقة الدخول
                $_SESSION['new_account'] = [
                    'username' => $user['username'],
                    'password' => $newPassword,
                    'full_name' => $data['full_name'],
                    'class' => $student ? (isset(CLASSES[$student['class_id']]) ? CLASSES[$student['class_id']] : '') : '',
                    'section' => $student['section'] ?? ''
                ];
                
                alert('✅ تم تحديث حساب التلميذ وكلمة المرور بنجاح', 'success');
                redirect('/student_users.php?show_card=1');
            } else {
                alert('تم تحديث حساب التلميذ بنجاح', 'success');
            }
        } else {
            alert('حدث خطأ أثناء التحديث', 'error');
        }
        redirect('/student_users.php');
        break;
        
    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        
        $user = $userModel->findById($id);
        if (!$user || $user['role'] !== 'student') {
            alert('المستخدم غير موجود أو ليس تلميذاً', 'error');
            redirect('/student_users.php');
        }
        
        // Unlink student from user before deleting
        $student = $studentModel->findByUserId($id);
        if ($student) {
            $studentModel->unlinkFromUser($student['id']);
        }
        
        // Delete user permanently from database
        $studentName = $user['full_name'] ?? 'غير معروف';
        if ($userModel->permanentDelete($id)) {
            try {
                logActivity('حذف حساب تلميذ', 'delete', 'student_account', $id, $studentName);
            } catch (Exception $e) {}
            alert('✅ تم حذف حساب التلميذ بنجاح', 'success');
        } else {
            alert('حدث خطأ أثناء الحذف', 'error');
        }
        redirect('/student_users.php');
        break;
        
    case 'reset_password':
        $id = (int)($_POST['id'] ?? 0);
        
        $user = $userModel->findById($id);
        if (!$user || $user['role'] !== 'student') {
            alert('المستخدم غير موجود أو ليس تلميذاً', 'error');
            redirect('/student_users.php');
        }
        
        $defaultPassword = '123456';
        
        if ($userModel->update($id, ['password' => $defaultPassword])) {
            // تحديث كلمة المرور الأصلية أيضاً
            $userModel->updatePlainPassword($id, $defaultPassword);
            
            try {
                logActivity('إعادة تعيين كلمة مرور تلميذ', 'edit', 'student_account', $id, $user['full_name']);
            } catch (Exception $e) {}
            
            // حفظ بيانات الحساب في Session لعرض بطاقة الدخول
            $student = $studentModel->findByUserId($id);
            $_SESSION['new_account'] = [
                'username' => $user['username'],
                'password' => $defaultPassword,
                'full_name' => $user['full_name'],
                'class' => $student ? (isset(CLASSES[$student['class_id']]) ? CLASSES[$student['class_id']] : '') : '',
                'section' => $student['section'] ?? ''
            ];
            
            alert("✅ تم إعادة تعيين كلمة المرور إلى: {$defaultPassword}", 'success');
            redirect('/student_users.php?show_card=1');
        } else {
            alert('حدث خطأ أثناء إعادة تعيين كلمة المرور', 'error');
        }
        redirect('/student_users.php');
        break;
        
    case 'toggle_status':
        $id = (int)($_POST['id'] ?? 0);
        $user = $userModel->findById($id);
        
        if (!$user || $user['role'] !== 'student') {
            alert('التلميذ غير موجود', 'error');
            redirect('/student_users.php');
        }
        
        $newStatus = ($user['status'] === 'active') ? 'inactive' : 'active';
        
        if ($userModel->update($id, ['status' => $newStatus])) {
            try {
                $statusText = $newStatus === 'active' ? 'تفعيل' : 'تعطيل';
                logActivity("$statusText حساب تلميذ", 'edit', 'student_account', $id, $user['full_name']);
            } catch (Exception $e) {}
            
            if ($newStatus === 'active') {
                alert('✅ تم تفعيل الحساب', 'success');
            } else {
                alert('🚫 تم تعطيل الحساب', 'warning');
            }
        } else {
            alert('حدث خطأ', 'error');
        }
        redirect('/student_users.php');
        break;
        
    default:
        redirect('/student_users.php');
}
