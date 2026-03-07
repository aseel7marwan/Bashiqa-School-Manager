<?php
/**
 * تعيين المعلمين للمواد والصفوف - Teacher Assignments
 * تحديد المواد والصفوف والشعب لكل معلم
 * 
 * @package SchoolManager
 * @access  مدير المدرسة فقط
 * @security صلاحية حصرية للمدير
 */

$pageTitle = 'تعيين المعلمين';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/TeacherAssignment.php';
require_once __DIR__ . '/models/Subject.php';

requireLogin();

// صلاحية حصرية للمدير
if (!canManageSystem()) {
    alert('هذه الصفحة متاحة للمدير والمعاون فقط', 'error');
    redirect('/dashboard.php');
}

$userModel = new User();
$assignmentModel = new TeacherAssignment();

// الحصول على المعلمين فقط
$teachers = $userModel->getTeachers();

// معالجة الإجراءات
$selectedTeacher = null;
$teacherAssignments = [];

if (isset($_GET['teacher_id'])) {
    $teacherId = (int)$_GET['teacher_id'];
    $selectedTeacher = $userModel->findById($teacherId);
    if ($selectedTeacher && $selectedTeacher['role'] === 'teacher') {
        $teacherAssignments = $assignmentModel->getByTeacher($teacherId);
    }
}

require_once __DIR__ . '/views/components/header.php';
?>

<div class="page-header d-flex justify-between align-center flex-wrap gap-2">
    <div>
        <h1><?= __('📚 توزيع المواد والصفوف') ?></h1>
        <p><?= __('تحديد المواد والصفوف والشعب لكل معلم لرصد الدرجات') ?></p>
    </div>
</div>

<style>
/* تنسيق خاص بصفحة التعيينات */
.assignments-container {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 1.5rem;
}
@media (max-width: 992px) {
    .assignments-container {
        grid-template-columns: 1fr;
    }
}

/* قائمة المعلمين */
.teacher-list-card {
    background: var(--bg-primary);
    border-radius: 16px;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}
.teacher-list-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 1.25rem 1.5rem;
    color: white;
}
.teacher-list-header h3 {
    margin: 0;
    color: white;
    font-size: 1.1rem;
}
.teacher-list-body {
    padding: 1rem;
    max-height: 550px;
    overflow-y: auto;
}

/* عناصر المعلمين */
.teacher-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-radius: 12px;
    margin-bottom: 0.75rem;
    text-decoration: none;
    color: inherit;
    border: 2px solid transparent;
    background: var(--bg-secondary);
    transition: all 0.3s ease;
}
.teacher-card:hover {
    border-color: var(--primary);
    transform: translateX(-5px);
    box-shadow: var(--shadow-md);
}
.teacher-card.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-color: transparent;
}
.teacher-card .avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: rgba(102, 126, 234, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.teacher-card.active .avatar {
    background: rgba(255, 255, 255, 0.2);
}
.teacher-card .info {
    flex: 1;
}
.teacher-card .name {
    font-weight: 700;
    font-size: 0.95rem;
    margin-bottom: 0.25rem;
}
.teacher-card .status {
    font-size: 0.8rem;
    opacity: 0.8;
}
.teacher-card .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.5rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}
.teacher-card .status-badge.assigned {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}
.teacher-card .status-badge.not-assigned {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}
.teacher-card.active .status-badge {
    background: rgba(255, 255, 255, 0.2);
    color: white;
}

/* بطاقة التعيينات */
.assignments-card {
    background: var(--bg-primary);
    border-radius: 16px;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}
.assignments-header {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    padding: 1.25rem 1.5rem;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}
.assignments-header h3 {
    margin: 0;
    color: white;
    font-size: 1.1rem;
}
.assignments-body {
    padding: 1.5rem;
}

/* جدول التعيينات */
.assignment-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: 12px;
    margin-bottom: 0.75rem;
    border-right: 4px solid var(--primary);
    transition: all 0.2s;
}
.assignment-item:hover {
    transform: translateX(-3px);
    box-shadow: var(--shadow-sm);
}
.assignment-item .subject-icon {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
}
.assignment-item .details {
    flex: 1;
}
.assignment-item .subject-name {
    font-weight: 700;
    font-size: 1rem;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}
.assignment-item .class-info {
    font-size: 0.85rem;
    color: var(--text-secondary);
}
.assignment-item .class-info span {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    margin-left: 1rem;
}
.assignment-item .status-badge {
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}
.assignment-item .status-badge.can-grade {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}
.assignment-item .status-badge.view-only {
    background: rgba(107, 114, 128, 0.15);
    color: #6b7280;
}
.assignment-item .actions {
    display: flex;
    gap: 0.5rem;
}

