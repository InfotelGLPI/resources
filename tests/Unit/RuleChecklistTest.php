<?php

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
