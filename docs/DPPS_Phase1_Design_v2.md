# Digital Plant Protection System (DPPS) — Phase 1 Design Document

**Client:** Directorate of Agriculture Extension (Plant Protection), Balochistan, Quetta
**Document status:** Draft for finalization — **Version 2**
**Scope of this document:** Companies, Dealers and Licensing (Phase 1)

---

## Changes in Version 2

| # | Change | Sections affected |
|---|---|---|
| V2-1 | Provincial registrations table removed. | 2, 4.5, 4.12, 9, 10 |
| V2-2 | Required documents no longer block licensing by default. A new setting, **"Enforce document requirements"**, turns the check on. While it is off, licenses can be issued and the company or dealer is marked **"Documents incomplete"** for that license period until the documents are uploaded and verified. | 1, 4.2, 4.9, 5.1, 6.3, 7, 10, 11 |
| V2-3 | Company assets are optional (checklist item X is no longer required). | 4.5, 5.4 |
| V2-4 | Scrutiny and Interview stages removed from Phase 1, along with their tables and screens. Moved to the Phase 2 parking lot. | 4.7, 4.8, 5.3, 9, 10, 15 |
| V2-5 | Person qualifications are optional. | 4.4, 10 |
| V2-6 | Companies field `ptcl` renamed to `landline`. | 4.5, 10, 14 |
| V2-7 | File storage and download links explained more clearly. | 3, 13 |

---

## Table of Contents

1. Decisions Log
2. Scope
3. Technical Architecture
4. Database Structure
5. Initial (Seed) Data
6. Business Rules and Validations
7. Status Logic and Expiry Alerts
8. Roles and Permissions
9. API Endpoint Catalogue
10. Screen Sketches — Department Side
11. Screen Sketches — Company Portal
12. Public Verification Page
13. Security, Backups and Audit
14. Data Migration Plan (Excel → DPPS)
15. Phase 2 Parking Lot
16. Open Items

---

## 1. Decisions Log

| # | Topic | Decision |
|---|---|---|
| D1 | Phase 1 scope | Companies, Dealers and Licensing only. Pest Alerts, Inspections and Samples move to Phase 2. |
| D2 | Company–dealer relationship | Not tracked. |
| D3 | Products | Recorded per company as a structured list. |
| D4 | License period | 1 year by default, editable in Settings (separately for companies and dealers). |
| D5 | Fees | Stored in the database only, with no screen to edit them. |
| D6 | Certificate | System-generated in a format to be provided later, with a QR code linking to a public verification page. |
| D7 | Higher approval (Step 8) | Disabled for now; it can be switched on later from Settings. |
| D8 | Technical staff vs dealer owner | The same person cannot be both. One person may own multiple dealer shops. |
| D9 | Staff transfer | The end date at the previous company is required before the person can join another company. |
| D10 | Portal access | Companies only in Phase 1. A dealer portal is planned for Phase 2. |
| D11 | Portal capabilities | Companies can submit renewals, manage staff, upload documents and add products. Company information is locked. |
| D12 | Locked company info | No change-request feature. Companies must contact the Directorate, and officers make the change. |
| D13 | Hosting | Cloud. |
| D14 | Technology | Laravel (API-first) + MySQL + Vue 3 SPA. The same API will serve the future mobile app. |
| D15 | Notifications | Dashboard and in-app only in Phase 1. SMS and email in Phase 2. |
| D16 | Language | English only. |
| D17 | Dealer checklist | Same items as the company checklist. It is kept as a separate template so the two can differ later. |
| D18 | Penalties | Entered manually by the officer, because penalties may be waived or reduced. The system shows helper information only. |
| D19 | License number format | Configurable pattern in Settings. A placeholder format is used for now. |
| D20 | Reports | None in Phase 1. Lists can be exported to Excel. |
| D21 | Barcode type | QR code, so any phone camera can open the verification URL. |
| D22 | Provincial registrations | Not recorded as structured data. Only the document (checklist item H) is kept. |
| D23 | Document enforcement | Controlled by a setting, off by default. While it is off, licenses can be issued with missing or unverified documents and are marked "Documents incomplete". |
| D24 | Company assets | Optional. |
| D25 | Scrutiny and interviews | Not part of Phase 1. |
| D26 | Person qualifications | Optional. |
| D27 | Landline | The companies' PTCL field is called `landline`. |

---

## 2. Scope

### In Phase 1
- Company records: profile, people, technical staff, premises, assets (optional), products and documents.
- Dealer records: shop, owners and documents.
- The license application workflow (new and renewal) for companies and dealers, based on the 9-step process.
- Dynamic, versioned checklists for four combinations: company new, company renewal, dealer new and dealer renewal.
- A setting to enforce or relax document requirements, with a "Documents incomplete" flag while they are relaxed.
- Manual penalty entry with a waiver or reduction trail.
- License issuance with a certificate PDF and QR code, plus a public verification page.
- Expiry alerts on the dashboard.
- A company portal with restricted access.
- Role-based permissions.
- A tamper-proof activity log.
- Migration of the existing Excel data, with an exceptions review.

### Out of Phase 1
Covered in Section 15.

---

## 3. Technical Architecture

```
┌──────────────────────┐     ┌──────────────────────┐
│  Vue 3 Web App (SPA) │     │ Mobile App (Phase 2) │
│  Staff + Co. Portal  │     │                      │
└──────────┬───────────┘     └──────────┬───────────┘
           │   HTTPS  /api/v1/*  (JSON)  │
           └──────────────┬──────────────┘
                ┌─────────▼─────────┐
                │  Laravel 12 API   │  Sanctum auth, policies,
                │                   │  validation, business rules
                └───┬──────┬────┬───┘
                    │      │    │
            ┌───────▼─┐ ┌──▼──────────┐ ┌────────────┐
            │ MySQL 8 │ │ Private file │ │ Queue +    │
            │         │ │ storage (S3) │ │ Scheduler  │
            └─────────┘ └─────────────┘ └────────────┘
```

| Layer | Choice | Reason |
|---|---|---|
| Backend | Laravel 12, a pure REST API under `/api/v1/` | Every rule lives in one place, so web and mobile behave identically. |
| Authentication | Laravel Sanctum | Cookie sessions for the web app, tokens for the mobile app. |
| Permissions | spatie/laravel-permission | Roles and permissions are editable from a screen. |
| Activity log | spatie/laravel-activitylog, extended | Adds IP address, user agent, channel and a batch ID to each entry. |
| Frontend | Vue 3 + Vite + Pinia + Vue Router + PrimeVue | Livewire does not produce a reusable API, so Vue is the better fit for an API-first system. |
| Database | MySQL 8 | Needed for generated columns, JSON fields and check constraints. |
| Files | Private S3-compatible cloud storage | Holds every uploaded document (see the note below this table). |
| PDF / QR | Server-side PDF rendering with an embedded QR code | Used for certificates and deficiency letters. |
| Jobs | Laravel scheduler (daily) plus a queue | Recalculates statuses and alerts, generates PDFs, and runs imports. |

**Note on files:** "files" means every document uploaded into the system:
- CNIC scans, degrees and appointment letters
- challans, agreements and certificates
- deficiency letters
- license certificates generated by the system

These files are stored **permanently** in private cloud storage. The storage is not open to the internet, so nobody can reach a file by typing or guessing its address.

When an authorised user clicks **View** or **Download**, the API first checks that user's permission. It then creates a one-time link to that single file, and the link works for **5 minutes**. Only the link expires; the file itself stays in storage forever. The next click creates a fresh link.

This means a download link copied into WhatsApp or email stops working after 5 minutes, so documents cannot leak through shared links.

API conventions:
- **Response format:** `{ "data": ..., "meta": {pagination}, "errors": {field: [messages]} }`.
- **Warnings:** the API returns HTTP `409` together with a `warnings` array. The client can resend the request with `confirm_warnings: true` and a `warning_reason`, which is logged.
- **Dates:** exchanged as `YYYY-MM-DD` and displayed as `DD-MM-YYYY`.
- **CNIC:** stored as 13 digits and displayed as `XXXXX-XXXXXXX-X`.

---

## 4. Database Structure

### 4.0 Conventions

- Every table has:
  - `id` BIGINT UNSIGNED as the primary key, auto-incrementing
  - `created_at` and `updated_at`
- Master and transaction tables also have:
  - `created_by` and `updated_by`, both FK to `users`
  - `deleted_at`, for soft delete; nothing is ever hard-deleted
- Money is stored as `DECIMAL(12,2)`.
- Enumerations are shown as ENUM for readability. They may be implemented as ENUM or VARCHAR with a check constraint.
- `licensable_type` / `licensable_id` is a polymorphic link that points to either `companies` or `dealers`.

### 4.1 Users and Access

```
users
  id
  name                  VARCHAR(150)
  email                 VARCHAR(150)  UNIQUE
  mobile                VARCHAR(20)
  password              VARCHAR(255)  (hashed)
  user_type             ENUM(staff, company)
  company_id            FK companies  NULL (required when user_type = company)
  is_active             BOOLEAN default true
  must_change_password  BOOLEAN default true
  two_factor_secret     TEXT NULL
  failed_login_count    INT default 0
  locked_until          DATETIME NULL
  last_login_at         DATETIME NULL
  last_login_ip         VARCHAR(45) NULL
  timestamps, deleted_at

user_districts          -- limits a District Officer's data scope
  user_id               FK users
  district_id           FK districts
  UNIQUE(user_id, district_id)

roles / permissions / model_has_roles / model_has_permissions / role_has_permissions
                        -- standard spatie/laravel-permission tables

personal_access_tokens  -- standard Sanctum table

login_attempts
  email, ip_address, user_agent, success BOOLEAN, attempted_at
```

### 4.2 Settings and Fees

```
settings                -- editable from the Settings screen (except where is_ui_editable = false)
  key                   VARCHAR(100) UNIQUE
  value                 TEXT
  data_type             ENUM(integer, decimal, string, boolean, json)
  group                 VARCHAR(50)   (general, licensing, alerts, numbering)
  label                 VARCHAR(150)
  description           TEXT
  is_ui_editable        BOOLEAN

fee_structures          -- NO screen. Edited directly in the database only.
  entity_type           ENUM(company, dealer)
  fee_type              ENUM(registration, renewal, late_renewal_per_day,
                             no_technical_staff_per_month, restoration_per_month)
  amount                DECIMAL(12,2)
  effective_from        DATE
  effective_to          DATE NULL      (NULL = currently applicable)
  notes                 VARCHAR(255)
```
The penalty rates in `fee_structures` are **reference values only**. The screen shows them to the officer as guidance, and the officer enters the final penalty (Rule R-16).

