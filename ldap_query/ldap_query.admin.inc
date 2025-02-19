<?php

/**
 * @file
 * Defines functions for administrative interface for servers.
 */

/**
 * @return string
 *   Index of ldap queries
 */
function ldap_query_index() {

  return ldap_query_list(NULL, TRUE);

}

/**
 * LDAP queries list.
 *
 * @param $qid
 *   query id.  if empty list all queries
 * @param $actions
 *   True or False indicating if query edit, delete, enable, disable, etc links should show in table
 *
 * @return
 *   The HTML table with the servers list.
 */
function ldap_query_list($qid = NULL, $actions = FALSE) {

  $ldap_queries = ldap_query_get_queries('all', $qid);
  $variables = [
    'ldap_queries' => $ldap_queries,
    'action' => TRUE,
    'type' => 'table',
  ];

  $html = theme('ldap_query_list', $variables);

  return $html;
}

/**
 * Implements the LDAP query edit page.
 *
 * @param $form_state
 *   A form state array.
 * @param $op
 *   An operatin - add or edit.
 * @param $sid
 *   A LDAP server ID.
 *
 * @return
 *   The form structure.
 */
function ldap_query_admin_form($form, &$form_state, $op = NULL, $qid = NULL) {
  ldap_servers_module_load_include('php', 'ldap_query', 'LdapQueryAdmin.class');
  $server = new LdapQueryAdmin($qid);
  $form = $server->drupalForm($op);
  return $form;
}

/**
 * Validate hook for the LDAP server form.
 */
function ldap_query_admin_form_validate($form, &$form_state) {
  $op = drupal_strtolower($form_state['clicked_button']['#value']);
  ldap_servers_module_load_include('php', 'ldap_query', 'LdapQueryAdmin.class');
  $server = new LdapQueryAdmin($form_state['values']['qid']);

  $errors = $server->drupalFormValidate($op, $form_state['values']);
  foreach ($errors as $error_name => $error_text) {
    form_set_error($error_name, t($error_text));
  }
}

/**
 * Submit hook for the LDAP server form.
 */
function ldap_query_admin_form_submit($form, &$form_state) {
  $button = drupal_strtolower($form_state['clicked_button']['#value']);
  $op = ($button == 'add') ? 'add' : 'edit';
  $verb = ($op == 'edit') ? 'edited' : $op . 'ed';
  ldap_servers_module_load_include('php', 'ldap_query', 'LdapQueryAdmin.class');
  $query = new LdapQueryAdmin($form_state['values']['qid']);
  // Add form data to object and save or create.
  $query->drupalFormSubmit($op, $form_state['values']);

  if ($query->hasError() == FALSE) {
    drupal_set_message(t('LDAP Query %name !verb.', ['!verb' => $verb, '%name' => $query->name]), 'status');
    ldap_query_cache_clear();
    // If ($form_state['ldap_warnings'] && $op != 'add') {
    // do nothing, but don't redirect away from form.
    // if there are warnings, want them to see form even if its been saved
    // }
    // else {.
    drupal_goto(LDAP_QUERY_MENU_BASE_PATH . '/query');
    // }.
  }
  else {
    form_set_error($query->errorName(), $query->errorMsg());
    $query->clearError();
  }
  ldap_query_cache_clear();
}

/**
 * Implements the LDAP query delete page.
 *
 * @param $form_state
 *   A form state array.
 * @param $sid
 *   A LDAP server ID.
 *
 * @return
 *   The form structure.
 */
function ldap_query_admin_delete($form, &$form_state, $op = NULL, $qid = NULL) {

  if ($qid && ($ldap_query = ldap_query_get_queries('all', $qid, true))) {
    // array()
    $variables = [
      'ldap_query' => $ldap_query,
      'actions' => FALSE,
      'type' => 'detail',
    ];
    $form['#prefix'] = '<div>' . theme('ldap_query', $variables) . '</div>';
    $form['qid'] = [
      '#type' => 'hidden',
      '#value' => $qid,
    ];
    $form['name'] = [
      '#type' => 'hidden',
      '#value' => $ldap_query->name,
    ];
    return confirm_form(
      $form,
      'Delete Confirmation Form',
      LDAP_QUERY_MENU_BASE_PATH . '/query/list',
        '<p>' . t('Are you sure you want to delete the LDAP query named <em><strong>%name</strong></em> ?', ['%name' => $ldap_query->name]) . '</p><p>' .
        t('This action cannot be undone.') . '</p>',
      t('Delete'),
      t('Cancel')
    );
  }
  drupal_goto(LDAP_QUERY_MENU_BASE_PATH . '/query/list');
}

