<?php
/**
 * تعديل الجداول الدراسية - Schedule Editor
 * تعديل الجداول وتعيين المعلمين للمواد
 * 
 * @package SchoolManager
 * @access  مدير المدرسة فقط (لا يُسمح للمعاون)
 * @security صلاحية حصرية للمدير لتعيين المواد للمعلمين
 */

$pageTitle = 'تعديل الجداول';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/models/Schedule.php';
require_once __DIR__ . '/models/User.php';

requireLogin();

// ═══════════════════════════════════════════════════════════════
// 🔒 صلاحية: المدير والمعاون فقط
// ═══════════════════════════════════════════════════════════════
if (!canManageSystem()) {
    alert('⛔ تعديل الجداول الدراسية متاح للمدير والمعاون فقط', 'error');
    redirect('/schedule.php');
}

$scheduleModel = new Schedule();
$userModel = new User();

$classId = (int)($_GET['class_id'] ?? 1);
$section = $_GET['section'] ?? 'أ';

$teachers = $userModel->getTeachers();
$schedule = $scheduleModel->getByClassSection($classId, $section);

// جلب المواد الخاصة بالصف المحدد
require_once __DIR__ . '/models/Subject.php';
$classSubjects = Subject::getSubjectsByClass($classId);

$scheduleGrid = [];
foreach ($schedule as $item) {
    $scheduleGrid[$item['day_of_week']][$item['lesson_number']] = $item;
}

require_once __DIR__ . '/views/components/header.php';
?>

<div class="page-header d-flex justify-between align-center flex-wrap gap-2">
    <div>
        <h1>✏️ تعديل الجداول</h1>
        <p>الصف <?= CLASSES[$classId] ?? $classId ?> - شعبة <?= sanitize($section) ?></p>
    </div>
    <a href="schedule.php?class_id=<?= $classId ?>&section=<?= urlencode($section) ?>" class="btn btn-secondary">
        ← العودة للجدول
    </a>
</div>

