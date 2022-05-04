<?php

namespace Drupal\d8_progress_tracking\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\d8_progress_entity\ProgressEntityManager;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "progress_entity_rest_resource",
 *   label = @Translation("Progress entity rest resource"),
 *   uri_paths = {
 *     "canonical" = "/progress-tracking",
 *     "create" = "/progress-tracking"
 *   }
 * )
 */
class ProgressEntityRestResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The progress entity storage.
   *
   * @var \Drupal\d8_progress_entity\ProgressEntityManager
   */
  protected $progressEntityManager;

  /**
   * The progress entity that we are acting on.
   *
   * @var \Drupal\d8_progress_entity\Entity\ProgressEntity
   */
  protected $videoProgressEntity;

  /**
   * Data to be updated on a progress entity $field_name => $field_value.
   *
   * @var array
   */
  protected $data;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, AccountProxyInterface $current_user, ProgressEntityManager $progress_entity_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->progressEntityManager = $progress_entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('current_user'),
      $container->get('d8_progress_entity.entity_manager')
    );
  }

  /**
   * Responds to GET requests.
   *
   * @param array $payload
   *   Requested object properties to return.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get(array $payload) {
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    if (!isset($payload['nid'])) {
      $result = ['error' => 'Invalid request'];
      return new ResourceResponse($result, 400);
    }

    $nid = intval($payload['nid']);
    $this->videoProgressEntity = $this->progressEntityManager->getActiveProgressEntity($nid);

    $result = [
      'progress_object' => $this->videoProgressEntity,
    ];

    $response = new ResourceResponse($result, 200);

    return $response->addCacheableDependency($this->videoProgressEntity);
  }

  /**
   * Responds to POST requests.
   *
   * @param array $payload
   *   Contains updated progress data for standalone or collection viewing.
   *
   * @return \Drupal\rest\ModifiedResourceResponse|\Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post(array $payload) {
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    if (!isset($payload['nid']) || !isset($payload['playhead'])) {
      $response = [
        'body' => 'Invalid request',
        'status' => 400,
      ];

      return new ResourceResponse($response['body'], $response['status']);
    }

    $viewing_type = $payload['viewing_type'];
    $nid = intval($payload['nid']);

    // Create progress entity object if it doesn't exist.
    switch ($viewing_type) {
      case 'standalone':
        if (!$this->videoProgressEntity = $this->progressEntityManager->getActiveProgressEntity($nid)) {
          $this->videoProgressEntity = $this->progressEntityManager->createVideoProgress($nid, FALSE);
        }
        break;

      case 'collection':
        $collection_item_paragraph_id = $payload['collection_item_paragraph_id'];
        $collection_item_progress = $this->progressEntityManager
          ->getCollectionItemProgressEntity($collection_item_paragraph_id);

        /**
         * It's important that we use getReferencingProgressEntities() and not
         * getActiveProgressEntity(), here. If the video associated with this
         * collection_item_progress has been marked completed, the latter
         * method will skip over it and generate a new vid_progress. That is
         * not what we want to happen, here. There should only ever be a single
         * video_progress entity associated with a given collection_item_progress.
         */
        if (!$referencing_entities = $this->progressEntityManager->getReferencingProgressEntities($nid, $collection_item_progress)) {
          $this->videoProgressEntity = $this->progressEntityManager
            ->createVideoProgress($nid, FALSE, $collection_item_progress);
          break;
        }

        if (count($referencing_entities) > 1) {
          throw new \LengthException("There is more than one video_progress entity associated with {$collection_item_progress->id()}");
        }

        $this->videoProgressEntity = reset($referencing_entities);
        break;
    }

    // Update progress fields accordingly.
    $this->data['field_video_playhead_position'] = intval($payload['playhead']);
    $this->data['field_video_date_viewed'] = date('Y-m-d\TH:i:s', time());

    if ($progress_percentage_data = $this->progressEntityManager->determineProgressPercentages($this->videoProgressEntity, $payload['playhead'])) {
      $this->data['field_video_progress'] = $progress_percentage_data['field_video_progress'];
      $this->data['field_video_percentage_viewed'] = $progress_percentage_data['field_video_percentage_viewed'];
    }

    $response_body = $this->progressEntityManager
      ->update($this->videoProgressEntity, $this->data)
      ->toArray();

    $response = [
      'body' => $response_body,
      'status' => 200,
    ];

    return new ModifiedResourceResponse($response['body'], $response['status']);
  }

}
