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

// The pdf plugin is optional and absent from the test environment. ResourcePDF extends one of
// its classes and the PDF renderers type their first argument with the other: minimal doubles
// stand in for both, declared only when the plugin itself is not there.

namespace {
    if (!class_exists('PluginPdfSimplePDF')) {
        class PluginPdfSimplePDF
        {
            public function setColumnsSize(...$args) {}

            public function displayTitle(...$args) {}

            public function displayLine(...$args) {}

            public function displayText($name, $content = '', $minline = 3, $maxline = 100) {}

            public function displaySpace($nb = 1) {}
        }
    }
    if (!class_exists('PluginPdfCommon')) {
        abstract class PluginPdfCommon extends CommonGLPI
        {
            protected $obj = null;
            protected $pdf = null;
        }
    }
}

namespace GlpiPlugin\Resources\Tests\Integration {

    use Computer;
    use Glpi\Exception\Http\AccessDeniedHttpException;
    use Glpi\Exception\Http\BadRequestHttpException;
    use Glpi\RichText\RichText;
    use Glpi\Tests\DbTestCase;
    use GlpiPlugin\Resources\Checklist;
    use GlpiPlugin\Resources\Client;
    use GlpiPlugin\Resources\Employee;
    use GlpiPlugin\Resources\Employer;
    use GlpiPlugin\Resources\Habilitation;
    use GlpiPlugin\Resources\Profile;
    use GlpiPlugin\Resources\ReportExport;
    use GlpiPlugin\Resources\Resource;
    use GlpiPlugin\Resources\Resource_Item;
    use GlpiPlugin\Resources\ResourceHabilitation;
    use GlpiPlugin\Resources\ResourcePDF;
    use GlpiPlugin\Resources\Task;
    use GlpiPlugin\Resources\TaskPlanning;
    use GlpiPlugin\Resources\User as ResourceUser;
    use Search;
    use Session;
    use User;

    /**
     * What the plugin displays: tabs, planning, AJAX dropdowns, report cells and PDF exports only
     * show what the session may read, and stored values are escaped wherever they reach markup.
     */
    class DisplayTest extends DbTestCase
    {
        public function setUp(): void
        {
            parent::setUp();

            $this->login();
            $this->setEntity('_test_root_entity', true);
            // The plugin rights are only granted to the profile active when the plugin was
            // installed: give them to the session of the test.
            Profile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);
            Profile::initProfile();
            // Writing the profile rights marks them updated: the first Session::checkRight() of
            // a script would reload the profile, and lose the rights a test removes from the
            // session. Reload it now, once.
            Session::checkValidSessionId();
        }

        public function tearDown(): void
        {
            $_POST = [];
            parent::tearDown();
        }

        /**
         * @param array<string, int> $rights
         */
        private function setSessionRights(array $rights): void
        {
            foreach ($rights as $name => $value) {
                $_SESSION['glpiactiveprofile'][$name] = $value;
            }
        }

        private function getEntity(string $name): int
        {
            return getItemByTypeName('Entity', $name, true);
        }

        private function createResource(string $name, int $entities_id, array $fields = []): Resource
        {
            return $this->createItem(Resource::class, $fields + [
                'name'               => $name,
                'firstname'          => 'Display',
                'entities_id'        => $entities_id,
                'users_id_recipient' => Session::getLoginUserID(),
                'withtemplate'       => 0,
            ], ['withtemplate']);
        }

        private function createChecklist(Resource $resource, string $name): int
        {
            return $this->createItem(Checklist::class, [
                'name'                              => $name,
                'entities_id'                       => $resource->fields['entities_id'],
                'plugin_resources_resources_id'     => $resource->getID(),
                'plugin_resources_contracttypes_id' => 0,
                'checklist_type'                    => Checklist::RESOURCES_CHECKLIST_IN,
                'is_checked'                        => 0,
            ])->getID();
        }

        /**
         * Run a renderer and return what it printed along with what it returned.
         *
         * @return array{0: mixed, 1: string}
         */
        private function capture(callable $call): array
        {
            ob_start();
            try {
                $result = $call();
            } finally {
                $output = (string) ob_get_clean();
            }

            return [$result, $output];
        }

