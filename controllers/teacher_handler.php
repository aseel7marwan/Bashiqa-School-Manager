<?php
/**
 * معالج المعلمين - Teacher Handler
 * إضافة وتعديل وحذف المعلمين مع جميع حقول البطاقة
 * 
 * @package SchoolManager
 * @access  مدير المدرسة فقط (لا يُسمح للمعاون)
 * @security صلاحية حصرية للمدير لمنع التلاعب
 */

// ضبط الترميز
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/ActivityLog.php';

requireLogin();

// ═══════════════════════════════════════════════════════════════
// 🔒 صلاحية: المدير والمعاون
// ═══════════════════════════════════════════════════════════════
if (!canManageSystem()) {
    alert('⛔ إدارة الكادر التعليمي متاحة للمدير والمعاون فقط', 'error');
    redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/teachers.php');
}

if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    alert('خطأ في التحقق الأمني', 'error');
    redirect('/teachers.php');
}

$action = $_POST['action'] ?? '';
$teacherModel = new Teacher();
$userModel = new User();

/**
 * جمع جميع بيانات المعلم من النموذج
 */
function collectTeacherData($post) {
    // قائمة جميع الحقول
    $fields = [
        // البيانات الشخصية
        'full_name', 'birth_place', 'birth_date', 'mother_name', 'phone', 'email', 'blood_type',
        
        // الشهادة والتخصص
        'certificate', 'specialization', 'institute_name', 'graduation_year',
        
        // بيانات التعيين
        'hire_date', 'first_job_date', 'current_school_date',
        'hire_order_number', 'hire_order_date',
        'school_order_number', 'transfer_order_number', 'transfer_date',
        'interruption_date', 'interruption_reason', 'return_date',
        'job_grade', 'career_stage',
        
        // الوثائق والهويات
        'national_id', 'national_id_date', 'record_number', 'page_number',
        'nationality_cert_number', 'nationality_cert_date', 'nationality_folder_number',
        'residence_card', 'form_number', 'ration_card_number', 'agent_info', 'ration_center',
        
        // الحالة الاجتماعية
        'marital_status', 'marriage_date', 'spouse_name', 'spouse_job', 'marriage_contract_info',
        
        // التقاعد
        'retirement_order_number', 'retirement_date',
        
        // معلومات إضافية
        'courses', 'notes'
    ];
    
    $data = [];
    foreach ($fields as $field) {
        if (isset($post[$field])) {
            // تنظيف البيانات النصية
            if (strpos($field, '_date') !== false) {
                // حقول التاريخ - لا تنظيفها
                $data[$field] = $post[$field] ?: null;
            } else {
                // حقول نصية
                $data[$field] = sanitize($post[$field]);
            }
        }
    }
    
    return $data;
}

