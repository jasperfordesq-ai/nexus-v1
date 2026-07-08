// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Tag as HeroUITag } from '@heroui/react/tag';
import { TagGroup as HeroUITagGroup, type TagGroupProps as HeroUITagGroupProps } from '@heroui/react/tag-group';
import type { ComponentProps } from 'react';

export type TagGroupProps = HeroUITagGroupProps;
export type TagProps = ComponentProps<typeof HeroUITag>;

export const TagGroup = HeroUITagGroup;
export const Tag = HeroUITag;
