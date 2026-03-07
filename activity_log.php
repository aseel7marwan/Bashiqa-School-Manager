<?php
/**
 * صفحة سجل العمليات - Activity Log Page
 * عرض جميع العمليات التي تمت في النظام
 * 
 * @package SchoolManager
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/models/ActivityLog.php';

requireLogin();

// فقط المدير والمعاون يمكنهم رؤية السجل
if (!isAdmin()) {
    alert('غير مصرح لك بالوصول لهذه الصفحة', 'error');
    redirect('/dashboard.php');
}

$activityLog = new ActivityLog();

// التصفية
$filters = [];
if (!empty($_GET['action_type'])) {
    $filters['action_type'] = $_GET['action_type'];
}
if (!empty($_GET['target_type'])) {
    $filters['target_type'] = $_GET['target_type'];
}
if (!empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

// الترقيم
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 30;
$offset = ($page - 1) * $perPage;

$logs = $activityLog->getAll($filters, $perPage, $offset);
$totalLogs = $activityLog->count($filters);
$totalPages = ceil($totalLogs / $perPage);
$stats = $activityLog->getStats();

// ترجمة أنواع العمليات
$actionTypes = [
    'add' => ['label' => 'إضافة', 'icon' => '➕', 'color' => '#10b981'],
    'edit' => ['label' => 'تعديل', 'icon' => '✏️', 'color' => '#f59e0b'],
    'delete' => ['label' => 'حذف', 'icon' => '🗑️', 'color' => '#ef4444'],
    'login' => ['label' => 'دخول', 'icon' => '🔑', 'color' => '#3b82f6'],
    'other' => ['label' => 'أخرى', 'icon' => '📋', 'color' => '#6b7280']
];

// ترجمة أنواع الأهداف
$targetTypes = [
    'student' => 'طالب',
    'teacher' => 'معلم',
    'user' => 'مستخدم',
    'attendance' => 'حضور',
    'grade' => 'درجة',
    'leave' => 'إجازة',
    'schedule' => 'جدول',
    'event' => 'حدث',
    'backup' => 'نسخ احتياطي',
    'system' => 'النظام'
];

// ترجمة الأدوار
$roles = [
    'admin' => 'مدير',
    'assistant' => 'معاون',
    'teacher' => 'معلم',
    'student' => 'طالب'
];

include 'views/components/header.php';
?>

<style>
.activity-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-color) 100%);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    border: 1px solid var(--border-color);
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.stat-card .stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: var(--primary);
}

.stat-card .stat-label {
    color: var(--text-muted);
    font-size: 0.9rem;
    margin-top: 5px;
}

.filters-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid var(--border-color);
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    align-items: end;
}

.filter-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: var(--text-color);
    font-size: 0.85rem;
}

.filter-group input,
.filter-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--bg-color);
    color: var(--text-color);
    font-size: 0.95rem;
}

.activity-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--card-bg);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.activity-table th {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark, #4f46e5) 100%);
    color: white;
    padding: 15px;
    text-align: right;
    font-weight: 600;
}

.activity-table td {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
}

.activity-table tr:hover {
    background: var(--bg-color);
}

.activity-table tr:last-child td {
    border-bottom: none;
}

.action-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
}

.role-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
}

.role-admin { background: #dbeafe; color: #1d4ed8; }
.role-assistant { background: #ede9fe; color: #7c3aed; }
.role-teacher { background: #d1fae5; color: #059669; }
.role-student { background: #fef3c7; color: #d97706; }

.user-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.user-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.9rem;
}

.time-display {
    display: flex;
    flex-direction: column;
    font-size: 0.85rem;
}

.time-display .date {
    color: var(--text-color);
    font-weight: 500;
}

.time-display .time {
    color: var(--text-muted);
    font-size: 0.8rem;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.pagination a, .pagination span {
    padding: 8px 15px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
}

.pagination a {
    background: var(--card-bg);
    color: var(--text-color);
    border: 1px solid var(--border-color);
}

.pagination a:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination .current {
    background: var(--primary);
    color: white;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
}

.empty-state .icon {
    font-size: 4rem;
    margin-bottom: 15px;
}

.target-info {
    display: flex;
    flex-direction: column;
}

.target-name {
    font-weight: 500;
    color: var(--text-color);
}

.target-type {
    font-size: 0.8rem;
    color: var(--text-muted);
}

@media (max-width: 768px) {
    .activity-table {
        display: block;
        overflow-x: auto;
    }
    
    .filters-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .stats-row {
        grid-template-columns: 1fr 1fr;
    }
}

/* عرض التغييرات */
.changes-toggle {
    background: none;
    border: 1px solid var(--border-color);
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    cursor: pointer;
    color: var(--primary);
    transition: all 0.2s;
}

