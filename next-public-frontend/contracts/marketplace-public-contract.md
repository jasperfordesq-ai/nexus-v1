# Marketplace Public Contract

This contract follows the listings, events, and jobs public-contract pattern.
It is opt-in and is only emitted for callers that send
`include=public_contract` or `X-Public-Contract: 1`.

## Envelope

- `data`: marketplace rows retain the existing SPA fields and include
  `public_contract` only for opted-in callers.
- `meta.cursor`: next cursor, or `null` when no further page exists.
- `meta.per_page`: requested page size after backend validation.
- `meta.has_more`: whether a further cursor page exists.
- `meta.total`: total public result count when available from the existing
  marketplace service.

## `public_contract`

- `id`: numeric marketplace listing id.
- `slug`: stable string route identifier, currently the numeric id string.
- `title`: public marketplace item title.
- `description`: full public item description, empty on list endpoints if the
  existing endpoint does not expose full text.
- `excerpt`: card/social-preview text, preferring the existing `tagline`.
- `primary_image`: `{ url, alt_text }` for cards and social previews, normally
  the primary marketplace image, or `null`.
- `gallery`: ordered public images as `{ url, alt_text, sort_order }`.
- `category`: `{ id, name, slug }`, nullable when uncategorised.
- `location`: neutral/global `{ label, latitude, longitude }`; no default
  location is inferred.
- `price`: public pricing summary containing `amount`, `currency`,
  `price_type`, and `time_credits`.
- `seller`: public seller display summary containing `id`, `display_name`,
  `avatar_url`, `is_verified`, and `seller_type`; no private contact fields.
- `delivery`: public delivery flags containing `method`, `shipping_available`,
  and `local_pickup`.
- `condition`: public item condition such as `new`, `like_new`, `good`, `fair`,
  or `poor`.
- `quantity`: public quantity when exposed by the detail endpoint, nullable.
- `expires_at`: public expiry timestamp, nullable.
- `created_at`: public creation timestamp.
- `updated_at`: public update timestamp, nullable on list endpoints.
- `status`: public status value, normally `active`.

The Next.js marketplace renderer must use only `public_contract` for cards,
detail pages, metadata, schema.org/Product + Offer JSON-LD, and
no-JavaScript HTML.
