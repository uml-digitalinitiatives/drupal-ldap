<?php

/**
 * @file
 * Functions related to REST webservices for LDAP User module.
 *
 * DO NOT USE THIS CODE, it is unsupported and only left for those relying on
 * these functions already.
 * 
 * @codingStandardsIgnoreFile
 */

/**
 * @deprecated
 */
function _ldap_user_ws_urls_item_list() {
  global $base_url;
  $base = '<br/>' . $base_url . '/' . LDAP_USER_WS_USER_PATH;
  $ldap_user_conf = new LdapUserConf();
  $key = $ldap_user_conf->wsKey;
  $item_list = [
    'Create: Drupal User based on LDAP Entry: ' . $base . "/create/todrupal/[username]|[dn]/$key",
    'Synch:  LDAP Entry to Drupal User: ' . $base . "/synch/todrupal/[username]|[dn]/$key",
    'Disable:  Drupal User: ' . $base . "/disable/todrupal/[username]|[dn]/$key",
    'Delete:  Drupal User: ' . $base . "/delete/todrupal/[username]|[dn]/$key",
    'Create:  LDAP Entry based on Drupal User: ' . $base . "/create/toldap/[username]/$key",
    'Synch:  Drupal User to LDAP Entry: ' . $base . "/synch/toldap/[username]/$key",
    'Query: LDAP Associated Drupal User Exists: ' . $base . "/query/none/[username]|[dn]/$key",
  ];
  return $item_list;
}

/**
 * @deprecated
 */
function ldap_user_ws($action, $direction_tag, $drupal_user_name_or_dn, $key) {

  $action = check_plain($action);
  $key = urldecode($key);

  if (!$ldap_user_conf->wsEnabled) {
    return ldap_user_ws_out([0, t('Webservice Not Enabled')]);
  }
  // ldap_servers_encrypt($ldap_user_conf->wsKey, LDAP_SERVERS_ENC_TYPE_BLOWFISH)
  elseif ($key != $ldap_user_conf->wsKey) {
    return ldap_user_ws_out([0, t('Bad Webservice Key')]);
  }
  elseif (!in_array($_SERVER['REMOTE_ADDR'], array_values($ldap_user_conf->wsUserIps))) {
    return ldap_user_ws_out([0, t('Request from non-allowed IP Address')]);
  }

  if ($direction_tag == 'todrupal') {
    $direction = LDAP_USER_PROV_DIRECTION_TO_DRUPAL_USER;
    $sid = $ldap_user_conf->drupalAcctProvisionServer;
    $ldap_server = ldap_servers_get_servers($sid, NULL, TRUE);
  }
  if ($direction_tag == 'toldap') {
    $direction = LDAP_USER_PROV_DIRECTION_TO_LDAP_ENTRY;
    $sid = $ldap_user_conf->ldapEntryProvisionServer;
    $ldap_server = ldap_servers_get_servers($sid, NULL, TRUE);
  }
  else {
    $direction = LDAP_USER_PROV_DIRECTION_NONE;
    $sid = LDAP_USER_NO_SERVER_SID;
    $ldap_server = FALSE;
  }

  if (strpos($drupal_user_name_or_dn, '=') === FALSE) {
    $drupal_user_name = check_plain($drupal_user_name_or_dn);
  }
  else {
    $drupal_user_name = ($ldap_server) ? $ldap_server->userUsernameFromDn($drupal_user_name_or_dn) : FALSE;
  }

  ldap_servers_module_load_include('php', 'ldap_user', 'LdapUserConfAdmin.class');
  $ldap_user_conf = new LdapUserConf();

  $drupal_user = ($action == 'create' || $drupal_user_name === FALSE) ? FALSE : user_load_by_name($drupal_user_name);
  $user_edit = [];
  $account = [];

  switch ($action) {
    case 'create':
      if ($direction = LDAP_USER_PROV_DIRECTION_TO_DRUPAL_USER) {
        $user_edit['name'] = $drupal_user_name;
        $new_account = $ldap_user_conf->provisionDrupalAccount($user_edit, $account, $ldap_user, true);
        // @todo return boolean on first line, not human readable message
        $text = ($new_account) ? 'Created Account ' . $drupal_user_name : 'Fails to Create Account ' . $drupal_user_name;
        return ldap_user_ws_out([(boolean) ($new_account), $text]);
      }
      elseif ($direction = LDAP_USER_PROV_DIRECTION_TO_LDAP_ENTRY) {
        // No need for ldap_user_ldap_provision_semaphore call with webservice since not tied to single user like logon process.
        $provision_result = $ldap_user_conf->provisionLdapEntry($drupal_user_name);
        // @todo turn result array into response
      }
      break;

    case 'synch':
      if ($direction = LDAP_USER_PROV_DIRECTION_TO_DRUPAL_USER) {
        $saved_account = $ldap_user_conf->synchToDrupalAccount($drupal_user, $user_edit, LDAP_USER_EVENT_SYNCH_TO_DRUPAL_USER, $ldap_user, TRUE);
        $text = ($saved_account) ? 'Updated Account ' . $drupal_user_name : 'Failed to Update Account ' . $drupal_user_name;
        return ldap_user_ws_out([(boolean) ($saved_account), $text]);
      }
      elseif ($direction = LDAP_USER_PROV_DIRECTION_TO_LDAP_ENTRY) {
        $boolean_result = $ldap_user_conf->synchToLdapEntry($drupal_user_name);
        // @todo turn result array into response
      }
      break;

    case 'disable':
      if ($direction = LDAP_USER_PROV_DIRECTION_TO_DRUPAL_USER) {
        $drupal_user->status = 0;
        $edit = ['status' => 0];
        $saved_account = user_save($drupal_user, ['status' => 0]);
        return ldap_user_ws_out([(boolean) ($saved_account), 'Disabled Account ' . $drupal_user_name]);
      }
      break;

    case 'delete':
      if ($direction = LDAP_USER_PROV_DIRECTION_TO_DRUPAL_USER) {
        user_delete($drupal_user->uid);
        return ldap_user_ws_out([1, 'Deleted Account ' . $drupal_user_name]);
      }
      elseif ($direction = LDAP_USER_PROV_DIRECTION_TO_LDAP_ENTRY) {
        // @todo implement delete ldap record and call ldap_user event handler for delete account
      }
      break;
  }
  return $out;

}

/**
 * @deprecated
 */
function ldap_user_ws_out($response) {
  return join("\n", $response);
}
