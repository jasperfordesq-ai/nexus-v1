<?php
/**
 * Admin Real-Time Updates System - Gold Standard v2.0
 * Polling-based live dashboard updates
 *
 * NOTE: SSE (Server-Sent Events) has been disabled due to server hanging issues.
 * This system now uses polling via /admin-legacy/api/realtime/poll endpoint.
 */
?>

<script>
/**
 * Admin Real-Time Updates
 * Provides live updates for dashboard stats, notifications, and system health
 */
window.AdminRealTime = {
    eventSource: null,
    isConnected: false,
    reconnectAttempts: 0,
    maxReconnectAttempts: 5,
    reconnectDelay: 3000,
    updateInterval: 30000, // 30 seconds for polling fallback
    pollingTimer: null,

    // Callbacks for different update types
    callbacks: {
        stats: [],
        notifications: [],
        health: [],
        users: []
    },

    /**
     * Initialize real-time updates
     * @param {Object} config - Configuration options
     */
    init: function(config) {
        const defaults = {
            endpoint: '/admin-legacy/api/realtime',
            fallbackToPolling: true,
            autoConnect: true,
            debug: false
        };

        this.config = Object.assign({}, defaults, config);

        if (this.config.autoConnect) {
            this.connect();
        }

        // Setup visibility change detection for reconnection
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && !this.isConnected) {
                this.connect();
            }
        });
    },

    /**
     * Connect to real-time updates stream
     * NOTE: SSE has been disabled due to server hanging issues.
     * This method now immediately falls back to polling.
     */
    connect: function() {
        // SSE endpoint is disabled on the server (returns 503)
        // Skip SSE entirely and use polling instead
        if (this.config.debug) {
            console.log('AdminRealTime: Using polling mode (SSE disabled on server)');
        }
        this.startPolling();
        return;

        /* SSE CODE DISABLED - Server returns 503 for SSE endpoint
        // Check if browser supports SSE
        if (typeof(EventSource) === 'undefined') {
            console.warn('AdminRealTime: Browser does not support SSE, falling back to polling');
            this.startPolling();
            return;
        }

        if (this.isConnected) {
            return;
        }

        try {
            const basePath = window.location.pathname.includes('/admin-legacy')
                ? window.location.pathname.split('/admin-legacy')[0]
                : '';

            this.eventSource = new EventSource(basePath + this.config.endpoint);

            this.eventSource.onopen = () => {
                this.isConnected = true;
                this.reconnectAttempts = 0;
                if (this.config.debug) {
                    console.log('AdminRealTime: Connected to real-time updates');
                }
                this.stopPolling();
            };

            this.eventSource.onerror = (error) => {
                this.isConnected = false;
                this.eventSource.close();

                if (this.reconnectAttempts < this.maxReconnectAttempts) {
                    this.reconnectAttempts++;
                    setTimeout(() => this.connect(), this.reconnectDelay);
                } else if (this.config.fallbackToPolling) {
                    console.warn('AdminRealTime: Max reconnection attempts reached, falling back to polling');
                    this.startPolling();
                }
            };

            // Stats updates
            this.eventSource.addEventListener('stats', (e) => {
                try {
                    const data = JSON.parse(e.data);
                    this.triggerCallbacks('stats', data);
                } catch (error) {
                    console.error('AdminRealTime: Failed to parse stats data', error);
                }
            });

            // Notification updates
            this.eventSource.addEventListener('notification', (e) => {
                try {
                    const data = JSON.parse(e.data);
                    this.triggerCallbacks('notifications', data);
                } catch (error) {
                    console.error('AdminRealTime: Failed to parse notification data', error);
                }
            });

            // Health status updates
            this.eventSource.addEventListener('health', (e) => {
                try {
                    const data = JSON.parse(e.data);
                    this.triggerCallbacks('health', data);
                } catch (error) {
                    console.error('AdminRealTime: Failed to parse health data', error);
                }
            });

            // User activity updates
            this.eventSource.addEventListener('users', (e) => {
                try {
                    const data = JSON.parse(e.data);
                    this.triggerCallbacks('users', data);
                } catch (error) {
                    console.error('AdminRealTime: Failed to parse users data', error);
                }
            });

        } catch (error) {
            console.error('AdminRealTime: Failed to connect', error);
            if (this.config.fallbackToPolling) {
                this.startPolling();
            }
        }
        */
    },

    /**
     * Disconnect from real-time updates
     */
    disconnect: function() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        this.isConnected = false;
        this.stopPolling();
    },

    /**
     * Start polling fallback
     */
    startPolling: function() {
        if (this.pollingTimer) {
            return;
        }

        this.pollingTimer = setInterval(() => {
            this.fetchUpdates();
        }, this.updateInterval);

        // Fetch immediately
        this.fetchUpdates();
    },

    /**
     * Stop polling
     */
    stopPolling: function() {
        if (this.pollingTimer) {
            clearInterval(this.pollingTimer);
            this.pollingTimer = null;
        }
    },

    /**
     * Fetch updates via AJAX (polling fallback)
     */
    fetchUpdates: async function() {
        try {
            const basePath = window.location.pathname.includes('/admin-legacy')
                ? window.location.pathname.split('/admin-legacy')[0]
                : '';

            const response = await fetch(basePath + '/admin-legacy/api/realtime/poll', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to fetch updates');
            }

            const data = await response.json();

            // Trigger callbacks for each update type
            if (data.stats) {
                this.triggerCallbacks('stats', data.stats);
            }
            if (data.notifications) {
                this.triggerCallbacks('notifications', data.notifications);
            }
            if (data.health) {
                this.triggerCallbacks('health', data.health);
            }
            if (data.users) {
                this.triggerCallbacks('users', data.users);
            }

        } catch (error) {
            if (this.config.debug) {
                console.error('AdminRealTime: Polling failed', error);
            }
        }
    },

    /**
     * Subscribe to update type
     * @param {string} type - Update type (stats, notifications, health, users)
     * @param {Function} callback - Callback function
     */
    on: function(type, callback) {
        if (this.callbacks[type]) {
            this.callbacks[type].push(callback);
        }
    },

    /**
     * Unsubscribe from update type
     * @param {string} type - Update type
     * @param {Function} callback - Callback function to remove
     */
    off: function(type, callback) {
        if (this.callbacks[type]) {
            this.callbacks[type] = this.callbacks[type].filter(cb => cb !== callback);
        }
    },

    /**
     * Trigger callbacks for update type
     */
    triggerCallbacks: function(type, data) {
        if (this.callbacks[type]) {
            this.callbacks[type].forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`AdminRealTime: Callback error for ${type}`, error);
                }
            });
        }
    },

    /**
     * Update notification badge
     */
    updateNotificationBadge: function(count) {
        const badge = document.querySelector('.admin-notif-badge');
        const bell = document.querySelector('.admin-notif-bell');

        if (count > 0) {
            if (badge) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = '';
            } else if (bell) {
                const newBadge = document.createElement('span');
                newBadge.className = 'admin-notif-badge';
                newBadge.textContent = count > 99 ? '99+' : count;
                bell.appendChild(newBadge);
            }
        } else if (badge) {
            badge.style.display = 'none';
        }
    },

    /**
     * Show toast notification
     */
    showToast: function(message, type) {
        type = type || 'info';

        const toast = document.createElement('div');
        toast.className = 'admin-toast admin-toast-' + type;
        toast.innerHTML = `
            <i class="fa-solid ${this.getToastIcon(type)}"></i>
            <span>${message}</span>
        `;

        // Add to page
        let container = document.querySelector('.admin-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'admin-toast-container';
            document.body.appendChild(container);
        }

        container.appendChild(toast);

        // Animate in
        setTimeout(() => toast.classList.add('show'), 10);

        // Remove after 5 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    },

    /**
     * Get toast icon by type
     */
    getToastIcon: function(type) {
        const icons = {
            success: 'fa-circle-check',
            error: 'fa-circle-xmark',
            warning: 'fa-triangle-exclamation',
            info: 'fa-circle-info'
        };
        return icons[type] || icons.info;
    }
};

