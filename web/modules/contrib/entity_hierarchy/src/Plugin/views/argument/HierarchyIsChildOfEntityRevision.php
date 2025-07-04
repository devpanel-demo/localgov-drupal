<?php

namespace Drupal\entity_hierarchy\Plugin\views\argument;

/**
 * Argument to limit to children of a revision.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("entity_hierarchy_argument_is_child_of_entity_revision")
 */
class HierarchyIsChildOfEntityRevision extends HierarchyIsChildOfEntity {

  /**
   * {@inheritdoc}
   */
  protected function loadEntity() {
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($this->getEntityType());
    return $storage->loadRevision($this->argument) ?: $storage->load($this->argument);
  }

}
