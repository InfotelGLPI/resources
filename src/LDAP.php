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

    private static function getConfig()
    {
        $config_ldap = new AuthLDAP();
        $configAD = new Adconfig();
        $configAD->getFromDB(1);
        $authID = $configAD->fields["auth_id"] ?? 0;
        $res = $config_ldap->getFromDB($authID);

        // The AuthLDAP record may not be loaded yet (no auth_id configured,
        // or the referenced directory was deleted): fall back to empty values
        // instead of dereferencing undefined field keys.
        $raw_host = $config_ldap->fields['host'] ?? '';

        // Create a configuration array.
        if (strpos($raw_host, 'ldaps://') !== false) {
            $host = str_replace('ldaps://', '', $raw_host);
            $ssl = true;
        } elseif (strpos($raw_host, 'ldap://') !== false) {
            $host = str_replace('ldap://', '', $raw_host);
            $ssl = false;
        } else {
            $host = $raw_host;
            $ssl = false;
        }
        $deref = !empty($config_ldap->fields['deref_option']);
        $tls   = !empty($config_ldap->fields['use_tls']);

        $config = [
            // An array of your LDAP hosts. You can use either
            // the host name or the IP address of your host.
            'hosts' => [$host],
            'port' => $config_ldap->fields['port'] ?? 389,
            'use_tls' => $tls,
            'use_ssl' => $ssl,
            'follow_referrals' => $deref,

            // The base distinguished name of your domain to perform searches upon.
            'base_dn' => $config_ldap->fields['basedn'] ?? '',

            // The account to use for querying / modifying LDAP records. This
            // does not need to be an admin account. This can also
            // be a full distinguished name of the user account.
            'username' => $configAD->fields['login'] ?? '',
            'password' => (new GLPIKey())->decrypt($configAD->fields['password'] ?? ''),
        ];
        //      Toolbox::logWarning($config);
        return $config;
    }

    public function getUserInformation($authID)
    {
        $config_ldap = new AuthLDAP();
        $res = $config_ldap->getFromDB($authID);

        // Guard against a missing/unloaded directory record.
        $raw_host = $config_ldap->fields['host'] ?? '';

        // Create a configuration array.
        if (strpos($raw_host, 'ldaps://') !== false) {
            $host = str_replace('ldaps://', '', $raw_host);
            $ssl = true;
        } elseif (strpos($raw_host, 'ldap://') !== false) {
            $host = str_replace('ldap://', '', $raw_host);
            $ssl = false;
        } else {
            $host = $raw_host;
            $ssl = false;
        }

        $config = [
            // An array of your LDAP hosts. You can use either
            // the host name or the IP address of your host.
            'hosts' => [$host],
            'port' => $config_ldap->fields['port'] ?? 389,
            'use_tls' => !empty($config_ldap->fields['use_tls']),
            'use_ssl' => $ssl,
            'follow_referrals' => !empty($config_ldap->fields['deref_option']),
            'version' => 3,

            // The base distinguished name of your domain to perform searches upon.
            'base_dn' => $config_ldap->fields['basedn'] ?? '',

            // The account to use for querying / modifying LDAP records. This
            // does not need to be an admin account. This can also
            // be a full distinguished name of the user account.
            'username' => $config_ldap->fields['rootdn'] ?? '',
            'password' => (new GLPIKey())->decrypt($config_ldap->fields['rootdn_passwd'] ?? ''),
        ];

        $connection = new Connection($config);

        try {
            // Bind to the server to validate the connection settings.
            $connection->connect();
        } catch (BindException $e) {
            // There was an issue binding / connecting to the server.
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

    public function isSSLorTLSAD()
    {
        $adConfig = new Adconfig();
        $config = self::getConfig();
        return $config['use_tls'] || $config['use_ssl'];
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
            if (($config['use_tls'] || $config['use_ssl']) && $adConfig->fields['use_password_module']) {
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
