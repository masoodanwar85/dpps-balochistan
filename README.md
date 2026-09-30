# Digital Plant Protection System

Phase 1 office system for the Directorate of Plant Protection, Quetta. The API is Laravel. The screens are a Vue application.

## Requirements

- PHP 8.3 or newer, with the `intl` extension. Production is PHP 8.3. This machine may use PHP 8.5 for development.
- Composer
- MySQL 8 or newer
- Node.js 20 or newer

## Setup

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
```

Set `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `backend/.env`. Create that database, then:

```bash
php artisan migrate --seed
cd ../frontend
cp .env.example .env
npm install
```

`frontend/.env` should contain `VITE_API_URL=http://localhost:8000` for local use.

The seeder creates the Super Admin and the reference data. The password is recorded in `docs/CLARIFICATIONS.md` and is not repeated here.

## Run it locally

```bash
cd backend
php artisan serve
```

```bash
cd frontend
npm run dev
```

Open `http://localhost:5173`.

The nightly status refresh is scheduled at 00:30:

```bash
php artisan schedule:work
```

On a server, run `php artisan schedule:run` every minute from cron instead.

## Deployment

- Serve the site on HTTPS only. The web server must tell PHP that the request is secure. The API then sends `Strict-Transport-Security`. Plain HTTP, including local development, does not send that header.
- Open only ports 80 and 443 on the cloud firewall.
- Keep `APP_ENV=production` and `APP_DEBUG=false`. Set `APP_URL` and `FRONTEND_URL` to the public HTTPS addresses. Set `SESSION_DOMAIN` and `SANCTUM_STATEFUL_DOMAINS` to those hosts.
- Web sessions expire after 30 minutes without a request (`SESSION_LIFETIME=30`). A signed-in screen that receives an expired session returns to the login page. Mobile tokens are revoked by logging out.
- Login is limited to 30 attempts per minute per address, and an account locks for 15 minutes after 5 wrong passwords. Signed-in API calls are limited to 60 per minute per user. The public license check is limited to 30 per minute per address.
- Passwords are at least 8 characters and include letters and numbers. A new password must be changed at the first login. There is no emailed reset link in Phase 1. A Super Admin, or a Company Admin for their own company, sets a temporary password.
- Store uploaded files on the private disk. Downloads use a one-time link that lasts 5 minutes. The file type is taken from the contents, and the size limit comes from settings.
- Run `docs/DB_GRANTS.sql` for the production database account so that account can only insert and read `activity_logs`. Do not run that file with the development root account. Logs are kept.
- Take a database backup every day and keep 30 days. Turn on versioning for the file storage. Restore a backup once a month and confirm the application opens.
- Keep staging and production separate. Do not copy production data into staging.
- Build the screens with `npm run build` in `frontend` and serve the `frontend/dist` files from the web server, with `/api` proxied to PHP.

## Business rules

| Rule | Behaviour | Code | Test |
|---|---|---|---|
| R-01 | One active technical staff post per person. The message names the other company and start date. | `PersonRules` | `PersonsTest`, `CompanyProfileTest` |
| R-02 | End the previous post before starting another. | `PersonRules` | `PersonsTest` |
| R-03 | Active technical staff and an active dealer owner cannot be the same person. | `PersonRules` | `PersonsTest`, `DealersTest`, `CompanyProfileTest` |
| R-04 | One person may own more than one shop. | `PersonRules` | `PersonsTest`, `DealersTest` |
| R-05 | CNIC is 13 digits and unique. | `PersonRules` | `PersonsTest` |
| R-06 | Adding staff starts from the CNIC and reuses the person. | `PersonRules` | `PersonsTest`, `CompanyProfileTest` |
| R-07 | A mobile already used by someone else is a warning. | `PersonRules` | `PersonsTest`, `CompanyProfileTest` |
| R-08 | The end date cannot precede the start date, and the start date cannot be more than 30 days ahead. | `PersonRules` | `PersonsTest`, `CompanyProfileTest` |
| R-09 | A CNIC-pending person is not verified staff and blocks issuance. | `LicenseIssuer`, `PersonRules` | `PersonsTest`, `CompanyProfileTest` |
| R-10 | One open application per company or dealer. | applications table unique index, `ApplicationWriter` | `ApplicationsTest`, `MigrationsPart3Test` |
| R-11 | NTN is unique. An empty NTN may repeat. | `CompanyWriter` | `CompaniesTest` |
| R-12 | A similar company name is a warning. | `CompanyName` | `CompaniesTest` |
| R-13 | The same shop name or address in one district is a warning. | `DealerName` | `DealersTest` |
| R-14 | Brand names are unique inside one company. | `CompanyProducts` | `CompanyProfileTest` |
| R-15 | The same file on another record is a warning for officers only. | `DocumentStore` | `DocumentsTest` |
| R-16 | The officer types the penalty. The screen shows the reference amount only. | `ApplicationPenalties` | `ApplicationsFeesTest` |
| R-17 | A penalty below the standard amount needs a waiver and approval. | `ApplicationPenalties` | `ApplicationsFeesTest` |
| R-18 | A renewal cannot start before the renewal window. | `ApplicationWriter` | `ApplicationsTest`, `PortalTest` |
| R-19 | Issuance checks challans, staff, CNIC, and documents when enforcement is on. | `LicenseIssuer` | `LicensesTest` |
| R-20 | A covering document must last until the new license ends. This blocks issuance only when enforcement is on. | `LicenseIssuer` | `LicensesTest` |
| R-20a | Issuance with enforcement off and missing documents marks the license incomplete. | `LicenseIssuer` | `LicensesTest` |
| R-20b | Checklist items stay open until the license expires. The last verified item marks documents complete. | `ApplicationChecklist` | `LicensesTest` |
| R-20c | Assets and qualifications are optional. | company profile services | `CompanyProfileTest` |
| R-21 | A challan number is unique. | `ApplicationChallans` | `ApplicationsFeesTest` |
| R-22 | The checklist version is locked when the application is created. | `ApplicationWriter` | `ApplicationsTest`, `ChecklistsWorkflowTest` |
| R-23 | A published checklist cannot be edited. | `ChecklistEditor` | `ChecklistsWorkflowTest` |
| R-24 | Validity dates follow the on-time renewal rule, or the issue date when the renewal is late or the license is new. | `LicenseIssuer` | `LicensesTest` |
| R-25 | The previous license becomes superseded. | `LicenseIssuer` | `LicensesTest` |
| R-26 | Stages run in order. An inactive stage is skipped. A skippable stage needs a remark. | `ApplicationStages` | `ApplicationsTest` |
| R-27 | A stage past its due date is marked breached. | `ApplicationStages` | `ApplicationsTest` |
| R-28 | A company user can only reach their own company. | portal and company queries | `PortalTest`, `CompaniesTest` |
| R-29 | Company identity on the portal is read-only, with the contact message. | `PortalHome` | `PortalTest` |
| R-30 | Portal staff, documents, and products are saved as pending until an officer verifies them. | portal writers | `PortalTest`, `VerificationsTest` |
| R-31 | Deletes are soft deletes and require a reason, which is logged. | company and dealer writers | `CompaniesTest`, `DealersTest` |
| R-32 | Create, update, delete, login, download, export, approval, and warning override are logged. | `ActivityLogger` | `ActivityLogsTest` |

Password reset links that expire after 60 minutes are not part of Phase 1. Clarification C4 replaces them with a temporary password set by an administrator.
