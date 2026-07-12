// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';

const apiMock = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  download: vi.fn(),
}));

vi.mock('@/lib/api', () => ({ api: apiMock }));

import { eventRegistrationApi, type RegistrationQuestion } from './event-registration-api';

const question: RegistrationQuestion = {
  id: 91,
  position: 2,
  stable_key: 'access_needs',
  question_type: 'accessibility',
  prompt: 'What support would help?',
  help_text: null,
  is_required: false,
  data_classification: 'sensitive',
  purpose: 'Prepare reasonable adjustments',
  retention_days: 30,
  choice_options: null,
  validation_rules: { max_length: 500 },
  visibility_rules: null,
  displayed_text: null,
  displayed_text_version: null,
};

describe('eventRegistrationApi', () => {
  beforeEach(() => vi.clearAllMocks());

  it('strips persistence-only question fields before updating a draft', async () => {
    apiMock.put.mockResolvedValue({
      success: true,
      data: { form: { id: 11 }, changed: true, idempotent_replay: false },
    });

    const response = await eventRegistrationApi.updateForm(42, 11, {
      name: 'Requirements',
      description: 'Safe planning',
      questions: [question],
      expected_form_revision: 3,
      expected_settings_revision: 7,
    });

    expect(response.success).toBe(true);
    expect(apiMock.put).toHaveBeenCalledWith(
      '/v2/events/42/registration-product/forms/11',
      expect.objectContaining({
        questions: [expect.not.objectContaining({ id: 91, position: 2 })],
        idempotency_key: expect.any(String),
      }),
    );
  });

  it('sends optimistic registration policy mutations to the dedicated settings endpoints', async () => {
    apiMock.put.mockResolvedValue({
      success: true,
      data: { settings: { id: 5 }, changed: true },
    });
    apiMock.post.mockResolvedValue({
      success: true,
      data: { settings: { id: 5 }, changed: true },
    });

    await eventRegistrationApi.saveSettings(42, {
      approval_mode: 'manual',
      opens_at_utc: null,
      closes_at_utc: null,
      cancellation_cutoff_at_utc: null,
      per_member_limit: 2,
      guests_enabled: true,
      max_guests_per_registration: 2,
      guest_retention_days: 30,
      expected_revision: 7,
    });
    await eventRegistrationApi.publishSettings(42, 8);

    expect(apiMock.put).toHaveBeenCalledWith(
      '/v2/events/42/registration-product/settings',
      expect.objectContaining({ expected_revision: 7, idempotency_key: expect.any(String) }),
    );
    expect(apiMock.post).toHaveBeenCalledWith(
      '/v2/events/42/registration-product/settings/publish',
      expect.objectContaining({ expected_revision: 8, idempotency_key: expect.any(String) }),
    );
  });

  it('fails closed when a nominally successful mutation omits its contracted entity', async () => {
    apiMock.post.mockResolvedValue({ success: true, data: { changed: true } });

    const response = await eventRegistrationApi.previewCampaign(42, 'member', { member_ids: [7] }, 'en');

    expect(response.success).toBe(false);
    expect(response.data).toBeUndefined();
  });

  it('records purpose evidence in the answer-export request', async () => {
    apiMock.download.mockResolvedValue(new Blob());

    await eventRegistrationApi.exportAnswers(42, 'Catering safety review', 'case-123', false);

    expect(apiMock.download).toHaveBeenCalledWith(
      '/v2/events/42/registration-product/submissions/export',
      {
        method: 'POST',
        body: {
          purpose: 'Catering safety review',
          correlation_id: 'case-123',
          include_sensitive: false,
        },
        filename: 'event-registration-42.csv',
      },
    );
  });
});
