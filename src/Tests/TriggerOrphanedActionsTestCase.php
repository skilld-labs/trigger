<?php
namespace Drupal\trigger\Tests;

/**
 * Test triggering an action that has since been removed.
 *
 * @group trigger
 */
class TriggerOrphanedActionsTestCase extends \Drupal\simpletest\WebTestBase {

  protected $profile = 'standard';

  public static function getInfo() {
    return [
      'name' => 'Trigger orphaned actions',
      'description' => 'Test triggering an action that has since been removed.',
      'group' => 'Trigger',
    ];
  }

  public function setUp() {
    parent::setUp('trigger', 'trigger_test');
  }

  public /**
   * Tests logic around orphaned actions.
   */
  function testActionsOrphaned() {
    $action = 'trigger_test_generic_any_action';
    $hash = drupal_hash_base64($action);

    // Assign an action from a disable-able module to a trigger, then pull the
    // trigger, and make sure the actions fire.
    $test_user = $this->drupalCreateUser([
      'administer actions'
      ]);
    $this->drupalLogin($test_user);
    $edit = ['aid' => $hash];
    $this->drupalPost('admin/structure/trigger/node', $edit, t('Assign'), [], [], 'trigger-node-presave-assign-form');

    // Create an unpublished node.
    $web_user = $this->drupalCreateUser([
      'create page content',
      'edit own page content',
      'access content',
      'administer nodes',
    ]);
    $this->drupalLogin($web_user);
    $edit = [];
    $langcode = \Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED;
    $edit["title"] = '!SimpleTest test node! ' . $this->randomName(10);
    $edit["body[$langcode][0][value]"] = '!SimpleTest test body! ' . $this->randomName(32) . ' ' . $this->randomName(32);
    $this->drupalPost('node/add/page', $edit, t('Save'));
    $this->assertRaw(t('!post %title has been created.', [
      '!post' => 'Basic page',
      '%title' => $edit["title"],
    ]), t('Make sure the Basic page has actually been created'));

    // Action should have been fired.
    $this->assertTrue(\Drupal::config('trigger.settings')->get('trigger_test_generic_any_action'), t('Trigger test action successfully fired.'));

    // Disable the module that provides the action and make sure the trigger
    // doesn't white screen.
    module_disable([
      'trigger_test'
      ]);
    $loaded_node = $this->drupalGetNodeByTitle($edit["title"]);
    $edit["body[$langcode][0][value]"] = '!SimpleTest test body! ' . $this->randomName(32) . ' ' . $this->randomName(32);
    $this->drupalPost("node/$loaded_node->nid/edit", $edit, t('Save'));

    // If the node body was updated successfully we have dealt with the
    // unavailable action.
    $this->assertRaw(t('!post %title has been updated.', [
      '!post' => 'Basic page',
      '%title' => $edit["title"],
    ]), t('Make sure the Basic page can be updated with the missing trigger function.'));
  }

}
