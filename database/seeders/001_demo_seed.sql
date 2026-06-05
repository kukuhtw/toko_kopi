INSERT IGNORE INTO tenants (id, tenant_code, tenant_name, business_type, owner_name, owner_email, owner_phone, subscription_plan, status, created_at)
VALUES (1, 'demo', 'Demo KopiBot Merchant', 'coffee_shop', 'Demo Owner', 'owner@example.com', '08123456789', 'FREE', 'active', NOW());

INSERT IGNORE INTO branches (id, tenant_id, branch_code, branch_name, city, timezone, is_active, created_at)
VALUES (1, 1, 'main', 'Main Branch', 'Jakarta', 'Asia/Jakarta', 1, NOW());

INSERT INTO products (tenant_id, branch_id, sku, name, category, description, base_price, is_active, created_at)
SELECT 1, 1, 'CAP001', 'Cappuccino', 'Coffee', 'Espresso dengan susu steamed dan foam lembut.', 25000, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM products WHERE tenant_id = 1 AND sku = 'CAP001');

INSERT INTO products (tenant_id, branch_id, sku, name, category, description, base_price, is_active, created_at)
SELECT 1, 1, 'CRS001', 'Croissant', 'Bakery', 'Croissant butter renyah cocok untuk teman kopi.', 18000, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM products WHERE tenant_id = 1 AND sku = 'CRS001');

INSERT INTO promos (tenant_id, branch_id, promo_code, promo_name, promo_type, promo_value, minimum_order, max_discount, start_date, end_date, is_active, created_at)
SELECT 1, NULL, 'HEMAT10', 'Diskon 10 Persen', 'percentage', 10, 50000, 25000, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM promos WHERE tenant_id = 1 AND promo_code = 'HEMAT10');

INSERT INTO faq_items (tenant_id, branch_id, category, question, answer, tags, is_active, created_at)
SELECT 1, NULL, 'general', 'Jam buka toko?', 'Kami buka setiap hari pukul 08:00 sampai 22:00.', 'jam buka operasional', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM faq_items WHERE tenant_id = 1 AND question = 'Jam buka toko?');
