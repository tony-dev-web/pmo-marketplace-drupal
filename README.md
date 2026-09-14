# PieceMotoOccasion marketplace pour Drupal 10 / 11

Vendez vos pièces moto sur la marketplace française [PieceMotoOccasion](https://piecemotooccasion.eu) depuis votre site Drupal équipé de Drupal Commerce : catalogue et stocks synchronisés, commandes reçues dans Commerce.

**Page et guide d'installation** : https://piecemotooccasion.eu/extensions/drupal
**Téléchargement** : https://cdn.piecemotooccasion.eu/static/plugins/pmo-drupal.zip
**Jeton API** : espace vendeur, page « Ma boutique en ligne »

## Installation

1. Déposez le dossier `pmo/` dans `web/modules/custom/`, puis activez : `drush en pmo`.
2. Commerce › Configuration › PieceMotoOccasion : jeton API et catégorie par défaut.
3. Copiez l'URL de notification (`/pmo/commande`) dans votre espace vendeur.
4. « Envoyer tout le catalogue maintenant » : chaque nouvelle pièce est vérifiée avant sa mise en ligne.

Une fiche par variation de produit : c'est elle qui porte le SKU, le prix et le stock. Le cron Drupal rattrape chaque jour les modifications faites hors interface, import CSV ou migration.

La route `/pmo/commande` est ouverte mais chaque appel doit porter la signature HMAC-SHA256 du corps, calculée avec votre jeton API. Sans elle, la requête est refusée.

## API

L'extension utilise l'API vendeur PieceMotoOccasion (`https://piecemotooccasion.eu/api/v1`, jeton Bearer) : `PUT /produits`, `PATCH /produits/<ref>/stock`, `DELETE /produits/<ref>`, `GET /commandes`, `POST /commandes/<id>/expedier`, et recoit un webhook JSON signe HMAC-SHA256 (`X-Pmo-Signature`) a chaque commande payee. Documentation : https://piecemotooccasion.eu/extensions/api

Licence GPL-2.0-or-later.

## La plateforme

Cette extension s'installe sur Drupal, qui n'est pas edite par PieceMotoOccasion.

- Site officiel : https://www.drupal.org
- Code source de la plateforme : https://github.com/drupal/drupal
- Documentation pour developpeurs : https://www.drupal.org/docs/develop

## Les extensions PieceMotoOccasion

- [PrestaShop](https://github.com/tony-dev-web/pmo-marketplace-prestashop)
- [WooCommerce](https://github.com/tony-dev-web/pmo-marketplace-woocommerce)
- [WordPress](https://github.com/tony-dev-web/pmo-marketplace-wordpress)
- [Shopify](https://github.com/tony-dev-web/pmo-marketplace-shopify)
- [Magento](https://github.com/tony-dev-web/pmo-marketplace-magento)
- [API](https://github.com/tony-dev-web/pmo-marketplace-api)
- Toutes les extensions : https://piecemotooccasion.eu/extensions/
