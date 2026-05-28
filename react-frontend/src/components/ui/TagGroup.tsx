// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  Tag as HeroUITag,
  TagGroup as HeroUITagGroup,
  type TagGroupProps as HeroUITagGroupProps,
  type TagProps as HeroUITagProps,
} from '@heroui/react';

export type TagGroupProps = HeroUITagGroupProps;
export type TagProps = HeroUITagProps;

export const TagGroup = HeroUITagGroup;
export const Tag = HeroUITag;
