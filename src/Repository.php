<?php

namespace UniMapper;

use UniMapper\Exceptions\RepositoryException,
    UniMapper\NamingConvention as NC,
    UniMapper\Cache\ICache,
    UniMapper\Reflection;

/**
 * Repository is ancestor for every new repository. It contains common
 * parameters or methods used in its descendants. Repository is intended as a
 * mediator between your application and current mappers.
 */
abstract class Repository
{

    /** @var array $mappers Registered mappers */
    protected $mappers = array();

    private $logger;

    private $cache;

    public function getEntityName()
    {
        return NC::classToName(get_called_class(), NC::$repositoryMask);
    }

    public function setCache(ICache $cache)
    {
        $this->cache = $cache;
    }

    public function setLogger(\UniMapper\Logger $logger)
    {
        $this->logger = $logger;
    }

    public function registerMapper(\UniMapper\Mapper $mapper)
    {
        $this->mappers[$mapper->getName()] = $mapper;
    }

    protected function createQuery($entityClass = "")
    {
        if (empty($entityClass)) {
            $entityClass = NC::classToName($this->getEntityName(), NC::$entityMask);
        }
        if (!$entityClass) {
            throw new RepositoryException("Query must be called on some entity class in repository " .  get_class($this) . "!");
        }
        if (!is_subclass_of($entityClass, "UniMapper\Entity")) {
            throw new RepositoryException("Can not set class '" . $entityClass . "' as default entity in repository " .  get_class($this) . "!");
        }
        if (count($this->mappers) === 0) {
            throw new RepositoryException("You must set one mapper at least!");
        }

        if ($this->cache) {

            $key = "entity-" . $entityClass;
            $reflection = $this->cache->load($key);
            if (!$reflection) {
                $reflection = new Reflection\Entity($entityClass);
                $this->cache->save($key, $reflection, $reflection->getFileName());
            }
        } else {
            $reflection = new Reflection\Entity($entityClass);
        }

        return new QueryBuilder($reflection, $this->mappers, $this->logger);
    }

    public function getLogger()
    {
        return $this->logger;
    }

}