.changes-toggle:hover {
    background: var(--primary);
    color: white;
}

.changes-box {
    display: none;
    margin-top: 10px;
    padding: 12px;
    background: var(--bg-color);
    border-radius: 8px;
    border: 1px solid var(--border-color);
    font-size: 0.8rem;
}

.changes-box.show {
    display: block;
}

.change-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
    padding: 6px 0;
    border-bottom: 1px dashed var(--border-color);
}

.change-row:last-child {
    margin-bottom: 0;
    border-bottom: none;
}

.change-label {
    font-weight: 600;
    color: var(--text-color);
    min-width: 80px;
}

.change-old {
    background: #fee2e2;
    color: #991b1b;
    padding: 3px 8px;
    border-radius: 4px;
    text-decoration: line-through;
}

.change-arrow {
    color: var(--text-muted);
    font-size: 1rem;
}

.change-new {
    background: #dcfce7;
    color: #166534;
    padding: 3px 8px;
    border-radius: 4px;
}

.change-value {
    background: #dbeafe;
    color: #1e40af;
    padding: 3px 8px;
    border-radius: 4px;
}

.change-deleted {
    background: #fee2e2;
    color: #991b1b;
    padding: 3px 8px;
    border-radius: 4px;
}

.change-header {
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 8px;
    font-size: 0.75rem;
    text-transform: uppercase;
}
</style>

