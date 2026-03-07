<?php
/**
 * إدخال الدرجات - Grades Entry
 * صفحة إدخال درجات التلاميذ
 * - المدير والمعاون: صلاحيات كاملة لجميع المواد
 * - المعلم: المواد المعينة له فقط
 * 
 * @package SchoolManager
 * @access  مدير + معاون + معلم
 */

$pageTitle = 'إدخال الدرجات';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/models/Subject.php';
require_once __DIR__ . '/models/Grade.php';
require_once __DIR__ . '/models/Student.php';
require_once __DIR__ . '/models/TeacherAssignment.php';

requireLogin();

// التلميذ لا يمكنه الوصول لهذه الصفحة
if (isStudent()) {
    alert('ليس لديك صلاحية للوصول لهذه الصفحة', 'error');
    redirect('/student_profile.php');
}

// التحقق من صلاحية رصد الدرجات
if (!canEnterGrades()) {
    alert('ليس لديك صلاحية للوصول لهذه الصفحة', 'error');
    redirect('/grades_report.php');
}

$subjectModel = new Subject();
$gradeModel = new Grade();
$studentModel = new Student();
$assignmentModel = new TeacherAssignment();
$currentUser = getCurrentUser();
$conn = getConnection();

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
} else {
    // المعلم يرى فقط الصفوف والشعب المعينة له
    $teacherClasses = $assignmentModel->getClassesForTeacher($currentUser['id']);
    $teacherSections = [];
    
    foreach ($teacherClasses as $tc) {
        if (!in_array($tc['class_id'], $availableClasses)) {
            $availableClasses[] = $tc['class_id'];
        }
        if (!in_array($tc['section'], $teacherSections)) {
            $teacherSections[] = $tc['section'];
        }
        // تجميع الشعب حسب الصف
        if (!isset($sectionsByClass[$tc['class_id']])) {
            $sectionsByClass[$tc['class_id']] = [];
        }
        if (!in_array($tc['section'], $sectionsByClass[$tc['class_id']])) {
            $sectionsByClass[$tc['class_id']][] = $tc['section'];
        }
    }
    // المعلم يرى فقط الشعب المعينة له
    $availableSections = $teacherSections;
}

// الفلاتر - مع التحقق من الصلاحيات
// للمدير/المعاون: افتراضياً الصف الأول مع شعبة أ، للمعلم: أول صف وشعبة معينة
$firstTeacherClass = !empty($teacherClasses) ? $teacherClasses[0] : null;
$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : ($isAdminOrAssistant ? 1 : ($firstTeacherClass ? $firstTeacherClass['class_id'] : 1));
$section = isset($_GET['section']) ? $_GET['section'] : ($isAdminOrAssistant ? 'أ' : ($firstTeacherClass ? $firstTeacherClass['section'] : 'أ'));
$term = $_GET['term'] ?? 'first';
$academicYear = $_GET['year'] ?? date('Y');

// للمعلم: إذا اختار صف غير مسموح، نعيده لأول صف متاح
// لكن إذا اختار "الكل" (0 أو فارغ) نسمح له برؤية جميع الصفوف المعينة له
if (!$isAdminOrAssistant && $classId != 0 && !in_array($classId, $availableClasses)) {
    $classId = $firstTeacherClass ? $firstTeacherClass['class_id'] : 1;
}

// للمعلم: تحديد الشعب المتاحة بناءً على الصف المختار
if (!$isAdminOrAssistant && $classId && isset($sectionsByClass[$classId])) {
    $availableSections = $sectionsByClass[$classId];
}

// للمعلم: إذا الشعبة غير مسموحة للصف المختار، نعيدها لأول شعبة متاحة
if (!$isAdminOrAssistant && !empty($section) && !in_array($section, $availableSections)) {
    $section = !empty($availableSections) ? $availableSections[0] : '';
}

// الحصول على المواد حسب الصف
$allSubjects = Subject::getSubjectsByClass($classId);
$maxGrade = Subject::getMaxGrade($classId);
$passingGrade = Subject::getPassingGrade($classId);

// ═══════════════════════════════════════════════════════════════
// 🔒 التحقق من تعيينات المعلم وتحديد الصلاحيات
// ═══════════════════════════════════════════════════════════════
$teacherAssignedSubjects = [];
$hasAssignments = false;
$canEdit = false;

// المدير والمعاون يستطيعون رصد وتعديل الدرجات
// المعلم يشاهد فقط دون تعديل
if ($isAdminOrAssistant) {
    $subjects = $allSubjects;
    $canEdit = true; // المدير والمعاون يستطيعون رصد الدرجات
    $hasAssignments = true;
    $viewOnlyMode = false;
} else {
    // للمعلم: الحصول على المواد المعينة له (للعرض فقط)
    $teacherAssignedSubjects = $assignmentModel->getSubjectsForTeacher($currentUser['id'], $classId, $section);
    $hasAssignments = !empty($teacherAssignedSubjects);
    $canEdit = false; // المعلم لا يستطيع التعديل - فقط المشاهدة
    $viewOnlyMode = true; // وضع العرض فقط للمعلم
    
    // المعلم يرى فقط المواد المعينة له
    if (!empty($teacherAssignedSubjects)) {
        $subjects = array_intersect($allSubjects, $teacherAssignedSubjects);
    } else {
        $subjects = []; // المعلم لا يرى أي مواد إذا لم يكن معيناً لهذا الصف/الشعبة
    }
    
    // تنبيه للمعلم إذا لم يكن لديه تعيينات
    if (!$hasAssignments) {
        $noAssignmentWarning = true;
    }
}

// معالجة البحث
$searchQuery = trim($_GET['search'] ?? '');

