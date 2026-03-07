<?php
/**
 * 🚀 نظام الترجمة المُحسّن - JSON-Based Translation System
 * 
 * التحسينات:
 * - تحميل الترجمات من ملف JSON (أسرع بكثير)
 * - Static Cache لمنع إعادة التحميل
 * - دعم كامل للغتين العربية والإنجليزية
 * - أداء محسّن للغاية
 */

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) session_start();

// ═══════════════════════════════════════════════════════════════
// 🌍 إدارة اللغة
// ═══════════════════════════════════════════════════════════════

/**
 * الحصول على اللغة الحالية
 * @return string 'ar' أو 'en'
 */
function getLang() {
    static $lang = null;
    return $lang ?? ($lang = $_SESSION['app_lang'] ?? 'ar');
}

/**
 * تعيين اللغة
 * @param string $lang اللغة المطلوبة
 */
function setLang($lang) {
    $_SESSION['app_lang'] = in_array($lang, ['ar', 'en']) ? $lang : 'ar';
}

// ═══════════════════════════════════════════════════════════════
// 📚 تحميل قاموس الترجمة من JSON
// ═══════════════════════════════════════════════════════════════

/**
 * تحميل الترجمات من ملف JSON مع caching
 * @return array مصفوفة الترجمات
 */
function loadTranslations() {
    static $translations = null;
    
    if ($translations === null) {
        $jsonPath = __DIR__ . '/translations.json';
        
        if (file_exists($jsonPath)) {
            $jsonContent = file_get_contents($jsonPath);
            $translations = json_decode($jsonContent, true);
            
            if ($translations === null) {
                // خطأ في JSON - إرجاع مصفوفة فارغة
                error_log('Translation JSON parsing error: ' . json_last_error_msg());
                $translations = [];
            }
        } else {
            // الملف غير موجود
            error_log('Translation file not found: ' . $jsonPath);
            $translations = [];
        }
    }
    
    return $translations;
}

// تحميل الترجمات للاستخدام العام
$GLOBALS['translations'] = loadTranslations();

// ═══════════════════════════════════════════════════════════════
// 🚀 دوال الترجمة المُحسّنة
// ═══════════════════════════════════════════════════════════════

/**
 * دالة الترجمة الرئيسية - محسّنة للأداء
 * @param string $text النص العربي للترجمة
 * @return string النص المترجم أو الأصلي
 */
function __($text) {
    // ⚡ باقي مباشر للعربية - الأكثر شيوعاً (بدون بحث)
    if (getLang() === 'ar') return $text;
    
    // البحث في القاموس (O(1) - سريع جداً بفضل hash table)
    return $GLOBALS['translations'][$text] ?? $text;
}

/**
 * ترجمة مختصرة - اسم بديل للدالة الرئيسية
 * @param string $text النص للترجمة
 * @return string النص المترجم
 */
function t($text) { 
    return __($text); 
}

/**
 * طباعة النص المترجم مباشرة
 * @param string $text النص للترجمة والطباعة
 */
function _e($text) { 
    echo __($text); 
}

/**
 * الحصول على اتجاه الصفحة حسب اللغة
 * @return string 'rtl' للعربية، 'ltr' للإنجليزية
 */
function getDirection() { 
    return getLang() === 'ar' ? 'rtl' : 'ltr'; 
}

/**
 * الحصول على كود اللغة
 * @return string كود اللغة
 */
function getLangCode() { 
    return getLang(); 
}
