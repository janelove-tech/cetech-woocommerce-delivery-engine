# Phase 2B5 — Configuration Diagnostics and Admin Hardening

**Status:** Complete  
**Plugin version:** 0.1.0 (foundation)  
**Schema target:** `1` (unchanged)  
**Sources:** `docs/PHASE-2B4-IMPLEMENTATION.md`, `docs/ARCHITECTURE-PLAN.md`

## What was added

Phase 2B5 adds **read-only configuration diagnostics** and small **admin hardening** improvements. No configuration data is written.

| Area | Files / behaviour |
|------|-------------------|
| Diagnostics service | `ConfigurationHealthChecker`, `ConfigurationDiagnostic`, `DiagnosticSeverity` |
| System Status | New **Configuration Health** section with severity summary + diagnostics table |
| Admin hardening | `AdminPageAccess`, `AdminUiHelper`, parent menu capability resolution |
| Repository read | `DestinationRuleRepositoryInterface::list()` for orphan rule checks |

## What was intentionally not added

- Product rules, product selector, cart/checkout/shipping/order/shipment behaviour
- Public REST/Store API endpoints
- Frontend JS/CSS assets (wp-admin only inline styles for diagnostic badges)
- Demo data, migrations, schema version changes
- Automated CLI smoke runner (manual checklist documented below)
- Configuration auto-repair or write actions from diagnostics

## Diagnostics categories

| Category | Checks |
|----------|--------|
| **A. Baseline** | Zero counts for profiles, offers, zones, pickups, suppliers, origins, rate cards |
| **B. Schema/migration** | Schema version, missing tables, last migration status |
| **C. Destination** | Zones without rules (non-fallback), multiple fallbacks, orphan/invalid rules, pickup empty address |
| **D. Supplier/origin** | Missing supplier FK, inactive supplier link, invalid address JSON, active supplier without origins |
| **E. Rate card** | Missing/inactive FKs, expired/future effective dates, invalid amount/currency/charge type, duplicate match signatures |
| **F. Privacy** | Static scan for `register_rest_route` under `src/` |

## Severity meanings

| Severity | Meaning |
|--------|---------|
| **error** | Broken references or schema/migration failure — likely blocks reliable configuration use |
| **warning** | Misconfiguration or data quality issue — should be reviewed |
| **info** | Informational only — not necessarily a problem |
| **ok** | Reserved; individual diagnostics use error/warning/info. Clean state shows success banner instead |

## System Status changes

**Delivery Engine → System Status → Configuration Health**

- Summary counts: errors, warnings, info
- Diagnostics table: severity badge, title, message, related entity (type, ID, safe details)
- Success message: **“No critical configuration issues found.”** when errors and warnings are both zero
- Read-only; requires `manage_delivery_settings`
- Existing capability re-sync unchanged

## Privacy protections

- Diagnostics never include customer/order/payment data
- `internal_notes` and private text are never emitted — only entity type, numeric ID, and safe codes (e.g. `code=…`, `supplier_id=…`)
- Privacy check scans plugin `src/` for `register_rest_route` (static inspection)
- Supplier/origin/rate card repositories remain admin-only; no new public endpoints

## Admin hardening changes

### Parent menu visibility

`AdminMenu::resolve_parent_menu_capability()` sets the parent menu capability to the **first delivery capability** the current user has. This helps custom roles with subset caps (e.g. only `manage_delivery_rate_cards`) see the Delivery Engine top-level menu.

**Tradeoff:** Parent `add_menu_page` callback still points at System Status. WordPress normally lands on the first registered submenu the user can access after the duplicate parent entry is removed. Users without `manage_delivery_settings` should use their permitted submenu directly.

### Consistent access denial

All admin CRUD pages use `AdminPageAccess::require_capability()` with the same denial message.

### UI helpers

`AdminUiHelper` provides `record_status_badge()` and `diagnostic_severity_badge()` for consistent wp-admin markup (used on System Status diagnostics).

## Manual smoke-test checklist

Run after deploy or before tagging a release:

### Autoload / syntax

```bash
composer dump-autoload -o
find src -name '*.php' -print0 | xargs -0 php -l
```

Windows PowerShell:

```powershell
composer dump-autoload -o
Get-ChildItem -Path src -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

### wp-admin

- [ ] Re-sync capabilities on System Status (optional)
- [ ] **System Status** loads; Configuration Health section renders
- [ ] With empty/partial config, warnings appear; with healthy config, success banner shows
- [ ] **Logistics Profiles** list/add/edit still work
- [ ] **Delivery Offers** list/add/edit still work
- [ ] **Destination Zones** list/add/edit + test tool still work
- [ ] **Pickup Locations** list/add/edit still work
- [ ] **Suppliers & Origins** list/add/edit still work
- [ ] **Rate Cards** list/add/edit + test rate tool still work
- [ ] Rate card test with no match shows explicit no-match (not zero/free price)
- [ ] User without delivery caps cannot access admin pages (direct URL → permission denied)
- [ ] Custom role with only one delivery cap sees parent menu (if role granted that cap)

### Storefront unchanged

- [ ] Cart page shows no delivery-engine pricing output
- [ ] Checkout shows no new shipping methods from this plugin
- [ ] No new frontend JS/CSS enqueued on storefront
- [ ] View page source / REST: no supplier, origin, or rate card data exposed

## How to test diagnostics

1. Fresh install with schema but no records → baseline warnings for all zero counts
2. Create active zone without rules (not fallback) → `active_zone_without_rules`
3. Create two active fallback zones → `multiple_active_fallback_zones`
4. Create origin pointing at deleted supplier (if possible via DB) → `origin_missing_supplier`
5. Create two active rate cards with identical match dimensions + priority → duplicate warning
6. Set active rate card `effective_to` in the past → `rate_card_effective_to_expired`
7. Confirm diagnostics table escapes output and shows no note text

## How to test permissions

- Administrator: full access + diagnostics
- User without delivery caps: no menu, direct URLs denied
- Custom role with single cap (e.g. rate cards only): parent menu visible; only permitted submenus; System Status denied unless `manage_delivery_settings` granted

## Known limitations

- Diagnostics load up to **500 rows** per entity type — very large configs may not scan every record
- Duplicate rate card detection uses offer + zone + optional FKs + currency + priority (not charge type)
- Privacy REST scan covers `src/` only at runtime; manual review still recommended before exposing any future API
- Info-level items (e.g. migration unknown, no REST routes) appear even on healthy sites
- Parent menu callback edge case for subset-cap users documented above

## Recommended next phase

**Product Rules (Phase 2C / product configuration)** — wire product-level delivery rules to offers/zones/origins without yet connecting checkout shipping calculation, unless architecture plan specifies a different 2C scope first.

## Risks / TODOs

- Consider filtering info-level diagnostics behind a “show details” toggle in a future admin UX pass
- Add automated PHPUnit tests for `ConfigurationHealthChecker` when test harness exists
- Block rate card saves referencing inactive FK entities (optional CRUD hardening)
- Shared production rate engine should reuse diagnostic codes where applicable
