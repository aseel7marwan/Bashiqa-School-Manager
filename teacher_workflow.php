<?php
/**
 * التسلسل الإجباري للمعلمين - Enforced Teacher Workflow
 * خطوات إلزامية ومتتابعة: إضافة المعلم -> تعيين المواد/الصفوف/الشعب -> إنشاء الحساب
 * 
 * @package SchoolManager
 * @access  مدير المدرسة فقط
 * @security تسلسل إجباري لضمان النزاهة
 */

$pageTitle = 'التسلسل الإجباري للمعلم';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/models/Teacher.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/TeacherAssignment.php';
require_once __DIR__ . '/models/Subject.php';

requireLogin();

// صلاحية للمدير والمعاون
if (!canManageSystem()) {
    alert('⛔ هذه الصفحة متاحة للمدير والمعاون فقط', 'error');
    redirect('/dashboard.php');
}

$teacherModel = new Teacher();
$userModel = new User();
$assignmentModel = new TeacherAssignment();

// الحصول على معرف المعلم والخطوة الحالية
$teacherId = (int)($_GET['teacher_id'] ?? 0);
$step = $_GET['step'] ?? 'assignments';

if (!$teacherId) {
    alert('معرف المعلم مطلوب', 'error');
    redirect('/teachers.php');
}

$teacher = $teacherModel->findById($teacherId);
if (!$teacher) {
    alert('المعلم غير موجود', 'error');
    redirect('/teachers.php');
}

// التحقق من حالة التسلسل
$hasAccount = ($teacher['user_id'] > 0);

// جلب التعيينات المؤقتة (مرتبطة بـ teacher_db_id) أو الدائمة (مرتبطة بـ user_id)
$hasAssignments = false;
$assignments = [];

// أولاً: التحقق من التعيينات المؤقتة
$tempAssignments = $assignmentModel->getByTeacherDbId($teacherId);
if (!empty($tempAssignments)) {
    $assignments = $tempAssignments;
    $hasAssignments = true;
} elseif ($hasAccount) {
    // ثانياً: التحقق من التعيينات الدائمة
    $assignments = $assignmentModel->getByTeacher($teacher['user_id']);
    $hasAssignments = !empty($assignments);
}

// ═══════════════════════════════════════════════════════════════
// التسلسل الصحيح: إضافة المعلم (تم) -> تعيين المواد -> إنشاء الحساب
// ═══════════════════════════════════════════════════════════════
function getCurrentStep($hasAssignments, $hasAccount) {
    if (!$hasAssignments) return 'assignments';
    if (!$hasAccount) return 'account';
    return 'complete';
}

$currentStep = getCurrentStep($hasAssignments, $hasAccount);

// التحقق من أن المستخدم يحاول الوصول لخطوة صحيحة
// لا يُسمح بإنشاء الحساب إلا بعد اكتمال التعيينات
if ($step === 'account' && !$hasAssignments) {
    alert('⚠️ يجب تعيين المواد والصفوف للمعلم أولاً قبل إنشاء الحساب', 'warning');
    $step = 'assignments';
}

require_once __DIR__ . '/views/components/header.php';
?>

<style>
/* أنماط شريط التقدم */
.workflow-progress {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
    border-radius: 16px;
    border: 1px solid var(--border-color);
}

.workflow-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    flex: 1;
    max-width: 200px;
}

.step-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 700;
    transition: all 0.3s ease;
    z-index: 2;
}

.step-icon.pending {
    background: var(--bg-secondary);
    border: 3px solid var(--border-color);
    color: var(--text-muted);
}

.step-icon.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: 3px solid transparent;
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    animation: pulse 2s infinite;
}

