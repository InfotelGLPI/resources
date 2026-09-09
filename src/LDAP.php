<?php

/**
 * -------------------------------------------------------------------------
 * resources plugin for GLPI
 * Copyright (C) 2015-2026 by the resources Development Team.
 *
 * https://github.com/InfotelGLPI/resources
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of resources.
 *
 * resources is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * resources is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with resources. If not, see <http://www.gnu.org/licenses/>.
 * --------------------------------------------------------------------------
 */

namespace GlpiPlugin\Resources;

use AuthLDAP;
use CommonDBTM;
use Exception;
use Glpi\Toolbox\Filesystem;
use GLPIKey;
use LdapRecord\Auth\BindException;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Attributes\AccountControl;
use LdapRecord\Models\ModelNotFoundException;
use Session;
use Toolbox;

/**
 * Class LDAP
 */
class LDAP extends CommonDBTM
{
    public static $rightname = 'plugin_resources';
    // From CommonDBTM
    public $dohistory = true;

    /**
     * Return the localized name of the current Type
     * Should be overloaded in each new class
     *
     * @param integer $nb Number of items
     *
     * @return string
     **/
    public static function getTypeName($nb = 0)
    {
        return __('LDAP', 'resources');
    }

    /**
     * Have I the global right to "view" the Object
     *
     * Default is true and check entity if the objet is entity assign
     *
     * May be overloaded if needed
     *
     * @return bool
     **/
    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }

    /**
     * Have I the global right to "create" the Object
     * May be overloaded if needed (ex KnowbaseItem)
     *
     * @return bool
     **/
    public static function canCreate(): bool
    {
        return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]);
    }

    /**
     * Display Tab for each budget
     *
     * @param array $options
     *
     * @return array
     */
    //   function defineTabs($options = []) {
    //
    //      $ong = [];
    //
    //      $this->addDefaultFormTab($ong);
    //      $this->addStandardTab('Document', $ong, $options);
    //      $this->addStandardTab('Log', $ong, $options);
    //
    //      return $ong;
    //   }

    /**
     * allow to control data before adding in bdd
     *
     * @param $input
     * @return array
     */
    //   function prepareInputForAdd($input) {
    //
    //      if (!isset($input["plugin_resources_professions_id"]) || $input["plugin_resources_professions_id"] == '0') {
    //         Session::addMessageAfterRedirect(__('The profession for the budget must be filled', 'resources'), false, ERROR);
    //         return [];
    //      }
    //
    //      return $input;
    //   }

    /**
     * allow to control data before updating in bdd
     *
     * @param $input
     * @return array
     */
    //   function prepareInputForUpdate($input) {
    //
    //      if (!isset($input["plugin_resources_professions_id"]) || $input["plugin_resources_professions_id"] == '0') {
    //         Session::addMessageAfterRedirect(__('The profession for the budget must be filled', 'resources'), false, ERROR);
    //         return [];
    //      }
    //
    //      return $input;
    //   }

    /**
     * PluginInsightvmInsightvm constructor.
     */
    public function __construct() {}

    public function connect($authsId)
    {
        $ldap = new AuthLDAP();
        $ldap->getFromDB($authsId);
        $ldap_connection = $ldap->connect();
        return $ldap_connection;
    }

    /**
     * Split the host field of a directory into the host list LdapRecord expects.
     *
     * The scheme used to be looked for with strpos() anywhere in the value and stripped with
     * str_replace(), which mangles a field naming several servers and mistakes a host whose name
     * merely contains "ldaps://" for a secure one. Each entry is parsed on its own, with the same
     * idiom as AuthLDAP::buildUri().
     *
     * @param string $raw_host the host field of the AuthLDAP record
     *
     * @return array{0: string[], 1: bool} the bare host names, and whether they are all LDAPS
     */
    private static function parseHosts(string $raw_host): array
    {
        $entries = preg_split('/[\s,]+/', trim($raw_host), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $hosts  = [];
        $secure = [];
        foreach ($entries as $entry) {
            $hosts[]  = (string) preg_replace('@^ldaps?://@i', '', $entry);
            $secure[] = stripos($entry, 'ldaps://') === 0;
        }

        if ($hosts === []) {
            // No directory configured yet: keep the shape of the return value so callers do not
            // have to special case it.
            return [[''], false];
        }

        // One connection cannot be LDAPS for the first host and plain for the next: only call the
        // list secure when every entry says so.
        return [$hosts, !in_array(false, $secure, true)];
    }

    /**
     * Transport options pinned on the connections built below.
     *
     * LdapRecord applies these through ldap_set_option() from Connection::configure(), which runs
     * before ldap_connect(): they therefore land on the global handle, which is where libldap
     * reads LDAP_OPT_X_TLS_REQUIRE_CERT from when it negotiates the handshake.
     *
     * @param AuthLDAP $config_ldap the directory GLPI is configured with
     *
     * @return array<int, mixed>
     */
    private static function getTransportOptions(AuthLDAP $config_ldap): array
    {
        // Whether the server certificate is checked at all used to be decided by the ldap.conf of
        // the host, outside GLPI. This connection writes the initial password of the accounts it
        // creates (see createUserAD()), so a TLS_REQCERT never left there was enough for anyone on
        // the path to present their own certificate and read both the bind credentials and those
        // passwords. Validation is the default now, and switching it off is an explicit stored
        // decision that also closes the password write.
        $options = [
            LDAP_OPT_X_TLS_REQUIRE_CERT => Adconfig::allowsInvalidCertificate()
                ? LDAP_OPT_X_TLS_NEVER
                : LDAP_OPT_X_TLS_HARD,
        ];

        // Client certificate and TLS version: same fields and same safety checks as
        // AuthLDAP::connectToServer(), so both connections to the same directory behave alike.
        $certfile = (string) ($config_ldap->fields['tls_certfile'] ?? '');
        if ($certfile !== '' && Filesystem::isFilepathSafe($certfile) && file_exists($certfile)) {
            $options[LDAP_OPT_X_TLS_CERTFILE] = $certfile;
        }

        $keyfile = (string) ($config_ldap->fields['tls_keyfile'] ?? '');
        if ($keyfile !== '' && Filesystem::isFilepathSafe($keyfile) && file_exists($keyfile)) {
            $options[LDAP_OPT_X_TLS_KEYFILE] = $keyfile;
        }

        $tls_version = (string) ($config_ldap->fields['tls_version'] ?? '');
        if ($tls_version !== '') {
            $cipher_suite = 'NORMAL';
            foreach (AuthLDAP::TLS_VERSIONS as $version) {
                $cipher_suite .= ($version === $tls_version ? ':+' : ':!') . 'VERS-TLS' . $version;
            }
            $options[LDAP_OPT_X_TLS_CIPHER_SUITE] = $cipher_suite;
        }

        return $options;
    }

    /**
     * Build the LdapRecord configuration of the account provisioning connection.
     *
     * @return array<string, mixed>
     */
    private static function getConfig()
    {
        $config_ldap = new AuthLDAP();
        $configAD = new Adconfig();
        $configAD->getFromDB(1);
        $authID = $configAD->fields["auth_id"] ?? 0;
        $config_ldap->getFromDB($authID);

        // The AuthLDAP record may not be loaded yet (no auth_id configured,
        // or the referenced directory was deleted): fall back to empty values
        // instead of dereferencing undefined field keys.
        [$hosts, $ssl] = self::parseHosts((string) ($config_ldap->fields['host'] ?? ''));

        // Create a configuration array.
        return [
            // An array of your LDAP hosts. You can use either
            // the host name or the IP address of your host.
            'hosts' => $hosts,
            'port' => $config_ldap->fields['port'] ?? 389,
            'use_tls' => !empty($config_ldap->fields['use_tls']),
            'use_ssl' => $ssl,
            'follow_referrals' => !empty($config_ldap->fields['deref_option']),
            // getUserInformation() pinned the protocol version and this one did not, which left
            // the connection that writes to the directory on whatever the library defaults to.
            'version' => 3,
            'timeout' => (int) ($config_ldap->fields['timeout'] ?? 10),

            // The base distinguished name of your domain to perform searches upon.
            'base_dn' => $config_ldap->fields['basedn'] ?? '',

            // The account to use for querying / modifying LDAP records. This
            // does not need to be an admin account. This can also
            // be a full distinguished name of the user account.
            'username' => $configAD->fields['login'] ?? '',
            'password' => (new GLPIKey())->decrypt($configAD->fields['password'] ?? ''),
            'options' => self::getTransportOptions($config_ldap),
        ];
    }

    /**
     * Bind to a directory to validate the credentials stored on its AuthLDAP record.
     *
     * @param int|string $authID
     *
     * @return bool whether the bind succeeded
     */
    public function getUserInformation($authID)
    {
        $config_ldap = new AuthLDAP();
        $config_ldap->getFromDB($authID);

        // Guard against a missing/unloaded directory record.
        [$hosts, $ssl] = self::parseHosts((string) ($config_ldap->fields['host'] ?? ''));

        // Create a configuration array.
        $config = [
            // An array of your LDAP hosts. You can use either
            // the host name or the IP address of your host.
            'hosts' => $hosts,
            'port' => $config_ldap->fields['port'] ?? 389,
            'use_tls' => !empty($config_ldap->fields['use_tls']),
            'use_ssl' => $ssl,
            'follow_referrals' => !empty($config_ldap->fields['deref_option']),
            'version' => 3,
            'timeout' => (int) ($config_ldap->fields['timeout'] ?? 10),

            // The base distinguished name of your domain to perform searches upon.
            'base_dn' => $config_ldap->fields['basedn'] ?? '',

            // The account to use for querying / modifying LDAP records. This
            // does not need to be an admin account. This can also
            // be a full distinguished name of the user account.
            'username' => $config_ldap->fields['rootdn'] ?? '',
            'password' => (new GLPIKey())->decrypt($config_ldap->fields['rootdn_passwd'] ?? ''),
            'options' => self::getTransportOptions($config_ldap),
        ];

        $connection = new Connection($config);

        try {
            // Bind to the server to validate the connection settings.
            $connection->connect();
            return true;
        } catch (BindException $e) {
            // The exception used to be dropped here, which made an unreachable server or an
            // expired service account indistinguishable from a directory that simply answered.
            Toolbox::logInFile(
                'LDAPERROR',
                sprintf('Bind to directory %d failed: %s', (int) $authID, $e->getMessage()),
            );
            return false;
        }
    }

    /**
     * Build an initial Active Directory password no one can derive from the identity of the
     * account holder. One character is drawn from each class first so the result satisfies the
     * default AD complexity policy, then the whole string is shuffled with random_int().
     *
     * @param int $length total length of the generated password
     *
     * @return string
     */
    public static function generateRandomPassword(int $length = 20): string
    {
        // Ambiguous glyphs (0/O, 1/l/I) are left out: the password is read out loud or typed
        // from a printout before its first use.
        $alphabets = [
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'abcdefghijkmnpqrstuvwxyz',
            '23456789',
            '!@#$%*-_=+?',
        ];

        $chars = [];
        foreach ($alphabets as $alphabet) {
            $chars[] = $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $all = implode('', $alphabets);
        while (count($chars) < max($length, count($alphabets))) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }

        // str_shuffle() draws from a non-cryptographic generator: shuffle by hand.
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    public function existingUser($login)
    {
        $find = false;
        $adConfig = new Adconfig();
        $adConfig->getFromDB(1);
        $config = self::getConfig();
        Container::addConnection(new Connection($config));

        try {
            User::findByOrFail($adConfig->getField("logAD"), $login);

            $find = true;
        } catch (ModelNotFoundException $e) {
            // Record wasn't found!
            $find = false;
        }
        return $find;
    }

    /**
     * Whether the directory connection can be trusted with a secret.
     *
     * Encryption alone is not enough for the password write this gates: without certificate
     * validation the far end of the channel is not authenticated, so it may well be someone on
     * the path rather than the domain controller.
     *
     * @return bool
     */
    public static function isTrustedADChannel(): bool
    {
        $config = self::getConfig();

        return ($config['use_tls'] || $config['use_ssl']) && !Adconfig::allowsInvalidCertificate();
    }

    public function createUserAD($data)
    {
        $adConfig = new Adconfig();
        $adConfig->getFromDB(1);
        $config = self::getConfig();
        Container::addConnection(new Connection($config));
        try {
            $user = new User();

            // Create the users distinguished name.
            // We're adding an OU onto the users base DN to have it be saved in the specified OU.
            // The identity comes from the resource form: the RFC 4514 special characters
            // (, + " \\ < > ; =) would otherwise close the CN component and let the caller pick
            // the OU the account is created in. Escape the value, and assert the result still
            // sits under the configured OU.
            $cn = trim($data["name"] . " " . $data["firstname"]);
            $ou = (string) $adConfig->getField("ouUser");
            if ($cn === '' || $ou === '') {
                return false;
            }
            $dn = "CN=" . ldap_escape($cn, '', LDAP_ESCAPE_DN) . "," . $ou;
            if (!str_ends_with($dn, ',' . $ou)) {
                return false;
            }
            $user->setDn($dn);
            $user->setFirstAttribute('samaccountname', $data['login']);
            // Attribute values are not DN components: the unescaped name belongs here.
            $user->setFirstAttribute('cn', $cn);


            $attributes = [];
            $attr = $adConfig->getArrayAttributes();
            foreach ($attr as $at) {
                if (!empty($adConfig->getField($at))) {
                    $a = LinkAd::getMapping($at);
                    if (isset($data[$a]) && !empty($data[$a])) {
                        if ($at == "contractEndAD") {
                            $win_time = 0;
                            if (!empty($data[$a])) {
                                $unix_time = strtotime($data[$a]);
                                $win_time = $this->unixTimeToLdapTime($unix_time);
                            }
                            $data[$a] = $win_time;
                        }
                        $attributes[$adConfig->getField($at)] = $data[$a];
                        //                  if(empty($data[$a])){
                        //                     $attributes[$adConfig->getField($at)] = array();
                        //                  }
                    }
                }
            }
            $attributes['displayName'] = $data["firstname"] . " " . $data["name"];
            $attributes['description'] = $data["role"];
            $user->fill($attributes);

            // save() returns void and throws LdapRecordException on failure.
            $user->save();
            if (!empty($adConfig->fields['use_password_module'])) {
                if (!self::isTrustedADChannel()) {
                    // Fail closed: the account is created, but its initial password is not sent
                    // down a channel whose far end has not been authenticated. The gate used to
                    // ask for encryption only, and did so without saying anything when it
                    // declined.
                    Toolbox::logInFile(
                        'LDAPERROR',
                        'Initial password not written: the directory connection is not encrypted, '
                        . 'or its certificate is not validated.',
                    );
                    return true;
                }
                try {
                    $newPassword = '';
                    $format      = (int) $adConfig->fields['format_default_account_password'];
                    if ($format === Adconfig::PASSWORD_FORMAT_DYNAMIC) {
                        $newPassword = strtoupper(substr($data["firstname"], 0, 1))
                            . strtolower(substr($data["name"], 0, 1));
                        if ($adConfig->fields['prefix_default_account_password'] == 1 && isset($data['begindate'])) {
                            $date = substr($data["begindate"], 0, 10);
                            $date = explode('-', $date);
                            $newPassword .= $date[2] . $date[1] . $date[0] ;
                        }
                        $newPassword .= (new GLPIKey())->decrypt($adConfig->fields['default_account_password']);

                    } elseif ($format === Adconfig::PASSWORD_FORMAT_STATIC) {
                        $newPassword = (new GLPIKey())->decrypt($adConfig->fields['default_account_password']);
                    } elseif ($format === Adconfig::PASSWORD_FORMAT_RANDOM) {
                        $newPassword = self::generateRandomPassword();
                    }
                    if ($newPassword != '') {
                        // Reset the password. The 'unicodepwd' mutator auto-encodes it (UTF-16LE, quoted).
                        $user->setAttribute('unicodepwd', $newPassword);
                        // The first two formats derive the initial password from public identity
                        // data or share a single secret across every account: expiring it right
                        // away keeps the window in which it can be guessed to the first logon.
                        $user->setAttribute('pwdlastset', 0);
                        $user->save();
                    }
                    return true;
                } catch (Exception $ex) {
                    Toolbox::logInFile('LDAPERROR', "Erreur LDAP : " . $ex->getMessage());
                    return false;
                }
            }
            return true;
        } catch (LdapRecordException $e) {
            // Record wasn't found or the write failed.
            return false;
        }
    }

    public function updateUserAD($data)
    {
        $adConfig = new Adconfig();
        $adConfig->getFromDB(1);
        $config = self::getConfig();
        Container::addConnection(new Connection($config));
        try {
            $user = User::query()->whereEquals($adConfig->getField("logAD"), $data["login"])->firstOrFail();


            $attributes = [];
            $attr = $adConfig->getArrayAttributes();
            foreach ($attr as $at) {
                if (!empty($adConfig->getField($at))) {
                    $a = LinkAd::getMapping($at);
                    if (isset($data[$a])) {
                        if (empty($data[$a]) && $at != "contractEndAD") {
                            // An empty value marks the attribute for deletion on save.
                            $user->setAttribute($adConfig->getField($at), []);
                            $attributes[$adConfig->getField($at)] = [];
                        } else {
                            if ($at == "contractEndAD") {
                                $win_time = 0;
                                if (!empty($data[$a])) {
                                    $unix_time = strtotime($data[$a]);
                                    $win_time = $this->unixTimeToLdapTime($unix_time);
                                }
                                $data[$a] = $win_time;
                            }
                            $user->setAttribute($adConfig->getField($at), $data[$a]);
                            $attributes[$adConfig->getField($at)] = $data[$a];
                        }
                    }
                }
            }
            $rename = false;
            if (count($dirty = $user->getDirty())) {
                if (isset($dirty[$adConfig->getField("firstnameAD")]) || isset($dirty[$adConfig->getField("nameAD")])) {
                    $rename = true;
                }
            }
            $new_value = [];

            $attributesEnd = $user->getAttributes();
            foreach ($dirty as $k => $d) {
                if (isset($attributesEnd[$k])) {
                    $new_value[$k] = $attributesEnd[$k];
                }
            }
            // save() and rename() return void and throw LdapRecordException on failure.
            $user->save();
            if ($rename) {
                // rename() takes the new RDN verbatim: escape the identity so it cannot append
                // components of its own and move the account out of its OU.
                $ncn = trim($data["name"] . " " . $data["firstname"]);
                if ($ncn !== '') {
                    $user->rename("cn=" . ldap_escape($ncn, '', LDAP_ESCAPE_DN));
                }
            }

            return [true, $new_value];
        } catch (LdapRecordException $e) {
            // Record wasn't found or the write failed.
            return [false, $new_value ?? []];
        }
    }

    public function disableUserAD($data)
    {
        $adConfig = new Adconfig();
        $adConfig->getFromDB(1);
        $config = self::getConfig();
        Container::addConnection(new Connection($config));
        try {
            $user = User::query()->whereEquals($adConfig->getField("logAD"), $data["login"])->firstOrFail();


            $attributes = [];
            $attr = $adConfig->getArrayAttributes();
            $ac = new AccountControl((int) $user->getFirstAttribute('userAccountControl'));

            // Flip the disabled bit on the account control flags.
            $ac->setAccountIsDisabled();
            $user->setAttribute('userAccountControl', $ac->getValue());

            // save() and move() return void and throw LdapRecordException on failure.
            $user->save();
            //            $newParentDn = $user->getDnBuilder()->addOu($adConfig->getField("ouDesactivateUserAD"));
            //            $newParentDn = $newParentDn->removeOu($adConfig->getField("ouUser"));
            //            $newParentDn = $newParentDn->removeCn($user->getCommonName());
            $newParentDn = $adConfig->getField("ouDesactivateUserAD");
            $user->move($newParentDn);
            return true;
        } catch (LdapRecordException $e) {
            // Record wasn't found or the write failed.
            return false;
        }
    }


    public function ldapTimeToUnixTime($ldapTime)
    {
        $secsAfterADEpoch = $ldapTime / 10000000;
        $ADToUnixConverter = ((1970 - 1601) * 365 - 3 + round((1970 - 1601) / 4, 0, PHP_ROUND_HALF_UP)) * 86400;
        return intval($secsAfterADEpoch - $ADToUnixConverter);
    }

    public function unixTimeToLdapTime($unixTime)
    {
        $ADToUnixConverter = ((1970 - 1601) * 365 - 3 + round((1970 - 1601) / 4, 0, PHP_ROUND_HALF_UP)) * 86400;
        $secsAfterADEpoch = intval($ADToUnixConverter + $unixTime);
        return $secsAfterADEpoch * 10000000;
    }


}
