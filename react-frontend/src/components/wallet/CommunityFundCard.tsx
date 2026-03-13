// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CommunityFundCard - Displays community fund balance and recent activity
 */

import { useState, useEffect } from 'react';
import { Button } from '@heroui/react';
import { Landmark, TrendingUp, TrendingDown, Heart } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { CommunityFundBalance } from '@/types/api';

interface CommunityFundCardProps {
  onDonateClick?: () => void;
  compact?: boolean;
}

export function CommunityFundCard({ onDonateClick, compact = false }: CommunityFundCardProps) {
  const { t } = useTranslation('wallet');
  const [fund, setFund] = useState<CommunityFundBalance | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    async function loadFund() {
      try {
        const response = await api.get<CommunityFundBalance>('/v2/wallet/community-fund');
        if (response.success && response.data) {
          setFund(response.data);
        }
      } catch (err) {
        logError('Failed to load community fund', err);
      } finally {
        setIsLoading(false);
      }
    }
    loadFund();
  }, []);

  if (isLoading) {
    return (
      <GlassCard className="p-4 animate-pulse">
        <div className="h-8 bg-theme-hover rounded w-1/3 mb-2" />
        <div className="h-6 bg-theme-hover rounded w-1/2" />
      </GlassCard>
    );
  }

  if (!fund) return null;

  if (compact) {
    return (
      <GlassCard className="p-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="p-2 rounded-lg bg-gradient-to-br from-amber-500/20 to-orange-500/20">
              <Landmark className="w-5 h-5 text-amber-400" />
            </div>
            <div>
              <p className="text-xs text-theme-muted">{t('community_fund')}</p>
              <p className="text-lg font-bold text-theme-primary">{fund.balance}h</p>
            </div>
          </div>
          {onDonateClick && (
            <Button
              size="sm"
              variant="flat"
              className="bg-rose-500/10 text-rose-400"
              startContent={<Heart className="w-3 h-3" />}
              onPress={onDonateClick}
            >
              {t('donate')}
            </Button>
          )}
        </div>
      </GlassCard>
    );
  }

  return (
    <GlassCard className="p-6">
      <div className="flex items-center gap-3 mb-4">
        <div className="p-3 rounded-xl bg-gradient-to-br from-amber-500/20 to-orange-500/20">
          <Landmark className="w-6 h-6 text-amber-400" />
        </div>
        <div>
          <h3 className="text-lg font-semibold text-theme-primary">{t('community_fund')}</h3>
          <p className="text-sm text-theme-muted">{t('community_fund_desc')}</p>
        </div>
      </div>

      <div className="text-center mb-4">
        <p className="text-3xl font-bold text-theme-primary">{fund.balance}</p>
        <p className="text-sm text-theme-muted">{t('community_fund_hours')}</p>
      </div>

      <div className="grid grid-cols-3 gap-3 mb-4">
        <div className="text-center p-2 rounded-lg bg-theme-elevated">
          <TrendingUp className="w-4 h-4 text-emerald-400 mx-auto mb-1" />
          <p className="text-xs text-theme-muted">{t('community_fund_deposited')}</p>
          <p className="text-sm font-semibold text-theme-primary">{fund.total_deposited}h</p>
        </div>
        <div className="text-center p-2 rounded-lg bg-theme-elevated">
          <TrendingDown className="w-4 h-4 text-rose-400 mx-auto mb-1" />
          <p className="text-xs text-theme-muted">{t('community_fund_withdrawn')}</p>
          <p className="text-sm font-semibold text-theme-primary">{fund.total_withdrawn}h</p>
        </div>
        <div className="text-center p-2 rounded-lg bg-theme-elevated">
          <Heart className="w-4 h-4 text-pink-400 mx-auto mb-1" />
          <p className="text-xs text-theme-muted">{t('donated')}</p>
          <p className="text-sm font-semibold text-theme-primary">{fund.total_donated}h</p>
        </div>
      </div>

      {onDonateClick && (
        <Button
          className="w-full bg-gradient-to-r from-rose-500 to-pink-600 text-white"
          startContent={<Heart className="w-4 h-4" />}
          onPress={onDonateClick}
        >
          {t('donate_to_community')}
        </Button>
      )}
    </GlassCard>
  );
}
