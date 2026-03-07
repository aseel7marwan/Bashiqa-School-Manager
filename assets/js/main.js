document.addEventListener('DOMContentLoaded', function () {
    const themeToggle = document.getElementById('themeToggle');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    // ═══════════════════════════════════════════════════════════════
    // حل مشكلة الـ scroll التلقائي
    // ═══════════════════════════════════════════════════════════════
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }

    // ═══════════════════════════════════════════════════════════════
    // تم إزالة AJAX Navigation - جميع الصفحات تفتح بالطريقة العادية
    // لضمان تجربة موحدة وسلسة في التنقل
    // ═══════════════════════════════════════════════════════════════

    // ═══════════════════════════════════════════════════════════════
    // اكتشاف نوع الجهاز
    // ═══════════════════════════════════════════════════════════════
    function isMobileDevice() {
        return window.innerWidth <= 768;
    }

    function isDesktop() {
        return window.innerWidth > 768;
    }

    // ═══════════════════════════════════════════════════════════════
    // Sidebar Toggle - إظهار/إخفاء القائمة الجانبية
    // ═══════════════════════════════════════════════════════════════
    const SIDEBAR_STATE_KEY = 'sidebarCollapsed';

    // متغير لتتبع ما إذا قام المستخدم بالضغط يدوياً
    let userManuallyToggled = false;

    // فتح القائمة
    function openSidebar() {
        document.body.classList.remove('sidebar-collapsed');
        if (sidebar) sidebar.classList.remove('collapsed');
    }

    // إغلاق القائمة
    function closeSidebar() {
        document.body.classList.add('sidebar-collapsed');
        if (sidebar) sidebar.classList.add('collapsed');
    }

    // تبديل حالة القائمة الجانبية (عند ضغط المستخدم)
    function toggleSidebar() {
        userManuallyToggled = true; // المستخدم ضغط يدوياً

        const isCurrentlyCollapsed = document.body.classList.contains('sidebar-collapsed');

        if (isCurrentlyCollapsed) {
            openSidebar();
        } else {
            closeSidebar();
        }

        // حفظ الحالة في localStorage (فقط على الشاشات الكبيرة)
        if (isDesktop()) {
            localStorage.setItem(SIDEBAR_STATE_KEY, !isCurrentlyCollapsed ? 'true' : 'false');
        }
    }

    // استعادة حالة القائمة الجانبية (بدون animation)
    function restoreSidebarState() {
        // على الموبايل: القائمة مغلقة افتراضياً - لا نفعل شيء (الحالة صحيحة في HTML)
        if (isMobileDevice()) {
            // القائمة مغلقة بالفعل من HTML
            enableTransition();
            return;
        }

        // على الديسكتوب: استعادة من localStorage أو فتح افتراضياً
        const savedState = localStorage.getItem(SIDEBAR_STATE_KEY);

        // تطبيق الحالة مباشرة (بدون transition لأن لدينا no-transition class)
        if (savedState === 'true') {
            // المستخدم اختار إغلاقها - تبقى مغلقة (الحالة الصحيحة من HTML)
            // لا نفعل شيء
        } else {
            // فتح القائمة افتراضياً على الديسكتوب
            openSidebar();
        }

        // إعادة تفعيل الـ transition بعد تطبيق الحالة الصحيحة
        enableTransition();
    }

    // إعادة تفعيل الـ transition
    function enableTransition() {
        setTimeout(function () {
            if (sidebar) {
                sidebar.classList.remove('no-transition');
            }
        }, 50); // تأخير صغير لضمان تطبيق الحالة أولاً
    }

    // تفعيل زر toggle
    if (sidebarToggle) {
        let isProcessing = false;

        function handleToggle(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }

            if (isProcessing) return;
            isProcessing = true;

            toggleSidebar();

            setTimeout(function () {
                isProcessing = false;
            }, 300);
        }

        sidebarToggle.addEventListener('click', handleToggle, false);
    }

    // إغلاق القائمة عند الضغط على overlay (لجميع الشاشات)
    if (sidebarOverlay) {
        function handleOverlayClick(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            closeSidebar();
            return false;
        }

        sidebarOverlay.ontouchstart = handleOverlayClick;
        sidebarOverlay.onclick = handleOverlayClick;
    }

    // إغلاق القائمة عند الضغط على أي رابط فيها (على الموبايل فقط)
    const navLinks = document.querySelectorAll('#sidebarNav a.nav-item');
    navLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            // إغلاق القائمة فقط على الموبايل للسماح بالتنقل السلس
            if (isMobileDevice()) {
                setTimeout(function () {
                    closeSidebar();
                }, 100);
            }
        });
    });

    // استعادة الحالة عند تحميل الصفحة
    restoreSidebarState();

    // ═══════════════════════════════════════════════════════════════
    // التعامل مع تغيير حجم الشاشة - ذكي ومرن
    // ═══════════════════════════════════════════════════════════════
    let resizeTimeout;
    let lastWindowWidth = window.innerWidth;
    let wasDesktop = isDesktop();

    window.addEventListener('resize', function () {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function () {
            const currentWidth = window.innerWidth;
            const widthChange = Math.abs(currentWidth - lastWindowWidth);
            const nowDesktop = isDesktop();
            const nowMobile = isMobileDevice();

            // تجاهل التغييرات الصغيرة (أقل من 50 بكسل) - من لوحة المفاتيح أو select
            if (widthChange < 50) {
                return;
            }

            // التحول من ديسكتوب إلى موبايل
            if (wasDesktop && nowMobile) {
                closeSidebar();
                userManuallyToggled = false;
            }
            // التحول من موبايل إلى ديسكتوب
            else if (!wasDesktop && nowDesktop) {
                // استعادة الحالة المحفوظة على الديسكتوب
                const savedState = localStorage.getItem(SIDEBAR_STATE_KEY);
                if (savedState === 'true') {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            }
            // على الديسكتوب: لا نغير شيء - القائمة تبقى كما هي
            // (لا نفعل شيء إذا كان المستخدم على ديسكتوب وبقي على ديسكتوب)

            lastWindowWidth = currentWidth;
            wasDesktop = nowDesktop;
        }, 250);
    });



    // ═══════════════════════════════════════════════════════════════
    // Theme toggle
    // ═══════════════════════════════════════════════════════════════
    if (themeToggle) {
        themeToggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

            html.setAttribute('data-theme', newTheme);
            themeToggle.textContent = newTheme === 'dark' ? '☀️' : '🌙';

            var themePath = (typeof baseUrl !== 'undefined' ? baseUrl : '') + 'controllers/theme_handler.php';
            fetch(themePath, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'theme=' + newTheme
            });
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // إخفاء التنبيهات تلقائياً
    // ═══════════════════════════════════════════════════════════════
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(function () {
                alert.remove();
            }, 300);
        }, 5000);
    });
});

