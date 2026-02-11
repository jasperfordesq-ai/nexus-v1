# Mobile UX & HeroUI Improvement Plan

## Overview
This document outlines the implementation plan to improve mobile experience (current score: 7.6/10) and fully utilize HeroUI components (current usage: ~30%).

**Target Score:** 9/10 mobile experience
**Estimated Total Time:** 12-16 hours

---

## Phase 1: Quick Wins (2-3 hours)
*High impact, low effort changes*

### 1.1 Add `isLoading` to Form Submit Buttons
**Files to modify:**
- [ ] `src/pages/auth/LoginPage.tsx`
- [ ] `src/pages/auth/RegisterPage.tsx`
- [ ] `src/pages/auth/ForgotPasswordPage.tsx`
- [ ] `src/pages/listings/CreateListingPage.tsx`
- [ ] `src/pages/events/CreateEventPage.tsx`
- [ ] `src/pages/groups/CreateGroupPage.tsx`
- [ ] `src/pages/exchanges/RequestExchangePage.tsx`

**Change:**
```tsx
// Before
<Button type="submit">Submit</Button>

// After
<Button type="submit" isLoading={isSubmitting}>Submit</Button>
```

### 1.2 Add HeroUI Form Validation Props
**Add to all Input/Textarea/Select components:**
- `errorMessage={errors.fieldName}`
- `isInvalid={!!errors.fieldName}`
- `isRequired` for required fields
- `description` for helper text

**Files:**
- [ ] `src/pages/auth/LoginPage.tsx`
- [ ] `src/pages/auth/RegisterPage.tsx`
- [ ] `src/pages/settings/SettingsPage.tsx`
- [ ] `src/pages/listings/CreateListingPage.tsx`
- [ ] `src/pages/events/CreateEventPage.tsx`
- [ ] `src/pages/groups/CreateGroupPage.tsx`

### 1.3 Fix Dashboard Stats Grid (Priority #1)
**File:** `src/pages/dashboard/DashboardPage.tsx` (Line 144)

**Change:**
```tsx
// Before
className="grid grid-cols-2 lg:grid-cols-4 gap-4"

// After (mobile-first with intermediate breakpoints)
className="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4"
```

### 1.4 Fix Wallet Stats Grid (Priority #3)
**File:** `src/pages/wallet/WalletPage.tsx` (Line ~282)

**Change:**
```tsx
// Before
className="grid grid-cols-3"

// After
className="grid grid-cols-2 sm:grid-cols-3 gap-3"
```

---

## Phase 2: HeroUI Skeleton Loaders (2-3 hours)
*Replace custom loading states with HeroUI Skeleton*

### 2.1 Create Reusable Skeleton Components
**New file:** `src/components/ui/Skeleton.tsx`

```tsx
import { Skeleton } from '@heroui/react';

export function ListingSkeleton() {
  return (
    <div className="p-4 rounded-lg bg-theme-elevated">
      <Skeleton className="h-5 w-3/4 rounded mb-2" />
      <Skeleton className="h-4 w-full rounded mb-2" />
      <Skeleton className="h-4 w-1/2 rounded" />
    </div>
  );
}

export function MemberCardSkeleton() {
  return (
    <div className="p-4 rounded-lg bg-theme-elevated flex items-center gap-4">
      <Skeleton className="h-12 w-12 rounded-full" />
      <div className="flex-1">
        <Skeleton className="h-4 w-24 rounded mb-2" />
        <Skeleton className="h-3 w-32 rounded" />
      </div>
    </div>
  );
}

export function StatCardSkeleton() {
  return (
    <div className="p-4 rounded-lg bg-theme-elevated">
      <Skeleton className="h-10 w-10 rounded-lg mb-3" />
      <Skeleton className="h-3 w-16 rounded mb-2" />
      <Skeleton className="h-8 w-12 rounded" />
    </div>
  );
}
```

### 2.2 Replace Loading States
**Files to update:**
- [ ] `src/pages/dashboard/DashboardPage.tsx` - Use StatCardSkeleton
- [ ] `src/pages/members/MembersPage.tsx` - Use MemberCardSkeleton
- [ ] `src/pages/listings/ListingsPage.tsx` - Use ListingSkeleton
- [ ] `src/pages/messages/MessagesPage.tsx` - Use Skeleton for conversations
- [ ] `src/pages/groups/GroupsPage.tsx` - Use Skeleton for group cards

---

## Phase 3: Mobile Responsive Fixes (3-4 hours)
*Fix breakpoint issues identified in audit*

### 3.1 HomePage Hero Typography (Priority #10)
**File:** `src/pages/public/HomePage.tsx` (Line ~153)

**Change:**
```tsx
// Before
className="text-4xl sm:text-5xl md:text-6xl lg:text-7xl"

// After (smooth scaling)
className="text-3xl sm:text-4xl md:text-5xl lg:text-6xl xl:text-7xl"
```

### 3.2 ListingsPage Filter Bar (Priority #5)
**File:** `src/pages/listings/ListingsPage.tsx` (Lines 171-219)

**Change:**
```tsx
// Make filters stack vertically on mobile
<div className="flex flex-col sm:flex-row gap-3 sm:gap-4">
  {/* Filter buttons */}
</div>
```

### 3.3 ProfilePage Stats Grid (Priority #7)
**File:** `src/pages/profile/ProfilePage.tsx` (Line ~350)

**Change:**
```tsx
// Before
className="grid grid-cols-3 text-xs"

// After
className="grid grid-cols-3 text-sm sm:text-xs gap-2"
```

