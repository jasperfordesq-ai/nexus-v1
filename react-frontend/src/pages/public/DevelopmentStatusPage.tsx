// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Development Status Page
 *
 * Public page explaining the platform's current release stage (RC),
 * what's stable, what may be rough, and how to help test.
 */

import { Card, CardBody, CardHeader, Divider, Chip } from '@heroui/react';
import { FlaskConical, CheckCircle, AlertTriangle, Bug, Shield, Users, ExternalLink } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks/usePageTitle';
import { RELEASE_STATUS } from '@/config/releaseStatus';

export function DevelopmentStatusPage() {
  const { t } = useTranslation('public');
  usePageTitle('Development Status — Release Candidate (RC)');

  return (
    <div className="max-w-3xl mx-auto space-y-6 py-4">
      {/* Header */}
      <div className="flex items-start gap-3">
        <FlaskConical className="w-8 h-8 text-amber-500 shrink-0 mt-1" aria-hidden="true" />
        <div>
          <h1 className="text-2xl font-bold text-foreground">
            {t('dev_status.title', { stage: RELEASE_STATUS.stageLabel })}
          </h1>
          <Chip
            color="warning"
            variant="flat"
            size="sm"
            className="mt-2"
          >
            {RELEASE_STATUS.stageKey.toUpperCase()}
          </Chip>
        </div>
      </div>

      {/* Where we are */}
      <Card>
        <CardHeader>
          <h2 className="text-lg font-semibold">{t('dev_status.where_we_are.title')}</h2>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-3 text-sm text-foreground-600">
          <p>
            {t('dev_status.where_we_are.paragraph1')}
          </p>
          <p>
            {t('dev_status.where_we_are.paragraph2')}
          </p>
        </CardBody>
      </Card>

      {/* What's stable */}
      <Card>
        <CardHeader className="flex gap-2">
          <CheckCircle className="w-5 h-5 text-success" aria-hidden="true" />
          <h2 className="text-lg font-semibold">{t('dev_status.whats_stable.title')}</h2>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-5">
          {([
            {
              groupKey: 'core',
              items: ['item1','item2','item3','item4','item5','item6'],
            },
            {
              groupKey: 'member',
              items: ['item1','item2','item3','item4','item5','item6','item7','item8','item9','item10','item11','item12','item13','item14','item15','item16','item17','item18','item19'],
            },
            {
              groupKey: 'content',
              items: ['item1','item2','item3','item4','item5','item6','item7'],
            },
            {
              groupKey: 'platform',
              items: ['item1','item2','item3','item4','item5','item6','item7','item8'],
            },
          ]).map(({ groupKey, items }) => (
            <div key={groupKey}>
              <p className="text-xs font-semibold uppercase tracking-wide text-foreground-400 mb-2">
                {t(`dev_status.whats_stable.${groupKey}_title`)}
              </p>
              <ul className="text-sm text-foreground-600 space-y-1.5 list-none">
                {items.map((item, index) => (
                  <li key={index} className="flex items-start gap-2">
                    <CheckCircle className="w-3.5 h-3.5 text-success shrink-0 mt-0.5" aria-hidden="true" />
                    {t(`dev_status.whats_stable.${groupKey}_${item}`)}
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </CardBody>
      </Card>

      {/* What may be rough */}
      <Card>
        <CardHeader className="flex gap-2">
          <AlertTriangle className="w-5 h-5 text-warning" aria-hidden="true" />
          <h2 className="text-lg font-semibold">{t('dev_status.whats_rough.title')}</h2>
        </CardHeader>
        <Divider />
        <CardBody>
          <ul className="text-sm text-foreground-600 space-y-2 list-none">
            {([
              t('dev_status.whats_rough.item1'),
              t('dev_status.whats_rough.item2'),
              t('dev_status.whats_rough.item3'),
              t('dev_status.whats_rough.item4'),
              t('dev_status.whats_rough.item5'),
              t('dev_status.whats_rough.item6'),
            ]).map((item, index) => (
              <li key={index} className="flex items-start gap-2">
                <AlertTriangle className="w-3.5 h-3.5 text-warning shrink-0 mt-0.5" aria-hidden="true" />
                {item}
              </li>
            ))}
          </ul>
        </CardBody>
      </Card>

      {/* How we catch bugs */}
      <Card>
        <CardHeader className="flex gap-2">
          <Shield className="w-5 h-5 text-primary" aria-hidden="true" />
          <h2 className="text-lg font-semibold">{t('dev_status.how_we_catch_bugs.title')}</h2>
        </CardHeader>
        <Divider />
        <CardBody className="text-sm text-foreground-600 space-y-2">
          <p>
            {t('dev_status.how_we_catch_bugs.intro')}
          </p>
          <ul className="space-y-1.5 list-none ml-2">
            <li>&bull; <strong>{t('dev_status.how_we_catch_bugs.method1_bold')}</strong> — {t('dev_status.how_we_catch_bugs.method1_text')}</li>
            <li>&bull; <strong>{t('dev_status.how_we_catch_bugs.method2_bold')}</strong> — {t('dev_status.how_we_catch_bugs.method2_text')}</li>
            <li>&bull; <strong>{t('dev_status.how_we_catch_bugs.method3_bold')}</strong> — {t('dev_status.how_we_catch_bugs.method3_text')}</li>
            <li>&bull; <strong>{t('dev_status.how_we_catch_bugs.method4_bold')}</strong> — {t('dev_status.how_we_catch_bugs.method4_text')}</li>
            <li>&bull; <strong>{t('dev_status.how_we_catch_bugs.method5_bold')}</strong> — {t('dev_status.how_we_catch_bugs.method5_text')}</li>
          </ul>
        </CardBody>
      </Card>

      {/* How to help */}
      <Card>
        <CardHeader className="flex gap-2">
          <Users className="w-5 h-5 text-secondary" aria-hidden="true" />
          <h2 className="text-lg font-semibold">{t('dev_status.how_to_help.title')}</h2>
        </CardHeader>
        <Divider />
        <CardBody className="text-sm text-foreground-600 space-y-4">
          <div className="flex items-start gap-3">
            <Bug className="w-5 h-5 text-danger shrink-0 mt-0.5" aria-hidden="true" />
            <div>
              <h3 className="font-semibold text-foreground mb-1">{t('dev_status.how_to_help.report_bug_title')}</h3>
              <p>
                {t('dev_status.how_to_help.report_bug_description')}
              </p>
              <a
                href="https://github.com/jasperfordesq-ai/nexus-v1/issues"
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1 mt-2 text-primary underline font-medium focus:outline-none focus:ring-2 focus:ring-primary rounded"
              >
                {t('dev_status.how_to_help.github_link')}
                <ExternalLink className="w-3.5 h-3.5" aria-hidden="true" />
              </a>
            </div>
          </div>

          <Divider />

          <div className="flex items-start gap-3">
            <Users className="w-5 h-5 text-secondary shrink-0 mt-0.5" aria-hidden="true" />
            <div>
              <h3 className="font-semibold text-foreground mb-1">{t('dev_status.how_to_help.become_tester_title')}</h3>
              <p>
                {t('dev_status.how_to_help.become_tester_description')}
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Security */}
      <Card className="border border-danger-200 dark:border-danger-800">
        <CardHeader className="flex gap-2">
          <Shield className="w-5 h-5 text-danger" aria-hidden="true" />
          <h2 className="text-lg font-semibold">{t('dev_status.security.title')}</h2>
        </CardHeader>
        <Divider />
        <CardBody className="text-sm text-foreground-600">
          <p>
            {t('dev_status.security.description_before_email')}{' '}
            <a
              href="mailto:jasper@hour-timebank.ie"
              className="text-primary underline font-medium focus:outline-none focus:ring-2 focus:ring-primary rounded"
            >
              jasper@hour-timebank.ie
            </a>
            {t('dev_status.security.description_after_email')}
          </p>
        </CardBody>
      </Card>
    </div>
  );
}

export default DevelopmentStatusPage;
