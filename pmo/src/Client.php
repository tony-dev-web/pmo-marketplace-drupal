<?php

namespace Drupal\pmo;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Appels a l'API vendeur PieceMotoOccasion (jeton Bearer, JSON).
 */
class Client {

  const API = 'https://piecemotooccasion.eu/api/v1';

  protected $http;
  protected $config;

  public function __construct(ClientInterface $http, ConfigFactoryInterface $config) {
    $this->http = $http;
    $this->config = $config;
  }

  public function jeton(): string {
    return (string) $this->config->get('pmo.reglages')->get('jeton');
  }

  /**
   * Rend le tableau decode, ou ['erreur' => message].
   */
  public function appel(string $methode, string $chemin, ?array $corps = NULL): array {
    $jeton = $this->jeton();
    if ($jeton === '') {
      return ['erreur' => 'jeton API manquant'];
    }
    $options = [
      'timeout' => 60,
      'headers' => [
        'Authorization' => 'Bearer ' . $jeton,
        'Content-Type' => 'application/json',
        'User-Agent' => 'pmo-drupal/1.0',
      ],
      'http_errors' => FALSE,
    ];
    if ($corps !== NULL) {
      $options['body'] = json_encode($corps, JSON_UNESCAPED_UNICODE);
    }
    try {
      $reponse = $this->http->request($methode, self::API . $chemin, $options);
    }
    catch (RequestException $e) {
      return ['erreur' => $e->getMessage()];
    }
    $json = json_decode((string) $reponse->getBody(), TRUE);
    if (!is_array($json)) {
      return ['erreur' => 'reponse illisible (HTTP ' . $reponse->getStatusCode() . ')'];
    }
    if ($reponse->getStatusCode() >= 400) {
      return ['erreur' => $json['erreur'] ?? ('HTTP ' . $reponse->getStatusCode())];
    }
    return $json;
  }

}
