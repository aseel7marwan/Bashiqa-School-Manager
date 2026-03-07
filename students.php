<?php
/**
 * إدارة التلاميذ - Students Management
 * عرض وإضافة وتعديل وحذف بيانات التلاميذ
 * 
 * @package SchoolManager
 * @access  مدير ومعلم
 */

$pageTitle = 'إدارة التلاميذ';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/models/Student.php';

requireLogin();

// Students cannot access this page
if (isStudent()) {
    alert('ليس لديك صلاحية للوصول لهذه الصفحة', 'error');
    redirect('student_profile.php');
}

$studentModel = new Student();

// API للحصول على الشعب الموجودة لصف معين
if (isset($_GET['ajax']) && $_GET['ajax'] === 'sections') {
    header('Content-Type: application/json');
    $classId = $_GET['class_id'] ?? null;
    $sections = $studentModel->getAvailableSections($classId);
    echo json_encode($sections);
    exit;
}

$action = $_GET['action'] ?? 'list';
$classFilter = $_GET['class_id'] ?? null;
$sectionFilter = $_GET['section'] ?? null;
$searchQuery = $_GET['search'] ?? '';

// الحصول على الصفوف والشعب الموجودة فعلياً
$availableClasses = $studentModel->getAvailableClasses();
$availableSections = $studentModel->getAvailableSections($classFilter);

if ($searchQuery) {
    $students = $studentModel->search($searchQuery);
    $groupedStudents = null;
} else {
    $students = $studentModel->getAll($classFilter, $sectionFilter);
    $groupedStudents = $studentModel->getGroupedByClassAndSection($classFilter, $sectionFilter);
}

$editStudent = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $editStudent = $studentModel->findById((int)$_GET['id']);
}

require_once __DIR__ . '/views/components/header.php';
?>

<div class="page-header d-flex justify-between align-center flex-wrap gap-2">
    <div>
        <h1>إدارة التلاميذ</h1>
        <p>عدد التلاميذ: <?= count($students) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (isAdmin()): ?>
        <a href="?action=add" class="btn btn-primary">
            ➕ إضافة تلميذ جديد
        </a>
        <?php endif; ?>
        
        <!-- أزرار التصدير -->
        <div class="dropdown" style="position: relative;">
            <button class="btn btn-success dropdown-toggle" onclick="toggleStudentExportMenu()" id="studentExportBtn">
                📥 تصدير
            </button>
            <div class="dropdown-menu" id="studentExportMenu" style="display: none; position: absolute; top: 100%; left: 0; background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-lg); z-index: 100; min-width: 180px;">
                <a href="/export_report.php?type=students_list&format=pdf<?= $classFilter ? '&class_id=' . $classFilter : '' ?><?= $sectionFilter ? '&section=' . urlencode($sectionFilter) : '' ?>" 
                   class="dropdown-item" target="_blank" style="display: flex; align-items: center; gap: 8px; padding: 10px 15px; text-decoration: none; color: var(--text-primary);">
                    📄 PDF
                </a>
                <a href="/export_report.php?type=students_list&format=word<?= $classFilter ? '&class_id=' . $classFilter : '' ?><?= $sectionFilter ? '&section=' . urlencode($sectionFilter) : '' ?>" 
                   class="dropdown-item" style="display: flex; align-items: center; gap: 8px; padding: 10px 15px; text-decoration: none; color: var(--text-primary);">
                    📝 Word
                </a>
                <a href="/export_report.php?type=students_list&format=excel<?= $classFilter ? '&class_id=' . $classFilter : '' ?><?= $sectionFilter ? '&section=' . urlencode($sectionFilter) : '' ?>" 
                   class="dropdown-item" style="display: flex; align-items: center; gap: 8px; padding: 10px 15px; text-decoration: none; color: var(--text-primary);">
                    📊 Excel
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function toggleStudentExportMenu() {
    const menu = document.getElementById('studentExportMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', function(e) {
    const dropdown = document.querySelector('.dropdown');
    const menu = document.getElementById('studentExportMenu');
    if (dropdown && menu && !dropdown.contains(e.target)) {
        menu.style.display = 'none';
    }
});
</script>

<!-- أنماط متجاوبة للهواتف -->
<style>
/* ═══════════════════════════════════════════════════════════════════
   أنماط متجاوبة لصفحة إدارة التلاميذ - Mobile Responsive Styles
   ═══════════════════════════════════════════════════════════════════ */

/* ═══ الشاشات المتوسطة (تابلت) ═══ */
@media (max-width: 992px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 1rem;
    }
    .page-header > div:last-child {
        width: 100%;
        justify-content: flex-start;
    }
}

/* ═══ الشاشات الصغيرة (هواتف) ═══ */
@media (max-width: 768px) {
    /* صفحة الرأس */
    .page-header h1 {
        font-size: 1.3rem;
    }
    .page-header p {
        font-size: 0.85rem;
    }
    
    /* أزرار الرأس */
    .page-header .btn {
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
    }
    
    /* قسم الفلاتر */
    .card-header > .d-flex:last-child {
        flex-direction: column !important;
        align-items: stretch !important;
    }
    
    .card-header .d-flex.align-center.gap-1 {
        width: 100%;
    }
    
    .card-header label {
        font-size: 0.8rem !important;
        min-width: auto !important;
    }
    
    .card-header select.form-control,
    .card-header input.form-control {
        width: 100% !important;
        min-width: 0 !important;
        flex: 1;
    }
    
    #studentSearchLive {
        min-width: 100% !important;
    }
    
    /* تحسين الجدول على الموبايل - تمرير أفقي مثل صفحة المعلمين */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .table-responsive table {
        min-width: 700px;
    }
    
    /* إخفاء بطاقات الموبايل - استخدام الجدول فقط */
    .student-mobile-cards {
        display: none !important;
    }
    
    /* إظهار بطاقات الطلاب بدلاً من الجدول */
    .student-mobile-cards {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        padding: 0.75rem;
    }
    
    .student-mobile-card {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .student-mobile-card .student-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
        color: white;
    }
    
    .student-mobile-card .student-avatar img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }
    
    .student-mobile-card .student-info {
        flex: 1;
        min-width: 0;
    }
    
    .student-mobile-card .student-name {
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .student-mobile-card .student-meta {
        font-size: 0.75rem;
        color: var(--text-secondary);
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .student-mobile-card .student-actions {
        display: flex;
        gap: 0.5rem;
        flex-shrink: 0;
    }
    
    .student-mobile-card .student-actions .btn {
        width: 36px;
        height: 36px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-size: 0.9rem;
    }
    
    /* رأس المجموعة على الهاتف */
    .group-header {
        border-radius: 8px !important;
        margin-bottom: 0.5rem;
    }
    
    .group-header h4 {
        font-size: 0.9rem !important;
    }
    
    /* زر مسح الفلاتر */
    .card-header .btn-secondary.btn-sm {
        width: 100%;
        margin-top: 0.5rem;
    }
    
    /* Modal على الهاتف */
    .modal-content {
        width: 95% !important;
        max-width: 95% !important;
        max-height: 85vh !important;
        margin: 0 auto;
    }
    
    #viewStudentModal .modal-content {
        max-width: 95% !important;
    }
    
    #viewStudentModal .modal-body > div {
        padding: 1rem !important;
    }
    
    #viewStudentModal .modal-body .grid,
    #viewStudentModal .modal-body div[style*="grid-template-columns: repeat(4"] {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.5rem !important;
    }
    
    /* Header ثابت في Modal */
    #viewStudentModal .modal-header {
        position: sticky;
        top: 0;
        z-index: 10;
    }
}

/* ═══ الشاشات الصغيرة جداً ═══ */
@media (max-width: 480px) {
    .page-header h1 {
        font-size: 1.1rem;
    }
    
    .page-header .btn {
        width: 100%;
        justify-content: center;
    }
    
    .page-header > div:last-child {
        flex-direction: column;
    }
    
    .student-mobile-card {
        padding: 0.75rem;
    }
    
    .student-mobile-card .student-avatar {
        width: 42px;
        height: 42px;
        font-size: 1.2rem;
    }
    
    .student-mobile-card .student-name {
        font-size: 0.9rem;
    }
    
    .student-mobile-card .student-actions .btn {
        width: 32px;
        height: 32px;
        font-size: 0.8rem;
    }
    
    #viewStudentModal .modal-body div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
}

/* إخفاء العناصر على سطح المكتب */
@media (min-width: 769px) {
    .student-mobile-cards {
        display: none !important;
    }
}
</style>

<?php if ($action === 'add' || $action === 'edit'): ?>
<?php if (isAdmin()): ?>

<style>
/* أنماط نموذج التلميذ - مشابه للاستمارة الورقية */
.form-section {
    background: var(--bg-secondary);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid var(--border-color);
}
.form-section-title {
    color: #333;
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.form-section .grid { gap: 1rem; }
.form-group label {
    font-weight: 600;
    color: #333;
    font-size: 0.9rem;
    margin-bottom: 0.4rem;
    display: block;
}
.form-group label .required { color: var(--danger); margin-right: 2px; }

/* معاينة الصورة */
.photo-preview-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 12px;
    border: 2px dashed var(--border-color);
}
.photo-preview {
    width: 150px;
    height: 180px;
    border-radius: 8px;
    object-fit: cover;
    background: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    color: #adb5bd;
    border: 3px solid var(--primary);
}
.photo-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 6px;
}
.photo-upload-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: var(--primary);
    color: white;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}
.photo-upload-btn:hover { background: var(--primary-dark); transform: translateY(-2px); }
.photo-upload-btn input { display: none; }

/* بطاقات الصور المتعددة */
.multi-photo-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}
@media (max-width: 768px) {
    .multi-photo-grid { grid-template-columns: 1fr; }
}
.photo-card {
    background: white;
    border-radius: 12px;
    padding: 1rem;
    text-align: center;
    border: 1px solid var(--border-color);
}
.photo-card-title {
    font-weight: 600;
    color: #333;
    margin-bottom: 0.75rem;
    font-size: 0.9rem;
}
.photo-card-preview {
    width: 100%;
    height: 120px;
    background: #f8f9fa;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.75rem;
    overflow: hidden;
}
.photo-card-preview img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}
</style>