        /**
         * Run an AJAX script of the plugin with the given POST parameters.
         */
        private function runAjax(string $script, array $post): string
        {
            $_POST = $post;
            ob_start();
            try {
                include dirname(__DIR__, 2) . '/ajax/' . $script;
            } finally {
                $output = (string) ob_get_clean();
                $_POST = [];
            }

            return $output;
        }

        public function testChecklistTabIsOnlyOfferedOnResources(): void
        {
            $computer = $this->createItem(Computer::class, [
                'name'        => 'Display tab computer',
                'entities_id' => $this->getTestRootEntity(true),
            ]);

            $this->assertSame('', (new Checklist())->getTabNameForItem($computer));

            [$result, $output] = $this->capture(fn() => Checklist::displayTabContentForItem($computer));
            $this->assertFalse($result);
            $this->assertSame('', $output);
        }

        public function testChecklistListingRequiresReadOnTheResource(): void
        {
            $visible = $this->createResource('Display visible', $this->getEntity('_test_child_1'));
            $hidden  = $this->createResource('Display hidden', $this->getEntity('_test_child_2'));
            $this->createChecklist($visible, 'Visible checklist row');
            $this->createChecklist($hidden, 'Hidden checklist row');

            $this->setEntity('_test_child_1', false);

            // The checklist right alone does not open the resource of another entity
            [$result, $output] = $this->capture(fn() => Checklist::displayTabContentForItem($hidden));
            $this->assertFalse($result);
            $this->assertStringNotContainsString('Hidden checklist row', $output);

            [$result, $output] = $this->capture(
                fn() => Checklist::showFromResources($hidden->getID(), Checklist::RESOURCES_CHECKLIST_IN),
            );
            $this->assertFalse($result);
            $this->assertStringNotContainsString('Hidden checklist row', $output);

            [$result, $output] = $this->capture(fn() => Checklist::displayTabContentForItem($visible));
            $this->assertTrue($result);
            $this->assertStringContainsString('Visible checklist row', $output);
        }

        /**
         * Task planned on the session user, inserted as is so that its entity, its deleted flag and
         * the resource it belongs to can be set independently.
         */
        private function createPlannedTask(int $resources_id, int $entities_id, string $name, array $fields = []): int
        {
            global $DB;

            $DB->insert('glpi_plugin_resources_tasks', $fields + [
                'name'                          => $name,
                'entities_id'                   => $entities_id,
                'plugin_resources_resources_id' => $resources_id,
                'users_id'                      => Session::getLoginUserID(),
                'comment'                       => 'Comment of ' . $name,
                'is_finished'                   => 0,
                'is_deleted'                    => 0,
            ]);
            $tasks_id = (int) $DB->insertId();
            $DB->insert('glpi_plugin_resources_taskplannings', [
                'plugin_resources_tasks_id' => $tasks_id,
                'begin'                     => '2031-03-10 10:00:00',
                'end'                       => '2031-03-10 11:00:00',
            ]);

            return $tasks_id;
        }

        /**
         * @return array<int, array<string, mixed>> planning events, by task id
         */
        private function populatePlanning(): array
        {
            $events = TaskPlanning::populatePlanning([
                'who'              => Session::getLoginUserID(),
                'whogroup'         => 0,
                'begin'            => '2031-03-01 00:00:00',
                'end'              => '2031-04-01 00:00:00',
                'color'            => '#ffffff',
                'event_type_color' => '#000000',
            ]);

            return array_column($events, null, 'id');
        }

