<?php

use Tester\Assert,
    UniMapper\Query\Update,
    UniMapper\Reflection;

require __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
class QueryUpdateTest extends UniMapper\Tests\TestCase
{

    /** @var array $adapters */
    private $adapters = [];

    public function setUp()
    {
        $this->adapters["FooAdapter"] = Mockery::mock("UniMapper\Adapter");
    }

    /**
     * @throws UniMapper\Exception\QueryException Nothing to update!
     */
    public function testNoValues()
    {
        $connectionMock = Mockery::mock("UniMapper\Connection");
        $connectionMock->shouldReceive("getMapper")->once()->andReturn(new UniMapper\Mapper);

        $query = new Update(new Reflection\Entity("UniMapper\Tests\Fixtures\Entity\Simple"), []);
        $query->run($connectionMock);
    }

    public function testSuccess()
    {
        $adapterQueryMock = Mockery::mock("UniMapper\Adapter\IQuery");
        $adapterQueryMock->shouldReceive("setConditions")
            ->once()
            ->with([["simplePrimaryId", "=", 1, "AND"]]);
        $adapterQueryMock->shouldReceive("getRaw")->once();

        $this->adapters["FooAdapter"]->shouldReceive("createUpdate")
            ->once()
            ->with("simple_resource", ['text'=>'foo'])
            ->andReturn($adapterQueryMock);

        $this->adapters["FooAdapter"]->shouldReceive("onExecute")
            ->once()
            ->with($adapterQueryMock)
            ->andReturn("2");

        $connectionMock = Mockery::mock("UniMapper\Connection");
        $connectionMock->shouldReceive("getMapper")->once()->andReturn(new UniMapper\Mapper);
        $connectionMock->shouldReceive("getAdapters")->once()->andReturn($this->adapters);

        $query = new Update(
            new Reflection\Entity("UniMapper\Tests\Fixtures\Entity\Simple"),
            ["text" => "foo", "oneToOne" => ["id" => 3]]
        );
        $query->where("id", "=", 1);
        Assert::same(2, $query->run($connectionMock));
    }

}

$testCase = new QueryUpdateTest;
$testCase->run();
