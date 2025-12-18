# 📧 Nouvelle fonctionnalité : Ajout manuel d'emails dans les segments

## ✨ Fonctionnalités ajoutées

### 1. **Liste d'emails manuelle**
- Section dédiée en haut du formulaire de création/édition de segment
- Possibilité d'ajouter des emails ligne par ligne
- Si des emails manuels sont présents, **tous les autres filtres sont ignorés**

### 2. **Recherche d'emails** 🔍
- **Bouton "Rechercher des emails"** : Ouvre une modale de recherche
  - Recherche en temps réel (min. 2 caractères)
  - Recherche par nom, prénom ou email
  - Affiche les résultats avec nombre de commandes
  - Sélection multiple via checkboxes
  - Ajout des emails sélectionnés au textarea

### 3. **Chargement en masse** 📥
- **Bouton "Charger tous les emails clients"**
  - Charge automatiquement TOUS les emails clients de la base
  - Limité à 5000 emails pour éviter les problèmes de performance
  - Triés par nombre de commandes (décroissant)

### 4. **Prévisualisation intelligente** 👁️
- Affiche un bandeau bleu indiquant "Mode liste manuelle activé"
- Vérifie la validité de chaque email (✅ Valide / ⚠️ Invalide)
- Compte et affiche le nombre total d'emails

### 5. **Affichage dans la liste des segments**
- Les segments avec liste manuelle affichent : `📧 Liste manuelle (X emails)`
- Différenciation claire des segments manuels vs filtres automatiques

## 🔧 Modifications techniques

### Fichiers modifiés :

1. **`class-wp-mail-sender-segments.php`**
   - Ajout de la section UI pour les emails manuels
   - Nouvelle modale de recherche avec JavaScript
   - Handler AJAX `ajax_search_emails()`
   - Fonction `get_manual_recipients()` pour parser les emails
   - Mise à jour de la sauvegarde et prévisualisation

2. **`class-wp-mail-sender-db.php`**
   - Nouvelle méthode `get_manual_email_recipients($manual_emails_string)`
   - Traitement par batch (100 emails à la fois)
   - Enrichissement avec données client si disponibles en BDD
   - Support des emails externes (non présents en base)

### Fonctionnement :

```php
// Priorité dans get_segment_recipients()
if (!empty($filters['manual_emails'])) {
    return $this->get_manual_email_recipients($filters['manual_emails']);
}
// Sinon, filtres classiques...
```

## 📋 Utilisation

### Ajouter des emails manuellement :

1. Aller dans **Segments** → **Créer un segment**
2. Section **"Liste d'emails manuelle"**
3. Saisir les emails (un par ligne) OU utiliser les boutons :
   - 🔍 **Rechercher** : Taper un nom/email → cocher → ajouter
   - 📥 **Charger tous** : Importer tous les clients

### Recherche d'emails :

1. Cliquer sur **"🔍 Rechercher des emails"**
2. Taper au moins 2 caractères dans la zone de recherche
3. Les résultats s'affichent instantanément :
   ```
   ☑️ Jean Dupont
       jean.dupont@email.com (3 commandes)
   ```
4. Cocher les emails souhaités
5. Cliquer sur **"✅ Ajouter les emails sélectionnés"**

### Validation :

- La prévisualisation affiche le statut de chaque email :
  - ✅ **Valide** : Format email correct
  - ⚠️ **Invalide** : Format incorrect (typo, etc.)

## ⚡ Performance

- **Recherche** : Limitée à 100 résultats max
- **Chargement total** : 5000 emails max
- **Envoi** : Traitement par batch de 100 emails
- **Base de données** : Requêtes optimisées avec GROUP BY et index

## 🎯 Cas d'usage

1. **Liste VIP** : Ajouter manuellement les meilleurs clients
2. **Export externe** : Importer une liste d'emails depuis un fichier Excel
3. **Test ciblé** : Créer une petite liste de test avant envoi massif
4. **Événement spécial** : Inviter une sélection précise de clients

## 🔒 Sécurité

- Validation des emails avec `filter_var($email, FILTER_VALIDATE_EMAIL)`
- Sanitisation via `sanitize_textarea_field()`
- Nonces AJAX pour toutes les requêtes
- Vérification des permissions `current_user_can('manage_options')`

## 📊 Logging

Les logs incluent maintenant :
```
[WP Mail Sender DB DEBUG] Processing 45 manual emails
[WP Mail Sender DB DEBUG] get_manual_email_recipients found/created 45 recipients
[WP Mail Sender Segments] Preview called with filters: manual_emails => "email1@..."
```

---

**Version** : 1.2.0  
**Date** : 18 décembre 2025  
**Compatibilité** : WordPress 5.0+, WooCommerce 3.0+
