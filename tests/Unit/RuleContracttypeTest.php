<?php

namespace GlpiPlugin\Resources\Tests\Unit;

use GlpiPlugin\Resources\RuleContracttype;
use PHPUnit\Framework\TestCase;

class RuleContracttypeTest extends TestCase
{
    protected function setUp(): void
    {
        global $DB;
        $_SESSION['glpiactiveprofile'] = [];
        // Session::haveRight() appelle $DB->isSlave() — on fournit un stub minimal
        $DB = new class {
            public function isSlave(): bool { return false; }
            public function request($criteria, ...$args): \ArrayIterator { return new \ArrayIterator([]); }
        };
    }

    protected function tearDown(): void
    {
        global $DB;
        $DB = null;
    }

    public function testMaxCriteriasCountReturnsOne(): void
    {
        $rule = new RuleContracttype();
        $this->assertSame(1, $rule->maxCriteriasCount());
    }

    public function testMaybeRecursiveReturnsTrue(): void
    {
        $rule = new RuleContracttype();
        $this->assertTrue($rule->maybeRecursive());
    }

    public function testIsEntityAssignReturnsTrue(): void
    {
        $rule = new RuleContracttype();
        $this->assertTrue($rule->isEntityAssign());
    }

    public function testCanUnrecursReturnsTrue(): void
    {
        $rule = new RuleContracttype();
        $this->assertTrue($rule->canUnrecurs());
    }

    public function testGetCriteriasContainsContractTypeKey(): void
    {
        $rule = new RuleContracttype();
        $criterias = $rule->getCriterias();

        $this->assertArrayHasKey('plugin_resources_contracttypes_id', $criterias);
    }

    public function testGetCriteriasContractTypeHasCorrectDropdownType(): void
    {
        $rule = new RuleContracttype();
        $criterias = $rule->getCriterias();

        $this->assertSame('dropdownContractType', $criterias['plugin_resources_contracttypes_id']['type']);
    }

    public function testGetCriteriasContractTypeAllowConditionIsNotEmpty(): void
    {
        $rule = new RuleContracttype();
        $criterias = $rule->getCriterias();

        $this->assertNotEmpty($criterias['plugin_resources_contracttypes_id']['allow_condition']);
    }

    public function testGetActionsContainsRequiredFieldsName(): void
    {
        $rule = new RuleContracttype();
        $actions = $rule->getActions();

        $this->assertArrayHasKey('requiredfields_name', $actions);
    }

    public function testGetActionsContainsRequiredFieldsFirstname(): void
    {
        $rule = new RuleContracttype();
        $actions = $rule->getActions();

        $this->assertArrayHasKey('requiredfields_firstname', $actions);
    }

    public function testGetActionsAllHaveYesonlyType(): void
    {
        $rule = new RuleContracttype();
        $actions = $rule->getActions();

        foreach ($actions as $key => $action) {
            $this->assertSame('yesonly', $action['type'], "L'action '$key' devrait avoir le type 'yesonly'.");
        }
    }

    public function testGetActionsAllHaveAssignInForceActions(): void
    {
        $rule = new RuleContracttype();
        $actions = $rule->getActions();

        foreach ($actions as $key => $action) {
            $this->assertContains('assign', $action['force_actions'], "L'action '$key' devrait avoir 'assign' dans force_actions.");
        }
    }
}
