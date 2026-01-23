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
        <span class="ai-fab-icon ai-fab-icon-close" style="display: none;">
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

<!-- AI Chat Widget CSS now loaded via header.php: ai-chat-widget.css -->

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
