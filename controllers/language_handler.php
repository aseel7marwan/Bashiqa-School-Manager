<?php
/**
 * معالج تبديل اللغة
 * Language Switch Handler
 */

session_start();

$lang = $_POST['lang'] ?? $_GET['lang'] ?? null;
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? null;

if ($lang && in_array($lang, ['ar', 'en'])) {
    $_SESSION['app_lang'] = $lang;
    
    // إذا كان هناك رابط إعادة توجيه - استخدمه
    if ($redirect) {
        header('Location: ' . urldecode($redirect));
        exit;
    }
    
    // إذا كان الطلب AJAX - أرجع JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'lang' => $lang,
            'direction' => $lang === 'ar' ? 'rtl' : 'ltr'
        ]);
        exit;
    }
    
    // إعادة التوجيه للصفحة السابقة
    $referer = $_SERVER['HTTP_REFERER'] ?? '/dashboard.php';
    header('Location: ' . $referer);
    exit;
} else {
    // Toggle the language
    $currentLang = $_SESSION['app_lang'] ?? 'ar';
    $newLang = $currentLang === 'ar' ? 'en' : 'ar';
    $_SESSION['app_lang'] = $newLang;
    
    // Redirect back
    $referer = $redirect ? urldecode($redirect) : ($_SERVER['HTTP_REFERER'] ?? '/dashboard.php');
    header('Location: ' . $referer);
    exit;
}
