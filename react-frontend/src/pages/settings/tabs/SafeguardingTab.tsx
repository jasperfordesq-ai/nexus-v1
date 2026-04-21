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

import { useState, useEffect, useCallback } from 'react';
import {
  Button,
  Chip,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Spinner,
  useDisclosure,
} from '@heroui/react';
import { Shield, Trash2, CheckCircle2, Lock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

interface MemberPreference {
  preference_id: number;
  option_id: number;
  option_key: string;
  label: string;
  description: string | null;
  selected_value: string;
  consent_given_at: string | null;
  created_at: string | null;
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

export function SafeguardingTab() {
  const { t } = useTranslation('settings');
  const toast = useToast();
  const confirmModal = useDisclosure();

  const [loading, setLoading] = useState(true);
  const [preferences, setPreferences] = useState<MemberPreference[]>([]);
  const [revokingId, setRevokingId] = useState<number | null>(null);
  const [pendingRevoke, setPendingRevoke] = useState<MemberPreference | null>(null);

  const loadPreferences = useCallback(async () => {
    try {
      setLoading(true);
      const res = await api.get<MyPreferencesResponse>('/v2/safeguarding/my-preferences');
      if (res.success && res.data) {
        setPreferences(res.data.preferences ?? []);
      } else {
        setPreferences([]);
      }
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

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <GlassCard className="p-6">
        <div className="flex items-center gap-3 mb-4">
          <div className="p-3 rounded-xl bg-blue-500/20">
            <Shield className="w-6 h-6 text-blue-600 dark:text-blue-400" />
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

              return (
                <div
                  key={pref.preference_id}
                  className="p-4 rounded-lg border border-theme-default bg-theme-surface"
                >
                  <div className="flex items-start gap-3">
                    <CheckCircle2 className="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" />
                    <div className="flex-1 min-w-0">
                      <p className="font-medium text-sm text-theme-primary">
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
                            date: new Date(pref.consent_given_at).toLocaleDateString('en-GB'),
                          })}
                        </p>
                      )}
                      {activationChips.length > 0 && (
                        <div className="flex flex-wrap gap-1 mt-2">
                          {activationChips.map((label, idx) => (
                            <Chip key={idx} size="sm" variant="flat" color="warning">
                              {label}
                            </Chip>
                          ))}
                        </div>
                      )}
                    </div>
                    <Button
                      size="sm"
                      variant="light"
                      color="danger"
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
                <Button variant="light" onPress={onClose}>
                  {t('safeguarding.revoke_confirm_no')}
                </Button>
                <Button
                  color="danger"
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
