<?php
/**
 * إدارة الصفوف والشعب - Classes Management
 * نظرة عامة على توزيع التلاميذ
 * 
 * @package SchoolManager
 * @access  مدير فقط
 */

$pageTitle = 'إدارة الصفوف والشعب';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/models/Student.php';

// التحقق من تسجيل الدخول أولاً
requireLogin();

if (!isAdmin()) {
    alert('ليس لديك صلاحية للوصول لهذه الصفحة', 'error');
    redirect('/dashboard.php');
}

$studentModel = new Student();
$studentsByClass = $studentModel->getCountByClass();

$classStats = [];
foreach ($studentsByClass as $row) {
    $classId = $row['class_id'];
    if (!isset($classStats[$classId])) {
        $classStats[$classId] = [
            'name' => CLASSES[$classId] ?? "الصف $classId",
            'sections' => [],
            'total' => 0
        ];
    }
    $classStats[$classId]['sections'][$row['section']] = $row['count'];
    $classStats[$classId]['total'] += $row['count'];
}

require_once __DIR__ . '/views/components/header.php';
?>

<div class="page-header">
    <h1>إدارة الصفوف والشعب</h1>
    <p>نظرة عامة على توزيع التلاميذ</p>
</div>

<div class="grid grid-3 mb-4">
    <?php foreach (CLASSES as $id => $name): ?>
    <div class="card fade-in">
        <div class="card-header">
            <h3>🏫 الصف <?= $name ?></h3>
        </div>
        <div class="card-body">
            <?php if (isset($classStats[$id])): ?>
            <div class="mb-3">
                <div style="font-size: 2rem; font-weight: 700; color: var(--primary);">
                    <?= $classStats[$id]['total'] ?>
                </div>
                <div class="text-muted">إجمالي التلاميذ</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php foreach (SECTIONS as $sec): ?>
                <div style="background: var(--bg-secondary); padding: 0.5rem 1rem; border-radius: var(--radius-sm); text-align: center; flex: 1; min-width: 60px;">
                    <div style="font-weight: 600;"><?= $classStats[$id]['sections'][$sec] ?? 0 ?></div>
                    <small class="text-muted">شعبة <?= $sec ?></small>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding: 1rem;">
                <p class="text-muted">لا يوجد تلاميذ</p>
            </div>
            <?php endif; ?>
            
            <div class="mt-3 d-flex gap-1">
                <a href="/students.php?class_id=<?= $id ?>" class="btn btn-secondary btn-sm" style="flex: 1;">
                    👨‍🎓 التلاميذ
                </a>
                <a href="/schedule.php?class_id=<?= $id ?>" class="btn btn-secondary btn-sm" style="flex: 1;">
                    📅 الجدول
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <h3>📊 ملخص إحصائي</h3>
    </div>
    <div class="card-body">
        <div class="grid grid-4">
            <div class="stat-card">
                <div class="stat-icon primary">🏫</div>
                <div class="stat-content">
                    <h3><?= count(CLASSES) ?></h3>
                    <p>عدد الصفوف</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon success">📋</div>
                <div class="stat-content">
                    <h3><?= count(CLASSES) * count(SECTIONS) ?></h3>
                    <p>إجمالي الشعب</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon warning">👨‍🎓</div>
                <div class="stat-content">
                    <h3><?= $studentModel->getTotalCount() ?></h3>
                    <p>إجمالي التلاميذ</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon info">📏</div>
                <div class="stat-content">
                    <?php 
                    $total = $studentModel->getTotalCount();
                    $sections = count(CLASSES) * count(SECTIONS);
                    $avg = $sections > 0 ? round($total / $sections, 1) : 0;
                    ?>
                    <h3><?= $avg ?></h3>
                    <p>متوسط تلاميذ/شعبة</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
