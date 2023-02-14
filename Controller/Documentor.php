<?php

declare(strict_types=1);

namespace DragonBlack\Bundle\JsonRpcDocBundle\Controller;

use DragonBlack\Bundle\JsonRpcDocBundle\Service\StructureBuilder;

class Documentor
{
    private \Traversable $resolvers;
    
    public function __construct(\Traversable $resolvers)
    {
        $this->resolvers = $resolvers;
    }

    public function __invoke()
    {
        $builder = new StructureBuilder();
        foreach($this->resolvers as $resolver) {
            $builder->add($resolver);
            break;
        }
        d($builder->getAll());
    }
}