        public function testPlanningOnlyFeedsTasksTheSessionMayRead(): void
        {
            global $DB;

            $child_1 = $this->getEntity('_test_child_1');
            $child_2 = $this->getEntity('_test_child_2');

            $resource       = $this->createResource('Display planning', $child_1);
            $other_resource = $this->createResource('Display planning other', $child_2);
            $template       = $this->createResource('Display planning template', $child_1);
            $deleted        = $this->createResource('Display planning deleted', $child_1);
            $DB->update('glpi_plugin_resources_resources', ['is_template' => 1], ['id' => $template->getID()]);
            $DB->update('glpi_plugin_resources_resources', ['is_deleted' => 1], ['id' => $deleted->getID()]);

            $readable         = $this->createPlannedTask($resource->getID(), $child_1, 'Readable task');
            $other_entity     = $this->createPlannedTask($other_resource->getID(), $child_2, 'Other entity task');
            $other_resource_t = $this->createPlannedTask($other_resource->getID(), $child_1, 'Task of a hidden resource');
            $deleted_task     = $this->createPlannedTask($resource->getID(), $child_1, 'Deleted task', ['is_deleted' => 1]);
            $template_task    = $this->createPlannedTask($template->getID(), $child_1, 'Template task');
            $deleted_res_task = $this->createPlannedTask($deleted->getID(), $child_1, 'Task of a deleted resource');

            $this->setEntity('_test_child_1', false);

            $events = $this->populatePlanning();
            $this->assertArrayHasKey($readable, $events);
            foreach ([$other_entity, $other_resource_t, $deleted_task, $template_task, $deleted_res_task] as $tasks_id) {
                $this->assertArrayNotHasKey($tasks_id, $events);
            }

            // The rendering of an event re-checks the task itself
            $this->assertSame('', TaskPlanning::displayPlanningItem(['id' => $other_entity] + $events[$readable], 0, 'in'));
            $this->assertNotSame('', TaskPlanning::displayPlanningItem($events[$readable], 0, 'in'));

            // Without the task right, nothing at all
            $this->setSessionRights(['plugin_resources_task' => 0]);
            $this->assertSame([], $this->populatePlanning());
            $this->assertSame('', TaskPlanning::displayPlanningItem($events[$readable], 0, 'in'));
        }

        public function testPlanningItemEscapesStoredLabels(): void
        {
            $resource = $this->createResource('<b>Display resource</b>', $this->getTestRootEntity(true));
            $tasks_id = $this->createPlannedTask($resource->getID(), $this->getTestRootEntity(true), '<i>Display task</i>');

            $events = $this->populatePlanning();
            $this->assertArrayHasKey($tasks_id, $events);

            $html = TaskPlanning::displayPlanningItem($events[$tasks_id], 0, 'in');
            $this->assertStringContainsString('&lt;i&gt;Display task&lt;/i&gt;', $html);
            $this->assertStringContainsString('&lt;b&gt;Display resource&lt;/b&gt;', $html);
            $this->assertStringNotContainsString('<i>Display task</i>', $html);
            $this->assertStringNotContainsString('<b>Display resource</b>', $html);
        }

        public function testLinkItemsRequiresTheChecklistRight(): void
        {
            $this->setSessionRights(['plugin_resources_checklist' => 0]);

            $this->expectException(AccessDeniedHttpException::class);
            $this->runAjax('linkItems.php', ['type' => Computer::class, 'current_type' => Computer::class, 'values' => 0]);
        }

        public function testLinkItemsRefusesATypeTheSessionCannotView(): void
        {
            $this->setSessionRights(['computer' => 0]);

            $this->expectException(BadRequestHttpException::class);
            $this->runAjax('linkItems.php', ['type' => Computer::class, 'current_type' => Computer::class, 'values' => 0]);
        }

        public function testLinkItemsRefusesATypeThePluginDoesNotExpose(): void
        {
            $this->expectException(BadRequestHttpException::class);
            $this->runAjax('linkItems.php', ['type' => Resource::class, 'current_type' => Resource::class, 'values' => 0]);
        }

        public function testLinkItemsOnlyResolvesAReadableCurrentValue(): void
        {
            $visible = $this->createItem(Computer::class, [
                'name'        => 'Display linkitems visible',
                'entities_id' => $this->getEntity('_test_child_1'),
            ]);
            $hidden = $this->createItem(Computer::class, [
                'name'        => 'Display linkitems hidden',
                'entities_id' => $this->getEntity('_test_child_2'),
            ]);

            $this->setEntity('_test_child_1', false);

            $output = $this->runAjax('linkItems.php', [
                'type'         => Computer::class,
                'current_type' => Computer::class,
                'values'       => $hidden->getID(),
            ]);
            $this->assertStringContainsString('name="items"', $output);
            $this->assertStringNotContainsString('Display linkitems hidden', $output);

            $output = $this->runAjax('linkItems.php', [
                'type'         => Computer::class,
                'current_type' => Computer::class,
                'values'       => $visible->getID(),
            ]);
            $this->assertStringContainsString('Display linkitems visible', $output);
        }

