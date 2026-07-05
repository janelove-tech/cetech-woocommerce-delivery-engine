# Phase 2F1 — Checkout Preflight Validation Foundation

**Status:** Complete  
**Plugin version:** 0.1.0 (foundation)  
**Schema target:** `2` (unchanged)  
**Sources:** `docs/PHASE-2E2-IMPLEMENTATION.md`, `docs/ARCHITECTURE-PLAN.md`

## What was added

Phase 2F1 adds **guarded checkout preflight validation** for captured cart delivery selections, controlled by a separate feature flag.

| Area | Files / behaviour |
|------|-------------------|
| Feature flag | `enable_checkout_delivery_selection_validation` (default **false**) |
| Checkout validator | `CheckoutDeliverySelectionValidator` |
| Validation result | `CheckoutDeliveryValidationResult` |
| System Status | Checkout validation flag, registration, mode |
| Diagnostics | Checkout validation misconfiguration warnings |

## What was intentionally not added

- Shipping package splitting, custom shipping methods, rate-card calculation
- Checkout delivery selector or checkout fields
- Order snapshots, order item meta, shipment creation
- Cart mutation at checkout
- Public REST/Store API, frontend JS/CSS
- Schema migrations or product metadata writes

## New checkout validation feature flag

### `enable_checkout_delivery_selection_validation` (default: false)

| Flag state | Behaviour |
|------------|-----------|
| **false** | No checkout validation hook. Checkout unchanged. |
| **true** + cart capture **false** | Validation does not run. Diagnostics warn. |
| **true** + selector + cart capture **true** | Checkout preflight validation active. |

Requires all three flags for runtime activation:

- `enable_product_delivery_selector`
- `enable_cart_delivery_selection_capture`
- `enable_checkout_delivery_selection_validation`

## Checkout hook used

`woocommerce_after_checkout_validation` (priority 10)

Adds errors to the WooCommerce `WP_Error` object without altering totals or shipping.

## Checkout validation behavior

1. Iterate cart lines when all three flags are active.
2. Lines **with** captured selection:
   - Detect hash mismatch → block
   - Revalidate via `CartDeliverySelectionRevalidator`
   - Block on `stale`, `unavailable`, `invalid`, or malformed data
3. Lines **without** captured selection (capture-applicable simple products only):
   - No applicable rules → allow
   - Delivery unavailable → block
   - Selection required → block (missing)
4. Variable / non-capture lines → skip unless captured selection present

Does not mutate cart, calculate shipping, or write order meta.

## Missing-selection policy

| Scenario | Checkout |
|----------|----------|
| Capture-applicable simple product, rules require selection, no capture data | **Block** |
| No applicable rules | Allow |
| Only unavailable options | **Block** |
| Variable product, no capture data | Allow (capture deferred) |
| Captured selection present | Revalidate |

## Customer-safe notices

- Stale/unavailable/invalid/hash mismatch:
  - “A delivery option in your cart is no longer available. Please return to your cart and update the affected product.”
- Missing required selection:
  - “Please select a delivery option for all products in your cart before checking out.”

No internal error codes, rule IDs, offer IDs, hashes, or private data exposed.

## System Status changes

- Checkout delivery validation flag
- Checkout validation registered
- Checkout validation mode: Preflight validation only; no shipping calculation
- Checkout delivery selector: Not enabled
- Order delivery persistence: Not enabled
- Shipping calculation: Not enabled

## Diagnostics changes

When checkout validation flag is true:

- `checkout_validation_enabled_cart_capture_disabled`
- `checkout_validation_enabled_selector_disabled`
- `checkout_validation_enabled_validator_missing`
- `checkout_validation_enabled_revalidator_missing`

Existing cart capture diagnostics preserved.

## No shipping / checkout-fields / order boundary

- No checkout fields or selector
- No shipping hooks or price calculation
- No order meta or order item meta writes
- No shipment records

## How to test with all flags off

1. System Status: checkout validation **No**, not registered
2. Place order with any cart — unchanged WooCommerce behavior

## How to test selector + capture on, checkout validation off

1. Enable selector + cart capture only
2. Valid selection in cart → checkout succeeds even if stale (cart warning only from 2E2)
3. System Status: checkout validation **Not enabled**

## How to test checkout validation on with valid selection

1. Enable all three flags
2. Add simple product with valid delivery selection
3. Checkout completes preflight without delivery errors

## How to test stale/invalid selection blocks checkout

1. Enable all three flags
2. Add product with selection; invalidate config in admin
3. Cart page may show 2E2 warning
4. Attempt checkout → blocked with safe message

## How to confirm totals/shipping/order meta unchanged

- No shipping price logic added
- Order meta after checkout unchanged by this plugin
- System Status: shipping and order persistence **Not enabled**

## Known limitations

- Classic checkout hook only (no Blocks)
- Does not auto-fix or remove stale cart lines
- Variable products not blocked for missing capture (by design)
- One customer-facing checkout error message per failed submission
- Cart page warnings (2E2) remain non-blocking

## Recommended next phase

**Phase 2F2 (or equivalent):** Order line meta snapshot at payment, checkout field integration if needed, variable product capture — still without full shipping package calculation unless scoped.

## Commands

```powershell
composer dump-autoload -o
Get-ChildItem -Path src,database -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
php -l cetech-woocommerce-delivery-engine.php
php -l uninstall.php
```

## WordPress admin checklist

- [ ] All flags off → checkout unchanged
- [ ] Selector + capture on, checkout validation off → checkout not blocked by 2F1
- [ ] All flags on + valid selection → checkout succeeds
- [ ] Stale selection → checkout blocked with safe message
- [ ] Missing required selection → checkout blocked
- [ ] System Status shows checkout validation rows
- [ ] Diagnostics warn when checkout flag on without cart capture
- [ ] Totals/shipping/order meta unchanged
