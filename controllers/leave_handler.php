<?php
/**
 * معالج الإجازات - Leave Handler
 * معالجة عمليات الإجازات
 * 
 * @package SchoolManager
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Leave.php';
require_once __DIR__ . '/../models/ActivityLog.php';

requireLogin();

// المدير والمعاون فقط
if (!isAdmin() && !isAssistant()) {
    alert('ليس لديك صلاحية', 'error');
    redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/leaves.php');
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    alert('خطأ في التحقق الأمني', 'error');
    redirect('/leaves.php');
}

$leaveModel = new Leave();
$currentUser = getCurrentUser();
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        $data = [
            'person_type' => sanitize($_POST['person_type'] ?? ''),
            'person_id' => (int)($_POST['person_id'] ?? 0),
            'leave_type' => sanitize($_POST['leave_type'] ?? 'sick'),
            'start_date' => $_POST['start_date'] ?? '',
            'end_date' => $_POST['end_date'] ?? '',
            'reason' => sanitize($_POST['reason'] ?? ''),
            'notes' => sanitize($_POST['notes'] ?? ''),
            'recorded_by' => $currentUser['id']
        ];
        
        if (empty($data['person_id']) || empty($data['start_date']) || empty($data['end_date'])) {
            alert('الرجاء ملء جميع الحقول المطلوبة', 'error');
            redirect('/leaves.php?type=' . $data['person_type']);
        }
        
        if (strtotime($data['end_date']) < strtotime($data['start_date'])) {
            alert('تاريخ النهاية يجب أن يكون بعد تاريخ البداية', 'error');
            redirect('/leaves.php?type=' . $data['person_type']);
        }
        
        if ($leaveId = $leaveModel->create($data)) {
            try {
                $personTypeName = $data['person_type'] === 'teacher' ? 'موظف' : 'طالب';
                $leaveTypes = ['sick' => 'مرضية', 'annual' => 'سنوية', 'emergency' => 'طارئة', 'maternity' => 'أمومة', 'other' => 'أخرى'];
                $leaveTypeName = $leaveTypes[$data['leave_type']] ?? $data['leave_type'];
                
                // حساب عدد الأيام
                $days = (strtotime($data['end_date']) - strtotime($data['start_date'])) / 86400 + 1;
                
                $details = "النوع: $leaveTypeName | المدة: $days يوم";
                $details .= " | من: " . formatDate($data['start_date']) . " إلى: " . formatDate($data['end_date']);
                if (!empty($data['reason'])) {
                    $details .= " | السبب: " . mb_substr($data['reason'], 0, 30);
                }
                
                logActivity("تسجيل إجازة $personTypeName", 'add', 'leave', $leaveId, null, $details);
            } catch (Exception $e) {}
            alert('تم تسجيل الإجازة بنجاح', 'success');
        } else {
            alert('حدث خطأ أثناء تسجيل الإجازة', 'error');
        }
        
        redirect('/leaves.php?type=' . $data['person_type']);
        break;
        
    case 'update':
        $leaveId = (int)($_POST['leave_id'] ?? 0);
        $personType = sanitize($_POST['person_type'] ?? 'teacher');
        
        $data = [
            'leave_type' => sanitize($_POST['leave_type'] ?? 'sick'),
            'start_date' => $_POST['start_date'] ?? '',
            'end_date' => $_POST['end_date'] ?? '',
            'reason' => sanitize($_POST['reason'] ?? ''),
            'notes' => sanitize($_POST['notes'] ?? '')
        ];
        
        if (empty($data['start_date']) || empty($data['end_date'])) {
            alert('الرجاء ملء جميع الحقول المطلوبة', 'error');
            redirect('/leaves.php?type=' . $personType);
        }
        
        if ($leaveModel->update($leaveId, $data)) {
            try {
                $days = (strtotime($data['end_date']) - strtotime($data['start_date'])) / 86400 + 1;
                $details = "المدة الجديدة: $days يوم | من: " . formatDate($data['start_date']) . " إلى: " . formatDate($data['end_date']);
                logActivity('تعديل إجازة', 'edit', 'leave', $leaveId, null, $details);
            } catch (Exception $e) {}
            alert('تم تحديث الإجازة بنجاح', 'success');
        } else {
            alert('حدث خطأ أثناء تحديث الإجازة', 'error');
        }
        
        redirect('/leaves.php?type=' . $personType);
        break;
        
    case 'delete':
        $leaveId = (int)($_POST['leave_id'] ?? 0);
        
        // الحصول على بيانات الإجازة قبل الحذف
        $leave = $leaveModel->findById($leaveId);
        
        if ($leaveModel->delete($leaveId)) {
            try {
                $details = '';
                if ($leave) {
                    $days = (strtotime($leave['end_date']) - strtotime($leave['start_date'])) / 86400 + 1;
                    $details = "المدة المحذوفة: $days يوم | من: " . formatDate($leave['start_date']) . " إلى: " . formatDate($leave['end_date']);
                }
                logActivity('حذف إجازة', 'delete', 'leave', $leaveId, null, $details);
            } catch (Exception $e) {}
            alert('تم حذف الإجازة بنجاح', 'success');
        } else {
            alert('حدث خطأ أثناء حذف الإجازة', 'error');
        }
        
        redirect($_SERVER['HTTP_REFERER'] ?? '/leaves.php');
        break;
        
    default:
        redirect('/leaves.php');
}
