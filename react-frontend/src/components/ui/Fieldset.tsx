// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Fieldset as HeroUIFieldset, type Fieldset as HeroUIFieldsetTypes } from '@heroui/react/fieldset';

export type FieldsetProps = HeroUIFieldsetTypes['Props'];
export type FieldsetLegendProps = HeroUIFieldsetTypes['LegendProps'];
export type FieldGroupProps = HeroUIFieldsetTypes['GroupProps'];
export type FieldsetActionsProps = HeroUIFieldsetTypes['ActionsProps'];

export const Fieldset = HeroUIFieldset;
export const FieldsetLegend = HeroUIFieldset.Legend;
export const FieldGroup = HeroUIFieldset.Group;
export const FieldsetActions = HeroUIFieldset.Actions;

