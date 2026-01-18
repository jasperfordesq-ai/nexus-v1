<?php
/**
 * Messages Index - Dual Interface
 * Path: views/modern/messages/index.php
 *
 * Desktop: Holographic Glassmorphism Interface (2025)
 * Mobile: Clean, minimal fullscreen design
 */

$hTitle = 'Messages';
$hSubtitle = 'Your Conversations';
$hGradient = 'htb-hero-gradient-members';
$hType = 'Direct Messages';
$hideHero = true;

// CRITICAL: Disable all PTR and enable fullscreen mode
$bodyClass = 'no-ptr messages-page messages-fullscreen';
$hideUtilityBar = true;
$hideBottomNav = true;

require dirname(__DIR__, 2) . '/layouts/modern/header.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Cleanup function must be defined before any onclick handlers reference it -->
<script>
window.cleanupBeforeLeave = function() {
    document.documentElement.classList.remove('messages-page');
    document.body.classList.remove('messages-page', 'no-ptr', 'messages-fullscreen');
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
};
</script>

<style>
/* ============================================
   MESSAGES INDEX - Clean Mobile Fullscreen
   ============================================ */

:root {
    --msg-primary: #6366f1;
    --msg-primary-light: #818cf8;
    --msg-gradient: linear-gradient(135deg, #6366f1, #8b5cf6);
    --msg-bg: #f8fafc;
    --msg-surface: #ffffff;
    --msg-border: rgba(0, 0, 0, 0.08);
    --msg-text: #1e293b;
    --msg-text-muted: #64748b;
    --msg-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    --msg-radius: 12px;
    --safe-top: env(safe-area-inset-top, 0px);
    --safe-bottom: env(safe-area-inset-bottom, 0px);
}

[data-theme="dark"] {
    --msg-bg: #0f172a;
    --msg-surface: #1e293b;
    --msg-border: rgba(255, 255, 255, 0.1);
    --msg-text: #f1f5f9;
    --msg-text-muted: #94a3b8;
}

/* Prevent pull-to-refresh on messages page */
html.messages-page,
body.messages-page {
    overflow: hidden !important;
    overscroll-behavior: none !important;
    overscroll-behavior-y: none !important;
    -webkit-overflow-scrolling: auto !important;
    height: 100%;
    width: 100%;
    touch-action: manipulation;
}

/* Hide footer and navigation on messages page */
.messages-page .nexus-modern-footer,
.messages-page .nexus-desktop-only,
.messages-page .nexus-native-nav,
.messages-page .nexus-quick-fab,
.messages-page .nexus-bottom-sheet,
.messages-page .mobile-tab-bar,
.messages-page #mobileTabBar {
    display: none !important;
}

/* Fullscreen Container */
.messages-app {
    position: fixed;
    inset: 0;
    display: flex;
    flex-direction: column;
    background: var(--msg-bg);
    z-index: 100;
    overflow: hidden;
}

/* Header */
.messages-header {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    padding-top: calc(12px + var(--safe-top));
    background: var(--msg-surface);
    border-bottom: 1px solid var(--msg-border);
    gap: 12px;
}

.messages-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.messages-back {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: transparent;
    border: none;
    color: var(--msg-primary);
    font-size: 1.1rem;
    text-decoration: none;
    -webkit-tap-highlight-color: transparent;
}

.messages-back:active {
    background: rgba(99, 102, 241, 0.1);
}

.messages-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--msg-text);
    margin: 0;
}

.messages-count {
    font-size: 0.8rem;
    color: var(--msg-text-muted);
}

.messages-new-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: var(--msg-gradient);
    border: none;
    color: white;
    font-size: 1.1rem;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
    -webkit-tap-highlight-color: transparent;
}

.messages-new-btn:active {
    transform: scale(0.95);
}

/* Search */
.messages-search {
    flex-shrink: 0;
    padding: 12px 16px;
    background: var(--msg-surface);
}

.messages-search-input {
    width: 100%;
    padding: 12px 16px 12px 44px;
    border: 1px solid var(--msg-border);
    border-radius: var(--msg-radius);
    background: var(--msg-bg);
    font-size: 16px; /* Prevents iOS zoom */
    color: var(--msg-text);
    outline: none;
}

.messages-search-input::placeholder {
    color: var(--msg-text-muted);
}

.messages-search-input:focus {
    border-color: var(--msg-primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.messages-search-wrap {
    position: relative;
}

.messages-search-wrap i {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--msg-text-muted);
    font-size: 0.9rem;
    pointer-events: none;
}

/* Thread List */
.messages-list {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
}

.messages-thread {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    text-decoration: none;
    color: inherit;
    border-bottom: 1px solid var(--msg-border);
    background: var(--msg-surface);
    -webkit-tap-highlight-color: transparent;
}

.messages-thread:active {
    background: rgba(99, 102, 241, 0.05);
}

.messages-thread.unread {
    background: rgba(99, 102, 241, 0.08);
}

.messages-avatar {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: var(--msg-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: white;
    font-size: 1.1rem;
    flex-shrink: 0;
    overflow: hidden;
}

.messages-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.messages-content {
    flex: 1;
    min-width: 0;
}

.messages-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
}

.messages-name {
    font-weight: 600;
    font-size: 1rem;
    color: var(--msg-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.messages-thread.unread .messages-name {
    font-weight: 700;
}

.messages-time {
    font-size: 0.75rem;
    color: var(--msg-text-muted);
    flex-shrink: 0;
}

.messages-thread.unread .messages-time {
    color: var(--msg-primary);
    font-weight: 600;
}

.messages-preview {
    font-size: 0.9rem;
    color: var(--msg-text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.messages-thread.unread .messages-preview {
    color: var(--msg-text);
}

.messages-badge {
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 10px;
    background: var(--msg-gradient);
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: 8px;
    flex-shrink: 0;
}

/* Empty State */
.messages-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 24px;
    text-align: center;
}

.messages-empty-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
}

.messages-empty-icon i {
    font-size: 2rem;
    color: var(--msg-primary);
}

.messages-empty h3 {
    margin: 0 0 8px;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--msg-text);
}

.messages-empty p {
    margin: 0 0 24px;
    color: var(--msg-text-muted);
    font-size: 0.9rem;
    line-height: 1.5;
}

.messages-empty-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 24px;
    background: var(--msg-gradient);
    color: white;
    font-size: 0.95rem;
    font-weight: 600;
    border: none;
    border-radius: var(--msg-radius);
    cursor: pointer;
    text-decoration: none;
}

/* New Message Modal */
.nm-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 200;
    display: none;
    align-items: flex-end;
}

.nm-overlay.active {
    display: flex;
}

.nm-sheet {
    width: 100%;
    max-height: 85vh;
    background: var(--msg-surface);
    border-radius: 20px 20px 0 0;
    display: flex;
    flex-direction: column;
    transform: translateY(100%);
    transition: transform 0.3s ease;
}

.nm-overlay.active .nm-sheet {
    transform: translateY(0);
}

.nm-handle {
    width: 36px;
    height: 4px;
    background: var(--msg-border);
    border-radius: 2px;
    margin: 8px auto 0;
}

.nm-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid var(--msg-border);
}

.nm-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--msg-text);
}

.nm-close {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: none;
    background: var(--msg-bg);
    color: var(--msg-text-muted);
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.nm-search {
    padding: 16px 20px;
}

.nm-search input {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid var(--msg-border);
    border-radius: var(--msg-radius);
    background: var(--msg-bg);
    font-size: 16px;
    color: var(--msg-text);
    outline: none;
}

.nm-search input:focus {
    border-color: var(--msg-primary);
}

.nm-results {
    flex: 1;
    overflow-y: auto;
    padding-bottom: var(--safe-bottom);
}

.nm-user {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    cursor: pointer;
    border-bottom: 1px solid var(--msg-border);
}

.nm-user:active {
    background: var(--msg-bg);
}

.nm-user-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: var(--msg-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    overflow: hidden;
}

.nm-user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.nm-user-name {
    font-weight: 600;
    color: var(--msg-text);
}

.nm-state {
    padding: 40px 20px;
    text-align: center;
    color: var(--msg-text-muted);
}

.nm-spinner {
    width: 32px;
    height: 32px;
    border: 3px solid var(--msg-border);
    border-top-color: var(--msg-primary);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 12px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Hide scrollbars on mobile */
.messages-list::-webkit-scrollbar,
.nm-results::-webkit-scrollbar {
    display: none;
}

/* Swipe to delete */
.messages-thread-wrap {
    position: relative;
    overflow: hidden;
}

.messages-thread-actions {
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    display: flex;
    align-items: stretch;
    transform: translateX(100%);
    transition: transform 0.2s ease;
}

.messages-thread-wrap.swiped .messages-thread-actions {
    transform: translateX(0);
}

.messages-thread-wrap.swiped .messages-thread {
    transform: translateX(-80px);
}

.messages-thread {
    transition: transform 0.2s ease;
}

.msg-delete-action {
    width: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ef4444;
    color: white;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
}

.msg-delete-action:active {
    background: #dc2626;
}

/* Thread options button (for desktop) */
.messages-thread-options {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 1px solid var(--msg-border);
    background: var(--msg-surface);
    color: var(--msg-text-muted);
    font-size: 1rem;
    cursor: pointer;
    opacity: 0;
    transition: all 0.2s ease;
    display: none;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    z-index: 10;
}

/* Ensure thread content doesn't overlap with options button */
.messages-thread {
    padding-right: 60px;
}

@media (hover: hover) {
    .messages-thread-options {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .messages-thread-wrap:hover .messages-thread-options {
        opacity: 1;
    }

    .messages-thread-options:hover {
        background: var(--msg-primary);
        border-color: var(--msg-primary);
        color: white;
        transform: translateY(-50%) scale(1.1);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }

    .messages-thread-options:active {
        transform: translateY(-50%) scale(0.95);
    }
}

/* Always show options button on touch devices */
@media (hover: none) {
    .messages-thread-options {
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0.7;
    }

    .messages-thread {
        padding-right: 56px;
    }
}

/* Thread context menu */
.thread-menu-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 200;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.thread-menu-overlay.active {
    display: flex;
}

.thread-menu-sheet {
    width: 100%;
    max-width: 280px;
    background: var(--msg-surface);
    border-radius: 16px;
    overflow: hidden;
    transform: scale(0.9);
    opacity: 0;
    transition: transform 0.2s ease, opacity 0.2s ease;
}

.thread-menu-overlay.active .thread-menu-sheet {
    transform: scale(1);
    opacity: 1;
}

.thread-menu-header {
    padding: 16px;
    border-bottom: 1px solid var(--msg-border);
    font-weight: 600;
    color: var(--msg-text);
    display: flex;
    align-items: center;
    gap: 10px;
}

.thread-menu-header .messages-avatar {
    width: 36px;
    height: 36px;
    font-size: 0.9rem;
}

.thread-menu-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 14px 16px;
    border: none;
    background: transparent;
    color: var(--msg-text);
    font-size: 1rem;
    text-align: left;
    cursor: pointer;
}

.thread-menu-btn:active {
    background: var(--msg-bg);
}

.thread-menu-btn.delete {
    color: #ef4444;
}

.thread-menu-btn i {
    width: 20px;
    text-align: center;
}

/* Delete confirmation modal */
.delete-confirm-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 210;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.delete-confirm-overlay.active {
    display: flex;
}

.delete-confirm-box {
    width: 100%;
    max-width: 300px;
    background: var(--msg-surface);
    border-radius: 16px;
    padding: 24px;
    text-align: center;
}

.delete-confirm-box h4 {
    margin: 0 0 8px;
    font-size: 1.1rem;
    color: var(--msg-text);
}

.delete-confirm-box p {
    margin: 0 0 20px;
    color: var(--msg-text-muted);
    font-size: 0.9rem;
}

.delete-confirm-actions {
    display: flex;
    gap: 12px;
}

.delete-confirm-actions button {
    flex: 1;
    padding: 12px;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
}

.delete-cancel-btn {
    background: var(--msg-bg);
    color: var(--msg-text);
}

.delete-confirm-btn {
    background: #ef4444;
    color: white;
}

/* Toast */
.msg-toast {
    position: fixed;
    bottom: 100px;
    left: 50%;
    transform: translateX(-50%) translateY(20px);
    background: #1e293b;
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 0.9rem;
    z-index: 300;
    opacity: 0;
    transition: opacity 0.3s, transform 0.3s;
    pointer-events: none;
}

.msg-toast.show {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}

[data-theme="dark"] .msg-toast {
    background: #475569;
}

/* ============================================
   DESKTOP HOLOGRAPHIC GLASSMORPHISM INTERFACE
   Theme: Purple/Cyan Holographic (#8b5cf6 / #06b6d4)
   ============================================ */

/* Hide mobile interface on desktop */
@media (min-width: 769px) {
    .messages-app.mobile-interface {
        display: none !important;
    }
}

/* Hide desktop interface on mobile */
@media (max-width: 768px) {
    .messages-desktop-holo {
        display: none !important;
    }
}

/* Desktop Holographic Variables */
.messages-desktop-holo {
    --holo-primary: #8b5cf6;
    --holo-primary-rgb: 139, 92, 246;
    --holo-secondary: #06b6d4;
    --holo-secondary-rgb: 6, 182, 212;
    --holo-accent: #f472b6;
    --holo-accent-rgb: 244, 114, 182;
    --holo-success: #10b981;
    --holo-danger: #ef4444;
    --holo-text: #f1f5f9;
    --holo-text-muted: #94a3b8;
    --holo-surface: rgba(15, 23, 42, 0.6);
    --holo-surface-hover: rgba(30, 41, 59, 0.8);
    --holo-border: rgba(255, 255, 255, 0.08);
    --holo-glow: rgba(139, 92, 246, 0.4);
}

/* Animated Holographic Background */
.messages-desktop-holo {
    position: fixed;
    inset: 0;
    z-index: 100;
    overflow: hidden;
    background: linear-gradient(135deg,
        #0f0c29 0%,
        #302b63 50%,
        #24243e 100%);
}

/* Holographic Gradient Overlay */
.messages-desktop-holo::before {
    content: '';
    position: absolute;
    inset: 0;
    z-index: 0;
    background:
        radial-gradient(ellipse at 20% 0%, rgba(139, 92, 246, 0.25) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 20%, rgba(6, 182, 212, 0.2) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 100%, rgba(244, 114, 182, 0.15) 0%, transparent 50%);
    animation: holoShiftDesktop 20s ease-in-out infinite alternate;
    pointer-events: none;
}

@keyframes holoShiftDesktop {
    0% { opacity: 1; filter: hue-rotate(0deg); }
    50% { opacity: 0.85; filter: hue-rotate(10deg); }
    100% { opacity: 1; filter: hue-rotate(-10deg); }
}

/* Floating Holographic Orbs */
.holo-orb-desktop {
    position: absolute;
    border-radius: 50%;
    filter: blur(100px);
    opacity: 0.3;
    pointer-events: none;
    z-index: 0;
    animation: orbFloatDesktop 30s ease-in-out infinite;
}

.holo-orb-desktop-1 {
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(139, 92, 246, 0.5) 0%, transparent 70%);
    top: -200px;
    left: -200px;
}

.holo-orb-desktop-2 {
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(6, 182, 212, 0.4) 0%, transparent 70%);
    top: 30%;
    right: -150px;
    animation-delay: -10s;
}

.holo-orb-desktop-3 {
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(244, 114, 182, 0.35) 0%, transparent 70%);
    bottom: -100px;
    left: 30%;
    animation-delay: -20s;
}

