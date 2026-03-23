// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { type ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { ChevronRight } from 'lucide-react';
import { motion } from 'framer-motion';

interface ExploreSectionProps {
  title: string;
  subtitle?: string;
  seeAllLink?: string;
  seeAllLabel?: string;
  children: ReactNode;
  className?: string;
}

/**
 * Reusable section wrapper for the Explore page.
 * Provides consistent heading, optional subtitle, and "See All" link.
 */
export function ExploreSection({
  title,
  subtitle,
  seeAllLink,
  seeAllLabel = 'See All',
  children,
  className = '',
}: ExploreSectionProps) {
  return (
    <motion.section
      initial={{ opacity: 0, y: 20 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: '-50px' }}
      transition={{ duration: 0.4, ease: 'easeOut' }}
      className={`mb-10 ${className}`}
    >
      <div className="flex items-end justify-between mb-4">
        <div>
          <h2 className="text-xl sm:text-2xl font-bold text-[var(--text-primary)]">
            {title}
          </h2>
          {subtitle && (
            <p className="text-sm text-[var(--text-muted)] mt-1">{subtitle}</p>
          )}
        </div>
        {seeAllLink && (
          <Link
            to={seeAllLink}
            className="flex items-center gap-1 text-sm font-medium text-[var(--color-primary)] hover:underline shrink-0"
          >
            {seeAllLabel}
            <ChevronRight className="w-4 h-4" />
          </Link>
        )}
      </div>
      {children}
    </motion.section>
  );
}
