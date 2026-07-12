// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import Wrench from 'lucide-react/icons/wrench';
import { useTranslation } from 'react-i18next';
import { GroupDataExportPanel } from '../components/GroupDataExportPanel';
import { ScheduledPostPanel } from '../components/ScheduledPostPanel';
import { WebhookConfigPanel } from '../components/WebhookConfigPanel';
import { WelcomeConfigPanel } from '../components/WelcomeConfigPanel';

interface GroupAutomationTabProps {
  groupId: number;
  isAdmin: boolean;
}

export function GroupAutomationTab({ groupId, isAdmin }: GroupAutomationTabProps) {
  const { t } = useTranslation('groups');
  if (!isAdmin) return null;

  return (
    <section className="space-y-5" aria-labelledby="group-automation-heading">
      <div className="flex items-start gap-3">
        <Wrench className="mt-1 h-5 w-5 flex-shrink-0 text-accent" aria-hidden="true" />
        <div>
          <h2 id="group-automation-heading" className="text-xl font-semibold text-theme-primary">
            {t('automation.title')}
          </h2>
          <p className="mt-1 text-sm text-theme-muted">{t('automation.description')}</p>
        </div>
      </div>

      <ScheduledPostPanel groupId={groupId} isAdmin={isAdmin} />
      <WebhookConfigPanel groupId={groupId} isAdmin={isAdmin} />
      <WelcomeConfigPanel groupId={groupId} isAdmin={isAdmin} />
      <GroupDataExportPanel groupId={groupId} isAdmin={isAdmin} />
    </section>
  );
}

export default GroupAutomationTab;
