<?php

namespace Drupal\Tests\localgov_openreferral\Functional;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\geo_entity\Entity\GeoEntity;
use Drupal\localgov_openreferral\Entity\PropertyMapping;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Utility\Utility;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Tests the structure of a Open Referral resource.
 *
 * @group rest
 */
class ResourceTest extends BrowserTestBase {

  use NodeCreationTrait;

  /**
   * Skip schema checks.
   *
   * @var string[]
   */
  protected static $configSchemaCheckerExclusions = [
    // Missing schema:
    // - 'content.location.settings.reset_map.position'.
    // - 'content.location.settings.weight'.
    'core.entity_view_display.localgov_geo.area.default',
    'core.entity_view_display.localgov_geo.area.embed',
    'core.entity_view_display.localgov_geo.area.full',
    'core.entity_view_display.geo_entity.area.default',
    'core.entity_view_display.geo_entity.area.embed',
    'core.entity_view_display.geo_entity.area.full',
    // Missing schema:
    // - content.location.settings.geometry_validation.
    // - content.location.settings.multiple_map.
    // - content.location.settings.leaflet_map.
    // - content.location.settings.height.
    // - content.location.settings.height_unit.
    // - content.location.settings.hide_empty_map.
    // - content.location.settings.disable_wheel.
    // - content.location.settings.gesture_handling.
    // - content.location.settings.popup.
    // - content.location.settings.popup_content.
    // - content.location.settings.leaflet_popup.
    // - content.location.settings.leaflet_tooltip.
    // - content.location.settings.map_position.
    // - content.location.settings.weight.
    // - content.location.settings.icon.
    // - content.location.settings.leaflet_markercluster.
    // - content.location.settings.feature_properties.
    'core.entity_form_display.geo_entity.address.default',
    'core.entity_form_display.geo_entity.address.inline',
    // Missing schema:
    // - content.postal_address.settings.providers.
    // - content.postal_address.settings.geocode_geofield.
    'core.entity_form_display.localgov_geo.address.default',
    'core.entity_form_display.localgov_geo.address.inline',
  ];

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'localgov_openreferral',
    'entity_test',
    'views',
    'search_api',
    'facets',
    'node',
    'geo_entity_address',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * Organization Type node for testing.
   *
   * @var \Drupal\node\Entity\NodeType
   */
  protected $organizationType;

  /**
   * Organization node for testing.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $organization;

  /**
   * Location Geo Entity for testing.
   *
   * @var \Drupal\geo_entity\Entity\GeoEntity
   */
  protected $location;

  /**
   * Service Type node for testing.
   *
   * @var \Drupal\node\Entity\NodeType
   */
  protected $serviceType;

