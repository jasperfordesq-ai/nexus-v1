/**
 * CivicOne Groups Create Overlay - Interactivity
 * WCAG 2.1 AA Compliant
 * Overlay close, type selection, image preview
 */

(function() {
    'use strict';

    // Prevent multiple close calls
    let isClosing = false;

    // Get base path from global or fallback
    const basePath = window.TENANT_BASE_PATH || '';

    // Close overlay - with debounce to prevent multiple calls
    window.closeOverlay = function() {
        if (isClosing) return;
        isClosing = true;

        // Get the referrer URL
        const referrer = document.referrer;
        const currentHost = window.location.host;

        // Check if referrer exists and is from same host (internal navigation)
        if (referrer && referrer.includes(currentHost)) {
            window.location.href = referrer;
        } else {
            // No valid referrer, go to groups page
            window.location.href = basePath + '/groups';
        }
    };

    // Type selection
    window.selectType = function(typeId, isHub) {
        // Update hidden input
        const typeInput = document.getElementById('typeIdInput');
        if (typeInput) {
            typeInput.value = typeId;
        }

        // Update active pill
        document.querySelectorAll('.type-pill').forEach(pill => {
            pill.classList.remove('active');
        });
        const selectedPill = document.querySelector(`.type-pill[data-type-id="${typeId}"]`);
        if (selectedPill) {
            selectedPill.classList.add('active');
        }
    };

    // Image preview
    window.previewImage = function(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewImg = document.getElementById('previewImg');
                const uploadArea = document.getElementById('uploadArea');
                const imagePreview = document.getElementById('imagePreview');

                if (previewImg) previewImg.src = e.target.result;
                if (uploadArea) uploadArea.style.display = 'none';
                if (imagePreview) imagePreview.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        }
    };

    // Remove image
    window.removeImage = function(e) {
        e.stopPropagation();
        const imageFile = document.getElementById('imageFile');
        const uploadArea = document.getElementById('uploadArea');
        const imagePreview = document.getElementById('imagePreview');

        if (imageFile) imageFile.value = '';
        if (uploadArea) uploadArea.style.display = 'block';
        if (imagePreview) imagePreview.style.display = 'none';
    };

    // Initialize event listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Close on backdrop click
        const backdrop = document.getElementById('overlayBackdrop');
        if (backdrop) {
            backdrop.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeOverlay();
                }
            });
        }

        // ESC key to close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                closeOverlay();
            }
        });

        // Prevent backdrop clicks from bubbling to container
        const container = document.querySelector('.create-overlay-container');
        if (container) {
            container.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    });

})();