/**
 * Submit handler for the LDAP server delete page.
 */
function ldap_query_admin_delete_submit($form, &$form_state) {
  $values = $form_state['values'];
  $qid = $values['qid'];
  ldap_servers_module_load_include('php', 'ldap_query', 'LdapQueryAdmin.class');
  $ldap_query = new LdapQueryAdmin($qid);
  if ($values['confirm'] && $qid) {
    if ($result = $ldap_query->delete($qid)) {
      $tokens = ['%name' => $ldap_query->name, '!qid' => $qid];
      drupal_set_message(t('LDAP Server %name (query id = !qid) has been deleted.', $tokens), 'status');
      watchdog('ldap_query', 'LDAP Query deleted: %name (qid = !qid) ', $tokens);
    }
    else {
      drupal_set_message(t('LDAP Query delete failed.'), 'warning');
    }
  }
  else {
    drupal_set_message(t('LDAP Query delete cancelled.'), 'status');
  }
  ldap_query_cache_clear();
  drupal_goto(LDAP_QUERY_MENU_BASE_PATH . '/query/list');
}

/**
 * Implements the LDAP query disable.
 *
 * @param $form_state
 *   A form state array.
 * @param $qid
 *   A LDAP query ID.
 *
 * @return
 *   The form structure.
 */
function ldap_query_admin_enable_disable($form, &$form_state, $action = NULL, $qid = NULL) {

  if ($ldap_query = ldap_query_get_queries('all', $qid, true)) {
    $variables = [
      'ldap_query' => $ldap_query,
      'actions' => FALSE,
      'type' => 'detail',
    ];
    $form['#prefix'] = "<div>" . theme('ldap_query', $variables) . "</div>";

    $form['qid'] = [
      '#type' => 'hidden',
      '#value' => $qid,
    ];
    $form['name'] = [
      '#type' => 'hidden',
      '#value' => $ldap_query->name,
    ];
    $form['action'] = [
      '#type' => 'hidden',
      '#value' => $action,
    ];
    return confirm_form(
      $form,
      t('Are you sure you want to') . t($action) . ' ' . t('the LDAP query named <em><strong>%name</strong></em>?', ['%name' => $ldap_query->name]),
      LDAP_QUERY_MENU_BASE_PATH . '/query/list',
      t('<p></p>'),
      t($action),
      t('Cancel')
    );
  }

}

/**
 * Submit hook for the LDAP server delete page.
 */
function ldap_query_admin_enable_disable_submit($form, &$form_state) {
  $values = $form_state['values'];
  $qid = $values['qid'];
  $status = ($values['action'] == 'enable') ? 1 : 0;
  if ($values['confirm'] && $qid) {

    $form_state['redirect'] = LDAP_QUERY_MENU_BASE_PATH . '/query';
    $ldap_query = new LdapQueryAdmin($qid);

    $ldap_query->status = $status;
    $ldap_query->save('edit');
    $tokens = ['%name' => $values['name'], '!qid' => $qid, '!action' => t($values['action']) . 'd'];
    drupal_set_message(t('LDAP Query Configuration %name (query id = !qid) has been !action.', $tokens));
    $message = t('LDAP Query !action: %name (qid = !qid) ', $tokens);
    watchdog('ldap_query', $message);

  }

}

/**
 *
 */
function ldap_query_test($qid) {

  $ldap_query = ldap_query_get_queries('all', $qid, true);
  $results = $ldap_query->query();
  $display = theme('ldap_query_results', ['results' => $results, 'ldap_query' => $ldap_query, 'show_query' => TRUE]);

  return $display;

}
