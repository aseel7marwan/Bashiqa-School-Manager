<?php
/**
 * معالج تغيير الثيم - Theme Handler
 * يتعامل مع طلبات تغيير الوضع المظلم/الفاتح
 * 
 * @package SchoolManager
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'غير مصرح']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['theme'])) {
    $theme = $_POST['theme'] === 'dark' ? 'dark' : 'light';
    setUserTheme($theme);
    echo json_encode(['success' => true, 'theme' => $theme]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'طلب غير صالح']);
}