### 4.3 Lookups

```
provinces        id, name, is_active
districts        id, name, code (e.g. QTA, BRK, KZD, HUB), is_active
tehsils          id, district_id FK, name, is_active
qualifications   id, name, is_agriculture_degree BOOLEAN, is_active
document_types   id, name, category ENUM(application, cnic, license, challan,
                   inspection, correspondence, agreement, qualification, other),
                 applies_to ENUM(company, dealer, person, any),
                 has_expiry BOOLEAN, is_active
```

### 4.4 People

Each human is stored **once**, regardless of whether they are a CEO, director, technical staff member, contact person or dealer owner.

```
persons
  cnic                  CHAR(13) NULL UNIQUE     (NULL allowed only for legacy imported records)
  cnic_pending          BOOLEAN default false    (true = legacy record, CNIC still to be obtained)
  full_name             VARCHAR(150)
  normalized_name       VARCHAR(150) INDEX
  father_name           VARCHAR(150) NULL
  gender                ENUM(male, female, other) NULL
  date_of_birth         DATE NULL
  mobile                VARCHAR(20) INDEX         (not unique; reuse triggers a warning)
  alt_mobile            VARCHAR(20) NULL
  email                 VARCHAR(150) NULL
  address               TEXT NULL
  photo_path            VARCHAR(255) NULL
  timestamps, created_by, updated_by, deleted_at

person_qualifications                  -- OPTIONAL: a person may have zero rows
  person_id             FK persons
  qualification_id      FK qualifications
  institution           VARCHAR(200) NULL
  passing_year          YEAR NULL
  degree_document_id    FK documents NULL
```

### 4.5 Companies

```
companies
  company_code          VARCHAR(20) UNIQUE       (system generated, e.g. C-0001)
  name                  VARCHAR(250)
  normalized_name       VARCHAR(250) INDEX       (lowercase, punctuation and "pvt/ltd/(private)" removed)
  legal_type            ENUM(private_ltd, public_ltd, partnership, sole_proprietor, other)
  ntn                   VARCHAR(20) NULL UNIQUE
  incorporation_no      VARCHAR(50) NULL
  incorporation_date    DATE NULL
  head_office_address   TEXT
  city                  VARCHAR(100)
  province_id           FK provinces
  landline              VARCHAR(20) NULL
  mobile                VARCHAR(20) NULL
  email                 VARCHAR(150) NULL
  website               VARCHAR(200) NULL
  pcpa_member           BOOLEAN default false
  croplife_member       BOOLEAN default false
  membership_no         VARCHAR(50) NULL
  status                ENUM(unlicensed, active, expiring, expired, suspended, cancelled)
  legacy_reg_no         VARCHAR(100) NULL        (from Excel, e.g. "905 /Renewal/PP/DGA")
  legacy_notes          TEXT NULL                (unmapped Excel columns kept for reference)
  timestamps, created_by, updated_by, deleted_at

company_people
  company_id            FK companies
  person_id             FK persons
  role                  ENUM(ceo, director, technical_staff, contact_person, authorized_rep)
  designation           VARCHAR(100) NULL
  appointment_date      DATE NULL
  start_date            DATE
  end_date              DATE NULL                (NULL = currently working)
  end_reason            VARCHAR(255) NULL
  verification_status   ENUM(pending, verified, rejected)
  verified_by           FK users NULL
  verified_at           DATETIME NULL
  rejection_reason      VARCHAR(255) NULL
  source                ENUM(office, portal, import)
  active_tech_person    BIGINT GENERATED ALWAYS AS
                          (CASE WHEN role = 'technical_staff' AND end_date IS NULL
                                AND deleted_at IS NULL AND verification_status <> 'rejected'
                           THEN person_id END) STORED
  UNIQUE(active_tech_person)                     -- database-level guarantee for Rule R-01
  CHECK(end_date IS NULL OR end_date >= start_date)
  timestamps, created_by, updated_by, deleted_at

company_premises                        -- offices and warehouses (Form-13)
  company_id            FK companies
  type                  ENUM(head_office, regional_office, field_office, warehouse)
  district_id           FK districts NULL   (NULL if outside Balochistan)
  address               TEXT
  gps_lat               DECIMAL(10,7) NULL
  gps_lng               DECIMAL(10,7) NULL
  contact_person_id     FK persons NULL
  phone                 VARCHAR(20) NULL
  is_active             BOOLEAN

company_assets                          -- Form-13 capital / assets (OPTIONAL: zero rows allowed)
  company_id            FK companies
  asset_type            ENUM(movable, immovable)
  description           VARCHAR(255)
  district_id           FK districts NULL
  estimated_value       DECIMAL(14,2) NULL

products                                -- generic master list
  generic_name          VARCHAR(150)     (e.g. Chlorpyrifos)
  concentration         VARCHAR(30)      (e.g. 40%)
  formulation           VARCHAR(30)      (EC, WP, SC, WDG, GR …)
  category              ENUM(insecticide, herbicide, fungicide, acaricide,
                             rodenticide, nematicide, plant_growth_regulator, other)
  is_restricted         BOOLEAN default false
  is_active             BOOLEAN
  UNIQUE(generic_name, concentration, formulation)

company_products
  company_id            FK companies
  product_id            FK products NULL   (NULL only for imported rows awaiting mapping)
  brand_name            VARCHAR(150)
  dpp_registration_no   VARCHAR(100) NULL  (federal DPP registration)
  dpp_valid_to          DATE NULL
  source                ENUM(own_import, purchase_agreement)
  sample_provided       BOOLEAN default false
  status                ENUM(pending, approved, withdrawn, needs_mapping)
  approved_in_license_id FK licenses NULL
  remarks               VARCHAR(255) NULL
  UNIQUE(company_id, brand_name)
  timestamps, created_by, updated_by, deleted_at
```

### 4.6 Dealers

```
dealers
  dealer_code           VARCHAR(20) UNIQUE   (system generated, e.g. D-QTA-0001)
  shop_name             VARCHAR(250)
  normalized_name       VARCHAR(250) INDEX
  district_id           FK districts
  tehsil_id             FK tehsils NULL
  business_address      TEXT
  gps_lat, gps_lng      DECIMAL(10,7) NULL
  mobile                VARCHAR(20) NULL
  email                 VARCHAR(150) NULL
  status                ENUM(unlicensed, active, expiring, expired, suspended, cancelled)
  legacy_reg_no         VARCHAR(100) NULL
  legacy_notes          TEXT NULL
  timestamps, created_by, updated_by, deleted_at

dealer_owners                          -- one person may own many shops
  dealer_id             FK dealers
  person_id             FK persons
  start_date            DATE
  end_date              DATE NULL
  timestamps, created_by, updated_by, deleted_at
```

### 4.7 Workflow and Checklists

```
workflow_stages
  entity_type           ENUM(company, dealer)
  sequence              TINYINT
  code                  VARCHAR(30)   (submission, progress_review, file_review,
                                       deficiency, fee, higher_approval, issuance)
  name                  VARCHAR(150)
  description           TEXT
  applies_to            ENUM(new, renewal, both)
  sla_days              SMALLINT       (maximum days allowed in this stage)
  is_active             BOOLEAN        (higher_approval = false in Phase 1)
  is_skippable          BOOLEAN        (e.g. deficiency, when there is nothing deficient)
  required_permission   VARCHAR(100)   (permission needed to complete the stage)
  UNIQUE(entity_type, sequence)

checklist_templates
  entity_type           ENUM(company, dealer)
  application_type      ENUM(new, renewal)
  version_no            SMALLINT
  name                  VARCHAR(150)
  status                ENUM(draft, published, archived)
  published_at          DATETIME NULL
  published_by          FK users NULL
  UNIQUE(entity_type, application_type, version_no)
  -- Only ONE 'published' template per (entity_type, application_type).
  -- A published template is read-only. Editing creates a new draft version.

checklist_items
  template_id           FK checklist_templates
  sort_order            SMALLINT
  annex_code            VARCHAR(5)     (A … AA)
  title                 VARCHAR(255)
  description           TEXT NULL
  form_reference        VARCHAR(30) NULL   (e.g. Form-6)
  is_required           BOOLEAN
  requires_upload       BOOLEAN
  allowed_file_types    VARCHAR(100)       (e.g. pdf,jpg,png)
  max_files             TINYINT default 1
  attestation_required  ENUM(none, gazetted_officer, notary_public, oath_commissioner)
  requires_validity_dates   BOOLEAN        (the upload must include issue and expiry dates)
  must_cover_license_period BOOLEAN        (expiry must be on or after the license end date)
  portal_uploadable     BOOLEAN            (the company can upload this through the portal)
```

### 4.8 Applications

