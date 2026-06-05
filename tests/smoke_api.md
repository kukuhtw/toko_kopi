# KopiBot API Smoke Test

Run local server:

```bash
php -S localhost:8000 -t public
```

Health:

```bash
curl http://localhost:8000/api/health
```

Product menu:

```bash
curl "http://localhost:8000/api/products?tenant_id=1&branch_id=1"
```

Add cart item:

```bash
curl -X POST http://localhost:8000/api/cart/add \
  -H "Content-Type: application/json" \
  -d '{"tenant_id":1,"branch_id":1,"customer_id":1,"session_id":"demo","product_id":1,"product_name":"Cappuccino","qty":2,"price":25000}'
```

Checkout cart:

```bash
curl -X POST http://localhost:8000/api/cart/checkout \
  -H "Content-Type: application/json" \
  -d '{"tenant_id":1,"branch_id":1,"customer_id":1,"session_id":"demo","customer_name":"Demo Customer","customer_email":"demo@example.com","customer_phone":"08123456789"}'
```

Chatbot message:

```bash
curl -X POST http://localhost:8000/api/chatbot/message \
  -H "Content-Type: application/json" \
  -d '{"tenant_id":1,"branch_id":1,"sender_id":"guest","message":"ada promo?"}'
```
