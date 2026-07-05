# Phase 2A — Configuration Domain Schema

**Status:** Complete  
**Plugin version:** 0.1.0 (foundation)  
**Schema target:** `1`  
**Sources:** `docs/ARCHITECTURE-PLAN.md`, `docs/PHASE-1B-IMPLEMENTATION.md`

## What was added

Phase 2A delivers the **configuration-domain database schema and persistence foundation**. No customer-facing delivery behaviour was added.

| Area | Files / behaviour |
|------|-------------------|
| Migration | `database/migrations/20260705160000_create_configuration_tables.php` |
| Discovery | `src/Core/Versioning/MigrationDiscovery.php` |
| Migration status | `src/Core/Versioning/MigrationStatus.php` |
| Schema version | `SchemaVersion::TARGET = '1'`, `SchemaVersion::FOUNDATION = '0'` |
| Table helpers | `TableNames`, `ConfigurationTables` |
| Domain enums | `FulfilmentAvailability`, `FulfilmentChoice`, `DeliveryRoute`, `CarrierVisibility`, `RateCardChargeType`, `RecordStatus` |
| Value objects | `Money`, `CurrencyCode`, `DurationRange` |
| Repository interfaces | 8 configuration-domain interfaces under `src/Domain/` |
| Repository skeletons | 8 `Wpdb*` classes under `src/Infrastructure/Persistence/` |
| Wiring | `Plugin::register_repository_bindings()`, migration discovery on boot/activation |
| System Status | Target/installed schema version, table presence, last migration status |
| Uninstall | Drops Phase 2A configuration tables when delete-data option is explicitly true |

## Tables created

All tables use the dynamic WordPress prefix: `{$wpdb->prefix}delivery_engine_*`

| Table suffix | Purpose |
|--------------|---------|
| `delivery_offers` | Reusable customer-selectable delivery services (route, service level, carrier visibility, default time ranges, display priority) |
| `destination_zones` | Geographic zone headers (fallback flag, remote-area flag, priority) |
| `destination_rules` | Layered match rules per zone (country, region, city, postcode) |
| `logistics_profiles` | Internal transport/handling classification (parcel size, handling, route eligibility, consolidation) |
| `suppliers` | Private supplier registry (admin-only operational data) |
| `origins` | Private origin records linked to suppliers |
| `pickup_locations` | Public-facing store pickup locations |
| `rate_cards` | Manual pricing rules linking offer + zone + optional profile/supplier/origin |
| `rate_card_rules` | Extensible key/value rule rows attached to rate cards |
| `audit_log` | Append-only administrative change log |

**Not created in Phase 2A:** `product_rules`, `shipments`, `shipment_items`, `shipment_events`.

## Migration behaviour

- Migrations are discovered from `database/migrations/*.php` via `MigrationDiscovery`.
- Each file must `return` an instance implementing `MigrationInterface`.
- Malformed or non-conforming files are logged and skipped (no fatal error).
- Migrations are sorted by `get_version()` and applied only when installed version is lower.
- `up()` uses WordPress `dbDelta()` with `$wpdb->get_charset_collate()`.
- Successful runs update `cetech_de_db_version` and record `cetech_de_last_migration_status`.
- Failed runs log an error and record failure status; schema version is **not** bumped.
- No demo data, offers, zones, suppliers, or rate cards are seeded.

## Schema version behaviour

| Constant / method | Value / role |
|-------------------|--------------|
| `SchemaVersion::FOUNDATION` | `0` — pre-configuration state |
| `SchemaVersion::TARGET` | `1` — current code target |
| `SchemaVersion::get()` | Installed version from `cetech_de_db_version` |
| `SchemaVersion::is_up_to_date()` | `true` when installed >= target |

After successful Phase 2A migration, `cetech_de_db_version` becomes `1`.

## How to test migration

1. Run `composer install && composer dump-autoload -o`.
2. Activate (or reactivate) the plugin with WooCommerce active.
3. Open **Delivery Engine → System Status**:
   - Target schema version = `1`
   - Installed schema version = `1`
   - Configuration tables present = Yes
   - Last migration status shows success for `20260705160000_create_configuration_tables`
4. Verify tables in phpMyAdmin or WP-CLI:

```bash
wp db query "SHOW TABLES LIKE '%delivery_engine_%'"
```

5. Deactivate and reactivate — migration must **not** re-run (schema stays `1`, logs show skip).
6. Check WooCommerce → Status → Logs (`cetech-delivery-engine` source) for migration info entries.

## How to test uninstall safety

### Default (safe)

1. Activate plugin, confirm tables exist.
2. Delete plugin from Plugins screen (do **not** set delete-data option).
3. Confirm `cetech_de_*` options remain.
4. Confirm configuration tables remain in the database.

### Delete-data enabled

1. `wp option update cetech_de_delete_data_on_uninstall 1`
2. Delete plugin from Plugins screen.
3. Confirm all 10 Phase 2A configuration tables are dropped.
4. Confirm options and capabilities are removed.

## What was intentionally not added

- Product delivery selector, product edit panel
- Cart-line delivery selections, fingerprinting
- WooCommerce shipping packages or custom shipping method
- Checkout validation, order snapshots, shipments
- Admin CRUD screens (Dashboard, Offers, Zones, etc.)
- Full repository `save()` implementations
- WPML/WCML/WoodMart/WCFM/VitePOS real adapters
- Frontend JS/CSS, demo data, auto-created configuration records

## Commands

```bash
composer dump-autoload -o
```

```powershell
Get-ChildItem -Path src,database -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
php -l cetech-woocommerce-delivery-engine.php
php -l uninstall.php
```

## WordPress activation checklist

| Step | Expected |
|------|----------|
| Activate with WC | Success notice; schema migrates to `1` |
| System Status | Target/installed version `1`; all tables present |
| Storefront | No delivery UI or shipping changes |
| Reactivate | Migration not re-applied |
| Default uninstall | Tables and options retained |
| Delete-data uninstall | Configuration tables dropped |

## TODOs for Phase 2B

- Admin CRUD screens for offers, zones, profiles, suppliers, origins, pickup locations, rate cards
- Full repository `save()` / validation / audit logging on writes
- Destination zone rule editor and zone test tool
- Rate card “Test this rate” tool
- Configuration health checks in diagnostics
- Product rules table migration (separate phase per roadmap)
- Cache invalidation hooks when configuration records change