```
license_applications
  application_no        VARCHAR(30) UNIQUE   (e.g. APP-C-2026-0045 / APP-D-2026-0310)
  licensable_type       ENUM(company, dealer)
  licensable_id         BIGINT
  application_type      ENUM(new, renewal, restoration)
  checklist_template_id FK checklist_templates   (checklist version locked at filing)
  previous_license_id   FK licenses NULL
  submitted_via         ENUM(office, portal)
  submitted_by_user_id  FK users
  submitted_at          DATETIME NULL
  diary_no              VARCHAR(50) NULL
  received_by           FK users NULL
  received_at           DATETIME NULL
  total_pages           SMALLINT NULL        (from the checklist certificate)
  current_stage_id      FK workflow_stages NULL
  status                ENUM(draft, submitted, under_review, deficiency_issued,
                             fee_pending, ready_to_issue, issued, rejected, withdrawn)
  late_days             INT default 0        (system calculated, informational)
  fee_amount            DECIMAL(12,2)        (from fee_structures at submission date)
  penalty_total         DECIMAL(12,2)        (sum of application_penalties.final_amount)
  total_payable         DECIMAL(12,2)        (fee_amount + penalty_total)
  rejection_reason      TEXT NULL
  open_flag             TINYINT GENERATED   (1 while status is not issued/rejected/withdrawn, else NULL)
  UNIQUE(licensable_type, licensable_id, open_flag)   -- only one open application (Rule R-10)
  timestamps, created_by, updated_by, deleted_at

application_stage_logs
  application_id        FK license_applications
  stage_id              FK workflow_stages
  entered_at            DATETIME
  due_at                DATETIME           (entered_at + sla_days)
  completed_at          DATETIME NULL
  acted_by              FK users NULL
  outcome               ENUM(completed, skipped, returned, rejected) NULL
  remarks               TEXT NULL
  sla_breached          BOOLEAN default false

application_checklist_items
  application_id        FK license_applications
  checklist_item_id     FK checklist_items
  title_snapshot        VARCHAR(255)
  annex_code_snapshot   VARCHAR(5)
  status                ENUM(pending, submitted, verified, deficient, not_applicable)
  page_count            SMALLINT NULL
  officer_remarks       TEXT NULL
  verified_by           FK users NULL
  verified_at           DATETIME NULL

application_penalties                -- entered MANUALLY by the officer (Decision D18)
  application_id        FK license_applications
  penalty_type          ENUM(late_renewal, no_technical_staff, restoration, other)
  reference_rate        DECIMAL(12,2) NULL   (copied from fee_structures for guidance)
  helper_info           VARCHAR(255) NULL    (e.g. "Submitted 12 days after expiry";
                                              "Records show 2 months below minimum staff")
  basis                 VARCHAR(255)         (officer's basis, e.g. "12 days × 500")
  standard_amount       DECIMAL(12,2) NULL   (the full amount before any waiver, entered by officer)
  final_amount          DECIMAL(12,2)        (amount actually charged)
  is_waived_or_reduced  BOOLEAN GENERATED    (final_amount < standard_amount)
  waiver_reason         TEXT NULL            (REQUIRED when waived or reduced)
  waiver_order_no       VARCHAR(100) NULL
  waiver_approved_by    FK users NULL        (must hold 'penalties.waive')
  entered_by            FK users
  entered_at            DATETIME

deficiency_letters                 -- deficiency stage
  application_id        FK license_applications
  letter_no             VARCHAR(50) UNIQUE
  issued_at             DATE
  reply_due_date        DATE
  response_received_at  DATE NULL
  status                ENUM(open, resolved, lapsed)
  pdf_path              VARCHAR(255) NULL

deficiency_letter_items
  deficiency_letter_id  FK deficiency_letters
  application_checklist_item_id FK application_checklist_items
  remarks               TEXT

challans                           -- fee stage
  application_id        FK license_applications
  challan_no            VARCHAR(50) UNIQUE
  bank_name             VARCHAR(100)
  branch                VARCHAR(100) NULL
  payment_date          DATE
  amount                DECIMAL(12,2)
  document_id           FK documents NULL
  verification_status   ENUM(pending, verified, rejected)
  verified_by           FK users NULL
  verified_at           DATETIME NULL
  remarks               VARCHAR(255) NULL

challan_items
  challan_id            FK challans
  item_type             ENUM(registration_fee, renewal_fee, late_renewal_penalty,
                             no_technical_staff_penalty, restoration_penalty, other)
  amount                DECIMAL(12,2)
```

### 4.9 Licenses

```
licenses
  license_no            VARCHAR(100) UNIQUE
  licensable_type       ENUM(company, dealer)
  licensable_id         BIGINT
  application_id        FK license_applications NULL   (NULL for imported history)
  license_kind          ENUM(registration, renewal, restoration)
  renewal_count         SMALLINT default 0
  valid_from            DATE
  valid_to              DATE
  issued_at             DATETIME NULL
  issued_by             FK users NULL
  status                ENUM(active, expired, suspended, cancelled, superseded)
  certificate_path      VARCHAR(255) NULL
  verification_token    CHAR(40) UNIQUE       (random, used in the QR URL)
  documents_status      ENUM(complete, incomplete, not_applicable)
                        (set at issue; not_applicable for legacy licenses)
  documents_completed_at DATETIME NULL        (when the last required item was verified)
  issued_with_enforcement BOOLEAN             (value of the setting at the time of issue)
  is_legacy             BOOLEAN default false (imported from Excel)
  CHECK(valid_to > valid_from)
  timestamps, created_by, updated_by, deleted_at

license_status_history
  license_id            FK licenses
  from_status, to_status
  reason                TEXT
  order_no              VARCHAR(100) NULL
  effective_date        DATE
  changed_by            FK users

license_number_sequences
  entity_type           ENUM(company, dealer)
  district_id           FK districts NULL   (NULL for companies)
  year                  YEAR
  last_serial           INT
  UNIQUE(entity_type, district_id, year)
```

### 4.10 Documents

```
documents
  documentable_type     VARCHAR(50)   (company, dealer, person, application_checklist_item,
                                       license, challan, deficiency_letter)
  documentable_id       BIGINT
  document_type_id      FK document_types
  title                 VARCHAR(255)
  file_path             VARCHAR(255)
  original_name         VARCHAR(255)
  mime_type             VARCHAR(100)
  size_bytes            INT
  file_hash             CHAR(64) INDEX      (SHA-256, for duplicate detection)
  issue_date            DATE NULL
  expiry_date           DATE NULL
  attested_by           ENUM(none, gazetted_officer, notary_public, oath_commissioner)
  version_no            SMALLINT default 1
  replaced_by_id        FK documents NULL
  uploaded_by           FK users
  uploaded_via          ENUM(office, portal, import)
  verification_status   ENUM(pending, verified, rejected)
  verified_by           FK users NULL
  verified_at           DATETIME NULL
  rejection_reason      VARCHAR(255) NULL
  timestamps, deleted_at
```

### 4.11 Notifications, Activity Log and Import

```
notifications          -- Laravel standard table (in-app notifications for staff and portal users)

activity_logs          -- INSERT-ONLY (app DB user has INSERT + SELECT only)
  user_id               FK users NULL      (NULL = system job)
  user_type             ENUM(staff, company, system, public)
  action                VARCHAR(40)  (created, updated, deleted, restored, login, logout,
                                      login_failed, viewed, downloaded, exported, verified,
                                      rejected, approved, issued, suspended, cancelled,
                                      penalty_entered, penalty_waived, warning_overridden,
                                      settings_changed, template_published)
  subject_type          VARCHAR(50)
  subject_id            BIGINT
  description           VARCHAR(255)
  old_values            JSON NULL
  new_values            JSON NULL
  ip_address            VARCHAR(45)
  user_agent            VARCHAR(255)
  channel               ENUM(web, mobile, system)
  route                 VARCHAR(150)
  batch_uuid            CHAR(36)     (groups all rows produced by one user action)
  created_at            DATETIME(3)  (no updated_at)
  INDEX(subject_type, subject_id), INDEX(user_id, created_at)

import_batches
  file_name, sheet_name, imported_by, started_at, finished_at,
  total_rows, success_rows, exception_rows, status ENUM(running, completed, failed)

import_exceptions
  batch_id              FK import_batches
  row_no                INT
  raw_data              JSON
  issue_type            ENUM(bad_date, possible_duplicate, missing_cnic, invalid_cnic,
                             unknown_district, unparsed_product, missing_required, other)
  issue_details         VARCHAR(255)
  resolution_status     ENUM(open, fixed, merged, skipped)
  resolved_by           FK users NULL
  resolved_at           DATETIME NULL
  notes                 TEXT NULL
```

### 4.12 Relationship Summary

```
companies ─┬─< company_people >── persons ──< dealer_owners >─┬─ dealers
           ├─< company_premises                                │
           ├─< company_assets                                  │
           ├─< company_products >── products                   │
           ├─< users (portal)                                  │
           └──────────────┐                    ┌───────────────┘
                          ▼                    ▼
                license_applications (licensable) ──< application_checklist_items >── checklist_items
                          │                                         │
                          ├─< application_stage_logs >── workflow_stages
                          ├─< application_penalties
                          ├─< deficiency_letters ──< deficiency_letter_items
                          ├─< challans ──< challan_items
                          └──1 licenses ──< license_status_history

documents (polymorphic) → companies | dealers | persons | application items | licenses | challans
activity_logs (polymorphic) → any record
```

---

## 5. Initial (Seed) Data

### 5.1 Settings

| Key | Default | Group | UI editable |
|---|---|---|---|
| company_license_period_months | 12 | licensing | Yes |
| dealer_license_period_months | 12 | licensing | Yes |
| renewal_window_days | 60 | licensing | Yes |
| min_technical_staff_company | 2 | licensing | Yes |
| deficiency_reply_days | 15 | licensing | Yes |
| enforce_document_requirements | **false** | licensing | Yes |
| alert_red_days | 30 | alerts | Yes |
| alert_amber_days | 90 | alerts | Yes |
| max_upload_size_mb | 10 | general | Yes |
| company_license_no_pattern | `DPP/C/{YYYY}/{SERIAL:4}{RENEWAL}` | numbering | Yes |
| dealer_license_no_pattern | `DPP/D/{DISTRICT}/{YYYY}/{SERIAL:4}{RENEWAL}` | numbering | Yes |
| application_no_pattern | `APP-{C/D}-{YYYY}-{SERIAL:4}` | numbering | Yes |
| public_verify_base_url | `https://<domain>/verify/` | general | No |

The license number patterns support these placeholders:

| Placeholder | Meaning |
|---|---|
| `{YYYY}` | Year of issue |
| `{SERIAL:n}` | Serial number, zero-padded to n digits |
| `{DISTRICT}` | District code |
| `{RENEWAL}` | Blank for a new license, `/R1`, `/R2`… for renewals |

The final format can be changed later without any code change (Decision D19). Existing license numbers are never renumbered.

**How `enforce_document_requirements` works:**

| | Setting OFF (default, during data entry) | Setting ON |
|---|---|---|
| Missing or unverified required documents | License **can** be issued | License **cannot** be issued |
| At issuance | The license is saved with documents status **Incomplete**, and the company or dealer shows a "📄 Documents incomplete" badge for that license period | The license is saved with documents status **Complete** |
| After issuance | The application's checklist stays open. Documents can be uploaded and verified at any time during the license period. When the last required item is verified, the license becomes **Complete** automatically. | — |
| "Must cover license period" check (R-20) | Warning only | Blocks issuance |

Changing the setting affects only licenses issued **after** the change. Existing licenses keep their documents status, and every change of the setting is recorded in the activity log.

### 5.2 Fee Structures

The table is created empty. Amounts are inserted directly into the database before go-live, one row per fee type and entity type. The penalty reference rates from the checklist are Rs 500 per day for late renewal, Rs 40,000 per month for no technical staff, and Rs 50,000 per month for restoration.

### 5.3 Workflow Stages

The same stages are seeded for both companies and dealers.

