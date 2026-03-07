<?php
/**
 * صفحة الأخطاء الموحدة - Unified Error Page
 * تتعامل مع جميع أنواع الأخطاء (403, 404, 500, إلخ)
 * 
 * @package SchoolManager
 * @version 2.1.0
 */

// تحديد نوع الخطأ تلقائياً
$errorCode = http_response_code();
if ($errorCode === 200) {
    // إذا لم يكن هناك خطأ، افترض 404
    $errorCode = 404;
    http_response_code(404);
}

// إعدادات كل نوع خطأ
$errors = [
    400 => [
        'title' => 'طلب غير صحيح',
        'icon' => '❓',
        'message' => 'الطلب الذي أرسلته غير صحيح أو تالف.',
        'color' => '#f59e0b',
        'gradient' => 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)'
    ],
    401 => [
        'title' => 'تسجيل الدخول مطلوب',
        'icon' => '🔑',
        'message' => 'يجب تسجيل الدخول للوصول إلى هذه الصفحة.',
        'color' => '#3b82f6',
        'gradient' => 'linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%)'
    ],
    403 => [
        'title' => 'الوصول ممنوع',
        'icon' => '🚫',
        'message' => 'ليس لديك صلاحية للوصول إلى هذا المحتوى.',
        'color' => '#f093fb',
        'gradient' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)'
    ],
    404 => [
        'title' => 'الصفحة غير موجودة',
        'icon' => '🔍',
        'message' => 'الصفحة التي تبحث عنها غير موجودة أو تم نقلها.',
        'color' => '#667eea',
        'gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
    ],
    500 => [
        'title' => 'خطأ في الخادم',
        'icon' => '⚙️',
        'message' => 'حدث خطأ غير متوقع. نعتذر عن الإزعاج.',
        'color' => '#ef4444',
        'gradient' => 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)'
    ],
    503 => [
        'title' => 'الخدمة غير متاحة',
        'icon' => '🔧',
        'message' => 'الموقع قيد الصيانة حالياً. حاول لاحقاً.',
        'color' => '#6b7280',
        'gradient' => 'linear-gradient(135deg, #6b7280 0%, #374151 100%)'
    ]
];

// استخدام إعدادات الخطأ أو الافتراضي
$error = $errors[$errorCode] ?? $errors[404];
$error['code'] = $errorCode;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $error['title'] ?> (<?= $errorCode ?>) - نظام إدارة المدرسة</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Cairo', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: <?= $error['gradient'] ?>;
            padding: 20px;
        }
        
        .error-container {
            text-align: center;
            background: white;
            padding: 3rem 2rem;
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.2);
            max-width: 480px;
            width: 100%;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .error-icon {
            font-size: 5rem;
            margin-bottom: 0.5rem;
            display: inline-block;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .error-code {
            font-size: 4.5rem;
            font-weight: 700;
            background: <?= $error['gradient'] ?>;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.1;
        }
        
        .error-title {
            font-size: 1.4rem;
            color: #333;
            margin: 0.5rem 0;
            font-weight: 600;
        }
        
        .error-message {
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.8;
            font-size: 1rem;
        }
        
        .btn-group {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.9rem 1.8rem;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            font-family: inherit;
        }
        
        .btn-primary {
            background: <?= $error['gradient'] ?>;
            color: white;
            box-shadow: 0 4px 15px <?= $error['color'] ?>66;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px <?= $error['color'] ?>88;
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
            transform: translateY(-2px);
        }
        
        /* الدوائر الديكورية */
        .circles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
            z-index: -1;
        }
        
        .circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
            animation: float 25s infinite ease-in-out;
        }
        
        .circle:nth-child(1) { width: 180px; height: 180px; top: 10%; left: 15%; animation-delay: 0s; }
        .circle:nth-child(2) { width: 120px; height: 120px; top: 60%; right: 15%; animation-delay: 4s; }
        .circle:nth-child(3) { width: 80px; height: 80px; bottom: 15%; left: 35%; animation-delay: 8s; }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }
        
        .school-badge {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
            font-size: 0.85rem;
            color: #9ca3af;
        }
        
        .school-badge span {
            font-size: 1.2rem;
        }
        
        @media (max-width: 480px) {
            .error-container { padding: 2rem 1.5rem; }
            .error-icon { font-size: 4rem; }
            .error-code { font-size: 3.5rem; }
            .error-title { font-size: 1.2rem; }
            .btn { padding: 0.8rem 1.4rem; font-size: 0.9rem; }
        }
    </style>
</head>
<body>
    <div class="circles">
        <div class="circle"></div>
        <div class="circle"></div>
        <div class="circle"></div>
    </div>
    
    <div class="error-container">
        <div class="error-icon"><?= $error['icon'] ?></div>
        <div class="error-code"><?= $errorCode ?></div>
        <h1 class="error-title"><?= $error['title'] ?></h1>
        <p class="error-message"><?= $error['message'] ?></p>
        
        <div class="btn-group">
            <?php if ($errorCode === 401): ?>
                <a href="/login" class="btn btn-primary">
                    <span>🔑</span> تسجيل الدخول
                </a>
            <?php elseif ($errorCode === 500 || $errorCode === 503): ?>
                <button onclick="location.reload()" class="btn btn-primary">
                    <span>🔄</span> إعادة المحاولة
                </button>
            <?php else: ?>
                <a href="/dashboard" class="btn btn-primary">
                    <span>🏠</span> الصفحة الرئيسية
                </a>
            <?php endif; ?>
            
            <button onclick="history.back()" class="btn btn-secondary">
                <span>↩️</span> رجوع
            </button>
        </div>
        
        <div class="school-badge">
            <span>🏫</span> نظام إدارة المدرسة
        </div>
    </div>
</body>
</html>
