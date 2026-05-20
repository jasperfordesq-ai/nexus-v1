// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ChangelogPage
 *
 * Renders the project CHANGELOG.md in-app. The markdown source is copied
 * from the repo root into `public/changelog.md` by `scripts/copy-changelog.mjs`
 * at prebuild/predev time. We fetch it at runtime — no markdown content
 * lives in the JS bundle, so the page stays trim and the changelog stays
 * in sync with the file in git.
 */

import { useEffect, useState } from 'react';
import { Card, CardBody, Chip, Spinner } from '@heroui/react';
import { useTranslation } from 'react-i18next';
import ScrollText from 'lucide-react/icons/scroll-text';
import ExternalLink from 'lucide-react/icons/external-link';
import { PageMeta } from '@/components/seo';
import { usePageTitle } from '@/hooks/usePageTitle';
import { MarkdownRenderer } from '@/components/content/MarkdownRenderer';
import { RELEASE_STATUS } from '@/config/releaseStatus';

type FetchState =
  | { status: 'loading' }
  | { status: 'ok'; content: string }
  | { status: 'error'; message: string };

export function ChangelogPage() {
  const { t } = useTranslation('public');
  usePageTitle(t('changelog_page.title'));
  const [state, setState] = useState<FetchState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;
    fetch('/changelog.md', { headers: { Accept: 'text/markdown, text/plain' } })
      .then(async (res) => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const text = await res.text();
        if (!cancelled) setState({ status: 'ok', content: text });
      })
      .catch((err: unknown) => {
        if (!cancelled) {
          const message = err instanceof Error ? err.message : 'Unknown error';
          setState({ status: 'error', message });
        }
      });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <div className="max-w-3xl mx-auto space-y-6 py-4 px-4 sm:px-0">
      <PageMeta
        title={t('changelog_page.meta_title')}
        description={t('changelog_page.meta_description')}
      />

      {/* Header */}
      <div className="flex flex-col gap-2">
        <div className="flex items-center gap-3 flex-wrap">
          <ScrollText className="w-7 h-7 text-primary shrink-0" aria-hidden="true" />
          <h1 className="text-2xl sm:text-3xl font-bold text-foreground">
            {t('changelog_page.heading')}
          </h1>
          <Chip color="success" variant="flat" size="sm">
            {RELEASE_STATUS.stageLabel}
          </Chip>
        </div>
        <p className="text-sm text-foreground-600">
          {t('changelog_page.subheading')}
        </p>
        <p className="text-xs text-foreground-500">
          <a
            href="https://github.com/jasperfordesq-ai/nexus-v1/blob/main/CHANGELOG.md"
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1 text-primary underline focus:outline-none focus:ring-2 focus:ring-primary rounded"
          >
            {t('changelog_page.view_on_github')}
            <ExternalLink className="w-3 h-3" aria-hidden="true" />
          </a>
        </p>
      </div>

      {/* Body */}
      <Card>
        <CardBody className="py-6 px-4 sm:px-6">
          {state.status === 'loading' && (
            <div className="flex items-center justify-center py-12 gap-3 text-foreground-500">
              <Spinner size="sm" />
              <span className="text-sm">
                {t('changelog_page.loading')}
              </span>
            </div>
          )}
          {state.status === 'error' && (
            <div className="py-8 text-center text-sm text-danger">
              {t('changelog_page.error')}
              <p className="mt-2 text-xs text-foreground-500 font-mono">{state.message}</p>
            </div>
          )}
          {state.status === 'ok' && <MarkdownRenderer content={state.content} />}
        </CardBody>
      </Card>
    </div>
  );
}

export default ChangelogPage;
