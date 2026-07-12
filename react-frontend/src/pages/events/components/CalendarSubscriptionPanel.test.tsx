// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type {
  ButtonHTMLAttributes,
  InputHTMLAttributes,
  ReactNode,
} from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { CalendarSubscriptionPanel } from './CalendarSubscriptionPanel';

const {
  copyMock,
  confirmMock,
  createMock,
  downloadMock,
  listMock,
  revokeMock,
  toastErrorMock,
  toastSuccessMock,
} = vi.hoisted(() => ({
  copyMock: vi.fn(),
  confirmMock: vi.fn(),
  createMock: vi.fn(),
  downloadMock: vi.fn(),
  listMock: vi.fn(),
  revokeMock: vi.fn(),
  toastErrorMock: vi.fn(),
  toastSuccessMock: vi.fn(),
}));

vi.mock('@/lib/events-api', () => ({
  eventsApi: {
    calendarFeedTokens: listMock,
    createCalendarFeedToken: createMock,
    revokeCalendarFeedToken: revokeMock,
    downloadTenantCalendar: downloadMock,
  },
}));

vi.mock('@/contexts/ToastContext', () => ({
  useToast: () => ({ success: toastSuccessMock, error: toastErrorMock }),
}));

vi.mock('@/components/ui/ConfirmDialog', () => ({
  useConfirm: () => confirmMock,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({ formatDateTime: () => '11 July 2026, 10:00' }));
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, values?: Record<string, unknown>) => (
      key === 'calendar_subscriptions.revoke_confirm_identity'
        ? `${key}:${String(values?.label)}:${String(values?.prefix)}`
        : key
    ),
  }),
  initReactI18next: { type: '3rdParty', init: () => undefined },
}));

vi.mock('@/components/ui/Button', () => ({
  Button: ({
    children,
    onPress,
    startContent,
    endContent,
    isLoading,
    isDisabled,
    color: _color,
    variant: _variant,
    size: _size,
    ...props
  }: {
    children?: ReactNode;
    onPress?: () => void;
    startContent?: ReactNode;
    endContent?: ReactNode;
    isLoading?: boolean;
    isDisabled?: boolean;
    color?: string;
    variant?: string;
    size?: string;
  } & Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'onClick'>) => (
    <button type="button" onClick={onPress} disabled={isLoading || isDisabled} {...props}>
      {startContent}{children}{endContent}
    </button>
  ),
}));

vi.mock('@/components/ui/Input', () => ({
  Input: ({
    label,
    value,
    onValueChange,
    className: _className,
    ...props
  }: {
    label: string;
    value: string;
    onValueChange: (value: string) => void;
  } & Omit<InputHTMLAttributes<HTMLInputElement>, 'onChange'>) => (
    <label>
      {label}
      <input
        value={value}
        onChange={(event) => onValueChange(event.currentTarget.value)}
        {...props}
      />
    </label>
  ),
}));

vi.mock('@/components/ui/Chip', () => ({
  Chip: ({ children }: { children?: ReactNode }) => <span>{children}</span>,
}));

vi.mock('@/components/ui/Modal', () => ({
  Modal: ({ isOpen, children }: { isOpen: boolean; children?: ReactNode }) => (
    isOpen ? <div role="dialog">{children}</div> : null
  ),
  ModalContent: ({ children }: { children: ReactNode | ((close: () => void) => ReactNode) }) => (
    <div>{typeof children === 'function' ? children(() => undefined) : children}</div>
  ),
  ModalHeader: ({ children }: { children?: ReactNode }) => <header>{children}</header>,
  ModalBody: ({ children }: { children?: ReactNode }) => <div>{children}</div>,
  ModalFooter: ({ children }: { children?: ReactNode }) => <footer>{children}</footer>,
}));

const existingToken = {
  id: 41,
  label: 'Desktop calendar',
  token_prefix: 'nxc_existing',
  locale: 'en',
  created_at: '2026-07-11T10:00:00+00:00',
  last_used_at: null,
  revoked_at: null,
  active: true,
};

const createdToken = {
  id: 42,
  label: 'Phone',
  token_prefix: 'nxc_created',
  locale: 'en',
  created_at: '2026-07-11T10:05:00+00:00',
  last_used_at: null,
  revoked_at: null,
  active: true,
  secret: 'nxc_created-secret-once',
  feed_url: 'https://api.example.test/api/v2/events/calendar/personal/hour-timebank/nxc_created-secret-once.ics',
};

