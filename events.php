<?php
/**
 * الأحداث والعطل - Events & Holidays
 * إدارة المناسبات والعطل الرسمية
 * 
 * @package SchoolManager
 * @access  جميع المستخدمين (الإضافة/الحذف للمدير فقط)
 */

$pageTitle = 'الأحداث والعطل';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/models/SchoolEvent.php';

requireLogin();

$eventModel = new SchoolEvent();
$events = $eventModel->getByMonth(date('Y'), date('m'));
$upcomingEvents = $eventModel->getUpcoming(20);

$action = $_GET['action'] ?? 'list';

// Only admins can add events
if ($action === 'add' && !isAdmin()) {
    alert('ليس لديك صلاحية لإضافة أحداث', 'error');
    redirect('/events.php');
}

require_once __DIR__ . '/views/components/header.php';
?>

<div class="page-header d-flex justify-between align-center flex-wrap gap-2">
    <div>
        <h1>الأحداث والعطل المدرسية</h1>
        <p>المناسبات والعطل الرسمية</p>
    </div>
    <?php if (isAdmin()): ?>
    <a href="?action=add" class="btn btn-primary">
        ➕ إضافة حدث جديد
    </a>
    <?php endif; ?>
</div>

<?php if ($action === 'add'): ?>
<div class="card mb-3 fade-in">
    <div class="card-header">
        <h3>➕ إضافة حدث جديد</h3>
        <a href="events.php" class="btn btn-secondary btn-sm">إلغاء</a>
    </div>
    <div class="card-body">
        <form action="controllers/event_handler.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label>عنوان الحدث *</label>
                    <input type="text" name="title" class="form-control" required
                           placeholder="مثال: عطلة عيد الفطر">
                </div>
                
                <div class="form-group">
                    <label>التاريخ *</label>
                    <input type="date" name="event_date" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>نوع الحدث</label>
                    <select name="event_type" class="form-control">
                        <option value="event">حدث عام</option>
                        <option value="holiday">عطلة</option>
                        <option value="exam">امتحان</option>
                        <option value="meeting">اجتماع</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>هل هو عطلة رسمية؟</label>
                    <select name="is_holiday" class="form-control">
                        <option value="0">لا</option>
                        <option value="1">نعم</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>الوصف</label>
                <textarea name="description" class="form-control" rows="3"
                          placeholder="تفاصيل إضافية عن الحدث..."></textarea>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">💾 إضافة الحدث</button>
                <a href="events.php" class="btn btn-secondary">إلغاء</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card fade-in">
    <div class="card-header">
        <h3>📅 الأحداث القادمة</h3>
    </div>
    <div class="card-body">
        <?php if (empty($upcomingEvents)): ?>
        <div class="empty-state">
            <div class="icon">📅</div>
            <h3>لا توجد أحداث</h3>
            <p>قم بإضافة أحداث ومناسبات مدرسية</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>العنوان</th>
                        <th>التاريخ</th>
                        <th>النوع</th>
                        <th>عطلة</th>
                        <?php if (isAdmin()): ?>
                        <th>إجراءات</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcomingEvents as $event): ?>
                    <tr>
                        <td><strong><?= sanitize($event['title']) ?></strong></td>
                        <td><?= formatArabicDate($event['event_date']) ?></td>
                        <td>
                            <?php
                            $types = ['event' => 'حدث', 'holiday' => 'عطلة', 'exam' => 'امتحان', 'meeting' => 'اجتماع'];
                            echo $types[$event['event_type']] ?? $event['event_type'];
                            ?>
                        </td>
                        <td>
                            <?php if ($event['is_holiday']): ?>
                            <span class="badge badge-success">نعم</span>
                            <?php else: ?>
                            <span class="badge badge-secondary">لا</span>
                            <?php endif; ?>
                        </td>
                        <?php if (isAdmin()): ?>
                        <td>
                            <button type="button" 
                                    class="btn btn-danger btn-sm"
                                    data-delete
                                    data-module="events"
                                    data-id="<?= $event['id'] ?>"
                                    data-delete-message="هل تريد حذف الحدث '<?= sanitize($event['title']) ?>'؟"
                                    title="حذف">🗑️</button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
