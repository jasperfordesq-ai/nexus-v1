// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  Disclosure as HeroUIDisclosure,
  type Disclosure as HeroUIDisclosureTypes,
} from '@heroui/react';

export type DisclosureProps = HeroUIDisclosureTypes['Props'];
export type DisclosureHeadingProps = HeroUIDisclosureTypes['HeadingProps'];
export type DisclosureTriggerProps = HeroUIDisclosureTypes['TriggerProps'];
export type DisclosureContentProps = HeroUIDisclosureTypes['ContentProps'];
export type DisclosureBodyProps = HeroUIDisclosureTypes['BodyProps'];
export type DisclosureIndicatorProps = HeroUIDisclosureTypes['IndicatorProps'];

export const Disclosure = HeroUIDisclosure;
export const DisclosureHeading = HeroUIDisclosure.Heading;
export const DisclosureTrigger = HeroUIDisclosure.Trigger;
export const DisclosureContent = HeroUIDisclosure.Content;
export const DisclosureBody = HeroUIDisclosure.Body;
export const DisclosureIndicator = HeroUIDisclosure.Indicator;

