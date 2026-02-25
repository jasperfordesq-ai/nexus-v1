// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { LucideIcon } from 'lucide-react';
import {
  Home,
  LayoutDashboard,
  ListTodo,
  MessageSquare,
  Wallet,
  Users,
  Calendar,
  Bell,
  Settings,
  Search,
  Plus,
  ArrowRightLeft,
  Trophy,
  Target,
  HelpCircle,
  Newspaper,
  BookOpen,
  FolderOpen,
  Heart,
  Building2,
  Globe,
  Info,
  FileText,
  Handshake,
  Stethoscope,
  TrendingUp,
  BarChart3,
  Compass,
  Medal,
  Shield,
  Cookie,
  ChevronDown,
  UserCircle,
  LogOut,
  Moon,
  Sun,
  Star,
  MapPin,
  Clock,
  Tag,
  Hash,
  Link,
  ExternalLink,
  Mail,
  Phone,
  Image,
  Video,
  Music,
  Mic,
  Camera,
  Zap,
  Sparkles,
  Flag,
  Bookmark,
  Archive,
  Grid,
  List,
  BarChart,
  PieChart,
  Activity,
  Coffee,
  Gift,
  Smile,
  ThumbsUp,
  Megaphone,
  Lightbulb,
  Wrench,
  Puzzle,
  Layers,
  Package,
} from 'lucide-react';

/**
 * Curated map of Lucide icons available for dynamic rendering.
 * Used by the menu system (admin icon picker + frontend nav rendering).
 * Only includes icons relevant to navigation — not the full 1400+ set.
 */
export const ICON_MAP: Record<string, LucideIcon> = {
  Home,
  LayoutDashboard,
  ListTodo,
  MessageSquare,
  Wallet,
  Users,
  Calendar,
  Bell,
  Settings,
  Search,
  Plus,
  ArrowRightLeft,
  Trophy,
  Target,
  HelpCircle,
  Newspaper,
  BookOpen,
  FolderOpen,
  Heart,
  Building2,
  Globe,
  Info,
  FileText,
  Handshake,
  Stethoscope,
  TrendingUp,
  BarChart3,
  Compass,
  Medal,
  Shield,
  Cookie,
  ChevronDown,
  UserCircle,
  LogOut,
  Moon,
  Sun,
  Star,
  MapPin,
  Clock,
  Tag,
  Hash,
  Link,
  ExternalLink,
  Mail,
  Phone,
  Image,
  Video,
  Music,
  Mic,
  Camera,
  Zap,
  Sparkles,
  Flag,
  Bookmark,
  Archive,
  Grid,
  List,
  BarChart,
  PieChart,
  Activity,
  Coffee,
  Gift,
  Smile,
  ThumbsUp,
  Megaphone,
  Lightbulb,
  Wrench,
  Puzzle,
  Layers,
  Package,
};

/** All available icon names for the icon picker */
export const ICON_NAMES = Object.keys(ICON_MAP);

interface DynamicIconProps {
  name: string | null | undefined;
  className?: string;
  size?: number;
}

/**
 * Renders a Lucide icon by name string.
 * Returns null for unknown/null names (graceful degradation).
 */
export function DynamicIcon({ name, className, size }: DynamicIconProps) {
  if (!name) return null;
  const Icon = ICON_MAP[name];
  if (!Icon) return null;
  return <Icon className={className} size={size} aria-hidden="true" />;
}