/* حالة فارغة */
.empty-assignments {
    text-align: center;
    padding: 3rem 2rem;
}
.empty-assignments .icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}
.empty-assignments h3 {
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}
.empty-assignments p {
    color: var(--text-secondary);
    margin-bottom: 1rem;
}
.empty-assignments .warning {
    color: var(--danger);
    font-weight: 600;
    margin-bottom: 1.5rem;
}

/* اختيار معلم */
.select-teacher-state {
    text-align: center;
    padding: 4rem 2rem;
}
.select-teacher-state .icon {
    font-size: 5rem;
    margin-bottom: 1rem;
    animation: bounce 2s infinite;
}
@keyframes bounce {
    0%, 100% { transform: translateX(0); }
    50% { transform: translateX(-10px); }
}
</style>

<div class="assignments-container">
    <!-- قائمة المعلمين -->
    <div class="teacher-list-card fade-in">
        <div class="teacher-list-header">
            <h3>👨‍🏫 <?= __('قائمة المعلمين') ?></h3>
        </div>
        <div class="teacher-list-body">
            <?php if (empty($teachers)): ?>
            <div class="empty-state" style="padding: 2rem; text-align: center;">
                <p><?= __('لا يوجد معلمون مسجلون') ?></p>
                <a href="users.php?action=add" class="btn btn-primary btn-sm"><?= __('إضافة معلم') ?></a>
            </div>
            <?php else: ?>
                <?php foreach ($teachers as $teacher): ?>
                <?php 
                    $hasAssignments = $assignmentModel->hasAssignments($teacher['id']);
                    $isSelected = $selectedTeacher && $selectedTeacher['id'] == $teacher['id'];
                ?>
                <a href="?teacher_id=<?= $teacher['id'] ?>" 
                   class="teacher-card <?= $isSelected ? 'active' : '' ?>"
                   data-teacher-id="<?= $teacher['id'] ?>"
                   data-teacher-name="<?= sanitize($teacher['full_name']) ?>"
                   onclick="loadTeacherAssignments(event, this)">
                    <div class="avatar"><?= $hasAssignments ? '✅' : '👤' ?></div>
                    <div class="info">
                        <div class="name">
                            <?= sanitize($teacher['full_name']) ?>
                            <code style="font-size: 0.7rem; opacity: 0.7; margin-right: 0.3rem;">#<?= $teacher['id'] ?></code>
                        </div>
                        <div class="status">
                            <?php if ($hasAssignments): ?>
                            <span class="status-badge assigned">✓ <?= __('معيّن') ?></span>
                            <?php else: ?>
                            <span class="status-badge not-assigned">⚠ <?= __('غير معيّن') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- تفاصيل التعيينات -->
    <div class="assignments-card fade-in">
        <?php if ($selectedTeacher): ?>
        <div class="assignments-header">
            <h3>📋 <?= __('تعيينات:') ?> <?= sanitize($selectedTeacher['full_name']) ?> <code style="font-size: 0.8rem; margin-right: 0.5rem;">#<?= $selectedTeacher['id'] ?></code></h3>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-light btn-sm" onclick="showBulkModal()">
                    📦 <?= __('تعيين صف كامل') ?>
                </button>
                <button type="button" class="btn btn-light btn-sm" onclick="showAddModal()">
                    ➕ <?= __('إضافة تعيين') ?>
                </button>
            </div>
        </div>
        <div class="assignments-body">
            <?php if (empty($teacherAssignments)): ?>
            <div class="empty-assignments">
                <div class="icon">📭</div>
                <h3><?= __('لا توجد تعيينات') ?></h3>
                <p><?= __('هذا المعلم غير معيّن لأي مواد أو صفوف') ?></p>
                <p class="warning">⚠️ <?= __('لن يستطيع رصد أي درجات!') ?></p>
                <button type="button" class="btn btn-primary" onclick="showAddModal()">
                    ➕ <?= __('إضافة تعيين الآن') ?>
                </button>
            </div>
            <?php else: ?>
                <?php foreach ($teacherAssignments as $assignment): ?>
                <div class="assignment-item" id="assignment_<?= $assignment['id'] ?>">
                    <div class="subject-icon">📚</div>
                    <div class="details">
                        <div class="subject-name"><?= sanitize($assignment['subject_name']) ?></div>
                        <div class="class-info">
                            <span>🏫 <?= CLASSES[$assignment['class_id']] ?? $assignment['class_id'] ?></span>
                            <span>📍 شعبة <?= sanitize($assignment['section']) ?></span>
                            <span>📅 <?= formatArabicDate($assignment['assigned_at']) ?></span>
                        </div>
                    </div>
                    <?php if ($assignment['can_enter_grades']): ?>
                    <span class="status-badge can-grade">✓ <?= __('رصد الدرجات') ?></span>
                    <?php else: ?>
                    <span class="status-badge view-only">👁 <?= __('مشاهدة فقط') ?></span>
                    <?php endif; ?>
                    <div class="actions">
                        <button type="button" class="btn btn-danger btn-sm" 
                                onclick="deleteAssignmentAjax(<?= $assignment['id'] ?>, <?= $selectedTeacher['id'] ?>)"
                                title="حذف التعيين">🗑️</button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="assignments-body">
            <div class="select-teacher-state">
                <div class="icon">👈</div>
                <h3><?= __('اختر معلماً من القائمة') ?></h3>
                <p><?= __('حدد معلماً لعرض وإدارة تعييناته للمواد والصفوف') ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($selectedTeacher): ?>
