/**
 * Nexus Notification Controller (v3.0 - Pusher WebSocket Support)
 * Handles all notification logic: Real-time WebSocket, Polling Fallback, and UI Updates.
 * Uses Pusher for instant notifications with automatic fallback to polling.
 */

class NexusNotifications {
    constructor() {
        // Fix: Ensure base path never ends with a slash to prevent //api URL errors
        this.basePath = this.getBasePath().replace(/\/+$/, '');
        this.csrfToken = this.getCsrfToken();
        this.elements = {
            bellIcon: document.querySelector('.nexus-utility-bar .fa-bell'),
            dropdown: document.querySelector('.htb-dropdown-content')
        };

        // State
        this.isProcessing = false;
        this.pollInterval = 60000; // 60s
        this.pollingActive = false;
        this.pusherConnected = false;

        this.init();
    }

    getBasePath() {
        return (typeof NEXUS_BASE !== 'undefined') ? NEXUS_BASE : '';
    }

    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    init() {
        console.log("NexusNotifications v3.0: Initializing (Pusher + Polling Fallback)...");
        // 1. Global Event Delegation (Mousedown Handling) - KEPT FOR LINKS
        document.body.addEventListener('mousedown', (e) => this.handleInteraction(e));

        // 2. Try to connect to Pusher, fallback to polling
        this.initPusher();
    }

    /**
     * Initialize Pusher real-time connection
     */
    initPusher() {
        // Wait for NexusPusher to be available
        if (typeof window.nexusPusher !== 'undefined') {
            this.bindPusherEvents();
        } else {
            // Wait for pusher-client.js to initialize
            const checkPusher = setInterval(() => {
                if (typeof window.nexusPusher !== 'undefined') {
                    clearInterval(checkPusher);
                    this.bindPusherEvents();
                }
            }, 100);

            // Fallback to polling after 3 seconds if Pusher not available
            setTimeout(() => {
                if (!this.pusherConnected && !this.pollingActive) {
                    console.log("NexusNotifications: Pusher not available, starting polling");
                    this.startPolling();
                }
            }, 3000);
        }
    }

    /**
     * Bind event handlers to Pusher client
     */
    bindPusherEvents() {
        const pusher = window.nexusPusher;

        // Connection status
        pusher.on('connection', (data) => {
            if (data.status === 'connected') {
                console.log("NexusNotifications: Connected to Pusher");
                this.pusherConnected = true;
                this.stopPolling();
            } else if (data.status === 'fallback' || data.status === 'error') {
                console.log("NexusNotifications: Pusher unavailable, using polling");
                this.pusherConnected = false;
                this.startPolling();
            }
        });

        // Real-time notification received
        pusher.on('notification', (data) => {
            console.log("NexusNotifications: Real-time notification received", data);
            this.handleRealtimeNotification(data);
        });

        // Unread count update
        pusher.on('unread-count', (data) => {
            console.log("NexusNotifications: Unread count update", data);
            this.updateBadge(data.notifications || 0);
        });

        // New message notification
        pusher.on('new-message', (data) => {
            console.log("NexusNotifications: New message received", data);
            this.handleNewMessage(data);
        });

        // If Pusher is already connected
        if (pusher.isConnected()) {
            this.pusherConnected = true;
        } else {
            // Start polling as backup until Pusher connects
            this.startPolling();
        }
    }

    /**
     * Handle real-time notification from Pusher
     */
    handleRealtimeNotification(data) {
        // Update the badge count
        this.incrementBadge();

        // Show browser notification if permitted
        this.showBrowserNotification(data);

        // Add to dropdown if open
        this.addNotificationToDropdown(data);
    }

    /**
     * Handle new message notification
     */
    handleNewMessage(data) {
        // Increment message badge if exists
        const messageBadge = document.querySelector('.message-badge, [data-message-count]');
        if (messageBadge) {
            const current = parseInt(messageBadge.textContent) || 0;
            messageBadge.textContent = current + 1;
            messageBadge.style.display = 'flex';
        }
    }

