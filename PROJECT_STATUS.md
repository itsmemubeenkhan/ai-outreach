# Project Status

## Completed

- Environment inspection and Laravel 12 installation on PHP 8.2
- Laravel Breeze authentication and production frontend build
- Professional responsive dashboard shell and KPI queries
- Indexed, paginated Leads schema and CRUD
- Server-side lead filtering and email/phone normalization
- CSV upload, five-row preview, explicit column mapping, streamed queue processing in 500-row batches
- Import history, progress counters, duplicate email handling, and rejected-row recording
- MySQL `ai_outreach` configuration and setup documentation
- Managed brands seeded for The Brand Maker and Aspire Website Designs
- Campaign CRUD with daily limits, dates, sender strategy, lifecycle validation, and professional UI
- Filter-defined campaign audiences with live estimates and queued, chunked campaign-lead materialization
- Per-lead campaign state designed for sequence progress and safe stopping
- Multi-step email sequence editor with ordering, delays, enable/disable controls, supported variables, and safe sample preview
- Owner-authorized sending account CRUD with encrypted SMTP/IMAP credentials and blank-password preservation
- Timeout-bound SMTP/IMAP connection checks and one-recipient SMTP test action with sanitized failures
- Per-job dynamic Symfony SMTP transport without shared global mail configuration
- Campaign-to-sending-account assignment and daily-limit-aware round-robin selection
- Idempotent outbound email log with a unique campaign-lead/sequence-step delivery constraint
- Guarded queued campaign and follow-up jobs that re-check campaign, lead, sender, suppression, sequence, due-date, and daily-limit state
- Log-backed campaign/account daily enforcement and counter reconciliation in the application timezone
- Stream-safe plain-text template rendering and functional signed unsubscribe links
- Production-safe suppression list and automatic campaign stopping on unsubscribe
- Scheduler dispatch command, campaign delivery metrics, sending-account health UI, and paginated outbound log
- Pure-PHP IMAP reply polling with per-mailbox queued jobs, UID cursors, health errors, and overlap protection
- Header-first reply matching with conservative sender/account fallback and unmatched-message preservation
- Idempotent inbound message storage and immediate reply-based campaign stopping before classification
- Deterministic unsubscribe-by-reply detection and suppression enforcement
- Mock AI provider abstraction with deterministic reply classification and recommended human actions
- CRM Inbox, safe text-only reply detail, Hot Leads, Tasks, and lead activity timeline
- Configurable/idempotent reply scoring and internal database hot-lead notifications
- Reply-focused dashboard data, mailbox scheduler command, and attachment metadata-only handling
- ZIP archive lead importer with automatic mixed-schema CSV mapping, CSV-only staging, resumable per-file jobs, and duplicate XLSX avoidance
- Power Dialer with category queues, phone-only lead selection, start/pause/resume/skip/stop controls, dispositions, notes, and call history
- Windows `tel:` launch integration for Zoom Phone/default desktop calling app
- Signed Zoom Phone webhook foundation, provider call IDs, duration/result capture, and queued AI-summary lifecycle fields

## In Progress

- Optional open/click tracking remains deferred until the reply workflow is manually validated

## Remaining

- Optional open/click tracking with tokenized destinations
- Settings UI and future real AI/Telegram providers
- Complete Zoom OAuth/API summary retrieval after Zoom Phone credentials and eligible licensing are provided
- Role/permission policy design once multi-user requirements are defined

## Important architecture decisions

- Laravel 12 is the current PHP 8.2-compatible baseline.
- MySQL is the application database; database-backed queues keep local operations simple.
- Lead audiences will be stored as filter definitions plus per-campaign state, not duplicated lead records.
- Audience materialization is queued and chunked; changing filters safely rebuilds pending membership while preserving already-processed state.
- Campaign activation requires a ready audience and at least one enabled sequence step.
- Campaign activation also requires an assigned sending account; first-version sender selection is conservative round robin.
- A delivery is claimed in the database before SMTP. Ambiguous SMTP failures are logged and not automatically retransmitted, prioritizing duplicate prevention.
- Daily enforcement is queried from outbound logs; `sent_today` is a reconciled UI counter rather than the source of truth.
- Outbound mail uses a per-job Symfony transport so credentials cannot leak between long-running queue jobs.
- CSV processing is stream-based and queued. The browser request only stages and maps a file.
- Automation must stop on replies/positive intent; AI will never negotiate or close sales.
- Inbound matching prefers `In-Reply-To`/`References`; ambiguous fallback matches remain unmatched.
- Reply ingestion stops automation transactionally before the queued classifier runs.
- Inbound HTML is retained for future sanitized use but never rendered; the UI displays derived plain text only.
- Power Dialer does not automate the Zoom desktop UI. It uses the OS phone handler and official webhook/API integration points.

## Commands needed from me

- Keep XAMPP Apache/MySQL running.
- Run `C:\xampp\php\php.exe artisan queue:work --tries=3` while processing imports and campaign mail.
- During development, trigger one scheduling cycle with `C:\xampp\php\php.exe artisan schedule:run` or process immediately with `C:\xampp\php\php.exe artisan outreach:process`.
- Dispatch mailbox checks manually with `C:\xampp\php\php.exe artisan outreach:check-replies`.
- In production, invoke `artisan schedule:run` every minute through Windows Task Scheduler.
- Set Zoom Workplace as Windows' default `TEL` link handler before using Power Dialer.
- Add a first mailbox from **Sending Accounts → Add account**, save it paused, test SMTP, send one test to an address you control, then activate it and assign it to a campaign.

## Known issues

- Apache must point requests through the `public` directory; the default local URL is `/ai-outreach/public`.
- XAMPP PHP does not have the `imap` extension enabled; `webklex/php-imap` 6.2 provides pure-PHP polling, so XAMPP itself was not modified.
- Automated campaign SMTP is intentionally conservative: a transport exception after an attempted send is not automatically retried because provider acceptance may be ambiguous.
- Attachment contents are not persisted or downloaded intentionally; only filename/MIME/size metadata is recorded when provided by IMAP.
- Mock classification is deterministic development logic, not production-grade language understanding.
- Open/click tracking, paid AI, Telegram, and automatic reply sending remain intentionally inactive.
- Zoom Phone OAuth and webhook credentials are not configured; real call-completion and AI-summary synchronization remain disabled until an eligible Zoom Phone account is connected.

## New migrations

- `create_inbound_messages_table`
- `create_tasks_table`
- `create_lead_activities_table`
- `create_hot_lead_notifications_table`
- `add_reply_state_to_campaign_leads_table`
- `add_imap_cursor_to_sending_accounts_table`

## Safe one-reply test

1. Use two addresses you control and send one campaign email only.
2. Reply from the recipient while preserving the email thread.
3. Run `C:\xampp\php\php.exe artisan outreach:check-replies` and keep the queue worker running.
4. Confirm the reply appears in Inbox, its campaign lead is stopped, classification completes, and a positive reply appears in Hot Leads.
5. Run `C:\xampp\php\php.exe artisan outreach:process` and confirm no follow-up is queued or sent.
