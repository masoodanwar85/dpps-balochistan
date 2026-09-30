# DPPS Phase 1 — Clarifications

This file is part of the specification. Where it conflicts with `docs/DPPS_Phase1_Design_v2.md`, this file wins.

=====================================================================
ENVIRONMENT
=====================================================================

E1. OS: MacOS on development, Ubuntu on Production.
    Development PHP: 8.5. Production PHP: 8.3.
    The app must keep running on PHP 8.3. Composer resolves packages for PHP 8.3
    so a later update on the development machine does not require PHP 8.4+.
    Composer 2.10 is acceptable.
    Node: 25 LTS.
    Development MySQL: 9.6.0 (accepted).
    MySQL is installed on the local PC.

E2. Database name: `dpp_management_system` (already created). Database user: `root`.
    Password: `M@$00d6276`.

=====================================================================
CONTRADICTIONS
=====================================================================

C1. The New checklists have 18 items (A–H, L, Q–X, AA).
    Renewal has 27. Seed 18 for New. "17" in the design is a mistake.

C2. Confirmed: 7 stages only. The "9-step" wording in Section 2 is outdated.

C3. Include the inactive `higher_approval` seed row AND the on/off switch in
    S18. The switch is the generic Active toggle every stage has. It stays
    off in Phase 1; no extra approval screens are built.

C4. No email in Phase 1. Remove "Forgot password" from S1. Instead:
    - Super Admin resets any user's password from the Users screen (S20).
    - Company Admin resets their own company users' passwords (P7).
    A reset sets a temporary password and `must_change_password = true`.
    Keep the `password_reset_tokens` table unused, ready for Phase 2.

=====================================================================
DATABASE
=====================================================================

D1. Column rule:
    - ALL tables get `created_at` and `updated_at`, except `activity_logs`
      (`created_at` only, as designed).
    - `created_by`, `updated_by` and `deleted_at` ONLY on tables whose column
      list shows them.
    - Otherwise follow each table's own column list exactly.

D2. Agreed: add the `users.company_id` foreign key in Step 3, in a new
    migration.

=====================================================================
SEEDING (Step 5)
=====================================================================

S1. `fee_structures` stays EMPTY in the main `DatabaseSeeder`.
    Also create a separate `DevFeeSeeder` (run only on local/staging, never
    called from `DatabaseSeeder` in production) with test values:
    - company registration 50000
    - company renewal 30000
    - dealer registration 5000
    - dealer renewal 2500
    - late_renewal_per_day 500
    - no_technical_staff_per_month 40000
    - restoration_per_month 50000 (both entity types where relevant)
    - effective_from 2020-01-01

S2. Checklist item defaults for version 1 (all four templates):
    - `requires_upload` = true for all items EXCEPT V (samples and dummies
      are physical items) = false
    - `allowed_file_types` = pdf,jpg,jpeg,png
    - `max_files` = 5
    - `portal_uploadable` = true for all items
    - `requires_validity_dates` = true ONLY for item S; false for all others

S3. `required_permission` per stage (company and dealer):
    - submission → applications.create
    - progress_review → applications.process
    - file_review → applications.process
    - deficiency → applications.process
    - fee → challans.verify
    - higher_approval → licenses.issue
    - issuance → licenses.issue

S4. Qualifications (name | is_agriculture_degree):
    - Matric | no
    - Intermediate (FSc) | no
    - Diploma in Agriculture | yes
    - B.Sc Agriculture | yes
    - B.Sc (Hons) Agriculture | yes
    - M.Sc Agriculture | yes
    - M.Sc (Hons) Agriculture | yes
    - M.Phil Agriculture | yes
    - PhD Agriculture | yes
    - B.Sc (Other) | no
    - M.Sc (Other) | no
    - Other | no

    Document types (name | category | applies_to | has_expiry):
    - CNIC copy | cnic | person | yes
    - Degree certificate | qualification | person | no
    - Appointment letter | application | person | no
    - Certificate of Incorporation | other | company | no
    - Memorandum & Articles of Association | other | company | no
    - List of Directors | other | company | no
    - NTN / Income tax certificate | other | company | no
    - Bank certificate / statement | other | any | no
    - PCPA / CropLife membership | other | company | yes
    - Provincial registration certificate | license | company | yes
    - Previous license certificate | license | any | yes
    - Audit report | other | company | no
    - Agreement / Purchase agreement | agreement | any | yes
    - Partnership deed | agreement | any | no
    - Import certificate | other | company | yes
    - DPP product registration | license | company | yes
    - Product label / leaflet | other | company | no
    - Affidavit / Undertaking | agreement | any | no
    - Treasury challan | challan | any | no
    - Application form | application | any | no
    - Deficiency letter | correspondence | any | no
    - Correspondence | correspondence | any | no
    - Other | other | any | no

S5. Super Admin: name "Masood Anwar", email "masoodanwar85@gmail.com".
    Password: `m@$00d6276`.
    `must_change_password` = true.

