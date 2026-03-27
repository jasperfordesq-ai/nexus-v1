# Project NEXUS V1 — Open Source Announcement Email

**Date:** 2026-03-27 (updated)
**Status:** Ready to send
**Purpose:** Public announcement of Project NEXUS V1 open source release

---

**Subject: Project NEXUS V1 is Now Open Source — Building the Future of Community Exchange Together**

Dear Community Builders, Developers, and Changemakers,

Today marks a pivotal moment in our mission to reimagine how communities connect, exchange, and thrive.

**We're releasing Project NEXUS V1 to the world — fully open source, built for everyone.**

---

## What is Project NEXUS?

Project NEXUS is an enterprise-grade, multi-tenant community platform that puts people before profit. At its core is timebanking — a radical idea that every hour of your time is worth exactly one hour of someone else's, regardless of what you do.

But NEXUS goes far beyond timebanking. It's a complete ecosystem for building connected, engaged, and empowered communities — **and we're building the infrastructure to connect those communities across borders.**

---

## What's Inside V1?

At a glance: **timebanking engine, real-time messaging, AI-powered matching, multi-tenant architecture, global federation, gamification, and a full social platform** — all production-ready and fully Dockerized.

Here's the full breakdown:

**Core Platform:**
- ⏰ **Timebanking Engine** — Full credit exchange system with wallet, transactions, and broker controls
- 🏢 **Multi-Tenancy** — Host unlimited communities on a single platform, each with their own branding and configuration
- 🎯 **Smart Matching** — AI-powered matching with semantic embeddings, collaborative filtering, availability scheduling, and learned preferences
- 💬 **Real-Time Messaging** — Private conversations with Pusher WebSocket integration and real-time presence/online status
- 📱 **Progressive Web App + Native Mobile App** — Install as a PWA on any device, or deploy as a native iOS/Android app via Capacitor
- 🌐 **Federation** — Connect multiple communities into a network for cross-community exchange, shared listings, events, and messaging

**Member Experience:**
- 📋 **Service Marketplace** — Post offers and requests, browse listings
- 🔄 **Exchange Workflow** — Structured service exchange lifecycle with broker approval
- 👥 **Group Exchanges** — Bulk community service exchanges
- 📰 **Social Feed** — Posts, comments, likes, polls, hashtags, voice messages, media attachments, link previews, and @mentions
- 📸 **Stories** — Ephemeral 24-hour photo and video stories with reactions, polls, and highlights
- 🟢 **Presence System** — Real-time online/offline status with privacy controls and custom status messages
- 🎉 **Events & Groups** — Community gatherings, interest-based groups, and event reminders
- 🤝 **Connections** — Follow and connect with community members
- 👤 **Members Directory** — Browse, filter, and discover people in your community
- 🏆 **Gamification** — Badges, XP, leaderboards, achievements, challenges, streaks, XP shop rewards, and seasonal leaderboard competitions
- 🎯 **Goals & Impact** — Track personal goals and community impact with mentoring and deliverables tracking
- 💡 **Ideation Challenges** — Innovation hub with campaigns, ideas, voting, and outcomes tracking
- 🤝 **Volunteering** — Manage volunteer opportunities, track hours, check-ins, expenses, certificates, wellbeing monitoring, and emergency alerts
- 💼 **Job Vacancies** — Full recruitment module with alerts, analytics, and public RSS/JSON job feed syndication for aggregators
- 🏛️ **Organisations** — Company and employer profiles with sub-accounts and a dedicated organisation wallet
- 👨‍👩‍👧 **Sub-Accounts / Family Accounts** — Parent-child account relationships for household and family management
- ⭐ **Reviews & Ratings** — Build trust through member feedback
- 👍 **Endorsements** — Peer skill and experience endorsements
- 📊 **Polls** — Community voting and surveys
- 🧭 **Skills Browse** — Explore skill taxonomy and discover expertise
- 🗓️ **Availability Scheduling** — Timezone-aware time-slot scheduling for smart matching and bookings
- 📄 **Custom Pages** — Tenant-managed CMS pages for community content

**Content & Communication:**
- 📝 **Blog** — Tenant content management and community news
- 📚 **Resources & Knowledge Base** — Structured articles and shared resource library
- ❓ **Help Center** — Documentation hub and FAQ
- 📰 **Newsletter System** — Email campaign manager with smart segments, templates, and send-time optimisation
- 🤖 **AI Chat** — OpenAI-powered assistant for platform guidance
- 📄 **Legal Hub** — Versioned legal documents with acceptance gates and audit trail
- 📊 **Impact Reports** — SROI analysis, member outcome reports, and social impact case studies
- 🏥 **Social Prescribing** — Information and tooling for community health integration workflows

