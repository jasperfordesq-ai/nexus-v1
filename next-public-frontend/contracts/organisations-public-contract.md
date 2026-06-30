# Organisations Public Contract

This contract follows the listings, events, jobs, and marketplace
public-contract pattern. It is opt-in and is only emitted for callers that send
`include=public_contract` or `X-Public-Contract: 1`.

## Envelope

- `data`: organisation rows retain the existing SPA fields and include
  `public_contract` only for opted-in callers.
- `meta.cursor`: next cursor, or `null` when no further page exists.
- `meta.per_page`: requested page size after backend validation.
- `meta.has_more`: whether a further cursor page exists.
- `meta.total`: total public result count when available from the existing
  volunteering service.

## `public_contract`

- `id`: numeric volunteering organisation id.
- `slug`: stable string route identifier, falling back to the numeric id string.
- `name`: public organisation name.
- `description`: full public organisation profile description.
- `excerpt`: card/social-preview text derived from the public description.
- `logo_image`: `{ url, alt_text }` for cards and social previews, or `null`.
- `website`: public organisation website, nullable.
- `contact_email`: public organisation contact email already exposed by the
  existing public endpoint, nullable.
- `location`: neutral/global `{ label }`; no default location is inferred.
- `owner`: public owner display summary containing `id`, `display_name`, and
  `avatar_url`; no private contact fields.
- `stats`: public aggregate counts containing `opportunity_count`,
  `volunteer_count`, `total_hours`, `review_count`, and `average_rating`.
- `org_type`: public organisation type such as `organisation` or `club`.
- `created_at`: public creation timestamp.
- `updated_at`: public update timestamp, nullable.
- `status`: public status value, normally `active` or `approved`.

The contract must never include wallet or financial state such as `balance` or
`auto_pay_enabled`. The Next.js organisations renderer must use only
`public_contract` for cards, detail pages, metadata, schema.org/Organization
JSON-LD, and no-JavaScript HTML.
