/**
 * Menu Builder Drag-and-Drop Functionality
 * Implements sortable menu items with visual feedback
 */

class MenuBuilderDragDrop {
    constructor(listSelector, options = {}) {
        this.list = document.querySelector(listSelector);
        this.menuId = options.menuId;
        this.basePath = options.basePath;
        this.csrfToken = options.csrfToken;
        this.onReorder = options.onReorder || null;

        if (!this.list) {
            console.warn('MenuBuilderDragDrop: List element not found');
            return;
        }

        this.draggedItem = null;
        this.placeholder = null;
        this.items = [];

        this.init();
    }

    init() {
        this.setupDragHandlers();
        this.createPlaceholder();
    }

    createPlaceholder() {
        this.placeholder = document.createElement('div');
        this.placeholder.className = 'menu-item-placeholder';
        this.placeholder.style.cssText = `
            height: 60px;
            border: 2px dashed rgba(99, 102, 241, 0.5);
            background: rgba(99, 102, 241, 0.1);
            border-radius: 0.5rem;
            margin: 0.25rem 0;
            transition: all 0.2s ease;
        `;
    }

    setupDragHandlers() {
        // Get all draggable items
        this.updateItemsList();

        this.items.forEach(item => {
            item.setAttribute('draggable', 'true');

            // Drag start
            item.addEventListener('dragstart', (e) => this.handleDragStart(e, item));

            // Drag over (for drop targets)
            item.addEventListener('dragover', (e) => this.handleDragOver(e, item));

            // Drag enter
            item.addEventListener('dragenter', (e) => this.handleDragEnter(e, item));

            // Drag leave
            item.addEventListener('dragleave', (e) => this.handleDragLeave(e, item));

            // Drop
            item.addEventListener('drop', (e) => this.handleDrop(e, item));

            // Drag end
            item.addEventListener('dragend', (e) => this.handleDragEnd(e, item));
        });
    }

    updateItemsList() {
        this.items = Array.from(this.list.querySelectorAll('.menu-item-row'));
    }

    handleDragStart(e, item) {
        this.draggedItem = item;
        item.classList.add('dragging');
        item.style.opacity = '0.5';

        // Set drag data
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', item.innerHTML);
    }

    handleDragOver(e, item) {
        if (e.preventDefault) {
            e.preventDefault();
        }

        e.dataTransfer.dropEffect = 'move';

        // Don't do anything if dragging over self
        if (item === this.draggedItem) {
            return false;
        }

        // Get depth of dragged item and current item
        const draggedDepth = parseInt(this.draggedItem.dataset.depth || 0);
        const targetDepth = parseInt(item.dataset.depth || 0);

        // Insert placeholder
        const rect = item.getBoundingClientRect();
        const midpoint = rect.top + rect.height / 2;

        if (e.clientY < midpoint) {
            // Insert before
            item.parentNode.insertBefore(this.placeholder, item);
        } else {
            // Insert after
            if (item.nextSibling) {
                item.parentNode.insertBefore(this.placeholder, item.nextSibling);
            } else {
                item.parentNode.appendChild(this.placeholder);
            }
        }

        return false;
    }

    handleDragEnter(e, item) {
        if (item !== this.draggedItem) {
            item.classList.add('drag-over');
        }
    }

    handleDragLeave(e, item) {
        item.classList.remove('drag-over');
    }

    handleDrop(e, item) {
        if (e.stopPropagation) {
            e.stopPropagation();
        }

        // Don't drop on self
        if (this.draggedItem === item) {
            return false;
        }

        // Remove placeholder and insert dragged item
        if (this.placeholder.parentNode) {
            this.placeholder.parentNode.insertBefore(this.draggedItem, this.placeholder);
            this.placeholder.parentNode.removeChild(this.placeholder);
        }

        return false;
    }

    handleDragEnd(e, item) {
        item.style.opacity = '1';
        item.classList.remove('dragging');

        // Remove drag-over class from all items
        this.items.forEach(i => i.classList.remove('drag-over'));

        // Remove placeholder if still in DOM
        if (this.placeholder.parentNode) {
            this.placeholder.parentNode.removeChild(this.placeholder);
        }

        // Get new order
        this.updateItemsList();
        const newOrder = this.getNewOrder();

        // Save to server
        this.saveOrder(newOrder);

        // Call callback if provided
        if (this.onReorder && typeof this.onReorder === 'function') {
            this.onReorder(newOrder);
        }
    }

