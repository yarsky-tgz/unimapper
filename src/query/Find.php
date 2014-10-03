<?php

namespace UniMapper\Query;

use UniMapper\Exception,
    UniMapper\Reflection,
    UniMapper\Reflection\Entity\Property\Association\OneToMany,
    UniMapper\Reflection\Entity\Property\Association\OneToOne,
    UniMapper\Reflection\Entity\Property\Association\ManyToOne,
    UniMapper\Reflection\Entity\Property\Association\ManyToMany,
    UniMapper\NamingConvention as UNC,
    UniMapper\Cache\ICache;

class Find extends Selectable
{

    protected $limit;
    protected $offset;
    protected $orderBy = [];
    protected $selection = [];
    protected $cached = false;
    protected $cachedOptions = [];

    public function __construct(
        Reflection\Entity $entityReflection,
        array $adapters
    ) {
        parent::__construct($entityReflection, $adapters);

        $selection = array_slice(func_get_args(), 2);
        array_walk($selection, [$this, "select"]);
    }

    public function select($name)
    {
        if (!$this->entityReflection->hasProperty($name)) {
            throw new Exception\QueryException(
                "Property " . $name . " is not defined on entity "
                . $this->entityReflection->getClassName() . "!"
            );
        }

        $property = $this->entityReflection->getProperty($name);
        if ($property->isAssociation() || $property->isComputed()) {
            throw new Exception\QueryException(
                "Associations and computed properties can not be selected!"
            );
        }

        if (!array_search($name, $this->selection)) {
            $this->selection[] = $name;
        }

        return $this;
    }

    public function limit($limit)
    {
        $this->limit = (int) $limit;
        return $this;
    }

    public function offset($offset)
    {
        $this->offset = (int) $offset;
        return $this;
    }

    public function cached($enable = true, array $options = [])
    {
        $this->cached = (bool) $enable;
        $this->cachedOptions = $options;
        return $this;
    }

    public function orderBy($propertyName, $direction = "asc")
    {
        if (!$this->entityReflection->hasProperty($propertyName)) {
            throw new Exception\QueryException(
                "Invalid property name '" . $propertyName . "'!"
            );
        }

        $direction = strtolower($direction);
        if ($direction !== "asc" && $direction !== "desc") {
            throw new Exception\QueryException("Order direction must be 'asc' or 'desc'!");
        }
        $this->orderBy[$propertyName] = $direction;
        return $this;
    }

    protected function onExecute(\UniMapper\Adapter $adapter)
    {
        $mapping = $adapter->getMapping();

        if ($this->cached) {

            if (!$this->cache) {
                throw new Exception\QueryException("Cache not set!");
            }

            $cachedResult = $this->cache->load($this->_getQueryChecksum());
            if ($cachedResult) {
                return $mapping->mapCollection($this->entityReflection, $cachedResult);
            }
        }

        $result = $adapter->find(
            $this->entityReflection->getAdapterReflection()->getResource(),
            $mapping->unmapSelection($this->_createSelection(), $this->entityReflection),
            $mapping->unmapConditions($this->conditions, $this->entityReflection),
            $mapping->unmapOrderBy($this->orderBy, $this->entityReflection),
            $this->limit,
            $this->offset,
            $this->associations["local"]
        );

        // Get remote associations
        if ($this->associations["remote"] && !empty($result)) {

            settype($result, "array");

            $primaryPropertyName = $this->entityReflection->getPrimaryProperty()
                ->getMappedName();

            foreach ($this->associations["remote"] as $colName => $association) {

                $refValues = [];
                foreach ($result as $item) {

                    if (is_array($item)) {
                        $refValues[] = $item[$primaryPropertyName];
                    } else {
                        $refValues[] = $item->{$primaryPropertyName};
                    }
                }

                $refKey = $association->getPrimaryKey();

                if (!isset($this->adapters[$association->getTargetAdapterName()])) {
                    throw new Exception\QueryException(
                        "Adapter with name '"
                        . $association->getTargetAdapterName() . "' not set!"
                    );
                }

                if ($association instanceof ManyToMany) {

                    $associated = $this->manyToMany(
                        $adapter,
                        $this->adapters[$association->getTargetAdapterName()],
                        $association,
                        $refValues
                    );
                } elseif ($association instanceof OneToOne) {

                    $refKey = $association->getForeignKey();

                    $refValues = [];
                    foreach ($result as $item) {

                        if (is_array($item)) {
                            $refValues[] = $item[$refKey];
                        } else {
                            $refValues[] = $item->{$refKey};
                        }
                    }

                    $associated = $this->oneToOne(
                        $this->adapters[$association->getTargetAdapterName()],
                        $association,
                        $refValues
                    );
                } elseif ($association instanceof OneToMany) {

                    $associated = $this->oneToMany(
                        $this->adapters[$association->getTargetAdapterName()],
                        $association,
                        $refValues
                    );
                } elseif ($association instanceof ManyToOne) {

                    $refKey = $association->getReferenceKey();

                    $refValues = [];
                    foreach ($result as $item) {

                        if (is_array($item)) {
                            $refValues[] = $item[$refKey];
                        } else {
                            $refValues[] = $item->{$refKey};
                        }
                    }
                    $associated = $this->manyToOne(
                        $this->adapters[$association->getTargetAdapterName()],
                        $association,
                        $refValues
                    );
                } else {

                    throw new Exception\QueryException(
                        "Unsupported remote association "
                        . get_class($association) . "!"
                    );
                }

                // Merge returned associations
                if (!empty($associated)) {

                    $result = $this->_mergeAssociated(
                        $result,
                        $associated,
                        $refKey,
                        $colName
                    );
                }
            }
        }

        if ($this->cached) {

            $cachedOptions = $this->cachedOptions;
            if (isset($cachedOptions[ICache::TAGS])) {
                $cachedOptions[ICache::TAGS][] = ICache::TAG_QUERY; // @todo is it really array?
            } else {
                $cachedOptions[ICache::TAGS] = [ICache::TAG_QUERY];
            }
            $this->cache->save(
                $this->_getQueryChecksum(),
                $result,
                $cachedOptions
            );
        }

        return $mapping->mapCollection(
            $this->entityReflection,
            empty($result) ? [] : $result
        );
    }

