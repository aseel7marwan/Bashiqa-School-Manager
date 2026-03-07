            </div>
            
            <!-- تذييل الصفحة -->
            <footer class="page-footer">
                <div class="footer-content">
                    <span class="footer-logo">🏫</span>
                    <span class="footer-text"><?= __('مدرسة بعشيقة الابتدائية للبنين') ?></span>
                    <span class="footer-divider">|</span>
                    <span class="footer-year"><?= date('Y') ?></span>
                    <span class="footer-divider">|</span>
                    <a href="<?= getBaseUrl() ?>privacy" class="footer-link" style="color: inherit; text-decoration: none; opacity: 0.8;">🔒 <?= __('الخصوصية') ?></a>
                </div>
            </footer>
        </main>
    </div>
    
    <!-- أنماط اتجاه الصفحة للإنجليزية -->
    <style>
        html[dir="ltr"] { text-align: left; }
        html[dir="ltr"] th, html[dir="ltr"] td { text-align: left; }
        html[dir="ltr"] .form-group label { text-align: left; }
        html[dir="ltr"] .user-info { direction: ltr; }
        html[dir="ltr"] .sidebar-nav { direction: ltr; }
        html[dir="ltr"] .topbar { direction: ltr; }
        html[dir="ltr"] .lang-toggle.active { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
    </style>
    
    <script>
    // تبديل اللغة - توجيه لمعالج PHP
    var baseUrl = '<?= getBaseUrl() ?>';
    function toggleLanguage() {
        window.location.href = baseUrl + 'controllers/language_handler.php';
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // نظام الترجمة التلقائي الشامل - عند اختيار اللغة الإنجليزية
    // ═══════════════════════════════════════════════════════════════════════════
    <?php if (getLang() === 'en'): ?>
    (function() {
        // قاموس الترجمة الشامل - مرتب حسب الطول (الأطول أولاً)
        var translations = <?= json_encode($GLOBALS['translations'], JSON_UNESCAPED_UNICODE) ?>;
        
        // ترتيب الكلمات حسب الطول (الأطول أولاً لتجنب الترجمات الجزئية)
        var sortedKeys = Object.keys(translations).sort(function(a, b) {
            return b.length - a.length;
        });
        
        // تخزين العناصر المترجمة لتجنب التكرار
        var translatedNodes = new WeakSet();
        
        // قائمة الكلمات القصيرة التي يجب تطابقها ككلمات كاملة فقط
        var shortWords = ['لا', 'نعم', 'أم', 'أب', 'أخ', 'عم', 'خال', 'جد', 'آخر', 'كل', 'أو', 'و', 'من', 'إلى', 'في', 'هل', 'ما', 'أي'];
        
        // ترجمة نص واحد
        function translateText(text) {
            if (!text || !text.trim()) return text;
            var result = text;
            
            for (var i = 0; i < sortedKeys.length; i++) {
                var ar = sortedKeys[i];
                
                // تخطي الكلمات القصيرة جداً (أقل من 3 حروف) إذا كانت جزءاً من كلمة أكبر
                if (ar.length <= 3 && shortWords.includes(ar)) {
                    // استخدام تعبير نمطي للتحقق من أن الكلمة قائمة بذاتها
                    // تحقق من وجود حدود كلمة (مسافة أو بداية/نهاية أو علامات ترقيم)
                    var regex = new RegExp('(^|[\\s:،,\\.!\\?\\)\\(]|$)' + ar.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(?=[\\s:،,\\.!\\?\\)\\(]|$)', 'g');
                    if (regex.test(result)) {
                        result = result.replace(regex, function(match, prefix) {
                            return prefix + translations[ar];
                        });
                    }
                } else {
                    // للكلمات الطويلة، استبدال عادي
                    if (result.includes(ar)) {
                        result = result.split(ar).join(translations[ar]);
                    }
                }
            }
            return result;
        }
        
        // ترجمة عنصر DOM ومحتوياته
        function translateElement(root) {
            if (!root) return;
            
            // ترجمة النصوص داخل العنصر
            var walker = document.createTreeWalker(
                root,
                NodeFilter.SHOW_TEXT,
                {
                    acceptNode: function(node) {
                        // تجاهل السكربتات والستايلات
                        var parent = node.parentElement;
                        if (parent && (parent.tagName === 'SCRIPT' || parent.tagName === 'STYLE' || parent.tagName === 'NOSCRIPT')) {
                            return NodeFilter.FILTER_REJECT;
                        }
                        return NodeFilter.FILTER_ACCEPT;
                    }
                },
                false
            );
            
            var nodes = [];
            while (walker.nextNode()) {
                if (!translatedNodes.has(walker.currentNode)) {
                    nodes.push(walker.currentNode);
                }
            }
            
            nodes.forEach(function(node) {
                var text = node.textContent;
                if (!text || !text.trim()) return;
                var translated = translateText(text);
                if (translated !== text) {
                    node.textContent = translated;
                    translatedNodes.add(node);
                }
            });
            
            // ترجمة العناوين (title attributes)
            root.querySelectorAll('[title]').forEach(function(el) {
                var t = el.getAttribute('title');
                if (t) {
                    var translated = translateText(t);
                    if (translated !== t) el.setAttribute('title', translated);
                }
            });
            
            // ترجمة الـ placeholders
            root.querySelectorAll('[placeholder]').forEach(function(el) {
                var p = el.getAttribute('placeholder');
                if (p) {
                    var translated = translateText(p);
                    if (translated !== p) el.setAttribute('placeholder', translated);
                }
            });
            
            // ترجمة الـ options في القوائم المنسدلة
            root.querySelectorAll('option').forEach(function(opt) {
                var text = opt.textContent;
                var translated = translateText(text);
                if (translated !== text) opt.textContent = translated;
            });
            
            // ترجمة الأزرار
            root.querySelectorAll('button, .btn, [type="submit"], [type="button"]').forEach(function(btn) {
                var text = btn.textContent;
                var translated = translateText(text);
                if (translated !== text) btn.textContent = translated;
            });
            
            // ترجمة الروابط
            root.querySelectorAll('a').forEach(function(link) {
                // ترجمة النص فقط إذا لم يحتوي على عناصر أخرى
                if (link.childElementCount === 0) {
                    var text = link.textContent;
                    var translated = translateText(text);
                    if (translated !== text) link.textContent = translated;
                }
            });
            
            // ترجمة الـ labels
            root.querySelectorAll('label, .profile-label, .form-section-title, th, .card-header h3, .page-header h1, .page-header p').forEach(function(el) {
                if (el.childElementCount === 0) {
                    var text = el.textContent;
                    var translated = translateText(text);
                    if (translated !== text) el.textContent = translated;
                }
            });
        }
        
        // ترجمة كل الصفحة
        function translatePage() {
            // 1. ترجمة عنوان الصفحة
            document.title = translateText(document.title);
            
            // 2. ترجمة القائمة الجانبية
            var sidebar = document.querySelector('.sidebar');
            if (sidebar) translateElement(sidebar);
            
            // 3. ترجمة الشريط العلوي
            var topbar = document.querySelector('.topbar');
            if (topbar) translateElement(topbar);
            
            // 4. ترجمة المحتوى الرئيسي
            var pageContent = document.querySelector('.page-content');
            if (pageContent) translateElement(pageContent);
            
            // 5. ترجمة التذييل
            var footer = document.querySelector('.page-footer');
            if (footer) translateElement(footer);
            
            // 6. ترجمة النوافذ المنبثقة (modals)
            document.querySelectorAll('.modal, [class*="modal"], .teacher-modal, .student-modal').forEach(function(modal) {
                translateElement(modal);
            });
        }
        
        // مراقب التغييرات لترجمة المحتوى المضاف ديناميكياً
        function setupMutationObserver() {
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            translateElement(node);
                        } else if (node.nodeType === Node.TEXT_NODE && !translatedNodes.has(node)) {
                            var text = node.textContent;
                            if (text && text.trim()) {
                                var translated = translateText(text);
                                if (translated !== text) {
                                    node.textContent = translated;
                                    translatedNodes.add(node);
                                }
                            }
                        }
                    });
                });
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
        
        // تنفيذ الترجمة
        function init() {
            translatePage();
            setupMutationObserver();
        }
        
        // تنفيذ عند تحميل الصفحة
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
        
        // إعادة الترجمة بعد تحميل كامل الصفحة (للصور والموارد الخارجية)
        window.addEventListener('load', function() {
            setTimeout(translatePage, 100);
        });
    })();
    <?php endif; ?>
    </script>
    
    <script src="<?= getBaseUrl() ?>assets/js/main.js?v=20241225arabic"></script>
    <script src="<?= getBaseUrl() ?>assets/js/ajax.js?v=20241226"></script>
    <script src="<?= getBaseUrl() ?>assets/js/search.js?v=20260109"></script>
    <script src="<?= getBaseUrl() ?>assets/js/pjax.js?v=20260111"></script>
    <!-- Flatpickr Arabic Date Picker -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        flatpickr('input[type="date"]', {
            locale: '<?= getLang() ?>',
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'j / n / Y',
            allowInput: true,
            disableMobile: true
        });
    });
    </script>
    
    <!-- تم إزالة كود إعادة التحميل التلقائي لتحسين أداء القائمة الجانبية -->
    
    <!-- حفظ موضع التمرير للقائمة الجانبية -->
    <script>
    (function() {
        var nav = document.getElementById('sidebarNav');
        if (!nav) return;
        
        var pos = localStorage.getItem('sidebarScrollPos');
        if (pos) nav.scrollTop = parseInt(pos);
        
        nav.addEventListener('scroll', function() {
            localStorage.setItem('sidebarScrollPos', this.scrollTop);
        }, { passive: true });
        
        document.querySelectorAll('#sidebarNav a').forEach(function(link) {
            link.addEventListener('click', function() {
                localStorage.setItem('sidebarScrollPos', nav.scrollTop);
            });
        });
    })();
    </script>
    
    <?php if (isset($extraScripts)): ?>
        <?php foreach ($extraScripts as $script): ?>
            <script src="<?= $script ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