<div class="card mb-3 fade-in">
    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
        <h3 style="color: white; margin: 0;"><?= $action === 'add' ? '➕ إضافة تلميذ جديد' : '✏️ تعديل بيانات التلميذ' ?></h3>
        <a href="students.php" class="btn btn-secondary btn-sm">← العودة للقائمة</a>
    </div>
    <div class="card-body">
        <form action="controllers/student_handler.php" method="POST" enctype="multipart/form-data" id="studentForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="<?= $action ?>">
            <?php if ($editStudent): ?>
            <input type="hidden" name="id" value="<?= $editStudent['id'] ?>">
            <?php endif; ?>
            
            <div class="student-form-layout">
                <div>
                    <!-- ═══ القسم 1: البيانات الأولية ═══ -->
                    <div class="form-section">
                        <div class="form-section-title">📋 البيانات الأولية</div>
                        <div class="grid grid-3">
                            <div class="form-group">
                                <label><span class="required">*</span> اسم التلميذ الثلاثي ولقبه</label>
                                <input type="text" name="full_name" class="form-control" required
                                       value="<?= sanitize($editStudent['full_name'] ?? '') ?>"
                                       placeholder="مثال: علي محمد حسين الطائي">
                            </div>
                            <div class="form-group">
                                <label><span class="required">*</span> المحافظة</label>
                                <input type="text" name="province" class="form-control"
                                       value="<?= sanitize($editStudent['province'] ?? '') ?>"
                                       placeholder="مثال: نينوى">
                            </div>
                            <div class="form-group">
                                <label>المدينة أو القرية</label>
                                <input type="text" name="city_village" class="form-control"
                                       value="<?= sanitize($editStudent['city_village'] ?? '') ?>"
                                       placeholder="مثال: بعشيقة">
                            </div>
                            <div class="form-group">
                                <label><span class="required">*</span> الجنس</label>
                                <select name="gender" class="form-control">
                                    <option value="male" <?= ($editStudent['gender'] ?? 'male') === 'male' ? 'selected' : '' ?>>ذكر</option>
                                    <option value="female" <?= ($editStudent['gender'] ?? '') === 'female' ? 'selected' : '' ?>>أنثى</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>ترتيب التلميذ بين أخوته وأخواته</label>
                                <input type="number" name="sibling_order" class="form-control" min="1"
                                       value="<?= $editStudent['sibling_order'] ?? '' ?>"
                                       placeholder="مثال: 3">
                            </div>
                            <div class="form-group">
                                <label>محل التولد</label>
                                <input type="text" name="birth_place" class="form-control"
                                       value="<?= sanitize($editStudent['birth_place'] ?? '') ?>"
                                       placeholder="مثال: موصل">
                            </div>
                            <div class="form-group">
                                <label>تاريخ التولد</label>
                                <input type="date" name="birth_date" class="form-control"
                                       value="<?= $editStudent['birth_date'] ?? '' ?>">
                            </div>
                            <div class="form-group" style="grid-column: span 2;">
                                <label>عنوان المسكن</label>
                                <input type="text" name="address" class="form-control"
                                       value="<?= sanitize($editStudent['address'] ?? '') ?>"
                                       placeholder="العنوان الكامل">
                            </div>
                        </div>
                    </div>
                    
                    <!-- ═══ القسم 2: بيانات الصف ═══ -->
                    <div class="form-section">
                        <div class="form-section-title">🏫 بيانات الصف والتسجيل</div>
                        <div class="grid grid-3">
                            <div class="form-group">
                                <label><span class="required">*</span> الصف</label>
                                <select name="class_id" class="form-control" required id="classIdSelect">
                                    <option value="">-- اختر الصف --</option>
                                    <?php foreach (CLASSES as $id => $name): ?>
                                    <option value="<?= $id ?>" <?= ($editStudent['class_id'] ?? '') == $id ? 'selected' : '' ?>><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><span class="required">*</span> الشعبة</label>
                                <select name="section" class="form-control" required id="sectionSelect">
                                    <option value="">-- اختر الشعبة --</option>
                                    <?php foreach (SECTIONS as $sec): ?>
                                    <option value="<?= $sec ?>" <?= ($editStudent['section'] ?? '') === $sec ? 'selected' : '' ?>><?= $sec ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>رقمه في سجل القيد العام</label>
                                <input type="text" name="registration_number" class="form-control"
                                       value="<?= sanitize($editStudent['registration_number'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>تاريخ الالتحاق بالمدرسة</label>
                                <input type="date" name="enrollment_date" class="form-control"
                                       value="<?= $editStudent['enrollment_date'] ?? '' ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- ═══ قسم المدارس السابقة ═══ -->
                    <div class="form-section">
                        <div class="form-section-title">🏛️ المدارس والمعاهد والكليات التي التحق بها التلميذ/الطالب خلال سنوات الدراسة</div>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd; font-size: 0.9rem;">
                                <thead style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                    <tr>
                                        <th style="padding: 12px 8px; border: 1px solid #ddd; text-align: center;">اسم المدرسة أو المعهد أو الكلية</th>
                                        <th style="padding: 12px 8px; border: 1px solid #ddd; text-align: center; width: 100px;">المحافظة</th>
                                        <th style="padding: 12px 8px; border: 1px solid #ddd; text-align: center; width: 120px;">تاريخ الالتحاق</th>
                                        <th style="padding: 12px 8px; border: 1px solid #ddd; text-align: center; width: 100px;">رقمه في سجل القيد العام</th>
                                        <th style="padding: 12px 8px; border: 1px solid #ddd; text-align: center; width: 120px;">تاريخ الانتقال إلى مدرسة أخرى</th>
                                        <th style="padding: 12px 8px; border: 1px solid #ddd; text-align: center;">الملاحظات</th>
                                    </tr>
                                </thead>
                                <tbody id="previousSchoolsTable">
                                    <!-- سيتم ملء الصفوف ديناميكياً -->
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex gap-2 mt-2" style="justify-content: center;">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addSchoolRow()" style="padding: 8px 16px;">
                                ➕ إضافة مدرسة
                            </button>
                        </div>
                        <!-- حقل مخفي لتخزين البيانات -->
                        <textarea name="previous_schools" id="previousSchoolsData" style="display: none;"><?= sanitize($editStudent['previous_schools'] ?? '') ?></textarea>
                    </div>
                    
                    <script>
                    let schoolRowCount = 0;
                    
                    function addSchoolRow(data = {}) {
                        schoolRowCount++;
                        const tbody = document.getElementById('previousSchoolsTable');
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td style="padding: 8px; border: 1px solid #ddd;">
                                <input type="text" class="form-control school-field" data-field="name" 
                                       value="${data.name || ''}" placeholder="اسم المدرسة" style="font-size: 0.85rem;">
                            </td>
                            <td style="padding: 8px; border: 1px solid #ddd;">
                                <input type="text" class="form-control school-field" data-field="province" 
                                       value="${data.province || ''}" placeholder="المحافظة" style="font-size: 0.85rem;">
                            </td>
                            <td style="padding: 8px; border: 1px solid #ddd;">
                                <input type="date" class="form-control school-field" data-field="enrollment_date" 
                                       value="${data.enrollment_date || ''}" style="font-size: 0.85rem;">
                            </td>
                            <td style="padding: 8px; border: 1px solid #ddd;">
                                <input type="text" class="form-control school-field" data-field="reg_number" 
                                       value="${data.reg_number || ''}" placeholder="الرقم" style="font-size: 0.85rem;">
                            </td>
                            <td style="padding: 8px; border: 1px solid #ddd;">
                                <input type="date" class="form-control school-field" data-field="transfer_date" 
                                       value="${data.transfer_date || ''}" style="font-size: 0.85rem;">
                            </td>
                            <td style="padding: 8px; border: 1px solid #ddd;">
                                <div style="display: flex; gap: 4px;">
                                    <input type="text" class="form-control school-field" data-field="notes" 
                                           value="${data.notes || ''}" placeholder="ملاحظات" style="font-size: 0.85rem; flex: 1;">
                                    <button type="button" onclick="this.closest('tr').remove(); updateSchoolsData();" 
                                            style="background: #ef4444; color: white; border: none; border-radius: 4px; padding: 4px 8px; cursor: pointer;">✕</button>
                                </div>
                            </td>
                        `;
                        tbody.appendChild(tr);
                        
                        // إضافة مستمعات للتحديث التلقائي
                        tr.querySelectorAll('.school-field').forEach(input => {
                            input.addEventListener('change', updateSchoolsData);
                            input.addEventListener('blur', updateSchoolsData);
                        });
                    }
                    
                    function updateSchoolsData() {
                        const rows = document.querySelectorAll('#previousSchoolsTable tr');
                        const schools = [];
                        rows.forEach(row => {
                            const school = {};
                            row.querySelectorAll('.school-field').forEach(input => {
                                school[input.dataset.field] = input.value;
                            });
                            // فقط أضف إذا كان الاسم موجوداً
                            if (school.name && school.name.trim()) {
                                schools.push(school);
                            }
                        });
                        document.getElementById('previousSchoolsData').value = JSON.stringify(schools);
                    }
                    
                    // تحميل البيانات الموجودة عند فتح الصفحة
                    document.addEventListener('DOMContentLoaded', function() {
                        const existingData = document.getElementById('previousSchoolsData').value;
                        if (existingData && existingData.trim()) {
                            try {
                                const schools = JSON.parse(existingData);
                                if (Array.isArray(schools)) {
                                    schools.forEach(school => addSchoolRow(school));
                                }
                            } catch (e) {
                                // إذا كانت البيانات بالتنسيق القديم (نص)، أضف صف فارغ
                                addSchoolRow();
                            }
                        } else {
                            // إضافة صف فارغ افتراضي
                            addSchoolRow();
                        }
                    });
                    </script>
                    
                    <!-- ═══ القسم 3: بيانات ولي الأمر ═══ -->
                    <div class="form-section">
                        <div class="form-section-title">👨‍👩‍👦 بيانات ولي الأمر والوالدين</div>
                        <div class="grid grid-3">
                            <div class="form-group">
                                <label>الاسم الثلاثي لولي الأمر</label>
                                <input type="text" name="parent_name" class="form-control"
                                       value="<?= sanitize($editStudent['parent_name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>صلته بالتلميذ</label>
                                <select name="guardian_relation" class="form-control">
                                    <option value="">-- اختر --</option>
                                    <option value="أب" <?= ($editStudent['guardian_relation'] ?? '') === 'أب' ? 'selected' : '' ?>>أب</option>
                                    <option value="أم" <?= ($editStudent['guardian_relation'] ?? '') === 'أم' ? 'selected' : '' ?>>أم</option>
                                    <option value="أخ" <?= ($editStudent['guardian_relation'] ?? '') === 'أخ' ? 'selected' : '' ?>>أخ</option>
                                    <option value="عم" <?= ($editStudent['guardian_relation'] ?? '') === 'عم' ? 'selected' : '' ?>>عم</option>
                                    <option value="خال" <?= ($editStudent['guardian_relation'] ?? '') === 'خال' ? 'selected' : '' ?>>خال</option>
                                    <option value="جد" <?= ($editStudent['guardian_relation'] ?? '') === 'جد' ? 'selected' : '' ?>>جد</option>
                                    <option value="آخر" <?= ($editStudent['guardian_relation'] ?? '') === 'آخر' ? 'selected' : '' ?>>آخر</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>مهنته</label>
                                <input type="text" name="guardian_job" class="form-control"
                                       value="<?= sanitize($editStudent['guardian_job'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>رقم الهاتف</label>
                                <input type="tel" name="parent_phone" class="form-control"
                                       value="<?= sanitize($editStudent['parent_phone'] ?? '') ?>"
                                       placeholder="07xxxxxxxxx">
                            </div>
                            <div class="form-group">
                                <label>اسم الأم الثلاثي</label>
                                <input type="text" name="mother_name" class="form-control"
                                       value="<?= sanitize($editStudent['mother_name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>هل الأب على قيد الحياة</label>
                                <select name="father_alive" class="form-control">
                                    <option value="نعم" <?= ($editStudent['father_alive'] ?? 'نعم') === 'نعم' ? 'selected' : '' ?>>نعم</option>
                                    <option value="لا" <?= ($editStudent['father_alive'] ?? '') === 'لا' ? 'selected' : '' ?>>لا</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>هل الأم على قيد الحياة</label>
                                <select name="mother_alive" class="form-control">
                                    <option value="نعم" <?= ($editStudent['mother_alive'] ?? 'نعم') === 'نعم' ? 'selected' : '' ?>>نعم</option>
                                    <option value="لا" <?= ($editStudent['mother_alive'] ?? '') === 'لا' ? 'selected' : '' ?>>لا</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>التحصيل الدراسي : للأب</label>
                                <input type="text" name="father_education" class="form-control"
                                       value="<?= sanitize($editStudent['father_education'] ?? '') ?>"
                                       placeholder="مثال: بكالوريوس">
                            </div>
                            <div class="form-group">
                                <label>التحصيل الدراسي : للأم</label>
                                <input type="text" name="mother_education" class="form-control"
                                       value="<?= sanitize($editStudent['mother_education'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>عمر الأب عند تسجيل الطالب في المدرسة</label>
                                <input type="number" name="father_age_at_registration" class="form-control" min="18"
                                       value="<?= $editStudent['father_age_at_registration'] ?? '' ?>">
                            </div>
                            <div class="form-group">
                                <label>عمر الأم عند تسجيل الطالب في المدرسة</label>
                                <input type="number" name="mother_age_at_registration" class="form-control" min="18"
                                       value="<?= $editStudent['mother_age_at_registration'] ?? '' ?>">
                            </div>
                            <div class="form-group">
                                <label>درجة القرابة بين الوالدين</label>
                                <input type="text" name="parents_kinship" class="form-control"
                                       value="<?= sanitize($editStudent['parents_kinship'] ?? '') ?>"
                                       placeholder="مثال: لا توجد / أبناء عم">
                            </div>
                        </div>
                    </div>
                    
                    <!-- ═══ القسم 4: الوثائق ═══ -->
                    <div class="form-section">
                        <div class="form-section-title">🪪 الوثائق والهويات</div>
                        <div class="grid grid-2">
                            <div class="form-group">
                                <label>رقم الجنسية العراقية</label>
                                <input type="text" name="nationality_number" class="form-control"
                                       value="<?= sanitize($editStudent['nationality_number'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- ═══ القسم 5: الحالة الاجتماعية ═══ -->
                    <div class="form-section">
                        <div class="form-section-title">👨‍👩‍👧‍👦 الحالة الاجتماعية</div>
                        <div id="socialStatusContainer"></div>
                        <div class="d-flex gap-2 mt-2" style="justify-content: center;">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addSocialStatusCard()" style="padding: 8px 16px; background: #10b981;">
                                ➕ إضافة سنة دراسية
                            </button>
                        </div>
                        <small style="color: #666; display: block; margin-top: 8px; text-align: center;">
                            ⓘ يُملأ حقل دخل الأسرة بأحدى العبارات: جيد جداً، جيد، متوسط، ضعيف
                        </small>
                        <textarea name="social_status" id="socialStatusData" style="display: none;"><?= sanitize($editStudent['social_status'] ?? '') ?></textarea>
                    </div>
                    
                    <script>
                    function addSocialStatusCard(data = {}) {
                        const container = document.getElementById('socialStatusContainer');
                        const currentYear = new Date().getFullYear();
                        const defaultYear = data.year || `${currentYear}/${currentYear + 1}`;
                        
                        const card = document.createElement('div');
                        card.className = 'social-card';
                        card.style.cssText = 'background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 15px; margin-bottom: 15px; position: relative;';
                        
                        card.innerHTML = `
                            <button type="button" onclick="this.parentElement.remove(); updateSocialStatusData();" 
                                    style="position: absolute; top: 5px; left: 5px; background: #ef4444; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-size: 12px;">✕</button>
                            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
                                <div class="form-group">
                                    <label style="font-size: 0.85rem; font-weight: 600;">السنة الدراسية</label>
                                    <input type="text" class="form-control social-field" data-field="year" value="${defaultYear}" style="font-size: 0.9rem;">
                                </div>
                                <div class="form-group">
                                    <label style="font-size: 0.85rem;">عدد أفراد الأسرة</label>
                                    <input type="number" class="form-control social-field" data-field="family_members" value="${data.family_members || ''}" min="1">
                                </div>
                                <div class="form-group">
                                    <label style="font-size: 0.85rem;">عدد الأخوة والأخوات</label>
                                    <input type="number" class="form-control social-field" data-field="siblings" value="${data.siblings || ''}" min="0">
                                </div>
                                <div class="form-group">
                                    <label style="font-size: 0.85rem;">دخل الأسرة الشهري</label>
                                    <select class="form-control social-field" data-field="income">
                                        <option value="">-- اختر --</option>
                                        <option value="جيد جداً" ${data.income === 'جيد جداً' ? 'selected' : ''}>جيد جداً</option>
                                        <option value="جيد" ${data.income === 'جيد' ? 'selected' : ''}>جيد</option>
                                        <option value="متوسط" ${data.income === 'متوسط' ? 'selected' : ''}>متوسط</option>
                                        <option value="ضعيف" ${data.income === 'ضعيف' ? 'selected' : ''}>ضعيف</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label style="font-size: 0.85rem;">عدد الغرف التي تشغلها الأسرة</label>
                                    <input type="number" class="form-control social-field" data-field="rooms" value="${data.rooms || ''}" min="1">
                                </div>
                                <div class="form-group">
                                    <label style="font-size: 0.85rem;">وضع التلميذ العائلي (يعيش مع)</label>
                                    <select class="form-control social-field" data-field="lives_with">
                                        <option value="">-- اختر --</option>
                                        <option value="والديه" ${data.lives_with === 'والديه' ? 'selected' : ''}>والديه</option>
                                        <option value="الأب" ${data.lives_with === 'الأب' ? 'selected' : ''}>الأب</option>
                                        <option value="الأم" ${data.lives_with === 'الأم' ? 'selected' : ''}>الأم</option>
                                        <option value="أقارب" ${data.lives_with === 'أقارب' ? 'selected' : ''}>أقارب</option>
                                        <option value="آخر" ${data.lives_with === 'آخر' ? 'selected' : ''}>آخر</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label style="font-size: 0.85rem;">هل التلميذ يعمل</label>
                                    <select class="form-control social-field" data-field="works">
                                        <option value="لا" ${(data.works || 'لا') === 'لا' ? 'selected' : ''}>لا</option>
                                        <option value="نعم" ${data.works === 'نعم' ? 'selected' : ''}>نعم</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label style="font-size: 0.85rem;">مدى ملاءمة الجو العام في البيت للدراسة</label>
                                    <select class="form-control social-field" data-field="home_suitable">
                                        <option value="">-- اختر --</option>
                                        <option value="ملائم" ${data.home_suitable === 'ملائم' ? 'selected' : ''}>ملائم</option>
                                        <option value="صالح" ${data.home_suitable === 'صالح' ? 'selected' : ''}>صالح</option>
                                        <option value="غير ملائم" ${data.home_suitable === 'غير ملائم' ? 'selected' : ''}>غير ملائم</option>
                                    </select>
                                </div>
                            </div>
                        `;
                        container.appendChild(card);
                        card.querySelectorAll('.social-field').forEach(input => input.addEventListener('change', updateSocialStatusData));
                    }
                    
                    function updateSocialStatusData() {
                        const cards = document.querySelectorAll('#socialStatusContainer .social-card');
                        const records = [];
                        cards.forEach(card => {
                            const record = {};
                            card.querySelectorAll('.social-field').forEach(input => record[input.dataset.field] = input.value);
                            if (record.year && record.year.trim()) records.push(record);
                        });
                        document.getElementById('socialStatusData').value = JSON.stringify(records);
                    }
                    
                    document.addEventListener('DOMContentLoaded', function() {
                        const existingData = document.getElementById('socialStatusData').value;
                        if (existingData && existingData.trim()) {
                            try {
                                const records = JSON.parse(existingData);
                                if (Array.isArray(records) && records.length > 0) {
                                    records.forEach(record => addSocialStatusCard(record));
                                } else { addSocialStatusCard(); }
                            } catch (e) { addSocialStatusCard(); }
                        } else { addSocialStatusCard(); }
                    });
                    </script>
                    
                    <!-- ═══ القسم 6: الصفات الجسمية والحالة الصحية ═══ -->
                    <div class="form-section">
                        <div class="form-section-title" style="color: #0891b2;">🏥 الصفات الجسمية والحالة الصحية</div>
                        <div id="healthStatusContainer"></div>
                        <div class="d-flex gap-2 mt-2" style="justify-content: center;">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addHealthStatusCard()" style="padding: 8px 16px; background: #0891b2;">
                                ➕ إضافة سنة دراسية
                            </button>
                        </div>
                        <textarea name="health_status" id="healthStatusData" style="display: none;"><?= sanitize($editStudent['health_status'] ?? '') ?></textarea>
                    </div>
                    
                    <script>
                    function addHealthStatusCard(data = {}) {
                        const container = document.getElementById('healthStatusContainer');
                        const currentYear = new Date().getFullYear();
                        const defaultYear = data.year || `${currentYear}/${currentYear + 1}`;
                        
                        const card = document.createElement('div');
                        card.className = 'health-card';
                        card.style.cssText = 'background: #ecfeff; border: 1px solid #67e8f9; border-radius: 8px; padding: 15px; margin-bottom: 15px; position: relative;';
                        
                        card.innerHTML = `
                            <button type="button" onclick="this.parentElement.remove(); updateHealthStatusData();" 
                                    style="position: absolute; top: 5px; left: 5px; background: #ef4444; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-size: 12px;">✕</button>
                            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
                                <div class="form-group">
                                    <label style="font-size: 0.85rem; font-weight: 600;">السنة الدراسية</label>
                                    <input type="text" class="form-control health-field" data-field="year" value="${defaultYear}" style="font-size: 0.9rem;">
                                </div>
                                <div class="form-group">
                                    <label style="font-size: 0.85rem;">الطول (سم)</label>
                                    <input type="number" class="form-control health-field" data-field="height" value="${data.height || ''}" min="50" max="250" placeholder="بالسنتيمتر">
                                </div>
                                <div class="form-group">
                                    <label style="font-size: 0.85rem;">الوزن (كغم)</label>
                                    <input type="number" class="form-control health-field" data-field="weight" value="${data.weight || ''}" min="10" max="200" placeholder="بالكيلوغرام">
                                </div>
                                <div class="form-group">
                                    <label style="font-size: 0.85rem;">حدة البصر - اليمنى</label>
                                    <select class="form-control health-field" data-field="vision_right">
                                        <option value="">-- اختر --</option>
                                        <option value="6/6" ${data.vision_right === '6/6' ? 'selected' : ''}>6/6 سليم</option>
                                        <option value="بالملاحظة" ${data.vision_right === 'بالملاحظة' ? 'selected' : ''}>بالملاحظة</option>
                                        <option value="بالفحص الطبي" ${data.vision_right === 'بالفحص الطبي' ? 'selected' : ''}>بالفحص الطبي</option>
                                        <option value="ضعف" ${data.vision_right === 'ضعف' ? 'selected' : ''}>ضعف</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label style="font-size: 0.85rem;">حدة البصر - اليسرى</label>
                                    <select class="form-control health-field" data-field="vision_left">
                                        <option value="">-- اختر --</option>
                                        <option value="6/6" ${data.vision_left === '6/6' ? 'selected' : ''}>6/6 سليم</option>
                                        <option value="بالملاحظة" ${data.vision_left === 'بالملاحظة' ? 'selected' : ''}>بالملاحظة</option>
                                        <option value="بالفحص الطبي" ${data.vision_left === 'بالفحص الطبي' ? 'selected' : ''}>بالفحص الطبي</option>
                                        <option value="ضعف" ${data.vision_left === 'ضعف' ? 'selected' : ''}>ضعف</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label style="font-size: 0.85rem;">درجة السمع</label>
                                    <select class="form-control health-field" data-field="hearing">
                                        <option value="">-- اختر --</option>
                                        <option value="سليم" ${data.hearing === 'سليم' ? 'selected' : ''}>سليم</option>
                                        <option value="بالملاحظة" ${data.hearing === 'بالملاحظة' ? 'selected' : ''}>بالملاحظة</option>
                                        <option value="بالفحص الطبي" ${data.hearing === 'بالفحص الطبي' ? 'selected' : ''}>بالفحص الطبي</option>
                                        <option value="ضعف" ${data.hearing === 'ضعف' ? 'selected' : ''}>ضعف</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label style="font-size: 0.85rem;">النطق</label>
                                    <select class="form-control health-field" data-field="speech">
                                        <option value="سليم" ${(data.speech || 'سليم') === 'سليم' ? 'selected' : ''}>سليم</option>
                                        <option value="غير سليم" ${data.speech === 'غير سليم' ? 'selected' : ''}>غير سليم</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label style="font-size: 0.85rem;">هل اكمل التلقيحات المطلوبة</label>
                                    <select class="form-control health-field" data-field="vaccinations_complete">
                                        <option value="نعم" ${(data.vaccinations_complete || 'نعم') === 'نعم' ? 'selected' : ''}>نعم</option>
                                        <option value="لا" ${data.vaccinations_complete === 'لا' ? 'selected' : ''}>لا</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group" style="margin-top: 10px;">
                                <label style="font-size: 0.85rem;">اللقاحات المزودة (إن وجدت)</label>
                                <input type="text" class="form-control health-field" data-field="vaccines_provided" value="${data.vaccines_provided || ''}" placeholder="اذكر اللقاحات المزودة">
                            </div>
                        `;
                        container.appendChild(card);
                        card.querySelectorAll('.health-field').forEach(input => input.addEventListener('change', updateHealthStatusData));
                    }
                    
                    function updateHealthStatusData() {
                        const cards = document.querySelectorAll('#healthStatusContainer .health-card');
                        const records = [];
                        cards.forEach(card => {
                            const record = {};
                            card.querySelectorAll('.health-field').forEach(input => record[input.dataset.field] = input.value);
                            if (record.year && record.year.trim()) records.push(record);
                        });
                        document.getElementById('healthStatusData').value = JSON.stringify(records);
                    }
                    
                    document.addEventListener('DOMContentLoaded', function() {
                        const existingData = document.getElementById('healthStatusData').value;
                        if (existingData && existingData.trim()) {
                            try {
                                const records = JSON.parse(existingData);
                                if (Array.isArray(records) && records.length > 0) {
                                    records.forEach(record => addHealthStatusCard(record));
                                } else { addHealthStatusCard(); }
                            } catch (e) { addHealthStatusCard(); }
                        } else { addHealthStatusCard(); }
                    });
                    </script>
                    
                    <!-- ═══ القسم 7: التحصيل الدراسي ═══ -->
                    <div class="form-section">
                        <div class="form-section-title" style="color: #7c3aed;">📚 التحصيل الدراسي - دروس المرحلة الابتدائية</div>
                        
                        <style>
                            #academicTable {
                                width: 100%;
                                border-collapse: separate;
                                border-spacing: 0;
                                font-size: 0.85rem;
                                min-width: 800px;
                                border-radius: 12px;
                                overflow: hidden;
                                box-shadow: 0 4px 15px rgba(124, 58, 237, 0.15);
                            }
                            #academicTable thead tr:first-child th {
                                background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%);
                                color: white;
                                font-weight: 600;
                                padding: 12px;
                                font-size: 0.95rem;
                            }
                            #academicTable .year-header {
                                background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
                                color: white;
                                padding: 8px;
                                min-width: 100px;
                            }
                            #academicTable .year-input {
                                background: rgba(255,255,255,0.95) !important;
                                color: #5b21b6 !important;
                                border: 2px solid #c4b5fd !important;
                                border-radius: 6px;
                                font-weight: 600;
                                font-size: 0.8rem !important;
                                width: 90px !important;
                                padding: 6px !important;
                            }
                            #academicTable tbody tr:nth-child(odd) {
                                background: #faf5ff;
                            }
                            #academicTable tbody tr:nth-child(even) {
                                background: #ffffff;
                            }
                            #academicTable tbody tr:hover {
                                background: #ede9fe;
                            }
                            #academicTable tbody td {
                                padding: 8px;
                                border: 1px solid #e9d5ff;
                            }
                            #academicTable tbody td:first-child {
                                background: linear-gradient(90deg, #f5f3ff 0%, #ede9fe 100%);
                                font-weight: 600;
                                color: #5b21b6;
                                text-align: right;
                                padding-right: 12px;
                                border-left: 3px solid #8b5cf6;
                            }
                            #academicTable .grade-input {
                                width: 75px;
                                text-align: center;
                                padding: 6px;
                                border: 1px solid #ddd6fe;
                                border-radius: 6px;
                                font-size: 0.85rem;
                                transition: all 0.2s;
                            }
                            #academicTable .grade-input:focus {
                                border-color: #8b5cf6;
                                box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
                                outline: none;
                            }
                            #academicTable tfoot tr {
                                background: linear-gradient(90deg, #ede9fe 0%, #ddd6fe 100%);
                            }
                            #academicTable tfoot td {
                                padding: 10px 8px;
                                border: 1px solid #c4b5fd;
                                font-weight: 600;
                                color: #5b21b6;
                            }
                            #academicTable tfoot td:first-child {
                                background: linear-gradient(90deg, #ddd6fe 0%, #c4b5fd 100%);
                                border-left: 3px solid #7c3aed;
                            }
                        </style>
                        
                        <div style="overflow-x: auto; border-radius: 12px; margin-top: 10px;">
                            <table id="academicTable">
                                <thead>
                                    <tr>
                                        <th rowspan="2" style="width: 200px; border-radius: 12px 0 0 0;">المادة الدراسية</th>
                                        <th id="yearHeaders" colspan="6" style="border-radius: 0 12px 0 0;">السنة الدراسية</th>
                                    </tr>
                                    <tr id="yearRow">
                                        <!-- سيتم ملء السنوات ديناميكياً -->
                                    </tr>
                                </thead>
                                <tbody id="academicTableBody">
                                    <!-- سيتم ملء الصفوف ديناميكياً -->
                                </tbody>
                                <tfoot>
                                    <tr id="totalRow">
                                        <td>📊 المجموع</td>
                                    </tr>
                                    <tr id="supplementaryRow">
                                        <td>📝 ملاحظات من نتائج الدروس المكمل فيها</td>
                                    </tr>
                                    <tr id="finalResultRow">
                                        <td>✅ النتيجة النهائية</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div class="d-flex gap-2 mt-3" style="justify-content: center;">
                            <button type="button" class="btn btn-sm" onclick="addAcademicYear()" 
                                    style="padding: 10px 20px; background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; border: none; border-radius: 8px; font-weight: 500; box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);">
                                ➕ إضافة سنة دراسية
                            </button>
                            <button type="button" class="btn btn-sm" onclick="removeLastYear()" 
                                    style="padding: 10px 20px; background: linear-gradient(135deg, #f87171, #ef4444); color: white; border: none; border-radius: 8px; font-weight: 500; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);">
                                ➖ حذف آخر سنة
                            </button>
                        </div>
                        <textarea name="academic_records" id="academicRecordsData" style="display: none;"><?= sanitize($editStudent['academic_records'] ?? '') ?></textarea>
                    </div>
                    
                    <script>
                    const academicSubjects = [
                        'الصف والشعبة',
                        'التربية الإسلامية والتفسير',
                        'اللغة العربية واملاء',
                        'اللغة الكردية',
                        'اللغة الانكليزية',
                        'الرياضيات',
                        'الاجتماعيات',
                        'العلوم والتربية الصحية',
                        'التربية الزراعية',
                        'التربية الفنية',
                        'التربية الرياضية',
                        'النشيد والموسيقى',
                        'التربية الأسرية',
                        'اللغة السريانية'
                    ];
                    
                    let academicYears = [];
                    
                    function initAcademicTable() {
                        const tbody = document.getElementById('academicTableBody');
                        tbody.innerHTML = '';
                        
                        academicSubjects.forEach((subject, index) => {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `<td>${subject}</td>`;
                            tr.dataset.subjectIndex = index;
                            tbody.appendChild(tr);
                        });
                    }
                    
                    function addAcademicYear(yearData = null) {
                        const currentYear = new Date().getFullYear();
                        const yearIndex = academicYears.length;
                        const defaultYear = yearData?.year || `${currentYear + yearIndex}/${currentYear + yearIndex + 1}`;
                        
                        academicYears.push(defaultYear);
                        
                        // إضافة عمود السنة في الرأس
                        const yearRow = document.getElementById('yearRow');
                        const th = document.createElement('th');
                        th.className = 'year-header';
                        th.innerHTML = `<input type="text" class="year-input" data-year-index="${yearIndex}" value="${defaultYear}" 
                                         onchange="updateYearValue(${yearIndex}, this.value)">`;
                        yearRow.appendChild(th);
                        
                        // تحديث colspan
                        document.getElementById('yearHeaders').colSpan = academicYears.length;
                        
                        // إضافة خلايا للمواد
                        const tbody = document.getElementById('academicTableBody');
                        const rows = tbody.querySelectorAll('tr');
                        rows.forEach((row, subjectIndex) => {
                            const td = document.createElement('td');
                            const fieldName = subjectIndex === 0 ? 'grade_section' : `subject_${subjectIndex - 1}`;
                            const value = yearData?.[fieldName] || '';
                            td.innerHTML = `<input type="${subjectIndex === 0 ? 'text' : 'number'}" class="grade-input academic-input" 
                                            data-year="${yearIndex}" data-field="${fieldName}" value="${value}"
                                            ${subjectIndex !== 0 ? 'min="0" max="100"' : 'placeholder="الصف/الشعبة"'}
                                            onchange="calculateTotal(${yearIndex}); updateAcademicData()">`
                            row.appendChild(td);
                        });
                        
                        // إضافة خلايا المجموع (حساب تلقائي)
                        const totalRow = document.getElementById('totalRow');
                        const totalTd = document.createElement('td');
                        totalTd.innerHTML = `<span class="grade-input academic-input" data-year="${yearIndex}" data-field="total" 
                                             style="font-weight: 700; background: #ede9fe; padding: 8px 12px; border-radius: 6px; display: inline-block; min-width: 60px;"
                                             id="total_${yearIndex}">${yearData?.total || '0'}</span>`;
                        totalRow.appendChild(totalTd);
                        
                        // إضافة خلية ملاحظات الدروس المكمل فيها
                        const supplementaryRow = document.getElementById('supplementaryRow');
                        const supplementaryTd = document.createElement('td');
                        supplementaryTd.innerHTML = `<input type="text" class="grade-input academic-input" data-year="${yearIndex}" data-field="supplementary_notes" 
                                            value="${yearData?.supplementary_notes || ''}" placeholder="ملاحظات المكمل" style="width: 120px;"
                                            onchange="updateAcademicData()">`;
                        supplementaryRow.appendChild(supplementaryTd);
                        
                        // إضافة خلية النتيجة النهائية
                        const finalResultRow = document.getElementById('finalResultRow');
                        const finalResultTd = document.createElement('td');
                        finalResultTd.innerHTML = `<input type="text" class="grade-input academic-input" data-year="${yearIndex}" data-field="final_result" 
                                            value="${yearData?.final_result || ''}" placeholder="ناجح/راسب/مكمل" style="width: 100px;"
                                            onchange="updateAcademicData()">`;
                        finalResultRow.appendChild(finalResultTd);
                        
                        // حساب المجموع الأولي
                        setTimeout(() => calculateTotal(yearIndex), 100);
                        updateAcademicData();
                    }
                    
                    // دالة حساب المجموع تلقائياً
                    function calculateTotal(yearIndex) {
                        let total = 0;
                        const inputs = document.querySelectorAll(`.academic-input[data-year="${yearIndex}"]`);
                        inputs.forEach(input => {
                            const field = input.dataset.field;
                            // تجاهل حقول غير رقمية
                            if (field && field.startsWith('subject_') && input.value) {
                                const val = parseFloat(input.value);
                                if (!isNaN(val)) {
                                    total += val;
                                }
                            }
                        });
                        // تحديث عرض المجموع
                        const totalSpan = document.getElementById(`total_${yearIndex}`);
                        if (totalSpan) {
                            totalSpan.textContent = total || '0';
                            totalSpan.dataset.value = total;
                        }
                    }
                    
                    function removeLastYear() {
                        if (academicYears.length === 0) return;
                        
                        academicYears.pop();
                        
                        // حذف عمود السنة من الرأس
                        const yearRow = document.getElementById('yearRow');
                        if (yearRow.lastChild) yearRow.removeChild(yearRow.lastChild);
                        
                        // تحديث colspan
                        document.getElementById('yearHeaders').colSpan = Math.max(1, academicYears.length);
                        
                        // حذف خلايا المواد
                        const tbody = document.getElementById('academicTableBody');
                        tbody.querySelectorAll('tr').forEach(row => {
                            if (row.lastChild && row.lastChild.tagName === 'TD' && row.children.length > 1) {
                                row.removeChild(row.lastChild);
                            }
                        });
                        
                        // حذف خلايا المجموع والملاحظات والنتيجة
                        const totalRow = document.getElementById('totalRow');
                        if (totalRow.children.length > 1) totalRow.removeChild(totalRow.lastChild);
                        
                        const supplementaryRow = document.getElementById('supplementaryRow');
                        if (supplementaryRow.children.length > 1) supplementaryRow.removeChild(supplementaryRow.lastChild);
                        
                        const finalResultRow = document.getElementById('finalResultRow');
                        if (finalResultRow.children.length > 1) finalResultRow.removeChild(finalResultRow.lastChild);
                        
                        updateAcademicData();
                    }
                    
                    function updateYearValue(yearIndex, value) {
                        academicYears[yearIndex] = value;
                        updateAcademicData();
                    }
                    
                    function updateAcademicData() {
                        const data = [];
                        academicYears.forEach((year, yearIndex) => {
                            const yearRecord = { year: year };
                            document.querySelectorAll(`.academic-input[data-year="${yearIndex}"]`).forEach(input => {
                                yearRecord[input.dataset.field] = input.value;
                            });
                            data.push(yearRecord);
                        });
                        document.getElementById('academicRecordsData').value = JSON.stringify(data);
                    }
                    
                    document.addEventListener('DOMContentLoaded', function() {
                        initAcademicTable();
                        
                        const existingData = document.getElementById('academicRecordsData').value;
                        if (existingData && existingData.trim()) {
                            try {
                                const records = JSON.parse(existingData);
                                if (Array.isArray(records) && records.length > 0) {
                                    records.forEach(record => addAcademicYear(record));
                                }
                            } catch (e) { }
                        }
                    });
                    </script>
                    
                    <!-- ═══ القسم 8: المواظبة والدوام ═══ -->
                    <div class="form-section">
                        <div class="form-section-title" style="color: #0891b2;">📅 المواظبة والدوام</div>
                        
                        <style>
                            #attendanceTable {
                                width: 100%;
                                border-collapse: separate;
                                border-spacing: 0;
                                font-size: 0.8rem;
                                min-width: 1000px;
                                border-radius: 12px;
                                overflow: hidden;
                                box-shadow: 0 4px 20px rgba(8, 145, 178, 0.2);
                            }
                            #attendanceTable thead th {
                                background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);
                                color: white;
                                font-weight: 600;
                                padding: 10px 6px;
                                text-align: center;
                                border: 1px solid #06b6d4;
                            }
                            #attendanceTable thead tr:nth-child(2) th {
                                background: linear-gradient(135deg, #22d3ee 0%, #06b6d4 100%);
                                font-size: 0.75rem;
                                padding: 8px 4px;
                            }
                            #attendanceTable tbody tr {
                                transition: all 0.2s;
                            }
                            #attendanceTable tbody tr:nth-child(odd) {
                                background: #ecfeff;
                            }
                            #attendanceTable tbody tr:nth-child(even) {
                                background: #ffffff;
                            }
                            #attendanceTable tbody tr:hover {
                                background: #cffafe;
                                transform: scale(1.005);
                            }
                            #attendanceTable tbody td {
                                padding: 8px 4px;
                                border: 1px solid #a5f3fc;
                                text-align: center;
                                vertical-align: middle;
                            }
                            #attendanceTable tbody td:first-child {
                                background: linear-gradient(90deg, #cffafe 0%, #a5f3fc 100%);
                                font-weight: 700;
                                color: #0e7490;
                                min-width: 100px;
                                border-left: 4px solid #0891b2;
                            }
                            #attendanceTable .att-num-input {
                                width: 50px;
                                text-align: center;
                                padding: 6px 4px;
                                border: 2px solid #a5f3fc;
                                border-radius: 6px;
                                font-size: 0.85rem;
                                font-weight: 600;
                                transition: all 0.2s;
                            }
                            #attendanceTable .att-num-input:focus {
                                border-color: #0891b2;
                                box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.2);
                                outline: none;
                            }
                            #attendanceTable .att-txt-input {
                                width: 95%;
                                min-width: 100px;
                                text-align: right;
                                padding: 6px 8px;
                                border: 2px solid #a5f3fc;
                                border-radius: 6px;
                                font-size: 0.8rem;
                                transition: all 0.2s;
                            }
                            #attendanceTable .att-txt-input:focus {
                                border-color: #0891b2;
                                box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.2);
                                outline: none;
                            }
                            #attendanceTable .year-input-cell {
                                background: linear-gradient(90deg, #cffafe 0%, #a5f3fc 100%) !important;
                            }
                            #attendanceTable .year-input-cell input {
                                width: 90px;
                                text-align: center;
                                padding: 6px;
                                border: 2px solid #22d3ee;
                                border-radius: 6px;
                                font-weight: 700;
                                font-size: 0.85rem;
                                color: #0e7490;
                                background: white;
                            }
                        </style>
                        
                        <div style="overflow-x: auto; border-radius: 12px; margin-top: 10px;">
                            <table id="attendanceTable">
                                <thead>
                                    <tr>
                                        <th rowspan="2" style="width: 100px; border-radius: 12px 0 0 0;">السنة الدراسية</th>
                                        <th colspan="2">مجموع أيام الغياب للنصف الأول</th>
                                        <th colspan="2">مجموع أيام الغياب للنصف الثاني</th>
                                        <th rowspan="2" style="min-width: 120px;">أسباب الغياب</th>
                                        <th rowspan="2" style="min-width: 180px;">الأجازات الأجبارية الممنوحة للطالب لأصابته بأحد الأمراض مع ذكر المرض المعني</th>
                                        <th rowspan="2" style="min-width: 150px; border-radius: 0 12px 0 0;">الأجراءات التي أتخذت لمعالجة ظاهرة الغياب</th>
                                    </tr>
                                    <tr>
                                        <th>بعذر</th>
                                        <th>بدون عذر</th>
                                        <th>بعذر</th>
                                        <th>بدون عذر</th>
                                    </tr>
                                </thead>
                                <tbody id="attendanceTableBody">
                                    <!-- سيتم ملء الصفوف ديناميكياً -->
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="d-flex gap-2 mt-3" style="justify-content: center;">
                            <button type="button" class="btn btn-sm" onclick="addAttendanceRow()" 
                                    style="padding: 10px 20px; background: linear-gradient(135deg, #22d3ee, #0891b2); color: white; border: none; border-radius: 8px; font-weight: 500; box-shadow: 0 4px 12px rgba(8, 145, 178, 0.3);">
                                ➕ إضافة سنة دراسية
                            </button>
                            <button type="button" class="btn btn-sm" onclick="removeLastAttRow()" 
                                    style="padding: 10px 20px; background: linear-gradient(135deg, #f87171, #ef4444); color: white; border: none; border-radius: 8px; font-weight: 500; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);">
                                ➖ حذف آخر سنة
                            </button>
                        </div>
                        <textarea name="attendance_records" id="attendanceRecordsData" style="display: none;"><?= sanitize($editStudent['attendance_records'] ?? '') ?></textarea>
                    </div>
                    
                    <script>
                    let attRowCount = 0;
                    
                    function addAttendanceRow(data = null) {
                        const tbody = document.getElementById('attendanceTableBody');
                        const currentYear = new Date().getFullYear();
                        const rowIndex = attRowCount++;
                        const defaultYear = data?.year || `${currentYear + rowIndex}/${currentYear + rowIndex + 1}`;
                        
                        const tr = document.createElement('tr');
                        tr.dataset.rowIndex = rowIndex;
                        
                        tr.innerHTML = `
                            <td class="year-input-cell">
                                <input type="text" class="att-data" data-field="year" value="${defaultYear}" onchange="updateAttData()">
                            </td>
                            <td><input type="number" class="att-num-input att-data" data-field="absence_first_excused" value="${data?.absence_first_excused || ''}" min="0" onchange="updateAttData()"></td>
                            <td><input type="number" class="att-num-input att-data" data-field="absence_first_unexcused" value="${data?.absence_first_unexcused || ''}" min="0" onchange="updateAttData()"></td>
                            <td><input type="number" class="att-num-input att-data" data-field="absence_second_excused" value="${data?.absence_second_excused || ''}" min="0" onchange="updateAttData()"></td>
                            <td><input type="number" class="att-num-input att-data" data-field="absence_second_unexcused" value="${data?.absence_second_unexcused || ''}" min="0" onchange="updateAttData()"></td>
                            <td><input type="text" class="att-txt-input att-data" data-field="absence_reasons" value="${data?.absence_reasons || ''}" onchange="updateAttData()"></td>
                            <td><input type="text" class="att-txt-input att-data" data-field="compulsory_leave" value="${data?.compulsory_leave || ''}" onchange="updateAttData()"></td>
                            <td><input type="text" class="att-txt-input att-data" data-field="actions_taken" value="${data?.actions_taken || ''}" onchange="updateAttData()"></td>
                        `;
                        
                        tbody.appendChild(tr);
                        updateAttData();
                    }
                    
                    function removeLastAttRow() {
                        const tbody = document.getElementById('attendanceTableBody');
                        if (tbody.lastChild) {
                            tbody.removeChild(tbody.lastChild);
                            attRowCount = Math.max(0, attRowCount - 1);
                            updateAttData();
                        }
                    }
                    
                    function updateAttData() {
                        const rows = document.querySelectorAll('#attendanceTableBody tr');
                        const data = [];
                        rows.forEach(row => {
                            const record = {};
                            row.querySelectorAll('.att-data').forEach(input => {
                                record[input.dataset.field] = input.value;
                            });
                            if (record.year && record.year.trim()) {
                                data.push(record);
                            }
                        });
                        document.getElementById('attendanceRecordsData').value = JSON.stringify(data);
                    }
                    
                    document.addEventListener('DOMContentLoaded', function() {
                        const existingAttData = document.getElementById('attendanceRecordsData').value;
                        if (existingAttData && existingAttData.trim()) {
                            try {
                                const records = JSON.parse(existingAttData);
                                if (Array.isArray(records) && records.length > 0) {
                                    records.forEach(record => addAttendanceRow(record));
                                }
                            } catch (e) { }
                        }
                    });
                    </script>
                    
                    <!-- ═══ القسم 8: ملاحظات ═══ -->
                    <div class="form-section">
                        <div class="form-section-title">📝 التغييرات التي تطرأ على البيانات السابقة</div>
                        <div class="form-group">
                            <label>التغيرات على البيانات السابقة</label>
                            <textarea name="data_changes" class="form-control" rows="2"><?= sanitize($editStudent['data_changes'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="2"><?= sanitize($editStudent['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- قسم الصور على اليسار -->
                <div>
                    <!-- الصورة الرئيسية -->
                    <div class="form-section">
                        <div class="form-section-title">📷 صورة التلميذ</div>
                        <div class="photo-preview-container">
                            <div class="photo-preview" id="photoPreview">
                                <?php if (!empty($editStudent['photo'])): ?>
                                <img src="/uploads/students/<?= $editStudent['photo'] ?>" alt="صورة التلميذ" id="previewImg">
                                <?php else: ?>
                                <span id="photoPlaceholder">👤</span>
                                <img src="" alt="" id="previewImg" style="display: none;">
                                <?php endif; ?>
                            </div>
                            <label class="photo-upload-btn">
                                📤 اختيار صورة
                                <input type="file" name="photo" accept="image/*" id="photoInput">
                            </label>
                            <small style="color: #666; text-align: center;">الأبعاد المثالية: 3x4 سم<br>JPG, PNG - حتى 2MB</small>
                        </div>
                    </div>
                    
                    <!-- صور المراحل الدراسية -->
                    <div class="form-section">
                        <div class="form-section-title">🖼️ صور المراحل</div>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <!-- صورة الابتدائية -->
                            <div class="photo-card">
                                <div class="photo-card-title">الأول ابتدائي</div>
                                <div class="photo-card-preview" id="primaryPreview">
                                    <?php if (!empty($editStudent['photo_primary'])): ?>
                                    <img src="/uploads/students/<?= $editStudent['photo_primary'] ?>" alt="">
                                    <?php else: ?>
                                    <span style="color: #adb5bd; font-size: 2rem;">📷</span>
                                    <?php endif; ?>
                                </div>
                                <input type="file" name="photo_primary" accept="image/*" class="form-control" style="font-size: 0.8rem;">
                            </div>
                            
                            <!-- صورة المتوسطة -->
                            <div class="photo-card">
                                <div class="photo-card-title">الأول متوسط</div>
                                <div class="photo-card-preview" id="intermediatePreview">
                                    <?php if (!empty($editStudent['photo_intermediate'])): ?>
                                    <img src="/uploads/students/<?= $editStudent['photo_intermediate'] ?>" alt="">
                                    <?php else: ?>
                                    <span style="color: #adb5bd; font-size: 2rem;">📷</span>
                                    <?php endif; ?>
                                </div>
                                <input type="file" name="photo_intermediate" accept="image/*" class="form-control" style="font-size: 0.8rem;">
                            </div>
                            
                            <!-- صورة الإعدادية -->
                            <div class="photo-card">
                                <div class="photo-card-title">الرابع إعدادي</div>
                                <div class="photo-card-preview" id="secondaryPreview">
                                    <?php if (!empty($editStudent['photo_secondary'])): ?>
                                    <img src="/uploads/students/<?= $editStudent['photo_secondary'] ?>" alt="">
                                    <?php else: ?>
                                    <span style="color: #adb5bd; font-size: 2rem;">📷</span>
                                    <?php endif; ?>
                                </div>
                                <input type="file" name="photo_secondary" accept="image/*" class="form-control" style="font-size: 0.8rem;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="d-flex gap-2 mt-3" style="justify-content: center;">
                <button type="submit" class="btn btn-primary btn-lg" style="padding: 1rem 3rem; font-size: 1.1rem;">
                    💾 <?= $action === 'add' ? 'إضافة التلميذ' : 'حفظ التعديلات' ?>
                </button>
                <a href="/students.php" class="btn btn-secondary" style="padding: 1rem 2rem;">إلغاء</a>
            </div>
        </form>
    </div>
</div>

<script>
// ═══════════════════════════════════════════════════════════════
// التحقق من الحقول المطلوبة قبل إرسال نموذج التلميذ
// ═══════════════════════════════════════════════════════════════
document.getElementById('studentForm').addEventListener('submit', function(e) {
    const fullName = document.querySelector('input[name="full_name"]');
    const classId = document.getElementById('classIdSelect');
    const section = document.getElementById('sectionSelect');
    
    const errors = [];
    
    // التحقق من اسم التلميذ
    if (!fullName || !fullName.value.trim()) {
        errors.push('⚠️ اسم التلميذ مطلوب');
        fullName?.classList.add('is-invalid');
    } else {
        fullName?.classList.remove('is-invalid');
    }
    
    // التحقق من الصف
    if (!classId || !classId.value) {
        errors.push('⚠️ يجب اختيار الصف');
        classId?.classList.add('is-invalid');
    } else {
        classId?.classList.remove('is-invalid');
    }
    
    // التحقق من الشعبة
    if (!section || !section.value) {
        errors.push('⚠️ يجب اختيار الشعبة');
        section?.classList.add('is-invalid');
    } else {
        section?.classList.remove('is-invalid');
    }
    
    // إذا كانت هناك أخطاء، منع الإرسال وعرض الرسالة
    if (errors.length > 0) {
        e.preventDefault();
        
        // إزالة أي تنبيه سابق
        const oldAlert = document.getElementById('formValidationAlert');
        if (oldAlert) oldAlert.remove();
        
        // إنشاء تنبيه جديد
        const alertDiv = document.createElement('div');
        alertDiv.id = 'formValidationAlert';
        alertDiv.style.cssText = `
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(239, 68, 68, 0.4);
            z-index: 10000;
            animation: slideDown 0.4s ease-out;
            text-align: center;
            max-width: 90%;
        `;
        alertDiv.innerHTML = `
            <div style="font-size: 1.2rem; font-weight: 700; margin-bottom: 10px;">❌ لا يمكن إضافة التلميذ</div>
            <div style="font-size: 1rem;">${errors.join('<br>')}</div>
            <div style="margin-top: 15px; font-size: 0.9rem; opacity: 0.9;">يرجى ملء جميع الحقول المطلوبة</div>
        `;
        document.body.appendChild(alertDiv);
        
        // إضافة أنيميشن CSS
        if (!document.getElementById('validationAlertStyles')) {
            const style = document.createElement('style');
            style.id = 'validationAlertStyles';
            style.textContent = `
                @keyframes slideDown {
                    from { opacity: 0; transform: translateX(-50%) translateY(-30px); }
                    to { opacity: 1; transform: translateX(-50%) translateY(0); }
                }
                .is-invalid {
                    border-color: #ef4444 !important;
                    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.3) !important;
                    animation: shake 0.5s ease-in-out;
                }
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
                    20%, 40%, 60%, 80% { transform: translateX(5px); }
                }
            `;
            document.head.appendChild(style);
        }
        
        // إزالة التنبيه تلقائياً بعد 5 ثواني
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.style.animation = 'slideDown 0.3s ease-out reverse';
                setTimeout(() => alertDiv.remove(), 300);
            }
        }, 5000);
        
        // التمرير إلى أول حقل به خطأ
        const firstInvalid = document.querySelector('.is-invalid');
        if (firstInvalid) {
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalid.focus();
        }
        
        return false;
    }
});

// معاينة الصورة الرئيسية
document.getElementById('photoInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('previewImg').style.display = 'block';
            const placeholder = document.getElementById('photoPlaceholder');
            if (placeholder) placeholder.style.display = 'none';
        }
        reader.readAsDataURL(file);
    }
});

// معاينة الصور الأخرى
document.querySelectorAll('input[name="photo_primary"], input[name="photo_intermediate"], input[name="photo_secondary"]').forEach(function(input) {
    input.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const previewId = this.name.replace('photo_', '') + 'Preview';
            const preview = document.getElementById(previewId.charAt(0).toUpperCase() + previewId.slice(1).replace('Preview', 'Preview'));
            // Simplified approach - just show file selected
            this.previousElementSibling.innerHTML = '<span style="color: #22c55e;">✓ تم اختيار صورة</span>';
        }
    });
});
</script>

<?php endif; ?>
<?php endif; ?>

<?php if ($action === 'list'): ?>
<div class="card fade-in">
    <div class="card-header" style="flex-direction: column; align-items: stretch; gap: 1rem;">
        <div class="d-flex justify-between align-center flex-wrap gap-2">
            <h3>📋 قائمة التلاميذ <span id="studentCount" style="font-weight: normal; color: #666; font-size: 0.9rem;">(<?= count($students) ?> تلميذ)</span></h3>
        </div>
        
        <!-- 🔍 نظام الفلترة والبحث المباشر بدون إعادة تحميل -->
        <div class="d-flex gap-2 flex-wrap align-center" style="background: var(--bg-secondary); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-color);">
            <div class="d-flex align-center gap-1">
                <label style="font-weight: 600; color: #555; white-space: nowrap;"><?= __('🏫 الصف:') ?></label>
                <select id="classFilterLive" class="form-control" style="width: auto; min-width: 120px;" onchange="filterStudentsLive()">
                    <option value=""><?= __('كل الصفوف') ?></option>
                    <?php foreach (CLASSES as $id => $name): ?>
                    <option value="<?= $id ?>"><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="d-flex align-center gap-1">
                <label style="font-weight: 600; color: #555; white-space: nowrap;"><?= __('📂 الشعبة:') ?></label>
                <select id="sectionFilterLive" class="form-control" style="width: auto; min-width: 100px;" onchange="filterStudentsLive()">
                    <option value=""><?= __('كل الشعب') ?></option>
                    <?php foreach (SECTIONS as $sec): ?>
                    <option value="<?= $sec ?>"><?= $sec ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="d-flex align-center gap-1" style="flex: 1; min-width: 250px;">
                <input type="text" 
                       id="studentSearchLive" 
                       class="form-control" 
                       placeholder="<?= __('🔍 ابحث بالاسم، رقم الهاتف، أو ID...') ?>"
                       oninput="filterStudentsLive()"
                       onkeyup="filterStudentsLive()"
                       style="border-radius: 25px; border: 2px solid var(--border); transition: all 0.3s; flex: 1;">
            </div>
            
            <select id="accountFilterLive" class="form-control" style="width: auto; min-width: 130px;" onchange="filterStudentsLive()">
                <option value=""><?= __('🔐 كل الحسابات') ?></option>
                <option value="has-account"><?= __('✅ لديه حساب') ?></option>
                <option value="no-account"><?= __('❌ بدون حساب') ?></option>
            </select>
            
            <button type="button" class="btn btn-secondary btn-sm" onclick="resetStudentFilters()" style="padding: 0.5rem 1rem;">
                <?= __('✕ مسح الفلاتر') ?>
            </button>
            
            <span style="color: var(--text-muted); font-size: 0.9rem;" id="studentSearchCount"></span>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (empty($students)): ?>
        <div class="empty-state">
            <div class="icon">👨‍🎓</div>
            <h3>لا يوجد تلاميذ</h3>
            <p><?= $searchQuery ? 'لم يتم العثور على نتائج للبحث' : 'قم بإضافة تلاميذ جدد للنظام' ?></p>
            <?php if ($searchQuery || $classFilter || $sectionFilter): ?>
            <a href="/students.php" class="btn btn-secondary">عرض كل التلاميذ</a>
            <?php endif; ?>
        </div>
        
        <?php elseif ($groupedStudents && !$searchQuery): ?>
        <!-- عرض مجمّع حسب الصف والشعبة -->
        <?php foreach ($groupedStudents as $group): ?>
        <div class="student-group" style="margin-bottom: 1.5rem;">
            <div class="group-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 0.75rem 1rem; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center;">
                <h4 style="margin: 0; color: white; font-size: 1rem;">
                    🏫 الصف <?= CLASSES[$group['class_id']] ?? $group['class_id'] ?> - شعبة (<?= $group['section'] ?>)
                </h4>
                <span style="background: rgba(255,255,255,0.2); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem;">
                    <?= count($group['students']) ?> تلميذ
                </span>
            </div>
            <div class="table-responsive" style="border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 8px 8px; overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <table style="margin: 0;">
                    <thead>
                        <tr style="background: #f8fafc;">
                            <th style="width: 50px;">#</th>
                            <th style="width: 60px;">ID</th>
                            <th>الاسم</th>
                            <th>الجنس</th>
                            <th>ولي الأمر</th>
                            <th>الهاتف</th>
                            <?php if (isAdmin()): ?>
                            <th style="width: 180px;">إجراءات</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; foreach ($group['students'] as $student): ?>
                        <tr class="student-row" data-class="<?= $group['class_id'] ?>" data-section="<?= $group['section'] ?>" data-has-account="<?= !empty($student['user_id']) ? 'yes' : 'no' ?>">
                            <td><?= $counter++ ?></td>
                            <td><code style="background: var(--bg-secondary); padding: 2px 6px; border-radius: 4px; font-weight: 600;">#<?= $student['id'] ?></code></td>
                            <td>
                                <div class="d-flex align-center gap-1">
                                    <?php if ($student['photo']): ?>
                                    <img src="/uploads/students/<?= $student['photo'] ?>" alt="" 
                                         style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                                    <?php else: ?>
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 0.9rem;">👤</div>
                                    <?php endif; ?>
                                    <strong><?= sanitize($student['full_name']) ?></strong>
                                </div>
                            </td>
                            <td><?= $student['gender'] === 'male' ? 'ذكر' : 'أنثى' ?></td>
                            <td><?= sanitize($student['parent_name'] ?: '-') ?></td>
                            <td dir="ltr"><?= sanitize($student['parent_phone'] ?: '-') ?></td>
                            <?php if (isAdmin()): ?>
                            <td>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-info btn-sm" title="عرض البيانات"
                                            onclick="viewStudent(<?= $student['id'] ?>)">👁️</button>
                                    <a href="?action=edit&id=<?= $student['id'] ?>" class="btn btn-secondary btn-sm" title="تعديل">✏️</a>
                                    <?php if (empty($student['user_id'])): ?>
                                    <button type="button" class="btn btn-success btn-sm btn-create-account" 
                                            onclick="createStudentAccount(<?= $student['id'] ?>, this)"
                                            title="إنشاء حساب">🔑</button>
                                    <?php else: ?>
                                    <span class="btn btn-primary btn-sm" title="لديه حساب" style="cursor: default;">
                                        ✓
                                    </span>
                                    <?php endif; ?>
                                    <button type="button" 
                                            class="btn btn-danger btn-sm"
                                            data-delete
                                            data-module="students"
                                            data-id="<?= $student['id'] ?>"
                                            data-delete-message="هل تريد حذف التلميذ '<?= sanitize($student['full_name']) ?>'؟"
                                            title="حذف">🗑️</button>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- بطاقات الموبايل - تظهر فقط على الشاشات الصغيرة -->
                <div class="student-mobile-cards">
                    <?php $mobileCounter = 1; foreach ($group['students'] as $student): ?>
                    <div class="student-mobile-card student-row" 
                         data-class="<?= $student['class_id'] ?>" 
                         data-section="<?= sanitize($student['section']) ?>"
                         data-has-account="<?= !empty($student['user_id']) ? 'yes' : 'no' ?>">
                        <div class="student-avatar">
                            <?php if (!empty($student['photo'])): ?>
                            <img src="/uploads/students/<?= sanitize($student['photo']) ?>" alt="">
                            <?php else: ?>
                            <?= $student['gender'] === 'male' ? '👦' : '👧' ?>
                            <?php endif; ?>
                        </div>
                        <div class="student-info">
                            <div class="student-name"><?= sanitize($student['full_name']) ?></div>
                            <div class="student-meta">
                                <span><?= $student['gender'] === 'male' ? '👦 ذكر' : '👧 أنثى' ?></span>
                                <?php if ($student['parent_phone']): ?>
                                <span>📞 <?= sanitize($student['parent_phone']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($student['user_id'])): ?>
                                <span style="color: #10b981;">✓ لديه حساب</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (isAdmin()): ?>
                        <div class="student-actions">
                            <button type="button" class="btn btn-info" title="عرض"
                                    onclick="viewStudent(<?= $student['id'] ?>)">👁️</button>
                            <a href="?action=edit&id=<?= $student['id'] ?>" class="btn btn-secondary" title="تعديل">✏️</a>
                            <button type="button" class="btn btn-danger"
                                    data-delete data-module="students"
                                    data-id="<?= $student['id'] ?>"
                                    data-delete-message="هل تريد حذف التلميذ '<?= sanitize($student['full_name']) ?>'؟"
                                    title="حذف">🗑️</button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php $mobileCounter++; endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php else: ?>
        <!-- عرض عادي (نتائج البحث) -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ID</th>
                        <th>الاسم</th>
                        <th>اسم المستخدم</th>
                        <th>الصف</th>
                        <th>الشعبة</th>
                        <th>الجنس</th>
                        <th>ولي الأمر</th>
                        <th>الهاتف</th>
                        <?php if (isAdmin()): ?>
                        <th>إجراءات</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $counter = 1; foreach ($students as $student): ?>
                    <?php 
                        // جلب اسم المستخدم إن وجد
                        $studentUsername = '';
                        if (!empty($student['user_id'])) {
                            $conn = getConnection();
                            $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                            $stmt->execute([$student['user_id']]);
                            $userRow = $stmt->fetch();
                            $studentUsername = $userRow['username'] ?? '';
                        }
                    ?>
                    <tr class="student-row" data-class="<?= $student['class_id'] ?>" data-section="<?= $student['section'] ?>" data-has-account="<?= !empty($student['user_id']) ? 'yes' : 'no' ?>">
                        <td><?= $counter++ ?></td>
                        <td><code style="background: var(--bg-secondary); padding: 2px 6px; border-radius: 4px; font-weight: 600;">#<?= $student['id'] ?></code></td>
                        <td>
                            <div class="d-flex align-center gap-1">
                                <?php if ($student['photo']): ?>
                                <img src="/uploads/students/<?= $student['photo'] ?>" alt="" 
                                     style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                                <?php endif; ?>
                                <strong><?= sanitize($student['full_name']) ?></strong>
                            </div>
                        </td>
                        <td>
                            <?php if ($studentUsername): ?>
                            <code style="background: #dbeafe; color: #1e40af; padding: 2px 6px; border-radius: 4px; font-size: 0.85rem;"><?= $studentUsername ?></code>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= CLASSES[$student['class_id']] ?? $student['class_id'] ?></td>
                        <td><?= sanitize($student['section']) ?></td>
                        <td><?= $student['gender'] === 'male' ? 'ذكر' : 'أنثى' ?></td>
                        <td><?= sanitize($student['parent_name'] ?: '-') ?></td>
                        <td dir="ltr"><?= sanitize($student['parent_phone'] ?: '-') ?></td>
                        <?php if (isAdmin()): ?>
                        <td>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-info btn-sm" title="عرض البيانات"
                                        onclick="viewStudent(<?= $student['id'] ?>)">👁️</button>
                                <a href="?action=edit&id=<?= $student['id'] ?>" class="btn btn-secondary btn-sm" title="تعديل">✏️</a>
                                <?php if (empty($student['user_id'])): ?>
                                <button type="button" class="btn btn-success btn-sm btn-create-account" 
                                        onclick="createStudentAccount(<?= $student['id'] ?>, this)"
                                        title="إنشاء حساب">🔑</button>
                                <?php else: ?>
                                <span class="btn btn-primary btn-sm" title="لديه حساب" style="cursor: default;">
                                    ✓
                                </span>
                                <?php endif; ?>
                                <button type="button" 
                                        class="btn btn-danger btn-sm"
                                        data-delete
                                        data-module="students"
                                        data-id="<?= $student['id'] ?>"
                                        data-delete-message="هل تريد حذف التلميذ '<?= sanitize($student['full_name']) ?>'؟"
                                        title="حذف">🗑️</button>
                            </div>
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
<?php endif; ?>

<script>
// ═══════════════════════════════════════════════════════════════
// 🔍 دالة البحث والفلترة المباشرة في جداول الطلاب
// ═══════════════════════════════════════════════════════════════
function filterStudentsLive() {
    const searchInput = document.getElementById('studentSearchLive');
    const classFilter = document.getElementById('classFilterLive');
    const sectionFilter = document.getElementById('sectionFilterLive');
    const accountFilter = document.getElementById('accountFilterLive');
    
    const searchValue = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const classValue = classFilter ? classFilter.value : '';
    const sectionValue = sectionFilter ? sectionFilter.value : '';
    const accountValue = accountFilter ? accountFilter.value : '';
    
    // جلب كل صفوف الطلاب
    const rows = document.querySelectorAll('.student-row');
    // جلب كل مجموعات الصفوف
    const groups = document.querySelectorAll('.student-group');
    
    let visibleCount = 0;
    let groupVisibility = {};
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const rowClass = row.getAttribute('data-class') || '';
        const rowSection = row.getAttribute('data-section') || '';
        const hasAccount = row.getAttribute('data-has-account') || '';
        
        // فحص البحث
        let matchesSearch = !searchValue || text.includes(searchValue);
        
        // فحص فلتر الصف
        let matchesClass = !classValue || rowClass === classValue;
        
        // فحص فلتر الشعبة
        let matchesSection = !sectionValue || rowSection === sectionValue;
        
        // فحص فلتر الحساب
        let matchesAccount = true;
        if (accountValue === 'has-account') {
            matchesAccount = hasAccount === 'yes';
        } else if (accountValue === 'no-account') {
            matchesAccount = hasAccount === 'no';
        }
        
        if (matchesSearch && matchesClass && matchesSection && matchesAccount) {
            row.style.display = '';
            visibleCount++;
            
            // تتبع رؤية المجموعات
            const groupKey = rowClass + '-' + rowSection;
            groupVisibility[groupKey] = true;
        } else {
            row.style.display = 'none';
        }
    });
    
    // إخفاء أو إظهار المجموعات الفارغة
    groups.forEach(group => {
        const groupTable = group.querySelector('tbody');
        const visibleRows = groupTable ? groupTable.querySelectorAll('tr.student-row:not([style*="display: none"])') : [];
        
        if (visibleRows.length === 0) {
            group.style.display = 'none';
        } else {
            group.style.display = '';
            // تحديث عداد المجموعة
            const countSpan = group.querySelector('.group-header span');
            if (countSpan) {
                countSpan.textContent = visibleRows.length + ' تلميذ';
            }
        }
    });
    
    // عرض عدد النتائج
    const countSpan = document.getElementById('studentSearchCount');
    if (countSpan) {
        if (searchValue || classValue || sectionValue || accountValue) {
            countSpan.textContent = '📊 ' + visibleCount + ' نتيجة';
        } else {
            countSpan.textContent = '';
        }
    }
    
    // تحديث إجمالي العداد
    const totalCount = document.getElementById('studentCount');
    if (totalCount) {
        if (searchValue || classValue || sectionValue || accountValue) {
            totalCount.textContent = '(' + visibleCount + ' من أصل ' + rows.length + ' تلميذ)';
        } else {
            totalCount.textContent = '(' + rows.length + ' تلميذ)';
        }
    }
    
    // تمييز حقل البحث
    if (searchInput && searchValue) {
        searchInput.style.borderColor = 'var(--primary)';
        searchInput.style.boxShadow = '0 0 0 3px rgba(102, 126, 234, 0.2)';
    } else if (searchInput) {
        searchInput.style.borderColor = '';
        searchInput.style.boxShadow = '';
    }
}

// دالة مسح جميع الفلاتر
function resetStudentFilters() {
    const searchInput = document.getElementById('studentSearchLive');
    const classFilter = document.getElementById('classFilterLive');
    const sectionFilter = document.getElementById('sectionFilterLive');
    const accountFilter = document.getElementById('accountFilterLive');
    
    if (searchInput) searchInput.value = '';
    if (classFilter) classFilter.value = '';
    if (sectionFilter) sectionFilter.value = '';
    if (accountFilter) accountFilter.value = '';
    
    filterStudentsLive();
}
</script>

<!-- Modal لإنشاء حساب تلميذ - توليد تلقائي -->
<div id="createAccountModal" class="modal" style="display: none;">
    <div class="modal-overlay" onclick="closeAccountModal()"></div>
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
            <h3 style="color: white; margin: 0;">🔑 إنشاء حساب دخول للتلميذ</h3>
            <button type="button" class="modal-close" onclick="closeAccountModal()">&times;</button>
        </div>
        
        <!-- المرحلة 1: إنشاء الحساب -->
        <div id="createAccountStep1">
            <form action="controllers/student_user_handler.php" method="POST" id="createAccountForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create_account">
                <input type="hidden" name="student_id" id="modal_student_id" value="">
                <input type="hidden" name="username" id="modal_username" value="">
                <input type="hidden" name="password" id="modal_password" value="">
                
                <div class="modal-body" style="text-align: center; padding: 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">👨‍🎓</div>
                    <h4 id="modal_student_name" style="margin-bottom: 0.5rem;"></h4>
                    <p style="color: #666; margin-bottom: 1.5rem;">سيتم إنشاء حساب دخول لهذا التلميذ</p>
                    
                    <div style="background: #f0fdf4; border: 2px solid #10b981; border-radius: 12px; padding: 1rem; margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span style="color: #666;">اسم المستخدم:</span>
                            <strong id="preview_username" style="font-family: monospace; color: #10b981;"></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #666;">كلمة المرور:</span>
                            <strong id="preview_password" style="font-family: monospace; color: #10b981;"></strong>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-secondary btn-sm" onclick="regenerateCredentials()" style="margin-bottom: 1rem;">
                        🔄 توليد بيانات جديدة
                    </button>
                </div>
                
                <div class="modal-footer" style="justify-content: center; gap: 1rem;">
                    <button type="submit" class="btn btn-success" style="padding: 0.75rem 2rem;">
                        ✅ إنشاء وطباعة البطاقة
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeAccountModal()">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal بطاقة الدخول للطباعة بعد الإنشاء -->
<div id="newAccountCardModal" class="modal" style="display: none;">
    <div class="modal-overlay" onclick="closeNewAccountCard()"></div>
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h3 style="color: white; margin: 0;">✅ تم إنشاء الحساب بنجاح!</h3>
            <button class="modal-close" onclick="closeNewAccountCard()">&times;</button>
        </div>
        <div class="modal-body" id="newAccountCardContent" style="padding: 1.5rem;">
        </div>
        <div class="modal-footer" style="display: flex; gap: 0.5rem; justify-content: center;">
            <button class="btn btn-primary" onclick="printNewAccountCard()">
                🖨️ طباعة البطاقة
            </button>
            <button class="btn btn-secondary" onclick="closeNewAccountCard()">
                إغلاق
            </button>
        </div>
    </div>
</div>

<style>
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
}

.modal-content {
    position: relative;
    background: var(--bg-primary);
    border-radius: var(--radius-md);
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    width: 90%;
    max-width: 450px;
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-secondary);
    padding: 0;
    line-height: 1;
}

.modal-close:hover {
    color: var(--danger);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
}

/* أنماط متجاوبة للـ Modal على الهواتف */
@media (max-width: 768px) {
    .modal-content {
        width: 95% !important;
        max-width: 95% !important;
        max-height: 90vh !important;
    }
    
    #viewStudentModal .modal-content {
        max-width: 95% !important;
        display: flex;
        flex-direction: column;
    }
    
    #viewStudentModal .modal-header {
        position: sticky;
        top: 0;
        z-index: 10;
        flex-shrink: 0;
    }
    
    #viewStudentModal .modal-body {
        flex: 1;
        overflow-y: auto;
    }
    
    #viewStudentModal .modal-footer {
        position: sticky;
        bottom: 0;
        background: var(--bg-primary);
        z-index: 10;
        flex-shrink: 0;
    }
    
    #viewStudentModal .modal-body > div[style*="grid-template-columns: repeat(4"] {
        grid-template-columns: repeat(2, 1fr) !important;
    }
    
    #viewStudentModal .modal-body > div > div[style*="grid-template-columns: repeat(4"] {
        grid-template-columns: repeat(2, 1fr) !important;
    }
    
    .modal-header h3 {
        font-size: 0.95rem;
    }
    
    .modal-close {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,0.2);
        border-radius: 50%;
    }
}

@media (max-width: 480px) {
    #viewStudentModal .modal-body > div[style*="grid-template-columns"],
    #viewStudentModal .modal-body > div > div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script>
// متغيرات التوليد
var currentStudentId = null;
var currentStudentName = '';
var generatedUsername = '';
var generatedPassword = '';

// تحويل الاسم العربي للإنجليزي (مبسط)
function arabicToEnglish(name) {
    const map = {
        'أ': 'a', 'ا': 'a', 'إ': 'e', 'آ': 'a', 'ب': 'b', 'ت': 't', 'ث': 'th',
        'ج': 'j', 'ح': 'h', 'خ': 'kh', 'د': 'd', 'ذ': 'th', 'ر': 'r', 'ز': 'z',
        'س': 's', 'ش': 'sh', 'ص': 's', 'ض': 'd', 'ط': 't', 'ظ': 'z', 'ع': 'a',
        'غ': 'gh', 'ف': 'f', 'ق': 'q', 'ك': 'k', 'ل': 'l', 'م': 'm', 'ن': 'n',
        'ه': 'h', 'و': 'w', 'ي': 'y', 'ى': 'a', 'ة': 'a', 'ئ': 'e', 'ء': '',
        'ؤ': 'o', ' ': ''
    };
    return name.split('').map(c => map[c] || c).join('').toLowerCase().substring(0, 8);
}

// توليد كلمة مرور بسيطة
function generateSimplePassword() {
    const numbers = Math.floor(1000 + Math.random() * 9000); // رقم من 4 خانات
    return numbers.toString();
}

// توليد اسم مستخدم
function generateUsername(fullName) {
    const firstName = fullName.split(' ')[0];
    const englishName = arabicToEnglish(firstName);
    const randomNum = Math.floor(10 + Math.random() * 90);
    return englishName + randomNum;
}

// توليد بيانات جديدة
function regenerateCredentials() {
    generatedUsername = generateUsername(currentStudentName);
    generatedPassword = generateSimplePassword();
    
    document.getElementById('preview_username').textContent = generatedUsername;
    document.getElementById('preview_password').textContent = generatedPassword;
    document.getElementById('modal_username').value = generatedUsername;
    document.getElementById('modal_password').value = generatedPassword;
}

function openAccountModal(studentId, studentName) {
    currentStudentId = studentId;
    currentStudentName = studentName;
    
    document.getElementById('modal_student_id').value = studentId;
    document.getElementById('modal_student_name').textContent = studentName;
    
    // توليد بيانات تلقائية
    regenerateCredentials();
    
    document.getElementById('createAccountModal').style.display = 'flex';
}

function closeAccountModal() {
    document.getElementById('createAccountModal').style.display = 'none';
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAccountModal();
        closeViewModal();
        closeNewAccountCard();
    }
});

// دوال بطاقة الحساب الجديد
function closeNewAccountCard() {
    var modal = document.getElementById('newAccountCardModal');
    if (modal) modal.style.display = 'none';
}

function printNewAccountCard() {
    window.print();
}

// عرض تفاصيل التلميذ
function viewStudent(studentId) {
    // جلب البيانات عبر AJAX
    const baseUrl = window.location.origin;
    fetch(baseUrl + '/controllers/get_student.php?id=' + studentId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showStudentModal(data.student);
            } else {
                alert('خطأ في جلب البيانات');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ');
        });
}

function showStudentModal(student) {
    const modal = document.getElementById('viewStudentModal');
    if (!modal) return;
    
    // تحديث البيانات
    document.getElementById('view_student_name').textContent = student.full_name || '-';
    document.getElementById('view_student_photo').src = student.photo ? '/uploads/students/' + student.photo : '';
    document.getElementById('view_student_photo').style.display = student.photo ? 'block' : 'none';
    document.getElementById('view_student_placeholder').style.display = student.photo ? 'none' : 'flex';
    
    // البيانات الأساسية
    document.getElementById('view_class').textContent = student.class_name || '-';
    document.getElementById('view_section').textContent = student.section || '-';
    document.getElementById('view_gender').textContent = student.gender === 'male' ? 'ذكر' : 'أنثى';
    document.getElementById('view_province').textContent = student.province || '-';
    document.getElementById('view_city').textContent = student.city_village || '-';
    document.getElementById('view_birth_date').textContent = student.birth_date || '-';
    document.getElementById('view_birth_place').textContent = student.birth_place || '-';
    document.getElementById('view_address').textContent = student.address || '-';
    document.getElementById('view_sibling_order').textContent = student.sibling_order || '-';
    document.getElementById('view_registration_number').textContent = student.registration_number || '-';
    document.getElementById('view_enrollment_date').textContent = student.enrollment_date || '-';
    document.getElementById('view_nationality_number').textContent = student.nationality_number || '-';
    
    // بيانات ولي الأمر
    document.getElementById('view_parent_name').textContent = student.parent_name || '-';
    document.getElementById('view_guardian_relation').textContent = student.guardian_relation || '-';
    document.getElementById('view_guardian_job').textContent = student.guardian_job || '-';
    document.getElementById('view_parent_phone').textContent = student.parent_phone || '-';
    document.getElementById('view_mother_name').textContent = student.mother_name || '-';
    document.getElementById('view_father_alive').textContent = student.father_alive || '-';
    document.getElementById('view_mother_alive').textContent = student.mother_alive || '-';
    document.getElementById('view_father_education').textContent = student.father_education || '-';
    document.getElementById('view_mother_education').textContent = student.mother_education || '-';
    document.getElementById('view_father_age').textContent = student.father_age_at_registration || '-';
    document.getElementById('view_mother_age').textContent = student.mother_age_at_registration || '-';
    document.getElementById('view_parents_kinship').textContent = student.parents_kinship || '-';
    
    // ملاحظات
    document.getElementById('view_notes').textContent = student.notes || '-';
    document.getElementById('view_data_changes').textContent = student.data_changes || '-';
    
    // المدارس السابقة
    renderPreviousSchools(student.previous_schools);
    
    // الحالة الاجتماعية
    renderSocialStatus(student.social_status);
    
    // الصفات الجسمية والحالة الصحية
    renderHealthStatus(student.health_status);
    
    // التحصيل الدراسي
    renderAcademicRecords(student.academic_records);
    
    // المواظبة والدوام
    renderAttendanceRecords(student.attendance_records);
    
    modal.style.display = 'flex';
}

function renderPreviousSchools(data) {
    const container = document.getElementById('view_previous_schools_container');
    if (!data || data === 'null' || data === '') {
        container.innerHTML = '<p style="color: #888; font-style: italic;">لا توجد مدارس مسجلة</p>';
        return;
    }
    try {
        const schools = JSON.parse(data);
        if (!Array.isArray(schools) || schools.length === 0) {
            container.innerHTML = '<p style="color: #888; font-style: italic;">لا توجد مدارس مسجلة</p>';
            return;
        }
        let html = '<table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;"><thead style="background: #10b981; color: white;"><tr>';
        html += '<th style="padding: 8px; border: 1px solid #ddd;">المدرسة/المعهد</th>';
        html += '<th style="padding: 8px; border: 1px solid #ddd;">المحافظة</th>';
        html += '<th style="padding: 8px; border: 1px solid #ddd;">تاريخ الالتحاق</th>';
        html += '<th style="padding: 8px; border: 1px solid #ddd;">رقم القيد</th>';
        html += '<th style="padding: 8px; border: 1px solid #ddd;">تاريخ الانتقال</th>';
        html += '<th style="padding: 8px; border: 1px solid #ddd;">ملاحظات</th>';
        html += '</tr></thead><tbody>';
        schools.forEach(s => {
            html += '<tr>';
            html += `<td style="padding: 8px; border: 1px solid #ddd;">${s.school_name || '-'}</td>`;
            html += `<td style="padding: 8px; border: 1px solid #ddd;">${s.province || '-'}</td>`;
            html += `<td style="padding: 8px; border: 1px solid #ddd;">${s.enrollment_date || '-'}</td>`;
            html += `<td style="padding: 8px; border: 1px solid #ddd;">${s.registration_number || '-'}</td>`;
            html += `<td style="padding: 8px; border: 1px solid #ddd;">${s.transfer_date || '-'}</td>`;
            html += `<td style="padding: 8px; border: 1px solid #ddd;">${s.notes || '-'}</td>`;
            html += '</tr>';
        });
        html += '</tbody></table>';
        container.innerHTML = html;
    } catch (e) {
        container.innerHTML = '<p style="color: #888; font-style: italic;">لا توجد مدارس مسجلة</p>';
    }
}

function renderSocialStatus(data) {
    const container = document.getElementById('view_social_status_container');
    if (!data || data === 'null' || data === '') {
        container.innerHTML = '<p style="color: #888; font-style: italic;">لا توجد بيانات مسجلة</p>';
        return;
    }
    try {
        const records = JSON.parse(data);
        if (!Array.isArray(records) || records.length === 0) {
            container.innerHTML = '<p style="color: #888; font-style: italic;">لا توجد بيانات مسجلة</p>';
            return;
        }
        let html = '';
        records.forEach(r => {
            html += `<div style="background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 12px; margin-bottom: 10px;">`;
            html += `<strong style="color: #10b981;">📅 ${r.year || '-'}</strong>`;
            html += `<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 8px; font-size: 0.85rem;">`;
            html += `<div><span style="color: #666;">عدد أفراد الأسرة:</span> <strong>${r.family_members || '-'}</strong></div>`;
            html += `<div><span style="color: #666;">عدد الأخوة والأخوات:</span> <strong>${r.siblings || '-'}</strong></div>`;
            html += `<div><span style="color: #666;">دخل الأسرة:</span> <strong>${r.income || '-'}</strong></div>`;
            html += `<div><span style="color: #666;">عدد الغرف:</span> <strong>${r.rooms || '-'}</strong></div>`;
            html += `<div><span style="color: #666;">يعيش مع:</span> <strong>${r.lives_with || '-'}</strong></div>`;
            html += `<div><span style="color: #666;">هل يعمل:</span> <strong>${r.works || '-'}</strong></div>`;
            html += `<div><span style="color: #666;">ملاءمة البيت:</span> <strong>${r.home_suitable || '-'}</strong></div>`;
            html += `</div></div>`;
        });
        container.innerHTML = html;
    } catch (e) {
        container.innerHTML = '<p style="color: #888; font-style: italic;">لا توجد بيانات مسجلة</p>';
    }
}

