<?php
/**
 * إدارة المستخدمين - Users Management
 * إدارة حسابات المعلمين والمدراء
 * 
 * @package SchoolManager
 * @access  مدير فقط
 */

$pageTitle = 'إدارة المستخدمين';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/models/User.php';

// التحقق من تسجيل الدخول أولاً
requireLogin();

if (!isAdmin()) {
    alert('ليس لديك صلاحية للوصول لهذه الصفحة', 'error');
    redirect('/dashboard.php');
}

$userModel = new User();
$users = $userModel->getAllWithLinks();

$action = $_GET['action'] ?? 'list';
$editUser = null;

if ($action === 'edit' && isset($_GET['id'])) {
    $editUser = $userModel->findById((int)$_GET['id']);
}

// جلب قائمة المعلمين للربط
require_once __DIR__ . '/models/Teacher.php';
$teacherModel = new Teacher();
$allTeachers = $teacherModel->getAll();

require_once __DIR__ . '/views/components/header.php';
?>

<div class="page-header d-flex justify-between align-center flex-wrap gap-2">
    <div>
        <h1><?= __('إدارة المستخدمين') ?></h1>
        <p><?= __('المعلمون والمدراء') ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="?action=add" class="btn btn-primary">
            <?= __('➕ إضافة مستخدم جديد') ?>
        </a>
    </div>
</div>



