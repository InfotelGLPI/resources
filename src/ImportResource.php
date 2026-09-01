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

use CommonDBTM;
use CronTask;
use DateTime;
use DBConnection;
use DbUtils;
use Document;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Glpi\DBAL\QueryExpression;
use Glpi\Exception\Http\BadRequestHttpException;
use Html;
use Location;
use Migration;
use Profile_User;
use Session;
use Toolbox;
use User;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class ImportResource
 */
class ImportResource extends CommonDBTM
{
    public const UPDATE_RESOURCES = 0;

    // Pages
    public const VERIFY_FILE = 1;
    public const VERIFY_GLPI = 2;
    public const IDENTICAL = 0;
    public const DIFFERENT = 1;

    // Status
    public const NOT_IN_GLPI = 2;
    public const BEFORE = 0;
    public const AFTER = 1;

    // Orders
    public const DEFAULT_LIMIT = 20;
    public const FILE_READ_MAX_LINE = 50;
    public const IMPORT_RECOVERY_LIMIT = 50;

    // Limitation
    public const SELECTED_FILE_DROPDOWN_NAME = 'selected-file';
    // We read line by 50 iteration to don't use too much ram
    public const SELECTED_IMPORT_DROPDOWN_NAME = 'selected-import';
    // Number of import that can be recovered from the database at ones
    public const SESSION_IMPORT_ID = 'import-display-last-id';
    public const SESSION_IMPORT_START = 'import-display-last-start';
    public const FILE_IMPORTER = false;

    // Display types
    public const DISPLAY_HTML = 0;
    public const DISPLAY_STATISTICS = 1;
    public const DISPLAY_CSV = 2;

    public static $rightname = 'plugin_resources_importresources';
    public static $keyInOtherTables = 'plugin_resources_importresources_id';
    public static $currentStart;
    public static $currentVerifiedFile;

    /**
     * @param $name
     *
     * @return array
     */
    public static function cronInfo($name)
    {
        switch ($name) {
            case 'ResourceImport':
                return ['description' => __('Resource files imports', 'resources')];   // Optional
                break;
        }
        return [];
    }

    /**
     * Cron action
     *
     * @param  $task for log
     * @global $CFG_GLPI
     *
     * @global $DB
     */
    public static function cronResourceImport($task = null)
    {
        $CronTask = new CronTask();
        if ($CronTask->getFromDBbyName(ImportResource::class, 'ResourceImport')) {
            if ($CronTask->fields['state'] == CronTask::STATE_DISABLE) {
                return 0;
            }
        } else {
            return 0;
        }

        $import = new self();
        return $import->importResourcesFromCSVFile($task);
    }

    /**
     * @return string
     */
    public static function getLocationOfVerificationFiles()
    {
        return GLPI_PLUGIN_DOC_DIR . '/resources/import/verify';
    }

    /**
     * @return string
     */
    public static function getResourceImportFormUrl()
    {
        return ResourceImport::getFormURL(true);
    }

    /**
     * @return string
     */
    public static function getIndexUrl()
    {
        global $CFG_GLPI;
        return PLUGIN_RESOURCES_WEBDIR . '/front/importresource.php';
    }

    /**
     * Copy of html::showDateFieldWithoutDiv
     *
     * Underscore removed from name
     * Change self reference to Html
     *
     **/
    //   static function showDateFieldWithoutDiv($name, $options = []) {
    //      $p['value'] = '';
    //      $p['maybeempty'] = true;
    //      $p['canedit'] = true;
    //      $p['min'] = '';
    //      $p['max'] = '';
    //      $p['showyear'] = true;
    //      $p['display'] = true;
    //      $p['rand'] = mt_rand();
    //      $p['yearrange'] = '';
    //
    //      foreach ($options as $key => $val) {
    //         if (isset($p[$key])) {
    //            $p[$key] = $val;
    //         }
    //      }
    //      $output = "<input id='showdate" . $p['rand'] . "' type='text' size='10' name='$name' " . "value='" . Html::convDate($p['value']) . "'>";
    //      $output .= Html::hidden($name, ['value' => $p['value'], 'id' => "hiddendate" . $p['rand']]);
    //      if ($p['maybeempty'] && $p['canedit']) {
    //         $output .= "<span class='ti ti-circle-x pointer' title='" . __s('Clear') . "' id='resetdate" . $p['rand'] . "'>" . "<span class='sr-only'>" . __('Clear') . "</span></span>";
    //      }
    //
    //      $js = '$(function(){';
    //      if ($p['maybeempty'] && $p['canedit']) {
    //         $js .= "$('#resetdate" . $p['rand'] . "').click(function(){
    //                  $('#showdate" . $p['rand'] . "').val('');
    //                  $('#hiddendate" . $p['rand'] . "').val('');
    //                  });";
    //      }
    //      $js .= "$( '#showdate" . $p['rand'] . "' ).datepicker({
    //                  altField: '#hiddendate" . $p['rand'] . "',
    //                  altFormat: 'yy-mm-dd',
    //                  firstDay: 1,
    //                  showOtherMonths: true,
    //                  selectOtherMonths: true,
    //                  showButtonPanel: true,
    //                  changeMonth: true,
    //                  changeYear: true,
    //                  showOn: 'both',
    //                  showWeek: true,
    //                  buttonText: '<i class=\'far fa-calendar-alt\'></i>'";
    //
    //      if (!$p['canedit']) {
    //         $js .= ',disabled: true';
    //      }
    //
    //      if (!empty($p['min'])) {
    //         $js .= ",minDate: '" . self::convDate($p['min']) . "'";
    //      }
    //
    //      if (!empty($p['max'])) {
    //         $js .= ",maxDate: '" . self::convDate($p['max']) . "'";
    //      }
    //
    //      if (!empty($p['yearrange'])) {
    //         $js .= ",yearRange: '" . $p['yearrange'] . "'";
    //      }
    //
    //      switch ($_SESSION['glpidate_format']) {
    //         case 1 :
    //            $p['showyear'] ? $format = 'dd-mm-yy' : $format = 'dd-mm';
    //            break;
    //
    //         case 2 :
    //            $p['showyear'] ? $format = 'mm-dd-yy' : $format = 'mm-dd';
    //            break;
    //
    //         default :
    //            $p['showyear'] ? $format = 'yy-mm-dd' : $format = 'mm-dd';
    //      }
    //      $js .= ",dateFormat: '" . $format . "'";
    //
    //      $js .= "}).next('.ui-datepicker-trigger').addClass('pointer');";
    //      $js .= "});";
    //      $output .= Html::scriptBlock($js);
    //
    //      if ($p['display']) {
    //         echo $output;
    //         return $p['rand'];
    //      }
    //      return $output;
    //   }

    public function purgeDatabase()
    {
        global $DB;

        return $DB->delete(self::getTable(), [1]);
    }

    /**
     * @param $task
     *
     * @return bool
     */
    public function importResourcesFromCSVFile($task)
    {
        // glpi files folder
        $path = GLPI_PLUGIN_DOC_DIR . '/resources/import/';
        // List of files in path
        $files = scandir($path);
        // Exclude dot and dotdot
        $files = array_diff($files, ['.', '..']);

        foreach ($files as $file) {
            $importSuccess = false;

            $filePath = $path . $file;

            // Ignore directories
            if (is_dir($filePath)) {
                continue;
            }

            if (file_exists($filePath)) {
                // Initialize existingImports Array
                // Used to prevent multiple get imports from database
                // Speed up execution time
                $this->purgeDatabase();
                $this->resetExistingImportsArray();
                $this->initExistingImportsArray();

                $temp = $this->readCSVLines($filePath, 0, 1);
                $header = array_shift($temp);

                $importID = $this->checkHeader($header);

                if ($importID) {
                    $lines = $this->readCSVLines($filePath, 1, INF);

                    foreach ($lines as $line) {
                        $datas = $this->parseFileLine($header, $line, $importID);
                        $this->manageImport($datas, $importID);
                    }
                    $importSuccess = true;
                }
            }
            if ($importSuccess) {
                // Move file to done folder
                $output = $path . 'done/' . $file;
                rename(str_replace('\\', '/', $filePath), str_replace('\\', '/', $output));
            } else {
                // Move file to fail folder
                $output = $path . 'fail/' . $file;
                rename(str_replace('\\', '/', $filePath), str_replace('\\', '/', $output));
            }
        }

        return true;
    }

    /**
     * Insert or update imports
     *
     * @param $datas
     * @param $importID
     */
    public function manageImport($datas, $importID)
    {
        $importResourceID = $this->isExistingImportResourceByDataFromFile($datas);

        // Override data of existing importResource
        if (!is_null($importResourceID)) {
            $this->updateDatas($datas, $importResourceID);
        } else {
            // Create new Import Resource
            $importResourceInput = [
                'date_creation' => date('Y-m-d H:i:s'),
                Import::$keyInOtherTables => $importID,
            ];


            $datas2 = $datas;

            //INFOTEL

            // Find identifiers
            $firstLevelIdentifiers = [];
            $secondLevelIdentifiers = [];
            $allDatas = [];

            foreach ($datas2 as $data) {
                $ImportColumn = new ImportColumn();
                $ImportColumn->getFromDB($data['plugin_resources_importcolumns_id']);

                $element = [
                    'name' => $data['name'],
                    'value' => $data['value'],
                    'type' => $data['plugin_resources_importcolumns_id'],
                    'resource_column' => $ImportColumn->getField('resource_column'),
                ];

                $allDatas[] = $element;

                switch ($ImportColumn->getField('is_identifier')) {
                    case 1:
                        $firstLevelIdentifiers[] = $element;
                        break;
                    case 2:
                        $secondLevelIdentifiers[] = $element;
                        break;
                }
            }

            $status = null;

            $resourceID = $this->findResource($firstLevelIdentifiers);
            if (is_null($resourceID) && count($secondLevelIdentifiers) > 0) {
                $resourceID = $this->findResource($secondLevelIdentifiers);
            }

            $Resource = new Resource();
            if (!$resourceID) {
                $status = self::NOT_IN_GLPI;
            } else {
                // Test Field in resources
                if ($Resource->isDifferentFromImportResourceDatas($resourceID, $allDatas)) {
                    $status = self::DIFFERENT;
                } else {
                    $status = self::IDENTICAL;
                }
            }

            if ($status != self::IDENTICAL) {
                $newImportId = $this->add($importResourceInput);
                $importResourceData = new ImportResourceData();

                // Create new Import resource data
                foreach ($datas as $item) {
                    $importResourceDataInput = $importResourceData->prepareInput(
                        addslashes($item['name']),
                        addslashes($item['value']),
                        $newImportId,
                        $item['plugin_resources_importcolumns_id'],
                    );

                    $importResourceData->add($importResourceDataInput);
                }
            }
        }
    }

