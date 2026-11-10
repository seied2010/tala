# G0 threat model

Assets: quoted prices, payment receipts, inventory commitments, customer ownership, API credentials, company/store boundaries and immutable invoice evidence.

| Threat | Boundary/control | Evidence target |
|---|---|---|
| Client changes amount or product | Price from server model; issued item/quantity/shipping/snapshot comparison | T07,T16,T19 |
| Replay / double click / callbacks | Atomic order mutex, single intent, consumed token, provider/transaction receipt uniqueness | T15,T18,T20 |
| Last unit contention | Native Woo InnoDB held-stock with pending order status; finite managed stock required | T23,T24 |
| Expired link or late bank receipt | UTC/server clock; quote TTL and payment grace; durable review receipt | T13,T17,T22 |
| Guest IDOR | Separate 256-bit guest ownership credential or authenticated matching customer | T11,SEC |
| CSRF / privilege escalation | WP nonce plus capability; REST cookie nonce; explicit admin setup approval | T09,SEC |
| XSS / SQL injection / malicious report formulas | Escaping, prepared values, constrained identifiers; XLSX inline strings and XML escaping | SEC,T43 |
| SSRF | Administrator-only HTTPS provider URL, WP safe HTTP validation, no redirect/private host/userinfo | SEC |
| Cross-company / forged webhook | Store/company binding, HMAC timestamp/body, replay inbox and hash conflict detection | I02,T28 |
| Timeout after remote commit | Stable idempotency and lookup; persistent retry queue | T27,T29,I07,I08 |
| Core removal / stale authority | Persistent MU guard; no implicit authority switch; no mock-connected purchase | T35,I11,I12 |
| Secrets / payment link disclosure | Environment-only API secrets, hashed quote tokens, encrypted pending notification body, no-referrer/private headers | SEC,T42 |

Residual trust: a verified gateway adapter, database/operator access, HTTPS host, reliable cron, configured SMTP, OS clocks and the actual third-party APIs. The database owner can rewrite any database; the audit hash chain is tamper-evident, not a substitute for offsite signed backups. A notification left in `sending` after a crash must be reconciled with the mail provider before retry. Production configuration, real sandbox bank verification, real SMS and live Agah remain explicit external gates.
