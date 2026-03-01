// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AdvancedSearchFilters - Collapsible panel with advanced search filter controls
 *
 * Supports:
 * - Date range filtering
 * - Category selection
 * - Skills/tags filtering
 * - Sort order
 * - Content type selection
 */

import { useState, useEffect, useCallback } from 'react';
import { Button, Input, Select, SelectItem, Chip } from '@heroui/react';
import { Filter, X, Calendar, Tag, SlidersHorizontal } from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { Category } from '@/types/api';

export interface SearchFilters {
  type: string;
  category_id: string;
  date_from: string;
  date_to: string;
  sort: string;
  skills: string;
  location: string;
}

interface AdvancedSearchFiltersProps {
  filters: SearchFilters;
  onChange: (filters: SearchFilters) => void;
  onApply: () => void;
  onReset: () => void;
}

const defaultFilters: SearchFilters = {
  type: 'all',
  category_id: '',
  date_from: '',
  date_to: '',
  sort: 'relevance',
  skills: '',
  location: '',
};

export function AdvancedSearchFilters({
  filters,
  onChange,
  onApply,
  onReset,
}: AdvancedSearchFiltersProps) {
  const [isExpanded, setIsExpanded] = useState(false);
  const [categories, setCategories] = useState<Category[]>([]);
  const [popularTags, setPopularTags] = useState<string[]>([]);
  const [tagInput, setTagInput] = useState('');

  // Count active filters
  const activeFilterCount = Object.entries(filters).filter(
    ([key, value]) => value && value !== defaultFilters[key as keyof SearchFilters]
  ).length;

  const loadCategories = useCallback(async () => {
    try {
      const response = await api.get<Category[]>('/v2/categories');
      if (response.success && response.data) {
        setCategories(response.data);
      }
    } catch (error) {
      logError('Failed to load categories', error);
    }
  }, []);

  const loadPopularTags = useCallback(async () => {
    try {
      const response = await api.get<Array<{ tag: string; count: number }>>('/v2/listings/tags/popular?limit=10');
      if (response.success && response.data) {
        setPopularTags(response.data.map((t) => t.tag));
      }
    } catch (error) {
      logError('Failed to load popular tags', error);
    }
  }, []);

  useEffect(() => {
    if (isExpanded) {
      loadCategories();
      loadPopularTags();
    }
  }, [isExpanded, loadCategories, loadPopularTags]);

  const updateFilter = (key: keyof SearchFilters, value: string) => {
    onChange({ ...filters, [key]: value });
  };

  const handleAddSkill = (skill: string) => {
    const currentSkills = filters.skills ? filters.skills.split(',').filter(Boolean) : [];
    if (!currentSkills.includes(skill.toLowerCase())) {
      const newSkills = [...currentSkills, skill.toLowerCase()].join(',');
      updateFilter('skills', newSkills);
    }
    setTagInput('');
  };

  const handleRemoveSkill = (skill: string) => {
    const currentSkills = filters.skills ? filters.skills.split(',').filter(Boolean) : [];
    const newSkills = currentSkills.filter((s) => s !== skill).join(',');
    updateFilter('skills', newSkills);
  };

  const skillsList = filters.skills ? filters.skills.split(',').filter(Boolean) : [];

  const handleReset = () => {
    onChange({ ...defaultFilters });
    onReset();
  };

  return (
    <div className="space-y-3">
      {/* Toggle button */}
      <Button
        variant="flat"
        size="sm"
        className="bg-theme-elevated text-theme-primary"
        startContent={<SlidersHorizontal className="w-4 h-4" />}
        endContent={
          activeFilterCount > 0 ? (
            <Chip size="sm" color="primary" variant="flat">
              {activeFilterCount}
            </Chip>
          ) : null
        }
        onPress={() => setIsExpanded(!isExpanded)}
      >
        Advanced Filters
      </Button>

      {/* Expanded filter panel */}
      {isExpanded && (
        <GlassCard className="p-4 space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {/* Content type */}
            <Select
              label="Content Type"
              selectedKeys={filters.type ? [filters.type] : ['all']}
              onChange={(e) => updateFilter('type', e.target.value)}
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default',
                value: 'text-theme-primary',
              }}
            >
              <SelectItem key="all">All Types</SelectItem>
              <SelectItem key="listings">Listings</SelectItem>
              <SelectItem key="users">Members</SelectItem>
              <SelectItem key="events">Events</SelectItem>
              <SelectItem key="groups">Groups</SelectItem>
            </Select>

            {/* Category */}
            <Select
              label="Category"
              selectedKeys={filters.category_id ? [filters.category_id] : []}
              onChange={(e) => updateFilter('category_id', e.target.value)}
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default',
                value: 'text-theme-primary',
              }}
              items={[{ id: 0, name: 'All Categories', slug: '' }, ...categories]}
            >
              {(cat) => <SelectItem key={cat.id || ''}>{cat.name}</SelectItem>}
            </Select>

            {/* Sort */}
            <Select
              label="Sort By"
              selectedKeys={filters.sort ? [filters.sort] : ['relevance']}
              onChange={(e) => updateFilter('sort', e.target.value)}
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default',
                value: 'text-theme-primary',
              }}
            >
              <SelectItem key="relevance">Relevance</SelectItem>
              <SelectItem key="newest">Newest First</SelectItem>
              <SelectItem key="oldest">Oldest First</SelectItem>
            </Select>

            {/* Date from */}
            <Input
              type="date"
              label="From Date"
              value={filters.date_from}
              onChange={(e) => updateFilter('date_from', e.target.value)}
              startContent={<Calendar className="w-4 h-4 text-theme-subtle" />}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />

            {/* Date to */}
            <Input
              type="date"
              label="To Date"
              value={filters.date_to}
              onChange={(e) => updateFilter('date_to', e.target.value)}
              startContent={<Calendar className="w-4 h-4 text-theme-subtle" />}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />

            {/* Location */}
            <Input
              label="Location"
              placeholder="e.g. London, Bristol..."
              value={filters.location}
              onChange={(e) => updateFilter('location', e.target.value)}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
          </div>

          {/* Skill tags */}
          <div>
            <label className="text-sm font-medium text-theme-muted mb-2 flex items-center gap-1">
              <Tag className="w-4 h-4" />
              Skill Tags
            </label>
            <div className="flex gap-2 mb-2">
              <Input
                size="sm"
                placeholder="Add a skill tag..."
                value={tagInput}
                onChange={(e) => setTagInput(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter' && tagInput.trim()) {
                    handleAddSkill(tagInput.trim());
                  }
                }}
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                }}
              />
            </div>

            {/* Active skill tags */}
            {skillsList.length > 0 && (
              <div className="flex flex-wrap gap-2 mb-2">
                {skillsList.map((skill) => (
                  <Chip
                    key={skill}
                    variant="flat"
                    color="primary"
                    onClose={() => handleRemoveSkill(skill)}
                    size="sm"
                  >
                    {skill}
                  </Chip>
                ))}
              </div>
            )}

            {/* Popular tags */}
            {popularTags.length > 0 && (
              <div className="flex flex-wrap gap-1">
                <span className="text-xs text-theme-subtle">Popular:</span>
                {popularTags
                  .filter((t) => !skillsList.includes(t))
                  .slice(0, 8)
                  .map((tag) => (
                    <button
                      key={tag}
                      type="button"
                      onClick={() => handleAddSkill(tag)}
                      className="text-xs px-2 py-0.5 rounded-full bg-theme-hover text-theme-muted
                                 hover:bg-primary/20 hover:text-primary transition-colors"
                    >
                      {tag}
                    </button>
                  ))}
              </div>
            )}
          </div>

          {/* Actions */}
          <div className="flex justify-end gap-2 pt-2 border-t border-theme-default">
            <Button
              size="sm"
              variant="light"
              startContent={<X className="w-4 h-4" />}
              onPress={handleReset}
            >
              Reset
            </Button>
            <Button
              size="sm"
              color="primary"
              startContent={<Filter className="w-4 h-4" />}
              onPress={onApply}
            >
              Apply Filters
            </Button>
          </div>
        </GlassCard>
      )}
    </div>
  );
}

export { defaultFilters };
export default AdvancedSearchFilters;
