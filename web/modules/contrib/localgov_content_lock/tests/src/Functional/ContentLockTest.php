<?php

namespace Drupal\Tests\localgov_content_lock\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Functional tests for LocalGov Content Lock module.
 */
class ContentLockTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'localgov';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'localgov_content_lock',
  ];

  /**
   * Set up tests.
   */
  protected function setUp(): void {
    parent::setUp();

    // Use Claro for testing as there are problems with the Gin theme.
    // See https://github.com/localgovdrupal/localgov/issues/731
    $this->assertTrue(\Drupal::service('theme_installer')->install(['claro']));
    $this->container->get('config.factory')
      ->getEditable('system.theme')
      ->set('default', 'claro')
      ->set('admin', 'claro')
      ->save();
  }

  /**
   * Test content lock configuration.
   */
  public function testContentLockConfiguration(): void {

    $user = $this->drupalCreateUser([], 'test_user', TRUE);
    $this->drupalLogin($user);

    // Create a node.
    $this->drupalGet('/node/add/localgov_services_page');
    $title = $this->randomMachineName();
    $this->submitForm(
      [
        'title[0][value]' => $title,
        'body[0][summary]' => 'Test content lock',
        'body[0][value]' => 'Test content lock',
      ],
      'Save'
    );
    $this->assertSession()->pageTextContains('Service page ' . $title . ' has been created.');
    $nid = $this->drupalGetNodeByTitle($title)->id();

    // Check that the node gets locked when editing.
    $this->drupalGet('/node/' . $nid . '/edit');
    $this->assertSession()->pageTextContains('This content is now locked against simultaneous editing.');
    $this->drupalGet('/admin/content/locked-content');
    $this->assertSession()->pageTextContains($title);
  }

}
