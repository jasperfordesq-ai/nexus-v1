// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * LegalAcceptanceGate
 *
 * Blocking modal that appears when the current user has legal documents
 * they haven't yet accepted (or that have been updated since their last
 * acceptance). Users must accept before accessing any protected page.
 *
 * If the tenant has no active legal documents with `requires_acceptance = 1`,
 * this component never renders — `hasPending` will always be false.
 */

import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Chip,
} from '@heroui/react';
import FileText from 'lucide-react/icons/file-text';
import ExternalLink from 'lucide-react/icons/external-link';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import type { PendingDocument } from '@/hooks/useLegalGate';
import { useTenant } from '@/contexts';

// Label keys are resolved via i18n below; this map serves as fallback
const TYPE_LABEL_KEYS: Record<string, string> = {
  terms:                'gate.type_terms',
  privacy:              'gate.type_privacy',
  cookies:              'gate.type_cookies',
  accessibility:        'gate.type_accessibility',
  community_guidelines: 'gate.type_community_guidelines',
  acceptable_use:       'gate.type_acceptable_use',
};

interface LegalAcceptanceGateProps {
  pendingDocs: PendingDocument[];
  onAcceptAll: () => Promise<void>;
  isAccepting: boolean;
}

export function LegalAcceptanceGate({
  pendingDocs,
  onAcceptAll,
  isAccepting,
}: LegalAcceptanceGateProps) {
  const { t } = useTranslation('legal');
  const { tenantPath } = useTenant();

  const handleAccept = async () => {
    await onAcceptAll();
  };

  return (
    <Modal
      isOpen
      isDismissable={false}
      hideCloseButton
      size="md"
      classNames={{ backdrop: 'bg-black/70' }}
      aria-labelledby="legal-gate-title"
    >
      <ModalContent>
        <ModalHeader id="legal-gate-title" className="flex flex-col gap-1">
          <div className="flex items-center gap-2">
            <FileText className="w-5 h-5 text-warning shrink-0" aria-hidden="true" />
            <span>{t('gate.title')}</span>
          </div>
          <p className="text-sm font-normal text-foreground-500">
            {pendingDocs.length === 1
              ? t('gate.subtitle_one')
              : t('gate.subtitle_other', { count: pendingDocs.length })}
          </p>
        </ModalHeader>

        <ModalBody className="gap-3">
          {pendingDocs.map((doc) => {
            const labelKey = TYPE_LABEL_KEYS[doc.document_type];
            const label = labelKey ? t(labelKey) : doc.title;
            const linkPath = tenantPath(`/${doc.document_type.replace('_', '-')}`);
            return (
              <div
                key={doc.document_id}
                className="flex items-center justify-between gap-3 p-3 rounded-lg bg-content2"
              >
                <div className="flex items-center gap-2 min-w-0">
                  <FileText className="w-4 h-4 text-foreground-400 shrink-0" aria-hidden="true" />
                  <span className="text-sm font-medium truncate">{label}</span>
                  {doc.acceptance_status === 'outdated' && (
                    <Chip color="warning" variant="flat" size="sm" className="shrink-0">
                      {t('gate.updated')}
                    </Chip>
                  )}
                </div>
                <Link
                  to={linkPath}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex items-center gap-1 text-xs text-primary underline shrink-0 focus:outline-none focus:ring-2 focus:ring-primary rounded"
                >
                  {t('gate.read')}
                  <ExternalLink className="w-3 h-3" aria-hidden="true" />
                </Link>
              </div>
            );
          })}
        </ModalBody>

        <ModalFooter>
          <p className="text-xs text-foreground-400 flex-1">
            {t('gate.consent_text')}
          </p>
          <Button
            color="primary"
            onPress={handleAccept}
            isLoading={isAccepting}
            isDisabled={isAccepting}
            aria-label={t('gate.accept_aria')}
          >
            {isAccepting ? t('gate.accepting') : t('gate.accept_continue')}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

export default LegalAcceptanceGate;
