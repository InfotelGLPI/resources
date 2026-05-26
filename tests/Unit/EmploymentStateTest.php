<?php

/*
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2015-2026 by the resources Development Team.

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

namespace GlpiPlugin\Resources\Tests\Unit;

use GlpiPlugin\Resources\EmploymentState;
use PHPUnit\Framework\TestCase;

class EmploymentStateTest extends TestCase
{
    protected function setUp(): void
    {
        // Session vide pour éviter toute erreur d'accès à $_SESSION
        $_SESSION['glpiactiveprofile'] = [];
    }

    public function testGetAdditionalFieldsReturnThreeFields(): void
    {
        $state = new EmploymentState();
        $fields = $state->getAdditionalFields();

        $this->assertCount(3, $fields);
    }

    public function testGetAdditionalFieldsFirstFieldIsShortName(): void
    {
        $state = new EmploymentState();
        $fields = $state->getAdditionalFields();

        $this->assertSame('short_name', $fields[0]['name']);
        $this->assertSame('text', $fields[0]['type']);
        $this->assertTrue($fields[0]['list']);
    }

    public function testGetAdditionalFieldsIsActiveFieldIsBool(): void
    {
        $state = new EmploymentState();
        $fields = $state->getAdditionalFields();

        $isActiveField = array_values(array_filter($fields, fn($f) => $f['name'] === 'is_active'));
        $this->assertCount(1, $isActiveField);
        $this->assertSame('bool', $isActiveField[0]['type']);
    }

    public function testGetAdditionalFieldsIsLeavingStateFieldIsBool(): void
    {
        $state = new EmploymentState();
        $fields = $state->getAdditionalFields();

        $leavingField = array_values(array_filter($fields, fn($f) => $f['name'] === 'is_leaving_state'));
        $this->assertCount(1, $leavingField);
        $this->assertSame('bool', $leavingField[0]['type']);
    }

    public function testPostGetEmptySetsIsActiveToOne(): void
    {
        $state = new EmploymentState();
        $state->post_getEmpty();

        $this->assertSame(1, $state->fields['is_active']);
    }
}