<?php
/**
 * تقرير الدرجات - Grades Report
 * عرض نتائج التلاميذ وإحصائيات الصف
 * 
 * @package SchoolManager
 * @access  للجميع (مع قيود حسب الدور)
 */

$pageTitle = 'تقرير الدرجات';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/models/Subject.php';
require_once __DIR__ . '/models/Grade.php';
require_once __DIR__ . '/models/Student.php';
require_once __DIR__ . '/models/TeacherAssignment.php';

requireLogin();

$subjectModel = new Subject();
$gradeModel = new Grade();
$studentModel = new Student();
$assignmentModel = new TeacherAssignment();
$currentUser = getCurrentUser();

// ═══════════════════════════════════════════════════════════════
// 🔒 تحديد الصفوف والشعب المتاحة حسب الدور
// ═══════════════════════════════════════════════════════════════
$availableClasses = [];
$availableSections = [];
$teacherClasses = [];
$sectionsByClass = [];
$isAdminOrAssistant = isMainAdmin() || isAssistant();

if ($isAdminOrAssistant) {
    // المدير والمعاون يرون جميع الصفوف والشعب
    $availableClasses = array_keys(CLASSES);
    $availableSections = SECTIONS;
} elseif (!isStudent()) {
    // المعلم يرى فقط الصفوف والشعب المعينة له
    $teacherClasses = $assignmentModel->getClassesForTeacher($currentUser['id']);
    foreach ($teacherClasses as $tc) {
        if (!in_array($tc['class_id'], $availableClasses)) {
            $availableClasses[] = $tc['class_id'];
        }
        if (!in_array($tc['section'], $availableSections)) {
            $availableSections[] = $tc['section'];
        }
        // تجميع الشعب حسب الصف
        if (!isset($sectionsByClass[$tc['class_id']])) {
            $sectionsByClass[$tc['class_id']] = [];
        }
        if (!in_array($tc['section'], $sectionsByClass[$tc['class_id']])) {
            $sectionsByClass[$tc['class_id']][] = $tc['section'];
        }
    }
}

// الفلاتر مع دعم "الكل"
$classId = isset($_GET['class_id']) && $_GET['class_id'] !== '' ? (int)$_GET['class_id'] : 0; // 0 = الكل
$section = $_GET['section'] ?? ''; // فارغ = الكل
$term = $_GET['term'] ?? 'first';
$academicYear = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? ''; // للنظام الشهري

// للمعلم: التحقق من أن الصف المختار مسموح له
if (!$isAdminOrAssistant && !isStudent() && $classId != 0 && !in_array($classId, $availableClasses)) {
    $classId = !empty($availableClasses) ? $availableClasses[0] : 0;
}

// للمعلم: تحديد الشعب المتاحة بناءً على الصف المختار
if (!$isAdminOrAssistant && !isStudent() && $classId && isset($sectionsByClass[$classId])) {
    $availableSections = $sectionsByClass[$classId];
}

// للمعلم: إذا الشعبة غير مسموحة للصف المختار، نعيدها لأول شعبة متاحة
if (!$isAdminOrAssistant && !isStudent() && !empty($section) && !in_array($section, $availableSections)) {
    $section = !empty($availableSections) ? $availableSections[0] : '';
}

// التحقق من نوع النظام (شهري للصفوف 5 و 6)
$isMonthlySystem = usesMonthlyGradeSystem($classId);

// الأشهر الدراسية
$academicMonths = [
    9 => 'أيلول (سبتمبر)',
    10 => 'تشرين الأول (أكتوبر)',
    11 => 'تشرين الثاني (نوفمبر)',
    12 => 'كانون الأول (ديسمبر)',
    1 => 'كانون الثاني (يناير)',
    2 => 'شباط (فبراير)',
    3 => 'آذار (مارس)',
    4 => 'نيسان (أبريل)',
    5 => 'أيار (مايو)'
];

// إذا كان المستخدم طالب، يرى نتائجه فقط
$student = null;
if (isStudent()) {
    $student = $studentModel->findByUserId($currentUser['id']);
    if (!$student) {
        alert('لم يتم ربط حسابك بتلميذ', 'error');
        redirect('/dashboard.php');
    }
    // استخدام بيانات الطالب
    $classId = $student['class_id'];
    $section = $student['section'];
}

