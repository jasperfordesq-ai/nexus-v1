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
