<?php
/**
 * سياسة الخصوصية - Privacy Policy
 * 
 * @package SchoolManager
 */

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'سياسة الخصوصية';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - نظام إدارة المدرسة</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        
        .header h1 {
            color: #1a365d;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .header .subtitle {
            color: #666;
            font-size: 1rem;
        }
        
        .official-header {
            background: linear-gradient(135deg, #1a365d, #2c5282);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .official-header h2 {
            font-size: 1.3rem;
            margin-bottom: 5px;
        }
        
        .official-header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }
        
        .section {
            margin-bottom: 25px;
        }
        
        .section h3 {
            color: #2c5282;
            font-size: 1.2rem;
            margin-bottom: 15px;
            padding-right: 15px;
            border-right: 4px solid #3182ce;
        }
        
        .section p, .section li {
            color: #444;
            line-height: 1.9;
            font-size: 1rem;
        }
        
        .section ul {
            margin-right: 20px;
            margin-top: 10px;
        }
        
        .section li {
            margin-bottom: 8px;
            position: relative;
            padding-right: 25px;
        }
        
        .section li::before {
            content: "✓";
            position: absolute;
            right: 0;
            color: #38a169;
            font-weight: bold;
        }
        
        .highlight-box {
            background: #ebf8ff;
            border: 1px solid #90cdf4;
            border-radius: 10px;
            padding: 15px 20px;
            margin: 15px 0;
        }
        
        .warning-box {
            background: #fffbeb;
            border: 1px solid #f6e05e;
            border-radius: 10px;
            padding: 15px 20px;
            margin: 15px 0;
        }
        
        .contact-info {
            background: #f0fff4;
            border: 1px solid #9ae6b4;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .contact-info h4 {
            color: #276749;
            margin-bottom: 10px;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #eee;
            color: #666;
        }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 25px;
            background: linear-gradient(135deg, #3182ce, #2c5282);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(49, 130, 206, 0.4);
        }
        
        .last-updated {
            text-align: center;
            color: #888;
            font-size: 0.9rem;
            margin-top: 20px;
        }
        
        @media (max-width: 600px) {
            .container { padding: 20px; }
            .header h1 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔒 سياسة الخصوصية وحماية البيانات</h1>
            <p class="subtitle">نظام إدارة المدرسة الإلكتروني</p>
        </div>
        
        <div class="official-header">
            <h2>🇮🇶 جمهورية العراق - وزارة التربية</h2>
            <p>المديرية العامة لتربية نينوى - قسم بعشيقة</p>
        </div>
        
        <div class="section">
            <h3>📋 مقدمة</h3>
            <p>
                نلتزم في مدرستنا بحماية خصوصية وسرية البيانات الشخصية لجميع الطلاب والمعلمين وأولياء الأمور، 
                وفقاً للقوانين والتعليمات الصادرة من وزارة التربية العراقية.
            </p>
        </div>
        
        <div class="section">
            <h3>📊 البيانات التي نجمعها</h3>
            <ul>
                <li><strong>بيانات الطلاب:</strong> الاسم، تاريخ الميلاد، الصف، الشعبة، اسم ولي الأمر، رقم الهاتف</li>
                <li><strong>بيانات المعلمين:</strong> الاسم، المؤهل العلمي، التخصص، بيانات التعيين</li>
                <li><strong>السجلات الأكاديمية:</strong> الدرجات، الحضور والغياب، التقارير</li>
                <li><strong>بيانات الدخول:</strong> اسم المستخدم، سجل الدخول للنظام</li>
            </ul>
        </div>
        
        <div class="section">
            <h3>🎯 الغرض من جمع البيانات</h3>
            <ul>
                <li>إدارة السجلات الأكاديمية والإدارية للمدرسة</li>
                <li>تسجيل الحضور والغياب</li>
                <li>إصدار الشهادات والتقارير الرسمية</li>
                <li>التواصل مع أولياء الأمور</li>
                <li>تحسين العملية التعليمية</li>
            </ul>
        </div>
        
        <div class="section">
            <h3>🔐 حماية البيانات</h3>
            <div class="highlight-box">
                <p>نتخذ إجراءات أمنية صارمة لحماية بياناتكم:</p>
            </div>
            <ul>
                <li>تشفير كلمات المرور وعدم تخزينها بشكل نصي</li>
                <li>استخدام اتصال آمن (HTTPS) عند توفره</li>
                <li>تقييد الوصول حسب صلاحيات المستخدم</li>
                <li>تسجيل جميع العمليات في سجل المراقبة</li>
                <li>النسخ الاحتياطي الدوري للبيانات</li>
            </ul>
        </div>
        
        <div class="section">
            <h3>👥 مشاركة البيانات</h3>
            <div class="warning-box">
                <p>⚠️ <strong>لا نشارك البيانات الشخصية مع أي جهة خارجية</strong> إلا في الحالات التالية:</p>
            </div>
            <ul>
                <li>بناءً على طلب رسمي من وزارة التربية</li>
                <li>للجهات الرقابية والتفتيشية المخولة قانوناً</li>
                <li>بموافقة ولي الأمر الخطية</li>
            </ul>
        </div>
        
        <div class="section">
            <h3>⏳ الاحتفاظ بالبيانات</h3>
            <p>
                نحتفظ بالسجلات الأكاديمية وفقاً للمدد المحددة من قبل وزارة التربية العراقية، 
                ويتم حذف البيانات غير الضرورية بشكل دوري مع الحفاظ على السجلات الأساسية للطلاب.
            </p>
        </div>
        
        <div class="section">
            <h3>✅ حقوقكم</h3>
            <ul>
                <li>الاطلاع على بياناتكم الشخصية المحفوظة</li>
                <li>طلب تصحيح أي معلومات خاطئة</li>
                <li>الحصول على نسخة من سجلاتكم</li>
                <li>تقديم شكوى في حال انتهاك الخصوصية</li>
            </ul>
        </div>
        
        <div class="section">
            <h3>📞 التواصل معنا</h3>
            <div class="contact-info">
                <h4>للاستفسارات حول الخصوصية وحماية البيانات:</h4>
                <p>📍 <strong>العنوان:</strong> مدرسة بعشيقة الابتدائية للبنين - بعشيقة - نينوى</p>
                <p>📧 <strong>البريد:</strong> يمكنكم التواصل مع إدارة المدرسة مباشرة</p>
                <p>🏛️ <strong>الجهة المسؤولة:</strong> مديرية تربية نينوى - قسم بعشيقة</p>
            </div>
        </div>
        
        <div class="footer">
            <a href="javascript:history.back()" class="btn-back">
                <span>↩️</span>
                رجوع
            </a>
            <p class="last-updated">
                آخر تحديث: <?= date('Y/m/d') ?>
            </p>
        </div>
    </div>
</body>
</html>
