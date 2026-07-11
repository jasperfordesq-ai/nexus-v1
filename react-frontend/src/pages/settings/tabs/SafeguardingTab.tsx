// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SafeguardingTab — member self-service view of their safeguarding preferences.
 *
 * Safeguarding Ireland adult-autonomy principle: adults who self-identify as
 * requiring protections have the right to view and revoke those preferences
 * without admin involvement. This tab wires the React UI to the existing
 * SafeguardingPreferenceService::revokePreference backend endpoint.
 */

import { getFormattingLocale } from '@/lib/helpers';
import { useState, useEffect, useCallback } from 'react';

import Shield from 'lucide-react/icons/shield';
import Trash2 from 'lucide-react/icons/trash-2';
import CheckCircle2 from 'lucide-react/icons/circle-check';
import MinusCircle from 'lucide-react/icons/circle-minus';
import Lock from 'lucide-react/icons/lock';
import TriangleAlert from 'lucide-react/icons/triangle-alert';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Chip } from '@/components/ui/Chip';
import { GlassCard } from '@/components/ui/GlassCard';
import { Modal, ModalBody, ModalContent, ModalFooter, ModalHeader } from '@/components/ui/Modal';
import { Spinner } from '@/components/ui/Spinner';
import { useDisclosure } from '@/components/ui/useDisclosure';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

export interface MemberPreference {
  preference_id: number;
  option_id: number;
  option_key: string;
  label: string;
  description: string | null;
  selected_value: string;
  consent_given_at: string | null;
  created_at: string | null;
  policy_review_required?: boolean;
  policy_review_reason_code?: string | null;
  activations: {
    requires_broker_approval: boolean;
    restricts_messaging: boolean;
    restricts_matching: boolean;
    requires_vetted_interaction: boolean;
    vetting_type_required: string | null;
  };
}

interface MyPreferencesResponse {
  preferences: MemberPreference[];
  count: number;
}

interface MyVettingStatus {
  policy: {
    configured: boolean;
    contact_policy_available: boolean;
    jurisdiction: string;
    label: string;
    attestation_code: string | null;
    attestation_label: string | null;
    purpose_code: string;
  };
  decision: 'confirmed' | 'revoked' | 'not_confirmed';
  review_status: 'pending' | 'resolved' | null;
  confirmed_at: string | null;
  revoked_at?: string | null;
}