function renderHealthStatus(data) {
    const container = document.getElementById('view_health_status_container');
    if (!data || data === 'null' || data === '') {
        container.innerHTML = '<p style="color: #888; font-style: italic;">لا توجد بيانات مسجلة</p>';
        return;
    }
    try {
        const records = JSON.parse(data);
        if (!Array.isArray(records) || records.length === 0) {
            container.innerHTML = '<p style="color: #888; font-style: italic;">لا توجد بيانات مسجلة</p>';
            return;
        }
        let html = '';
        records.forEach(r => {
            html += `<div style="background: #ecfeff; border: 1px solid #67e8f9; border-radius: 8px; padding: 12px; margin-bottom: 10px;">`;
            html += `<strong style="color: #0891b2;">📅 ${r.year || '-'}</strong>`;
            html += `<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 8px; font-size: 0.85rem;">`;
            html += `<div><span style="color: #666;">الطول:</span> <strong>${r.height ? r.height + ' سم' : '-'}</strong></div>`;
            html += `<div><span style="color: #666;">الوزن:</span> <strong>${r.weight ? r.weight + ' كغم' : '-'}</strong></div>`;
            html += `<div><span style="color: #666;">البصر (اليمنى):</span> <strong>${r.vision_right || '-'}</strong></div>`;
            html += `<div><span style="color: #666;">البصر (اليسرى):</span> <strong>${r.vision_left || '-'}</strong></div>`;
            html += `<div><span style="color: #666;">السمع:</span> <strong>${r.hearing || '-'}</strong></div>`;
            html += `<div><span style="color: #666;">النطق:</span> <strong>${r.speech || '-'}</strong></div>`;
            html += `<div><span style="color: #666;">التلقيحات:</span> <strong>${r.vaccinations_complete || '-'}</strong></div>`;
            html += `<div><span style="color: #666;">اللقاحات المزودة:</span> <strong>${r.vaccines_provided || '-'}</strong></div>`;
            html += `</div></div>`;
        });
        container.innerHTML = html;
    } catch (e) {
        container.innerHTML = '<p style="color: #888; font-style: italic;">لا توجد بيانات مسجلة</p>';
    }
}

