/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * نظام البحث الشامل - Global Search System
 * بحث احترافي مع تنقل بلوحة المفاتيح وتأثيرات سلسة
 * ═══════════════════════════════════════════════════════════════════════════════
 */

class GlobalSearch {
    constructor() {
        this.overlay = null;
        this.modal = null;
        this.input = null;
        this.resultsContainer = null;
        this.selectedIndex = -1;
        this.results = [];
        this.debounceTimer = null;
        this.currentFilter = 'all';
        this.isLoading = false;
        this.baseUrl = document.querySelector('meta[name="base-url"]')?.content || '/School-Manager/';
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        this.userRole = document.querySelector('meta[name="user-role"]')?.content || 'student';

        this.init();
    }

    init() {
        this.createModal();
        this.bindEvents();
        this.setupKeyboardShortcut();
    }

    /**
     * إنشاء نافذة البحث
     */
    createModal() {
        const lang = document.documentElement.lang || 'ar';
        const isAr = lang === 'ar';

        const overlay = document.createElement('div');
        overlay.className = 'search-modal-overlay';
        overlay.id = 'globalSearchOverlay';

        overlay.innerHTML = `
            <div class="search-modal" role="dialog" aria-modal="true" aria-label="${isAr ? 'البحث الشامل' : 'Global Search'}">
                <div class="search-header">
                    <div class="search-input-wrapper" id="searchInputWrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input 
                            type="text" 
                            id="globalSearchInput" 
                            placeholder="${this.getSearchPlaceholder(isAr)}"
                            autocomplete="off"
                            autocapitalize="off"
                            spellcheck="false"
                        >
                        <div class="search-loading"></div>
                        <button class="search-close-btn" id="searchCloseBtn" title="${isAr ? 'إغلاق' : 'Close'}">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="search-filters" id="searchFilters">
                        <button class="search-filter-btn active" data-filter="all">
                            <i class="fas fa-globe"></i> ${isAr ? 'الكل' : 'All'}
                        </button>
                        ${this.userRole !== 'student' ? `
                        <button class="search-filter-btn" data-filter="actions" ${this.userRole === 'teacher' ? 'style="display:none"' : ''}>
                            <i class="fas fa-bolt"></i> ${isAr ? 'إجراءات' : 'Actions'}
                        </button>
                        <button class="search-filter-btn" data-filter="students">
                            <i class="fas fa-user-graduate"></i> ${isAr ? 'الطلاب' : 'Students'}
                        </button>
                        ` : ''}
                        ${(this.userRole === 'admin' || this.userRole === 'assistant') ? `
                        <button class="search-filter-btn" data-filter="teachers">
                            <i class="fas fa-chalkboard-teacher"></i> ${isAr ? 'المعلمين' : 'Teachers'}
                        </button>
                        ` : ''}
                        <button class="search-filter-btn" data-filter="pages">
                            <i class="fas fa-file-alt"></i> ${isAr ? 'الصفحات' : 'Pages'}
                        </button>
                    </div>
                </div>
                <div class="search-results" id="searchResults">
                    ${this.getInitialContent(isAr)}
                </div>
                <div class="search-footer">
                    <div class="search-footer-hint">
                        <span><kbd>↑</kbd><kbd>↓</kbd> ${isAr ? 'للتنقل' : 'Navigate'}</span>
                        <span><kbd>↵</kbd> ${isAr ? 'للفتح' : 'Open'}</span>
                    </div>
                    <span class="search-footer-close"><kbd>ESC</kbd> ${isAr ? 'للإغلاق' : 'Close'}</span>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        this.overlay = overlay;
        this.modal = overlay.querySelector('.search-modal');
        this.input = document.getElementById('globalSearchInput');
        this.resultsContainer = document.getElementById('searchResults');
        this.inputWrapper = document.getElementById('searchInputWrapper');
    }

    /**
     * الحصول على placeholder مناسب حسب دور المستخدم
     */
    getSearchPlaceholder(isAr) {
        if (this.userRole === 'student') {
            return isAr ? 'ابحث عن صفحة...' : 'Search for a page...';
        } else if (this.userRole === 'teacher') {
            return isAr ? 'ابحث عن طالب، صفحة...' : 'Search for student, page...';
        } else {
            return isAr ? 'ابحث عن طالب، معلم، صفحة...' : 'Search for student, teacher, page...';
        }
    }

    /**
     * المحتوى الأولي (الروابط السريعة)
     */
    getInitialContent(isAr) {
        // تعريف الروابط السريعة مع الأدوار المسموحة
        let shortcuts = [
            { icon: 'fa-home', color: '#3b82f6', page: 'dashboard', title: isAr ? 'الرئيسية' : 'Dashboard', roles: ['admin', 'assistant', 'teacher', 'student'] },
            { icon: 'fa-user-graduate', color: '#10b981', page: 'students', title: isAr ? 'البطاقات المدرسية' : 'Students', roles: ['admin', 'assistant', 'teacher'] },
            { icon: 'fa-chalkboard-teacher', color: '#6366f1', page: 'teachers', title: isAr ? 'المعلمين' : 'Teachers', roles: ['admin', 'assistant'] },
            { icon: 'fa-user-check', color: '#f59e0b', page: 'attendance', title: isAr ? 'الحضور' : 'Attendance', roles: ['admin', 'assistant', 'teacher'] },
            { icon: 'fa-chart-bar', color: '#ec4899', page: 'grades_report', title: isAr ? 'كشف الدرجات' : 'Grades Report', roles: ['admin', 'assistant', 'teacher'] },
            { icon: 'fa-table', color: '#06b6d4', page: 'schedule', title: isAr ? 'الجدول' : 'Schedule', roles: ['admin', 'assistant', 'teacher', 'student'] },
            { icon: 'fa-calendar-alt', color: '#f97316', page: 'events', title: isAr ? 'التقويم' : 'Events', roles: ['admin', 'assistant', 'teacher', 'student'] },
            // روابط خاصة بالطالب
            { icon: 'fa-id-card', color: '#8b5cf6', page: 'student_profile', title: isAr ? 'بطاقتي' : 'My Profile', roles: ['student'] },
            { icon: 'fa-clipboard-list', color: '#10b981', page: 'student_attendance', title: isAr ? 'سجل حضوري' : 'My Attendance', roles: ['student'] },
            { icon: 'fa-chart-line', color: '#ec4899', page: 'grades_report', title: isAr ? 'درجاتي' : 'My Grades', roles: ['student'] },
        ];

        // فلترة حسب دور المستخدم
        shortcuts = shortcuts.filter(s => s.roles.includes(this.userRole));

        return `
            <div class="search-initial">
                <div class="search-recent-title">
                    <i class="fas fa-bolt"></i> ${isAr ? 'وصول سريع' : 'Quick Access'}
                </div>
                <div class="search-shortcuts">
                    ${shortcuts.map(s => `
                        <a href="${this.baseUrl}${s.page}" class="search-shortcut-item">
                            <div class="search-shortcut-icon" style="background: ${s.color}; color: white;">
                                <i class="fas ${s.icon}"></i>
                            </div>
                            <span>${s.title}</span>
                        </a>
                    `).join('')}
                </div>
            </div>
        `;
    }

    /**
     * عرض اقتراحات حسب الفلتر المختار
     */
    showFilterSuggestions(filter) {
        const lang = document.documentElement.lang || 'ar';
        const isAr = lang === 'ar';

        // اقتراحات كل فلتر
        const suggestions = {
            'all': this.getInitialContent(isAr),

            'actions': `
                <div class="search-initial">
                    <div class="search-recent-title">
                        <i class="fas fa-bolt"></i> ${isAr ? 'إجراءات شائعة' : 'Common Actions'}
                    </div>
                    <div class="search-suggestions-list">
                        ${this.getActionSuggestions(isAr)}
                    </div>
                </div>
            `,

            'students': `
                <div class="search-initial">
                    <div class="search-recent-title">
                        <i class="fas fa-user-graduate"></i> ${isAr ? 'إجراءات الطلاب' : 'Student Actions'}
                    </div>
                    <div class="search-suggestions-list">
                        <a href="${this.baseUrl}students?action=add" class="search-suggestion-item">
                            <div class="search-suggestion-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="search-suggestion-text">
                                <span class="title">${isAr ? 'إضافة تلميذ جديد' : 'Add New Student'}</span>
                                <span class="hint">${isAr ? 'إنشاء بطاقة مدرسية' : 'Create student card'}</span>
                            </div>
                        </a>
                        <a href="${this.baseUrl}students" class="search-suggestion-item">
                            <div class="search-suggestion-icon" style="background: linear-gradient(135deg, #6366f1, #4f46e5);">
                                <i class="fas fa-search"></i>
                            </div>
                            <div class="search-suggestion-text">
                                <span class="title">${isAr ? 'البحث عن تلميذ' : 'Search for Student'}</span>
                                <span class="hint">${isAr ? 'اكتب اسم التلميذ للبحث' : 'Type student name to search'}</span>
                            </div>
                        </a>
                        <a href="${this.baseUrl}leaves?action=add" class="search-suggestion-item">
                            <div class="search-suggestion-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                                <i class="fas fa-calendar-minus"></i>
                            </div>
                            <div class="search-suggestion-text">
                                <span class="title">${isAr ? 'تسجيل إجازة تلميذ' : 'Register Student Leave'}</span>
                                <span class="hint">${isAr ? 'إضافة إجازة جديدة' : 'Add new leave'}</span>
                            </div>
                        </a>
                    </div>
                </div>
            `,

            'teachers': `
                <div class="search-initial">
                    <div class="search-recent-title">
                        <i class="fas fa-chalkboard-teacher"></i> ${isAr ? 'إجراءات المعلمين' : 'Teacher Actions'}
                    </div>
                    <div class="search-suggestions-list">
                        <a href="${this.baseUrl}teachers?action=add" class="search-suggestion-item">
                            <div class="search-suggestion-icon" style="background: linear-gradient(135deg, #6366f1, #4f46e5);">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div class="search-suggestion-text">
                                <span class="title">${isAr ? 'إضافة معلم جديد' : 'Add New Teacher'}</span>
                                <span class="hint">${isAr ? 'إنشاء ملف معلم' : 'Create teacher profile'}</span>
                            </div>
                        </a>
                        <a href="${this.baseUrl}teachers" class="search-suggestion-item">
                            <div class="search-suggestion-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                                <i class="fas fa-search"></i>
                            </div>
                            <div class="search-suggestion-text">
                                <span class="title">${isAr ? 'البحث عن معلم' : 'Search for Teacher'}</span>
                                <span class="hint">${isAr ? 'اكتب اسم المعلم للبحث' : 'Type teacher name to search'}</span>
                            </div>
                        </a>
                        <a href="${this.baseUrl}teacher_assignments" class="search-suggestion-item">
                            <div class="search-suggestion-icon" style="background: linear-gradient(135deg, #f97316, #ea580c);">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="search-suggestion-text">
                                <span class="title">${isAr ? 'توزيع المواد والصفوف' : 'Assign Subjects & Classes'}</span>
                                <span class="hint">${isAr ? 'تعيين المواد للمعلمين' : 'Assign subjects to teachers'}</span>
                            </div>
                        </a>
                    </div>
                </div>
            `,

            'pages': `
                <div class="search-initial">
                    <div class="search-recent-title">
                        <i class="fas fa-file-alt"></i> ${isAr ? 'صفحات مهمة' : 'Important Pages'}
                    </div>
                    <div class="search-shortcuts">
                        <a href="${this.baseUrl}dashboard" class="search-shortcut-item">
                            <div class="search-shortcut-icon" style="background: #3b82f6; color: white;">
                                <i class="fas fa-home"></i>
                            </div>
                            <span>${isAr ? 'الرئيسية' : 'Dashboard'}</span>
                        </a>
                        ${this.userRole !== 'student' ? `
                        <a href="${this.baseUrl}attendance" class="search-shortcut-item">
                            <div class="search-shortcut-icon" style="background: #10b981; color: white;">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <span>${isAr ? 'الحضور' : 'Attendance'}</span>
                        </a>
                        <a href="${this.baseUrl}grades" class="search-shortcut-item">
                            <div class="search-shortcut-icon" style="background: #8b5cf6; color: white;">
                                <i class="fas fa-edit"></i>
                            </div>
                            <span>${isAr ? 'الدرجات' : 'Grades'}</span>
                        </a>
                        <a href="${this.baseUrl}reports" class="search-shortcut-item">
                            <div class="search-shortcut-icon" style="background: #ec4899; color: white;">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <span>${isAr ? 'التقارير' : 'Reports'}</span>
                        </a>
                        ` : `
                        <a href="${this.baseUrl}student_attendance" class="search-shortcut-item">
                            <div class="search-shortcut-icon" style="background: #10b981; color: white;">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <span>${isAr ? 'سجل حضوري' : 'My Attendance'}</span>
                        </a>
                        <a href="${this.baseUrl}grades_report" class="search-shortcut-item">
                            <div class="search-shortcut-icon" style="background: #8b5cf6; color: white;">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <span>${isAr ? 'درجاتي' : 'My Grades'}</span>
                        </a>
                        <a href="${this.baseUrl}student_profile" class="search-shortcut-item">
                            <div class="search-shortcut-icon" style="background: #ec4899; color: white;">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <span>${isAr ? 'بطاقتي' : 'My Profile'}</span>
                        </a>
                        `}
                        <a href="${this.baseUrl}schedule" class="search-shortcut-item">
                            <div class="search-shortcut-icon" style="background: #06b6d4; color: white;">
                                <i class="fas fa-table"></i>
                            </div>
                            <span>${isAr ? 'الجدول' : 'Schedule'}</span>
                        </a>
                        ${(this.userRole === 'admin' || this.userRole === 'assistant') ? `
                        <a href="${this.baseUrl}backup" class="search-shortcut-item">
                            <div class="search-shortcut-icon" style="background: #f97316; color: white;">
                                <i class="fas fa-database"></i>
                            </div>
                            <span>${isAr ? 'النسخ الاحتياطي' : 'Backup'}</span>
                        </a>
                        ` : ''}
                    </div>
                </div>
            `
        };

        this.resultsContainer.innerHTML = suggestions[filter] || suggestions['all'];
    }

    /**
     * اقتراحات الإجراءات الشائعة
     */
    getActionSuggestions(isAr) {
        const actions = [
            { url: 'students?action=add', icon: 'fa-user-plus', color: '#10b981', title: isAr ? 'إضافة تلميذ جديد' : 'Add New Student', hint: isAr ? 'إنشاء بطاقة مدرسية' : 'Create student card' },
            { url: 'attendance', icon: 'fa-user-check', color: '#3b82f6', title: isAr ? 'تسجيل حضور اليوم' : 'Record Attendance', hint: isAr ? 'تسجيل الحضور والغياب' : 'Record daily attendance' },
            { url: 'grades', icon: 'fa-edit', color: '#8b5cf6', title: isAr ? 'رصد الدرجات' : 'Enter Grades', hint: isAr ? 'رصد درجات الطلاب' : 'Enter student grades' },
            { url: 'leaves?action=add', icon: 'fa-calendar-minus', color: '#f59e0b', title: isAr ? 'تسجيل إجازة' : 'Register Leave', hint: isAr ? 'إضافة إجازة جديدة' : 'Add new leave' },
            { url: 'teachers?action=add', icon: 'fa-user-tie', color: '#6366f1', title: isAr ? 'إضافة معلم جديد' : 'Add New Teacher', hint: isAr ? 'إنشاء ملف معلم' : 'Create teacher profile' },
            { url: 'backup', icon: 'fa-database', color: '#ef4444', title: isAr ? 'نسخة احتياطية' : 'Create Backup', hint: isAr ? 'حفظ البيانات' : 'Backup database' },
        ];

        return actions.map(a => `
            <a href="${this.baseUrl}${a.url}" class="search-suggestion-item">
                <div class="search-suggestion-icon" style="background: linear-gradient(135deg, ${a.color}, ${this.darkenColor(a.color)});">
                    <i class="fas ${a.icon}"></i>
                </div>
                <div class="search-suggestion-text">
                    <span class="title">${a.title}</span>
                    <span class="hint">${a.hint}</span>
                </div>
            </a>
        `).join('');
    }

    /**
     * تعتيم اللون
     */
    darkenColor(hex) {
        const num = parseInt(hex.replace('#', ''), 16);
        const amt = -30;
        const R = Math.max((num >> 16) + amt, 0);
        const G = Math.max(((num >> 8) & 0x00FF) + amt, 0);
        const B = Math.max((num & 0x0000FF) + amt, 0);
        return '#' + (0x1000000 + R * 0x10000 + G * 0x100 + B).toString(16).slice(1);
    }

    /**
     * ربط الأحداث
     */
    bindEvents() {
        // إغلاق عند النقر على الخلفية
        this.overlay.addEventListener('click', (e) => {
            if (e.target === this.overlay) {
                this.close();
            }
        });

        // زر الإغلاق
        document.getElementById('searchCloseBtn').addEventListener('click', () => this.close());

        // البحث عند الكتابة
        this.input.addEventListener('input', (e) => {
            this.handleSearch(e.target.value);
        });

        // التنقل بلوحة المفاتيح
        this.input.addEventListener('keydown', (e) => this.handleKeydown(e));

        // فلاتر البحث
        document.querySelectorAll('.search-filter-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.search-filter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                this.currentFilter = btn.dataset.filter;

                // إعادة البحث إذا كان هناك نص
                if (this.input.value.trim()) {
                    this.handleSearch(this.input.value);
                } else {
                    // عرض اقتراحات حسب الفلتر المختار
                    this.input.focus();
                    this.input.placeholder = this.getFilterPlaceholder(btn.dataset.filter);
                    this.showFilterSuggestions(btn.dataset.filter);
                }
            });
        });

        // روابط الوصول السريع
        this.resultsContainer.addEventListener('click', (e) => {
            const shortcut = e.target.closest('.search-shortcut-item');
            if (shortcut) {
                this.close();
            }
        });
    }

    /**
     * اختصار لوحة المفاتيح (Ctrl+K)
     */
    setupKeyboardShortcut() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+K أو Cmd+K
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                this.open();
            }

            // ESC للإغلاق
            if (e.key === 'Escape' && this.overlay.classList.contains('active')) {
                this.close();
            }
        });
    }

    /**
     * فتح نافذة البحث
     */
    open() {
        this.overlay.classList.add('active');
        document.body.style.overflow = 'hidden';

        // تركيز على حقل البحث
        setTimeout(() => {
            this.input.focus();
        }, 100);
    }

    /**
     * إغلاق نافذة البحث
     */
    close() {
        this.overlay.classList.remove('active');
        document.body.style.overflow = '';
        this.input.value = '';
        this.selectedIndex = -1;
        this.results = [];

        // إعادة المحتوى الأولي
        const lang = document.documentElement.lang || 'ar';
        this.resultsContainer.innerHTML = this.getInitialContent(lang === 'ar');
    }

    /**
     * معالجة البحث
     */
    handleSearch(query) {
        clearTimeout(this.debounceTimer);

        query = query.trim();

        if (query.length < 2) {
            const lang = document.documentElement.lang || 'ar';
            this.resultsContainer.innerHTML = this.getInitialContent(lang === 'ar');
            return;
        }

        // إظهار loading
        this.setLoading(true);

        // تأخير للكتابة السلسة
        this.debounceTimer = setTimeout(() => {
            this.performSearch(query);
        }, 300);
    }

    /**
     * تنفيذ البحث
     */
    async performSearch(query) {
        try {
            const formData = new FormData();
            formData.append('query', query);
            formData.append('filter', this.currentFilter);
            formData.append('csrf_token', this.csrfToken);

            const response = await fetch(`${this.baseUrl}api.php?module=search&action=global`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.results = data.data;
                this.renderResults(query);
            } else {
                this.showError(data.message || 'حدث خطأ');
            }
        } catch (error) {
            console.error('Search error:', error);
            this.showError('حدث خطأ في الاتصال');
        } finally {
            this.setLoading(false);
        }
    }

    /**
     * عرض النتائج
     */
    renderResults(query) {
        const lang = document.documentElement.lang || 'ar';
        const isAr = lang === 'ar';

        if (!this.results || Object.keys(this.results).length === 0 ||
            ((!this.results.students || this.results.students.length === 0) &&
                (!this.results.teachers || this.results.teachers.length === 0) &&
                (!this.results.pages || this.results.pages.length === 0) &&
                (!this.results.actions || this.results.actions.length === 0))) {
            this.resultsContainer.innerHTML = `
                <div class="search-no-results">
                    <div class="search-no-results-icon">🔍</div>
                    <div class="search-no-results-title">${isAr ? 'لا توجد نتائج' : 'No results found'}</div>
                    <div class="search-no-results-text">${isAr ? 'جرب كلمات بحث مختلفة' : 'Try different search terms'}</div>
                </div>
            `;
            return;
        }

        let html = '';
        let itemIndex = 0;

        // الطلاب
        if (this.results.students && this.results.students.length > 0) {
            html += `
                <div class="search-result-group">
                    <div class="search-result-group-title">
                        <i class="fas fa-user-graduate"></i>
                        ${isAr ? 'الطلاب' : 'Students'}
                        <span class="count">${this.results.students.length}</span>
                    </div>
                    ${this.results.students.map(student => `
                        <a href="${this.baseUrl}students?id=${student.id}" 
                           class="search-result-item" 
                           data-index="${itemIndex++}">
                            <div class="search-result-icon student">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="search-result-info">
                                <div class="search-result-title">${this.highlightMatch(student.full_name, query)}</div>
                                <div class="search-result-subtitle">
                                    ${student.class_name || ''} ${student.section || ''} 
                                    ${student.student_number ? `• ${student.student_number}` : ''}
                                </div>
                            </div>
                            <i class="fas fa-arrow-left search-result-action"></i>
                        </a>
                    `).join('')}
                </div>
            `;
        }

        // المعلمين
        if (this.results.teachers && this.results.teachers.length > 0) {
            html += `
                <div class="search-result-group">
                    <div class="search-result-group-title">
                        <i class="fas fa-chalkboard-teacher"></i>
                        ${isAr ? 'المعلمين' : 'Teachers'}
                        <span class="count">${this.results.teachers.length}</span>
                    </div>
                    ${this.results.teachers.map(teacher => `
                        <a href="${this.baseUrl}teachers?id=${teacher.id}" 
                           class="search-result-item" 
                           data-index="${itemIndex++}">
                            <div class="search-result-icon teacher">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <div class="search-result-info">
                                <div class="search-result-title">${this.highlightMatch(teacher.full_name, query)}</div>
                                <div class="search-result-subtitle">
                                    ${teacher.specialization || ''} 
                                    ${teacher.phone ? `• ${teacher.phone}` : ''}
                                </div>
                            </div>
                            <i class="fas fa-arrow-left search-result-action"></i>
                        </a>
                    `).join('')}
                </div>
            `;
        }

        // الصفحات
        if (this.results.pages && this.results.pages.length > 0) {
            html += `
                <div class="search-result-group">
                    <div class="search-result-group-title">
                        <i class="fas fa-file-alt"></i>
                        ${isAr ? 'الصفحات' : 'Pages'}
                        <span class="count">${this.results.pages.length}</span>
                    </div>
                    ${this.results.pages.map(page => `
                        <a href="${this.baseUrl}${page.url}" 
                           class="search-result-item" 
                           data-index="${itemIndex++}">
                            <div class="search-result-icon page">
                                <i class="fas ${page.icon || 'fa-file-alt'}"></i>
                            </div>
                            <div class="search-result-info">
                                <div class="search-result-title">${this.highlightMatch(page.title, query)}</div>
                                <div class="search-result-subtitle">${page.description || ''}</div>
                            </div>
                            <i class="fas fa-arrow-left search-result-action"></i>
                        </a>
                    `).join('')}
                </div>
            `;
        }

        // الإجراءات السريعة
        if (this.results.actions && this.results.actions.length > 0) {
            html += `
                <div class="search-result-group">
                    <div class="search-result-group-title">
                        <i class="fas fa-bolt"></i>
                        ${isAr ? 'إجراءات سريعة' : 'Quick Actions'}
                        <span class="count">${this.results.actions.length}</span>
                    </div>
                    ${this.results.actions.map(action => `
                        <a href="${this.baseUrl}${action.url}" 
                           class="search-result-item" 
                           data-index="${itemIndex++}">
                            <div class="search-result-icon action">
                                <i class="fas ${action.icon || 'fa-bolt'}"></i>
                            </div>
                            <div class="search-result-info">
                                <div class="search-result-title">${this.highlightMatch(action.title, query)}</div>
                                <div class="search-result-subtitle">${action.category || ''}</div>
                            </div>
                            <i class="fas fa-arrow-left search-result-action"></i>
                        </a>
                    `).join('')}
                </div>
            `;
        }

        this.resultsContainer.innerHTML = html;
        this.selectedIndex = -1;

        // إضافة أحداث النقر
        this.resultsContainer.querySelectorAll('.search-result-item').forEach(item => {
            item.addEventListener('click', () => {
                this.close();
            });
        });
    }

    /**
     * الحصول على نص placeholder حسب الفلتر
     */
    getFilterPlaceholder(filter) {
        const lang = document.documentElement.lang || 'ar';
        const isAr = lang === 'ar';

        const placeholders = {
            'all': isAr ? 'ابحث عن طالب، معلم، صفحة...' : 'Search for student, teacher, page...',
            'actions': isAr ? 'ابحث عن إجراء (إضافة، تسجيل...)' : 'Search for action (add, record...)',
            'students': isAr ? 'ابحث عن طالب...' : 'Search for student...',
            'teachers': isAr ? 'ابحث عن معلم...' : 'Search for teacher...',
            'pages': isAr ? 'ابحث عن صفحة...' : 'Search for page...'
        };

        return placeholders[filter] || placeholders['all'];
    }

    /**
     * تمييز نص البحث في النتائج
     */
    highlightMatch(text, query) {
        if (!text || !query) return text || '';

        const regex = new RegExp(`(${this.escapeRegex(query)})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }

    /**
     * تهريب الأحرف الخاصة في regex
     */
    escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    /**
     * معالجة التنقل بلوحة المفاتيح
     */
    handleKeydown(e) {
        // البحث عن عناصر النتائج أو الوصول السريع أو الاقتراحات
        let items = this.resultsContainer.querySelectorAll('.search-result-item');

        // إذا لم توجد نتائج، استخدم روابط الوصول السريع
        if (items.length === 0) {
            items = this.resultsContainer.querySelectorAll('.search-shortcut-item');
        }

        // إذا لم توجد، استخدم الاقتراحات
        if (items.length === 0) {
            items = this.resultsContainer.querySelectorAll('.search-suggestion-item');
        }

        if (items.length === 0) return;

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.selectedIndex = Math.min(this.selectedIndex + 1, items.length - 1);
                this.updateSelection(items);
                break;

            case 'ArrowUp':
                e.preventDefault();
                this.selectedIndex = Math.max(this.selectedIndex - 1, 0);
                this.updateSelection(items);
                break;

            case 'Enter':
                e.preventDefault();
                if (this.selectedIndex >= 0 && items[this.selectedIndex]) {
                    items[this.selectedIndex].click();
                }
                break;
        }
    }

    /**
     * تحديث التحديد المرئي
     */
    updateSelection(items) {
        items.forEach((item, index) => {
            item.classList.toggle('selected', index === this.selectedIndex);
        });

        // Scroll إلى العنصر المحدد
        if (items[this.selectedIndex]) {
            items[this.selectedIndex].scrollIntoView({
                block: 'nearest',
                behavior: 'smooth'
            });
        }
    }

    /**
     * حالة التحميل
     */
    setLoading(loading) {
        this.isLoading = loading;
        this.inputWrapper.classList.toggle('loading', loading);
    }

    /**
     * عرض خطأ
     */
    showError(message) {
        const lang = document.documentElement.lang || 'ar';
        const isAr = lang === 'ar';

        this.resultsContainer.innerHTML = `
            <div class="search-no-results">
                <div class="search-no-results-icon">⚠️</div>
                <div class="search-no-results-title">${isAr ? 'حدث خطأ' : 'Error occurred'}</div>
                <div class="search-no-results-text">${message}</div>
            </div>
        `;
    }
}

// تهيئة البحث عند تحميل الصفحة
let globalSearch = null;

document.addEventListener('DOMContentLoaded', () => {
    globalSearch = new GlobalSearch();

    // ربط زر البحث في الـ header
    const searchTrigger = document.getElementById('searchTrigger');
    if (searchTrigger) {
        searchTrigger.addEventListener('click', () => {
            globalSearch.open();
        });
    }
});

// تصدير للاستخدام الخارجي
window.GlobalSearch = GlobalSearch;
window.openGlobalSearch = () => globalSearch?.open();
