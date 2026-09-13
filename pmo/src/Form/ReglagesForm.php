<?php

namespace Drupal\pmo\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Reglages PieceMotoOccasion et envoi manuel du catalogue.
 */
class ReglagesForm extends ConfigFormBase {

  protected $catalogue;

  public static function create(ContainerInterface $container) {
    $form = parent::create($container);
    $form->catalogue = $container->get('pmo.catalogue');
    return $form;
  }

  protected function getEditableConfigNames() {
    return ['pmo.reglages'];
  }

  public function getFormId() {
    return 'pmo_reglages';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('pmo.reglages');
    $form['jeton'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Jeton API PieceMotoOccasion'),
      '#default_value' => $config->get('jeton'),
      '#description' => $this->t('Genere dans votre espace vendeur : https://piecemotooccasion.eu/a2/vendeurs/boutique'),
    ];
    $form['categorie'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Categorie par defaut'),
      '#default_value' => $config->get('categorie') ?: 'Pièce moto',
      '#description' => $this->t('Utilisee quand la piece n\'a pas de categorie : Carénage, Selle, Jante…'),
    ];
    $form['creer_commandes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Creer une commande Drupal Commerce a chaque commande payee sur PieceMotoOccasion'),
      '#default_value' => $config->get('creer_commandes') ?? TRUE,
      '#description' => $this->t('URL de notification a renseigner dans votre espace vendeur : @url', [
        '@url' => Url::fromRoute('pmo.commande', [], ['absolute' => TRUE])->toString(),
      ]),
    ];
    $form['envoyer'] = [
      '#type' => 'submit',
      '#value' => $this->t('Envoyer tout le catalogue maintenant'),
      '#submit' => ['::envoyerTout'],
      '#limit_validation_errors' => [],
    ];
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('pmo.reglages')
      ->set('jeton', trim($form_state->getValue('jeton')))
      ->set('categorie', trim($form_state->getValue('categorie')))
      ->set('creer_commandes', (bool) $form_state->getValue('creer_commandes'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  public function envoyerTout(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->catalogue->envoyerTout());
  }

}
