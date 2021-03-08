<?php

namespace Drupal\brightcove_dynamic_ingest\Form;

use Drupal\brightcove_dynamic_ingest\Services\BcDynamicIngestService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BcDynamicIngestConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'brightcove_dynamic_ingest.settings';

  protected $bcDynamicIngestService;

  /**
   * @inheritDoc
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * @inheritDoc
   */
  public function getFormId() {
    return 'brightcove_dynamic_ingest_config_settings';
  }

  public function __construct(ConfigFactoryInterface $config_factory,
                              BcDynamicIngestService $bc_dynamic_ingest_service) {
    parent::__construct($config_factory);
    $this->bcDynamicIngestService = $bc_dynamic_ingest_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('brightcove_dynamic_ingest.service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['upload_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Upload location'),
      '#default_value' => $config->get('upload_location'),
    ];

    $form['clients_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => 'Api Clients',
    ];
    $clients = $this->bcDynamicIngestService->getOnlyClientField(['label']);
    if (!empty($clients)) {
      foreach ($clients as $key => $client) {
        $form['clients_wrapper'][$key] = [
          '#type' => 'details',
          '#title' => $client,
          '#open' => TRUE,
        ];
        $form['clients_wrapper'][$key]['push_tags'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Push tags'),
          '#default_value' => $config->get('push_tags'),
        ];
        $form['clients_wrapper'][$key]['push_youtube_tag'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Youtube label'),
          '#default_value' => $config->get('push_youtube_tag'),
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('upload_location', $form_state->getValue('upload_location'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
