// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useRef, useEffect } from 'react';
import { Button, Input, Spinner } from '@heroui/react';
import { Sparkles, X, Send } from 'lucide-react';
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

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  return (
    <>
      <Button
        isIconOnly
        color="secondary"
        variant="shadow"
        size="lg"
        className="fixed bottom-6 right-6 z-40 rounded-full shadow-lg"
        onPress={onOpen}
        aria-label={t('ai_chat.open', { defaultValue: 'Ask AI about this job' })}
      >
        <Sparkles size={22} />
      </Button>
      {isOpen && (
        <div className="fixed bottom-0 right-0 z-50 w-full max-w-md h-[500px] bg-background border-l border-t border-divider rounded-tl-2xl shadow-2xl flex flex-col">
          <div className="flex items-center justify-between p-4 border-b border-divider">
            <div className="flex items-center gap-2">
              <Sparkles size={18} className="text-secondary" />
              <span className="font-semibold text-sm">{t('ai_chat.title', { defaultValue: 'Ask AI about this job' })}</span>
            </div>
            <Button
              isIconOnly
              size="sm"
              variant="light"
              onPress={onClose}
              aria-label={t('ai_chat.close', { defaultValue: 'Close AI chat' })}
            >
              <X size={16} />
            </Button>
          </div>
          <div className="flex-1 overflow-y-auto p-4 space-y-3">
            {messages.length === 0 && (
              <div className="text-center text-default-400 text-sm py-8">
                <Sparkles size={24} className="mx-auto mb-2 text-secondary" />
                <p>{t('ai_chat.hint', { defaultValue: 'Ask me anything about this job — qualifications, salary, how to apply, interview tips...' })}</p>
              </div>
            )}
            {messages.map((msg, i) => (
              <div key={i} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                <div className={`max-w-[85%] rounded-2xl px-4 py-2.5 text-sm ${msg.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-default-100 text-foreground'}`}>
                  {msg.content}
                </div>
              </div>
            ))}
            {isLoading && (
              <div className="flex justify-start">
                <div className="bg-default-100 rounded-2xl px-4 py-2.5">
                  <Spinner size="sm" />
                </div>
              </div>
            )}
            <div ref={endRef} />
          </div>
          <div className="p-3 border-t border-divider flex gap-2">
            <Input
              size="sm"
              placeholder={t('ai_chat.placeholder', { defaultValue: 'Type your question...' })}
              value={inputValue}
              onValueChange={onInputChange}
              onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); onSend(); } }}
              className="flex-1"
            />
            <Button
              isIconOnly
              size="sm"
              color="primary"
              onPress={onSend}
              isDisabled={!inputValue.trim() || isLoading}
              aria-label={t('ai_chat.send', { defaultValue: 'Send message' })}
            >
              <Send size={14} />
            </Button>
          </div>
        </div>
      )}
    </>
  );
}