**Trust & Reputation:**
- ✅ **Member Verification Badges** — Verified status indicators on member profiles
- 🏅 **NexusScore** — Proprietary reputation and trustworthiness scoring
- 🔥 **Streaks** — Consecutive activity tracking to reward consistent engagement
- 📊 **Personal Insights & Activity Dashboard** — Individual engagement metrics, hours given/received, skills breakdown, monthly charts, and personalised recommendations
- 🛡️ **Safeguarding Module** — Flagged content review workflow, incident reporting, safeguarding assignment tracking, and community safety dashboard
- 📋 **CRM** — Admin contact management with notes, tasks, tags, activity timelines, and export

**AI & Recommendation Engine:**
- 🔍 **Semantic Search** — Meilisearch-powered full-text search with synonym support and tenant isolation
- 🤖 **Collaborative Filtering** — Item-based recommendations from real community interaction data
- 🧠 **Semantic Embeddings** — OpenAI-powered content matching for listings, members, and requests
- 📈 **EdgeRank Feed** — Time-decay, affinity, and engagement-weighted feed ranking
- 🏅 **MatchRank & CommunityRank** — Bayesian quality scoring with Wilson confidence intervals
- 👥 **Group Recommendations** — Trending and affinity-based group discovery
- 🔁 **Match Learning** — Feedback loop that improves recommendations from user interactions
- 📊 **Algorithm Health Dashboard** — Live admin monitoring and tuning of all ranking systems

**Modern Tech Stack:**
- Frontend: React 18 + TypeScript + HeroUI + Tailwind CSS 4
- Backend: Laravel 12 + PHP 8.2+
- Database: MariaDB 10.11
- Search: Meilisearch v1.7
- AI: OpenAI text-embedding-3-small
- Real-Time: Pusher WebSockets, Firebase Cloud Messaging
- Infrastructure: Docker-ready, Redis caching, full PWA + Capacitor native app support
- API: OpenAPI 3.0 specification with Swagger UI docs

**Built for Production:**
- 🔐 Enterprise security — CSRF, rate limiting, TOTP 2FA, WebAuthn passwordless authentication, email verification gates, and invite-code registration
- 🛡️ GDPR compliance suite — data requests, consent management, cookie consent, breach tracking, and full audit log
- 🚨 Fraud & abuse detection — automated suspicious activity alerts and content moderation
- 🛡️ Insurance certificate tracking — volunteer insurance management and verification
- 🏢 Enterprise RBAC — role-based access control across 13+ modules with a full permission matrix
- ♿ WCAG 2.1 AA accessibility compliance
- 🌍 Multi-language support — 7 languages: English, Irish, German, French, Italian, Portuguese, Spanish
- 🚀 Guided onboarding wizard for new members
- 📊 Comprehensive admin panel with algorithm controls, diagnostics, cron job monitoring, and email deliverability monitoring
- 🏗️ Tenant hierarchy — parent-child tenant relationships with feature toggling per tenant
- 📧 Email webhook processing — SendGrid bounce, complaint, and delivery event handling
- 🧪 PHPUnit and Vitest test suites
- 📦 Fully Dockerized

---

## Global Federation: Connecting Timebanks Worldwide

This is where it gets exciting.

We're actively developing a **Federation API** designed to connect Project NEXUS with other timebanking platforms around the world. The vision is simple but ambitious: **a global time bank where external federation partners can discover each other, exchange services, and trade time credits across platforms and across borders.**

Imagine a member in Dublin helping someone in São Paulo with web design, while a member in Tokyo teaches Irish students origami — all through interconnected timebanking platforms, all valuing every hour equally.

The Federation API will enable:
- **Cross-platform discovery** — Find members and services on partner timebanking platforms globally
- **Interoperable time credit exchange** — Trade time credits between different timebanking systems seamlessly
- **Federation Neighborhoods** — Geographically grouped clusters of federated communities for regional coordination
- **Credit Agreements** — Negotiated exchange rate terms between federated communities
- **External federation partnerships** — Any timebanking platform worldwide can connect to the network via standardized API endpoints
- **Global marketplace** — A unified view of offers and requests spanning multiple platforms and communities
- **Trust & verification** — Federated reputation and review systems that travel with members across platforms

This isn't just about connecting NEXUS communities to each other — it's about building the **open infrastructure for a worldwide timebanking economy.** We're inviting timebanking platforms, community currency projects, and mutual aid networks everywhere to join us in defining this standard together.

---

## Why Open Source?

Because transformative technology should be accessible to everyone.

Communities around the world deserve tools that respect their autonomy, protect their data, and adapt to their needs. By going open source under AGPL-3.0, we're ensuring that Project NEXUS remains:

- ✅ **Free forever** — No vendor lock-in, no surprise fees
- ✅ **Transparent** — Every line of code is visible and auditable
- ✅ **Customizable** — Fork it, modify it, make it yours
- ✅ **Community-driven** — Shape the roadmap, contribute features, fix bugs together

