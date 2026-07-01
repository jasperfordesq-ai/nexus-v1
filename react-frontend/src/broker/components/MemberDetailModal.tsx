// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Member Detail Modal (broker panel)
 *
 * A single-member view giving brokers the operational actions the admin Users
 * area used to be needed for: approve/suspend/reactivate, resend verification,
 * send a password reset, reset 2FA, adjust the time balance, edit safe profile
 * fields, and review vetting / insurance / consent status. Privileged actions
 * (role/status change, ban, delete) are intentionally absent — the backend also
 * rejects them for brokers (see AdminUsersController@update).
 */

import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Avatar, Button, Chip, Spinner, Separator, Input, Textarea,
  Modal, ModalContent, ModalHeader, ModalBody, ModalFooter,
} from '@/components/ui';
import UserCheck from 'lucide-react/icons/user-check';
import UserX from 'lucide-react/icons/user-x';
import RotateCcw from 'lucide-react/icons/rotate-ccw';
import MailCheck from 'lucide-react/icons/mail-check';
import KeyRound from 'lucide-react/icons/key-round';
import ShieldOff from 'lucide-react/icons/shield-off';
import Coins from 'lucide-react/icons/coins';
import Pencil from 'lucide-react/icons/pencil';
import { useToast } from '@/contexts';
import { adminUsers, adminTimebanking, adminVetting, adminInsurance } from '@/admin/api/adminApi';
import type { AdminUserDetail, VettingRecord, InsuranceCertificate } from '@/admin/api/types';
import { resolveAvatarUrl } from '@/lib/helpers';
import { formatServerDate, formatServerDateTime } from '@/lib/serverTime';

type MemberDetail = AdminUserDetail;

interface ConsentRow {
  consent_type?: string;
  type?: string;
  name?: string;
  granted?: boolean;
  is_granted?: boolean;
  status?: string;
}

interface EditForm {
  first_name: string;
  last_name: string;
  phone: string;
  bio: string;
  tagline: string;
  location: string;
}

const STATUS_COLOR: Record<string, 'warning' | 'success' | 'danger' | 'default'> = {
  pending: 'warning', active: 'success', suspended: 'danger', banned: 'danger',
};

interface MemberDetailModalProps {
  userId: number | null;
  onClose: () => void;
  /** Called after any mutation so the parent list can refresh. */
  onChanged: () => void;
}

