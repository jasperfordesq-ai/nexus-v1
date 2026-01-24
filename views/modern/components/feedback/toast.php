<?php

/**
 * Component: Toast
 *
 * Toast notification (typically triggered via JavaScript).
 * This component provides the toast container and JS helper.
 *
 * @param string $position Position: 'top-right', 'top-left', 'bottom-right', 'bottom-left', 'top-center', 'bottom-center' (default: 'top-right')
 * @param string $class Additional CSS classes for container
 */

$position = $position ?? 'top-right';
$class = $class ?? '';

$cssClass = trim('toast-container ' . $class);
?>

<div class="<?= e($cssClass) ?>" data-position="<?= e($position) ?>" id="toast-container">
    <!-- Toasts will be inserted here by JavaScript -->
</div>

<script>
/**
 * Show a toast notification
 *
 * @param {string} message - The message to display
 * @param {string} type - Toast type: 'success', 'error', 'warning', 'info' (default: 'info')
 * @param {number} duration - Duration in ms before auto-dismiss (default: 5000, 0 for persistent)
 */
function showToast(message, type = 'info', duration = 5000) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <i class="fa-solid fa-${icons[type] || icons.info} toast-icon"></i>
        <span class="toast-message">${message}</span>
        <button type="button" class="toast-dismiss" aria-label="Dismiss">
            <i class="fa-solid fa-times"></i>
        </button>
    `;

    // Add dismiss handler
    toast.querySelector('.toast-dismiss').addEventListener('click', () => {
        toast.classList.add('toast-hiding');
        setTimeout(() => toast.remove(), 300);
    });

    container.appendChild(toast);

    // Trigger animation
    requestAnimationFrame(() => {
        toast.classList.add('toast-visible');
    });

    // Auto-dismiss
    if (duration > 0) {
        setTimeout(() => {
            if (toast.parentNode) {
                toast.classList.add('toast-hiding');
                setTimeout(() => toast.remove(), 300);
            }
        }, duration);
    }

    return toast;
}

// Shorthand helpers
function toastSuccess(message, duration) { return showToast(message, 'success', duration); }
function toastError(message, duration) { return showToast(message, 'error', duration); }
function toastWarning(message, duration) { return showToast(message, 'warning', duration); }
function toastInfo(message, duration) { return showToast(message, 'info', duration); }
</script>
