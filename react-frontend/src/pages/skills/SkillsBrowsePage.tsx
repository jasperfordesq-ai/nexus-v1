// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Skills Browse Page - Shows the full skill category tree
 *
 * Displays all available skill categories with counts and
 * allows drilling into categories to see skilled members.
 */

import { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { Button, Input, Chip, Spinner } from '@heroui/react';
import {
  Sparkles,
  Search,
  ChevronRight,
  Users,
  RefreshCw,
  AlertTriangle,
  FolderTree,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { SkillCategory } from '@/components/skills/SkillSelector';

export function SkillsBrowsePage() {
  usePageTitle('Browse Skills');

  const [categories, setCategories] = useState<SkillCategory[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [expandedCategories, setExpandedCategories] = useState<Set<number>>(new Set());

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

  const toggleCategory = (id: number) => {
    setExpandedCategories((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  // Filter categories by search
  const filteredCategories = searchQuery
    ? categories.filter(
        (cat) =>
          cat.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
          cat.children?.some((child) =>
            child.name.toLowerCase().includes(searchQuery.toLowerCase())
          )
      )
    : categories;

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.05 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
          <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
            <Sparkles className="w-5 h-5 text-white" aria-hidden="true" />
          </div>
          Browse Skills
        </h1>
        <p className="text-theme-muted mt-1 text-sm">
          Explore skill categories and find members with the expertise you need.
        </p>
      </div>

      {/* Search */}
      <GlassCard className="p-4">
        <Input
          placeholder="Search skill categories..."
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          startContent={<Search className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
          classNames={{
            input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
            inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
          }}
          aria-label="Search skill categories"
        />
      </GlassCard>

      {/* Error State */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h3 className="text-lg font-semibold text-theme-primary mb-2">Unable to load</h3>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={loadCategories}
          >
            Try Again
          </Button>
        </GlassCard>
      )}

      {/* Loading */}
      {isLoading && (
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      )}

      {/* Categories Tree */}
      {!isLoading && !error && (
        <>
          {filteredCategories.length === 0 ? (
            <EmptyState
              icon={<FolderTree className="w-12 h-12" aria-hidden="true" />}
              title="No categories found"
              description={searchQuery ? `No categories match "${searchQuery}"` : 'No skill categories available yet.'}
            />
          ) : (
            <motion.div
              variants={containerVariants}
              initial="hidden"
              animate="visible"
              className="space-y-3"
            >
              {filteredCategories.map((category) => (
                <motion.div key={category.id} variants={itemVariants}>
                  <GlassCard className="overflow-hidden">
                    {/* Category Header */}
                    <button
                      onClick={() => toggleCategory(category.id)}
                      className="w-full flex items-center justify-between p-4 hover:bg-theme-hover transition-colors cursor-pointer"
                    >
                      <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 flex items-center justify-center">
                          <span className="text-lg">{category.icon || '📂'}</span>
                        </div>
                        <div className="text-left">
                          <h3 className="font-semibold text-theme-primary">{category.name}</h3>
                          {category.skills_count !== undefined && (
                            <p className="text-xs text-theme-subtle">
                              {category.skills_count} skill{category.skills_count !== 1 ? 's' : ''}
                            </p>
                          )}
                        </div>
                      </div>
                      <div className="flex items-center gap-2">
                        {category.children && category.children.length > 0 && (
                          <Chip size="sm" variant="flat" className="bg-theme-elevated text-theme-muted">
                            {category.children.length} sub-categories
                          </Chip>
                        )}
                        <ChevronRight
                          className={`w-5 h-5 text-theme-subtle transition-transform ${
                            expandedCategories.has(category.id) ? 'rotate-90' : ''
                          }`}
                          aria-hidden="true"
                        />
                      </div>
                    </button>

                    {/* Sub-categories */}
                    {expandedCategories.has(category.id) && category.children && category.children.length > 0 && (
                      <div className="px-4 pb-4 pt-0 border-t border-theme-default">
                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-2 pt-3">
                          {category.children.map((child) => (
                            <div
                              key={child.id}
                              className="flex items-center gap-2 px-3 py-2 rounded-lg bg-theme-elevated border border-theme-default hover:border-indigo-500/30 transition-colors"
                            >
                              <span className="text-sm">{child.icon || '🏷️'}</span>
                              <div className="min-w-0">
                                <span className="text-sm font-medium text-theme-primary truncate block">
                                  {child.name}
                                </span>
                                {child.skills_count !== undefined && (
                                  <span className="text-xs text-theme-subtle flex items-center gap-1">
                                    <Users className="w-3 h-3" aria-hidden="true" />
                                    {child.skills_count}
                                  </span>
                                )}
                              </div>
                            </div>
                          ))}
                        </div>
                      </div>
                    )}
                  </GlassCard>
                </motion.div>
              ))}
            </motion.div>
          )}
        </>
      )}
    </div>
  );
}

export default SkillsBrowsePage;
