<?php

declare(strict_types = 1);

namespace Drupal\date_recur\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\Element\Datetime;
use Drupal\Core\Datetime\TimeZoneFormHelper;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\date_recur\DateRecurHelper;
use Drupal\date_recur\Plugin\Field\FieldType\DateRecurFieldItemList;
use Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime_range\Plugin\Field\FieldWidget\DateRangeDefaultWidget;

/**
 * Basic RRULE widget.
 *
 * Displays an input textarea accepting RRULE strings.
 *
 * @FieldWidget(
 *   id = "date_recur_basic_widget",
 *   label = @Translation("Simple Recurring Date Widget"),
 *   field_types = {
 *     "date_recur"
 *   }
 * )
 */
class DateRecurBasicWidget extends DateRangeDefaultWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    assert($items instanceof DateRecurFieldItemList);

    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#theme'] = 'date_recur_basic_widget';
    $element['#element_validate'][] = [$this, 'validateRrule'];

    // ::createDefaultValue isnt given enough context about the field item, so
    // override its functions here.
    $element['value']['#default_value'] = $element['end_value']['#default_value'] = NULL;
    $element['value']['#date_timezone'] = $element['end_value']['#date_timezone'] = NULL;
    $this->createDateRecurDefaultValue($element, $items[$delta]);

    // Move fields into a first occurrence container as 'End date' can be
    // confused with 'End date' RRULE concept.
    $element['first_occurrence'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('First occurrence'),
      // Needs a weight otherwise children do not show up within single
      // cardinality widgets.
      '#weight' => 0,
    ];
    $firstOccurrenceParents = [
      ...$element['#field_parents'],
      $this->fieldDefinition->getName(),
      $delta,
      'first_occurrence',
    ];
    $element['value']['#title'] = $this->t('Start');
    $element['end_value']['#title'] = $this->t('End');
    $element['end_value']['#description'] = $this->t('Leave end empty to copy start date; the occurrence will therefore not have any duration.');
    // The end date is never required. Start date is copied over if end date is
    // empty.
    $element['end_value']['#required'] = FALSE;
    $element['value']['#group'] = $element['end_value']['#group'] = implode('][', $firstOccurrenceParents);

    // Add custom value callbacks to correctly form a date from time zone field.
    // @codingStandardsIgnoreLine
    $element['value']['#value_callback'] = $element['end_value']['#value_callback'] = [$this, 'dateValueCallback'];

    // Replace \Datetime::validateDatetime validator with our own.
    $element['value']['#element_validate'] = $element['end_value']['#element_validate'] = [[$this, 'validateDatetime']];

    // Saved values (should) always have a time zone.
    $timeZone = $items[$delta]->timezone ?? NULL;

    $zones = $this->getTimeZoneOptions();
    $element['timezone'] = [
      '#type' => 'select',
      '#title' => $this->t('Time zone'),
      '#default_value' => $timeZone,
      '#options' => $zones,
    ];

    $element['rrule'] = [
      '#type' => 'textarea',
      '#default_value' => $items[$delta]->rrule ?? NULL,
      '#title' => $this->t('Repeat rule'),
      '#description' => $this->t('Repeat rule in <a href=":link">iCalendar Recurrence Rule</a> (RRULE) format.', [
        ':link' => 'https://icalendar.org/iCalendar-RFC-5545/3-8-5-3-recurrence-rule.html',
      ]),
      '#access' => $items->getPartGrid()->isRecurringAllowed(),
    ];

    return $element;
  }

  /**
   * Validator for start and end elements.
   *
   * Sets the time zone before datetime element processes values.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param array|false $input
   *   Input, if any.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The value to assign to the element.
   */
  public function dateValueCallback(array &$element, $input, FormStateInterface $form_state): array {
    if ($input !== FALSE) {
      $timeZonePath = array_slice($element['#parents'], 0, -1);
      $timeZonePath[] = 'timezone';

      // Warning: The time zone is not yet validated, make sure it is valid
      // before using.
      /** @var string|null $submittedTimeZone */
      $submittedTimeZone = NestedArray::getValue($form_state->getUserInput(), $timeZonePath);
      if (!isset($submittedTimeZone)) {
        // If no time zone was submitted, such as when the 'timezone' field is
        // set to #access => FALSE, its necessary to fall back to the fields
        // default value.
        $timeZoneFieldPath = array_slice($element['#array_parents'], 0, -1);
        $timeZoneFieldPath[] = 'timezone';
        $timeZoneField = NestedArray::getValue($form_state->getCompleteForm(), $timeZoneFieldPath);
        $submittedTimeZone = $timeZoneField['#value'] ?? ($timeZoneField['#default_value'] ?? NULL);
      }

      $allTimeZones = \DateTimeZone::listIdentifiers();
      // @todo Add test for invalid submitted time zone.
      if (!in_array($submittedTimeZone, $allTimeZones, TRUE)) {
        // A date is invalid if the time zone is invalid.
        // Need to kill inputs otherwise
        // \Drupal\Core\Datetime\Element\Datetime::validateDatetime thinks there
        // is valid input.
        // Indicate to validator the value could not be built since time zone
        // was invalid combined with a provided non-empty start or end date.
        // This key/value is internal and may be modified at any time.
        $element['#date_recur_basic_widget__invalid_timezone'] = TRUE;
        return [
          // Restore the inputs' previous values.
          'date' => $input['date'],
          'time' => $input['time'],
          // Marking object as NULL indicates this field is invalid, see
          // \Drupal\Core\Datetime\Element\Datetime::processDatetime.
          'object' => NULL,
        ];
      }

      $element['#date_timezone'] = $submittedTimeZone;
    }

    // Setting a callback overrides default value callback in the element,
    // call original now.
    return Datetime::valueCallback($element, $input, $form_state);
  }

  /**
   * Validates start and end date field.
   *
   * If a time zone was not provided then its not necessary to validate start
   * and end date values if they are non-empty.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public function validateDatetime(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $input_exists = FALSE;
    $input = NestedArray::getValue($form_state->getValues(), $element['#parents'], $input_exists);
    if ($input_exists) {
      if ((!empty($input['date']) || !empty($input['time'])) && isset($element['#date_recur_basic_widget__invalid_timezone'])) {
        $timeZoneFieldPath = array_slice($element['#array_parents'], 0, -1);
        $timeZoneFieldPath[] = 'timezone';
        $timeZoneField = NestedArray::getValue($form_state->getCompleteForm(), $timeZoneFieldPath);
        $form_state->setError($timeZoneField, $this->t('Missing time zone for date.'));
        return;
      }
    }

    Datetime::validateDatetime($element, $form_state, $complete_form);
  }

  /**
   * Validates RRULE and first occurrence dates.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public function validateRrule(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $input = NestedArray::getValue($form_state->getValues(), $element['#parents']);
    /** @var \Drupal\Core\Datetime\DrupalDateTime|array|null $startDate */
    $startDate = $input['value'];
    /** @var \Drupal\Core\Datetime\DrupalDateTime|array|null $startDateEnd */
    $startDateEnd = $input['end_value'];
    if (is_array($startDate) || is_array($startDateEnd)) {
      // Dates are an array if invalid input was submitted (e.g date:
      // 80616-02-01).
      return;
    }

    /** @var string $rrule */
    $rrule = $input['rrule'];

    if ($startDateEnd && !isset($startDate)) {
      $form_state->setError($element['value'], (string) $this->t('Start date must be set if end date is set.'));
    }

    // If end was empty, copy start date over.
    if (!isset($startDateEnd) && $startDate) {
      $form_state->setValueForElement($element['end_value'], $startDate);
      $startDateEnd = $startDate;
    }

    // Validate RRULE.
    // Only ensure start date is set, as end date is optional.
    if (strlen($rrule) > 0 && $startDate) {
      try {
        DateRecurHelper::create(
          $rrule,
          $startDate->getPhpDateTime(),
          $startDateEnd?->getPhpDateTime(),
        );
      }
      catch (\Exception) {
        $form_state->setError($element['rrule'], (string) $this->t('Repeat rule is formatted incorrectly.'));
      }
    }
  }

  /**
   * Get a list of time zones suitable for a select field.
   *
   * @return array
   *   A list of time zones where keys are PHP time zone codes, and values are
   *   human readable and translatable labels.
   */
  protected function getTimeZoneOptions(): array {
    return TimeZoneFormHelper::getOptionsListByRegion(TRUE);
  }

  /**
   * Get the current users time zone.
   *
   * @return string
   *   A PHP time zone string.
   */
  protected function getCurrentUserTimeZone(): string {
    return \date_default_timezone_get();
  }

  /**
   * {@inheritdoc}
   */
  protected function createDefaultValue($date, $timezone): DrupalDateTime {
    assert($date instanceof DrupalDateTime);
    assert(is_string($timezone));
    // Cannot set time zone here as field item contains time zone.
    if ($this->getFieldSetting('datetime_type') == DateTimeItem::DATETIME_TYPE_DATE) {
      $date->setDefaultDateTime();
    }
    return $date;
  }

  /**
   * Set element default value and time zone.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem $item
   *   The date recur field item.
   */
  protected function createDateRecurDefaultValue(array &$element, DateRecurItem $item): void {
    $startDate = $item->start_date;
    $startDateEnd = $item->end_date;
    $timeZone = isset($item->timezone) ? new \DateTimeZone($item->timezone) : NULL;
    if ($timeZone) {
      $element['value']['#date_timezone'] = $element['end_value']['#date_timezone'] = $timeZone->getName();
      if ($startDate) {
        $startDate->setTimezone($timeZone);
        $element['value']['#default_value'] = $this->createDefaultValue($startDate, $timeZone->getName());
      }
      if ($startDateEnd) {
        $startDateEnd->setTimezone($timeZone);
        $element['end_value']['#default_value'] = $this->createDefaultValue($startDateEnd, $timeZone->getName());
      }
    }
  }

}
