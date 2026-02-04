# Authentication & 2FA Manual Test Plan

This document contains 6 manual tests to verify the React frontend authentication flow.

## Prerequisites

- React dev server running (`cd react-frontend && npm run dev`)
- Backend running at `http://staging.timebank.local`
- Test user with 2FA enabled
- Test user without 2FA enabled
- Browser dev tools open (Network tab)

## Test Environment

| Variable | Value |
|----------|-------|
| React URL | `http://localhost:5173` |
| API URL | `http://staging.timebank.local` |
| Tenant ID | 2 (hour-timebank) |

---

## Test 1: Successful Login (No 2FA)

**Purpose**: Verify login works for users without 2FA enabled.

### Steps

1. Navigate to `http://localhost:5173/login`
2. Enter email of user **without** 2FA enabled
3. Enter correct password
4. Click "Sign In"

### Expected Results

- [ ] Loading spinner appears on button during request
- [ ] Network tab shows `POST /api/auth/login` returning 200
- [ ] Response contains `success: true` and `access_token`
- [ ] User is redirected to home page `/`
- [ ] Browser localStorage contains `nexus_access_token` and `nexus_refresh_token`
- [ ] Browser localStorage contains `nexus_user` with user data

### Verification Commands

```javascript
// Run in browser console
console.log('Access Token:', localStorage.getItem('nexus_access_token')?.substring(0, 20) + '...');
console.log('Refresh Token:', localStorage.getItem('nexus_refresh_token')?.substring(0, 20) + '...');
console.log('User:', JSON.parse(localStorage.getItem('nexus_user')));
```

---

## Test 2: Successful Login with 2FA (TOTP)

**Purpose**: Verify full 2FA login flow with authenticator app.

### Steps

1. Navigate to `http://localhost:5173/login`
2. Enter email of user **with** 2FA enabled
3. Enter correct password
4. Click "Sign In"
5. **2FA form should appear**
6. Open authenticator app (Google Authenticator, Authy, etc.)
7. Enter the 6-digit TOTP code
8. Click "Verify"

### Expected Results

- [ ] After login submit: shows 2FA form with lock icon
- [ ] 2FA form displays user's first name
- [ ] Network tab shows `POST /api/auth/login` returning 200 with `requires_2fa: true`
- [ ] After 2FA submit: `POST /api/totp/verify` returns 200 with tokens
- [ ] User is redirected to home page `/`
- [ ] All tokens are stored in localStorage

### Key Fields to Verify

```javascript
// After login step, AuthContext should have:
{
  status: 'requires_2fa',
  twoFactorChallenge: {
    two_factor_token: '...',  // 128-char hex string
    user: { first_name: '...', email_masked: 'j*****@...' },
    methods: ['totp', 'backup_code']
  }
}
```

---

## Test 3: 2FA with Backup Code

**Purpose**: Verify backup code authentication works.

### Steps

1. Navigate to `http://localhost:5173/login`
2. Login with 2FA-enabled user (email + password)
3. On 2FA form, check "Use backup code instead"
4. Enter one of the user's backup codes
5. Click "Verify"

### Expected Results

- [ ] Input field label changes to "Backup Code"
- [ ] Input placeholder changes to "XXXX-XXXX-XXXX"
- [ ] Input accepts alphanumeric characters (not just digits)
- [ ] Network tab shows `POST /api/totp/verify` with `use_backup_code: true`
- [ ] Response contains `codes_remaining` field
- [ ] User is redirected to home page

### Note

Backup codes are single-use. After this test, the used code will be invalid.

---

## Test 4: Invalid 2FA Code

**Purpose**: Verify error handling for wrong TOTP codes.

### Steps

1. Navigate to `http://localhost:5173/login`
2. Login with 2FA-enabled user
3. On 2FA form, enter an **incorrect** 6-digit code (e.g., `000000`)
4. Click "Verify"

### Expected Results

- [ ] Error message appears: "Invalid code" or similar
- [ ] User remains on 2FA form (not redirected)
- [ ] Network tab shows `POST /api/totp/verify` returning 401
- [ ] Response contains `code: 'AUTH_2FA_INVALID'`
- [ ] Input field is NOT cleared (user can correct the code)
- [ ] User can retry up to 5 times

---

## Test 5: 2FA Session Expiration

**Purpose**: Verify handling when 2FA challenge token expires.

### Steps

1. Navigate to `http://localhost:5173/login`
2. Login with 2FA-enabled user
3. On 2FA form, **wait more than 5 minutes** without entering code
4. Enter a valid 6-digit code
5. Click "Verify"

### Expected Results

- [ ] Error message: "2FA session expired. Please log in again."
- [ ] User is returned to login form (not 2FA form)
- [ ] Network tab shows `POST /api/totp/verify` returning 401
- [ ] Response contains `code: 'AUTH_2FA_TOKEN_EXPIRED'`

### Alternative Test (Faster)

If you can't wait 5 minutes:
1. Complete step 1-2 above
2. In browser console, run:
   ```javascript
   // Corrupt the 2FA token to simulate expiration
   window.dispatchEvent(new CustomEvent('nexus:session-expired'));
   ```
3. Verify user is returned to login form with error

---

## Test 6: Session Expired Event (Token Refresh Failure)

**Purpose**: Verify the app handles session expiration gracefully.

### Steps

1. Login successfully (with or without 2FA)
2. Verify you're on the home page
3. In browser console, simulate session expiration:
   ```javascript
   // Clear tokens to simulate expired session
   localStorage.removeItem('nexus_access_token');
   localStorage.removeItem('nexus_refresh_token');

   // Dispatch session expired event
   window.dispatchEvent(new CustomEvent('nexus:session-expired'));
   ```
4. Observe the app behavior

### Expected Results

- [ ] User data is cleared from localStorage
- [ ] AuthContext status changes to 'error'
- [ ] Error message displayed: "Session expired. Please log in again."
- [ ] User is redirected to login page (or shown login form)
- [ ] No tokens remain in localStorage

### Verification

```javascript
// After session expired event
console.log('Access Token:', localStorage.getItem('nexus_access_token')); // null
console.log('User:', localStorage.getItem('nexus_user')); // null
```

---

## Troubleshooting

### CORS Errors

If you see CORS errors in the Network tab:
1. Check that `http://localhost:5173` is in `CorsHelper.php` whitelist
2. Verify the backend is responding to OPTIONS preflight requests

### 500 Errors on Login

1. Check the endpoint is `/api/auth/login` (not `/api/v2/auth/login`)
2. Verify the backend is running
3. Check PHP error logs

### 2FA Form Not Appearing

1. Verify the user has 2FA enabled in the database (`users.totp_enabled = 1`)
2. Check Network tab for `requires_2fa: true` in login response

### Tokens Not Stored

1. Verify no JavaScript errors in console
2. Check that response contains `access_token` field
3. Verify localStorage is not disabled in browser

---

## Test Results Template

| Test | Date | Tester | Result | Notes |
|------|------|--------|--------|-------|
| 1. Login (no 2FA) | | | PASS/FAIL | |
| 2. Login with 2FA | | | PASS/FAIL | |
| 3. Backup code | | | PASS/FAIL | |
| 4. Invalid code | | | PASS/FAIL | |
| 5. 2FA expiration | | | PASS/FAIL | |
| 6. Session expired | | | PASS/FAIL | |
