/**
 * Exchanges Page - View and manage exchange requests
 */

import { useState, useEffect } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Avatar, Chip, Tabs, Tab } from '@heroui/react';
import {
  ArrowRightLeft,
  Clock,
  CheckCircle,
  XCircle,
  AlertTriangle,
  Calendar,
  User,
  Plus,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { LoadingScreen, EmptyState } from '@/components/feedback';
import { useAuth } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import type { Exchange, ExchangeStatus, ExchangeConfig } from '@/types/api';

const STATUS_CONFIG: Record<ExchangeStatus, { label: string; color: string; icon: typeof Clock }> = {
  pending_provider: { label: 'Awaiting Provider', color: 'warning', icon: Clock },
  pending_broker: { label: 'Awaiting Broker', color: 'secondary', icon: Clock },
  accepted: { label: 'Accepted', color: 'primary', icon: CheckCircle },
  in_progress: { label: 'In Progress', color: 'primary', icon: ArrowRightLeft },
  pending_confirmation: { label: 'Confirm Hours', color: 'warning', icon: AlertTriangle },
  completed: { label: 'Completed', color: 'success', icon: CheckCircle },
  disputed: { label: 'Disputed', color: 'danger', icon: AlertTriangle },
  cancelled: { label: 'Cancelled', color: 'default', icon: XCircle },
};

export function ExchangesPage() {
  const { user } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();

  const [exchanges, setExchanges] = useState<Exchange[]>([]);
  const [config, setConfig] = useState<ExchangeConfig | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [selectedTab, setSelectedTab] = useState(searchParams.get('status') || 'active');

  useEffect(() => {
    loadConfig();
    loadExchanges();
  }, [selectedTab]);

  async function loadConfig() {
    try {
      const response = await api.get<ExchangeConfig>('/v2/exchanges/config');
      if (response.success && response.data) {
        setConfig(response.data);
      }
    } catch (err) {
      logError('Failed to load exchange config', err);
    }
  }

  async function loadExchanges() {
    try {
      setIsLoading(true);
      const queryString = selectedTab !== 'all' ? `?status=${selectedTab}` : '';
      const response = await api.get<Exchange[]>(`/v2/exchanges${queryString}`);
      if (response.success && response.data) {
        setExchanges(response.data);
      }
    } catch (err) {
      logError('Failed to load exchanges', err);
    } finally {
      setIsLoading(false);
    }
  }

  function handleTabChange(key: string | number) {
    setSelectedTab(key.toString());
    setSearchParams({ status: key.toString() });
  }

  const isRequester = (exchange: Exchange) => exchange.requester_id === user?.id;
  const isProvider = (exchange: Exchange) => exchange.provider_id === user?.id;
  const otherParty = (exchange: Exchange) =>
    isRequester(exchange) ? exchange.provider : exchange.requester;

  if (!config?.exchange_workflow_enabled) {
    return (
      <EmptyState
        icon={<ArrowRightLeft className="w-12 h-12" />}
        title="Exchange Workflow Not Enabled"
        description="The exchange workflow system is not enabled for this community. Contact your coordinator for more information."
        action={
          <Link to="/listings">
            <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
              Browse Listings
            </Button>
          </Link>
        }
      />
    );
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="space-y-6"
    >
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl sm:text-3xl font-bold text-theme-primary">
            My Exchanges
          </h1>
          <p className="text-theme-muted mt-1">
            Track your service exchange requests and confirmations
          </p>
        </div>
        <Link to="/listings">
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<Plus className="w-4 h-4" />}
          >
            Browse Listings
          </Button>
        </Link>
      </div>

      {/* Tabs */}
      <GlassCard className="p-2">
        <Tabs
          selectedKey={selectedTab}
          onSelectionChange={handleTabChange}
          variant="light"
          classNames={{
            tabList: 'gap-2',
            tab: 'px-4 py-2',
          }}
        >
          <Tab key="active" title="Active" />
          <Tab key="pending_confirmation" title="Needs Confirmation" />
          <Tab key="completed" title="Completed" />
          <Tab key="all" title="All" />
        </Tabs>
      </GlassCard>

      {/* Exchanges List */}
      {isLoading ? (
        <LoadingScreen message="Loading exchanges..." />
      ) : exchanges.length === 0 ? (
        <EmptyState
          icon={<ArrowRightLeft className="w-12 h-12" />}
          title="No Exchanges Found"
          description={
            selectedTab === 'active'
              ? "You don't have any active exchanges. Browse listings to request a service."
              : "No exchanges match this filter."
          }
          action={
            <Link to="/listings">
              <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                Browse Listings
              </Button>
            </Link>
          }
        />
      ) : (
        <div className="space-y-4">
          {exchanges.map((exchange) => {
            const statusConfig = STATUS_CONFIG[exchange.status];
            const StatusIcon = statusConfig.icon;
            const other = otherParty(exchange);

            return (
              <Link key={exchange.id} to={`/exchanges/${exchange.id}`}>
                <GlassCard className="p-4 sm:p-6 hover:border-indigo-500/30 transition-colors cursor-pointer">
                  <div className="flex flex-col sm:flex-row sm:items-center gap-4">
                    {/* Other party avatar */}
                    <Avatar
                      src={resolveAvatarUrl(other?.avatar)}
                      name={other?.name || 'Unknown'}
                      size="lg"
                      className="flex-shrink-0"
                    />

                    {/* Exchange info */}
                    <div className="flex-1 min-w-0">
                      <div className="flex flex-wrap items-center gap-2 mb-1">
                        <h3 className="font-semibold text-theme-primary truncate">
                          {exchange.listing?.title || 'Service Exchange'}
                        </h3>
                        <Chip
                          size="sm"
                          color={statusConfig.color as 'warning' | 'primary' | 'success' | 'danger' | 'secondary' | 'default'}
                          variant="flat"
                          startContent={<StatusIcon className="w-3 h-3" />}
                        >
                          {statusConfig.label}
                        </Chip>
                      </div>

                      <div className="flex flex-wrap items-center gap-4 text-sm text-theme-muted">
                        <span className="flex items-center gap-1">
                          <User className="w-4 h-4" />
                          {isRequester(exchange) ? 'With ' : 'From '}
                          {other?.name || 'Unknown'}
                        </span>
                        <span className="flex items-center gap-1">
                          <Clock className="w-4 h-4" />
                          {exchange.proposed_hours} hour{exchange.proposed_hours !== 1 ? 's' : ''}
                        </span>
                        <span className="flex items-center gap-1">
                          <Calendar className="w-4 h-4" />
                          {new Date(exchange.created_at).toLocaleDateString()}
                        </span>
                      </div>

                      {/* Role indicator */}
                      <div className="mt-2">
                        <span className={`
                          text-xs px-2 py-1 rounded-full
                          ${isRequester(exchange)
                            ? 'bg-amber-500/20 text-amber-400'
                            : 'bg-emerald-500/20 text-emerald-400'}
                        `}>
                          {isRequester(exchange) ? 'You requested' : 'You are providing'}
                        </span>
                      </div>
                    </div>

                    {/* Action needed indicator */}
                    {exchange.status === 'pending_confirmation' && (
                      <div className="flex-shrink-0">
                        {(isRequester(exchange) && !exchange.requester_confirmed_at) ||
                         (isProvider(exchange) && !exchange.provider_confirmed_at) ? (
                          <Chip color="warning" variant="flat">
                            Confirm Hours
                          </Chip>
                        ) : (
                          <Chip color="default" variant="flat">
                            Waiting for other
                          </Chip>
                        )}
                      </div>
                    )}

                    {exchange.status === 'pending_provider' && isProvider(exchange) && (
                      <Chip color="warning" variant="flat">
                        Respond
                      </Chip>
                    )}
                  </div>
                </GlassCard>
              </Link>
            );
          })}
        </div>
      )}
    </motion.div>
  );
}

export default ExchangesPage;
