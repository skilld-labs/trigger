<?php
namespace Drupal\trigger;

/**
 * Tests other triggers.
 */
class TriggerOtherTestCase extends TriggerWebTestCase {
  var $_cleanup_roles = array();
  var $_cleanup_users = array();

  public static function getInfo() {
    return array(
      'name' => 'Trigger other actions',
      'description' => 'Test triggering of user, comment, taxonomy actions.',
      'group' => 'Trigger',
    );
  }

  function setUp() {
    parent::setUp('trigger', 'trigger_test', 'contact');
  }

  /**
   * Tests triggering on user create and user login.
   */
  function testActionsUser() {
    // Assign an action to the create user trigger.
    $test_user = $this->drupalCreateUser(array('administer actions'));
    $this->drupalLogin($test_user);
    $action_id = 'trigger_test_generic_action';
    $hash = drupal_hash_base64($action_id);
    $edit = array('aid' => $hash);
    $this->drupalPost('admin/structure/trigger/user', $edit, t('Assign'), array(), array(), 'trigger-user-insert-assign-form');

    // Set action variable to FALSE.
    variable_set($action_id, FALSE);

    // Create an unblocked user
    $web_user = $this->drupalCreateUser(array('administer users'));
    $this->drupalLogin($web_user);
    $name = $this->randomName();
    $pass = user_password();
    $edit = array();
    $edit['name'] = $name;
    $edit['mail'] = $name . '@example.com';
    $edit['pass[pass1]'] = $pass;
    $edit['pass[pass2]'] = $pass;
    $edit['status'] = 1;
    $this->drupalPost('admin/people/create', $edit, t('Create new account'));

    // Verify that the action variable has been set.
    $this->assertTrue(variable_get($action_id, FALSE), t('Check that creating a user triggered the test action.'));

    // Reset the action variable.
    variable_set($action_id, FALSE);

    $this->drupalLogin($test_user);
    // Assign a configurable action 'System message' to the user_login trigger.
    $action_edit = array(
      'actions_label' => $this->randomName(16),
      'message' => t("You have logged in:") . $this->randomName(16),
    );

    // Configure an advanced action that we can assign.
    $aid = $this->configureAdvancedAction('system_message_action', $action_edit);
    $edit = array('aid' => drupal_hash_base64($aid));
    $this->drupalPost('admin/structure/trigger/user', $edit, t('Assign'), array(), array(), 'trigger-user-login-assign-form');

    // Verify that the action has been assigned to the correct hook.
    $actions = trigger_get_assigned_actions('user_login');
    $this->assertEqual(1, count($actions), t('One Action assigned to the hook'));
    $this->assertEqual($actions[$aid]['label'], $action_edit['actions_label'], t('Correct action label found.'));

    // User should get the configured message at login.
    $contact_user = $this->drupalCreateUser(array('access site-wide contact form'));;
    $this->drupalLogin($contact_user);
    $this->assertText($action_edit['message']);
  }

  /**
   * Tests triggering on comment save.
   */
  function testActionsComment() {
    // Assign an action to the comment save trigger.
    $test_user = $this->drupalCreateUser(array('administer actions'));
    $this->drupalLogin($test_user);
    $action_id = 'trigger_test_generic_action';
    $hash = drupal_hash_base64($action_id);
    $edit = array('aid' => $hash);
    $this->drupalPost('admin/structure/trigger/comment', $edit, t('Assign'), array(), array(), 'trigger-comment-insert-assign-form');

    // Set action variable to FALSE.
    variable_set($action_id, FALSE);

    // Create a node and add a comment to it.
    $web_user = $this->drupalCreateUser(array('create article content', 'access content', 'skip comment approval', 'post comments'));
    $this->drupalLogin($web_user);
    $node = $this->drupalCreateNode(array('type' => 'article', 'promote' => 1));
    $edit = array();
    $edit['subject'] = $this->randomName(10);
    $edit['comment_body[' . \Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED . '][0][value]'] = $this->randomName(10) . ' ' . $this->randomName(10);
    $this->drupalGet('comment/reply/' . $node->nid);
    $this->drupalPost(NULL, $edit, t('Save'));

    // Verify that the action variable has been set.
    $this->assertTrue(variable_get($action_id, FALSE), t('Check that creating a comment triggered the action.'));
  }

  /**
   * Tests triggering on taxonomy new term.
   */
  function testActionsTaxonomy() {
    // Assign an action to the taxonomy term save trigger.
    $test_user = $this->drupalCreateUser(array('administer actions'));
    $this->drupalLogin($test_user);
    $action_id = 'trigger_test_generic_action';
    $hash = drupal_hash_base64($action_id);
    $edit = array('aid' => $hash);
    $this->drupalPost('admin/structure/trigger/taxonomy', $edit, t('Assign'), array(), array(), 'trigger-taxonomy-term-insert-assign-form');

    // Set action variable to FALSE.
    variable_set($action_id, FALSE);

    // Create a taxonomy vocabulary and add a term to it.

    // Create a vocabulary.
    $vocabulary = new stdClass();
    $vocabulary->name = $this->randomName();
    $vocabulary->description = $this->randomName();
    $vocabulary->machine_name = drupal_strtolower($this->randomName());
    $vocabulary->help = '';
    $vocabulary->nodes = array('article' => 'article');
    $vocabulary->weight = mt_rand(0, 10);
    taxonomy_vocabulary_save($vocabulary);

    $term = new stdClass();
    $term->name = $this->randomName();
    $term->vid = $vocabulary->vid;
    taxonomy_term_save($term);

    // Verify that the action variable has been set.
    $this->assertTrue(variable_get($action_id, FALSE), t('Check that creating a taxonomy term triggered the action.'));
  }

}