// الحصول على المواد حسب الصف (إذا تم تحديد صف معين)
$subjects = $classId ? Subject::getSubjectsByClass($classId) : [];
$maxGrade = $classId ? Subject::getMaxGrade($classId) : 100;
$passingGrade = $classId ? Subject::getPassingGrade($classId) : 50;

// الحصول على النتائج
if (isStudent() && $student) {
    // للطالب: نحصل على نتائجه فقط مباشرة
    $studentClassId = $student['class_id'];
    
    // اختيار النظام المناسب حسب الصف
    if (usesMonthlyGradeSystem($studentClassId)) {
        // النظام الشهري للصفين الخامس والسادس
        $studentResult = $gradeModel->calculateMonthlyResult($student['id'], $studentClassId, $academicYear);
    } else {
        // النظام العادي للصفوف 1-4
        $studentResult = $gradeModel->calculateResult($student['id'], $studentClassId, $term, $academicYear);
    }
    
    $studentResult['student'] = $student;
    $results = [$studentResult];
    $stats = ['total_students' => 1, 'passed' => 0, 'failed' => 0, 'supplementary' => 0, 'no_grades' => 0];
} else {
    // للمدير/المعلم: نحصل على النتائج حسب الفلاتر
    $filterClassId = $classId ?: null;
    $filterSection = $section ?: null;
    $filterTerm = $term ?: null;
    
    // إذا كان الصف المحدد يستخدم النظام الشهري، نجلب النتائج الشهرية
    if ($classId && usesMonthlyGradeSystem($classId)) {
        $results = getMonthlyClassResults($gradeModel, $studentModel, $classId, $filterSection, $academicYear);
        $stats = calculateMonthlyStats($results);
    } elseif ($isAdminOrAssistant) {
        // المدير/المعاون يرى جميع الطلاب
        $results = $gradeModel->getClassResults($filterClassId, $filterSection, $filterTerm, $academicYear);
        $stats = $gradeModel->getClassStatistics($filterClassId, $filterSection, $filterTerm, $academicYear);
    } else {
        // المعلم: جلب نتائج الطلاب من الصفوف/الشعب المعينة له فقط
        $results = [];
        
        if ($classId && $section) {
            // صف وشعبة محددة
            $results = $gradeModel->getClassResults($classId, $section, $filterTerm, $academicYear);
        } elseif ($classId) {
            // صف محدد، الشعب المعينة له
            foreach ($availableSections as $sec) {
                $classResults = $gradeModel->getClassResults($classId, $sec, $filterTerm, $academicYear);
                $results = array_merge($results, $classResults);
            }
        } else {
            // "الكل" - جميع الصفوف والشعب المعينة للمعلم
            foreach ($teacherClasses as $tc) {
                $classResults = $gradeModel->getClassResults($tc['class_id'], $tc['section'], $filterTerm, $academicYear);
                $results = array_merge($results, $classResults);
            }
        }
        
        // حساب الإحصائيات
        $stats = [
            'total_students' => count($results),
            'passed' => 0,
            'failed' => 0,
            'supplementary' => 0,
            'no_grades' => 0
        ];
        foreach ($results as $result) {
            switch ($result['status']) {
                case 'pass': $stats['passed']++; break;
                case 'fail': $stats['failed']++; break;
                case 'supp': $stats['supplementary']++; break;
                default: $stats['no_grades']++;
            }
        }
    }
}

// دالة مساعدة لجلب نتائج الصف للنظام الشهري
function getMonthlyClassResults($gradeModel, $studentModel, $classId, $section, $academicYear) {
    $students = $studentModel->getAll($classId, $section);
    $results = [];
    
    foreach ($students as $student) {
        $result = $gradeModel->calculateMonthlyResult($student['id'], $classId, $academicYear);
        $result['student'] = $student;
        $results[] = $result;
    }
    
    return $results;
}

// دالة مساعدة لحساب إحصائيات النظام الشهري
function calculateMonthlyStats($results) {
    $stats = [
        'total_students' => count($results),
        'passed' => 0,
        'failed' => 0,
        'supplementary' => 0,
        'no_grades' => 0
    ];
    
    foreach ($results as $result) {
        switch ($result['status']) {
            case 'pass':
                $stats['passed']++;
                break;
            case 'fail':
                $stats['failed']++;
                break;
            case 'supp':
                $stats['supplementary']++;
                break;
            default:
                $stats['no_grades']++;
        }
    }
    
    return $stats;
}


