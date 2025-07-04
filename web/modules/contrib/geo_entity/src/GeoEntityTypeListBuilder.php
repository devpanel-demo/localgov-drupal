<?php

namespace Drupal\geo_entity;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of geo type entities.
 *
 * @see \Drupal\geo_entity\Entity\GeoEntityType
 */
class GeoEntityTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [];
    $header['title'] = $this->t('Label');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = [];
    $row['title'] = [
      'data' => $entity->label(),
      'class' => ['menu-label'],
    ];

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();

    $build['table']['#empty'] = $this->t(
      'No geo types available. <a href=":link">Add geo type</a>.',
      [':link' => Url::fromRoute('entity.geo_entity_type.add_form')->toString()]
    );

    return $build;
  }

}
