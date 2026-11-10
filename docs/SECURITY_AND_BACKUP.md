# Security, backup and restore

* Use HTTPS, updated server packages, individual staff accounts, least-privilege capabilities, restricted DB access and independent credentials per shop. Enable provider-supported MFA for administrators separately from optional customer OTP.
* Secrets belong in environment/wp-config, never source, exports, browser HTML or logs. Do not expose full environment, raw order payloads, payment tokens, card data, CVV or gateway credentials. Configure HTTP/access/error logging to redact `gc_token`, order keys and authorization headers. Gold logs store safe codes/hashes, not those tokens.
* Exclude cart, checkout, account, tracking, quote URLs, Woo sessions and all financial REST responses from page/CDN caches. Respect no-store/no-cache, cookies and private route headers. Purge public product pages when product/rate state changes; never serve a public price beyond source TTL.
* Sync the OS clock using NTP. Store timestamps UTC and present them using the shop timezone. Rate TTL, quote TTL and settlement deadline are separate. The accepted grace window is snapshotted; changing settings does not extend an existing intent.
* Run real cron every minute. Action Scheduler group `gold-commerce` handles recurring maintenance and notification triggers; WP cron is a fallback. Test the actual host's CLI/cron process and alarms. A stopped cron never makes an expired quote valid; server checks still deny it.
* Pending notifications are secretbox-encrypted using a key derived from the site's auth salt. Rotate API secrets separately. Changing WP auth salts invalidates encrypted pending messages; reconcile or reissue links. A mail-provider timeout/crash after acceptance cannot be solved by an unconditional resend. Inspect `sending` records before manual retry.
* Financial records are retained on deactivation/uninstall. Configurable notification retention removes old delivered/dead bodies only; it does not erase receipts or invoices. WP privacy exporter exposes customer-owned support data; financial/support deletion requests remain explicit policy review.

## Backup procedure

1. Record app/schema/plugin versions, stock authority, queues and reconciliation totals.
2. Take a consistent InnoDB database snapshot with `wp db export --single-transaction --quick` to a private location. Include WP config/secrets in an independently encrypted secret backup, never a public package.
3. Back up uploads, installed packages, MU guard and custom configuration. Encrypt offsite and restrict access. Determine retention/RPO/RTO with the shop operator; no arbitrary production SLA is asserted.
4. Restore to an isolated database and private filesystem with all live gateways, SMTP, SMS, rate and Agah egress disabled. Preserve store/company identity for reconciliation; use a new test identity before any mock export to another company.
5. Verify exact counts/hashes of issued/paid quotes, receipts, invoice snapshots and outbox states. Check one checkout, one expired link, one duplicate callback and one stock/refund cycle. Restart queues only after provider reconciliation to avoid repeating unknown external operations.
6. Record measured restore duration and mismatches. A successful backup command alone does not prove restorability. Evidence from this delivery's local restore drill is separate from the owner's actual-host acceptance.

Rollback preserves newly received money. Never restore an old DB over current paid orders without separately reconciling gateway receipts. The audit chain is tamper-evident; secure offsite checkpoints are required against a malicious DB administrator.
