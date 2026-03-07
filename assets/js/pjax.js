/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * School Manager - PJAX Navigation for Sidebar
 * نظام التنقل السريع للقائمة الجانبية
 * ═══════════════════════════════════════════════════════════════════════════════
 * 
 * يوفر تنقل سلس بدون إعادة تحميل كاملة للصفحة
 * يعمل فقط على روابط القائمة الجانبية
 * 
 * @version 1.0.0
 */

(function () {
    'use strict';

    // ═══════════════════════════════════════════════════════════════════════════════
    // إعدادات PJAX
    // ═══════════════════════════════════════════════════════════════════════════════
    const PJAX = {
        enabled: true,
        contentSelector: '.page-content',
        sidebarSelector: '#sidebarNav',
        linkSelector: '#sidebarNav a.nav-item',
        loadingClass: 'pjax-loading',
        fadeClass: 'pjax-fade',
        cache: new Map(),
        cacheTimeout: 5 * 60 * 1000, // 5 دقائق
        abortController: null,

        /**
         * تهيئة PJAX
         */
        init: function () {
            // التحقق من دعم المتصفح
            if (!this.isSupported()) {
                console.log('PJAX: Browser not supported');
                return;
            }

            // ربط روابط القائمة الجانبية
            this.bindLinks();

            // التعامل مع زر الرجوع/التقدم
            this.bindPopState();

            // إضافة أنماط CSS
            this.addStyles();

            console.log('PJAX: Initialized for sidebar navigation');
        },

        /**
         * التحقق من دعم المتصفح
         */
        isSupported: function () {
            return !!(window.history && window.history.pushState && window.fetch);
        },

        /**
         * ربط الروابط
         */
        bindLinks: function () {
            const self = this;

            // استخدام Event Delegation للأداء
            document.addEventListener('click', function (e) {
                // البحث عن أقرب رابط
                const link = e.target.closest(self.linkSelector);

                if (!link) return;

                // التحقق من أن الرابط صالح للـ PJAX
                if (!self.isValidLink(link, e)) return;

                e.preventDefault();

                const url = link.href;

                // تحديث الرابط النشط
                self.updateActiveLink(link);

                // تحميل الصفحة
                self.navigate(url, true);
            });
        },

        /**
         * التحقق من صلاحية الرابط
         */
        isValidLink: function (link, e) {
            // تجاهل إذا تم الضغط على Ctrl/Cmd
            if (e.ctrlKey || e.metaKey || e.shiftKey) return false;

            // تجاهل الروابط الخارجية
            if (link.hostname !== window.location.hostname) return false;

            // تجاهل روابط الـ hash فقط
            if (link.href === window.location.href) return false;

            // تجاهل روابط التحميل
            if (link.hasAttribute('download')) return false;

            // تجاهل الروابط ذات target مختلف
            if (link.target && link.target !== '_self') return false;

            // تجاهل روابط logout
            if (link.href.includes('logout')) return false;

            return true;
        },

        /**
         * التنقل إلى صفحة
         */
        navigate: function (url, pushState) {
            const self = this;

            // إلغاء أي طلب سابق
            if (this.abortController) {
                this.abortController.abort();
            }

            this.abortController = new AbortController();

            // التحقق من الكاش
            const cached = this.getFromCache(url);
            if (cached) {
                this.updateContent(cached.content, cached.title, url, pushState);
                return;
            }

            // إظهار مؤشر التحميل
            this.showLoading();

            // جلب الصفحة
            fetch(url, {
                method: 'GET',
                headers: {
                    'X-PJAX': 'true',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                signal: this.abortController.signal
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(function (html) {
                    // تحليل HTML
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    // استخراج المحتوى
                    const newContent = doc.querySelector(self.contentSelector);
                    const newTitle = doc.title;

                    if (newContent) {
                        // حفظ في الكاش
                        self.addToCache(url, newContent.innerHTML, newTitle);

                        // تحديث المحتوى
                        self.updateContent(newContent.innerHTML, newTitle, url, pushState);
                    } else {
                        // إذا لم نجد المحتوى، نذهب للصفحة مباشرة
                        window.location.href = url;
                    }
                })
                .catch(function (error) {
                    if (error.name === 'AbortError') {
                        console.log('PJAX: Request aborted');
                        return;
                    }
                    console.error('PJAX Error:', error);
                    // في حالة الخطأ، نذهب للصفحة مباشرة
                    window.location.href = url;
                })
                .finally(function () {
                    self.hideLoading();
                });
        },

        /**
         * تحديث المحتوى
         */
        updateContent: function (content, title, url, pushState) {
            const self = this;
            const container = document.querySelector(this.contentSelector);

            if (!container) return;

            // تأثير الخروج
            container.classList.add(this.fadeClass);

            setTimeout(function () {
                // تحديث المحتوى
                container.innerHTML = content;

                // تحديث العنوان
                document.title = title;

                // تحديث URL
                if (pushState) {
                    history.pushState({ url: url }, title, url);
                }

                // تأثير الدخول
                container.classList.remove(self.fadeClass);

                // التمرير لأعلى
                container.scrollTop = 0;
                window.scrollTo(0, 0);

                // إعادة تهيئة السكربتات
                self.reinitializeScripts();

                // إطلاق حدث PJAX
                self.dispatchEvent('pjax:complete', { url: url });

            }, 150);
        },

        /**
         * إعادة تهيئة السكربتات بعد تحميل المحتوى
         */
        reinitializeScripts: function () {
            var self = this;

            // إعادة تهيئة Flatpickr
            if (typeof flatpickr !== 'undefined') {
                var lang = document.documentElement.lang || 'ar';
                document.querySelectorAll('input[type="date"]').forEach(function (input) {
                    if (!input._flatpickr) {
                        flatpickr(input, {
                            locale: lang,
                            dateFormat: 'Y-m-d',
                            altInput: true,
                            altFormat: 'j / n / Y',
                            allowInput: true,
                            disableMobile: true
                        });
                    }
                });
            }

            // إعادة تهيئة Forms AJAX (من ajax.js)
            if (typeof Forms !== 'undefined' && Forms.init) {
                Forms.init();
            }

            // إعادة تهيئة API CSRF Token
            if (typeof API !== 'undefined' && API.refreshCsrf) {
                API.refreshCsrf();
            }

            // إعادة تهيئة LiveFilter (من ajax.js)
            if (typeof LiveFilter !== 'undefined' && LiveFilter.init) {
                LiveFilter.init();
            }

            // إعادة تهيئة Lazy Loading (من main.js)
            if (typeof LazyLoader !== 'undefined' && LazyLoader.refresh) {
                LazyLoader.refresh();
            }

            // إعادة تهيئة البحث العام (من search.js)
            if (typeof GlobalSearch !== 'undefined' && GlobalSearch.init) {
                GlobalSearch.init();
            }

            // إعادة تهيئة التلميحات
            document.querySelectorAll('[title]').forEach(function (el) {
                // أي تهيئة إضافية للتلميحات
            });

            // ═══════════════════════════════════════════════════════════════
            // إعادة تهيئة جميع الفلاتر والبحث
            // ═══════════════════════════════════════════════════════════════
            self.reinitializeAllFilters();

            // إطلاق حدث DOMContentLoaded مخصص
            document.dispatchEvent(new CustomEvent('pjax:scriptsReady'));

            // إعادة تهيئة أي سكربتات inline في المحتوى الجديد
            var contentContainer = document.querySelector(this.contentSelector);
            if (contentContainer) {
                var scripts = contentContainer.querySelectorAll('script');
                scripts.forEach(function (oldScript) {
                    var newScript = document.createElement('script');

                    // نسخ الخصائص
                    Array.from(oldScript.attributes).forEach(function (attr) {
                        newScript.setAttribute(attr.name, attr.value);
                    });

                    // نسخ المحتوى
                    newScript.textContent = oldScript.textContent;

                    // استبدال السكربت
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });
            }

            // إعادة تشغيل الترجمة إذا كانت اللغة إنجليزية
            if (document.documentElement.lang === 'en') {
                // إعادة تطبيق الترجمة على المحتوى الجديد
                if (typeof translatePage === 'function') {
                    setTimeout(translatePage, 100);
                }
            }

            // إعادة إظهار التنبيهات
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function (alert) {
                setTimeout(function () {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(function () {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        },

        /**
         * إعادة تهيئة جميع الفلاتر والبحث في كل الصفحات
         */
        reinitializeAllFilters: function () {
            var self = this;

            // ═══════════════════════════════════════════════════════════════
            // 1. إعادة تهيئة الفلاتر التي تُحدّث الصفحة عند التغيير
            // ═══════════════════════════════════════════════════════════════

            // الفلاتر الشائعة (select, date)
            var filterSelectors = [
                // الحضور
                '#filterClassId', '#filterSection', '#filterDate',
                // الدرجات
                '#classFilter', '#sectionFilter', '#termFilter',
                // عام
                '#dateSelector', '#yearFilter', '#monthFilter',
                // صفحات أخرى
                '#roleFilter', '#statusFilter', '#typeFilter',
                // تقارير الإجازات
                '#yearSelector',
                // تقارير دوام المعلمين
                '#reportMonth', '#reportYearFilter',
                // نماذج
                'select[name="class_id"]', 'select[name="section"]',
                'select[name="term"]', 'select[name="role"]',
                'select[name="status"]', 'select[name="type"]',
                'select[name="month"]', 'select[name="year"]',
                // حقول التاريخ
                'input[type="date"]', 'input[name="date"]',
                // كلاسات عامة
                '.filter-select', '.auto-submit', '.form-control[onchange]'
            ];

            filterSelectors.forEach(function (selector) {
                var elements = document.querySelectorAll(selector);
                elements.forEach(function (el) {
                    if (el && !el.hasAttribute('data-pjax-bound')) {
                        el.setAttribute('data-pjax-bound', 'true');

                        el.addEventListener('change', function () {
                            // التحقق من وجود form
                            var form = this.closest('form');
                            if (form && form.hasAttribute('data-auto-submit')) {
                                form.submit();
                                return;
                            }

                            // تحديث URL وإعادة تحميل
                            var url = new URL(window.location);
                            var paramName = this.name || '';

                            // إذا لم يكن له name، نحاول استخراجه من ID
                            if (!paramName && this.id) {
                                paramName = this.id
                                    .replace('filter', '')
                                    .replace('Filter', '')
                                    .replace('Selector', '')
                                    .replace('report', '')
                                    .replace('Report', '')
                                    .toLowerCase();
                            }

                            // تنظيف اسم المعامل
                            var paramMapping = {
                                'classid': 'class_id',
                                'yearselector': 'year',
                                'reportyearfilter': 'year',
                                'reportmonth': 'month',
                                'yearfilter': 'year',
                                'monthfilter': 'month',
                                'dateselector': 'date'
                            };

                            paramName = paramMapping[paramName] || paramName;

                            if (paramName) {
                                url.searchParams.set(paramName, this.value);
                                window.location.href = url.href;
                            }
                        });
                    }
                });
            });

            // ═══════════════════════════════════════════════════════════════
            // 2. إعادة تهيئة حقول البحث (input text)
            // ═══════════════════════════════════════════════════════════════

            var searchSelectors = [
                '#studentSearch', '#teacherSearch', '#userSearch',
                '#searchInput', '.search-input', 'input[type="search"]',
                'input[placeholder*="بحث"]', 'input[placeholder*="Search"]'
            ];

            searchSelectors.forEach(function (selector) {
                var elements = document.querySelectorAll(selector);
                elements.forEach(function (searchInput) {
                    if (searchInput && !searchInput.hasAttribute('data-pjax-bound')) {
                        searchInput.setAttribute('data-pjax-bound', 'true');

                        // البحث المباشر في الجدول
                        searchInput.addEventListener('input', function () {
                            var searchTerm = this.value.trim().toLowerCase();

                            // البحث في أقرب جدول
                            var container = this.closest('.card, .page-content, form') || document;
                            var table = container.querySelector('table');

                            if (table) {
                                var rows = table.querySelectorAll('tbody tr');
                                rows.forEach(function (row) {
                                    var text = row.textContent.toLowerCase();
                                    row.style.display = (searchTerm === '' || text.includes(searchTerm)) ? '' : 'none';
                                });
                            }
                        });

                        // مسح البحث بـ Escape
                        searchInput.addEventListener('keydown', function (e) {
                            if (e.key === 'Escape') {
                                this.value = '';
                                this.dispatchEvent(new Event('input'));
                            }
                        });
                    }
                });
            });

            // ═══════════════════════════════════════════════════════════════
            // 3. إعادة تهيئة أزرار التصفية (role, status filters)
            // ═══════════════════════════════════════════════════════════════

            var buttonFilters = document.querySelectorAll('[data-filter], .filter-btn');
            buttonFilters.forEach(function (btn) {
                if (!btn.hasAttribute('data-pjax-bound')) {
                    btn.setAttribute('data-pjax-bound', 'true');
                    btn.addEventListener('click', function () {
                        var filterValue = this.dataset.filter || this.dataset.value;
                        var filterType = this.dataset.filterType || 'status';

                        var url = new URL(window.location);
                        url.searchParams.set(filterType, filterValue);
                        window.location.href = url.href;
                    });
                }
            });

            // ═══════════════════════════════════════════════════════════════
            // 4. استدعاء دوال التصفية المخصصة إذا كانت موجودة
            // ═══════════════════════════════════════════════════════════════

            var customFilterFunctions = [
                'filterUsers', 'filterStudents', 'filterTeachers',
                'filterGrades', 'initFilters', 'setupFilters'
            ];

            customFilterFunctions.forEach(function (funcName) {
                if (typeof window[funcName] === 'function') {
                    try {
                        // لا نستدعيها مباشرة، لكن نتأكد أنها مرتبطة
                    } catch (e) {
                        console.log('Filter function error:', funcName, e);
                    }
                }
            });
        },

        /**
         * التعامل مع زر الرجوع/التقدم
         */
        bindPopState: function () {
            const self = this;

            window.addEventListener('popstate', function (e) {
                if (e.state && e.state.url) {
                    // تحديث الرابط النشط
                    const link = document.querySelector(self.linkSelector + '[href="' + e.state.url + '"]');
                    if (link) {
                        self.updateActiveLink(link);
                    }

                    // تحميل الصفحة
                    self.navigate(e.state.url, false);
                }
            });
        },

        /**
         * تحديث الرابط النشط
         */
        updateActiveLink: function (activeLink) {
            // إزالة active من جميع الروابط
            document.querySelectorAll(this.linkSelector).forEach(function (link) {
                link.classList.remove('active');
            });

            // إضافة active للرابط الجديد
            activeLink.classList.add('active');
        },

        /**
         * إظهار مؤشر التحميل
         */
        showLoading: function () {
            document.body.classList.add(this.loadingClass);

            // إضافة شريط التحميل العلوي
            var progressBar = document.getElementById('pjax-progress');
            if (!progressBar) {
                progressBar = document.createElement('div');
                progressBar.id = 'pjax-progress';
                document.body.appendChild(progressBar);
            }
            progressBar.style.width = '0%';
            progressBar.classList.add('active');

            // تحريك الشريط
            setTimeout(function () {
                progressBar.style.width = '70%';
            }, 50);
        },

        /**
         * إخفاء مؤشر التحميل
         */
        hideLoading: function () {
            document.body.classList.remove(this.loadingClass);

            var progressBar = document.getElementById('pjax-progress');
            if (progressBar) {
                progressBar.style.width = '100%';
                setTimeout(function () {
                    progressBar.classList.remove('active');
                }, 200);
            }
        },

        /**
         * إضافة للكاش
         */
        addToCache: function (url, content, title) {
            this.cache.set(url, {
                content: content,
                title: title,
                timestamp: Date.now()
            });
        },

        /**
         * الحصول من الكاش
         */
        getFromCache: function (url) {
            const cached = this.cache.get(url);

            if (!cached) return null;

            // التحقق من انتهاء الصلاحية
            if (Date.now() - cached.timestamp > this.cacheTimeout) {
                this.cache.delete(url);
                return null;
            }

            return cached;
        },

        /**
         * مسح الكاش
         */
        clearCache: function () {
            this.cache.clear();
        },

        /**
         * إطلاق حدث مخصص
         */
        dispatchEvent: function (name, detail) {
            document.dispatchEvent(new CustomEvent(name, { detail: detail }));
        },

        /**
         * إضافة أنماط CSS
         */
        addStyles: function () {
            if (document.getElementById('pjax-styles')) return;

            var style = document.createElement('style');
            style.id = 'pjax-styles';
            style.textContent = `
                /* شريط التقدم */
                #pjax-progress {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 3px;
                    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
                    z-index: 99999;
                    transition: width 0.3s ease, opacity 0.2s ease;
                    opacity: 0;
                    pointer-events: none;
                }
                
                #pjax-progress.active {
                    opacity: 1;
                }
                
                /* تأثير التلاشي */
                .page-content.pjax-fade {
                    opacity: 0.5;
                    transform: translateY(5px);
                    transition: opacity 0.15s ease, transform 0.15s ease;
                }
                
                .page-content {
                    transition: opacity 0.15s ease, transform 0.15s ease;
                }
                
                /* حالة التحميل */
                body.pjax-loading .page-content {
                    pointer-events: none;
                }
                
                /* تأثير الدخول للمحتوى */
                @keyframes pjaxFadeIn {
                    from {
                        opacity: 0.5;
                        transform: translateY(5px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                
                .page-content:not(.pjax-fade) {
                    animation: pjaxFadeIn 0.2s ease;
                }
            `;
            document.head.appendChild(style);
        }
    };

    // ═══════════════════════════════════════════════════════════════════════════════
    // تهيئة عند تحميل الصفحة
    // ═══════════════════════════════════════════════════════════════════════════════
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            PJAX.init();
        });
    } else {
        PJAX.init();
    }

    // تصدير للاستخدام الخارجي
    window.PJAX = PJAX;

})();