@keyframes orbFloatDesktop {
    0%, 100% { transform: translate(0, 0) scale(1); }
    25% { transform: translate(40px, -40px) scale(1.05); }
    50% { transform: translate(-30px, 30px) scale(0.95); }
    75% { transform: translate(30px, 40px) scale(1.02); }
}

/* Main Content Container */
.holo-messages-container {
    position: relative;
    z-index: 1;
    display: flex;
    height: 100vh;
    padding: 24px;
    gap: 24px;
}

/* Sidebar - Conversation List */
.holo-sidebar {
    width: 380px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid var(--holo-border);
    border-radius: 24px;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    overflow: hidden;
    position: relative;
}

/* Holographic Border Glow for Sidebar */
.holo-sidebar::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 24px;
    padding: 1px;
    background: linear-gradient(135deg,
        rgba(139, 92, 246, 0.3) 0%,
        rgba(6, 182, 212, 0.2) 50%,
        rgba(244, 114, 182, 0.3) 100%);
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
}

/* Sidebar Header */
.holo-sidebar-header {
    padding: 24px;
    border-bottom: 1px solid var(--holo-border);
}

.holo-sidebar-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.holo-back-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--holo-border);
    color: var(--holo-text-muted);
    text-decoration: none;
    transition: all 0.3s ease;
}

.holo-back-btn:hover {
    background: rgba(139, 92, 246, 0.2);
    border-color: rgba(139, 92, 246, 0.4);
    color: var(--holo-primary);
    transform: translateX(-3px);
}

.holo-title-group h1 {
    font-size: 1.5rem;
    font-weight: 800;
    margin: 0;
    background: linear-gradient(135deg, #ffffff 0%, #c4b5fd 50%, #67e8f9 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.holo-title-group span {
    font-size: 0.85rem;
    color: var(--holo-text-muted);
}

.holo-new-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--holo-primary), var(--holo-secondary));
    border: none;
    color: white;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 20px rgba(139, 92, 246, 0.4);
}

.holo-new-btn:hover {
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 6px 30px rgba(139, 92, 246, 0.5);
}

.holo-new-btn:active {
    transform: scale(0.95);
}

/* Search Box */
.holo-search-wrap {
    position: relative;
}

.holo-search-wrap i {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--holo-text-muted);
    font-size: 0.9rem;
    pointer-events: none;
    z-index: 2;
}

.holo-search-input {
    width: 100%;
    padding: 14px 16px 14px 46px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--holo-border);
    border-radius: 14px;
    color: var(--holo-text);
    font-size: 0.95rem;
    outline: none;
    transition: all 0.3s ease;
}

.holo-search-input::placeholder {
    color: var(--holo-text-muted);
}

.holo-search-input:focus {
    background: rgba(139, 92, 246, 0.05);
    border-color: rgba(139, 92, 246, 0.4);
    box-shadow: 0 0 20px rgba(139, 92, 246, 0.15);
}

/* Thread List */
.holo-thread-list {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 12px;
}

.holo-thread-list::-webkit-scrollbar {
    width: 6px;
}

.holo-thread-list::-webkit-scrollbar-track {
    background: transparent;
}

.holo-thread-list::-webkit-scrollbar-thumb {
    background: rgba(139, 92, 246, 0.3);
    border-radius: 3px;
}

.holo-thread-list::-webkit-scrollbar-thumb:hover {
    background: rgba(139, 92, 246, 0.5);
}

/* Thread Item */
.holo-thread {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    margin-bottom: 8px;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid transparent;
    border-radius: 16px;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    transition: all 0.3s ease;
    position: relative;
}

.holo-thread:hover {
    background: rgba(139, 92, 246, 0.1);
    border-color: rgba(139, 92, 246, 0.2);
    transform: translateX(4px);
}

.holo-thread.active {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(6, 182, 212, 0.1));
    border-color: rgba(139, 92, 246, 0.3);
    box-shadow: 0 4px 20px rgba(139, 92, 246, 0.2);
}

.holo-thread.unread::before {
    content: '';
    position: absolute;
    left: 4px;
    top: 50%;
    transform: translateY(-50%);
    width: 4px;
    height: 40%;
    background: linear-gradient(180deg, var(--holo-primary), var(--holo-secondary));
    border-radius: 2px;
}

/* Thread Avatar */
.holo-avatar {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--holo-primary), var(--holo-secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
    color: white;
    flex-shrink: 0;
    overflow: hidden;
    position: relative;
}

.holo-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.holo-avatar-status {
    position: absolute;
    bottom: -2px;
    right: -2px;
    width: 14px;
    height: 14px;
    background: var(--holo-success);
    border: 3px solid #0f172a;
    border-radius: 50%;
}

/* Thread Info */
.holo-thread-info {
    flex: 1;
    min-width: 0;
}

.holo-thread-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
}

.holo-thread-name {
    font-weight: 600;
    font-size: 1rem;
    color: var(--holo-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.holo-thread.unread .holo-thread-name {
    font-weight: 700;
    color: #ffffff;
}

.holo-thread-time {
    font-size: 0.75rem;
    color: var(--holo-text-muted);
    flex-shrink: 0;
}

.holo-thread.unread .holo-thread-time {
    color: var(--holo-secondary);
    font-weight: 600;
}

.holo-thread-preview {
    font-size: 0.9rem;
    color: var(--holo-text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.holo-thread.unread .holo-thread-preview {
    color: rgba(255, 255, 255, 0.8);
}

.holo-thread-badge {
    min-width: 22px;
    height: 22px;
    padding: 0 7px;
    border-radius: 11px;
    background: linear-gradient(135deg, var(--holo-primary), var(--holo-secondary));
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: auto;
    flex-shrink: 0;
    box-shadow: 0 2px 10px rgba(139, 92, 246, 0.4);
}

/* Main Chat Area */
.holo-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: rgba(15, 23, 42, 0.4);
    border: 1px solid var(--holo-border);
    border-radius: 24px;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    overflow: hidden;
    position: relative;
}

/* Holographic Border Glow for Main */
.holo-main::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 24px;
    padding: 1px;
    background: linear-gradient(135deg,
        rgba(6, 182, 212, 0.2) 0%,
        rgba(139, 92, 246, 0.3) 50%,
        rgba(244, 114, 182, 0.2) 100%);
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
}

/* Empty State / Welcome */
.holo-welcome {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px;
    text-align: center;
}

.holo-welcome-icon {
    width: 120px;
    height: 120px;
    border-radius: 30px;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(6, 182, 212, 0.1));
    border: 1px solid rgba(139, 92, 246, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 30px;
    animation: floatIcon 4s ease-in-out infinite;
}

@keyframes floatIcon {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.holo-welcome-icon i {
    font-size: 3rem;
    background: linear-gradient(135deg, var(--holo-primary), var(--holo-secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.holo-welcome h2 {
    font-size: 1.75rem;
    font-weight: 800;
    margin: 0 0 12px;
    background: linear-gradient(135deg, #ffffff 0%, #c4b5fd 50%, #67e8f9 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.holo-welcome p {
    font-size: 1rem;
    color: var(--holo-text-muted);
    max-width: 400px;
    line-height: 1.6;
    margin: 0 0 30px;
}

.holo-welcome-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 16px 32px;
    background: linear-gradient(135deg, var(--holo-primary), var(--holo-secondary));
    border: none;
    border-radius: 16px;
    color: white;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 25px rgba(139, 92, 246, 0.4);
}

.holo-welcome-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 35px rgba(139, 92, 246, 0.5);
}

.holo-welcome-btn:active {
    transform: scale(0.98);
}

/* Empty Threads State */
.holo-empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 24px;
    text-align: center;
}

.holo-empty-icon {
    width: 80px;
    height: 80px;
    border-radius: 20px;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(6, 182, 212, 0.1));
    border: 1px solid rgba(139, 92, 246, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
}

.holo-empty-icon i {
    font-size: 2rem;
    color: var(--holo-primary);
}

.holo-empty-state h3 {
    margin: 0 0 8px;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--holo-text);
}

.holo-empty-state p {
    margin: 0 0 24px;
    color: var(--holo-text-muted);
    font-size: 0.9rem;
}

.holo-empty-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 24px;
    background: linear-gradient(135deg, var(--holo-primary), var(--holo-secondary));
    border: none;
    border-radius: 14px;
    color: white;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 20px rgba(139, 92, 246, 0.4);
}

.holo-empty-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(139, 92, 246, 0.5);
}

/* Desktop New Message Modal */
.holo-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(8px);
    z-index: 300;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 24px;
}

.holo-modal-overlay.active {
    display: flex;
}

.holo-modal {
    width: 100%;
    max-width: 480px;
    max-height: 80vh;
    background: rgba(15, 23, 42, 0.95);
    border: 1px solid var(--holo-border);
    border-radius: 24px;
    backdrop-filter: blur(20px);
    overflow: hidden;
    transform: scale(0.9);
    opacity: 0;
    transition: all 0.3s ease;
    position: relative;
}

.holo-modal-overlay.active .holo-modal {
    transform: scale(1);
    opacity: 1;
}

/* Modal Holographic Border */
.holo-modal::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 24px;
    padding: 1px;
    background: linear-gradient(135deg,
        rgba(139, 92, 246, 0.4) 0%,
        rgba(6, 182, 212, 0.3) 50%,
        rgba(244, 114, 182, 0.4) 100%);
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
}

