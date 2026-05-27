// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

export type LegalPath = '/legal/terms' | '/legal/privacy' | '/legal/cookies' | '/legal/acceptable-use' | '/legal/data-processing';

export interface LegalCallout {
  title: string;
  body: string;
}

export interface LegalSection {
  title: string;
  intro?: string;
  items: string[];
}

export interface LegalTable {
  title: string;
  columns: [string, string, string];
  rows: [string, string, string][];
}

export interface LegalPageContent {
  path: LegalPath;
  label: string;
  eyebrow: string;
  title: string;
  summary: string;
  lastUpdated: string;
  callouts: LegalCallout[];
  sections: LegalSection[];
  tables?: LegalTable[];
}

const lastUpdated = '27 May 2026';
const company = 'PROJECT NEXUS PLATFORM IRELAND LTD';
const companyWithNumber = `${company}, registered number 812763`;
const legalEmail = 'jasper.ford.esq@gmail.com';

export const legalPages: LegalPageContent[] = [
  {
    path: '/legal/terms',
    label: 'Terms',
    eyebrow: 'Terms and conditions',
    title: 'Two clear lanes: open-source software and managed hosting.',
    summary:
      'These terms explain the difference between Project NEXUS as open-source software created by Jasper Ford and Project NEXUS managed hosting supplied by the hosting company.',
    lastUpdated,
    callouts: [
      {
        title: 'Software creator and licensor',
        body:
          'Jasper Ford is the creator, copyright holder, and licensor of the Project NEXUS software. The public source code is available under AGPL-3.0-or-later with the additional notices in the NOTICE file.',
      },
      {
        title: 'Hosting and commercial services provider',
        body: `${companyWithNumber} provides managed hosting, implementation, support, and commercial service arrangements where a customer accepts a written quote, order form, or agreement.`,
      },
    ],
    sections: [
      {
        title: '1. What these terms cover',
        items: [
          'The website, pricing pages, quote builder, order enquiry form, and public sales material for Project NEXUS.',
          'Managed hosting, setup, support, maintenance, migration, training, and related commercial services supplied under a written quote or order form.',
          'The relationship between the open-source software licence and any separate commercial hosting or support terms.',
          'These terms do not replace a signed master services agreement, data processing agreement, statement of work, order form, invoice terms, or separately agreed enterprise contract. If a signed agreement conflicts with this page, the signed agreement controls for that customer.',
        ],
      },
      {
        title: '2. Open-source software terms',
        intro: 'This lane is about the code itself.',
        items: [
          'Project NEXUS is licensed under AGPL-3.0-or-later unless a separate written commercial licence says otherwise.',
          'The AGPL licence grants rights to run, study, share, and modify the covered software, subject to the licence conditions, including network source-code obligations for modified versions.',
          'The NOTICE file contains additional attribution, source-code, warranty, trademark, modified-version, and Section 7 terms. Downstream deployments must preserve the required legal notices and attribution paths.',
          'The public AGPL licence does not grant trademark rights, hosted-service access, commercial support, private modifications, implementation work, warranties, uptime commitments, or proprietary licensing.',
          'Jasper Ford may offer the same code under separate commercial, proprietary, support, hosting, warranty, or service terms where those terms are agreed in writing.',
        ],
      },
      {
        title: '3. Managed hosting terms',
        intro: 'This lane is about paid operation of the platform.',
        items: [
          `${companyWithNumber} is the intended provider of managed hosting and related commercial services for Project NEXUS.`,
          'The quote builder and order form are enquiry tools. Submitting the form does not create an automatic contract, payment obligation, or guaranteed acceptance. A contract starts only when the relevant written quote, order form, or agreement is accepted.',
          'Published pricing may be capped by active members, modules, support level, storage, email volume, traffic, and operational complexity. Enterprise or unusually high-scale use requires a bespoke quote.',
          'Managed hosting may include platform deployment, updates, backups, monitoring, security maintenance, email delivery configuration, tenant setup, migration assistance, support, and agreed launch services.',
          'Service commitments, response times, maintenance windows, data export, deletion, renewal, cancellation, and service credits apply only where stated in the accepted written order terms.',
        ],
      },
      {
        title: '4. Customer responsibilities',
        items: [
          'Customers are responsible for their tenant content, user permissions, community rules, safeguarding practice, moderation decisions, local policy compliance, and lawful use of the platform.',
          'Customers must make sure they have the rights and permissions needed for content, data, member records, branding, imports, integrations, email lists, documents, and materials they provide.',
          'Customers must use suitable passwords, administrator controls, access permissions, and internal governance. They must promptly report suspected security incidents or misuse.',
          'Customers must not use the hosted service in a way that creates unreasonable load, bypasses limits, undermines security, infringes rights, sends spam, or breaches the Acceptable Use Policy.',
          'If a customer operates Project NEXUS for its own members, volunteers, staff, partners, or community users, the customer remains responsible for the relationship with those end users unless a written agreement says otherwise.',
        ],
      },
      {
        title: '5. Fees, taxes, suspension, and termination',
        items: [
          'Fees are set out in the applicable quote, order form, invoice, or subscription plan. Unless stated otherwise, prices exclude taxes, duties, payment processing costs, third-party charges, premium integrations, and bespoke work.',
          'The provider may suspend or restrict hosting where payment is overdue, security is at risk, legal compliance requires it, usage materially exceeds the agreed plan, or the service is being misused.',
          'On termination, customers remain responsible for charges incurred before termination and for any post-termination export, archival, migration, or professional services that are separately agreed.',
          'The provider may make operational changes needed for security, reliability, legal compliance, third-party service changes, or continued maintainability of the hosted service.',
        ],
      },
      {
        title: '6. Disclaimers and liability',
        items: [
          'The public software is provided under the AGPL warranty and liability disclaimers, including the additional NOTICE wording.',
          'These public legal pages are not legal advice and are not a substitute for a signed customer agreement reviewed for the customer’s own jurisdiction, procurement rules, and risk profile.',
          'Managed hosting is not legal, safeguarding, accounting, tax, procurement, public-sector, healthcare, employment, charity, financial, or regulatory advice.',
          'Project NEXUS can support community exchange, volunteering, timebanking, civic participation, and related workflows, but customers must decide whether their deployment is appropriate for their own duties and risk profile.',
          'To the maximum extent permitted by law, liability for managed hosting is limited by the applicable written agreement. Nothing in these terms excludes liability that cannot lawfully be excluded.',
        ],
      },
      {
        title: '7. Governing law and notices',
        items: [
          'Unless a written agreement says otherwise, managed hosting terms are intended to be governed by the laws of Ireland, subject to any mandatory rights that apply by law.',
          `Legal and commercial notices may be sent to ${legalEmail}. Formal order forms and invoices may provide additional contact and company details.`,
          'These terms may be updated as the service matures. Material commercial changes will apply according to the relevant customer agreement or renewal terms.',
        ],
      },
    ],
  },
  {
    path: '/legal/privacy',
    label: 'Privacy',
    eyebrow: 'Privacy policy',
    title: 'Privacy for sales enquiries, hosted services, and open-source communications.',
    summary:
      'This policy explains what personal data may be collected through the sales site, order enquiries, managed hosting, support, licensing correspondence, and platform operations.',
    lastUpdated,
    callouts: [
      {
        title: 'Controller and processor roles',
        body:
          'For sales enquiries and commercial administration, the hosting provider normally acts as controller. For personal data inside a customer tenant, the customer normally acts as controller and the hosting provider acts as processor under written instructions.',
      },
      {
        title: 'No sale of customer tenant data',
        body:
          'Customer tenant data is processed to provide, secure, support, and improve the hosted service under the customer relationship. It is not sold as a data product.',
      },
      {
        title: 'Open-source correspondence',
        body:
          'Jasper Ford may separately receive and process correspondence about software authorship, licensing, copyright, attribution, security reports, or the public repository.',
      },
    ],
    sections: [
      {
        title: '1. Who this policy applies to',
        items: [
          'Visitors to project-nexus.ie and related Project NEXUS sales pages.',
          'People who submit an order enquiry, pricing request, support request, security report, partnership message, or licensing question.',
          'Customers, administrators, tenant operators, and authorised contacts for managed hosting.',
          'End users of customer-operated hosted tenants, where the hosting provider processes data on behalf of the customer.',
          'Contributors, issue reporters, and correspondents who interact with Jasper Ford or the public Project NEXUS repository.',
        ],
      },
      {
        title: '2. Personal data we may collect',
        items: [
          'Contact details such as name, email address, organisation, role, region, and message contents.',
          'Sales and order information such as selected plan, quoted capacity, support tier, add-ons, billing preference, notes, timestamps, and enquiry reference.',
          'Commercial administration data such as order forms, invoices, payment status, procurement details, customer contacts, and contract records.',
          'Support and operational data such as tickets, diagnostics, logs, IP address, user agent, request identifiers, security events, and error reports.',
          'Hosted tenant data supplied by customers and their authorised users, which may include account information, community content, timebanking records, messages, events, listings, volunteering records, files, and configuration.',
          'Repository or licensing correspondence such as GitHub usernames, email addresses, issue content, pull requests, security reports, and attribution discussions.',
        ],
      },
      {
        title: '3. How we use personal data',
        items: [
          'To respond to enquiries, prepare quotes, assess fit, arrange discovery, and progress a requested commercial conversation.',
          'To provide, secure, maintain, monitor, support, improve, and troubleshoot managed hosting.',
          'To administer contracts, billing, renewals, procurement, compliance records, and customer communications.',
          'To investigate misuse, security incidents, platform abuse, delivery failures, fraud, or legal requests.',
          'To comply with legal obligations, enforce terms, protect rights, preserve evidence, and maintain accounting or corporate records.',
          'To manage open-source licensing, attribution, vulnerability reports, contributor communication, and repository governance.',
        ],
      },
      {
        title: '4. Legal bases',
        items: [
          'Contract or steps before contract, where we respond to a requested quote, provide hosting, or administer an agreed service.',
          'Legitimate interests, including operating a secure website, responding to business enquiries, improving service reliability, protecting the platform, and maintaining commercial records.',
          'Legal obligation, where accounting, tax, company, regulatory, law-enforcement, or data-protection duties require processing.',
          'Consent, where required for optional cookies, marketing communications, or other optional processing. Consent can be withdrawn where applicable.',
        ],
      },
      {
        title: '5. Sharing and service providers',
        items: [
          'Personal data may be shared with service providers that help provide hosting, infrastructure, email delivery, analytics, security, payments, backups, support, professional advice, and administration.',
          'The order enquiry workflow may use backend email delivery through SendGrid or equivalent email infrastructure.',
          'The sales site may load fonts, analytics, security, or delivery services where configured. Optional analytics should be handled according to the Cookie Policy.',
          'Customer tenant data is not sold, rented, or brokered. It is processed to provide the hosted service, comply with customer instructions, secure the platform, and meet legal obligations.',
          'Information may be disclosed where required by law, court order, regulator request, security incident response, or to protect rights, safety, or service integrity.',
        ],
      },
      {
        title: '6. International transfers',
        items: [
          'Some service providers may process data outside Ireland, the UK, or the EEA.',
          'Where required, transfers should use appropriate safeguards such as adequacy decisions, Standard Contractual Clauses, data processing terms, or equivalent lawful transfer mechanisms.',
          'Enterprise customers may request more detail about sub-processors and transfer arrangements during procurement or contracting.',
        ],
      },
      {
        title: '7. Retention',
        items: [
          'Sales enquiries are retained for as long as needed to respond, manage follow-up, preserve commercial context, and keep reasonable business records.',
          'Contract, invoice, accounting, and tax records may be retained for statutory periods.',
          'Support, diagnostic, and security logs are retained for limited periods based on operational need, security, audit, and incident response.',
          'Hosted tenant data is retained according to the customer agreement, backup cycle, export process, deletion request, or lawful hold requirement.',
          'Open-source contribution and licensing records may remain public or retained where needed for authorship, provenance, security, or legal reasons.',
        ],
      },
      {
        title: '8. Your rights and contacts',
        items: [
          'Depending on your location and role, you may have rights to access, correct, delete, restrict, object to, or receive a copy of your personal data.',
          'Where processing is based on legitimate interests, you may have a right to object to that processing. Where processing is based on consent, you may withdraw that consent without affecting earlier lawful processing.',
          'If your data is held inside a customer-operated tenant, contact that tenant operator first. They are normally the controller for their community data.',
          `For sales-site, hosting, licensing, or repository privacy questions, contact ${legalEmail}.`,
          'You may have the right to complain to the Irish Data Protection Commission or another competent supervisory authority.',
        ],
      },
    ],
    tables: [
      {
        title: 'Privacy notice map',
        columns: ['Context', 'Typical role', 'Primary records'],
        rows: [
          ['Sales site and order enquiries', 'Controller for commercial enquiries and follow-up.', 'Contact details, organisation, quote selections, page URL, notes, timestamps, email delivery records.'],
          ['Managed customer tenants', 'Usually processor for customer tenant data and controller for provider administration data.', 'User accounts, tenant content, operational logs, support records, security events, backups, configuration.'],
          ['Open-source repository and licensing', 'Separate controller for authorship, licensing, attribution, vulnerability, and contributor correspondence.', 'Repository identities, issue content, pull requests, emails, security reports, attribution records.'],
        ],
      },
    ],
  },
  {
    path: '/legal/cookies',
    label: 'Cookies',
    eyebrow: 'Cookie policy',
    title: 'Cookies should be necessary, understandable, and controllable.',
    summary:
      'This policy explains cookies and similar technologies used on the sales site and in hosted Project NEXUS deployments.',
    lastUpdated,
    callouts: [
      {
        title: 'Strictly necessary first',
        body:
          'Core security, routing, authentication, session, consent, and load-balancing technologies may be needed to provide the requested service.',
      },
      {
        title: 'Optional tracking needs a choice',
        body:
          'Analytics, advertising, behavioural measurement, or optional third-party tools should only run where there is an appropriate lawful basis and consent where required.',
      },
      {
        title: 'Cookie register',
        body:
          'The public register below groups technologies by purpose so customers and users can see which tools are necessary, optional, customer-selected, or third-party controlled.',
      },
    ],
    sections: [
      {
        title: '1. What cookies are',
        items: [
          'Cookies are small files or identifiers stored on a browser or device. Similar technologies include local storage, pixels, beacons, tags, SDKs, and server-side identifiers.',
          'They can keep a service secure, remember choices, maintain sessions, measure performance, or help understand how a website is used.',
          'The sales site is primarily informational. Hosted Project NEXUS applications may use more cookies because they include login, tenant context, security, accessibility, localisation, and user settings.',
        ],
      },
      {
        title: '2. How to manage cookies',
        items: [
          'You can block or delete cookies through your browser settings. Some security, login, consent, and session features may not work correctly without necessary cookies.',
          'Where a cookie banner or preference centre is available, you can use it to accept, reject, or change optional choices.',
          'If a hosted customer enables optional analytics or embedded third-party content, that customer is responsible for giving appropriate information and choices to its own users.',
        ],
      },
      {
        title: '3. Third-party services',
        items: [
          'The sales site may use third-party services for fonts, analytics, security, email delivery, and performance monitoring.',
          'Hosted deployments may also use services such as payment providers, email providers, push notification providers, maps, analytics, accessibility tools, AI services, or customer-selected integrations.',
          'Third-party providers may process data under their own policies where they act as independent controllers, or under service terms where they act as processors.',
        ],
      },
      {
        title: '4. Changes to this policy',
        items: [
          'This policy should be updated when new analytics, marketing, embedded content, authentication, security, or third-party tools are added.',
          'Material changes to optional cookies should be reflected in the consent interface where one is used.',
        ],
      },
    ],
    tables: [
      {
        title: 'Cookie register and technology categories',
        columns: ['Category', 'Purpose', 'Consent position'],
        rows: [
          ['Strictly necessary', 'Security, routing, session continuity, CSRF protection, authentication, load balancing, consent storage, and service delivery.', 'Usually required to provide the requested service.'],
          ['Preferences', 'Remembering theme, accessibility, language, tenant, or display choices.', 'May be necessary or consent-based depending on the feature and implementation.'],
          ['Analytics', 'Understanding page visits, performance, conversion, errors, and aggregate usage.', 'Consent may be required where tracking is not strictly necessary.'],
          ['Marketing', 'Campaign measurement, retargeting, advertising, or audience building.', 'Consent should be obtained where required before use.'],
          ['Third-party embeds', 'Maps, videos, chat, payment widgets, or customer-selected integrations.', 'Depends on the provider and purpose; users should be informed before optional embeds run.'],
        ],
      },
    ],
  },
  {
    path: '/legal/acceptable-use',
    label: 'Acceptable Use',
    eyebrow: 'Acceptable use policy',
    title: 'A community platform must protect people, data, and infrastructure.',
    summary:
      'This policy sets baseline rules for using Project NEXUS managed hosting, demos, public services, support channels, and related infrastructure.',
    lastUpdated,
    callouts: [
      {
        title: 'Community safety',
        body:
          'Customers must operate their communities with suitable rules, moderation, safeguarding, and escalation processes for their context.',
      },
      {
        title: 'Infrastructure integrity',
        body:
          'The hosted service cannot be used to attack, overload, evade, scrape, spam, mine, or compromise systems, networks, users, or third parties.',
      },
    ],
    sections: [
      {
        title: '1. Prohibited content and conduct',
        items: [
          'Illegal content, unlawful discrimination, harassment, credible threats, exploitation, sexual abuse material, non-consensual intimate content, or content that facilitates serious harm.',
          'Spam, phishing, malware, credential harvesting, deceptive impersonation, click fraud, unauthorised bulk messaging, or abusive automation.',
          'Content or activity that infringes copyright, database rights, trademarks, privacy rights, publicity rights, trade secrets, or other third-party rights.',
          'Attempts to bypass access controls, probe systems without permission, disrupt service, exfiltrate data, or interfere with other tenants or networks.',
          'Use that breaches sanctions, export controls, anti-bribery laws, data-protection law, safeguarding duties, or other applicable legal obligations.',
        ],
      },
      {
        title: '2. Restricted use cases',
        items: [
          'Do not process special-category, safeguarding-sensitive, health, biometric, high-risk financial, law-enforcement, or children’s data unless this has been assessed and agreed in writing.',
          'Do not use Project NEXUS as a payment ledger, regulated financial service, employment tribunal, clinical record system, emergency service, or sole safeguarding case-management system unless a specific written agreement says otherwise.',
          'Do not use AI, matching, ranking, or recommendation features to make decisions that require human review, legal safeguards, or regulated assessment without appropriate governance.',
        ],
      },
      {
        title: '3. Usage limits and fair use',
        items: [
          'Published plans assume reasonable community use within the selected capacity, support tier, storage, email, tenant, and traffic profile.',
          'Customers must not create artificial activity, avoid rate limits, resell unmanaged access, run unrelated workloads, mine cryptocurrency, or use the platform as a generic file host or bulk email tool.',
          'High-scale, unusually busy, high-risk, or integration-heavy deployments may require enterprise pricing, architecture review, or written limits.',
        ],
      },
      {
        title: '4. Enforcement',
        items: [
          'The provider may investigate suspected abuse, restrict features, throttle traffic, remove harmful content, suspend access, preserve evidence, or terminate services where necessary.',
          'Where practical and safe, customers will be given notice and an opportunity to remedy issues. Immediate action may be taken for urgent security, legal, safeguarding, payment, or infrastructure risks.',
          `Report suspected abuse, security issues, or urgent platform misuse to ${legalEmail}.`,
        ],
      },
    ],
  },
  {
    path: '/legal/data-processing',
    label: 'Data Processing',
    eyebrow: 'Data processing and security',
    title: 'Managed hosting needs processor-grade discipline, not just a server.',
    summary:
      'This page explains the intended data-processing, hosting, security, backup, export, and customer responsibility model for managed Project NEXUS deployments.',
    lastUpdated,
    callouts: [
      {
        title: 'Customer as controller',
        body:
          'For tenant community data, the customer normally decides why and how personal data is processed. The hosting provider normally processes that data to provide the hosted service.',
      },
      {
        title: 'Written DPA for production hosting',
        body:
          'Production customers should have a written data processing agreement or equivalent processing terms, especially where the platform stores member, volunteer, message, or community records. This public page is not a substitute for a signed data processing agreement.',
      },
      {
        title: 'Sub-processor transparency',
        body:
          'Customers should be able to ask which infrastructure, email, monitoring, storage, security, payment, and support providers are used for their deployment before production launch.',
      },
    ],
    sections: [
      {
        title: '1. Processing roles',
        items: [
          'For sales, billing, support, security, and commercial administration, the hosting provider may act as controller.',
          'For tenant content and end-user records, the customer normally acts as controller and the hosting provider acts as processor.',
          'For open-source licensing, attribution, copyright, security disclosure, and repository correspondence, Jasper Ford may act separately in relation to that correspondence.',
          'Where roles differ for a specific project, they should be recorded in the relevant order form, DPA, or statement of work.',
        ],
      },
      {
        title: '2. Hosting and operational controls',
        items: [
          'Managed hosting may include deployment, backups, monitoring, security patching, configuration, tenant setup, email delivery, storage, logs, and disaster-recovery planning appropriate to the agreed plan.',
          'Operational controls may include access control, least-privilege administration, logging, encryption in transit, protected secrets, backup retention, malware prevention, audit trails, and monitored infrastructure.',
          'Customers should maintain their own governance controls, administrator lists, lawful-basis decisions, privacy notices, community policies, export procedures, and incident contacts.',
        ],
      },
      {
        title: '3. Sub-processors and third-party providers',
        items: [
          'The provider may use infrastructure, email, DNS, CDN, monitoring, analytics, payment, storage, communications, professional, and support providers to deliver the hosted service.',
          'Sub-processors should be selected for appropriate security, reliability, confidentiality, and data-protection terms.',
          'Where international transfers are involved, appropriate safeguards may include adequacy decisions, Standard Contractual Clauses, transfer risk review, and contractual processor obligations.',
          'Enterprise customers may request a sub-processor list, transfer details, and security information during procurement.',
        ],
      },
      {
        title: '4. Security incidents',
        items: [
          'Customers must promptly report suspected compromise, leaked credentials, unauthorised access, harmful content, or platform misuse.',
          'The provider will investigate incidents affecting managed hosting and take reasonable containment, remediation, notification, and evidence-preservation steps.',
          'Customer controllers remain responsible for deciding and making any legally required notifications to their own users or regulators unless a written agreement allocates that task differently.',
        ],
      },
      {
        title: '5. Export, deletion, and end of service',
        items: [
          'Customers should be able to request a reasonable export of their tenant data, subject to plan limits, technical feasibility, security, and any outstanding payment or legal retention requirements.',
          'Data deletion follows the agreed contract, backup lifecycle, legal retention duties, and any active dispute, abuse, security, or compliance hold.',
          'The AGPL software licence remains separate from hosted-service data export. Receiving source code does not automatically include hosted customer data, private credentials, third-party accounts, or managed infrastructure access.',
        ],
      },
      {
        title: '6. AI, search, and integrations',
        items: [
          'Project NEXUS may include search, matching, recommendation, AI chat, embeddings, notifications, payment, email, and third-party integration features.',
          'Customers should decide which optional modules are appropriate for their community and document any additional privacy, transparency, or consent requirements.',
          'Sensitive, regulated, or high-risk processing should not be enabled without a written assessment and suitable safeguards.',
        ],
      },
    ],
  },
];

export function findLegalPage(path: LegalPath): LegalPageContent {
  return legalPages.find((page) => page.path === path) ?? legalPages[0];
}
