<?php

namespace Drupal\localgov_subsites\EventSubscriber;

use Drupal\localgov_core\Event\PageHeaderDisplayEvent;
use Drupal\node\Entity\Node;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Hide page header.
 *
 * @package Drupal\localgov_subsites\EventSubscriber
 */
class PageHeaderSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PageHeaderDisplayEvent::EVENT_NAME => ['setPageHeader', 0],
    ];
  }

  /**
   * Hide page header block.
   */
  public function setPageHeader(PageHeaderDisplayEvent $event) {
    if ($event->getEntity() instanceof Node &&
      ($event->getEntity()->bundle() == 'localgov_subsites_overview' ||
      $event->getEntity()->bundle() == 'localgov_subsites_page')
    ) {
      $event->setVisibility(FALSE);
    }
  }

}
