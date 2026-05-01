// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';
import {
  Avatar,
  Button,
  Chip,
  Input,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Spinner,
  Tab,
  Tabs,
  Textarea,
} from '@heroui/react';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Gift from 'lucide-react/icons/gift';
import HeartHandshake from 'lucide-react/icons/heart-handshake';
import Search from 'lucide-react/icons/search';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { useAuth, useTenant, useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

type GiftStatus = 'pending' | 'accepted' | 'declined' | 'reverted';

interface GiftPartner {
  id: number;
  name: string;
  avatar_url: string | null;
}

interface GiftItem {
  id: number;
  hours: number;
  message: string | null;
  status: GiftStatus;
  created_at: string;
  partner: GiftPartner;
}

interface ListResponse {
  items: GiftItem[];
}

interface MemberSearchResult {
  id: number;
  name: string;
  first_name?: string;
  last_name?: string;
  profile_photo?: string | null;
  avatar_url?: string | null;
}

interface SendResponse {
  gift_id: number;
  status: GiftStatus;
  success: boolean;
}

const STATUS_COLOR: Record<GiftStatus, 'default' | 'primary' | 'success' | 'warning' | 'danger'> = {
  pending: 'warning',
  accepted: 'success',
  declined: 'danger',
  reverted: 'default',
};

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export function HourGiftPage() {
  const { t } = useTranslation('common');
  const { hasFeature, tenantPath } = useTenant();
  const { user } = useAuth();
  const toast = useToast();
  usePageTitle(t('hour_gift.meta.title'));

  const balance = useMemo(() => Number(user?.balance ?? 0), [user]);

  const [activeTab, setActiveTab] = useState<'send' | 'inbox' | 'sent'>('send');

  // Send tab state
  const [recipientQuery, setRecipientQuery] = useState('');
  const [recipientResults, setRecipientResults] = useState<MemberSearchResult[]>([]);
  const [recipient, setRecipient] = useState<MemberSearchResult | null>(null);
  const [searching, setSearching] = useState(false);
  const [hoursInput, setHoursInput] = useState('');
  const [message, setMessage] = useState('');
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  // Inbox / sent state
  const [inbox, setInbox] = useState<GiftItem[]>([]);
  const [sent, setSent] = useState<GiftItem[]>([]);
  const [inboxLoading, setInboxLoading] = useState(false);
  const [sentLoading, setSentLoading] = useState(false);

  // Inline decline state
  const [declineId, setDeclineId] = useState<number | null>(null);
  const [declineReason, setDeclineReason] = useState('');

  const loadInbox = useCallback(async () => {
    setInboxLoading(true);
    try {
      const res = await api.get<ListResponse>('/v2/caring-community/hour-gifts/inbox');
      if (res.success && res.data) {
        setInbox(res.data.items ?? []);
      }
    } catch (err) {
      logError('HourGiftPage: load inbox failed', err);
    } finally {
      setInboxLoading(false);
    }
  }, []);

  const loadSent = useCallback(async () => {
    setSentLoading(true);
    try {
      const res = await api.get<ListResponse>('/v2/caring-community/hour-gifts/sent');
      if (res.success && res.data) {
        setSent(res.data.items ?? []);
      }
    } catch (err) {
      logError('HourGiftPage: load sent failed', err);
    } finally {
      setSentLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadInbox();
    void loadSent();
  }, [loadInbox, loadSent]);

  // Debounced recipient search
  useEffect(() => {
    if (recipient !== null) return;
    const q = recipientQuery.trim();
    if (q.length < 2) {
      setRecipientResults([]);
      return;
    }
    const handle = setTimeout(async () => {
      setSearching(true);
      try {
        const res = await api.get<{ items?: MemberSearchResult[] } | MemberSearchResult[]>(
          `/v2/users?q=${encodeURIComponent(q)}&limit=8`,
        );
        const items: MemberSearchResult[] = Array.isArray(res.data)
          ? res.data
          : (res.data as { items?: MemberSearchResult[] })?.items ?? [];
        setRecipientResults(items.filter((m) => m.id !== user?.id).slice(0, 8));
      } catch (err) {
        logError('HourGiftPage: search failed', err);
      } finally {
        setSearching(false);
      }
    }, 250);
    return () => clearTimeout(handle);
  }, [recipientQuery, recipient, user?.id]);

  if (!hasFeature('caring_community')) {
    return <Navigate to={tenantPath('/caring-community')} replace />;
  }

  const hoursValue = parseFloat(hoursInput);
  const validHours = !Number.isNaN(hoursValue) && hoursValue > 0 && hoursValue <= balance;
  const canSubmit = recipient !== null && validHours && !submitting;

  const performSend = async () => {
    if (!recipient) return;
    setSubmitting(true);
    try {
      const res = await api.post<SendResponse>('/v2/caring-community/hour-gifts/send', {
        recipient_user_id: recipient.id,
        hours: hoursValue,
        message: message.trim() || null,
      });
      if (res.success) {
        toast.success(t('hour_gift.success.sent', { name: recipient.name }));
        setRecipient(null);
        setRecipientQuery('');
        setHoursInput('');
        setMessage('');
        setConfirmOpen(false);
        void loadSent();
      } else {
        const code = res.code;
        const msg =
          code === 'INSUFFICIENT_HOURS'
            ? t('hour_gift.errors.insufficient_balance')
            : res.error || t('hour_gift.errors.send_failed');
        toast.error(msg);
      }
    } catch (err) {
      logError('HourGiftPage: send failed', err);
      toast.error(t('hour_gift.errors.send_failed'));
    } finally {
      setSubmitting(false);
    }
  };

  const handleAccept = async (id: number) => {
    try {
      const res = await api.post(`/v2/caring-community/hour-gifts/${id}/accept`, {});
      if (res.success) {
        toast.success(t('hour_gift.success.accepted'));
        void loadInbox();
      } else {
        toast.error(res.error || t('hour_gift.errors.send_failed'));
      }
    } catch (err) {
      logError('HourGiftPage: accept failed', err);
      toast.error(t('hour_gift.errors.send_failed'));
    }
  };

  const handleDecline = async (id: number) => {
    try {
      const res = await api.post(`/v2/caring-community/hour-gifts/${id}/decline`, {
        reason: declineReason.trim() || null,
      });
      if (res.success) {
        toast.success(t('hour_gift.success.declined'));
        setDeclineId(null);
        setDeclineReason('');
        void loadInbox();
      } else {
        toast.error(res.error || t('hour_gift.errors.send_failed'));
      }
    } catch (err) {
      logError('HourGiftPage: decline failed', err);
      toast.error(t('hour_gift.errors.send_failed'));
    }
  };

  const handleRevert = async (id: number) => {
    try {
      const res = await api.post(`/v2/caring-community/hour-gifts/${id}/revert`, {});
      if (res.success) {
        void loadSent();
      } else {
        toast.error(res.error || t('hour_gift.errors.send_failed'));
      }
    } catch (err) {
      logError('HourGiftPage: revert failed', err);
      toast.error(t('hour_gift.errors.send_failed'));
    }
  };

  return (
    <>
      <PageMeta
        title={t('hour_gift.meta.title')}
        description={t('hour_gift.meta.description')}
        noIndex
      />
      <div className="mx-auto max-w-2xl space-y-6">
        <div>
          <Link
            to={tenantPath('/caring-community')}
            className="inline-flex items-center gap-1 text-sm text-theme-muted hover:text-theme-primary"
          >
            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
            {t('hour_gift.back')}
          </Link>
        </div>

        <GlassCard className="p-6 sm:p-8">
          <div className="mb-6 flex items-center gap-4">
            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-rose-500/15">
              <HeartHandshake className="h-6 w-6 text-rose-500" aria-hidden="true" />
            </div>
            <div>
              <h1 className="text-2xl font-bold leading-tight text-theme-primary">
                {t('hour_gift.title')}
              </h1>
              <p className="mt-1 text-base leading-7 text-theme-muted">
                {t('hour_gift.subtitle')}
              </p>
            </div>
          </div>

          <Tabs
            selectedKey={activeTab}
            onSelectionChange={(k) => setActiveTab(k as 'send' | 'inbox' | 'sent')}
            aria-label={t('hour_gift.title')}
            color="primary"
            variant="underlined"
          >
            <Tab key="send" title={t('hour_gift.tabs.send')}>
              <div className="mt-4 space-y-5">
                {/* Recipient picker */}
                <div>
                  <label className="mb-1.5 block text-sm font-medium text-theme-primary">
                    {t('hour_gift.send.recipient_label')}
                  </label>
                  {recipient ? (
                    <div className="flex items-center justify-between gap-3 rounded-lg border border-default-200 bg-default-50 px-3 py-2">
                      <div className="flex items-center gap-2">
                        <Avatar
                          src={recipient.profile_photo ?? recipient.avatar_url ?? undefined}
                          name={recipient.name}
                          size="sm"
                        />
                        <span className="font-medium text-theme-primary">{recipient.name}</span>
                      </div>
                      <Button
                        size="sm"
                        variant="light"
                        onPress={() => {
                          setRecipient(null);
                          setRecipientQuery('');
                        }}
                      >
                        {t('hour_gift.send.cancel')}
                      </Button>
                    </div>
                  ) : (
                    <>
                      <Input
                        placeholder={t('hour_gift.send.recipient_placeholder')}
                        value={recipientQuery}
                        onValueChange={setRecipientQuery}
                        variant="bordered"
                        startContent={<Search className="h-4 w-4 text-default-400" />}
                      />
                      {searching && <Spinner size="sm" className="mt-2" />}
                      {recipientResults.length > 0 && (
                        <ul className="mt-2 divide-y divide-default-200 overflow-hidden rounded-lg border border-default-200">
                          {recipientResults.map((m) => (
                            <li key={m.id}>
                              <Button
                                type="button"
                                variant="light"
                                className="h-auto w-full justify-start rounded-none px-3 py-2 text-left"
                                startContent={
                                  <Avatar
                                    src={m.profile_photo ?? m.avatar_url ?? undefined}
                                    name={m.name}
                                    size="sm"
                                  />
                                }
                                onPress={() => {
                                  setRecipient(m);
                                  setRecipientResults([]);
                                  setRecipientQuery('');
                                }}
                              >
                                <span className="min-w-0 truncate text-sm">{m.name}</span>
                              </Button>
                            </li>
                          ))}
                        </ul>
                      )}
                    </>
                  )}
                </div>

                {/* Hours */}
                <Input
                  type="number"
                  label={t('hour_gift.send.hours_label')}
                  description={t('hour_gift.send.hours_balance_helper', { balance })}
                  value={hoursInput}
                  onValueChange={setHoursInput}
                  variant="bordered"
                  min="0.01"
                  step="0.5"
                  max={balance.toString()}
                  isRequired
                />

                {/* Message */}
                <Textarea
                  label={t('hour_gift.send.message_label')}
                  placeholder={t('hour_gift.send.message_placeholder')}
                  value={message}
                  onValueChange={setMessage}
                  variant="bordered"
                  minRows={2}
                  maxRows={4}
                  maxLength={500}
                />

                <Button
                  color="primary"
                  size="lg"
                  className="w-full text-base"
                  startContent={<Gift className="h-5 w-5" />}
                  isDisabled={!canSubmit}
                  onPress={() => setConfirmOpen(true)}
                >
                  {t('hour_gift.send.submit')}
                </Button>
              </div>
            </Tab>

            <Tab key="inbox" title={t('hour_gift.tabs.inbox')}>
              <div className="mt-4 space-y-3">
                {inboxLoading ? (
                  <div className="flex justify-center py-6">
                    <Spinner size="md" />
                  </div>
                ) : inbox.length === 0 ? (
                  <p className="py-4 text-sm text-theme-muted">{t('hour_gift.inbox.empty')}</p>
                ) : (
                  inbox.map((g) => (
                    <div
                      key={g.id}
                      className="rounded-lg border border-default-200 bg-default-50 p-4"
                    >
                      <div className="flex items-start justify-between gap-3">
                        <div className="flex items-center gap-3">
                          <Avatar
                            src={g.partner.avatar_url ?? undefined}
                            name={g.partner.name}
                            size="md"
                          />
                          <div>
                            <p className="font-medium text-theme-primary">
                              {t('hour_gift.inbox.from', { name: g.partner.name })}
                            </p>
                            <p className="text-sm text-theme-muted">
                              {t('hour_gift.inbox.received_at', {
                                date: new Date(g.created_at).toLocaleDateString(),
                              })}
                            </p>
                          </div>
                        </div>
                        <span className="text-lg font-semibold text-theme-primary tabular-nums">
                          {t('hours_short', { count: g.hours })}
                        </span>
                      </div>
                      {g.message && (
                        <p className="mt-3 rounded-md bg-white/60 p-3 text-sm text-theme-muted dark:bg-default-100">
                          “{g.message}”
                        </p>
                      )}
                      {declineId === g.id ? (
                        <div className="mt-3 space-y-2">
                          <Textarea
                            placeholder={t('hour_gift.inbox.decline_reason_label')}
                            value={declineReason}
                            onValueChange={setDeclineReason}
                            variant="bordered"
                            minRows={2}
                            maxRows={3}
                            maxLength={500}
                          />
                          <div className="flex gap-2">
                            <Button
                              color="danger"
                              size="sm"
                              onPress={() => void handleDecline(g.id)}
                            >
                              {t('hour_gift.inbox.decline_button')}
                            </Button>
                            <Button
                              size="sm"
                              variant="light"
                              onPress={() => {
                                setDeclineId(null);
                                setDeclineReason('');
                              }}
                            >
                              {t('hour_gift.send.cancel')}
                            </Button>
                          </div>
                        </div>
                      ) : (
                        <div className="mt-3 flex gap-2">
                          <Button
                            color="primary"
                            size="sm"
                            onPress={() => void handleAccept(g.id)}
                          >
                            {t('hour_gift.inbox.accept')}
                          </Button>
                          <Button
                            size="sm"
                            variant="bordered"
                            onPress={() => setDeclineId(g.id)}
                          >
                            {t('hour_gift.inbox.decline')}
                          </Button>
                        </div>
                      )}
                    </div>
                  ))
                )}
              </div>
            </Tab>

            <Tab key="sent" title={t('hour_gift.tabs.sent')}>
              <div className="mt-4 space-y-3">
                {sentLoading ? (
                  <div className="flex justify-center py-6">
                    <Spinner size="md" />
                  </div>
                ) : sent.length === 0 ? (
                  <p className="py-4 text-sm text-theme-muted">{t('hour_gift.sent.empty')}</p>
                ) : (
                  sent.map((g) => (
                    <div
                      key={g.id}
                      className="rounded-lg border border-default-200 bg-default-50 p-4"
                    >
                      <div className="flex items-start justify-between gap-3">
                        <div className="flex items-center gap-3">
                          <Avatar
                            src={g.partner.avatar_url ?? undefined}
                            name={g.partner.name}
                            size="md"
                          />
                          <div>
                            <p className="font-medium text-theme-primary">
                              {t('hour_gift.sent.to', { name: g.partner.name })}
                            </p>
                            <p className="text-sm text-theme-muted">
                              {new Date(g.created_at).toLocaleDateString()}
                            </p>
                          </div>
                        </div>
                        <div className="flex flex-col items-end gap-1">
                          <span className="text-lg font-semibold text-theme-primary tabular-nums">
                            {t('hours_short', { count: g.hours })}
                          </span>
                          <Chip size="sm" color={STATUS_COLOR[g.status]} variant="flat">
                            {t(`hour_gift.sent.status.${g.status}`)}
                          </Chip>
                        </div>
                      </div>
                      {g.status === 'pending' && (
                        <div className="mt-3">
                          <Button
                            size="sm"
                            variant="bordered"
                            onPress={() => void handleRevert(g.id)}
                          >
                            {t('hour_gift.sent.revert')}
                          </Button>
                        </div>
                      )}
                    </div>
                  ))
                )}
              </div>
            </Tab>
          </Tabs>
        </GlassCard>
      </div>

      {/* Confirmation modal */}
      <Modal isOpen={confirmOpen} onOpenChange={setConfirmOpen} placement="center">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>
                {recipient
                  ? t('hour_gift.send.confirm_title', {
                      hours: hoursValue || 0,
                      name: recipient.name,
                    })
                  : ''}
              </ModalHeader>
              <ModalBody>
                <p className="text-sm text-theme-muted">{t('hour_gift.send.confirm_body')}</p>
              </ModalBody>
              <ModalFooter>
                <Button variant="light" onPress={onClose} isDisabled={submitting}>
                  {t('hour_gift.send.cancel')}
                </Button>
                <Button
                  color="primary"
                  isLoading={submitting}
                  onPress={() => void performSend()}
                >
                  {t('hour_gift.send.confirm_button')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </>
  );
}

export default HourGiftPage;
