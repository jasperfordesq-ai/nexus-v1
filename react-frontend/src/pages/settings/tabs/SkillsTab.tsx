// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback } from 'react';
import { Spinner } from '@heroui/react';
import { GlassCard } from '@/components/ui';
import { SkillSelector } from '@/components/skills/SkillSelector';
import type { UserSkill } from '@/components/skills/SkillSelector';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function SkillsTab() {
  const [userSkills, setUserSkills] = useState<UserSkill[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  const loadSkills = useCallback(async () => {
    try {
      setIsLoading(true);
      const response = await api.get<UserSkill[]>('/v2/users/me/skills');
      if (response.success && response.data) {
        setUserSkills(response.data);
      }
    } catch (err) {
      logError('Failed to load user skills', err);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadSkills();
  }, [loadSkills]);

  return (
    <div className="space-y-6">
      <GlassCard className="p-6">
        <h2 className="text-lg font-semibold text-theme-primary mb-2">Your Skills</h2>
        <p className="text-sm text-theme-muted mb-6">
          Add skills to your profile so other members can find you. Community members can endorse your skills.
        </p>
        {isLoading ? (
          <div className="flex justify-center py-8">
            <Spinner size="lg" />
          </div>
        ) : (
          <SkillSelector userSkills={userSkills} onSkillsChange={loadSkills} />
        )}
      </GlassCard>
    </div>
  );
}
