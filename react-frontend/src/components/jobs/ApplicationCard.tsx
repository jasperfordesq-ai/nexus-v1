// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import { motion } from 'framer-motion';
import { Button, Chip, Avatar } from '@heroui/react';
import {
  CheckCircle,
  XCircle,
  ChevronRight,
  History,
  MessageCircle,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { resolveAvatarUrl } from '@/lib/helpers';
import { api } from '@/lib/api';
import type { Application, HistoryEntry } from './JobDetailTypes';
import { STATUS_COLORS } from './JobDetailTypes';

interface ApplicationCardProps {
  application: Application;
  onUpdateStatus: (applicationId: number, status: string) => void;
  tenantPathFn: (path: string) => string;
  navigateFn: (path: string) => void;
}

export function ApplicationCard({ application, onUpdateStatus, tenantPathFn, navigateFn }: ApplicationCardProps) {
  const { t } = useTranslation('jobs');
  const [showHistory, setShowHistory] = useState(false);
  const [history, setHistory] = useState<HistoryEntry[]>([]);
  const [isLoadingHistory, setIsLoadingHistory] = useState(false);

  const currentStage = application.stage ?? application.status;

  const handleToggleHistory = async () => {
    if (!showHistory && history.length === 0) {
      setIsLoadingHistory(true);
      try {
        const response = await api.get<HistoryEntry[]>(`/v2/jobs/applications/${application.id}/history`);
        if (response.success && response.data) {
          setHistory(response.data);
        }
      } catch {
        // Non-critical
      } finally {
        setIsLoadingHistory(false);
      }
    }
    setShowHistory(!showHistory);
  };

  const getAvailableStages = () => {
    const stages: string[] = [];
    switch (currentStage) {
      case 'applied':
        stages.push('screening', 'interview', 'accepted', 'rejected');
        break;
      case 'screening':
        stages.push('interview', 'accepted', 'rejected');
        break;
      case 'interview':
        stages.push('offer', 'accepted', 'rejected');
        break;
      case 'offer':
        stages.push('accepted', 'rejected');
        break;
      case 'reviewed':
        stages.push('screening', 'interview', 'accepted', 'rejected');
        break;
      default:
        break;
    }
    return stages;
  };

  const availableStages = getAvailableStages();

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      className="p-4 rounded-lg bg-theme-elevated border border-theme-default"
    >
      <div className="flex items-start gap-3">
        <Avatar
          name={application.applicant.name}
          src={resolveAvatarUrl(application.applicant.avatar_url)}
          size="sm"
          isBordered
        />
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap">
            <p className="font-medium text-theme-primary">{application.applicant.name}</p>
            <Chip
              size="sm"
              variant="flat"
              color={STATUS_COLORS[currentStage] ?? 'default'}
            >
              {t(`application_status.${currentStage}`)}
            </Chip>
          </div>
          {application.applicant.email && (
            <p className="text-xs text-theme-subtle">{application.applicant.email}</p>
          )}
          <p className="text-xs text-theme-subtle mt-1">
            {new Date(application.created_at).toLocaleDateString()}
          </p>
          {application.message && (
            <p className="text-sm text-theme-secondary mt-2 whitespace-pre-wrap">
              {application.message}
            </p>
          )}
          {application.reviewer_notes && (
            <div className="mt-2 p-2 rounded bg-theme-hover text-sm">
              <span className="font-medium text-theme-subtle">{t('detail.reviewer_notes')}: </span>
              <span className="text-theme-secondary">{application.reviewer_notes}</span>
            </div>
          )}
        </div>
      </div>

      {/* Pipeline stage action buttons */}
      {availableStages.length > 0 && (
        <div className="flex gap-2 mt-3 flex-wrap justify-end">
          {availableStages.map((stage) => (
            <Button
              key={stage}
              size="sm"
              variant="flat"
              color={STATUS_COLORS[stage] ?? 'default'}
              startContent={
                stage === 'accepted' ? <CheckCircle className="w-3.5 h-3.5" aria-hidden="true" /> :
                stage === 'rejected' ? <XCircle className="w-3.5 h-3.5" aria-hidden="true" /> :
                <ChevronRight className="w-3.5 h-3.5" aria-hidden="true" />
              }
              onPress={() => onUpdateStatus(application.id, stage)}
            >
              {t(`application_status.${stage}`)}
            </Button>
          ))}
        </div>
      )}

      {/* Message applicant button */}
      <div className="mt-2 flex items-center gap-3">
        <Button
          size="sm"
          variant="flat"
          className="bg-theme-elevated text-theme-muted"
          startContent={<MessageCircle size={13} aria-hidden="true" />}
          onPress={() => navigateFn(tenantPathFn(`/messages?user=${application.applicant.id}&context=job&context_id=${application.vacancy_id}`))}
        >
          {t('detail.message_applicant', 'Message')}
        </Button>
      </div>

      {/* History toggle */}
      <div className="mt-2">
        <Button
          variant="light"
          size="sm"
          onPress={handleToggleHistory}
          className="text-xs text-theme-subtle hover:text-theme-primary flex items-center gap-1 transition-colors h-auto p-0 min-w-0"
          startContent={<History className="w-3 h-3" aria-hidden="true" />}
        >
          {t('history.title')}
        </Button>
        {showHistory && (
          <div className="mt-2 pl-4 border-l-2 border-theme-default space-y-2">
            {isLoadingHistory ? (
              <div className="h-4 bg-theme-hover rounded w-3/4 animate-pulse" />
            ) : history.length === 0 ? (
              <p className="text-xs text-theme-subtle">{t('history.empty')}</p>
            ) : (
              history.map((entry) => (
                <div key={entry.id} className="text-xs text-theme-subtle">
                  <span className="text-theme-muted">
                    {entry.from_status
                      ? `${t(`application_status.${entry.from_status}`)} → ${t(`application_status.${entry.to_status}`)}`
                      : t('history.initial')}
                  </span>
                  {entry.changed_by_name && (
                    <span> - {entry.changed_by_name}</span>
                  )}
                  <span className="ml-2">{new Date(entry.changed_at).toLocaleString()}</span>
                  {entry.notes && (
                    <p className="text-theme-subtle mt-0.5 italic">{entry.notes}</p>
                  )}
                </div>
              ))
            )}
          </div>
        )}
      </div>
    </motion.div>
  );
}