<div class="activity-container">
    <h1 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
        <?= __('📋 سجل العمليات') ?>
        <span style="font-size: 0.5em; color: var(--text-muted); font-weight: normal;">
            (<?= number_format($totalLogs) ?> <?= __('عملية') ?>)
        </span>
    </h1>
    
    <!-- الإحصائيات -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value"><?= number_format($stats['today']) ?></div>
            <div class="stat-label"><?= __('📅 عمليات اليوم') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format($stats['week']) ?></div>
            <div class="stat-label"><?= __('📊 هذا الأسبوع') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format($stats['by_type']['add'] ?? 0) ?></div>
            <div class="stat-label"><?= __('➕ إضافات') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format($stats['by_type']['edit'] ?? 0) ?></div>
            <div class="stat-label"><?= __('✏️ تعديلات') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format($stats['by_type']['delete'] ?? 0) ?></div>
            <div class="stat-label"><?= __('🗑️ حذف') ?></div>
        </div>
    </div>
    
    <!-- التصفية المباشرة -->
    <div class="filters-card">
        <div class="filters-grid">
            <div class="filter-group">
                <label><?= __('🔍 بحث') ?></label>
                <input type="text" id="searchInput" placeholder="<?= __('اسم أو عملية...') ?>" oninput="filterActivityLog()">
            </div>
            
            <div class="filter-group">
                <label><?= __('📋 نوع العملية') ?></label>
                <select id="actionTypeFilter" onchange="filterActivityLog()">
                    <option value=""><?= __('الكل') ?></option>
                    <?php foreach ($actionTypes as $key => $type): ?>
                        <option value="<?= $key ?>">
                            <?= $type['icon'] ?> <?= __($type['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label><?= __('🎯 نوع الهدف') ?></label>
                <select id="targetTypeFilter" onchange="filterActivityLog()">
                    <option value=""><?= __('الكل') ?></option>
                    <?php foreach ($targetTypes as $key => $label): ?>
                        <option value="<?= $key ?>">
                            <?= __($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label><?= __('📅 من تاريخ') ?></label>
                <input type="date" id="dateFromFilter" onchange="filterActivityLog()">
            </div>
            
            <div class="filter-group">
                <label><?= __('📅 إلى تاريخ') ?></label>
                <input type="date" id="dateToFilter" onchange="filterActivityLog()">
            </div>
            
            <div class="filter-group">
                <button type="button" class="btn btn-secondary" style="width: 100%; padding: 10px;" onclick="resetFilters()">
                    <?= __('🔄 إعادة تعيين') ?>
                </button>
            </div>
        </div>
        <div id="filterResultCount" style="margin-top: 10px; color: var(--text-muted); font-size: 0.9rem;"></div>
    </div>
    
    <!-- جدول السجل -->
    <?php if (empty($logs)): ?>
        <div class="empty-state">
            <div class="icon">📭</div>
            <h3><?= __('لا توجد عمليات') ?></h3>
            <p><?= __('لم يتم العثور على أي عمليات مطابقة للبحث') ?></p>
        </div>
    <?php else: ?>
        <table class="activity-table">
            <thead>
                <tr>
                    <th style="width: 60px;">#</th>
                    <th><?= __('المستخدم') ?></th>
                    <th><?= __('العملية') ?></th>
                    <th><?= __('الهدف') ?></th>
                    <th><?= __('التفاصيل') ?></th>
                    <th style="width: 140px;"><?= __('التاريخ') ?></th>
                </tr>
            </thead>
            <tbody id="activityTableBody">
                <?php foreach ($logs as $index => $log): ?>
                    <?php 
                    $actionInfo = $actionTypes[$log['action_type']] ?? $actionTypes['other'];
                    $roleClass = 'role-' . $log['user_role'];
                    $roleName = $roles[$log['user_role']] ?? $log['user_role'];
                    $targetTypeName = $targetTypes[$log['target_type']] ?? $log['target_type'];
                    ?>
                    <tr class="activity-row" 
                        data-action-type="<?= $log['action_type'] ?>"
                        data-target-type="<?= $log['target_type'] ?>"
                        data-date="<?= date('Y-m-d', strtotime($log['created_at'])) ?>"
                        data-search="<?= htmlspecialchars(strtolower($log['user_name'] . ' ' . $log['action'] . ' ' . ($log['target_name'] ?? ''))) ?>">
                        <td style="text-align: center; color: var(--text-muted);">
                            <?= $offset + $index + 1 ?>
                        </td>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar">
                                    <?= mb_substr($log['user_name'], 0, 1) ?>
                                </div>
                                <div>
                                    <strong><?= htmlspecialchars($log['user_name']) ?></strong>
                                    <br>
                                    <span class="role-badge <?= $roleClass ?>"><?= $roleName ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="action-badge" style="background: <?= $actionInfo['color'] ?>20; color: <?= $actionInfo['color'] ?>;">
                                <?= $actionInfo['icon'] ?>
                                <?= htmlspecialchars($log['action']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="target-info">
                                <?php if ($log['target_name']): ?>
                                    <span class="target-name"><?= htmlspecialchars($log['target_name']) ?></span>
                                <?php endif; ?>
                                <span class="target-type"><?= $targetTypeName ?></span>
                            </div>
                        </td>
                        <td style="max-width: 300px; font-size: 0.85rem;">
                            <?php 
                            // التحقق من وجود بيانات التغييرات
                            $oldValue = isset($log['old_value']) ? json_decode($log['old_value'], true) : null;
                            $newValue = isset($log['new_value']) ? json_decode($log['new_value'], true) : null;
                            $hasChanges = $oldValue || $newValue;
                            $logId = $log['id'];
                            ?>
                            
                            <?php if ($hasChanges): ?>
                                <button class="changes-toggle" onclick="toggleChanges(<?= $logId ?>)">
                                    👁️ عرض التفاصيل
                                </button>
                                <div class="changes-box" id="changes-<?= $logId ?>">
                                    <?php if ($log['action_type'] === 'add' && $newValue): ?>
                                        <div class="change-header">➕ البيانات المُضافة:</div>
                                        <?php foreach ($newValue as $key => $value): ?>
                                            <?php if (!empty($value)): ?>
                                            <div class="change-row">
                                                <span class="change-label"><?= htmlspecialchars($key) ?>:</span>
                                                <span class="change-value"><?= htmlspecialchars($value) ?></span>
                                            </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    
                                    <?php elseif ($log['action_type'] === 'edit' && $oldValue && $newValue): ?>
                                        <div class="change-header">✏️ التغييرات:</div>
                                        <?php foreach ($newValue as $key => $newVal): ?>
                                            <?php 
                                            $oldVal = $oldValue[$key] ?? '';
                                            if ($oldVal !== $newVal): ?>
                                            <div class="change-row">
                                                <span class="change-label"><?= htmlspecialchars($key) ?>:</span>
                                                <?php if (!empty($oldVal)): ?>
                                                    <span class="change-old"><?= htmlspecialchars($oldVal) ?></span>
                                                    <span class="change-arrow">←</span>
                                                <?php endif; ?>
                                                <span class="change-new"><?= htmlspecialchars($newVal) ?></span>
                                            </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    
                                    <?php elseif ($log['action_type'] === 'delete' && $oldValue): ?>
                                        <div class="change-header">🗑️ البيانات المحذوفة:</div>
                                        <?php foreach ($oldValue as $key => $value): ?>
                                            <?php if (!empty($value)): ?>
                                            <div class="change-row">
                                                <span class="change-label"><?= htmlspecialchars($key) ?>:</span>
                                                <span class="change-deleted"><?= htmlspecialchars($value) ?></span>
                                            </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($log['details']): ?>
                                <span style="color: var(--text-muted);"><?= htmlspecialchars(mb_substr($log['details'], 0, 100)) ?></span>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $timestamp = strtotime($log['created_at']);
                            ?>
                            <div class="time-display">
                                <span class="date"><?= date('Y/m/d', $timestamp) ?></span>
                                <span class="time"><?= date('h:i A', $timestamp) ?></span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- الترقيم -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">« السابق</a>
                <?php endif; ?>
                
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                
                if ($start > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                    <?php if ($start > 2): ?><span>...</span><?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?><span>...</span><?php endif; ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>"><?= $totalPages ?></a>
                <?php endif; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">التالي »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function toggleChanges(logId) {
    const box = document.getElementById('changes-' + logId);
    if (box) {
        box.classList.toggle('show');
    }
}

// ═══════════════════════════════════════════════════════════════
// 🔍 دالة الفلترة المباشرة لسجل العمليات
// ═══════════════════════════════════════════════════════════════
function filterActivityLog() {
    const searchInput = document.getElementById('searchInput');
    const actionTypeFilter = document.getElementById('actionTypeFilter');
    const targetTypeFilter = document.getElementById('targetTypeFilter');
    const dateFromFilter = document.getElementById('dateFromFilter');
    const dateToFilter = document.getElementById('dateToFilter');
    
    const searchValue = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const actionValue = actionTypeFilter ? actionTypeFilter.value : '';
    const targetValue = targetTypeFilter ? targetTypeFilter.value : '';
    const dateFrom = dateFromFilter ? dateFromFilter.value : '';
    const dateTo = dateToFilter ? dateToFilter.value : '';
    
    const rows = document.querySelectorAll('.activity-row');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const rowSearch = row.dataset.search || '';
        const rowActionType = row.dataset.actionType || '';
        const rowTargetType = row.dataset.targetType || '';
        const rowDate = row.dataset.date || '';
        
        let matchesSearch = !searchValue || rowSearch.includes(searchValue);
        let matchesAction = !actionValue || rowActionType === actionValue;
        let matchesTarget = !targetValue || rowTargetType === targetValue;
        let matchesDateFrom = !dateFrom || rowDate >= dateFrom;
        let matchesDateTo = !dateTo || rowDate <= dateTo;
        
        if (matchesSearch && matchesAction && matchesTarget && matchesDateFrom && matchesDateTo) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // عرض عدد النتائج
    const countEl = document.getElementById('filterResultCount');
    if (countEl) {
        if (searchValue || actionValue || targetValue || dateFrom || dateTo) {
            countEl.innerHTML = '📊 <strong>' + visibleCount + '</strong> نتيجة';
        } else {
            countEl.innerHTML = '';
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

function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('actionTypeFilter').value = '';
    document.getElementById('targetTypeFilter').value = '';
    document.getElementById('dateFromFilter').value = '';
    document.getElementById('dateToFilter').value = '';
    filterActivityLog();
}
</script>

<?php include 'views/components/footer.php'; ?>
