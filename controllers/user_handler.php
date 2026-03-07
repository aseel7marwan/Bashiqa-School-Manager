<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/ActivityLog.php';

// التحقق من الجلسة
if (!isLoggedIn()) {
    redirect('login.php');
}

if (!isAdmin()) {
    $_SESSION['alert'] = ['message' => 'ليس لديك صلاحية', 'type' => 'error'];
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('users.php');
}

// تحقق مرن من CSRF
$csrfValid = true;
if (isset($_POST['csrf_token']) && isset($_SESSION['csrf_token'])) {
    $csrfValid = hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

$action = $_POST['action'] ?? '';
$userModel = new User();

switch ($action) {
    case 'add':
        $data = [
            'username' => trim($_POST['username'] ?? ''),
            'full_name' => sanitize($_POST['full_name'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'role' => $_POST['role'] ?? 'teacher'
        ];
        
        $errors = [];
        if ($error = validateUsername($data['username'])) $errors[] = $error;
        if (empty($data['full_name'])) $errors[] = 'الاسم الكامل مطلوب';
        if ($error = validatePassword($data['password'])) $errors[] = $error;
        if (!in_array($data['role'], ['admin', 'assistant', 'teacher'])) $errors[] = 'الصلاحية غير صحيحة';
        
        if ($userModel->findByUsername($data['username'])) {
            $errors[] = 'اسم المستخدم موجود مسبقاً';
        }
        
        if (!empty($errors)) {
            alert(implode('<br>', $errors), 'error');
            redirect('/users.php?action=add');
        }
        
        if ($userId = $userModel->createAndGetId($data)) {
            // ربط الحساب بسجل المعلم إذا تم تحديده
            $teacherId = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
            if ($teacherId && in_array($data['role'], ['teacher', 'assistant'])) {
                // ربط الحساب بسجل المعلم (في جدول users)
                $userModel->linkToTeacher($userId, $teacherId);
                
                // الربط العكسي: تحديث سجل المعلم (في جدول teachers)
                require_once __DIR__ . '/../models/Teacher.php';
                $teacherModel = new Teacher();
                $teacherModel->linkToUser($teacherId, $userId);
                
                // نقل التعيينات المؤقتة للجدول الدائم
                require_once __DIR__ . '/../models/TeacherAssignment.php';
                $assignmentModel = new TeacherAssignment();
                $assignmentModel->migrateTemporaryAssignments($teacherId, $userId);
            }
            
            try {
                $roleName = ROLES[$data['role']] ?? $data['role'];
                $details = "اسم المستخدم: " . $data['username'];
                $details .= " | الدور: $roleName";
                if ($teacherId) {
                    $details .= " | مرتبط بسجل المعلم #$teacherId";
                }
                logActivity('إنشاء حساب جديد', 'add', 'user', $userId, $data['full_name'], $details);
            } catch (Exception $e) {}
            alert('تم إضافة المستخدم بنجاح', 'success');
        } else {
            alert('حدث خطأ أثناء إضافة المستخدم', 'error');
        }
        redirect('/users.php');
        break;
        
    case 'edit':
        $id = (int)($_POST['id'] ?? 0);
        
        // التحقق من صلاحية التعديل على هذا المستخدم
        if (!canManageUser($id)) {
            alert('ليس لديك صلاحية لتعديل هذا المستخدم', 'error');
            redirect('/users.php');
        }
        
        $data = [
            'full_name' => sanitize($_POST['full_name'] ?? ''),
            'role' => $_POST['role'] ?? 'teacher'
        ];
        
        if (!empty($_POST['password'])) {
            $data['password'] = $_POST['password'];
        }

        
        // الحصول على البيانات القديمة
        $oldUser = $userModel->findById($id);
        
        if ($userModel->update($id, $data)) {
            // ربط/تحديث الحساب بسجل المعلم
            $teacherId = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
            $oldTeacherId = $oldUser['teacher_id'] ?? null;
            
            require_once __DIR__ . '/../models/Teacher.php';
            $teacherModel = new Teacher();
            
            if (in_array($data['role'], ['teacher', 'assistant'])) {
                $userModel->linkToTeacher($id, $teacherId ?: null);
                
                // الربط العكسي: تحديث سجل المعلم الجديد
                if ($teacherId) {
                    $teacherModel->linkToUser($teacherId, $id);
                    
                    // نقل التعيينات المؤقتة إذا كان هذا ربط جديد
                    if ($oldTeacherId != $teacherId) {
                        require_once __DIR__ . '/../models/TeacherAssignment.php';
                        $assignmentModel = new TeacherAssignment();
                        $assignmentModel->migrateTemporaryAssignments($teacherId, $id);
                    }
                }
                
                // إلغاء الربط من السجل القديم إذا تغير
                if ($oldTeacherId && $oldTeacherId != $teacherId) {
                    $teacherModel->unlinkUser($oldTeacherId);
                }
            } else {
                // إذا تغير الدور لمدير، نلغي الربط
                $userModel->linkToTeacher($id, null);
                if ($oldTeacherId) {
                    $teacherModel->unlinkUser($oldTeacherId);
                }
            }
            
            // ═══════════════════════════════════════════════════════════════
            // 🔄 تحديث الاسم في جدول teachers إذا كان المستخدم مرتبطاً بمعلم
            // ═══════════════════════════════════════════════════════════════
            $linkedTeacherId = $teacherId ?: $oldTeacherId;
            if ($linkedTeacherId) {
                $teacherModel->update($linkedTeacherId, ['full_name' => $data['full_name']]);
            }
            
            // تحديث اسم المستخدم في الجلسة إذا كان هو المستخدم الحالي
            updateSessionUserName($id, $data['full_name']);
            try {
                $changes = [];
                if ($oldUser && $oldUser['role'] != $data['role']) {
                    $oldRole = ROLES[$oldUser['role']] ?? $oldUser['role'];
                    $newRole = ROLES[$data['role']] ?? $data['role'];
                    $changes[] = "الدور: $oldRole ← $newRole";
                }
                if (!empty($_POST['password'])) {
                    $changes[] = "تم تغيير كلمة المرور";
                }

                if ($teacherId && ($oldUser['teacher_id'] ?? null) != $teacherId) {
                    $changes[] = "تم ربطه بسجل المعلم #$teacherId";
                } elseif (!$teacherId && ($oldUser['teacher_id'] ?? null)) {
                    $changes[] = "تم إلغاء الربط بسجل المعلم";
                }
                $details = count($changes) > 0 ? implode(' | ', $changes) : "تم تحديث البيانات";
                logActivity('تعديل حساب مستخدم', 'edit', 'user', $id, $data['full_name'], $details);
            } catch (Exception $e) {}
            alert('تم تحديث المستخدم بنجاح', 'success');
        } else {
            alert('حدث خطأ أثناء التحديث', 'error');
        }
        redirect('/users.php');
        break;
        
    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        $currentUser = getCurrentUser();
        
        if ($id == $currentUser['id']) {
            alert('لا يمكنك حذف حسابك', 'error');
            redirect('/users.php');
        }
        
        // التحقق من صلاحية الحذف على هذا المستخدم
        if (!canManageUser($id)) {
            alert('ليس لديك صلاحية لحذف هذا المستخدم', 'error');
            redirect('/users.php');
        }
        
        // الحصول على اسم المستخدم قبل الحذف
        $user = $userModel->findById($id);
        $userName = $user['full_name'] ?? 'غير معروف';
        
        if ($userModel->delete($id)) {
            try {
                $roleName = ROLES[$user['role'] ?? ''] ?? ($user['role'] ?? 'غير محدد');
                $details = "اسم المستخدم: " . ($user['username'] ?? '-');
                $details .= " | الدور: $roleName";
                logActivity('حذف حساب مستخدم', 'delete', 'user', $id, $userName, $details);
            } catch (Exception $e) {}
            alert('تم حذف المستخدم بنجاح', 'success');
        } else {
            alert('حدث خطأ أثناء الحذف', 'error');
        }
        redirect('/users.php');
        break;
        
    default:
        redirect('/users.php');
}