### 3.4 EventsPage Date Box (Priority #8)
**File:** `src/pages/events/EventsPage.tsx` (Lines 311-322)

**Change:**
```tsx
// Make date box responsive
className="w-14 sm:w-16"
// Date text
className="text-xl sm:text-2xl"
```

### 3.5 Touch Targets - Increase to 44px Minimum
**Files to check:**
- [ ] `src/pages/messages/MessagesPage.tsx` - Search button padding (p-3 → p-4)
- [ ] `src/pages/notifications/NotificationsPage.tsx` - Icon buttons (remove size="sm")
- [ ] `src/pages/listings/CreateListingPage.tsx` - Radio buttons (add min-h-[56px])

---

## Phase 4: HeroUI Badge Component (1-2 hours)
*Replace inline spans with HeroUI Badge*

### 4.1 Add Badge Import
**Files to update:**
```tsx
import { Badge } from '@heroui/react';
```

### 4.2 Replace Inline Count Displays
**MessagesPage - Unread count:**
```tsx
// Before
<span className="text-xs bg-red-500 ...">3</span>

// After
<Badge content={unreadCount} color="danger" size="sm">
  <Avatar ... />
</Badge>
```

**NotificationsPage - Notification badges:**
```tsx
<Badge content={count} color="primary" isInvisible={count === 0}>
  <Bell className="w-5 h-5" />
</Badge>
```

---

## Phase 5: HeroUI Progress Component (1-2 hours)
*Add progress indicators*

### 5.1 Password Strength Indicator
**File:** `src/pages/auth/RegisterPage.tsx`

```tsx
import { Progress } from '@heroui/react';

// Add after password input
<Progress
  value={passwordStrength}
  color={passwordStrength < 50 ? 'danger' : passwordStrength < 75 ? 'warning' : 'success'}
  size="sm"
  className="mt-2"
/>
```

### 5.2 Profile Completion Progress
**File:** `src/pages/dashboard/DashboardPage.tsx`

```tsx
<Progress
  value={75}
  color="primary"
  size="sm"
  label="Level Progress"
  showValueLabel
/>
```

---

## Phase 6: Replace MobileDrawer with HeroUI Drawer (2-3 hours)
*Major refactor - reduces 387 LOC custom code*

### 6.1 Update MobileDrawer Component
**File:** `src/components/layout/MobileDrawer.tsx`

```tsx
import { Drawer, DrawerContent, DrawerHeader, DrawerBody } from '@heroui/react';

export function MobileDrawer({ isOpen, onClose, children }) {
  return (
    <Drawer
      isOpen={isOpen}
      onClose={onClose}
      placement="left"
      size="xs"
      classNames={{
        base: 'bg-theme-card',
        header: 'border-b border-theme-default',
        body: 'p-0',
      }}
    >
      <DrawerContent>
        <DrawerHeader>Menu</DrawerHeader>
        <DrawerBody>
          {children}
        </DrawerBody>
      </DrawerContent>
    </Drawer>
  );
}
```

### 6.2 Update Navbar Integration
**File:** `src/components/layout/Navbar.tsx`

Update to use new simplified MobileDrawer.

---

## Phase 7: Form Improvements (2-3 hours)
*RegisterPage step-by-step form for mobile*

### 7.1 Add Form Steps for Mobile
**File:** `src/pages/auth/RegisterPage.tsx`

```tsx
const [step, setStep] = useState(1);
const totalSteps = 3;

// Step 1: Account Info (email, password)
// Step 2: Profile Info (name, location)
// Step 3: Community Selection

// Mobile: Show one step at a time
// Desktop: Show all steps
```

### 7.2 Add Step Indicator
```tsx
<Progress
  value={(step / totalSteps) * 100}
  size="sm"
  className="mb-6 sm:hidden"
/>
```

---

## Implementation Checklist

### Week 1
- [ ] Phase 1: Quick Wins (all items)
- [ ] Phase 2: Skeleton Loaders
- [ ] Phase 3.1-3.2: Critical responsive fixes

### Week 2
- [ ] Phase 3.3-3.5: Remaining responsive fixes
- [ ] Phase 4: Badge component
- [ ] Phase 5: Progress component

### Week 3
- [ ] Phase 6: MobileDrawer refactor
- [ ] Phase 7: Form improvements
- [ ] Final testing on all mobile viewports

---

## Testing Checklist

### Viewports to Test
- [ ] iPhone SE (375px)
- [ ] iPhone 12/14 (390-430px)
- [ ] iPad Mini (768px)
- [ ] iPad (1024px)
- [ ] Landscape orientations

### Features to Verify
- [ ] All buttons have 44px+ touch targets
- [ ] No horizontal scrolling on any page
- [ ] Forms work with mobile keyboard
- [ ] Modals don't exceed viewport
- [ ] Loading states visible
- [ ] Error states accessible

---

## Success Metrics

| Metric | Current | Target |
|--------|---------|--------|
| Mobile UX Score | 7.6/10 | 9/10 |
| HeroUI Usage | ~30% | ~70% |
| Custom Component LOC | ~1000 | ~500 |
| Touch Target Compliance | 60% | 100% |
| Skeleton Loader Coverage | 0% | 80% |

---

## Notes

- Always test changes on real mobile devices, not just browser DevTools
- HeroUI components include accessibility features by default
- Preserve existing Framer Motion animations where they add value
- Keep GlassCard custom component (provides unique styling)