const subjectNames = [
    'التربية الإسلامية والتفسير',
    'اللغة العربية واملاء',
    'اللغة الكردية',
    'اللغة الانكليزية',
    'الرياضيات',
    'التربية الوطنية',
    'التاريخ',
    'الجغرافية',
    'الاجتماعيات',
    'العلوم والتربية الصحية',
    'التربية الزراعية',
    'التربية الفنية',
    'التربية الرياضية',
    'النشيد والموسيقى',
    'التربية الأسرية',
    'القرآن الكريم'
];

function renderAcademicRecords(data) {
    const container = document.getElementById('view_academic_records_container');
    if (!container) return;
    if (!data || data === 'null' || data === '') {
        container.innerHTML = '<p style="color: #888; font-style: italic;">لا توجد بيانات مسجلة</p>';
        return;
    }
    try {
        const records = JSON.parse(data);
        if (!Array.isArray(records) || records.length === 0) {
            container.innerHTML = '<p style="color: #888; font-style: italic;">لا توجد بيانات مسجلة</p>';
            return;
        }
        let html = '';
        records.forEach(r => {
            html += `<div style="background: #faf5ff; border: 1px solid #c4b5fd; border-radius: 8px; padding: 12px; margin-bottom: 10px;">`;
            html += `<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">`;
            html += `<strong style="color: #6b21a8;">📅 ${r.year || '-'} | الصف: ${r.grade || '-'} | الشعبة: ${r.section || '-'}</strong>`;
            html += `<span style="background: #8b5cf6; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem;">المجموع: ${r.total || '-'} | الترتيب: ${r.rank || '-'}</span>`;
            html += `</div>`;
            html += `<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; font-size: 0.85rem;">`;
            subjectNames.forEach((subject, index) => {
                const value = r['subject_' + index] || '-';
                html += `<div style="display: flex; justify-content: space-between; background: ${index % 2 === 0 ? '#f5f3ff' : '#ffffff'}; padding: 4px 8px; border-radius: 4px;">`;
                html += `<span style="color: #6b21a8;">${subject}:</span>`;
                html += `<strong>${value}</strong>`;
                html += `</div>`;
            });
            html += `</div></div>`;
        });
        container.innerHTML = html;
    } catch (e) {
        container.innerHTML = '<p style="color: #888; font-style: italic;">لا توجد بيانات مسجلة</p>';
    }
}

