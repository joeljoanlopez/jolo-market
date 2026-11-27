-- Known catalog for the Playwright journeys (frontend/e2e/). Load it after the migrations:
--   docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql --default-character-set=utf8mb4 -u"$MYSQL_USER" "$MYSQL_DATABASE"' < backend/tests/Fixtures/e2e/catalog.sql
-- It replaces only its own rows (external ids starting with "e2e-"), so it can be loaded again.
-- Search "leche" expects, by PD-33: the L group (5 results, Bonpreu's Catalan "Llet sencera" among them through the
-- dictionary, PD-101) by unit price, then kg (2), then unit (1), then the product without a unit price. The
-- unavailable one must not appear. "pan" and "pa" both find the bread of Mercadona and Bonpreu (E16).
-- Search reads the Typesense index: run app:catalog:reindex-search after loading this file (PD-100).
-- "Leche entera" also has a price history (E5): 0,95 € → 0,99 € → 0,89 € over the last 20 days, so its
-- page shows a minimum of 0,89 €, a maximum of 0,99 € and a current price of 0,89 €. The dates are relative
-- to now, so they always fall within the default period of 6 months (PD-48).

DELETE FROM product WHERE external_id LIKE 'e2e-%';

INSERT INTO product
    (id, supermarket_id, external_id, name, brand, category, image_url,
     package_quantity, package_unit, price_cents, unit_price_cents, unit_price_unit, available, last_seen_at)
VALUES
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000001'), 'mercadona', 'e2e-1', 'Leche entera', 'Hacendado', 'Lácteos y huevos', NULL,
     1000, 'l', 89, 89, 'l', 1, '2026-11-09 05:00:00'),
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000002'), 'aldi', 'e2e-2', 'Leche semidesnatada', 'Milsani', 'Lácteos y huevos', NULL,
     1500, 'l', 135, 90, 'l', 1, '2026-11-09 05:00:00'),
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000003'), 'bonpreu', 'e2e-3', 'Leche desnatada', 'Bonpreu', 'Lácteos y huevos', NULL,
     1000, 'l', 105, 105, 'l', 1, '2026-11-09 05:00:00'),
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000004'), 'mercadona', 'e2e-4', 'Leche sin lactosa', 'Puleva', 'Lácteos y huevos', NULL,
     1000, 'l', 129, 129, 'l', 1, '2026-11-09 05:00:00'),
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000005'), 'aldi', 'e2e-5', 'Leche en polvo', 'Nestlé', 'Lácteos y huevos', NULL,
     500, 'kg', 650, 1300, 'kg', 1, '2026-11-09 05:00:00'),
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000006'), 'bonpreu', 'e2e-6', 'Leche condensada', 'La Lechera', 'Lácteos y huevos', NULL,
     370, 'kg', 245, 662, 'kg', 1, '2026-11-09 05:00:00'),
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000007'), 'mercadona', 'e2e-7', 'Batido de cacao con leche', 'Hacendado', 'Lácteos y huevos', NULL,
     6000, 'unit', 270, 45, 'unit', 1, '2026-11-09 05:00:00'),
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000008'), 'aldi', 'e2e-8', 'Leche fresca', NULL, 'Lácteos y huevos', NULL,
     NULL, NULL, 150, NULL, NULL, 1, '2026-11-09 05:00:00'),
    -- The cheapest litre of all, but unavailable (PD-22): search must leave it out.
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000009'), 'bonpreu', 'e2e-9', 'Leche entera retirada', 'Bonpreu', 'Lácteos y huevos', NULL,
     1000, 'l', 50, 50, 'l', 0, '2026-11-01 05:00:00'),
    -- Does not match "leche".
    (UUID_TO_BIN('01960000-0000-7000-8000-00000000000a'), 'mercadona', 'e2e-10', 'Yogur natural', 'Hacendado', 'Lácteos y huevos', NULL,
     500, 'kg', 120, 240, 'kg', 1, '2026-11-09 05:00:00'),
    -- Bonpreu names its products in Catalan: "leche" finds this milk through the dictionary (PD-101).
    (UUID_TO_BIN('01960000-0000-7000-8000-00000000000b'), 'bonpreu', 'e2e-11', 'Llet sencera', 'Bonpreu', 'Lácteos y huevos', NULL,
     1000, 'l', 110, 110, 'l', 1, '2026-11-09 05:00:00'),
    -- The bread of two chains, one Spanish and one Catalan name ("pan" ↔ "pa", PD-101).
    (UUID_TO_BIN('01960000-0000-7000-8000-00000000000c'), 'mercadona', 'e2e-12', 'Pan de molde', 'Hacendado', 'Panadería', NULL,
     460, 'kg', 125, 272, 'kg', 1, '2026-11-09 05:00:00'),
    (UUID_TO_BIN('01960000-0000-7000-8000-00000000000d'), 'bonpreu', 'e2e-13', 'Pa de pagès', 'Bonpreu', 'Panadería', NULL,
     500, 'kg', 180, 360, 'kg', 1, '2026-11-09 05:00:00');

-- Deleting a product deletes its snapshots (ON DELETE CASCADE), so loading this file again starts from scratch.
INSERT INTO price_snapshot (product_id, price_cents, unit_price_cents, unit_price_unit, captured_at)
VALUES
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000001'), 95, 95, 'l', UTC_TIMESTAMP(6) - INTERVAL 20 DAY),
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000001'), 95, 95, 'l', UTC_TIMESTAMP(6) - INTERVAL 15 DAY),
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000001'), 99, 99, 'l', UTC_TIMESTAMP(6) - INTERVAL 10 DAY),
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000001'), 89, 89, 'l', UTC_TIMESTAMP(6) - INTERVAL 5 DAY),
    (UUID_TO_BIN('01960000-0000-7000-8000-000000000001'), 89, 89, 'l', UTC_TIMESTAMP(6) - INTERVAL 1 DAY);
