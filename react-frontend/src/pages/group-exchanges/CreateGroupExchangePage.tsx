/**
 * Create Group Exchange Page - 4-step wizard
 *
 * Steps:
 *  1. Exchange Details - Title, description, split type, total hours
 *  2. Add Participants - Search members, assign as provider/receiver
 *  3. Review Split    - Table showing calculated hour distribution
 *  4. Confirm & Create - Full summary, create button
 *
 * Route: /group-exchanges/create
 */

import { useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Button,
  Input,
  Textarea,
  Progress,
  Chip,
  Avatar,
  Spinner,
} from '@heroui/react';
import {
  ArrowRight,
  ArrowLeft,
  Users,
  Clock,
  Scale,
  Percent,
  CheckCircle,
  Search,
  Plus,
  X,
  UserPlus,
  ArrowLeftRight,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { usePageTitle } from '@/hooks';
import { useAuth, useTenant, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

type SplitType = 'equal' | 'custom' | 'weighted';

interface Participant {
  user_id: number;
  name: string;
  avatar: string | null;
  role: 'provider' | 'receiver';
  hours: number;
  weight: number;
}

interface SearchResult {
  id: number;
  name?: string;
  first_name?: string;
  last_name?: string;
  avatar_url?: string;
  avatar?: string;
  email?: string;
}

const TOTAL_STEPS = 4;

const SPLIT_TYPE_CARDS: { value: SplitType; title: string; description: string; icon: React.ReactNode }[] = [
  {
    value: 'equal',
    title: 'Equal Split',
    description: 'Total hours divided equally among all providers and receivers.',
    icon: <Scale className="w-6 h-6 text-indigo-500" />,
  },
  {
    value: 'custom',
    title: 'Custom Hours',
    description: 'Set specific hours for each participant individually.',
    icon: <Clock className="w-6 h-6 text-purple-500" />,
  },
  {
    value: 'weighted',
    title: 'Weighted Split',
    description: 'Distribute hours proportionally by assigned weights.',
    icon: <Percent className="w-6 h-6 text-emerald-500" />,
  },
];

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function CreateGroupExchangePage() {
  usePageTitle('Create Group Exchange');
  const navigate = useNavigate();
  const { user } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();

  // Wizard state
  const [currentStep, setCurrentStep] = useState(1);
  const [slideDirection, setSlideDirection] = useState(1);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Step 1: Exchange details
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [splitType, setSplitType] = useState<SplitType>('equal');
  const [totalHours, setTotalHours] = useState('');

  // Step 2: Participants
  const [participants, setParticipants] = useState<Participant[]>([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<SearchResult[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const [searchTimeout, setSearchTimeout] = useState<ReturnType<typeof setTimeout> | null>(null);

  // ─────────────────────────────────────────────────────────────────────────
  // Navigation
  // ─────────────────────────────────────────────────────────────────────────

  const goNext = useCallback(() => {
    setSlideDirection(1);
    setCurrentStep((s) => Math.min(s + 1, TOTAL_STEPS));
  }, []);

  const goBack = useCallback(() => {
    setSlideDirection(-1);
    setCurrentStep((s) => Math.max(s - 1, 1));
  }, []);

  // ─────────────────────────────────────────────────────────────────────────
  // Step 1 validation
  // ─────────────────────────────────────────────────────────────────────────

  const canProceedStep1 = title.trim().length > 0 && parseFloat(totalHours) > 0;

  // ─────────────────────────────────────────────────────────────────────────
  // Step 2: Member search
  // ─────────────────────────────────────────────────────────────────────────

  const handleSearchChange = useCallback((value: string) => {
    setSearchQuery(value);

    if (searchTimeout) {
      clearTimeout(searchTimeout);
    }

    if (value.trim().length < 2) {
      setSearchResults([]);
      return;
    }

    const timeout = setTimeout(async () => {
      try {
        setIsSearching(true);
        const response = await api.get<{ data: SearchResult[] }>(`/v2/users?search=${encodeURIComponent(value.trim())}&limit=10`);

        if (response.success && response.data) {
          const results = Array.isArray(response.data) ? response.data : [];
          // Filter out current user and already-added participants
          const existingIds = new Set(participants.map((p) => p.user_id));
          if (user?.id) {
            existingIds.add(user.id);
          }
          setSearchResults(results.filter((r: SearchResult) => !existingIds.has(r.id)));
        }
      } catch (err) {
        logError('Failed to search users', err);
      } finally {
        setIsSearching(false);
      }
    }, 300);

    setSearchTimeout(timeout);
  }, [participants, user?.id, searchTimeout]);

  const addParticipant = useCallback((result: SearchResult, role: 'provider' | 'receiver') => {
    const displayName = result.name || [result.first_name, result.last_name].filter(Boolean).join(' ') || 'Unknown';
    const avatarUrl = result.avatar_url || result.avatar || null;

    setParticipants((prev) => [
      ...prev,
      {
        user_id: result.id,
        name: displayName,
        avatar: avatarUrl,
        role,
        hours: 0,
        weight: 1,
      },
    ]);

    // Remove from search results
    setSearchResults((prev) => prev.filter((r) => r.id !== result.id));
  }, []);

  const removeParticipant = useCallback((userId: number) => {
    setParticipants((prev) => prev.filter((p) => p.user_id !== userId));
  }, []);

  const updateParticipantHours = useCallback((userId: number, hours: number) => {
    setParticipants((prev) =>
      prev.map((p) => (p.user_id === userId ? { ...p, hours } : p))
    );
  }, []);

  const updateParticipantWeight = useCallback((userId: number, weight: number) => {
    setParticipants((prev) =>
      prev.map((p) => (p.user_id === userId ? { ...p, weight } : p))
    );
  }, []);

  const providers = participants.filter((p) => p.role === 'provider');
  const receivers = participants.filter((p) => p.role === 'receiver');
  const canProceedStep2 = providers.length >= 1 && receivers.length >= 1;

  // ─────────────────────────────────────────────────────────────────────────
  // Step 3: Calculate split preview (client-side)
  // ─────────────────────────────────────────────────────────────────────────

  function calculateSplitPreview(): { providerId: number; providerName: string; receiverId: number; receiverName: string; amount: number }[] {
    const total = parseFloat(totalHours) || 0;
    if (total <= 0 || providers.length === 0 || receivers.length === 0) return [];

    const splits: { providerId: number; providerName: string; receiverId: number; receiverName: string; amount: number }[] = [];

    switch (splitType) {
      case 'equal': {
        const perTransaction = total / providers.length / receivers.length;
        for (const provider of providers) {
          for (const receiver of receivers) {
            splits.push({
              providerId: provider.user_id,
              providerName: provider.name,
              receiverId: receiver.user_id,
              receiverName: receiver.name,
              amount: Math.round(perTransaction * 100) / 100,
            });
          }
        }
        break;
      }

      case 'custom': {
        const totalReceiverHours = receivers.reduce((sum, r) => sum + r.hours, 0);
        if (totalReceiverHours <= 0) break;
        for (const provider of providers) {
          for (const receiver of receivers) {
            const receiverShare = receiver.hours / totalReceiverHours;
            splits.push({
              providerId: provider.user_id,
              providerName: provider.name,
              receiverId: receiver.user_id,
              receiverName: receiver.name,
              amount: Math.round(provider.hours * receiverShare * 100) / 100,
            });
          }
        }
        break;
      }

      case 'weighted': {
        const totalProviderWeight = providers.reduce((sum, p) => sum + p.weight, 0);
        const totalReceiverWeight = receivers.reduce((sum, r) => sum + r.weight, 0);
        if (totalProviderWeight <= 0 || totalReceiverWeight <= 0) break;
        for (const provider of providers) {
          const providerShare = (provider.weight / totalProviderWeight) * total;
          for (const receiver of receivers) {
            const receiverShare = receiver.weight / totalReceiverWeight;
            splits.push({
              providerId: provider.user_id,
              providerName: provider.name,
              receiverId: receiver.user_id,
              receiverName: receiver.name,
              amount: Math.round(providerShare * receiverShare * 100) / 100,
            });
          }
        }
        break;
      }
    }

    return splits;
  }

  const splitPreview = calculateSplitPreview();

  // ─────────────────────────────────────────────────────────────────────────
  // Step 4: Create exchange
  // ─────────────────────────────────────────────────────────────────────────

  const handleCreate = useCallback(async () => {
    try {
      setIsSubmitting(true);

      const payload = {
        title: title.trim(),
        description: description.trim() || null,
        split_type: splitType,
        total_hours: parseFloat(totalHours),
        participants: participants.map((p) => ({
          user_id: p.user_id,
          role: p.role,
          hours: p.hours,
          weight: p.weight,
        })),
      };

      const response = await api.post<{ id: number }>('/v2/group-exchanges', payload);

      if (response.success && response.data) {
        const newId = response.data.id;
        toast.success('Group exchange created!', 'Your exchange has been created successfully.');
        navigate(tenantPath(`/group-exchanges/${newId}`));
      } else {
        toast.error('Failed to create exchange', response.error || 'An error occurred.');
      }
    } catch (err) {
      logError('Failed to create group exchange', err);
      toast.error('Failed to create exchange', 'Something went wrong. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  }, [title, description, splitType, totalHours, participants, toast, navigate, tenantPath]);

  // ─────────────────────────────────────────────────────────────────────────
  // Animation
  // ─────────────────────────────────────────────────────────────────────────

  const slideVariants = {
    enter: (direction: number) => ({
      x: direction > 0 ? 80 : -80,
      opacity: 0,
    }),
    center: { x: 0, opacity: 1 },
    exit: (direction: number) => ({
      x: direction < 0 ? 80 : -80,
      opacity: 0,
    }),
  };

  // ─────────────────────────────────────────────────────────────────────────
  // Shared classNames
  // ─────────────────────────────────────────────────────────────────────────

  const inputClassNames = {
    input: 'bg-transparent text-theme-primary',
    inputWrapper: 'bg-theme-elevated border-theme-default',
    label: 'text-theme-muted',
  };

  // ─────────────────────────────────────────────────────────────────────────
  // Render
  // ─────────────────────────────────────────────────────────────────────────

  return (
    <div className="max-w-2xl mx-auto py-6 space-y-6">
      {/* Breadcrumbs */}
      <Breadcrumbs items={[
        { label: 'Group Exchanges', href: tenantPath('/group-exchanges') },
        { label: 'Create' },
      ]} />

      {/* Title */}
      <motion.div
        initial={{ opacity: 0, y: -10 }}
        animate={{ opacity: 1, y: 0 }}
        className="text-center"
      >
        <h1 className="text-2xl font-bold text-theme-primary">Create Group Exchange</h1>
        <p className="text-theme-muted mt-1">Set up a multi-participant exchange in a few steps</p>
      </motion.div>

      {/* Progress Bar */}
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ delay: 0.1 }}
      >
        <div className="flex items-center justify-between mb-2">
          <span className="text-sm text-theme-subtle">Step {currentStep} of {TOTAL_STEPS}</span>
          <span className="text-sm text-theme-subtle">
            {currentStep === 1 && 'Details'}
            {currentStep === 2 && 'Participants'}
            {currentStep === 3 && 'Review Split'}
            {currentStep === 4 && 'Confirm'}
          </span>
        </div>
        <Progress
          value={(currentStep / TOTAL_STEPS) * 100}
          classNames={{
            indicator: 'bg-gradient-to-r from-indigo-500 to-purple-600',
            track: 'bg-theme-elevated',
          }}
          aria-label={`Step ${currentStep} of ${TOTAL_STEPS}`}
        />
      </motion.div>

      {/* Step Content */}
      <AnimatePresence mode="wait" custom={slideDirection}>
        <motion.div
          key={currentStep}
          custom={slideDirection}
          variants={slideVariants}
          initial="enter"
          animate="center"
          exit="exit"
          transition={{ duration: 0.25, ease: 'easeInOut' }}
        >
          {/* ─── Step 1: Exchange Details ─── */}
          {currentStep === 1 && (
            <div className="space-y-6">
              <GlassCard className="p-6">
                <h2 className="text-lg font-semibold text-theme-primary mb-2 flex items-center gap-2">
                  <ArrowLeftRight className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
                  Exchange Details
                </h2>
                <p className="text-theme-muted text-sm mb-6">
                  Provide the basic details for your group exchange.
                </p>

                <div className="space-y-4">
                  <Input
                    label="Title"
                    placeholder="e.g., Community Garden Workday"
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    isRequired
                    classNames={inputClassNames}
                  />

                  <Textarea
                    label="Description (optional)"
                    placeholder="Describe the exchange..."
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    classNames={{
                      input: 'bg-transparent text-theme-primary',
                      inputWrapper: 'bg-theme-elevated border-theme-default',
                      label: 'text-theme-muted',
                    }}
                  />

                  <Input
                    type="number"
                    label="Total Hours"
                    placeholder="e.g., 10"
                    value={totalHours}
                    onChange={(e) => setTotalHours(e.target.value)}
                    min="0.25"
                    step="0.25"
                    isRequired
                    endContent={<span className="text-theme-subtle text-sm">hours</span>}
                    classNames={inputClassNames}
                  />
                </div>
              </GlassCard>

              {/* Split Type Selection */}
              <GlassCard className="p-6">
                <h2 className="text-lg font-semibold text-theme-primary mb-2 flex items-center gap-2">
                  <Scale className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
                  Split Type
                </h2>
                <p className="text-theme-muted text-sm mb-4">
                  How should hours be distributed among participants?
                </p>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  {SPLIT_TYPE_CARDS.map((card) => (
                    <button
                      key={card.value}
                      type="button"
                      onClick={() => setSplitType(card.value)}
                      className={`
                        p-4 rounded-xl border-2 text-left transition-all cursor-pointer
                        ${splitType === card.value
                          ? 'border-indigo-500 bg-indigo-500/10'
                          : 'border-theme-default bg-theme-elevated hover:border-indigo-500/30'}
                      `}
                      aria-pressed={splitType === card.value}
                    >
                      <div className="flex justify-center mb-3" aria-hidden="true">
                        {card.icon}
                      </div>
                      <h3 className="font-semibold text-theme-primary text-sm text-center mb-1">
                        {card.title}
                      </h3>
                      <p className="text-xs text-theme-subtle text-center">
                        {card.description}
                      </p>
                    </button>
                  ))}
                </div>
              </GlassCard>

              {/* Navigation */}
              <div className="flex items-center justify-end">
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  endContent={<ArrowRight className="w-4 h-4" aria-hidden="true" />}
                  onPress={goNext}
                  isDisabled={!canProceedStep1}
                >
                  Next
                </Button>
              </div>
            </div>
          )}

          {/* ─── Step 2: Add Participants ─── */}
          {currentStep === 2 && (
            <div className="space-y-6">
              {/* Search */}
              <GlassCard className="p-6">
                <h2 className="text-lg font-semibold text-theme-primary mb-2 flex items-center gap-2">
                  <UserPlus className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
                  Add Participants
                </h2>
                <p className="text-theme-muted text-sm mb-4">
                  Search for members and add them as providers or receivers.
                  You need at least 1 provider and 1 receiver.
                </p>

                <div className="relative">
                  <Input
                    placeholder="Search members by name..."
                    value={searchQuery}
                    onChange={(e) => handleSearchChange(e.target.value)}
                    startContent={<Search className="w-4 h-4 text-theme-muted" aria-hidden="true" />}
                    endContent={isSearching ? <Spinner size="sm" /> : null}
                    classNames={inputClassNames}
                    aria-label="Search members"
                  />
                </div>

                {/* Search Results */}
                {searchResults.length > 0 && (
                  <div className="mt-4 space-y-2">
                    {searchResults.map((result) => {
                      const displayName = result.name || [result.first_name, result.last_name].filter(Boolean).join(' ') || 'Unknown';
                      return (
                        <div
                          key={result.id}
                          className="flex items-center justify-between p-3 rounded-lg bg-theme-elevated"
                        >
                          <div className="flex items-center gap-3">
                            <Avatar
                              src={resolveAvatarUrl(result.avatar_url || result.avatar)}
                              name={displayName}
                              size="sm"
                            />
                            <div>
                              <p className="font-medium text-theme-primary text-sm">{displayName}</p>
                              {result.email && (
                                <p className="text-xs text-theme-subtle">{result.email}</p>
                              )}
                            </div>
                          </div>
                          <div className="flex gap-2">
                            <Button
                              size="sm"
                              variant="flat"
                              className="bg-emerald-500/20 text-emerald-400"
                              onPress={() => addParticipant(result, 'provider')}
                              startContent={<Plus className="w-3 h-3" aria-hidden="true" />}
                            >
                              Provider
                            </Button>
                            <Button
                              size="sm"
                              variant="flat"
                              className="bg-amber-500/20 text-amber-400"
                              onPress={() => addParticipant(result, 'receiver')}
                              startContent={<Plus className="w-3 h-3" aria-hidden="true" />}
                            >
                              Receiver
                            </Button>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                )}
              </GlassCard>

              {/* Current Participants */}
              {participants.length > 0 && (
                <GlassCard className="p-6">
                  <h3 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
                    <Users className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
                    Participants ({participants.length})
                  </h3>

                  {/* Providers */}
                  {providers.length > 0 && (
                    <div className="mb-4">
                      <h4 className="text-sm font-medium text-emerald-400 mb-2">
                        Providers ({providers.length})
                      </h4>
                      <div className="space-y-2">
                        {providers.map((p) => (
                          <ParticipantRow
                            key={p.user_id}
                            participant={p}
                            splitType={splitType}
                            onRemove={() => removeParticipant(p.user_id)}
                            onHoursChange={(h) => updateParticipantHours(p.user_id, h)}
                            onWeightChange={(w) => updateParticipantWeight(p.user_id, w)}
                            inputClassNames={inputClassNames}
                          />
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Receivers */}
                  {receivers.length > 0 && (
                    <div>
                      <h4 className="text-sm font-medium text-amber-400 mb-2">
                        Receivers ({receivers.length})
                      </h4>
                      <div className="space-y-2">
                        {receivers.map((p) => (
                          <ParticipantRow
                            key={p.user_id}
                            participant={p}
                            splitType={splitType}
                            onRemove={() => removeParticipant(p.user_id)}
                            onHoursChange={(h) => updateParticipantHours(p.user_id, h)}
                            onWeightChange={(w) => updateParticipantWeight(p.user_id, w)}
                            inputClassNames={inputClassNames}
                          />
                        ))}
                      </div>
                    </div>
                  )}
                </GlassCard>
              )}

              {/* Validation message */}
              {!canProceedStep2 && participants.length > 0 && (
                <div className="text-center text-sm text-amber-400">
                  You need at least 1 provider and 1 receiver to continue.
                </div>
              )}

              {/* Navigation */}
              <StepNavigation
                onBack={goBack}
                onNext={goNext}
                isNextDisabled={!canProceedStep2}
              />
            </div>
          )}

          {/* ─── Step 3: Review Split ─── */}
          {currentStep === 3 && (
            <div className="space-y-6">
              <GlassCard className="p-6">
                <h2 className="text-lg font-semibold text-theme-primary mb-2 flex items-center gap-2">
                  <Scale className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
                  Hour Split Preview
                </h2>
                <p className="text-theme-muted text-sm mb-6">
                  Review how hours will be distributed between participants.
                </p>

                {/* Summary */}
                <div className="grid grid-cols-3 gap-4 mb-6">
                  <div className="bg-theme-elevated rounded-lg p-4 text-center">
                    <p className="text-sm text-theme-muted">Providers</p>
                    <p className="text-2xl font-bold text-emerald-400">{providers.length}</p>
                  </div>
                  <div className="bg-theme-elevated rounded-lg p-4 text-center">
                    <p className="text-sm text-theme-muted">Total Hours</p>
                    <p className="text-2xl font-bold text-theme-primary">{totalHours}</p>
                  </div>
                  <div className="bg-theme-elevated rounded-lg p-4 text-center">
                    <p className="text-sm text-theme-muted">Receivers</p>
                    <p className="text-2xl font-bold text-amber-400">{receivers.length}</p>
                  </div>
                </div>

                <div className="text-center mb-6 text-sm text-theme-muted">
                  {providers.length} provider{providers.length !== 1 ? 's' : ''} will transfer{' '}
                  {totalHours} hour{parseFloat(totalHours) !== 1 ? 's' : ''} to{' '}
                  {receivers.length} receiver{receivers.length !== 1 ? 's' : ''} via{' '}
                  <span className="font-medium text-theme-primary capitalize">{splitType}</span> split.
                </div>

                {/* Split Table */}
                {splitPreview.length > 0 ? (
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                      <thead>
                        <tr className="border-b border-theme-default">
                          <th className="text-left py-2 px-3 text-theme-muted font-medium">Provider</th>
                          <th className="text-center py-2 px-3 text-theme-muted font-medium" aria-hidden="true" />
                          <th className="text-left py-2 px-3 text-theme-muted font-medium">Receiver</th>
                          <th className="text-right py-2 px-3 text-theme-muted font-medium">Hours</th>
                        </tr>
                      </thead>
                      <tbody>
                        {splitPreview.map((split, idx) => (
                          <tr key={idx} className="border-b border-theme-default/50">
                            <td className="py-2 px-3 text-emerald-400">{split.providerName}</td>
                            <td className="py-2 px-3 text-center text-theme-subtle">
                              <ArrowRight className="w-4 h-4 inline" aria-label="gives to" />
                            </td>
                            <td className="py-2 px-3 text-amber-400">{split.receiverName}</td>
                            <td className="py-2 px-3 text-right font-medium text-theme-primary">{split.amount}h</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                ) : (
                  <div className="text-center py-6 text-theme-muted">
                    Unable to calculate split. Check participant hours or weights.
                  </div>
                )}
              </GlassCard>

              {/* Participant Details */}
              <GlassCard className="p-6">
                <h3 className="text-sm font-medium text-theme-muted mb-3">Per-Participant Summary</h3>
                <div className="space-y-2">
                  {participants.map((p) => {
                    let totalForParticipant = 0;
                    if (p.role === 'provider') {
                      totalForParticipant = splitPreview
                        .filter((s) => s.providerId === p.user_id)
                        .reduce((sum, s) => sum + s.amount, 0);
                    } else {
                      totalForParticipant = splitPreview
                        .filter((s) => s.receiverId === p.user_id)
                        .reduce((sum, s) => sum + s.amount, 0);
                    }

                    return (
                      <div key={p.user_id} className="flex items-center justify-between p-3 rounded-lg bg-theme-elevated">
                        <div className="flex items-center gap-3">
                          <Avatar
                            src={resolveAvatarUrl(p.avatar)}
                            name={p.name}
                            size="sm"
                          />
                          <div>
                            <p className="font-medium text-theme-primary text-sm">{p.name}</p>
                            <Chip
                              size="sm"
                              variant="flat"
                              color={p.role === 'provider' ? 'success' : 'warning'}
                            >
                              {p.role}
                            </Chip>
                          </div>
                        </div>
                        <div className="text-right">
                          <p className="font-bold text-theme-primary">{totalForParticipant}h</p>
                          <p className="text-xs text-theme-subtle">
                            {p.role === 'provider' ? 'giving' : 'receiving'}
                          </p>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </GlassCard>

              <StepNavigation onBack={goBack} onNext={goNext} nextLabel="Review" />
            </div>
          )}

          {/* ─── Step 4: Confirm and Create ─── */}
          {currentStep === 4 && (
            <div className="space-y-6">
              <GlassCard className="p-6">
                <h2 className="text-lg font-semibold text-theme-primary mb-2 flex items-center gap-2">
                  <CheckCircle className="w-5 h-5 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                  Review Your Exchange
                </h2>
                <p className="text-theme-muted text-sm mb-6">
                  Everything looks good? Create your group exchange.
                </p>

                <div className="space-y-4">
                  {/* Title & Description */}
                  <div>
                    <h3 className="text-sm font-medium text-theme-muted mb-1">Title</h3>
                    <p className="text-theme-primary font-semibold">{title}</p>
                  </div>

                  {description && (
                    <div>
                      <h3 className="text-sm font-medium text-theme-muted mb-1">Description</h3>
                      <p className="text-theme-primary">{description}</p>
                    </div>
                  )}

                  <div className="border-t border-theme-default" />

                  {/* Split & Hours */}
                  <div className="flex flex-wrap gap-4">
                    <div>
                      <h3 className="text-sm font-medium text-theme-muted mb-1">Split Type</h3>
                      <Chip size="sm" variant="flat" color="primary" className="capitalize">{splitType}</Chip>
                    </div>
                    <div>
                      <h3 className="text-sm font-medium text-theme-muted mb-1">Total Hours</h3>
                      <p className="text-theme-primary font-semibold">{totalHours}h</p>
                    </div>
                    <div>
                      <h3 className="text-sm font-medium text-theme-muted mb-1">Participants</h3>
                      <p className="text-theme-primary font-semibold">{participants.length}</p>
                    </div>
                  </div>

                  <div className="border-t border-theme-default" />

                  {/* Participants summary */}
                  <div>
                    <h3 className="text-sm font-medium text-theme-muted mb-2">Participants</h3>
                    <div className="flex flex-wrap gap-2">
                      {participants.map((p) => (
                        <Chip
                          key={p.user_id}
                          size="sm"
                          variant="flat"
                          color={p.role === 'provider' ? 'success' : 'warning'}
                          avatar={
                            <Avatar
                              src={resolveAvatarUrl(p.avatar)}
                              name={p.name}
                              size="sm"
                            />
                          }
                        >
                          {p.name} ({p.role})
                        </Chip>
                      ))}
                    </div>
                  </div>
                </div>
              </GlassCard>

              {/* Action buttons */}
              <div className="flex items-center justify-between gap-3">
                <Button
                  variant="light"
                  className="text-theme-muted"
                  onPress={goBack}
                  startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
                >
                  Back
                </Button>
                <Button
                  size="lg"
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  onPress={handleCreate}
                  isLoading={isSubmitting}
                  startContent={!isSubmitting && <CheckCircle className="w-5 h-5" aria-hidden="true" />}
                >
                  Create Group Exchange
                </Button>
              </div>
            </div>
          )}
        </motion.div>
      </AnimatePresence>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Sub-components
// ─────────────────────────────────────────────────────────────────────────────

interface ParticipantRowProps {
  participant: Participant;
  splitType: SplitType;
  onRemove: () => void;
  onHoursChange: (hours: number) => void;
  onWeightChange: (weight: number) => void;
  inputClassNames: Record<string, string>;
}

function ParticipantRow({ participant, splitType, onRemove, onHoursChange, onWeightChange, inputClassNames }: ParticipantRowProps) {
  return (
    <div className="flex items-center gap-3 p-3 rounded-lg bg-theme-elevated">
      <Avatar
        src={resolveAvatarUrl(participant.avatar)}
        name={participant.name}
        size="sm"
      />
      <div className="flex-1 min-w-0">
        <p className="font-medium text-theme-primary text-sm truncate">{participant.name}</p>
        <Chip
          size="sm"
          variant="flat"
          color={participant.role === 'provider' ? 'success' : 'warning'}
        >
          {participant.role}
        </Chip>
      </div>

      {/* Custom hours input */}
      {splitType === 'custom' && (
        <Input
          type="number"
          size="sm"
          placeholder="Hours"
          value={participant.hours > 0 ? participant.hours.toString() : ''}
          onChange={(e) => onHoursChange(parseFloat(e.target.value) || 0)}
          min="0"
          step="0.25"
          className="w-24"
          classNames={inputClassNames}
          aria-label={`Hours for ${participant.name}`}
          endContent={<span className="text-theme-subtle text-xs">h</span>}
        />
      )}

      {/* Weighted input */}
      {splitType === 'weighted' && (
        <Input
          type="number"
          size="sm"
          placeholder="Weight"
          value={participant.weight > 0 ? participant.weight.toString() : ''}
          onChange={(e) => onWeightChange(parseFloat(e.target.value) || 0)}
          min="0.1"
          step="0.1"
          className="w-24"
          classNames={inputClassNames}
          aria-label={`Weight for ${participant.name}`}
          endContent={<span className="text-theme-subtle text-xs">x</span>}
        />
      )}

      <Button
        isIconOnly
        size="sm"
        variant="flat"
        className="bg-red-500/20 text-red-400"
        onPress={onRemove}
        aria-label={`Remove ${participant.name}`}
      >
        <X className="w-4 h-4" />
      </Button>
    </div>
  );
}

interface StepNavigationProps {
  onBack: () => void;
  onNext: () => void;
  nextLabel?: string;
  isLoading?: boolean;
  isNextDisabled?: boolean;
}

function StepNavigation({ onBack, onNext, nextLabel = 'Next', isLoading, isNextDisabled }: StepNavigationProps) {
  return (
    <div className="flex items-center justify-between">
      <Button
        variant="light"
        className="text-theme-muted"
        onPress={onBack}
        startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
      >
        Back
      </Button>
      <Button
        className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
        endContent={<ArrowRight className="w-4 h-4" aria-hidden="true" />}
        onPress={onNext}
        isLoading={isLoading}
        isDisabled={isNextDisabled}
      >
        {nextLabel}
      </Button>
    </div>
  );
}

export default CreateGroupExchangePage;
