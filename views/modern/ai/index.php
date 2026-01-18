<?php
/**
 * AI Assistant Page
 * Full-featured AI chat interface
 */

$hero_title = "AI Assistant";
$hero_subtitle = "Your intelligent timebank companion";
$hero_gradient = 'htb-hero-gradient-special';
$hero_type = 'AI';

require __DIR__ . '/../../layouts/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
$currentConversationId = $conversation['id'] ?? null;
?>

<style>
/* ============================================
   AI ASSISTANT PAGE STYLES
   ============================================ */

/* Account for fixed header */
.ai-page-wrapper {
    padding-top: 100px;
    min-height: 100vh;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 50%, #e2e8f0 100%);
}

[data-theme="dark"] .ai-page-wrapper {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
}

.ai-page-container {
    display: flex;
    height: calc(100vh - 170px);
    min-height: 500px;
    gap: 1rem;
    padding: 1rem;
    max-width: 1400px;
    margin: 0 auto;
}

/* Sidebar */
.ai-sidebar {
    width: 280px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    border: 1px solid rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

[data-theme="dark"] .ai-sidebar {
    background: rgba(30, 41, 59, 0.9);
    border-color: rgba(255, 255, 255, 0.1);
}

.ai-sidebar-header {
    padding: 1rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

[data-theme="dark"] .ai-sidebar-header {
    border-color: rgba(255, 255, 255, 0.1);
}

.ai-new-chat-btn {
    width: 100%;
    padding: 0.75rem 1rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.ai-new-chat-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
}

.ai-new-chat-btn:active {
    transform: translateY(0) scale(0.98);
}

.ai-new-chat-btn:focus-visible {
    outline: 2px solid #fff;
    outline-offset: 2px;
}

.ai-conversations-list {
    flex: 1;
    overflow-y: auto;
    padding: 0.5rem;
}

/* Hide toggle button on desktop */
.ai-sidebar-toggle {
    display: none;
}

.ai-conversation-item {
    padding: 0.75rem 1rem;
    border-radius: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: all 0.15s ease;
    margin-bottom: 0.25rem;
}

.ai-conversation-item:hover {
    background: rgba(99, 102, 241, 0.1);
}

.ai-conversation-item.active {
    background: rgba(99, 102, 241, 0.15);
}

.ai-conversation-item .conv-icon {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.875rem;
    flex-shrink: 0;
}

.ai-conversation-item .conv-info {
    flex: 1;
    min-width: 0;
}

.ai-conversation-item .conv-title {
    font-weight: 500;
    font-size: 0.875rem;
    color: var(--text-primary, #1e293b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

[data-theme="dark"] .ai-conversation-item .conv-title {
    color: #f1f5f9;
}

.ai-conversation-item .conv-date {
    font-size: 0.75rem;
    color: var(--text-muted, #64748b);
}

/* Delete button for conversations */
.conv-delete-btn {
    opacity: 0;
    padding: 6px;
    background: transparent;
    border: none;
    border-radius: 6px;
    color: var(--text-muted, #64748b);
    cursor: pointer;
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.ai-conversation-item:hover .conv-delete-btn {
    opacity: 1;
}

.conv-delete-btn:hover {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.conv-delete-btn:focus-visible {
    opacity: 1;
    outline: 2px solid #ef4444;
    outline-offset: 2px;
}

/* Main Chat Area */
.ai-chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    border: 1px solid rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

[data-theme="dark"] .ai-chat-main {
    background: rgba(30, 41, 59, 0.9);
    border-color: rgba(255, 255, 255, 0.1);
}

/* Chat Header */
.ai-chat-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

[data-theme="dark"] .ai-chat-header {
    border-color: rgba(255, 255, 255, 0.1);
}

.ai-chat-header h2 {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary, #1e293b);
    margin: 0;
}

[data-theme="dark"] .ai-chat-header h2 {
    color: #f1f5f9;
}

.ai-provider-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.375rem 0.75rem;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    color: #6366f1;
}

/* Messages Container */
.ai-messages-container {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    scroll-behavior: smooth;
    overscroll-behavior: contain;
}

/* Welcome State */
.ai-welcome {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    text-align: center;
    padding: 2rem;
}

.ai-welcome-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
    box-shadow: 0 8px 32px rgba(99, 102, 241, 0.3);
}

.ai-welcome-icon svg {
    width: 40px;
    height: 40px;
    color: white;
}

.ai-welcome h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary, #1e293b);
    margin-bottom: 0.5rem;
}

[data-theme="dark"] .ai-welcome h3 {
    color: #f1f5f9;
}

.ai-welcome p {
    color: var(--text-muted, #64748b);
    max-width: 400px;
    margin-bottom: 2rem;
}

.ai-suggestions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    justify-content: center;
    max-width: 600px;
}

.ai-suggestion-btn {
    padding: 0.75rem 1rem;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    font-size: 0.875rem;
    color: #6366f1;
    cursor: pointer;
    transition: all 0.2s ease;
}

.ai-suggestion-btn:hover {
    background: rgba(99, 102, 241, 0.2);
    transform: translateY(-2px);
}

.ai-suggestion-btn:active {
    transform: translateY(0) scale(0.98);
}

.ai-suggestion-btn:focus-visible {
    outline: 2px solid #6366f1;
    outline-offset: 2px;
}

/* Message Bubbles */
.ai-message {
    display: flex;
    gap: 0.75rem;
    max-width: 85%;
}

.ai-message.user {
    flex-direction: row-reverse;
    margin-left: auto;
}

.ai-message-avatar {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
}

.ai-message.assistant .ai-message-avatar {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
}

.ai-message.user .ai-message-avatar {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
}

.ai-message-content-wrapper {
    max-width: 100%;
}

.ai-message-content {
    padding: 0.875rem 1.125rem;
    border-radius: 16px;
    font-size: 0.9375rem;
    line-height: 1.6;
}

.ai-message.assistant .ai-message-content {
    background: rgba(0, 0, 0, 0.05);
    color: var(--text-primary, #1e293b);
    border-bottom-left-radius: 4px;
}

[data-theme="dark"] .ai-message.assistant .ai-message-content {
    background: rgba(255, 255, 255, 0.1);
    color: #f1f5f9;
}

.ai-message.user .ai-message-content {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border-bottom-right-radius: 4px;
}

/* Message entry animation */
.ai-message {
    animation: messageSlideIn 0.3s ease-out;
}

@keyframes messageSlideIn {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Markdown styles in messages */
.ai-message-content h2,
.ai-message-content h3,
.ai-message-content h4 {
    margin: 0.5rem 0 0.25rem;
    font-weight: 600;
}

.ai-message-content h2 { font-size: 1.1rem; }
.ai-message-content h3 { font-size: 1rem; }
.ai-message-content h4 { font-size: 0.95rem; }

.ai-message-content ul,
.ai-message-content ol {
    margin: 0.5rem 0;
    padding-left: 1.5rem;
}

.ai-message-content li {
    margin: 0.25rem 0;
}

.ai-message-content code {
    background: rgba(99, 102, 241, 0.1);
    padding: 0.125rem 0.375rem;
    border-radius: 4px;
    font-family: 'Fira Code', 'Monaco', 'Consolas', monospace;
    font-size: 0.85em;
}

.ai-message.user .ai-message-content code {
    background: rgba(255, 255, 255, 0.2);
}

.ai-message-content pre {
    background: rgba(0, 0, 0, 0.05);
    padding: 0.75rem 1rem;
    border-radius: 8px;
    overflow-x: auto;
    margin: 0.5rem 0;
}

[data-theme="dark"] .ai-message-content pre {
    background: rgba(0, 0, 0, 0.3);
}

.ai-message-content pre code {
    background: transparent;
    padding: 0;
}

.ai-message-content strong {
    font-weight: 600;
}

.ai-message-content a {
    color: #6366f1;
    text-decoration: underline;
}

.ai-message.user .ai-message-content a {
    color: rgba(255, 255, 255, 0.9);
}

/* Message Actions */
.ai-message-actions {
    display: flex;
    gap: 4px;
    margin-top: 8px;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.ai-message:hover .ai-message-actions {
    opacity: 1;
}

.ai-action-btn {
    padding: 4px 8px;
    background: rgba(0, 0, 0, 0.05);
    border: none;
    border-radius: 6px;
    font-size: 0.75rem;
    color: var(--text-muted, #64748b);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 4px;
    transition: all 0.15s ease;
}

.ai-action-btn:hover {
    background: rgba(99, 102, 241, 0.15);
    color: #6366f1;
}

.ai-action-btn.copied {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.ai-action-btn.regenerate-btn:hover {
    background: rgba(99, 102, 241, 0.2);
    color: #6366f1;
}

/* Stopped indicator */
.ai-stopped-indicator {
    display: inline-block;
    font-size: 0.75rem;
    color: var(--text-muted, #94a3b8);
    font-style: italic;
    margin-left: 8px;
}

/* Error message styles */
.ai-error-message .ai-error-avatar {
    background: linear-gradient(135deg, #ef4444, #dc2626) !important;
}

.ai-error-content {
    background: rgba(239, 68, 68, 0.1) !important;
    border: 1px solid rgba(239, 68, 68, 0.2);
}

[data-theme="dark"] .ai-error-content {
    background: rgba(239, 68, 68, 0.15) !important;
    border-color: rgba(239, 68, 68, 0.3);
}

.ai-action-btn.retry-btn {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.ai-action-btn.retry-btn:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #dc2626;
}

/* Screen reader only class */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

[data-theme="dark"] .ai-action-btn {
    background: rgba(255, 255, 255, 0.08);
}

[data-theme="dark"] .ai-action-btn:hover {
    background: rgba(99, 102, 241, 0.2);
}

/* Typing Indicator */
.ai-typing {
    display: flex;
    gap: 4px;
    padding: 1rem;
}

.ai-typing span {
    width: 8px;
    height: 8px;
    background: #6366f1;
    border-radius: 50%;
    animation: typingBounce 1.4s ease-in-out infinite;
}

.ai-typing span:nth-child(2) { animation-delay: 0.2s; }
.ai-typing span:nth-child(3) { animation-delay: 0.4s; }

@keyframes typingBounce {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-8px); }
}

/* Streaming cursor animation */
.streaming-cursor {
    display: inline-block;
    width: 2px;
    height: 1em;
    background: #6366f1;
    margin-left: 2px;
    animation: cursorBlink 0.8s ease-in-out infinite;
    vertical-align: text-bottom;
}

@keyframes cursorBlink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0; }
}

/* Input Area */
.ai-input-area {
    padding: 1rem 1.5rem;
    border-top: 1px solid rgba(0, 0, 0, 0.08);
}

[data-theme="dark"] .ai-input-area {
    border-color: rgba(255, 255, 255, 0.1);
}

.ai-input-wrapper {
    display: flex;
    gap: 0.75rem;
    align-items: flex-end;
}

.ai-input-field {
    flex: 1;
    padding: 0.875rem 1rem;
    border: 2px solid rgba(0, 0, 0, 0.1);
    border-radius: 14px;
    font-size: 0.9375rem;
    resize: none;
    min-height: 48px;
    max-height: 150px;
    background: transparent;
    color: var(--text-primary, #1e293b);
    transition: border-color 0.2s ease;
}

[data-theme="dark"] .ai-input-field {
    border-color: rgba(255, 255, 255, 0.15);
    color: #f1f5f9 !important;
    background: rgba(30, 30, 30, 0.5) !important;
    -webkit-text-fill-color: #f1f5f9 !important;
    caret-color: #f1f5f9;
}

.ai-input-field:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] .ai-input-field:focus {
    border-color: #818cf8;
    box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.2);
    background: rgba(30, 30, 30, 0.5) !important;
    color: #f1f5f9 !important;
    -webkit-text-fill-color: #f1f5f9 !important;
}

.ai-input-field::placeholder {
    color: var(--text-muted, #94a3b8);
}

[data-theme="dark"] .ai-input-field::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

.ai-send-btn {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border: none;
    border-radius: 14px;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.ai-send-btn:hover:not(:disabled) {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
}

.ai-send-btn:active:not(:disabled) {
    transform: scale(0.92);
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.2);
}

.ai-send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: linear-gradient(135deg, #94a3b8, #64748b);
}

.ai-send-btn:focus-visible {
    outline: 2px solid #6366f1;
    outline-offset: 2px;
}

/* Stop Generation Button */
.ai-stop-btn {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    border: none;
    border-radius: 14px;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    animation: pulseStop 1.5s ease-in-out infinite;
}

@keyframes pulseStop {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
    50% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
}

.ai-stop-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

.ai-stop-btn:active {
    transform: scale(0.95);
}

.ai-stop-btn:focus-visible {
    outline: 2px solid #ef4444;
    outline-offset: 2px;
}

/* Usage Limits */
.ai-limits-bar {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: var(--text-muted, #64748b);
    padding-top: 0.5rem;
}

/* Responsive */
@media (max-width: 768px) {
    .ai-page-wrapper {
        padding-top: 70px;
    }

    .ai-page-container {
        flex-direction: column;
        height: auto;
        min-height: calc(100vh - 120px);
        padding: 0.5rem;
        gap: 0.5rem;
    }

    .ai-sidebar {
        width: 100%;
        max-height: none;
        border-radius: 12px;
    }

    /* Collapsible sidebar on mobile */
    .ai-sidebar-header {
        padding: 0.75rem;
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .ai-sidebar-header .ai-new-chat-btn {
        flex: 1;
    }

    /* Toggle button for conversations on mobile */
    .ai-sidebar-toggle {
        display: flex;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        border: 1px solid rgba(0, 0, 0, 0.1);
        background: rgba(99, 102, 241, 0.1);
        color: #6366f1;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    [data-theme="dark"] .ai-sidebar-toggle {
        border-color: rgba(255, 255, 255, 0.15);
        background: rgba(99, 102, 241, 0.2);
    }

    .ai-sidebar-toggle:hover {
        background: rgba(99, 102, 241, 0.2);
    }

    .ai-sidebar-toggle svg {
        transition: transform 0.2s ease;
    }

    .ai-sidebar-toggle.expanded svg {
        transform: rotate(180deg);
    }

    .ai-conversations-list {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
    }

    .ai-conversations-list.expanded {
        max-height: 150px;
        overflow-y: auto;
    }

    .ai-chat-main {
        min-height: calc(100vh - 280px);
        flex: 1;
        border-radius: 12px;
    }

    .ai-chat-header {
        padding: 0.75rem 1rem;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .ai-chat-header h2 {
        font-size: 1rem;
        flex: 1;
        min-width: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ai-provider-badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
    }

    .ai-messages-container {
        padding: 1rem;
    }

    .ai-message {
        max-width: 95%;
    }

    .ai-message-avatar {
        width: 28px;
        height: 28px;
        font-size: 0.75rem;
        border-radius: 8px;
    }

    .ai-message-content {
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
        border-radius: 12px;
    }

    .ai-input-area {
        padding: 0.75rem 1rem;
    }

    .ai-input-field {
        padding: 0.75rem;
        font-size: 0.875rem;
        border-radius: 12px;
    }

    .ai-send-btn {
        width: 42px;
        height: 42px;
        border-radius: 12px;
    }

    .ai-limits-bar {
        font-size: 0.7rem;
    }

    .ai-welcome-icon {
        width: 60px;
        height: 60px;
        border-radius: 16px;
    }

    .ai-welcome h3 {
        font-size: 1.25rem;
    }

    .ai-welcome p {
        font-size: 0.875rem;
        margin-bottom: 1.5rem;
    }

    .ai-suggestions {
        gap: 0.5rem;
    }

    .ai-suggestion-btn {
        padding: 0.625rem 0.875rem;
        font-size: 0.8rem;
    }

    .ai-new-chat-btn {
        padding: 0.625rem 0.875rem;
        font-size: 0.875rem;
    }

    .ai-conversation-item {
        padding: 0.625rem 0.75rem;
    }

    .ai-conversation-item .conv-icon {
        width: 28px;
        height: 28px;
    }

    .ai-conversation-item .conv-title {
        font-size: 0.8rem;
    }

    .ai-conversation-item .conv-date {
        font-size: 0.7rem;
    }
}

/* Extra small devices */
@media (max-width: 480px) {
    .ai-page-wrapper {
        padding-top: 60px;
    }

    .ai-page-container {
        padding: 0.25rem;
    }

    .ai-sidebar {
        border-radius: 10px;
    }

    .ai-conversations-list {
        max-height: 100px;
    }

    .ai-chat-main {
        border-radius: 10px;
    }

    .ai-messages-container {
        padding: 0.75rem;
    }

    .ai-message {
        max-width: 98%;
        gap: 0.5rem;
    }

    .ai-welcome {
        padding: 1.5rem 1rem;
    }

    .ai-suggestions {
        flex-direction: column;
    }

    .ai-suggestion-btn {
        width: 100%;
        text-align: center;
    }

    /* Hide message actions until tapped on mobile */
    .ai-message-actions {
        opacity: 1;
    }
}

/* Landscape mobile */
@media (max-height: 500px) and (orientation: landscape) {
    .ai-page-container {
        flex-direction: row;
    }

    .ai-sidebar {
        width: 200px;
        max-height: none;
    }

    .ai-conversations-list {
        max-height: calc(100vh - 200px);
    }
}
</style>

<div class="ai-page-wrapper">
<div class="ai-page-container">
    <!-- Sidebar with conversations -->
    <aside class="ai-sidebar" role="complementary" aria-label="Conversation history">
        <div class="ai-sidebar-header">
            <button class="ai-new-chat-btn" onclick="startNewChat()" aria-label="Start a new conversation">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                New Chat
            </button>
            <button class="ai-sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()" aria-label="Toggle conversations">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </button>
        </div>

        <div class="ai-conversations-list" id="conversationsList" role="list" aria-label="Previous conversations">
            <?php if (empty($conversations)): ?>
                <p style="text-align: center; color: var(--text-muted); padding: 2rem 1rem; font-size: 0.875rem;">
                    No conversations yet.<br>Start a new chat!
                </p>
            <?php else: ?>
                <?php foreach ($conversations as $conv): ?>
                    <div class="ai-conversation-item <?= ($conv['id'] ?? 0) == $currentConversationId ? 'active' : '' ?>"
                         onclick="loadConversation(<?= $conv['id'] ?>)"
                         data-id="<?= $conv['id'] ?>"
                         role="button"
                         tabindex="0"
                         aria-label="Conversation: <?= htmlspecialchars($conv['title'] ?? 'New Chat') ?>">
                        <div class="conv-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                        </div>
                        <div class="conv-info">
                            <div class="conv-title"><?= htmlspecialchars($conv['title'] ?? 'New Chat') ?></div>
                            <div class="conv-date"><?= date('M j, g:i a', strtotime($conv['created_at'])) ?></div>
                        </div>
                        <button class="conv-delete-btn" onclick="event.stopPropagation(); deleteConversation(<?= $conv['id'] ?>)" aria-label="Delete conversation" title="Delete">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Main Chat Area -->
    <main class="ai-chat-main" role="main" aria-label="AI Chat">
        <div class="ai-chat-header">
            <h2 id="chatTitle"><?= htmlspecialchars($conversation['title'] ?? 'NEXUS AI Assistant') ?></h2>
            <div class="ai-provider-badge">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path>
                </svg>
                <span id="providerName"><?= ucfirst($defaultProvider ?? 'Gemini') ?></span>
            </div>
        </div>

        <div class="ai-messages-container" id="messagesContainer" role="log" aria-live="polite" aria-label="Chat messages">
            <?php if (empty($messages)): ?>
                <!-- Welcome State -->
                <div class="ai-welcome" id="welcomeState">
                    <div class="ai-welcome-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2z"></path>
                            <path d="M12 16v-4M12 8h.01"></path>
                        </svg>
                    </div>
                    <h3>Welcome to NEXUS AI</h3>
                    <p><?= nl2br(htmlspecialchars($welcomeMessage ?? "I'm your intelligent timebank assistant. I can help you find members, create listings, understand features, and more!")) ?></p>

                    <div class="ai-suggestions">
                        <button class="ai-suggestion-btn" onclick="sendSuggestion('How does timebanking work?')">
                            How does timebanking work?
                        </button>
                        <button class="ai-suggestion-btn" onclick="sendSuggestion('Help me write a listing description')">
                            Help me write a listing
                        </button>
                        <button class="ai-suggestion-btn" onclick="sendSuggestion('What skills are in demand?')">
                            What skills are in demand?
                        </button>
                        <button class="ai-suggestion-btn" onclick="sendSuggestion('How do I earn time credits?')">
                            How do I earn credits?
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="ai-message <?= $msg['role'] ?>">
                        <div class="ai-message-avatar">
                            <?php if ($msg['role'] === 'assistant'): ?>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            <?php else: ?>
                                <?= substr($_SESSION['user_name'] ?? 'U', 0, 1) ?>
                            <?php endif; ?>
                        </div>
                        <div class="ai-message-content">
                            <?= nl2br(htmlspecialchars($msg['content'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="ai-input-area">
            <div class="ai-input-wrapper">
                <textarea
                    class="ai-input-field"
                    id="messageInput"
                    placeholder="Ask me anything about timebanking..."
                    rows="1"
                    onkeydown="handleInputKeydown(event)"
                    aria-label="Type your message"
                ></textarea>
                <button class="ai-send-btn" id="sendBtn" onclick="sendMessage()" aria-label="Send message">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
                <button class="ai-stop-btn" id="stopBtn" onclick="stopGeneration()" aria-label="Stop generation" style="display: none;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="6" y="6" width="12" height="12" rx="2"></rect>
                    </svg>
                </button>
            </div>
            <div class="ai-limits-bar">
                <span>Daily: <span id="dailyUsage"><?= $limits['daily_used'] ?? 0 ?></span>/<?= $limits['daily_limit'] ?? 50 ?></span>
                <span>Monthly: <span id="monthlyUsage"><?= $limits['monthly_used'] ?? 0 ?></span>/<?= $limits['monthly_limit'] ?? 1000 ?></span>
            </div>
        </div>
    </main>
</div>
</div><!-- .ai-page-wrapper -->

<script>
const BASE_PATH = '<?= $basePath ?>';
let currentConversationId = <?= $currentConversationId ? $currentConversationId : 'null' ?>;
let isWaiting = false;
let currentAbortController = null;
let lastAssistantMessage = null;

// Mobile sidebar toggle
function toggleSidebar() {
    const toggle = document.getElementById('sidebarToggle');
    const list = document.getElementById('conversationsList');
    toggle.classList.toggle('expanded');
    list.classList.toggle('expanded');
}

// Auto-resize textarea
const messageInput = document.getElementById('messageInput');
messageInput.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 150) + 'px';
});

function handleInputKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
}

function startNewChat() {
    currentConversationId = null;
    document.getElementById('messagesContainer').innerHTML = `
        <div class="ai-welcome" id="welcomeState">
            <div class="ai-welcome-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2z"></path>
                    <path d="M12 16v-4M12 8h.01"></path>
                </svg>
            </div>
            <h3>Welcome to NEXUS AI</h3>
            <p>I'm your intelligent timebank assistant. I can help you find members, create listings, understand features, and more!</p>
            <div class="ai-suggestions">
                <button class="ai-suggestion-btn" onclick="sendSuggestion('How does timebanking work?')">How does timebanking work?</button>
                <button class="ai-suggestion-btn" onclick="sendSuggestion('Help me write a listing description')">Help me write a listing</button>
                <button class="ai-suggestion-btn" onclick="sendSuggestion('What skills are in demand?')">What skills are in demand?</button>
                <button class="ai-suggestion-btn" onclick="sendSuggestion('How do I earn time credits?')">How do I earn credits?</button>
            </div>
        </div>
    `;
    document.getElementById('chatTitle').textContent = 'NEXUS AI Assistant';

    // Remove active state from all conversations
    document.querySelectorAll('.ai-conversation-item').forEach(el => el.classList.remove('active'));
}

function sendSuggestion(text) {
    messageInput.value = text;
    sendMessage();
}

async function sendMessage() {
    const message = messageInput.value.trim();
    if (!message || isWaiting) return;

    isWaiting = true;
    document.getElementById('sendBtn').disabled = true;

    // Remove welcome state
    const welcomeState = document.getElementById('welcomeState');
    if (welcomeState) {
        welcomeState.remove();
    }

    // Add user message to UI
    addMessage('user', message);
    messageInput.value = '';
    messageInput.style.height = 'auto';

    // Try streaming first, fall back to regular API
    try {
        await sendMessageWithStreaming(message);
    } catch (error) {
        console.warn('Streaming failed, falling back to regular API:', error);
        await sendMessageWithRegularAPI(message);
    }

    isWaiting = false;
    document.getElementById('sendBtn').disabled = false;
    messageInput.focus();
}

async function sendMessageWithStreaming(message) {
    // Create abort controller for this request
    currentAbortController = new AbortController();

    // Show stop button, hide send button
    document.getElementById('sendBtn').style.display = 'none';
    document.getElementById('stopBtn').style.display = 'flex';

    // Create message bubble for streaming response
    const container = document.getElementById('messagesContainer');
    const messageId = 'streaming-' + Date.now();

    const messageHtml = `
        <div class="ai-message assistant" id="${messageId}">
            <div class="ai-message-avatar">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle></svg>
            </div>
            <div class="ai-message-content-wrapper">
                <div class="ai-message-content">
                    <span class="streaming-cursor"></span>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', messageHtml);
    container.scrollTop = container.scrollHeight;

    const messageEl = document.getElementById(messageId);
    const contentEl = messageEl.querySelector('.ai-message-content');
    let fullContent = '';

    try {
        const response = await fetch(BASE_PATH + '/api/ai/chat/stream', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                message: message,
                conversation_id: currentConversationId
            }),
            signal: currentAbortController.signal
        });

        if (!response.ok) {
            messageEl.remove();
            throw new Error('Stream request failed');
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
                            contentEl.innerHTML = parseMarkdown('Sorry, an error occurred: ' + data.error);
                            addMessageActions(messageEl, fullContent || data.error, message);
                            return;
                        }

                        if (data.content) {
                            fullContent += data.content;
                            contentEl.innerHTML = parseMarkdown(fullContent) + '<span class="streaming-cursor"></span>';
                            container.scrollTop = container.scrollHeight;
                        }

                        if (data.done && data.conversation_id) {
                            currentConversationId = data.conversation_id;
                            // Remove cursor and finalize
                            contentEl.innerHTML = parseMarkdown(fullContent);
                            lastAssistantMessage = { content: fullContent, prompt: message };
                            addMessageActions(messageEl, fullContent, message);
                            refreshConversations();
                            updateLimitsDisplay();
                        }
                    } catch (e) {
                        // Skip malformed JSON
                    }
                }
            }
        }
    } catch (error) {
        if (error.name === 'AbortError') {
            // User stopped generation
            if (fullContent) {
                contentEl.innerHTML = parseMarkdown(fullContent) + '<span class="ai-stopped-indicator">(stopped)</span>';
                lastAssistantMessage = { content: fullContent, prompt: message };
                addMessageActions(messageEl, fullContent, message);
            } else {
                messageEl.remove();
            }
        } else {
            throw error;
        }
    } finally {
        // Reset buttons
        document.getElementById('sendBtn').style.display = 'flex';
        document.getElementById('stopBtn').style.display = 'none';
        currentAbortController = null;
    }
}

function stopGeneration() {
    if (currentAbortController) {
        currentAbortController.abort();
    }
}

function addMessageActions(messageEl, content, originalPrompt) {
    const wrapper = messageEl.querySelector('.ai-message-content-wrapper');
    if (!wrapper) return;

    const actionsHtml = `
        <div class="ai-message-actions">
            <button class="ai-action-btn copy-btn" onclick="copyMessageContent(this)" data-content="${escapeHtml(content).replace(/"/g, '&quot;')}">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                </svg>
                Copy
            </button>
            <button class="ai-action-btn regenerate-btn" onclick="regenerateResponse('${escapeHtml(originalPrompt).replace(/'/g, "\\'")}')">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                </svg>
                Regenerate
            </button>
        </div>
    `;
    wrapper.insertAdjacentHTML('beforeend', actionsHtml);
}

async function regenerateResponse(prompt) {
    if (isWaiting) return;

    // Remove the last assistant message
    const messages = document.querySelectorAll('.ai-message.assistant');
    if (messages.length > 0) {
        messages[messages.length - 1].remove();
    }

    // Resend the message
    isWaiting = true;
    document.getElementById('sendBtn').disabled = true;

    try {
        await sendMessageWithStreaming(prompt);
    } catch (error) {
        console.warn('Streaming failed, falling back to regular API:', error);
        await sendMessageWithRegularAPI(prompt);
    }

    isWaiting = false;
    document.getElementById('sendBtn').disabled = false;
    messageInput.focus();
}

async function deleteConversation(id) {
    if (!confirm('Delete this conversation?')) return;

    try {
        const response = await fetch(BASE_PATH + '/api/ai/conversations/' + id, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.success) {
            // If we're viewing the deleted conversation, start a new chat
            if (currentConversationId === id) {
                startNewChat();
            }
            // Refresh the conversations list
            refreshConversations();
        } else {
            alert('Failed to delete conversation: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Delete conversation error:', error);
        alert('Failed to delete conversation. Please try again.');
    }
}

async function sendMessageWithRegularAPI(message) {
    showTyping();

    try {
        const response = await fetch(BASE_PATH + '/api/ai/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                message: message,
                conversation_id: currentConversationId
            })
        });

        const data = await response.json();

        hideTyping();

        if (data.success) {
            currentConversationId = data.conversation_id;
            addMessage('assistant', data.message.content, message);
            lastAssistantMessage = { content: data.message.content, prompt: message };

            // Update limits
            if (data.limits) {
                document.getElementById('dailyUsage').textContent = (<?= $limits['daily_limit'] ?? 50 ?> - data.limits.daily_remaining);
                document.getElementById('monthlyUsage').textContent = (<?= $limits['monthly_limit'] ?? 1000 ?> - data.limits.monthly_remaining);
            }

            // Refresh conversations list
            refreshConversations();
        } else {
            addErrorMessage(data.error || 'Unknown error', message);
        }

    } catch (error) {
        hideTyping();
        addErrorMessage('Connection failed. Please check your internet connection.', message);
        console.error('AI chat error:', error);
    }
}

function addErrorMessage(errorText, originalPrompt) {
    const container = document.getElementById('messagesContainer');
    const messageHtml = `
        <div class="ai-message assistant ai-error-message">
            <div class="ai-message-avatar ai-error-avatar">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
            <div class="ai-message-content-wrapper">
                <div class="ai-message-content ai-error-content">
                    <strong>Something went wrong</strong><br>
                    ${escapeHtml(errorText)}
                </div>
                <div class="ai-message-actions" style="opacity: 1;">
                    <button class="ai-action-btn retry-btn" onclick="retryMessage('${escapeHtml(originalPrompt).replace(/'/g, "\\'")}')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                        </svg>
                        Retry
                    </button>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', messageHtml);
    container.scrollTop = container.scrollHeight;
}

async function retryMessage(prompt) {
    if (isWaiting) return;

    // Remove the error message
    const errorMessages = document.querySelectorAll('.ai-error-message');
    if (errorMessages.length > 0) {
        errorMessages[errorMessages.length - 1].remove();
    }

    // Retry sending the message
    isWaiting = true;
    document.getElementById('sendBtn').disabled = true;

    try {
        await sendMessageWithStreaming(prompt);
    } catch (error) {
        console.warn('Streaming failed, falling back to regular API:', error);
        await sendMessageWithRegularAPI(prompt);
    }

    isWaiting = false;
    document.getElementById('sendBtn').disabled = false;
    messageInput.focus();
}

async function updateLimitsDisplay() {
    try {
        const response = await fetch(BASE_PATH + '/api/ai/limits', { credentials: 'same-origin' });
        const data = await response.json();
        if (data.success && data.limits) {
            document.getElementById('dailyUsage').textContent = (<?= $limits['daily_limit'] ?? 50 ?> - data.limits.daily_remaining);
            document.getElementById('monthlyUsage').textContent = (<?= $limits['monthly_limit'] ?? 1000 ?> - data.limits.monthly_remaining);
        }
    } catch (e) {
        // Silently fail
    }
}

function addMessage(role, content, originalPrompt = null) {
    const container = document.getElementById('messagesContainer');
    const avatar = role === 'assistant'
        ? `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle></svg>`
        : '<?= substr($_SESSION['user_name'] ?? 'U', 0, 1) ?>';

    // Parse markdown for assistant messages, escape HTML for user messages
    const formattedContent = role === 'assistant'
        ? parseMarkdown(content)
        : escapeHtml(content).replace(/\n/g, '<br>');

    // Add copy and regenerate buttons for assistant messages
    let actionsHtml = '';
    if (role === 'assistant') {
        const regenerateBtn = originalPrompt ? `
            <button class="ai-action-btn regenerate-btn" onclick="regenerateResponse('${escapeHtml(originalPrompt).replace(/'/g, "\\'")}')">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                </svg>
                Regenerate
            </button>
        ` : '';

        actionsHtml = `
            <div class="ai-message-actions">
                <button class="ai-action-btn copy-btn" onclick="copyMessageContent(this)" data-content="${escapeHtml(content).replace(/"/g, '&quot;')}" aria-label="Copy message">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                    Copy
                </button>
                ${regenerateBtn}
            </div>
        `;
    }

    const messageHtml = `
        <div class="ai-message ${role}" role="article" aria-label="${role === 'assistant' ? 'AI response' : 'Your message'}">
            <div class="ai-message-avatar" aria-hidden="true">${avatar}</div>
            <div class="ai-message-content-wrapper">
                <div class="ai-message-content">${formattedContent}</div>
                ${actionsHtml}
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', messageHtml);
    container.scrollTop = container.scrollHeight;

    // Announce new message for screen readers
    if (role === 'assistant') {
        announceToScreenReader('AI has responded');
    }
}

function copyMessageContent(btn) {
    const content = btn.dataset.content;
    navigator.clipboard.writeText(content).then(() => {
        btn.classList.add('copied');
        btn.innerHTML = `
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            Copied!
        `;
        setTimeout(() => {
            btn.classList.remove('copied');
            btn.innerHTML = `
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                </svg>
                Copy
            `;
        }, 2000);
    });
}

// Simple markdown parser for AI responses
function parseMarkdown(text) {
    if (!text) return '';

    // Escape HTML first
    let html = escapeHtml(text);

    // Code blocks (```)
    html = html.replace(/```(\w*)\n([\s\S]*?)```/g, '<pre><code class="language-$1">$2</code></pre>');

    // Inline code (`)
    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');

    // Bold (**text** or __text__)
    html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/__([^_]+)__/g, '<strong>$1</strong>');

    // Italic (*text* or _text_)
    html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    html = html.replace(/_([^_]+)_/g, '<em>$1</em>');

    // Headers (## Header)
    html = html.replace(/^### (.+)$/gm, '<h4>$1</h4>');
    html = html.replace(/^## (.+)$/gm, '<h3>$1</h3>');
    html = html.replace(/^# (.+)$/gm, '<h2>$1</h2>');

    // Unordered lists
    html = html.replace(/^[\-\*] (.+)$/gm, '<li>$1</li>');
    html = html.replace(/(<li>.*<\/li>\n?)+/g, '<ul>$&</ul>');

    // Numbered lists
    html = html.replace(/^\d+\. (.+)$/gm, '<li>$1</li>');

    // Links [text](url)
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');

    // Line breaks
    html = html.replace(/\n/g, '<br>');

    // Clean up excessive breaks
    html = html.replace(/<br><br><br>/g, '<br><br>');
    html = html.replace(/<br><\/ul>/g, '</ul>');
    html = html.replace(/<ul><br>/g, '<ul>');

    return html;
}

function showTyping() {
    const container = document.getElementById('messagesContainer');
    container.insertAdjacentHTML('beforeend', `
        <div class="ai-message assistant" id="typingIndicator">
            <div class="ai-message-avatar">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle></svg>
            </div>
            <div class="ai-typing">
                <span></span><span></span><span></span>
            </div>
        </div>
    `);
    container.scrollTop = container.scrollHeight;
}

function hideTyping() {
    const indicator = document.getElementById('typingIndicator');
    if (indicator) indicator.remove();
}

async function loadConversation(id) {
    window.location.href = BASE_PATH + '/ai/chat/' + id;
}

async function refreshConversations() {
    try {
        const response = await fetch(BASE_PATH + '/api/ai/conversations', {
            credentials: 'same-origin'
        });
        const data = await response.json();

        if (data.success && data.data) {
            const list = document.getElementById('conversationsList');
            if (data.data.length === 0) {
                list.innerHTML = '<p style="text-align: center; color: var(--text-muted); padding: 2rem 1rem; font-size: 0.875rem;">No conversations yet.<br>Start a new chat!</p>';
            } else {
                list.innerHTML = data.data.map(conv => `
                    <div class="ai-conversation-item ${conv.id == currentConversationId ? 'active' : ''}"
                         onclick="loadConversation(${conv.id})"
                         data-id="${conv.id}">
                        <div class="conv-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                        </div>
                        <div class="conv-info">
                            <div class="conv-title">${escapeHtml(conv.title || 'New Chat')}</div>
                            <div class="conv-date">${formatDate(conv.created_at)}</div>
                        </div>
                    </div>
                `).join('');
            }
        }
    } catch (e) {
        console.error('Failed to refresh conversations:', e);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) +
           ', ' + date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}

// Scroll to bottom on load if there are messages
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('messagesContainer');
    container.scrollTop = container.scrollHeight;

    // Add keyboard navigation for conversation items
    document.querySelectorAll('.ai-conversation-item').forEach(item => {
        item.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const id = this.dataset.id;
                if (id) loadConversation(id);
            }
            if (e.key === 'Delete' || e.key === 'Backspace') {
                e.preventDefault();
                const id = this.dataset.id;
                if (id) deleteConversation(parseInt(id));
            }
        });
    });

    // Focus input on page load
    messageInput.focus();
});

// Announce status changes for screen readers
function announceToScreenReader(message) {
    const announcement = document.createElement('div');
    announcement.setAttribute('role', 'status');
    announcement.setAttribute('aria-live', 'polite');
    announcement.setAttribute('aria-atomic', 'true');
    announcement.className = 'sr-only';
    announcement.textContent = message;
    document.body.appendChild(announcement);
    setTimeout(() => announcement.remove(), 1000);
}
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
