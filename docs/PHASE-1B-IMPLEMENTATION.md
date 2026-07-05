# Phase 1B — Core Infrastructure Hardening

**Status:** Complete  
**Plugin version:** 0.1.0 (foundation)  
**Sources:** `docs/ARCHITECTURE-PLAN.md`, `docs/PHASE-1A-IMPLEMENTATION.md`

## What was added

| Component | Files |
|-----------|--------|
| Migration runner shell | `src/Core/Versioning/MigrationInterface.php`, `SchemaVersion.php`, `MigrationRunner.php` |
| Uninstall policy | `uninstall.php`, `src/Bootstrap/Uninstaller.php` |
| Admin menu (minimal) | `src/Presentation/Admin/AdminMenu.php` |
| System Status page | `src/Presentation/Admin/SystemStatusPage.php` |
| Capability sync | `Capabilities::sync()`, `Capabilities::unregister()` |
| Bootstrap wiring | `src/Bootstrap/Plugin.php`, `Activator.php` |
| Autoload notice | Updated message in `cetech-woocommerce-delivery-engine.php` |

### Runtime behaviour added

- Migration runner runs on each boot (after PHP check) and on activation — **no pending migrations** in Phase 1B (schema version stays `0`).
- Admin menu **Delivery Engine → System Status** (capability: `manage_delivery_settings`).
- Read-only status tables: environment, feature flags, integrations, current-user capabilities.
- **Re-sync capabilities** button (POST + nonce) for administrator/shop_manager role caps.
- Uninstall deletes data **only** when `cetech_de_delete_data_on_uninstall` is explicitly `true`.

## What was intentionally not added

- Delivery-domain database tables or migrations
- Delivery offers, zones, rate cards, profiles, suppliers, product rules
- Product/cart/checkout/shipping/shipment/order behaviour
- CRUD admin screens (Dashboard, Shipments, Offers, etc.)
- Feature-flag editor
- Real WPML/WCML/WoodMart/WCFM/VitePOS/Blocks integrations
- Frontend JS/CSS assets (native form submit only)

## How to test System Status page

1. Run `composer install` in the plugin directory.
2. Activate plugin with WooCommerce active (or inactive — page still loads after PHP check).
3. Log in as Administrator.
4. Open **Delivery Engine → System Status** in wp-admin.
5. Confirm read-only rows: plugin/PHP/WP/WC versions, HPOS declaration, schema `0`, autoload yes/no, feature flags, integrations, your capabilities.
6. Confirm no customer/order/supplier data appears.

## How to test uninstall safety

### Default (safe)

1. Activate plugin, then deactivate.
2. Delete plugin from Plugins screen.
3. Confirm `cetech_de_*` options remain in `wp_options` (e.g. `cetech_de_db_version`).
4. Confirm delivery capabilities remain on administrator role.

### Delete-data enabled

1. In database or WP-CLI: `update_option( 'cetech_de_delete_data_on_uninstall', 1 );`
2. Delete plugin from Plugins screen.
3. Confirm feature-flag options and `cetech_de_db_version` are removed.
4. Confirm delivery capabilities removed from administrator and shop_manager.
5. Confirm no errors when WooCommerce is inactive during uninstall.

## How to test capability re-sync

1. Manually remove one cap from administrator (e.g. `manage_shipments`).
2. Open **System Status**, click **Re-sync capabilities**.
3. Confirm success notice and `manage_shipments` shows **Yes** again for your user.

## Rollback notes

- Deactivate plugin: no data removed (unchanged from Phase 1A).
- Remove Phase 1B code: deactivate plugin; optional delete with `cetech_de_delete_data_on_uninstall` if you want options/caps cleaned.
- Migration runner with zero migrations does not change schema version.

## Commands

```bash
composer install
composer dump-autoload -o
```

```powershell
Get-ChildItem -Path src -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
php -l cetech-woocommerce-delivery-engine.php
php -l uninstall.php
```

## WordPress admin checklist

| Step | Expected |
|------|----------|
| Missing `vendor/` | Admin error about `composer install` / package with vendor |
| Activate with WC | Success notice; **Delivery Engine** menu visible |
| System Status | All sections render escaped |
| Re-sync caps | Nonce-protected; success notice |
| Storefront | No delivery UI or shipping changes |
| `debug.log` / WC logs | Migration runner info: no migrations registered |

## TODOs for Phase 2

- Register first real migration when configuration tables are introduced
- Wire `MigrationRunner::set_migrations()` from migration discovery (`database/migrations/`)
- Add Diagnostics / Logs admin screens
- Feature-flag editor (settings screen)
- Do not enable product delivery selector until configuration domain exists
