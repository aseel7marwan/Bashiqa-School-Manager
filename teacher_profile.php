<?php
/**
 * بطاقة المعلم/المدير الشخصية - Staff Profile
 * عرض جميع المعلومات الشخصية والوظيفية
 * 
 * @package SchoolManager
 * @access  معلم ومدير
 */

$pageTitle = 'بطاقتي الشخصية';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Teacher.php';
require_once __DIR__ . '/models/TeacherAttendance.php';
require_once __DIR__ . '/models/TeacherAssignment.php';

requireLogin();

// يجب أن يكون معلم أو مدير
if (isStudent()) {
    redirect('/student_profile.php');
}

$teacherModel = new Teacher();
$teacherAttendanceModel = new TeacherAttendance();
$currentUser = getCurrentUser();

// جلب بيانات المعلم (إن وجدت)
$teacher = $teacherModel->findByUserId($currentUser['id']);

// جلب إحصائيات الحضور الشهري والسنوي
$monthlyStats = ['present' => 0, 'late' => 0, 'absent' => 0, 'total' => 0];
$yearlyStats = ['present' => 0, 'late' => 0, 'absent' => 0, 'total' => 0];
$adminAbsences = [];

if ($teacher) {
    $currentMonth = date('n');
    $currentYear = date('Y');
    
    try {
        // إحصائيات الشهر الحالي
        $monthlyStats = $teacherAttendanceModel->getTeacherStats($teacher['id'], $currentMonth, $currentYear);
        
        // إحصائيات السنة الكاملة
        $conn = getConnection();
        $stmt = $conn->prepare("
            SELECT 
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                COUNT(*) as total
            FROM teacher_attendance 
            WHERE teacher_id = ? AND YEAR(date) = ?
        ");
        $stmt->execute([$teacher['id'], $currentYear]);
        $result = $stmt->fetch();
        if ($result) {
            $yearlyStats = [
                'present' => (int)($result['present'] ?? 0),
                'late' => (int)($result['late'] ?? 0),
                'absent' => (int)($result['absent'] ?? 0),
                'total' => (int)($result['total'] ?? 0)
            ];
        }
        
        // جلب الغيابات الإدارية للشهر الحالي
        $startDate = "$currentYear-" . str_pad($currentMonth, 2, '0', STR_PAD_LEFT) . "-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM teacher_absences 
            WHERE teacher_id = ? AND date >= ? AND date <= ?
        ");
        $stmt->execute([$teacher['id'], $startDate, $endDate]);
        $adminAbsences = $stmt->fetch();
    } catch (Exception $e) {
        // تجاهل الأخطاء
    }
}

// جلب تعيينات المعلم (المواد والصفوف المعينة له)
$teacherAssignments = [];
$assignmentModel = new TeacherAssignment();
if (isTeacher() || isAssistant()) {
    try {
        $teacherAssignments = $assignmentModel->getByTeacher($currentUser['id']);
    } catch (Exception $e) {
        // تجاهل الأخطاء
    }
}

require_once __DIR__ . '/views/components/header.php';
?>

<style>
.profile-container {
    max-width: 1000px;
    margin: 0 auto;
}
.profile-card {
    background: var(--bg-primary);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    margin-bottom: 1.5rem;
}
.profile-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 2.5rem 2rem;
    text-align: center;
    color: white;
    position: relative;
}
.profile-avatar {
    width: 120px;
    height: 120px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    margin: 0 auto 1rem;
    box-shadow: 0 8px 30px rgba(0,0,0,0.3);
    border: 4px solid rgba(255,255,255,0.3);
}
.profile-name {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}
.profile-specialty {
    opacity: 0.9;
    font-size: 1.1rem;
    margin-bottom: 1rem;
}
.profile-badges {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    flex-wrap: wrap;
}
.profile-badges .badge {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}
.profile-section {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border-color);
}
.profile-section:last-child {
    border-bottom: none;
}
.profile-section-title {
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
.profile-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.25rem;
}
.profile-item {
    background: var(--bg-secondary);
    padding: 1rem;
    border-radius: 10px;
    border-right: 3px solid var(--primary);
}
.profile-label {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-bottom: 0.35rem;
    font-weight: 500;
}
.profile-value {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.95rem;
}
.profile-value.empty {
    color: var(--text-muted);
    font-style: italic;
}
.quick-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    padding: 1.5rem 2rem;
    background: var(--bg-secondary);
}
.no-data-card {
    text-align: center;
    padding: 4rem 2rem;
}
.no-data-card .icon {
    font-size: 5rem;
    margin-bottom: 1rem;
}
.no-data-card h3 {
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}
.no-data-card p {
    color: var(--text-secondary);
    margin-bottom: 1.5rem;
}
@media (max-width: 992px) {
    .profile-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 576px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }
    .profile-header {
        padding: 2rem 1rem;
    }
    .profile-section {
        padding: 1.25rem 1rem;
    }
}
</style>

