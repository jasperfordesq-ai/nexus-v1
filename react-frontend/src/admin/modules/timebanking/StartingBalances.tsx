// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Starting Balances (W7)
 * Admin page for granting starting time credits to members.
 * Allows searching members, granting credits, and viewing grant history.
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Input,
  Button,
  Spinner,
  Chip,
  Textarea,
} from '@heroui/react';
import { Wallet, Plus, History, Search, Users } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminUsers, adminTimebanking } from '../../api/adminApi';
import { DataTable, PageHeader, type Column } from '../../components';
import type { AdminUser, WalletGrant } from '../../api/types';

import { useTranslation } from 'react-i18next';
// ─────────────────────────────────────────────────────────────────────────────
// Member Search + Grant Form
// ─────────────────────────────────────────────────────────────────────────────

function GrantCreditsForm({ onGranted }: { onGranted: () => void }) {
  const { t } = useTranslation('admin');
  const toast = useToast();

  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<AdminUser[]>([]);
  const [searching, setSearching] = useState(false);
  const [selectedUser, setSelectedUser] = useState<AdminUser | null>(null);
  const [amount, setAmount] = useState('');
  const [reason, setReason] = useState('');
  const [granting, setGranting] = useState(false);

  const handleSearch = useCallback(async (query: string) => {
    setSearchQuery(query);
    if (!query || query.length < 2) {
      setSearchResults([]);
      return;
    }
    setSearching(true);
    try {
      const res = await adminUsers.list({ search: query, limit: 10 });
      if (res.success && res.data) {
        const data = res.data as unknown;
        if (Array.isArray(data)) {
          setSearchResults(data);
        } else if (data && typeof data === 'object') {
          const pd = data as { data: AdminUser[] };
          setSearchResults(pd.data || []);
        }
      }
    } catch {
      // Silently handle search errors
    } finally {
      setSearching(false);
    }
  }, []);

  const handleSelectUser = (user: AdminUser) => {
    setSelectedUser(user);
    setSearchQuery('');
    setSearchResults([]);
  };

  const handleGrant = async () => {
    if (!selectedUser) {
      toast.error(t('timebanking.please_select_a_member'));
      return;
    }
    const parsedAmount = parseFloat(amount);
    if (!parsedAmount || parsedAmount <= 0) {
      toast.error(t('timebanking.please_enter_a_valid_credit_amount_great'));
      return;
    }
    if (!reason.trim()) {
      toast.error(t('timebanking.please_provide_a_reason_for_this_grant'));
      return;
    }

    setGranting(true);
    try {
      const res = await adminTimebanking.grantCredits({
        user_id: selectedUser.id,
        amount: parsedAmount,
        reason: reason.trim(),
      });

      if (res?.success) {
        toast.success(t('timebanking.granted_credits', { amount: parsedAmount, name: selectedUser.name }));
        setSelectedUser(null);
        setAmount('');
        setReason('');
        onGranted();
      } else {
        toast.error(res?.error || t('timebanking.failed_to_grant_credits'));
      }
    } catch {
      toast.error(t('timebanking.an_unexpected_error_occurred'));
    } finally {
      setGranting(false);
    }
  };

  return (
    <Card shadow="sm">
      <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
        <Plus size={18} className="text-primary" />
        <h3 className="font-semibold">{t('timebanking.grant_starting_credits')}</h3>
      </CardHeader>
      <CardBody className="px-4 pb-4 space-y-4">
        {/* Member search */}
        {!selectedUser ? (
          <div>
            <Input
              label={t('timebanking.label_search_member')}
              placeholder={t('timebanking.placeholder_search_by_name_or_email')}
              startContent={<Search size={16} className="text-default-400" />}
              value={searchQuery}
              onValueChange={handleSearch}
              size="sm"
              variant="bordered"
            />
            {searching && (
              <div className="flex items-center justify-center py-4">
                <Spinner size="sm" />
              </div>
            )}
            {searchResults.length > 0 && (
              <div className="mt-2 space-y-1 max-h-48 overflow-y-auto border border-divider rounded-lg">
                {searchResults.map((user) => (
                  <Button
                    key={user.id}
                    variant="light"
                    className="flex items-center justify-between w-full px-3 py-2 h-auto rounded-none"
                    onPress={() => handleSelectUser(user)}
                  >
                    <div className="flex-1 min-w-0 text-left">
                      <p className="text-sm font-medium text-foreground truncate">
                        {user.name}
                      </p>
                      <p className="text-xs text-default-500 truncate">
                        {user.email}
                      </p>
                    </div>
                    <div className="text-right shrink-0 ml-3">
                      <p className="text-xs text-default-500">{t('timebanking.col_balance')}</p>
                      <p className="text-sm font-semibold text-foreground">
                        {user.balance}h
                      </p>
                    </div>
                  </Button>
                ))}
              </div>
            )}
            {searchQuery.length >= 2 && !searching && searchResults.length === 0 && (
              <p className="text-sm text-default-400 text-center py-2 mt-2">
                {t('timebanking.no_members_found', { query: searchQuery })}
              </p>
            )}
          </div>
        ) : (
          <div className="flex items-center justify-between border border-divider rounded-lg p-3">
            <div className="flex items-center gap-3">
              <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10">
                <Users size={16} className="text-primary" />
              </div>
              <div>
                <p className="text-sm font-medium text-foreground">
                  {selectedUser.name}
                </p>
                <p className="text-xs text-default-500">
                  {selectedUser.email} &middot; {t('timebanking.current_balance')}: {selectedUser.balance}h
                </p>
              </div>
            </div>
            <Button
              size="sm"
              variant="flat"
              onPress={() => setSelectedUser(null)}
            >
              {t('timebanking.change')}
            </Button>
          </div>
        )}

        {/* Amount input */}
        <Input
          label={t('timebanking.label_credit_amount')}
          placeholder={t('timebanking.placeholder_credit_amount')}
          type="number"
          min="0.25"
          step="0.25"
          value={amount}
          onValueChange={setAmount}
          size="sm"
          variant="bordered"
          startContent={
            <Wallet size={16} className="text-default-400" />
          }
          description={t('timebanking.desc_amount_of_time_credits_to_grant_in_hours')}
        />

        {/* Reason */}
        <Textarea
          label={t('timebanking.label_reason')}
          placeholder={t('timebanking.placeholder_grant_reason')}
          value={reason}
          onValueChange={setReason}
          size="sm"
          variant="bordered"
          minRows={2}
          maxRows={4}
          description={t('timebanking.desc_required_this_will_be_recorded_in_the_g')}
        />

        {/* Submit */}
        <Button
          color="primary"
          startContent={<Plus size={16} />}
          onPress={handleGrant}
          isLoading={granting}
          isDisabled={!selectedUser || !amount || !reason.trim()}
          className="w-full sm:w-auto"
        >
          {t('timebanking.grant_credits')}
        </Button>
      </CardBody>
    </Card>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Grant History Table
// ─────────────────────────────────────────────────────────────────────────────

function GrantHistory({ refreshKey }: { refreshKey: number }) {
  const { t } = useTranslation('admin');
  const toast = useToast();

  const [grants, setGrants] = useState<WalletGrant[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');

  const loadGrants = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminTimebanking.getGrants({
        page,
        search: search || undefined,
      });
      if (res.success && res.data) {
        const data = res.data as unknown;
        if (Array.isArray(data)) {
          setGrants(data);
          const metaTotal = (res.meta as Record<string, unknown> | undefined)?.total;
          setTotal(typeof metaTotal === 'number' ? metaTotal : data.length);
        } else if (data && typeof data === 'object') {
          const pd = data as { data: WalletGrant[]; meta?: { total: number } };
          setGrants(pd.data || []);
          setTotal(pd.meta?.total || 0);
        }
      }
    } catch {
      toast.error(t('timebanking.failed_to_load_grant_history'));
    } finally {
      setLoading(false);
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps -- refreshKey intentionally triggers reload
  }, [page, search, refreshKey, toast]);

  useEffect(() => {
    loadGrants();
  }, [loadGrants]);

  const columns: Column<WalletGrant>[] = [
    {
      key: 'user_name',
      label: t('timebanking.col_member'),
      sortable: true,
      render: (item) => (
        <div>
          <p className="text-sm font-medium text-foreground">{item.user_name}</p>
          <p className="text-xs text-default-500">{item.user_email}</p>
        </div>
      ),
    },
    {
      key: 'amount',
      label: t('timebanking.col_amount'),
      sortable: true,
      render: (item) => (
        <Chip size="sm" variant="flat" color="success">
          +{item.amount}h
        </Chip>
      ),
    },
    {
      key: 'reason',
      label: t('timebanking.label_reason'),
      render: (item) => (
        <span className="text-sm text-default-600 line-clamp-2">
          {item.reason}
        </span>
      ),
    },
    {
      key: 'granted_by',
      label: t('timebanking.col_granted_by'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.granted_by || '—'}
        </span>
      ),
    },
    {
      key: 'created_at',
      label: t('timebanking.col_date'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {new Date(item.created_at).toLocaleDateString()}
        </span>
      ),
    },
  ];

  return (
    <div>
      <h3 className="text-sm font-semibold text-foreground mb-3 flex items-center gap-2">
        <History size={16} className="text-secondary" />
        {t('timebanking.grant_history')}
      </h3>
      <DataTable
        columns={columns}
        data={grants}
        isLoading={loading}
        searchPlaceholder={t('timebanking.search_grants_placeholder')}
        onSearch={(q) => { setSearch(q); setPage(1); }}
        onRefresh={loadGrants}
        totalItems={total}
        page={page}
        pageSize={20}
        onPageChange={setPage}
        emptyContent={t('timebanking.no_credit_grants')}
      />
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function StartingBalances() {
  const { t } = useTranslation('admin');
  usePageTitle(t('timebanking.page_title'));

  // Key to trigger grant history refresh after new grant
  const [refreshKey, setRefreshKey] = useState(0);

  const handleGranted = () => {
    setRefreshKey((prev) => prev + 1);
  };

  return (
    <div>
      <PageHeader
        title={t('timebanking.starting_balances_title')}
        description={t('timebanking.starting_balances_desc')}
      />

      <div className="space-y-6">
        <GrantCreditsForm onGranted={handleGranted} />
        <GrantHistory refreshKey={refreshKey} />
      </div>
    </div>
  );
}

export default StartingBalances;
