# Phase 2E1 — Validated Add-to-Cart Selection Capture Foundation

**Status:** Complete  
**Plugin version:** 0.1.0 (foundation)  
**Schema target:** `2` (unchanged)  
**Sources:** `docs/PHASE-2D3-IMPLEMENTATION.md`, `docs/ARCHITECTURE-PLAN.md`

## What was added

Phase 2E1 introduces a **guarded, validated add-to-cart selection capture** path controlled by a separate feature flag.

| Area | Files / behaviour |
|------|-------------------|
| Feature flag | `enable_cart_delivery_selection_capture` (default **false**) |
| Cart capture service | `CartDeliverySelectionCapture` |
| Product-page submission | Radio inputs inside add-to-cart form (simple products only) when both flags on |
| Cart item data | Validated `ProductDeliverySelectionIntent` + public summary + selection hash |
| Cart display | Customer-safe delivery summary on cart line items |
| System Status | Cart capture flag, capture registration, persistence status |
| Diagnostics | Cart capture misconfiguration warnings |

## What was intentionally not added

- WooCommerce shipping package splitting or custom shipping methods
- Rate-card price calculation in cart or checkout
- Checkout validation or checkout delivery selector
- Order delivery snapshots or shipment creation
- Public REST/Store API, frontend JS/CSS
- Product metadata writes or schema migrations
- Variable product form integration (variation add-to-cart unchanged)

## New feature flag behaviour

### `enable_cart_delivery_selection_capture` (default: false)

| Flag state | Behaviour |
|------------|-----------|
| **false** | No add-to-cart hooks register. No cart item data changes. No radio/hidden submission fields. Cart and checkout unchanged. |
| **true** + selector **false** | Capture hooks do not register (capture requires both flags). Diagnostics warn. |
| **true** + selector **true** | Add-to-cart validation and cart item data capture active for applicable simple products. |

The existing `enable_product_delivery_selector` flag still controls whether any product-page selector output appears.

## Product-page submission behaviour

| Selector | Capture | Product page |
|----------|---------|--------------|
| off | off | No selector output |
| on | off | Display-only selector at `woocommerce_single_product_summary` priority 25 (Phase 2D2 behaviour) |
| on | on | Radio inputs at `woocommerce_before_add_to_cart_button` (inside add-to-cart form) for **simple products only** |

### Submission field contract

- Input name: `cetech_de_delivery_option_key`
- Input type: radio
- Value: `display_key` only (e.g. `in_store:delivery:12`)
- No prices, rule IDs, offer IDs, intent JSON, or private data in the DOM
- No JavaScript enqueued
- Variable products: notice only; no form inputs (deferred to a later phase)

## Add-to-cart capture behaviour

`CartDeliverySelectionCapture` registers hooks only when both flags are enabled:

1. `woocommerce_add_to_cart_validation` — reject invalid/missing selections
2. `woocommerce_add_cart_item_data` — attach validated intent + summary + hash
3. `woocommerce_get_item_data` — show customer-safe cart line summary

## Validation flow

1. Assess product: no rules → allow add-to-cart unchanged
2. Only unavailable options → block with “Delivery is currently unavailable…”
3. Available options exist → require submitted `display_key`
4. Call `ProductDeliverySelectionValidator::validate()`
5. On success → store intent in cart item data
6. On failure → safe WooCommerce error notice (no internal diagnostic text)

### Customer-facing notices

- “Please select a delivery option for this product.”
- “The selected delivery option is no longer available. Please choose another option.”
- “Delivery is currently unavailable for this product.”

## Cart item data stored

| Key | Contents |
|-----|----------|
| `cetech_de_delivery_selection` | `ProductDeliverySelectionIntent::toArray()` |
| `cetech_de_delivery_selection_summary` | Public labels only (availability, choice, offer label, estimate) |
| `cetech_de_delivery_selection_hash` | SHA-256 of safe intent fingerprint fields |

### Forbidden in cart item data

