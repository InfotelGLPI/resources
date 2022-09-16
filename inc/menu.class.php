<?php
/**
 * Created by PhpStorm.
 * User: mate
 * Date: 11/04/2019
 * Time: 09:19
 */

class PluginResourcesMenu extends CommonDBTM {

    static $rightname = 'plugin_resources';
   /**
    * Return the localized name of the current Type
    * Should be overloaded in each new class
    *
    * @return string
    **/
   static function getTypeName($nb = 0) {

      return _n('Human resource', 'Human resources', $nb, 'resources');
   }

    public static function canView()
    {
        if (static::$rightname) {
            return Session::haveRight(static::$rightname, READ);
        }
        return false;
    }

   /**
    * Display menu
    */
   static function showMenu(CommonDBTM $item) {
      global $CFG_GLPI;

      echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR."/css/style_bootstrap_main.css");
      echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR."/css/style_bootstrap_ticket.css");

      echo "<div align='center'>";

      $canresting       = Session::haveright('plugin_resources_resting', UPDATE);
      $canholiday       = Session::haveright('plugin_resources_holiday', UPDATE);
      $canhabilitation  = Session::haveright('plugin_resources_habilitation', UPDATE);
      $canemployment    = Session::haveright('plugin_resources_employment', UPDATE);
      $canseeemployment = Session::haveright('plugin_resources_employment', READ);
      $canseebudget     = Session::haveright('plugin_resources_budget', READ);
      $canbadges        = Session::haveright('plugin_badges', READ) && Plugin::isPluginActive("badges");
      $canImport        = Session::haveright('plugin_resources_import', READ);

      if ($item->canCreate()) {

         echo "<h3><div class='alert alert-secondary' role='alert'>";
         echo "<i class='fas fa-user-friends'></i>&nbsp;";
         echo __('Resources management', 'resources');
         echo "</div></h3>";

         echo "<table class='tab_cadre_fixe resources_menu' style='width: 400px;'>";

         echo "<tr class=''>";

         //Add a resource
         echo "<td class=' center' colspan='2' width='200'>";
         echo "<a href=\"./wizard.form.php\">";
         echo "<img src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/newresource.png' alt='" . __('Declare an arrival', 'resources') . "'>";
         echo "<br>" . __('Declare an arrival', 'resources') . "</a>";
         echo "</td>";

         //Add a change
         echo "<td class=' center' colspan='2'  width='200'>";
         $config = new PluginResourcesConfig();
         if (!empty($config->fields["use_meta_for_changes"]) && Plugin::isPluginActive('metademands')) {
            $url = PLUGIN_METADEMANDS_WEBDIR . "/front/wizard.form.php?step=2&metademands_id=" . $config->fields["use_meta_for_changes"];
            echo "<a href=\"" . $url . "\">";
         } else {
            echo "<a href=\"./resource.change.php\">";
         }
         echo "<img src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/recap.png' alt='" . __('Declare a change', 'resources') . "'>";
         echo "<br>" . __('Declare a change', 'resources') . "</a>";
         echo "</td>";

         //Remove resources
         echo "<td class=' center' colspan='2'  width='200'>";
         if (!empty($config->fields["use_meta_for_leave"]) && Plugin::isPluginActive('metademands')) {
            $url = PLUGIN_METADEMANDS_WEBDIR . "/front/wizard.form.php?step=2&metademands_id=" . $config->fields["use_meta_for_leave"];
            echo "<a href=\"" . $url . "\">";
         } else {
            echo "<a href=\"./resource.remove.php\">";
         }
         echo "<img src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/removeresource.png' alt='" . __('Declare a departure', 'resources') . "'>";
         echo "<br>" . __('Declare a departure', 'resources') . "</a>";
         echo "</td>";

         echo "</tr>";
         echo " </table>";
      }

      if ($canresting || $canholiday || $canbadges || $canhabilitation) {

         echo "<br><h3><div class='alert alert-secondary' role='alert'>";
         echo "<i class='fas fa-user-friends'></i>&nbsp;";
         echo __('Others declarations', 'resources');
         echo "</div></h3>";

         echo "<table class='tab_cadre_fixe resources_menu' style='width: 400px;'>";

         $num_col = 0;
         if ($canresting) {
            $num_col += 1;
         }
         if ($canholiday) {
            $num_col += 1;
         }
         if ($canhabilitation && Plugin::isPluginActive("metademands")) {
            $num_col += 1;
         }
         if ($canbadges && Plugin::isPluginActive("badges")) {
            $num_col += 1;
         }
         if ($num_col == 0) {
            $colspan = 0;
         } else {
            $colspan = floor(6 / $num_col);
         }

         echo "<tr class=''>";
         if ($colspan == 1) {
            echo "<td></td>";
         }
         if ($canresting) {
            //Management of a non contract period
            echo "<td colspan=$colspan class=' center'>";
            echo "<a href=\"./resourceresting.form.php?menu\">";
            echo "<img src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/deleteresting.png' alt='" . _n('Non contract period management', 'Non contract periods management', 2, 'resources') . "'>";
            echo "<br>" . _n('Non contract period management', 'Non contract periods management', 2, 'resources') . "</a>";
            echo "</td>";
         }

         if ($canholiday) {
            //Management of a non contract period
            echo "<td colspan=$colspan class=' center'>";
            echo "<a href=\"./resourceholiday.form.php?menu\">";
            echo "<img src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/holidayresource.png' alt='" . __('Forced holiday management', 'resources') . "'>";
            echo "<br>" . __('Forced holiday management', 'resources') . "</a>";
            echo "</td>";
         }

         if ($canhabilitation && Plugin::isPluginActive("metademands")) {
            //Management of a super habilitation
            echo "<td colspan=$colspan class=' center'>";
            echo "<a href=\"./confighabilitation.form.php?menu\">";
            echo "<img src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/habilitation.png' alt='" . PluginResourcesConfigHabilitation::getTypeName(1) . "'>";
            echo "<br>" . PluginResourcesConfigHabilitation::getTypeName(1) . "</a>";
            echo "</td>";
         }

         if ($canbadges && Plugin::isPluginActive("badges")) {
            //Management of a non contract period
            echo "<td colspan=$colspan class=' center'>";
            echo "<a href=\"./resourcebadge.form.php?menu\">";
            echo "<img src='" . PLUGIN_BADGES_WEBDIR . "/badges.png' alt='" . _n('Badge management', 'Badges management', 2, 'resources') . "'>";
            echo "<br>" . _n('Badge management', 'Badges management', 2, 'resources') . "</a>";
            echo "</td>";
         }
         if ($colspan == 1) {
            echo "<td></td>";
         }
         echo "</tr>";
         echo " </table>";
      }

      if ($item->canView()) {

         echo "<br><h3><div class='alert alert-secondary' role='alert'>";
         echo "<i class='fas fa-user-friends'></i>&nbsp;";
         echo __('Others actions', 'resources');
         echo "</div></h3>";

         echo "<table class='tab_cadre_fixe resources_menu' style='width: 400px;'>";

         echo "<tr class=''>";

         $opt                              = [];
         $opt['reset']                     = 'reset';
         $opt['criteria'][0]['field']      = 27;
         $opt['criteria'][0]['searchtype'] = 'equals';
         $opt['criteria'][0]['value']      = Session::getLoginUserID();
         $opt['criteria'][0]['link']       = 'AND';

         $url = PLUGIN_RESOURCES_WEBDIR. "/front/resource.php?" . Toolbox::append_params($opt, '&amp;');
         $config = new PluginResourcesConfig();
         if (!$config->fields["hide_view_commercial_resource"]) {
            echo "<td class=' center'>";
            echo "<a href=\"$url\">";
            echo "<i class='fas fa-user-tie fa-4x' title='" . __('View my resources as a commercial', 'resources') . "'></i>";
            echo "<br>" . __('View my resources as a commercial', 'resources') . "</a>";
            echo "</td>";
         }

         //See resources
         echo "<td class=' center'>";
         echo "<a href=\"./resource.php?reset=reset\">";
         echo "<img src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/resourcelist.png' alt='" . __('Search resources', 'resources') . "'>";
         echo "<br>" . __('Search resources', 'resources') . "</a>";
         echo "</td>";

//         echo "<td class=' center'>";
//         echo "<a href=\"./resource.card.form.php\">";
//         echo "<img src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/detailresource.png' alt='" . __('See details of a resource', 'resources') . "'>";
//         echo "<br>" . __('See details of a resource', 'resources') . "</a>";
//         echo "</td>";

         echo "<td class=' center'>";
         echo "<a href=\"./directory.php\">";
         echo "<img src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/directory.png' alt='" . PluginResourcesDirectory::getTypeName(1) . "'>";
         echo "<br>" . PluginResourcesDirectory::getTypeName(1) . "</a>";
         echo "</td>";

         echo "</tr>";
         echo " </table>";
      }

      if ($canseeemployment || $canseebudget) {
         $colspan = 0;

         echo "<br><h3><div class='alert alert-secondary' role='alert'>";
         echo "<i class='fas fa-user-friends'></i>&nbsp;";
         echo  __('Employments / budgets management', 'resources');
         echo "</div></h3>";

         echo "<table class='tab_cadre_fixe resources_menu' style='width: 400px;'>";

         echo "<tr class=''>";
         echo "<td class='center'>";
         echo "</td>";

         if ($canseeemployment) {
            if ($canemployment) {
               //Add an employment
               echo "<td class=' center'>";
               echo "<a href=\"./employment.form.php\">";
               echo "<img src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/employment.png' alt='" . __('Declare an employment', 'resources') . "'>";
               echo "<br>" . __('Declare an employment', 'resources') . "</a>";
               echo "</td>";
            } else {
               $colspan += 1;
            }
            //See managment employments
            echo "<td class=' center'>";
            echo "<a href=\"./employment.php\">";
            echo "<img src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/employmentlist.png' alt='" . __('Employment management', 'resources') . "'>";
            echo "<br>" . __('Employment management', 'resources') . "</a>";
            echo "</td>";
         } else {
            $colspan += 1;
         }
         if ($canseebudget) {
            //See managment budgets
            echo "<td class=' center'>";
            echo "<a href=\"./budget.php\">";
            echo "<img src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/budgetlist.png' alt='" . __('Budget management', 'resources') . "'>";
            echo "<br>" . __('Budget management', 'resources') . "</a>";
            echo "</td>";
         } else {
            $colspan += 1;
         }

         if ($canseeemployment) {
            //See recap ressource / employment
            echo "<td class=' center'>";
            echo "<a href=\"./recap.php\">";
            echo "<img src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/recap.png' alt='" . __('List Employments / Resources', 'resources') . "'>";
            echo "<br>" . __('List Employments / Resources', 'resources') . "</a>";
            echo "</td>";
         } else {
            $colspan += 1;
         }

         echo "<td class='center' colspan='" . ($colspan + 1) . "'></td>";

         echo "</tr>";
         echo " </table>";
      }

      if ($canImport) {

         echo "<br><h3><div class='alert alert-secondary' role='alert'>";
         echo "<i class='fas fa-user-friends'></i>&nbsp;";
         echo  __('Import resources', 'resources');
         echo "</div></h3>";

         echo "<table class='tab_cadre_fixe resources_menu' style='width: 400px;'>";

         echo "<tr class=''>";
         echo "<td class=' center' colspan='2'>";
         echo "<a href='" . PluginResourcesImportResource::getIndexUrl() . "?type=" . PluginResourcesImportResource::UPDATE_RESOURCES . "'>";
         echo "<i class=\"fas fa-user-edit fa-4x\"></i>";
         echo "<br>" . __('Update GLPI Resources', 'resources') . "</a>";
         echo "</td>";

         echo "<td class=' center' colspan='2'>";
         echo "<a href='" . PluginResourcesImportResource::getIndexUrl() . "?type=" . PluginResourcesImportResource::VERIFY_FILE . "'>";
         echo "<img src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/csv_check.png' />";
         echo "<br>" . __('Verify CSV file', 'resources') . "</a>";
         echo "</td>";

         echo "<td class=' center' colspan='2'>";
         echo "<a href='" . PluginResourcesImportResource::getIndexUrl() . "?type=" . PluginResourcesImportResource::VERIFY_GLPI . "'>";
         echo "<img src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/resource_check.png' />";
         echo "<br>" . __('Verify GLPI resources', 'resources') . "</a>";
         echo "</td>";

         echo "</tr>";

         echo "<tr class=''>";

         echo "<td class=' center' colspan='2'>";
         echo "<a href='" . PluginResourcesImport::getIndexUrl() . "'>";
         echo "<img src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/conf.png' />";
         echo "<br>" . __('Configure Imports', 'resources') . "</a>";
         echo "</td>";

         echo "<td class=' center' colspan='2'>";
         echo "<a href='" . PluginResourcesImportResource::getFormURL() . "?reset-imports=1'>";
         echo "<i class=\"fas fa-trash fa-4x\"></i>";
         echo "<br>" . __('Purge imported resources', 'resources') . "</a>";
         echo "</td>";

         echo "<td colspan='2'></td>";

         echo "</tr>";

         echo " </table>";
      }

      echo "</div>";
   }

   /**
    * get menu content
    *
    * @return array array for menu
    **/
   static function getMenuContent() {
      $plugin_page =PLUGIN_RESOURCES_NOTFULL_WEBDIR."/front/menu.php";

      $menu = [];
      //Menu entry in admin
      $menu['title']           = PluginResourcesResource::getTypeName(2);
      $menu['page']            = $plugin_page;
      $menu['links']['search'] = PLUGIN_RESOURCES_NOTFULL_WEBDIR."/front/resource.php";
      $menu['links']['lists']  = "";
      $menu['lists_itemtype']  = PluginResourcesResource::getType();
      if (Session::haveright("plugin_resources", CREATE)) {

         $menu['links']['add']      = PLUGIN_RESOURCES_NOTFULL_WEBDIR.'/front/wizard.form.php';
         $menu['links']['template'] = PLUGIN_RESOURCES_NOTFULL_WEBDIR.'/front/setup.templates.php?add=0';
      }

      // Resource directory
      $menu['links']["<i class='far fa-address-book fa-1x' title='" . __('Directory', 'resources') . "'></i>"] = PLUGIN_RESOURCES_NOTFULL_WEBDIR.'/front/directory.php';

      // Resting
      if (Session::haveright("plugin_resources_resting", UPDATE)) {
         $menu['links']["<i class='fas fa-file-signature fa-1x' title='" . __('List of non contract periods', 'resources') . "'></i>"] = PLUGIN_RESOURCES_NOTFULL_WEBDIR.'/front/resourceresting.php';
      }

      // Holiday
      if (Session::haveright("plugin_resources_holiday", UPDATE)) {
         $menu['links']["<i class='fas fa-atlas fa-1x' title='" . __('List of forced holidays', 'resources') . "'></i>"] = PLUGIN_RESOURCES_NOTFULL_WEBDIR.'/front/resourceholiday.php';
      }

      // Employment
      if (Session::haveright("plugin_resources_employment", READ)) {
         $menu['links']["<i class='fas fa-list-ul fa-1x' title='" . __('Employment management', 'resources') . "'></i>"]     = PLUGIN_RESOURCES_NOTFULL_WEBDIR.'/front/employment.php';
         $menu['links']["<i class='fas fa-city fa-1x' title='" . __('List Employments / Resources', 'resources') . "'></i>"] = PLUGIN_RESOURCES_NOTFULL_WEBDIR.'/front/recap.php';
      }

      // Budget
      if (Session::haveright("plugin_resources_budget", READ)) {
         $menu['links']["<i class='fas fa-coins fa-1x' title='" . __('Budget management', 'resources') . "'></i>"] = PLUGIN_RESOURCES_NOTFULL_WEBDIR.'/front/budget.php';
      }

      // Task
      if (Session::haveright("plugin_resources_task", READ)) {
         $menu['links']["<i class='fas fa-tasks fa-1x' title='" . __('Tasks list', 'resources') . "'></i>"] = PLUGIN_RESOURCES_NOTFULL_WEBDIR.'/front/task.php';
      }

      // Checklist
      if (Session::haveright("plugin_resources_checklist", READ)) {
         $menu['links']["<i class='far fa-calendar-check fa-1x' title='" . _n('Checklist', 'Checklists', 2, 'resources') . "'></i>"] = PLUGIN_RESOURCES_NOTFULL_WEBDIR.'/front/checklistconfig.php';
      }

      $opt                              = [];
      $opt['reset']                     = 'reset';
      $opt['criteria'][0]['field']      = 27; // validation status
      $opt['criteria'][0]['searchtype'] = 'equals';
      $opt['criteria'][0]['value']      = Session::getLoginUserID();
      $opt['criteria'][0]['link']       = 'AND';

      $url = PLUGIN_RESOURCES_NOTFULL_WEBDIR."/front/resource.php?" . Toolbox::append_params($opt, '&amp;');

      $menu['links']["<i class='fas fa-user-tie fa-1x' title='" . __('View my resources as a commercial', 'resources') . "'></i>"] = $url;

      // Import page
      if (Session::haveRight('plugin_resources_import', READ)) {
         $menu['links']["<i class='fas fa-cog fa-1x' title='" . __('Import configuration', 'resources') . "'></i>"]
            = PLUGIN_RESOURCES_NOTFULL_WEBDIR.'/front/import.php';
      }

      // Config page
      if (Session::haveRight("config", UPDATE)) {
         $menu['links']['config'] = PLUGIN_RESOURCES_NOTFULL_WEBDIR.'/front/config.form.php';
      }

      // Add menu to class
      $menu = PluginResourcesBudget::getMenuOptions($menu);
      $menu = PluginResourcesChecklist::getMenuOptions($menu);
      $menu = PluginResourcesEmployment::getMenuOptions($menu);

      $menu['icon'] = PluginResourcesResource::getIcon();

      return $menu;
   }
}
