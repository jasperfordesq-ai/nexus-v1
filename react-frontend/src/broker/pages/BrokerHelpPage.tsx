// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BrokerControlsHelp — collapsible guidance panel for the Broker Controls dashboard.
 *
 * Rendered at the bottom of BrokerDashboard. The panel heading is always visible;
 * each section's content is tucked into a HeroUI Accordion so admins can scan
 * section titles and expand the one they need.
 *
 * Content is written in British English for admin operators. If admin-panel i18n
 * is ever turned on (currently English-only per project policy), migrate these
 * strings into the `admin.broker_help.*` namespace.
 */

import { Card, CardBody, CardHeader, Accordion, AccordionItem, Divider } from '@heroui/react';
import { usePageTitle } from '@/hooks';
import BookOpen from 'lucide-react/icons/book-open';
import Workflow from 'lucide-react/icons/workflow';
import MessageSquareWarning from 'lucide-react/icons/message-square-warning';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import Eye from 'lucide-react/icons/eye';
import ShieldCheck from 'lucide-react/icons/shield-check';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import Scale from 'lucide-react/icons/scale';
import Phone from 'lucide-react/icons/phone';
import Database from 'lucide-react/icons/database';

export function BrokerControlsHelp() {
  usePageTitle("Help - Broker");
  return (
    <section className="mt-10">
      <Card shadow="sm" className="border border-default-200">
        <CardHeader className="flex items-center gap-3 pb-2">
          <div className="p-2 rounded-lg bg-primary/10">
            <BookOpen className="w-5 h-5 text-primary" />
          </div>
          <div>
            <h2 className="text-lg font-semibold text-foreground">
              Guidance &amp; reference
            </h2>
            <p className="text-xs text-default-500">
              How the brokering and safeguarding system works — for admins and coordinators.
              Each section is independent — open whichever you need.
            </p>
          </div>
        </CardHeader>
        <Divider />
        <CardBody className="pt-4">
          <Accordion variant="splitted" selectionMode="multiple">
            {/* ─────────────────────────────────────────────────────────────── */}
            <AccordionItem
              key="overview"
              aria-label="What Broker Controls manages"
              title={
                <div className="flex items-center gap-2">
                  <Workflow className="w-4 h-4 text-primary" />
                  <span className="font-medium">What this module manages</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <p>
                  Broker Controls is the operational hub for coordinators, brokers, and admins who
                  mediate between members when safeguarding or compliance concerns are in play.
                  It brings together six sub-modules:
                </p>
                <ul className="list-disc pl-5 space-y-1">
                  <li><strong>Exchange management</strong> — approve or reject exchanges that need broker sign-off.</li>
                  <li><strong>Risk tags</strong> — manually flag listings you want to watch.</li>
                  <li><strong>Message review</strong> — triage messages that have been copied to the broker queue.</li>
                  <li><strong>User monitoring</strong> — put a member's communications under broker oversight.</li>
                  <li><strong>Vetting records</strong> — track DBS, Garda Vetting, PVG, and AccessNI checks.</li>
                  <li><strong>Configuration</strong> — tune thresholds for how the system decides what to copy.</li>
                </ul>
                <p>
                  Access is restricted to users with the <code>admin</code>, <code>tenant_admin</code>,
                  <code> broker</code>, or <code>super_admin</code> role. Every view and action is written
                  to the audit log.
                </p>
              </div>
            </AccordionItem>

            {/* ─────────────────────────────────────────────────────────────── */}
            <AccordionItem
              key="workflow"
              aria-label="Your daily broker workflow"
              title={
                <div className="flex items-center gap-2">
                  <Workflow className="w-4 h-4 text-secondary" />
                  <span className="font-medium">Your daily workflow</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <p>A recommended daily check-in routine for coordinators:</p>
                <ol className="list-decimal pl-5 space-y-2">
                  <li>
                    <strong>Unreviewed messages</strong> — open Message Review and clear anything
                    flagged red (high severity) first. Aim for a 24-hour SLA on flagged items.
                  </li>
                  <li>
                    <strong>Pending exchanges</strong> — approve or reject exchanges awaiting broker
                    sign-off. An unapproved exchange blocks both parties.
                  </li>
                  <li>
                    <strong>Safeguarding alerts</strong> — if the red counter on the safeguarding
                    tile is non-zero, jump to the Safeguarding dashboard to triage.
                  </li>
                  <li>
                    <strong>Vetting expiring</strong> — members flagged as expiring in 30 days need
                    a renewal reminder. The system also emails them automatically.
                  </li>
                  <li>
                    <strong>Recent activity feed</strong> — skim the activity list to catch
                    patterns: repeat senders, unusual volumes, new monitored users.
                  </li>
                </ol>
                <p className="italic text-default-500">
                  The stat tiles all deep-link into the correct filter — one click from overview
                  to actionable list.
                </p>
              </div>
            </AccordionItem>

            {/* ─────────────────────────────────────────────────────────────── */}
            <AccordionItem
              key="messages"
              aria-label="How message review works"
              title={
                <div className="flex items-center gap-2">
                  <MessageSquareWarning className="w-4 h-4 text-warning" />
                  <span className="font-medium">How message review works</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <p>
                  When a member is under monitoring (either self-declared during onboarding or
                  placed under monitoring by an admin), the system automatically copies each message
                  they send or receive into a separate broker review queue. The original message is
                  delivered normally — members never know a copy was made unless you tell them.
                </p>
                <p className="font-medium text-foreground">Severity levels</p>
                <ul className="list-disc pl-5 space-y-1">
                  <li><strong className="text-danger">High</strong> — flagged by the content scanner for keywords or patterns of concern.</li>
                  <li><strong className="text-warning">Medium</strong> — sender or recipient is a flagged user (no content match).</li>
                  <li><strong className="text-default-500">Low</strong> — routine copy for audit; no automatic concerns.</li>
                </ul>
                <p className="font-medium text-foreground">How to action</p>
                <ol className="list-decimal pl-5 space-y-1">
                  <li>Open the message from the queue.</li>
                  <li>Read the full exchange — previous messages give context.</li>
                  <li>Click <em>Mark as reviewed</em> with optional notes, or escalate.</li>
                  <li>If the message is a safeguarding concern, also create or update a guardian
                      assignment in the Safeguarding dashboard.</li>
                </ol>
                <p>
                  Reviewed copies stay in the archive for 180 days then auto-purge (configurable
                  in the Configuration sub-module).
                </p>
              </div>
            </AccordionItem>

            {/* ─────────────────────────────────────────────────────────────── */}
            <AccordionItem
              key="monitoring"
              aria-label="User monitoring and risk tags"
              title={
                <div className="flex items-center gap-2">
                  <Eye className="w-4 h-4 text-secondary" />
                  <span className="font-medium">User monitoring &amp; risk tags</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <p>
                  There are two ways a member can end up on a watch list:
                </p>
                <ul className="list-disc pl-5 space-y-2">
                  <li>
                    <strong>Automatic (self-identified)</strong> — during onboarding, the member
                    ticked a safeguarding option that has <code>restricts_messaging</code> or
                    <code> requires_broker_approval</code> triggers. These are written to
                    <code> user_messaging_restrictions</code> with reason
                    <em> "Safeguarding: self-identified during onboarding"</em>.
                  </li>
                  <li>
                    <strong>Manual (admin-initiated)</strong> — you open User Monitoring and add
                    a member with a reason and optional expiry date. The reason is stored so any
                    future reviewer knows why.
                  </li>
                </ul>
                <p>
                  Monitoring can be <em>time-limited</em> — set <code>monitoring_expires_at</code>
                  and the daily <code>safeguarding:clear-expired-monitoring</code> command will
                  silently lift the flag once the date passes.
                </p>
                <p>
                  <strong>Risk tags</strong> are a listing-level flag, not a member-level one.
                  Tag a listing if you want to watch how it performs without restricting the member.
                </p>
              </div>
            </AccordionItem>

            {/* ─────────────────────────────────────────────────────────────── */}
            <AccordionItem
              key="vetting"
              aria-label="Vetting records"
              title={
                <div className="flex items-center gap-2">
                  <ShieldCheck className="w-4 h-4 text-success" />
                  <span className="font-medium">Vetting records (DBS, Garda, PVG, AccessNI)</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <p>
                  A vetting record captures one submitted background-check reference. Supported types:
                </p>
                <ul className="list-disc pl-5 space-y-1">
                  <li><strong>garda_vetting</strong> — Ireland, National Vetting Bureau (NVB).</li>
                  <li><strong>dbs_basic / standard / enhanced</strong> — England &amp; Wales, Disclosure &amp; Barring Service.</li>
                  <li><strong>pvg_scotland</strong> — Scotland, Protecting Vulnerable Groups scheme.</li>
                  <li><strong>access_ni</strong> — Northern Ireland, AccessNI disclosure.</li>
                  <li><strong>international</strong> — for members with overseas vetting.</li>
                  <li><strong>other</strong> — anything non-standard; use sparingly.</li>
                </ul>
                <p className="font-medium text-foreground">Life cycle</p>
                <ol className="list-decimal pl-5 space-y-1">
                  <li>Member submits reference and expiry date → status <code>pending</code>.</li>
                  <li>You verify against the issuing authority → status <code>verified</code>, which
                      stamps <code>verified_by</code> and <code>verified_at</code>.</li>
                  <li>30 days before <code>expiry_date</code> the member receives a reminder.</li>
                  <li>Past expiry → status <code>expired</code>; they lose the benefit of the
                      vetting until renewed.</li>
                  <li>If evidence is bad, <em>reject</em> with a reason. Rejected records preserve
                      the audit trail (soft delete) for legal retention.</li>
                </ol>
                <p className="italic text-default-500">
                  The vetting status also powers the discovery filter in the Smart Matching Engine —
                  a member who has declared <em>"I only want vetted contacts"</em> will never see
                  un-vetted members in their match suggestions.
                </p>
              </div>
            </AccordionItem>

            {/* ─────────────────────────────────────────────────────────────── */}
            <AccordionItem
              key="alerts"
              aria-label="Safeguarding alerts"
              title={
                <div className="flex items-center gap-2">
                  <AlertTriangle className="w-4 h-4 text-danger" />
                  <span className="font-medium">Safeguarding alerts &amp; escalation</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <p>
                  The safeguarding alerts tile counts:
                </p>
                <ul className="list-disc pl-5 space-y-1">
                  <li>Unreviewed flagged message copies (content-matched).</li>
                  <li>Open high- or critical-severity volunteer safeguarding incidents.</li>
                </ul>
                <p className="font-medium text-foreground">When to escalate</p>
                <ul className="list-disc pl-5 space-y-2">
                  <li>
                    <strong>Suspected abuse or exploitation:</strong> stop, document, and contact your
                    Designated Safeguarding Lead (DSL) before taking any user-facing action. Do not
                    warn the member; do not delete messages. The audit trail is evidence.
                  </li>
                  <li>
                    <strong>Child protection concern:</strong> in Ireland, notify Tusla; in UK,
                    notify the local authority children's services. The law requires this regardless
                    of the platform's internal process.
                  </li>
                  <li>
                    <strong>Vetting mismatch:</strong> if the name or DoB on submitted vetting
                    evidence does not match the profile, reject with reason and ask for a fresh
                    submission.
                  </li>
                </ul>
              </div>
            </AccordionItem>

            {/* ─────────────────────────────────────────────────────────────── */}
            <AccordionItem
              key="legal"
              aria-label="Legal framework"
              title={
                <div className="flex items-center gap-2">
                  <Scale className="w-4 h-4 text-default-500" />
                  <span className="font-medium">Legal framework &amp; compliance</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <p>
                  The following legislation underpins how we gate interactions with children and
                  vulnerable adults. This is not legal advice — consult your DSL or legal team for
                  specific situations.
                </p>
                <ul className="list-disc pl-5 space-y-2">
                  <li>
                    <strong>National Vetting Bureau (Children and Vulnerable Persons) Acts 2012–2016</strong>
                    {' '}(Ireland) — Garda Vetting is a legal requirement for relevant work with
                    children and vulnerable adults. Our system blocks unvetted contact, it does not
                    merely warn.
                  </li>
                  <li>
                    <strong>Children First Act 2015</strong> (Ireland) — requires any organisation
                    working with children to publish a Child Safeguarding Statement. When a tenant
                    admin ticks the <em>"works with children"</em> flag, the system enforces PDF
                    upload of this statement.
                  </li>
                  <li>
                    <strong>Safeguarding Vulnerable Groups Act 2006</strong> (England &amp; Wales) —
                    backs the DBS system. Enhanced DBS required for regulated activity.
                  </li>
                  <li>
                    <strong>Protection of Vulnerable Groups (Scotland) Act 2007</strong> — the PVG
                    Scheme.
                  </li>
                  <li>
                    <strong>Safeguarding Vulnerable Groups (Northern Ireland) Order 2007</strong> —
                    AccessNI disclosures.
                  </li>
                  <li>
                    <strong>GDPR / UK GDPR</strong> — vetting data and safeguarding preferences are
                    <em> special category</em> personal data. Access is restricted, audit-logged,
                    and retained only as long as operationally required.
                  </li>
                </ul>
              </div>
            </AccordionItem>

            {/* ─────────────────────────────────────────────────────────────── */}
            <AccordionItem
              key="data"
              aria-label="Where data lives"
              title={
                <div className="flex items-center gap-2">
                  <Database className="w-4 h-4 text-default-500" />
                  <span className="font-medium">Where the data lives &amp; retention</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <ul className="list-disc pl-5 space-y-2">
                  <li>
                    <strong>Monitoring status</strong>: <code>user_messaging_restrictions</code>.
                    Cleared automatically when expiry passes.
                  </li>
                  <li>
                    <strong>Message copies</strong>: <code>broker_message_copies</code>. 180-day
                    auto-purge via weekly scheduled command.
                  </li>
                  <li>
                    <strong>Vetting records</strong>: <code>vetting_records</code>. Legally retained
                    (soft delete) — cannot be hard-deleted through the UI.
                  </li>
                  <li>
                    <strong>Member safeguarding preferences</strong>:
                    <code> user_safeguarding_preferences</code>. Revoked by the member from their
                    settings; tombstoned, not deleted.
                  </li>
                  <li>
                    <strong>Guardian assignments</strong>: <code>safeguarding_assignments</code>.
                    Revocation is soft — the audit trail is preserved.
                  </li>
                  <li>
                    <strong>All admin access</strong> to any of the above is written to
                    <code> activity_log</code> with <code>action_type = 'safeguarding'</code>. Use
                    the per-member activity drill-down on the Safeguarding dashboard to export
                    a full trail as CSV.
                  </li>
                </ul>
              </div>
            </AccordionItem>

            {/* ─────────────────────────────────────────────────────────────── */}
            <AccordionItem
              key="contacts"
              aria-label="Who to contact"
              title={
                <div className="flex items-center gap-2">
                  <Phone className="w-4 h-4 text-primary" />
                  <span className="font-medium">Who to contact &amp; when</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <ul className="list-disc pl-5 space-y-2">
                  <li>
                    <strong>Technical issue with this panel</strong> — open a ticket with your
                    platform administrator; include the URL and approximate timestamp.
                  </li>
                  <li>
                    <strong>Safeguarding concern about a member</strong> — your community's
                    Designated Safeguarding Lead (DSL), not the platform. The DSL decides
                    whether to escalate to statutory services.
                  </li>
                  <li>
                    <strong>Suspected criminality</strong> — law enforcement (999 / 112 for
                    immediate danger). The platform's audit trail will support any subsequent
                    investigation.
                  </li>
                  <li>
                    <strong>Policy question</strong> — your tenant admin or compliance officer.
                    Safeguarding options and country presets are configurable per-tenant.
                  </li>
                </ul>
              </div>
            </AccordionItem>

            {/* ─────────────────────────────────────────────────────────────── */}
            <AccordionItem
              key="troubleshooting"
              aria-label="Troubleshooting"
              title={
                <div className="flex items-center gap-2">
                  <ShieldAlert className="w-4 h-4 text-warning" />
                  <span className="font-medium">Troubleshooting common situations</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <ul className="list-disc pl-5 space-y-2">
                  <li>
                    <strong>"A member says they can't message someone"</strong> — check whether the
                    recipient has safeguarding preferences requiring vetting types the sender doesn't
                    hold. The frontend shows a <em>VETTING_REQUIRED</em> banner with the specific
                    types needed.
                  </li>
                  <li>
                    <strong>"The wrong member is flagged"</strong> — open User Monitoring, remove
                    them manually. If it was the onboarding auto-flag, the member can also revoke
                    their own preference from <em>Settings → Safeguarding</em>.
                  </li>
                  <li>
                    <strong>"The vetting tile is stuck"</strong> — the Vetting Pending counter is
                    driven by <code>vetting_records.status = 'pending'</code>. Either verify or
                    reject; nothing will clear the tile until each pending row has moved on.
                  </li>
                  <li>
                    <strong>"Messages aren't being copied"</strong> — confirm the member has
                    <code> under_monitoring = 1</code> on <code>user_messaging_restrictions</code>.
                    If the row is missing, add them via User Monitoring with a reason.
                  </li>
                </ul>
              </div>
            </AccordionItem>
          </Accordion>
        </CardBody>
      </Card>
    </section>
  );
}

export default BrokerControlsHelp;