| Seq | Code | Name | Applies to | SLA (days) | Active | Skippable |
|---|---|---|---|---|---|---|
| 1 | submission | Application submitted (letterhead / email / portal) | both | — | Yes | No |
| 2 | progress_review | Review of last period's progress | renewal | 3 | Yes | No |
| 3 | file_review | File submission and checklist review | both | 15 | Yes | No |
| 4 | deficiency | Deficiency letter (if any) | both | 4 | Yes | Yes |
| 5 | fee | Fee and penalty deposit (challan) | both | 10 | Yes | No |
| 6 | higher_approval | Approval by Secretary / DG | both | 3 | **No** | — |
| 7 | issuance | Issuance of license certificate | both | 3 | Yes | No |

The official steps "Scrutiny" and "Interview" are not used in Phase 1 (Decision D25).

### 5.4 Checklist Templates (Version 1)

The **same items are seeded into all four templates**:
- Company – New
- Company – Renewal
- Dealer – New
- Dealer – Renewal

Items marked (*) in Form-A are **included only in the Renewal templates**. Each template can be edited independently afterwards.

| Annex | Item | Form | Required | Attestation | Covers license period | In New | In Renewal |
|---|---|---|---|---|---|---|---|
| A | Application on Form-C 12 (new) / Form-C 14 (renewal), signed by CEO/Director with full contact details | C-12/C-14 | Yes | none | No | ✓ | ✓ |
| B | Certificate of Incorporation | — | Yes | gazetted | No | ✓ | ✓ |
| C | Memorandum & Articles of Association | — | Yes | gazetted | No | ✓ | ✓ |
| D | List of Board of Directors with addresses and specimen signatures | — | Yes | gazetted | No | ✓ | ✓ |
| E | NTN and Income Tax certificates | — | Yes | gazetted | No | ✓ | ✓ |
| F | Bank certificate with one-year bank statement | — | Yes | gazetted | No | ✓ | ✓ |
| G | PCPA / CropLife membership certificate | — | Yes | gazetted | No | ✓ | ✓ |
| H | Registration certificates as distributor in Punjab, Sindh and KP | — | Yes | gazetted | No | ✓ | ✓ |
| I | Previous registration certificate(s) in Balochistan (*) | — | Yes | gazetted | No | — | ✓ |
| J | Audit report for the last period (*) | — | Yes | gazetted | No | — | ✓ |
| K | Evidence of commercial advertisement on agriculture websites (*) | — | Yes | none | No | — | ✓ |
| L | Appointment of two technical staff / agriculture graduates | Form-6 | Yes | gazetted | Yes | ✓ | ✓ |
| M | Pay record of technical and other staff (*) | Form-7 | Yes | none | No | — | ✓ |
| N | Sale data of products (*) | Form-8 | Yes | none | No | — | ✓ |
| O | Sale record of restricted pesticides (*) | Form-8(B) | Yes | none | No | — | ✓ |
| P | Evidence of R&D and farmer advisory services (*) | Form-9 | Yes | none | No | — | ✓ |
| Q | Advisory slip sample (new) / used advisory slip books (renewal) | Form-10 | Yes | none | No | ✓ | ✓ |
| R | List of registered dealers, dealership certificates and model shops | Form-11 | Yes | none | No | ✓ | ✓ |
| S | Product import certificate or purchase agreement with importer | — | Yes | notary | **Yes** | ✓ | ✓ |
| T | Products to be registered, with DPP registration, test reports, labels and leaflets | Form-12 | Yes | none | No | ✓ | ✓ |
| U | Approximate business volume (Rs M) for the next period | Form-12 | Yes | none | No | ✓ | ✓ |
| V | Pesticide samples and dummies | Form-12 | Yes | none | No | ✓ | ✓ |
| W | List of offices in Pakistan and Balochistan | Form-13 | Yes | none | No | ✓ | ✓ |
| X | List of capital / assets in Balochistan | Form-13 | **No** | none | No | ✓ | ✓ |
| Y | Samples drawn (fit / unfit) (*) | Form-14 | Yes | none | No | — | ✓ |
| Z | Medical check-up and treatment record of staff (*) | Form-15 | Yes | none | No | — | ✓ |
| AA | Bank treasury challan (fee / penalties) | — | Yes | none | No | ✓ | ✓ |

The 17 items without (*) go into the New templates; all 27 go into the Renewal templates. After seeding, the officer should review the dealer templates and remove company-only items (e.g. Memorandum & Articles) using the checklist editor.

### 5.5 Roles

The seeded roles are:
- Super Admin
- Director
- Registration Officer
- District Officer
- Data Entry Operator
- Auditor
- Company Admin
- Company Staff

Their permissions are described in Section 8.

### 5.6 Lookups

- **Districts and tehsils:** seeded from the Dealers Excel file (44 districts), with short codes assigned.
- **Provinces:** Punjab, Sindh, KP, Balochistan, Islamabad, GB and AJK.
- **Qualifications and document types:** seeded with the common values.

---

## 6. Business Rules and Validations

**Type key:**
- **BLOCK:** the action is refused.
- **WARN:** the user must confirm and give a reason, and the reason is logged.
- **AUTO:** the system does it automatically.

### 6.1 People and Staff

| ID | Rule | Type |
|---|---|---|
| R-01 | A person can be active technical staff at only one company at a time. The error message names the other company and its start date. | BLOCK (also enforced by a DB unique index) |
| R-02 | To move staff to another company, the end date at the previous company must be entered first, by the previous company or by an officer. | BLOCK |
| R-03 | A person who is active technical staff cannot be added as an active dealer owner, and vice versa. | BLOCK |
| R-04 | One person may own multiple dealer shops. | Allowed |
| R-05 | CNIC must be exactly 13 digits and unique across all persons. | BLOCK |
| R-06 | Staff are always added CNIC-first. If the CNIC exists, the existing person record is linked and no duplicate is created. | AUTO |
| R-07 | A mobile number already belonging to a different person. | WARN |
| R-08 | The end date cannot be before the start date, and the start date cannot be in the future by more than 30 days. | BLOCK |
| R-09 | A person with `cnic_pending = true` (legacy) cannot be counted as verified staff, and no license can be issued while any linked owner or staff member is CNIC-pending. | BLOCK |

### 6.2 Companies, Dealers and Products

| ID | Rule | Type |
|---|---|---|
| R-10 | Only one open application per company or dealer at a time. | BLOCK (DB unique index) |
| R-11 | NTN must be unique across companies. | BLOCK |
| R-12 | A company name similar to an existing one (normalized match or high similarity score). | WARN |
| R-13 | The same dealer shop name in the same district, or the same business address. | WARN |
| R-14 | Brand names must be unique within a company. | BLOCK |
| R-15 | An identical uploaded file (same SHA-256) already attached to a different company, dealer or person, e.g. the same degree reused. | WARN, shown to officers only |

### 6.3 Applications and Licensing

| ID | Rule | Type |
|---|---|---|
| R-16 | Penalties are entered manually by the officer. The system shows the reference rate and helper info (days late; months with fewer than the minimum staff) but never adds a penalty by itself. | Manual |
| R-17 | If the final penalty is lower than the standard amount entered, a waiver reason is required and the waiver must be approved by a user holding `penalties.waive`. | BLOCK |
| R-18 | A renewal cannot be submitted before the renewal window opens (`renewal_window_days` before expiry). | BLOCK |
| R-19 | A license cannot be issued unless all of the following hold:<br>• all challans are verified<br>• the sum of verified challans is at least the total payable<br>• the company has at least `min_technical_staff_company` verified active technical staff<br>• no linked person is CNIC-pending<br>**Only when `enforce_document_requirements` is ON:** every required checklist item is uploaded and Verified (or N/A) | BLOCK |
| R-20 | A document on an item marked "must cover license period" must have an expiry date on or after the new license's valid-to date. | BLOCK when enforcement is ON; WARN when OFF |
| R-20a | When a license is issued with enforcement OFF and any required item is missing or unverified, the license's documents status is set to Incomplete. | AUTO |
| R-20b | Checklist items of an issued application remain open for upload and verification until the license expires. When the last required item is verified, the license's documents status changes to Complete and the change is logged. | AUTO |
| R-20c | Company assets (company_assets) and person qualifications are optional and are never required for any action. | Allowed |
| R-21 | Challan number must be unique system-wide. | BLOCK |
| R-22 | The checklist version is locked when the application is created. Later template changes do not affect it. | AUTO |
| R-23 | A published checklist template cannot be edited, only versioned. | BLOCK |
| R-24 | Validity dates for a new license:<br>• **Renewal filed on time:** valid-from = previous license's valid-to + 1 day<br>• **Renewal filed late, or a new license:** valid-from = issue date<br>• **In all cases:** valid-to = valid-from + license period − 1 day | AUTO |
| R-25 | When a new license is issued, the previous license's status becomes "superseded". | AUTO |
| R-26 | Stages must be completed in sequence. Inactive stages are skipped automatically. A skippable stage needs an explicit "Skip" with remarks. | BLOCK / AUTO |
| R-27 | When a stage passes its due date, it is flagged as SLA-breached and highlighted on the dashboard. | AUTO |

### 6.4 Portal and General

| ID | Rule | Type |
|---|---|---|
| R-28 | Company users can only read or write data of their own company. This is enforced in every API query, not only hidden on screen. | BLOCK |
| R-29 | Company information (name, NTN, legal type, incorporation, head office address, CEO/directors) is read-only on the portal. The portal shows "Contact the Directorate to change this information." | BLOCK |
| R-30 | Everything submitted through the portal (staff, documents, products) is saved as Pending, and only Verified records count toward any rule. | AUTO |
| R-31 | Nothing is hard-deleted. A soft delete requires a reason, which is logged. | AUTO |
| R-32 | Every create, update, delete, login, download, export, approval and warning override is written to the activity log. | AUTO |

---

## 7. Status Logic and Expiry Alerts

A scheduled job runs every night at 00:30. It updates license statuses first, then recalculates the status of every company and dealer.

**License status:** a license with status `active` and `valid_to` earlier than today becomes `expired`.

**Company / dealer status** is checked in this order, and the first match applies:

1. If a manual status of `suspended` or `cancelled` is set, that status is kept.
2. If there is no license at all, the status is `unlicensed`.
3. If the current license's valid-to date has passed, the status is `expired`.
4. If the valid-to date is within `alert_amber_days`, the status is `expiring`.
5. Otherwise, the status is `active`.

**Alert levels shown on the dashboard:**

