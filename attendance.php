<?php
/**
 * تسجيل الحضور - Attendance Registration
 * صفحة تسجيل حضور وغياب التلاميذ
 * 
 * @package SchoolManager
 * @access  معلم + معاون معيّن كمعلم (المدير والمعاون العادي للمشاهدة فقط)
 * @security صلاحية التسجيل للمعلمين فقط
 */

$pageTitle = 'تسجيل الحضور';
$extraScripts = ['/assets/js/attendance.js'];

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/models/Student.php';
require_once __DIR__ . '/models/Attendance.php';
require_once __DIR__ . '/models/Schedule.php';

// التحقق من تسجيل الدخول أولاً
requireLogin();

// التلميذ لا يمكنه الوصول لهذه الصفحة
if (isStudent()) {
    alert('ليس لديك صلاحية للوصول لهذه الصفحة', 'error');
    redirect('/student_profile.php');
}

$studentModel = new Student();
$attendanceModel = new Attendance();
$scheduleModel = new Schedule();

// ═══════════════════════════════════════════════════════════════
// 🔐 فلترة الصفوف والشعب للمعلم
// ═══════════════════════════════════════════════════════════════
$allowedClasses = filterClassesForTeacher(CLASSES);
$isAdminOrAssistant = isMainAdmin() || isAssistant();

// إذا كان المعلم ولا يوجد لديه تعيينات
if (isTeacher() && empty($allowedClasses)) {
    alert('⚠️ لم يتم تعيينك لأي صفوف بعد', 'warning', 'تواصل مع مدير المدرسة لتعيينك للصفوف والمواد.');
    redirect('/dashboard.php');
}

// الفلاتر - للمدير/المعاون: افتراضياً "الكل"، للمعلم: أول صف معين
$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : ($isAdminOrAssistant ? 0 : (isTeacher() ? array_key_first($allowedClasses) : 1));
$section = isset($_GET['section']) ? $_GET['section'] : ($isAdminOrAssistant ? '' : 'أ');
$date = $_GET['date'] ?? date('Y-m-d');

// التحقق من أن المعلم مخصص لهذا الصف/الشعبة
if (isTeacher() && $classId > 0 && !empty($section) && !isTeacherAssignedToClass($classId, $section)) {
    // محاولة إيجاد صف/شعبة مسموحة
    $assignedClasses = getTeacherAssignedClasses();
    if (!empty($assignedClasses)) {
        $classId = $assignedClasses[0]['class_id'];
        $section = $assignedClasses[0]['section'];
    }
}

$allowedSections = filterSectionsForTeacher($classId, SECTIONS);

// جلب الطلاب - للمدير/المعاون: جميع الطلاب إذا لم يُحدد صف
$students = $studentModel->getAll($classId ?: null, $section ?: null);
$existingAttendance = $classId && $section ? $attendanceModel->getByDate($classId, $section, $date) : [];

$attendanceMap = [];
foreach ($existingAttendance as $record) {
    $key = $record['student_id'] . '_' . $record['lesson_number'];
    $attendanceMap[$key] = $record['status'];
}

$dayOfWeek = strtolower(date('l', strtotime($date)));
$todaySchedule = $scheduleModel->getByDay($classId, $section, $dayOfWeek);

$currentLesson = getCurrentLesson();

// فحص عطلة نهاية الأسبوع (الجمعة والسبت)
$isWeekendDay = isWeekend($date);

// هل لديه صلاحية تسجيل الحضور؟
$canEditAttendance = canRecordAttendanceData();
$canRecord = $canEditAttendance && canRecordAttendance($date);
$arabicDayName = getArabicDayName($date);

// إذا كان المستخدم لا يملك صلاحية التسجيل أصلاً
$isViewOnly = !$canEditAttendance;

require_once __DIR__ . '/views/components/header.php';
?>

<div class="page-header d-flex justify-between align-center flex-wrap gap-2">
    <div>
        <h1><?= __('تسجيل الحضور') ?></h1>
        <p><?= __('الصف') ?> <?= CLASSES[$classId] ?? $classId ?> - <?= __('شعبة') ?> <?= sanitize($section) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($currentLesson): ?>
        <div class="current-lesson">
            <span>🕐</span>
            <span><?= LESSONS[$currentLesson]['name'] ?></span>
        </div>
        <?php endif; ?>
        
        <!-- أزرار التصدير -->
        <a href="reports.php?class_id=<?= $classId ?>&section=<?= urlencode($section) ?>" class="btn btn-info btn-sm"><?= __('📊 التقارير') ?></a>
    </div>
</div>

