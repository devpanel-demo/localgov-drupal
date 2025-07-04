<?php

namespace Drupal\geo_entity\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the geo entity edit forms.
 */
class GeoEntityForm extends ContentEntityForm {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = parent::create($container);

    $form->renderer = $container->get('renderer');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toRenderable();

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => $this->renderer->render($link)];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New geo %label has been created.', $message_arguments));
      $this->logger('geo_entity')->notice('Created new geo %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The geo %label has been updated.', $message_arguments));
      $this->logger('geo_entity')->notice('Updated new geo %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.geo_entity.canonical', ['geo_entity' => $entity->id()]);

    return $result;
  }

}
