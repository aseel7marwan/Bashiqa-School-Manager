<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/translations.php';

requireLogin();
$currentUser = getCurrentUser();
$theme = getUserTheme();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$lang = getLang();
$dir = getDirection();
$baseUrl = getBaseUrl();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.9, minimum-scale=0.5, maximum-scale=3.0, user-scalable=yes">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?= $pageTitle ?? SITE_NAME ?></title>
    <?php $version = '20241220v4'; // تغيير هذا الرقم يجبر المتصفح على تحميل الملف الجديد ?>
    <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/main.css?v=<?= $version ?>">
    <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/layout.css?v=<?= $version ?>">
    <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/attendance.css?v=<?= $version ?>">
    <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/ajax.css?v=<?= $version ?>">
    <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/search.css?v=<?= $version ?>">
    <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/lazy.css?v=<?= $version ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/print.css?v=<?= $version ?>" media="print">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">
    <meta name="base-url" content="<?= $baseUrl ?>">
    <meta name="user-role" content="<?= getCurrentUser()['role'] ?? 'student' ?>">
</head>
<?php
// تحديد حالة القائمة الجانبية مسبقاً لتجنب الوميض
// على الموبايل: مغلقة دائماً
// على الديسكتوب: مفتوحة افتراضياً (إلا إذا حفظها المستخدم مغلقة)
$sidebarCollapsed = true; // افتراضي: مغلقة (للموبايل)
?>
<body class="sidebar-collapsed" id="appBody">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="app-layout">
        <aside class="sidebar collapsed no-transition" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo"><i class="fas fa-school"></i></div>
                <div class="sidebar-brand">
                    <h1><?= __('مدرسة بعشيقة') ?></h1>
                    <small><?= __('الابتدائية للبنين') ?></small>
                </div>
            </div>
            
            <nav class="sidebar-nav" id="sidebarNav">
                
                <?php if (isStudent()): ?>
                <!-- ═══════════════ قائمة التلميذ ═══════════════ -->
                
                <a href="<?= $baseUrl ?>dashboard" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-home"></i></span>
                    <span><?= __('الرئيسية') ?></span>
                </a>
                
                <div class="nav-section">
                    <span class="nav-section-title"><i class="fas fa-folder"></i> <?= __('ملفي') ?></span>
                </div>
                <a href="<?= $baseUrl ?>student_profile" class="nav-item <?= $currentPage === 'student_profile' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-id-card"></i></span>
                    <span><?= __('البطاقة المدرسية') ?></span>
                </a>
                <a href="<?= $baseUrl ?>student_attendance" class="nav-item <?= $currentPage === 'student_attendance' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-chart-bar"></i></span>
                    <span><?= __('سجل الغياب') ?></span>
                </a>
                <a href="<?= $baseUrl ?>grades_report" class="nav-item <?= $currentPage === 'grades_report' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-file-alt"></i></span>
                    <span><?= __('كشف الدرجات') ?></span>
                </a>
                <a href="<?= $baseUrl ?>my_leaves" class="nav-item <?= $currentPage === 'my_leaves' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-calendar-check"></i></span>
                    <span><?= __('الإجازات') ?></span>
                </a>
                
                <div class="nav-section">
                    <span class="nav-section-title"><i class="fas fa-school"></i> <?= __('المدرسة') ?></span>
                </div>
                <a href="<?= $baseUrl ?>schedule" class="nav-item <?= $currentPage === 'schedule' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-table"></i></span>
                    <span><?= __('الجدول الدراسي') ?></span>
                </a>
                <a href="<?= $baseUrl ?>events" class="nav-item <?= $currentPage === 'events' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-calendar-alt"></i></span>
                    <span><?= __('التقويم') ?></span>
                </a>
                
                <?php else: ?>
                <!-- ═══════════════ قائمة المعلم/المدير ═══════════════ -->
                
                <a href="<?= $baseUrl ?>dashboard" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-home"></i></span>
                    <span><?= __('الصفحة الرئيسية') ?></span>
                </a>
                
                <!-- ═══ المتابعة اليومية ═══ -->
                <div class="nav-section">
                    <span class="nav-section-title"><i class="fas fa-calendar-day"></i> <?= __('المتابعة اليومية') ?></span>
                </div>
                <a href="<?= $baseUrl ?>attendance" class="nav-item <?= $currentPage === 'attendance' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-user-check"></i></span>
                    <span><?= __('تسجيل حضور التلاميذ') ?></span>
                </a>
                <a href="<?= $baseUrl ?>schedule" class="nav-item <?= $currentPage === 'schedule' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-table"></i></span>
                    <span><?= __('الجدول الأسبوعي') ?></span>
                </a>
                <a href="<?= $baseUrl ?>events" class="nav-item <?= $currentPage === 'events' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-calendar-alt"></i></span>
                    <span><?= __('التقويم والمناسبات') ?></span>
                </a>
                
                <!-- ═══ شؤون التلاميذ ═══ -->
                <div class="nav-section">
                    <span class="nav-section-title"><i class="fas fa-user-graduate"></i> <?= __('شؤون التلاميذ') ?></span>
                </div>
                <a href="<?= $baseUrl ?>students" class="nav-item <?= $currentPage === 'students' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-id-card"></i></span>
                    <span><?= __('البطاقات المدرسية') ?></span>
                </a>
                <?php if (isTeacher() || isAdmin() || isAssistant()): ?>
                <a href="<?= $baseUrl ?>grades" class="nav-item <?= $currentPage === 'grades' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-edit"></i></span>
                    <span><?= __('رصد الدرجات') ?></span>
                </a>
                <?php endif; ?>
                <a href="<?= $baseUrl ?>grades_report" class="nav-item <?= $currentPage === 'grades_report' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-chart-bar"></i></span>
                    <span><?= __('كشف نتائج الدرجات') ?></span>
                </a>
                <a href="<?= $baseUrl ?>reports" class="nav-item <?= $currentPage === 'reports' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-chart-line"></i></span>
                    <span><?= __('تقارير الحضور والغياب') ?></span>
                </a>
                <a href="<?= $baseUrl ?>leaves" class="nav-item <?= $currentPage === 'leaves' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-file-alt"></i></span>
                    <span><?= __('إجازات التلاميذ') ?></span>
                </a>
                <?php if (isAdmin()): ?>
                <a href="<?= $baseUrl ?>student_users" class="nav-item <?= $currentPage === 'student_users' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-key"></i></span>
                    <span><?= __('حسابات دخول التلاميذ') ?></span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin()): ?>
                <!-- ═══ شؤون المعلمين ═══ -->
                <div class="nav-section">
                    <span class="nav-section-title"><i class="fas fa-chalkboard-teacher"></i> <?= __('شؤون المعلمين') ?></span>
                </div>
                <a href="<?= $baseUrl ?>teachers" class="nav-item <?= $currentPage === 'teachers' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-folder-open"></i></span>
                    <span><?= __('ملفات المعلمين') ?></span>
                </a>
                <a href="<?= $baseUrl ?>teacher_assignments" class="nav-item <?= $currentPage === 'teacher_assignments' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-tasks"></i></span>
                    <span><?= __('توزيع المواد والصفوف') ?></span>
                </a>
                <a href="<?= $baseUrl ?>teacher_absences" class="nav-item <?= $currentPage === 'teacher_absences' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-user-times"></i></span>
                    <span><?= __('تسجيل غيابات المعلمين') ?></span>
                </a>
                <a href="<?= $baseUrl ?>teacher_reports" class="nav-item <?= $currentPage === 'teacher_reports' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-clipboard-list"></i></span>
                    <span><?= __('تقارير دوام المعلمين') ?></span>
                </a>
                <a href="<?= $baseUrl ?>users" class="nav-item <?= $currentPage === 'users' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-user-lock"></i></span>
                    <span><?= __('حسابات دخول المعلمين') ?></span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin() || isAssistant()): ?>
                <!-- ═══ إجازات الكادر ═══ -->
                <div class="nav-section">
                    <span class="nav-section-title"><i class="fas fa-calendar-alt"></i> <?= __('إجازات الكادر') ?></span>
                </div>
                <a href="<?= $baseUrl ?>staff_leaves" class="nav-item <?= $currentPage === 'staff_leaves' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-calendar-minus"></i></span>
                    <span><?= __('تسجيل إجازة') ?></span>
                </a>
                <a href="<?= $baseUrl ?>leaves_report" class="nav-item <?= $currentPage === 'leaves_report' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-chart-pie"></i></span>
                    <span><?= __('تقارير الإجازات') ?></span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin()): ?>
                <!-- ═══ الإدارة والنظام ═══ -->
                <div class="nav-section">
                    <span class="nav-section-title"><i class="fas fa-cogs"></i> <?= __('الإدارة') ?></span>
                </div>
                <a href="<?= $baseUrl ?>classes" class="nav-item <?= $currentPage === 'classes' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-school"></i></span>
                    <span><?= __('إدارة الصفوف والشعب') ?></span>
                </a>
                <?php endif; ?>
                <?php if (isAdmin() || isAssistant()): ?>
                <a href="<?= $baseUrl ?>classroom_equipment" class="nav-item <?= $currentPage === 'classroom_equipment' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-chair"></i></span>
                    <span><?= __('جرد أثاث المدرسة') ?></span>
                </a>
                <?php endif; ?>
                <?php if (isAdmin()): ?>
                <a href="<?= $baseUrl ?>backup" class="nav-item <?= $currentPage === 'backup' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-database"></i></span>
                    <span><?= __('النسخ الاحتياطي') ?></span>
                </a>
                <a href="<?= $baseUrl ?>activity_log" class="nav-item <?= $currentPage === 'activity_log' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-history"></i></span>
                    <span><?= __('سجل العمليات') ?></span>
                </a>
                <?php endif; ?>
                
                <!-- ═══ ملفي الشخصي ═══ -->
                <div class="nav-section">
                    <span class="nav-section-title"><i class="fas fa-user"></i> <?= __('ملفي') ?></span>
                </div>
                <a href="<?= $baseUrl ?>teacher_profile" class="nav-item <?= $currentPage === 'teacher_profile' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-address-card"></i></span>
                    <span><?= __('بطاقتي الوظيفية') ?></span>
                </a>
                <a href="<?= $baseUrl ?>export_my_data" class="nav-item <?= $currentPage === 'export_my_data' ? 'active' : '' ?>">
                    <span class="icon"><i class="fas fa-download"></i></span>
                    <span><?= __('تصدير بياناتي') ?></span>
                </a>
                
                <?php endif; ?>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar"><?= mb_substr($currentUser['full_name'], 0, 1) ?></div>
                    <div class="user-details">
                        <h4><?= sanitize($currentUser['full_name']) ?></h4>
                        <small><?= __(ROLES[$currentUser['role']] ?? $currentUser['role']) ?></small>
                    </div>
                </div>
            </div>
        </aside>
        
        <main class="main-content">
            <header class="topbar">
                <div class="topbar-right">
                    <button class="sidebar-toggle-btn" id="sidebarToggle" type="button" title="<?= __('إظهار/إخفاء القائمة') ?>">
                        <span class="toggle-icon">
                            <span class="bar"></span>
                            <span class="bar"></span>
                            <span class="bar"></span>
                        </span>
                    </button>
                    <span class="page-title"><?= __($pageTitle ?? 'لوحة التحكم') ?></span>
                </div>
                <div class="topbar-left">
                    <button class="search-trigger" id="searchTrigger" type="button" title="<?= __('البحث') ?> (Ctrl+K)">
                        <i class="fas fa-search search-icon"></i>
                        <span class="search-text"><?= __('بحث...') ?></span>
                        <span class="search-shortcut"><kbd>Ctrl</kbd><kbd>K</kbd></span>
                    </button>
                    <button class="lang-toggle" onclick="toggleLanguage()" title="<?= $lang === 'ar' ? 'English' : 'العربية' ?>" id="langToggle">
                        <span class="lang-icon">🌐</span>
                        <span class="lang-text" id="langText"><?= $lang === 'ar' ? 'EN' : 'عربي' ?></span>
                    </button>
                    <button class="theme-toggle" id="themeToggle" title="<?= __('تغيير المظهر') ?>">
                        <?= $theme === 'dark' ? '☀️' : '🌙' ?>
                    </button>
 
                    <a href="<?= $baseUrl ?>logout" class="btn btn-danger btn-sm">
                        🚪 <?= __('خروج') ?>
                    </a>
                </div>
            </header>
            
            <div class="page-content">
<?= showAlert() ?>
