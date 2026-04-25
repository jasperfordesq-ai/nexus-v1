// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * RecommendedShiftsTab - Skills-based shift recommendations (V4)
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Chip,
  Progress,
} from '@heroui/react';
import Sparkles from 'lucide-react/icons/sparkles';
import MapPin from 'lucide-react/icons/map-pin';
import Calendar from 'lucide-react/icons/calendar';
import Clock from 'lucide-react/icons/clock';
import Building2 from 'lucide-react/icons/building-2';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import Target from 'lucide-react/icons/target';
import Zap from 'lucide-react/icons/zap';
import ExternalLink from 'lucide-react/icons/external-link';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

interface RecommendedShift {
  shift: {
    id: number;
    start_time: string;
    end_time: string;
    capacity: number | null;
    required_skills: string[];
  };
  opportunity: {
    id: number;
    title: string;
    location: string;
    skills_needed: string;
  };
  organization: {
    name: string;
    logo_url: string | null;
  };
  match_score: number;
  match_reasons: string[];
}

export function RecommendedShiftsTab() {
  const { t } = useTranslation('volunteering');
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const [shifts, setShifts] = useState<RecommendedShift[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // AbortController ref to cancel stale requests
  const abortRef = useRef<AbortController | null>(null);

  // Stable ref for t — avoids re-creating callbacks when i18n namespace loads
  const tRef = useRef(t);
  tRef.current = t;

  const load = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    try {
      setIsLoading(true);
      setError(null);

      const response = await api.get<{ shifts?: RecommendedShift[] }>(
        '/v2/volunteering/recommended-shifts?limit=10'
      );

      if (controller.signal.aborted) return;
      if (response.success && response.data) {
        const payload = response.data as { shifts?: RecommendedShift[] } | RecommendedShift[];
        setShifts(Array.isArray(payload) ? payload : (payload.shifts ?? []));
      } else {
        setError(tRef.current('recommendations.error_load', 'Failed to load recommendations'));
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load recommended shifts', err);
      setError(tRef.current('recommendations.error_load_generic', 'Unable to load recommendations. Please try again.'));
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const getMatchColor = (score: number): 'success' | 'warning' | 'primary' | 'default' => {
    if (score >= 75) return 'success';
    if (score >= 50) return 'primary';
    if (score >= 30) return 'warning';
    return 'default';
  };

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.05 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Sparkles className="w-5 h-5 text-amber-400" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary">{t('recommendations.title', 'Recommended for You')}</h2>
        </div>
        <Button
          size="sm"
          variant="flat"
          className="bg-theme-elevated text-theme-muted"
          startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
          onPress={load}
          isLoading={isLoading}
        >
          {t('common.refresh', 'Refresh')}
        </Button>
      </div>

      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-[var(--color-warning)] mx-auto mb-4" aria-hidden="true" />
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
            onPress={load}
          >
            {t('common.try_again', 'Try Again')}
          </Button>
        </GlassCard>
      )}

      {!error && isLoading && (
        <div className="space-y-4">
          {[1, 2, 3].map((i) => (
            <GlassCard key={i} className="p-5 animate-pulse">
              <div className="h-5 bg-theme-hover rounded w-1/3 mb-3" />
              <div className="h-3 bg-theme-hover rounded w-full mb-3" />
              <div className="h-3 bg-theme-hover rounded w-2/3" />
            </GlassCard>
          ))}
        </div>
      )}

      {!error && !isLoading && shifts.length === 0 && (
        <EmptyState
          icon={<Target className="w-12 h-12" aria-hidden="true" />}
          title={t('recommendations.empty_title', 'No recommendations yet')}
          description={t('recommendations.empty_description', 'Add skills to your profile to get personalized shift recommendations.')}
        />
      )}

      {!error && !isLoading && shifts.length > 0 && (
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="space-y-4"
        >
          {shifts.map((item) => (
            <motion.div key={item.shift.id} variants={itemVariants}>
              <GlassCard className="p-5">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-2 flex-wrap">
                      <h3 className="font-semibold text-theme-primary">{item.opportunity.title}</h3>
                      <Chip
                        size="sm"
                        color={getMatchColor(item.match_score)}
                        variant="flat"
                        startContent={<Zap className="w-3 h-3" />}
                      >
                        {t('recommendations.match_score', '{{score}}% match', { score: item.match_score })}
                      </Chip>
                    </div>

                    <p className="text-sm text-theme-muted flex items-center gap-1 mb-2">
                      <Building2 className="w-3 h-3" aria-hidden="true" />
                      {item.organization.name}
                    </p>

                    <div className="flex flex-wrap items-center gap-3 text-xs text-theme-subtle mb-2">
                      {item.opportunity.location && (
                        <span className="flex items-center gap-1">
                          <MapPin className="w-3 h-3" aria-hidden="true" />
                          {item.opportunity.location}
                        </span>
                      )}
                      <span className="flex items-center gap-1">
                        <Calendar className="w-3 h-3" aria-hidden="true" />
                        {new Date(item.shift.start_time).toLocaleDateString()}
                      </span>
                      <span className="flex items-center gap-1">
                        <Clock className="w-3 h-3" aria-hidden="true" />
                        {new Date(item.shift.start_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                        {' - '}
                        {new Date(item.shift.end_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                      </span>
                    </div>

                    {item.match_reasons.length > 0 && (
                      <div className="flex flex-wrap gap-1 mt-2">
                        {item.match_reasons.map((reason, i) => (
                          <Chip key={i} size="sm" variant="flat" className="text-theme-subtle text-xs">
                            {reason}
                          </Chip>
                        ))}
                      </div>
                    )}

                    {item.shift.required_skills.length > 0 && (
                      <div className="flex flex-wrap gap-1 mt-2">
                        {item.shift.required_skills.map((skill, i) => (
                          <Chip key={i} size="sm" variant="dot" color="primary" className="text-xs">
                            {skill}
                          </Chip>
                        ))}
                      </div>
                    )}
                  </div>

                  <div className="flex-shrink-0 flex flex-col items-center gap-2">
                    <div className="w-14 h-14 relative">
                      <Progress
                        value={item.match_score}
                        classNames={{
                          indicator: item.match_score >= 75
                            ? 'bg-emerald-500'
                            : item.match_score >= 50
                              ? 'bg-indigo-500'
                              : 'bg-amber-500',
                          track: 'bg-theme-hover',
                        }}
                        size="md"
                        aria-label={`${item.match_score}% match`}
                      />
                    </div>
                    <Button
                      size="sm"
                      className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
                      endContent={<ExternalLink className="w-3 h-3" aria-hidden="true" />}
                      onPress={() => navigate(tenantPath(`/volunteering/opportunities/${item.opportunity.id}`))}
                    >
                      {t('recommendations.view_opportunity', 'View')}
                    </Button>
                  </div>
                </div>
              </GlassCard>
            </motion.div>
          ))}
        </motion.div>
      )}
    </div>
  );
}

export default RecommendedShiftsTab;
