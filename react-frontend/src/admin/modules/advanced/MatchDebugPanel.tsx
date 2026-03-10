// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Match Debug Panel
 * Admin tool to inspect match score breakdowns for any user.
 * Shows top matches with per-component score bars (category, skill,
 * proximity, freshness, reciprocity, quality).
 */

import { useState, useCallback, useRef } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Progress,
  Chip,
  Avatar,
  Input,
  Spinner,
  Button,
} from '@heroui/react';
import {
  Target,
  Search,
  User,
  RefreshCw,
  Sparkles,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { resolveAvatarUrl } from '@/lib/helpers';
import { PageHeader } from '../../components';
import { adminUsers } from '../../api/adminApi';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface DebugScores {
  category: number;
  skill: number;
  proximity: number;
  freshness: number;
  reciprocity: number;
  quality: number;
}

interface MatchedUser {
  id: number;
  name: string;
  avatar_url?: string | null;
}

interface DebugMatch {
  id: number;
  source_type: 'listing' | 'job' | 'volunteering' | 'group';
  source_id: number;
  match_score: number;
  title: string;
  description?: string;
  reasons: string[];
  matched_user?: MatchedUser | null;
  matched_at: string;
  category?: string | null;
  _debug_scores?: DebugScores;
}

interface UserSearchResult {
  id: number;
  name: string;
  email: string;
  avatar_url?: string | null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Score colour helper
// ─────────────────────────────────────────────────────────────────────────────

type ProgressColor = 'success' | 'warning' | 'danger' | 'default';

function scoreColor(value: number): ProgressColor {
  if (value >= 70) return 'success';
  if (value >= 40) return 'warning';
  if (value >= 1) return 'danger';
  return 'default';
}

const SOURCE_LABELS: Record<string, string> = {
  listing: 'Listing',
  job: 'Job',
  volunteering: 'Volunteering',
  group: 'Group',
};

const SCORE_COMPONENTS: Array<{ key: keyof DebugScores; label: string }> = [
  { key: 'category', label: 'Category' },
  { key: 'skill', label: 'Skill' },
  { key: 'proximity', label: 'Proximity' },
  { key: 'freshness', label: 'Freshness' },
  { key: 'reciprocity', label: 'Reciprocity' },
  { key: 'quality', label: 'Quality' },
];

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function MatchDebugPanel() {
  usePageTitle('Admin - Match Debug Panel');
  const toast = useToast();

  // User search state
  const [searchQuery, setSearchQuery] = useState('');
  const [userResults, setUserResults] = useState<UserSearchResult[]>([]);
  const [searchLoading, setSearchLoading] = useState(false);
  const [selectedUser, setSelectedUser] = useState<UserSearchResult | null>(null);
  const [showDropdown, setShowDropdown] = useState(false);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Match results state
  const [matches, setMatches] = useState<DebugMatch[]>([]);
  const [matchesLoading, setMatchesLoading] = useState(false);
  const [total, setTotal] = useState(0);

  // ─── User search with debounce ───────────────────────────────────────────

  const handleSearchChange = useCallback((value: string) => {
    setSearchQuery(value);
    setShowDropdown(false);

    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }

    if (!value.trim() || value.trim().length < 2) {
      setUserResults([]);
      return;
    }

    debounceRef.current = setTimeout(async () => {
      setSearchLoading(true);
      try {
        const res = await adminUsers.list({ search: value, limit: 8 } as Record<string, unknown>);
        if (res.success && res.data) {
          const data = res.data as unknown;
          const items: UserSearchResult[] = Array.isArray(data)
            ? (data as UserSearchResult[])
            : ((data as { data?: UserSearchResult[] }).data ?? []);
          setUserResults(items);
          setShowDropdown(items.length > 0);
        }
      } catch {
        // silently fail — dropdown is non-critical
      } finally {
        setSearchLoading(false);
      }
    }, 350);
  }, []);

  // ─── Select a user and load their matches ───────────────────────────────

  const selectUser = useCallback(async (user: UserSearchResult) => {
    setSelectedUser(user);
    setSearchQuery(user.name);
    setShowDropdown(false);
    setUserResults([]);
    setMatchesLoading(true);
    setMatches([]);
    setTotal(0);

    try {
      // Try the admin debug endpoint first, fall back to standard matches endpoint
      const res = await api.get(`/v2/matches/all?limit=10&debug=true&user_id=${user.id}`);
      if (res.success && res.data) {
        const payload = res.data as unknown;
        const items: DebugMatch[] = Array.isArray(payload)
          ? (payload as DebugMatch[])
          : ((payload as { matches?: DebugMatch[] }).matches ?? []);
        setMatches(items);
        setTotal(items.length);
      }
    } catch {
      toast.error('Failed to load matches for this user');
    } finally {
      setMatchesLoading(false);
    }
  }, [toast]);

  // ─── Reload for current user ─────────────────────────────────────────────

  const handleReload = useCallback(() => {
    if (selectedUser) {
      selectUser(selectedUser);
    }
  }, [selectedUser, selectUser]);

  // ─────────────────────────────────────────────────────────────────────────
  // Render
  // ─────────────────────────────────────────────────────────────────────────

  return (
    <div className="space-y-6">
      <PageHeader
        title="Match Debug Panel"
        description="Inspect match score breakdown for any user. Shows top 10 matches with per-component scores."
        actions={
          selectedUser ? (
            <Button
              onPress={handleReload}
              isDisabled={matchesLoading}
              variant="flat"
              className="flex items-center gap-2"
              aria-label="Reload matches"
              startContent={<RefreshCw className={`w-4 h-4 ${matchesLoading ? 'animate-spin' : ''}`} />}
            >
              Reload
            </Button>
          ) : undefined
        }
      />

      {/* User search card */}
      <Card>
        <CardHeader className="flex gap-3 items-center">
          <div className="p-2 rounded-xl bg-primary/10">
            <Target className="w-5 h-5 text-primary" />
          </div>
          <div>
            <p className="text-sm font-semibold text-foreground">Select a User</p>
            <p className="text-xs text-default-500">Search by name or email to inspect their matches</p>
          </div>
        </CardHeader>
        <CardBody>
          <div className="relative max-w-md">
            <Input
              value={searchQuery}
              onValueChange={handleSearchChange}
              placeholder="Search users by name or email..."
              aria-label="Search users"
              startContent={
                searchLoading
                  ? <Spinner size="sm" />
                  : <Search className="w-4 h-4 text-default-400" />
              }
              isClearable
              onClear={() => {
                setSearchQuery('');
                setUserResults([]);
                setShowDropdown(false);
                setSelectedUser(null);
                setMatches([]);
                setTotal(0);
              }}
            />

            {/* Dropdown results */}
            {showDropdown && userResults.length > 0 && (
              <div className="absolute z-50 top-full left-0 right-0 mt-1 bg-content1 border border-divider rounded-xl shadow-lg overflow-hidden">
                {userResults.map((user) => (
                  <Button
                    key={user.id}
                    onPress={() => selectUser(user)}
                    variant="light"
                    className="w-full flex items-center gap-3 px-4 py-3 justify-start h-auto rounded-none"
                  >
                    <Avatar
                      src={resolveAvatarUrl(user.avatar_url)}
                      name={user.name}
                      size="sm"
                    />
                    <div className="min-w-0 text-left">
                      <p className="text-sm font-medium text-foreground truncate">{user.name}</p>
                      <p className="text-xs text-default-500 truncate">{user.email}</p>
                    </div>
                    <span className="ml-auto text-xs text-default-400 shrink-0">#{user.id}</span>
                  </Button>
                ))}
              </div>
            )}
          </div>

          {/* Selected user pill */}
          {selectedUser && (
            <div className="mt-4 flex items-center gap-2">
              <span className="text-sm text-default-500">Inspecting:</span>
              <div className="flex items-center gap-2 bg-primary/10 rounded-full px-3 py-1">
                <Avatar
                  src={resolveAvatarUrl(selectedUser.avatar_url)}
                  name={selectedUser.name}
                  size="sm"
                  className="w-5 h-5"
                />
                <span className="text-sm font-medium text-primary">{selectedUser.name}</span>
                <span className="text-xs text-primary/70">#{selectedUser.id}</span>
              </div>
              {total > 0 && (
                <Chip size="sm" variant="flat" color="primary">
                  {total} match{total !== 1 ? 'es' : ''}
                </Chip>
              )}
            </div>
          )}
        </CardBody>
      </Card>

      {/* Loading state */}
      {matchesLoading && (
        <div className="flex justify-center py-16">
          <div className="flex flex-col items-center gap-3">
            <Spinner size="lg" />
            <p className="text-sm text-default-500">Loading match scores...</p>
          </div>
        </div>
      )}

      {/* Empty state when user selected but no matches */}
      {!matchesLoading && selectedUser && matches.length === 0 && (
        <Card>
          <CardBody>
            <div className="flex flex-col items-center gap-3 py-10 text-center">
              <div className="p-4 rounded-full bg-default-100">
                <Sparkles className="w-8 h-8 text-default-400" />
              </div>
              <p className="font-semibold text-foreground">No matches found</p>
              <p className="text-sm text-default-500 max-w-sm">
                This user has no matches above the minimum score threshold,
                or they have an incomplete profile (no skills or interests).
              </p>
            </div>
          </CardBody>
        </Card>
      )}

      {/* Prompt when no user selected */}
      {!matchesLoading && !selectedUser && (
        <Card>
          <CardBody>
            <div className="flex flex-col items-center gap-3 py-10 text-center">
              <div className="p-4 rounded-full bg-default-100">
                <User className="w-8 h-8 text-default-400" />
              </div>
              <p className="font-semibold text-foreground">No user selected</p>
              <p className="text-sm text-default-500">
                Search for a user above to view their match score breakdown.
              </p>
            </div>
          </CardBody>
        </Card>
      )}

      {/* Match results */}
      {!matchesLoading && matches.length > 0 && (
        <div className="space-y-4">
          <p className="text-sm text-default-500 font-medium">
            Top {matches.length} match{matches.length !== 1 ? 'es' : ''} for{' '}
            <span className="text-foreground font-semibold">{selectedUser?.name}</span>
          </p>

          {matches.map((match, index) => (
            <Card key={`${match.source_type}-${match.id}-${index}`}>
              <CardHeader className="flex items-start justify-between gap-4 pb-2">
                <div className="flex items-start gap-3 flex-1 min-w-0">
                  {/* Rank badge */}
                  <div className="shrink-0 w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-xs font-bold text-primary">
                    {index + 1}
                  </div>

                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap mb-1">
                      <span className="font-semibold text-foreground truncate">{match.title}</span>
                      <Chip
                        size="sm"
                        variant="flat"
                        color={match.source_type === 'listing' ? 'primary'
                          : match.source_type === 'job' ? 'warning'
                          : match.source_type === 'volunteering' ? 'danger'
                          : 'success'}
                      >
                        {SOURCE_LABELS[match.source_type] ?? match.source_type}
                      </Chip>
                      {match.category && (
                        <Chip size="sm" variant="bordered" className="text-xs">
                          {match.category}
                        </Chip>
                      )}
                    </div>

                    {match.description && (
                      <p className="text-xs text-default-500 line-clamp-2 mb-2">
                        {match.description}
                      </p>
                    )}

                    {/* Matched user row */}
                    {match.matched_user && (
                      <div className="flex items-center gap-2 mb-2">
                        <Avatar
                          src={resolveAvatarUrl(match.matched_user.avatar_url)}
                          name={match.matched_user.name}
                          size="sm"
                          className="w-5 h-5"
                        />
                        <span className="text-xs text-default-500">{match.matched_user.name}</span>
                      </div>
                    )}
                  </div>
                </div>

                {/* Overall score */}
                <div className="shrink-0 text-right">
                  <div className={`text-2xl font-bold ${
                    match.match_score >= 70 ? 'text-success'
                    : match.match_score >= 40 ? 'text-warning'
                    : 'text-danger'
                  }`}>
                    {match.match_score}
                  </div>
                  <div className="text-xs text-default-400">/ 100</div>
                </div>
              </CardHeader>

              <CardBody className="pt-0 space-y-4">
                {/* Overall score bar */}
                <div>
                  <div className="flex justify-between text-xs text-default-500 mb-1">
                    <span className="font-medium">Overall Match Score</span>
                    <span>{match.match_score}%</span>
                  </div>
                  <Progress
                    value={match.match_score}
                    color={scoreColor(match.match_score)}
                    size="md"
                    aria-label={`Overall match score: ${match.match_score}%`}
                  />
                </div>

                {/* Per-component score breakdown */}
                {match._debug_scores && (
                  <div>
                    <p className="text-xs font-medium text-default-500 mb-2">Score Breakdown</p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                      {SCORE_COMPONENTS.map(({ key, label }) => {
                        const value = match._debug_scores![key] ?? 0;
                        return (
                          <div key={key}>
                            <div className="flex justify-between text-xs mb-1">
                              <span className="text-default-500">{label}</span>
                              <span className={`font-medium ${
                                value >= 70 ? 'text-success'
                                : value >= 40 ? 'text-warning'
                                : value >= 1 ? 'text-danger'
                                : 'text-default-400'
                              }`}>
                                {value}
                              </span>
                            </div>
                            <Progress
                              value={value}
                              color={scoreColor(value)}
                              size="sm"
                              aria-label={`${label} score: ${value}`}
                            />
                          </div>
                        );
                      })}
                    </div>
                  </div>
                )}

                {/* Debug scores not available notice */}
                {!match._debug_scores && (
                  <p className="text-xs text-default-400 italic">
                    Detailed score breakdown not available for this match type.
                    Only listings returned from SmartMatchingEngine include per-component scores.
                  </p>
                )}

                {/* Match reasons */}
                {match.reasons && match.reasons.length > 0 && (
                  <div>
                    <p className="text-xs font-medium text-default-500 mb-2">Match Reasons</p>
                    <div className="flex flex-wrap gap-1.5">
                      {match.reasons.map((reason, i) => (
                        <Chip key={i} size="sm" variant="flat" color="primary" className="text-xs">
                          {reason}
                        </Chip>
                      ))}
                    </div>
                  </div>
                )}
              </CardBody>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}

export default MatchDebugPanel;