// الحصول على التلاميذ
if ($isAdminOrAssistant) {
    // للمدير/المعاون: جميع الطلاب أو حسب الفلتر
    $students = $studentModel->getAll($classId ?: null, $section ?: null);
} else {
    // للمعلم: جلب طلاب الصفوف/الشعب المعينة له فقط
    $students = [];
    
    if ($classId && $section) {
        // صف وشعبة محددة
        $students = $studentModel->getAll($classId, $section);
    } elseif ($classId) {
        // صف محدد، كل الشعب المعينة له
        foreach ($availableSections as $sec) {
            $classStudents = $studentModel->getAll($classId, $sec);
            $students = array_merge($students, $classStudents);
        }
    } else {
        // "الكل" - جميع الصفوف والشعب المعينة للمعلم
        foreach ($teacherClasses as $tc) {
            $classStudents = $studentModel->getAll($tc['class_id'], $tc['section']);
            $students = array_merge($students, $classStudents);
        }
    }
    
    // إزالة التكرارات إن وجدت
    $uniqueStudents = [];
    $seenIds = [];
    foreach ($students as $student) {
        if (!in_array($student['id'], $seenIds)) {
            $uniqueStudents[] = $student;
            $seenIds[] = $student['id'];
        }
    }
    $students = $uniqueStudents;
}

// فلترة الطلاب حسب البحث
if (!empty($searchQuery)) {
    $students = array_filter($students, function($student) use ($searchQuery) {
        // البحث بالاسم أو الرقم
        return stripos($student['full_name'], $searchQuery) !== false 
            || $student['id'] == $searchQuery;
    });
    $students = array_values($students); // إعادة ترتيب المفاتيح
}

// الحصول على الدرجات الموجودة
$existingGrades = [];
foreach ($students as $student) {
    $studentGrades = $gradeModel->getStudentGrades($student['id'], $term, $academicYear);
    foreach ($studentGrades as $grade) {
        $existingGrades[$student['id']][$grade['subject_name']] = $grade['grade'];
    }
}

// أسماء الفترات
$termNames = [
    'first' => 'الفصل الأول',
    'second' => 'الفصل الثاني',
    'final' => 'النهائي'
];

// تحديد نوع النظام (شهري للصفوف 5 و 6)
$isMonthlySystem = usesMonthlyGradeSystem($classId);

