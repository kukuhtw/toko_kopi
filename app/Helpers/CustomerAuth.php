<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Config\Database;
use App\Models\CustomerModel;

class CustomerAuth
{
    public static function startSession(): void
    {
        Auth::startSession();
    }

    public static function login(string $contact, string $orderNumber): array|false
    {
        $contact = trim($contact);
        $orderNumber = strtoupper(trim($orderNumber));
        if ($contact === '' || $orderNumber === '') {
            return false;
        }

        $customerModel = new CustomerModel();
        $normalizedEmail = CustomerModel::normalizeEmail($contact);
        $normalizedWhatsapp = $customerModel->normalizeWhatsApp($contact);

        $stmt = Database::getInstance()->prepare(
            'SELECT
                c.id,
                c.name,
                c.identifier,
                c.email,
                c.whatsapp,
                o.order_number,
                o.customer_email,
                o.customer_wa,
                o.created_at AS order_created_at
             FROM orders o
             JOIN customers c ON c.id = o.customer_id
             WHERE UPPER(o.order_number) = ?
             ORDER BY o.created_at DESC
             LIMIT 10'
        );
        $stmt->execute([$orderNumber]);

        foreach ($stmt->fetchAll() as $row) {
            $rowEmail = CustomerModel::normalizeEmail((string)($row['email'] ?? ''));
            $orderEmail = CustomerModel::normalizeEmail((string)($row['customer_email'] ?? ''));
            $rowWhatsapp = $customerModel->normalizeWhatsApp((string)($row['whatsapp'] ?? ''));
            $orderWhatsapp = $customerModel->normalizeWhatsApp((string)($row['customer_wa'] ?? ''));

            $matched = false;
            if ($normalizedEmail !== '' && ($normalizedEmail === $rowEmail || $normalizedEmail === $orderEmail)) {
                $matched = true;
            }
            if ($normalizedWhatsapp !== '' && ($normalizedWhatsapp === $rowWhatsapp || $normalizedWhatsapp === $orderWhatsapp)) {
                $matched = true;
            }

            if ($matched) {
                $_SESSION['customer_portal_id'] = (int)$row['id'];
                $_SESSION['customer_portal_name'] = (string)($row['name'] ?: $row['identifier']);
                $_SESSION['customer_portal_email'] = (string)($row['email'] ?? '');
                $_SESSION['customer_portal_whatsapp'] = (string)($row['whatsapp'] ?? '');
                $_SESSION['customer_portal_verified_at'] = date('Y-m-d H:i:s');

                session_regenerate_id(true);

                return [
                    'id' => (int)$row['id'],
                    'name' => (string)($row['name'] ?: $row['identifier']),
                    'email' => (string)($row['email'] ?? ''),
                    'whatsapp' => (string)($row['whatsapp'] ?? ''),
                ];
            }
        }

        return false;
    }

    public static function logout(): void
    {
        unset(
            $_SESSION['customer_portal_id'],
            $_SESSION['customer_portal_name'],
            $_SESSION['customer_portal_email'],
            $_SESSION['customer_portal_whatsapp'],
            $_SESSION['customer_portal_verified_at']
        );
        session_regenerate_id(true);
    }

    public static function check(): bool
    {
        return !empty($_SESSION['customer_portal_id']);
    }

    public static function customer(): array
    {
        return [
            'id' => (int)($_SESSION['customer_portal_id'] ?? 0),
            'name' => (string)($_SESSION['customer_portal_name'] ?? ''),
            'email' => (string)($_SESSION['customer_portal_email'] ?? ''),
            'whatsapp' => (string)($_SESSION['customer_portal_whatsapp'] ?? ''),
            'verified_at' => (string)($_SESSION['customer_portal_verified_at'] ?? ''),
        ];
    }

    public static function requireLogin(string $redirectTo = '/customer/login.php'): void
    {
        if (!self::check()) {
            header('Location: ' . BASE_URL . $redirectTo);
            exit;
        }
    }
}