const attendanceFieldLabels = {
    'absence_first_excused': 'مجموع أيام الغياب للنصف الأول - بعذر',
    'absence_first_unexcused': 'مجموع أيام الغياب للنصف الأول - بدون عذر',
    'absence_second_excused': 'مجموع أيام الغياب للنصف الثاني - بعذر',
    'absence_second_unexcused': 'مجموع أيام الغياب للنصف الثاني - بدون عذر',
    'absence_reasons': 'أسباب الغياب',
    'compulsory_leave': 'الأجازات الأجبارية الممنوحة للطالب لأصابته بأحد الأمراض مع ذكر المرض المعني',
    'actions_taken': 'الأجراءات التي أتخذت لمعالجة ظاهرة الغياب'
};

function renderAttendanceRecords(data) {
    const container = document.getElementById('view_attendance_records_container');
    if (!container) return;
    if (!data || data === 'null' || data === '') {
        container.innerHTML = '<p style="color: #888; font-style: italic;">لا توجد بيانات مسجلة</p>';
        return;
    }
    try {
        const records = JSON.parse(data);
        if (!Array.isArray(records) || records.length === 0) {
            container.innerHTML = '<p style="color: #888; font-style: italic;">لا توجد بيانات مسجلة</p>';
            return;
        }
        let html = '';
        records.forEach(r => {
            html += `<div style="background: #ecfeff; border: 1px solid #67e8f9; border-radius: 8px; padding: 12px; margin-bottom: 10px;">`;
            html += `<strong style="color: #0891b2;">📅 ${r.year || '-'}</strong>`;
            html += `<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-top: 10px; font-size: 0.85rem;">`;
            Object.keys(attendanceFieldLabels).forEach(key => {
                const value = r[key] || '-';
                html += `<div style="display: flex; gap: 8px; background: #f0fdfa; padding: 6px 10px; border-radius: 4px;">`;
                html += `<span style="color: #0e7490; font-weight: 500;">${attendanceFieldLabels[key]}:</span>`;
                html += `<strong>${value}</strong>`;
                html += `</div>`;
            });
            html += `</div></div>`;
        });
        container.innerHTML = html;
    } catch (e) {
        container.innerHTML = '<p style="color: #888; font-style: italic;">لا توجد بيانات مسجلة</p>';
    }
}

