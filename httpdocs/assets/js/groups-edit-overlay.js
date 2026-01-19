/**
 * Groups Edit Overlay - Gold Standard Implementation
 * Handles the edit overlay modal for groups with tab switching, form handling, and PWA features
 */

// ============================================
// CONFIGURATION & STATE
// ============================================
const EditOverlayConfig = {
    isClosing: false,
    basePath: window.location.pathname.split('/edit-group/')[0] || ''
};

// ============================================
// HAPTIC FEEDBACK
// ============================================
function haptic(duration = 10) {
    if (navigator.vibrate) {
        navigator.vibrate(duration);
    }
}

// ============================================
// TAB SWITCHING
// ============================================
function switchTab(type) {
    // Update pills
    const pills = document.querySelectorAll('.edit-pill');
    pills.forEach(pill => {
        pill.classList.remove('active');
        pill.setAttribute('aria-selected', 'false');
    });

    const activePill = document.querySelector(`.edit-pill[data-type="${type}"]`);
    if (activePill) {
        activePill.classList.add('active');
        activePill.setAttribute('aria-selected', 'true');
    }

    // Update panels
    const panels = document.querySelectorAll('.edit-panel');
    panels.forEach(panel => {
        panel.classList.remove('active');
    });

    const activePanel = document.getElementById(`panel-${type}`);
    if (activePanel) {
        activePanel.classList.add('active');
    }

    haptic();
}

// ============================================
// CLOSE OVERLAY
// ============================================
function closeEditOverlay() {
    if (EditOverlayConfig.isClosing) return;
    EditOverlayConfig.isClosing = true;
    haptic();

    // Try to get group ID from the page
    const groupIdInput = document.querySelector('input[name="group_id"]');
    const groupId = groupIdInput ? groupIdInput.value : null;

    const referrer = document.referrer;
    const currentHost = window.location.host;

    // If came from same site, go back to referrer
    if (referrer && referrer.includes(currentHost)) {
        window.location.href = referrer;
    } else if (groupId) {
        // Otherwise go to the group page
        window.location.href = `${EditOverlayConfig.basePath}/groups/${groupId}?tab=settings`;
    } else {
        // Fallback to groups list
        window.location.href = `${EditOverlayConfig.basePath}/groups`;
    }
}

// ============================================
// IMAGE PREVIEW
// ============================================
function previewImage(input, targetId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const target = document.getElementById(targetId);
            if (target) {
                target.src = e.target.result;
            }
        };
        reader.readAsDataURL(input.files[0]);
        haptic(20);
    }
}

