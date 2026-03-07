<?php
/**
 * الجداول الدراسية - Class Schedules
 * عرض جداول الحصص الأسبوعية مع حالة المعلمين
 * 
 * @package SchoolManager
 * @access  جميع المستخدمين (التعديل للمدير فقط)
 */

$pageTitle = 'الجداول الدراسية';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Schedule.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Student.php';
require_once __DIR__ . '/models/Subject.php';
require_once __DIR__ . '/models/TeacherAssignment.php';

requireLogin();

$scheduleModel = new Schedule();
$userModel = new User();
$conn = getConnection();

// للتلميذ: جلب صفه وشعبته تلقائياً
if (isStudent()) {
    $studentModel = new Student();
    $currentUser = getCurrentUser();
    $myInfo = $studentModel->findByUserId($currentUser['id']);
    
    if (!$myInfo) {
        alert('لم يتم العثور على بياناتك', 'error');
        redirect('/student_profile.php');
    }
    
    $classId = $myInfo['class_id'];
    $section = $myInfo['section'];
} else {
    $classId = (int)($_GET['class_id'] ?? 1);
    $section = $_GET['section'] ?? 'أ';
}

$schedule = $scheduleModel->getByClassSection($classId, $section);
$teachers = $userModel->getTeachers();

// جلب تعيينات المعلمين للصف/الشعبة الحالية
$assignmentModel = new TeacherAssignment();
$classSubjects = Subject::getSubjectsByClass($classId);

