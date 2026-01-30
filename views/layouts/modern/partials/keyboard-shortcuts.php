<?php
/**
 * Keyboard Shortcuts for Power Users
 * LEGENDARY FEATURE: Quick access to layout switcher and other features
 *
 * CSS: /httpdocs/assets/css/keyboard-shortcuts-modal.css
 */
?>

<script>
// Keyboard Shortcuts Handler
(function() {
    'use strict';

    const shortcuts = {
        // Ctrl+Shift+L: Open Layout Switcher
        'ctrl+shift+l': function() {
            window.location.href = '<?= \Nexus\Core\TenantContext::getBasePath() ?>/layouts';
        },

        // Ctrl+Shift+K: Toggle Mobile Drawer (if exists)
        'ctrl+shift+k': function() {
            const drawerToggle = document.querySelector('[data-drawer-toggle]') ||
                                 document.querySelector('.nexus-burger-menu');
            if (drawerToggle) {
                drawerToggle.click();
            }
        },

        // Ctrl+Shift+P: Preview Mode (cycle through layouts)
        'ctrl+shift+p': function() {
            const currentLayout = '<?= $_SESSION['nexus_layout'] ?? 'modern' ?>';
            const layouts = ['modern', 'civicone'];
            const currentIndex = layouts.indexOf(currentLayout);
            const nextIndex = (currentIndex + 1) % layouts.length;
            const nextLayout = layouts[nextIndex];

            window.location.href = '<?= \Nexus\Core\TenantContext::getBasePath() ?>/?preview_layout=' + nextLayout;
        },

        // Ctrl+Shift+H: Go Home
        'ctrl+shift+h': function() {
            window.location.href = '<?= \Nexus\Core\TenantContext::getBasePath() ?>/';
        },

        // Ctrl+Shift+/: Show Shortcuts Help
        'ctrl+shift+/': function() {
            showShortcutsHelp();
        }
    };

    // Key press handler
    document.addEventListener('keydown', function(e) {
        // Build shortcut string
        const keys = [];
        if (e.ctrlKey || e.metaKey) keys.push('ctrl');
        if (e.shiftKey) keys.push('shift');
        if (e.altKey) keys.push('alt');

        // Add the actual key (with safety check for undefined e.key)
        if (!e.key) return; // Skip if key is undefined
        const key = e.key.toLowerCase();
        if (key !== 'control' && key !== 'shift' && key !== 'alt' && key !== 'meta') {
            keys.push(key);
        }

        const shortcut = keys.join('+');

        // Check if shortcut exists
        if (shortcuts[shortcut]) {
            e.preventDefault();
            shortcuts[shortcut]();
        }
    });

    // Show shortcuts help modal
    function showShortcutsHelp() {
        // Remove existing modal if any
        const existing = document.getElementById('keyboard-shortcuts-modal');
        if (existing) {
            existing.remove();
        }

        // Create modal
        const modal = document.createElement('div');
        modal.id = 'keyboard-shortcuts-modal';
        modal.className = 'keyboard-shortcuts-modal';

        modal.innerHTML = `
            <div class="keyboard-shortcuts-modal__content">
                <div class="keyboard-shortcuts-modal__header">
                    <h2 class="keyboard-shortcuts-modal__title">
                        <i class="fa-solid fa-keyboard"></i> Keyboard Shortcuts
                    </h2>
                    <button class="keyboard-shortcuts-modal__close" onclick="this.closest('#keyboard-shortcuts-modal').remove()">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>

                <div class="keyboard-shortcuts-modal__description">
                    Power user shortcuts for faster navigation
                </div>

                <div class="keyboard-shortcuts-modal__list">
                    ${createShortcutRow('Ctrl+Shift+L', 'Open Layout Switcher', 'primary')}
                    ${createShortcutRow('Ctrl+Shift+P', 'Preview Next Layout', 'purple')}
                    ${createShortcutRow('Ctrl+Shift+K', 'Toggle Mobile Menu', 'success')}
                    ${createShortcutRow('Ctrl+Shift+H', 'Go to Home', 'blue')}
                    ${createShortcutRow('Ctrl+Shift+/', 'Show This Help', 'warning')}
                </div>

                <div class="keyboard-shortcuts-modal__tip">
                    <div class="keyboard-shortcuts-modal__tip-title">
                        <i class="fa-solid fa-lightbulb"></i> Pro Tip
                    </div>
                    <div class="keyboard-shortcuts-modal__tip-text">
                        On Mac, use Cmd instead of Ctrl for all shortcuts
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Close on click outside
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.remove();
            }
        });

        // Close on Escape
        function closeOnEscape(e) {
            if (e.key === 'Escape') {
                modal.remove();
                document.removeEventListener('keydown', closeOnEscape);
            }
        }
        document.addEventListener('keydown', closeOnEscape);
    }

    function createShortcutRow(keys, description, colorClass) {
        const keyParts = keys.split('+');
        const keyBadges = keyParts.map(key =>
            `<span class="keyboard-shortcuts-modal__key keyboard-shortcuts-modal__key--${colorClass}">${key}</span>`
        ).join('<span class="keyboard-shortcuts-modal__key-separator">+</span>');

        return `
            <div class="keyboard-shortcuts-modal__row">
                <span class="keyboard-shortcuts-modal__row-label">${description}</span>
                <div class="keyboard-shortcuts-modal__keys">${keyBadges}</div>
            </div>
        `;
    }

    console.log('%c🚀 Keyboard Shortcuts Loaded!', 'color: #6366f1; font-weight: bold; font-size: 14px;');
    console.log('%cPress Ctrl+Shift+/ to view all shortcuts', 'color: #6b7280; font-size: 12px;');
})();
</script>
