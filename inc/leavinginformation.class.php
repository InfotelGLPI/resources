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

use Glpi\Application\View\TemplateRenderer;

/**
 * Class PluginResourcesEmployee
 */

class PluginResourcesLeavingInformation extends CommonDBTM {

//   static $rightname = 'plugin_resources_leavinginformation';
   static $rightname = 'plugin_resources';

   /**
    * Return the localized name of the current Type
    * Should be overloaded in each new class
    *
    * @param integer $nb Number of items
    *
    * @return string
    **/
   static function getTypeName($nb = 0) {

      return _n('Leaving Information', 'Leaving Informations', $nb, 'resources');
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
    * Get Tab Name used for itemtype
    *
    * NB : Only called for existing object
    *      Must check right on what will be displayed + template
    *
    * @since 0.83
    *
    * @param CommonGLPI $item         Item on which the tab need to be displayed
    * @param boolean    $withtemplate is a template object ? (default 0)
    *
    *  @return string tab name
    **/
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      $wizard_employee = PluginResourcesContractType::checkWizardSetup($item->getField('id'), "use_employee_wizard");

      if ($item->getType() == 'PluginResourcesResource'
          && $this->canView()
          && Session::haveRight('plugin_resources_leavinginformation',1)
          && $item->fields['is_leaving']
      ) {
         return self::getTypeName(1);
      }
      return '';
   }


   /**
    * show Tab content
    *
    * @since 0.83
    *
    * @param CommonGLPI $item         Item on which the tab need to be displayed
    * @param integer    $tabnum       tab number (default 1)
    * @param boolean    $withtemplate is a template object ? (default 0)
    *
    * @return boolean
    **/
   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      global $CFG_GLPI;

