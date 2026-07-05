# Phase 2C1 — Product Delivery Rules Schema + Admin CRUD Foundation

**Status:** Complete  
**Plugin version:** 0.1.0 (foundation)  
**Schema target:** `2` (was `1`)  
**Sources:** `docs/ARCHITECTURE-PLAN.md`, `docs/PHASE-2B5-IMPLEMENTATION.md`

## What was added

Phase 2C1 introduces **admin-only Product Delivery Rules** — database schema, repository, validation, diagnostics, audit logging, and CRUD UI. Rules are **configuration records only**; they are not consumed by cart, checkout, product pages, or shipping yet.

| Area | Files / behaviour |
|------|-------------------|
| Migration | `database/migrations/20260705170000_create_product_delivery_rules_table.php` |
| Schema | `SchemaVersion::TARGET = '2'` |
| Table registry | `ConfigurationTables` — `product_delivery_rules` suffix |
| Domain | `ProductDeliveryRuleRepositoryInterface`, `ProductTargetType` enum |
| Persistence | `WpdbProductDeliveryRuleRepository` |
| Admin page | `ProductDeliveryRulesPage` — list / add / edit / deactivate |
| Validation | `ProductDeliveryRuleValidator` |
| Target helper | `ProductTargetResolver` — WooCommerce product/variation/category checks |
| Menu | **Delivery Engine → Product Rules** |
| Diagnostics | `ConfigurationHealthChecker::check_product_rules()` |
| System Status | Product rule count + zero warning |
| Uninstaller | Drops `product_delivery_rules` when delete-data is true |

## What was intentionally not added

- Product delivery selector on storefront
- WooCommerce product edit metaboxes/panels
- Cart-line delivery selections or fingerprinting
- WooCommerce shipping packages or custom shipping methods
- Checkout validation, order snapshots, shipments
- Public REST/Store API, frontend JS/CSS
- WPML/WCML/WoodMart/WCFM/VitePOS real adapters
- Demo data or auto-created product rules

## Migration / table details

**Table:** `{prefix}delivery_engine_product_delivery_rules`

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT UNSIGNED PK | Auto increment |
| `target_type` | VARCHAR(32) | `product`, `variation`, `category` |
| `target_id` | BIGINT UNSIGNED | WooCommerce entity ID |
| `target_label_snapshot` | VARCHAR(255) NULL | Admin display snapshot |
| `fulfilment_availability` | VARCHAR(64) | `FulfilmentAvailability` enum |
| `fulfilment_choice` | VARCHAR(64) | `FulfilmentChoice` enum |
| `delivery_offer_ids` | LONGTEXT NULL | JSON array of offer IDs |
| `logistics_profile_id` | BIGINT UNSIGNED NULL | Optional FK |
| `supplier_id` | BIGINT UNSIGNED NULL | Optional FK |
| `origin_id` | BIGINT UNSIGNED NULL | Optional FK |
| `priority` | INT DEFAULT 100 | Lower = higher precedence (future) |
| `status` | VARCHAR(32) DEFAULT `active` | `RecordStatus` enum |
| `internal_notes` | LONGTEXT NULL | Admin-only; redacted from audit |
| `created_at` / `updated_at` | DATETIME | UTC timestamps |

**Indexes:** `target_lookup`, `fulfilment_availability`, `logistics_profile_id`, `supplier_id`, `origin_id`, `status`, `priority`

**Verification:** Migration `verify()` throws if `product_delivery_rules` table is missing — schema version does not advance on failure.

## Admin page

**Path:** Delivery Engine → Product Rules  
**Slug:** `cetech-delivery-engine-product-rules`  
**Capability:** `manage_product_delivery_rules`

List columns: ID, target type/ID/label, fulfilment availability/choice, delivery offers, logistics profile, supplier, origin, priority, status, updated at, actions.

Form fields match table columns including `delivery_offer_ids[]` checkbox group and `internal_notes` textarea.

## Product rule fields supported

All columns listed above. `delivery_offer_ids` stored as JSON in DB; normalized to integer arrays in PHP.

## Validation rules

| Rule | Behaviour |
|------|-----------|
| `target_type` | Required; `product`, `variation`, or `category` |
| `target_id` | Required positive integer; validated against WooCommerce when available |
| `target_label_snapshot` | Sanitized; auto-filled from WooCommerce when blank on save |
| `fulfilment_availability` / `fulfilment_choice` | Required; must match enum values |
| Availability/choice pairs | `international_fulfilment` and `in_warehouse` → `delivery` only; `in_store` → `delivery` or `store_pickup` |
| `delivery_offer_ids` | Required for delivery; must reference existing offers; empty only for `store_pickup` |
| Optional FKs | Logistics profile, supplier, origin — validated when set |
| Origin/supplier | When both set, origin must belong to supplier |
| `priority` | Required integer |
| `status` | `RecordStatus` enum |
| Active duplicates | **Blocked** — same `target_type` + `target_id` + `fulfilment_availability` + active status |

