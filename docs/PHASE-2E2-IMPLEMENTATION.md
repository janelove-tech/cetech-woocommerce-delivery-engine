# Phase 2E2 — Cart Selection Persistence Hardening + Cart Revalidation Foundation

**Status:** Complete  
**Plugin version:** 0.1.0 (foundation)  
**Schema target:** `2` (unchanged)  
**Sources:** `docs/PHASE-2E1-IMPLEMENTATION.md`, `docs/ARCHITECTURE-PLAN.md`

## What was added

Phase 2E2 hardens captured cart delivery selections across WooCommerce session reloads and introduces read-only cart revalidation with optional cart-page warnings.

| Area | Files / behaviour |
|------|-------------------|
| Session restore | `CartDeliverySelectionCapture::restore_cart_item_from_session()` via `woocommerce_get_cart_item_from_session` |
| Session normalization | `CartDeliverySelectionSessionData` |
| Fingerprint helper | `CartDeliverySelectionFingerprint` (stable hash format from 2E1) |
| Cart revalidation | `CartDeliverySelectionRevalidator` + `CartDeliverySelectionRevalidationResult` |
| Cart warnings | `woocommerce_before_cart` — one safe notice when any line is stale/invalid |
| Cart display hardening | Normalized summary + escaped output in `display_cart_item_data` |
| System Status | Session restore, revalidator, revalidation mode, order persistence |
| Diagnostics | Revalidator missing warning |

## What was intentionally not added

- Shipping package splitting, custom shipping methods, rate-card calculation
- Checkout validation, checkout selector, checkout blocking
- Order item meta / order snapshots / shipment creation
- Automatic cart item removal or mutation
- Public REST/Store API, frontend JS/CSS
- Schema migrations or product metadata writes
- Broad cart scans in System Status

## Cart session restore behavior

When both capture flags are enabled, `woocommerce_get_cart_item_from_session`:

1. Reads `cetech_de_delivery_selection`, `_summary`, `_hash` from session values
2. Normalizes intent and summary via `CartDeliverySelectionSessionData`
3. Verifies hash with `CartDeliverySelectionFingerprint::fromIntent()`
4. Restores all three keys on success
5. Strips all selection keys on malformed, partial, or hash-mismatch data (no fatal errors)

## Cart revalidation behavior

`CartDeliverySelectionRevalidator::revalidate_cart_item()`:

1. Reads stored intent from cart line
2. Reconstructs `product_id`, `variation_id`, `display_key`
3. Calls `ProductDeliverySelectionValidator`
4. Compares live intent fingerprint to stored intent

### Status values

| Status | Meaning |
|--------|---------|
| `valid` | Selection still matches live rules |
| `stale` | Option changed (e.g. rule linkage) but may still validate |
| `unavailable` | Option no longer available |
| `invalid` | Malformed data or validation failure |
| `missing` | No captured selection on line (skipped for warnings) |

Does not mutate cart, calculate shipping, or touch checkout/orders.

## Cart warning behavior

On cart page only (`woocommerce_before_cart`):

- When any line has actionable status (`stale`, `unavailable`, `invalid`), shows one notice:
  - “A delivery option in your cart is no longer available. Please remove and re-add the product.”
- Does not expose validator error codes
- Does not block checkout
- Does not remove cart items automatically

## Cart display hardening

`woocommerce_get_item_data`:

- Only when capture flags enabled
- Summary re-normalized from session via `CartDeliverySelectionSessionData::normalizeSummary()`
- Malformed summary → no display
- Key/value escaped with `esc_html` / `esc_html__`
- No IDs, hashes, rule IDs, prices, or private data

## Fingerprint consistency

`CartDeliverySelectionFingerprint`:

- SHA-256 over: `product_id`, `variation_id`, `display_key`, `fulfilment_availability`, `fulfilment_choice`, `delivery_offer_id`, `rule_id`
- Excludes `issued_at`
- Nullable fields normalize to empty string
- `display_key` normalized via `ProductDeliveryOptionsBuilder::normalizeDisplayKey()` (preserves colons)
- Hash format unchanged from Phase 2E1

## Feature flag behavior

Unchanged from 2E1:

- Session restore, revalidation, and warnings register only when **both** `enable_cart_delivery_selection_capture` and `enable_product_delivery_selector` are true
- Flags off → no new hooks; cart behavior unchanged from pre-2E1

## No shipping / checkout / order boundary

- No shipping hooks or price calculation
- No checkout hooks or checkout blocking
- No order meta writes
- Order delivery persistence: **Not enabled** in System Status

## Privacy protections

- Cart display: public labels only
- Warnings: generic customer message
- Session data: validated intent (server-side); not re-trusted without hash check
- Diagnostics/System Status: no customer cart inspection

## How to test cart reload / session persistence

1. Enable both capture flags
2. Add simple product with valid delivery selection
3. View cart — delivery summary visible
4. Reload cart page / new browser tab (same session)
5. Confirm delivery summary still visible
6. Inspect cart item data (debug): intent, summary, hash present and hash matches intent

## How to test stale selection warning

1. Add product with delivery selection to cart
2. In admin, deactivate delivery offer or product rule (or change config so selection invalidates)
3. Visit cart page
4. Expect safe notice (not validator error codes)
5. Checkout still accessible (not blocked in 2E2)

## How to confirm checkout / shipping / order unchanged

- Checkout totals and shipping unchanged
- No order meta after placing order
- System Status: shipping **Not enabled**, order persistence **Not enabled**

## Known limitations

- Warnings on cart page only (not mini-cart, not checkout)
- One combined notice per cart page load (not per line)
- Cart items with stale selections are not auto-removed
- Revalidation does not refresh stored intent/summary in cart
- Variable product capture still deferred from 2E1

## Recommended next phase

**Phase 2E3 (or equivalent):** Checkout validation, order line meta snapshot at payment, and/or variable product capture — still without full shipping package calculation unless scoped.

## Commands

```powershell
composer dump-autoload -o
Get-ChildItem -Path src -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## WordPress admin checklist

- [ ] Cart selection persists after page reload
- [ ] Malformed session data stripped safely (no fatal errors)
- [ ] Stale selection shows cart-page warning
- [ ] Warning does not block checkout
- [ ] Cart display shows public labels only
- [ ] System Status shows session restore + revalidator + revalidation mode
- [ ] Configuration Health warns if revalidator missing when capture on
- [ ] Checkout/shipping/order unchanged
