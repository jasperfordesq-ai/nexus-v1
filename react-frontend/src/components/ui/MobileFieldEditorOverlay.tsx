// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MobileFieldEditorOverlay — Full-screen single-field editor for phones.
 *
 * The Instagram edit-profile pattern: tapping a long-form field (bio,
 * description) opens a full-screen overlay with the field autofocused,
 * Cancel on the left, and Save on the right. Edits are kept in a local
 * draft so Cancel/Escape discards them; Save commits via onSave and closes.
 *
 * Rendered via createPortal at z-[400] above Navbar/MobileTabBar (z-300),
 * following the MobileComposeOverlay pattern. Desktop never renders this —
 * callers gate it behind their phone media query.
 */

import { useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { FocusScope } from '@react-aria/focus';
import { motion, AnimatePresence } from '@/lib/motion';
import X from 'lucide-react/icons/x';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Textarea } from '@/components/ui/Textarea';

export interface MobileFieldEditorOverlayProps {
  isOpen: boolean;
  onClose: () => void;
  /** Field name shown in the header (e.g. "Bio"). */
  title: string;
  value: string;
  onSave: (value: string) => void;
  placeholder?: string;
  maxLength?: number;
}

export function MobileFieldEditorOverlay({
  isOpen,
  onClose,
  title,
  value,
  onSave,
  placeholder,
  maxLength,
}: MobileFieldEditorOverlayProps) {
  const { t } = useTranslation('common');
  const [draft, setDraft] = useState(value);

  // Re-seed the draft from the committed value each time the editor opens.
  useEffect(() => {
    if (isOpen) setDraft(value);
  }, [isOpen, value]);

  // Close (discard) on Escape key.
  useEffect(() => {
    if (!isOpen) return;
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        onClose();
      }
    };
    document.addEventListener('keydown', handleKeyDown, true);
    return () => document.removeEventListener('keydown', handleKeyDown, true);
  }, [isOpen, onClose]);

  const handleSave = useCallback(() => {
    onSave(draft);
    onClose();
  }, [draft, onClose, onSave]);

  return createPortal(
    <AnimatePresence>
      {isOpen && (
        <FocusScope contain restoreFocus>
          <motion.div
            role="dialog"
            aria-modal="true"
            aria-label={title}
            className="fixed inset-0 z-[400] flex flex-col bg-[var(--surface-base)] pt-[env(safe-area-inset-top,0px)]"
            initial={{ y: '100%' }}
            animate={{ y: 0 }}
            exit={{ y: '100%' }}
            transition={{ type: 'spring', damping: 30, stiffness: 300 }}
          >
            {/* ── Header: cancel · title · save ── */}
            <div className="flex items-center gap-2 h-14 px-3 border-b border-[var(--border-default)] shrink-0">
              <Button
                isIconOnly
                variant="tertiary"
                size="sm"
                onPress={onClose}
                aria-label={t('cancel')}
                className="size-11 min-h-11"
              >
                <X className="w-5 h-5" aria-hidden="true" />
              </Button>
              <span className="flex-1 truncate font-semibold text-[var(--text-primary)]">
                {title}
              </span>
              <Button
                size="sm"
                variant="primary"
                onPress={handleSave}
                className="min-w-[72px] min-h-11"
              >
                {t('save')}
              </Button>
            </div>

            {/* ── Field ── */}
            <div className="flex-1 overflow-y-auto px-4 pt-4 pb-[calc(env(safe-area-inset-bottom,0px)+16px)]">
              <Textarea
                autoFocus
                aria-label={title}
                placeholder={placeholder}
                value={draft}
                maxLength={maxLength}
                onChange={(e) => setDraft(e.target.value)}
                minRows={6}
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                }}
              />
              {typeof maxLength === 'number' && (
                <p className="mt-1 text-right text-xs text-[var(--text-subtle)]">
                  {draft.length}/{maxLength}
                </p>
              )}
            </div>
          </motion.div>
        </FocusScope>
      )}
    </AnimatePresence>,
    document.body,
  );
}

export default MobileFieldEditorOverlay;