<div class="attendance-grid fade-in">
    <div class="attendance-header">
        <form method="GET" class="attendance-filters" id="attendanceFilterForm">
            <div class="filter-group">
                <label><?= __('الصف:') ?></label>
                <select name="class_id" id="filterClassId" class="form-control">
                    <?php if ($isAdminOrAssistant): ?>
                    <option value="0" <?= $classId == 0 ? 'selected' : '' ?>>📚 الكل</option>
                    <?php endif; ?>
                    <?php foreach ($allowedClasses as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $classId == $id ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label><?= __('الشعبة:') ?></label>
                <select name="section" id="filterSection" class="form-control">
                    <?php if ($isAdminOrAssistant): ?>
                    <option value="" <?= $section == '' ? 'selected' : '' ?>>📋 الكل</option>
                    <?php endif; ?>
                    <?php foreach ($allowedSections as $sec): ?>
                    <option value="<?= $sec ?>" <?= $section == $sec ? 'selected' : '' ?>><?= $sec ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label><?= __('التاريخ:') ?></label>
                <input type="date" name="date" id="filterDate" class="form-control" value="<?= $date ?>">
            </div>
            
            <div class="filter-group">
                <label><?= __('🔍 بحث:') ?></label>
                <input type="text" id="studentSearch" class="form-control" placeholder="<?= __('ابحث عن طالب...') ?>" autocomplete="off" style="min-width: 180px;">
            </div>
            
            <div class="filter-group" style="display: none; align-items: flex-end;">
                <button type="button" id="loadAttendanceBtn" class="btn btn-primary btn-sm" onclick="loadAttendanceData()">
                    🔄 تحميل
                </button>
            </div>
        </form>
        
        <div class="attendance-date" id="attendanceDateDisplay">
            <h3><?= formatArabicDate($date) ?> (<?= $arabicDayName ?>)</h3>
        </div>
    </div>
    
    <?php if ($isWeekendDay): ?>
    <div class="weekend-alert" style="background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%); border: 1px solid #ffc107; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 1rem; box-shadow: 0 4px 15px rgba(255, 193, 7, 0.2);">
        <div style="font-size: 2.5rem;">🏖️</div>
        <div style="flex: 1;">
            <h4 style="margin: 0 0 0.5rem 0; color: #856404; font-size: 1.2rem;"><?= __('عطلة نهاية الأسبوع - يوم') ?> <?= $arabicDayName ?></h4>
            <p style="margin: 0; color: #856404;"><?= __('لا يمكن تسجيل الحضور في أيام العطلة.') ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (empty($students)): ?>
    <div class="card-body">
        <div class="empty-state">
            <div class="icon">👨‍🎓</div>
            <h3><?= __('لا يوجد تلاميذ في هذا الصف') ?></h3>
            <p><?= __('قم بإضافة تلاميذ لهذا الصف والشعبة أولاً') ?></p>
            <?php if (isAdmin()): ?>
            <a href="students.php?action=add" class="btn btn-primary mt-2"><?= __('إضافة تلميذ') ?></a>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    
    <form id="attendanceForm" action="controllers/attendance_handler.php" method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="class_id" value="<?= $classId ?>">
        <input type="hidden" name="section" value="<?= $section ?>">
        <input type="hidden" name="date" value="<?= $date ?>">
        
        <div class="table-responsive">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th style="width: 200px;"><?= __('التلميذ') ?></th>
                        <?php foreach (LESSONS as $num => $lesson): ?>
                        <th>
                            <div><?= $lesson['name'] ?></div>
                            <small style="color: var(--text-muted); font-weight: 400;"><?= $lesson['start'] ?></small>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $counter = 1; foreach ($students as $student): ?>
                    <tr>
                        <td>
                            <div class="student-info">
                                <?php if ($student['photo']): ?>
                                <img src="uploads/students/<?= $student['photo'] ?>" alt="" class="student-photo">
                                <?php else: ?>
                                <div class="student-photo" style="display: flex; align-items: center; justify-content: center; background: var(--primary); color: white; font-weight: 600;">
                                    <?= mb_substr($student['full_name'], 0, 1) ?>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <div class="student-name"><?= sanitize($student['full_name']) ?></div>
                                    <div class="student-number">#<?= $counter++ ?> <code style="font-size: 0.7rem; opacity: 0.7;">ID:<?= $student['id'] ?></code></div>
                                </div>
                            </div>
                        </td>
                        <?php foreach (LESSONS as $num => $lesson): ?>
                        <?php $key = $student['id'] . '_' . $num; $currentStatus = $attendanceMap[$key] ?? ''; ?>
                        <td>
                            <div class="status-group">
                                <?php foreach (ATTENDANCE_STATUS as $status => $info): ?>
                                <button type="button" 
                                        class="status-btn <?= $status ?> <?= $currentStatus === $status ? 'active' : '' ?>"
                                        data-student="<?= $student['id'] ?>"
                                        data-lesson="<?= $num ?>"
                                        data-status="<?= $status ?>"
                                        title="<?= $info['label'] ?>">
                                    <?= $info['icon'] ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="attendance-legend">
            <?php foreach (ATTENDANCE_STATUS as $status => $info): ?>
            <div class="legend-item">
                <span class="legend-icon"><?= $info['icon'] ?></span>
                <span><?= $info['label'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="attendance-stats">
            <div class="stat-item present">
                <div class="number">0</div>
                <div class="label"><?= __('حاضر') ?></div>
            </div>
            <div class="stat-item late">
                <div class="number">0</div>
                <div class="label"><?= __('متأخر') ?></div>
            </div>
            <div class="stat-item excused">
                <div class="number">0</div>
                <div class="label"><?= __('غائب معذور') ?></div>
            </div>
            <div class="stat-item absent">
                <div class="number">0</div>
                <div class="label"><?= __('غائب') ?></div>
            </div>
        </div>
        
        <div class="attendance-actions">
            <?php if ($canRecord): ?>
            <button type="submit" class="btn btn-primary btn-lg">
                <?= __('💾 حفظ الحضور') ?>
            </button>
            <?php else: ?>
            <button type="button" class="btn btn-secondary btn-lg" disabled style="opacity: 0.6; cursor: not-allowed;">
                <?= __('🚫 لا يمكن التسجيل') ?>
            </button>
            <?php endif; ?>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
    // تمرير حالة إمكانية التسجيل للجافاسكريبت
    window.canRecordAttendance = <?= $canRecord ? 'true' : 'false' ?>;
    
    // ═══════════════════════════════════════════════════════════════
    // 🔄 تحميل بيانات الحضور عبر AJAX
    // ═══════════════════════════════════════════════════════════════
    function loadAttendanceData() {
        const classId = document.getElementById('filterClassId').value;
        const section = document.getElementById('filterSection').value;
        const date = document.getElementById('filterDate').value;
        
        // تحديث URL بدون إعادة تحميل
        const url = new URL(window.location);
        url.searchParams.set('class_id', classId);
        url.searchParams.set('section', section);
        url.searchParams.set('date', date);
        window.history.pushState({}, '', url);
        
        // إظهار مؤشر التحميل
        const btn = document.getElementById('loadAttendanceBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '⏳ جاري التحميل...';
        btn.disabled = true;
        
        // تحميل الصفحة عبر AJAX
        fetch(url.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.text())
        .then(html => {
            // استخراج محتوى الجدول فقط
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // تحديث الجدول
            const newTable = doc.querySelector('.table-responsive') || doc.querySelector('.attendance-table')?.closest('.table-responsive');
            const currentTable = document.querySelector('.table-responsive');
            if (newTable && currentTable) {
                currentTable.innerHTML = newTable.innerHTML;
            }
            
            // تحديث العرض الفارغ أو رسالة العطلة
            const newContent = doc.querySelector('.attendance-grid .card-body') || doc.querySelector('.weekend-alert');
            const currentContent = document.querySelector('.attendance-grid');
            
            // تحديث معلومات التاريخ
            const newDateDisplay = doc.querySelector('#attendanceDateDisplay, .attendance-date');
            const currentDateDisplay = document.getElementById('attendanceDateDisplay');
            if (newDateDisplay && currentDateDisplay) {
                currentDateDisplay.innerHTML = newDateDisplay.innerHTML;
            }
            
            // إعادة تهيئة أزرار الحالة
            if (typeof updateStats === 'function') {
                updateStats();
            }
            
            // إظهار رسالة نجاح
            if (window.SchoolAjax && window.SchoolAjax.toast) {
                window.SchoolAjax.toast('تم تحميل البيانات ✓', 'success');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // في حالة الخطأ، إعادة تحميل الصفحة
            window.location.href = url.href;
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
    
    // تحميل تلقائي عند تغيير أي فلتر
    document.addEventListener('DOMContentLoaded', function() {
        const filters = ['filterClassId', 'filterSection', 'filterDate'];
        filters.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', loadAttendanceData);
            }
        });
        
        // البحث المباشر في أسماء الطلاب
        const searchInput = document.getElementById('studentSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.trim().toLowerCase();
                const rows = document.querySelectorAll('.attendance-table tbody tr');
                let visibleCount = 0;
                
                rows.forEach(function(row) {
                    const studentName = row.querySelector('.student-name');
                    if (studentName) {
                        const name = studentName.textContent.toLowerCase();
                        if (searchTerm === '' || name.includes(searchTerm)) {
                            row.style.display = '';
                            visibleCount++;
                        } else {
                            row.style.display = 'none';
                        }
                    }
                });
                
                // عرض عدد النتائج
                const resultsInfo = document.getElementById('searchResults');
                if (resultsInfo) {
                    resultsInfo.textContent = searchTerm ? `(${visibleCount} نتيجة)` : '';
                }
            });
            
            // مسح البحث بالضغط على Escape
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    this.value = '';
                    this.dispatchEvent(new Event('input'));
                }
            });
        }
    });
    
    // إذا لم يكن مسموحاً بالتسجيل، أضف class للتعطيل
    <?php if (!$canRecord): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const statusButtons = document.querySelectorAll('.status-btn');
        statusButtons.forEach(function(btn) {
            btn.style.pointerEvents = 'none';
            btn.style.opacity = '0.5';
            btn.style.cursor = 'not-allowed';
        });
        
        // إضافة تنبيه عند محاولة النقر
        const table = document.querySelector('.attendance-table');
        if (table) {
            table.addEventListener('click', function(e) {
                if (e.target.classList.contains('status-btn') || e.target.closest('.status-btn')) {
                    alert('لا يمكنك تسجيل الحضور في أيام العطلة. فقط المدير والمعاون يمكنهم التعديل.');
                }
            });
        }
    });
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
