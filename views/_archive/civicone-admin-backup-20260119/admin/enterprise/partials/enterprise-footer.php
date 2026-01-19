<?php
/**
 * Enterprise Module Footer - Gold Standard
 * STANDALONE footer with toast notifications
 */
?>

    </div><!-- /.enterprise-content -->
</div><!-- /.enterprise-wrapper -->

<!-- Toast Notification System -->
<div id="enterpriseToastContainer" style="position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.75rem;"></div>

<script>
// Toast Notification System
const EnterpriseToast = {
    container: null,
    init() {
        this.container = document.getElementById('enterpriseToastContainer');
    },
    show(message, type = 'info', duration = 4000) {
        if (!this.container) this.init();

        const toast = document.createElement('div');
        toast.className = 'enterprise-toast enterprise-toast-' + type;

        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };

        toast.innerHTML = `
            <i class="fa-solid ${icons[type] || icons.info}"></i>
            <span>${message}</span>
            <button type="button" class="toast-close">&times;</button>
        `;

        toast.style.cssText = `
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            background: rgba(10, 22, 40, 0.95);
            border-radius: 12px;
            min-width: 300px;
            max-width: 450px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
            animation: toastSlideIn 0.3s ease;
            border: 1px solid rgba(6, 182, 212, 0.25);
            color: white;
            font-size: 0.9rem;
        `;

        const typeStyles = {
            success: 'border-left: 4px solid #10b981;',
            error: 'border-left: 4px solid #ef4444;',
            warning: 'border-left: 4px solid #f59e0b;',
            info: 'border-left: 4px solid #06b6d4;'
        };
        toast.style.cssText += typeStyles[type] || typeStyles.info;

        const icon = toast.querySelector('i');
        const iconColors = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#06b6d4'
        };
        icon.style.color = iconColors[type] || iconColors.info;

        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.style.cssText = `
            background: none;
            border: none;
            color: rgba(255,255,255,0.5);
            font-size: 1.25rem;
            cursor: pointer;
            margin-left: auto;
            padding: 0;
            line-height: 1;
        `;
        closeBtn.onclick = () => this.dismiss(toast);

        this.container.appendChild(toast);

        if (duration > 0) {
            setTimeout(() => this.dismiss(toast), duration);
        }

        return toast;
    },
    dismiss(toast) {
        if (!toast || !toast.parentNode) return;
        toast.style.animation = 'toastSlideOut 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    },
    success(message, duration) { return this.show(message, 'success', duration); },
    error(message, duration) { return this.show(message, 'error', duration); },
    warning(message, duration) { return this.show(message, 'warning', duration); },
    info(message, duration) { return this.show(message, 'info', duration); }
};

// Add animations
const toastStyle = document.createElement('style');
toastStyle.textContent = `
    @keyframes toastSlideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes toastSlideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(toastStyle);

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => EnterpriseToast.init());

// Handle flash messages
<?php if (!empty($_GET['msg'])): ?>
(function() {
    const messages = {
        'saved': ['Changes saved successfully', 'success'],
        'created': ['Record created successfully', 'success'],
        'deleted': ['Record deleted successfully', 'success'],
        'updated': ['Record updated successfully', 'success'],
        'error': ['An error occurred', 'error'],
        'access_denied': ['Access denied', 'error'],
        'request_submitted': ['GDPR request submitted', 'success'],
        'breach_reported': ['Data breach reported', 'warning'],
        'consent_recorded': ['Consent recorded successfully', 'success'],
    };
    const msg = <?= json_encode($_GET['msg']) ?>;
    const [text, type] = messages[msg] || [msg, 'info'];
    setTimeout(() => EnterpriseToast.show(text, type), 100);
})();
<?php endif; ?>

<?php if (!empty($_GET['error'])): ?>
setTimeout(() => EnterpriseToast.error(<?= json_encode($_GET['error']) ?>), 100);
<?php endif; ?>
</script>

</body>
</html>
