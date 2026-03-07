<?php
/**
 * معالج أثاث ومستلزمات الصفوف
 * Classroom Equipment Handler
 * 
 * @package SchoolManager
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../models/ClassroomEquipment.php';

// التحقق من تسجيل الدخول
requireLogin();

// التحقق من الصلاحيات (مدير أو معاون فقط)
if (!isAdmin() && !isAssistant()) {
    alert('ليس لديك صلاحية لإدارة أثاث الصفوف', 'error');
    redirect('dashboard.php');
}

// التحقق من CSRF
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    alert('خطأ في التحقق الأمني', 'error');
    redirect('classroom_equipment.php');
}

$model = new ClassroomEquipment();
$action = $_POST['action'] ?? '';
$currentUser = getCurrentUser();

// دالة مساعدة للحصول على اسم الصف
function getClassName($classId) {
    return CLASSES[$classId] ?? "الصف $classId";
}

try {
    switch ($action) {
        case 'add':
            $data = [
                'class_id' => (int)$_POST['class_id'],
                'section' => $_POST['section'] ?? null,
                'equipment_type' => $_POST['equipment_type'],
                'custom_name' => $_POST['custom_name'] ?? null,
                'quantity' => (int)($_POST['quantity'] ?? 1),
                'condition' => $_POST['condition'] ?? 'good',
                'notes' => $_POST['notes'] ?? null,
                'added_by' => $currentUser['id']
            ];
            
            if ($model->add($data)) {
                // تسجيل العملية بتفاصيل دقيقة
                try {
                    require_once __DIR__ . '/../models/ActivityLog.php';
                    $actLog = new ActivityLog();
                    
                    $typeName = ClassroomEquipment::getTypeName($data['equipment_type']);
                    $conditionName = ClassroomEquipment::getConditionName($data['condition']);
                    $className = getClassName($data['class_id']);
                    $sectionText = $data['section'] ? " شعبة {$data['section']}" : '';
                    
                    $targetName = "{$typeName} ({$data['quantity']})";
                    $details = "الصف: {$className}{$sectionText} | الكمية: {$data['quantity']} | الحالة: {$conditionName}";
                    if ($data['custom_name']) {
                        $details .= " | الاسم المخصص: {$data['custom_name']}";
                    }
                    
                    $actLog->log(
                        'إضافة أثاث جديد',
                        'add',
                        'equipment',
                        null,
                        $targetName,
                        $details
                    );
                } catch (Exception $e) {}
                
                alert('✅ تمت إضافة العنصر بنجاح', 'success');
            } else {
                alert('حدث خطأ أثناء الإضافة', 'error');
            }
            break;
            
        case 'edit':
            $id = (int)$_POST['id'];
            $oldItem = $model->findById($id);
            
            $data = [
                'class_id' => (int)$_POST['class_id'],
                'section' => $_POST['section'] ?? null,
                'equipment_type' => $_POST['equipment_type'],
                'custom_name' => $_POST['custom_name'] ?? null,
                'quantity' => (int)($_POST['quantity'] ?? 1),
                'condition' => $_POST['condition'] ?? 'good',
                'notes' => $_POST['notes'] ?? null
            ];
            
            if ($model->update($id, $data)) {
                // تسجيل العملية بتفاصيل دقيقة
                try {
                    require_once __DIR__ . '/../models/ActivityLog.php';
                    $actLog = new ActivityLog();
                    
                    $typeName = ClassroomEquipment::getTypeName($data['equipment_type']);
                    $className = getClassName($data['class_id']);
                    $sectionText = $data['section'] ? " شعبة {$data['section']}" : '';
                    
                    $targetName = "{$typeName} - {$className}{$sectionText}";
                    
                    // بناء تفاصيل التغييرات
                    $changes = [];
                    if ($oldItem) {
                        if ($oldItem['quantity'] != $data['quantity']) {
                            $changes[] = "الكمية: {$oldItem['quantity']} ← {$data['quantity']}";
                        }
                        if ($oldItem['condition'] != $data['condition']) {
                            $oldCond = ClassroomEquipment::getConditionName($oldItem['condition']);
                            $newCond = ClassroomEquipment::getConditionName($data['condition']);
                            $changes[] = "الحالة: {$oldCond} ← {$newCond}";
                        }
                        if ($oldItem['class_id'] != $data['class_id']) {
                            $oldClass = getClassName($oldItem['class_id']);
                            $newClass = getClassName($data['class_id']);
                            $changes[] = "الصف: {$oldClass} ← {$newClass}";
                        }
                    }
                    
                    $details = count($changes) > 0 ? implode(' | ', $changes) : "تم تحديث البيانات";
                    
                    $actLog->log(
                        'تعديل أثاث',
                        'edit',
                        'equipment',
                        $id,
                        $targetName,
                        $details
                    );
                } catch (Exception $e) {}
                
                alert('✅ تم تحديث العنصر بنجاح', 'success');
            } else {
                alert('حدث خطأ أثناء التحديث', 'error');
            }
            break;
            
        case 'delete':
            $id = (int)$_POST['id'];
            $item = $model->findById($id);
            
            if ($item && $model->delete($id)) {
                // تسجيل العملية بتفاصيل دقيقة
                try {
                    require_once __DIR__ . '/../models/ActivityLog.php';
                    $actLog = new ActivityLog();
                    
                    $typeName = ClassroomEquipment::getTypeName($item['equipment_type']);
                    $className = getClassName($item['class_id']);
                    $sectionText = $item['section'] ? " شعبة {$item['section']}" : '';
                    $conditionName = ClassroomEquipment::getConditionName($item['condition']);
                    
                    $targetName = "{$typeName} ({$item['quantity']})";
                    $details = "تم حذفه من: {$className}{$sectionText} | الكمية المحذوفة: {$item['quantity']} | الحالة: {$conditionName}";
                    
                    $actLog->log(
                        'حذف أثاث',
                        'delete',
                        'equipment',
                        $id,
                        $targetName,
                        $details
                    );
                } catch (Exception $e) {}
                
                alert('✅ تم حذف العنصر بنجاح', 'success');
            } else {
                alert('حدث خطأ أثناء الحذف', 'error');
            }
            break;
            
        default:
            alert('عملية غير معروفة', 'error');
    }
} catch (Exception $e) {
    error_log("Equipment handler error: " . $e->getMessage());
    alert('حدث خطأ غير متوقع', 'error');
}

// إعادة التوجيه
redirect('classroom_equipment.php');