export function MemberDetailModal({ userId, onClose, onChanged }: MemberDetailModalProps) {
  const { t } = useTranslation('broker');
  const toast = useToast();

  const [detail, setDetail] = useState<MemberDetail | null>(null);
  const [loading, setLoading] = useState(false);
  const [vetting, setVetting] = useState<VettingRecord[]>([]);
  const [insurance, setInsurance] = useState<InsuranceCertificate[]>([]);
  const [consents, setConsents] = useState<ConsentRow[]>([]);
  // Which action is currently running (disables the relevant button).
  const [busy, setBusy] = useState<string | null>(null);

  // Edit form
  const [editing, setEditing] = useState(false);
  const [form, setForm] = useState<EditForm>({ first_name: '', last_name: '', phone: '', bio: '', tagline: '', location: '' });

  // Balance adjustment sub-modal
  const [balanceOpen, setBalanceOpen] = useState(false);
  const [balanceAmount, setBalanceAmount] = useState('');
  const [balanceReason, setBalanceReason] = useState('');

  const asArray = <T,>(payload: unknown): T[] => {
    if (Array.isArray(payload)) return payload as T[];
    if (payload && typeof payload === 'object' && Array.isArray((payload as { data?: unknown }).data)) {
      return (payload as { data: T[] }).data;
    }
    return [];
  };

  const load = useCallback(async (id: number) => {
    setLoading(true);
    setEditing(false);
    setDetail(null);
    setVetting([]);
    setInsurance([]);
    setConsents([]);
    try {
      const res = await adminUsers.get(id);
      if (res.success && res.data) {
        const d = res.data as MemberDetail;
        setDetail(d);
        setForm({
          first_name: d.first_name ?? '',
          last_name: d.last_name ?? '',
          phone: d.phone ?? '',
          bio: d.bio ?? '',
          tagline: d.tagline ?? '',
          location: d.location ?? '',
        });
      } else {
        toast.error(t('member_detail.load_failed'));
      }
    } catch {
      toast.error(t('member_detail.load_failed'));
    } finally {
      setLoading(false);
    }
    // Compliance data is best-effort — failures shouldn't block the modal.
    try {
      const [v, i, c] = await Promise.all([
        adminVetting.getUserRecords(id).catch(() => null),
        adminInsurance.getUserCertificates(id).catch(() => null),
        adminUsers.getConsents(id).catch(() => null),
      ]);
      if (v?.success) setVetting(asArray<VettingRecord>(v.data));
      if (i?.success) setInsurance(asArray<InsuranceCertificate>(i.data));
      if (c?.success) setConsents(asArray<ConsentRow>(c.data));
    } catch { /* ignore */ }
  }, [toast, t]);

  useEffect(() => {
    if (userId != null) load(userId);
    // `load` is intentionally NOT a dependency: it closes over toast/t, which
    // are not guaranteed to be referentially stable, so keying the effect on
    // its identity would re-run on every render and loop. The member id is the
    // only thing that should trigger a (re)fetch.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [userId]);

  // Generic action runner — refreshes the detail + parent list on success.
  const run = useCallback(async (
    key: string,
    fn: () => Promise<{ success?: boolean; error?: string }>,
    successKey: string,
    refetch = true,
  ) => {
    if (!detail) return;
    setBusy(key);
    try {
      const res = await fn();
      if (res?.success) {
        toast.success(t(successKey));
        onChanged();
        if (refetch) await load(detail.id);
      } else {
        toast.error(res?.error || t('member_detail.action_failed'));
      }
    } catch {
      toast.error(t('member_detail.action_failed'));
    } finally {
      setBusy(null);
    }
  }, [detail, toast, t, onChanged, load]);

  const handleSaveEdit = useCallback(async () => {
    if (!detail) return;
    setBusy('edit');
    try {
      const res = await adminUsers.update(detail.id, {
        first_name: form.first_name.trim(),
        last_name: form.last_name.trim(),
        phone: form.phone.trim(),
        bio: form.bio,
        tagline: form.tagline,
        location: form.location,
      });
      if (res.success) {
        toast.success(t('member_detail.edit_success'));
        setEditing(false);
        onChanged();
        await load(detail.id);
      } else {
        toast.error(res.error || t('member_detail.action_failed'));
      }
    } catch {
      toast.error(t('member_detail.action_failed'));
    } finally {
      setBusy(null);
    }
  }, [detail, form, toast, t, onChanged, load]);

  const handleAdjustBalance = useCallback(async () => {
    if (!detail) return;
    const amount = Number(balanceAmount);
    if (!Number.isFinite(amount) || amount === 0) {
      toast.error(t('member_detail.balance_amount_invalid'));
      return;
    }
    if (!balanceReason.trim()) {
      toast.error(t('member_detail.balance_reason_required'));
      return;
    }
    setBusy('balance');
    try {
      const res = await adminTimebanking.adjustBalance(detail.id, amount, balanceReason.trim());
      if (res.success) {
        toast.success(t('member_detail.balance_success'));
        setBalanceOpen(false);
        setBalanceAmount('');
        setBalanceReason('');
        onChanged();
        await load(detail.id);
      } else {
        toast.error(res.error || t('member_detail.action_failed'));
      }
    } catch {
      toast.error(t('member_detail.action_failed'));
    } finally {
      setBusy(null);
    }
  }, [detail, balanceAmount, balanceReason, toast, t, onChanged, load]);

  const consentLabel = (c: ConsentRow) => c.consent_type || c.type || c.name || '';
  const consentGranted = (c: ConsentRow) => c.granted ?? c.is_granted ?? (c.status === 'granted' || c.status === 'active');

  return (
    <>
      <Modal isOpen={userId != null} onClose={onClose} size="2xl" scrollBehavior="inside">
        <ModalContent>
          {loading || !detail ? (
            <ModalBody>
              <div role="status" aria-busy="true" aria-label={t('common.loading')} className="flex justify-center py-12">
                <Spinner size="lg" />
              </div>
            </ModalBody>
          ) : (
            <>
              <ModalHeader className="flex items-center gap-3">
                <Avatar
                  src={resolveAvatarUrl(detail.avatar_url || detail.avatar) || undefined}
                  name={detail.name}
                  size="md"
                />
                <div className="min-w-0 flex-1">
                  <p className="text-base font-semibold truncate">{detail.name}</p>
                  <p className="text-xs text-muted font-normal truncate">{detail.email}</p>
                </div>
                <div className="flex flex-wrap items-center gap-1">
                  <Chip size="sm" variant="tertiary" color={STATUS_COLOR[detail.status] ?? 'default'}>
                    {t(`status.${detail.status}`)}
                  </Chip>
                  <Chip size="sm" variant="tertiary" color={detail.role === 'member' ? 'default' : 'accent'}>
                    {t(`members.role_${detail.role}`, { defaultValue: detail.role })}
                  </Chip>
                  <Chip size="sm" variant="tertiary" color={detail.email_verified_at ? 'success' : 'warning'}>
                    {detail.email_verified_at ? t('members.email_verified') : t('members.email_unverified')}
                  </Chip>
                </div>
              </ModalHeader>

              <ModalBody className="gap-4">
                {/* Overview */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                  <Overview label={t('member_detail.label_balance')} value={`${typeof detail.balance === 'number' ? detail.balance.toLocaleString() : '0'} ${t('members.hours_short')}`} />
                  <Overview label={t('member_detail.label_joined')} value={formatServerDate(detail.created_at)} />
                  <Overview label={t('member_detail.label_last_active')} value={detail.last_active_at ? formatServerDate(detail.last_active_at) : t('members.time_never')} />
                  <Overview label={t('member_detail.label_onboarding')} value={detail.onboarding_completed ? t('member_detail.onboarding_complete') : t('member_detail.onboarding_incomplete')} />
                  <Overview label={t('member_detail.label_vetting')} value={t(`member_detail.compliance_${detail.vetting_status ?? 'none'}`, { defaultValue: detail.vetting_status ?? '—' })} />
                  <Overview label={t('member_detail.label_insurance')} value={t(`member_detail.compliance_${detail.insurance_status ?? 'none'}`, { defaultValue: detail.insurance_status ?? '—' })} />
                </div>

                <Separator />

                {/* Actions */}
                <div>
                  <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-muted">{t('member_detail.section_actions')}</p>
                  <div className="flex flex-wrap gap-2">
                    {detail.status === 'pending' && (
                      <Button size="sm" color="success" variant="flat" startContent={<UserCheck size={14} />} isLoading={busy === 'approve'}
                        onPress={() => run('approve', () => adminUsers.approve(detail.id), 'member_detail.approve_success')}>
                        {t('members.approve')}
                      </Button>
                    )}
                    {detail.status === 'active' && (
                      <Button size="sm" color="danger" variant="flat" startContent={<UserX size={14} />} isLoading={busy === 'suspend'}
                        onPress={() => run('suspend', () => adminUsers.suspend(detail.id), 'member_detail.suspend_success')}>
                        {t('members.suspend')}
                      </Button>
                    )}
                    {detail.status === 'suspended' && (
                      <Button size="sm" variant="flat" startContent={<RotateCcw size={14} />} isLoading={busy === 'reactivate'}
                        onPress={() => run('reactivate', () => adminUsers.reactivate(detail.id), 'member_detail.reactivate_success')}>
                        {t('members.reactivate')}
                      </Button>
                    )}
                    <Button size="sm" variant="flat" startContent={<MailCheck size={14} />} isLoading={busy === 'verify'}
                      onPress={() => run('verify', () => adminUsers.sendVerificationEmail(detail.id), 'member_detail.verification_sent', false)}>
                      {t('member_detail.action_resend_verification')}
                    </Button>
                    <Button size="sm" variant="flat" startContent={<KeyRound size={14} />} isLoading={busy === 'pwd'}
                      onPress={() => run('pwd', () => adminUsers.sendPasswordReset(detail.id), 'member_detail.password_reset_sent', false)}>
                      {t('member_detail.action_send_password_reset')}
                    </Button>
                    <Button size="sm" variant="flat" startContent={<ShieldOff size={14} />} isLoading={busy === '2fa'}
                      onPress={() => run('2fa', () => adminUsers.reset2fa(detail.id, t('member_detail.reset_2fa_reason')), 'member_detail.reset_2fa_success', false)}>
                      {t('member_detail.action_reset_2fa')}
                    </Button>
                    <Button size="sm" color="primary" variant="flat" startContent={<Coins size={14} />}
                      onPress={() => { setBalanceAmount(''); setBalanceReason(''); setBalanceOpen(true); }}>
                      {t('member_detail.action_adjust_balance')}
                    </Button>
                  </div>
                </div>

                <Separator />

                {/* Edit profile */}
                <div>
                  <div className="mb-2 flex items-center justify-between">
                    <p className="text-xs font-semibold uppercase tracking-wider text-muted">{t('member_detail.section_edit')}</p>
                    {!editing && (
                      <Button size="sm" variant="light" startContent={<Pencil size={14} />} onPress={() => setEditing(true)}>
                        {t('member_detail.edit_toggle')}
                      </Button>
                    )}
                  </div>
                  {editing ? (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                      <Input size="sm" variant="bordered" label={t('member_detail.edit_first_name')} value={form.first_name} onValueChange={(v) => setForm((f) => ({ ...f, first_name: v }))} />
                      <Input size="sm" variant="bordered" label={t('member_detail.edit_last_name')} value={form.last_name} onValueChange={(v) => setForm((f) => ({ ...f, last_name: v }))} />
                      <Input size="sm" variant="bordered" label={t('member_detail.edit_phone')} value={form.phone} onValueChange={(v) => setForm((f) => ({ ...f, phone: v }))} placeholder="+1 555 123 4567" />
                      <Input size="sm" variant="bordered" label={t('member_detail.edit_location')} value={form.location} onValueChange={(v) => setForm((f) => ({ ...f, location: v }))} />
                      <Input size="sm" variant="bordered" label={t('member_detail.edit_tagline')} value={form.tagline} onValueChange={(v) => setForm((f) => ({ ...f, tagline: v }))} className="sm:col-span-2" />
                      <Textarea size="sm" variant="bordered" label={t('member_detail.edit_bio')} value={form.bio} onValueChange={(v) => setForm((f) => ({ ...f, bio: v }))} minRows={2} className="sm:col-span-2" />
                      <div className="flex gap-2 sm:col-span-2">
                        <Button size="sm" color="primary" isLoading={busy === 'edit'} onPress={handleSaveEdit}>{t('member_detail.edit_save')}</Button>
                        <Button size="sm" variant="flat" isDisabled={busy === 'edit'} onPress={() => setEditing(false)}>{t('common.cancel')}</Button>
                      </div>
                    </div>
                  ) : (
                    <p className="text-sm text-muted">{detail.tagline || detail.bio || detail.location || '—'}</p>
                  )}
                </div>

                <Separator />

                {/* Compliance */}
                <div className="space-y-3">
                  <ComplianceList
                    title={t('member_detail.vetting_title')}
                    empty={t('member_detail.vetting_none')}
                    items={vetting.map((v) => ({
                      key: `v-${v.id}`,
                      label: t(`vetting.type_${v.vetting_type}`, { defaultValue: v.vetting_type }),
                      status: v.status,
                      expiry: v.expiry_date ?? null,
                    }))}
                  />
                  <ComplianceList
                    title={t('member_detail.insurance_title')}
                    empty={t('member_detail.insurance_none')}
                    items={insurance.map((i) => ({
                      key: `i-${i.id}`,
                      label: t(`insurance.type_${i.insurance_type}`, { defaultValue: i.insurance_type }),
                      status: i.status,
                      expiry: i.expiry_date ?? null,
                    }))}
                  />
                  <div>
                    <p className="mb-1 text-xs font-semibold uppercase tracking-wider text-muted">{t('member_detail.consents_title')}</p>
                    {consents.length === 0 ? (
                      <p className="text-sm text-muted">{t('member_detail.consents_none')}</p>
                    ) : (
                      <div className="flex flex-wrap gap-1.5">
                        {consents.map((c, idx) => (
                          <Chip key={`c-${idx}`} size="sm" variant="tertiary" color={consentGranted(c) ? 'success' : 'default'}>
                            {consentLabel(c) || t('member_detail.consent_generic')}
                          </Chip>
                        ))}
                      </div>
                    )}
                  </div>
                </div>
              </ModalBody>

              <ModalFooter>
                <Button variant="flat" onPress={onClose}>{t('member_detail.close')}</Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Balance adjustment sub-modal */}
      <Modal isOpen={balanceOpen} onClose={() => setBalanceOpen(false)} size="sm">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <Coins size={18} className="text-primary" />
            {t('member_detail.balance_modal_title')}
          </ModalHeader>
          <ModalBody className="gap-3">
            <Input
              type="number"
              variant="bordered"
              label={t('member_detail.balance_amount_label')}
              description={t('member_detail.balance_amount_help')}
              value={balanceAmount}
              onValueChange={setBalanceAmount}
              placeholder="0"
            />
            <Textarea
              variant="bordered"
              label={t('member_detail.balance_reason_label')}
              placeholder={t('member_detail.balance_reason_placeholder')}
              value={balanceReason}
              onValueChange={setBalanceReason}
              minRows={2}
              isRequired
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" isDisabled={busy === 'balance'} onPress={() => setBalanceOpen(false)}>{t('common.cancel')}</Button>
            <Button color="primary" isLoading={busy === 'balance'} onPress={handleAdjustBalance}>{t('member_detail.balance_submit')}</Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
}

function Overview({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-lg bg-surface-secondary p-2.5">
      <p className="text-xs text-muted">{label}</p>
      <p className="mt-0.5 text-sm font-medium text-foreground">{value}</p>
    </div>
  );
}

interface ComplianceItem { key: string; label: string; status?: string; expiry: string | null }

function ComplianceList({ title, empty, items }: { title: string; empty: string; items: ComplianceItem[] }) {
  const { t } = useTranslation('broker');
  return (
    <div>
      <p className="mb-1 text-xs font-semibold uppercase tracking-wider text-muted">{title}</p>
      {items.length === 0 ? (
        <p className="text-sm text-muted">{empty}</p>
      ) : (
        <div className="space-y-1">
          {items.map((it) => (
            <div key={it.key} className="flex items-center justify-between rounded-md bg-surface-secondary px-2.5 py-1.5 text-sm">
              <span className="truncate">{it.label}</span>
              <span className="flex items-center gap-2">
                {it.status && (
                  <Chip size="sm" variant="tertiary" color={it.status === 'verified' ? 'success' : it.status === 'expired' || it.status === 'rejected' ? 'danger' : 'warning'}>
                    {t(`vetting.status_${it.status}`, { defaultValue: it.status })}
                  </Chip>
                )}
                {it.expiry && <span className="text-xs text-muted">{formatServerDateTime(it.expiry)}</span>}
              </span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

export default MemberDetailModal;
