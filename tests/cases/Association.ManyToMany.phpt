<?php

use Tester\Assert;
use UniMapper\Association;
use UniMapper\Entity\Reflection;

require __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
class AssociationManyToManyTest extends TestCase
{

    /** @var array $adapters */
    private $adapters = [];

    /** @var \Mockery\Mock */
    private $adapterQueryMock;

    public function setUp()
    {
        $this->adapters["FooAdapter"] = Mockery::mock("UniMapper\Adapter");
        $this->adapters["BarAdapter"] = Mockery::mock("UniMapper\Adapter");

        $this->adapterQueryMock = Mockery::mock("UniMapper\Adapter\IQuery");
    }

    public function testSaveChangesAdd()
    {
        $this->adapters["BarAdapter"]
            ->shouldReceive("createInsert")
            ->with("barResource", [], "barId")
            ->once()
            ->andReturn($this->adapterQueryMock);
        $this->adapters["BarAdapter"]
            ->shouldReceive("onExecute")
            ->with($this->adapterQueryMock)
            ->once()
            ->andReturn(2);
        $this->adapters["FooAdapter"]
            ->shouldReceive("createModifyManyToMany")
            ->with(
                Mockery::type("UniMapper\Association\ManyToMany"),
                1,
                [2],
                \UniMapper\Adapter\IAdapter::ASSOC_ADD
            )
            ->once()
            ->andReturn($this->adapterQueryMock);
        $this->adapters["FooAdapter"]
            ->shouldReceive("onExecute")
            ->with($this->adapterQueryMock)
            ->once()
            ->andReturn(null);

        $connectionMock = Mockery::mock("UniMapper\Connection");
        $connectionMock->shouldReceive("getAdapter")
            ->once()
            ->with("FooAdapter")
            ->andReturn($this->adapters["FooAdapter"]);
        $connectionMock->shouldReceive("getAdapter")
            ->once()
            ->with("BarAdapter")
            ->andReturn($this->adapters["BarAdapter"]);

        $collection = Bar::createCollection();
        $collection->add(new Bar(["text" => "foo"]));

        $association = new Association\ManyToMany(
            "manyToMany",
            Foo::getReflection(),
            Bar::getReflection(),
            ["foo_fooId", "foo_bar", "bar_barId"]
        );

        Assert::null($association->saveChanges(1, $connectionMock, $collection));
    }

    public function testSaveChangesRemove()
    {
        $this->adapters["BarAdapter"]
            ->shouldReceive("createDeleteOne")
            ->with("barResource", "barId", 3)
            ->once()
            ->andReturn($this->adapterQueryMock);
        $this->adapters["BarAdapter"]
            ->shouldReceive("onExecute")
            ->with($this->adapterQueryMock)
            ->once()
            ->andReturn(2);
        $this->adapters["FooAdapter"]
            ->shouldReceive("createModifyManyToMany")
            ->with(
                Mockery::type("UniMapper\Association\ManyToMany"),
                1,
                [3],
                \UniMapper\Adapter\IAdapter::ASSOC_REMOVE
            )
            ->once()
            ->andReturn($this->adapterQueryMock);
        $this->adapters["FooAdapter"]
            ->shouldReceive("onExecute")
            ->with($this->adapterQueryMock)
            ->once()
            ->andReturn(null);

        $connectionMock = Mockery::mock("UniMapper\Connection");
        $connectionMock->shouldReceive("getAdapter")
            ->once()
            ->with("FooAdapter")
            ->andReturn($this->adapters["FooAdapter"]);
        $connectionMock->shouldReceive("getAdapter")
            ->once()
            ->with("BarAdapter")
            ->andReturn($this->adapters["BarAdapter"]);

        $collection = Bar::createCollection();
        $collection->remove(new Bar(["id" => 3, "text" => "foo"]));

        $association = new Association\ManyToMany(
            "manyToMany",
            Foo::getReflection(),
            Bar::getReflection(),
            ["foo_fooId", "foo_bar", "bar_barId"]
        );

        Assert::null($association->saveChanges(1, $connectionMock, $collection));
    }

}

/**
 * @adapter FooAdapter(fooResource)
 *
 * @property int $id m:primary m:map-by(fooId)
 */
class Foo extends \UniMapper\Entity {}

/**
 * @adapter BarAdapter(barResource)
 *
 * @property int $id m:primary m:map-by(barId)
 */
class Bar extends \UniMapper\Entity {}

$testCase = new AssociationManyToManyTest;
$testCase->run();