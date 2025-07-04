<?php

namespace Drupal\localgov_core\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\TitleResolver;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\localgov_core\Event\PageHeaderDisplayEvent;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\localgov_core\Plugin\views\display_extender\PageHeaderDisplayExtender;

/**
 * Provides a 'PageHeaderBlock' block.
 *
 * @Block(
 *  id = "localgov_page_header_block",
 *  admin_label = @Translation("Page header block"),
 * )
 */
class PageHeaderBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Core current_route_match service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRouteMatch;

  /**
   * Core event_dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Core request_stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Core title_resolver service.
   *
   * @var \Drupal\Core\Controller\TitleResolver
   */
  protected $titleResolver;

  /**
   * Entity associated with the current route.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected $entity = NULL;

  /**
   * View executable associated with the current route.
   *
   * @var \Drupal\views\ViewExecutable|null
   */
  protected $view = NULL;

  /**
   * The page title override.
   *
   * @var array|string|null
   */
  protected $title;

  /**
   * The page subtitle override.
   *
   * @var array|string|null
   */
  protected $subTitle;

  /**
   * The page lede override.
   *
   * @var array|string|null
   */
  protected $lede;

  /**
   * Should the page header block be displayed?
   *
   * @var bool
   */
  protected $visible;

  /**
   * Cache tags for this block.
   *
   * @var array
   */
  protected $cacheTags;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('event_dispatcher'),
      $container->get('request_stack'),
      $container->get('title_resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CurrentRouteMatch $current_route_match, EventDispatcherInterface $event_dispatcher, RequestStack $request_stack, TitleResolver $title_resolver) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->currentRouteMatch = $current_route_match;
    $this->eventDispatcher = $event_dispatcher;
    $this->requestStack = $request_stack;
    $this->titleResolver = $title_resolver;

    // Find the entity, if any, associated with the current route.
    //
    // We consider two cases: (1) type entity:*, and (2) type node_preview with
    // view_mode_id set to 'full'.
    $route = $this->currentRouteMatch->getRouteObject();
    if (!is_null($route)) {
      $parameters = $route->getOption('parameters');
      $defaults = $route->getDefaults();
      if (!is_null($parameters)) {
        foreach ($parameters as $name => $options) {
          if (!isset($options['type'])) {
            continue;
          }

          if (strpos($options['type'], 'entity:') === 0) {
            $entity = $this->currentRouteMatch->getParameter($name);
          }
          elseif ($options['type'] === 'node_preview') {
            $preview = $this->currentRouteMatch->getParentRouteMatch()->getParameter($name);
            if (isset($preview->preview_view_mode) && $preview->preview_view_mode === 'full') {
              $entity = $preview;
            }
          }

          if (isset($entity) && $entity instanceof EntityInterface) {
            $this->entity = $entity;
            break;
          }
        }
      }

      // If this is a view path, set the view instead.
      if (isset($defaults['view_id']) && isset($defaults['display_id'])) {
        $view = Views::getView($defaults['view_id']);
        if ($view) {
          $view->setDisplay($defaults['display_id']);
          $this->view = $view;
        }
      }
    }

    // Dispatch event to allow modules to alter block content.
    $event = new PageHeaderDisplayEvent();

    // If an entity is set, pass to the event.
    if ($this->entity instanceof EntityInterface) {
      $event->setEntity($this->entity);
    }

    // If a view is set, pass to the event.
    if ($this->view instanceof ViewExecutable) {
      $event->setView($this->view);
    }

    // Dispatch event.
    $this->eventDispatcher->dispatch($event, PageHeaderDisplayEvent::EVENT_NAME);

    // Set the title, lede, visibility and cache tags.
    $this->title = is_null($event->getTitle()) ? $this->getTitle() : $event->getTitle();
    $this->subTitle = is_null($event->getSubTitle()) ? NULL : $event->getSubTitle();
    $this->lede = is_null($event->getLede()) ? $this->getLede() : $event->getLede();
    $this->visible = $event->getVisibility();
    $entityCacheTags = is_null($this->entity) ? [] : $this->entity->getCacheTags();
    $viewCacheTags = is_null($this->view) ? [] : $this->view->getCacheTags();
    $this->cacheTags = is_null($event->getCacheTags()) ? array_merge($entityCacheTags, $viewCacheTags) : $event->getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $build[] = [
      '#theme' => 'localgov_page_header_block',
      '#title' => $this->title,
      '#subtitle' => $this->subTitle,
      '#lede' => $this->lede,
      '#entity' => $this->entity,
    ];

    return $build;
  }

  /**
   * Get the default title for the page.
   *
   * @return array|string|null
   *   Returns a title for the current page or NULL if it can't be determined.
   */
  protected function getTitle() {
    $request = $this->requestStack->getCurrentRequest();
    $route = $this->currentRouteMatch->getRouteObject();
    if ($route) {
      return $this->titleResolver->getTitle($request, $route);
    }
    return NULL;
  }

  /**
   * Get the default lede for page.
   *
   * @return array|string|null
   *   Returns a lede for the current page or NULL if it can't be determined.
   */
  protected function getLede() {

    // Return node summary if it exists.
    if ($this->entity instanceof Node && $this->entity->hasField('body')) {
      return [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->entity->get('body')->summary,
      ];
    }

    // Return a term string.
    if ($this->entity instanceof Term) {
      return [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('All pages relating to @label.', ['@label' => strtolower($this->entity->label())]),
      ];
    }

    // Return view custom summary.
    if ($this->view instanceof ViewExecutable) {
      $extender = $this->view->getDisplay()->getExtenders()['localgov_page_header_display_extender'] ?? NULL;

      // Only return a view lede if the Page Header Display extender is found.
      if ($extender instanceof PageHeaderDisplayExtender) {

        // Need to render view to apply tokens.
        $this->view->render();
        $lede = $extender->getLede();
        return [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $lede,
        ];
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    if ($this->visible) {
      return AccessResult::allowed();
    }
    else {
      return AccessResult::neutral();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['label_display' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    if (!empty($this->cacheTags)) {
      return Cache::mergeTags(parent::getCacheTags(), $this->cacheTags);
    }
    return parent::getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

}
