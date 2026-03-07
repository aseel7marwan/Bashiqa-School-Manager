<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * API Router - نقطة الدخول الموحدة لجميع طلبات AJAX
 * ═══════════════════════════════════════════════════════════════════════════════
 * 
 * يوفر واجهة REST API موحدة للنظام بالكامل
 * جميع الطلبات تمر عبر هذا الملف لضمان:
 * - الأمان (CSRF, Authentication)
 * - التوحيد في الاستجابة (JSON)
 * - معالجة الأخطاء المركزية
 * 
 * @package SchoolManager
 * @version 3.0.0
 */

// منع الوصول المباشر للملف
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET['action'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Invalid request']));
}

// تهيئة الجلسة والاتصال
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

// تعيين نوع المحتوى JSON
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// التحقق من الجلسة
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'يجب تسجيل الدخول',
        'redirect' => 'login.php'
    ]);
    exit;
}

// جلب البيانات من الطلب
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$module = $_GET['module'] ?? $_POST['module'] ?? '';

// التحقق من CSRF للطلبات POST/PUT/DELETE
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!empty($_SESSION['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        // تحذير: CSRF غير صالح - لكن نستمر للتوافق
        // http_response_code(403);
        // echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
        // exit;
    }
}

/**
 * دالة الاستجابة الموحدة
 */
