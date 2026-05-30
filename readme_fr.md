# KopiBot - Système de commande par chatbot IA

> ## Plateforme AI Agent Commerce
>
> KopiBot est une plateforme de commerce basée sur l'IA pour automatiser les commandes, le service client, la fidélisation client, le Customer CRM, le Customer Portal, l'intégration des canaux de chat, l'intégration des passerelles de paiement, le connecteur de livraison, le connecteur POS et la gestion multi-succursales pour différents types d'entreprises.
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
> - Plugins de modèles de menus pour coffee shops, boulangeries, boutiques de fruits, viande fraîche, légumes, pharmacies et marts
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

KopiBot est un système de commande par chatbot et une plateforme de commerce IA construits avec PHP 8 natif, sans grand framework. Il utilise une seule base de code pour le multi-business, le multi-succursales, le multi-canal, le multilingue, le moteur de promotions, les points de fidélité, le Customer CRM, le Customer Portal et le système de plugins. Même si le nom du dépôt reste `toko_kopi`, l'orientation de l'application a été élargie en une plateforme AI Agent Commerce configurable pour différents secteurs d'activité comme la restauration, la pharmacie et les marts.

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
| **Chatbot IA** | Détection d'intention basée sur des règles et sur LLM pour les commandes, promotions, FAQ, réclamations, recommandations de produits et interactions client |
| **Multi verticale métier** | Une seule base de code peut être utilisée pour les coffee shops, restaurants, boulangeries, boutiques de fruits, magasins de viande fraîche, magasins de légumes, pharmacies, mini marts et retail marts |
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
| **Modèles de menus** | Plugins de modèles de données de menus prêts à l'emploi : Coffee Shop, Boulangerie, Boutique de fruits, Viande & Légumes, Pharmacie et Mart, avec seed data et override de devise par succursale |
| **Tableau de bord** | Super admin multi-succursales, admin par succursale, Customer CRM, historique de fidélité client et Customer Portal self-service |
| **Customer CRM** | Normalisation de l'identité client basée sur email/WhatsApp, notifications de fidélité et logs CRM par succursale |
| **Customer Portal** | Connexion client légère via informations de contact + numéro de commande pour consulter l'historique des commandes, la fidélité, le profil et refaire une commande |
| **Documentation HTML** | README et documentation Markdown disponibles également sous forme de pages HTML |
| **Export CSV** | Export des commandes, menus, promotions et données liées au tableau de bord |

---

## Note de mise à jour du README

Ce README a été mis à jour pour expliquer la nouvelle orientation de l'application comme plateforme AI Agent Commerce multi-verticale. Les informations ajoutées suivent les dernières fonctionnalités de plugins déjà disponibles ou préparées dans l'architecture plugin, notamment les canaux de chat, les passerelles de paiement, le connecteur POS, le connecteur de livraison, FAQ RAG, la gestion des réclamations, Customer CRM, Customer Portal et les modèles de menus pour différents types d'entreprises.
