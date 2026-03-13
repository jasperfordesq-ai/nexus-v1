// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SkillSelector - Autocomplete skill selector with category hierarchy
 *
 * Used in settings/profile to add skills with proficiency levels.
 * Fetches skill categories from API and supports search.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Button,
  Input,
  Select,
  SelectItem,
  Chip,
  Spinner,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
} from '@heroui/react';
import { Search, Plus, X, Star, Sparkles } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export interface SkillCategory {
  id: number;
  name: string;
  slug: string;
  icon?: string;
  skills_count?: number;
  children?: SkillCategory[];
}

export interface UserSkill {
  id: number;
  skill_name: string;
  category_name?: string;
  category_id?: number;
  proficiency_level: 'beginner' | 'intermediate' | 'advanced' | 'expert';
  endorsement_count?: number;
  created_at?: string;
}

interface SkillSearchResult {
  id: number;
  name: string;
  category_name: string;
  category_id: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Proficiency Config
// ─────────────────────────────────────────────────────────────────────────────

const proficiencyConfig: Record<string, { label: string; color: string; dots: number }> = {
  beginner: { label: 'Beginner', color: 'text-emerald-500', dots: 1 },
  intermediate: { label: 'Intermediate', color: 'text-blue-500', dots: 2 },
  advanced: { label: 'Advanced', color: 'text-purple-500', dots: 3 },
  expert: { label: 'Expert', color: 'text-amber-500', dots: 4 },
};

// ─────────────────────────────────────────────────────────────────────────────
// ProficiencyDots
// ─────────────────────────────────────────────────────────────────────────────

export function ProficiencyDots({ level }: { level: string }) {
  const config = proficiencyConfig[level] ?? proficiencyConfig.beginner;
  return (
    <div className="flex items-center gap-1" aria-label={`Proficiency: ${config.label}`}>
      {[1, 2, 3, 4].map((dot) => (
        <div
          key={dot}
          className={`w-2 h-2 rounded-full ${
            dot <= config.dots
              ? `bg-current ${config.color}`
              : 'bg-theme-hover'
          }`}
        />
      ))}
      <span className={`text-xs ml-1 ${config.color}`}>{config.label}</span>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// SkillChip
// ─────────────────────────────────────────────────────────────────────────────

export function SkillChip({
  skill,
  onRemove,
  showProficiency = true,
  endorsementCount,
}: {
  skill: UserSkill;
  onRemove?: () => void;
  showProficiency?: boolean;
  endorsementCount?: number;
}) {
  return (
    <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-indigo-500/10 border border-indigo-500/20">
      <span className="text-sm font-medium text-theme-primary">{skill.skill_name}</span>
      {showProficiency && (
        <ProficiencyDots level={skill.proficiency_level} />
      )}
      {endorsementCount !== undefined && endorsementCount > 0 && (
        <span className="flex items-center gap-0.5 text-xs text-amber-500">
          <Star className="w-3 h-3 fill-amber-500" aria-hidden="true" />
          {endorsementCount}
        </span>
      )}
      {onRemove && (
        <Button
          isIconOnly
          size="sm"
          variant="light"
          onPress={onRemove}
          className="ml-1 p-0.5 rounded-full hover:bg-red-500/20 text-theme-subtle hover:text-red-500 transition-colors min-w-0 w-auto h-auto"
          aria-label={`Remove ${skill.skill_name}`}
        >
          <X className="w-3 h-3" />
        </Button>
      )}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// SkillSelector Component
// ─────────────────────────────────────────────────────────────────────────────

export function SkillSelector({
  userSkills,
  onSkillsChange,
}: {
  userSkills: UserSkill[];
  onSkillsChange: () => void;
}) {
  const toast = useToast();
  const { t } = useTranslation('settings');
  const { isOpen, onOpen, onClose } = useDisclosure();

  const [categories, setCategories] = useState<SkillCategory[]>([]);
  const [searchResults, setSearchResults] = useState<SkillSearchResult[]>([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [isSearching, setIsSearching] = useState(false);
  const [isLoadingCategories, setIsLoadingCategories] = useState(false);
  const [selectedSkill, setSelectedSkill] = useState<string>('');
  const [selectedCategory, setSelectedCategory] = useState<string>('');
  const [proficiency, setProficiency] = useState<string>('intermediate');
  const [isAdding, setIsAdding] = useState(false);

  // Load categories on mount
  useEffect(() => {
    const loadCategories = async () => {
      try {
        setIsLoadingCategories(true);
        const response = await api.get<SkillCategory[]>('/v2/skills/categories');
        if (response.success && response.data) {
          setCategories(response.data);
        }
      } catch (err) {
        logError('Failed to load skill categories', err);
      } finally {
        setIsLoadingCategories(false);
      }
    };
    loadCategories();
  }, []);

  // Search skills
  const handleSearch = useCallback(async (query: string) => {
    setSearchQuery(query);
    if (query.length < 2) {
      setSearchResults([]);
      return;
    }

    try {
      setIsSearching(true);
      const response = await api.get<SkillSearchResult[]>(`/v2/skills/search?q=${encodeURIComponent(query)}`);
      if (response.success && response.data) {
        setSearchResults(response.data);
      }
    } catch (err) {
      logError('Failed to search skills', err);
    } finally {
      setIsSearching(false);
    }
  }, []);

  // Add skill
  const handleAddSkill = async (skillName?: string, categoryId?: number) => {
    const name = skillName || selectedSkill.trim();
    if (!name) return;

    try {
      setIsAdding(true);
      const response = await api.post('/v2/users/me/skills', {
        skill_name: name,
        category_id: categoryId || (selectedCategory ? parseInt(selectedCategory) : undefined),
        proficiency_level: proficiency,
      });

      if (response.success) {
        toast.success(t('toasts.skill_added'));
        setSelectedSkill('');
        setSearchQuery('');
        setSearchResults([]);
        onSkillsChange();
        onClose();
      } else {
        toast.error(response.error || t('toasts.skill_add_failed'));
      }
    } catch (err) {
      logError('Failed to add skill', err);
      toast.error(t('toasts.skill_add_failed'));
    } finally {
      setIsAdding(false);
    }
  };

  // Remove skill
  const handleRemoveSkill = async (skillId: number) => {
    try {
      const response = await api.delete(`/v2/users/me/skills/${skillId}`);
      if (response.success) {
        toast.success(t('toasts.skill_removed'));
        onSkillsChange();
      } else {
        toast.error(response.error || t('toasts.skill_remove_failed'));
      }
    } catch (err) {
      logError('Failed to remove skill', err);
      toast.error(t('toasts.skill_remove_failed'));
    }
  };

  return (
    <div className="space-y-4">
      {/* Current Skills */}
      <div className="flex flex-wrap gap-2">
        {userSkills.length > 0 ? (
          userSkills.map((skill) => (
            <SkillChip
              key={skill.id}
              skill={skill}
              onRemove={() => handleRemoveSkill(skill.id)}
              endorsementCount={skill.endorsement_count}
            />
          ))
        ) : (
          <p className="text-sm text-theme-subtle italic">No skills added yet. Add your first skill below.</p>
        )}
      </div>

      {/* Add Skill Button */}
      <Button
        variant="flat"
        className="bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
        startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
        onPress={onOpen}
      >
        Add Skill
      </Button>

      {/* Add Skill Modal */}
      <Modal
        isOpen={isOpen}
        onClose={onClose}
        size="lg"
        classNames={{
          base: 'bg-[var(--glass-bg)] backdrop-blur-xl border border-[var(--glass-border)]',
          backdrop: 'bg-black/60 backdrop-blur-sm',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500/20 to-purple-500/20 flex items-center justify-center">
                <Sparkles className="w-4 h-4 text-indigo-500" aria-hidden="true" />
              </div>
              Add a Skill
            </div>
          </ModalHeader>
          <ModalBody>
            {/* Search */}
            <Input
              placeholder="Search for a skill..."
              aria-label="Search skills"
              value={searchQuery}
              onChange={(e) => handleSearch(e.target.value)}
              startContent={<Search className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              endContent={isSearching ? <Spinner size="sm" /> : undefined}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
              autoFocus
            />

            {/* Search Results */}
            {searchResults.length > 0 && (
              <div className="space-y-1 max-h-48 overflow-y-auto">
                {searchResults.map((result) => (
                  <Button
                    key={result.id}
                    variant="light"
                    onPress={() => {
                      setSelectedSkill(result.name);
                      setSelectedCategory(result.category_id.toString());
                      setSearchResults([]);
                      setSearchQuery(result.name);
                    }}
                    className="w-full text-left px-3 py-2 rounded-lg hover:bg-theme-hover transition-colors justify-start h-auto"
                  >
                    <span className="text-sm font-medium text-theme-primary">{result.name}</span>
                    <span className="text-xs text-theme-subtle ml-2">in {result.category_name}</span>
                  </Button>
                ))}
              </div>
            )}

            {/* Manual entry if no search results */}
            {searchQuery.length >= 2 && searchResults.length === 0 && !isSearching && (
              <div className="px-3 py-2 rounded-lg bg-theme-elevated border border-theme-default">
                <p className="text-sm text-theme-muted">
                  No matching skill found. You can add &quot;{searchQuery}&quot; as a custom skill.
                </p>
                <Button
                  size="sm"
                  variant="flat"
                  className="mt-2 bg-indigo-500/10 text-indigo-500"
                  onPress={() => setSelectedSkill(searchQuery)}
                >
                  Use &quot;{searchQuery}&quot;
                </Button>
              </div>
            )}

            {/* Category selector */}
            {!isLoadingCategories && categories.length > 0 && (
              <Select
                label="Category (optional)"
                placeholder="Select a category"
                selectedKeys={selectedCategory ? [selectedCategory] : []}
                onChange={(e) => setSelectedCategory(e.target.value)}
                classNames={{
                  trigger: 'bg-theme-elevated border-theme-default',
                  value: 'text-theme-primary',
                }}
              >
                {categories.map((cat) => (
                  <SelectItem key={cat.id.toString()}>
                    {cat.icon ? `${cat.icon} ${cat.name}` : cat.name}
                  </SelectItem>
                ))}
              </Select>
            )}

            {/* Proficiency selector */}
            <Select
              label="Proficiency Level"
              selectedKeys={[proficiency]}
              onChange={(e) => setProficiency(e.target.value)}
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default',
                value: 'text-theme-primary',
              }}
            >
              <SelectItem key="beginner">Beginner</SelectItem>
              <SelectItem key="intermediate">Intermediate</SelectItem>
              <SelectItem key="advanced">Advanced</SelectItem>
              <SelectItem key="expert">Expert</SelectItem>
            </Select>

            {/* Selected skill preview */}
            {(selectedSkill || searchQuery) && (
              <div className="p-3 rounded-lg bg-theme-elevated border border-theme-default">
                <p className="text-xs text-theme-subtle mb-1">Skill to add:</p>
                <div className="flex items-center gap-3">
                  <Chip variant="flat" className="bg-indigo-500/20 text-indigo-600 dark:text-indigo-300">
                    {selectedSkill || searchQuery}
                  </Chip>
                  <ProficiencyDots level={proficiency} />
                </div>
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose} className="text-theme-muted">
              Cancel
            </Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={() => handleAddSkill()}
              isLoading={isAdding}
              isDisabled={!selectedSkill && !searchQuery}
            >
              Add Skill
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default SkillSelector;
