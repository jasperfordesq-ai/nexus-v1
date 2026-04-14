// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useCallback } from 'react';
import { Button, Select, SelectItem, Input } from '@heroui/react';
import { Zap, ChevronUp, ChevronDown } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { PipelineRule } from './JobDetailTypes';

interface JobPipelineRulesProps {
  jobId: string;
}

export function JobPipelineRules({ jobId }: JobPipelineRulesProps) {
  const { t } = useTranslation('jobs');
  const [pipelineOpen, setPipelineOpen] = useState(false);
  const [pipelineRules, setPipelineRules] = useState<PipelineRule[]>([]);
  const [newRule, setNewRule] = useState({
    name: '',
    trigger_stage: 'applied',
    condition_days: 7,
    action: 'move_stage',
    action_target: 'screening',
  });
  const [isAddingRule, setIsAddingRule] = useState(false);

  const loadPipelineRules = useCallback(async () => {
    try {
      const res = await api.get<{ data: PipelineRule[] }>(`/v2/jobs/${jobId}/pipeline-rules`);
      if (res.success && res.data) setPipelineRules((res.data as { data: PipelineRule[] }).data ?? []);
    } catch (err) {
      logError('Failed to load pipeline rules', err);
    }
  }, [jobId]);

  return (
    <GlassCard className="p-4">
      <button
        className="w-full flex items-center justify-between"
        onClick={() => {
          setPipelineOpen((v) => !v);
          if (!pipelineOpen) loadPipelineRules();
        }}
        aria-expanded={pipelineOpen}
      >
        <span className="font-semibold flex items-center gap-2">
          <Zap size={16} aria-hidden="true" />
          {t('pipeline.title', 'Automation Rules')}
        </span>
        {pipelineOpen ? <ChevronUp size={16} aria-hidden="true" /> : <ChevronDown size={16} aria-hidden="true" />}
      </button>
      {pipelineOpen && (
        <div className="mt-3 space-y-3">
          {pipelineRules.length === 0 && (
            <p className="text-sm text-theme-muted">{t('pipeline.no_rules', 'No automation rules yet.')}</p>
          )}
          {pipelineRules.map((rule) => (
            <div key={rule.id} className="flex items-center justify-between text-sm p-2 bg-white/5 rounded-lg">
              <div>
                <span className="font-medium">{rule.name}</span>
                <span className="text-theme-muted ml-2 text-xs">
                  If in &ldquo;{rule.trigger_stage}&rdquo; for {rule.condition_days}d &rarr; {rule.action}{rule.action_target ? ` \u2192 ${rule.action_target}` : ''}
                </span>
              </div>
              <Button
                size="sm"
                color="danger"
                variant="flat"
                onPress={() =>
                  api.delete(`/v2/jobs/pipeline-rules/${rule.id}`)
                    .then(() => loadPipelineRules())
                    .catch((err) => { if (import.meta.env.DEV) console.warn('Non-critical:', err); })
                }
              >
                {t('pipeline.delete', 'Delete')}
              </Button>
            </div>
          ))}
          <div className="border-t border-divider pt-3">
            <p className="text-xs font-medium text-theme-muted mb-2">{t('pipeline.add_rule', 'Add rule')}</p>
            <div className="grid grid-cols-2 gap-2">
              <Select
                size="sm"
                label={t('pipeline.trigger', 'If in stage')}
                selectedKeys={[newRule.trigger_stage]}
                onSelectionChange={(keys) =>
                  setNewRule((r) => ({ ...r, trigger_stage: Array.from(keys)[0] as string }))
                }
              >
                {(['applied', 'screening', 'reviewed', 'interview'] as const).map((s) => (
                  <SelectItem key={s}>{s}</SelectItem>
                ))}
              </Select>
              <Input
                size="sm"
                type="number"
                label={t('pipeline.days', 'Days')}
                value={String(newRule.condition_days)}
                onChange={(e) =>
                  setNewRule((r) => ({ ...r, condition_days: parseInt(e.target.value) || 7 }))
                }
              />
              <Select
                size="sm"
                label={t('pipeline.action', 'Action')}
                selectedKeys={[newRule.action]}
                onSelectionChange={(keys) =>
                  setNewRule((r) => ({ ...r, action: Array.from(keys)[0] as string }))
                }
              >
                <SelectItem key="move_stage">{t('pipeline.action_move_stage', 'Move stage')}</SelectItem>
                <SelectItem key="reject">{t('pipeline.action_auto_reject', 'Auto-reject')}</SelectItem>
                <SelectItem key="notify_reviewer">{t('pipeline.action_notify_me', 'Notify me')}</SelectItem>
              </Select>
              {newRule.action === 'move_stage' && (
                <Select
                  size="sm"
                  label={t('pipeline.target', 'Move to')}
                  selectedKeys={[newRule.action_target]}
                  onSelectionChange={(keys) =>
                    setNewRule((r) => ({ ...r, action_target: Array.from(keys)[0] as string }))
                  }
                >
                  {(['screening', 'reviewed', 'interview', 'rejected'] as const).map((s) => (
                    <SelectItem key={s}>{s}</SelectItem>
                  ))}
                </Select>
              )}
            </div>
            <Button
              size="sm"
              color="primary"
              className="mt-2"
              isLoading={isAddingRule}
              onPress={async () => {
                setIsAddingRule(true);
                try {
                  await api.post(`/v2/jobs/${jobId}/pipeline-rules`, {
                    ...newRule,
                    name: `${newRule.trigger_stage} \u2192 ${newRule.action_target || newRule.action} after ${newRule.condition_days}d`,
                  });
                  await loadPipelineRules();
                } catch (err) {
                  logError('Failed to add pipeline rule', err);
                } finally {
                  setIsAddingRule(false);
                }
              }}
            >
              {t('pipeline.add', 'Add Rule')}
            </Button>
          </div>
        </div>
      )}
    </GlassCard>
  );
}
