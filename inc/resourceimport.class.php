<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginResourcesResourceImport
 */
class PluginResourcesResourceImport extends CommonDBChild {

   static $rightname = 'plugin_resources_import';
   public $dohistory = true;

   static public $itemtype = PluginResourcesResource::class;
   static public $items_id = 'plugin_resources_resources_id';

   /**
    * Return the localized name of the current Type
    * Should be overloaded in each new class
    *
    * @return string
    **/
   static function getTypeName($nb = 0) {
      return _n('Import', 'Imports', $nb, 'resources');
   }

   /**
    * Get Tab Name used for itemtype
    *
    * NB : Only called for existing object
    *      Must check right on what will be displayed + template
    *
    * @since version 0.83
    *
    * @param CommonDBTM|CommonGLPI $item CommonDBTM object for which the tab need to be displayed
    * @param bool|int              $withtemplate boolean  is a template object ? (default 0)
    *
    * @return string tab name
    */
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      // can exists for template
      if ($item->getType() == PluginResourcesResource::class) {
         if ($_SESSION['glpishow_count_on_tabs']) {
            $dbu = new DbUtils();
            $table = $dbu->getTableForItemType(__CLASS__);
            return self::createTabEntry(self::getTypeName(),
               $dbu->countElementsInTable($table,
                  [self::$items_id => $item->getID()]));
         }
         return self::getTypeName();
      }
      return '';
   }

   /**
    * Create New Resource and linked ResourceImport
    * Delete ImportResource and ImportResourceData
    *
    * @param array $input
    * @param array $options
    * @param bool $history
    * @return int|null
    */
   function add(array $input, $options = [], $history = true) {
      $resourceID = null;
      if(isset($input['import'])){
         foreach($input['import'] as $importID=>$datas){

            $resourceInput = [];
            $resourceInput['entities_id'] = $_SESSION['glpiactive_entity'];
            $resourceImportInputs = [];

            foreach($datas as $importColumnID=>$data){

               if($data['id'] == 0 && $data['value'] == "-1" ){
                  continue;
               }

               switch($data['resource_column']){
                  case "10": //others
                     $resourceImportInputs[] = [
                        'name' => $data['name'],
                        'value' => $data['value']
                     ];
                     break;
                  default:
                     $resourceTableColumnName = PluginResourcesResource::getResourceColumnNameFromDataNameID($data['resource_column']);
                     $resourceInput[$resourceTableColumnName] = $data['value'];
                     break;
               }
            }

            $resource = new PluginResourcesResource();
            $resourceID = $resource->add($resourceInput);

            foreach($resourceImportInputs as $resourceImportInput){
               $resourceImportInput[PluginResourcesResourceImport::$items_id] = $resourceID;
               parent::add($resourceImportInput);
            }
         }
         // Delete importResource and importResourceData
         $pluginResourcesImportResource = new PluginResourcesImportResource();
         $pluginResourcesImportResourceData = new PluginResourcesImportResourceData();

         foreach($input['import'] as $importID=>$datas){
            foreach($datas as $importColumnID=>$data){
               if($data['id'] == 0){
                  continue;
               }
               $pluginResourcesImportResourceData->delete(["id"=>$data['id']]);
            }
            $pluginResourcesImportResource->delete(["id"=>$importID]);
         }
      }
      return $resourceID;
   }

   function update(array $input, $history = 1, $options = [])
   {
      $resourceID = $input['resource'];
      $pluginResourcesResourceImport = new PluginResourcesResourceImport();

      if (isset($input['import'])) {
         foreach ($input['import'] as $importID => $datas) {

            $resourceInput = [];
            $resourceInput['entities_id'] = $_SESSION['glpiactive_entity'];

            foreach ($datas as $importColumnID => $data) {

               if ($data['id'] == 0 && $data['value'] == "-1") {
                  continue;
               }

               switch($data['resource_column']){
                  case 10:
                     $criterias = [PluginResourcesResourceImport::$items_id => $resourceID,'name' => $data['name']];

                     $pluginResourcesResourceImport->getFromDBByCrit($criterias);

                     $resourceImportInput = [
                        PluginResourcesResourceImport::getIndexName() => $pluginResourcesResourceImport->getID(),
                        'value' => $data['value']
                     ];

                     if(!parent::update($resourceImportInput)){
                        Html::displayErrorAndDie('Error when updating Resource Import');
                     }
                     break;
                  default:

                     // Get the column name from resource_column
                     $fieldName = PluginResourcesResource::getResourceColumnNameFromDataNameID($data['resource_column']);

                     // Prepare inputs
                     $resourceInput = [
                        PluginResourcesResource::getIndexName() => $resourceID,
                        $fieldName => $data['value']
                     ];

                     $resource = new PluginResourcesResource();

                     // Update resource column
                     if(!$resource->update($resourceInput)){
                        Html::displayErrorAndDie('Error when updating Resource Import');
                     }
               }
            }
            // Delete importResource and importResourceData
            foreach ($datas as $importColumnID => $data) {

               if ($data['id'] == 0 && $data['value'] == "-1") {
                  continue;
               }

               $pluginResourcesImportResourceData = new PluginResourcesImportResourceData();

               $importResourceDataInput = [PluginResourcesImportResourceData::getIndexName() => $data['id']];

               // Delete resource column
               if(!$pluginResourcesImportResourceData->delete($importResourceDataInput)){
                  Html::displayErrorAndDie('Error when deleting Import Resource Data');
               }
            }
            $pluginResourcesImportResource = new PluginResourcesImportResource();

            $importResourceInput = [];
         }
      }
   }



   /**
    * show Tab content
    *
    * @since version 0.83
    *
    * @param          $item                  CommonGLPI object for which the tab need to be displayed
    * @param          $tabnum       integer  tab number (default 1)
    * @param bool|int $withtemplate boolean  is a template object ? (default 0)
    *
    * @return true
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      if ($item->getType() == PluginResourcesResource::class) {
         self::showImportResources($item, $withtemplate);
      }
      return true;
   }

   static function showImportResources($item, $withtemplate){

      echo "yolo";

   }
}