<div class="page-header d-flex justify-between align-center flex-wrap gap-2">
    <div>
        <h1><?= isAdmin() ? '👨‍💼' : '👨‍🏫' ?> بطاقتي الشخصية</h1>
        <p>جميع معلوماتك المسجلة في النظام</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($teacher): ?>
        <a href="/export_report.php?type=teacher_profile&teacher_id=<?= $teacher['id'] ?>&format=pdf" 
           target="_blank" class="btn btn-danger btn-sm">📄 PDF</a>
        <a href="/export_report.php?type=teacher_profile&teacher_id=<?= $teacher['id'] ?>&format=word" 
           class="btn btn-primary btn-sm">📝 Word</a>
        <?php endif; ?>
    </div>
</div>

<div class="profile-container">
    <?php if ($teacher): ?>
    <!-- ══════════════════════════════════════ -->
    <!-- بطاقة المعلم الكاملة -->
    <!-- ══════════════════════════════════════ -->
    <div class="profile-card fade-in">
        <div class="profile-header">
            <div class="profile-avatar"><?= isAdmin() ? '👨‍💼' : '👨‍🏫' ?></div>
            <div class="profile-name"><?= sanitize($teacher['full_name']) ?></div>
            <div class="profile-specialty"><?= sanitize($teacher['specialization'] ?? $teacher['certificate'] ?? 'معلم') ?></div>
            <div class="profile-badges">
                <?php if (isAdmin()): ?>
                <span class="badge badge-info">👨‍💼 مدير النظام</span>
                <?php else: ?>
                <span class="badge badge-secondary">👨‍🏫 معلم</span>
                <?php endif; ?>
                <span class="badge badge-success">✅ نشط</span>
            </div>
        </div>
        
        <!-- البيانات الشخصية الأساسية -->
        <div class="profile-section">
            <div class="profile-section-title">📋 البيانات الشخصية الأساسية</div>
            <div class="profile-grid">
                <div class="profile-item">
                    <div class="profile-label">الاسم الرباعي واللقب</div>
                    <div class="profile-value"><?= sanitize($teacher['full_name']) ?></div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">محل الولادة</div>
                    <div class="profile-value <?= empty($teacher['birth_place']) ? 'empty' : '' ?>">
                        <?= sanitize($teacher['birth_place'] ?? 'غير محدد') ?>
                    </div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">تاريخ الولادة</div>
                    <div class="profile-value <?= empty($teacher['birth_date']) ? 'empty' : '' ?>">
                        <?= $teacher['birth_date'] ? formatArabicDate($teacher['birth_date']) : 'غير محدد' ?>
                    </div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">اسم الام الثلاثي</div>
                    <div class="profile-value <?= empty($teacher['mother_name']) ? 'empty' : '' ?>">
                        <?= sanitize($teacher['mother_name'] ?? 'غير محدد') ?>
                    </div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">رقم الهاتف او الموبايل</div>
                    <div class="profile-value <?= empty($teacher['phone']) ? 'empty' : '' ?>">
                        <?= sanitize($teacher['phone'] ?? 'غير محدد') ?>
                    </div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">فصيلة الدم</div>
                    <div class="profile-value <?= empty($teacher['blood_type']) ? 'empty' : '' ?>">
                        <?= sanitize($teacher['blood_type'] ?? 'غير محدد') ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- الشهادة والتخصص -->
        <div class="profile-section">
            <div class="profile-section-title">🎓 الشهادة والتخصص</div>
            <div class="profile-grid">
                <div class="profile-item">
                    <div class="profile-label">الشهادة</div>
                    <div class="profile-value <?= empty($teacher['certificate']) ? 'empty' : '' ?>">
                        <?= sanitize($teacher['certificate'] ?? 'غير محدد') ?>
                    </div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">الاختصاص</div>
                    <div class="profile-value <?= empty($teacher['specialization']) ? 'empty' : '' ?>">
                        <?= sanitize($teacher['specialization'] ?? 'غير محدد') ?>
                    </div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">اسم المعهد او الكلية</div>
                    <div class="profile-value <?= empty($teacher['institute_name']) ? 'empty' : '' ?>">
                        <?= sanitize($teacher['institute_name'] ?? 'غير محدد') ?>
                    </div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">سنة التخرج</div>
                    <div class="profile-value <?= empty($teacher['graduation_year']) ? 'empty' : '' ?>">
                        <?= sanitize($teacher['graduation_year'] ?? 'غير محدد') ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- بيانات التعيين والوظيفة -->
        <div class="profile-section">
            <div class="profile-section-title">💼 بيانات التعيين والوظيفة</div>
            <div class="profile-grid">
                <div class="profile-item">
                    <div class="profile-label">تاريخ التعيين</div>
                    <div class="profile-value <?= empty($teacher['hire_date']) ? 'empty' : '' ?>">
                        <?= $teacher['hire_date'] ? formatArabicDate($teacher['hire_date']) : 'غير محدد' ?>
                    </div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">رقم الامر الاداري بالتعين</div>
                    <div class="profile-value <?= empty($teacher['hire_order_number']) ? 'empty' : '' ?>">
                        <?= sanitize($teacher['hire_order_number'] ?? 'غير محدد') ?>
                    </div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">الدرجة الوظيفية</div>
                    <div class="profile-value <?= empty($teacher['job_grade']) ? 'empty' : '' ?>">
                        <?= sanitize($teacher['job_grade'] ?? 'غير محدد') ?>
                    </div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">المرحلة في السلم الوظيفي</div>
                    <div class="profile-value <?= empty($teacher['career_stage']) ? 'empty' : '' ?>">
                        <?= sanitize($teacher['career_stage'] ?? 'غير محدد') ?>
                    </div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">تاريخ المباشرة بالمدرسة الحالية</div>
                    <div class="profile-value <?= empty($teacher['current_school_date']) ? 'empty' : '' ?>">
                        <?= $teacher['current_school_date'] ? formatArabicDate($teacher['current_school_date']) : 'غير محدد' ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- الوثائق والهويات -->
        <div class="profile-section">
            <div class="profile-section-title">🪪 الوثائق والهويات</div>
            <div class="profile-grid">
                <div class="profile-item">
                    <div class="profile-label">رقم البطاقة الوطنية</div>
                    <div class="profile-value <?= empty($teacher['national_id']) ? 'empty' : '' ?>">
                        <?= sanitize($teacher['national_id'] ?? 'غير محدد') ?>
                    </div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">رقم شهادة الجنسية العراقية</div>
                    <div class="profile-value <?= empty($teacher['nationality_cert_number']) ? 'empty' : '' ?>">
                        <?= sanitize($teacher['nationality_cert_number'] ?? 'غير محدد') ?>
                    </div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">رقم البطاقة التموينية</div>
                    <div class="profile-value <?= empty($teacher['ration_card_number']) ? 'empty' : '' ?>">
                        <?= sanitize($teacher['ration_card_number'] ?? 'غير محدد') ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- الحالة الاجتماعية -->
        <div class="profile-section">
            <div class="profile-section-title">💍 الحالة الاجتماعية</div>
            <div class="profile-grid">
                <div class="profile-item">
                    <div class="profile-label">الحالة الزوجية</div>
                    <div class="profile-value <?= empty($teacher['marital_status']) ? 'empty' : '' ?>">
                        <?= sanitize($teacher['marital_status'] ?? 'غير محدد') ?>
                    </div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">اسم الزوج/الزوجة</div>
                    <div class="profile-value <?= empty($teacher['spouse_name']) ? 'empty' : '' ?>">
                        <?= sanitize($teacher['spouse_name'] ?? 'غير محدد') ?>
                    </div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">العمل الوظيفي للزوج/الزوجة</div>
                    <div class="profile-value <?= empty($teacher['spouse_job']) ? 'empty' : '' ?>">
                        <?= sanitize($teacher['spouse_job'] ?? 'غير محدد') ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- معلومات الحساب -->
        <div class="profile-section">
            <div class="profile-section-title">🔑 معلومات الحساب</div>
            <div class="profile-grid">
                <div class="profile-item">
                    <div class="profile-label">اسم المستخدم</div>
                    <div class="profile-value"><code><?= sanitize($currentUser['username']) ?></code></div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">الصلاحية</div>
                    <div class="profile-value"><?= ROLES[$currentUser['role']] ?? $currentUser['role'] ?></div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">حالة الحساب</div>
                    <div class="profile-value"><span class="badge badge-success">نشط</span></div>
                </div>
            </div>
        </div>
        
        <!-- تعيينات المعلم (المواد والصفوف المعينة له) -->
        <?php if (isTeacher() && !empty($teacherAssignments)): ?>
        <div class="profile-section">
            <div class="profile-section-title">📚 المواد والصفوف المعينة لي</div>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 1rem;">
                    <thead>
                        <tr style="background: var(--bg-secondary);">
                            <th style="padding: 0.75rem; text-align: right; border: 1px solid var(--border);">#</th>
                            <th style="padding: 0.75rem; text-align: right; border: 1px solid var(--border);">الصف</th>
                            <th style="padding: 0.75rem; text-align: right; border: 1px solid var(--border);">الشعبة</th>
                            <th style="padding: 0.75rem; text-align: right; border: 1px solid var(--border);">المادة</th>
                            <th style="padding: 0.75rem; text-align: center; border: 1px solid var(--border);">رصد الدرجات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; foreach ($teacherAssignments as $assignment): ?>
                        <tr style="background: var(--bg-primary);">
                            <td style="padding: 0.75rem; border: 1px solid var(--border);"><?= $counter++ ?></td>
                            <td style="padding: 0.75rem; border: 1px solid var(--border);">
                                <?= CLASSES[$assignment['class_id']] ?? 'الصف ' . $assignment['class_id'] ?>
                            </td>
                            <td style="padding: 0.75rem; border: 1px solid var(--border);">
                                <span class="badge badge-secondary"><?= sanitize($assignment['section']) ?></span>
                            </td>
                            <td style="padding: 0.75rem; border: 1px solid var(--border); font-weight: 600;">
                                <?= sanitize($assignment['subject_name']) ?>
                            </td>
                            <td style="padding: 0.75rem; border: 1px solid var(--border); text-align: center;">
                                <?php if ($assignment['can_enter_grades']): ?>
                                <span class="badge badge-success">✅ مفعّل</span>
                                <?php else: ?>
                                <span class="badge badge-secondary">❌ غير مفعّل</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
                <div style="padding: 0.75rem 1.25rem; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 10px; color: white;">
                    <strong>📊 إجمالي التعيينات:</strong> <?= toArabicNum(count($teacherAssignments)) ?>
                </div>
                <a href="/grades.php" class="btn btn-primary">📝 إدخال الدرجات</a>
            </div>
        </div>
        <?php elseif (isTeacher()): ?>
        <div class="profile-section">
            <div class="profile-section-title">📚 المواد والصفوف المعينة لي</div>
            <div style="text-align: center; padding: 2rem; background: var(--bg-secondary); border-radius: 12px;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
                <h4 style="color: var(--text-secondary); margin-bottom: 0.5rem;">لا توجد تعيينات حالياً</h4>
                <p style="color: var(--text-muted);">تواصل مع المدير لتعيين المواد والصفوف لك</p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- إحصائيات الحضور الشهري والسنوي -->
        <div class="profile-section">
            <div class="profile-section-title">📊 سجل الحضور والغياب</div>
            
            <!-- الشهر الحالي -->
            <h4 style="margin-bottom: 1rem; color: var(--text-secondary); font-size: 0.95rem;">📅 الشهر الحالي (<?= date('F Y') ?>)</h4>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 2rem;">
                <div style="flex: 1; min-width: 120px; padding: 1.25rem; background: linear-gradient(135deg, #4caf50, #45a049); border-radius: 12px; text-align: center; color: white;">
                    <div style="font-size: 2rem; font-weight: bold;"><?= toArabicNum($monthlyStats['present']) ?></div>
                    <div style="opacity: 0.9; font-size: 0.9rem;">✅ حضور</div>
                </div>
                <div style="flex: 1; min-width: 120px; padding: 1.25rem; background: linear-gradient(135deg, #ffc107, #e0a800); border-radius: 12px; text-align: center; color: #333;">
                    <div style="font-size: 2rem; font-weight: bold;"><?= toArabicNum($monthlyStats['late']) ?></div>
                    <div style="opacity: 0.9; font-size: 0.9rem;">⏰ تأخير</div>
                </div>
                <div style="flex: 1; min-width: 120px; padding: 1.25rem; background: linear-gradient(135deg, #f44336, #d32f2f); border-radius: 12px; text-align: center; color: white;">
                    <div style="font-size: 2rem; font-weight: bold;"><?= toArabicNum($monthlyStats['absent']) ?></div>
                    <div style="opacity: 0.9; font-size: 0.9rem;">❌ غياب</div>
                </div>
                <div style="flex: 1; min-width: 120px; padding: 1.25rem; background: linear-gradient(135deg, #2196f3, #1976d2); border-radius: 12px; text-align: center; color: white;">
                    <div style="font-size: 2rem; font-weight: bold;"><?= toArabicNum($monthlyStats['total']) ?></div>
                    <div style="opacity: 0.9; font-size: 0.9rem;">📊 إجمالي</div>
                </div>
            </div>
            
            <!-- السنة الكاملة -->
            <h4 style="margin-bottom: 1rem; color: var(--text-secondary); font-size: 0.95rem;">📆 السنة الدراسية (<?= date('Y') ?>)</h4>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem;">
                <div style="flex: 1; min-width: 120px; padding: 1.25rem; background: #e8f5e9; border-radius: 12px; text-align: center; border: 2px solid #4caf50;">
                    <div style="font-size: 2rem; font-weight: bold; color: #2e7d32;"><?= toArabicNum($yearlyStats['present']) ?></div>
                    <div style="color: #666; font-size: 0.9rem;">✅ حضور سنوي</div>
                </div>
                <div style="flex: 1; min-width: 120px; padding: 1.25rem; background: #fff3e0; border-radius: 12px; text-align: center; border: 2px solid #ffc107;">
                    <div style="font-size: 2rem; font-weight: bold; color: #f57c00;"><?= toArabicNum($yearlyStats['late']) ?></div>
                    <div style="color: #666; font-size: 0.9rem;">⏰ تأخير سنوي</div>
                </div>
                <div style="flex: 1; min-width: 120px; padding: 1.25rem; background: #ffebee; border-radius: 12px; text-align: center; border: 2px solid #f44336;">
                    <div style="font-size: 2rem; font-weight: bold; color: #c62828;"><?= toArabicNum($yearlyStats['absent']) ?></div>
                    <div style="color: #666; font-size: 0.9rem;">❌ غياب سنوي</div>
                </div>
                <div style="flex: 1; min-width: 120px; padding: 1.25rem; background: #e3f2fd; border-radius: 12px; text-align: center; border: 2px solid #2196f3;">
                    <div style="font-size: 2rem; font-weight: bold; color: #1565c0;"><?= toArabicNum($yearlyStats['total']) ?></div>
                    <div style="color: #666; font-size: 0.9rem;">📊 إجمالي سنوي</div>
                </div>
            </div>
            
            <?php if (!empty($adminAbsences) && $adminAbsences['count'] > 0): ?>
            <div style="margin-top: 1rem; padding: 1rem; background: #fce4ec; border-radius: 8px; border-right: 4px solid #e91e63;">
                <strong style="color: #c2185b;">🚫 غيابات مسجلة من الإدارة هذا الشهر:</strong>
                <span style="font-size: 1.25rem; font-weight: bold; color: #c2185b;"><?= toArabicNum($adminAbsences['count']) ?></span>
            </div>
            <?php endif; ?>
            
            <div style="margin-top: 1.5rem; text-align: center;">
                <a href="/my_attendance.php" class="btn btn-primary">📋 عرض السجل التفصيلي</a>
            </div>
        </div>
        
        <!-- إجراءات سريعة -->
        <div class="quick-actions">
            <a href="/my_attendance.php" class="btn btn-info">📋 سجل حضور حصصي</a>
            <a href="/schedule.php" class="btn btn-secondary">📅 جدولي الدراسي</a>
            <a href="/attendance.php" class="btn btn-secondary">✅ تسجيل الحضور</a>
            <?php if (isAdmin()): ?>
            <a href="/teachers.php" class="btn btn-primary">👨‍🏫 إدارة المعلمين</a>
            <a href="/students.php" class="btn btn-secondary">👨‍🎓 إدارة التلاميذ</a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php else: ?>
    <!-- ══════════════════════════════════════ -->
    <!-- لا توجد بيانات -->
    <!-- ══════════════════════════════════════ -->
    <div class="profile-card fade-in">
        <div class="profile-header">
            <div class="profile-avatar"><?= isAdmin() ? '👨‍💼' : '👨‍🏫' ?></div>
            <div class="profile-name"><?= sanitize($currentUser['full_name']) ?></div>
            <div class="profile-specialty"><?= ROLES[$currentUser['role']] ?? $currentUser['role'] ?></div>
        </div>
        
        <div class="no-data-card">
            <div class="icon">📋</div>
            <h3>لا توجد بطاقة شخصية</h3>
            <p>
                <?php if (isAdmin()): ?>
                يمكنك إضافة بياناتك من صفحة <a href="/teachers.php">إدارة المعلمين</a>
                <?php else: ?>
                يرجى التواصل مع الإدارة لإضافة بياناتك الكاملة
                <?php endif; ?>
            </p>
            <?php if (isAdmin()): ?>
            <a href="/teachers.php?action=add" class="btn btn-primary">➕ إضافة بياناتي</a>
            <?php endif; ?>
        </div>
        
        <!-- معلومات الحساب الأساسية -->
        <div class="profile-section">
            <div class="profile-section-title">🔑 معلومات الحساب</div>
            <div class="profile-grid">
                <div class="profile-item">
                    <div class="profile-label">اسم المستخدم</div>
                    <div class="profile-value"><code><?= sanitize($currentUser['username']) ?></code></div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">الاسم الكامل</div>
                    <div class="profile-value"><?= sanitize($currentUser['full_name']) ?></div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">الصلاحية</div>
                    <div class="profile-value"><?= ROLES[$currentUser['role']] ?? $currentUser['role'] ?></div>
                </div>
            </div>
        </div>
        
        <!-- إجراءات سريعة -->
        <div class="quick-actions">
            <a href="/my_attendance.php" class="btn btn-info">📋 سجل حضور حصصي</a>
            <a href="/schedule.php" class="btn btn-secondary">📅 جدولي الدراسي</a>
            <?php if (isAdmin()): ?>
            <a href="/teachers.php" class="btn btn-primary">👨‍🏫 إدارة المعلمين</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
