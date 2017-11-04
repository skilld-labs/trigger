<?php
namespace Drupal\trigger\Tests;

/**
 * Test assigning new triggers using the administration form.
 *
 * @group trigger
 */
class TriggerAssignFormTestCase extends \Drupal\simpletest\WebTestBase {

  protected $profile = 'standard';

  public static function getInfo() {
    return [
      'name' => 'Trigger assignment form',
      'description' => 'Test assigning new triggers using the administration form.',
      'group' => 'Trigger',
    ];
  }

  public function setUp() {
    parent::setUp('trigger', 'trigger_test');
  }

  public /**
   * Tests submitting an action for a trigger with a long name.
   */
  function testLongTrigger() {
    $test_user = $this->drupalCreateUser(['administer actions']);
    $this->drupalLogin($test_user);
    $action = 'trigger_test_generic_any_action';
    $hash = drupal_hash_base64($action);

    // Make sure a long hook name can be inserted.
    $edit = ['aid' => $hash];
    $this->drupalPost('admin/structure/trigger/trigger_test', $edit, t('Assign'), [], [], 'trigger-trigger-test-in-the-day-we-sweat-it-out-in-the-streets-of-a-runaway-american-dream-assign-form');

    $this->assertText(t('Generic test action for any trigger'));
  }

}
