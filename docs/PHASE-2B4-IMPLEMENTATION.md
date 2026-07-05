# Phase 2B4 — Rate Cards CRUD + Admin Test Rate Tool

**Status:** Complete  
**Plugin version:** 0.1.0 (foundation)  
**Schema target:** `1` (unchanged)  
**Sources:** `docs/PHASE-2B3-IMPLEMENTATION.md`, `docs/ARCHITECTURE-PLAN.md`

## What was added

Phase 2B4 delivers **admin-only CRUD** for rate cards and a **read-only admin test rate tool**.

| Area | Files / behaviour |
|------|-------------------|
| Validation | `RateCardValidator` (save + test input) |
| Admin page | `RateCardsPage` (list, add/edit, deactivate, test tool) |
| Application service | `AdminRateCardTester` (matching + V1 amount preview) |
| Repository | Real `save()`, `count_all()` on `WpdbRateCardRepository` |
| Menu | Rate Cards submenu |
| System Status | Rate card count + zero-record warning |
| Privacy | Repository docblocks, admin-only boundaries |

## What was intentionally not added

- Product rules, product delivery selector, product edit panels
- Cart-line delivery selections, cart fingerprinting
- WooCommerce shipping packages or custom shipping method registration/calculation
- Checkout validation, order snapshots, shipment creation/management
- Customer-facing delivery prices or storefront output
- `rate_card_rules` admin UI (deferred; see below)
- Advanced charge types beyond `fixed_per_shipment` and `fixed_per_item`
- Weight tiers, surcharges, free-shipping thresholds (schema columns exist but are not edited in 2B4)
- Demo/sample data auto-creation
- Frontend JS/CSS assets
- Schema version change or new migrations

## Admin page created

| Menu | Slug | Capability |
|------|------|------------|
| Rate Cards | `cetech-delivery-engine-rate-cards` | `manage_delivery_rate_cards` |

## Capability used

| Capability | Used for |
|------------|----------|
| `manage_delivery_rate_cards` | Rate card CRUD, test tool, page access, POST actions |

## Rate card fields supported

### Actual schema (`delivery_engine_rate_cards`)

| Form field | Database column | Notes |
|------------|-----------------|-------|
| code | `internal_code` | Required stable lowercase code |
| delivery_offer_id | `delivery_offer_id` | Required FK |
| destination_zone_id | `destination_zone_id` | Required FK |
| logistics_profile_id | `logistics_profile_id` | Optional FK (NULL = wildcard) |
| supplier_id | `supplier_id` | Optional FK (NULL = wildcard) |
| origin_id | `origin_id` | Optional FK (NULL = wildcard) |
| charge_type | `charge_type` | `RateCardChargeType` enum |
| base_amount | `base_amount` | Non-negative decimal |
| currency_code | `base_currency` | Uppercase 3-letter ISO |
| priority | `priority` | Integer; lower wins after specificity |
| effective_from | `effective_from` | Optional UTC datetime |
| effective_to | `effective_to` | Optional UTC datetime |
| status | `status` | `RecordStatus` enum |

### Prompt/schema mismatches

| Prompt field | Status |
|--------------|--------|
| `internal_name` | **Not in schema.** List column shows `—`. Use `code` as the stable identifier. |
| `currency_code` | Mapped to `base_currency` in persistence. |
| `internal_notes` | **Not in schema.** Omitted from forms. |
| Weight/surcharge/threshold columns | Exist in schema but not exposed in 2B4 forms (future pricing phases). |

### `rate_card_rules` table

Table exists (`rate_card_id`, `rule_key`, `rule_value`) for future complex pricing.

**Phase 2B4 defers** admin rule-row editing. V1 pricing uses `charge_type` + `base_amount` on `rate_cards` only.

## Validation rules

- Code: required, lowercase, `[a-z0-9_-]+`, unique
- Delivery offer / destination zone: required, must exist
- Logistics profile / supplier / origin: optional; if set, must exist
- Origin + supplier: if both set, origin must belong to supplier
- Charge type: `fixed_per_shipment` or `fixed_per_item` only
- Base amount: numeric, non-negative
- Currency: required 3-letter ISO
- Priority: required integer
- Status: `RecordStatus` enum
- Effective dates: valid if supplied; `effective_from` must not be after `effective_to`
- Duplicate code rejected on save
- Deactivate sets `status = inactive` (soft delete); already-inactive is no-op success

Test tool validation additionally requires positive integer quantity and valid FK references.

## Admin test rate tool behaviour

- **Location:** bottom of Rate Cards list page
- **Method:** POST with nonce (`cetech_de_test_rate_card`)
- **Capability:** `manage_delivery_rate_cards`
- **Read-only:** no DB writes, no cart/checkout/session/order access
- **No match:** returns explanation text only — **never** zero price as fallback

