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

<!-- AI Chat Interface CSS -->
<link rel="stylesheet" href="<?= NexusCoreTenantContext::getBasePath() ?>/assets/css/purged/civicone-ai-index.min.css">

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
                <p class="ai-empty-conversations">
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
                <button class="ai-stop-btn hidden" id="stopBtn" onclick="stopGeneration()" aria-label="Stop generation">
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

<!-- AI Chat Interface JavaScript -->
<script src="<?= NexusCoreTenantContext::getBasePath() ?>/assets/js/civicone-ai-index.min.js" defer></script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
