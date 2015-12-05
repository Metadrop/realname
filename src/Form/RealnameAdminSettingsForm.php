<?php
/**
 * @file
 * Contains \Drupal\realname\Form\RealnameAdminSettingsForm.
 */

namespace Drupal\realname\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Configure Realname settings for this site.
 */
class RealnameAdminSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'realname_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['realname.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('realname.settings');

    $form['general'] = [
      '#type' => 'fieldset',
      '#title' => t('General settings'),
    ];

    $note = '<div>';
    $note .= t('Note that if it is changed, all current Realnames will be deleted and the list in the database will be rebuilt as needed.');
    $note .= '</div>';

    $form['general']['realname_pattern'] = [
      '#type' => 'textfield',
      '#title' => t('Realname pattern'),
      '#default_value' => $config->get('pattern'),
      '#element_validate' => ['token_element_validate'],
      '#token_types' => ['user'],
      '#min_tokens' => 1,
      '#required' => TRUE,
      '#maxlength' => 256,
      '#description' => t('This pattern will be used to construct Realnames for all users.') . $note,
    ];
    // Add the token tree UI.
    $form['general']['token_help'] = [
      '#theme' => 'token_tree',
      '#token_types' => ['user'],
      '#global_types' => FALSE,
      '#dialog' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $pattern = $form_state->getValue('pattern');

    // Tokens that will cause recursion.
    $tokens = [
      '[user:name]',
    ];
    foreach ($tokens as $token) {
      if (strpos($pattern, $token) !== FALSE) {
        $form_state->setErrorByName('realname_pattern', t('The %token token cannot be used as it will cause recursion.', ['%token' => $token]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('realname.settings');

    if ($form['general']['realname_pattern']['#default_value'] != $form_state->getValue('realname_pattern')) {
      $config->set('pattern', $form_state->getValue('realname_pattern'))->save();
      // Only clear the realname cache if the pattern was changed.
      realname_delete_all();

      // A change to the display-name must invalidate the render cache
      // since the display-name could be used anywhere.
      Cache::invalidateTags(['rendered']);
    }

    parent::submitForm($form, $form_state);
  }

}