| Level | Condition |
|---|---|
| 🔴 Expired | The valid-to date is before today |
| 🟠 Under 30 days | 0–30 days remaining |
| 🟡 Under 90 days | 31–90 days remaining |
| 🔵 Renewal window open | Within `renewal_window_days` and no renewal application submitted yet |
| ⚠ SLA breached | An application stage is past its due date |
| ⚠ Document expiring | A verified document's expiry falls within the current license period |
| 📄 Documents incomplete | The current license was issued with documents status Incomplete |

**In-app notifications:**
- Portal users are notified when their renewal window opens, when a deficiency letter is issued, and when a submission is verified or rejected.
- Officers are notified when new portal submissions arrive.

---

## 8. Roles and Permissions

### 8.1 Permission Keys

```
dashboard.view
companies.view        companies.create      companies.update      companies.delete
dealers.view          dealers.create        dealers.update        dealers.delete
persons.view          persons.update
staff.manage          staff.verify
products.manage       products.verify       products_master.manage
documents.upload      documents.verify      documents.download
applications.view     applications.create   applications.process
applications.reject
penalties.enter       penalties.waive
challans.verify
licenses.issue        licenses.suspend      licenses.cancel       licenses.restore
checklists.manage     workflow.manage       settings.manage       lookups.manage
users.manage          roles.manage
activity_logs.view
imports.run           imports.resolve
exports.run
portal.access         portal.users.manage   portal.renewal.submit
```

### 8.2 Default Role Matrix

| Permission area | Super Admin | Director | Registration Officer | District Officer | Data Entry | Auditor | Company Admin | Company Staff |
|---|---|---|---|---|---|---|---|---|
| View companies | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | Own | Own |
| View dealers | ✓ | ✓ | ✓ | Own district | ✓ | ✓ | — | — |
| Create/edit companies | ✓ | ✓ | ✓ | — | ✓ | — | — | — |
| Create/edit dealers | ✓ | ✓ | ✓ | Own district | ✓ | — | — | — |
| Manage staff | ✓ | ✓ | ✓ | — | ✓ | — | Own (pending) | Own (pending) |
| Verify portal submissions | ✓ | ✓ | ✓ | Dealers, own district | — | — | — | — |
| Process applications | ✓ | ✓ | ✓ | Dealers, own district | — | — | — | — |
| Enter penalties | ✓ | ✓ | ✓ | Dealers, own district | — | — | — | — |
| Waive/reduce penalties | ✓ | ✓ | — | — | — | — | — | — |
| Verify challans | ✓ | ✓ | ✓ | — | — | — | — | — |
| Issue license | ✓ | ✓ | — | — | — | — | — | — |
| Suspend/cancel/restore | ✓ | ✓ | — | — | — | — | — | — |
| Submit renewal (portal) | — | — | — | — | — | — | ✓ | — |
| Upload documents | ✓ | ✓ | ✓ | ✓ | ✓ | — | ✓ | ✓ |
| Checklists/workflow/settings | ✓ | ✓ | — | — | — | — | — | — |
| Users & roles | ✓ | — | — | — | — | — | Own company users | — |
| Activity logs | ✓ | ✓ | — | — | — | ✓ | — | — |
| Data import | ✓ | — | — | — | — | — | — | — |
| Export lists to Excel | ✓ | ✓ | ✓ | Own district | — | ✓ | — | — |

"Own" and "own district" are enforced by data scoping in the API, in addition to the permission checks.

---

## 9. API Endpoint Catalogue (v1)

All endpoints are prefixed with `/api/v1`. List endpoints support `?search=`, `?filter[...]=`, `?sort=`, `?page=` and `?per_page=`.

| Module | Method & Path | Purpose |
|---|---|---|
| Auth | POST `/auth/login`, POST `/auth/logout`, GET `/auth/me` | Login, logout, and the current user with permissions |
| Auth | POST `/auth/password/forgot`, POST `/auth/password/reset`, POST `/auth/password/change` | Password management |
| Dashboard | GET `/dashboard/summary`, GET `/dashboard/expiry-alerts` | Cards and alert lists |
| Search | GET `/search?type=&q=` | Quick search by company, dealer, license, CNIC, mobile or district |
| Companies | GET/POST `/companies`, GET/PUT/DELETE `/companies/{id}` | CRUD |
| Companies | GET `/companies/{id}/timeline` | License history |
| Companies | POST `/companies/check-duplicate` | Name/NTN pre-check (returns warnings) |
| Company people | GET/POST `/companies/{id}/people`, PUT `/company-people/{id}`, POST `/company-people/{id}/end` | Staff and officers |
| Persons | GET `/persons/lookup?cnic=` | CNIC-first lookup, with the person's current roles and blocking conflicts |
| Persons | GET/PUT `/persons/{id}` | Person profile |
| Premises / Assets | `/companies/{id}/premises`, `/companies/{id}/assets` | CRUD |
| Products | `/products` (master), `/companies/{id}/products` | CRUD |
| Dealers | GET/POST `/dealers`, GET/PUT/DELETE `/dealers/{id}`, `/dealers/{id}/owners` | CRUD |
| Documents | POST `/documents` (multipart), GET `/documents/{id}/download-url`, PUT `/documents/{id}` | Upload, temporary download link, metadata |
| Verification | GET `/verifications?type=staff\|document\|product`, POST `/verifications/{type}/{id}/approve\|reject` | Portal submission queue |
| Applications | GET/POST `/applications`, GET `/applications/{id}` | List, create, detail |
| Applications | POST `/applications/{id}/stages/{stage}/complete\|skip` | Workflow actions |
| Applications | PUT `/applications/{id}/checklist-items/{item}` | Verify, mark deficient or N/A, page count |
| Applications | `/applications/{id}/deficiency-letters`, `/penalties`, `/challans` | Sub-records |
| Applications | GET `/applications/documents-incomplete` | Issued applications still awaiting documents |
| Applications | POST `/applications/{id}/penalties/{p}/approve-waiver` | Waiver approval |
| Applications | POST `/applications/{id}/issue`, POST `/applications/{id}/reject` | Final actions |
| Licenses | GET `/licenses`, GET `/licenses/{id}`, GET `/licenses/{id}/certificate` | View and download certificate |
| Licenses | POST `/licenses/{id}/suspend\|cancel\|restore` | Enforcement actions |
| Settings | GET/PUT `/settings` | Editable settings only |
| Checklists | `/checklist-templates`, POST `/checklist-templates/{id}/new-version`, POST `/checklist-templates/{id}/publish`, `/checklist-templates/{id}/items` (CRUD, reorder) | Checklist editor |
| Workflow | GET/PUT `/workflow-stages` | Stage settings |
| Lookups | `/districts`, `/tehsils`, `/provinces`, `/qualifications`, `/document-types` | CRUD |
| Users | `/users`, `/roles`, `/permissions` | Admin |
| Activity | GET `/activity-logs` | Read-only |
| Imports | POST `/imports`, GET `/imports/{id}`, GET/PUT `/import-exceptions/{id}` | Migration |
| Exports | GET `/exports/{list}?filters…` | Excel export of lists |
| Portal | GET `/portal/dashboard`, GET `/portal/company`, `/portal/staff`, `/portal/documents`, `/portal/products`, `/portal/applications`, `/portal/users` | Company-scoped endpoints |
| Public | GET `/public/verify/{token}` | Minimal license info (no login required, rate-limited) |

---

## 10. Screen Sketches — Department Side

### Navigation (left sidebar)
```
Dashboard
Companies
Dealers
Persons (CNIC search)
Applications
Verification Queue
Licenses
Settings ▸ General | Checklists | Workflow | Lookups | Products Master
Users & Roles
Activity Logs
Data Import
```
Menu items appear only when the user has the matching permission.

### S1 — Login
```
┌──────────────────────────────────────────────┐
│      DIGITAL PLANT PROTECTION SYSTEM         │
│   Directorate of Plant Protection, Quetta    │
├──────────────────────────────────────────────┤
│  Email:    [______________________]          │
│  Password: [______________________]          │
│  [ Login ]              Forgot password?     │
│                                              │
│  5 failed attempts → locked for 15 minutes   │
└──────────────────────────────────────────────┘
```
On first login, the user is taken straight to the "Change Password" screen.

### S2 — Main Dashboard
```
┌────────────────────────────────────────────────────────────────┐
│ DIRECTORATE OF PLANT PROTECTION - MAIN DASHBOARD   [User ▼] 🔔 │
├──────────────────┬──────────────────┬──────────────────────────┤
│ 🏢 COMPANIES     │ 🏪 DEALERS       │ 📋 LICENSES              │
│ Total:    ___    │ Total:    ___    │ New (this year):   ___   │
│ Active:   ___    │ Active:   ___    │ Renewed (this yr): ___   │
│ Expiring: ___    │ Expiring: ___    │ Expired:           ___   │
│ Expired:  ___    │ Expired:  ___    │ Suspended:         ___   │
├──────────────────┴──────────────────┴──────────────────────────┤
│ 📥 APPLICATIONS IN PROCESS          │ ⏳ PENDING VERIFICATION   │
│ New: ___  Renewal: ___              │ Staff: ___  Documents: ___│
│ Deficiency open: ___                │ Products: ___             │
│ SLA breached: ___ (red)             │ [Open Queue]              │
├─────────────────────────────────────┴───────────────────────────┤
│ 📄 DOCUMENTS INCOMPLETE (current license period)                │
│ Companies: ___   Dealers: ___                        [View list] │
├────────────────────────────────────────────────────────────────┤
│ 🔍 QUICK SEARCH                                                │
│ [Company|Dealer|License|CNIC|Mobile|District ▼] [_______] [Go] │
├────────────────────────────────────────────────────────────────┤
│ EXPIRY ALERTS       [Companies] [Dealers]   District [All ▼]   │
│ 🔴 Expired (__)   🟠 <30 Days (__)   🟡 <90 Days (__)           │
│ 🔵 Renewal window open, not applied (__)                        │
│ Name                  │ License No   │ Expiry    │ Days │      │
│ A.M.B. Agro Division  │ DPP/C/…      │ 26-11-26  │  62  │[View]│
│ …                                                   [See all]  │
└────────────────────────────────────────────────────────────────┘
```
- Every number on the dashboard is clickable and opens a pre-filtered list.
- A District Officer sees only the figures for their own district.
- The Pest Alerts, Inspections and Samples cards are added in Phase 2.

