<?php
/**
 * Federation Real-time Notifications Component
 *
 * Include this partial in federation pages to enable real-time notifications.
 * Uses Pusher when configured, falls back to SSE (Server-Sent Events).
 *
 * Usage: <?php require __DIR__ . '/../partials/federation-realtime.php'; ?>
 */

use Nexus\Services\FederationRealtimeService;
use Nexus\Services\PusherService;
use Nexus\Core\TenantContext;
use Nexus\Core\Auth;

$currentUser = Auth::user();
if (!$currentUser || empty($currentUser['id'])) return;

$tenantId = TenantContext::getId();
if (!$tenantId) return;

$connectionMethod = FederationRealtimeService::getConnectionMethod() ?? 'sse';
$pusherConfig = ($connectionMethod === 'pusher') ? PusherService::getConfig() : null;
$userChannel = FederationRealtimeService::getUserFederationChannel($currentUser['id'], $tenantId) ?? '';
?>

<!-- Federation Real-time Toast Container -->
<div class="fed-toast-container" id="fedToastContainer" role="alert" aria-live="polite"></div>

<!-- Federation Realtime CSS -->
<link rel="stylesheet" href="/assets/css/federation-realtime.min.css">

<!-- Connection Indicator -->
<div class="fed-connection-indicator" id="fedConnectionIndicator">
    <span class="pulse"></span>
    <span class="status-text">Connecting...</span>
</div>

<script>
/**
 * Federation Real-time Notification System
 */