.holo-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 24px;
    border-bottom: 1px solid var(--holo-border);
}

.holo-modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    background: linear-gradient(135deg, #ffffff, #c4b5fd);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.holo-modal-close {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    border: 1px solid var(--holo-border);
    background: rgba(255, 255, 255, 0.05);
    color: var(--holo-text-muted);
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.holo-modal-close:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: rgba(239, 68, 68, 0.4);
    color: #ef4444;
}

.holo-modal-search {
    padding: 20px 24px;
}

.holo-modal-search input {
    width: 100%;
    padding: 14px 16px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--holo-border);
    border-radius: 14px;
    color: var(--holo-text);
    font-size: 1rem;
    outline: none;
    transition: all 0.3s ease;
}

.holo-modal-search input:focus {
    background: rgba(139, 92, 246, 0.05);
    border-color: rgba(139, 92, 246, 0.4);
    box-shadow: 0 0 20px rgba(139, 92, 246, 0.15);
}

.holo-modal-search input::placeholder {
    color: var(--holo-text-muted);
}

.holo-modal-results {
    max-height: 400px;
    overflow-y: auto;
    padding: 0 12px 12px;
}

.holo-modal-results::-webkit-scrollbar {
    width: 6px;
}

.holo-modal-results::-webkit-scrollbar-track {
    background: transparent;
}

.holo-modal-results::-webkit-scrollbar-thumb {
    background: rgba(139, 92, 246, 0.3);
    border-radius: 3px;
}

.holo-modal-user {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    margin-bottom: 6px;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid transparent;
    border-radius: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.holo-modal-user:hover {
    background: rgba(139, 92, 246, 0.1);
    border-color: rgba(139, 92, 246, 0.2);
}

.holo-modal-user-avatar {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--holo-primary), var(--holo-secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    overflow: hidden;
}

.holo-modal-user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.holo-modal-user-name {
    font-weight: 600;
    color: var(--holo-text);
    font-size: 1rem;
}

.holo-modal-state {
    padding: 40px 24px;
    text-align: center;
    color: var(--holo-text-muted);
}

.holo-modal-spinner {
    width: 36px;
    height: 36px;
    border: 3px solid var(--holo-border);
    border-top-color: var(--holo-primary);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 16px;
}

/* Desktop Toast */
.holo-toast {
    position: fixed;
    bottom: 40px;
    left: 50%;
    transform: translateX(-50%) translateY(20px);
    background: rgba(15, 23, 42, 0.95);
    border: 1px solid rgba(139, 92, 246, 0.3);
    color: var(--holo-text);
    padding: 16px 28px;
    border-radius: 14px;
    font-size: 0.95rem;
    z-index: 400;
    opacity: 0;
    transition: opacity 0.3s, transform 0.3s;
    pointer-events: none;
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 25px rgba(0, 0, 0, 0.3);
}

.holo-toast.show {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}

/* Animation for content */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.holo-sidebar {
    animation: fadeInUp 0.5s ease-out;
}

.holo-main {
    animation: fadeInUp 0.5s ease-out 0.1s both;
}

/* ============================================
   INLINE CHAT AREA STYLES
   ============================================ */

/* Chat Header */
.holo-chat-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid var(--holo-border);
    background: rgba(15, 23, 42, 0.3);
}

.holo-chat-user {
    display: flex;
    align-items: center;
    gap: 14px;
}

.holo-chat-avatar {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--holo-primary), var(--holo-secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
    color: white;
    overflow: hidden;
    position: relative;
}

.holo-chat-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.holo-chat-avatar-status {
    position: absolute;
    bottom: -2px;
    right: -2px;
    width: 12px;
    height: 12px;
    background: var(--holo-success);
    border: 2px solid rgba(15, 23, 42, 0.8);
    border-radius: 50%;
}

.holo-chat-info h3 {
    margin: 0 0 2px;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--holo-text);
}

.holo-chat-status {
    font-size: 0.8rem;
    color: var(--holo-text-muted);
}

.holo-chat-status.online {
    color: var(--holo-success);
}

.holo-chat-actions {
    display: flex;
    gap: 8px;
}

.holo-chat-action-btn {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    border: 1px solid var(--holo-border);
    background: rgba(255, 255, 255, 0.03);
    color: var(--holo-text-muted);
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.holo-chat-action-btn:hover {
    background: rgba(139, 92, 246, 0.1);
    border-color: rgba(139, 92, 246, 0.3);
    color: var(--holo-primary);
}

.holo-chat-action-btn.danger:hover {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.3);
    color: var(--holo-danger);
}

/* Messages Container */
.holo-chat-messages {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.holo-chat-messages::-webkit-scrollbar {
    width: 6px;
}

.holo-chat-messages::-webkit-scrollbar-track {
    background: transparent;
}

.holo-chat-messages::-webkit-scrollbar-thumb {
    background: rgba(139, 92, 246, 0.3);
    border-radius: 3px;
}

/* Date Separator */
.holo-date-sep {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 8px 0;
}

.holo-date-sep::before,
.holo-date-sep::after {
    content: '';
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(139, 92, 246, 0.2), transparent);
}

.holo-date-sep span {
    font-size: 0.75rem;
    color: var(--holo-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 6px 14px;
    background: rgba(139, 92, 246, 0.1);
    border-radius: 20px;
}

/* Message Bubble */
.holo-message {
    display: flex;
    gap: 12px;
    max-width: 75%;
    animation: messageIn 0.3s ease-out;
}

@keyframes messageIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.holo-message.sent {
    flex-direction: row-reverse;
    margin-left: auto;
}

.holo-message-avatar {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--holo-primary), var(--holo-secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.85rem;
    color: white;
    flex-shrink: 0;
    overflow: hidden;
}

.holo-message.sent .holo-message-avatar {
    background: linear-gradient(135deg, var(--holo-secondary), var(--holo-accent));
}

.holo-message-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.holo-message-content {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.holo-message-bubble {
    padding: 14px 18px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--holo-border);
    border-radius: 18px;
    border-top-left-radius: 4px;
    color: var(--holo-text);
    font-size: 0.95rem;
    line-height: 1.5;
    position: relative;
}

.holo-message.sent .holo-message-bubble {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(6, 182, 212, 0.15));
    border-color: rgba(139, 92, 246, 0.3);
    border-radius: 18px;
    border-top-right-radius: 4px;
}

.holo-message-time {
    font-size: 0.7rem;
    color: var(--holo-text-muted);
    padding: 0 4px;
}

.holo-message.sent .holo-message-time {
    text-align: right;
}

/* Typing Indicator */
.holo-typing-indicator {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0;
    opacity: 0;
    transition: opacity 0.3s;
}

.holo-typing-indicator.visible {
    opacity: 1;
}

.holo-typing-dots {
    display: flex;
    gap: 4px;
    padding: 12px 16px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--holo-border);
    border-radius: 16px;
}

.holo-typing-dots span {
    width: 8px;
    height: 8px;
    background: var(--holo-text-muted);
    border-radius: 50%;
    animation: typingBounce 1.4s ease-in-out infinite;
}

.holo-typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.holo-typing-dots span:nth-child(3) { animation-delay: 0.4s; }

@keyframes typingBounce {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-4px); }
}

/* Chat Input Area */
.holo-chat-input-wrap {
    padding: 20px 24px;
    border-top: 1px solid var(--holo-border);
    background: rgba(15, 23, 42, 0.3);
}

.holo-chat-input-row {
    display: flex;
    align-items: flex-end;
    gap: 12px;
}

.holo-chat-textarea-wrap {
    flex: 1;
    position: relative;
}

.holo-chat-textarea {
    width: 100%;
    min-height: 48px;
    max-height: 150px;
    padding: 14px 18px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--holo-border);
    border-radius: 16px;
    color: var(--holo-text);
    font-size: 0.95rem;
    font-family: inherit;
    line-height: 1.4;
    resize: none;
    outline: none;
    transition: all 0.3s ease;
}

.holo-chat-textarea::placeholder {
    color: var(--holo-text-muted);
}

.holo-chat-textarea:focus {
    background: rgba(139, 92, 246, 0.05);
    border-color: rgba(139, 92, 246, 0.4);
    box-shadow: 0 0 25px rgba(139, 92, 246, 0.1);
}

.holo-send-btn {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--holo-primary), var(--holo-secondary));
    border: none;
    color: white;
    font-size: 1.1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    box-shadow: 0 4px 20px rgba(139, 92, 246, 0.4);
    flex-shrink: 0;
}

.holo-send-btn:hover {
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 6px 30px rgba(139, 92, 246, 0.5);
}

.holo-send-btn:active {
    transform: scale(0.95);
}

.holo-send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Voice Recording Button */
.holo-voice-btn {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--holo-border);
    color: var(--holo-text-muted);
    font-size: 1.1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.holo-voice-btn:hover {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.holo-voice-btn.recording {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    border-color: #ef4444;
    color: white;
    animation: voicePulse 1s ease-in-out infinite;
    box-shadow: 0 4px 20px rgba(239, 68, 68, 0.4);
}

@keyframes voicePulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

/* Voice Recording Overlay */
.holo-voice-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(12px);
    z-index: 500;
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 24px;
}

.holo-voice-overlay.active {
    display: flex;
}

.holo-voice-visual {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.1));
    border: 2px solid rgba(239, 68, 68, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    animation: voiceRipple 1.5s ease-in-out infinite;
}

@keyframes voiceRipple {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4),
                    0 0 0 20px rgba(239, 68, 68, 0.1),
                    0 0 0 40px rgba(239, 68, 68, 0.05);
    }
    50% {
        box-shadow: 0 0 0 10px rgba(239, 68, 68, 0.3),
                    0 0 0 30px rgba(239, 68, 68, 0.15),
                    0 0 0 50px rgba(239, 68, 68, 0.05);
    }
}

.holo-voice-visual i {
    font-size: 3.5rem;
    color: #ef4444;
}

.holo-voice-time {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--holo-text);
    font-variant-numeric: tabular-nums;
}

.holo-voice-hint {
    font-size: 1rem;
    color: var(--holo-text-muted);
}

.holo-voice-actions {
    display: flex;
    gap: 20px;
    margin-top: 16px;
}

.holo-voice-cancel,
.holo-voice-send {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.holo-voice-cancel {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid var(--holo-border);
    color: var(--holo-text-muted);
}

.holo-voice-cancel:hover {
    background: rgba(255, 255, 255, 0.15);
    color: var(--holo-text);
}

.holo-voice-send {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    box-shadow: 0 4px 20px rgba(16, 185, 129, 0.4);
}

.holo-voice-send:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 30px rgba(16, 185, 129, 0.5);
}

/* Voice Message Bubble */
.holo-voice-message {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: rgba(139, 92, 246, 0.1);
    border: 1px solid rgba(139, 92, 246, 0.2);
    border-radius: 16px;
    min-width: 200px;
}

.holo-message.sent .holo-voice-message {
    background: rgba(6, 182, 212, 0.1);
    border-color: rgba(6, 182, 212, 0.2);
}

.holo-voice-play-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--holo-primary), var(--holo-secondary));
    border: none;
    color: white;
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.3s ease;
}

.holo-voice-play-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
}

.holo-voice-play-btn.playing i::before {
    content: "\f04c"; /* pause icon */
}

.holo-voice-waveform {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 3px;
    height: 32px;
}

.holo-voice-waveform span {
    width: 3px;
    background: var(--holo-primary);
    border-radius: 2px;
    opacity: 0.5;
    transition: height 0.1s ease;
}

.holo-voice-waveform span:nth-child(1) { height: 40%; }
.holo-voice-waveform span:nth-child(2) { height: 70%; }
.holo-voice-waveform span:nth-child(3) { height: 50%; }
.holo-voice-waveform span:nth-child(4) { height: 90%; }
.holo-voice-waveform span:nth-child(5) { height: 60%; }
.holo-voice-waveform span:nth-child(6) { height: 80%; }
.holo-voice-waveform span:nth-child(7) { height: 45%; }
.holo-voice-waveform span:nth-child(8) { height: 65%; }

.holo-voice-duration {
    font-size: 0.8rem;
    color: var(--holo-text-muted);
    min-width: 35px;
    text-align: right;
    font-variant-numeric: tabular-nums;
}

