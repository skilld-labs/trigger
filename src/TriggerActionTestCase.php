<?php
namespace Drupal\trigger;

/**
 * Provides a base class with trigger assignments and test comparisons.
 */
class TriggerActionTestCase extends TriggerWebTestCase {

  function setUp() {
    parent::setUp('trigger');
  }

  /**
   * Creates a message with tokens.
   *
   * @param $trigger
   *
   * @return
   *   A message with embedded tokens.
   */
  function generateMessageWithTokens($trigger) {
    // Note that subject is limited to 254 characters in action configuration.
    $message = t('Action was triggered by trigger @trigger user:name=[user:name] user:uid=[user:uid] user:mail=[user:mail] user:url=[user:url] user:edit-url=[user:edit-url] user:created=[user:created]',
      array('@trigger' => $trigger));
    return trim($message);
  }

  /**
   * Generates a comparison message to match the pre-token-replaced message.
   *
   * @param $trigger
   *   Trigger, like 'user_login'.
   * @param $account
   *   Associated user account.
   *
   * @return
   *   The token-replaced equivalent message. This does not use token
   *   functionality.
   *
   * @see generateMessageWithTokens()
   */
  function generateTokenExpandedComparison($trigger, $account) {
    // Note that user:last-login was omitted because it changes and can't
    // be properly verified.
    $message = t('Action was triggered by trigger @trigger user:name=@username user:uid=@uid user:mail=@mail user:url=@user_url user:edit-url=@user_edit_url user:created=@user_created',
       array(
        '@trigger' => $trigger,
        '@username' => $account->name,
        '@uid' => !empty($account->uid) ? $account->uid : t('not yet assigned'),
        '@mail' => $account->mail,
        '@user_url' => !empty($account->uid) ? url("user/$account->uid", array('absolute' => TRUE)) : t('not yet assigned'),
        '@user_edit_url' => !empty($account->uid) ? url("user/$account->uid/edit", array('absolute' => TRUE)) : t('not yet assigned'),
        '@user_created' => isset($account->created) ? format_date($account->created, 'medium') : t('not yet created'),
        )
      );
      return trim($message);
  }


  /**
   * Assigns a simple (non-configurable) action to a trigger.
   *
   * @param $trigger
   *   The trigger to assign to, like 'user_login'.
   * @param $action
   *   The simple action to be assigned, like 'comment_insert'.
   */
  function assignSimpleAction($trigger, $action) {
    $form_name = "trigger_{$trigger}_assign_form";
    $form_html_id = strtr($form_name, '_', '-');
    $edit = array('aid' => drupal_hash_base64($action));
    $trigger_type = preg_replace('/_.*/', '', $trigger);
    $this->drupalPost("admin/structure/trigger/$trigger_type", $edit, t('Assign'), array(), array(), $form_html_id);
    $actions = trigger_get_assigned_actions($trigger);
    $this->assertTrue(!empty($actions[$action]), t('Simple action @action assigned to trigger @trigger', array('@action' => $action, '@trigger' => $trigger)));
  }

  /**
   * Assigns a system message action to the passed-in trigger.
   *
   * @param $trigger
   *   For example, 'user_login'
   */
  function assignSystemMessageAction($trigger) {
    $form_name = "trigger_{$trigger}_assign_form";
    $form_html_id = strtr($form_name, '_', '-');
    // Assign a configurable action 'System message' to the passed trigger.
    $action_edit = array(
      'actions_label' => $trigger . "_system_message_action_" . $this->randomName(16),
      'message' => $this->generateMessageWithTokens($trigger),
    );

    // Configure an advanced action that we can assign.
    $aid = $this->configureAdvancedAction('system_message_action', $action_edit);

    $edit = array('aid' => drupal_hash_base64($aid));
    $this->drupalPost('admin/structure/trigger/user', $edit, t('Assign'), array(), array(), $form_html_id);
  }


  /**
   * Assigns a system_send_email_action to the passed-in trigger.
   *
   * @param $trigger
   *   For example, 'user_login'
   */
  function assignSystemEmailAction($trigger) {
    $form_name = "trigger_{$trigger}_assign_form";
    $form_html_id = strtr($form_name, '_', '-');

    $message = $this->generateMessageWithTokens($trigger);
    // Assign a configurable action 'System message' to the passed trigger.
    $action_edit = array(
      // 'actions_label' => $trigger . "_system_send_message_action_" . $this->randomName(16),
      'actions_label' => $trigger . "_system_send_email_action",
      'recipient' => '[user:mail]',
      'subject' => $message,
      'message' => $message,
    );

    // Configure an advanced action that we can assign.
    $aid = $this->configureAdvancedAction('system_send_email_action', $action_edit);

    $edit = array('aid' => drupal_hash_base64($aid));
    $this->drupalPost('admin/structure/trigger/user', $edit, t('Assign'), array(), array(), $form_html_id);
  }

  /**
   * Asserts correct token replacement in both system message and email.
   *
   * @param $trigger
   *   A trigger like 'user_login'.
   * @param $account
   *   The user account which triggered the action.
   * @param $email_depth
   *   Number of emails to scan, starting with most recent.
   */
  function assertSystemMessageAndEmailTokenReplacement($trigger, $account, $email_depth = 1) {
    $this->assertSystemMessageTokenReplacement($trigger, $account);
    $this->assertSystemEmailTokenReplacement($trigger, $account, $email_depth);
  }

  /**
   * Asserts correct token replacement for the given trigger and account.
   *
   * @param $trigger
   *   A trigger like 'user_login'.
   * @param $account
   *   The user account which triggered the action.
   */
  function assertSystemMessageTokenReplacement($trigger, $account) {
    $expected = $this->generateTokenExpandedComparison($trigger, $account);
    $this->assertText($expected,
      t('Expected system message to contain token-replaced text "@expected" found in configured system message action', array('@expected' => $expected )) );
  }


  /**
   * Asserts correct token replacement for the given trigger and account.
   *
   * @param $trigger
   *   A trigger like 'user_login'.
   * @param $account
   *   The user account which triggered the action.
   * @param $email_depth
   *   Number of emails to scan, starting with most recent.
   */
  function assertSystemEmailTokenReplacement($trigger, $account, $email_depth = 1) {
    $this->verboseEmail($email_depth);
    $expected = $this->generateTokenExpandedComparison($trigger, $account);
    $this->assertMailString('subject', $expected, $email_depth);
    $this->assertMailString('body', $expected, $email_depth);
    $this->assertMail('to', $account->mail, t('Mail sent to correct destination'));
  }
}