describe('CalendarSubscriptionPanel', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    listMock.mockResolvedValue({ success: true, data: [existingToken] });
    createMock.mockResolvedValue({ success: true, data: createdToken });
    revokeMock.mockResolvedValue({ success: true, data: { revoked: true } });
    confirmMock.mockResolvedValue(true);
    downloadMock.mockResolvedValue(new Blob());
    copyMock.mockRejectedValue(new Error('clipboard unavailable'));
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: { writeText: copyMock },
    });
  });

  it('shows the secret URL only after creation, handles copy failure, revokes, and clears it on open', async () => {
    const { container } = render(<CalendarSubscriptionPanel />);
    const openButton = screen.getByRole('button', { name: 'calendar_subscriptions.button' });
    fireEvent.click(openButton);

    expect(await screen.findByText('Desktop calendar')).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText('calendar_subscriptions.label'), {
      target: { value: 'Phone' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'calendar_subscriptions.create' }));

    expect(await screen.findByText(createdToken.feed_url)).toBeInTheDocument();
    expect(createMock).toHaveBeenCalledWith('Phone');
    expect(container.querySelector(`a[href="${createdToken.feed_url}"]`)).toBeNull();

    fireEvent.click(screen.getByRole('button', { name: 'calendar_subscriptions.copy' }));
    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith(
      'calendar_subscriptions.copy_error',
    ));
    expect(copyMock).toHaveBeenCalledWith(createdToken.feed_url);

    fireEvent.click(screen.getAllByRole('button', { name: 'calendar_subscriptions.revoke' })[0]);
    await waitFor(() => expect(revokeMock).toHaveBeenCalledWith(createdToken.id));
    expect(await screen.findByText('calendar_subscriptions.inactive')).toBeInTheDocument();

    fireEvent.click(openButton);
    await waitFor(() => expect(screen.queryByText(createdToken.feed_url)).not.toBeInTheDocument());
  });

  it('requires translated confirmation, exposes token identity, and keeps focus after cancellation', async () => {
    confirmMock.mockResolvedValueOnce(false);
    render(<CalendarSubscriptionPanel />);
    fireEvent.click(screen.getByRole('button', { name: 'calendar_subscriptions.button' }));

    const revokeButton = await screen.findByRole('button', { name: 'calendar_subscriptions.revoke' });
    revokeButton.focus();
    fireEvent.click(revokeButton);
    await waitFor(() => expect(confirmMock).toHaveBeenCalledTimes(1));
    expect(revokeMock).not.toHaveBeenCalled();
    await waitFor(() => expect(revokeButton).toHaveFocus());

    const options = confirmMock.mock.calls[0][0];
    expect(options).toMatchObject({
      title: 'calendar_subscriptions.revoke_confirm_title',
      status: 'danger',
      confirmLabel: 'calendar_subscriptions.revoke',
      cancelLabel: 'calendar_subscriptions.cancel',
    });
    render(<>{options.body}</>);
    expect(screen.getByText('calendar_subscriptions.revoke_confirm_body')).toBeInTheDocument();
    expect(screen.getByText(
      `calendar_subscriptions.revoke_confirm_identity:${existingToken.label}:${existingToken.token_prefix}`,
    )).toBeInTheDocument();

    confirmMock.mockResolvedValueOnce(true);
    fireEvent.click(revokeButton);
    await waitFor(() => expect(revokeMock).toHaveBeenCalledWith(existingToken.id));
  });

  it('maps backend create and revoke failures to translated stable messages', async () => {
    createMock.mockResolvedValueOnce({ success: false, error: 'raw create database detail' });
    revokeMock.mockResolvedValueOnce({ success: false, error: 'raw revoke database detail' });
    render(<CalendarSubscriptionPanel />);
    fireEvent.click(screen.getByRole('button', { name: 'calendar_subscriptions.button' }));
    await screen.findByText('Desktop calendar');

    fireEvent.click(screen.getByRole('button', { name: 'calendar_subscriptions.create' }));
    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith(
      'calendar_subscriptions.create_error',
    ));
    expect(toastErrorMock).not.toHaveBeenCalledWith('raw create database detail');

    fireEvent.click(screen.getByRole('button', { name: 'calendar_subscriptions.revoke' }));
    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith(
      'calendar_subscriptions.revoke_error',
    ));
    expect(toastErrorMock).not.toHaveBeenCalledWith('raw revoke database detail');
  });
});
