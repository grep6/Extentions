# WP Social Mail Sender - Multi-SMTP Edition

Extension WordPress pour envoyer des emails depuis **plusieurs adresses SMTP différentes** avec sélection automatique basée sur l'adresse "From".

## 📋 Fonctionnalités

- **Multi-SMTP Support** - Configurez plusieurs adresses email avec des serveurs SMTP différents
- **Sélection Automatique** - Le plugin choisit automatiquement le bon SMTP basé sur l'adresse From
- **Configuration Sécurisée** - Stockez les mots de passe dans `wp-config.php` (pas en base de données)
- **Interface Admin Intuitive** - Tableau de bord pour gérer et tester les configurations
- **Envoi Manuel** - Formulaire pour envoyer des emails custom directement depuis l'admin
- **Logging Détaillé** - Suivi complet de tous les envois

## 🔧 Installation

1. Déposez le dossier `wp-social-mail-sender` dans `/wp-content/plugins/`
2. Activez le plugin dans l'administration WordPress
3. Configurez vos comptes SMTP (voir ci-dessous)

## ⚙️ Configuration

### 1. Ajouter les comptes SMTP

Éditez `wp-social-mail-sender.php` et modifiez la configuration :

```php
$wp_social_mail_sender_smtp_config = array(
    'social@tabac-des-battieres.com' => array(
        'host' => 'mail.tabac-des-battieres.com',
        'port' => 465,
        'user' => 'social@tabac-des-battieres.com',
        'secure' => 'ssl'
    ),
    'contact@example.com' => array(
        'host' => 'smtp.example.com',
        'port' => 587,
        'user' => 'contact@example.com',
        'secure' => 'tls'
    ),
);
```

### 2. Stocker les mots de passe (sécurisé)

Ajoutez ceci à votre `wp-config.php` **avant la ligne** `require_once ABSPATH . 'wp-settings.php';` :

```php
// SMTP Passwords - WP Social Mail Sender
define('WP_SOCIAL_MAIL_SENDER_PASSWORD_SOCIAL_TABAC_DES_BATTIERES_COM', 'votre-mot-de-passe-1');
define('WP_SOCIAL_MAIL_SENDER_PASSWORD_CONTACT_EXAMPLE_COM', 'votre-mot-de-passe-2');
```

**Règle de nommage :** `WP_SOCIAL_MAIL_SENDER_PASSWORD_` + email en MAJUSCULES avec `@` et `.` remplacés par `_`

## 📧 Utilisation

### Via l'interface Admin

1. Allez dans **Social Mail Sender** dans le menu de gauche
2. Utilisez la section **"Send Email"** pour envoyer un email
3. Sélectionnez l'adresse "From" et remplissez le message
4. Appuyez sur "Send Email"

### Via Code WordPress

```php
// Envoyer depuis social@tabac-des-battieres.com
wp_mail(
    'destinataire@example.com',
    'Sujet',
    '<p>Message HTML</p>',
    array('From: social@tabac-des-battieres.com')
);

// Envoyer depuis contact@example.com
wp_mail(
    'destinataire@example.com',
    'Sujet',
    '<p>Message HTML</p>',
    array('From: contact@example.com')
);
```

**Le plugin sélectionne automatiquement le bon serveur SMTP basé sur l'adresse From !**

## 🧪 Test des Connexions

1. Allez dans **Social Mail Sender**
2. Cliquez sur le bouton **"Test"** à côté de chaque adresse email
3. Un email de test sera envoyé à votre adresse administrateur
4. Un message confirmera le succès ou l'erreur

## 📊 Tableau de Bord

Le tableau de bord affiche :
- ✅ **Tous les comptes configurés**
- 🔒 **Statut des mots de passe** (Configuré ✓ / Manquant ✗)
- 🧪 **Boutons de test** pour chaque compte
- 📝 **Formulaire d'envoi** d'emails

## 🔍 Fichiers de Log

Les envois d'email sont enregistrés dans `/wp-content/debug.log` (si `WP_DEBUG_LOG` est activé).

Exemples :
```
[28-Dec-2025 10:15:30 UTC] [WP Social Mail Sender SMTP] Processing email from: social@tabac-des-battieres.com
[28-Dec-2025 10:15:31 UTC] [WP Social Mail Sender SMTP] ✓ Connection test SUCCESSFUL - test email sent
```

## 📦 Structure

```
wp-social-mail-sender/
├── wp-social-mail-sender.php              # Fichier principal
├── README.md                               # Documentation
├── admin/
│   ├── class-wp-social-mail-sender-admin.php
│   ├── class-wp-social-mail-sender-send.php
│   ├── css/admin.css
│   └── js/admin.js
└── includes/
    ├── class-wp-social-mail-sender-core.php
    └── class-wp-social-mail-sender-smtp.php
```

## 🛠️ Développement

### Ajouter un nouveau compte SMTP

1. Éditez `wp-social-mail-sender.php`
2. Ajoutez une nouvelle entrée à `$wp_social_mail_sender_smtp_config`
3. Ajoutez le mot de passe à `wp-config.php`
4. Testez via l'interface admin

### Utiliser les filtres

```php
// Modifier la configuration SMTP (avant d'envoyer)
apply_filters('wp_social_mail_sender_config', $config);

// Modifier le corp de l'email avant envoi
apply_filters('wp_social_mail_sender_body', $body);
```

## ⚠️ Important

- Le mot de passe doit être défini (soit en `wp-config.php` soit en base de données)
- Les emails sont envoyés de manière synchrone (bloquante)
- Assurez-vous que les credentials SMTP sont corrects avant production
- Les mots de passe en `wp-config.php` sont plus sécurisés

## 📝 Licence

GPL-2.0+

## 👤 Auteur

Antonin pour Tabac des Battières
