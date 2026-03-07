<?php
/**
 * حسابات التلاميذ - Student Accounts
 * إدارة حسابات دخول التلاميذ للنظام
 * 
 * @package SchoolManager
 * @access  مدير فقط
 */

$pageTitle = 'حسابات التلاميذ';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Student.php';

// التحقق من تسجيل الدخول أولاً
requireLogin();

if (!isAdmin()) {
    alert('ليس لديك صلاحية للوصول لهذه الصفحة', 'error');
    redirect('/dashboard.php');
}

$userModel = new User();
$studentModel = new Student();

// Get only student users with passwords and links
$studentUsers = $userModel->getStudentUsersWithLinks();

$action = $_GET['action'] ?? 'list';
$editUser = null;

if ($action === 'edit' && isset($_GET['id'])) {
    $editUser = $userModel->findById((int)$_GET['id']);
    if ($editUser && $editUser['role'] !== 'student') {
        alert('هذا المستخدم ليس تلميذاً', 'error');
        redirect('/student_users.php');
    }
}

// التحقق من وجود حساب جديد للعرض
$newAccount = null;
if (isset($_GET['show_card']) && isset($_SESSION['new_account'])) {
    $newAccount = $_SESSION['new_account'];
    unset($_SESSION['new_account']); // حذف بعد الاستخدام
}

require_once __DIR__ . '/views/components/header.php';
?>

<div class="page-header d-flex justify-between align-center flex-wrap gap-2">
    <div>
        <h1>👨‍🎓 حسابات التلاميذ</h1>
        <p>إدارة حسابات دخول التلاميذ للنظام</p>
    </div>
    <div class="d-flex gap-2">
        <a href="students.php" class="btn btn-secondary">
            📋 قائمة التلاميذ
        </a>
    </div>
</div>




