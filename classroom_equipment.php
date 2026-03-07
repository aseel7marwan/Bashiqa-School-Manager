<?php
/**
 * أثاث ومستلزمات الصفوف الدراسية
 * Classroom Equipment & Supplies Management
 * 
 * @package SchoolManager
 * @access  مدير ومعاون فقط
 */

$pageTitle = 'أثاث ومستلزمات الصفوف';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/models/ClassroomEquipment.php';

requireLogin();

// التحقق من الصلاحيات (مدير أو معاون فقط)
if (!isAdmin() && !isAssistant()) {
    alert('ليس لديك صلاحية للوصول لهذه الصفحة', 'error');
    redirect('dashboard.php');
}

$model = new ClassroomEquipment();
$currentLang = getLang();

// فلاتر
$filterClass = $_GET['class_id'] ?? '';
$filterSection = $_GET['section'] ?? '';

// الحصول على البيانات
if ($filterClass) {
    $equipment = $model->getByClass((int)$filterClass, $filterSection ?: null);
} else {
    $equipment = $model->getAll();
}

$stats = $model->getStatistics();
$classSummary = $model->getClassSummary();
$equipmentSummary = $model->getEquipmentSummary();

$action = $_GET['action'] ?? 'list';
$editItem = null;

if ($action === 'edit' && isset($_GET['id'])) {
    $editItem = $model->findById((int)$_GET['id']);
}

require_once __DIR__ . '/views/components/header.php';
?>

<style>
/* أنماط صفحة الأثاث */
.equipment-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.equipment-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 1.5rem;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.equipment-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.equipment-card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.equipment-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--primary) 0%, #6366f1 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.equipment-title {
    font-weight: 700;
    color: var(--text-primary);
    font-size: 1.1rem;
}

.equipment-subtitle {
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.equipment-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.equipment-detail {
    display: flex;
    flex-direction: column;
}

.equipment-detail-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-bottom: 0.25rem;
}

.equipment-detail-value {
    font-weight: 600;
    color: var(--text-primary);
}