### S3 — Companies List
```
┌─────────────────────────────────────────────────────────────────┐
│ COMPANIES                                       [+ New Company] │
│ Search [_________]  Status [All ▼]  PCPA [All ▼]  Expiry [▼]    │
├──────┬───────────────────────┬──────────┬───────────┬───────────┤
│ Code │ Name                  │ NTN      │ Expiry    │ Status    │
│ C001 │ A.M.B. Agro Division  │ 5440004… │ 26-11-26  │ 🟡 Expiring│
│ C002 │ AASI Agro Chemical    │ 4399191  │ 02-01-27  │ 🟢 Active  │
│ C003 │ Abdullah Haseeb…      │ —        │ 21-02-20  │ 🔴 Expired │
├─────────────────────────────────────────────────────────────────┤
│ Showing 1–25 of ___                    [Export Excel] [< 1 2 >] │
└─────────────────────────────────────────────────────────────────┘
```

### S4 — New / Edit Company
```
┌─────────────────────────────────────────────────────────────┐
│ NEW COMPANY                                                 │
│ Name:  [AMB Agro Division________]                          │
│  ⚠ Similar existing company: "A.M.B. Agro Division" (C001)  │
│    [View it]  [Continue anyway – reason: ____________]      │
│ Legal type [Pvt Ltd ▼]  NTN [_______]  ✖ NTN already exists │
│ Incorporation No [____]  Date [__/__/____]                  │
│ Head office address [__________________]  City [____]       │
│ Province [Punjab ▼] Landline [____] Mobile [____] Email [__]│
│ PCPA ☐  CropLife ☐  Membership No [____]                    │
│                                    [Cancel] [Save]          │
└─────────────────────────────────────────────────────────────┘
```
Duplicate checks run as the user types (after a short pause) and again when the form is saved.

### S5 — Company Profile
```
┌──────────────────────────────────────────────────────────────────┐
│ A.M.B. AGRO DIVISION   (C001)     🟢 ACTIVE   📄 DOCS INCOMPLETE  │
│ License: DPP/C/2025/0012/R3    Valid: 27-11-25 → 26-11-26 (62 d) │
│ [Edit] [New Application] [Suspend] [Certificate] [Activity]      │
├──────────────────────────────────────────────────────────────────┤
│ Overview │ People │ Tech Staff │ Premises │ Products │ Licenses  │
│ Applications │ Documents │ Portal Users │ Activity               │
└──────────────────────────────────────────────────────────────────┘
```

**Overview tab**
```
│ COMPANY PROFILE                                                  │
│ Name, Legal type, NTN, Incorporation, PCPA/CropLife              │
│ CONTACT: Address | Landline | Mobile | Email | Contact Person    │
│ ALERTS: ⚠ Only 1 verified technical staff (minimum 2)            │
│         📄 Documents incomplete: 12 of 27 required items missing  │
│            or unverified  [Complete Documents]                   │
│         ⚠ Purchase agreement expires before license end          │
```

**People tab** (CEO, directors, contacts and authorized representatives)
```
│ Role      │ Name              │ CNIC        │ Mobile  │ From │ To  │
│ CEO       │ Haji Abdul Maliq  │ 54400-…-1   │ 0300…   │ 2017 │  —  │
```

**Tech Staff tab**
```
│ [+ Add Staff]   Show: [Current ▼]                                │
│ Name     │ CNIC      │ Qualification*│ Mobile │ Since   │ Status │
│ M Ashraf │ 54400-…   │ B.Sc (Hons)   │ 0300…  │ 2021    │ ✓ Ver. │
│ Hamayoun │ 54400-…   │ M.Sc Agri     │ 0343…  │ 2026    │ ⏳ Pend│
│ Row actions: [View Person] [End Employment] [Verify]             │
│ * shows "—" when no qualification has been entered (optional)    │
```

**Assets** (optional) are shown under the Premises tab as a second table, and may be left empty.

**Premises tab**
```
│ Type      │ Location / District   │ GPS          │ Contact  │ ⋯ │
│ Warehouse │ Kasi Plaza, Quetta    │ 30.19, 67.00 │ …        │   │
│ (Inspection column added in Phase 2)                            │
```

**Products tab**
```
│ Brand       │ Generic / Conc. / Form.  │ DPP Reg  │ Status    │
│ Fiumax      │ Flumioxazin 60% WP       │ …        │ Approved  │
```

**Licenses tab** (the license history timeline)
```
│ 2017-20 ──▶ 2020-23 ──▶ 2023-24 ──▶ 2024-25 ──▶ 2025-26 (current)│
│ License No │ Kind │ From │ To │ Status │ Documents │ [Certificate]│
│ DPP/C/…    │ Ren. │ 2025 │2026│ Active │ 📄 Incomplete │ [PDF]    │
```

**Documents tab**
```
│ Category: [All|Application|CNIC|License|Challan|Correspondence|  │
│            Agreement|Other]                        [+ Upload]    │
│ Title │ Type │ Expiry │ Version │ Via │ Status │ [View][History] │
```

**Portal Users tab**
```
│ Name │ Email │ Role │ Last login │ Active │ [Reset password]     │
```

**Activity tab:** the activity log filtered to this company and its related records.

### S6 — Add Technical Staff (CNIC-first)
```
┌─────────────────────────────────────────────────────────────┐
│ ADD TECHNICAL STAFF — A.M.B. Agro Division                  │
│ Step 1: CNIC [54400-1234567-1]  [Check]                     │
│                                                             │
│ Result A — BLOCKED:                                         │
│ ✖ Active technical staff at "Green Agro Pvt Ltd" since      │
│   01-03-2024. Employment must be ended there first.         │
│                                                             │
│ Result B — BLOCKED:                                         │
│ ✖ This person is the owner of dealer shop "Kisan Zarai      │
│   Markaz, Rakhni". Technical staff cannot be a dealer owner.│
│                                                             │
│ Result C — Person exists, no conflict:                      │
│ ✓ Found: Naveed Ahmed. Details pre-filled.                  │
│                                                             │
│ Result D — New person:                                      │
│ Step 2: Name [___] Father [___] Mobile [___] Email [___]    │
│   ⚠ Mobile 0300-3892412 belongs to "M Ashraf"               │
│ Qualification (optional) [B.Sc Agri ▼] Institution [__] Yr[_]│
│ Uploads (optional now): Degree 📎 CNIC 📎 Appointment 📎     │
│ Start date [__/__/____]                                     │
│                                          [Cancel] [Save]    │
└─────────────────────────────────────────────────────────────┘
```

### S7 — End Employment
```
┌─────────────────────────────────────────────┐
│ END EMPLOYMENT — M Ashraf at A.M.B. Agro     │
│ End date [__/__/____]                        │
│ Reason  [Resigned ▼] [____________]          │
│ Supporting document (optional) 📎            │
│ ⚠ Company will have 1 verified staff left    │
│   (minimum 2)                                │
│                         [Cancel] [Confirm]   │
└─────────────────────────────────────────────┘
```

### S8 — Person Profile (CNIC-centric)
```
┌─────────────────────────────────────────────────────────────┐
│ M ASHRAF   CNIC 54400-xxxxxxx-x   Mobile 0300-3892412       │
├─────────────────────────────────────────────────────────────┤
│ ROLES ACROSS SYSTEM                                         │
│ Technical Staff │ A.M.B. Agro Division │ 2021 → present     │
│ Technical Staff │ Lala Agro            │ 2018 → 2021 (ended)│
│ QUALIFICATIONS (optional) │ DOCUMENTS │ ACTIVITY            │
└─────────────────────────────────────────────────────────────┘
```

### S9 — Dealers List
```
┌─────────────────────────────────────────────────────────────────┐
│ DEALERS                                          [+ New Dealer] │
│ Search [____]  District [All ▼]  Tehsil [All ▼]  Status [All ▼] │
├──────────┬───────────────────────────┬──────────┬────────┬──────┤
│ Code     │ Shop Name                 │ District │ Expiry │Status│
│ D-AWR-01 │ Hamal Saba Zarai Markaz   │ Awaran   │ …      │ 🔴   │
│ D-BRK-04 │ Kisan Zarai Markaz        │ Barkhan  │ …      │ 🟢   │
│                                         [Export Excel] [< 1 2 >]│
└─────────────────────────────────────────────────────────────────┘
```

### S10 — Dealer Profile
```
┌──────────────────────────────────────────────────────────────────┐
│ KISAN ZARAI MARKAZ (D-BRK-04)                     🟢 ACTIVE        │
│ Barkhan › Rakhni   License: DPP/D/BRK/2026/0004/R2  to 30-06-27  │
│ [Edit] [New Application] [Suspend] [Certificate]                 │
├──────────────────────────────────────────────────────────────────┤
│ Overview │ Owners │ Licenses │ Applications │ Documents │Activity│
│ OVERVIEW: Shop name, Address, District, Tehsil, GPS, Mobile      │
│ OWNERS:   Name │ CNIC │ Mobile │ From │ To │ Other shops owned   │
└──────────────────────────────────────────────────────────────────┘
```
Owners are added with the same CNIC-first flow as S6. It blocks anyone who is active technical staff, and the "Other shops owned" column lists the person's other shops.

### S11 — Applications Work Queue
```
┌──────────────────────────────────────────────────────────────────┐
│ APPLICATIONS  [Company|Dealer]  Type [All ▼] Stage [All ▼]       │
│ Status [Open ▼]  District [All ▼]              [+ New Application]│
├──────────┬──────────────────┬─────────┬───────────────┬─────┬────┤
│ App No   │ Applicant        │ Type    │ Current Stage │ Day │SLA │
│ C-26-045 │ Rudolf Life Sci. │ Renewal │ 3. File Review│ 2/15│ 🟢 │
│ C-26-046 │ AG Pharma        │ New     │ 4. Deficiency │ 6/4 │ 🔴 │
│ D-26-310 │ Kisan Zarai…     │ Renewal │ 5. Fee        │ 3/10│ 🟢 │
└──────────────────────────────────────────────────────────────────┘
```

### S12 — Application Detail (main working screen)
```
┌──────────────────────────────────────────────────────────────────┐
│ APP C-26-045 │ Rudolf Life Sciences │ RENEWAL │ Via: Portal      │
│ Diary No [1123]  Received [01-09-26]  Total pages [240]          │
│ ①Submit✓ ②Review✓ ③File Review● ④Deficiency ⑤Fee ⑦Issue          │
│                                     (⑥ Higher approval: off)    │
│ Stage due: 03-09-26    [Complete Stage] [Skip (if allowed)]      │
├──────────────────────────────────────────────────────────────────┤
│ Tabs: Checklist │ Deficiency │ Fees │ Log                        │
├──────────────────────────────────────────────────────────────────┤
│ CHECKLIST (Company – Renewal v1)          Progress: 20/27 ✓      │
│ Annex│ Item                        │Pages│File│ Status           │
│  A   │ Application Form-C 14       │  3  │ 📎 │ ✓ Verified       │
│  I   │ Previous registration cert. │  1  │ 📎 │ ✖ Deficient      │
│  L   │ Technical staff (Form-6)    │  6  │ 📎 │ ○ Submitted      │
│  S   │ Purchase agreement          │  4  │ 📎 │ ✖ Expires before │
│      │                             │     │    │   license end    │
│ Row actions: [Verify] [Deficient + remarks] [N/A] [Upload]       │
└──────────────────────────────────────────────────────────────────┘
```

