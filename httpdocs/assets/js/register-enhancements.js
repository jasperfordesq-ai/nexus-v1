// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Progressive enhancement for the GOV.UK alpha registration form.
// The form fully works without this script (server-side validation handles
// every rule). This adds nicer in-browser feedback that mirrors the React
// frontend's interactive behaviour:
//
//   1. Show/hide organisation_name based on profile_type radio.
//   2. Live password-confirmation match indicator.
//   3. Google Places autocomplete on the location field — populates the
//      hidden latitude/longitude inputs when a place is selected.
//
// The Google Places init is registered as `__nexusRegisterPlacesInit` so the
// Maps JS API can `callback=` into it after the SDK loads.

(function () {
    // ─── Profile type ⇄ organisation name conditional ─────────────────────
    var radios = document.querySelectorAll('input[name="profile_type"]');
    var orgConditional = document.getElementById('conditional-profile-organisation');
    var orgNameInput = document.getElementById('organization_name');
    function syncOrgVisibility() {
        var checked = document.querySelector('input[name="profile_type"]:checked');
        var isOrg = checked && checked.value === 'organisation';
        if (orgConditional) {
            orgConditional.classList.toggle('govuk-radios__conditional--hidden', !isOrg);
        }
        if (orgNameInput) {
            if (isOrg) {
                orgNameInput.setAttribute('required', 'required');
            } else {
                orgNameInput.removeAttribute('required');
            }
        }
    }
    radios.forEach(function (r) { r.addEventListener('change', syncOrgVisibility); });
    syncOrgVisibility();

    // ─── Password confirmation match indicator ────────────────────────────
    var pw = document.getElementById('password');
    var pwConfirm = document.getElementById('password_confirmation');
    if (pw && pwConfirm) {
        var group = pwConfirm.closest('.govuk-form-group');
        var msg = document.createElement('p');
        msg.className = 'govuk-body-s govuk-!-margin-top-2';
        msg.setAttribute('aria-live', 'polite');
        if (group) group.appendChild(msg);

        function updateMatch() {
            if (pwConfirm.value.length === 0) {
                msg.textContent = '';
                msg.className = 'govuk-body-s govuk-!-margin-top-2';
                return;
            }
            var match = pw.value === pwConfirm.value;
            msg.textContent = match
                ? 'Passwords match.'
                : 'Passwords do not match.';
            msg.className = 'govuk-body-s govuk-!-margin-top-2 ' +
                (match ? 'app-success-message' : 'govuk-error-message');
        }
        pwConfirm.addEventListener('input', updateMatch);
        pw.addEventListener('input', updateMatch);
    }

    // ─── Invite code: uppercase as user types ─────────────────────────────
    var invite = document.getElementById('invite_code');
    if (invite) {
        invite.addEventListener('input', function () {
            var pos = invite.selectionStart;
            invite.value = invite.value.toUpperCase();
            try { invite.setSelectionRange(pos, pos); } catch (e) {}
        });
    }
})();

// ─── Google Places autocomplete (loaded async via Maps JS API) ────────────
// Defined globally so the Maps JS API can call back into it.
window.__nexusRegisterPlacesInit = function () {
    var location = document.getElementById('location');
    var lat = document.getElementById('latitude');
    var lng = document.getElementById('longitude');
    if (!location || !lat || !lng) return;
    if (!window.google || !window.google.maps || !window.google.maps.places) return;

    var ac = new window.google.maps.places.Autocomplete(location, {
        fields: ['formatted_address', 'geometry'],
        types: ['geocode'],
    });
    ac.addListener('place_changed', function () {
        var place = ac.getPlace();
        if (place && place.formatted_address) {
            location.value = place.formatted_address;
        }
        if (place && place.geometry && place.geometry.location) {
            lat.value = String(place.geometry.location.lat());
            lng.value = String(place.geometry.location.lng());
        } else {
            // User typed without selecting a suggestion — clear lat/lng so
            // we don't send stale coordinates for a different address.
            lat.value = '';
            lng.value = '';
        }
    });

    // If the user edits the field after a selection, drop the cached lat/lng
    // until they pick a new suggestion.
    location.addEventListener('input', function () {
        lat.value = '';
        lng.value = '';
    });
};
