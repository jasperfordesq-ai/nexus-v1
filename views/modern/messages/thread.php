<?php
/**
 * Message Thread - Mobile-First Fullscreen Chat Interface
 * Path: views/modern/messages/thread.php
 *
 * Clean, minimal chat design focused on mobile usability.
 * True fullscreen - no header/footer interference.
 * Real-time messaging via Pusher with polling fallback.
 */

$hTitle = 'Chat';
$hSubtitle = 'with ' . htmlspecialchars($otherUser['name']);
$hGradient = 'htb-hero-gradient-members';
$hType = 'Direct Message';
$hideHero = true;

// CRITICAL: Disable ALL PTR and enable fullscreen mode
$bodyClass = 'no-ptr chat-page chat-fullscreen';
$hideUtilityBar = true;
$hideBottomNav = true;

require dirname(__DIR__, 2) . '/layouts/modern/header.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
$avatarUrl = $otherUser['avatar_url'] ?? null;
$initial = strtoupper(substr($otherUser['name'], 0, 1));
$currentUserId = $_SESSION['user_id'];
$otherUserId = $otherUser['id'];

// Check real-time online status
$otherUserLastActive = $otherUser['last_active_at'] ?? null;
$isOtherUserOnline = $otherUserLastActive && (strtotime($otherUserLastActive) > strtotime('-5 minutes'));
$onlineStatusText = $isOtherUserOnline ? 'Active now' : 'Offline';
?>

<!-- CSS moved to /assets/css/messages-thread.css -->

<div class="chat-app">
    <!-- Header -->
    <header class="chat-header">
        <a href="<?= $basePath ?>/messages" class="chat-back no-transition" aria-label="Back to messages" data-turbo="false">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <a href="<?= $basePath ?>/profile/<?= $otherUserId ?>" class="chat-user">
            <div class="chat-avatar">
                <?php if ($avatarUrl): ?>
                    <?= webp_avatar($avatarUrl, $otherUser['name'], 40) ?>
                <?php else: ?>
                    <?= $initial ?>
                <?php endif; ?>
            </div>
            <div class="chat-user-info">
                <div class="chat-user-name"><?= htmlspecialchars($otherUser['name']) ?></div>
                <div class="chat-user-status" id="userStatus">
                    <?php if ($isOtherUserOnline): ?>
                        <span class="chat-online-dot"></span>
                        <span>Active now</span>
                    <?php else: ?>
                        <span style="color: var(--chat-text-muted);">Offline</span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    </header>

    <!-- Messages -->
    <div class="chat-messages" id="chatMessages">
        <?php if (empty($messages)): ?>
            <div class="chat-empty">
                <div class="chat-empty-icon">
                    <i class="fa-solid fa-comment-dots"></i>
                </div>
                <p>Start the conversation with <?= htmlspecialchars($otherUser['name']) ?></p>
            </div>
        <?php else: ?>
            <?php
            $lastDate = null;
            foreach ($messages as $msg):
                $isSent = ($msg['sender_id'] == $currentUserId);
                $msgDate = date('Y-m-d', strtotime($msg['created_at']));
                $msgTime = date('g:i A', strtotime($msg['created_at']));

                // Date separator
                if ($lastDate !== $msgDate):
                    $today = date('Y-m-d');
                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                    if ($msgDate === $today) $dateLabel = 'Today';
                    elseif ($msgDate === $yesterday) $dateLabel = 'Yesterday';
                    else $dateLabel = date('M j, Y', strtotime($msg['created_at']));
                    $lastDate = $msgDate;
            ?>
                <div class="chat-date"><span><?= $dateLabel ?></span></div>
            <?php endif; ?>

            <div class="chat-bubble <?= $isSent ? 'sent' : 'received' ?>" data-id="<?= $msg['id'] ?>">
                <div class="chat-bubble-actions">
                    <button type="button" class="chat-bubble-action-btn" data-emoji="👍" aria-label="Like">👍</button>
                    <button type="button" class="chat-bubble-action-btn" data-emoji="❤️" aria-label="Love">❤️</button>
                    <button type="button" class="chat-bubble-action-btn" data-emoji="😂" aria-label="Haha">😂</button>
                    <button type="button" class="chat-bubble-action-btn menu-btn" data-action="menu" aria-label="More options">
                        <i class="fa-solid fa-ellipsis"></i>
                    </button>
                </div>
                <?php if (!empty($msg['audio_url'])): ?>
                    <?php
                    $audioDuration = (int)($msg['audio_duration'] ?? 0);
                    $durationStr = floor($audioDuration / 60) . ':' . str_pad($audioDuration % 60, 2, '0', STR_PAD_LEFT);
                    ?>
                    <div class="voice-message" data-audio-url="<?= htmlspecialchars($msg['audio_url']) ?>">
                        <button type="button" class="voice-play-btn" onclick="playVoiceMessage(this)">
                            <i class="fa-solid fa-play"></i>
                        </button>
                        <div class="voice-waveform">
                            <?php for ($i = 0; $i < 12; $i++): ?>
                                <span style="height:<?= rand(8, 24) ?>px"></span>
                            <?php endfor; ?>
                        </div>
                        <span class="voice-duration"><?= $durationStr ?></span>
                    </div>
                <?php else: ?>
                    <?= nl2br(htmlspecialchars($msg['body'])) ?>
                <?php endif; ?>
                <div class="chat-bubble-time">
                    <?= $msgTime ?>
                    <?php if ($isSent && !empty($msg['is_read'])): ?>
                        <i class="fa-solid fa-check-double chat-check"></i>
                    <?php elseif ($isSent): ?>
                        <i class="fa-solid fa-check chat-check"></i>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <!-- Mobile hint - shown only on touch devices -->
            <div class="chat-hint" id="chatHint">
                <i class="fa-solid fa-hand-pointer"></i> Tap a message to react, double-tap for 👍
            </div>
        <?php endif; ?>
    </div>

    <!-- Input -->
    <div class="chat-input-area">
        <button type="button" class="chat-voice-btn" id="voiceBtn" aria-label="Record voice message">
            <i class="fa-solid fa-microphone"></i>
        </button>
        <div class="chat-input-wrap">
            <textarea class="chat-input"
                      id="chatInput"
                      placeholder="Type a message..."
                      rows="1"
                      enterkeyhint="send"></textarea>
        </div>
        <button type="button" class="chat-send-btn" id="sendBtn" disabled aria-label="Send message">
            <i class="fa-solid fa-paper-plane"></i>
        </button>
    </div>
