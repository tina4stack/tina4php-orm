<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 *
 * Unit tests for tina4php-orm that do not require a live database connection.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../src/orm/Person.php";

/**
 * A minimal ORM subclass for testing without database annotations.
 */
class SimpleItem extends \Tina4\ORM
{
    public $primaryKey = "id";
    public $tableName = "simple_item";

    /**
     * @var integer not null
     */
    public $id;

    /**
     * @var varchar(200)
     */
    public $itemName;

    /**
     * @var varchar(500)
     */
    public $itemDescription;

    /**
     * @var integer
     */
    public $categoryId;
}

/**
 * ORM subclass with field mapping to exercise mapped-field logic.
 */
class MappedRecord extends \Tina4\ORM
{
    public $primaryKey = "id";
    public $tableName = "mapped_record";
    public $fieldMapping = [
        "companyName" => "org_name",
        "companyCode" => "org_code",
    ];

    /**
     * @var integer not null
     */
    public $id;

    /**
     * @var varchar(200)
     */
    public $companyName;

    /**
     * @var varchar(50)
     */
    public $companyCode;
}

/**
 * A mock database adapter that satisfies the Tina4\DataBase interface
 * without connecting to anything. Used to test SQL generation methods
 * that call DBA->getQueryParam() and similar helpers.
 */
class MockDBA implements \Tina4\DataBase
{
    public $dateFormat = "Y-m-d";

    public function __construct(string $database = "", string $username = "", string $password = "", string $dateFormat = "Y-m-d", string $charset = "utf8", string $certificateFile = "")
    {
        $this->dateFormat = $dateFormat;
    }

    public function open() { return true; }
    public function close() { return true; }
    public function exec() { return new \Tina4\DataError(0, "none"); }
    public function getLastId(): string { return "1"; }
    public function tableExists(string $tableName): bool { return true; }
    public function fetch($sql, int $noOfRecords = 10, int $offSet = 0, array $fieldMapping = []): ?\Tina4\DataResult { return null; }
    public function commit($transactionId = null) { return true; }
    public function rollback($transactionId = null) { return true; }
    public function autoCommit(bool $onState = true): void {}
    public function startTransaction() { return "txn1"; }
    public function error() { return false; }
    public function getDatabase(): array { return []; }
    public function getDefaultDatabaseDateFormat(): string { return "Y-m-d"; }
    public function getDefaultDatabasePort(): ?int { return 0; }
    public function getQueryParam(string $fieldName, int $fieldIndex): string { return "?"; }
    public function isNoSQL(): bool { return false; }
    public function getShortName(): string { return "mock"; }
    public function supportsReturning(): bool { return false; }
}


class ORMUnitTest extends TestCase
{
    private MockDBA $mockDBA;

    protected function setUp(): void
    {
        // Provide a global $DBA so that ORM construction does not fail when
        // it tries to resolve a database connection.
        $this->mockDBA = new MockDBA();
        $GLOBALS["DBA"] = $this->mockDBA;

        // Make sure debug constants exist
        if (!defined("TINA4_LOG_DEBUG")) {
            define("TINA4_LOG_DEBUG", "debug");
        }
    }

    protected function tearDown(): void
    {
        unset($GLOBALS["DBA"]);
    }

    // ---------------------------------------------------------------
    // 1. getFieldName: camelCase -> snake_case conversion
    // ---------------------------------------------------------------
    public function testGetFieldNameConvertsToSnakeCase(): void
    {
        $item = new SimpleItem();
        // getFieldName inserts underscores before uppercase but preserves the letter's case
        $this->assertSame("item_Name", $item->getFieldName("itemName"));
        $this->assertSame("item_Description", $item->getFieldName("itemDescription"));
        $this->assertSame("category_Id", $item->getFieldName("categoryId"));
    }

    public function testGetFieldNamePreservesAlreadySnakeCase(): void
    {
        $item = new SimpleItem();
        // If the name already contains an underscore it is returned as-is.
        $this->assertSame("item_name", $item->getFieldName("item_name"));
    }

    // ---------------------------------------------------------------
    // 2. getObjectName / camelCase: snake_case -> camelCase
    // ---------------------------------------------------------------
    public function testCamelCaseConversion(): void
    {
        $item = new SimpleItem();
        $this->assertSame("testName", $item->camelCase("test_name"));
        $this->assertSame("firstName", $item->camelCase("first_name"));
        $this->assertSame("myLongFieldName", $item->camelCase("my_long_field_name"));
    }

