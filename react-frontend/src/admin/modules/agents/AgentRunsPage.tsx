// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Button,
  Card,
  CardBody,
  Chip,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
} from '@heroui/react';
import Bot from 'lucide-react/icons/bot';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { useAdminPageMeta } from '../../AdminMetaContext';
import { EmptyState } from '../../components/EmptyState';
import { PageHeader } from '../../components/PageHeader';

interface AgentRun {
  id: number;
  tenant_id: number;
  agent_type: string;
  agent_definition_id: number | null;
  status: string;
  triggered_by: string | null;
  proposals_generated: number;
  proposals_applied: number;
  llm_input_tokens: number;
  llm_output_tokens: number;
  cost_cents: number;
  error_message: string | null;
  output_summary: string | null;
  started_at: string | null;
  completed_at: string | null;
}

export default function AgentRunsPage() {
  const { t } = useTranslation('admin');
  usePageTitle(t('agents.runs.meta.title'));
  useAdminPageMeta({
    title: t('agents.runs.meta.title'),
    description: t('agents.runs.meta.description'),
  });
  const toast = useToast();

  const [items, setItems] = useState<AgentRun[]>([]);
  const [loading, setLoading] = useState(false);
  const [expanded, setExpanded] = useState<number | null>(null);

  const fetchItems = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<{ items: AgentRun[] }>(
        '/v2/admin/agents/runs?per_page=50'
      );
      setItems(res.data?.items ?? []);
    } catch {
      toast.error(t('agents.runs.toasts.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [t, toast]);

  useEffect(() => {
    void fetchItems();
  }, [fetchItems]);

  const statusColor = (s: string): 'success' | 'warning' | 'danger' | 'default' => {
    if (s === 'completed') return 'success';
    if (s === 'running') return 'warning';
    if (s === 'failed') return 'danger';
    return 'default';
  };

  return (
    <div className="p-6 space-y-6">
      <PageHeader
        title={t('agents.runs.title')}
        description={t('agents.runs.subtitle')}
        icon={<Bot className="h-5 w-5" />}
        actions={(
          <Button variant="flat" onPress={() => void fetchItems()} isLoading={loading}>
            {t('agents.actions.refresh')}
          </Button>
        )}
      />

      {loading && (
        <Card shadow="sm" className="border border-divider/70 bg-content1">
          <CardBody className="py-8 text-sm text-default-500">
            {t('agents.runs.loading')}
          </CardBody>
        </Card>
      )}

      {!loading && items.length === 0 && (
        <EmptyState
          icon={Bot}
          title={t('agents.runs.empty.title')}
          description={t('agents.runs.empty.description')}
        />
      )}

      {!loading && items.length > 0 && (
        <Table aria-label={t('agents.runs.table_aria')}>
          <TableHeader>
            <TableColumn>{t('agents.runs.columns.id')}</TableColumn>
            <TableColumn>{t('agents.runs.columns.agent')}</TableColumn>
            <TableColumn>{t('agents.runs.columns.started')}</TableColumn>
            <TableColumn>{t('agents.runs.columns.status')}</TableColumn>
            <TableColumn>{t('agents.runs.columns.proposals')}</TableColumn>
            <TableColumn>{t('agents.runs.columns.tokens')}</TableColumn>
            <TableColumn>{t('agents.runs.columns.cost')}</TableColumn>
            <TableColumn>{t('agents.runs.columns.triggered')}</TableColumn>
          </TableHeader>
          <TableBody>
            {items.flatMap((r) => {
              const rows = [
                <TableRow
                  key={`run-${r.id}`}
                  className="cursor-pointer"
                  onClick={() => setExpanded(expanded === r.id ? null : r.id)}
                >
                  <TableCell>{r.id}</TableCell>
                  <TableCell>{r.agent_type}</TableCell>
                  <TableCell>
                    {r.started_at ? new Date(r.started_at).toLocaleString() : t('agents.common.empty_dash')}
                  </TableCell>
                  <TableCell>
                    <Chip size="sm" variant="flat" color={statusColor(r.status)}>
                      {t(`agents.run_status.${r.status}`, r.status)}
                    </Chip>
                  </TableCell>
                  <TableCell>
                    {t('agents.runs.proposals_cell', {
                      generated: r.proposals_generated,
                      applied: r.proposals_applied,
                    })}
                  </TableCell>
                  <TableCell>{r.llm_input_tokens} / {r.llm_output_tokens}</TableCell>
                  <TableCell>${(r.cost_cents / 100).toFixed(4)}</TableCell>
                  <TableCell>{r.triggered_by ?? t('agents.common.empty_dash')}</TableCell>
                </TableRow>,
              ];
              if (expanded === r.id) {
                rows.push(
                  <TableRow key={`detail-${r.id}`}>
                    <TableCell colSpan={8} className="bg-default-50">
                      <div className="space-y-2 py-2">
                        {r.error_message && (
                          <div>
                            <p className="text-xs font-semibold uppercase text-danger">{t('agents.runs.detail.error')}</p>
                            <pre className="text-xs whitespace-pre-wrap">{r.error_message}</pre>
                          </div>
                        )}
                        {r.output_summary && (
                          <div>
                            <p className="text-xs font-semibold uppercase text-default-500">{t('agents.runs.detail.summary')}</p>
                            <p className="text-sm">{r.output_summary}</p>
                          </div>
                        )}
                        {r.completed_at && (
                          <p className="text-xs text-default-500">
                            {t('agents.runs.detail.completed', {
                              date: new Date(r.completed_at).toLocaleString(),
                            })}
                          </p>
                        )}
                      </div>
                    </TableCell>
                  </TableRow>
                );
              }
              return rows;
            })}
          </TableBody>
        </Table>
      )}
    </div>
  );
}
