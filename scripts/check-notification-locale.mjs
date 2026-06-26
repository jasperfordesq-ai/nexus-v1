// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * check-notification-locale.mjs — recipient-locale guard.
 *
 * Enforces the AGENTS.md rule "EMAIL & NOTIFICATION LOCALE": every async
 * notification sender (Service / Listener / Job) that renders a user-facing
 * string from the emails.* / notifications.* / svc_notifications.* namespaces
 * MUST wrap the render+send in App\I18n\LocaleContext::withLocale($recipient, …)
 * so the message goes out in the RECIPIENT's preferred_language — not the queue
 * worker's or HTTP caller's locale. Without the wrap, mail dispatched from
 * cron/queues silently goes out in English to non-English members.
 *
 * Scope: app/Services, app/Listeners, app/Jobs — the async / cross-recipient
 * contexts where __() resolves against the wrong locale. Controllers run in the
 * acting user's request locale, and HTML renderers (app/Core/EmailTemplate*)
 * are called INSIDE an already-wrapped sender, so both are intentionally out of
 * scope. A file is only flagged if it both references a notification namespace
 * AND performs a send action AND does not reference LocaleContext.
 *
 * Mechanism regression test: tests/Laravel/Feature/I18n/EmailLocaleIntegrationTest.php
 */
import fs from 'node:fs';
import path from 'node:path';

const DIRS = ['app/Services', 'app/Listeners', 'app/Jobs'];
const NAMESPACE = /__\(\s*['"](emails|notifications|svc_notifications)\./;
const SEND = /send\(|Mail::|Notification::|->notify\(|dispatchNotification|NotificationDispatcher|mailer/;
const WRAP = /LocaleContext/;

// Files verified to legitimately NOT need the wrap. Add new entries WITH a reason.
const ALLOWLIST = new Set([
  // (none currently — all 104 senders in scope wrap correctly)
]);

function walk(dir, out) {
  if (!fs.existsSync(dir)) return;
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name).split(path.sep).join('/');
    if (e.isDirectory()) walk(p, out);
    else if (p.endsWith('.php')) out.push(p);
  }
}

const files = [];
for (const d of DIRS) walk(d, files);

const offenders = [];
let scanned = 0;
for (const f of files) {
  const txt = fs.readFileSync(f, 'utf8');
  if (!NAMESPACE.test(txt)) continue;
  scanned++;
  if (WRAP.test(txt)) continue;            // wrapped → ok
  if (!SEND.test(txt)) continue;           // references a string but does not send → ok
  if (ALLOWLIST.has(f)) continue;
  offenders.push(f);
}

if (offenders.length > 0) {
  console.error('❌ Notification-locale guard FAILED.\n');
  console.error('These async senders render a user-facing notification string but do NOT wrap in LocaleContext::withLocale($recipient, …):\n');
  for (const o of offenders) console.error('   - ' + o);
  console.error('\nWhy it matters: without the wrap the message renders in the queue worker / HTTP caller locale,');
  console.error('so non-English recipients silently receive English.');
  console.error('\nFix: wrap the render+send block:');
  console.error('    use App\\I18n\\LocaleContext;');
  console.error('    LocaleContext::withLocale($recipient, function () use (...) { /* __() + send */ });');
  console.error('Ensure the recipient SELECT includes preferred_language. See AGENTS.md "EMAIL & NOTIFICATION LOCALE".');
  console.error('If a file legitimately does not need it, add it to ALLOWLIST in scripts/check-notification-locale.mjs with a reason.');
  process.exit(1);
}

console.log(`✅ Notification-locale guard: ${scanned} notification-rendering sender(s) in Services/Listeners/Jobs all wrap the recipient locale.`);
process.exit(0);