    /**
     * Search if a resource exist with the same identifiers
     *
     * @param $columnDatas
     * @return mixed|null
     */
    public function isExistingImportResourceByDataFromFile($columnDatas)
    {
        $ImportResourceData = new ImportResourceData();

        // List of existing imports
        $this->initExistingImportsArray();

        foreach ($this->existingImports as $existingImportResource) {
            $firstLevelIdentifiers = $ImportResourceData->getFromParentAndIdentifierLevel(
                $existingImportResource['id'],
                1,
            );

            $firstLevelIdentifierFounded = true;

            foreach ($firstLevelIdentifiers as $firstLevelIdentifier) {
                foreach ($columnDatas as $columnData) {
                    if ($columnData['name'] != $firstLevelIdentifier['name']) {
                        continue;
                    }

                    if ($columnData['value'] != $firstLevelIdentifier['value']) {
                        $firstLevelIdentifierFounded = false;
                        break;
                    }
                }
            }

            if ($firstLevelIdentifierFounded) {
                return $existingImportResource['id'];
            }

            $secondLevelIdentifiers = $ImportResourceData->getFromParentAndIdentifierLevel(
                $existingImportResource['id'],
                2,
            );
            $secondLevelIdentifierFounded = true;

            foreach ($secondLevelIdentifiers as $secondLevelIdentifier) {
                foreach ($columnDatas as $columnData) {
                    if ($columnData['name'] != $secondLevelIdentifier['name']) {
                        continue;
                    }

                    if ($columnData['value'] != $secondLevelIdentifier['value']) {
                        $secondLevelIdentifierFounded = false;
                    }
                }
            }

            if ($secondLevelIdentifierFounded) {
                return $existingImportResource['id'];
            }
        }
        return null;
    }

    /**
     * Update child Import Resources Datas
     *
     * @param $datas
     * @param $importResourceID
     */
    public function updateDatas($datas, $importResourceID)
    {
        $ImportResourceData = new ImportResourceData();

        $crit = [
            ImportResourceData::$items_id => $importResourceID,
        ];

        $importResourceDatas = $ImportResourceData->find($crit);

        foreach ($importResourceDatas as $importResourceData) {
            foreach ($datas as $data) {
                if ($data['name'] != $importResourceData['name']) {
                    continue;
                }

                if ($data['value'] == $importResourceData['value']) {
                    continue;
                }

                $input = [
                    ImportResourceData::getIndexName() => $importResourceData['id'],
                    'value' => addslashes($data['value']),
                ];

                $ImportResourceData->update($input);
                break;
            }
        }
    }

    /**
     * @param array $params
     */
    public function importFileToVerify($params = [])
    {
        // The uploaded filename is client-supplied: strip any directory component so a
        // crafted "../" value cannot escape the temporary upload directory.
        $safe_filename = basename($params['_filename'][0]);
        $filePath = GLPI_DOC_DIR . '/_tmp/' . $safe_filename;

        $temp = $this->readCSVLines($filePath, 0, 1);
        $header = array_shift($temp);

        $importId = $this->checkHeader($header);

        // Verify file compatibility
        if (is_null($importId)) {
            Session::addMessageAfterRedirect(__('The file does not match any template', 'resources'));
            return;
        }

        $fullpath = GLPI_TMP_DIR . "/" . $safe_filename;
        $filename = str_replace($params['_prefix_filename'], '', $safe_filename);
        $origin_filename = $filename;
        $exist = false;
        $i = 1;
        do {
            if (is_file(GLPI_PLUGIN_DOC_DIR . "/resources/import/verify/" . $filename)) {
                $exist = true;
                $filename = $i . "_" . $origin_filename;
                $i++;
            } else {
                $exist = false;
            }
        } while ($exist == true);


        Document::renameForce($fullpath, GLPI_PLUGIN_DOC_DIR . "/resources/import/verify/" . $filename);
    }

    /**
     * Verify the header of the csv file
     *
     * Return the index of the configured import that match to this header
     *
     * @param $header
     * @return bool
     */
    public function checkHeader(&$header)
    {
        $pluginResourcesImport = new Import();
        $ImportColumn = new ImportColumn();

        $imports = $pluginResourcesImport->find(['is_active' => 1]);

        foreach ($imports as $import) {
            $columns = $ImportColumn->find([
                Import::$keyInOtherTables => $import['id'],
            ]);

            // Test number of columns
            if (count($columns) != count($header)) {
                continue;
            }

            $foundImport = true;

            foreach ($columns as $column) {
                $foundColumnInHeader = false;

                //            foreach ($header as $item) {
                //               Toolbox::logWarning($item);
                //               Toolbox::logWarning($column['name']);
                $val = $column['name'];
                if (in_array($val, $header, true)) {
                    $foundColumnInHeader = true;
                    //                  break;
                }
                //            }
                // Import column not found in header
                if (!$foundColumnInHeader) {
                    $foundImport = false;
                    break;
                }
            }
            if ($foundImport) {
                return $import['id'];
            }
        }
        return false;
    }

    /**
     * this function return the number of rows of file
     *
     * @param $filePath
     * @return int
     */
    public function countRowsInFile($filePath)
    {
        if (file_exists($filePath)) {
            return count(file($filePath));
        }
        return null;
    }

    /**
     * Delete Import Resources and all child Import Resources Datas
     *
     * @param array $input
     * @param int $force
     * @param int $history
     * @return bool|void
     */
    public function delete(array $input, $force = 0, $history = 1)
    {
        if (!isset($input[self::getIndexName()])) {
            throw new BadRequestHttpException('Import resources not found');
        }

        $ImportResourceData = new ImportResourceData();

        $dataCrit = [
            self::$keyInOtherTables => $input[self::getIndexName()],
        ];

        $datas = $ImportResourceData->find($dataCrit);
        // Remove datas
        foreach ($datas as $data) {
            $ImportResourceData->delete([ImportResourceData::getIndexName() => $data['id']]);
        }

        // Remove item
        parent::delete($input, $force, $history);
    }

    /**
     * @param array $params
     */
    public function displayPageByType($params = [])
    {
        switch ($params['type']) {
            case self::VERIFY_FILE:
            case self::VERIFY_GLPI:
                $this->verifyFilePage($params);
                break;
            case self::UPDATE_RESOURCES:
                $this->importFilePage($params);
                break;
            default:
                throw new BadRequestHttpException();
        }
    }

