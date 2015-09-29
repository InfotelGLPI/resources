<?php
/*
 * @version $Id: resource.class.php 480 2012-11-09 tynet $
 -------------------------------------------------------------------------
 Resources plugin for GLPI
 Copyright (C) 2006-2012 by the Resources Development Team.

 https://forge.indepnet.net/projects/resources
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Resources.

 Resources is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Resources is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Resources. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginResourcesResource extends CommonDBTM {

   static $rightname = 'plugin_resources';
   
   static $types = array('Computer','Monitor','NetworkEquipment','Peripheral',
         'Phone', 'Printer', 'Software', 'ConsumableItem','User');
   
   protected $usenotepad = true;

   public $dohistory=true;

   static function getTypeName($nb = 0) {

      return _n('Human resource', 'Human resources', $nb, 'resources');
   }

   static function canView() {
      return Session::haveRight(self::$rightname, READ);
   }

   static function canCreate() {
      return Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, DELETE));
   }

   /**
    * For other plugins, add a type to the linkable types
    *
    *
    * @param $type string class name
   **/
   static function registerType($type) {
      if (!in_array($type, self::$types)) {
         self::$types[] = $type;
      }
   }

   /**
    * Type than could be linked to a Resource
    *
    * @param $all boolean, all type, or only allowed ones
    *
    * @return array of types
   **/
   static function getTypes($all=false) {

      if ($all) {
         return self::$types;
      }

      // Only allowed types
      $types = self::$types;

      foreach ($types as $key => $type) {
         if (!class_exists($type)) {
            continue;
         }

         $item = new $type();
         if (!$item->canView()) {
            unset($types[$key]);
         }
      }
      return $types;
   }

   function cleanDBonPurge() {

      $temp = new PluginResourcesResource_Item();
      $temp->deleteByCriteria(array('plugin_resources_resources_id' => $this->fields['id']));

      $temp = new PluginResourcesChoice();
      $temp->deleteByCriteria(array('plugin_resources_resources_id' => $this->fields['id']));

      $temp = new PluginResourcesTask();
      $temp->deleteByCriteria(array('plugin_resources_resources_id' => $this->fields['id']),1);

      $temp = new PluginResourcesEmployee();
      $temp->deleteByCriteria(array('plugin_resources_resources_id' => $this->fields['id']));

      $temp = new PluginResourcesReportConfig();
      $temp->deleteByCriteria(array('plugin_resources_resources_id' => $this->fields['id']));

      $temp = new PluginResourcesChecklist();
      $temp->deleteByCriteria(array('plugin_resources_resources_id' => $this->fields['id']));

      $temp = new PluginResourcesResourceResting();
      $temp->deleteByCriteria(array('plugin_resources_resources_id' => $this->fields['id']));

      $temp = new PluginResourcesResourceHoliday();
      $temp->deleteByCriteria(array('plugin_resources_resources_id' => $this->fields['id']));
   }

   /**
    * Hook called After an item is purge
    */
   static function cleanForItem(CommonDBTM $item) {

      $type = get_class($item);
      $temp = new PluginResourcesResource_Item();
      $temp->deleteByCriteria(array('itemtype' => $type,
                                       'items_id' => $item->getField('id')));

      $task = new PluginResourcesTask_Item();
      $task->deleteByCriteria(array('itemtype' => $type,
                                       'items_id' => $item->getField('id')));
   }

   function getSearchOptions() {

      $tab = array();

      $tab['common']             = self::getTypeName(2);
      
      $tab[1]['table']           = $this->getTable();
      $tab[1]['field']           = 'name';
      $tab[1]['name']            = __('Surname');
      $tab[1]['datatype']        = 'itemlink';
      $tab[1]['itemlink_type']   = $this->getType();
      if ($_SESSION['glpiactiveprofile']['interface'] != 'central') {
         $tab[1]['searchtype']      = 'contains';
      }
      
      $tab[2]['table']           = $this->getTable();
      $tab[2]['field']           = 'firstname';
      $tab[2]['name']            = __('First name');
      if ($_SESSION['glpiactiveprofile']['interface'] != 'central') {
         $tab[2]['searchtype']      = 'contains';
      }

      $tab[3]['table']           = 'glpi_plugin_resources_contracttypes';
      $tab[3]['field']           = 'name';
      $tab[3]['name']            = PluginResourcesContractType::getTypeName(1);
      $tab[3]['datatype']        = 'dropdown';
      
      $tab[4]['table']           = 'glpi_users';
      $tab[4]['field']           = 'name';
      $tab[4]['name']            = __('Resource manager', 'resources');
      $tab[4]['datatype']        = 'dropdown';
      $tab[4]['right']           = 'all';
      if ($_SESSION['glpiactiveprofile']['interface'] != 'central') {
         $tab[4]['searchtype']      = 'contains';
      }
      
      $tab[5]['table']           = $this->getTable();
      $tab[5]['field']           = 'date_begin';
      $tab[5]['name']            = __('Arrival date', 'resources');
      $tab[5]['datatype']        = 'date';
      
      $tab[6]['table']           = $this->getTable();
      $tab[6]['field']           = 'date_end';
      $tab[6]['name']            = __('Departure date', 'resources');
      $tab[6]['datatype']        = 'date';
      
      $tab[7]['table']           = $this->getTable();
      $tab[7]['field']           = 'comment';
      $tab[7]['name']            = __('Description');
      $tab[7]['datatype']        = 'text';
      
      if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
         $tab[8]['table']           = 'glpi_plugin_resources_resources_items';
         $tab[8]['field']           = 'items_id';
         $tab[8]['name']            = _n('Associated item' , 'Associated items', 2);
         $tab[8]['massiveaction']   = false;
         $tab[8]['forcegroupby']    = true;
         $tab[8]['nosearch']        = true;
         $tab[8]['joinparams']      = array('jointype' => 'child');
      }
      
      $tab[9]['table']           = $this->getTable();
      $tab[9]['field']           = 'date_declaration';
      $tab[9]['name']            = __('Request date');
      $tab[9]['datatype']        = 'date';
      $tab[9]['massiveaction']   = false;
      
      $tab[10]['table']          = 'glpi_users';
      $tab[10]['field']          = 'name';
      $tab[10]['linkfield']      = 'users_id_recipient';
      $tab[10]['name']           = __('Requester');
      $tab[10]['datatype']       = 'dropdown';
      $tab[10]['right']          = 'all';
      $tab[10]['massiveaction']  = false;
      if ($_SESSION['glpiactiveprofile']['interface'] != 'central') {
         $tab[10]['searchtype']      = 'contains';
      }
      
      $tab[11]['table']          = 'glpi_plugin_resources_departments';
      $tab[11]['field']          = 'name';
      $tab[11]['name']           = PluginResourcesDepartment::getTypeName(1);
      $tab[11]['datatype']       = 'dropdown';
      
      $tab[12]['table']          = 'glpi_locations';
      $tab[12]['field']          = 'completename';
      $tab[12]['name']           = __('Location');
      $tab[12]['datatype']       = 'dropdown';
      
      $tab[13]['table']          = $this->getTable();
      $tab[13]['field']          = 'is_leaving';
      $tab[13]['name']           = __('Declared as leaving', 'resources');
      $tab[13]['datatype']       = 'bool';
      
      $tab[14]['table']          = 'glpi_users';
      $tab[14]['field']          = 'name';
      $tab[14]['linkfield']      = 'users_id_recipient_leaving';
      $tab[14]['name']           = __('Informant of leaving', 'resources');
      $tab[14]['datatype']       = 'dropdown';
      $tab[14]['right']          = 'all';
      $tab[14]['massiveaction']  = false;
      if ($_SESSION['glpiactiveprofile']['interface'] != 'central') {
         $tab[14]['searchtype']      = 'contains';
      }

      if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
         $tab[15]['table']          = $this->getTable();
         $tab[15]['field']          = 'is_helpdesk_visible';
         $tab[15]['name']           = __('Associable to a ticket');
         $tab[15]['datatype']       = 'bool';
      }

      $tab[16]['table']          = $this->getTable();
      $tab[16]['field']          = 'date_mod';
      $tab[16]['name']           = __('Last update');
      $tab[16]['datatype']       = 'datetime';
      $tab[16]['massiveaction']  = false;

      $tab[17]['table']          = 'glpi_plugin_resources_resourcestates';
      $tab[17]['field']          = 'name';
      $tab[17]['name']           = PluginResourcesResourceState::getTypeName(1);
      $tab[17]['datatype']       = 'dropdown';

      if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
         $tab[18]['table']          = $this->getTable();
         $tab[18]['field']          = 'picture';
         $tab[18]['name']           = __('Photo', 'resources');
         $tab[18]['massiveaction']  = false;

         $tab[19]['table']          = $this->getTable();
         $tab[19]['field']          = 'is_recursive';
         $tab[19]['name']           = __('Child entities');
         $tab[19]['datatype']       = 'bool';
         $tab[19]['massiveaction']  = false;
      }

      $tab[20]['table']          = $this->getTable();
      $tab[20]['field']          = 'quota';
      $tab[20]['name']           = __('Quota', 'resources');
      $tab[20]['datatype']       = 'decimal';

      if (Session::haveRight('plugin_resources_dropdown_public', READ)){

         $tab[21]['table']          = 'glpi_plugin_resources_resourcesituations';
         $tab[21]['field']          = 'name';
         $tab[21]['name']           = PluginResourcesResourceSituation::getTypeName(1);
         $tab[21]['massiveaction']  = false;
         $tab[21]['datatype']       = 'dropdown';
         
         $tab[22]['table']          = 'glpi_plugin_resources_contractnatures';
         $tab[22]['field']          = 'name';
         $tab[22]['name']           = PluginResourcesContractNature::getTypeName(1);
         $tab[22]['massiveaction']  = false;
         $tab[22]['datatype']       = 'dropdown';
         
         $tab[23]['table']          = 'glpi_plugin_resources_ranks';
         $tab[23]['field']          = 'name';
         $tab[23]['name']           = PluginResourcesRank::getTypeName(1);
         $tab[23]['massiveaction']  = false;
         $tab[23]['datatype']       = 'dropdown';
         
         $tab[24]['table']          = 'glpi_plugin_resources_resourcespecialities';
         $tab[24]['field']          = 'name';
         $tab[24]['name']           = PluginResourcesResourceSpeciality::getTypeName(1);
         $tab[24]['massiveaction']  = false;
         $tab[24]['datatype']       = 'dropdown';
      }

      $tab[25]['table']          = 'glpi_plugin_resources_leavingreasons';
      $tab[25]['field']          = 'name';
      $tab[25]['name']           = PluginResourcesLeavingReason::getTypeName(1);
      $tab[25]['datatype']       = 'dropdown';

      $tab[31]['table']          = $this->getTable();
      $tab[31]['field']          = 'id';
      $tab[31]['name']           = __('ID');
      $tab[31]['massiveaction']  = false;
      $tab[31]['datatype']       = 'number';

      if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
         $tab[80]['table']          = 'glpi_entities';
         $tab[80]['field']          = 'completename';
         $tab[80]['name']           = __('Entity');
         $tab[80]['datatype']       = 'dropdown';
      }
      return $tab;
   }

   function defineTabs($options=array()) {

      $ong = array();
      
      $this->addDefaultFormTab($ong);
      $this->addStandardTab('PluginResourcesResource_Item', $ong,$options);
      $this->addStandardTab('PluginResourcesChoice', $ong,$options);
      $this->addStandardTab('PluginResourcesEmployment', $ong, $options);
      $this->addStandardTab('PluginResourcesEmployee',$ong,$options);
      $this->addStandardTab('PluginResourcesChecklist',$ong,$options);
      $this->addStandardTab('PluginResourcesTask',$ong,$options);

      if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
         
         $this->addStandardTab('PluginResourcesReportConfig',$ong,$options);
         $this->addStandardTab('Document_Item',$ong,$options);
      
         if (!isset($options['withtemplate']) || empty($options['withtemplate'])) {
            $this->addStandardTab('Ticket',$ong,$options);
            $this->addStandardTab('Item_Problem', $ong, $options);
         }
      
         $this->addStandardTab('Notepad',$ong,$options);
         $this->addStandardTab('Log',$ong,$options);
      }
      return $ong;
   }

   function checkRequiredFields($input) {

      $need=array();
      $rulecollection = new PluginResourcesRuleContracttypeCollection($input['entities_id']);

      $fields=array();
      $fields=$rulecollection->processAllRules($input,$fields,array());

      $rank = new PluginResourcesRank();

      $field=array();
      foreach ($fields as $key=>$val) {
            $required=explode("requiredfields_", $key);
            if (isset($required[1]))
               $field[]=$required[1];
      }

      if (count($field) > 0)
         foreach ($field as $key=>$val) {
            if (!isset($input[$val]) 
                  || empty($input[$val]) 
                     || is_null($input[$val]) 
                        || $input[$val] == "NULL"){
               if (!$rank->canCreate() 
                     && in_array($val,
                        array('plugin_resources_ranks_id', 'plugin_resources_resourcesituations_id'))){
               } else {
                  $need[]=$val;
               }
            }
         }

      return $need;
   }

   function prepareInputForAdd($input) {

      if (!isset ($input["is_template"])) {

         $required = $this->checkRequiredFields($input);

         if(count($required) > 0) {
            Session::addMessageAfterRedirect(__('Required fields are not filled. Please try again.', 'resources'), false, ERROR);
            return array ();
         }
      }

      if (isset($input['date_end']) 
         && empty($input['date_end'])) $input['date_end']='NULL';
      if (!isset($input['plugin_resources_resourcestates_id'])
            || empty($input['plugin_resources_resourcestates_id'])) $input['plugin_resources_resourcestates_id']='0';
      //Add picture of the resource
      $input['picture']="NULL";
      if (isset($_FILES) && isset($_FILES['picture']) && $_FILES['picture']['size']>0) {

         if ($_FILES['picture']['type']=="image/jpeg" 
               || $_FILES['picture']['type']=="image/pjpeg") {
            $max_size=Toolbox::return_bytes_from_ini_vars(ini_get("upload_max_filesize"));
            if ($_FILES['picture']['size'] <= $max_size) {

               if (is_writable (GLPI_PLUGIN_DOC_DIR."/resources/")) {
                  $input['picture']= $this->addPhoto($this);
               }
            } else {
               Session::addMessageAfterRedirect(__('Failed to send the file (probably too large)'),false,ERROR);
            }
         } else {
            Session::addMessageAfterRedirect(__('Invalid filename'). " : ".$_FILES['picture']['type'],false,ERROR);
         }
      }

      if (isset($input["id"]) && $input["id"]>0) {
         $input["_oldID"]=$input["id"];
      }
      unset($input['id']);

      return $input;
   }

   function post_addItem() {
      global $CFG_GLPI;

      // Manage add from template
      if (isset($this->input["_oldID"])) {

         // ADD choices
         PluginResourcesChoice::cloneItem($this->input["_oldID"], $this->fields['id']);

         // ADD items
         PluginResourcesResource_Item::cloneItem($this->input["_oldID"], $this->fields['id']);

         // ADD reports
         PluginResourcesReportConfig::cloneItem($this->input["_oldID"], $this->fields['id']);

         //manage template from helpdesk (no employee to add : resource.form.php)
         if (!isset($this->input["add_from_helpdesk"])) {
            PluginResourcesEmployee::cloneItem($this->input["_oldID"], $this->fields['id']);
      }
         // ADD Documents
         Document_Item::cloneItem($this->getType(), $this->input["_oldID"], $this->fields['id']);
         
         // ADD tasks
         PluginResourcesTask::cloneItem($this->input["_oldID"], $this->fields['id']);
      }

      //Launch notification

      if (isset($this->input['withtemplate'])
          && $this->input["withtemplate"]!=1
          && isset($this->input['send_notification'])
          && $this->input['send_notification']==1) {
         if ($CFG_GLPI["use_mailing"]) {
            NotificationEvent::raiseEvent("new",$this);
         }
      }

      //ADD Checklists from rules
      $PluginResourcesChecklistconfig= new PluginResourcesChecklistconfig();
      $PluginResourcesChecklistconfig->addChecklistsFromRules($this,PluginResourcesChecklist::RESOURCES_CHECKLIST_IN);
      $PluginResourcesChecklistconfig->addChecklistsFromRules($this,PluginResourcesChecklist::RESOURCES_CHECKLIST_OUT);
      $PluginResourcesChecklistconfig->addChecklistsFromRules($this,PluginResourcesChecklist::RESOURCES_CHECKLIST_TRANSFER);
   }

   function replace_accents($str, $charset='utf-8')
   {
      $str = htmlentities($str, ENT_NOQUOTES, $charset);

      $str = preg_replace('#\&([A-za-z])(?:acute|cedil|circ|grave|ring|tilde|uml)\;#', '\1', $str);
      $str = preg_replace('#\&([A-za-z]{2})(?:lig)\;#', '\1', $str); // pour les ligatures e.g. '&oelig;'
      $str = preg_replace('#\&[^;]+\;#', '', $str); // supprime les autres caractères

      return $str;
   }

   function addPhoto($class)
   {
      $uploadedfile= $_FILES['picture']['tmp_name'];
      $src = imagecreatefromjpeg($uploadedfile);

      list($width,$height)=getimagesize($uploadedfile);

      $newwidth=75;
      $newheight=($height/$width)*$newwidth;
      $tmp=imagecreatetruecolor($newwidth,$newheight);

      imagecopyresampled($tmp,$src,0,0,0,0,$newwidth,$newheight,$width,$height);
      $ext = strtolower(substr(strrchr($_FILES['picture']['name'], '.'), 1));
      $resources_name = str_replace(" ","", strtolower($class->fields["name"]));
      $resources_firstname = str_replace(" ","", strtolower($class->fields["firstname"]));
      $name = $resources_name."_".$resources_firstname.".".$ext;

      $name = $this->replace_accents($name);

      $tmpfile = GLPI_DOC_DIR."/_uploads/". $name;
      $filename = GLPI_PLUGIN_DOC_DIR."/resources/". $name;

      imagejpeg($tmp,$tmpfile,100);

      rename($tmpfile,$filename);
      //Document::renameForce($tmpfile, $filename);

      imagedestroy($src);
      imagedestroy($tmp);

      return $name;
   }

   function prepareInputForUpdate($input) {

      if (isset($input['date_begin']) 
            && empty($input['date_begin'])) $input['date_begin']='NULL';
      if (isset($input['date_end']) 
            && empty($input['date_end'])) $input['date_end']='NULL';

      //unset($input['picture']);
      $this->getFromDB($input["id"]);

      if (isset($_FILES) && isset($_FILES['picture']) && $_FILES['picture']['size']>0) {

         if ($_FILES['picture']['type']=="image/jpeg" 
               || $_FILES['picture']['type']=="image/pjpeg") {
            $max_size=Toolbox::return_bytes_from_ini_vars(ini_get("upload_max_filesize"));
            if ($_FILES['picture']['size'] <= $max_size) {

               $input['picture']= $this->addPhoto($this);

            } else {
               Session::addMessageAfterRedirect(__('Failed to send the file (probably too large)'),false,ERROR);
            }
         } else {
            Session::addMessageAfterRedirect(__('Invalid filename'). " : ".$_FILES['picture']['type'],false,ERROR);
         }
      }

      $input["_old_name"]=$this->fields["name"];
      $input["_old_firstname"]=$this->fields["firstname"];
      $input["_old_plugin_resources_contracttypes_id"]=$this->fields["plugin_resources_contracttypes_id"];
      $input["_old_users_id"]=$this->fields["users_id"];
      $input["_old_users_id_recipient"]=$this->fields["users_id_recipient"];
      $input["_old_date_declaration"]=$this->fields["date_declaration"];
      $input["_old_date_begin"]=$this->fields["date_begin"];
      $input["_old_date_end"]=$this->fields["date_end"];
      $input["_old_quota"]=$this->fields["quota"];
      $input["_old_plugin_resources_departments_id"]=$this->fields["plugin_resources_departments_id"];
      $input["_old_plugin_resources_resourcestates_id"]=$this->fields["plugin_resources_resourcestates_id"];
      $input["_old_plugin_resources_resourcesituations_id"]=$this->fields["plugin_resources_resourcesituations_id"];
      $input["_old_plugin_resources_contractnatures_id"]=$this->fields["plugin_resources_contractnatures_id"];
      $input["_old_plugin_resources_ranks_id"]=$this->fields["plugin_resources_ranks_id"];
      $input["_old_plugin_resources_resourcespecialities_id"]=$this->fields["plugin_resources_resourcespecialities_id"];
      $input["_old_locations_id"]=$this->fields["locations_id"];
      $input["_old_is_leaving"]=$this->fields["is_leaving"];
      $input["_old_plugin_resources_leavingreasons_id"]=$this->fields["plugin_resources_leavingreasons_id"];
      $input["_old_comment"]=$this->fields["comment"];

      return $input;
   }

   function pre_updateInDB() {

      $PluginResourcesResource_Item= new PluginResourcesResource_Item();
      $PluginResourcesChecklist= new PluginResourcesChecklist();
      //if leaving field is updated  && isset($this->input["withtemplate"]) && $this->input["withtemplate"]!=1

      $this->input["checkbadge"]=0;

      if (isset($this->input["is_leaving"]) 
            && $this->input["is_leaving"]==1 
               && in_array("is_leaving", $this->updates)) {

         if ((!(isset($this->input["date_end"]))
               || $this->input["date_end"]=='NULL')
                  || (!(isset($this->fields["date_end"]))
                  || $this->fields["date_end"]=='NULL')) {

            Session::addMessageAfterRedirect(__('End date was not completed. Please try again.', 'resources'),false,ERROR);
            Html::back();

         } else {
            $this->fields["users_id_recipient_leaving"]=Session::getLoginUserID();
            $this->updates[]="users_id_recipient_leaving";

            $resources_checklist=PluginResourcesChecklist::checkIfChecklistExist($this->fields["id"]);
            if (!$resources_checklist) {
               $PluginResourcesChecklistconfig= new PluginResourcesChecklistconfig();
               $PluginResourcesChecklistconfig->addChecklistsFromRules($this,PluginResourcesChecklist::RESOURCES_CHECKLIST_OUT);
            }
         }
      }

      //if location field is updated
      if (isset ($this->fields["locations_id"])
         && isset ($this->input["_old_locations_id"])
         && !isset ($this->input["_UpdateFromUser_"])
         && $this->fields["locations_id"]!=$this->input["_old_locations_id"]) {

         $PluginResourcesResource_Item->updateLocation($this->fields,"PluginResourcesResource");
      }

      $this->input["addchecklist"]=0;
      if (isset ($this->fields["plugin_resources_contracttypes_id"])
         && isset ($this->input["_old_plugin_resources_contracttypes_id"])
         && $this->fields["plugin_resources_contracttypes_id"]!=$this->input["_old_plugin_resources_contracttypes_id"])
         $this->input["addchecklist"]=1;

   }

   function post_updateItem($history=1) {
      global $CFG_GLPI;

      $PluginResourcesChecklist= new PluginResourcesChecklist();
      if (isset ($this->input["addchecklist"])
         && $this->input["addchecklist"]==1) {

         $PluginResourcesChecklist->deleteByCriteria(array('plugin_resources_resources_id' => $this->fields["id"]));

         $PluginResourcesChecklistconfig= new PluginResourcesChecklistconfig();
         $PluginResourcesChecklistconfig->addChecklistsFromRules($this,
                                                   PluginResourcesChecklist::RESOURCES_CHECKLIST_IN);
         $PluginResourcesChecklistconfig->addChecklistsFromRules($this,
                                                   PluginResourcesChecklist::RESOURCES_CHECKLIST_OUT);
         $PluginResourcesChecklistconfig->addChecklistsFromRules($this,
                                                   PluginResourcesChecklist::RESOURCES_CHECKLIST_TRANSFER);
      }
      $status = "update";
      if (isset($this->fields["is_leaving"]) 
            && !empty($this->fields["is_leaving"])) {
         $status = "LeavingResource";
         $PluginResourcesResource_Item= new PluginResourcesResource_Item();
         $badge = $PluginResourcesResource_Item->searchAssociatedBadge($this->fields["id"]);
         if ($badge)
            $this->input["checkbadge"]=1;

         //when a resource is leaving, current employment get default state
         if (isset($this->input['date_end'])) {
            $PluginResourcesEmployment= new PluginResourcesEmployment();
            $default = PluginResourcesEmploymentState::getDefault();
            // only current employment
            $restrict = "`plugin_resources_resources_id` = '".$this->input["id"]."'
                        AND ((`begin_date` < '".$this->input['date_end']."'
                              OR `begin_date` IS NULL)
                              AND (`end_date` > '".$this->input['date_end']."'
                                    OR `end_date` IS NULL)) ";
            $employments = getAllDatasFromTable("glpi_plugin_resources_employments",$restrict);
            if (!empty($employments)) {
               foreach ($employments as $employment) {
                  $values = array('plugin_resources_employmentstates_id'=> $default,
                                  'end_date' => $this->input['date_end'],
                                  'id'=> $employment['id']
                  );
                  $PluginResourcesEmployment->update($values);
               }
            }
         }
      }

      $picture = array(0 => "picture",1 => "date_mod");
      if (count($this->updates)
         && array_diff($this->updates,$picture)
         && isset($this->input["withtemplate"])
         && $this->input["withtemplate"]!=1) {

         if ($CFG_GLPI["use_mailing"]
            && isset($this->input['send_notification'])
            && $this->input['send_notification']==1) {
            NotificationEvent::raiseEvent($status,$this);
         }
      }
   }

   function pre_deleteItem() {
      global $CFG_GLPI;

      if (isset($this->input['picture']) && $this->input['picture'] != "" && $this->input['picture'] != "null" && $this->input['picture'] != "NULL") {
         $filename = GLPI_PLUGIN_DOC_DIR."/resources/". $this->input['picture'];
         unlink($filename);
      }
      if ($CFG_GLPI["use_mailing"]
         && $this->fields["is_template"]!=1
         && isset($this->input['delete'])
         && isset($this->input['send_notification'])
         && $this->input['send_notification']==1) {
         NotificationEvent::raiseEvent("delete",$this);
      }

      return true;
   }

   function dropdownTemplate($name, $value = 0) {

      $restrict = "`is_template` = '1'";
      $restrict.=getEntitiesRestrictRequest(" AND ",$this->getTable(),'','',$this->maybeRecursive());
      $restrict.=" GROUP BY `template_name`
                  ORDER BY `template_name`";
      $templates = getAllDatasFromTable($this->getTable(),$restrict);

      $option[-1] = __('Without contract', 'resources');

      if (!empty($templates)) {
         foreach ($templates as $template) {
            $id_display ="";
            if ($_SESSION["glpiis_ids_visible"]||empty($template["template_name"]))
               $id_display = " (".$template["id"].")";
            $option[$template["id"]] = $template["template_name"].$id_display;
         }
      }
      return Dropdown::showFromArray($name, $option, array('value'  => $value));
   }

   /*
    * Return the SQL command to retrieve linked object
    *
    * @return a SQL command which return a set of (itemtype, items_id)
    */
   function getSelectLinkedItem () {
      return "SELECT `itemtype`, `items_id`
              FROM `glpi_plugin_resources_resources_items`
              WHERE `plugin_resources_resources_id`='" . $this->fields['id']."'";
   }

   function showForm($ID, $options=array()) {
      global $CFG_GLPI;

      $this->initForm($ID, $options);
      $options['formoptions'] = " enctype='multipart/form-data'";
      $this->showFormHeader($options);

      $required = array();
      if (isset($this->fields["entities_id"])) {
         $input['entities_id'] = $this->fields["entities_id"];
      } else {
         $input['entities_id'] = $_SESSION['glpiactive_entity'];
      }
      $input['plugin_resources_contracttypes_id'] = $this->fields["plugin_resources_contracttypes_id"];
      $required = $this->checkRequiredFields($input);
      $alert = " class='red' ";

      echo "<tr class='tab_bg_1'>";
      echo "<td";
      if(in_array("name", $required))
         echo $alert;
      echo ">";
      echo __('Surname')."</td>";
      echo "<td>";
      $option = array('option' => "onChange=\"javascript:this.value=this.value.toUpperCase();\"");
      Html::autocompletionTextField($this,"name",$option);
      echo "</td>";

      echo "<td rowspan='5' colspan='2' align='center'>";
      if (isset($this->fields["picture"]) && !empty($this->fields["picture"])) {
         $path = GLPI_PLUGIN_DOC_DIR."/resources/".$this->fields["picture"];
         if (file_exists($path)) {
            echo "<object data='".$CFG_GLPI['root_doc']."/plugins/resources/front/picture.send.php?file=".$this->fields["picture"]."'>
             <param name='src' value='".$CFG_GLPI['root_doc'].
               "/plugins/resources/front/picture.send.php?file=".$this->fields["picture"]."'>
            </object> ";
            echo "<input type='hidden' name='picture' value='".$this->fields["picture"]."'>";
         } else {
            echo "<img src='../pics/nobody.png'>";
         }
      } else {
         echo "<img src='../pics/nobody.png'>";
      }

      echo "<br>".__('Photo', 'resources')."<br>";
      echo "<input type='file' name='picture' value=\"".
         $this->fields["picture"]."\" size='25'>&nbsp;";
      echo "(".Document::getMaxUploadSize().")&nbsp;";
      if (isset($this->fields["picture"]) && !empty($this->fields["picture"])) {
         Html::showSimpleForm($CFG_GLPI["root_doc"]."/plugins/resources/front/resource.form.php",
            'delete_picture',
            _x('button', 'Delete permanently'),
            array('id' => $ID,
               'picture' => $this->fields["picture"]),
            "../pics/puce-delete2.png");
      }
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td";
      if(in_array("firstname", $required))
         echo $alert;
      echo ">";
      echo __('First name')."</td>";
      echo "<td>";
      $option = array('option' => "onChange='First2UpperCase(this.value);' style='text-transform:capitalize;'");
      Html::autocompletionTextField($this,"firstname",$option);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'><td>".PluginResourcesResourceState::getTypeName(1)."</td>";
      echo "<td>";
      if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
         Dropdown::show('PluginResourcesResourceState',
            array('value'  => $this->fields["plugin_resources_resourcestates_id"],
                  'entity' => $this->fields["entities_id"]));
      } else {
         echo Dropdown::getDropdownName("glpi_plugin_resources_resourcestates",$this->fields["plugin_resources_resourcestates_id"]);
      }
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'><td>".PluginResourcesContractType::getTypeName(1)."</td>";
      echo "<td>";
      Dropdown::show('PluginResourcesContractType',
         array('value'  => $this->fields["plugin_resources_contracttypes_id"],
               'entity' => $this->fields["entities_id"]));
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td";
      if(in_array("quota", $required))
         echo $alert;
      echo ">";
      echo __('Quota', 'resources')."</td>";
      echo "<td>";
      echo "<input type='text' name='quota' value='".Html::formatNumber($this->fields["quota"], true, 4).
         "' size='14'>";
      echo "</td>";
      echo "</tr>";

      echo "</table><table class='tab_cadre_fixe'>";
      $rank = new PluginResourcesRank();
      if ($rank->canView()) {
         echo "<tr class='tab_bg_1'>";
         echo "<td";
         if(in_array("plugin_resources_resourcesituations_id", $required))
            echo $alert;
         echo ">";
         echo PluginResourcesResourceSituation::getTypeName(1)."</td>";
         echo "<td>";

         $params = array('name' => 'plugin_resources_resourcesituations_id',
            'value' => $this->fields['plugin_resources_resourcesituations_id'],
            'entity' => $this->fields["entities_id"],
            'action' => $CFG_GLPI["root_doc"]."/plugins/resources/ajax/dropdownContractnature.php",
            'span' => 'span_contractnature'
         );
         self::showGenericDropdown('PluginResourcesResourceSituation',$params);
         echo "<td";
         if(in_array("plugin_resources_contractnatures_id", $required))
            echo $alert;
         echo ">";
         echo PluginResourcesContractNature::getTypeName(1)."</td>";
         echo "<td>";
         echo "<span id='span_contractnature' name='span_contractnature'>";
         if ($this->fields["plugin_resources_contractnatures_id"]>0) {
            echo Dropdown::getDropdownName('glpi_plugin_resources_contractnatures',
               $this->fields["plugin_resources_contractnatures_id"]);
         } else {
            _e('None');
         }
         echo "</span>";
         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td";
         if(in_array("plugin_resources_ranks_id", $required))
            echo $alert;
         echo ">";
         echo PluginResourcesRank::getTypeName(1)."</td>";
         echo "<td>";

         $params = array('name' => 'plugin_resources_ranks_id',
            'value' => $this->fields['plugin_resources_ranks_id'],
            'entity' => $this->fields["entities_id"],
            'action' => $CFG_GLPI["root_doc"]."/plugins/resources/ajax/dropdownSpeciality.php",
            'span' => 'span_speciality'
         );
         self::showGenericDropdown('PluginResourcesRank',$params);
         echo "</td>";
         echo "<td";
         if(in_array("plugin_resources_resourcespecialities_id", $required))
            echo $alert;
         echo ">";
         echo PluginResourcesResourceSpeciality::getTypeName(1)."</td>";
         echo "<td>";
         echo "<span id='span_speciality' name='span_speciality'>";
         if ($this->fields["plugin_resources_resourcespecialities_id"]>0) {
            echo Dropdown::getDropdownName('glpi_plugin_resources_resourcespecialities',
               $this->fields["plugin_resources_resourcespecialities_id"]);
         } else {
            _e('None');
         }
         echo "</span>";
         echo "</td>";
         echo "</tr>";
         echo "</table><table class='tab_cadre_fixe'>";

      }

      echo "<tr class='tab_bg_1'>";
      echo "<td";
      if(in_array("locations_id", $required))
         echo $alert;
      echo ">";
      echo __('Location')."</td>";
      echo "<td>";
      Dropdown::show('Location',
         array('value'  => $this->fields["locations_id"],
               'entity' => $this->fields["entities_id"]));
      echo "</td>";
      echo "<td";
      if(in_array("plugin_resources_departments_id", $required))
         echo $alert;
      echo ">";
      echo PluginResourcesDepartment::getTypeName(1)."</td>";
      echo "<td>";
      Dropdown::show('PluginResourcesDepartment',
         array('value'  => $this->fields["plugin_resources_departments_id"],
               'entity' => $this->fields["entities_id"]));
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td";
      if(in_array("users_id", $required))
         echo $alert;
      echo ">";
      echo __('Resource manager', 'resources')."</td>";
      echo "<td>";
      User::dropdown(array('value'  => $this->fields["users_id"],
                           'name'  => "users_id",
                           'entity' => $this->fields["entities_id"],
                           'right'  => 'all'));
      echo "</td>";
      echo "<td";
      if(in_array("date_begin", $required))
         echo $alert;
      echo ">";
      echo __('Arrival date', 'resources')."</td>";
      echo "<td>";
      Html::showDateFormItem("date_begin",$this->fields["date_begin"],true,true);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'><td colspan='4'>".__('Description')."</td></tr>";

      echo "<tr class='tab_bg_1'><td colspan='4'>";
      echo "<textarea cols='130' rows='4' name='comment' >".$this->fields["comment"]."</textarea>";
      echo "<input type='hidden' name='withtemplate' value='".$options['withtemplate']."'>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td colspan='2'>";
      if($ID && $options['withtemplate'] < 2) {
         echo __('Request date')." : ";
         echo Html::convDateTime($this->fields["date_declaration"]);
         echo "&nbsp;".__('By')."&nbsp;";
         $users_id_recipient=new User();
         $users_id_recipient->getFromDB($this->fields["users_id_recipient"]);
         if ($this->canCreate() && $_SESSION['glpiactiveprofile']['interface'] == 'central') {

            User::dropdown(array('value'  => $this->fields["users_id_recipient"],
               'name'  => "users_id_recipient",
               'entity' => $this->fields["entities_id"],
               'right'  => 'all'));
         } else {
            echo $users_id_recipient->getName();
         }
      } else {
         echo "<input type='hidden' name='users_id_recipient' value=\"".Session::getLoginUserID()."\" >";
         echo "<input type='hidden' name='date_declaration' value=\"".$_SESSION["glpi_currenttime"]."\" >";
      }
      echo "</td>";

     echo "<td>".__('Associable to a ticket')."</td><td>";

      if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
         Dropdown::showYesNo('is_helpdesk_visible',$this->fields['is_helpdesk_visible']);
      } else {
         echo Dropdown::getDropdownName($this->getTable(),$this->fields["is_helpdesk_visible"]);
      }
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td></td><td></td><td>".__('Send a notification')."</td><td>";
      echo "<input type='checkbox' name='send_notification' checked = true";
      if ($_SESSION['glpiactiveprofile']['interface'] != 'central')
         echo " disabled='true' ";
      echo " value='1'>";
      if ($_SESSION['glpiactiveprofile']['interface'] != 'central')
         echo "<input type='hidden' name='send_notification' value=\"1\">";
      echo "</td>";
      echo "</tr>";

      echo "</table><table class='tab_cadre_fixe'>";
      echo "<tr class='tab_bg_1'>";

      echo "<td>".__('Declared as leaving', 'resources')."</td><td>";
      Dropdown::showYesNo("is_leaving",$this->fields["is_leaving"]);

      if($ID!=-1 && $options['withtemplate'] != 1 && $this->fields["is_leaving"]==1
         && isset($this->fields["users_id_recipient_leaving"])) {
         echo "&nbsp;".__('By')."&nbsp;";
         $users_id_recipient_leaving=new User();
         if ($users_id_recipient_leaving->getFromDB($this->fields["users_id_recipient_leaving"]))
            echo $users_id_recipient_leaving->getName();
      }

      echo "</td>";
      echo "<td";
      if(in_array("plugin_resources_leavingreasons_id", $required))
         echo $alert;
      echo ">";
      echo PluginResourcesLeavingReason::getTypeName(1)."</td>";
      echo "<td>";
      Dropdown::show('PluginResourcesLeavingReason',
         array('value'  => $this->fields["plugin_resources_leavingreasons_id"],
               'entity' => $this->fields["entities_id"]));
      echo "</td>";

      echo "<td";
      if(in_array("date_end", $required))
         echo $alert;
      echo ">";
      echo __('Departure date', 'resources')."&nbsp;";
      if(!in_array("date_end", $required))
         Html::showToolTip(nl2br(__('Empty for non defined', 'resources')));
      echo "</td>";
      echo "<td>";
      Html::showDateFormItem("date_end",$this->fields["date_end"],true,true);
      echo "</td>";
      echo "</tr>";
      echo "<tr class='tab_bg_1'>";
      echo "<td colspan='6'>";
      if (isset($options['withtemplate']) && $options['withtemplate']) {
         //TRANS: %s is the datetime of insertion
         printf(__('Created on %s'), Html::convDateTime($_SESSION["glpi_currenttime"]));
      } else {
         //TRANS: %s is the datetime of update
         printf(__('Last update on %s'), Html::convDateTime($this->fields["date_mod"]));
      }
      echo "</td></tr>\n";

      echo "</table><table class='tab_cadre_fixe'>";

      if ($_SESSION['glpiactiveprofile']['interface'] != 'central')
         $options['candel'] = false;
      $this->showFormButtons($options);

      return true;
   }

   function sendReport($options) {
      global $CFG_GLPI;

      if (!$this->getFromDB($options["id"])) return false;

      if ($CFG_GLPI["use_mailing"]) {
         $report = new PluginResourcesReportConfig();
         $report->getFromDB($options["reports_id"]);
         
         if ($report->fields['send_report_notif']) {
            $notification = new PluginResourcesNotification();
            $notification->add(array('users_id'                      => Session::getLoginUserID(), 
                                     'plugin_resources_resources_id' => $options["id"], 
                                     'type'                          => 'report'));
            NotificationEvent::raiseEvent('report',$this,array('reports_id'=>$options["reports_id"]));
         }
         
         if ($report->fields['send_other_notif']) {
            $notification = new PluginResourcesNotification();
            $notification->add(array('users_id'                      => Session::getLoginUserID(), 
                                     'plugin_resources_resources_id' => $options["id"], 
                                     'type'                          => 'other'));
            NotificationEvent::raiseEvent('other',$this,array('reports_id'=>$options["reports_id"]));
         }
      }
   }

   function reSendResourceCreation($options) {
      global $CFG_GLPI;

      if (!$this->getFromDB($options["id"])) return false;

      if ($CFG_GLPI["use_mailing"]) {
         $status = "new";
         NotificationEvent::raiseEvent($status,$this);
      }
   }

   static function showReportForm($options) {

      $reportconfig = new PluginResourcesReportConfig();
      $reportconfig->getFromDBByResource($options['id']);
      
      if ($reportconfig->fields['send_report_notif'] || $reportconfig->fields['send_other_notif']) {
         echo "<div align='center'>";
         echo "<form action='".$options['target']."' method='post'>";
         echo "<table class='tab_cadre_fixe' width='50%'>";
         echo "<tr><th colspan='4'>".PluginResourcesReportConfig::getTypeName(2)."</th></tr>";
         echo "<tr class='tab_bg_2 center'>";
         echo "<td colspan='4'>";
         echo "<input type='submit' name='report' value='".__s('Send a notification')."' class='submit' />";
         echo "<input type='hidden' name='id' value='".$options['id']."'>";
         echo "<input type='hidden' name='reports_id' value='".$reportconfig->fields["id"]."'>";
         echo "</td></tr></table>";
         Html::closeForm();
         echo "</div>";
      }
      
      $notification = new PluginResourcesNotification();
      $notification->listItems($options['id']);
   }

   /**
    * Display menu
    */
   function showMenu() {
      global $CFG_GLPI;

      echo "<div align='center'><table class='tab_cadre' width='30%' cellpadding='5'>";
      echo "<tr><th colspan='5'>".__('Menu', 'resources')."</th></tr>";

      $canresting = Session::haveright('plugin_resources_resting', UPDATE);
      $canholiday = Session::haveright('plugin_resources_holiday', UPDATE);
      $canemployment = Session::haveright('plugin_resources_employment', UPDATE);
      $canseeemployment = Session::haveright('plugin_resources_employment', READ);
      $canseebudget = Session::haveright('plugin_resources_budget', READ);
      $colspan = "1";
      $colspan2 = "1";
      if (!$this->canCreate())
         $colspan+= 3;
      if (!$canresting || !$canholiday)
         $colspan2+= 1;
      echo "<tr><th colspan='5'>".__('Resources management', 'resources')."</th></tr>";

      echo "<tr class='tab_bg_1'>";

      if($this->canCreate()) {
         //Add a resource
         echo "<td class='center'>";
         echo "<a href=\"./wizard.form.php\">";
         echo "<img src='".$CFG_GLPI["root_doc"]."/plugins/resources/pics/newresource.png' alt='".__('Declare an arrival', 'resources')."'>";
         echo "<br>".__('Declare an arrival', 'resources')."</a>";
         echo "</td>";
      
      } else {
         echo "<td class='center'>&nbsp;";
         echo "</td>";
      }
      if($this->caView()) {
         //See resources
         echo "<td class='center'>";
         echo "<a href=\"./resource.php\">";
         echo "<img src='".$CFG_GLPI["root_doc"]."/plugins/resources/pics/resourcelist.png' alt='".__('Search resources', 'resources')."'>";
         echo "<br>".__('Search resources', 'resources')."</a>";
         echo "</td>";
         
      } else {
         echo "<td class='center'>&nbsp;";
         echo "</td>";
      }
      if($this->canCreate()) {
      
         //Remove resources
         echo "<td class='center'>";
         echo "<a href=\"./resource.remove.php\">";
         echo "<img src='".$CFG_GLPI["root_doc"]."/plugins/resources/pics/removeresource.png' alt='".__('Declare a departure', 'resources')."'>";
         echo "<br>".__('Declare a departure', 'resources')."</a>";
         echo "</td>";
      
         //Transfer resources
         echo "<td class='center'>";
         echo "<a href=\"./resource.transfer.php\">";
         echo "<img src='".$CFG_GLPI["root_doc"]."/plugins/resources/pics/transferresource.png' alt='".__('Declare a transfer', 'resources')."'>";
         echo "<br>".__('Declare a transfer', 'resources')."</a>";
         echo "</td>";
      } else {
         echo "<td class='center'>&nbsp;";
         echo "</td>";
         echo "<td class='center'>&nbsp;";
         echo "</td>";
      }
      echo "<td colspan='$colspan' class='center'>";
      echo "<a href=\"./directory.php\">";
      echo "<img src='".$CFG_GLPI["root_doc"]."/plugins/resources/pics/directory.png' alt='".PluginResourcesDirectory::getTypeName(1)."'>";
      echo "<br>".PluginResourcesDirectory::getTypeName(1)."</a>";
      echo "</td>";

      echo "</tr>";

      if ($canresting || $canholiday) {
         echo "<tr><th colspan='5'>".__('Others declarations', 'resources')."</th></tr>";

         echo "<tr class='tab_bg_1'>";
         if ($canresting) {
            //Add resting resource
            echo "<td colspan='$colspan2' class='center'>";
            echo "<a href=\"./resourceresting.form.php\">";
            echo "<img src='".$CFG_GLPI["root_doc"]."/plugins/resources/pics/newresting.png' alt='".__('Declare a non contract period', 'resources')."'>";
            echo "<br>".__('Declare a non contract period', 'resources')."</a>";
            echo "</td>";
            //List resting resource
            echo "<td colspan='$colspan2' class='center'>";
            echo "<a href=\"./resourceresting.php\">";
            echo "<img src='".$CFG_GLPI["root_doc"]."/plugins/resources/pics/restinglist.png' alt='".__('List of non contract periods', 'resources')."'>";
            echo "<br>".__('List of non contract periods', 'resources')."</a>";
            echo "</td>";
         }
         if ($canholiday) {
            echo "<td colspan='$colspan2' class='center'>";
            echo "<a href=\"./resourceholiday.form.php\">";
            echo "<img src='".$CFG_GLPI["root_doc"]."/plugins/resources/pics/holidayresource.png' alt='".__('Declare a forced holiday', 'resources')."'>";
            echo "<br>".__('Declare a forced holiday', 'resources')."</a>";
            echo "</td>";
            echo "<td colspan='$colspan2' class='center'>";
            echo "<a href=\"./resourceholiday.php\">";
            echo "<img src='".$CFG_GLPI["root_doc"]."/plugins/resources/pics/holidaylist.png' alt='".__('List of forced holidays', 'resources')."'>";
            echo "<br>".__('List of forced holidays', 'resources')."</a>";
            echo "</td>";
         }
         echo "<td></td>";
         echo "</tr>";
      }

      if ($canseeemployment || $canseebudget) {

         echo "<tr><th colspan='5'>".__('Employments / budgets management', 'resources')."</th></tr>";

         echo "<tr class='tab_bg_1'>";

         if($canseeemployment) {
            if($canemployment) {
               //Add an employment
               echo "<td class='center'>";
               echo "<a href=\"./employment.form.php\">";
               echo "<img src='".$CFG_GLPI["root_doc"]."/plugins/resources/pics/employment.png' alt='".__('Declare an employment', 'resources')."'>";
               echo "<br>".__('Declare an employment', 'resources')."</a>";
               echo "</td>";
            }
            //See managment employments
            echo "<td class='center'>";
            echo "<a href=\"./employment.php\">";
            echo "<img src='".$CFG_GLPI["root_doc"]."/plugins/resources/pics/employmentlist.png' alt='".__('Employment management', 'resources')."'>";
            echo "<br>".__('Employment management', 'resources')."</a>";
            echo "</td>";
         }
         if ($canseebudget){
            //See managment budgets
            echo "<td class='center'>";
            echo "<a href=\"./budget.php\">";
            echo "<img src='".$CFG_GLPI["root_doc"]."/plugins/resources/pics/budgetlist.png' alt='".__('Budget management', 'resources')."'>";
            echo "<br>".__('Budget management', 'resources')."</a>";
            echo "</td>";
         }

         if($canseeemployment) {
            //See recap ressource / employment
            echo "<td class='center'>";
            echo "<a href=\"./recap.php\">";
            echo "<img src='".$CFG_GLPI["root_doc"]."/plugins/resources/pics/recap.png' alt='".__('List Employments / Resources', 'resources')."'>";
            echo "<br>".__('List Employments / Resources', 'resources')."</a>";
            echo "</td>";
         }
         echo "<td></td>";
         echo "</tr>";
      }
      echo " </table></div>";
   }


   function wizardFirstForm () {

      echo "<div align='center'>";

      echo "<form action='./wizard.form.php' method='post'>";
      echo "<table class='plugin_resources_wizard'>";
      echo "<tr>";
      echo "<td class='plugin_resources_wizard_left_area' valign='top'>";
      echo "</td>";

      echo "<td class='plugin_resources_wizard_right_area' valign='top'>";

      echo "<div class='plugin_resources_wizard_title'><p>";
      echo "<img class='plugin_resource_wizard_img' src='../pics/newresource.png' alt='newresource' />&nbsp;";
      _e('Welcome to the wizard resource', 'resources');
      echo "</p></div>";

      echo "<div class='plugin_resources_presentation_text'>";
      _e('This wizard lets you create new resources in GLPI', 'resources');
      echo "<br /><br /><br />";
      _e('To begin, select type of contract', 'resources');
      echo "</div>";

      echo "<br /><br /><div class='center'>";

      $this->dropdownTemplate("template");

      echo "</div></td>";
      echo "</tr>";

      echo "<tr><td class='plugin_resources_wizard_button' colspan='2'>";
      echo "<div class='next'>";
      echo "<input type='hidden' name='withtemplate' value='2' >";
      echo "<input type='submit' name='first_step' value='"._sx('button','Next >', 'resources')."' class='submit' />";
      echo "</div>";
      echo "</td></tr></table>";
      Html::closeForm();

      echo "</div>";
   }

   function wizardSecondForm ($ID, $options=array()) {
      global $CFG_GLPI;

      $empty = 0;
      if ($ID > 0) {
         $this->check($ID,READ);
      } else {
         // Create item
         $this->check(-1,UPDATE);
         $this->getEmpty();
         $empty = 1;
      }

      $rank = new PluginResourcesRank();

      if (!isset($options["requiredfields"]))
         $options["requiredfields"] = 0;
      if (($options['withtemplate'] == 2 || $options["new"]!=1) && $options["requiredfields"]!=1) {

         $options["name"] = $this->fields["name"];
         $options["firstname"] = $this->fields["firstname"];
         $options["locations_id"] = $this->fields["locations_id"];
         $options["users_id"] = $this->fields["users_id"];
         $options["plugin_resources_departments_id"] = $this->fields["plugin_resources_departments_id"];
         $options["date_begin"] = $this->fields["date_begin"];
         $options["date_end"] = $this->fields["date_end"];
         $options["comment"] = $this->fields["comment"];
         $options["quota"] = $this->fields["quota"];
         $options["plugin_resources_resourcesituations_id"] = $this->fields["plugin_resources_resourcesituations_id"];
         $options["plugin_resources_contractnatures_id"] = $this->fields["plugin_resources_contractnatures_id"];
         $options["plugin_resources_ranks_id"] = $this->fields["plugin_resources_ranks_id"];
         $options["plugin_resources_resourcespecialities_id"] = $this->fields["plugin_resources_resourcespecialities_id"];
         $options["plugin_resources_leavingreasons_id"] = $this->fields["plugin_resources_leavingreasons_id"];

      }

      echo "<div align='center'>";

      echo "<form action='".$options['target']."' method='post'>";
      echo "<table class='plugin_resources_wizard'>";
      echo "<tr>";

      echo "<td class='plugin_resources_wizard_right_area' valign='top'>";

      echo "<div class='plugin_resources_wizard_title'><p>";
      echo "<img class='plugin_resource_wizard_img' src='".$CFG_GLPI['root_doc']."/plugins/resources/pics/newresource.png' alt='newresource'/>&nbsp;";
      _e('Enter general information about the resource', 'resources');
      echo "</p></div>";

      echo "<div class='center'>";

      if (!$this->canView()) return false;
      echo "<table class='plugin_resources_wizard_table'>";
      echo "<tr class='plugin_resources_wizard_explain'>";
      echo "<td width='30%'>".PluginResourcesContractType::getTypeName(1)."</td><td width='70%'>";
      if ($this->fields["plugin_resources_contracttypes_id"])
         echo Dropdown::getDropdownName("glpi_plugin_resources_contracttypes",
            $this->fields["plugin_resources_contracttypes_id"]);
      else
         _e('Without contract', 'resources');
      echo "</td>";
      echo "</tr></table>";

      echo "<br>";

      echo "<table class='plugin_resources_wizard_table'>";
      echo "<tr class='plugin_resources_wizard_explain'>";

      $required = array();
      $input = array();
      if (isset($this->fields["entities_id"]) || $empty==1) {
         if ($empty==1) {
            $input['plugin_resources_contracttypes_id'] = 0;
            $input['entities_id'] = $_SESSION['glpiactive_entity'];
            echo "<input type='hidden' name='entities_id' value='".$_SESSION['glpiactive_entity']."'>";
         } else {
            $input['plugin_resources_contracttypes_id'] = $this->fields["plugin_resources_contracttypes_id"];
            if (isset($options['withtemplate']) && $options['withtemplate'] == 2) {
               $input['entities_id'] = $_SESSION['glpiactive_entity'];
               echo "<input type='hidden' name='entities_id' value='".$_SESSION['glpiactive_entity']."'>";
            } else {
               $input['entities_id'] = $this->fields["entities_id"];
               echo "<input type='hidden' name='entities_id' value='".$this->fields["entities_id"]."'>";
            }
         }
      }
      $required = $this->checkRequiredFields($input);
      $alert = " class='red' ";

      if (Session::isMultiEntitiesMode()) {
         echo "<tr class='plugin_resources_wizard_explain'>";
         echo "<td width='30%'>";
         _e('Entity');
         echo "</td>";
         echo "<td width='50%'>";
         echo Dropdown::getDropdownName("glpi_entities",$input['entities_id']);
         echo "</td>";
         echo "</tr>";
      }

      echo "<tr class='plugin_resources_wizard_explain'>";
      echo "<td";
      if(in_array("name", $required))
         echo $alert;
      echo ">";
      _e('Surname');
      echo "</td>";
      echo "<td>";
      $option = array('value' => $options["name"],'option' => "onchange=\"javascript:this.value=this.value.toUpperCase();\"");
      Html::autocompletionTextField($this,"name",$option);
      echo "</td>";

      echo "<td rowspan='2' class='plugin_resources_wizard_comment red'>";
      _e("Thank you for paying attention to the spelling of the name and the firstname of the resource. For compound firstnames, separate them with a dash \"-\".", "resources");
      echo "</td>";

      echo "</tr>";

      echo "<tr class='plugin_resources_wizard_explain'>";
      echo "<td";
      if(in_array("firstname", $required))
         echo $alert;
      echo ">";
      _e('First name');
      echo "</td>";
      echo "<td>";
      $option = array('value' => $options["firstname"],
                  'option' => "onChange='First2UpperCase(this.value);' style='text-transform:capitalize;'");
      Html::autocompletionTextField($this,"firstname",$option);
      echo "</td>";
      echo "</tr>";

      if ($this->fields["plugin_resources_resourcestates_id"]) {
         echo "<tr class='plugin_resources_wizard_explain'>";
         echo "<td>".PluginResourcesResourceState::getTypeName(1)."</td><td>";
         echo Dropdown::getDropdownName("glpi_plugin_resources_resourcestates",
                                          $this->fields["plugin_resources_resourcestates_id"]);
         echo "</td>";
         echo "</tr>";
      }

      echo "<tr class='plugin_resources_wizard_explain'>";
      echo "<td";
      if(in_array("locations_id", $required))
         echo $alert;
      echo ">";
      _e('Location');
      echo "</td>";
      echo "<td>";
      Dropdown::show('Location', array('name' => "locations_id",'value' => $options["locations_id"]));
      echo "</td>";
      echo "</tr>";

      echo "<tr class='plugin_resources_wizard_explain'>";
      echo "<td";
      if(in_array("quota", $required))
         echo $alert;
      echo ">";
      _e('Quota', 'resources');
      echo "</td>";
      echo "<td>";
      echo "<input type='text' name='quota' value='".Html::formatNumber($options["quota"], true,4).
         "' size='14'>";
      echo "</td>";
      echo "</tr></table>";

      echo "<br>";

      if ($rank->canView()) {
         echo "<table class='plugin_resources_wizard_table'>";
         echo "<tr class='plugin_resources_wizard_explain'>";
         echo "<td width='30%' ";
         if(in_array("plugin_resources_resourcesituations_id", $required))
            echo $alert;
         echo ">";
         echo PluginResourcesResourceSituation::getTypeName(1);
         echo "</td>";
         echo "<td width='70%'>";
         $params = array('name' => 'plugin_resources_resourcesituations_id',
            'value' => $options['plugin_resources_resourcesituations_id'],
            'entity' => $this->fields["entities_id"],
            'action' => $CFG_GLPI["root_doc"]."/plugins/resources/ajax/dropdownContractnature.php",
            'span' => 'span_contractnature'
         );
         self::showGenericDropdown('PluginResourcesResourceSituation',$params);
         echo "</td>";
         echo "</tr>";

         echo "<tr class='plugin_resources_wizard_explain'>";
         echo "<td";
         if(in_array("plugin_resources_contractnatures_id", $required))
            echo $alert;
         echo ">";
         echo PluginResourcesContractNature::getTypeName(1);
         echo "</td><td>";
         echo "<span id='span_contractnature' name='span_contractnature'>";
         if ($options["plugin_resources_contractnatures_id"]>0) {
            echo Dropdown::getDropdownName('glpi_plugin_resources_contractnatures',
               $options["plugin_resources_contractnatures_id"]);
         } else {
            echo "<input type='hidden' name='plugin_resources_contractnatures_id' value='0'>";
            _e('None');
         }
         echo "</span>";
         echo "</td>";
         echo "</tr>";

         echo "<tr class='plugin_resources_wizard_explain'>";
         echo "<td";
         if(in_array("plugin_resources_ranks_id", $required))
            echo $alert;
         echo ">";
         echo PluginResourcesRank::getTypeName(1);
         echo "</td>";
         echo "<td>";
         $params = array('name' => 'plugin_resources_ranks_id',
                         'value' => $options['plugin_resources_ranks_id'],
                         'entity' => $this->fields["entities_id"],
                         'action' => $CFG_GLPI["root_doc"]."/plugins/resources/ajax/dropdownSpeciality.php",
                         'span' => 'span_speciality'
         );
         self::showGenericDropdown('PluginResourcesRank',$params);

         echo "</td>";
         echo "</tr>";

         echo "<tr class='plugin_resources_wizard_explain'>";
         echo "<td";
         if(in_array("plugin_resources_resourcespecialities_id", $required))
            echo $alert;
         echo ">";
         echo PluginResourcesResourceSpeciality::getTypeName(1);
         echo "</td><td>";
         echo "<span id='span_speciality' name='span_speciality'>";
         if ($options["plugin_resources_resourcespecialities_id"]>0) {
            echo Dropdown::getDropdownName('glpi_plugin_resources_resourcespecialities',
               $options["plugin_resources_resourcespecialities_id"]);
         } else {
            echo "<input type='hidden' name='plugin_resources_resourcespecialities_id' value='0'>";
            _e('None');
         }
         echo "</span>";
         echo "</td>";
         echo "</tr></table>";

         echo "<br>";
      } else {

         echo "<input type='hidden' name='plugin_resources_resourcesituations_id' value='0'>";
         echo "<input type='hidden' name='plugin_resources_contractnatures_id' value='0'>";
         echo "<input type='hidden' name='plugin_resources_ranks_id' value='0'>";
         echo "<input type='hidden' name='plugin_resources_resourcespecialities_id' value='0'>";
      }

      echo "<table class='plugin_resources_wizard_table'>";
      echo "<tr class='plugin_resources_wizard_explain'>";
      echo "<td width='30%' ";
      if(in_array("users_id", $required))
         echo $alert;
      echo ">";
      echo __('Resource manager', 'resources')."</td>";
      echo "<td width='70%'>";
      User::dropdown(array('value'  => $options["users_id"],
                           'name'  => "users_id",
                           'entity' => $input['entities_id'],
                           'right'  => 'all'));
      echo "</td>";
      echo "</tr>";

      echo "<tr class='plugin_resources_wizard_explain'>";
      echo "<td";
      if(in_array("plugin_resources_departments_id", $required))
         echo $alert;
      echo ">";
      echo PluginResourcesDepartment::getTypeName(1)."</td><td>";
      Dropdown::show('PluginResourcesDepartment',
         array('name' => "plugin_resources_departments_id",
               'value' => $options["plugin_resources_departments_id"],
               'entity' => $this->fields["entities_id"]));
      echo "</td>";
      echo "</tr>";

      echo "<tr class='plugin_resources_wizard_explain'>";
      echo "<td";
      if(in_array("date_begin", $required))
         echo $alert;
      echo ">";
      _e('Arrival date', 'resources');
      echo "</td>";
      echo "<td>";
      Html::showDateFormItem("date_begin",$options["date_begin"],true,true);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='plugin_resources_wizard_explain'>";
      echo "<td";
      if(in_array("date_end", $required))
         echo $alert;
      echo ">";
       echo __('Departure date', 'resources')."&nbsp;";
      if(!in_array("date_end", $required))
         Html::showToolTip(nl2br(__('Empty for non defined', 'resources')));
      echo "</td>";
      echo "<td>";
      Html::showDateFormItem("date_end",$options["date_end"],true,true);
      echo "</td>";
      echo "</tr></table>";

      echo "<br>";

      echo "<table class='plugin_resources_wizard_table'>";
      echo "<tr class='plugin_resources_wizard_explain'>";
      echo "<td colspan='4'>".__('Description')."</td>";
      echo "</tr>";

      echo "<tr><td colspan='4'>";
      echo "<textarea cols='95' rows='6' name='comment' >".$options["comment"]."</textarea>";

      echo "</td></tr>";

      echo "<tr class='plugin_resources_wizard_explain'>";
      echo "<td colspan='2'>".__('Send a notification')."&nbsp;";
      echo "<input type='checkbox' name='send_notification' checked = true";
      if ($_SESSION['glpiactiveprofile']['interface'] != 'central')
         echo " disabled='true' ";
      echo " value='1'>";
      if ($_SESSION['glpiactiveprofile']['interface'] != 'central')
         echo "<input type='hidden' name='send_notification' value=\"1\">";
      echo "</td></tr>";

      echo "<tr><td colspan='4'>&nbsp;";
      echo "</td></tr>";

      if (!empty($required)){
         echo "<tr>";
         echo "<td class='right plugin_resources_wizard_explain red' colspan='4'>";
         _e('The fields in red must be completed', 'resources');
         echo "</td>";
         echo "</tr>";
      }

      echo "</table>";
      echo "</div></td>";
      echo "</tr>";
      $contract = $this->fields["plugin_resources_contracttypes_id"];
      if ($empty==1)
        $contract = $input['plugin_resources_contracttypes_id'];
      echo "<input type='hidden' name='plugin_resources_contracttypes_id' value=\"".$contract."\">";
      echo "<input type='hidden' name='plugin_resources_resourcestates_id' value=\"".$this->fields["plugin_resources_resourcestates_id"]."\">";
      echo "<input type='hidden' name='withtemplate' value=\"".$options['withtemplate']."\" >";
      echo "<input type='hidden' name='date_declaration' value=\"".$_SESSION["glpi_currenttime"]."\">";
      echo "<input type='hidden' name='users_id_recipient' value=\"".Session::getLoginUserID()."\">";
      echo "<input type='hidden' name='id' value=\"".$ID."\">";
      echo "<input type='hidden' name='plugin_resources_leavingreasons_id' value='0'>";

      if($this->canCreate() && (empty($ID)||$options['withtemplate']==2)) {
         echo "<tr><td class='plugin_resources_wizard_button' colspan='2'>";
         echo "<div class='preview'>";
         echo "<input type='submit' name='undo_first_step' value='"._sx('button','< Previous', 'resources')."' class='submit' />";
         echo "</div>";
         echo "<div class='next'>";
         echo "<input type='submit' name='second_step' value='"._sx('button','Next >', 'resources')."' class='submit' />";
         echo "<input type='hidden' name='plugin_resources_resources_id' value='".$this->fields["id"]."'/>";
         echo "</div>";
         echo "</td></tr>";
      } else if ($this->canCreate() && !empty($ID) && $options["new"]!=1) {

         echo "<tr><td class='plugin_resources_wizard_button' colspan='2'>";
         echo "<div class='preview'>";
         echo "<input type='submit' name='undo_first_step' value='"._sx('button','< Previous', 'resources')."' class='submit' />";
         echo "</div>";
         echo "<div class='next'>";
         echo "<input type='submit' name='second_step_update' value='"._sx('button','Next >', 'resources')."' class='submit' />";
         echo "<input type='hidden' name='plugin_resources_resources_id' value='".$this->fields["id"]."'/>";
         echo "</div>";
         echo "</td></tr>";
      }
      echo "</table>";
      Html::closeForm();

      echo "</div>";
   }

   function wizardFiveForm ($ID, $options=array()) {
      global $CFG_GLPI;

      if ($ID > 0) {
         $this->check($ID,READ);
      }

      echo "<div align='center'>";

      echo "<form action='".$options['target']."' enctype='multipart/form-data' method='post'>";
      echo "<table class='plugin_resources_wizard' >";
      echo "<tr>";
      echo "<td class='plugin_resources_wizard_left_area' valign='top'>";
      echo "</td>";

      echo "<td class='plugin_resources_wizard_right_area' valign='top'>";

      echo "<div class='plugin_resources_wizard_title'><p>";
      echo "<img class='plugin_resource_wizard_img' src='".$CFG_GLPI['root_doc']."/plugins/resources/pics/newresource.png' alt='newresource'/>&nbsp;";
      _e('Add the photo of the resource', 'resources');
      echo "</p></div>";

      echo "<div class='center'>";

      if (!$this->canView()) return false;
      echo "<table class='plugin_resources_wizard_table'>";

      echo "<tr><td colspan='2' align='left'>";
      if (isset($this->fields["picture"])) {
         $path = GLPI_PLUGIN_DOC_DIR."/resources/".$this->fields["picture"];
         if (file_exists($path)) {
            echo "<object data='".$CFG_GLPI['root_doc']."/plugins/resources/front/picture.send.php?file=".$this->fields["picture"]."'>
             <param name='src' value='".$CFG_GLPI['root_doc'].
              "/plugins/resources/front/picture.send.php?file=".$this->fields["picture"]."'>
            </object> ";
         } else {
            echo "<img src='".$CFG_GLPI['root_doc']."/plugins/resources/pics/nobody.png'>";
         }
      } else {
         echo "<img src='".$CFG_GLPI['root_doc']."/plugins/resources/pics/nobody.png'>";
      }
      echo "</td><td colspan='2' align='left'>".__('Photo format : JPG', 'resources')."<br>";
      echo "<input type='file' name='picture' value=\"".
                 $this->fields["picture"]."\" size='25'>&nbsp;";
      echo "(".Document::getMaxUploadSize().")&nbsp;";
      echo "</td></tr>";
      echo "<tr><td colspan='2'>&nbsp;";
      echo "</td><td colspan='2'>";
      echo "<input type='submit' name='upload_five_step' value='"._sx('button','Add')."' class='submit' />";
      echo "<input type='hidden' name='plugin_resources_resources_id' value='".$this->fields["id"]."'/>";
      echo "</td></tr>";
      echo "</table>";
      echo "</div></td>";
      echo "</tr>";

      echo "<input type='hidden' name='plugin_resources_resources_id' value=\"".$ID."\">";

      if($this->canCreate() && (!empty($ID))) {
         echo "<tr><td class='plugin_resources_wizard_button' colspan='2'>";
         echo "<div class='next'>";
         echo "<input type='submit' name='five_step' value='"._sx('button','Next >', 'resources')."' class='submit' />";
         echo "<input type='hidden' name='plugin_resources_resources_id' value='".$this->fields["id"]."'/>";
         echo "</div>";
         echo "</td></tr>";
      }
      echo "</table>";
      Html::closeForm();

      echo "</div>";
   }

   static function getResourceName($ID, $link=0) {
      global $DB, $CFG_GLPI;

      $user = "";
      if ($link==2) {
         $user = array("name"    => "",
                       "link"    => "",
                       "comment" => "");
      }

      if ($ID) {
         $query = "SELECT `glpi_plugin_resources_resources`.*,
                          `glpi_users`.`registration_number`,
                          `glpi_users`.`name` AS username
                   FROM `glpi_plugin_resources_resources`
                      LEFT JOIN `glpi_plugin_resources_resources_items`
                        ON (`glpi_plugin_resources_resources_items`.`plugin_resources_resources_id`
                            = `glpi_plugin_resources_resources`.`id`)
                      LEFT JOIN `glpi_users`
                        ON (`glpi_users`.`id` = `glpi_plugin_resources_resources_items`.`items_id`
                            AND `glpi_plugin_resources_resources_items`.`itemtype` = 'User')
                   WHERE `glpi_plugin_resources_resources`.`id` = '$ID' 
                   GROUP BY `glpi_plugin_resources_resources`.`id`";
         $result = $DB->query($query);

         if ($link==2) {
            $user = array("name"    => "",
                          "comment" => "",
                          "link"    => "");
         }

         if ($DB->numrows($result)==1) {
            $data     = $DB->fetch_assoc($result);
            $username = formatUserName($data["id"], $data["username"], $data["name"],
                                       $data["firstname"], $link);

            if ($link==2) {
               $user["name"]    = $username;
               $user["link"]    = $CFG_GLPI["root_doc"]."/plugins/resources/front/resource.form.php?id=".$ID;
               $user["comment"] = "";

               if (isset($data["picture"]) && !empty($data["picture"])) {
                  $path = GLPI_PLUGIN_DOC_DIR."/resources/".$data["picture"];
                  if (file_exists($path)) {
                     $user["comment"] .="<object data='".$CFG_GLPI['root_doc']."/plugins/resources/front/picture.send.php?file=".$data["picture"]."'>
                      <param name='src' value='".$CFG_GLPI['root_doc'].
                        "/plugins/resources/front/picture.send.php?file=".$data["picture"]."'>
                     </object><br> ";

                  } else {
                     $user["comment"] .="<img src='".$CFG_GLPI['root_doc']."/plugins/resources/pics/nobody.png'><br>";
                  }
               } else {
                  $user["comment"] .="<img src='".$CFG_GLPI['root_doc']."/plugins/resources/pics/nobody.png'><br>";
               }

               $user["comment"] .= __('Name')."&nbsp;: ".$username."<br>";

               if ($data["plugin_resources_ranks_id"]>0) {
                  $user["comment"] .= PluginResourcesRank::getTypeName(1)."&nbsp;: ".
                                      Dropdown::getDropdownName("glpi_plugin_resources_ranks",
                                                                $data["plugin_resources_ranks_id"])."<br>";
               }

               if ($data["locations_id"]>0) {
                  $user["comment"] .= __('Location')."&nbsp;: ".
                                      Dropdown::getDropdownName("glpi_locations",
                                                                $data["locations_id"])."<br>";
               }

               if($data["registration_number"]>0) {
                  $user["comment"] .= __('Administrative number')."&nbsp;: ".
                                      $data["registration_number"]."<br>";
               }

            } else {
               $user = $username;
            }
         }
      }
      return $user;
   }

   /**
    * Permet l'affichage dynamique des ressources avec info bulle
    *
    * @static
    * @param array ($myname,$value,$entity_restrict)
    */

   static function dropdown($options=array()) {
      global $CFG_GLPI;

      $params['value']            = 0;
      $params['valuename']        = Dropdown::EMPTY_VALUE;
      $params['customcomments']   = true;
      $params['comments']         = false;
      $params['entity']           = $_SESSION['glpiactive_entity'];
      $params['name']             = 'plugin_resources_resources_id';
      $params['addUnlinkedUsers'] = false;
      $params['rand']             = mt_rand();
      
      if (!empty($options)) {
         foreach ($options as $key => $val) {
            $params[$key] = $val;
         }
      }
      
      $params['value2'] = $params['value'];
      if (!empty($params['value'])) {
         $params['valuename'] = Dropdown::getDropdownName(self::getTable(), $params['value']);
      }

      $field_id = Html::cleanId("dropdown_".$params['name'].$params['rand']);

      $item = new self();
      echo Html::jsAjaxDropdown($params['name'], $field_id,
                               $CFG_GLPI['root_doc']."/plugins/resources/ajax/dropdownResources.php",
                               $params);
      if (class_exists('PluginPositionsPosition')) {
         PluginPositionsPosition::showGeolocLink('PluginResourcesResource',$params["value"]);
      }
            // Display comment
      if ($params['customcomments']) {
         $table = $item->getTable();
         $user = self::getResourceName($params['value'],2);
         
         $comment_id      = Html::cleanId("comment_".$params['name'].$params['rand']);
         $link_id         = Html::cleanId("comment_link_".$params['name'].$params['rand']);

         if (empty($user["link"])) {
            $user["link"] = $CFG_GLPI['root_doc']."/plugins/resources/front/resource.php";
         }
         
         echo "&nbsp;";
         Html::showToolTip($user["comment"],
                           array('contentid'  => $comment_id,
                                 'link'       => $user["link"],
                                 'linkid'     => $link_id,
                                 'linktarget' => '_blank'));
         
         
         $paramscomment = array('value' => '__VALUE__',
                                'table' => $table);
         if ($item->canView()) {
            $paramscomment['withlink'] = $link_id;
         }

         echo Ajax::updateItemOnSelectEvent($field_id, $comment_id,
                                            $CFG_GLPI["root_doc"]."/plugins/resources/ajax/comments.php",
                                            $paramscomment, false);
         
         return $params['rand'];
      }
      
//      // Default values
//      $p['name']           = 'plugin_resources_resources_id';
//      $p['value']          = '';
//      $p['all']            = 0;
//      $p['on_change']      = '';
//      $p['comments']       = 1;
//      $p['entity']         = -1;
//      $p['entity_sons']    = false;
//      $p['used']           = array();
//      $p['toupdate']       = '';
//      $p['rand']           = mt_rand();
//      $p['plugin_resources_contracttypes_id'] = 0;
//
//      if (is_array($options) && count($options)) {
//         foreach ($options as $key => $val) {
//            $p[$key] = $val;
//         }
//      }
//
//      if (!($p['entity']<0) && $p['entity_sons']) {
//         if (is_array($p['entity'])) {
//            echo "entity_sons options is not available with array of entity";
//         } else {
//            $p['entity'] = getSonsOf('glpi_entities',$p['entity']);
//         }
//      }
//
//      // Make a select box with all glpi users
//      $use_ajax = false;
//
//      if ($CFG_GLPI["use_ajax"]) {
//         $res = self::getSqlSearchResult (true, $p['entity'], $p['value'], $p['used'],'',$p['plugin_resources_contracttypes_id']);
//         $nb = ($res ? $DB->result($res,0,"cpt") : 0);
//         if ($nb > $CFG_GLPI["ajax_limit_count"]) {
//            $use_ajax = true;
//         }
//      }
//      $user = self::getResourceName($p['value'],2);
//
//      $default_display  = "<select id='dropdown_".$p['name'].$p['rand']."' name='".$p['name']."'>";
//      $default_display .= "<option value='".$p['value']."'>";
//      $default_display .= Toolbox::substr($user["name"], 0, $_SESSION["glpidropdown_chars_limit"]);
//      $default_display .= "</option></select>";
//
//      //$view_users = (Session::haveRight("user", "r"));
//
//      $params = array('searchText'       => '__VALUE__',
//                      'value'            => $p['value'],
//                      'myname'           => $p['name'],
//                      'all'              => $p['all'],
//                      'comment'          => $p['comments'],
//                      'rand'             => $p['rand'],
//                      'on_change'        => $p['on_change'],
//                      'entity_restrict'  => $p['entity'],
//                      'used'             => $p['used'],
//                      'update_item'      => $p['toupdate'],
//                      'plugin_resources_contracttypes_id' => $p['plugin_resources_contracttypes_id']);
//
//      $default = "";
//      if (!empty($p['value']) && $p['value']>0) {
//         $default = $default_display;
//
//      } else {
//         $default = "<select name='".$p['name']."' id='dropdown_".$p['name'].$p['rand']."'>";
//         if ($p['all']) {
//            $default.= "<option value='0'>[ ".__('All')." ]</option></select>";
//         } else {
//            $default.= "<option value='0'>".Dropdown::EMPTY_VALUE."</option></select>\n";
//         }
//      }
//      Ajax::dropdown($use_ajax, "/plugins/resources/ajax/dropdownResources.php", $params, $default, $p['rand']);
//      
//      if (class_exists('PluginPositionsPosition')) {
//         PluginPositionsPosition::showGeolocLink('PluginResourcesResource',$params["value"]);
//      }
//      // Display comment
//      if ($p['comments']) {
//         if (empty($user["link"])) {
//            $user["link"] = $CFG_GLPI['root_doc']."/plugins/resources/front/resource.php";
//         }
//         Html::showToolTip($user["comment"],
//                           array('contentid' => "comment_".$p['name'].$p['rand'],
//                                 'link'      => $user["link"],
//                                 'linkid'    => "comment_link_".$p["name"].$p['rand']));
//      }
//
//      return $p['rand'];
   }
   
   static function fastResourceAddForm(){
      
      echo "<table class='tab_cadre'>";
      // ContractType
      echo "<tr>";
      echo "<td>".__("Contract type")."</td>";
      echo "<td>";

      PluginResourcesDepartment::getTypeName(1)."</td><td>";
      Dropdown::show('PluginResourcesContractType',
         array('name' => "plugin_resources_contracttypes_id"));

      echo "</td>";
      echo "</tr>";
      echo "<tr>";

      // Recipient
      echo "<td>";
      echo __('Resource manager', 'resources')."</td>";
      echo "<td width='70%'>";
      User::dropdown(array('name'  => "users_id_recipient",
                           'entity' => $_SESSION['glpiactive_entity'],
                           'right'  => 'all'));
      echo "<td>";
      echo "</tr>";

      // Department
      echo "<tr>";
      echo "<td>";
      echo PluginResourcesDepartment::getTypeName(1)."</td><td>";
      Dropdown::show('PluginResourcesDepartment',
         array('name' => "plugin_resources_departments_id"));
      
      echo '<input type="hidden" name="itemtype" value="User">';
      echo "</td>";
      echo "</tr>"; 
      echo "</table>";
   }
   
   static function fastResourceAdd($userId, $options) {
      global $DB;

      $params['plugin_resources_contracttypes_id'] = 0;
      $params['plugin_resources_departments_id']   = 0;
      $params['users_id_recipient']                = 0;
      $params['itemtype']                          = 'User';
      $params['entities_id']                       = $_SESSION['glpiactive_entity'];

      foreach ($options as $key => $val) {
         $params[$key] = $val;
      }

      $message        = null;
      $idResource     = 0;
      $error['right'] = 0;
      $error['error'] = 0;

      $user = new User();
      if ($user->getFromDB($userId)) {
         $resource = new PluginResourcesResource();
         $query    = "WHERE name='".$user->fields['realname']."' AND firstname='".$user->fields['firstname']."' AND is_deleted=0";
         $resource->getFromDBByQuery($query);

         if (!isset($resource->fields['id']) || $resource->fields['id'] <= 0) {
            $resource->fields['entities_id'] = $params['entities_id'];
            $resource->fields['name']        = isset($user->fields['realname']) ? $user->fields['realname'] : '';
            $resource->fields['firstname']   = isset($user->fields['firstname']) ? $user->fields['firstname'] : '';

            $resource->fields['plugin_resources_contracttypes_id'] = $params['plugin_resources_contracttypes_id'];
            $resource->fields['users_id_recipient']                = Session::getLoginUserID();
            $resource->fields['users_id']                          = $params["users_id_recipient"];

            $resource->fields['date_declaration'] = date('Y-m-d');
            $resource->fields['date_begin']       = null;
            $resource->fields['date_end']         = null;

            $resource->fields['plugin_resources_departments_id'] = $params['plugin_resources_departments_id'];
            $resource->fields['locations_id']                    = 0;
            $resource->fields['is_leaving']                      = 0;
            $resource->fields['users_id_recipient_leaving']      = 0;
            $resource->fields['comment']                         = '';
            $resource->fields['notepad']                         = '';
            $resource->fields['is_template']                     = 0;
            $resource->fields['template_name']                   = '';
            $resource->fields['is_deleted']                      = 0;
            $resource->fields['is_helpdesk_visible']             = 1;
            $resource->fields['date_mod']                        = date('Y-m-d');

            $resource->fields['plugin_resources_resourcestates_id']       = 0;
            $resource->fields['picture']                                  = null;
            $resource->fields['is_recursive']                             = 0;
            $resource->fields['quota']                                    = 1;
            $resource->fields['plugin_resources_resourcesituations_id']   = 0;
            $resource->fields['plugin_resources_contractnatures_id']      = 0;
            $resource->fields['plugin_resources_ranks_id']                = 0;
            $resource->fields['plugin_resources_resourcespecialities_id'] = 0;
            $resource->fields['plugin_resources_leavingreasons_id']       = 0;

            $resourceItem = new PluginResourcesResource_Item();
            if ($resourceItem->can(-1, UPDATE, $input)) {
               $idResource = $resource->add($resource->fields);
               if ($idResource) {
                  $resource->fields['id'] = $idResource;
                  if (isset($resourceItem->fields['id']))
                     unset($resourceItem->fields['id']);

                  $resourceItem->fields['plugin_resources_resources_id'] = $idResource;
                  $resourceItem->fields['items_id']                      = $user->fields['id'];
                  $resourceItem->fields['itemtype']                      = $params['itemtype'];
                  $resourceItem->fields['comment']                       = null;

                  $idResourceItem = $resourceItem->add($resourceItem->fields);
                  if ($idResourceItem) {
                     // Cochage des checklist en mode "JOB DONE"
                     $pChecklist = new PluginResourcesChecklist();

                     $query = "UPDATE ".$pChecklist->getTable()." SET `is_checked`=1 WHERE `plugin_resources_resources_id`=".$idResource;
                     if ($DB->query($query)) {
                        $message = $user->fields['realname']." ".$user->fields['firstname']."<br/>";
                     }
                  } else {
                     $error['error'] = 1;
                     $message        = $user->fields['realname']." ".$user->fields['firstname']."<br/>";
                     $resource->delete($resource->fields, 1);
                  }
               } else {
                  $error['error'] = 1;
               }
            } else {
               $error['right'] = 1;
            }
         } else {
            $error['error'] = 1;
            $message        = $user->fields['realname']." ".$user->fields['firstname']."<br/>";
         }
      } else {
         $error['error'] = 1;
      }

      return array($idResource, $error, $message);
   }

   static function getSqlSearchResult ($count=true, $entity_restrict=-1, $value=0, $used=array(), $search='', $showOnlyLinkedResources=false) {
      global $DB, $CFG_GLPI;
      
      // No entity define : use active ones
      if ($entity_restrict < 0) {
         $entity_restrict = $_SESSION["glpiactiveentities"];
      }

      $joinprofile = false;

      $where = " `glpi_plugin_resources_resources`.`is_deleted` = '0'
                  AND `glpi_plugin_resources_resources`.`is_leaving` = '0'
                  AND `glpi_plugin_resources_resources`.`is_template` = '0' ";


      $where.= getEntitiesRestrictRequest('AND','glpi_plugin_resources_resources','',$entity_restrict,true);
      if ((is_numeric($value) && $value)
      || count($used)) {

         $where .= " AND `glpi_plugin_resources_resources`.`id` NOT IN (0";
         if (is_numeric($value)) {
            $first = false;
            $where .= $value;
         } else {
            $first = true;
         }
         if(is_array($used)){
            foreach ($used as $val) {
               if ($first) {
                  $first = false;
               } else {
                  $where .= ",";
               }
               $where .= $val;
            }
         }
         $where .= ")";
      }

      if ($count) {
         $query = "SELECT COUNT(DISTINCT `glpi_plugin_resources_resources`.`id` ) AS cpt
                   FROM `glpi_plugin_resources_resources` ";
      } else {
         $query = "SELECT DISTINCT `glpi_plugin_resources_resources`.*,
                          `glpi_users`.`registration_number`,
                          `glpi_users`.`name` AS username,
                          `glpi_users`.`id` AS userid
                   FROM `glpi_plugin_resources_resources`";
         if ($showOnlyLinkedResources) {
            $query .= "INNER JOIN `glpi_plugin_resources_resources_items`
                        ON (`glpi_plugin_resources_resources_items`.`plugin_resources_resources_id`
                            = `glpi_plugin_resources_resources`.`id`)
                      INNER JOIN `glpi_users`
                        ON (`glpi_users`.`id` = `glpi_plugin_resources_resources_items`.`items_id`
                              AND `glpi_plugin_resources_resources_items`.`itemtype` = 'User') ";
         } else {
            $query .= "LEFT JOIN `glpi_plugin_resources_resources_items`
                        ON (`glpi_plugin_resources_resources_items`.`plugin_resources_resources_id`
                            = `glpi_plugin_resources_resources`.`id`)
                      LEFT JOIN `glpi_users`
                        ON (`glpi_users`.`id` = `glpi_plugin_resources_resources_items`.`items_id`
                              AND `glpi_plugin_resources_resources_items`.`itemtype` = 'User') ";
         }
      }

      if ($count) {
         $query .= " WHERE $where ";
      } else {
         if (strlen($search)>0 && $search!=$CFG_GLPI["ajax_wildcard"]) {
            $where .= " AND (`glpi_plugin_resources_resources`.`name` ".Search::makeTextSearch($search)."
                             OR `glpi_plugin_resources_resources`.`firstname` ".Search::makeTextSearch($search)."
                             OR `glpi_users`.`registration_number` ".Search::makeTextSearch($search)."
                             OR `glpi_users`.`name` ".Search::makeTextSearch($search)."
                             OR CONCAT(`glpi_plugin_resources_resources`.`name`,' ',`glpi_plugin_resources_resources`.`firstname`,' ',`glpi_users`.`registration_number`,' ',`glpi_users`.`name`) ".
                                       Search::makeTextSearch($search).")";
         }
         $query .= " WHERE $where ";

         if ($_SESSION["glpinames_format"] == User::FIRSTNAME_BEFORE) {
            $query.=" ORDER BY `glpi_plugin_resources_resources`.`firstname`,
                               `glpi_plugin_resources_resources`.`name` ";
         } else {
            $query.=" ORDER BY `glpi_plugin_resources_resources`.`firstname`,
                               `glpi_plugin_resources_resources`.`name` ";
         }

         if ($search != $CFG_GLPI["ajax_wildcard"]) {
            $query .= " LIMIT 0,".$CFG_GLPI["dropdown_max"];
         }
      }
      return $DB->query($query);
   }


   function listOfTemplates($target,$add=0) {

      $restrict = "`is_template` = '1'";
      $restrict.=getEntitiesRestrictRequest(" AND ",$this->getTable(),'','',$this->maybeRecursive());
      $restrict.=" ORDER BY `name`";
      $templates = getAllDatasFromTable($this->getTable(),$restrict);

      if (Session::isMultiEntitiesMode()) {
         $colsup=1;
      } else {
         $colsup=0;
      }

      echo "<div align='center'><table class='tab_cadre_fixe'>";
      if ($add) {
         echo "<tr><th colspan='".(2+$colsup)."'>".__('Choose a template')." - ".self::getTypeName(2)."</th>";
      } else {
         echo "<tr><th colspan='".(2+$colsup)."'>".__('Templates')." - ".self::getTypeName(2)."</th>";
      }

      echo "</tr>";
      if ($add) {

         echo "<tr>";
         echo "<td colspan='".(2+$colsup)."' class='center tab_bg_1'>";
         echo "<a href=\"$target?id=-1&amp;withtemplate=2\">&nbsp;&nbsp;&nbsp;" . __('Blank Template') . "&nbsp;&nbsp;&nbsp;</a></td>";
         echo "</tr>";
      }

      foreach ($templates as $template) {

         $templname = $template["template_name"];
         if ($_SESSION["glpiis_ids_visible"]||empty($template["template_name"]))
         $templname.= "(".$template["id"].")";

         echo "<tr>";
         echo "<td class='center tab_bg_1'>";
         if (!$add) {
            echo "<a href=\"$target?id=".$template["id"]."&amp;withtemplate=1\">&nbsp;&nbsp;&nbsp;$templname&nbsp;&nbsp;&nbsp;</a></td>";

            if (Session::isMultiEntitiesMode()) {
               echo "<td class='center tab_bg_2'>";
               echo Dropdown::getDropdownName("glpi_entities",$template['entities_id']);
               echo "</td>";
            }
            echo "<td class='center tab_bg_2'>";
            Html::showSimpleForm($target,
                                    'purge',
                                    _x('button', 'Delete permanently'),
                                    array('id' => $template["id"],'withtemplate'=>1));
            echo "</td>";

         } else {
            echo "<a href=\"$target?id=".$template["id"]."&amp;withtemplate=2\">&nbsp;&nbsp;&nbsp;$templname&nbsp;&nbsp;&nbsp;</a></td>";

            if (Session::isMultiEntitiesMode()) {
               echo "<td class='center tab_bg_2'>";
               echo Dropdown::getDropdownName("glpi_entities",$template['entities_id']);
               echo "</td>";
            }
         }
         echo "</tr>";
      }
      if (!$add) {
         echo "<tr>";
         echo "<td colspan='".(2+$colsup)."' class='tab_bg_2 center'>";
         echo "<b><a href=\"$target?withtemplate=1\">".__('Add a template...')."</a></b>";
         echo "</td>";
         echo "</tr>";
      }
      echo "</table></div>";
   }

   //Show form from heelpdesk to remove a resource
   function showResourcesToRemove() {
      global $CFG_GLPI;

      if (countElementsInTable($this->getTable())>0) {
         
         echo "<div align='center'>";

         echo "<form method='post' action=\"".$CFG_GLPI["root_doc"]."/plugins/resources/front/resource.remove.php\">";

         echo "<table class='plugin_resources_wizard' style='margin-top:1px;'>";
         echo "<tr>";
         echo "<td class='plugin_resources_wizard_left_area' valign='top'>";
         echo "<div class='plugin_resources_presentation_logo'>";
         echo "<img src='".$CFG_GLPI['root_doc']."/plugins/resources/pics/removeresource.png' alt='removeresource' /></div>";
         echo "</td>";

         echo "<td class='plugin_resources_wizard_right_area' style='width:500px' valign='top'>";

         echo "<div class='plugin_resources_wizard_title'>";
         _e('Declare a departure', 'resources');
         echo "</div>";

         echo "<table>";
         echo "<tr class='plugin_resources_wizard_explain'>";
         echo "<td style='width:40%'>".self::getTypeName(1)."</td>";
         
         echo "<td class='left'>";
         PluginResourcesResource::dropdown(array('name'   => 'plugin_resources_resources_id',
                                                'entity' => $_SESSION['glpiactiveentities']));

         echo "</td></tr>";
         echo "<tr class='plugin_resources_wizard_explain'><td>";
         echo __('Departure date', 'resources')."</td>";
         echo "<td class='left'>";
         Html::showDateFormItem("date_end",$_POST["date_end"],true,true);
         echo "</td></tr>";

         echo "<tr class='plugin_resources_wizard_explain'><td>";
         echo PluginResourcesLeavingReason::getTypeName(1)."</td>";
         echo "<td class='left'>";
         Dropdown::show('PluginResourcesLeavingReason',
                     array('entity' => $_SESSION['glpiactiveentities']));
         echo "</td></tr>";
         
         echo "</table>";
         echo "</td>";
         echo "</tr>";

         echo "<tr><td class='plugin_resources_wizard_button' colspan='2'>";
         echo "<div class='next'>";
         echo "<input type='submit' name='removeresources' value=\"".__s('Declare a departure', 'resources')."\" class='submit'>";
         echo "</div>";
         echo "</td></tr></table>";
         Html::closeForm();
         echo "</div>";
         
      } else {
         echo "<div align='center'>".__('No item found')."</div>";
      }
   }
   
   //Show form from heelpdesk to transfer a resource
   function showResourcesToTransfer() {
      global $CFG_GLPI;

      if (countElementsInTable($this->getTable())>0) {
         echo "<div align='center'>";
         
         echo "<form method='post' action=\"".$CFG_GLPI["root_doc"]."/plugins/resources/front/resource.transfer.php\">";

         echo "<table class='plugin_resources_wizard' style='margin-top:1px;'>";
         echo "<tr>";
         echo "<td class='plugin_resources_wizard_left_area' valign='top'>";
         echo "<div class='plugin_resources_presentation_logo'>";
         echo "<img src='".$CFG_GLPI['root_doc']."/plugins/resources/pics/transferresource.png'/>";
         echo "</div>";
         echo "</td>";
         
         echo "<td class='plugin_resources_wizard_right_area' style='width:500px' valign='top'>";
         echo "<div class='plugin_resources_wizard_title'>";
         _e('Declare a transfer', 'resources');
         echo "</div>";
         echo "<table>";
         echo "<tr class='plugin_resources_wizard_explain'>";
         echo "<td style='width:40%' valign='top'>".self::getTypeName(1)." <span class='red'>*</span></td>";
         echo "<td>";
         $rand = PluginResourcesResource::dropdown(array('name'             => 'plugin_resources_resources_id',
                                                         'on_change'        => 'loadResourceInfo();',
                                                         'entity'           => $_SESSION['glpiactiveentities'],
                                                         'addUnlinkedUsers' => true));
         echo "<script type='text/javascript'>";
         echo "function loadResourceInfo(){";
         echo Ajax::updateItemJsCode('loadResourceInfo', 
                                     $CFG_GLPI['root_doc'].'/plugins/resources/ajax/resourceinfo.php', 
                                     array('plugin_resources_resources_id' => '__VALUE__',
                                           'action'                        => 'resourceInfo'),
                                     'dropdown_plugin_resources_resources_id'.$rand);
         echo "}";
         echo "</script>";
         echo "<span id='loadResourceInfo'></span>";
         echo "</td>";
         echo "</tr>";
                  
         echo "<tr class='plugin_resources_wizard_explain'>";
         echo "<td style='width:40%'>".__('Target entity', 'resources')." <span class='red'>*</span></td>";
         echo "<td>";
         $transferentity = new PluginResourcesTransferEntity();
         $data           = $transferentity->find();
         $elements       = array(Dropdown::EMPTY_VALUE);
         foreach ($data as $val) {
            $elements[$val['entities_id']] = Dropdown::getDropdownName("glpi_entities", $val['entities_id']);
         }
         Dropdown::showFromArray("entities_id", $elements);
         echo "</td>";
         echo "</tr>";
         echo "</table>";
         echo "</td>";
         echo "</tr>";

         echo "<tr>";
         echo "<td class='plugin_resources_wizard_button' colspan='2'>";
         echo "<div class='next'>";
         echo "<input type='submit' name='transferresources' value=\"".__s('Declare a transfer', 'resources')."\" class='submit'>";
         echo "</div>";
         echo "</td>";
         echo "</tr>";
         echo "</table>";
         Html::closeForm();
         echo "</div>";
         
      } else {
         echo "<div align='center'>".__('No item found')."</div>";
      }
   }
   
   /**
    * Massive actions to be added
    * 
    * @param type $type
    * @return $action
    */
   function massiveActions($type) {
      
      $prefix = $this->getType().MassiveAction::CLASS_ACTION_SEPARATOR;
      
      $action[$prefix."plugin_resources_add_item"] = __('Associate a resource', 'resources');
      if ($type == "User") {
         $action[$prefix."plugin_resources_generate_resources"] = __('Generate resources', 'resources');
      }
      return $action;
   }
   
   /**
    * Get the specific massive actions
    * 
    * @since version 0.84
    * @param $checkitem link item to check right   (default NULL)
    * 
    * @return an array of massive actions
    * */
   function getSpecificMassiveActions($checkitem = NULL) {
      $isadmin = static::canUpdate();
      $actions = parent::getSpecificMassiveActions($checkitem);

      if ($isadmin && $_SESSION['glpiactiveprofile']['interface'] == 'central') {
         $actions['PluginResourcesResource'.MassiveAction::CLASS_ACTION_SEPARATOR.'Install'] = __('Associate');
         $actions['PluginResourcesResource'.MassiveAction::CLASS_ACTION_SEPARATOR.'Desinstall'] = __('Dissociate');

         if (Session::haveRight('transfer', READ)
            && Session::isMultiEntitiesMode()) {
            $actions['PluginResourcesResource'.MassiveAction::CLASS_ACTION_SEPARATOR.'Transfert'] = __('Transfer');
         }
       
         $actions['PluginResourcesResource'.MassiveAction::CLASS_ACTION_SEPARATOR.'Send'] = __('Send a notification');
      }
      return $actions;
   }

   static function showMassiveActionsSubForm(MassiveAction $ma) {
      $itemtype = $ma->getItemtype(false);
      switch ($ma->getAction()) {
         case "Install" :
            Dropdown::showAllItems("item_item", 0, 0, -1, self::getTypes());
            break;
         case "Desinstall" :
            Dropdown::showAllItems("item_item", 0, 0, -1, self::getTypes());
            break;
         case "Transfert" :
            Dropdown::show('Entity');
            break;
         case "plugin_resources_add_item":
            echo "<input type='hidden' name='itemtype' value='$itemtype'>";
            PluginResourcesResource::dropdown();
            break;
         case "plugin_resources_generate_resources":
            echo "<input type='hidden' name='itemtype' value='$itemtype'>";
            PluginResourcesResource::fastResourceAddForm();
            break;
      }

      return parent::showMassiveActionsSubForm($ma);
   }

   /**
    * @since version 0.85
    *
    * @see CommonDBTM::processMassiveActionsForOneItemtype()
    * */
   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids) {

      $input         = $ma->getInput();
      $resource_item = new PluginResourcesResource_Item();
      $resource      = new PluginResourcesResource();
      $itemtype      = $ma->getItemtype(false);

      switch ($ma->getAction()) {
         case "Transfert" :
            if ($itemtype == 'PluginResourcesResource') {
               foreach ($ids as $key => $val) {
                  if ($item->transferResource($key, $input['entities_id'])) {
                     $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                  } else {
                     $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                  }
               }
            }
            break;

         case "Install" :
            foreach ($ids as $key => $val) {
               if ($item->can($key, UPDATE)) {
                  $values = array('plugin_resources_resources_id' => $key,
                                 'items_id'                      => $input["item_item"],
                                 'itemtype'                      => $input['itemtype']);
                  if ($resource_item->add($values)) {
                     $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                  } else {
                     $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                  }
               } else {
                  $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                  $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
               }
            }
            break;

         case "Desinstall" :
            foreach ($ids as $key => $val) {
               if ($resource_item->deleteItemByResourcesAndItem($key, $input['item_item'], $input['itemtype'])) {
                  $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
               } else {
                  $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
               }
            }
            break;

         case "Send" :
            if ($resource->sendEmail($ids)) {
               $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_OK);
            } else {
               $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_KO);
            }
            break;
            
         case "plugin_resources_add_item":
            $messages = array();
            foreach ($ids as $key => $val) {
               if ($item->can($key, UPDATE)) {
                  $input = array('plugin_resources_resources_id' => $input['plugin_resources_resources_id'],
                                 'items_id'                      => $key,
                                 'itemtype'                      => $input['itemtype']);

                  if ($resource_item->add($input)) {
                     $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                     $messages[] =_n("This resource has been added", "These resources have been added", 2, "resources");
                  } else {
                     $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                     $messages[] =_n("This resource aldready exists", "These resources aldready exist", 2, "resources");
                  }
               } else {
                  $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                  $messages[] = $item->getErrorMessage(ERROR_RIGHT);
               }
            }
            $ma->addMessage(implode("<br>", array_unique($messages)));
            break;

         case "plugin_resources_generate_resources":
            $messages = array();
            if (sizeof($input['itemtype']) > 0) {
               foreach ($ids as $key => $val) {
                  list($id, $error, $message) = PluginResourcesResource::fastResourceAdd($key, $input);
                  if ($error['right']) {
                     $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                     $messages[] = $item->getErrorMessage(ERROR_RIGHT);
                  } else if ($error['error']) {
                     $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                     $messages[] = _n("This resource aldready exists", "These resources aldready exist", 2, "resources")."<br>".$message;
                  } else {
                     $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                     $messages[] = _n("This resource has been added", "These resources have been added", 2, "resources")."<br>".$message;
                  }
               }
            }
            $ma->addMessage(implode("<br>", array_unique($messages)));
            break;

         default :
            return parent::doSpecificMassiveActions($input);
      }
   }
   
    /**
    * Transfer resource
    * 
    * @param type $resources_id
    * @param type $entities_id
    * @return boolean
    */
   function transferResource($resources_id, $entities_id, $options=array()) {
      global $DB;

      $params['users_id']          = 0;
      $params['itemtype']          = 'User';
      $params['link_resources_id'] = 0;

      foreach($options as $key => $val){
         $params[$key] = $val;
      }
      
      $resource_item = new PluginResourcesResource_Item();
      
      if (strstr($resources_id, 'users')) {
         list($tag, $users_id) = explode('-', $resources_id);
      }
      
      $resourceOk = $this->getFromDB($resources_id);
      $source_entity = $this->fields['entities_id'];

      if (!$resourceOk) {
         // Link user to resource
         if (!empty($params['link_resources_id'])) {
            $input = array('plugin_resources_resources_id' => $params['link_resources_id'],
                           'items_id'                      => $users_id,
                           'itemtype'                      => $params['itemtype']);
            if ($resource_item->can(-1, 'w', $input)) {
               $resourceOk = $resource_item->add($input);
            }
            $resources_id = $params['link_resources_id'];
            $resourceOk   = $this->getFromDB($resources_id);
            
         // Add resource   
         } else {
            list($resources_id, $error, $message) = self::fastResourceAdd($users_id, $params);
            if ($error['error'] || $error['right']) {
               $resourceOk = false;
            } else {
               $resourceOk = $this->getFromDB($resources_id);
            }
         }
      }
 
      if ($resourceOk) {
         // Link to a user if needed
         if (!empty($params['users_id'])) {
            $input = array('plugin_resources_resources_id' => $resources_id,
                           'items_id'                      => $params['users_id'],
                           'itemtype'                      => $params['itemtype']);
            if ($resource_item->can(-1, 'w', $input)) {
               $resource_item->add($input);
            }
         }

         $contracttype = PluginResourcesContractType::transfer($this->fields["plugin_resources_contracttypes_id"], $entities_id);
         if ($contracttype > 0) {
            $values["id"]                                = $resources_id;
            $values["plugin_resources_contracttypes_id"] = $contracttype;
            $this->update($values);
         }

         unset($values);

         $resourcestate = PluginResourcesResourceState::transfer($this->fields["plugin_resources_resourcestates_id"], $entities_id);
         if ($resourcestate > 0) {
            $values["id"]                                 = $resources_id;
            $values["plugin_resources_resourcestates_id"] = $resourcestate;
            $this->update($values);
         }

         unset($values);

         $department = PluginResourcesDepartment::transfer($this->fields["plugin_resources_departments_id"], $entities_id);
         if ($department > 0) {
            $values["id"]                              = $resources_id;
            $values["plugin_resources_departments_id"] = $department;
            $this->update($values);
         }

         unset($values);

         $situation = PluginResourcesResourceSituation::transfer($this->fields["plugin_resources_resourcesituations_id"], $entities_id);
         if ($situation > 0) {
            $values["id"]                                     = $resources_id;
            $values["plugin_resources_resourcesituations_id"] = $situation;
            $this->update($values);
         }

         unset($values);

         $contractnature = PluginResourcesContractNature::transfer($this->fields["plugin_resources_contractnatures_id"], $entities_id);
         if ($contractnature > 0) {
            $values["id"]                                  = $resources_id;
            $values["plugin_resources_contractnatures_id"] = $contractnature;
            $this->update($values);
         }
         unset($values);

         $rank = PluginResourcesRank::transfer($this->fields["plugin_resources_ranks_id"], $entities_id);
         if ($rank > 0) {
            $values["id"]                        = $resources_id;
            $values["plugin_resources_ranks_id"] = $rank;
            $this->update($values);
         }

         unset($values);

         $speciality = PluginResourcesResourceSpeciality::transfer($this->fields["plugin_resources_resourcespecialities_id"], $entities_id);
         if ($speciality > 0) {
            $values["id"]                                       = $resources_id;
            $values["plugin_resources_resourcespecialities_id"] = $speciality;
            $this->update($values);
         }
         unset($values);

         $PluginResourcesTask = new PluginResourcesTask();
         $restrict            = "`plugin_resources_resources_id` = '".$resources_id."'";
         $tasks               = getAllDatasFromTable("glpi_plugin_resources_tasks", $restrict);
         if (!empty($tasks)) {
            foreach ($tasks as $task) {
               $PluginResourcesTask->getFromDB($task["id"]);
               $tasktype = PluginResourcesTaskType::transfer($PluginResourcesTask->fields["plugin_resources_tasktypes_id"], $entities_id);
               if ($tasktype > 0) {
                  $values["id"]                            = $task["id"];
                  $values["plugin_resources_tasktypes_id"] = $tasktype;
                  $PluginResourcesTask->update($values);
               }
               $values["id"]          = $task["id"];
               $values["entities_id"] = $entities_id;
               $PluginResourcesTask->update($values);
            }
         }

         unset($values);

         $PluginResourcesEmployment = new PluginResourcesEmployment();
         $restrict                  = "`plugin_resources_resources_id` = '".$resources_id."'";
         $employments               = getAllDatasFromTable("glpi_plugin_resources_employments", $restrict);
         if (!empty($employments)) {
            foreach ($employments as $employment) {
               $PluginResourcesEmployment->getFromDB($employment["id"]);
               $rank = PluginResourcesRank::transfer($PluginResourcesEmployment->fields["plugin_resources_ranks_id"], $entities_id);
               if ($rank > 0) {
                  $values["id"]                        = $employment["id"];
                  $values["plugin_resources_ranks_id"] = $rank;
                  $PluginResourcesEmployment->update($values);
               }
               $PluginResourcesEmployment->getFromDB($employment["id"]);
               $profession = PluginResourcesProfession::transfer($PluginResourcesEmployment->fields["plugin_resources_professions_id"], $entities_id);
               if ($profession > 0) {
                  $values["id"]                              = $employment["id"];
                  $values["plugin_resources_professions_id"] = $profession;
                  $PluginResourcesEmployment->update($values);
               }
               $values["id"]          = $employment["id"];
               $values["entities_id"] = $entities_id;
               $PluginResourcesEmployment->update($values);
            }
         }

         unset($values);

         $PluginResourcesEmployee = new PluginResourcesEmployee();

         $restrict  = "`plugin_resources_resources_id` = '".$resources_id."'";
         $employees = getAllDatasFromTable("glpi_plugin_resources_employees", $restrict);
         if (!empty($employees)) {
            foreach ($employees as $employee) {
               $employer = PluginResourcesEmployer::transfer($employee["plugin_resources_employers_id"], $entities_id);
               if ($employer > 0) {
                  $values["id"]                            = $employee["id"];
                  $values["plugin_resources_employers_id"] = $employer;
                  $PluginResourcesEmployee->update($values);
               }

               $client = PluginResourcesClient::transfer($employee["plugin_resources_clients_id"], $entities_id);
               if ($client > 0) {
                  $values["id"]                          = $employee["id"];
                  $values["plugin_resources_clients_id"] = $client;
                  $PluginResourcesEmployee->update($values);
               }
            }
         }

         unset($values);

         $values["id"]          = $resources_id;
         $values["entities_id"] = $entities_id;
         if ($this->update($values)) {
            // Check list
            $checklist_exist = PluginResourcesChecklist::checkIfChecklistExist($resources_id);
            $checklistconfig = new PluginResourcesChecklistconfig();
            if ($checklist_exist) {
               $checklist = new PluginResourcesChecklist();
               $checklist->deleteByCriteria(array('plugin_resources_resources_id' => $resources_id, 
                                                  'checklist_type'                => PluginResourcesChecklist::RESOURCES_CHECKLIST_TRANSFER));
               $query = "UPDATE `glpi_plugin_resources_checklists`
                         SET `entities_id` = '".$entities_id."'
                         WHERE `plugin_resources_resources_id` ='$resources_id'";
               $DB->query($query);
            }
            $checklistconfig->addChecklistsFromRules($this, PluginResourcesChecklist::RESOURCES_CHECKLIST_TRANSFER);

            // Notification 
            $restrict = "`itemtype` = 'User' AND `plugin_resources_resources_id` = '".$resources_id."'";

            $data = getAllDatasFromTable('glpi_plugin_resources_resources_items', $restrict);

            if (!empty($data)) {
               $linkeduser = array();
               foreach ($data as $val) {
                  $linkeduser[$val['items_id']] = $val['items_id'];
               }
               $reportconfig = new PluginResourcesReportConfig();
               if ($reportconfig->getFromDBByResource($resources_id)) {
                  if ($reportconfig->fields['send_other_notif']) {
                     NotificationEvent::raiseEvent('other', $this, array('reports_id' => $reportconfig->fields['id']));
                  }
                  if ($reportconfig->fields['send_transfer_notif']) {
                     NotificationEvent::raiseEvent('transfer', $this, array('reports_id' => $reportconfig->fields['id'], 'users_id' => $linkeduser, 'source_entity' => $source_entity, 'target_entity' => $entities_id));
                  }
               }
            } else {
               Session::addMessageAfterRedirect(__('The notification is not sent because the resource is not linked with a user', 'resources'), true, ERROR);
            }

            Session::addMessageAfterRedirect(__('Declaration of resource transfer OK', 'resources'), true);
            return true;
         } 
      }
      
      return false;
   }
   
   /**
    * Show for PDF an resources
    *
    * @param $pdf object for the output
    * @param $ID of the resources
    */
   function show_PDF ($pdf) {

      $pdf->setColumnsSize(50,50);
      $col1 = '<b>'.__('ID').' '.$this->fields['id'].'</b>';
      if (isset($this->fields["date_declaration"])) {
         $users_id_recipient=new User();
         $users_id_recipient->getFromDB($this->fields["users_id_recipient"]);
         $col2 = __('Request date').' : '.Html::convDateTime($this->fields["date_declaration"]).' '.__('Requester').' '.$users_id_recipient->getName();
      } else {
         $col2 = '';
      }
      $pdf->displayTitle($col1, $col2);

      $pdf->displayLine(
         '<b><i>'.__('Surname').' :</i></b> '.$this->fields['name'],
         '<b><i>'.__('First name').' :</i></b> '.$this->fields['firstname']);
      $pdf->displayLine(
         '<b><i>'.__('Location').' :</i></b> '.Html::clean(Dropdown::getDropdownName('glpi_locations',$this->fields['locations_id'])),
         '<b><i>'.PluginResourcesContractType::getTypeName(1).' :</i></b> '.Html::clean(Dropdown::getDropdownName('glpi_plugin_resources_contracttypes',$this->fields['plugin_resources_contracttypes_id'])));

      $pdf->displayLine(
         '<b><i>'.__('Resource manager', 'resources').' :</i></b> '.Html::clean(getusername($this->fields["users_id"])),
         '<b><i>'.PluginResourcesDepartment::getTypeName(1).' :</i></b> '.Html::clean(Dropdown::getDropdownName('glpi_plugin_resources_departments',$this->fields["plugin_resources_departments_id"])));

      $pdf->displayLine(
         '<b><i>'.__('Arrival date', 'resources').' :</i></b> '.Html::convDate($this->fields["date_begin"]),
         '<b><i>'.__('Departure date', 'resources').' :</i></b> '.Html::convDate($this->fields["date_end"]));

      $pdf->setColumnsSize(100);

      $pdf->displayText('<b><i>'.__('Description').' :</i></b>', $this->fields['comment']);

      $pdf->displaySpace();
   }

   // Cron action
   static function cronInfo($name) {

      switch ($name) {
         case 'Resources':
            return array (
               'description' => __('Resources not declaring leaving', 'resources'));   // Optional
            break;
      }
      return array();
   }

   function queryAlert() {

      $first=false;
      $date=date("Y-m-d H:i:s");
      $query = "SELECT *
            FROM `".$this->getTable()."`
            WHERE `date_end` IS NOT NULL
            AND `date_end` <= '".$date."'
            AND `is_leaving` != '1'";

      // Add Restrict templates
      if ($this->maybeTemplate()) {
         $LINK= " AND " ;
         if ($first) {$LINK=" ";$first=false;}
         $query.= $LINK."`".$this->getTable()."`.`is_template` = '0' ";
      }
      // Add is_deleted if item have it
      if ($this->maybeDeleted()) {
         $LINK= " AND " ;
         if ($first) {$LINK=" ";$first=false;}
         $query.= $LINK."`".$this->getTable()."`.`is_deleted` = '0' ";
      }

      return $query;

   }

   /**
    * Cron action on tasks : LeavingResources
    *
    * @param $task for log, if NULL display
    *
    **/
   static function cronResources($task=NULL) {
      global $DB,$CFG_GLPI;

      if (!$CFG_GLPI["use_mailing"]) {
         return 0;
      }

      $message=array();
      $cron_status = 0;

      $resource = new self();
      $query_expired = $resource->queryAlert();

      $querys = array(Alert::END=>$query_expired);

      $task_infos = array();
      $task_messages = array();

      foreach ($querys as $type => $query) {
         $task_infos[$type] = array();
         foreach ($DB->request($query) as $data) {
            $entity = $data['entities_id'];
            $message = $data["name"]." ".$data["firstname"]." : ".
               Html::convDate($data["date_end"])."<br>\n";
            $task_infos[$type][$entity][] = $data;

            if (!isset($tasks_infos[$type][$entity])) {
               $task_messages[$type][$entity] = __('These resources have normally left the company', 'resources')."<br />";
            }
            $task_messages[$type][$entity] .= $message;
         }
      }

      foreach ($querys as $type => $query) {

         foreach ($task_infos[$type] as $entity => $resources) {
            Plugin::loadLang('resources');

            if (NotificationEvent::raiseEvent("AlertLeavingResources",
               new PluginResourcesResource(),
               array('entities_id'=>$entity,
                  'resources'=>$resources))) {
               $message = $task_messages[$type][$entity];
               $cron_status = 1;
               if ($task) {
                  $task->log(Dropdown::getDropdownName("glpi_entities",
                     $entity).":  $message\n");
                  $task->addVolume(1);
               } else {
                  Session::addMessageAfterRedirect(Dropdown::getDropdownName("glpi_entities",
                     $entity).":  $message");
               }

            } else {
               if ($task) {
                  $task->log(Dropdown::getDropdownName("glpi_entities",$entity).
                     ":  Send leaving resources alert failed\n");
               } else {
                  Session::addMessageAfterRedirect(Dropdown::getDropdownName("glpi_entities",$entity).
                     ":  Send leaving resources alert failed",false,ERROR);
               }
            }
         }
      }

      return $cron_status;
   }

   /**
    * Display entities of the loaded profile
    *
   * @param $myname select name
    * @param $target target for entity change action
    */
   static function showSelector($target) {
      global $CFG_GLPI;

      $rand=mt_rand();
      Plugin::loadLang('resources');
      echo "<div class='center' ><span class='b'>".__('Select the contract type', 'resources')."</span><br>";
      echo "<a style='font-size:14px;' href='".$target."?reset=reset' title=\"".
             __('Show all')."\">".str_replace(" ","&nbsp;",__('Show all'))."</a></div>";

      echo "<div class='left' style='width:100%'>";

      echo "<script type='javascript'>";
      echo "var Tree_Category_Loader$rand = new Ext.tree.TreeLoader({
         dataUrl:'".$CFG_GLPI["root_doc"]."/plugins/resources/ajax/resourcetreetypes.php'
      });";

      echo "var Tree_Category$rand = new Ext.tree.TreePanel({
         collapsible      : false,
         animCollapse     : false,
         border           : false,
         id               : 'tree_projectcategory$rand',
         el               : 'tree_projectcategory$rand',
         autoScroll       : true,
         animate          : false,
         enableDD         : true,
         containerScroll  : true,
         height           : 320,
         width            : 770,
         loader           : Tree_Category_Loader$rand,
         rootVisible     : false
      });";

      // SET the root node.
      echo "var Tree_Category_Root$rand = new Ext.tree.AsyncTreeNode({
         text     : '',
         draggable   : false,
         id    : '-1'                  // this IS the id of the startnode
      });
      Tree_Category$rand.setRootNode(Tree_Category_Root$rand);";

      // Render the tree.
      echo "Tree_Category$rand.render();
            Tree_Category_Root$rand.expand();";

      echo "</script>";

      echo "<div id='tree_projectcategory$rand' ></div>";
      echo "<script type='text/javascript'>";
      echo Html::jsGetElementbyID("tree_projectcategory$rand")."
              // call `.jstree` with the options object
              .jstree({
                  // the `plugins` array allows you to configure the active plugins on this instance
                  'plugins' : ['themes','json_data', 'search'],
                  'core' : {'load_open': true,
                            'html_titles': true,
                            'animation' : 0},
                  'themes' : {
                     'theme' : 'classic',
                     'url'   : '".$CFG_GLPI["root_doc"]."/css/jstree/style.css'
                  },
                  'search' : {
                     'case_insensitive' : true,
                     'show_only_matches' : true,
                     'ajax' : {
                        'type': 'POST',
                       'url' : '".$CFG_GLPI["root_doc"]."/plugins/resources/ajax/resourcetreetypes.php'
                     }
                   },
                  'json_data' : {
                'ajax' : {
                'type': 'POST',
                'url': function (node) {
                    var nodeId = '';
                    var url = ''
                    if (node == -1)
                    {
                        url = '".$CFG_GLPI["root_doc"]."/plugins/resources/ajax/resourcetreetypes.php?node=-1';
                    }
                    else
                    {
                        nodeId = node.attr('id');
                        url = '".$CFG_GLPI["root_doc"]."/plugins/resources/ajax/resourcetreetypes.php?node='+nodeId;
                    }

                    return url;
                },
                'success': function (new_data) {
                    //where new_data = node children
                    //e.g.: [{'data':'Hardware','attr':{'id':'child2'}},
                    //         {'data':'Software','attr':{'id':'child3'}}]
                    return new_data;
                },
                'progressive_render' : true
               }
        }
              }).bind(
        'select_node.jstree',
        function (e, data) {
            document.location.href = data.rslt.obj.children('a').attr('href');
        });
         $('#entsearch').click(function () {
            ".Html::jsGetElementbyID("tree_projectcategory$rand").".jstree('close_all');;
            ".Html::jsGetElementbyID("tree_projectcategory$rand").
            ".jstree('search',".Html::jsGetDropdownValue('entsearchtext').");
         });

        ";
      echo "</script>";

      echo "<div id='tree_projectcategory$rand' ></div>";
      echo "</div>";
   }

   function sendEmail($items) {

      $users = array();
      foreach ($items as $key => $val) {
         $restrict  = "`itemtype` = 'User'
                       AND `plugin_resources_resources_id` = '".$key."'";
         $resources = getAllDatasFromTable("glpi_plugin_resources_resources_items", $restrict);

         if (!empty($resources)) {
            foreach ($resources as $resource) {
               $users[] = $resource["items_id"];
            }
         }
      }

      $User  = new User();
      $mail  = "";
      $first = true;
      foreach ($users as $key => $val) {
         if ($User->getFromDB($val)) {
            $email = $User->getDefaultEmail();
            if (!empty($email)) {
               if (!$first)
                  $mail.=";";
               else
                  $first = false;
               $mail.= $email;
            }
         }
      }

      $send = "<a href='mailto:$mail'>".__('Click here to send your email', 'resources')."</a>";
      Session::addMessageAfterRedirect($send);

      return true;
   }

   /**
    * Send a file (not a document) to the navigator
    * See Document->send();
    *
    * @param $file string: storage filename
    * @param $filename string: file title
    *
    * @return nothing
   **/
   static function sendFile($file, $filename) {

      // Test securite : document in DOC_DIR
      $tmpfile = str_replace(GLPI_PLUGIN_DOC_DIR."/resources/", "", $file);

      if (strstr($tmpfile,"../") || strstr($tmpfile,"..\\")) {
         Event::log($file, "sendFile", 1, "security",
                    $_SESSION["glpiname"]." try to get a non standard file.");
         die("Security attack !!!");
      }

      if (!file_exists($file)) {
         die("Error file $file does not exist");
      }

      $splitter = explode("/", $file);
      $mime     = "application/octet-stream";

      if (preg_match('/\.(....?)$/', $file, $regs)) {
         switch ($regs[1]) {
            case "jpeg" :
               $mime = "image/jpeg";
               break;

            case "jpg" :
               $mime = "image/jpeg";
               break;
         }
      }
      //print_r($file);

      // Now send the file with header() magic
      header("Expires: Mon, 26 Nov 1962 00:00:00 GMT");
      header('Pragma: private'); /// IE BUG + SSL
      header('Cache-control: private, must-revalidate'); /// IE BUG + SSL
      header("Content-disposition: filename=\"$filename\"");
      header("Content-type: ".$mime);

      readfile($file) or die ("Error opening file $file");
   }

   
   /**
    * Permet l'affichage dynamique d'une liste déroulante imbriquee
    *
    * @static
    * @param array ($itemtype,$myname,$value,$entity_restrict,$action,$span)
    */
   static function showGenericDropdown($itemtype, $options = array()) {
      
      if (isset($options['name'])) {
         // Set dropdown
         $options['on_change'] = "update".$options['name']."();";
         $options['entity']    = $_SESSION['glpiactive_entity'];
         $options['addicon']   = true;
         $rand = Dropdown::show($itemtype, $options);

         // Set ajax load if needed
         if (isset($options['action']) && isset($options['span'])) {
            $options[$options['name']]  = "__VALUE__";
            $options['entity_restrict'] = $_SESSION['glpiactive_entity'];
            $options['rand']            = $rand;
            $script = "function update".$options['name']."(){";
            $script .= Ajax::updateItemJsCode($options['span'], $options['action'], $options, 'dropdown_'.$options['name'].$rand, false);
            $script .= "}";
            echo Html::scriptBlock($script);
         }
      }
   }

   /**
   * Display information on treeview plugin
   *
   * @params itemtype, id, pic, url, name
   *
   * @return params
   **/
   static function showResourceTreeview($params) {
      global $CFG_GLPI;
      
      if ($params['itemtype'] == "PluginResourcesResource") {
         
         $params['pic'] = "../resources/pics/miniresources.png";
         
         $item = new $params['itemtype']();
         if ($item->getFromDB($params['id'])) {
            $params['name'] = self::getResourceName($params['id']);
            
            if (isset($item->fields["picture"])) {
               $params['pic'] = $CFG_GLPI['root_doc']."/plugins/resources/front/picture.send.php?file=".$item->fields["picture"];
            }
         }
      }
      return $params;
   }
   
   static function getMenuContent() {
      global $CFG_GLPI;

      $plugin_page = "/plugins/resources/front/menu.php";
      $menu = array();
      //Menu entry in admin
      $menu['title'] = self::getTypeName(2);
      $menu['page'] = $plugin_page;
      $menu['links']['search'] = "/plugins/resources/front/resource.php";
      $menu['links']['add'] = '/plugins/resources/front/wizard.form.php';
      $menu['links']['template'] = '/plugins/resources/front/setup.templates.php?add=0';
      
      // Resource directory
      $menu['links']["<img src='".$CFG_GLPI["root_doc"]."/plugins/resources/pics/directory18.png' title='".__('Directory', 'resources')."' alt='".__('Directory', 'resources')."'>"] = '/plugins/resources/front/directory.php';
      
      // Resting
      if (Session::haveright("plugin_resources_resting", UPDATE)) {
         $menu['links']["<img src='".$CFG_GLPI["root_doc"]."/plugins/resources/pics/restinglist18.png' title='".__('List of non contract periods', 'resources')."' alt='".__('List of non contract periods', 'resources')."'>"] = '/plugins/resources/front/resourceresting.php';
      }
      
      // Holiday
      if (Session::haveright("plugin_resources_holiday",UPDATE)) {
         $menu['links']["<img src='".$CFG_GLPI["root_doc"]."/plugins/resources/pics/holidaylist18.png' title='".__('List of forced holidays', 'resources')."' alt='".__('List of forced holidays', 'resources')."'>"] = '/plugins/resources/front/resourceholiday.php';
      }
      
      // Employment
      if (Session::haveright("plugin_resources_employment",READ)) {
         $menu['links']["<img src='".$CFG_GLPI["root_doc"]."/plugins/resources/pics/employmentlist18.png' title='".__('Employment management', 'resources')."' alt='".__('Employment management', 'resources')."'>"] = '/plugins/resources/front/employment.php';
         $menu['links']["<img src='".$CFG_GLPI["root_doc"]."/plugins/resources/pics/recap18.png' title='".__('List Employments / Resources', 'resources')."' alt='".__('List Employments / Resources', 'resources')."'>"] = '/plugins/resources/front/recap.php';
      }
      
      // Budget
      if (Session::haveright("plugin_resources_budget", READ)) {
         $menu['links']["<img src='".$CFG_GLPI["root_doc"]."/plugins/resources/pics/budgetlist18.png' title='".__('Budget management', 'resources')."' alt='".__('Budget management', 'resources')."'>"] = '/plugins/resources/front/budget.php';
      }

      // Task
      if (Session::haveright("plugin_resources_task", READ)) {
         $menu['links']["<img  src='".$CFG_GLPI["root_doc"]."/pics/menu_showall.png' title='".__('Tasks list', 'resources')."' alt='".__('Tasks list', 'resources')."'>"] = '/plugins/resources/front/task.php';
      }
      
      // Checklist
      if (Session::haveright("plugin_resources_checklist", READ)) {
         $menu['links']["<img  src='".$CFG_GLPI["root_doc"]."/pics/reservation-3.png' title='"._n('Checklist', 'Checklists', 2, 'resources')."' alt='"._n('Checklist', 'Checklists', 2, 'resources')."'>"] = '/plugins/resources/front/checklistconfig.php';
      }

      // Config page
      if (Session::haveRight("config",UPDATE)) {
         $menu['links']['config'] = '/plugins/resources/front/config.form.php';
      }
     
      // Add menu to class
      $menu = PluginResourcesBudget::getMenuOptions($menu);
      $menu = PluginResourcesChecklist::getMenuOptions($menu);
      $menu = PluginResourcesEmployment::getMenuOptions($menu);
      
      return $menu;
   }
   
   function checkTransferMandatoryFields($input){
      $msg     = array();
      $checkKo = false;
      
      $mandatory_fields = array('entities_id'  => __('Entity'), 'plugin_resources_resources_id' => PluginResourcesResource::getTypeName(1));
      
      foreach($input as $key => $value){
         if (array_key_exists($key, $mandatory_fields)) {
            if (empty($value)) {
               $msg[] = $mandatory_fields[$key];
               $checkKo = true;
            }
         }
      }
      
      if ($checkKo) {
         Session::addMessageAfterRedirect(sprintf(__("Mandatory fields are not filled. Please correct: %s"), implode(', ', $msg)), false, ERROR);
         return false;
      }
      return true;
   }

}

?>