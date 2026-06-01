# KopiBot - Système de commande par chatbot IA

> ## Plateforme AI Agent Commerce
>
> KopiBot est une plateforme de commerce basée sur l'IA pour automatiser les commandes, le service client, la fidélisation client, le Customer CRM, le Customer Portal, l'intégration des canaux de chat, l'intégration des passerelles de paiement, le connecteur de livraison, le connecteur POS et la gestion multi-succursales pour différents types d'entreprises.
>
> **Langues de documentation :**
> - [README Indonésien](README.md)
> - [English README](readme_en.md)
>
> Cette application a été initialement développée pour les coffee shops, puis élargie en une plateforme AI Agent Commerce pouvant être utilisée par les entreprises culinaires, les boulangeries, les magasins de boissons, les boutiques de fruits, les magasins de viande fraîche, les commerces de légumes, les pharmacies, les mini marts, les retail marts et d'autres modèles de magasins qui ont besoin de commandes basées sur le chat, de catalogues produits, de promotions, de fidélisation, de checkout, de livraison et d'intégration avec des systèmes externes.
>
> ### Fonctionnalités
> - Menu de commande via chatbot IA
> - Intégration WhatsApp / Telegram / Discord
> - Gestion multi-succursales
> - Upselling IA et recommandation de promotions
> - Commande via site web et applications de chat
> - Support des variantes de produits et des toppings
> - Téléversement de photos produits et génération d'images par IA
> - Points de fidélité, échange de points et Customer CRM
> - Tableau de bord self-service pour les clients
> - Multi-devise, taxe et fuseau horaire
> - Plugins de modèles de menus pour coffee shops, boulangeries, boutiques de fruits, fresh market, pharmacies, marts, mode, accessoires téléphone, tours & travel et umrah
> - Plugins de passerelle de paiement, connecteur POS, connecteur de livraison, FAQ RAG, gestion des réclamations et automatisation du support client
>
> ### Stack Technique
> PHP Native - MySQL - OpenAI - Anthropic
> WhatsApp Gateway - REST API - LLM AI
>
> ### Adapté pour
> Coffee Shop - Café - Restaurant - Boulangerie - Magasin de boissons - Boutique de fruits - Marché de viande fraîche - Magasin de légumes - Pharmacie - Mini Mart - Retail Mart - Magasin spécialisé
>
> Créé et développé par :
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
> Démo :
> https://botlelang.com/toko_kopi
>
> Copyright 2026 Kukuh TW. Tous droits réservés.

KopiBot est conçu pour les entreprises qui veulent un système de commande, de support client, de fidélisation et de catalogue digital qu'elles peuvent réellement maîtriser. Il repose sur PHP 8 natif, sans grand framework, et utilise une seule base de code pour le multi-business, le multi-succursales, le multi-canal, le multilingue, le moteur de promotions, les points de fidélité, le Customer CRM, le Customer Portal et le système de plugins. Même si le nom du dépôt reste `toko_kopi`, l'orientation du produit a été élargie vers une plateforme AI Agent Commerce configurable pour la restauration, la pharmacie, le retail, le voyage et les services basés sur la réservation.

## Problèmes Résolus

De nombreuses petites et moyennes entreprises veulent gérer les commandes depuis leur site web, WhatsApp et d'autres canaux de chat, mais leurs opérations sont souvent dispersées dans des outils qui ne communiquent pas entre eux. Le catalogue est à un endroit, les promotions à un autre, l'historique client est fragmenté, la fidélité manque de cohérence et les équipes en succursale ont du mal à obtenir une vue complète du client.

Un autre problème fréquent vient des solutions instantanées trop génériques. Dès qu'une entreprise a besoin d'un parcours de checkout spécifique, de règles promotionnelles propres à chaque succursale, d'une intégration de paiement particulière ou d'un modèle produit adapté à sa verticale métier, les outils génériques deviennent vite limitants. Même de petits ajustements peuvent dépendre du fournisseur, augmenter les coûts récurrents ou enfermer les données métier dans une plateforme tierce.

## La Solution

KopiBot est pensé comme une base AI Agent Commerce que l'entreprise ou son équipe technique peut installer, posséder et faire évoluer. L'objectif n'est pas seulement de faire répondre un chatbot, mais de prendre en charge le flux commerce de bout en bout :

- capter l'intention client depuis le chat ou la commande web
- afficher des catalogues et variantes produits adaptés à chaque succursale
- activer automatiquement l'upselling, les promotions et la fidélité
- stocker un historique client réutilisable dans le CRM
- connecter le checkout au paiement, à la livraison, au POS ou à d'autres workflows opérationnels

Du point de vue de l'implémentation, la plateforme est volontairement modulaire. Une même marque peut gérer plusieurs succursales, canaux, types de catalogues et intégrations sans devoir fragmenter le système en plusieurs applications séparées.

## Pourquoi Pas un SaaS Classique ?