// ═══════════════════════════════════════════════════════════════
// Global Functions
// ═══════════════════════════════════════════════════════════════
function confirmDelete(message) {
    return confirm(message || 'هل أنت متأكد من الحذف؟');
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-US').format(num);
}

function toArabicNumerals(str) {
    const arabicNums = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    return String(str).replace(/[0-9]/g, d => arabicNums[d]);
}

// ═══════════════════════════════════════════════════════════════
// 📌 نظام التنبيهات الشامل - Notification System
// ═══════════════════════════════════════════════════════════════

/**
 * عرض تنبيه للمستخدم
 * @param {string} message - الرسالة
 * @param {string} type - نوع التنبيه: success, error, warning, info, loading
 * @param {number} duration - مدة العرض بالمللي ثانية (0 = دائم)
 * @returns {HTMLElement} عنصر التنبيه
 */
function showNotification(message, type = 'info', duration = 4000) {
    // إزالة أي تنبيه سابق من نفس النوع
    const existing = document.querySelector('.system-notification.' + type);
    if (existing) existing.remove();

    // إنشاء حاوية التنبيهات إذا لم تكن موجودة
    let container = document.getElementById('notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notification-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 90%;
            width: 450px;
        `;
        document.body.appendChild(container);
    }

    // أيقونات حسب النوع
    const icons = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️',
        loading: '⏳'
    };

    // ألوان حسب النوع
    const colors = {
        success: { bg: '#d1fae5', border: '#10b981', text: '#065f46' },
        error: { bg: '#fee2e2', border: '#ef4444', text: '#991b1b' },
        warning: { bg: '#fef3c7', border: '#f59e0b', text: '#92400e' },
        info: { bg: '#dbeafe', border: '#3b82f6', text: '#1e40af' },
        loading: { bg: '#e0e7ff', border: '#6366f1', text: '#3730a3' }
    };

    const style = colors[type] || colors.info;

    // إنشاء التنبيه
    const notification = document.createElement('div');
    notification.className = 'system-notification ' + type;
    notification.style.cssText = `
        background: ${style.bg};
        border: 2px solid ${style.border};
        color: ${style.text};
        padding: 15px 20px;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideDown 0.3s ease;
        font-weight: 600;
        font-size: 0.95rem;
        direction: rtl;
    `;

    notification.innerHTML = `
        <span style="font-size: 1.5rem;">${icons[type] || icons.info}</span>
        <span style="flex: 1;">${message}</span>
        ${type !== 'loading' ? '<button onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; opacity: 0.7; padding: 0 5px;">×</button>' : ''}
    `;

    container.appendChild(notification);

    // إضافة الـ CSS للأنيميشن إذا لم يكن موجوداً
    if (!document.getElementById('notification-styles')) {
        const styleSheet = document.createElement('style');
        styleSheet.id = 'notification-styles';
        styleSheet.textContent = `
            @keyframes slideDown {
                from { opacity: 0; transform: translateY(-20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            @keyframes slideUp {
                from { opacity: 1; transform: translateY(0); }
                to { opacity: 0; transform: translateY(-20px); }
            }
        `;
        document.head.appendChild(styleSheet);
    }

    // إزالة التنبيه بعد المدة المحددة
    if (duration > 0) {
        setTimeout(() => {
            notification.style.animation = 'slideUp 0.3s ease forwards';
            setTimeout(() => notification.remove(), 300);
        }, duration);
    }

    return notification;
}

/**
 * تنبيهات مختصرة
 */
function notifySuccess(message) { return showNotification(message, 'success'); }
function notifyError(message) { return showNotification(message, 'error', 6000); }
function notifyWarning(message) { return showNotification(message, 'warning', 5000); }
function notifyInfo(message) { return showNotification(message, 'info'); }
function notifyLoading(message) { return showNotification(message || 'جارِ التحميل...', 'loading', 0); }



function toEnglishNumerals(str) {
    const arabicNums = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    return String(str).replace(/[٠-٩]/g, d => arabicNums.indexOf(d));
}

function formatArabicDate(dateStr) {
    if (!dateStr) return '';
    const parts = dateStr.split('-');
    if (parts.length !== 3) return dateStr;
    const day = parseInt(parts[2]);
    const month = parseInt(parts[1]);
    const year = parts[0];
    return day + ' / ' + month + ' / ' + year;
}

// إضافة عرض التاريخ تحت كل حقل تاريخ
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[type="date"]').forEach(function (input) {
        const preview = document.createElement('small');
        preview.className = 'date-arabic-preview';
        preview.style.cssText = 'display: block; color: var(--primary); font-weight: 500; margin-top: 4px; direction: rtl;';

        function updatePreview() {
            if (input.value) {
                preview.textContent = '📅 ' + formatArabicDate(input.value);
            } else {
                preview.textContent = '';
            }
        }

        input.parentNode.appendChild(preview);
        updatePreview();
        input.addEventListener('change', updatePreview);
    });
});

// ═══════════════════════════════════════════════════════════════
// 🌐 رسائل التحقق العربية - Arabic Validation Messages
// ═══════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', function () {

    // رسائل التحقق العربية
    const arabicMessages = {
        valueMissing: 'هذا الحقل مطلوب. يرجى ملؤه.',
        typeMismatch: {
            email: 'يرجى إدخال بريد إلكتروني صحيح (مثال: name@example.com)',
            url: 'يرجى إدخال رابط صحيح (يبدأ بـ http:// أو https://)',
            default: 'يرجى إدخال قيمة صحيحة.'
        },
        patternMismatch: 'الصيغة غير صحيحة. يرجى اتباع النمط المطلوب.',
        tooShort: function (el) {
            return 'يجب أن يكون النص ' + el.minLength + ' أحرف على الأقل. (أنت تستخدم ' + el.value.length + ' أحرف)';
        },
        tooLong: function (el) {
            return 'يجب أن لا يتجاوز النص ' + el.maxLength + ' حرف. (أنت تستخدم ' + el.value.length + ' حرف)';
        },
        rangeUnderflow: function (el) {
            return 'القيمة يجب أن تكون ' + el.min + ' أو أكثر.';
        },
        rangeOverflow: function (el) {
            return 'القيمة يجب أن تكون ' + el.max + ' أو أقل.';
        },
        stepMismatch: 'يرجى إدخال قيمة صحيحة.',
        badInput: 'يرجى إدخال قيمة صحيحة.',
        customError: 'يرجى تصحيح هذا الحقل.'
    };

    // تطبيق الرسائل العربية على جميع حقول الإدخال
    function setArabicValidation(input) {
        input.addEventListener('invalid', function (e) {
            e.preventDefault();

            let message = '';

            if (input.validity.valueMissing) {
                message = arabicMessages.valueMissing;
            } else if (input.validity.typeMismatch) {
                if (input.type === 'email') {
                    message = arabicMessages.typeMismatch.email;
                } else if (input.type === 'url') {
                    message = arabicMessages.typeMismatch.url;
                } else {
                    message = arabicMessages.typeMismatch.default;
                }
            } else if (input.validity.patternMismatch) {
                message = input.title || arabicMessages.patternMismatch;
            } else if (input.validity.tooShort) {
                message = arabicMessages.tooShort(input);
            } else if (input.validity.tooLong) {
                message = arabicMessages.tooLong(input);
            } else if (input.validity.rangeUnderflow) {
                message = arabicMessages.rangeUnderflow(input);
            } else if (input.validity.rangeOverflow) {
                message = arabicMessages.rangeOverflow(input);
            } else if (input.validity.stepMismatch) {
                message = arabicMessages.stepMismatch;
            } else if (input.validity.badInput) {
                message = arabicMessages.badInput;
            } else {
                message = arabicMessages.customError;
            }

            input.setCustomValidity(message);

            // عرض الرسالة كتنبيه
            showValidationMessage(input, message);
        });

        // مسح الرسالة المخصصة عند الكتابة
        input.addEventListener('input', function () {
            input.setCustomValidity('');
            hideValidationMessage(input);
        });
    }

    // عرض رسالة التحقق بجانب الحقل
    function showValidationMessage(input, message) {
        // إزالة أي رسالة سابقة
        hideValidationMessage(input);

        // إنشاء رسالة التحقق
        const msgEl = document.createElement('div');
        msgEl.className = 'validation-message-ar';
        msgEl.innerHTML = '⚠️ ' + message;
        msgEl.style.cssText = `
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-top: 5px;
            direction: rtl;
            animation: fadeIn 0.2s ease;
        `;

        // إضافة الرسالة بعد الحقل
        input.parentNode.appendChild(msgEl);

        // إضافة حدود حمراء للحقل
        input.style.borderColor = '#ef4444';
        input.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.15)';
    }

    // إخفاء رسالة التحقق
    function hideValidationMessage(input) {
        const existing = input.parentNode.querySelector('.validation-message-ar');
        if (existing) existing.remove();

        // إعادة الحدود الطبيعية
        input.style.borderColor = '';
        input.style.boxShadow = '';
    }

    // تطبيق على جميع الحقول
    document.querySelectorAll('input, select, textarea').forEach(setArabicValidation);

    // إضافة CSS للأنيميشن
    if (!document.getElementById('validation-styles')) {
        const style = document.createElement('style');
        style.id = 'validation-styles';
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-5px); }
                to { opacity: 1; transform: translateY(0); }
            }
        `;
        document.head.appendChild(style);
    }
});

// ═══════════════════════════════════════════════════════════════
// 🖼️ Lazy Loading المحسن - Advanced Image Lazy Loading
// ═══════════════════════════════════════════════════════════════

const LazyLoader = {
    observer: null,

    /**
     * تهيئة نظام Lazy Loading
     */
    init: function () {
        // استخدام IntersectionObserver للأداء الأفضل
        if ('IntersectionObserver' in window) {
            this.initObserver();
        } else {
            // Fallback للمتصفحات القديمة
            this.loadAllImages();
        }

        // تطبيق على الصور الموجودة
        this.processImages();

        // تطبيق على الخلفيات
        this.processBackgrounds();

        // تطبيق على iframes
        this.processIframes();
    },

    /**
     * إنشاء IntersectionObserver
     */
    initObserver: function () {
        const self = this;

        this.observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    self.loadElement(entry.target);
                    self.observer.unobserve(entry.target);
                }
            });
        }, {
            rootMargin: '100px 0px', // تحميل مبكر قبل الظهور بـ 100px
            threshold: 0.01
        });
    },

    /**
     * معالجة الصور
     */
    processImages: function () {
        const self = this;

        document.querySelectorAll('img:not([data-lazy-processed])').forEach(function (img) {
            // تمييز العنصر كمعالج
            img.setAttribute('data-lazy-processed', 'true');

            // تجاهل الصور الصغيرة جداً (أيقونات)
            if ((img.width > 0 && img.width < 30) || (img.height > 0 && img.height < 30)) {
                img.classList.add('loaded');
                return;
            }

            // إضافة loading="lazy" إذا لم يكن موجوداً
            if (!img.hasAttribute('loading')) {
                img.setAttribute('loading', 'lazy');
            }

            // إذا كان لديها data-src (تحميل مخصص)
            if (img.dataset.src) {
                if (self.observer) {
                    self.observer.observe(img);
                } else {
                    self.loadImage(img);
                }
            } else {
                // الصور العادية - فقط إضافة التأثير
                if (img.complete && img.naturalHeight !== 0) {
                    img.classList.add('loaded');
                } else {
                    img.addEventListener('load', function () {
                        this.classList.add('loaded');
                    });
                    img.addEventListener('error', function () {
                        this.classList.add('loaded');
                        this.classList.add('error');
                    });
                }
            }
        });
    },

    /**
     * معالجة الخلفيات
     */
    processBackgrounds: function () {
        const self = this;

        document.querySelectorAll('[data-lazy-bg]:not([data-lazy-processed])').forEach(function (el) {
            el.setAttribute('data-lazy-processed', 'true');

            if (self.observer) {
                self.observer.observe(el);
            } else {
                self.loadBackground(el);
            }
        });
    },

    /**
     * معالجة iframes
     */
    processIframes: function () {
        document.querySelectorAll('iframe:not([loading])').forEach(function (iframe) {
            iframe.setAttribute('loading', 'lazy');
        });
    },

    /**
     * تحميل عنصر
     */
    loadElement: function (el) {
        if (el.tagName === 'IMG') {
            this.loadImage(el);
        } else if (el.dataset.lazyBg) {
            this.loadBackground(el);
        }
    },

    /**
     * تحميل صورة
     */
    loadImage: function (img) {
        const src = img.dataset.src;
        const srcset = img.dataset.srcset;

        if (!src) return;

        // تحميل الصورة في الخلفية
        const tempImg = new Image();

        tempImg.onload = function () {
            img.src = src;
            if (srcset) img.srcset = srcset;
            img.classList.add('loaded');
            img.removeAttribute('data-src');
            img.removeAttribute('data-srcset');
        };

        tempImg.onerror = function () {
            img.classList.add('loaded', 'error');
        };

        tempImg.src = src;
    },

    /**
     * تحميل خلفية
     */
    loadBackground: function (el) {
        const bg = el.dataset.lazyBg;

        if (!bg) return;

        // تحميل الصورة في الخلفية
        const tempImg = new Image();

        tempImg.onload = function () {
            el.style.backgroundImage = 'url(' + bg + ')';
            el.classList.add('bg-loaded');
            el.removeAttribute('data-lazy-bg');
        };

        tempImg.src = bg;
    },

    /**
     * تحميل جميع الصور (Fallback)
     */
    loadAllImages: function () {
        const self = this;

        document.querySelectorAll('img[data-src]').forEach(function (img) {
            self.loadImage(img);
        });

        document.querySelectorAll('[data-lazy-bg]').forEach(function (el) {
            self.loadBackground(el);
        });
    },

    /**
     * إعادة فحص العناصر الجديدة (للمحتوى الديناميكي)
     */
    refresh: function () {
        this.processImages();
        this.processBackgrounds();
        this.processIframes();
    }
};

