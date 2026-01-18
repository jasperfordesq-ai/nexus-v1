    </div><!-- /.super-admin-content -->
</div><!-- /.super-admin-wrapper -->

<!-- Toast Notification System -->
<div id="superAdminToastContainer" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px;"></div>

<script>
// Toast notification system
window.showSuperAdminToast = function(message, type = 'info', duration = 4000) {
    const container = document.getElementById('superAdminToastContainer');
    const toast = document.createElement('div');

    const colors = {
        success: { bg: 'rgba(16, 185, 129, 0.95)', border: '#10b981', icon: 'fa-check-circle' },
        error: { bg: 'rgba(239, 68, 68, 0.95)', border: '#ef4444', icon: 'fa-exclamation-circle' },
        warning: { bg: 'rgba(245, 158, 11, 0.95)', border: '#f59e0b', icon: 'fa-exclamation-triangle' },
        info: { bg: 'rgba(147, 51, 234, 0.95)', border: '#9333ea', icon: 'fa-info-circle' }
    };

    const config = colors[type] || colors.info;

    toast.innerHTML = `
        <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fa-solid ${config.icon}" style="font-size: 1.1rem;"></i>
            <span style="font-size: 0.9rem;">${message}</span>
            <button onclick="this.closest('.super-admin-toast').remove()" style="background: none; border: none; color: white; cursor: pointer; padding: 4px; margin-left: auto; opacity: 0.7;">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
    `;

    toast.className = 'super-admin-toast';
    toast.style.cssText = `
        background: ${config.bg};
        border: 1px solid ${config.border};
        border-radius: 10px;
        padding: 14px 18px;
        color: white;
        font-family: 'Inter', sans-serif;
        min-width: 280px;
        max-width: 400px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        animation: superAdminToastSlide 0.3s ease;
    `;

    container.appendChild(toast);

    if (duration > 0) {
        setTimeout(() => {
            toast.style.animation = 'superAdminToastFade 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
};

// Animation styles
const toastStyles = document.createElement('style');
toastStyles.textContent = `
    @keyframes superAdminToastSlide {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes superAdminToastFade {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(toastStyles);

// Flash message handler
document.addEventListener('DOMContentLoaded', function() {
    const flashMessages = document.querySelectorAll('.super-admin-flash-message');
    flashMessages.forEach(function(flash) {
        const type = flash.dataset.type || 'info';
        const message = flash.textContent.trim();
        if (message) {
            showSuperAdminToast(message, type);
        }
        flash.remove();
    });
});
</script>
</body>
</html>
