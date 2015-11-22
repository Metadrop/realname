<?php
/**
 * @file
 * Contains \Drupal\realname\Form\RealnameAdminSettingsForm.
 */

namespace Drupal\realname\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

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

    $form['general'] = array(
      '#type' => 'fieldset',
      '#title' => t('General settings'),
    );

    $note = '<div>';
    $note .= t('Note that if it is changed, all current Realnames will be deleted and the list in the database will be rebuilt as needed.');
    $note .= '</div>';

    $form['general']['realname_pattern'] = array(
      '#type' => 'textfield',
      '#title' => t('Realname pattern'),
      '#default_value' => $config->get('pattern'),
      '#element_validate' => array('token_element_validate'),
      '#token_types' => array('user'),
      '#min_tokens' => 1,
      '#required' => TRUE,
      '#maxlength' => 256,
      '#description' => t('This pattern will be used to construct Realnames for all users.') . $note,
    );
    // Add the token tree UI.
    $form['general']['token_help'] = array(
      '#theme' => 'token_tree',
      '#token_types' => array('user'),
      '#global_types' => FALSE,
      '#dialog' => TRUE,
    );

    $form['advanced'] = array(
      '#type' => 'fieldset',
      '#title' => t('Advanded settings'),
    );
    $form['advanced']['realname_suppress_user_name_mail_validation'] = array(
      '#type' => 'checkbox',
      '#title' => t('Suppress missing token warning in e-mail templates'),
      '#description' => t('With Real name module enabled you need to replace the token <code>[user:name]</code> with <code>[user:name-raw]</code> in your <a href="@people">e-mail</a> templates. If you are running modules like <em>Email Registration</em> you may like to suppress this warning and use a different token.', array('@people' => url('admin/config/people/accounts', array('fragment' => 'edit-email-admin-created')))),
      '#default_value' => $config->get('suppress_user_name_mail_validation'),
    );

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
      '[user:account-name]',
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
    $config
      ->set('suppress_user_name_mail_validation', $form_state->getValue('realname_suppress_user_name_mail_validation'))
      ->save();

    if ($form['general']['realname_pattern']['#default_value'] != $form_state->getValue('realname_pattern')) {
      $config->set('pattern', $form_state->getValue('realname_pattern'))->save();
      // Only clear the realname cache if the pattern was changed.
      realname_delete_all();
    }

    parent::submitForm($form, $form_state);
  }

}