    /**
     * Show browser notification
     */
    showBrowserNotification(data) {
        if (!('Notification' in window)) return;
        if (Notification.permission !== 'granted') return;
        if (document.hasFocus()) return; // Don't show if page is focused

        const notification = new Notification(data.type || 'Notification', {
            body: data.message,
            icon: '/assets/images/pwa/icon-192x192.png',
            tag: 'nexus-notification-' + (data.id || Date.now()),
        });

        notification.onclick = () => {
            window.focus();
            if (data.link) {
                window.location.href = data.link;
            }
            notification.close();
        };

        setTimeout(() => notification.close(), 5000);
    }

    /**
     * Add notification to dropdown UI
     */
    addNotificationToDropdown(data) {
        const listContainer = document.getElementById('nexus-notif-list');
        if (!listContainer) return;

        // Remove "no notifications" message if present
        const emptyMsg = listContainer.querySelector('.notif-dropdown-empty, .empty-message, [data-empty]');
        if (emptyMsg) emptyMsg.remove();

        // Create notification element using dark-mode compatible classes
        const notifEl = document.createElement('a');
        notifEl.href = data.link || '#';
        notifEl.className = 'notif-dropdown-item';
        notifEl.setAttribute('data-notif-id', data.id);
        notifEl.innerHTML = `
            <div class="notif-message">${this.escapeHtml(data.message)}</div>
            <div class="notif-time"><i class="fa-regular fa-clock"></i> Just now</div>
        `;

        // Prepend to list
        listContainer.insertBefore(notifEl, listContainer.firstChild);
    }

    /**
     * Increment badge count by 1
     */
    incrementBadge() {
        const bellIcon = document.querySelector('.nexus-utility-bar .fa-bell');
        if (!bellIcon) return;

        const container = bellIcon.parentNode;
        let badge = container.querySelector('span');

        if (badge) {
            const current = parseInt(badge.textContent) || 0;
            const newCount = current + 1;
            badge.textContent = newCount > 9 ? '9+' : newCount;
            badge.style.display = 'flex';
        } else {
            const s = document.createElement('span');
            s.style.cssText = 'position: absolute; top: -6px; right: -6px; background: #ef4444; color: white; border-radius: 50%; min-width: 16px; height: 16px; padding: 0 4px; font-size: 0.65rem; display: flex; align-items: center; justify-content: center; font-weight: 700; border: 2px solid rgba(0,0,0,0.1);';
            s.textContent = '1';
            container.appendChild(s);
        }
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Stop polling (when Pusher is connected)
     */
    stopPolling() {
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
        this.pollingActive = false;
        console.log("NexusNotifications: Polling stopped (using Pusher)");
    }

    handleInteraction(e) {
        // Only handle individual notification links here now.
        // The "Mark All Read" button is handled via inline onclick.
        const notifItem = e.target.closest('[data-notif-id]');
        if (notifItem) {
            const id = notifItem.getAttribute('data-notif-id');
            this.markOneRead(id);
        }
    }

    async markAllRead(btn = null) {
        if (this.isProcessing) return;
        this.isProcessing = true;

        // Desktop dropdown list
        const listContainer = document.getElementById('nexus-notif-list');
        const badge = document.getElementById('nexus-bell-badge');
        // Mobile bottom sheet list
        const mobileList = document.querySelector('.mobile-notification-list');

        // Save original state for error recovery
        const originalListHTML = listContainer ? listContainer.innerHTML : '';
        const originalBadgeDisplay = badge ? badge.style.display : '';
        const originalMobileHTML = mobileList ? mobileList.innerHTML : '';

        // OPTIMISTIC UI: Immediately clear the lists and badge
        const loadingHTML = '<div style="padding:20px;text-align:center;color:#65676b;">Marking all read...</div>';
        if (listContainer) {
            listContainer.innerHTML = loadingHTML;
        }
        if (mobileList) {
            mobileList.innerHTML = loadingHTML;
        }
        if (badge) {
            badge.style.display = 'none';
        }
        // Hide mobile badges
        document.querySelectorAll('.mobile-tab-badge, .nexus-notif-badge').forEach(b => {
            b.style.display = 'none';
        });

        // UI Feedback on button (if provided)
        let originalText = '';
        if (btn) {
            originalText = btn.textContent;
            btn.textContent = 'Processing...';
            btn.style.opacity = '0.6';
        }

        try {
            console.log("NexusNotifications: Send POST to /api/notifications/read [ALL]");

            const headers = {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            };
            if (this.csrfToken) {
                headers['X-CSRF-Token'] = this.csrfToken;
            }

            const body = new URLSearchParams();
            body.append('all', 'true');

            const response = await fetch(`${this.basePath}/api/notifications/read`, {
                method: 'POST',
                headers: headers,
                body: body
            });

            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error("Server returned non-JSON:", text);
                throw new Error("Server error (Invalid JSON response)");
            }

            if (response.ok && data.success) {
                console.log("NexusNotifications: Success. Reloading.");
                // Show empty state immediately before reload
                const emptyHTML = '<div style="padding: 30px; text-align: center; color: #94a3b8;"><div style="font-size: 1.5rem; margin-bottom: 5px; opacity: 0.5;"><i class="fa-regular fa-bell-slash"></i></div><div style="font-size: 0.9rem;">No notifications</div></div>';
                if (listContainer) {
                    listContainer.innerHTML = emptyHTML;
                }
                if (mobileList) {
                    mobileList.innerHTML = '<div class="mobile-notification-empty"><i class="fa-regular fa-bell-slash"></i><p>No notifications yet</p></div>';
                }
                if (btn) {
                    btn.textContent = 'Done!';
                }
                window.location.reload();
            } else {
                throw new Error(data.error || `Server responded with ${response.status}`);
            }
        } catch (error) {
            console.error("NexusNotifications Error:", error);
            alert("Unable to mark notifications as read. " + error.message);
            // Restore original state on error
            if (listContainer) {
                listContainer.innerHTML = originalListHTML;
            }
            if (mobileList) {
                mobileList.innerHTML = originalMobileHTML;
            }
            if (badge) {
                badge.style.display = originalBadgeDisplay;
            }
            if (btn) {
                btn.textContent = originalText;
                btn.style.opacity = '1.0';
            }
            this.isProcessing = false;
        }
    }

