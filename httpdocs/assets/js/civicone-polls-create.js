/**
 * CivicOne Polls Create - Poll Creation Form
 * WCAG 2.1 AA Compliant
 * Dynamic option adding
 */

(function() {
    'use strict';

    window.addOption = function() {
        const div = document.createElement('input');
        div.type = 'text';
        div.name = 'options[]';
        div.className = 'civic-input civic-poll-option-input';
        div.placeholder = 'New Option';
        document.getElementById('poll-options').appendChild(div);
    };

})();