// أسماء الفترات
$termNames = [
    'first' => 'الفصل الأول',
    'second' => 'الفصل الثاني',
    'final' => 'النهائي'
];

require_once __DIR__ . '/views/components/header.php';
?>

<div class="page-header d-flex justify-between align-center flex-wrap gap-2">
    <div>
        <h1><?= __('📊 تقرير الدرجات') ?></h1>
        <p>
            <?php if ($classId): ?>
                الصف <?= CLASSES[$classId] ?? $classId ?> 
                <?php if ($section): ?>- شعبة <?= sanitize($section) ?><?php else: ?>- جميع الشعب<?php endif; ?>
                <?php if ($isMonthlySystem && $month): ?>
                    - <?= $academicMonths[$month] ?? "شهر $month" ?>
                <?php elseif ($term): ?>
                    - <?= $termNames[$term] ?? $term ?>
                <?php else: ?>
                    - جميع الفترات
                <?php endif; ?>
            <?php else: ?>
                جميع الصفوف 
                <?php if ($section): ?>- شعبة <?= sanitize($section) ?><?php endif; ?>
                <?php if ($term): ?>- <?= $termNames[$term] ?? $term ?><?php endif; ?>
            <?php endif; ?>
            - <?= $academicYear ?>
        </p>
    </div>
    <?php if (!isStudent()): ?>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/grades.php?class_id=<?= $classId ?>&section=<?= urlencode($section) ?>&term=<?= $term ?>&year=<?= $academicYear ?>" class="btn btn-primary">
            <?= __('✏️ تعديل الدرجات') ?>
        </a>
        
        <!-- قائمة التصدير -->
        <div class="dropdown" style="position: relative;">
            <button class="btn btn-success dropdown-toggle" onclick="toggleExportMenu()" id="exportBtn">
                📥 تصدير
            </button>
            <div class="dropdown-menu" id="exportMenu" style="display: none; position: absolute; top: 100%; left: 0; background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-lg); z-index: 100; min-width: 180px;">
                <a href="/export_report.php?type=grades&format=pdf&class_id=<?= $classId ?>&section=<?= urlencode($section) ?>&term=<?= $term ?>&year=<?= $academicYear ?>" 
                   class="dropdown-item" target="_blank" style="display: flex; align-items: center; gap: 8px; padding: 10px 15px; text-decoration: none; color: var(--text-primary); transition: background 0.2s;">
                    📄 PDF للطباعة
                </a>
                <a href="/export_report.php?type=grades&format=word&class_id=<?= $classId ?>&section=<?= urlencode($section) ?>&term=<?= $term ?>&year=<?= $academicYear ?>" 
                   class="dropdown-item" style="display: flex; align-items: center; gap: 8px; padding: 10px 15px; text-decoration: none; color: var(--text-primary); transition: background 0.2s;">
                    📝 Word
                </a>
                <a href="/export_report.php?type=grades&format=excel&class_id=<?= $classId ?>&section=<?= urlencode($section) ?>&term=<?= $term ?>&year=<?= $academicYear ?>" 
                   class="dropdown-item" style="display: flex; align-items: center; gap: 8px; padding: 10px 15px; text-decoration: none; color: var(--text-primary); transition: background 0.2s;">
                    📊 Excel
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (!isStudent()): ?>
<!-- الفلاتر الاحترافية -->
<div class="card mb-3 fade-in no-print">
    <div class="card-body">
        <form method="GET" class="d-flex gap-2 flex-wrap align-center" id="reportFilterForm">
            <!-- حقل البحث -->
            <div class="form-group" style="margin: 0; min-width: 200px; flex: 1;">
                <label style="margin-bottom: 0.25rem; font-size: 0.85rem;"><?= __('🔍 بحث عن طالب') ?></label>
                <input type="text" id="studentSearch" class="form-control" placeholder="<?= __('ابحث بالاسم أو ID...') ?>" 
                       oninput="filterStudentResults()" style="border-radius: 25px;">
            </div>
            
            <div class="form-group" style="margin: 0; min-width: 120px;">
                <label style="margin-bottom: 0.25rem; font-size: 0.85rem;"><?= __('الصف') ?></label>
                <select name="class_id" id="reportClassId" class="form-control" onchange="loadReportData()">
                    <?php if ($isAdminOrAssistant): ?>
                    <option value="" <?= $classId === 0 ? 'selected' : '' ?>>🏫 الكل</option>
                    <?php endif; ?>
                    <?php foreach (CLASSES as $id => $name): ?>
                        <?php if (in_array($id, $availableClasses)): ?>
                        <option value="<?= $id ?>" <?= $classId == $id ? 'selected' : '' ?> 
                                data-monthly="<?= usesMonthlyGradeSystem($id) ? '1' : '0' ?>"><?= $name ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin: 0; min-width: 100px;">
                <label style="margin-bottom: 0.25rem; font-size: 0.85rem;"><?= __('الشعبة') ?></label>
                <select name="section" id="reportSection" class="form-control" onchange="loadReportData()">
                    <?php if ($isAdminOrAssistant): ?>
                    <option value="" <?= $section === '' ? 'selected' : '' ?>>📚 الكل</option>
                    <?php endif; ?>
                    <?php foreach ($availableSections as $sec): ?>
                    <option value="<?= $sec ?>" <?= $section == $sec ? 'selected' : '' ?>><?= $sec ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- فلتر الفترة (للصفوف غير الشهرية) -->
            <div class="form-group" style="margin: 0; min-width: 130px;" id="termFilterGroup">
                <label style="margin-bottom: 0.25rem; font-size: 0.85rem;"><?= __('الفترة') ?></label>
                <select name="term" id="reportTerm" class="form-control" onchange="loadReportData()">
                    <option value="" <?= $term === '' ? 'selected' : '' ?>>📅 الكل</option>
                    <?php foreach ($termNames as $key => $name): ?>
                    <option value="<?= $key ?>" <?= $term == $key ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- فلتر الشهر (للنظام الشهري - الخامس والسادس) -->
            <div class="form-group" style="margin: 0; min-width: 160px; <?= !$isMonthlySystem ? 'display: none;' : '' ?>" id="monthFilterGroup">
                <label style="margin-bottom: 0.25rem; font-size: 0.85rem;">📆 الشهر</label>
                <select name="month" id="reportMonth" class="form-control" onchange="loadReportData()">
                    <option value="">🗓️ كل الأشهر</option>
                    <?php foreach ($academicMonths as $m => $mName): ?>
                    <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>><?= $mName ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin: 0; min-width: 100px;">
                <label style="margin-bottom: 0.25rem; font-size: 0.85rem;"><?= __('السنة') ?></label>
                <select name="year" id="reportYear" class="form-control" onchange="loadReportData()">
                    <?php for ($y = date('Y'); $y >= date('Y') - 1; $y--): ?>
                    <option value="<?= $y ?>" <?= $academicYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <!-- زر إعادة تعيين -->
            <div class="form-group" style="margin: 0; align-self: flex-end;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="resetFilters()" title="<?= __('إعادة تعيين الفلاتر') ?>" style="padding: 0.5rem 0.75rem; border-radius: 8px;">
                    <?= __('🔄 مسح') ?>
                </button>
            </div>

        </form>
        
        <!-- مؤشر نوع النظام -->
        <?php if ($classId && $isMonthlySystem): ?>
        <div style="margin-top: 1rem; padding: 0.75rem; background: linear-gradient(135deg, #f0fdf4, #dcfce7); border-radius: 8px; border: 2px solid #22c55e;">
            <span style="font-size: 1.1rem;">📊</span>
            <strong style="color: #166534;"><?= __('نظام الدرجات الشهري') ?></strong> - 
            <span style="color: #15803d;"><?= __('الصف') ?> <?= CLASSES[$classId] ?? $classId ?> <?= __('يستخدم النظام الشهري (درجات من 1-10 لكل شهر)') ?></span>
        </div>
        <?php elseif ($classId): ?>
        <div style="margin-top: 1rem; padding: 0.75rem; background: linear-gradient(135deg, #eff6ff, #dbeafe); border-radius: 8px; border: 2px solid #3b82f6;">
            <span style="font-size: 1.1rem;">📋</span>
            <strong style="color: #1e40af;"><?= __('النظام الفصلي') ?></strong> - 
            <span style="color: #1d4ed8;"><?= __('الصف') ?> <?= CLASSES[$classId] ?? $classId ?> <?= __('يستخدم النظام الفصلي (الفصل الأول، الثاني، النهائي)') ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- الإحصائيات -->
