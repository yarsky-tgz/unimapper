<?php

namespace UniMapper\Reflection\Association;

use UniMapper\Reflection,
    UniMapper\Exception;

class ManyToMany extends Reflection\Association
{

    public function __construct(
        Reflection\Property $propertyReflection,
        Reflection\Entity $targetReflection,
        array $arguments,
        $dominant = true
    ) {
        if (!isset($arguments[0])) {
            throw new Exception\DefinitionException(
                "You must define join key!"
            );
        }

        if (!isset($arguments[1])) {
            throw new Exception\DefinitionException(
                "You must define join resource!"
            );
        }

        if (!isset($arguments[2])) {
            throw new Exception\DefinitionException(
                "You must define reference key!!"
            );
        }

        parent::__construct($propertyReflection, $targetReflection, $arguments, $dominant);
    }

    public function getJoinKey()
    {
        return $this->arguments[0];
    }

    public function getJoinResource()
    {
        return$this->arguments[1];
    }

    public function getReferenceKey()
    {
        return $this->arguments[2];
    }

    public function getForeignKey()
    {
        return $this->targetReflection->getPrimaryProperty()->getName(true);
    }

    public function isDominant()
    {
        return $this->dominant;
    }

}