WooCommerce unavailable → safe admin error on target validation; no fatal.

## Product target resolver behaviour

`ProductTargetResolver` (admin-only):

- `validate_target()` — returns error message or null
- `resolve_label()` — product/variation name or category term name
- `target_exists()` — type-specific WooCommerce checks
- Uses `wc_get_product()` and `get_term( …, 'product_cat' )`
- No frontend hooks or customer output

## Diagnostics added

Under **Configuration Health** (read-only):

| Code | Severity | Check |
|------|----------|-------|
| `zero_product_delivery_rules` | warning | No rules configured |
| `product_rule_missing_target` | warning | Active rule, missing WC target |
| `product_rule_invalid_availability` | warning | Unknown availability value |
| `product_rule_invalid_choice` | warning | Unknown choice value |
| `product_rule_invalid_availability_choice` | warning | Invalid pair |
| `product_rule_missing_delivery_offer` | error | Referenced offer missing |
| `product_rule_inactive_delivery_offer` | warning | Referenced offer inactive |
| `product_rule_missing_*` / `inactive_*` | error/warning | FK checks for profile, supplier, origin |
| `product_rule_origin_supplier_mismatch` | error | Origin not under supplier |
| `duplicate_active_product_rule` | warning | Same target + availability |

Does not expose `internal_notes` or private supplier/origin note fields.

## Audit logging behaviour

Uses `ConfigurationAuditLogger` with entity type `product_delivery_rule`:

- Actions: `created`, `updated`, `deactivated`
- `internal_notes` omitted from audit payloads (existing sanitizer)
- Audit failure → admin warning only; CRUD still succeeds

## Privacy / customer boundary rules

- All product rule UI is wp-admin only (`manage_product_delivery_rules`)
- No WooCommerce product metaboxes or metadata writes
- No cart/checkout/shipping/order hooks registered
- No frontend assets enqueued
- No customer-facing REST routes
- Diagnostics and list views show codes/labels only — not private notes

## How to test migration

1. Activate or re-activate the plugin (or trigger `MigrationRunner`).
2. **System Status** → confirm installed schema version is `2`, target `2`.
3. Confirm **Missing configuration tables** shows none (includes `product_delivery_rules`).
4. In phpMyAdmin or WP-CLI, verify table `{prefix}delivery_engine_product_delivery_rules` exists with expected columns/indexes.

## How to test CRUD

1. Ensure baseline config exists (delivery offers, etc.).
2. **Delivery Engine → Product Rules → Add New**.
3. Create a rule for an existing product ID with `in_store` + `delivery` + at least one offer.
4. Confirm list shows the new row; edit updates fields; deactivate sets status inactive.
5. Try duplicate active rule (same target + availability) — should be blocked with error and form draft preserved.
6. Try invalid target ID — validation error, draft preserved.

## How to test permissions

1. Log in as **Administrator** — Product Rules submenu visible; CRUD works.
2. Create a role with only `manage_product_delivery_rules` — parent menu visible; Product Rules accessible; other pages hidden unless other caps granted.
3. User without delivery caps — no Delivery Engine menu.

## How to confirm storefront unchanged

- Browse shop, product, cart, checkout — no delivery selector or new UI
- No new frontend scripts/styles in page source
- WooCommerce product edit screen has no Delivery Engine panel
- No shipping rate changes from product rules (not wired)

## Commands

```powershell
composer dump-autoload -o
Get-ChildItem -Path src,database -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## Risks / TODOs for next phase

| Item | Notes |
|------|-------|
| Runtime consumption | Next phase should resolve rules for cart/product context |
| Priority resolution | Multiple rules per target differentiated by priority — not used in 2C1 |
| WC target drift | Label snapshot may stale if product renamed; diagnostics flag missing targets |
| List limit | Admin list and diagnostics scan up to 500 rows |
| Store pickup without offers | Allowed; ensure pickup locations exist before enabling pickup UX |
| No partial unique index | Duplicate active rules blocked in validator only — DB allows duplicates if bypassed |

## WordPress admin checklist

- [ ] Schema version 2 after activation
- [ ] Product Rules submenu under Delivery Engine
- [ ] Create / edit / deactivate product rule
- [ ] Validation errors preserve form draft
- [ ] Duplicate active rule blocked
- [ ] System Status shows product rule count
- [ ] Configuration Health includes product rule diagnostics
- [ ] Audit log entries for create/update/deactivate (no internal_notes)
- [ ] Storefront/cart/checkout unchanged
