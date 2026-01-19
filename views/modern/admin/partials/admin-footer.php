<?php
/**
 * Admin Gold Standard Footer Component
 * STANDALONE - closes HTML document started by admin-header.php
 */
?>

    </div><!-- /.admin-gold-content -->
</div><!-- /.admin-gold-wrapper -->

<!-- Admin Toast Container -->
<div class="admin-toast-container" id="adminToastContainer"></div>

<script>
/**
 * Admin Toast Notification System
 */
window.AdminToast = {
    container: document.getElementById('adminToastContainer'),

    show: function(type, title, message, duration) {
        duration = duration || 5000;
        var toast = document.createElement('div');
        toast.className = 'admin-toast ' + type;

        var icons = {
            success: 'fa-check-circle',
            error: 'fa-times-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };

        toast.innerHTML = '<i class="fa-solid ' + icons[type] + ' admin-toast-icon"></i>' +
            '<div class="admin-toast-content">' +
                '<div class="admin-toast-title">' + title + '</div>' +
                '<div class="admin-toast-message">' + message + '</div>' +
            '</div>' +
            '<button class="admin-toast-close" onclick="this.parentElement.remove()">' +
                '<i class="fa-solid fa-times"></i>' +
            '</button>';

        this.container.appendChild(toast);

        if (duration > 0) {
            setTimeout(function() {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, duration);
        }

        return toast;
    },

    success: function(title, message) { return this.show('success', title, message); },
    error: function(title, message) { return this.show('error', title, message); },
    warning: function(title, message) { return this.show('warning', title, message); },
    info: function(title, message) { return this.show('info', title, message); }
};

/**
 * Admin Modal System
 */
window.AdminModal = {
    show: function(id) {
        var modal = document.getElementById(id);
        if (modal) modal.classList.add('open');
    },
    hide: function(id) {
        var modal = document.getElementById(id);
        if (modal) modal.classList.remove('open');
    }
};
</script>

<style>
/* Toast Notifications */
.admin-toast-container {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.admin-toast {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    background: rgba(15, 23, 42, 0.95);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
    min-width: 300px;
    max-width: 450px;
    animation: adminToastIn 0.3s ease;
}

@keyframes adminToastIn {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.admin-toast-icon {
    font-size: 1.25rem;
    flex-shrink: 0;
}

.admin-toast-content {
    flex: 1;
}

.admin-toast-title {
    font-weight: 600;
    color: #fff;
    margin-bottom: 2px;
}

.admin-toast-message {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.7);
}

.admin-toast-close {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.5);
    cursor: pointer;
    padding: 0.25rem;
    transition: color 0.2s;
}

.admin-toast-close:hover {
    color: #fff;
}

.admin-toast.success { border-left: 3px solid #22c55e; }
.admin-toast.success .admin-toast-icon { color: #22c55e; }

.admin-toast.error { border-left: 3px solid #ef4444; }
.admin-toast.error .admin-toast-icon { color: #ef4444; }

.admin-toast.warning { border-left: 3px solid #f59e0b; }
.admin-toast.warning .admin-toast-icon { color: #f59e0b; }

.admin-toast.info { border-left: 3px solid #06b6d4; }
.admin-toast.info .admin-toast-icon { color: #06b6d4; }

@media (max-width: 600px) {
    .admin-toast-container {
        left: 1rem;
        right: 1rem;
        bottom: 1rem;
    }

    .admin-toast {
        min-width: auto;
        max-width: none;
    }
}
</style>

</body>
</html>
