/**
 * Organization Members Page JavaScript
 * Pay modal and form validation
 * WCAG 2.1 AA Compliant
 */

(function() {
    'use strict';

    // Get wallet balance from data attribute or default to 0
    const walletBalanceElement = document.querySelector('[data-wallet-balance]');
    const walletBalance = walletBalanceElement
        ? parseFloat(walletBalanceElement.dataset.walletBalance)
        : 0;

    // Pay Modal Functions
    window.openPayModal = function(userId, name, email, initial) {
        const modal = document.getElementById('payMemberModal');
        if (!modal) return;

        document.getElementById('payRecipientId').value = userId;
        document.getElementById('payRecipientName').textContent = name;
        document.getElementById('payRecipientEmail').textContent = email;
        document.getElementById('payRecipientAvatar').textContent = initial;
        document.getElementById('payAmount').value = '';
        document.getElementById('payDescription').value = '';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';

        // Focus on amount field
        setTimeout(() => {
            const amountField = document.getElementById('payAmount');
            if (amountField) amountField.focus();
        }, 100);
    };

    window.closePayModal = function() {
        const modal = document.getElementById('payMemberModal');
        if (!modal) return;

        modal.classList.remove('active');
        document.body.style.overflow = '';
    };

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        const modal = document.getElementById('payMemberModal');
        if (e.key === 'Escape' && modal && modal.classList.contains('active')) {
            window.closePayModal();
        }
    });

    // Form validation
    const payForm = document.getElementById('payMemberForm');
    if (payForm) {
        payForm.addEventListener('submit', function(e) {
            const amountField = document.getElementById('payAmount');
            if (!amountField) return;

            const amount = parseFloat(amountField.value);

            if (isNaN(amount) || amount <= 0) {
                e.preventDefault();
                alert('Please enter a valid amount');
                return;
            }

            if (amount > walletBalance) {
                e.preventDefault();
                alert('Amount exceeds organization wallet balance (' + walletBalance.toFixed(2) + ' credits)');
                return;
            }
        });
    }
})();
