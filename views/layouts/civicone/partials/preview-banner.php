<?php
/**
 * Layout Preview Mode Banner
 * Shows when user is previewing a layout temporarily
 */

if (empty($_SESSION['layout_preview_mode'])) {
    return; // Not in preview mode
}

$previewLayoutName = $_SESSION['layout_preview_name'] ?? 'Unknown Layout';
$currentUrl = $_SERVER['REQUEST_URI'] ?? '/';
$baseUrl = \Nexus\Core\TenantContext::getBasePath();
?>

<div id="layoutPreviewBanner" style="
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 99999;
    background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
    color: white;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
    animation: slideDown 0.3s ease;
">
    <div style="display: flex; align-items: center; gap: 12px;">
        <i class="fa-solid fa-eye" style="font-size: 18px; opacity: 0.9;"></i>
        <div>
            <div style="font-weight: 700; font-size: 14px;">
                Previewing: <?= htmlspecialchars($previewLayoutName) ?>
            </div>
            <div style="font-size: 11px; opacity: 0.85;">
                This is temporary and won't be saved
            </div>
        </div>
    </div>

    <div style="display: flex; gap: 8px; margin-left: auto;">
        <a href="?layout=<?= layout() ?>"
           style="padding: 8px 16px; background: rgba(255,255,255,0.2); border-radius: 8px; color: white; text-decoration: none; font-size: 13px; font-weight: 600; border: 1px solid rgba(255,255,255,0.3); transition: all 0.2s;">
            <i class="fa-solid fa-check"></i> Keep This Layout
        </a>
        <a href="<?= $baseUrl ?>"
           style="padding: 8px 16px; background: rgba(255,255,255,0.15); border-radius: 8px; color: white; text-decoration: none; font-size: 13px; font-weight: 600; border: 1px solid rgba(255,255,255,0.2); transition: all 0.2s;">
            <i class="fa-solid fa-xmark"></i> Cancel
        </a>
    </div>
</div>

<style>
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

#layoutPreviewBanner a:hover {
    background: rgba(255,255,255,0.3) !important;
    transform: translateY(-1px);
}

/* Push body content down when banner is visible */
body {
    padding-top: 48px;
}

/* Adjust header positions */
.nexus-utility-bar {
    top: 48px !important;
}

.nexus-navbar {
    top: 104px !important; /* 48px banner + 56px utility bar */
}

.nexus-navbar.scrolled {
    top: 96px !important; /* 48px banner + 48px scrolled utility bar */
}
</style>