S6. Districts and tehsils are needed before Step 15 (dealers), so seed them
    in Step 5, not Step 23. At Step 5, `Dealers_List.xlsx` will be placed in
    `docs/data/`. Read sheet "By District": take the unique District values
    (trimmed) and the unique Area/Tehsil values per district. Generate a
    unique 3-letter uppercase code per district (e.g. Quetta = QTA); codes
    stay editable in Lookups. Show the district list with codes in the
    Step 5 report for checking.

=====================================================================
BUSINESS RULES (use when you reach the relevant step)
=====================================================================

B1. Restoration: Phase 1 screens create only "new" and "renewal"
    applications. Keep "restoration" in the enums for later. A cancelled
    license is restored through the Restore action on S15 (reason,
    order no, effective date). A late renewal is still a "renewal".

B2. Yes: `progress_review` is skipped automatically for new applications,
    because it applies to renewals only.

B3. Stage → application status:
    - created but not yet submitted (portal wizard) → draft
    - submission → submitted
    - progress_review, file_review → under_review
    - deficiency, while a letter is open → deficiency_issued
    - fee → fee_pending
    - issuance, when every R-19 check passes → ready_to_issue
    - after Issue → issued
    - Reject → rejected
    - Withdraw → withdrawn

B4. No "returned" action in Phase 1. Add a "Withdraw" action for officers
    with `applications.process` (reason required, logged). It closes the
    application, so a new one can be opened (R-10).

B5. R-12 similarity (no extra package; PHP built-ins only). Warn when ANY
    of these hold:
    - normalized names are equal
    - `similar_text()` percentage ≥ 85
    - one normalized name contains the other, and the shorter is at
      least 6 characters long

B6. R-13: compare normalized shop names within the same district, and
    normalized addresses within the same district.

B7. Confirmed: the minimum technical staff check applies to companies
    only. Dealers skip it.

B8. R-09 "linked" means CURRENT people only (no end date): current
    technical staff, CEO and directors, and current dealer owners.

B9. Missing fee row: submission is allowed. The fee is looked up for the
    submission date when the application reaches the fee stage. If no row
    exists, the fee stage cannot be completed, and the message reads:
    "Fee is not configured for this date. Contact the system
    administrator."

B10. Issuance does NOT approve products. Products are approved only
     through the verification queue. At issuance, currently approved
     products that have `approved_in_license_id` = NULL get the new
     license's id. S13 shows the count of approved products.

B11. Suspend, cancel and restore update the company or dealer status
     immediately (not only in the nightly job).
     - Restoring sets the license back to active if it is still within
       its validity dates, otherwise to expired.
     - Cancelled licenses CAN be restored, only with `licenses.restore`
       (Super Admin and Director), and an order no is required.

B12. District Officer:
     - Dealer data and dealer counts are limited to their assigned
       districts.
     - Company data is read-only and NOT district-limited.
     - The dashboard's company cards show all companies.
     - One officer can have several districts (`user_districts`).

B13. Persons menu:
     - `persons.view`: Super Admin, Director, Registration Officer,
       Data Entry, Auditor
     - `persons.update`: Super Admin, Director, Registration Officer,
       Data Entry

B14. Application permissions:
     - Data Entry: `applications.view`, `applications.create`
     - Registration Officer: view, create, process, reject
     - Director and Super Admin: all

B15. Show these as OPTIONAL fields on the forms:
     - person: gender, date of birth, alt mobile, address, photo
     - company: website

B16. End-employment reasons: Resigned, Terminated, Transferred, Retired,
     Deceased, Contract ended, Other. Free text is required when "Other"
     is chosen.

B17. Public verification rate limit: 30 requests per minute per IP.

B18. Approved libraries (install them only in the step that needs them):
     - `barryvdh/laravel-dompdf` for PDFs
     - `endroid/qr-code` for QR codes
     - `maatwebsite/excel` for Excel import and export
     - frontend: shadcn-vue, with Tailwind CSS and the packages that shadcn-vue
       requires. PrimeVue is not used.
     - Checklist row reorder is still Step 10. Do not add a drag library
       unless asked.
     Ask before installing anything else.

B19. Confirmed: local development and production use a private local disk.

B20. Section 16 items stay open. Do not invent answers. Use the
     placeholders already defined in the design.

B21. Step 23 import decisions:
     - Ignore the dealer columns day, Month and Year. Do not build a dealer
       license from them.
     - Ignore the yearly dealer sheets (2021-22, 2022-23, 2023-24).
     - Company legacy licenses still use Licenses Exp as valid_to.
     - Where the company sheet has no value for a required field, the import
       stores a temporary one and says so in legacy notes: address or city
       "Not recorded", province Balochistan, and mobile 00000000000. A city
       or province read from the address is kept. Bad dates, duplicate names
       or NTNs, a CEO CNIC that is missing or invalid, and a staff value of
       "2" or "Nill" stay exceptions.
