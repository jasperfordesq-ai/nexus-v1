<?php
/**
 * Organization UI Components
 * Shared components for modals, loaders, toasts, and form validation
 * Include this file once in your layout or page
 */
?>

<!-- Organization UI Components CSS -->
<link rel="stylesheet" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/css/purged/civicone-org-ui-components.min.css">

<!-- Toast Container -->
<div class="org-toast-container" id="orgToastContainer" role="alert" aria-live="polite"></div>

<!-- Modal Container -->
<div class="org-modal-overlay" id="orgModalOverlay" role="dialog" aria-modal="true" aria-labelledby="orgModalTitle">
    <div class="org-modal">
        <div class="org-modal-header">
            <div class="org-modal-icon" id="orgModalIcon">
                <i class="fa-solid fa-question"></i>
            </div>
            <h3 class="org-modal-title" id="orgModalTitle">Confirm Action</h3>
        </div>
        <div class="org-modal-body">
            <p class="org-modal-text" id="orgModalText">Are you sure you want to proceed?</p>
            <input type="text" class="org-modal-input hidden" id="orgModalInput" placeholder="">
        </div>
        <div class="org-modal-footer">
            <button type="button" class="org-modal-btn cancel" id="orgModalCancel">Cancel</button>
            <button type="button" class="org-modal-btn confirm" id="orgModalConfirm">Confirm</button>
        </div>
    </div>
</div>

<!-- Organization UI Components JavaScript -->
<script src="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/js/civicone-org-ui-components.min.js" defer></script>
