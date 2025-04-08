<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2009-2022 by the resources Development Team.

 https://github.com/InfotelGLPI/resources
 -------------------------------------------------------------------------

 LICENSE

 This file is part of resources.

 resources is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 resources is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with resources. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

use Glpi\Toolbox\Sanitizer;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginResourcesBudget
 */
class PluginResourcesLDAP extends CommonDBTM
{

    static $rightname = 'plugin_resources_budget';
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
    static function getTypeName($nb = 0)
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
    static function canView()
    {
        return Session::haveRight(self::$rightname, READ);
    }

    /**
     * Have I the global right to "create" the Object
     * May be overloaded if needed (ex KnowbaseItem)
     *
     * @return bool
     **/
    static function canCreate()
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
    function __construct()
    {
    }

    function connect($authsId)
    {
        $ldap = new AuthLDAP;
        $ldap->getFromDB($authsId);
        $ldap_connection = $ldap->connect();
        return $ldap_connection;
    }

    private static function getConfig()
    {
        $config_ldap = new AuthLDAP();
        $configAD = new PluginResourcesAdconfig();
        $configAD->getFromDB(1);
        $authID = $configAD->fields["auth_id"];
        $res = $config_ldap->getFromDB($authID);


        // Create a configuration array.
        if (($ret = strpos($config_ldap->fields['host'], 'ldaps://')) !== false) {
            $host = str_replace('ldaps://', '', $config_ldap->fields['host']);
            $ssl = true;
        } elseif (($ret = strpos($config_ldap->fields['host'], 'ldap://')) !== false) {
            $host = str_replace('ldap://', '', $config_ldap->fields['host']);
            $ssl = false;
        } else {
            $host = $config_ldap->fields['host'];
            $ssl = false;
        }
        if ($config_ldap->fields['deref_option']) {
            $deref = true;
        } else {
            $deref = false;
        }
        if ($config_ldap->fields['use_tls']) {
            $tls = true;
        } else {
            $tls = false;
        }

        $config = [
            // An array of your LDAP hosts. You can use either
            // the host name or the IP address of your host.
            'hosts' => [$host],
            'port' => $config_ldap->fields['port'],
            'use_tls' => $tls,
            'use_ssl' => $ssl,
            'follow_referrals' => $deref,

            // The base distinguished name of your domain to perform searches upon.
            'base_dn' => $config_ldap->fields['basedn'],

            // The account to use for querying / modifying LDAP records. This
            // does not need to be an admin account. This can also
            // be a full distinguished name of the user account.
            'username' => $configAD->fields['login'],
            'password' => (new GLPIKey())->decrypt($configAD->fields['password']),
        ];
//      Toolbox::logWarning($config);
        return $config;
    }

    function getUserInformation($authID)
    {
        // Construct new Adldap instance.
        $ad = new \Adldap\Adldap();
        $config_ldap = new AuthLDAP();
        $res = $config_ldap->getFromDB($authID);


        // Create a configuration array.
        if (($ret = strpos($config_ldap->fields['host'], 'ldaps://')) !== false) {
            $host = str_replace('ldaps://', '', $config_ldap->fields['host']);
            $ssl = true;
        } elseif (($ret = strpos($config_ldap->fields['host'], 'ldap://')) !== false) {
            $host = str_replace('ldap://', '', $config_ldap->fields['host']);
            $ssl = false;
        } else {
            $host = $config_ldap->fields['host'];
            $ssl = false;
        }

        $config = [
            // An array of your LDAP hosts. You can use either
            // the host name or the IP address of your host.
            'hosts' => [$host],
            'port' => $config_ldap->fields['port'],
            'use_tls' => !!$config_ldap->fields['use_tls'],
            'use_ssl' => $ssl,
            'follow_referrals' => !!$config_ldap->fields['deref_option'],
            'version' => 3,

            // The base distinguished name of your domain to perform searches upon.
            'base_dn' => $config_ldap->fields['basedn'],

            // The account to use for querying / modifying LDAP records. This
            // does not need to be an admin account. This can also
            // be a full distinguished name of the user account.
            'username' => $config_ldap->fields['rootdn'],
            'password' => (new GLPIKey())->decrypt($config_ldap->fields['rootdn_passwd']),
        ];

        // Add a connection provider to Adldap.
        $ad->addProvider($config);

        try {
            // If a successful connection is made to your server, the provider will be returned.
            $provider = $ad->connect();

            // Performing a query.
            $results = $provider->search()->where('samaccountname', '=', 'ales')->get();
//         Toolbox::logWarning($results);
        } catch (\Adldap\Auth\BindException $e) {
            // There was an issue binding / connecting to the server.

        }
    }

