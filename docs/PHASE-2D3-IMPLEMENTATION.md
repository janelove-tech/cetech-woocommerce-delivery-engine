# Phase 2D3 — Selector Runtime Handoff Readiness

**Status:** Complete  
**Plugin version:** 0.1.0 (foundation)  
**Schema target:** `2` (unchanged)  
**Sources:** `docs/PHASE-2D2-IMPLEMENTATION.md`, `docs/ARCHITECTURE-PLAN.md`

## What was added

Phase 2D3 introduces a **server-side selection handoff contract** and **validation service** so a future cart phase can verify display keys without persisting anything yet.

| Area | Files / behaviour |
|------|-------------------|
| Selection intent | `ProductDeliverySelectionIntent` (`CONTRACT_VERSION = '1'`) |
| Validation result | `ProductDeliverySelectionValidationResult` |
| Validator | `ProductDeliverySelectionValidator` |
| Display key helpers | `ProductDeliveryOptionsBuilder::formatDisplayKey()` / `normalizeDisplayKey()` |
| Admin test tool | Product Rules → **Test delivery selection validation** |
| System Status | Selection validator, intent contract, persistence status |
| Diagnostics | Selection validator missing, contract version mismatch |

## What was intentionally not added

- Hidden add-to-cart fields or customer selection submission
- Cart-line persistence, cart item data, session writes
- Add-to-cart / checkout hooks or validation
- Shipping packages, rate calculation, order snapshots
- Public REST/Store API, frontend JS/CSS
- Product metadata writes

## Runtime handoff contract fields

`ProductDeliverySelectionIntent`:

| Field | Purpose |
|-------|---------|
| `contract_version` | Intent contract identifier (`1`) |
| `product_id` | WooCommerce product context |
| `variation_id` | Optional variation context |
| `target_type` / `target_id` | Resolver target used |
| `display_key` | Validated option key |
| `fulfilment_availability` / `fulfilment_choice` | Enum slugs (server-side only) |
| `delivery_offer_id` | Active offer when applicable |
| `rule_id` | Resolved product rule ID for server validation |
| `issued_at` | ISO 8601 UTC timestamp |

Not rendered as hidden inputs. Not stored in cart/session/order in this phase.

## Selection validator behavior

`ProductDeliverySelectionValidator::validate( int $product_id, ?int $variation_id, string $display_key )`

1. Normalizes display key via `ProductDeliveryOptionsBuilder::normalizeDisplayKey()`.
2. Requires WooCommerce and `enable_product_delivery_selector` flag enabled.
3. Verifies intent/option contract compatibility.
4. Resolves product context (simple, variation, or variable+variation ID).
5. Re-resolves rules via `ProductDeliveryRuleResolver`.
6. Rebuilds options via `ProductDeliveryOptionsBuilder`.
7. Matches `display_key`; rejects unavailable options.
8. Builds `ProductDeliverySelectionIntent` on success.

### Validation error codes

| Code | Meaning |
|------|---------|
| `invalid_display_key` | Missing or malformed key |
| `invalid_product_id` | Product ID not positive |
| `woocommerce_unavailable` | WooCommerce not active |
| `selector_disabled` | Feature flag off |
| `incompatible_contract` | Intent/option contract mismatch |
| `product_not_found` | Product/variation missing |
| `invalid_product_context` | Variable without variation, unsupported type |
| `resolver_failed` | Resolver error |
| `resolver_no_match` | No chosen rules |
| `option_not_found` | Display key not in rebuilt options |
| `option_unavailable` | Matched option not available |

## Validation result fields

- `valid` (bool)
- `error_code` / `error_message`
- `matched_option` (safe option array)
- `intent` (safe intent array when valid)
- `warnings` (list)

## Admin test tool

**Location:** Delivery Engine → Product Rules → **Test delivery selection validation**

**Inputs:** `product_id`, optional `variation_id`, `display_key`  
**Action:** `cetech_de_test_selection_validation`  
**Capability:** `manage_product_delivery_rules`  
**Security:** Nonce + capability

Stores plain-array result in form draft transient (one minute). Shows valid/invalid, error code, matched option safe fields, and intent handoff fields. Does not write configuration, cart, session, order, or product metadata.

## ProductDeliveryOption display_key stability

Format: `{availability}:{choice}:{suffix}` (three segments, each `sanitize_key`-normalized)

| Suffix | Meaning |
|--------|---------|
| Numeric offer ID | Active delivery offer |
| `pickup` | Store pickup option |
| `unavailable` | Delivery unavailable placeholder |

Deterministic, no private data, no prices. Changing format requires bumping option and/or intent contract version.

## Feature flag behavior

Unchanged: `enable_product_delivery_selector` default **false**. Validator rejects with `selector_disabled` when flag is off. Product-page renderer unchanged (display-only when flag on).

## No cart / checkout / shipping boundary

- No form/hidden inputs on product pages
- No add-to-cart hooks or cart item filters
- No checkout/shipping/order writes
- Selection intent exists only in validator responses and admin test transients

## Privacy protections

- No supplier/origin/logistics/rate-card/internal notes in intent or validation output
- Admin test shows only safe option/intent fields
- No customer/order/payment/session data

## How to test validator manually

1. Enable `enable_product_delivery_selector`.
2. Configure active product rules and delivery offers.
3. Run **Test product rule resolution** to identify targets and options.
4. Derive display key (e.g. `in_store:delivery:12` or `in_store:store_pickup:pickup`).
5. Run **Test delivery selection validation** with product ID, optional variation ID, and display key.
6. Confirm valid result includes matched option and intent array.
7. Test invalid key → `option_not_found`.
8. Test with flag off → `selector_disabled`.

## How to confirm no hidden inputs / cart changes

- Product page source: no hidden fields, no data attributes added in 2D3
- Add to cart / cart / checkout unchanged
- No new frontend assets

## Known limitations

- Validator requires feature flag on (matches future storefront gate)
- Variable products require variation ID in admin test
- Display keys not shown on product page HTML (admin/resolution test only)
- Intent not persisted; cart phase must re-validate at add-to-cart
- No customer-facing validation UI

## Recommended next phase

**Phase 2E (or equivalent):** Wire selection validation into add-to-cart (hidden field or request payload) and cart item data — still without full checkout/shipping integration.

## Commands

```powershell
composer dump-autoload -o
Get-ChildItem -Path src -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## WordPress admin checklist

- [ ] Selection validation test with valid display key → valid + intent
- [ ] Invalid display key → `option_not_found`
- [ ] Flag off → `selector_disabled`
- [ ] Variable product without variation ID → `invalid_product_context`
- [ ] System Status shows selection validator + intent contract v1
- [ ] Configuration Health warns if validator missing when flag on
- [ ] Product page still display-only; no hidden inputs
- [ ] Cart/checkout unchanged
