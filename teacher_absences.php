<?php
/**
 * تسجيل غيابات الكادر - Staff Absences Management
 * صفحة تسجيل وإدارة غيابات الكادر التعليمي والإداري
 * 
 * @package SchoolManager
 * @access  مدير ومعاون فقط
 */

$pageTitle = 'تسجيل غيابات الكادر';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Schedule.php';

requireLogin();

if (!isAdmin()) {
    alert('ليس لديك صلاحية للوصول لهذه الصفحة', 'error');
    redirect('/dashboard.php');
}

$userModel = new User();
$scheduleModel = new Schedule();
$conn = getConnection();

$teachers = $userModel->getTeachers();
$date = $_GET['date'] ?? date('Y-m-d');
$arabicDayName = getArabicDayName($date);

// جلب غيابات اليوم المحدد
$absences = [];
try {
    $stmt = $conn->prepare("
        SELECT ta.*, u.full_name as teacher_name 
        FROM teacher_absences ta
        JOIN users u ON ta.teacher_id = u.id
        WHERE ta.date = ?
        ORDER BY u.full_name, ta.lesson_number
    ");
    $stmt->execute([$date]);
    $absences = $stmt->fetchAll();
} catch (Exception $e) {}

// تجميع الغيابات حسب المعلم
$absencesByTeacher = [];
foreach ($absences as $abs) {
    $teacherId = $abs['teacher_id'];
    if (!isset($absencesByTeacher[$teacherId])) {
        $absencesByTeacher[$teacherId] = [
            'name' => $abs['teacher_name'],
            'absences' => [],
            'full_day' => false
        ];
    }
    if ($abs['lesson_number'] === null) {
        $absencesByTeacher[$teacherId]['full_day'] = true;
        $absencesByTeacher[$teacherId]['reason'] = $abs['reason'];
    } else {
        $absencesByTeacher[$teacherId]['absences'][] = $abs;
    }
}

// جلب جدول اليوم
$todaySchedules = [];
$dayOfWeek = strtolower(date('l', strtotime($date)));
$dayMapping = [
    'sunday' => 'الأحد', 'monday' => 'الإثنين', 'tuesday' => 'الثلاثاء',
    'wednesday' => 'الأربعاء', 'thursday' => 'الخميس', 'friday' => 'الجمعة', 'saturday' => 'السبت'
];
$arabicDay = $dayMapping[$dayOfWeek] ?? '';

try {
    $stmt = $conn->prepare("
        SELECT s.*, u.full_name as teacher_name
        FROM schedules s
        LEFT JOIN users u ON s.teacher_id = u.id
        WHERE s.day_of_week = ?
        ORDER BY s.teacher_id, s.lesson_number
    ");
    $stmt->execute([$arabicDay]);
    $allSchedules = $stmt->fetchAll();
    
    foreach ($allSchedules as $sch) {
        if ($sch['teacher_id']) {
            if (!isset($todaySchedules[$sch['teacher_id']])) {
                $todaySchedules[$sch['teacher_id']] = [
                    'name' => $sch['teacher_name'],
                    'lessons' => []
                ];
            }
            $todaySchedules[$sch['teacher_id']]['lessons'][] = $sch;
        }
    }
} catch (Exception $e) {}

$absentCount = count($absencesByTeacher);
$presentCount = max(0, count($todaySchedules) - $absentCount);

require_once __DIR__ . '/views/components/header.php';
?>

<style>
.absence-page { max-width: 1200px; margin: 0 auto; padding: 0 1rem; }

/* بطاقة معلومات اليوم */
.day-card {
    background: linear-gradient(135deg, #6366f1 0%, #a855f7 50%, #ec4899 100%);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    color: white;
    box-shadow: 0 15px 50px rgba(99, 102, 241, 0.4);
    animation: cardGlow 3s ease-in-out infinite alternate;
}
@keyframes cardGlow {
    from { box-shadow: 0 15px 50px rgba(99, 102, 241, 0.4); }
    to { box-shadow: 0 15px 60px rgba(168, 85, 247, 0.5); }
}
.day-card-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 2rem;
}
.day-info h2 {
    color: white;
    margin: 0 0 0.5rem 0;
    font-size: 1.75rem;
    font-weight: 700;
    text-shadow: 0 2px 10px rgba(0,0,0,0.2);
}
.day-info .date {
    font-size: 1.1rem;
    opacity: 0.95;
    font-weight: 500;
}
.stats-row {
    display: flex;
    gap: 1.5rem;
}
.stat-item {
    text-align: center;
    padding: 1.5rem 2.5rem;
    border-radius: 16px;
    min-width: 130px;
    transition: all 0.3s ease;
}
.stat-item:hover {
    transform: translateY(-5px) scale(1.02);
}
.stat-item .number {
    font-size: 3.5rem;
    font-weight: 900;
    line-height: 1;
    margin-bottom: 0.5rem;
    letter-spacing: -2px;
}
.stat-item .label {
    font-size: 1.1rem;
    font-weight: 800;
    text-transform: uppercase;
}
.stat-item.present { 
    background: linear-gradient(135deg, #00c853 0%, #00e676 100%);
    border: 5px solid #4ade80;
    box-shadow: 0 10px 40px rgba(0, 200, 83, 0.6), inset 0 -3px 0 rgba(0,0,0,0.1);
}
.stat-item.present .number { 
    color: #ffffff;
    text-shadow: 0 3px 15px rgba(0,0,0,0.4), 0 0 30px rgba(255,255,255,0.3);
}
.stat-item.present .label { 
    color: #ffffff;
    text-shadow: 0 1px 3px rgba(0,0,0,0.3);
}
.stat-item.absent { 
    background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
    border: 5px solid #f87171;
    box-shadow: 0 10px 40px rgba(220, 38, 38, 0.6), inset 0 -3px 0 rgba(0,0,0,0.1);
}
.stat-item.absent .number { 
    color: #ffffff;
    text-shadow: 0 3px 15px rgba(0,0,0,0.4), 0 0 30px rgba(255,255,255,0.3);
}
.stat-item.absent .label { 
    color: #ffffff;
    text-shadow: 0 1px 3px rgba(0,0,0,0.3);
}

/* نموذج تسجيل الغياب */
.form-card {
    background: var(--bg-primary);
    border-radius: 20px;
    border: 2px solid #ef4444;
    overflow: hidden;
    margin-bottom: 2rem;
    box-shadow: var(--shadow);
}
.form-card-header {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    padding: 1.25rem 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}
.form-card-header .icon { font-size: 1.5rem; }
.form-card-header h3 { margin: 0; color: white; font-size: 1.15rem; font-weight: 600; }
.form-card-body { padding: 2rem; }

.form-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
.form-field label {
    display: block;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
}
.form-field .required { color: #ef4444; margin-right: 0.25rem; }
.form-field .form-control {
    width: 100%;
    padding: 1rem 1.25rem;
    border-radius: 12px;
    border: 2px solid var(--border-color);
    font-size: 1rem;
    transition: all 0.3s;
    background: var(--bg-primary);
}
.form-field .form-control:focus {
    border-color: #ef4444;
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
    outline: none;
}

/* جدول الغيابات */
.data-card {
    background: var(--bg-primary);
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 2rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--border-color);
}
.data-card-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-secondary);
}
.data-card-header h3 { margin: 0; font-size: 1.1rem; font-weight: 600; }

.data-table {
    width: 100%;
    border-collapse: collapse;
}
.data-table th {
    background: var(--bg-secondary);
    padding: 1rem 1.5rem;
    text-align: right;
    font-weight: 600;
    color: var(--text-primary);
    border-bottom: 2px solid var(--border-color);
    font-size: 0.9rem;
}
.data-table td {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
}
.data-table tr:hover { background: var(--bg-secondary); }
.data-table tr:last-child td { border-bottom: none; }

.teacher-cell {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.teacher-avatar {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 1.2rem;
    flex-shrink: 0;
}
.teacher-name { font-weight: 600; font-size: 1rem; }

.badge {
    display: inline-block;
    padding: 0.6rem 1.5rem;
    border-radius: 25px;
    font-weight: 700;
    font-size: 0.9rem;
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
}
.badge-fullday { 
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    color: #ffffff;
    border: 2px solid #f87171;
    box-shadow: 0 4px 15px rgba(220, 38, 38, 0.4);
}
.badge-partial { 
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #ffffff;
    border: 2px solid #fbbf24;
    box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);
}
.badge-lesson {
    display: inline-block;
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
    color: #ffffff;
    padding: 0.4rem 0.9rem;
    border-radius: 10px;
    font-size: 0.85rem;
    margin: 0.25rem;
    font-weight: 600;
    border: 2px solid #a78bfa;
    box-shadow: 0 3px 10px rgba(124, 58, 237, 0.3);
}

/* الكادر المتواجد */
.present-section {
    background: var(--bg-primary);
    border-radius: 20px;
    border: 3px solid #22c55e;
    overflow: hidden;
    margin-bottom: 2rem;
    box-shadow: 0 10px 40px rgba(34, 197, 94, 0.15);
}
.present-header {
    background: linear-gradient(135deg, #16a34a 0%, #22c55e 100%);
    padding: 1.25rem 2rem;
    border-bottom: none;
}
.present-header h3 { margin: 0; color: #ffffff; font-size: 1.15rem; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }
.present-body { padding: 1.5rem 2rem; background: var(--bg-primary); }
.present-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1rem;
}
.present-item {
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    border: 2px solid #4ade80;
    border-radius: 14px;
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(34, 197, 94, 0.15);
}
.present-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(34, 197, 94, 0.25);
}
.present-item .check {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #16a34a 0%, #22c55e 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.4);
    font-weight: bold;
}
.present-item .name { font-weight: 700; color: #166534; font-size: 1rem; }
.present-item .count { font-size: 0.9rem; color: #15803d; font-weight: 600; }

/* حالة فارغة */
.empty-state {
    padding: 4rem 2rem;
    text-align: center;
}
.empty-state .icon { font-size: 4rem; margin-bottom: 1rem; }
.empty-state h3 { color: var(--text-primary); margin-bottom: 0.5rem; }
.empty-state p { color: var(--text-muted); }

@media (max-width: 768px) {
    .form-grid { grid-template-columns: 1fr; }
    .day-card-content { flex-direction: column; text-align: center; }
    .stats-row { justify-content: center; }
    .stat-item { min-width: 100px; padding: 1rem 1.5rem; }
}
</style>

<div class="absence-page">
    <!-- العنوان -->
    <div class="page-header d-flex justify-between align-center flex-wrap gap-2">
        <div>
            <h1><?= __('🚫 تسجيل غيابات الكادر') ?></h1>
            <p><?= __('تسجيل وإدارة غيابات الكادر التعليمي') ?></p>
        </div>
        <div>
            <input type="date" id="dateSelector" value="<?= $date ?>" class="form-control" 
                   style="padding: 0.875rem 1.25rem; border-radius: 12px; font-size: 1rem; border: 2px solid var(--border-color);">
        </div>
    </div>

    <?= showAlert() ?>

    <!-- بطاقة معلومات اليوم -->
    <div class="day-card">
        <div class="day-card-content">
            <div class="day-info">
                <h2>📅 يوم <?= $arabicDayName ?></h2>
                <div class="date"><?= date('Y / m / d', strtotime($date)) ?></div>
            </div>
            <div class="stats-row">
                <div class="stat-item absent">
                    <div class="number"><?= toArabicNum($absentCount) ?></div>
                    <div class="label"><?= __('عضو غائب') ?></div>
                </div>
                <?php if ($presentCount > 0): ?>
                <div class="stat-item present">
                    <div class="number"><?= toArabicNum($presentCount) ?></div>
                    <div class="label"><?= __('عضو حاضر') ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- نموذج تسجيل غياب -->
    <div class="form-card">
        <div class="form-card-header">
            <span class="icon">➕</span>
            <h3><?= __('تسجيل غياب عضو كادر') ?></h3>
        </div>
        <div class="form-card-body">
            <form action="controllers/teacher_absence_handler.php" method="POST" id="absenceForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add_absence">
                <input type="hidden" name="date" value="<?= $date ?>">
                
                <div class="form-grid">
                    <div class="form-field">
                        <label>👔 <?= __('عضو الكادر') ?> <span class="required">*</span></label>
                        <select name="teacher_id" id="teacherSelect" class="form-control" required>
                            <option value=""><?= __('-- اختر عضو الكادر --') ?></option>
                            <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['id'] ?>">
                                <?= sanitize($teacher['full_name']) ?>
                                <?php if (isset($todaySchedules[$teacher['id']])): ?>
                                (<?= toArabicNum(count($todaySchedules[$teacher['id']]['lessons'])) ?> حصة)
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-field">
                        <label>📋 <?= __('نوع الغياب') ?></label>
                        <select name="absence_type" id="absenceType" class="form-control">
                            <option value="full_day"><?= __('🌙 اليوم كامل') ?></option>
                            <option value="specific_lesson"><?= __('📚 حصة محددة') ?></option>
                        </select>
                    </div>
                    
                    <div class="form-field" id="lessonSelectGroup" style="display: none;">
                        <label>🕐 <?= __('الحصة') ?> <span class="required">*</span></label>
                        <select name="lesson_number" id="lessonSelect" class="form-control">
                            <option value=""><?= __('-- اختر الحصة --') ?></option>
                            <?php foreach (LESSONS as $num => $lesson): ?>
                            <option value="<?= $num ?>"><?= $lesson['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-field" style="margin-bottom: 1.5rem;">
                    <label>📝 <?= __('سبب الغياب') ?> (<?= __('اختياري') ?>)</label>
                    <input type="text" name="reason" class="form-control" 
                           placeholder="مثال: إجازة مرضية، مهمة رسمية، ظرف طارئ...">
                </div>
                
                <button type="submit" class="btn btn-danger" style="padding: 1rem 3rem; font-size: 1.05rem; border-radius: 12px;">
                    <?= __('🚫 تسجيل الغياب') ?>
                </button>
            </form>
        </div>
    </div>

    <!-- قائمة الغيابات -->
    <div class="data-card">
        <div class="data-card-header">
            <h3>📋 الغيابات المسجلة ليوم <?= $arabicDayName ?></h3>
        </div>
        <?php if (empty($absencesByTeacher)): ?>
        <div class="empty-state">
            <div class="icon">✨</div>
            <h3>لا توجد غيابات مسجلة</h3>
            <p>جميع أعضاء الكادر متواجدون اليوم</p>
        </div>
        <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>عضو الكادر</th>
                        <th style="text-align: center;">نوع الغياب</th>
                        <th>الحصص المتأثرة</th>
                        <th>السبب</th>
                        <th style="text-align: center;">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($absencesByTeacher as $teacherId => $data): ?>
                    <tr>
                        <td>
                            <div class="teacher-cell">
                                <div class="teacher-avatar"><?= mb_substr($data['name'], 0, 1) ?></div>
                                <span class="teacher-name"><?= htmlspecialchars($data['name']) ?></span>
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($data['full_day']): ?>
                            <span class="badge badge-fullday">🌙 اليوم كامل</span>
                            <?php else: ?>
                            <span class="badge badge-partial">📚 حصص محددة</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($data['full_day']): ?>
                                <?php if (isset($todaySchedules[$teacherId])): ?>
                                <?php foreach ($todaySchedules[$teacherId]['lessons'] as $lesson): ?>
                                <span class="badge-lesson"><?= LESSONS[$lesson['lesson_number']]['name'] ?? 'ح' . $lesson['lesson_number'] ?></span>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <span style="color: var(--text-muted);">كل الحصص</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php foreach ($data['absences'] as $abs): ?>
                                <span class="badge-lesson"><?= LESSONS[$abs['lesson_number']]['name'] ?? 'ح' . $abs['lesson_number'] ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $reason = $data['full_day'] ? ($data['reason'] ?? '') : ($data['absences'][0]['reason'] ?? '');
                            echo $reason ?: '<span style="color: var(--text-muted);">-</span>';
                            ?>
                        </td>
                        <td style="text-align: center;">
                            <form action="controllers/teacher_absence_handler.php" method="POST" style="display: inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="remove_absence">
                                <input type="hidden" name="teacher_id" value="<?= $teacherId ?>">
                                <input type="hidden" name="date" value="<?= $date ?>">
                                <button type="submit" class="btn btn-success btn-sm" 
                                        onclick="return confirm('هل تريد إلغاء غياب هذا العضو؟')"
                                        style="border-radius: 8px;">
                                    ✅ إلغاء
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- الكادر المتواجد -->
    <?php if (!empty($todaySchedules)): ?>
    <div class="present-section">
        <div class="present-header">
            <h3>✅ الكادر المتواجد اليوم</h3>
        </div>
        <div class="present-body">
            <div class="present-grid">
                <?php foreach ($todaySchedules as $teacherId => $data): ?>
                    <?php if (!isset($absencesByTeacher[$teacherId])): ?>
                    <div class="present-item">
                        <div class="check">✓</div>
                        <div>
                            <div class="name"><?= htmlspecialchars($data['name']) ?></div>
                            <div class="count"><?= toArabicNum(count($data['lessons'])) ?> حصة</div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const absenceType = document.getElementById('absenceType');
    const lessonSelectGroup = document.getElementById('lessonSelectGroup');
    const lessonSelect = document.getElementById('lessonSelect');
    const dateSelector = document.getElementById('dateSelector');
    const absenceForm = document.getElementById('absenceForm');
    
    // إظهار/إخفاء قائمة الحصص
    function toggleLessonSelect() {
        const isSpecific = absenceType.value === 'specific_lesson';
        lessonSelectGroup.style.display = isSpecific ? 'block' : 'none';
        
        // إضافة/إزالة required
        if (isSpecific) {
            lessonSelect.setAttribute('required', 'required');
        } else {
            lessonSelect.removeAttribute('required');
            lessonSelect.value = ''; // إعادة تعيين القيمة
        }
    }
    
    absenceType.addEventListener('change', toggleLessonSelect);
    
    // تشغيل عند تحميل الصفحة
    toggleLessonSelect();
    
    // التحقق قبل الإرسال
    absenceForm.addEventListener('submit', function(e) {
        if (absenceType.value === 'specific_lesson' && !lessonSelect.value) {
            e.preventDefault();
            alert('يرجى اختيار الحصة');
            lessonSelect.focus();
            return false;
        }
        return true;
    });
    
    // تغيير التاريخ
    dateSelector.addEventListener('change', function() {
        const url = new URL(window.location);
        url.searchParams.set('date', this.value);
        window.location.href = url.href;
    });
});
</script>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
