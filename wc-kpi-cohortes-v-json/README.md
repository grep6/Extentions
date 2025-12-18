WC KPI Cohortes
================

Plugin WordPress minimal pour analyser les commandes WooCommerce par code postal / rue et afficher une carte basée sur OpenStreetMap.

Installation rapide
- Copier le dossier `wp-wc-kpi-cohortes` dans `wp-content/plugins/`
- Activer le plugin depuis l'administration WordPress
- Aller dans le menu "KPI Cohortes" (permissions admin requises)

Fonctionnalités
- Formulaire d'analyse : plage de dates, produits (IDs), statut, filtres 'nouveaux clients' et 'nouveaux codes postaux'
- Graphiques : histogramme / courbe. Support basique de comparatif produits si des breakdowns sont disponibles.
- Carte : OpenStreetMap (Leaflet) affichant des cercles proportionnels au nombre de commandes par code postal (géocodage via Nominatim, résultats mis en cache dans `localStorage` côté navigateur et dans les options WP côté serveur).

Notes et limites
- Le plugin agrège par `postcode` + `address_1`. Il s'appuie sur une table `{$wpdb->prefix}wc_order_addresses` (schema fourni par ton data sample). Si ta structure diffère, adapte le nom de table/colonnes.
- Le géocodage utilise l'API publique Nominatim (OpenStreetMap). Respecte les règles d'utilisation (rate limits). Le plugin met en cache les résultats par code postal.
- L'implémentation 3D isométrique complète (Three.js + extrusion topojson) n'est pas encore implémentée — la carte actuelle est 2D Leaflet avec cercles extrudés visuellement. Je peux pousser la version Three.js (tiles -> texture + extrusions) si tu veux.

Prochaines étapes recommandées
1. Vérifier sur une installation de test (local) avec WooCommerce activé.
2. Ajouter tests unitaires et un petit script pour peupler la table d'adresses si besoin.
3. Implémenter la vraie vue 3D isométrique (Three.js) si nécessaire — nécessite mapping postcode->polygon ou centroid dataset.

Si tu veux, je peux maintenant :
- Ajouter la détection "nouveaux clients" côté serveur (actuellement partiellement supportée),
- Remplacer la visualisation par une vraie extrusion 3D via Three.js + topojson (besoin d'un fichier GeoJSON/topojson ou d'une table postcode->coords),
- Ajouter options d'administration (purger cache, clé API si tu veux utiliser un service payant de geocoding).