<?php if ($action === 'edit' && $editUser): ?>
<div class="card mb-3 fade-in">
    <div class="card-header">
        <h3>✏️ تعديل حساب التلميذ</h3>
        <a href="student_users.php" class="btn btn-secondary btn-sm">إلغاء</a>
    </div>
    <div class="card-body">
        <form action="controllers/student_user_handler.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= $editUser['id'] ?>">
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label>اسم المستخدم (للدخول)</label>
                    <input type="text" class="form-control" value="<?= sanitize($editUser['username']) ?>" readonly disabled>
                    <small class="text-muted">لا يمكن تغيير اسم المستخدم</small>
                </div>
                
                <div class="form-group">
                    <label>الاسم الكامل *</label>
                    <input type="text" name="full_name" class="form-control" required
                           value="<?= sanitize($editUser['full_name']) ?>"
                           placeholder="اسم التلميذ">
                </div>
                
                <div class="form-group">
                    <label>كلمة المرور الجديدة (اتركها فارغة للإبقاء)</label>
                    <input type="password" name="password" class="form-control"
                           placeholder="••••••">
                </div>
                
                <div class="form-group">
                    <label>الحالة</label>
                    <select name="status" class="form-control">
                        <option value="active" <?= $editUser['status'] === 'active' ? 'selected' : '' ?>>نشط</option>
                        <option value="inactive" <?= $editUser['status'] === 'inactive' ? 'selected' : '' ?>>معطل</option>
                    </select>
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    💾 حفظ التعديلات
                </button>
                <a href="student_users.php" class="btn btn-secondary">إلغاء</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card fade-in">
    <div class="card-header" style="flex-direction: column; align-items: stretch; gap: 1rem;">
        <div class="d-flex justify-between align-center flex-wrap gap-2">
            <div class="d-flex align-center gap-2">
                <h3>🔐 قائمة حسابات التلاميذ</h3>
                <span class="badge badge-info"><?= count($studentUsers) ?> حساب</span>
            </div>
        </div>
        <!-- 🔍 شريط الفلترة السريعة -->
        <div class="d-flex gap-2 flex-wrap align-center" style="background: var(--bg-secondary); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-color);">
            <div class="d-flex align-center gap-1" style="flex: 1; min-width: 200px;">
                <input type="text" 
                       id="studentUserSearch" 
                       class="form-control" 
                       placeholder="🔍 ابحث بالاسم، اسم المستخدم، أو ID..."
                       oninput="filterStudentUsers()"
                       onkeyup="filterStudentUsers()"
                       style="border-radius: 25px; border: 2px solid var(--border); transition: all 0.3s;">
            </div>
            <select id="classFilter" class="form-control" style="width: auto; min-width: 120px;" onchange="filterStudentUsers()">
                <option value="">🏫 كل الصفوف</option>
                <?php foreach (CLASSES as $id => $name): ?>
                <option value="<?= $name ?>"><?= $name ?></option>
                <?php endforeach; ?>
            </select>
            <select id="sectionFilter" class="form-control" style="width: auto; min-width: 100px;" onchange="filterStudentUsers()">
                <option value="">📚 كل الشعب</option>
                <?php foreach (SECTIONS as $sec): ?>
                <option value="<?= $sec ?>"><?= $sec ?></option>
                <?php endforeach; ?>
            </select>
            <select id="studentStatusFilter" class="form-control" style="width: auto; min-width: 120px;" onchange="filterStudentUsers()">
                <option value="">🔄 كل الحالات</option>
                <option value="active">✅ نشط</option>
                <option value="inactive">❌ معطل</option>
            </select>
            <button type="button" class="btn btn-secondary btn-sm" onclick="resetStudentUserFilters()" style="padding: 0.5rem 1rem;">
                ✕ مسح
            </button>
            <span style="color: var(--text-muted); font-size: 0.9rem;" id="studentUserSearchCount"></span>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($studentUsers)): ?>
        <div class="empty-state">
            <div class="icon">🔑</div>
            <h3>لا توجد حسابات تلاميذ</h3>
            <p>يمكنك إنشاء حساب لأي تلميذ من صفحة <a href="students.php">إدارة التلاميذ</a></p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ID الحساب</th>
                        <th>ID التلميذ</th>
                        <th>اسم المستخدم</th>
                        <th>اسم التلميذ</th>
                        <th>الصف</th>
                        <th>الشعبة</th>
                        <th>الحالة</th>
                        <th>تاريخ الإنشاء</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $counter = 1; foreach ($studentUsers as $user): ?>
                    <tr>
                        <td><?= $counter++ ?></td>
                        <td><code style="background: #dbeafe; color: #1e40af; padding: 2px 6px; border-radius: 4px; font-weight: 600;"><?= $user['id'] ?></code></td>
                        <td>
                            <?php if (!empty($user['student_id'])): ?>
                            <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-weight: 600;"><?= $user['student_id'] ?></code>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= sanitize($user['username']) ?></code></td>
                        <td><strong><?= sanitize($user['full_name']) ?></strong></td>
                        <td>
                            <?php if (!empty($user['class_id'])): ?>
                                <?= CLASSES[$user['class_id']] ?? $user['class_id'] ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($user['section'])): ?>
                                <?= sanitize($user['section']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $user['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                <?= $user['status'] === 'active' ? 'نشط' : 'معطل' ?>
                            </span>
                        </td>
                        <td><?= formatArabicDate($user['created_at']) ?></td>
                        <td>
                                <button type="button" class="btn btn-primary btn-sm" title="طباعة بطاقة الدخول"
                                        onclick="showLoginCard('<?= sanitize($user['username']) ?>', '<?= sanitize($user['full_name']) ?>', '<?= !empty($user['class_id']) ? (CLASSES[$user['class_id']] ?? $user['class_id']) : '' ?>', '<?= !empty($user['section']) ? sanitize($user['section']) : '' ?>', '<?= $user['plain_password'] ?? '' ?>')">
                                    📱
                                </button>
                                <a href="?action=edit&id=<?= $user['id'] ?>" class="btn btn-secondary btn-sm" title="تعديل">
                                    ✏️
                                </a>
                                <!-- زر تفعيل/تعطيل - AJAX -->
                                <?php if ($user['status'] === 'active'): ?>
                                <button type="button" 
                                        class="btn btn-warning btn-sm"
                                        data-toggle-status
                                        data-module="student_users"
                                        data-id="<?= $user['id'] ?>"
                                        data-current-status="active"
                                        title="تعطيل الحساب">🚫</button>
                                <?php else: ?>
                                <button type="button" 
                                        class="btn btn-success btn-sm"
                                        data-toggle-status
                                        data-module="student_users"
                                        data-id="<?= $user['id'] ?>"
                                        data-current-status="inactive"
                                        title="تفعيل الحساب">✅</button>
                                <?php endif; ?>
                                
                                <!-- زر الحذف - AJAX -->
                                <button type="button" 
                                        class="btn btn-danger btn-sm"
                                        data-delete
                                        data-module="student_users"
                                        data-id="<?= $user['id'] ?>"
                                        data-delete-message="هل تريد حذف حساب التلميذ '<?= sanitize($user['full_name']) ?>'؟ (سيتم حذف الحساب فقط وليس بيانات التلميذ)"
                                        title="حذف الحساب">🗑️</button>
                                
                                <!-- زر إعادة تعيين كلمة المرور - TODO: تحويل لـ AJAX لاحقاً -->
                                <form action="controllers/student_user_handler.php" method="POST" style="display: inline;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="btn btn-info btn-sm" title="إعادة تعيين كلمة المرور"
                                            onclick="return confirm('هل تريد إعادة تعيين كلمة المرور إلى الافتراضية (123456)؟')">
                                        🔄
                                    </button>
                                </form>
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

<div class="card mt-3 fade-in">
    <div class="card-header">
        <h3>💡 معلومات مفيدة</h3>
    </div>
    <div class="card-body">
        <div class="grid grid-3">
            <div class="info-box" style="background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-sm);">
                <h4>📝 إنشاء حساب تلميذ</h4>
                <p class="text-muted">لإنشاء حساب لتلميذ، اذهب إلى <a href="students.php">إدارة التلاميذ</a> واضغط على زر 🔑 بجانب اسم التلميذ، ثم أدخل اسم المستخدم وكلمة المرور.</p>
            </div>
            <div class="info-box" style="background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-sm);">
                <h4>🔄 إعادة تعيين كلمة المرور</h4>
                <p class="text-muted">يمكنك إعادة تعيين كلمة المرور لأي تلميذ إلى <code>123456</code> بالضغط على زر 🔄 في قائمة الحسابات.</p>
            </div>
            <div class="info-box" style="background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-sm);">
                <h4>👁️ صلاحيات التلميذ</h4>
                <p class="text-muted">يستطيع التلميذ رؤية بياناته الشخصية وسجل غياباته فقط، ولا يمكنه الوصول لبقية صفحات النظام.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>

<!-- Modal بطاقة الدخول -->
<div id="loginCardModal" class="modal" style="display: none;">
    <div class="modal-overlay" onclick="closeLoginCardModal()"></div>
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h3 style="color: white; margin: 0;">📱 بطاقة دخول التلميذ</h3>
            <button class="modal-close" onclick="closeLoginCardModal()">&times;</button>
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

#loginCardModal .modal-close {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    font-size: 1.5rem;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
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

/* أنماط الطباعة */
@media print {
    /* إخفاء كل شيء */
    html, body {
        visibility: visible !important;
        background: white !important;
    }
    
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
        color-adjust: exact !important;
    }
    
    .login-card * {
        visibility: visible !important;
        color: #333 !important;
    }
    
    .login-card-school {
        color: #666 !important;
    }
    
    .login-card-title {
        color: #667eea !important;
    }
    
    .login-card-qr {
        background: white !important;
        padding: 10px !important;
    }
    
    .login-card-qr img {
        width: 150px !important;
        height: 150px !important;
        display: block !important;
    }
    
    .login-card-info-row {
        border-bottom: 1px dashed #ccc !important;
    }
    
    .login-card-label {
        color: #555 !important;
    }
    
    .login-card-value {
        color: #333 !important;
    }
    
    .login-card-note {
        background: #f0f0f0 !important;
        color: #666 !important;
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

function showLoginCard(username, fullName, className, section, password) {
    const modal = document.getElementById('loginCardModal');
    const content = document.getElementById('loginCardContent');
    
    // استخدام QR Server API (مجاني وموثوق)
    const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent(loginUrl) + '&color=667eea';
    
    // كلمة المرور - إذا لم تكن متوفرة نعرض رسالة واضحة
    const passwordDisplay = password || '(غير متوفرة - أعد التعيين)';
    
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
                    <span class="login-card-value">${passwordDisplay}</span>
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

// ═══════════════════════════════════════════════════════════════
// 🔍 دالة البحث والفلترة في جدول حسابات التلاميذ
// ═══════════════════════════════════════════════════════════════
function filterStudentUsers() {
    const input = document.getElementById('studentUserSearch');
    const classFilter = document.getElementById('classFilter');
    const sectionFilter = document.getElementById('sectionFilter');
    const statusFilter = document.getElementById('studentStatusFilter');
    
    const filter = input ? input.value.toLowerCase().trim() : '';
    const classValue = classFilter ? classFilter.value : '';
    const sectionValue = sectionFilter ? sectionFilter.value : '';
    const statusValue = statusFilter ? statusFilter.value : '';
    
    const table = document.querySelector('.table-responsive table');
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    let visibleCount = 0;
    
    // تحويل قيمة الحالة إلى النص العربي
    const statusMap = {
        'active': 'نشط',
        'inactive': 'معطل'
    };
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const cells = row.querySelectorAll('td');
        const classCell = cells[5]?.textContent.trim() || ''; // عمود الصف
        const sectionCell = cells[6]?.textContent.trim() || ''; // عمود الشعبة
        const statusCell = cells[7]?.textContent.trim() || ''; // عمود الحالة
        
        let matchesSearch = !filter || text.includes(filter);
        // مطابقة الصف
        let matchesClass = !classValue || classCell.includes(classValue);
        // مطابقة الشعبة
        let matchesSection = !sectionValue || sectionCell === sectionValue;
        // مطابقة الحالة بالنص العربي
        let matchesStatus = !statusValue || statusCell.includes(statusMap[statusValue] || '');
        
        if (matchesSearch && matchesClass && matchesSection && matchesStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    const countSpan = document.getElementById('studentUserSearchCount');
    if (countSpan) {
        if (filter || classValue || sectionValue || statusValue) {
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

function resetStudentUserFilters() {
    document.getElementById('studentUserSearch').value = '';
    document.getElementById('classFilter').value = '';
    document.getElementById('sectionFilter').value = '';
    document.getElementById('studentStatusFilter').value = '';
    filterStudentUsers();
}

<?php if ($newAccount): ?>
// عرض بطاقة الحساب الجديد تلقائياً
document.addEventListener('DOMContentLoaded', function() {
    showLoginCard(
        '<?= $newAccount['username'] ?>',
        '<?= $newAccount['full_name'] ?>',
        '<?= $newAccount['class'] ?>',
        '<?= $newAccount['section'] ?>',
        '<?= $newAccount['password'] ?>'
    );
});
<?php endif; ?>
</script>
