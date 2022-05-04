<?php

namespace Drupal\d8_progress_tracking\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\d8_progress_entity\CollectionItemProgressManager;
use Drupal\d8_progress_entity\ProgressEntityManager;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "progress_entity_completion_rest_resource",
 *   label = @Translation("Progress entity completion rest resource"),
 *   uri_paths = {
 *     "create" = "/progress-tracking/completion"
 *   }
 * )
 */
class ProgressEntityCompletionRestResource extends ResourceBase {

  /**
   * @var \Drupal\d8_progress_entity\CollectionItemProgressManager
   */
  protected $collectionItemProgressManager;

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The progress entity manager.
   *
   * @var \Drupal\d8_progress_entity\ProgressEntityManager
   */
  protected $progressEntityManager;

  /**
   * Data to be updated on a progress entity $field_name => $field_value.
   *
   * @var array
   */
  protected $data;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, AccountProxyInterface $current_user, EntityTypeManager $entity_type_manager, ProgressEntityManager $progress_entity_manager, CollectionItemProgressManager $collection_item_progress_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->progressEntityManager = $progress_entity_manager;
    $this->collectionItemProgressManager = $collection_item_progress_manager;
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
      $container->get('entity_type.manager'),
      $container->get('d8_progress_entity.entity_manager'),
      $container->get('d8_progress_entity.collection_item_progress_manager')
    );
  }

  /**
   * Responds to POST requests.
   *
   * @param string $payload
   *
   * @return ModifiedResourceResponse|ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function post($payload) {

    //    This should check the progress entity perms instead
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    if (!isset($payload['progress_item_id']) || !isset($payload['target_completion_state'])) {
      $response = [
        'body' => 'Invalid request',
        'status' => 400,
      ];

      return new ResourceResponse($response['body'], $response['status']);
    }

    $collection_item_progress_id = $payload['progress_item_id'];

    $collection_item_progress_entity = $this->entityTypeManager
      ->getStorage('progress_entity')
      ->load($collection_item_progress_id);

    $target_status = $payload['target_completion_state'] ? 'completed' : 'initial';

    try {
      switch ($target_status) {
        case 'completed':
          // Completion method will always be manual since this is
          // fired by user interaction with a checkbox.
          $this->collectionItemProgressManager
            ->markCompleted($collection_item_progress_entity, 'manual');
          break;
        case 'initial':
          $this->collectionItemProgressManager
            ->unmarkCompleted($collection_item_progress_entity);
          break;
        default:
          throw new \Exception('Invalid completion target state.');
      }
    }
    catch (\Exception $e) {
      return new ResourceResponse([$e->getMessage()], 400);
    }

    return new ModifiedResourceResponse("['Collection Progress Entity $collection_item_progress_id updated successfully']", 200);
  }

}
