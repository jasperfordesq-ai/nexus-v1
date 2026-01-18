<?php
/**
 * Keyboard Shortcuts for Power Users
 * LEGENDARY FEATURE: Quick access to layout switcher and other features
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
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s ease;
        `;

        modal.innerHTML = `
            <div style="
                background: white;
                border-radius: 16px;
                padding: 32px;
                max-width: 600px;
                width: 90%;
                max-height: 80vh;
                overflow-y: auto;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                animation: slideUp 0.3s ease;
            ">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
                    <h2 style="margin: 0; font-size: 24px; font-weight: 700; color: #1f2937;">
                        <i class="fa-solid fa-keyboard" style="color: #6366f1;"></i> Keyboard Shortcuts
                    </h2>
                    <button onclick="this.closest('#keyboard-shortcuts-modal').remove()" style="
                        background: none;
                        border: none;
                        font-size: 24px;
                        color: #9ca3af;
                        cursor: pointer;
                        padding: 0;
                        width: 32px;
                        height: 32px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        border-radius: 8px;
                        transition: all 0.2s;
                    " onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='none'">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>

                <div style="color: #6b7280; margin-bottom: 24px;">
                    Power user shortcuts for faster navigation
                </div>

                <div style="display: flex; flex-direction: column; gap: 16px;">
                    ${createShortcutRow('Ctrl+Shift+L', 'Open Layout Switcher', '#6366f1')}
                    ${createShortcutRow('Ctrl+Shift+P', 'Preview Next Layout', '#8b5cf6')}
                    ${createShortcutRow('Ctrl+Shift+K', 'Toggle Mobile Menu', '#059669')}
                    ${createShortcutRow('Ctrl+Shift+H', 'Go to Home', '#0866ff')}
                    ${createShortcutRow('Ctrl+Shift+/', 'Show This Help', '#f59e0b')}
                </div>

                <div style="
                    margin-top: 24px;
                    padding: 16px;
                    background: rgba(99, 102, 241, 0.05);
                    border-left: 4px solid #6366f1;
                    border-radius: 8px;
                ">
                    <div style="font-size: 13px; color: #4f46e5; font-weight: 600; margin-bottom: 4px;">
                        <i class="fa-solid fa-lightbulb"></i> Pro Tip
                    </div>
                    <div style="font-size: 13px; color: #6b7280;">
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

    function createShortcutRow(keys, description, color) {
        return `
            <div style="
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 12px;
                background: #f9fafb;
                border-radius: 8px;
                transition: all 0.2s;
            " onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='#f9fafb'">
                <span style="font-size: 14px; color: #1f2937; font-weight: 500;">
                    ${description}
                </span>
                <div style="
                    display: flex;
                    gap: 4px;
                    font-family: 'Courier New', monospace;
                    font-size: 13px;
                    font-weight: 600;
                ">
                    ${keys.split('+').map(key => `
                        <span style="
                            background: ${color};
                            color: white;
                            padding: 4px 8px;
                            border-radius: 4px;
                            min-width: 32px;
                            text-align: center;
                        ">${key.charAt(0).toUpperCase() + key.slice(1)}</span>
                    `).join('<span style="color: #9ca3af; padding: 0 4px;">+</span>')}
                </div>
            </div>
        `;
    }

    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Keyboard shortcut hint badge in layout switcher link */
        .keyboard-hint-badge {
            background: rgba(99, 102, 241, 0.1);
            color: #6366f1;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            margin-left: 6px;
        }
    `;
    document.head.appendChild(style);

    console.log('%c🚀 Keyboard Shortcuts Loaded!', 'color: #6366f1; font-weight: bold; font-size: 14px;');
    console.log('%cPress Ctrl+Shift+/ to view all shortcuts', 'color: #6b7280; font-size: 12px;');
})();
</script>
