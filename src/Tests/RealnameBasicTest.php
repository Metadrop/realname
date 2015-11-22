<?php

/**
 * @file
 * Contains \Drupal\realname\Tests\RealnameBasicTest.
 */

namespace Drupal\realname\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Test basic functionality of Realname module.
 *
 * @group Realname
 */
class RealnameBasicTest extends WebTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['realname'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $permissions = array(
      'access administration pages',
      'administer modules',
      'administer realname',
      'administer site configuration',
      'administer users',
    );

    // User to set up google_analytics.
    $this->admin_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->admin_user);
    $this->verbose('<pre>' . print_r($this->admin_user, TRUE) . '</pre>');
    $this->verbose('<pre>' . print_r($this->drupalLogin($this->admin_user, TRUE)) . '</pre>');
  }

  /**
   * Test realname configuration.
   */
  public function testRealnameConfiguration() {
    // Check if Configure link is available on 'Modules' page.
    // Requires 'administer modules' permission.
    $this->drupalGet('admin/modules');
    $this->assertRaw('admin/config/people/realname', '[testRealnameConfiguration]: Configure link from Modules page to Realname settings page exists.');

    // Check for setting page's presence.
    $this->drupalGet('admin/config/people/realname');
    $this->assertRaw(t('Realname pattern'), '[testRealnameConfiguration]: Settings page displayed.');

    // Save form with allowed token.
    $edit['realname_pattern'] = '[user:name-raw]';
    $this->drupalPostForm('admin/config/people/realname', $edit, t('Save configuration'));
    $this->assertRaw(t('The configuration options have been saved.'), '[testRealnameConfiguration]: Settings form has been saved.');

    // Check if Configure link is available on 'Status Reports' page.
    // Requires 'administer site configuration' permission.
    $this->drupalGet('admin/reports/status');
    $this->assertRaw(t('E-mail: "Welcome (new user created by administrator)" template'), '[testRealnameConfiguration]: "Welcome (new user created by administrator)" template warning shown.');
    $this->assertRaw(t('E-mail: "Welcome (no approval required)" template'), '[testRealnameConfiguration]: "Welcome (no approval required)" template warning shown.');
    $this->assertRaw(t('E-mail: "Account activation" template'), '[testRealnameConfiguration]: "Account activation" template warning shown.');

    // Save form with allowed token.
    $edit['realname_pattern'] = '[user:name-raw]';
    $edit['realname_suppress_user_name_mail_validation'] = TRUE;
    $this->drupalPostForm('admin/config/people/realname', $edit, t('Save configuration'));
    $this->assertRaw(t('The configuration options have been saved.'), '[testRealnameConfiguration]: Settings form has been saved.');

    // Test suppress missing token warning in e-mail templates.
    $this->drupalGet('admin/reports/status');
    $this->assertNoRaw(t('E-mail: "Welcome (new user created by administrator)" template'), '[testRealnameConfiguration]: "Welcome (new user created by administrator)" template warning shown.');
    $this->assertNoRaw(t('E-mail: "Welcome (no approval required)" template'), '[testRealnameConfiguration]: "Welcome (no approval required)" template warning shown.');
    $this->assertNoRaw(t('E-mail: "Account activation" template'), '[testRealnameConfiguration]: "Account activation" template warning shown.');

    // Check token recursion protection.
    $edit['realname_pattern'] = '[user:name]';
    $this->drupalPostForm('admin/config/people/realname', $edit, t('Save configuration'));
    $this->assertRaw(t('The <em>[user:name]</em> token cannot be used as it will cause recursion.'), '[testRealnameConfiguration]: Invalid token found.');
  }

  /**
   * Test realname alter functions.
   */
  public function testRealnameUsernameAlter() {
    // Add a test string and see if core username has been replaced by realname.
    $edit['realname_pattern'] = '[user:name-raw] (UID: [user:uid])';
    $this->drupalPostForm('admin/config/people/realname', $edit, t('Save configuration'));

    $this->drupalGet('user/' . $this->admin_user->uid);
    $this->assertRaw($this->admin_user->name . ' (UID: ' . $this->admin_user->uid . ')', '[testRealnameUsernameAlter]: Real name shown on user page.');

    $this->drupalGet('user/' . $this->admin_user->uid . '/edit');
    $this->assertRaw($this->admin_user->name . ' (UID: ' . $this->admin_user->uid . ')', '[testRealnameUsernameAlter]: Real name shown on user edit page.');
  }

  /**
   * Test realname display configuration.
   */
  public function testRealnameManageDisplay() {
    $edit['realname_pattern'] = '[user:name-raw]';
    $this->drupalPostForm('admin/config/people/realname', $edit, t('Save configuration'));

    $this->drupalGet('admin/config/people/accounts/fields');
    $this->assertNoRaw('Real name', '[testRealnameManageDisplay]: Real name field not shown in manage fields list.');

    $this->drupalGet('admin/config/people/accounts/display');
    $this->assertRaw('Real name', '[testRealnameManageDisplay]: Real name field shown in manage display.');

    $this->drupalGet('user/' . $this->admin_user->uid);
    $this->assertText('Real name', '[testRealnameManageDisplay]: Real name field visible on user page.');
  }

}
