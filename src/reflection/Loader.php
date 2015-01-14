<?php

namespace UniMapper\Reflection;

use UniMapper\Exception;
use UniMapper\NamingConvention as UNC;

class Loader
{

    /** @var array */
    private static $reflections = [];

    public static function register(Entity $reflection)
    {
        if (isset(self::$reflections[$reflection->getClassName()])) {
            throw new Exception\InvalidArgumentException(
                "Reflection of " . $reflection->getClassName() . " already registered!"
            );
        }
        self::$reflections[$reflection->getClassName()] = $reflection;
    }

    public static function get($class)
    {
        if (isset(self::$reflections[$class])) {
            return self::$reflections[$class];
        }
        return false;
    }

    /**
     * Load entity reflection
     *
     * @param mixed $entity Entity object, class or name
     *
     * @throws Exception\InvalidArgumentException
     */
    public static function load($entity)
    {
        if (is_object($entity)) {
            $class = get_class($entity);
        } elseif (is_string($entity)) {
            $class = $entity;
        } else {
            throw new Exception\InvalidArgumentException(
                "Entity identifier must be object, class or name!",
                $entity
            );
        }

        if (!is_subclass_of($class, "UniMapper\Entity")) {
            $class = UNC::nameToClass($entity, UNC::ENTITY_MASK);
        }

        if (!class_exists($class)) {
            throw new Exception\InvalidArgumentException(
                "Entity class " . $class . " not found!"
            );
        }

        if (isset(self::$reflections[$class])) {
            return self::$reflections[$class];
        }

        return new Entity($class);
    }

}