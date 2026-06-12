// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GuardianConsentModal — shown when a volunteering action returns
 * GUARDIAN_CONSENT_REQUIRED (the member is under 18 with no active
 * guardian consent). Lets the minor send their parent/guardian a secure
 * approval link, or shows the pending state when a request is already out.
 *
 * API: GET  /api/v2/volunteering/guardian-consents       (my consents)
 *      POST /api/v2/volunteering/guardian-consents       (request consent)
 */

import { useState, useEffect } from 'react';
import {
  Button,
  Input,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Select,
  SelectItem,
  Spinner,
} from '@/components/ui';
import ShieldCheck from 'lucide-react/icons/shield-check';
import MailCheck from 'lucide-react/icons/mail-check';
import Hourglass from 'lucide-react/icons/hourglass';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

const RELATIONSHIPS = ['parent', 'guardian', 'legal_guardian', 'carer'] as const;

interface ConsentRecord {
  id: number;
  guardian_email: string;
  status: 'pending' | 'active' | 'expired' | 'withdrawn';
  expires_at?: string | null;
}

interface GuardianConsentModalProps {
  isOpen: boolean;
  onOpenChange: (open: boolean) => void;
  onClose: () => void;
  /** Scope the consent to one opportunity; omitted = general consent. */
  opportunityId?: number;
}

type Stage = 'loading' | 'form' | 'pending' | 'sent';

export default function GuardianConsentModal({ isOpen, onOpenChange, onClose, opportunityId }: GuardianConsentModalProps) {
  const { t } = useTranslation('volunteering');
  const toast = useToast();

  const [stage, setStage] = useState<Stage>('loading');
  const [pendingEmail, setPendingEmail] = useState('');
  const [guardianName, setGuardianName] = useState('');
  const [guardianEmail, setGuardianEmail] = useState('');
  const [guardianPhone, setGuardianPhone] = useState('');
  const [relationship, setRelationship] = useState<string>('parent');
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    if (!isOpen) return;
    let cancelled = false;
    setStage('loading');
    (async () => {
      try {
        const res = await api.get('/v2/volunteering/guardian-consents');
        if (cancelled) return;
        const records = Array.isArray(res.data) ? (res.data as ConsentRecord[]) : [];
        const pending = records.find((c) => c.status === 'pending');
        if (pending) {
          setPendingEmail(pending.guardian_email);
          setStage('pending');
        } else {
          setStage('form');
        }
      } catch (err) {
        logError('Failed to load guardian consents', err);
        if (!cancelled) setStage('form');
      }
    })();
    return () => { cancelled = true; };
  }, [isOpen]);

  async function handleSubmit() {
    setIsSubmitting(true);
    try {
      const body: Record<string, unknown> = {
        guardian_name: guardianName.trim(),
        guardian_email: guardianEmail.trim(),
        relationship,
      };
      if (guardianPhone.trim()) body.guardian_phone = guardianPhone.trim();
      if (opportunityId) body.opportunity_id = opportunityId;

      const res = await api.post('/v2/volunteering/guardian-consents', body);
      if (res.success) {
        setPendingEmail(guardianEmail.trim());
        setStage('sent');
      } else {
        toast.error(res.error || t('guardian.request_failed'));
      }
    } catch (err) {
      logError('Failed to request guardian consent', err);
      toast.error(t('guardian.request_failed'));
    } finally {
      setIsSubmitting(false);
    }
  }

  const formValid = guardianName.trim().length > 0 && /\S+@\S+\.\S+/.test(guardianEmail.trim());

  return (
    <Modal isOpen={isOpen} onOpenChange={onOpenChange}>
      <ModalContent>
        {() => (
          <>
            <ModalHeader>
              <div className="flex items-center gap-2">
                <ShieldCheck className="w-5 h-5 text-[var(--color-primary)]" aria-hidden="true" />
                {stage === 'pending'
                  ? t('guardian.pending_title')
                  : stage === 'sent'
                    ? t('guardian.request_sent_title')
                    : t('guardian.consent_required_title')}
              </div>
            </ModalHeader>
            <ModalBody>
              {stage === 'loading' && (
                <div className="flex justify-center py-8">
                  <Spinner aria-label={t('guardian.verify_loading')} />
                </div>
              )}

              {stage === 'pending' && (
                <div className="flex items-start gap-3 py-2">
                  <Hourglass className="w-6 h-6 text-[var(--color-warning)] shrink-0 mt-0.5" aria-hidden="true" />
                  <p className="text-theme-muted">{t('guardian.pending_body', { email: pendingEmail })}</p>
                </div>
              )}

              {stage === 'sent' && (
                <div className="flex items-start gap-3 py-2">
                  <MailCheck className="w-6 h-6 text-[var(--color-success)] shrink-0 mt-0.5" aria-hidden="true" />
                  <p className="text-theme-muted">{t('guardian.request_sent_body', { email: pendingEmail })}</p>
                </div>
              )}

              {stage === 'form' && (
                <div className="space-y-4">
                  <p className="text-theme-muted text-sm">{t('guardian.consent_required_body')}</p>
                  <Input
                    label={t('guardian.guardian_name')}
                    value={guardianName}
                    onValueChange={setGuardianName}
                    isRequired
                  />
                  <Input
                    label={t('guardian.guardian_email')}
                    type="email"
                    value={guardianEmail}
                    onValueChange={setGuardianEmail}
                    isRequired
                  />
                  <Input
                    label={t('guardian.guardian_phone')}
                    type="tel"
                    value={guardianPhone}
                    onValueChange={setGuardianPhone}
                  />
                  <Select
                    label={t('guardian.relationship')}
                    selectedKeys={[relationship]}
                    onSelectionChange={(keys) => {
                      const val = Array.from(keys)[0] as string;
                      if (val) setRelationship(val);
                    }}
                    isRequired
                  >
                    {RELATIONSHIPS.map((rel) => (
                      <SelectItem key={rel} id={rel}>{t(`guardian.relationship_${rel}`)}</SelectItem>
                    ))}
                  </Select>
                </div>
              )}
            </ModalBody>
            <ModalFooter>
              {stage === 'pending' && (
                <Button variant="tertiary" onPress={() => setStage('form')}>
                  {t('guardian.resend')}
                </Button>
              )}
              {stage === 'form' && (
                <Button
                  color="primary"
                  onPress={handleSubmit}
                  isDisabled={!formValid || isSubmitting}
                  isLoading={isSubmitting}
                >
                  {isSubmitting ? t('guardian.sending') : t('guardian.send_request')}
                </Button>
              )}
              <Button variant="tertiary" onPress={onClose}>
                {t('guardian.close')}
              </Button>
            </ModalFooter>
          </>
        )}
      </ModalContent>
    </Modal>
  );
}