.condition-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.condition-new { background: #dcfce7; color: #166534; }
.condition-good { background: #dbeafe; color: #1e40af; }
.condition-fair { background: #fef3c7; color: #92400e; }
.condition-poor { background: #fed7aa; color: #c2410c; }
.condition-damaged { background: #fee2e2; color: #991b1b; }

.equipment-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
}

/* بطاقات الإحصائيات */
.stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-overview-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    border: 1px solid var(--border-color);
}

.stat-overview-icon {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.stat-overview-value {
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary);
}

.stat-overview-label {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

/* جدول ملخص الصفوف */
.summary-table {
    width: 100%;
    border-collapse: collapse;
}

.summary-table th,
.summary-table td {
    padding: 1rem;
    border: 1px solid var(--border-color);
    text-align: center;
}

.summary-table th {
    background: var(--bg-secondary);
    font-weight: 600;
}

.summary-table tbody tr:hover {
    background: var(--bg-hover);
}

/* نموذج الإضافة */
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

/* التبويبات */
.tabs-container {
    margin-bottom: 2rem;
}

.tabs {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 0;
}

.tab-btn {
    padding: 0.75rem 1.5rem;
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-secondary);
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s;
    font-family: inherit;
}

.tab-btn:hover {
    color: var(--primary);
}

.tab-btn.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}

.tab-content {
    display: none;
    padding-top: 1.5rem;
}

.tab-content.active {
    display: block;
}

@media (max-width: 768px) {
    .equipment-grid {
        grid-template-columns: 1fr;
    }
    
    .equipment-details {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page-header d-flex justify-between align-center flex-wrap gap-2">
    <div>
        <h1>🪑 <?= __('أثاث ومستلزمات الصفوف') ?></h1>
        <p><?= __('إدارة الأثاث والتجهيزات لكل صف دراسي') ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">➕ <?= __('إضافة عنصر') ?></a>
        <?php else: ?>
        <a href="classroom_equipment.php" class="btn btn-secondary">← <?= __('العودة للقائمة') ?></a>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- نموذج الإضافة/التعديل -->
<div class="card mb-3 fade-in">
    <div class="card-header">
        <h3><?= $action === 'add' ? '➕ ' . __('إضافة عنصر جديد') : '✏️ ' . __('تعديل العنصر') ?></h3>
    </div>
    <div class="card-body">
        <form action="controllers/equipment_handler.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="<?= $action ?>">
            <?php if ($editItem): ?>
            <input type="hidden" name="id" value="<?= $editItem['id'] ?>">
            <?php endif; ?>
            
            <div class="form-section">
                <div class="form-section-title">📍 <?= __('موقع العنصر') ?></div>
                <div class="grid grid-3">
                    <div class="form-group">
                        <label><span class="required">*</span> <?= __('الصف') ?></label>
                        <select name="class_id" class="form-control" required>
                            <option value=""><?= __('-- اختر الصف --') ?></option>
                            <?php foreach (CLASSES as $id => $name): ?>
                            <option value="<?= $id ?>" <?= ($editItem['class_id'] ?? '') == $id ? 'selected' : '' ?>>
                                <?= $name ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= __('الشعبة') ?> (<?= __('اختياري') ?>)</label>
                        <select name="section" class="form-control">
                            <option value=""><?= __('جميع الشعب') ?></option>
                            <?php foreach (SECTIONS as $sec): ?>
                            <option value="<?= $sec ?>" <?= ($editItem['section'] ?? '') === $sec ? 'selected' : '' ?>>
                                <?= $sec ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <div class="form-section-title">🪑 <?= __('تفاصيل العنصر') ?></div>
                <div class="grid grid-3">
                    <div class="form-group">
                        <label><span class="required">*</span> <?= __('نوع العنصر') ?></label>
                        <select name="equipment_type" class="form-control" required>
                            <option value=""><?= __('-- اختر النوع --') ?></option>
                            <?php foreach (ClassroomEquipment::$equipmentTypes as $type => $info): ?>
                            <option value="<?= $type ?>" <?= ($editItem['equipment_type'] ?? '') === $type ? 'selected' : '' ?>>
                                <?= $info['icon'] ?> <?= $info[$currentLang] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= __('اسم مخصص') ?> (<?= __('اختياري') ?>)</label>
                        <input type="text" name="custom_name" class="form-control"
                               value="<?= sanitize($editItem['custom_name'] ?? '') ?>"
                               placeholder="<?= __('مثال: رحلة خشبية كبيرة') ?>">
                    </div>
                    <div class="form-group">
                        <label><span class="required">*</span> <?= __('الكمية') ?></label>
                        <input type="number" name="quantity" class="form-control" required min="1"
                               value="<?= $editItem['quantity'] ?? 1 ?>">
                    </div>
                    <div class="form-group">
                        <label><span class="required">*</span> <?= __('الحالة') ?></label>
                        <select name="condition" class="form-control" required>
                            <?php foreach (ClassroomEquipment::$conditions as $cond => $info): ?>
                            <option value="<?= $cond ?>" <?= ($editItem['condition'] ?? 'good') === $cond ? 'selected' : '' ?>>
                                <?= $info[$currentLang] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label><?= __('ملاحظات') ?></label>
                    <textarea name="notes" class="form-control" rows="2"
                              placeholder="<?= __('أي ملاحظات إضافية') ?>"><?= sanitize($editItem['notes'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">💾 <?= __('حفظ') ?></button>
                <a href="classroom_equipment.php" class="btn btn-secondary"><?= __('إلغاء') ?></a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>

<!-- الإحصائيات -->
<div class="stats-overview fade-in">
    <div class="stat-overview-card">
        <div class="stat-overview-icon">📦</div>
        <div class="stat-overview-value"><?= number_format($stats['total_items']) ?></div>
        <div class="stat-overview-label"><?= __('إجمالي العناصر') ?></div>
    </div>
    <div class="stat-overview-card">
        <div class="stat-overview-icon">🆕</div>
        <div class="stat-overview-value"><?= number_format($stats['new']) ?></div>
        <div class="stat-overview-label"><?= __('عناصر جديدة') ?></div>
    </div>
    <div class="stat-overview-card">
        <div class="stat-overview-icon">⚠️</div>
        <div class="stat-overview-value"><?= number_format($stats['damaged']) ?></div>
        <div class="stat-overview-label"><?= __('عناصر تالفة') ?></div>
    </div>
    <div class="stat-overview-card">
        <div class="stat-overview-icon">📊</div>
        <div class="stat-overview-value"><?= $stats['types'] ?></div>
        <div class="stat-overview-label"><?= __('أنواع المعدات') ?></div>
    </div>
</div>

<!-- الفلاتر -->
<div class="card mb-3 fade-in">
    <div class="card-body">
        <form method="GET" class="d-flex gap-2 flex-wrap align-center" id="equipmentFilterForm">
            <div class="form-group" style="margin: 0; min-width: 150px;">
                <label style="font-size: 0.85rem;"><?= __('الصف') ?></label>
                <select name="class_id" id="equipClassId" class="form-control">
                    <option value=""><?= __('جميع الصفوف') ?></option>
                    <?php foreach (CLASSES as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $filterClass == $id ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filterClass): ?>
            <div class="form-group" style="margin: 0; min-width: 120px;">
                <label style="font-size: 0.85rem;"><?= __('الشعبة') ?></label>
                <select name="section" id="equipSection" class="form-control">
                    <option value=""><?= __('جميع الشعب') ?></option>
                    <?php foreach (SECTIONS as $sec): ?>
                    <option value="<?= $sec ?>" <?= $filterSection === $sec ? 'selected' : '' ?>><?= $sec ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group" style="margin: 0; display: none; align-items: flex-end;">
                <button type="button" id="loadEquipmentBtn" class="btn btn-primary btn-sm">🔄 تحميل</button>
            </div>
            <?php if ($filterClass || $filterSection): ?>
            <a href="classroom_equipment.php" class="btn btn-secondary btn-sm" style="margin-top: 1.5rem;">
                ✖ <?= __('إزالة الفلاتر') ?>
            </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- التبويبات -->
<div class="tabs-container">
    <div class="tabs">
        <button class="tab-btn active" onclick="showTab('items')">🪑 <?= __('العناصر') ?></button>
        <button class="tab-btn" onclick="showTab('summary')">📊 <?= __('ملخص الصفوف') ?></button>
        <button class="tab-btn" onclick="showTab('types')">📋 <?= __('ملخص الأنواع') ?></button>
    </div>
    
    <!-- تبويب العناصر -->
    <div id="tab-items" class="tab-content active">
        <?php if (empty($equipment)): ?>
        <div class="empty-state">
            <div class="icon">🪑</div>
            <h3><?= __('لا توجد عناصر') ?></h3>
            <p><?= __('لم يتم إضافة أي أثاث أو مستلزمات بعد') ?></p>
            <a href="?action=add" class="btn btn-primary">➕ <?= __('إضافة عنصر') ?></a>
        </div>
        <?php else: ?>
        <div class="equipment-grid">
            <?php foreach ($equipment as $item): ?>
            <div class="equipment-card">
                <div class="equipment-card-header">
                    <div class="equipment-icon">
                        <?= ClassroomEquipment::getTypeIcon($item['equipment_type']) ?>
                    </div>
                    <div>
                        <div class="equipment-title">
                            <?= $item['custom_name'] ?: ClassroomEquipment::getTypeName($item['equipment_type'], $currentLang) ?>
                        </div>
                        <div class="equipment-subtitle">
                            <?= CLASSES[$item['class_id']] ?? $item['class_id'] ?>
                            <?= $item['section'] ? ' - ' . __('شعبة') . ' ' . $item['section'] : '' ?>
                        </div>
                    </div>
                </div>
                
                <div class="equipment-details">
                    <div class="equipment-detail">
                        <span class="equipment-detail-label"><?= __('الكمية') ?></span>
                        <span class="equipment-detail-value"><?= $item['quantity'] ?></span>
                    </div>
                    <div class="equipment-detail">
                        <span class="equipment-detail-label"><?= __('الحالة') ?></span>
                        <span class="condition-badge condition-<?= $item['condition'] ?>">
                            <?= ClassroomEquipment::getConditionName($item['condition'], $currentLang) ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($item['notes']): ?>
                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">
                    📝 <?= sanitize($item['notes']) ?>
                </div>
                <?php endif; ?>
                
                <div class="equipment-actions">
                    <a href="?action=edit&id=<?= $item['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
                    <form action="controllers/equipment_handler.php" method="POST" style="display: inline;"
                          onsubmit="return confirm('<?= __('هل تريد حذف هذا العنصر؟') ?>')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- تبويب ملخص الصفوف -->
    <div id="tab-summary" class="tab-content">
        <div class="card">
            <div class="card-header">
                <h3>📊 <?= __('ملخص الأثاث حسب الصف') ?></h3>
            </div>
            <div class="card-body">
                <?php if (empty($classSummary)): ?>
                <p class="text-muted text-center"><?= __('لا توجد بيانات') ?></p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th><?= __('الصف') ?></th>
                                <th><?= __('الشعبة') ?></th>
                                <th><?= __('عدد الأنواع') ?></th>
                                <th><?= __('إجمالي العناصر') ?></th>
                                <th><?= __('التالف') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classSummary as $row): ?>
                            <tr>
                                <td><?= CLASSES[$row['class_id']] ?? $row['class_id'] ?></td>
                                <td><?= $row['section'] ?: __('جميع الشعب') ?></td>
                                <td><?= $row['item_types'] ?></td>
                                <td><strong><?= $row['total_quantity'] ?></strong></td>
                                <td>
                                    <?php if ($row['damaged_count'] > 0): ?>
                                    <span class="badge badge-danger"><?= $row['damaged_count'] ?></span>
                                    <?php else: ?>
                                    <span class="badge badge-success">0</span>
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
    </div>
    
    <!-- تبويب ملخص الأنواع -->
    <div id="tab-types" class="tab-content">
        <div class="card">
            <div class="card-header">
                <h3>📋 <?= __('ملخص حسب نوع المعدات') ?></h3>
            </div>
            <div class="card-body">
                <?php if (empty($equipmentSummary)): ?>
                <p class="text-muted text-center"><?= __('لا توجد بيانات') ?></p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th><?= __('النوع') ?></th>
                                <th><?= __('الإجمالي') ?></th>
                                <th><?= __('جديد') ?></th>
                                <th><?= __('جيد') ?></th>
                                <th><?= __('متوسط') ?></th>
                                <th><?= __('سيء') ?></th>
                                <th><?= __('تالف') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($equipmentSummary as $row): ?>
                            <tr>
                                <td>
                                    <?= ClassroomEquipment::getTypeIcon($row['equipment_type']) ?>
                                    <?= ClassroomEquipment::getTypeName($row['equipment_type'], $currentLang) ?>
                                </td>
                                <td><strong><?= $row['total_quantity'] ?></strong></td>
                                <td><?= $row['new_count'] ?></td>
                                <td><?= $row['good_count'] ?></td>
                                <td><?= $row['fair_count'] ?></td>
                                <td><?= $row['poor_count'] ?></td>
                                <td>
                                    <?php if ($row['damaged_count'] > 0): ?>
                                    <span class="badge badge-danger"><?= $row['damaged_count'] ?></span>
                                    <?php else: ?>
                                    0
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
    </div>
</div>

<?php endif; ?>

<script>
function showTab(tabId) {
    // إخفاء جميع التبويبات
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    
    // إظهار التبويب المختار
    document.getElementById('tab-' + tabId).classList.add('active');
    event.target.classList.add('active');
}

// تحميل البيانات عبر AJAX
function loadEquipmentData() {
    const classId = document.getElementById('equipClassId')?.value || '';
    const section = document.getElementById('equipSection')?.value || '';
    
    const url = new URL(window.location);
    if (classId) url.searchParams.set('class_id', classId);
    else url.searchParams.delete('class_id');
    if (section) url.searchParams.set('section', section);
    else url.searchParams.delete('section');
    
    window.history.pushState({}, '', url);
    
    const btn = document.getElementById('loadEquipmentBtn');
    if (btn) {
        btn.innerHTML = '⏳';
        btn.disabled = true;
    }
    
    fetch(url.href, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.text())
    .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // تحديث الإحصائيات والمحتوى
        const newContent = doc.querySelector('.tabs-container');
        const currentContent = document.querySelector('.tabs-container');
        if (newContent && currentContent) {
            currentContent.innerHTML = newContent.innerHTML;
        }
        
        if (window.UI && window.UI.success) {
            window.UI.success('تم تحميل البيانات ✓');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.location.href = url.href;
    })
    .finally(() => {
        if (btn) {
            btn.innerHTML = '🔄 تحميل';
            btn.disabled = false;
        }
    });
}

// ربط الأحداث
const loadEquipBtn = document.getElementById('loadEquipmentBtn');
if (loadEquipBtn) {
    loadEquipBtn.addEventListener('click', loadEquipmentData);
}

['equipClassId', 'equipSection'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('change', loadEquipmentData);
    }
});
</script>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
