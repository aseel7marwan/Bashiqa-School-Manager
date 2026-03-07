<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/permissions.php';
require_once __DIR__ . '/../models/SchoolEvent.php';
require_once __DIR__ . '/../models/ActivityLog.php';

requireLogin();

if (!isAdmin()) {
    alert('ليس لديك صلاحية لهذا الإجراء', 'error');
    redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/events.php');
}

if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    alert('خطأ في التحقق الأمني', 'error');
    redirect('/events.php');
}

$action = $_POST['action'] ?? '';
$eventModel = new SchoolEvent();

switch ($action) {
    case 'add':
        $data = [
            'title' => sanitize($_POST['title'] ?? ''),
            'description' => sanitize($_POST['description'] ?? ''),
            'event_date' => $_POST['event_date'] ?? '',
            'event_type' => $_POST['event_type'] ?? 'event',
            'is_holiday' => (bool)($_POST['is_holiday'] ?? false)
        ];
        
        if (empty($data['title']) || empty($data['event_date'])) {
            alert('العنوان والتاريخ مطلوبان', 'error');
            redirect('/events.php?action=add');
        }
        
        if ($eventId = $eventModel->create($data)) {
            try {
                $eventType = $data['is_holiday'] ? 'عطلة رسمية' : 'حدث مدرسي';
                $details = "النوع: $eventType | التاريخ: " . formatDate($data['event_date']);
                if (!empty($data['description'])) {
                    $details .= " | الوصف: " . mb_substr($data['description'], 0, 50);
                }
                logActivity('إضافة ' . $eventType, 'add', 'event', $eventId, $data['title'], $details);
            } catch (Exception $e) {}
            alert('تم إضافة الحدث بنجاح', 'success');
        } else {
            alert('حدث خطأ أثناء الإضافة', 'error');
        }
        redirect('/events.php');
        break;
        
    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        
        // الحصول على بيانات الحدث قبل الحذف
        $event = $eventModel->findById($id);
        $eventTitle = $event['title'] ?? 'غير معروف';
        
        if ($eventModel->delete($id)) {
            try {
                $eventType = ($event['is_holiday'] ?? false) ? 'عطلة رسمية' : 'حدث مدرسي';
                $details = "النوع: $eventType | التاريخ: " . formatDate($event['event_date'] ?? '');
                logActivity('حذف ' . $eventType, 'delete', 'event', $id, $eventTitle, $details);
            } catch (Exception $e) {}
            alert('تم حذف الحدث بنجاح', 'success');
        } else {
            alert('حدث خطأ أثناء الحذف', 'error');
        }
        redirect('/events.php');
        break;
        
    default:
        redirect('/events.php');
}
