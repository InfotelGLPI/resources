<?php

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