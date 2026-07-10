// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useRef } from 'react';
import Sparkles from 'lucide-react/icons/sparkles';
import Send from 'lucide-react/icons/send';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/Button';
import {
  Drawer,
  DrawerBody,
  DrawerContent,
  DrawerFooter,
  DrawerHeader,
  DrawerHeading,
} from '@/components/ui/Drawer';
import { Input } from '@/components/ui/Input';
import { Spinner } from '@/components/ui/Spinner';

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

  // Keep a live ref to onClose/isOpen so the unmount cleanup never closes over a stale value.
  const onCloseRef = useRef(onClose);
  const isOpenRef = useRef(isOpen);
  useEffect(() => { onCloseRef.current = onClose; isOpenRef.current = isOpen; }, [onClose, isOpen]);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

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
      <Drawer
        isOpen={isOpen}
        onClose={onClose}
        placement="right"
        size="md"
        closeLabel={t('ai_chat.close')}
        classNames={{
          base: '!h-[min(500px,100dvh)] !w-full self-end !max-w-md !rounded-tl-2xl border-l border-t border-divider !p-0',
          closeButton: '!top-[calc(var(--safe-area-top)+0.5rem)] right-2 size-11 text-muted',
        }}
      >
        <DrawerContent>
          <DrawerHeader className="shrink-0 border-b border-divider px-4 py-4 pr-14 pt-[calc(var(--safe-area-top)+1rem)]">
            <div className="flex items-center gap-2">
              <Sparkles size={18} className="text-accent" aria-hidden="true" />
              <DrawerHeading className="text-sm font-semibold text-foreground">
                {t('ai_chat.title')}
              </DrawerHeading>
            </div>
          </DrawerHeader>
          <DrawerBody className="!m-0 space-y-3 !p-4">
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
          </DrawerBody>
          <DrawerFooter className="shrink-0 border-t border-divider p-3 pb-[calc(var(--safe-area-bottom)+0.75rem)]">
            <Input
              autoFocus
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
              className="size-11 shrink-0"
            >
              <Send size={14} aria-hidden="true" />
            </Button>
          </DrawerFooter>
        </DrawerContent>
      </Drawer>
    </>
  );
}