    public function testGetObjectNameWithCamelCaseFlag(): void
    {
        $item = new SimpleItem();
        // With camelCase = true the name should be converted.
        $this->assertSame("testName", $item->getObjectName("test_name", true));
        // With camelCase = false it should be returned as-is.
        $this->assertSame("test_name", $item->getObjectName("test_name", false));
    }

    // ---------------------------------------------------------------
    // 3. getObjectName with fieldMapping
    // ---------------------------------------------------------------
    public function testGetObjectNameUsesFieldMapping(): void
    {
        $rec = new MappedRecord();
        // fieldMapping: companyName => org_name, so reverse lookup: org_name => companyName
        $this->assertSame("companyName", $rec->getObjectName("org_name", true));
        $this->assertSame("companyCode", $rec->getObjectName("org_code", true));
    }

    // ---------------------------------------------------------------
    // 4. getTableName derivation from class name
    // ---------------------------------------------------------------
    public function testGetTableNameFromClassName(): void
    {
        // Person class should derive table name "person"
        $person = new Person();
        $this->assertSame("person", $person->getTableName());
    }

    public function testGetTableNameExplicit(): void
    {
        // SimpleItem has tableName = "simple_item" set explicitly.
        $item = new SimpleItem();
        $this->assertSame("simple_item", $item->getTableName());
    }

    // ---------------------------------------------------------------
    // 5. Pluralization
    // ---------------------------------------------------------------
    public function testPluralizeSuffix(): void
    {
        $orm = new SimpleItem();
        // Default: adds "s"
        $this->assertSame("persons", $orm->pluralize("Person"));
        // Ending in 'y': replace with 'ies'
        $this->assertSame("categories", $orm->pluralize("Category"));
        // Ending in 's': adds 'es'
        $this->assertSame("addresses", $orm->pluralize("Address"));
    }

    // ---------------------------------------------------------------
    // 6. getPrimaryCheck builds correct WHERE clause
    // ---------------------------------------------------------------
    public function testGetPrimaryCheckSimple(): void
    {
        $item = new SimpleItem();
        $item->id = 42;
        $tableData = $item->getTableData();
        $check = $item->getPrimaryCheck($tableData);
        $this->assertSame("id = '42'", $check);
    }

    public function testGetPrimaryCheckNullKey(): void
    {
        $item = new SimpleItem();
        // id is null
        $tableData = $item->getTableData();
        $check = $item->getPrimaryCheck($tableData);
        // When the value is empty string it should become "is null"
        $this->assertStringContainsString("is null", $check);
    }

    // ---------------------------------------------------------------
    // 7. ORMSQLGenerator::generateDeleteSQL
    // ---------------------------------------------------------------
    public function testGenerateDeleteSQL(): void
    {
        $gen = new \Tina4\ORMSQLGenerator();
        $sql = $gen->generateDeleteSQL("id = '5'", "simple_item");
        $this->assertSame("delete from simple_item where id = '5'", $sql);
    }

    public function testGenerateDeleteSQLComplexFilter(): void
    {
        $gen = new \Tina4\ORMSQLGenerator();
        $sql = $gen->generateDeleteSQL("category_id = '3' and id = '10'", "products");
        $this->assertSame("delete from products where category_id = '3' and id = '10'", $sql);
    }

    // ---------------------------------------------------------------
    // 8. ORMSQLGenerator::generateInsertSQL
    // ---------------------------------------------------------------
    public function testGenerateInsertSQL(): void
    {
        $item = new SimpleItem();
        $item->DBA = $this->mockDBA;
        $item->id = 1;
        $item->itemName = "Widget";
        $item->itemDescription = "A fine widget";
        $item->categoryId = 7;

        $tableData = $item->getTableData();
        $gen = new \Tina4\ORMSQLGenerator();
        $result = $gen->generateInsertSQL($tableData, "simple_item", $item);

        $this->assertArrayHasKey("sql", $result);
        $this->assertArrayHasKey("fieldValues", $result);

        $sql = $result["sql"];
        $this->assertStringStartsWith("insert into simple_item", $sql);
        $this->assertStringContainsString("item_Name", $sql);
        $this->assertStringContainsString("item_Description", $sql);
        $this->assertStringContainsString("category_Id", $sql);
        $this->assertStringContainsString("values (", $sql);

        // Field values should contain the actual data
        $this->assertContains("Widget", $result["fieldValues"]);
        $this->assertContains("A fine widget", $result["fieldValues"]);
        $this->assertContains(7, $result["fieldValues"]);
    }

