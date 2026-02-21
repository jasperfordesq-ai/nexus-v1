// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GroupSelector — optional audience/group dropdown for posts, polls, and events.
 */

import { useState, useEffect } from 'react';
import { Select, SelectItem } from '@heroui/react';
import { Users } from 'lucide-react';
import { api } from '@/lib/api';
import { useAuth } from '@/contexts';
import { logError } from '@/lib/logger';

interface Group {
  id: number;
  name: string;
  member_count?: number;
}

interface GroupSelectorProps {
  value: number | null;
  onChange: (groupId: number | null) => void;
}

export function GroupSelector({ value, onChange }: GroupSelectorProps) {
  const { user } = useAuth();
  const [groups, setGroups] = useState<Group[]>([]);
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    if (!user?.id) return;
    let cancelled = false;

    async function loadGroups() {
      setIsLoading(true);
      try {
        const res = await api.get<Group[]>(`/v2/groups?user_id=${user!.id}&limit=50`);
        if (!cancelled && res.success && res.data) {
          setGroups(Array.isArray(res.data) ? res.data : []);
        }
      } catch (err) {
        logError('Failed to load groups for selector', err);
      } finally {
        if (!cancelled) setIsLoading(false);
      }
    }

    loadGroups();
    return () => { cancelled = true; };
  }, [user?.id]);

  if (groups.length === 0 && !isLoading) return null;

  return (
    <Select
      label="Post to"
      placeholder="Public Feed"
      selectedKeys={value ? [String(value)] : []}
      onSelectionChange={(keys) => {
        const selected = Array.from(keys)[0];
        onChange(selected ? Number(selected) : null);
      }}
      startContent={<Users className="w-4 h-4 text-[var(--text-muted)]" />}
      classNames={{
        trigger: 'bg-[var(--surface-elevated)] border-[var(--border-default)] min-h-11',
        value: 'text-[var(--text-primary)]',
      }}
      isLoading={isLoading}
    >
      {groups.map((g) => (
        <SelectItem key={String(g.id)} textValue={g.name}>
          <div className="flex items-center gap-2">
            <span className="text-sm">{g.name}</span>
            {g.member_count != null && (
              <span className="text-xs text-[var(--text-subtle)]">
                {g.member_count} members
              </span>
            )}
          </div>
        </SelectItem>
      ))}
    </Select>
  );
}