</div>

<!-- Voice Recording Overlay -->
<div class="voice-recording-overlay" id="voiceOverlay">
    <div class="voice-recording-visual">
        <i class="fa-solid fa-microphone"></i>
    </div>
    <div class="voice-recording-time" id="voiceTime">0:00</div>
    <div class="voice-recording-hint">Recording...</div>
    <div class="voice-recording-actions">
        <button type="button" class="voice-recording-cancel" id="voiceCancel" aria-label="Cancel recording">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <button type="button" class="voice-recording-send" id="voiceSend" aria-label="Send voice message">
            <i class="fa-solid fa-paper-plane"></i>
        </button>
    </div>
</div>

<!-- Message Actions Menu -->
<div class="msg-actions-overlay" id="msgActionsOverlay">
    <div class="msg-actions-sheet" id="msgActionsSheet">
        <div class="msg-reactions-row" id="msgReactionsRow">
            <button type="button" class="msg-reaction-pick" data-emoji="👍" title="Thumbs up">👍</button>
            <button type="button" class="msg-reaction-pick" data-emoji="❤️" title="Love">❤️</button>
            <button type="button" class="msg-reaction-pick" data-emoji="😂" title="Haha">😂</button>
            <button type="button" class="msg-reaction-pick" data-emoji="😮" title="Wow">😮</button>
            <button type="button" class="msg-reaction-pick" data-emoji="😢" title="Sad">😢</button>
            <button type="button" class="msg-reaction-pick" data-emoji="🙏" title="Thanks">🙏</button>
        </div>
        <div class="msg-actions-preview" id="msgActionsPreview"></div>
        <button type="button" class="msg-action-btn" id="msgCopyBtn">
            <i class="fa-solid fa-copy"></i>
            <span>Copy text</span>
        </button>
        <button type="button" class="msg-action-btn delete" id="msgDeleteBtn">
            <i class="fa-solid fa-trash"></i>
            <span>Delete message</span>
        </button>
    </div>
</div>

<!-- Delete Confirmation -->
<div class="msg-actions-overlay" id="msgDeleteOverlay">
    <div class="msg-actions-sheet">
        <div class="msg-delete-confirm">
            <p>Delete this message?</p>
            <div class="msg-delete-actions">
                <button type="button" class="msg-delete-cancel" id="msgDeleteCancel">Cancel</button>
                <button type="button" class="msg-delete-confirm-btn" id="msgDeleteConfirm">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="msg-toast" id="msgToast"></div>

<script>
// Voice message playback - must be global for onclick handlers
let currentAudio = null;
let currentPlayBtn = null;

function playVoiceMessage(btn) {
    const voiceMessage = btn.closest('.voice-message');
    const audioUrl = voiceMessage?.dataset.audioUrl;

    if (!audioUrl) {
        console.error('No audio URL found');
        return;
    }

    const icon = btn.querySelector('i');

    // If same audio is playing, pause it
    if (currentAudio && currentPlayBtn === btn) {
        if (currentAudio.paused) {
            currentAudio.play();
            icon.className = 'fa-solid fa-pause';
        } else {
            currentAudio.pause();
            icon.className = 'fa-solid fa-play';
        }
        return;
    }

    // Stop any currently playing audio
    if (currentAudio) {
        currentAudio.pause();
        currentAudio.currentTime = 0;
        if (currentPlayBtn) {
            const prevIcon = currentPlayBtn.querySelector('i');
            if (prevIcon) prevIcon.className = 'fa-solid fa-play';
        }
    }

    // Create and play new audio
    currentAudio = new Audio(audioUrl);
    currentPlayBtn = btn;

    currentAudio.addEventListener('play', () => {
        icon.className = 'fa-solid fa-pause';
    });

    currentAudio.addEventListener('pause', () => {
        icon.className = 'fa-solid fa-play';
    });

    currentAudio.addEventListener('ended', () => {
        icon.className = 'fa-solid fa-play';
        currentAudio = null;
        currentPlayBtn = null;
    });

    currentAudio.addEventListener('error', (e) => {
        console.error('Audio playback error:', e);
        icon.className = 'fa-solid fa-play';
        alert('Failed to play voice message');
        currentAudio = null;
        currentPlayBtn = null;
    });

    currentAudio.play().catch(err => {
        console.error('Play failed:', err);
        icon.className = 'fa-solid fa-play';
    });
}