// جلب المعلمين المعينين لهذا الصف/الشعبة (للعرض للمدير والمعاون)
$assignedTeachers = [];
if ($isAdminOrAssistant && $classId && $section) {
    $stmt = $conn->prepare("
        SELECT DISTINCT ta.subject_name, u.full_name as teacher_name 
        FROM teacher_assignments ta
        JOIN users u ON ta.teacher_id = u.id
        WHERE ta.class_id = ? AND ta.section = ?
        ORDER BY ta.subject_name
    ");
    $stmt->execute([$classId, $section]);
    $assignedTeachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/views/components/header.php';
?>

<div class="page-header d-flex justify-between align-center flex-wrap gap-2">
    <div>
        <h1><?= __('📝 إدخال الدرجات') ?></h1>
        <p><?= __('الصف') ?> <?= CLASSES[$classId] ?? $classId ?> - <?= __('شعبة') ?> <?= sanitize($section) ?> - <?= __($termNames[$term]) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="grades_report.php?class_id=<?= $classId ?>&section=<?= urlencode($section) ?>&term=<?= $term ?>&year=<?= $academicYear ?>" class="btn btn-secondary">
            <?= __('📊 عرض التقرير') ?>
        </a>
        
        <!-- قائمة التصدير -->
        <div class="dropdown" style="position: relative;">
            <button class="btn btn-success dropdown-toggle" onclick="toggleGradesExportMenu()" id="gradesExportBtn">
                <?= __('📥 تصدير') ?>
            </button>
            <div class="dropdown-menu" id="gradesExportMenu" style="display: none; position: absolute; top: 100%; left: 0; background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-lg); z-index: 100; min-width: 180px;">
                <a href="export_report.php?type=grades&format=pdf&class_id=<?= $classId ?>&section=<?= urlencode($section) ?>&term=<?= $term ?>&year=<?= $academicYear ?>" 
                   class="dropdown-item" target="_blank" style="display: flex; align-items: center; gap: 8px; padding: 10px 15px; text-decoration: none; color: var(--text-primary);">
                    📄 PDF
                </a>
                <a href="export_report.php?type=grades&format=word&class_id=<?= $classId ?>&section=<?= urlencode($section) ?>&term=<?= $term ?>&year=<?= $academicYear ?>" 
                   class="dropdown-item" style="display: flex; align-items: center; gap: 8px; padding: 10px 15px; text-decoration: none; color: var(--text-primary);">
                    📝 Word
                </a>
                <a href="export_report.php?type=grades&format=excel&class_id=<?= $classId ?>&section=<?= urlencode($section) ?>&term=<?= $term ?>&year=<?= $academicYear ?>" 
                   class="dropdown-item" style="display: flex; align-items: center; gap: 8px; padding: 10px 15px; text-decoration: none; color: var(--text-primary);">
                    📊 Excel
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function toggleGradesExportMenu() {
    const menu = document.getElementById('gradesExportMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', function(e) {
    const menu = document.getElementById('gradesExportMenu');
    const btn = document.getElementById('gradesExportBtn');
    if (menu && btn && !btn.contains(e.target) && !menu.contains(e.target)) {
        menu.style.display = 'none';
    }
});
</script>

<!-- الفلاتر -->
<div class="card mb-3 fade-in">
    <div class="card-body">
        <form method="GET" class="d-flex gap-2 flex-wrap align-center" id="gradesFilterForm">
            <!-- حقل البحث -->
            <div class="form-group" style="margin: 0; min-width: 180px;">
                <label style="margin-bottom: 0.25rem; font-size: 0.85rem;"><?= __('🔍 بحث') ?></label>
                <input type="text" name="search" id="gradesSearch" class="form-control" 
                       placeholder="<?= __('اسم الطالب أو الرقم...') ?>" 
                       value="<?= sanitize($_GET['search'] ?? '') ?>">
            </div>
            
            <div class="form-group" style="margin: 0; min-width: 120px;">
                <label style="margin-bottom: 0.25rem; font-size: 0.85rem;"><?= __('الصف') ?></label>
                <select name="class_id" id="gradesClassId" class="form-control">
                    <?php foreach (CLASSES as $id => $name): ?>
                        <?php if ($isAdminOrAssistant || in_array($id, $availableClasses)): ?>
                        <option value="<?= $id ?>" <?= $classId == $id ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin: 0; min-width: 100px;">
                <label style="margin-bottom: 0.25rem; font-size: 0.85rem;"><?= __('الشعبة') ?></label>
                <select name="section" id="gradesSection" class="form-control">
                    <?php foreach (SECTIONS as $sec): ?>
                    <option value="<?= $sec ?>" <?= $section == $sec ? 'selected' : '' ?>><?= $sec ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin: 0; min-width: 130px;">
                <label style="margin-bottom: 0.25rem; font-size: 0.85rem;"><?= __('الفترة') ?></label>
                <select name="term" id="gradesTerm" class="form-control">
                    <?php foreach ($termNames as $key => $name): ?>
                    <option value="<?= $key ?>" <?= $term == $key ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin: 0; min-width: 100px;">
                <label style="margin-bottom: 0.25rem; font-size: 0.85rem;"><?= __('السنة') ?></label>
                <select name="year" id="gradesYear" class="form-control">
                    <?php for ($y = date('Y'); $y >= date('Y') - 1; $y--): ?>
                    <option value="<?= $y ?>" <?= $academicYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin: 0; display: flex; align-items: flex-end; gap: 0.5rem;">
                <button type="button" id="resetGradesBtn" class="btn btn-outline-secondary btn-sm" onclick="resetGradesFilter()" title="<?= __('مسح الفلاتر') ?>">
                    <?= __('🗑️ مسح') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- معلومات نظام الدرجات -->
<div class="card mb-3 fade-in" style="background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);">
    <div class="card-body">
        <div class="d-flex gap-3 flex-wrap align-center">
            <div>
                <strong><?= __('📊 نظام الدرجات:') ?></strong>
                <?php if (Subject::usesTenPointSystem($classId)): ?>
                    <span class="badge badge-info"><?= __('من 10 درجات') ?></span>
                <?php else: ?>
                    <span class="badge badge-primary"><?= __('من 100 درجة') ?></span>
                <?php endif; ?>
            </div>
            <div>
                <strong><?= __('✅ درجة النجاح:') ?></strong>
                <span class="badge badge-success"><?= $passingGrade ?></span>
            </div>
            <div>
                <strong><?= __('⚠️ المكمّل:') ?></strong>
                <span class="badge badge-warning"><?= __('مادتان راسبتان') ?></span>
            </div>
            <div>
                <strong><?= __('❌ الراسب:') ?></strong>
                <span class="badge badge-danger"><?= __('٣ مواد فأكثر') ?></span>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($viewOnlyMode) && $classId && $section): ?>
<div class="alert alert-info" style="display: flex; align-items: flex-start; gap: 12px; margin-bottom: 1.5rem;">
    <span style="font-size: 2rem;">👁️</span>
    <div style="flex: 1;">
        <strong style="font-size: 1.1rem;"><?= __('وضع العرض فقط') ?></strong><br>
        <span style="opacity: 0.9;">
            <?= __('أنت تشاهد درجات الطلاب في مادتك المُرصدة من قِبل المدير أو المعاون.') ?> 
            <strong><?= __('رصد الدرجات متاح فقط للمدير والمعاون.') ?></strong>
        </span>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($noAssignmentWarning)): ?>
<div class="alert alert-warning" style="display: flex; align-items: flex-start; gap: 12px; margin-bottom: 1.5rem;">
    <span style="font-size: 2rem;">⚠️</span>
    <div style="flex: 1;">
        <strong style="font-size: 1.1rem;"><?= __('أنت غير معيّن لهذا الصف/الشعبة') ?></strong><br>
        <span style="opacity: 0.9;"><?= __('لا يمكنك رصد درجات للصف') ?> <?= CLASSES[$classId] ?? $classId ?> - <?= __('شعبة') ?> <?= $section ?>.</span><br>
        <small style="color: var(--text-secondary);">💡 تواصل مع مدير المدرسة لتعيينك للمواد والصفوف الصحيحة.</small>
    </div>
</div>
<?php endif; ?>

<?php if (empty($students)): ?>
<div class="card fade-in">
    <div class="card-body">
        <div class="empty-state">
            <div class="icon">👨‍🎓</div>
            <h3><?= __('لا يوجد تلاميذ في هذا الصف') ?></h3>
            <p><?= __('قم بإضافة تلاميذ لهذا الصف والشعبة أولاً') ?></p>
            <?php if (isAdmin()): ?>
            <a href="students.php?action=add" class="btn btn-primary mt-2"><?= __('إضافة تلميذ') ?></a>
            <?php endif; ?>
            
            <?php if (isset($debugInfo) && isMainAdmin()): ?>
            <!-- معلومات تشخيصية للمدير -->
            <div style="margin-top: 20px; padding: 15px; background: #fef3c7; border-radius: 8px; text-align: right; font-size: 0.85rem;">
                <strong>🔍 تشخيص:</strong><br>
                class_id: <?= $debugInfo['classId'] ?><br>
                section: "<?= $debugInfo['section'] ?>" (hex: <?= $debugInfo['section_hex'] ?>)<br>
                نتيجة البحث المباشر: <?= $debugInfo['direct_count'] ?> طالب<br>
                طلاب الصف (بدون شرط شعبة): <?= $debugInfo['no_section_count'] ?> طالب<br>
                الشعب الموجودة: <?= implode(', ', $debugInfo['students_sections']) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php else: ?>

<?php 
// جلب الدرجات الشهرية للصفوف 5 و 6
$monthlyColumns = $isMonthlySystem ? MONTHLY_GRADE_COLUMNS : [];
$monthlyGrades = [];
if ($isMonthlySystem) {
    $stmt = $conn->prepare("SELECT * FROM monthly_grades WHERE class_id = ? AND section = ? AND academic_year = ?");
    $stmt->execute([$classId, $section, $academicYear]);
    $allMonthlyGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allMonthlyGrades as $mg) {
        $monthlyGrades[$mg['student_id']][$mg['subject_name']] = $mg;
    }
}
?>

