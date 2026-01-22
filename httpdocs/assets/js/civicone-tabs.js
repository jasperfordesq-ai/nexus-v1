/**
 * CivicOne ARIA-Compliant Tabs Component
 * WCAG 2.1 AA Compliant
 *
 * Keyboard Support:
 * - Tab: Moves focus to active tab or first tab
 * - Arrow Left/Right: Navigate between tabs
 * - Home: Move to first tab
 * - End: Move to last tab
 * - Enter/Space: Activate focused tab
 *
 * Based on WAI-ARIA Authoring Practices 1.1
 * https://www.w3.org/WAI/ARIA/apg/patterns/tabs/
 */

(function() {
    'use strict';

    // Initialize all tab modules on the page
    function initTabs() {
        const tabModules = document.querySelectorAll('[data-module="tabs"]');

        tabModules.forEach(function(tabModule) {
            const tablist = tabModule.querySelector('[role="tablist"]');
            const tabs = Array.from(tablist.querySelectorAll('[role="tab"]'));
            const panels = Array.from(tabModule.querySelectorAll('[role="tabpanel"]'));

            // Set up click handlers
            tabs.forEach(function(tab) {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    activateTab(tab, tabs, panels);
                });
            });

            // Set up keyboard navigation
            tablist.addEventListener('keydown', function(e) {
                handleKeyboardNavigation(e, tabs, panels);
            });
        });
    }

    /**
     * Activate a tab and show its panel
     */
    function activateTab(tab, allTabs, allPanels) {
        // Deactivate all tabs
        allTabs.forEach(function(t) {
            t.setAttribute('aria-selected', 'false');
            t.setAttribute('tabindex', '-1');
            t.classList.remove('active');
        });

        // Hide all panels
        allPanels.forEach(function(panel) {
            panel.classList.remove('active');
        });

        // Activate clicked tab
        tab.setAttribute('aria-selected', 'true');
        tab.setAttribute('tabindex', '0');
        tab.classList.add('active');
        tab.focus();

        // Show corresponding panel
        const panelId = tab.getAttribute('aria-controls');
        const panel = document.getElementById(panelId);
        if (panel) {
            panel.classList.add('active');
        }
    }

    /**
     * Handle keyboard navigation
     */
    function handleKeyboardNavigation(e, tabs, panels) {
        const currentIndex = tabs.findIndex(function(tab) {
            return tab === document.activeElement;
        });

        let targetIndex = currentIndex;

        switch(e.key) {
            case 'ArrowLeft':
            case 'Left': // IE/Edge
                e.preventDefault();
                targetIndex = currentIndex > 0 ? currentIndex - 1 : tabs.length - 1;
                break;

            case 'ArrowRight':
            case 'Right': // IE/Edge
                e.preventDefault();
                targetIndex = currentIndex < tabs.length - 1 ? currentIndex + 1 : 0;
                break;

            case 'Home':
                e.preventDefault();
                targetIndex = 0;
                break;

            case 'End':
                e.preventDefault();
                targetIndex = tabs.length - 1;
                break;

            case 'Enter':
            case ' ': // Space
                e.preventDefault();
                // Tab is already focused, just activate it
                if (currentIndex !== -1) {
                    activateTab(tabs[currentIndex], tabs, panels);
                }
                return;

            default:
                return; // Exit early for other keys
        }

        // Focus the target tab (arrow keys move focus without activating)
        if (targetIndex !== currentIndex && targetIndex >= 0) {
            tabs[targetIndex].focus();
            // Auto-activate on focus (alternative pattern)
            // Comment out the line below if you want manual activation only
            activateTab(tabs[targetIndex], tabs, panels);
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTabs);
    } else {
        initTabs();
    }

})();