<div class="stats-grid mb-3 fade-in">
    <div class="stat-card success">
        <div class="stat-icon">✅</div>
        <div class="stat-content">
            <div class="stat-value"><?= $stats['passed'] ?></div>
            <div class="stat-label"><?= __('ناجح') ?></div>
        </div>
    </div>
    <div class="stat-card warning">
        <div class="stat-icon">⚠️</div>
        <div class="stat-content">
            <div class="stat-value"><?= $stats['supplementary'] ?></div>
            <div class="stat-label"><?= __('مكمّل') ?></div>
        </div>
    </div>
    <div class="stat-card danger">
        <div class="stat-icon">❌</div>
        <div class="stat-content">
            <div class="stat-value"><?= $stats['failed'] ?></div>
            <div class="stat-label"><?= __('راسب') ?></div>
        </div>
    </div>
    <div class="stat-card info">
        <div class="stat-icon">📋</div>
        <div class="stat-content">
            <div class="stat-value"><?= $stats['total_students'] ?></div>
            <div class="stat-label"><?= __('المجموع') ?></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- جدول النتائج -->
<div class="card fade-in">
    <div class="card-header">
        <h3><?= __('📋 نتائج التلاميذ') ?></h3>
    </div>
    <div class="card-body">
        <?php if (empty($results)): ?>
        <div class="empty-state">
            <div class="icon">📊</div>
            <h3><?= __('لا توجد نتائج') ?></h3>
            <p><?= __('لم يتم إدخال درجات لهذا الصف في هذه الفترة') ?></p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="results-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th style="min-width: 150px;"><?= __('اسم التلميذ') ?></th>
                        <?php if (!$classId): // عرض الصف والشعبة عند اختيار "الكل" ?>
                        <th style="width: 80px; text-align: center;"><?= __('الصف') ?></th>
                        <th style="width: 60px; text-align: center;"><?= __('الشعبة') ?></th>
                        <?php endif; ?>
                        <?php if ($classId): ?>
                        <?php foreach ($subjects as $subject): ?>
                        <th style="min-width: 70px; text-align: center; font-size: 0.8rem;">
                            <?= $subject ?>
                        </th>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        <th style="width: 80px; text-align: center;"><?= __('المجموع') ?></th>
                        <th style="width: 70px; text-align: center;"><?= __('المعدل') ?></th>
                        <th style="width: 100px; text-align: center;"><?= __('النتيجة') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $counter = 1; foreach ($results as $result): ?>
                    <?php
                        $studentClassId = $result['student']['class_id'] ?? $classId;
                        $studentSubjects = $classId ? $subjects : Subject::getSubjectsByClass($studentClassId);
                        
                        // تحديد نوع النظام للطالب
                        $studentisMonthly = usesMonthlyGradeSystem($studentClassId);
                        
                        // جلب الدرجات حسب نوع النظام
                        if ($studentisMonthly) {
                            // النظام الشهري - نجلب من monthly_grades ونستخدم final_grade
                            $monthlyGrades = $gradeModel->getStudentMonthlyGrades($result['student']['id'], $academicYear);
                            $gradesBySubject = [];
                            foreach ($monthlyGrades as $mg) {
                                // استخدام الدرجة النهائية إن وجدت
                                $gradesBySubject[$mg['subject_name']] = $mg['final_grade'] ?? '-';
                            }
                        } else {
                            // النظام العادي - نجلب من جدول grades
                            $studentGrades = $gradeModel->getStudentGrades($result['student']['id'], $term, $academicYear);
                            $gradesBySubject = [];
                            foreach ($studentGrades as $g) {
                                $gradesBySubject[$g['subject_name']] = $g['grade'];
                            }
                        }
                        
                        $resultClass = '';
                        switch ($result['status']) {
                            case 'pass': $resultClass = 'success'; break;
                            case 'fail': $resultClass = 'danger'; break;
                            case 'supp': $resultClass = 'warning'; break;
                        }
                    ?>
                    <tr class="result-row <?= $result['status'] ?>">
                        <td><?= $counter++ ?></td>
                        <td>
                            <strong><?= sanitize($result['student']['full_name']) ?></strong>
                            <?php if ($studentisMonthly): ?>
                            <span class="badge badge-success" style="font-size: 0.7rem; margin-right: 5px;"><?= __('شهري') ?></span>
                            <?php endif; ?>
                        </td>
                        <?php if (!$classId): ?>
                        <td style="text-align: center;">
                            <span class="badge <?= $studentisMonthly ? 'badge-success' : 'badge-info' ?>">
                                <?= CLASSES[$studentClassId] ?? $studentClassId ?>
                            </span>
                        </td>
                        <td style="text-align: center;"><?= sanitize($result['student']['section'] ?? '') ?></td>
                        <?php endif; ?>
                        <?php if ($classId): ?>
                        <?php foreach ($subjects as $subject): ?>
                        <?php 
                            $grade = $gradesBySubject[$subject] ?? '-';
                            $isLow = $grade !== '-' && (
                                (Subject::usesTenPointSystem($classId) && $grade <= 4) ||
                                (!Subject::usesTenPointSystem($classId) && $grade < 50)
                            );
                        ?>
                        <td style="text-align: center; <?= $isLow ? 'color: #ef4444; font-weight: bold;' : '' ?>">
                            <?= $grade ?>
                        </td>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        <td style="text-align: center; font-weight: bold;">
                            <?= $result['total'] ?: '-' ?>
                        </td>
                        <td style="text-align: center;">
                            <?= $result['average'] ? number_format($result['average'], 1) . '%' : '-' ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($result['status']): ?>
                            <span class="badge badge-<?= $resultClass ?>">
                                <?php
                                switch ($result['status']) {
                                    case 'pass': echo 'ناجح'; break;
                                    case 'fail': echo 'راسب'; break;
                                    case 'supp': echo 'مكمّل'; break;
                                }
                                ?>
                            </span>
                            <?php if (!empty($result['failed_subjects'])): ?>
                            <div style="font-size: 0.7rem; color: #ef4444; margin-top: 0.25rem;">
                                <?= implode('، ', $result['failed_subjects']) ?>
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="badge">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}

