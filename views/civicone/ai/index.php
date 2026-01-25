<?php
/**
 * AI Assistant Page - GOV.UK Design System
 * WCAG 2.1 AA Compliant Chat Interface
 */

$pageTitle = "AI Assistant";
\Nexus\Core\SEO::setTitle('AI Assistant - Your Timebank Companion');
\Nexus\Core\SEO::setDescription('Get intelligent help with timebanking, listings, and community features.');

require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
$currentConversationId = $conversation['id'] ?? null;
?>

<div class="govuk-width-container">
    <main class="govuk-main-wrapper">
        <div class="govuk-grid-row">
            <!-- Sidebar with conversations -->
            <aside class="govuk-grid-column-one-third" role="complementary" aria-label="Conversation history">
                <div class="govuk-!-margin-bottom-4">
                    <button type="button" class="govuk-button govuk-!-margin-bottom-0 civicone-button-full-width" data-module="govuk-button" onclick="startNewChat()">
                        <i class="fa-solid fa-plus govuk-!-margin-right-2" aria-hidden="true"></i>
                        New Chat
                    </button>
                </div>

                <h2 class="govuk-heading-s">Previous Chats</h2>

                <div id="conversationsList" role="list" aria-label="Previous conversations">
                    <?php if (empty($conversations)): ?>
                        <div class="govuk-!-padding-4 govuk-!-text-align-center civicone-panel-bg civicone-action-card">
                            <p class="govuk-body-s govuk-!-margin-bottom-0">
                                No conversations yet.<br>Start a new chat!
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): ?>
                            <?php $isActive = ($conv['id'] ?? 0) == $currentConversationId; ?>
                            <div class="govuk-!-margin-bottom-2 govuk-!-padding-3 civicone-conversation-item <?= $isActive ? 'civicone-conversation-item--active' : 'civicone-conversation-item--inactive' ?>"
                                 onclick="loadConversation(<?= $conv['id'] ?>)"
                                 data-id="<?= $conv['id'] ?>"
                                 role="button"
                                 tabindex="0"
                                 aria-label="Conversation: <?= htmlspecialchars($conv['title'] ?? 'New Chat') ?>">
                                <i class="fa-solid fa-message" aria-hidden="true"></i>
                                <div class="civicone-conversation-content">
                                    <p class="govuk-body-s govuk-!-font-weight-bold govuk-!-margin-bottom-0 civicone-text-truncate">
                                        <?= htmlspecialchars($conv['title'] ?? 'New Chat') ?>
                                    </p>
                                    <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-text-muted">
                                        <?= date('M j, g:i a', strtotime($conv['created_at'])) ?>
                                    </p>
                                </div>
                                <button type="button"
                                        onclick="event.stopPropagation(); deleteConversation(<?= $conv['id'] ?>)"
                                        class="govuk-button govuk-button--warning govuk-!-margin-bottom-0 civicone-btn-delete-small"
                                        aria-label="Delete conversation"
                                        title="Delete">
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>

            <!-- Main Chat Area -->
            <div class="govuk-grid-column-two-thirds" role="main" aria-label="AI Chat">
                <!-- Chat Header -->
                <div class="govuk-!-margin-bottom-4 govuk-!-padding-4 civicone-chat-header">
                    <div class="civicone-chat-footer">
                        <h1 class="govuk-heading-m govuk-!-margin-bottom-0">
                            <i class="fa-solid fa-robot govuk-!-margin-right-2" aria-hidden="true"></i>
                            <span id="chatTitle"><?= htmlspecialchars($conversation['title'] ?? 'NEXUS AI Assistant') ?></span>
                        </h1>
                        <strong class="govuk-tag govuk-tag--green">
                            <i class="fa-solid fa-brain govuk-!-margin-right-1" aria-hidden="true"></i>
                            <span id="providerName"><?= ucfirst($defaultProvider ?? 'Gemini') ?></span>
                        </strong>
                    </div>
                </div>

                <!-- Messages Container -->
                <div id="messagesContainer"
                     role="log"
                     aria-live="polite"
                     aria-label="Chat messages"
                     class="civicone-panel-bg civicone-chat-container">
                    <?php if (empty($messages)): ?>
                        <!-- Welcome State -->
                        <div id="welcomeState" class="govuk-!-text-align-center govuk-!-padding-6">
                            <p class="govuk-body govuk-!-margin-bottom-4">
                                <i class="fa-solid fa-robot fa-3x civicone-icon-blue" aria-hidden="true"></i>
                            </p>
                            <h2 class="govuk-heading-l">Welcome to NEXUS AI</h2>
                            <p class="govuk-body-l govuk-!-margin-bottom-6">
                                <?= nl2br(htmlspecialchars($welcomeMessage ?? "I'm your intelligent timebank assistant. I can help you find members, create listings, understand features, and more!")) ?>
                            </p>

                            <div class="govuk-button-group civicone-button-group-center">
                                <button type="button" class="govuk-button govuk-button--secondary" data-module="govuk-button" onclick="sendSuggestion('How does timebanking work?')">
                                    How does timebanking work?
                                </button>
                                <button type="button" class="govuk-button govuk-button--secondary" data-module="govuk-button" onclick="sendSuggestion('Help me write a listing description')">
                                    Help me write a listing
                                </button>
                                <button type="button" class="govuk-button govuk-button--secondary" data-module="govuk-button" onclick="sendSuggestion('What skills are in demand?')">
                                    What skills are in demand?
                                </button>
                                <button type="button" class="govuk-button govuk-button--secondary" data-module="govuk-button" onclick="sendSuggestion('How do I earn time credits?')">
                                    How do I earn credits?
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="govuk-!-margin-bottom-4 govuk-!-padding-4 <?= $msg['role'] === 'assistant' ? 'civicone-chat-bubble-assistant' : 'civicone-chat-bubble-user' ?>">
                                <div class="civicone-chat-message">
                                    <div class="civicone-chat-avatar <?= $msg['role'] === 'assistant' ? 'civicone-chat-avatar--assistant' : 'civicone-chat-avatar--user' ?>">
                                        <?php if ($msg['role'] === 'assistant'): ?>
                                            <i class="fa-solid fa-robot" aria-hidden="true"></i>
                                        <?php else: ?>
                                            <?= substr($_SESSION['user_name'] ?? 'U', 0, 1) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="civicone-flex-grow">
                                        <p class="govuk-body-s govuk-!-font-weight-bold govuk-!-margin-bottom-1">
                                            <?= $msg['role'] === 'assistant' ? 'AI Assistant' : ($_SESSION['user_name'] ?? 'You') ?>
                                        </p>
                                        <p class="govuk-body govuk-!-margin-bottom-0">
                                            <?= nl2br(htmlspecialchars($msg['content'])) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Input Area -->
                <div class="govuk-!-margin-top-4">
                    <div class="govuk-form-group govuk-!-margin-bottom-2">
                        <label class="govuk-label govuk-visually-hidden" for="messageInput">Type your message</label>
                        <textarea
                            class="govuk-textarea"
                            id="messageInput"
                            placeholder="Ask me anything about timebanking..."
                            rows="3"
                            onkeydown="handleInputKeydown(event)"
                            aria-label="Type your message"
                        ></textarea>
                    </div>
                    <div class="civicone-chat-footer">
                        <div class="govuk-button-group govuk-!-margin-bottom-0">
                            <button type="button" class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button" id="sendBtn" onclick="sendMessage()">
                                <i class="fa-solid fa-paper-plane govuk-!-margin-right-2" aria-hidden="true"></i>
                                Send Message
                            </button>
                            <button type="button" class="govuk-button govuk-button--warning govuk-!-margin-bottom-0 govuk-!-display-none" data-module="govuk-button" id="stopBtn" onclick="stopGeneration()">
                                <i class="fa-solid fa-stop govuk-!-margin-right-2" aria-hidden="true"></i>
                                Stop
                            </button>
                        </div>
                        <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">
                            Daily: <strong id="dailyUsage"><?= $limits['daily_used'] ?? 0 ?></strong>/<?= $limits['daily_limit'] ?? 50 ?>
                            |
                            Monthly: <strong id="monthlyUsage"><?= $limits['monthly_used'] ?? 0 ?></strong>/<?= $limits['monthly_limit'] ?? 1000 ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
