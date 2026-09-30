# Cursor Prompt — Digital Plant Protection System (Phase 1)

## How to use this file

1. Create an empty project folder and open it in Cursor.
2. Create a folder `docs/` and put **`DPPS_Phase1_Design_v2.md`** inside it.
3. Create the folder `.cursor/rules/` and put **`dpps-rules.mdc`** inside it. This file makes Cursor follow the rules in every chat, even new ones.
4. Open Cursor chat in **Agent** mode and choose a strong model.
5. Copy everything inside the box under **"MASTER PROMPT"** below and paste it into the chat.
6. After each step, test what Cursor asks you to test.
   - If everything works, reply: **`CONFIRMED`**
   - If something is wrong, describe the problem (screenshots help). Cursor fixes only that problem and asks you again.
7. If a chat becomes long or slow, start a new chat and paste the **"RESUME PROMPT"** at the end of this file.

---

## MASTER PROMPT

````text
You are building "Digital Plant Protection System" (DPPS) Phase 1 for the
Directorate of Plant Protection, Balochistan.

THE SPECIFICATION
- The complete and final design is in @docs/DPPS_Phase1_Design_v2.md
- Read the WHOLE document before doing anything.
- The document is the single source of truth. Do not add, remove or change
  any table, field, rule, screen, role or setting that is not in it.
- Anything listed in Section 15 (Phase 2 Parking Lot) must NOT be built.

TECHNOLOGY (fixed, do not change)
- Backend: Laravel 12, pure REST API under /api/v1, MySQL 8, Sanctum
  (cookie auth for the SPA, tokens for the future mobile app),
  spatie/laravel-permission, spatie/laravel-activitylog.
- Frontend: Vue 3 + Vite + Pinia + Vue Router + PrimeVue, a separate SPA.
- Folder layout: /backend (Laravel) and /frontend (Vue) in this one repository.
- Backend tests: Pest (or PHPUnit, if Pest cannot be installed).

HOW WE WORK — STRICT STEP-BY-STEP
1. We follow the BUILD PLAN below, one step at a time, in order.
2. Work ONLY on the current step. Never start, prepare or "partly do" the
   next step.
3. At the end of each step, run the checks you can run yourself (migrations,
   tests, build, lint) and fix any errors BEFORE reporting to me.
4. Then give me the STEP REPORT (format below) and STOP.
   Do not write any more code until I reply "CONFIRMED".
5. If I report a problem, fix ONLY that problem, inside the current step,
   then give me a short report with how to re-test, and STOP again.
6. Only when I reply "CONFIRMED" do you move to the next step.

STAY FOCUSED — DO NOT MOVE HERE AND THERE
- Do not touch files that are not needed for the current step. If you must
  change a file that belongs to an earlier, confirmed step, STOP and ask me
  first, explaining exactly what and why.
- Do not refactor, rename, reformat or "improve" existing working code
  unless I ask.
- Do not install any package that is not named above or in the design
  document without asking me first.
- Do not invent features, extra fields, extra screens or "nice to have"
  additions. If you think something is missing from the design, write it
  under "Questions / Suggestions" in the step report. Do not build it.
- If the design document is unclear or seems to contradict itself, STOP and
  ask me. Never guess silently.
- Never edit a migration from a confirmed step. Create a new migration.
- Never delete files, tables or data without asking me.
- Do not leave TODOs or placeholder code without listing them in the report.
- Keep business rules in the backend (services / policies / form requests),
  never only in the Vue screens. The mobile app will use the same API.

PROGRESS FILE
- Create and maintain docs/PROGRESS.md containing:
  * the build plan with the status of each step
    (Not started / In progress / Waiting for confirmation / Confirmed)
  * for each finished step: files created or changed, and important notes.
- Update it at the end of every step. This lets us continue in a new chat.

STEP REPORT FORMAT (use exactly this)
---------------------------------------------------------------
## Step N — <name> : READY FOR YOUR CHECK
### What I built
(short list)
### Files created / changed
(list of paths)
### Commands you need to run
(exact commands, in order, e.g. composer install, php artisan migrate --seed,
 npm install, npm run dev)
### How to test (click by click)
1. ...
2. ...
### Expected result
(what you should see if everything is working)
### Automated checks I ran
(tests / migrations / build and their results)
### Not included in this step (comes later)
### Questions / Suggestions (nothing here has been built)
Reply CONFIRMED to continue to Step N+1, or tell me what is wrong.
---------------------------------------------------------------

BUILD PLAN
Step 0  — Read and understand. NO CODE.
          Summarise the system in 10–15 lines. List every question or
          ambiguity you find in the design document. Ask me for my OS,
          PHP, Composer, Node and MySQL versions and how I run MySQL
          locally. Then STOP.
Step 1  — Project setup. /backend Laravel 12 + /frontend Vue 3 skeletons,
          .env examples, MySQL connection, Sanctum SPA auth config, CORS,
          API versioning /api/v1, standard JSON response format (Section 3),
          GET /api/v1/health endpoint, and a frontend page that calls it
          and shows "API connected". Create docs/PROGRESS.md.
Step 2  — Migrations part 1: users and access, settings, fee_structures,
          lookups (Sections 4.1–4.3).
Step 3  — Migrations part 2: persons, person_qualifications, companies and
          all company tables, products, dealers, dealer_owners
          (Sections 4.4–4.6), including the active_tech_person generated
          column and its unique index.
Step 4  — Migrations part 3: workflow and checklists, applications,
          penalties, deficiency letters, challans, licenses, documents,
          notifications, activity_logs, import tables (Sections 4.7–4.11),
          including the open_flag unique index. Write the SQL for the
          INSERT/SELECT-only grant on activity_logs into
          docs/DB_GRANTS.sql (do not run it).
