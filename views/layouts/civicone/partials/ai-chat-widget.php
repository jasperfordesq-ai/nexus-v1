<?php
/**
 * Floating AI Chat Widget
 *
 * A floating bubble chat interface for AI assistant access from any page.
 * Hidden by default - user must enable in Settings > Appearance.
 */

// Check if user has AI widget enabled (defaults to OFF)
// Read from cookie - user can enable in Settings > Appearance
$aiWidgetEnabled = isset($_COOKIE['ai_widget_enabled']) && $_COOKIE['ai_widget_enabled'] === '1';

// Don't show if user hasn't enabled the widget
if (!$aiWidgetEnabled) return;

// Only show for logged in users
if (!isset($_SESSION['user_id'])) return;

// Check if AI is enabled globally
$aiEnabled = true;
try {
    if (class_exists('Nexus\Services\AI\AIServiceFactory')) {
        $aiEnabled = \Nexus\Services\AI\AIServiceFactory::isEnabled();
    }
} catch (Exception $e) {
    $aiEnabled = false;
}

if (!$aiEnabled) return;

// Don't show on AI page itself
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (strpos($currentPath, '/ai') === 0) return;

$basePath = Nexus\Core\TenantContext::getBasePath();

// Check if user has AI pulse animation enabled (defaults to false)
// Read from cookie - user can enable in Settings > Appearance
$aiPulseEnabled = isset($_COOKIE['ai_pulse_enabled']) && $_COOKIE['ai_pulse_enabled'] === '1';
?>

<!-- AI Chat Widget -->
<div id="ai-chat-widget" class="ai-widget-container<?= $aiPulseEnabled ? ' pulse-enabled' : '' ?>">
    <!-- Backdrop for mobile drawer -->
    <div id="ai-widget-backdrop" class="ai-widget-backdrop"></div>

    <!-- Floating Button -->
    <button id="ai-widget-toggle" class="ai-widget-fab" aria-label="Open AI Assistant" title="AI Assistant">
        <span class="ai-fab-icon ai-fab-icon-default">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                <path d="M2 17l10 5 10-5"/>
                <path d="M2 12l10 5 10-5"/>
            </svg>
        </span>
        <span class="ai-fab-icon ai-fab-icon-close hidden">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </span>
    </button>

    <!-- Chat Panel -->
    <div id="ai-widget-panel" class="ai-widget-panel" aria-hidden="true">
        <!-- Drag handle for mobile bottom sheet -->
        <div class="ai-widget-drag-handle" id="ai-widget-drag-handle"></div>

        <div class="ai-widget-header">
            <div class="ai-widget-header-info">
                <div class="ai-widget-avatar">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                        <path d="M2 17l10 5 10-5"/>
                        <path d="M2 12l10 5 10-5"/>
                    </svg>
                </div>
                <div>
                    <div class="ai-widget-title">AI Assistant</div>
                    <div class="ai-widget-status">
                        <span class="ai-status-dot"></span>
                        <span class="ai-status-text">Ready to help</span>
                    </div>
                </div>
            </div>
            <div class="ai-widget-actions">
                <a href="<?= $basePath ?>/ai" class="ai-widget-expand" title="Open full AI page">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>
                    </svg>
                </a>
                <button class="ai-widget-close" id="ai-widget-close" aria-label="Close">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>

        <div class="ai-widget-messages" id="ai-widget-messages">
            <div class="ai-widget-welcome">
                <div class="ai-welcome-icon">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                        <path d="M2 17l10 5 10-5"/>
                        <path d="M2 12l10 5 10-5"/>
                    </svg>
                </div>
                <h4>Hello! How can I help?</h4>
                <p>Ask me anything about the platform, timebanking, your listings, or finding community connections.</p>
                <div class="ai-quick-prompts">
                    <button class="ai-quick-prompt" data-prompt="How do I earn time credits?">How do I earn credits?</button>
                    <button class="ai-quick-prompt" data-prompt="What can I offer on the timebank?">What can I offer?</button>
                    <button class="ai-quick-prompt" data-prompt="How do I find help in my area?">Find local help</button>
                </div>
            </div>
        </div>

        <div class="ai-widget-input-area">
            <div class="ai-widget-input-wrapper">
                <textarea
                    id="ai-widget-input"
                    class="ai-widget-input"
                    placeholder="Type your message..."
                    rows="1"
                    maxlength="2000"
                ></textarea>
                <button id="ai-widget-send" class="ai-widget-send" aria-label="Send message" disabled>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>
            <div class="ai-widget-footer">
                <span class="ai-widget-quota" id="ai-widget-quota">
                    <span id="quota-daily-used">-</span>/<span id="quota-daily-limit">-</span> today
                </span>
                <span class="ai-widget-powered">Powered by AI</span>
            </div>
        </div>
    </div>
