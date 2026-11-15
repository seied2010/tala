# Test report — Gold Commerce 1.2.0

Generated UTC: 2026-11-10T14:07:19.916525+00:00. Specification 1.1. Tests use synthetic data only.

## Exact environment

WordPress 7.1.4, WooCommerce 11.2.2, Yoast 28.8; HPOS enabled. PHP 8.3.36 and 8.5.12; MariaDB 11.8.9. Classic checkout with Gold TEST_ONLY gateway. Two databases/stores and a third clean ZIP installation. See build-versions.json and docker-images.txt.

PHP suites: 140 checks executed across the two runtimes; 140 passed, 0 failed. Each runtime includes 500 deterministic exact-arithmetic comparisons inside its unit suite. Counts are executed checks, not a project-completion percentage.

## اعتبارسنجی تکمیلی نسخه ۱.۲.۰ و کاتالوگ نمونه

در ۲۰۲۶-۱۱-۱۵، سورس هسته با ZIP ارسالی `gold-commerce-core-1.2.0.zip` با SHA-256 برابر `663ae7f9a2dba771afcf58de8606d23158bd897e6c1a07f4760f1f1c380c5ba4` تطبیق داده شد. مجموعه‌های PHP دوباره روی PHP 8.3.36 و 8.5.12، WordPress 7.1.4، WooCommerce 11.2.2 و HPOS اجرا شدند: در هر runtime تعداد ۱۱ واحد، ۴۳ یکپارچه‌سازی، ۸ امنیت و ۸ extended پاس شد؛ جمعاً ۱۴۰ پاس و صفر شکست در اجرای معتبر.

افزونه `Tala Demo Catalog 1.0.0` نیز روی فروشگاه دورریختنی آزموده شد: ورود ۲۷ محصول، ورود مجدد بدون تکرار، خریدپذیری و موجودی همه ۲۷ قلم، افزودن یک محصول به سبد، محاسبه مبلغ و تولید URL تسویه، حذف ۲۷ محصول و حذف تصاویر متعلق به افزونه همگی PASS هستند. محصولات نمونه، کالای ساده WooCommerce هستند و موفقیت پرداخت واقعی همچنان به درگاه و تنظیمات سایت مقصد وابسته است. جزئیات ساختاریافته در `evidence/release-1.2.0-validation.json` و `evidence/demo-catalog.json` ثبت شده‌اند.

## Mandatory acceptance mapping

