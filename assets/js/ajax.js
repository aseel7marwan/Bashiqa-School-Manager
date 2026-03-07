/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * School Manager - AJAX Library
 * مكتبة JavaScript للتعامل مع طلبات AJAX
 * ═══════════════════════════════════════════════════════════════════════════════
 * 
 * توفر واجهة سهلة وموحدة للتعامل مع API
 * مع دعم:
 * - Loading indicators تلقائية
 * - معالجة الأخطاء الموحدة
 * - رسائل Toast للإشعارات
 * - تحديث الواجهة التلقائي
 * 
 * @version 3.0.0
 */

// ═══════════════════════════════════════════════════════════════════════════════
// إعدادات عامة
// ═══════════════════════════════════════════════════════════════════════════════
const API = {
    baseUrl: 'api.php',
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',

    /**
     * إعداد CSRF Token
     */
    async refreshCsrf() {
        try {
            const response = await this.get('system', 'csrf_token');
            if (response.success) {
                this.csrfToken = response.data.token;
            }
        } catch (e) {
            console.error('Failed to refresh CSRF token');
        }
    },

    /**
     * طلب GET
     */
    async get(module, action, params = {}) {
        const url = new URL(this.baseUrl, window.location.origin + window.location.pathname.replace(/[^/]*$/, ''));
        url.searchParams.set('module', module);
        url.searchParams.set('action', action);

        Object.entries(params).forEach(([key, value]) => {
            if (value !== null && value !== undefined) {
                url.searchParams.set(key, value);
            }
        });

        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            return await this.handleResponse(response);
        } catch (error) {
            return this.handleError(error);
        }
    },

    /**
     * طلب POST
     */
    async post(module, action, data = {}) {
        const url = new URL(this.baseUrl, window.location.origin + window.location.pathname.replace(/[^/]*$/, ''));
        url.searchParams.set('module', module);
        url.searchParams.set('action', action);

        const formData = new FormData();
        formData.append('csrf_token', this.csrfToken);

        Object.entries(data).forEach(([key, value]) => {
            if (value !== null && value !== undefined) {
                formData.append(key, value);
            }
        });

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                credentials: 'same-origin',
                body: formData
            });

            return await this.handleResponse(response);
        } catch (error) {
            return this.handleError(error);
        }
    },

    /**
     * طلب POST مع JSON
     */
    async postJson(module, action, data = {}) {
        const url = new URL(this.baseUrl, window.location.origin + window.location.pathname.replace(/[^/]*$/, ''));
        url.searchParams.set('module', module);
        url.searchParams.set('action', action);

        data.csrf_token = this.csrfToken;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                credentials: 'same-origin',
                body: JSON.stringify(data)
            });

            return await this.handleResponse(response);
        } catch (error) {
            return this.handleError(error);
        }
    },

    /**
     * معالجة الاستجابة
     */
    async handleResponse(response) {
        const contentType = response.headers.get('content-type');

        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('استجابة غير صالحة من الخادم');
        }

        const data = await response.json();

        // التحقق من إعادة التوجيه (انتهاء الجلسة)
        if (data.redirect) {
            window.location.href = data.redirect;
            return data;
        }

        return data;
    },

    /**
     * معالجة الأخطاء
     */
    handleError(error) {
        console.error('API Error:', error);
        return {
            success: false,
            message: error.message || 'حدث خطأ في الاتصال'
        };
    }
};