// بناء خريطة المعلمين المعينين لكل مادة
$assignedTeachers = [];
try {
    $stmt = $conn->prepare("
        SELECT ta.subject_name, ta.teacher_id, u.full_name as teacher_name
        FROM teacher_assignments ta
        JOIN users u ON ta.teacher_id = u.id
        WHERE ta.class_id = ? AND ta.section = ? AND ta.is_active = 1
        ORDER BY ta.subject_name, u.full_name
    ");
    $stmt->execute([$classId, $section]);
    $assignments = $stmt->fetchAll();
    foreach ($assignments as $a) {
        $assignedTeachers[$a['subject_name']][] = [
            'id' => $a['teacher_id'],
            'name' => $a['teacher_name']
        ];
    }
} catch (Exception $e) {
    // الجدول قد لا يكون موجوداً بعد
}

$scheduleGrid = [];
foreach ($schedule as $item) {
    $scheduleGrid[$item['day_of_week']][$item['lesson_number']] = $item;
}

// جلب غيابات المعلمين لليوم
$today = date('Y-m-d');
$todayDayName = date('l');
$dayMapping = [
    'Sunday' => 'الأحد',
    'Monday' => 'الإثنين', 
    'Tuesday' => 'الثلاثاء',
    'Wednesday' => 'الأربعاء',
    'Thursday' => 'الخميس',
    'Friday' => 'الجمعة',
    'Saturday' => 'السبت'
];
$todayArabic = $dayMapping[$todayDayName] ?? '';

// جلب غيابات اليوم
$absentTeachers = [];
try {
    $stmt = $conn->prepare("SELECT teacher_id, lesson_number FROM teacher_absences WHERE date = ?");
    $stmt->execute([$today]);
    $absences = $stmt->fetchAll();
    foreach ($absences as $abs) {
        if ($abs['lesson_number']) {
            $absentTeachers[$abs['teacher_id']][$abs['lesson_number']] = true;
        } else {
            $absentTeachers[$abs['teacher_id']]['all'] = true;
        }
    }
} catch (Exception $e) {
    // الجدول قد لا يكون موجوداً بعد
}

function isTeacherAbsent($teacherId, $lessonNum, $absentTeachers) {
    if (!$teacherId) return false;
    if (isset($absentTeachers[$teacherId]['all'])) return true;
    if (isset($absentTeachers[$teacherId][$lessonNum])) return true;
    return false;
}

require_once __DIR__ . '/views/components/header.php';
?>

<div class="page-header d-flex justify-between align-center flex-wrap gap-2">
    <div>
        <h1><?= __('📅 الجداول الدراسية') ?></h1>
        <p><?= __('الصف') ?> <?= CLASSES[$classId] ?? $classId ?> - <?= __('شعبة') ?> <?= sanitize($section) ?></p>
    </div>
    <?php if (isAdmin()): ?>
    <a href="/schedule_edit.php?class_id=<?= $classId ?>&section=<?= urlencode($section) ?>" class="btn btn-primary">
        <?= __('✏️ تعديل الجدول') ?>
    </a>
    <?php endif; ?>
</div>

<?php if (!isStudent()): ?>
<div class="card mb-3">
    <div class="card-header">
        <form method="GET" class="d-flex gap-2 flex-wrap align-center" id="scheduleViewFilterForm">
            <div class="filter-group">
                <label><?= __('الصف:') ?></label>
                <select name="class_id" id="viewClassId" class="form-control">
                    <?php foreach (CLASSES as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $classId == $id ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label><?= __('الشعبة:') ?></label>
                <select name="section" id="viewSection" class="form-control">
                    <?php foreach (SECTIONS as $sec): ?>
                    <option value="<?= $sec ?>" <?= $section == $sec ? 'selected' : '' ?>><?= $sec ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label><?= __('عرض تاريخ:') ?></label>
                <input type="date" name="view_date" id="viewDate" class="form-control" 
                       value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
            </div>
            
            <div class="filter-group" style="display: none; align-items: flex-end;">
                <button type="button" id="loadViewBtn" class="btn btn-primary btn-sm">
                    🔄 تحميل
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php
// تحديد اليوم المعروض - دائماً اليوم الحالي عند reload
$viewDate = date('Y-m-d');
$viewDayName = date('l', strtotime($viewDate));
$viewDayArabic = $dayMapping[$viewDayName] ?? $todayArabic;
$isViewingToday = true;
?>

<div class="card fade-in" id="scheduleCard">
    <div class="card-header d-flex justify-between align-center flex-wrap gap-2">
        <h3><?= __('📋 الجدول الأسبوعي') ?></h3>
        <div class="d-flex gap-2 align-center">
            <?php if (!$isViewingToday): ?>
            <span class="badge badge-warning">📅 عرض: <?= formatArabicDate($viewDate) ?> (<?= $viewDayArabic ?>)</span>
            <?php endif; ?>
            <span class="badge badge-info">اليوم: <?= $todayArabic ?> - <?= formatArabicDate($today) ?></span>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 100px;"><?= __('الحصة') ?></th>
                        <?php foreach (DAYS as $dayKey => $dayName): 
                            $isHighlighted = ($dayKey === $viewDayArabic);
                        ?>
                        <th class="<?= $isHighlighted ? 'today-column' : '' ?>" 
                            style="<?= $isHighlighted ? 'background: rgba(102, 126, 234, 0.15); border-bottom: 3px solid var(--primary);' : '' ?>">
                            <?= $dayName ?>
                            <?= $isHighlighted ? '📍' : '' ?>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (LESSONS as $lessonNum => $lesson): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600;"><?= $lesson['name'] ?></div>
                            <small class="text-muted"><?= toArabicNum($lesson['start']) ?></small>
                        </td>
                        <?php foreach (DAYS as $dayKey => $dayName): 
                            $isHighlighted = ($dayKey === $viewDayArabic);
                        ?>
                        <td style="<?= $isHighlighted ? 'background: rgba(102, 126, 234, 0.05);' : '' ?>">
                        <?php if (isset($scheduleGrid[$dayKey][$lessonNum])): ?>
                            <?php 
                            $item = $scheduleGrid[$dayKey][$lessonNum];
                            $isAbsent = isTeacherAbsent($item['teacher_id'], $lessonNum, $absentTeachers);
                            $isSelectedDay = $isHighlighted;
                            ?>
                            <div class="schedule-cell" style="background: <?= ($isAbsent && $isSelectedDay) ? 'rgba(239, 68, 68, 0.1)' : 'var(--bg-secondary)' ?>; 
                                        padding: 0.5rem; 
                                        border-radius: var(--radius-sm);
                                        border-right: 3px solid <?= ($isAbsent && $isSelectedDay) ? '#ef4444' : 'var(--primary)' ?>;
                                        position: relative;">
                                <div style="font-weight: 600; color: var(--primary);">
                                    <?= sanitize($item['subject_name']) ?>
                                </div>
                                <?php if ($item['teacher_name']): ?>
                                <div style="display: flex; align-items: center; gap: 0.25rem; margin-top: 0.25rem;">
                                    <?php if ($isAbsent && $isSelectedDay): ?>
                                    <span style="color: #ef4444; font-size: 0.8rem;">🚫 غائب</span>
                                    <?php else: ?>
                                    <small class="text-muted"><?= sanitize($item['teacher_name']) ?></small>
                                    <?php if ($isSelectedDay): ?>
                                    <span style="color: #22c55e; font-size: 0.75rem;">✓</span>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (isAdmin()): ?>
                                <!-- أزرار التعديل والحذف للمدير/المعاون -->
                                <div class="schedule-actions" style="position: absolute; top: 2px; left: 2px; display: flex; gap: 2px; opacity: 0; transition: opacity 0.2s;">
                                    <button type="button" class="btn-mini btn-edit" 
                                            onclick="editScheduleCell(<?= $item['id'] ?>, '<?= sanitize($item['subject_name']) ?>', <?= $item['teacher_id'] ?? 'null' ?>)"
                                            title="تعديل">✏️</button>
                                    <button type="button" class="btn-mini btn-delete" 
                                            onclick="deleteScheduleCell(<?= $item['id'] ?>, '<?= sanitize($item['subject_name']) ?>')"
                                            title="حذف">🗑️</button>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <?php if (isAdmin()): ?>
                            <!-- خلية فارغة - زر إضافة للمدير/المعاون -->
                            <button type="button" class="btn-add-cell" 
                                    onclick="addScheduleCell('<?= $dayKey ?>', <?= $lessonNum ?>, <?= $classId ?>, '<?= $section ?>')"
                                    title="إضافة حصة">
                                <span style="opacity: 0.5;">➕</span>
                            </button>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (isStudent()): ?>
        <div class="mt-3" style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="width: 12px; height: 12px; background: var(--primary); border-radius: 2px;"></span>
                <small>المعلم موجود</small>
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="width: 12px; height: 12px; background: #ef4444; border-radius: 2px;"></span>
                <small>المعلم غائب اليوم</small>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h3><?= __('🕐 أوقات الحصص') ?></h3>
    </div>
    <div class="card-body">
        <div class="grid grid-3">
            <?php foreach (LESSONS as $num => $lesson): ?>
            <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-sm); text-align: center;">
                <div style="font-weight: 600; color: var(--primary); margin-bottom: 0.25rem;"><?= $lesson['name'] ?></div>
                <div style="font-size: 1.1rem;"><?= toArabicNum($lesson['start']) ?> - <?= toArabicNum($lesson['end']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
// ═══════════════════════════════════════════════════════════════
// 📅 تحميل الجدول بـ AJAX بدون reload
// ═══════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', function() {
    const classSelect = document.getElementById('viewClassId');
    const sectionSelect = document.getElementById('viewSection');
    const dateInput = document.getElementById('viewDate');
    
    if (!classSelect || !sectionSelect) return;
    
    // تحميل الجدول بـ AJAX
    function loadScheduleAjax() {
        const classId = classSelect.value;
        const section = sectionSelect.value;
        const viewDate = dateInput ? dateInput.value : '';
        
        // تحديث URL بدون reload
        const url = new URL(window.location);
        url.searchParams.set('class_id', classId);
        url.searchParams.set('section', section);
        if (viewDate) url.searchParams.set('view_date', viewDate);
        window.history.pushState({}, '', url);
        
        // إظهار مؤشر التحميل
        const scheduleCard = document.getElementById('scheduleCard');
        if (scheduleCard) {
            scheduleCard.style.opacity = '0.5';
        }
        
        // طلب AJAX
        fetch(url.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // تحديث الجدول
            const newCard = doc.getElementById('scheduleCard');
            const currentCard = document.getElementById('scheduleCard');
            if (newCard && currentCard) {
                currentCard.outerHTML = newCard.outerHTML;
            }
            
            // تحديث عنوان الصفحة
            const newHeader = doc.querySelector('.page-header p');
            const currentHeader = document.querySelector('.page-header p');
            if (newHeader && currentHeader) {
                currentHeader.innerHTML = newHeader.innerHTML;
            }
            
            // تحديث رابط التعديل
            const newEditBtn = doc.querySelector('.page-header a.btn-primary');
            const currentEditBtn = document.querySelector('.page-header a.btn-primary');
            if (newEditBtn && currentEditBtn) {
                currentEditBtn.href = newEditBtn.href;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // في حالة الخطأ، reload عادي
            window.location.href = url.href;
        })
        .finally(() => {
            const card = document.getElementById('scheduleCard');
            if (card) card.style.opacity = '1';
        });
    }
    
    // ربط الفلاتر بـ AJAX
    classSelect.addEventListener('change', loadScheduleAjax);
    sectionSelect.addEventListener('change', loadScheduleAjax);
    if (dateInput) {
        dateInput.addEventListener('change', loadScheduleAjax);
    }
});
</script>