    getNewOrder() {
        this.updateItemsList();

        return this.items.map((item, index) => ({
            id: parseInt(item.dataset.itemId),
            sort_order: index,
            depth: parseInt(item.dataset.depth || 0)
        }));
    }

    saveOrder(orderData) {
        if (!this.menuId || !this.basePath || !this.csrfToken) {
            console.warn('MenuBuilderDragDrop: Missing required config for saving');
            return;
        }

        // Show loading indicator
        const loadingIndicator = this.showLoadingIndicator();

        // Send as form data to match backend expectations
        const formData = new FormData();
        formData.append('csrf_token', this.csrfToken);
        formData.append('items', JSON.stringify(orderData));

        fetch(`${this.basePath}/admin/menus/items/reorder`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showSuccessMessage();

                // Optional: refresh preview without full page reload
                if (window.refreshMenuPreview) {
                    window.refreshMenuPreview();
                }
            } else {
                this.showErrorMessage(data.error || 'Failed to save order');
                // Reload page to restore original order
                setTimeout(() => location.reload(), 2000);
            }
        })
        .catch(error => {
            console.error('Error saving order:', error);
            this.showErrorMessage('Network error. Reloading...');
            setTimeout(() => location.reload(), 2000);
        })
        .finally(() => {
            this.hideLoadingIndicator(loadingIndicator);
        });
    }

    showLoadingIndicator() {
        const indicator = document.createElement('div');
        indicator.className = 'menu-save-indicator';
        indicator.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        indicator.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(99, 102, 241, 0.9);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 10000;
            font-weight: 600;
            backdrop-filter: blur(10px);
        `;
        document.body.appendChild(indicator);
        return indicator;
    }

    hideLoadingIndicator(indicator) {
        if (indicator && indicator.parentNode) {
            indicator.style.opacity = '0';
            setTimeout(() => indicator.remove(), 300);
        }
    }

    showSuccessMessage() {
        const message = document.createElement('div');
        message.className = 'menu-save-success';
        message.innerHTML = '<i class="fa-solid fa-check-circle"></i> Order saved';
        message.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(34, 197, 94, 0.9);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 10000;
            font-weight: 600;
            backdrop-filter: blur(10px);
            animation: slideInRight 0.3s ease;
        `;
        document.body.appendChild(message);

        setTimeout(() => {
            message.style.opacity = '0';
            message.style.transform = 'translateX(100%)';
            message.style.transition = 'all 0.3s ease';
            setTimeout(() => message.remove(), 300);
        }, 2000);
    }

    showErrorMessage(errorText) {
        const message = document.createElement('div');
        message.className = 'menu-save-error';
        // Sanitize errorText to prevent XSS
        const sanitizedText = String(errorText).replace(/[<>"'&]/g, char => ({
            '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '&': '&amp;'
        })[char]);
        message.innerHTML = `<i class="fa-solid fa-exclamation-circle"></i> ${sanitizedText}`;
        message.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 10000;
            font-weight: 600;
            backdrop-filter: blur(10px);
        `;
        document.body.appendChild(message);

        setTimeout(() => {
            message.style.opacity = '0';
            message.style.transition = 'opacity 0.3s ease';
            setTimeout(() => message.remove(), 300);
        }, 3000);
    }

    destroy() {
        this.items.forEach(item => {
            item.removeAttribute('draggable');
            item.classList.remove('dragging', 'drag-over');
        });
    }

    refresh() {
        this.destroy();
        this.init();
    }
}

// Add animation keyframes
if (!document.getElementById('menu-drag-drop-animations')) {
    const style = document.createElement('style');
    style.id = 'menu-drag-drop-animations';
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .menu-item-row.dragging {
            opacity: 0.5 !important;
            cursor: grabbing !important;
        }

        .menu-item-row.drag-over {
            background: rgba(99, 102, 241, 0.1) !important;
            border-color: rgba(99, 102, 241, 0.5) !important;
        }
    `;
    document.head.appendChild(style);
}

// Export for global use
window.MenuBuilderDragDrop = MenuBuilderDragDrop;