.step-icon.completed {
    background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
    border: 3px solid transparent;
    color: white;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.step-label {
    margin-top: 0.75rem;
    font-weight: 600;
    font-size: 0.9rem;
    text-align: center;
    color: var(--text-secondary);
}

.step-connector {
    flex: 1;
    height: 4px;
    background: var(--border-color);
    margin: 0 -10px;
    margin-bottom: 30px;
}

.step-connector.completed {
    background: linear-gradient(90deg, #22c55e 0%, #16a34a 100%);
}

/* أنماط بطاقة الخطوة */
.workflow-card {
    background: var(--bg-primary);
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    overflow: hidden;
    border: 1px solid var(--border-color);
}

.workflow-card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.workflow-card-header h2 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: white;
}

.workflow-card-body {
    padding: 2rem;
}

/* شبكة التعيينات */
.assignment-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

@media (max-width: 768px) {
    .assignment-grid {
        grid-template-columns: 1fr;
    }
}

.assignment-card {
    background: var(--bg-secondary);
    border-radius: 12px;
    padding: 1.25rem;
    border: 1px solid var(--border-color);
}

.assignment-card h4 {
    margin: 0 0 1rem;
    color: var(--text-primary);
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-height: 250px;
    overflow-y: auto;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem;
    border-radius: 8px;
    transition: background 0.2s;
}

.checkbox-item:hover {
    background: var(--bg-primary);
}

.checkbox-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.checkbox-item label {
    cursor: pointer;
    font-size: 0.9rem;
}

.checkbox-item.option-none {
    border-top: 1px dashed var(--border-color);
    margin-top: 0.5rem;
    padding-top: 0.75rem;
}

/* التعيينات الحالية */
.current-assignments {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border: 1px solid #86efac;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1.5rem;
}

.assignment-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: white;
    border: 1px solid #86efac;
    border-radius: 20px;
    padding: 0.5rem 1rem;
    margin: 0.25rem;
    font-size: 0.85rem;
}

.assignment-badge .remove-btn {
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    cursor: pointer;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* رسالة النجاح */
.success-message {
    text-align: center;
    padding: 3rem;
}

.success-icon {
    font-size: 5rem;
    margin-bottom: 1rem;
}

.success-message h3 {
    color: var(--success);
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

/* زر الخطوة التالية */
.next-step-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 1rem 2rem;
    font-size: 1.1rem;
    font-weight: 600;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    text-decoration: none;
}

.next-step-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    color: white;
}

.next-step-btn:disabled {
    background: var(--bg-secondary);
    color: var(--text-muted);
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}
</style>

<div class="page-header d-flex justify-between align-center flex-wrap gap-2">
    <div>
        <h1>🔄 التسلسل الإجباري للمعلم</h1>
        <p>المعلم: <strong><?= sanitize($teacher['full_name']) ?></strong> 
           <code style="background: var(--bg-secondary); padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem; margin-right: 0.5rem;">#<?= $teacherId ?></code>
        </p>
    </div>
    <a href="teachers.php" class="btn btn-secondary">← العودة للقائمة</a>
</div>

<!-- شريط التقدم -->
<div class="workflow-progress">
    <!-- الخطوة 1: إضافة المعلم (مكتملة دائماً) -->
    <div class="workflow-step">
        <div class="step-icon completed">✓</div>
        <div class="step-label">إضافة المعلم</div>
    </div>
    
    <div class="step-connector completed"></div>
    
    <!-- الخطوة 2: تعيين المواد والصفوف -->
    <div class="workflow-step">
        <div class="step-icon <?= $hasAssignments ? 'completed' : ($step === 'assignments' ? 'active' : 'pending') ?>">
            <?= $hasAssignments ? '✓' : '2' ?>
        </div>
        <div class="step-label">تعيين المواد والصفوف</div>
    </div>
    
    <div class="step-connector <?= $hasAssignments ? 'completed' : '' ?>"></div>
    
    <!-- الخطوة 3: إنشاء الحساب -->
    <div class="workflow-step">
        <div class="step-icon <?= $hasAccount ? 'completed' : ($step === 'account' ? 'active' : 'pending') ?>">
            <?= $hasAccount ? '✓' : '3' ?>
        </div>
        <div class="step-label">إنشاء الحساب</div>
    </div>
</div>

