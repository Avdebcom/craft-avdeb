# Release Notes for AVDEB Products

## 1.0.0 - 2026-11-21

### Added
- Twig functions `avdebProducts()`, `avdebProduct()` and `avdebButton()`, and `craft.avdeb.*`.
- The `|avdeb` filter turns `{avdeb-products …}`, `{avdeb-product …}` and `{avdeb-button …}` tags typed in CKEditor or any rich text field into product embeds.
- `craft.avdeb.data()` returns product cards for your own markup.
- Grid, carousel, list and spotlight layouts; single products as a horizontal card, a hero or a compact card, with your own badge and note.
- Settings page with connection status, environment variable support for the secret key and referral code, display defaults, affiliate disclosure and "Refresh products now".
- Product data cached in the site cache with a one-week stale fallback and a request budget; "AVDEB product data" option in Utilities → Caches.
- Zero-specificity styles with `--avdeb-*` design tokens; the stylesheet can be turned off.
