# Rate provider contract v1

An administrator registers a fixed HTTPS JSON endpoint; Bearer authorization is optional and obtained from a server environment variable. No redirects are followed. Response size is limited to 64 KiB; timeout is 10 seconds.

```json
{"version":"source-version-123","value":"10000000.0000","currency":"IRR","unit":"IRR_PER_GRAM","reference_purity":"750","effective_at":1800000000,"valid_until":1800000060}
```

All financial values are strings. `unit` is `IRR_PER_GRAM` or `IRR_PER_ITEM`. Currency is IRR; weights use grams; purity uses per mille. Scientific notation, floats, negative numbers, missing type or overprecision are rejected. Effective time must not be in the future; validity must fall within configured rate TTL. The locally recorded UUID, source version, fetched time and source are retained. Failure marks provider health and prevents stale buying. Explicit configured fallback may select another valid source; it never changes stock authority.

REST Gold endpoints under `gold-commerce/v1`: GET rates/preview (rate limited), POST quotes (bounded customer input), GET quotes/{uuid} (owner), POST quotes/{uuid}/payment (one-use payment token), POST orders/{id}/issue (gc_issue_quotes), POST rates/manual (gc_manage_rates), GET health (gc_manage_settings), POST support (own authenticated order). Cookie-authenticated REST requests require X-WP-Nonce. Guest ownership uses X-GC-Owner on the status route. Payment verification is not a public Gold REST endpoint.
