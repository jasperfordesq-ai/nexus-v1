// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Accordion,
  AccordionItem,
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Divider,
  Snippet,
  Spinner,
  Tooltip,
} from '@heroui/react';
import ClipboardList from 'lucide-react/icons/clipboard-list';
import ExternalLink from 'lucide-react/icons/external-link';
import FileCode from 'lucide-react/icons/file-code';
import FileJson from 'lucide-react/icons/file-json';
import Info from 'lucide-react/icons/info';
import KeyRound from 'lucide-react/icons/key-round';
import Network from 'lucide-react/icons/network';
import Plug from 'lucide-react/icons/plug';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Webhook from 'lucide-react/icons/webhook';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';

interface ShowcaseItem {
  label: string;
  path: string;
  method: 'GET' | 'POST' | 'PUT' | 'DELETE';
}

interface SamplePayload {
  label: string;
  kind: 'json' | 'curl';
  body: string;
  headers?: string[];
}

interface Section {
  id: string;
  title: string;
  icon: string;
  body: string;
  items?: ShowcaseItem[];
  samples?: SamplePayload[];
  checklist?: string[];
  docs_link?: string;
  sample_request?: { curl: string };
  verification_note?: string;
}

interface Showcase {
  updated_at: string;
  sections: Section[];
}

const ICON_MAP: Record<string, typeof FileJson> = {
  FileJson,
  Plug,
  KeyRound,
  Webhook,
  Network,
  FileCode,
  ClipboardList,
};

const METHOD_COLOUR: Record<string, 'success' | 'primary' | 'warning' | 'danger'> = {
  GET: 'success',
  POST: 'primary',
  PUT: 'warning',
  DELETE: 'danger',
};

export default function IntegrationShowcaseAdminPage() {
  usePageTitle('Integration Showcase');
  const { showToast } = useToast();

  const [data, setData] = useState<Showcase | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<Showcase>('/v2/admin/caring-community/integration-showcase');
      setData(res.data ?? null);
    } catch {
      showToast('Failed to load integration showcase', 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast]);

  useEffect(() => {
    load();
  }, [load]);

  const updatedLabel = useMemo(
    () => (data?.updated_at ? new Date(data.updated_at).toLocaleString() : '—'),
    [data?.updated_at],
  );

  return (
    <div className="space-y-6">
      <PageHeader
        title="Integration Showcase"
        subtitle="A single-page reference of every public API, webhook, federation endpoint, and OAuth surface available in this deployment — ready to hand to a technical integration partner."
        icon={<Plug size={20} />}
        actions={
          <Tooltip content="Refresh">
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              onPress={load}
              isLoading={loading}
              aria-label="Refresh"
            >
              <RefreshCw size={15} />
            </Button>
          </Tooltip>
        }
      />

      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">About this page</p>
              <p className="text-default-600">
                Use this page when setting up an integration with a third-party service, a municipality's data system, or a federated NEXUS network. Every endpoint listed here is live and scoped to this deployment. Sample payloads are illustrative — real values depend on your tenant slug and partner credentials. Share this URL with integration partners rather than pointing them to the raw API docs.
              </p>
              <p className="text-default-500 text-xs">Last refreshed {updatedLabel}.</p>
            </div>
          </div>
        </CardBody>
      </Card>

      {loading && (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      )}

      {!loading && data && (
        <Accordion variant="splitted" selectionMode="multiple" defaultExpandedKeys={['openapi', 'partner_api']}>
          {data.sections.map((section) => {
            const Icon = ICON_MAP[section.icon] ?? Plug;
            return (
              <AccordionItem
                key={section.id}
                aria-label={section.title}
                title={
                  <span className="flex items-center gap-2 font-semibold">
                    <Icon size={16} />
                    {section.title}
                  </span>
                }
              >
                <div className="space-y-4 pb-2">
                  <p className="text-sm text-default-600">{section.body}</p>

                  {section.items && section.items.length > 0 && (
                    <div className="space-y-2">
                      {section.items.map((item, i) => (
                        <div
                          key={`${section.id}-item-${i}`}
                          className="flex items-center gap-3 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] px-3 py-2"
                        >
                          <Chip
                            size="sm"
                            color={METHOD_COLOUR[item.method] ?? 'default'}
                            variant="flat"
                            className="font-mono text-xs"
                          >
                            {item.method}
                          </Chip>
                          <code className="flex-1 text-xs font-mono text-default-700">{item.path}</code>
                          <span className="text-xs text-default-500">{item.label}</span>
                        </div>
                      ))}
                    </div>
                  )}

                  {section.sample_request && (
                    <Card className="border border-[var(--color-border)]">
                      <CardHeader className="pb-1">
                        <span className="text-xs font-semibold">Sample request</span>
                      </CardHeader>
                      <CardBody className="pt-0">
                        <Snippet
                          variant="flat"
                          symbol=""
                          className="text-xs whitespace-pre w-full"
                        >
                          {section.sample_request.curl}
                        </Snippet>
                      </CardBody>
                    </Card>
                  )}

                  {section.verification_note && (
                    <Card className="border border-warning bg-warning/10">
                      <CardBody className="py-2 text-xs">{section.verification_note}</CardBody>
                    </Card>
                  )}

                  {section.samples && section.samples.length > 0 && (
                    <div className="space-y-3">
                      {section.samples.map((sample, i) => (
                        <Card key={`${section.id}-sample-${i}`} className="border border-[var(--color-border)]">
                          <CardHeader className="pb-1 flex items-center justify-between">
                            <span className="text-xs font-semibold">{sample.label}</span>
                            <Chip size="sm" variant="flat">{sample.kind.toUpperCase()}</Chip>
                          </CardHeader>
                          <CardBody className="pt-0 space-y-2">
                            {sample.headers && sample.headers.length > 0 && (
                              <div className="rounded bg-[var(--color-surface-alt)] p-2 font-mono text-[11px] text-default-600">
                                {sample.headers.map((h, j) => (
                                  <div key={`${section.id}-hdr-${i}-${j}`}>{h}</div>
                                ))}
                              </div>
                            )}
                            <Snippet
                              variant="flat"
                              symbol=""
                              className="text-xs whitespace-pre w-full overflow-auto"
                            >
                              {sample.body}
                            </Snippet>
                          </CardBody>
                        </Card>
                      ))}
                    </div>
                  )}

                  {section.checklist && section.checklist.length > 0 && (
                    <ul className="list-disc pl-5 text-sm text-default-700 space-y-1">
                      {section.checklist.map((c, i) => (
                        <li key={`${section.id}-cl-${i}`}>{c}</li>
                      ))}
                    </ul>
                  )}

                  {section.docs_link && (
                    <>
                      <Divider />
                      <a
                        href={section.docs_link}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                      >
                        Read the full spec on GitHub <ExternalLink size={12} />
                      </a>
                    </>
                  )}
                </div>
              </AccordionItem>
            );
          })}
        </Accordion>
      )}
    </div>
  );
}
