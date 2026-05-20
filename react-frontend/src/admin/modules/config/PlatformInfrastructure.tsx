// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Platform Infrastructure
 *
 * Tenant-wide infrastructure that doesn't belong to a single module:
 *  - Languages (default + supported)
 *  - Maps & location (kill switch + provider choice)
 *  - Per-tenant API keys (Google Maps, MapTiler)
 *
 * Rendered inline at the bottom of /admin/module-configuration.
 * Previously lived on /admin/tenant-features (now retired).
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Card, CardBody, CardHeader, Switch, Spinner, Button, Divider,
  Select, SelectItem, Checkbox, Input,
} from '@heroui/react';
import Globe from 'lucide-react/icons/globe';
import MapPin from 'lucide-react/icons/map-pin';
import KeyRound from 'lucide-react/icons/key-round';
import Lock from 'lucide-react/icons/lock';
import Eye from 'lucide-react/icons/eye';
import EyeOff from 'lucide-react/icons/eye-off';
import { useTranslation } from 'react-i18next';
import { useToast, useTenant } from '@/contexts';
import { adminConfig, adminSettings } from '../../api/adminApi';
import type { TenantConfig } from '../../api/types';

const PLATFORM_LANGUAGES = [
  { code: 'en', label: 'English', short: 'EN' },
  { code: 'ga', label: 'Gaeilge', short: 'GA' },
  { code: 'de', label: 'Deutsch', short: 'DE' },
  { code: 'fr', label: 'Français', short: 'FR' },
  { code: 'it', label: 'Italiano', short: 'IT' },
  { code: 'pt', label: 'Português', short: 'PT' },
  { code: 'es', label: 'Español', short: 'ES' },
  { code: 'nl', label: 'Nederlands', short: 'NL' },
  { code: 'pl', label: 'Polski', short: 'PL' },
  { code: 'ja', label: '日本語', short: 'JA' },
  { code: 'ar', label: 'العربية', short: 'AR' },
];

interface PlatformInfrastructureProps {
  config: TenantConfig | null;
  onConfigChange: (updater: (prev: TenantConfig | null) => TenantConfig | null) => void;
}

