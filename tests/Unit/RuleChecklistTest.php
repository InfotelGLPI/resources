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

use GlpiPlugin\Resources\RuleChecklist;
use PHPUnit\Framework\TestCase;

class RuleChecklistTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION['glpiactiveprofile'] = [];
    }

    public function testMaybeRecursiveReturnsTrue(): void
    {
        $rule = new RuleChecklist();
        $this->assertTrue($rule->maybeRecursive());
    }

    public function testIsEntityAssignReturnsTrue(): void
    {
        $rule = new RuleChecklist();
        $this->assertTrue($rule->isEntityAssign());
    }

    public function testCanUnrecursReturnsTrue(): void
    {
        $rule = new RuleChecklist();
        $this->assertTrue($rule->canUnrecurs());
    }

    public function testGetCriteriasReturnsTwoKeys(): void
    {
        $rule = new RuleChecklist();
        $criterias = $rule->getCriterias();

        $this->assertCount(2, $criterias);
    }

    public function testGetCriteriasContainsContractTypeKey(): void
    {
        $rule = new RuleChecklist();
        $criterias = $rule->getCriterias();

        $this->assertArrayHasKey('plugin_resources_contracttypes_id', $criterias);
    }

    public function testGetCriteriasContainsChecklistTypeKey(): void
    {
        $rule = new RuleChecklist();
        $criterias = $rule->getCriterias();

        $this->assertArrayHasKey('checklist_type', $criterias);
    }

    public function testGetCriteriasChecklistTypeHasCorrectDropdownType(): void
    {
        $rule = new RuleChecklist();
        $criterias = $rule->getCriterias();

        $this->assertSame('dropdownChecklistType', $criterias['checklist_type']['type']);
    }

    public function testGetCriteriasChecklistTypeAllowConditionIsNotEmpty(): void
    {
        $rule = new RuleChecklist();
        $criterias = $rule->getCriterias();

        $this->assertNotEmpty($criterias['checklist_type']['allow_condition']);
    }
}