      if ($item->getType() == 'PluginResourcesResource'
          && Session::haveRight('plugin_resources_leavinginformation',1)
          && $item->fields['is_leaving']) {
         $self = new self();
         $self->showLeavingInformationForm($item->getField('id'), 0, $withtemplate);
      }
      return true;
   }

   /**
    * @param $plugin_resources_resources_id
    *
    * @return bool
    */
   function getFromDBbyResources($plugin_resources_resources_id) {
      global $DB;

      $query = "SELECT *
                FROM `" . $this->getTable() . "`
                WHERE `plugin_resources_resources_id` = '$plugin_resources_resources_id'";

      if ($result = $DB->query($query)) {
         if ($DB->numrows($result) != 1) {
            return false;
         }
         $this->fields = $DB->fetchAssoc($result);
         if (is_array($this->fields) && count($this->fields)) {
            return true;
         }
         return false;
      }
      return false;
   }

   /**
    * Prepare input datas for adding the item
    *
    * @param array $input datas used to add the item
    *
    * @return array the modified $input array
    **/
   function prepareInputForAdd($input) {
      // Not attached to resource -> not added
      if (!isset($input['plugin_resources_resources_id']) || $input['plugin_resources_resources_id'] <= 0) {
         return false;
      }
      if ($this->getFromDBbyResources($input['plugin_resources_resources_id'])) {
         return false;
      }
      return $input;
   }

   /**
    * Duplicate item resources from an item template to its clone
    *
    * @since version 0.84
    *
    * @param $itemtype     itemtype of the item
    * @param $oldid        ID of the item to clone
    * @param $newid        ID of the item cloned
    * @param $newitemtype  itemtype of the new item (= $itemtype if empty) (default '')
    **/
   static function cloneItem($oldid, $newid) {
      global $DB;

      $query = "SELECT *
                 FROM `glpi_plugin_resources_employees`
                 WHERE `plugin_resources_resources_id` = '$oldid';";

      foreach ($DB->request($query) as $data) {
         $employee = new self();
         $employee->add(['plugin_resources_resources_id' => $newid,
                              'plugin_resources_employers_id' => $data["plugin_resources_employers_id"],
                              'plugin_resources_clients_id'   => $data["plugin_resources_clients_id"]]);
      }
   }

   /**
    * @param        $plugin_resources_resources_id
    * @param        $users_id
    * @param string $withtemplate
    *
    * @return bool
    */
   function showLeavingInformationForm($plugin_resources_resources_id, $users_id, $withtemplate = '') {
      global $CFG_GLPI;

      if (!$this->canView()) {
         return false;
      }

      $resource         = new PluginResourcesResource();

      $restrict  = ["plugin_resources_resources_id" => $plugin_resources_resources_id];

      $dbu       = new DbUtils();
      $this->getFromDBByCrit($restrict);


      $canedit = $resource->can($plugin_resources_resources_id, UPDATE);
       $resource->getFromDB($plugin_resources_resources_id);
      $input['plugin_resources_contracttypes_id'] = $resource->fields['plugin_resources_contracttypes_id'];
       $input['entities_id'] = $resource->fields['entities_id'];
       $input['more_information'] = 1;
//       $tt = ['users_id' => 'users_id'];
       $mandatory                                   = $this->checkRequiredFields($input);
       $mandatory = array_flip($mandatory);
       $hidden                                      = $this->getHiddenFields($input);
       $hidden = array_flip($hidden);
       $readonly                                      = $this->getReadonlyFields($input);
       $readonly = array_flip($readonly);
       $config = new PluginResourcesConfig();
       if (($config->getField('sales_manager') != "")) {


           $tableProfileUser = Profile_User::getTable();
           $tableUser        = User::getTable();
           $profile_User     = new  Profile_User();
           $prof             = [];
           foreach (json_decode($config->getField('sales_manager')) as $profs) {
               $prof[$profs] = $profs;
           }

           $ids           = join("','", $prof);
           $restrict      = getEntitiesRestrictCriteria($tableProfileUser, 'entities_id', $resource->fields["entities_id"], true);
           $restrict      = array_merge([$tableProfileUser . ".profiles_id" => [$ids]], $restrict);
           $profiles_User = $profile_User->find($restrict);
           $used          = [];
           foreach ($profiles_User as $profileUser) {
               $user = new User();
               $user->getFromDB($profileUser["users_id"]);
               $used[$profileUser["users_id"]] = $user->getFriendlyName();
           }

           TemplateRenderer::getInstance()->display('@resources/leavinginformation.html.twig', [
               'item'   => $this,
               'params' => [
                   'plugin_resources_resources_id' => $plugin_resources_resources_id,
                   'hidden_fields'       => $hidden,
                   'readonly_fields'    => $readonly,
                   'mandatory_fields'       => $mandatory,
                   'element_sales' => $used,
               ],
           ]);

       } else {
           TemplateRenderer::getInstance()->display('@resources/leavinginformation.html.twig', [
               'item'   => $this,
               'params' => [
                   'plugin_resources_resources_id' => $plugin_resources_resources_id,
                   'hidden_fields'       => $hidden,
                   'readonly_fields'    => $readonly,
                   'mandatory_fields'       => $mandatory,
                   'right_sales'       => true,
               ],
           ]);
       }



//        echo '<div class="asset">';
//         if ($withtemplate < 2) {
//            echo "<form method='post' action=\"" . PLUGIN_RESOURCES_WEBDIR. "/front/resource.form.php\">";
//         }


   }








   /**
    * Provides search options configuration. Do not rely directly
    * on this, @see CommonDBTM::searchOptions instead.
    *
    * @since 9.3
    *
    * This should be overloaded in Class
    *
    * @return array a *not indexed* array of search options
    *
    * @see https://glpi-developer-documentation.rtfd.io/en/master/devapi/search.html
    **/
   function rawSearchOptions() {

      $tab = parent::rawSearchOptions();

       $tab[] = [
           'id' => '2',
           'table' => PluginResourcesResource::getTable(),
           'field' => 'name',
           'name'     => PluginResourcesResource::getTypeName(1),
           'datatype' => 'dropdown'
       ];
       $tab[] = [
           'id' => '3',
           'table' => PluginResourcesDestination::getTable(),
           'field' => 'name',
           'name'     => PluginResourcesDestination::getTypeName(1),
           'datatype' => 'dropdown'
       ];
       $tab[] = [
           'id' => '4',
           'table' => PluginResourcesClient::getTable(),
           'field' => 'name',
           'name'     => PluginResourcesClient::getTypeName(1),
           'datatype' => 'dropdown'
       ];
       $tab[] = [
           'id' => '5',
           'table' => PluginResourcesWorkProfile::getTable(),
           'field' => 'name',
           'name'     => PluginResourcesWorkProfile::getTypeName(1),
           'datatype' => 'dropdown'
       ];
       $tab[] = [
           'id' => '6',
           'table' => PluginResourcesResignationReason::getTable(),
           'field' => 'name',
           'name'     => PluginResourcesResignationReason::getTypeName(1),
           'datatype' => 'dropdown'
       ];
       $tab[] = [
           'id' => '7',
           'table' => User::getTable(),
           'field' => 'name',
           'name'     => __('Sales manager', 'resources'),
           'datatype' => 'dropdown'
       ];
       $tab[] = [
           'id'            => '8',
           'table'         => $this->getTable(),
           'field'         => 'interview_date',
           'name'          =>  __('Interview date', 'resources'),
           'datatype'      => 'datetime',
           'massiveaction' => false
       ];
       $tab[] = [
           'id'            => '9',
           'table'         => $this->getTable(),
           'field'         => 'resignation_date',
           'name'          =>  __('Resignation date', 'resources'),
           'datatype'      => 'datetime',
           'massiveaction' => false
       ];
       $tab[] = [
           'id'            => '10',
           'table'         => $this->getTable(),
           'field'         => 'wished_leaving_date',
           'name'          =>   __('Wished leaving date', 'resources'),
           'datatype'      => 'datetime',
           'massiveaction' => false
       ];
       $tab[] = [
           'id'            => '11',
           'table'         => $this->getTable(),
           'field'         => 'effective_leaving_date',
           'name'          =>    __('Effective leaving date', 'resources'),
           'datatype'      => 'datetime',
           'massiveaction' => false
       ];
       $tab[] = [
           'id'            => '12',
           'table'         => $this->getTable(),
           'field'         => 'pay_gap',
           'name'          =>  __('Pay gap','resources'),
           'datatype'      => 'bool',
           'massiveaction' => true
       ];
       $tab[] = [
           'id'            => '13',
           'table'         => $this->getTable(),
           'field'         => 'mission_lost',
           'name'          =>  __('Mission lost','resources'),
           'datatype'      => 'bool',
           'massiveaction' => true
       ];
       $tab[] = [
           'id'            => '14',
           'table'         => $this->getTable(),
           'field'         => 'company_name',
           'name'          => __('Company name', 'resources'),
           'datatype'      => 'text',
           'massiveaction' => true
       ];

      return $tab;
   }


    public static function rawSearchOptionsToAdd($itemtype)
    {
        $tab = [];
        $haveRight = Session::haveRight('plugin_resources_leavinginformation',1);
        if($itemtype == PluginResourcesResource::getType() && $haveRight) {


            $tab[] = [
                'id' => '160',
                'table' => PluginResourcesDestination::getTable(),
                'field' => 'name',
                'name' => PluginResourcesDestination::getTypeName(),
                'forcegroupby' => true,
                'massiveaction' => false,
                'datatype' => 'dropdown',
                'joinparams' => [
                    'beforejoin' => [
                        'table' => self::getTable(),
                        'joinparams' => [
                            'jointype' => 'child',

                        ]
                    ]
                ]
            ];
            $tab[] = [
                'id' => '161',
                'table' => PluginResourcesClient::getTable(),
                'field' => 'name',
                'name' => PluginResourcesClient::getTypeName(),

                'massiveaction' => false,
                'datatype' => 'dropdown',
                'joinparams' => [
                    'beforejoin' => [
                        'table' => self::getTable(),
                        'joinparams' => [
                            'jointype' => 'child',

                        ]
                    ]
                ]
            ];

            $tab[] = [
                'id' => '162',
                'table' => PluginResourcesWorkProfile::getTable(),
                'field' => 'name',
                'name' => PluginResourcesWorkProfile::getTypeName(),

                'massiveaction' => false,
                'datatype' => 'dropdown',
                'joinparams' => [
                    'beforejoin' => [
                        'table' => self::getTable(),
                        'joinparams' => [
                            'jointype' => 'child',

                        ]
                    ]
                ]
            ];

            $tab[] = [
                'id' => '163',
                'table' => PluginResourcesResignationReason::getTable(),
                'field' => 'name',
                'name' => PluginResourcesResignationReason::getTypeName(),
                'massiveaction' => false,
                'datatype' => 'dropdown',
                'joinparams' => [
                    'beforejoin' => [
                        'table' => self::getTable(),
                        'joinparams' => [
                            'jointype' => 'child',

                        ]
                    ]
                ]
            ];

            $tab[] = [
                'id' => '164',
                'table' => User::getTable(),
                'field' => 'name',
                'name' => __('Sales manager', 'resources'),

                'massiveaction' => false,
                'datatype' => 'dropdown',
                'joinparams' => [
                    'beforejoin' => [
                        'table' => self::getTable(),
                        'joinparams' => [
                            'jointype' => 'child',

                        ]
                    ]
                ]
            ];

            $tab[] = [
                'id'            => '165',
                'table'         => self::getTable(),
                'field'         => 'interview_date',
                'name'          => __('Interview date', 'resources'),
                'massiveaction' => false,
                'datatype'      => 'date',
                'joinparams'    => [
                    'jointype' => 'child'
                ]
            ];

            $tab[] = [
                'id'            => '166',
                'table'         => self::getTable(),
                'field'         => 'resignation_date',
                'name'          => __('Resignation date', 'resources'),
                'massiveaction' => false,
                'datatype'      => 'date',
                'joinparams'    => [
                    'jointype' => 'child'
                ]
            ];

            $tab[] = [
                'id'            => '167',
                'table'         => self::getTable(),
                'field'         => 'wished_leaving_date',
                'name'          => __('Wished leaving date', 'resources'),
                'massiveaction' => false,
                'datatype'      => 'date',
                'joinparams'    => [
                    'jointype' => 'child'
                ]
            ];

            $tab[] = [
                'id'            => '168',
                'table'         => self::getTable(),
                'field'         => 'pay_gap',
                'name'          => __('Pay gap','resources'),
                'massiveaction' => false,
                'datatype'      => 'bool',
                'joinparams'    => [
                    'jointype' => 'child'
                ]
            ];

            $tab[] = [
                'id'            => '169',
                'table'         => self::getTable(),
                'field'         => 'mission_lost',
                'name'          => __('Mission lost','resources'),
                'massiveaction' => false,
                'datatype'      => 'bool',
                'joinparams'    => [
                    'jointype' => 'child'
                ]
            ];

            $tab[] = [
                'id'            => '170',
                'table'         => self::getTable(),
                'field'         => 'company_name',
                'name'          => __('Company name', 'resources'),
                'massiveaction' => false,
                'datatype'      => 'text',
                'joinparams'    => [
                    'jointype' => 'child'
                ]
            ];
        }

        return $tab;
    }
    /**
     * @param $input
     *
     * @return array
     */
    function getHiddenFields($input) {

        $need           = [];
        $rulecollection = new PluginResourcesRuleContracttypeHiddenCollection($input['entities_id']);

        $fields = [];
        $fields = $rulecollection->processAllRules($input, $fields, []);


        $field = [];
        foreach ($fields as $key => $val) {
            $hidden = explode("hiddenfields_", $key);
            if (isset($hidden[1])) {
                $field[] = $hidden[1];
            }
        }


        return $field;
    }

    /**
     * @param $input
     *
     * @return array
     */
    function getReadonlyFields($input) {

        $need           = [];
        $rulecollection = new PluginResourcesRuleContracttypeReadonlyCollection($input['entities_id']);

        $fields = [];
        $fields = $rulecollection->processAllRules($input, $fields, []);


        $field = [];
        foreach ($fields as $key => $val) {
            $hidden = explode("readonlyfields_", $key);
            if (isset($hidden[1])) {
                $field[] = $hidden[1];
            }
        }


        return $field;
    }

    /**
     * @param $input
     *
     * @return array
     */
    function checkRequiredFields($input) {

        $need           = [];
        $rulecollection = new PluginResourcesRuleContracttypeCollection($input['entities_id']);

        $fields = [];
        $fields = $rulecollection->processAllRules($input, $fields, []);

        $rank = new PluginResourcesRank();

        $field = [];
        foreach ($fields as $key => $val) {
            $required = explode("requiredfields_", $key);
            if (isset($required[1])) {
                $field[] = $required[1];
            }
        }

        if (count($field) > 0) {
            foreach ($field as $key => $val) {
                if (!isset($input[$val])
                    || empty($input[$val])
                    || is_null($input[$val])
                    || $input[$val] == "NULL"
                ) {
                    if (!$rank->canCreate()
                        && in_array($val,
                            [
                                'interview_date', 'users_id','plugin_resources_workprofiles_id',
                                'plugin_resources_clients_id','resignation_date','wished_leaving_date',
                                'effective_leaving_date','plugin_resources_destinations_id',
                                'plugin_resources_leavingreasons_id','company_name','pay_gap','mission_lost'
                            ])
                    ) {
                    } else {
                        $need[] = $val;
                    }
                }
            }
        }

        return $need;
    }


}

