-- Patch existing affiliate_marketing installations so each order only has one affiliate record.
-- Safe to run multiple times.

-- 1. Remove duplicate rows while keeping the newest record for each order_id.
DELETE older
FROM affiliate_orders AS older
JOIN affiliate_orders AS newer
  ON newer.order_id = older.order_id
 AND newer.id > older.id;

-- 2. Add a unique key if it does not exist yet.
SET @affiliate_order_unique_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'affiliate_orders'
      AND index_name = 'uq_order_id'
);

SET @affiliate_order_unique_sql := IF(
    @affiliate_order_unique_exists = 0,
    'ALTER TABLE affiliate_orders ADD UNIQUE KEY uq_order_id (order_id)',
    'SELECT ''uq_order_id already exists'' AS message'
);

PREPARE affiliate_order_unique_stmt FROM @affiliate_order_unique_sql;
EXECUTE affiliate_order_unique_stmt;
DEALLOCATE PREPARE affiliate_order_unique_stmt;
