<?php
/**
 * مساعد التصدير - Export Helper
 * توليد تقارير بصيغة Word و PDF
 * 
 * @package SchoolManager
 */

/**
 * رأس الوثيقة الرسمية
 */
function getDocumentHeader($title, $subtitle = '') {
    $date = date('Y/m/d');
    // تحويل الوقت لنظام 12 ساعة
    $hour = (int)date('G');
    $minute = date('i');
    $period = $hour >= 12 ? 'م' : 'ص';
    $hour12 = $hour % 12;
    if ($hour12 == 0) $hour12 = 12;
    $time = $hour12 . ':' . $minute . ' ' . $period;
    $year = date('Y');
    
    return '
    <div class="document-header">
        <div class="header-logo">🏫</div>
        <div class="header-ministry">جمهورية العراق</div>
        <div class="header-ministry">وزارة التربية</div>
        <div class="header-ministry">المديرية العامة لتربية نينوى</div>
        <div class="header-school">مدرسة بعشيقة الابتدائية للبنين</div>
        <div class="header-title">' . htmlspecialchars($title) . '</div>
        ' . ($subtitle ? '<div class="header-subtitle">' . htmlspecialchars($subtitle) . '</div>' : '') . '
        <div class="header-date">تاريخ الإصدار: ' . $date . ' | الساعة: ' . $time . '</div>
    </div>
    ';
}

/**
 * تذييل الوثيقة الرسمية
 */
function getDocumentFooter() {
    return '
    <div class="document-footer">
        <div class="footer-signatures">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">توقيع المدير</div>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">الختم الرسمي</div>
            </div>
        </div>
        <div class="footer-info">
            مدرسة بعشيقة الابتدائية للبنين - نظام الإدارة المدرسية الإلكتروني | ' . date('Y') . '
        </div>
    </div>
    ';
}

/**
 * أنماط CSS للوثيقة
 */
function getDocumentStyles() {
    return '
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: "Traditional Arabic", "Arial", sans-serif;
            font-size: 14pt;
            line-height: 1.6;
            direction: rtl;
            background: white;
            color: black;
            padding: 20px;
        }
        
        /* رأس الوثيقة */
        .document-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px double #333;
        }
        
        .header-logo {
            font-size: 50pt;
            margin-bottom: 10px;
        }
        
        .header-ministry {
            font-size: 12pt;
            color: #555;
            margin: 2px 0;
        }
        
        .header-school {
            font-size: 18pt;
            font-weight: bold;
            color: #1a1a2e;
            margin: 15px 0 10px;
        }
        
        .header-title {
            font-size: 16pt;
            font-weight: bold;
            background: #f0f0f0;
            padding: 10px 30px;
            display: inline-block;
            border: 2px solid #333;
            border-radius: 5px;
            margin: 15px 0;
        }
        
        .header-subtitle {
            font-size: 12pt;
            color: #666;
            margin: 10px 0;
        }
        
        .header-date {
            font-size: 10pt;
            color: #888;
            margin-top: 10px;
        }
        
        /* المحتوى */
        .document-content {
            min-height: 500px;
        }
        
        /* الجداول */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 11pt;
        }
        
        table, th, td {
            border: 1px solid #333;
        }
        
        th {
            background: #e0e0e0;
            font-weight: bold;
            padding: 10px 8px;
            text-align: center;
        }
        
        td {
            padding: 8px;
            text-align: center;
        }
        
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        /* البطاقات */
        .info-section {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        
        .info-section-title {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
        }
        
        .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px dotted #ccc;
        }
        
        .info-label {
            font-weight: bold;
            min-width: 150px;
        }
        
        .info-value {
            flex: 1;
        }
        
        /* الإحصائيات */
        .stats-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-box {
            flex: 1;
            min-width: 150px;
            padding: 15px;
            border: 2px solid #333;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24pt;
            font-weight: bold;
        }
        
        .stat-label {
            font-size: 10pt;
            color: #666;
            margin-top: 5px;
        }
        
        /* الشارات */
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 10pt;
            font-weight: bold;
        }
        
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        
        /* تذييل الوثيقة */
        .document-footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #333;
        }
        
        .footer-signatures {
            display: flex;
            justify-content: space-around;
            margin: 30px 0;
        }
        
        .signature-box {
            text-align: center;
            min-width: 200px;
        }
        
        .signature-line {
            border-bottom: 1px solid #333;
            height: 60px;
            margin-bottom: 10px;
        }
        
        .signature-label {
            font-size: 10pt;
            color: #666;
        }
        
        .footer-info {
            text-align: center;
            font-size: 9pt;
            color: #888;
            margin-top: 20px;
        }
        
        /* فاصل الصفحات */
        .page-break {
            page-break-before: always;
        }
    </style>
    ';
}

