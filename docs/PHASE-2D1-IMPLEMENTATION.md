# Phase 2D1 — Product Delivery Selector Foundation

**Status:** Complete  
**Plugin version:** 0.1.0 (foundation)  
**Schema target:** `2` (unchanged)  
**Sources:** `docs/PHASE-2C3-IMPLEMENTATION.md`, `docs/ARCHITECTURE-PLAN.md`

## What was added

Phase 2D1 introduces a **feature-flagged, display-only** product-page delivery selector that consumes the hardened `ProductDeliveryRuleResolver`. No cart, checkout, shipping, or order behaviour was added.

| Area | Files / behaviour |
|------|-------------------|
| Frontend renderer | `ProductDeliverySelectorRenderer` |
| Feature flag | `enable_product_delivery_selector` (default **false**, unchanged) |
| Product-page hook | `woocommerce_single_product_summary` priority 25 |
| Plugin wiring | `Plugin::boot()` registers renderer after WooCommerce check |
| System Status | Runtime readiness shows selector flag and renderer state |
| Diagnostics | Selector-specific warnings when flag is enabled |
| Admin preview note | Product Rules test section links to flag + product page preview |

## What was intentionally not added

- Cart-line delivery selections or fingerprinting
- Hidden add-to-cart fields or cart item data changes
- WooCommerce shipping packages or custom shipping methods
- Checkout validation or order snapshots
- Shipment creation/management, tracking UI, customer timeline
- Public REST/Store API, frontend JS/CSS
- Variation JavaScript switching
- Product edit metaboxes or product metadata writes
- Schema changes or migrations

## Feature flag behavior

| State | Behaviour |
|-------|-----------|
| `enable_product_delivery_selector = false` (default) | No hook registered; no customer-facing output |
| Flag enabled | Hook registered on product pages only; display-only selector renders |

Flag stored in `wp_options` as `cetech_de_enable_product_delivery_selector`. Default remains `0` (false) in `FeatureFlags::DEFAULTS`.

## Product-page hook

**Hook:** `woocommerce_single_product_summary` at priority **25**  
**Conditions:** WooCommerce active, flag enabled, valid single product context

**Product types:**

| Type | Behaviour |
|------|-----------|
| Simple | Resolve rules for product ID |
| Variation (direct) | Resolve rules for variation ID |
| Variable (parent) | Show notice: “Delivery options may update after selecting a product option.” |
| Other | No output |

## Selector rendering behavior

1. Resolve rules via `ProductDeliveryRuleResolver`.
2. For each chosen rule (grouped by `fulfilment_availability`):
   - Show availability label and fulfilment choice label.
   - **Delivery choice:** load active delivery offers; show public label, public description, estimate text.
   - **Store pickup choice:** show “Store pickup available”.
   - **No active offers for delivery:** show safe unavailable message.
3. If no rules match: show “Delivery options are not available for this product.”
4. If resolver fails: render nothing (no admin errors exposed).

Display-only — no form inputs, hidden fields, or selection persistence.

## Customer-safe fields rendered

- Fulfilment availability label (translated enum)
- Fulfilment choice label (translated enum)
- Delivery offer `public_label`
- Delivery offer `public_description`
- Estimate text from `default_processing_*`, `default_transit_*`, `default_final_mile_*`, and `duration_unit`
- “Store pickup available” for store pickup choice

## Forbidden / private fields (never rendered)

- Supplier/origin IDs, codes, names
- Logistics profile IDs/codes
- Rate card data and amounts
- `internal_notes`, internal codes/names
- Shipping prices, checkout charges
- Admin diagnostics or audit data

## Variation limitation

Variable products do **not** resolve per-variation rules in this phase. No frontend JS is enqueued; variation forms are unchanged. A static notice explains that delivery options may update after option selection (future phase).

## No cart / checkout / shipping boundary

The selector:

- Does not submit data to cart
- Does not alter add-to-cart forms
- Does not hook cart, checkout, or shipping
- Does not calculate rates or prices
- Does not write order/product meta

## Diagnostics added (admin-only, when flag enabled)

| Code | Severity | Check |
|------|----------|-------|
| `selector_enabled_no_active_product_rules` | warning | Flag on, zero active product rules |
| `selector_enabled_no_active_delivery_offers` | warning | Flag on, zero active delivery offers |
| `selector_enabled_delivery_rule_without_offers` | warning | Flag on, active delivery rule with no offers |
| `selector_enabled_inactive_delivery_offer` | warning | Flag on, active rule references inactive offer |

Existing product-rule diagnostics remain unchanged.

## How to test with flag off

1. Confirm default: `get_option('cetech_de_enable_product_delivery_selector')` is `0` or unset.
2. Visit any product page — no delivery selector block appears.
3. System Status → Selector storefront output: “Disabled (flag off by default)”.

## How to test with flag on

1. Enable flag (wp-cli or options table):
   ```sql
   UPDATE wp_options SET option_value = '1' WHERE option_name = 'cetech_de_enable_product_delivery_selector';
   ```
   Or: `update_option('cetech_de_enable_product_delivery_selector', 1);`
2. Create active product rules and delivery offers with public labels.
3. Visit a **simple** product page — selector shows delivery options.
4. Visit a **variable** product — notice about selecting options appears.
5. Product with no matching rules — unavailable message.
6. Store pickup rule — “Store pickup available” shown.
7. Delivery rule with inactive/missing offers — safe unavailable message.
8. Configuration Health — selector-specific warnings when misconfigured.

## How to confirm cart / checkout unchanged

- Add to cart — no hidden fields or delivery data in cart
- Cart page — no new delivery UI
- Checkout — no validation or shipping changes
- No new JS/CSS assets loaded

## Known limitations

- Variable products cannot resolve variation-specific rules without JS (deferred)
- Multiple availabilities may all display (one section per fulfilment availability)
- Estimate text is informational only; not a live quote or price
- Flag must be enabled manually; no admin UI toggle in this phase
- Selector diagnostics may overlap with existing product-rule warnings when flag is on

## Recommended next phase

**Phase 2D2 (or equivalent):** Variation-aware selector updates (still display-only or begin cart-line persistence) — connect selected delivery offer to cart item data without full checkout/shipping integration.

## Commands

```powershell
composer dump-autoload -o
Get-ChildItem -Path src -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## WordPress admin checklist

- [ ] Flag off → no product-page output
- [ ] Flag on → simple product shows selector
- [ ] Flag on → variable product shows variation notice only
- [ ] Public labels/descriptions visible; no internal codes or supplier data
- [ ] Store pickup and delivery unavailable states render safely
- [ ] Cart/checkout unchanged
- [ ] System Status shows selector flag state
- [ ] Configuration Health shows selector warnings when flag on and misconfigured
- [ ] No frontend JS/CSS enqueued
