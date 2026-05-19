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
}