// Auto-initialize on pages that need it
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on an admin page
    if (window.location.pathname.includes('/admin-legacy')) {
        // Initialize with polling only (SSE endpoint is disabled due to server issues)
        AdminRealTime.init({
            fallbackToPolling: true,
            autoConnect: true,  // This will trigger connect() which immediately starts polling
            debug: false
        });

        // Subscribe to notifications
        AdminRealTime.on('notifications', function(data) {
            if (data.count !== undefined) {
                AdminRealTime.updateNotificationBadge(data.count);
            }
            if (data.new && data.message) {
                AdminRealTime.showToast(data.message, 'info');
            }
        });

        // Subscribe to stats updates (dashboard)
        if (window.location.pathname.endsWith('/admin-legacy') || window.location.pathname.endsWith('/admin-legacy/')) {
            AdminRealTime.on('stats', function(data) {
                // Update dashboard stats
                updateDashboardStats(data);
            });
        }

        // Subscribe to health updates (monitoring page)
        if (window.location.pathname.includes('/monitoring')) {
            AdminRealTime.on('health', function(data) {
                updateHealthStatus(data);
            });
        }
    }
});

// Dashboard stats update helper
function updateDashboardStats(data) {
    if (data.users_online !== undefined) {
        const el = document.querySelector('[data-stat="users-online"]');
        if (el) {
            el.textContent = data.users_online;
            el.classList.add('stat-updated');
            setTimeout(() => el.classList.remove('stat-updated'), 500);
        }
    }

    if (data.active_sessions !== undefined) {
        const el = document.querySelector('[data-stat="active-sessions"]');
        if (el) {
            el.textContent = data.active_sessions;
            el.classList.add('stat-updated');
            setTimeout(() => el.classList.remove('stat-updated'), 500);
        }
    }
}

