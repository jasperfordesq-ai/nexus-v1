# Federation Coverage Snapshot

This is a dated verification record for federation tests. Treat it as a snapshot, not as a live dashboard.

Last verified: 2026-04-12.

Command used:

```bash
docker exec nexus-php-app vendor/bin/phpunit \
  --filter 'TwoWayFlow|KomunitinProtocol|CreditCommonsProtocol|NativeNexus|TimeOverflow|CrossProtocol'
```

Result: 118 tests, 171 assertions, 93 passed, 25 incomplete, 1 skipped, 0 failed.

## Matrix

| Entity | Nexus out | Nexus in | Komunitin out | Komunitin in | Credit Commons out | Credit Commons in | TimeOverflow out | TimeOverflow in |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Listing | OK | Incomplete [1] | OK | Incomplete [1] | OK | Incomplete [1] | OK | Incomplete [1] |
| Message | OK | OK | OK | OK | OK | OK | OK | OK |
| Transaction | OK | OK | OK | OK | OK | OK | OK | OK |
| Review | OK | Incomplete [2] | OK | Incomplete [2] | OK | Incomplete [2] | OK | Incomplete [2] |
| Community event | OK | Incomplete [3] | OK | Incomplete [3] | OK | Incomplete [3] | OK | Incomplete [3] |
| Group | OK | Incomplete [4] | OK | Incomplete [4] | OK | Incomplete [4] | OK | Incomplete [4] |
| Connection | OK | Incomplete [5] | OK | Incomplete [5] | OK | Incomplete [5] | OK | Incomplete [5] |
| Volunteering | OK | Incomplete [6] | OK | Incomplete [6] | OK | Incomplete [6] | OK | Incomplete [6] |
| Member | OK | OK | OK | OK | OK | OK | OK | OK |

## Incomplete Cells

The incomplete cells are inbound webhook handlers. The protocol adapters normalize the wire payload, but `FederationExternalWebhookController::handleEvent()` did not yet branch for these event types at the time of this snapshot:

- [1] `listing.created`
- [2] `review.created`
- [3] `event.created`
- [4] `group.created`
- [5] `connection.requested`
- [6] `volunteering.created`

## Protocol Tests

| Test | Snapshot status |
| --- | --- |
| `KomunitinProtocolTest` | Passing |
| `CreditCommonsProtocolTest` | 4 of 5 passing; hashchain validation incomplete |
| `NativeNexusProtocolTest` | Passing |
| `TimeOverflowProtocolTest` | Passing |
| `CrossProtocolRegressionTest` | Passing |

## Outbound Listener Coverage

The snapshot verified these outbound listeners:

- `PushListingToFederatedPartners`
- `PushMessageToFederatedPartner`
- `PushTransactionToFederatedPartner`
- `PushReviewToFederatedPartner`
- `PushCommunityEventToFederatedPartners`
- `PushGroupToFederatedPartners`
- `PushConnectionAcceptedToFederatedPartner`
- `PushVolunteerOpportunityToFederatedPartners`
- `PushMemberProfileUpdateToFederatedPartners`