        public function testResourceDropdownAnswersInertJsonWithResourcesOnly(): void
        {
            $this->createResource('<b>Display dropdown</b>', $this->getTestRootEntity(true));

            $output = $this->runAjax('dropdownResources.php', [
                'searchText'       => 'Display dropdown',
                'addUnlinkedUsers' => 1,
            ]);

            // Markup characters are hex-escaped in the body itself
            $this->assertStringNotContainsString('<b>', $output);
            $this->assertStringContainsString(trim(json_encode('<b>', JSON_HEX_TAG), '"'), $output);

            // ... and decoded back by a JSON parser, as select2 does
            $data = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            $texts = array_column($data['results'], 'text');
            $this->assertNotEmpty(array_filter($texts, static fn($text) => str_contains($text, '<b>Display dropdown</b>')));

            // The former unlinked users branch is gone: no user entry whatever is posted
            foreach ($data['results'] as $row) {
                $this->assertStringStartsNotWith('users-', (string) $row['id']);
            }
            $this->assertSame(count($data['results']), $data['count']);
        }

        public function testReportCellsAreEscapedUnlessDeclaredHtml(): void
        {
            $num = 1;
            $cell = ReportExport::showItem(Search::HTML_OUTPUT, '<b>Rank</b> & co', $num, 1);
            $this->assertStringContainsString('&lt;b&gt;Rank&lt;/b&gt; &amp; co', $cell);
            $this->assertStringNotContainsString('<b>', $cell);

            $cell = ReportExport::showItem(Search::HTML_OUTPUT, '<a href="/x">Link</a>', $num, 1, '', true);
            $this->assertStringContainsString('<a href="/x">Link</a>', $cell);

            $cell = ReportExport::showItem(Search::HTML_OUTPUT, null, $num, 1);
            $this->assertStringContainsString('<td', $cell);
        }

        public function testReportSortLinkOnlyCarriesTheReportCriteria(): void
        {
            $_REQUEST['injected"><b>'] = 'x';

            $link = ReportExport::getSortLink(
                '/report.php',
                [
                    'find'    => true,
                    'a"><x'   => 'v',
                    'date'    => '2031-01-01',
                    'ids'     => [1, 2],
                    'sort'    => 'forged',
                    'order'   => 'forged',
                    'ignored' => null,
                ],
                'profession',
                'DESC',
            );
            unset($_REQUEST['injected"><b>']);

            $this->assertSame(
                '/report.php?find=1&a%22%3E%3Cx=v&date=2031-01-01&ids%5B0%5D=1&ids%5B1%5D=2&sort=profession&order=DESC',
                $link,
            );

            // An unknown direction falls back to ascending
            $this->assertStringEndsWith('&order=ASC', ReportExport::getSortLink('/r.php', [], 'name', 'forged'));
        }

        /**
         * PDF renderer recording what it is given.
         */
        private function getPdfRecorder(): \PluginPdfSimplePDF
        {
            return new class extends \PluginPdfSimplePDF {
                /** @var array<int, string> */
                public array $printed = [];

                public function __construct() {}

                public function setColumnsSize(...$args) {}

                public function displayTitle(...$args)
                {
                    array_push($this->printed, ...array_map('strval', $args));
                }

                public function displayLine(...$args)
                {
                    array_push($this->printed, ...array_map('strval', $args));
                }

                // What the pdf plugin (4.x) renders: the content is sanitized as rich text,
                // then its entities are decoded. Recording the arguments as they are would
                // hide that an escaped value comes out as markup.
                public function displayText($name, $content = '', $minline = 3, $maxline = 100)
                {
                    $this->printed[] = html_entity_decode(
                        RichText::getEnhancedHtml($name . ' ' . $content, ['text_maxsize' => 0]),
                        ENT_QUOTES,
                        'UTF-8',
                    );
                }

                public function displaySpace($nb = 1) {}
            };
        }

