<?php
namespace Drupal\trigger;

/**
 * Tests cron trigger.
 */
class TriggerCronTestCase extends TriggerWebTestCase {
  public static function getInfo() {
    return array(
      'name' => 'Trigger cron (system) actions',
      'description' => 'Perform various tests with cron trigger.',
      'group' => 'Trigger',
    );
  }

  function setUp() {
    parent::setUp('trigger', 'trigger_test');
  }

  /**
   * Tests assigning multiple actions to the cron trigger.
   *
   * This test ensures that both simple and multiple complex actions
   * succeed properly. This is done in the cron trigger test because
   * cron allows passing multiple actions in at once.
   */
  function testActionsCron() {
    // Create an administrative user.
    $test_user = $this->drupalCreateUser(array('administer actions'));
    $this->drupalLogin($test_user);

    // Assign a non-configurable action to the cron run trigger.
    $edit = array('aid' => drupal_hash_base64('trigger_test_system_cron_action'));
    $this->drupalPost('admin/structure/trigger/system', $edit, t('Assign'), array(), array(), 'trigger-cron-assign-form');

    // Assign a configurable action to the cron trigger.
    $action_label = $this->randomName();
    $edit = array(
      'actions_label' => $action_label,
      'subject' => $action_label,
    );
    $aid = $this->configureAdvancedAction('trigger_test_system_cron_conf_action', $edit);
    // $aid is likely 3 but if we add more uses for the sequences table in
    // core it might break, so it is easier to get the value from the database.
    $edit = array('aid' => drupal_hash_base64($aid));
    $this->drupalPost('admin/structure/trigger/system', $edit, t('Assign'), array(), array(), 'trigger-cron-assign-form');

    // Add a second configurable action to the cron trigger.
    $action_label = $this->randomName();
    $edit = array(
      'actions_label' => $action_label,
      'subject' => $action_label,
    );
    $aid = $this->configureAdvancedAction('trigger_test_system_cron_conf_action', $edit);
    $edit = array('aid' => drupal_hash_base64($aid));
    $this->drupalPost('admin/structure/trigger/system', $edit, t('Assign'), array(), array(), 'trigger-cron-assign-form');

    // Force a cron run.
    $this->cronRun();

    // Make sure the non-configurable action has fired.
    $action_run = variable_get('trigger_test_system_cron_action', FALSE);
    $this->assertTrue($action_run, t('Check that the cron run triggered the test action.'));

    // Make sure that both configurable actions have fired.
    $action_run = variable_get('trigger_test_system_cron_conf_action', 0) == 2;
    $this->assertTrue($action_run, t('Check that the cron run triggered both complex actions.'));
  }
}
