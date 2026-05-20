// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * DonateModal - Modal for donating credits to community fund or another member
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, motion } from 'framer-motion';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Input,
  Textarea,
  RadioGroup,
  Radio,
  Avatar,
  Spinner,
} from '@heroui/react';
import Heart from 'lucide-react/icons/heart';
import Users from 'lucide-react/icons/users';
import User from 'lucide-react/icons/user';
import Search from 'lucide-react/icons/search';
import X from 'lucide-react/icons/x';
import { api } from '@/lib/api';
import { resolveAvatarUrl } from '@/lib/helpers';
import { logError } from '@/lib/logger';
import { useToast } from '@/contexts';
import type { WalletUserSearchResult } from '@/types/api';

interface DonateModalProps {
  isOpen: boolean;
  onClose: () => void;
  currentBalance: number;
  onDonationComplete?: () => void;
}

export function DonateModal({ isOpen, onClose, currentBalance, onDonationComplete }: DonateModalProps) {
  const { t } = useTranslation('wallet');
  const toast = useToast();
  const [recipientType, setRecipientType] = useState<'community_fund' | 'user'>('community_fund');
  const [selectedRecipient, setSelectedRecipient] = useState<WalletUserSearchResult | null>(null);
  const [amount, setAmount] = useState('');
  const [message, setMessage] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Member search state
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<WalletUserSearchResult[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const [showResults, setShowResults] = useState(false);
  const searchInputRef = useRef<HTMLInputElement>(null);
  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const resultsRef = useRef<HTMLDivElement>(null);

  // Reset form when modal opens
  useEffect(() => {
    if (isOpen) {
      setRecipientType('community_fund');
      setSelectedRecipient(null);
      setSearchQuery('');
      setSearchResults([]);
      setShowResults(false);
      setAmount('');
      setMessage('');
    }
    return () => {
      if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
    };
  }, [isOpen]);

  const searchUsers = useCallback(async (query: string) => {
    if (query.length < 2) {
      setSearchResults([]);
      setShowResults(false);
      return;
    }
    setIsSearching(true);
    try {
      const response = await api.get<{ users: WalletUserSearchResult[] }>(
        `/v2/wallet/user-search?q=${encodeURIComponent(query)}&limit=10`
      );
      if (response.success && response.data?.users) {
        setSearchResults(response.data.users);
        setShowResults(true);
      }
    } catch (err) {
      logError('Member search failed', err);
    } finally {
      setIsSearching(false);
    }
  }, []);

  const handleSearchChange = (value: string) => {
    setSearchQuery(value);
    if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
    searchTimeoutRef.current = setTimeout(() => searchUsers(value), 300);
  };

  const handleSelectRecipient = (user: WalletUserSearchResult) => {
    setSelectedRecipient(user);
    setSearchQuery('');
    setShowResults(false);
    setSearchResults([]);
  };

  const handleClearRecipient = () => {
    setSelectedRecipient(null);
    setTimeout(() => searchInputRef.current?.focus(), 50);
  };

  async function handleDonate() {
    if (isSubmitting) return;

    const parsedAmount = parseFloat(amount);
    if (isNaN(parsedAmount) || parsedAmount <= 0) {
      toast.error(t('donate_invalid_amount'), t('donate_invalid_amount_desc'));
      return;
    }

    if (parsedAmount > currentBalance) {
      toast.error(t('donate_insufficient'), t('donate_insufficient_desc'));
      return;
    }

    if (recipientType === 'user' && !selectedRecipient) {
      toast.error(t('donate_recipient_required'), t('donate_recipient_required_desc'));
      return;
    }

    try {
      setIsSubmitting(true);
      const response = await api.post('/v2/wallet/donate', {
        recipient_type: recipientType,
        recipient_id: recipientType === 'user' ? selectedRecipient!.id : undefined,
        amount: parsedAmount,
        message,
      });

      if (response.success) {
        toast.success(t('donate_success'), t('donate_success_desc'));
        setAmount('');
        setMessage('');
        setSelectedRecipient(null);
        onClose();
        onDonationComplete?.();
      } else {
        toast.error(t('donate_failed'), response.error || t('donate_error'));
      }
    } catch (err) {
      logError('Donation failed', err);
      toast.error(t('donate_failed'), t('donate_error'));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      classNames={{
        base: 'bg-content1 border border-theme-default',
        header: 'border-b border-theme-default',
        body: 'py-6',
        footer: 'border-t border-theme-default',
      }}
    >
      <ModalContent>
        <ModalHeader className="text-theme-primary flex items-center gap-2">
          <Heart className="w-5 h-5 text-rose-400" />
          {t('donate_credits')}
        </ModalHeader>
        <ModalBody>
          <RadioGroup
            label={t('donate_to')}
            value={recipientType}
            onValueChange={(v) => {
              setRecipientType(v as 'community_fund' | 'user');
              setSelectedRecipient(null);
              setSearchQuery('');
              setSearchResults([]);
              setShowResults(false);
            }}
            classNames={{ label: 'text-theme-muted' }}
          >
            <Radio value="community_fund" description={t('donate_community_desc')}>
              <span className="flex items-center gap-2">
                <Users className="w-4 h-4" />
                {t('donate_community_fund')}
              </span>
            </Radio>
            <Radio value="user" description={t('donate_member_desc')}>
              <span className="flex items-center gap-2">
                <User className="w-4 h-4" />
                {t('donate_member')}
              </span>
            </Radio>
          </RadioGroup>

          {recipientType === 'user' && (
            <div className="space-y-2">
              <label className="text-sm font-medium text-theme-muted">
                {t('donate_search_member')}
                <span className="text-[var(--color-error)] ml-1">*</span>
              </label>

              {selectedRecipient ? (
                <div className="flex items-center gap-3 bg-theme-elevated rounded-lg p-3">
                  <Avatar
                    src={resolveAvatarUrl(selectedRecipient.avatar) || undefined}
                    name={`${selectedRecipient.first_name} ${selectedRecipient.last_name}`}
                    size="sm"
                    className="flex-shrink-0"
                  />
                  <div className="flex-1 min-w-0">
                    <p className="text-theme-primary font-medium truncate">
                      {selectedRecipient.first_name} {selectedRecipient.last_name}
                    </p>
                    {selectedRecipient.username && (
                      <p className="text-theme-subtle text-sm truncate">
                        @{selectedRecipient.username}
                      </p>
                    )}
                  </div>
                  <Button
                    type="button"
                    variant="light"
                    isIconOnly
                    onPress={handleClearRecipient}
                    className="text-theme-subtle hover:text-theme-primary p-1 min-w-0 h-auto"
                    aria-label={t('remove_recipient')}
                  >
                    <X className="w-4 h-4" />
                  </Button>
                </div>
              ) : (
                <div className="relative">
                  <Input
                    ref={searchInputRef}
                    type="text"
                    placeholder={t('search_placeholder')}
                    aria-label={t('donate_search_member')}
                    value={searchQuery}
                    onValueChange={handleSearchChange}
                    onFocus={() => searchResults.length > 0 && setShowResults(true)}
                    onBlur={(e) => {
                      const rel = e.relatedTarget as HTMLElement | null;
                      if (!resultsRef.current?.contains(rel)) setShowResults(false);
                    }}
                    startContent={
                      isSearching
                        ? <Spinner size="sm" color="current" />
                        : <Search className="w-4 h-4 text-theme-subtle" />
                    }
                    classNames={{
                      input: 'bg-transparent text-theme-primary',
                      inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                    }}
                  />

                  <AnimatePresence>
                    {showResults && searchResults.length > 0 && (
                      <motion.div
                        ref={resultsRef}
                        initial={{ opacity: 0, y: -8 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -8 }}
                        className="absolute top-full left-0 right-0 mt-2 bg-content1 border border-theme-default rounded-lg shadow-xl overflow-hidden z-50 max-h-60 overflow-y-auto"
                        role="listbox"
                        aria-label={t('search_results')}
                      >
                        {searchResults.map((user) => (
                          <Button
                            key={user.id}
                            type="button"
                            role="option"
                            variant="light"
                            onPress={() => handleSelectRecipient(user)}
                            className="w-full flex items-center gap-3 p-3 hover:bg-theme-hover transition-colors text-left h-auto min-w-0 justify-start rounded-none"
                          >
                            <Avatar
                              src={resolveAvatarUrl(user.avatar) || undefined}
                              name={`${user.first_name} ${user.last_name}`}
                              size="sm"
                            />
                            <div className="flex-1 min-w-0">
                              <p className="text-theme-primary font-medium truncate">
                                {user.first_name} {user.last_name}
                              </p>
                              {user.username && (
                                <p className="text-theme-subtle text-sm truncate">
                                  @{user.username}
                                </p>
                              )}
                            </div>
                          </Button>
                        ))}
                      </motion.div>
                    )}
                  </AnimatePresence>

                  {showResults && searchQuery.length >= 2 && searchResults.length === 0 && !isSearching && (
                    <div className="absolute top-full left-0 right-0 mt-2 bg-content1 border border-theme-default rounded-lg p-4 text-center z-50">
                      <User className="w-8 h-8 text-theme-subtle mx-auto mb-2" />
                      <p className="text-theme-muted text-sm">{t('no_members_found')}</p>
                    </div>
                  )}
                </div>
              )}
            </div>
          )}

          <Input
            label={t('donate_amount')}
            placeholder="1"
            value={amount}
            onChange={(e) => setAmount(e.target.value)}
            type="number"
            min="0.25"
            step="0.25"
            endContent={<span className="text-theme-muted text-sm">{t('hours')}</span>}
            description={t('donate_balance_info', { balance: currentBalance })}
            classNames={{
              input: 'bg-transparent text-theme-primary',
              inputWrapper: 'bg-theme-elevated border-theme-default',
            }}
          />

          <Textarea
            label={t('donate_message')}
            placeholder={t('donate_message_placeholder')}
            value={message}
            onChange={(e) => setMessage(e.target.value)}
            maxLength={500}
            classNames={{
              input: 'bg-transparent text-theme-primary',
              inputWrapper: 'bg-theme-elevated border-theme-default',
            }}
          />
        </ModalBody>
        <ModalFooter>
          <Button
            variant="flat"
            onPress={onClose}
            className="bg-theme-elevated text-theme-primary"
            isDisabled={isSubmitting}
          >
            {t('cancel')}
          </Button>
          <Button
            color="danger"
            onPress={handleDonate}
            isLoading={isSubmitting}
            isDisabled={isSubmitting}
            startContent={!isSubmitting ? <Heart className="w-4 h-4" /> : undefined}
          >
            {t('donate_confirm')}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}
