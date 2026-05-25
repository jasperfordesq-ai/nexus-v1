// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { HTMLAttributes, ReactNode } from 'react';
import { cn } from '@/lib/helpers';

type CodeSize = 'sm' | 'md' | 'lg';

const sizeClasses: Record<CodeSize, string> = {
  sm: 'text-sm',
  md: 'text-base',
  lg: 'text-lg',
};

export interface CodeProps extends HTMLAttributes<HTMLElement> {
  children: ReactNode;
  size?: CodeSize;
}

export function Code({ children, className, size = 'sm', ...props }: CodeProps) {
  return (
    <code
      className={cn(
        'rounded bg-default-100 px-2 py-1 font-mono font-normal text-default-700',
        sizeClasses[size],
        className,
      )}
      {...props}
    >
      {children}
    </code>
  );
}