<?php if ($step === 'assignments'): ?>
<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- الخطوة 2: تعيين المواد والصفوف والشُعب -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="workflow-card">
    <div class="workflow-card-header">
        <h2>� الخطوة الثانية: تعيين المواد والصفوف والشُعب</h2>
        <span class="badge badge-primary">إلزامي</span>
    </div>
    <div class="workflow-card-body">
        
        <?php if (!$hasAssignments): ?>
        <div class="alert alert-info mb-3">
            ℹ️ <strong>معلومات:</strong> عيّن المواد والصفوف والشُعب للمعلم. يمكن اختيار أكثر من مادة وصف وشعبة.
            <br><strong>ملاحظة:</strong> لا يُسمح بإنشاء حساب للمعلم إلا بعد إكمال هذه الخطوة.
        </div>
        <?php else: ?>
        <div class="alert alert-success mb-3">
            ✅ <strong>ممتاز!</strong> تم تعيين المواد والصفوف. يمكنك إضافة المزيد أو الانتقال لإنشاء الحساب.
        </div>
        <?php endif; ?>

        <?php if (!empty($assignments)): ?>
        <div class="current-assignments" id="currentAssignmentsContainer">
            <h4 style="margin-bottom: 0.75rem; color: var(--text-secondary);">📋 التعيينات الحالية:</h4>
            <div id="assignmentsList">
                <?php foreach ($assignments as $a): ?>
                <span class="assignment-badge" id="assignment_<?= $a['id'] ?>">
                    <?= sanitize($a['subject_name']) ?> - <?= CLASSES[$a['class_id']] ?? $a['class_id'] ?> (<?= sanitize($a['section']) ?>)
                    <button type="button" class="remove-btn" 
                            onclick="deleteAssignment(<?= $a['id'] ?>, <?= $teacherId ?>)" 
                            title="إزالة">×</button>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <form action="controllers/teacher_assignment_handler.php" method="POST" id="assignmentForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="workflow_add">
            <input type="hidden" name="teacher_db_id" value="<?= $teacherId ?>">
            <input type="hidden" name="redirect_to" value="teacher_workflow.php?teacher_id=<?= $teacherId ?>&step=assignments">

            <div class="assignment-grid">
                <!-- اختيار الصفوف -->
                <div class="assignment-card">
                    <h4>📖 الصفوف</h4>
                    <div class="checkbox-group">
                        <?php foreach (CLASSES as $id => $name): ?>
                        <div class="checkbox-item">
                            <input type="checkbox" name="classes[]" value="<?= $id ?>" id="class_<?= $id ?>" onchange="updateSubjects()">
                            <label for="class_<?= $id ?>"><?= $name ?></label>
                        </div>
                        <?php endforeach; ?>
                        <div class="checkbox-item option-none">
                            <input type="checkbox" name="classes[]" value="none" id="class_none">
                            <label for="class_none">لا يوجد / أخرى</label>
                        </div>
                    </div>
                </div>

                <!-- اختيار الشُعب -->
                <div class="assignment-card">
                    <h4>🏢 الشُعب</h4>
                    <div class="checkbox-group">
                        <?php foreach (SECTIONS as $sec): ?>
                        <div class="checkbox-item">
                            <input type="checkbox" name="sections[]" value="<?= $sec ?>" id="section_<?= $sec ?>">
                            <label for="section_<?= $sec ?>">شعبة <?= $sec ?></label>
                        </div>
                        <?php endforeach; ?>
                        <div class="checkbox-item option-none">
                            <input type="checkbox" name="sections[]" value="none" id="section_none">
                            <label for="section_none">لا يوجد / أخرى</label>
                        </div>
                    </div>
                </div>

                <!-- اختيار المواد -->
                <div class="assignment-card">
                    <h4>📝 المواد</h4>
                    <div class="checkbox-group" id="subjectsContainer">
                        <p style="color: var(--text-muted); text-align: center; padding: 1rem;">
                            اختر صفاً واحداً على الأقل لعرض المواد المتاحة
                        </p>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-end gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    ➕ إضافة التعيينات
                </button>
            </div>
        </form>

        <?php if ($hasAssignments): ?>
        <hr style="margin: 2rem 0;">
        <div class="d-flex justify-center">
            <a href="?teacher_id=<?= $teacherId ?>&step=account" class="next-step-btn">
                الانتقال للخطوة التالية: إنشاء الحساب →
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($step === 'account'): ?>
<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- الخطوة 3: إنشاء حساب المستخدم -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="workflow-card">
    <div class="workflow-card-header">
        <h2>🔑 الخطوة الثالثة: إنشاء حساب المستخدم</h2>
        <span class="badge badge-success">الخطوة الأخيرة</span>
    </div>
    <div class="workflow-card-body">
        
        <?php if ($hasAccount): ?>
        <!-- الحساب موجود - اكتمل التسلسل -->
        <div class="success-message">
            <div class="success-icon">🎉</div>
            <h3>اكتمل التسلسل الإجباري بنجاح!</h3>
            <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                المعلم <strong><?= sanitize($teacher['full_name']) ?></strong> جاهز للعمل ويمكنه رصد الدرجات الآن.
            </p>
            <p style="margin-bottom: 1.5rem;">
                <span class="badge badge-info" style="font-size: 1rem; padding: 0.75rem 1.5rem;">
                    اسم المستخدم: <?= sanitize($teacher['username']) ?>
                </span>
            </p>
            <div class="d-flex gap-2 justify-center">
                <a href="teachers.php" class="btn btn-primary">✓ العودة لقائمة المعلمين</a>
                <a href="teacher_assignments.php?teacher_id=<?= $teacher['user_id'] ?>" class="btn btn-secondary">📚 إدارة التعيينات</a>
            </div>
        </div>
        
        <?php else: ?>
        <!-- نموذج إنشاء الحساب -->
        <div class="alert alert-info mb-3">
            ℹ️ <strong>معلومات:</strong> هذه هي الخطوة الأخيرة. بعد إنشاء الحساب سيتمكن المعلم من تسجيل الدخول ورصد الدرجات.
        </div>
        
        <!-- ملخص التعيينات -->
        <div class="current-assignments" style="margin-bottom: 1.5rem;">
            <h4 style="margin-bottom: 0.75rem; color: var(--text-secondary);">📋 التعيينات المكتملة:</h4>
            <div>
                <?php foreach ($assignments as $a): ?>
                <span class="assignment-badge">
                    <?= sanitize($a['subject_name']) ?> - <?= CLASSES[$a['class_id']] ?? $a['class_id'] ?> (<?= sanitize($a['section']) ?>)
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        
        <form action="controllers/teacher_handler.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create_account_workflow">
            <input type="hidden" name="id" value="<?= $teacherId ?>">
            
            <div class="grid grid-2" style="gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label><span class="required">*</span> اسم المستخدم (للدخول)</label>
                    <input type="text" name="username" class="form-control" required
                           placeholder="مثال: teacher1" pattern="[a-zA-Z0-9_]+" 
                           title="يجب أن يحتوي على حروف إنجليزية وأرقام فقط">
                    <small style="color: var(--text-muted);">حروف إنجليزية وأرقام فقط</small>
                </div>
                
                <div class="form-group">
                    <label><span class="required">*</span> كلمة المرور</label>
                    <input type="password" name="password" class="form-control" required
                           minlength="6" placeholder="6 أحرف على الأقل">
                </div>
                
                <div class="form-group">
                    <label><span class="required">*</span> الصلاحية</label>
                    <select name="role" class="form-control" required>
                        <option value="teacher" selected>معلم</option>
                        <option value="assistant">معاون</option>
                        <option value="admin">مدير</option>
                    </select>
                </div>
            </div>
            
            <div class="d-flex justify-between align-center gap-2">
                <a href="?teacher_id=<?= $teacherId ?>&step=assignments" class="btn btn-secondary">
                    ← العودة لتعديل التعيينات
                </a>
                <button type="submit" class="next-step-btn">
                    ✓ إنشاء الحساب وإكمال التسلسل
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- JavaScript لتحديث المواد ديناميكياً -->
<script>
// المواد حسب الصف
const subjectsByClass = {
    <?php for ($i = 1; $i <= 6; $i++): ?>
    <?= $i ?>: <?= json_encode(Subject::getSubjectsByClass($i), JSON_UNESCAPED_UNICODE) ?>,
    <?php endfor; ?>
};

