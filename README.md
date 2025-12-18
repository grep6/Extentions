# Hub Extensions WordPress
> Ensemble de plugins WordPress développés pour l'alternance

## 📊 Vue d'ensemble

Ce dépôt regroupe plusieurs extensions WordPress développées pour améliorer les fonctionnalités de WooCommerce et la gestion des emails.

---

## 🚀 Projets

### 1. WP Mail Sender
**📁 Dossier :** `wp-mail-sender/`  
**📅 Date de création :** Novembre 2024  
**🔧 Dernière maintenance :** Décembre 2024  
**📌 Version :** 1.1.0

#### Description
Extension d'envoi d'emails via SMTP avec gestion de templates et listes de diffusion. Permet la création de campagnes d'emailing personnalisées avec segmentation des destinataires.

#### Fonctionnalités principales
- Configuration SMTP avec détection automatique de l'environnement (staging/production)
- Gestion de listes de diffusion
- Création et gestion de templates d'emails
- Segmentation des destinataires
- Envoi de campagnes d'emailing
- Interface d'administration complète

#### Technologies utilisées
- **Backend :** PHP 7.4+
- **Frontend :** JavaScript (ES6), CSS3
- **CMS :** WordPress 5.8+
- **Dépendances :** WooCommerce 6.0+

---

### 2. WC KPI Cohortes (Version JSON)
**📁 Dossier :** `wc-kpi-cohortes-v-json/`  
**📅 Date de création :** Octobre 2024  
**🔧 Dernière maintenance :** Novembre 2024  
**📌 Version :** 0.3

#### Description
Dashboard admin pour analyser les commandes WooCommerce par code postal et rue avec visualisation cartographique 3D isométrique de la France.

#### Fonctionnalités principales
- Analyse des commandes par zones géographiques
- Formulaire d'analyse avec filtres avancés (dates, produits, statuts)
- Graphiques interactifs (histogrammes, courbes)
- Carte OpenStreetMap avec visualisation des commandes
- Géocodage automatique avec cache
- Shortcode `[tdb_kpis]` pour affichage frontend
- Widget tableau de bord WordPress

#### Technologies utilisées
- **Backend :** PHP 7.4+
- **Frontend :** JavaScript (ES6), Leaflet.js, Chart.js
- **APIs :** Nominatim (OpenStreetMap)
- **Base de données :** MySQL (tables WooCommerce)
- **CMS :** WordPress 5.8+, WooCommerce 6.0+

---

### 3. Tri Segmentation
**📁 Dossier :** `Tri_segmentation/`  
**📅 Date de création :** Septembre 2024  
**🔧 Dernière maintenance :** Octobre 2024

#### Description
Collection de variantes du système KPI Cohortes pour la segmentation et l'indexation des données clients.

#### Sous-projets

##### 3.1 Index KPI - Version Originale
**📂 Sous-dossier :** `Tri_segmentation/indexKPI/kpi-index-vo/`  
**📌 Version :** 0.0 (Version initiale)

- Dashboard d'analyse des commandes par code postal
- Carte 3D isométrique de la France
- Système de cache pour géocodage

##### 3.2 KPI Index VO
**📂 Sous-dossier :** `Tri_segmentation/kpi-index-vo/`  
**📌 Version :** Développement

- Variante optimisée du système d'indexation
- Focus sur la performance des requêtes

#### Technologies utilisées
- **Backend :** PHP 7.4+
- **Frontend :** JavaScript, CSS3
- **CMS :** WordPress 5.8+, WooCommerce 6.0+

---

## 💻 Stack technique globale

| Technologie | Version | Usage |
|------------|---------|-------|
| PHP | 7.4+ | Backend WordPress |
| JavaScript | ES6+ | Frontend interactif |
| WordPress | 5.8+ | CMS principal |
| WooCommerce | 6.0+ | E-commerce |
| MySQL | 5.7+ | Base de données |
| Leaflet.js | 1.9+ | Cartographie |
| Chart.js | 3.0+ | Graphiques |
| CSS3 | - | Styles |

---

## 📦 Installation générale

### Prérequis
- WordPress 5.8 ou supérieur
- WooCommerce 6.0 ou supérieur
- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur

### Procédure d'installation
1. Copier le dossier du plugin souhaité dans `wp-content/plugins/`
2. Activer le plugin depuis l'administration WordPress
3. Configurer les paramètres selon la documentation de chaque plugin

---

## 🔒 Sécurité

Tous les plugins incluent :
- Vérifications `ABSPATH` pour empêcher l'accès direct
- Validation et échappement des données
- Nonces pour les actions AJAX
- Vérification des permissions utilisateur

---

## 📝 Notes de développement

### Environnements
- **Production :** tabac-des-battieres.com
- **Staging :** staging.tabac-des-battieres.com

### Conventions de code
- Standards WordPress Coding Standards
- Préfixes de fonctions : `wp_mail_sender_`, `wc_kpi_`
- Classes PHP en CamelCase
- Fonctions en snake_case

---

## 📄 Licence

GPL-2.0+ - Tous droits réservés

---

## 👤 Auteur

**Antonin**  
Développé dans le cadre de l'alternance pour Tabac des Battières

---

## 📅 Historique des versions

| Date | Projet | Version | Changements |
|------|--------|---------|-------------|
| Déc 2024 | WP Mail Sender | 1.1.0 | Dernière maintenance |
| Nov 2024 | WC KPI Cohortes | 0.3 | Ajout shortcode et widget |
| Oct 2024 | Tri Segmentation | 0.0 | Versions initiales |

---

## 🚧 Roadmap

### WP Mail Sender
- [ ] Statistiques d'ouverture des emails
- [ ] A/B testing de templates
- [ ] Intégration avec services tiers

### WC KPI Cohortes
- [ ] Implémentation complète 3D avec Three.js
- [ ] Export des données en CSV/Excel
- [ ] Filtres avancés supplémentaires
- [ ] API REST pour intégrations tierces

