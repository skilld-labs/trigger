<?php
namespace Drupal\trigger\Tests;

/**
 * Provides common helper methods.
 */
class TriggerWebTestCase extends \Drupal\simpletest\WebTestBase {

  protected $profile = 'standard';

  /**
   * Configure an advanced action.
   *
   * @param $action
   *   The name of the action callback. For example: 'user_block_user_action'
   * @param $edit
   *   The $edit array for the form to be used to configure.
   *   Example members would be 'actions_label' (always), 'message', etc.
   *
   * @return
   *   the aid (action id) of the configured action, or FALSE if none.
   */
  protected function configureAdvancedAction($action, $edit) {
    // Create an advanced action.
    $hash = drupal_hash_base64($action);
    $this->drupalPost("admin/config/system/actions/configure/$hash", $edit, t('Save'));
    $this->assertText(t('The action has been successfully saved.'));

    // Now we have to find out the action ID of what we created.
    return db_query('SELECT aid FROM {actions} WHERE callback = :callback AND label = :label', [
      ':callback' => $action,
      ':label' => $edit['actions_label'],
    ])->fetchField();
  }

}
