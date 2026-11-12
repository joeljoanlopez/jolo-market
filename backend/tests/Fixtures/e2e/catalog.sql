-- Known catalog for the Playwright journeys (frontend/e2e/). Load it after the migrations:
--   docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql --default-character-set=utf8mb4 -u"$MYSQL_USER" "$MYSQL_DATABASE"' < backend/tests/Fixtures/e2e/catalog.sql
-- It replaces only its own rows (external ids starting with "e2e-"), so it can be loaded again.
-- Search "leche" expects, by PD-33: the L group (4 results) by unit price, then kg (2), then
-- unit (1), then the product without a unit price. The unavailable one must not appear.
-- "Leche entera" also has a price history (E5): 0,95 € → 0,99 € → 0,89 € over the last 20 days, so its
-- page shows a minimum of 0,89 €, a maximum of 0,99 € and a current price of 0,89 €. The dates are relative
-- to now, so they always fall within the default period of 6 months (PD-48).

DELETE FROM product WHERE external_id LIKE 'e2e-%';

INSERT INTO product
    (id, supermarket_id, external_id, name, brand, category, image_url,
     package_quantity, package_unit, price_cents, unit_price_cents, unit_price_unit, available, last_seen_at)
VALUES
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000001'), 'mercadona', 'e2e-1', 'Leche entera', 'Hacendado', 'Lácteos', NULL,
     1000, 'l', 89, 89, 'l', 1, '2026-11-09 05:00:00'),
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000002'), 'aldi', 'e2e-2', 'Leche semidesnatada', 'Milsani', 'Lácteos', NULL,
     1500, 'l', 135, 90, 'l', 1, '2026-11-09 05:00:00'),
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000003'), 'bonpreu', 'e2e-3', 'Leche desnatada', 'Bonpreu', 'Lácteos', NULL,
     1000, 'l', 105, 105, 'l', 1, '2026-11-09 05:00:00'),
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000004'), 'mercadona', 'e2e-4', 'Leche sin lactosa', 'Puleva', 'Lácteos', NULL,
     1000, 'l', 129, 129, 'l', 1, '2026-11-09 05:00:00'),
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000005'), 'aldi', 'e2e-5', 'Leche en polvo', 'Nestlé', 'Lácteos', NULL,
     500, 'kg', 650, 1300, 'kg', 1, '2026-11-09 05:00:00'),
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000006'), 'bonpreu', 'e2e-6', 'Leche condensada', 'La Lechera', 'Lácteos', NULL,
     370, 'kg', 245, 662, 'kg', 1, '2026-11-09 05:00:00'),
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000007'), 'mercadona', 'e2e-7', 'Batido de cacao con leche', 'Hacendado', 'Lácteos', NULL,
     6000, 'unit', 270, 45, 'unit', 1, '2026-11-09 05:00:00'),
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000008'), 'aldi', 'e2e-8', 'Leche fresca', NULL, 'Lácteos', NULL,
     NULL, NULL, 150, NULL, NULL, 1, '2026-11-09 05:00:00'),
    -- The cheapest litre of all, but unavailable (PD-22): search must leave it out.
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000009'), 'bonpreu', 'e2e-9', 'Leche entera retirada', 'Bonpreu', 'Lácteos', NULL,
     1000, 'l', 50, 50, 'l', 0, '2026-11-01 05:00:00'),
    -- Does not match "leche".
    (UUID_TO_BIN('01960000-0000-7000-8000-00000000000a'), 'mercadona', 'e2e-10', 'Yogur natural', 'Hacendado', 'Lácteos', NULL,
     500, 'kg', 120, 240, 'kg', 1, '2026-11-09 05:00:00');

-- Deleting a product deletes its snapshots (ON DELETE CASCADE), so loading this file again starts from scratch.
INSERT INTO price_snapshot (product_id, price_cents, unit_price_cents, unit_price_unit, captured_at)
VALUES
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000001'), 95, 95, 'l', UTC_TIMESTAMP(6) - INTERVAL 20 DAY),
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000001'), 95, 95, 'l', UTC_TIMESTAMP(6) - INTERVAL 15 DAY),
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000001'), 99, 99, 'l', UTC_TIMESTAMP(6) - INTERVAL 10 DAY),
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000001'), 89, 89, 'l', UTC_TIMESTAMP(6) - INTERVAL 5 DAY),
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000001'), 89, 89, 'l', UTC_TIMESTAMP(6) - INTERVAL 1 DAY);