export default function PlatformInfrastructure({ config: _config, onConfigChange: _onConfigChange }: PlatformInfrastructureProps) {
  const { t } = useTranslation('admin');
  const toast = useToast();
  const { refreshTenant, supportedLanguages, defaultLanguage } = useTenant();

  const [loading, setLoading] = useState(true);
  // Language state
  const [langDefault, setLangDefault] = useState(defaultLanguage);
  const [langSupported, setLangSupported] = useState<string[]>(supportedLanguages);
  const [savingLang, setSavingLang] = useState(false);

  // Provider state
  const [mapProvider, setMapProvider] = useState<'google' | 'openstreetmap'>('google');
  const [geocodingProvider, setGeocodingProvider] = useState<'google' | 'nominatim'>('google');
  const [savingProviders, setSavingProviders] = useState(false);

  // API keys
  const [googleMapsKeyDisplay, setGoogleMapsKeyDisplay] = useState('');
  const [googleMapsKeyInput, setGoogleMapsKeyInput] = useState('');
  const [googleMapsKeySet, setGoogleMapsKeySet] = useState(false);
  const [googleMapId, setGoogleMapId] = useState('');
  const [maptilerKeyDisplay, setMaptilerKeyDisplay] = useState('');
  const [maptilerKeyInput, setMaptilerKeyInput] = useState('');
  const [maptilerKeySet, setMaptilerKeySet] = useState(false);
  const [savingKeys, setSavingKeys] = useState(false);
  const [showGoogleKey, setShowGoogleKey] = useState(false);
  const [showMaptilerKey, setShowMaptilerKey] = useState(false);

  useEffect(() => {
    setLangDefault(defaultLanguage);
    setLangSupported(supportedLanguages);
  }, [defaultLanguage, supportedLanguages]);

  const loadSettings = useCallback(async () => {
    setLoading(true);
    const settingsRes = await adminSettings.get();
    if (settingsRes.success && settingsRes.data) {
      const s = settingsRes.data.settings as Record<string, unknown>;
      const mp = s.map_provider;
      const gp = s.geocoding_provider;
      if (mp === 'google' || mp === 'openstreetmap') setMapProvider(mp);
      if (gp === 'google' || gp === 'nominatim') setGeocodingProvider(gp);

      const gk = s.google_maps_api_key;
      setGoogleMapsKeyDisplay(typeof gk === 'string' ? gk : '');
      setGoogleMapsKeySet(s.google_maps_api_key_set === true);

      const gmid = s.google_maps_map_id;
      setGoogleMapId(typeof gmid === 'string' ? gmid : '');

      const mk = s.maptiler_api_key;
      setMaptilerKeyDisplay(typeof mk === 'string' ? mk : '');
      setMaptilerKeySet(s.maptiler_api_key_set === true);
    }
    setLoading(false);
  }, []);

  useEffect(() => {
    loadSettings();
  }, [loadSettings]);

  const handleLangToggle = (code: string, checked: boolean) => {
    if (code === 'en') return;
    const updated = checked
      ? [...langSupported, code]
      : langSupported.filter((c) => c !== code);
    setLangSupported(updated);
    if (!checked && langDefault === code) setLangDefault('en');
  };

  const handleSaveLanguages = async () => {
    setSavingLang(true);
    const res = await adminConfig.updateLanguageConfig({
      default_language: langDefault,
      supported_languages: langSupported,
    });
    if (res.success) {
      toast.success(t('tenant_features.language_settings_saved'));
      refreshTenant();
    } else {
      toast.error(res.error || t('tenant_features.language_settings_save_failed'));
    }
    setSavingLang(false);
  };

  const handleSaveProviders = async () => {
    setSavingProviders(true);
    const res = await adminSettings.update({
      map_provider: mapProvider,
      geocoding_provider: geocodingProvider,
    });
    if (res.success) {
      toast.success(t('tenant_features.maps_providers_saved'));
      refreshTenant();
    } else {
      toast.error(res.error || t('tenant_features.maps_providers_save_failed'));
    }
    setSavingProviders(false);
  };

  const handleSaveApiKeys = async () => {
    setSavingKeys(true);
    const payload: Record<string, string> = {};
    if (googleMapsKeyInput !== '') payload.google_maps_api_key = googleMapsKeyInput.trim();
    payload.google_maps_map_id = googleMapId.trim();
    if (maptilerKeyInput !== '') payload.maptiler_api_key = maptilerKeyInput.trim();
    const res = await adminSettings.update(payload);
    if (res.success) {
      toast.success(t('tenant_features.api_keys_saved'));
      setGoogleMapsKeyInput('');
      setMaptilerKeyInput('');
      await loadSettings();
    } else {
      toast.error(res.error || t('tenant_features.api_keys_save_failed'));
    }
    setSavingKeys(false);
  };

  const handleClearGoogleKey = async () => {
    setSavingKeys(true);
    const res = await adminSettings.update({ google_maps_api_key: '' });
    if (res.success) {
      toast.success(t('tenant_features.google_key_cleared'));
      await loadSettings();
    } else {
      toast.error(res.error || t('tenant_features.clear_failed'));
    }
    setSavingKeys(false);
  };

  const handleClearMaptilerKey = async () => {
    setSavingKeys(true);
    const res = await adminSettings.update({ maptiler_api_key: '' });
    if (res.success) {
      toast.success(t('tenant_features.maptiler_key_cleared'));
      await loadSettings();
    } else {
      toast.error(res.error || t('tenant_features.clear_failed'));
    }
    setSavingKeys(false);
  };

  if (loading) {
    return (
      <div className="flex h-32 items-center justify-center">
        <Spinner size="sm" />
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <div className="lg:col-span-3 space-y-6">

        {/* Languages */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <Globe size={18} className="text-primary" />
            <h3 className="font-semibold">{t('tenant_features.language_localisation_heading')}</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4 space-y-4">
            <div>
              <p className="text-sm font-medium mb-1">{t('tenant_features.default_language')}</p>
              <p className="text-xs text-default-400 mb-2">
                {t('tenant_features.default_language_hint')}
              </p>
              <Select
                aria-label={t('tenant_features.default_language')}
                selectedKeys={[langDefault]}
                onSelectionChange={(keys) => {
                  const val = Array.from(keys)[0] as string;
                  if (val) setLangDefault(val);
                }}
                className="max-w-xs"
                size="sm"
              >
                {PLATFORM_LANGUAGES.filter((l) => langSupported.includes(l.code)).map((lang) => (
                  <SelectItem key={lang.code}>{lang.label} ({lang.short})</SelectItem>
                ))}
              </Select>
            </div>
            <Divider />
            <div>
              <p className="text-sm font-medium mb-1">{t('tenant_features.available_languages')}</p>
              <p className="text-xs text-default-400 mb-3">
                {t('tenant_features.available_languages_hint')}
              </p>
              <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                {PLATFORM_LANGUAGES.map((lang) => (
                  <Checkbox
                    key={lang.code}
                    isSelected={langSupported.includes(lang.code)}
                    isDisabled={lang.code === 'en'}
                    onValueChange={(checked) => handleLangToggle(lang.code, checked)}
                  >
                    <span className="text-sm">
                      {lang.label} ({lang.short})
                      {lang.code === 'en' && (
                        <span className="ml-2 text-xs text-default-400">{t('tenant_features.always_enabled')}</span>
                      )}
                    </span>
                  </Checkbox>
                ))}
              </div>
            </div>
            <div className="flex justify-end">
              <Button
                color="primary"
                size="sm"
                isLoading={savingLang}
                isDisabled={savingLang}
                onPress={handleSaveLanguages}
              >
                {t('tenant_features.save_changes')}
              </Button>
            </div>
          </CardBody>
        </Card>

        {/* Maps & Location */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <MapPin size={18} className="text-warning" />
            <h3 className="font-semibold">{t('tenant_features.maps_card_title')}</h3>
            <span className="text-sm text-default-400">
              {t('tenant_features.maps_card_subtitle')}
            </span>
          </CardHeader>
          <CardBody className="px-4 pb-4 space-y-4">
            <div className="flex flex-wrap gap-2 items-center text-xs">
              <span className="text-default-500">{t('tenant_features.currently_serving')}:</span>
              <span className="px-2 py-0.5 rounded-full font-medium bg-default-200 text-default-600">
                {t('tenant_features.status_maps')}{': '}
                {t('tenant_features.status_off')}
              </span>
              <span className={`px-2 py-0.5 rounded-full font-medium ${geocodingProvider === 'google' ? 'bg-warning-100 text-warning-700 dark:bg-warning-900/30 dark:text-warning-300' : 'bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-300'}`}>
                {t('tenant_features.status_autocomplete')}{': '}
                {geocodingProvider === 'google'
                  ? t('tenant_features.status_google_places_paid')
                  : t('tenant_features.status_nominatim_free')}
              </span>
            </div>

            {geocodingProvider === 'google' && (
              <div className="rounded-lg bg-warning-50 dark:bg-warning-900/10 px-3 py-2 text-xs text-warning-700 dark:text-warning-300 border border-warning-200 dark:border-warning-800">
                {t('tenant_features.cost_warning')}
              </div>
            )}

            <Divider />

            <div className="flex items-center justify-between rounded-lg bg-default-100 dark:bg-default-50/5 px-3 py-3 opacity-60">
              <div className="pr-4">
                <div className="flex items-center gap-1.5 mb-0.5">
                  <p className="font-medium text-sm">
                    {t('tenant_features.maps_kill_switch_label')}
                  </p>
                  <span className="inline-flex items-center gap-1 text-xs px-1.5 py-0.5 rounded-full bg-default-200 text-default-600">
                    <Lock size={10} />
                    {t('tenant_features.maps_policy_locked')}
                  </span>
                </div>
                <p className="text-xs text-default-500 mt-0.5">
                  {t('tenant_features.maps_policy_desc')}
                </p>
              </div>
              <Switch
                isSelected={false}
                isDisabled={true}
                size="sm"
                aria-label={t('tenant_features.maps_kill_switch_aria')}
              />
            </div>

            <Divider />

            <div>
              <p className="text-sm font-medium mb-1">
                {t('tenant_features.map_provider_label')}
              </p>
              <p className="text-xs text-default-400 mb-2">
                {t('tenant_features.map_provider_desc')}
              </p>
              <Select
                aria-label={t('tenant_features.map_provider_aria')}
                selectedKeys={[mapProvider]}
                onSelectionChange={(keys) => {
                  const val = Array.from(keys)[0] as string;
                  if (val === 'google' || val === 'openstreetmap') setMapProvider(val);
                }}
                className="max-w-xs"
                size="sm"
                isDisabled={true}
              >
                <SelectItem key="google">{t('tenant_features.provider_google')}</SelectItem>
                <SelectItem key="openstreetmap">{t('tenant_features.provider_osm')}</SelectItem>
              </Select>
            </div>

            <div>
              <p className="text-sm font-medium mb-1">
                {t('tenant_features.geocoding_provider_label')}
              </p>
              <p className="text-xs text-default-400 mb-2">
                {t('tenant_features.geocoding_provider_desc')}
              </p>
              <Select
                aria-label={t('tenant_features.geocoding_provider_aria')}
                selectedKeys={[geocodingProvider]}
                onSelectionChange={(keys) => {
                  const val = Array.from(keys)[0] as string;
                  if (val === 'google' || val === 'nominatim') setGeocodingProvider(val);
                }}
                className="max-w-xs"
                size="sm"
              >
                <SelectItem key="google">{t('tenant_features.provider_google_places')}</SelectItem>
                <SelectItem key="nominatim">{t('tenant_features.provider_nominatim')}</SelectItem>
              </Select>
            </div>

            <div className="flex justify-end">
              <Button
                color="primary"
                size="sm"
                isLoading={savingProviders}
                isDisabled={savingProviders}
                onPress={handleSaveProviders}
              >
                {t('tenant_features.save_changes')}
              </Button>
            </div>
          </CardBody>
        </Card>

        {/* API Keys */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <KeyRound size={18} className="text-primary" />
            <h3 className="font-semibold">
              {t('tenant_features.api_keys_card_title')}
            </h3>
            <span className="text-sm text-default-400">
              {t('tenant_features.api_keys_card_subtitle')}
            </span>
          </CardHeader>
          <CardBody className="px-4 pb-4 space-y-5">
            <p className="text-xs text-default-500">
              {t('tenant_features.api_keys_intro')}
            </p>

            <div>
              <div className="flex items-center justify-between mb-1">
                <p className="text-sm font-medium">
                  {t('tenant_features.google_maps_api_key_label')}
                </p>
                <span className={`text-xs px-2 py-0.5 rounded-full ${googleMapsKeySet ? 'bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-300' : 'bg-default-200 text-default-600'}`}>
                  {googleMapsKeySet
                    ? t('tenant_features.key_status_set')
                    : t('tenant_features.key_status_default')}
                </span>
              </div>
              <p className="text-xs text-default-400 mb-2">
                {t('tenant_features.google_maps_api_key_hint')}
              </p>
              <div className="flex gap-2">
                <Input
                  aria-label="Google Maps API key"
                  placeholder={googleMapsKeySet ? googleMapsKeyDisplay : 'AIza…'}
                  value={googleMapsKeyInput}
                  onValueChange={setGoogleMapsKeyInput}
                  type={showGoogleKey ? 'text' : 'password'}
                  size="sm"
                  autoComplete="off"
                  isDisabled={savingKeys}
                  endContent={
                    <Button
                      isIconOnly
                      size="sm"
                      variant="light"
                      className="text-default-500"
                      aria-label={showGoogleKey ? t('tenant_features.hide_key') : t('tenant_features.show_key')}
                      onPress={() => setShowGoogleKey((v) => !v)}
                    >
                      {showGoogleKey ? <EyeOff size={14} /> : <Eye size={14} />}
                    </Button>
                  }
                />
                {googleMapsKeySet && (
                  <Button
                    size="sm"
                    variant="flat"
                    color="danger"
                    isDisabled={savingKeys}
                    onPress={handleClearGoogleKey}
                  >
                    {t('tenant_features.clear')}
                  </Button>
                )}
              </div>
            </div>

            <div>
              <p className="text-sm font-medium mb-1">
                {t('tenant_features.google_map_id_label')}
              </p>
              <p className="text-xs text-default-400 mb-2">
                {t('tenant_features.google_map_id_hint')}
              </p>
              <Input
                aria-label="Google Map ID"
                placeholder="map-id…"
                value={googleMapId}
                onValueChange={setGoogleMapId}
                size="sm"
                className="max-w-md"
                autoComplete="off"
                isDisabled={savingKeys}
              />
            </div>

            <Divider />

            <div>
              <div className="flex items-center justify-between mb-1">
                <p className="text-sm font-medium">
                  {t('tenant_features.maptiler_api_key_label')}
                </p>
                <span className={`text-xs px-2 py-0.5 rounded-full ${maptilerKeySet ? 'bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-300' : 'bg-default-200 text-default-600'}`}>
                  {maptilerKeySet
                    ? t('tenant_features.tiles_status_maptiler')
                    : t('tenant_features.tiles_status_free_osm')}
                </span>
              </div>
              <p className="text-xs text-default-400 mb-2">
                {t('tenant_features.maptiler_api_key_hint')}
              </p>
              <div className="flex gap-2">
                <Input
                  aria-label="MapTiler API key"
                  placeholder={maptilerKeySet ? maptilerKeyDisplay : '…'}
                  value={maptilerKeyInput}
                  onValueChange={setMaptilerKeyInput}
                  type={showMaptilerKey ? 'text' : 'password'}
                  size="sm"
                  autoComplete="off"
                  isDisabled={savingKeys}
                  endContent={
                    <Button
                      isIconOnly
                      size="sm"
                      variant="light"
                      className="text-default-500"
                      aria-label={showMaptilerKey ? t('tenant_features.hide_key') : t('tenant_features.show_key')}
                      onPress={() => setShowMaptilerKey((v) => !v)}
                    >
                      {showMaptilerKey ? <EyeOff size={14} /> : <Eye size={14} />}
                    </Button>
                  }
                />
                {maptilerKeySet && (
                  <Button
                    size="sm"
                    variant="flat"
                    color="danger"
                    isDisabled={savingKeys}
                    onPress={handleClearMaptilerKey}
                  >
                    {t('tenant_features.clear')}
                  </Button>
                )}
              </div>
            </div>

            <div className="flex justify-end">
              <Button
                color="primary"
                size="sm"
                isLoading={savingKeys}
                isDisabled={savingKeys || (!googleMapsKeyInput && !maptilerKeyInput && googleMapId === '')}
                onPress={handleSaveApiKeys}
              >
                {t('tenant_features.save_api_keys')}
              </Button>
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  );
}
