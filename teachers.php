<?php
/**
 * بيانات الكادر - Staff Management
 * عرض وإضافة وتعديل بيانات الكادر التعليمي والإداري
 * 
 * @package SchoolManager
 * @access  مدير المدرسة فقط (لا يُسمح للمعاون)
 * @security صلاحية حصرية للمدير لمنع التلاعب بالبيانات
 */

$pageTitle = 'بيانات الكادر';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/models/Teacher.php';
require_once __DIR__ . '/models/User.php';

requireLogin();

// ═══════════════════════════════════════════════════════════════
// 🔒 صلاحية: المدير والمعاون
// ═══════════════════════════════════════════════════════════════
if (!canManageSystem()) {
    alert('⛔ إدارة بيانات الكادر متاحة للمدير والمعاون فقط', 'error');
    redirect('dashboard.php');
}

$teacherModel = new Teacher();
$userModel = new User();

$action = $_GET['action'] ?? 'list';
$editTeacher = null;

if ($action === 'edit' && isset($_GET['id'])) {
    $editTeacher = $teacherModel->findById((int)$_GET['id']);
    if (!$editTeacher) {
        alert('المعلم غير موجود', 'error');
        redirect('teachers.php');
    }
}

$teachers = $teacherModel->getAll();

// التحقق من تعيينات المعلمين
require_once __DIR__ . '/models/TeacherAssignment.php';
$assignmentModel = new TeacherAssignment();

// حساب عدد المعلمين غير المعينين
$unassignedCount = 0;
foreach ($teachers as $teacher) {
    // الحصول على user_id من معلومات المعلم
    if (isset($teacher['user_id']) && $teacher['user_id']) {
        if (!$assignmentModel->hasAssignments($teacher['user_id'])) {
            $unassignedCount++;
        }
    }
}

require_once __DIR__ . '/views/components/header.php';
?>

<style>
/* أنماط نموذج المعلم */
.form-section {
    background: var(--bg-secondary);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid var(--border-color);
}
.form-section-title {
    color: var(--primary);
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.form-section .grid {
    gap: 1rem;
}
.form-group label {
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin-bottom: 0.4rem;
    display: block;
}
.form-group label .required {
    color: var(--danger);
    margin-right: 2px;
}
.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.2s;
    background: var(--bg-primary);
}
.form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    outline: none;
}
.form-control::placeholder {
    color: var(--text-muted);
    font-size: 0.85rem;
}

/* بطاقة عرض المعلم */
.profile-card {
    background: var(--bg-primary);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
}
.profile-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 2rem;
    text-align: center;
    color: white;
}
.profile-avatar {
    width: 100px;
    height: 100px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    margin: 0 auto 1rem;
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
}
.profile-name {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}
.profile-specialty {
    opacity: 0.9;
    font-size: 1rem;
}
.profile-section {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
}
.profile-section:last-child {
    border-bottom: none;
}
.profile-section-title {
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 1rem;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.profile-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}
.profile-item {
    display: flex;
    flex-direction: column;
}
.profile-label {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-bottom: 0.25rem;
}
.profile-value {
    font-weight: 600;
    color: var(--text-primary);
}
@media (max-width: 768px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page-header d-flex justify-between align-center flex-wrap gap-2">
    <div>
        <h1><?= __('👨‍🏫 إدارة المعلمين') ?></h1>
        <p><?= __('إجمالي المعلمين') ?>: <?= count($teachers) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary"><?= __('➕ إضافة معلم') ?></a>
        <a href="teacher_assignments.php" class="btn btn-info"><?= __('📚 تعيين المواد') ?></a>
        
        <!-- أزرار التصدير -->
        <div class="dropdown" style="position: relative;">
            <button class="btn btn-success dropdown-toggle" onclick="toggleTeacherExportMenu()" id="teacherExportBtn">
                <?= __('📥 تصدير') ?>
            </button>
            <div class="dropdown-menu" id="teacherExportMenu" style="display: none; position: absolute; top: 100%; left: 0; background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-lg); z-index: 100; min-width: 180px;">
                <a href="export_report.php?type=teachers_list&format=pdf" 
                   class="dropdown-item" target="_blank" style="display: flex; align-items: center; gap: 8px; padding: 10px 15px; text-decoration: none; color: var(--text-primary);">
                    📄 PDF
                </a>
                <a href="export_report.php?type=teachers_list&format=word" 
                   class="dropdown-item" style="display: flex; align-items: center; gap: 8px; padding: 10px 15px; text-decoration: none; color: var(--text-primary);">
                    📝 Word
                </a>
            </div>
        </div>
        <?php else: ?>
        <a href="teachers.php" class="btn btn-secondary"><?= __('← العودة للقائمة') ?></a>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'list' && $unassignedCount > 0): ?>
<!-- تنبيه المعلمين غير المعينين -->
<div class="alert alert-warning mb-3" style="background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%); border: 1px solid #ffc107; border-radius: 12px; padding: 1rem; display: flex; align-items: center; gap: 1rem;">
    <div style="font-size: 1.5rem;">⚠️</div>
    <div style="flex: 1;">
        <strong>يوجد <?= $unassignedCount ?> معلم بدون تعيينات!</strong>
        <br>
        <small>لن يستطيعوا رصد الدرجات حتى يتم تعيين المواد والصفوف لهم.</small>
    </div>
    <a href="teacher_assignments.php" class="btn btn-warning btn-sm">تعيين المواد</a>
</div>
<?php endif; ?>

