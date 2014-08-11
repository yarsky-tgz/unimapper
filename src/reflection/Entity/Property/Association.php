<?php

namespace UniMapper\Reflection\Entity\Property;

use UniMapper\Reflection,
    UniMapper\Exception;

abstract class Association
{

    /** @var \UniMapper\Reflection\Entity */
    protected $currentEntityReflection;

    /** @var \UniMapper\Reflection\Entity */
    protected $targetEntityReflection;

    /** @var array $parameters Additional association informations */
    protected $parameters = [];

    public function __construct(Reflection\Entity $currentReflection,
        Reflection\Entity $targetReflection, $parameters
    ) {
        if (!$currentReflection->hasAdapter()) {
            throw new Exception\PropertyParseException(
                "Can not use associations while current entity "
                . $currentReflection->getClassName()
                . " has no adapter defined!"
            );
        }
        $this->currentEntityReflection = $currentReflection;

        $this->targetEntityReflection = $targetReflection;
        if (!$targetReflection->hasAdapter()) {
            throw new Exception\PropertyParseException(
                "Can not use associations while target entity "
                . $targetReflection->getClassName()
                . " has no adapter defined!"
            );
        }

        $this->parameters = explode("|", $parameters);
    }

    public function getPrimaryKey()
    {
        return $this->currentEntityReflection->getPrimaryProperty()->getMappedName();
    }

    public function getTargetReflection()
    {
        return $this->targetEntityReflection;
    }

    public function getTargetResource()
    {
        return $this->targetEntityReflection->getAdapterReflection()->getResource();
    }

    public function getTargetAdapterName()
    {
        return $this->targetEntityReflection->getAdapterReflection()->getName();
    }

    public function isRemote()
    {
        return $this->currentEntityReflection->getAdapterReflection()->getName()
            !== $this->targetEntityReflection->getAdapterReflection()->getName();
    }

}