/**
 * CivicOne Achievements Module JavaScript
 * Consolidated from achievement view files
 * 2026-01-19
 */

// ============================================
// BADGE MODAL FUNCTIONS
// ============================================

function openBadgeModal(element) {
    const modal = document.getElementById('badgeModal');
    if (!modal) return;

    const header = document.getElementById('badgeModalHeader');
    const icon = document.getElementById('badgeModalIcon');
    const name = document.getElementById('badgeModalName');
    const desc = document.getElementById('badgeModalDesc');
    const date = document.getElementById('badgeModalDate');
    const rarityTag = document.getElementById('badgeModalRarity');
    const rarityText = document.getElementById('badgeModalRarityText');
    const rarityBar = document.getElementById('badgeModalRarityBar');

    // Get data from clicked element
    const badgeName = element.dataset.badgeName || 'Badge';
    const badgeIcon = element.dataset.badgeIcon || '🏆';
    const badgeDesc = element.dataset.badgeDesc || 'earning this achievement';
    const badgeDate = element.dataset.badgeDate || 'Unknown';
    const badgeRarity = element.dataset.badgeRarity || 'Common';
    const badgePercent = parseFloat(element.dataset.badgePercent) || 100;

    // Populate modal
    if (icon) icon.textContent = badgeIcon;
    if (name) name.textContent = badgeName;
    if (desc) desc.textContent = badgeDesc.charAt(0).toUpperCase() + badgeDesc.slice(1);
    if (date) date.textContent = badgeDate;

    // Set rarity tag
    if (rarityTag) {
        const rarityLower = badgeRarity.toLowerCase();
        rarityTag.className = 'badge-rarity-tag ' + rarityLower;
        rarityTag.innerHTML = getRarityIcon(rarityLower) + ' ' + badgeRarity;
    }

    // Set rarity text
    if (rarityText) {
        if (badgePercent <= 1) {
            rarityText.textContent = `Only ${badgePercent.toFixed(1)}% of members have this badge`;
        } else if (badgePercent <= 5) {
            rarityText.textContent = `Top ${badgePercent.toFixed(1)}% of members`;
        } else if (badgePercent <= 15) {
            rarityText.textContent = `${badgePercent.toFixed(1)}% of members have earned this`;
        } else if (badgePercent <= 40) {
            rarityText.textContent = `Earned by ${badgePercent.toFixed(0)}% of active members`;
        } else {
            rarityText.textContent = `A common achievement (${badgePercent.toFixed(0)}% have it)`;
        }
    }

    // Set rarity bar
    if (rarityBar) {
        const rarityLower = badgeRarity.toLowerCase();
        rarityBar.className = 'badge-rarity-fill ' + rarityLower;
        rarityBar.style.width = '0%';
    }

    // Show modal and hide navbar
    modal.classList.add('visible');
    document.body.style.overflow = 'hidden';

    // Hide navbar and bottom tab bar while drawer is open
    const navbar = document.querySelector('.nexus-navbar');
    if (navbar) {
        navbar.style.display = 'none';
    }
    const mobileTabBar = document.querySelector('.mobile-tab-bar');
    if (mobileTabBar) {
        mobileTabBar.style.display = 'none';
    }

    // Animate rarity bar
    if (rarityBar) {
        setTimeout(() => {
            // Invert percentage for visual (rarer = less fill = more impressive)
            const fillWidth = Math.max(5, 100 - badgePercent);
            rarityBar.style.width = fillWidth + '%';
        }, 100);
    }

    // Haptic feedback on mobile
    if (navigator.vibrate) {
        navigator.vibrate(10);
    }
}

function getRarityIcon(rarity) {
    switch (rarity) {
        case 'legendary':
            return '<i class="fa-solid fa-crown"></i>';
        case 'epic':
            return '<i class="fa-solid fa-gem"></i>';
        case 'rare':
            return '<i class="fa-solid fa-star"></i>';
        case 'uncommon':
            return '<i class="fa-solid fa-circle-up"></i>';
        default:
            return '<i class="fa-solid fa-circle"></i>';
    }
}