<!-- مودال إضافة تعيين -->
<div id="addModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><?= __('➕ إضافة تعيين جديد') ?></h3>
            <button type="button" onclick="closeModal('addModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form action="controllers/teacher_assignment_handler.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="teacher_id" value="<?= $selectedTeacher['id'] ?>">
            
            <div class="modal-body">
                <div class="form-group">
                    <label><?= __('الصف *') ?></label>
                    <select name="class_id" id="classSelect" class="form-control" required onchange="loadSubjects()">
                        <option value=""><?= __('اختر الصف') ?></option>
                        <?php foreach (CLASSES as $id => $name): ?>
                        <option value="<?= $id ?>"><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><?= __('الشعبة *') ?></label>
                    <select name="section" class="form-control" required>
                        <option value=""><?= __('اختر الشعبة') ?></option>
                        <?php foreach (SECTIONS as $sec): ?>
                        <option value="<?= $sec ?>"><?= $sec ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><?= __('المادة *') ?></label>
                    <select name="subject_name" id="subjectSelect" class="form-control" required>
                        <option value=""><?= __('اختر الصف أولاً') ?></option>
                    </select>
                </div>
                </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')"><?= __('إلغاء') ?></button>
                <button type="submit" class="btn btn-primary"><?= __('💾 حفظ التعيين') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- مودال تعيين جميع المواد -->
<div id="bulkModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><?= __('📦 تعيين جميع مواد صف') ?></h3>
            <button type="button" onclick="closeModal('bulkModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form action="controllers/teacher_assignment_handler.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="bulk_add">
            <input type="hidden" name="teacher_id" value="<?= $selectedTeacher['id'] ?>">
            
            <div class="modal-body">
                <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                    <?= __('سيتم تعيين جميع مواد الصف المحدد للمعلم') ?>
                </p>
                
                <div class="form-group">
                    <label><?= __('الصف *') ?></label>
                    <select name="class_id" class="form-control" required>
                        <option value=""><?= __('اختر الصف') ?></option>
                        <?php foreach (CLASSES as $id => $name): ?>
                        <option value="<?= $id ?>"><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><?= __('الشعبة *') ?></label>
                    <select name="section" class="form-control" required>
                        <option value=""><?= __('اختر الشعبة') ?></option>
                        <?php foreach (SECTIONS as $sec): ?>
                        <option value="<?= $sec ?>"><?= $sec ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('bulkModal')"><?= __('إلغاء') ?></button>
                <button type="submit" class="btn btn-primary"><?= __('📦 تعيين جميع المواد') ?></button>
            </div>
        </form>
    </div>
</div>

<style>
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}
.modal-content {
    background: var(--bg-primary);
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    width: 90%;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
}
.modal-body {
    padding: 1.5rem;
}
.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}
.teacher-item:hover {
    border-color: var(--primary) !important;
    transform: translateX(-5px);
}
</style>

<script>
// المواد حسب الصف
const subjectsByClass = {
    <?php for ($i = 1; $i <= 6; $i++): ?>
    <?= $i ?>: <?= json_encode(Subject::getSubjectsByClass($i), JSON_UNESCAPED_UNICODE) ?>,
    <?php endfor; ?>
};

function showAddModal() {
    document.getElementById('addModal').style.display = 'flex';
}

