// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { initAll } from 'govuk-frontend';
import './app.scss';

initAll();

document.querySelectorAll<HTMLFormElement>('[data-alpha-auto-submit]').forEach((form) => {
  form.querySelectorAll<HTMLSelectElement>('select').forEach((select) => {
    select.addEventListener('change', () => form.requestSubmit());
  });
});

// Escape user-supplied text before inserting it into an autocomplete suggestion
// (the suggestion template is rendered as innerHTML by accessible-autocomplete).
function escapeHtml(value: string): string {
  return value.replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c] as string
  ));
}

// Progressive enhancement: turn the wallet recipient search into an accessible
// autocomplete (the official GOV.UK component). The no-JS path — a plain search
// input that reloads with server-rendered results — stays the primary, fully
// working experience; we only remove it once the enhancement has initialised.
const recipientContainer = document.querySelector<HTMLElement>('[data-alpha-recipient-autocomplete]');
if (recipientContainer) {
  const source = recipientContainer.dataset.source ?? '';
  const target = recipientContainer.dataset.target ?? '';
  const noJsInput = document.getElementById('recipient_q');
  const noJsSubmit = document.querySelector<HTMLElement>('[data-alpha-recipient-submit]');

  if (source && target) {
    // Dynamic import keeps the autocomplete bundle off every other page.
    Promise.all([
      import('accessible-autocomplete'),
      import('accessible-autocomplete/dist/accessible-autocomplete.min.css'),
    ])
      .then(([mod]) => {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const accessibleAutocomplete: any = (mod as any).default ?? mod;

        accessibleAutocomplete({
          element: recipientContainer,
          id: 'recipient_q',
          minLength: 2,
          displayMenu: 'overlay',
          confirmOnBlur: false,
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          source: async (query: string, populateResults: (results: any[]) => void) => {
            try {
              const res = await fetch(`${source}?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
              });
              if (!res.ok) {
                populateResults([]);
                return;
              }
              const data = await res.json();
              populateResults(Array.isArray(data?.results) ? data.results : []);
            } catch {
              populateResults([]);
            }
          },
          templates: {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            inputValue: (r: any) => (r && r.name ? String(r.name) : ''),
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            suggestion: (r: any) => {
              if (!r) return '';
              const name = escapeHtml(String(r.name ?? ''));
              const meta = [r.location, r.since].filter(Boolean).map((x: string) => escapeHtml(String(x))).join(' · ');
              return meta ? `${name} <span class="nexus-alpha-ac-meta">— ${meta}</span>` : name;
            },
          },
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          onConfirm: (r: any) => {
            if (r && r.id) {
              window.location.href = `${target}?recipient_id=${encodeURIComponent(String(r.id))}#transfer`;
            }
          },
        });

        // The enhancement is live — drop the no-JS input + button so there is no
        // duplicate id and the autocomplete is the single recipient control.
        noJsInput?.remove();
        noJsSubmit?.remove();
        recipientContainer.querySelector('input')?.setAttribute('aria-describedby', 'recipient-q-hint');
      })
      .catch(() => {
        // Enhancement failed to load — leave the no-JS search exactly as it was.
      });
  }
}