/* Loading State for Chat Area */
.holo-chat-loading {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 16px;
}

.holo-chat-spinner {
    width: 48px;
    height: 48px;
    border: 3px solid var(--holo-border);
    border-top-color: var(--holo-primary);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

.holo-chat-loading p {
    color: var(--holo-text-muted);
    font-size: 0.9rem;
}

/* Thread Delete Hover Button */
.holo-thread-delete {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid transparent;
    background: transparent;
    color: var(--holo-text-muted);
    font-size: 0.85rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: all 0.2s ease;
    z-index: 10;
}

.holo-thread:hover .holo-thread-delete {
    opacity: 1;
}

.holo-thread-delete:hover {
    background: rgba(239, 68, 68, 0.15);
    border-color: rgba(239, 68, 68, 0.3);
    color: var(--holo-danger);
}

/* Adjust thread info to make room for delete button */
.holo-thread {
    padding-right: 50px;
}

/* Desktop Delete Confirmation Modal */
.holo-delete-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(8px);
    z-index: 350;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 24px;
}

.holo-delete-overlay.active {
    display: flex;
}

.holo-delete-modal {
    width: 100%;
    max-width: 380px;
    background: rgba(15, 23, 42, 0.95);
    border: 1px solid var(--holo-border);
    border-radius: 20px;
    backdrop-filter: blur(20px);
    padding: 28px;
    text-align: center;
    transform: scale(0.9);
    opacity: 0;
    transition: all 0.3s ease;
    position: relative;
}

.holo-delete-overlay.active .holo-delete-modal {
    transform: scale(1);
    opacity: 1;
}

/* Delete modal glow border */
.holo-delete-modal::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 20px;
    padding: 1px;
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.4), rgba(239, 68, 68, 0.1), rgba(244, 114, 182, 0.3));
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
}

.holo-delete-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.holo-delete-icon i {
    font-size: 1.5rem;
    color: var(--holo-danger);
}

.holo-delete-modal h4 {
    margin: 0 0 10px;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--holo-text);
}

.holo-delete-modal p {
    margin: 0 0 24px;
    font-size: 0.9rem;
    color: var(--holo-text-muted);
    line-height: 1.5;
}

.holo-delete-actions {
    display: flex;
    gap: 12px;
}

.holo-delete-actions button {
    flex: 1;
    padding: 14px 20px;
    border-radius: 12px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.3s ease;
}

.holo-cancel-btn {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--holo-border) !important;
    color: var(--holo-text);
}

.holo-cancel-btn:hover {
    background: rgba(255, 255, 255, 0.1);
}

.holo-delete-btn {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
}

.holo-delete-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
}

/* Context Menu for threads */
.holo-context-menu {
    position: fixed;
    background: rgba(15, 23, 42, 0.95);
    border: 1px solid var(--holo-border);
    border-radius: 14px;
    backdrop-filter: blur(20px);
    padding: 8px;
    min-width: 180px;
    z-index: 400;
    opacity: 0;
    transform: scale(0.95);
    transition: opacity 0.15s, transform 0.15s;
    pointer-events: none;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.holo-context-menu.active {
    opacity: 1;
    transform: scale(1);
    pointer-events: auto;
}

.holo-context-menu::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 14px;
    padding: 1px;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.3), rgba(6, 182, 212, 0.2));
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
}

.holo-context-item {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 12px 14px;
    border: none;
    background: transparent;
    color: var(--holo-text);
    font-size: 0.9rem;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.2s ease;
    text-align: left;
}

.holo-context-item:hover {
    background: rgba(139, 92, 246, 0.1);
}

.holo-context-item i {
    width: 18px;
    text-align: center;
    color: var(--holo-text-muted);
}

.holo-context-item:hover i {
    color: var(--holo-primary);
}

.holo-context-item.danger {
    color: var(--holo-danger);
}

.holo-context-item.danger i {
    color: var(--holo-danger);
}

.holo-context-item.danger:hover {
    background: rgba(239, 68, 68, 0.1);
}

.holo-context-divider {
    height: 1px;
    background: var(--holo-border);
    margin: 6px 0;
}
</style>

<!-- ==========================================
     DESKTOP HOLOGRAPHIC GLASSMORPHISM INTERFACE
     ========================================== -->
<!-- Main content wrapper (main tag opened in header.php) -->
<div class="messages-desktop-holo">
    <!-- Floating Orbs -->
    <div class="holo-orb-desktop holo-orb-desktop-1"></div>
    <div class="holo-orb-desktop holo-orb-desktop-2"></div>
    <div class="holo-orb-desktop holo-orb-desktop-3"></div>

    <div class="holo-messages-container">
        <!-- Sidebar - Conversations List -->
        <div class="holo-sidebar">
            <div class="holo-sidebar-header">
                <div class="holo-sidebar-top">
                    <a href="<?= $basePath ?>/" class="holo-back-btn no-transition" aria-label="Back to home" onclick="cleanupBeforeLeave()" data-turbo="false">
                        <i class="fa-solid fa-arrow-left"></i>
                    </a>
                    <div class="holo-title-group">
                        <h1>Messages</h1>
                        <span id="holoConvCount"><?= count($threads ?? []) ?> conversation<?= count($threads ?? []) !== 1 ? 's' : '' ?></span>
                    </div>
                    <button type="button" class="holo-new-btn" onclick="openHoloNewMessage()" aria-label="New message">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>
                <div class="holo-search-wrap">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" class="holo-search-input" placeholder="Search conversations..." id="holoSearchInput">
                </div>
            </div>

            <?php if (empty($threads)): ?>
                <div class="holo-empty-state">
                    <div class="holo-empty-icon">
                        <i class="fa-solid fa-paper-plane"></i>
                    </div>
                    <h3>No messages yet</h3>
                    <p>Start connecting with community members</p>
                    <button type="button" class="holo-empty-btn" onclick="openHoloNewMessage()">
                        <i class="fa-solid fa-plus"></i>
                        Start a conversation
                    </button>
                </div>
            <?php else: ?>
                <div class="holo-thread-list" id="holoThreadList">
                    <?php foreach ($threads as $thread): ?>
                        <?php
                        $isUnread = ($thread['receiver_id'] == $_SESSION['user_id'] && !$thread['is_read']);
                        $avatarUrl = $thread['other_user_avatar'] ?? $thread['avatar_url'] ?? null;
                        $initial = strtoupper(substr($thread['other_user_name'], 0, 1));
                        $preview = htmlspecialchars(substr($thread['body'], 0, 60));
                        if (strlen($thread['body']) > 60) $preview .= '...';

                        $msgTime = strtotime($thread['created_at']);
                        $diff = time() - $msgTime;
                        if ($diff < 60) $timeDisplay = 'Now';
                        elseif ($diff < 3600) $timeDisplay = floor($diff / 60) . 'm';
                        elseif ($diff < 86400) $timeDisplay = floor($diff / 3600) . 'h';
                        elseif ($diff < 604800) $timeDisplay = date('D', $msgTime);
                        else $timeDisplay = date('M j', $msgTime);

                        $otherLastActive = $thread['other_user_last_active'] ?? null;
                        $isOtherOnline = $otherLastActive && (strtotime($otherLastActive) > strtotime('-5 minutes'));
                        ?>
                        <div class="holo-thread <?= $isUnread ? 'unread' : '' ?>"
                             data-user-id="<?= $thread['other_user_id'] ?>"
                             data-user-name="<?= htmlspecialchars($thread['other_user_name']) ?>"
                             data-avatar="<?= htmlspecialchars($avatarUrl ?? '') ?>"
                             data-initial="<?= $initial ?>"
                             data-online="<?= $isOtherOnline ? '1' : '0' ?>"
                             data-name="<?= htmlspecialchars(strtolower($thread['other_user_name'])) ?>">
                            <div class="holo-avatar">
                                <?php if ($avatarUrl): ?>
                                    <?= webp_avatar($avatarUrl, $thread['other_user_name'], 48) ?>
                                <?php else: ?>
                                    <?= $initial ?>
                                <?php endif; ?>
                                <?php if ($isOtherOnline): ?>
                                    <span class="holo-avatar-status"></span>
                                <?php endif; ?>
                            </div>
                            <div class="holo-thread-info">
                                <div class="holo-thread-row">
                                    <span class="holo-thread-name"><?= htmlspecialchars($thread['other_user_name']) ?></span>
                                    <span class="holo-thread-time"><?= $timeDisplay ?></span>
                                </div>
                                <div class="holo-thread-row">
                                    <span class="holo-thread-preview"><?= $preview ?></span>
                                    <?php if ($isUnread): ?>
                                        <span class="holo-thread-badge">1</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button type="button" class="holo-thread-delete" title="Delete conversation">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Main Chat Area -->
        <div class="holo-main" id="holoMainChat">
            <!-- Welcome State (shown by default) -->
            <div class="holo-welcome" id="holoWelcomeState">
                <div class="holo-welcome-icon">
                    <i class="fa-solid fa-comments"></i>
                </div>
                <h2>Welcome to Messages</h2>
                <p>Select a conversation from the sidebar or start a new one to begin chatting with community members.</p>
                <button type="button" class="holo-welcome-btn" onclick="openHoloNewMessage()">
                    <i class="fa-solid fa-paper-plane"></i>
                    Start New Conversation
                </button>
            </div>

            <!-- Chat Area (hidden by default, shown when conversation selected) -->
            <div id="holoChatArea" style="display: none; flex-direction: column; height: 100%;">
                <!-- Chat Header -->
                <div class="holo-chat-header">
                    <div class="holo-chat-user">
                        <div class="holo-chat-avatar" id="holoChatAvatar">
                            <span id="holoChatInitial"></span>
                        </div>
                        <div class="holo-chat-info">
                            <h3 id="holoChatName">User Name</h3>
                            <div class="holo-chat-status" id="holoChatStatus">Offline</div>
                        </div>
                    </div>
                    <div class="holo-chat-actions">
                        <a href="#" id="holoChatProfileLink" class="holo-chat-action-btn" title="View profile">
                            <i class="fa-solid fa-user"></i>
                        </a>
                        <button type="button" class="holo-chat-action-btn danger" id="holoChatDeleteBtn" title="Delete conversation">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>

                <!-- Messages Container -->
                <div class="holo-chat-messages" id="holoChatMessages">
                    <!-- Messages will be loaded here via AJAX -->
                </div>

                <!-- Typing Indicator -->
                <div class="holo-typing-indicator" id="holoTypingIndicator">
                    <div class="holo-typing-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>

                <!-- Chat Input -->
                <div class="holo-chat-input-wrap">
                    <div class="holo-chat-input-row">
                        <button type="button" class="holo-voice-btn" id="holoVoiceBtn" title="Record voice message">
                            <i class="fa-solid fa-microphone"></i>
                        </button>
                        <div class="holo-chat-textarea-wrap">
                            <textarea class="holo-chat-textarea" id="holoChatInput" placeholder="Type a message..." rows="1"></textarea>
                        </div>
                        <button type="button" class="holo-send-btn" id="holoSendBtn" disabled>
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div class="holo-chat-loading" id="holoChatLoading" style="display: none;">
                <div class="holo-chat-spinner"></div>
                <p>Loading conversation...</p>
            </div>
        </div>
    </div>

    <!-- Desktop Delete Confirmation Modal -->
    <div class="holo-delete-overlay" id="holoDeleteOverlay">
        <div class="holo-delete-modal">
            <div class="holo-delete-icon">
                <i class="fa-solid fa-trash"></i>
            </div>
            <h4>Delete conversation?</h4>
            <p>This will permanently delete all messages with <span id="holoDeleteUserName">this person</span>.</p>
            <div class="holo-delete-actions">
                <button type="button" class="holo-cancel-btn" id="holoDeleteCancel">Cancel</button>
                <button type="button" class="holo-delete-btn" id="holoDeleteConfirm">Delete</button>
            </div>
        </div>
    </div>

    <!-- Context Menu -->
    <div class="holo-context-menu" id="holoContextMenu">
        <button type="button" class="holo-context-item" id="holoCtxOpen">
            <i class="fa-solid fa-comment"></i>
            <span>Open conversation</span>
        </button>
        <button type="button" class="holo-context-item" id="holoCtxProfile">
            <i class="fa-solid fa-user"></i>
            <span>View profile</span>
        </button>
        <div class="holo-context-divider"></div>
        <button type="button" class="holo-context-item danger" id="holoCtxDelete">
            <i class="fa-solid fa-trash"></i>
            <span>Delete conversation</span>
        </button>
    </div>

    <!-- Desktop New Message Modal -->
    <div class="holo-modal-overlay" id="holoNewMessageModal">
        <div class="holo-modal">
            <div class="holo-modal-header">
                <h3>New Message</h3>
                <button type="button" class="holo-modal-close" onclick="closeHoloNewMessage()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="holo-modal-search">
                <input type="text" placeholder="Search members..." id="holoNmSearchInput">
            </div>
            <div class="holo-modal-results" id="holoNmResults">
                <div class="holo-modal-state">
                    <i class="fa-solid fa-users" style="font-size: 1.5rem; margin-bottom: 12px; opacity: 0.5;"></i>
                    <p>Type a name to search</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Desktop Toast -->
    <div class="holo-toast" id="holoToast"></div>

    <!-- Voice Recording Overlay -->
    <div class="holo-voice-overlay" id="holoVoiceOverlay">
        <div class="holo-voice-visual">
            <i class="fa-solid fa-microphone"></i>
        </div>
        <div class="holo-voice-time" id="holoVoiceTime">0:00</div>
        <div class="holo-voice-hint">Recording voice message...</div>
        <div class="holo-voice-actions">
            <button type="button" class="holo-voice-cancel" id="holoVoiceCancel" aria-label="Cancel recording">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <button type="button" class="holo-voice-send" id="holoVoiceSend" aria-label="Send voice message">
                <i class="fa-solid fa-check"></i>
            </button>
        </div>
    </div>
