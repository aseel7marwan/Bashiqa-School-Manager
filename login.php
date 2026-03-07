<?php
/**
 * صفحة تسجيل الدخول - Login Page
 * تصميم متناسق مع الصفحة الترحيبية - مدرسة ابتدائية للبنين
 * 
 * @package SchoolManager
 * @access  عام - لا يتطلب تسجيل دخول
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/translations.php';

// منع التخزين المؤقت
header('Cache-Control: private, no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');

// إذا كان المستخدم مسجل دخول بالفعل
if (isLoggedIn()) {
    redirect('dashboard');
}

$theme = getUserTheme();
$baseUrl = getBaseUrl();
$currentLang = getLang();
$direction = getDirection();
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $direction ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta name="description" content="<?= __('تسجيل الدخول - مدرسة بعشيقة الابتدائية للبنين') ?>">
    <title><?= __('تسجيل الدخول') ?> - <?= __('مدرسة بعشيقة الابتدائية للبنين') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a7431;
            --primary-light: #2e9e47;
            --primary-dark: #0d4a1c;
            --secondary: #f8b500;
            --accent: #00897b;
            --bg-light: #f0f7f1;
            --text-dark: #1a1a2e;
            --text-light: #4a5568;
            --white: #ffffff;
            --shadow: 0 10px 40px rgba(0,0,0,0.1);
            --shadow-lg: 0 25px 60px rgba(0,0,0,0.15);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Tajawal', 'Segoe UI', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
            overflow: hidden;
            background: 
                linear-gradient(
                    135deg,
                    rgba(240, 247, 241, 0.85) 0%,
                    rgba(200, 230, 210, 0.75) 50%,
                    rgba(180, 220, 195, 0.80) 100%
                ),
                url('assets/images/welcome.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        
        /* الأشكال الهندسية المتحركة */
        .bg-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
            z-index: 0;
        }
        
        .shape {
            position: absolute;
            border-radius: 50%;
            opacity: 0.08;
        }
        
        .shape-1 {
            width: 400px;
            height: 400px;
            background: var(--primary);
            top: -150px;
            right: -100px;
            animation: float1 20s ease-in-out infinite;
        }
        
        .shape-2 {
            width: 300px;
            height: 300px;
            background: var(--secondary);
            bottom: -100px;
            left: -100px;
            animation: float2 15s ease-in-out infinite;
        }
        
        .shape-3 {
            width: 200px;
            height: 200px;
            background: var(--accent);
            top: 50%;
            left: 10%;
            animation: float3 18s ease-in-out infinite;
        }
        
        @keyframes float1 {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(-30px, 30px) rotate(180deg); }
        }
        
        @keyframes float2 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(40px, -20px) scale(1.1); }
        }
        
        @keyframes float3 {
            0%, 100% { transform: translate(0, 0); }
            33% { transform: translate(20px, 20px); }
            66% { transform: translate(-20px, 10px); }
        }
        
        /* زر تبديل اللغة */
        .lang-toggle-btn {
            position: fixed;
            top: 1.5rem;
            <?= $direction === 'rtl' ? 'left' : 'right' ?>: 1.5rem;
            background: rgba(255, 255, 255, 0.95);
            color: var(--primary);
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            box-shadow: 0 6px 25px rgba(0,0,0,0.1);
            z-index: 1000;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: 2px solid var(--primary);
            backdrop-filter: blur(10px);
        }
        
        .lang-toggle-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 35px rgba(26, 116, 49, 0.3);
        }
        
        /* بطاقة تسجيل الدخول */
        .login-container {
            width: 100%;
            max-width: 480px;
            z-index: 10;
            position: relative;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 30px;
            padding: 3rem;
            box-shadow: 
                0 25px 80px rgba(0,0,0,0.12),
                0 10px 30px rgba(0,0,0,0.08);
            animation: cardSlide 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.5);
        }
        
        @keyframes cardSlide {
            from {
                opacity: 0;
                transform: translateY(40px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        /* رأس البطاقة */
        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        
        .school-badge {
            display: flex;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        
        .school-logo {
            width: 110px;
            height: 110px;
            background: linear-gradient(145deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 
                0 15px 40px rgba(26, 116, 49, 0.35),
                0 0 0 8px rgba(26, 116, 49, 0.1);
            animation: logoBreath 3s ease-in-out infinite;
        }
        
        @keyframes logoBreath {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.03); }
        }
        
        .school-logo span {
            font-size: 3.5rem;
        }
        
        .login-header h1 {
            color: var(--primary-dark);
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: var(--text-light);
            font-size: 1rem;
            font-weight: 500;
        }
        
        /* أيقونات تعليمية */
        .edu-icons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .edu-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--bg-light) 0%, rgba(26, 116, 49, 0.1) 100%);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .edu-icon:hover {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            transform: scale(1.15) rotate(-5deg);
        }
        
        /* رسائل التنبيه */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 14px;
            margin-bottom: 1.75rem;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border: 1px solid #a5d6a7;
            color: #2e7d32;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #ffebee, #ffcdd2);
            border: 1px solid #ef9a9a;
            color: #c62828;
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #fff3e0, #ffe0b2);
            border: 1px solid #ffcc80;
            color: #e65100;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border: 1px solid #90caf9;
            color: #1565c0;
        }
        
        /* حقول الإدخال */
        .form-group {
            margin-bottom: 1.75rem;
        }
        
        .form-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-dark);
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }
        
        .form-group label .icon {
            font-size: 1.2rem;
        }
        
        .form-control {
            width: 100%;
            padding: 1.1rem 1.5rem;
            background: var(--bg-light);
            border: 2px solid transparent;
            border-radius: 14px;
            color: var(--text-dark);
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .form-control::placeholder {
            color: #9ca3af;
        }
        
        .form-control:focus {
            outline: none;
            background: white;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(26, 116, 49, 0.1);
        }
        
        /* حقل كلمة المرور */
        .password-wrapper {
            position: relative;
        }
        
        .password-wrapper .form-control {
            padding-left: 3.5rem;
        }
        
        .password-toggle {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-light);
            font-size: 1.3rem;
            cursor: pointer;
            padding: 0.25rem;
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        
        .password-toggle:hover {
            color: var(--primary);
            background: rgba(26, 116, 49, 0.1);
        }
        
        /* زر تسجيل الدخول */
        .btn-submit {
            width: 100%;
            padding: 1.15rem 1.5rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 1.15rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 25px rgba(26, 116, 49, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }
        
        .btn-submit:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 35px rgba(26, 116, 49, 0.45);
        }
        
        .btn-submit:active {
            transform: translateY(-2px);
        }
        
        .btn-submit .icon {
            font-size: 1.4rem;
        }
        
        /* رابط الصفحة الرئيسية */
        .back-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.75rem;
            color: var(--text-light);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 0.75rem;
            border-radius: 10px;
        }
        
        .back-link:hover {
            color: var(--primary);
            background: rgba(26, 116, 49, 0.05);
        }
        
        /* تذييل البطاقة */
        .login-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.75rem;
            border-top: 1px solid rgba(0,0,0,0.06);
        }
        
        .login-footer p {
            color: var(--text-light);
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .privacy-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            margin-top: 0.75rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .privacy-link:hover {
            background: rgba(26, 116, 49, 0.08);
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 2rem 1.5rem;
                border-radius: 24px;
            }
            
            .school-logo {
                width: 90px;
                height: 90px;
            }
            
            .badge-ring {
                width: 110px;
                height: 110px;
            }
            
            .school-logo span {
                font-size: 2.75rem;
            }
            
            .login-header h1 {
                font-size: 1.35rem;
            }
            
            .edu-icon {
                width: 42px;
                height: 42px;
                font-size: 1.2rem;
            }
            
            .lang-toggle-btn {
                top: 1rem;
                <?= $direction === 'rtl' ? 'left' : 'right' ?>: 1rem;
                padding: 0.6rem 1rem;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <!-- الأشكال الهندسية -->
    <div class="bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>
    
    <!-- زر تبديل اللغة -->
    <a href="<?= $baseUrl ?>controllers/language_handler.php?lang=<?= $currentLang === 'ar' ? 'en' : 'ar' ?>&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
       class="lang-toggle-btn" title="<?= $currentLang === 'ar' ? 'English' : 'العربية' ?>">
        <?= $currentLang === 'ar' ? '🌐 EN' : '🌐 عربي' ?>
    </a>
    
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="school-badge">
                    <div class="school-logo">
                        <span>🏫</span>
                    </div>
                </div>
                <h1><?= __('مدرسة بعشيقة الابتدائية للبنين') ?></h1>
                <p><?= __('تسجيل الدخول لنظام الإدارة المدرسية') ?></p>
                
                <!-- أيقونات تعليمية صغيرة -->
                <div class="edu-icons">
                    <div class="edu-icon" title="<?= __('الكتب') ?>">📚</div>
                    <div class="edu-icon" title="<?= __('الدراسة') ?>">✏️</div>
                    <div class="edu-icon" title="<?= __('النجاح') ?>">🎓</div>
                    <div class="edu-icon" title="<?= __('العلم') ?>">🔬</div>
                </div>
            </div>
            
            <?= showAlert() ?>
            
            <form action="<?= $baseUrl ?>controllers/login_handler.php" method="POST" autocomplete="off">
                <?= csrfField() ?>
                
                <div class="form-group">
                    <label>
                        <span class="icon">👤</span>
                        <?= __('اسم المستخدم') ?>
                    </label>
                    <input type="text" name="username" class="form-control" 
                           placeholder="<?= __('أدخل اسم المستخدم') ?>" required autofocus
                           autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label>
                        <span class="icon">🔒</span>
                        <?= __('كلمة المرور') ?>
                    </label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" 
                               class="form-control" placeholder="<?= __('أدخل كلمة المرور') ?>" required
                               autocomplete="off">
                        <button type="button" class="password-toggle" onclick="togglePassword()">👁️</button>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">
                    <span class="icon">🚀</span>
                    <?= __('تسجيل الدخول') ?>
                </button>
            </form>
            
            <a href="<?= $baseUrl ?>welcome" class="back-link">
                <span>🏠</span> <?= __('العودة للصفحة الرئيسية') ?>
            </a>
            
            <div class="login-footer">
                <p>🏫 <?= __('مدرسة بعشيقة الابتدائية للبنين') ?> - <?= date('Y') ?></p>
                <a href="<?= $baseUrl ?>privacy" class="privacy-link">
                    🔒 <?= __('سياسة الخصوصية') ?>
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // إظهار/إخفاء كلمة المرور
        function togglePassword() {
            const input = document.getElementById('password');
            const btn = document.querySelector('.password-toggle');
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = '🙈';
            } else {
                input.type = 'password';
                btn.textContent = '👁️';
            }
        }
        
        // منع تخزين الصفحة في المتصفح
        if (window.history && window.history.pushState) {
            window.history.pushState('forward', null, window.location.href);
            window.onpopstate = function() {
                window.history.pushState('forward', null, window.location.href);
            };
        }
        
        // مسح حقول النموذج عند تحميل الصفحة
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                document.querySelector('form').reset();
            }
        });
    </script>
</body>
</html>