    /**
     * Display the header of the view
     *
     * @param $type
     * @param $import
     */
    public function showHead($params)
    {
        // Capture GLPI widgets that echo directly, so they can be injected as |raw.
        $capture = static function (callable $renderer): string {
            ob_start();
            $renderer();
            return (string) ob_get_clean();
        };

        // FIRST LINE HEADER
        $colspan  = 21;
        $title    = '';
        $subtitle = '';
        switch ($params['type']) {
            case self::UPDATE_RESOURCES:
                $colspan  = 16;
                $title    = __('Update GLPI Resources', 'resources');
                $subtitle = sprintf(
                    __('%1$s : %2$s'),
                    __('Be careful, new resources will be created in the entity', 'resources'),
                    Dropdown::getDropdownName('glpi_entities', $_SESSION['glpiactive_entity']),
                );
                break;
            case self::VERIFY_FILE:
                $title = __('Compare File with GLPI Resources', 'resources');
                break;
            case self::VERIFY_GLPI:
                $title = __('Compare GLPI Resources with File', 'resources');
                break;
        }

        // SECOND LINE HEADER
        $selector_cells = [];
        switch ($params['type']) {
            case self::VERIFY_FILE:
            case self::VERIFY_GLPI:
                $selector_cells[] = $capture(fn() => self::showFileImporter());
                $selector_cells[] = $capture(fn() => self::showFileSelector($params));
                break;
            case self::UPDATE_RESOURCES:
                $selector_cells[] = $capture(fn() => self::showImportSelector($params));
                break;
        }

        // THIRD LINE HEADER: statistics counters, keyed by the span id they fill.
        $stats = [];
        switch ($params['type']) {
            case self::VERIFY_FILE:
                $stats = [
                    'identical' => __('Identical to GLPI', 'resources'),
                    'not_found' => __('Not in GLPI', 'resources'),
                    'different' => __('Different to GLPI', 'resources'),
                    'total'     => __('Total', 'resources'),
                ];
                break;
            case self::VERIFY_GLPI:
                $stats = [
                    'found_first_identifier'  => __('Found in the file with a top level id', 'resources'),
                    'found_second_identifier' => __('Found in file with second level id', 'resources'),
                    'not_found'               => __('Not in the file', 'resources'),
                    'total'                   => __('Total lines in the file', 'resources'),
                ];
                break;
        }

        $show_actions = !empty($params[self::SELECTED_FILE_DROPDOWN_NAME]);

        TemplateRenderer::getInstance()->display('@resources/import_head.html.twig', [
            'colspan'         => $colspan,
            'title'           => $title,
            'subtitle'        => $subtitle,
            'selector_cells'  => $selector_cells,
            'show_actions'    => $show_actions,
            'stats'           => $stats,
            'form_action'     => self::getFormURL(),
            'filename'        => $params[self::SELECTED_FILE_DROPDOWN_NAME] ?? '',
            'validate_label'  => __('Validate and pre-import file', 'resources'),
        ]);

        // The counters script is only meaningful when the #calculate button was rendered.
        if (!$show_actions || empty($stats)) {
            return;
        }

        $initElemJs     = '';
        $updateResultJs = '';
        foreach (array_keys($stats) as $key) {
            $initElemJs     .= "$('#{$key}').html('?');";
            $updateResultJs .= "$('#{$key}').html(results.{$key});";
        }

        $url  = PLUGIN_RESOURCES_WEBDIR . '/ajax/verifyCSVStatistics.php';
        $page = json_encode((string) $params['type'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        echo Html::scriptBlock(<<<JAVASCRIPT
            $('#calculate').click(function () {
                {$initElemJs}
                $('#ajax_loader').show();
                $.ajax({
                    url: '{$url}',
                    data: {
                        page: {$page},
                        file: $('[name="selected-file"] option:selected').text()
                    },
                    type: 'GET',
                    dataType: 'json',
                    success: function (data) {
                        let results = data;
                        {$updateResultJs}
                        $('#ajax_loader').hide();
                    },
                    error: function (xhr, status) {
                        console.error(xhr);
                        console.error(status);
                        $('#ajax_loader').hide();
                    }
                });
            });
            JAVASCRIPT);
    }

    /**
     * Display the error header
     *
     * @param $title
     * @param null $linkText
     * @param null $url
     */
    public function showErrorHeader($title, $linkText = null, $url = null)
    {
        TemplateRenderer::getInstance()->display('@resources/import_error_header.html.twig', [
            'title'     => $title,
            'link_text' => $linkText,
            'url'       => $url,
        ]);
    }

    /**
     * Describe the header columns of an import list.
     *
     * The columns are returned as data rather than as markup so the list templates can
     * render the header row themselves (see import_list_header.html.twig): a `type` of
     * `check_all` is the box that selects every line, `file` is a column read from the
     * imported CSV, and a column without a type is a plain label.
     *
     * @param array $params
     *
     * @return array<int, array<string, string>>
     */
    public function getListHeaderColumns($params)
    {
        $columns = [];

        switch ($params['type']) {
            case self::UPDATE_RESOURCES:
                $columns[] = ['type' => 'check_all'];
                $columns[] = ['label' => __('Resource', 'resources')];
                $csv_icon  = PLUGIN_RESOURCES_WEBDIR . "/pics/csv_file.png";
                $csv_title = __("Data from file", "resources");
                foreach ($this->getImportColumnNames($params['import']) as $name) {
                    $columns[] = [
                        'type'       => 'file',
                        'label'      => $name,
                        'icon'       => $csv_icon,
                        'icon_title' => $csv_title,
                    ];
                }
                break;
            case self::VERIFY_FILE:
                foreach ($params['titles'] as $title) {
                    $columns[] = ['label' => $this->encodeUtf8($title)];
                }
                $columns[] = ['label' => __('Status')];
                break;
            case self::VERIFY_GLPI:
                $columns[] = ['label' => 'ID'];
                $columns[] = ['label' => __('Last name')];
                $columns[] = ['label' => __('First name')];
                $columns[] = ['label' => __('Identification', 'resources')];
                $columns[] = ['label' => __('Informations from file', 'resources')];
                break;
        }

        return $columns;
    }

    public function validateDate($date, $delimiter = "/")
    {
        $test_arr = explode($delimiter, $date);
        if (count($test_arr) == 3) {
            if (checkdate($test_arr[0], $test_arr[1], $test_arr[2]) // English date
                || checkdate($test_arr[1], $test_arr[0], $test_arr[2])) { // French date
                return true;
            }
        }
        return false;
    }

    /**
     * Display an import line
     *
     * @param $importResourceId
     * @param $type
     * @param $resourceID
     */
    public function showOne($importResourceId, $type, $resourceID = null, $borderColor = false)
    {
        /*
       The date need to be send to form are :
          - ResourceID
          - Data
             - resource_column
             - value
        */

        $inputs = "import[$importResourceId][%s]";

        $ImportResourceData = new ImportResourceData();

        // Get all import data
        $datas = $ImportResourceData->getFromParentAndIdentifierLevel($importResourceId, null, ['resource_column']);

        $Resource = new Resource();
        $hasResource = $Resource->getFromDB($resourceID);

        // Capture GLPI widgets that echo directly, so they can be injected as |raw.
        $capture = static function (callable $renderer): string {
            ob_start();
            $renderer();
            return (string) ob_get_clean();
        };

        if ($hasResource) {
            $link = Toolbox::getItemTypeFormURL(Resource::class) . "?id=" . (int) $resourceID;
            $resource_link = '<a href="' . htmlescape($link) . '">' . htmlescape((string) $resourceID) . '</a>';
        } else {
            $resource_link = htmlescape(__('New resource', 'resources'));
        }

        $numberOfOthersValues = 0;
        foreach ($datas as $data) {
            if ($data['resource_column'] == 10) {
                $numberOfOthersValues++;
            }
        }

        $cells = [];
        foreach ($datas as $data) {
            $hValue = sprintf($inputs, $data['id']);

            $oldValues = $resourceID && $Resource->hasDifferenciesWithValueByDataNameID(
                $resourceID,
                $data['resource_column'],
                $data['name'],
                $data['value'],
            );

            $cell = [
                'hidden'  => '',
                'has_old' => (bool) $oldValues,
                'old'     => '',
                'widget'  => '',
            ];

            switch ($data['resource_column']) {
                case 0:
                case 1:
                    $cell['hidden'] = Html::hidden($hValue, ['value' => $data['value']]);
                    $cell['old'] = htmlescape((string) $Resource->getFieldByDataNameID($data['resource_column']));
                    $cell['widget'] = htmlescape((string) $data['value']);
                    break;
                case 2:
                    if ($oldValues) {
                        $ContractType = new ContractType();
                        $ContractType->getFromDB($Resource->getFieldByDataNameID($data['resource_column']));
                        $cell['old'] = htmlescape($ContractType->getName());
                    }
                    if ($data['value'] != -1) {
                        $cell['widget'] = $capture(fn() => Dropdown::show(ContractType::class, [
                            'name' => $hValue,
                            'value' => $data['value'],
                            'entity' => $_SESSION['glpiactive_entity'],
                            'entity_sons' => true,
                        ]));
                    }
                    break;
                case 3:
                    if ($oldValues) {
                        $user = new User();
                        $user->getFromDB($Resource->getFieldByDataNameID($data['resource_column']));
                        $cell['old'] = htmlescape($user->getName());
                    }
                    $cell['widget'] = $capture(fn() => User::dropdown([
                        'name' => $hValue,
                        'value' => $data['value'],
                        'entity' => $_SESSION['glpiactive_entity'],
                        'entity_sons' => true,
                        'right' => 'all',
                    ]));
                    break;
                case 4:
                    if ($oldValues) {
                        $oldLocation = new Location();
                        $oldLocation->getFromDB($Resource->getFieldByDataNameID($data['resource_column']));
                        $cell['old'] = htmlescape((string) $oldLocation->getField('completename'));
                    }
                    $cell['widget'] = $capture(fn() => Dropdown::show(Location::class, [
                        'name' => $hValue,
                        'value' => ($data['value'] == -1) ? 0 : $data['value'],
                        'entity' => $_SESSION['glpiactive_entity'],
                        'entity_sons' => true,
                    ]));
                    break;
                case 5:
                case 9:
                    if ($oldValues) {
                        $user = new User();
                        $user->getFromDB($Resource->getFieldByDataNameID($data['resource_column']));
                        $cell['old'] = htmlescape($user->getName());
                    }
                    $cell['widget'] = $this->captureManagerDropdown(
                        $data['resource_column'] == 5 ? 'resource_manager' : 'sales_manager',
                        $hValue,
                        $data['value'],
                    );
                    break;
                case 6:
                    if ($oldValues) {
                        $Department = new Department();
                        $Department->getFromDB($Resource->getFieldByDataNameID($data['resource_column']));
                        $cell['old'] = htmlescape($Department->getName());
                    }
                    $cell['widget'] = $capture(fn() => Dropdown::show(Department::class, [
                        'name' => $hValue,
                        'value' => $data['value'],
                        'entity' => $_SESSION['glpiactive_entity'],
                        'entity_sons' => true,
                    ]));
                    break;
                case 7:
                case 8:
                    $cell['old'] = htmlescape((string) $Resource->getFieldByDataNameID($data['resource_column']));
                    $cell['widget'] = $capture(fn() => Html::showDateField($hValue, ['value' => $data['value']]));
                    break;
                case 10:
                    // "Other" values are shown as their own one-row comparison table.
                    $cell['hidden'] = Html::hidden($hValue, ['value' => $data['value']]);
                    $cell['has_old'] = false;

                    $previous = '';
                    if ($oldValues) {
                        $previous = htmlescape(
                            (string) $Resource->getResourceImportValueByName($resourceID, $data['name']),
                        );
                    }
                    $cell['widget'] = TemplateRenderer::getInstance()->render(
                        '@resources/import_other_value.html.twig',
                        [
                            'name'     => $data['name'],
                            'previous' => $previous,
                            'current'  => $data['value'],
                        ],
                    );
                    break;
                case 11:
                    if ($oldValues) {
                        $Team = new Team();
                        $Team->getFromDB($Resource->getFieldByDataNameID($data['resource_column']));
                        $cell['old'] = htmlescape($Team->getName());
                    }
                    $cell['widget'] = $capture(fn() => Dropdown::show(Team::class, [
                        'name' => $hValue,
                        'value' => $data['value'],
                        'entity' => $_SESSION['glpiactive_entity'],
                        'entity_sons' => true,
                    ]));
                    break;
            }

            $cells[] = $cell;
        }

        TemplateRenderer::getInstance()->display('@resources/import_one_row.html.twig', [
            'border_color'  => is_null($borderColor) ? '' : $borderColor,
            'checkbox'      => $capture(static fn() => Html::showCheckbox(
                ["name" => "select[" . $importResourceId . "]"],
            )),
            'resource_link' => $resource_link,
            'cells'         => $cells,
            'old_style'     => 'display:block;border-bottom:solid 1px red',
            'new_style'     => 'display:block;border-top:solid 1px green;margin-top:1px;',
        ]);
    }

    /**
     * Build the manager dropdown: restricted to the profiles configured for the given
     * setting when one is set, a plain user dropdown otherwise.
     *
     * @param string $configField 'resource_manager' or 'sales_manager'
     * @param string $name        input name
     * @param mixed  $value       currently selected user
     *
     * @return string ready-to-render HTML
     */
    private function captureManagerDropdown(string $configField, string $name, $value): string
    {
        $config = new Config();

        if ($config->getField($configField) == "") {
            ob_start();
            User::dropdown([
                'name' => $name,
                'value' => $value,
                'entity' => $_SESSION['glpiactive_entity'],
                'entity_sons' => true,
                'right' => 'all',
            ]);
            return (string) ob_get_clean();
        }

        $tableProfileUser = Profile_User::getTable();

        $prof = [];
        foreach (json_decode($config->getField($configField)) as $profs) {
            $prof[$profs] = $profs;
        }
        $ids = join("','", $prof);

        $restrict = getEntitiesRestrictCriteria(
            $tableProfileUser,
            'entities_id',
            $_SESSION['glpiactive_entity'],
            true,
        );
        $restrict = array_merge([$tableProfileUser . ".profiles_id" => [$ids]], $restrict);

        $profile_User = new Profile_User();
        $used = [];
        foreach ($profile_User->find($restrict) as $profileUser) {
            $user = new User();
            $user->getFromDB($profileUser["users_id"]);
            $used[$profileUser["users_id"]] = $user->getFriendlyName();
        }

        ob_start();
        Dropdown::showFromArray($name, $used, ['value' => $value, 'display_emptychoice' => true]);
        return (string) ob_get_clean();
    }

    private function resetExistingImportsArray()
    {
        $this->existingImports = null;
    }

    private function initExistingImportsArray()
    {
        if (is_null($this->existingImports)) {
            $this->existingImports = $this->find();
        }
    }

    /**
     * @param $value
     *
     * @return array|false|string|string[]|null
     */
    private function encodeUtf8($value)
    {
        $detectEncoding = mb_detect_encoding($value, 'ASCII,UTF-8,ISO-8859-15');

        if ($detectEncoding) {
            return mb_convert_encoding($value, "UTF-8", $detectEncoding);
        }
        Toolbox::logDebug("Can't detect encoding of string");
        return $value;
    }

    /**
     * @param array $params
     */
    private function verifyFilePage($params = [])
    {
        $defaultFileSelected = "";
        if (isset($params[self::SELECTED_FILE_DROPDOWN_NAME]) && !empty($params[self::SELECTED_FILE_DROPDOWN_NAME])) {
            $defaultFileSelected = $params[self::SELECTED_FILE_DROPDOWN_NAME];
        }

        $locationOfFiles = self::getLocationOfVerificationFiles();

        $params['location'] = $locationOfFiles;
        $params['default'] = $defaultFileSelected;

        // The head and the list echo directly: buffer them so the shell owns the markup.
        ob_start();
        $this->showHead($params);

        // Verify user select a file
        if (isset($params[self::SELECTED_FILE_DROPDOWN_NAME]) && !empty($params[self::SELECTED_FILE_DROPDOWN_NAME])) {
            // Defense in depth: never trust the caller-supplied file name for a filesystem
            // path. Reduce it to its basename and confirm the resolved path stays within
            // the verification directory before reading it back for display (path traversal
            // / arbitrary file read guard).
            $baseDir  = self::getLocationOfVerificationFiles();
            $safeName = basename((string) $params[self::SELECTED_FILE_DROPDOWN_NAME]);
            $params[self::SELECTED_FILE_DROPDOWN_NAME] = $safeName;
            $absoluteFilePath = $baseDir . "/" . $safeName;

            $realBase = realpath($baseDir);
            $realPath = realpath($absoluteFilePath);

            // Verify file exist and is confined to the verification directory
            if ($realBase === false
                || $realPath === false
                || strpos($realPath, $realBase . DIRECTORY_SEPARATOR) !== 0) {
                $title = __("File not found", "resources");
                self::showErrorHeader($title);
            } else {
                $temp = $this->readCSVLines($absoluteFilePath, 0, 1);
                $header = array_shift($temp);

                $importId = $this->checkHeader($header);

                // Verify file header match a configured import
                if (!$importId) {
                    $title = __("The selected file doesn't match any configured import", "resources");
                    self::showErrorHeader($title);
                } else {
                    $listParams = $this->fillVerifyParams(
                        $params['start'],
                        $params['limit'],
                        $params['type'],
                        $absoluteFilePath,
                        $importId,
                        $params[self::SELECTED_FILE_DROPDOWN_NAME],
                        self::DISPLAY_HTML,
                    );

                    switch ($params['type']) {
                        case self::VERIFY_FILE:
                            self::showVerificationFileList($listParams);
                            break;
                        case self::VERIFY_GLPI:
                            self::showVerificationGLPIFromFileList($listParams);
                            break;
                    }
                }
            }
        }

        TemplateRenderer::getInstance()->display('@resources/import_page.html.twig', [
            'show_loader' => true,
            'content'     => (string) ob_get_clean(),
        ]);
    }

    private function showFileImporter()
    {
        $file_widget = '';
        ob_start();
        Html::file();
        $file_widget = (string) ob_get_clean();

        TemplateRenderer::getInstance()->display('@resources/import_file_upload.html.twig', [
            'form_action'  => self::getFormURL(),
            'file_widget'  => $file_widget,
            'label_import' => __('Import file', 'resources'),
        ]);
    }

    /**
     * @param $params
     */
    private function showFileSelector($params)
    {
        $locationOfFiles = $params['location'];
        $type = $params['type'];
        $defaultFileSelected = $params['default'];

        $action = ImportResource::getIndexUrl();
        $action .= "?type=" . $type;

        $dropdownParams = [
            'name' => self::SELECTED_FILE_DROPDOWN_NAME,
            'folder' => $locationOfFiles,
            'default' => $defaultFileSelected,
        ];

        ob_start();
        self::dropdownFileInFolder($dropdownParams);
        $dropdown = (string) ob_get_clean();

        TemplateRenderer::getInstance()->display('@resources/import_selector_form.html.twig', [
            'form_action' => $action,
            'dropdown'    => $dropdown,
            'buttons'     => [
                ['name' => 'verify', 'label' => __('Verify file', 'resources')],
                [
                    'name'    => 'delete_file',
                    'label'   => __('Delete file', 'resources'),
                    'class'   => 'btn-outline-danger',
                    'confirm' => __('Confirm the deletion of this file?', 'resources'),
                ],
            ],
        ]);
    }

    /**
     * TOTO Recursive not implemented yet
     *
     * @param $name
     * @param $absoluteFolderPath
     * @param null $defaultValue
     * @param bool $recursive
     */
    private function dropdownFileInFolder($params)
    {
        $name = $params['name'];
        $defaultValue = isset($params['default']) ? $params['default'] : null;
        $absoluteFolderPath = $params['folder'];

        if (!is_null($absoluteFolderPath) && !empty($absoluteFolderPath) && file_exists($absoluteFolderPath)) {
            // List of files in path
            $files = scandir($absoluteFolderPath);
            // Exclude dot and dotdot
            $files = array_diff($files, ['.', '..']);

            foreach ($files as $key => $file) {
                // Ignore directories
                if (is_dir($absoluteFolderPath . $file)) {
                    unset($files[$key]);
                }
            }

            if (empty($files)) {
                TemplateRenderer::getInstance()->display('@resources/alert_message.html.twig', [
                    'level'   => 'warning',
                    'message' => __("No file to compare", "resources"),
                ]);
            } else {
                $names = [];

                foreach ($files as $file) {
                    if (is_null($defaultValue)) {
                        $defaultValue = $file;
                    }
                    $names[$file] = $file;
                }

                Dropdown::showFromArray($name, $names, [
                    'value' => $defaultValue,
                ]);
            }
        } else {
            TemplateRenderer::getInstance()->display('@resources/alert_message.html.twig', [
                'level'   => 'warning',
                'message' => __("The folder you expected to display content doesn't exist.", "resources"),
            ]);
        }
    }

    /**
     * @param $params
     */
    private function showImportSelector($params)
    {

        $type = $params['type'];
        $imports = $params['imports'];

        if (!count($imports)) {
            $title = __("No imports configured", "resources");
            $linkText = __("Configure a new import", "resources");
            $link = PLUGIN_RESOURCES_WEBDIR . "/front/import.php";

            self::showErrorHeader($title, $linkText, $link);
        } else {
            $action = ImportResource::getIndexUrl();
            $action .= "?type=" . $type;

            ob_start();
            self::dropdownImports($params);
            $dropdown = (string) ob_get_clean();

            TemplateRenderer::getInstance()->display('@resources/import_selector_form.html.twig', [
                'form_action' => $action,
                'dropdown'    => $dropdown,
                'buttons'     => [
                    ['name' => 'select', 'label' => __('Choose', 'resources')],
                ],
            ]);
        }
    }

    /**
     * @param $params
     */
    private function dropdownImports($params)
    {
        $defaultValue = isset($params['selected-import']) ? $params['selected-import'] : null;

        $pluginResourcesImport = new Import();

        $names = [];
        $results = $pluginResourcesImport->find(['is_active' => 1]);

        foreach ($results as $result) {
            $names[$result['name']] = $result['name'];
        }

        Dropdown::showFromArray(self::SELECTED_IMPORT_DROPDOWN_NAME, $names, [
            'value' => $defaultValue,
        ]);
    }

    public function fillVerifyParams($start, $limit, $type, $filePath, $importId, $fileSelected, $display)
    {
        return [
            'start' => $start,
            'limit' => $limit,
            'type' => $type,
            'file-path' => $filePath,
            'import-id' => $importId,
            self::SELECTED_FILE_DROPDOWN_NAME => $fileSelected,
            'display' => $display,
        ];
    }

    /**
     * @param array $params
     */
    public function showVerificationFileList(array $params)
    {
        $start = $params['start'];
        $type = $params['type'];
        $limit = $params['limit'];
        $importId = $params['import-id'];
        $absoluteFilePath = $params['file-path'];
        $display = $params['display'];

        // Number of lines in csv - header
        $nbLines = $this->countCSVLines($absoluteFilePath) - 1;

        // The first line is header
        $startLine = ($start === 0) ? 1 : $start;
        $limitLine = ($start === 0) ? $limit + 1 : $limit;

        $lines = $this->readCSVLines($absoluteFilePath, $startLine, $limitLine);

        // Recover the header of file FIRST LINE
        $temp = $this->readCSVLines($absoluteFilePath, 0, 1);
        $header = array_shift($temp);

        $result = [
            'identical' => 0,
            'different' => 0,
            'not_found' => 0,
            'total' => 0,
        ];
        $entries = [];

        foreach ($lines as $line) {
            $datas = self::parseFileLine($header, $line, $importId);

            // Find identifiers
            $firstLevelIdentifiers = [];
            $secondLevelIdentifiers = [];
            $allDatas = [];

            foreach ($datas as $data) {
                $ImportColumn = new ImportColumn();
                $ImportColumn->getFromDB($data['plugin_resources_importcolumns_id']);

                $element = [
                    'name' => $data['name'],
                    'value' => $data['value'],
                    'type' => $data['plugin_resources_importcolumns_id'],
                    'resource_column' => $ImportColumn->getField('resource_column'),
                ];

                $allDatas[] = $element;

                switch ($ImportColumn->getField('is_identifier')) {
                    case 1:
                        $firstLevelIdentifiers[] = $element;
                        break;
                    case 2:
                        $secondLevelIdentifiers[] = $element;
                        break;
                }
            }

            $resourceID = $this->findResource($firstLevelIdentifiers);
            if (is_null($resourceID) && count($secondLevelIdentifiers) > 0) {
                $resourceID = $this->findResource($secondLevelIdentifiers);
            }

            $Resource = new Resource();

            // Status is the same in both modes: resolve it once.
            if (!$resourceID) {
                $status = self::NOT_IN_GLPI;
            } elseif ($Resource->isDifferentFromImportResourceDatas($resourceID, $allDatas)) {
                $status = self::DIFFERENT;
            } else {
                $status = self::IDENTICAL;
            }

            if ($display === self::DISPLAY_STATISTICS) {
                $result['total']++;
                switch ($status) {
                    case self::NOT_IN_GLPI:
                        $result['not_found']++;
                        break;
                    case self::DIFFERENT:
                        $result['different']++;
                        break;
                    default:
                        $result['identical']++;
                }
                continue;
            }

            $cells = [];
            foreach ($allDatas as $data) {
                $different = !$resourceID
                    || $Resource->isDifferentFromImportResourceData($resourceID, $data);
                $cells[] = [
                    'class'   => $different ? 'text-danger' : '',
                    'content' => self::formatImportValue($data),
                ];
            }
            $cells[] = ['content' => htmlescape((string) self::getStatusTitle($status))];

            $entries[] = ['cells' => $cells];
        }

        if ($display === self::DISPLAY_STATISTICS) {
            echo json_encode($result);
            return;
        }

        // Generate pager parameters
        $parameters = "type=" . $type;
        $parameters .= "&" . self::SELECTED_FILE_DROPDOWN_NAME;
        $parameters .= "=" . $params[self::SELECTED_FILE_DROPDOWN_NAME];

        ob_start();
        Html::printPager($start, $nbLines, self::getIndexUrl(), $parameters);
        $pager = (string) ob_get_clean();

        $header_columns = $this->getListHeaderColumns(['type' => $params['type'], 'titles' => $header]);

        TemplateRenderer::getInstance()->display('@resources/import_verification_list.html.twig', [
            'form_action' => self::getIndexUrl() . "?" . $parameters,
            'pager'       => $pager,
            'header_columns' => $header_columns,
            'entries'     => $entries,
        ]);
    }

    /**
     * Render one imported value: a link when it references a GLPI item, plain text
     * otherwise. The returned string is HTML-escaped and safe to inject as |raw.
     */
    private static function formatImportValue(array $data): string
    {
        if ($data['value'] == -1) {
            return '';
        }

        $dataType = $data['resource_column'] > count(Resource::getDataTypes())
            ? null
            : Resource::getDataType($data['resource_column']);

        // Each itemtype is instantiated explicitly: dynamic instantiation from a string
        // is forbidden by the GLPI PHPStan ruleset.
        switch ($dataType) {
            case User::class:
                $label = getUserName($data['value']);
                $url   = User::getFormURLWithID($data['value']);
                break;
            case Location::class:
                $item = new Location();
                $item->getFromDB($data['value']);
                $label = $item->getField('name');
                $url   = Location::getFormURLWithID($data['value']);
                break;
            case Department::class:
                $item = new Department();
                $item->getFromDB($data['value']);
                $label = $item->getField('name');
                $url   = Department::getFormURLWithID($data['value']);
                break;
            case Team::class:
                $item = new Team();
                $item->getFromDB($data['value']);
                $label = $item->getField('name');
                $url   = Team::getFormURLWithID($data['value']);
                break;
            case ContractType::class:
                $item = new ContractType();
                $item->getFromDB($data['value']);
                $label = $item->getField('name');
                $url   = ContractType::getFormURLWithID($data['value']);
                break;
            default:
                return htmlescape((string) $data['value']);
        }

        return '<a href="' . htmlescape($url) . '">' . htmlescape((string) $label) . '</a>';
    }

    /**
     * @param $absoluteFilePath
     *
     * @return int
     */
    private function countCSVLines($absoluteFilePath)
    {
        $nb = 0;
        if (file_exists($absoluteFilePath)) {
            $handle = fopen($absoluteFilePath, 'r');
            while (($line = fgetcsv($handle, 1000, ";")) !== false) {
                $nb++;
            }
        }
        return $nb;
    }

    /**
     * Read lines in csv file
     * Carefull the first line is the header
     *
     * @param $absoluteFilePath
     * @param $start
     * @param $limit
     */
    public function readCSVLines($absoluteFilePath, $start, $limit = INF)
    {
        $lines = [];
        if (file_exists($absoluteFilePath)) {
            $handle = fopen($absoluteFilePath, 'r');

            $lineIndex = 0;
            while (($line = fgetcsv($handle, 1024, ';')) !== false) {
                // Loop through each field
                foreach ($line as &$field) {
                    // Remove any invalid or hidden characters
                    $field = $this->encodeUtf8($field);
                }


                if ($lineIndex >= $start) {
                    // Read line
                    $lines[] = $line;
                }

                // End condition
                if ($limit != INF && $lineIndex == $start + $limit) {
                    break;
                }

                $lineIndex++;
            }
            fclose($handle);
        }
        return $lines;
    }

    /**
     * Names of the CSV columns mapped by an import, in mapping order.
     *
     * @param array|null $import
     *
     * @return array<int, string>
     */
    private function getImportColumnNames($import)
    {
        if (is_null($import)) {
            return [];
        }

        $resourceColumnNames = Resource::getDataNames();

        $ImportColumn  = new ImportColumn();
        $importColumns = $ImportColumn->getColumnsByImport($import['id'], true);

        $names = [];
        foreach ($importColumns as $importColumn) {
            $names[] = $resourceColumnNames[$importColumn['resource_column']] ?? '';
        }

        return $names;
    }

    /**
     * Transform data in csv file to match glpi data types
     *
     * @param $header
     * @param $line
     * @param $importID
     * @return array
     */
    public function parseFileLine($header, $line, $importID)
    {
        $column = new ImportColumn();
        $datas = [];

        $headerIndex = 0;
        foreach ($header as $columnName) {
            $utf8ColumnName = addslashes($columnName);
            $utf8ColumnName = $this->encodeUtf8($utf8ColumnName);

            $crit = [
                'name' => $utf8ColumnName,
                Import::$keyInOtherTables => $importID,
            ];

            if (!$column->getFromDBByCrit($crit)) {
                throw new BadRequestHttpException("Import column not found");
            }

            $outType = Resource::getDataType($column->getField('resource_column'));

            $value = null;
            if ($this->isCastable($column->getField('type'), $outType)) {
                $value = $this->castValue($line[$headerIndex], $column->getField('type'), $outType);
            }

            $datas[] = [
                "name" => $column->getName(),
                "value" => $value,
                "plugin_resources_importcolumns_id" => intval($column->getID()),
            ];

            $headerIndex++;
        }

        return $datas;
    }

    /**
     * Test if input type is castable to output type
     *
     * @param $in
     * @param $out
     * @return bool
     */
    private function isCastable($in, $out)
    {
        switch ($in) {
            case 0: //Integer
                switch ($out) {
                    case ContractType::class:
                    case User::class:
                    case Location::class:
                    case Department::class:
                    case Team::class:
                    case "String":
                        return true;
                    case "Date":
                        return false;
                }
                // no break
            case 1: //Decimal
                switch ($out) {
                    case "String":
                        return true;
                    case "Date":
                    case Department::class:
                    case Team::class:
                    case Location::class:
                    case User::class:
                    case ContractType::class:
                        return false;
                }
                // no break
            case 2: //String
                switch ($out) {
                    case Department::class:
                    case Team::class:
                    case Location::class:
                    case User::class:
                    case ContractType::class:
                    case "String":
                        return true;
                    case "Date":
                        return false;
                }
                // no break
            case 3: //Date
                switch ($out) {
                    case "Date":
                    case "String":
                        return true;
                    case User::class:
                    case Location::class:
                    case Department::class:
                    case Team::class:
                    case ContractType::class:
                        return false;
                }
        }
        return false;
    }

    /**
     * Cast value from input type to output type
     *
     * @param $value
     * @param $in
     * @param $out
     * @return int|string|null
     */
    private function castValue($value, $in, $out)
    {
        switch ($in) {
            case 0: //Integer
                switch ($out) {
                    case "String":
                        return "$value";
                    case ContractType::class:
                    case User::class:
                    case Location::class:
                    case Department::class:
                    case Team::class:
                        return $value;
                }
                // no break
            case 1: //Decimal
                switch ($out) {
                    case "String":
                        return $value;
                }
                // no break
            case 2: //String
                $utf8String = $this->encodeUtf8($value);

                switch ($out) {
                    case "String":
                        return $utf8String;
                    case ContractType::class:
                        // CAREFUL : ContractType is translated in database
                        $objectID = $this->getObjectIDByClassNameAndName(ContractType::class, $utf8String);

                        if ($objectID === 0 || $objectID === -1) {
                            // TODO find an alternative to find in code, maybe alternative_name variable with an array ?
                            $ContractTypeDBTM = new ContractType();
                            //                     if (count($contracts = $ContractTypeDBTM->find(['code' => $utf8String])) > 0) {
                            //                        if(count($contracts) == 1) {
                            //                           $objectID = $contracts[0]['id'];
                            //                        } else {
                            //                           $objectID = $ContractTypeDBTM->getID();
                            //                        }
                            //
                            //                     }
                            if ($ContractTypeDBTM->getFromDBByCrit(['code' => $utf8String])) {
                                $objectID = $ContractTypeDBTM->getID();
                            }
                        }
                        return $objectID;
                    case Team::class:
                        $objectID = $this->getObjectIDByClassNameAndName(Team::class, $utf8String);

                        if ($objectID === 0 || $objectID === -1) {
                            // TODO find an alternative to find in code, maybe alternative_name variable with an array ?
                            $TeamDBTM = new Team();
                            if ($TeamDBTM->getFromDBByCrit(['code' => $utf8String])) {
                                $objectID = $TeamDBTM->getID();
                            }
                        }
                        return $objectID;
                    case User::class:
                        $userList = $this->getUserByFullname($utf8String);

                        if (count($userList)) {
                            $u = array_pop($userList);
                            return $u['id'];
                        }

                        return -1;
                    case Location::class:
                        return $this->getObjectIDByClassNameAndName(Location::class, $utf8String);
                    case Department::class:
                        return $this->getObjectIDByClassNameAndName(Department::class, $utf8String);
                }
                // no break
            case 3: //Date
                switch ($out) {
                    case "String":
                        return $value;
                    case "Date":
                        return $this->formatDate($value);
                }
        }
        return null;
    }

    /**
     * Recover object from database by class and name
     *
     * @param $classname
     * @param $name
     * @return int
     */
    private function getObjectIDByClassNameAndName($classname, $name)
    {
        $item = new $classname();

        if ($item) {
            $item->getFromDBByCrit(['name' => $name]);
            return $item->getID();
        }

        // 0 is the default ID of items
        return 0;
    }

    /**
     * The fullname must be firstname + 1 space + lastname
     *
     * @param $fullname
     * @return array
     */
    private function getUserByFullname($fullname)
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => 'id',
            'FROM'   => User::getTable(),
            'WHERE'  => [
                new QueryExpression(
                    'CONCAT(' . $DB->quoteName('firstname') . ', ' . $DB->quoteValue(' ')
                    . ', ' . $DB->quoteName('realname') . ') LIKE ' . $DB->quoteValue($fullname),
                ),
            ],
        ]);
        $result = [];