| ID | Result | Executed evidence | Scope |
|---|---|---|---|
| T01 | PASS | unit-php83.json, unit-php85.json | Local automated test; no live bank certification. |
| T02 | PASS | unit-php83.json, unit-php85.json | Local automated test; no live bank certification. |
| T03 | PASS | unit-php83.json, unit-php85.json | Local automated test; no live bank certification. |
| T04 | PASS | unit-php83.json, unit-php85.json | Local automated test; no live bank certification. |
| T05 | PASS | unit-php83.json, unit-php85.json | Local automated test; no live bank certification. |
| T06 | PASS | unit-php83.json, unit-php85.json | Local automated test; no live bank certification. |
| T07 | PASS | integration-php83.json, integration-php85.json | Local automated test; no live bank certification. |
| T08 | PASS | unit-php83.json, unit-php85.json | Local automated test; no live bank certification. |
| T09 | PASS | integration-php83.json, integration-php85.json | Local automated test; no live bank certification. |
| T10 | PASS | unit-php83.json, unit-php85.json | Local automated test; no live bank certification. |
| T11 | PASS | integration-php83.json, integration-php85.json | Local automated test; no live bank certification. |
| T12 | PASS | integration-php83.json, integration-php85.json | Local automated test; no live bank certification. |
| T13 | PASS | integration-php83.json, integration-php85.json | Local automated test; no live bank certification. |
| T14 | PASS | integration-php83.json, integration-php85.json | Local automated test; no live bank certification. |
| T15 | PASS | concurrency.json, integration-php83.json, integration-php85.json | Local automated test; no live bank certification. |
| T16 | PASS | integration-php83.json, integration-php85.json | Local automated test; no live bank certification. |
| T17 | PASS | integration-php83.json, integration-php85.json | Local automated test; no live bank certification. |
| T18 | PASS | concurrency.json, integration-php83.json, integration-php85.json | Local automated test; no live bank certification. |
| T19 | PASS | integration-php83.json, integration-php85.json | Local automated test; no live bank certification. |
| T20 | PASS | integration-php83.json, integration-php85.json | Local automated test; no live bank certification. |
| T21 | PASS | integration-php83.json, integration-php85.json | Local automated test; no live bank certification. |
| T22 | PASS | integration-php83.json, integration-php85.json | Local automated test; no live bank certification. |
| T23 | PASS | concurrency.json, integration-php83.json, integration-php85.json | Local automated test; no live bank certification. |
| T24 | PASS | integration-php83.json, integration-php85.json | Local automated test; no live bank certification. |
| T25 | PASS | integration-php83.json, integration-php85.json | Local automated test; no live bank certification. |
| T26 | PASS | integration-php83.json, integration-php85.json | MOCK_ONLY / proposed Agah contract; actual API BLOCKED_EXTERNAL. |
| T27 | PASS | integration-php83.json, integration-php85.json | MOCK_ONLY / proposed Agah contract; actual API BLOCKED_EXTERNAL. |
| T28 | PASS | integration-php83.json, integration-php85.json | MOCK_ONLY / proposed Agah contract; actual API BLOCKED_EXTERNAL. |
| T29 | PASS | integration-php83.json, integration-php85.json | MOCK_ONLY / proposed Agah contract; actual API BLOCKED_EXTERNAL. |
| T30 | PASS | integration-php83.json, integration-php85.json | MOCK_ONLY / proposed Agah contract; actual API BLOCKED_EXTERNAL. |
| T31 | PASS | integration-php83.json, integration-php85.json | MOCK_ONLY / proposed Agah contract; actual API BLOCKED_EXTERNAL. |
| T32 | PASS | integration-php83.json, integration-php85.json | MOCK_ONLY / proposed Agah contract; actual API BLOCKED_EXTERNAL. |
| T33 | PASS | zip-install.json | Local automated test; no live bank certification. |
| T34 | PASS | integration-php83.json, integration-php85.json, zip-install.json | Local automated test; no live bank certification. |
| T35 | PASS | integration-php83.json, integration-php85.json, lifecycle.json | Local automated test; no live bank certification. |
| T36 | PASS | integration-php83.json, integration-php85.json | Local automated test; no live bank certification. |
| T37 | PASS | browser.json | Classic checkout alternative, actual browser purchase; Blocks denied for Gold. |
| T38 | PASS | browser.json, extended-php83.json, extended-php85.json, integration-php83.json, integration-php85.json, security-php83.json, security-php85.json, unit-php83.json, unit-php85.json | Both PHP runtimes, actual WP/Woo/Yoast and test gateway; unselected real gateway BLOCKED_EXTERNAL. |
| T39 | PASS | browser.json | Local automated test; no live bank certification. |
| T40 | PASS | browser.json | Keyboard + axe WCAG-tagged checks. Formal screen-reader audit NOT_TESTED. |
| T41 | PASS | browser.json, integration-php83.json, integration-php85.json, seo.json | Local automated test; no live bank certification. |
| T42 | PASS | browser.json | Local automated test; no live bank certification. |
| T43 | PASS | export-readers.json, integration-php83.json, integration-php85.json | Local automated test; no live bank certification. |
| T44 | PASS | store-isolation.json | Independent UUID/DB/brand/color; TEST_ONLY adapter in both, real gateway choice pending. |
| I01 | PASS | integration-php83.json, integration-php85.json | MOCK_ONLY / proposed Agah contract; actual API BLOCKED_EXTERNAL. |
| I02 | PASS | integration-php83.json, integration-php85.json | MOCK_ONLY / proposed Agah contract; actual API BLOCKED_EXTERNAL. |
| I03 | PASS | integration-php83.json, integration-php85.json | MOCK_ONLY / proposed Agah contract; actual API BLOCKED_EXTERNAL. |
| I04 | PASS | integration-php83.json, integration-php85.json | MOCK_ONLY / proposed Agah contract; actual API BLOCKED_EXTERNAL. |
| I05 | PASS | integration-php83.json, integration-php85.json | MOCK_ONLY / proposed Agah contract; actual API BLOCKED_EXTERNAL. |
| I06 | PASS | integration-php83.json, integration-php85.json | MOCK_ONLY / proposed Agah contract; actual API BLOCKED_EXTERNAL. |
| I07 | PASS | integration-php83.json, integration-php85.json | MOCK_ONLY / proposed Agah contract; actual API BLOCKED_EXTERNAL. |
| I08 | PASS | integration-php83.json, integration-php85.json | MOCK_ONLY / proposed Agah contract; actual API BLOCKED_EXTERNAL. |
| I09 | PASS | integration-php83.json, integration-php85.json | MOCK_ONLY / proposed Agah contract; actual API BLOCKED_EXTERNAL. |
| I10 | PASS | integration-php83.json, integration-php85.json | MOCK_ONLY / proposed Agah contract; actual API BLOCKED_EXTERNAL. |
| I11 | PASS | integration-php83.json, integration-php85.json | MOCK_ONLY / proposed Agah contract; actual API BLOCKED_EXTERNAL. |
| I12 | PASS | integration-php83.json, integration-php85.json, lifecycle.json | MOCK_ONLY / proposed Agah contract; actual API BLOCKED_EXTERNAL. |

