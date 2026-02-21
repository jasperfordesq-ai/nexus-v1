// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SdgGoalsPicker — toggleable chips for the 17 UN Sustainable Development Goals.
 */

import { Chip } from '@heroui/react';
import { SDG_GOALS } from '@/data/sdg-goals';

interface SdgGoalsPickerProps {
  selected: number[];
  onChange: (selected: number[]) => void;
}

export function SdgGoalsPicker({ selected, onChange }: SdgGoalsPickerProps) {
  const toggle = (id: number) => {
    if (selected.includes(id)) {
      onChange(selected.filter((s) => s !== id));
    } else {
      onChange([...selected, id]);
    }
  };

  return (
    <div className="space-y-2">
      <label className="text-xs font-medium text-[var(--text-muted)] uppercase tracking-wider">
        UN Sustainable Development Goals (optional)
      </label>
      <div className="flex flex-wrap gap-1.5 max-h-28 sm:max-h-none overflow-y-auto sm:overflow-visible scrollbar-hide">
        {SDG_GOALS.map((goal) => {
          const isActive = selected.includes(goal.id);
          return (
            <Chip
              key={goal.id}
              size="sm"
              variant={isActive ? 'solid' : 'flat'}
              className={`cursor-pointer transition-all text-xs ${
                isActive
                  ? 'text-white shadow-sm'
                  : 'bg-[var(--surface-elevated)] text-[var(--text-muted)] hover:bg-[var(--surface-hover)]'
              }`}
              style={isActive ? { backgroundColor: goal.color } : undefined}
              onClick={() => toggle(goal.id)}
            >
              <span className="mr-0.5">{goal.icon}</span>
              {goal.label}
            </Chip>
          );
        })}
      </div>
      {selected.length > 0 && (
        <p className="text-xs text-[var(--text-subtle)]">
          {selected.length} goal{selected.length !== 1 ? 's' : ''} selected
        </p>
      )}
    </div>
  );
}
