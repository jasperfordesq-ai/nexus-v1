# Listings Public Contract

This contract is the template for rich public-route contracts consumed by the
shadow Next.js frontend. It is intentionally additive to the existing Laravel
`/api/v2/listings` payload so the React/Vite SPA and prerender paths keep their
current field semantics.

## Envelope

- `data`: listing rows retain the existing SPA fields and include a
  `public_contract` object.
- `meta.page`: current requested page, currently informational while cursor
  pagination remains the backend source of truth.
- `meta.cursor`: next cursor, or `null` when no further page exists.
- `meta.per_page`: requested page size after backend validation.
- `meta.total`: total public listings matching the current filters.
- `meta.total_items`: legacy total field retained for existing consumers.
- `meta.has_more`: whether a further cursor page exists.

## `public_contract`

- `id`: numeric listing id.
- `slug`: stable string route identifier. Uses a future `slug` field when
  present; otherwise falls back to the numeric id string because current public
  listing routes are `/listings/:id`.
- `title`: public listing title.
- `description`: full public description for detail pages.
- `excerpt`: crawler/card-safe text excerpt derived from the description.
- `primary_image`: `{ url, alt_text }` for cards and social previews, or `null`.
- `gallery`: ordered public image list of `{ url, alt_text, sort_order }`.
- `category`: `{ id, name, slug }`, nullable when the listing is uncategorised.
- `location`: neutral/global location object
  `{ label, latitude, longitude }`; no Irish default location is inferred.
- `time_credit_value`: `{ hours, unit }`, where `unit` is currently `hour`.
- `provider`: `{ id, display_name }`, with no private contact fields.
- `created_at`: public creation timestamp.
- `updated_at`: public update timestamp.
- `status`: public status value, normally `active`.
- `type`: optional public listing type (`offer` or `request`) for future UI
  refinement.

The Next.js listings renderer must use only `public_contract` for listing SEO,
cards, detail pages, JSON-LD, and no-JavaScript HTML.