Cette plateforme se distingue d'un SaaS commerce générique parce qu'elle met l'accent sur le contrôle et l'extensibilité. Dans un modèle SaaS, les entreprises s'adaptent souvent au workflow défini par le fournisseur. Lorsqu'un besoin spécifique apparaît, les options sont généralement limitées : attendre la roadmap du fournisseur, payer un add-on supplémentaire, ou accepter un compromis opérationnel.

Avec KopiBot, l'entreprise ou l'équipe technique interne peut :

- auto-héberger le système et garder un accès complet à la base de données et au code
- ajuster les workflows de checkout, les prompts IA, la logique CRM, les promotions et les règles par succursale
- ajouter de nouvelles intégrations sans attendre un fournisseur central
- créer des modèles d'installation adaptés à différents verticales métiers

Cette approche convient aux agences, software houses, opérateurs multi-succursales ou entreprises qui veulent construire un actif numérique de long terme plutôt que louer le même panneau SaaS que tout le monde.

## Plugins & Extensions

L'architecture plugin est l'un des piliers du projet. Les nouvelles fonctionnalités n'ont pas besoin d'être ajoutées directement dans le core. Grâce aux hooks action et filter, les plugins peuvent étendre le comportement de l'application avec moins de risque pour la base principale, ce qui rend les montées de version et les expérimentations plus sûres.

Quelques catégories d'extensions déjà supportées :

- plugins de modèles produits et services pour injecter un catalogue initial à l'installation
- plugins de passerelles de paiement comme Midtrans, Xendit, iPaymu et Nicepay
- plugins de connecteurs POS comme Moka Connect / Private Solution
- plugins de connecteurs de livraison comme GoSend
- plugins de knowledge et support comme FAQ RAG et complaint handling
- plugins de branding et de thème pour le nom du magasin, l'icône de marque, la tagline et l'apparence visuelle

Ce modèle permet à chaque déploiement d'assembler une pile fonctionnelle différente. Une installation peut servir de bot de commande pour coffee shop, une autre de pharmacie digitale, de minimarket, d'assistant de réservation voyage ou de portail umrah, tout cela sur la même fondation mais avec une composition de plugins différente.

---

## Extension des verticales métiers

Cette application ne se concentre plus uniquement sur les coffee shops. Grâce à l'approche par système de plugins et modèles de menus, l'application peut devenir la base de chatbots commerce pour plusieurs catégories d'entreprises.

| Verticale métier | Exemples d'utilisation | Support fonctionnel |
|----------|--------|----------|
| **Culinaire / F&B** | Coffee shop, café, restaurant, boulangerie, magasin de boissons | Commande de menu, variantes de produits, toppings, promotions, fidélité, upselling, livraison, passerelle de paiement |
| **Fresh Market** | Boutique de fruits, jus, smoothie, salade, viande fraîche, légumes | Modèles de menus de produits frais, catalogue d'articles, prix par article, support multi-succursales, checkout, Customer CRM |
| **Pharmacie** | Pharmacies, magasins de médicaments généraux, produits de santé sans ordonnance, vitamines, petits équipements médicaux | Catalogue produits, FAQ client, gestion des réclamations, CRM, passerelle de paiement, connecteur de livraison |
| **Mart / Retail** | Mini mart, convenience store, épicerie moderne, retail mart | Grand catalogue d'articles, panier, promotions, support multi-succursales, portail client, passerelle de paiement, connecteur POS |
| **Magasin spécialisé** | Boutique de produits de niche, boutique communautaire, petite succursale | Plugins modulaires, canaux de chat, tableau de bord admin, export de données, intégration externe |

Les dernières fonctionnalités de plugins qui renforcent cette extension incluent les modèles de menus, FAQ RAG, gestion des réclamations, passerelles de paiement supplémentaires comme iPaymu et Nicepay, connecteur Moka POS, connecteur de livraison GoSend, scaffold du connecteur SIRCLO, Customer CRM et Customer Portal. Cette combinaison de fonctionnalités permet d'utiliser l'application comme plateforme d'automatisation des commandes, du support, de la fidélité et du commerce dans plusieurs industries, et non seulement comme chatbot de commande de café.

---

## Fonctionnalités