function showBulkModal() {
    document.getElementById('bulkModal').style.display = 'flex';
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function loadSubjects() {
    const classId = document.getElementById('classSelect').value;
    const subjectSelect = document.getElementById('subjectSelect');
    
    subjectSelect.innerHTML = '<option value="">اختر المادة</option>';
    
    if (classId && subjectsByClass[classId]) {
        subjectsByClass[classId].forEach(subject => {
            const option = document.createElement('option');
            option.value = subject;
            option.textContent = subject;
            subjectSelect.appendChild(option);
        });
    }
}

// إغلاق المودال عند النقر خارجه
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
});

// ═══════════════════════════════════════════════════════════════
// حذف التعيين بـ AJAX بدون إعادة تحميل الصفحة
// ═══════════════════════════════════════════════════════════════
function deleteAssignmentAjax(assignmentId, teacherId) {
    if (!confirm('هل تريد إزالة هذا التعيين؟')) {
        return;
    }
    
    const item = document.getElementById('assignment_' + assignmentId);
    if (item) {
        // تعطيل الزر وإظهار حالة التحميل
        const btn = item.querySelector('.btn-danger');
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
            teacher_id: teacherId,
            csrf_token: '<?= generateCSRFToken() ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // إزالة العنصر بأنيميشن سلس
            if (item) {
                item.style.transition = 'all 0.3s ease';
                item.style.transform = 'translateX(100%)';
                item.style.opacity = '0';
                setTimeout(() => {
                    item.remove();
                    
                    // التحقق إذا كانت القائمة فارغة
                    const body = document.querySelector('.assignments-body');
                    if (body && body.querySelectorAll('.assignment-item').length === 0) {
                        body.innerHTML = `
                            <div class="empty-assignments">
                                <div class="icon">📭</div>
                                <h3><?= __('لا توجد تعيينات') ?></h3>
                                <p><?= __('تم حذف جميع التعيينات لهذا المعلم') ?></p>
                                <button type="button" class="btn btn-primary" onclick="showAddModal()">
                                    ➕ <?= __('إضافة تعيين الآن') ?>
                                </button>
                            </div>
                        `;
                    }
                }, 300);
            }
            
            // عرض تنبيه نجاح
            if (typeof notifySuccess === 'function') {
                notifySuccess('تم حذف التعيين بنجاح');
            }
        } else {
            // إعادة الزر للحالة الأصلية
            if (item) {
                const btn = item.querySelector('.btn-danger');
                if (btn) {
                    btn.innerHTML = '🗑️';
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
        if (item) {
            const btn = item.querySelector('.btn-danger');
            if (btn) {
                btn.innerHTML = '🗑️';
                btn.disabled = false;
            }
        }
        if (typeof notifyError === 'function') {
            notifyError('حدث خطأ في الاتصال');
        }
    });
}

// ═══════════════════════════════════════════════════════════════
// تحميل تعيينات المعلم بـ AJAX بدون إعادة تحميل الصفحة
// ═══════════════════════════════════════════════════════════════
function loadTeacherAssignments(event, element) {
    event.preventDefault();
    
    const teacherId = element.dataset.teacherId;
    const teacherName = element.dataset.teacherName;
    const url = element.href;
    
    // تحديث URL في المتصفح
    window.history.pushState({teacherId: teacherId}, '', url);
    
    // تحديث الكارد النشط
    document.querySelectorAll('.teacher-card').forEach(card => {
        card.classList.remove('active');
    });
    element.classList.add('active');
    
    // إظهار حالة التحميل
    const assignmentsCard = document.querySelector('.assignments-card');
    if (assignmentsCard) {
        assignmentsCard.innerHTML = `
            <div class="assignments-body">
                <div style="text-align: center; padding: 4rem 2rem;">
                    <div style="font-size: 3rem; animation: pulse 1s infinite;">⏳</div>
                    <h3>جاري تحميل بيانات ${teacherName}...</h3>
                </div>
            </div>
        `;
    }
    
    // طلب AJAX لتحميل البيانات
    fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.text())
    .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // تحديث بطاقة التعيينات
        const newAssignmentsCard = doc.querySelector('.assignments-card');
        if (newAssignmentsCard && assignmentsCard) {
            assignmentsCard.outerHTML = newAssignmentsCard.outerHTML;
        }
        
        // إظهار رسالة نجاح
        if (window.UI && window.UI.success) {
            window.UI.success('تم تحميل بيانات ' + teacherName + ' ✓');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // في حالة الخطأ، إعادة تحميل الصفحة
        window.location.href = url;
    });
}

// دعم زر الرجوع
window.addEventListener('popstate', function(e) {
    if (e.state && e.state.teacherId) {
        const card = document.querySelector(`.teacher-card[data-teacher-id="${e.state.teacherId}"]`);
        if (card) {
            loadTeacherAssignments({preventDefault: () => {}}, card);
        }
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
