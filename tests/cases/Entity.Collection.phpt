<?php

use Tester\Assert;
use UniMapper\Tests\Fixtures;
use UniMapper\Entity;

require __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
class EntityCollectionTest extends \Tester\TestCase
{

    public function testCreateCollection()
    {
        $entity = new Fixtures\Entity\Simple(["text" => "test"]);

        $collection = new Entity\Collection("Simple");

        $collection[] = $entity;
        Assert::same("test", $collection[0]->text);

        $entity->text = "foo";
        $collection[] = $entity;

        foreach ($collection as $entity) {
            Assert::type(get_class($entity), $entity);
            Assert::same("foo", $entity->text);
        }
    }

    /**
     * @throws UniMapper\Exception\InvalidArgumentException Values must be traversable data!
     */
    public function testValuesNotTraversable()
    {
        new Entity\Collection("Simple", "foo");
    }

    /**
     * @throws UniMapper\Exception\InvalidArgumentException Expected instance of entity UniMapper\Tests\Fixtures\Entity\Simple!
     */
    public function testInvalidEntity()
    {
        new Entity\Collection("Simple", [new Fixtures\Entity\Remote]);
    }

    public function testAdd()
    {
        $entity = new Fixtures\Entity\Simple(["id" => 1]);

        $collection = new Entity\Collection("Simple");
        $collection->add($entity);

        Assert::same([$entity], $collection->getChanges()[Entity::CHANGE_ADD]);
    }

    public function testAttach()
    {
        $entity = new Fixtures\Entity\Simple(["id" => 1]);

        $collection = new Entity\Collection("Simple");
        $collection->attach($entity);

        Assert::same([1], $collection->getChanges()[Entity::CHANGE_ATTACH]);
    }

    public function testDetach()
    {
        $entity = new Fixtures\Entity\Simple(["id" => 1]);

        $collection = new Entity\Collection("Simple");
        $collection->detach($entity);

        Assert::same([1], $collection->getChanges()[Entity::CHANGE_DETACH]);
    }

    public function testRemove()
    {
        $entity = new Fixtures\Entity\Simple(["id" => 1]);

        $collection = new Entity\Collection("Simple");
        $collection->remove($entity);

        Assert::same([1], $collection->getChanges()[Entity::CHANGE_REMOVE]);
    }

    public function testJsonSerialize()
    {
        $collection = new Entity\Collection("Simple");
        Assert::same("[]", json_encode($collection));

        $collection[] = new Fixtures\Entity\Simple(["id" => 1]);
        Assert::same(
            '[{"id":1,"text":null,"empty":null,"url":null,"email":null,"time":null,"date":null,"year":null,"ip":null,"mark":null,"entity":null,"collection":[],"oneToMany":[],"oneToManyRemote":[],"manyToMany":[],"mmFilter":[],"manyToOne":null,"oneToOne":null,"ooFilter":null,"readonly":null,"storedData":null,"enumeration":null,"publicProperty":"defaultValue"}]',
            json_encode($collection)
        );
    }

}

$testCase = new EntityCollectionTest;
$testCase->run();