<!-- جدول الدرجات -->
<div class="card fade-in">
    <div class="card-header d-flex justify-between align-center">
        <h3>📋 <?= __('درجات التلاميذ') ?> <?= $isMonthlySystem ? '<span class="badge badge-primary" style="margin-right: 10px;">' . __('النظام الشهري') . '</span>' : '' ?></h3>
        <span class="badge badge-info"><?= count($students) ?> <?= __('تلميذ') ?></span>
    </div>
    <div class="card-body">
        <?php if ($isMonthlySystem): ?>
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- نظام الدرجات الشهري للصفوف 5 و 6 -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        
        <!-- اختيار المادة -->
        <div class="mb-3 p-2" style="background: var(--bg-secondary); border-radius: var(--radius);">
            <form method="GET" class="d-flex gap-2 flex-wrap align-center" id="subjectForm">
                <input type="hidden" name="class_id" value="<?= $classId ?>">
                <input type="hidden" name="section" value="<?= $section ?>">
                <input type="hidden" name="term" value="<?= $term ?>">
                <input type="hidden" name="year" value="<?= $academicYear ?>">
                <label style="font-weight: 600;"><?= __('📚 اختر المادة:') ?></label>
                <select name="subject" id="monthlySubjectSelect" class="form-control" style="width: auto; min-width: 180px;" onchange="this.form.submit()">
                    <?php 
                    $selectedSubject = $_GET['subject'] ?? $subjects[0] ?? '';
                    foreach ($subjects as $subj): 
                    ?>
                    <option value="<?= $subj ?>" <?= $selectedSubject == $subj ? 'selected' : '' ?>><?= $subj ?></option>
                    <?php endforeach; ?>
                </select>
                <span id="autoSaveIndicator" style="display: none; color: var(--success); font-size: 0.85rem;">
                    <i class="fas fa-check-circle"></i> <?= __('تم الحفظ') ?>
                </span>
            </form>
        </div>
        
        <form action="controllers/grade_handler.php" method="POST" id="gradesForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_monthly_grades">
            <input type="hidden" name="class_id" value="<?= $classId ?>">
            <input type="hidden" name="section" value="<?= $section ?>">
            <input type="hidden" name="subject" value="<?= $selectedSubject ?>">
            <input type="hidden" name="academic_year" value="<?= $academicYear ?>">
            
            <div class="table-responsive">
                <table class="grades-table monthly-table">
                    <thead>
                        <!-- صف المجموعات -->
                        <tr class="group-header">
                            <th rowspan="2" style="width: 40px;">#</th>
                            <th rowspan="2" style="min-width: 140px;"><?= __('التلميذ') ?></th>
                            <th colspan="5" style="background: #dbeafe; color: #1e40af; text-align: center;"><?= __('النصف الأول') ?></th>
                            <th colspan="5" style="background: #dcfce7; color: #166534; text-align: center;"><?= __('النصف الثاني') ?></th>
                            <th colspan="3" style="background: #fef3c7; color: #92400e; text-align: center;"><?= __('النتائج') ?></th>
                        </tr>
                        <!-- صف الأعمدة -->
                        <tr class="columns-header">
                            <?php foreach ($monthlyColumns as $key => $col): ?>
                            <th style="min-width: 65px; text-align: center; font-size: 0.7rem; padding: 0.4rem;">
                                <?= __($col['name']) ?>
                            </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; foreach ($students as $student): ?>
                        <?php $studentMG = $monthlyGrades[$student['id']][$selectedSubject] ?? []; ?>
                        <tr data-student-id="<?= $student['id'] ?>">
                            <td><?= $counter++ ?></td>
                            <td>
                                <strong style="font-size: 0.85rem;"><?= sanitize($student['full_name']) ?></strong>
                            </td>
                            <?php foreach ($monthlyColumns as $key => $col): ?>
                            <td style="text-align: center; padding: 0.3rem;">
                                <?php if ($col['type'] === 'input'): ?>
                                <input type="number" 
                                       name="grades[<?= $student['id'] ?>][<?= $key ?>]"
                                       class="grade-input monthly-input"
                                       data-col="<?= $key ?>"
                                       value="<?= $studentMG[$key] ?? '' ?>"
                                       min="0" max="<?= $col['max'] ?>"
                                       step="0.5"
                                       placeholder="-"
                                       <?= !$canEdit ? 'readonly' : '' ?>>
                                <?php elseif ($col['type'] === 'calc'): ?>
                                <span class="calc-field" data-col="<?= $key ?>">
                                    <?= isset($studentMG[$key]) ? number_format($studentMG[$key], 1) : '-' ?>
                                </span>
                                <input type="hidden" name="grades[<?= $student['id'] ?>][<?= $key ?>]" class="calc-hidden" data-col="<?= $key ?>" value="<?= $studentMG[$key] ?? '' ?>">
                                <?php elseif ($col['type'] === 'result'): ?>
                                <span class="result-badge badge" data-col="<?= $key ?>">
                                    <?= $studentMG[$key] ?? '-' ?>
                                </span>
                                <input type="hidden" name="grades[<?= $student['id'] ?>][<?= $key ?>]" class="result-hidden" data-col="<?= $key ?>" value="<?= $studentMG[$key] ?? '' ?>">
                                <?php elseif ($col['type'] === 'text'): ?>
                                <input type="text" 
                                       name="grades[<?= $student['id'] ?>][<?= $key ?>]"
                                       class="note-input"
                                       value="<?= sanitize($studentMG[$key] ?? '') ?>"
                                       placeholder="<?= __('ملاحظات') ?>"
                                       <?= !$canEdit ? 'readonly' : '' ?>>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($canEdit): ?>
            <div class="d-flex justify-between align-center mt-3">
                <div class="grade-legend d-flex gap-2">
                    <span class="legend-item"><span class="color-box success"></span> <?= __('ناجح') ?> (50+)</span>
                    <span class="legend-item"><span class="color-box warning"></span> <?= __('مكمّل') ?> (40-49)</span>
                    <span class="legend-item"><span class="color-box danger"></span> <?= __('راسب') ?> (-40)</span>
                </div>
                <button type="submit" class="btn btn-primary btn-lg">
                    💾 <?= __('حفظ الدرجات') ?>
                </button>
            </div>
            <?php endif; ?>
        </form>
        
        <?php else: ?>
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- نظام الدرجات العادي للصفوف 1-4 -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <form action="controllers/grade_handler.php" method="POST" id="gradesForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_grades">
            <input type="hidden" name="class_id" value="<?= $classId ?>">
            <input type="hidden" name="section" value="<?= $section ?>">
            <input type="hidden" name="term" value="<?= $term ?>">
            <input type="hidden" name="academic_year" value="<?= $academicYear ?>">
            <input type="hidden" name="max_grade" value="<?= $maxGrade ?>">
            
            <div class="table-responsive">
                <table class="grades-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th style="min-width: 150px;"><?= __('اسم التلميذ') ?></th>
                            <?php foreach ($subjects as $subject): ?>
                            <th style="min-width: 80px; text-align: center;">
                                <div style="font-size: 0.85rem;"><?= $subject ?></div>
                                <small style="color: var(--text-muted);">/ <?= $maxGrade ?></small>
                            </th>
                            <?php endforeach; ?>
                            <th style="width: 80px; text-align: center;"><?= __('المجموع') ?></th>
                            <th style="width: 80px; text-align: center;"><?= __('النتيجة') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; foreach ($students as $student): ?>
                        <tr data-student-id="<?= $student['id'] ?>">
                            <td><?= $counter++ ?></td>
                            <td>
                                <strong><?= sanitize($student['full_name']) ?></strong>
                                <code style="font-size: 0.7rem; opacity: 0.6; margin-right: 0.3rem;">#<?= $student['id'] ?></code>
                            </td>
                            <?php foreach ($subjects as $subject): ?>
                            <?php 
                                $existingGrade = $existingGrades[$student['id']][$subject] ?? '';
                                $isLow = $existingGrade !== '' && (
                                    (Subject::usesTenPointSystem($classId) && $existingGrade <= 4) ||
                                    (!Subject::usesTenPointSystem($classId) && $existingGrade < 50)
                                );
                            ?>
                            <td style="text-align: center;">
                                <input type="number" 
                                       name="grades[<?= $student['id'] ?>][<?= $subject ?>]"
                                       class="grade-input <?= $isLow ? 'low-grade' : '' ?>"
                                       value="<?= $existingGrade ?>"
                                       min="0" max="<?= $maxGrade ?>"
                                       step="<?= Subject::usesTenPointSystem($classId) ? '0.5' : '1' ?>"
                                       data-max="<?= $maxGrade ?>"
                                       data-passing="<?= $passingGrade ?>"
                                       placeholder="-"
                                       <?= !$canEdit ? 'readonly' : '' ?>>
                            </td>
                            <?php endforeach; ?>
                            <td style="text-align: center;">
                                <strong class="student-total">-</strong>
                            </td>
                            <td style="text-align: center;">
                                <span class="student-result badge">-</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($canEdit): ?>
            <div class="d-flex justify-between align-center mt-3">
                <div class="grade-legend d-flex gap-2">
                    <span class="legend-item"><span class="color-box success"></span> <?= __('ناجح') ?></span>
                    <span class="legend-item"><span class="color-box warning"></span> <?= __('مكمّل') ?></span>
                    <span class="legend-item"><span class="color-box danger"></span> <?= __('راسب') ?></span>
                </div>
                <button type="submit" class="btn btn-primary btn-lg">
                    💾 <?= __('حفظ الدرجات') ?>
                </button>
            </div>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<style>
