<?php

/**
 * @file
 * Defines server classes and related functions.
 */

/**
 * TODO check if this already exists or find a better place for this function.
 *
 * Formats a ldap-entry ready to be printed on console.
 * TODO describe preconditions for ldap_entry.
 */
function pretty_print_ldap_entry($ldap_entry) {
  $m = [];
  for ($i = 0; $i < $ldap_entry['count']; $i++) {
    $k = $ldap_entry[$i];
    $v = $ldap_entry[$k];
    if (is_array($v)) {
      $m2 = [];
      $max = $v['count'] > 3 ? 3 : $v['count'];
      for ($j = 0; $j < $max; $j++) {
        $m2[] = $v[$j];
      }
      $v = "(" . join(", ", $m2) . ")";
    }
    $m[] = $k . ": " . $v;
  }
  return join(", ", $m);
}

/**
 * LDAP Server Class.
 *
 * This class is used to create, work with, and eventually destroy ldap_server
 * objects.
 *
 * @todo make bindpw protected
 */
class LdapServer {

  const LDAP_CONNECT_ERROR = 0x5b;
  const LDAP_SUCCESS = 0x00;
  const LDAP_OPERATIONS_ERROR = 0x01;
  const LDAP_PROTOCOL_ERROR = 0x02;

  public $sid;
  public $numericSid;
  public $name;
  public $status;
  public $ldap_type;
  public $address;
  public $port = 389;
  public $tls = FALSE;
  public $followrefs = FALSE;
  public $bind_method = 0;
  public $basedn = [];

  /**
   * Default to an anonymous bind.
   */
  public $binddn = FALSE;

  /**
   * Default to an anonymous bind.
   */
  public $bindpw = FALSE;

  public $user_dn_expression;
  public $user_attr;

  /**
   * Lowercase.
   */
  public $account_name_attr;

  /**
   * Lowercase.
   */
  public $mail_attr;
  public $mail_template;
  public $picture_attr;

  /**
   * Lowercase.
   */
  public $unique_persistent_attr;
  public $unique_persistent_attr_binary = FALSE;
  public $ldapToDrupalUserPhp;
  public $testingDrupalUsername;
  public $testingDrupalUserDn;
  public $detailed_watchdog_log;
  public $editPath;

  /**
   * Can this server be queried without user credentials provided?
   */
  public $queriableWithoutUserCredentials = FALSE;

  /**
   * Array of attributes needed keyed on $op such as 'user_update'.
   */
  public $userAttributeNeededCache = [];

  public $groupFunctionalityUnused = 0;
  public $groupObjectClass;

  /**
   * 1 | 0.
   */
  public $groupNested = 0;
  public $groupDeriveFromDn = FALSE;

  /**
   * Lowercase.
   */
  public $groupDeriveFromDnAttr = NULL;

  /**
   * Does a user attribute containing groups exist?
   */
  public $groupUserMembershipsAttrExists = FALSE;

  /**
   * Lowercase name of user attribute containing groups.
   */
  public $groupUserMembershipsAttr = NULL;
  /**
   * User attribute containing memberships is configured enough to use.
   */
  public $groupUserMembershipsConfigured = FALSE;

  /**
   * Lowercase // members, uniquemember, memberUid.
   */
  public $groupMembershipsAttr = NULL;


  /**
   * Lowercase // dn, cn, etc contained in groupMembershipsAttr.
   */
  public $groupMembershipsAttrMatchingUserAttr = NULL;

  /**
   * Are groupMembershipsAttrMatchingUserAttr and
   * groupGroupEntryMembershipsConfigured populated.
   */
  public $groupGroupEntryMembershipsConfigured = FALSE;

  public $groupTestGroupDn = NULL;
  public $groupTestGroupDnWriteable = NULL;

  private $group_properties = [
    'groupObjectClass',
    'groupNested',
    'groupDeriveFromDn',
    'groupDeriveFromDnAttr',
    'groupUserMembershipsAttrExists',
    'groupUserMembershipsAttr',
    'groupMembershipsAttrMatchingUserAttr',
    'groupTestGroupDn',
    'groupTestGroupDnWriteable',
  ];

  public $paginationEnabled = FALSE;
  public $searchPagination = FALSE;
  public $searchPageSize = 1000;
  public $searchPageStart = 0;
  public $searchPageEnd = NULL;

  public $inDatabase = FALSE;
  public $connection;

  /**
   * Direct mapping of db to object properties.
   *
   * @return array
   */
  public static function field_to_properties_map() {
    return [
      'sid' => 'sid',
      'numeric_sid' => 'numericSid',
      'name'  => 'name' ,
      'status'  => 'status',
      'ldap_type'  => 'ldap_type',
      'address'  => 'address',
      'port'  => 'port',
      'tls'  => 'tls',
      'followrefs'  => 'followrefs',
      'bind_method' => 'bind_method',
      'basedn'  => 'basedn',
      'binddn'  => 'binddn',
      'user_dn_expression' => 'user_dn_expression',
      'user_attr'  => 'user_attr',
      'account_name_attr'  => 'account_name_attr',
      'mail_attr'  => 'mail_attr',
      'mail_template'  => 'mail_template',
      'picture_attr'  => 'picture_attr',
      'unique_persistent_attr' => 'unique_persistent_attr',
      'unique_persistent_attr_binary' => 'unique_persistent_attr_binary',
      'ldap_to_drupal_user'  => 'ldapToDrupalUserPhp',
      'testing_drupal_username'  => 'testingDrupalUsername',
      'testing_drupal_user_dn'  => 'testingDrupalUserDn',

      'grp_unused' => 'groupFunctionalityUnused',
      'grp_object_cat' => 'groupObjectClass',
      'grp_nested' => 'groupNested',
      'grp_user_memb_attr_exists' => 'groupUserMembershipsAttrExists',
      'grp_user_memb_attr' => 'groupUserMembershipsAttr',
      'grp_memb_attr' => 'groupMembershipsAttr',
      'grp_memb_attr_match_user_attr' => 'groupMembershipsAttrMatchingUserAttr',
      'grp_derive_from_dn' => 'groupDeriveFromDn',
      'grp_derive_from_dn_attr' => 'groupDeriveFromDnAttr',
      'grp_test_grp_dn' => 'groupTestGroupDn',
      'grp_test_grp_dn_writeable' => 'groupTestGroupDnWriteable',

      'search_pagination' => 'searchPagination',
      'search_page_size' => 'searchPageSize',

    ];

  }

  /**
   * Constructor Method.
   *
   * @param $sid
   */
  public function __construct($sid) {
    if (!is_scalar($sid)) {
      return;
    }
    $this->detailed_watchdog_log = variable_get('ldap_help_watchdog_detail', 0);
    $server_record = FALSE;
    if (module_exists('ctools')) {
      ctools_include('export');
      $result = ctools_export_load_object('ldap_servers', 'names', [$sid]);
      if (isset($result[$sid])) {
        $server_record = new stdClass();
        foreach ($result[$sid] as $db_field_name => $value) {
          $server_record->{$db_field_name} = $value;
        }
      }
    }
    else {
      $select = db_select('ldap_servers')
        ->fields('ldap_servers')
        ->condition('ldap_servers.sid', $sid)
        ->execute();
      foreach ($select as $record) {
        if ($record->sid == $sid) {
          $server_record = $record;
        }
      }
    }

    $server_record_bindpw = NULL;
    if (!$server_record) {
      $this->inDatabase = FALSE;
    }
    else {
      $this->inDatabase = TRUE;
      $this->sid = $sid;
      $this->detailedWatchdogLog = variable_get('ldap_help_watchdog_detail', 0);
      foreach ($this->field_to_properties_map() as $db_field_name => $property_name) {
        if (isset($server_record->$db_field_name)) {
          $this->{$property_name} = $server_record->$db_field_name;
        }
      }
      $server_record_bindpw = property_exists($server_record, 'bindpw') ? $server_record->bindpw : '';
    }
    $this->initDerivedProperties($server_record_bindpw);
  }

  /**
   * This method sets properties that don't directly map from db record.
   *
   * It is split out so it can be shared with ldapServerTest.class.php.
   *
   * @param $bindpw
   */
  protected function initDerivedProperties($bindpw) {

    // Get this->basedn in array format.
    if (!$this->basedn) {
      $this->basedn = [];
    }
    // Do nothing.
    elseif (is_array($this->basedn)) {
    }
    else {
      $basedn_unserialized = @unserialize($this->basedn);
      if (is_array($basedn_unserialized)) {
        $this->basedn = $basedn_unserialized;
      }
      else {
        $this->basedn = [];
        $token = is_scalar($basedn_unserialized) ? $basedn_unserialized : print_r($basedn_unserialized, TRUE);
        debug("basednb desearialization error" . $token);
        watchdog('ldap_servers', 'Failed to deserialize LdapServer::basedn of !basedn', ['!basedn' => $token], WATCHDOG_ERROR);
      }

    }

    if ($this->followrefs && !function_exists('ldap_set_rebind_proc')) {
      $this->followrefs = FALSE;
    }

    if ($bindpw) {
      $this->bindpw = ($bindpw == '') ? '' : ldap_servers_decrypt($bindpw);
    }

    $bind_overrides = variable_get('ldap_servers_overrides', []);
    if (isset($bind_overrides[$this->sid])) {
      if (isset($bind_overrides[$this->sid]['binddn'])) {
        $this->binddn = $bind_overrides[$this->sid]['binddn'];
      }
      if (isset($bind_overrides[$this->sid]['bindpw'])) {
        $this->bindpw = $bind_overrides[$this->sid]['bindpw'];
      }
    }

    $this->paginationEnabled = (boolean) (ldap_servers_php_supports_pagination() && $this->searchPagination);

    $this->queriableWithoutUserCredentials = (boolean) (
      $this->bind_method == LDAP_SERVERS_BIND_METHOD_SERVICE_ACCT ||
      $this->bind_method == LDAP_SERVERS_BIND_METHOD_ANON_USER
    );
    $this->editPath = (!$this->sid) ? '' : 'admin/config/people/ldap/servers/edit/' . $this->sid;

    $this->groupGroupEntryMembershipsConfigured = ($this->groupMembershipsAttrMatchingUserAttr && $this->groupMembershipsAttr);
    $this->groupUserMembershipsConfigured = ($this->groupUserMembershipsAttrExists && $this->groupUserMembershipsAttr);
  }

