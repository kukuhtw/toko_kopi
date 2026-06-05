# KopiBot MVP Core

Branch `composer-mvp-core` introduces a Composer based MVP architecture.

## Main capabilities

- Composer autoload
- Environment config
- Migration runner
- Demo seeder
- REST API
- JWT auth
- Product catalog
- Customer
- Cart
- Order
- Mock payment checkout URL
- Promo
- Loyalty
- CRM event log
- FAQ keyword search
- Rule based chatbot

## Quick start

```bash
composer install
cp .env.example .env
composer migrate
php seed.php
composer serve
```

## Docker quick start

```bash
docker compose up -d
```

Then run composer install and migration inside your PHP environment if needed.

## API docs

See `docs/openapi.yaml`.
