// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useCallback } from 'react';
import { Button, Select, SelectItem, Input } from '@heroui/react';
import Zap from 'lucide-react/icons/zap';
import ChevronUp from 'lucide-react/icons/chevron-up';
import ChevronDown from 'lucide-react/icons/chevron-down';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { PipelineRule } from './JobDetailTypes';

interface JobPipelineRulesProps {
  jobId: string;
}

const PIPELINE_STAGES = ['applied', 'screening', 'reviewed', 'interview', 'rejected'] as const;
const TRIGGER_STAGES = PIPELINE_STAGES.filter(stage => stage !== 'rejected');
const TARGET_STAGES = PIPELINE_STAGES.filter(stage => stage !== 'applied');

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

  const stageLabel = useCallback((stage: string) => t(`pipeline.${stage}`), [t]);
  const actionLabel = useCallback((action: string) => {
    if (action === 'reject') return t('pipeline.action_auto_reject');
    if (action === 'notify_reviewer') return t('pipeline.action_notify_me');
    return t(`pipeline.action_${action}`);
  }, [t]);

  const loadPipelineRules = useCallback(async () => {
    try {
      const res = await api.get<PipelineRule[]>(`/v2/jobs/${jobId}/pipeline-rules`);
      if (res.success && Array.isArray(res.data)) {
        setPipelineRules(res.data);
      } else {
        setPipelineRules([]);
      }
    } catch (err) {
      setPipelineRules([]);
      logError('Failed to load pipeline rules', err);
    }
  }, [jobId]);

  return (
    <GlassCard className="p-4">
      <Button
        variant="light"
        onPress={() => {
          setPipelineOpen((v) => !v);
          if (!pipelineOpen) loadPipelineRules();
        }}
        aria-expanded={pipelineOpen}
        className="w-full flex items-center justify-between h-auto p-0 justify-between"
      >
        <span className="font-semibold flex items-center gap-2">
          <Zap size={16} aria-hidden="true" />
          {t('pipeline.rules_title')}
        </span>
        {pipelineOpen ? <ChevronUp size={16} aria-hidden="true" /> : <ChevronDown size={16} aria-hidden="true" />}
      </Button>
      {pipelineOpen && (
        <div className="mt-3 space-y-3">
          {pipelineRules.length === 0 && (
            <p className="text-sm text-theme-muted">{t('pipeline.no_rules')}</p>
          )}
          {pipelineRules.map((rule) => (
            <div key={rule.id} className="flex items-center justify-between text-sm p-2 bg-white/5 rounded-lg">
              <div>
                <span className="font-medium">{rule.name}</span>
                <span className="text-theme-muted ml-2 text-xs">
                  {t(rule.action_target ? 'pipeline.rule_summary_with_target' : 'pipeline.rule_summary', {
                    stage: rule.trigger_stage,
                    count: rule.condition_days,
                    action: actionLabel(rule.action),
                    target: rule.action_target ? stageLabel(rule.action_target) : '',
                  })}
                </span>
              </div>
              <Button
                size="sm"
                color="danger"
                variant="flat"
                onPress={() =>
                  api.delete(`/v2/jobs/pipeline-rules/${rule.id}`)
                    .then((res) => {
                      if (res.success) {
                        loadPipelineRules();
                      }
                    })
                    .catch((err) => { if (import.meta.env.DEV) console.warn('Non-critical:', err); })
                }
              >
                {t('pipeline.delete')}
              </Button>
            </div>
          ))}
          <div className="border-t border-divider pt-3">
            <p className="text-xs font-medium text-theme-muted mb-2">{t('pipeline.add_rule')}</p>
            <div className="grid grid-cols-2 gap-2">
              <Select
                size="sm"
                label={t('pipeline.trigger')}
                selectedKeys={[newRule.trigger_stage]}
                onSelectionChange={(keys) =>
                  setNewRule((r) => ({ ...r, trigger_stage: Array.from(keys)[0] as string }))
                }
              >
                {TRIGGER_STAGES.map((stage) => (
                  <SelectItem key={stage}>{stageLabel(stage)}</SelectItem>
                ))}
              </Select>
              <Input
                size="sm"
                type="number"
                label={t('pipeline.days')}
                value={String(newRule.condition_days)}
                onChange={(e) =>
                  setNewRule((r) => ({ ...r, condition_days: parseInt(e.target.value) || 7 }))
                }
              />
              <Select
                size="sm"
                label={t('pipeline.action')}
                selectedKeys={[newRule.action]}
                onSelectionChange={(keys) =>
                  setNewRule((r) => ({ ...r, action: Array.from(keys)[0] as string }))
                }
              >
                <SelectItem key="move_stage">{t('pipeline.action_move_stage')}</SelectItem>
                <SelectItem key="reject">{t('pipeline.action_auto_reject')}</SelectItem>
                <SelectItem key="notify_reviewer">{t('pipeline.action_notify_me')}</SelectItem>
              </Select>
              {newRule.action === 'move_stage' && (
                <Select
                  size="sm"
                  label={t('pipeline.target')}
                  selectedKeys={[newRule.action_target]}
                  onSelectionChange={(keys) =>
                    setNewRule((r) => ({ ...r, action_target: Array.from(keys)[0] as string }))
                  }
                >
                  {TARGET_STAGES.map((stage) => (
                    <SelectItem key={stage}>{stageLabel(stage)}</SelectItem>
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
                    name: t('pipeline.generated_name', {
                      stage: stageLabel(newRule.trigger_stage),
                      target: newRule.action_target ? stageLabel(newRule.action_target) : actionLabel(newRule.action),
                      count: newRule.condition_days,
                    }),
                  });
                  await loadPipelineRules();
                } catch (err) {
                  logError('Failed to add pipeline rule', err);
                } finally {
                  setIsAddingRule(false);
                }
              }}
            >
              {t('pipeline.add')}
            </Button>
          </div>
        </div>
      )}
    </GlassCard>
  );
}
