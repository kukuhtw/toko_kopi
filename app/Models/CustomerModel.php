<?php

declare(strict_types=1);

namespace App\Models;

class CustomerModel extends BaseModel
{
    protected string $table = 'customers';

    public function findOrCreate(string $channel, string $identifier): array
    {
        $existing = $this->query(
            'SELECT * FROM customers WHERE channel = ? AND identifier = ? LIMIT 1',
            [$channel, $identifier]
        )->fetch();

        if ($existing) {
            return $existing;
        }

        $id = $this->insert([
            'channel'    => $channel,
            'identifier' => $identifier,
        ]);

        // Create empty profile
        $this->query(
            'INSERT INTO customer_profiles (customer_id) VALUES (?)',
            [$id]
        );

        return $this->find($id);
    }

    public function updateInfo(int $customerId, array $data): void
    {
        $allowed = ['name', 'email', 'whatsapp'];
        $update = array_intersect_key($data, array_flip($allowed));
        if (isset($update['email'])) {
            $update['email'] = self::normalizeEmail((string)$update['email']);
        }
        if (isset($update['whatsapp'])) {
            $update['whatsapp'] = $this->normalizeWhatsApp((string)$update['whatsapp']);
        }
        if (!empty($update)) {
            $this->update($customerId, $update);
        }
    }

    public function getProfile(int $customerId): array|false
    {
        return $this->query(
            'SELECT * FROM customer_profiles WHERE customer_id = ? LIMIT 1',
            [$customerId]
        )->fetch();
    }

