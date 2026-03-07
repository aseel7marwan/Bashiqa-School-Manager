/**
 * Attendance Module - نظام تسجيل الحضور بـ AJAX
 * يحفظ الحضور تلقائياً بدون إعادة تحميل الصفحة
 */

document.addEventListener('DOMContentLoaded', function () {
    const attendanceForm = document.getElementById('attendanceForm');
    const statusButtons = document.querySelectorAll('.status-btn');

    // معلومات الصف/الشعبة/التاريخ من النموذج
    const getFormData = () => ({
        class_id: document.querySelector('input[name="class_id"]')?.value,
        section: document.querySelector('input[name="section"]')?.value,
        date: document.querySelector('input[name="date"]')?.value,
        csrf_token: document.querySelector('input[name="csrf_token"]')?.value
    });

    // ═══════════════════════════════════════════════════════════════
    // 📌 حفظ سجل حضور واحد بـ AJAX
    // ═══════════════════════════════════════════════════════════════
    async function saveAttendance(studentId, lessonNumber, status, button) {
        const formData = getFormData();

        // تأثير التحميل
        const originalContent = button.innerHTML;
        button.innerHTML = '⏳';
        button.disabled = true;

        try {
            const response = await fetch('/api.php?module=attendance&action=save_single', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': formData.csrf_token
                },
                body: new URLSearchParams({
                    csrf_token: formData.csrf_token,
                    class_id: formData.class_id,
                    section: formData.section,
                    date: formData.date,
                    student_id: studentId,
                    lesson_number: lessonNumber,
                    status: status
                })
            });

            const data = await response.json();

            if (data.success) {
                // تم الحفظ بنجاح - تأثير نجاح سريع
                button.innerHTML = '✓';
                button.style.transform = 'scale(1.2)';

                setTimeout(() => {
                    button.innerHTML = originalContent;
                    button.style.transform = '';
                }, 300);

                // إظهار Toast صغير (اختياري)
                if (window.SchoolAjax && window.SchoolAjax.toast) {
                    // لا نُظهر Toast لكل ضغطة لتجنب الإزعاج
                }
            } else {
                // خطأ
                button.innerHTML = '✗';
                setTimeout(() => {
                    button.innerHTML = originalContent;
                }, 500);

                if (window.SchoolAjax && window.SchoolAjax.toast) {
                    window.SchoolAjax.toast(data.message || 'حدث خطأ', 'error');
                } else {
                    console.error('Error:', data.message);
                }
            }
        } catch (error) {
            button.innerHTML = originalContent;
            console.error('Network error:', error);

            if (window.SchoolAjax && window.SchoolAjax.toast) {
                window.SchoolAjax.toast('خطأ في الاتصال', 'error');
            }
        } finally {
            button.disabled = false;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // 📌 الأحداث على أزرار الحالة
    // ═══════════════════════════════════════════════════════════════
    statusButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            // التحقق من إمكانية التسجيل
            if (window.canRecordAttendance === false) {
                if (window.SchoolAjax && window.SchoolAjax.toast) {
                    window.SchoolAjax.toast('لا يمكنك تسجيل الحضور في أيام العطلة', 'warning');
                }
                return;
            }

            const studentId = this.dataset.student;
            const lessonNumber = this.dataset.lesson;
            const status = this.dataset.status;

            // إزالة active من الأزرار الأخرى في نفس المجموعة
            const group = this.closest('.status-group');
            group.querySelectorAll('.status-btn').forEach(function (b) {
                b.classList.remove('active');
            });

            // إضافة active للزر الحالي
            this.classList.add('active');

            // حفظ بـ AJAX فوراً
            saveAttendance(studentId, lessonNumber, status, this);

            // تحديث الإحصائيات المحلية
            updateStats();
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // 📊 تحديث الإحصائيات
    // ═══════════════════════════════════════════════════════════════
    function updateStats() {
        let stats = { present: 0, late: 0, excused: 0, absent: 0 };

        document.querySelectorAll('.status-btn.active').forEach(function (btn) {
            const status = btn.dataset.status;
            if (stats.hasOwnProperty(status)) {
                stats[status]++;
            }
        });

        Object.keys(stats).forEach(function (status) {
            const el = document.querySelector('.stat-item.' + status + ' .number');
            if (el) {
                // تأثير تحديث
                el.style.transform = 'scale(1.2)';
                el.textContent = stats[status];
                setTimeout(() => el.style.transform = '', 200);
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // 📌 تحديد الكل بحالة معينة
    // ═══════════════════════════════════════════════════════════════
    const markAllButtons = document.querySelectorAll('.mark-all-btn');
    markAllButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const status = this.dataset.status;
            const lessonNumber = this.dataset.lesson;

            document.querySelectorAll('.status-btn[data-lesson="' + lessonNumber + '"][data-status="' + status + '"]').forEach(function (statusBtn) {
                statusBtn.click();
            });
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // 📌 زر الحفظ الرئيسي (احتياطي)
    // ═══════════════════════════════════════════════════════════════
    if (attendanceForm) {
        attendanceForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const activeButtons = document.querySelectorAll('.status-btn.active');
            if (activeButtons.length === 0) {
                if (window.SchoolAjax && window.SchoolAjax.toast) {
                    window.SchoolAjax.toast('الرجاء تسجيل الحضور لطالب واحد على الأقل', 'warning');
                } else {
                    alert('الرجاء تسجيل الحضور لطالب واحد على الأقل');
                }
                return;
            }

            // جمع كل البيانات
            const formData = new FormData(attendanceForm);
            const submitBtn = attendanceForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;

            submitBtn.innerHTML = '⏳ جاري الحفظ...';
            submitBtn.disabled = true;

            try {
                const response = await fetch('/api.php?module=attendance&action=save', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': formData.get('csrf_token')
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    submitBtn.innerHTML = '✅ تم الحفظ';
                    if (window.SchoolAjax && window.SchoolAjax.toast) {
                        window.SchoolAjax.toast('تم حفظ الحضور بنجاح', 'success');
                    }

                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }, 2000);
                } else {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;

                    if (window.SchoolAjax && window.SchoolAjax.toast) {
                        window.SchoolAjax.toast(data.message || 'حدث خطأ', 'error');
                    }
                }
            } catch (error) {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                console.error('Error:', error);

                if (window.SchoolAjax && window.SchoolAjax.toast) {
                    window.SchoolAjax.toast('خطأ في الاتصال', 'error');
                }
            }
        });
    }

    // تحديث الإحصائيات عند تحميل الصفحة
    updateStats();

    // Module loaded
});
