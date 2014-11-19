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

	public function __construct(FactoryInterface $factory, SecurityContext $securityContext, ObjectManager $om)
	{
	    $this->factory = $factory;
	    $this->securityContext = $securityContext;
	    $this->user = $securityContext->getToken()->getUser();
        $this->om = $om;
	}

    public function createLeftMenu(Request $request)
	{
		$menu = $this->factory->createItem('root');

		$routes = array();

		$menu->addChild('Dashboard', array('route' => 'maci_admin'));

		$menu->addChild('Album', array(
		    'route' => 'maci_admin_entity',
		    'routeParameters' => array('entity' => 'album')
		));

		$menu->addChild('Media', array(
		    'route' => 'maci_admin_entity',
		    'routeParameters' => array('entity' => 'media')
		));

		$menu->addChild('Media Tag', array(
		    'route' => 'maci_admin_entity',
		    'routeParameters' => array('entity' => 'media_tag')
		));

		$menu->addChild('Blog', array(
		    'route' => 'maci_admin_entity',
		    'routeParameters' => array('entity' => 'blog_post')
		));

		$menu->addChild('Blog Tag', array(
		    'route' => 'maci_admin_entity',
		    'routeParameters' => array('entity' => 'blog_tag')
		));

		$menu->addChild('Categories', array(
		    'route' => 'maci_admin_entity',
		    'routeParameters' => array('entity' => 'category')
		));

		$menu->addChild('Products', array(
		    'route' => 'maci_admin_entity',
		    'routeParameters' => array('entity' => 'product')
		));

		$menu->addChild('Variants', array(
		    'route' => 'maci_admin_entity',
		    'routeParameters' => array('entity' => 'product_variant')
		));

		$menu->addChild('Translations', array(
		    'route' => 'maci_admin_entity',
		    'routeParameters' => array('entity' => 'language')
		));

		$menu->addChild('Page', array(
		    'route' => 'maci_admin_entity',
		    'routeParameters' => array('entity' => 'page')
		));

		$menu->addChild('Panel', array(
		    'route' => 'maci_admin_entity',
		    'routeParameters' => array('entity' => 'panel')
		));

		$menu->addChild('Panel Item', array(
		    'route' => 'maci_admin_entity',
		    'routeParameters' => array('entity' => 'panel_item')
		));

		return $menu;
	}
}
