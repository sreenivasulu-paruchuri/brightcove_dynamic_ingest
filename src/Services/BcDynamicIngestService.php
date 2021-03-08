<?php


namespace Drupal\brightcove_dynamic_ingest\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class BcDynamicIngestService {

  protected $configFactory;
  protected $entityTypeBundle;
  protected $entityTypeManager;

  public function __construct(ConfigFactoryInterface $config_factory,
                              EntityTypeBundleInfoInterface $entity_type_bundle,
                              EntityTypeManagerInterface $entity_type_manager) {
    $this->configFactory = $config_factory;
    $this->entityTypeBundle = $entity_type_bundle;
    $this->entityTypeManager = $entity_type_manager;
  }

  public function getEntityTypeInfo(string $entity_type_id) {
    return $this->entityTypeBundle->getBundleInfo($entity_type_id);
  }

  public function getBcClients() {
    $clients = [];
    if ($this->getEntityTypeInfo('brightcove_api_client')) {
      $storage = $this->entityTypeManager->getStorage('brightcove_api_client');
      if ($storage->hasData()) {
        $clients = $storage->getQuery()->execute();
      }
    }
    return $clients;
  }

  public function getBcClientDetails(string $client_name = NULL) {
    $clientsDetails = [];
    $clients = $this->getBcClients();
    if (!empty($clients)) {
      $clients_detail = $this->entityTypeManager->getStorage('brightcove_api_client')->loadMultiple($clients);
      foreach ($clients_detail as $client) {
        if ($client->status()) {
          $clientsDetails[$client->id()] = [
            'label' => $client->getLabel(),
            'accountId' => $client->getAccountId(),
            'clientId' => $client->getClientId(),
            'secretKey' => $client->getSecretKey(),
            'tags' => $this->getDefaultTags($client->id()),
          ];
        }
      }
    }
    if (!empty($client_name) && isset($clientsDetails[$client_name])) {
      $clientsDetails = $clientsDetails[$client_name];
    }
    return $clientsDetails;
  }

  public function getOnlyClientField(array $fields_name, string $client_name = NULL) {
    $clientFieldOnly = [];
    $clientDatas = $this->getBcClientDetails($client_name);
    if (!empty($clientDatas)) {
      foreach ($clientDatas as $key => $clientData) {
        if (!empty($fields_name)) {
          foreach ($fields_name as $field) {
            if (isset($clientData[$field])) {
              if (count($fields_name) > 1) {
                $clientFieldOnly[$key][$field] = $clientData[$field];
              }
              else {
                $clientFieldOnly[$key] = $clientData[$field];
              }
            }
          }
        }
      }
    }
    return $clientFieldOnly;
  }

  public function getDefaultTags(string $client_name) {
    return 'default_tag';
  }

}