</div>

<!-- ==========================================
     MOBILE FULLSCREEN INTERFACE
     ========================================== -->
<div class="messages-app mobile-interface">
    <!-- Header -->
    <header class="messages-header">
        <div class="messages-header-left">
            <a href="<?= $basePath ?>/" class="messages-back no-transition" aria-label="Back to home" onclick="cleanupBeforeLeave()" data-turbo="false">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="messages-title">Messages</h1>
                <div class="messages-count"><?= count($threads ?? []) ?> conversation<?= count($threads ?? []) !== 1 ? 's' : '' ?></div>
            </div>
        </div>
        <button type="button" class="messages-new-btn" onclick="openNewMessage()" aria-label="New message">
            <i class="fa-solid fa-plus"></i>
        </button>
    </header>

    <!-- Search -->
    <div class="messages-search">
        <div class="messages-search-wrap">
            <i class="fa-solid fa-search"></i>
            <input type="text" class="messages-search-input" placeholder="Search conversations..." id="searchInput">
        </div>
    </div>

    <!-- Thread List -->
    <?php if (empty($threads)): ?>
        <div class="messages-empty">
            <div class="messages-empty-icon">
                <i class="fa-solid fa-paper-plane"></i>
            </div>
            <h3>No messages yet</h3>
            <p>Start connecting with members in your community.</p>
            <button type="button" class="messages-empty-btn" onclick="openNewMessage()">
                <i class="fa-solid fa-plus"></i>
                Start a conversation
            </button>
        </div>
    <?php else: ?>
        <div class="messages-list" id="threadList">
            <?php foreach ($threads as $thread): ?>
                <?php
                $isUnread = ($thread['receiver_id'] == $_SESSION['user_id'] && !$thread['is_read']);
                $avatarUrl = $thread['other_user_avatar'] ?? $thread['avatar_url'] ?? null;
                $initial = strtoupper(substr($thread['other_user_name'], 0, 1));
                $preview = htmlspecialchars(substr($thread['body'], 0, 50));
                if (strlen($thread['body']) > 50) $preview .= '...';

                $msgTime = strtotime($thread['created_at']);
                $diff = time() - $msgTime;
                if ($diff < 60) $timeDisplay = 'Now';
                elseif ($diff < 3600) $timeDisplay = floor($diff / 60) . 'm';
                elseif ($diff < 86400) $timeDisplay = floor($diff / 3600) . 'h';
                elseif ($diff < 604800) $timeDisplay = date('D', $msgTime);
                else $timeDisplay = date('M j', $msgTime);

                // Real-time online status
                $otherLastActive = $thread['other_user_last_active'] ?? null;
                $isOtherOnline = $otherLastActive && (strtotime($otherLastActive) > strtotime('-5 minutes'));
                ?>
                <div class="messages-thread-wrap"
                     data-user-id="<?= $thread['other_user_id'] ?>"
                     data-user-name="<?= htmlspecialchars($thread['other_user_name']) ?>"
                     data-avatar="<?= htmlspecialchars($avatarUrl ?? '') ?>"
                     data-initial="<?= $initial ?>">
                    <a href="<?= $basePath ?>/messages/<?= $thread['other_user_id'] ?>"
                       class="messages-thread <?= $isUnread ? 'unread' : '' ?>"
                       data-name="<?= htmlspecialchars(strtolower($thread['other_user_name'])) ?>">
                        <div class="messages-avatar" style="position: relative;">
                            <?php if ($avatarUrl): ?>
                                <?= webp_avatar($avatarUrl, $thread['other_user_name'], 48) ?>
                            <?php else: ?>
                                <?= $initial ?>
                            <?php endif; ?>
                            <?php if ($isOtherOnline): ?>
                                <span style="position:absolute;bottom:0;right:0;width:12px;height:12px;background:#10b981;border:2px solid var(--msg-surface);border-radius:50%;"></span>
                            <?php endif; ?>
                        </div>
                        <div class="messages-content">
                            <div class="messages-row">
                                <span class="messages-name"><?= htmlspecialchars($thread['other_user_name']) ?></span>
                                <span class="messages-time"><?= $timeDisplay ?></span>
                            </div>
                            <div class="messages-row">
                                <span class="messages-preview"><?= $preview ?></span>
                                <?php if ($isUnread): ?>
                                    <span class="messages-badge">1</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <div class="messages-thread-actions">
                        <button type="button" class="msg-delete-action" aria-label="Delete conversation">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                    <button type="button" class="messages-thread-options" aria-label="More options">
                        <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- New Message Modal -->
<div class="nm-overlay" id="newMessageModal">
    <div class="nm-sheet">
        <div class="nm-handle"></div>
        <div class="nm-header">
            <h3>New Message</h3>
            <button type="button" class="nm-close" onclick="closeNewMessage()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="nm-search">
            <input type="text" placeholder="Search members..." id="nmSearchInput">
        </div>
        <div class="nm-results" id="nmResults">
            <div class="nm-state">
                <i class="fa-solid fa-users" style="font-size: 1.5rem; margin-bottom: 12px; opacity: 0.5;"></i>
                <p>Type a name to search</p>
            </div>
        </div>
    </div>
</div>

<!-- Thread Options Menu -->
<div class="thread-menu-overlay" id="threadMenuOverlay">
    <div class="thread-menu-sheet">
        <div class="thread-menu-header" id="threadMenuHeader">
            <div class="messages-avatar" id="threadMenuAvatar"></div>
            <span id="threadMenuName"></span>
        </div>
        <button type="button" class="thread-menu-btn" id="threadMenuView">
            <i class="fa-solid fa-comment"></i>
            <span>View conversation</span>
        </button>
        <button type="button" class="thread-menu-btn delete" id="threadMenuDelete">
            <i class="fa-solid fa-trash"></i>
            <span>Delete conversation</span>
        </button>
    </div>
</div>

<!-- Delete Confirmation -->
<div class="delete-confirm-overlay" id="deleteConfirmOverlay">
    <div class="delete-confirm-box">
        <h4>Delete conversation?</h4>
        <p>This will permanently delete all messages with this person.</p>
        <div class="delete-confirm-actions">
            <button type="button" class="delete-cancel-btn" id="deleteCancelBtn">Cancel</button>
            <button type="button" class="delete-confirm-btn" id="deleteConfirmBtn">Delete</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="msg-toast" id="msgToast"></div>