Step 5  — Seeders: settings, workflow stages, 4 checklist templates with
          all items, roles and permissions with the default matrix, lookups
          (provinces, qualifications, document types; districts come later
          from the import), and one Super Admin user
          (Sections 5 and 8). Add model factories.
Step 6  — Authentication API: login, logout, me (with permissions),
          forgot/reset/change password, must-change-password on first
          login, lockout after 5 failed attempts, login_attempts,
          two-factor (optional per user). Activity logging foundation
          (the logging service, batch_uuid, IP address, channel).
          Feature tests.
Step 7  — Frontend shell: login screen (S1), change password, main layout,
          sidebar showing only permitted menu items (Section 10
          navigation), a route guard, and an empty dashboard page.
Step 8  — Users & Roles: API and screen S20 (users list, create/edit user,
          assign role, district scope, company link, role/permission
          matrix). Feature tests for scoping.
Step 9  — Settings › General (S16, including "Enforce document
          requirements"), Lookups and Products Master (S19). Fees must have
          NO screen and NO write API.
Step 10 — Checklist templates editor (S17: versions, draft/publish, items,
          drag to reorder, copy from another template) and Workflow Stages
          (S18). Rules R-22 and R-23. Tests.
Step 11 — Persons: API, CNIC lookup endpoint returning roles and blocking
          conflicts, person profile screen S8. Rules R-01 to R-09.
          Tests for every rule.
Step 12 — Companies: API, list S3, create/edit S4 with duplicate checks
          (R-11, R-12) and the warning/confirm-with-reason mechanism
          (HTTP 409 + confirm_warnings). Excel export of the list. Tests.
Step 13 — Company profile S5 with tabs: Overview, People, Tech Staff
          (CNIC-first add S6, end employment S7), Premises + Assets
          (optional), Products (R-14), Activity tab. Portal Users,
          Licenses, Applications and Documents tabs are shown empty for
          now. Tests.
Step 14 — Documents: private storage, upload with real file-type check and
          size limit, 5-minute temporary download links, versions and
          replace, SHA-256 duplicate warning (R-15), verification status.
          Connect the Documents tab of the company profile. Tests.
Step 15 — Dealers: API, list S9, profile S10, owners with the CNIC-first
          flow (R-03, R-04, R-13), documents tab. Excel export. Tests.
Step 16 — Applications part 1: create an application (new or renewal),
          application numbering, one open application rule (R-10),
          renewal window (R-18), stage engine (R-26, R-27, skips inactive
          stages), checklist items tab with verify / deficient / N/A /
          upload, deficiency letters with PDF. Screens S11 and S12
          (Checklist and Deficiency tabs). Tests.
Step 17 — Applications part 2: fees from fee_structures, manual penalties
          with helper info and waiver approval (R-16, R-17), challans and
          challan verification (R-21), total payable. Fees tab of S12. Tests.
Step 18 — License issuance: issuance checks R-19 and R-20 respecting the
          "Enforce document requirements" setting, validity dates (R-24),
          superseding (R-25), license number patterns from settings,
          documents status (R-20a, R-20b) and the document-completion mode,
          certificate PDF with a SIMPLE PLACEHOLDER LAYOUT and QR code,
          public verification page (Section 12), Licenses list S15 with
          suspend / cancel / restore. Connect the Licenses tabs. Tests.
Step 19 — Nightly status job (Section 7), dashboard S2 with all cards and
          counts, expiry alerts, the "Documents incomplete" card, quick
          search, and in-app notifications (bell icon). Tests for the
          status calculation.
Step 20 — Verification queue S14 (staff, documents, products from the
          portal), approve / reject with reason (R-30). Tests.
Step 21 — Company portal P1–P7: portal dashboard, read-only company info
          with contact message (R-29), staff, documents, products, renewal
          wizard, company users. Strict own-company scoping (R-28). Tests
          proving a company user can never read another company's data.
Step 22 — Activity log viewer S21 (filters, old/new values, Excel export)
          and a check that every action listed in R-32 is logged.
Step 23 — Data import S22: dry run, run, exceptions review
          (fix / merge / skip), following Section 14 mappings exactly for
          Companies_List.xlsx and Dealers_List.xlsx. Legacy licenses and
          CNIC-pending persons. Ask me for the Excel files at this step.
Step 24 — Final hardening and review: security items in Section 13,
          rate limiting, session timeout, full test run, a README with
          setup and deployment steps, and a checklist of every business
          rule R-01 to R-32 showing where it is implemented and tested.

Start now with Step 0 only.
````

---

## RESUME PROMPT (for a new chat)

````text
We are building DPPS Phase 1. The design is @docs/DPPS_Phase1_Design_v2.md
and the progress is @docs/PROGRESS.md. The rules are in
@.cursor/rules/dpps-rules.mdc.

Read all three files. Then tell me, in 5 lines or fewer:
- which step is current and its status,
- what remains in that step.
Then STOP and wait for my instruction. Do not write code until I reply
"CONTINUE". Keep following the step-by-step process with the STEP REPORT
and wait for "CONFIRMED" after every step.
````

---

## Useful short replies during the build

| Situation | What to type |
|---|---|
| Step works | `CONFIRMED` |
| Something is broken | `Problem in Step N: <what you did> → <what happened> → <what you expected>. Fix only this.` |
| Cursor is going off track | `STOP. You are outside the current step. Undo changes not related to Step N and show me the list of files you changed.` |
| Cursor wants to add something extra | `Not in the design. Do not build it. Add it to Questions / Suggestions only.` |
| You want to change the design | Update the design .md file first, then tell Cursor: `The design document was updated in section X. Re-read it and tell me what this changes in the current step before coding.` |
