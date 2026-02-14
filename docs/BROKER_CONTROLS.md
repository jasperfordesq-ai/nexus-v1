# Broker Controls Suite

## Overview

The Broker Controls suite provides timebank coordinators (brokers) with tools to manage, monitor, and safeguard exchanges within their community. These features are designed for organizations that require oversight of member interactions.

**Admin URL:** `/admin/broker-controls`

## Features

### 1. Direct Messaging Toggle

Control whether members can message each other directly or must go through the structured exchange workflow.

**Configuration:**
- Enable/disable direct messaging for the entire tenant
- When disabled, members must use the Exchange Request system

**Use Case:** Organizations with vulnerable populations may want all member contact to be mediated.

### 2. Risk Tagging for Listings

Brokers can tag listings with risk assessments to flag potential safeguarding concerns.

**Risk Levels:**
- **Critical** - Severe concern, immediate action required
- **High** - Significant concern, broker approval needed
- **Medium** - Moderate concern, review messages
- **Low** - Minor concern, monitor only

**Risk Categories:**
- Safeguarding Concern
- Financial Risk
- Health & Safety
- Legal/Regulatory
- Reputational Risk
- Potential Fraud
- Other

**Features:**
- Tag any listing with risk level and notes
- Optionally require broker approval for exchanges involving tagged listings
- View all tagged listings filtered by risk level
- Remove tags when concerns are resolved

### 3. Structured Exchange Workflow

A multi-step process that tracks exchanges from initial request to completion.

**Exchange States:**

```
pending_provider → pending_broker → accepted → in_progress → pending_confirmation → completed
                         ↓                                           ↓
                    cancelled                                    disputed
```

**Workflow Steps:**
1. **Requester** creates exchange request for a listing
2. **Provider** accepts or declines the request
3. **Broker** (optional) approves or rejects if required
4. **Provider** starts the exchange when work begins
5. **Provider** marks exchange as complete
6. **Both parties** confirm hours worked (dual-party confirmation)
7. **Transaction** is created automatically on agreement

### 4. Dual-Party Confirmation

Both the requester and provider must confirm the hours worked before credits are transferred.

**Features:**
- Each party independently confirms hours
- Hours must match (within tolerance) for automatic completion
- Disputes are flagged when hours don't match
- Brokers can resolve disputes manually

### 5. Broker Message Visibility

Brokers can review copies of messages for compliance and safeguarding.

**Copy Triggers:**
- **First Contact** - First message between two members
- **New Member** - Messages from members joined within X days
- **High Risk Listing** - Messages about risk-tagged listings
- **Flagged User** - Messages from users under monitoring
- **Enhanced Monitoring** - Users with monitoring flag enabled

**Features:**
- Message review queue with unreviewed count
- Mark messages as reviewed
- Flag concerning messages for follow-up
- Filter by copy reason, review status, or flag status

### 6. User Monitoring

Track and manage user messaging privileges.

**Controls:**
- **Messaging Disabled** - User cannot send private messages
- **Enhanced Monitoring** - All user messages copied for broker review

**Statistics:**
- New members (last 30 days)
- First contacts today
- Users with restrictions
- Users under monitoring

## Configuration

Access via `/admin/broker-controls/configuration`

### Messaging Settings
| Setting | Default | Description |
|---------|---------|-------------|
| Direct Messaging | Enabled | Allow members to message directly |
| First Contact Monitoring | Enabled | Copy first messages between members |
| New Member Monitoring Days | 30 | Days to monitor new member messages |

### Risk Tagging Settings
| Setting | Default | Description |
|---------|---------|-------------|
| Risk Tagging | Enabled | Allow brokers to tag listings |
| High Risk Requires Approval | Yes | Require approval for high/critical risk listings |

### Exchange Workflow Settings
| Setting | Default | Description |
|---------|---------|-------------|
| Exchange Workflow | Disabled | Enable structured exchange workflow |
| Require Broker Approval | No | Require broker approval for all exchanges |
| Confirmation Deadline | 72 hours | Time allowed for dual-party confirmation |

### Broker Visibility Settings
| Setting | Default | Description |
|---------|---------|-------------|
| Broker Visibility | Enabled | Enable message copying for brokers |
| Copy First Contact | Yes | Copy first messages between members |

## Database Tables

### exchange_requests
Stores exchange workflow data including status, hours, and confirmation details.

### exchange_history
Audit trail of all exchange state changes.

### listing_risk_tags
Risk assessments for listings.

### broker_message_copies
Copies of messages for broker review.

### user_messaging_restrictions
User-level messaging restrictions and monitoring flags.

### user_first_contacts
Tracks first contacts between members.

## API Endpoints (V2)

### Exchanges
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v2/exchanges/config` | Get exchange configuration |
| GET | `/api/v2/exchanges` | List user's exchanges |
| POST | `/api/v2/exchanges` | Create exchange request |
| GET | `/api/v2/exchanges/{id}` | Get exchange details |
| POST | `/api/v2/exchanges/{id}/accept` | Provider accepts |
| POST | `/api/v2/exchanges/{id}/decline` | Provider declines |
| POST | `/api/v2/exchanges/{id}/start` | Start exchange |
| POST | `/api/v2/exchanges/{id}/complete` | Mark complete |
| POST | `/api/v2/exchanges/{id}/confirm` | Confirm hours |
| DELETE | `/api/v2/exchanges/{id}` | Cancel exchange |

## Services

### BrokerControlConfigService
Central configuration management for all broker control features.

```php
// Check if features are enabled
BrokerControlConfigService::isDirectMessagingEnabled();
BrokerControlConfigService::isExchangeWorkflowEnabled();
BrokerControlConfigService::isRiskTaggingEnabled();
BrokerControlConfigService::isBrokerVisibilityEnabled();
BrokerControlConfigService::requiresBrokerApproval();

