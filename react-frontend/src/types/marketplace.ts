// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Marketplace Module Type Definitions
 *
 * Types for marketplace listings, offers, categories, sellers, and filters.
 */

// ─────────────────────────────────────────────────────────────────────────────
// Listing Types
// ─────────────────────────────────────────────────────────────────────────────

export interface MarketplaceListingItem {
  id: number;
  title: string;
  tagline?: string;
  price: number | null;
  price_currency: string;
  price_type: 'fixed' | 'negotiable' | 'free' | 'auction' | 'contact';
  time_credit_price?: number | null;
  condition: 'new' | 'like_new' | 'good' | 'fair' | 'poor' | null;
  location?: string;
  delivery_method: string;
  seller_type: string;
  status: string;
  image: { url: string; thumbnail_url?: string; alt_text?: string } | null;
  image_count: number;
  category?: { id: number; name: string; slug: string; icon?: string } | null;
  user?: { id: number; name: string; avatar_url?: string; is_verified?: boolean } | null;
  is_saved: boolean;
  is_own: boolean;
  is_promoted: boolean;
  views_count: number;
  created_at: string;
}

export interface MarketplaceListingDetail extends MarketplaceListingItem {
  description: string;
  quantity: number;
  latitude?: number;
  longitude?: number;
  shipping_available: boolean;
  local_pickup: boolean;
  template_data?: Record<string, unknown>;
  images: Array<{
    id: number;
    url: string;
    thumbnail_url?: string;
    alt_text?: string;
    is_primary: boolean;
  }>;
  saves_count: number;
  expires_at?: string;
  updated_at?: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Category Types
// ─────────────────────────────────────────────────────────────────────────────

export interface MarketplaceCategory {
  id: number;
  name: string;
  slug: string;
  description?: string;
  icon?: string;
  parent_id?: number;
  listing_count: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Offer Types
// ─────────────────────────────────────────────────────────────────────────────

export interface MarketplaceOffer {
  id: number;
  amount: number;
  currency: string;
  message?: string;
  status: 'pending' | 'accepted' | 'declined' | 'countered' | 'expired' | 'withdrawn';
  counter_amount?: number;
  counter_message?: string;
  expires_at?: string;
  accepted_at?: string;
  created_at: string;
  listing?: {
    id: number;
    title: string;
    price: number;
    price_currency: string;
    status: string;
    image?: { url: string; thumbnail_url?: string } | null;
  };
  buyer?: { id: number; name: string; avatar_url?: string };
  seller?: { id: number; name: string; avatar_url?: string };
}

// ─────────────────────────────────────────────────────────────────────────────
// Filter Types
// ─────────────────────────────────────────────────────────────────────────────

export interface MarketplaceFilters {
  q?: string;
  category_id?: number;
  price_min?: number;
  price_max?: number;
  condition?: string[];
  seller_type?: string;
  delivery_method?: string;
  sort?: string;
  posted_within?: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Seller Types
// ─────────────────────────────────────────────────────────────────────────────

export interface MarketplaceSellerProfile {
  id: number;
  user_id: number;
  display_name: string;
  bio?: string;
  avatar_url?: string;
  cover_image_url?: string;
  seller_type: string;
  business_name?: string;
  business_verified: boolean;
  is_community_endorsed: boolean;
  community_trust_score?: number;
  avg_rating?: number;
  total_ratings: number;
  total_sales: number;
  response_time_avg?: number;
  response_rate?: number;
  active_listings: number;
  member_since?: string;
  joined_marketplace_at?: string;
}