        public function testPdfMainEscapesTheResourceFields(): void
        {
            $resource = $this->createResource('<b>Pdf name</b>', $this->getTestRootEntity(true), [
                'firstname' => '<i>Pdf firstname</i>',
                'comment'   => "<img src=\"/front/document.send.php?docid=1\">Pdf comment\n<u>second line</u>",
            ]);

            $pdf = $this->getPdfRecorder();
            ResourcePDF::pdfMain($pdf, $resource);
            $printed = implode("\n", $pdf->printed);

            $this->assertStringContainsString('&lt;b&gt;Pdf name&lt;/b&gt;', $printed);
            $this->assertStringContainsString('&lt;i&gt;Pdf firstname&lt;/i&gt;', $printed);
            $this->assertStringContainsString(
                '&lt;img src=&quot;/front/document.send.php?docid=1&quot;&gt;Pdf comment<br />',
                $printed,
            );
            $this->assertStringContainsString('&lt;u&gt;second line&lt;/u&gt;', $printed);
            $this->assertStringNotContainsString('<b>Pdf name', $printed);
            $this->assertStringNotContainsString('<img', $printed);
            $this->assertStringNotContainsString('<u>', $printed);
            // The static markup of the labels is kept
            $this->assertStringContainsString('<b><i>', $printed);
        }

        public function testReportSpreadsheetCellsAreWrittenAsText(): void
        {
            $type = Search::CSV_OUTPUT;
            [, $csv] = $this->capture(static function () use ($type): void {
                $num = 0;
                ReportExport::showHeader($type, 2, 3);
                ReportExport::showHeaderItem($type, 'Plain', $num);
                ReportExport::showHeaderItem($type, 'Link', $num);
                ReportExport::showHeaderItem($type, 'Groups', $num);
                ReportExport::showNewLine($type);
                $num = 1;
                ReportExport::showItem($type, "R&D <x> l'équipe", $num, 1);
                ReportExport::showItem($type, '<a href="/front/x.php?id=1&amp;a=2">R&amp;D link</a>', $num, 1, '', true);
                ReportExport::showItem($type, 'First<br>Second', $num, 1, '', true);
                ReportExport::showEndLine($type);
                ReportExport::showFooter($type, 'Report');
            });

            $lines = array_values(array_filter(
                preg_split('/\r\n/', preg_replace('/^\xEF\xBB\xBF/', '', $csv)),
                static fn(string $line): bool => $line !== '',
            ));
            $delimiter = $_SESSION['glpicsv_delimiter'];
            $this->assertSame(
                implode($delimiter, ['"Plain"', '"Link"', '"Groups"']),
                $lines[0],
            );
            // The escaped value gets its characters back, the trusted markup its text only
            $this->assertStringStartsWith(
                implode($delimiter, ['"R&D <x> l\'équipe"', '"R&D link"', '"First']),
                $lines[1],
            );
            $this->assertStringNotContainsString('&amp;', $csv);
            $this->assertStringNotContainsString('&lt;', $csv);
            $this->assertStringNotContainsString('<a ', $csv);
            $this->assertStringNotContainsString('<br', $csv);
        }

        public function testChecklistPdfRequiresTheChecklistRightAndEscapesRows(): void
        {
            $resource = $this->createResource('Display pdf checklist', $this->getTestRootEntity(true));
            $this->createChecklist($resource, '<u>Pdf row</u>');

            $pdf = $this->getPdfRecorder();
            Checklist::pdfForResource($pdf, $resource, Checklist::RESOURCES_CHECKLIST_IN);
            $printed = implode("\n", $pdf->printed);
            $this->assertStringContainsString('&lt;u&gt;Pdf row&lt;/u&gt;', $printed);
            $this->assertStringNotContainsString('<u>', $printed);

            $this->setSessionRights(['plugin_resources_checklist' => 0]);
            $pdf = $this->getPdfRecorder();
            $this->assertFalse(Checklist::pdfForResource($pdf, $resource, Checklist::RESOURCES_CHECKLIST_IN));
            $this->assertSame([], $pdf->printed);
        }