<script>
(function() {
    'use strict';

    // Add messages-page class for CSS
    document.documentElement.classList.add('messages-page');
    document.body.classList.add('messages-page');

    // Disable PTR if NexusMobile is present
    if (window.NexusMobile && window.NexusMobile.ptrInstances) {
        window.NexusMobile.ptrInstances.forEach(function(instance) {
            if (instance && instance.destroy) instance.destroy();
        });
        window.NexusMobile.ptrInstances = [];
    }

    // Note: PTR prevention is handled by CSS (overscroll-behavior: none)
    // and the skip conditions in nexus-mobile.js

    // =============================================
    // MESSAGES LIST FUNCTIONALITY
    // =============================================

    const BASE_PATH = <?= json_encode(rtrim($basePath, '/')) ?>;
    const modal = document.getElementById('newMessageModal');
    const searchInput = document.getElementById('searchInput');
    const nmSearchInput = document.getElementById('nmSearchInput');
    const nmResults = document.getElementById('nmResults');
    const threadList = document.getElementById('threadList');
    let searchTimeout = null;

    // Filter threads
    if (searchInput && threadList) {
        searchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            threadList.querySelectorAll('.messages-thread').forEach(t => {
                const name = t.dataset.name || '';
                t.style.display = name.includes(q) ? '' : 'none';
            });
        });
    }

    // New message modal
    window.openNewMessage = function() {
        modal.classList.add('active');
        setTimeout(() => nmSearchInput?.focus(), 300);
    };

    window.closeNewMessage = function() {
        modal.classList.remove('active');
        if (nmSearchInput) nmSearchInput.value = '';
        resetResults();
    };

    function resetResults() {
        nmResults.innerHTML = `
            <div class="nm-state">
                <i class="fa-solid fa-users" style="font-size: 1.5rem; margin-bottom: 12px; opacity: 0.5;"></i>
                <p>Type a name to search</p>
            </div>
        `;
    }

    function showLoading() {
        nmResults.innerHTML = `
            <div class="nm-state">
                <div class="nm-spinner"></div>
                <p>Searching...</p>
            </div>
        `;
    }

    async function searchUsers(query) {
        if (query.length < 2) { resetResults(); return; }
        showLoading();
        try {
            const res = await fetch(`${BASE_PATH}/members?q=${encodeURIComponent(query)}&ajax=1`, {
                credentials: 'include',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            const users = data.data || data || [];
            if (users.length) {
                nmResults.innerHTML = users.map(u => {
                    const initial = (u.name || 'U').charAt(0).toUpperCase();
                    const avatar = u.avatar_url
                        ? `<img src="${escapeHtml(u.avatar_url)}" alt="" loading="lazy">`
                        : initial;
                    return `
                        <div class="nm-user" onclick="window.location.href='${BASE_PATH}/messages/${u.id}'">
                            <div class="nm-user-avatar">${avatar}</div>
                            <div class="nm-user-name">${escapeHtml(u.name || 'Unknown')}</div>
                        </div>
                    `;
                }).join('');
            } else {
                nmResults.innerHTML = `<div class="nm-state"><p>No members found</p></div>`;
            }
        } catch (e) {
            nmResults.innerHTML = `<div class="nm-state"><p>Search failed</p></div>`;
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    if (nmSearchInput) {
        nmSearchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => searchUsers(this.value.trim()), 300);
        });
    }

    // Close modal on backdrop click
    modal?.addEventListener('click', function(e) {
        if (e.target === this) closeNewMessage();
    });

    // =============================================
    // CONVERSATION DELETE FUNCTIONALITY
    // =============================================

    const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
    const threadMenuOverlay = document.getElementById('threadMenuOverlay');
    const threadMenuAvatar = document.getElementById('threadMenuAvatar');
    const threadMenuName = document.getElementById('threadMenuName');
    const threadMenuView = document.getElementById('threadMenuView');
    const threadMenuDelete = document.getElementById('threadMenuDelete');
    const deleteConfirmOverlay = document.getElementById('deleteConfirmOverlay');
    const deleteCancelBtn = document.getElementById('deleteCancelBtn');
    const deleteConfirmBtn = document.getElementById('deleteConfirmBtn');
    const msgToast = document.getElementById('msgToast');

    let selectedUserId = null;
    let selectedWrap = null;
    let swipeStartX = 0;
    let swipeThreshold = 60;
    let isSwiping = false;

    // Show toast notification
    function showToast(message, duration = 2000) {
        msgToast.textContent = message;
        msgToast.classList.add('show');
        setTimeout(() => msgToast.classList.remove('show'), duration);
    }

    // Open thread menu
    function openThreadMenu(wrap) {
        selectedUserId = wrap.dataset.userId;
        selectedWrap = wrap;
        const userName = wrap.dataset.userName;
        const avatar = wrap.dataset.avatar;
        const initial = wrap.dataset.initial;

        threadMenuName.textContent = userName;
        if (avatar) {
            threadMenuAvatar.innerHTML = `<img src="${escapeHtml(avatar)}" alt="" loading="lazy">`;
        } else {
            threadMenuAvatar.textContent = initial;
        }

        threadMenuOverlay.classList.add('active');
    }

    // Close thread menu
    function closeThreadMenu() {
        threadMenuOverlay.classList.remove('active');
    }

    // Show delete confirmation
    function showDeleteConfirm() {
        closeThreadMenu();
        deleteConfirmOverlay.classList.add('active');
    }

    // Close delete confirmation
    function closeDeleteConfirm() {
        deleteConfirmOverlay.classList.remove('active');
        selectedUserId = null;
        selectedWrap = null;
    }

    // Delete conversation
    async function deleteConversation() {
        if (!selectedUserId || !selectedWrap) return;

        const userId = selectedUserId;
        const wrap = selectedWrap;

        closeDeleteConfirm();

        // Optimistic UI - fade out
        wrap.style.transition = 'opacity 0.3s, transform 0.3s, max-height 0.3s';
        wrap.style.opacity = '0.5';

        try {
            const res = await fetch(`${BASE_PATH}/api/messages/delete-conversation`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ other_user_id: userId })
            });

            const data = await res.json();

            if (data.success) {
                wrap.style.opacity = '0';
                wrap.style.transform = 'translateX(-100%)';
                wrap.style.maxHeight = '0';
                wrap.style.overflow = 'hidden';
                setTimeout(() => wrap.remove(), 300);
                showToast('Conversation deleted');

                // Update count
                const countEl = document.querySelector('.messages-count');
                if (countEl) {
                    const remaining = document.querySelectorAll('.messages-thread-wrap').length - 1;
                    countEl.textContent = `${remaining} conversation${remaining !== 1 ? 's' : ''}`;
                }
            } else {
                wrap.style.opacity = '1';
                showToast(data.error || 'Failed to delete');
            }
        } catch (e) {
            wrap.style.opacity = '1';
            showToast('Failed to delete conversation');
        }

        selectedUserId = null;
        selectedWrap = null;
    }

    // Close any swiped threads
    function closeSwipedThreads(except) {
        document.querySelectorAll('.messages-thread-wrap.swiped').forEach(w => {
            if (w !== except) w.classList.remove('swiped');
        });
    }

    // Handle swipe start
    function handleSwipeStart(e) {
        const wrap = e.target.closest('.messages-thread-wrap');
        if (!wrap) return;

        closeSwipedThreads(wrap);
        swipeStartX = e.touches ? e.touches[0].clientX : e.clientX;
        isSwiping = true;
    }

    // Handle swipe move
    function handleSwipeMove(e) {
        if (!isSwiping) return;
        const wrap = e.target.closest('.messages-thread-wrap');
        if (!wrap) return;

        const currentX = e.touches ? e.touches[0].clientX : e.clientX;
        const diff = swipeStartX - currentX;

        if (diff > swipeThreshold) {
            wrap.classList.add('swiped');
        } else if (diff < -20) {
            wrap.classList.remove('swiped');
        }
    }

    // Handle swipe end
    function handleSwipeEnd() {
        isSwiping = false;
    }

    // Attach swipe listeners
    if (threadList) {
        threadList.addEventListener('touchstart', handleSwipeStart, { passive: true });
        threadList.addEventListener('touchmove', handleSwipeMove, { passive: true });
        threadList.addEventListener('touchend', handleSwipeEnd);

        // Handle delete button click
        threadList.addEventListener('click', function(e) {
            const deleteBtn = e.target.closest('.msg-delete-action');
            if (deleteBtn) {
                e.preventDefault();
                e.stopPropagation();
                const wrap = deleteBtn.closest('.messages-thread-wrap');
                if (wrap) {
                    selectedUserId = wrap.dataset.userId;
                    selectedWrap = wrap;
                    showDeleteConfirm();
                }
                return;
            }

            // Handle options button click (desktop)
            const optionsBtn = e.target.closest('.messages-thread-options');
            if (optionsBtn) {
                e.preventDefault();
                e.stopPropagation();
                const wrap = optionsBtn.closest('.messages-thread-wrap');
                if (wrap) {
                    openThreadMenu(wrap);
                }
                return;
            }
        });

        // Long press for mobile (alternative to swipe)
        let longPressTimer = null;
        threadList.addEventListener('touchstart', function(e) {
            const wrap = e.target.closest('.messages-thread-wrap');
            if (!wrap) return;

            longPressTimer = setTimeout(() => {
                openThreadMenu(wrap);
            }, 600);
        }, { passive: true });

        threadList.addEventListener('touchend', () => clearTimeout(longPressTimer));
        threadList.addEventListener('touchmove', () => clearTimeout(longPressTimer));
    }

    // Menu button handlers
    threadMenuView?.addEventListener('click', function() {
        if (selectedUserId) {
            window.location.href = `${BASE_PATH}/messages/${selectedUserId}`;
        }
    });

    threadMenuDelete?.addEventListener('click', showDeleteConfirm);
    deleteCancelBtn?.addEventListener('click', closeDeleteConfirm);
    deleteConfirmBtn?.addEventListener('click', deleteConversation);

    // Close overlays on backdrop click
    threadMenuOverlay?.addEventListener('click', function(e) {
        if (e.target === this) closeThreadMenu();
    });
    deleteConfirmOverlay?.addEventListener('click', function(e) {
        if (e.target === this) closeDeleteConfirm();
    });

    // Escape key to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (deleteConfirmOverlay?.classList.contains('active')) {
                closeDeleteConfirm();
            } else if (threadMenuOverlay?.classList.contains('active')) {
                closeThreadMenu();
            } else if (modal?.classList.contains('active')) {
                closeNewMessage();
            }
        }
    });

    // Close swiped threads when clicking elsewhere
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.messages-thread-wrap')) {
            closeSwipedThreads();
        }
    });
})();

