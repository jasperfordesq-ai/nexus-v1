# Legal Documents Versioning System

## Overview

A complete version-controlled legal documents system for Terms of Service, Privacy Policy, and other legal documents with GDPR-compliant user acceptance tracking.

**Created:** January 25, 2026
**Purpose:** Insurance compliance, GDPR compliance, legal document version control

---

## Features

### 1. Document Version Control
- Full version history for each legal document
- Semantic versioning (1.0, 2.0, 2.1, etc.)
- Draft and published states
- Version labels (e.g., "January 2026 Insurance Update")
- Summary of changes for each version

### 2. User Acceptance Tracking
- Records when users accept each document version
- Tracks IP address, user agent, acceptance method
- Identifies users with outdated acceptances
- Export acceptance records for compliance audits

### 3. Admin Interface
- Create and manage legal documents
- Create new versions with WYSIWYG editor
- View version history and comparisons
- Compliance dashboard with acceptance statistics
- Export acceptance records as CSV

### 4. Public Display
- Version badge on public legal pages
- Version history archive
- In-page acceptance for logged-in users

---

## Database Schema

### Tables Created

1. **`legal_documents`** - Master table for legal document types
   - Stores document type, title, slug
   - Links to current active version
   - Tracks if acceptance is required

2. **`legal_document_versions`** - Version history
   - Full content for each version
   - Effective date, published date
   - Draft/published status
   - Summary of changes

3. **`user_legal_acceptances`** - GDPR compliance tracking
   - Links user to specific document version
   - Timestamp, IP, user agent
   - Acceptance method (registration, login, settings)

---

## File Structure

### Services
```
src/Services/LegalDocumentService.php
```

### Controllers
```
src/Controllers/LegalDocumentController.php         # Public pages + API
src/Controllers/Admin/LegalDocumentsController.php  # Admin interface
```

### Views
```
views/legal/show.php                                # Public document view
views/admin/legal-documents/index.php               # Dispatcher
views/admin/legal-documents/show.php                # Dispatcher
views/admin/legal-documents/versions/create.php     # Dispatcher
views/modern/admin/legal-documents/index.php        # Document list
views/modern/admin/legal-documents/show.php         # Document detail + versions
views/modern/admin/legal-documents/versions/create.php  # Create version form
```

### Migrations
```
migrations/2026_01_25_create_legal_documents_system.sql   # Schema
migrations/2026_01_25_seed_legal_documents_content.php    # Initial content
```

---

## Routes

### Public Routes
| Method | URL | Description |
|--------|-----|-------------|
| GET | `/terms` | Terms of Service (uses versioned system) |
| GET | `/privacy` | Privacy Policy (uses versioned system) |
| GET | `/cookies` | Cookie Policy |
| GET | `/accessibility` | Accessibility Statement |

### API Routes
| Method | URL | Description |
|--------|-----|-------------|
| POST | `/api/legal/accept` | Accept a specific document version |
| POST | `/api/legal/accept-all` | Accept all pending documents |
| GET | `/api/legal/status` | Get user's acceptance status |

### Admin Routes
| Method | URL | Description |
|--------|-----|-------------|
| GET | `/admin/legal-documents` | List all documents |
| GET | `/admin/legal-documents/create` | Create new document |
| GET | `/admin/legal-documents/{id}` | View document + versions |
| GET | `/admin/legal-documents/{id}/edit` | Edit document settings |
| GET | `/admin/legal-documents/{id}/versions/create` | Create new version |
| GET | `/admin/legal-documents/{id}/versions/{versionId}` | View version |
| POST | `/admin/legal-documents/{id}/versions/{versionId}/publish` | Publish version |
| GET | `/admin/legal-documents/{id}/versions/{versionId}/acceptances` | View acceptances |
| GET | `/admin/legal-documents/{id}/export` | Export acceptance CSV |
| GET | `/admin/legal-documents/compliance` | Compliance dashboard |

---

## Usage

### 1. Run Migrations

```bash
# Create database tables
mysql -u root your_database < migrations/2026_01_25_create_legal_documents_system.sql

# Seed initial content (Terms v2.0 and Privacy v1.0)
php migrations/2026_01_25_seed_legal_documents_content.php
```

### 2. Access Admin Interface

Navigate to `/admin/legal-documents` to:
- View all legal documents
- Create new versions
- View acceptance statistics
- Export compliance records

### 3. Public Pages

The system automatically serves versioned content when available:
- `/terms` - Shows Terms v2.0 with version badge
- `/privacy` - Shows Privacy v1.0 with version badge

If no versioned content exists, falls back to legacy file-based templates.

---

## API Usage

### Check User Acceptance Status
```javascript
fetch('/api/legal/status')
  .then(r => r.json())
  .then(data => {
    if (data.has_pending) {
      // User needs to accept updated documents
    }
  });
```

### Accept a Document
```javascript
fetch('/api/legal/accept', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    document_id: 1,
    version_id: 5
  })
});
```

### Accept All Pending
```javascript
fetch('/api/legal/accept-all', { method: 'POST' })
  .then(r => r.json())
  .then(data => {
    console.log(`Accepted: ${data.accepted.length} documents`);
  });
```

---

## Service Methods

### LegalDocumentService

```php
// Get document by type
$doc = LegalDocumentService::getByType('terms');

// Check if user has accepted current version
$hasAccepted = LegalDocumentService::hasAcceptedCurrent($userId, 'terms');

// Get documents requiring acceptance
$pending = LegalDocumentService::getDocumentsRequiringAcceptance($userId);

// Record acceptance
LegalDocumentService::recordAcceptanceFromRequest($userId, $docId, $versionId, 'registration');

// Get compliance summary
$stats = LegalDocumentService::getComplianceSummary($tenantId);

// Export acceptance records
$records = LegalDocumentService::exportAcceptanceRecords($docId, $startDate, $endDate);
```

---

## Insurance Compliance

This system was built to address insurance company requirements:

1. **Version Tracking** - Every version is timestamped and identified
2. **Acceptance Proof** - Records who accepted what, when, and from where
3. **Audit Trail** - Full history for regulatory review
4. **Export Capability** - CSV exports for compliance audits
5. **Change Documentation** - Summary of changes for each version

---

## GDPR Compliance

- Tracks explicit consent for legal documents
- Records timestamp and method of acceptance
- Allows users to see their acceptance history
- Supports right to access (exportable records)
- Notifies users when documents are updated

---

## Future Enhancements

- [ ] Email notifications when documents are updated
- [ ] Forced re-acceptance workflow for critical updates
- [ ] Version comparison (diff view)
- [ ] WYSIWYG editor integration
- [ ] Scheduled version publishing
- [ ] Multi-language document support
