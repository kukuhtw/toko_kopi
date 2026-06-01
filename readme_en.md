# KopiBot - AI Chatbot Order System

> ## AI Agent Commerce Platform
>
> KopiBot is an AI commerce platform for automating orders, customer service, customer loyalty, Customer CRM, Customer Portal, chat channel integration, payment gateway integration, delivery connector, POS connector, and multi-branch management for different types of businesses.
>
> **Documentation Language:**
> - [Indonesian README](README.md)
> - [French README](readme_fr.md)
>
> This application was originally developed for coffee shops, then expanded into an AI Agent Commerce platform that can be used for culinary businesses, bakeries, beverage stores, fruit shops, fresh meat stores, vegetable stores, pharmacies, mini marts, retail marts, and other store models that need chat-based ordering, product catalogs, promotions, loyalty, checkout, delivery, and external system integration.
>
> ### Features
> - AI Chatbot Order Menu
> - WhatsApp / Telegram / Discord Integration
> - Multi Branch Management
> - AI Upselling & Promo Recommendation
> - Order via Website & Chat Apps
> - Variant Product & Topping Support
> - Product Photo Upload & AI Image Generation
> - Loyalty Point, Redeem Point, and Customer CRM
> - Customer Self-Service Dashboard
> - Multi Currency, Tax, and Timezone
> - Menu template plugins for coffee shops, bakeries, fruit stores, fresh market, pharmacies, marts, fashion, phone accessories, tours & travel, and umrah
> - Payment gateway plugins, POS connector, delivery connector, FAQ RAG, complaint handling, and customer support automation
>
> ### Tech Stack
> PHP Native - MySQL - OpenAI - Anthropic
> WhatsApp Gateway - REST API - LLM AI
>
> ### Suitable For
> Coffee Shop - Cafe - Restaurant - Bakery - Beverage Store - Fruit Store - Fresh Meat Market - Vegetable Store - Pharmacy - Mini Mart - Retail Mart - Specialty Store
>
> Created and developed by:
> Kukuh TW
>
> Email     : kukuhtw@gmail.com
> WhatsApp  : https://wa.me/628129893706
> Instagram : @kukuhtw
> X/Twitter : @kukuhtw
> GitHub    : https://github.com/kukuhtw/toko_kopi
> Facebook  : https://www.facebook.com/kukuhtw
> LinkedIn  : https://linkedin.com/in/kukuhtw
>
> Demo:
> https://botlelang.com/toko_kopi
>
> Copyright 2026 Kukuh TW. All rights reserved.

KopiBot is built for businesses that want an ordering, customer support, loyalty, and digital catalog system they can truly control. It is powered by native PHP 8, without a large framework, and uses one codebase for multi-business, multi-branch, multi-channel, multi-language operations, promo engine, loyalty points, Customer CRM, Customer Portal, and a plugin system. Although the repository name is still `toko_kopi`, the product direction has expanded into a configurable AI Agent Commerce platform for culinary, pharmacy, retail, travel, and booking-based service businesses.

## Problems It Solves

Many small and mid-sized businesses want to serve orders from websites, WhatsApp, and other chat channels, but their operations are often spread across disconnected tools. The catalog lives in one place, promotions in another, customer history is scattered, loyalty is inconsistent, and branch teams struggle to get a complete picture of each customer.

Another recurring problem is the limitation of instant, one-size-fits-all solutions. As soon as a business needs a specific checkout flow, branch-specific promotion rules, a certain payment integration, or a product template that matches its vertical, generic tools quickly become restrictive. Even small changes may depend on the vendor, raise recurring costs, or lock business data inside a third-party platform.

## The Solution

KopiBot is designed as an AI Agent Commerce foundation that can be installed, owned, and extended by the business or its technical team. The goal is not only to make a chatbot reply to messages, but to support the full commerce flow end to end:

- capture customer intent from chat or web ordering
- display branch-aware catalogs and product variants
- drive upselling, promotions, and loyalty automatically
- store reusable customer history inside CRM
- connect checkout to payment, delivery, POS, or other operational workflows

From an implementation perspective, the platform is intentionally modular. One brand can run many branches, channels, catalog types, and integrations without splitting the system into separate applications.

