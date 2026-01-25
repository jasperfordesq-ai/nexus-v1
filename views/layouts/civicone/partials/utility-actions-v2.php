<?php
/**
 * CivicOne Utility Actions v2
 *
 * COMPLETE REBUILD - Clean, minimal implementation
 *
 * NO LAYOUT SWITCHER - Removed completely from CivicOne
 *
 * Includes:
 * - Platform switcher (god users only)
 * - Contrast toggle
 * - Messages link
 * - Notifications button
 * - Account dropdown
 * - Sign in / Register (logged out)
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Auth;
use Nexus\Services\NotificationService;
use Nexus\Services\MessageService;

// Get current user and tenant
$user = Auth::user();
$isLoggedIn = !empty($user);
$basePath = TenantContext::getBasePath();

// Get unread counts if logged in
$msgUnread = 0;
$notifUnread = 0;
if ($isLoggedIn) {
    $msgUnread = MessageService::getUnreadCount($user['id'] ?? 0);
    $notifUnread = NotificationService::getUnreadCount($user['id'] ?? 0);
}

// Check if user is god (can switch platforms)
$isGod = !empty($_SESSION['is_god']);

// Check if user is admin
$isAdmin = !empty($user['is_admin']) || !empty($_SESSION['is_admin']);

// Get available tenants for god users
$tenants = [];
if ($isGod) {
    $tenants = \Nexus\Models\Tenant::getAll();
}
?>
<div class="civicone-utility-bar">
    <div class="govuk-width-container">
        <div class="civicone-utility-bar__container">

            <?php if ($isGod && !empty($tenants)): ?>
            <!-- Platform Switcher (God Users Only) -->
            <div class="civicone-utility-dropdown">
                <button type="button"
                        class="civicone-utility-btn"
                        aria-expanded="false"
                        aria-controls="platform-dropdown"
                        aria-haspopup="listbox">
                    Platform
                    <svg class="civicone-chevron" width="10" height="6" viewBox="0 0 10 6" aria-hidden="true" focusable="false">
                        <path fill="currentColor" d="M0 0h10L5 6z"/>
                    </svg>
                </button>
                <ul class="civicone-utility-dropdown__panel" id="platform-dropdown" role="listbox" hidden>
                    <?php foreach ($tenants as $tenant): ?>
                    <li role="option">
                        <a href="/<?= htmlspecialchars($tenant['slug']) ?>/">
                            <?= htmlspecialchars($tenant['name']) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Contrast Toggle -->
            <button type="button"
                    id="contrast-toggle"
                    class="civicone-utility-btn"
                    aria-pressed="false">
                <span aria-hidden="true">&#9680;</span>
                Contrast
            </button>

            <?php if ($isLoggedIn): ?>
            <!-- Messages -->
            <a href="<?= $basePath ?>/messages" class="civicone-utility-link">
                Messages
                <?php if ($msgUnread > 0): ?>
                <span class="civicone-badge" aria-label="<?= $msgUnread ?> unread messages">
                    <?= $msgUnread > 99 ? '99+' : $msgUnread ?>
                </span>
                <?php endif; ?>
            </a>

            <!-- Notifications -->
            <button type="button"
                    class="civicone-utility-btn"
                    data-action="open-notifications"
                    aria-label="Open notifications<?= $notifUnread > 0 ? ', ' . $notifUnread . ' unread' : '' ?>">
                Notifications
                <?php if ($notifUnread > 0): ?>
                <span class="civicone-badge" id="notif-badge" aria-hidden="true">
                    <?= $notifUnread > 99 ? '99+' : $notifUnread ?>
                </span>
                <?php endif; ?>
            </button>

            <!-- Account Dropdown -->
            <div class="civicone-utility-dropdown">
                <button type="button"
                        class="civicone-utility-btn"
                        aria-expanded="false"
                        aria-controls="account-dropdown"
                        aria-haspopup="menu">
                    <?php if (!empty($user['avatar'])): ?>
                    <img src="<?= htmlspecialchars($user['avatar']) ?>"
                         alt=""
                         class="civicone-utility-avatar"
                         width="24"
                         height="24">
                    <?php endif; ?>
                    <?= htmlspecialchars($user['first_name'] ?? 'Account') ?>
                    <svg class="civicone-chevron" width="10" height="6" viewBox="0 0 10 6" aria-hidden="true" focusable="false">
                        <path fill="currentColor" d="M0 0h10L5 6z"/>
                    </svg>
                </button>
                <ul class="civicone-utility-dropdown__panel" id="account-dropdown" role="menu" hidden>
                    <li role="none">
                        <a href="<?= $basePath ?>/profile/<?= $user['id'] ?? '' ?>" role="menuitem">My profile</a>
                    </li>
                    <li role="none">
                        <a href="<?= $basePath ?>/dashboard" role="menuitem">Dashboard</a>
                    </li>
                    <li role="none">
                        <a href="<?= $basePath ?>/wallet" role="menuitem">Wallet</a>
                    </li>
                    <li role="none">
                        <a href="<?= $basePath ?>/settings" role="menuitem">Settings</a>
                    </li>
                    <?php if ($isAdmin): ?>
                    <li class="civicone-utility-dropdown__sep" role="separator"></li>
                    <li role="none">
                        <a href="<?= $basePath ?>/admin" role="menuitem">Admin panel</a>
                    </li>
                    <?php endif; ?>
                    <li class="civicone-utility-dropdown__sep" role="separator"></li>
                    <li role="none">
                        <a href="<?= $basePath ?>/logout" role="menuitem">Sign out</a>
                    </li>
                </ul>
            </div>

            <?php else: ?>
            <!-- Sign In / Register -->
            <a href="<?= $basePath ?>/login" class="civicone-utility-link">Sign in</a>
            <a href="<?= $basePath ?>/register" class="civicone-utility-link civicone-utility-link--cta">Create account</a>
            <?php endif; ?>

        </div>
    </div>
</div>
