<?php

namespace Drupal\Tests\localgov_directories_db\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;

/**
 * Kernel test enabling server.
 *
 * @group localgov_directories
 */
class InstallTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'node',
    'field',
    'image',
    'link',
    'media',
    'address',
    'telephone',
    'text',
    'image',
    'search_api',
    'search_api_db',
    'localgov_directories',
    'views',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('search_api_index');
    $this->installEntitySchema('search_api_server');
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installConfig([
      'image',
      'media',
      'node',
      'localgov_directories',
      'search_api_db',
      'search_api',
      'user',
    ]);
  }

  /**
   * Test enabling/uninstall.
   */
  public function testEnableModule() {
    $index = Index::load('localgov_directories_index_default');
    $this->assertEmpty($index->getServerId());
    $this->assertFalse($index->status());
    \Drupal::service('module_installer')->install(['localgov_directories_db']);
    $index = Index::load('localgov_directories_index_default');
    $this->assertEquals('localgov_directories_default', $index->getServerId());
    $this->assertTrue($index->status());
    \Drupal::service('module_installer')->uninstall(['localgov_directories_db']);
    $index = Index::load('localgov_directories_index_default');
    $this->assertEquals('', $index->getServerId());
    $this->assertFalse($index->status());
  }

}
