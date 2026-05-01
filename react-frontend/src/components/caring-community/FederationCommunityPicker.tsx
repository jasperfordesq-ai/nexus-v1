// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  Input,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  RadioGroup,
  Spinner,
} from '@heroui/react';
import Search from 'lucide-react/icons/search';
import Globe from 'lucide-react/icons/globe';
import { useTranslation } from 'react-i18next';
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

  useEffect(() => {
    if (isOpen) {
      setSelectedSlug(null);
      setQuery('');
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
          <Globe className="h-5 w-5 text-primary" aria-hidden="true" />
          <span>{t('federation_picker.title')}</span>
        </ModalHeader>
        <ModalBody className="gap-4">
          <Input
            placeholder={t('federation_picker.search_placeholder')}
            value={query}
            onValueChange={setQuery}
            startContent={<Search className="h-4 w-4 text-default-400" aria-hidden="true" />}
            variant="bordered"
            aria-label={t('federation_picker.search_placeholder')}
          />

          {loading ? (
            <div className="flex justify-center py-12">
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
            >
              <div className="flex flex-col gap-2">
                {filteredPeers.map((peer) => (
                  <Card
                    key={peer.id}
                    isPressable
                    onPress={() => setSelectedSlug(peer.slug)}
                    className={`border ${
                      selectedSlug === peer.slug
                        ? 'border-primary bg-primary/5'
                        : 'border-default-200'
                    }`}
                  >
                    <CardBody className="flex flex-row items-start gap-3 px-4 py-3">
                      <input
                        type="radio"
                        className="mt-1 h-4 w-4 accent-primary"
                        checked={selectedSlug === peer.slug}
                        onChange={() => setSelectedSlug(peer.slug)}
                        aria-label={peer.display_name}
                      />
                      <div className="flex-1">
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
                    </CardBody>
                  </Card>
                ))}
              </div>
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
