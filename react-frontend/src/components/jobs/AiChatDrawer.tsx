// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Spinner } from '@/components/ui/Spinner';
import { useRef, useEffect } from 'react';import Sparkles from 'lucide-react/icons/sparkles';
import X from 'lucide-react/icons/x';
import Send from 'lucide-react/icons/send';
import { useTranslation } from 'react-i18next';

interface AiChatMessage {
  role: 'user' | 'assistant';
  content: string;
}

interface AiChatDrawerProps {
  isOpen: boolean;
  messages: AiChatMessage[];
  inputValue: string;
  isLoading: boolean;
  onOpen: () => void;
  onClose: () => void;
  onInputChange: (v: string) => void;
  onSend: () => void;
}

export function AiChatDrawer({
  isOpen,
  messages,
  inputValue,
  isLoading,
  onOpen,
  onClose,
  onInputChange,
  onSend,
}: AiChatDrawerProps) {
  const { t } = useTranslation('jobs');
  const endRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  // Keep a live ref to onClose/isOpen so the unmount cleanup never closes over a stale value.
  const onCloseRef = useRef(onClose);
  const isOpenRef = useRef(isOpen);
  useEffect(() => { onCloseRef.current = onClose; isOpenRef.current = isOpen; }, [onClose, isOpen]);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  // Focus the input when the drawer opens, and close on Escape while open.
  useEffect(() => {
    if (!isOpen) return;
    inputRef.current?.focus();
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.stopPropagation();
        onClose();
      }
    };
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, onClose]);

  // Ensure the drawer is closed if the component unmounts (e.g. route change) while open.
  useEffect(() => {
    return () => {
      if (isOpenRef.current) onCloseRef.current();
    };
  }, []);

  return (
    <>
      <Button
        isIconOnly
        variant="secondary"
        size="lg"
        className="fixed bottom-6 right-6 z-40 rounded-full shadow-lg"
        onPress={onOpen}
        aria-label={t('ai_chat.open')}
      >
        <Sparkles size={22} aria-hidden="true" />
      </Button>
      {isOpen && (
        <div
          role="dialog"
          aria-modal="true"
          aria-label={t('ai_chat.title')}
          className="fixed bottom-0 right-0 z-50 w-full max-w-md h-[min(500px,100dvh)] max-h-[100dvh] bg-background border-l border-t border-divider rounded-tl-2xl shadow-2xl flex flex-col"
        >
          <div className="flex items-center justify-between p-4 border-b border-divider">
            <div className="flex items-center gap-2">
              <Sparkles size={18} className="text-accent" aria-hidden="true" />
              <span className="font-semibold text-sm">{t('ai_chat.title')}</span>
            </div>
            <Button
              isIconOnly
              size="sm"
              variant="tertiary"
              onPress={onClose}
              aria-label={t('ai_chat.close')}
            >
              <X size={16} aria-hidden="true" />
            </Button>
          </div>
          <div className="flex-1 overflow-y-auto p-4 space-y-3">
            {messages.length === 0 && (
              <div className="text-center text-muted text-sm py-8">
                <Sparkles size={24} className="mx-auto mb-2 text-accent" aria-hidden="true" />
                <p>{t('ai_chat.hint')}</p>
              </div>
            )}
            {messages.map((msg, i) => (
              <div key={i} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                <div className={`max-w-[85%] rounded-2xl px-4 py-2.5 text-sm ${msg.role === 'user' ? 'bg-accent text-accent-foreground' : 'bg-surface-secondary text-foreground'}`}>
                  {msg.content}
                </div>
              </div>
            ))}
            {isLoading && (
              <div className="flex justify-start">
                <div className="bg-surface-secondary rounded-2xl px-4 py-2.5" role="status" aria-busy="true" aria-label={t('common:loading')}>
                  <Spinner size="sm" />
                </div>
              </div>
            )}
            <div ref={endRef} />
          </div>
          <div className="p-3 border-t border-divider flex gap-2">
            <Input
              ref={inputRef}
              size="sm"
              placeholder={t('ai_chat.placeholder')}
              aria-label={t('ai_chat.placeholder')}
              value={inputValue}
              onValueChange={onInputChange}
              onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); onSend(); } }}
              className="flex-1"
            />
            <Button
              isIconOnly
              size="sm"
              onPress={onSend}
              isDisabled={!inputValue.trim() || isLoading}
              aria-label={t('ai_chat.send')}
            >
              <Send size={14} aria-hidden="true" />
            </Button>
          </div>
        </div>
      )}
    </>
  );
}
