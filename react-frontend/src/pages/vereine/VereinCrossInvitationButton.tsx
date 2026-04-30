// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * VereinCrossInvitationButton — AG55
 *
 * Inline button on profile pages — visible only when the auth'd viewer
 * shares membership in a federated Verein with the target user. Opens a
 * modal to send a cross-Verein invitation to one of the network Vereine.
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Button,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Select,
  SelectItem,
  Textarea,
} from '@heroui/react';
import UserPlus from 'lucide-react/icons/user-plus';
import { useAuth, useFeature, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

interface SharedVereinDto {
  source_organization_id: number;
  source_name: string;
  network: { organization_id: number; name: string }[];
}

interface Props {
  /** The profile being viewed (potential invitee) */
  userId: number;
}

export default function VereinCrossInvitationButton({ userId }: Props) {
  const { t } = useTranslation('common');
  const { isAuthenticated, user: currentUser } = useAuth();
  const hasCaringCommunity = useFeature('caring_community');
  const toast = useToast();

  const [shared, setShared] = useState<SharedVereinDto[]>([]);
  const [open, setOpen] = useState(false);
  const [targetOrgId, setTargetOrgId] = useState<string>('');
  const [sourceOrgId, setSourceOrgId] = useState<number | null>(null);
  const [message, setMessage] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (!isAuthenticated || !hasCaringCommunity || !currentUser || currentUser.id === userId) {
      setShared([]);
      return;
    }
    let cancelled = false;
    void (async () => {
      try {
        const res = await api.get<SharedVereinDto[]>(`/v2/vereine/cross-invite-targets/${userId}`);
        if (!cancelled && res.success && Array.isArray(res.data)) {
          setShared(res.data);
        }
      } catch {
        // If endpoint isn't available yet, silently no-op (button just hides)
        if (!cancelled) setShared([]);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [isAuthenticated, hasCaringCommunity, userId, currentUser]);

  const allTargets = useMemo(() => {
    return shared.flatMap((s) =>
      s.network.map((n) => ({
        sourceOrgId: s.source_organization_id,
        sourceName: s.source_name,
        targetOrgId: n.organization_id,
        targetName: n.name,
      })),
    );
  }, [shared]);

  const handleSelectTarget = useCallback((value: string) => {
    setTargetOrgId(value);
    const match = allTargets.find((tg) => String(tg.targetOrgId) === value);
    setSourceOrgId(match?.sourceOrgId ?? null);
  }, [allTargets]);

  const handleSubmit = useCallback(async () => {
    if (!sourceOrgId || !targetOrgId) return;
    setSubmitting(true);
    try {
      const res = await api.post(
        `/v2/vereine/${sourceOrgId}/cross-invitations`,
        {
          target_organization_id: Number(targetOrgId),
          invitee_user_id: userId,
          message: message.trim() || null,
        },
      );
      if (res.success) {
        toast.success(t('verein_federation.invite_sent'));
        setOpen(false);
        setMessage('');
        setTargetOrgId('');
      } else {
        toast.error(res.error || t('verein_federation.invite_failed'));
      }
    } catch (err) {
      logError('VereinCrossInvitationButton: send failed', err);
      toast.error(t('verein_federation.invite_failed'));
    } finally {
      setSubmitting(false);
    }
  }, [sourceOrgId, targetOrgId, userId, message, toast, t]);

  if (allTargets.length === 0) return null;

  // Display first available target's name in label as a hint
  const primary = allTargets[0];

  return (
    <>
      <Button
        size="sm"
        variant="flat"
        color="primary"
        startContent={<UserPlus className="w-4 h-4" />}
        onPress={() => setOpen(true)}
      >
        {t('verein_federation.invite_to_verein_button', { name: primary?.targetName ?? '' })}
      </Button>

      <Modal isOpen={open} onClose={() => setOpen(false)} size="md">
        <ModalContent>
          {(close) => (
            <>
              <ModalHeader className="flex flex-col gap-1">
                {t('verein_federation.invite_modal_title')}
                <span className="text-sm font-normal text-default-500">
                  {t('verein_federation.invite_modal_subtitle')}
                </span>
              </ModalHeader>
              <ModalBody>
                <Select
                  label={t('verein_federation.invite_target_label')}
                  selectedKeys={targetOrgId ? [targetOrgId] : []}
                  onChange={(e) => handleSelectTarget(e.target.value)}
                >
                  {allTargets.map((tg) => (
                    <SelectItem key={String(tg.targetOrgId)} textValue={tg.targetName}>
                      {tg.targetName}
                    </SelectItem>
                  ))}
                </Select>

                <Textarea
                  label={t('verein_federation.invite_message_label')}
                  placeholder={t('verein_federation.invite_message_placeholder')}
                  value={message}
                  onValueChange={(v) => setMessage(v.slice(0, 500))}
                  maxRows={5}
                  description={t('verein_federation.char_count', { count: message.length })}
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={close}>
                  {t('cancel')}
                </Button>
                <Button
                  color="primary"
                  onPress={() => void handleSubmit()}
                  isLoading={submitting}
                  isDisabled={!targetOrgId}
                >
                  {t('verein_federation.invite_send')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </>
  );
}
