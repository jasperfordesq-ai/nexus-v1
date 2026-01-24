/**
 * NexusPusher - Real-time WebSocket client for Project NEXUS
 *
 * Provides real-time notifications, messages, presence, and typing indicators
 * using Pusher Channels. Falls back to polling if Pusher is unavailable.
 */
class NexusPusher {
    constructor(options = {}) {
        this.pusher = null;
        this.userChannel = null;
        this.presenceChannel = null;
        this.chatChannels = {};
        this.config = null;
        this.connected = false;
        this.userId = null;
        this.onlineUsers = new Map();

        // Event handlers
        this.handlers = {
            notification: [],
            message: [],
            'new-message': [],
            'unread-count': [],
            'notifications-read': [],
            typing: [],
            presence: [],
            connection: [],
        };

        // Options
        this.debug = options.debug || false;
        this.autoConnect = options.autoConnect !== false;
        this.fallbackToPolling = options.fallbackToPolling !== false;

        // Initialize
        if (this.autoConnect) {
            this.init();
        }
    }

    /**
     * Initialize Pusher connection
     */
    async init() {
        try {
            // Fetch config from server
            const response = await fetch('/api/pusher/config');
            if (!response.ok) {
                throw new Error('Failed to fetch Pusher config');
            }

            this.config = await response.json();

            if (!this.config.enabled || !this.config.key) {
                this.log('Pusher not configured, falling back to polling');
                this.triggerFallback();
                return;
            }

            this.userId = this.config.userId;

            // Check if Pusher JS is loaded
            if (typeof Pusher === 'undefined') {
                this.log('Pusher JS not loaded, falling back to polling');
                this.triggerFallback();
                return;
            }

            // Initialize Pusher
            this.pusher = new Pusher(this.config.key, {
                cluster: this.config.cluster,
                authEndpoint: this.config.authEndpoint,
                auth: {
                    headers: {
                        'X-CSRF-Token': this.getCsrfToken(),
                    }
                }
            });

            // Connection event handlers
            this.pusher.connection.bind('connected', () => {
                this.connected = true;
                this.log('Connected to Pusher');
                this.emit('connection', { status: 'connected' });
            });

            this.pusher.connection.bind('disconnected', () => {
                this.connected = false;
                this.log('Disconnected from Pusher');
                this.emit('connection', { status: 'disconnected' });
            });

            this.pusher.connection.bind('error', (error) => {
                this.log('Pusher error:', error);
                this.emit('connection', { status: 'error', error });
                if (this.fallbackToPolling) {
                    this.triggerFallback();
                }
            });

            // Subscribe to channels
            this.subscribeToUserChannel();
            this.subscribeToPresenceChannel();

        } catch (error) {
            this.log('Pusher init error:', error);
            if (this.fallbackToPolling) {
                this.triggerFallback();
            }
        }
    }

    /**
     * Subscribe to user's private notification channel
     */
    subscribeToUserChannel() {
        if (!this.config?.channels?.user) return;

        this.userChannel = this.pusher.subscribe(this.config.channels.user);

        this.userChannel.bind('pusher:subscription_succeeded', () => {
            this.log('Subscribed to user channel');
        });

        this.userChannel.bind('pusher:subscription_error', (error) => {
            this.log('User channel subscription error:', error);
        });

        // Notification events
        this.userChannel.bind('notification', (data) => {
            this.log('Received notification:', data);
            this.emit('notification', data);
        });

        // New message notification
        this.userChannel.bind('new-message', (data) => {
            this.log('Received new message notification:', data);
            this.emit('new-message', data);
        });

        // Unread count update
        this.userChannel.bind('unread-count', (data) => {
            this.log('Received unread count:', data);
            this.emit('unread-count', data);
        });

        // Notifications marked as read
        this.userChannel.bind('notifications-read', (data) => {
            this.log('Notifications marked as read:', data);
            this.emit('notifications-read', data);
        });
    }

    /**
     * Subscribe to presence channel for online users
     */
    subscribeToPresenceChannel() {
        if (!this.config?.channels?.presence) return;

        this.presenceChannel = this.pusher.subscribe(this.config.channels.presence);

        this.presenceChannel.bind('pusher:subscription_succeeded', (members) => {
            this.log('Subscribed to presence channel');
            this.onlineUsers.clear();
            members.each((member) => {
                this.onlineUsers.set(member.id, member.info);
            });
            this.emit('presence', {
                type: 'init',
                users: Array.from(this.onlineUsers.entries())
            });
        });

        this.presenceChannel.bind('pusher:member_added', (member) => {
            this.log('User came online:', member);
            this.onlineUsers.set(member.id, member.info);
            this.emit('presence', {
                type: 'added',
                userId: member.id,
                userInfo: member.info
            });
        });

        this.presenceChannel.bind('pusher:member_removed', (member) => {
            this.log('User went offline:', member);
            this.onlineUsers.delete(member.id);
            this.emit('presence', {
                type: 'removed',
                userId: member.id
            });
        });
    }

