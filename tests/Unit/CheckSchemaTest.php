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

namespace GlpiPlugin\Resources\Tests\Unit;

use Glpi\System\Diagnostic\DatabaseSchemaIntegrityChecker;
use GlpiPlugin\Resources\CheckSchema;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CheckSchemaTest extends TestCase
{
    private const TABLE = 'glpi_plugin_resources_tests';

    protected function setUp(): void
    {
        // Empty session, to avoid any $_SESSION access error
        $_SESSION['glpiactiveprofile'] = [];
    }

    /**
     * Raw "CREATE TABLE" query, as found in install/sql/empty.sql.
     */
    private function getExpectedQuery(): string
    {
        return <<<SQL
        CREATE TABLE `glpi_plugin_resources_tests`
        (
            `id`        int unsigned NOT NULL auto_increment,
            `name`      varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `comment`   longtext COLLATE utf8mb4_unicode_ci,
            `is_active` tinyint      NOT NULL                   DEFAULT '0',
            PRIMARY KEY (`id`),
            KEY `name` (`name`)
        ) ENGINE = InnoDB
        SQL;
    }

    /**
     * @return array{safe: array<int, string>, destructive: array<int, string>}
     */
    private function buildFixQueries(array $difference): array
    {
        $method = new ReflectionMethod(CheckSchema::class, 'buildFixQueries');
        $method->setAccessible(true);

        return $method->invoke(
            new CheckSchema(),
            self::TABLE,
            $difference,
            [self::TABLE => $this->getExpectedQuery()],
        );
    }

    public function testMissingTableSuggestsCreateTable(): void
    {
        $queries = $this->buildFixQueries([
            'type' => DatabaseSchemaIntegrityChecker::RESULT_TYPE_MISSING_TABLE,
            'diff' => '',
        ]);

        $this->assertCount(1, $queries['safe']);
        $this->assertStringStartsWith('CREATE TABLE `glpi_plugin_resources_tests`', $queries['safe'][0]);
        $this->assertStringEndsWith(';', $queries['safe'][0]);
        $this->assertSame([], $queries['destructive']);
    }

    public function testUnknownTableSuggestsDropTableAsDestructive(): void
    {
        $queries = $this->buildFixQueries([
            'type' => DatabaseSchemaIntegrityChecker::RESULT_TYPE_UNKNOWN_TABLE,
            'diff' => '',
        ]);

        $this->assertSame([], $queries['safe']);
        $this->assertSame(['DROP TABLE `glpi_plugin_resources_tests`;'], $queries['destructive']);
    }

    public function testAlteredTableSuggestsAlterQueries(): void
    {
        $diff = <<<DIFF
        --- Expected database schema
        +++ Current database schema
        @@ @@
         CREATE TABLE `glpi_plugin_resources_tests` (
           `id` int unsigned NOT NULL AUTO_INCREMENT,
           `name` varchar(255),
        -  `comment` text,
        -  `is_active` tinyint NOT NULL DEFAULT 0,
        +  `comment` varchar(255),
        +  `zz_old` int NOT NULL DEFAULT 0,
           PRIMARY KEY (`id`),
        -  KEY `name` (`name`),
         ) ENGINE=InnoDB
        DIFF;

        $queries = $this->buildFixQueries([
            'type' => DatabaseSchemaIntegrityChecker::RESULT_TYPE_ALTERED_TABLE,
            'diff' => $diff,
        ]);

        $this->assertCount(1, $queries['safe']);
        $safe = $queries['safe'][0];

        $this->assertStringStartsWith('ALTER TABLE `glpi_plugin_resources_tests`', $safe);
        // Raw definition is used, so the "longtext" type is not reduced to the normalized "text" one
        $this->assertStringContainsString('MODIFY `comment` longtext COLLATE utf8mb4_unicode_ci', $safe);
        // Missing column is added at its expected position, with its raw definition
        $this->assertStringContainsString(
            "ADD `is_active` tinyint NOT NULL DEFAULT '0' AFTER `comment`",
            $safe,
        );
        // Missing index is added back
        $this->assertStringContainsString('ADD KEY `name` (`name`)', $safe);
        $this->assertStringEndsWith(';', $safe);

        // Column found in database only is proposed apart, as it drops data
        $this->assertCount(1, $queries['destructive']);
        $this->assertStringContainsString('DROP COLUMN `zz_old`', $queries['destructive'][0]);
        $this->assertStringNotContainsString('DROP COLUMN', $safe);
    }

    public function testAlteredIndexIsDroppedThenAddedInTheSameQuery(): void
    {
        $diff = <<<DIFF
        --- Expected database schema
        +++ Current database schema
        @@ @@
         CREATE TABLE `glpi_plugin_resources_tests` (
           `id` int unsigned NOT NULL AUTO_INCREMENT,
        -  KEY `name` (`name`),
        +  KEY `name` (`name`,`id`),
         ) ENGINE=InnoDB
        DIFF;

        $queries = $this->buildFixQueries([
            'type' => DatabaseSchemaIntegrityChecker::RESULT_TYPE_ALTERED_TABLE,
            'diff' => $diff,
        ]);

        $this->assertCount(1, $queries['safe']);
        $this->assertMatchesRegularExpression(
            '/DROP KEY `name`,\s+ADD KEY `name` \(`name`\)/',
            $queries['safe'][0],
        );
        $this->assertSame([], $queries['destructive']);
    }

    public function testUnexpectedIndexIsProposedAsDestructive(): void
    {
        $diff = <<<DIFF
        --- Expected database schema
        +++ Current database schema
        @@ @@
         CREATE TABLE `glpi_plugin_resources_tests` (
           `id` int unsigned NOT NULL AUTO_INCREMENT,
        +  KEY `zz_old` (`zz_old`),
         ) ENGINE=InnoDB
        DIFF;

        $queries = $this->buildFixQueries([
            'type' => DatabaseSchemaIntegrityChecker::RESULT_TYPE_ALTERED_TABLE,
            'diff' => $diff,
        ]);

        $this->assertSame([], $queries['safe']);
        $this->assertCount(1, $queries['destructive']);
        $this->assertStringContainsString('DROP KEY `zz_old`', $queries['destructive'][0]);
    }
}