    protected function addCondition($propertyName, $operator, $value,
        $joiner = 'AND'
    ) {
        parent::addCondition($propertyName, $operator, $value, $joiner);

        // Add properties from conditions
        if (count($this->selection) > 0
            && !in_array($propertyName, $this->selection)
        ) {
            $this->selection[] = $propertyName;
        }
    }

    protected function addNestedConditions(\Closure $callback, $joiner = 'AND')
    {
        $query = parent::addNestedConditions($callback, $joiner);

        // Add properties from conditions
        $this->selection = array_unique(
            array_merge($this->selection, $query->selection)
        );
    }

    private function _createSelection()
    {
        if (empty($this->selection)) {

            $selection = [];
            foreach ($this->entityReflection->getProperties() as $property) {

                if (!$property->isAssociation() && !$property->isComputed()) {
                    $selection[] = $property->getName();
                }
            }
        } else {
            $primaryPropertyName = $this->entityReflection
                ->getPrimaryProperty()
                ->getName();

            // Add primary property automatically
            $selection = $this->selection;
            if (!in_array($primaryPropertyName, $selection)) {
                $selection[] = $primaryPropertyName;
            }
        }

        // Add required keys from remote associations
        foreach ($this->associations["remote"] as $association) {

            $refKey = $association->getReferenceKey();
            if ($association instanceof ManyToOne
                && !in_array($refKey, $selection, true)
            ) {
                $selection[] = $refKey;
            }
        }

        return $selection;
    }

    /**
     * Merge associated data with result
     *
     * @param array  $result
     * @param array  $associated
     * @param string $refKey
     * @param string $colName
     *
     * @return array
     */
    private function _mergeAssociated(
        array $result,
        array $associated,
        $refKey,
        $colName
    ) {
        foreach ($result as $index => $item) {

            if (is_array($item)) {
                $refValue = $item[$refKey];
            } else {
                $refValue = $item->{$refKey};
            }

            if (isset($associated[$refValue])) {

                if (is_array($result[$index])) {
                    $result[$index][$colName] = $associated[$refValue];
                } else {
                    $result[$index]->{$colName} = $associated[$refValue];
                }
            }
        }
        return $result;
    }

    /**
     * Get a unique query checksum
     *
     * @return integer
     */
    private function _getQueryChecksum()
    {
        return crc32(
            serialize(
                [
                    "name" => $this->getName(),
                    "entity" => UNC::classToName(
                        $this->entityReflection->getClassName(), UNC::$entityMask
                    ),
                    "limit" => $this->limit,
                    "offset" => $this->offset,
                    "selection" => $this->selection,
                    "orderBy" => $this->orderBy,
                    "localAssociations" => array_keys($this->associations["local"]),
                    "remoteAssociations" => array_keys($this->associations["remote"]),
                    "conditions" => $this->conditions
                ]
            )
        );
    }

}