## Why Not a Typical SaaS?

This platform differs from generic commerce SaaS because it prioritizes control and extensibility. In a SaaS model, businesses usually adapt themselves to the vendor's workflow. When they need something specific, the options are often limited: wait for the vendor roadmap, pay for another add-on, or accept an operational compromise.

With KopiBot, the business or internal technical team can:

- self-host the system and keep full access to the database and codebase
- adjust checkout workflows, AI prompts, CRM logic, promotions, and branch rules
- add new integrations without waiting for a central vendor
- build business-specific installation templates for different verticals

This approach fits agencies, software houses, multi-branch operators, or businesses that want to build a long-term digital asset instead of renting the same SaaS panel used by everyone else.

## Plugins & Extensions

The plugin architecture is one of the core pillars of the project. New features do not have to be placed directly in the core. Through action and filter hooks, plugins can extend application behavior with less risk to the main codebase, making upgrades and feature experiments safer.

Some extension categories already supported:

- product and service template plugins for installer seed catalogs
- payment gateway plugins such as Midtrans, Xendit, iPaymu, and Nicepay
- POS connector plugins such as Moka Connect / Private Solution
- delivery connector plugins such as GoSend
- knowledge and support plugins such as FAQ RAG and complaint handling
- branding and theme plugins for store name, brand icon, tagline, and visual appearance

This model allows each deployment to assemble a different feature stack. One installation can act as a coffee shop ordering bot, another as a digital pharmacy, minimarket, travel booking assistant, or umrah portal, all on the same foundation but with a different plugin composition.

---

## Business Vertical Expansion

This application is no longer focused only on coffee shops. With the plugin system and menu template approach, the application can become the foundation for commerce chatbots across several business categories.

| Business Vertical | Example Use Cases | Feature Support |
|----------|--------|----------|
| **Culinary / F&B** | Coffee shop, cafe, restaurant, bakery, beverage store | Menu ordering, product variants, toppings, promotions, loyalty, upselling, delivery, payment gateway |
| **Fresh Market** | Fruit store, juice, smoothie, salad, fresh meat, vegetables | Fresh product menu templates, item catalog, item pricing, multi-branch support, checkout, Customer CRM |
| **Pharmacy** | Pharmacies, general medicine stores, non-prescription health products, vitamins, light medical equipment | Product catalog, customer FAQ, complaint handler, CRM, payment gateway, delivery connector |
| **Mart / Retail** | Mini mart, convenience store, modern grocery store, retail mart | Large item catalog, cart, promotions, multi-branch support, customer portal, payment gateway, POS connector |
| **Specialty Store** | Niche product store, community store, small branch store | Modular plugins, chat channels, admin dashboard, data export, external integration |

The latest plugin features that strengthen this expansion include menu templates, FAQ RAG, complaint handling, additional payment gateways such as iPaymu and Nicepay, Moka POS connector, GoSend delivery connector, SIRCLO connector scaffold, Customer CRM, and Customer Portal. This combination of features allows the application to be used as an ordering, support, loyalty, and commerce automation platform across industries, not only as a coffee ordering chatbot.

---

## Features