## Browser, exports and lifecycle

Direct purchase was completed in Chromium on both PHP-backed sites. The separate quote-browser.json records guest request, seller issuance through an actual protected admin form, synthetic encrypted inbox, confirmation and Woo pay-order completion. No SMTP or real funds are implied. customizer.json records 7 Blue Pencil shortcuts, selective heading refresh and nonsecret design export. accessibility.json records 16 light/dark/mobile/desktop scans; browser.json includes keyboard, private headers, canonical/schema and no JavaScript errors. seo.json separately checks Yoast product-tag noindex and sitemap exclusion.

DOCX/XLSX/PDF were reopened by python-docx/openpyxl/pypdf, including a formula-injection string retained as an XLSX string. Actual plugin deactivation retained financial row counts and persistent financial guard denial. ZIP installation used extracted packages, not source symlinks. restore.json records an independent DB import, full quote snapshot digest and application-file SHA256 checks.

## Load and performance — preserve the limits

32 original burst phases (50/100/300/1000 virtual users × 8 operations): 31 PASS, 1 FAIL under a 180-second budget. These are short bursts, not sustained arrival-rate or real-user capacity tests. Hardware: 2 CPU quota cores, 8 GiB shared outer limit, PHP built-in server with 8 workers, MariaDB 11, no page cache, local network. Other regression work shared CPU. load-resources.jsonl records time samples; individual phase rows include response codes, throughput and P95.

The original operation named callback also created a new order, issued its quote and began payment before settling. At 1000 users this combined workflow exceeded the budget: 775 HTTP 200, 224 client timeouts, 1 safe authority denial while a simultaneous test toggled Agah mode. This historical failure remains in load.json; it is not relabelled as a pass. The earlier insertion-gap deadlock failure is separately preserved in load-before-gap-lock-fix.json and corrected in code.

A corrected settlement-only operation used 1000 already-started intents: PASS, response codes {'200': 1000}, P95 168508.63 ms, throughput 5.65 requests/s. CPU was still shared with regression work. load-callback-reconciliation.json checks 1000 Woo paid orders, 1000 paid quotes and 1000 unique receipts. This supersedes the mislabeled callback operation as a measurement of settlement itself, and does not establish a fast production SLA.

