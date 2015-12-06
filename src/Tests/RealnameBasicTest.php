<?php

/**
 * @file
 * Contains \Drupal\realname\Tests\RealnameBasicTest.
 */

namespace Drupal\realname\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\user\Entity\User;

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

    $permissions = [
      'access administration pages',
      'administer modules',
      'administer realname',
      'administer site configuration',
      'administer users',
    ];

    // User to set up realname.
    $this->admin_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->admin_user);
    //$this->verbose('<pre>' . print_r($this->admin_user, TRUE) . '</pre>');
    //$this->verbose('<pre>' . print_r($this->drupalLogin($this->admin_user, TRUE)) . '</pre>');
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
    $edit['realname_pattern'] = '[user:account-name]';
    $this->drupalPostForm('admin/config/people/realname', $edit, t('Save configuration'));
    $this->assertRaw(t('The configuration options have been saved.'), '[testRealnameConfiguration]: Settings form has been saved.');

    // Check token recursion protection.
    $edit['realname_pattern'] = '[user:name]';
    $this->drupalPostForm('admin/config/people/realname', $edit, t('Save configuration'));
    $this->assertRaw(t('The %token token cannot be used as it will cause recursion.', ['%token' => '[user:name]']), '[testRealnameConfiguration]: Invalid token found.');
  }

  /**
   * Test realname alter functions.
   */
  public function testRealnameUsernameAlter() {
    // Add a test string and see if core username has been replaced by realname.
    $edit['realname_pattern'] = '[user:account-name] (UID: [user:uid])';
    $this->drupalPostForm('admin/config/people/realname', $edit, t('Save configuration'));

    $this->drupalGet('user/' . $this->admin_user->id());
    $this->assertRaw($this->admin_user->name . ' (UID: ' . $this->admin_user->id() . ')', '[testRealnameUsernameAlter]: Real name shown on user page.');

    $this->drupalGet('user/' . $this->admin_user->id() . '/edit');
    $this->assertRaw($this->admin_user->name . ' (UID: ' . $this->admin_user->id() . ')', '[testRealnameUsernameAlter]: Real name shown on user edit page.');
  }

  /**
   * Test realname display configuration.
   */
  public function testRealnameManageDisplay() {
    $edit['realname_pattern'] = '[user:account-name]';
    $this->drupalPostForm('admin/config/people/realname', $edit, t('Save configuration'));

    $this->drupalGet('admin/config/people/accounts/fields');
    $this->assertNoRaw('Real name', '[testRealnameManageDisplay]: Real name field not shown in manage fields list.');

    $this->drupalGet('admin/config/people/accounts/display');
    $this->assertRaw('Real name', '[testRealnameManageDisplay]: Real name field shown in manage display.');

    $this->drupalGet('user/' . $this->admin_user->uid);
    $this->assertText('Real name', '[testRealnameManageDisplay]: Real name field visible on user page.');
  }

  /**
   * Test realname user update.
   */
  public function testRealnameUserUpdate() {
    $edit['realname_pattern'] = '[user:account-name]';
    $this->drupalPostForm('admin/config/people/realname', $edit, t('Save configuration'));

    $user1 = User::load($this->admin_user->id());
    $realname1 = $user1->realname;

    // Update user name.
    $user1->name = $this->randomMachineName();
    // @fixme: D8 Upgrade?
    user_save($user1);

    // Reload the user.
    $user2 = User::load($this->admin_user->id());
    $realname2 = $user2->realname;

    // Check if realname changed.
    $this->assertTrue($realname1);
    $this->assertTrue($realname2);
    $this->assertNotEqual($realname1, $realname2, '[testRealnameUserUpdate]: Real name changed.');
  }

}