</div>

<style>
/* AI Chat Widget Styles */
.ai-widget-container {
    position: fixed;
    bottom: 100px;
    right: 20px;
    z-index: 99990;
    font-family: inherit;
}

@media (max-width: 768px) {
    .ai-widget-container {
        bottom: 80px; /* Above bottom nav */
        right: 12px;
        z-index: 99990 !important;
    }

    .ai-widget-fab {
        width: 50px;
        height: 50px;
        z-index: 99990 !important;
        /* Ensure touch target is accessible */
        -webkit-tap-highlight-color: transparent;
        touch-action: manipulation;
    }
}

/* Floating Action Button */
.ai-widget-fab {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
    color: white;
    box-shadow:
        0 4px 20px rgba(99, 102, 241, 0.4),
        0 0 40px rgba(99, 102, 241, 0.2);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: visible;
}

.ai-widget-fab:hover {
    transform: scale(1.08);
    box-shadow:
        0 6px 30px rgba(99, 102, 241, 0.5),
        0 0 60px rgba(99, 102, 241, 0.3);
}

.ai-widget-fab:active {
    transform: scale(0.95);
}

.ai-fab-pulse {
    position: absolute;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    opacity: 0;
    animation: fabPulse 2s ease-out infinite;
}

@keyframes fabPulse {
    0% {
        transform: scale(1);
        opacity: 0.5;
    }
    100% {
        transform: scale(1.8);
        opacity: 0;
    }
}

.ai-widget-fab.open .ai-fab-icon-default {
    display: none;
}

.ai-widget-fab.open .ai-fab-icon-close {
    display: flex !important;
}

.ai-widget-fab.open .ai-fab-pulse {
    display: none;
}

/* Chat Panel */
.ai-widget-panel {
    position: absolute;
    bottom: 70px;
    right: 0;
    width: 380px;
    max-width: calc(100vw - 32px);
    height: 520px;
    max-height: calc(100vh - 200px);
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 20px;
    box-shadow:
        0 20px 60px rgba(0, 0, 0, 0.15),
        0 0 100px rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.5);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    opacity: 0;
    visibility: hidden;
    transform: translateY(20px) scale(0.95);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

[data-theme="dark"] .ai-widget-panel {
    background: rgba(15, 23, 42, 0.95);
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow:
        0 20px 60px rgba(0, 0, 0, 0.4),
        0 0 100px rgba(99, 102, 241, 0.15);
}

/* ============================================
   MOBILE BOTTOM SHEET / DRAWER
   ============================================ */
