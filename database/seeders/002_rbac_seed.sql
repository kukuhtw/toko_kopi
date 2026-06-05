INSERT INTO roles (tenant_id, role_code, role_name, created_at)
SELECT NULL, 'super_admin', 'Super Admin', NOW()
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE role_code = 'super_admin');

INSERT INTO roles (tenant_id, role_code, role_name, created_at)
SELECT NULL, 'merchant_admin', 'Merchant Admin', NOW()
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE role_code = 'merchant_admin');

INSERT INTO roles (tenant_id, role_code, role_name, created_at)
SELECT NULL, 'branch_manager', 'Branch Manager', NOW()
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE role_code = 'branch_manager');

INSERT INTO permissions (permission_code, permission_name, created_at)
SELECT 'cart.manage', 'Manage Cart', NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'cart.manage');

INSERT INTO permissions (permission_code, permission_name, created_at)
SELECT 'product.manage', 'Manage Product', NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'product.manage');

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r, permissions p
WHERE r.role_code IN ('super_admin', 'merchant_admin')
  AND p.permission_code IN ('cart.manage', 'product.manage')
  AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r, permissions p
WHERE r.role_code = 'branch_manager'
  AND p.permission_code = 'cart.manage'
  AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