**Deficiency tab**
```
│ [Generate Deficiency Letter from deficient items] → PDF preview  │
│ Letter No │ Issued │ Reply due │ Status │ [PDF] [Mark Resolved]  │
```

**Fees tab**
```
│ FEE                                                              │
│ Renewal fee (from fee table, effective 01-07-26)   Rs ______     │
│                                                                  │
│ PENALTIES (entered by officer)                    [+ Add Penalty]│
│ ┌──────────────────────────────────────────────────────────────┐ │
│ │ Type [Late renewal ▼]                                        │ │
│ │ ℹ Submitted 12 days after expiry. Reference rate Rs 500/day. │ │
│ │ Basis [12 days × 500]   Standard amount [6,000]              │ │
│ │ Final amount [3,000]                                         │ │
│ │ ⚠ Reduced — reason required [________] Order No [____]       │ │
│ │ Waiver approval: ⏳ Pending Director  [Approve] (Director)    │ │
│ └──────────────────────────────────────────────────────────────┘ │
│ ℹ Staff records show 2 months below minimum technical staff     │
│   in the last license period (reference Rs 40,000/month).        │
│                                                                  │
│ TOTAL PAYABLE                                   Rs ______        │
│ CHALLANS                                          [+ Add Challan]│
│ Challan No │ Bank │ Date │ Amount │ 📎 │ Status │ [Verify]       │
│ Paid (verified): Rs ___   Balance: Rs ___                        │
```

**Footer (always visible)**
```
│ [Reject Application]                  [Issue License] (disabled) │
│ Cannot issue yet:                                                │
│  • Challan not verified                                          │
│  • Waiver approval pending                                       │
│ Documents: 2 required items deficient, 5 not uploaded            │
│  → Enforcement OFF: license can be issued, marked 📄 Incomplete   │
│    (Enforcement ON: these would also block issuance)             │
└──────────────────────────────────────────────────────────────────┘
```

**Document completion after issuance (enforcement OFF).** When a license was issued with documents Incomplete, the application page opens in "Document completion" mode:
```
┌──────────────────────────────────────────────────────────────────┐
│ APP C-26-045 │ ISSUED │ License DPP/C/2026/0045/R1               │
│ 📄 DOCUMENTS INCOMPLETE — 7 of 27 required items outstanding       │
│ Checklist remains open until license expiry (28-01-2028)         │
│ Annex│ Item                        │File│ Status                 │
│  I   │ Previous registration cert. │ —  │ ○ Not uploaded [Upload]│
│  L   │ Technical staff (Form-6)    │ 📎 │ ○ Submitted  [Verify]  │
│ When all required items are verified → license marked Complete  │
└──────────────────────────────────────────────────────────────────┘
```
A list of all such licenses is available from the dashboard card "Documents incomplete".

### S13 — Issue License (confirmation)
```
┌─────────────────────────────────────────────┐
│ ISSUE LICENSE — Rudolf Life Sciences         │
│ License No:  DPP/C/2026/0045/R1 (auto)       │
│ Valid from:  29-01-2027  (continues previous)│
│ Valid to:    28-01-2028  (12 months)         │
│ Products approved: 11                        │
│ Documents:   📄 Incomplete (7 items)          │
│ ⚠ License will be flagged "Documents         │
│   incomplete" until all are verified         │
│ [Preview Certificate]   [Cancel] [Issue]     │
└─────────────────────────────────────────────┘
```
When the license is issued, the system generates the certificate PDF with its QR code and marks the previous license as superseded. It also notifies the company users.

### S14 — Verification Queue
```
┌──────────────────────────────────────────────────────────────────┐
│ PENDING VERIFICATION    [Staff (6)] [Documents (14)] [Products(3)]│
├──────────────┬────────────────────────────┬────────────┬─────────┤
│ Company      │ Item                       │ Submitted  │         │
│ AG Pharma    │ New staff: Naveed Ahmed    │ 21-09-26   │[Review] │
├──────────────────────────────────────────────────────────────────┤
│ REVIEW PANEL                                                     │
│ Submitted data │ Attached files (viewer) │ Conflict checks ✓     │
│ [Approve]  [Reject — reason: ______________]                     │
└──────────────────────────────────────────────────────────────────┘
```

### S15 — Licenses List
All licenses, filterable by type, status, district and validity date range, with Export to Excel. Each row has actions to view, download the certificate, suspend, cancel or restore. Suspend, cancel and restore each require a reason, an order number and an effective date.

### S16 — Settings › General
```
┌──────────────────────────────────────────────────────────────────┐
│ SETTINGS › GENERAL                                               │
│ LICENSING                                                        │
│  Company license period (months)        [12]                     │
│  Dealer license period (months)         [12]                     │
│  Renewal window (days before expiry)    [60]                     │
│  Minimum technical staff per company    [2]                      │
│  Deficiency reply period (days)         [15]                     │
│  Enforce document requirements          [ OFF ]                  │
│   ℹ ON: a license cannot be issued while any required document  │
│     is missing or not verified.                                  │
│     OFF: a license can be issued; it is marked "Documents        │
│     incomplete" until documents are uploaded and verified.       │
│ ALERTS                                                           │
│  Red alert (days) [30]     Amber alert (days) [90]               │
│ NUMBERING                                                        │
│  Company license no. pattern [DPP/C/{YYYY}/{SERIAL:4}{RENEWAL}]  │
│  Dealer license no. pattern  [DPP/D/{DISTRICT}/{YYYY}/{SERIAL:4}…│
│  Preview: DPP/C/2026/0046/R1                                     │
│ UPLOADS                                                          │
│  Max file size (MB) [10]                                         │
│ ℹ Fees are managed by the system administrator (database only). │
│                                                     [Save]       │
└──────────────────────────────────────────────────────────────────┘
```

### S17 — Settings › Checklists
```
┌──────────────────────────────────────────────────────────────────┐
│ CHECKLIST TEMPLATES                                              │
│ Template              │ Published │ Draft │ Items │              │
│ Company – New         │ v1        │  —    │ 17    │ [Open]       │
│ Company – Renewal     │ v1        │ v2    │ 27    │ [Open]       │
│ Dealer – New          │ v1        │  —    │ 17    │ [Open]       │
│ Dealer – Renewal      │ v1        │  —    │ 27    │ [Open]       │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────┐
│ CHECKLIST: Company – Renewal    v2 (DRAFT)      v1 published     │
│ [+ Add Item] [Copy from another template] [Preview] [Publish v2] │
├───┬─────┬───────────────────────┬───┬──────┬──────┬──────┬──────┤
│ ≡ │Annex│ Title                 │Req│Upload│Attest│Cover │Portal│
│ ≡ │ A   │ Application Form-C 14 │ ✓ │  ✓   │ —    │  —   │  ✓   │
│ ≡ │ B   │ Cert. of Incorporation│ ✓ │  ✓   │ Gaz. │  —   │  ✓   │
│ ≡ │ S   │ Purchase agreement    │ ✓ │  ✓   │Notary│  ✓   │  ✓   │
│ Drag ≡ to reorder  •  [Edit] [Remove] on each row                │
│ ℹ Publishing affects NEW applications only.                      │
└──────────────────────────────────────────────────────────────────┘
```
The Edit Item dialog contains every field of `checklist_items`, as listed in Section 4.7.

### S18 — Settings › Workflow Stages
```
│ Seq │ Stage                 │ Applies │ SLA days │ Permission     │ Active │
│  1  │ Submission            │ Both    │   —      │ apps.create    │  ✓     │
│  6  │ Higher approval       │ Both    │   3      │ licenses.issue │  ☐     │
```
The stages are shown separately for companies and dealers, selected with tabs.

### S19 — Settings › Lookups and Products Master
Simple CRUD tables for districts (with codes), tehsils, provinces, qualifications, document types and generic products.

### S20 — Users & Roles
```
┌──────────────────────────────────────────────────────────────────┐
│ USERS  [+ New User]   Type [Staff|Company]  Role [▼]  Active [▼] │
│ Name │ Email │ Type │ Role(s) │ Company/District │ Last login │⋯ │
├──────────────────────────────────────────────────────────────────┤
│ ROLES › Registration Officer                     [Save]          │
│ Module        │ view │ create │ update │ delete │ other          │
│ Companies     │  ☑   │   ☑    │   ☑    │   ☐    │                │
│ Applications  │  ☑   │   ☑    │   —    │   —    │ ☑ process      │
│ Penalties     │  —   │   —    │   —    │   —    │ ☑ enter ☐ waive│
└──────────────────────────────────────────────────────────────────┘
```

### S21 — Activity Logs
```
┌──────────────────────────────────────────────────────────────────┐
│ ACTIVITY LOG  User [▼] Action [▼] Module [▼] Date [__ to __]     │
│ Time          │ User  │ Action  │ Record        │ IP      │      │
│ 25-09 10:14   │ Ahmed │ updated │ Company C001  │ 39.x.x  │ [ⓘ]  │
│ 25-09 10:12   │ Sana  │ penalty_waived │ APP C-26-045 │ … │ [ⓘ]  │
│ ⓘ → Field │ Old value │ New value │ Reason (if override)          │
│                                               [Export Excel]     │
└──────────────────────────────────────────────────────────────────┘
```

### S22 — Data Import
```
┌──────────────────────────────────────────────────────────────────┐
│ DATA IMPORT   [Upload Excel]  Type [Companies ▼]  [Dry Run] [Run]│
│ Batch │ File │ Rows │ Imported │ Exceptions │ Status             │
├──────────────────────────────────────────────────────────────────┤
│ EXCEPTIONS  Issue [All ▼]  Status [Open ▼]                       │
│ Row │ Issue              │ Details                 │ Action      │
│ 14  │ bad_date           │ Licenses Exp = "--"     │ [Fix][Skip] │
│ 52  │ possible_duplicate │ ≈ "A.M.B. Agro Division"│ [Merge][New]│
│ 88  │ missing_cnic       │ TS "Mansoor Ahmed"      │ [Add CNIC]  │
└──────────────────────────────────────────────────────────────────┘
```