And by building the Federation API as an open standard, we're ensuring that **no single platform controls the global timebanking network.** The protocol belongs to everyone.

---

## Get Started Today

**GitHub Repository:** https://github.com/jasperfordesq-ai/nexus-v1

Clone it, deploy it, break it, improve it. The full platform is yours to explore, including:
- Complete source code
- Docker Compose setup for instant local development
- Comprehensive documentation
- Migration scripts and database schema
- Admin panel and super admin tools
- Production deployment guides
- OpenAPI 3.0 specification

---

## Looking Ahead: Version 2

While V1 represents years of refinement and real-world use, we're already building the next generation.

**Project NEXUS V2** is in early-stage development, exploring:
- Modern microservices architecture (ASP.NET Core 8 + React 18)
- Enhanced scalability for global networks
- Advanced AI integration for matching and insights
- **The Federation API** — purpose-built for global interoperability between timebanking platforms
- Improved federation protocols for cross-platform credit exchange
- Next-generation UX patterns

V2 is also fully open source from day one. Follow along, contribute ideas, or just watch the journey:

**V2 Repository:** https://github.com/jasperfordesq-ai/api.project-nexus.net

---

## Join the Movement

Whether you're a:
- **Developer** — Contribute code, fix bugs, add features
- **Community Organizer** — Deploy NEXUS for your community, share feedback
- **Timebanking Platform Operator** — Connect your platform to the global federation network
- **Designer** — Help improve the UX and accessibility
- **Documentation Writer** — Make it easier for others to join
- **Translator** — Help us reach communities worldwide

Your voice matters. Your contributions matter.

**Join the conversation:**
- 🐛 V1 Issues & Features: [GitHub Issues](https://github.com/jasperfordesq-ai/nexus-v1/issues)
- 💬 V1 Discussions: [GitHub Discussions](https://github.com/jasperfordesq-ai/nexus-v1/discussions)
- 🔬 V2 Development: [V2 Repository](https://github.com/jasperfordesq-ai/api.project-nexus.net)

Star the repos, fork them, deploy them, improve them. Let's build something remarkable together.

---

## Early Adoption

Timebanks from around the world are now starting to pilot Project NEXUS as their community platform. From Ireland to the UK and beyond, real communities are using NEXUS to connect members, exchange services, and build social capital — and their feedback is shaping every release.

If your timebank or community organisation is interested in piloting NEXUS, we'd love to hear from you. Reach out via [GitHub Discussions](https://github.com/jasperfordesq-ai/nexus-v1/discussions) or get in touch directly.

---

## Acknowledgments

Project NEXUS stands on the shoulders of:
- The global timebanking movement
- The open source community
- Every contributor who believed in this vision
- Communities who tested, broke, and helped refine the platform

---

## Contributors

### Creator
- **Jasper Ford** — Creator and primary author. Copyright © 2024–2026 Jasper Ford.

### Founders
The originating Irish timebank initiative [hOUR Timebank CLG](https://hour-timebank.ie) was co-founded by:
- **Jasper Ford**
- **Mary Casey**

### Contributors
- **Steven J. Kelly** — Community insight and product thinking
- **Sarah Bird** — CEO, Timebanking UK — Leadership and strategic guidance for the UK timebanking movement

### Research Foundation
This software is informed by and builds upon a social impact study commissioned by the **West Cork Development Partnership**.

### Acknowledgements
- **West Cork Development Partnership**
- **Fergal Conlon**, SICAP Manager

Full attribution details in the [NOTICE](https://github.com/jasperfordesq-ai/nexus-v1/blob/main/NOTICE) and [CONTRIBUTORS.md](https://github.com/jasperfordesq-ai/nexus-v1/blob/main/CONTRIBUTORS.md) files.

---

## The Future is Community-Owned — and Globally Connected

For too long, community platforms have been controlled by corporations optimizing for profit over people. And for too long, timebanking communities have operated in isolation, unable to connect with kindred communities across the world.

Project NEXUS is different. It's built by the community, for the community, owned by everyone. And with the Federation API, we're building the bridges that will connect timebanks into a **truly global network** — where every hour is equal, no matter where in the world it's given.

Today, we're not just releasing software. We're releasing a blueprint for how communities can thrive in the digital age — **together, across every border.**

Welcome to Project NEXUS. Welcome home.

---

*Project NEXUS V1 — Built by Jasper Ford — Licensed under AGPL-3.0-or-later*

**"Every hour is equal. Every contribution matters. Every community deserves better. Everywhere."**

- Get started with V1: https://github.com/jasperfordesq-ai/nexus-v1
- Explore V2 & the Federation API: https://github.com/jasperfordesq-ai/api.project-nexus.net
- See it live: https://app.project-nexus.ie
- Read the docs: [Documentation](https://github.com/jasperfordesq-ai/nexus-v1/tree/main/docs)
