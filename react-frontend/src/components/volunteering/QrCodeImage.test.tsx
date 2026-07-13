// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

const toDataURL = vi.fn();
vi.mock('qrcode', () => ({ toDataURL: (...args: unknown[]) => toDataURL(...args) }));

import { QrCodeImage } from './QrCodeImage';

describe('QrCodeImage', () => {
  beforeEach(() => {
    toDataURL.mockReset();
  });

  it('renders an <img> once the QR generates', async () => {
    toDataURL.mockResolvedValue('data:image/png;base64,AAA');

    render(<QrCodeImage value="tok" alt="Check-in QR" />);

    await waitFor(() => {
      const img = screen.getByRole('img', { name: 'Check-in QR' });
      expect(img.tagName).toBe('IMG');
      expect(img).toHaveAttribute('src', 'data:image/png;base64,AAA');
    });
  });

  it('falls back to a usable link when the qrcode chunk fails to load', async () => {
    toDataURL.mockRejectedValue(new Error('chunk 404'));

    render(<QrCodeImage value="https://example.test/checkin/tok" alt="Check-in QR" />);

    const link = await screen.findByRole('link', { name: 'Check-in QR' });
    expect(link).toHaveAttribute('href', 'https://example.test/checkin/tok');
    // The perpetual aria-busy placeholder must be gone.
    expect(document.querySelector('[aria-busy="true"]')).toBeNull();
  });

  it('does not turn an opaque token into a link when link fallback is disabled', async () => {
    toDataURL.mockRejectedValue(new Error('chunk 404'));

    render(<QrCodeImage value="private-redemption-token" alt="Coupon QR" fallbackToLink={false} />);

    const fallback = await screen.findByRole('img', { name: 'Coupon QR' });
    expect(fallback).toHaveTextContent('Coupon QR');
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
  });
});
