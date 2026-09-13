<?php

namespace Drupal\pmo\Controller;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Webhook des commandes payees sur PieceMotoOccasion.
 *
 * La route est ouverte : l'authentification est la signature HMAC-SHA256 du
 * corps avec le jeton API, que seuls PieceMotoOccasion et cette boutique
 * connaissent. Un corps non signe est refuse avant toute lecture.
 */
class CommandeController extends ControllerBase {

  const ENTETE = 'X-Pmo-Signature';

  protected function signatureValide(Request $requete, string $corps): bool {
    $jeton = (string) $this->config('pmo.reglages')->get('jeton');
    $signature = (string) $requete->headers->get(self::ENTETE, '');
    if ($jeton === '' || $signature === '') {
      return FALSE;
    }
    return hash_equals('sha256=' . hash_hmac('sha256', $corps, $jeton), $signature);
  }

  protected function texte($valeur, int $longueur = 200): string {
    return mb_substr(trim(strip_tags((string) $valeur)), 0, $longueur);
  }

  public function recevoir(Request $requete): JsonResponse {
    $corps = (string) $requete->getContent();
    if (!$this->signatureValide($requete, $corps)) {
      return new JsonResponse(['erreur' => 'signature invalide'], 403);
    }
    $donnees = json_decode($corps, TRUE);
    if (!is_array($donnees) || ($donnees['evenement'] ?? '') !== 'commande.payee' || empty($donnees['commande'])) {
      return new JsonResponse(['ok' => TRUE, 'ignore' => TRUE]);
    }
    $c = $donnees['commande'];
    $id = (int) ($c['id'] ?? 0);
    if ($id <= 0) {
      return new JsonResponse(['erreur' => 'commande sans identifiant'], 400);
    }
    // Anti-doublon : PieceMotoOccasion reessaie un webhook non acquitte.
    $etat = $this->keyValue('pmo.commandes');
    $deja = $etat->get((string) $id);
    if ($deja) {
      return new JsonResponse(['ok' => TRUE, 'deja' => TRUE, 'commande' => (int) $deja]);
    }
    if (!($this->config('pmo.reglages')->get('creer_commandes') ?? TRUE)) {
      return new JsonResponse(['ok' => TRUE]);
    }
    $commande = $this->creerCommande($c);
    $etat->set((string) $id, $commande->id());
    return new JsonResponse(['ok' => TRUE, 'commande' => $commande->id()]);
  }

  protected function keyValue(string $collection) {
    return \Drupal::keyValue($collection);
  }

  protected function creerCommande(array $c): Order {
    $liv = is_array($c['livraison'] ?? NULL) ? $c['livraison'] : [];
    $commande = Order::create([
      'type' => 'default',
      'store_id' => $this->premierMagasin(),
      'mail' => $this->texte($liv['email'] ?? '', 254),
      'state' => 'completed',
      'placed' => \Drupal::time()->getRequestTime(),
    ]);
    $commande->save();
    foreach ((array) ($c['lignes'] ?? []) as $ligne) {
      if (!is_array($ligne)) {
        continue;
      }
      $quantite = max(1, (int) ($ligne['quantite'] ?? 1));
      $prix = new Price((string) ($ligne['prix_ttc'] ?? '0'), 'EUR');
      $item = OrderItem::create([
        'type' => 'default',
        'title' => $this->texte($ligne['titre'] ?? 'Piece PieceMotoOccasion'),
        'quantity' => $quantite,
        'unit_price' => $prix,
        'overridden_unit_price' => TRUE,
      ]);
      $item->save();
      $commande->addItem($item);
    }
    $commande->setData('pmo_commande_id', (int) $c['id']);
    $commande->setData('pmo_livraison', $liv);
    $commande->save();
    return $commande;
  }

  protected function premierMagasin() {
    $magasins = $this->entityTypeManager()->getStorage('commerce_store')->loadMultiple();
    $magasin = reset($magasins);
    return $magasin ? $magasin->id() : NULL;
  }

}