@media (max-width: 768px) {
    /* Backdrop overlay for mobile - lighter like Meta */
    .ai-widget-backdrop {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.35); /* Lighter backdrop */
        z-index: 99991;
        opacity: 0;
        transition: opacity 0.25s ease;
        -webkit-tap-highlight-color: transparent;
    }

    .ai-widget-backdrop.visible {
        display: block;
        opacity: 1;
    }

    /* Bottom sheet drawer style - Meta/WhatsApp style compact drawer */
    .ai-widget-panel {
        position: fixed !important;
        top: auto !important;
        bottom: 0 !important;
        left: 0 !important;
        right: 0 !important;
        width: 100% !important;
        /* Key: Use max-height instead of fixed height - drawer sizes to content */
        height: auto !important;
        max-height: 60vh !important; /* Cap at 60% of viewport - Meta style */
        min-height: 280px !important; /* Minimum usable size */
        max-width: 100% !important;
        border-radius: 20px 20px 0 0 !important;
        transform: translateY(100%) !important;
        opacity: 1 !important;
        visibility: hidden;
        transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1),
                    visibility 0.3s !important;
        box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.12) !important;
        border: none !important;
        border-top: 1px solid rgba(0, 0, 0, 0.08) !important;
        z-index: 99992 !important;
    }

    [data-theme="dark"] .ai-widget-panel {
        border-top-color: rgba(255, 255, 255, 0.1);
        box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.4);
    }

    .ai-widget-panel.open {
        transform: translateY(0) !important;
        visibility: visible !important;
        z-index: 99992 !important;
    }

    /* Drag handle for bottom sheet - compact */
    .ai-widget-drag-handle {
        display: flex;
        justify-content: center;
        padding: 8px 0 4px;
        cursor: grab;
        flex-shrink: 0;
    }

    .ai-widget-drag-handle::before {
        content: '';
        width: 32px;
        height: 4px;
        background: rgba(0, 0, 0, 0.15);
        border-radius: 2px;
    }

    [data-theme="dark"] .ai-widget-drag-handle::before {
        background: rgba(255, 255, 255, 0.25);
    }

    /* Header adjustments for mobile drawer - compact */
    .ai-widget-header {
        padding: 6px 14px 10px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.06);
        flex-shrink: 0;
    }

    [data-theme="dark"] .ai-widget-header {
        border-bottom-color: rgba(255, 255, 255, 0.08);
    }

    .ai-widget-avatar {
        width: 32px;
        height: 32px;
        border-radius: 8px;
    }

    .ai-widget-title {
        font-size: 0.9rem;
    }

    .ai-widget-status {
        font-size: 0.7rem;
    }

    /* Messages area - scrollable within compact drawer */
    .ai-widget-messages {
        flex: 1;
        min-height: 0; /* Critical for flex scroll */
        padding: 10px 14px;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        max-height: calc(60vh - 140px); /* Account for header + input */
    }

    /* Compact welcome screen */
    .ai-widget-welcome {
        padding: 12px 10px;
        height: auto;
    }

    .ai-welcome-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        margin-bottom: 10px;
    }

    .ai-welcome-icon svg {
        width: 24px;
        height: 24px;
    }

    .ai-widget-welcome h4 {
        font-size: 1rem;
        margin-bottom: 4px;
    }

    .ai-widget-welcome p {
        font-size: 0.8rem;
        margin-bottom: 12px;
        line-height: 1.4;
    }

    .ai-quick-prompts {
        gap: 6px;
    }

    .ai-quick-prompt {
        padding: 10px 14px;
        font-size: 0.85rem;
        border-radius: 12px;
    }

    .ai-widget-message {
        max-width: 85%;
        padding: 10px 14px;
        font-size: 0.9rem;
        border-radius: 16px;
    }

    /* Input area at bottom - compact */
    .ai-widget-input-area {
        padding: 12px 14px;
        padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px));
        border-top: 1px solid rgba(0, 0, 0, 0.08);
        background: var(--feed-bg-card, #fff);
        flex-shrink: 0;
    }

    [data-theme="dark"] .ai-widget-input-area {
        background: #1e1e1e;
        border-top-color: rgba(255, 255, 255, 0.1);
    }

    .ai-widget-input-wrapper {
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(0, 0, 0, 0.05);
        border: 1px solid rgba(0, 0, 0, 0.1);
        border-radius: 24px;
        padding: 8px 10px 8px 16px;
    }

    [data-theme="dark"] .ai-widget-input-wrapper {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(255, 255, 255, 0.15);
    }

    .ai-widget-input {
        flex: 1;
        font-size: 16px; /* Prevents iOS zoom on focus */
        min-height: 36px;
        padding: 8px 0;
        background: transparent !important;
        border: none !important;
        color: var(--feed-text-primary, #111);
        line-height: 1.4;
    }

    [data-theme="dark"] .ai-widget-input {
        color: #f1f5f9 !important;
    }

    .ai-widget-input::placeholder {
        color: rgba(0, 0, 0, 0.4);
    }

    [data-theme="dark"] .ai-widget-input::placeholder {
        color: rgba(255, 255, 255, 0.4);
    }

    .ai-widget-send {
        width: 38px;
        height: 38px;
        min-width: 38px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    /* Hide expand button on mobile */
    .ai-widget-expand {
        display: none;
    }

    /* Close button styling for mobile */
    .ai-widget-close {
        width: 32px;
        height: 32px;
        border-radius: 50%;
    }

    /* Hide footer quota on mobile for compactness */
    .ai-widget-footer {
        display: none;
    }
}

/* Tablet-sized screens - use floating panel */
@media (min-width: 769px) and (max-width: 1024px) {
    .ai-widget-panel {
        width: 360px;
        height: 480px;
        bottom: 70px;
    }
}

/* Hide mobile-only elements on desktop */
@media (min-width: 769px) {
    .ai-widget-drag-handle {
        display: none;
    }

    .ai-widget-backdrop {
        display: none !important;
    }

    /* Desktop open state - scale up from small */
    .ai-widget-panel.open {
        opacity: 1;
        visibility: visible;
        transform: translateY(0) scale(1);
    }
}

/* Body scroll lock when drawer is open on mobile */
body.ai-drawer-open {
    overflow: hidden;
    position: fixed;
    width: 100%;
    height: 100%;
}

/* Hide bottom navigation when AI drawer is open */
body.ai-drawer-open .nexus-native-nav,
body.ai-drawer-open .mobile-tab-bar,
body.ai-drawer-open #mobileTabBar,
body.ai-drawer-open .mobile-bottom-nav,
body.ai-drawer-open [class*="bottom-nav"],
body.ai-drawer-open nav[role="navigation"] {
    transform: translateY(100%) !important;
    opacity: 0 !important;
    pointer-events: none !important;
    transition: transform 0.25s ease, opacity 0.25s ease !important;
}

/* Also hide the AI FAB button itself when drawer is open */
body.ai-drawer-open .ai-widget-fab {
    transform: scale(0) !important;
    opacity: 0 !important;
    pointer-events: none !important;
    transition: transform 0.2s ease, opacity 0.2s ease !important;
}

/* Header */
.ai-widget-header {
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    background: linear-gradient(180deg, rgba(99, 102, 241, 0.08) 0%, transparent 100%);
}

[data-theme="dark"] .ai-widget-header {
    border-bottom-color: rgba(255, 255, 255, 0.08);
}

.ai-widget-header-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.ai-widget-avatar {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.ai-widget-title {
    font-weight: 700;
    font-size: 1rem;
    color: var(--htb-text-main, #1e293b);
}

[data-theme="dark"] .ai-widget-title {
    color: #f1f5f9;
}

.ai-widget-status {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    color: var(--htb-text-muted, #64748b);
}

.ai-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10b981;
    animation: statusPulse 2s ease-in-out infinite;
}

@keyframes statusPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.ai-widget-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.ai-widget-expand,
.ai-widget-close {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    background: transparent;
    color: var(--htb-text-muted, #64748b);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.ai-widget-expand:hover,
.ai-widget-close:hover {
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
}

.ai-widget-expand:focus-visible,
.ai-widget-close:focus-visible {
    outline: 2px solid #6366f1;
    outline-offset: 2px;
}

.ai-widget-expand:active,
.ai-widget-close:active {
    transform: scale(0.9);
}

/* Messages Area */
.ai-widget-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    scroll-behavior: smooth;
    overscroll-behavior: contain;
}

/* Welcome Screen */
.ai-widget-welcome {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 30px 20px;
    height: 100%;
}

.ai-welcome-icon {
    width: 64px;
    height: 64px;
    border-radius: 20px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6366f1;
    margin-bottom: 16px;
}

.ai-widget-welcome h4 {
    margin: 0 0 8px;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--htb-text-main, #1e293b);
}

[data-theme="dark"] .ai-widget-welcome h4 {
    color: #f1f5f9;
}

.ai-widget-welcome p {
    margin: 0 0 20px;
    font-size: 0.9rem;
    color: var(--htb-text-muted, #64748b);
    line-height: 1.5;
}

.ai-quick-prompts {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
}

.ai-quick-prompt {
    padding: 10px 16px;
    border-radius: 12px;
    border: 1px solid rgba(99, 102, 241, 0.2);
    background: rgba(99, 102, 241, 0.05);
    color: #6366f1;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
}

.ai-quick-prompt:hover {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.3);
}

/* Message Bubbles */
.ai-widget-message {
    max-width: 85%;
    padding: 12px 16px;
    border-radius: 16px;
    font-size: 0.9rem;
    line-height: 1.5;
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

.ai-widget-message.user {
    align-self: flex-end;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border-bottom-right-radius: 4px;
}

.ai-widget-message.assistant {
    align-self: flex-start;
    background: rgba(0, 0, 0, 0.04);
    color: var(--htb-text-main, #1e293b);
    border-bottom-left-radius: 4px;
}

[data-theme="dark"] .ai-widget-message.assistant {
    background: rgba(255, 255, 255, 0.08);
    color: #e2e8f0;
}

/* Markdown styles in widget messages */
.ai-widget-message code {
    background: rgba(99, 102, 241, 0.15);
    padding: 0.1rem 0.3rem;
    border-radius: 3px;
    font-family: monospace;
    font-size: 0.85em;
}

.ai-widget-message.user code {
    background: rgba(255, 255, 255, 0.2);
}

.ai-widget-message pre {
    background: rgba(0, 0, 0, 0.08);
    padding: 0.5rem;
    border-radius: 6px;
    overflow-x: auto;
    margin: 0.25rem 0;
}

[data-theme="dark"] .ai-widget-message pre {
    background: rgba(0, 0, 0, 0.3);
}

.ai-widget-message pre code {
    background: transparent;
    padding: 0;
}

.ai-widget-message strong {
    font-weight: 600;
}

.ai-widget-message li {
    margin-left: 1rem;
}

.ai-widget-message a {
    color: #6366f1;
    text-decoration: underline;
}

.ai-widget-message.user a {
    color: rgba(255, 255, 255, 0.9);
}

.ai-widget-typing {
    display: flex;
    gap: 4px;
    padding: 12px 16px;
    align-self: flex-start;
}

.ai-typing-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #6366f1;
    animation: typingBounce 1.4s ease-in-out infinite;
}

.ai-typing-dot:nth-child(2) { animation-delay: 0.2s; }
.ai-typing-dot:nth-child(3) { animation-delay: 0.4s; }

@keyframes typingBounce {
    0%, 80%, 100% { transform: translateY(0); opacity: 0.5; }
    40% { transform: translateY(-6px); opacity: 1; }
}

/* Input Area */
.ai-widget-input-area {
    padding: 12px 16px 16px;
    border-top: 1px solid rgba(0, 0, 0, 0.06);
    background: rgba(248, 250, 252, 0.8);
    flex-shrink: 0;
}

[data-theme="dark"] .ai-widget-input-area {
    border-top-color: rgba(255, 255, 255, 0.1);
    background: rgba(15, 15, 15, 0.95);
}

.ai-widget-input-wrapper {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 16px;
    padding: 8px 8px 8px 16px;
    transition: all 0.2s;
    width: 100%;
    box-sizing: border-box;
}

[data-theme="dark"] .ai-widget-input-wrapper {
    background: #1a1a2e !important;
    border-color: rgba(255, 255, 255, 0.2) !important;
}

.ai-widget-input-wrapper:focus-within {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] .ai-widget-input-wrapper:focus-within {
    background: #1a1a2e !important;
    border-color: #818cf8 !important;
    box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.2);
}

.ai-widget-input {
    flex: 1;
    min-width: 0;
    width: 100%;
    border: none !important;
    background: transparent !important;
    resize: none;
    font-family: inherit;
    font-size: 0.95rem;
    line-height: 1.5;
    color: var(--htb-text-main, #1e293b);
    outline: none !important;
    max-height: 100px;
    min-height: 24px;
    padding: 4px 0;
    box-sizing: border-box;
}

[data-theme="dark"] .ai-widget-input {
    color: #f1f5f9 !important;
    caret-color: #f1f5f9;
    -webkit-text-fill-color: #f1f5f9 !important;
}

[data-theme="dark"] .ai-widget-input:focus {
    color: #f1f5f9 !important;
    -webkit-text-fill-color: #f1f5f9 !important;
    background: transparent !important;
}

/* High specificity override for dark mode input - ensures text is always visible */
[data-theme="dark"] #ai-widget-input,
[data-theme="dark"] #ai-widget-input:focus,
[data-theme="dark"] #ai-widget-input:active,
[data-theme="dark"] .ai-widget-panel .ai-widget-input,
[data-theme="dark"] .ai-widget-panel .ai-widget-input:focus {
    color: #f1f5f9 !important;
    -webkit-text-fill-color: #f1f5f9 !important;
    background-color: transparent !important;
}

.ai-widget-input::placeholder {
    color: var(--htb-text-muted, #94a3b8);
}

[data-theme="dark"] .ai-widget-input::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

.ai-widget-send {
    width: 36px;
    height: 36px;
    border-radius: 12px;
    border: none;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    flex-shrink: 0;
}

.ai-widget-send:hover:not(:disabled) {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.ai-widget-send:active:not(:disabled) {
    transform: scale(0.92);
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.2);
}

.ai-widget-send:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: linear-gradient(135deg, #94a3b8, #64748b);
}

.ai-widget-footer {
    text-align: center;
    margin-top: 8px;
}

.ai-widget-powered {
    font-size: 0.7rem;
    color: var(--htb-text-muted, #94a3b8);
}

.ai-widget-quota {
    font-size: 0.7rem;
    color: var(--htb-text-muted, #94a3b8);
    background: rgba(99, 102, 241, 0.1);
    padding: 2px 8px;
    border-radius: 10px;
}

/* Streaming cursor in widget */
.streaming-cursor {
    display: inline-block;
    width: 2px;
    height: 1em;
    background: #6366f1;
    margin-left: 2px;
    animation: widgetCursorBlink 0.8s ease-in-out infinite;
    vertical-align: text-bottom;
}

@keyframes widgetCursorBlink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0; }
}

/* Copy button in widget */
.ai-widget-message-actions {
    display: flex;
    gap: 4px;
    margin-top: 6px;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.ai-widget-message:hover .ai-widget-message-actions {
    opacity: 1;
}

.ai-widget-copy-btn {
    padding: 3px 6px;
    background: rgba(0, 0, 0, 0.05);
    border: none;
    border-radius: 4px;
    font-size: 0.7rem;
    color: var(--htb-text-muted, #64748b);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 3px;
    transition: all 0.15s ease;
}

.ai-widget-copy-btn:hover {
    background: rgba(99, 102, 241, 0.15);
    color: #6366f1;
}

.ai-widget-copy-btn.copied {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

[data-theme="dark"] .ai-widget-copy-btn {
    background: rgba(255, 255, 255, 0.08);
}

/* Hide widget on very small screens in landscape */
@media (max-height: 400px) {
    .ai-widget-container {
        display: none;
    }
}

/* When keyboard is likely open on mobile, use minimal drawer */
@media (max-width: 768px) and (max-height: 500px) {
    .ai-widget-panel {
        max-height: 50vh !important;
        min-height: 200px !important;
    }

    .ai-widget-welcome {
        padding: 8px;
    }

    .ai-welcome-icon {
        display: none; /* Hide icon when keyboard open */
    }

    .ai-quick-prompts {
        display: none; /* Hide prompts when keyboard open */
    }

    .ai-widget-messages {
        max-height: calc(50vh - 120px);
    }
}

/* Safe area insets for notched devices */
@supports (padding-bottom: env(safe-area-inset-bottom)) {
    @media (max-width: 768px) {
        .ai-widget-container {
            bottom: calc(80px + env(safe-area-inset-bottom));
        }

        /* Bottom sheet stays at bottom, but input area needs safe area padding */
        .ai-widget-input-area {
            padding-bottom: calc(12px + env(safe-area-inset-bottom));
        }
    }
}
</style>

<script>
(function() {
    const widget = document.getElementById('ai-chat-widget');
    const toggleBtn = document.getElementById('ai-widget-toggle');
    const closeBtn = document.getElementById('ai-widget-close');
    const panel = document.getElementById('ai-widget-panel');
    const backdrop = document.getElementById('ai-widget-backdrop');
    const dragHandle = document.getElementById('ai-widget-drag-handle');
    const messagesContainer = document.getElementById('ai-widget-messages');
    const input = document.getElementById('ai-widget-input');
    const sendBtn = document.getElementById('ai-widget-send');
    const basePath = '<?= $basePath ?>';

    let conversationId = null;
    let isWaitingForResponse = false;

    // Check if we're on mobile - use multiple detection methods
    const isMobile = () => {
        return window.innerWidth <= 768 ||
               ('ontouchstart' in window) ||
               (navigator.maxTouchPoints > 0);
    };

    // Toggle panel
    function togglePanel() {
        console.log('[AI Widget] Toggle clicked, isMobile:', isMobile(), 'isOpen:', panel.classList.contains('open'));
        const isOpen = panel.classList.contains('open');
        if (isOpen) {
            closePanel();
        } else {
            openPanel();
        }
    }

    function openPanel() {
        console.log('[AI Widget] Opening panel, isMobile:', isMobile());

        // Add open class to panel
        panel.classList.add('open');
        panel.setAttribute('aria-hidden', 'false');
        toggleBtn.classList.add('open');

        // Mobile: show backdrop and lock body scroll
        if (isMobile()) {
            console.log('[AI Widget] Mobile detected - showing backdrop');
            backdrop.classList.add('visible');
            document.body.classList.add('ai-drawer-open');
            // Haptic feedback
            if (navigator.vibrate) navigator.vibrate(10);
        }

        // Focus input after animation
        setTimeout(() => {
            if (input) input.focus();
        }, 350);
    }

    function closePanel() {
        panel.classList.remove('open');
        panel.setAttribute('aria-hidden', 'true');
        toggleBtn.classList.remove('open');

        // Mobile: hide backdrop and unlock body scroll
        if (isMobile()) {
            backdrop.classList.remove('visible');
            document.body.classList.remove('ai-drawer-open');
            if (navigator.vibrate) navigator.vibrate(5);
        }
    }

    // Event listeners - handle touch and click properly to avoid double firing
    let lastToggleTime = 0;
    function handleToggle(e) {
        const now = Date.now();
        // Debounce - ignore if triggered within 300ms of last toggle
        if (now - lastToggleTime < 300) {
            console.log('[AI Widget] Debounced duplicate toggle');
            return;
        }
        lastToggleTime = now;

        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        togglePanel();
    }

    toggleBtn.addEventListener('click', handleToggle);
    // Use touchstart for faster response on mobile
    toggleBtn.addEventListener('touchstart', function(e) {
        handleToggle(e);
    }, { passive: false });

    closeBtn.addEventListener('click', closePanel);
    closeBtn.addEventListener('touchstart', function(e) {
        e.preventDefault();
        closePanel();
    }, { passive: false });

    // Backdrop click to close (mobile)
    backdrop.addEventListener('click', closePanel);

    // Close on escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && panel.classList.contains('open')) {
            closePanel();
        }
    });

    // ============================================
    // SWIPE TO CLOSE (Mobile Bottom Sheet)
    // ============================================
    let startY = 0;
    let currentY = 0;
    let isDragging = false;

    function handleTouchStart(e) {
        if (!isMobile()) return;
        startY = e.touches[0].clientY;
        isDragging = true;
        panel.style.transition = 'none';
    }

    function handleTouchMove(e) {
        if (!isDragging || !isMobile()) return;

        currentY = e.touches[0].clientY;
        const diff = currentY - startY;

        // Only allow dragging down (positive diff)
        if (diff > 0) {
            panel.style.transform = `translateY(${diff}px)`;
            // Fade backdrop as we drag
            const opacity = Math.max(0, 1 - (diff / 300));
            backdrop.style.opacity = opacity;
        }
    }

    function handleTouchEnd() {
        if (!isDragging || !isMobile()) return;
        isDragging = false;

        const diff = currentY - startY;
        panel.style.transition = '';
        backdrop.style.opacity = '';

        // If dragged down more than 100px, close the panel
        if (diff > 100) {
            closePanel();
        } else {
            // Snap back
            panel.style.transform = '';
        }

        startY = 0;
        currentY = 0;
    }

    // Attach touch listeners to drag handle and header
    if (dragHandle) {
        dragHandle.addEventListener('touchstart', handleTouchStart, { passive: true });
        dragHandle.addEventListener('touchmove', handleTouchMove, { passive: true });
        dragHandle.addEventListener('touchend', handleTouchEnd, { passive: true });
    }

    // Also allow dragging from the header area
    const header = panel.querySelector('.ai-widget-header');
    if (header) {
        header.addEventListener('touchstart', handleTouchStart, { passive: true });
        header.addEventListener('touchmove', handleTouchMove, { passive: true });
        header.addEventListener('touchend', handleTouchEnd, { passive: true });
    }

    // Quick prompts
    document.querySelectorAll('.ai-quick-prompt').forEach(btn => {
        btn.addEventListener('click', function() {
            const prompt = this.dataset.prompt;
            if (prompt) {
                input.value = prompt;
                sendMessage();
            }
        });
    });

    // Input handling
    input.addEventListener('input', function() {
        // Auto-resize
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 100) + 'px';

        // Enable/disable send button
        sendBtn.disabled = !this.value.trim() || isWaitingForResponse;
    });

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (!sendBtn.disabled) {
                sendMessage();
            }
        }
    });

    sendBtn.addEventListener('click', sendMessage);

    // Load quota on init
    loadQuota();

    function sendMessage() {
        const message = input.value.trim();
        if (!message || isWaitingForResponse) return;

        // Clear welcome screen
        const welcome = messagesContainer.querySelector('.ai-widget-welcome');
        if (welcome) {
            welcome.remove();
        }

        // Add user message
        addMessage(message, 'user');

        // Clear input
        input.value = '';
        input.style.height = 'auto';
        sendBtn.disabled = true;
        isWaitingForResponse = true;

        // Update status
        updateStatus('Thinking...', false);

        // Try streaming first, fallback to regular API
        sendMessageWithStreaming(message).catch(error => {
            console.warn('Streaming failed, falling back:', error);
            sendMessageWithRegularAPI(message);
        });
    }

    async function sendMessageWithStreaming(message) {
        // Create message element for streaming
        const messageId = 'stream-' + Date.now();
        const msg = document.createElement('div');
        msg.className = 'ai-widget-message assistant';
        msg.id = messageId;
        msg.innerHTML = '<span class="streaming-cursor"></span>';
        messagesContainer.appendChild(msg);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;

        let fullContent = '';

        const response = await fetch(basePath + '/api/ai/chat/stream', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream'
            },
            body: JSON.stringify({
                message: message,
                conversation_id: conversationId
            })
        });

        if (!response.ok) {
            msg.remove();
            throw new Error('Stream failed');
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            const chunk = decoder.decode(value, { stream: true });
            const lines = chunk.split('\n');

            for (const line of lines) {
                if (line.startsWith('data: ')) {
                    try {
                        const data = JSON.parse(line.slice(6));

                        if (data.error) {
                            // Escape error message explicitly for security
                            msg.innerHTML = parseMarkdown('Sorry: ' + escapeHtml(data.error));
                            finishResponse();
                            return;
                        }

                        if (data.content) {
                            fullContent += data.content;
                            msg.innerHTML = parseMarkdown(fullContent) + '<span class="streaming-cursor"></span>';
                            messagesContainer.scrollTop = messagesContainer.scrollHeight;
                        }

                        if (data.done && data.conversation_id) {
                            conversationId = data.conversation_id;
                            msg.innerHTML = parseMarkdown(fullContent);
                            addCopyButton(msg, fullContent);
                            loadQuota();
                            finishResponse();
                        }
                    } catch (e) {
                        // Skip malformed JSON
                    }
                }
            }
        }
    }

    async function sendMessageWithRegularAPI(message) {
        showTyping();

        try {
            const response = await fetch(basePath + '/api/ai/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    message: message,
                    conversation_id: conversationId
                })
            });

            const data = await response.json();
            hideTyping();

            if (data.success) {
                conversationId = data.conversation_id;
                addMessage(data.message.content, 'assistant');
                updateStatus('Ready to help', true);
                loadQuota();
            } else {
                addMessage('Sorry, something went wrong. Please try again.', 'assistant');
                updateStatus('Error - try again', false);
            }
        } catch (error) {
            hideTyping();
            console.error('AI Widget Error:', error);
            addMessage('Sorry, I couldn\'t connect. Please try again.', 'assistant');
            updateStatus('Connection error', false);
        }

        finishResponse();
    }

    function finishResponse() {
        isWaitingForResponse = false;
        sendBtn.disabled = !input.value.trim();
        updateStatus('Ready to help', true);
    }

    async function loadQuota() {
        try {
            const response = await fetch(basePath + '/api/ai/limits', { credentials: 'same-origin' });
            const data = await response.json();
            if (data.success && data.limits) {
                const used = data.limits.daily_limit - data.limits.daily_remaining;
                document.getElementById('quota-daily-used').textContent = used;
                document.getElementById('quota-daily-limit').textContent = data.limits.daily_limit;
            }
        } catch (e) {
            // Silently fail
        }
    }

    function addMessage(content, role) {
        const msg = document.createElement('div');
        msg.className = `ai-widget-message ${role}`;

        // Parse markdown for assistant messages
        if (role === 'assistant') {
            msg.innerHTML = parseMarkdown(content);
            // Add copy button for assistant messages
            addCopyButton(msg, content);
        } else {
            msg.textContent = content;
        }

        messagesContainer.appendChild(msg);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function addCopyButton(msgElement, content) {
        const actionsDiv = document.createElement('div');
        actionsDiv.className = 'ai-widget-message-actions';
        actionsDiv.innerHTML = `
            <button class="ai-widget-copy-btn" title="Copy response">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                </svg>
                Copy
            </button>
        `;

        const copyBtn = actionsDiv.querySelector('.ai-widget-copy-btn');
        copyBtn.addEventListener('click', function() {
            navigator.clipboard.writeText(content).then(() => {
                this.classList.add('copied');
                this.innerHTML = `
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    Copied!
                `;
                setTimeout(() => {
                    this.classList.remove('copied');
                    this.innerHTML = `
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                        Copy
                    `;
                }, 2000);
            });
        });

        msgElement.appendChild(actionsDiv);
    }

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/&/g, '&amp;')
                   .replace(/</g, '&lt;')
                   .replace(/>/g, '&gt;')
                   .replace(/"/g, '&quot;')
                   .replace(/'/g, '&#39;');
    }

    // Simple markdown parser for AI responses
    function parseMarkdown(text) {
        if (!text) return '';

        // Escape HTML first
        let html = escapeHtml(text);

        // Code blocks
        html = html.replace(/```(\w*)\n([\s\S]*?)```/g, '<pre><code>$2</code></pre>');

        // Inline code
        html = html.replace(/`([^`]+)`/g, '<code>$1</code>');

        // Bold
        html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');

        // Italic
        html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');

        // Lists
        html = html.replace(/^[\-\*] (.+)$/gm, '<li>$1</li>');

        // Links
        html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>');

        // Line breaks
        html = html.replace(/\n/g, '<br>');

        return html;
    }

    function showTyping() {
        const typing = document.createElement('div');
        typing.className = 'ai-widget-typing';
        typing.id = 'ai-typing-indicator';
        typing.innerHTML = '<div class="ai-typing-dot"></div><div class="ai-typing-dot"></div><div class="ai-typing-dot"></div>';
        messagesContainer.appendChild(typing);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function hideTyping() {
        const typing = document.getElementById('ai-typing-indicator');
        if (typing) typing.remove();
    }

    function updateStatus(text, isReady) {
        const statusText = widget.querySelector('.ai-status-text');
        const statusDot = widget.querySelector('.ai-status-dot');
        if (statusText) statusText.textContent = text;
        if (statusDot) statusDot.style.background = isReady ? '#10b981' : '#f59e0b';
    }
})();
</script>