export function SafeguardingTab() {
  const { t } = useTranslation('settings');
  const toast = useToast();
  const confirmModal = useDisclosure();

  const [loading, setLoading] = useState(true);
  const [preferences, setPreferences] = useState<MemberPreference[]>([]);
  const [vettingStatus, setVettingStatus] = useState<MyVettingStatus | null>(null);
  const [isRequestingReview, setIsRequestingReview] = useState(false);
  const [isConfirmingPolicyReview, setIsConfirmingPolicyReview] = useState(false);
  const [revokingId, setRevokingId] = useState<number | null>(null);
  const [pendingRevoke, setPendingRevoke] = useState<MemberPreference | null>(null);

  const loadPreferences = useCallback(async () => {
    try {
      setLoading(true);
      const [preferencesResponse, vettingResponse] = await Promise.all([
        api.get<MyPreferencesResponse>('/v2/safeguarding/my-preferences'),
        api.get<MyVettingStatus>('/v2/safeguarding/my-vetting-status'),
      ]);
      if (preferencesResponse.success && preferencesResponse.data) {
        setPreferences(preferencesResponse.data.preferences ?? []);
      } else {
        setPreferences([]);
      }
      setVettingStatus(vettingResponse.success && vettingResponse.data ? vettingResponse.data : null);
    } catch (error) {
      logError('Failed to load safeguarding preferences', error);
      setPreferences([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadPreferences();
  }, [loadPreferences]);

  const handleRevokeClick = (pref: MemberPreference) => {
    setPendingRevoke(pref);
    confirmModal.onOpen();
  };

  const handleRevokeConfirm = useCallback(async () => {
    if (!pendingRevoke) return;
    const optionId = pendingRevoke.option_id;
    try {
      setRevokingId(optionId);
      const res = await api.post('/v2/safeguarding/revoke', {
        option_id: optionId,
      });

      if (res.success) {
        toast.success(t('safeguarding.revoked_toast'));
        setPreferences(prev => prev.filter(p => p.option_id !== optionId));
      } else {
        toast.error(
          t('safeguarding.revoke_error_toast'),
          res.error || ''
        );
      }
    } catch (error) {
      logError('Revoke safeguarding preference failed', error);
      toast.error(t('safeguarding.revoke_error_toast'));
    } finally {
      setRevokingId(null);
      setPendingRevoke(null);
      confirmModal.onClose();
    }
  }, [pendingRevoke, t, toast, confirmModal]);

  const handleRequestReview = useCallback(async () => {
    if (!vettingStatus?.policy.configured || !vettingStatus.policy.contact_policy_available || isRequestingReview) return;

    setIsRequestingReview(true);
    try {
      const response = await api.post('/v2/safeguarding/vetting-review-request');
      if (!response.success) {
        toast.error(t('safeguarding.vetting.review_error'));
        return;
      }

      setVettingStatus((current) => current ? { ...current, review_status: 'pending' } : current);
      toast.success(t('safeguarding.vetting.review_requested_toast'));
    } catch (error) {
      logError('Safeguarding vetting review request failed', error);
      toast.error(t('safeguarding.vetting.review_error'));
    } finally {
      setIsRequestingReview(false);
    }
  }, [isRequestingReview, t, toast, vettingStatus]);

  const handleConfirmPolicyReview = useCallback(async () => {
    if (isConfirmingPolicyReview) return;
    setIsConfirmingPolicyReview(true);
    try {
      const response = await api.post('/v2/safeguarding/confirm-policy-review');
      if (!response.success) {
        toast.error(t('safeguarding.policy_review_error'));
        return;
      }
      setPreferences(current => current.map(preference => ({
        ...preference,
        policy_review_required: false,
        policy_review_reason_code: null,
      })));
      toast.success(t('safeguarding.policy_review_confirmed'));
    } catch (error) {
      logError('Confirm safeguarding policy review failed', error);
      toast.error(t('safeguarding.policy_review_error'));
    } finally {
      setIsConfirmingPolicyReview(false);
    }
  }, [isConfirmingPolicyReview, t, toast]);

  if (loading) {
    return (
      <div role="status" aria-busy="true" aria-label={t('loading', { ns: 'common' })} className="flex items-center justify-center py-12">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <GlassCard className="p-6">
        <div className="flex items-center gap-3 mb-4">
          <div className="p-3 rounded-xl bg-blue-500/20">
            <Shield className="w-6 h-6 text-blue-600 dark:text-blue-400" aria-hidden="true" />
          </div>
          <div>
            <h2 className="text-lg font-semibold text-theme-primary">
              {t('safeguarding.page_title')}
            </h2>
            <p className="text-sm text-theme-muted">
              {t('safeguarding.intro')}
            </p>
          </div>
        </div>

        {preferences.some(preference => preference.policy_review_required) && (
          <div className="mb-6 rounded-xl border border-warning-300 bg-warning-50 p-4 text-warning-900 dark:border-warning-700 dark:bg-warning-950/30 dark:text-warning-100" role="status">
            <div className="flex items-start gap-3">
              <TriangleAlert className="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
              <div className="min-w-0 flex-1">
                <h3 className="text-sm font-semibold">{t('safeguarding.policy_review_title')}</h3>
                <p className="mt-1 text-sm">{t('safeguarding.policy_review_body')}</p>
                <Button
                  className="mt-3"
                  size="sm"
                  color="warning"
                  isLoading={isConfirmingPolicyReview}
                  isDisabled={isConfirmingPolicyReview}
                  onPress={handleConfirmPolicyReview}
                >
                  {t('safeguarding.policy_review_confirm')}
                </Button>
              </div>
            </div>
          </div>
        )}

        <div className="mb-6 rounded-xl border border-theme-default bg-theme-surface p-4">
          <div className="flex items-start gap-3">
            <Shield className="mt-0.5 h-5 w-5 shrink-0 text-accent" aria-hidden="true" />
            <div className="min-w-0 flex-1">
              <h3 className="text-sm font-semibold text-theme-primary">
                {t('safeguarding.vetting.title')}
              </h3>
              {!vettingStatus ? (
                <>
                  <p className="mt-1 text-sm text-theme-muted">{t('safeguarding.vetting.status_unavailable')}</p>
                  <p className="mt-2 text-xs text-theme-muted">{t('safeguarding.vetting.no_documents')}</p>
                </>
              ) : (
                <>
                  <div className="mt-2 flex flex-wrap items-center gap-2">
                    <Chip
                      size="sm"
                      variant="soft"
                      color={
                        vettingStatus.decision === 'confirmed'
                          ? 'success'
                          : vettingStatus.decision === 'revoked'
                            ? 'danger'
                            : vettingStatus.review_status === 'pending'
                              ? 'warning'
                              : 'default'
                      }
                    >
                      {t(`safeguarding.vetting.status_${
                        vettingStatus.review_status === 'pending' ? 'review_requested' : vettingStatus.decision
                      }`)}
                    </Chip>
                    {vettingStatus.policy.attestation_label && (
                      <span className="text-sm text-theme-muted">{vettingStatus.policy.attestation_label}</span>
                    )}
                  </div>
                  <p className="mt-2 text-sm leading-6 text-theme-muted">
                    {vettingStatus.decision === 'confirmed' && vettingStatus.confirmed_at
                      ? t('safeguarding.vetting.confirmed_on', {
                          date: new Date(vettingStatus.confirmed_at).toLocaleDateString(getFormattingLocale()),
                        })
                      : vettingStatus.review_status === 'pending'
                        ? t('safeguarding.vetting.review_pending_body')
                        : vettingStatus.policy.configured && vettingStatus.policy.contact_policy_available
                          ? t('safeguarding.vetting.not_confirmed_body')
                          : t('safeguarding.vetting.policy_unavailable_body')}
                  </p>
                  <p className="mt-2 text-xs leading-5 text-theme-muted">{t('safeguarding.vetting.no_documents')}</p>
                  {vettingStatus.decision !== 'confirmed' && vettingStatus.policy.configured && vettingStatus.policy.contact_policy_available && (
                    <Button
                      className="mt-3"
                      size="sm"
                      variant="secondary"
                      isPending={isRequestingReview}
                      isDisabled={vettingStatus.review_status === 'pending'}
                      onPress={handleRequestReview}
                    >
                      {vettingStatus.review_status === 'pending'
                        ? t('safeguarding.vetting.review_requested_button')
                        : t('safeguarding.vetting.request_review_button')}
                    </Button>
                  )}
                </>
              )}
            </div>
          </div>
        </div>

        {preferences.length === 0 ? (
          <div className="p-6 text-center rounded-lg bg-theme-elevated border border-theme-default">
            <Lock className="w-8 h-8 mx-auto mb-2 text-theme-muted opacity-50" />
            <p className="text-sm text-theme-muted">
              {t('safeguarding.no_preferences')}
            </p>
          </div>
        ) : (
          <div className="space-y-3">
            {preferences.map(pref => {
              const activationChips: string[] = [];
              if (pref.activations.requires_broker_approval) {
                activationChips.push(t('safeguarding.chip_broker_review'));
              }
              if (pref.activations.restricts_matching) {
                activationChips.push(t('safeguarding.chip_match_approval'));
              }
              if (pref.activations.requires_vetted_interaction) {
                activationChips.push(t('safeguarding.chip_vetted_only'));
              }

              const isDeclination = pref.option_key === 'none_apply';

              return (
                <div
                  key={pref.preference_id}
                  className="p-4 rounded-lg border border-theme-default bg-theme-surface"
                >
                  <div className="flex items-start gap-3">
                    {isDeclination
                      ? <MinusCircle className="w-5 h-5 text-theme-muted shrink-0 mt-0.5" />
                      : <CheckCircle2 className="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" />
                    }
                    <div className="flex-1 min-w-0">
                      <p className={`font-medium text-sm ${isDeclination ? 'text-theme-muted' : 'text-theme-primary'}`}>
                        {pref.label}
                      </p>
                      {pref.description && (
                        <p className="text-xs text-theme-muted mt-1 leading-relaxed">
                          {pref.description}
                        </p>
                      )}
                      {pref.consent_given_at && (
                        <p className="text-xs text-theme-muted mt-2">
                          {t('safeguarding.selected_on', {
                            date: new Date(pref.consent_given_at).toLocaleDateString(getFormattingLocale()),
                          })}
                        </p>
                      )}
                      {/* Never show activation chips for the declination option */}
                      {!isDeclination && activationChips.length > 0 && (
                        <div className="flex flex-wrap gap-1 mt-2">
                          {activationChips.map((label) => (
                            <Chip key={label} size="sm" variant="soft" color="warning">
                              {label}
                            </Chip>
                          ))}
                        </div>
                      )}
                    </div>
                    <Button
                      size="sm"
                      variant="danger-soft"
                      className="shrink-0"
                      onPress={() => handleRevokeClick(pref)}
                      isLoading={revokingId === pref.option_id}
                      startContent={revokingId !== pref.option_id ? <Trash2 className="w-3 h-3" /> : undefined}
                    >
                      {t('safeguarding.revoke_button')}
                    </Button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </GlassCard>

      {/* Confirm revocation */}
      <Modal isOpen={confirmModal.isOpen} onOpenChange={confirmModal.onOpenChange}>
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>
                {t('safeguarding.revoke_confirm_title')}
              </ModalHeader>
              <ModalBody>
                <p className="text-sm text-theme-secondary">
                  {t('safeguarding.revoke_confirm_body')}
                </p>
                {pendingRevoke && (
                  <p className="text-sm font-medium text-theme-primary mt-2">
                    {pendingRevoke.label}
                  </p>
                )}
              </ModalBody>
              <ModalFooter>
                <Button variant="tertiary" onPress={onClose}>
                  {t('safeguarding.revoke_confirm_no')}
                </Button>
                <Button
                  variant="danger"
                  onPress={handleRevokeConfirm}
                  isLoading={revokingId !== null}
                >
                  {t('safeguarding.revoke_confirm_yes')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default SafeguardingTab;
