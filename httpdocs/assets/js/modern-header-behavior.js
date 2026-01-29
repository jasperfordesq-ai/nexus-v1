/**
 * Modern Header Behavior JavaScript
 * Extracted from header.php for better maintainability
 *
 * Features:
 * - Scroll-aware header behavior
 * - Active navigation link detection
 * - Drawer toggle functions
 * - Dark/Light mode switcher
 * - Collapsible search functionality
 * - Mobile dropdown handling
 * - Notification drawer controller
 */

// --- SCROLL-AWARE HEADER BEHAVIOR ---
(function() {
    let lastScroll = 0;
    let ticking = false;

    function updateHeaderOnScroll() {
        const scrollY = window.scrollY || window.pageYOffset;
        const utilityBar = document.querySelector('.nexus-utility-bar');
        const navbar = document.querySelector('.nexus-navbar');

        if (scrollY > 60) {
            utilityBar?.classList.add('scrolled');
            navbar?.classList.add('scrolled');
        } else {
            utilityBar?.classList.remove('scrolled');
            navbar?.classList.remove('scrolled');
        }

        lastScroll = scrollY;
        ticking = false;
    }

    window.addEventListener('scroll', function() {
        if (!ticking) {
            window.requestAnimationFrame(updateHeaderOnScroll);
            ticking = true;
        }
    }, { passive: true });

    // Initial check
    updateHeaderOnScroll();
})();

// --- ACTIVE NAV LINK DETECTION ---
(function() {
    try {
        const path = window.location.pathname;
        if (!path || typeof path !== 'string') {
            console.warn('[NAV] Invalid pathname:', path);
            return;
        }

        const segments = path.split('/').filter(s => s && s.length > 0);

        // Get the relevant segment (skip tenant base if present)
        // Find first segment that matches a known nav item
        const navMatches = ['listings', 'groups', 'community-groups', 'members', 'volunteering', 'events', 'polls', 'goals', 'resources', 'news'];
        let activeSegment = '/'; // default to home

        for (const seg of segments) {
            if (seg && navMatches.includes(seg)) {
                activeSegment = seg;
                break;
            }
        }

        // If path ends with / or is just base path, it's home
        const isHome = segments.length === 0 ||
                       path.endsWith('/home') ||
                       (segments.length === 1 && !navMatches.includes(segments[0]));

        if (isHome) {
            activeSegment = '/';
        }

        // Apply active class to matching nav links
        document.querySelectorAll('.nav-link[data-nav-match]').forEach(link => {
            const match = link.getAttribute('data-nav-match');
            if (match && (match === activeSegment || (match === '/' && activeSegment === '/'))) {
                link.classList.add('active');
                console.warn('[NAV] Active class added to:', match, link);
            } else {
                link.classList.remove('active');
            }
        });
        console.warn('[NAV] Active segment:', activeSegment, 'from path:', window.location.pathname);
    } catch (e) {
        console.error('[NAV] Error in active link detection:', e);
    }
})();

// Mode Switcher (Light/Dark) with smooth transitions
function toggleMode() {
    const current = document.documentElement.getAttribute('data-theme');
    const next = current === 'dark' ? 'light' : 'dark';
    const html = document.documentElement;

    // Add transitioning class for smooth color transitions
    html.classList.add('theme-transitioning');

    // Apply the new theme
    html.setAttribute('data-theme', next);
    document.cookie = "nexus_mode=" + next + ";path=/;max-age=31536000";

    // Update color-scheme meta for native elements
    const colorSchemeMeta = document.querySelector('meta[name="color-scheme"]');
    if (colorSchemeMeta) {
        colorSchemeMeta.content = next === 'dark' ? 'dark' : 'light';
    }

    // Remove transitioning class after animation completes
    setTimeout(function() {
        html.classList.remove('theme-transitioning');
    }, 350);

    // Update header mode switcher
    const modeIconContainer = document.getElementById('modeIconContainer');
    const modeIcon = document.getElementById('modeIcon');
    const modeLabel = document.getElementById('modeLabel');

    if (modeIconContainer) {
        modeIconContainer.className = 'mode-icon-container ' + (next === 'dark' ? 'dark-mode' : 'light-mode');
    }
    if (modeIcon) {
        modeIcon.className = 'fa-solid ' + (next === 'dark' ? 'fa-moon' : 'fa-sun') + ' mode-icon';
    }
    if (modeLabel) {
        modeLabel.textContent = next === 'dark' ? 'Dark Mode' : 'Light Mode';
    }

    // Update mode switcher title/tooltip
    document.querySelectorAll('.mode-switcher').forEach(el => {
        el.title = next === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode';
    });

    // Update mobile drawer mode button
    document.querySelectorAll('.mode-toggle-icon').forEach(el => {
        el.className = 'fa-solid ' + (next === 'dark' ? 'fa-moon' : 'fa-sun') + ' mode-toggle-icon';
    });
    document.querySelectorAll('.mode-drawer-icon').forEach(el => {
        el.className = 'mode-drawer-icon ' + (next === 'dark' ? 'dark-mode' : 'light-mode');
    });
    document.querySelectorAll('.mode-toggle-text').forEach(el => {
        el.textContent = next === 'dark' ? 'Switch to Light' : 'Switch to Dark';
    });

    // Legacy support for old theme toggle elements
    document.querySelectorAll('.theme-toggle-icon').forEach(el => {
        el.className = 'fa-solid ' + (next === 'dark' ? 'fa-sun' : 'fa-moon') + ' theme-toggle-icon';
    });
    document.querySelectorAll('.theme-toggle-text').forEach(el => {
        el.textContent = next === 'dark' ? 'Light Mode' : 'Dark Mode';
    });
    document.querySelectorAll('.theme-toggle-switch').forEach(sw => {
        sw.classList.toggle('active', next === 'dark');
    });

    // Dispatch event for any components that need to react
    window.dispatchEvent(new CustomEvent('modechange', { detail: { mode: next } }));
    window.dispatchEvent(new CustomEvent('themechange', { detail: { theme: next } })); // Legacy

    // Haptic feedback on mobile
    if (navigator.vibrate) {
        navigator.vibrate(10);
    }
}

