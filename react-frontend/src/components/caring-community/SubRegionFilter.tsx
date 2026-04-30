// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SubRegionFilter — AG77 member-facing locality filter
 *
 * Lets members narrow caring community surfaces (care providers, Markt) to a
 * specific Quartier / Ortsteil / Gemeinde / Kanton. Renders nothing when the
 * tenant has no active sub-regions configured, so the filter degrades silently
 * for tenants that have not opted into geographic segmentation.
 */

import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Select, SelectItem } from '@heroui/react';
import MapPin from 'lucide-react/icons/map-pin';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

export interface SubRegion {
  id: number;
  name: string;
  slug: string;
  type: 'quartier' | 'ortsteil' | 'municipality' | 'canton' | 'other';
  description: string | null;
  postal_codes: string[] | null;
  status: 'active' | 'inactive';
}

interface SubRegionListResponse {
  data: SubRegion[];
  total: number;
}

export interface SubRegionFilterProps {
  /** Currently selected sub-region id, or null when no filter applied. */
  selectedId: number | null;
  onChange: (id: number | null) => void;
  className?: string;
  /** Optional label override; defaults to the translated "Sub-region" label. */
  label?: string;
}

const STORAGE_KEY = 'nexus.caring.subRegions.cache.v1';
const CACHE_TTL_MS = 5 * 60 * 1000;

interface CachedPayload {
  fetched_at: number;
  regions: SubRegion[];
}

function readCache(): SubRegion[] | null {
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw) as CachedPayload;
    if (!parsed?.fetched_at || !Array.isArray(parsed.regions)) return null;
    if (Date.now() - parsed.fetched_at > CACHE_TTL_MS) return null;
    return parsed.regions;
  } catch {
    return null;
  }
}

function writeCache(regions: SubRegion[]): void {
  try {
    const payload: CachedPayload = { fetched_at: Date.now(), regions };
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
  } catch {
    /* ignore quota / privacy-mode failures */
  }
}

export function SubRegionFilter({
  selectedId,
  onChange,
  className,
  label,
}: SubRegionFilterProps) {
  const { t } = useTranslation('common');
  const [regions, setRegions] = useState<SubRegion[] | null>(() => readCache());
  const [loadFailed, setLoadFailed] = useState(false);

  useEffect(() => {
    let cancelled = false;
    void (async () => {
      try {
        const res = await api.get<SubRegionListResponse>('/v2/caring-community/sub-regions');
        if (cancelled) return;
        const list = (res.data?.data ?? []).filter((r) => r.status === 'active');
        setRegions(list);
        writeCache(list);
      } catch (err) {
        if (cancelled) return;
        logError('SubRegionFilter.fetch', err);
        // Soft-fail: hide the filter for tenants without the feature, keep cached if present.
        if (!regions) setLoadFailed(true);
      }
    })();
    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const grouped = useMemo(() => {
    if (!regions) return null;
    const order: SubRegion['type'][] = ['canton', 'municipality', 'ortsteil', 'quartier', 'other'];
    return [...regions].sort((a, b) => {
      const ai = order.indexOf(a.type);
      const bi = order.indexOf(b.type);
      if (ai !== bi) return ai - bi;
      return a.name.localeCompare(b.name);
    });
  }, [regions]);

  // Hide filter entirely when tenant has no sub-regions or fetch failed (no opt-in).
  if (loadFailed) return null;
  if (regions !== null && regions.length === 0) return null;

  const selectedKey = selectedId !== null ? String(selectedId) : '__all__';

  return (
    <div className={['flex items-center gap-2', className].filter(Boolean).join(' ')}>
      <div className="flex items-center gap-1.5 text-theme-muted shrink-0">
        <MapPin className="w-4 h-4 shrink-0" aria-hidden="true" />
        <span className="text-sm font-medium text-theme-secondary">
          {label ?? t('sub_region.label')}
        </span>
      </div>
      <Select
        aria-label={label ?? t('sub_region.label')}
        size="sm"
        variant="bordered"
        className="max-w-[260px]"
        selectedKeys={new Set([selectedKey])}
        onSelectionChange={(keys) => {
          const v = Array.from(keys)[0] as string | undefined;
          if (!v || v === '__all__') {
            onChange(null);
          } else {
            const parsed = Number.parseInt(v, 10);
            onChange(Number.isFinite(parsed) ? parsed : null);
          }
        }}
        isLoading={regions === null}
        placeholder={t('sub_region.all_areas')}
      >
        <>
          <SelectItem key="__all__">{t('sub_region.all_areas')}</SelectItem>
          {(grouped ?? []).map((r) => (
            <SelectItem key={String(r.id)} textValue={r.name}>
              <span className="flex items-center gap-2">
                <span className="font-medium">{r.name}</span>
                <span className="text-xs text-default-400">
                  {t(`sub_region.type_${r.type}` as const, { defaultValue: r.type })}
                </span>
              </span>
            </SelectItem>
          ))}
        </>
      </Select>
    </div>
  );
}

export default SubRegionFilter;
