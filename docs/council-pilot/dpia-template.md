# Data Protection Impact Assessment (DPIA) Template

**Document ID:** NEXUS-DPIA-001
**Version:** 1.0
**Date:** February 2026
**Classification:** OFFICIAL
**Status:** TEMPLATE — Requires organisation-specific completion

---

## Document Control

| Field | Value |
|-------|-------|
| Organisation | [ORGANISATION NAME] |
| Project Name | Project NEXUS Timebanking Pilot |
| DPIA Owner | [NAME, ROLE] |
| Data Protection Officer | [DPO NAME] |
| Date Prepared | [DATE] |
| Review Date | [DATE + 12 months] |
| Version | 1.0 |

### Approval

| Role | Name | Signature | Date |
|------|------|-----------|------|
| DPIA Owner | | | |
| Data Protection Officer | | | |
| Senior Information Risk Owner (SIRO) | | | |

---

## 1. Project Overview

### 1.1 Project Description

Project NEXUS is an open-source community timebanking platform that enables residents to exchange services using time credits. The pilot will deploy NEXUS to support [DESCRIBE PILOT SCOPE, e.g., "community volunteering coordination in [AREA]"].

### 1.2 Pilot Scope

| Aspect | Detail |
|--------|--------|
| Pilot Duration | [START DATE] to [END DATE] |
| Target Users | [NUMBER] residents/staff |
| Geographic Area | [AREA/BOROUGH] |
| Services Offered | Timebanking, community events, volunteering |

### 1.3 Why a DPIA is Required

This DPIA is conducted because the processing:
- [ ] Involves personal data of potentially vulnerable individuals
- [ ] Is a new technology implementation for the organisation
- [ ] Involves systematic monitoring of individuals (activity tracking)
- [ ] Processes data on a large scale (or will scale post-pilot)
- [ ] Combines datasets in new ways

### 1.4 Processing Not Covered

This DPIA does not cover:
- Processing by third-party integrations not enabled in the pilot
- Processing that occurs after data export to other council systems
- Staff HR data processed through separate council systems

---

## 2. Data Processing Details

### 2.1 Categories of Personal Data

| Category | Data Elements | Lawful Basis | Retention |
|----------|---------------|--------------|-----------|
| **Identity** | Name, profile photo | Consent / Legitimate Interest | Account lifetime + 30 days |
| **Contact** | Email address, phone (optional) | Consent | Account lifetime + 30 days |
| **Account** | Username, hashed password, role | Contract (service provision) | Account lifetime + 30 days |
| **Profile** | Bio, skills, interests, location (area only) | Consent | Account lifetime + 30 days |
| **Activity** | Service listings, transactions, event RSVPs | Contract / Legitimate Interest | Account lifetime + 1 year |
| **Communications** | Private messages (user-to-user) | Consent | 2 years or until deleted |
| **Technical** | IP address, browser type, session data | Legitimate Interest | 90 days |
| **Audit** | Admin actions, login attempts, consent records | Legal Obligation / Legitimate Interest | 7 years |

### 2.2 Special Category Data

| Type | Collected? | Justification |
|------|------------|---------------|
| Racial/ethnic origin | No | Not required for service |
| Political opinions | No | Not required for service |
| Religious beliefs | No | Not required for service |
| Trade union membership | No | Not required for service |
| Genetic data | No | Not required for service |
| Biometric data | No | Not required for service |
| Health data | No* | Not required for service |
| Sexual orientation | No | Not required for service |

*Note: Users may voluntarily disclose health-related information in free-text fields (e.g., accessibility needs). The system does not require or systematically process this data.

### 2.3 Data Subjects

| Category | Estimated Number | Vulnerability Considerations |
|----------|------------------|------------------------------|
| Adult residents | [NUMBER] | General population |
| Council staff (admin) | [NUMBER] | None specific |
| Volunteers | [NUMBER] | May include vulnerable adults |
| Young people (16-17) | [NUMBER] | Enhanced safeguarding applies |

**Note:** Users under 16 are not permitted. Age verification is via self-declaration at registration.

---

## 3. Lawful Basis Analysis

### 3.1 Primary Lawful Bases