.stat-card {
    background: var(--bg-secondary);
    border-radius: var(--radius);
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border-right: 4px solid var(--border);
}

.stat-card.success { border-color: #22c55e; }
.stat-card.warning { border-color: #f59e0b; }
.stat-card.danger { border-color: #ef4444; }
.stat-card.info { border-color: #3b82f6; }

.stat-icon {
    font-size: 2rem;
}

.stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
}

.stat-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.results-table {
    width: 100%;
    border-collapse: collapse;
}

.results-table th, .results-table td {
    padding: 0.75rem 0.5rem;
    border: 1px solid var(--border);
    vertical-align: middle;
}

.results-table th {
    background: var(--bg-secondary);
    font-weight: 600;
}

.results-table tbody tr:hover {
    background: var(--bg-hover);
}

.result-row.fail {
    background: rgba(239, 68, 68, 0.05);
}

.result-row.supp {
    background: rgba(245, 158, 11, 0.05);
}

@media print {
    .no-print { display: none !important; }
    .sidebar, .topbar, .navbar { display: none !important; }
    .main-content { margin: 0 !important; padding: 1rem !important; }
    .results-table th, .results-table td { font-size: 10px; padding: 0.25rem; }
}

/* Dropdown Styles */
.dropdown-item:hover {
    background: var(--bg-hover) !important;
}
</style>

<script>
function toggleExportMenu() {
    const menu = document.getElementById('exportMenu');
    if (menu.style.display === 'none' || menu.style.display === '') {
        menu.style.display = 'block';
    } else {
        menu.style.display = 'none';
    }
}

// إغلاق القائمة عند النقر خارجها
document.addEventListener('click', function(e) {
    const dropdown = document.querySelector('.dropdown');
    const menu = document.getElementById('exportMenu');
    if (dropdown && menu && !dropdown.contains(e.target)) {
        menu.style.display = 'none';
    }
});

// تحميل التقرير عبر AJAX
function loadReportData() {
    const classId = document.getElementById('reportClassId')?.value || '';
    const section = document.getElementById('reportSection')?.value || '';
    const term = document.getElementById('reportTerm')?.value || '';
    const year = document.getElementById('reportYear')?.value || new Date().getFullYear();
    const month = document.getElementById('reportMonth')?.value || '';
    
    const url = new URL(window.location);
    url.searchParams.set('class_id', classId);
    url.searchParams.set('section', section);
    url.searchParams.set('term', term);
    url.searchParams.set('year', year);
    if (month) url.searchParams.set('month', month);
    window.history.pushState({}, '', url);
    
    // عرض مؤشر تحميل
    const tableCard = document.querySelector('.card.fade-in:last-of-type');
    if (tableCard) {
        tableCard.style.opacity = '0.5';
        tableCard.style.pointerEvents = 'none';
    }
    
    fetch(url.href, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.text())
    .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // تحديث الإحصائيات
        const newStats = doc.querySelector('.stats-grid');
        const currentStats = document.querySelector('.stats-grid');
        if (newStats && currentStats) {
            currentStats.outerHTML = newStats.outerHTML;
        }
        
        // تحديث الجدول
        const newTable = doc.querySelector('.card.fade-in:last-of-type');
        const currentTable = document.querySelector('.card.fade-in:last-of-type');
        if (newTable && currentTable) {
            currentTable.outerHTML = newTable.outerHTML;
        }
        
        // تحديث العنوان
        const newHeader = doc.querySelector('.page-header p');
        const currentHeader = document.querySelector('.page-header p');
        if (newHeader && currentHeader) {
            currentHeader.innerHTML = newHeader.innerHTML;
        }
        
        // تحديث مؤشر نوع النظام
        const newSystemIndicator = doc.querySelector('.card-body > div[style*="background: linear-gradient"]');
        const currentSystemIndicator = document.querySelector('.card-body > div[style*="background: linear-gradient"]');
        if (newSystemIndicator) {
            if (currentSystemIndicator) {
                currentSystemIndicator.outerHTML = newSystemIndicator.outerHTML;
            }
        } else if (currentSystemIndicator) {
            currentSystemIndicator.remove();
        }
        
        // تحديث فلتر الشهر حسب الصف المختار
        handleClassChange();
    })
    .catch(error => {
        console.error('Error:', error);
        // في حالة خطأ، إعادة تحميل الصفحة
        window.location.href = url.href;
    })
    .finally(() => {
        const tableCard = document.querySelector('.card.fade-in:last-of-type');
        if (tableCard) {
            tableCard.style.opacity = '1';
            tableCard.style.pointerEvents = '';
        }
    });
}

// ربط الأحداث
document.addEventListener('DOMContentLoaded', function() {
    const loadBtn = document.getElementById('loadReportBtn');
    if (loadBtn) {
        loadBtn.addEventListener('click', loadReportData);
    }
    
    ['reportClassId', 'reportSection', 'reportTerm', 'reportYear', 'reportMonth'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', loadReportData);
        }
    });
    
    // تهيئة عند التحميل
    handleClassChange();
});

