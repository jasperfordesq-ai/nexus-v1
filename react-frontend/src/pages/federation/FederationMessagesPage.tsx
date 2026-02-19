// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation Messages Page - Cross-community messaging
 *
 * Two-panel layout (desktop) with conversation thread list + active thread view.
 * Supports compose via URL params (?compose=true&to_user=X&to_tenant=Y).
 *
 * API Endpoints:
 *   GET  /api/v2/federation/messages           - List message threads
 *   POST /api/v2/federation/messages           - Send a new message
 *   POST /api/v2/federation/messages/:id/mark-read - Mark thread as read
 */

import { useState, useEffect, useCallback, useRef, useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Button,
  Input,
  Textarea,
  Avatar,
  Chip,
  Spinner,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
} from '@heroui/react';
import {
  MessageSquare,
  Send,
  Mail,
  MailOpen,
  Globe,
  ChevronRight,
  Plus,
  Search,
  ArrowLeft,
} from 'lucide-react';

import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { usePageTitle } from '@/hooks';
import { useAuth, useTenant, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { resolveAvatarUrl, formatRelativeTime } from '@/lib/helpers';
import { logError } from '@/lib/logger';
import type { FederatedMessage } from '@/types/api';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

/** A conversation thread grouped by the other participant */
interface FederatedThread {
  /** The other participant */
  partner: {
    id: number;
    name: string;
    avatar?: string | null;
    tenant_id: number;
    tenant_name: string;
  };
  /** All messages in the thread, chronological */
  messages: FederatedMessage[];
  /** Newest message in the thread (for sorting / preview) */
  lastMessage: FederatedMessage;
  /** Count of unread messages from the partner */
  unreadCount: number;
}

/** Recipient search result from federation member search */
interface FederatedMemberResult {
  id: number;
  name: string;
  avatar?: string | null;
  tenant_id: number;
  tenant_name: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Group flat messages into conversation threads keyed by partner.
 * The partner is whichever side is NOT the current user.
 */
function buildThreads(messages: FederatedMessage[], _currentUserId: number): FederatedThread[] {
  const threadMap = new Map<string, FederatedThread>();

  for (const msg of messages) {
    const isOutbound = msg.direction === 'outbound';
    const partner = isOutbound ? msg.receiver : msg.sender;
    const key = `${partner.id}-${partner.tenant_id}`;

    if (!threadMap.has(key)) {
      threadMap.set(key, {
        partner,
        messages: [],
        lastMessage: msg,
        unreadCount: 0,
      });
    }

    const thread = threadMap.get(key)!;
    thread.messages.push(msg);

    // Track the newest message
    if (new Date(msg.created_at) > new Date(thread.lastMessage.created_at)) {
      thread.lastMessage = msg;
    }

    // Count unread inbound messages
    if (!isOutbound && (msg.status === 'unread' || msg.status === 'delivered')) {
      thread.unreadCount += 1;
    }
  }

  // Sort threads by newest message first
  const threads = Array.from(threadMap.values());
  threads.sort(
    (a, b) => new Date(b.lastMessage.created_at).getTime() - new Date(a.lastMessage.created_at).getTime()
  );

  // Sort messages within each thread chronologically
  for (const thread of threads) {
    thread.messages.sort(
      (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime()
    );
  }

  return threads;
}

// ─────────────────────────────────────────────────────────────────────────────
// Animation variants
// ─────────────────────────────────────────────────────────────────────────────

const containerVariants = {
  hidden: { opacity: 0 },
  visible: {
    opacity: 1,
    transition: { staggerChildren: 0.04 },
  },
};

const itemVariants = {
  hidden: { opacity: 0, x: -16 },
  visible: { opacity: 1, x: 0 },
};

const bubbleVariants = {
  hidden: { opacity: 0, y: 12 },
  visible: { opacity: 1, y: 0 },
};

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function FederationMessagesPage() {
  usePageTitle('Federated Messages');

  const { user } = useAuth();
  const { hasFeature } = useTenant();
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();

  // ── Data state ──
  const [allMessages, setAllMessages] = useState<FederatedMessage[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [loadError, setLoadError] = useState(false);

  // ── Thread selection ──
  const [activeThreadKey, setActiveThreadKey] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');

  // ── Reply state ──
  const [replyText, setReplyText] = useState('');
  const [isSending, setIsSending] = useState(false);

  // ── Compose modal ──
  const [isComposeOpen, setIsComposeOpen] = useState(false);
  const [composeSubject, setComposeSubject] = useState('');
  const [composeBody, setComposeBody] = useState('');
  const [composeRecipientQuery, setComposeRecipientQuery] = useState('');
  const [composeRecipientResults, setComposeRecipientResults] = useState<FederatedMemberResult[]>([]);
  const [isSearchingRecipients, setIsSearchingRecipients] = useState(false);
  const [selectedRecipient, setSelectedRecipient] = useState<FederatedMemberResult | null>(null);
  const [isComposeSending, setIsComposeSending] = useState(false);

  // ── Mobile state ──
  const [mobileShowThread, setMobileShowThread] = useState(false);

  // ── Refs ──
  const messagesEndRef = useRef<HTMLDivElement>(null);

  // ── Derived data ──
  const threads = useMemo(
    () => buildThreads(allMessages, user?.id ?? 0),
    [allMessages, user?.id]
  );

  const filteredThreads = useMemo(() => {
    if (!searchQuery.trim()) return threads;
    const q = searchQuery.toLowerCase();
    return threads.filter(
      (t) =>
        t.partner.name.toLowerCase().includes(q) ||
        t.partner.tenant_name.toLowerCase().includes(q) ||
        t.lastMessage.subject.toLowerCase().includes(q)
    );
  }, [threads, searchQuery]);

  const activeThread = useMemo(
    () => threads.find((t) => `${t.partner.id}-${t.partner.tenant_id}` === activeThreadKey) ?? null,
    [threads, activeThreadKey]
  );

  // ── Load messages ──
  const loadMessages = useCallback(async () => {
    try {
      setIsLoading(true);
      setLoadError(false);
      const response = await api.get<FederatedMessage[]>('/v2/federation/messages');
      if (response.success && response.data) {
        setAllMessages(response.data);
      } else {
        setLoadError(true);
        toast.error('Error', 'Failed to load federated messages.');
      }
    } catch (err) {
      logError('Failed to load federated messages', err);
      setLoadError(true);
      toast.error('Error', 'Failed to load federated messages. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadMessages();
  }, [loadMessages]);

  // ── Handle compose URL params ──
  useEffect(() => {
    const compose = searchParams.get('compose');
    const toUser = searchParams.get('to_user');
    const toTenant = searchParams.get('to_tenant');

    if (compose === 'true') {
      setIsComposeOpen(true);

      if (toUser && toTenant) {
        // Pre-fill recipient from URL params
        setSelectedRecipient({
          id: parseInt(toUser, 10),
          name: '', // Will be resolved when recipient search loads
          tenant_id: parseInt(toTenant, 10),
          tenant_name: '',
        });

        // Try to fetch the user info to fill in the name
        api
          .get<FederatedMemberResult>(`/v2/federation/members/${toUser}?tenant_id=${toTenant}`)
          .then((res) => {
            if (res.success && res.data) {
              setSelectedRecipient(res.data);
            }
          })
          .catch(() => {
            // Silently fail - user can still search
          });
      }

      // Clear compose params from URL
      const newParams = new URLSearchParams(searchParams);
      newParams.delete('compose');
      newParams.delete('to_user');
      newParams.delete('to_tenant');
      setSearchParams(newParams, { replace: true });
    }
  }, []); // Only run on mount

  // ── Mark thread as read ──
  const markThreadRead = useCallback(
    async (thread: FederatedThread) => {
      // Find unread inbound message IDs
      const unreadIds = thread.messages
        .filter((m) => m.direction === 'inbound' && (m.status === 'unread' || m.status === 'delivered'))
        .map((m) => m.id);

      if (unreadIds.length === 0) return;

      // Optimistically mark as read in local state
      setAllMessages((prev) =>
        prev.map((msg) =>
          unreadIds.includes(msg.id) ? { ...msg, status: 'read' as const, read_at: new Date().toISOString() } : msg
        )
      );

      // Fire off mark-read calls
      for (const id of unreadIds) {
        try {
          await api.post(`/v2/federation/messages/${id}/mark-read`);
        } catch (err) {
          logError(`Failed to mark federated message ${id} as read`, err);
        }
      }
    },
    []
  );

  // ── Select a thread ──
  const selectThread = useCallback(
    (thread: FederatedThread) => {
      const key = `${thread.partner.id}-${thread.partner.tenant_id}`;
      setActiveThreadKey(key);
      setReplyText('');
      setMobileShowThread(true);

      // Mark as read
      markThreadRead(thread);

      // Scroll to bottom after render
      setTimeout(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
      }, 100);
    },
    [markThreadRead]
  );

  // ── Send reply ──
  const sendReply = useCallback(async () => {
    if (!replyText.trim() || !activeThread || isSending) return;

    try {
      setIsSending(true);
      const response = await api.post<FederatedMessage>('/v2/federation/messages', {
        receiver_id: activeThread.partner.id,
        receiver_tenant_id: activeThread.partner.tenant_id,
        subject: activeThread.lastMessage.subject,
        body: replyText.trim(),
        reference_message_id: activeThread.lastMessage.id,
      });

      if (response.success && response.data) {
        setAllMessages((prev) => [...prev, response.data!]);
        setReplyText('');
        toast.success('Message sent!', 'Your reply has been delivered.');

        // Scroll to bottom
        setTimeout(() => {
          messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
        }, 100);
      } else {
        toast.error('Error', response.error || 'Failed to send message.');
      }
    } catch (err) {
      logError('Failed to send federated reply', err);
      toast.error('Error', 'Failed to send message. Please try again.');
    } finally {
      setIsSending(false);
    }
  }, [replyText, activeThread, isSending, toast]);

  // ── Recipient search (debounced) ──
  useEffect(() => {
    if (!composeRecipientQuery.trim()) {
      setComposeRecipientResults([]);
      return;
    }

    const timer = setTimeout(async () => {
      try {
        setIsSearchingRecipients(true);
        const response = await api.get<FederatedMemberResult[]>(
          `/v2/federation/members?q=${encodeURIComponent(composeRecipientQuery)}&limit=10`
        );
        if (response.success && response.data) {
          // Filter out current user
          setComposeRecipientResults(response.data.filter((m) => m.id !== user?.id));
        }
      } catch (err) {
        logError('Failed to search federated members', err);
      } finally {
        setIsSearchingRecipients(false);
      }
    }, 300);

    return () => clearTimeout(timer);
  }, [composeRecipientQuery, user?.id]);

  // ── Send compose message ──
  const sendComposeMessage = useCallback(async () => {
    if (!selectedRecipient || !composeSubject.trim() || !composeBody.trim() || isComposeSending) return;

    try {
      setIsComposeSending(true);
      const response = await api.post<FederatedMessage>('/v2/federation/messages', {
        receiver_id: selectedRecipient.id,
        receiver_tenant_id: selectedRecipient.tenant_id,
        subject: composeSubject.trim(),
        body: composeBody.trim(),
      });

      if (response.success && response.data) {
        setAllMessages((prev) => [...prev, response.data!]);
        toast.success('Message sent!', `Your message to ${selectedRecipient.name || 'the recipient'} has been sent.`);

        // Close modal and reset
        setIsComposeOpen(false);
        setComposeSubject('');
        setComposeBody('');
        setComposeRecipientQuery('');
        setComposeRecipientResults([]);
        setSelectedRecipient(null);

        // Select the new thread
        const key = `${selectedRecipient.id}-${selectedRecipient.tenant_id}`;
        setActiveThreadKey(key);
        setMobileShowThread(true);
      } else {
        toast.error('Error', response.error || 'Failed to send message.');
      }
    } catch (err) {
      logError('Failed to send federated message', err);
      toast.error('Error', 'Failed to send message. Please try again.');
    } finally {
      setIsComposeSending(false);
    }
  }, [selectedRecipient, composeSubject, composeBody, isComposeSending, toast]);

  // ── Close compose and reset ──
  const closeCompose = useCallback(() => {
    setIsComposeOpen(false);
    setComposeSubject('');
    setComposeBody('');
    setComposeRecipientQuery('');
    setComposeRecipientResults([]);
    setSelectedRecipient(null);
  }, []);

  // ── Breadcrumbs ──
  const breadcrumbItems = [
    { label: 'Federation', href: '/federation' },
    { label: 'Messages' },
  ];

  // ── Check federation feature ──
  const isFederationEnabled = hasFeature('federation');

  return (
    <div className="max-w-6xl mx-auto space-y-4">
      <Breadcrumbs items={breadcrumbItems} />

      {/* Feature disabled notice */}
      {!isFederationEnabled && (
        <GlassCard className="p-4 border-l-4 border-amber-500 bg-amber-500/10">
          <div className="flex items-start gap-3">
            <Globe className="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" aria-hidden="true" />
            <div className="flex-1">
              <h3 className="font-semibold text-theme-primary">Federation Not Enabled</h3>
              <p className="text-sm text-theme-muted mt-1">
                Cross-community federation is not enabled for this community. Contact your administrator to enable it.
              </p>
            </div>
          </div>
        </GlassCard>
      )}

      {/* Header row */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <Globe className="w-7 h-7 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
            Federated Messages
          </h1>
          <p className="text-theme-muted mt-1">Messages with members from partner communities</p>
        </div>
        <Button
          className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
          startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
          onPress={() => setIsComposeOpen(true)}
          isDisabled={!isFederationEnabled}
        >
          Compose
        </Button>
      </div>

      {/* Main content area */}
      {isLoading ? (
        <GlassCard className="p-12 flex flex-col items-center justify-center gap-4">
          <Spinner size="lg" color="primary" />
          <p className="text-theme-muted">Loading federated messages...</p>
        </GlassCard>
      ) : loadError ? (
        <GlassCard className="p-8 text-center">
          <Globe className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h3 className="text-lg font-semibold text-theme-primary mb-2">Unable to Load Messages</h3>
          <p className="text-theme-muted mb-4">Something went wrong loading your federated messages.</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            onPress={loadMessages}
          >
            Try Again
          </Button>
        </GlassCard>
      ) : threads.length === 0 && !searchQuery ? (
        <GlassCard className="p-12 text-center">
          <div className="flex flex-col items-center gap-4">
            <div className="w-16 h-16 rounded-full bg-indigo-500/10 flex items-center justify-center">
              <MessageSquare className="w-8 h-8 text-indigo-500" aria-hidden="true" />
            </div>
            <h3 className="text-lg font-semibold text-theme-primary">No federated messages yet</h3>
            <p className="text-theme-muted max-w-md">
              Connect with members from partner communities! Start a conversation by clicking Compose.
            </p>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white mt-2"
              startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
              onPress={() => setIsComposeOpen(true)}
              isDisabled={!isFederationEnabled}
            >
              Compose Message
            </Button>
          </div>
        </GlassCard>
      ) : (
        /* Two-panel layout */
        <div className="flex gap-4 h-[calc(100vh-16rem)]">
          {/* ── Left Panel: Thread List ── */}
          <GlassCard
            className={`w-full md:w-[380px] flex-shrink-0 flex flex-col overflow-hidden ${
              mobileShowThread ? 'hidden md:flex' : 'flex'
            }`}
          >
            {/* Search */}
            <div className="p-3 border-b border-theme-default">
              <Input
                placeholder="Search conversations..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                startContent={<Search className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                classNames={{
                  input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                  inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                }}
                aria-label="Search federated conversations"
              />
            </div>

            {/* Thread list */}
            <div className="flex-1 overflow-y-auto">
              {filteredThreads.length === 0 ? (
                <div className="p-6 text-center">
                  <Search className="w-8 h-8 text-theme-subtle mx-auto mb-2" aria-hidden="true" />
                  <p className="text-theme-subtle text-sm">No conversations match your search</p>
                </div>
              ) : (
                <motion.div
                  variants={containerVariants}
                  initial="hidden"
                  animate="visible"
                >
                  {filteredThreads.map((thread) => {
                    const key = `${thread.partner.id}-${thread.partner.tenant_id}`;
                    const isActive = key === activeThreadKey;

                    return (
                      <motion.div key={key} variants={itemVariants}>
                        <Button
                          variant="light"
                          className={`w-full h-auto p-3 rounded-none justify-start text-left border-b border-theme-default transition-colors ${
                            isActive
                              ? 'bg-indigo-500/10 dark:bg-indigo-500/15'
                              : 'hover:bg-theme-hover'
                          }`}
                          onPress={() => selectThread(thread)}
                          aria-label={`Conversation with ${thread.partner.name} from ${thread.partner.tenant_name}${
                            thread.unreadCount > 0
                              ? `, ${thread.unreadCount} unread message${thread.unreadCount > 1 ? 's' : ''}`
                              : ''
                          }`}
                        >
                          <div className="flex items-center gap-3 w-full min-w-0">
                            {/* Avatar + unread dot */}
                            <div className="relative flex-shrink-0">
                              <Avatar
                                src={resolveAvatarUrl(thread.partner.avatar)}
                                name={thread.partner.name}
                                size="md"
                                className="ring-2 ring-theme-default"
                              />
                              {thread.unreadCount > 0 && (
                                <div className="absolute -top-0.5 -right-0.5 w-3 h-3 bg-indigo-500 rounded-full border-2 border-theme-default" />
                              )}
                            </div>

                            {/* Content */}
                            <div className="flex-1 min-w-0">
                              <div className="flex items-center justify-between gap-2">
                                <span
                                  className={`font-semibold truncate text-sm ${
                                    thread.unreadCount > 0 ? 'text-theme-primary' : 'text-theme-muted'
                                  }`}
                                >
                                  {thread.partner.name}
                                </span>
                                <span className="text-xs text-theme-subtle whitespace-nowrap flex-shrink-0">
                                  {formatRelativeTime(thread.lastMessage.created_at)}
                                </span>
                              </div>

                              {/* Community chip */}
                              <Chip
                                size="sm"
                                variant="flat"
                                startContent={<Globe className="w-2.5 h-2.5" aria-hidden="true" />}
                                classNames={{
                                  base: 'h-5 mt-0.5 bg-indigo-500/10 dark:bg-indigo-500/20',
                                  content: 'text-indigo-600 dark:text-indigo-400 text-[10px] px-1',
                                }}
                              >
                                {thread.partner.tenant_name}
                              </Chip>

                              {/* Preview */}
                              <p
                                className={`text-xs truncate mt-1 ${
                                  thread.unreadCount > 0 ? 'text-theme-muted font-medium' : 'text-theme-subtle'
                                }`}
                              >
                                {thread.lastMessage.direction === 'outbound' ? 'You: ' : ''}
                                {thread.lastMessage.body}
                              </p>
                            </div>

                            {/* Unread indicator + chevron */}
                            <div className="flex items-center gap-1 flex-shrink-0">
                              {thread.unreadCount > 0 && (
                                <Chip
                                  size="sm"
                                  color="primary"
                                  variant="solid"
                                  classNames={{
                                    base: 'h-5 min-w-5',
                                    content: 'text-xs px-1 font-bold',
                                  }}
                                >
                                  {thread.unreadCount > 9 ? '9+' : thread.unreadCount}
                                </Chip>
                              )}
                              <ChevronRight className="w-4 h-4 text-theme-subtle hidden md:block" aria-hidden="true" />
                            </div>
                          </div>
                        </Button>
                      </motion.div>
                    );
                  })}
                </motion.div>
              )}
            </div>
          </GlassCard>

          {/* ── Right Panel: Thread View ── */}
          <GlassCard
            className={`flex-1 flex flex-col overflow-hidden ${
              !mobileShowThread ? 'hidden md:flex' : 'flex'
            }`}
          >
            {activeThread ? (
              <>
                {/* Thread header */}
                <div className="p-4 border-b border-theme-default flex items-center gap-3">
                  {/* Mobile back button */}
                  <Button
                    isIconOnly
                    variant="light"
                    size="sm"
                    className="md:hidden text-theme-muted"
                    onPress={() => setMobileShowThread(false)}
                    aria-label="Back to message list"
                  >
                    <ArrowLeft className="w-5 h-5" aria-hidden="true" />
                  </Button>

                  <Avatar
                    src={resolveAvatarUrl(activeThread.partner.avatar)}
                    name={activeThread.partner.name}
                    size="md"
                    className="ring-2 ring-theme-default flex-shrink-0"
                  />
                  <div className="flex-1 min-w-0">
                    <h2 className="font-semibold text-theme-primary truncate">
                      {activeThread.partner.name}
                    </h2>
                    <Chip
                      size="sm"
                      variant="flat"
                      startContent={<Globe className="w-2.5 h-2.5" aria-hidden="true" />}
                      classNames={{
                        base: 'h-5 bg-indigo-500/10 dark:bg-indigo-500/20',
                        content: 'text-indigo-600 dark:text-indigo-400 text-[10px] px-1',
                      }}
                    >
                      {activeThread.partner.tenant_name}
                    </Chip>
                  </div>
                </div>

                {/* Messages */}
                <div className="flex-1 overflow-y-auto p-4 space-y-4">
                  <AnimatePresence mode="popLayout">
                    {activeThread.messages.map((msg) => {
                      const isOwn = msg.direction === 'outbound';
                      const isRead = msg.status === 'read' || !!msg.read_at;

                      return (
                        <motion.div
                          key={msg.id}
                          variants={bubbleVariants}
                          initial="hidden"
                          animate="visible"
                          exit={{ opacity: 0, y: -8 }}
                          className={`flex ${isOwn ? 'justify-end' : 'justify-start'}`}
                        >
                          <div className={`flex gap-2 max-w-[75%] ${isOwn ? 'flex-row-reverse' : ''}`}>
                            {!isOwn && (
                              <Avatar
                                src={resolveAvatarUrl(msg.sender.avatar)}
                                name={msg.sender.name}
                                size="sm"
                                className="flex-shrink-0 mt-1"
                              />
                            )}
                            <div>
                              {/* Subject line if first message or subject changed */}
                              {msg.subject && (
                                <p
                                  className={`text-[10px] font-medium mb-1 ${
                                    isOwn
                                      ? 'text-right text-indigo-300 dark:text-indigo-300'
                                      : 'text-theme-subtle'
                                  }`}
                                >
                                  {msg.subject}
                                </p>
                              )}

                              {/* Bubble */}
                              <div
                                className={`px-4 py-2.5 rounded-2xl ${
                                  isOwn
                                    ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-br-md'
                                    : 'bg-theme-elevated text-theme-primary rounded-bl-md'
                                }`}
                              >
                                <p className="text-sm whitespace-pre-wrap">{msg.body}</p>
                              </div>

                              {/* Timestamp + read indicator */}
                              <div
                                className={`flex items-center gap-1.5 mt-1 px-1 ${
                                  isOwn ? 'justify-end' : 'justify-start'
                                }`}
                              >
                                <span className="text-[10px] text-theme-subtle">
                                  {new Date(msg.created_at).toLocaleTimeString([], {
                                    hour: '2-digit',
                                    minute: '2-digit',
                                  })}
                                </span>
                                {isOwn && (
                                  isRead ? (
                                    <MailOpen className="w-3 h-3 text-indigo-400" aria-label="Read" />
                                  ) : (
                                    <Mail className="w-3 h-3 text-theme-subtle" aria-label="Delivered" />
                                  )
                                )}
                              </div>
                            </div>
                          </div>
                        </motion.div>
                      );
                    })}
                  </AnimatePresence>
                  <div ref={messagesEndRef} />
                </div>

                {/* Reply input */}
                <div className="p-4 border-t border-theme-default">
                  <div className="flex gap-3">
                    <Textarea
                      placeholder="Type your reply..."
                      value={replyText}
                      onChange={(e) => setReplyText(e.target.value)}
                      minRows={1}
                      maxRows={4}
                      classNames={{
                        input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                        inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                      }}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter' && !e.shiftKey) {
                          e.preventDefault();
                          sendReply();
                        }
                      }}
                      aria-label="Reply message"
                    />
                    <Button
                      isIconOnly
                      className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white self-end"
                      onPress={sendReply}
                      isLoading={isSending}
                      isDisabled={!replyText.trim()}
                      aria-label="Send reply"
                    >
                      <Send className="w-4 h-4" aria-hidden="true" />
                    </Button>
                  </div>
                </div>
              </>
            ) : (
              /* No thread selected placeholder (desktop) */
              <div className="flex-1 flex flex-col items-center justify-center gap-4 p-8">
                <div className="w-20 h-20 rounded-full bg-indigo-500/10 flex items-center justify-center">
                  <MessageSquare className="w-10 h-10 text-indigo-500/50" aria-hidden="true" />
                </div>
                <h3 className="text-lg font-semibold text-theme-muted">Select a conversation</h3>
                <p className="text-theme-subtle text-sm text-center max-w-xs">
                  Choose a conversation from the list to view messages, or compose a new message.
                </p>
              </div>
            )}
          </GlassCard>
        </div>
      )}

      {/* ── Compose Modal ── */}
      <Modal
        isOpen={isComposeOpen}
        onClose={closeCompose}
        size="lg"
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-4',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary flex items-center gap-2">
            <Globe className="w-5 h-5 text-indigo-500" aria-hidden="true" />
            New Federated Message
          </ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              {/* Recipient selector */}
              {selectedRecipient ? (
                <div className="flex items-center gap-3 p-3 rounded-lg bg-theme-elevated">
                  <Avatar
                    src={resolveAvatarUrl(selectedRecipient.avatar)}
                    name={selectedRecipient.name || 'Recipient'}
                    size="sm"
                    className="ring-2 ring-theme-default"
                  />
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-theme-primary truncate">
                      {selectedRecipient.name || `User #${selectedRecipient.id}`}
                    </p>
                    {selectedRecipient.tenant_name && (
                      <Chip
                        size="sm"
                        variant="flat"
                        startContent={<Globe className="w-2.5 h-2.5" aria-hidden="true" />}
                        classNames={{
                          base: 'h-5 bg-indigo-500/10 dark:bg-indigo-500/20',
                          content: 'text-indigo-600 dark:text-indigo-400 text-[10px] px-1',
                        }}
                      >
                        {selectedRecipient.tenant_name}
                      </Chip>
                    )}
                  </div>
                  <Button
                    size="sm"
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    onPress={() => {
                      setSelectedRecipient(null);
                      setComposeRecipientQuery('');
                    }}
                  >
                    Change
                  </Button>
                </div>
              ) : (
                <>
                  <Input
                    label="Recipient"
                    placeholder="Search for a member in partner communities..."
                    value={composeRecipientQuery}
                    onChange={(e) => setComposeRecipientQuery(e.target.value)}
                    startContent={<Search className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                    endContent={isSearchingRecipients ? <Spinner size="sm" /> : null}
                    classNames={{
                      input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                      inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                      label: 'text-theme-muted',
                    }}
                    autoFocus
                  />
                  {/* Search results */}
                  {composeRecipientResults.length > 0 && (
                    <div className="max-h-48 overflow-y-auto space-y-1 rounded-lg border border-theme-default bg-theme-card p-1">
                      {composeRecipientResults.map((member) => (
                        <Button
                          key={`${member.id}-${member.tenant_id}`}
                          variant="light"
                          className="w-full h-auto p-2 justify-start"
                          onPress={() => {
                            setSelectedRecipient(member);
                            setComposeRecipientQuery('');
                            setComposeRecipientResults([]);
                          }}
                        >
                          <div className="flex items-center gap-3 w-full">
                            <Avatar
                              src={resolveAvatarUrl(member.avatar)}
                              name={member.name}
                              size="sm"
                              className="ring-2 ring-theme-default"
                            />
                            <div className="flex-1 min-w-0 text-left">
                              <p className="font-medium text-theme-primary text-sm truncate">{member.name}</p>
                              <div className="flex items-center gap-1 text-theme-subtle">
                                <Globe className="w-2.5 h-2.5" aria-hidden="true" />
                                <span className="text-[11px]">{member.tenant_name}</span>
                              </div>
                            </div>
                          </div>
                        </Button>
                      ))}
                    </div>
                  )}
                  {composeRecipientQuery.trim() && !isSearchingRecipients && composeRecipientResults.length === 0 && (
                    <p className="text-sm text-theme-subtle text-center py-2">
                      No members found in partner communities
                    </p>
                  )}
                </>
              )}

              {/* Subject */}
              <Input
                label="Subject"
                placeholder="Enter a subject..."
                value={composeSubject}
                onChange={(e) => setComposeSubject(e.target.value)}
                classNames={{
                  input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                  inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                  label: 'text-theme-muted',
                }}
              />

              {/* Body */}
              <Textarea
                label="Message"
                placeholder="Write your message..."
                value={composeBody}
                onChange={(e) => setComposeBody(e.target.value)}
                minRows={4}
                maxRows={8}
                classNames={{
                  input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                  inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                  label: 'text-theme-muted',
                }}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-muted"
              onPress={closeCompose}
            >
              Cancel
            </Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<Send className="w-4 h-4" aria-hidden="true" />}
              onPress={sendComposeMessage}
              isLoading={isComposeSending}
              isDisabled={!selectedRecipient || !composeSubject.trim() || !composeBody.trim()}
            >
              Send Message
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default FederationMessagesPage;
