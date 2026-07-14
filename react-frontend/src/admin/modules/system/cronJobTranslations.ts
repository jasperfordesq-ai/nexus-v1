// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { TFunction } from 'i18next';

interface TranslationCodedJob {
  id: string | number;
  slug?: string;
  translation_key?: string;
}

export function getCronJobTranslationCode(job: TranslationCodedJob): string {
  return (job.translation_key ?? job.slug ?? String(job.id))
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '');
}

export function getCronJobName(t: TFunction, job: TranslationCodedJob): string {
  const code = getCronJobTranslationCode(job);
  return t(`system.cron_jobs.${code}.name`, {
    defaultValue: t('system.cron_job_name_unknown'),
  });
}

export function getCronJobDescription(t: TFunction, job: TranslationCodedJob): string {
  const code = getCronJobTranslationCode(job);
  return t(`system.cron_jobs.${code}.description`, {
    defaultValue: t('system.cron_job_description_unknown'),
  });
}
