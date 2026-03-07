<?php
/**
 * الصفحة الرئيسية - Index
 * يوجه المستخدم للصفحة الترحيبية
 * 
 * @package SchoolManager
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// توجيه للصفحة الترحيبية
redirect('/welcome.php');