function closeBadgeModal() {
    const modal = document.getElementById('badgeModal');
    if (!modal) return;

    const content = modal.querySelector('.badge-modal-content');

    // Restore navbar and bottom tab bar visibility
    const navbar = document.querySelector('.nexus-navbar');
    if (navbar) {
        navbar.style.display = '';
    }
    const mobileTabBar = document.querySelector('.mobile-tab-bar');
    if (mobileTabBar) {
        mobileTabBar.style.display = '';
    }

    // On mobile, animate drawer closing
    if (window.innerWidth <= 640 && content) {
        content.classList.add('closing');
        setTimeout(() => {
            modal.classList.remove('visible');
            content.classList.remove('closing');
            document.body.style.overflow = '';
        }, 200);
    } else {
        modal.classList.remove('visible');
        document.body.style.overflow = '';
    }
}

function closeBadgeModalOnBackdrop(event) {
    if (event.target === event.currentTarget) {
        closeBadgeModal();
    }
}

// ============================================
// BADGE SHOWCASE FUNCTIONS (badges.php)
// ============================================

function toggleShowcase(btn, key, name, icon) {
    const form = document.getElementById('showcase-form');
    if (!form) return;

    const slots = form.querySelectorAll('.showcase-badge-slot');
    const saveBtn = document.getElementById('save-showcase');

    // Check if already pinned
    const existingInput = form.querySelector(`input[value="${key}"]`);
    if (existingInput) {
        // Remove from showcase
        const slot = existingInput.closest('.showcase-badge-slot');
        slot.classList.remove('filled');
        slot.classList.add('empty');
        slot.innerHTML = '<i class="fa-solid fa-plus"></i><span>Empty Slot</span>';
        slot.removeAttribute('data-key');
        btn.classList.remove('pinned');
        btn.innerHTML = '<i class="fa-regular fa-star"></i> Pin';
    } else {
        // Find empty slot
        let emptySlot = null;
        slots.forEach(slot => {
            if (!slot.classList.contains('filled') && !emptySlot) {
                emptySlot = slot;
            }
        });

        if (!emptySlot) {
            alert('All 3 showcase slots are full. Remove one first.');
            return;
        }

        // Add to showcase
        emptySlot.classList.remove('empty');
        emptySlot.classList.add('filled');
        emptySlot.setAttribute('data-key', key);
        emptySlot.innerHTML = `
            <input type="hidden" name="badge_keys[]" value="${key}">
            <span class="badge-icon">${icon}</span>
            <span class="badge-name">${name}</span>
        `;
        btn.classList.add('pinned');
        btn.innerHTML = '<i class="fa-solid fa-star"></i> Pinned';
    }

    if (saveBtn) {
        saveBtn.style.display = 'inline-flex';
    }
}

// ============================================
// CONFETTI EFFECT (badges.php)
// ============================================

function createConfetti() {
    const container = document.getElementById('confetti-container');
    if (!container) return;

    const colors = ['#a855f7', '#6366f1', '#f59e0b', '#10b981', '#ef4444', '#3b82f6'];

    for (let i = 0; i < 100; i++) {
        setTimeout(() => {
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            confetti.style.left = Math.random() * 100 + 'vw';
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.animationDelay = Math.random() * 0.5 + 's';
            confetti.style.borderRadius = Math.random() > 0.5 ? '50%' : '0';
            container.appendChild(confetti);

            setTimeout(() => confetti.remove(), 3500);
        }, i * 20);
    }
}

// ============================================
// SHOP FUNCTIONS (shop.php)
// ============================================

let currentItemId = null;

