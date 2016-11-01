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

    private $mcm;

	public function __construct(FactoryInterface $factory, SecurityContext $securityContext, ObjectManager $om, $mcm)
	{
	    $this->factory = $factory;
	    $this->securityContext = $securityContext;
	    $this->user = $securityContext->getToken()->getUser();
        $this->om = $om;
        $this->mcm = $mcm;
	}

    public function createSectionsMenu(Request $request)
	{
		$menu = $this->factory->createItem('root');

		$menu->setChildrenAttribute('class', 'nav navbar-nav');

		foreach ($this->mcm->getSections() as $name) {
			$config = $this->mcm->getConfig($name);
			if (array_key_exists('label', $config)) {
				$label = $config['label'];
			} else {
				$label = str_replace('_', ' ', $name);
        		$label = ucwords($label);
			}
			if ($name != $request->get('section')) {
				$menu->addChild($label, array(
				    'route' => 'maci_admin_view',
				    'routeParameters' => array('section' => $name)
				));
			}
		}

		$menu->addChild('Dashboard', array('route' => 'maci_admin'));
		$menu->addChild('Homepage', array('route' => 'homepage'));

		return $menu;
	}

    public function createEntitiesMenu(Request $request)
	{
		$menu = $this->factory->createItem('root');

		$menu->setChildrenAttribute('class', 'nav');

		$sections = $this->mcm->getSections();

		$section = $request->get('section');

		if ( $section && in_array($section, $sections) ) {

			foreach ($this->mcm->getEntities($section) as $name => $entity) {
				if (array_key_exists('label', $entity)) {
					$label = $entity['label'];
				} else {
					$label = str_replace('_', ' ', $name);
	        		$label = ucwords($label);
				}
				$menu->addChild($label, array(
				    'route' => 'maci_admin_view',
				    'routeParameters' => array('section' => $section, 'entity' => $name, 'action' => 'list')
				));
			}

		}

		return $menu;
	}

    public function createActionsMenu(Request $request)
	{
		$menu = $this->factory->createItem('root');

		$menu->setChildrenAttribute('class', 'nav navbar-nav');

		$sections = $this->mcm->getSections();

		$section = $request->get('section');

		$entity = $request->get('entity');

		if ( $section && in_array($section, $sections) ) {

			foreach ($this->mcm->getMainActions($section, $entity) as $action) {
				$label = $this->mcm->generateLabel($action);
				$menu->addChild($label, array(
				    'route' => 'maci_admin_view',
				    'routeParameters' => array('section' => $section, 'entity' => $entity, 'action' => $action)
				));
			}

		}

		return $menu;
	}

    public function createItemActionsMenu(Request $request)
	{
		$menu = $this->factory->createItem('root');

		$menu->setChildrenAttribute('class', 'nav navbar-nav');

		$sections = $this->mcm->getSections();

		$section = $request->get('section');

		$entity = $request->get('entity');

        $entity = $this->mcm->getEntity($section, $entity);

		$associations = $this->mcm->getEntityAssociations($entity);

		$action = $request->get('action');

		$id = $request->get('id');

		$single_actions = $this->mcm->getSingleActions($section, $entity);

		if ( $section && in_array($section, $sections) ) {

			foreach ($single_actions as $action) {
				if ($action == 'relations') {
					if ( count($associations) ) {
						foreach ($associations as $relation) {
							if ($relation === 'translations') {
								continue;
							}
							$label = $this->mcm->generateLabel($relation);
							$relAction = $this->mcm->getRelationDefaultAction($entity, $relation);
							$menu->addChild($label, array(
							    'route' => 'maci_admin_view_relations',
							    'routeParameters' => array('section' => $section, 'entity' => $entity['name'], 'action' => $action, 'id' => $id, 'relation' => $relation, 'relAction' => $relAction)
							));
						}
					}
				} else {
					$label = $this->mcm->generateLabel($action);
					$menu->addChild($label, array(
					    'route' => 'maci_admin_view',
					    'routeParameters' => array('section' => $section, 'entity' => $entity['name'], 'action' => $action, 'id' => $id)
					));
				}
			}

		}

		return $menu;
	}
}
