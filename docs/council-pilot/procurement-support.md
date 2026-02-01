# Procurement Note: How to Buy Support for Open Source Software

**Document ID:** NEXUS-PROC-001
**Version:** 1.0
**Date:** February 2026
**Classification:** OFFICIAL

---

## Purpose

This document helps procurement officers and contract managers understand how to acquire professional support services for open-source software like Project NEXUS. It addresses common questions about licensing, support arrangements, and contract structures.

---

## Key Concept: Software vs. Services

### What You're NOT Buying

- The software itself (it's freely available under open-source licence)
- Exclusive rights to the software
- A traditional software licence fee

### What You ARE Buying

- Professional services to deploy, configure, and maintain the software
- Support agreements (SLAs for issue response)
- Training and knowledge transfer
- Security review and hardening
- Custom development (if needed)
- Ongoing maintenance and updates

---

## Understanding Open Source Licensing

### Project NEXUS Licence

[Confirm actual licence — assume MIT or similar permissive licence for this template]

**Typical permissive licence allows:**
- Commercial use
- Modification
- Distribution
- Private use

**Typical requirements:**
- Licence and copyright notice preserved
- No warranty provided

### What This Means for Councils

- **No software licence fee** — The software is free to use
- **No vendor lock-in** — You can change support providers
- **Full code access** — You can inspect, modify, and audit the code
- **Community support available** — GitHub issues, documentation
- **Professional support optional** — Pay only if needed

---

## Support Service Options

### Option 1: Internal IT Delivery

**Description:** Council IT team deploys and maintains the software using available documentation.

**Suitable when:**
- IT team has PHP/MySQL experience
- Lower risk tolerance acceptable
- Budget constraints significant
- Small pilot scope

**Procurement:** No external procurement needed.

**Estimated effort:**
- Initial deployment: 2-5 days
- Ongoing maintenance: 0.5-1 day/month

---

### Option 2: One-Time Implementation Service

**Description:** External supplier deploys and configures the software, hands over to internal IT.

**Suitable when:**
- IT team can maintain but needs help with initial setup
- Time constraints on internal team
- Specific configuration requirements

**Procurement route:**
- Direct award (if below threshold)
- G-Cloud (Digital Marketplace)
- Mini-competition from framework

**Typical deliverables:**
- Server provisioning and configuration
- Application deployment
- Security hardening
- Administrator training
- Handover documentation

**Estimated cost:** £5,000 - £15,000

---

### Option 3: Managed Service Agreement

**Description:** External supplier provides ongoing support and maintenance.

**Suitable when:**
- Limited internal IT capacity
- Higher availability requirements
- Regulatory/compliance obligations

**Procurement route:**
- G-Cloud (Digital Marketplace)
- Crown Commercial Service frameworks
- Local government frameworks (ESPO, YPO, etc.)
- Competitive tender

**Typical service levels:**

| Severity | Response Time | Resolution Target |
|----------|---------------|-------------------|
| Critical (system down) | 1 hour | 4 hours |
| High (major function impaired) | 4 hours | 1 business day |
| Medium (minor function impaired) | 1 business day | 3 business days |
| Low (cosmetic/enhancement) | 3 business days | Next release |

**Estimated cost:** £500 - £2,000/month depending on SLA and scope

---

### Option 4: Development Partner

**Description:** External supplier provides ongoing development, customisation, and strategic support.

**Suitable when:**
- Significant customisation required
- Integration with council systems needed
- Long-term product roadmap involvement

**Procurement route:**
- Competitive tender
- Framework call-off
- Dynamic Purchasing System

**Typical scope:**
- All Option 3 services
- Feature development
- Integration work
- Security assessments
- Performance optimisation

**Estimated cost:** £2,000 - £10,000/month depending on scope

---

## Procurement Routes

### Below Threshold (Currently £214,904 for services)

**Options:**
- Direct award to suitable supplier
- Request for quotations (RFQ) from 3+ suppliers
- Framework call-off

**Considerations:**
- Document rationale for direct award
- Demonstrate value for money
- Consider social value requirements

### G-Cloud (Digital Marketplace)

**Advantages:**
- Pre-competed framework
- Quick procurement route
- Transparent pricing
- Standardised terms

**Process:**
1. Search Digital Marketplace for relevant services
2. Filter by requirements
3. Compare offerings
4. Award to most suitable

**Search terms:** "PHP support", "open source support", "web application hosting", "DevOps services"

### Competitive Tender

**When required:**
- Above threshold values
- Complex requirements
- No suitable framework

**Specification should include:**
- Technical requirements (PHP, MySQL experience)
- Support hours and response times
- Security requirements
- Handover/exit provisions
- Social value considerations

---

## Contract Considerations

### Essential Clauses

**1. Intellectual Property**
```
All customisations and improvements to the software shall be:
(a) Owned by the Council, or
(b) Contributed back to the open-source project under its licence

The Council retains all rights to its data at all times.
```

**2. Exit/Handover Provisions**
```
Upon termination, the Supplier shall:
(a) Provide full documentation of configuration and customisations
(b) Transfer all credentials and access rights
(c) Provide 30 days handover support at no additional cost
(d) Return or destroy Council data as directed
```

**3. Security Requirements**
```
The Supplier shall:
(a) Maintain Cyber Essentials Plus certification (or equivalent)
(b) Notify the Council of security vulnerabilities within 24 hours
(c) Apply critical security patches within 72 hours
(d) Support annual penetration testing
```

**4. Data Protection**
```
The Supplier shall:
(a) Process personal data only on Council instructions
(b) Maintain appropriate technical and organisational measures
(c) Support Council compliance with data subject requests
(d) Notify data breaches within 24 hours
```

### Data Processing Agreement (DPA)

If the supplier will access or process personal data, a DPA is required covering:
- Nature and purpose of processing
- Types of personal data
- Categories of data subjects
- Security measures
- Sub-processor provisions
- Audit rights
- Data return/deletion

---

## Supplier Evaluation Criteria

### Technical Capability

| Criterion | Evidence |
|-----------|----------|
| PHP/MySQL experience | Case studies, certifications |
| Security expertise | Cyber Essentials, penetration testing capability |
| Open-source experience | Contributions, community involvement |
| UK public sector experience | References, framework presence |
| Hosting capability (if applicable) | UK data centres, certifications |

### Commercial Considerations

| Criterion | Evidence |
|-----------|----------|
| Financial stability | Accounts, credit check |
| Insurance | Professional indemnity, public liability |
| Pricing transparency | Clear rate card, no hidden fees |
| Exit terms | Reasonable handover provisions |

### Social Value

Under PPN 06/20, consider:
- Local employment
- Skills development
- Environmental sustainability
- Innovation
- SME and VCSE involvement

---

## Finding Suppliers

### Where to Look

1. **Digital Marketplace (G-Cloud)**
   - Search for PHP, open-source, or web application services

2. **Local Supplier Networks**
   - Council supplier portals
   - Local enterprise partnerships

3. **Open-Source Community**
   - Contributors to the project
   - Companies offering commercial support

4. **Framework Agreements**
   - Crown Commercial Service frameworks
   - Regional frameworks (YPO, ESPO, etc.)

### Questions to Ask Potential Suppliers

1. What experience do you have with this specific software (or similar)?
2. Do you contribute to open-source projects?
3. What are your standard support SLAs and pricing?
4. Can you provide UK public sector references?
5. What security certifications do you hold?
6. What are your handover/exit arrangements?
7. Do you have UK-based staff for support?

---

## Budget Planning

### Initial Costs (One-Time)

| Item | Estimate | Notes |
|------|----------|-------|
| Server infrastructure | £0 - £500/month | Cloud or on-premises |
| Implementation services | £5,000 - £15,000 | If using external supplier |
| Penetration testing | £3,000 - £8,000 | Required before go-live |
| Accessibility audit | £2,000 - £5,000 | Recommended |
| Staff training | £0 - £2,000 | Internal or external |

### Ongoing Costs (Annual)

| Item | Estimate | Notes |
|------|----------|-------|
| Hosting | £1,200 - £6,000 | Depends on scale and provider |
| Support agreement | £6,000 - £24,000 | Depends on SLA level |
| Security testing | £3,000 - £8,000 | Annual pen test |
| Updates and maintenance | Included in support | Or internal effort |

### Total Cost of Ownership (3-Year Pilot)

| Scenario | Year 1 | Year 2 | Year 3 | Total |
|----------|--------|--------|--------|-------|
| Internal IT only | £8,000 | £4,000 | £4,000 | £16,000 |
| Light touch support | £20,000 | £12,000 | £12,000 | £44,000 |
| Full managed service | £40,000 | £30,000 | £30,000 | £100,000 |

*Estimates only — actual costs depend on scale, requirements, and market conditions.*

---

## Risk Considerations

### Risks of No Professional Support

- Slower incident resolution
- Security patches may be delayed
- No guaranteed response times
- Reliance on community goodwill
- Internal capacity constraints

### Mitigation Without Full Support Contract

- Establish internal expertise (training)
- Document runbooks and procedures
- Join user community
- Budget for ad-hoc consultancy
- Maintain test environment for updates

### Risks of External Support

- Supplier financial failure
- Knowledge concentration
- Contract disputes
- Cost escalation

### Mitigation With Support Contract

- Exit provisions and handover requirements
- Knowledge transfer clauses
- Escrow or documentation requirements
- Regular supplier review meetings

---

## Template Specification Clause

For inclusion in tender documents:

```
The Council requires professional services to support the deployment
and operation of Project NEXUS, an open-source community platform.

The software is available under [LICENCE] and may be obtained from
[REPOSITORY URL].

Services required:
1. Initial deployment and configuration to Council specifications
2. Security hardening and penetration test remediation
3. Administrator training and documentation
4. Ongoing support and maintenance [if applicable]

The Supplier should demonstrate:
- Experience with PHP 8.1+, MySQL 8.0, and Redis
- Understanding of UK public sector requirements
- Cyber Essentials Plus certification (or roadmap to achieve)
- UK-based support capability

All work shall be documented and transferable. The Council reserves
the right to change support provider at any time with 90 days notice.
```

---

## Summary

| Question | Answer |
|----------|--------|
| Do we need to buy the software? | No — it's freely available under open-source licence |
| What are we actually buying? | Professional services: deployment, support, maintenance |
| Can we do it internally? | Yes, if you have PHP/MySQL skills and capacity |
| What's the minimum external spend? | £0 (internal only) to ~£5,000 (one-time setup help) |
| What's a typical support contract? | £6,000 - £24,000/year depending on SLA |
| Are we locked in to a vendor? | No — you can change providers or bring in-house |
| Where do we find suppliers? | Digital Marketplace, frameworks, open-source community |

---

## Further Reading

- [Government guidance on open standards](https://www.gov.uk/government/publications/open-standards-principles)
- [Digital Marketplace](https://www.digitalmarketplace.service.gov.uk/)
- [Crown Commercial Service frameworks](https://www.crowncommercial.gov.uk/)
- [PPN 06/20 Social Value](https://www.gov.uk/government/publications/procurement-policy-note-0620-taking-account-of-social-value-in-the-award-of-central-government-contracts)

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | February 2026 | Initial release |
