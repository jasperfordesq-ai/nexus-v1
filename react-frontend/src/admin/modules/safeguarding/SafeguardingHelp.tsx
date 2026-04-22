// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SafeguardingHelp — collapsible guidance panel for the Safeguarding dashboard.
 *
 * Rendered at the bottom of SafeguardingDashboard. Focused on the member
 * protection flow — how members are flagged, what activates, the adult-autonomy
 * principle, and audit access.
 *
 * Companion to BrokerControlsHelp, which covers the broader operational flow
 * under /admin/broker-controls. Keep the two synchronised if the underlying
 * system behaviour changes.
 */

import { Card, CardBody, CardHeader, Accordion, AccordionItem, Divider, Chip } from '@heroui/react';
import BookOpen from 'lucide-react/icons/book-open';
import Shield from 'lucide-react/icons/shield';
import Flag from 'lucide-react/icons/flag';
import Zap from 'lucide-react/icons/zap';
import UserCheck from 'lucide-react/icons/user-check';
import Users from 'lucide-react/icons/users';
import Scale from 'lucide-react/icons/scale';
import Clock from 'lucide-react/icons/clock';
import FileText from 'lucide-react/icons/file-text';
import Eye from 'lucide-react/icons/eye';

export function SafeguardingHelp() {
  return (
    <section className="mt-10">
      <Card shadow="sm" className="border border-default-200">
        <CardHeader className="flex items-center gap-3 pb-2">
          <div className="p-2 rounded-lg bg-primary/10">
            <BookOpen className="w-5 h-5 text-primary" />
          </div>
          <div>
            <h2 className="text-lg font-semibold text-foreground">
              How safeguarding works here
            </h2>
            <p className="text-xs text-default-500">
              A guide to the flows you see on this dashboard — flagging, activations,
              member autonomy, audit. Expand any section for detail.
            </p>
          </div>
        </CardHeader>
        <Divider />
        <CardBody className="pt-4">
          <Accordion variant="splitted" selectionMode="multiple">
            {/* ─────────────────────────────────────────────────────────────── */}
            <AccordionItem
              key="about"
              aria-label="About this dashboard"
              title={
                <div className="flex items-center gap-2">
                  <Shield className="w-4 h-4 text-primary" />
                  <span className="font-medium">About this dashboard</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <p>
                  The safeguarding dashboard is your view of every member the system is actively
                  protecting. It surfaces:
                </p>
                <ul className="list-disc pl-5 space-y-1">
                  <li><strong>Flagged messages</strong> — copies of messages sent or received by monitored members.</li>
                  <li><strong>Guardian assignments</strong> — relationships where a coordinator mediates contact for a ward.</li>
                  <li><strong>Member preferences</strong> — which safeguarding options each flagged member has selected.</li>
                </ul>
                <p>
                  The numbers at the top are live — the red Safeguarding item in the sidebar shows
                  the count of unreviewed critical flags so you can spot when attention is needed
                  without opening the page.
                </p>
              </div>
            </AccordionItem>

            {/* ─────────────────────────────────────────────────────────────── */}
            <AccordionItem
              key="flagging"
              aria-label="How members get flagged"
              title={
                <div className="flex items-center gap-2">
                  <Flag className="w-4 h-4 text-danger" />
                  <span className="font-medium">How members get flagged</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <p>
                  Flags are never applied silently behind the member's back. There are two routes:
                </p>
                <div className="p-3 rounded-lg bg-primary/5 border border-primary/20">
                  <p className="font-medium text-foreground mb-1">
                    <Chip size="sm" color="primary" variant="flat" className="mr-2">
                      Most common
                    </Chip>
                    Self-identification during onboarding
                  </p>
                  <p>
                    The member ticked one or more options on the safeguarding step during sign-up
                    (e.g. <em>"I consider myself a vulnerable adult"</em>, or
                    <em> "I prefer only to interact with vetted members"</em>). The system reads the
                    triggers attached to each selected option and OR-merges them into a single
                    set of protections written to <code>user_messaging_restrictions</code>.
                  </p>
                  <p className="italic mt-2">
                    Because these are self-chosen, the member can revoke any of them from
                    <em> Settings → Safeguarding</em> at any time — without needing to ask you.
                  </p>
                </div>
                <div className="p-3 rounded-lg bg-warning/5 border border-warning/20">
                  <p className="font-medium text-foreground mb-1">
                    <Chip size="sm" color="warning" variant="flat" className="mr-2">
                      Admin-initiated
                    </Chip>
                    Manual monitoring
                  </p>
                  <p>
                    You open <em>User Monitoring</em> in Broker Controls and place a member under
                    oversight with a reason and optional expiry date. Used for compliance reviews,
                    complaint investigations, or probationary periods after an incident.
                  </p>
                </div>
              </div>
            </AccordionItem>

            {/* ─────────────────────────────────────────────────────────────── */}
            <AccordionItem
              key="triggers"
              aria-label="What each trigger activates"
              title={
                <div className="flex items-center gap-2">
                  <Zap className="w-4 h-4 text-warning" />
                  <span className="font-medium">What each trigger activates</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <p>
                  A safeguarding option (defined per-tenant in <em>Safeguarding Options</em>) carries
                  a JSON block of triggers. When a member selects the option, the relevant triggers
                  activate:
                </p>
                <table className="w-full text-sm border-collapse">
                  <thead>
                    <tr className="border-b border-default-200 bg-default-100 dark:bg-default-800/40">
                      <th className="text-left p-2 font-semibold">Trigger key</th>
                      <th className="text-left p-2 font-semibold">Effect</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-default-200">
                    <tr>
                      <td className="p-2"><code>requires_broker_approval</code></td>
                      <td className="p-2">All outgoing messages copied for broker review; matches need sign-off.</td>
                    </tr>
                    <tr>
                      <td className="p-2"><code>restricts_messaging</code></td>
                      <td className="p-2">Marks the member under monitoring (message copies begin).</td>
                    </tr>
                    <tr>
                      <td className="p-2"><code>restricts_matching</code></td>
                      <td className="p-2">Match approval workflow engages — no auto-introductions.</td>
                    </tr>
                    <tr>
                      <td className="p-2"><code>requires_vetted_interaction</code></td>
                      <td className="p-2">Non-vetted members are hidden from the member's discovery feed.</td>
                    </tr>
                    <tr>
                      <td className="p-2"><code>notify_admin_on_selection</code></td>
                      <td className="p-2">You get a real-time notification the moment the option is ticked.</td>
                    </tr>
                    <tr>
                      <td className="p-2"><code>vetting_type_required</code></td>
                      <td className="p-2">Specifies which vetting a contacting member must hold (e.g. <code>garda_vetting</code>).</td>
                    </tr>
                  </tbody>
                </table>
                <p className="italic text-default-500">
                  When a member selects multiple options, triggers OR-merge: the most protective
                  combination wins. Revoking one option may release some protections but keep
                  others active — the system re-evaluates from scratch each time.
                </p>
              </div>
            </AccordionItem>

            {/* ─────────────────────────────────────────────────────────────── */}
            <AccordionItem
              key="vetting-gate"
              aria-label="Vetting gates"
              title={
                <div className="flex items-center gap-2">
                  <UserCheck className="w-4 h-4 text-success" />
                  <span className="font-medium">How vetting gates interactions</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <p>
                  When a flagged member requires a specific vetting type, the system enforces it
                  at three separate points — not just as a UI warning, but as a hard block with
                  a specific error code <code>VETTING_REQUIRED</code> returned to the caller.
                </p>
                <ol className="list-decimal pl-5 space-y-2">
                  <li>
                    <strong>Discovery filter</strong> — Smart Matching Engine never surfaces
                    flagged members to someone who doesn't hold the required vetting. They are
                    invisible, not greyed out.
                  </li>
                  <li>
                    <strong>Messaging</strong> — if a sender lacks vetting the recipient requires,
                    <code> MessageService::send</code> returns <code>VETTING_REQUIRED</code> with
                    the specific types listed.
                  </li>
                  <li>
                    <strong>Match approval</strong> — even if a match is manually submitted for
                    approval, it's blocked bidirectionally if either party is flagged and the
                    other lacks the required vetting.
                  </li>
                  <li>
                    <strong>Group exchanges</strong> — adding a participant fails if the organiser
                    or the participant requires vetting the other doesn't hold.
                  </li>
                </ol>
                <p>
                  <strong>Staff bypass:</strong> admins, tenant admins, brokers, and super admins
                  see the full pool in discovery (for assignment purposes). Messaging, match
                  submission, and group exchange gates still apply to everyone — there is no
                  admin-level bypass for those.
                </p>
              </div>
            </AccordionItem>

            {/* ─────────────────────────────────────────────────────────────── */}
            <AccordionItem
              key="assignments"
              aria-label="Guardian assignments"
              title={
                <div className="flex items-center gap-2">
                  <Users className="w-4 h-4 text-secondary" />
                  <span className="font-medium">Guardian / ward assignments</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <p>
                  A guardian assignment pairs a ward (the protected member) with a guardian (a
                  trusted coordinator or family member). Use this when a member needs a specific
                  person mediating their interactions, rather than the general broker pool.
                </p>
                <ul className="list-disc pl-5 space-y-2">
                  <li>
                    <strong>Create</strong> from the Assignments tab or by navigating to
                    <em> broker-controls/monitoring → Assign Guardian</em>. Both parties receive an
                    email and an in-app bell notification.
                  </li>
                  <li>
                    <strong>Consent</strong> — the ward must accept; their acceptance is recorded
                    in <code>consent_given_at</code>. The assignment only becomes <em>active</em>
                    once accepted.
                  </li>
                  <li>
                    <strong>Revoke</strong> — either the admin or the ward can revoke. Revocation
                    is soft (sets <code>revoked_at</code>) so the audit trail is preserved.
                  </li>
                </ul>
                <p>
                  Guardian assignments do not replace the broker review queue — monitored messages
                  still flow there. A guardian is an extra trusted layer, not a substitute for
                  platform oversight.
                </p>
              </div>
            </AccordionItem>

            {/* ─────────────────────────────────────────────────────────────── */}
            <AccordionItem
              key="member-preferences"
              aria-label="Member preferences tab"
              title={
                <div className="flex items-center gap-2">
                  <FileText className="w-4 h-4 text-primary" />
                  <span className="font-medium">Reading member preferences</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <p>
                  The Member Preferences tab lists every flagged member alongside the specific
                  options they've selected. This data is <strong>special category personal data</strong>
                  under GDPR — treat it with care.
                </p>
                <ul className="list-disc pl-5 space-y-2">
                  <li>Every view is audit-logged with your user ID and timestamp.</li>
                  <li>It is never exposed in a member's public profile.</li>
                  <li>Other members cannot see this tab — access requires an admin-class role.</li>
                  <li>Do not discuss another member's flags with anyone outside the safeguarding team.</li>
                </ul>
                <p>
                  Click a member's row to open their full activity trail — every message copied,
                  every assignment change, every trigger activation, chronologically. The trail
                  is downloadable as CSV for regulator requests or incident reports.
                </p>
              </div>
            </AccordionItem>

            {/* ─────────────────────────────────────────────────────────────── */}
            <AccordionItem
              key="autonomy"
              aria-label="Adult autonomy principle"
              title={
                <div className="flex items-center gap-2">
                  <Scale className="w-4 h-4 text-success" />
                  <span className="font-medium">Adult autonomy principle</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <div className="p-3 rounded-lg bg-success/5 border border-success/20">
                  <p className="font-medium text-foreground mb-2">The rule:</p>
                  <p>
                    An adult who has self-identified as requiring safeguarding protections has the
                    absolute right to view <em>and</em> revoke those protections without asking
                    an administrator. Admins <strong>cannot</strong> block member self-revocation.
                  </p>
                </div>
                <p>
                  This is grounded in <strong>Safeguarding Ireland's adult-autonomy guidance</strong>
                  (and equivalent UK statutory guidance — "Making Safeguarding Personal"). Revoking
                  a flag triggers:
                </p>
                <ul className="list-disc pl-5 space-y-1">
                  <li>Re-evaluation of triggers → monitoring/approval requirements may lift.</li>
                  <li>An in-app bell notification to admins/brokers — you are told, but you cannot veto.</li>
                  <li>An audit-log entry with <code>action = 'safeguarding_consent_revoked'</code>.</li>
                </ul>
                <p>
                  If you believe revocation was coerced by a third party, the right response is
                  a safeguarding conversation with the member (and if needed, a referral to
                  statutory services) — not re-applying the flag via admin controls.
                </p>
              </div>
            </AccordionItem>

            {/* ─────────────────────────────────────────────────────────────── */}
            <AccordionItem
              key="annual-review"
              aria-label="Annual review process"
              title={
                <div className="flex items-center gap-2">
                  <Clock className="w-4 h-4 text-warning" />
                  <span className="font-medium">Annual review process</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <p>
                  On the first of each month, the <code>safeguarding:review-flags</code> command runs
                  automatically. For any preference older than 365 days without a recent review, it:
                </p>
                <ol className="list-decimal pl-5 space-y-1">
                  <li>Sends the member an email and bell asking them to review — stamps <code>review_reminder_sent_at</code>.</li>
                  <li>Waits 30 days.</li>
                  <li>If no response, notifies admins/brokers via bell + email — stamps <code>review_escalated_at</code>.</li>
                </ol>
                <p>
                  <strong>The flag stays active regardless.</strong> The escalation is a prompt for
                  a coordinator to reach out personally — it is not a cue to strip the protection.
                  Silence is not consent to remove safeguards.
                </p>
                <p>
                  Viewing <em>Settings → Safeguarding</em> counts as a review — the backend stamps
                  <code> review_confirmed_at</code> automatically when the member opens the page.
                </p>
              </div>
            </AccordionItem>

            {/* ─────────────────────────────────────────────────────────────── */}
            <AccordionItem
              key="audit"
              aria-label="Audit trail & CSV export"
              title={
                <div className="flex items-center gap-2">
                  <Eye className="w-4 h-4 text-default-500" />
                  <span className="font-medium">Audit trail &amp; CSV export</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <p>
                  Every safeguarding-related action is written to <code>activity_log</code> with
                  <code> action_type = 'safeguarding'</code>. The per-member activity endpoint combines
                  three sources into one newest-first timeline:
                </p>
                <ul className="list-disc pl-5 space-y-1">
                  <li><strong>Activity log</strong> — option selections, consent revocations, admin views, trigger activations.</li>
                  <li><strong>Message copies</strong> — every message sent or received by the member while monitored.</li>
                  <li><strong>Assignment history</strong> — guardian assignments created or revoked involving the member.</li>
                </ul>
                <p>
                  Use the <em>Export CSV</em> action on the member detail view when you need a
                  file you can attach to an incident report or share with a regulator. The CSV
                  is UTF-8 with a BOM so Excel opens it correctly without prompting for encoding.
                </p>
              </div>
            </AccordionItem>
          </Accordion>
        </CardBody>
      </Card>
    </section>
  );
}

export default SafeguardingHelp;
