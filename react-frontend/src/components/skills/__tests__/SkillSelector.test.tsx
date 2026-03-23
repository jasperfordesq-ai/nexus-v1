// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for SkillSelector component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
};

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => mockToast),
  useAuth: vi.fn(() => ({
    isAuthenticated: true,
    user: { id: 1, name: 'Test User', role: 'user' },
    login: vi.fn(),
    logout: vi.fn(),
    register: vi.fn(),
    updateUser: vi.fn(),
    refreshUser: vi.fn(),
    status: 'idle',
    error: null,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    branding: { name: 'Test', logo_url: null },
    tenantSlug: 'test',
    tenantPath: (p: string) => '/test' + p,
    isLoading: false,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

import { SkillSelector, SkillChip, ProficiencyDots } from '../SkillSelector';
import type { UserSkill } from '../SkillSelector';

const mockUserSkills: UserSkill[] = [
  {
    id: 1,
    skill_name: 'Gardening',
    category_name: 'Outdoor',
    category_id: 10,
    proficiency_level: 'advanced',
    endorsement_count: 5,
    created_at: '2026-01-01T00:00:00Z',
  },
  {
    id: 2,
    skill_name: 'Cooking',
    category_name: 'Home',
    category_id: 11,
    proficiency_level: 'beginner',
    endorsement_count: 0,
    created_at: '2026-01-02T00:00:00Z',
  },
];

describe('ProficiencyDots', () => {
  it('renders with beginner level', () => {
    render(<ProficiencyDots level="beginner" />);
    expect(screen.getByText('Beginner')).toBeInTheDocument();
  });

  it('renders with intermediate level', () => {
    render(<ProficiencyDots level="intermediate" />);
    expect(screen.getByText('Intermediate')).toBeInTheDocument();
  });

  it('renders with advanced level', () => {
    render(<ProficiencyDots level="advanced" />);
    expect(screen.getByText('Advanced')).toBeInTheDocument();
  });

  it('renders with expert level', () => {
    render(<ProficiencyDots level="expert" />);
    expect(screen.getByText('Expert')).toBeInTheDocument();
  });

  it('falls back to beginner for unknown level', () => {
    render(<ProficiencyDots level="unknown" />);
    expect(screen.getByText('Beginner')).toBeInTheDocument();
  });

  it('has aria-label with proficiency info', () => {
    render(<ProficiencyDots level="expert" />);
    expect(screen.getByLabelText('Proficiency: Expert')).toBeInTheDocument();
  });
});

describe('SkillChip', () => {
  const baseSkill: UserSkill = {
    id: 1,
    skill_name: 'Gardening',
    proficiency_level: 'advanced',
  };

  it('renders skill name', () => {
    render(<SkillChip skill={baseSkill} />);
    expect(screen.getByText('Gardening')).toBeInTheDocument();
  });

  it('renders proficiency dots by default', () => {
    render(<SkillChip skill={baseSkill} />);
    expect(screen.getByText('Advanced')).toBeInTheDocument();
  });

  it('hides proficiency dots when showProficiency is false', () => {
    render(<SkillChip skill={baseSkill} showProficiency={false} />);
    expect(screen.queryByText('Advanced')).not.toBeInTheDocument();
  });

  it('shows endorsement count when provided', () => {
    render(<SkillChip skill={baseSkill} endorsementCount={7} />);
    expect(screen.getByText('7')).toBeInTheDocument();
  });

  it('does not show endorsement count when 0', () => {
    render(<SkillChip skill={baseSkill} endorsementCount={0} />);
    expect(screen.queryByText('0')).not.toBeInTheDocument();
  });

  it('shows remove button when onRemove is provided', () => {
    const onRemove = vi.fn();
    render(<SkillChip skill={baseSkill} onRemove={onRemove} />);
    expect(screen.getByLabelText('Remove Gardening')).toBeInTheDocument();
  });

  it('does not show remove button when onRemove is not provided', () => {
    render(<SkillChip skill={baseSkill} />);
    expect(screen.queryByLabelText('Remove Gardening')).not.toBeInTheDocument();
  });
});

describe('SkillSelector', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Mock categories load
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
  });

  it('renders skill chips for each user skill', async () => {
    const onChange = vi.fn();
    render(<SkillSelector userSkills={mockUserSkills} onSkillsChange={onChange} />);

    await waitFor(() => {
      expect(screen.getByText('Gardening')).toBeInTheDocument();
      expect(screen.getByText('Cooking')).toBeInTheDocument();
    });
  });

  it('renders empty message when no skills', async () => {
    const onChange = vi.fn();
    render(<SkillSelector userSkills={[]} onSkillsChange={onChange} />);

    expect(screen.getByText(/No skills added yet/)).toBeInTheDocument();
  });

  it('renders Add Skill button', async () => {
    const onChange = vi.fn();
    render(<SkillSelector userSkills={[]} onSkillsChange={onChange} />);

    expect(screen.getByText('Add Skill')).toBeInTheDocument();
  });

  it('renders remove buttons for each skill', async () => {
    const onChange = vi.fn();
    render(<SkillSelector userSkills={mockUserSkills} onSkillsChange={onChange} />);

    await waitFor(() => {
      expect(screen.getByLabelText('Remove Gardening')).toBeInTheDocument();
      expect(screen.getByLabelText('Remove Cooking')).toBeInTheDocument();
    });
  });

  it('shows endorsement count on skill chips', async () => {
    const onChange = vi.fn();
    render(<SkillSelector userSkills={mockUserSkills} onSkillsChange={onChange} />);

    await waitFor(() => {
      expect(screen.getByText('5')).toBeInTheDocument();
    });
  });

  it('calls api.delete when remove button is clicked', async () => {
    vi.mocked(api.delete).mockResolvedValueOnce({ success: true });
    const onChange = vi.fn();

    render(<SkillSelector userSkills={mockUserSkills} onSkillsChange={onChange} />);

    await waitFor(() => {
      expect(screen.getByLabelText('Remove Gardening')).toBeInTheDocument();
    });

    const removeBtn = screen.getByLabelText('Remove Gardening');
    removeBtn.click();

    await waitFor(() => {
      expect(api.delete).toHaveBeenCalledWith('/v2/users/me/skills/1');
    });
  });
});