// ═══════════════════════════════════════════════════════════════════════════════
// مكتبة واجهة المستخدم
// ═══════════════════════════════════════════════════════════════════════════════
const UI = {
    /**
     * إظهار رسالة Toast
     */
    toast(message, type = 'info', duration = 3000) {
        // إزالة أي toast موجود
        document.querySelectorAll('.ajax-toast').forEach(el => el.remove());

        const toast = document.createElement('div');
        toast.className = `ajax-toast ajax-toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <span class="toast-icon">${this.getIcon(type)}</span>
                <span class="toast-message">${message}</span>
                <button class="toast-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;

        document.body.appendChild(toast);

        // Animation
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        // Auto remove
        if (duration > 0) {
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
    },

    /**
     * إظهار رسالة نجاح
     */
    success(message) {
        this.toast(message, 'success');
    },

    /**
     * إظهار رسالة خطأ
     */
    error(message) {
        this.toast(message, 'error', 5000);
    },

    /**
     * إظهار رسالة تحذير
     */
    warning(message) {
        this.toast(message, 'warning', 4000);
    },

    /**
     * الحصول على أيقونة
     */
    getIcon(type) {
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        return icons[type] || icons.info;
    },

    /**
     * إظهار مؤشر التحميل
     */
    showLoading(element = null, text = 'جاري التحميل...') {
        if (element) {
            element.classList.add('ajax-loading');
            element.dataset.originalContent = element.innerHTML;
            element.innerHTML = `<span class="spinner"></span> ${text}`;
            element.disabled = true;
        } else {
            // Global loading overlay
            let overlay = document.getElementById('ajax-loading-overlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'ajax-loading-overlay';
                overlay.innerHTML = `
                    <div class="loading-content">
                        <div class="spinner-large"></div>
                        <p>${text}</p>
                    </div>
                `;
                document.body.appendChild(overlay);
            }
            overlay.classList.add('show');
        }
    },

    /**
     * إخفاء مؤشر التحميل
     */
    hideLoading(element = null) {
        if (element) {
            element.classList.remove('ajax-loading');
            if (element.dataset.originalContent) {
                element.innerHTML = element.dataset.originalContent;
                delete element.dataset.originalContent;
            }
            element.disabled = false;
        } else {
            const overlay = document.getElementById('ajax-loading-overlay');
            if (overlay) {
                overlay.classList.remove('show');
            }
        }
    },

    /**
     * تأكيد الحذف
     */
    async confirmDelete(message = 'هل أنت متأكد من الحذف؟') {
        return new Promise((resolve) => {
            // إزالة أي modal موجود
            document.querySelectorAll('.ajax-confirm-modal').forEach(el => el.remove());

            const modal = document.createElement('div');
            modal.className = 'ajax-confirm-modal';
            modal.innerHTML = `
                <div class="confirm-content">
                    <div class="confirm-icon">⚠</div>
                    <h3>تأكيد الحذف</h3>
                    <p>${message}</p>
                    <div class="confirm-buttons">
                        <button class="btn btn-secondary" data-action="cancel">إلغاء</button>
                        <button class="btn btn-danger" data-action="confirm">حذف</button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            requestAnimationFrame(() => modal.classList.add('show'));

            modal.addEventListener('click', (e) => {
                const action = e.target.dataset.action;
                if (action === 'confirm') {
                    resolve(true);
                } else if (action === 'cancel' || e.target === modal) {
                    resolve(false);
                }
                modal.classList.remove('show');
                setTimeout(() => modal.remove(), 300);
            });
        });
    },

    /**
     * تحديث جزء من الصفحة
     */
    async refreshSection(selector, url = null) {
        const element = document.querySelector(selector);
        if (!element) return;

        this.showLoading(element);

        try {
            const response = await fetch(url || window.location.href, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newContent = doc.querySelector(selector);

            if (newContent) {
                element.innerHTML = newContent.innerHTML;
            }
        } catch (error) {
            console.error('Refresh failed:', error);
        } finally {
            this.hideLoading(element);
        }
    }
};

