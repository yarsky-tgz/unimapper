<?php

use Tester\Assert,
    UniMapper\Query,
    UniMapper\Reflection;

require __DIR__ . '/../bootstrap.php';

class QueryFindTest extends UniMapper\Tests\TestCase
{

    /** @var array $adapters */
    private $adapters = [];

    public function setUp()
    {
        $this->adapters["FooAdapter"] = Mockery::mock("UniMapper\Tests\Fixtures\Adapter\Simple");
        $this->adapters["FooAdapter"]->shouldReceive("getMapping")->once()->andReturn(new UniMapper\Mapping);

        $this->adapters["RemoteAdapter"] = Mockery::mock("UniMapper\Tests\Fixtures\Adapter\Simple");
        $this->adapters["RemoteAdapter"]->shouldReceive("getMapping")->once()->andReturn(new UniMapper\Mapping);
    }

    public function testNoAssociations()
    {
        $entity1 = $this->createEntity("Simple", ["id" => 2]);
        $entity2 = $this->createEntity("Simple", ["id" => 3]);

        $collection = new UniMapper\EntityCollection($entity1->getReflection());
        $collection[] = $entity1;
        $collection[] = $entity2;

        $this->adapters["FooAdapter"]->shouldReceive("find")
            ->with(
                "simple_resource",
                ["link", "text", "id"],
                [
                    ["id", ">", 1, "AND"],
                    [
                        [
                            ["text", "LIKE", "%foo", "AND"]
                        ],
                        'OR'
                    ]
                ],
                ["id" => "desc"],
                null,
                null,
                []
            )
            ->once()
            ->andReturn([["id" => 2], ["id" => 3]]);

        $query = new Query\Find(new Reflection\Entity("UniMapper\Tests\Fixtures\Entity\Simple"), $this->adapters, "url", "text");
        $query->where("id", ">", 1)
                ->orWhereAre(function($query) {
                    $query->where("text", "LIKE", "%foo");
        })->orderBy("id", "DESC");
        $result = $query->execute();

        Assert::type("Unimapper\EntityCollection", $result);
        Assert::same(2, count($result));
        Assert::type("UniMapper\Tests\Fixtures\Entity\Simple", $result[0]);
        Assert::type("UniMapper\Tests\Fixtures\Entity\Simple", $result[1]);
    }

    public function testAssociateManyToOneRemote()
    {
        $this->adapters["FooAdapter"]->shouldReceive("find")
            ->with("simple_resource", ["id", "remoteId"], [], [], null, null, [])
            ->once()
            ->andReturn([["id" => 1, "remoteId" => 3], ["id" => 2, "remoteId" => 4]]);

        $this->adapters["RemoteAdapter"]->shouldReceive("find")
            ->with(
                "remote_resource",
                [],
                [["id", "IN", [3, 4], "AND"]]
            )
            ->once()
            ->andReturn([["id" => 3], ["id" => 4]]);

        $query = new Query\Find(new Reflection\Entity("UniMapper\Tests\Fixtures\Entity\Simple"), $this->adapters, "id");
        $result = $query->associate("manyToOne")->execute();

        Assert::count(2, $result);
        Assert::same(3, $result[0]->manyToOne->id);
        Assert::same(4, $result[1]->manyToOne->id);
    }

    public function testAssociateManyToMany()
    {
        $this->adapters["FooAdapter"]->shouldReceive("find")
            ->with(
                "simple_resource",
                ["id"],
                [],
                [],
                null,
                null,
                Mockery::on(function($arg) {
                    return $arg["collection"] instanceof Reflection\Entity\Property\Association\ManyToMany;
                })
            )
            ->once()
            ->andReturn(false);

        $query = new Query\Find(new Reflection\Entity("UniMapper\Tests\Fixtures\Entity\Simple"), $this->adapters, "id");
        Assert::count(0, $query->associate("collection")->execute());
    }

