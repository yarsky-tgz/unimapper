<?php

namespace UniMapper;

class Mapper
{

    /** @var array */
    private $adapterMappings = [];

    public function registerAdapterMapping($name, Adapter\Mapping $mapping)
    {
        if (isset($this->adapterMappings[$name])) {
            throw new Exception\InvalidArgumentException(
                "Mapping on adapater " . $name . " already registered!"
            );
        }

        $this->adapterMappings[$name] = $mapping;
    }

    /**
     * Convert value to defined property format
     *
     * @param \UniMapper\Reflection\Property $property
     * @param mixed                                 $value
     *
     * @return mixed
     *
     * @throws Exception\MappingException
     */
    public function mapValue(Reflection\Property $property, $value)
    {
        // Call adapter's mapping if needed
        if (!$property->getEntityReflection()->hasAdapter()) {
            throw new Exception\MappingException(
                "Entity " . $property->getEntityReflection()->getClassName()
                . " should have adapter defined if you want to use mapping!"
            );
        }
        if (isset($this->adapterMappings[$property->getEntityReflection()->getAdapterName()])) {
            $value = $this->adapterMappings[$property->getEntityReflection()->getAdapterName()]
                ->mapValue($property, $value);
        }

        // Call map filter from property option
        if ($property->hasOption(Reflection\Property::OPTION_MAP_FILTER)) {
            $value = call_user_func($property->getOption(Reflection\Property::OPTION_MAP_FILTER)[0], $value);
        }

        $type = $property->getType();

        if ($value === null || $value === "") {
            return null;
        }

        if ($property->isTypeBasic()) {
            // Basic type

            if ($type === "boolean" && $value === "false") {
                return false;
            }

            if ($type === "boolean" && $value === "true") {
                return true;
            }

            if (settype($value, $type)) {
                return $value;
            }

        } elseif ($type instanceof EntityCollection) {
            // Collection

            return $this->mapCollection($type->getEntityReflection(), $value);
        } elseif ($type instanceof Reflection\Entity) {
            // Entity

            return $this->mapEntity($type, $value);
        } elseif ($type === Reflection\Property::TYPE_DATETIME) {
            // DateTime

            if ($value instanceof \DateTime) {
                return $value;
            } elseif (is_array($value) && isset($value["date"])) {
                $date = $value["date"];
            } elseif (is_object($value) && isset($value->date)) {
                $date = $value->date;
            } else {
                $date = $value;
            }

            if (isset($date)) {

                try {
                    return new \DateTime($date);
                } catch (\Exception $e) {
                    throw new Exception\MappingException(
                        "Can not map value to DateTime automatically! "
                        . $e->getMessage()
                    );
                }
            }
        }

        // Unexpected value type
        throw new Exception\MappingException(
            "Unexpected value type given. Can not convert value to entity "
            . "@property $" . $property->getName() . ". Expected " . $type
            . " but " . gettype($value) . " given!"
        );
    }

    public function mapCollection(Reflection\Entity $entityReflection, $data)
    {
        if (!Validator::isTraversable($data)) {
            throw new Exception\InvalidArgumentException(
                "Input data must be traversable!"
            );
        }

        $collection = new EntityCollection($entityReflection);
        foreach ($data as $value) {
            $collection[] = $this->mapEntity($entityReflection, $value);
        }
        return $collection;
    }

    public function mapEntity(Reflection\Entity $entityReflection, $data)
    {
        if (!Validator::isTraversable($data)) {
            throw new Exception\MappingException("Input data must be traversable!");
        }

        $values = [];
        foreach ($data as $index => $value) {

            $propertyName = $index;

            // Map property name if needed
            foreach ($entityReflection->getProperties() as $propertyReflection) {

                if ($propertyReflection->getName(true) === $index) {

                    $propertyName = $propertyReflection->getName();
                    break;
                }
            }

            // Skip undefined properties
            if (!$entityReflection->hasProperty($propertyName)) {
                continue;
            }

            // Map value
            $values[$propertyName] = $this->mapValue(
                $entityReflection->getProperty($propertyName),
                $value
            );
        }

        return $entityReflection->createEntity($values);
    }

    /**
     * Convert entity to simple array
     *
     *  @param Entity $entity
     *
     *  @return array
     */
    public function unmapEntity(Entity $entity)
    {
        $output = [];
        foreach ($entity->getData() as $propertyName => $value) {

            $property = $entity->getReflection()->getProperties()[$propertyName];

            // Skip associations
            if ($property->hasOption(Reflection\Property::OPTION_ASSOC)) {
                continue;
            }

            $output[$property->getName(true)] = $this->unmapValue(
                $property,
                $value
            );
        }
        return $output;
    }

    public function unmapValue(Reflection\Property $property, $value)
    {
        // Call map filter from property option
        if ($property->hasOption(Reflection\Property::OPTION_MAP_FILTER)) {
            $value = call_user_func($property->getOption(Reflection\Property::OPTION_MAP_FILTER)[1], $value);
        }

        if ($value instanceof EntityCollection) {
            return $this->unmapCollection($value);
        } elseif ($value instanceof Entity) {
            return $this->unmapEntity($value);
        }

        // Call adapter's mapping if needed
        if (!$property->getEntityReflection()->hasAdapter()) {
            throw new Exception\MappingException(
                "Entity " . $property->getEntityReflection()->getClassName()
                . " should have adapter defined if you want to use mapping!"
            );
        }

        if (isset($this->adapterMappings[$property->getEntityReflection()->getAdapterName()])) {
            return $this->adapterMappings[$property->getEntityReflection()->getAdapterName()]
                ->unmapValue($property, $value);
        }

        return $value;
    }

    /**
     * Convert entity to simple array
     *
     *  @param EntityCollection $collection
     *
     *  @return array
     */
    public function unmapCollection(EntityCollection $collection)
    {
        $data = [];
        foreach ($collection as $index => $entity) {
            $data[$index] = $this->unmapEntity($entity);
        }
        return $data;
    }

}