# Project NEXUS - React Frontend

Modern React frontend for Project NEXUS, built with Vite, TypeScript, and Hero UI.

## Stack

- **React 18** - UI framework
- **TypeScript** - Type safety
- **Vite** - Build tool & dev server
- **Hero UI** - Component library
- **Tailwind CSS** - Utility-first styling
- **React Router** - Client-side routing

## Prerequisites

- Node.js 18+ (LTS recommended)
- npm 9+
- PHP backend running at `http://staging.timebank.local`

## Quick Start

```bash
# Install dependencies
npm install

# Start dev server
npm run dev

# Build for production
npm run build

# Preview production build
npm run preview
```

## Environment Configuration

### Local Development (default)

Uses `.env.development`:
```
VITE_API_BASE_URL=http://staging.timebank.local
VITE_TENANT_SLUG=hour-timebank
```

The dev environment:
- Makes cross-origin requests to the PHP backend
- Sends `X-Tenant-ID` header for tenant resolution
- Requires CORS to be configured on the backend

### Production

Uses `.env.production`:
```
VITE_API_BASE_URL=
VITE_TENANT_SLUG=
```

In production:
- Frontend is served from the same domain via reverse proxy
- API requests use relative URLs (`/api/...`)
- Tenant resolved by domain automatically

## Project Structure

```
react-frontend/
├── public/              # Static assets
├── src/
│   ├── api/             # API client & types
│   │   ├── client.ts    # Fetch wrapper with auth
│   │   ├── types.ts     # TypeScript interfaces
│   │   ├── auth.ts      # Auth endpoints
│   │   └── listings.ts  # Listings endpoints
│   ├── auth/            # Authentication context
│   │   └── AuthContext.tsx
│   ├── tenant/          # Tenant configuration
│   │   ├── TenantContext.tsx
│   │   └── useTenantBootstrap.ts
│   ├── components/      # Shared components
│   │   ├── Layout.tsx
│   │   ├── Navbar.tsx
│   │   ├── LoadingScreen.tsx
│   │   └── ErrorScreen.tsx
│   ├── pages/           # Route pages
│   │   ├── HomePage.tsx
│   │   ├── LoginPage.tsx
│   │   ├── ListingsPage.tsx
│   │   └── NotFoundPage.tsx
│   ├── App.tsx          # Main app with routing
│   ├── main.tsx         # Entry point
│   └── index.css        # Global styles
├── .env.development     # Dev environment
├── .env.production      # Prod environment
└── .env.example         # Template
```

## API Integration

### Tenant Bootstrap

On app load, the frontend fetches tenant configuration:

```typescript
GET /api/v2/tenant/bootstrap
// or with header for dev:
GET /api/v2/tenant/bootstrap
X-Tenant-ID: hour-timebank
```

This returns branding, features, SEO settings, etc.

### Authentication

```typescript
// Login
POST /api/v2/auth/login
{ "email": "...", "password": "..." }
// Returns: { access_token, refresh_token, user }

// Logout
POST /api/v2/auth/logout
Authorization: Bearer <token>

// Refresh
POST /api/v2/auth/refresh
{ "refresh_token": "..." }
```

### Listings

```typescript
GET /api/v2/listings?page=1&per_page=12&type=offer
Authorization: Bearer <token>
```

## Development Notes

### Adding New Pages

1. Create page component in `src/pages/`
2. Export from `src/pages/index.ts`
3. Add route in `src/App.tsx`

### Adding New API Endpoints

1. Add types to `src/api/types.ts`
2. Create endpoint file in `src/api/`
3. Export from `src/api/index.ts`

### Tenant-Aware Styling

Use CSS variables set by tenant branding:

```css
.my-element {
  color: var(--tenant-primary);
  background: var(--tenant-secondary);
}
```

Or utility classes:

```jsx
<div className="tenant-primary tenant-bg-secondary">
  Branded content
</div>
```

## Testing Checklist

### 1. Bootstrap Works
- [ ] App loads without errors
- [ ] Tenant name appears in navbar
- [ ] Page title updates to tenant name
- [ ] Browser console shows no errors

### 2. Login Works
- [ ] Navigate to /login
- [ ] Enter valid credentials
- [ ] Form submits without error
- [ ] Redirects to home on success
- [ ] User name appears in navbar
- [ ] Logout button appears

### 3. Listings Load
- [ ] Navigate to /listings
- [ ] Loading spinner appears
- [ ] Listings grid populates
- [ ] Filter tabs work (All/Offers/Requests)
- [ ] Load more button works

### 4. Routing Works
- [ ] Home page loads at /
- [ ] Login page loads at /login
- [ ] Listings page loads at /listings
- [ ] 404 page shows for unknown routes
- [ ] Navigation links work

## Troubleshooting

### CORS Errors

If you see CORS errors in dev:
1. Ensure PHP backend has CORS configured for `http://localhost:5173`
2. Check `src/Core/CorsHelper.php` whitelist

### Tenant Not Found

If bootstrap returns 404:
1. Verify `VITE_TENANT_SLUG` matches a valid tenant slug
2. Check the tenant exists in the database
3. Ensure `/api/v2/tenant/bootstrap` route is registered

### Auth Token Issues

If API calls return 401:
1. Check token is stored in localStorage
2. Verify token hasn't expired
3. Try logging out and back in

## Scripts

| Command | Description |
|---------|-------------|
| `npm run dev` | Start dev server on port 5173 |
| `npm run build` | Build for production |
| `npm run preview` | Preview production build |
| `npm run lint` | Run ESLint |

## License

Proprietary - Project NEXUS