| Category | Details |
|----------|--------|
| **AI Chatbot** | Multi-intent detection via rule-based and LLM — a single customer message can trigger and process multiple intents at once (e.g. order + ask promo). `detectAll()` detects all intents, `filterIntents()` removes noise, `dispatchAll()` executes each sequentially and merges all replies |
| **Multi Business Vertical** | One codebase can be used for coffee shops, restaurants, bakeries, fruit stores, fresh meat stores, vegetable stores, pharmacies, mini marts, and retail marts. The `business_type` branch setting makes all LLM prompts (intent detector, menu assistant, promo assistant, FAQ assistant) automatically adapt to the business context |
| **Multi Branch** | One brand can manage many branches with separate menus, promotions, settings, currencies, and timezones |
| **Multi Channel** | Website, WhatsApp, Telegram, and Discord with the same chatbot logic |
| **Plugin System** | Add features without changing the core code through action/filter hooks |
| **Shopping Cart** | Add, edit, remove, clear, apply promotions, redeem loyalty points, and checkout using sessions |
| **Checkout Flow** | The chatbot asks for customer data step by step until the order is ready to be created |
| **Checkout Profile Memory** | Customer data such as name, email, WhatsApp number, and address is stored in the browser and automatically filled in during the next checkout |
| **Loyalty Point** | Automatically earn points, check balance, and redeem points through the chatbot and web order page |
| **Promo Engine** | Percentage discounts, fixed discounts, promo codes, promo schedules, minimum order rules, and promo recommendations |
| **FAQ RAG** | Global FAQ and custom branch FAQ, branch override, CSV/XLS import/export, analytics, and local vector store |
| **Complaint Handling** | Detect complaints in the chat flow, classify AI vs human follow-up, and create complaint tickets for branches |
| **Payment Gateway** | Midtrans, Xendit, iPaymu, and Nicepay through plugins |
| **POS Connector** | Scaffold and live sync queue for Moka Connect / Private Solution, inbound webhook sync, and retry runner |
| **Delivery Connector** | GoSend partner connector with live-ready endpoint configuration, booking queue, pickup trigger, webhook status, and audit log |
| **Menu Management** | CSV upload, size/price variants, toppings, branch-level override, product photo upload, and AI product photo generation |
| **Menu Templates** | Ready-to-use menu data template plugins: Coffee Shop, Bakery, Fruit Store, Meat & Vegetables, Pharmacy, Mart, Warung, Baso, Kebab, Burger, Phone Accessories, Women's Fashion, Tours & Travel, and Umrah |
| **Dashboard** | Cross-branch super admin, branch admin, Customer CRM, customer loyalty history, and Customer Portal self-service |
| **Customer CRM** | Customer identity normalization based on email/WhatsApp, loyalty notifications, and branch-level CRM logs |
| **Customer Portal** | Lightweight customer login using contact information + order number to check order history, loyalty, profile, and repeat order |
| **HTML Documentation** | README and Markdown documentation are also available as HTML pages |
| **Export CSV** | Export orders, menus, promotions, and related dashboard data |

---

## README Update Note

This README has been updated to explain the new direction of the application as a multi-vertical AI Agent Commerce platform. The added information follows the latest plugin features that are already available or prepared in the plugin architecture, including chat channels, payment gateway, POS connector, delivery connector, FAQ RAG, complaint handler, Customer CRM, Customer Portal, and menu templates for different business types.

## Available Product Templates

The installer currently provides the following ready-to-seed product and service templates:

| Template | Category | Example Products / Services |
| --- | --- | --- |
| Default Seed Coffee Menu | Coffee Shop | Espresso, Cappuccino, Cafe Latte, Americano |
| Coffee Shop | Coffee & Beverages | Signature Coffee, Matcha Latte, Croffle |
| Bakery | Bakery & Pastry | Croissant, Milk Bread, Cinnamon Roll |
| Fruit Store | Fresh Fruit | Mango, Orange, Apple, Fruit Package |
| Meat & Veggie | Fresh Market | Beef, Chicken, Spinach, Carrot |
| Pharmacy / Apotek | Health & Medicine | Fever Medicine, Vitamins, Antiseptic |
| Minimarket | Daily Essentials | Rice, Instant Noodles, Mineral Water |
| Resto Indonesia | Indonesian Restaurant | Nasi Goreng, Ayam Geprek, Es Teh |
| Warung | Food Stall | Rice Package, Fried Snacks, Hot Tea |
| Resto Baso & Minuman | Meatball & Drinks | Baso Urat, Baso Telur, Iced Orange |
| Kebab | Fast Food | Beef Kebab, Cheese Kebab, Shawarma |
| Burger | Fast Food | Beef Burger, Chicken Burger, Fries |
| Phone Accessories | Gadget Accessories | Charger, USB Cable, Phone Case |
| Women's Fashion | Fashion Retail | Dress, Blouse, Tunic, Hijab |
| Tours & Travel | Travel Services | Open Trip, Private Tour, Airport Transfer |
| Umrah | Religious Travel | Umrah Package, Manasik, Travel Documents |
