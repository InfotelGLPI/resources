<?php
/*
 *
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
 * Class PluginResourcesConfig
 */
class PluginResourcesConfig extends CommonDBTM {

   static $rightname = 'plugin_resources';

   /**
    * functions mandatory
    * getTypeName(), canCreate(), canView()
    * */
   static function getTypeName($nb = 0) {
      return __('Setup');
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
      return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]);
   }

   /**
    * PluginResourcesConfig constructor.
    */
   function __construct() {
      global $DB;

      if ($DB->tableExists($this->getTable())) {
         $this->getFromDB(1);
      }
   }

   /**
    * @return bool
    */
   function showConfigForm() {

      if (!$this->canView()) {
         return false;
      }
      if (!$this->canCreate()) {
         return false;
      }

      $canedit = true;

      if ($canedit) {
         $this->getFromDB(1);
         echo "<form name='form' method='post' action='" . $this->getFormURL() . "'>";

         echo "<div align='center'><table class='tab_cadre_fixe'>";
         echo "<tr><th colspan='2'>".self::getTypeName()."</th></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Displaying the security block on the resource', 'resources');
         echo "</td>";
         echo "<td>";
         Dropdown::showYesNo('security_display', $this->fields['security_display']);
         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Security compliance management', 'resources');
         echo "<br><span class='red'>".sprintf(__('%1$s <br> %2$s'), __('Display of four additional security fields in the clients', 'resources'),
               __('(If all four fields are enabled, the client is compliant with security)', 'resources'))."</span>";
         echo "</td>";
         echo "<td>";
         Dropdown::showYesNo('security_compliance', $this->fields['security_compliance']);
         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Import external', 'resources');
         echo "</td>";
         echo "<td>";
         Dropdown::showYesNo('import_external_datas', $this->fields['import_external_datas']);
         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Resource managers selection : only these users with these profiles', 'resources');
         echo "</td>";
         echo "<td>";
         echo Html::hidden("resource_manager");
         $possible_values = [];
         $profileITIL = new Profile();
         $profiles = $profileITIL->find([]);
         if (!empty($profiles)) {
            foreach ($profiles as $profile) {
               $possible_values[$profile['id']] = $profile['name'];
            }
         }
         $values = json_decode($this->fields['resource_manager']);
         if (!is_array($values)) {
            $values = [];
         }
         Dropdown::showFromArray("resource_manager",
            $possible_values,
            ['values'   => $values,
               'multiple' => 'multiples']);

         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Sales managers selection : only these users with these profiles', 'resources');
         echo "</td>";
         echo "<td>";
         echo Html::hidden("sales_manager");


         $values = json_decode($this->fields['sales_manager']);
         if (!is_array($values)) {
            $values = [];
         }
         Dropdown::showFromArray("sales_manager",
            $possible_values,
            ['values'   => $values,
               'multiple' => 'multiples']);

         echo "</td>";
         echo "</tr>";
         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Create a ticket with departure', 'resources');
         echo "</td>";
         echo "<td>";

         Dropdown::showYesNo("create_ticket_departure",$this->fields["create_ticket_departure"]);

         echo "</td>";
         echo "</tr>";
         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Category of departure ticket', 'resources');
         echo "</td>";
         echo "<td>";

         ITILCategory::dropdown(["name"=>"categories_id","value"=>$this->fields["categories_id"]]);

         echo "</td>";
         echo "</tr>";
         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('All checklist done is mandatory for arrival and departure to close ticket', 'resources');
         echo "</td>";
         echo "<td>";

         Dropdown::showYesNo("mandatory_checklist",$this->fields["mandatory_checklist"]);

         echo "</td>";
         echo "</tr>";
         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Create or delete user in ldap is mandatory to close ticket', 'resources');
         echo "</td>";
         echo "<td>";

         Dropdown::showYesNo("mandatory_adcreation",$this->fields["mandatory_adcreation"]);

         echo "</td>";
         echo "</tr>";
         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __("If other contract are available don't display without contract", 'resources');
         echo "</td>";
         echo "<td>";

         Dropdown::showYesNo("allow_without_contract",$this->fields["allow_without_contract"]);

         echo "</td>";
         echo "</tr>";
         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Default contract template selected', 'resources');
         echo "</td>";
         echo "<td>";
         $resource = new PluginResourcesResource();
         $resource->dropdownTemplate("plugin_resources_resourcetemplates_id",
                                     $this->fields["plugin_resources_resourcetemplates_id"], false);
//         Dropdown::showYesNo("plugin_resources_resourcestemplates_id",$this->fields["plugin_resources_resourcestemplates_id"]);

         echo "</td>";
         echo "</tr>";
         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Resource state for working people', 'resources');
         echo "</td>";
         echo "<td>";
         $resource = new PluginResourcesResource();
         PluginResourcesResourceState::dropdown(['name'=>'plugin_resources_resourcestates_id_arrival','value'=>$this->fields['plugin_resources_resourcestates_id_arrival']]);
         //         Dropdown::showYesNo("plugin_resources_resourcestemplates_id",$this->fields["plugin_resources_resourcestemplates_id"]);

         echo "</td>";
         echo "</tr>";
         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Resource state for left people', 'resources');
         echo "</td>";
         echo "<td>";
         PluginResourcesResourceState::dropdown(['name'=>'plugin_resources_resourcestates_id_departure','value'=>$this->fields['plugin_resources_resourcestates_id_departure']]);
         //         Dropdown::showYesNo("plugin_resources_resourcestemplates_id",$this->fields["plugin_resources_resourcestemplates_id"]);

         echo "</td>";
         echo "</tr>";
         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Change checklists for resources during a contract change', 'resources');
         echo "</td>";
         echo "<td>";
        Dropdown::showYesNo('reaffect_checklist_change',$this->fields['reaffect_checklist_change']);

         echo "</td>";
         echo "</tr>";

         if (Plugin::isPluginActive('metademands')) {
            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Use metademand for resources changes', 'resources');
            echo "</td>";
            echo "<td>";

            $meta                   = new PluginMetademandsMetademand();
            $options['empty_value'] = true;
            $data                   = $meta->listMetademands(false, $options, true);
            echo Dropdown::showFromArray('use_meta_for_changes', $data, ['width' => 250, 'display' => false, 'value' => $this->fields['use_meta_for_changes']]);

            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Use metademand for leaving resources', 'resources');
            echo "</td>";
            echo "<td>";

            $meta                   = new PluginMetademandsMetademand();
            $options['empty_value'] = true;
            $data                   = $meta->listMetademands(false, $options);
            echo Dropdown::showFromArray('use_meta_for_leave', $data, ['width' => 250, 'display' => false, 'value' => $this->fields['use_meta_for_leave']]);

            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Remove habilitation when update resource', 'resources');
            echo "</td>";
            echo "<td>";
            Dropdown::showYesNo("remove_habilitation_on_update",$this->fields["remove_habilitation_on_update"]);
            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Display habilitation resource with dropdown', 'resources');
            echo "</td>";
            echo "<td>";
            Dropdown::showYesNo("display_habilitations_txt",$this->fields["display_habilitations_txt"]);
            echo "</td>";
            echo "</tr>";
         }

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Use service and departement from AD', 'resources');
         echo "</td>";
         echo "<td>";
        Dropdown::showYesNo('use_service_department_ad',$this->fields['use_service_department_ad']);
         echo "</td>";
         echo "</tr>";

         if($this->useServiceDepartmentAD()){
            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Use secondaries services', 'resources');
            echo "</td>";
            echo "<td>";
            Dropdown::showYesNo('use_secondary_service',$this->fields['use_secondary_service']);
            echo "</td>";
            echo "</tr>";
         }

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Hide/Show elements', 'resources'). " : " . __('View my resources as a commercial', 'resources');
         echo "</td>";
         echo "<td>";
         Dropdown::showYesNo('hide_view_commercial_resource',$this->fields['hide_view_commercial_resource']);
         echo "</td>";
         echo "</tr>";

         echo "<tr>";
         echo "<td class='tab_bg_2 center' colspan='2'>";
         echo Html::hidden('id', ['value' => 1]);
         echo Html::submit(_sx('button', 'Update'), ['name' => 'update_setup', 'class' => 'btn btn-primary']);
         echo "</td>";
         echo "</tr>";
         echo "</table></div>";
         Html::closeForm();
      }

   }

   /**
    * @return mixed
    */
   function useSecurity() {
      return $this->fields['security_display'];
   }

   /**
    * @return mixed
    */
   function useSecurityCompliance() {
      return $this->fields['security_compliance'];
   }

   /**
    * @return mixed
    */
   function useImportExternalDatas() {
      return $this->fields['import_external_datas'];
   }

   /**
    * @param $input
    *
    * @return array|\type
    */
   function prepareInputForAdd($input) {
      return $this->encodeSubtypes($input);
   }

   /**
    * @param $input
    *
    * @return array|\type
    */
   function prepareInputForUpdate($input) {
      return $this->encodeSubtypes($input);
   }

   /**
    * Encode sub types
    *
    * @param type $input
    *
    * @return \type
    */
   function encodeSubtypes($input) {
      if (!empty($input['resource_manager'])) {
         $input['resource_manager'] = json_encode(array_values($input['resource_manager']));
      }
      if (!empty($input['sales_manager'])) {
         $input['sales_manager'] = json_encode(array_values($input['sales_manager']));
      }

      return $input;
   }

   /**
    * @return mixed
    */
   function useServiceDepartmentAD() {
      return $this->fields['use_service_department_ad'];
   }

   /**
    * @return mixed
    */
   function useSecondaryService() {
      return $this->fields['use_secondary_service'];
   }


}
