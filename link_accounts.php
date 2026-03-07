<?php
/**
 * الحسابات اليتيمة للطلاب
 * حسابات بدون سجل طالب مرتبط - للحذف فقط
 */

$pageTitle = 'الحسابات اليتيمة';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/permissions.php';

requireLogin();
requirePermission('manage_students');

$conn = getConnection();

// AJAX: حذف حساب واحد
if (isset($_POST['ajax_delete'])) {
    header('Content-Type: application/json');
    $id = (int)$_POST['id'];
    try {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
        $stmt->execute([$id]);
        echo json_encode(['success' => $stmt->rowCount() > 0, 'message' => 'تم حذف الحساب بنجاح']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// AJAX: حذف جميع الحسابات
if (isset($_POST['ajax_delete_all'])) {
    header('Content-Type: application/json');
    try {
        $stmt = $conn->query("
            SELECT u.id FROM users u
            LEFT JOIN students s ON s.user_id = u.id
            WHERE u.role = 'student' AND s.id IS NULL
        ");
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $deleted = 0;
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $deleteStmt = $conn->prepare("DELETE FROM users WHERE id IN ($placeholders)");
            $deleteStmt->execute($ids);
            $deleted = $deleteStmt->rowCount();
        }
        echo json_encode(['success' => true, 'deleted' => $deleted, 'message' => "تم حذف {$deleted} حساب يتيم"]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// جلب الحسابات اليتيمة
$orphanAccounts = [];
try {
    $stmt = $conn->query("
        SELECT u.id, u.username, u.full_name, u.created_at, u.plain_password
        FROM users u
        LEFT JOIN students s ON s.user_id = u.id
        WHERE u.role = 'student' AND s.id IS NULL
        ORDER BY u.created_at DESC
    ");
    $orphanAccounts = $stmt->fetchAll();
} catch (Exception $e) {}

require_once __DIR__ . '/views/components/header.php';
?>

<div class="page-header d-flex justify-between align-center flex-wrap gap-2">
    <div>
        <h1>🗑️ الحسابات اليتيمة (الطلاب)</h1>
        <p>حسابات بدون سجل طالب مرتبط</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="student_users.php" class="btn btn-secondary">← حسابات التلاميذ</a>
    </div>
</div>

<div class="card">
    <div class="card-header" style="background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); color: white;">
        <div class="d-flex justify-between align-center flex-wrap gap-2">
            <h3>⚠️ حسابات يتيمة <span class="badge" id="orphanCount" style="background: rgba(255,255,255,0.3);"><?= count($orphanAccounts) ?></span></h3>
            <?php if (!empty($orphanAccounts)): ?>
            <button type="button" class="btn btn-danger" id="deleteAllBtn">
                🗑️ حذف الجميع (<span id="totalCount"><?= count($orphanAccounts) ?></span>)
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($orphanAccounts)): ?>
        <div class="empty-state" id="emptyState">
            <div class="icon" style="font-size: 4rem;">✅</div>
            <h3>لا توجد حسابات يتيمة!</h3>
            <p>جميع الحسابات مرتبطة بسجلات طلاب</p>
        </div>
        <?php else: ?>
        <div class="table-responsive" id="tableContainer">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ID</th>
                        <th>الاسم</th>
                        <th>اسم المستخدم</th>
                        <th>كلمة المرور</th>
                        <th>تاريخ الإنشاء</th>
                        <th>حذف</th>
                    </tr>
                </thead>
                <tbody id="orphanTable">
                    <?php $i = 1; foreach ($orphanAccounts as $account): ?>
                    <tr id="row-<?= $account['id'] ?>">
                        <td><?= $i++ ?></td>
                        <td><strong><?= $account['id'] ?></strong></td>
                        <td><?= htmlspecialchars($account['full_name']) ?></td>
                        <td><code><?= htmlspecialchars($account['username']) ?></code></td>
                        <td>
                            <?php if ($account['plain_password']): ?>
                            <code style="background: #e3f2fd; padding: 2px 6px; border-radius: 4px;"><?= htmlspecialchars($account['plain_password']) ?></code>
                            <?php else: ?>
                            <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= formatArabicDate($account['created_at']) ?></td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm delete-btn" data-id="<?= $account['id'] ?>" data-name="<?= htmlspecialchars($account['full_name']) ?>">
                                🗑️
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="empty-state" id="emptyState" style="display: none;">
            <div class="icon" style="font-size: 4rem;">✅</div>
            <h3>تم حذف جميع الحسابات اليتيمة!</h3>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h3>ℹ️ ما هي الحسابات اليتيمة؟</h3>
    </div>
    <div class="card-body">
        <p style="color: var(--text-secondary);">
            الحسابات اليتيمة هي حسابات مستخدمين من نوع "طالب" ليس لها سجل طالب مرتبط.
            يمكنك حذفها بأمان لأنها لا تؤثر على أي بيانات.
        </p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // حذف فردي
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            
            if (!confirm('هل تريد حذف حساب "' + name + '"؟')) return;
            
            const originalText = this.innerHTML;
            this.innerHTML = '⏳';
            this.disabled = true;
            
            try {
                const response = await fetch('link_accounts.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'ajax_delete=1&id=' + id
                });
                const data = await response.json();
                
                if (data.success) {
                    const row = document.getElementById('row-' + id);
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-20px)';
                    setTimeout(() => {
                        row.remove();
                        updateCount();
                    }, 300);
                    
                    // رسالة نجاح
                    if (typeof UI !== 'undefined') {
                        UI.success(data.message);
                    } else {
                        alert('✅ ' + data.message);
                    }
                } else {
                    this.innerHTML = originalText;
                    this.disabled = false;
                    alert('❌ ' + (data.message || 'فشل الحذف'));
                }
            } catch (e) {
                this.innerHTML = originalText;
                this.disabled = false;
                alert('❌ خطأ في الاتصال');
            }
        });
    });
    
    // حذف الجميع
    const deleteAllBtn = document.getElementById('deleteAllBtn');
    if (deleteAllBtn) {
        deleteAllBtn.addEventListener('click', async function() {
            if (!confirm('هل أنت متأكد من حذف جميع الحسابات اليتيمة؟\nهذا الإجراء لا يمكن التراجع عنه!')) return;
            
            const originalText = this.innerHTML;
            this.innerHTML = '⏳ جاري الحذف...';
            this.disabled = true;
            
            try {
                const response = await fetch('link_accounts.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'ajax_delete_all=1'
                });
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('orphanTable').innerHTML = '';
                    document.getElementById('tableContainer').style.display = 'none';
                    document.getElementById('emptyState').style.display = 'block';
                    this.style.display = 'none';
                    
                    if (typeof UI !== 'undefined') {
                        UI.success(data.message);
                    } else {
                        alert('✅ ' + data.message);
                    }
                } else {
                    this.innerHTML = originalText;
                    this.disabled = false;
                    alert('❌ ' + (data.message || 'فشل الحذف'));
                }
            } catch (e) {
                this.innerHTML = originalText;
                this.disabled = false;
                alert('❌ خطأ في الاتصال');
            }
        });
    }
    
    function updateCount() {
        const rows = document.querySelectorAll('#orphanTable tr');
        const count = rows.length;
        const orphanCount = document.getElementById('orphanCount');
        const totalCount = document.getElementById('totalCount');
        if (orphanCount) orphanCount.textContent = count;
        if (totalCount) totalCount.textContent = count;
        
        if (count === 0) {
            document.getElementById('tableContainer').style.display = 'none';
            document.getElementById('emptyState').style.display = 'block';
            const deleteAllBtn = document.getElementById('deleteAllBtn');
            if (deleteAllBtn) deleteAllBtn.style.display = 'none';
        }
    }
});
</script>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
