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
> - Menu template plugins for coffee shops, bakeries, fruit stores, fresh meat, vegetables, pharmacies, and marts
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

KopiBot is a chatbot ordering and AI commerce system built with PHP 8 native, without a large framework. It uses one codebase for multi-business, multi-branch, multi-channel, multi-language, promo engine, loyalty points, Customer CRM, Customer Portal, and plugin system. Although the repository name is still `toko_kopi`, the direction of the application has expanded into a configurable AI Agent Commerce platform for various business verticals such as culinary businesses, pharmacies, and marts.

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
| **AI Chatbot** | Rule-based and LLM-based intent detection for orders, promotions, FAQ, complaints, product recommendations, and customer interactions |
| **Multi Business Vertical** | One codebase can be used for coffee shops, restaurants, bakeries, fruit stores, fresh meat stores, vegetable stores, pharmacies, mini marts, and retail marts |
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
| **Menu Templates** | Ready-to-use menu data template plugins: Coffee Shop, Bakery, Fruit Store, Meat & Vegetables, Pharmacy, and Mart, with seed data and branch-level currency override |
| **Dashboard** | Cross-branch super admin, branch admin, Customer CRM, customer loyalty history, and Customer Portal self-service |
| **Customer CRM** | Customer identity normalization based on email/WhatsApp, loyalty notifications, and branch-level CRM logs |
| **Customer Portal** | Lightweight customer login using contact information + order number to check order history, loyalty, profile, and repeat order |
| **HTML Documentation** | README and Markdown documentation are also available as HTML pages |
| **Export CSV** | Export orders, menus, promotions, and related dashboard data |

---

## README Update Note

This README has been updated to explain the new direction of the application as a multi-vertical AI Agent Commerce platform. The added information follows the latest plugin features that are already available or prepared in the plugin architecture, including chat channels, payment gateway, POS connector, delivery connector, FAQ RAG, complaint handler, Customer CRM, Customer Portal, and menu templates for different business types.
