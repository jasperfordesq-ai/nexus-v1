// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Transfer Credits Modal
 * Allows users to send time credits to other members
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Button, Input, Textarea, Avatar, Spinner } from '@heroui/react';
import { X, Send, Search, User, AlertCircle } from 'lucide-react';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { WalletUserSearchResult, Transaction } from '@/types/api';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface TransferModalProps {
  isOpen: boolean;
  onClose: () => void;
  currentBalance: number;
  onTransferComplete: (transaction: Transaction) => void;
  initialRecipientId?: number | null;
}

interface TransferFormData {
  recipient: WalletUserSearchResult | null;
  amount: string;
  description: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function TransferModal({
  isOpen,
  onClose,
  currentBalance,
  onTransferComplete,
  initialRecipientId,
}: TransferModalProps) {
  // Form state
  const [formData, setFormData] = useState<TransferFormData>({
    recipient: null,
    amount: '',
    description: '',
  });

  // Search state
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<WalletUserSearchResult[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const [showResults, setShowResults] = useState(false);

  // Submission state
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Refs
  const searchInputRef = useRef<HTMLInputElement>(null);
  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const resultsRef = useRef<HTMLDivElement>(null);

  // Reset form when modal opens/closes
  useEffect(() => {
    if (isOpen) {
      setFormData({ recipient: null, amount: '', description: '' });
      setSearchQuery('');
      setSearchResults([]);
      setError(null);

      // Auto-fill recipient if initialRecipientId is provided
      if (initialRecipientId) {
        api.get<{ id: number; first_name: string; last_name: string; avatar_url?: string; username?: string }>(`/v2/users/${initialRecipientId}`)
          .then((res) => {
            if (res.success && res.data) {
              const u = res.data;
              setFormData((prev) => ({
                ...prev,
                recipient: {
                  id: u.id,
                  first_name: u.first_name,
                  last_name: u.last_name,
                  avatar: u.avatar_url || (u as Record<string, unknown>).avatar as string || null,
                  username: u.username || null,
                } as WalletUserSearchResult,
              }));
            }
          })
          .catch(() => {
            // Silently fail — user can still search manually
          });
      } else {
        // Focus search input after animation (only when no pre-fill)
        setTimeout(() => searchInputRef.current?.focus(), 100);
      }
    }

    // Cleanup timeout on unmount
    return () => {
      if (searchTimeoutRef.current) {
        clearTimeout(searchTimeoutRef.current);
      }
    };
  }, [isOpen, initialRecipientId]);

  // Debounced user search
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
      logError('User search failed', err);
    } finally {
      setIsSearching(false);
    }
  }, []);

  // Handle search input change with debounce
  const handleSearchChange = (value: string) => {
    setSearchQuery(value);
    setError(null);

    // Clear previous timeout
    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current);
    }

    // Debounce search
    searchTimeoutRef.current = setTimeout(() => {
      searchUsers(value);
    }, 300);
  };

  // Select a recipient
  const handleSelectRecipient = (user: WalletUserSearchResult) => {
    setFormData((prev) => ({ ...prev, recipient: user }));
    setSearchQuery('');
    setShowResults(false);
    setSearchResults([]);
  };

  // Clear selected recipient
  const handleClearRecipient = () => {
    setFormData((prev) => ({ ...prev, recipient: null }));
    setTimeout(() => searchInputRef.current?.focus(), 50);
  };

  // Validate form
  const validateForm = (): string | null => {
    if (!formData.recipient) {
      return 'Please select a recipient';
    }

    const amount = parseFloat(formData.amount);
    if (isNaN(amount) || amount <= 0) {
      return 'Please enter a valid amount';
    }

    if (amount > currentBalance) {
      return 'Insufficient balance';
    }

    // Check for reasonable max (prevent typos)
    if (amount > 1000) {
      return 'Maximum transfer is 1000 hours';
    }

    return null;
  };

  // Submit transfer
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    const validationError = validateForm();
    if (validationError) {
      setError(validationError);
      return;
    }

    setIsSubmitting(true);
    setError(null);

    try {
      const response = await api.post<Transaction>('/v2/wallet/transfer', {
        recipient: formData.recipient!.id,
        amount: parseFloat(formData.amount),
        description: formData.description.trim() || undefined,
      });

      if (response.success && response.data) {
        onTransferComplete(response.data);
        onClose();
      } else {
        setError(response.error || 'Transfer failed. Please try again.');
      }
    } catch (err) {
      logError('Transfer failed', err);
      setError('An unexpected error occurred. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  // Parse amount for display
  const parsedAmount = parseFloat(formData.amount) || 0;
  const isOverBalance = parsedAmount > currentBalance;

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          {/* Backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 bg-black/50 dark:bg-black/60 backdrop-blur-sm z-[9998]"
            onClick={onClose}
            aria-hidden="true"
          />

          {/* Modal */}
          <motion.div
            initial={{ opacity: 0, scale: 0.95, y: 20 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.95, y: 20 }}
            className="fixed inset-0 flex items-center justify-center z-[9999] p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="transfer-modal-title"
          >
            <div className="bg-theme-card backdrop-blur-xl border border-theme-default rounded-2xl w-full max-w-sm sm:max-w-md shadow-2xl">
              {/* Header */}
              <div className="flex items-center justify-between p-6 border-b border-theme-default">
                <h2
                  id="transfer-modal-title"
                  className="text-xl font-semibold text-theme-primary flex items-center gap-2"
                >
                  <Send className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                  Send Credits
                </h2>
                <button
                  onClick={onClose}
                  className="text-theme-subtle hover:text-theme-primary transition-colors p-1"
                  aria-label="Close modal"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>

              {/* Form */}
              <form onSubmit={handleSubmit} className="p-4 sm:p-6 space-y-5">
                {/* Balance Display */}
                <div className="bg-theme-elevated rounded-lg p-3 flex justify-between items-center">
                  <span className="text-theme-muted text-sm">Available Balance</span>
                  <span className="text-theme-primary font-semibold">{currentBalance} hours</span>
                </div>

                {/* Recipient Search */}
                <div className="space-y-2">
                  <label className="text-sm font-medium text-theme-muted">
                    Recipient <span className="text-red-500 dark:text-red-400">*</span>
                  </label>

                  {formData.recipient ? (
                    // Selected recipient display
                    <div className="flex items-center gap-3 bg-theme-elevated rounded-lg p-3">
                      <Avatar
                        src={formData.recipient.avatar || undefined}
                        name={`${formData.recipient.first_name} ${formData.recipient.last_name}`}
                        size="sm"
                        className="flex-shrink-0"
                      />
                      <div className="flex-1 min-w-0">
                        <p className="text-theme-primary font-medium truncate">
                          {formData.recipient.first_name} {formData.recipient.last_name}
                        </p>
                        {formData.recipient.username && (
                          <p className="text-theme-subtle text-sm truncate">
                            @{formData.recipient.username}
                          </p>
                        )}
                      </div>
                      <button
                        type="button"
                        onClick={handleClearRecipient}
                        className="text-theme-subtle hover:text-theme-primary transition-colors p-1"
                        aria-label="Remove recipient"
                      >
                        <X className="w-4 h-4" />
                      </button>
                    </div>
                  ) : (
                    // Search input
                    <div className="relative">
                      <Input
                        ref={searchInputRef}
                        type="text"
                        placeholder="Search by name or username..."
                        value={searchQuery}
                        onChange={(e) => handleSearchChange(e.target.value)}
                        onFocus={() => searchResults.length > 0 && setShowResults(true)}
                        onBlur={(e) => {
                          // Only hide results if focus moved outside the results container
                          const relatedTarget = e.relatedTarget as HTMLElement | null;
                          if (!resultsRef.current?.contains(relatedTarget)) {
                            setShowResults(false);
                          }
                        }}
                        startContent={
                          isSearching ? (
                            <Spinner size="sm" color="current" />
                          ) : (
                            <Search className="w-4 h-4 text-theme-subtle" />
                          )
                        }
                        classNames={{
                          input: 'bg-transparent text-theme-primary',
                          inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                        }}
                      />

                      {/* Search Results Dropdown */}
                      <AnimatePresence>
                        {showResults && searchResults.length > 0 && (
                          <motion.div
                            ref={resultsRef}
                            initial={{ opacity: 0, y: -10 }}
                            animate={{ opacity: 1, y: 0 }}
                            exit={{ opacity: 0, y: -10 }}
                            className="absolute top-full left-0 right-0 mt-2 bg-content1 border border-theme-default rounded-lg shadow-xl overflow-hidden z-50 max-h-60 overflow-y-auto"
                            role="listbox"
                            aria-label="Search results"
                          >
                            {searchResults.map((user) => (
                              <button
                                key={user.id}
                                type="button"
                                role="option"
                                onClick={() => handleSelectRecipient(user)}
                                className="w-full flex items-center gap-3 p-3 hover:bg-theme-hover transition-colors text-left"
                              >
                                <Avatar
                                  src={user.avatar || undefined}
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
                              </button>
                            ))}
                          </motion.div>
                        )}
                      </AnimatePresence>

                      {/* No results message */}
                      {showResults && searchQuery.length >= 2 && searchResults.length === 0 && !isSearching && (
                        <div className="absolute top-full left-0 right-0 mt-2 bg-content1 border border-theme-default rounded-lg p-4 text-center z-50">
                          <User className="w-8 h-8 text-theme-subtle mx-auto mb-2" aria-hidden="true" />
                          <p className="text-theme-muted text-sm">No members found</p>
                        </div>
                      )}
                    </div>
                  )}
                </div>

                {/* Amount Input */}
                <div className="space-y-2">
                  <label className="text-sm font-medium text-theme-muted">
                    Amount (hours) <span className="text-red-500 dark:text-red-400">*</span>
                  </label>
                  <Input
                    type="number"
                    placeholder="0"
                    min="0.25"
                    step="0.25"
                    value={formData.amount}
                    onChange={(e) => {
                      setFormData((prev) => ({ ...prev, amount: e.target.value }));
                      setError(null);
                    }}
                    endContent={<span className="text-theme-subtle text-sm">hours</span>}
                    classNames={{
                      input: `bg-transparent text-theme-primary text-lg font-semibold ${isOverBalance ? 'text-red-500 dark:text-red-400' : ''}`,
                      inputWrapper: `bg-theme-elevated border-theme-default hover:bg-theme-hover ${isOverBalance ? 'border-red-500/50' : ''}`,
                    }}
                  />
                  {isOverBalance && (
                    <p className="text-red-500 dark:text-red-400 text-sm flex items-center gap-1">
                      <AlertCircle className="w-3 h-3" />
                      Exceeds available balance
                    </p>
                  )}
                </div>

                {/* Description Input */}
                <div className="space-y-2">
                  <label className="text-sm font-medium text-theme-muted">
                    Description <span className="text-theme-subtle">(optional)</span>
                  </label>
                  <Textarea
                    placeholder="What is this transfer for?"
                    value={formData.description}
                    onChange={(e) =>
                      setFormData((prev) => ({ ...prev, description: e.target.value }))
                    }
                    maxLength={255}
                    minRows={2}
                    maxRows={4}
                    classNames={{
                      input: 'bg-transparent text-theme-primary',
                      inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                    }}
                  />
                  <p className="text-theme-subtle text-xs text-right">
                    {formData.description.length}/255
                  </p>
                </div>

                {/* Error Message */}
                {error && (
                  <div className="bg-red-500/20 border border-red-500/40 rounded-lg p-3 flex items-center gap-2">
                    <AlertCircle className="w-4 h-4 text-red-500 dark:text-red-400 flex-shrink-0" />
                    <p className="text-red-500 dark:text-red-400 text-sm">{error}</p>
                  </div>
                )}

                {/* Transfer Summary */}
                {formData.recipient && parsedAmount > 0 && !isOverBalance && (
                  <div className="bg-indigo-500/10 border border-indigo-500/30 rounded-lg p-4">
                    <p className="text-theme-muted text-sm">
                      You are about to send{' '}
                      <span className="font-semibold text-theme-primary">{parsedAmount} hours</span> to{' '}
                      <span className="font-semibold text-theme-primary">
                        {formData.recipient.first_name} {formData.recipient.last_name}
                      </span>
                    </p>
                    <p className="text-theme-subtle text-xs mt-1">
                      Your new balance will be {(currentBalance - parsedAmount).toFixed(2)} hours
                    </p>
                  </div>
                )}

                {/* Actions */}
                <div className="flex gap-3 pt-2">
                  <Button
                    type="button"
                    variant="flat"
                    className="flex-1 bg-theme-elevated text-theme-primary"
                    onClick={onClose}
                    isDisabled={isSubmitting}
                  >
                    Cancel
                  </Button>
                  <Button
                    type="submit"
                    className="flex-1 bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                    startContent={!isSubmitting && <Send className="w-4 h-4" />}
                    isLoading={isSubmitting}
                    isDisabled={!formData.recipient || !parsedAmount || isOverBalance}
                  >
                    Send Credits
                  </Button>
                </div>
              </form>
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
}

export default TransferModal;
