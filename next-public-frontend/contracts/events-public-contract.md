# Events Public Contract

This contract follows the listings public-contract pattern. It is opt-in and is
only emitted for callers that send `include=public_contract` or
`X-Public-Contract: 1`.

## Envelope

- `data`: event rows retain the existing SPA fields and include
  `public_contract` only for opted-in callers.
- `meta.cursor`: next cursor, or `null` when no further page exists.
- `meta.per_page`: requested page size after backend validation.
- `meta.has_more`: whether a further cursor page exists.

## `public_contract`

- `id`: numeric event id.
- `slug`: stable string route identifier, currently the numeric id string.
- `title`: public event title.
- `description`: full public event description.
- `excerpt`: crawler/card-safe text excerpt derived from the description.
- `primary_image`: `{ url, alt_text }` for cards and social previews, or `null`.
- `category`: `{ id, name, slug }`, nullable when uncategorised.
- `location`: neutral/global `{ label, latitude, longitude }`; no default
  location is inferred.
- `organiser`: `{ id, display_name }`, with no private contact fields.
- `start_at`: public start timestamp.
- `end_at`: public end timestamp, nullable.
- `created_at`: public creation timestamp.
- `updated_at`: public update timestamp.
- `status`: public status value, normally `active`.

The Next.js events renderer must use only `public_contract` for cards, detail
pages, metadata, schema.org/Event JSON-LD, and no-JavaScript HTML.
