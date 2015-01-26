<?php

namespace UniMapper;

/**
 * Entity collection as ArrayList
 */
class EntityCollection implements \ArrayAccess, \Countable, \IteratorAggregate,
    \JsonSerializable
{

    /** @var Reflection\Entity $entityReflection */
    private $entityReflection;

    /** @var array $data Data container */
    private $data = [];

    /** @var array */
    private $changes = [
        Entity::CHANGE_ATTACH => [],
        Entity::CHANGE_DETACH => [],
        Entity::CHANGE_ADD => [],
        Entity::CHANGE_REMOVE => []
    ];

    /**
     * @param string $name   Entity name
     * @param mixed  $values
     */
    public function __construct($name, $values = null)
    {
        $this->entityReflection = Reflection\Loader::load($name);

        if ($values) {

            if (Validator::isTraversable($values)) {

                foreach ($values as $index => $value) {

                    if ($value instanceof Entity) {
                        $this->offsetSet($index, $value);
                    } else {
                        $this->data[$index] = $this->entityReflection->createEntity($value);
                    }
                }
            } else {
                throw new Exception\InvalidArgumentException(
                    "Values must be traversable data!",
                    $values
                );
            }
        }
    }

    private function _validateEntity($entity, $primaryRequired = false)
    {
        $entityClass = $this->entityReflection->getClassName();
        if (!$entity instanceof $entityClass) {
            throw new Exception\InvalidArgumentException(
                "Expected instance of entity " . $entityClass . "!",
                $entity
            );
        }

        if ($primaryRequired) {

            $primaryName = $entity->getReflection()->getPrimaryProperty()->getName();
            if (empty($entity->{$primaryName})) {
                throw new Exception\InvalidArgumentException(
                    "Primary value can not be empty!"
                );
            }
        }
    }

    public function attach(Entity $entity)
    {
        $this->_validateEntity($entity, true);

        $primary = $entity->{$entity->getReflection()->getPrimaryProperty()->getName()};
        if (!in_array($primary, $this->changes[Entity::CHANGE_ATTACH], true)) {
            array_push($this->changes{Entity::CHANGE_ATTACH}, $primary);
        }
    }

    public function detach(Entity $entity)
    {
        $this->_validateEntity($entity, true);

        $primary = $entity->{$entity->getReflection()->getPrimaryProperty()->getName()};
        if (!in_array($primary, $this->changes[Entity::CHANGE_DETACH], true)) {
            array_push($this->changes{Entity::CHANGE_DETACH}, $primary);
        }
    }

    public function add(Entity $entity)
    {
        $this->_validateEntity($entity);
        $this->changes[Entity::CHANGE_ADD][] = $entity;
    }

    public function remove(Entity $entity)
    {
        $this->_validateEntity($entity, true);

        $primary = $entity->{$entity->getReflection()->getPrimaryProperty()->getName()};
        if (!in_array($primary, $this->changes[Entity::CHANGE_REMOVE], true)) {
            array_push($this->changes{Entity::CHANGE_REMOVE}, $primary);
        }
    }

    public function getChanges()
    {
        return $this->changes;
    }

    /**
     * Get changed data only
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get entity reflection
     *
     * @return \UniMapper\Reflection\Entity
     */
    public function getEntityReflection()
    {
        return $this->entityReflection;
    }

    /**
     * Returns an iterator over all items.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->data);
    }

    /**
     * Returns items count.
     *
     * @return integer
     */
    public function count()
    {
        return count($this->data);
    }

    /**
     * Replaces or appends a item.
     *
     * @param integer           $offset Index
     * @param \UniMapper\Entity $value  Value
     */
    public function offsetSet($offset, $value)
    {
        $this->_validateEntity($value);

        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    /**
     * Returns an item.
     *
     * @param integer $key Key
     *
     * @return \UniMapper\Entity|null
     */
    public function offsetGet($key)
    {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }
        return null;
    }

    /**
     * Determines whether a item exists.
     *
     * @param integer $key Key
     *
     * @return boolean
     */
    public function offsetExists($key)
    {
        return isset($this->data[$key]) || array_key_exists($key, $this->data);
    }

    /**
     * Removes the element at the specified position in this data.
     *
     * @param integer $offset Offset
     */
    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    /**
     * Convert collection to array
     *
     * @param boolean $nesting Convert nested entities and collections too
     *
     * @return array
     */
    public function toArray($nesting = false)
    {
        $output = [];
        foreach ($this->data as $index => $entity) {
            $output[$index] = $entity->toArray($nesting);
        }
        return $output;
    }

    /**
     * Convert to json representation of entity collection
     *
     * @return array
     */
    public function jsonSerialize()
    {
        $output = [];
        foreach ($this->data as $index => $entity) {
            $output[$index] = $entity->jsonSerialize();
        }
        return $output;
    }

    /**
     * Get entity by primary value
     *
     * @param mixed $value
     *
     * @return \UniMapper\Entity|false
     */
    public function getByPrimary($value)
    {
        foreach ($this->data as $entity) {

            $primaryPropertyName = $entity->getReflection()->getPrimaryProperty()
                ->getName();
            $primaryValue = $entity->{$primaryPropertyName};
            if ($primaryValue === $value && $primaryValue !== null) {
                return $entity;
            }
        }
        return false;
    }

}