        public function testUserPdfRequiresTheUserRightAndEscapesTheAccount(): void
        {
            global $DB;

            $resource = $this->createResource('Display pdf user', $this->getTestRootEntity(true));
            $users_id = getItemByTypeName(User::class, 'tech', true);
            $DB->update('glpi_users', ['phone' => '<b>0102</b>'], ['id' => $users_id]);
            $DB->insert('glpi_plugin_resources_resources_items', [
                'plugin_resources_resources_id' => $resource->getID(),
                'itemtype'                      => User::class,
                'items_id'                      => $users_id,
            ]);

            $pdf = $this->getPdfRecorder();
            ResourceUser::pdfForResource($pdf, $resource);
            $printed = implode("\n", $pdf->printed);
            $this->assertStringContainsString('&lt;b&gt;0102&lt;/b&gt;', $printed);
            $this->assertStringNotContainsString('<b>0102', $printed);

            // Neither the PDF nor the tab without the right to view users
            $this->setSessionRights(['user' => 0]);
            $pdf = $this->getPdfRecorder();
            $this->assertFalse(ResourceUser::pdfForResource($pdf, $resource));
            $this->assertSame([], $pdf->printed);
            $this->assertSame('', (new ResourceUser())->getTabNameForItem($resource));
        }

        public function testUserTabOnlyShowsAReadableAccount(): void
        {
            global $DB;

            $child_1  = $this->getEntity('_test_child_1');
            $resource = $this->createResource('Display user tab', $child_1);
            $link     = static function (int $users_id) use ($DB, $resource): void {
                $DB->insert('glpi_plugin_resources_resources_items', [
                    'plugin_resources_resources_id' => $resource->getID(),
                    'itemtype'                      => User::class,
                    'items_id'                      => $users_id,
                ]);
            };
            $own = $this->createItem(User::class, [
                'name'          => 'display-user-tab-own',
                'phone'         => '0122222222',
                '_profiles_id'  => getItemByTypeName('Profile', 'Self-Service', true),
                '_entities_id'  => $child_1,
                '_is_recursive' => 0,
            ])->getID();

            // The last account linked cannot be read: nothing is shown. From the command line
            // Session::canViewAllEntities() is always true, which makes every existing user
            // readable whatever its entity: an account that does not exist stands for it.
            $link($own + 1000000);
            $this->setEntity('_test_child_1', false);
            [$result, $output] = $this->capture(fn() => ResourceUser::displayTabContentForItem($resource));
            $this->assertTrue($result);
            $this->assertStringNotContainsString('name="phone"', $output);

            // A readable account linked after it is shown
            $link($own);
            [, $output] = $this->capture(fn() => ResourceUser::displayTabContentForItem($resource));
            $this->assertStringContainsString('0122222222', $output);

            // Not without the right to view users, nor on another itemtype
            $this->setSessionRights(['user' => 0]);
            [$result, $output] = $this->capture(fn() => ResourceUser::displayTabContentForItem($resource));
            $this->assertFalse($result);
            $this->assertSame('', $output);
            $computer = getItemByTypeName(Computer::class, '_test_pc01');
            $this->assertFalse(ResourceUser::displayTabContentForItem($computer));
        }

        public function testEmployeePdfRequiresTheEmployeeRightAndEscapesNames(): void
        {
            global $DB;

            $resource = $this->createResource('Display pdf employee', $this->getTestRootEntity(true));
            $DB->insert(Employer::getTable(), [
                'name'         => '<b>Pdf employer</b>',
                'completename' => '<b>Pdf employer</b>',
                'entities_id'  => $this->getTestRootEntity(true),
                'is_recursive' => 1,
            ]);
            $employers_id = (int) $DB->insertId();
            $DB->insert(Client::getTable(), [
                'name'         => '<i>Pdf client</i>',
                'entities_id'  => $this->getTestRootEntity(true),
                'is_recursive' => 1,
            ]);
            $clients_id = (int) $DB->insertId();
            $DB->insert(Employee::getTable(), [
                'plugin_resources_resources_id' => $resource->getID(),
                'plugin_resources_employers_id' => $employers_id,
                'plugin_resources_clients_id'   => $clients_id,
            ]);

            $pdf = $this->getPdfRecorder();
            Employee::pdfForResource($pdf, $resource);
            $printed = implode("\n", $pdf->printed);
            $this->assertStringContainsString('&lt;b&gt;Pdf employer&lt;/b&gt;', $printed);
            $this->assertStringContainsString('&lt;i&gt;Pdf client&lt;/i&gt;', $printed);
            $this->assertStringNotContainsString('<b>Pdf', $printed);
            $this->assertStringNotContainsString('<i>Pdf', $printed);
            // The static markup of the titles is kept
            $this->assertStringContainsString('<b><i>', $printed);

            // The on-screen tab requires the employee right, so does the PDF
            $this->setSessionRights(['plugin_resources_employee' => 0]);
            $pdf = $this->getPdfRecorder();
            $this->assertFalse(Employee::pdfForResource($pdf, $resource));
            $this->assertSame([], $pdf->printed);
        }