/**
 * تصدير كـ Word
 */
function exportAsWord($title, $content, $filename) {
    header("Content-Type: application/vnd.ms-word; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"{$filename}.doc\"");
    header("Cache-Control: no-cache");
    
    echo '<!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        ' . getDocumentStyles() . '
    </head>
    <body>
        ' . getDocumentHeader($title) . '
        <div class="document-content">
            ' . $content . '
        </div>
        ' . getDocumentFooter() . '
    </body>
    </html>';
    exit;
}

/**
 * تصدير كـ HTML للطباعة كـ PDF
 */
function exportAsPrintablePDF($title, $content, $subtitle = '') {
    echo '<!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($title) . '</title>
        ' . getDocumentStyles() . '
        <style>
            @media print {
                body { padding: 0; }
                .no-print { display: none !important; }
            }
        </style>
    </head>
    <body>
        ' . getDocumentHeader($title, $subtitle) . '
        <div class="document-content">
            ' . $content . '
        </div>
        ' . getDocumentFooter() . '
        
        <div class="no-print" style="text-align: center; margin: 30px 0;">
            <button onclick="window.print()" style="padding: 15px 40px; font-size: 16pt; cursor: pointer; background: #667eea; color: white; border: none; border-radius: 8px;">
                🖨️ طباعة / حفظ كـ PDF
            </button>
            <button onclick="window.close()" style="padding: 15px 40px; font-size: 16pt; cursor: pointer; background: #6c757d; color: white; border: none; border-radius: 8px; margin-right: 10px;">
                ✖️ إغلاق
            </button>
        </div>
        
        <script>
            // طباعة تلقائية عند التحميل
            // window.onload = function() { window.print(); }
        </script>
    </body>
    </html>';
    exit;
}

/**
 * توليد جدول HTML من مصفوفة
 */
function generateTable($headers, $rows, $footerRow = null) {
    $html = '<table>';
    
    // الرأس
    $html .= '<thead><tr>';
    foreach ($headers as $header) {
        $html .= '<th>' . htmlspecialchars($header) . '</th>';
    }
    $html .= '</tr></thead>';
    
    // المحتوى
    $html .= '<tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . $cell . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody>';
    
    // التذييل
    if ($footerRow) {
        $html .= '<tfoot><tr>';
        foreach ($footerRow as $cell) {
            $html .= '<td style="font-weight: bold; background: #e0e0e0;">' . $cell . '</td>';
        }
        $html .= '</tr></tfoot>';
    }
    
    $html .= '</table>';
    return $html;
}

/**
 * توليد قسم معلومات
 */
