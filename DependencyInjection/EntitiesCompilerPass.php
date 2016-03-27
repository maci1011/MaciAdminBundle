<?php

namespace Maci\AdminBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class EntitiesCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {

        if (!$container->has('maci_admin.entities_chain')) {
            return;
        }

        $definition = $container->findDefinition(
            'maci_admin.entities_chain'
        );

        $taggedServices = $container->findTaggedServiceIds(
            'maci_admin.entities'
        );

        foreach ($taggedServices as $id => $tags) {
            $definition->addMethodCall(
                'addEntities',
                array(new Reference($id))
            );
        }

    }
}
