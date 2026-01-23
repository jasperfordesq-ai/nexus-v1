/**
 * Organization Transfer Requests Functions
 * Filter and manage transfer requests
 * CivicOne Theme
 */

(function() {
    'use strict';

    window.filterRequests = function(status) {
        const tabs = document.querySelectorAll('.filter-tab');
        const rows = document.querySelectorAll('#requestsBody tr');

        tabs.forEach(tab => tab.classList.remove('active'));
        event.target.classList.add('active');

        rows.forEach(row => {
            if (status === 'all' || row.dataset.status === status) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });
    };

    window.promptRejectReason = function(form, requestId) {
        const reason = prompt('Please enter a reason for rejection (optional):');
        if (reason !== null) {
            document.getElementById('rejectReason_' + requestId).value = reason;
            return true;
        }
        return false;
    };
})();
