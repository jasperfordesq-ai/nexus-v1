// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BrokerControlsHelp — collapsible guidance panel for the Broker Controls.
 *
 * Used in two places:
 *   1. Embedded at the bottom of BrokerDashboardPage (no page-title side effect)
 *   2. As the standalone /broker/help route via the BrokerHelpPage default export
 *
 * The presentational `BrokerControlsHelp` component does NOT call usePageTitle —
 * if it did, embedding it on the dashboard would clobber the dashboard's title.
 * The standalone wrapper `BrokerHelpPage` owns the title for the /broker/help route.
 */

import { Card, CardBody, CardHeader, Accordion, AccordionItem, Divider } from '@heroui/react';
import { Trans, useTranslation } from 'react-i18next';
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

const richComponents = {
  b: <strong />,
  i: <em />,
  code: <code />,
};

export function BrokerControlsHelp() {
  const { t } = useTranslation('broker');

  return (
    <section className="mt-10">
      <Card shadow="sm" className="border border-default-200">
        <CardHeader className="flex items-center gap-3 pb-2">
          <div className="p-2 rounded-lg bg-primary/10">
            <BookOpen className="w-5 h-5 text-primary" />
          </div>
          <div>
            <h2 className="text-lg font-semibold text-foreground">
              {t('help.title')}
            </h2>
            <p className="text-xs text-default-500">
              {t('help.subtitle')}
            </p>
          </div>
        </CardHeader>
        <Divider />
        <CardBody className="pt-4">
          <Accordion variant="splitted" selectionMode="multiple">
            {/* Overview */}
            <AccordionItem
              key="overview"
              aria-label={t('help.overview.aria')}
              title={
                <div className="flex items-center gap-2">
                  <Workflow className="w-4 h-4 text-primary" />
                  <span className="font-medium">{t('help.overview.title')}</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <p>{t('help.overview.intro')}</p>
                <ul className="list-disc pl-5 space-y-1">
                  <li><Trans t={t} i18nKey="help.overview.bullet_exchange" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.overview.bullet_risk_tags" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.overview.bullet_message_review" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.overview.bullet_user_monitoring" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.overview.bullet_vetting" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.overview.bullet_configuration" components={richComponents} /></li>
                </ul>
                <p><Trans t={t} i18nKey="help.overview.access_note" components={richComponents} /></p>
              </div>
            </AccordionItem>

            {/* Workflow */}
            <AccordionItem
              key="workflow"
              aria-label={t('help.workflow.aria')}
              title={
                <div className="flex items-center gap-2">
                  <Workflow className="w-4 h-4 text-secondary" />
                  <span className="font-medium">{t('help.workflow.title')}</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <p>{t('help.workflow.intro')}</p>
                <ol className="list-decimal pl-5 space-y-2">
                  <li><Trans t={t} i18nKey="help.workflow.step_unreviewed" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.workflow.step_pending" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.workflow.step_alerts" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.workflow.step_vetting" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.workflow.step_activity" components={richComponents} /></li>
                </ol>
                <p className="italic text-default-500">{t('help.workflow.tip')}</p>
              </div>
            </AccordionItem>

            {/* Messages */}
            <AccordionItem
              key="messages"
              aria-label={t('help.messages.aria')}
              title={
                <div className="flex items-center gap-2">
                  <MessageSquareWarning className="w-4 h-4 text-warning" />
                  <span className="font-medium">{t('help.messages.title')}</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <p>{t('help.messages.intro')}</p>
                <p className="font-medium text-foreground">{t('help.messages.severity_heading')}</p>
                <ul className="list-disc pl-5 space-y-1">
                  <li><Trans t={t} i18nKey="help.messages.severity_high" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.messages.severity_medium" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.messages.severity_low" components={richComponents} /></li>
                </ul>
                <p className="font-medium text-foreground">{t('help.messages.action_heading')}</p>
                <ol className="list-decimal pl-5 space-y-1">
                  <li>{t('help.messages.action_open')}</li>
                  <li>{t('help.messages.action_read')}</li>
                  <li><Trans t={t} i18nKey="help.messages.action_mark" components={richComponents} /></li>
                  <li>{t('help.messages.action_escalate')}</li>
                </ol>
                <p>{t('help.messages.retention')}</p>
              </div>
            </AccordionItem>

            {/* Monitoring */}
            <AccordionItem
              key="monitoring"
              aria-label={t('help.monitoring.aria')}
              title={
                <div className="flex items-center gap-2">
                  <Eye className="w-4 h-4 text-secondary" />
                  <span className="font-medium">{t('help.monitoring.title')}</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <p>{t('help.monitoring.intro')}</p>
                <ul className="list-disc pl-5 space-y-2">
                  <li><Trans t={t} i18nKey="help.monitoring.automatic" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.monitoring.manual" components={richComponents} /></li>
                </ul>
                <p><Trans t={t} i18nKey="help.monitoring.expiry" components={richComponents} /></p>
                <p><Trans t={t} i18nKey="help.monitoring.risk_tags" components={richComponents} /></p>
              </div>
            </AccordionItem>

            {/* Vetting */}
            <AccordionItem
              key="vetting"
              aria-label={t('help.vetting.aria')}
              title={
                <div className="flex items-center gap-2">
                  <ShieldCheck className="w-4 h-4 text-success" />
                  <span className="font-medium">{t('help.vetting.title')}</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <p>{t('help.vetting.intro')}</p>
                <ul className="list-disc pl-5 space-y-1">
                  <li><Trans t={t} i18nKey="help.vetting.type_garda" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.vetting.type_dbs" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.vetting.type_pvg" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.vetting.type_access_ni" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.vetting.type_international" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.vetting.type_other" components={richComponents} /></li>
                </ul>
                <p className="font-medium text-foreground">{t('help.vetting.lifecycle_heading')}</p>
                <ol className="list-decimal pl-5 space-y-1">
                  <li><Trans t={t} i18nKey="help.vetting.lifecycle_pending" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.vetting.lifecycle_verified" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.vetting.lifecycle_reminder" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.vetting.lifecycle_expired" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.vetting.lifecycle_rejected" components={richComponents} /></li>
                </ol>
                <p className="italic text-default-500"><Trans t={t} i18nKey="help.vetting.match_note" components={richComponents} /></p>
              </div>
            </AccordionItem>

            {/* Alerts */}
            <AccordionItem
              key="alerts"
              aria-label={t('help.alerts.aria')}
              title={
                <div className="flex items-center gap-2">
                  <AlertTriangle className="w-4 h-4 text-danger" />
                  <span className="font-medium">{t('help.alerts.title')}</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <p>{t('help.alerts.intro')}</p>
                <ul className="list-disc pl-5 space-y-1">
                  <li>{t('help.alerts.counts_messages')}</li>
                  <li>{t('help.alerts.counts_incidents')}</li>
                </ul>
                <p className="font-medium text-foreground">{t('help.alerts.escalate_heading')}</p>
                <ul className="list-disc pl-5 space-y-2">
                  <li><Trans t={t} i18nKey="help.alerts.escalate_abuse" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.alerts.escalate_child" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.alerts.escalate_vetting" components={richComponents} /></li>
                </ul>
              </div>
            </AccordionItem>

            {/* Legal */}
            <AccordionItem
              key="legal"
              aria-label={t('help.legal.aria')}
              title={
                <div className="flex items-center gap-2">
                  <Scale className="w-4 h-4 text-default-500" />
                  <span className="font-medium">{t('help.legal.title')}</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <p>{t('help.legal.disclaimer')}</p>
                <ul className="list-disc pl-5 space-y-2">
                  <li><Trans t={t} i18nKey="help.legal.law_nvb" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.legal.law_children_first" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.legal.law_svga" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.legal.law_pvg" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.legal.law_svgni" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.legal.law_gdpr" components={richComponents} /></li>
                </ul>
              </div>
            </AccordionItem>

            {/* Data */}
            <AccordionItem
              key="data"
              aria-label={t('help.data.aria')}
              title={
                <div className="flex items-center gap-2">
                  <Database className="w-4 h-4 text-default-500" />
                  <span className="font-medium">{t('help.data.title')}</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <ul className="list-disc pl-5 space-y-2">
                  <li><Trans t={t} i18nKey="help.data.monitoring_status" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.data.message_copies" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.data.vetting_records" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.data.user_prefs" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.data.guardian_assignments" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.data.audit_trail" components={richComponents} /></li>
                </ul>
              </div>
            </AccordionItem>

            {/* Contacts */}
            <AccordionItem
              key="contacts"
              aria-label={t('help.contacts.aria')}
              title={
                <div className="flex items-center gap-2">
                  <Phone className="w-4 h-4 text-primary" />
                  <span className="font-medium">{t('help.contacts.title')}</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <ul className="list-disc pl-5 space-y-2">
                  <li><Trans t={t} i18nKey="help.contacts.technical" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.contacts.safeguarding" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.contacts.criminality" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.contacts.policy" components={richComponents} /></li>
                </ul>
              </div>
            </AccordionItem>

            {/* Troubleshooting */}
            <AccordionItem
              key="troubleshooting"
              aria-label={t('help.troubleshooting.aria')}
              title={
                <div className="flex items-center gap-2">
                  <ShieldAlert className="w-4 h-4 text-warning" />
                  <span className="font-medium">{t('help.troubleshooting.title')}</span>
                </div>
              }
            >
              <div className="text-sm leading-relaxed text-default-700 dark:text-default-400 space-y-3">
                <ul className="list-disc pl-5 space-y-2">
                  <li><Trans t={t} i18nKey="help.troubleshooting.cant_message" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.troubleshooting.wrong_member" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.troubleshooting.vetting_stuck" components={richComponents} /></li>
                  <li><Trans t={t} i18nKey="help.troubleshooting.no_copies" components={richComponents} /></li>
                </ul>
              </div>
            </AccordionItem>
          </Accordion>
        </CardBody>
      </Card>
    </section>
  );
}

/**
 * Standalone /broker/help route wrapper. Owns the page title; the embedded
 * usage on the dashboard renders the bare `BrokerControlsHelp` component.
 */
export default function BrokerHelpPage() {
  const { t } = useTranslation('broker');
  usePageTitle(t('help.page_title'));
  return <BrokerControlsHelp />;
}