    markOneRead(id) {
        if (!id) return;

        const headers = {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        };
        if (this.csrfToken) {
            headers['X-CSRF-Token'] = this.csrfToken;
        }

        const body = new URLSearchParams();
        body.append('id', id);

        // Uses keepalive to ensure request completes even if page navigates
        fetch(`${this.basePath}/api/notifications/read`, {
            method: 'POST',
            keepalive: true,
            headers: headers,
            body: body
        }).catch(err => console.warn("Failed to mark notification read:", err));
    }

    startPolling() {
        // Don't start if already polling or Pusher is connected
        if (this.pollingActive || this.pusherConnected) return;

        this.pollingActive = true;
        console.log("NexusNotifications: Starting polling (interval: " + this.pollInterval + "ms)");

        this.pollTimer = setInterval(() => {
            // Stop polling if Pusher connects
            if (this.pusherConnected) {
                this.stopPolling();
                return;
            }

            if (document.hidden) return;

            fetch(`${this.basePath}/api/notifications/poll`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        this.updateBadge(data.count);
                    }
                })
                .catch(e => console.warn("NexusNotifications Poll Error:", e));
        }, this.pollInterval);
    }

    updateBadge(count) {
        const bellIcon = document.querySelector('.nexus-utility-bar .fa-bell');
        if (!bellIcon) return;

        const container = bellIcon.parentNode;
        let badge = container.querySelector('span');

        if (count > 0) {
            const displayCount = count > 9 ? '9+' : count;
            if (badge) {
                badge.textContent = displayCount;
                badge.style.display = 'flex';
            } else {
                const s = document.createElement('span');
                s.style.cssText = 'position: absolute; top: -6px; right: -6px; background: #ef4444; color: white; border-radius: 50%; min-width: 16px; height: 16px; padding: 0 4px; font-size: 0.65rem; display: flex; align-items: center; justify-content: center; font-weight: 700; border: 2px solid rgba(0,0,0,0.1);';
                s.textContent = displayCount;
                container.appendChild(s);
            }
        } else {
            if (badge) badge.style.display = 'none';
        }
    }
}

// Bootstrap
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.nexusNotifications = new NexusNotifications();
    });
} else {
    window.nexusNotifications = new NexusNotifications();
}