---

## 11. Screen Sketches — Company Portal

The portal uses the same application with a restricted menu. Company users see only their own company.

```
Menu: Dashboard │ Company Info │ Staff │ Documents │ Products │ Applications │ Users │ 🔔
```

### P1 — Portal Dashboard
```
┌──────────────────────────────────────────────────────────────────┐
│ RUDOLF LIFE SCIENCES — COMPANY PORTAL                  [User ▼]  │
├──────────────────────────────────────────────────────────────────┤
│ License: DPP/C/2026/0045/R1    Valid until 28-01-2027 (125 days) │
│ Renewal opens on 29-11-2026            [Start Renewal] (disabled)│
├──────────────────────────────────────────────────────────────────┤
│ ACTION REQUIRED                                                  │
│ ⚠ Deficiency letter DL-26-12: 2 items due 30-09-26  [Respond]    │
│ ⚠ Purchase agreement expires before license end     [Upload new] │
│ ⚠ Only 1 verified technical staff (minimum 2)       [Add staff]  │
│ 📄 Documents incomplete for current license: 7 items [Upload]     │
│ ⏳ Waiting for Directorate: 1 staff, 3 documents                  │
└──────────────────────────────────────────────────────────────────┘
```

### P2 — Company Info (read-only)
```
┌──────────────────────────────────────────────────────────────────┐
│ COMPANY INFORMATION                                          🔒  │
│ Name, Legal type, NTN, Incorporation, Head office, CEO/Directors │
│ Premises and assets (read-only)                                  │
│ ℹ To change any of this information, please contact the        │
│   Directorate of Plant Protection: 081-9211868,                  │
│   dppb2018@gmail.com                                             │
└──────────────────────────────────────────────────────────────────┘
```

### P3 — Staff
This screen uses the same CNIC-first flow as S6, with the same blocking messages. One exception: the name of the other company is shown **only as "registered with another company"**, so that companies cannot see each other's data.
```
│ [+ Add Staff]                                                    │
│ Name │ CNIC │ Qualification │ Since │ Status (Pending/Verified/  │
│      │      │               │       │ Rejected: reason)          │
│ Row action: [End Employment]                                     │
```

### P4 — Documents
```
│ [+ Upload]  Title │ Type │ Expiry │ Version │ Status │ [Replace] │
```
Replacing a document creates a new version, which starts as Pending. The old version is kept.

### P5 — Products
```
│ [+ Add Product]  Brand │ Generic │ DPP Reg │ Status │            │
```
New products stay Pending until verified. To withdraw a product, the company contacts the Directorate.

### P6 — Renewal Wizard
```
Step 1  Confirm details       – read-only summary; staff count check
Step 2  Checklist uploads     – only items with portal_uploadable = ✓
                                each item: upload, pages, issue/expiry dates
Step 3  Fee information       – fee amount; note that penalties (if any)
                                will be determined by the Directorate
Step 4  Declaration & submit  – "I certify that…" (from Form-A certificate)
                                name, CNIC, total pages
→ Application created; status tracker shown (current stage, deficiency
  letters, required payment once fees are finalized)
```

### P7 — My Users (Company Admin only)
```
│ [+ Add User]  Name │ Email │ Role (Company Admin / Company Staff) │
│ Active │ [Deactivate] [Reset password]                           │
```

---

## 12. Public Verification Page

This page is reached by scanning the certificate QR code (`/verify/{token}`). No login is needed, and requests are rate-limited.

```
┌────────────────────────────────────────────┐
│ ✓ VERIFIED LICENSE                          │
│ Directorate of Plant Protection, Balochistan│
│                                             │
│ Name:        Rudolf Life Sciences (Pvt) Ltd │
│ Type:        Pesticide Company              │
│ License No:  DPP/C/2026/0045/R1             │
│ Valid:       29-01-2027 to 28-01-2028       │
│ Status:      🟢 ACTIVE                        │
│ (for dealers: District also shown)          │
└────────────────────────────────────────────┘
```
- If the license has been superseded, expired, suspended or cancelled, the page shows a red banner, e.g. "This certificate is no longer valid."
- An invalid token shows "Certificate not found."
- The page never shows CNICs, staff, contacts or documents.

---

## 13. Security, Backups and Audit

- **Transport:** HTTPS only, with HSTS. The cloud firewall allows only ports 80 and 443.
- **Passwords:**
  - At least 8 characters, including letters and numbers
  - Changed on first login
  - Reset links expire after 60 minutes
- **Login protection:** accounts lock for 15 minutes after 5 failed attempts. Failed attempts are logged.
- **Two-factor authentication:** optional (authenticator app), and recommended for Super Admin and Director.
- **Sessions:** web sessions time out after 30 minutes of inactivity. Mobile tokens can be revoked.
- **Authorization:** every endpoint checks the permission and the data scope (own company or own district).
- **Files:**
  - Stored permanently in a private bucket. Viewing or downloading creates a one-time link valid for 5 minutes (explained in Section 3).
  - File type is checked from the actual content, not only the extension
  - Size limit is taken from settings
- **Activity log:** the application's database account can only insert and read activity-log rows. Logs are kept indefinitely.
- **Backups:** automated daily database backups kept for 30 days, and versioning on the file storage. A restore test is run monthly.
- **Environments:** separate staging and production environments. Real data is never used on staging.

---

## 14. Data Migration Plan (Excel → DPPS)

### 14.1 Order of Import
1. Lookups: districts and tehsils (from the Dealers file), then provinces.
2. Persons: CEOs from the "Employ details" sheet (which has CNICs), technical staff, and dealer owners.
3. Companies, then company people, then premises (warehouses), then products.
4. Dealers, then dealer owners.
5. Legacy licenses, built from the expiry dates. They are marked `is_legacy = true`.
6. Status recalculation.

Imported companies and dealers have no uploaded documents at first. Their legacy licenses are marked documents status "not_applicable". Documents can be attached to the records at any time.

Every import is **run as a dry run first**. The exceptions report is reviewed and corrected, and only then is the real import run.

### 14.2 Column Mapping — Companies_List.xlsx › "Company Details"

| Excel column | DPPS field | Notes |
|---|---|---|
| Visited | legacy_notes | Inspections are Phase 2 |
| S.No | — | Ignored |
| Reg + Reg (suffix) | companies.legacy_reg_no | Combined, e.g. "905 /Renewal/PP/DGA" |
| Name | companies.name | Duplicate check applied |
| Address | head_office_address | |
| NTN # | ntn | Duplicates go to exceptions |
| PTCL / Phone # / Gmail | landline / mobile / email | "Nill" is treated as blank |
| Licenses Exp | legacy license valid_to | Parsed from "D-M-YYYY" text. "--" and blanks go to exceptions. |
| PCPA/Croplife | pcpa_member / croplife_member | |
| Field office/Ware house Bln | company_premises (warehouse) | |
| Dealers | legacy_notes | Decision D2: not tracked |
| chalan # / Amount | legacy_notes | |
| CSR | legacy_notes | Meaning to be confirmed |
| RD | legacy_notes | Meaning to be confirmed |

### 14.3 Column Mapping — "Employ details"

| Excel column | DPPS field | Notes |
|---|---|---|
| Name Of CEO + CNIC | persons + company_people (role = ceo) | CNIC validated |
| TS 1 / Contact, TS2 / Contact | persons + company_people (role = technical_staff) | **No CNIC in the sheet.** These are imported as `cnic_pending = true`, source = import, and are not counted as verified (Rule R-09). A value of "2" or "Nill" goes to exceptions. |

### 14.4 Column Mapping — "Name Of Products"

| Excel column | DPPS field | Notes |
|---|---|---|
| Name of products | company_products | Each line is split and parsed as generic name + concentration. Anything that cannot be parsed, such as brand names, is saved with status `needs_mapping` for an officer to map to the generic product master. |
| Samples# | sample_provided | "Provided" = true |

### 14.5 Column Mapping — Dealers_List.xlsx › "By District"

| Excel column | DPPS field | Notes |
|---|---|---|
| District | dealers.district_id | Name matched to the districts table; trailing spaces trimmed |
| Buisness Address | shop_name + business_address | The shop name is taken from the text before the location |
| Area/Tehsil | tehsil_id | Created if missing |
| Name of Onwer + Contact No | persons + dealer_owners | No CNIC, so `cnic_pending = true` |
| Registration No. | legacy_reg_no | ~220 repeated numbers go to exceptions for review |
| day / Month / Year | legacy license date | Meaning to be confirmed (see Open Items) |
| Renewal / Regisration / fee | legacy_notes | |

The yearly sheets (2021-22, 2022-23, 2023-24) are attached to the matching dealers as legacy challan history, if the officer confirms this is wanted.

---

## 15. Phase 2 Parking Lot

- Inspections and enforcement history (profile tab, dashboard card, premises inspection column)
- Sampling (drawn, passed and failed)
- Pest alerts and advisories
- SMS and email notifications
- Dealer portal
- Higher approval (Step 8), switched on from Workflow settings
- Mobile app, using the same API
- Reports (license register, revenue, district-wise statistics)
- Structured Form-7 (pay) and Form-8 (sales) data, if required
- Scrutiny record and interviews of technical staff, as workflow stages with their own screens
- Structured provincial registrations (Punjab, Sindh, KP), if needed later

---

## 16. Open Items

| # | Item | Owner | Needed by |
|---|---|---|---|
| O-1 | Certificate format (layout, wording, signature block, QR position) | Directorate | Before development of S13 / certificate PDF |
| O-2 | Final license number format (a placeholder is in use; configurable later) | Directorate | Any time |
| O-3 | Fee amounts and effective dates for `fee_structures` | Directorate | Before go-live |
| O-4 | Meaning of the Excel columns "CSR" and "RD" (Companies) and "day/Month/Year" (Dealers: issue date or expiry date?) | Directorate | Before migration |
| O-5 | Confirm that legacy staff and dealer owners without CNIC may be imported as "CNIC pending" and must provide a CNIC before their next license is issued (Rule R-09) | Directorate | Before migration |
| O-6 | Confirm which items to remove from the Dealer templates (e.g. Memorandum & Articles) | Directorate | After seeding, via checklist editor |
| O-7 | Domain name and cloud provider account | Directorate IT | Before deployment |

---
*End of document.*
