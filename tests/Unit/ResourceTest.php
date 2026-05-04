<?php

namespace GlpiPlugin\Resources\Tests\Unit;

use Glpi\Exception\Http\BadRequestHttpException;
use GlpiPlugin\Resources\Resource;
use PHPUnit\Framework\TestCase;

class ResourceTest extends TestCase
{
    protected function setUp(): void
    {
        global $DB;
        $_SESSION['glpiactiveprofile'] = [];
        // Session::haveRight() et Rank::canCreate() nécessitent $DB->isSlave()
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

    // -------------------------------------------------------------------------
    // replace_accents()
    // -------------------------------------------------------------------------

    public function testReplaceAccentsRemovesAcuteAccent(): void
    {
        $resource = new Resource();
        $this->assertSame('cafe', $resource->replace_accents('café'));
    }

    public function testReplaceAccentsRemovesGraveAccent(): void
    {
        $resource = new Resource();
        $this->assertSame('a', $resource->replace_accents('à'));
    }

    public function testReplaceAccentsRemovesUmlaut(): void
    {
        $resource = new Resource();
        $this->assertSame('naive', $resource->replace_accents('naïve'));
    }

    public function testReplaceAccentsRemovesCedilla(): void
    {
        $resource = new Resource();
        $this->assertSame('francais', $resource->replace_accents('français'));
    }

    public function testReplaceAccentsLeavesAsciiUnchanged(): void
    {
        $resource = new Resource();
        $this->assertSame('hello_world', $resource->replace_accents('hello_world'));
    }

    public function testReplaceAccentsOnEmptyStringReturnsEmpty(): void
    {
        $resource = new Resource();
        $this->assertSame('', $resource->replace_accents(''));
    }

    // -------------------------------------------------------------------------
    // getDataTypes()
    // -------------------------------------------------------------------------

    public function testGetDataTypesReturnsTwelveElements(): void
    {
        $this->assertCount(12, Resource::getDataTypes());
    }

    public function testGetDataTypesFirstElementIsString(): void
    {
        $types = Resource::getDataTypes();
        $this->assertSame('String', $types[0]);
    }

    public function testGetDataTypesContainsDate(): void
    {
        $this->assertContains('Date', Resource::getDataTypes());
    }

    // -------------------------------------------------------------------------
    // getDataType()
    // -------------------------------------------------------------------------

    public function testGetDataTypeIndex0ReturnsString(): void
    {
        $this->assertSame('String', Resource::getDataType(0));
    }

    public function testGetDataTypeIndex7ReturnsDate(): void
    {
        $this->assertSame('Date', Resource::getDataType(7));
    }

    public function testGetDataTypeThrowsForInvalidIndex(): void
    {
        $this->expectException(BadRequestHttpException::class);
        Resource::getDataType(999);
    }

    // -------------------------------------------------------------------------
    // getResourceColumnNameFromDataNameID()
    // -------------------------------------------------------------------------

    public function testGetResourceColumnNameIndex0ReturnsFirstname(): void
    {
        $this->assertSame('firstname', Resource::getResourceColumnNameFromDataNameID(0));
    }

    public function testGetResourceColumnNameIndex1ReturnsName(): void
    {
        $this->assertSame('name', Resource::getResourceColumnNameFromDataNameID(1));
    }

    public function testGetResourceColumnNameIndex7ReturnsDateBegin(): void
    {
        $this->assertSame('date_begin', Resource::getResourceColumnNameFromDataNameID(7));
    }

    public function testGetResourceColumnNameThrowsForInvalidIndex(): void
    {
        $this->expectException(BadRequestHttpException::class);
        Resource::getResourceColumnNameFromDataNameID(999);
    }

    // -------------------------------------------------------------------------
    // getColumnName()
    // -------------------------------------------------------------------------

    public function testGetColumnNameIndex0ReturnsFirstname(): void
    {
        $this->assertSame('firstname', Resource::getColumnName(0));
    }

    public function testGetColumnNameIndex1ReturnsName(): void
    {
        $this->assertSame('name', Resource::getColumnName(1));
    }

    public function testGetColumnNameThrowsForInvalidIndex(): void
    {
        $this->expectException(BadRequestHttpException::class);
        Resource::getColumnName(999);
    }

    // -------------------------------------------------------------------------
    // registerType() / getTypes(true)
    // -------------------------------------------------------------------------

    public function testGetTypesAllIncludesComputer(): void
    {
        $this->assertContains(\Computer::class, Resource::getTypes(true));
    }

    public function testRegisterTypeAddsNewType(): void
    {
        $before = Resource::getTypes(true);
        Resource::registerType('MyCustomType');

        $this->assertContains('MyCustomType', Resource::getTypes(true));

        // nettoyage de l'état statique
        Resource::$types = $before;
    }

    public function testRegisterTypeDoesNotDuplicateExistingType(): void
    {
        $before = count(Resource::getTypes(true));
        Resource::registerType(\Computer::class); // déjà présent
        $this->assertSame($before, count(Resource::getTypes(true)));
    }

    // -------------------------------------------------------------------------
    // prepareInputForAdd() — logique de normalisation des champs par défaut
    // Le flag `force` court-circuite checkRequiredFields() (qui utilise la DB).
    // -------------------------------------------------------------------------

    public function testPrepareInputForAddWithForceRemovesForceKey(): void
    {
        $resource = new Resource();
        $result = $resource->prepareInputForAdd(['force' => true, 'entities_id' => 0]);

        $this->assertArrayNotHasKey('force', $result);
    }

    public function testPrepareInputForAddSetsSensitizeSecurityDefaultToZero(): void
    {
        $resource = new Resource();
        $result = $resource->prepareInputForAdd(['force' => true, 'entities_id' => 0]);

        $this->assertSame(0, $result['sensitize_security']);
    }

    public function testPrepareInputForAddSetsReadChartDefaultToZero(): void
    {
        $resource = new Resource();
        $result = $resource->prepareInputForAdd(['force' => true, 'entities_id' => 0]);

        $this->assertSame(0, $result['read_chart']);
    }

    public function testPrepareInputForAddSetsResourceStateDefaultToZeroString(): void
    {
        $resource = new Resource();
        $result = $resource->prepareInputForAdd(['force' => true, 'entities_id' => 0]);

        $this->assertSame('0', $result['plugin_resources_resourcestates_id']);
    }

    public function testPrepareInputForAddSetsPictureToNullString(): void
    {
        $resource = new Resource();
        $result = $resource->prepareInputForAdd(['force' => true, 'entities_id' => 0]);

        $this->assertSame('NULL', $result['picture']);
    }

    public function testPrepareInputForAddConvertsEmptyDateEndToNullString(): void
    {
        $resource = new Resource();
        $result = $resource->prepareInputForAdd(['force' => true, 'entities_id' => 0, 'date_end' => '']);

        $this->assertSame('NULL', $result['date_end']);
    }

    public function testPrepareInputForAddPreservesProvidedResourceState(): void
    {
        $resource = new Resource();
        $result = $resource->prepareInputForAdd([
            'force' => true,
            'entities_id' => 0,
            'plugin_resources_resourcestates_id' => 5,
        ]);

        $this->assertSame(5, $result['plugin_resources_resourcestates_id']);
    }

    public function testPrepareInputForAddWithIsTemplateSetsDefaultsWithoutRequiredCheck(): void
    {
        $resource = new Resource();
        $result = $resource->prepareInputForAdd(['is_template' => 1, 'entities_id' => 0]);

        $this->assertSame(0, $result['sensitize_security']);
        $this->assertSame(0, $result['read_chart']);
        $this->assertSame('NULL', $result['picture']);
    }
}
