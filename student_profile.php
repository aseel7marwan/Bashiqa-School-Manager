<?php
/**
 * بطاقة التلميذ الشخصية - Student Profile
 * تعرض للطالب معلوماته الأساسية فقط (بدون بيانات الوالدين الحساسة)
 * 
 * @package SchoolManager
 * @access  تلميذ فقط (يرى بياناته الأساسية فقط)
 */

$pageTitle = 'بطاقتي الشخصية';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Student.php';

requireLogin();

if (!isStudent()) {
    alert('ليس لديك صلاحية للوصول لهذه الصفحة', 'error');
    redirect('/dashboard.php');
}

$studentModel = new Student();
$currentUser = getCurrentUser();

// Get student info linked to this user account
$myInfo = $studentModel->findByUserId($currentUser['id']);

// جلب إحصائيات الحضور للطالب
$attendanceStats = ['present' => 0, 'late' => 0, 'absent' => 0, 'total' => 0];
if ($myInfo) {
    try {
        $conn = getConnection();
        $currentMonth = date('n');
        $currentYear = date('Y');
        
        $stmt = $conn->prepare("
            SELECT 
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                COUNT(*) as total
            FROM attendance 
            WHERE student_id = ? AND MONTH(date) = ? AND YEAR(date) = ?
        ");
        $stmt->execute([$myInfo['id'], $currentMonth, $currentYear]);
        $result = $stmt->fetch();
        if ($result) {
            $attendanceStats = [
                'present' => (int)($result['present'] ?? 0),
                'late' => (int)($result['late'] ?? 0),
                'absent' => (int)($result['absent'] ?? 0),
                'total' => (int)($result['total'] ?? 0)
            ];
        }
    } catch (Exception $e) {
        // تجاهل الأخطاء
    }
}

require_once __DIR__ . '/views/components/header.php';
?>

<style>
.student-profile-container {
    max-width: 900px;
    margin: 0 auto;
}
.student-profile-card {
    background: var(--bg-primary);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    margin-bottom: 1.5rem;
}
.student-profile-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 2.5rem;
    text-align: center;
    color: white;
}
.student-avatar {
    width: 120px;
    height: 120px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    margin: 0 auto 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    border: 4px solid rgba(255,255,255,0.3);
    overflow: hidden;
}
.student-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.student-name {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}
.student-class-badge {
    display: inline-block;
    background: rgba(255,255,255,0.2);
    padding: 0.5rem 1.5rem;
    border-radius: 30px;
    font-size: 1rem;
}
.student-profile-section {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border-color);
}
.student-profile-section:last-child {
    border-bottom: none;
}
.section-title {
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 1.25rem;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--primary);
}
.info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}
@media (max-width: 768px) {
    .info-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 480px) {
    .info-grid { grid-template-columns: 1fr; }
}
.info-item {
    background: var(--bg-secondary);
    padding: 1rem;
    border-radius: 10px;
    border-right: 3px solid var(--primary);
}
.info-label {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-bottom: 0.35rem;
}
.info-value {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 1rem;
}
.attendance-stats {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}
.stat-card {
    flex: 1;
    min-width: 100px;
    padding: 1.25rem;
    border-radius: 12px;
    text-align: center;
    color: white;
}
.stat-card.present { background: linear-gradient(135deg, #4caf50, #45a049); }
.stat-card.late { background: linear-gradient(135deg, #ffc107, #e0a800); color: #333; }
.stat-card.absent { background: linear-gradient(135deg, #f44336, #d32f2f); }
.stat-card.total { background: linear-gradient(135deg, #2196f3, #1976d2); }
.stat-value { font-size: 2rem; font-weight: bold; }
.stat-label { opacity: 0.9; font-size: 0.85rem; margin-top: 0.25rem; }
.quick-links {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    padding: 1.5rem 2rem;
    background: var(--bg-secondary);
}
.quick-link {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    background: var(--bg-primary);
    border-radius: 12px;
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.3s;
    border: 1px solid var(--border-color);
}
.quick-link:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
    border-color: var(--primary);
}
.quick-link .icon {
    font-size: 2rem;
}
.quick-link-text {
    font-weight: 600;
}
.quick-link-desc {
    font-size: 0.8rem;
    color: var(--text-muted);
}
</style>

<div class="page-header">
    <h1>👤 بطاقتي الشخصية</h1>
    <p class="subtitle">مرحباً بك في ملفك الشخصي</p>
</div>

<?= showAlert() ?>

<div class="student-profile-container">
    <?php if ($myInfo): ?>
    <div class="student-profile-card fade-in">
        <!-- الهيدر -->
        <div class="student-profile-header">
            <div class="student-avatar">
                <?php if ($myInfo['photo']): ?>
                <img src="/uploads/students/<?= htmlspecialchars($myInfo['photo']) ?>" alt="صورتي">
                <?php else: ?>
                <?= $myInfo['gender'] === 'male' ? '👦' : '👧' ?>
                <?php endif; ?>
            </div>
            <div class="student-name"><?= htmlspecialchars($myInfo['full_name']) ?></div>
            <div class="student-class-badge">
                🏫 <?= CLASSES[$myInfo['class_id']] ?? $myInfo['class_id'] ?> - <?= $myInfo['section'] ?>
            </div>
        </div>
        
        <!-- البيانات الأساسية (فقط ما يهم الطالب) -->
        <div class="student-profile-section">
            <div class="section-title">📋 معلوماتي الأساسية</div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">الاسم الكامل</div>
                    <div class="info-value"><?= htmlspecialchars($myInfo['full_name']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">الصف الدراسي</div>
                    <div class="info-value"><?= CLASSES[$myInfo['class_id']] ?? 'غير محدد' ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">الشعبة</div>
                    <div class="info-value"><?= htmlspecialchars($myInfo['section']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">الجنس</div>
                    <div class="info-value"><?= $myInfo['gender'] === 'male' ? '👦 ذكر' : '👧 أنثى' ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">تاريخ الميلاد</div>
                    <div class="info-value"><?= $myInfo['birth_date'] ? formatArabicDate($myInfo['birth_date']) : 'غير محدد' ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">تاريخ التسجيل</div>
                    <div class="info-value"><?= $myInfo['enrollment_date'] ? formatArabicDate($myInfo['enrollment_date']) : 'غير محدد' ?></div>
                </div>
            </div>
        </div>
        
        <!-- إحصائيات الحضور الشهري -->
        <div class="student-profile-section">
            <div class="section-title">📊 حضوري هذا الشهر (<?= date('F Y') ?>)</div>
            <div class="attendance-stats">
                <div class="stat-card present">
                    <div class="stat-value"><?= toArabicNum($attendanceStats['present']) ?></div>
                    <div class="stat-label">✅ حضور</div>
                </div>
                <div class="stat-card late">
                    <div class="stat-value"><?= toArabicNum($attendanceStats['late']) ?></div>
                    <div class="stat-label">⏰ تأخير</div>
                </div>
                <div class="stat-card absent">
                    <div class="stat-value"><?= toArabicNum($attendanceStats['absent']) ?></div>
                    <div class="stat-label">❌ غياب</div>
                </div>
                <div class="stat-card total">
                    <div class="stat-value"><?= toArabicNum($attendanceStats['total']) ?></div>
                    <div class="stat-label">📊 إجمالي</div>
                </div>
            </div>
        </div>
        
        <!-- روابط سريعة -->
        <div class="quick-links">
            <a href="/student_attendance.php" class="quick-link">
                <span class="icon">📋</span>
                <div>
                    <div class="quick-link-text">سجل الحضور والغياب</div>
                    <div class="quick-link-desc">عرض سجلك الكامل</div>
                </div>
            </a>
            <a href="/schedule.php" class="quick-link">
                <span class="icon">📅</span>
                <div>
                    <div class="quick-link-text">جدول الحصص</div>
                    <div class="quick-link-desc">جدولك الدراسي الأسبوعي</div>
                </div>
            </a>
            <a href="/events.php" class="quick-link">
                <span class="icon">🎉</span>
                <div>
                    <div class="quick-link-text">الأحداث والعطل</div>
                    <div class="quick-link-desc">مناسبات المدرسة</div>
                </div>
            </a>
        </div>
    </div>
    
    <?php else: ?>
    <div class="student-profile-card">
        <div style="padding: 4rem; text-align: center;">
            <div style="font-size: 5rem; margin-bottom: 1rem;">😕</div>
            <h3>لم يتم العثور على بياناتك</h3>
            <p style="color: #666;">يرجى التواصل مع إدارة المدرسة لربط حسابك ببياناتك الشخصية.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