// Backwards compatibility alias
function toggleTheme() { toggleMode(); }

// Collapsible Search Toggle
document.addEventListener('DOMContentLoaded', function() {
    const searchContainer = document.querySelector('.collapsible-search-container');
    const searchToggleBtn = document.getElementById('searchToggleBtn');
    const searchCloseBtn = document.getElementById('searchCloseBtn');
    const searchInput = document.getElementById('searchInput');
    const collapsibleSearch = document.getElementById('collapsibleSearch');

    if (searchToggleBtn && searchContainer) {
        // Open search
        searchToggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            searchContainer.classList.add('search-expanded');
            document.body.classList.add('search-expanded');
            searchToggleBtn.setAttribute('aria-expanded', 'true');
            // Focus the input after animation
            setTimeout(() => {
                if (searchInput) searchInput.focus();
            }, 150);
        });

        // Close search
        if (searchCloseBtn) {
            searchCloseBtn.addEventListener('click', function(e) {
                e.preventDefault();
                searchContainer.classList.remove('search-expanded');
                document.body.classList.remove('search-expanded');
                searchToggleBtn.setAttribute('aria-expanded', 'false');
                if (searchInput) searchInput.value = '';
            });
        }

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && searchContainer.classList.contains('search-expanded')) {
                searchContainer.classList.remove('search-expanded');
                document.body.classList.remove('search-expanded');
                searchToggleBtn.setAttribute('aria-expanded', 'false');
                if (searchInput) searchInput.value = '';
                searchToggleBtn.focus();
            }
        });

        // Close when clicking outside
        document.addEventListener('click', function(e) {
            if (searchContainer.classList.contains('search-expanded') &&
                !searchContainer.contains(e.target)) {
                searchContainer.classList.remove('search-expanded');
                document.body.classList.remove('search-expanded');
                searchToggleBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }
});

// Mobile utility bar dropdown toggles
if (window.innerWidth <= 1024) {
    document.addEventListener('DOMContentLoaded', function() {
        const utilityBar = document.querySelector('.nexus-utility-bar');
        if (!utilityBar) return;

        const dropdowns = utilityBar.querySelectorAll('.htb-dropdown');

        dropdowns.forEach(dropdown => {
            const button = dropdown.querySelector('button');
            if (!button) return;

            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                // Close all other dropdowns
                dropdowns.forEach(dd => {
                    if (dd !== dropdown) {
                        dd.classList.remove('active');
                    }
                });

                // Toggle current dropdown
                dropdown.classList.toggle('active');
            });
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.nexus-utility-bar')) {
                dropdowns.forEach(dd => dd.classList.remove('active'));
            }
        });

        // Close dropdown when clicking a link inside it
        utilityBar.querySelectorAll('.htb-dropdown-content a').forEach(link => {
            link.addEventListener('click', function() {
                dropdowns.forEach(dd => dd.classList.remove('active'));
            });
        });
    });
}

// Notification Drawer Controller
window.nexusNotifDrawer = {
    drawer: null,
    overlay: null,
    isOpen: false,

    init: function() {
        this.drawer = document.getElementById('notif-drawer');
        this.overlay = document.getElementById('notif-drawer-overlay');

        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }
        });
    },

    open: function() {
        if (!this.drawer) this.init();
        if (!this.drawer) return;

        this.drawer.classList.add('open');
        this.overlay.classList.add('open');
        document.body.classList.add('js-overflow-hidden');
        this.isOpen = true;
    },

    close: function() {
        if (!this.drawer) return;

        this.drawer.classList.remove('open');
        this.overlay.classList.remove('open');
        document.body.classList.remove('js-overflow-hidden');
        this.isOpen = false;
    },

    toggle: function() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }
};

// Initialize drawer on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    window.nexusNotifDrawer.init();
});