var basePath = '<?= $basePath ?>';
var currentConversationId = <?= $currentConversationId ?? 'null' ?>;
var isGenerating = false;

function startNewChat() {
    window.location.href = basePath + '/ai';
}

function loadConversation(id) {
    window.location.href = basePath + '/ai/' + id;
}

function deleteConversation(id) {
    if (!confirm('Delete this conversation?')) return;

    fetch(basePath + '/api/ai/conversations/' + id, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' }
    }).then(function(response) {
        if (response.ok) {
            var item = document.querySelector('[data-id="' + id + '"]');
            if (item) item.remove();
            if (currentConversationId === id) {
                startNewChat();
            }
        }
    });
}

function sendSuggestion(text) {
    document.getElementById('messageInput').value = text;
    sendMessage();
}

function handleInputKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

function sendMessage() {
    var input = document.getElementById('messageInput');
    var message = input.value.trim();
    if (!message || isGenerating) return;

    // Hide welcome state
    var welcome = document.getElementById('welcomeState');
    if (welcome) welcome.classList.add('govuk-!-display-none');

    // Add user message
    addMessage('user', message);
    input.value = '';

    // Show loading state
    isGenerating = true;
    document.getElementById('sendBtn').classList.add('govuk-!-display-none');
    document.getElementById('stopBtn').classList.remove('govuk-!-display-none');

    // Add typing indicator
    var typingId = 'typing-' + Date.now();
    addMessage('assistant', '<i class="fa-solid fa-spinner fa-spin"></i> Thinking...', typingId);

    // Send to API
    fetch(basePath + '/api/ai/chat', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            message: message,
            conversation_id: currentConversationId
        })
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        // Remove typing indicator
        var typing = document.getElementById(typingId);
        if (typing) typing.remove();

        if (data.error) {
            addMessage('assistant', 'Error: ' + data.error);
        } else {
            addMessage('assistant', data.response);
            if (data.conversation_id && !currentConversationId) {
                currentConversationId = data.conversation_id;
            }
            // Update usage
            if (data.usage) {
                document.getElementById('dailyUsage').textContent = data.usage.daily || 0;
                document.getElementById('monthlyUsage').textContent = data.usage.monthly || 0;
            }
        }
    })
    .catch(function(err) {
        var typing = document.getElementById(typingId);
        if (typing) typing.remove();
        addMessage('assistant', 'Sorry, something went wrong. Please try again.');
    })
    .finally(function() {
        isGenerating = false;
        document.getElementById('sendBtn').classList.remove('govuk-!-display-none');
        document.getElementById('stopBtn').classList.add('govuk-!-display-none');
    });
}