// التعامل مع تغيير الصف
function handleClassChange() {
    const classSelect = document.getElementById('reportClassId');
    const monthGroup = document.getElementById('monthFilterGroup');
    const termGroup = document.getElementById('termFilterGroup');
    
    if (!classSelect) return;
    
    const selectedOption = classSelect.options[classSelect.selectedIndex];
    const isMonthly = selectedOption?.dataset?.monthly === '1';
    
    if (monthGroup) {
        monthGroup.style.display = isMonthly ? 'block' : 'none';
    }
    
    // لا نخفي فلتر الفترة حتى للشهريين لأنهم قد يحتاجونه
}

// البحث في جدول النتائج
function filterStudentResults() {
    const searchInput = document.getElementById('studentSearch');
    if (!searchInput) return;
    
    const filter = searchInput.value.toLowerCase().trim();
    const rows = document.querySelectorAll('.results-table tbody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (!filter || text.includes(filter)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // تحديث العدد
    const badge = document.querySelector('.card-header .badge');
    if (badge && filter) {
        badge.textContent = visibleCount + ' نتيجة';
    }
    
    // تمييز حقل البحث
    if (filter) {
        searchInput.style.borderColor = 'var(--primary)';
        searchInput.style.boxShadow = '0 0 0 3px rgba(102, 126, 234, 0.2)';
    } else {
        searchInput.style.borderColor = '';
        searchInput.style.boxShadow = '';
    }
}

// إعادة تعيين الفلاتر
function resetFilters() {
    const searchEl = document.getElementById('studentSearch');
    if (searchEl) searchEl.value = '';
    
    document.getElementById('reportClassId').value = '';
    document.getElementById('reportSection').value = '';
    document.getElementById('reportTerm').value = '';
    document.getElementById('reportYear').value = new Date().getFullYear();
    const monthEl = document.getElementById('reportMonth');
    if (monthEl) monthEl.value = '';
    
    handleClassChange();
    filterStudentResults();
    loadReportData(); // تحميل البيانات بعد المسح
}
</script>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