<?php if (isAdmin()): ?>
<!-- Modal لإضافة/تعديل حصة -->
<div id="scheduleModal" class="modal" style="display: none;">
    <div class="modal-overlay" onclick="closeScheduleModal()"></div>
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3 id="modalTitle">➕ إضافة حصة</h3>
            <button class="modal-close" onclick="closeScheduleModal()">&times;</button>
        </div>
        <form id="scheduleForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="scheduleId">
            <input type="hidden" name="class_id" id="formClassId" value="<?= $classId ?>">
            <input type="hidden" name="section" id="formSection" value="<?= $section ?>">
            <input type="hidden" name="day_of_week" id="formDay">
            <input type="hidden" name="lesson_number" id="formLesson">
            
            <div class="form-group">
                <label>المادة:</label>
                <select name="subject_select" id="subjectSelect" class="form-control" onchange="handleSubjectSelect(this)">
                    <option value="">-- اختر المادة --</option>
                    <?php 
                    require_once __DIR__ . '/models/Subject.php';
                    $subjects = Subject::getSubjectsByClass($classId);
                    foreach ($subjects as $subj): ?>
                    <option value="<?= sanitize($subj) ?>"><?= sanitize($subj) ?></option>
                    <?php endforeach; ?>
                    <option value="__custom__">📝 مادة أخرى (كتابة يدوية)...</option>
                </select>
            </div>
            
            <div class="form-group" id="customSubjectGroup" style="display: none;">
                <label>اسم المادة:</label>
                <input type="text" name="custom_subject" id="customSubject" class="form-control" placeholder="أدخل اسم المادة...">
            </div>
            
            <input type="hidden" name="subject_name" id="subjectName">
            
            <div class="form-group">
                <label>المعلم:
                    <span id="assignedHint" style="font-weight: normal; color: var(--success); font-size: 0.85rem; display: none;">
                        ✓ معين لهذه المادة
                    </span>
                </label>
                <select name="teacher_id" id="teacherSelect" class="form-control">
                    <option value="">-- اختر المعلم --</option>
                    <?php foreach ($teachers as $teacher): ?>
                    <option value="<?= $teacher['id'] ?>" data-name="<?= sanitize($teacher['full_name']) ?>">
                        <?= sanitize($teacher['full_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small id="noAssignedMsg" style="color: var(--warning); display: none;">
                    ⚠️ لا يوجد معلم معين لهذه المادة - <a href="teacher_assignments.php" target="_blank">تعيين معلم</a>
                </small>
            </div>
            
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary" id="submitBtn">💾 حفظ</button>
                <button type="button" class="btn btn-secondary" onclick="closeScheduleModal()">إلغاء</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Styles -->
<style>
.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}
.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
}
.modal-content {
    position: relative;
    background: var(--bg-primary);
    border-radius: var(--radius-lg);
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalSlideIn 0.3s ease;
}
@keyframes modalSlideIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}
.modal-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-header h3 { margin: 0; }
.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-muted);
    padding: 0;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.modal-close:hover {
    background: var(--bg-secondary);
    color: var(--text-primary);
}
.modal-content form { padding: 1.5rem; }
.modal-footer {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-start;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
    margin-top: 1rem;
}
.btn-add-cell {
    width: 100%;
    height: 60px;
    border: 2px dashed var(--border-color);
    background: transparent;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.btn-add-cell:hover {
    border-color: var(--primary);
    background: rgba(102, 126, 234, 0.05);
}
.btn-mini {
    padding: 2px 6px;
    font-size: 0.7rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-mini.btn-edit { background: #dbeafe; }
.btn-mini.btn-delete { background: #fee2e2; }
.btn-mini:hover { transform: scale(1.1); }
.schedule-cell:hover .schedule-actions { opacity: 1 !important; }
</style>

<script>
// ═══════════════════════════════════════════════════════════════
// 📅 دوال إدارة الجدول الدراسي
// ═══════════════════════════════════════════════════════════════

// فتح Modal للإضافة
function addScheduleCell(day, lesson, classId, section) {
    document.getElementById('modalTitle').textContent = '➕ إضافة حصة';
    document.getElementById('formAction').value = 'add';
    document.getElementById('scheduleId').value = '';
    document.getElementById('formClassId').value = classId;
    document.getElementById('formSection').value = section;
    document.getElementById('formDay').value = day;
    document.getElementById('formLesson').value = lesson;
    document.getElementById('subjectSelect').value = '';
    document.getElementById('customSubject').value = '';
    document.getElementById('customSubjectGroup').style.display = 'none';
    document.getElementById('teacherSelect').value = '';
    document.getElementById('submitBtn').textContent = '➕ إضافة';
    
    document.getElementById('scheduleModal').style.display = 'flex';
}

// فتح Modal للتعديل
function editScheduleCell(id, subjectName, teacherId) {
    document.getElementById('modalTitle').textContent = '✏️ تعديل الحصة';
    document.getElementById('formAction').value = 'update';
    document.getElementById('scheduleId').value = id;
    
    // التحقق إذا كانت المادة من القائمة أم مخصصة
    const subjectSelect = document.getElementById('subjectSelect');
    let found = false;
    for (let i = 0; i < subjectSelect.options.length; i++) {
        if (subjectSelect.options[i].value === subjectName) {
            subjectSelect.value = subjectName;
            found = true;
            break;
        }
    }
    
    if (!found && subjectName) {
        subjectSelect.value = '__custom__';
        document.getElementById('customSubject').value = subjectName;
        document.getElementById('customSubjectGroup').style.display = 'block';
    } else {
        document.getElementById('customSubjectGroup').style.display = 'none';
        // تحديث قائمة المعلمين بناءً على المادة
        handleSubjectSelect(subjectSelect);
    }
    
    // تحديد المعلم الحالي بعد تحديث القائمة
    setTimeout(() => {
        document.getElementById('teacherSelect').value = teacherId || '';
    }, 50);
    
    document.getElementById('submitBtn').textContent = '💾 حفظ التعديل';
    
    document.getElementById('scheduleModal').style.display = 'flex';
}

// حذف حصة
function deleteScheduleCell(id, subjectName) {
    if (!confirm('هل تريد حذف حصة "' + subjectName + '"؟')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    formData.append('csrf_token', document.querySelector('#scheduleForm input[name="csrf_token"]').value);
    
    fetch('/controllers/schedule_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            notifySuccess('تم حذف الحصة بنجاح');
            setTimeout(() => location.reload(), 500);
        } else {
            notifyError(data.error || 'حدث خطأ أثناء الحذف');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        notifyError('خطأ في الاتصال');
    });
}

// إغلاق Modal
function closeScheduleModal() {
    document.getElementById('scheduleModal').style.display = 'none';
}

// خريطة المعلمين المعينين لكل مادة (من PHP)
const assignedTeachersMap = <?= json_encode($assignedTeachers, JSON_UNESCAPED_UNICODE) ?>;

// جميع المعلمين
const allTeachers = [
    <?php foreach ($teachers as $teacher): ?>
    { id: <?= $teacher['id'] ?>, name: "<?= sanitize($teacher['full_name']) ?>" },
    <?php endforeach; ?>
];

// التعامل مع اختيار المادة
function handleSubjectSelect(select) {
    const customGroup = document.getElementById('customSubjectGroup');
    const teacherSelect = document.getElementById('teacherSelect');
    const assignedHint = document.getElementById('assignedHint');
    const noAssignedMsg = document.getElementById('noAssignedMsg');
    
    // إظهار/إخفاء حقل المادة المخصصة
    if (select.value === '__custom__') {
        customGroup.style.display = 'block';
        document.getElementById('customSubject').focus();
        assignedHint.style.display = 'none';
        noAssignedMsg.style.display = 'none';
        
        // إظهار جميع المعلمين + خيار لا معلم
        teacherSelect.innerHTML = '<option value="">🚫 لا معلم (فراغ)</option>';
        allTeachers.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.name;
            teacherSelect.appendChild(opt);
        });
        return;
    } else {
        customGroup.style.display = 'none';
    }
    
    // تحديث قائمة المعلمين بناءً على المادة المختارة
    const selectedSubject = select.value;
    const assignedForSubject = assignedTeachersMap[selectedSubject] || [];
    const assignedIds = assignedForSubject.map(t => t.id);
    
    // مسح الخيارات الحالية
    teacherSelect.innerHTML = '<option value="">-- اختر المعلم --</option>';
    
    if (selectedSubject && assignedForSubject.length > 0) {
        // إضافة مجموعة المعلمين المعينين أولاً
        const assignedGroup = document.createElement('optgroup');
        assignedGroup.label = '⭐ معينون لهذه المادة';
        assignedForSubject.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = '⭐ ' + t.name;
            opt.style.fontWeight = 'bold';
            opt.style.color = '#059669';
            assignedGroup.appendChild(opt);
        });
        teacherSelect.appendChild(assignedGroup);
        
        // إضافة باقي المعلمين
        const othersGroup = document.createElement('optgroup');
        othersGroup.label = 'معلمون آخرون';
        allTeachers.forEach(t => {
            if (!assignedIds.includes(t.id)) {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = t.name;
                othersGroup.appendChild(opt);
            }
        });
        if (othersGroup.children.length > 0) {
            teacherSelect.appendChild(othersGroup);
        }
        
        // تحديد أول معلم معين تلقائياً
        teacherSelect.value = assignedForSubject[0].id;
        assignedHint.style.display = 'inline';
        noAssignedMsg.style.display = 'none';
    } else if (selectedSubject) {
        // لا يوجد معلمين معينين - إظهار الجميع
        allTeachers.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.name;
            teacherSelect.appendChild(opt);
        });
        assignedHint.style.display = 'none';
        noAssignedMsg.style.display = 'block';
    } else {
        // لم يتم اختيار مادة
        allTeachers.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.name;
            teacherSelect.appendChild(opt);
        });
        assignedHint.style.display = 'none';
        noAssignedMsg.style.display = 'none';
    }
}

// إرسال النموذج
document.getElementById('scheduleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const action = document.getElementById('formAction').value;
    const subjectSelect = document.getElementById('subjectSelect').value;
    let subjectName = '';
    
    if (subjectSelect === '__custom__') {
        subjectName = document.getElementById('customSubject').value.trim();
        if (!subjectName) {
            notifyWarning('يرجى إدخال اسم المادة');
            return;
        }
    } else if (subjectSelect) {
        subjectName = subjectSelect;
    } else {
        notifyWarning('يرجى اختيار المادة');
        return;
    }
    
    document.getElementById('subjectName').value = subjectName;
    
    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = '⏳ جارٍ الحفظ...';
    submitBtn.disabled = true;
    
    const formData = new FormData(this);
    
    fetch('/controllers/schedule_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            notifySuccess(action === 'add' ? 'تمت إضافة الحصة بنجاح' : 'تم تحديث الحصة بنجاح');
            closeScheduleModal();
            setTimeout(() => location.reload(), 500);
        } else {
            notifyError(data.error || 'حدث خطأ');
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        notifyError('خطأ في الاتصال');
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
});

// إغلاق Modal بزر Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeScheduleModal();
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