function addMessage(role, content, id) {
    var container = document.getElementById('messagesContainer');
    var div = document.createElement('div');
    if (id) div.id = id;
    div.className = 'govuk-!-margin-bottom-4 govuk-!-padding-4 ' + (role === 'assistant' ? 'civicone-chat-bubble-assistant' : 'civicone-chat-bubble-user');

    var avatar = role === 'assistant'
        ? '<i class="fa-solid fa-robot" aria-hidden="true"></i>'
        : '<?= substr($_SESSION['user_name'] ?? 'U', 0, 1) ?>';
    var name = role === 'assistant' ? 'AI Assistant' : '<?= addslashes($_SESSION['user_name'] ?? 'You') ?>';
    var avatarClass = role === 'assistant' ? 'civicone-chat-avatar--assistant' : 'civicone-chat-avatar--user';

    div.innerHTML =
        '<div class="civicone-chat-message">' +
        '<div class="civicone-chat-avatar ' + avatarClass + '">' + avatar + '</div>' +
        '<div class="civicone-flex-grow">' +
        '<p class="govuk-body-s govuk-!-font-weight-bold govuk-!-margin-bottom-1">' + name + '</p>' +
        '<p class="govuk-body govuk-!-margin-bottom-0">' + content.replace(/\n/g, '<br>') + '</p>' +
        '</div></div>';

    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

function stopGeneration() {
    isGenerating = false;
    document.getElementById('sendBtn').classList.remove('govuk-!-display-none');
    document.getElementById('stopBtn').classList.add('govuk-!-display-none');
}
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
