// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { ToggleButton as HeroUIToggleButton, type ToggleButtonProps as HeroUIToggleButtonProps } from '@heroui/react/toggle-button';
import { ToggleButtonGroup as HeroUIToggleButtonGroup, type ToggleButtonGroupProps as HeroUIToggleButtonGroupProps } from '@heroui/react/toggle-button-group';

export type ToggleButtonGroupProps = HeroUIToggleButtonGroupProps;
export type ToggleButtonProps = HeroUIToggleButtonProps;

export const ToggleButtonGroup = HeroUIToggleButtonGroup;
export const ToggleButton = HeroUIToggleButton;