<script>
function toggleTeacherExportMenu() {
    const menu = document.getElementById('teacherExportMenu');
    if (menu) menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', function(e) {
    const menu = document.getElementById('teacherExportMenu');
    const btn = document.getElementById('teacherExportBtn');
    if (menu && btn && !btn.contains(e.target) && !menu.contains(e.target)) {
        menu.style.display = 'none';
    }
});
</script>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- ══════════════════════════════════════════════════════════════════ -->
<!-- نموذج إضافة/تعديل المعلم -->
<!-- ══════════════════════════════════════════════════════════════════ -->
<div class="card mb-3 fade-in">
    <div class="card-header">
        <h3><?= $action === 'add' ? '➕ إضافة معلم جديد' : '✏️ تعديل بيانات المعلم' ?></h3>
    </div>
    <div class="card-body">
        <form action="controllers/teacher_handler.php" method="POST" accept-charset="UTF-8">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="<?= $action ?>">
            <?php if ($editTeacher): ?>
            <input type="hidden" name="id" value="<?= $editTeacher['id'] ?>">
            <?php endif; ?>
            
            <!-- ═══ القسم 1: البيانات الشخصية الأساسية ═══ -->
            <div class="form-section">
                <div class="form-section-title">📋 البيانات الشخصية الأساسية</div>
                <div class="grid grid-3">
                    <div class="form-group">
                        <label><span class="required">*</span> الاسم الرباعي واللقب</label>
                        <input type="text" name="full_name" class="form-control" required
                               value="<?= sanitize($editTeacher['full_name'] ?? '') ?>"
                               placeholder="مثال: سالم جلال عبد منصور الصباغ">
                    </div>
                    <div class="form-group">
                        <label>محل الولادة</label>
                        <input type="text" name="birth_place" class="form-control"
                               value="<?= sanitize($editTeacher['birth_place'] ?? '') ?>"
                               placeholder="مثال: موصل - بعشيقة">
                    </div>
                    <div class="form-group">
                        <label>تاريخ الولادة</label>
                        <input type="date" name="birth_date" class="form-control"
                               value="<?= $editTeacher['birth_date'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>اسم الام الثلاثي</label>
                        <input type="text" name="mother_name" class="form-control"
                               value="<?= sanitize($editTeacher['mother_name'] ?? '') ?>"
                               placeholder="مثال: عذراء يوسف محمودكي">
                    </div>
                    <div class="form-group">
                        <label>رقم الهاتف او الموبايل</label>
                        <input type="text" name="phone" class="form-control"
                               value="<?= sanitize($editTeacher['phone'] ?? '') ?>"
                               placeholder="07xxxxxxxxx">
                    </div>
                    <div class="form-group">
                        <label>فصيلة الدم</label>
                        <select name="blood_type" class="form-control">
                            <option value="">-- اختر --</option>
                            <?php 
                            $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                            foreach ($bloodTypes as $type): ?>
                            <option value="<?= $type ?>" <?= ($editTeacher['blood_type'] ?? '') === $type ? 'selected' : '' ?>><?= $type ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- ═══ القسم 2: الشهادة والتخصص ═══ -->
            <div class="form-section">
                <div class="form-section-title">🎓 الشهادة والتخصص</div>
                <div class="grid grid-3">
                    <div class="form-group">
                        <label><span class="required">*</span> الشهادة</label>
                        <select name="certificate" class="form-control" required>
                            <option value="">-- اختر --</option>
                            <option value="دبلوم" <?= ($editTeacher['certificate'] ?? '') === 'دبلوم' ? 'selected' : '' ?>>دبلوم</option>
                            <option value="بكالوريوس" <?= ($editTeacher['certificate'] ?? '') === 'بكالوريوس' ? 'selected' : '' ?>>بكالوريوس</option>
                            <option value="ماجستير" <?= ($editTeacher['certificate'] ?? '') === 'ماجستير' ? 'selected' : '' ?>>ماجستير</option>
                            <option value="دكتوراه" <?= ($editTeacher['certificate'] ?? '') === 'دكتوراه' ? 'selected' : '' ?>>دكتوراه</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><span class="required">*</span> الاختصاص</label>
                        <input type="text" name="specialization" class="form-control" required
                               value="<?= sanitize($editTeacher['specialization'] ?? '') ?>"
                               placeholder="مثال: رسم، رياضيات، فيزياء">
                    </div>
                    <div class="form-group">
                        <label>اسم المعهد او الكلية</label>
                        <input type="text" name="institute_name" class="form-control"
                               value="<?= sanitize($editTeacher['institute_name'] ?? '') ?>"
                               placeholder="مثال: معهد الفنون الجميلة">
                    </div>
                    <div class="form-group">
                        <label><span class="required">*</span> سنة التخرج</label>
                        <input type="text" name="graduation_year" class="form-control" required
                               value="<?= sanitize($editTeacher['graduation_year'] ?? '') ?>"
                               placeholder="مثال: 2005">
                    </div>
                </div>
            </div>
            
            <!-- ═══ القسم 3: بيانات التعيين والوظيفة ═══ -->
            <div class="form-section">
                <div class="form-section-title">💼 بيانات التعيين والوظيفة</div>
                <div class="grid grid-3">
                    <div class="form-group">
                        <label><span class="required">*</span> تاريخ التعيين</label>
                        <input type="date" name="hire_date" class="form-control" required
                               min="1950-01-01" max="<?= date('Y-m-d') ?>"
                               value="<?= $editTeacher['hire_date'] ?? '' ?>">
                        <small style="color: var(--text-muted);">تاريخ حصول المعلم على التعيين الوظيفي</small>
                    </div>
                    <div class="form-group">
                        <label>تاريخ المباشرة بالوظيفة لاول مرة</label>
                        <input type="date" name="first_job_date" class="form-control"
                               value="<?= $editTeacher['first_job_date'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>تاريخ المباشرة بالمدرسة الحالية</label>
                        <input type="date" name="current_school_date" class="form-control"
                               value="<?= $editTeacher['current_school_date'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>رقم الامر الاداري بالتعين</label>
                        <input type="text" name="hire_order_number" class="form-control"
                               value="<?= sanitize($editTeacher['hire_order_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>تاريخ الامر الاداري بالتعين</label>
                        <input type="date" name="hire_order_date" class="form-control"
                               value="<?= $editTeacher['hire_order_date'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>رقم الامر الاداري بالمباشرة في المدرسة</label>
                        <input type="text" name="school_order_number" class="form-control"
                               value="<?= sanitize($editTeacher['school_order_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>رقم الامر الاداري بالنقل من المدرسة</label>
                        <input type="text" name="transfer_order_number" class="form-control"
                               value="<?= sanitize($editTeacher['transfer_order_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>تاريخ الانفكاك (النقل)</label>
                        <input type="date" name="transfer_date" class="form-control"
                               value="<?= $editTeacher['transfer_date'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>الدرجة الوظيفية</label>
                        <input type="text" name="job_grade" class="form-control"
                               value="<?= sanitize($editTeacher['job_grade'] ?? '') ?>"
                               placeholder="مثال: 4">
                    </div>
                    <div class="form-group">
                        <label>المرحلة في السلم الوظيفي</label>
                        <input type="text" name="career_stage" class="form-control"
                               value="<?= sanitize($editTeacher['career_stage'] ?? '') ?>"
                               placeholder="مثال: 5">
                    </div>
                    <div class="form-group">
                        <label>تاريخ الانقطاع</label>
                        <input type="date" name="interruption_date" class="form-control"
                               value="<?= $editTeacher['interruption_date'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>تاريخ العودة</label>
                        <input type="date" name="return_date" class="form-control"
                               value="<?= $editTeacher['return_date'] ?? '' ?>">
                    </div>
                </div>
                <div class="form-group mt-2">
                    <label>سبب الانقطاع</label>
                    <textarea name="interruption_reason" class="form-control" rows="2"
                              placeholder="أدخل سبب الانقطاع إن وجد"><?= sanitize($editTeacher['interruption_reason'] ?? '') ?></textarea>
                </div>
            </div>
            
            <!-- ═══ القسم 4: الوثائق والهويات ═══ -->
            <div class="form-section">
                <div class="form-section-title">🪪 الوثائق والهويات</div>
                <div class="grid grid-3">
                    <div class="form-group">
                        <label>رقم البطاقة الوطنية</label>
                        <input type="text" name="national_id" class="form-control"
                               value="<?= sanitize($editTeacher['national_id'] ?? '') ?>"
                               placeholder="مثال: 19842640799">
                    </div>
                    <div class="form-group">
                        <label>تاريخ البطاقة الوطنية</label>
                        <input type="date" name="national_id_date" class="form-control"
                               value="<?= $editTeacher['national_id_date'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>رقم السجل</label>
                        <input type="text" name="record_number" class="form-control"
                               value="<?= sanitize($editTeacher['record_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>رقم الصفحة</label>
                        <input type="text" name="page_number" class="form-control"
                               value="<?= sanitize($editTeacher['page_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>رقم شهادة الجنسية العراقية</label>
                        <input type="text" name="nationality_cert_number" class="form-control"
                               value="<?= sanitize($editTeacher['nationality_cert_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>تاريخ شهادة الجنسية</label>
                        <input type="date" name="nationality_cert_date" class="form-control"
                               value="<?= $editTeacher['nationality_cert_date'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>رقم المحفظة لشهادة الجنسية العراقية</label>
                        <input type="text" name="nationality_folder_number" class="form-control"
                               value="<?= sanitize($editTeacher['nationality_folder_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>بطاقة السكن (مكتب المعلومات)</label>
                        <input type="text" name="residence_card" class="form-control"
                               value="<?= sanitize($editTeacher['residence_card'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>رقم الاستمارة</label>
                        <input type="text" name="form_number" class="form-control"
                               value="<?= sanitize($editTeacher['form_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>رقم البطاقة التموينية</label>
                        <input type="text" name="ration_card_number" class="form-control"
                               value="<?= sanitize($editTeacher['ration_card_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>رقم الوكيل واسمه</label>
                        <input type="text" name="agent_info" class="form-control"
                               value="<?= sanitize($editTeacher['agent_info'] ?? '') ?>"
                               placeholder="مثال: 07XXXXXXX / محسن نوفجورك">
                    </div>
                    <div class="form-group">
                        <label>اسم مركز التموين</label>
                        <input type="text" name="ration_center" class="form-control"
                               value="<?= sanitize($editTeacher['ration_center'] ?? '') ?>"
                               placeholder="مثال: موصل / بعشيقة 332">
                    </div>
                </div>
            </div>
            
            <!-- ═══ القسم 5: الحالة الاجتماعية ═══ -->
            <div class="form-section">
                <div class="form-section-title">💍 الحالة الاجتماعية</div>
                <div class="grid grid-3">
                    <div class="form-group">
                        <label>الحالة الزوجية</label>
                        <select name="marital_status" class="form-control">
                            <option value="">-- اختر --</option>
                            <option value="أعزب" <?= ($editTeacher['marital_status'] ?? '') === 'أعزب' ? 'selected' : '' ?>>أعزب</option>
                            <option value="متزوج" <?= ($editTeacher['marital_status'] ?? '') === 'متزوج' ? 'selected' : '' ?>>متزوج</option>
                            <option value="مطلق" <?= ($editTeacher['marital_status'] ?? '') === 'مطلق' ? 'selected' : '' ?>>مطلق</option>
                            <option value="أرمل" <?= ($editTeacher['marital_status'] ?? '') === 'أرمل' ? 'selected' : '' ?>>أرمل</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>تاريخ الزواج</label>
                        <input type="date" name="marriage_date" class="form-control"
                               value="<?= $editTeacher['marriage_date'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>رقم وتاريخ عقد الزواج</label>
                        <input type="text" name="marriage_contract_info" class="form-control"
                               value="<?= sanitize($editTeacher['marriage_contract_info'] ?? '') ?>"
                               placeholder="مثال: 3 في 2008">
                    </div>
                    <div class="form-group">
                        <label>اسم الزوج او الزوجة</label>
                        <input type="text" name="spouse_name" class="form-control"
                               value="<?= sanitize($editTeacher['spouse_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>العمل الوظيفي للزوج او الزوجة</label>
                        <input type="text" name="spouse_job" class="form-control"
                               value="<?= sanitize($editTeacher['spouse_job'] ?? '') ?>">
                    </div>
                </div>
            </div>
            
            <!-- ═══ القسم 6: التقاعد ═══ -->
            <div class="form-section">
                <div class="form-section-title">🏖️ بيانات التقاعد</div>
                <div class="grid grid-3">
                    <div class="form-group">
                        <label>رقم الامر الاداري بالاحالة على التقاعد</label>
                        <input type="text" name="retirement_order_number" class="form-control"
                               value="<?= sanitize($editTeacher['retirement_order_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>تاريخ الانفكاك من المدرسة للتقاعد</label>
                        <input type="date" name="retirement_date" class="form-control"
                               value="<?= $editTeacher['retirement_date'] ?? '' ?>">
                    </div>
                </div>
            </div>
            
            <!-- ═══ القسم 7: معلومات إضافية ═══ -->
            <div class="form-section">
                <div class="form-section-title">📝 معلومات إضافية</div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label>الدورات التي شارك فيها</label>
                        <textarea name="courses" class="form-control" rows="3"
                                  placeholder="أدخل الدورات مفصولة بفاصلة"><?= sanitize($editTeacher['courses'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>ملاحظات</label>
                        <textarea name="notes" class="form-control" rows="3"
                                  placeholder="أي ملاحظات إضافية"><?= sanitize($editTeacher['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- ═══ القسم 8: المسؤول عن كتابة البيانات ═══ -->
            <div class="form-section">
                <div class="form-section-title">✍️ المسؤول عن كتابة البيانات</div>
                <input type="hidden" name="data_writers" id="dataWritersData" value="<?= sanitize($editTeacher['data_writers'] ?? '') ?>">
                
                <div class="data-writers-table-container">
                    <table class="data-writers-table">
                        <thead style="background: #1f2937 !important;">
                            <tr>
                                <th style="width: 5%; color: #fff !important; background: #1f2937 !important;">#</th>
                                <th style="width: 25%; color: #fff !important; background: #1f2937 !important;">الأسم الثلاثي</th>
                                <th style="width: 30%; color: #fff !important; background: #1f2937 !important;">المدرسة / المعهد / الجامعة</th>
                                <th style="width: 15%; color: #fff !important; background: #1f2937 !important;">التوقيع</th>
                                <th style="width: 20%; color: #fff !important; background: #1f2937 !important;">السنة الدراسية</th>
                                <th style="width: 5%; color: #fff !important; background: #1f2937 !important;">حذف</th>
                            </tr>
                        </thead>
                        <tbody id="dataWritersTableBody">
                            <!-- الصفوف تضاف ديناميكياً -->
                        </tbody>
                    </table>
                </div>
                
                <button type="button" class="btn btn-success btn-sm mt-2" onclick="addDataWriterRow()">
                    ➕ إضافة سطر جديد
                </button>
            </div>
            
            <style>
            .data-writers-table-container {
                overflow-x: auto;
                border: 1px solid #d1d5db;
                border-radius: 10px;
                background: #fafafa;
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            }
            .data-writers-table {
                width: 100%;
                border-collapse: collapse;
                min-width: 600px;
            }
            .data-writers-table thead {
                background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
            }
            .data-writers-table thead th {
                color: white;
                padding: 14px 10px;
                text-align: center;
                font-weight: 600;
                font-size: 0.9rem;
                border-left: 1px solid rgba(255,255,255,0.15);
            }
            .data-writers-table thead th:last-child {
                border-left: none;
            }
            .data-writers-table tbody tr {
                border-bottom: 1px solid #e5e7eb;
                transition: background 0.2s;
            }
            .data-writers-table tbody tr:hover {
                background: #f3f4f6;
            }
            .data-writers-table tbody tr:nth-child(even) {
                background: #f9fafb;
            }
            .data-writers-table tbody tr:nth-child(even):hover {
                background: #f3f4f6;
            }
            .data-writers-table tbody td {
                padding: 10px;
                text-align: center;
                border-left: 1px solid #e5e7eb;
            }
            .data-writers-table tbody td:last-child {
                border-left: none;
            }
            .data-writers-table .dw-input {
                width: 100%;
                padding: 10px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                font-size: 0.9rem;
                text-align: center;
                background: white;
                transition: border-color 0.2s, box-shadow 0.2s;
            }
            .data-writers-table .dw-input:focus {
                border-color: #6b7280;
                box-shadow: 0 0 0 3px rgba(107, 114, 128, 0.15);
                outline: none;
            }
            .data-writers-table .dw-input::placeholder {
                color: #9ca3af;
            }
            .data-writers-table .row-number {
                font-weight: 700;
                color: #4b5563;
                font-size: 0.95rem;
            }
            .data-writers-table .btn-remove {
                background: #9ca3af;
                color: white;
                border: none;
                border-radius: 6px;
                padding: 8px 12px;
                cursor: pointer;
                transition: background 0.2s;
            }
            .data-writers-table .btn-remove:hover {
                background: #ef4444;
            }
            </style>
            
            <script>
            function addDataWriterRow(data = {}) {
                const tbody = document.getElementById('dataWritersTableBody');
                const rowNum = tbody.children.length + 1;
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="row-number">${rowNum}</td>
                    <td><input type="text" class="dw-input dw-data" data-field="writer_name" 
                               value="${data.writer_name || ''}" placeholder="الاسم الثلاثي" onchange="updateDWData()"></td>
                    <td><input type="text" class="dw-input dw-data" data-field="institution" 
                               value="${data.institution || ''}" placeholder="المدرسة / المعهد / الجامعة" onchange="updateDWData()"></td>
                    <td><input type="text" class="dw-input dw-data" data-field="signature" 
                               value="${data.signature || ''}" placeholder="التوقيع" onchange="updateDWData()"></td>
                    <td><input type="text" class="dw-input dw-data" data-field="academic_year" 
                               value="${data.academic_year || ''}" placeholder="مثال: 2024/2025" onchange="updateDWData()"></td>
                    <td><button type="button" class="btn-remove" onclick="removeDataWriterRow(this)">🗑️</button></td>
                `;
                tbody.appendChild(row);
                updateDWData();
            }
            
            function removeDataWriterRow(btn) {
                btn.closest('tr').remove();
                updateRowNumbers();
                updateDWData();
            }
            
            function updateRowNumbers() {
                const rows = document.querySelectorAll('#dataWritersTableBody tr');
                rows.forEach((row, index) => {
                    row.querySelector('.row-number').textContent = index + 1;
                });
            }
            
            function updateDWData() {
                const rows = document.querySelectorAll('#dataWritersTableBody tr');
                const data = [];
                rows.forEach(row => {
                    const record = {};
                    row.querySelectorAll('.dw-data').forEach(input => {
                        record[input.dataset.field] = input.value;
                    });
                    if (record.writer_name && record.writer_name.trim()) {
                        data.push(record);
                    }
                });
                document.getElementById('dataWritersData').value = JSON.stringify(data);
            }
            
            // تحميل البيانات الموجودة عند فتح الصفحة
            document.addEventListener('DOMContentLoaded', function() {
                const existingData = document.getElementById('dataWritersData').value;
                if (existingData && existingData.trim()) {
                    try {
                        const records = JSON.parse(existingData);
                        if (Array.isArray(records) && records.length > 0) {
                            records.forEach(record => addDataWriterRow(record));
                        }
                    } catch (e) { }
                }
                // إضافة صف فارغ افتراضي إذا لم توجد بيانات
                if (document.getElementById('dataWritersTableBody').children.length === 0) {
                    addDataWriterRow();
                }
            });
            </script>
            
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    💾 <?= $action === 'add' ? 'إضافة المعلم' : 'حفظ التعديلات' ?>
                </button>
                <a href="teachers.php" class="btn btn-secondary">إلغاء</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
<!-- ══════════════════════════════════════════════════════════════════ -->
<!-- قائمة المعلمين -->
<!-- ══════════════════════════════════════════════════════════════════ -->
<div class="card fade-in">
    <div class="card-header" style="flex-direction: column; align-items: stretch; gap: 1rem;">
        <div class="d-flex justify-between align-center flex-wrap gap-2">
            <h3><?= __('📋 قائمة المعلمين') ?> <span id="teacherCount" style="font-weight: normal; color: #666; font-size: 0.9rem;">(<?= count($teachers) ?> <?= __('معلم') ?>)</span></h3>
        </div>
        <!-- 🔍 شريط الفلترة السريعة -->
        <div class="d-flex gap-2 flex-wrap align-center" style="background: var(--bg-secondary); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-color);">
            <div class="d-flex align-center gap-1" style="flex: 1; min-width: 200px;">
                <input type="text" 
                       id="teacherSearch" 
                       class="form-control" 
                       placeholder="<?= __('🔍 ابحث بالاسم، التخصص، أو ID...') ?>"
                       oninput="filterTeachers()"
                       onkeyup="filterTeachers()"
                       style="border-radius: 25px; border: 2px solid var(--border); transition: all 0.3s;">
            </div>
            <select id="accountFilter" class="form-control" style="width: auto; min-width: 130px;" onchange="filterTeachers()">
                <option value=""><?= __('🔐 كل الحسابات') ?></option>
                <option value="has-account"><?= __('✅ لديه حساب') ?></option>
                <option value="no-account"><?= __('❌ بدون حساب') ?></option>
            </select>
            <button type="button" class="btn btn-secondary btn-sm" onclick="resetTeacherFilters()" style="padding: 0.5rem 1rem;">
                <?= __('✕ مسح الفلاتر') ?>
            </button>
            <span style="color: var(--text-muted); font-size: 0.9rem;" id="teacherSearchCount"></span>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($teachers)): ?>
        <div class="empty-state">
            <div class="icon">👨‍🏫</div>
            <h3><?= __('لا يوجد معلمون') ?></h3>
            <p><?= __('ابدأ بإضافة المعلمين للنظام') ?></p>
            <a href="?action=add" class="btn btn-primary"><?= __('➕ إضافة معلم') ?></a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ID</th>
                        <th>الاسم</th>
                        <th>التخصص</th>
                        <th>الهاتف</th>
                        <th>تاريخ التعيين</th>
                        <th>الحساب</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $counter = 1; foreach ($teachers as $teacher): ?>
                    <tr>
                        <td><?= $counter++ ?></td>
                        <td><code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-weight: 600;"><?= $teacher['id'] ?></code></td>
                        <td><strong><?= sanitize($teacher['full_name']) ?></strong></td>
                        <td><?= sanitize($teacher['specialization'] ?? '-') ?></td>
                        <td><?= sanitize($teacher['phone'] ?? '-') ?></td>
                        <td><?= !empty($teacher['hire_date']) ? formatArabicDate($teacher['hire_date']) : '-' ?></td>
                        <td>
                            <?php if ($teacher['user_id']): ?>
                            <div>
                                <span class="badge badge-success"><?= sanitize($teacher['username']) ?></span>
                                <?php if ($teacher['user_role'] === 'admin'): ?>
                                <span class="badge badge-info">👨‍💼 مدير</span>
                                <?php elseif ($teacher['user_role'] === 'assistant'): ?>
                                <span class="badge badge-primary">👔 معاون</span>
                                <?php else: ?>
                                <span class="badge badge-secondary">👨‍🏫 معلم</span>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <span class="badge badge-warning">❌ بدون حساب</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-info btn-sm" title="عرض البيانات"
                                        onclick="viewTeacher(<?= $teacher['id'] ?>)">👁️</button>
                                <a href="?action=edit&id=<?= $teacher['id'] ?>" class="btn btn-secondary btn-sm" title="تعديل">✏️</a>
                                
                                <?php if (!$teacher['user_id']): ?>
                                <a href="teacher_workflow.php?teacher_id=<?= $teacher['id'] ?>&step=assignments" 
                                   class="btn btn-success btn-sm" 
                                   title="التسلسل الإجباري: تعيين المواد ثم إنشاء الحساب">🔄</a>
                                <?php else: ?>
                                <form action="controllers/teacher_handler.php" method="POST" style="display: inline;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?= $teacher['id'] ?>">
                                    <?php if ($teacher['account_status'] === 'active'): ?>
                                    <button type="submit" class="btn btn-warning btn-sm" title="تعطيل الحساب"
                                            onclick="return confirm('هل تريد تعطيل هذا الحساب؟')">🚫</button>
                                    <?php else: ?>
                                    <button type="submit" class="btn btn-success btn-sm" title="تفعيل الحساب"
                                            onclick="return confirm('هل تريد تفعيل هذا الحساب؟')">✅</button>
                                    <?php endif; ?>
                                </form>
                                <?php endif; ?>
                                
                                <button type="button" 
                                        class="btn btn-danger btn-sm"
                                        data-delete
                                        data-module="teachers"
                                        data-id="<?= $teacher['id'] ?>"
                                        data-delete-message="هل تريد حذف المعلم '<?= sanitize($teacher['full_name']) ?>'؟"
                                        title="حذف">🗑️</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Modal لعرض بيانات المعلم الكاملة -->
<div id="viewTeacherModal" class="teacher-modal" style="display: none;">
    <div class="teacher-modal-overlay" onclick="closeViewModal()"></div>
    <div class="teacher-modal-content" style="max-width: 1000px; max-height: 90vh; overflow-y: auto;">
        <div class="teacher-modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h3 style="color: white; margin: 0;">👁️ بيانات المعلم الكاملة</h3>
            <button type="button" class="teacher-modal-close" onclick="closeViewModal()" style="color: white;">&times;</button>
        </div>
        <div class="teacher-modal-body" style="padding: 0;">
            <!-- رأس البطاقة -->
            <div style="text-align: center; padding: 2rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <div id="view_teacher_placeholder" style="width: 100px; height: 100px; background: rgba(255,255,255,0.2); border-radius: 50%; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; font-size: 3rem;">👨‍🏫</div>
                <h2 id="view_teacher_name" style="margin: 0; color: white;"></h2>
                <p id="view_teacher_specialty" style="margin-top: 0.5rem; opacity: 0.9;"></p>
            </div>
            
            <!-- البيانات الشخصية الأساسية -->
            <div style="padding: 1.5rem; border-bottom: 1px solid #e9ecef;">
                <h4 style="color: #667eea; margin-bottom: 1rem; border-bottom: 2px solid #667eea; padding-bottom: 0.5rem;">📋 البيانات الشخصية الأساسية</h4>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                    <div><label style="font-size: 0.8rem; color: #666;">الاسم الرباعي واللقب</label><div id="view_full_name" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">محل الولادة</label><div id="view_birth_place" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">تاريخ الولادة</label><div id="view_birth_date" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">رقم الهاتف</label><div id="view_phone" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">اسم الام الثلاثي</label><div id="view_mother_name" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">فصيلة الدم</label><div id="view_blood_type" style="font-weight: 600;">-</div></div>
                </div>
            </div>
            
            <!-- الشهادة والتخصص -->
            <div style="padding: 1.5rem; border-bottom: 1px solid #e9ecef;">
                <h4 style="color: #10b981; margin-bottom: 1rem; border-bottom: 2px solid #10b981; padding-bottom: 0.5rem;">🎓 الشهادة والتخصص</h4>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                    <div><label style="font-size: 0.8rem; color: #666;">الشهادة</label><div id="view_certificate" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">الاختصاص</label><div id="view_specialization" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">اسم المعهد او الكلية</label><div id="view_institute_name" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">سنة التخرج</label><div id="view_graduation_year" style="font-weight: 600;">-</div></div>
                </div>
            </div>
            
            <!-- بيانات التعيين والوظيفة -->
            <div style="padding: 1.5rem; border-bottom: 1px solid #e9ecef;">
                <h4 style="color: #f59e0b; margin-bottom: 1rem; border-bottom: 2px solid #f59e0b; padding-bottom: 0.5rem;">💼 بيانات التعيين والوظيفة</h4>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                    <div><label style="font-size: 0.8rem; color: #666;">تاريخ التعيين</label><div id="view_hire_date" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">تاريخ المباشرة بالوظيفة لاول مرة</label><div id="view_first_job_date" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">تاريخ المباشرة بالمدرسة الحالية</label><div id="view_current_school_date" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">رقم الامر الاداري بالتعين</label><div id="view_hire_order_number" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">تاريخ الامر الاداري بالتعين</label><div id="view_hire_order_date" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">رقم الامر الاداري بالمباشرة في المدرسة</label><div id="view_school_order_number" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">رقم الامر الاداري بالنقل من المدرسة</label><div id="view_transfer_order_number" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">تاريخ الانفكاك (النقل)</label><div id="view_transfer_date" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">الدرجة الوظيفية</label><div id="view_job_grade" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">المرحلة في السلم الوظيفي</label><div id="view_career_stage" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">تاريخ الانقطاع</label><div id="view_interruption_date" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">تاريخ العودة</label><div id="view_return_date" style="font-weight: 600;">-</div></div>
                </div>
                <div style="margin-top: 1rem;">
                    <label style="font-size: 0.8rem; color: #666;">سبب الانقطاع</label>
                    <div id="view_interruption_reason" style="font-weight: 600; background: #fef3c7; padding: 0.5rem; border-radius: 4px; margin-top: 0.25rem;">-</div>
                </div>
            </div>
            
            <!-- الوثائق والهويات -->
            <div style="padding: 1.5rem; border-bottom: 1px solid #e9ecef;">
                <h4 style="color: #8b5cf6; margin-bottom: 1rem; border-bottom: 2px solid #8b5cf6; padding-bottom: 0.5rem;">🪪 الوثائق والهويات</h4>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                    <div><label style="font-size: 0.8rem; color: #666;">رقم البطاقة الوطنية</label><div id="view_national_id" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">تاريخ البطاقة الوطنية</label><div id="view_national_id_date" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">رقم السجل</label><div id="view_record_number" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">رقم الصفحة</label><div id="view_page_number" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">رقم شهادة الجنسية العراقية</label><div id="view_nationality_cert_number" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">تاريخ شهادة الجنسية</label><div id="view_nationality_cert_date" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">رقم المحفظة لشهادة الجنسية العراقية</label><div id="view_nationality_folder_number" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">بطاقة السكن (مكتب المعلومات)</label><div id="view_residence_card" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">رقم الاستمارة</label><div id="view_form_number" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">رقم البطاقة التموينية</label><div id="view_ration_card_number" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">رقم الوكيل واسمه</label><div id="view_agent_info" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">اسم مركز التموين</label><div id="view_ration_center" style="font-weight: 600;">-</div></div>
                </div>
            </div>
            
            <!-- الحالة الاجتماعية -->
            <div style="padding: 1.5rem; border-bottom: 1px solid #e9ecef;">
                <h4 style="color: #ec4899; margin-bottom: 1rem; border-bottom: 2px solid #ec4899; padding-bottom: 0.5rem;">💍 الحالة الاجتماعية</h4>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                    <div><label style="font-size: 0.8rem; color: #666;">الحالة الزوجية</label><div id="view_marital_status" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">تاريخ الزواج</label><div id="view_marriage_date" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">رقم وتاريخ عقد الزواج</label><div id="view_marriage_contract_info" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">اسم الزوج او الزوجة</label><div id="view_spouse_name" style="font-weight: 600;">-</div></div>
                    <div style="grid-column: span 2;"><label style="font-size: 0.8rem; color: #666;">العمل الوظيفي للزوج او الزوجة</label><div id="view_spouse_job" style="font-weight: 600;">-</div></div>
                </div>
            </div>
            
            <!-- بيانات التقاعد -->
            <div style="padding: 1.5rem; border-bottom: 1px solid #e9ecef;">
                <h4 style="color: #0891b2; margin-bottom: 1rem; border-bottom: 2px solid #0891b2; padding-bottom: 0.5rem;">🏖️ بيانات التقاعد</h4>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                    <div><label style="font-size: 0.8rem; color: #666;">رقم الامر الاداري بالاحالة على التقاعد</label><div id="view_retirement_order_number" style="font-weight: 600;">-</div></div>
                    <div><label style="font-size: 0.8rem; color: #666;">تاريخ الانفكاك من المدرسة للتقاعد</label><div id="view_retirement_date" style="font-weight: 600;">-</div></div>
                </div>
            </div>
            
            <!-- معلومات إضافية -->
            <div style="padding: 1.5rem;">
                <h4 style="color: #667eea; margin-bottom: 1rem; border-bottom: 2px solid #667eea; padding-bottom: 0.5rem;">📝 معلومات إضافية</h4>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                    <div>
                        <label style="font-size: 0.8rem; color: #666;">الدورات التي شارك فيها</label>
                        <div id="view_courses" style="font-weight: 600; background: #f0fdf4; padding: 0.5rem; border-radius: 4px; margin-top: 0.25rem; min-height: 50px;">-</div>
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; color: #666;">ملاحظات</label>
                        <div id="view_notes" style="font-weight: 600; background: #fef3c7; padding: 0.5rem; border-radius: 4px; margin-top: 0.25rem; min-height: 50px;">-</div>
                    </div>
                </div>
            </div>
            
            <!-- المسؤول عن كتابة البيانات -->
            <div style="padding: 1.5rem;">
                <h4 style="color: #4b5563; margin-bottom: 1rem; border-bottom: 2px solid #9ca3af; padding-bottom: 0.5rem;">✍️ المسؤول عن كتابة البيانات</h4>
                <div id="view_data_writers_container"></div>
            </div>
        </div>
        <div class="teacher-modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeViewModal()">إغلاق</button>
        </div>
    </div>
</div>

<style>
.teacher-modal {
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

.teacher-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
}

.teacher-modal-content {
    position: relative;
    background: var(--bg-primary);
    border-radius: var(--radius-md);
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    width: 90%;
    animation: teacherModalSlideIn 0.3s ease;
}

@keyframes teacherModalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.teacher-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
}

.teacher-modal-header h3 {
    margin: 0;
}

.teacher-modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-secondary);
    padding: 0;
    line-height: 1;
}

.teacher-modal-close:hover {
    color: var(--danger);
}

.teacher-modal-body {
    padding: 1.5rem;
}

.teacher-modal-footer {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
}

@media (max-width: 768px) {
    .teacher-modal-content > div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script>
// عرض تفاصيل المعلم
function viewTeacher(teacherId) {
    fetch('/controllers/get_teacher.php?id=' + teacherId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showTeacherModal(data.teacher);
            } else {
                alert('خطأ في جلب البيانات: ' + (data.error || 'خطأ غير معروف'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ في الاتصال');
        });
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return dateStr;
    
    const day = date.getDate().toString();
    const month = (date.getMonth() + 1).toString();
    const year = date.getFullYear().toString();
    
    // تحويل إلى أرقام عربية
    const arabicNums = {'0':'٠','1':'١','2':'٢','3':'٣','4':'٤','5':'٥','6':'٦','7':'٧','8':'٨','9':'٩'};
    const toArabic = (str) => str.split('').map(c => arabicNums[c] || c).join('');
    
    return toArabic(day) + ' / ' + toArabic(month) + ' / ' + toArabic(year);
}

function showTeacherModal(teacher) {
    const modal = document.getElementById('viewTeacherModal');
    if (!modal) return;
    
    // البيانات الأساسية
    document.getElementById('view_teacher_name').textContent = teacher.full_name || '-';
    document.getElementById('view_teacher_specialty').textContent = teacher.specialization || 'معلم';
    document.getElementById('view_full_name').textContent = teacher.full_name || '-';
    document.getElementById('view_birth_place').textContent = teacher.birth_place || '-';
    document.getElementById('view_birth_date').textContent = formatDate(teacher.birth_date);
    document.getElementById('view_phone').textContent = teacher.phone || '-';
    document.getElementById('view_mother_name').textContent = teacher.mother_name || '-';
    document.getElementById('view_blood_type').textContent = teacher.blood_type || '-';
    
    // الشهادة والتخصص
    document.getElementById('view_certificate').textContent = teacher.certificate || '-';
    document.getElementById('view_specialization').textContent = teacher.specialization || '-';
    document.getElementById('view_institute_name').textContent = teacher.institute_name || '-';
    document.getElementById('view_graduation_year').textContent = teacher.graduation_year || '-';
    
    // بيانات التعيين والوظيفة
    document.getElementById('view_hire_date').textContent = formatDate(teacher.hire_date);
    document.getElementById('view_first_job_date').textContent = formatDate(teacher.first_job_date);
    document.getElementById('view_current_school_date').textContent = formatDate(teacher.current_school_date);
    document.getElementById('view_hire_order_number').textContent = teacher.hire_order_number || '-';
    document.getElementById('view_hire_order_date').textContent = formatDate(teacher.hire_order_date);
    document.getElementById('view_school_order_number').textContent = teacher.school_order_number || '-';
    document.getElementById('view_transfer_order_number').textContent = teacher.transfer_order_number || '-';
    document.getElementById('view_transfer_date').textContent = formatDate(teacher.transfer_date);
    document.getElementById('view_job_grade').textContent = teacher.job_grade || '-';
    document.getElementById('view_career_stage').textContent = teacher.career_stage || '-';
    document.getElementById('view_interruption_date').textContent = formatDate(teacher.interruption_date);
    document.getElementById('view_return_date').textContent = formatDate(teacher.return_date);
    document.getElementById('view_interruption_reason').textContent = teacher.interruption_reason || '-';
    
    // الوثائق والهويات
    document.getElementById('view_national_id').textContent = teacher.national_id || '-';
    document.getElementById('view_national_id_date').textContent = formatDate(teacher.national_id_date);
    document.getElementById('view_record_number').textContent = teacher.record_number || '-';
    document.getElementById('view_page_number').textContent = teacher.page_number || '-';
    document.getElementById('view_nationality_cert_number').textContent = teacher.nationality_cert_number || '-';
    document.getElementById('view_nationality_cert_date').textContent = formatDate(teacher.nationality_cert_date);
    document.getElementById('view_nationality_folder_number').textContent = teacher.nationality_folder_number || '-';
    document.getElementById('view_residence_card').textContent = teacher.residence_card || '-';
    document.getElementById('view_form_number').textContent = teacher.form_number || '-';
    document.getElementById('view_ration_card_number').textContent = teacher.ration_card_number || '-';
    document.getElementById('view_agent_info').textContent = teacher.agent_info || '-';
    document.getElementById('view_ration_center').textContent = teacher.ration_center || '-';
    
    // الحالة الاجتماعية
    document.getElementById('view_marital_status').textContent = teacher.marital_status || '-';
    document.getElementById('view_marriage_date').textContent = formatDate(teacher.marriage_date);
    document.getElementById('view_marriage_contract_info').textContent = teacher.marriage_contract_info || '-';
    document.getElementById('view_spouse_name').textContent = teacher.spouse_name || '-';
    document.getElementById('view_spouse_job').textContent = teacher.spouse_job || '-';
    
    // بيانات التقاعد
    document.getElementById('view_retirement_order_number').textContent = teacher.retirement_order_number || '-';
    document.getElementById('view_retirement_date').textContent = formatDate(teacher.retirement_date);
    
    // معلومات إضافية
    document.getElementById('view_courses').textContent = teacher.courses || '-';
    document.getElementById('view_notes').textContent = teacher.notes || '-';
    
    // المسؤول عن كتابة البيانات
    renderDataWriters(teacher.data_writers);
    
    modal.style.display = 'flex';
}

function renderDataWriters(data) {
    const container = document.getElementById('view_data_writers_container');
    if (!container) return;
    
    if (!data || data === 'null' || data === '') {
        container.innerHTML = '<p style="color: #9ca3af; font-style: italic;">لا توجد بيانات مسجلة</p>';
        return;
    }
    
    try {
        const records = JSON.parse(data);
        if (!Array.isArray(records) || records.length === 0) {
            container.innerHTML = '<p style="color: #9ca3af; font-style: italic;">لا توجد بيانات مسجلة</p>';
            return;
        }
        
        let html = `
            <div style="overflow-x: auto; border: 1px solid #d1d5db; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <table style="width: 100%; border-collapse: collapse; min-width: 500px;">
                    <thead style="background: linear-gradient(135deg, #374151 0%, #1f2937 100%);">
                        <tr>
                            <th style="color: white; padding: 14px 10px; text-align: center; font-weight: 600; border-left: 1px solid rgba(255,255,255,0.15);">#</th>
                            <th style="color: white; padding: 14px 10px; text-align: center; font-weight: 600; border-left: 1px solid rgba(255,255,255,0.15);">الأسم الثلاثي</th>
                            <th style="color: white; padding: 14px 10px; text-align: center; font-weight: 600; border-left: 1px solid rgba(255,255,255,0.15);">المدرسة / المعهد / الجامعة</th>
                            <th style="color: white; padding: 14px 10px; text-align: center; font-weight: 600; border-left: 1px solid rgba(255,255,255,0.15);">التوقيع</th>
                            <th style="color: white; padding: 14px 10px; text-align: center; font-weight: 600;">السنة الدراسية</th>
                        </tr>
                    </thead>
                    <tbody>`;
        
        records.forEach((record, index) => {
            const bgColor = index % 2 === 0 ? '#f9fafb' : '#ffffff';
            html += `
                <tr style="background: ${bgColor}; border-bottom: 1px solid #e5e7eb;">
                    <td style="padding: 12px 10px; text-align: center; font-weight: 700; color: #4b5563; border-left: 1px solid #e5e7eb;">${index + 1}</td>
                    <td style="padding: 12px 10px; text-align: center; font-weight: 600; color: #374151; border-left: 1px solid #e5e7eb;">${record.writer_name || '-'}</td>
                    <td style="padding: 12px 10px; text-align: center; color: #6b7280; border-left: 1px solid #e5e7eb;">${record.institution || '-'}</td>
                    <td style="padding: 12px 10px; text-align: center; color: #6b7280; border-left: 1px solid #e5e7eb;">${record.signature || '-'}</td>
                    <td style="padding: 12px 10px; text-align: center; font-weight: 600; color: #4b5563;">${record.academic_year || '-'}</td>
                </tr>`;
        });
        
        html += '</tbody></table></div>';
        container.innerHTML = html;
        
    } catch (e) {
        container.innerHTML = '<p style="color: #9ca3af; font-style: italic;">لا توجد بيانات مسجلة</p>';
    }
}

function closeViewModal() {
    document.getElementById('viewTeacherModal').style.display = 'none';
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeViewModal();
        closeAccountModal();
    }
});
</script>

<!-- Modal إنشاء حساب -->
<div id="accountModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: var(--bg-primary); padding: 2rem; border-radius: var(--radius-lg); width: 90%; max-width: 400px;">
        <h3 style="margin-bottom: 1rem;">🔑 إنشاء حساب للمعلم</h3>
        <p id="teacherNameDisplay" style="color: var(--text-secondary); margin-bottom: 1rem;"></p>
        <form action="controllers/teacher_handler.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create_account">
            <input type="hidden" name="id" id="modalTeacherId">
            
            <div class="form-group">
                <label>اسم المستخدم *</label>
                <input type="text" name="username" id="modalUsername" class="form-control" required
                       pattern="[a-zA-Z0-9_]+" placeholder="مثال: teacher_ahmed">
            </div>
            
            <div class="form-group">
                <label>كلمة المرور *</label>
                <input type="password" name="password" class="form-control" required
                       minlength="6" placeholder="6 أحرف على الأقل">
            </div>
            
            <div class="form-group">
                <label>الصلاحية *</label>
                <select name="role" class="form-control" required>
                    <option value="teacher">👨‍🏫 معلم</option>
                    <option value="assistant">👔 معاون</option>
                    <option value="admin">👨‍💼 مدير</option>
                </select>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">✅ إنشاء الحساب</button>
                <button type="button" class="btn btn-secondary" onclick="closeAccountModal()">إلغاء</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAccountModal(teacherId, teacherName) {
    document.getElementById('modalTeacherId').value = teacherId;
    document.getElementById('teacherNameDisplay').textContent = 'المعلم: ' + teacherName;
    document.getElementById('modalUsername').value = '';
    document.getElementById('accountModal').style.display = 'flex';
}

function closeAccountModal() {
    document.getElementById('accountModal').style.display = 'none';
}

// ═══════════════════════════════════════════════════════════════
// 🔍 دالة البحث والفلترة في جدول المعلمين
// ═══════════════════════════════════════════════════════════════
function filterTeachers() {
    const input = document.getElementById('teacherSearch');
    const accountFilter = document.getElementById('accountFilter');
    const filter = input ? input.value.toLowerCase().trim() : '';
    const accountValue = accountFilter ? accountFilter.value : '';
    const table = document.querySelector('.table-responsive table');
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const id = row.querySelector('code')?.textContent || '';
        const hasAccount = row.querySelector('.badge-success') !== null;
        
        let matchesSearch = !filter || text.includes(filter) || id.includes(filter);
        let matchesAccount = true;
        
        if (accountValue === 'has-account') {
            matchesAccount = hasAccount;
        } else if (accountValue === 'no-account') {
            matchesAccount = !hasAccount;
        }
        
        if (matchesSearch && matchesAccount) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // عرض عدد النتائج
    const countSpan = document.getElementById('teacherSearchCount');
    if (countSpan) {
        if (filter || accountValue) {
            countSpan.textContent = '📊 ' + visibleCount + ' نتيجة';
        } else {
            countSpan.textContent = '';
        }
    }
    
    // تمييز حقل البحث
    if (input && filter) {
        input.style.borderColor = 'var(--primary)';
        input.style.boxShadow = '0 0 0 3px rgba(102, 126, 234, 0.2)';
    } else if (input) {
        input.style.borderColor = '';
        input.style.boxShadow = '';
    }
}

function resetTeacherFilters() {
    document.getElementById('teacherSearch').value = '';
    document.getElementById('accountFilter').value = '';
    filterTeachers();
}
</script>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
