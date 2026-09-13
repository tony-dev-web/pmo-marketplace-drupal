<?php

namespace Drupal\pmo;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;

/**
 * Fabrique les fiches a partir des produits Commerce, et les envoie.
 *
 * Une fiche par variation : c'est la variation qui porte le SKU, le prix et
 * le stock, et c'est le SKU qui sert de reference chez PieceMotoOccasion.
 */
class Catalogue {

  const LOT = 50;

  protected $client;
  protected $entites;
  protected $config;

  public function __construct(Client $client, EntityTypeManagerInterface $entites, ConfigFactoryInterface $config) {
    $this->client = $client;
    $this->entites = $entites;
    $this->config = $config;
  }

  protected function categorieDefaut(): string {
    return (string) ($this->config->get('pmo.reglages')->get('categorie') ?: 'Pièce moto');
  }

  /**
   * La premiere categorie du produit, sinon celle du reglage.
   */
  protected function categorie(ProductInterface $produit): string {
    foreach (['field_categorie', 'field_category', 'field_type_piece'] as $champ) {
      if ($produit->hasField($champ) && !$produit->get($champ)->isEmpty()) {
        $terme = $produit->get($champ)->entity;
        if ($terme) {
          return (string) $terme->label();
        }
      }
    }
    return $this->categorieDefaut();
  }

  /**
   * Les adresses absolues des images du produit (6 au plus).
   */
  protected function images(ProductInterface $produit): array {
    $urls = [];
    foreach (['field_images', 'field_image', 'field_photo'] as $champ) {
      if (!$produit->hasField($champ)) {
        continue;
      }
      foreach ($produit->get($champ) as $element) {
        $fichier = $element->entity;
        if ($fichier instanceof FileInterface) {
          $urls[] = $fichier->createFileUrl(FALSE);
        }
      }
    }
    return array_slice(array_values(array_unique(array_filter($urls))), 0, 6);
  }

  protected function texte($valeur, int $longueur): string {
    $texte = is_array($valeur) ? ($valeur['value'] ?? '') : (string) $valeur;
    return mb_substr(trim(strip_tags($texte)), 0, $longueur);
  }

  /**
   * La fiche d'une variation, ou NULL si elle n'est pas envoyable.
   */
  public function fiche(ProductVariationInterface $variation): ?array {
    $produit = $variation->getProduct();
    if (!$produit || !$produit->isPublished() || !$variation->isPublished()) {
      return NULL;
    }
    $sku = (string) $variation->getSku();
    $prix = $variation->getPrice();
    if ($sku === '' || !$prix) {
      return NULL;
    }
    $description = $produit->hasField('body') && !$produit->get('body')->isEmpty()
      ? $produit->get('body')->first()->getValue() : '';
    return [
      'reference' => $sku,
      'titre' => (string) $variation->getOrderItemTitle(),
      'description' => $this->texte($description, 160),
      'information' => $this->texte($description, 2160),
      'prix_ttc' => $prix->getNumber(),
      'stock' => $this->stock($variation),
      'categorie' => $this->categorie($produit),
      'images' => $this->images($produit),
      'url_boutique' => $produit->toUrl('canonical', ['absolute' => TRUE])->toString(),
      'etat_achat' => 'Occasion',
    ];
  }

  /**
   * Le stock de la variation : champ commerce_stock s'il existe, sinon 1.
   */
  public function stock(ProductVariationInterface $variation): int {
    foreach (['field_stock', 'field_quantite'] as $champ) {
      if ($variation->hasField($champ) && !$variation->get($champ)->isEmpty()) {
        return max(0, (int) $variation->get($champ)->value);
      }
    }
    return 1;
  }

  /**
   * Envoie une variation. Rend TRUE si l'API l'a acceptee.
   */
  public function envoyer(ProductVariationInterface $variation): bool {
    $fiche = $this->fiche($variation);
    if ($fiche === NULL) {
      return FALSE;
    }
    $r = $this->client->appel('PUT', '/produits', ['produits' => [$fiche]]);
    return empty($r['erreur']) && !empty($r['produits']);
  }

  public function envoyerStock(ProductVariationInterface $variation): void {
    $sku = (string) $variation->getSku();
    if ($sku !== '') {
      $this->client->appel('PATCH', '/produits/' . rawurlencode($sku) . '/stock', ['stock' => $this->stock($variation)]);
    }
  }

  /**
   * Envoie tout le catalogue, par lots. Rend le bilan en clair.
   */
  public function envoyerTout(): string {
    $stockage = $this->entites->getStorage('commerce_product_variation');
    $ids = $stockage->getQuery()->accessCheck(FALSE)->execute();
    $fiches = [];
    foreach (array_chunk(array_values($ids), 100) as $paquet) {
      foreach ($stockage->loadMultiple($paquet) as $variation) {
        $fiche = $this->fiche($variation);
        if ($fiche !== NULL) {
          $fiches[] = $fiche;
        }
      }
    }
    $envoyes = 0;
    $erreurs = [];
    foreach (array_chunk($fiches, self::LOT) as $lot) {
      $r = $this->client->appel('PUT', '/produits', ['produits' => $lot]);
      if (!empty($r['erreur'])) {
        $erreurs[] = $r['erreur'];
        continue;
      }
      $envoyes += count($r['produits'] ?? []);
      foreach ($r['erreurs'] ?? [] as $e) {
        $erreurs[] = ($e['reference'] ?? '?') . ' : ' . ($e['erreur'] ?? '');
      }
    }
    return sprintf('%d piece(s) envoyee(s) a PieceMotoOccasion, %d erreur(s). %s', $envoyes, count($erreurs), implode(' | ', array_slice($erreurs, 0, 5)));
  }

}
