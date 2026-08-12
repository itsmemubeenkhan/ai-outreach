# AI Outreach CRM

Laravel 12 CRM foundation for scalable B2B prospect management and guarded queued outreach. It includes encrypted multi-mailbox SMTP/IMAP configuration, campaign sender assignment, suppression and signed unsubscribe handling, idempotent outbound logs, conservative round-robin delivery, and follow-up scheduling.

## Local setup (XAMPP / Windows)

1. Start Apache and MySQL in XAMPP.
2. Create a MySQL database named `ai_outreach` (the installer already attempts this locally):
   `C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE ai_outreach CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"`
3. Copy `.env.example` to `.env` if `.env` does not exist, then set database credentials. Never commit real credentials.
4. Run `composer install`, `npm install`, `C:\xampp\php\php.exe artisan key:generate`, `C:\xampp\php\php.exe artisan migrate`, and `npm run build`.
5. Open `http://localhost/ai-outreach/public`, register the first user, and sign in.
6. For queued imports and campaign delivery, keep `C:\xampp\php\php.exe artisan queue:work --tries=3` running.

For scheduler-driven outreach, configure Windows Task Scheduler to invoke `C:\xampp\php\php.exe artisan schedule:run` every minute from this project directory. During development, run one cycle with `C:\xampp\php\php.exe artisan schedule:run`, or dispatch immediately with `C:\xampp\php\php.exe artisan outreach:process`.

## Add and safely test a sending account

1. Open **Sending Accounts → Add account**. Use provider-issued SMTP/app-password credentials; start with status **Paused**.
2. Save, then use **Test SMTP**. Use **Test IMAP** only after completing its fields.
3. Enter an email address you personally control in **Send one test**. This action sends exactly one standalone test and does not start a campaign or retain the recipient.
4. When tests pass, set the account **Active**, edit a campaign, assign the account, verify its audience and sequence, then activate the campaign.
5. Run `C:\xampp\php\php.exe artisan outreach:process` and keep the queue worker running.

Passwords are encrypted with `APP_KEY`, hidden from serialization/pages, and blank password fields preserve saved values. Back up `APP_KEY`; changing it without `APP_PREVIOUS_KEYS` makes stored credentials unreadable.

## Reply polling

Reply ingestion uses `webklex/php-imap` 6.2 and does not require XAMPP's `ext-imap`. Active mailboxes with complete IMAP settings are checked every five minutes by the scheduler. To run one check manually:

```powershell
C:\xampp\php\php.exe artisan outreach:check-replies
C:\xampp\php\php.exe artisan queue:work --tries=3
```

For a safe manual test, send one campaign email between addresses you control, reply in the same thread, run the commands above, and verify Inbox shows the reply and the campaign lead is stopped. Positive replies appear under Hot Leads; do not use bulk outreach for this test.

Inbound HTML is never rendered. Attachment contents are not stored; only metadata is retained. Mock AI classification is deterministic and free, and never sends responses, negotiates, or closes sales.

## Import a ZIP dataset

Large ZIP archives can be staged as one queued import per CSV. XLSX files are skipped to avoid importing duplicate exports:

```powershell
C:\xampp\php\php.exe artisan outreach:import-archive "C:\absolute\path\leads.zip" --user=admin@aioutreach.local
C:\xampp\php\php.exe artisan queue:work --tries=3 --timeout=1200 --memory=512
```

The command is resumable and will not queue the same staged archive entries twice. Blank emails are allowed; supplied invalid emails are rejected, and populated duplicate emails are ignored by the database constraint.

## Power Dialer and Zoom Phone

Open **Power Dialer**, select a category, and start a session. The **Dial with Zoom** action opens a standard `tel:` URL; configure Zoom Workplace as Windows' default TEL handler. Save a disposition after each call to record the result and load the next lead.

Real call completion and Zoom-generated AI summary synchronization require an eligible Zoom Phone account and Zoom Marketplace app. Configure `ZOOM_PHONE_ENABLED`, `ZOOM_ACCOUNT_ID`, `ZOOM_CLIENT_ID`, `ZOOM_CLIENT_SECRET`, and `ZOOM_WEBHOOK_SECRET` in `.env`, then expose the signed webhook endpoint `/webhooks/zoom-phone` over public HTTPS. Credentials are intentionally blank by default. The CRM does not automate mouse/keyboard actions in the Zoom desktop application.

## Testing

Run `C:\xampp\php\php.exe artisan test` and `npm run build`.

## Security and scale notes

Authentication routes use CSRF protection and rate limiting. Eloquent/query builder parameterization is used throughout. Lists are paginated server-side. CSVs are streamed with `SplFileObject`, inserted in batches, and processed outside HTTP requests. The application key protects future encrypted integration credentials; never expose saved SMTP or IMAP passwords.
