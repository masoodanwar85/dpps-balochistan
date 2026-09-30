# Knowledge base

Short notes on how the office and the company portal work.

## How to sign in

Open the system and enter the email and password. Five wrong passwords lock the account for 15 minutes. The first login goes straight to Change Password. A session with no activity for 30 minutes returns to the login page.

## How to change a password

A new user sets their own password at the first login. After that, a Super Admin sets a temporary password. A Company Admin can do the same for a user in their own company. The password needs at least 8 characters and must include letters and numbers.

## How to add a company

From Companies, choose New Company and fill in the name, legal type, NTN, address, and contact details. A name close to one already on file asks you to continue and give a reason. An NTN that is already used is refused. Saving creates the company as unlicensed, with a code such as C-0001.

## How to add a dealer

From Dealers, choose New Dealer. The shop code comes from the district, for example D-QTA-0001, and it stays the same if the district is edited later. The same shop name or address in that district asks you to continue and give a reason. A new dealer is unlicensed. A District Officer can add and edit dealers only in their assigned districts.

## How to add technical staff

On the company profile, open Tech Staff and choose Add Staff. Enter the CNIC first. If that person already exists, their details are reused. The save is refused while they are still technical staff at another company, or while they own a dealer shop. A mobile number already used by someone else asks for a reason. The start date can be at most 30 days ahead. A company needs at least two verified technical staff before a license can be issued. Staff added from the portal stay pending until an officer verifies them.

## How to end employment

On the staff row, choose End Employment, enter the end date and a reason, and confirm. The end date must be on or after the start date. If this would leave the company short of the minimum verified staff, the screen warns you. The person can join another company only after this end date is saved. The previous company or an officer can enter it.

## How to add a dealer owner

On the dealer profile, add an owner with the same CNIC-first check. An active technical staff member cannot be an owner, and an active owner cannot be added as technical staff. One person may own more than one shop. The profile lists the person's other shops.

## How to upload a document

On the company, dealer, person, or application, choose Upload, set the title and type, and attach the file. The file type is read from the contents, and the size limit comes from Settings. Replacing a file keeps the older version and starts the new one as pending. An officer verifies it, marks it deficient with remarks, or marks it not applicable. The same file already attached to another record warns officers.

## How to add a product

On the company Products tab, add the brand and map it to a generic product. Brand names must be unique inside that company. A product from the portal, or from the Excel import, waits until an officer approves it. Approval does not attach the product to a license. That happens when the license is issued. To withdraw a product, the company contacts the Directorate.

## How to file a new application

From Applications, or from the company or dealer profile, choose New Application and pick New. Only one application can be open for that company or dealer. An office filing goes straight into the submission stage. The checklist version in force that day is locked onto the application, so a later checklist edit leaves it unchanged. A new application skips the progress-review stage.

## How to file a renewal

A renewal uses the current license, the latest one that has not been superseded. It can be filed once the renewal window opens. That window is the number of days before expiry set in Settings, 60 by default. A Company Admin starts it from the portal: confirm the details, upload the checklist items the portal allows, see the fee, then declare and submit. An officer can file the same renewal from the office. Days after expiry are stored as late days. A company with no current license cannot start a renewal.

## How to work an application

Open the application from the work queue. The stages run in order: submission, progress review for a renewal, file review, deficiency, fee, then issuance. Higher approval stays off until it is turned on under Settings, Workflow. Complete Stage moves to the next active stage. A skippable stage, such as deficiency, can be skipped with a remark. A stage past its due date shows as breached on the dashboard. On the checklist, verify an item, mark it deficient with remarks, mark it not applicable, or upload the file. Generate a deficiency letter from the deficient items. The application can leave the deficiency stage when nothing is still deficient and no letter is open.

## How to record a fee, penalty, and challan

On the Fees tab, the registration or renewal fee is taken from the fee table for the date the application was submitted. If no fee exists for that date, the fee stage stays where it is. The officer types any penalty. The screen shows the reference only: days late, or months in the last license period with fewer verified technical staff than the minimum. A penalty below that reference needs a reason, an order number, and approval from a user who can waive penalties. Add each challan with its number, bank, date, and amount, then verify it. A challan number can be used only once.

## How to issue a license

A license granted from now on is different from an imported one. The company files a renewal, or a new application, an officer moves it to issuance, and Issue License creates the new license. The earlier one is then marked superseded. A renewal filed on time starts the day after the previous license ends. A late renewal, or a new license, starts on the issue date. The end date is the start date plus the license period, minus one day. The officer does not type the dates or the license number. Issue License stays disabled until the challans are verified and cover the amount due, the company has enough verified technical staff, and nobody linked to the file is still waiting for a CNIC. With document enforcement off, the license can still be issued and is marked documents incomplete. With enforcement on, every required checklist item must be verified or marked not applicable, and a document that must cover the license period must last until the new end date. Issuing creates the certificate and notifies the company users. Approved products that have no license yet are stamped with this one. A Super Admin or a Director can issue.

