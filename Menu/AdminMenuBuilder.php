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

		foreach ($this->mcm->getAuthSections() as $name) {
			$config = $this->mcm->getSectionConfig($name);
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
		$menu['Homepage']->setLinkAttributes(array('target' => '_blank'));

		return $menu;
	}

    public function createEntitiesMenu(array $options)
	{
		$menu = $this->factory->createItem('root');

		$menu->setChildrenAttribute('class', 'nav');

		$sections = $this->mcm->getAuthSections();

		$section = $this->request->get('section');

		if ( $section && in_array($section, $sections) ) {

			if ($this->mcm->hasSectionDashboard($section)) {
				$menu->addChild('Dashboard', array(
					'route' => 'maci_admin_dashboard',
					'routeParameters' => array('section' => $section)
				));
			}

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

        $section = $this->mcm->getCurrentSection();

        $entity = $this->mcm->getCurrentEntity();

		if ( $entity ) {

			foreach ($this->mcm->getMainActions($entity) as $action) {
				$label = $this->mcm->generateLabel($action);
				$menu->addChild($label, array(
				    'route' => 'maci_admin_view',
				    'routeParameters' => array('section' => $section, 'entity' => $entity['name'], 'action' => $action)
				));
			}

		}

		return $menu;
	}

    public function createItemActionsMenu(array $options)
	{
		$menu = $this->factory->createItem('root');

		$menu->setChildrenAttribute('class', 'nav navbar-nav');

        $section = $this->mcm->getCurrentSection();

        $entity = $this->mcm->getCurrentEntity();

		$associations = $this->mcm->getAssociations($entity);

		$action = $this->request->get('action');

		$id = $this->request->get('id');

		$single_actions = $this->mcm->getSingleActions($section, $entity);

		foreach ($single_actions as $action) {
			if ($action === 'relations') {
				if ( count($associations) ) {
					foreach ($associations as $relation) {
						if ($relation === 'translations') {
							continue;
						}
						$label = $this->mcm->generateLabel($relation);
						$relAction = $this->mcm->getRelationDefaultAction($entity, $relation);
						$menu->addChild($label, array(
						    'route' => 'maci_admin_view',
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

		return $menu;
	}

    public function createItemRelationActionsMenu(array $options)
	{
		$menu = $this->factory->createItem('root');

		$menu->setChildrenAttribute('class', 'nav navbar-nav');

        $section = $this->mcm->getCurrentSection();
        if (!$section) return false;

        $entity = $this->mcm->getCurrentEntity();
        if (!$entity) return false;

        $relation = $this->mcm->getCurrentRelation();
        if (!$relation) return false;

		$id = $this->request->get('id');

		$relation_default_action = $this->mcm->getRelationDefaultAction($entity, $relation['association']);

		$menu->addChild($this->mcm->generateLabel($relation_default_action), array(
		    'route' => 'maci_admin_view',
		    'routeParameters' => array('section' => $section, 'entity' => $entity['name'], 'action' => 'relations', 'id' => $id, 'relation' => $relation['association'], 'relAction' => $relation_default_action)
		));

		$relation_default_method = $this->mcm->getRelationSetterAction($entity, $relation['association']);

		$menu->addChild($this->mcm->generateLabel($relation_default_method . ' ' . $relation['association']), array(
		    'route' => 'maci_admin_view',
		    'routeParameters' => array('section' => $section, 'entity' => $entity['name'], 'action' => 'relations', 'id' => $id, 'relation' => $relation['association'], 'relAction' => $relation_default_method)
		));

		if ($this->mcm->isUploadable($relation)) {
			$menu->addChild('Upload', array(
			    'route' => 'maci_admin_view',
			    'routeParameters' => array('section' => $section, 'entity' => $entity['name'], 'action' => 'relations', 'id' => $id, 'relation' => $relation['association'], 'relAction' => 'uploader')
			));
		}

		foreach ($this->mcm->getBridges($relation) as $bridge) {

			$menu->addChild($this->mcm->generateLabel($relation_default_method . ' ' . $bridge), array(
			    'route' => 'maci_admin_view',
			    'routeParameters' => array('section' => $section, 'entity' => $entity['name'], 'action' => 'relations', 'id' => $id, 'relation' => $relation['association'], 'relAction' => 'bridge', 'bridge' => $bridge)
			));

			if (in_array($bridge, $this->mcm->getUpladableBridges($relation))) {
				$menu->addChild($this->mcm->generateLabel('Upload ' . $bridge), array(
				    'route' => 'maci_admin_view',
				    'routeParameters' => array('section' => $section, 'entity' => $entity['name'], 'action' => 'relations', 'id' => $id, 'relation' => $relation['association'], 'relAction' => 'uploader', 'bridge' => $bridge)
				));
			}

		}

		return $menu;
	}
}