    /**
     * Subscribe to a chat channel for real-time messages
     */
    subscribeToChatChannel(otherUserId) {
        if (!this.pusher || !this.userId) return null;

        const chatId = Math.min(this.userId, otherUserId) + '-' + Math.max(this.userId, otherUserId);
        const channelName = `private-tenant.${this.getTenantId()}.chat.${chatId}`;

        if (this.chatChannels[chatId]) {
            return this.chatChannels[chatId];
        }

        const channel = this.pusher.subscribe(channelName);

        channel.bind('pusher:subscription_succeeded', () => {
            this.log('Subscribed to chat channel:', chatId);
        });

        channel.bind('message', (data) => {
            this.log('Received chat message:', data);
            this.emit('message', data);
        });

        channel.bind('typing', (data) => {
            this.log('Typing indicator:', data);
            this.emit('typing', data);
        });

        this.chatChannels[chatId] = channel;
        return channel;
    }

    /**
     * Unsubscribe from a chat channel
     */
    unsubscribeFromChatChannel(otherUserId) {
        const chatId = Math.min(this.userId, otherUserId) + '-' + Math.max(this.userId, otherUserId);

        if (this.chatChannels[chatId]) {
            this.pusher.unsubscribe(`private-tenant.${this.getTenantId()}.chat.${chatId}`);
            delete this.chatChannels[chatId];
        }
    }

    /**
     * Send typing indicator
     */
    sendTyping(otherUserId, isTyping = true) {
        const chatId = Math.min(this.userId, otherUserId) + '-' + Math.max(this.userId, otherUserId);
        const channel = this.chatChannels[chatId];

        if (channel) {
            channel.trigger('client-typing', {
                user_id: this.userId,
                is_typing: isTyping
            });
        }
    }

    /**
     * Check if a user is online
     */
    isUserOnline(userId) {
        return this.onlineUsers.has(userId);
    }

    /**
     * Get all online users
     */
    getOnlineUsers() {
        return Array.from(this.onlineUsers.entries()).map(([id, info]) => ({
            id,
            ...info
        }));
    }

    /**
     * Register event handler
     */
    on(event, handler) {
        if (this.handlers[event]) {
            this.handlers[event].push(handler);
        }
        return this;
    }

    /**
     * Remove event handler
     */
    off(event, handler) {
        if (this.handlers[event]) {
            const index = this.handlers[event].indexOf(handler);
            if (index > -1) {
                this.handlers[event].splice(index, 1);
            }
        }
        return this;
    }

    /**
     * Emit event to handlers
     */
    emit(event, data) {
        if (this.handlers[event]) {
            this.handlers[event].forEach(handler => {
                try {
                    handler(data);
                } catch (e) {
                    console.error('Event handler error:', e);
                }
            });
        }
    }

    /**
     * Trigger fallback to polling
     */
    triggerFallback() {
        this.emit('connection', { status: 'fallback' });
        // The notifications.js will handle the fallback
        if (typeof window.NexusNotifications !== 'undefined' && window.nexusNotifications) {
            window.nexusNotifications.startPolling();
        }
    }

    /**
     * Get CSRF token from meta tag
     */
    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    /**
     * Get tenant ID from page context
     */
    getTenantId() {
        // Try to get from config first, then from page
        if (window.NEXUS_CONFIG?.tenantId) {
            return window.NEXUS_CONFIG.tenantId;
        }
        const meta = document.querySelector('meta[name="tenant-id"]');
        return meta ? meta.getAttribute('content') : '1';
    }

    /**
     * Log debug messages
     */
    log(...args) {
        if (this.debug) {
            console.warn('[NexusPusher]', ...args);
        }
    }

    /**
     * Disconnect from Pusher
     */
    disconnect() {
        if (this.pusher) {
            this.pusher.disconnect();
            this.connected = false;
            this.userChannel = null;
            this.presenceChannel = null;
            this.chatChannels = {};
        }
    }

    /**
     * Reconnect to Pusher
     */
    reconnect() {
        this.disconnect();
        this.init();
    }

    /**
     * Check if connected
     */
    isConnected() {
        return this.connected;
    }
}

// Auto-initialize immediately (script has defer attribute, so DOM is ready)
// This script is only loaded for logged-in users (via PHP session check in footer)
(function() {
    // Initialize if NEXUS_CONFIG has userId (set by footer for logged-in users)
    if (window.NEXUS_CONFIG?.userId) {
        console.warn('[NexusPusher] Initializing for user:', window.NEXUS_CONFIG.userId);
        window.nexusPusher = new NexusPusher({
            debug: window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
        });
    } else {
        console.warn('[NexusPusher] No userId in NEXUS_CONFIG, skipping init');
    }
})();

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NexusPusher;
}