  /**
   * Destructor Method.
   */
  public function __destruct() {
    // Close the server connection to be sure.
    $this->disconnect();
  }

  /**
   * Invoke Method.
   */
  public function __invoke() {
    $this->connect();
    $this->bind();
  }

  /**
   * Connect Method.
   */
  public function connect() {
    if (!function_exists('ldap_connect')) {
      watchdog('ldap_servers', 'PHP LDAP extension not found, aborting.');
      return LDAP_NOT_SUPPORTED;
    }

    if (!$con = ldap_connect($this->address, $this->port)) {
      watchdog('ldap_servers', 'LDAP Connect failure to ' . $this->address . ':' . $this->port);
      return LDAP_CONNECT_ERROR;
    }

    ldap_set_option($con, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($con, LDAP_OPT_REFERRALS, (int) $this->followrefs);

    // Use TLS if we are configured and able to.
    if ($this->tls) {
      ldap_get_option($con, LDAP_OPT_PROTOCOL_VERSION, $vers);
      if ($vers == -1) {
        watchdog('ldap_servers', 'Could not get LDAP protocol version.');
        return LDAP_PROTOCOL_ERROR;
      }
      if ($vers != 3) {
        watchdog('ldap_servers', 'Could not start TLS, only supported by LDAP v3.');
        return LDAP_CONNECT_ERROR;
      }
      elseif (!function_exists('ldap_start_tls')) {
        watchdog('ldap_servers', 'Could not start TLS. It does not seem to be supported by this PHP setup.');
        return LDAP_CONNECT_ERROR;
      }
      elseif (!ldap_start_tls($con)) {
        $msg = t("Could not start TLS. (Error %errno: %error).", ['%errno' => ldap_errno($con), '%error' => ldap_error($con)]);
        watchdog('ldap_servers', $msg);
        return LDAP_CONNECT_ERROR;
      }
    }

    // Store the resulting resource.
    $this->connection = $con;
    return LDAP_SUCCESS;
  }

  /**
   * Bind (authenticate) against an active LDAP database.
   *
   * @param $userdn
   *   The DN to bind against. If NULL, we use $this->binddn
   * @param $pass
   *   The password search base. If NULL, we use $this->bindpw
   *
   * @return
   *   Result of bind; TRUE if successful, FALSE otherwise.
   */
  public function bind($userdn = NULL, $pass = NULL, $anon_bind = FALSE) {

    // Ensure that we have an active server connection.
    if (!$this->connection) {
      watchdog('ldap_servers', "LDAP bind failure for user %user. Not connected to LDAP server.", ['%user' => $userdn]);
      return LDAP_CONNECT_ERROR;
    }

    if ($anon_bind === FALSE && $userdn === NULL && $pass === NULL && $this->bind_method == LDAP_SERVERS_BIND_METHOD_ANON) {
      $anon_bind = TRUE;
    }
    if ($anon_bind === TRUE) {
      if (@!ldap_bind($this->connection)) {
        if ($this->detailedWatchdogLog) {
          watchdog('ldap_servers', "LDAP anonymous bind error. Error %errno: %error", ['%errno' => ldap_errno($this->connection), '%error' => ldap_error($this->connection)]);
        }
        return ldap_errno($this->connection);
      }
    }
    else {
      $userdn = ($userdn != NULL) ? $userdn : $this->binddn;
      $pass = ($pass != NULL) ? $pass : $this->bindpw;

      if ($this->followrefs) {
        $rebHandler = new LdapServersRebindHandler($userdn, $pass);
        ldap_set_rebind_proc($this->connection, [$rebHandler, 'rebind_callback']);
      }

      if (drupal_strlen($pass) == 0 || drupal_strlen($userdn) == 0) {
        watchdog('ldap_servers', "LDAP bind failure for user userdn=%userdn, pass=%pass.", ['%userdn' => $userdn, '%pass' => $pass]);
        return LDAP_LOCAL_ERROR;
      }
      if (@!ldap_bind($this->connection, $userdn, $pass)) {
        if ($this->detailedWatchdogLog) {
          watchdog('ldap_servers', "LDAP bind failure for user %user. Error %errno: %error", ['%user' => $userdn, '%errno' => ldap_errno($this->connection), '%error' => ldap_error($this->connection)]);
        }
        return ldap_errno($this->connection);
      }
    }

    return LDAP_SUCCESS;
  }

  /**
   * Disconnect (unbind) from an active LDAP server.
   */
  public function disconnect() {
    if (!$this->connection) {
      // Never bound or not currently bound, so no need to disconnect
      // watchdog('ldap_servers', 'LDAP disconnect failure from '. $this->server_addr . ':' . $this->port);.
    }
    else {
      ldap_unbind($this->connection);
      $this->connection = NULL;
    }
  }

  /**
   *
   */
  public function connectAndBindIfNotAlready() {
    if (!$this->connection) {
      $this->connect();
      $this->bind();
    }
  }

  /**
   * Does dn exist for this server?
   *
   * @param string $dn
   * @param enum $return
   *   = 'boolean' or 'ldap_entry'.
   * @param array $attributes
   *   in same form as ldap_read $attributes parameter.
   *
   * @return bool|array
   */
  public function dnExists($dn, $return = 'boolean', $attributes = NULL) {

    $params = [
      'base_dn' => $dn,
      'attributes' => $attributes,
      'attrsonly' => FALSE,
      'filter' => '(objectclass=*)',
      'sizelimit' => 0,
      'timelimit' => 0,
      'deref' => NULL,
    ];

    if ($return == 'boolean' || !is_array($attributes)) {
      $params['attributes'] = ['objectclass'];
    }
    else {
      $params['attributes'] = $attributes;
    }

    $result = $this->ldapQuery(LDAP_SCOPE_BASE, $params);
    if ($result !== FALSE) {
      $entries = @ldap_get_entries($this->connection, $result);
      if ($entries !== FALSE && $entries['count'] > 0) {
        return ($return == 'boolean') ? TRUE : $entries[0];
      }
    }

    return FALSE;

  }

  /**
   * @param $ldap_result
   *   as ldap link identifier
   *
   * @return FALSE on error or number of entries.
   *   (if 0 entries will return 0)
   */
  public function countEntries($ldap_result) {
    return ldap_count_entries($this->connection, $ldap_result);
  }

  /**
   * Create ldap entry.
   *
   * @param array $attributes
   *   should follow the structure of ldap_add functions
   *   entry array: http://us.php.net/manual/en/function.ldap-add.php
   *     $attributes["attribute1"] = "value";
   *     $attributes["attribute2"][0] = "value1";
   *     $attributes["attribute2"][1] = "value2";.
   *
   * @return boolean result
   */
  public function createLdapEntry($attributes, $dn = NULL) {

    if (!$this->connection) {
      $this->connect();
      $this->bind();
    }
    if (isset($attributes['dn'])) {
      $dn = $attributes['dn'];
      unset($attributes['dn']);
    }
    elseif (!$dn) {
      return FALSE;
    }

    if (!empty($attributes['unicodePwd']) && ($this->ldap_type == 'ad')) {
      $attributes['unicodePwd'] = ldap_servers_convert_password_for_active_directory_unicodePwd($attributes['unicodePwd']);
    }

    $result = @ldap_add($this->connection, $dn, $attributes);
    if (!$result) {
      $error = "LDAP Server ldap_add(%dn) Error Server ID = %sid, LDAP Err No: %ldap_errno LDAP Err Message: %ldap_err2str ";
      $tokens = ['%dn' => $dn, '%sid' => $this->sid, '%ldap_errno' => ldap_errno($this->connection), '%ldap_err2str' => ldap_err2str(ldap_errno($this->connection))];
      watchdog('ldap_servers', $error, $tokens, WATCHDOG_ERROR);
    }

    return $result;
  }

  /**
   * Compares 2 LDAP entries and returns the difference.
   *
   * Given 2 ldap entries, old and new, removes unchanged values to avoid
   * security errors and incorrect date modified.
   *
   * @param array $new_entry
   *   LDAP entry array in form <attribute> => <value>, or
   *   <attribute> => array(<value1>, <value2>, ...).
   * @param array $old_entry
   *   LDAP entry in form <attribute> =>
   *   array('count' => N, <value1>, <value2>, ...).
   *
   * @return array
   *   The $new_entry with unchanged attributes removed.
   *
   * @see \LdapServer::modifyLdapEntry()
   */
  public static function removeUnchangedAttributes($new_entry, $old_entry) {

    foreach ($new_entry as $key => $new_val) {
      $old_value = FALSE;
      $old_value_is_scalar = FALSE;
      $key_lcase = drupal_strtolower($key);
      if (isset($old_entry[$key_lcase])) {
        if ($old_entry[$key_lcase]['count'] == 1) {
          $old_value = $old_entry[$key_lcase][0];
          $old_value_is_scalar = TRUE;
        }
        else {
          unset($old_entry[$key_lcase]['count']);
          $old_value = $old_entry[$key_lcase];
          $old_value_is_scalar = FALSE;
        }
      }

      // Identical multivalued attributes.
      if (is_array($new_val) && is_array($old_value) && count(array_diff($new_val, $old_value)) == 0) {
        unset($new_entry[$key]);
      }
      elseif ($old_value_is_scalar && !is_array($new_val) && drupal_strtolower($old_value) == drupal_strtolower($new_val)) {
        // don't change values that aren't changing to avoid false permission constraints.
        unset($new_entry[$key]);
      }
    }

    return $new_entry;
  }

  /**
   * Modify attributes of ldap entry.
   *
   * @param string $dn
   *   DN of entry.
   * @param array $attributes
   *   should follow the structure of ldap_add functions
   *   entry array: http://us.php.net/manual/en/function.ldap-add.php
   *     $attributes["attribute1"] = "value";
   *     $attributes["attribute2"][0] = "value1";
   *     $attributes["attribute2"][1] = "value2";.
   *
   * @return TRUE on success FALSE on error
   */
  public function modifyLdapEntry($dn, $attributes = [], $old_attributes = FALSE) {

    $this->connectAndBindIfNotAlready();

    if (!$old_attributes) {
      $result = @ldap_read($this->connection, $dn, 'objectClass=*');
      if (!$result) {
        $error = "LDAP Server ldap_read(%dn) in LdapServer::modifyLdapEntry() Error Server ID = %sid, LDAP Err No: %ldap_errno LDAP Err Message: %ldap_err2str ";
        $tokens = ['%dn' => $dn, '%sid' => $this->sid, '%ldap_errno' => ldap_errno($this->connection), '%ldap_err2str' => ldap_err2str(ldap_errno($this->connection))];
        watchdog('ldap_servers', $error, $tokens, WATCHDOG_ERROR);
        return FALSE;
      }

      $entries = ldap_get_entries($this->connection, $result);
      if (is_array($entries) && $entries['count'] == 1) {
        $old_attributes = $entries[0];
      }
    }

    if (!empty($attributes['unicodePwd']) && ($this->ldap_type == 'ad')) {
      $attributes['unicodePwd'] = ldap_servers_convert_password_for_active_directory_unicodePwd($attributes['unicodePwd']);
    }

    $attributes = $this->removeUnchangedAttributes($attributes, $old_attributes);

    foreach ($attributes as $key => $cur_val) {
      $old_value = FALSE;
      $key_lcase = drupal_strtolower($key);
      if (isset($old_attributes[$key_lcase])) {
        if ($old_attributes[$key_lcase]['count'] == 1) {
          $old_value = $old_attributes[$key_lcase][0];
        }
        else {
          unset($old_attributes[$key_lcase]['count']);
          $old_value = $old_attributes[$key_lcase];
        }
      }

      // Remove enpty attributes.
      if ($cur_val == '' && $old_value != '') {
        unset($attributes[$key]);
        $result = @ldap_mod_del($this->connection, $dn, [$key_lcase => $old_value]);
        if (!$result) {
          $error = "LDAP Server ldap_mod_del(%dn) in LdapServer::modifyLdapEntry() Error Server ID = %sid, LDAP Err No: %ldap_errno LDAP Err Message: %ldap_err2str ";
          $tokens = ['%dn' => $dn, '%sid' => $this->sid, '%ldap_errno' => ldap_errno($this->connection), '%ldap_err2str' => ldap_err2str(ldap_errno($this->connection))];
          watchdog('ldap_servers', $error, $tokens, WATCHDOG_ERROR);
          return FALSE;
        }
      }
      elseif (is_array($cur_val)) {
        foreach ($cur_val as $mv_key => $mv_cur_val) {
          if ($mv_cur_val == '') {
            // Remove empty values in multivalues attributes.
            unset($attributes[$key][$mv_key]);
          }
          else {
            $attributes[$key][$mv_key] = $mv_cur_val;
          }
        }
      }
    }

    if (count($attributes) > 0) {
      $result = @ldap_modify($this->connection, $dn, $attributes);
      if (!$result) {
        $error = "LDAP Server ldap_modify(%dn) in LdapServer::modifyLdapEntry() Error Server ID = %sid, LDAP Err No: %ldap_errno LDAP Err Message: %ldap_err2str ";
        $tokens = ['%dn' => $dn, '%sid' => $this->sid, '%ldap_errno' => ldap_errno($this->connection), '%ldap_err2str' => ldap_err2str(ldap_errno($this->connection))];
        watchdog('ldap_servers', $error, $tokens, WATCHDOG_ERROR);
        return FALSE;
      }
    }

    return TRUE;

  }

  /**
   * Perform an LDAP delete.
   *
   * @param string $dn
   *
   * @return boolean result per ldap_delete
   */
  public function delete($dn) {
    if (!$this->connection) {
      $this->connect();
      $this->bind();
    }
    $result = @ldap_delete($this->connection, $dn);
    if (!$result) {
      $error = "LDAP Server delete(%dn) in LdapServer::delete() Error Server ID = %sid, LDAP Err No: %ldap_errno LDAP Err Message: %ldap_err2str ";
      $tokens = ['%dn' => $dn, '%sid' => $this->sid, '%ldap_errno' => ldap_errno($this->connection), '%ldap_err2str' => ldap_err2str(ldap_errno($this->connection))];
      watchdog('ldap_servers', $error, $tokens, WATCHDOG_ERROR);
    }
    return $result;
  }

  /**
   * Perform an LDAP search on all base dns and aggregate into one result.
   *
   * @param string $filter
   *   The search filter. such as sAMAccountName=jbarclay.  attribute values (e.g. jbarclay) should be esacaped before calling.
   *
   * @param array $attributes
   *   List of desired attributes. If omitted, we only return "dn".
   *
   * @remaining params mimick ldap_search() function params
   *
   * @return array
   *   An array of matching entries->attributes (will have 0 elements if search
   *   returns no results), or FALSE on error on any of the basedn queries.
   */
  public function searchAllBaseDns(
    $filter,
    $attributes = [],
    $attrsonly = 0,
    $sizelimit = 0,
    $timelimit = 0,
    $deref = NULL,
    $scope = LDAP_SCOPE_SUBTREE
    ) {
    $all_entries = [];
    // Need to search on all basedns one at a time.
    foreach ($this->basedn as $base_dn) {
      // No attributes, just dns needed.
      $entries = $this->search($base_dn, $filter, $attributes, $attrsonly, $sizelimit, $timelimit, $deref, $scope);
      // If error in any search, return false.
      if ($entries === FALSE) {
        return FALSE;
      }
      if (count($all_entries) == 0) {
        $all_entries = $entries;
        unset($all_entries['count']);
      }
      else {
        $existing_count = count($all_entries);
        unset($entries['count']);
        foreach ($entries as $i => $entry) {
          $all_entries[$existing_count + $i] = $entry;
        }
      }
    }
    $all_entries['count'] = count($all_entries);
    return $all_entries;

  }

  /**
   * Perform an LDAP search.
   *
   * @param string $basedn
   *   The search base. If NULL, we use $this->basedn. should not be esacaped.
   * @param string $filter
   *   The search filter. such as sAMAccountName=jbarclay.  attribute values
   *   (e.g. jbarclay) should be esacaped before calling.
   *
   * @param array $attributes
   *   List of desired attributes. If omitted, we only return "dn".
   *
   * @remaining params mimick ldap_search() function params
   *
   * @return
   *   An array of matching entries->attributes (will have 0
   *   elements if search returns no results),
   *   or FALSE on error.
   */
  public function search($base_dn = NULL,
  $filter = "",
  $attributes = [],
    $attrsonly = 0,
  $sizelimit = 0,
  $timelimit = 0,
  $deref = NULL,
  $scope = LDAP_SCOPE_SUBTREE) {

    /**
      * pagingation issues:
      * -- see documentation queue: http://markmail.org/message/52w24iae3g43ikix#query:+page:1+mid:bez5vpl6smgzmymy+state:results
      * -- wait for php 5.4? https://svn.php.net/repository/php/php-src/tags/php_5_4_0RC6/NEWS (ldap_control_paged_result
      * -- http://sgehrig.wordpress.com/2009/11/06/reading-paged-ldap-results-with-php-is-a-show-stopper/
      */

    if ($base_dn == NULL) {
      if (count($this->basedn) == 1) {
        $base_dn = $this->basedn[0];
      }
      else {
        return FALSE;
      }
    }

    $attr_display = is_array($attributes) ? join(',', $attributes) : 'none';
    $query = 'ldap_search() call: ' . join(",\n", [
      'base_dn: ' . $base_dn,
      'filter = ' . $filter,
      'attributes: ' . $attr_display,
      'attrsonly = ' . $attrsonly,
      'sizelimit = ' . $sizelimit,
      'timelimit = ' . $timelimit,
      'deref = ' . $deref,
      'scope = ' . $scope,
    ]
    );
    if ($this->detailed_watchdog_log) {
      watchdog('ldap_servers', $query, []);
    }

    // When checking multiple servers, there's a chance we might not be connected yet.
    if (!$this->connection) {
      $this->connect();
      $this->bind();
    }

    $ldap_query_params = [
      'connection' => $this->connection,
      'base_dn' => $base_dn,
      'filter' => $filter,
      'attributes' => $attributes,
      'attrsonly' => $attrsonly,
      'sizelimit' => $sizelimit,
      'timelimit' => $timelimit,
      'deref' => $deref,
      'query_display' => $query,
      'scope' => $scope,
    ];

    if ($this->searchPagination && $this->paginationEnabled) {
      $aggregated_entries = $this->pagedLdapQuery($ldap_query_params);
      return $aggregated_entries;
    }
    else {
      $result = $this->ldapQuery($scope, $ldap_query_params);
      if ($result && ($this->countEntries($result) !== FALSE)) {
        $entries = ldap_get_entries($this->connection, $result);
        drupal_alter('ldap_server_search_results', $entries, $ldap_query_params);
        return (is_array($entries)) ? $entries : FALSE;
      }
      elseif ($this->ldapErrorNumber()) {
        $watchdog_tokens = [
          '%basedn' => $ldap_query_params['base_dn'],
          '%filter' => $ldap_query_params['filter'],
          '%attributes' => print_r($ldap_query_params['attributes'], TRUE),
          '%errmsg' => $this->errorMsg('ldap'),
          '%errno' => $this->ldapErrorNumber(),
        ];
        watchdog('ldap_servers', "LDAP ldap_search error. basedn: %basedn| filter: %filter| attributes:
          %attributes| errmsg: %errmsg| ldap err no: %errno|", $watchdog_tokens);
        return FALSE;
      }
      else {
        return FALSE;
      }
    }
  }

  /**
   * Execute a paged ldap query and return entries as one aggregated array.
   *
   * $this->searchPageStart and $this->searchPageEnd should be set before calling if
   *   a particular set of pages is desired.
   *
   * @param array $ldap_query_params
   *   of form:
   *   'base_dn' => base_dn,
   *   'filter' =>  filter,
   *   'attributes' => attributes,
   *   'attrsonly' => attrsonly,
   *   'sizelimit' => sizelimit,
   *   'timelimit' => timelimit,
   *   'deref' => deref,
   *   'scope' => scope,
   *
   *   (this array of parameters is primarily passed on to ldapQuery() method)
   *
   * @return array of ldap entries or FALSE on error.
   */
  public function pagedLdapQuery($ldap_query_params) {

    if (!($this->searchPagination && $this->paginationEnabled)) {
      $watchdog_tokens = [
        '%basedn' => $ldap_query_params['base_dn'],
        '%filter' => $ldap_query_params['filter'],
        '%attributes' => print_r($ldap_query_params['attributes'], TRUE),
        '%errmsg' => $this->errorMsg('ldap'),
        '%errno' => $this->ldapErrorNumber(),
      ];
      watchdog('ldap_servers', "LDAP server pagedLdapQuery() called when functionality not available in php install or
        not enabled in ldap server configuration.  error. basedn: %basedn| filter: %filter| attributes:
         %attributes| errmsg: %errmsg| ldap err no: %errno|", $watchdog_tokens);
      return FALSE;
    }

    $page_token = '';
    $page = 0;
    $estimated_entries = 0;
    $aggregated_entries = [];
    $aggregated_entries_count = 0;
    $has_page_results = FALSE;

    do {
      $ldap_query_params['controls'] = [LDAP_CONTROL_PAGEDRESULTS => ['size' => $this->searchPageSize, 'cookie' => $page_token]];
      $result = $this->ldapQuery($ldap_query_params['scope'], $ldap_query_params);
      ldap_parse_result($this->connection, $result, $errcode, $matcheddn, $errmsg, $referrals, $ctrls);

      if ($page >= $this->searchPageStart) {
        $skipped_page = FALSE;
        if ($result && ($this->countEntries($result) !== FALSE)) {
          $page_entries = ldap_get_entries($this->connection, $result);
          unset($page_entries['count']);
          $has_page_results = (is_array($page_entries) && count($page_entries) > 0);
          $aggregated_entries = array_merge($aggregated_entries, $page_entries);
          $aggregated_entries_count = count($aggregated_entries);
        }
        elseif ($this->ldapErrorNumber()) {
          $watchdog_tokens = [
            '%basedn' => $ldap_query_params['base_dn'],
            '%filter' => $ldap_query_params['filter'],
            '%attributes' => print_r($ldap_query_params['attributes'], TRUE),
            '%errmsg' => $this->errorMsg('ldap'),
            '%errno' => $this->ldapErrorNumber(),
          ];
          watchdog('ldap_servers', "LDAP ldap_search error. basedn: %basedn| filter: %filter| attributes:
            %attributes| errmsg: %errmsg| ldap err no: %errno|", $watchdog_tokens);
          return FALSE;
        }
        else {
          return FALSE;
        }
      }
      else {
        $skipped_page = TRUE;
      }
      if ($errcode != 0) {
        watchdog('ldap_servers', "LDAP ldap_parse_results error. errcode: %errcode| errmsg %errmsg", [ 'errcode' => $errcode, 'errmsg' => $errmsg]);
      } else {
        $page_token = (isset($ctrls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'])) ? $ctrls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] : NULL;
      }
      if ($ldap_query_params['sizelimit'] && $this->ldapErrorNumber() == LDAP_SIZELIMIT_EXCEEDED) {
        // False positive error thrown.  do not set result limit error when $sizelimit specified.
      }
      elseif ($this->hasError()) {
        watchdog('ldap_servers', 'ldap_control_paged_result_response() function error. LDAP Error: %message, ldap_list() parameters: %query',
          ['%message' => $this->errorMsg('ldap'), '%query' => $ldap_query_params['query_display']],
          WATCHDOG_ERROR);
      }

      if (isset($ldap_query_params['sizelimit']) && $ldap_query_params['sizelimit'] && $aggregated_entries_count >= $ldap_query_params['sizelimit']) {
        $discarded_entries = array_splice($aggregated_entries, $ldap_query_params['sizelimit']);
        break;
      }
      // User defined pagination has run out.
      elseif ($this->searchPageEnd !== NULL && $page >= $this->searchPageEnd) {
        break;
      }
      // Ldap reference pagination has run out.
      elseif ($page_token === NULL || $page_token == '') {
        break;
      }
      $page++;
    } while ($skipped_page || $has_page_results);

    $aggregated_entries['count'] = count($aggregated_entries);
    return $aggregated_entries;
  }

  /**
   * Execute ldap query and return ldap records.
   *
   * @param scope
   *
   * @params see pagedLdapQuery $params
   *
   * @return \LDAP\Result|boolean Result of ldap entries or false if error
   */
  public function ldapQuery($scope, $params) {

    $this->connectAndBindIfNotAlready();

    $params['controls'] = $params['controls'] ?? null;

    switch ($scope) {
      case LDAP_SCOPE_SUBTREE:
        $result = @ldap_search($this->connection, $params['base_dn'], $params['filter'], $params['attributes'], $params['attrsonly'],
          $params['sizelimit'], $params['timelimit'], $params['deref'], $params['controls']);
        if ($params['sizelimit'] && $this->ldapErrorNumber() == LDAP_SIZELIMIT_EXCEEDED) {
          // False positive error thrown.  do not return result limit error when $sizelimit specified.
        }
        elseif ($this->hasError()) {
          watchdog('ldap_servers', 'ldap_search() function error. LDAP Error: %message, ldap_search() parameters: %query',
            ['%message' => $this->errorMsg('ldap'), '%query' => $params['query_display']],
            WATCHDOG_ERROR);
        }
        break;

      case LDAP_SCOPE_BASE:
        $result = @ldap_read($this->connection, $params['base_dn'], $params['filter'], $params['attributes'], $params['attrsonly'],
          $params['sizelimit'], $params['timelimit'], $params['deref'], $params['controls']);
        if ($params['sizelimit'] && $this->ldapErrorNumber() == LDAP_SIZELIMIT_EXCEEDED) {
          // False positive error thrown.  do not result limit error when $sizelimit specified.
        }
        elseif ($this->hasError()) {
          watchdog('ldap_servers', 'ldap_read() function error.  LDAP Error: %message, ldap_read() parameters: %query',
            ['%message' => $this->errorMsg('ldap'), '%query' => @$params['query_display']],
            WATCHDOG_ERROR);
        }
        break;

      case LDAP_SCOPE_ONELEVEL:
        $result = @ldap_list($this->connection, $params['base_dn'], $params['filter'], $params['attributes'], $params['attrsonly'],
          $params['sizelimit'], $params['timelimit'], $params['deref'], $params['controls']);
        if ($params['sizelimit'] && $this->ldapErrorNumber() == LDAP_SIZELIMIT_EXCEEDED) {
          // False positive error thrown.  do not result limit error when $sizelimit specified.
        }
        elseif ($this->hasError()) {
          watchdog('ldap_servers', 'ldap_list() function error. LDAP Error: %message, ldap_list() parameters: %query',
            ['%message' => $this->errorMsg('ldap'), '%query' => $params['query_display']],
            WATCHDOG_ERROR);
        }
        break;
    }
    return $result;
  }

  /**
   * @param array $dns
   *   Mixed Case.
   * @return array $dns Lower Case
   */
  public function dnArrayToLowerCase($dns) {
    return array_keys(array_change_key_case(array_flip($dns), CASE_LOWER));
  }

  /**
   * UserUserEntityFromPuid.
   *
   * @param string $puid
   *   Binary or string as returned from ldap_read or other ldap function.
   *
   * @return mixed
   */
  public function userUserEntityFromPuid($puid) {

    $query = new EntityFieldQuery();
    $query->entityCondition('entity_type', 'user')
      ->fieldCondition('ldap_user_puid_sid', 'value', $this->sid, '=')
      ->fieldCondition('ldap_user_puid', 'value', $puid, '=')
      ->fieldCondition('ldap_user_puid_property', 'value', $this->unique_persistent_attr, '=')
    // Run the query as user 1.
      ->addMetaData('account', user_load(1));

    $result = $query->execute();

    if (isset($result['user'])) {
      $uids = array_keys($result['user']);
      if (count($uids) == 1) {
        $user = entity_load('user', array_keys($result['user']));
        return $user[$uids[0]];
      }
      else {
        $uids = join(',', $uids);
        $tokens = ['%uids' => $uids, '%puid' => $puid, '%sid' => $this->sid, '%ldap_user_puid_property' => $this->unique_persistent_attr];
        watchdog('ldap_servers', 'multiple users (uids: %uids) with same puid (puid=%puid, sid=%sid, ldap_user_puid_property=%ldap_user_puid_property)', $tokens, WATCHDOG_ERROR);
        return FALSE;
      }
    }
    else {
      return FALSE;
    }

  }

  /**
   * @param $drupal_username
   * @param $watchdog_tokens
   *
   * @return string
   */
  public function userUsernameToLdapNameTransform($drupal_username, &$watchdog_tokens) {
    if ($this->ldapToDrupalUserPhp && module_exists('php')) {
      global $name;
      $old_name_value = $name;
      $name = $drupal_username;
      $code = "<?php global \$name; \n" . $this->ldapToDrupalUserPhp . "; \n ?>";
      $watchdog_tokens['%code'] = $this->ldapToDrupalUserPhp;
      $code_result = php_eval($code);
      $watchdog_tokens['%code_result'] = $code_result;
      $ldap_username = $code_result;
      $watchdog_tokens['%ldap_username'] = $ldap_username;
      // Important because of global scope of $name.
      $name = $old_name_value;
      if ($this->detailedWatchdogLog) {
        watchdog('ldap_servers', '%drupal_user_name tansformed to %ldap_username by applying code <code>%code</code>', $watchdog_tokens, WATCHDOG_DEBUG);
      }
    }
    else {
      $ldap_username = $drupal_username;
    }

    // Let other modules alter the ldap name.
    $context = [
      'ldap_server' => $this,
    ];
    drupal_alter('ldap_servers_username_to_ldapname', $ldap_username, $drupal_username, $context);

    return $ldap_username;

  }

  /**
   * UserUsernameFromLdapEntry.
   *
   * @param array $ldap_entry
   *
   * @return string
   *   user's username value
   */
  public function userUsernameFromLdapEntry($ldap_entry) {

    if ($this->account_name_attr) {
      $accountname = (empty($ldap_entry[$this->account_name_attr][0])) ? FALSE : $ldap_entry[$this->account_name_attr][0];
    }
    elseif ($this->user_attr) {
      $accountname = (empty($ldap_entry[$this->user_attr][0])) ? FALSE : $ldap_entry[$this->user_attr][0];
    }
    else {
      $accountname = FALSE;
    }

    return $accountname;
  }

  /**
   * UserUsernameFromDn.
   *
   * @param string $dn
   *
   * @return mixed
   *   string user's username value of FALSE
   */
  public function userUsernameFromDn($dn) {

    $ldap_entry = @$this->dnExists($dn, 'ldap_entry', []);
    if (!$ldap_entry || !is_array($ldap_entry)) {
      return FALSE;
    }
    else {
      return $this->userUsernameFromLdapEntry($ldap_entry);
    }

  }

  /**
   * @param ldap entry array $ldap_entry
   *
   * @return string user's mail value or FALSE if none present
   */
  public function userEmailFromLdapEntry($ldap_entry) {

    // Not using template.
    if ($ldap_entry && $this->mail_attr) {
      $mail = isset($ldap_entry[$this->mail_attr][0]) ? $ldap_entry[$this->mail_attr][0] : FALSE;
      return $mail;
    }
    // Template is of form [cn]@illinois.edu.
    elseif ($ldap_entry && $this->mail_template) {
      ldap_servers_module_load_include('inc', 'ldap_servers', 'ldap_servers.functions');
      return ldap_servers_token_replace($ldap_entry, $this->mail_template, 'ldap_entry');
    }
    else {
      return FALSE;
    }
  }

  /**
   * @param array $ldap_entry
   *
   * @return object|bool
   *   Drupal file object image user's thumbnail or FALSE if none present or
   *   ERROR happens.
   */
  public function userPictureFromLdapEntry($ldap_entry, $drupal_username = FALSE) {
    if ($ldap_entry && $this->picture_attr) {
      // Check if ldap entry has been provisioned.
      $image_data = isset($ldap_entry[$this->picture_attr][0]) ? $ldap_entry[$this->picture_attr][0] : FALSE;
      if (!$image_data) {
        return FALSE;
      }

      $md5thumb = md5($image_data);

      /**
       * If the existing account already has picture check if it has changed. If
       * so remove the old file and create the new one. If a picture is not set
       * but the account has an md5 hash, something is wrong and we exit.
       */
      if ($drupal_username && $account = user_load_by_name($drupal_username)) {
        if ($account->uid == 0 || $account->uid == 1) {
          return FALSE;
        }
        if (isset($account->picture)) {
          // Check if image has changed.
          if (isset($account->data['ldap_user']['init']['thumb5md']) && $md5thumb === $account->data['ldap_user']['init']['thumb5md']) {
            // No change, return same image.
            $account->picture->md5Sum = $md5thumb;
            return $account->picture;
          }
          else {
            // Image is different, remove file object.
            if (is_object($account->picture)) {
              file_delete($account->picture, TRUE);
            }
            elseif (is_string($account->picture)) {
              $file = file_load(intval($account->picture));
              file_delete($file, TRUE);
            }
          }
        }
        elseif (isset($account->data['ldap_user']['init']['thumb5md'])) {
          watchdog('ldap_servers', "Some error happened during thumbnailPhoto sync.");
          return FALSE;
        }
      }
      return $this->savePictureData($image_data, $md5thumb);
    }
    return FALSE;
  }

  /**
   * @param $image_data
   * @param $md5thumb
   *
   * @return bool|\stdClass
   */
  private function savePictureData($image_data, $md5thumb) {
    // Create tmp file to get image format.
    $filename = uniqid();
    $fileuri = file_directory_temp() . '/' . $filename;
    $size = file_put_contents($fileuri, $image_data);
    $info = image_get_info($fileuri);
    unlink($fileuri);
    // Create file object.
    $file = file_save_data($image_data, file_default_scheme() . '://' . variable_get('user_picture_path') . '/' . $filename . '.' . $info['extension']);
    $file->md5Sum = $md5thumb;
    // Standard Drupal validators for user pictures.
    $validators = [
      'file_validate_is_image' => [],
      'file_validate_image_resolution' => [variable_get('user_picture_dimensions', '85x85')],
      'file_validate_size' => [variable_get('user_picture_file_size', '30') * 1024],
    ];
    $errors = file_validate($file, $validators);
    if (empty($errors)) {
      return $file;
    }
    else {
      foreach ($errors as $err => $err_val) {
        watchdog('ldap_servers', "Error storing picture: %error", ["%error" => $err_val], WATCHDOG_ERROR);
      }
      return FALSE;
    }
  }

  /**
   * @param array $ldap_entry
   *
   * @return string
   *   user's PUID or permanent user id (within ldap), converted from binary, if applicable
   */
  public function userPuidFromLdapEntry($ldap_entry) {

    if ($this->unique_persistent_attr
        && isset($ldap_entry[$this->unique_persistent_attr][0])
        && is_scalar($ldap_entry[$this->unique_persistent_attr][0])
        ) {
      if (is_array($ldap_entry[$this->unique_persistent_attr])) {
        $puid = $ldap_entry[$this->unique_persistent_attr][0];
      }
      else {
        $puid = $ldap_entry[$this->unique_persistent_attr];
      }
      return ($this->unique_persistent_attr_binary) ? ldap_servers_binary($puid) : $puid;
    }
    else {
      return FALSE;
    }
  }

  /**
   * @param mixed $user
   *   - drupal user object (stdClass Object)
   *    - ldap entry of user (array)
   *    - ldap dn of user (string)
   *    - drupal username of user (string)
   *
   * @return array $ldap_user_entry (with top level keys of 'dn', 'mail', 'sid' and 'attr' )
   */
  public function user_lookup($user) {
    return $this->userUserToExistingLdapEntry($user);
  }

  /**
   *
   */
  public function userUserToExistingLdapEntry($user) {

    if (is_object($user)) {
      $user_ldap_entry = $this->userUserNameToExistingLdapEntry($user->name);
    }
    elseif (is_array($user)) {
      $user_ldap_entry = $user;
    }
    elseif (is_scalar($user)) {
      // Username.
      if (strpos($user, '=') === FALSE) {
        $user_ldap_entry = $this->userUserNameToExistingLdapEntry($user);
      }
      else {
        $user_ldap_entry = $this->dnExists($user, 'ldap_entry');
      }
    }
    return $user_ldap_entry;
  }

  /**
   * Queries LDAP server for the user.
   *
   * @param string $drupal_user_name
   *
   * @param string or int $prov_event
   *   This could be anything, particularly when used by other modules.
   *   Other modules should use string like 'mymodule_myevent'
   *   LDAP_USER_EVENT_ALL signifies get all attributes needed by all other
   *   contexts/ops.
   *
   * @return array
   *   representing ldap data of a user.  for example of returned value.
   *   'sid' => ldap server id
   *   'mail' => derived from ldap mail (not always populated).
   *   'dn'   => dn of user
   *   'attr' => single ldap entry array in form returned from ldap_search() extension, e.g.
   *   'dn' => dn of entry
   */
  public function userUserNameToExistingLdapEntry($drupal_user_name, $ldap_context = NULL) {

    $watchdog_tokens = ['%drupal_user_name' => $drupal_user_name];
    $ldap_username = $this->userUsernameToLdapNameTransform($drupal_user_name, $watchdog_tokens);
    if (!$ldap_username) {
      return FALSE;
    }
    if (!$ldap_context) {
      $attributes = [];
    }
    else {
      $attribute_maps = ldap_servers_attributes_needed($this->sid, $ldap_context);
      $attributes = array_keys($attribute_maps);
    }

    foreach ($this->basedn as $basedn) {
      if (empty($basedn)) {
        continue;
      }
      $filter = '(' . $this->user_attr . '=' . ldap_server_massage_text($ldap_username, 'attr_value', LDAP_SERVER_MASSAGE_QUERY_LDAP) . ')';
      $result = $this->search($basedn, $filter, $attributes);
      if (!$result || !isset($result['count']) || !$result['count']) {
        continue;
      }

      // Must find exactly one user for authentication to work.
      if ($result['count'] != 1) {
        $count = $result['count'];
        watchdog('ldap_servers', "Error: !count users found with $filter under $basedn.", ['!count' => $count], WATCHDOG_ERROR);
        continue;
      }
      $match = $result[0];
      // These lines serve to fix the attribute name in case a
      // naughty server (i.e.: MS Active Directory) is messing the
      // characters' case.
      // This was contributed by Dan "Gribnif" Wilga, and described
      // here: http://drupal.org/node/87833
      $name_attr = $this->user_attr;

      if (isset($match[$name_attr][0])) {
        // Leave name.
      }
      elseif (isset($match[drupal_strtolower($name_attr)][0])) {
        $name_attr = drupal_strtolower($name_attr);

      }
      else {
        if ($this->bind_method == LDAP_SERVERS_BIND_METHOD_ANON_USER) {
          $result = [
            'dn' => $match['dn'],
            'mail' => $this->userEmailFromLdapEntry($match),
            'attr' => $match,
            'sid' => $this->sid,
          ];
          return $result;
        }
        else {
          continue;
        }
      }

      // Finally, we must filter out results with spaces added before
      // or after, which are considered OK by LDAP but are no good for us
      // We allow lettercase independence, as requested by Marc Galera
      // on http://drupal.org/node/97728
      //
      // Some setups have multiple $name_attr per entry, as pointed out by
      // Clarence "sparr" Risher on http://drupal.org/node/102008, so we
      // loop through all possible options.
      foreach ($match[$name_attr] as $value) {
        if (drupal_strtolower(trim($value)) == drupal_strtolower($ldap_username)) {
          $result = [
            'dn' => $match['dn'],
            'mail' => $this->userEmailFromLdapEntry($match),
            'attr' => $match,
            'sid' => $this->sid,
          ];
          return $result;
        }
      }
    }
  }

  /**
   * Is a user a member of group?
   *
   * @param string $group_dn
   *   MIXED CASE.
   * @param mixed $user
   *   - drupal user object (stdClass Object)
   *    - ldap entry of user (array)
   *    - ldap dn of user (array)
   *    - drupal user name (string)
   * @param enum $nested
   *   = NULL (default to server configuration), TRUE, or FALSE indicating to
   *   test for nested groups.
   *
   * @return bool
   */
  public function groupIsMember($group_dn, $user, $nested = NULL) {

    $nested = ($nested === TRUE || $nested === FALSE) ? $nested : $this->groupNested;
    $group_dns = $this->groupMembershipsFromUser($user, 'group_dns', $nested);
    // While list of group dns is going to be in correct mixed case, $group_dn may not since it may be derived from user entered values
    // so make sure in_array() is case insensitive.
    return (is_array($group_dns) && in_array(drupal_strtolower($group_dn), $this->dnArrayToLowerCase($group_dns)));
  }

  /**
   * NOT TESTED
   * add a group entry.
   *
   * @param string $group_dn
   *   as ldap dn.
   * @param array $attributes
   *   in key value form
   *   $attributes = array(
   *      "attribute1" = "value",
   *      "attribute2" = array("value1", "value2"),
   *      )
   *
   * @return boolean success
   */
  public function groupAddGroup($group_dn, $attributes = []) {

    if ($this->dnExists($group_dn, 'boolean')) {
      return FALSE;
    }

    $attributes = array_change_key_case($attributes, CASE_LOWER);
    $objectclass = (empty($attributes['objectclass'])) ? $this->groupObjectClass : $attributes['objectclass'];
    $attributes['objectclass'] = $objectclass;

    /**
     * 2. give other modules a chance to add or alter attributes
     */
    $context = [
      'action' => 'add',
      'corresponding_drupal_data' => [$group_dn => $attributes],
      'corresponding_drupal_data_type' => 'group',
    ];
    $ldap_entries = [$group_dn => $attributes];
    drupal_alter('ldap_entry_pre_provision', $ldap_entries, $this, $context);
    $attributes = $ldap_entries[$group_dn];

    /**
     * 4. provision ldap entry
     *   @todo how is error handling done here?
     */
    $ldap_entry_created = $this->createLdapEntry($attributes, $group_dn);

    /**
     * 5. allow other modules to react to provisioned ldap entry
     *   @todo how is error handling done here?
     */
    if ($ldap_entry_created) {
      module_invoke_all('ldap_entry_post_provision', $ldap_entries, $this, $context);
      return TRUE;
    }
    else {
      return FALSE;
    }

  }

  /**
   * NOT TESTED
   * remove a group entry.
   *
   * @param string $group_dn
   *   as ldap dn.
   * @param bool $only_if_group_empty
   *   TRUE = group should not be removed if not empty
   *   FALSE = groups should be deleted regardless of members.
   *
   * @return bool
   */
  public function groupRemoveGroup($group_dn, $only_if_group_empty = TRUE) {

    if ($only_if_group_empty) {
      $members = $this->groupAllMembers($group_dn);
      if (is_array($members) && count($members) > 0) {
        return FALSE;
      }
    }

    return $this->delete($group_dn);

  }

  /**
   * NOT TESTED
   * add a member to a group.
   *
   * @param string $ldap_user_dn
   *   as ldap dn.
   * @param mixed $user
   *   - drupal user object (stdClass Object)
   *    - ldap entry of user (array) (with top level keys of 'dn', 'mail',
   *   'sid' and 'attr' )
   *    - ldap dn of user (array)
   *    - drupal username of user (string)
   *
   * @return bool
   */
  public function groupAddMember($group_dn, $user) {

    $user_ldap_entry = $this->userUserToExistingLdapEntry($user);
    $result = FALSE;
    if ($user_ldap_entry && $this->groupGroupEntryMembershipsConfigured) {
      $add = [];
      $add[$this->groupMembershipsAttr] = $user_ldap_entry['dn'];
      $this->connectAndBindIfNotAlready();
      $result = @ldap_mod_add($this->connection, $group_dn, $add);
    }

    return $result;
  }

  /**
   * NOT TESTED
   * remove a member from a group.
   *
   * @param string $group_dn
   *   as ldap dn.
   * @param mixed $user
   *   - drupal user object (stdClass Object)
   *    - ldap entry of user (array) (with top level keys of 'dn', 'mail',
   *   'sid' and 'attr' )
   *    - ldap dn of user (array)
   *    - drupal username of user (string)
   *
   * @return bool
   */
  public function groupRemoveMember($group_dn, $user) {

    $user_ldap_entry = $this->userUserToExistingLdapEntry($user);
    $result = FALSE;
    if ($user_ldap_entry && $this->groupGroupEntryMembershipsConfigured) {
      $del = [];
      $del[$this->groupMembershipsAttr] = $user_ldap_entry['dn'];
      $this->connectAndBindIfNotAlready();
      $result = @ldap_mod_del($this->connection, $group_dn, $del);
    }
    return $result;
  }

  /**
   * Get all members of a group.
   *
   * @todo: NOT IMPLEMENTED: nested groups
   *
   * @param string $group_dn
   *   as ldap dn.
   *
   * @return false
   *   on error otherwise array of group members (could be users or groups)
   */
  public function groupAllMembers($group_dn) {
    if (!$this->groupGroupEntryMembershipsConfigured) {
      return FALSE;
    }
    $attributes = [$this->groupMembershipsAttr, 'cn'];
    $group_entry = $this->dnExists($group_dn, 'ldap_entry', $attributes);
    if (!$group_entry) {
      return FALSE;
    }
    else {
      // If attributes weren't returned, don't give false  empty group.
      if (empty($group_entry['cn'])) {
        return FALSE;
      }
      if (empty($group_entry[$this->groupMembershipsAttr])) {
        // If no attribute returned, no members.
        return [];
      }
      $members = $group_entry[$this->groupMembershipsAttr];
      if (isset($members['count'])) {
        unset($members['count']);
      }
      return $members;
    }

    $this->groupMembersResursive($current_group_entries, $all_group_dns, $tested_group_ids, 0, $max_levels, $object_classes);

    return $all_group_dns;

  }

  /**
   * NOT IMPLEMENTED
   * recurse through all child groups and add members.
   *
   * @param array $current_group_entries
   *   of ldap group entries that are starting point.  should include at least
   *   1 entry.
   * @param array $all_group_dns
   *   as array of all groups user is a member of.  MIXED CASE VALUES.
   * @param array $tested_group_ids
   *   as array of tested group dn, cn, uid, etc.  MIXED CASE VALUES
   *   whether these value are dn, cn, uid, etc depends on what attribute
   *   members, uniquemember, memberUid contains whatever attribute is in
   *   $this->$tested_group_ids to avoid redundant recursing.
   * @param int $level
   *   of recursion.
   * @param int $max_levels
   *   as max recursion allowed.
   *
   * @return bool
   */
  public function groupMembersResursive($current_member_entries, &$all_member_dns, &$tested_group_ids, $level, $max_levels, $object_classes = FALSE) {

    if (!$this->groupGroupEntryMembershipsConfigured || !is_array($current_member_entries) || count($current_member_entries) == 0) {
      return FALSE;
    }
    if (isset($current_member_entries['count'])) {
      unset($current_member_entries['count']);
    };

    foreach ($current_member_entries as $i => $member_entry) {
      // 1.  Add entry itself if of the correct type to $all_member_dns.
      $objectClassMatch = (!$object_classes || (count(array_intersect(array_values($member_entry['objectclass']), $object_classes)) > 0));
      $objectIsGroup = in_array($this->groupObjectClass, array_values($member_entry['objectclass']));
      // Add member.
      if ($objectClassMatch && !in_array($member_entry['dn'], $all_member_dns)) {
        $all_member_dns[] = $member_entry['dn'];
      }

      // 2. If its a group, keep recurse the group for descendants.
      if ($objectIsGroup && $level < $max_levels) {
        if ($this->groupMembershipsAttrMatchingUserAttr == 'dn') {
          $group_id = $member_entry['dn'];
        }
        else {
          $group_id = $member_entry[$this->groupMembershipsAttrMatchingUserAttr][0];
        }
        // 3. skip any groups that have already been tested.
        if (!in_array($group_id, $tested_group_ids)) {
          $tested_group_ids[] = $group_id;
          $member_ids = $member_entry[$this->groupMembershipsAttr];
          if (isset($member_ids['count'])) {
            unset($member_ids['count']);
          };
          $ors = [];
          foreach ($member_ids as $i => $member_id) {
            // @todo this would be replaced by query template
            $ors[] = $this->groupMembershipsAttr . '=' . ldap_pear_escape_filter_value($member_id);
          }

          if (count($ors)) {
            // e.g. (|(cn=group1)(cn=group2)) or   (|(dn=cn=group1,ou=blah...)(dn=cn=group2,ou=blah...))
            $query_for_child_members = '(|(' . join(")(", $ors) . '))';
            // Add or on object classe, otherwise get all object classes.
            if (count($object_classes)) {
              $object_classes_ors = ['(objectClass=' . $this->groupObjectClass . ')'];
              foreach ($object_classes as $object_class) {
                $object_classes_ors[] = '(objectClass=' . $object_class . ')';
              }
              $query_for_child_members = '&(|' . join($object_classes_ors) . ')(' . $query_for_child_members . ')';
            }
            // Need to search on all basedns one at a time.
            foreach ($this->basedn as $base_dn) {
              $child_member_entries = $this->search($base_dn, $query_for_child_members, ['objectclass', $this->groupMembershipsAttr, $this->groupMembershipsAttrMatchingUserAttr]);
              if ($child_member_entries !== FALSE) {
                $this->groupMembersResursive($child_member_entries, $all_member_dns, $tested_group_ids, $level + 1, $max_levels, $object_classes);
              }
            }
          }
        }
      }
    }
  }

  /**
   * Get list of all groups that a user is a member of.
   *
   *    If $nested = TRUE,
   *    list will include all parent group.  That is if user is a member of "programmer" group
   *    and "programmer" group is a member of "it" group, user is a member of
   *    both "programmer" and "it" groups.
   *
   *    If $nested = FALSE, list will only include groups user is in directly.
   *
   * @param mixed
   *   - drupal user object (stdClass Object)
   *    - ldap entry of user (array) (with top level keys of 'dn', 'mail', 'sid' and 'attr' )
   *    - ldap dn of user (array)
   *    - drupal username of user (string)
   * @param mixed $return
   *   = 'group_dns'.
   * @param bool $nested
   *   if groups should be recursed or not.
   *
   * @return array of groups dns in mixed case or FALSE on error
   */
  public function groupMembershipsFromUser($user, $return = 'group_dns', $nested = NULL) {

    $group_dns = FALSE;
    $user_ldap_entry = @$this->userUserToExistingLdapEntry($user);
    if (!$user_ldap_entry || $this->groupFunctionalityUnused) {
      return FALSE;
    }
    if ($nested === NULL) {
      $nested = $this->groupNested;
    }

    // Preferred method.
    if ($this->groupUserMembershipsConfigured) {
      $group_dns = $this->groupUserMembershipsFromUserAttr($user_ldap_entry, $nested);
    }
    elseif ($this->groupGroupEntryMembershipsConfigured) {
      $group_dns = $this->groupUserMembershipsFromEntry($user_ldap_entry, $nested);
    }
    else {
      watchdog('ldap_servers', 'groupMembershipsFromUser: Group memberships for server have not been configured.', [], WATCHDOG_WARNING);
      return FALSE;
    }
    if ($return == 'group_dns') {
      return $group_dns;
    }

  }

  /**
   * Get list of all groups that a user is a member of by using memberOf attribute first,
   *    then if nesting is true, using group entries to find parent groups.
   *
   *    If $nested = TRUE,
   *    list will include all parent group.  That is if user is a member of "programmer" group
   *    and "programmer" group is a member of "it" group, user is a member of
   *    both "programmer" and "it" groups.
   *
   *    If $nested = FALSE, list will only include groups user is in directly.
   *
   * @param mixed
   *   - drupal user object (stdClass Object)
   *    - ldap entry of user (array) (with top level keys of 'dn', 'mail', 'sid' and 'attr' )
   *    - ldap dn of user (array)
   *    - drupal username of user (string)
   * @param bool $nested
   *   if groups should be recursed or not.
   *
   * @return array of group dns
   */
  public function groupUserMembershipsFromUserAttr($user, $nested = NULL) {

    if (!$this->groupUserMembershipsConfigured) {
      return FALSE;
    }
    if ($nested === NULL) {
      $nested = $this->groupNested;
    }

    $not_user_ldap_entry = empty($user['attr'][$this->groupUserMembershipsAttr]);
    // If drupal user passed in, try to get user_ldap_entry.
    if ($not_user_ldap_entry) {
      $user = $this->userUserToExistingLdapEntry($user);
      $not_user_ldap_entry = empty($user['attr'][$this->groupUserMembershipsAttr]);
      if ($not_user_ldap_entry) {
        // user's membership attribute is not present.  either misconfigured or query failed.
        return FALSE;
      }
    }
    // If not exited yet, $user must be user_ldap_entry.
    $user_ldap_entry = $user;
    $all_group_dns = [];
    $tested_group_ids = [];
    $level = 0;

    $member_group_dns = $user_ldap_entry['attr'][$this->groupUserMembershipsAttr];
    if (isset($member_group_dns['count'])) {
      unset($member_group_dns['count']);
    }
    $ors = [];
    foreach ($member_group_dns as $i => $member_group_dn) {
      $all_group_dns[] = $member_group_dn;
      if ($nested) {
        if ($this->groupMembershipsAttrMatchingUserAttr == 'dn') {
          $member_value = $member_group_dn;
        }
        else {
          $member_value = ldap_servers_get_first_rdn_value_from_dn($member_group_dn, $this->groupMembershipsAttrMatchingUserAttr);
        }
        $ors[] = $this->groupMembershipsAttr . '=' . ldap_pear_escape_filter_value($member_value);
      }
    }

    if ($nested && count($ors)) {
      $count = count($ors);
      // Only 50 or so per query.
      for ($i = 0; $i < $count; $i = $i + LDAP_SERVER_LDAP_QUERY_CHUNK) {
        $current_ors = array_slice($ors, $i, LDAP_SERVER_LDAP_QUERY_CHUNK);
        // e.g. (|(cn=group1)(cn=group2)) or   (|(dn=cn=group1,ou=blah...)(dn=cn=group2,ou=blah...))
        $or = '(|(' . join(")(", $current_ors) . '))';
        $query_for_parent_groups = '(&(objectClass=' . $this->groupObjectClass . ')' . $or . ')';

        // Need to search on all basedns one at a time.
        foreach ($this->basedn as $base_dn) {
          // No attributes, just dns needed.
          $group_entries = $this->search($base_dn, $query_for_parent_groups);
          if ($group_entries !== FALSE  && $level < LDAP_SERVER_LDAP_QUERY_RECURSION_LIMIT) {
            $this->groupMembershipsFromEntryRecursive($group_entries, $all_group_dns, $tested_group_ids, $level + 1, LDAP_SERVER_LDAP_QUERY_RECURSION_LIMIT);
          }
        }
      }
    }

    return $all_group_dns;
  }

  /**
   * Get list of all groups that a user is a member of by querying groups.
   *
   *    If $nested = TRUE,
   *    list will include all parent group.  That is if user is a member of "programmer" group
   *    and "programmer" group is a member of "it" group, user is a member of
   *    both "programmer" and "it" groups.
   *
   *    If $nested = FALSE, list will only include groups user is in directly.
   *
   * @param mixed
   *   - drupal user object (stdClass Object)
   *    - ldap entry of user (array) (with top level keys of 'dn', 'mail', 'sid' and 'attr' )
   *    - ldap dn of user (array)
   *    - drupal username of user (string)
   * @param bool $nested
   *   if groups should be recursed or not.
   *
   * @return array of group dns MIXED CASE VALUES
   *
   * @see tests/DeriveFromEntry/ldap_servers.inc for fuller notes and test example
   */
  public function groupUserMembershipsFromEntry($user, $nested = NULL) {

    if (!$this->groupGroupEntryMembershipsConfigured) {
      return FALSE;
    }
    if ($nested === NULL) {
      $nested = $this->groupNested;
    }

    $user_ldap_entry = $this->userUserToExistingLdapEntry($user);

    // MIXED CASE VALUES.
    $all_group_dns = [];
    // Array of dns already tested to avoid excess queries MIXED CASE VALUES.
    $tested_group_ids = [];
    $level = 0;

    if ($this->groupMembershipsAttrMatchingUserAttr == 'dn') {
      $member_value = $user_ldap_entry['dn'];
    }
    else {
      $member_value = $user_ldap_entry['attr'][$this->groupMembershipsAttrMatchingUserAttr][0];
    }
    $member_value = ldap_pear_escape_filter_value($member_value);
    if ($this->groupObjectClass == '') {
      $group_query = '(' . $this->groupMembershipsAttr . "=$member_value)";
    }
    else {
      $group_query = '(&(objectClass=' . $this->groupObjectClass . ')(' . $this->groupMembershipsAttr . "=$member_value))";
    }

    // Need to search on all basedns one at a time.
    foreach ($this->basedn as $base_dn) {
      // Only need dn, so empty array forces return of no attributes.
      $group_entries = $this->search($base_dn, $group_query, []);
      if ($group_entries !== FALSE) {
        $max_levels = ($nested) ? LDAP_SERVER_LDAP_QUERY_RECURSION_LIMIT : 0;
        $this->groupMembershipsFromEntryRecursive($group_entries, $all_group_dns, $tested_group_ids, $level, $max_levels);
      }
    }

    return $all_group_dns;
  }

  /**
   * Recurse through all groups, adding parent groups to $all_group_dns array.
   *
   * @param array $current_group_entries
   *   of ldap group entries that are starting point.  should include at least 1 entry.
   * @param array $all_group_dns
   *   as array of all groups user is a member of.  MIXED CASE VALUES.
   * @param array $tested_group_ids
   *   as array of tested group dn, cn, uid, etc.  MIXED CASE VALUES
   *   whether these value are dn, cn, uid, etc depends on what attribute members, uniquemember, memberUid contains
   *   whatever attribute is in $this->$tested_group_ids to avoid redundant recursing.
   * @param int $level
   *   of recursion.
   * @param int $max_levels
   *   as max recursion allowed
   *
   *   given set of groups entries ($current_group_entries such as it, hr, accounting),
   *   find parent groups (such as staff, people, users) and add them to list of group memberships ($all_group_dns)
   *
   *   (&(objectClass=[$this->groupObjectClass])(|([$this->groupMembershipsAttr]=groupid1)([$this->groupMembershipsAttr]=groupid2))
   *
   * @return FALSE for error or misconfiguration, otherwise TRUE.  results are passed by reference.
   */
  public function groupMembershipsFromEntryRecursive($current_group_entries, &$all_group_dns, &$tested_group_ids, $level, $max_levels) {

    if (!$this->groupGroupEntryMembershipsConfigured || !is_array($current_group_entries) || count($current_group_entries) == 0) {
      return FALSE;
    }
    if (isset($current_group_entries['count'])) {
      unset($current_group_entries['count']);
    };

    $ors = [];
    foreach ($current_group_entries as $i => $group_entry) {
      if ($this->groupMembershipsAttrMatchingUserAttr == 'dn') {
        $member_id = $group_entry['dn'];
      }
      // Maybe cn, uid, etc is held.
      else {
        $member_id = ldap_servers_get_first_rdn_value_from_dn($group_entry['dn'], $this->groupMembershipsAttrMatchingUserAttr);
        if (!$member_id) {
          if ($this->detailed_watchdog_log) {
            watchdog('ldap_servers', 'group_entry: %ge', ['%ge' => pretty_print_ldap_entry($group_entry)]);
          }
          // Group not identified by simple checks yet!
          // examine the entry and see if it matches the configured groupObjectClass
          // TODO do we need to ensure such entry is there?
          $goc = $group_entry['objectclass'];
          // TODO is it always an array?
          if (is_array($goc)) {
            foreach ($goc as $g) {
              $g = drupal_strtolower($g);
              if ($g == $this->groupObjectClass) {
                // Found a group, current user must be member in it - so:
                if ($this->detailed_watchdog_log) {
                  watchdog('ldap_servers', 'adding %mi', ['%mi' => $member_id]);
                }
                $member_id = $group_entry['dn'];
                break;
              }
            }
          }
        }
      }

      if ($member_id && !in_array($member_id, $tested_group_ids)) {
        $tested_group_ids[] = $member_id;
        $all_group_dns[] = $group_entry['dn'];
        // Add $group_id (dn, cn, uid) to query.
        $ors[] = $this->groupMembershipsAttr . '=' . ldap_pear_escape_filter_value($member_id);
      }
    }

    if ($level < $max_levels && count($ors)) {
      $count = count($ors);
      // Only 50 or so per query.
      for ($i = 0; $i < $count; $i = $i + LDAP_SERVER_LDAP_QUERY_CHUNK) {
        $current_ors = array_slice($ors, $i, LDAP_SERVER_LDAP_QUERY_CHUNK);
        // e.g. (|(cn=group1)(cn=group2)) or   (|(dn=cn=group1,ou=blah...)(dn=cn=group2,ou=blah...))
        $or = '(|(' . join(")(", $current_ors) . '))';
        $query_for_parent_groups = '(&(objectClass=' . $this->groupObjectClass . ')' . $or . ')';

        // Need to search on all basedns one at a time.
        foreach ($this->basedn as $base_dn) {
          // No attributes, just dns needed.
          $group_entries = $this->search($base_dn, $query_for_parent_groups);
          if ($group_entries !== FALSE) {
            $this->groupMembershipsFromEntryRecursive($group_entries, $all_group_dns, $tested_group_ids, $level + 1, $max_levels);
          }
        }
      }
    }

    return TRUE;
  }

  /**
   * Get "groups" from derived from DN.  Has limited usefulness.
   *
   * @param mixed
   *   - drupal user object (stdClass Object)
   *    - ldap entry of user (array) (with top level keys of 'dn', 'mail', 'sid' and 'attr' )
   *    - ldap dn of user (array)
   *    - drupal username of user (string)
   *
   * @return array of group strings
   */
  public function groupUserMembershipsFromDn($user) {

    if (!$this->groupDeriveFromDn || !$this->groupDeriveFromDnAttr) {
      return FALSE;
    }
    elseif ($user_ldap_entry = $this->userUserToExistingLdapEntry($user)) {
      return ldap_servers_get_all_rdn_values_from_dn($user_ldap_entry['dn'], $this->groupDeriveFromDnAttr);
    }
    else {
      return FALSE;
    }

  }

  /**
   * Error methods and properties.
   */

  public $detailedWatchdogLog = FALSE;
  protected $_errorMsg = NULL;
  protected $_hasError = FALSE;
  protected $_errorName = NULL;

  /**
   *
   */
  public function setError($_errorName, $_errorMsgText = NULL) {
    $this->_errorMsgText = $_errorMsgText;
    $this->_errorName = $_errorName;
    $this->_hasError = TRUE;
  }

  /**
   *
   */
  public function clearError() {
    $this->_hasError = FALSE;
    $this->_errorMsg = NULL;
    $this->_errorName = NULL;
  }

  /**
   *
   */
  public function hasError() {
    return ($this->_hasError || $this->ldapErrorNumber());
  }

  /**
   *
   */
  public function errorMsg($type = NULL) {
    if ($type == 'ldap' && $this->connection) {
      return ldap_err2str(ldap_errno($this->connection));
    }
    elseif ($type == NULL) {
      return $this->_errorMsg;
    }
    else {
      return NULL;
    }
  }

  /**
   *
   */
  public function errorName($type = NULL) {
    if ($type == 'ldap' && $this->connection) {
      return "LDAP Error: " . ldap_error($this->connection);
    }
    elseif ($type == NULL) {
      return $this->_errorName;
    }
    else {
      return NULL;
    }
  }

  /**
   *
   */
  public function ldapErrorNumber() {
    if ($this->connection && ldap_errno($this->connection)) {
      return ldap_errno($this->connection);
    }
    else {
      return FALSE;
    }
  }

}

/**
 * Class for enabling rebind functionality for following referrrals.
 */
class LdapServersRebindHandler {

  private $bind_dn = 'Anonymous';
  private $bind_passwd = '';

  /**
   *
   */
  public function __construct($bind_user_dn, $bind_user_passwd) {
    $this->bind_dn = $bind_user_dn;
    $this->bind_passwd = $bind_user_passwd;
  }

  /**
   *
   */
  public function rebind_callback($ldap, $referral) {
    // Ldap options.
    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 1);
    ldap_set_rebind_proc($ldap, [$this, 'rebind_callback']);

    // Bind to new host, assumes initial bind dn has access to the referred servers.
    if (!ldap_bind($ldap, $this->bind_dn, $this->bind_passwd)) {
      echo "Could not bind to referral server: $referral";
      return 1;
    }
    return 0;
  }

}
