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
import { FileText, ExternalLink } from 'lucide-react';
import { Link } from 'react-router-dom';
import type { PendingDocument } from '@/hooks/useLegalGate';
import { useTenant } from '@/contexts';

const TYPE_LABELS: Record<string, string> = {
  terms:                'Terms of Service',
  privacy:              'Privacy Policy',
  cookies:              'Cookie Policy',
  accessibility:        'Accessibility Statement',
  community_guidelines: 'Community Guidelines',
  acceptable_use:       'Acceptable Use Policy',
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
            <span>Updated legal documents</span>
          </div>
          <p className="text-sm font-normal text-foreground-500">
            {pendingDocs.length === 1
              ? 'A document has been updated. Please review and accept it to continue.'
              : `${pendingDocs.length} documents have been updated. Please review and accept them to continue.`}
          </p>
        </ModalHeader>

        <ModalBody className="gap-3">
          {pendingDocs.map((doc) => {
            const label = TYPE_LABELS[doc.document_type] ?? doc.title;
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
                      Updated
                    </Chip>
                  )}
                </div>
                <Link
                  to={linkPath}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex items-center gap-1 text-xs text-primary underline shrink-0 focus:outline-none focus:ring-2 focus:ring-primary rounded"
                >
                  Read
                  <ExternalLink className="w-3 h-3" aria-hidden="true" />
                </Link>
              </div>
            );
          })}
        </ModalBody>

        <ModalFooter>
          <p className="text-xs text-foreground-400 flex-1">
            By clicking Accept, you confirm you have read and agree to the documents listed above.
          </p>
          <Button
            color="primary"
            onPress={handleAccept}
            isLoading={isAccepting}
            isDisabled={isAccepting}
            aria-label="Accept all updated legal documents and continue"
          >
            {isAccepting ? 'Accepting…' : 'Accept & Continue'}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

export default LegalAcceptanceGate;