| Catégorie | Détails |
|----------|--------|
| **Chatbot IA** | Détection multi-intention basée sur des règles et sur LLM — un seul message client peut déclencher et traiter plusieurs intentions simultanément (ex. commande + demande de promotion). `detectAll()` détecte toutes les intentions, `filterIntents()` supprime le bruit, `dispatchAll()` exécute chacune séquentiellement et regroupe toutes les réponses |
| **Multi verticale métier** | Une seule base de code peut être utilisée pour les coffee shops, restaurants, boulangeries, boutiques de fruits, magasins de viande fraîche, magasins de légumes, pharmacies, mini marts et retail marts. Le paramètre `business_type` dans `branch_settings` adapte automatiquement tous les prompts LLM (détecteur d'intention, assistant menu, assistant promo, assistant FAQ) au contexte métier |
| **Multi-succursales** | Une marque peut gérer plusieurs succursales avec des menus, promotions, paramètres, devises et fuseaux horaires séparés |
| **Multi-canal** | Site web, WhatsApp, Telegram et Discord avec la même logique de chatbot |
| **Système de plugins** | Ajouter des fonctionnalités sans modifier le code principal grâce aux hooks action/filter |
| **Panier d'achat** | Ajouter, modifier, supprimer, vider, appliquer des promotions, échanger des points de fidélité et checkout via session |
| **Flux de checkout** | Le chatbot demande les données client étape par étape jusqu'à ce que la commande soit prête à être créée |
| **Mémoire du profil checkout** | Les données client comme le nom, l'email, le numéro WhatsApp et l'adresse sont stockées dans le navigateur et remplies automatiquement lors du checkout suivant |
| **Points de fidélité** | Gagner automatiquement des points, vérifier le solde et échanger des points via le chatbot et la page de commande web |
| **Moteur de promotions** | Remises en pourcentage, remises nominales, codes promo, calendrier de promotions, minimum de commande et recommandations de promotions |
| **FAQ RAG** | FAQ globale et FAQ personnalisée par succursale, override par branche, import/export CSV/XLS, analytics et vector store local |
| **Gestion des réclamations** | Détecter les réclamations dans le flux de chat, classifier le suivi IA vs humain et créer des tickets de réclamation pour les succursales |
| **Passerelle de paiement** | Midtrans, Xendit, iPaymu et Nicepay via plugins |
| **Connecteur POS** | Scaffold et file de synchronisation live pour Moka Connect / Private Solution, synchronisation webhook entrant et retry runner |
| **Connecteur de livraison** | Connecteur partenaire GoSend avec configuration endpoint prête pour production, file de réservation, déclencheur pickup, statut webhook et journal d'audit |
| **Gestion des menus** | Téléversement CSV, variantes de taille/prix, toppings, override par succursale, téléversement de photos produits et génération de photos produits par IA |
| **Modèles de menus** | Plugins de modèles de données prêts à l'emploi : Coffee Shop, Boulangerie, Boutique de fruits, Viande & Légumes, Pharmacie, Mart, Warung, Baso, Kebab, Burger, Accessoires téléphone, Mode femme, Tours & Travel et Umrah |
| **Tableau de bord** | Super admin multi-succursales, admin par succursale, Customer CRM, historique de fidélité client et Customer Portal self-service |
| **Customer CRM** | Normalisation de l'identité client basée sur email/WhatsApp, notifications de fidélité et logs CRM par succursale |
| **Customer Portal** | Connexion client légère via informations de contact + numéro de commande pour consulter l'historique des commandes, la fidélité, le profil et refaire une commande |
| **Documentation HTML** | README et documentation Markdown disponibles également sous forme de pages HTML |
| **Export CSV** | Export des commandes, menus, promotions et données liées au tableau de bord |

---

## Note de mise à jour du README

Ce README a été mis à jour pour expliquer la nouvelle orientation de l'application comme plateforme AI Agent Commerce multi-verticale. Les informations ajoutées suivent les dernières fonctionnalités de plugins déjà disponibles ou préparées dans l'architecture plugin, notamment les canaux de chat, les passerelles de paiement, le connecteur POS, le connecteur de livraison, FAQ RAG, la gestion des réclamations, Customer CRM, Customer Portal et les modèles de menus pour différents types d'entreprises.

## Modèles de Produits Disponibles

L'installateur propose actuellement les modèles de produits et services prêts à être injectés suivants :

| Modèle | Catégorie | Exemples de produits / services |
| --- | --- | --- |
| Menu café par défaut | Coffee Shop | Espresso, Cappuccino, Cafe Latte, Americano |
| Coffee Shop | Café & boissons | Signature Coffee, Matcha Latte, Croffle |
| Boulangerie | Boulangerie & pâtisserie | Croissant, Pain au lait, Cinnamon Roll |
| Boutique de fruits | Fruits frais | Mangue, Orange, Pomme, Pack fruits |
| Viande & Légumes | Fresh market | Bœuf, Poulet, Épinards, Carotte |
| Pharmacie / Apotek | Santé & médicaments | Médicament fièvre, Vitamines, Antiseptique |
| Minimarket | Produits du quotidien | Riz, Nouilles instantanées, Eau minérale |
| Resto Indonesia | Restaurant indonésien | Nasi Goreng, Ayam Geprek, Thé glacé |
| Warung | Petite restauration | Menu riz, Snacks frits, Thé chaud |
| Resto Baso & Minuman | Boulettes & boissons | Baso Urat, Baso Telur, Orange glacée |
| Kebab | Restauration rapide | Kebab bœuf, Kebab fromage, Shawarma |
| Burger | Restauration rapide | Burger bœuf, Burger poulet, Frites |
| Accessoires téléphone | Accessoires gadget | Chargeur, Câble USB, Coque téléphone |
| Mode femme | Mode | Robe, Blouse, Tunique, Hijab |
| Tours & Travel | Services de voyage | Open Trip, Private Tour, Airport Transfer |
| Umrah | Voyage religieux | Pack Umrah, Manasik, Documents de voyage |
