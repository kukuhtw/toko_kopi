<?php
// English README page for KopiBot.
// This file can be opened directly from public/index.html or embedded using an iframe/link.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KopiBot - AI Agent Commerce Platform</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.7;
            color: #222;
            background: #f7f7f7;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 32px 20px 60px;
            background: #fff;
        }
        h1, h2, h3 {
            line-height: 1.3;
            color: #111;
        }
        h1 {
            font-size: 34px;
            margin-bottom: 8px;
        }
        h2 {
            margin-top: 36px;
            padding-bottom: 8px;
            border-bottom: 1px solid #ddd;
        }
        .lead {
            font-size: 18px;
            color: #444;
        }
        .hero {
            background: #f1f5f9;
            border: 1px solid #d8e0ea;
            border-radius: 12px;
            padding: 22px;
            margin: 22px 0;
        }
        ul {
            padding-left: 22px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 18px 0;
            overflow-x: auto;
            display: block;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            vertical-align: top;
            text-align: left;
        }
        th {
            background: #f1f1f1;
        }
        code {
            background: #f3f3f3;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .contact {
            background: #fafafa;
            border: 1px solid #e5e5e5;
            border-radius: 10px;
            padding: 18px;
        }
        a {
            color: #0f62fe;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>KopiBot - AI Chatbot Order System</h1>
    <p class="lead"><strong>AI Agent Commerce Platform based on PHP Native, MySQL, OpenAI, Anthropic, WhatsApp Gateway, REST API, and LLM AI.</strong></p>

    <div class="hero">
        <h2>AI Agent Commerce Platform</h2>
        <p>KopiBot is an AI commerce platform designed to automate ordering, customer service, customer loyalty, Customer CRM, Customer Portal, chat channel integration, payment gateway integration, delivery connector, POS connector, and multi-branch management for different types of businesses.</p>
        <p>This application was originally developed for coffee shops, then expanded into an AI Agent Commerce platform that can be used for culinary businesses, bakeries, beverage stores, fruit shops, fresh meat stores, vegetable stores, pharmacies, mini marts, retail marts, and other store models that need chat-based ordering, product catalogs, promotions, loyalty, checkout, delivery, and external system integration.</p>
    </div>

    <h2>Features</h2>
    <ul>
        <li>AI Chatbot Order Menu</li>
        <li>WhatsApp, Telegram, and Discord Integration</li>
        <li>Multi Branch Management</li>
        <li>AI Upselling and Promo Recommendation</li>
        <li>Order via Website and Chat Apps</li>
        <li>Variant Product and Topping Support</li>
        <li>Product Photo Upload and AI Image Generation</li>
        <li>Loyalty Point, Redeem Point, and Customer CRM</li>
        <li>Customer Self-Service Dashboard</li>
        <li>Multi Currency, Tax, and Timezone Support</li>
        <li>Menu template plugins for coffee shops, bakeries, fruit stores, fresh meat, vegetables, pharmacies, and marts</li>
        <li>Payment gateway plugins, POS connector, delivery connector, FAQ RAG, complaint handling, and customer support automation</li>
    </ul>

    <h2>Tech Stack</h2>
    <p>PHP Native, MySQL, OpenAI, Anthropic, WhatsApp Gateway, REST API, and LLM AI.</p>

    <h2>Suitable For</h2>
    <p>Coffee Shop, Cafe, Restaurant, Bakery, Beverage Store, Fruit Store, Fresh Meat Market, Vegetable Store, Pharmacy, Mini Mart, Retail Mart, and Specialty Store.</p>

    <h2>Overview</h2>
    <p>KopiBot is a chatbot ordering and AI commerce system built with PHP 8 native, without a large framework. It uses one codebase for multi-business, multi-branch, multi-channel, multi-language, promo engine, loyalty points, Customer CRM, Customer Portal, and plugin system.</p>
    <p>Although the repository name is still <code>toko_kopi</code>, the direction of the application has expanded into a configurable AI Agent Commerce platform for different business verticals such as culinary businesses, pharmacies, and marts.</p>

    <h2>Business Vertical Expansion</h2>
    <p>This application is no longer focused only on coffee shops. Through a plugin system and menu template approach, it can become the foundation for commerce chatbots across several business categories.</p>

    <table>
        <thead>
            <tr>
                <th>Business Vertical</th>
                <th>Example Use Cases</th>
                <th>Feature Support</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Culinary / F&amp;B</strong></td>
                <td>Coffee shop, cafe, restaurant, bakery, beverage store</td>
                <td>Menu ordering, product variants, toppings, promotions, loyalty, upselling, delivery, payment gateway</td>
            </tr>
            <tr>
                <td><strong>Fresh Market</strong></td>
                <td>Fruit store, juice, smoothie, salad, fresh meat, vegetables</td>
                <td>Fresh product menu templates, item catalogs, item pricing, multi-branch support, checkout, customer CRM</td>
            </tr>
            <tr>
                <td><strong>Pharmacy</strong></td>
                <td>Pharmacies, general medicine stores, non-prescription health products, vitamins, light medical equipment</td>
                <td>Product catalog, customer FAQ, complaint handler, CRM, payment gateway, delivery connector</td>
            </tr>
            <tr>
                <td><strong>Mart / Retail</strong></td>
                <td>Mini mart, convenience store, modern grocery store, retail mart</td>
                <td>Large item catalog, cart, promotions, multi-branch support, customer portal, payment gateway, POS connector</td>
            </tr>
            <tr>
                <td><strong>Specialty Store</strong></td>
                <td>Niche product store, community store, small branch store</td>
                <td>Modular plugin, chat channel, admin dashboard, data export, external integration</td>
            </tr>
        </tbody>
    </table>

    <p>The latest plugin features that strengthen this expansion include menu templates, FAQ RAG, complaint handling, additional payment gateways such as iPaymu and Nicepay, Moka POS connector, GoSend delivery connector, SIRCLO connector scaffold, Customer CRM, and Customer Portal. These features allow the application to function as an ordering, support, loyalty, and commerce automation platform across industries, not only as a coffee ordering chatbot.</p>

    <h2>Detailed Feature List</h2>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>AI Chatbot</strong></td>
                <td>Rule-based and LLM-based intent detection for orders, promotions, FAQ, complaints, product recommendations, and customer interactions.</td>
            </tr>
            <tr>
                <td><strong>Multi Business Vertical</strong></td>
                <td>One codebase can be used for coffee shops, restaurants, bakeries, fruit stores, fresh meat stores, vegetable stores, pharmacies, mini marts, and retail marts.</td>
            </tr>
            <tr>
                <td><strong>Multi Branch</strong></td>
                <td>One brand can manage multiple branches with separate menus, promotions, settings, currencies, and timezones.</td>
            </tr>
            <tr>
                <td><strong>Multi Channel</strong></td>
                <td>Website, WhatsApp, Telegram, and Discord with the same chatbot logic.</td>
            </tr>
            <tr>
                <td><strong>Plugin System</strong></td>
                <td>Add features without changing the core code through action and filter hooks.</td>
            </tr>
            <tr>
                <td><strong>Shopping Cart</strong></td>
                <td>Add, edit, remove, clear, promo, loyalty redeem, and session-based checkout.</td>
            </tr>
            <tr>
                <td><strong>Checkout Flow</strong></td>
                <td>The chatbot asks for customer data step by step until the order is ready to be created.</td>
            </tr>
            <tr>
                <td><strong>Checkout Profile Memory</strong></td>
                <td>Customer data such as name, email, WhatsApp number, and address is stored in the browser and automatically filled in during the next checkout.</td>
            </tr>
            <tr>
                <td><strong>Loyalty Point</strong></td>
                <td>Automatic point earning, balance checking, and point redemption through chatbot and web order pages.</td>
            </tr>
            <tr>
                <td><strong>Promo Engine</strong></td>
                <td>Percentage discounts, fixed discounts, promo codes, promo schedules, minimum order rules, and promo recommendations.</td>
            </tr>
            <tr>
                <td><strong>FAQ RAG</strong></td>
                <td>Global and custom branch FAQ, branch override, CSV/XLS import and export, analytics, and local vector store.</td>
            </tr>
            <tr>
                <td><strong>Complaint Handling</strong></td>
                <td>Complaint detection in chat flow, AI vs human follow-up classification, and complaint tickets for branches.</td>
            </tr>
            <tr>
                <td><strong>Payment Gateway</strong></td>
                <td>Midtrans, Xendit, iPaymu, and Nicepay through plugins.</td>
            </tr>
            <tr>
                <td><strong>POS Connector</strong></td>
                <td>Scaffold and live sync queue for Moka Connect or private solutions, inbound webhook sync, and retry runner.</td>
            </tr>
            <tr>
                <td><strong>Delivery Connector</strong></td>
                <td>GoSend partner connector with live-ready endpoint configuration, booking queue, pickup trigger, webhook status, and audit log.</td>
            </tr>
            <tr>
                <td><strong>Menu Management</strong></td>
                <td>CSV upload, size and price variants, toppings, branch-level override, product photo upload, and AI product photo generation.</td>
            </tr>
            <tr>
                <td><strong>Menu Templates</strong></td>
                <td>Ready-to-use menu data template plugins for Coffee Shop, Bakery, Fruit Store, Meat and Vegetables, Pharmacy, and Mart, with seed data and branch-level currency override.</td>
            </tr>
            <tr>
                <td><strong>Dashboard</strong></td>
                <td>Cross-branch super admin, branch admin, Customer CRM, customer loyalty history, and Customer Portal self-service.</td>
            </tr>
            <tr>
                <td><strong>Customer CRM</strong></td>
                <td>Customer identity normalization based on email and WhatsApp, loyalty notifications, and branch-level CRM logs.</td>
            </tr>
            <tr>
                <td><strong>Customer Portal</strong></td>
                <td>Light customer login using contact details and order number to check order history, loyalty, profile, and repeat order.</td>
            </tr>
            <tr>
                <td><strong>HTML Documentation</strong></td>
                <td>README and Markdown documentation are also available as HTML pages.</td>
            </tr>
            <tr>
                <td><strong>CSV Export</strong></td>
                <td>Export orders, menus, promotions, and related dashboard data.</td>
            </tr>
        </tbody>
    </table>

    <h2>README Update Note</h2>
    <p>This README has been updated to explain the new direction of the application as a multi-vertical AI Agent Commerce platform. The added information reflects the latest plugin features that are already available or prepared in the plugin architecture, including chat channels, payment gateways, POS connector, delivery connector, FAQ RAG, complaint handler, Customer CRM, Customer Portal, and menu templates for different business types.</p>

    <h2>Demo</h2>
    <p><a href="https://botlelang.com/toko_kopi" target="_blank" rel="noopener">https://botlelang.com/toko_kopi</a></p>

    <h2>Repository</h2>
    <p><a href="https://github.com/kukuhtw/toko_kopi" target="_blank" rel="noopener">https://github.com/kukuhtw/toko_kopi</a></p>

    <h2>Developer</h2>
    <div class="contact">
        <p><strong>Created and developed by:</strong> Kukuh TW</p>
        <p>Email: <a href="mailto:kukuhtw@gmail.com">kukuhtw@gmail.com</a></p>
        <p>WhatsApp: <a href="https://wa.me/628129893706" target="_blank" rel="noopener">https://wa.me/628129893706</a></p>
        <p>Instagram: @kukuhtw</p>
        <p>X/Twitter: @kukuhtw</p>
        <p>GitHub: <a href="https://github.com/kukuhtw/toko_kopi" target="_blank" rel="noopener">https://github.com/kukuhtw/toko_kopi</a></p>
        <p>Facebook: <a href="https://www.facebook.com/kukuhtw" target="_blank" rel="noopener">https://www.facebook.com/kukuhtw</a></p>
        <p>LinkedIn: <a href="https://linkedin.com/in/kukuhtw" target="_blank" rel="noopener">https://linkedin.com/in/kukuhtw</a></p>
    </div>

    <p>Copyright 2026 Kukuh TW. All rights reserved.</p>
</div>
</body>
</html>