function updateSubjects() {
    const container = document.getElementById('subjectsContainer');
    if (!container) return;
    
    const selectedClasses = Array.from(document.querySelectorAll('input[name="classes[]"]:checked'))
        .map(cb => cb.value)
        .filter(v => v !== 'none');
    
    if (selectedClasses.length === 0) {
        container.innerHTML = '<p style="color: var(--text-muted); text-align: center; padding: 1rem;">اختر صفاً واحداً على الأقل لعرض المواد المتاحة</p>';
        return;
    }
    
    // جمع كل المواد من الصفوف المختارة
    const allSubjects = new Set();
    selectedClasses.forEach(classId => {
        if (subjectsByClass[classId]) {
            subjectsByClass[classId].forEach(subject => allSubjects.add(subject));
        }
    });
    
    let html = '';
    allSubjects.forEach(subject => {
        const safeId = subject.replace(/\s+/g, '_');
        html += `
            <div class="checkbox-item">
                <input type="checkbox" name="subjects[]" value="${subject}" id="subject_${safeId}">
                <label for="subject_${safeId}">${subject}</label>
            </div>
        `;
    });
    
    html += `
        <div class="checkbox-item option-none">
            <input type="checkbox" name="subjects[]" value="none" id="subject_none">
            <label for="subject_none">لا يوجد / أخرى</label>
        </div>
    `;
    
    container.innerHTML = html;
}