function closeViewModal() {
    document.getElementById('viewStudentModal').style.display = 'none';
}
</script>

<!-- Modal لعرض بيانات التلميذ -->
<div id="viewStudentModal" class="modal" style="display: none;">
    <div class="modal-overlay" onclick="closeViewModal()"></div>
    <div class="modal-content" style="max-width: 1000px; max-height: 90vh;">
        <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h3 style="color: white; margin: 0;">👁️ بيانات التلميذ الكاملة</h3>
            <button type="button" class="modal-close" onclick="closeViewModal()" style="color: white;">&times;</button>
        </div>
        <div class="modal-body" style="padding: 0; overflow-y: auto; max-height: calc(90vh - 120px);">
            <!-- رأس البطاقة -->
            <div style="text-align: center; padding: 2rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <div id="view_student_placeholder" style="width: 100px; height: 100px; background: rgba(255,255,255,0.2); border-radius: 50%; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; font-size: 3rem;">👤</div>
                <img id="view_student_photo" src="" alt="" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin: 0 auto 1rem; border: 3px solid white; display: none;">
                <h2 id="view_student_name" style="margin: 0; color: white;"></h2>
            </div>
            
            <!-- البيانات الأساسية -->
            <div style="padding: 1.5rem; border-bottom: 1px solid #e9ecef;">
                <h4 style="color: #667eea; margin-bottom: 1rem; border-bottom: 2px solid #667eea; padding-bottom: 0.5rem;">📋 البيانات الأولية</h4>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                    <div><label style="font-size: 0.8rem; color: #666;">الصف</label><div id="view_class" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">الشعبة</label><div id="view_section" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">الجنس</label><div id="view_gender" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">المحافظة</label><div id="view_province" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">المدينة أو القرية</label><div id="view_city" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">محل التولد</label><div id="view_birth_place" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">تاريخ التولد</label><div id="view_birth_date" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">ترتيبه بين أخوته وأخواته</label><div id="view_sibling_order" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">رقمه في سجل القيد العام</label><div id="view_registration_number" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">تاريخ الالتحاق</label><div id="view_enrollment_date" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">رقم الجنسية العراقية</label><div id="view_nationality_number" style="font-weight: 600;">-</div></div>
                    <div style="grid-column: span 2;"><label style="font-size: 0.8rem; color: #666;">عنوان المسكن</label><div id="view_address" style="font-weight: 600;">-</div></div>
                </div>
            </div>
            
            <!-- بيانات ولي الأمر -->
            <div style="padding: 1.5rem; border-bottom: 1px solid #e9ecef;">
                <h4 style="color: #667eea; margin-bottom: 1rem; border-bottom: 2px solid #667eea; padding-bottom: 0.5rem;">👨‍👩‍👦 بيانات ولي الأمر والوالدين</h4>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                    <div><label style="font-size: 0.8rem; color: #666;">الاسم الثلاثي لولي الأمر</label><div id="view_parent_name" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">صلته بالتلميذ</label><div id="view_guardian_relation" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">مهنته</label><div id="view_guardian_job" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">رقم الهاتف</label><div id="view_parent_phone" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">اسم الأم الثلاثي</label><div id="view_mother_name" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">هل الأب على قيد الحياة</label><div id="view_father_alive" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">هل الأم على قيد الحياة</label><div id="view_mother_alive" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">درجة القرابة بين الوالدين</label><div id="view_parents_kinship" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">التحصيل الدراسي : للأب</label><div id="view_father_education" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">التحصيل الدراسي : للأم</label><div id="view_mother_education" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">عمر الأب عند تسجيل الطالب في المدرسة</label><div id="view_father_age" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">عمر الأم عند تسجيل الطالب في المدرسة</label><div id="view_mother_age" style="font-weight: 600;">-</div></div>
                </div>
            </div>
            
            <!-- المدارس السابقة -->
            <div style="padding: 1.5rem; border-bottom: 1px solid #e9ecef;">
                <h4 style="color: #10b981; margin-bottom: 1rem; border-bottom: 2px solid #10b981; padding-bottom: 0.5rem;">🏛️ المدارس والمعاهد التي التحق بها</h4>
                <div id="view_previous_schools_container" style="overflow-x: auto;"></div>
            </div>
            
            <!-- الحالة الاجتماعية -->
            <div style="padding: 1.5rem; border-bottom: 1px solid #e9ecef;">
                <h4 style="color: #10b981; margin-bottom: 1rem; border-bottom: 2px solid #10b981; padding-bottom: 0.5rem;">👨‍👩‍👧‍👦 الحالة الاجتماعية</h4>
                <div id="view_social_status_container"></div>
            </div>
            
            <!-- الصفات الجسمية والحالة الصحية -->
            <div style="padding: 1.5rem; border-bottom: 1px solid #e9ecef;">
                <h4 style="color: #0891b2; margin-bottom: 1rem; border-bottom: 2px solid #0891b2; padding-bottom: 0.5rem;">🏥 الصفات الجسمية والحالة الصحية</h4>
                <div id="view_health_status_container"></div>
            </div>
            
            <!-- التحصيل الدراسي -->
            <div style="padding: 1.5rem; border-bottom: 1px solid #e9ecef;">
                <h4 style="color: #8b5cf6; margin-bottom: 1rem; border-bottom: 2px solid #8b5cf6; padding-bottom: 0.5rem;">📚 التحصيل الدراسي - دروس المرحلة الابتدائية</h4>
                <div id="view_academic_records_container"></div>
            </div>
            
            <!-- المواظبة والدوام -->
            <div style="padding: 1.5rem; border-bottom: 1px solid #e9ecef;">
                <h4 style="color: #0891b2; margin-bottom: 1rem; border-bottom: 2px solid #0891b2; padding-bottom: 0.5rem;">📅 المواظبة والدوام</h4>
                <div id="view_attendance_records_container"></div>
            </div>
            
            <!-- ملاحظات -->
            <div style="padding: 1.5rem;">
                <h4 style="color: #667eea; margin-bottom: 1rem; border-bottom: 2px solid #667eea; padding-bottom: 0.5rem;">📝 التغييرات والملاحظات</h4>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                    <div><label style="font-size: 0.8rem; color: #666;">التغييرات التي تطرأ على البيانات السابقة</label><div id="view_data_changes" style="font-weight: 600; color: #666;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">ملاحظات</label><div id="view_notes" style="font-weight: 600; color: #666;">-</div></div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeViewModal()">إغلاق</button>
        </div>
    </div>