// تهيئة عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function () {
    LazyLoader.init();
});

// الاستماع لحدث PJAX لإعادة التهيئة
document.addEventListener('pjax:complete', function () {
    LazyLoader.refresh();
});

// تصدير للاستخدام الخارجي
window.LazyLoader = LazyLoader;


// ═══════════════════════════════════════════════════════════════
// 🎯 Intersection Observer - للعناصر المتحركة عند الظهور
// ═══════════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', function () {
    // تحقق من دعم المتصفح
    if (!('IntersectionObserver' in window)) return;

    const animateElements = document.querySelectorAll('.animate-on-scroll');

    if (animateElements.length === 0) return;

    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                // إلغاء المراقبة بعد الظهور
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });

    animateElements.forEach(function (el) {
        observer.observe(el);
    });
});

// ═══════════════════════════════════════════════════════════════
// ⚡ Performance Helpers - أدوات تحسين الأداء
// ═══════════════════════════════════════════════════════════════

/**
 * Debounce function - لتقليل عدد الاستدعاءات
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction() {
        const context = this;
        const args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(function () {
            func.apply(context, args);
        }, wait);
    };
}

/**
 * Throttle function - للحد من معدل الاستدعاء
 */
function throttle(func, limit) {
    let inThrottle;
    return function () {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(function () {
                inThrottle = false;
            }, limit);
        }
    };
}

