<?php
namespace Drupal\trigger;

/**
 * Tests token substitution in trigger actions.
 *
 * This tests nearly every permutation of user triggers with system actions
 * and checks the token replacement.
 */
class TriggerUserActionTestCase extends TriggerActionTestCase {
  public static function getInfo() {
    return array(
      'name' => 'Test user actions',
      'description' => 'Test user actions.',
      'group' => 'Trigger',
    );
  }

  /**
   * Tests user action assignment and execution.
   */
  function testUserActionAssignmentExecution() {
    $test_user = $this->drupalCreateUser(array('administer actions', 'create article content', 'access comments', 'administer comments', 'skip comment approval', 'edit own comments'));
    $this->drupalLogin($test_user);

    $triggers = array('comment_presave', 'comment_insert', 'comment_update');
    // system_block_ip_action is difficult to test without ruining the test.
    $actions = array('user_block_user_action');
    foreach ($triggers as $trigger) {
      foreach ($actions as $action) {
        $this->assignSimpleAction($trigger, $action);
      }
    }

    $node = $this->drupalCreateNode(array('type' => 'article'));
    $this->drupalPost("node/{$node->nid}", array('comment_body[und][0][value]' => t("my comment"), 'subject' => t("my comment subject")), t('Save'));
    // Posting a comment should have blocked this user.
    $account = user_load($test_user->uid, TRUE);
    $this->assertTrue($account->status == 0, t('Account is blocked'));
    $comment_author_uid = $account->uid;
    // Now rehabilitate the comment author so it can be be blocked again when
    // the comment is updated.
    user_save($account, array('status' => TRUE));

    $test_user = $this->drupalCreateUser(array('administer actions', 'create article content', 'access comments', 'administer comments', 'skip comment approval', 'edit own comments'));
    $this->drupalLogin($test_user);

    // Our original comment will have been comment 1.
    $this->drupalPost("comment/1/edit", array('comment_body[und][0][value]' => t("my comment, updated"), 'subject' => t("my comment subject")), t('Save'));
    $comment_author_account = user_load($comment_author_uid, TRUE);
    $this->assertTrue($comment_author_account->status == 0, t('Comment author account (uid=@uid) is blocked after update to comment', array('@uid' => $comment_author_uid)));

    // Verify that the comment was updated.
    $test_user = $this->drupalCreateUser(array('administer actions', 'create article content', 'access comments', 'administer comments', 'skip comment approval', 'edit own comments'));
    $this->drupalLogin($test_user);

    $this->drupalGet("node/$node->nid");
    $this->assertText(t("my comment, updated"));
    $this->verboseEmail();
  }
}