    public function testGenerateInsertSQLSkipsNullFields(): void
    {
        $item = new SimpleItem();
        $item->DBA = $this->mockDBA;
        $item->id = 1;
        $item->itemName = "OnlyName";
        // categoryId and itemDescription left as null

        $tableData = $item->getTableData();
        $gen = new \Tina4\ORMSQLGenerator();
        $result = $gen->generateInsertSQL($tableData, "simple_item", $item);

        $sql = $result["sql"];
        // Null fields should be skipped
        $this->assertStringNotContainsString("category_Id", $sql);
        $this->assertStringNotContainsString("item_Description", $sql);
    }

    // ---------------------------------------------------------------
    // 9. ORMSQLGenerator::generateUpdateSQL
    // ---------------------------------------------------------------
    public function testGenerateUpdateSQL(): void
    {
        $item = new SimpleItem();
        $item->DBA = $this->mockDBA;
        $item->id = 5;
        $item->itemName = "Updated Widget";
        $item->categoryId = 3;

        $tableData = $item->getTableData();
        $filter = "id = '5'";
        $gen = new \Tina4\ORMSQLGenerator();
        $result = $gen->generateUpdateSQL($tableData, $filter, "simple_item", $item);

        $sql = $result["sql"];
        $this->assertStringStartsWith("update simple_item set", $sql);
        $this->assertStringContainsString("where id = '5'", $sql);
        $this->assertStringContainsString("item_Name", $sql);

        // The primary key field should NOT appear in the SET clause
        // (generateUpdateSQL skips the primary key)
        $setPart = explode("where", $sql)[0];
        // "id" by itself as a column assignment should not be present
        $this->assertStringNotContainsString("id =", $setPart);

        $this->assertContains("Updated Widget", $result["fieldValues"]);
    }

    // ---------------------------------------------------------------
    // 10. ORMCache without backend returns null / false
    // ---------------------------------------------------------------
    public function testORMCacheGetWithoutBackendReturnsNull(): void
    {
        // Ensure no global cache is set
        unset($GLOBALS["cache"]);
        $cache = new \Tina4\ORMCache();
        $result = $cache->get("nonexistent_key");
        $this->assertNull($result);
    }

    public function testORMCacheSetWithoutBackendReturnsFalse(): void
    {
        unset($GLOBALS["cache"]);
        $cache = new \Tina4\ORMCache();
        $result = $cache->set("some_key", "some_value", 60);
        $this->assertFalse($result);
    }

    public function testORMCacheSetReturnsTrueWhenCacheOff(): void
    {
        // When TINA4_CACHE_ON is defined as false, set() returns true immediately
        if (!defined("TINA4_CACHE_ON")) {
            define("TINA4_CACHE_ON", false);
        }
        $cache = new \Tina4\ORMCache();
        $result = $cache->set("any_key", "any_value", 60);
        $this->assertTrue($result);
    }

    public function testORMCacheGetReturnsNullWhenCacheOff(): void
    {
        // TINA4_CACHE_ON already defined as false above
        $cache = new \Tina4\ORMCache();
        $result = $cache->get("any_key");
        $this->assertNull($result);
    }

    // ---------------------------------------------------------------
    // 11. ORM construction from array populates fields
    // ---------------------------------------------------------------
    public function testORMConstructFromArray(): void
    {
        $person = new Person(["firstName" => "Alice", "lastName" => "Smith"]);
        $this->assertSame("Alice", $person->firstName);
        $this->assertSame("Smith", $person->lastName);
    }

    public function testORMConstructFromArrayExtraFieldsGoToVirtual(): void
    {
        $person = new Person(["firstName" => "Bob", "extraField" => "extraValue"]);
        $this->assertSame("Bob", $person->firstName);
        // Extra fields are added as virtual fields
        $this->assertSame("extraValue", $person->extraField);
        $this->assertContains("extraField", $person->virtualFields);
        $this->assertContains("extraField", $person->excludeFields);
    }

