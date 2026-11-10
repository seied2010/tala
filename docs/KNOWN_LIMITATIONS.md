# Known limitations and external gates

This package implements and tests standalone Woo/Gold workflows with synthetic data. It is not certification of an unselected bank, a real Agah installation or an owner's production host.

| Area | Delivered status / practical limit |
|---|---|
| Agah | **MOCK_ONLY**. Supplied alpha12 contains no public API/reservation service matching the proposed contract. Live connected purchases stay blocked. Do not describe the simulator as operational accounting integration |
| Payment | Included gateway is **TEST_ONLY**, no funds moved. Bank signature/amount/refund adapters need the chosen provider's real Sandbox and reconciliation tests. Generic Woo hooks depend on that gateway correctly verifying its provider |
| Automatic rate | HTTPS provider contract, persistence, freshness, fallback and manual rate are implemented. A real commercial provider and its credentials are **BLOCKED_EXTERNAL**; none is impersonated |
| SMS, OTP, social publication | Official provider-specific adapters/accounts are owner inputs. A notification-channel interface exists; email queue uses WP mail. No real SMS/OTP or social publishing is claimed; optional features remain disabled |
| Email | Encrypted queue/dedup/dispatch are implemented. Synthetic inbox inspection proves link content/workflow, not actual SMTP deliverability. A `sending` state after a crash is an unknown delivery outcome requiring reconciliation |
| Checkout | **Classic Woo Cart/Checkout is the supported tested alternative. Blocks checkout for Gold is denied.** Existing block pages require the explicit setup action; no content is silently overwritten |
| Persian font/content | Owner WOFF2/logo/product photos/policies are pending. Local system-font fallback and original decorative SVG are bundled. The official fa_IR language packs should be installed for complete WP/Woo translation; critical checkout labels have a local fallback |
| Trading extensions | Gold wallet, customer buyback, exchange, credit, presale, consignment and complex multiwarehouse remain disabled by design, as required by the specification |
| Financial precision | Gold computations use exact decimal strings and arbitrary-length intermediates. Woo/gateway boundary is integer IRR. Proposed Agah hundredth-rial values require an explicitly agreed rounding/reconciliation contract before live activation |
| Tax and discounts | Policy and component allocation are configurable. Sample percentages are fictional. Gold tax is already in item price; Woo must not tax the same Gold amount again. Shipping taxes are separate |
| Manual final amount | A seller-declared final total is preserved exactly with INCLUDED_UNITEMIZED status; unknown tax/components are not fabricated or shown as tax-exempt. Choose the itemized FIXED/manual-rate model when the seller must provide each tax component |
| Reports | XLSX/DOCX/PDF generated locally. Period export caps at 10,000 records with an explicit error to narrow the range, never silent truncation. Grouped sales are gross, detailed sales list refunds separately; multi-category items appear under each category |
| Large catalogs | Current-price filtering resolves Gold prices server-side across visible candidates; profile and index/queue this path for large real catalogs. Dynamic pricing pages default to no-store to prevent stale cached price/schema. Static assets remain cacheable |
| Accessibility | Automated axe and keyboard tests cover listed surfaces/default themes; no formal screen-reader certification or guarantee for arbitrary owner colors/content |
| Performance | Local lab and synthetic bursts only. See exact load response/error/P95 data and hardware. No production concurrency guarantee, real mobile network benchmark or field INP is asserted |
| Backup | Local DB and application-file restore drill is recorded. Offsite encryption, production-host recovery, actual RPO/RTO and operational alarms remain owner-host acceptance |
| PHP 8.5 | Exact tested WordPress/Woo/Yoast and test gateway versions are recorded. Third-party WP CLI deprecation output is tooling context; compatibility of a future unselected real gateway is not assumed |

No feature is silently marked passed. TEST_REPORT separates automated success, failed historical runs, external blocks and untested operational checks. The original Agah ZIP checksum is unchanged and proprietary Agah code is excluded from all archives.
