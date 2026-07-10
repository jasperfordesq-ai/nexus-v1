// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState } from 'react';
import Globe from 'lucide-react/icons/globe';
import { useTranslation } from 'react-i18next';
import {
  Button,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  ModalHeading,
  Radio,
  RadioGroup,
  SearchField,
  Spinner,
} from '@/components/ui';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

export interface FederationPeer {
  id: number;
  slug: string;
  display_name: string;
  base_url: string;
  region: string | null;
  member_count_bucket: string | null;
  accepts_inbound_transfers: boolean;
}

interface FederationDirectoryResponse {
  peers: FederationPeer[];
}

interface Props {
  isOpen: boolean;
  onClose: () => void;
  onSelect: (peer: FederationPeer) => void;
}

export function FederationCommunityPicker({ isOpen, onClose, onSelect }: Props) {
  const { t } = useTranslation('caring_community');

  const [peers, setPeers] = useState<FederationPeer[]>([]);
  const [loading, setLoading] = useState(false);
  const [query, setQuery] = useState('');
  const [selectedSlug, setSelectedSlug] = useState<string | null>(null);

  const loadPeers = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<FederationDirectoryResponse>(
        '/v2/caring-community/federation-directory',
      );
      if (res.success && res.data) {
        setPeers(res.data.peers ?? []);
      } else {
        setPeers([]);
      }
    } catch (err) {
      logError('FederationCommunityPicker: load peers failed', err);
      setPeers([]);
    } finally {
      setLoading(false);
    }
  }, []);

  // Reset the selection and search query when the modal opens. Done during
  // render with a prev-prop comparison (not useEffect) so the picker never
  // shows a stale selection/query from a previous open.
  const [prevIsOpen, setPrevIsOpen] = useState(isOpen);
  if (isOpen !== prevIsOpen) {
    setPrevIsOpen(isOpen);
    if (isOpen) {
      setSelectedSlug(null);
      setQuery('');
    }
  }

  // Fetch the federation directory when the modal opens. Real network call, so
  // it stays in an effect.
  useEffect(() => {
    if (isOpen) {
      void loadPeers();
    }
  }, [isOpen, loadPeers]);

  const filteredPeers = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (q === '') return peers;
    return peers.filter(
      (p) =>
        p.display_name.toLowerCase().includes(q) ||
        (p.region ?? '').toLowerCase().includes(q),
    );
  }, [peers, query]);

  const handleConfirm = () => {
    const peer = peers.find((p) => p.slug === selectedSlug);
    if (peer) {
      onSelect(peer);
      onClose();
    }
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} size="lg" scrollBehavior="inside">
      <ModalContent>
        <ModalHeader className="flex items-center gap-2">
          <Globe className="h-5 w-5 text-accent" aria-hidden="true" />
          <ModalHeading>{t('federation_picker.title')}</ModalHeading>
        </ModalHeader>
        <ModalBody className="gap-4">
          <SearchField
            placeholder={t('federation_picker.search_placeholder')}
            value={query}
            onValueChange={setQuery}
            variant="bordered"
            aria-label={t('federation_picker.search_placeholder')}
          />

          {loading ? (
            <div className="flex justify-center py-12" role="status" aria-busy="true" aria-label={t('common:loading')}>
              <Spinner size="md" />
            </div>
          ) : filteredPeers.length === 0 ? (
            <div className="py-8 text-center text-sm text-theme-muted">
              {t('federation_picker.empty')}
            </div>
          ) : (
            <RadioGroup
              value={selectedSlug ?? ''}
              onValueChange={setSelectedSlug}
              aria-label={t('federation_picker.title')}
              className="flex flex-col gap-2"
            >
              {filteredPeers.map((peer) => (
                <Radio
                  key={peer.id}
                  value={peer.slug}
                  aria-label={peer.display_name}
                  className="min-h-11 rounded-xl border border-border px-4 py-3 transition-colors data-[selected=true]:border-accent data-[selected=true]:bg-accent/5"
                  classNames={{
                    wrapper: 'mt-1',
                    labelWrapper: 'min-w-0 flex-1',
                  }}
                >
                  <div className="min-w-0">
                    <p className="font-semibold text-theme-primary">
                      {peer.display_name}
                    </p>
                    <div className="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-theme-muted">
                      {peer.region && <span>{peer.region}</span>}
                      {peer.member_count_bucket && (
                        <span>
                          {peer.member_count_bucket}{' '}
                          {t('federation_picker.member_count_label')}
                        </span>
                      )}
                      <span className="font-mono">{peer.slug}</span>
                    </div>
                  </div>
                </Radio>
              ))}
            </RadioGroup>
          )}
        </ModalBody>
        <ModalFooter>
          <Button variant="flat" onPress={onClose}>
            {t('federation_picker.cancel_button')}
          </Button>
          <Button
            color="primary"
            isDisabled={!selectedSlug}
            onPress={handleConfirm}
          >
            {t('federation_picker.select_button')}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

export default FederationCommunityPicker;
