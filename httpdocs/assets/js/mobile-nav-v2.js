/**
 * Modern Mobile Navigation v2 - JavaScript
 * Full-screen, native app-like mobile navigation
 * Inspired by Instagram, TikTok, and iOS design patterns
 */

// Define mobile menu functions early to prevent ReferenceError
// These need to be available immediately for onclick handlers
window.openMobileMenu = function() {
    const menu = document.getElementById('mobileMenu');
    if (menu) {
        menu.classList.add('active');
        document.body.classList.add('mobile-menu-open');
    }
};

window.closeMobileMenu = function() {
    const menu = document.getElementById('mobileMenu');
    if (menu) {
        menu.classList.remove('active');
        document.body.classList.remove('mobile-menu-open');
    }
};

window.openMobileNotifications = function() {
    const sheet = document.getElementById('mobileNotifications');
    if (sheet) {
        sheet.classList.add('active');
        document.body.classList.add('mobile-notifications-open');
    }
};

window.closeMobileNotifications = function() {
    const sheet = document.getElementById('mobileNotifications');
    if (sheet) {
        sheet.classList.remove('active');
        document.body.classList.remove('mobile-notifications-open');
    }
};

// Clean up stuck menu classes on page load
(function() {
    function cleanupMenuClasses() {
        // Only remove classes if menus aren't actually open
        const menuOpen = document.getElementById('mobileMenu')?.classList.contains('active');
        const notifOpen = document.getElementById('mobileNotifications')?.classList.contains('active');

        if (!menuOpen) {
            document.body.classList.remove('mobile-menu-open');
        }
        if (!notifOpen) {
            document.body.classList.remove('mobile-notifications-open');
        }
    }

    // Run on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', cleanupMenuClasses);
    } else {
        cleanupMenuClasses();
    }

    // Run when tab becomes visible
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            cleanupMenuClasses();
        }
    });
})();

// Haptic Feedback Helper
const Haptics = {
    // Check if vibration API is available
    isSupported: () => 'vibrate' in navigator,

    // Light tap feedback (for buttons, toggles)
    light: () => {
        if (Haptics.isSupported()) navigator.vibrate(10);
        // Also try Capacitor Haptics if available
        if (window.Capacitor?.Plugins?.Haptics) {
            window.Capacitor.Plugins.Haptics.impact({ style: 'light' });
        }
    },

    // Medium feedback (for selections, confirmations)
    medium: () => {
        if (Haptics.isSupported()) navigator.vibrate(20);
        if (window.Capacitor?.Plugins?.Haptics) {
            window.Capacitor.Plugins.Haptics.impact({ style: 'medium' });
        }
    },

    // Success feedback
    success: () => {
        if (Haptics.isSupported()) navigator.vibrate([10, 50, 10]);
        if (window.Capacitor?.Plugins?.Haptics) {
            window.Capacitor.Plugins.Haptics.notification({ type: 'success' });
        }
    }
};

// Enhance Mobile Menu Functions with Haptic Feedback
// Functions already defined at top of file, we're just adding haptics here
(function() {
    // Store original functions
    const originalOpenMenu = window.openMobileMenu;
    const originalCloseMenu = window.closeMobileMenu;
    const originalOpenNotif = window.openMobileNotifications;
    const originalCloseNotif = window.closeMobileNotifications;

    // Enhance with haptic feedback
    window.openMobileMenu = function() {
        Haptics.light();
        if (originalOpenMenu) originalOpenMenu();
    };

    window.closeMobileMenu = function() {
        Haptics.light();
        if (originalCloseMenu) originalCloseMenu();
    };

    window.openMobileNotifications = function() {
        Haptics.light();
        if (originalOpenNotif) originalOpenNotif();
    };

    window.closeMobileNotifications = function() {
        Haptics.light();
        if (originalCloseNotif) originalCloseNotif();
    };
})();

window.markAllNotificationsRead = function() {
    Haptics.success();
    if (typeof window.nexusNotifications !== 'undefined' && window.nexusNotifications.markAllRead) {
        window.nexusNotifications.markAllRead();
    }
    // Visual feedback
    document.querySelectorAll('.mobile-notification-item.unread').forEach(item => {
        item.classList.remove('unread');
    });
    document.querySelectorAll('.mobile-tab-badge').forEach(badge => {
        badge.style.display = 'none';
    });
};

// Add haptic feedback to interactive elements when DOM is ready
function initializeHapticFeedback() {
    // Add haptic feedback to tab bar items
    document.querySelectorAll('.mobile-tab-item').forEach(item => {
        item.addEventListener('click', () => Haptics.light());
    });

    // Add haptic feedback to menu items
    document.querySelectorAll('.mobile-menu-item').forEach(item => {
        item.addEventListener('click', () => Haptics.light());
    });
}

// Initialize haptics when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeHapticFeedback);
} else {
    initializeHapticFeedback();
}

// Close menu on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeMobileMenu();
        closeMobileNotifications();
    }
});
