<?php
/**
 * Federation Real-time Notifications Component - CivicOne Version
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
<div class="fed-connection-indicator" id="fedConnectionIndicator" role="status" aria-live="polite">
    <span class="pulse" aria-hidden="true"></span>
    <span class="status-text">Connecting...</span>
</div>

<!-- Federation Realtime Configuration -->
<script>
window.FedRealtimeConfig = {
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
</script>
<script src="/assets/js/federation-realtime.js"></script>