/**
 * إظهار Skeleton Loading
 * @param {HTMLElement} container - العنصر الحاوي
 * @param {number} rows - عدد الصفوف
 */
function showSkeleton(container, rows = 3) {
    let html = '';
    for (let i = 0; i < rows; i++) {
        html += `
            <div class="skeleton-table-row">
                <div class="skeleton skeleton-avatar"></div>
                <div class="skeleton skeleton-table-cell"></div>
                <div class="skeleton skeleton-table-cell" style="width: 60%;"></div>
                <div class="skeleton skeleton-table-cell" style="width: 40%;"></div>
            </div>
        `;
    }
    container.innerHTML = html;
}

/**
 * إخفاء Skeleton وإظهار المحتوى
 */
function hideSkeleton(container, content) {
    container.innerHTML = content;
    container.classList.add('fade-in-content');
}

/**
 * تحقق من اتصال الإنترنت البطيء
 */
function isSlowConnection() {
    if ('connection' in navigator) {
        const conn = navigator.connection;
        // 2g, slow-2g أو saveData
        if (conn.saveData) return true;
        if (conn.effectiveType === '2g' || conn.effectiveType === 'slow-2g') return true;
        // أقل من 1 Mbps
        if (conn.downlink && conn.downlink < 1) return true;
    }
    return false;
}

/**
 * تقليل الـ Animations على الاتصالات البطيئة
 */
document.addEventListener('DOMContentLoaded', function () {
    if (isSlowConnection()) {
        document.body.classList.add('reduce-animations');

        // إضافة CSS لتقليل الـ animations
        const style = document.createElement('style');
        style.textContent = `
            .reduce-animations * {
                animation-duration: 0.1s !important;
                transition-duration: 0.1s !important;
            }
            .reduce-animations .hero-shapes,
            .reduce-animations .shape {
                display: none !important;
            }
        `;
        document.head.appendChild(style);
    }
});

/**
 * Preload للصور المهمة
 */
function preloadImage(src) {
    return new Promise(function (resolve, reject) {
        const img = new Image();
        img.onload = resolve;
        img.onerror = reject;
        img.src = src;
    });
}

/**
 * Preload لقائمة صور
 */
function preloadImages(sources) {
    return Promise.all(sources.map(preloadImage));
}

