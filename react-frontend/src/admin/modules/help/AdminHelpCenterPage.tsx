// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardBody, CardHeader, Button, Input, Chip } from '@heroui/react';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import BookOpen from 'lucide-react/icons/book-open';
import SearchIcon from 'lucide-react/icons/search';
import HelpCircle from 'lucide-react/icons/help-circle';
import { PageHeader } from '../../components';
import { HELP_CONTENT, type HelpArticle } from '../../data/helpContent';

// ─────────────────────────────────────────────────────────────────────────────
// Category detection
// ─────────────────────────────────────────────────────────────────────────────

type Category = 'Caring Community' | 'KISS & AGORIS' | 'General Admin';

const KISS_PATHS = new Set([
  '/admin/national/kiss',
  '/admin/ki-agents',
  '/admin/pilot-inquiries',
]);

function getCategory(path: string): Category {
  if (path.startsWith('/caring/') || path === '/caring') {
    return 'Caring Community';
  }
  if (KISS_PATHS.has(path)) {
    return 'KISS & AGORIS';
  }
  return 'General Admin';
}

const CATEGORY_ORDER: Category[] = ['General Admin', 'Caring Community', 'KISS & AGORIS'];

const CATEGORY_CHIP_COLOR: Record<Category, 'default' | 'secondary' | 'warning'> = {
  'General Admin': 'default',
  'Caring Community': 'secondary',
  'KISS & AGORIS': 'warning',
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export default function AdminHelpCenterPage() {
  usePageTitle('Help Centre');
  const { tenantPath } = useTenant();
  const navigate = useNavigate();
  const [query, setQuery] = useState('');

  // Filter + categorise in one pass
  const categorised = useMemo(() => {
    const q = query.toLowerCase().trim();

    const filtered = Object.entries(HELP_CONTENT).filter(
      ([, article]: [string, HelpArticle]) =>
        !q ||
        article.title.toLowerCase().includes(q) ||
        article.summary.toLowerCase().includes(q),
    );

    const groups = new Map<Category, Array<[string, HelpArticle]>>();
    for (const entry of filtered) {
      const cat = getCategory(entry[0]);
      if (!groups.has(cat)) groups.set(cat, []);
      groups.get(cat)!.push(entry);
    }

    return CATEGORY_ORDER.map((cat) => ({
      category: cat,
      articles: groups.get(cat) ?? [],
    })).filter((g) => g.articles.length > 0);
  }, [query]);

  const totalArticles = useMemo(
    () => Object.keys(HELP_CONTENT).length,
    [],
  );

  const isEmpty = query.trim() && categorised.length === 0;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Admin Help Centre"
        description="Find guidance for every page in the admin panel. Click any article to go to that page — a ? button will open contextual help."
      />

      {/* Search bar */}
      <div className="max-w-lg">
        <Input
          placeholder={`Search ${totalArticles} help articles…`}
          value={query}
          onValueChange={setQuery}
          startContent={<SearchIcon size={16} className="text-default-400 shrink-0" />}
          variant="bordered"
          size="md"
          aria-label="Search help articles"
          isClearable
          onClear={() => setQuery('')}
        />
      </div>

      {/* Empty state */}
      {isEmpty && (
        <div className="flex flex-col items-center gap-3 py-16 text-center text-default-400">
          <SearchIcon size={40} className="opacity-40" />
          <p className="text-sm">No articles match your search.</p>
        </div>
      )}

      {/* Categorised article grid */}
      {!isEmpty &&
        categorised.map(({ category, articles }) => (
          <section key={category}>
            <div className="mb-3 flex items-center gap-2">
              <h2 className="text-base font-semibold text-foreground">{category}</h2>
              <Chip
                size="sm"
                variant="flat"
                color={CATEGORY_CHIP_COLOR[category]}
              >
                {articles.length}
              </Chip>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {articles.map(([path, article]) => (
                <Card
                  key={path}
                  isPressable
                  onPress={() => navigate(tenantPath(path))}
                  className="transition-shadow hover:shadow-md"
                >
                  <CardHeader className="flex items-start gap-2 pb-1">
                    <BookOpen size={16} className="mt-0.5 shrink-0 text-primary" />
                    <span className="text-sm font-semibold text-foreground leading-snug">
                      {article.title}
                    </span>
                  </CardHeader>
                  <CardBody className="flex flex-col gap-3 pt-0">
                    <p className="line-clamp-2 text-xs text-default-500 leading-relaxed">
                      {article.summary}
                    </p>
                    <Button
                      size="sm"
                      variant="flat"
                      color="primary"
                      className="self-start"
                      onPress={() => navigate(tenantPath(path))}
                      startContent={<HelpCircle size={14} />}
                    >
                      View help
                    </Button>
                  </CardBody>
                </Card>
              ))}
            </div>
          </section>
        ))}
    </div>
  );
}