        public function testLinkedItemsPdfEscapesTheirValues(): void
        {
            global $DB;

            $root     = $this->getTestRootEntity(true);
            $resource = $this->createResource('<b>Display pdf linked</b>', $root);
            $computer = $this->createItem(Computer::class, [
                'name'        => '<u>Pdf computer</u>',
                'serial'      => '<i>Pdf serial</i>',
                'entities_id' => $root,
            ]);
            $DB->insert('glpi_plugin_resources_resources_items', [
                'plugin_resources_resources_id' => $resource->getID(),
                'itemtype'                      => Computer::class,
                'items_id'                      => $computer->getID(),
            ]);

            // From the resource: the linked computer
            $pdf = $this->getPdfRecorder();
            Resource_Item::pdfForResource($pdf, $resource);
            $printed = implode("\n", $pdf->printed);
            $this->assertStringContainsString('&lt;u&gt;Pdf computer&lt;/u&gt;', $printed);
            $this->assertStringContainsString('&lt;i&gt;Pdf serial&lt;/i&gt;', $printed);
            $this->assertStringNotContainsString('<u>', $printed);
            $this->assertStringNotContainsString('<i>Pdf', $printed);

            // From the computer: the resource linked
            $pdf = $this->getPdfRecorder();
            Resource_Item::PdfFromItems($pdf, $computer);
            $printed = implode("\n", $pdf->printed);
            $this->assertStringContainsString('&lt;b&gt;Display pdf linked&lt;/b&gt;', $printed);
            $this->assertStringNotContainsString('<b>Display', $printed);

            // ... only for a profile allowed to read resources
            $this->setSessionRights(['plugin_resources' => 0]);
            $pdf = $this->getPdfRecorder();
            Resource_Item::PdfFromItems($pdf, $computer);
            $this->assertSame([], $pdf->printed);
        }

        public function testTaskAndHabilitationPdfEscapeLabelsAndValues(): void
        {
            global $DB;

            $root     = $this->getTestRootEntity(true);
            $resource = $this->createResource('Display pdf task', $root);
            $this->assertGreaterThan(0, (new Task())->add([
                'name'                          => '<s>Pdf task</s>',
                'plugin_resources_resources_id' => $resource->getID(),
            ]));
            $DB->insert(Habilitation::getTable(), [
                'name'         => '<em>Pdf habilitation</em>',
                'completename' => '<em>Pdf habilitation</em>',
                'entities_id'  => $root,
                'is_recursive' => 1,
            ]);
            $DB->insert(ResourceHabilitation::getTable(), [
                'plugin_resources_resources_id'     => $resource->getID(),
                'plugin_resources_habilitations_id' => (int) $DB->insertId(),
            ]);

            $pdf = $this->getPdfRecorder();
            Task::pdfForResource($pdf, $resource);
            $printed = implode("\n", $pdf->printed);
            $this->assertStringContainsString('&lt;s&gt;Pdf task&lt;/s&gt;', $printed);
            $this->assertStringNotContainsString('<s>', $printed);
            // No planning: the label, escaped like the others
            $this->assertContains(htmlescape(__('None')), $pdf->printed);

            $pdf = $this->getPdfRecorder();
            ResourceHabilitation::pdfForResource($pdf, $resource);
            $printed = implode("\n", $pdf->printed);
            $this->assertStringContainsString('&lt;em&gt;Pdf habilitation&lt;/em&gt;', $printed);
            $this->assertStringNotContainsString('<em>', $printed);
        }
    }
}
