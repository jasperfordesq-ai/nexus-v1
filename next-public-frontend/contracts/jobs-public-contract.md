# Jobs Public Contract

This contract follows the listings and events public-contract pattern. It is
opt-in and is only emitted for callers that send `include=public_contract` or
`X-Public-Contract: 1`.

## Envelope

- `data`: job rows retain the existing SPA fields and include
  `public_contract` only for opted-in callers.
- `meta.cursor`: next cursor, or `null` when no further page exists.
- `meta.per_page`: requested page size after backend validation.
- `meta.has_more`: whether a further cursor page exists.
- `meta.total`: total public result count when available from the existing jobs
  service.

## `public_contract`

- `id`: numeric job vacancy id.
- `slug`: stable string route identifier, currently the numeric id string.
- `title`: public job title.
- `description`: full public job description.
- `excerpt`: card/social-preview text, preferring `tagline` when present.
- `primary_image`: `{ url, alt_text }` for cards and social previews, normally
  the employer logo or first culture photo, or `null`.
- `gallery`: ordered public culture photos as `{ url, alt_text, sort_order }`.
- `category`: `{ name, slug }`, nullable when uncategorised.
- `location`: neutral/global `{ label, latitude, longitude, is_remote }`; no
  default location is inferred.
- `employer`: `{ id, display_name, logo_url }`, with no private contact fields.
- `job_type`: public vacancy type such as `paid`, `volunteer`, or `timebank`.
- `commitment`: public commitment value such as `full_time`, `part_time`,
  `flexible`, or `one_off`.
- `skills`: public skill labels parsed from the existing skills field.
- `compensation`: public compensation summary containing `salary_min`,
  `salary_max`, `salary_currency`, `salary_type`, `salary_negotiable`,
  `time_credits`, and `hours_per_week`.
- `deadline_at`: public application deadline timestamp, nullable.
- `created_at`: public creation timestamp.
- `updated_at`: public update timestamp.
- `status`: public status value, normally `open`.

The Next.js jobs renderer must use only `public_contract` for cards, detail
pages, metadata, schema.org/JobPosting JSON-LD, and no-JavaScript HTML.
