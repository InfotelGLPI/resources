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
if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginResourcesChecklist
 */
class PluginResourcesLinkAd extends CommonDBTM {

   static $rightname = 'plugin_resources_checklist';

   const RESOURCES_CHECKLIST_IN       = 1;
   const RESOURCES_CHECKLIST_OUT      = 2;
   const RESOURCES_CHECKLIST_TRANSFER = 3;

   /**
    * Return the localized name of the current Type
    * Should be overloaded in each new class
    *
    * @param integer $nb Number of items
    *
    * @return string
    **/
   static function getTypeName($nb = 0) {

      return __('Update LDAP directory', 'resources');
   }

   /**
    * Have I the global right to "view" the Object
    *
    * Default is true and check entity if the objet is entity assign
    *
    * May be overloaded if needed
    *
    * @return booleen
    **/
   static function canView() {
      return Session::haveRight(self::$rightname, READ);
   }

   /**
    * Have I the global right to "create" the Object
    * May be overloaded if needed (ex KnowbaseItem)
    *
    * @return booleen
    **/
   static function canCreate() {
      return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);
   }

   /**
    * Clean object veryfing criteria (when a relation is deleted)
    *
    * @param $crit array of criteria (should be an index)
    */
   public function clean($crit) {
      global $DB;

      foreach ($DB->request($this->getTable(), $crit) as $data) {
         $this->delete($data);
      }
   }

   /**
    * Get Tab Name used for itemtype
    *
    * NB : Only called for existing object
    *      Must check right on what will be displayed + template
    *
    * @param CommonGLPI $item Item on which the tab need to be displayed
    * @param boolean    $withtemplate is a template object ? (default 0)
    *
    * @return string tab name
    **@since 0.83
    *
    */
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if (!$withtemplate) {
         if ($item->getID() && $this->canView()) {

            if ($item->getType() == Ticket::getType()) {
               $items = new Item_Ticket();
               if ($items->getFromDBByCrit(["tickets_id" => $item->getID(),
                                            "itemtype" => PluginResourcesResource::getType()])) {
                  $adConfig = new PluginResourcesAdconfig();
                  $adConfig->getFromDB(1);
                  $adConfig->fields = $adConfig->prepareFields($adConfig->fields);
                  if ((is_array($adConfig->fields["creation_categories_id"])
                       && in_array($item->getField('itilcategories_id'), $adConfig->getField("creation_categories_id")))
                      || (is_array($adConfig->fields["modification_categories_id"])
                          && in_array($item->getField('itilcategories_id'), $adConfig->getField("modification_categories_id")))
                      || (is_array($adConfig->fields["deletion_categories_id"])
                          && in_array($item->getField('itilcategories_id'), $adConfig->getField("deletion_categories_id")))) {
                     if ($_SESSION['glpishow_count_on_tabs']) {
                        return self::createTabEntry(self::getTypeName(2), self::countForItem($item));
                     }
                     return self::getTypeName(2);
                  }

               }
            }

         }
      }
      return '';
   }

   /**
    * show Tab content
    *
    * @param CommonGLPI $item Item on which the tab need to be displayed
    * @param integer    $tabnum tab number (default 1)
    * @param boolean    $withtemplate is a template object ? (default 0)
    *
    * @return boolean
    **@since 0.83
    *
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      $ID = $item->getField('id');

      if ($item->getType() == Ticket::getType()) {
         $items = new Item_Ticket();
         if ($items->getFromDBByCrit(["tickets_id" => $ID, "itemtype" => PluginResourcesResource::getType()])) {
            self::showFromResources($items->getField("items_id"), $item);
         }


         return true;
      }
      return false;
   }

   /**
    * @param $item
    *
    * @return int
    */
   static function countForItem($item) {

      if ($item->getField('is_leaving') == 1) {
         $checklist_type = self::RESOURCES_CHECKLIST_OUT;
      } else {
         $checklist_type = self::RESOURCES_CHECKLIST_IN;
      }
      $dbu      = new DbUtils();
      $restrict = ["plugin_resources_resources_id" => $item->getField('id'),
                   "checklist_type"                => $checklist_type,
                   "NOT"                           => ["is_checked" => 1]];
      $nb       = $dbu->countElementsInTable(['glpi_plugin_resources_checklists'], $restrict);

      return $nb;
   }


   /**
    * Prepare input datas for adding the item
    *
    * @param array $input datas used to add the item
    *
    * @return array the modified $input array
    **/
   function prepareInputForAdd($input) {
      global $DB;


      return $input;
   }

   /**
    * @param $ID
    */
   static function showAddForm($ID) {

      echo "<div align='center'>";
      echo "<form action='" . Toolbox::getItemTypeFormURL('PluginResourcesResource') . "' method='post'>";
      echo "<table class='tab_cadre' width='50%'>";
      echo "<tr>";
      echo "<th colspan='2'>";
      echo __('Create checklists', 'resources');
      echo "</th></tr>";
      echo "<tr class='tab_bg_2 center'>";
      echo "<td colspan='2'>";
      echo Html::submit(_sx('button', 'Post'), ['name' => 'add_checklist_resources', 'class' => 'btn btn-primary']);
      echo Html::hidden('id', ['value' => $ID]);
      echo "</td></tr></table>";
      Html::closeForm();
      echo "</div>";
   }


   /**
    * @param       $ID
    * @param array $options
    *
    * @return bool
    */
   function showForm($ID, $options = []) {

      if (!$this->canView()) {
         return false;
      }

      $plugin_resources_contracttypes_id = -1;
      if (isset($options['plugin_resources_contracttypes_id'])) {
         $plugin_resources_contracttypes_id = $options['plugin_resources_contracttypes_id'];
      }

      $checklist_type = -1;
      if (isset($options['checklist_type'])) {
         $checklist_type = $options['checklist_type'];
      }

      $plugin_resources_resources_id = -1;

      if (isset($options['plugin_resources_resources_id'])) {
         $plugin_resources_resources_id = $options['plugin_resources_resources_id'];
         $item                          = new PluginResourcesResource();
         if ($item->getFromDB($plugin_resources_resources_id)) {
            $options["entities_id"] = $item->fields["entities_id"];
         }
      }

      if ($ID > 0) {
         $this->check($ID, READ);
      } else {
         // Create item
         $this->check(-1, UPDATE, $input);
      }

      $this->showFormHeader($options);

      echo Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
      if ($ID > 0) {
         echo Html::hidden('plugin_resources_contracttypes_id', ['value' => $this->fields["plugin_resources_contracttypes_id"]]);
         echo Html::hidden('checklist_type', ['value' => $this->fields["checklist_type"]]);
      } else {
         echo Html::hidden('plugin_resources_contracttypes_id', ['value' => $plugin_resources_contracttypes_id]);
         echo Html::hidden('checklist_type', ['value' => $checklist_type]);
      }

      echo "<tr class='tab_bg_1'>";

      echo "<td >" . __('Name') . "</td>";
      echo "<td>";
      echo Html::input('name', ['value' => $this->fields['name'], 'size' => 40]);
      echo "</td>";

      echo "<td>";
      echo __('Important', 'resources');
      echo "</td><td>";
      Dropdown::showYesNo("tag", $this->fields["tag"]);
      echo "</td>";

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";

      echo "<td >" . __('Link', 'resources') . "</td>";
      echo "<td>";
      echo Html::input('address', ['value' => $this->fields['address'], 'size' => 75]);
      echo "</td>";

      echo "<td></td>";
      echo "<td></td>";

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";

      echo "<td class='left' colspan = '4'>";
      echo __('Description') . "<br>";
      echo Html::textarea([
                             'name'    => 'comment',
                             'value' => $this->fields["comment"],
                             'cols'    => '150',
                             'rows'    => '6',
                             'display' => false,
                          ]);
      echo "</td>";

      echo "</tr>";

      $this->showFormButtons($options);
      return true;
   }

   /**
    * show from resources
    *
    * @param        $plugin_resources_resources_id
    * @param        $checklist_type
    * @param string $withtemplate
    *
    * @return bool
    */
   static function showFromResources($plugin_resources_resources_id, $ticket) {
      global $CFG_GLPI;

      if (!self::canView()) {
         return false;
      }

      $target          = "./resource.form.php";
      $targetchecklist = "./checklist.form.php";
      $targettask      = "./task.form.php";
      $config          = new PluginResourcesConfig();
      $configAD        = new PluginResourcesAdconfig();
      $config->getFromDB(1);
      $configAD->getFromDB(1);
      $configAD->fields = $configAD->prepareFields($configAD->fields);
      $resource         = new PluginResourcesResource();
      $resource->getFromDB($plugin_resources_resources_id);
      $canedit                           = $resource->can($plugin_resources_resources_id, UPDATE);
      $entities_id                       = $resource->fields["entities_id"];
      $plugin_resources_contracttypes_id = $resource->fields["plugin_resources_contracttypes_id"];
      $rand                              = mt_rand();
      $enddate                           = $resource->getField("date_end");
      $linkAD                            = new self();
      $linkAD->getEmpty();
      $islink = $linkAD->getFromDBByCrit(["plugin_resources_resources_id" => $resource->getID()]);
      if (!$islink) {
         $ret                     = self::processLogin($resource);
         $linkAD->fields["login"] = $ret[0];
         $logAvailable            = $ret[1];

         $mail                       = self::processMail($resource, $linkAD->fields["login"]);
         $linkAD->fields["mail"]     = $mail;
         $role                       = Dropdown::getDropdownName(PluginResourcesRole::getTable(), $resource->fields['plugin_resources_roles_id']);
         $linkAD->fields["role"]     = $role;
         $service                    = Dropdown::getDropdownName(PluginResourcesService::getTable(), $resource->fields['plugin_resources_services_id']);
         $linkAD->fields["service"]  = $service;
         $location                   = Dropdown::getDropdownName(Location::getTable(), $resource->fields['locations_id']);
         $linkAD->fields["location"] = $location;
      }
      $ID = $linkAD->getID();
      echo "<form name='form' method='post' action='" . Toolbox::getItemTypeFormURL(self::getType()) . "'>";
      echo "<table class='tab_cadre_fixe'>";


      $dbu = new DbUtils();

      if (($islink) || !$islink) {
         echo Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
         echo Html::hidden('ticket_id', ['value' => $ticket->getID()]);
         echo Html::hidden('plugin_resources_contracttypes_id', ['value' => $plugin_resources_contracttypes_id]);
         echo Html::hidden('entities_id', ['value' => $entities_id]);
         echo Html::hidden('enddate', ['value' => $enddate]);
         echo Html::hidden('id', ['value' => $ID]);
         // Actions on finished checklist
         if (self::canCreate() && $canedit) {
            echo "<tr><th colspan='4'>" . __('Resources data', 'resources') . "</th></tr>";
            echo "<tr>";
            echo "<td colspan = ''>" . __('Login') . "</td>";
            echo "<td>";
            $option = ['value' => $linkAD->fields["login"], "option" => "disabled"];
            if (!$islink) {
               $option = ['value' => $linkAD->fields["login"]];
            }
            echo Html::input('name', $option);
            echo "</td>";
            echo "<td colspan = ''>" . __('Department', 'resources') . "</td>";

            echo "<td>";
            echo Html::hidden('department', ['value' => Dropdown::getDropdownName('glpi_plugin_resources_departments', $resource->getField("plugin_resources_departments_id"))]);
            echo Dropdown::getDropdownName('glpi_plugin_resources_departments', $resource->getField("plugin_resources_departments_id"));
            echo "</td>";
            echo "</tr>";
            echo "<tr class='tab_bg_1'>";
            echo "<td>" . __('Name') . "</td>";
            echo "<td>";
            $option = ['rand'   => $rand,
                       'value'  => $resource->getField("name"),
                       'onChange' => "\"javascript:this.value=this.value.toUpperCase();\" "];
            $rand1  = Html::input('name', $option);
            echo "</td>";
            echo "<td>" . __('Firstname', 'resources') . "</td>";
            echo "<td>";
            $option = ['rand'   => $rand,
                       'value'  => $resource->getField("firstname"),
                       'onchange' => "First2UpperCase(this.value); plugin_resources_load_button_changeresources_information();' style='text-transform:capitalize;'"];
            $rand2  = Html::input('firstname', $option);
            echo "</td>";

            echo "</tr>";
            echo "<tr class='tab_bg_1'>";
            echo "<td>" . __('Phone', 'resources') . "</td>";

            echo "<td>";
            echo Html::input('phone', ['value' => $linkAD->fields["phone"]]);
            echo "</td>";

            echo "<td>" . __('Mail') . "</td>";

            echo "<td>";
            echo Html::input('mail', ['type'=> 'email', 'value' => $linkAD->fields["mail"]]);
            echo "</td>";
            echo "</tr>";
            echo "<tr class='tab_bg_1'>";
            echo "<td>" . __('Company', 'resources') . "</td>";
            echo "<td>";
            $employee = new PluginResourcesEmployee();
            $employee->getFromDBByCrit(["plugin_resources_resources_id" => $resource->getID()]);

            echo Html::hidden('company', ['value' => Dropdown::getDropdownName('glpi_plugin_resources_employers', $employee->getField("plugin_resources_employers_id"))]);

            echo Dropdown::getDropdownName('glpi_plugin_resources_employers', $employee->getField("plugin_resources_employers_id"));
            echo "</td>";
            echo "<td>" . _n('Contract type', 'Contract types', 1) . "</td>";
            echo "<td>";
            $employee = new PluginResourcesEmployee();
            $employee->getFromDBByCrit(["plugin_resources_resources_id" => $resource->getID()]);

            echo Html::hidden('contract', ['value' => Dropdown::getDropdownName('glpi_plugin_resources_contracttypes', $resource->getField("plugin_resources_contracttypes_id"))]);

            echo Dropdown::getDropdownName('glpi_plugin_resources_contracttypes', $resource->getField("plugin_resources_contracttypes_id"));
            echo "</td>";

            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>" . __('Cell phone', 'resources') . "</td>";

            echo "<td>";
            echo Html::input('cellphone', ['value' => $linkAD->fields["cellphone"]]);
            echo "</td>";

            echo "<td>" . __('Role', 'resources') . "</td>";

            echo "<td>";
            echo Html::input('role', ['value' => $linkAD->fields["role"]]);
            echo "</td>";

            echo "</tr>";
            echo "<tr class='tab_bg_1'>";
            echo "<td>" . PluginResourcesService::getTypeName(1) . "</td>";

            echo "<td>";
            echo Html::input('service', ['value' => $linkAD->fields["service"]]);
            echo "</td>";

            echo "<td>" . Location::getTypeName(1) . "</td>";

            echo "<td>";
            echo Html::input('location', ['value' => $linkAD->fields["location"]]);
            echo "</td>";

            echo "</tr>";


            if (!$islink && !$linkAD->fields["action_done"] && in_array($ticket->fields["itilcategories_id"], $configAD->fields["creation_categories_id"]) && $logAvailable) {
               echo "<tr class='tab_bg_2'>";
               echo "<td colspan='4' class='center'>";
               echo Html::submit(_sx('button', 'Create user in AD', 'resources'), ['name' => 'createAD', 'class' => 'btn btn-primary']);
               echo "</td>";
            }
            if ($islink && !$linkAD->fields["action_done"] && in_array($ticket->fields["itilcategories_id"], $configAD->fields["modification_categories_id"])) {
               echo "<tr class='tab_bg_2'>";
               echo "<td colspan='4' class='center'>";
               echo Html::submit(_sx('button', 'Modify user in AD', 'resources'), ['name' => 'updateAD', 'class' => 'btn btn-primary']);
               echo "</td>";
            }

            if ($islink && !$linkAD->fields["action_done"] && in_array($ticket->fields["itilcategories_id"], $configAD->fields["deletion_categories_id"])) {
               echo "<tr class='tab_bg_2'>";
               echo "<td colspan='4' class='center'>";
               echo Html::submit(_sx('button', 'Disable user in AD', 'resources'), ['name' => 'disableAD', 'class' => 'btn btn-primary']);
               echo "</td>";
            }
            echo "</tr>";
         }
      } else {

      }

      echo "</table>";
      Html::closeForm();
      echo "<br>";
   }


   static function processLogin(PluginResourcesResource $resource) {
      $config = new PluginResourcesAdconfig();
      $config->getFromDB(1);
      $login = self::getLoginFromRule($resource->fields["firstname"], $resource->fields["name"], $config->fields["first_form"]);
      $ldap  = new PluginResourcesLDAP();
      $exist = $ldap->existingUser($login);
      if ($exist) {
         $login = self::getLoginFromRule($resource->fields["firstname"], $resource->fields["name"], $config->fields["second_form"]);
         $exist = $ldap->existingUser($login);
         if ($exist) {
            return [__("existing login", "resources"), false];
         } else {
            return [$login, true];
         }
      } else {
         return [$login, true];
      }

   }

   static function processMail(PluginResourcesResource $resource, $login) {
      $config = new PluginResourcesAdconfig();
      $config->getFromDB(1);
      $mail = "";
      if ($config->fields["mail_prefix"] == 2) {
         $mail = $login;
      } else if ($config->fields["mail_prefix"] == 1) {
         $nametab = explode(" ", strtolower($resource->fields["name"]));
         $name    = "";

         foreach ($nametab as $namepart) {
            $name .= $namepart;
         }

         $firstnametab = explode(" ", strtolower($resource->fields["firstname"]));
         $firstname    = "";

         foreach ($firstnametab as $namepart) {
            $firstname .= $namepart;
         }

         $prefix = $firstname . "." . $name;
         $mail   = $prefix;
      }
      $mail .= "@" . $config->fields["mail_suffix"];
      return $mail;

   }

   static function getLoginFromRule($firstname, $name, $conf) {
      switch ($conf) {
         case 1:
            //            $name = strtolower($name);
            $nametab = explode(" ", strtolower($name));
            $name    = "";

            foreach ($nametab as $namepart) {
               $name .= $namepart;
            }
            $firstnametab = explode(" ", strtolower($firstname));
            $firstname    = "";

            foreach ($firstnametab as $namepart) {
               $firstname .= substr($namepart, 0, 1);
            }

            $login = $firstname . $name;
            break;
         case 2:
            //            $name = strtolower($name);
            $nametab = explode(" ", strtolower($name));
            $name    = "";

            foreach ($nametab as $namepart) {
               $name .= $namepart;
            }
            $firstnametab = explode(" ", strtolower($firstname));
            $firstname    = "";

            foreach ($firstnametab as $namepart) {
               $firstname .= $namepart;
            }
            $login = $firstname . $name;
            break;
         case 3:
            $name      = substr($name, 0, 2);
            $firstname = substr($firstname, 0, 2);
            $login     = $firstname . $name;
            break;
         default:
            $login = "";
      }
      return $login;
   }

   static function getMapping($val) {
      $mapping["logAD"]   = "login";
      $mapping["nameAD"]  = "name";
      $mapping["phoneAD"] = "phone";

      $mapping["firstnameAD"] = "firstname";
      $mapping["mailAD"]      = "mail";

      $mapping["cellPhoneAD"]    = "cellphone";
      $mapping["roleAD"]         = "role";
      $mapping["serviceAD"]      = "service";
      $mapping["locationAD"]     = "location";
      $mapping["companyAD"]      = "company";
      $mapping["departmentAD"]   = "department";
      $mapping["contractTypeAD"] = "contract";
      $mapping["contractEndAD"]  = "enddate";

      if (isset($mapping[$val])) {
         return $mapping[$val];
      }
      return null;
   }

   static function getNameMapping($val) {
      $mapping["login"]     = __('Login');
      $mapping["firstname"] = __('Firstname', 'resources');
      $mapping["phone"]     = Phone::getTypeName(1);

      $mapping["name"] = __('Name');
      $mapping["mail"] = __('Mail');

      $mapping["cellphone"]  = __('Mobile phone');
      $mapping["role"]       = __('Role', 'resources');
      $mapping["role"]       = PluginResourcesService::getTypeName(1);
      $mapping["contract"]   = __("Contract type");
      $mapping["company"]    = __('Company', 'resources');
      $mapping["department"] = __('Department', 'resources');

      $mapping["enddate"] = __('Departure date', 'resources');

      if (isset($mapping[$val])) {
         return $mapping[$val];
      }
      return null;
   }

   /**
    * Displaying message solution
    *
    * @param $params
    *
    * @return bool
    */
   static function messageSolution($params) {

      if (isset($params['item'])) {
         $item = $params['item'];
         if ($item->getType() == 'ITILSolution') {

            self::showMessage($params);
         }

      }
   }

   /**
    * Displaying questions in GLPI's ticket satisfaction
    *
    * @param $params
    *
    * @return bool
    */
   static function deleteButtton($params) {

      if (isset($params['item'])) {
         $item = $params['item'];
         if ($item->getType() == 'ITILSolution') {
            if (self::cancelButtonSolution($params)) {
               $params['options']['canedit'] = false;
               return $params;
            }

         }

      }
   }

   /**
    * show warning message
    *
    * @param $params
    *
    * @return bool
    */
   static function showMessage($params) {

      if (isset($params['options'])) {
         $options = $params['options'];
         $ticket  = new Ticket();
         if ($ticket->getFromDB($options['item']->fields["id"])) {
            $adconfig = new PluginResourcesAdconfig();
            $adconfig->getFromDB(1);
            $adconfig->fields = $adconfig->prepareFields($adconfig->fields);
            $linkad           = new PluginResourcesLinkAd();
            $items            = new Item_Ticket();
            $conf             = new PluginResourcesConfig();
            $conf->getFromDB(1);
            if (is_array($adconfig->fields["creation_categories_id"]) && in_array($ticket->fields["itilcategories_id"], $adconfig->fields["creation_categories_id"])) {
               if ($items->getFromDBByCrit(["tickets_id" => $ticket->getID(), "itemtype" => PluginResourcesResource::getType()])) {
                  if ($conf->fields["mandatory_adcreation"] == 1) {
                     if (!$linkad->getFromDBByCrit(['plugin_resources_resources_id' => $items->getField('items_id')]) || ($linkad->getFromDBByCrit(['plugin_resources_resources_id' => $items->getField('items_id')]) && $linkad->getField('action_done') == 0)) {
                        $ldapaction = true;

                     }
                  }
                  if ($conf->fields["mandatory_checklist"] == 1) {
                     $checklist  = new PluginResourcesChecklist();
                     $checklists = $checklist->find(["plugin_resources_resources_id" => $items->getField('items_id'), "is_checked" => 0, "checklist_type" => PluginResourcesChecklist::RESOURCES_CHECKLIST_IN]);
                     if (!empty($checklists)) {
                        $checklistaction = true;

                     }
                  }
               }
            } else if (is_array($adconfig->fields["deletion_categories_id"]) && in_array($ticket->fields["itilcategories_id"], $adconfig->fields["deletion_categories_id"])) {
               if ($items->getFromDBByCrit(["tickets_id" => $ticket->getID(), "itemtype" => PluginResourcesResource::getType()])) {
                  if ($conf->fields["mandatory_adcreation"] == 1) {
                     if (!$linkad->getFromDBByCrit(['plugin_resources_resources_id' => $items->getField('items_id')]) || ($linkad->getFromDBByCrit(['plugin_resources_resources_id' => $items->getField('items_id')]) && $linkad->getField('action_done') == 0)) {
                        $ldapaction = true;

                     }
                  }
                  if ($conf->fields["mandatory_checklist"] == 1) {
                     $checklist  = new PluginResourcesChecklist();
                     $checklists = $checklist->find(["plugin_resources_resources_id" => $items->getField('items_id'), "is_checked" => 0, "checklist_type" => PluginResourcesChecklist::RESOURCES_CHECKLIST_OUT]);
                     if (!empty($checklists)) {
                        $checklistaction = true;

                     }
                  }
               }
            }
            $text = "";
            if (isset($ldapaction) && isset($checklistaction)) {
               $text = __('You have to perform the action on the LDAP directory before and you have to do all checklist in action before', 'resources');
            } else if (isset($ldapaction)) {
               $text = __('You have to perform the action on the LDAP directory before', 'resources');
            } else if (isset($checklistaction)) {
               $text = __('You have to do all checklist in action before', 'resources');
            }
            if (!empty($text)) {
               echo "<tr class='tab_bg_1 warning'><td colspan='4'>$text</td></tr>";
            }
         }
      }
   }

   static function cancelButtonSolution($params) {
      if (isset($params['options'])) {
         $options = $params['options'];
         $ticket  = new Ticket();
         if ($ticket->getFromDB($options['item']->fields["id"])) {
            $adconfig = new PluginResourcesAdconfig();
            $adconfig->getFromDB(1);
            $adconfig->fields = $adconfig->prepareFields($adconfig->fields);
            $linkad           = new PluginResourcesLinkAd();
            $items            = new Item_Ticket();
            $conf             = new PluginResourcesConfig();
            $conf->getFromDB(1);
            if (is_array($adconfig->fields["creation_categories_id"]) && in_array($ticket->fields["itilcategories_id"], $adconfig->fields["creation_categories_id"])) {
               if ($items->getFromDBByCrit(["tickets_id" => $ticket->getID(), "itemtype" => PluginResourcesResource::getType()])) {
                  if ($conf->fields["mandatory_adcreation"] == 1) {
                     if (!$linkad->getFromDBByCrit(['plugin_resources_resources_id' => $items->getField('items_id')]) || ($linkad->getFromDBByCrit(['plugin_resources_resources_id' => $items->getField('items_id')]) && $linkad->getField('action_done') == 0)) {
                        return true;

                     }
                  }
                  if ($conf->fields["mandatory_checklist"] == 1) {
                     $checklist  = new PluginResourcesChecklist();
                     $checklists = $checklist->find(["plugin_resources_resources_id" => $items->getField('items_id'), "is_checked" => 0, "checklist_type" => PluginResourcesChecklist::RESOURCES_CHECKLIST_IN]);
                     if (!empty($checklists)) {
                        return true;

                     }
                  }
               }
               //            } else if (in_array($ticket->fields["itilcategories_id"] , $adconfig->fields["modification_categories_id"])) {
               //               if ($items->getFromDBByCrit(["tickets_id" => $ticket->getID(), "itemtype" => PluginResourcesResource::getType()])) {
               //                  if (!$linkad->getFromDBByCrit(['plugin_resources_resources_id' => $items->getField('items_id')]) || ($linkad->getFromDBByCrit(['plugin_resources_resources_id' => $items->getField('items_id')]) && $linkad->getField('action_done') == 0)) {
               //                     return true;
               //
               //                  }
               //
               //               }
            } else if (is_array($adconfig->fields["deletion_categories_id"]) && in_array($ticket->fields["itilcategories_id"], $adconfig->fields["deletion_categories_id"])) {
               if ($items->getFromDBByCrit(["tickets_id" => $ticket->getID(), "itemtype" => PluginResourcesResource::getType()])) {
                  if ($conf->fields["mandatory_adcreation"] == 1) {
                     if (!$linkad->getFromDBByCrit(['plugin_resources_resources_id' => $items->getField('items_id')]) || ($linkad->getFromDBByCrit(['plugin_resources_resources_id' => $items->getField('items_id')]) && $linkad->getField('action_done') == 0)) {
                        return true;

                     }
                  }
                  if ($conf->fields["mandatory_checklist"] == 1) {
                     $checklist  = new PluginResourcesChecklist();
                     $checklists = $checklist->find(["plugin_resources_resources_id" => $items->getField('items_id'), "is_checked" => 0, "checklist_type" => PluginResourcesChecklist::RESOURCES_CHECKLIST_OUT]);
                     if (!empty($checklists)) {
                        return true;

                     }
                  }
               }
            }
            return false;
         }
      }
   }

}

