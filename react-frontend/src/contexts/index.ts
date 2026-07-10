// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

export { AuthProvider, useAuth, useAuthOptional } from './AuthContext';
export { TenantProvider, useTenant, useFeature, useModule } from './TenantContext';
export { ToastProvider, useToast } from './ToastContext';
export { NotificationsProvider, useNotifications, useNotificationsOptional } from './NotificationsContext';
export { ThemeProvider, useTheme } from './ThemeContext';
export type { Toast, ToastType } from './ToastContext';
export type { ThemeMode, ResolvedTheme, FontSize, Density, ThemePreferences } from './ThemeContext';
export { CookieConsentProvider, useCookieConsent, readStoredConsent } from './CookieConsentContext';
export type { CookieConsent } from './CookieConsentContext';