    public function updateProfile(int $customerId, array $data): void
    {
        $allowed = ['address', 'postal_code', 'city', 'favorite_items', 'order_count', 'notes'];
        $update = array_intersect_key($data, array_flip($allowed));
        if (empty($update)) return;

        // Check if profile exists
        $exists = $this->query(
            'SELECT id FROM customer_profiles WHERE customer_id = ?',
            [$customerId]
        )->fetch();

        if ($exists) {
            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($update)));
            $vals = array_values($update);
            $vals[] = $customerId;
            $this->query("UPDATE customer_profiles SET $set WHERE customer_id = ?", $vals);
        } else {
            $update['customer_id'] = $customerId;
            $this->query(
                'INSERT INTO customer_profiles (' . implode(', ', array_keys($update)) . ') VALUES (' .
                implode(', ', array_fill(0, count($update), '?')) . ')',
                array_values($update)
            );
        }
    }

    public function getFavoriteItems(int $customerId): array
    {
        $profile = $this->getProfile($customerId);
        if (!$profile || empty($profile['favorite_items'])) return [];
        return json_decode($profile['favorite_items'], true) ?? [];
    }

    public function updateFavorites(int $customerId, array $itemIds): void
    {
        $this->updateProfile($customerId, ['favorite_items' => json_encode(array_unique($itemIds))]);
    }

    public function resolveWebCustomer(
        string $sessionIdentifier,
        string $name = '',
        string $email = '',
        string $whatsapp = ''
    ): array {
        $sessionIdentifier = trim($sessionIdentifier);
        $email = self::normalizeEmail($email);
        $whatsapp = $this->normalizeWhatsApp($whatsapp);

        $sessionCustomer = $this->findOrCreate('web', $sessionIdentifier);
        $matchedCustomer = $this->findWebCustomerByContact($whatsapp, $email, (int)$sessionCustomer['id']);

        if ($matchedCustomer && (int)$matchedCustomer['id'] !== (int)$sessionCustomer['id']) {
            $this->mergeCustomerRecords((int)$sessionCustomer['id'], (int)$matchedCustomer['id']);
            $sessionCustomer = $this->find((int)$matchedCustomer['id']) ?: $matchedCustomer;
        }

        $update = [];
        if ($name !== '') {
            $update['name'] = $name;
        }
        if ($email !== '') {
            $update['email'] = $email;
        }
        if ($whatsapp !== '') {
            $update['whatsapp'] = $whatsapp;
        }
        if (!empty($update)) {
            $this->updateInfo((int)$sessionCustomer['id'], $update);
        }

        return $this->find((int)$sessionCustomer['id']) ?: $sessionCustomer;
    }

    private function findWebCustomerByContact(string $whatsapp, string $email, int $excludeCustomerId = 0): array|false
    {
        if ($whatsapp !== '') {
            $params = ['web'];
            $sql = 'SELECT * FROM customers WHERE channel = ? AND whatsapp IS NOT NULL AND whatsapp != ""';
            if ($excludeCustomerId > 0) {
                $sql .= ' AND id != ?';
                $params[] = $excludeCustomerId;
            }
            $sql .= ' ORDER BY id ASC';

            foreach ($this->query($sql, $params)->fetchAll() as $row) {
                if ($this->normalizeWhatsApp((string)($row['whatsapp'] ?? '')) === $whatsapp) {
                    return $row;
                }
            }
        }

        if ($email !== '') {
            $params = ['web', $email];
            $sql = 'SELECT * FROM customers WHERE channel = ? AND LOWER(email) = ?';
            if ($excludeCustomerId > 0) {
                $sql .= ' AND id != ?';
                $params[] = $excludeCustomerId;
            }
            $sql .= ' ORDER BY id ASC LIMIT 1';

            $row = $this->query($sql, $params)->fetch();
            if ($row) {
                return $row;
            }
        }

        return false;
    }

    private function mergeCustomerRecords(int $fromCustomerId, int $toCustomerId): void
    {
        if ($fromCustomerId <= 0 || $toCustomerId <= 0 || $fromCustomerId === $toCustomerId) {
            return;
        }

        $from = $this->find($fromCustomerId);
        $to = $this->find($toCustomerId);
        if (!$from || !$to) {
            return;
        }

        $this->db->beginTransaction();

        try {
            $mergedInfo = [
                'name' => $to['name'] ?: $from['name'],
                'email' => $to['email'] ?: $from['email'],
                'whatsapp' => $to['whatsapp'] ?: $from['whatsapp'],
            ];
            $this->update($toCustomerId, $mergedInfo);

            $this->mergeProfiles($fromCustomerId, $toCustomerId);

            $this->query('UPDATE carts SET customer_id = ? WHERE customer_id = ?', [$toCustomerId, $fromCustomerId]);
            $this->query('UPDATE conversations SET customer_id = ? WHERE customer_id = ?', [$toCustomerId, $fromCustomerId]);
            $this->query('UPDATE orders SET customer_id = ? WHERE customer_id = ?', [$toCustomerId, $fromCustomerId]);

            $this->mergeLoyaltyData($fromCustomerId, $toCustomerId);
            $this->mergeCrmNotificationLogs($fromCustomerId, $toCustomerId);

            $this->query('DELETE FROM customers WHERE id = ?', [$fromCustomerId]);

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function mergeProfiles(int $fromCustomerId, int $toCustomerId): void
    {
        $fromProfile = $this->getProfile($fromCustomerId) ?: [];
        $toProfile = $this->getProfile($toCustomerId) ?: [];

        $allowed = ['address', 'postal_code', 'city', 'favorite_items', 'order_count', 'notes'];
        $merged = [];
        foreach ($allowed as $key) {
            $merged[$key] = $toProfile[$key] ?? '';
            if (($merged[$key] === '' || $merged[$key] === null) && !empty($fromProfile[$key])) {
                $merged[$key] = $fromProfile[$key];
            }
        }

        if (!empty($fromProfile['favorite_items']) && !empty($toProfile['favorite_items'])) {
            $fromFavorites = json_decode((string)$fromProfile['favorite_items'], true) ?: [];
            $toFavorites = json_decode((string)$toProfile['favorite_items'], true) ?: [];
            $merged['favorite_items'] = json_encode(array_values(array_unique(array_merge($toFavorites, $fromFavorites))));
        }

        if (!empty($fromProfile['order_count']) || !empty($toProfile['order_count'])) {
            $merged['order_count'] = (string)((int)($fromProfile['order_count'] ?? 0) + (int)($toProfile['order_count'] ?? 0));
        }

        $this->updateProfile($toCustomerId, $merged);
        $this->query('DELETE FROM customer_profiles WHERE customer_id = ?', [$fromCustomerId]);
    }

    private function mergeLoyaltyData(int $fromCustomerId, int $toCustomerId): void
    {
        try {
            $accounts = $this->query(
                'SELECT branch_id, balance_points, lifetime_points
                 FROM loyalty_point_accounts
                 WHERE customer_id = ?',
                [$fromCustomerId]
            )->fetchAll();

            foreach ($accounts as $account) {
                $existing = $this->query(
                    'SELECT id, balance_points, lifetime_points
                     FROM loyalty_point_accounts
                     WHERE branch_id = ? AND customer_id = ?
                     LIMIT 1',
                    [(int)$account['branch_id'], $toCustomerId]
                )->fetch();

                if ($existing) {
                    $this->query(
                        'UPDATE loyalty_point_accounts
                         SET balance_points = ?, lifetime_points = ?, updated_at = CURRENT_TIMESTAMP
                         WHERE id = ?',
                        [
                            (int)$existing['balance_points'] + (int)$account['balance_points'],
                            (int)$existing['lifetime_points'] + (int)$account['lifetime_points'],
                            (int)$existing['id'],
                        ]
                    );

                    $this->query(
                        'DELETE FROM loyalty_point_accounts
                         WHERE branch_id = ? AND customer_id = ?',
                        [(int)$account['branch_id'], $fromCustomerId]
                    );
                } else {
                    $this->query(
                        'UPDATE loyalty_point_accounts
                         SET customer_id = ?
                         WHERE branch_id = ? AND customer_id = ?',
                        [$toCustomerId, (int)$account['branch_id'], $fromCustomerId]
                    );
                }
            }

            $this->query(
                'UPDATE loyalty_point_transactions SET customer_id = ? WHERE customer_id = ?',
                [$toCustomerId, $fromCustomerId]
            );
        } catch (\Throwable) {
            // Loyalty plugin might not be installed yet; ignore merge for missing tables.
        }
    }

    private function mergeCrmNotificationLogs(int $fromCustomerId, int $toCustomerId): void
    {
        try {
            $this->query(
                'UPDATE crm_notification_logs
                 SET customer_id = ?
                 WHERE customer_id = ?',
                [$toCustomerId, $fromCustomerId]
            );
        } catch (\Throwable) {
            // Customer CRM plugin might not be installed yet; ignore merge for missing tables.
        }
    }

    public static function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    public function normalizeWhatsApp(string $whatsapp, ?string $countryCode = null): string
    {
        $value = trim($whatsapp);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/[^0-9+]/', '', $value) ?? '';
        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, '00')) {
            $value = '+' . substr($value, 2);
        }

        if ($countryCode === null || $countryCode === '') {
            $countryCode = $this->getDefaultWhatsAppCountryCode();
        }

        $countryCode = trim($countryCode);
        if ($countryCode === '' || $countryCode === '+') {
            $countryCode = '+62';
        }
        if (!str_starts_with($countryCode, '+')) {
            $countryCode = '+' . ltrim($countryCode, '+');
        }

        if (str_starts_with($value, '+')) {
            return '+' . ltrim(substr($value, 1), '0');
        }

        if (str_starts_with($value, '0')) {
            return $countryCode . ltrim($value, '0');
        }

        $countryDigits = ltrim($countryCode, '+');
        if (str_starts_with($value, $countryDigits)) {
            return '+' . $value;
        }

        return $countryCode . ltrim($value, '0');
    }

    private function getDefaultWhatsAppCountryCode(): string
    {
        try {
            $value = $this->query(
                'SELECT setting_val FROM app_settings WHERE setting_key = ? LIMIT 1',
                ['plugin_customer_crm_default_country_code']
            )->fetchColumn();

            if (is_string($value) && trim($value) !== '') {
                $value = trim($value);
                return str_starts_with($value, '+') ? $value : '+' . ltrim($value, '+');
            }
        } catch (\Throwable) {
            // Ignore missing app_settings in unusual bootstrap cases.
        }

        return '+62';
    }
}
