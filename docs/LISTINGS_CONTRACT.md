# Listings API Contract

This document specifies the API contracts for listings endpoints used by the React frontend.

## Backend Source Files

| File | Purpose |
|------|---------|
| [src/Controllers/Api/ListingsApiController.php](../src/Controllers/Api/ListingsApiController.php) | REST API endpoints |
| [src/Services/ListingService.php](../src/Services/ListingService.php) | Business logic |

---

## 1. GET /api/v2/listings

**Purpose**: List listings with optional filtering and cursor-based pagination.

### Request

**Query Parameters**:
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `type` | string | - | `offer`, `request`, or comma-separated `offer,request` |
| `category_id` | int | - | Filter by category ID |
| `q` | string | - | Search term (searches title, description, location) |
| `cursor` | string | - | Base64-encoded ID for pagination |
| `per_page` | int | 20 | Items per page (max 100) |
| `user_id` | int | - | Filter by listing owner |

**Headers**:
- `Authorization: Bearer <token>` (optional - public endpoint)
- `X-Tenant-ID: <tenant_id>` (required in CORS mode)

### Response

**HTTP 200 OK**

```typescript
interface ListingsResponse {
  data: Listing[];
  meta: {
    per_page: number;
    has_more: boolean;
    cursor?: string;     // Base64-encoded, only present if has_more
    base_url?: string;
  };
}

interface Listing {
  id: number;
  title: string;
  description: string;
  type: 'offer' | 'request';
  category_id: number | null;
  category_name?: string;
  category_color?: string;
  image_url: string | null;
  location: string | null;
  latitude: number | null;
  longitude: number | null;
  status: string | null;        // 'active', 'deleted', null
  federated_visibility: string; // 'none', 'listed', 'bookable'
  created_at: string;           // ISO 8601
  updated_at: string;           // ISO 8601
  user_id: number;
  author_name: string;          // User's name or organization name
  author_avatar: string | null;
}
```

---

## 2. GET /api/v2/listings/:id

**Purpose**: Get a single listing with full details.

### Request

**URL Parameters**:
- `id`: Listing ID (integer)

**Headers**:
- `Authorization: Bearer <token>` (optional - public endpoint)
- `X-Tenant-ID: <tenant_id>` (required in CORS mode)

### Response A: Success

**HTTP 200 OK**

```typescript
interface ListingDetailResponse {
  data: ListingDetail;
}

interface ListingDetail extends Listing {
  // All fields from Listing plus:
  author_email: string;
  attributes: Attribute[];
  likes_count: number;
  comments_count: number;
}

interface Attribute {
  id: number;
  name: string;
  slug: string;
  value: string;
}
```

### Response B: Not Found

**HTTP 404 Not Found**

```typescript
interface ErrorResponse {
  errors: [{
    code: 'NOT_FOUND';
    message: 'Listing not found';
  }];
}
```

---

## Notes

### Cursor Pagination

The API uses cursor-based pagination for infinite scroll:

1. First request: No `cursor` parameter
2. If `meta.has_more` is true, `meta.cursor` contains the next cursor
3. Subsequent requests: Include `cursor` parameter
4. When `has_more` is false, no more items available

```typescript
// First page
const first = await getListings({ per_page: 20 });

// Next page
if (first.meta.has_more) {
  const next = await getListings({ per_page: 20, cursor: first.meta.cursor });
}
```

### Public vs Authenticated

Both endpoints are publicly accessible (no auth required). Authentication is optional but may affect:
- Listing visibility for private/federated listings (future)
- Personalized sorting (future)

### Search Behavior

The `q` parameter performs a LIKE search on:
- `title`
- `description`
- `location`

Search is case-insensitive and matches partial strings.

### Type Filtering

- `type=offer` - Only offers
- `type=request` - Only requests
- `type=offer,request` - Both (same as no filter)
- No `type` parameter - All listings