</div>

<!-- Modal بطاقة دخول التلميذ -->
<div id="loginCardModal" class="modal" style="display: none;">
    <div class="modal-overlay" onclick="closeLoginCardModal()"></div>
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
            <h3 style="color: white; margin: 0;">✅ تم إنشاء الحساب بنجاح!</h3>
            <button class="modal-close" onclick="closeLoginCardModal()" style="background: rgba(255,255,255,0.2); border: none; color: white; font-size: 1.5rem; width: 32px; height: 32px; border-radius: 50%; cursor: pointer;">&times;</button>
        </div>
        <div class="modal-body" id="loginCardContent">
        </div>
        <div class="modal-footer" style="display: flex; gap: 0.5rem; justify-content: center;">
            <button class="btn btn-primary" onclick="printLoginCard()">
                🖨️ طباعة البطاقة
            </button>
            <button class="btn btn-secondary" onclick="closeLoginCardModal()">
                إغلاق
            </button>
        </div>
    </div>
</div>

<style>
#loginCardModal .modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.6);
    z-index: 9998;
}

#loginCardModal .modal-content {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: var(--bg-primary);
    border-radius: 16px;
    z-index: 9999;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    overflow: hidden;
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from { opacity: 0; transform: translate(-50%, -50%) scale(0.9); }
    to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
}

