<?php

namespace Maci\AdminBundle\Menu;

use Knp\Menu\FactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\SecurityContext;
use Doctrine\Common\Persistence\ObjectManager;

class AdminMenuBuilder
{
	private $factory;

	private $securityContext;

	private $user;

    private $om;

    private $entities;

	public function __construct(FactoryInterface $factory, SecurityContext $securityContext, ObjectManager $om, $entities)
	{
	    $this->factory = $factory;
	    $this->securityContext = $securityContext;
	    $this->user = $securityContext->getToken()->getUser();
        $this->om = $om;
        $this->entities = $entities;
	}

    public function createLeftMenu(Request $request)
	{
		$menu = $this->factory->createItem('root');

		$menu->setChildrenAttribute('class', 'nav');

		$routes = array();

		$menu->addChild('Homepage', array('route' => 'maci_homepage'));

		$menu->addChild('Dashboard', array('route' => 'maci_admin'));

		foreach ($this->entities as $name => $entity) {
			if (array_key_exists('label', $entity)) {
				$label = $entity['label'];
			} else {
				$label = str_replace('_', ' ', $name);
        		$label = ucwords($label);
			}
			$menu->addChild($label, array(
			    'route' => 'maci_admin_entity',
			    'routeParameters' => array('entity' => $name)
			));
		}

		return $menu;
	}
}
