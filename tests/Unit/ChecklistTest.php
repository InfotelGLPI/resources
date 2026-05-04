<?php

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
