// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import userEvent from '@testing-library/user-event';
import { render, screen, waitFor } from '@/test/test-utils';
import type { ReactNode } from 'react';
import { JobPipelineRules } from './JobPipelineRules';
import { api } from '@/lib/api';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      if (key === 'pipeline.rule_summary_with_target') {
        return `${opts?.stage} ${opts?.action} ${opts?.target}`;
      }
      if (key === 'pipeline.rule_summary') {
        return `${opts?.stage} ${opts?.action}`;
      }
      if (key === 'pipeline.generated_name') {
        return `${opts?.stage} to ${opts?.target} after ${opts?.count}d`;
      }
      return key;
    },
  }),
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: ReactNode; className?: string }) => (
    <div className={className}>{children}</div>
  ),
}));

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

describe('JobPipelineRules', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders rules from the unwrapped API response', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [
        {
          id: 7,
          name: 'Move stale applications',
          trigger_stage: 'applied',
          condition_days: 5,
          action: 'move_stage',
          action_target: 'screening',
          is_active: true,
          last_run_at: null,
        },
      ],
    });

    render(<JobPipelineRules jobId="42" />);

    await userEvent.click(screen.getByRole('button', { name: /pipeline.title/i }));

    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/jobs/42/pipeline-rules');
      expect(screen.getByText('Move stale applications')).toBeInTheDocument();
    });
  });
});
