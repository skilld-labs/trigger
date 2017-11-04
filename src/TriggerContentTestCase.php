<?php
namespace Drupal\trigger;

/**
 * Provides tests for node triggers.
 */
class TriggerContentTestCase extends TriggerWebTestCase {
  var $_cleanup_roles = array();
  var $_cleanup_users = array();

  public static function getInfo() {
    return array(
      'name' => 'Trigger content (node) actions',
      'description' => 'Perform various tests with content actions.',
      'group' => 'Trigger',
    );
  }

  function setUp() {
    parent::setUp('trigger', 'trigger_test');
  }

  /**
   * Tests several content-oriented trigger issues.
   *
   * These are in one function to assure they happen in the right order.
   */
  function testActionsContent() {
    $user = \Drupal::currentUser();
    $content_actions = array('node_publish_action', 'node_unpublish_action', 'node_make_sticky_action', 'node_make_unsticky_action', 'node_promote_action', 'node_unpromote_action');

    $test_user = $this->drupalCreateUser(array('administer actions'));
    $web_user = $this->drupalCreateUser(array('create page content', 'access content', 'administer nodes'));
    foreach ($content_actions as $action) {
      $hash = drupal_hash_base64($action);
      $info = $this->actionInfo($action);

      // Assign an action to a trigger, then pull the trigger, and make sure
      // the actions fire.
      $this->drupalLogin($test_user);
      $edit = array('aid' => $hash);
      $this->drupalPost('admin/structure/trigger/node', $edit, t('Assign'), array(), array(), 'trigger-node-presave-assign-form');
      // Create an unpublished node.
      $this->drupalLogin($web_user);
      $edit = array();
      $langcode = \Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED;
      $edit["title"] = '!SimpleTest test node! ' . $this->randomName(10);
      $edit["body[$langcode][0][value]"] = '!SimpleTest test body! ' . $this->randomName(32) . ' ' . $this->randomName(32);
      $edit[$info['property']] = !$info['expected'];
      $this->drupalPost('node/add/page', $edit, t('Save'));
      // Make sure the text we want appears.
      $this->assertRaw(t('!post %title has been created.', array('!post' => 'Basic page', '%title' => $edit["title"])), t('Make sure the Basic page has actually been created'));
      // Action should have been fired.
      $loaded_node = $this->drupalGetNodeByTitle($edit["title"]);
      $this->assertTrue($loaded_node->$info['property'] == $info['expected'], t('Make sure the @action action fired.', array('@action' => $info['name'])));
      // Leave action assigned for next test

      // There should be an error when the action is assigned to the trigger
      // twice.
      $this->drupalLogin($test_user);
      // This action already assigned in this test.
      $edit = array('aid' => $hash);
      $this->drupalPost('admin/structure/trigger/node', $edit, t('Assign'), array(), array(), 'trigger-node-presave-assign-form');
      $this->assertRaw(t('The action you chose is already assigned to that trigger.'), t('Check to make sure an error occurs when assigning an action to a trigger twice.'));

      // The action should be able to be unassigned from a trigger.
      $this->drupalPost('admin/structure/trigger/unassign/node/node_presave/' . $hash, array(), t('Unassign'));
      $this->assertRaw(t('Action %action has been unassigned.', array('%action' => ucfirst($info['name']))), t('Check to make sure the @action action can be unassigned from the trigger.', array('@action' => $info['name'])));
      $assigned = db_query("SELECT COUNT(*) FROM {trigger_assignments} WHERE aid IN (:keys)", array(':keys' => $content_actions))->fetchField();
      $this->assertFalse($assigned, t('Check to make sure unassign worked properly at the database level.'));
    }
  }

  /**
   * Tests multiple node actions.
   *
   * Verifies that node actions are fired for each node individually, if acting
   * on multiple nodes.
   */
  function testActionContentMultiple() {
    // Assign an action to the node save/update trigger.
    $test_user = $this->drupalCreateUser(array('administer actions', 'administer nodes', 'create page content', 'access administration pages', 'access content overview'));
    $this->drupalLogin($test_user);

    for ($index = 0; $index < 3; $index++) {
      $edit = array('title' => $this->randomName());
      $this->drupalPost('node/add/page', $edit, t('Save'));
    }

    $action_id = 'trigger_test_generic_any_action';
    $hash = drupal_hash_base64($action_id);
    $edit = array('aid' => $hash);
    $this->drupalPost('admin/structure/trigger/node', $edit, t('Assign'), array(), array(), 'trigger-node-update-assign-form');

    $edit = array(
      'operation' => 'unpublish',
      'nodes[1]' => TRUE,
      'nodes[2]' => TRUE,
    );
    $this->drupalPost('admin/content', $edit, t('Update'));
    $count = variable_get('trigger_test_generic_any_action', 0);
    $this->assertTrue($count == 2, t('Action was triggered 2 times. Actual: %count', array('%count' => $count)));
  }

  /**
   * Returns some info about each of the content actions.
   *
   * This is helper function for testActionsContent().
   *
   * @param $action
   *   The name of the action to return info about.
   *
   * @return
   *   An associative array of info about the action.
   */
  function actionInfo($action) {
    $info = array(
      'node_publish_action' => array(
        'property' => 'status',
        'expected' => 1,
        'name' => t('publish content'),
      ),
      'node_unpublish_action' => array(
        'property' => 'status',
        'expected' => 0,
        'name' => t('unpublish content'),
      ),
      'node_make_sticky_action' => array(
        'property' => 'sticky',
        'expected' => 1,
        'name' => t('make content sticky'),
      ),
      'node_make_unsticky_action' => array(
        'property' => 'sticky',
        'expected' => 0,
        'name' => t('make content unsticky'),
      ),
      'node_promote_action' => array(
        'property' => 'promote',
        'expected' => 1,
        'name' => t('promote content to front page'),
      ),
      'node_unpromote_action' => array(
        'property' => 'promote',
        'expected' => 0,
        'name' => t('remove content from front page'),
      ),
    );
    return $info[$action];
  }
}
