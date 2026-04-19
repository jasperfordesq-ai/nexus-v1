// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ShareViaDMModal — modal for sharing a feed post via direct message.
 * Shows a user search/picker and sends the post link as a message.
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Input,
  Avatar,
  Spinner,
} from '@heroui/react';
import { Search, Send, Check } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';

interface ShareViaDMModalProps {
  isOpen: boolean;
  onClose: () => void;
  postUrl: string;
  postContent: string;
}

interface UserResult {
  id: number;
  name: string;
  avatar_url?: string | null;
}

export function ShareViaDMModal({ isOpen, onClose, postUrl, postContent }: ShareViaDMModalProps) {
  const { t } = useTranslation('feed');
  const toast = useToast();
  const [query, setQuery] = useState('');
  const [users, setUsers] = useState<UserResult[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const [selectedUser, setSelectedUser] = useState<UserResult | null>(null);
  const [isSending, setIsSending] = useState(false);
  const [sentTo, setSentTo] = useState<Set<number>>(new Set());

  // M4: Mounted guard — prevents setState after unmount in debounced search
  const isMountedRef = useRef(true);
  useEffect(() => { return () => { isMountedRef.current = false; }; }, []);

  const searchUsers = useCallback(async (q: string) => {
    if (q.length < 2) {
      setUsers([]);
      return;
    }

    try {
      setIsSearching(true);
      // Backend /v2/users (UsersController::index) reads ?q=... and ?limit=...
      // The previous ?search=...&per_page=... params were ignored, which meant
      // the query fell through to the full member-directory rank path and timed out.
      const response = await api.get<{ data: UserResult[] }>(`/v2/users?q=${encodeURIComponent(q)}&limit=10`);
      if (response.success && response.data) {
        const items = Array.isArray(response.data) ? response.data : (response.data as { data: UserResult[] }).data ?? [];
        if (isMountedRef.current) setUsers(items);
      }
    } catch (err) {
      logError('Failed to search users for DM share', err);
    } finally {
      if (isMountedRef.current) setIsSearching(false);
    }
  }, []);

  useEffect(() => {
    const timer = setTimeout(() => {
      searchUsers(query);
    }, 300);
    return () => clearTimeout(timer);
    // searchUsers is stable (useCallback with []), so it's intentionally excluded
    // to prevent the debounce timer resetting on unrelated parent re-renders.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [query]);

  // Reset state when modal opens
  useEffect(() => {
    if (isOpen) {
      setQuery('');
      setUsers([]);
      setSelectedUser(null);
      setSentTo(new Set());
    }
  }, [isOpen]);

  const handleSend = async (user: UserResult) => {
    try {
      setIsSending(true);
      setSelectedUser(user);

      const snippet = postContent.length > 100 ? postContent.slice(0, 100) + '...' : postContent;
      const messageBody = `${snippet}\n\n${postUrl}`;

      const response = await api.post('/v2/messages', {
        recipient_id: user.id,
        body: messageBody,
      });

      if (response.success) {
        setSentTo((prev) => new Set(prev).add(user.id));
        toast.success(t('share.dm_sent', 'Post shared with {{name}}', { name: user.name }));
      } else {
        toast.error(response.error || t('share.dm_failed', 'Failed to send message'));
      }
    } catch (err) {
      logError('Failed to share via DM', err);
      toast.error(t('share.dm_failed', 'Failed to send message'));
    } finally {
      setIsSending(false);
      setSelectedUser(null);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      size="md"
      classNames={{
        // `--color-surface` doesn't exist in tokens.css — that's why this modal
        // was rendering transparent. Use the opaque dropdown surface + explicit
        // backdrop, matching the pattern used by other feed modals.
        base: 'bg-[var(--surface-dropdown)] border border-[var(--border-default)]',
        backdrop: 'bg-black/60 backdrop-blur-sm',
        header: 'border-b border-[var(--border-default)]',
      }}
    >
      <ModalContent>
        <ModalHeader className="text-[var(--text-primary)]">
          {t('share.dm_title', 'Send via Message')}
        </ModalHeader>
        <ModalBody className="gap-3">
          <Input
            placeholder={t('share.dm_search_placeholder', 'Search for a member...')}
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            startContent={<Search className="w-4 h-4 text-[var(--text-subtle)]" />}
            size="sm"
            classNames={{
              input: 'bg-transparent text-[var(--text-primary)]',
              inputWrapper: 'bg-[var(--surface-elevated)] border-[var(--border-default)]',
            }}
            autoFocus
          />

          <div className="max-h-64 overflow-y-auto space-y-1">
            {isSearching && (
              <div className="flex justify-center py-4">
                <Spinner size="sm" />
              </div>
            )}

            {!isSearching && query.length >= 2 && users.length === 0 && (
              <p className="text-sm text-[var(--text-subtle)] text-center py-4">
                {t('share.dm_no_results', 'No members found')}
              </p>
            )}

            {!isSearching && query.length < 2 && (
              <p className="text-sm text-[var(--text-subtle)] text-center py-4">
                {t('share.dm_hint', 'Type at least 2 characters to search')}
              </p>
            )}

            {users.map((user) => {
              const alreadySent = sentTo.has(user.id);
              const isCurrent = selectedUser?.id === user.id && isSending;

              return (
                <div
                  key={user.id}
                  className="flex items-center gap-3 p-2 rounded-lg hover:bg-[var(--surface-hover)] transition-colors"
                >
                  <Avatar
                    name={user.name}
                    src={resolveAvatarUrl(user.avatar_url)}
                    size="sm"
                    className="flex-shrink-0"
                  />
                  <span className="flex-1 text-sm font-medium text-[var(--text-primary)] truncate">
                    {user.name}
                  </span>
                  <Button
                    size="sm"
                    variant={alreadySent ? 'flat' : 'solid'}
                    color={alreadySent ? 'success' : 'primary'}
                    isLoading={isCurrent}
                    isDisabled={alreadySent}
                    onPress={() => handleSend(user)}
                    startContent={
                      alreadySent ? <Check className="w-3.5 h-3.5" /> : <Send className="w-3.5 h-3.5" />
                    }
                    className="min-w-[80px]"
                  >
                    {alreadySent ? t('share.dm_sent_label', 'Sent') : t('share.dm_send', 'Send')}
                  </Button>
                </div>
              );
            })}
          </div>
        </ModalBody>
        <ModalFooter>
          <Button
            variant="light"
            onPress={onClose}
            className="text-[var(--text-muted)]"
          >
            {t('share.dm_done', 'Done')}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

export default ShareViaDMModal;
