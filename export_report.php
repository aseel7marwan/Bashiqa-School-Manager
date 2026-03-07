<?php
/**
 * تصدير التقارير - Export Reports
 * تصدير تقارير الحضور والإجازات والدرجات بصيغة Word و PDF
 * 
 * @package SchoolManager
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/export_helper.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';

requireLogin();

$reportType = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'pdf';
$conn = getConnection();

switch ($reportType) {
    
    // ═══════════════════════════════════════════════════════════════
    // تقرير حضور التلاميذ
    // ═══════════════════════════════════════════════════════════════
    case 'student_attendance':
        require_once __DIR__ . '/models/Attendance.php';
        
        $classId = (int)($_GET['class_id'] ?? 1);
        $section = $_GET['section'] ?? 'أ';
        $month = $_GET['month'] ?? date('m');
        $year = $_GET['year'] ?? date('Y');
        
        $arabicMonths = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
        ];
        $monthName = $arabicMonths[(int)$month] ?? $month;
        
        $attendanceModel = new Attendance();
        $monthlyReport = $attendanceModel->getMonthlyReport($classId, $section, $year, $month);
        
        // تجميع الإحصائيات
        $studentStats = [];
        $totals = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0, 'total' => 0];
        
        foreach ($monthlyReport as $record) {
            $studentId = $record['student_id'];
            if (!isset($studentStats[$studentId])) {
                $studentStats[$studentId] = [
                    'name' => $record['full_name'],
                    'present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0, 'total' => 0
                ];
            }
            if ($record['status']) {
                $studentStats[$studentId][$record['status']]++;
                $studentStats[$studentId]['total']++;
                $totals[$record['status']]++;
                $totals['total']++;
            }
        }
        
        $title = 'تقرير حضور التلاميذ';
        $subtitle = CLASSES[$classId] . ' - شعبة ' . $section . ' | شهر ' . $monthName . ' ' . $year;
        
        // إنشاء المحتوى
        $content = generateStatsGrid([
            ['value' => $totals['present'], 'label' => 'حضور'],
            ['value' => $totals['late'], 'label' => 'تأخير'],
            ['value' => $totals['excused'], 'label' => 'معذور'],
            ['value' => $totals['absent'], 'label' => 'غياب'],
            ['value' => count($studentStats), 'label' => 'عدد التلاميذ']
        ]);
        
        $headers = ['#', 'اسم التلميذ', 'حضور', 'تأخير', 'معذور', 'غياب', 'المجموع', 'النسبة'];
        $rows = [];
        $i = 1;
        foreach ($studentStats as $stats) {
            $rate = $stats['total'] > 0 ? round(($stats['present'] + $stats['late']) / $stats['total'] * 100) : 0;
            $rows[] = [
                $i++,
                $stats['name'],
                $stats['present'],
                $stats['late'],
                $stats['excused'],
                $stats['absent'],
                $stats['total'],
                $rate . '%'
            ];
        }
        
        $overallRate = $totals['total'] > 0 ? round(($totals['present'] + $totals['late']) / $totals['total'] * 100) : 0;
        $footerRow = ['', 'الإجمالي', $totals['present'], $totals['late'], $totals['excused'], $totals['absent'], $totals['total'], $overallRate . '%'];
        
        $content .= generateTable($headers, $rows, $footerRow);
        
        if ($format === 'excel') {
            exportAsExcel($title . ' - ' . $subtitle, $headers, $rows, 'تقرير_حضور_' . $classId . '_' . $section . '_' . $month . '_' . $year, $footerRow);
        } elseif ($format === 'word') {
            exportAsWord($title . ' - ' . $subtitle, $content, 'تقرير_حضور_' . $classId . '_' . $section . '_' . $month . '_' . $year);
        } else {
            exportAsPrintablePDF($title, $content, $subtitle);
        }
        break;
    
    // ═══════════════════════════════════════════════════════════════
    // تقرير دوام الكادر
    // ═══════════════════════════════════════════════════════════════
    case 'staff_attendance':
        if (!isAdmin()) {
            die('ليس لديك صلاحية');
        }
        
        require_once __DIR__ . '/models/User.php';
        require_once __DIR__ . '/models/TeacherAttendance.php';
        
        $month = (int)($_GET['month'] ?? date('n'));
        $year = (int)($_GET['year'] ?? date('Y'));
        
        $arabicMonths = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
        ];
        $monthName = $arabicMonths[$month] ?? $month;
        
        $userModel = new User();
        $teacherAttendanceModel = new TeacherAttendance();
        $teachers = $userModel->getTeachers();
        
        $title = 'تقرير دوام الكادر التعليمي والإداري';
        $subtitle = 'شهر ' . $monthName . ' ' . $year;
        
        $headers = ['#', 'اسم الموظف', 'الصلاحية', 'حضور', 'تأخير', 'غياب', 'المجموع', 'النسبة'];
        $rows = [];
        $totals = ['present' => 0, 'late' => 0, 'absent' => 0, 'total' => 0];
        $i = 1;
        
        foreach ($teachers as $teacher) {
            $stats = $teacherAttendanceModel->getTeacherStats($teacher['id'], $month, $year);
            $rate = $stats['total'] > 0 ? round(($stats['present'] + $stats['late']) / $stats['total'] * 100) : 0;
            
            $rows[] = [
                $i++,
                $teacher['full_name'],
                isset($teacher['role']) ? (ROLES[$teacher['role']] ?? $teacher['role']) : '-',
                $stats['present'],
                $stats['late'],
                $stats['absent'],
                $stats['total'],
                $rate . '%'
            ];
            
            $totals['present'] += $stats['present'];
            $totals['late'] += $stats['late'];
            $totals['absent'] += $stats['absent'];
            $totals['total'] += $stats['total'];
        }
        
        $overallRate = $totals['total'] > 0 ? round(($totals['present'] + $totals['late']) / $totals['total'] * 100) : 0;
        $footerRow = ['', 'الإجمالي', '', $totals['present'], $totals['late'], $totals['absent'], $totals['total'], $overallRate . '%'];
        
        $content = generateStatsGrid([
            ['value' => count($teachers), 'label' => 'عدد الكادر'],
            ['value' => $totals['present'], 'label' => 'إجمالي الحضور'],
            ['value' => $totals['late'], 'label' => 'إجمالي التأخير'],
            ['value' => $totals['absent'], 'label' => 'إجمالي الغياب'],
            ['value' => $overallRate . '%', 'label' => 'نسبة الحضور']
        ]);
        
        $content .= generateTable($headers, $rows, $footerRow);
        
        if ($format === 'excel') {
            exportAsExcel($title . ' - ' . $subtitle, $headers, $rows, 'تقرير_دوام_الكادر_' . $month . '_' . $year, $footerRow);
        } elseif ($format === 'word') {
            exportAsWord($title, $content, 'تقرير_دوام_الكادر_' . $month . '_' . $year);
        } else {
            exportAsPrintablePDF($title, $content, $subtitle);
        }
        break;
    
    // ═══════════════════════════════════════════════════════════════
    // تقرير الإجازات
    // ═══════════════════════════════════════════════════════════════
    case 'leaves':
        require_once __DIR__ . '/models/Leave.php';
        
        $personType = $_GET['person_type'] ?? 'student';
        $year = (int)($_GET['year'] ?? date('Y'));
        
        if ($personType === 'teacher' && !isAdmin()) {
            die('ليس لديك صلاحية');
        }
        
        $leaveModel = new Leave();
        $stats = $leaveModel->getStatistics($personType, $year);
        
        $title = 'تقرير الإجازات - ' . ($personType === 'teacher' ? 'الكادر' : 'التلاميذ');
        $subtitle = 'السنة الدراسية ' . $year;
        
        // جلب الإجازات
        if ($personType === 'teacher') {
            $leaves = $leaveModel->getTeacherLeaves(null, "$year-01-01", "$year-12-31");
        } else {
            $leaves = $leaveModel->getStudentLeaves(null, null, null, "$year-01-01", "$year-12-31");
        }
        
        $leaveTypes = ['sick' => 'مرضية', 'regular' => 'اعتيادية', 'emergency' => 'طارئة'];
        $statsSummary = ['sick' => 0, 'regular' => 0, 'emergency' => 0, 'total' => 0, 'days' => 0];
        
        foreach ($stats as $s) {
            $statsSummary[$s['leave_type']] = $s['leaves_count'];
            $statsSummary['total'] += $s['leaves_count'];
            $statsSummary['days'] += $s['total_days'];
        }
        
        $content = generateStatsGrid([
            ['value' => $statsSummary['total'], 'label' => 'إجمالي الإجازات'],
            ['value' => $statsSummary['days'], 'label' => 'إجمالي الأيام'],
            ['value' => $statsSummary['sick'], 'label' => 'مرضية'],
            ['value' => $statsSummary['regular'], 'label' => 'اعتيادية'],
            ['value' => $statsSummary['emergency'], 'label' => 'طارئة']
        ]);
        
        $headers = ['#', 'الاسم', 'نوع الإجازة', 'من', 'إلى', 'عدد الأيام', 'السبب'];
        $rows = [];
        $i = 1;
        
        foreach ($leaves as $leave) {
            $rows[] = [
                $i++,
                $leave['person_name'],
                $leaveTypes[$leave['leave_type']] ?? $leave['leave_type'],
                formatArabicDate($leave['start_date']),
                formatArabicDate($leave['end_date']),
                $leave['days_count'],
                $leave['reason'] ?: '-'
            ];
        }
        
        $content .= generateTable($headers, $rows);
        
        if ($format === 'excel') {
            exportAsExcel($title . ' - ' . $subtitle, $headers, $rows, 'تقرير_الاجازات_' . $personType . '_' . $year);
        } elseif ($format === 'word') {
            exportAsWord($title, $content, 'تقرير_الاجازات_' . $personType . '_' . $year);
        } else {
            exportAsPrintablePDF($title, $content, $subtitle);
        }
        break;
    
    // ═══════════════════════════════════════════════════════════════
    // سجل حضوري الشخصي
    // ═══════════════════════════════════════════════════════════════
    case 'my_attendance':
        if (isStudent()) {
            die('ليس لديك صلاحية');
        }
        
        require_once __DIR__ . '/models/TeacherAttendance.php';
        
        $currentUser = getCurrentUser();
        $month = $_GET['month'] ?? date('Y-m');
        list($year, $monthNum) = explode('-', $month);
        
        $arabicMonths = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
        ];
        $monthName = $arabicMonths[(int)$monthNum] ?? $monthNum;
        
        $teacherAttendanceModel = new TeacherAttendance();
        $stats = $teacherAttendanceModel->getTeacherStats($currentUser['id'], (int)$monthNum, (int)$year);
        
        $title = 'سجل الحضور الشخصي';
        $subtitle = $currentUser['full_name'] . ' | شهر ' . $monthName . ' ' . $year;
        
        $content = generateInfoSection('معلومات الموظف', [
            'الاسم' => $currentUser['full_name'],
            'الصلاحية' => ROLES[$currentUser['role']] ?? $currentUser['role'],
            'الشهر' => $monthName . ' ' . $year
        ]);
        
        $rate = $stats['total'] > 0 ? round(($stats['present'] + $stats['late']) / $stats['total'] * 100) : 0;
        
        $content .= generateStatsGrid([
            ['value' => $stats['present'], 'label' => 'أيام الحضور'],
            ['value' => $stats['late'], 'label' => 'أيام التأخير'],
            ['value' => $stats['absent'], 'label' => 'أيام الغياب'],
            ['value' => $stats['total'], 'label' => 'إجمالي الأيام'],
            ['value' => $rate . '%', 'label' => 'نسبة الحضور']
        ]);
        
        // جلب تفاصيل الحضور
        $startDate = "$year-" . str_pad($monthNum, 2, '0', STR_PAD_LEFT) . "-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $stmt = $conn->prepare("
            SELECT date, status, notes 
            FROM teacher_attendance 
            WHERE teacher_id = ? AND date >= ? AND date <= ?
            ORDER BY date
        ");
        $stmt->execute([$currentUser['id'], $startDate, $endDate]);
        $records = $stmt->fetchAll();
        
        $statusLabels = ['present' => 'حاضر', 'late' => 'متأخر', 'absent' => 'غائب'];
        $headers = ['#', 'التاريخ', 'الحالة', 'ملاحظات'];
        $rows = [];
        $i = 1;
        
        foreach ($records as $record) {
            $rows[] = [
                $i++,
                formatArabicDate($record['date']),
                $statusLabels[$record['status']] ?? $record['status'],
                $record['notes'] ?: '-'
            ];
        }
        
        $content .= '<h3 style="margin-top: 30px;">تفاصيل الحضور</h3>';
        $content .= generateTable($headers, $rows);
        
        if ($format === 'word') {
            exportAsWord($title, $content, 'سجل_حضوري_' . $month);
        } else {
            exportAsPrintablePDF($title, $content, $subtitle);
        }
        break;
    
    // ═══════════════════════════════════════════════════════════════
    // تقرير الدرجات
    // ═══════════════════════════════════════════════════════════════
    case 'grades':
        require_once __DIR__ . '/models/Subject.php';
        require_once __DIR__ . '/models/Grade.php';
        require_once __DIR__ . '/models/Student.php';
        
        $classId = (int)($_GET['class_id'] ?? 1);
        $section = $_GET['section'] ?? 'أ';
        $term = $_GET['term'] ?? 'first';
        $academicYear = $_GET['year'] ?? date('Y');
        
        $termNames = [
            'first' => 'الفصل الأول',
            'second' => 'الفصل الثاني',
            'final' => 'النهائي'
        ];
        
        $gradeModel = new Grade();
        $studentModel = new Student();
        $subjects = Subject::getSubjectsByClass($classId);
        $maxGrade = Subject::getMaxGrade($classId);
        $passingGrade = Subject::getPassingGrade($classId);
        
        // الحصول على النتائج
        $results = $gradeModel->getClassResults($classId, $section, $term, $academicYear);
        $stats = $gradeModel->getClassStatistics($classId, $section, $term, $academicYear);
        
        $title = 'تقرير درجات الصف ' . (CLASSES[$classId] ?? $classId);
        $subtitle = 'شعبة ' . $section . ' | ' . $termNames[$term] . ' - ' . $academicYear;
        
        // إنشاء محتوى الإحصائيات
        $passRate = $stats['total_students'] > 0 ? round(($stats['passed'] / $stats['total_students']) * 100) : 0;
        
        // بناء الرؤوس
        $headers = ['#', 'اسم التلميذ'];
        foreach ($subjects as $subject) {
            $headers[] = $subject;
        }
        $headers[] = 'المجموع';
        $headers[] = 'المعدل';
        $headers[] = 'النتيجة';
        
        // بناء الصفوف
        $rows = [];
        $i = 1;
        $totals = ['passed' => 0, 'failed' => 0, 'supp' => 0];
        
        foreach ($results as $result) {
            $studentGrades = $gradeModel->getStudentGrades($result['student']['id'], $term, $academicYear);
            $gradesBySubject = [];
            foreach ($studentGrades as $g) {
                $gradesBySubject[$g['subject_name']] = $g['grade'];
            }
            
            $row = [$i++, $result['student']['full_name']];
            
            foreach ($subjects as $subject) {
                $grade = $gradesBySubject[$subject] ?? '-';
                $row[] = $grade;
            }
            
            $row[] = $result['total'] ?: '-';
            $row[] = $result['average'] ? number_format($result['average'], 1) . '%' : '-';
            
            // النتيجة
            $resultText = '-';
            switch ($result['status']) {
                case 'pass': 
                    $resultText = 'ناجح'; 
                    $totals['passed']++;
                    break;
                case 'fail': 
                    $resultText = 'راسب'; 
                    $totals['failed']++;
                    break;
                case 'supp': 
                    $resultText = 'مكمّل'; 
                    $totals['supp']++;
                    break;
            }
            $row[] = $resultText;
            
            $rows[] = $row;
        }
        
        // صف الإجماليات
        $footerRow = ['', 'الإجمالي'];
        foreach ($subjects as $subject) {
            $footerRow[] = '-';
        }
        $footerRow[] = count($results) . ' تلميذ';
        $footerRow[] = 'نجاح: ' . $passRate . '%';
        $footerRow[] = 'ن:' . $totals['passed'] . ' م:' . $totals['supp'] . ' ر:' . $totals['failed'];
        
        if ($format === 'excel') {
            exportAsExcel($title . ' - ' . $subtitle, $headers, $rows, 'درجات_' . $classId . '_' . $section . '_' . $term . '_' . $academicYear, $footerRow);
        } elseif ($format === 'word') {
            $content = generateStatsGrid([
                ['value' => count($results), 'label' => 'عدد التلاميذ'],
                ['value' => $stats['passed'], 'label' => 'ناجحون'],
                ['value' => $stats['supplementary'], 'label' => 'مكمّلون'],
                ['value' => $stats['failed'], 'label' => 'راسبون'],
                ['value' => $passRate . '%', 'label' => 'نسبة النجاح']
            ]);
            
            $content .= '<div style="margin: 20px 0;"><strong>معلومات نظام الدرجات:</strong> ';
            $content .= 'الدرجة القصوى: ' . $maxGrade . ' | ';
            $content .= 'درجة النجاح: ' . $passingGrade . '</div>';
            
            $content .= generateTable($headers, $rows, $footerRow);
            
            exportAsWord($title, $content, 'درجات_' . $classId . '_' . $section . '_' . $term . '_' . $academicYear);
        } else {
            // PDF
            $content = generateStatsGrid([
                ['value' => count($results), 'label' => 'عدد التلاميذ'],
                ['value' => $stats['passed'], 'label' => 'ناجحون'],
                ['value' => $stats['supplementary'], 'label' => 'مكمّلون'],
                ['value' => $stats['failed'], 'label' => 'راسبون'],
                ['value' => $passRate . '%', 'label' => 'نسبة النجاح']
            ]);
            
            $content .= '<div class="info-section" style="margin: 15px 0; padding: 10px; background: #f8f9fa; border-radius: 8px;">';
            $content .= '<strong>📊 معلومات نظام الدرجات:</strong><br>';
            $content .= '• الدرجة القصوى: <strong>' . $maxGrade . '</strong> | ';
            $content .= '• درجة النجاح: <strong>' . $passingGrade . '</strong> | ';
            $content .= '• المكمّل: مادتان راسبتان | ';
            $content .= '• الراسب: ٣ مواد فأكثر';
            $content .= '</div>';
            
            $content .= generateTable($headers, $rows, $footerRow);
            
            exportAsPrintablePDF($title, $content, $subtitle);
        }
        break;
    
    // ═══════════════════════════════════════════════════════════════
    // تقرير درجات تلميذ واحد
    // ═══════════════════════════════════════════════════════════════
    case 'student_grades':
        require_once __DIR__ . '/models/Subject.php';
        require_once __DIR__ . '/models/Grade.php';
        require_once __DIR__ . '/models/Student.php';
        
        $studentId = (int)($_GET['student_id'] ?? 0);
        $term = $_GET['term'] ?? 'first';
        $academicYear = $_GET['year'] ?? date('Y');
        
        if (!$studentId) {
            die('معرف التلميذ مطلوب');
        }
        
        $studentModel = new Student();
        $gradeModel = new Grade();
        
        $student = $studentModel->getById($studentId);
        if (!$student) {
            die('التلميذ غير موجود');
        }
        
        $classId = $student['class_id'];
        $subjects = Subject::getSubjectsByClass($classId);
        $maxGrade = Subject::getMaxGrade($classId);
        
        $termNames = [
            'first' => 'الفصل الأول',
            'second' => 'الفصل الثاني',
            'final' => 'النهائي'
        ];
        
        $studentGrades = $gradeModel->getStudentGrades($studentId, $term, $academicYear);
        $result = $gradeModel->calculateResult($studentId, $classId, $term, $academicYear);
        
        $gradesBySubject = [];
        foreach ($studentGrades as $g) {
            $gradesBySubject[$g['subject_name']] = $g['grade'];
        }
        
        $title = 'بطاقة درجات التلميذ';
        $subtitle = $student['full_name'] . ' | ' . $termNames[$term] . ' - ' . $academicYear;
        
        // معلومات التلميذ
        $content = generateInfoSection('معلومات التلميذ', [
            'الاسم الكامل' => $student['full_name'],
            'الصف' => CLASSES[$student['class_id']] ?? $student['class_id'],
            'الشعبة' => $student['section'],
            'الفترة' => $termNames[$term],
            'السنة الدراسية' => $academicYear
        ]);
        
        // جدول الدرجات
        $headers = ['#', 'المادة', 'الدرجة', 'من', 'الحالة'];
        $rows = [];
        $i = 1;
        
        foreach ($subjects as $subject) {
            $grade = $gradesBySubject[$subject] ?? '-';
            $status = '-';
            if ($grade !== '-') {
                if (Subject::usesTenPointSystem($classId)) {
                    $status = $grade > 4 ? 'ناجح' : 'راسب';
                } else {
                    $status = $grade >= 50 ? 'ناجح' : 'راسب';
                }
            }
            $rows[] = [$i++, $subject, $grade, $maxGrade, $status];
        }
        
        $content .= generateTable($headers, $rows);
        
        // النتيجة النهائية
        $resultText = '-';
        $resultClass = '';
        switch ($result['status']) {
            case 'pass': $resultText = 'ناجح ✅'; $resultClass = 'success'; break;
            case 'fail': $resultText = 'راسب ❌'; $resultClass = 'danger'; break;
            case 'supp': $resultText = 'مكمّل ⚠️'; $resultClass = 'warning'; break;
        }
        
        $content .= '<div class="stats-grid" style="margin-top: 20px;">';
        $content .= '<div class="stat-box"><div class="stat-value">' . ($result['total'] ?: '-') . '</div><div class="stat-label">المجموع</div></div>';
        $content .= '<div class="stat-box"><div class="stat-value">' . ($result['average'] ? number_format($result['average'], 1) . '%' : '-') . '</div><div class="stat-label">المعدل</div></div>';
        $content .= '<div class="stat-box" style="border-color: var(--' . $resultClass . ');"><div class="stat-value">' . $resultText . '</div><div class="stat-label">النتيجة</div></div>';
        $content .= '</div>';
        
        if (!empty($result['failed_subjects'])) {
            $content .= '<div style="margin-top: 15px; padding: 10px; background: #fee2e2; border-radius: 8px; color: #991b1b;">';
            $content .= '<strong>المواد الراسبة:</strong> ' . implode('، ', $result['failed_subjects']);
            $content .= '</div>';
        }
        
        if ($format === 'word') {
            exportAsWord($title, $content, 'درجات_' . $student['full_name'] . '_' . $term . '_' . $academicYear);
        } else {
            exportAsPrintablePDF($title, $content, $subtitle);
        }
        break;
    
    // ═══════════════════════════════════════════════════════════════
    // قائمة التلاميذ
    // ═══════════════════════════════════════════════════════════════
    case 'students_list':
        require_once __DIR__ . '/models/Student.php';
        
        $classId = isset($_GET['class_id']) && $_GET['class_id'] !== '' ? (int)$_GET['class_id'] : null;
        $section = isset($_GET['section']) && $_GET['section'] !== '' ? $_GET['section'] : null;
        
        $studentModel = new Student();
        $students = $studentModel->getAll($classId, $section);
        
        $title = 'سجل التلاميذ';
        $subtitle = '';
        if ($classId && isset(CLASSES[$classId])) {
            $subtitle .= CLASSES[$classId];
            if ($section) {
                $subtitle .= ' - شعبة ' . $section;
            }
        } else {
            $subtitle = 'جميع الصفوف';
        }
        $subtitle .= ' | تاريخ: ' . date('Y/m/d');
        
        // إحصائيات
        $totalStudents = count($students);
        $maleCount = 0;
        $femaleCount = 0;
        foreach ($students as $s) {
            if (($s['gender'] ?? 'male') === 'male') $maleCount++;
            else $femaleCount++;
        }
        
        $content = generateStatsGrid([
            ['value' => $totalStudents, 'label' => 'إجمالي التلاميذ'],
            ['value' => $maleCount, 'label' => 'ذكور'],
            ['value' => $femaleCount, 'label' => 'إناث']
        ]);
        
        $headers = ['#', 'الاسم الكامل', 'الصف', 'الشعبة', 'تاريخ الميلاد', 'ولي الأمر', 'الهاتف'];
        $rows = [];
        $i = 1;
        
        foreach ($students as $student) {
            $rows[] = [
                $i++,
                $student['full_name'],
                CLASSES[$student['class_id']] ?? $student['class_id'],
                $student['section'],
                !empty($student['birth_date']) ? formatArabicDate($student['birth_date']) : '-',
                $student['parent_name'] ?? '-',
                $student['parent_phone'] ?? '-'
            ];
        }
        
        $footerRow = ['', 'الإجمالي', $totalStudents . ' تلميذ', '', '', '', ''];
        
        $content .= generateTable($headers, $rows, $footerRow);
        
        if ($format === 'excel') {
            exportAsExcel($title . ' - ' . $subtitle, $headers, $rows, 'سجل_التلاميذ_' . ($classId ?: 'الكل') . '_' . date('Y-m-d'), $footerRow);
        } elseif ($format === 'word') {
            exportAsWord($title, $content, 'سجل_التلاميذ_' . ($classId ?: 'الكل') . '_' . date('Y-m-d'));
        } else {
            exportAsPrintablePDF($title, $content, $subtitle);
        }
        break;
    
    // ═══════════════════════════════════════════════════════════════
    // قائمة المعلمين
    // ═══════════════════════════════════════════════════════════════
    case 'teachers_list':
        require_once __DIR__ . '/models/Teacher.php';
        
        $teacherModel = new Teacher();
        $teachers = $teacherModel->getAll();
        
        $title = 'سجل الكادر التعليمي والإداري';
        $subtitle = 'مدرسة بعشيقة الابتدائية للبنين | تاريخ: ' . date('Y/m/d');
        
        $content = generateStatsGrid([
            ['value' => count($teachers), 'label' => 'إجمالي الكادر']
        ]);
        
        $headers = ['#', 'الاسم الكامل', 'التخصص', 'الشهادة', 'تاريخ التعيين', 'الهاتف'];
        $rows = [];
        $i = 1;
        
        foreach ($teachers as $teacher) {
            $rows[] = [
                $i++,
                $teacher['full_name'],
                $teacher['specialization'] ?? '-',
                $teacher['certificate'] ?? '-',
                !empty($teacher['hire_date']) ? formatArabicDate($teacher['hire_date']) : '-',
                $teacher['phone'] ?? '-'
            ];
        }
        
        $footerRow = ['', 'الإجمالي', count($teachers) . ' عضو', '', '', ''];
        
        $content .= generateTable($headers, $rows, $footerRow);
        
        if ($format === 'word') {
            exportAsWord($title, $content, 'سجل_الكادر_' . date('Y-m-d'));
        } else {
            exportAsPrintablePDF($title, $content, $subtitle);
        }
        break;
    
    // ═══════════════════════════════════════════════════════════════
    // بيانات تلميذ واحد
    // ═══════════════════════════════════════════════════════════════
    case 'student_profile':
        require_once __DIR__ . '/models/Student.php';
        
        $studentId = (int)($_GET['student_id'] ?? 0);
        if (!$studentId) {
            die('معرف التلميذ مطلوب');
        }
        
        $studentModel = new Student();
        $student = $studentModel->findById($studentId);
        
        if (!$student) {
            die('التلميذ غير موجود');
        }
        
        $title = 'بطاقة بيانات التلميذ';
        $subtitle = $student['full_name'];
        
        $content = generateInfoSection('📋 البيانات الأساسية', [
            'الاسم الكامل' => $student['full_name'],
            'الصف' => CLASSES[$student['class_id']] ?? $student['class_id'],
            'الشعبة' => $student['section'],
            'الجنس' => ($student['gender'] ?? 'male') === 'male' ? 'ذكر' : 'أنثى',
            'تاريخ الميلاد' => !empty($student['birth_date']) ? formatArabicDate($student['birth_date']) : '-',
            'المحافظة' => $student['province'] ?? '-',
            'العنوان' => $student['address'] ?? '-'
        ]);
        
        $content .= generateInfoSection('👨‍👩‍👦 بيانات ولي الأمر', [
            'اسم ولي الأمر' => $student['parent_name'] ?? '-',
            'صلته بالتلميذ' => $student['guardian_relation'] ?? '-',
            'رقم الهاتف' => $student['parent_phone'] ?? '-',
            'اسم الأم' => $student['mother_name'] ?? '-'
        ]);
        
        if ($format === 'word') {
            exportAsWord($title, $content, 'بطاقة_' . $student['full_name']);
        } else {
            exportAsPrintablePDF($title, $content, $subtitle);
        }
        break;
    
    // ═══════════════════════════════════════════════════════════════
    // بيانات معلم واحد
    // ═══════════════════════════════════════════════════════════════
    case 'teacher_profile':
        require_once __DIR__ . '/models/Teacher.php';
        
        $teacherId = (int)($_GET['teacher_id'] ?? 0);
        if (!$teacherId) {
            die('معرف المعلم مطلوب');
        }
        
        $teacherModel = new Teacher();
        $teacher = $teacherModel->findById($teacherId);
        
        if (!$teacher) {
            die('المعلم غير موجود');
        }
        
        $title = 'بطاقة بيانات عضو الكادر';
        $subtitle = $teacher['full_name'];
        
        $content = generateInfoSection('📋 البيانات الشخصية', [
            'الاسم الكامل' => $teacher['full_name'],
            'محل الولادة' => $teacher['birth_place'] ?? '-',
            'تاريخ الولادة' => !empty($teacher['birth_date']) ? formatArabicDate($teacher['birth_date']) : '-',
            'رقم الهاتف' => $teacher['phone'] ?? '-',
            'فصيلة الدم' => $teacher['blood_type'] ?? '-'
        ]);
        
        $content .= generateInfoSection('🎓 الشهادة والتخصص', [
            'الشهادة' => $teacher['certificate'] ?? '-',
            'التخصص' => $teacher['specialization'] ?? '-',
            'المعهد/الكلية' => $teacher['institute_name'] ?? '-',
            'سنة التخرج' => $teacher['graduation_year'] ?? '-'
        ]);
        
        $content .= generateInfoSection('💼 بيانات التعيين', [
            'تاريخ التعيين' => !empty($teacher['hire_date']) ? formatArabicDate($teacher['hire_date']) : '-',
            'الدرجة الوظيفية' => $teacher['job_grade'] ?? '-',
            'المرحلة الوظيفية' => $teacher['career_stage'] ?? '-',
            'رقم البطاقة الوطنية' => $teacher['national_id'] ?? '-'
        ]);
        
        if ($format === 'word') {
            exportAsWord($title, $content, 'بطاقة_' . $teacher['full_name']);
        } else {
            exportAsPrintablePDF($title, $content, $subtitle);
        }
        break;
    
    // ═══════════════════════════════════════════════════════════════
    // إجازاتي الشخصية
    // ═══════════════════════════════════════════════════════════════
    case 'my_leaves':
        require_once __DIR__ . '/models/Leave.php';
        
        $currentUser = getCurrentUser();
        $year = $_GET['year'] ?? date('Y');
        
        $leaveModel = new Leave();
        
        // تحديد نوع الشخص
        if (isStudent()) {
            $stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
            $stmt->execute([$currentUser['id']]);
            $student = $stmt->fetch();
            $personType = Leave::PERSON_STUDENT;
            $personId = $student['id'] ?? 0;
        } else {
            $personType = Leave::PERSON_TEACHER;
            $personId = $currentUser['id'];
        }
        
        $leaves = $leaveModel->getByPerson($personType, $personId, null, "$year-01-01", "$year-12-31");
        $summary = $leaveModel->getPersonSummary($personType, $personId, $year);
        
        $title = 'سجل إجازاتي';
        $subtitle = $currentUser['full_name'] . ' | العام ' . $year;
        
        $content = generateStatsGrid([
            ['value' => $summary['total']['days'], 'label' => 'إجمالي الأيام'],
            ['value' => $summary['sick']['days'], 'label' => 'مرضية'],
            ['value' => $summary['regular']['days'], 'label' => 'اعتيادية'],
            ['value' => $summary['emergency']['days'], 'label' => 'طارئة']
        ]);
        
        $headers = ['#', 'نوع الإجازة', 'من', 'إلى', 'عدد الأيام', 'السبب'];
        $rows = [];
        $i = 1;
        
        foreach ($leaves as $leave) {
            $typeNames = ['sick' => 'مرضية', 'regular' => 'اعتيادية', 'emergency' => 'طارئة'];
            $rows[] = [
                $i++,
                $typeNames[$leave['leave_type']] ?? $leave['leave_type'],
                formatArabicDate($leave['start_date']),
                formatArabicDate($leave['end_date']),
                $leave['days_count'] . ' يوم',
                $leave['reason'] ?: '-'
            ];
        }
        
        $content .= generateTable($headers, $rows);
        
        exportAsPrintablePDF($title, $content, $subtitle);
        break;
    
    // ═══════════════════════════════════════════════════════════════
    // سجل حضوري الشخصي
    // ═══════════════════════════════════════════════════════════════
    case 'my_attendance':
        require_once __DIR__ . '/models/TeacherAttendance.php';
        require_once __DIR__ . '/models/Teacher.php';
        
        $currentUser = getCurrentUser();
        $monthFilter = $_GET['month'] ?? date('Y-m');
        $yearMonth = explode('-', $monthFilter);
        $year = $yearMonth[0] ?? date('Y');
        $month = $yearMonth[1] ?? date('m');
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $teacherAttendanceModel = new TeacherAttendance();
        $teacherModel = new Teacher();
        $teacherInfo = $teacherModel->findByUserId($currentUser['id']);
        
        $attendance = [];
        $generalStats = ['present' => 0, 'late' => 0, 'absent' => 0, 'total' => 0];
        
        if ($teacherInfo) {
            $attendance = $teacherAttendanceModel->getByTeacher($currentUser['id'], $startDate, $endDate);
            $generalStats = $teacherAttendanceModel->getTeacherStats($currentUser['id'], $month, $year);
        }
        
        $arabicMonths = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
        $monthName = $arabicMonths[(int)$month] ?? '';
        
        $title = 'سجل حضور الحصص';
        $subtitle = ($teacherInfo['full_name'] ?? $currentUser['full_name']) . ' | ' . $monthName . ' ' . $year;
        
        $attendanceRate = $generalStats['total'] > 0 
            ? round(($generalStats['present'] + $generalStats['late']) / $generalStats['total'] * 100) 
            : 0;
        
        $content = generateStatsGrid([
            ['value' => $generalStats['present'], 'label' => 'حضور'],
            ['value' => $generalStats['late'], 'label' => 'تأخير'],
            ['value' => $generalStats['absent'], 'label' => 'غياب'],
            ['value' => $attendanceRate . '%', 'label' => 'نسبة الحضور']
        ]);
        
        $headers = ['التاريخ', 'الحصة', 'المادة', 'الصف', 'الحالة'];
        $rows = [];
        
        $statusNames = ['present' => 'حاضر', 'late' => 'متأخر', 'absent' => 'غائب'];
        
        foreach ($attendance as $record) {
            $rows[] = [
                formatArabicDate($record['date']),
                isset(LESSONS[$record['lesson_number']]) ? LESSONS[$record['lesson_number']]['name'] : 'الحصة ' . $record['lesson_number'],
                $record['subject_name'],
                (CLASSES[$record['class_id']] ?? $record['class_id']) . ' - ' . $record['section'],
                $statusNames[$record['status']] ?? $record['status']
            ];
        }
        
        $content .= generateTable($headers, $rows);
        
        exportAsPrintablePDF($title, $content, $subtitle);
        break;
    
    // ═══════════════════════════════════════════════════════════════
    // الخطأ الافتراضي
    // ═══════════════════════════════════════════════════════════════
    default:
        die('نوع التقرير غير صالح');
}