// ═══════════════════════════════════════════════════════════════════════════════
// معالج النماذج التلقائي
// ═══════════════════════════════════════════════════════════════════════════════
const Forms = {
    /**
     * تهيئة النماذج للعمل مع AJAX
     */
    init() {
        document.querySelectorAll('form[data-ajax="true"]').forEach(form => {
            this.bind(form);
        });
    },

    /**
     * ربط نموذج بـ AJAX
     */
    bind(form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = form.querySelector('[type="submit"]');
            const module = form.dataset.module;
            const action = form.dataset.action;

            if (!module || !action) {
                console.error('Form missing data-module or data-action');
                return;
            }

            UI.showLoading(submitBtn);

            // جمع البيانات
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            try {
                const response = await API.post(module, action, data);

                if (response.success) {
                    UI.success(response.message || 'تمت العملية بنجاح');

                    // إغلاق Modal إذا كان موجوداً
                    const modal = form.closest('.modal');
                    if (modal) {
                        modal.querySelector('.close')?.click();
                    }

                    // تحديث الجدول إذا تم تحديده
                    const tableSelector = form.dataset.refreshTable;
                    if (tableSelector) {
                        UI.refreshSection(tableSelector);
                    }

                    // إعادة تعيين النموذج
                    if (form.dataset.resetOnSuccess !== 'false') {
                        form.reset();
                    }

                    // استدعاء callback إذا تم تحديده
                    const callback = form.dataset.callback;
                    if (callback && window[callback]) {
                        window[callback](response);
                    }
                } else {
                    UI.error(response.message || 'حدث خطأ');
                }
            } catch (error) {
                UI.error('حدث خطأ في الاتصال');
            } finally {
                UI.hideLoading(submitBtn);
            }
        });
    }
};

// ═══════════════════════════════════════════════════════════════════════════════
// أدوات مساعدة
// ═══════════════════════════════════════════════════════════════════════════════
const Utils = {
    /**
     * Debounce للبحث
     */
    debounce(func, wait = 300) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * تنسيق التاريخ
     */
    formatDate(date, format = 'YYYY-MM-DD') {
        const d = new Date(date);
        const pad = n => n.toString().padStart(2, '0');

        return format
            .replace('YYYY', d.getFullYear())
            .replace('MM', pad(d.getMonth() + 1))
            .replace('DD', pad(d.getDate()))
            .replace('HH', pad(d.getHours()))
            .replace('mm', pad(d.getMinutes()));
    },

    /**
     * تحويل رقم لعربي
     */
    toArabicNumber(num) {
        const arabicNums = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        return num.toString().replace(/[0-9]/g, d => arabicNums[d]);
    }
};

