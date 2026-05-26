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

use GlpiPlugin\Resources\Checklist;
use PHPUnit\Framework\TestCase;

class ChecklistTest extends TestCase
{
    public function testChecklistInConstantIsOne(): void
    {
        $this->assertSame(1, Checklist::RESOURCES_CHECKLIST_IN);
    }

    public function testChecklistOutConstantIsTwo(): void
    {
        $this->assertSame(2, Checklist::RESOURCES_CHECKLIST_OUT);
    }

    public function testChecklistTransferConstantIsThree(): void
    {
        $this->assertSame(3, Checklist::RESOURCES_CHECKLIST_TRANSFER);
    }

    public function testChecklistConstantsAreDistinct(): void
    {
        $this->assertNotSame(Checklist::RESOURCES_CHECKLIST_IN, Checklist::RESOURCES_CHECKLIST_OUT);
        $this->assertNotSame(Checklist::RESOURCES_CHECKLIST_OUT, Checklist::RESOURCES_CHECKLIST_TRANSFER);
        $this->assertNotSame(Checklist::RESOURCES_CHECKLIST_IN, Checklist::RESOURCES_CHECKLIST_TRANSFER);
    }
}