  /**
   * Service node for testing.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Do not use a batch for tracking the initial items after creating an
    // index when running the tests via the GUI. Otherwise, it seems Drupal's
    // Batch API gets confused and the test fails.
    if (!Utility::isRunningInCli()) {
      \Drupal::state()->set('search_api_use_tracking_batch', FALSE);
    }

    // Create an entity programmatic.
    $this->entity = EntityTest::create([
      'name' => $this->randomMachineName(),
      'user_id' => 1,
      'field_test_text' => [
        0 => [
          'value' => $this->randomString(),
          'format' => 'plain_text',
        ],
      ],
    ]);
    $this->entity->save();
    PropertyMapping::create([
      'id' => 'entity_test.entity_test',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'public_type' => 'taxonomy',
      'public_datatype' => 'vocabulary_test',
      'property_mappings' => [
        'default' => [
          [
            'field_name' => 'name',
            'public_name' => 'name',
          ],
          [
            'field_name' => 'uuid',
            'public_name' => 'id',
          ],
        ],
      ],
    ])->save();

    $this->organizationType = $this->drupalCreateContentType();
    PropertyMapping::create([
      'id' => 'node.' . $this->organizationType->id(),
      'entity_type' => 'node',
      'bundle' => $this->organizationType->id(),
      'public_type' => 'organization',
      'public_datatype' => NULL,
      'property_mappings' => [
        'default' => [
          [
            'field_name' => 'title',
            'public_name' => 'name',
          ],
          [
            'field_name' => 'uuid',
            'public_name' => 'id',
          ],
          [
            'field_name' => 'body',
            'public_name' => 'description',
          ],
        ],
      ],
    ])->save();
    $this->organization = $this->createNode(['type' => $this->organizationType->id()]);

    $this->location = GeoEntity::create([
      'bundle' => 'address',
      'label' => $this->randomString(256),
      'status' => 0,
    ]);
    $this->location->save();

    $this->serviceType = $this->drupalCreateContentType();
    // Add a reference field from service to organization.
    FieldStorageConfig::create([
      'field_name' => 'organization_reference',
      'entity_type' => 'node',
      'type' => 'entity_reference',
    ])->save();
    FieldConfig::create([
      'field_name' => 'organization_reference',
      'entity_type' => 'node',
      'bundle' => $this->serviceType->id(),
    ])->save();

    PropertyMapping::create([
      'id' => 'node.' . $this->serviceType->id(),
      'entity_type' => 'node',
      'bundle' => $this->serviceType->id(),
      'public_type' => 'service',
      'public_datatype' => NULL,
      'property_mappings' => [
        'default' => [
          [
            'field_name' => 'title',
            'public_name' => 'name',
          ],
          [
            'field_name' => 'uuid',
            'public_name' => 'id',
          ],
          [
            'field_name' => 'body',
            'public_name' => 'description',
          ],
          [
            'field_name' => 'organization_reference',
            'public_name' => 'organization',
          ],
        ],
      ],
    ])->save();
    $this->service = $this->createNode([
      'type' => $this->serviceType->id(),
      'organization_reference' => ['target_id' => $this->organization->id()],
    ]);

    Role::load(AccountInterface::ANONYMOUS_ROLE)
      ->grantPermission('view test entity')
      ->save();
  }

  /**
   * Test access to unpublished content, including referenced content.
   */
  public function testUnpublishedAccess() {
    //
    // Organization route.
    //
    // Published organization.
    $this->drupalGet(Url::fromRoute('localgov_openreferral.organization', ['entity' => $this->organization->uuid()]));
    $this->assertSession()->statusCodeEquals(200);

    // Unpublish the organization.
    $this->organization->setUnpublished();
    $this->organization->save();
    $this->drupalGet(Url::fromRoute('localgov_openreferral.organization', ['entity' => $this->organization->uuid()]));
    $this->assertSession()->statusCodeEquals(403);

    //
    // Service route.
    //
    // Published service - with referenced unpublished organization.
    $this->drupalGet(Url::fromRoute('localgov_openreferral.service', ['entity' => $this->service->uuid()]));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains($this->organization->label());

    // Publish organization - now appears on service.
    $this->organization->setPublished();
    $this->organization->save();
    $this->drupalGet(Url::fromRoute('localgov_openreferral.service', ['entity' => $this->service->uuid()]));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($this->organization->label());

    // And unpublish organization again.
    $this->organization->setUnpublished();
    $this->organization->save();
    $this->drupalGet(Url::fromRoute('localgov_openreferral.service', ['entity' => $this->service->uuid()]));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains($this->organization->label());

    // Also unpublish service.
    $this->service->setUnpublished();
    $this->service->save();
    $this->drupalGet(Url::fromRoute('localgov_openreferral.service', ['entity' => $this->service->uuid()]));
    $this->assertSession()->statusCodeEquals(403);

    //
    // Service list route.
    //
    $index = Index::load('openreferral_services');
    $index->indexItems();

    // Service unpublished.
    $this->drupalGet('/openreferral/v1/services');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains($this->service->label());

    // Publish service.
    $this->service->setPublished();
    $this->service->save();
    $this->organization->setPublished();
    $this->organization->save();
    $index->indexItems();

    $this->drupalGet('/openreferral/v1/services');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($this->service->label());
    $this->assertSession()->pageTextContains($this->organization->label());

    // And unpublish related organization.
    $this->organization->setUnpublished();
    $this->organization->save();
    $this->drupalGet('/openreferral/v1/services');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($this->service->label());
    $this->assertSession()->pageTextNotContains($this->organization->label());

    // Unpublish again.
    $this->service->setUnpublished();
    $this->service->save();
    $index->indexItems();
    $this->drupalGet('/openreferral/v1/services');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains($this->service->label());
    $this->assertSession()->pageTextNotContains($this->organization->label());

  }

  /**
   * Test access permissions.
   */
  public function testPermissions() {
    // Anon without access to content.
    \user_role_revoke_permissions(RoleInterface::ANONYMOUS_ID, [
      'access content',
      'view geo',
      'view test entity',
    ]);
    // User with access to content.
    $user = $this->drupalCreateUser([
      'access content',
      'view geo',
      'view test entity',
    ]);

    //
    // User.
    //
    $this->drupalLogin($user);

    // Location route.
    $this->drupalGet(Url::fromRoute('localgov_openreferral.location', ['entity' => $this->location->uuid()]));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($this->location->label());

    // Vocabulary route.
    $this->drupalGet(Url::fromRoute('localgov_openreferral.vocabulary'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('vocabulary_test');

    // Organization route.
    $this->drupalGet(Url::fromRoute('localgov_openreferral.organization', ['entity' => $this->organization->uuid()]));
    $this->assertSession()->statusCodeEquals(200);

    // Service route.
    $this->drupalGet(Url::fromRoute('localgov_openreferral.service', ['entity' => $this->service->uuid()]));
    $this->assertSession()->statusCodeEquals(200);

    //
    // Anon.
    //
    $this->drupalLogout();

    // Location route.
    $this->drupalGet(Url::fromRoute('localgov_openreferral.location', ['entity' => $this->location->uuid()]));
    $this->assertSession()->statusCodeEquals(403);

    // Vocabulary route.
    $this->drupalGet(Url::fromRoute('localgov_openreferral.vocabulary'));
    $this->assertSession()->statusCodeEquals(403);

    // Organization route.
    $this->drupalGet(Url::fromRoute('localgov_openreferral.organization', ['entity' => $this->organization->uuid()]));
    $this->assertSession()->statusCodeEquals(403);

    // Service route.
    $this->drupalGet(Url::fromRoute('localgov_openreferral.service', ['entity' => $this->service->uuid()]));
    $this->assertSession()->statusCodeEquals(403);
  }

}
