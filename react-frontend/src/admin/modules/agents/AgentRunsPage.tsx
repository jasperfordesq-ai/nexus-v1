// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AG61 — Agent Runs admin page.
 * ADMIN IS ENGLISH-ONLY — NO t() calls.
 */

import { useCallback, useEffect, useState } from 'react';
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
  usePageTitle('AI Agent Runs');
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
      toast.error('Failed to load runs');
    } finally {
      setLoading(false);
    }
  }, [toast]);

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
      <div className="flex items-center gap-3 justify-between">
        <div className="flex items-center gap-3">
          <Bot className="w-7 h-7 text-primary" />
          <div>
            <h1 className="text-2xl font-bold">Agent Runs</h1>
            <p className="text-sm text-default-500">
              History of every agent execution. Click a row to expand error details.
            </p>
          </div>
        </div>
        <Button variant="flat" onPress={fetchItems} isLoading={loading}>Refresh</Button>
      </div>

      {!loading && items.length === 0 && (
        <Card><CardBody className="text-default-500">No runs yet.</CardBody></Card>
      )}

      {items.length > 0 && (
        <Table aria-label="Agent runs">
          <TableHeader>
            <TableColumn>ID</TableColumn>
            <TableColumn>Agent</TableColumn>
            <TableColumn>Started</TableColumn>
            <TableColumn>Status</TableColumn>
            <TableColumn>Proposals</TableColumn>
            <TableColumn>Tokens (in/out)</TableColumn>
            <TableColumn>Cost</TableColumn>
            <TableColumn>Triggered</TableColumn>
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
                    {r.started_at ? new Date(r.started_at).toLocaleString() : '—'}
                  </TableCell>
                  <TableCell>
                    <Chip size="sm" variant="flat" color={statusColor(r.status)}>{r.status}</Chip>
                  </TableCell>
                  <TableCell>{r.proposals_generated} ({r.proposals_applied} applied)</TableCell>
                  <TableCell>{r.llm_input_tokens} / {r.llm_output_tokens}</TableCell>
                  <TableCell>${(r.cost_cents / 100).toFixed(4)}</TableCell>
                  <TableCell>{r.triggered_by ?? '—'}</TableCell>
                </TableRow>,
              ];
              if (expanded === r.id) {
                rows.push(
                  <TableRow key={`detail-${r.id}`}>
                    <TableCell colSpan={8} className="bg-default-50">
                      <div className="space-y-2 py-2">
                        {r.error_message && (
                          <div>
                            <p className="text-xs font-semibold uppercase text-danger">Error</p>
                            <pre className="text-xs whitespace-pre-wrap">{r.error_message}</pre>
                          </div>
                        )}
                        {r.output_summary && (
                          <div>
                            <p className="text-xs font-semibold uppercase text-default-500">Summary</p>
                            <p className="text-sm">{r.output_summary}</p>
                          </div>
                        )}
                        {r.completed_at && (
                          <p className="text-xs text-default-500">
                            Completed: {new Date(r.completed_at).toLocaleString()}
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
