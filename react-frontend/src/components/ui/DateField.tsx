// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  DateField as HeroUIDateField,
  type DateField as HeroUIDateFieldTypes,
} from '@heroui/react';

export type DateFieldProps = HeroUIDateFieldTypes['Props'];
export const DateField = HeroUIDateField;