(function() {
    'use strict';

    // Add chat-page class for CSS
    document.documentElement.classList.add('chat-page');
    document.body.classList.add('chat-page');

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
    // CHAT FUNCTIONALITY
    // =============================================

    const BASE_PATH = "<?= rtrim($basePath, '/') ?>";
    const CURRENT_USER_ID = <?= $currentUserId ?>;
    const OTHER_USER_ID = <?= $otherUserId ?>;
    const CSRF_TOKEN = "<?= $_SESSION['csrf_token'] ?? '' ?>";

    const messagesEl = document.getElementById('chatMessages');
    const inputEl = document.getElementById('chatInput');
    const sendBtn = document.getElementById('sendBtn');
    const statusEl = document.getElementById('userStatus');

    let lastMessageId = <?= !empty($messages) ? end($messages)['id'] : 0 ?>;
    let isTyping = false;
    let typingTimeout = null;
    let pollInterval = null;

    // Scroll to bottom
    function scrollToBottom(smooth = false) {
        if (messagesEl) {
            messagesEl.scrollTo({
                top: messagesEl.scrollHeight,
                behavior: smooth ? 'smooth' : 'auto'
            });
        }
    }

    // Initial scroll
    scrollToBottom();

    // Auto-resize textarea
    function autoResize() {
        inputEl.style.height = 'auto';
        inputEl.style.height = Math.min(inputEl.scrollHeight, 120) + 'px';
        sendBtn.disabled = !inputEl.value.trim();
    }

    inputEl?.addEventListener('input', autoResize);

    // Send message
    async function sendMessage() {
        const text = inputEl.value.trim();
        if (!text) return;

        // Disable input
        inputEl.disabled = true;
        sendBtn.disabled = true;

        // Optimistic UI - add message immediately
        const tempId = 'temp-' + Date.now();
        const now = new Date();
        const timeStr = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });

        // Remove empty state if present
        const emptyState = messagesEl.querySelector('.chat-empty');
        if (emptyState) emptyState.remove();

        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble sent';
        bubble.id = tempId;
        bubble.innerHTML = `
            ${escapeHtml(text).replace(/\n/g, '<br>')}
            <div class="chat-bubble-time">
                ${timeStr}
                <i class="fa-solid fa-clock chat-check" style="opacity:0.5"></i>
            </div>
        `;
        messagesEl.appendChild(bubble);
        scrollToBottom(true);

        // Clear input
        inputEl.value = '';
        autoResize();

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
                    receiver_id: OTHER_USER_ID,
                    body: text
                })
            });

            const data = await res.json();

            if (data.success && data.message) {
                // Update temp message with real ID
                bubble.dataset.id = data.message.id;
                bubble.id = '';
                lastMessageId = data.message.id;

                // Update check icon
                const check = bubble.querySelector('.chat-check');
                if (check) {
                    check.className = 'fa-solid fa-check chat-check';
                    check.style.opacity = '1';
                }
            } else {
                // Show error
                bubble.style.opacity = '0.5';
                bubble.innerHTML += '<div style="color:#ef4444;font-size:0.7rem;margin-top:4px;">Failed to send</div>';
            }
        } catch (e) {
            bubble.style.opacity = '0.5';
            bubble.innerHTML += '<div style="color:#ef4444;font-size:0.7rem;margin-top:4px;">Failed to send</div>';
        }

        inputEl.disabled = false;
        sendBtn.disabled = !inputEl.value.trim();
        inputEl.focus();
    }

    // Send button click
    sendBtn?.addEventListener('click', sendMessage);

    // Enter to send (Shift+Enter for newline)
    inputEl?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Poll for new messages
    async function pollMessages() {
        try {
            const res = await fetch(`${BASE_PATH}/api/messages/poll?other_user_id=${OTHER_USER_ID}&after=${lastMessageId}`, {
                credentials: 'include',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();

            if (data.messages && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    // Skip if already displayed
                    if (messagesEl.querySelector(`[data-id="${msg.id}"]`)) return;

                    const isSent = msg.sender_id == CURRENT_USER_ID;
                    const msgTime = new Date(msg.created_at).toLocaleTimeString('en-US', {
                        hour: 'numeric',
                        minute: '2-digit'
                    });

                    const bubble = document.createElement('div');
                    bubble.className = `chat-bubble ${isSent ? 'sent' : 'received'}`;
                    bubble.dataset.id = msg.id;

                    // Check if this is a voice message
                    if (msg.audio_url) {
                        const duration = parseInt(msg.audio_duration) || 0;
                        const durationStr = Math.floor(duration / 60) + ':' + String(duration % 60).padStart(2, '0');
                        bubble.innerHTML = `
                            <div class="voice-message" data-audio-url="${escapeHtml(msg.audio_url)}">
                                <button type="button" class="voice-play-btn" onclick="playVoiceMessage(this)">
                                    <i class="fa-solid fa-play"></i>
                                </button>
                                <div class="voice-waveform">
                                    ${Array.from({length: 12}, () => '<span style="height:' + (Math.random() * 16 + 8) + 'px"></span>').join('')}
                                </div>
                                <span class="voice-duration">${durationStr}</span>
                            </div>
                            <div class="chat-bubble-time">
                                ${msgTime}
                                ${isSent ? '<i class="fa-solid fa-check chat-check"></i>' : ''}
                            </div>
                        `;
                    } else {
                        bubble.innerHTML = `
                            ${escapeHtml(msg.body || '').replace(/\n/g, '<br>')}
                            <div class="chat-bubble-time">
                                ${msgTime}
                                ${isSent ? '<i class="fa-solid fa-check chat-check"></i>' : ''}
                            </div>
                        `;
                    }
                    messagesEl.appendChild(bubble);
                    lastMessageId = msg.id;
                });
                scrollToBottom(true);
            }
        } catch (e) {
            // Silent fail
        }
    }

    // Start polling
    pollInterval = setInterval(pollMessages, 3000);

    // Typing indicator
    function sendTyping() {
        if (isTyping) return;
        isTyping = true;

        fetch(`${BASE_PATH}/api/messages/typing`, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ receiver_id: OTHER_USER_ID })
        }).catch(() => {});

        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => { isTyping = false; }, 2000);
    }

    inputEl?.addEventListener('input', sendTyping);

    // Pusher real-time (if available)
    if (typeof Pusher !== 'undefined' && window.PUSHER_KEY) {
        try {
            const pusher = new Pusher(window.PUSHER_KEY, {
                cluster: window.PUSHER_CLUSTER || 'mt1',
                encrypted: true
            });

            const channelName = `chat-${Math.min(CURRENT_USER_ID, OTHER_USER_ID)}-${Math.max(CURRENT_USER_ID, OTHER_USER_ID)}`;
            const channel = pusher.subscribe(channelName);

            channel.bind('new-message', function(data) {
                if (data.sender_id == OTHER_USER_ID) {
                    // Skip if already displayed
                    if (messagesEl.querySelector(`[data-id="${data.id}"]`)) return;

                    const msgTime = new Date().toLocaleTimeString('en-US', {
                        hour: 'numeric',
                        minute: '2-digit'
                    });

                    const bubble = document.createElement('div');
                    bubble.className = 'chat-bubble received';
                    bubble.dataset.id = data.id;
                    bubble.innerHTML = `
                        ${escapeHtml(data.body).replace(/\n/g, '<br>')}
                        <div class="chat-bubble-time">${msgTime}</div>
                    `;
                    messagesEl.appendChild(bubble);
                    lastMessageId = data.id;
                    scrollToBottom(true);
                }
            });

            channel.bind('typing', function(data) {
                if (data.user_id == OTHER_USER_ID) {
                    statusEl.innerHTML = '<span class="chat-typing">typing...</span>';
                    setTimeout(() => {
                        // Restore to original status (user must be online if typing)
                        statusEl.innerHTML = '<span class="chat-online-dot"></span><span>Active now</span>';
                    }, 2000);
                }
            });

            // Reduce polling frequency when Pusher is connected
            clearInterval(pollInterval);
            pollInterval = setInterval(pollMessages, 10000);
        } catch (e) {
            console.warn('Pusher init failed:', e);
        }
    }

    // Keyboard handling for mobile
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', () => {
            // Adjust for keyboard
            setTimeout(scrollToBottom, 100);
        });
    }

    inputEl?.addEventListener('focus', () => {
        setTimeout(scrollToBottom, 300);
    });

    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // =============================================
    // VOICE MESSAGE FUNCTIONALITY
    // =============================================

    const voiceBtn = document.getElementById('voiceBtn');
    const voiceOverlay = document.getElementById('voiceOverlay');
    const voiceTime = document.getElementById('voiceTime');
    const voiceCancel = document.getElementById('voiceCancel');
    const voiceSend = document.getElementById('voiceSend');

    let mediaRecorder = null;
    let audioChunks = [];
    let audioStream = null;
    let recordingStartTime = 0;
    let recordingTimer = null;

    // Check for MediaRecorder support
    function isVoiceRecordingSupported() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder);
    }

    if (!isVoiceRecordingSupported() && voiceBtn) {
        voiceBtn.style.display = 'none';
    }

    // Start recording
    async function startRecording() {
        // Check HTTPS requirement
        if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
            alert('Voice messages require a secure connection (HTTPS).');
            return;
        }

        if (!isVoiceRecordingSupported()) {
            alert('Voice recording is not supported in your browser.');
            return;
        }

        try {
            // Request microphone permission
            audioStream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                }
            });

            // Determine best supported format
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

            mediaRecorder.start(100); // Collect data every 100ms
            recordingStartTime = Date.now();

            // Show overlay
            voiceOverlay.classList.add('active');
            voiceBtn.classList.add('recording');

            // Start timer
            recordingTimer = setInterval(updateRecordingTime, 100);

        } catch (err) {
            console.error('Microphone access error:', err);

            if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                alert('Microphone access denied. Please allow microphone permission and try again.');
            } else if (err.name === 'NotFoundError') {
                alert('No microphone found. Please connect a microphone and try again.');
            } else {
                alert('Could not access microphone: ' + (err.message || 'Unknown error'));
            }
        }
    }

    // Update recording time display
    function updateRecordingTime() {
        const elapsed = Math.floor((Date.now() - recordingStartTime) / 1000);
        const mins = Math.floor(elapsed / 60);
        const secs = elapsed % 60;
        voiceTime.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    // Stop recording without sending
    function cancelRecording() {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }
        clearInterval(recordingTimer);
        voiceOverlay.classList.remove('active');
        voiceBtn.classList.remove('recording');
        audioChunks = [];
        voiceTime.textContent = '0:00';

        // Stop audio stream
        if (audioStream) {
            audioStream.getTracks().forEach(track => track.stop());
            audioStream = null;
        }
    }

    // Stop recording and send
    async function sendVoiceMessage() {
        if (!mediaRecorder || mediaRecorder.state === 'inactive') return;

        const duration = Math.floor((Date.now() - recordingStartTime) / 1000);
        clearInterval(recordingTimer);

        // Stop and wait for final data
        mediaRecorder.stop();

        // Wait a bit for ondataavailable to fire
        await new Promise(resolve => setTimeout(resolve, 100));

        voiceOverlay.classList.remove('active');
        voiceBtn.classList.remove('recording');

        if (audioChunks.length === 0) {
            voiceTime.textContent = '0:00';
            return;
        }

        // Create blob
        const audioBlob = new Blob(audioChunks, { type: mediaRecorder.mimeType });

        // Create blob URL for immediate playback
        const blobUrl = URL.createObjectURL(audioBlob);

        // Convert to base64
        const reader = new FileReader();
        reader.onloadend = async () => {
            const base64data = reader.result.split(',')[1];

            // Optimistic UI - add voice message bubble with blob URL for immediate playback
            const tempId = 'temp-voice-' + Date.now();
            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
            const durationStr = `${Math.floor(duration / 60)}:${(duration % 60).toString().padStart(2, '0')}`;

            // Remove empty state if present
            const emptyState = messagesEl.querySelector('.chat-empty');
            if (emptyState) emptyState.remove();

            const bubble = document.createElement('div');
            bubble.className = 'chat-bubble sent';
            bubble.id = tempId;
            bubble.innerHTML = `
                <div class="voice-message" data-audio-url="${blobUrl}">
                    <button type="button" class="voice-play-btn" onclick="playVoiceMessage(this)">
                        <i class="fa-solid fa-play"></i>
                    </button>
                    <div class="voice-waveform">
                        ${Array.from({length: 12}, () => '<span style="height:' + (Math.random() * 16 + 8) + 'px"></span>').join('')}
                    </div>
                    <span class="voice-duration">${durationStr}</span>
                </div>
                <div class="chat-bubble-time">
                    ${timeStr}
                    <i class="fa-solid fa-clock chat-check" style="opacity:0.5"></i>
                </div>
            `;
            messagesEl.appendChild(bubble);
            scrollToBottom(true);

            // Send to server using form-urlencoded (required by VoiceMessageController)
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
                        receiver_id: OTHER_USER_ID,
                        audio_data: base64data,
                        mime_type: mediaRecorder.mimeType,
                        duration: duration
                    })
                });

                const data = await res.json();

                if (data.success) {
                    bubble.dataset.id = data.message_id;
                    bubble.id = '';
                    lastMessageId = data.message_id;

                    // Update to server URL for persistence
                    const voiceMsg = bubble.querySelector('.voice-message');
                    if (voiceMsg && data.audio_url) {
                        voiceMsg.dataset.audioUrl = data.audio_url;
                    }

                    const check = bubble.querySelector('.chat-check');
                    if (check) {
                        check.className = 'fa-solid fa-check chat-check';
                        check.style.opacity = '1';
                    }

                    // Revoke blob URL after successful upload
                    URL.revokeObjectURL(blobUrl);
                } else {
                    bubble.style.opacity = '0.5';
                    bubble.innerHTML += '<div style="color:#ef4444;font-size:0.7rem;margin-top:4px;">Failed to send</div>';
                }
            } catch (e) {
                bubble.style.opacity = '0.5';
                bubble.innerHTML += '<div style="color:#ef4444;font-size:0.7rem;margin-top:4px;">Failed to send</div>';
            }
        };

        reader.readAsDataURL(audioBlob);
        audioChunks = [];
        voiceTime.textContent = '0:00';

        // Stop audio stream
        if (audioStream) {
            audioStream.getTracks().forEach(track => track.stop());
            audioStream = null;
        }
    }

    // Event listeners
    voiceBtn?.addEventListener('click', () => {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            cancelRecording();
        } else {
            startRecording();
        }
    });

    voiceCancel?.addEventListener('click', cancelRecording);
    voiceSend?.addEventListener('click', sendVoiceMessage);

    // Cleanup on page leave
    window.addEventListener('beforeunload', () => {
        clearInterval(pollInterval);
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
        }
    });

    // =============================================
    // MESSAGE DELETE FUNCTIONALITY
    // =============================================

    const msgActionsOverlay = document.getElementById('msgActionsOverlay');
    const msgActionsPreview = document.getElementById('msgActionsPreview');
    const msgCopyBtn = document.getElementById('msgCopyBtn');
    const msgDeleteBtn = document.getElementById('msgDeleteBtn');
    const msgDeleteOverlay = document.getElementById('msgDeleteOverlay');
    const msgDeleteCancel = document.getElementById('msgDeleteCancel');
    const msgDeleteConfirm = document.getElementById('msgDeleteConfirm');
    const msgToast = document.getElementById('msgToast');

    let selectedBubble = null;
    let selectedMessageId = null;
    let longPressTimer = null;
    const LONG_PRESS_DURATION = 500;

    // Show toast notification
    function showToast(message, duration = 2000) {
        msgToast.textContent = message;
        msgToast.classList.add('show');
        setTimeout(() => msgToast.classList.remove('show'), duration);
    }

    // Open message actions menu
    function openMessageActions(bubble) {
        selectedBubble = bubble;
        selectedMessageId = bubble.dataset.id;

        if (!selectedMessageId || selectedMessageId.startsWith('temp')) {
            return; // Don't allow actions on unsent messages
        }

        bubble.classList.add('selected');

        // Hide the hint on first use
        const hint = document.getElementById('chatHint');
        if (hint) {
            hint.style.transition = 'opacity 0.3s';
            hint.style.opacity = '0';
            setTimeout(() => hint.remove(), 300);
        }

        // Get message preview text
        const voiceMsg = bubble.querySelector('.voice-message');
        if (voiceMsg) {
            msgActionsPreview.innerHTML = '<i class="fa-solid fa-microphone"></i> Voice message';
            msgCopyBtn.style.display = 'none';
        } else {
            // Get text content excluding action buttons
            const clone = bubble.cloneNode(true);
            clone.querySelectorAll('.chat-bubble-actions, .chat-bubble-time, .chat-bubble-reactions').forEach(el => el.remove());
            const textContent = clone.textContent?.trim() || '';
            msgActionsPreview.textContent = textContent.substring(0, 100) + (textContent.length > 100 ? '...' : '');
            msgCopyBtn.style.display = '';
        }

        msgActionsOverlay.classList.add('active');
    }

    // Close message actions menu
    function closeMessageActions() {
        msgActionsOverlay.classList.remove('active');
        if (selectedBubble) {
            selectedBubble.classList.remove('selected');
        }
        selectedBubble = null;
        selectedMessageId = null;
    }

    // Copy message text
    function copyMessageText() {
        if (!selectedBubble) return;

        const textContent = selectedBubble.childNodes[0]?.textContent?.trim() || '';
        if (navigator.clipboard && textContent) {
            navigator.clipboard.writeText(textContent).then(() => {
                showToast('Copied to clipboard');
            }).catch(() => {
                showToast('Failed to copy');
            });
        }
        closeMessageActions();
    }

    // Show delete confirmation
    function showDeleteConfirm() {
        closeMessageActions();
        msgDeleteOverlay.classList.add('active');
    }

    // Close delete confirmation
    function closeDeleteConfirm() {
        msgDeleteOverlay.classList.remove('active');
    }

    // Delete message
    async function deleteMessage() {
        if (!selectedMessageId) return;

        const messageId = selectedMessageId;
        const bubble = messagesEl.querySelector(`[data-id="${messageId}"]`);

        closeDeleteConfirm();

        // Optimistic UI - fade out the message
        if (bubble) {
            bubble.style.transition = 'opacity 0.3s, transform 0.3s';
            bubble.style.opacity = '0.5';
            bubble.style.transform = 'scale(0.95)';
        }

        try {
            const res = await fetch(`${BASE_PATH}/api/messages/delete`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ message_id: messageId })
            });

            const data = await res.json();

            if (data.success) {
                if (bubble) {
                    bubble.style.opacity = '0';
                    bubble.style.transform = 'scale(0.8)';
                    setTimeout(() => bubble.remove(), 300);
                }
                showToast('Message deleted');
            } else {
                // Restore bubble on failure
                if (bubble) {
                    bubble.style.opacity = '1';
                    bubble.style.transform = 'scale(1)';
                }
                showToast(data.error || 'Failed to delete');
            }
        } catch (e) {
            // Restore bubble on error
            if (bubble) {
                bubble.style.opacity = '1';
                bubble.style.transform = 'scale(1)';
            }
            showToast('Failed to delete message');
        }

        selectedMessageId = null;
    }

    // Long press detection for mobile
    function handleTouchStart(e) {
        const bubble = e.target.closest('.chat-bubble');
        if (!bubble || !bubble.dataset.id) return;

        longPressTimer = setTimeout(() => {
            e.preventDefault();
            openMessageActions(bubble);
        }, LONG_PRESS_DURATION);
    }

    function handleTouchEnd() {
        clearTimeout(longPressTimer);
    }

    function handleTouchMove() {
        clearTimeout(longPressTimer);
    }

    // Context menu for desktop (right-click)
    function handleContextMenu(e) {
        const bubble = e.target.closest('.chat-bubble');
        if (!bubble || !bubble.dataset.id) return;

        e.preventDefault();
        openMessageActions(bubble);
    }

    // Attach event listeners to messages container
    messagesEl?.addEventListener('touchstart', handleTouchStart, { passive: true });
    messagesEl?.addEventListener('touchend', handleTouchEnd);
    messagesEl?.addEventListener('touchmove', handleTouchMove);
    messagesEl?.addEventListener('contextmenu', handleContextMenu);

    // Handle action button clicks (reactions and menu)
    messagesEl?.addEventListener('click', function(e) {
        const actionBtn = e.target.closest('.chat-bubble-action-btn');
        if (actionBtn) {
            e.preventDefault();
            e.stopPropagation();
            const bubble = actionBtn.closest('.chat-bubble');

            // Quick reaction button
            if (actionBtn.dataset.emoji) {
                const msgId = bubble?.dataset.id;
                if (msgId && !msgId.startsWith('temp')) {
                    toggleReaction(msgId, actionBtn.dataset.emoji);
                    hideHint();
                }
                return;
            }

            // Menu button
            if (actionBtn.dataset.action === 'menu' && bubble) {
                openMessageActions(bubble);
            }
        }
    });

    // Track active bubble for mobile tap-to-show
    let activeBubbleForActions = null;

    // Close action bars when clicking outside
    function closeAllActionBars() {
        document.querySelectorAll('.chat-bubble.show-actions').forEach(b => {
            b.classList.remove('show-actions');
        });
        activeBubbleForActions = null;
    }

    // Hide hint
    const chatHint = document.getElementById('chatHint');
    function hideHint() {
        if (chatHint && chatHint.parentNode) {
            chatHint.style.transition = 'opacity 0.3s';
            chatHint.style.opacity = '0';
            setTimeout(() => chatHint.remove(), 300);
        }
    }

    // Close action bars when tapping elsewhere
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.chat-bubble')) {
            closeAllActionBars();
        }
    });

    // Action button handlers
    msgCopyBtn?.addEventListener('click', copyMessageText);
    msgDeleteBtn?.addEventListener('click', showDeleteConfirm);
    msgDeleteCancel?.addEventListener('click', closeDeleteConfirm);
    msgDeleteConfirm?.addEventListener('click', deleteMessage);

    // Close overlays on backdrop click
    msgActionsOverlay?.addEventListener('click', function(e) {
        if (e.target === this) closeMessageActions();
    });
    msgDeleteOverlay?.addEventListener('click', function(e) {
        if (e.target === this) closeDeleteConfirm();
    });

    // Escape key to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (msgDeleteOverlay?.classList.contains('active')) {
                closeDeleteConfirm();
            } else if (msgActionsOverlay?.classList.contains('active')) {
                closeMessageActions();
            }
        }
    });

    // =============================================
    // MESSAGE REACTIONS FUNCTIONALITY
    // =============================================

    const msgReactionsRow = document.getElementById('msgReactionsRow');

    // Handle reaction picker in action menu
    msgReactionsRow?.addEventListener('click', async function(e) {
        const btn = e.target.closest('.msg-reaction-pick');
        if (!btn || !selectedMessageId) return;

        const emoji = btn.dataset.emoji;
        if (!emoji) return;

        closeMessageActions();
        await toggleReaction(selectedMessageId, emoji);
    });

    // Toggle reaction on a message
    async function toggleReaction(messageId, emoji) {
        const bubble = messagesEl.querySelector(`[data-id="${messageId}"]`);
        if (!bubble) return;

        try {
            const res = await fetch(`${BASE_PATH}/api/messages/reaction`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ message_id: messageId, emoji: emoji })
            });

            const data = await res.json();

            if (data.success) {
                updateBubbleReactions(bubble, data.reactions);
                if (data.action === 'added') {
                    showToast('Reaction added');
                }
            } else {
                showToast(data.error || 'Failed to add reaction');
            }
        } catch (e) {
            showToast('Failed to add reaction');
        }
    }

    // Update reactions display on a bubble
    function updateBubbleReactions(bubble, reactions) {
        // Remove existing reactions container
        let container = bubble.querySelector('.chat-bubble-reactions');
        if (container) {
            container.remove();
        }

        // Add new reactions if any
        if (reactions && reactions.length > 0) {
            container = document.createElement('div');
            container.className = 'chat-bubble-reactions';

            reactions.forEach(r => {
                const isMine = r.user_ids.includes(CURRENT_USER_ID);
                const reactionEl = document.createElement('span');
                reactionEl.className = 'chat-reaction' + (isMine ? ' mine' : '');
                reactionEl.dataset.emoji = r.emoji;
                reactionEl.innerHTML = `
                    <span class="chat-reaction-emoji">${r.emoji}</span>
                    ${r.count > 1 ? `<span class="chat-reaction-count">${r.count}</span>` : ''}
                `;
                reactionEl.addEventListener('click', (e) => {
                    e.stopPropagation();
                    toggleReaction(bubble.dataset.id, r.emoji);
                });
                container.appendChild(reactionEl);
            });

            // Insert before the time element
            const timeEl = bubble.querySelector('.chat-bubble-time');
            if (timeEl) {
                bubble.insertBefore(container, timeEl);
            } else {
                bubble.appendChild(container);
            }
        }
    }

    // Single tap to show action bar, double-tap to add thumbs up (mobile)
    let lastTapTime = 0;
    let lastTapBubble = null;
    let singleTapTimer = null;

    messagesEl?.addEventListener('click', function(e) {
        const bubble = e.target.closest('.chat-bubble');
        if (!bubble || !bubble.dataset.id || bubble.dataset.id.startsWith('temp')) return;

        // Don't trigger on reaction clicks or action buttons
        if (e.target.closest('.chat-reaction') || e.target.closest('.chat-bubble-action-btn')) return;

        const now = Date.now();

        // Check for double tap
        if (lastTapBubble === bubble && (now - lastTapTime) < 300) {
            // Double tap detected - add thumbs up
            clearTimeout(singleTapTimer);
            toggleReaction(bubble.dataset.id, '👍');
            lastTapTime = 0;
            lastTapBubble = null;
            hideHint();
            // Hide action bar after double tap
            bubble.classList.remove('show-actions');
        } else {
            // Single tap - show action bar on touch devices
            lastTapTime = now;
            lastTapBubble = bubble;

            // On touch devices, show action bar after short delay (to detect double tap)
            const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
            if (isTouchDevice) {
                clearTimeout(singleTapTimer);
                singleTapTimer = setTimeout(() => {
                    // Close other action bars first
                    closeAllActionBars();
                    // Show this bubble's action bar
                    bubble.classList.add('show-actions');
                    hideHint();
                }, 200);
            }
        }
    });

    // Load existing reactions on page load
    async function loadExistingReactions() {
        const bubbles = messagesEl?.querySelectorAll('.chat-bubble[data-id]');
        if (!bubbles || bubbles.length === 0) return;

        // Collect message IDs (exclude temp messages)
        const messageIds = [];
        bubbles.forEach(b => {
            const id = b.dataset.id;
            if (id && !id.startsWith('temp')) {
                messageIds.push(parseInt(id));
            }
        });

        if (messageIds.length === 0) return;

        try {
            const res = await fetch(`${BASE_PATH}/api/messages/reactions-batch?ids=${messageIds.join(',')}`, {
                credentials: 'include',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!res.ok) return;

            const data = await res.json();
            if (data.success && data.reactions) {
                // Apply reactions to bubbles
                for (const [msgId, reactions] of Object.entries(data.reactions)) {
                    const bubble = messagesEl.querySelector(`[data-id="${msgId}"]`);
                    if (bubble && reactions.length > 0) {
                        updateBubbleReactions(bubble, reactions);
                    }
                }
            }
        } catch (e) {
            console.log('Could not load reactions:', e);
        }
    }

    // Load reactions after a short delay
    setTimeout(loadExistingReactions, 500);

    // Visual Viewport API - Handle mobile keyboard appearance
    // This prevents the chat from glitching when keyboard opens/closes
    if (window.visualViewport) {
        const chatApp = document.querySelector('.chat-app');
        const inputArea = document.querySelector('.chat-input-area');

        function handleViewportResize() {
            if (!chatApp || !inputArea) return;

            // Calculate the keyboard height by comparing viewport to window
            const keyboardHeight = window.innerHeight - window.visualViewport.height;

            // When keyboard is open, adjust the chat app height
            if (keyboardHeight > 100) {
                // Keyboard is visible
                chatApp.style.height = `${window.visualViewport.height}px`;
                // Scroll to bottom to keep input visible
                messagesEl?.scrollTo({
                    top: messagesEl.scrollHeight,
                    behavior: 'smooth'
                });
            } else {
                // Keyboard is hidden - reset to full viewport
                chatApp.style.height = '';
            }
        }

        // Listen for viewport resize (keyboard open/close)
        window.visualViewport.addEventListener('resize', handleViewportResize);
        window.visualViewport.addEventListener('scroll', handleViewportResize);

        // Also handle when input is focused
        inputEl?.addEventListener('focus', () => {
            // Small delay to let keyboard fully appear
            setTimeout(() => {
                handleViewportResize();
                messagesEl?.scrollTo({
                    top: messagesEl.scrollHeight,
                    behavior: 'smooth'
                });
            }, 300);
        });
    }
})();
</script>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