// Health status update helper
function updateHealthStatus(data) {
    if (data.status) {
        const banner = document.querySelector('.health-status-banner');
        if (banner) {
            banner.className = 'health-status-banner ' + data.status;
        }
    }
}
</script>

<style>
/* Toast Notifications */
.admin-toast-container {
    position: fixed;
    top: 80px;
    right: 20px;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.admin-toast {
    background: rgba(15, 23, 42, 0.98);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 12px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 300px;
    max-width: 400px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
    transform: translateX(450px);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    color: #f1f5f9;
    font-size: 0.9rem;
}

.admin-toast.show {
    transform: translateX(0);
}

.admin-toast i {
    font-size: 1.1rem;
    flex-shrink: 0;
}

.admin-toast-success {
    border-left: 3px solid #10b981;
}

.admin-toast-success i {
    color: #10b981;
}

.admin-toast-error {
    border-left: 3px solid #ef4444;
}

.admin-toast-error i {
    color: #ef4444;
}

.admin-toast-warning {
    border-left: 3px solid #f59e0b;
}

.admin-toast-warning i {
    color: #f59e0b;
}

.admin-toast-info {
    border-left: 3px solid #6366f1;
}

.admin-toast-info i {
    color: #6366f1;
}

/* Stat Update Animation */
.stat-updated {
    animation: statPulse 0.5s ease-out;
}

@keyframes statPulse {
    0% {
        transform: scale(1);
        color: #6366f1;
    }
    50% {
        transform: scale(1.1);
        color: #8b5cf6;
    }
    100% {
        transform: scale(1);
        color: #6366f1;
    }
}

/* Live Indicator */
.admin-live-indicator {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #10b981;
}

.admin-live-indicator::before {
    content: '';
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10b981;
    animation: livePulse 2s ease-in-out infinite;
}

@keyframes livePulse {
    0%, 100% {
        opacity: 1;
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    }
    50% {
        opacity: 0.7;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .admin-toast-container {
        top: 60px;
        right: 10px;
        left: 10px;
    }

    .admin-toast {
        min-width: auto;
        max-width: none;
    }
}
</style>
