/**
 * Mobile Select Sheet
 * Native app-style bottom sheet for select dropdowns
 *
 * Version: 1.0 - 2026-01-20
 *
 * Automatically upgrades <select> elements on mobile/native
 * Desktop uses native browser selects unchanged
 *
 * Usage:
 * 1. Auto-init: Add class "mobile-select" to any <select>
 * 2. Manual: MobileSelectSheet.upgrade(selectElement)
 * 3. API: MobileSelectSheet.open(selectElement)
 */

(function() {
    'use strict';

    // Only run on mobile or native
    const isMobile = () => {
        return document.body.classList.contains('is-native') ||
               window.innerWidth <= 768 ||
               'ontouchstart' in window;
    };

    // Sheet HTML template
    const createSheetHTML = (id) => `
        <div class="mobile-sheet-backdrop" id="${id}-backdrop"></div>
        <div class="mobile-sheet mobile-select-sheet" id="${id}" role="listbox" aria-modal="true">
            <div class="mobile-sheet-handle" aria-hidden="true"></div>
            <div class="mobile-sheet-header">
                <span class="mobile-sheet-title"></span>
                <button class="mobile-sheet-close" aria-label="Close">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="mobile-sheet-body">
                <div class="mobile-select-search-wrap" style="display: none;">
                    <input type="text" class="mobile-select-search" placeholder="Search..." aria-label="Search options">
                </div>
                <ul class="mobile-select-options"></ul>
                <div class="mobile-select-empty" style="display: none;">
                    <div class="mobile-select-empty-icon">🔍</div>
                    <div class="mobile-select-empty-text">No results found</div>
                </div>
            </div>
        </div>
    `;

    // Create trigger button HTML
    const createTriggerHTML = () => `
        <button type="button" class="mobile-select-trigger" aria-haspopup="listbox">
            <span class="mobile-select-trigger-text placeholder">Select...</span>
            <svg class="mobile-select-trigger-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </button>
    `;

    class MobileSelectSheet {
        constructor() {
            this.activeSelect = null;
            this.sheet = null;
            this.backdrop = null;
            this.sheetId = 'mobile-select-sheet';
            this.isOpen = false;
            this.startY = 0;
            this.currentY = 0;
            this.isDragging = false;

            this.init();
        }

        init() {
            // Create the sheet element once
            this.createSheet();

            // Bind events
            this.bindSheetEvents();

            // Auto-upgrade selects with .mobile-select class
            if (isMobile()) {
                this.autoUpgrade();

                // Watch for dynamically added selects
                this.observeDOM();
            }
        }

        createSheet() {
            // Check if sheet already exists
            if (document.getElementById(this.sheetId)) {
                this.sheet = document.getElementById(this.sheetId);
                this.backdrop = document.getElementById(this.sheetId + '-backdrop');
                return;
            }

            // Insert sheet HTML
            const container = document.createElement('div');
            container.innerHTML = createSheetHTML(this.sheetId);
            document.body.appendChild(container.firstElementChild); // backdrop
            document.body.appendChild(container.lastElementChild);  // sheet

            this.sheet = document.getElementById(this.sheetId);
            this.backdrop = document.getElementById(this.sheetId + '-backdrop');
        }

        bindSheetEvents() {
            // Close button
            this.sheet.querySelector('.mobile-sheet-close').addEventListener('click', () => this.close());

            // Backdrop click
            this.backdrop.addEventListener('click', () => this.close());

            // Search input
            const searchInput = this.sheet.querySelector('.mobile-select-search');
            searchInput.addEventListener('input', (e) => this.filterOptions(e.target.value));

            // Option selection (delegated)
            this.sheet.querySelector('.mobile-select-options').addEventListener('click', (e) => {
                const option = e.target.closest('.mobile-select-option');
                if (option && !option.classList.contains('disabled')) {
                    this.selectOption(option);
                }
            });

            // Drag to dismiss
            this.initDragToDismiss();

            // Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });

            // Hardware back button (Capacitor)
            document.addEventListener('backbutton', () => {
                if (this.isOpen) {
                    this.close();
                    return false;
                }
            });
        }

        initDragToDismiss() {
            const handle = this.sheet.querySelector('.mobile-sheet-handle');

            const onStart = (e) => {
                this.isDragging = true;
                this.startY = e.touches ? e.touches[0].clientY : e.clientY;
                this.sheet.classList.add('dragging');
            };

            const onMove = (e) => {
                if (!this.isDragging) return;

                const currentY = e.touches ? e.touches[0].clientY : e.clientY;
                const diff = currentY - this.startY;

                if (diff > 0) {
                    this.sheet.style.transform = `translateY(${diff}px)`;
                }
            };

            const onEnd = (e) => {
                if (!this.isDragging) return;

                this.isDragging = false;
                this.sheet.classList.remove('dragging');

                const currentY = e.changedTouches ? e.changedTouches[0].clientY : e.clientY;
                const diff = currentY - this.startY;

                this.sheet.style.transform = '';

                if (diff > 100) {
                    this.close();
                }
            };

            handle.addEventListener('touchstart', onStart, { passive: true });
            handle.addEventListener('mousedown', onStart);
            document.addEventListener('touchmove', onMove, { passive: true });
            document.addEventListener('mousemove', onMove);
            document.addEventListener('touchend', onEnd);
            document.addEventListener('mouseup', onEnd);
        }

        autoUpgrade() {
            document.querySelectorAll('select.mobile-select').forEach(select => {
                this.upgrade(select);
            });
        }

        observeDOM() {
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === 1) {
                            if (node.matches && node.matches('select.mobile-select')) {
                                this.upgrade(node);
                            }
                            // Check children
                            if (node.querySelectorAll) {
                                node.querySelectorAll('select.mobile-select').forEach(select => {
                                    this.upgrade(select);
                                });
                            }
                        }
                    });
                });
            });

            observer.observe(document.body, { childList: true, subtree: true });
        }

        upgrade(select) {
            if (!isMobile()) return;
            if (select.dataset.mobileSelectUpgraded) return;

            select.dataset.mobileSelectUpgraded = 'true';

            // Wrap in container
            const wrapper = document.createElement('div');
            wrapper.className = 'has-mobile-select';
            wrapper.style.position = 'relative';
            select.parentNode.insertBefore(wrapper, select);
            wrapper.appendChild(select);

            // Create trigger button
            const triggerContainer = document.createElement('div');
            triggerContainer.innerHTML = createTriggerHTML();
            const trigger = triggerContainer.firstElementChild;
            wrapper.insertBefore(trigger, select);

            // Update trigger text
            this.updateTriggerText(select, trigger);

            // Click handler
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                this.open(select);
            });

            // Keep select synced
            select.addEventListener('change', () => {
                this.updateTriggerText(select, trigger);
            });
        }

        updateTriggerText(select, trigger) {
            const textEl = trigger.querySelector('.mobile-select-trigger-text');
            const selectedOption = select.options[select.selectedIndex];

            if (selectedOption && selectedOption.value) {
                textEl.textContent = selectedOption.textContent;
                textEl.classList.remove('placeholder');
            } else {
                textEl.textContent = select.getAttribute('placeholder') || 'Select...';
                textEl.classList.add('placeholder');
            }
        }

        open(select) {
            if (!select) return;

            this.activeSelect = select;
            this.isOpen = true;

            // Set title
            const label = select.getAttribute('aria-label') ||
                         select.getAttribute('data-label') ||
                         (select.previousElementSibling?.tagName === 'LABEL'
                            ? select.previousElementSibling.textContent
                            : null) ||
                         'Select an option';
            this.sheet.querySelector('.mobile-sheet-title').textContent = label;

            // Populate options
            this.populateOptions(select);

            // Show search if many options
            const searchWrap = this.sheet.querySelector('.mobile-select-search-wrap');
            const searchInput = this.sheet.querySelector('.mobile-select-search');
            if (select.options.length > 8) {
                searchWrap.classList.remove('hidden');
                this.sheet.classList.add('has-search');
                searchInput.value = '';
            } else {
                searchWrap.classList.add('hidden');
                this.sheet.classList.remove('has-search');
            }

            // Show sheet
            this.backdrop.classList.add('active');
            this.sheet.classList.add('active');
            document.body.classList.add('mobile-sheet-open');

            // Focus search if visible
            if (!searchWrap.classList.contains('hidden')) {
                setTimeout(() => searchInput.focus(), 300);
            }

            // Trigger haptic
            this.triggerHaptic('light');

            // Mark trigger as open
            const trigger = select.parentElement?.querySelector('.mobile-select-trigger');
            if (trigger) trigger.classList.add('open');
        }

        populateOptions(select) {
            const optionsList = this.sheet.querySelector('.mobile-select-options');
            const emptyState = this.sheet.querySelector('.mobile-select-empty');
            optionsList.innerHTML = '';
            emptyState.classList.add('hidden');

            let currentGroup = null;

            Array.from(select.children).forEach((child) => {
                if (child.tagName === 'OPTGROUP') {
                    // Group header
                    const header = document.createElement('li');
                    header.className = 'mobile-select-group-header';
                    header.textContent = child.label;
                    header.setAttribute('role', 'presentation');
                    optionsList.appendChild(header);
                    currentGroup = child.label;

                    // Group options
                    Array.from(child.children).forEach(option => {
                        optionsList.appendChild(this.createOptionElement(option, currentGroup));
                    });
                } else if (child.tagName === 'OPTION') {
                    optionsList.appendChild(this.createOptionElement(child, null));
                }
            });
        }

        createOptionElement(option, group) {
            const li = document.createElement('li');
            li.className = 'mobile-select-option';
            li.setAttribute('role', 'option');
            li.dataset.value = option.value;
            if (group) li.dataset.group = group;

            // Check if selected
            if (option.selected) {
                li.classList.add('selected');
                li.setAttribute('aria-selected', 'true');
            }

            // Check if disabled
            if (option.disabled) {
                li.classList.add('disabled');
                li.setAttribute('aria-disabled', 'true');
            }

            // Build content
            const contentDiv = document.createElement('div');
            contentDiv.className = 'mobile-select-option-content';

            // Icon (if data-icon attribute exists)
            if (option.dataset.icon) {
                const iconDiv = document.createElement('div');
                iconDiv.className = 'mobile-select-option-icon';
                if (option.dataset.icon.startsWith('http') || option.dataset.icon.startsWith('/')) {
                    iconDiv.innerHTML = `<img src="${option.dataset.icon}" alt="">`;
                } else {
                    iconDiv.innerHTML = `<i class="${option.dataset.icon}"></i>`;
                }
                li.insertBefore(iconDiv, li.firstChild);
            }

            // Label
            const labelSpan = document.createElement('span');
            labelSpan.className = 'mobile-select-option-label';
            labelSpan.textContent = option.textContent;
            contentDiv.appendChild(labelSpan);

            // Description (if data-description attribute exists)
            if (option.dataset.description) {
                const descSpan = document.createElement('span');
                descSpan.className = 'mobile-select-option-desc';
                descSpan.textContent = option.dataset.description;
                contentDiv.appendChild(descSpan);
            }

            li.appendChild(contentDiv);

            return li;
        }

        filterOptions(query) {
            const options = this.sheet.querySelectorAll('.mobile-select-option');
            const groups = this.sheet.querySelectorAll('.mobile-select-group-header');
            const emptyState = this.sheet.querySelector('.mobile-select-empty');
            const lowerQuery = query.toLowerCase().trim();
            let visibleCount = 0;
            let visibleGroups = new Set();

            options.forEach(option => {
                const text = option.querySelector('.mobile-select-option-label').textContent.toLowerCase();
                const desc = option.querySelector('.mobile-select-option-desc')?.textContent.toLowerCase() || '';
                const matches = text.includes(lowerQuery) || desc.includes(lowerQuery);

                if (matches) {
                    option.classList.remove('hidden');
                    visibleCount++;
                    if (option.dataset.group) {
                        visibleGroups.add(option.dataset.group);
                    }
                } else {
                    option.classList.add('hidden');
                }
            });

            // Show/hide group headers
            groups.forEach(group => {
                if (visibleGroups.has(group.textContent)) {
                    group.classList.remove('hidden');
                } else {
                    group.classList.add('hidden');
                }
            });

            // Show empty state
            if (visibleCount === 0) {
                emptyState.classList.remove('hidden');
            } else {
                emptyState.classList.add('hidden');
            }
        }

        selectOption(optionEl) {
            if (!this.activeSelect) return;

            const value = optionEl.dataset.value;

            // Update native select
            this.activeSelect.value = value;

            // Dispatch change event
            const event = new Event('change', { bubbles: true });
            this.activeSelect.dispatchEvent(event);

            // Also dispatch input event for frameworks
            const inputEvent = new Event('input', { bubbles: true });
            this.activeSelect.dispatchEvent(inputEvent);

            // Update trigger
            const trigger = this.activeSelect.parentElement?.querySelector('.mobile-select-trigger');
            if (trigger) {
                this.updateTriggerText(this.activeSelect, trigger);
            }

            // Haptic feedback
            this.triggerHaptic('medium');

            // Close sheet
            this.close();
        }

        close() {
            if (!this.isOpen) return;

            this.isOpen = false;
            this.backdrop.classList.remove('active');
            this.sheet.classList.remove('active');
            document.body.classList.remove('mobile-sheet-open');

            // Clear search
            const searchInput = this.sheet.querySelector('.mobile-select-search');
            searchInput.value = '';
            this.filterOptions('');

            // Remove open class from trigger
            if (this.activeSelect) {
                const trigger = this.activeSelect.parentElement?.querySelector('.mobile-select-trigger');
                if (trigger) trigger.classList.remove('open');
            }

            this.activeSelect = null;
        }

        triggerHaptic(type = 'light') {
            // Capacitor haptics
            if (window.NexusNative?.Haptics?.impact) {
                window.NexusNative.Haptics.impact(type);
            }
            // Vibration API fallback
            else if (navigator.vibrate) {
                navigator.vibrate(type === 'medium' ? 20 : 10);
            }
        }
    }

    // Initialize and expose globally
    const instance = new MobileSelectSheet();

    window.MobileSelectSheet = {
        upgrade: (select) => instance.upgrade(select),
        open: (select) => instance.open(select),
        close: () => instance.close()
    };

    // Auto-init on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => instance.autoUpgrade());
    }
})();