<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card mb-3 fade-in">
    <div class="card-header">
        <h3><?= $action === 'add' ? '➕ إضافة مستخدم جديد' : '✏️ تعديل المستخدم' ?></h3>
        <a href="users.php" class="btn btn-secondary btn-sm">إلغاء</a>
    </div>
    <div class="card-body">
        <form action="controllers/user_handler.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="<?= $action ?>">
            <?php if ($editUser): ?>
            <input type="hidden" name="id" value="<?= $editUser['id'] ?>">
            <?php endif; ?>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label>اسم المستخدم (للدخول) *</label>
                    <input type="text" name="username" class="form-control" required
                           value="<?= sanitize($editUser['username'] ?? '') ?>"
                           placeholder="مثال: teacher1" <?= $editUser ? 'readonly' : '' ?>>
                </div>
                
                <div class="form-group">
                    <label>الاسم الكامل *</label>
                    <input type="text" name="full_name" class="form-control" required
                           value="<?= sanitize($editUser['full_name'] ?? '') ?>"
                           placeholder="مثال: أحمد محمد">
                </div>
                
                <div class="form-group">
                    <label>كلمة المرور <?= $action === 'add' ? '*' : '(اتركها فارغة للإبقاء)' ?></label>
                    <input type="password" name="password" class="form-control"
                           <?= $action === 'add' ? 'required' : '' ?>
                           placeholder="••••••">
                </div>
                
                <div class="form-group">
                    <label>الصلاحية *</label>
                    <select name="role" class="form-control" required id="userRole" onchange="toggleTeacherOption()">
                        <option value="teacher" <?= ($editUser['role'] ?? '') === 'teacher' ? 'selected' : '' ?>>معلم</option>
                        <option value="assistant" <?= ($editUser['role'] ?? '') === 'assistant' ? 'selected' : '' ?>>معاون</option>
                        <option value="admin" <?= ($editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>مدير</option>
                    </select>
                </div>
                

                
                <!-- 🔗 ربط الحساب بسجل المعلم -->
                <div class="form-group" id="teacherLinkGroup" style="display: none;">
                    <label>🔗 ربط بسجل المعلم</label>
                    <select name="teacher_id" class="form-control" id="teacherSelect">
                        <option value="">-- بدون ربط --</option>
                        <?php foreach ($allTeachers as $teacher): ?>
                        <option value="<?= $teacher['id'] ?>" 
                                <?= ($editUser['teacher_id'] ?? '') == $teacher['id'] ? 'selected' : '' ?>>
                            #<?= $teacher['id'] ?> - <?= sanitize($teacher['full_name']) ?>
                            <?= !empty($teacher['specialization']) ? '(' . sanitize($teacher['specialization']) . ')' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: var(--text-secondary);">ربط الحساب بسجل المعلم يتيح تحديد صلاحياته بناءً على المواد المسندة إليه</small>
                </div>
            </div>
            
            <script>
            function toggleTeacherOption() {
                const role = document.getElementById('userRole').value;
                const teacherLinkGroup = document.getElementById('teacherLinkGroup');
                
                // إظهار حقل ربط سجل المعلم للمعلم والمعاون
                if (role === 'teacher' || role === 'assistant') {
                    teacherLinkGroup.style.display = 'block';
                } else {
                    teacherLinkGroup.style.display = 'none';
                }
            }
            // تشغيل عند تحميل الصفحة
            document.addEventListener('DOMContentLoaded', toggleTeacherOption);
            </script>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    💾 <?= $action === 'add' ? 'إضافة المستخدم' : 'حفظ التعديلات' ?>
                </button>
                <a href="users.php" class="btn btn-secondary">إلغاء</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card fade-in">
    <div class="card-header" style="flex-direction: column; align-items: stretch; gap: 1rem;">
        <div class="d-flex justify-between align-center flex-wrap gap-2">
            <h3><?= __('👥 قائمة المستخدمين') ?> <span id="userCount" style="font-weight: normal; color: #666; font-size: 0.9rem;">(<?= count($users) ?> <?= __('مستخدم') ?>)</span></h3>
        </div>
        <!-- 🔍 شريط الفلترة السريعة -->
        <div class="d-flex gap-2 flex-wrap align-center" style="background: var(--bg-secondary); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-color);">
            <div class="d-flex align-center gap-1" style="flex: 1; min-width: 200px;">
                <input type="text" 
                       id="userSearch" 
                       class="form-control" 
                       placeholder="<?= __('🔍 ابحث بالاسم، اسم المستخدم، أو ID...') ?>"
                       oninput="filterUsers()"
                       onkeyup="filterUsers()"
                       style="border-radius: 25px; border: 2px solid var(--border); transition: all 0.3s;">
            </div>
            <select id="roleFilter" class="form-control" style="width: auto; min-width: 120px;" onchange="filterUsers()">
                <option value=""><?= __('👤 كل الأدوار') ?></option>
                <option value="admin"><?= __('👨‍💼 مدير') ?></option>
                <option value="assistant"><?= __('👨‍✈️ معاون') ?></option>
                <option value="teacher"><?= __('👨‍🏫 معلم') ?></option>
            </select>
            <select id="statusFilter" class="form-control" style="width: auto; min-width: 120px;" onchange="filterUsers()">
                <option value=""><?= __('🔄 كل الحالات') ?></option>
                <option value="active"><?= __('✅ نشط') ?></option>
                <option value="inactive"><?= __('❌ معطل') ?></option>
            </select>
            <button type="button" class="btn btn-secondary btn-sm" onclick="resetUserFilters()" style="padding: 0.5rem 1rem;">
                <?= __('✕ مسح') ?>
            </button>
            <span style="color: var(--text-muted); font-size: 0.9rem;" id="userSearchCount"></span>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ID</th>
                        <th>اسم المستخدم</th>
                        <th>الاسم الكامل</th>
                        <th>الصلاحية</th>
                        <th>🔗 سجل المعلم</th>
                        <th>الحالة</th>
                        <th>تاريخ الإنشاء</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $counter = 1; foreach ($users as $user): ?>
                    <tr>
                        <td><?= $counter++ ?></td>
                        <td><code style="background: var(--bg-secondary); padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 600;">#<?= $user['id'] ?></code></td>
                        <td><code><?= sanitize($user['username']) ?></code></td>
                        <td><strong><?= sanitize($user['full_name']) ?></strong></td>
                        <td>
                            <span class="badge <?= $user['role'] === 'admin' ? 'badge-info' : ($user['role'] === 'assistant' ? 'badge-primary' : 'badge-secondary') ?>">
                                <?= ROLES[$user['role']] ?? $user['role'] ?>
                            </span>

                        </td>
                        <td>
                            <?php if (!empty($user['teacher_id']) && !empty($user['teacher_name'])): ?>
                            <span class="badge badge-info" title="مرتبط بسجل المعلم">
                                🔗 #<?= $user['teacher_id'] ?> - <?= sanitize($user['teacher_name']) ?>
                            </span>
                            <?php else: ?>
                            <span style="color: var(--text-muted);">--</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <!-- زر تبديل الحالة - AJAX -->
                            <button type="button" 
                                    class="badge <?= $user['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"
                                    style="cursor: pointer; border: none;"
                                    data-toggle-status
                                    data-module="users"
                                    data-id="<?= $user['id'] ?>"
                                    data-current-status="<?= $user['status'] ?>"
                                    title="اضغط لتبديل الحالة">
                                <?= $user['status'] === 'active' ? 'نشط' : 'معطل' ?>
                            </button>
                        </td>
                        <td><?= formatArabicDate($user['created_at']) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <?php if (canManageUser($user['id'])): ?>
                                <a href="?action=edit&id=<?= $user['id'] ?>" class="btn btn-secondary btn-sm" title="تعديل">✏️</a>
                                <?php if ($user['id'] != $currentUser['id']): ?>
                                <!-- زر تعطيل/تنشيط -->
                                <button type="button" 
                                        class="btn <?= $user['status'] === 'active' ? 'btn-warning' : 'btn-success' ?> btn-sm"
                                        data-toggle-status
                                        data-module="users"
                                        data-id="<?= $user['id'] ?>"
                                        data-current-status="<?= $user['status'] ?>"
                                        title="<?= $user['status'] === 'active' ? 'تعطيل الحساب' : 'تنشيط الحساب' ?>">
                                    <?= $user['status'] === 'active' ? '🚫' : '✅' ?>
                                </button>
                                <button type="button" 
                                        class="btn btn-danger btn-sm ajax-delete-btn"
                                        data-delete
                                        data-module="users"
                                        data-id="<?= $user['id'] ?>"
                                        data-delete-message="هل تريد حذف المستخدم '<?= sanitize($user['full_name']) ?>'؟"
                                        title="حذف">
                                    🗑️
                                </button>
                                <?php endif; ?>
                                <?php else: ?>
                                <!-- المعاون لا يستطيع التعديل على المدير -->
                                <span class="badge badge-secondary" title="لا يمكن التعديل على حساب المدير">🔒</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 🔍 سكربت البحث والفلترة -->
<script>
function filterUsers() {
    const input = document.getElementById('userSearch');
    const roleFilter = document.getElementById('roleFilter');
    const statusFilter = document.getElementById('statusFilter');
    const filter = input ? input.value.toLowerCase().trim() : '';
    const roleValue = roleFilter ? roleFilter.value : '';
    const statusValue = statusFilter ? statusFilter.value : '';
    const table = document.querySelector('.table-responsive table');
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    let visibleCount = 0;
    
    // تحويل قيم الفلتر إلى النصوص العربية المطابقة
    const roleMap = {
        'admin': 'مدير',
        'assistant': 'معاون',
        'teacher': 'معلم'
    };
    const statusMap = {
        'active': 'نشط',
        'inactive': 'معطل'
    };
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const cells = row.querySelectorAll('td');
        const roleCell = cells[4]?.textContent.trim() || ''; // عمود الصلاحية
        const statusCell = cells[6]?.textContent.trim() || ''; // عمود الحالة
        
        let matchesSearch = !filter || text.includes(filter);
        let matchesRole = !roleValue || roleCell.includes(roleMap[roleValue] || '');
        let matchesStatus = !statusValue || statusCell.includes(statusMap[statusValue] || '');
        
        if (matchesSearch && matchesRole && matchesStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    const countSpan = document.getElementById('userSearchCount');
    if (countSpan) {
        if (filter || roleValue || statusValue) {
            countSpan.textContent = '📊 ' + visibleCount + ' نتيجة';
        } else {
            countSpan.textContent = '';
        }
    }
    
    if (input && filter) {
        input.style.borderColor = 'var(--primary)';
        input.style.boxShadow = '0 0 0 3px rgba(102, 126, 234, 0.2)';
    } else if (input) {
        input.style.borderColor = '';
        input.style.boxShadow = '';
    }
}

function resetUserFilters() {
    document.getElementById('userSearch').value = '';
    document.getElementById('roleFilter').value = '';
    document.getElementById('statusFilter').value = '';
    filterUsers();
}
</script>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