    // ---------------------------------------------------------------
    // 12. getTableData returns correct structure
    // ---------------------------------------------------------------
    public function testGetTableDataReturnsSnakeCaseKeys(): void
    {
        $item = new SimpleItem();
        $item->id = 10;
        $item->itemName = "Test";
        $item->categoryId = 2;

        $data = $item->getTableData();
        $this->assertArrayHasKey("id", $data);
        $this->assertArrayHasKey("item_Name", $data);
        $this->assertArrayHasKey("category_Id", $data);
        $this->assertSame("Test", $data["item_Name"]);
        $this->assertSame(2, $data["category_Id"]);
    }

    // ---------------------------------------------------------------
    // 13. getFieldNames returns snake_case list
    // ---------------------------------------------------------------
    public function testGetFieldNamesReturnsList(): void
    {
        $item = new SimpleItem();
        $names = $item->getFieldNames();
        $this->assertContains("id", $names);
        $this->assertContains("item_Name", $names);
        $this->assertContains("item_Description", $names);
        $this->assertContains("category_Id", $names);
    }

    // ---------------------------------------------------------------
    // 14. getTableColumnName produces display-friendly names
    // ---------------------------------------------------------------
    public function testGetTableColumnNameDisplayFriendly(): void
    {
        $item = new SimpleItem();
        $this->assertSame("First Name", $item->getTableColumnName("firstName"));
        $this->assertSame("Item Description", $item->getTableColumnName("itemDescription"));
        $this->assertSame("Id", $item->getTableColumnName("id"));
    }

    // ---------------------------------------------------------------
    // 15. asArray / asObject / jsonSerialize
    // ---------------------------------------------------------------
    public function testAsArrayExcludesProtectedFields(): void
    {
        $item = new SimpleItem();
        $item->id = 1;
        $item->itemName = "Gadget";

        $arr = $item->asArray();
        // Protected fields like primaryKey, tableName, DBA should not appear
        $this->assertArrayNotHasKey("primaryKey", $arr);
        $this->assertArrayNotHasKey("DBA", $arr);
        $this->assertArrayNotHasKey("tableName", $arr);
        $this->assertArrayNotHasKey("protectedFields", $arr);
        // Actual data fields should appear
        $this->assertArrayHasKey("id", $arr);
        $this->assertArrayHasKey("itemName", $arr);
    }

    public function testAsObjectReturnsStdClass(): void
    {
        $item = new SimpleItem();
        $item->id = 1;
        $item->itemName = "Gadget";

        $obj = $item->asObject();
        $this->assertIsObject($obj);
        $this->assertSame(1, $obj->id);
        $this->assertSame("Gadget", $obj->itemName);
    }

    // ---------------------------------------------------------------
    // 16. getFieldName with fieldMapping
    // ---------------------------------------------------------------
    public function testGetFieldNameWithMapping(): void
    {
        $rec = new MappedRecord();
        $mapping = $rec->fieldMapping;
        // companyName should map to org_name
        $this->assertSame("org_name", $rec->getFieldName("companyName", $mapping));
        $this->assertSame("org_code", $rec->getFieldName("companyCode", $mapping));
    }

    // ---------------------------------------------------------------
    // 17. generateCreateSQL produces DDL
    // ---------------------------------------------------------------
    public function testGenerateCreateSQLForPerson(): void
    {
        $person = new Person();
        $sql = $person->generateCreateSQL("person");
        $this->assertStringContainsString("create table person", $sql);
        $this->assertStringContainsString("first_name varchar(100)", $sql);
        $this->assertStringContainsString("last_name varchar(100)", $sql);
        $this->assertStringContainsString("primary key (id)", $sql);
    }

    // ---------------------------------------------------------------
    // 18. parseFilter replaces placeholders
    // ---------------------------------------------------------------
    public function testParseFilterReplacesPlaceholders(): void
    {
        $item = new SimpleItem();
        $item->DBA = $this->mockDBA;
        $result = $item->parseFilter("id = ? and item_name = ?", [42, "Widget"]);
        $this->assertStringContainsString("'42'", $result);
        $this->assertStringContainsString("'Widget'", $result);
    }

    // ---------------------------------------------------------------
    // 19. mapFromRecord populates object
    // ---------------------------------------------------------------
    public function testMapFromRecordPopulatesFields(): void
    {
        $item = new SimpleItem();
        $record = [
            "id" => 99,
            "item_name" => "Mapped Widget",
            "item_description" => "Desc from DB",
            "category_id" => 5,
        ];
        $item->mapFromRecord($record, true);
        // mapFromRecord with overRide=true should set values
        $this->assertSame(99, $item->id);
    }
}