(function() {
    'use strict';

    const CONFIG = {
        method: '<?= htmlspecialchars($connectionMethod ?? 'sse') ?>',
        userId: <?= (int)($currentUser['id'] ?? 0) ?>,
        tenantId: <?= (int)($tenantId ?? 0) ?>,
        <?php if ($connectionMethod === 'pusher' && $pusherConfig): ?>
        pusher: {
            key: '<?= htmlspecialchars($pusherConfig['key'] ?? '') ?>',
            cluster: '<?= htmlspecialchars($pusherConfig['cluster'] ?? '') ?>',
            channel: '<?= htmlspecialchars($userChannel ?? '') ?>'
        },
        <?php endif; ?>
        sseEndpoint: '/federation/stream',
        maxToasts: 5,
        toastDuration: 8000
    };

    let connection = null;
    let reconnectAttempts = 0;
    const maxReconnectAttempts = 5;
    const toastContainer = document.getElementById('fedToastContainer');
    const connectionIndicator = document.getElementById('fedConnectionIndicator');

    /**
     * Initialize real-time connection
     */
    function init() {
        if (CONFIG.method === 'pusher' && typeof Pusher !== 'undefined') {
            initPusher();
        } else {
            initSSE();
        }
    }

    /**
     * Initialize Pusher connection
     */
    function initPusher() {
        if (!CONFIG.pusher) {
            console.warn('[FedRealtime] Pusher config missing, falling back to SSE');
            initSSE();
            return;
        }

        updateConnectionStatus('connecting');

        const pusher = new Pusher(CONFIG.pusher.key, {
            cluster: CONFIG.pusher.cluster,
            authEndpoint: '/federation/pusher/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            }
        });

        const channel = pusher.subscribe(CONFIG.pusher.channel);

        channel.bind('pusher:subscription_succeeded', () => {
            updateConnectionStatus('connected');
            reconnectAttempts = 0;
        });

        channel.bind('pusher:subscription_error', (error) => {
            console.error('[FedRealtime] Pusher subscription error:', error);
            updateConnectionStatus('disconnected');
        });

        // Bind to federation events
        channel.bind('federation.new-message', (data) => showToast('message', data));
        channel.bind('federation.transaction', (data) => showToast('transaction', data));
        channel.bind('federation.partnership-update', (data) => showToast('partnership', data));
        channel.bind('federation.activity', (data) => showToast('activity', data));
        channel.bind('federation.member-joined', (data) => showToast('activity', data));

        pusher.connection.bind('connected', () => updateConnectionStatus('connected'));
        pusher.connection.bind('disconnected', () => updateConnectionStatus('disconnected'));
        pusher.connection.bind('error', () => updateConnectionStatus('disconnected'));

        connection = pusher;
    }

    /**
     * Initialize SSE connection
     */
    function initSSE() {
        updateConnectionStatus('connecting');

        const eventSource = new EventSource(CONFIG.sseEndpoint);

        eventSource.addEventListener('connected', (e) => {
            updateConnectionStatus('connected');
            reconnectAttempts = 0;
            console.log('[FedRealtime] SSE connected');
        });

        eventSource.addEventListener('heartbeat', (e) => {
            // Keep connection alive indicator
        });

        eventSource.addEventListener('reconnect', (e) => {
            eventSource.close();
            setTimeout(initSSE, 1000);
        });

        // Federation events
        eventSource.addEventListener('federation.new-message', (e) => {
            showToast('message', JSON.parse(e.data));
        });

        eventSource.addEventListener('federation.transaction', (e) => {
            showToast('transaction', JSON.parse(e.data));
        });

        eventSource.addEventListener('partnership.update', (e) => {
            showToast('partnership', JSON.parse(e.data));
        });

        eventSource.addEventListener('activity', (e) => {
            showToast('activity', JSON.parse(e.data));
        });

        eventSource.addEventListener('member.joined', (e) => {
            showToast('activity', JSON.parse(e.data));
        });

        eventSource.addEventListener('test', (e) => {
            showToast('activity', JSON.parse(e.data));
        });

        eventSource.onerror = () => {
            updateConnectionStatus('disconnected');
            eventSource.close();

            if (reconnectAttempts < maxReconnectAttempts) {
                reconnectAttempts++;
                const delay = Math.min(1000 * Math.pow(2, reconnectAttempts), 30000);
                console.log(`[FedRealtime] Reconnecting in ${delay}ms (attempt ${reconnectAttempts})`);
                setTimeout(initSSE, delay);
            }
        };

        connection = eventSource;
    }

    /**
     * Update connection status indicator
     */
    function updateConnectionStatus(status) {
        connectionIndicator.className = 'fed-connection-indicator visible ' + status;
        const statusText = connectionIndicator.querySelector('.status-text');

        switch (status) {
            case 'connecting':
                statusText.textContent = 'Connecting...';
                break;
            case 'connected':
                statusText.textContent = 'Live updates active';
                // Hide after 3 seconds when connected
                setTimeout(() => {
                    connectionIndicator.classList.remove('visible');
                }, 3000);
                break;
            case 'disconnected':
                statusText.textContent = 'Reconnecting...';
                break;
        }
    }

    /**
     * Show toast notification
     */
    function showToast(type, data) {
        // Limit number of toasts
        const existingToasts = toastContainer.querySelectorAll('.fed-toast');
        if (existingToasts.length >= CONFIG.maxToasts) {
            removeToast(existingToasts[0]);
        }

        const toast = document.createElement('div');
        toast.className = 'fed-toast';
        toast.setAttribute('role', 'alert');

        const iconClass = getIconClass(type);
        const { title, body, link } = formatToastContent(type, data);

        toast.innerHTML = `
            <div class="fed-toast-icon ${type}">
                <i class="fa-solid ${iconClass}"></i>
            </div>
            <div class="fed-toast-content">
                <div class="fed-toast-title">
                    ${title}
                    <span class="fed-toast-badge">Federation</span>
                </div>
                <div class="fed-toast-body">${body}</div>
                <div class="fed-toast-time">Just now</div>
            </div>
            <button class="fed-toast-close" aria-label="Dismiss">
                <i class="fa-solid fa-times"></i>
            </button>
        `;

        if (link) {
            toast.style.cursor = 'pointer';
            toast.addEventListener('click', (e) => {
                if (!e.target.closest('.fed-toast-close')) {
                    window.location.href = link;
                }
            });
        }

        toast.querySelector('.fed-toast-close').addEventListener('click', (e) => {
            e.stopPropagation();
            removeToast(toast);
        });

        toastContainer.appendChild(toast);

        // Play notification sound if available
        playNotificationSound();

        // Vibrate on mobile
        if (navigator.vibrate) {
            navigator.vibrate([100, 50, 100]);
        }

        // Auto-remove after duration
        setTimeout(() => removeToast(toast), CONFIG.toastDuration);
    }

    /**
     * Remove toast with animation
     */
    function removeToast(toast) {
        if (!toast || toast.classList.contains('removing')) return;
        toast.classList.add('removing');
        setTimeout(() => toast.remove(), 300);
    }

    /**
     * Get icon class for toast type
     */
    function getIconClass(type) {
        switch (type) {
            case 'message': return 'fa-envelope';
            case 'transaction': return 'fa-coins';
            case 'partnership': return 'fa-handshake';
            case 'activity':
            default: return 'fa-bolt';
        }
    }

    /**
     * Format toast content based on event type
     */
    function formatToastContent(type, data) {
        let title = 'Federation Update';
        let body = data.message || 'New activity in your federation network';
        let link = '/federation/activity';

        switch (type) {
            case 'message':
                title = data.sender_name || 'New Message';
                body = data.subject || data.preview || 'You received a new federated message';
                link = '/federation/messages';
                break;

            case 'transaction':
                title = 'Time Credits Received';
                body = `${data.sender_name || 'Someone'} sent you ${data.amount || '?'} hour(s)`;
                link = '/federation/transactions';
                break;

            case 'partnership':
                title = 'Partnership Update';
                body = `${data.partner_name || 'A timebank'} - Status: ${data.status || 'updated'}`;
                link = '/federation';
                break;

            case 'activity':
                if (data.event_type === 'member.joined') {
                    title = 'New Federation Member';
                    body = `${data.user_name || 'Someone'} joined the federation network`;
                }
                break;
        }

        return { title, body, link };
    }

    /**
     * Play notification sound
     */
    function playNotificationSound() {
        // Check if sounds are enabled in user preferences
        if (localStorage.getItem('fedNotificationSound') === 'off') return;

        try {
            const audio = new Audio('/assets/sounds/notification.mp3');
            audio.volume = 0.3;
            audio.play().catch(() => {}); // Ignore autoplay restrictions
        } catch (e) {
            // Sound not available
        }
    }

    /**
     * Expose API for manual notifications
     */
    window.FedRealtime = {
        showToast: showToast,
        getConnectionStatus: () => connection ? 'connected' : 'disconnected'
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        if (connection) {
            if (typeof connection.close === 'function') {
                connection.close();
            } else if (typeof connection.disconnect === 'function') {
                connection.disconnect();
            }
        }
    });
})();
</script>
