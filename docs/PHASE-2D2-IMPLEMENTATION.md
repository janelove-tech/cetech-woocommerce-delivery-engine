# Phase 2D2 — Product Delivery Selector Hardening + Option Contract

**Status:** Complete  
**Plugin version:** 0.1.0 (foundation)  
**Schema target:** `2` (unchanged)  
**Sources:** `docs/PHASE-2D1-IMPLEMENTATION.md`, `docs/ARCHITECTURE-PLAN.md`

## What was added

Phase 2D2 hardens the product-page delivery selector with a **customer-safe option contract** and refactors rendering to use a dedicated builder. The selector remains **display-only** and feature-flagged (off by default).

| Area | Files / behaviour |
|------|-------------------|
| Option contract | `ProductDeliveryOption` with `CONTRACT_VERSION = '1'` |
| Options builder | `ProductDeliveryOptionsBuilder` |
| Renderer refactor | `ProductDeliverySelectorRenderer` uses builder |
| System Status | Option contract version + persistence note |
| Diagnostics | Builder missing + all delivery rules unavailable checks |

## What was intentionally not added

- Cart-line delivery selection persistence
- Hidden add-to-cart fields or validation
- Cart item fingerprinting or cart item data changes
- WooCommerce shipping packages or custom shipping methods
- Checkout validation, order snapshots, shipments
- Public REST/Store API, frontend JS/CSS
- Variation JavaScript switching
- Product metadata writes or schema changes

## Option contract fields

`ProductDeliveryOption::CONTRACT_VERSION = '1'`

| Field | Purpose |
|-------|---------|
| `display_key` | Stable customer-safe key for display grouping |
| `fulfilment_availability` | Enum slug (internal to contract; not rendered raw) |
| `fulfilment_availability_label` | Translated customer label |
| `fulfilment_choice` | Enum slug |
| `fulfilment_choice_label` | Translated customer label |
| `delivery_offer_id` | Active offer ID when applicable |
| `delivery_offer_public_label` | Customer-facing offer name |
| `delivery_offer_public_description` | Customer-facing description |
| `estimate_text` | Duration estimate from offer defaults |
| `is_available` | Whether option is displayable as available |
| `unavailable_reason` | Safe reason when `is_available` is false |
| `contract_version` | Contract identifier for future cart phases |

`toArray()` / `fromArray()` support future cart consumption without storing private fields.

## Customer-safe fields

Rendered on product pages (when flag enabled):

- Fulfilment availability label
- Fulfilment choice label
- Delivery offer public label / description
- Estimate text (duration only)
- Store pickup availability message
- Unavailable reason (generic, no internal codes)

## Forbidden / private fields

Never included in contract or rendered output:

- Supplier/origin/logistics profile IDs, codes, names
- Rate card IDs, codes, amounts
- `internal_notes`, internal costs
- Shipping prices, checkout charges, “free shipping”
- Customer/order/payment/session data
- Admin diagnostics

## Feature flag behavior

Unchanged from Phase 2D1:

| State | Behaviour |
|-------|-----------|
| `enable_product_delivery_selector = false` (default) | No hook; no customer output |
| Flag enabled | Product-page display-only selector |

## Renderer hardening

- Uses `ProductDeliveryOptionsBuilder` instead of inline offer logic
- Unknown fulfilment availability/choice enums are **skipped** (no raw slug fallback)
- Unavailable delivery options show `--unavailable` CSS class + `unavailable_reason`
- Variable parent products: static variation notice (unchanged)
- Unsupported types (grouped, external, etc.): silent return — no selector
- Resolver failure: silent return — no admin errors on storefront

## Unsupported product type behavior

| Type | Behaviour |
|------|-----------|
| Simple | Resolve + render options |
| Variation (direct) | Resolve + render options |
| Variable (parent) | Variation notice only |
| Grouped, external, other | No output |

## Variable product limitation

No JavaScript variation switching. Variable products show a static notice until a future phase adds variation-aware resolution.

## No cart / checkout / shipping boundary

Verified preserved:

- No form inputs or hidden fields
- No add-to-cart modifications
- No cart/checkout/shipping hooks
- No cart/session/order/product meta writes
- No price calculation

## Diagnostics / System Status updates

**Diagnostics (when flag enabled):**

| Code | Check |
|------|-------|
| `selector_enabled_options_builder_missing` | Builder class not available |
| `selector_enabled_all_delivery_rules_unavailable` | All active delivery rules lack resolvable active offers |
| (existing 2D1 checks unchanged) | No rules, no offers, per-rule issues |

**System Status runtime readiness:**

- Selector option contract version: `1`
- Selector options builder registered
- Selector option persistence: “Display-only; not persisted to cart”

## How to test flag off

1. Default flag off → no product-page selector
2. System Status → “Disabled (flag off by default)”

## How to test flag on

1. Enable `cetech_de_enable_product_delivery_selector`
2. Simple product with active rules + offers → grouped options with public labels
3. Store pickup rule → “Store pickup available”
4. Delivery rule with inactive/missing offers → unavailable message + reason
5. Variable product → variation notice only
6. Grouped/external product → no selector
7. Configuration Health → new selector warnings when misconfigured

## How to confirm no hidden inputs / cart / checkout changes

- View page source on product page — no `<input>` in selector block
- Add to cart — cart unchanged
- Checkout — unchanged
- No new JS/CSS assets

## Known limitations

- Options are display-only; not persisted for cart
- Variable products cannot show variation-specific options
- Multiple options per availability/choice render as list items
- Flag enable remains manual (no admin UI toggle)
- Selector diagnostics may overlap existing product-rule warnings

## Recommended next phase

**Phase 2D3 (or equivalent):** Variation-aware selector resolution and/or begin cart-line option persistence using `ProductDeliveryOption` contract — still without full checkout/shipping integration.

## Commands

```powershell
composer dump-autoload -o
Get-ChildItem -Path src -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## WordPress admin checklist

- [ ] Flag off → no product-page output
- [ ] Flag on → options render via builder contract
- [ ] Unknown enums skipped; no raw slug output
- [ ] Unavailable delivery shows reason without internal data
- [ ] Variable product → notice only
- [ ] Grouped/external → no output
- [ ] No form inputs in selector HTML
- [ ] Cart/checkout unchanged
- [ ] System Status shows contract version + persistence note
- [ ] Configuration Health shows new selector warnings when applicable