| Users | Operation | HTTP/error result | P95 ms | Throughput/s |
|---|---|---|---|---|
| 50 | browse | PASS / {'200': 50} | 3683.26 | 12.87 |
| 50 | product | PASS / {'200': 50} | 3501.47 | 13.14 |
| 50 | search | PASS / {'200': 50} | 4177.32 | 11.21 |
| 50 | request | PASS / {'200': 50} | 5909.24 | 7.74 |
| 50 | issue | PASS / {'200': 50} | 5569.79 | 8.1 |
| 50 | checkout | PASS / {'200': 50} | 7935.61 | 5.95 |
| 50 | callback | PASS / {'200': 50} | 8016.74 | 5.81 |
| 50 | queue | PASS / {'200': 50} | 3685.11 | 12.78 |
| 100 | browse | PASS / {'200': 100} | 7188.23 | 13.39 |
| 100 | product | PASS / {'200': 100} | 7405.51 | 12.41 |
| 100 | search | PASS / {'200': 100} | 7382.47 | 12.78 |
| 100 | request | PASS / {'200': 100} | 9078.8 | 10.54 |
| 100 | issue | PASS / {'200': 100} | 11512.54 | 8.2 |
| 100 | checkout | PASS / {'200': 100} | 12440.21 | 7.38 |
| 100 | callback | PASS / {'200': 100} | 22574.51 | 4.19 |
| 100 | queue | PASS / {'200': 100} | 10000.39 | 9.33 |
| 300 | browse | PASS / {'200': 300} | 24522.31 | 11.21 |
| 300 | product | PASS / {'200': 300} | 23250.94 | 12.22 |
| 300 | search | PASS / {'200': 300} | 26881.36 | 10.35 |
| 300 | request | PASS / {'200': 300} | 27726.92 | 9.93 |
| 300 | issue | PASS / {'200': 300} | 33611.36 | 8.34 |
| 300 | checkout | PASS / {'200': 300} | 39005.4 | 7.07 |
| 300 | callback | PASS / {'200': 300} | 57671.4 | 4.84 |
| 300 | queue | PASS / {'200': 300} | 25899.78 | 10.95 |
| 1000 | browse | PASS / {'200': 1000} | 89003.13 | 10.25 |
| 1000 | product | PASS / {'200': 1000} | 86984.54 | 11.0 |
| 1000 | search | PASS / {'200': 1000} | 83990.87 | 11.02 |
| 1000 | request | PASS / {'200': 1000} | 115814.89 | 8.03 |
| 1000 | issue | PASS / {'200': 1000} | 156817.22 | 5.92 |
| 1000 | checkout | PASS / {'200': 1000} | 152783.35 | 6.2 |
| 1000 | callback | FAIL / {'200': 775, '409': 1, 'TimeoutError': 224} | 180794.42 | 5.53 |
| 1000 | queue | PASS / {'200': 1000} | 121660.13 | 7.58 |

Lab performance samples: [{"width": 1440, "lcp": 1076, "cls": 0, "eventDuration": 56, "ttfb": 278.3999999994412, "bytes": 325321, "status": "PASS", "scope": "Local Chromium lab sample, no network/CPU throttling. eventDuration is a sampled interaction duration, not field INP."}, {"width": 390, "lcp": 536, "cls": 0, "eventDuration": 24, "ttfb": 216, "bytes": 325321, "status": "PASS", "scope": "Local Chromium lab sample, no network/CPU throttling. eventDuration is a sampled interaction duration, not field INP."}]. Field INP is NOT_TESTED and needs the actual deployed site/users. Do not interpret sampled event duration as field INP.

## External and manual gates

* BLOCKED_EXTERNAL: real Agah OpenAPI/reservation/staging/company binding; actual bank Sandbox and refunds; rate provider credentials; official SMS/OTP/social adapters; licensed storefront WOFF2/owner media; production host/DNS/TLS/SMTP/cron/offsite backup.
* NOT_TESTED: formal screen-reader certification, field Core Web Vitals/INP, sustained production load, chosen third-party bank plugin under PHP 8.5, disaster recovery on the actual host.
* Full-workflow 1000-user burst has a recorded capacity limit. Deploy with a measured FPM/DB/cache/queue configuration and define a real concurrency target before selling a capacity SLA.

## Reproduction and audit trail

See REPRODUCING_TESTS.md and executable tests/tools. Structured JSON evidence includes runtime/time/result. Raw runtime configs, SQL backups, credentials, request tokens and proprietary Agah source are excluded from delivery. The original Agah ZIP hash is unchanged. The specification checksums were verified. No missing or blocked operational check is counted as production approval.