#loginCardModal .modal-header {
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

#loginCardModal .modal-body {
    padding: 1.5rem;
}

#loginCardModal .modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    background: var(--bg-secondary);
}

.login-card {
    text-align: center;
    background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
    border-radius: 12px;
    padding: 1.5rem;
    border: 2px solid var(--primary);
}

.login-card-school { font-size: 0.85rem; color: #666; margin-bottom: 0.5rem; }
.login-card-title { font-size: 1.1rem; font-weight: 700; color: var(--primary); margin-bottom: 1rem; }
.login-card-qr { margin: 1rem auto; padding: 10px; background: white; border-radius: 8px; display: inline-block; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.login-card-info { margin-top: 1rem; text-align: right; }
.login-card-info-row { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px dashed #ccc; }
.login-card-info-row:last-child { border-bottom: none; }
.login-card-label { font-weight: 600; color: #555; font-size: 0.85rem; }
.login-card-value { font-weight: 700; color: #333; font-size: 0.95rem; direction: ltr; font-family: monospace; }
.login-card-note { margin-top: 1rem; font-size: 0.75rem; color: #888; background: rgba(255,255,255,0.7); padding: 0.5rem; border-radius: 6px; }

/* زر إنشاء الحساب */
.btn-create-account {
    transition: all 0.3s ease;
}
.btn-create-account.loading {
    pointer-events: none;
    opacity: 0.7;
}

/* أنماط الطباعة للبطاقة */
@media print {
    body > *:not(#loginCardModal) {
        display: none !important;
    }
    
    #loginCardModal {
        display: block !important;
        position: static !important;
    }
    
    #loginCardModal .modal-overlay {
        display: none !important;
    }
    
    #loginCardModal .modal-content {
        position: static !important;
        transform: none !important;
        box-shadow: none !important;
        max-width: 100% !important;
        margin: 0 auto !important;
    }
    
    #loginCardModal .modal-header,
    #loginCardModal .modal-footer {
        display: none !important;
    }
    
    #loginCardModal .modal-body {
        padding: 0 !important;
    }
    
    .login-card {
        visibility: visible !important;
        display: block !important;
        background: #f5f7fa !important;
        border: 2px solid #667eea !important;
        padding: 25px !important;
        margin: 20px auto !important;
        max-width: 350px !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>

<script>
<?php 
// إنشاء رابط الموقع
$siteProtocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$siteHost = $_SERVER['HTTP_HOST'];
$sitePath = rtrim(dirname($_SERVER['REQUEST_URI']), '/');
$fullSiteUrl = $siteProtocol . '://' . $siteHost . $sitePath . '/';
?>
const siteUrl = '<?= $fullSiteUrl ?>';
const loginUrl = siteUrl + 'login.php';
const csrfToken = '<?= generateCSRFToken() ?>';

// إنشاء حساب تلميذ تلقائياً
function createStudentAccount(studentId, btn) {
    if (!confirm('هل تريد إنشاء حساب دخول لهذا التلميذ؟')) {
        return;
    }
    
    // تغيير شكل الزر أثناء التحميل
    const originalContent = btn.innerHTML;
    btn.innerHTML = '⏳';
    btn.classList.add('loading');
    btn.disabled = true;
    
    // إرسال الطلب
    fetch('/controllers/student_user_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=quick_create_ajax&student_id=${studentId}&csrf_token=${csrfToken}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // تغيير الزر لعلامة ✓
            btn.innerHTML = '✓';
            btn.classList.remove('loading', 'btn-success');
            btn.classList.add('btn-primary');
            btn.disabled = true;
            btn.title = 'لديه حساب';
            btn.style.cursor = 'default';
            
            // عرض بطاقة الدخول
            showLoginCard(data.username, data.full_name, data.class, data.section, data.password);
        } else {
            // إعادة الزر لحالته الأصلية
            btn.innerHTML = originalContent;
            btn.classList.remove('loading');
            btn.disabled = false;
            
            alert('❌ ' + (data.error || 'حدث خطأ أثناء إنشاء الحساب'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        btn.innerHTML = originalContent;
        btn.classList.remove('loading');
        btn.disabled = false;
        alert('❌ حدث خطأ في الاتصال');
    });
}

// عرض بطاقة الدخول
function showLoginCard(username, fullName, className, section, password) {
    const modal = document.getElementById('loginCardModal');
    const content = document.getElementById('loginCardContent');
    
    // استخدام QR Server API
    const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent(loginUrl) + '&color=667eea';
    
    content.innerHTML = `
        <div class="login-card" id="printableCard">
            <div class="login-card-school">🏫 مدرسة بعشيقة الابتدائية للبنين</div>
            <div class="login-card-title">بطاقة دخول النظام الإلكتروني</div>
            
            <div class="login-card-qr">
                <img src="${qrUrl}" alt="QR Code" style="width: 150px; height: 150px;">
            </div>
            
            <div class="login-card-info">
                <div class="login-card-info-row">
                    <span class="login-card-label">👤 اسم التلميذ:</span>
                    <span class="login-card-value" style="direction: rtl;">${fullName}</span>
                </div>
                ${className ? `
                <div class="login-card-info-row">
                    <span class="login-card-label">📚 الصف:</span>
                    <span class="login-card-value" style="direction: rtl;">${className} - ${section}</span>
                </div>
                ` : ''}
                <div class="login-card-info-row">
                    <span class="login-card-label">🔐 اسم المستخدم:</span>
                    <span class="login-card-value">${username}</span>
                </div>
                <div class="login-card-info-row">
                    <span class="login-card-label">🔑 كلمة المرور:</span>
                    <span class="login-card-value" style="font-size: 1.1rem; color: #059669;">${password}</span>
                </div>
            </div>
            
            <div class="login-card-note">
                📌 امسح الباركود أو زر: <strong>${siteUrl}</strong>
            </div>
        </div>
    `;
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeLoginCardModal() {
    document.getElementById('loginCardModal').style.display = 'none';
    document.body.style.overflow = '';
}

function printLoginCard() {
    window.print();
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLoginCardModal();
});
</script>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>