        foreach ($iterator as $data) {
            $result[] = $data;
        }
        return $result;
    }

    /**
     * @param $value
     *
     * @return string|null
     */
    private function formatDate($value)
    {
        if (self::validateDate($value)) {
            return DateTime::createFromFormat('d/m/Y', $value)->format('Y-m-d');
        } else {
            return null;
        }
    }

    /**
     * BE CAREFULL IDENTIFIERS VALUE CANNOT BE EMPTY
     *
     * @param $identifiers
     * @return |null
     */
    public function findResource($identifiers)
    {
        global $DB;
        $where    = [];
        $needLink = false;
        $Resource = new Resource();
        foreach ($identifiers as $identifier) {
            if ($identifier['resource_column'] != "10") {
                $column = $Resource->getResourceColumnNameFromDataNameID($identifier['resource_column']);
                $where[] = ["r.$column" => $identifier['value']];
            } else {
                $needLink = true;
                $where[]  = ['rd.name' => $identifier['name']];
                $where[]  = ['rd.value' => $identifier['value']];
            }
        }

        $criteria = [
            'SELECT' => 'r.id',
            'FROM'   => Resource::getTable() . ' AS r',
            'WHERE'  => $where,
        ];

        if ($needLink) {
            $criteria['INNER JOIN'] = [
                ResourceImport::getTable() . ' AS rd' => [
                    'ON' => [
                        'rd' => ResourceImport::$items_id,
                        'r'  => 'id',
                    ],
                ],
            ];
        }

        foreach ($DB->request($criteria) as $data) {
            return $data['id'];
        }

        return false;
    }

    /**
     * @param $status
     *
     * @return string
     */
    private function getStatusTitle($status)
    {
        switch ($status) {
            case self::IDENTICAL:
                return __('Identical to GLPI', 'resources');
            case self::DIFFERENT:
                return __('Different to GLPI', 'resources');
            case self::NOT_IN_GLPI:
                return __('Not in GLPI', 'resources');
        }
    }

    /**
     * @param array $params
     */
    public function showVerificationGLPIFromFileList(array $params)
    {
        $start = $params['start'];
        $type = $params['type'];
        $limit = $params['limit'];
        $importId = $params['import-id'];
        $absoluteFilePath = $params['file-path'];
        $display = $params['display'];

        // Resource identifiers
        $ImportColumn = new ImportColumn();
        $crit = [$ImportColumn::$items_id => $importId];
        $columns = $ImportColumn->find($crit);

        // Get resources

        if ($display === self::DISPLAY_STATISTICS) {
            $Resource = new Resource();
            $resources = $Resource->find();
        } else {
            $resources = self::getResources($start, $limit);
        }

        $result = [
            'found_first_identifier' => 0,
            'found_second_identifier' => 0,
            'not_found' => 0,
            'total' => 0,
        ];
        $entries = [];

        $nbOfResources = (new DBUtils())->countElementsInTable(Resource::getTable());

        $temp = $this->readCSVLines($absoluteFilePath, 0, 1);
        $header = array_shift($temp);

        $firstLevelResourceColumns = [];
        $secondLevelResourceColumns = [];

        $columnTitles = [];

        foreach ($columns as $column) {
            $columnTitles[] = $column['name'];

            // Target : table Resource or ResourceImport
            // Name : name of the column in table
            $identifier = [
                'target' => null,
                'name' => null,
            ];

            switch ($column['resource_column']) {
                case 10:
                    $identifier['target'] = ResourceImport::class;
                    $identifier['name'] = $column['name'];
                    break;
                default:
                    $identifier['target'] = Resource::class;
                    $identifier['name'] = Resource::getColumnName(
                        $column['resource_column'],
                        ['date_declaration DESC'],
                    );
                    break;
            }

            foreach ($header as $key => $headerItem) {
                if ($headerItem == $column['name']) {
                    $identifier['columnKey'] = $key;
                }
            }

            switch ($column['is_identifier']) {
                case 1:
                    $firstLevelResourceColumns[] = $identifier;
                    break;
                case 2:
                    $secondLevelResourceColumns[] = $identifier;
                    break;
            }
        }

        // The line 0 is header
        $fileReadStart = 1;

        // Find resource in file
        $lines = $this->readCSVLines($absoluteFilePath, $fileReadStart);

        $ResourceImport = new ResourceImport();

        foreach ($resources as $resource) {
            $firstLevel = false;
            $secondLevel = false;

            // Values to display in differences tooltip
            $tooltipArray = [];

            $foundedLineIndex = null;

            foreach ($lines as $key => $line) {
                $foundedFirstLevel = true;

                // Find first level
                foreach ($firstLevelResourceColumns as $firstLevelResourceColumn) {
                    $lineValue = $line[$firstLevelResourceColumn['columnKey']];

                    switch ($firstLevelResourceColumn['target']) {
                        case ResourceImport::class:
                            $crit = [
                                'plugin_resources_resources_id' => $resource['id'],
                                'name' => $ResourceImport->getField('name'),
                            ];

                            if ($ResourceImport->getFromDBByCrit($crit)) {
                                if (is_string($lineValue)) {
                                    $foundedFirstLevel = strcasecmp(
                                        $lineValue,
                                        $ResourceImport->getField('value') == 0,
                                    );
                                } else {
                                    $foundedFirstLevel = ($lineValue == $firstLevelResourceColumn);
                                }
                            } else {
                                $foundedFirstLevel = false;
                            }
                            break;
                        case Resource::class:
                            $resourceValue = $resource[$firstLevelResourceColumn['name']];

                            if (is_string($lineValue)) {
                                $foundedFirstLevel = strcasecmp($lineValue, $resourceValue) == 0;
                            } else {
                                $foundedFirstLevel = ($lineValue == $firstLevelResourceColumn);
                            }
                            break;
                    }

                    if ($foundedFirstLevel == false) {
                        break;
                    }
                }

                if ($foundedFirstLevel == true) {
                    $foundedLineIndex = $key;
                    $tooltipArray = $line;
                    $firstLevel = true;
                    break;
                }
            }

            if (!$firstLevel && count($secondLevelResourceColumns) > 0) {
                foreach ($lines as $key => $line) {
                    $foundedSecondLevel = true;

                    // Find first level
                    foreach ($secondLevelResourceColumns as $secondLevelResourceColumn) {
                        $lineValue = $line[$secondLevelResourceColumn['columnKey']];

                        switch ($secondLevelResourceColumn['target']) {
                            case ResourceImport::class:
                                $crit = [
                                    'plugin_resources_resources_id' => $resource['id'],
                                    'name' => $ResourceImport->getField('name'),
                                ];

                                if ($ResourceImport->getFromDBByCrit($crit)) {
                                    if (is_string($lineValue)) {
                                        $foundedSecondLevel = strcasecmp(
                                            $lineValue,
                                            $ResourceImport->getField('value') == 0,
                                        );
                                    } else {
                                        $foundedSecondLevel = ($lineValue == $secondLevelResourceColumn);
                                    }
                                } else {
                                    $foundedSecondLevel = false;
                                }
                                break;
                            case Resource::class:
                                $resourceValue = $resource[$secondLevelResourceColumn['name']];

                                if (is_string($lineValue)) {
                                    $foundedSecondLevel = strcasecmp($lineValue, $resourceValue) == 0;
                                } else {
                                    $foundedSecondLevel = ($lineValue == $secondLevelResourceColumn);
                                }
                                break;
                        }

                        if ($foundedSecondLevel == false) {
                            break;
                        }
                    }

                    if ($foundedSecondLevel == true) {
                        $foundedLineIndex = $key;
                        $tooltipArray = $line;
                        $secondLevel = true;
                        break;
                    }
                }
            }

            // Speed up next search
            if (!is_null($foundedLineIndex)) {
                unset($lines[$foundedLineIndex]);
            }

            if ($display === self::DISPLAY_STATISTICS) {
                if ($firstLevel) {
                    $result['found_first_identifier']++;
                } elseif ($secondLevel) {
                    $result['found_second_identifier']++;
                } else {
                    $result['not_found']++;
                }
                $result['total']++;
                continue;
            }

            if (!$firstLevel && !$secondLevel) {
                $identification = __("Not in file", "resources");
            } else {
                $level = $firstLevel
                    ? __("first level", "resources")
                    : __("second level", "resources");
                $identification = sprintf(__("Find in file with %s identifier", "resources"), $level);
            }

            $tooltip = '';
            if ($firstLevel || $secondLevel) {
                // showToolTipWithArray() echoes directly, so capture it for the template.
                ob_start();
                self::showToolTipWithArray($columnTitles, $tooltipArray);
                $tooltip = (string) ob_get_clean();
            }

            $link = Toolbox::getItemTypeFormURL(Resource::class) . "?id=" . (int) $resource['id'];

            $entries[] = [
                'cells' => [
                    [
                        // Deleted resources are flagged with a red left border.
                        'style'   => $resource['is_deleted'] ? 'border-left:solid 5px red;' : '',
                        'content' => '<a href="' . htmlescape($link) . '">'
                            . htmlescape((string) $resource['id']) . '</a>',
                    ],
                    ['content' => htmlescape((string) $resource['name'])],
                    ['content' => htmlescape((string) $resource['firstname'])],
                    ['content' => htmlescape($identification)],
                    ['content' => $tooltip],
                ],
            ];
        }

        if ($display === self::DISPLAY_STATISTICS) {
            echo json_encode($result);
            return;
        }

        // Generate pager parameters
        $parameters = "type=" . $type;
        $parameters .= "&" . self::SELECTED_FILE_DROPDOWN_NAME;
        $parameters .= "=" . $params[self::SELECTED_FILE_DROPDOWN_NAME];

        ob_start();
        Html::printPager($start, $nbOfResources, self::getIndexUrl(), $parameters);
        $pager = (string) ob_get_clean();

        $header_columns = $this->getListHeaderColumns(['type' => $params['type']]);

        TemplateRenderer::getInstance()->display('@resources/import_verification_list.html.twig', [
            'form_action' => self::getIndexUrl() . "?" . $parameters,
            'pager'       => $pager,
            'header_columns' => $header_columns,
            'entries'     => $entries,
        ]);
    }

    /**
     * @param $start
     * @param $limit
     *
     * @return array
     * @throws \GlpitestSQLError
     */
    public function getResources($start, $limit)
    {
        global $DB;

        $iterator = $DB->request([
            'FROM'  => Resource::getTable(),
            'START' => (int) $start,
            'LIMIT' => (int) $limit,
        ]);

        $resources = [];
        foreach ($iterator as $data) {
            $resources[] = $data;
        }

        return $resources;
    }

    /**
     * @param      $titles
     * @param      $values
     * @param null $title
     */
    private function showToolTipWithArray($titles, $values, $title = null)
    {
        if (count($titles) != count($values)) {
            // The content is injected raw by showToolTip(), so escape it here.
            Html::showToolTip(
                htmlescape(__("Number of titles and values of tooltip doesn't match", "resources")),
            );
            return;
        }

        $rows = [];
        foreach ($titles as $index => $columnTitle) {
            $rows[] = [
                'title' => $columnTitle,
                'value' => $values[$index] ?? '',
            ];
        }

        Html::showToolTip(
            TemplateRenderer::getInstance()->render('@resources/import_tooltip_table.html.twig', [
                'title' => $title,
                'rows'  => $rows,
            ]),
        );
    }

    /**
     * @param $params
     */
    private function importFilePage($params)
    {
        $pluginResourcesImport = new Import();
        $imports = $pluginResourcesImport->find(['is_active' => 1]);

        // The head and the list echo directly: buffer them so the shell owns the markup.
        ob_start();
        $this->showHead(array_merge($params, ['imports' => $imports]));

        // Message when no import configured
        if (!empty($params[self::SELECTED_IMPORT_DROPDOWN_NAME])) {
            self::showImportList2($params);
        }

        TemplateRenderer::getInstance()->display('@resources/import_page.html.twig', [
            'show_loader' => false,
            'content'     => (string) ob_get_clean(),
        ]);
    }

    /**
     * @param $imports_id
     * @param $start
     * @param $limit
     *
     * @return array
     * @throws \GlpitestSQLError
     */
    public function getResourcesImports($imports_id, $start, $limit)
    {
        global $DB;
        $iterator = $DB->request([
            'FROM'  => $this->getTable(),
            'WHERE' => ['plugin_resources_imports_id' => (int) $imports_id],
            'START' => (int) $start,
            'LIMIT' => (int) $limit,
        ]);

        $resourcesImports = [];
        foreach ($iterator as $data) {
            $resourcesImports[] = $data;
        }
        return $resourcesImports;
    }

    private function showImportList2(array $params)
    {
        $start = $params['start'];
        $limit = $params['limit'];
        $type = intval($params['type']);

        $dbu = new DbUtils();
        $ResourcesDBTM = new Resource();
        $pluginResourcesImportDBTM = new Import();
        $ImportColumnDBTM = new ImportColumn();

        $pluginResourcesImportDBTM->getFromDBByCrit(['name' => $params[self::SELECTED_IMPORT_DROPDOWN_NAME]]);

        $columns = $ImportColumnDBTM->find(['plugin_resources_imports_id' => $pluginResourcesImportDBTM->getID()]);

        $numberOfFirstLevelIdentifiers = 0;
        $numberOfSecondLevelIdentifiers = 0;

        foreach ($columns as $column) {
            switch ($column['is_identifier']) {
                case 1:
                    $numberOfFirstLevelIdentifiers++;
                    break;
                case 2:
                    $numberOfSecondLevelIdentifiers++;
                    break;
            }
        }

        // Get all imports from the selected type of import
        if (isset($params['filter'])) {
            $importResources = $this->find(['plugin_resources_imports_id' => $pluginResourcesImportDBTM->getID()]);
            $importResourcesSave = $importResources;
            foreach ($importResources as $key => $importResource) {
                $resourceID = $this->resolveImportResourceId(
                    $importResource,
                    $columns,
                    $numberOfFirstLevelIdentifiers,
                    $numberOfSecondLevelIdentifiers,
                );

                $keep = false;
                switch ($params['filter']) {
                    case "deleted":
                        if ($ResourcesDBTM->getFromDB($resourceID)) {
                            if ($ResourcesDBTM->fields['is_deleted']) {
                                $keep = true;
                            }
                        }
                        break;
                    case "update":
                        if ($ResourcesDBTM->getFromDB($resourceID)) {
                            if (!$ResourcesDBTM->fields['is_deleted']) {
                                $keep = true;
                            }
                        }
                        break;

                    case "new":
                        if (!$ResourcesDBTM->getFromDB($resourceID)) {
                            $keep = true;
                        }
                        break;
                }

                if ($keep === false) {
                    unset($importResourcesSave[$key]);
                }
            }

            $nbImports = count($importResourcesSave);
            $importResources = [];
            $i = 0;
            foreach ($importResourcesSave as $importResource) {
                if ($i < $start) {
                    $i++;
                    continue;
                }
                if ($i >= $start + $limit) {
                    break;
                }
                $importResources[] = $importResource;
                $i++;
            }
        } else {
            $importResources = $this->getResourcesImports($pluginResourcesImportDBTM->getID(), $start, $limit);
            $critNbImports = ['plugin_resources_imports_id' => $pluginResourcesImportDBTM->getID()];
            $nbImports = $dbu->countElementsInTable(ImportResource::getTable(), $critNbImports);
        }


        if (!is_array($importResources) || !count($importResources)) {
            ob_start();
            self::showErrorHeader(__('No Imports', 'resources'));
            $error_header = (string) ob_get_clean();

            TemplateRenderer::getInstance()->display('@resources/import_list.html.twig', [
                'entries'      => [],
                'error_header' => $error_header,
            ]);
            return;
        }

        // Generate pager parameters
        $parameters = "type=" . $params['type'];
        $parameters .= "&" . self::SELECTED_IMPORT_DROPDOWN_NAME;
        $parameters .= "=" . $params[self::SELECTED_IMPORT_DROPDOWN_NAME];
        $parameters2 = "";
        if (isset($params['filter'])) {
            $parameters2 = "&" . "filter=" . $params['filter'];
        }

        $baseUrl = self::getResourceImportFormUrl() . "?" . $parameters;
        $legends = [
            ['color' => 'red', 'url' => $baseUrl . '&filter=deleted', 'label' => __("Deleted resource", 'resources')],
            ['color' => 'orange', 'url' => $baseUrl . '&filter=update', 'label' => __("Updated resource", 'resources')],
            ['color' => 'green', 'url' => $baseUrl . '&filter=new', 'label' => __("New resource", 'resources')],
        ];

        ob_start();
        Html::printPager(
            $params['start'],
            $nbImports,
            self::getResourceImportFormUrl(),
            $parameters . $parameters2,
        );
        $pager = (string) ob_get_clean();

        ob_start();
        self::showImportListButtons();
        $buttons = (string) ob_get_clean();

        $header_columns = $this->getListHeaderColumns([
            'type'   => $params['type'],
            'import' => $pluginResourcesImportDBTM->fields,
        ]);

        $hidden_inputs = [];
        $entries = [];
        foreach ($importResources as $importResource) {
            $resourceID = $this->resolveImportResourceId(
                $importResource,
                $columns,
                $numberOfFirstLevelIdentifiers,
                $numberOfSecondLevelIdentifiers,
            );

            if (!$ResourcesDBTM->getFromDB($resourceID)) {
                $borderColor = 'green';
            } elseif ($ResourcesDBTM->fields['is_deleted']) {
                $borderColor = 'red';
            } else {
                $borderColor = 'orange';
            }

            $hidden_inputs[] = [
                'name'  => "resource[" . $importResource['id'] . "]",
                'value' => $resourceID,
            ];

            // showOne() echoes the row cells, so capture them for the template.
            ob_start();
            $this->showOne($importResource['id'], $params['type'], $resourceID, $borderColor);
            $entries[] = ['cells' => (string) ob_get_clean()];
        }

        TemplateRenderer::getInstance()->display('@resources/import_list.html.twig', [
            'form_action'   => $baseUrl . $parameters2,
            'pager'         => $pager,
            'legends'       => $legends,
            'buttons'       => $buttons,
            'header_columns' => $header_columns,
            'hidden_inputs' => $hidden_inputs,
            'entries'       => $entries,
        ]);
    }

    /**
     * Resolve the GLPI resource an import line points at, trying the first level
     * identifiers then the second level ones.
     *
     * @param array $importResource the import line
     * @param array $columns        import columns, keyed by id
     * @param int   $nbFirst        expected number of first level identifiers
     * @param int   $nbSecond       expected number of second level identifiers
     *
     * @return int|null the resource id, or null when no match was found
     */
    private function resolveImportResourceId(array $importResource, array $columns, int $nbFirst, int $nbSecond)
    {
        $ImportResourceDataDBTM = new ImportResourceData();

        $firstLevelIdentifiers = [];
        $secondLevelIdentifiers = [];

        $datas = $ImportResourceDataDBTM->find(
            ["plugin_resources_importresources_id" => $importResource['id']],
        );

        foreach ($datas as $data) {
            // Speed up loop
            if (count($firstLevelIdentifiers) == $nbFirst && count($secondLevelIdentifiers) == $nbSecond) {
                break;
            }

            $column = $columns[$data['plugin_resources_importcolumns_id']] ?? null;
            if ($column === null) {
                continue;
            }

            $level = (int) $column['is_identifier'];
            if ($level !== 1 && $level !== 2) {
                continue;
            }

            $element = [
                'name' => $data['name'],
                'value' => $data['value'],
                'type' => $data['plugin_resources_importcolumns_id'],
                'resource_column' => $column['resource_column'],
            ];
            if (is_string($element['value']) && empty($element['value'])) {
                $element['value'] = null;
            }

            if ($level === 1) {
                $firstLevelIdentifiers[] = $element;
            } else {
                $secondLevelIdentifiers[] = $element;
            }
        }

        $resourceID = null;
        if (count($firstLevelIdentifiers) > 0) {
            $resourceID = $this->findResource($firstLevelIdentifiers);
        }
        if (!$resourceID && count($secondLevelIdentifiers) > 0) {
            $resourceID = $this->findResource($secondLevelIdentifiers);
        }

        return $resourceID;
    }

    ////// CRON FUNCTIONS ///////
    //Cron action

    private function getImportResources($importID, $importId, $order, $limit = null)
    {
        global $DB;

        $criteria = [
            'FROM'  => self::getTable(),
            'WHERE' => [
                'plugin_resources_imports_id' => (int) $importID,
                'id' => [($order == self::BEFORE) ? '<' : '>', (int) $importId],
            ],
        ];

        if (!is_null($limit)) {
            $criteria['LIMIT'] = (int) $limit;
        }

        $imports = [];
        foreach ($DB->request($criteria) as $data) {
            $imports[] = $data;
        }

        return $imports;
    }

    private function showImportListButtons()
    {
        TemplateRenderer::getInstance()->display('@resources/import_list_buttons.html.twig');
    }

    public function setFileVerify($params)
    {
        // Reduce the client-supplied name to its basename so it cannot escape the
        // import directories (path traversal → arbitrary rename/move).
        $params['filename'] = basename((string) ($params['filename'] ?? ''));

        Document::renameForce(
            GLPI_PLUGIN_DOC_DIR . "/resources/import/verify/" . $params['filename'],
            GLPI_PLUGIN_DOC_DIR . "/resources/import/" . $params['filename'],
        );

        $importSuccess = false;

        $path = GLPI_PLUGIN_DOC_DIR . "/resources/import/";
        $file = $params['filename'];
        $filePath = $path . $file;


        if (file_exists($filePath)) {
            // Initialize existingImports Array
            // Used to prevent multiple get imports from database
            // Speed up execution time
            $this->purgeDatabase();
            $this->resetExistingImportsArray();
            $this->initExistingImportsArray();

            $temp = $this->readCSVLines($filePath, 0, 1);
            $header = array_shift($temp);

            $importID = $this->checkHeader($header);

            if ($importID) {
                $lines = $this->readCSVLines($filePath, 1, INF);

                foreach ($lines as $line) {
                    $datas = $this->parseFileLine($header, $line, $importID);

                    $this->manageImport($datas, $importID);
                }
                $importSuccess = true;
            }
        }
        if ($importSuccess) {
            // Move file to done folder
            $output = $path . 'done/' . $file;
            rename(str_replace('\\', '/', $filePath), str_replace('\\', '/', $output));
            Session::addMessageAfterRedirect(__('The file has been pre-import', 'resources'));
        } else {
            // Move file to fail folder
            $output = $path . 'fail/' . $file;
            rename(str_replace('\\', '/', $filePath), str_replace('\\', '/', $output));
            Session::addMessageAfterRedirect(__('The file does not match any template', 'resources'));
        }
    }


    public static function deleteFile($filename)
    {
        // Reduce the client-supplied name to its basename so it cannot escape the
        // verification directory (path traversal → arbitrary file delete).
        $filename = basename((string) $filename);
        $filepath = GLPI_PLUGIN_DOC_DIR . "/resources/import/verify/" . $filename;
        unlink($filepath);
    }

    public static function install(Migration $migration)
    {
        global $DB;

        $default_charset   = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();
        $table  = self::getTable();

        if (!$DB->tableExists($table)) {
            $query = "CREATE TABLE `$table` (
                        `id`           int {$default_key_sign} NOT NULL auto_increment,
                        `date_creation`               timestamp    NULL     DEFAULT NULL,
                        `plugin_resources_imports_id` int {$default_key_sign} NOT NULL DEFAULT '0',
                        PRIMARY KEY (`id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
