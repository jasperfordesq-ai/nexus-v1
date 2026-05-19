// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import { PageMeta } from '@/components/seo';

type AdminMetaType = 'website' | 'article' | 'profile';

export interface AdminPageMeta {
  title?: string;
  description?: string;
  keywords?: string;
  image?: string;
  type?: AdminMetaType;
}

interface AdminMetaContextValue {
  meta: AdminPageMeta;
  setPageMeta: (meta: AdminPageMeta | null) => void;
}

const AdminMetaContext = createContext<AdminMetaContextValue | null>(null);

export function AdminMetaProvider({
  children,
  defaultMeta,
}: {
  children: ReactNode;
  defaultMeta: AdminPageMeta;
}) {
  const [pageMeta, setPageMeta] = useState<AdminPageMeta | null>(null);
  const resolvedMeta = useMemo(() => {
    const definedPageMeta = Object.fromEntries(
      Object.entries(pageMeta ?? {}).filter(([, value]) => value !== undefined),
    );

    return { ...defaultMeta, ...definedPageMeta };
  }, [defaultMeta, pageMeta]);

  const value = useMemo(
    () => ({ meta: resolvedMeta, setPageMeta }),
    [resolvedMeta],
  );

  return (
    <AdminMetaContext.Provider value={value}>
      {children}
    </AdminMetaContext.Provider>
  );
}

export function useAdminPageMeta(meta: AdminPageMeta) {
  const context = useContext(AdminMetaContext);
  const setPageMeta = context?.setPageMeta;
  const stableMeta = useMemo(() => ({
    description: meta.description,
    image: meta.image,
    keywords: meta.keywords,
    title: meta.title,
    type: meta.type,
  }), [
    meta.description,
    meta.image,
    meta.keywords,
    meta.title,
    meta.type,
  ]);

  useEffect(() => {
    if (!setPageMeta) return undefined;

    setPageMeta(stableMeta);
    return () => setPageMeta(null);
  }, [
    setPageMeta,
    stableMeta,
  ]);
}

export function useResolvedAdminMeta() {
  const context = useContext(AdminMetaContext);
  if (!context) {
    throw new Error('useResolvedAdminMeta must be used within AdminMetaProvider');
  }
  return context.meta;
}

export function AdminMetaTags() {
  const meta = useResolvedAdminMeta();

  return (
    <PageMeta
      title={meta.title}
      description={meta.description}
      keywords={meta.keywords}
      image={meta.image}
      type={meta.type}
      noIndex
    />
  );
}