    function existingUser($login)
    {
        $find = false;
        $adConfig = new PluginResourcesAdconfig();
        $adConfig->getFromDB(1);
        $ad = new \Adldap\Adldap();
        $config = self::getConfig();
        $ad->addProvider($config);


        try {
            $provider = $ad->connect();
            $search = $provider->search();
            $record = $search->findByOrFail($adConfig->getField("logAD"), $login);

            $find = true;
        } catch (Adldap\Models\ModelNotFoundException $e) {
            // Record wasn't found!
            $find = false;
        }
        return $find;
    }

    function createUserAD($data)
    {
        $adConfig = new PluginResourcesAdconfig();
        $adConfig->getFromDB(1);
        $ad = new \Adldap\Adldap();
        $config = self::getConfig();
        $ad->addProvider($config);
        try {
            $provider = $ad->connect();
            $user = $provider->make()->user();

            // Create the users distinguished name.
            // We're adding an OU onto the users base DN to have it be saved in the specified OU.
//         $dn = $user->getDnBuilder()->addOu($adConfig->getField("ouUser")); // Built DN will be: "CN=John Doe,OU=Users,DC=acme,DC=org";
//         $dn->addCn($data["firstname"]." ".$data["name"]);
//         // Set the users DN, account name.
//         $user->setDn($dn);
            $dn = "CN=" . $data["name"] . " " . $data["firstname"] . "," . $adConfig->getField("ouUser");
            $user->setDn($dn);
            $user->setAccountName($data['login']);
            $user->setCommonName($data["name"] . " " . $data["firstname"]);


            $attributes = [];
            $attr = $adConfig->getArrayAttributes();
            foreach ($attr as $at) {
                if (!empty($adConfig->getField($at))) {
                    $a = PluginResourcesLinkAd::getMapping($at);
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

			$firstname = $data["firstname"];
			$name = $data["name"];
	        $date_begin = $data["date_begin"];

	        $setPassword = $this->setPasswordUser($firstname,$name, $date_begin);

            $user->setPassword($setPassword);

            if ($user->save()) {
				$config = new PluginResourcesConfig();
	            if($config->getField('	create_ticket_template') != 0){
		            $this->createTicket($config->getField('create_ticket_template'));
	            }
                return true;
            } else {
                return false;
            }
        } catch (Adldap\Models\ModelNotFoundException $e) {
            // Record wasn't found!
            return false;
        } catch (\Adldap\AdldapException $e) {
            return false;
        }
    }

    function updateUserAD($data)
    {
        $adConfig = new PluginResourcesAdconfig();
        $adConfig->getFromDB(1);
        $ad = new \Adldap\Adldap();
        $config = self::getConfig();
        $ad->addProvider($config);
        try {
            $provider = $ad->connect();
            $user = $provider->search()->whereEquals($adConfig->getField("logAD"), $data["login"])->firstOrFail();

            // Create the users distinguished name.
            // We're adding an OU onto the users base DN to have it be saved in the specified OU.
//         $user->setCommonName($data["firstname"]." ".$data["name"]);


            $attributes = [];
            $attr = $adConfig->getArrayAttributes();
            foreach ($attr as $at) {
                if (!empty($adConfig->getField($at))) {
                    $a = PluginResourcesLinkAd::getMapping($at);
                    if (isset($data[$a])) {
                        if (empty($data[$a]) && $at != "contractEndAD") {
                            $user->setAttribute($adConfig->getField($at), null);
                            $attributes[$adConfig->getField($at)] = array();
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
            if ($user->save()) {
                if ($rename) {
                    $ncn = "cn=" . $data["name"] . " " . $data["firstname"];
                    if ($user->rename($ncn)) {
                        return [true, $new_value];
                    }
                    return [false, $new_value];
                }

	            $config = new PluginResourcesConfig();
	            if($config->getField('update_ticket_template') != 0){
		            $this->createTicket($config->getField('update_ticket_template'));
	            }

                return [true, $new_value];
            } else {
                return [false, $new_value];
            }
        } catch (Adldap\Models\ModelNotFoundException $e) {
            // Record wasn't found!
            return [false, []];
        }
    }

    function disableUserAD($data)
    {
        $adConfig = new PluginResourcesAdconfig();
        $adConfig->getFromDB(1);
        $ad = new \Adldap\Adldap();
        $config = self::getConfig();
        $ad->addProvider($config);
        try {
            $provider = $ad->connect();
            $user = $provider->search()->whereEquals($adConfig->getField("logAD"), $data["login"])->firstOrFail();


            $attributes = [];
            $attr = $adConfig->getArrayAttributes();
            $ac = $user->getUserAccountControlObject();

            // Mark the account as enabled (normal).
            $ac->accountIsDisabled();
            $user->setUserAccountControl($ac);

            if ($user->save()) {
//            $newParentDn = $user->getDnBuilder()->addOu($adConfig->getField("ouDesactivateUserAD"));
//            $newParentDn = $newParentDn->removeOu($adConfig->getField("ouUser"));
//            $newParentDn = $newParentDn->removeCn($user->getCommonName());
                $newParentDn = $adConfig->getField("ouDesactivateUserAD");
                if ($user->move($newParentDn)) {
	                if($config->getField('leave_ticket_template') != 0){
						$this->createTicket($config->getField('leave_ticket_template'));
	                }
					return true;
                }
                return false;
            } else {
                return false;
            }
        } catch (Adldap\Models\ModelNotFoundException $e) {
            // Record wasn't found!
            return false;
        }
    }

    function ldapTimeToUnixTime($ldapTime)
    {
        $secsAfterADEpoch = $ldapTime / 10000000;
        $ADToUnixConverter = ((1970 - 1601) * 365 - 3 + round((1970 - 1601) / 4)) * 86400;
        return intval($secsAfterADEpoch - $ADToUnixConverter);
    }

    function unixTimeToLdapTime($unixTime)
    {
        $ADToUnixConverter = ((1970 - 1601) * 365 - 3 + round((1970 - 1601) / 4)) * 86400;
        $secsAfterADEpoch = intval($ADToUnixConverter + $unixTime);
        return $secsAfterADEpoch * 10000000;
    }

	private function setPasswordUser(string $firstname, string $name, $date)
	{

		$adconfig = new PluginResourcesAdconfig();
		$datas = $adconfig->find(['id' => 1]);

		$user_initial = 0;
		$user_date = 0;
		$password_end = '';

		foreach ($datas as $item) {
			$user_initial = $item['user_initial'];
			$user_date = $item['user_date'];
			$password_end = $item['password_end'];
		}

		if($user_initial != 0){
			$firstLetter_firstname = ucfirst(substr($firstname, 0, 1));
			$firstLetter_name = ucfirst(substr($name, 0, 1));
		}else{
			$firstLetter_firstname = '';
			$firstLetter_name = '';
		}

		if($user_date != 0){
			$date_begin = new DateTime($date);
			$date_begin = $date_begin->format("$user_date");
		}else{
			$date_begin = '';
		}

		return $firstLetter_firstname.$firstLetter_name.$date_begin.$password_end;

	}

	private function createTicket($id_template)
	{
		global $DB;
		if(isset($id_template)){
			$template = new PluginResourcesTicketTemplate();
			$templateUser = new PluginResourcesTicketTemplateUser();
			$templateGroup = new PluginResourcesGroupTicketTemplate();

			$datas = $template->find(['id' => $id_template]);
			$ticket_insert = $datas[$id_template];
			$ticket = new Ticket();
			$ticket->add([
				'entities_id' => $ticket_insert['entities_id'],
				'name' => $ticket_insert['name'],
				'content' => $ticket_insert['content'],
				'itilcategories_id' => $ticket_insert['itilcategories_id'],
				'type' => $ticket_insert['type'],
				'urgency' => 2,
				'impact' => 2,
				'priority' => 2,
				'date' => date_create(),
				'date_creation' => date_create(),
			]);

			$datas_users = $templateUser->find(['plugin_resources_tickettemplates_id' => $id_template]);

			$last_id = $DB->insertId();

			foreach ($datas_users as $datas_user) {
				$ticket_user = new Ticket_User();
				$ticket_user->add([
					'tickets_id' => $last_id,
					'users_id' => $datas_user['users_id'],
					'type' => $datas_user['type'],
					'use_notification' => 1
				]);
			}

			$datas_groups = $templateGroup->find(['plugin_resources_tickettemplates_id' => $id_template]);

			foreach ($datas_groups as $datas_group) {
				$group_ticket = new Group_Ticket();
				$group_ticket->add([
					'tickets_id' => $last_id,
					'groups_id' => $datas_group['groups_id'],
					'type' => $datas_group['type'],
				]);
			}

		}

	}


}