| Processing Activity | Lawful Basis | Article | Justification |
|---------------------|--------------|---------|---------------|
| Account creation | Consent (6(1)(a)) | 6(1)(a) | User actively registers and agrees to terms |
| Service provision | Contract (6(1)(b)) | 6(1)(b) | Necessary to provide timebanking service |
| Transaction records | Contract (6(1)(b)) | 6(1)(b) | Core function of timebanking |
| Security logging | Legitimate Interest (6(1)(f)) | 6(1)(f) | Fraud prevention, security |
| Email notifications | Consent (6(1)(a)) | 6(1)(a) | User opts in to communications |
| Analytics (anonymised) | Legitimate Interest (6(1)(f)) | 6(1)(f) | Service improvement |
| Audit trail | Legal Obligation (6(1)(c)) | 6(1)(c) | Accountability requirements |

### 3.2 Legitimate Interest Assessment (LIA)

**Purpose:** Security logging and fraud prevention

**Legitimate Interest:** The organisation has a legitimate interest in:
- Preventing fraudulent use of the platform
- Detecting and responding to security incidents
- Maintaining service integrity

**Necessity:** The processing is necessary because:
- Real-time security monitoring cannot rely on consent (would defeat purpose)
- Audit trails are required for accountability
- Less intrusive alternatives would not achieve the security objective

**Balancing Test:**
- Data subjects expect security measures on online services
- Processing is limited to technical data (IP, timestamps)
- Data is not shared externally except for legal requirements
- Retention is limited (90 days for technical logs)
- Data subjects can request access to their logs

**Conclusion:** The legitimate interests of the organisation are not overridden by the interests or rights of data subjects.

---

## 4. Data Flows

### 4.1 Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         DATA SUBJECT                                │
│                    (Resident/Volunteer)                             │
└─────────────────────────────────────┬───────────────────────────────┘
                                      │
                                      │ Registration, profile,
                                      │ service listings, messages
                                      ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      PROJECT NEXUS APPLICATION                      │
│                                                                     │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐             │
│  │   Web UI    │───▶│  PHP App    │───▶│   MySQL     │             │
│  │  (Browser)  │    │  Server     │    │  Database   │             │
│  └─────────────┘    └─────────────┘    └─────────────┘             │
│                            │                  │                     │
│                            │                  │ Encrypted backups   │
│                            ▼                  ▼                     │
│                     ┌─────────────┐    ┌─────────────┐             │
│                     │   Redis     │    │  Backup     │             │
│                     │  (Sessions) │    │  Storage    │             │
│                     └─────────────┘    └─────────────┘             │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
                                      │
                                      │ Transactional emails only
                                      ▼
                        ┌─────────────────────────┐
                        │     Email Service       │
                        │   (SMTP/Gmail API)      │
                        │   [PROVIDER NAME]       │
                        └─────────────────────────┘
