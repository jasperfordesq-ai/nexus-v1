// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import type { TFunction } from 'i18next';

import type { AdminSupportReport } from '@/admin/api/types';
import supportTranslations from '../../../../public/locales/en/admin_support.json';
import { buildSupportReportHandoff } from './SupportReportsPage';

const t = ((key: string, options?: Record<string, unknown>) => {
  const value = key.split('.').reduce<unknown>((current, segment) => {
    if (!current || typeof current !== 'object') return undefined;
    return (current as Record<string, unknown>)[segment];
  }, supportTranslations);

  if (typeof value !== 'string') return key;
  return value.replace(/{{(\w+)}}/g, (placeholder, token: string) =>
    options?.[token] === undefined ? placeholder : String(options[token]),
  );
}) as unknown as TFunction<'admin_support'>;

describe('buildSupportReportHandoff', () => {
  it('builds a coding-agent-ready report summary with diagnostics and links', () => {
    const report: AdminSupportReport = {
      id: 17,
      tenant_id: 2,
      tenant_name: 'hOUR Timebank',
      user_id: 8,
      assigned_user_id: null,
      reference: 'NXR-260527-RAASDS',
      source: 'in_app',
      summary: 'Checkout button does not respond',
      description: 'I tapped the checkout button and nothing happened.',
      impact: 'major',
      status: 'open',
      route: '/hour-timebank/marketplace/42',
      page_url: 'https://app.project-nexus.ie/hour-timebank/marketplace/42',
      sentry_event_id: '9f4f1e3b6d324be8afefb6ad8f8b31d2',
      sentry_issue_url: 'https://sentry.example/issues/123',
      diagnostics: {
        console: [{ level: 'error', message: 'POST /orders failed' }],
      },
      user_agent: 'Vitest',
      triage_notes: 'Reproduced locally.',
      created_at: '2026-05-27T10:30:00.000Z',
      updated_at: '2026-05-27T10:30:00.000Z',
      reporter: {
        id: 8,
        name: 'Ada Lovelace',
        email: 'ada@example.test',
      },
      assignee: null,
    };

    const handoff = buildSupportReportHandoff(report, t);

    expect(handoff).toContain('Support report NXR-260527-RAASDS');
    expect(handoff).toContain('Impact: Major');
    expect(handoff).toContain('Route: /hour-timebank/marketplace/42');
    expect(handoff).toContain('Reporter: Ada Lovelace <ada@example.test> (user 8)');
    expect(handoff).toContain('Sentry issue: https://sentry.example/issues/123');
    expect(handoff).toContain('"message": "POST /orders failed"');
    expect(handoff).toContain('Triage notes:');
    expect(handoff).toContain('Reproduced locally.');
  });
});