- Prices, rate card IDs/codes/amounts
- Supplier/origin/logistics profile details
- Internal notes
- Customer/order/payment/session data beyond WooCommerce cart context

## Cart item uniqueness / fingerprint

`cetech_de_delivery_selection_hash` is included in cart item data so WooCommerce generates distinct cart line keys for different delivery selections on the same product.

Hash inputs (deterministic, no random values):

- `product_id`, `variation_id`, `display_key`
- `fulfilment_availability`, `fulfilment_choice`
- `delivery_offer_id`, `rule_id` (when present)

Does not calculate shipping.

## Cart display behaviour

When capture is active, cart line items show a **Delivery** meta row with:

- Fulfilment availability label + choice label
- Delivery offer public label (if present)
- Estimate text (if present)

Does not display IDs, hashes, rule IDs, prices, or private data.

## No shipping / checkout / order boundary

- No shipping package hooks
- No custom shipping method registration
- No checkout hooks or checkout validation
- No order meta writes
- No shipment records
- Shipping calculation remains **Not enabled** in System Status

## Privacy protections

- DOM submits only `display_key`
- Cart stores validated intent server-side (not re-trusted from client)
- Cart display uses public labels only
- No public REST/Store API
- No frontend JS/CSS
- Feature flags off → zero customer-facing capture behaviour

## How to test with both flags off

1. Confirm System Status: cart capture flag **No**, add-to-cart capture **No**, persistence **Not enabled**
2. Product page: no selector, no radio inputs
3. Add to cart: unchanged WooCommerce behaviour
4. Cart: no delivery meta rows from this plugin

## How to test selector on but capture off

1. Enable `enable_product_delivery_selector` only
2. Product page: display-only delivery options (no radios)
3. Add to cart: succeeds without selection
4. Cart: no delivery selection data from plugin
5. System Status: persistence **Not enabled**

## How to test both flags on

1. Enable `enable_product_delivery_selector` and `enable_cart_delivery_selection_capture`
2. Configure active product rules + delivery offers for a **simple** product
3. Product page: radio inputs inside add-to-cart form
4. Add without selection → error notice
5. Select valid option → add succeeds
6. Cart: delivery summary visible; intent stored in cart item data (inspect via debugging, not shown to customer)
7. Same product + different selection → separate cart lines (hash prevents merge)

## How to test invalid / stale display_key

1. Enable both flags
2. Submit add-to-cart with tampered POST `cetech_de_delivery_option_key` (invalid or stale key)
3. Expect: “The selected delivery option is no longer available…”
4. Product not in scope (no rules) → add-to-cart still allowed

## How to confirm shipping / checkout / order remain unchanged

- Checkout totals unchanged (no shipping price logic added)
- No order meta from this phase after placing order (cart data may persist in session until order — not copied to order in 2E1)
- System Status: shipping calculation **Not enabled**
- No new checkout fields

## Known limitations

- **Simple products only** for interactive capture; variable products show notice only
- Capture requires **both** feature flags enabled
- Validator still requires `enable_product_delivery_selector` on (matches storefront gate)
- Selection not copied to order meta in this phase
- No checkout re-validation of cart selections
- Themes that relocate add-to-cart form may affect radio placement (hook is inside standard WooCommerce simple template)
- No WooCommerce Blocks support

## Recommended next phase

**Phase 2E2 (or equivalent):** Variable product variation-form integration, checkout validation, and/or order snapshot at payment — still without full shipping package calculation unless explicitly scoped.

## Commands

```powershell
composer dump-autoload -o
Get-ChildItem -Path src -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## WordPress admin checklist

- [ ] Both flags off → no capture hooks, cart unchanged
- [ ] Selector on, capture off → display-only, no cart data
- [ ] Both flags on → radios on simple product, validation enforced
- [ ] Invalid display_key → safe error notice
- [ ] Cart shows public delivery summary only
- [ ] Different selections → separate cart lines
- [ ] System Status shows capture flag, persistence, shipping not enabled
- [ ] Configuration Health warns if capture on without selector
- [ ] Checkout/order/shipping unchanged
