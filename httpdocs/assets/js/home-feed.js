/**
 * Home Feed JavaScript
 * Extracted from views/modern/home.php for maintainability
 *
 * Dependencies:
 * - NexusMobile (optional, for toast notifications)
 * - FeedFilter (optional, for universal feed filtering)
 * - SocialInteractions (for social features)
 *
 * Required globals (set by PHP):
 * - window.HomeFeed.isLoggedIn
 * - window.HomeFeed.baseUrl
 */

(function() {
    'use strict';

    // ============================================
    // CONFIGURATION - Set by PHP before this script loads
    // ============================================
    const config = window.HomeFeed || {};
    const IS_LOGGED_IN = config.isLoggedIn || false;
    const BASE_URL = config.baseUrl || '';

    // ============================================
    // FACEBOOK-STYLE FEED MENU FUNCTIONS
    // ============================================

    function toggleFeedItemMenu(btn) {
        if (event) event.stopPropagation();
        const dropdown = btn.nextElementSibling;
        if (!dropdown) return;

        const isOpen = dropdown.classList.contains('show');
        closeFeedMenus();

        if (!isOpen) {
            dropdown.classList.add('show');
            document.addEventListener('click', closeFeedMenusOnOutsideClick);
        }
    }

    function closeFeedMenus() {
        document.querySelectorAll('.feed-item-menu-dropdown.show').forEach(d => {
            d.classList.remove('show');
        });
        document.removeEventListener('click', closeFeedMenusOnOutsideClick);
    }

    function closeFeedMenusOnOutsideClick(e) {
        if (!e.target.closest('.feed-item-menu-container')) {
            closeFeedMenus();
        }
    }

    // Hide post function
    function hidePost(postId) {
        if (!IS_LOGGED_IN) {
            window.location.href = BASE_URL + '/login';
            return;
        }

        fetch(BASE_URL + '/api/feed/hide', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ post_id: postId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Fade out the post
                const card = event && event.target ? event.target.closest('.fb-card') : null;
                if (card) {
                    card.style.transition = 'opacity 0.3s, transform 0.3s';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.95)';
                    setTimeout(() => card.remove(), 300);
                }
                showFeedToast('Post hidden. You won\'t see this anymore.');
            } else {
                showFeedToast(data.error || 'Could not hide post', 'error');
            }
        })
        .catch(() => showFeedToast('Could not hide post', 'error'));
    }

    // Mute user function
    function muteUser(userId) {
        if (!IS_LOGGED_IN) {
            window.location.href = BASE_URL + '/login';
            return;
        }

        fetch(BASE_URL + '/api/feed/mute', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ user_id: userId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showFeedToast('User muted. You\'ll see fewer of their posts.');
            } else {
                showFeedToast(data.error || 'Could not mute user', 'error');
            }
        })
        .catch(() => showFeedToast('Could not mute user', 'error'));
    }

    // Report post function
    function reportPost(postId) {
        if (!IS_LOGGED_IN) {
            window.location.href = BASE_URL + '/login';
            return;
        }

        if (confirm('Are you sure you want to report this post?')) {
            fetch(BASE_URL + '/api/feed/report', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ post_id: postId, target_type: 'post' })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showFeedToast('Thanks for letting us know. We\'ll review this post.');
                } else {
                    showFeedToast(data.error || 'Could not submit report', 'error');
                }
            })
            .catch(() => showFeedToast('Could not submit report', 'error'));
        }
    }

    // Simple toast notification
    function showFeedToast(message, type = 'success') {
        // Use NexusMobile toast if available
        if (window.NexusMobile && NexusMobile.showToast) {
            NexusMobile.showToast(message, type);
            return;
        }

        // Use global showToast if available
        if (typeof showToast === 'function') {
            showToast(message, type);
            return;
        }

        // Fallback toast
        const existing = document.querySelector('.feed-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = 'feed-toast feed-toast--' + type;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('feed-toast--hiding');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // ============================================
    // SKELETON LOADER - Show shimmer then reveal content
    // ============================================
    function initSkeletonLoader() {
        const skeleton = document.getElementById('skeletonLoader');
        const feed = document.getElementById('feedContainer');

        if (!skeleton || !feed) {
            return;
        }

        // Brief shimmer effect then reveal real content
        setTimeout(function() {
            skeleton.classList.add('hidden');
            feed.classList.add('loaded');
        }, 400);
    }

    // ============================================
    // FEED FILTER FUNCTION
    // ============================================
    function filterFeed(filterType) {
        // Update button states
        document.querySelectorAll('.feed-filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`.feed-filter-btn[data-filter="${filterType}"]`)?.classList.add('active');

        // Get all feed items
        const feedItems = document.querySelectorAll('.fb-card[data-feed-type]');
        let visibleCount = 0;

        feedItems.forEach(item => {
            if (filterType === 'all') {
                item.style.display = '';
                visibleCount++;
                return;
            }

            const itemType = item.dataset.feedType;

            if (itemType === filterType) {
                item.style.display = '';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        // Haptic feedback
        if (navigator.vibrate) navigator.vibrate(10);
    }

    // ============================================
    // UNIVERSAL FEED FILTER INTEGRATION
    // ============================================
    function initFeedFilterIntegration() {
        if (typeof FeedFilter === 'undefined') return;

        // Track previous state to detect changes that require reload
        let previousAlgo = FeedFilter.getActiveFilter().algorithmMode;
        let previousLocation = FeedFilter.getActiveFilter().locationMode;
        let previousRadius = FeedFilter.getActiveFilter().radius;

        FeedFilter.onFilterChange(function(state) {
            // Check if algo, location mode, or radius changed
            const needsReload = (
                state.algorithmMode !== previousAlgo ||
                state.locationMode !== previousLocation ||
                (state.locationMode === 'nearby' && state.radius !== previousRadius)
            );

            if (needsReload) {
                window.location.reload();
                return;
            }

            // Update tracking
            previousAlgo = state.algorithmMode;
            previousLocation = state.locationMode;
            previousRadius = state.radius;

            // Map filter names
            const filterMap = {
                'all': 'all',
                'listings': 'listings',
                'events': 'events',
                'goals': 'goals',
                'polls': 'polls',
                'volunteering': 'volunteering',
                'groups': 'groups',
                'resources': 'resources'
            };

            const legacyFilter = filterMap[state.filter] || 'all';
            filterFeed(legacyFilter);

            // Handle sub-filters
            if (state.filter === 'listings' && state.subFilter) {
                const feedItems = document.querySelectorAll('[data-feed-type="listings"], .feed-listing');
                feedItems.forEach(item => {
                    if (state.subFilter === 'all') {
                        item.style.display = '';
                    } else {
                        const listingType = item.dataset.listingType ||
                            (item.querySelector('.listing-type-offer') ? 'offers' :
                             item.querySelector('.listing-type-request') ? 'requests' : null);

                        if (listingType === state.subFilter) {
                            item.style.display = '';
                        } else if (listingType) {
                            item.style.display = 'none';
                        }
                    }
                });
            }
        });
    }

    // ============================================
    // COMPOSER FUNCTIONS
    // ============================================

    // Focus Composer - scrolls to and focuses the textarea
    function focusComposer() {
        const composer = document.getElementById('composer-input');
        if (composer) {
            composer.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => composer.focus(), 300);
        }
    }

    // Image preview
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('image-preview-img').src = e.target.result;
                document.getElementById('image-preview-area').style.display = 'block';

                // Hide video preview if showing
                const videoArea = document.getElementById('video-preview-area');
                const videoPlayer = document.getElementById('video-preview-player');
                const videoInput = document.getElementById('post-video-input');
                if (videoArea) {
                    videoArea.style.display = 'none';
                    if (videoPlayer && videoPlayer.src) {
                        URL.revokeObjectURL(videoPlayer.src);
                        videoPlayer.src = '';
                    }
                    if (videoInput) videoInput.value = '';
                }
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function removeImage() {
        document.getElementById('post-image-input').value = '';
        document.getElementById('image-preview-area').style.display = 'none';
    }

    // Video preview
    function previewVideo(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const maxSize = 100 * 1024 * 1024; // 100MB limit

            if (file.size > maxSize) {
                showFeedToast('Video must be less than 100MB', 'error');
                input.value = '';
                return;
            }

            const videoPlayer = document.getElementById('video-preview-player');
            const videoArea = document.getElementById('video-preview-area');

            const videoURL = URL.createObjectURL(file);
            videoPlayer.src = videoURL;
            videoArea.style.display = 'block';

            // Hide image preview if showing
            document.getElementById('image-preview-area').style.display = 'none';
            document.getElementById('post-image-input').value = '';
        }
    }

    function removeVideo() {
        const videoPlayer = document.getElementById('video-preview-player');
        const videoArea = document.getElementById('video-preview-area');

        if (videoPlayer.src) {
            URL.revokeObjectURL(videoPlayer.src);
        }

        videoPlayer.src = '';
        document.getElementById('post-video-input').value = '';
        videoArea.style.display = 'none';
    }

    // ============================================
    // EMOJI PICKER
    // ============================================
    const emojiData = {
        smileys: ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '😉', '😊', '😇', '🥰', '😍', '🤩', '😘', '😗', '😚', '😙', '🥲', '😋', '😛', '😜', '🤪', '😝', '🤑', '🤗', '🤭', '🤫', '🤔', '🤐', '🤨', '😐', '😑', '😶', '😏', '😒', '🙄', '😬', '🤥', '😌', '😔', '😪', '🤤', '😴', '😷', '🤒', '🤕', '🤢', '🤮', '🤧', '🥵', '🥶', '🥴', '😵', '🤯', '🤠', '🥳', '🥸', '😎', '🤓', '🧐'],
        people: ['👋', '🤚', '🖐️', '✋', '🖖', '👌', '🤌', '🤏', '✌️', '🤞', '🤟', '🤘', '🤙', '👈', '👉', '👆', '🖕', '👇', '👍', '👎', '✊', '👊', '🤛', '🤜', '👏', '🙌', '👐', '🤲', '🤝', '🙏', '💪', '🦾', '🦿', '🦵', '🦶', '👂', '🦻', '👃', '🧠', '🫀', '🫁', '🦷', '🦴', '👀', '👁️', '👅', '👄', '👶', '🧒', '👦', '👧', '🧑', '👱', '👨', '🧔', '👩', '🧓', '👴', '👵'],
        nature: ['🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮', '🐷', '🐽', '🐸', '🐵', '🙈', '🙉', '🙊', '🐒', '🐔', '🐧', '🐦', '🐤', '🐣', '🐥', '🦆', '🦅', '🦉', '🦇', '🐺', '🐗', '🐴', '🦄', '🐝', '🐛', '🦋', '🐌', '🐞', '🐜', '🦟', '🦗', '🌸', '💐', '🌷', '🌹', '🥀', '🌺', '🌻', '🌼', '🌱', '🌲', '🌳', '🌴', '🌵', '🌾', '🌿', '☘️', '🍀', '🍁', '🍂', '🍃'],
        food: ['🍕', '🍔', '🍟', '🌭', '🍿', '🧂', '🥓', '🥚', '🍳', '🧇', '🥞', '🧈', '🍞', '🥐', '🥖', '🥨', '🧀', '🥗', '🥙', '🥪', '🌮', '🌯', '🫔', '🥫', '🍝', '🍜', '🍲', '🍛', '🍣', '🍱', '🥟', '🦪', '🍤', '🍙', '🍚', '🍘', '🍥', '🥠', '🥮', '🍢', '🍡', '🍧', '🍨', '🍦', '🥧', '🧁', '🍰', '🎂', '🍮', '🍭', '🍬', '🍫', '🍩', '🍪', '🌰', '🥜', '🍯', '🥛', '🍼', '☕', '🍵', '🧃', '🥤', '🧋'],
        activities: ['⚽', '🏀', '🏈', '⚾', '🥎', '🎾', '🏐', '🏉', '🥏', '🎱', '🪀', '🏓', '🏸', '🏒', '🏑', '🥍', '🏏', '🪃', '🥅', '⛳', '🪁', '🏹', '🎣', '🤿', '🥊', '🥋', '🎽', '🛹', '🛼', '🛷', '⛸️', '🥌', '🎿', '⛷️', '🏂', '🪂', '🏋️', '🤼', '🤸', '🤺', '⛹️', '🤾', '🏌️', '🏇', '🧘', '🏄', '🏊', '🤽', '🚣', '🧗', '🚵', '🚴', '🏆', '🥇', '🥈', '🥉', '🏅', '🎖️', '🏵️', '🎗️', '🎫', '🎟️', '🎪', '🎭'],
        travel: ['🚗', '🚕', '🚙', '🚌', '🚎', '🏎️', '🚓', '🚑', '🚒', '🚐', '🛻', '🚚', '🚛', '🚜', '🦯', '🦽', '🦼', '🛴', '🚲', '🛵', '🏍️', '🛺', '🚨', '🚔', '🚍', '🚘', '🚖', '🚡', '🚠', '🚟', '🚃', '🚋', '🚞', '🚝', '🚄', '🚅', '🚈', '🚂', '🚆', '🚇', '🚊', '🚉', '✈️', '🛫', '🛬', '🛩️', '💺', '🛰️', '🚀', '🛸', '🚁', '🛶', '⛵', '🚤', '🛥️', '🛳️', '⛴️', '🚢', '⚓', '🪝', '⛽', '🚧', '🚦', '🚥'],
        objects: ['💡', '🔦', '🏮', '🪔', '📱', '📲', '💻', '🖥️', '🖨️', '⌨️', '🖱️', '🖲️', '💽', '💾', '💿', '📀', '📼', '📷', '📸', '📹', '🎥', '📽️', '🎞️', '📞', '☎️', '📟', '📠', '📺', '📻', '🎙️', '🎚️', '🎛️', '🧭', '⏱️', '⏲️', '⏰', '🕰️', '⌛', '⏳', '📡', '🔋', '🔌', '💰', '🪙', '💴', '💵', '💶', '💷', '💸', '💳', '🧾', '💎', '⚖️', '🪜', '🧰', '🪛', '🔧', '🔨', '⚒️', '🛠️', '⛏️', '🪚', '🔩', '⚙️'],
        symbols: ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '💟', '☮️', '✝️', '☪️', '🕉️', '☸️', '✡️', '🔯', '🕎', '☯️', '☦️', '🛐', '⛎', '♈', '♉', '♊', '♋', '♌', '♍', '♎', '♏', '♐', '♑', '♒', '♓', '🆔', '⚛️', '🉑', '☢️', '☣️', '📴', '📳', '🈶', '🈚', '🈸', '🈺', '🈷️', '✴️', '🆚', '💮', '🉐', '㊙️', '㊗️', '🈴', '🈵', '🈹']
    };

    let currentEmojiCategory = 'smileys';

    function toggleEmojiPicker() {
        const picker = document.getElementById('emoji-picker-container');
        if (!picker) return;

        const isVisible = picker.style.display !== 'none';

        if (isVisible) {
            picker.style.display = 'none';
        } else {
            picker.style.display = 'block';
            renderEmojis(currentEmojiCategory);
        }
    }

    function renderEmojis(category) {
        const grid = document.getElementById('emoji-grid');
        if (!grid) return;

        const emojis = emojiData[category] || [];

        grid.innerHTML = emojis.map(emoji =>
            `<button type="button" class="emoji-item" onclick="insertEmoji('${emoji}')">${emoji}</button>`
        ).join('');

        // Update active tab
        document.querySelectorAll('.emoji-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.category === category);
        });

        currentEmojiCategory = category;
    }

    function insertEmoji(emoji) {
        const textarea = document.getElementById('composer-input');
        if (!textarea) return;

        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;

        textarea.value = text.substring(0, start) + emoji + text.substring(end);
        textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
        textarea.focus();

        // Close the picker
        const picker = document.getElementById('emoji-picker-container');
        if (picker) {
            picker.style.display = 'none';
        }
    }

    function initEmojiPicker() {
        document.querySelectorAll('.emoji-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                renderEmojis(this.dataset.category);
            });
        });

        // Close emoji picker when clicking outside
        document.addEventListener('click', function(e) {
            const picker = document.getElementById('emoji-picker-container');
            const btn = document.getElementById('emoji-picker-btn');
            if (picker && btn && !picker.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
                picker.style.display = 'none';
            }
        });
    }

    // ============================================
    // POST TYPE SWITCHING (Multi-Module Composer)
    // ============================================
    const postTypeConfig = {
        post: { submitText: 'Post', icon: 'fa-paper-plane', action: '' },
        listing: { submitText: 'Create Listing', icon: 'fa-hand-holding-heart', action: '' },
        event: { submitText: 'Create Event', icon: 'fa-calendar-plus', action: '' },
        goal: { submitText: 'Create Goal', icon: 'fa-bullseye', action: '' },
        poll: { submitText: 'Create Poll', icon: 'fa-chart-bar', action: '' }
    };

    function switchPostType(type) {
        const config = postTypeConfig[type];
        if (!config) return;

        // Update active tab
        document.querySelectorAll('.composer-type-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.type === type);
        });

        // Update hidden input
        const typeInput = document.getElementById('post-type-input');
        if (typeInput) typeInput.value = type;

        // Update submit button
        const submitText = document.getElementById('submit-btn-text');
        const submitIcon = document.getElementById('submit-btn-icon');
        if (submitText) submitText.textContent = config.submitText;
        if (submitIcon) submitIcon.className = 'fa-solid ' + config.icon;

        // Show/hide fields
        document.querySelectorAll('.composer-fields-inline').forEach(field => {
            field.style.display = 'none';
        });
        const fieldsEl = document.getElementById('fields-' + type);
        if (fieldsEl) fieldsEl.style.display = 'block';

        // Update form action
        const form = document.getElementById('composer-form');
        if (form) {
            form.action = config.action || '';
        }

        updateRequiredFields(type);
    }

    function updateRequiredFields(type) {
        // Remove required from all hidden fields
        document.querySelectorAll('.composer-fields-inline').forEach(container => {
            if (container.style.display === 'none') {
                container.querySelectorAll('input, textarea, select').forEach(el => {
                    el.required = false;
                });
            }
        });

        const fieldsEl = document.getElementById('fields-' + type);
        if (!fieldsEl) return;

        switch(type) {
            case 'post':
                setRequired(fieldsEl, 'textarea[name="content"]', true);
                break;
            case 'listing':
                setRequired(fieldsEl, 'input[name="listing_title"]', true);
                setRequired(fieldsEl, 'select[name="listing_category_id"]', true);
                setRequired(fieldsEl, 'textarea[name="listing_description"]', true);
                break;
            case 'event':
                setRequired(fieldsEl, 'input[name="event_title"]', true);
                setRequired(fieldsEl, 'input[name="event_start_date"]', true);
                setRequired(fieldsEl, 'input[name="event_start_time"]', true);
                setRequired(fieldsEl, 'textarea[name="event_description"]', true);
                break;
            case 'goal':
                setRequired(fieldsEl, 'input[name="goal_title"]', true);
                break;
            case 'poll':
                setRequired(fieldsEl, 'input[name="poll_question"]', true);
                const pollOptions = fieldsEl.querySelectorAll('input[name="poll_options[]"]');
                if (pollOptions[0]) pollOptions[0].required = true;
                if (pollOptions[1]) pollOptions[1].required = true;
                break;
        }
    }

    function setRequired(container, selector, required) {
        const el = container.querySelector(selector);
        if (el) el.required = required;
    }

    // Poll option management
    let pollOptionCount = 2;

    function addPollOption() {
        if (pollOptionCount >= 10) {
            showFeedToast('Maximum 10 options allowed', 'error');
            return;
        }
        pollOptionCount++;

        const container = document.getElementById('poll-options-container');
        if (!container) return;

        const optionDiv = document.createElement('div');
        optionDiv.className = 'composer-poll-option';
        optionDiv.innerHTML = `
            <input type="text" name="poll_options[]" class="composer-input-inline" placeholder="Option ${pollOptionCount}">
            <button type="button" class="remove-option-btn" onclick="removePollOption(this)">
                <i class="fa-solid fa-times"></i>
            </button>
        `;
        container.appendChild(optionDiv);
    }

    function removePollOption(btn) {
        if (pollOptionCount <= 2) {
            showFeedToast('Minimum 2 options required', 'error');
            return;
        }
        btn.closest('.composer-poll-option').remove();
        pollOptionCount--;

        // Re-number placeholders
        const options = document.querySelectorAll('#poll-options-container input[name="poll_options[]"]');
        options.forEach((input, idx) => {
            input.placeholder = `Option ${idx + 1}`;
        });
    }

    // Listing attribute filtering based on category
    function filterListingAttributes() {
        const categorySelect = document.querySelector('select[name="listing_category_id"]');
        const typeInputs = document.querySelectorAll('input[name="listing_type"]');
        if (!categorySelect) return;

        const selectedCat = categorySelect.value;
        const selectedType = Array.from(typeInputs).find(i => i.checked)?.value || 'offer';

        document.querySelectorAll('.composer-attribute-item').forEach(item => {
            const itemCat = item.getAttribute('data-category-id');
            const itemType = item.getAttribute('data-target-type');

            const catMatch = itemCat === 'global' || itemCat == selectedCat || !selectedCat;
            const typeMatch = itemType === 'any' || itemType === selectedType;

            item.style.display = (catMatch && typeMatch) ? 'flex' : 'none';

            if (item.style.display === 'none') {
                const checkbox = item.querySelector('input');
                if (checkbox) checkbox.checked = false;
            }
        });
    }

    function initComposer() {
        updateRequiredFields('post');

        const catSelect = document.querySelector('select[name="listing_category_id"]');
        const typeInputs = document.querySelectorAll('input[name="listing_type"]');

        if (catSelect) {
            catSelect.addEventListener('change', filterListingAttributes);
        }
        typeInputs.forEach(input => {
            input.addEventListener('change', filterListingAttributes);
        });

        filterListingAttributes();
    }

    // ============================================
    // OFFLINE INDICATOR
    // ============================================
    function initOfflineIndicator() {
        const banner = document.getElementById('offlineBanner');
        if (!banner) return;

        let wasOffline = false;

        function handleOffline() {
            wasOffline = true;
            banner.classList.add('visible');
            if (navigator.vibrate) navigator.vibrate(100);
        }

        function handleOnline() {
            banner.classList.remove('visible');
            if (wasOffline) {
                showFeedToast('Connection restored');
                wasOffline = false;
            }
        }

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        if (!navigator.onLine) {
            handleOffline();
        }
    }

    // ============================================
    // BUTTON PRESS STATES - Native Touch Feel
    // ============================================
    function initButtonPressStates() {
        document.querySelectorAll('.fb-action-btn, .nexus-smart-btn, .fds-btn-primary, .fds-btn-secondary').forEach(btn => {
            btn.addEventListener('pointerdown', function() {
                this.classList.add('pressing');
            });

            btn.addEventListener('pointerup', function() {
                this.classList.remove('pressing');
            });

            btn.addEventListener('pointerleave', function() {
                this.classList.remove('pressing');
            });

            btn.addEventListener('pointercancel', function() {
                this.classList.remove('pressing');
            });
        });
    }

    // ============================================
    // INFINITE SCROLL PAGINATION
    // ============================================
    function initInfiniteScroll() {
        const sentinel = document.getElementById('feedSentinel');
        const endMessage = document.getElementById('feedEndMessage');
        const feedContainer = document.getElementById('feedContainer');

        if (!sentinel || !feedContainer) return;

        let currentPage = 1;
        let isLoading = false;
        let hasMore = true;
        const ITEMS_PER_PAGE = 15;

        const initialItems = feedContainer.querySelectorAll('.fb-card').length;
        if (initialItems < ITEMS_PER_PAGE) {
            if (endMessage) endMessage.style.display = 'block';
            return;
        }

        sentinel.style.display = 'flex';

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !isLoading && hasMore) {
                    loadMoreFeed();
                }
            });
        }, { rootMargin: '200px' });

        observer.observe(sentinel);

        async function loadMoreFeed() {
            isLoading = true;
            sentinel.style.display = 'flex';
            currentPage++;

            try {
                const formData = new FormData();
                formData.append('action', 'load_more_feed');
                formData.append('page', currentPage);

                if (typeof FeedFilter !== 'undefined') {
                    const filterState = FeedFilter.getActiveFilter();
                    formData.append('algo', filterState.algorithmMode || 'ranked');
                    formData.append('location', filterState.locationMode || 'global');
                    formData.append('radius', filterState.radius || 500);
                } else {
                    const urlParams = new URLSearchParams(window.location.search);
                    formData.append('algo', urlParams.get('algo') || 'ranked');
                    formData.append('location', urlParams.get('location') || 'global');
                    formData.append('radius', urlParams.get('radius') || '500');
                }

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.html && data.html.trim()) {
                    sentinel.insertAdjacentHTML('beforebegin', data.html);
                } else {
                    hasMore = false;
                }

                if (!hasMore || (data.items && data.items.length < ITEMS_PER_PAGE)) {
                    hasMore = false;
                    sentinel.style.display = 'none';
                    if (endMessage) endMessage.style.display = 'block';
                    observer.disconnect();
                }
            } catch (err) {
                console.error('Feed load error:', err);
                hasMore = false;
                sentinel.style.display = 'none';
                if (endMessage) {
                    endMessage.innerHTML = '<i class="fa-solid fa-exclamation-circle"></i> Failed to load more';
                    endMessage.style.display = 'block';
                }
            } finally {
                isLoading = false;
            }
        }
    }

    // ============================================
    // DYNAMIC THEME COLOR FOR STATUS BAR
    // ============================================
    function initDynamicThemeColor() {
        const themeColorMeta = document.querySelector('meta[name="theme-color"]');
        if (!themeColorMeta) return;

        function updateThemeColor() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            themeColorMeta.setAttribute('content', isDark ? '#0f172a' : '#ffffff');
        }

        const observer = new MutationObserver(updateThemeColor);
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-theme']
        });

        updateThemeColor();
    }

    // ============================================
    // INITIALIZE ON DOM READY
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        initSkeletonLoader();
        initFeedFilterIntegration();
        initEmojiPicker();
        initComposer();
        initOfflineIndicator();
        initButtonPressStates();
        initInfiniteScroll();
        initDynamicThemeColor();
    });

    // ============================================
    // EXPOSE PUBLIC API
    // ============================================
    window.HomeFeed = window.HomeFeed || {};
    Object.assign(window.HomeFeed, {
        // Feed menu
        toggleFeedItemMenu: toggleFeedItemMenu,
        closeFeedMenus: closeFeedMenus,
        hidePost: hidePost,
        muteUser: muteUser,
        reportPost: reportPost,
        showFeedToast: showFeedToast,

        // Filter
        filterFeed: filterFeed,

        // Composer
        focusComposer: focusComposer,
        previewImage: previewImage,
        removeImage: removeImage,
        previewVideo: previewVideo,
        removeVideo: removeVideo,

        // Emoji
        toggleEmojiPicker: toggleEmojiPicker,
        renderEmojis: renderEmojis,
        insertEmoji: insertEmoji,

        // Post type
        switchPostType: switchPostType,
        addPollOption: addPollOption,
        removePollOption: removePollOption,
        filterListingAttributes: filterListingAttributes
    });

    // Global aliases for onclick handlers in HTML
    window.toggleFeedItemMenu = toggleFeedItemMenu;
    window.hidePost = hidePost;
    window.muteUser = muteUser;
    window.reportPost = reportPost;
    window.focusComposer = focusComposer;
    window.previewImage = previewImage;
    window.removeImage = removeImage;
    window.previewVideo = previewVideo;
    window.removeVideo = removeVideo;
    window.toggleEmojiPicker = toggleEmojiPicker;
    window.insertEmoji = insertEmoji;
    window.switchPostType = switchPostType;
    window.addPollOption = addPollOption;
    window.removePollOption = removePollOption;
    window.filterFeed = filterFeed;

})();
