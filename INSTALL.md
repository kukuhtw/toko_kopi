# KopiBot Composer MVP Installation

## 1. Clone branch

```bash
git clone https://github.com/kukuhtw/toko_kopi.git
cd toko_kopi
git checkout composer-mvp-core
```

## 2. Install Composer dependencies

```bash
composer install
```

## 3. Configure environment

```bash
cp .env.example .env
```

Edit `.env`:

```env
DB_DATABASE=kopibot
DB_USERNAME=root
DB_PASSWORD=
JWT_SECRET=change-this-secret-in-production
```

## 4. Create database

```sql
CREATE DATABASE kopibot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 5. Run migrations

```bash
php migrate.php
```

## 6. Run local API server

```bash
php -S localhost:8000 -t public
```

## 7. Test health endpoint

```bash
curl http://localhost:8000/api/health
```

## 8. Register admin user

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"tenant_id":1,"name":"Admin","email":"admin@example.com","password":"secret123"}'
```

## 9. Login

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"tenant_id":1,"email":"admin@example.com","password":"secret123"}'
```

## 10. Test chatbot

```bash
curl -X POST http://localhost:8000/api/chatbot/message \
  -H "Content-Type: application/json" \
  -d '{"tenant_id":1,"branch_id":1,"sender_id":"guest","message":"ada promo?"}'
```