    public function testAssociateManyToManyRemoteNoRecords()
    {
        $this->adapters["FooAdapter"]->shouldReceive("find")
            ->with("simple_resource", ["id"], [], [], null, null, [])
            ->once()
            ->andReturn([["id" => 1], ["id" => 2]]);
        $this->adapters["FooAdapter"]->shouldReceive("find")
            ->with(
                "simple_remote",
                ['simpleId', 'remoteId'],
                [["simpleId", "IN", [1, 2], "AND"]]
            )
            ->once()
            ->andReturn([]);

        $query = new Query\Find(new Reflection\Entity("UniMapper\Tests\Fixtures\Entity\Simple"), $this->adapters, "id");
        $result = $query->associate("manyToMany")->execute();

        Assert::count(2, $result);

        Assert::count(0, $result[0]->manyToMany);
        Assert::count(0, $result[1]->manyToMany);
    }

    public function testAssociateManyToManyRemote()
    {
        $this->adapters["FooAdapter"]->shouldReceive("find")
            ->with("simple_resource", ["id"], [], [], null, null, [])
            ->once()
            ->andReturn([["id" => 1], ["id" => 2]]);
        $this->adapters["FooAdapter"]->shouldReceive("find")
            ->with(
                "simple_remote",
                ['simpleId', 'remoteId'],
                [["simpleId", "IN", [1, 2], "AND"]]
            )
            ->once()
            ->andReturn(
                [
                    ["simpleId" => 1, "remoteId" => 3],
                    ["simpleId" => 2, "remoteId" => 4]
                ]
            );

        $this->adapters["RemoteAdapter"]->shouldReceive("find")
            ->with(
                "remote_resource",
                [],
                [["id", "IN", [3, 4], "AND"]]
            )
            ->once()
            ->andReturn([["id" => 3], ["id" => 4]]);

        $query = new Query\Find(new Reflection\Entity("UniMapper\Tests\Fixtures\Entity\Simple"), $this->adapters, "id");
        $result = $query->associate("manyToMany")->execute();

        Assert::count(2, $result);

        Assert::same(1, $result[0]->id);
        Assert::count(1, $result[0]->manyToMany);
        Assert::same(3, $result[0]->manyToMany[0]->id);

        Assert::same(2, $result[1]->id);
        Assert::count(1, $result[1]->manyToMany);
        Assert::same(4, $result[1]->manyToMany[0]->id);
    }

    public function testAssociateManyToManyRemoteNoDominance()
    {
        $this->adapters["RemoteAdapter"]->shouldReceive("find")
            ->with("remote_resource", ["id"], [], [], null, null, [])
            ->once()
            ->andReturn([["id" => 3], ["id" => 4]]);

        $this->adapters["FooAdapter"]->shouldReceive("find")
            ->with(
                "simple_remote",
                ['remoteId', 'simpleId'],
                [["remoteId", "IN", [3, 4], "AND"]]
            )
            ->once()
            ->andReturn(
                [
                    ["simpleId" => 1, "remoteId" => 3],
                    ["simpleId" => 2, "remoteId" => 4]
                ]
            );

        $this->adapters["FooAdapter"]->shouldReceive("find")
            ->with(
                "simple_resource",
                [],
                [["id", "IN", [1, 2], "AND"]]
            )
            ->once()
            ->andReturn([["id" => 1], ["id" => 2]]);

        $query = new Query\Find(new Reflection\Entity("UniMapper\Tests\Fixtures\Entity\Remote"), $this->adapters, "id");
        $result = $query->associate("manyToManyNoDominance")->execute();

        Assert::count(2, $result);

        Assert::same(3, $result[0]->id);
        Assert::count(1, $result[0]->manyToManyNoDominance);
        Assert::same(1, $result[0]->manyToManyNoDominance[0]->id);

        Assert::same(4, $result[1]->id);
        Assert::count(1, $result[1]->manyToManyNoDominance);
        Assert::same(2, $result[1]->manyToManyNoDominance[0]->id);
    }

}

$testCase = new QueryFindTest;
$testCase->run();