<div class="card mb-3">
    <div class="card-header">
        <form method="GET" class="d-flex gap-2 flex-wrap" id="scheduleFilterForm">
            <div class="filter-group">
                <label>الصف:</label>
                <select name="class_id" id="scheduleClassId" class="form-control">
                    <?php foreach (CLASSES as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $classId == $id ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>الشعبة:</label>
                <select name="section" id="scheduleSection" class="form-control">
                    <?php foreach (SECTIONS as $sec): ?>
                    <option value="<?= $sec ?>" <?= $section == $sec ? 'selected' : '' ?>><?= $sec ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group" style="display: none; align-items: flex-end;">
                <button type="button" id="loadScheduleBtn" class="btn btn-primary btn-sm">
                    🔄 تحميل الجدول
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card fade-in">
    <div class="card-header">
        <h3>📅 تعديل الجدول الأسبوعي</h3>
    </div>
    <div class="card-body">
        <form action="controllers/schedule_handler.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_schedule">
            <input type="hidden" name="class_id" value="<?= $classId ?>">
            <input type="hidden" name="section" value="<?= $section ?>">
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 100px;">الحصة</th>
                            <?php foreach (DAYS as $dayKey => $dayName): ?>
                            <th><?= $dayName ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (LESSONS as $lessonNum => $lesson): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600;"><?= $lesson['name'] ?></div>
                                <small class="text-muted"><?= $lesson['start'] ?></small>
                            </td>
                            <?php foreach (DAYS as $dayKey => $dayName): ?>
                            <td style="padding: 0.5rem;">
                                <?php 
                                $currentSubject = $scheduleGrid[$dayKey][$lessonNum]['subject_name'] ?? '';
                                $currentTeacher = $scheduleGrid[$dayKey][$lessonNum]['teacher_id'] ?? '';
                                ?>
                                <select name="schedule[<?= $dayKey ?>][<?= $lessonNum ?>][subject]"
                                        class="form-control mb-1" 
                                        style="font-size: 0.85rem;">
                                    <option value="">-- المادة --</option>
                                    <?php foreach ($classSubjects as $subj): ?>
                                    <option value="<?= $subj ?>" <?= $currentSubject == $subj ? 'selected' : '' ?>><?= $subj ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="schedule[<?= $dayKey ?>][<?= $lessonNum ?>][teacher]" 
                                        class="form-control" style="font-size: 0.85rem;">
                                    <option value="">-- المعلم --</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?= $teacher['id'] ?>" <?= $currentTeacher == $teacher['id'] ? 'selected' : '' ?>>
                                        <?= sanitize($teacher['full_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">💾 حفظ الجدول</button>
                <a href="schedule.php?class_id=<?= $classId ?>&section=<?= urlencode($section) ?>" class="btn btn-secondary">إلغاء</a>
            </div>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h3>🚫 غياب المعلمين اليوم</h3>
    </div>
    <div class="card-body">
        <form action="controllers/schedule_handler.php" method="POST" class="d-flex gap-2 flex-wrap align-center">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_teacher_absence">
            
            <div class="filter-group">
                <label>المعلم:</label>
                <select name="teacher_id" class="form-control" required>
                    <option value="">-- اختر المعلم --</option>
                    <?php foreach ($teachers as $teacher): ?>
                    <option value="<?= $teacher['id'] ?>"><?= sanitize($teacher['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>التاريخ:</label>
                <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            
            <div class="filter-group">
                <label>الحصة (اختياري):</label>
                <select name="lesson_number" class="form-control">
                    <option value="">-- اليوم كامل --</option>
                    <?php foreach (LESSONS as $num => $lesson): ?>
                    <option value="<?= $num ?>"><?= $lesson['name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>السبب:</label>
                <input type="text" name="reason" class="form-control" placeholder="السبب (اختياري)">
            </div>
            
            <button type="submit" class="btn btn-danger">➕ تسجيل غياب</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>

<script>
// ═══════════════════════════════════════════════════════════════
// 📅 حفظ الجدول الأسبوعي بـ AJAX فوراً عند التغيير
// ═══════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', function() {
    const scheduleForm = document.querySelector('form[action*="schedule_handler"]');
    const allSelects = scheduleForm.querySelectorAll('select[name^="schedule"]');
    
    // معلومات الصف والشعبة
    const classId = document.querySelector('input[name="class_id"]').value;
    const section = document.querySelector('input[name="section"]').value;
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    
    // ═══════════════════════════════════════════════════════════════
    // 📌 حفظ خانة واحدة عند التغيير
    // ═══════════════════════════════════════════════════════════════
    async function saveScheduleCell(day, lesson, subject, teacherId, selectElement) {
        // تأثير التحميل
        selectElement.style.opacity = '0.6';
        selectElement.disabled = true;
        
        try {
            const response = await fetch('/api.php?module=schedule&action=update_cell', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken
                },
                body: new URLSearchParams({
                    csrf_token: csrfToken,
                    class_id: classId,
                    section: section,
                    day: day,
                    lesson: lesson,
                    subject: subject,
                    teacher_id: teacherId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // تأثير نجاح
                selectElement.style.borderColor = '#10b981';
                selectElement.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.2)';
                
                setTimeout(() => {
                    selectElement.style.borderColor = '';
                    selectElement.style.boxShadow = '';
                }, 1000);
                
                if (window.SchoolAjax && window.SchoolAjax.toast) {
                    window.SchoolAjax.toast('تم الحفظ ✓', 'success');
                }
            } else {
                // تأثير خطأ
                selectElement.style.borderColor = '#ef4444';
                
                if (window.SchoolAjax && window.SchoolAjax.toast) {
                    window.SchoolAjax.toast(data.message || 'حدث خطأ', 'error');
                }
            }
        } catch (error) {
            console.error('Error:', error);
            selectElement.style.borderColor = '#ef4444';
            
            if (window.SchoolAjax && window.SchoolAjax.toast) {
                window.SchoolAjax.toast('خطأ في الاتصال', 'error');
            }
        } finally {
            selectElement.style.opacity = '';
            selectElement.disabled = false;
        }
    }
    
    // ═══════════════════════════════════════════════════════════════
    // 📌 إضافة أحداث change لكل select
    // ═══════════════════════════════════════════════════════════════
    allSelects.forEach(function(select) {
        select.addEventListener('change', function() {
            // استخراج اليوم ورقم الحصة من اسم الحقل
            // الصيغة: schedule[day][lesson][subject/teacher]
            const match = this.name.match(/schedule\[(\w+)\]\[(\d+)\]\[(subject|teacher)\]/);
            if (!match) return;
            
            const day = match[1];
            const lesson = match[2];
            const type = match[3];
            
            // جلب قيمة المادة والمعلم من نفس الخلية
            const cell = this.closest('td');
            const subjectSelect = cell.querySelector('select[name*="[subject]"]');
            const teacherSelect = cell.querySelector('select[name*="[teacher]"]');
            
            const subject = subjectSelect ? subjectSelect.value : '';
            const teacherId = teacherSelect ? teacherSelect.value : '';
            
            // حفظ فوري
            saveScheduleCell(day, lesson, subject, teacherId, this);
        });
    });
    
    // ═══════════════════════════════════════════════════════════════
    // 📌 تحويل زر الحفظ الرئيسي لـ AJAX (احتياطي)
    // ═══════════════════════════════════════════════════════════════
    scheduleForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '⏳ جاري الحفظ...';
        submitBtn.disabled = true;
        
        try {
            const formData = new FormData(this);
            formData.set('ajax', '1');
            
            const response = await fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                submitBtn.innerHTML = '✅ تم الحفظ';
                if (window.SchoolAjax && window.SchoolAjax.toast) {
                    window.SchoolAjax.toast('تم حفظ الجدول بنجاح', 'success');
                }
            } else {
                submitBtn.innerHTML = originalText;
                if (window.SchoolAjax && window.SchoolAjax.toast) {
                    window.SchoolAjax.toast(data.message || 'حدث خطأ', 'error');
                }
            }
        } catch (error) {
            console.error('Error:', error);
            // إذا فشل AJAX، نُرسل النموذج بالطريقة العادية
            this.submit();
        } finally {
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 2000);
        }
    });
    
    // ═══════════════════════════════════════════════════════════════
    // 🔄 تحميل الجدول عبر AJAX بدون إعادة تحميل الصفحة
    // ═══════════════════════════════════════════════════════════════
    function loadScheduleData() {
        const classId = document.getElementById('scheduleClassId').value;
        const section = document.getElementById('scheduleSection').value;
        
        // تحديث URL بدون إعادة تحميل
        const url = new URL(window.location);
        url.searchParams.set('class_id', classId);
        url.searchParams.set('section', section);
        window.history.pushState({}, '', url);
        
        // إظهار مؤشر التحميل
        const btn = document.getElementById('loadScheduleBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '⏳ جاري التحميل...';
        btn.disabled = true;
        
        // تحميل الصفحة عبر AJAX
        fetch(url.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.text())
        .then(html => {
            // استخراج المحتوى
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // تحديث الجدول
            const newCard = doc.querySelector('.card.fade-in');
            const currentCard = document.querySelector('.card.fade-in');
            if (newCard && currentCard) {
                currentCard.innerHTML = newCard.innerHTML;
            }
            
            // إظهار رسالة نجاح
            if (window.UI && window.UI.success) {
                window.UI.success('تم تحميل الجدول ✓');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // في حالة الخطأ، إعادة تحميل الصفحة
            window.location.href = url.href;
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
    
    // ربط الزر بالدالة
    const loadBtn = document.getElementById('loadScheduleBtn');
    if (loadBtn) {
        loadBtn.addEventListener('click', loadScheduleData);
    }
    
    // تحميل تلقائي عند تغيير الفلاتر
    ['scheduleClassId', 'scheduleSection'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', loadScheduleData);
        }
    });
    
    // Module loaded
});
</script>
