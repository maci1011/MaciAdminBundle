<?php

namespace Maci\AdminBundle\Tests\Configuration;

use Maci\AdminBundle\DependencyInjection\MaciAdminExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class Generic  extends WebTestCase
{

    /**
     * @param  array $config
     * @return \Symfony\Component\DependencyInjection\ContainerBuilder
     */
    protected function getBuilder(array $config = array())
    {
        $builder = new ContainerBuilder();
        $loader = new MaciAdminExtension();
        $loader->load($config, $builder);
        return $builder;
    }

    public function testSimpleConfig()
    {
    	$simpleConfigBuilder = $this->getBuilder(array(
    		''
    	));

    }
}