<?php

declare(strict_types = 1);

namespace Drupal\date_recur\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

//@codingStandardsIgnoreStart
/**
 * Date recur interpreter plugin manager.
 */
class DateRecurInterpreterManager extends DefaultPluginManager implements DateRecurInterpreterManagerInterface {

  /**
   * {@inheritdoc}
   *
   * @param \Traversable<string,string[]> $namespaces
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    //@codingStandardsIgnoreEnd
    parent::__construct(
      'Plugin/DateRecurInterpreter',
      $namespaces,
      $module_handler,
      'Drupal\date_recur\Plugin\DateRecurInterpreterPluginInterface',
      'Drupal\date_recur\Annotation\DateRecurInterpreter',
    );
    $this->setCacheBackend($cache_backend, 'date_recur_interpreter_info', ['config:date_recur_interpreter_list']);
  }

}
