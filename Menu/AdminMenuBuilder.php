<?php

namespace Maci\AdminBundle\Menu;

use Knp\Menu\FactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AdminMenuBuilder
{
	private $factory;

    private $request;

    private $mcm;

	public function __construct(FactoryInterface $factory, RequestStack $requestStack, $mcm)
	{
	    $this->factory = $factory;
	    $this->request = $requestStack->getCurrentRequest();
        $this->mcm = $mcm;
	}

    public function createSectionsMenu(array $options)
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
			if ($name != $this->request->get('section')) {
				$menu->addChild($label, array(
				    'route' => 'maci_admin_view',
				    'routeParameters' => array('section' => $name)
				));
			}
		}
		$menu->addChild('Homepage', array('route' => 'homepage'));

		return $menu;
	}

    public function createEntitiesMenu(array $options)
	{
		$menu = $this->factory->createItem('root');

		$menu->setChildrenAttribute('class', 'nav');

		$sections = $this->mcm->getSections();

		$section = $this->request->get('section');

		if ( $section && in_array($section, $sections) ) {

			$menu->addChild('Dashboard', array(
				'route' => 'maci_admin_dashboard',
				'routeParameters' => array('section' => $section)
			));

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

    public function createActionsMenu(array $options)
	{
		$menu = $this->factory->createItem('root');

		$menu->setChildrenAttribute('class', 'nav navbar-nav');

		$sections = $this->mcm->getSections();

		$section = $this->request->get('section');

		$entity = $this->request->get('entity');

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

    public function createItemActionsMenu(array $options)
	{
		$menu = $this->factory->createItem('root');

		$menu->setChildrenAttribute('class', 'nav navbar-nav');

		$sections = $this->mcm->getSections();

		$section = $this->request->get('section');

		$entity = $this->request->get('entity');

        $entity = $this->mcm->getEntity($section, $entity);

		$associations = $this->mcm->getEntityAssociations($entity);

		$action = $this->request->get('action');

		$id = $this->request->get('id');

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