// ═══════════════════════════════════════════════════════════════════════════════
// التهيئة التلقائية
// ═══════════════════════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    // تهيئة النماذج
    Forms.init();

    // تحديث CSRF Token
    API.refreshCsrf();

    // ═══════════════════════════════════════════════════════════════════════════════
    // معالجة أزرار الحذف الجديدة (data-delete)
    // ═══════════════════════════════════════════════════════════════════════════════
    document.addEventListener('click', async (e) => {
        const deleteBtn = e.target.closest('[data-delete]');
        if (deleteBtn) {
            e.preventDefault();
            e.stopPropagation();

            const confirmed = await UI.confirmDelete(deleteBtn.dataset.deleteMessage || 'هل أنت متأكد من الحذف؟');
            if (!confirmed) return;

            const module = deleteBtn.dataset.module;
            const id = deleteBtn.dataset.id;

            UI.showLoading(deleteBtn);

            const response = await API.post(module, 'delete', { id });

            UI.hideLoading(deleteBtn);

            if (response.success) {
                UI.success(response.message);

                // إزالة الصف من الجدول بتأثير حركي
                const row = deleteBtn.closest('tr');
                if (row) {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-20px)';
                    setTimeout(() => row.remove(), 300);
                }
            } else {
                UI.error(response.message);
            }
        }
    });

    // ═══════════════════════════════════════════════════════════════════════════════
    // معالجة أزرار تبديل الحالة (data-toggle-status)
    // ═══════════════════════════════════════════════════════════════════════════════
    document.addEventListener('click', async (e) => {
        const toggleBtn = e.target.closest('[data-toggle-status]');
        if (toggleBtn) {
            e.preventDefault();
            e.stopPropagation();

            const module = toggleBtn.dataset.module;
            const id = toggleBtn.dataset.id;
            const currentStatus = toggleBtn.dataset.currentStatus;
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';

            UI.showLoading(toggleBtn);

            const response = await API.post(module, 'toggle_status', { id, status: newStatus });

            UI.hideLoading(toggleBtn);

            if (response.success) {
                UI.success(response.message || 'تم تحديث الحالة');

                // تحديث جميع أزرار تبديل الحالة لنفس المستخدم
                const allToggleBtns = document.querySelectorAll(`[data-toggle-status][data-id="${id}"]`);
                allToggleBtns.forEach(btn => {
                    btn.dataset.currentStatus = newStatus;

                    // إذا كان الزر شارة الحالة (في عمود الحالة)
                    if (btn.classList.contains('badge')) {
                        if (newStatus === 'active') {
                            btn.className = 'badge badge-success';
                            btn.textContent = 'نشط';
                            btn.title = 'اضغط لتبديل الحالة';
                        } else {
                            btn.className = 'badge badge-danger';
                            btn.textContent = 'معطل';
                            btn.title = 'اضغط لتبديل الحالة';
                        }
                    }
                    // إذا كان زر في عمود الإجراءات
                    else if (btn.classList.contains('btn')) {
                        if (newStatus === 'active') {
                            btn.className = 'btn btn-warning btn-sm';
                            btn.innerHTML = '🚫';
                            btn.title = 'تعطيل الحساب';
                        } else {
                            btn.className = 'btn btn-success btn-sm';
                            btn.innerHTML = '✅';
                            btn.title = 'تنشيط الحساب';
                        }
                    }
                });
            } else {
                UI.error(response.message);
            }
        }
    });

    // ═══════════════════════════════════════════════════════════════════════════════
    // تحويل نماذج الحذف القديمة تلقائياً (للتوافق مع الكود القديم)
    // ═══════════════════════════════════════════════════════════════════════════════
    document.querySelectorAll('form[onsubmit*="confirmDelete"]').forEach(form => {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            // استخراج رسالة التأكيد من الـ onsubmit
            const onsubmit = form.getAttribute('onsubmit') || '';
            const match = onsubmit.match(/confirmDelete\(['"](.+?)['"]\)/);
            const message = match ? match[1] : 'هل أنت متأكد من الحذف؟';

            const confirmed = await UI.confirmDelete(message);
            if (!confirmed) return;

            // إظهار التحميل على زر الإرسال
            const submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) UI.showLoading(submitBtn);

            // جمع بيانات النموذج
            const formData = new FormData(form);
            const action = form.getAttribute('action');

            try {
                const response = await fetch(action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                // إعادة تحميل الصفحة بعد الحذف الناجح
                if (response.ok) {
                    UI.success('تم الحذف بنجاح');
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    UI.error('حدث خطأ أثناء الحذف');
                    if (submitBtn) UI.hideLoading(submitBtn);
                }
            } catch (error) {
                UI.error('حدث خطأ في الاتصال');
                if (submitBtn) UI.hideLoading(submitBtn);
            }
        });

        // إزالة الـ onsubmit القديم
        form.removeAttribute('onsubmit');
    });

    // ═══════════════════════════════════════════════════════════════════════════════
    // 🔍 نظام البحث والفلترة الحي (Live Search & Filter System)
    // ═══════════════════════════════════════════════════════════════════════════════

    const LiveFilter = {
        debounceTimeout: null,

        /**
         * تهيئة نظام الفلترة الحية على جميع الجداول
         */
        init() {
            // البحث الحي في الجداول
            document.querySelectorAll('[data-live-search]').forEach(input => {
                this.initTableSearch(input);
            });

            // الفلترة الحية (select, input)
            document.querySelectorAll('[data-live-filter]').forEach(element => {
                this.initLiveFilter(element);
            });

            // حقول البحث العامة
            document.querySelectorAll('input[name="search"], input[name="q"], input.search-input').forEach(input => {
                if (!input.hasAttribute('data-live-search') && !input.hasAttribute('data-live-initialized')) {
                    this.autoInitSearch(input);
                }
            });

            // فلاتر الـ select التي تُرسل النموذج
            document.querySelectorAll('select[onchange*="submit"], select.auto-filter').forEach(select => {
                if (!select.hasAttribute('data-live-filter') && !select.hasAttribute('data-live-initialized')) {
                    this.autoInitSelectFilter(select);
                }
            });
        },

        /**
         * تهيئة تلقائية للبحث في الجدول
         */
        autoInitSearch(input) {
            input.setAttribute('data-live-initialized', 'true');

            // البحث عن أقرب جدول
            const container = input.closest('.card, .page-content, main') || document.body;
            const table = container.querySelector('table');

            if (!table) return;

            const tbody = table.querySelector('tbody');
            if (!tbody) return;

            // إضافة مؤشر البحث
            input.style.transition = 'border-color 0.3s, box-shadow 0.3s';

            const doSearch = Utils.debounce((value) => {
                const searchValue = value.toLowerCase().trim();
                const rows = tbody.querySelectorAll('tr');
                let visibleCount = 0;

                rows.forEach(row => {
                    if (row.classList.contains('empty-row')) return;

                    const text = row.textContent.toLowerCase();
                    const matches = searchValue === '' || text.includes(searchValue);

                    if (matches) {
                        row.style.display = '';
                        row.style.animation = searchValue ? 'fadeIn 0.3s ease' : '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                // تحديث عداد النتائج
                this.updateResultsCount(container, visibleCount, rows.length);

                // تأثير بصري
                input.style.borderColor = searchValue ? '#667eea' : '';
                input.style.boxShadow = searchValue ? '0 0 0 3px rgba(102, 126, 234, 0.15)' : '';

            }, 150);

            input.addEventListener('input', (e) => doSearch(e.target.value));
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    e.target.value = '';
                    doSearch('');
                }
            });

            // إضافة placeholder إذا لم يكن موجوداً
            if (!input.placeholder) {
                input.placeholder = '🔍 ابحث...';
            }
        },

        /**
         * تهيئة فلترة الـ select الحية
         */
        autoInitSelectFilter(select) {
            select.setAttribute('data-live-initialized', 'true');

            // إزالة onchange القديم
            const originalOnchange = select.getAttribute('onchange');
            select.removeAttribute('onchange');

            const container = select.closest('.card, .page-content, main') || document.body;
            const table = container.querySelector('table');

            // إذا لم يكن هناك جدول، استخدم الطريقة القديمة
            if (!table) {
                select.addEventListener('change', () => {
                    select.closest('form')?.submit();
                });
                return;
            }

            const tbody = table.querySelector('tbody');
            if (!tbody) return;

            // الحصول على اسم الفلتر
            const filterName = select.name;
            const filterIndex = this.getColumnIndex(table, filterName);

            select.addEventListener('change', () => {
                this.applyFilters(container);
            });
        },

        /**
         * الحصول على فهرس العمود
         */
        getColumnIndex(table, name) {
            const headers = table.querySelectorAll('thead th');
            const mapping = {
                'class_id': 0,
                'section': 1,
                'status': -1, // آخر عمود أو عمود محدد
                'role': -1
            };
            return mapping[name] ?? -1;
        },

        /**
         * تطبيق جميع الفلاتر على الجدول
         */
        applyFilters(container) {
            const table = container.querySelector('table');
            if (!table) return;

            const tbody = table.querySelector('tbody');
            if (!tbody) return;

            // جمع قيم الفلاتر
            const filters = {};
            container.querySelectorAll('select[data-live-initialized], select[data-live-filter]').forEach(select => {
                const value = select.value;
                if (value && value !== 'all' && value !== '') {
                    filters[select.name] = value.toLowerCase();
                }
            });

            // جمع قيمة البحث
            const searchInput = container.querySelector('input[name="search"], input[name="q"], input.search-input');
            const searchValue = searchInput ? searchInput.value.toLowerCase().trim() : '';

            const rows = tbody.querySelectorAll('tr');
            let visibleCount = 0;

            rows.forEach(row => {
                if (row.classList.contains('empty-row')) return;

                const text = row.textContent.toLowerCase();
                let matches = true;

                // تطبيق البحث النصي
                if (searchValue && !text.includes(searchValue)) {
                    matches = false;
                }

                // تطبيق الفلاتر
                Object.entries(filters).forEach(([name, value]) => {
                    // البحث في سمات data أو في النص
                    const dataValue = row.dataset[name]?.toLowerCase();
                    if (dataValue && dataValue !== value) {
                        matches = false;
                    }
                });

                row.style.display = matches ? '' : 'none';
                if (matches) visibleCount++;
            });

            this.updateResultsCount(container, visibleCount, rows.length);
        },

        /**
         * تهيئة البحث في الجدول
         */
        initTableSearch(input) {
            const tableSelector = input.dataset.liveSearch;
            const table = document.querySelector(tableSelector);
            if (!table) return;

            const tbody = table.querySelector('tbody');
            if (!tbody) return;

            const doSearch = Utils.debounce((value) => {
                const searchValue = value.toLowerCase().trim();
                const rows = tbody.querySelectorAll('tr');
                let visibleCount = 0;

                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    const matches = searchValue === '' || text.includes(searchValue);
                    row.style.display = matches ? '' : 'none';
                    if (matches) visibleCount++;
                });

                // تحديث عداد النتائج
                const counter = document.querySelector('[data-search-count]');
                if (counter) {
                    counter.textContent = `${visibleCount} نتيجة`;
                }
            }, 200);

            input.addEventListener('input', (e) => doSearch(e.target.value));
        },

        /**
         * تهيئة فلتر حي
         */
        initLiveFilter(element) {
            const config = {
                target: element.dataset.liveFilter, // الجدول المستهدف
                column: element.dataset.filterColumn, // العمود المراد فلترته
                attribute: element.dataset.filterAttribute // سمة data في الصف
            };

            const table = document.querySelector(config.target);
            if (!table) return;

            const doFilter = () => {
                const value = element.value.toLowerCase();
                const rows = table.querySelectorAll('tbody tr');

                rows.forEach(row => {
                    if (value === '' || value === 'all') {
                        row.style.display = '';
                        return;
                    }

                    let cellValue = '';

                    if (config.attribute) {
                        cellValue = row.dataset[config.attribute]?.toLowerCase() || '';
                    } else if (config.column) {
                        const cell = row.cells[parseInt(config.column)];
                        cellValue = cell?.textContent.toLowerCase() || '';
                    } else {
                        cellValue = row.textContent.toLowerCase();
                    }

                    row.style.display = cellValue.includes(value) ? '' : 'none';
                });
            };

            element.addEventListener('change', doFilter);
            if (element.tagName === 'INPUT') {
                element.addEventListener('input', Utils.debounce(doFilter, 200));
            }
        },

        /**
         * تحديث عداد النتائج
         */
        updateResultsCount(container, visible, total) {
            let counter = container.querySelector('.filter-results-count');

            if (!counter) {
                // إنشاء عداد إذا لم يكن موجوداً
                const searchInput = container.querySelector('input[name="search"], input[name="q"], input.search-input');
                if (searchInput) {
                    counter = document.createElement('span');
                    counter.className = 'filter-results-count';
                    counter.style.cssText = 'margin-right: 0.5rem; font-size: 0.85rem; color: var(--text-muted); transition: opacity 0.3s;';
                    searchInput.parentNode.insertBefore(counter, searchInput.nextSibling);
                }
            }

            if (counter) {
                if (visible < total) {
                    counter.textContent = `${visible} من ${total}`;
                    counter.style.opacity = '1';
                } else {
                    counter.style.opacity = '0';
                }
            }
        }
    };

    // تهيئة نظام الفلترة
    LiveFilter.init();

    // تصدير LiveFilter للاستخدام العام
    window.LiveFilter = LiveFilter;

    // Library loaded
});

// تصدير للاستخدام العام
window.API = API;
window.UI = UI;
window.Forms = Forms;
window.Utils = Utils;
