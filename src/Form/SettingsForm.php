<?php

namespace Drupal\subpathauto\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuTreeStorage;

/**
 * Defines a form that configures Subpathauto.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('subpathauto.settings');

    $form['depth'] = [
      '#type' => 'select',
      '#title' => $this->t('Maximum depth of sub-paths to alias'),
      '#options' => array_merge([0 => $this->t('Disabled')], range(1, MenuTreeStorage::MAX_DEPTH - 1)),
      '#default_value' => $config->get('depth'),
      '#description' => $this->t('Increasing this value may decrease performance.'),
    ];

    $form['process_admin_routes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Apply sub-paths aliases to admin routes.'),
      '#default_value' => $config->get('process_admin_routes'),
      '#description' => $this->t('This will enable routes like node/[id]/edit to also be altered.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->config('subpathauto.settings')
         ->set('depth', $values['depth'])
         ->set('process_admin_routes', $values['process_admin_routes'])
         ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'subpathauto_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['subpathauto.settings'];
  }

}
