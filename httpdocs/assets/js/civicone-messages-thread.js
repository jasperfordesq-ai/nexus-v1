/**
 * CivicOne Messages Thread
 * Auto-scroll to bottom of conversation
 * WCAG 2.1 AA Compliant
 */

(function() {
    'use strict';

    // ============================================
    // Scroll to Bottom of Chat
    // ============================================
    function scrollToBottom() {
        const chatBox = document.getElementById('chat-messages');
        if (chatBox) {
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    }

    // ============================================
    // Initialize on Page Load
    // ============================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scrollToBottom);
    } else {
        scrollToBottom();
    }

})();
