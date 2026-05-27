import React from 'react';
import { useTranslation } from 'react-i18next';
import { SDG_GOALS } from '@/data/sdg-goals';
import { Chip } from '@/components/ui';

interface SdgGoalsPickerProps {
  selected: number[];
  onChange: (selected: number[]) => void;
}

export function SdgGoalsPicker({ selected, onChange }: SdgGoalsPickerProps) {
  const { t } = useTranslation('feed');

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
        {t('compose.sdg_label')}
      </label>
      <div className="flex flex-wrap gap-1.5 max-h-28 sm:max-h-none overflow-y-auto sm:overflow-visible scrollbar-hide">
        {SDG_GOALS.map((goal) => {
          const isActive = selected.includes(goal.id);
          return (
            <Chip
              key={goal.id}
              size="sm"
              variant={isActive ? 'primary' : 'secondary'}
              className={`cursor-pointer transition-all text-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface-base)] ${
                isActive
                  ? 'text-white shadow-sm'
                  : 'bg-[var(--surface-elevated)] text-[var(--text-muted)] hover:bg-[var(--surface-hover)]'
              }`}
              style={isActive ? { backgroundColor: goal.color } : undefined}
              role="button"
              tabIndex={0}
              aria-pressed={isActive}
              onClick={() => toggle(goal.id)}
              onKeyDown={(e: React.KeyboardEvent<HTMLElement>) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(goal.id); } }}
            >
              <span className="mr-0.5">{goal.icon}</span>
              {goal.label}
            </Chip>
          );
        })}
      </div>
      {selected.length > 0 && (
        <p className="text-xs text-[var(--text-subtle)]">
          {t('compose.sdg_selected', { count: selected.length })}
        </p>
      )}
    </div>
  );
}