function generateInfoSection($title, $data) {
    $html = '<div class="info-section">';
    $html .= '<div class="info-section-title">' . htmlspecialchars($title) . '</div>';
    
    foreach ($data as $label => $value) {
        $html .= '<div class="info-row">';
        $html .= '<span class="info-label">' . htmlspecialchars($label) . ':</span>';
        $html .= '<span class="info-value">' . htmlspecialchars($value) . '</span>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * توليد إحصائيات
 */
function generateStatsGrid($stats) {
    $html = '<div class="stats-grid">';
    
    foreach ($stats as $stat) {
        $html .= '<div class="stat-box">';
        $html .= '<div class="stat-value">' . $stat['value'] . '</div>';
        $html .= '<div class="stat-label">' . htmlspecialchars($stat['label']) . '</div>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * تصدير كـ Excel
 * ينشئ ملف Excel حقيقي بتنسيق XML Spreadsheet
 */
function exportAsExcel($title, $headers, $rows, $filename, $footerRow = null) {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"{$filename}.xls\"");
    header("Cache-Control: no-cache");
    header("Pragma: no-cache");
    
    // BOM for UTF-8
    echo "\xEF\xBB\xBF";
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
           xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
           xmlns:x="urn:schemas-microsoft-com:office:excel">' . "\n";
    
    // الأنماط
    echo '<Styles>
        <Style ss:ID="Default">
            <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
            <Font ss:FontName="Arial" ss:Size="11"/>
        </Style>
        <Style ss:ID="Title">
            <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
            <Font ss:FontName="Arial" ss:Size="16" ss:Bold="1"/>
            <Interior ss:Color="#667eea" ss:Pattern="Solid"/>
            <Font ss:Color="#FFFFFF"/>
        </Style>
        <Style ss:ID="Header">
            <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
            <Font ss:FontName="Arial" ss:Size="11" ss:Bold="1"/>
            <Interior ss:Color="#f0f0f0" ss:Pattern="Solid"/>
            <Borders>
                <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
            </Borders>
        </Style>
        <Style ss:ID="Data">
            <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
            <Font ss:FontName="Arial" ss:Size="10"/>
            <Borders>
                <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
            </Borders>
        </Style>
        <Style ss:ID="DataAlt">
            <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
            <Font ss:FontName="Arial" ss:Size="10"/>
            <Interior ss:Color="#f9f9f9" ss:Pattern="Solid"/>
            <Borders>
                <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
            </Borders>
        </Style>
        <Style ss:ID="Footer">
            <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
            <Font ss:FontName="Arial" ss:Size="11" ss:Bold="1"/>
            <Interior ss:Color="#e0e0e0" ss:Pattern="Solid"/>
            <Borders>
                <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2"/>
                <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2"/>
            </Borders>
        </Style>
        <Style ss:ID="Success">
            <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
            <Font ss:FontName="Arial" ss:Size="10" ss:Bold="1"/>
            <Interior ss:Color="#d4edda" ss:Pattern="Solid"/>
            <Font ss:Color="#155724"/>
            <Borders>
                <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
            </Borders>
        </Style>
        <Style ss:ID="Warning">
            <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
            <Font ss:FontName="Arial" ss:Size="10" ss:Bold="1"/>
            <Interior ss:Color="#fff3cd" ss:Pattern="Solid"/>
            <Font ss:Color="#856404"/>
            <Borders>
                <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
            </Borders>
        </Style>
        <Style ss:ID="Danger">
            <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
            <Font ss:FontName="Arial" ss:Size="10" ss:Bold="1"/>
            <Interior ss:Color="#f8d7da" ss:Pattern="Solid"/>
            <Font ss:Color="#721c24"/>
            <Borders>
                <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
            </Borders>
        </Style>
        <Style ss:ID="LowGrade">
            <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
            <Font ss:FontName="Arial" ss:Size="10" ss:Bold="1"/>
            <Font ss:Color="#dc2626"/>
            <Borders>
                <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
            </Borders>
        </Style>
    </Styles>' . "\n";
    
    echo '<Worksheet ss:Name="التقرير" ss:RightToLeft="1">' . "\n";
    echo '<Table>' . "\n";
    
    // عنوان التقرير
    $colCount = count($headers);
    echo '<Row ss:Height="30">';
    echo '<Cell ss:StyleID="Title" ss:MergeAcross="' . ($colCount - 1) . '"><Data ss:Type="String">' . htmlspecialchars($title) . '</Data></Cell>';
    echo '</Row>' . "\n";
    
    // سطر فارغ
    echo '<Row ss:Height="10"></Row>' . "\n";
    
    // رؤوس الأعمدة
    echo '<Row ss:Height="25">';
    foreach ($headers as $header) {
        echo '<Cell ss:StyleID="Header"><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>';
    }
    echo '</Row>' . "\n";
    
    // البيانات
    $rowNum = 0;
    foreach ($rows as $row) {
        $rowNum++;
        $style = ($rowNum % 2 == 0) ? 'DataAlt' : 'Data';
        echo '<Row ss:Height="22">';
        foreach ($row as $index => $cell) {
            // تحديد النمط بناءً على المحتوى
            $cellStyle = $style;
            $cellContent = strip_tags($cell);
            
            // التحقق من النتيجة
            if ($cellContent === 'ناجح') {
                $cellStyle = 'Success';
            } elseif ($cellContent === 'مكمّل' || $cellContent === 'مكمل') {
                $cellStyle = 'Warning';
            } elseif ($cellContent === 'راسب') {
                $cellStyle = 'Danger';
            }
            
            // تحديد نوع البيانات
            $dataType = 'String';
            if (is_numeric($cellContent) || preg_match('/^[\d.]+$/', $cellContent)) {
                $dataType = 'Number';
            }
            
            echo '<Cell ss:StyleID="' . $cellStyle . '"><Data ss:Type="' . $dataType . '">' . htmlspecialchars($cellContent) . '</Data></Cell>';
        }
        echo '</Row>' . "\n";
    }
    
    // صف التذييل
    if ($footerRow) {
        echo '<Row ss:Height="25">';
        foreach ($footerRow as $cell) {
            echo '<Cell ss:StyleID="Footer"><Data ss:Type="String">' . htmlspecialchars(strip_tags($cell)) . '</Data></Cell>';
        }
        echo '</Row>' . "\n";
    }
    
    echo '</Table>' . "\n";
    echo '</Worksheet>' . "\n";
    echo '</Workbook>';
    
    exit;
}

/**
 * تصدير Excel بسيط (HTML Table)
 * بديل أبسط يعمل على جميع الأنظمة
 */
function exportAsExcelSimple($title, $headers, $rows, $filename, $footerRow = null) {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"{$filename}.xls\"");
    header("Cache-Control: no-cache");
    
    // BOM for UTF-8
    echo "\xEF\xBB\xBF";
    
    echo '<!DOCTYPE html>
    <html dir="rtl">
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; }
            table { border-collapse: collapse; width: 100%; direction: rtl; }
            th, td { border: 1px solid #333; padding: 10px; text-align: center; }
            th { background: #f0f0f0; font-weight: bold; }
            .title { font-size: 16pt; font-weight: bold; text-align: center; background: #667eea; color: white; }
            .footer { font-weight: bold; background: #e0e0e0; }
            .success { background: #d4edda; color: #155724; font-weight: bold; }
            .warning { background: #fff3cd; color: #856404; font-weight: bold; }
            .danger { background: #f8d7da; color: #721c24; font-weight: bold; }
            tr:nth-child(even) { background: #f9f9f9; }
        </style>
    </head>
    <body>
    <table>
        <tr><td class="title" colspan="' . count($headers) . '">' . htmlspecialchars($title) . '</td></tr>
        <tr>';
    
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars($header) . '</th>';
    }
    echo '</tr>';
    
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            $cellContent = strip_tags($cell);
            $class = '';
            if ($cellContent === 'ناجح') $class = 'success';
            elseif ($cellContent === 'مكمّل' || $cellContent === 'مكمل') $class = 'warning';
            elseif ($cellContent === 'راسب') $class = 'danger';
            
            echo '<td class="' . $class . '">' . htmlspecialchars($cellContent) . '</td>';
        }
        echo '</tr>';
    }
    
    if ($footerRow) {
        echo '<tr class="footer">';
        foreach ($footerRow as $cell) {
            echo '<td>' . htmlspecialchars(strip_tags($cell)) . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</table></body></html>';
    exit;
}
