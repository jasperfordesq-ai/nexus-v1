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
                            // Escape error message for security (parseMarkdown also escapes, but be explicit)
                            const safeError = escapeHtml(data.error);
                            contentEl.innerHTML = parseMarkdown('Sorry, an error occurred: ' + safeError);
                            addMessageActions(messageEl, fullContent || safeError, message);
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
