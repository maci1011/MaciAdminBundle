<?php

namespace Maci\AdminBundle\DependencyInjection;

class EntitiesChain
{
    private $entities;

    public function __construct()
    {
        $this->entities = array();
    }

    public function addEntity($entity)
    {
        $this->entities[] = $entity;
    }
}