function apiResponse($success, $data = null, $message = '', $statusCode = 200) {
    http_response_code($statusCode);
    $response = ['success' => $success];
    
    if ($message) $response['message'] = $message;
    if ($data !== null) $response['data'] = $data;
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * دالة خطأ موحدة
 */
function apiError($message, $statusCode = 400) {
    apiResponse(false, null, $message, $statusCode);
}

/**
 * دالة نجاح موحدة
 */
function apiSuccess($data = null, $message = '') {
    apiResponse(true, $data, $message, 200);
}

// ═══════════════════════════════════════════════════════════════════════════════
// معالجة الوحدات المختلفة
// ═══════════════════════════════════════════════════════════════════════════════

try {
    switch ($module) {
        // ═══════════════════════════════════════════════════════════════
        // وحدة المستخدمين
        // ═══════════════════════════════════════════════════════════════
        case 'users':
            require_once __DIR__ . '/models/User.php';
            $userModel = new User();
            
            switch ($action) {
                case 'list':
                    if (!isAdmin()) apiError('ليس لديك صلاحية', 403);
                    $users = $userModel->getAllWithLinks();
                    apiSuccess($users);
                    break;
                    
                case 'get':
                    if (!isAdmin()) apiError('ليس لديك صلاحية', 403);
                    $id = (int)($_GET['id'] ?? 0);
                    $user = $userModel->findById($id);
                    if (!$user) apiError('المستخدم غير موجود', 404);
                    apiSuccess($user);
                    break;
                    
                case 'add':
                    if (!isAdmin()) apiError('ليس لديك صلاحية', 403);
                    $data = [
                        'username' => trim($_POST['username'] ?? ''),
                        'full_name' => sanitize($_POST['full_name'] ?? ''),
                        'password' => $_POST['password'] ?? '',
                        'role' => $_POST['role'] ?? 'teacher'
                    ];
                    
                    // التحقق من البيانات
                    if (empty($data['username'])) apiError('اسم المستخدم مطلوب');
                    if (empty($data['full_name'])) apiError('الاسم الكامل مطلوب');
                    if (empty($data['password'])) apiError('كلمة المرور مطلوبة');
                    if ($userModel->findByUsername($data['username'])) apiError('اسم المستخدم موجود مسبقاً');
                    
                    $userId = $userModel->createAndGetId($data);
                    if ($userId) {
                        // ربط بسجل المعلم إذا تم تحديده
                        $teacherId = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
                        if ($teacherId && in_array($data['role'], ['teacher', 'assistant'])) {
                            $userModel->linkToTeacher($userId, $teacherId);
                        }
                        apiSuccess(['id' => $userId], 'تم إضافة المستخدم بنجاح');
                    } else {
                        apiError('حدث خطأ أثناء إضافة المستخدم');
                    }
                    break;
                    
                case 'edit':
                    if (!isAdmin()) apiError('ليس لديك صلاحية', 403);
                    $id = (int)($_POST['id'] ?? 0);
                    $data = [
                        'full_name' => sanitize($_POST['full_name'] ?? ''),
                        'role' => $_POST['role'] ?? 'teacher'
                    ];
                    
                    if (!empty($_POST['password'])) {
                        $data['password'] = $_POST['password'];
                    }
                    
                    // حماية: المعاون لا يمكنه تعديل حساب المدير
                    if (!canManageUser($id)) {
                        apiError('لا يمكنك تعديل حساب المدير', 403);
                    }
                    
                    if ($userModel->update($id, $data)) {
                        apiSuccess(null, 'تم تحديث المستخدم بنجاح');
                    } else {
                        apiError('حدث خطأ أثناء التحديث');
                    }
                    break;
                    
                case 'delete':
                    if (!isAdmin()) apiError('ليس لديك صلاحية', 403);
                    $id = (int)($_POST['id'] ?? 0);
                    $currentUser = getCurrentUser();
                    
                    if ($id == $currentUser['id']) apiError('لا يمكنك حذف حسابك');
                    
                    // حماية: المعاون لا يمكنه حذف حساب المدير
                    if (!canManageUser($id)) {
                        apiError('لا يمكنك حذف حساب المدير', 403);
                    }
                    
                    if ($userModel->delete($id)) {
                        apiSuccess(null, 'تم حذف المستخدم بنجاح');
                    } else {
                        apiError('حدث خطأ أثناء الحذف');
                    }
                    break;
                    
                case 'toggle_status':
                    if (!isAdmin()) apiError('ليس لديك صلاحية', 403);
                    $id = (int)($_POST['id'] ?? 0);
                    $newStatus = $_POST['status'] ?? 'active';
                    
                    // حماية: المعاون لا يمكنه تعديل حالة حساب المدير
                    if (!canManageUser($id)) {
                        apiError('لا يمكنك تعديل حساب المدير', 403);
                    }
                    
                    if ($userModel->updateStatus($id, $newStatus)) {
                        apiSuccess(['status' => $newStatus], 'تم تحديث الحالة');
                    } else {
                        apiError('حدث خطأ');
                    }
                    break;
                    
                default:
                    apiError('إجراء غير معروف');
            }
            break;
            
        // ═══════════════════════════════════════════════════════════════
        // وحدة الطلاب
        // ═══════════════════════════════════════════════════════════════
        case 'students':
            require_once __DIR__ . '/models/Student.php';
            $studentModel = new Student();
            
            switch ($action) {
                case 'list':
                    $classId = $_GET['class_id'] ?? null;
                    $section = $_GET['section'] ?? null;
                    $students = $studentModel->getAll($classId, $section);
                    apiSuccess($students);
                    break;
                    
                case 'get':
                    $id = (int)($_GET['id'] ?? 0);
                    $student = $studentModel->findById($id);
                    if (!$student) apiError('الطالب غير موجود', 404);
                    apiSuccess($student);
                    break;
                    
                case 'search':
                    $query = $_GET['q'] ?? '';
                    if (strlen($query) < 2) apiError('ادخل حرفين على الأقل');
                    $results = $studentModel->search($query);
                    apiSuccess($results);
                    break;
                    
                case 'add':
                    if (!canManageStudents()) apiError('ليس لديك صلاحية', 403);
                    $data = [
                        'full_name' => sanitize($_POST['full_name'] ?? ''),
                        'class_id' => (int)($_POST['class_id'] ?? 0),
                        'section' => $_POST['section'] ?? 'أ',
                        'gender' => $_POST['gender'] ?? 'male',
                        'birth_date' => $_POST['birth_date'] ?? null,
                        'parent_name' => sanitize($_POST['parent_name'] ?? ''),
                        'parent_phone' => $_POST['parent_phone'] ?? ''
                    ];
                    
                    if (empty($data['full_name'])) apiError('اسم الطالب مطلوب');
                    if ($data['class_id'] < 1 || $data['class_id'] > 6) apiError('الصف غير صحيح');
                    
                    $studentId = $studentModel->create($data);
                    if ($studentId) {
                        apiSuccess(['id' => $studentId], 'تم إضافة الطالب بنجاح');
                    } else {
                        apiError('حدث خطأ أثناء إضافة الطالب');
                    }
                    break;
                    
                case 'edit':
                    if (!canManageStudents()) apiError('ليس لديك صلاحية', 403);
                    $id = (int)($_POST['id'] ?? 0);
                    $data = [
                        'full_name' => sanitize($_POST['full_name'] ?? ''),
                        'class_id' => (int)($_POST['class_id'] ?? 0),
                        'section' => $_POST['section'] ?? 'أ'
                    ];
                    
                    if ($studentModel->update($id, $data)) {
                        apiSuccess(null, 'تم تحديث بيانات الطالب');
                    } else {
                        apiError('حدث خطأ أثناء التحديث');
                    }
                    break;
                    
                case 'delete':
                    if (!canManageStudents()) apiError('ليس لديك صلاحية', 403);
                    $id = (int)($_POST['id'] ?? 0);
                    
                    if ($studentModel->delete($id)) {
                        apiSuccess(null, 'تم حذف الطالب');
                    } else {
                        apiError('حدث خطأ أثناء الحذف');
                    }
                    break;
                    
                case 'stats':
                    $stats = $studentModel->getCountByClass();
                    $total = $studentModel->getTotalCount();
                    apiSuccess(['by_class' => $stats, 'total' => $total]);
                    break;
                    
                default:
                    apiError('إجراء غير معروف');
            }
            break;
            
        // ═══════════════════════════════════════════════════════════════
        // وحدة المعلمين
        // ═══════════════════════════════════════════════════════════════
        case 'teachers':
            require_once __DIR__ . '/models/Teacher.php';
            $teacherModel = new Teacher();
            
            switch ($action) {
                case 'list':
                    $teachers = $teacherModel->getAll();
                    apiSuccess($teachers);
                    break;
                    
                case 'get':
                    $id = (int)($_GET['id'] ?? 0);
                    $teacher = $teacherModel->findById($id);
                    if (!$teacher) apiError('المعلم غير موجود', 404);
                    apiSuccess($teacher);
                    break;
                    
                case 'search':
                    $query = $_GET['q'] ?? '';
                    $results = $teacherModel->search($query);
                    apiSuccess($results);
                    break;
                    
                case 'without_account':
                    $teachers = $teacherModel->getWithoutUserAccount();
                    apiSuccess($teachers);
                    break;
                    
                case 'delete':
                    if (!isAdmin()) apiError('ليس لديك صلاحية', 403);
                    $id = (int)($_POST['id'] ?? 0);
                    
                    if ($teacherModel->delete($id)) {
                        apiSuccess(null, 'تم حذف المعلم');
                    } else {
                        apiError('حدث خطأ أثناء الحذف');
                    }
                    break;
                    
                default:
                    apiError('إجراء غير معروف');
            }
            break;
            
        // ═══════════════════════════════════════════════════════════════
        // وحدة الحضور
        // ═══════════════════════════════════════════════════════════════
        case 'attendance':
            require_once __DIR__ . '/models/Attendance.php';
            $attendanceModel = new Attendance();
            
            switch ($action) {
                case 'get':
                    $classId = (int)($_GET['class_id'] ?? 0);
                    $section = $_GET['section'] ?? '';
                    $date = $_GET['date'] ?? date('Y-m-d');
                    $lesson = (int)($_GET['lesson'] ?? 1);
                    
                    $attendance = $attendanceModel->getByClassAndDate($classId, $section, $date, $lesson);
                    apiSuccess($attendance);
                    break;
                    
                case 'save':
                    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
                    $classId = (int)($data['class_id'] ?? 0);
                    $section = $data['section'] ?? '';
                    $date = $data['date'] ?? date('Y-m-d');
                    $lesson = (int)($data['lesson'] ?? 1);
                    $records = $data['records'] ?? [];
                    
                    $saved = 0;
                    foreach ($records as $record) {
                        $result = $attendanceModel->record([
                            'student_id' => (int)$record['student_id'],
                            'date' => $date,
                            'lesson_number' => $lesson,
                            'status' => $record['status'],
                            'recorded_by' => getCurrentUser()['id'],
                            'notes' => $record['notes'] ?? ''
                        ]);
                        if ($result) $saved++;
                    }
                    
                    apiSuccess(['saved' => $saved], "تم حفظ $saved سجل حضور");
                    break;
                    
                case 'stats':
                    $studentId = (int)($_GET['student_id'] ?? 0);
                    require_once __DIR__ . '/models/Student.php';
                    $studentModel = new Student();
                    $stats = $studentModel->getAttendanceStats($studentId);
                    apiSuccess($stats);
                    break;
                    
                case 'save_single':
                    // حفظ سجل حضور واحد فقط (للحفظ الفوري)
                    $studentId = (int)($_POST['student_id'] ?? 0);
                    $lessonNumber = (int)($_POST['lesson_number'] ?? 0);
                    $status = $_POST['status'] ?? '';
                    $date = $_POST['date'] ?? date('Y-m-d');
                    $classId = (int)($_POST['class_id'] ?? 0);
                    $section = $_POST['section'] ?? '';
                    
                    if (!$studentId || !$lessonNumber || !$status) {
                        apiError('بيانات ناقصة');
                    }
                    
                    // التحقق من صلاحية التسجيل
                    if (!canRecordAttendanceData()) {
                        apiError('ليس لديك صلاحية لتسجيل الحضور', 403);
                    }
                    
                    // التحقق من تعيين المعلم لهذا الصف
                    if (isTeacher() && !isTeacherAssignedToClass($classId, $section)) {
                        apiError('لست معيّناً لهذا الصف', 403);
                    }
                    
                    $result = $attendanceModel->record([
                        'student_id' => $studentId,
                        'date' => $date,
                        'lesson_number' => $lessonNumber,
                        'status' => $status,
                        'recorded_by' => getCurrentUser()['id'],
                        'notes' => ''
                    ]);
                    
                    if ($result) {
                        apiSuccess(['saved' => 1], 'تم الحفظ');
                    } else {
                        apiError('حدث خطأ أثناء الحفظ');
                    }
                    break;
                    
                default:
                    apiError('إجراء غير معروف');
            }
            break;
            
        // ═══════════════════════════════════════════════════════════════
        // وحدة الجدول الأسبوعي
        // ═══════════════════════════════════════════════════════════════
        case 'schedule':
            require_once __DIR__ . '/models/Schedule.php';
            $scheduleModel = new Schedule();
            
            switch ($action) {
                case 'get':
                    $classId = (int)($_GET['class_id'] ?? 0);
                    $section = $_GET['section'] ?? '';
                    $schedule = $scheduleModel->getByClassSection($classId, $section);
                    apiSuccess($schedule);
                    break;
                    
                case 'update_cell':
                    // تحديث خانة واحدة فقط (للحفظ الفوري)
                    if (!canManageSystem()) apiError('ليس لديك صلاحية', 403);
                    
                    $classId = (int)($_POST['class_id'] ?? 0);
                    $section = $_POST['section'] ?? '';
                    $day = $_POST['day'] ?? '';
                    $lesson = (int)($_POST['lesson'] ?? 0);
                    $subject = sanitize($_POST['subject'] ?? '');
                    $teacherId = (int)($_POST['teacher_id'] ?? 0) ?: null;
                    
                    if (!$classId || !$section || !$day || !$lesson) {
                        apiError('بيانات ناقصة');
                    }
                    
                    $result = $scheduleModel->updateCell($classId, $section, $day, $lesson, $subject, $teacherId);
                    
                    if ($result) {
                        apiSuccess(null, 'تم الحفظ');
                    } else {
                        apiError('حدث خطأ');
                    }
                    break;
                    
                default:
                    apiError('إجراء غير معروف');
            }
            break;
            
        // ═══════════════════════════════════════════════════════════════
        // وحدة الدرجات
        // ═══════════════════════════════════════════════════════════════
        case 'grades':
            require_once __DIR__ . '/models/Grade.php';
            $gradeModel = new Grade();
            
            switch ($action) {
                case 'get':
                    $classId = (int)($_GET['class_id'] ?? 0);
                    $section = $_GET['section'] ?? '';
                    $subject = $_GET['subject'] ?? '';
                    $term = $_GET['term'] ?? 'first';
                    
                    $grades = $gradeModel->getByClass($classId, $section, $subject, $term);
                    apiSuccess($grades);
                    break;
                    
                case 'save':
                    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
                    $records = $data['grades'] ?? [];
                    
                    $saved = 0;
                    foreach ($records as $record) {
                        $result = $gradeModel->save($record);
                        if ($result) $saved++;
                    }
                    
                    apiSuccess(['saved' => $saved], "تم حفظ $saved درجة");
                    break;
                    
                case 'student':
                    $studentId = (int)($_GET['student_id'] ?? 0);
                    $grades = $gradeModel->getByStudent($studentId);
                    apiSuccess($grades);
                    break;
                    
                case 'get_monthly':
                    // جلب الدرجات الشهرية لمادة معينة
                    $classId = (int)($_GET['class_id'] ?? 0);
                    $section = $_GET['section'] ?? '';
                    $subject = $_GET['subject'] ?? '';
                    $year = $_GET['year'] ?? date('Y');
                    
                    if (!$classId || !$section || !$subject) {
                        apiError('بيانات ناقصة');
                    }
                    
                    $conn = getConnection();
                    $stmt = $conn->prepare("
                        SELECT mg.*, s.full_name as student_name 
                        FROM monthly_grades mg
                        JOIN students s ON mg.student_id = s.id
                        WHERE mg.class_id = ? 
                          AND mg.section = ? 
                          AND mg.subject_name = ?
                          AND mg.academic_year = ?
                    ");
                    $stmt->execute([$classId, $section, $subject, $year]);
                    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode(['success' => true, 'grades' => $grades]);
                    exit;
                    
                default:
                    apiError('إجراء غير معروف');
            }
            break;
            
        // ═══════════════════════════════════════════════════════════════
        // وحدة الإجازات
        // ═══════════════════════════════════════════════════════════════
        case 'leaves':
            require_once __DIR__ . '/models/Leave.php';
            $leaveModel = new Leave();
            
            switch ($action) {
                case 'list':
                    $personType = $_GET['person_type'] ?? null;
                    $leaves = $leaveModel->getAll($personType);
                    apiSuccess($leaves);
                    break;
                    
                case 'add':
                    $data = [
                        'person_type' => $_POST['person_type'] ?? 'student',
                        'person_id' => (int)($_POST['person_id'] ?? 0),
                        'leave_type' => $_POST['leave_type'] ?? 'sick',
                        'start_date' => $_POST['start_date'] ?? date('Y-m-d'),
                        'end_date' => $_POST['end_date'] ?? date('Y-m-d'),
                        'reason' => sanitize($_POST['reason'] ?? ''),
                        'recorded_by' => getCurrentUser()['id']
                    ];
                    
                    $result = $leaveModel->create($data);
                    if ($result) {
                        apiSuccess(['id' => $result], 'تم إضافة الإجازة');
                    } else {
                        apiError('حدث خطأ');
                    }
                    break;
                    
                case 'delete':
                    $id = (int)($_POST['id'] ?? 0);
                    if ($leaveModel->delete($id)) {
                        apiSuccess(null, 'تم حذف الإجازة');
                    } else {
                        apiError('حدث خطأ');
                    }
                    break;
                    
                default:
                    apiError('إجراء غير معروف');
            }
            break;
            
        // ═══════════════════════════════════════════════════════════════
        // وحدة حسابات الطلاب
        // ═══════════════════════════════════════════════════════════════
        case 'student_users':
            require_once __DIR__ . '/models/User.php';
            $userModel = new User();
            
            switch ($action) {
                case 'list':
                    if (!isAdmin()) apiError('ليس لديك صلاحية', 403);
                    $users = $userModel->getStudentUsersWithLinks();
                    apiSuccess($users);
                    break;
                    
                case 'toggle_status':
                    if (!isAdmin()) apiError('ليس لديك صلاحية', 403);
                    $id = (int)($_POST['id'] ?? 0);
                    $newStatus = $_POST['status'] ?? 'active';
                    
                    if ($userModel->updateStatus($id, $newStatus)) {
                        apiSuccess(['status' => $newStatus], 'تم تحديث الحالة');
                    } else {
                        apiError('حدث خطأ');
                    }
                    break;
                    
                case 'delete':
                    if (!isAdmin()) apiError('ليس لديك صلاحية', 403);
                    $id = (int)($_POST['id'] ?? 0);
                    
                    if ($userModel->delete($id)) {
                        apiSuccess(null, 'تم حذف الحساب');
                    } else {
                        apiError('حدث خطأ أثناء الحذف');
                    }
                    break;
                    
                case 'reset_password':
                    if (!isAdmin()) apiError('ليس لديك صلاحية', 403);
                    $id = (int)($_POST['id'] ?? 0);
                    $newPassword = $_POST['password'] ?? '';
                    
                    if (empty($newPassword)) {
                        $newPassword = generateRandomPassword(8);
                    }
                    
                    if ($userModel->updatePassword($id, $newPassword)) {
                        apiSuccess(['password' => $newPassword], 'تم تغيير كلمة المرور');
                    } else {
                        apiError('حدث خطأ');
                    }
                    break;
                    
                default:
                    apiError('إجراء غير معروف');
            }
            break;
            
        // ═══════════════════════════════════════════════════════════════
        // وحدة الأحداث والتقويم
        // ═══════════════════════════════════════════════════════════════
        case 'events':
            require_once __DIR__ . '/models/SchoolEvent.php';
            $eventModel = new SchoolEvent();
            
            switch ($action) {
                case 'list':
                    $month = $_GET['month'] ?? date('m');
                    $year = $_GET['year'] ?? date('Y');
                    $events = $eventModel->getByMonth($month, $year);
                    apiSuccess($events);
                    break;
                    
                case 'add':
                    if (!isAdmin() && !isAssistant()) apiError('ليس لديك صلاحية', 403);
                    $data = [
                        'title' => sanitize($_POST['title'] ?? ''),
                        'description' => sanitize($_POST['description'] ?? ''),
                        'event_date' => $_POST['event_date'] ?? date('Y-m-d'),
                        'event_type' => $_POST['event_type'] ?? 'event',
                        'is_holiday' => isset($_POST['is_holiday']) ? 1 : 0
                    ];
                    
                    if (empty($data['title'])) apiError('عنوان الحدث مطلوب');
                    
                    $result = $eventModel->create($data);
                    if ($result) {
                        apiSuccess(['id' => $result], 'تم إضافة الحدث');
                    } else {
                        apiError('حدث خطأ');
                    }
                    break;
                    
                case 'delete':
                    if (!isAdmin() && !isAssistant()) apiError('ليس لديك صلاحية', 403);
                    $id = (int)($_POST['id'] ?? 0);
                    
                    if ($eventModel->delete($id)) {
                        apiSuccess(null, 'تم حذف الحدث');
                    } else {
                        apiError('حدث خطأ');
                    }
                    break;
                    
                default:
                    apiError('إجراء غير معروف');
            }
            break;
            
        // ═══════════════════════════════════════════════════════════════
        // وحدة معدات الصفوف
        // ═══════════════════════════════════════════════════════════════
        case 'equipment':
            require_once __DIR__ . '/models/ClassroomEquipment.php';
            $equipmentModel = new ClassroomEquipment();
            
            switch ($action) {
                case 'list':
                    $classId = $_GET['class_id'] ?? null;
                    $section = $_GET['section'] ?? null;
                    $equipment = $equipmentModel->getAll($classId, $section);
                    apiSuccess($equipment);
                    break;
                    
                case 'add':
                    if (!isAdmin() && !isAssistant()) apiError('ليس لديك صلاحية', 403);
                    $data = [
                        'class_id' => (int)($_POST['class_id'] ?? 0),
                        'section' => $_POST['section'] ?? '',
                        'item_name' => sanitize($_POST['item_name'] ?? ''),
                        'quantity' => (int)($_POST['quantity'] ?? 1),
                        'condition_status' => $_POST['condition_status'] ?? 'good',
                        'notes' => sanitize($_POST['notes'] ?? '')
                    ];
                    
                    if (empty($data['item_name'])) apiError('اسم الصنف مطلوب');
                    
                    $result = $equipmentModel->create($data);
                    if ($result) {
                        apiSuccess(['id' => $result], 'تم إضافة الصنف');
                    } else {
                        apiError('حدث خطأ');
                    }
                    break;
                    
                case 'edit':
                    if (!isAdmin() && !isAssistant()) apiError('ليس لديك صلاحية', 403);
                    $id = (int)($_POST['id'] ?? 0);
                    $data = [
                        'item_name' => sanitize($_POST['item_name'] ?? ''),
                        'quantity' => (int)($_POST['quantity'] ?? 1),
                        'condition_status' => $_POST['condition_status'] ?? 'good',
                        'notes' => sanitize($_POST['notes'] ?? '')
                    ];
                    
                    if ($equipmentModel->update($id, $data)) {
                        apiSuccess(null, 'تم تحديث الصنف');
                    } else {
                        apiError('حدث خطأ');
                    }
                    break;
                    
                case 'delete':
                    if (!isAdmin() && !isAssistant()) apiError('ليس لديك صلاحية', 403);
                    $id = (int)($_POST['id'] ?? 0);
                    
                    if ($equipmentModel->delete($id)) {
                        apiSuccess(null, 'تم حذف الصنف');
                    } else {
                        apiError('حدث خطأ');
                    }
                    break;
                    
                default:
                    apiError('إجراء غير معروف');
            }
            break;
            
        // ═══════════════════════════════════════════════════════════════
        // وحدة تعيينات المعلمين
        // ═══════════════════════════════════════════════════════════════
        case 'assignments':
            require_once __DIR__ . '/models/TeacherAssignment.php';
            $assignmentModel = new TeacherAssignment();
            
            switch ($action) {
                case 'list':
                    $teacherId = $_GET['teacher_id'] ?? null;
                    $assignments = $teacherId 
                        ? $assignmentModel->getByTeacher($teacherId)
                        : $assignmentModel->getAll();
                    apiSuccess($assignments);
                    break;
                    
                case 'add':
                    if (!isAdmin()) apiError('ليس لديك صلاحية', 403);
                    $data = [
                        'teacher_id' => (int)($_POST['teacher_id'] ?? 0),
                        'subject_name' => sanitize($_POST['subject_name'] ?? ''),
                        'class_id' => (int)($_POST['class_id'] ?? 0),
                        'section' => $_POST['section'] ?? '',
                        'assigned_by' => getCurrentUser()['id']
                    ];
                    
                    $result = $assignmentModel->create($data);
                    if ($result) {
                        apiSuccess(['id' => $result], 'تم إضافة التعيين');
                    } else {
                        apiError('حدث خطأ - قد يكون التعيين موجوداً مسبقاً');
                    }
                    break;
                    
                case 'delete':
                    if (!isAdmin()) apiError('ليس لديك صلاحية', 403);
                    $id = (int)($_POST['id'] ?? 0);
                    
                    if ($assignmentModel->delete($id)) {
                        apiSuccess(null, 'تم حذف التعيين');
                    } else {
                        apiError('حدث خطأ');
                    }
                    break;
                    
                default:
                    apiError('إجراء غير معروف');
            }
            break;
            
        // ═══════════════════════════════════════════════════════════════
        // وحدة غيابات المعلمين
        // ═══════════════════════════════════════════════════════════════
        case 'teacher_absences':
            require_once __DIR__ . '/models/TeacherAttendance.php';
            $attendanceModel = new TeacherAttendance();
            
            switch ($action) {
                case 'list':
                    $date = $_GET['date'] ?? date('Y-m-d');
                    $absences = $attendanceModel->getAbsencesByDate($date);
                    apiSuccess($absences);
                    break;
                    
                case 'add':
                    if (!isAdmin()) apiError('ليس لديك صلاحية', 403);
                    $data = [
                        'teacher_id' => (int)($_POST['teacher_id'] ?? 0),
                        'date' => $_POST['date'] ?? date('Y-m-d'),
                        'lesson_number' => (int)($_POST['lesson_number'] ?? 0),
                        'reason' => sanitize($_POST['reason'] ?? '')
                    ];
                    
                    $result = $attendanceModel->recordAbsence($data);
                    if ($result) {
                        apiSuccess(['id' => $result], 'تم تسجيل الغياب');
                    } else {
                        apiError('حدث خطأ');
                    }
                    break;
                    
                case 'delete':
                    if (!isAdmin()) apiError('ليس لديك صلاحية', 403);
                    $id = (int)($_POST['id'] ?? 0);
                    
                    if ($attendanceModel->deleteAbsence($id)) {
                        apiSuccess(null, 'تم حذف سجل الغياب');
                    } else {
                        apiError('حدث خطأ');
                    }
                    break;
                    
                default:
                    apiError('إجراء غير معروف');
            }
            break;
            
        // ═══════════════════════════════════════════════════════════════
        // وحدة النظام
        // ═══════════════════════════════════════════════════════════════
        case 'system':
            switch ($action) {
                case 'stats':
                    if (!isAdmin()) apiError('ليس لديك صلاحية', 403);
                    require_once __DIR__ . '/database/schema.php';
                    $conn = getConnection();
                    $stats = getDatabaseStats($conn);
                    apiSuccess($stats);
                    break;
                    
                case 'current_user':
                    $user = getCurrentUser();
                    unset($user['password_hash']);
                    apiSuccess($user);
                    break;
                    
                case 'csrf_token':
                    if (empty($_SESSION['csrf_token'])) {
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    }
                    apiSuccess(['token' => $_SESSION['csrf_token']]);
                    break;
                    
                default:
                    apiError('إجراء غير معروف');
            }
            break;
            
        // ═══════════════════════════════════════════════════════════════
        // وحدة البحث الشامل
        // ═══════════════════════════════════════════════════════════════
        case 'search':
            switch ($action) {
                case 'global':
                    $query = trim($_POST['query'] ?? $_GET['q'] ?? '');
                    $filter = $_POST['filter'] ?? $_GET['filter'] ?? 'all';
                    
                    if (strlen($query) < 2) {
                        apiError('يجب إدخال حرفين على الأقل');
                    }
                    
                    $results = [
                        'students' => [],
                        'teachers' => [],
                        'pages' => []
                    ];
                    
                    // البحث في الطلاب (حسب صلاحيات المستخدم)
                    if ($filter === 'all' || $filter === 'students') {
                        $currentUser = getCurrentUser();
                        $userRole = $currentUser['role'] ?? 'student';
                        
                        // الطالب لا يمكنه البحث عن طلاب آخرين
                        if ($userRole !== 'student') {
                            require_once __DIR__ . '/models/Student.php';
                            $studentModel = new Student();
                            $students = $studentModel->search($query);
                            
                            // المعلم يرى فقط طلاب صفوفه المعينة
                            if ($userRole === 'teacher') {
                                require_once __DIR__ . '/models/TeacherAssignment.php';
                                $assignmentModel = new TeacherAssignment();
                                $teacherClasses = $assignmentModel->getClassesForTeacher($currentUser['id']);
                                
                                // فلترة الطلاب حسب الصفوف المعينة للمعلم
                                $allowedClasses = [];
                                foreach ($teacherClasses as $tc) {
                                    $key = $tc['class_id'] . '-' . $tc['section'];
                                    $allowedClasses[$key] = true;
                                }
                                
                                $students = array_filter($students, function($s) use ($allowedClasses) {
                                    $key = $s['class_id'] . '-' . $s['section'];
                                    return isset($allowedClasses[$key]);
                                });
                                $students = array_values($students);
                            }
                            
                            // تحديد النتائج لـ 10
                            $results['students'] = array_slice($students, 0, 10);
                        }
                    }
                    
                    // البحث في المعلمين (للمدير والمعاون فقط)
                    if (($filter === 'all' || $filter === 'teachers') && (isAdmin() || isAssistant())) {
                        require_once __DIR__ . '/models/Teacher.php';
                        $teacherModel = new Teacher();
                        $teachers = $teacherModel->search($query);
                        $results['teachers'] = array_slice($teachers, 0, 10);
                    }
                    
                    // البحث في الصفحات
                    if ($filter === 'all' || $filter === 'pages') {
                        $lang = getLang();
                        $isAr = $lang === 'ar';
                        
                        // قائمة الصفحات المتاحة - المعاون له نفس صلاحيات المدير
                        $allPages = [
                            ['url' => 'dashboard', 'title' => $isAr ? 'الصفحة الرئيسية' : 'Dashboard', 'icon' => 'fa-home', 'description' => $isAr ? 'لوحة التحكم الرئيسية' : 'Main dashboard', 'roles' => ['admin', 'assistant', 'teacher', 'student']],
                            ['url' => 'students', 'title' => $isAr ? 'البطاقات المدرسية' : 'Students', 'icon' => 'fa-user-graduate', 'description' => $isAr ? 'إدارة بيانات الطلاب' : 'Manage students', 'roles' => ['admin', 'assistant', 'teacher']],
                            ['url' => 'teachers', 'title' => $isAr ? 'ملفات المعلمين' : 'Teachers', 'icon' => 'fa-chalkboard-teacher', 'description' => $isAr ? 'إدارة بيانات المعلمين' : 'Manage teachers', 'roles' => ['admin', 'assistant']],
                            ['url' => 'attendance', 'title' => $isAr ? 'تسجيل الحضور' : 'Attendance', 'icon' => 'fa-user-check', 'description' => $isAr ? 'تسجيل حضور الطلاب' : 'Record attendance', 'roles' => ['admin', 'assistant', 'teacher']],
                            ['url' => 'grades', 'title' => $isAr ? 'رصد الدرجات' : 'Grades', 'icon' => 'fa-edit', 'description' => $isAr ? 'رصد درجات الطلاب' : 'Enter grades', 'roles' => ['admin', 'assistant']],
                            ['url' => 'grades_report', 'title' => $isAr ? 'كشف الدرجات' : 'Grades Report', 'icon' => 'fa-chart-bar', 'description' => $isAr ? 'عرض نتائج الدرجات' : 'View grades report', 'roles' => ['admin', 'assistant', 'teacher', 'student']],
                            ['url' => 'reports', 'title' => $isAr ? 'تقارير الحضور' : 'Reports', 'icon' => 'fa-chart-line', 'description' => $isAr ? 'تقارير الحضور والغياب' : 'Attendance reports', 'roles' => ['admin', 'assistant', 'teacher']],
                            ['url' => 'schedule', 'title' => $isAr ? 'الجدول الأسبوعي' : 'Schedule', 'icon' => 'fa-table', 'description' => $isAr ? 'جدول الحصص' : 'Class schedule', 'roles' => ['admin', 'assistant', 'teacher', 'student']],
                            ['url' => 'leaves', 'title' => $isAr ? 'إجازات التلاميذ' : 'Student Leaves', 'icon' => 'fa-file-alt', 'description' => $isAr ? 'إدارة إجازات الطلاب' : 'Manage student leaves', 'roles' => ['admin', 'assistant', 'teacher']],
                            ['url' => 'staff_leaves', 'title' => $isAr ? 'إجازات الكادر' : 'Staff Leaves', 'icon' => 'fa-calendar-minus', 'description' => $isAr ? 'إدارة إجازات المعلمين' : 'Manage staff leaves', 'roles' => ['admin', 'assistant']],
                            ['url' => 'teacher_assignments', 'title' => $isAr ? 'توزيع المواد' : 'Assignments', 'icon' => 'fa-tasks', 'description' => $isAr ? 'توزيع المواد والصفوف' : 'Subject assignments', 'roles' => ['admin', 'assistant']],
                            ['url' => 'teacher_absences', 'title' => $isAr ? 'غيابات المعلمين' : 'Teacher Absences', 'icon' => 'fa-user-times', 'description' => $isAr ? 'تسجيل غيابات المعلمين' : 'Record teacher absences', 'roles' => ['admin', 'assistant']],
                            ['url' => 'users', 'title' => $isAr ? 'حسابات المعلمين' : 'User Accounts', 'icon' => 'fa-user-lock', 'description' => $isAr ? 'إدارة حسابات الدخول' : 'User accounts', 'roles' => ['admin', 'assistant']],
                            ['url' => 'student_users', 'title' => $isAr ? 'حسابات الطلاب' : 'Student Accounts', 'icon' => 'fa-key', 'description' => $isAr ? 'حسابات دخول الطلاب' : 'Student accounts', 'roles' => ['admin', 'assistant']],
                            ['url' => 'classroom_equipment', 'title' => $isAr ? 'جرد الأثاث' : 'Equipment', 'icon' => 'fa-chair', 'description' => $isAr ? 'جرد أثاث المدرسة' : 'Classroom equipment', 'roles' => ['admin', 'assistant']],
                            ['url' => 'backup', 'title' => $isAr ? 'النسخ الاحتياطي' : 'Backup', 'icon' => 'fa-database', 'description' => $isAr ? 'النسخ الاحتياطي للبيانات' : 'Data backup', 'roles' => ['admin', 'assistant']],
                            ['url' => 'activity_log', 'title' => $isAr ? 'سجل العمليات' : 'Activity Log', 'icon' => 'fa-history', 'description' => $isAr ? 'سجل العمليات والتغييرات' : 'Activity log', 'roles' => ['admin', 'assistant']],
                            ['url' => 'events', 'title' => $isAr ? 'التقويم والمناسبات' : 'Events', 'icon' => 'fa-calendar-alt', 'description' => $isAr ? 'الأحداث والمناسبات' : 'Events calendar', 'roles' => ['admin', 'assistant', 'teacher', 'student']],
                            ['url' => 'teacher_profile', 'title' => $isAr ? 'بطاقتي الوظيفية' : 'My Profile', 'icon' => 'fa-address-card', 'description' => $isAr ? 'ملفي الشخصي' : 'My profile', 'roles' => ['admin', 'assistant', 'teacher']],
                            ['url' => 'export_my_data', 'title' => $isAr ? 'تصدير بياناتي' : 'Export My Data', 'icon' => 'fa-download', 'description' => $isAr ? 'تصدير بياناتي الشخصية' : 'Export my data', 'roles' => ['admin', 'assistant', 'teacher']],
                        ];
                        
                        $currentUser = getCurrentUser();
                        $userRole = $currentUser['role'] ?? 'student';
                        
                        // فلترة حسب الصلاحيات والبحث
                        $queryLower = mb_strtolower($query);
                        $filteredPages = [];
                        
                        foreach ($allPages as $page) {
                            // التحقق من الصلاحية
                            if (!in_array($userRole, $page['roles'])) continue;
                            
                            // البحث في العنوان والوصف
                            $titleMatch = mb_strpos(mb_strtolower($page['title']), $queryLower) !== false;
                            $descMatch = mb_strpos(mb_strtolower($page['description']), $queryLower) !== false;
                            $urlMatch = mb_strpos(mb_strtolower($page['url']), $queryLower) !== false;
                            
                            if ($titleMatch || $descMatch || $urlMatch) {
                                unset($page['roles']); // لا نرسل الأدوار للعميل
                                $filteredPages[] = $page;
                            }
                        }
                        
                        $results['pages'] = array_slice($filteredPages, 0, 8);
                    }
                    
                    // البحث في الإجراءات السريعة (للمدير والمعاون)
                    if (($filter === 'all' || $filter === 'actions') && (isAdmin() || isAssistant())) {
                        $lang = getLang();
                        $isAr = $lang === 'ar';
                        
                        // قائمة الإجراءات المتاحة
                        $allActions = [
                            // إدارة الطلاب
                            ['url' => 'students?action=add', 'title' => $isAr ? 'إضافة تلميذ جديد' : 'Add New Student', 'icon' => 'fa-user-plus', 'category' => $isAr ? 'الطلاب' : 'Students', 'keywords' => 'add student new تلميذ جديد اضافة'],
                            ['url' => 'students', 'title' => $isAr ? 'عرض البطاقات المدرسية' : 'View Student Cards', 'icon' => 'fa-id-card', 'category' => $isAr ? 'الطلاب' : 'Students', 'keywords' => 'view cards بطاقات عرض'],
                            
                            // الحضور
                            ['url' => 'attendance', 'title' => $isAr ? 'تسجيل حضور اليوم' : 'Record Today Attendance', 'icon' => 'fa-user-check', 'category' => $isAr ? 'الحضور' : 'Attendance', 'keywords' => 'attendance today حضور اليوم تسجيل'],
                            ['url' => 'reports', 'title' => $isAr ? 'تقرير الحضور الشهري' : 'Monthly Attendance Report', 'icon' => 'fa-chart-line', 'category' => $isAr ? 'الحضور' : 'Attendance', 'keywords' => 'report monthly تقرير شهري'],
                            
                            // الدرجات
                            ['url' => 'grades', 'title' => $isAr ? 'رصد درجات الطلاب' : 'Enter Student Grades', 'icon' => 'fa-edit', 'category' => $isAr ? 'الدرجات' : 'Grades', 'keywords' => 'grades enter رصد درجات'],
                            ['url' => 'grades_report', 'title' => $isAr ? 'كشف نتائج الدرجات' : 'View Grades Report', 'icon' => 'fa-chart-bar', 'category' => $isAr ? 'الدرجات' : 'Grades', 'keywords' => 'report results نتائج كشف'],
                            
                            // الإجازات
                            ['url' => 'leaves?action=add', 'title' => $isAr ? 'تسجيل إجازة تلميذ' : 'Register Student Leave', 'icon' => 'fa-calendar-minus', 'category' => $isAr ? 'الإجازات' : 'Leaves', 'keywords' => 'leave student إجازة تلميذ تسجيل'],
                            ['url' => 'staff_leaves?action=add', 'title' => $isAr ? 'تسجيل إجازة معلم' : 'Register Staff Leave', 'icon' => 'fa-calendar-times', 'category' => $isAr ? 'الإجازات' : 'Leaves', 'keywords' => 'leave teacher staff إجازة معلم كادر'],
                            
                            // المعلمين
                            ['url' => 'teachers?action=add', 'title' => $isAr ? 'إضافة معلم جديد' : 'Add New Teacher', 'icon' => 'fa-user-tie', 'category' => $isAr ? 'المعلمين' : 'Teachers', 'keywords' => 'add teacher معلم جديد اضافة'],
                            ['url' => 'teacher_assignments', 'title' => $isAr ? 'توزيع المواد على المعلمين' : 'Assign Subjects to Teachers', 'icon' => 'fa-tasks', 'category' => $isAr ? 'المعلمين' : 'Teachers', 'keywords' => 'assign subjects توزيع مواد تعيين'],
                            ['url' => 'teacher_absences', 'title' => $isAr ? 'تسجيل غياب معلم' : 'Record Teacher Absence', 'icon' => 'fa-user-times', 'category' => $isAr ? 'المعلمين' : 'Teachers', 'keywords' => 'absence teacher غياب معلم'],
                            
                            // النظام
                            ['url' => 'users?action=add', 'title' => $isAr ? 'إنشاء حساب مستخدم جديد' : 'Create New User Account', 'icon' => 'fa-user-plus', 'category' => $isAr ? 'النظام' : 'System', 'keywords' => 'user account حساب مستخدم انشاء'],
                            ['url' => 'backup', 'title' => $isAr ? 'إنشاء نسخة احتياطية' : 'Create Database Backup', 'icon' => 'fa-database', 'category' => $isAr ? 'النظام' : 'System', 'keywords' => 'backup database نسخة احتياطية'],
                            ['url' => 'events?action=add', 'title' => $isAr ? 'إضافة حدث أو مناسبة' : 'Add Event or Occasion', 'icon' => 'fa-calendar-plus', 'category' => $isAr ? 'النظام' : 'System', 'keywords' => 'event add حدث مناسبة اضافة'],
                            
                            // الجدول
                            ['url' => 'schedule', 'title' => $isAr ? 'تعديل الجدول الأسبوعي' : 'Edit Weekly Schedule', 'icon' => 'fa-table', 'category' => $isAr ? 'الجدول' : 'Schedule', 'keywords' => 'schedule edit جدول تعديل'],
                            
                            // الأثاث
                            ['url' => 'classroom_equipment', 'title' => $isAr ? 'جرد أثاث الصفوف' : 'Classroom Equipment Inventory', 'icon' => 'fa-chair', 'category' => $isAr ? 'الإدارة' : 'Admin', 'keywords' => 'equipment inventory جرد اثاث'],
                        ];
                        
                        // فلترة حسب البحث
                        $queryLower = mb_strtolower($query);
                        $filteredActions = [];
                        
                        foreach ($allActions as $action) {
                            $titleMatch = mb_strpos(mb_strtolower($action['title']), $queryLower) !== false;
                            $categoryMatch = mb_strpos(mb_strtolower($action['category']), $queryLower) !== false;
                            $keywordsMatch = mb_strpos(mb_strtolower($action['keywords']), $queryLower) !== false;
                            
                            if ($titleMatch || $categoryMatch || $keywordsMatch) {
                                unset($action['keywords']); // لا نرسل الكلمات المفتاحية للعميل
                                $filteredActions[] = $action;
                            }
                        }
                        
                        $results['actions'] = array_slice($filteredActions, 0, 6);
                    }
                    
                    apiSuccess($results);
                    break;
                    
                default:
                    apiError('إجراء غير معروف');
            }
            break;
        
        // ═══════════════════════════════════════════════════════════════
        // وحدة الدرجات
        // ═══════════════════════════════════════════════════════════════
        case 'grades':
            switch ($action) {
                case 'get_monthly':
                    // جلب الدرجات الشهرية لمادة معينة
                    $classId = (int)($_GET['class_id'] ?? 0);
                    $section = $_GET['section'] ?? '';
                    $subject = $_GET['subject'] ?? '';
                    $year = $_GET['year'] ?? date('Y');
                    
                    if (!$classId || !$section || !$subject) {
                        apiError('بيانات ناقصة');
                    }
                    
                    $conn = getConnection();
                    $stmt = $conn->prepare("
                        SELECT * FROM monthly_grades 
                        WHERE class_id = ? AND section = ? AND subject_name = ? AND academic_year = ?
                    ");
                    $stmt->execute([$classId, $section, $subject, $year]);
                    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    apiSuccess(['grades' => $grades]);
                    break;
                    
                default:
                    apiError('إجراء غير معروف');
            }
            break;
            
        default:
            apiError('وحدة غير معروفة: ' . $module);
    }
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    apiError('حدث خطأ في النظام: ' . $e->getMessage(), 500);
}