```

### 4.2 Data Locations

| Data Type | Storage Location | Encryption | Access Control |
|-----------|------------------|------------|----------------|
| Application data | MySQL database | At rest (TDE) | Application + DB credentials |
| Session data | Redis | In transit (TLS) | Application only |
| Uploaded files | Server filesystem | At rest (disk encryption) | Application only |
| Backups | [BACKUP LOCATION] | AES-256 | Restricted admin |
| Audit logs | MySQL + export | At rest | Admin only |

### 4.3 International Transfers

| Transfer | Destination | Safeguard |
|----------|-------------|-----------|
| Primary hosting | UK | N/A (domestic) |
| Backup storage | [LOCATION] | [SAFEGUARD IF NON-UK] |
| Email service | [LOCATION] | [SAFEGUARD IF NON-UK] |

**Note:** For UK-only hosting, no international transfer safeguards required. If using cloud services with potential non-UK processing, ensure appropriate safeguards (UK GDPR adequacy decision, SCCs, or binding corporate rules).

---

## 5. Risk Assessment

### 5.1 Risk Matrix

| Likelihood | Impact | Risk Level |
|------------|--------|------------|
| Rare (1) | Minimal (1) | Low (1-2) |
| Unlikely (2) | Minor (2) | Low (3-4) |
| Possible (3) | Moderate (3) | Medium (5-9) |
| Likely (4) | Significant (4) | High (10-12) |
| Almost Certain (5) | Severe (5) | Critical (13-25) |

### 5.2 Identified Risks

| ID | Risk Description | Likelihood | Impact | Risk Score | Mitigation |
|----|------------------|------------|--------|------------|------------|
| R1 | Unauthorised access to user accounts | 2 | 3 | 6 (Medium) | Strong passwords, rate limiting, session management, TOTP-based 2FA |
| R2 | Data breach via SQL injection | 1 | 5 | 5 (Medium) | Prepared statements throughout, WAF |
| R3 | Loss of data availability | 2 | 3 | 6 (Medium) | Daily backups, tested restore procedure |
| R4 | Excessive data collection | 2 | 2 | 4 (Low) | Data minimisation review, optional fields clearly marked |
| R5 | Failure to respond to DSR | 2 | 4 | 8 (Medium) | Automated DSR tools, documented procedures |
| R6 | Unauthorised admin access | 2 | 4 | 8 (Medium) | RBAC, admin audit logging, IP restrictions |
| R7 | Third-party service compromise | 2 | 3 | 6 (Medium) | Minimal third-party dependencies, contract review |
| R8 | User-to-user harassment via messages | 3 | 2 | 6 (Medium) | Reporting tools, moderation capabilities, terms of use |
| R9 | Retention beyond necessity | 2 | 2 | 4 (Low) | Automated retention policies, annual review |
| R10 | Insider threat (admin misuse) | 1 | 4 | 4 (Low) | Audit logging, principle of least privilege, background checks |

### 5.3 Residual Risk Assessment

After mitigations, overall residual risk: **MEDIUM**

Acceptable for pilot with:
- Active monitoring
- Incident response procedures
- Regular review schedule

---

## 6. Data Subject Rights

### 6.1 Rights Facilitation

| Right | Supported | Mechanism |
|-------|-----------|-----------|
| Right of access (Article 15) | Yes | Self-service data export, DSR process |
| Right to rectification (Article 16) | Yes | Self-service profile editing, DSR process |
| Right to erasure (Article 17) | Yes | Account deletion feature, DSR process |
| Right to restriction (Article 18) | Yes | Account suspension, DSR process |
| Right to portability (Article 20) | Yes | JSON/CSV export, DSR process |
| Right to object (Article 21) | Yes | Opt-out mechanisms, DSR process |
| Rights re: automated decisions (Article 22) | N/A | No automated decision-making with legal effects |

### 6.2 DSR Process

1. **Receipt:** DSR received via email/form to [DPO EMAIL]
2. **Verification:** Identity verification within 3 days
3. **Processing:** Request processed within 25 days
4. **Response:** Response provided within 30 days (statutory deadline)
5. **Record:** DSR logged in register

**Technical Support:** NEXUS includes GDPR tooling for:
- Automated data export (JSON, HTML, ZIP)
- Account anonymisation
- Consent record retrieval

---

## 7. Security Measures

### 7.1 Technical Measures

| Control | Implementation | Status |
|---------|----------------|--------|
| Encryption in transit | TLS 1.2+ required | Implemented |
| Encryption at rest | Database TDE, disk encryption | [TO CONFIRM] |
| Access control | RBAC with principle of least privilege | Implemented |
| Authentication | Password hashing (bcrypt), session management | Implemented |
| Multi-factor authentication | TOTP-based 2FA with authenticator apps | Implemented |
| Audit logging | Comprehensive admin and security logging | Implemented |
| Backup encryption | AES-256 encrypted backups | [TO CONFIRM] |
| Vulnerability management | An independent penetration test must be completed before go-live with real users | [TO SCHEDULE] |

### 7.2 Organisational Measures

| Control | Implementation | Status |
|---------|----------------|--------|
| Staff training | Data protection awareness for admins | [TO COMPLETE] |
| Access management | Documented joiner/leaver process | [TO DOCUMENT] |
| Incident response | Documented procedure, contact list | [TO DOCUMENT] |
| Supplier management | DPA with any sub-processors | [TO REVIEW] |
| Policy framework | Acceptable use, privacy notice | [TO FINALISE] |

---

## 8. Retention Schedule

| Data Category | Retention Period | Trigger | Disposal Method |
|---------------|------------------|---------|-----------------|
| Active user accounts | Indefinite while active | N/A | N/A |
| Inactive accounts | 2 years inactivity | Last login date | Anonymisation |
| Deleted accounts | 30 days post-deletion | Deletion request | Permanent deletion |
| Transaction records | Account lifetime + 1 year | Account deletion | Anonymisation |
| Private messages | 2 years or user deletion | Message date / deletion | Permanent deletion |
| Audit logs | 7 years | Log date | Secure deletion |
| Technical logs | 90 days | Log date | Automatic purge |
| Backups | 30 days rolling | Backup date | Secure overwrite |

---

## 9. Consultation

### 9.1 Internal Consultation

| Stakeholder | Date Consulted | Key Feedback | Resolution |
|-------------|----------------|--------------|------------|
| IT Security | [DATE] | [FEEDBACK] | [ACTION] |
| Legal | [DATE] | [FEEDBACK] | [ACTION] |
| Service Owner | [DATE] | [FEEDBACK] | [ACTION] |
| DPO | [DATE] | [FEEDBACK] | [ACTION] |

### 9.2 Data Subject Consultation

| Method | Date | Outcome |
|--------|------|---------|
| Privacy notice review | [DATE] | Clear, accessible language confirmed |
| User testing | [DATE] | Consent flows understood by users |
| Feedback mechanism | Ongoing | In-app feedback form available |

---

## 10. DPO Advice

### 10.1 DPO Opinion

[TO BE COMPLETED BY DPO]

The Data Protection Officer has reviewed this DPIA and provides the following opinion:

**Overall Assessment:** [SATISFACTORY / REQUIRES CHANGES / NOT RECOMMENDED]

**Key Observations:**
1. [OBSERVATION 1]
2. [OBSERVATION 2]
3. [OBSERVATION 3]

**Conditions/Recommendations:**
1. [CONDITION 1]
2. [CONDITION 2]
3. [CONDITION 3]

**Sign-off:**

| | |
|---|---|
| DPO Name | [NAME] |
| Date | [DATE] |
| Signature | |

---

## 11. Review and Monitoring

### 11.1 Review Schedule

| Review Type | Frequency | Responsibility |
|-------------|-----------|----------------|
| DPIA review | Annual or on significant change | DPIA Owner |
| Security review | Annual | IT Security |
| Access review | Quarterly | System Admin |
| Incident review | Per incident | DPO / SIRO |

### 11.2 Change Triggers

This DPIA must be reviewed if:
- Processing scope changes significantly
- New data categories are collected
- New third-party processors are engaged
- Security incident occurs
- Regulatory guidance changes
- Technology platform changes

---

## 12. Action Plan

| ID | Action | Owner | Due Date | Status |
|----|--------|-------|----------|--------|
| A1 | Complete penetration test | IT Security | [DATE] | Not Started |
| A2 | Finalise privacy notice | DPO | [DATE] | Not Started |
| A3 | Staff training delivery | HR / DPO | [DATE] | Not Started |
| A4 | Incident response procedure | IT Security | [DATE] | Not Started |
| A5 | Backup encryption confirmation | IT Ops | [DATE] | Not Started |
| A6 | Configure 2FA enforcement policy | IT Admin | [DATE] | Not Started |
| A7 | Third-party DPA review | Legal | [DATE] | Not Started |

---

## Appendices

### Appendix A: Privacy Notice

[LINK OR ATTACH PRIVACY NOTICE]

### Appendix B: Data Processing Agreement Template

[LINK OR ATTACH DPA TEMPLATE FOR SUB-PROCESSORS]

### Appendix C: DSR Procedure

[LINK OR ATTACH DETAILED DSR PROCEDURE]

### Appendix D: Incident Response Procedure

[LINK OR ATTACH INCIDENT RESPONSE PROCEDURE]

---

## Version History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 0.1 | [DATE] | [NAME] | Initial draft |
| 1.0 | February 2026 | [NAME] | Template release |