### Inputs

- `delivery_offer_id` (required)
- `destination_zone_id` (required)
- `logistics_profile_id` (optional)
- `supplier_id` (optional)
- `origin_id` (optional)
- `quantity` (positive integer)
- `currency_code` (required)

### Outputs

- Matched rate card code (or “No matching active rate card”)
- Charge type, calculated amount, currency
- Explanation of specificity, priority, and candidate count

### V1 calculation

| Charge type | Formula |
|-------------|---------|
| `fixed_per_shipment` | `base_amount` once |
| `fixed_per_item` | `base_amount × quantity` |

## Rate matching rules (`AdminRateCardTester`)

1. Only **active** rate cards
2. Exact match on `delivery_offer_id` and `destination_zone_id`
3. Exact match on `base_currency` vs test `currency_code`
4. Optional dimensions: NULL on card = wildcard; when test supplies a value, card must be NULL or equal
5. Effective window: current UTC time must fall within `effective_from` / `effective_to` when set
6. Sort candidates by:
   - Specificity score (count of exact optional-dimension matches when test supplied values) **desc**
   - `priority` **asc** (lower number wins)
   - `id` **asc** (tie-break)

## Audit logging behaviour

| Action | Entity type | Notes |
|--------|-------------|-------|
| created | `rate_card` | Via `ConfigurationAuditLogger` |
| updated | `rate_card` | Previous/new row snapshots |
| deactivated | `rate_card` | Status change to inactive |

- Audit failure logs warning; CRUD still succeeds (Patch 1 pattern)
- No customer/order/payment data in payloads
- `internal_notes` redaction applies if ever added to schema later

## Privacy / customer boundary rules

- `WpdbRateCardRepository` documented as admin/infrastructure only
- No frontend hooks read rate card repositories
- No public REST/Store API exposure
- No customer emails, My Account, product, cart, or checkout output
- Test tool is wp-admin configuration preview only

## How to test CRUD

1. Run `composer dump-autoload -o`
2. Ensure schema v1 tables exist (plugin activation / migration)
3. Re-sync capabilities on System Status if needed
4. As admin with `manage_delivery_rate_cards`:
   - **Delivery Engine → Rate Cards → Add New**
   - Create card with offer + zone + charge type + amount + currency
   - Confirm list row appears
   - Edit and save; confirm updated_at changes
   - Try duplicate code → error + form draft preserved
   - Deactivate → status inactive; repeat → “already inactive” success

## How to test rate-card matching

1. Create two active cards for same offer + zone + currency:
   - Card A: wildcard optional FKs, priority 100
   - Card B: specific logistics profile, priority 200
2. Run test tool with matching offer/zone/currency and the specific logistics profile
3. Expect Card B (higher specificity) even though priority is higher number
4. Run with no optional filters → both match; lower priority wins
5. Run with wrong currency → “No matching active rate card”
6. Test `fixed_per_item` with quantity 3 → amount = base × 3

## How to test permissions

- User without `manage_delivery_rate_cards`: Rate Cards menu hidden; direct URL → permission denied
- POST without nonce → redirect/error (Patch 1 handler)
- Test tool requires same capability + nonce

## How to confirm storefront remains unchanged

- No new WooCommerce shipping methods registered
- Cart/checkout pages show no delivery-engine rate output
- No new frontend assets enqueued
- Grep codebase: no cart/checkout hooks referencing `RateCardRepository`

## Risks / TODOs for Phase 2B5 (Product Rules)

- Wire product rules to offers/zones/origins
- Connect rate matching to real checkout context (cart qty, packages)
- Implement `rate_card_rules` UI and advanced pricing columns
- Consider adding `internal_name` column if admin UX requires it
- Replace admin test matcher with production rate engine sharing same core logic
- Currency/store base currency normalization
- Inactive FK entities should optionally block new rate card saves

## Commands

```bash
composer dump-autoload -o
```

```bash
# From plugin root — syntax check all PHP files (bash/Git Bash)
find src -name '*.php' -print0 | xargs -0 php -l
```

On Windows PowerShell (if PHP is in PATH):

```powershell
Get-ChildItem -Path src -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## WordPress admin checklist

- [ ] Rate Cards submenu visible with correct capability
- [ ] Create / edit / deactivate rate card
- [ ] Duplicate code rejected
- [ ] FK validation (missing offer/zone/origin-supplier mismatch)
- [ ] Test tool returns match or explicit no-match (never free shipping fallback)
- [ ] System Status shows rate card count + zero warning
- [ ] Audit entries on create/update/deactivate (or warning if audit fails)
- [ ] Storefront/cart/checkout unchanged