// =============================================
// DESKTOP HOLOGRAPHIC INTERFACE FUNCTIONALITY
// =============================================
(function() {
    'use strict';

    const BASE_PATH = <?= json_encode(rtrim($basePath, '/')) ?>;
    const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
    const CURRENT_USER_ID = <?= (int)($_SESSION['user_id'] ?? 0) ?>;
    const CURRENT_USER_AVATAR = <?= json_encode($_SESSION['avatar_url'] ?? '') ?>;
    const CURRENT_USER_NAME = <?= json_encode($_SESSION['name'] ?? 'You') ?>;

    // Elements
    const holoModal = document.getElementById('holoNewMessageModal');
    const holoSearchInput = document.getElementById('holoSearchInput');
    const holoNmSearchInput = document.getElementById('holoNmSearchInput');
    const holoNmResults = document.getElementById('holoNmResults');
    const holoThreadList = document.getElementById('holoThreadList');
    const holoToast = document.getElementById('holoToast');
    const holoWelcomeState = document.getElementById('holoWelcomeState');
    const holoChatArea = document.getElementById('holoChatArea');
    const holoChatLoading = document.getElementById('holoChatLoading');
    const holoChatMessages = document.getElementById('holoChatMessages');
    const holoChatInput = document.getElementById('holoChatInput');
    const holoSendBtn = document.getElementById('holoSendBtn');
    const holoChatAvatar = document.getElementById('holoChatAvatar');
    const holoChatInitial = document.getElementById('holoChatInitial');
    const holoChatName = document.getElementById('holoChatName');
    const holoChatStatus = document.getElementById('holoChatStatus');
    const holoChatProfileLink = document.getElementById('holoChatProfileLink');
    const holoChatDeleteBtn = document.getElementById('holoChatDeleteBtn');
    const holoDeleteOverlay = document.getElementById('holoDeleteOverlay');
    const holoDeleteUserName = document.getElementById('holoDeleteUserName');
    const holoDeleteCancel = document.getElementById('holoDeleteCancel');
    const holoDeleteConfirm = document.getElementById('holoDeleteConfirm');
    const holoContextMenu = document.getElementById('holoContextMenu');
    const holoCtxOpen = document.getElementById('holoCtxOpen');
    const holoCtxProfile = document.getElementById('holoCtxProfile');
    const holoCtxDelete = document.getElementById('holoCtxDelete');

    let holoSearchTimeout = null;
    let currentChatUserId = null;
    let currentChatUserData = null;
    let messagePollingInterval = null;
    let lastMessageId = 0;

    // =============================================
    // UTILITY FUNCTIONS
    // =============================================

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatMessageTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;

        if (diff < 60000) return 'Just now';
        if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
        if (diff < 86400000 && date.getDate() === now.getDate()) {
            return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        }
        if (diff < 172800000) return 'Yesterday ' + date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        return date.toLocaleDateString([], { month: 'short', day: 'numeric' }) + ' ' + date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }

    function formatDateSeparator(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = Math.floor((now - date) / 86400000);

        if (diff === 0) return 'Today';
        if (diff === 1) return 'Yesterday';
        if (diff < 7) return date.toLocaleDateString([], { weekday: 'long' });
        return date.toLocaleDateString([], { month: 'long', day: 'numeric', year: 'numeric' });
    }

    // =============================================
    // TOAST NOTIFICATION
    // =============================================

    window.showHoloToast = function(message, duration = 2000) {
        holoToast.textContent = message;
        holoToast.classList.add('show');
        setTimeout(() => holoToast.classList.remove('show'), duration);
    };

    // =============================================
    // FILTER THREADS
    // =============================================

    if (holoSearchInput && holoThreadList) {
        holoSearchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            holoThreadList.querySelectorAll('.holo-thread').forEach(t => {
                const name = t.dataset.name || '';
                t.style.display = name.includes(q) ? '' : 'none';
            });
        });
    }

    // =============================================
    // NEW MESSAGE MODAL
    // =============================================

    window.openHoloNewMessage = function() {
        holoModal.classList.add('active');
        setTimeout(() => holoNmSearchInput?.focus(), 300);
    };

    window.closeHoloNewMessage = function() {
        holoModal.classList.remove('active');
        if (holoNmSearchInput) holoNmSearchInput.value = '';
        resetHoloResults();
    };

    function resetHoloResults() {
        holoNmResults.innerHTML = `
            <div class="holo-modal-state">
                <i class="fa-solid fa-users" style="font-size: 1.5rem; margin-bottom: 12px; opacity: 0.5;"></i>
                <p>Type a name to search</p>
            </div>
        `;
    }

    function showHoloLoading() {
        holoNmResults.innerHTML = `
            <div class="holo-modal-state">
                <div class="holo-modal-spinner"></div>
                <p>Searching...</p>
            </div>
        `;
    }

    async function searchHoloUsers(query) {
        if (query.length < 2) { resetHoloResults(); return; }
        showHoloLoading();
        try {
            const res = await fetch(`${BASE_PATH}/members?q=${encodeURIComponent(query)}&ajax=1`, {
                credentials: 'include',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            const users = data.data || data || [];
            if (users.length) {
                holoNmResults.innerHTML = users.map(u => {
                    const initial = (u.name || 'U').charAt(0).toUpperCase();
                    const avatar = u.avatar_url
                        ? `<img src="${escapeHtml(u.avatar_url)}" alt="" loading="lazy">`
                        : initial;
                    return `
                        <div class="holo-modal-user" onclick="openHoloChat(${u.id}, '${escapeHtml(u.name || 'Unknown')}', '${escapeHtml(u.avatar_url || '')}')">
                            <div class="holo-modal-user-avatar">${avatar}</div>
                            <div class="holo-modal-user-name">${escapeHtml(u.name || 'Unknown')}</div>
                        </div>
                    `;
                }).join('');
            } else {
                holoNmResults.innerHTML = `<div class="holo-modal-state"><p>No members found</p></div>`;
            }
        } catch (e) {
            holoNmResults.innerHTML = `<div class="holo-modal-state"><p>Search failed</p></div>`;
        }
    }

    if (holoNmSearchInput) {
        holoNmSearchInput.addEventListener('input', function() {
            clearTimeout(holoSearchTimeout);
            holoSearchTimeout = setTimeout(() => searchHoloUsers(this.value.trim()), 300);
        });
    }

    holoModal?.addEventListener('click', function(e) {
        if (e.target === this) closeHoloNewMessage();
    });

    // =============================================
    // INLINE CHAT FUNCTIONALITY
    // =============================================

    window.openHoloChat = async function(userId, userName, avatarUrl, isOnline = false) {
        // Close new message modal if open
        closeHoloNewMessage();

        // Set current chat user
        currentChatUserId = userId;
        currentChatUserData = { name: userName, avatar: avatarUrl, online: isOnline };

        // Update thread list to show active state
        holoThreadList?.querySelectorAll('.holo-thread').forEach(t => {
            t.classList.toggle('active', t.dataset.userId == userId);
        });

        // Update chat header
        const initial = (userName || 'U').charAt(0).toUpperCase();
        if (avatarUrl) {
            holoChatAvatar.innerHTML = `<img src="${escapeHtml(avatarUrl)}" alt="" loading="lazy">`;
        } else {
            holoChatAvatar.innerHTML = `<span>${initial}</span>`;
        }
        holoChatName.textContent = userName;
        holoChatStatus.textContent = isOnline ? 'Online' : 'Offline';
        holoChatStatus.className = 'holo-chat-status' + (isOnline ? ' online' : '');
        holoChatProfileLink.href = `${BASE_PATH}/members/${userId}`;

        // Show loading state
        holoWelcomeState.style.display = 'none';
        holoChatArea.style.display = 'none';
        holoChatLoading.style.display = 'flex';

        // Load messages
        await loadHoloMessages(userId);

        // Show chat area
        holoChatLoading.style.display = 'none';
        holoChatArea.style.display = 'flex';

        // Focus input
        holoChatInput?.focus();

        // Start polling for new messages
        startMessagePolling();
    };

    async function loadHoloMessages(userId, append = false) {
        try {
            // Use the poll endpoint with other_user_id parameter (matching thread.php)
            let url = `${BASE_PATH}/api/messages/poll?other_user_id=${userId}`;
            if (append && lastMessageId) {
                url += `&after=${lastMessageId}`;
            }

            const res = await fetch(url, {
                credentials: 'include',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();

            // Handle both response formats: { success, messages } or { data }
            const messages = data.messages || data.data || [];

            if (messages.length > 0 || !append) {
                if (!append) {
                    holoChatMessages.innerHTML = '';
                    lastMessageId = 0;
                }

                if (messages.length > 0) {
                    renderMessages(messages, append);

                    // Update last message ID
                    lastMessageId = Math.max(...messages.map(m => m.id));
                }

                // Scroll to bottom
                if (!append || holoChatMessages.scrollTop + holoChatMessages.clientHeight >= holoChatMessages.scrollHeight - 100) {
                    holoChatMessages.scrollTop = holoChatMessages.scrollHeight;
                }

                // Mark messages as read
                if (!append && messages.length > 0) {
                    markConversationRead(userId);
                }
            }
        } catch (e) {
            console.error('Failed to load messages:', e);
            if (!append) {
                holoChatMessages.innerHTML = `
                    <div style="text-align: center; color: var(--holo-text-muted); padding: 40px;">
                        <i class="fa-solid fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 12px; opacity: 0.5;"></i>
                        <p>Failed to load messages</p>
                    </div>
                `;
            }
        }
    }

    // Mark conversation as read when opening
    async function markConversationRead(userId) {
        try {
            await fetch(`${BASE_PATH}/messages/${userId}/read`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'X-CSRF-Token': CSRF_TOKEN,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            // Update UI - remove unread badge from thread
            const thread = holoThreadList?.querySelector(`.holo-thread[data-user-id="${userId}"]`);
            if (thread) {
                thread.classList.remove('unread');
                const badge = thread.querySelector('.holo-thread-badge');
                if (badge) badge.remove();
            }
        } catch (e) {
            // Silent fail - not critical
        }
    }

    function renderMessages(messages, append = false) {
        let lastDate = null;
        const fragment = document.createDocumentFragment();

        messages.forEach(msg => {
            const msgDate = new Date(msg.created_at).toDateString();

            // Add date separator if new day
            if (msgDate !== lastDate && !append) {
                const sep = document.createElement('div');
                sep.className = 'holo-date-sep';
                sep.innerHTML = `<span>${formatDateSeparator(msg.created_at)}</span>`;
                fragment.appendChild(sep);
                lastDate = msgDate;
            }

            // Create message element
            const isSent = msg.sender_id == CURRENT_USER_ID;
            const msgEl = document.createElement('div');
            msgEl.className = `holo-message ${isSent ? 'sent' : 'received'}`;
            msgEl.dataset.messageId = msg.id;

            const avatarUrl = isSent ? CURRENT_USER_AVATAR : currentChatUserData.avatar;
            const initial = isSent ? CURRENT_USER_NAME.charAt(0).toUpperCase() : currentChatUserData.name.charAt(0).toUpperCase();
            const avatarHtml = avatarUrl
                ? `<img src="${escapeHtml(avatarUrl)}" alt="" loading="lazy">`
                : initial;

            // Check if this is a voice message
            let bubbleContent;
            if (msg.audio_url) {
                const duration = parseInt(msg.audio_duration) || 0;
                const mins = Math.floor(duration / 60);
                const secs = duration % 60;
                const durationStr = `${mins}:${secs.toString().padStart(2, '0')}`;
                bubbleContent = `
                    <div class="holo-voice-message" data-audio-url="${escapeHtml(msg.audio_url)}">
                        <button type="button" class="holo-voice-play-btn" onclick="playHoloVoiceMessage(this)">
                            <i class="fa-solid fa-play"></i>
                        </button>
                        <div class="holo-voice-waveform">
                            <span></span><span></span><span></span><span></span>
                            <span></span><span></span><span></span><span></span>
                        </div>
                        <span class="holo-voice-duration">${durationStr}</span>
                    </div>
                `;
            } else {
                bubbleContent = escapeHtml(msg.body);
            }

            msgEl.innerHTML = `
                <div class="holo-message-avatar">${avatarHtml}</div>
                <div class="holo-message-content">
                    <div class="holo-message-bubble">${bubbleContent}</div>
                    <div class="holo-message-time">${formatMessageTime(msg.created_at)}</div>
                </div>
            `;

            fragment.appendChild(msgEl);
        });

        if (append) {
            holoChatMessages.appendChild(fragment);
        } else {
            holoChatMessages.appendChild(fragment);
        }
    }

    function startMessagePolling() {
        stopMessagePolling();
        messagePollingInterval = setInterval(() => {
            if (currentChatUserId) {
                loadHoloMessages(currentChatUserId, true);
            }
        }, 5000);
    }

    function stopMessagePolling() {
        if (messagePollingInterval) {
            clearInterval(messagePollingInterval);
            messagePollingInterval = null;
        }
    }

    // =============================================
    // SEND MESSAGE
    // =============================================

    async function sendMessage() {
        const body = holoChatInput.value.trim();
        if (!body || !currentChatUserId) return;

        holoSendBtn.disabled = true;
        holoChatInput.disabled = true;

        try {
            const res = await fetch(`${BASE_PATH}/api/messages/send`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    receiver_id: currentChatUserId,
                    body: body
                })
            });

            const data = await res.json();

            if (data.success) {
                holoChatInput.value = '';
                holoChatInput.style.height = 'auto';

                // Add sent message to chat
                if (data.message) {
                    renderMessages([data.message], true);
                    lastMessageId = data.message.id;
                    holoChatMessages.scrollTop = holoChatMessages.scrollHeight;
                } else {
                    // Reload messages if no message returned
                    await loadHoloMessages(currentChatUserId);
                }

                // Update thread list preview
                updateThreadPreview(currentChatUserId, body);
            } else {
                showHoloToast(data.error || 'Failed to send message');
            }
        } catch (e) {
            showHoloToast('Failed to send message');
        }

        holoChatInput.disabled = false;
        holoSendBtn.disabled = false;
        holoChatInput.focus();
        updateSendButton();
    }

    function updateThreadPreview(userId, body) {
        const thread = holoThreadList?.querySelector(`.holo-thread[data-user-id="${userId}"]`);
        if (thread) {
            const preview = thread.querySelector('.holo-thread-preview');
            const time = thread.querySelector('.holo-thread-time');
            if (preview) preview.textContent = body.substring(0, 60) + (body.length > 60 ? '...' : '');
            if (time) time.textContent = 'Now';

            // Move thread to top
            if (holoThreadList && thread.parentNode === holoThreadList) {
                holoThreadList.insertBefore(thread, holoThreadList.firstChild);
            }
        }
    }

    function updateSendButton() {
        const hasContent = holoChatInput.value.trim().length > 0;
        holoSendBtn.disabled = !hasContent;
    }

    // Chat input event listeners
    holoChatInput?.addEventListener('input', function() {
        updateSendButton();
        // Auto-resize textarea
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 150) + 'px';
    });

    holoChatInput?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    holoSendBtn?.addEventListener('click', sendMessage);

    // =============================================
    // THREAD CLICK HANDLERS
    // =============================================

    holoThreadList?.addEventListener('click', function(e) {
        // Ignore clicks on delete button
        if (e.target.closest('.holo-thread-delete')) return;

        const thread = e.target.closest('.holo-thread');
        if (thread) {
            e.preventDefault();
            const userId = thread.dataset.userId;
            const userName = thread.dataset.userName;
            const avatar = thread.dataset.avatar;
            const online = thread.dataset.online === '1';
            openHoloChat(userId, userName, avatar, online);
        }
    });

    // =============================================
    // CONTEXT MENU
    // =============================================

    let contextMenuTarget = null;

    holoThreadList?.addEventListener('contextmenu', function(e) {
        const thread = e.target.closest('.holo-thread');
        if (thread) {
            e.preventDefault();
            showContextMenu(e.clientX, e.clientY, thread);
        }
    });

    function showContextMenu(x, y, thread) {
        contextMenuTarget = thread;
        holoContextMenu.style.left = Math.min(x, window.innerWidth - 200) + 'px';
        holoContextMenu.style.top = Math.min(y, window.innerHeight - 180) + 'px';
        holoContextMenu.classList.add('active');
    }

    function hideContextMenu() {
        holoContextMenu.classList.remove('active');
        contextMenuTarget = null;
    }

    document.addEventListener('click', function(e) {
        if (!holoContextMenu.contains(e.target)) {
            hideContextMenu();
        }
    });

    holoCtxOpen?.addEventListener('click', function() {
        if (contextMenuTarget) {
            const userId = contextMenuTarget.dataset.userId;
            const userName = contextMenuTarget.dataset.userName;
            const avatar = contextMenuTarget.dataset.avatar;
            const online = contextMenuTarget.dataset.online === '1';
            openHoloChat(userId, userName, avatar, online);
        }
        hideContextMenu();
    });

    holoCtxProfile?.addEventListener('click', function() {
        if (contextMenuTarget) {
            window.location.href = `${BASE_PATH}/members/${contextMenuTarget.dataset.userId}`;
        }
        hideContextMenu();
    });

    holoCtxDelete?.addEventListener('click', function() {
        if (contextMenuTarget) {
            showHoloDeleteConfirm(contextMenuTarget);
        }
        hideContextMenu();
    });

    // =============================================
    // DELETE FUNCTIONALITY
    // =============================================

    let deleteTarget = null;

    function showHoloDeleteConfirm(thread) {
        deleteTarget = thread;
        holoDeleteUserName.textContent = thread.dataset.userName;
        holoDeleteOverlay.classList.add('active');
    }

    function hideHoloDeleteConfirm() {
        holoDeleteOverlay.classList.remove('active');
        deleteTarget = null;
    }

    async function deleteHoloConversation() {
        if (!deleteTarget) return;

        const userId = deleteTarget.dataset.userId;
        const thread = deleteTarget;

        hideHoloDeleteConfirm();

        // Optimistic UI
        thread.style.opacity = '0.5';

        try {
            const res = await fetch(`${BASE_PATH}/api/messages/delete-conversation`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ other_user_id: userId })
            });

            const data = await res.json();

            if (data.success) {
                thread.style.transition = 'opacity 0.3s, transform 0.3s, max-height 0.3s';
                thread.style.opacity = '0';
                thread.style.transform = 'translateX(-100%)';
                setTimeout(() => thread.remove(), 300);

                // If currently viewing this conversation, go back to welcome
                if (currentChatUserId == userId) {
                    currentChatUserId = null;
                    currentChatUserData = null;
                    stopMessagePolling();
                    holoChatArea.style.display = 'none';
                    holoWelcomeState.style.display = 'flex';
                }

                // Update count
                const count = document.getElementById('holoConvCount');
                if (count) {
                    const remaining = holoThreadList.querySelectorAll('.holo-thread').length - 1;
                    count.textContent = `${remaining} conversation${remaining !== 1 ? 's' : ''}`;
                }

                showHoloToast('Conversation deleted');
            } else {
                thread.style.opacity = '1';
                showHoloToast(data.error || 'Failed to delete');
            }
        } catch (e) {
            thread.style.opacity = '1';
            showHoloToast('Failed to delete conversation');
        }
    }

    // Delete button in thread list (hover)
    holoThreadList?.addEventListener('click', function(e) {
        const deleteBtn = e.target.closest('.holo-thread-delete');
        if (deleteBtn) {
            e.preventDefault();
            e.stopPropagation();
            const thread = deleteBtn.closest('.holo-thread');
            if (thread) {
                showHoloDeleteConfirm(thread);
            }
        }
    });

    // Delete button in chat header
    holoChatDeleteBtn?.addEventListener('click', function() {
        const thread = holoThreadList?.querySelector(`.holo-thread[data-user-id="${currentChatUserId}"]`);
        if (thread) {
            showHoloDeleteConfirm(thread);
        } else if (currentChatUserId && currentChatUserData) {
            // Create a fake thread element for deletion
            deleteTarget = { dataset: { userId: currentChatUserId, userName: currentChatUserData.name } };
            holoDeleteUserName.textContent = currentChatUserData.name;
            holoDeleteOverlay.classList.add('active');
        }
    });

    holoDeleteCancel?.addEventListener('click', hideHoloDeleteConfirm);
    holoDeleteConfirm?.addEventListener('click', deleteHoloConversation);

    holoDeleteOverlay?.addEventListener('click', function(e) {
        if (e.target === this) hideHoloDeleteConfirm();
    });

    // =============================================
    // VOICE RECORDING FUNCTIONALITY
    // =============================================

    const holoVoiceBtn = document.getElementById('holoVoiceBtn');
    const holoVoiceOverlay = document.getElementById('holoVoiceOverlay');
    const holoVoiceTime = document.getElementById('holoVoiceTime');
    const holoVoiceCancel = document.getElementById('holoVoiceCancel');
    const holoVoiceSend = document.getElementById('holoVoiceSend');

    let mediaRecorder = null;
    let audioChunks = [];
    let audioStream = null;
    let recordingStartTime = 0;
    let recordingTimer = null;
    let currentAudio = null;
    let currentPlayingBtn = null;

    // Check if voice recording is supported
    function isVoiceRecordingSupported() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder);
    }

    // Hide voice button if not supported
    if (!isVoiceRecordingSupported() && holoVoiceBtn) {
        holoVoiceBtn.style.display = 'none';
    }

    // Start recording
    async function startHoloRecording() {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            return;
        }

        try {
            if (!isVoiceRecordingSupported()) {
                showHoloToast('Voice recording is not supported in your browser');
                return;
            }

            audioStream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    sampleRate: 44100
                }
            });

            let mimeType = 'audio/webm';
            if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                mimeType = 'audio/webm;codecs=opus';
            } else if (MediaRecorder.isTypeSupported('audio/mp4')) {
                mimeType = 'audio/mp4';
            } else if (MediaRecorder.isTypeSupported('audio/ogg')) {
                mimeType = 'audio/ogg';
            }

            mediaRecorder = new MediaRecorder(audioStream, { mimeType });
            audioChunks = [];

            mediaRecorder.ondataavailable = (e) => {
                if (e.data.size > 0) {
                    audioChunks.push(e.data);
                }
            };

            mediaRecorder.start(100);
            recordingStartTime = Date.now();

            // Show overlay and update button
            holoVoiceOverlay.classList.add('active');
            holoVoiceBtn.classList.add('recording');

            // Start timer
            recordingTimer = setInterval(updateHoloRecordingTime, 100);

        } catch (err) {
            console.error('Failed to start recording:', err);
            showHoloToast('Could not access microphone');
        }
    }

    // Update recording time display
    function updateHoloRecordingTime() {
        const elapsed = Math.floor((Date.now() - recordingStartTime) / 1000);
        const mins = Math.floor(elapsed / 60);
        const secs = elapsed % 60;
        holoVoiceTime.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    // Cancel recording
    function cancelHoloRecording() {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
        }
        clearInterval(recordingTimer);
        holoVoiceOverlay.classList.remove('active');
        holoVoiceBtn.classList.remove('recording');
        audioChunks = [];
        holoVoiceTime.textContent = '0:00';

        // Stop audio stream
        if (audioStream) {
            audioStream.getTracks().forEach(track => track.stop());
            audioStream = null;
        }
    }

    // Send voice message
    async function sendHoloVoiceMessage() {
        if (!mediaRecorder || mediaRecorder.state !== 'recording') return;

        const duration = Math.floor((Date.now() - recordingStartTime) / 1000);
        clearInterval(recordingTimer);

        mediaRecorder.stop();
        mediaRecorder.onstop = async () => {
            holoVoiceOverlay.classList.remove('active');
            holoVoiceBtn.classList.remove('recording');

            if (audioChunks.length === 0 || !currentChatUserId) {
                holoVoiceTime.textContent = '0:00';
                return;
            }

            const audioBlob = new Blob(audioChunks, { type: mediaRecorder.mimeType });
            const reader = new FileReader();

            // Create temp blob URL for immediate playback
            const blobUrl = URL.createObjectURL(audioBlob);

            // Format duration
            const mins = Math.floor(duration / 60);
            const secs = duration % 60;
            const durationStr = `${mins}:${secs.toString().padStart(2, '0')}`;

            // Optimistic UI - add voice message bubble
            const tempId = 'temp-voice-' + Date.now();
            const avatarHtml = CURRENT_USER_AVATAR
                ? `<img src="${escapeHtml(CURRENT_USER_AVATAR)}" alt="" loading="lazy">`
                : CURRENT_USER_NAME.charAt(0).toUpperCase();

            const bubble = document.createElement('div');
            bubble.className = 'holo-message sent';
            bubble.id = tempId;
            bubble.innerHTML = `
                <div class="holo-message-avatar">${avatarHtml}</div>
                <div class="holo-message-content">
                    <div class="holo-message-bubble">
                        <div class="holo-voice-message" data-audio-url="${blobUrl}">
                            <button type="button" class="holo-voice-play-btn" onclick="playHoloVoiceMessage(this)">
                                <i class="fa-solid fa-play"></i>
                            </button>
                            <div class="holo-voice-waveform">
                                <span></span><span></span><span></span><span></span>
                                <span></span><span></span><span></span><span></span>
                            </div>
                            <span class="holo-voice-duration">${durationStr}</span>
                        </div>
                    </div>
                    <div class="holo-message-time">Sending...</div>
                </div>
            `;
            holoChatMessages.appendChild(bubble);
            holoChatMessages.scrollTop = holoChatMessages.scrollHeight;

            // Send to server using form-urlencoded (required by VoiceMessageController)
            reader.onloadend = async () => {
                const base64data = reader.result.split(',')[1];
                try {
                    const res = await fetch(`${BASE_PATH}/api/messages/voice`, {
                        method: 'POST',
                        credentials: 'include',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: new URLSearchParams({
                            csrf_token: CSRF_TOKEN,
                            receiver_id: currentChatUserId,
                            audio_data: base64data,
                            mime_type: mediaRecorder.mimeType,
                            duration: duration
                        })
                    });

                    const data = await res.json();

                    if (data.success) {
                        // Update the temp bubble with real data
                        const timeEl = bubble.querySelector('.holo-message-time');
                        if (timeEl) timeEl.textContent = 'Just now';

                        const voiceMsg = bubble.querySelector('.holo-voice-message');
                        if (voiceMsg && data.audio_url) {
                            voiceMsg.dataset.audioUrl = data.audio_url;
                        }

                        if (data.message_id) {
                            bubble.dataset.messageId = data.message_id;
                            lastMessageId = Math.max(lastMessageId, data.message_id);
                        }

                        // Update thread preview
                        updateThreadPreview(currentChatUserId, '🎤 Voice message');
                    } else {
                        bubble.remove();
                        showHoloToast(data.error || 'Failed to send voice message');
                    }
                } catch (e) {
                    bubble.remove();
                    showHoloToast('Failed to send voice message');
                }
            };

            reader.readAsDataURL(audioBlob);
            audioChunks = [];
            holoVoiceTime.textContent = '0:00';

            // Stop audio stream
            if (audioStream) {
                audioStream.getTracks().forEach(track => track.stop());
                audioStream = null;
            }
        };
    }

    // Play voice message
    window.playHoloVoiceMessage = function(btn) {
        const voiceMessage = btn.closest('.holo-voice-message');
        const audioUrl = voiceMessage?.dataset.audioUrl;

        if (!audioUrl) {
            console.error('No audio URL found');
            return;
        }

        // If same audio is playing, pause it
        if (currentPlayingBtn === btn && currentAudio && !currentAudio.paused) {
            currentAudio.pause();
            btn.querySelector('i').className = 'fa-solid fa-play';
            btn.classList.remove('playing');
            return;
        }

        // Stop any currently playing audio
        if (currentAudio) {
            currentAudio.pause();
            if (currentPlayingBtn) {
                currentPlayingBtn.querySelector('i').className = 'fa-solid fa-play';
                currentPlayingBtn.classList.remove('playing');
            }
        }

        // Create and play new audio
        currentAudio = new Audio(audioUrl);
        currentPlayingBtn = btn;

        btn.querySelector('i').className = 'fa-solid fa-pause';
        btn.classList.add('playing');

        currentAudio.play().catch(err => {
            console.error('Audio playback failed:', err);
            btn.querySelector('i').className = 'fa-solid fa-play';
            btn.classList.remove('playing');
            showHoloToast('Failed to play voice message');
        });

        currentAudio.onended = () => {
            btn.querySelector('i').className = 'fa-solid fa-play';
            btn.classList.remove('playing');
            currentPlayingBtn = null;
        };
    };

    // Event listeners for voice recording
    holoVoiceBtn?.addEventListener('click', () => {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            // If already recording, don't start new
            return;
        }
        startHoloRecording();
    });

    holoVoiceCancel?.addEventListener('click', cancelHoloRecording);
    holoVoiceSend?.addEventListener('click', sendHoloVoiceMessage);

    // =============================================
    // KEYBOARD SHORTCUTS
    // =============================================

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (holoVoiceOverlay?.classList.contains('active')) {
                cancelHoloRecording();
            } else if (holoDeleteOverlay?.classList.contains('active')) {
                hideHoloDeleteConfirm();
            } else if (holoContextMenu?.classList.contains('active')) {
                hideContextMenu();
            } else if (holoModal?.classList.contains('active')) {
                closeHoloNewMessage();
            }
        }
    });

    // =============================================
    // CLEANUP ON PAGE UNLOAD
    // =============================================

    // Cleanup function to remove messages-page classes
    function cleanupMessagesPage() {
        document.documentElement.classList.remove('messages-page');
        document.body.classList.remove('messages-page', 'no-ptr', 'messages-fullscreen');
        stopMessagePolling();
        if (currentAudio) {
            currentAudio.pause();
            currentAudio = null;
        }
        if (audioStream) {
            audioStream.getTracks().forEach(track => track.stop());
        }
    }

    // Clean up when navigating away
    window.addEventListener('beforeunload', cleanupMessagesPage);

    // Also handle pagehide for bfcache
    window.addEventListener('pagehide', cleanupMessagesPage);
})();
</script>

<!-- Clean up messages-page class on other pages (handles bfcache restoration) -->
<script>
(function() {
    // When returning to a cached page, remove messages-page classes if we're not on messages
    window.addEventListener('pageshow', function(event) {
        if (event.persisted || (window.performance && window.performance.getEntriesByType('navigation')[0]?.type === 'back_forward')) {
            // Page was restored from bfcache - classes should already be removed by pagehide
            // But double-check we're on the messages page before re-adding
            if (!window.location.pathname.includes('/messages')) {
                document.documentElement.classList.remove('messages-page');
                document.body.classList.remove('messages-page', 'no-ptr', 'messages-fullscreen');
            }
        }
    });
})();
</script>
</main>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
