// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Skills Browse Page - Explore skill categories, skills, and skilled members
 */

import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { Button, Input, Chip, Spinner, Avatar } from '@heroui/react';
import {
  Sparkles,
  Search,
  ChevronRight,
  ChevronDown,
  Users,
  RefreshCw,
  AlertTriangle,
  FolderTree,
  GraduationCap,
  HandHelping,
  Megaphone,
  Settings,
  ArrowRight,
  X,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { usePageTitle } from '@/hooks';
import { useAuth, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import type { SkillCategory } from '@/components/skills/SkillSelector';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface CategorySkill {
  skill_name: string;
  user_count: number;
  offering_count: number;
  requesting_count: number;
}

interface SkillMember {
  id: number;
  first_name: string;
  last_name: string;
  avatar?: string;
  proficiency_level: string;
  is_offering: boolean;
  is_requesting: boolean;
}

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const proficiencyConfig: Record<string, { labelKey: string; fallback: string; color: string; bg: string; dots: number }> = {
  beginner: { labelKey: 'skills.proficiency.beginner', fallback: 'Beginner', color: 'text-emerald-500', bg: 'bg-emerald-500', dots: 1 },
  intermediate: { labelKey: 'skills.proficiency.intermediate', fallback: 'Intermediate', color: 'text-blue-500', bg: 'bg-blue-500', dots: 2 },
  advanced: { labelKey: 'skills.proficiency.advanced', fallback: 'Advanced', color: 'text-purple-500', bg: 'bg-purple-500', dots: 3 },
  expert: { labelKey: 'skills.proficiency.expert', fallback: 'Expert', color: 'text-amber-500', bg: 'bg-amber-500', dots: 4 },
};

// ─────────────────────────────────────────────────────────────────────────────
// Subcomponents
// ─────────────────────────────────────────────────────────────────────────────

function ProficiencyBadge({ level }: { level: string }) {
  const { t } = useTranslation('common');
  const config = proficiencyConfig[level] ?? proficiencyConfig.beginner;
  return (
    <span className={`inline-flex items-center gap-1 text-xs font-medium ${config.color}`}>
      {[1, 2, 3, 4].map((d) => (
        <span
          key={d}
          className={`w-1.5 h-1.5 rounded-full ${d <= config.dots ? config.bg : 'bg-theme-hover'}`}
        />
      ))}
      <span className="ml-0.5">{t(config.labelKey, config.fallback)}</span>
    </span>
  );
}

function StatCard({ icon: Icon, label, value }: { icon: React.ElementType; label: string; value: number | string }) {
  return (
    <GlassCard className="p-4 text-center">
      <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 flex items-center justify-center mx-auto mb-2">
        <Icon className="w-5 h-5 text-indigo-500" aria-hidden="true" />
      </div>
      <p className="text-2xl font-bold text-theme-primary">{value}</p>
      <p className="text-xs text-theme-subtle mt-0.5">{label}</p>
    </GlassCard>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function SkillsBrowsePage() {
  const { t } = useTranslation('common');
  usePageTitle(t('skills.browse_title', 'Browse Skills'));
  const { tenantPath } = useTenant();
  const { isAuthenticated } = useAuth();

  // State
  const [categories, setCategories] = useState<SkillCategory[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [expandedCategory, setExpandedCategory] = useState<number | null>(null);
  const [categorySkills, setCategorySkills] = useState<Record<number, CategorySkill[]>>({});
  const [loadingSkills, setLoadingSkills] = useState<number | null>(null);
  const [selectedSkill, setSelectedSkill] = useState<{ categoryId: number; skillName: string } | null>(null);
  const [skillMembers, setSkillMembers] = useState<SkillMember[]>([]);
  const [loadingMembers, setLoadingMembers] = useState(false);

  // ── Load categories ──────────────────────────────────────────────────────
  useEffect(() => {
    loadCategories();
  }, []);

  const loadCategories = async () => {
    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<SkillCategory[]>('/v2/skills/categories');
      if (response.success && response.data) {
        setCategories(response.data);
      } else {
        setError('Failed to load skill categories');
      }
    } catch (err) {
      logError('Failed to load skill categories', err);
      setError('Failed to load skill categories. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };

  // ── Load skills for a category ───────────────────────────────────────────
  const loadCategorySkills = useCallback(async (categoryId: number) => {
    if (categorySkills[categoryId]) return; // Already cached
    try {
      setLoadingSkills(categoryId);
      const response = await api.get<CategorySkill[]>(`/v2/skills/categories/${categoryId}`);
      if (response.success && response.data) {
        // The endpoint returns a category object with a `skills` array
        const data = response.data as unknown as { skills?: CategorySkill[] };
        setCategorySkills((prev) => ({
          ...prev,
          [categoryId]: data.skills || [],
        }));
      }
    } catch (err) {
      logError('Failed to load category skills', err);
    } finally {
      setLoadingSkills(null);
    }
  }, [categorySkills]);

  // ── Toggle category expansion ────────────────────────────────────────────
  const toggleCategory = useCallback((categoryId: number) => {
    setSelectedSkill(null);
    setSkillMembers([]);
    if (expandedCategory === categoryId) {
      setExpandedCategory(null);
    } else {
      setExpandedCategory(categoryId);
      loadCategorySkills(categoryId);
    }
  }, [expandedCategory, loadCategorySkills]);

  // ── Load members for a skill ─────────────────────────────────────────────
  const selectSkill = useCallback(async (categoryId: number, skillName: string) => {
    if (selectedSkill?.categoryId === categoryId && selectedSkill?.skillName === skillName) {
      setSelectedSkill(null);
      setSkillMembers([]);
      return;
    }
    try {
      setSelectedSkill({ categoryId, skillName });
      setLoadingMembers(true);
      setSkillMembers([]);
      const response = await api.get<SkillMember[]>(
        `/v2/skills/members?skill=${encodeURIComponent(skillName)}&limit=30`
      );
      if (response.success && response.data) {
        setSkillMembers(response.data);
      }
    } catch (err) {
      logError('Failed to load skill members', err);
    } finally {
      setLoadingMembers(false);
    }
  }, [selectedSkill]);

  // ── Filter categories by search ──────────────────────────────────────────
  const lowerQuery = searchQuery.toLowerCase();
  const filteredCategories = searchQuery
    ? categories.filter(
        (cat) =>
          cat.name.toLowerCase().includes(lowerQuery) ||
          cat.children?.some((child) => child.name.toLowerCase().includes(lowerQuery))
      )
    : categories;

  // ── Compute stats ────────────────────────────────────────────────────────
  const totalCategories = categories.length;
  const totalSubCategories = categories.reduce(
    (sum, cat) => sum + (cat.children?.length || 0),
    0
  );
  const totalSkillsCount = categories.reduce(
    (sum, cat) =>
      sum +
      (cat.skills_count || 0) +
      (cat.children?.reduce((s, c) => s + (c.skills_count || 0), 0) || 0),
    0
  );

  // ── Animation variants ───────────────────────────────────────────────────
  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.04 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 16 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      {/* ── Hero / Explainer ──────────────────────────────────────────── */}
      <GlassCard className="p-6 sm:p-8 relative overflow-hidden">
        <div className="absolute top-0 right-0 w-64 h-64 bg-gradient-to-bl from-indigo-500/5 to-transparent rounded-full -translate-y-1/2 translate-x-1/2 pointer-events-none" />
        <div className="relative">
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
              <GraduationCap className="w-5 h-5 text-white" aria-hidden="true" />
            </div>
            {t('skills.browse_title', 'Community Skills Directory')}
          </h1>

          <p className="text-theme-muted mt-3 text-sm leading-relaxed max-w-2xl">
            {t(
              'skills.explainer',
              'Every member of your community has something to offer. This directory shows all the skills people have shared \u2014 from gardening and cooking to IT support and language tutoring. Browse by category, see who can help, and discover members offering or looking for specific skills.'
            )}
          </p>

          <div className="flex flex-col sm:flex-row gap-4 mt-4">
            <div className="flex items-start gap-2">
              <HandHelping className="w-4 h-4 text-emerald-500 mt-0.5 shrink-0" aria-hidden="true" />
              <p className="text-xs text-theme-subtle">
                <span className="font-medium text-emerald-600 dark:text-emerald-400">
                  {t('skills.offering_label', 'Offering')}
                </span>
                {' \u2014 '}
                {t('skills.offering_desc', 'members willing to share this skill with others')}
              </p>
            </div>
            <div className="flex items-start gap-2">
              <Megaphone className="w-4 h-4 text-blue-500 mt-0.5 shrink-0" aria-hidden="true" />
              <p className="text-xs text-theme-subtle">
                <span className="font-medium text-blue-600 dark:text-blue-400">
                  {t('skills.requesting_label', 'Requesting')}
                </span>
                {' \u2014 '}
                {t('skills.requesting_desc', 'members looking to learn or receive help with this skill')}
              </p>
            </div>
          </div>

          {isAuthenticated && (
            <Link
              to={tenantPath('/settings?tab=skills')}
              className="inline-flex items-center gap-2 mt-4 px-4 py-2 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 text-sm font-medium hover:bg-indigo-500/20 transition-colors"
            >
              <Settings className="w-4 h-4" aria-hidden="true" />
              {t('skills.add_your_skills', 'Add your own skills')}
              <ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />
            </Link>
          )}
        </div>
      </GlassCard>

      {/* ── Stats ────────────────────────────────────────────────────────── */}
      {!isLoading && !error && categories.length > 0 && (
        <div className="grid grid-cols-3 gap-3">
          <StatCard icon={FolderTree} label={t('skills.categories', 'Categories')} value={totalCategories} />
          <StatCard icon={Sparkles} label={t('skills.sub_categories', 'Sub-categories')} value={totalSubCategories} />
          <StatCard icon={Users} label={t('skills.total_skills', 'Skills Listed')} value={totalSkillsCount} />
        </div>
      )}

      {/* ── Search ───────────────────────────────────────────────────────── */}
      <GlassCard className="p-4">
        <Input
          placeholder={t('skills.search_placeholder', 'Search skill categories...')}
          value={searchQuery}
          onChange={(e) => {
            setSearchQuery(e.target.value);
            setExpandedCategory(null);
            setSelectedSkill(null);
          }}
          startContent={<Search className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
          aria-label={t('skills.search_placeholder')}
          endContent={
            searchQuery ? (
              <Button
                isIconOnly
                size="sm"
                variant="light"
                onPress={() => setSearchQuery('')}
                className="p-0.5 rounded-full hover:bg-theme-hover transition-colors min-w-0 w-auto h-auto"
                aria-label="Clear search"
              >
                <X className="w-3.5 h-3.5 text-theme-subtle" />
              </Button>
            ) : undefined
          }
          classNames={{
            input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
            inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
          }}
        />
      </GlassCard>

      {/* ── Error State ──────────────────────────────────────────────────── */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h3 className="text-lg font-semibold text-theme-primary mb-2">
            {t('common.unable_to_load', 'Unable to load')}
          </h3>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={loadCategories}
          >
            {t('common.try_again', 'Try Again')}
          </Button>
        </GlassCard>
      )}

      {/* ── Loading ──────────────────────────────────────────────────────── */}
      {isLoading && (
        <div className="space-y-3">
          {[1, 2, 3, 4, 5].map((i) => (
            <GlassCard key={i} className="p-4 animate-pulse">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-theme-hover" />
                <div className="flex-1 space-y-2">
                  <div className="h-4 w-40 bg-theme-hover rounded" />
                  <div className="h-3 w-24 bg-theme-hover rounded" />
                </div>
              </div>
            </GlassCard>
          ))}
        </div>
      )}

      {/* ── Categories ───────────────────────────────────────────────────── */}
      {!isLoading && !error && (
        <>
          {filteredCategories.length === 0 ? (
            <EmptyState
              icon={<FolderTree className="w-12 h-12" aria-hidden="true" />}
              title={t('skills.no_categories', 'No categories found')}
              description={
                searchQuery
                  ? t('skills.no_match', `No categories match "{{query}}"`, { query: searchQuery })
                  : t('skills.no_categories_yet', 'No skill categories available yet.')
              }
            />
          ) : (
            <motion.div
              variants={containerVariants}
              initial="hidden"
              animate="visible"
              className="space-y-3"
            >
              {filteredCategories.map((category) => {
                const isExpanded = expandedCategory === category.id;
                const skills = categorySkills[category.id] || [];
                const isLoadingThis = loadingSkills === category.id;

                return (
                  <motion.div key={category.id} variants={itemVariants}>
                    <GlassCard className="overflow-hidden">
                      {/* Category Header */}
                      <Button
                        variant="light"
                        onPress={() => toggleCategory(category.id)}
                        className="w-full flex items-center justify-between p-4 hover:bg-theme-hover transition-colors justify-between h-auto"
                        aria-expanded={isExpanded}
                      >
                        <div className="flex items-center gap-3">
                          <div className="w-11 h-11 rounded-xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 flex items-center justify-center text-lg shrink-0">
                            {category.icon || '📂'}
                          </div>
                          <div className="text-left">
                            <h3 className="font-semibold text-theme-primary text-base">
                              {category.name}
                            </h3>
                            <div className="flex items-center gap-3 mt-0.5">
                              {category.skills_count !== undefined && category.skills_count > 0 && (
                                <span className="text-xs text-theme-subtle flex items-center gap-1">
                                  <Users className="w-3 h-3" aria-hidden="true" />
                                  {category.skills_count} {t('skills.members_with_skills', 'skilled members')}
                                </span>
                              )}
                              {category.children && category.children.length > 0 && (
                                <span className="text-xs text-theme-subtle">
                                  {category.children.length} {t('skills.sub_categories', 'sub-categories')}
                                </span>
                              )}
                            </div>
                          </div>
                        </div>
                        <div className="flex items-center gap-2 shrink-0">
                          {isExpanded ? (
                            <ChevronDown className="w-5 h-5 text-indigo-500 transition-transform" aria-hidden="true" />
                          ) : (
                            <ChevronRight className="w-5 h-5 text-theme-subtle transition-transform" aria-hidden="true" />
                          )}
                        </div>
                      </Button>

                      {/* Expanded: Sub-categories + Skills */}
                      <AnimatePresence>
                        {isExpanded && (
                          <motion.div
                            initial={{ height: 0, opacity: 0 }}
                            animate={{ height: 'auto', opacity: 1 }}
                            exit={{ height: 0, opacity: 0 }}
                            transition={{ duration: 0.2 }}
                            className="overflow-hidden"
                          >
                            <div className="px-4 pb-4 border-t border-theme-default">
                              {/* Sub-categories (if any) */}
                              {category.children && category.children.length > 0 && (
                                <div className="pt-3 pb-2">
                                  <p className="text-xs font-medium text-theme-subtle uppercase tracking-wider mb-2">
                                    {t('skills.sub_categories', 'Sub-categories')}
                                  </p>
                                  <div className="flex flex-wrap gap-2">
                                    {category.children.map((child) => (
                                      <Chip
                                        key={child.id}
                                        variant="flat"
                                        size="sm"
                                        className="bg-theme-elevated text-theme-primary border border-theme-default"
                                        startContent={<span className="text-xs">{child.icon || '🏷️'}</span>}
                                      >
                                        {child.name}
                                        {child.skills_count !== undefined && child.skills_count > 0 && (
                                          <span className="text-theme-subtle ml-1">({child.skills_count})</span>
                                        )}
                                      </Chip>
                                    ))}
                                  </div>
                                </div>
                              )}

                              {/* Skills in this category */}
                              {isLoadingThis ? (
                                <div className="flex items-center justify-center py-6">
                                  <Spinner size="sm" />
                                  <span className="text-sm text-theme-subtle ml-2">
                                    {t('skills.loading_skills', 'Loading skills...')}
                                  </span>
                                </div>
                              ) : skills.length > 0 ? (
                                <div className="pt-3">
                                  <p className="text-xs font-medium text-theme-subtle uppercase tracking-wider mb-2">
                                    {t('skills.skills_in_category', 'Skills')} ({skills.length})
                                  </p>
                                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    {skills.map((skill) => {
                                      const isSelected =
                                        selectedSkill?.categoryId === category.id &&
                                        selectedSkill?.skillName === skill.skill_name;
                                      return (
                                        <Button
                                          key={skill.skill_name}
                                          variant="flat"
                                          onPress={() => selectSkill(category.id, skill.skill_name)}
                                          className={`text-left p-3 rounded-xl border transition-all justify-start h-auto ${
                                            isSelected
                                              ? 'bg-indigo-500/10 border-indigo-500/40 shadow-sm shadow-indigo-500/10'
                                              : 'bg-theme-elevated border-theme-default hover:border-indigo-500/30 hover:bg-theme-hover'
                                          }`}
                                        >
                                          <div className="flex items-center justify-between">
                                            <span className="text-sm font-medium text-theme-primary">
                                              {skill.skill_name}
                                            </span>
                                            <Chip
                                              size="sm"
                                              variant="flat"
                                              className="bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
                                            >
                                              <Users className="w-3 h-3 mr-1 inline" aria-hidden="true" />
                                              {skill.user_count}
                                            </Chip>
                                          </div>
                                          <div className="flex items-center gap-3 mt-1.5">
                                            {skill.offering_count > 0 && (
                                              <span className="text-xs text-emerald-500 flex items-center gap-1">
                                                <HandHelping className="w-3 h-3" aria-hidden="true" />
                                                {skill.offering_count} {t('skills.offering', 'offering')}
                                              </span>
                                            )}
                                            {skill.requesting_count > 0 && (
                                              <span className="text-xs text-blue-500 flex items-center gap-1">
                                                <Megaphone className="w-3 h-3" aria-hidden="true" />
                                                {skill.requesting_count} {t('skills.requesting', 'requesting')}
                                              </span>
                                            )}
                                          </div>
                                        </Button>
                                      );
                                    })}
                                  </div>
                                </div>
                              ) : (
                                <div className="py-6 text-center">
                                  <p className="text-sm text-theme-subtle">
                                    {t('skills.no_skills_yet', 'No skills listed in this category yet.')}
                                  </p>
                                </div>
                              )}

                              {/* ── Member list for selected skill ─────────────── */}
                              <AnimatePresence>
                                {selectedSkill?.categoryId === category.id && (
                                  <motion.div
                                    initial={{ height: 0, opacity: 0 }}
                                    animate={{ height: 'auto', opacity: 1 }}
                                    exit={{ height: 0, opacity: 0 }}
                                    transition={{ duration: 0.2 }}
                                    className="overflow-hidden"
                                  >
                                    <div className="mt-4 pt-4 border-t border-theme-default">
                                      <div className="flex items-center justify-between mb-3">
                                        <h4 className="text-sm font-semibold text-theme-primary flex items-center gap-2">
                                          <Users className="w-4 h-4 text-indigo-500" aria-hidden="true" />
                                          {t('skills.members_with', 'Members with "{{skill}}"', {
                                            skill: selectedSkill.skillName,
                                          })}
                                        </h4>
                                        <Button
                                          isIconOnly
                                          size="sm"
                                          variant="light"
                                          onPress={() => {
                                            setSelectedSkill(null);
                                            setSkillMembers([]);
                                          }}
                                          className="p-1 rounded-lg hover:bg-theme-hover transition-colors min-w-0 w-auto h-auto"
                                          aria-label="Close members list"
                                        >
                                          <X className="w-4 h-4 text-theme-subtle" />
                                        </Button>
                                      </div>

                                      {loadingMembers ? (
                                        <div className="flex items-center justify-center py-6">
                                          <Spinner size="sm" />
                                        </div>
                                      ) : skillMembers.length > 0 ? (
                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                          {skillMembers.map((member) => (
                                            <Link
                                              key={member.id}
                                              to={tenantPath(`/profile/${member.id}`)}
                                              className="flex items-center gap-3 p-3 rounded-xl bg-theme-elevated border border-theme-default hover:border-indigo-500/30 hover:bg-theme-hover transition-all"
                                            >
                                              <Avatar
                                                src={resolveAvatarUrl(member.avatar)}
                                                name={`${member.first_name} ${member.last_name}`}
                                                size="sm"
                                                className="shrink-0"
                                              />
                                              <div className="min-w-0 flex-1">
                                                <p className="text-sm font-medium text-theme-primary truncate">
                                                  {member.first_name} {member.last_name}
                                                </p>
                                                {member.proficiency_level && (
                                                  <ProficiencyBadge level={member.proficiency_level} />
                                                )}
                                              </div>
                                              <div className="flex gap-1 shrink-0">
                                                {member.is_offering && (
                                                  <Chip size="sm" variant="flat" className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-[10px]">
                                                    {t('skills.offers', 'Offers')}
                                                  </Chip>
                                                )}
                                                {member.is_requesting && (
                                                  <Chip size="sm" variant="flat" className="bg-blue-500/10 text-blue-600 dark:text-blue-400 text-[10px]">
                                                    {t('skills.wants', 'Wants')}
                                                  </Chip>
                                                )}
                                              </div>
                                            </Link>
                                          ))}
                                        </div>
                                      ) : (
                                        <div className="py-4 text-center">
                                          <p className="text-sm text-theme-subtle">
                                            {t('skills.no_members', 'No members found with this skill.')}
                                          </p>
                                        </div>
                                      )}
                                    </div>
                                  </motion.div>
                                )}
                              </AnimatePresence>
                            </div>
                          </motion.div>
                        )}
                      </AnimatePresence>
                    </GlassCard>
                  </motion.div>
                );
              })}
            </motion.div>
          )}
        </>
      )}
    </div>
  );
}

export default SkillsBrowsePage;