// Get/update configuration
$config = BrokerControlConfigService::getConfig();
BrokerControlConfigService::updateConfig($newConfig);
```

### ExchangeWorkflowService
Manages the exchange request lifecycle.

```php
// Create and manage exchanges
$id = ExchangeWorkflowService::createRequest($requesterId, $listingId, $data);
ExchangeWorkflowService::acceptRequest($exchangeId, $providerId);
ExchangeWorkflowService::declineRequest($exchangeId, $providerId, $reason);
ExchangeWorkflowService::startExchange($exchangeId, $providerId);
ExchangeWorkflowService::completeExchange($exchangeId, $providerId);
ExchangeWorkflowService::confirmCompletion($exchangeId, $userId, $hours);

// Broker actions
ExchangeWorkflowService::approveExchange($exchangeId, $brokerId, $notes);
ExchangeWorkflowService::rejectExchange($exchangeId, $brokerId, $reason);

// Queries
$exchange = ExchangeWorkflowService::getExchange($id);
$exchanges = ExchangeWorkflowService::getExchangesForUser($userId);
$pending = ExchangeWorkflowService::getPendingBrokerApprovals();
```

### ListingRiskTagService
Manages risk tags for listings.

```php
// Tag management
$tagId = ListingRiskTagService::tagListing($listingId, $data, $brokerId);
$tag = ListingRiskTagService::getTagForListing($listingId);
ListingRiskTagService::removeTag($listingId);

// Queries
$highRisk = ListingRiskTagService::getHighRiskListings();
$listings = ListingRiskTagService::getListingsByRiskLevel('critical');
ListingRiskTagService::requiresApproval($listingId);
```

### BrokerMessageVisibilityService
Handles message copying and review.

```php
// Check if message should be copied
$reason = BrokerMessageVisibilityService::shouldCopyMessage($senderId, $receiverId);

// Copy and review
$copyId = BrokerMessageVisibilityService::copyMessageForBroker($messageId, $reason);
BrokerMessageVisibilityService::markAsReviewed($copyId, $brokerId);
BrokerMessageVisibilityService::flagMessage($copyId, $brokerId, $reason);

// Queries
$unreviewed = BrokerMessageVisibilityService::getUnreviewedMessages();
$flagged = BrokerMessageVisibilityService::getFlaggedMessages();
$count = BrokerMessageVisibilityService::countUnreviewed();
```

## Admin Navigation

Broker Controls appear in the admin sidebar under **Community** with the following sub-items:

- **Dashboard** - Overview and quick stats
- **Configuration** - Enable/disable features and settings
- **Exchange Requests** - Pending approvals (with badge count)
- **Risk Tags** - View and manage risk-tagged listings
- **Message Review** - Review copied messages (with badge count)
- **User Monitoring** - Manage user restrictions
- **Statistics** - Analytics and metrics

## Badge Counts

The admin sidebar shows live badge counts for:
- **pending_exchanges** - Exchanges awaiting broker approval
- **unreviewed_messages** - Messages pending broker review

These counts are populated by `AdminBadgeCountService`.

## Testing

Run the broker control service tests:

```bash
vendor/bin/phpunit tests/Services/BrokerControlConfigServiceTest.php
vendor/bin/phpunit tests/Services/ExchangeWorkflowServiceTest.php
vendor/bin/phpunit tests/Services/ListingRiskTagServiceTest.php
vendor/bin/phpunit tests/Services/BrokerMessageVisibilityServiceTest.php
```

Or run all broker control tests:

```bash
vendor/bin/phpunit --filter Broker
```

## Migration

Apply the database migration:

```bash
php scripts/safe_migrate.php
```

Or manually run:

```sql
source migrations/2026_02_08_broker_control_features.sql
```

## File Locations

| Component | Path |
|-----------|------|
| Config Service | `src/Services/BrokerControlConfigService.php` |
| Exchange Service | `src/Services/ExchangeWorkflowService.php` |
| Risk Tag Service | `src/Services/ListingRiskTagService.php` |
| Message Service | `src/Services/BrokerMessageVisibilityService.php` |
| Admin Controller | `src/Controllers/Admin/BrokerControlsController.php` |
| API Controller | `src/Controllers/Api/ExchangesApiController.php` |
| Admin Views (Modern PHP) | `views/modern/admin/broker-controls/` (legacy PHP admin panel — being migrated to React) |
| Admin Views (CivicOne PHP) | `views/civicone/admin/broker-controls/` (legacy PHP admin panel — being migrated to React) |
| CSS | `httpdocs/assets/css/admin/broker-controls.css` |
| Migration | `migrations/2026_02_08_broker_control_features.sql` |
| Tests | `tests/Services/*BrokerControl*Test.php` |