// ============================================
// INITIALIZATION
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Store base path from URL
    const pathParts = window.location.pathname.split('/');
    const editIndex = pathParts.indexOf('edit-group');
    if (editIndex > 0) {
        EditOverlayConfig.basePath = pathParts.slice(0, editIndex).join('/');
    }

    // ============================================
    // INVITE TAB FUNCTIONALITY
    // ============================================
    const searchInput = document.getElementById('userSearch');
    const userList = document.getElementById('userList');
    const selectedCount = document.getElementById('selectedCount');
    const submitBtn = document.getElementById('submitBtn');
    const checkboxes = document.querySelectorAll('input[name="user_ids[]"]');
    const addDirectlyCheckbox = document.getElementById('addDirectlyCheckbox');

    // Search filter
    if (searchInput && userList) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const items = userList.querySelectorAll('.user-item');

            items.forEach(item => {
                const name = item.dataset.name || '';
                item.style.display = name.includes(query) ? 'flex' : 'none';
            });
        });
    }

    // Selection count
    function updateCount() {
        const checked = document.querySelectorAll('input[name="user_ids[]"]:checked').length;
        if (selectedCount) {
            selectedCount.textContent = `${checked} member${checked !== 1 ? 's' : ''} selected`;
        }
        if (submitBtn) {
            submitBtn.disabled = checked === 0;
        }
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            this.closest('.user-item').classList.toggle('selected', this.checked);
            updateCount();
            haptic();
        });
    });

    // Toggle button text based on "Add directly" checkbox
    if (addDirectlyCheckbox && submitBtn) {
        addDirectlyCheckbox.addEventListener('change', function() {
            if (this.checked) {
                submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> Add Members Now';
                submitBtn.style.background = 'linear-gradient(135deg, #10b981, #059669)';
            } else {
                submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Invitations';
                submitBtn.style.background = 'linear-gradient(135deg, #6366f1, #8b5cf6)';
            }
            haptic();
        });
    }

    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    document.addEventListener('keydown', function(e) {
        // ESC key to close
        if (e.key === 'Escape') {
            closeEditOverlay();
        }

        // Tab navigation with numbers (1 = Edit, 2 = Invite)
        if (e.key === '1' && !e.target.matches('input, textarea')) {
            e.preventDefault();
            switchTab('edit');
        }
        if (e.key === '2' && !e.target.matches('input, textarea')) {
            e.preventDefault();
            switchTab('invite');
        }
    });

    // ============================================
    // FORM SUBMISSION - OFFLINE PROTECTION
    // ============================================
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!navigator.onLine) {
                e.preventDefault();
                alert('You are offline. Please connect to the internet to save changes.');
                return;
            }

            // Show loading state
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                const originalText = submitButton.innerHTML;
                submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
                submitButton.disabled = true;

                // Re-enable if form validation fails
                setTimeout(() => {
                    if (!form.checkValidity()) {
                        submitButton.innerHTML = originalText;
                        submitButton.disabled = false;
                    }
                }, 100);
            }
        });
    });

    // ============================================
    // BUTTON PRESS STATES (HAPTIC FEEDBACK)
    // ============================================
    const interactiveButtons = document.querySelectorAll('.ed-submit, .edit-pill, .edit-close-btn, button');
    interactiveButtons.forEach(btn => {
        btn.addEventListener('pointerdown', function() {
            this.style.transform = 'scale(0.96)';
            haptic(5);
        });
        btn.addEventListener('pointerup', function() {
            this.style.transform = '';
        });
        btn.addEventListener('pointerleave', function() {
            this.style.transform = '';
        });
    });

    // ============================================
    // FILE INPUT ENHANCEMENT
    // ============================================
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const label = this.parentElement.querySelector('i') || this.parentElement;
            if (this.files.length > 0) {
                label.style.color = '#10b981';
                haptic(15);
            }
        });
    });

    // ============================================
    // CLEAR IMAGE BUTTONS
    // ============================================
    const clearButtons = document.querySelectorAll('.ed-clear-btn input[type="checkbox"]');
    clearButtons.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                const preview = this.closest('.ed-image-upload').querySelector('.ed-image-preview');
                if (preview) {
                    preview.style.opacity = '0.3';
                    preview.style.filter = 'grayscale(1)';
                }
                haptic(20);
            }
        });
    });

    // ============================================
    // PWA - DYNAMIC THEME COLOR
    // ============================================
    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        let metaTheme = document.querySelector('meta[name="theme-color"]');

        if (!metaTheme) {
            metaTheme = document.createElement('meta');
            metaTheme.name = 'theme-color';
            document.head.appendChild(metaTheme);
        }

        metaTheme.setAttribute('content', isDark ? '#1e293b' : '#ffffff');
    }

    // Watch for theme changes
    const themeObserver = new MutationObserver(updateThemeColor);
    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();

    // ============================================
    // ACCESSIBILITY - FOCUS TRAP
    // ============================================
    const overlay = document.querySelector('.edit-overlay');
    if (overlay) {
        const focusableElements = overlay.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        const firstFocusable = focusableElements[0];
        const lastFocusable = focusableElements[focusableElements.length - 1];

        overlay.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                if (e.shiftKey) {
                    if (document.activeElement === firstFocusable) {
                        e.preventDefault();
                        lastFocusable.focus();
                    }
                } else {
                    if (document.activeElement === lastFocusable) {
                        e.preventDefault();
                        firstFocusable.focus();
                    }
                }
            }
        });

        // Focus first element on load
        setTimeout(() => {
            if (firstFocusable && firstFocusable.tagName !== 'BUTTON') {
                const firstInput = overlay.querySelector('input:not([type="hidden"]), textarea');
                if (firstInput) firstInput.focus();
            }
        }, 100);
    }

    // ============================================
    // OFFLINE INDICATOR
    // ============================================
    function handleOffline() {
        const banner = document.getElementById('offlineBanner');
        if (banner) {
            banner.classList.add('visible');
            if (navigator.vibrate) navigator.vibrate(100);
        }
    }

    function handleOnline() {
        const banner = document.getElementById('offlineBanner');
        if (banner) {
            banner.classList.remove('visible');
        }
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!navigator.onLine) {
        handleOffline();
    }
});

// Make functions globally available for inline event handlers
window.switchTab = switchTab;
window.closeEditOverlay = closeEditOverlay;
window.previewImage = previewImage;
window.haptic = haptic;
