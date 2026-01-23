/**
 * Avatar/Image Placeholders
 * Shimmer loading, smooth fade-in, initials fallback
 * Version: 1.0 - 2026-01-19
 *
 * Usage:
 *   // Auto-applies to images with data-avatar or data-placeholder
 *   <img data-avatar src="..." alt="User">
 *
 *   // Create initials avatar
 *   AvatarPlaceholder.initials('John Doe', 'md');
 *
 *   // Force reload with placeholder
 *   AvatarPlaceholder.load(imgElement);
 */

(function() {
    'use strict';

    // Configuration
    const config = {
        autoInit: true,
        fadeInDuration: 300,
        showInitialsOnError: true,
        placeholderClass: 'avatar-placeholder',
        loadedClass: 'loaded',
        errorClass: 'avatar-error',
        selectors: [
            'img[data-avatar]',
            'img[data-placeholder]',
            '.avatar img',
            '.user-avatar img',
            '.profile-avatar img',
            '.member-avatar img'
        ]
    };

    // Color palette for initials
    const colorCount = 8;

    /**
     * Get color index from string
     */
    function getColorFromString(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        return (Math.abs(hash) % colorCount) + 1;
    }

    /**
     * Get initials from name
     */
    function getInitials(name) {
        if (!name) return '?';

        const parts = name.trim().split(/\s+/);
        if (parts.length === 1) {
            return parts[0].charAt(0).toUpperCase();
        }

        return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
    }

    /**
     * Create initials avatar element
     */
    function createInitialsAvatar(name, size = 'md') {
        const initials = getInitials(name);
        const color = getColorFromString(name || '');

        const el = document.createElement('div');
        el.className = `avatar-initials avatar-initials--${size}`;
        el.setAttribute('data-color', color);
        el.setAttribute('aria-label', name || 'User');
        el.textContent = initials;

        return el;
    }

    /**
     * Wrap image in placeholder container
     */
    function wrapImage(img) {
        // Skip if already wrapped
        if (img.parentElement?.classList.contains(config.placeholderClass)) {
            return img.parentElement;
        }

        // Determine placeholder type and size
        const isAvatar = img.hasAttribute('data-avatar') ||
                        img.closest('.avatar, .user-avatar, .profile-avatar');
        const size = img.dataset.size || 'md';

        // Create wrapper
        const wrapper = document.createElement('div');
        wrapper.className = isAvatar ?
            `avatar-placeholder avatar-placeholder--${size}` :
            'image-placeholder';

        // Copy dimensions if set - dynamic values from image element
        // eslint-disable-next-line no-restricted-syntax -- dynamic width from image
        if (img.width) wrapper.style.width = `${img.width}px`;
        // eslint-disable-next-line no-restricted-syntax -- dynamic height from image
        if (img.height) wrapper.style.height = `${img.height}px`;

        // Wrap
        img.parentNode.insertBefore(wrapper, img);
        wrapper.appendChild(img);

        return wrapper;
    }

    /**
     * Handle image load success
     */
    function handleImageLoad(img) {
        const wrapper = img.closest(`.${config.placeholderClass}, .image-placeholder`);

        // Add loaded class to image
        img.classList.add(config.loadedClass);

        // Add loaded class to wrapper
        if (wrapper) {
            wrapper.classList.add(config.loadedClass);
        }

        // Remove loading state from parent
        const parent = img.closest('.loading');
        if (parent) {
            parent.classList.remove('loading');
        }
    }

    /**
     * Handle image load error
     */
    function handleImageError(img) {
        const wrapper = img.closest(`.${config.placeholderClass}, .image-placeholder`);
        const name = img.alt || img.dataset.name || '';

        // Add error class
        if (wrapper) {
            wrapper.classList.add(config.errorClass);
            wrapper.classList.remove(config.placeholderClass);
        }

        // Replace with initials if it's an avatar
        if (config.showInitialsOnError && (
            img.hasAttribute('data-avatar') ||
            img.closest('.avatar, .user-avatar, .profile-avatar')
        )) {
            const size = img.dataset.size || 'md';
            const initialsEl = createInitialsAvatar(name, size);

            if (wrapper) {
                // Clear wrapper and add initials
                wrapper.innerHTML = '';
                wrapper.className = ''; // Remove all classes
                wrapper.appendChild(initialsEl);
            } else {
                // Replace image with initials
                img.parentNode.replaceChild(initialsEl, img);
            }
        }
    }

    /**
     * Set up image loading behavior
     */
    function setupImage(img) {
        // Skip if already processed
        if (img.dataset.placeholderInit) return;
        img.dataset.placeholderInit = 'true';

        // Skip if already loaded
        if (img.complete && img.naturalWidth > 0) {
            handleImageLoad(img);
            return;
        }

        // Wrap in placeholder
        wrapImage(img);

        // Set up load handler
        img.addEventListener('load', () => handleImageLoad(img), { once: true });

        // Set up error handler
        img.addEventListener('error', () => handleImageError(img), { once: true });
    }

    /**
     * Force reload an image with placeholder
     */
    function loadImage(img, src) {
        // Remove existing states
        img.classList.remove(config.loadedClass);
        const wrapper = img.closest(`.${config.placeholderClass}, .image-placeholder`);
        if (wrapper) {
            wrapper.classList.remove(config.loadedClass, config.errorClass);
        }

        // Reset init flag
        delete img.dataset.placeholderInit;

        // Set new source if provided
        if (src) {
            img.src = src;
        }

        // Re-setup
        setupImage(img);
    }

    /**
     * Create a placeholder element
     */
    function createPlaceholder(type = 'avatar', size = 'md') {
        const el = document.createElement('div');

        if (type === 'avatar') {
            el.className = `avatar-placeholder avatar-placeholder--${size}`;
        } else if (type === 'image') {
            el.className = 'image-placeholder';
        } else if (type === 'text') {
            el.className = `skeleton-text skeleton-text--${size}`;
        } else if (type === 'button') {
            el.className = 'skeleton-button';
        }

        return el;
    }

    /**
     * Initialize all images on page
     */
    function init() {
        if (!config.autoInit) return;

        const selector = config.selectors.join(', ');
        const images = document.querySelectorAll(selector);

        images.forEach(setupImage);

        // Watch for new images
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType !== 1) return;

                    // Check if node is an image
                    if (node.tagName === 'IMG' && config.selectors.some(s => node.matches(s))) {
                        setupImage(node);
                    }

                    // Check children
                    if (node.querySelectorAll) {
                        node.querySelectorAll(selector).forEach(setupImage);
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        console.log(`[AvatarPlaceholder] Initialized ${images.length} images`);
    }

    /**
     * Create avatar group HTML
     */
    function createAvatarGroup(users, max = 4, size = 'sm') {
        const container = document.createElement('div');
        container.className = 'avatar-group';

        const displayUsers = users.slice(0, max);
        const remaining = users.length - max;

        displayUsers.forEach(user => {
            if (user.avatar) {
                const img = document.createElement('img');
                img.src = user.avatar;
                img.alt = user.name;
                img.className = `avatar-placeholder avatar-placeholder--${size}`;
                img.dataset.avatar = 'true';
                img.dataset.size = size;
                img.dataset.name = user.name;
                container.appendChild(img);
                setupImage(img);
            } else {
                container.appendChild(createInitialsAvatar(user.name, size));
            }
        });

        if (remaining > 0) {
            const more = document.createElement('div');
            more.className = `avatar-group-more avatar-placeholder--${size}`;
            more.textContent = `+${remaining}`;
            container.appendChild(more);
        }

        return container;
    }

    // Public API
    window.AvatarPlaceholder = {
        init: init,
        setup: setupImage,
        load: loadImage,
        initials: createInitialsAvatar,
        placeholder: createPlaceholder,
        avatarGroup: createAvatarGroup,
        getInitials: getInitials,
        getColorFromString: getColorFromString,
        config: (newConfig) => Object.assign(config, newConfig)
    };

    // Auto-initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        setTimeout(init, 50);
    }

})();
