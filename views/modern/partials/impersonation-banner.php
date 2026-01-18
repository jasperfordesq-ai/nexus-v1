<?php
/**
 * Admin Impersonation Banner
 * Shows when an admin is logged in as another user
 */

if (!empty($_SESSION['is_impersonating'])):
    $basePath = \Nexus\Core\TenantContext::getBasePath();
    $impersonatedUserName = $_SESSION['user_name'] ?? 'Unknown User';
    $adminName = $_SESSION['impersonating_as_admin_name'] ?? 'Admin';
?>
<div id="impersonation-banner" class="impersonation-banner">
    <div class="impersonation-banner-content">
        <div class="impersonation-banner-icon">
            <i class="fa-solid fa-user-secret"></i>
        </div>
        <div class="impersonation-banner-text">
            <strong>You are currently logged in as <?= htmlspecialchars($impersonatedUserName) ?></strong>
            <span class="impersonation-banner-subtext">Viewing the platform as this user would see it</span>
        </div>
    </div>
    <div class="impersonation-banner-actions">
        <a href="<?= $basePath ?>/admin/stop-impersonating" class="impersonation-banner-btn impersonation-exit-btn">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Exit & Return to <?= htmlspecialchars($adminName) ?></span>
        </a>
    </div>
</div>

<style>
.impersonation-banner {
    position: fixed;
    top: 56px; /* Below the nexus-utility-bar */
    left: 0;
    right: 0;
    z-index: 9999;
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    border-bottom: 2px solid rgba(245, 158, 11, 0.4);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    padding: 12px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        transform: translateY(-100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.impersonation-banner-content {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

.impersonation-banner-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
    flex-shrink: 0;
}

.impersonation-banner-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
    color: white;
}

.impersonation-banner-text strong {
    font-size: 0.95rem;
    font-weight: 600;
    color: white;
}

.impersonation-banner-subtext {
    font-size: 0.8rem;
    opacity: 0.9;
    color: rgba(255, 255, 255, 0.95);
}

.impersonation-banner-actions {
    display: flex;
    gap: 0.75rem;
    align-items: center;
}

.impersonation-banner-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.impersonation-exit-btn {
    background: rgba(255, 255, 255, 0.95);
    color: #d97706;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.impersonation-exit-btn:hover {
    background: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.impersonation-exit-btn i {
    font-size: 0.9rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .impersonation-banner {
        flex-direction: column;
        align-items: stretch;
        padding: 12px 16px;
        gap: 0.75rem;
    }

    .impersonation-banner-content {
        gap: 0.75rem;
    }

    .impersonation-banner-icon {
        width: 36px;
        height: 36px;
        font-size: 1rem;
    }

    .impersonation-banner-text strong {
        font-size: 0.875rem;
    }

    .impersonation-banner-subtext {
        font-size: 0.75rem;
    }

    .impersonation-banner-actions {
        width: 100%;
    }

    .impersonation-banner-btn {
        width: 100%;
        justify-content: center;
        padding: 0.625rem 1rem;
    }

    .impersonation-banner-btn span {
        font-size: 0.8125rem;
    }
}

@media (max-width: 480px) {
    .impersonation-banner-btn span {
        display: none;
    }

    .impersonation-banner-btn::after {
        content: 'Exit Impersonation';
    }
}

/* Adjust page content to account for banner */
.impersonation-banner + * {
    margin-top: 60px; /* Height of banner */
}

/* Dark mode adjustments */
[data-theme="dark"] .impersonation-banner {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

[data-theme="dark"] .impersonation-exit-btn {
    background: rgba(255, 255, 255, 0.9);
    color: #d97706;
}

/* Light mode adjustments */
[data-theme="light"] .impersonation-banner {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    border-bottom-color: rgba(251, 191, 36, 0.4);
}

[data-theme="light"] .impersonation-exit-btn {
    background: white;
    color: #d97706;
    border-color: rgba(217, 119, 6, 0.2);
}
</style>
<?php endif; ?>