// ═══════════════════════════════════════════════════════════════
// حذف التعيين بـ AJAX بدون إعادة تحميل الصفحة
// ═══════════════════════════════════════════════════════════════
function deleteAssignment(assignmentId, teacherId) {
    if (!confirm('هل تريد إزالة هذا التعيين؟')) {
        return;
    }
    
    const badge = document.getElementById('assignment_' + assignmentId);
    if (badge) {
        // تعطيل الزر وإظهار حالة التحميل
        const btn = badge.querySelector('.remove-btn');
        if (btn) {
            btn.innerHTML = '⏳';
            btn.disabled = true;
        }
    }
    
    // إرسال طلب AJAX
    fetch('<?= getBaseUrl() ?>api/delete_assignment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            assignment_id: assignmentId,
            teacher_db_id: teacherId,
            csrf_token: '<?= generateCSRFToken() ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // إزالة العنصر بأنيميشن سلس
            if (badge) {
                badge.style.transition = 'all 0.3s ease';
                badge.style.transform = 'scale(0)';
                badge.style.opacity = '0';
                setTimeout(() => {
                    badge.remove();
                    
                    // التحقق إذا كانت القائمة فارغة
                    const list = document.getElementById('assignmentsList');
                    if (list && list.children.length === 0) {
                        const container = document.getElementById('currentAssignmentsContainer');
                        if (container) {
                            container.innerHTML = '<p style="text-align: center; color: var(--text-muted); padding: 1rem;">✓ تم حذف جميع التعيينات</p>';
                        }
                    }
                }, 300);
            }
            
            // عرض تنبيه نجاح
            if (typeof notifySuccess === 'function') {
                notifySuccess('تم حذف التعيين بنجاح');
            }
        } else {
            // إعادة الزر للحالة الأصلية
            if (badge) {
                const btn = badge.querySelector('.remove-btn');
                if (btn) {
                    btn.innerHTML = '×';
                    btn.disabled = false;
                }
            }
            
            // عرض رسالة الخطأ
            if (typeof notifyError === 'function') {
                notifyError(data.message || 'فشل في حذف التعيين');
            } else {
                alert(data.message || 'فشل في حذف التعيين');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // إعادة الزر للحالة الأصلية
        if (badge) {
            const btn = badge.querySelector('.remove-btn');
            if (btn) {
                btn.innerHTML = '×';
                btn.disabled = false;
            }
        }
        if (typeof notifyError === 'function') {
            notifyError('حدث خطأ في الاتصال');
        }
    });
}
</script>

<!-- تنبيه مهم -->
<div class="alert alert-warning mt-3" style="background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%); border: 1px solid #ffc107; border-radius: 12px; padding: 1rem;">
    <strong>⚠️ تذكير بالقيود الأمنية:</strong>
    <ul style="margin: 0.5rem 0 0; padding-right: 1.5rem;">
        <li>لا يمكن إنشاء حساب للمعلم بدون تعيين المواد والصفوف أولاً</li>
        <li>المعلم يرصد الدرجات فقط للمواد والصفوف والشُعب المعينة له</li>
        <li>خيار "لا يوجد / أخرى" لا يمنح أي صلاحيات لرصد الدرجات</li>
    </ul>
</div>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
