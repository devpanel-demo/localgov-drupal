<?php

declare(strict_types=1);

namespace Drupal\preview_link\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\preview_link\PreviewLinkExpiry;
use Drupal\preview_link\PreviewLinkUtility;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Allow settings to be changed from the UI for preview link.
 */
class PreviewLinkSettingsForm extends ConfigFormBase {

  /**
   * Constructs a PreviewLinkSettingsForm.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    TypedConfigManagerInterface $typedConfigManager,
    protected EntityTypeBundleInfoInterface $bundleInfo,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configFactory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      $this->getConfigName(),
    ];
  }

  /**
   * A method to get the config name.
   *
   * @return string
   *   The config name.
   */
  private function getConfigName(): string {
    return 'preview_link.settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'preview_link_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config($this->getConfigName());
    $form = parent::buildForm($form, $form_state);

    $form['display_message'] = [
      '#type' => 'select',
      '#title' => $this->t('Display message'),
      '#description' => $this->t("When to display a message to a user that the preview link was created."),
      '#options' => [
        'always' => $this->t('Always'),
        'subsequent' => $this->t('Subsequent'),
        'never' => $this->t('Never'),
      ],
      '#default_value' => $config->get('display_message') ?: 'subsequent',
    ];

    $form['bundles'] = [
      '#type' => 'details',
    ];
    $form['bundles']['help'] = [
      '#markup' => $this->t('Enable entity type/bundles for use with preview link.'),
    ];
    $selectedOptions = $this->getSelectedEntityTypeOptions();
    $form['bundles']['enabled_entity_types'] = [
      '#type' => 'tableselect',
      '#header' => [
        $this->t('Entity type'),
        $this->t('Bundle'),
      ],
      '#options' => $this->getEntityTypeOptions(),
      '#default_value' => array_fill_keys($selectedOptions, TRUE),
    ];
    // Collapse the details element if anything is enabled.
    $form['bundles']['#title'] = $this->t('Enabled types (@count)', [
      '@count' => count($selectedOptions),
    ]);
    $form['bundles']['#open'] = count($selectedOptions) === 0;

    $form['multiple_entities'] = [
      '#type' => 'checkbox',
      '#title' => 'Multiple entities',
      '#description' => $this->t('Whether preview links can reference multiple entities.'),
      '#default_value' => $config->get('multiple_entities'),
    ];
    $form['expiry_days'] = [
      '#type' => 'number',
      '#title' => 'Expiry days',
      '#description' => $this->t('The number of days before a preview link expires.'),
      '#default_value' => round(($config->get('expiry_seconds') ?? PreviewLinkExpiry::DEFAULT_EXPIRY_SECONDS) / 86400, 2),
      '#min' => 0,
      '#step' => 0.01,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config($this->getConfigName());

    $config->set('display_message', $form_state->getValue('display_message'));
    $config->set('multiple_entities', $form_state->getValue('multiple_entities'));
    $config->set('expiry_seconds', intval(86400 * $form_state->getValue('expiry_days')));

    $config->clear('enabled_entity_types');
    foreach (array_keys(array_filter($form_state->getValue('enabled_entity_types'))) as $enabledBundle) {
      if (strpos($enabledBundle, ':') !== FALSE) {
        [$entityTypeId, $bundle] = explode(':', $enabledBundle);
        $bundles = $config->get('enabled_entity_types.' . $entityTypeId) ?: [];
        $bundles[] = $bundle;
        $config->set('enabled_entity_types.' . $entityTypeId, $bundles);
      }
      else {
        $entityTypeId = $enabledBundle;
        $config->set('enabled_entity_types.' . $entityTypeId, []);
      }
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * The options available for the user to select for bundle types.
   *
   * @return array
   *   A 'entity_id:bundle' style array of possible options.
   */
  protected function getEntityTypeOptions(): array {
    $options = [];
    $entityTypes = $this->entityTypeManager->getDefinitions();
    $entityTypes = array_filter($entityTypes, [
      PreviewLinkUtility::class,
      'isEntityTypeSupported',
    ]);
    foreach ($entityTypes as $entityTypeId => $info) {
      $options[$entityTypeId] = [
        ['data' => ['#markup' => '<strong>' . $info->getLabel() . '</strong>']],
        ['data' => ['#markup' => $this->t('<em>If selected and no bundles are selected, all bundles will be enabled.</em>')]],
      ];
      foreach ($this->bundleInfo->getBundleInfo($entityTypeId) as $bundle => $bundleInfo) {
        if ($entityTypeId === $bundle) {
          continue;
        }
        $options[sprintf('%s:%s', $entityTypeId, $bundle)] = [
          $info->getLabel() ?: '',
          $bundleInfo['label'] ?: '',
        ];
      }
    }
    return $options;
  }

  /**
   * The enabled entities and bundles for preview link to apply to.
   *
   * @return array
   *   A 'entity_id:bundle' style array of selected options.
   */
  protected function getSelectedEntityTypeOptions(): array {
    $config = $this->config($this->getConfigName());
    $configured = $config->get('enabled_entity_types') ?: [];
    $selected = [];
    foreach ($configured as $entityTypeId => $bundles) {
      $selected[] = $entityTypeId;
      foreach ($bundles as $bundle) {
        $selected[] = sprintf('%s:%s', $entityTypeId, $bundle);
      }
    }
    return $selected;
  }

}