function confirmPurchase(itemId, itemName, itemCost, itemIcon) {
    currentItemId = itemId;
    const modalIcon = document.getElementById('modalIcon');
    const modalItemName = document.getElementById('modalItemName');
    const modalItemCost = document.getElementById('modalItemCost');
    const purchaseModal = document.getElementById('purchaseModal');

    if (modalIcon) modalIcon.innerHTML = itemIcon || '<i class="fa-solid fa-gift"></i>';
    if (modalItemName) modalItemName.textContent = itemName;
    if (modalItemCost) modalItemCost.textContent = itemCost.toLocaleString();
    if (purchaseModal) purchaseModal.classList.add('active');
}

function closeModal() {
    const purchaseModal = document.getElementById('purchaseModal');
    if (purchaseModal) purchaseModal.classList.remove('active');
    currentItemId = null;
}

function closeSuccessModal() {
    const successModal = document.getElementById('successModal');
    if (successModal) successModal.classList.remove('active');
    location.reload(); // Refresh to update XP and ownership status
}

function initShopPurchase(basePath) {
    const confirmBtn = document.getElementById('confirmBtn');
    if (!confirmBtn) return;

    confirmBtn.addEventListener('click', async function () {
        if (!currentItemId) return;

        this.disabled = true;
        this.textContent = 'Processing...';

        try {
            const formData = new FormData();
            formData.append('item_id', currentItemId);

            const response = await fetch(basePath + '/api/shop/purchase', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            closeModal();

            const successIcon = document.getElementById('successIcon');
            const successTitle = document.getElementById('successTitle');
            const successMessage = document.getElementById('successMessage');
            const successModal = document.getElementById('successModal');

            if (result.success) {
                if (successIcon) successIcon.innerHTML = '<i class="fa-solid fa-check-circle" style="color: #10b981;"></i>';
                if (successTitle) successTitle.textContent = 'Purchase Complete!';
                if (successMessage) successMessage.textContent = result.message || 'Your item has been added to your account.';
                if (successModal) successModal.classList.add('active');

                // Trigger confetti if available
                if (typeof showConfetti === 'function') {
                    showConfetti();
                }
            } else {
                if (successIcon) successIcon.innerHTML = '<i class="fa-solid fa-times-circle" style="color: #ef4444;"></i>';
                if (successTitle) successTitle.textContent = 'Purchase Failed';
                if (successMessage) successMessage.textContent = result.error || 'Something went wrong. Please try again.';
                if (successModal) successModal.classList.add('active');
            }
        } catch (error) {
            closeModal();
            const successIcon = document.getElementById('successIcon');
            const successTitle = document.getElementById('successTitle');
            const successMessage = document.getElementById('successMessage');
            const successModal = document.getElementById('successModal');

            if (successIcon) successIcon.innerHTML = '<i class="fa-solid fa-times-circle" style="color: #ef4444;"></i>';
            if (successTitle) successTitle.textContent = 'Error';
            if (successMessage) successMessage.textContent = 'Failed to process purchase. Please try again.';
            if (successModal) successModal.classList.add('active');
        }

        this.disabled = false;
        this.textContent = 'Confirm';
    });
}

function initShopCategories() {
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const category = this.dataset.category;
            document.querySelectorAll('.shop-item').forEach(item => {
                if (category === 'all' || item.dataset.category === category) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
}

// ============================================
// GLOBAL EVENT LISTENERS
// ============================================

document.addEventListener('DOMContentLoaded', function () {
    // Close badge modal on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const badgeModal = document.getElementById('badgeModal');
            if (badgeModal && badgeModal.classList.contains('visible')) {
                closeBadgeModal();
            }
        }
    });

    // Handle keyboard activation for badges
    document.querySelectorAll('.badge-earned').forEach(badge => {
        badge.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openBadgeModal(this);
            }
        });
    });

    // Close modals on outside click
    document.querySelectorAll('.purchase-modal').forEach(modal => {
        modal.addEventListener('click', function (e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });

    // Initialize shop category filtering if on shop page
    if (document.querySelector('.shop-categories')) {
        initShopCategories();
    }

    // Check if confetti should be triggered (badges page with new_badge param)
    if (document.getElementById('confetti-container')) {
        createConfetti();
    }
});
