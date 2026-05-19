/* FinTrack — global UI behaviour: sidebar toggle + theme toggle. */
(function () {
    const sidebar = document.getElementById('finSidebar');
    const backdrop = document.getElementById('finSidebarBackdrop');
    const toggleBtn = document.getElementById('sidebarToggle');

    function openSidebar() {
        console.debug('[sidebar] openSidebar()');
        sidebar?.classList.add('show');
        backdrop?.classList.add('show');
        document.documentElement.classList.add('sidebar-open');
    }
    function closeSidebar() {
        console.debug('[sidebar] closeSidebar()');
        sidebar?.classList.remove('show');
        backdrop?.classList.remove('show');
        document.documentElement.classList.remove('sidebar-open');
    }

    toggleBtn?.addEventListener('click', () => {
        console.debug('[sidebar] toggle button clicked, showing?', sidebar?.classList.contains('show'));
        sidebar?.classList.contains('show') ? closeSidebar() : openSidebar();
    });
    backdrop?.addEventListener('click', closeSidebar);

    // Close on Esc
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            console.debug('[sidebar] Escape pressed');
            closeSidebar();
        }
    });

    // When a sidebar nav link is clicked on small screens, close the sidebar
    document.querySelectorAll('.fin-sidebar .nav-link').forEach((el) => {
        el.addEventListener('click', () => {
            console.debug('[sidebar] nav link clicked');
            if (window.innerWidth <= 991.98) closeSidebar();
        });
    });

    // ----- theme toggle -----
    const themeBtn = document.getElementById('themeToggle');
    const html = document.documentElement;

    function applyTheme(theme) {
        html.setAttribute('data-bs-theme', theme);
        document.cookie = 'theme=' + theme + '; max-age=31536000; path=/; samesite=lax';
        if (themeBtn) {
            themeBtn.querySelector('i').className =
                theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        }
    }

    if (themeBtn) {
        applyTheme(html.getAttribute('data-bs-theme') || 'light');
        themeBtn.addEventListener('click', () => {
            const next = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            applyTheme(next);
        });
    }
})();
