// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SkillTagsInput - Tag input for listing skill tags
 *
 * Provides autocomplete suggestions and popular tags for quick selection.
 */

import { useState, useCallback, useRef } from 'react';
import { Button, Input, Chip } from '@heroui/react';
import { Tag } from 'lucide-react';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

interface SkillTagsInputProps {
  tags: string[];
  onChange: (tags: string[]) => void;
  maxTags?: number;
}

export function SkillTagsInput({ tags, onChange, maxTags = 10 }: SkillTagsInputProps) {
  const [inputValue, setInputValue] = useState('');
  const [suggestions, setSuggestions] = useState<string[]>([]);
  const [showSuggestions, setShowSuggestions] = useState(false);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const fetchSuggestions = useCallback(async (prefix: string) => {
    if (prefix.length < 2) {
      setSuggestions([]);
      return;
    }

    try {
      const response = await api.get<string[]>(`/v2/listings/tags/autocomplete?q=${encodeURIComponent(prefix)}&limit=8`);
      if (response.success && response.data) {
        // Filter out already-selected tags
        setSuggestions(response.data.filter((s) => !tags.includes(s)));
      }
    } catch (error) {
      logError('Failed to fetch tag suggestions', error);
    }
  }, [tags]);

  const handleInputChange = (value: string) => {
    setInputValue(value);

    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }

    debounceRef.current = setTimeout(() => {
      fetchSuggestions(value);
      setShowSuggestions(true);
    }, 200);
  };

  const addTag = (tag: string) => {
    const normalized = tag.toLowerCase().trim();
    if (normalized && !tags.includes(normalized) && tags.length < maxTags) {
      onChange([...tags, normalized]);
    }
    setInputValue('');
    setSuggestions([]);
    setShowSuggestions(false);
  };

  const removeTag = (tag: string) => {
    onChange(tags.filter((t) => t !== tag));
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      if (inputValue.trim()) {
        addTag(inputValue.trim());
      }
    }
    if (e.key === 'Backspace' && !inputValue && tags.length > 0) {
      removeTag(tags[tags.length - 1]);
    }
  };

  return (
    <div className="space-y-2">
      <label className="text-sm font-medium text-theme-muted flex items-center gap-1">
        <Tag className="w-4 h-4" />
        Skill Tags
        <span className="text-theme-subtle">({tags.length}/{maxTags})</span>
      </label>

      {/* Tag chips */}
      {tags.length > 0 && (
        <div className="flex flex-wrap gap-2">
          {tags.map((tag) => (
            <Chip
              key={tag}
              variant="flat"
              color="primary"
              onClose={() => removeTag(tag)}
              size="sm"
            >
              {tag}
            </Chip>
          ))}
        </div>
      )}

      {/* Input with autocomplete */}
      {tags.length < maxTags && (
        <div className="relative">
          <Input
            size="sm"
            placeholder="Type a skill and press Enter..."
            aria-label="Add skill tag"
            value={inputValue}
            onChange={(e) => handleInputChange(e.target.value)}
            onKeyDown={handleKeyDown}
            onBlur={() => {
              // Delay hiding to allow clicking suggestions
              setTimeout(() => setShowSuggestions(false), 200);
            }}
            onFocus={() => {
              if (suggestions.length > 0) setShowSuggestions(true);
            }}
            classNames={{
              input: 'bg-transparent text-theme-primary',
              inputWrapper: 'bg-theme-elevated border-theme-default',
            }}
          />

          {/* Autocomplete dropdown */}
          {showSuggestions && suggestions.length > 0 && (
            <div className="absolute z-10 w-full mt-1 bg-theme-elevated border border-theme-default rounded-lg shadow-lg overflow-hidden">
              {suggestions.map((suggestion) => (
                <Button
                  key={suggestion}
                  variant="light"
                  className="w-full text-left px-3 py-2 text-sm text-theme-primary hover:bg-theme-hover transition-colors justify-start h-auto rounded-none"
                  onPress={() => addTag(suggestion)}
                  onMouseDown={(e) => {
                    e.preventDefault(); // Prevent input blur
                  }}
                >
                  {suggestion}
                </Button>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

export default SkillTagsInput;