## How to finish documents after a license is issued

When enforcement is off, the checklist stays open until the license expires. Upload and verify the remaining items on that application. When the last required item is verified or marked not applicable, the license is marked documents complete. The dashboard card Documents incomplete lists these licenses.

## How to suspend, cancel, or restore a license

From the Licenses list, or the company or dealer profile, suspend, cancel, or restore. Each action needs a reason, an order number, and an effective date, and it takes effect immediately. A company or dealer that is suspended or cancelled stays that way when a later license is issued. Restore sets the license and the party to active when the end date is today or later, and to expired when that date has passed. A Super Admin or a Director can suspend, cancel, and restore.

## How to check a certificate

Download the certificate PDF from the license row. The link lasts five minutes, and the download is logged. The QR code opens a public page for that certificate, with no login. A superseded, expired, suspended, or cancelled license shows that the certificate is no longer valid. An unknown code shows "Certificate not found." The public page leaves out CNICs, staff, contacts, and documents.

## How to record a license that was already granted

A Super Admin or a Director can record one license at a time. From Licenses, choose Record previous license. The same button is on the Licenses tab of a company or a dealer. Choose the company or dealer, enter the license number that was already granted, choose Registration or Renewal, and choose the end date. The latest end date that can be chosen is 31 December of the current year. Any earlier date can be chosen. The start date is filled in as that end date minus the license period in Settings, plus one day. Saving stores a legacy license with no application and no certificate. The company or dealer status follows that license, unless the party is already suspended or cancelled, or a later license is already on file.

To bring in many companies from the old workbook, sign in as a Super Admin and open Data Import. Upload the company workbook, choose Companies, and run a Dry Run first. On Company Details, Reg and its suffix become the license number, and Licenses Exp becomes the end date, written as day-month-year, such as 29-1-2027. Resolve every exception, then Run. The system stores a legacy license with no application and no certificate. The start date is the expiry minus the license period, plus one day. If the company is already on file, choose Merge for that row. Skipping a bad expiry leaves the company unlicensed. Dealer dates in the old sheets are left out. A dealer license from those sheets is recorded with Record previous license, or when an application is issued.

## How to verify a portal submission

Staff, documents, and products sent by a company arrive as pending. Open Verification Queue and review them. Approve uses the same check as verifying the item on the company or dealer profile. Reject needs a reason. Only verified staff and documents count toward a license. A District Officer verifies dealer items in their own districts. The company is notified when a submission is verified or rejected.

## How a company uses the portal

A company user sees only their own company: the dashboard, company information, staff, documents, products, and applications. A Company Admin also manages users and can submit a renewal once the window is open. Name, NTN, legal type, incorporation, head office, and directors stay locked, and the screen asks them to contact the Directorate to change those. Staff, documents, and products they add stay pending until an officer verifies them. They are notified when a license is issued, when a deficiency letter is issued, and once when the renewal window opens.

## How to add a user

From Users & Roles, add a staff user, assign a role, and assign districts for a District Officer. A Company Admin adds users for their own company as Company Admin or Company Staff. Deactivate a user to take away access. The role decides which menus appear. A District Officer's dealer work stays inside the assigned districts.

## How to read the dashboard

The dashboard counts companies, dealers, and licenses, applications in process, items waiting for verification, and licenses with incomplete documents. Each count opens the matching list. Expiry alerts split into expired, under 30 days, under 90 days, and renewal window open with no application yet. A District Officer sees the figures for their own district. Every night at 00:30, a license past its end date becomes expired, and each company and dealer is set to suspended or cancelled where that was chosen by hand, otherwise unlicensed, expired, expiring, or active.

## How to search

The dashboard quick search looks up a company, dealer, license, CNIC, mobile number, or district.

## How to export a list

Companies, dealers, licenses, and the activity log can be exported to Excel. The export itself is written to the activity log. A District Officer exports their own district.

## How to read the activity log

Activity Logs lists creates, updates, deletes, logins, downloads, exports, approvals, and cases where a warning was confirmed. Filter by user, action, module, and date, then open a row for the field, the old value, the new value, and the reason. A company, dealer, or application also has an Activity tab for that record.

## How to change settings

Settings, General holds the license period, the renewal window, the minimum number of technical staff, the deficiency reply period, document enforcement, the alert days, the license number patterns, and the upload size. A change applies to later actions. Fee amounts have no screen. They are entered in the database before a fee stage can be completed.

## How to change a checklist or a workflow stage

Under Settings, Checklists, edit a draft and publish it. A published version stays as it is, and a new version is used for later changes. Publishing affects applications filed after that. An application already open keeps the checklist it was given. Under Workflow, a stage can be given an SLA, a permission, and marked active or skippable. The order and the stage code stay fixed. Higher approval stays off until it is turned on here.

## How to delete a company or dealer

Delete asks for a reason. The row leaves the list and stays in the database. The reason is written to the activity log.
