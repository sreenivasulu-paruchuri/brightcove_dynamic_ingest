<?php

namespace Drupal\brightcove_dynamic_ingest\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the Brightcove access token.
 */
class BcDynamicIngestController extends ControllerBase {

  /**
   * Brightcove uploading log channel.
   */
  const LOG_CHANNEL = 'brightcove dynamic ingest';

  /**
   * Symfony\Component\HttpFoundation\RequestStack definition.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->requestStack = $container->get('request_stack');
    return $instance;
  }

  /**
   * Returns the access token of Brightcove.
   */
  public function getOauthToken(string $client_name = NULL) {
    $client = $this->getBrightcoveClientKeys();
    // Set up request for access token.
    $data          = [];
    $client_id     = $client["clientId"];
    $client_secret = $client["secretKey"];
    $auth_string   = "{$client_id}:{$client_secret}";
    $request       = "https://oauth.brightcove.com/v4/access_token?grant_type=client_credentials";
    $ch            = curl_init($request);
    curl_setopt_array($ch, [
      CURLOPT_POST           => TRUE,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_SSL_VERIFYPEER => FALSE,
      CURLOPT_USERPWD        => $auth_string,
      CURLOPT_HTTPHEADER     => [
        'Content-type: application/x-www-form-urlencoded',
      ],
      CURLOPT_POSTFIELDS => $data,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    // Check for errors.
    if ($response === FALSE) {
      echo 'An error occurred';
    }
    else {
      echo $response;
    }
    exit;
  }

  /**
   * Logs and display message and redirect to MGC form.
   */
  public function finalizeUpload() {
    $request = $this->requestStack->getCurrentRequest()->request;
    if (!empty($request) && !empty($request->get('status'))) {
      if ($request->get('status') == 'success') {
        $this->getLogger(self::LOG_CHANNEL)->info($this->t('Video has been uploaded successfully'));
        $this->messenger()->addStatus($this->t('Video has been uploaded successfully'));
      }
      else {
        $this->getLogger(self::LOG_CHANNEL)->error($this->t('Function(API) called: @location<br>@log', [
          '@location' => $request->get('location'),
          '@log' => $request->get('message'),
        ]));
        $this->messenger()->addError($this->t('An error occurred while uploading video. Please check logs or try again!'));
      }
    }
    echo Url::fromRoute('brightcove_dynamic_ingest.add_form')->setAbsolute()->toString();
    exit;
  }

  /**
   * Brightcove clients.
   *
   * @param bool $watermark
   *   Watermark status for brightcove api.
   *
   * @return array
   *   Array of brightcove clients.
   */
  public function getBrightcoveClientKeys($watermark = FALSE) {
    $client = [];
    $brightcoveConfig = $this->entityTypeManager()->getStorage('brightcove_api_client');
    $ids = $brightcoveConfig->getQuery('brightcove_api_client')->execute();
    if (in_array('watermark', $ids) && !$watermark) {
      unset($ids['watermark']);
    }
    elseif (in_array('watermark', $ids) && $watermark) {
      $ids = ['watermark'];
    }
    $list = $brightcoveConfig->loadMultiple($ids);
    foreach ($list as $entity) {
      $client['clientId'] = $entity->get('client_id');
      $client['secretKey'] = $entity->get('secret_key');
      $client['accountId'] = $entity->get('account_id');
    }
    return $client;
  }

  /**
   * Tags to push to Brightcove.
   *
   * @param bool $watermark
   *   Watermark status for brightcove api.
   *
   * @return array|false|string[]
   *   Tags to attach with.
   */
  public function getPushTags($watermark = FALSE) {
    $tags = [];
    $config = $this->config('mc_brightcove.settings');
    if (!empty($config->get('tags_to_push')) && !$watermark) {
      $tags = explode(',', $config->get('tags_to_push'));
    }
    if (!empty($config->get('watermark_tags_to_push')) && $watermark) {
      $tags = explode(',', $config->get('watermark_tags_to_push'));
    }
    if (!empty($config->get('youtube_label'))) {
      $tags[] = $config->get('youtube_label');
    }
    return $tags;
  }

  /**
   * Get upload location on cloudinary.
   *
   * @return string
   *   Upload location.
   */
  public function getUploadLocation() {
    $target_dir = 'cloudinary://mediacorp/one-cms/images/';
    $image_media_entity = $this->entityFieldManager->getFieldDefinitions('media', 'image');
    if (!empty($image_media_entity) && !empty($image_media_entity['field_media_image'])) {
      $token_replaced = $this->token->replace($image_media_entity['field_media_image']->getSetting('file_directory'));
      $target_dir = 'cloudinary://' . trim($token_replaced, '/') . '/';
    }
    return $target_dir;
  }

  /**
   * Get full path of image.
   */
  public function getThumbnail() {
    $url = NULL;
    $request = $this->requestStack->getCurrentRequest()->request;
    if (!empty($request) && !empty($request->get('fid'))) {
      $fid = $request->get('fid');
      if (is_numeric($fid) && $file = $this->entityTypeManager()->getStorage('file')->load($fid)) {
        $url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
        $this->getLogger(self::LOG_CHANNEL)->info($this->t('Function(API) called: @location<br>Image url: @fid (@url)', [
          '@location' => $request->get('location'),
          '@fid' => $fid,
          '@url' => $url,
        ]));
      }
      else {
        $this->getLogger(self::LOG_CHANNEL)->error($this->t('Function(API) called: @location<br>Not a valid fid: @fid', [
          '@location' => $request->get('location'),
          '@fid' => $fid,
        ]));
      }
    }
    echo $url;
    exit;
  }

}