.grades-table {
    width: 100%;
    border-collapse: collapse;
}

.grades-table th, .grades-table td {
    padding: 0.75rem 0.5rem;
    border: 1px solid var(--border);
    vertical-align: middle;
}

.grades-table th {
    background: var(--bg-secondary);
    font-weight: 600;
}

.grades-table tbody tr:hover {
    background: var(--bg-hover);
}

.grade-input {
    width: 60px;
    padding: 0.5rem;
    text-align: center;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    background: var(--bg-primary);
    color: var(--text-primary);
    font-weight: 600;
    transition: all 0.2s ease;
}

.grade-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.2);
}

.grade-input.low-grade {
    background: #fee2e2;
    border-color: #ef4444;
    color: #dc2626;
}

.grade-input[readonly] {
    background: var(--bg-secondary);
    cursor: not-allowed;
}

.student-total {
    font-size: 1.1rem;
    color: var(--primary);
}

.grade-legend {
    display: flex;
    gap: 1rem;
    font-size: 0.9rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.color-box {
    width: 16px;
    height: 16px;
    border-radius: 4px;
}

.color-box.success { background: #22c55e; }
.color-box.warning { background: #f59e0b; }
.color-box.danger { background: #ef4444; }

@media (max-width: 768px) {
    .grade-input {
        width: 50px;
        padding: 0.4rem;
        font-size: 0.85rem;
    }
    
    .grades-table th, .grades-table td {
        padding: 0.5rem 0.25rem;
        font-size: 0.85rem;
    }
}

/* ═══════════════════════════════════════════════════════════════ */
/* أنماط النظام الشهري للصفوف 5 و 6 */
/* ═══════════════════════════════════════════════════════════════ */
.monthly-table .group-header th {
    font-weight: 700;
    font-size: 0.85rem;
}

.monthly-table .columns-header th {
    background: var(--bg-tertiary, #f1f5f9);
    font-weight: 600;
}

.monthly-input {
    width: 50px !important;
    padding: 0.35rem !important;
    font-size: 0.8rem !important;
}

.calc-field {
    display: inline-block;
    padding: 0.35rem 0.5rem;
    background: #e0f2fe;
    border-radius: 4px;
    font-weight: 700;
    color: #0369a1;
    font-size: 0.8rem;
    min-width: 45px;
}

.result-badge {
    font-weight: 700;
    font-size: 0.75rem;
}

.note-input {
    width: 70px;
    padding: 0.3rem;
    text-align: center;
    border: 1px solid var(--border);
    border-radius: 4px;
    font-size: 0.7rem;
}

@media print {
    .monthly-input, .note-input {
        border: none !important;
        background: transparent !important;
        width: auto !important;
    }
}

/* ═══════════════════════════════════════════════════════════════
   إشعارات Toast
   ═══════════════════════════════════════════════════════════════ */
.grade-toast {
    position: fixed;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%) translateY(100px);
    background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
    color: white;
    padding: 1rem 2rem;
    border-radius: 50px;
    box-shadow: 0 10px 40px rgba(34, 197, 94, 0.4);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 600;
    font-size: 1rem;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

.grade-toast.show {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(0);
}

.grade-toast .toast-icon {
    font-size: 1.5rem;
    animation: pulse 0.5s ease;
}

.grade-toast .toast-text {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.grade-toast .toast-title {
    font-size: 1rem;
    font-weight: 700;
}

.grade-toast .toast-subtitle {
    font-size: 0.8rem;
    opacity: 0.9;
}

@keyframes pulse {
    0% { transform: scale(0); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
}

.grade-toast.error {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    box-shadow: 0 10px 40px rgba(239, 68, 68, 0.4);
}
</style>

<!-- Toast إشعار الحفظ -->
<div id="gradeToast" class="grade-toast">
    <span class="toast-icon">✅</span>
    <div class="toast-text">
        <span class="toast-title"><?= __('تم الحفظ بنجاح!') ?></span>
        <span class="toast-subtitle"><?= __('تم حفظ الدرجات تلقائياً') ?></span>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('gradesForm');
    if (!form) return;
    
    const maxGrade = <?= $maxGrade ?>;
    const passingGrade = <?= $passingGrade ?>;
    const usesTenPoint = <?= Subject::usesTenPointSystem($classId) ? 'true' : 'false' ?>;
    
    // تحديث حساب الدرجات عند التغيير
    form.querySelectorAll('.grade-input').forEach(input => {
        input.addEventListener('input', function() {
            // التحقق من الحد الأقصى
            if (parseFloat(this.value) > maxGrade) {
                this.value = maxGrade;
            }
            if (parseFloat(this.value) < 0) {
                this.value = 0;
            }
            
            // تحديث لون الحقل
            const val = parseFloat(this.value) || 0;
            if (usesTenPoint) {
                this.classList.toggle('low-grade', val <= 4 && val > 0);
            } else {
                this.classList.toggle('low-grade', val < 50 && val > 0);
            }
            
            // تحديث المجموع والنتيجة
            updateRowTotals(this.closest('tr'));
        });
    });
    
    function updateRowTotals(row) {
        if (!row) return;
        
        const inputs = row.querySelectorAll('.grade-input:not(.monthly-input)');
        let total = 0;
        let count = 0;
        let failedCount = 0;
        
        inputs.forEach(input => {
            const val = parseFloat(input.value);
            if (!isNaN(val) && val >= 0) {
                total += val;
                count++;
                
                // حساب المواد الراسبة
                if (usesTenPoint) {
                    if (val <= 4) failedCount++;
                } else {
                    if (val < 50) failedCount++;
                }
            }
        });
        
        // تحديث المجموع
        const totalCell = row.querySelector('.student-total');
        if (totalCell) {
            if (count > 0) {
                totalCell.textContent = total.toFixed(usesTenPoint ? 1 : 0);
            } else {
                totalCell.textContent = '-';
            }
        }
        
        // تحديث النتيجة
        const resultCell = row.querySelector('.student-result');
        if (resultCell) {
            if (count > 0) {
                if (failedCount >= 3) {
                    resultCell.textContent = 'راسب';
                    resultCell.className = 'student-result badge badge-danger';
                } else if (failedCount >= 1) {
                    resultCell.textContent = 'مكمّل';
                    resultCell.className = 'student-result badge badge-warning';
                } else {
                    resultCell.textContent = 'ناجح';
                    resultCell.className = 'student-result badge badge-success';
                }
            } else {
                resultCell.textContent = '-';
                resultCell.className = 'student-result badge';
            }
        }
    }
    
    // تحديث جميع الصفوف عند التحميل
    form.querySelectorAll('tbody tr').forEach(row => {
        updateRowTotals(row);
    });
    
    // ═══════════════════════════════════════════════════════════════
    // حسابات النظام الشهري للصفوف 5 و 6
    // ═══════════════════════════════════════════════════════════════
    const isMonthlySystem = <?= $isMonthlySystem ? 'true' : 'false' ?>;
    // Monthly system initialized
    
    if (isMonthlySystem) {
        // تحديث الحسابات عند تغيير أي قيمة
        const monthlyInputs = form.querySelectorAll('.monthly-input');
        
        monthlyInputs.forEach(input => {
            input.addEventListener('input', function() {
                const max = parseFloat(this.getAttribute('max')) || 100;
                if (parseFloat(this.value) > max) this.value = max;
                if (parseFloat(this.value) < 0) this.value = 0;
                
                // تحديث اللون
                const val = parseFloat(this.value) || 0;
                this.classList.toggle('low-grade', val < 50 && val > 0);
                
                // تحديث الحسابات للصف
                updateMonthlyRowCalc(this.closest('tr'));
            });
        });
        
        function updateMonthlyRowCalc(row) {
            const getValue = (col) => {
                const input = row.querySelector(`[data-col="${col}"]`);
                if (!input) return 0;
                if (input.tagName === 'INPUT') {
                    return parseFloat(input.value) || 0;
                } else {
                    return parseFloat(input.textContent) || 0;
                }
            };
            
            const setValue = (col, val) => {
                const span = row.querySelector(`span[data-col="${col}"]`);
                const hidden = row.querySelector(`input.calc-hidden[data-col="${col}"], input.result-hidden[data-col="${col}"]`);
                if (span && span.classList.contains('calc-field')) {
                    span.textContent = val !== null ? val.toFixed(1) : '-';
                }
                if (hidden) hidden.value = val !== null ? val.toFixed(1) : '';
            };
            
            // حساب معدل النصف الأول = (تشرين الأول + تشرين الثاني + كانون الأول) / 3
            // القيمة الفارغة = 0 مثل Excel
            const oct = getValue('oct');
            const nov = getValue('nov');
            const dec = getValue('dec');
            const firstAvg = (oct + nov + dec) / 3;
            setValue('first_avg', firstAvg);
            
            // حساب معدل النصف الثاني = (آذار + نيسان) / 2
            const mar = getValue('mar');
            const apr = getValue('apr');
            const secondAvg = (mar + apr) / 2;
            setValue('second_avg', secondAvg);
            
            // معدل السعي السنوي = (معدل النصف الأول + امتحان نصف السنة + معدل النصف الثاني) / 3
            const midExam = getValue('mid_exam');
            const yearlyAvg = (firstAvg + midExam + secondAvg) / 3;
            setValue('yearly_avg', yearlyAvg);
            
            // الدرجة النهائية = (معدل السعي السنوي + الامتحان النهائي) / 2
            const finalExam = getValue('final_exam');
            const finalGrade = (yearlyAvg + finalExam) / 2;
            setValue('final_grade', finalGrade);
            
            // النتيجة
            const resultSpan = row.querySelector('span.result-badge');
            const resultHidden = row.querySelector('input.result-hidden');
            if (finalGrade !== null) {
                let result, badgeClass;
                if (finalGrade >= 50) {
                    result = 'ناجح';
                    badgeClass = 'badge badge-success';
                } else if (finalGrade >= 40) {
                    result = 'مكمّل';
                    badgeClass = 'badge badge-warning';
                } else {
                    result = 'راسب';
                    badgeClass = 'badge badge-danger';
                }
                if (resultSpan) {
                    resultSpan.textContent = result;
                    resultSpan.className = 'result-badge ' + badgeClass;
                }
                if (resultHidden) resultHidden.value = result;
            } else {
                if (resultSpan) {
                    resultSpan.textContent = '-';
                    resultSpan.className = 'result-badge badge';
                }
                if (resultHidden) resultHidden.value = '';
            }
        }
        
        // تحديث جميع الصفوف عند التحميل
        form.querySelectorAll('tbody tr').forEach(row => {
            updateMonthlyRowCalc(row);
        });
    }
    
    // ═══════════════════════════════════════════════════════════════
    // 🔄 تحميل الدرجات عبر AJAX بدون إعادة تحميل الصفحة
    // ═══════════════════════════════════════════════════════════════
    function loadGradesData() {
        const classId = document.getElementById('gradesClassId')?.value || '';
        const section = document.getElementById('gradesSection')?.value || '';
        const term = document.getElementById('gradesTerm')?.value || '';
        const year = document.getElementById('gradesYear')?.value || '';
        const search = document.getElementById('gradesSearch')?.value || '';
        
        // بناء URL جديد
        const url = new URL(window.location.pathname, window.location.origin);
        if (classId) url.searchParams.set('class_id', classId);
        if (section) url.searchParams.set('section', section);
        if (term) url.searchParams.set('term', term);
        if (year) url.searchParams.set('year', year);
        if (search) url.searchParams.set('search', search);
        
        // إعادة تحميل الصفحة مع الفلاتر الجديدة
        window.location.href = url.href;
    }
    
    function initGradeInputs() {
        // إعادة ربط الـ event listeners بعد تحديث المحتوى
        document.querySelectorAll('.grade-input, .monthly-input').forEach(input => {
            input.addEventListener('input', function() {
                const max = parseFloat(this.getAttribute('max')) || 100;
                if (parseFloat(this.value) > max) this.value = max;
                if (parseFloat(this.value) < 0) this.value = 0;
            });
        });
    }
    
    // ربط Select للتغيير - الفلترة التلقائية
    ['gradesClassId', 'gradesSection', 'gradesTerm', 'gradesYear'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', loadGradesData);
        }
    });
    
    // ربط البحث الفوري (بدون reload)
    const searchInput = document.getElementById('gradesSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterStudentsTable(this.value);
        });
        
        // منع إرسال النموذج عند الضغط على Enter
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
    }
});

// دالة فلترة جدول الطلاب بدون reload
function filterStudentsTable(searchText) {
    const searchValue = searchText.trim().toLowerCase();
    const tables = document.querySelectorAll('.grades-table, .monthly-grades-table');
    
    tables.forEach(table => {
        const tbody = table.querySelector('tbody');
        if (!tbody) return;
        
        const rows = tbody.querySelectorAll('tr');
        let visibleCount = 0;
        
        rows.forEach(row => {
            // الحصول على اسم الطالب و ID
            const nameCell = row.querySelector('td:nth-child(2)');
            const idCell = row.querySelector('td:first-child');
            
            if (!nameCell) return;
            
            const studentName = nameCell.textContent.toLowerCase();
            const studentId = idCell ? idCell.textContent.trim() : '';
            
            // التحقق من التطابق
            const matches = searchValue === '' || 
                            studentName.includes(searchValue) || 
                            studentId.includes(searchValue);
            
            if (matches) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // إظهار رسالة إذا لم يتم العثور على نتائج
        let noResultsRow = tbody.querySelector('.no-search-results');
        if (visibleCount === 0 && searchValue !== '') {
            if (!noResultsRow) {
                const colCount = table.querySelector('thead tr')?.children.length || 10;
                noResultsRow = document.createElement('tr');
                noResultsRow.className = 'no-search-results';
                noResultsRow.innerHTML = `<td colspan="${colCount}" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                    🔍 لا توجد نتائج مطابقة للبحث "${searchText}"
                </td>`;
                tbody.appendChild(noResultsRow);
            }
        } else if (noResultsRow) {
            noResultsRow.remove();
        }
    });
}

// دالة إعادة تعيين الفلاتر
function resetGradesFilter() {
    // إعادة تعيين الفلاتر
    const classSelect = document.getElementById('gradesClassId');
    const sectionSelect = document.getElementById('gradesSection');
    const termSelect = document.getElementById('gradesTerm');
    const yearSelect = document.getElementById('gradesYear');
    const searchInput = document.getElementById('gradesSearch');
    
    if (classSelect) classSelect.selectedIndex = 0;
    if (sectionSelect) sectionSelect.selectedIndex = 0;
    if (termSelect) termSelect.value = 'first';
    if (yearSelect) yearSelect.value = new Date().getFullYear();
    if (searchInput) {
        searchInput.value = '';
        filterStudentsTable(''); // إظهار جميع الطلاب
    }
    
    // إعادة تحميل البيانات
    const url = new URL(window.location);
    url.searchParams.delete('class_id');
    url.searchParams.delete('section');
    url.searchParams.delete('term');
    url.searchParams.delete('year');
    url.searchParams.delete('search');
    window.location.href = url.pathname;
}

// ═══════════════════════════════════════════════════════════════
// 🔄 تبديل المادة بـ AJAX بدون إعادة تحميل الصفحة
// ═══════════════════════════════════════════════════════════════
const monthlySubjectSelect = document.getElementById('monthlySubjectSelect');
if (monthlySubjectSelect) {
    monthlySubjectSelect.addEventListener('change', function() {
        const subject = this.value;
        const loadingIndicator = document.getElementById('subjectLoadingIndicator');
        
        // تحديث URL بدون إعادة تحميل
        const url = new URL(window.location);
        url.searchParams.set('subject', subject);
        window.history.pushState({}, '', url);
        
        // إظهار مؤشر التحميل
        if (loadingIndicator) loadingIndicator.style.display = 'inline';
        
        // جلب البيانات الجديدة
        fetch(`api.php?module=grades&action=get_monthly&class_id=<?= $classId ?>&section=<?= urlencode($section) ?>&subject=${encodeURIComponent(subject)}&year=<?= $academicYear ?>`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // تحديث قيم الحقول
                    updateMonthlyGradesTable(data.grades);
                    
                    // تحديث حقل المادة المخفي في النموذج
                    const subjectInput = document.querySelector('input[name="subject"]');
                    if (subjectInput) subjectInput.value = subject;
                }
                if (loadingIndicator) loadingIndicator.style.display = 'none';
            })
            .catch(err => {
                console.error('Error loading grades:', err);
                if (loadingIndicator) loadingIndicator.style.display = 'none';
            });
    });
}

// تحديث جدول الدرجات الشهرية
function updateMonthlyGradesTable(grades) {
    const table = document.querySelector('.monthly-table tbody');
    if (!table) return;
    
    // تفريغ جميع الحقول أولاً
    table.querySelectorAll('input.monthly-input, input.note-input').forEach(input => {
        input.value = '';
    });
    table.querySelectorAll('.calc-field').forEach(span => {
        span.textContent = '-';
    });
    table.querySelectorAll('.calc-hidden, .result-hidden').forEach(input => {
        input.value = '';
    });
    
    // ملء البيانات الجديدة
    grades.forEach(g => {
        const row = table.querySelector(`tr[data-student-id="${g.student_id}"]`);
        if (!row) return;
        
        // ملء الحقول
        const columns = ['oct', 'nov', 'dec', 'first_avg', 'mid_exam', 'mar', 'apr', 'second_avg', 'yearly_avg', 'final_exam', 'final_grade', 'notes'];
        columns.forEach(col => {
            const input = row.querySelector(`input[data-col="${col}"]`);
            const span = row.querySelector(`.calc-field[data-col="${col}"]`);
            const hidden = row.querySelector(`.calc-hidden[data-col="${col}"], .result-hidden[data-col="${col}"]`);
            
            if (g[col] !== null && g[col] !== undefined) {
                if (input) input.value = g[col];
                if (span) span.textContent = typeof g[col] === 'number' ? g[col].toFixed(1) : g[col];
                if (hidden) hidden.value = g[col];
            }
        });
        
        // تحديث الحسابات
        updateMonthlyRowCalc(row);
    });
}

// ═══════════════════════════════════════════════════════════════
// 💾 حفظ تلقائي للدرجات
// ═══════════════════════════════════════════════════════════════
let autoSaveTimeout = null;
const autoSaveDelay = 2000; // 2 ثواني بعد آخر تعديل

function initAutoSave() {
    const form = document.getElementById('gradesForm');
    if (!form) return;
    
    form.querySelectorAll('input.monthly-input, input.note-input, input.grade-input').forEach(input => {
        input.addEventListener('change', function() {
            // إلغاء المؤقت السابق
            if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
            
            // بدء مؤقت جديد للحفظ
            autoSaveTimeout = setTimeout(() => {
                autoSaveGrades();
            }, autoSaveDelay);
        });
    });
}

function autoSaveGrades() {
    const form = document.getElementById('gradesForm');
    if (!form) return;
    
    const toast = document.getElementById('gradeToast');
    const formData = new FormData(form);
    
    fetch('controllers/grade_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        // إظهار إشعار Toast
        showGradeToast();
    })
    .catch(err => {
        console.error('Auto-save error:', err);
        showGradeToast(true); // error
    });
}

// دالة إظهار Toast
function showGradeToast(isError = false) {
    const toast = document.getElementById('gradeToast');
    if (!toast) return;
    
    if (isError) {
        toast.classList.add('error');
        toast.querySelector('.toast-icon').textContent = '❌';
        toast.querySelector('.toast-title').textContent = '<?= __("خطأ في الحفظ") ?>';
        toast.querySelector('.toast-subtitle').textContent = '<?= __("حاول مرة أخرى") ?>';
    } else {
        toast.classList.remove('error');
        toast.querySelector('.toast-icon').textContent = '✅';
        toast.querySelector('.toast-title').textContent = '<?= __("تم الحفظ بنجاح!") ?>';
        toast.querySelector('.toast-subtitle').textContent = '<?= __("تم حفظ الدرجات تلقائياً") ?>';
    }
    
    // إظهار الإشعار
    toast.classList.add('show');
    
    // إخفاء بعد 3 ثواني
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// تهيئة الحفظ التلقائي
initAutoSave();
</script>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