switch ($action) {
    case 'add':
        $data = collectTeacherData($_POST);
        
        // ═══════════════════════════════════════════════════════════════
        // التحقق من الحقول الإلزامية حسب المتطلبات الرسمية
        // ═══════════════════════════════════════════════════════════════
        $requiredFields = [
            'full_name' => '⚠️ الاسم الكامل مطلوب',
            'certificate' => '⚠️ المؤهل العلمي / الشهادة مطلوب',
            'specialization' => '⚠️ الاختصاص مطلوب',
            'graduation_year' => '⚠️ سنة التخرج مطلوبة',
            'hire_date' => '⚠️ تاريخ التعيين مطلوب'
        ];
        
        $errors = [];
        foreach ($requiredFields as $field => $message) {
            if (empty($data[$field])) {
                $errors[] = $message;
            }
        }
        
        if (!empty($errors)) {
            alert(implode('<br>', $errors), 'error');
            redirect('/teachers.php?action=add');
        }
        
        // الحصول على معرف المعلم الجديد بدلاً من استخدام create
        require_once __DIR__ . '/../config/database.php';
        $conn = getConnection();
        
        // تصفية البيانات
        $allowedFields = [
            'full_name', 'birth_place', 'birth_date', 'mother_name', 'phone', 'email', 'blood_type',
            'certificate', 'specialization', 'institute_name', 'graduation_year',
            'hire_date', 'first_job_date', 'current_school_date',
            'hire_order_number', 'hire_order_date', 'school_order_number', 'transfer_order_number', 'transfer_date',
            'interruption_date', 'interruption_reason', 'return_date', 'job_grade', 'career_stage',
            'national_id', 'national_id_date', 'record_number', 'page_number',
            'nationality_cert_number', 'nationality_cert_date', 'nationality_folder_number',
            'residence_card', 'form_number', 'ration_card_number', 'agent_info', 'ration_center',
            'marital_status', 'marriage_date', 'spouse_name', 'spouse_job', 'marriage_contract_info',
            'retirement_order_number', 'retirement_date', 'courses', 'notes', 'photo', 'data_writers',
            'user_id', 'status'
        ];
        
        $filteredData = array_intersect_key($data, array_flip($allowedFields));
        
        $fields = ['status'];
        $placeholders = ["'active'"];
        $values = [];
        
        foreach ($filteredData as $field => $value) {
            if ($value !== null && $value !== '') {
                $fields[] = $field;
                $placeholders[] = '?';
                $values[] = $value;
            }
        }
        
        $sql = "INSERT INTO teachers (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute($values);
        
        if ($result) {
            $newTeacherId = $conn->lastInsertId();
            
            try {
                $details = "التخصص: " . ($data['specialization'] ?? '-');
                $details .= " | الشهادة: " . ($data['certificate'] ?? '-');
                $details .= " | الهاتف: " . ($data['phone'] ?? '-');
                logActivity('إضافة موظف جديد', 'add', 'teacher', $newTeacherId, $data['full_name'], $details);
            } catch (Exception $e) {}
            
            // ═══════════════════════════════════════════════════════════════
            // 🔄 التسلسل الإجباري: التوجيه إلى صفحة تعيين المواد أولاً
            // ═══════════════════════════════════════════════════════════════
            alert('✅ تم إضافة المعلم بنجاح! الخطوة التالية: تعيين المواد والصفوف ثم إنشاء الحساب.', 'success');
            redirect('/teacher_workflow.php?teacher_id=' . $newTeacherId . '&step=assignments');
        } else {
            alert('❌ حدث خطأ أثناء الإضافة', 'error');
            redirect('/teachers.php');
        }
        break;
        
    case 'edit':
        $id = (int)($_POST['id'] ?? 0);
        $data = collectTeacherData($_POST);
        
        // التحقق من الحقول الإلزامية
        if (empty($data['full_name'])) {
            alert('الاسم مطلوب', 'error');
            redirect("/teachers.php?action=edit&id=$id");
        }
        
        // الحصول على البيانات القديمة
        $oldTeacher = $teacherModel->findById($id);
        
        if ($teacherModel->update($id, $data)) {
            // ═══════════════════════════════════════════════════════════════
            // 🔄 تحديث اسم المعلم في جدول users إذا كان لديه حساب
            // ═══════════════════════════════════════════════════════════════
            if ($oldTeacher && !empty($oldTeacher['user_id'])) {
                $userModel->update($oldTeacher['user_id'], ['full_name' => $data['full_name']]);
            }
            
            try {
                // بناء تفاصيل التغييرات
                $changes = [];
                if ($oldTeacher) {
                    if (($oldTeacher['specialization'] ?? '') != ($data['specialization'] ?? '')) {
                        $changes[] = "التخصص: {$oldTeacher['specialization']} ← {$data['specialization']}";
                    }
                    if (($oldTeacher['phone'] ?? '') != ($data['phone'] ?? '')) {
                        $changes[] = "الهاتف: {$oldTeacher['phone']} ← {$data['phone']}";
                    }
                    if (($oldTeacher['job_grade'] ?? '') != ($data['job_grade'] ?? '')) {
                        $changes[] = "الدرجة الوظيفية: {$oldTeacher['job_grade']} ← {$data['job_grade']}";
                    }
                }
                $details = count($changes) > 0 ? implode(' | ', $changes) : "تم تحديث البيانات";
                logActivity('تعديل بيانات موظف', 'edit', 'teacher', $id, $data['full_name'], $details);
            } catch (Exception $e) {}
            alert('✅ تم تحديث بيانات المعلم', 'success');
        } else {
            alert('❌ حدث خطأ أثناء التحديث', 'error');
        }
        redirect('/teachers.php');
        break;
        
    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        $teacher = $teacherModel->findById($id);
        
        if (!$teacher) {
            alert('المعلم غير موجود', 'error');
            redirect('/teachers.php');
        }
        
        $teacherName = $teacher['full_name'] ?? 'غير معروف';
        $userId = $teacher['user_id'] ?? null;
        
        $deletedData = [
            'الاسم' => $teacher['full_name'] ?? '',
            'التخصص' => $teacher['specialization'] ?? '',
            'الهاتف' => $teacher['phone'] ?? '',
            'لديه حساب' => $userId ? 'نعم' : 'لا'
        ];
        
        // ═══════════════════════════════════════════════════════════════
        // 🗑️ الحذف المتسلسل (Cascading Delete)
        // ═══════════════════════════════════════════════════════════════
        require_once __DIR__ . '/../config/database.php';
        $conn = getConnection();
        
        // ⚡ حذف حساب المستخدم أولاً (خارج Transaction لضمان التنفيذ)
        $userDeleted = false;
        $userToDelete = null;
        
        // البحث عن الحساب المرتبط
        if ($userId && $userId > 0) {
            $userToDelete = $userId;
        }
        
        // طريقة إضافية 1: البحث عبر teacher_id في جدول users
        if (!$userToDelete) {
            try {
                $stmt = $conn->prepare("SELECT id FROM users WHERE teacher_id = ? LIMIT 1");
                $stmt->execute([$id]);
                $found = $stmt->fetch();
                if ($found && !empty($found['id'])) {
                    $userToDelete = $found['id'];
                }
            } catch (Exception $e) { /* teacher_id column may not exist */ }
        }
        
        // طريقة إضافية 2: البحث عبر الاسم (مع الدور teacher)
        if (!$userToDelete && !empty($teacherName)) {
            try {
                $stmt = $conn->prepare("SELECT id FROM users WHERE full_name = ? AND role = 'teacher' LIMIT 1");
                $stmt->execute([$teacherName]);
                $found = $stmt->fetch();
                if ($found && !empty($found['id'])) {
                    $userToDelete = $found['id'];
                }
            } catch (Exception $e) { /* user search by name failed */ }
        }
        
        // طريقة إضافية 3: البحث عبر الاسم مع أدوار متعددة (teacher, assistant, admin)
        if (!$userToDelete && !empty($teacherName)) {
            try {
                $stmt = $conn->prepare("SELECT id FROM users WHERE full_name = ? AND role IN ('teacher', 'assistant', 'admin') LIMIT 1");
                $stmt->execute([$teacherName]);
                $found = $stmt->fetch();
                if ($found && !empty($found['id'])) {
                    $userToDelete = $found['id'];
                }
            } catch (Exception $e) { /* user search expanded roles failed */ }
        }
        
        // طريقة إضافية 4: البحث الجزئي بالاسم
        if (!$userToDelete && !empty($teacherName)) {
            try {
                $namePart = explode(' ', trim($teacherName))[0]; // الاسم الأول فقط
                $stmt = $conn->prepare("SELECT id FROM users WHERE full_name LIKE ? AND role IN ('teacher', 'assistant', 'admin') LIMIT 1");
                $stmt->execute([$namePart . '%']);
                $found = $stmt->fetch();
                if ($found && !empty($found['id'])) {
                    $userToDelete = $found['id'];
                }
            } catch (Exception $e) { /* partial name search failed */ }
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
            
            // 1. حذف تعيينات المواد والصفوف (بناءً على teacher_id و teacher_db_id)
            try {
                $stmt = $conn->prepare("DELETE FROM teacher_assignments WHERE teacher_id = ? OR teacher_db_id = ?");
                $stmt->execute([$userId, $id]);
            } catch (Exception $e) { /* teacher_assignments table may not exist */ }
            
            // 2. حذف التعيينات المؤقتة (بناءً على teacher_db_id)
            try {
                $stmt = $conn->prepare("DELETE FROM temp_teacher_assignments WHERE teacher_db_id = ?");
                $stmt->execute([$id]);
            } catch (Exception $e) { /* temp_teacher_assignments may not exist */ }
            
            // 3. حذف من الجدول الأسبوعي (إن وجد)
            try {
                $stmt = $conn->prepare("DELETE FROM schedules WHERE teacher_db_id = ?");
                $stmt->execute([$id]);
            } catch (Exception $e) { /* schedules.teacher_db_id may not exist */ }
            
            // 3. حذف الإجازات المرتبطة (جدول leaves لا يحتوي على FOREIGN KEY)
            try {
                $stmt = $conn->prepare("DELETE FROM leaves WHERE person_type = 'teacher' AND person_id = ?");
                $stmt->execute([$id]);
            } catch (Exception $e) { /* leaves table may not exist */ }
            
            // 4. حذف سجلات الغياب المرتبطة (بناءً على teacher_db_id)
            try {
                $stmt = $conn->prepare("DELETE FROM teacher_absences WHERE teacher_db_id = ?");
                $stmt->execute([$id]);
            } catch (Exception $e) { /* teacher_absences may not exist */ }
            
            // 5. حذف سجلات الحضور المرتبطة (بناءً على teacher_db_id)
            try {
                $stmt = $conn->prepare("DELETE FROM teacher_attendance WHERE teacher_db_id = ?");
                $stmt->execute([$id]);
            } catch (Exception $e) { /* teacher_attendance may not exist */ }
            
            // ℹ️ حذف الحساب تم مسبقاً (قبل بداية Transaction)
            
            // 4. حذف سجل المعلم الأساسي
            $stmt = $conn->prepare("DELETE FROM teachers WHERE id = ?");
            $stmt->execute([$id]);
            
            $conn->commit();
            
            // تسجيل العملية
            try {
                logActivity('حذف موظف وحسابه', 'delete', 'teacher', $id, $teacherName, 
                    $userId ? 'تم حذف الحساب المرتبط أيضاً' : null, $deletedData, null);
            } catch (Exception $e) {}
            
            alert('✅ تم حذف المعلم وجميع بياناته المرتبطة بنجاح', 'success');
            
        } catch (Exception $e) {
            $conn->rollBack();
            alert('حدث خطأ أثناء حذف المعلم: ' . $e->getMessage(), 'error');
        }
        
        redirect('/teachers.php');
        break;
        
    case 'create_account':
        // ═══════════════════════════════════════════════════════════════
        // 🔒 التسلسل الإجباري: توجيه لصفحة التعيينات أولاً
        // ═══════════════════════════════════════════════════════════════
        $id = (int)($_POST['id'] ?? 0);
        
        $teacher = $teacherModel->findById($id);
        
        if (!$teacher) {
            alert('المعلم غير موجود', 'error');
            redirect('/teachers.php');
        }
        
        if ($teacher['user_id']) {
            alert('المعلم لديه حساب بالفعل', 'error');
            redirect('/teachers.php');
        }
        
        // التوجيه إلى صفحة التسلسل الإجباري
        alert('⚠️ يجب تعيين المواد والصفوف للمعلم أولاً قبل إنشاء الحساب', 'info');
        redirect('/teacher_workflow.php?teacher_id=' . $id . '&step=assignments');
        break;
        
    case 'unlink_account':
        $id = (int)($_POST['id'] ?? 0);
        $teacher = $teacherModel->findById($id);
        
        if ($teacher && $teacher['user_id']) {
            // حذف حساب المستخدم
            $userModel->delete($teacher['user_id']);
            // فصل الحساب
            $teacherModel->unlinkUser($id);
            alert('✅ تم فصل وحذف الحساب', 'success');
        } else {
            alert('المعلم ليس لديه حساب', 'error');
        }
        redirect('/teachers.php');
        break;
        
    // ═══════════════════════════════════════════════════════════════
    // 🔄 إنشاء حساب من التسلسل الإجباري (مع التحقق من التعيينات)
    // ═══════════════════════════════════════════════════════════════
    case 'create_account_workflow':
        $id = (int)($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        $teacher = $teacherModel->findById($id);
        
        if (!$teacher) {
            alert('المعلم غير موجود', 'error');
            redirect('/teachers.php');
        }
        
        if ($teacher['user_id']) {
            alert('المعلم لديه حساب بالفعل', 'error');
            redirect('/teacher_workflow.php?teacher_id=' . $id . '&step=account');
        }
        
        // ═══════════════════════════════════════════════════════════════
        // 🔒 التحقق الإجباري من التعيينات قبل إنشاء الحساب
        // ═══════════════════════════════════════════════════════════════
        require_once __DIR__ . '/../models/TeacherAssignment.php';
        $assignmentModel = new TeacherAssignment();
        
        // التحقق من وجود تعيينات مؤقتة
        if (!$assignmentModel->hasTempAssignments($id)) {
            alert('⚠️ يجب تعيين المواد والصفوف للمعلم أولاً قبل إنشاء الحساب!', 'error');
            redirect('/teacher_workflow.php?teacher_id=' . $id . '&step=assignments');
        }
        
        // التحقق من البيانات
        if ($error = validateUsername($username)) {
            alert($error, 'error');
            redirect('/teacher_workflow.php?teacher_id=' . $id . '&step=account');
        }
        
        if ($error = validatePassword($password)) {
            alert($error, 'error');
            redirect('/teacher_workflow.php?teacher_id=' . $id . '&step=account');
        }
        
        // التحقق من عدم وجود اسم المستخدم
        if ($userModel->findByUsernameIncludingInactive($username)) {
            alert('اسم المستخدم موجود مسبقاً', 'error');
            redirect('/teacher_workflow.php?teacher_id=' . $id . '&step=account');
        }
        
        // إنشاء الحساب
        $role = $_POST['role'] ?? 'teacher';
        if (!in_array($role, ['teacher', 'assistant', 'admin'])) {
            $role = 'teacher';
        }
        
        $userData = [
            'username' => $username,
            'password' => $password,
            'full_name' => $teacher['full_name'],
            'role' => $role
        ];
        
        $userId = $userModel->createAndGetId($userData);
        
        if ($userId) {
            // ربط المعلم بالحساب
            $teacherModel->linkToUser($id, $userId);
            
            // ═══════════════════════════════════════════════════════════════
            // 🔄 نقل التعيينات المؤقتة إلى الجدول الدائم
            // ═══════════════════════════════════════════════════════════════
            $assignmentModel->migrateTemporaryAssignments($id, $userId);
            
            try {
                logActivity('إنشاء حساب معلم (تسلسل إجباري)', 'add', 'user', $userId, $teacher['full_name'],
                    'اسم المستخدم: ' . $username . ' - الدور: ' . $role);
            } catch (Exception $e) {}
            
            // اكتمل التسلسل - التوجيه لصفحة النجاح
            alert('🎉 تم إنشاء الحساب بنجاح! اكتمل التسلسل الإجباري.', 'success');
            redirect('/teacher_workflow.php?teacher_id=' . $id . '&step=account');
        } else {
            alert('❌ حدث خطأ أثناء إنشاء الحساب', 'error');
            redirect('/teacher_workflow.php?teacher_id=' . $id . '&step=account');
        }
        break;
        
    case 'toggle_status':
        $id = (int)($_POST['id'] ?? 0);
        $teacher = $teacherModel->findById($id);
        
        if ($teacher && $teacher['user_id']) {
            // جلب الحالة الحالية
            $user = $userModel->findById($teacher['user_id']);
            $newStatus = ($user['status'] === 'active') ? 'inactive' : 'active';
            
            $userModel->update($teacher['user_id'], ['status' => $newStatus]);
            
            if ($newStatus === 'active') {
                alert('✅ تم تفعيل الحساب', 'success');
            } else {
                alert('🚫 تم تعطيل الحساب', 'warning');
            }
        } else {
            alert('المعلم ليس لديه حساب', 'error');
        }
        redirect('/teachers.php');
        break;
        
    default:
        redirect('/teachers.php');
}
