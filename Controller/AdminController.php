<?php

namespace Maci\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Bundle\TwigBundle\TwigEngine;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\LocaleType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Intl\Locales;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

use Maci\AdminBundle\MaciPager;
use Maci\AdminBundle\Controller\DefaultController;

class AdminController
{
	private $_auth_sections;

	private $_sections;

	private $_class_list;

	private $_defaults;

	private $config;

	private $om;

	private $authorizationChecker;

	private $user;

	private $last;

	private $session;

	private $request;

	private $kernel;

	private $formFactory;

	private $formRegistry;

	private $templating;

	private $router;

	public function __construct(
		ObjectManager $objectManager,
		AuthorizationCheckerInterface $authorizationChecker,
		Session $session,
		RequestStack $requestStack,
		\App\Kernel $kernel,
		FormFactory $formFactory,
		FormRegistry $formRegistry,
		Router $router,
		TwigEngine $templating,
		Array $config
	) {
		$this->om = $objectManager;
		$this->authorizationChecker = $authorizationChecker;
		$this->session = $session;
		$this->request = $requestStack->getCurrentRequest();
		$this->kernel = $kernel;
		$this->formFactory = $formFactory;
		$this->formRegistry = $formRegistry;
		$this->templating = $templating;
		$this->router = $router;
		$this->config = $config;

		$controllerMap = false;
		$controllerAction = false;

		// Init Default Config
		$this->_defaults = [
			'actions' => [
				'list' => [
					'template' => 'MaciAdminBundle:Actions:list.html.twig',
					'types' => ['main']
				],
				'new' => [
					'template' => 'MaciAdminBundle:Actions:new.html.twig',
					'types' => ['main']
				],
				'trash' => [
					'template' => 'MaciAdminBundle:Actions:trash.html.twig',
					'types' => ['main']
				],
				'uploader' => [
					'template' => 'MaciAdminBundle:Actions:uploader.html.twig',
					'types' => ['main']
				],
				'show' => [
					'template' => 'MaciAdminBundle:Actions:show.html.twig',
					'types' => ['single']
				],
				'edit' => [
					'template' => 'MaciAdminBundle:Actions:edit.html.twig',
					'types' => ['single']
				],
				'remove' => [
					'template' => 'MaciAdminBundle:Actions:remove.html.twig',
					'types' => ['single']
				],
				'relations' => [
					'template' => 'MaciAdminBundle:Actions:relations.html.twig',
					'types' => ['single']
				],

		// return ['list', 'show', 'new', 'add', 'set', 'bridge', 'uploader', 'remove', 'reorder'];


				'relations_list' => [
					'template' => 'MaciAdminBundle:Actions:relations_list.html.twig',
					'types' => ['relations']
				],
				'relations_show' => [
					'template' => 'MaciAdminBundle:Actions:relations_show.html.twig',
					'types' => ['relations']
				],
				'relations_new' => [
					'template' => 'MaciAdminBundle:Actions:relations_new.html.twig',
					'types' => ['relations']
				],
				'relations_add' => [
					'template' => 'MaciAdminBundle:Actions:relations_add.html.twig',
					'types' => ['relations']
				],
				'relations_set' => [
					'template' => 'MaciAdminBundle:Actions:relations_set.html.twig',
					'types' => ['relations']
				],
				'relations_remove' => [
					'template' => 'MaciAdminBundle:Actions:relations_remove.html.twig',
					'types' => ['relations']
				],
				'relations_uploader' => [
					'template' => 'MaciAdminBundle:Actions:relations_uploader.html.twig',
					'types' => ['relations']
				]


			],
			'roles' => [
				'ROLE_ADMIN'
			],
			'controller' 	=> DefaultController::class,
			'enabled' 		=> true,
			'page_limit' 	=> 100,
			'page_range' 	=> 5,
			'sortable' 		=> false,
			'sort_field' 	=> 'position',
			'trash' 		=> true,
			'trash_field' 	=> 'removed',
			'uploadable' 	=> false,
			'upload_field' 	=> 'file',
			'upload_path_field' => 'path'
		];

		$this->initConfig();
	}

	/*
		------------> Config Functions
	*/

	// Init _sections and _auth_sections
	private function initConfig()
	{
		if ($this->authorizationChecker->isGranted('ROLE_ANONYMOUS')) {
			$this->_auth_sections = [];
			$this->_sections = [];
			return;
		}

		$this->_auth_sections = $this->session->get('maci_admin._auth_sections');
		$this->_sections = $this->session->get('maci_admin._sections');

		// Inited from Session. End.
		// if (is_array($this->_auth_sections)) return;

		// Init Authorized Sections
		$this->_auth_sections = [];
		$this->_sections = [];

		if (array_key_exists('config', $this->config)) $this->_defaults = $this->mergeConfig($this->config);

		// It Needs Sections Config
		if (!array_key_exists('sections', $this->config)) return;

		foreach ($this->config['sections'] as $name => $section)
		{
			// Section Auth
			$s_config = array_key_exists('config', $section) ? $this->mergeConfig($section['config']) : $this->_defaults;
			$section['authorized'] = false;
			foreach ($s_config['roles'] as $role)
			{
				if ($this->authorizationChecker->isGranted($role))
				{
					$section['authorized'] = true;
					break;
				}
			}
			// Section Properties
			$section['name'] = $name;
			if (!array_key_exists('label', $section)) {
				$section['label'] = $this->generateLabel($name);
			}
			if (!array_key_exists('dashboard', $section)) {
				$section['dashboard'] = false;
			}
			if (!$section['authorized']) {
				$section['pages'] = false;
			}
			if (!array_key_exists('pages', $section)) {
				$section['pages'] = false;
			}
			if (!array_key_exists('config', $section)) {
				$section['config'] = false;
			}
			// Section Entities
			if (array_key_exists('entities', $section) && count($section['entities'])) {
				$entities = [];
				foreach ($section['entities'] as $entity_name => $entity)
				{
					$e_config = array_key_exists('config', $section) ? $this->mergeConfig($section['config'], $s_config) : $s_config;
					$entity['authorized'] = false;
					foreach ($e_config['roles'] as $role) {
						if ($this->authorizationChecker->isGranted($role))
						{
							$section['authorized'] = true;
							$entity['authorized'] = true;
							break;
						}
					}
					if (!$entity['authorized']) continue;
					//  entity definitions
					$entity['section'] = $name;
					$entity['name'] = $entity_name;
					$entity['class'] = $this->getClass($entity);
					if (!array_key_exists('label', $entity)) $entity['label'] = $this->generateLabel($entity_name);
					if (!array_key_exists('config', $entity)) $entity['config'] = false;
					// Add Entity
					$entities[$entity_name] = $entity;
				}
				$section['entities'] = $entities;
			} else {
				$section['entities'] = false;
			}
			// Add Section
			$this->_auth_sections[] = $name;
			$this->_sections[$name] = $section;
		}

		$this->session->set('maci_admin._auth_sections', $this->_auth_sections);
		$this->session->set('maci_admin._sections', $this->_sections);
	}

	public function getDefaultConfigArray()
	{
		return $this->_defaults;
	}

	public function getEntitiesClassList()
	{
		if (!is_array($this->_class_list)) {
			$this->_class_list = $this->session->get('maci_admin._class_list');
			if (is_array($this->_class_list)) return $this->_class_list;
			$list = $this->om->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
			foreach ($list as $key => $value) {
				$reflcl = new \ReflectionClass($value);
				if ($reflcl->isAbstract()) {
					unset($list[$key]);
				}
			}
			$this->_class_list = array_values($list);
			$this->session->set('maci_admin._class_list', $this->_class_list);
		}
		return $this->_class_list;
	}

	public function mergeConfig($config, $defaults = false)
	{
		if (!is_array($defaults)) $defaults = $this->_defaults;
		if (!is_array($config) || count($config) == 0) return $defaults;
		if (array_key_exists('config', $config)) $config = $config['config'];
		if (array_key_exists('config', $defaults)) $defaults = $defaults['config'];
		$roles = array_key_exists('roles', $config) && count($config['roles']) ? $config['roles'] : array_key_exists('roles', $defaults) ? $defaults['roles'] : ['ROLE_ADMIN'];
		$actions = array_key_exists('actions', $config) && count($config['actions']) ? $config['actions'] : array_key_exists('actions', $defaults) ? $defaults['actions'] : $this->defaults['actions'];
		if (array_key_exists('actions', $config)) {
			foreach ($config['actions'] as $action => $cnf) {
				if (!array_key_exists($action, $actions)) {
					$actions[$action] = $cnf;
					continue;
				}
				if (array_key_exists('enabled', $cnf)) {
					$actions[$action]['enabled'] = $cnf['enabled'];
				}
				if (array_key_exists('controller', $cnf) && class_exists($cnf['controller'])) {
					$actions[$action]['controller'] = $cnf['controller'];
				}
				if (array_key_exists('template', $cnf) && $this->templating->exists($cnf['template'])) {
					$actions[$action]['template'] = $cnf['template'];
				}
			}
		}
		$config = array_merge($defaults, $config);
		$config['roles'] = $roles;
		$config['actions'] = $actions;
		return $config;
	}

	public function mergeViews($map, $defaults = false)
	{
		$view = is_array($defaults) ? $defaults : $this->getDefaultMap();

		if (!is_array($map)) return $view;

		if (array_key_exists('name', $map)) $view['name'] = $map['name'];
		if (array_key_exists('section', $map)) $view['section'] = $map['section'];
		if (array_key_exists('entity', $map)) $view['entity'] = $map['entity'];
		if (array_key_exists('class', $map)) $view['class'] = $map['class'];
		if (array_key_exists('form', $map)) $view['form'] = $map['form'];
		if (array_key_exists('label', $map)) $view['label'] = $map['label'];
		if (array_key_exists('list', $map) && count($map['list'])) $view['list'] = $map['list'];
		if (array_key_exists('bridges', $map) && count($map['bridges'])) $view['bridges'] = $map['bridges'];
		if (array_key_exists('config', $map)) $view['config'] = $this->mergeConfig($map['config'], array_key_exists('config', $view) ? $view['config'] : []);

		return $view;
	}

	public function isConfigLoaded(&$map)
	{
		return (array_key_exists('config', $map) && is_array($map['config']) && array_key_exists('_loaded', $map['config']));
	}

	public function loadConfig(&$map)
	{
		if (!is_array($map)) return [];
		if ($this->isConfigLoaded($map)) return;
		$map['config'] = $this->getConfig($map);
		$map['config']['_loaded'] = true;
	}

	public function getConfig($map)
	{
		if (!is_array($map)) return [];
		if ($this->isConfigLoaded($map)) return $map['config'];
		$config = $this->_defaults;
		if (array_key_exists('section', $map) && is_string($map['section'])) $config = $this->mergeConfig($this->getSectionConfig($map['section']), $config);
		if (array_key_exists('entity_root', $map) && is_string($map['entity_root'])) $config = $this->mergeConfig($this->getConfig($this->getEntity($map['entity_root'], $map['entity_root_section'])), $config);
		if (array_key_exists('config', $map) && is_array($map['config'])) $config = $this->mergeConfig($map['config'], $config);
		return $config;
	}

	public function getConfigKey($map, $key)
	{
		return $this->getConfig($map)[$key];
	}

	public function getController($map, $action)
	{
		if ($map != null &&
			array_key_exists($action, $map['config']['actions']) &&
			array_key_exists('controller', $map['config']['actions'][$action]) &&
			class_exists($map['config']['actions'][$action]['controller']) ) {
			return $map['config']['actions'][$action]['controller'];
		}
		return class_exists($map['config']['controller']) ? $map['config']['controller'] : $this->_defaults['controller'];
	}

	public function getTemplate($map, $action)
	{
		if (array_key_exists($action, $map['config']['actions']) &&
			array_key_exists('template', $map['config']['actions'][$action]) &&
			$this->templating->exists($map['config']['actions'][$action]['template'])
		) {
			return $map['config']['actions'][$action]['template'];
		}
		$bundleName = $this->getBundleName($map);
		if($bundleName) {
			$template = $bundleName . ':Mcm' . $this->getCamel($map['name']) . ':_' . $action . '.html.twig';
			if ( $this->templating->exists($template) ) {
				return $template;
			}
			$template = $bundleName . ':Mcm:_' . $action . '.html.twig';
			if ( $this->templating->exists($template) ) {
				return $template;
			}
		}
		if (array_key_exists($action, $this->_defaults['actions']) &&
			$this->templating->exists($this->_defaults['actions'][$action]['template'])
		) {
			return $this->_defaults['actions'][$action]['template'];
		}
		return 'MaciAdminBundle:Actions:template_not_found.html.twig';
	}

	// --- Check User Auths
	public function checkAuth()
	{
		if (!$this->authorizationChecker->isGranted('ROLE_USER') || !count($this->_auth_sections) || !count($this->_sections)) {
			return false;
		}
		return true;
	}

	// --- Check User Auths for the current Route
	public function checkRoute()
	{
		if (!$this->checkAuth()) {
			return $this->generateUrl('homepage');
		}
		$section = $this->getCurrentSection();
		if (!$section) {
			return $this->generateUrl('maci_admin_view', array('section'=>$this->_auth_sections[0]));
		}
		if (!in_array($section, $this->_auth_sections)) {
			$this->request->getSession()->getFlashBag()->add('error', 'Section [' . $section . '] not Found.');
			return $this->generateUrl('maci_admin_view', array('section'=>$this->_auth_sections[0]));
		}
		$entity = $this->request->get('entity');
		if (!$entity || !$this->hasEntity($section, $entity)) {
			if ($this->hasDashboard($section)) {
				return $this->render($this->getSectionDashboard($section));
			}
			$entities = array_keys($this->getEntities($section));
			return $this->generateUrl('maci_admin_view', array('section'=>$section,'entity'=>$entities[0],'action'=>'list'));
		}
		$_entity = $this->getCurrentEntity();
		$entity = $_entity['name'];
		$action = $this->getCurrentAction();
		if (!$action) {
			return $this->generateUrl('maci_admin_view', array('section'=>$section,'entity'=>$entity,'action'=>'list'));
		}
		if ($action === 'relations') {
			if (!$this->getCurrentItem()) {
				return $this->generateUrl('maci_admin_view', array('section'=>$section,'entity'=>$entity,'action'=>'list'));
			}
			$relation = $this->request->get('relation');
			if (!$relation) {
				$relations = $this->getAssociations($_entity);
				$relAction = $this->getRelationDefaultAction($_entity, $relation[0]);
				return $this->generateUrl('maci_admin_view', array('section'=>$section,'entity'=>$entity,'action'=>$action,'id'=>$this->request->get('id'),'relation'=>$relations[0],'relAction'=>$relAction));
			}
			$relAction = $this->request->get('relAction');
			$_relation = $this->getCurrentRelation();
			if (!$relAction || !in_array($relAction, $this->getRelationActions($_relation))) {
				$_entity = $this->getEntity($section, $entity);
				$relAction = $this->getRelationDefaultAction($_entity, $relation);
				return $this->generateUrl('maci_admin_view', array('section'=>$section,'entity'=>$entity,'action'=>$action,'id'=>$this->request->get('id'),'relation'=>$relation,'relAction'=>$relAction));
			}
		}
		return true;
	}

	public function getControllerMap()
	{
		if ($this->getCurrentAction() === 'relations') {
			return $this->getCurrentRelation();
		}
		return $this->getCurrentEntity();
	}

	public function getControllerAction()
	{
		$action = $this->getCurrentAction();
		if ($action === 'relations') {
			return $this->getCurrentRelationAction();
		}
		return $action;
	}

	/*
		------------> Section Maps Functions
	*/

	public function getAuthSections()
	{
		return $this->_auth_sections;
	}

	public function isAuthSection($section)
	{
		return $this->sectionExists($section) && in_array($section, $this->getAuthSections());
	}

	public function getSection($section)
	{
		if ($this->isAuthSection($section)) {
			return $this->_sections[$section];
		}
		return false;
	}

	public function sectionExists($section)
	{
		return array_key_exists($section, $this->_sections);
	}

	public function getSectionConfig($section)
	{
		return array_key_exists($section, $this->_sections) && $this->_sections[$section]['config'] ? $this->mergeConfig($section['config']) : $this->_defaults;
	}

	public function getSectionLabel($section)
	{
		if (array_key_exists($section, $this->_sections)) {
			return $this->_sections[$section]['label'];
		}
		return false;
	}

	public function hasDashboard($section)
	{
		if (array_key_exists($section, $this->_sections) && array_key_exists('dashboard', $this->_sections[$section])) {
			return $this->templating->exists($this->_sections[$section]['dashboard']);
		}
		return false;
	}

	public function getDashboard($section)
	{
		if ($this->hasDashboard($section)) {
			return $this->_sections[$section]['dashboard'];
		}
		return false;
	}

	public function getEntities($section)
	{
		if (!$this->isAuthSection($section)) {
			return false;
		}
		if (!$this->hasEntities($section)) {
			return false;
		}
		return $this->_sections[$section]['entities'];
	}

	public function hasEntities($section)
	{
		return (array_key_exists($section, $this->_sections) && count($this->_sections[$section]['entities']));
	}

	public function getEntity($section, $entity)
	{
		if (!$this->hasEntity($section, $entity)) {
			return false;
		}
		return $this->_sections[$section]['entities'][$entity];
	}

	public function hasEntity($section, $entity)
	{
		if ($this->hasEntities($section)) {
			return array_key_exists($entity, $this->_sections[$section]['entities']);
		}
		return false;
	}

	public function getEntityByClass($className, $pref_section = false)
	{
		$sections = $this->getAuthSections();
		if ($pref_section && in_array($pref_section, $sections)) {
			$sections = array_merge([$pref_section], $sections);
		}
		foreach ($sections as $section) {
			$entities = $this->getEntities($section);
			foreach ($entities as $name => $map) {
				if ($className === $this->getClass($map)) {
					return $map;
				}
			}
		}
		return false;
	}

	public function getPages($section)
	{
		if ($this->hasPages($section)) {
			return $this->_sections[$section]['pages'];
		}
		return [];
	}

	public function hasPages($section)
	{
		return array_key_exists('pages', $this->_sections[$section]);
	}

	/*
		------------> Defaults Map, Params and Urls
	*/

	static public function getDefaultMap()
	{
		return [
			'authorized' => false,
			'name' => false,
			'section' => false,
			'entity' => false,
			'class' => false,
			'form' => false,
			'label' => false,
			'list' => false,
			'bridges' => false,
			// 'filters' => false,
			'config' => false
		];
	}

	public function getDefaultParams($section = false)
	{
		if (!$section) {
			$section = $this->getCurrentSection();
		}
		return array(
			'section' => $section,
			'section_label' => $this->getSectionLabel($section),
			'section_has_dashboard' => $this->hasDashboard($section)
		);
	}

	public function getDefaultEntityParams($map)
	{
		$action = $this->request->get('action');
		return array_merge($this->getDefaultParams(),array(
			'entity' => $map['name'],
			'entity_label' => $map['label'],
			'action' =>  $action,
			'action_label' => $this->generateLabel($action),
			'fields' => $this->getFields($map),
			'list_fields' => $this->getListFields($map),
			'hasTrash' => $this->hasTrash($map),
			'form_filters' => $this->generateFiltersForm($map, $action)->createView(),
			'has_filters' => $this->hasFilters($map, $action),
			'filters' => $this->getFilters($map, $action),
			'filters_list' => $this->getGeneratedFilters($map, $action),
			'id' => $this->request->get('id'),
			'item' => $this->getCurrentItem(),
			'item_identifier' => $this->getIdentifier($map),
			'is_entity_uploadable' => $this->isUploadable($map),
			'form_search' => true,
			'search_query' => $this->getStoredSearchQuery($map, $action),
			'list_page' => $this->getStoredPage($map, $action),
			'sortable' => ($this->isSortable($map) ? $this->generateUrl('maci_admin_view', array(
				'section'=>$map['section'],'entity'=>$map['name'],
				'action'=>'reorder'
			)) : false),
			'template' => $this->getTemplate($map,$action),
			'uploader' => ($this->isUploadable($map) ? $this->generateUrl('maci_admin_view', array(
				'section'=>$map['section'],'entity'=>$map['name'],'action'=>'uploader'
			)) : false)
		));
	}

	public function getDefaultRelationParams($map, $relation)
	{
		$relAction = $this->request->get('relAction');
		return array_merge($this->getDefaultEntityParams($map),array(
			'fields' => $this->getFields($relation),
			'list_fields' => $this->getListFields($relation),
			'form_filters' => false, // $this->generateFiltersForm($relation, $relAction)->createView(),
			'has_filters' => false, // $this->hasFilters($relation, $relAction),
			'filters' => false, // $this->getFilters($relation, $relAction),
			'filters_list' => false, // $this->getGeneratedFilters($relation, $relAction),
			'form_search' => false,
			'search_query' => '', // $this->getStoredSearchQuery($map, $relAction, $relation),
			'relation' => $relation['association'],
			'association_label' => $this->generateLabel($relation['association']),
			'relation_label' => $relation['label'],
			'relation_section' => $relation['section'],
			'relation_entity' => $relation['name'],
			'relation_entity_root' => $relation['entity_root'],
			'relation_entity_root_section' => $relation['entity_root_section'],
			'relation_action' => $relAction,
			'relation_action_label' => $this->generateLabel($relAction),
			'bridges' => $this->getBridges($relation),
			'uploadable_bridges' => $this->getUpladableBridges($relation),
			'is_relation_uploadable' => $this->isUploadable($relation),
			'list_page' => $this->getStoredPage($map, $relAction, $relation),
			'sortable' => ($this->isSortable($relation) ? $this->generateUrl('maci_admin_view', array(
				'section'=>$map['section'],'entity'=>$map['name'],
				'action'=>'relations','id'=>$this->request->get('id'),
				'relation'=>$relation['association'],'relAction'=>'reorder'
			)) : false),
			'template' => $this->getTemplate($map,('relations_'.$relAction)),
			'uploader' => ($this->isUploadable($relation) ? $this->generateUrl('maci_admin_view', array(
				'section'=>$map['section'],'entity'=>$map['name'],
				'action'=>'relations','id'=>$this->request->get('id'),
				'relation'=>$relation['association'],'relAction'=>'uploader'
			)) : false)
		));
	}

	public function getDefaultBridgeParams($map, $relation, $bridge)
	{
		$relAction = $this->request->get('relAction');
		if ($relAction === 'bridge')
			$relAction = ($this->getRelationDefaultAction($map, $relation['association']) === 'show' ? 'set' : 'add');
		return array_merge($this->getDefaultRelationParams($map, $relation), [
			'fields' => $this->getFields($bridge),
			'list_fields' => $this->getListFields($bridge),
			'form_filters' => $this->generateFiltersForm($bridge, $relAction)->createView(),
			'has_filters' => $this->hasFilters($bridge, $relAction),
			'filters' => $this->getFilters($bridge, $relAction),
			'filters_list' => $this->getGeneratedFilters($bridge, $relAction),
			'form_search' => true,
			'search_query' => $this->getStoredSearchQuery($bridge, $relAction),
			'list_page' => $this->getStoredPage($relation, $relAction),
			'relation_action_label' => ($this->generateLabel($relAction) . ' ' . $bridge['label']),
			'relation_action' => $relAction,
			'template' => $this->getTemplate($bridge,('relations_'.$relAction)),
			'uploader' => ($this->isUploadable($bridge) ? $this->generateUrl('maci_admin_view', [
				'section'=>$map['section'],'entity'=>$map['name'],
				'action'=>'relations','id'=>$this->request->get('id'),
				'relation'=>$relation['association'],'relAction'=>'uploader',
				'bridge'=>$bridge['bridge']
			]) : false)
		]);
	}

	public function getDefaultRedirectParams($opt = [])
	{
		return array(
			'redirect' => 'maci_admin_view',
			'redirect_params' => $opt
		);
	}

	public function getDefaultSectionRedirectParams($section, $opt = [])
	{
		$params = $this->getDefaultRedirectParams();
		if (!$this->isAuthSection($section)) return $params = $this->getDefaultRedirectParams();
		$params['redirect_params'] = array_merge($params['redirect_params'], [
			'section' => $section
		], $opt);
		return $params;
	}

	public function getDefaultEntityRedirectParams($map, $action = false, $id = null, $opt = [])
	{
		if (!$action) $action = $this->getMainActions($map)[0];
		if (in_array($action, $this->getSingleActions($map)) || ($this->hasTrash($map) && $action == 'trash')) {
			$id = $id === null ? $this->request->get('id', null) : $id;
		} else {
			$id = null;
		}
		$params = $this->getDefaultRedirectParams();
		$params['redirect_params'] = array_merge($params['redirect_params'], [
			'section' => $map['section'],
			'entity' => $map['name'],
			'action' => $action,
			'id' => $id,
			'p' => null,
			's' => null,
			'f' => null
		], $this->getMapUrlOptions($map, $action), $opt);
		return $params;
	}

	public function getDefaultRelationRedirectParams($map, $relation, $action = false, $id = null, $opt = [])
	{
		$id = $id === null ? $this->request->get('id', null) : $id;
		if ($id === null) {
			return $this->getDefaultEntityRedirectParams($map);
		}
		if (!$action) $action = $this->getRelationDefaultAction($map, $relation['association']);
		$params = $this->getDefaultEntityRedirectParams($map, 'relations', $id);
		$params['redirect_params'] = array_merge($params['redirect_params'], [
			'relation' => $relation['association'],
			'relAction' => $action,
			'p' => null,
			's' => null,
			'f' => null
		], $this->getMapUrlOptions($map, $action), $opt);
		return $params;
	}

	public function getDefaultBridgeRedirectParams($map, $relation, $bridge, $action = 'bridge', $id = null, $opt = [])
	{
		$id = $id === null ? $this->request->get('id', null) : $id;
		if ($id === null) {
			return $this->getDefaultEntityRedirectParams($map);
		}
		$params = $this->getDefaultRelationRedirectParams($map, $relation, $action, $id);
		$params['redirect_params'] = array_merge($params['redirect_params'], [
			'bridge' => $bridge['name'],
			'p' => null,
			's' => null,
			'f' => null
		], $this->getMapUrlOptions($map, $action), $opt);
		return $params;
	}

	public function getMapUrlOptions($map, $action)
	{
		$page = $this->getStoredPage($map, $action);
		$query = $this->getStoredSearchQuery($map, $action);
		return array_merge(
			(1 < $page ? [
				'p' => $page
			] : []),
			(strlen($query) ? [
				's' => $query
			] : []),
			($this->hasFilters($map, $action) ? [
				'f' => count($this->getFilters($map, $action))
			] : [])
		);
	}

	public function getCurrentRedirectParams($opt = [])
	{
		$section = $this->getCurrentSection();
		if (!$section) return $this->getDefaultRedirectParams($opt);
		$entity = $this->getCurrentEntity();
		if (!$entity) return $this->getDefaultSectionRedirectParams($section);
		$action = $this->getCurrentAction();
		$relation = $this->getCurrentRelation();
		if (!$relation) return $this->getDefaultEntityRedirectParams($entity, $action, null, $opt);
		$action = $this->getCurrentRelationAction();
		$bridge = $this->getCurrentBridge();
		if (!$bridge) return $this->getDefaultRelationRedirectParams($entity, $relation, $action, null, $opt);
		return $this->getDefaultBridgeRedirectParams($entity, $relation, $bridge, $action, null, $opt);
	}

	public function getUrl($params)
	{
		return $this->generateUrl($params['redirect'], $params['redirect_params']);
	}

	public function getDefaultUrl($opt = [])
	{;
		return $this->getUrl($this->getDefaultRedirectParams($opt));
	}

	public function getSectionUrl($section, $opt = [])
	{
		return $this->getUrl($this->getDefaultSectionRedirectParams($section, $opt));
	}

	public function getEntityUrl($map, $action = false, $id = null, $opt = [])
	{
		return $this->getUrl($this->getDefaultEntityRedirectParams($map, $action, $id, $opt));
	}

	public function getRelationUrl($map, $relation, $action = false, $id = null, $opt = [])
	{
		return $this->getUrl($this->getDefaultRelationRedirectParams($map, $relation, $action, $id, $opt));
	}

	public function getBridgeUrl($map, $relation, $bridge, $action = 'bridge', $id = null, $opt = [])
	{
		return $this->getUrl($this->getDefaultBridgeRedirectParams($map, $relation, $bridge, $action, $id, $opt));
	}

	public function getMapUrl($map, $action = false, $id = null, $opt = [])
	{
		if ($id === null) $id = $this->request->get('id', null);
		$params = $this->getDefaultRedirectParams([
			'section' => $map['section'],
			'entity' => array_key_exists('parent_entity', $map) ? $map['parent_entity'] : $map['name'],
			'action' => array_key_exists('parent_entity', $map) ? 'relations' :
				$action ? $action : $this->getMainActions($map)[0]
		]);
		$relation = array_key_exists('parent_relation', $map) ? $map['parent_relation'] :
			array_key_exists('parent_entity', $map) ? $map['name'] : false;
		$relAction = !array_key_exists('parent_entity', $map) ? false :
			array_key_exists('parent_relation', $map) ? 'bridge' : 'list';
		$bridge = array_key_exists('parent_relation', $map) ? $map['name'] : false;
		if ($relation)
		{
			$params['relation'] = $relation;
			$params['relAction'] = $relAction;
			if ($bridge) $params['bridge'] = $bridge;
		}
		return $this->getUrl($params);
	}

	public function getCurrentUrl($opt = [])
	{
		$section = $this->getCurrentSection();
		if (!$section) return $this->getDefaultUrl($opt);
		$entity = $this->getCurrentEntity();
		if (!$entity) return $this->getSectionUrl($section, $opt);
		$action = $this->getCurrentAction();
		$relation = $this->getCurrentRelation();
		if (!$relation) return $this->getEntityUrl($entity, $action, null, $opt);
		$action = $this->getCurrentRelationAction();
		$bridge = $this->getCurrentBridge();
		if (!$bridge) return $this->getRelationUrl($entity, $relation, $action, null, $opt);
		return $this->getBridgeUrl($entity, $relation, $bridge, $action, null, $opt);
	}

	/*
		------------> Current Route Getters
	*/

	public function getCurrentSection()
	{
		if (isset($this->current_section)) return $this->current_section;
		$section = $this->request->get('section');
		if (!$section) return false;
		if ($this->isAuthSection($section)) {
			$this->current_section = $section;
			return $section;
		}
		$this->current_section = false;
		$this->session->getFlashBag()->add('error', 'Section [' . $section . '] not found.');
		return false;
	}

	public function getCurrentEntity()
	{
		if (isset($this->current_entity)) return $this->current_entity;
		$section = $this->getCurrentSection();
		if (!$section) return false;
		$_entity = $this->request->get('entity');
		if (!$_entity) return false;
		$entity = $this->getEntity($section, $_entity);
		if ($entity) {
			$this->loadConfig($entity);
			$this->current_entity = $entity;
			return $entity;
		}
		$this->current_entity = false;
		$this->session->getFlashBag()->add('error', 'Entity [' . $_entity . '] in section [' . $section . '] not found.');
		return false;
	}

	public function getCurrentItem()
	{
		if (isset($this->current_item)) return $this->current_item;
		$entity = $this->getCurrentEntity();
		if (!$entity) return false;
		$id = $this->request->get('id');
		if (!$id) return false;
		$item = $this->getItem($entity, $id);
		if ($item) {
			$this->current_item = $item;
			return $item;
		}
		$this->current_item = false;
		$this->session->getFlashBag()->add('error', 'Item [' . $id . '] for [' . $entity['label'] . '] not found.');
		return false;
	}

	public function getCurrentAction()
	{
		if (isset($this->current_action)) return $this->current_action;
		$entity = $this->getCurrentEntity();
		if (!$entity) return false;
		$action = $this->request->get('action');
		if (!$action) return false;
		if ($this->hasAction($entity, $action)) {
			$this->current_action = $action;
			return $action;
		}
		$this->current_action = false;
		$this->session->getFlashBag()->add('error', 'Action [' . $action . '] for [' . $entity['label'] . '] not found.');
		return false;
	}

	public function getCurrentRelation()
	{
		if (isset($this->current_relation)) return $this->current_relation;
		$entity = $this->getCurrentEntity();
		if (!$entity) return false;
		$relation = $this->request->get('relation');
		if (!$relation) return false;
		$relation = $this->getRelation($entity, $relation);
		if ($relation) {
			$this->loadConfig($relation);
			$this->current_relation = $relation;
			return $relation;
		}
		$this->current_relation = false;
		$this->session->getFlashBag()->add('error', 'Relation [' . $relation['label'] . '] for [' . $entity['label'] . '] not found.');
		return false;
	}

	public function getCurrentRelatedItem()
	{
		if (isset($this->current_related_item)) return $this->current_related_item;
		$relation = $this->getCurrentRelation();
		if (!$relation) return false;
		$id = $this->request->get('relId');
		if (!$id) return false;
		$item = $this->getItem($relation, $id);
		if ($item) {
			$this->current_related_item = $item;
			return $item;
		}
		$this->current_related_item = false;
		$this->session->getFlashBag()->add('error', 'Item [' . $id . '] for [' . $relation['label'] . '] not found.');
		return false;
	}

	public function getCurrentRelationAction()
	{
		if (isset($this->current_relation_action)) return $this->current_relation_action;
		$relation = $this->getCurrentRelation();
		if (!$relation) return false;
		$action = $this->request->get('relAction');
		if (!$action) return false;
		if ($this->hasRelationAction($relation, $action)) {
			$this->current_relation_action = $action;
			return $action;
		}
		$this->current_relation_action = false;
		$this->session->getFlashBag()->add('error', 'Action [' . $action . '] for relation [' . $relation['label'] . '] not found.');
		return false;
	}

	public function getCurrentBridge()
	{
		if (isset($this->current_bridge)) return $this->current_bridge;
		$relation = $this->getCurrentRelation();
		if (!$relation) return false;
		$_bridge = $this->request->get('bridge');
		if (!$_bridge) return false;
		$bridge = $this->getBridge($relation, $_bridge);
		if ($bridge) {
			$this->current_bridge = $bridge;
			return $bridge;
		}
		$this->current_bridge = false;
		$this->session->getFlashBag()->add('error', 'Bridge [' . $this->request->get('bridge') . '] in [' . $relation['label'] . '] not found.');
		return false;
	}

	public function getCurrentMap()
	{
		$entity = $this->getCurrentEntity();
		if (!$entity) return false;
		$relation = $this->getCurrentRelation();
		if (!$relation) return $entity;
		$bridge = $this->getCurrentBridge();
		if (!$bridge) return $relation;
		return $bridge;
	}

	public function getCurrentMapAction()
	{
		return $this->getCurrentRelationAction() ? $this->getCurrentRelationAction() : $this->getCurrentAction();
	}

	/*
		------------> Actions Functions
	*/

	public function getMainActions($entity)
	{
		$actions = array('list');

		if ($this->hasTrash($entity)) {
			$actions[] = 'trash';
		}

		$actions[] = 'new';

		if ($this->isUploadable($entity)) {
			$actions[] = 'uploader';
		}

		return $actions;
	}

	public function getSingleActions($entity)
	{
		return ['show', 'edit', 'relations', 'remove'];
	}

	public function getMultipleActions($entity)
	{
		return ['reorder'];
	}

	public function getActions($entity)
	{
		return array_merge(
			$this->getMainActions($entity),
			$this->getSingleActions($entity),
			$this->getMultipleActions($entity)
		);
	}

	public function hasAction($entity, $action)
	{
		return in_array($action, $this->getActions($entity));
	}

	public function getRelationActions($entity)
	{
		return ['list', 'show', 'new', 'add', 'set', 'bridge', 'uploader', 'remove', 'reorder'];
	}

	public function hasRelationAction($entity, $action)
	{
		return in_array($action, $this->getRelationActions($entity));
	}

	/*
		------------> Maps Generic Functions
	*/

	public function isAuthorized($map)
	{
		return $map['authorized'];
	}

	public function getAssociations($map)
	{
		$metadata = $this->getMetadata($map);

		$associations = [];

		foreach ($metadata->associationMappings as $fieldName => $association) {
			// if ($association['type'] !== ClassMetadataInfo::ONE_TO_MANY) {
			//     $associations[] = $fieldName;
			// }
			if ($this->isRelationEnable($map,$fieldName)) {
				$associations[] = $fieldName;
			}
		}

		return $associations;
	}

	public function getAssociationMappings($map)
	{
		return $this->getMetadata($map)->associationMappings;
	}

	public function getAssociationMetadata($map, $association)
	{
		$am = $this->getAssociationMappings($map);
		if (!array_key_exists($association, $am)) return false;
		return $am[$association];
	}

	public function getBridge($relation, $association)
	{
		$bridge = $this->getRelation($relation, $association);
		if (!$bridge) return false;
		$bridge['bridge'] = $association;
		$bridge['parent_entity'] = $relation['parent_entity'];
		$bridge['parent_relation'] = $relation['name'];
		return $bridge;
	}

	public function getBridges($relation)
	{
		if (array_key_exists('bridges', $relation) && is_array($relation['bridges'])) {
			return $relation['bridges'];
		}
		return false;
	}

	public function getUpladableBridges($relation)
	{
		if (!array_key_exists('bridges', $relation) || !is_array($relation['bridges'])) {
			return [];
		}
		$upb = [];
		foreach ($relation['bridges'] as $bridge) {
			$bm = $this->getBridge($relation,$bridge);
			if ($bm && $this->isUploadable($bm)) {
				$upb[] = $bridge;
			}
		}
		return $upb;
	}

	public function getBundle($map)
	{
		$name = explode(':', $map['class']);
		if (1 < count($name)) {
			return $this->kernel->getBundle($name[0]);
		}
		$namespace = substr($map['class'], 0, strpos($map['class'], 'Bundle') + 6);
		$bundles = $this->kernel->getBundles();
		foreach ($bundles as $bundle) {
			if ($bundle->getNamespace() === $namespace) {
				return $bundle;
			}
		}
		return false;
	}

	public function getBundleName($map)
	{
		$bundle = $this->getBundle($map);
		return $bundle ? $bundle->getName() : false;
	}

	public function getBundleNamespace($map)
	{
		$bundle = $this->getBundle($map);
		return $bundle ? $bundle->getNamespace() : false;
	}

	public function getClass($map)
	{
		if (class_exists($map['class'])) return $map['class'];
		$repo = $this->getRepository($map);
		if ($repo) return $repo->getClassName();
		return false;
	}

	public function getFields($map, $removeId = true)
	{
		$metadata = $this->getMetadata($map);
		$fields = (array) $metadata->fieldNames;
		// Remove the primary key field if it's not managed manually
		if ($removeId && !$metadata->isIdentifierNatural()) {
			$fields = array_diff($fields, $metadata->identifier);
		}
		return array_keys($fields);
	}

	public function getFieldsWithType($map)
	{
		$metadata = $this->getMetadata($map);
		$fieldMappings = (array) $metadata->fieldMappings;
		$fields = [];
		foreach ($fieldMappings as $field => $mapping) {
			$fields[$field] = $mapping['type'];
		}
		return $fields;
	}

	public function getFieldsByType($map, $types = ['string','text'])
	{
		$metadata = $this->getMetadata($map);
		$fieldMappings = (array) $metadata->fieldMappings;
		$fields = [];
		foreach ($fieldMappings as $field => $mapping) {
			if (in_array($mapping['type'], $types)) {
				$fields [] = $field;
			}
		}
		return $fields;
	}

	public function getFieldType($map, $field)
	{
		$metadata = $this->getMetadata($map);
		$fields = (array) $metadata->fieldMappings;
		if (!in_array($field, array_keys($fields))) {
			return false;
		}

		return $fields[$field]['type'];
	}

	public function getFieldValue($field, $object)
	{
		$getter = $this->getGetterMethod($object,$field);
		if (!$getter)
		{
			$this->session->getFlashBag()->add('error', 'Getter Method for ' . $field . ' in ' . get_class($object) . ' not found.');
			return false;
		}
		return call_user_func_array([$object,$getter], []);
	}

	public function getListFields($map)
	{
		$object = $this->getNewItem($map);
		$list = [$this->getIdentifier($map)];
		if (array_key_exists('list', $map) && is_array($map['list']) && count($map['list'])) {
			$list = array_merge($list, $map['list']);
			$field = $this->getConfigKey($map, 'sort_field');
			if ($this->isSortable($map) && !in_array($field, $list)) $list[] = $field;
			return array_unique($list, SORT_REGULAR);
		}
		if (method_exists($object, 'getPreview')) {
			$list[] = '_preview';
		}
		$fields = $this->getFields($map);
		$trash_field = $this->getConfigKey($map, 'trash_field');
		foreach ($fields as $field) {
			if ($field == $trash_field) continue;
			$list[] = lcfirst($this->getCamel($field));
		}
		return array_unique($list, SORT_REGULAR);
	}

	public function getForm($map, $object = false)
	{
		$form = $map['name'];
		if (array_key_exists('form', $map)) {
			return $this->createForm($map['form'], $object);
		}
		if (class_exists($form)) {
			return $this->createForm($form, $object);
		}
		$bundleNamespace = $this->getBundleNamespace($map);
		if($bundleNamespace) {
			$form = ( $bundleNamespace . "\\Form\\Mcm" . $this->getCamel($map['section']) . "\\" . $this->getCamel($map['name']) . "Type" );
			if (class_exists($form)) {
				return $this->createForm($form, $object);
			}
			$form = ( $bundleNamespace . "\\Form\\Mcm\\" . $this->getCamel($map['name']) . "Type" );
			if (class_exists($form)) {
				return $this->createForm($form, $object);
			}
		}
		return $this->generateForm($map, $object);
	}

	public function generateForm($map, $object = false)
	{
		if (!$object) {
			$object = $this->getNewItem($map);
		}

		$fields = $this->getFields($map);
		$form = $this->createFormBuilder($object);
		$id = $this->getIdentifierValue($map, $object);
		$isNew = !$id;

		// if ($isNew) {
		// 	$form->setAction($this->generateUrl('maci_admin_view', array(
		// 		'section'=>$map['section'], 'entity'=>$map['name'], 'action'=>'new'
		// 	)));
		// } else {
		// 	$form->setAction($this->generateUrl('maci_admin_view', array(
		// 		'section'=>$map['section'], 'entity'=>$map['name'], 'action'=>'edit', 'id'=>$id
		// 	)));
		// }

		$form->setAction('#');

		$isUploadable = $this->isUploadable($map);
		$upload_path_field = $this->getConfigKey($map,'upload_path_field');

		$fieldMappings = $this->getMetadata($map)->fieldMappings;

		foreach ($fields as $field) {

			if ($this->hasTrash($map) && $field === $this->getConfigKey($map,'trash_field')) {
				continue;
			}
			if (in_array($field, array('updated', 'created'))) {
				continue;
			}
			if (!array_key_exists($field, $fieldMappings)) {
				$field = str_replace('_', '', $field);
				if (!array_key_exists($field, $fieldMappings)) {
					continue;
				}
			}
			if (array_key_exists('id', $fieldMappings[$field]) && $fieldMappings[$field]['id']) {
				continue;
			}
			if (!array_key_exists('type', $fieldMappings[$field])) {
				continue;
			}
			if (!in_array(
				$fieldMappings[$field]['type'],
				['text', 'string', 'decimal', 'smallint', 'integer', 'bigint', 'boolean', 'date', 'datetime']
			)) {
				continue;
			}

			$arrMethod = ('get' . ucfirst($field) . 'Array');

			if (method_exists($object, $arrMethod))
			{
				$form->add($field, ChoiceType::class, array(
					'empty_data' => '',
					'choices' => call_user_func_array(array($object, $arrMethod), [])
				));
			}
			else if ($isUploadable && $field === $upload_path_field)
			{
				$form->add('file', FileType::class, array('required' => false));
			}
			else if ($field === 'locale')
			{
				$form->add('locale', ChoiceType::class, array(
					'empty_data' => '',
					'choices' => $this->getLocales()
				));
			}
			else
			{
				$form->add($field);
			}
		}

		if ($isNew) {
			if ($this->getCurrentAction() != 'relations') {
				$form->add('save', SubmitType::class, array(
					'label'=>'Save & Edit Item',
					'attr'=>array('class'=>'btn btn-success')
				));
			}
			$form->add('save_and_list', SubmitType::class, array(
				'label'=>'Save & Return to List',
				'attr'=>array('class'=>'btn btn-primary')
			));
			$form->add('save_and_add', SubmitType::class, array(
				'label'=>'Save & Add a New Item',
				'attr'=>array('class'=>'btn btn-primary')
			));
		} else {
			$form->add('save', SubmitType::class, array(
				'attr'=>array('class'=>'btn btn-success')
			));
			$form->add('save_and_list', SubmitType::class, array(
				'label'=>'Save & Return to List',
				'attr'=>array('class'=>'btn btn-primary')
			));
		}

		$form->add('reset', ResetType::class, array(
			'label'=>'Reset Form'
		));

		return $form->getForm();
	}

	public function generateFiltersForm($map, $action, $object = false, $opt = [])
	{
		if (!$object) {
			$object = $this->getNewItem($map);
		}

		$fields = $this->getFields($map);
		$object = $this->getNewItem($map);
		$form = $this->createFormBuilder($object);
		$isUploadable = $this->isUploadable($map);
		$upload_path_field = $this->getConfigKey($map, 'upload_path_field');
		$fieldMappings = $this->getMetadata($map)->fieldMappings;
		$filters = [];

		$form->setAction(array_key_exists('form_action', $opt) ?
			$opt['form_action'] : $this->getCurrentUrl(array_key_exists('url_opt', $opt) ? $opt['url_opt'] : [])
		);

		foreach ($fields as $field) {

			if ($this->hasTrash($map) && $field === $this->getConfigKey($map,'trash_field')) {
				continue;
			}
			if (!array_key_exists($field, $fieldMappings)) {
				$field = str_replace('_', '', $field);
				if (!array_key_exists($field, $fieldMappings)) {
					continue;
				}
			}
			if (array_key_exists('id', $fieldMappings[$field]) && $fieldMappings[$field]['id']) {
				continue;
			}
			if (!array_key_exists('type', $fieldMappings[$field])) {
				continue;
			}
			if (!in_array(
				$fieldMappings[$field]['type'],
				['text', 'string', 'decimal', 'smallint', 'integer', 'bigint', 'boolean', 'date', 'datetime']
			)) {
				continue;
			}

			if (in_array($this->getFieldType($map, $field), ['text', 'string']) &&
				(!$isUploadable || $field != $upload_path_field) &&
				!in_array($field, ['locale']))
			{
				$form->add('set_filter_for_' . $field, CheckboxType::class, [
					'label' => 'Set Filter',
					'label_attr' => array(
						'class' => 'sr-only'
					),
					'attr' => ['class' => 'setFilterCheckbox'],
					'required' => false,
					'mapped' => false
				]);
				$form->add('set_connector_for_' . $field, ChoiceType::class, [
					'label' => 'Set Connector',
					'label_attr' => array(
						'class' => 'sr-only'
					),
					'choices' => ['And' => 'and', 'Or' => 'or'],
					'mapped' => false
				]);
				$label = $this->generateLabel($field);
				$typesGetter = $this->getGetterMethod($object, $field . 'Array');
				if ($typesGetter)
				{
					$form->add($field . '_method', ChoiceType::class, [
						'label' => 'Method',
						'label_attr' => array(
							'class' => 'sr-only'
						),
						'choices' => ['Is' => '=', 'Is Not' => '!='],
						'mapped' => false
					]);
					$list = call_user_func_array([$object, $typesGetter], []);
					$form->add($field, ChoiceType::class, [
						'label_attr' => array(
							'class' => 'sr-only'
						),
						'choices' => $list
					]);
					array_push($filters, [
						'field' => $field,
						'label' => $label,
						'type' => 'select'
					]);
				}
				else
				{
					$form->add($field . '_method', ChoiceType::class, [
						'label' => 'Method',
						'label_attr' => array(
							'class' => 'sr-only'
						),
						'choices' => ['Is' => '=', 'Contains' => 'LIKE', 'Is Not' => '!='],
						'mapped' => false
					]);
					$form->add($field, TextType::class, [
						'attr' => ['placeholder' => $label],
						'label_attr' => array(
							'class' => 'sr-only'
						),
						'required' => false,
						'data' => ''
					]);
					array_push($filters, [
						'field' => $field,
						'label' => $label,
						'type' => 'text'
					]);
				}
			}
		}
		$this->setGeneratedFilters($map, $action, $filters);

		$form->add('add_filters', SubmitType::class, array(
			'label'=>'Add Filters',
			'attr'=>array('class'=>'btn btn-primary')
		));

		$form->add('set_filters', SubmitType::class, array(
			'label'=>'Set Filters',
			'attr'=>array('class'=>'btn btn-danger')
		));

		$form->add('unset_filters', SubmitType::class, array(
			'label'=>'Unset All Filters',
			'attr'=>array('class'=>'btn btn-danger')
		));

		return $form->getForm();
	}

	public function handleFiltersForm($map, $action, $form)
	{
		$form->handleRequest($this->request);
		if (!($form->isSubmitted() && $form->isValid())) return false;

		if ($form['set_filters']->isClicked() || $form['unset_filters']->isClicked())
		{
			$this->unsetAllFilters($map, $action);
			if ($form['unset_filters']->isClicked()) return true;
		}

		$fields = $this->getFields($map);
		$object = $this->getNewItem($map);
		$isUploadable = $this->isUploadable($map);
		$upload_path_field = $this->getConfigKey($map, 'upload_path_field');
		$filters = [];

		foreach ($fields as $field) {

			if (in_array($this->getFieldType($map, $field), ['text', 'string']) &&
				(!$isUploadable || $field != $upload_path_field) &&
				!in_array($field, ['locale']) &&
				$form->has('set_filter_for_type') &&
				$form['set_filter_for_' . $field]->getData()
			){
				$typesGetter = $this->getGetterMethod($object, $field . 'Array');
				$this->addFilter($map, $action, [
					'connector' => $form['set_connector_for_' . $field]->getData(),
					'field' => $field,
					'method' => $form[$field . '_method']->getData(),
					'value' => $form[$field]->getData()
				]);
			}
		}

		return true;
	}

	static public function getLocales()
	{
		$list = array_flip(Locales::getNames());
		$new = [];
		foreach ($list as $key => $value) {
			if (strlen($value) == 2) {
				$new[ucfirst($key)] = $value;
			}
		}
		return $new;
	}

	public function getIdentifier($map)
	{
		$identifiers = $this->getMetadata($map)->getIdentifier();
		return $identifiers[0];
	}

	public function getIdentifierValue($map, $item)
	{
		if(!is_object($item)) {
			return false;
		}
		$identifier = $this->getIdentifier($map);
		$getter = $this->getGetterMethod($item, $identifier);
		if ($getter) {
			return call_user_func_array(array($item, $getter), []);
		}
		return false;
	}

	public function getItem($map, $id)
	{
		return $this->getRepository($map)->findOneBy([$this->getIdentifier($map) => $id]);
	}

	public function getItemData($map, $item, $getters = false)
	{
		if (!$getters) $getters = $this->getGetters($map);
		$types = $this->getFieldsWithType($map);
		$data = [];
		foreach ($getters as $field => $getter) {
			$value = call_user_func_array([$item, $getter], []);
			if ($value === null) {
				$data[$field] = null;
			} elseif (in_array($types[$field], [
				'string', 'text', 'integer', 'smallint', 'bigint', 'boolean', 'decimal', 'float', 'json', 'guid'
			])) {
				$data[$field] = $value;
			} elseif ($types[$field] === 'date') {
				$data[$field] = date_format($value, "d-m-Y");
			} elseif ($types[$field] === 'time') {
				$data[$field] = date_format($value, "H:i:s");
			} elseif ($types[$field] === 'datetime') {
				$data[$field] = date_format($value, "Y/m/d H:i:s");
			} elseif ($types[$field] === 'datetimetz') {
				$data[$field] = date_format($value, "c");
			} elseif ($types[$field] === 'object') {
				$data[$field] = get_class($value);
			} elseif ($types[$field] === 'simple_array') {
				$data[$field] = implode(', ', $value);
			} elseif ($types[$field] === 'json') {
				$data[$field] = $value;
			} else {
				continue;
			}
		}
		return $data;
	}

	public function setItem($map, $item, $data)
	{
		$associations = $this->getAssociations($map);
		foreach ($data as $field => $value) {
			$setter = $this->getSetterMethod($item, $field);
			if (!$setter) {
				continue;
			}
			if (in_array($field, $associations))
			{
				$relation = $this->getRelation($map, $field);
				if (!$relation) continue;
				$this->addRelationItems($map, $relation, $item, $this->getRelationList($relation, $field));
				continue;
			}
			call_user_func_array([$item, $setter], [$value]);
		}
	}

	public function removeItems($map, $list)
	{
		if (!count($list)) return false;
		$removed = 0;
		foreach ($list as $item) {
			try {
				$this->om->remove($item);
				$this->om->flush();
				$removed++;
			} catch (\Doctrine\DBAL\DBALException $e) {
				$exception_message = $e->getPrevious()->getCode();
				$this->session->getFlashBag()->add('error', 'Error! Item [' . $map['label'] . ':' . $item . '] not removed. Exception: ' . get_class($e) . ' - ' . $exception_message . '.');
				// $this->om = $this->om->create($this->om->getConnection(), $this->om->getConfiguration());
			}
		}
		$this->session->getFlashBag()->add((0 < $removed ? 'success' : 'error'), (0 < $removed ? 'Success: ' : 'Error: ') .  $removed . ' item' . ($removed == 1 ? '' : 's') . (count($list) == 1 ? '' : ' of ' . count($list)) . ' removed.');
		return true;
	}

	public function isItemTrashed($map, $item)
	{
		$getter = $this->getGetterMethod($item, $this->getTrashField($map));
		if ($getter) {
			return call_user_func_array([$item, $getter], []);
		}
		$this->session->getFlashBag()->add('error', 'Getter Method for entity [' . $map['label'] . '] not found.');
		return false;
	}

	public function trashItems($map, $list)
	{
		if (!$this->hasTrash($map) || !count($list)) return false;
		$remover = $this->getRemoverMethod($list[0], $this->getTrashField($map));
		if (!$remover) {
			$remover = $this->getSetterMethod($list[0], $this->getTrashField($map));
			if (!$remover) {
				$this->session->getFlashBag()->add('error', 'Remover or Trash-Setter Method for entity [' . $map['label'] . '] not found.');
				return false;
			}
		}
		foreach ($list as $item) {
			$val = $this->isItemTrashed($map, $item);
			call_user_func_array([$item, $remover], [!$val]);
			$this->session->getFlashBag()->add('success', 'Item [' . $map['label'] . ':' . $item . '] ' . ($val ? 'restored' : 'moved to trash.'));
		}
		$this->om->flush();
		return true;
	}

	public function trashItemsFromRequestIds($map, $list)
	{
		return $this->trashItems($map, $this->selectItemsFromRequestIds($map,$list));
	}

	public function selectItemsFromRequestIds($map, $list)
	{
		$ids = $this->request->get('ids', '');
		if (is_string($ids)) {
			$ids = explode(',', $ids);
			if (!count($ids)) {
				return false;
			}
		}
		if(!is_array($ids)) {
			return false;
		}
		return $this->selectItems($map, $list, $ids);
	}

	public function selectItems($map, $list, $ids)
	{
		$obj_list = [];
		$ids_list = $this->getListIdentifiers($map, $list);
		if(count($ids_list)) {
			foreach ($ids as $_id) {
				if (trim($_id) === "") {
					continue;
				} else if(is_int($ids_list[0])) {
					$_id = (int) $_id;
				} else if(is_float($ids_list[0])) {
					$_id = (float) $_id;
				}
				if (!$_id) {
					$this->session->getFlashBag()->add('error', 'Id of Item [' . $_id . '] for ' . $this->getClass($map) . ' not found.');
					continue;
				}
				if (!in_array($_id, $ids_list)) {
					$this->session->getFlashBag()->add('error', 'Item [' . $_id . '] for ' . $this->getClass($map) . ' not found.');
					continue;
				}
				$obj_list[] = $list[array_search($_id, $ids_list)];
			}
		}
		return $obj_list;
	}

	public function getListIdentifiers($map, $list)
	{
		$ids = [];
		foreach ($list as $item) {
			$ids[] = $this->getIdentifierValue($map, $item);
		}
		return $ids;
	}

	public function getList($map, $opt = [])
	{
		$repo = $this->getRepository($map);
		$query = $repo->createQueryBuilder('e');
		$root = $query->getRootAlias();
		$this->addDefaultQueries($map, $query, $opt);
		$query = $query->getQuery();
		$list = $query->getResult();
		return $list;
	}

	public function getListForRelation($map, $relation, $item, $bridge = false, $opt = [])
	{
		$relation_items = $this->getRelationItems($relation, $item);
		$inverseField = $this->getRelationInverseField($map, $relation);
		$mainMap = $relation;
		if ($bridge) {
			$bridge_items = [];
			foreach ($relation_items as $obj) {
				$brcall = call_user_func_array(array($obj, $this->getGetterMethod($obj, $bridge['bridge'])), []);
				$brcall = is_array($brcall) ? $brcall : array($brcall);
				$bridge_items = array_merge($bridge_items, $brcall);
			}
			$relation_items = $bridge_items;
			// todo: same option as below
			$inverseField = $this->getRelationInverseField($relation, $bridge);
			$mainMap = $bridge;
		}
		$repo = $this->getRepository($mainMap);
		$query = $repo->createQueryBuilder('r');
		$root = $query->getRootAlias();
		$this->addDefaultQueries($mainMap, $query, array_merge(['trash' => false], $opt));
		// todo: add a config option for this (remove clones)
		$index = 0;
		foreach ($relation_items as $obj) {
			if (is_object($obj)) {
				$parameter = ':id_' . $index;
				$query->andWhere($root . '.' . $inverseField . ' != ' . $parameter);
				$query->setParameter($parameter, call_user_func_array([$obj, ('get'.ucfirst($inverseField))], []));
				$index++;
			}
		}
		// todo: add a config option for this (remove self)
		if ($this->getClass($map) === $this->getClass($relation) && !$bridge) {
			$query->andWhere($root . '.' . $inverseField . ' != :pid');
			$query->setParameter(':pid', call_user_func_array(array($item, ('get'.ucfirst($inverseField))), []));
		}
		$query = $query->getQuery();
		return $query->getResult();
	}

	public function getListData($map, $fields = false, $opt = [])
	{
		$list = $this->getList($map, $opt);
		return $this->getDataFromList($map, $list, $fields);
	}

	public function getDataFromList($map, $list, $fields = false)
	{
		if ($fields == false) {
			$fields = $this->getGetters($map);
		}
		$data = [];
		foreach ($list as $item) {
			$data[count($data)] = $this->getItemData($map, $item, $fields);
		}
		return $data;
	}

	public function getMetadata(&$map)
	{
		if(!array_key_exists('OMclassMetadata', $map)) {
			$map['OMclassMetadata'] = $this->om->getClassMetadata($this->getClass($map));
		}
		return $map['OMclassMetadata'];
	}

	public function getNewItem($map)
	{
		$class = $this->getClass($map);
		$item = new $class;
		if (method_exists($item, 'setLocale')) {
			call_user_func_array(array($item, 'setLocale'), array($this->request->getLocale()));
		}
		return $item;
	}

	public function getNewMap($metadata)
	{
		$map = $this->getDefaultMap();
		$map['class'] = $metadata['targetEntity'];
		$map['name'] = $metadata['fieldName'];
		$map['label'] = $this->generateLabel($metadata['fieldName']);
		return $map;
	}

	public function getRelation($map,$association)
	{
		if (!$this->isRelationEnable($map,$association)) {
			return false;
		}
		$metadata = $this->getAssociationMetadata($map,$association);
		if (!$metadata) {
			return false;
		}
		$relation = $this->getEntityByClass($metadata['targetEntity'], $map['section']);
		if (!$relation) {
			$relation = $this->getNewMap($metadata);
		}
		if (!$relation['section']) {
			$relation['section'] = $map['section'];
		}
		if (!array_key_exists('entity', $relation) || !$relation['entity']) {
			$relation['entity'] = $association;
		}
		if ($relation['section'] == null) {
			$relation['entity_root_section'] = $map['section'];
		} else {
			$relation['entity_root_section'] = $relation['section'];
		}
		if ($relation['name'] == null) {
			$relation['entity_root'] = $map['name'];
		} else {
			$relation['entity_root'] = $relation['name'];
		}
		$relation['parent_entity'] = $map['name'];
		$relation['association'] = $association;
		$this->loadConfig($relation);
		if (array_key_exists('relations', $map) && array_key_exists($association, $map['relations'])) {
			$relation = $this->mergeViews($map['relations'][$association], $relation);
		}
		if (!$this->isAuthorized($relation)) {
			return false;
		}
		return $relation;
	}

	public function getRelationList($relation, $id)
	{
		return $this->getList($relation, [
			'trash' => false,
			'filters' => [[
				'field' => $this->getIdentifier($relation),
				'value' => $id
			]]
		]);
	}

	public function getRelationDefaultAction($map, $association)
	{
		$relationMetadata = $this->getAssociationMetadata($map, $association);
		if (!$relationMetadata) {
			return false;
		}
		if ($relationMetadata['type'] === ClassMetadataInfo::ONE_TO_MANY || $relationMetadata['type'] === ClassMetadataInfo::MANY_TO_MANY) {
			return 'list';
		}
		return 'show';
	}

	public function getRelationSetterAction($map, $association)
	{
		return $this->getRelationDefaultAction($map, $association) === 'show' ? 'set' : 'add';
	}

	public function isRelationEnable($map,$association)
	{
		if (array_key_exists('relations', $map) &&
			array_key_exists($association, $map['relations'])) {
			return $this->getConfigKey($map['relations'][$association], 'enabled');
		}
		return $this->getConfigKey($map, 'enabled');
	}

	public function getRelationInverseField($map, $relation)
	{
		$relationMetadata = $this->getAssociationMetadata($map, $relation['association']);
		$joinTable = false;
		$joinColumns = false;
		$inverseJoinColumns = false;
		$inverseField = false;
		if (array_key_exists('joinTable', $relationMetadata)) {
			$joinTable = $relationMetadata['joinTable'];
			if (array_key_exists('joinColumns', $joinTable)) {
				$joinColumns = $joinTable['joinColumns'];
				$inverseJoinColumns = $joinTable['inverseJoinColumns'];
			}
		} else if (array_key_exists('joinColumns', $relationMetadata)) {
			$joinColumns = $relationMetadata['joinColumns'];
		}
		if ($joinColumns) {
			$sourceField = $joinColumns[0]['referencedColumnName'];
		}
		if ($inverseJoinColumns) {
			$inverseField = $inverseJoinColumns[0]['referencedColumnName'];
		}
		if (!$inverseField) {
			$inverseField = $this->getIdentifier($relation);
		}
		return $inverseField;
	}

	public function getRelationItems($relation, $object)
	{
		$getted = $this->getFieldValue($relation['association'], $object);
		if (is_object($getted))
		{
			if (is_array($getted) || get_class($getted) === 'Doctrine\ORM\PersistentCollection')
				return $getted;
			else
				return [$getted];
		}
		return [];
		// return $this->getList($relation, ['filters' => [($relation['association']) => $this->getIdentifierValue($relation, $object)]]);
	}

	public function getRemoveForm($map, $item, $trash = false, $opt = [])
	{
		return $this->createFormBuilder($item)
			->setAction($this->getEntityUrl($map, ($trash ? 'trash' : 'remove'), $this->getIdentifierValue($map, $item), $opt))
			->add('remove', SubmitType::class, array(
				'attr' => array('class' => 'btn-danger')
			))
			->getForm()
		;
	}

	public function getRelationRemoveForm($map, $relation, $item, $opt = [])
	{
		return $this->createFormBuilder($item)
			->setAction($this->getRelationUrl($map, $relation, 'remove', null, $opt))
			->add('remove', SubmitType::class, array(
				'attr' => array('class' => 'btn-danger')
			))
			->getForm()
		;
	}

	/*
		------------> Map Getters
	*/

	public function getRepository($map)
	{
		return $this->om->getRepository($map['class']);
	}

	public function isSortable($map)
	{
		return ($this->getConfigKey($map, 'sortable') && in_array($this->getConfigKey($map, 'sort_field'), $this->getFields($map)));
	}

	public function getTableName($map)
	{
		$metadata = $this->getMetadata($map);
		return $metadata->table['name'];
	}

	public function hasTrash($map)
	{
		return ($this->getConfigKey($map, 'trash') && in_array($this->getConfigKey($map, 'trash_field'), $this->getFields($map)));
	}

	public function getTrashField($map)
	{
		return $this->hasTrash($map) ? $this->getConfigKey($map, 'trash_field') : false;
	}

	public function isUploadable($map)
	{
		return ($this->getConfigKey($map, 'uploadable') && $this->getSetterMethod($this->getNewItem($map), $this->getConfigKey($map, 'upload_field')));
	}

	/*
		------------> Pager
	*/

	public function getPager($map, $action, $list, $opt = [])
	{
		$pager = new MaciPager($list,
			array_key_exists('page_limit', $opt) ? $opt['page_limit'] : $this->getPager_PageLimit($map, $action),
			array_key_exists('page_range', $opt) ? $opt['page_range'] : $this->getPager_PageRange($map, $action)
		);
		$pager->setPage(array_key_exists('page', $opt) ? $opt['page'] : $this->getStoredPage($map, $action));
		if ($this->isSortable($map)) $pager->setLimit(0);
		else $pager->setForm($this->getPagerForm($map, $action, $pager, $opt));
		$pager->setIdentifiers($this->getListIdentifiers($map, $list));
		return $pager;
	}

	public function getPagerForm($map, $action, $pager = false, $opt = [])
	{
		$values = [
			'page' => $pager ? $pager->getPage() : $this->getStoredPage($map, $action),
			'page_limit' => array_key_exists('page_limit', $opt) ? $opt['page_limit'] : $this->getPager_PageLimit($map, $action),
			'order_by_field' => array_key_exists('sort_field', $opt) ? $opt['sort_field'] : $this->getPagerForm_OrderByField($map, $action),
			'order_by_sort' => array_key_exists('sort_order', $opt) ? $opt['sort_order'] : $this->getPagerForm_OrderBySort($map, $action)
		];
		$page_attr = $pager ? ['max' => $pager->getMaxPages()] : [];
		$urlOpt = array_key_exists('url_opt', $opt) ? $opt['url_opt'] : [];
		$formAction = array_key_exists('form_action', $opt) ?
			$opt['form_action'] : $this->getMapUrl($map, $action, null, $urlOpt);
		return $this->createFormBuilder($values)
			->setAction($formAction)
			->add('page', IntegerType::class, [
				'label' => 'Page:',
				'attr' => $page_attr
			])
			->add('page_limit', IntegerType::class, [
				'label' => 'Items per Page:'
			])
			->add('order_by_field', ChoiceType::class, [
				'label' => 'Order By:',
				'choices' => $this->getArrayWithLabels($this->getFields($map, false))
			])
			->add('order_by_sort', ChoiceType::class, [
				'label' => 'Sort',
				'label_attr' => ['class' => 'sr-only'],
				'choices' => ['Asc' => 'ASC', 'Desc' => 'DESC']
			])
			->add('set', SubmitType::class,  [
				'attr' => ['class' => 'btn btn-primary']
			])
			->getForm()
		;
	}

	public function getPager_PageLimit($map, $action)
	{
		return $this->session->get(('maci_admin_' . $this->getSessionIdentifier($map, $action) . '_page_limit'), ($map && array_key_exists('page_limit', $map) ? $map['page_limit'] : $this->_defaults['page_limit']));
	}

	public function setPager_PageLimit($map, $action, $value)
	{
		return $this->session->set(('maci_admin_' . $this->getSessionIdentifier($map, $action) . '_page_limit'), $value);
	}

	public function getPager_PageRange($map, $action)
	{
		return $this->session->get(('maci_admin_' . $this->getSessionIdentifier($map, $action) . '_page_range'), ($map && array_key_exists('page_range', $map) ? $map['page_range'] : $this->_defaults['page_range']));
	}

	public function setPager_PageRange($map, $action, $value)
	{
		return $this->session->set(('maci_admin_' . $this->getSessionIdentifier($map, $action) . '_page_range'), $value);
	}

	public function getPagerForm_OrderByField($map, $action)
	{
		return $this->session->get(('maci_admin_' . $this->getSessionIdentifier($map, $action) . '_order_by_field'), $this->getIdentifier($map));
	}

	public function setPagerForm_OrderByField($map, $action, $value)
	{
		return $this->session->set(('maci_admin_' . $this->getSessionIdentifier($map, $action) . '_order_by_field'), $value);
	}

	public function getPagerForm_OrderBySort($map, $action)
	{
		return $this->session->get(('maci_admin_' . $this->getSessionIdentifier($map, $action) . '_order_by_sort'), 'DESC');
	}

	public function setPagerForm_OrderBySort($map, $action, $value)
	{
		return $this->session->set(('maci_admin_' . $this->getSessionIdentifier($map, $action) . '_order_by_sort'), $value);
	}

	public function setPagerOptions($map, $action, $pager = false, $opt = [])
	{
		if ($this->isSortable($map)) return false;

		$form = $this->getPagerForm($map, $action, $pager, $opt);
		$form->handleRequest($this->request);

		if (!($form->isSubmitted() && $form->isValid())) return false;

		$this->setPager_PageLimit($map, $action, $form->get('page_limit')->getData());
		$this->setPagerForm_OrderByField($map, $action, $form->get('order_by_field')->getData());
		$this->setPagerForm_OrderBySort($map, $action, $form->get('order_by_sort')->getData());

		// $page = (int) $form->get('page')->getData();
		// if (1<$page) $opt['page'] = $page;
		return true;
	}

	/*
		------------> Relations Manager
	*/

	public function getManagerSuccessMessage($managerMethod, $entity, $relation, $object)
	{
		$message = 'Item [' . ((string) $object) . '] ';
		if (strpos($managerMethod, 'Remover') !== false) {
			return $message . 'Removed.';
		} else if (strpos($managerMethod, 'Adder')) {
			return $message . ( $this->getRelationDefaultAction($entity, $relation['association']) === 'show' ? 'Setted.' : 'Added.' );
		} else {
			return $message . 'Setted.';
		}
	}

	public function manageRelation($managerName, $entity, $field, $item, $obj)
	{
		$managerMethod = 'get' . $managerName . 'Method';
		$manager = call_user_func_array(array($this, $managerMethod), array($item, $field));
		if (!$manager) {
			$this->session->getFlashBag()->add('error', $managerName . ' Method for ' . $field . ' in ' . $this->getClass($entity) . ' not found.');
			return false;
		}
		call_user_func_array([$item, $manager], [(strpos($managerMethod, 'Remover') !== false && !$this->getRemoverMethod($item, $field)) ? null : $obj]);
		return true;
	}

	public function manageRelationItem($managerMethod, $entity, $relation, $item, $obj)
	{
		if (!$this->manageRelation($managerMethod, $entity, $relation['association'], $item, $obj)) {
			return false;
		}
		$relationMetadata = $this->getAssociationMetadata($entity, $relation['association']);
		$isOwningSide = $relationMetadata['isOwningSide'];
		$mappedBy = $relationMetadata['mappedBy'];
		if (!$isOwningSide && 0<strlen($mappedBy) && !$this->manageRelation($managerMethod, $relation, $mappedBy, $obj, $item)) {
			return false;
		}
		$this->session->getFlashBag()->add('success', $this->getManagerSuccessMessage($managerMethod, $entity, $relation, $obj));
		return true;
	}

	public function manageRelationItems($managerMethod, $entity, $relation, $item, $list)
	{
		foreach ($list as $obj) {
			$this->manageRelationItem($managerMethod, $entity, $relation, $item, $obj);
		}
		$this->om->flush();
		return true;
	}

	public function addRelationItems($entity, $relation, $item, $list)
	{
		return $this->manageRelationItems('SetterOrAdder', $entity, $relation, $item, $list);
	}

	public function removeRelationItems($entity, $relation, $item, $list)
	{
		return $this->manageRelationItems('RemoverOrSetter', $entity, $relation, $item, $list);
	}

	public function removeItemsFromRequestIds($map, $list)
	{
		return $this->removeItems($map, $this->selectItemsFromRequestIds($map,$list));
	}

	public function addRelationItemsFromRequestIds($entity, $relation, $item, $list)
	{
		return $this->addRelationItems($entity, $relation, $item, $this->selectItemsFromRequestIds($relation,$list));
	}

	public function removeRelationItemsFromRequestIds($entity, $relation, $item, $list)
	{
		return $this->removeRelationItems($entity, $relation, $item, $this->selectItemsFromRequestIds($relation,$list));
	}

	/*
		------------> Bridges Manager
	*/

	public function manageBridgeItems($managerMethod, $entity, $relation, $bridge, $item, $list)
	{
		foreach ($list as $obj) {
			$newItem = $this->getNewItem($relation);
			if ($this->manageRelationItem($managerMethod, $entity, $relation, $item, $newItem) &&
				$this->manageRelationItem($managerMethod, $relation, $bridge, $newItem, $obj)
			) {
				if (strpos($managerMethod, 'Remover') === false) {
					$this->om->persist($newItem);
				}
				$this->session->getFlashBag()->add('success', $this->getManagerSuccessMessage($managerMethod, $entity, $relation, $obj));
			}
		}
		$this->om->flush();
		return true;
	}

	public function addBridgeItems($entity, $relation, $bridge, $item, $list)
	{
		return $this->manageBridgeItems('SetterOrAdder', $entity, $relation, $bridge, $item, $list);
	}

	public function addRelationBridgedItemsFromRequestIds($entity, $relation, $bridge, $item, $list)
	{
		return $this->addBridgeItems($entity, $relation, $bridge, $item, $this->selectItemsFromRequestIds($bridge,$list));
	}

	/*
		------------> search an object method
	*/

	public function searchMethod($object,$field,$prefix = null)
	{
		if (method_exists($object, $field)) {
			return $field;
		}
		$methodName = ( $prefix . ucfirst($field) );
		if (method_exists($object, $methodName)) {
			return $methodName;
		}
		$methodName = ( $prefix . ucfirst($field) . 's' );
		if (method_exists($object, $methodName)) {
			return $methodName;
		}
		if ($field === 'children') {
			$methodName = $prefix ? $prefix . 'Child' : 'child';
			if (method_exists($object, $methodName)) {
				return $methodName;
			}
		}
		if (strpos($field, '_')) {
			$methodName = $this->searchMethod($object, str_replace('_', '', $field), $prefix);
			if ($methodName) {
				return $methodName;
			}
		}
		if (2<strlen($field)) {
			$_len = strlen($field) - 1;
			if ($field[$_len] === 's') {
				$_sr = $this->searchMethod($object, substr($field, 0, $_len), $prefix);
				if ($_sr) {
					return $_sr;
				}
			}
		}
		return false;
	}

	public function getGetterMethod($object,$field,$prefix='get')
	{
		return $this->searchMethod($object,$field,$prefix);
	}

	public function getIsMethod($object,$field,$prefix='is')
	{
		return $this->searchMethod($object,$field,$prefix);
	}

	public function getSetterMethod($object,$field,$prefix='set')
	{
		return $this->searchMethod($object,$field,$prefix);
	}

	public function getAdderMethod($object,$field,$prefix='add')
	{
		return $this->searchMethod($object,$field,$prefix);
	}

	public function getRemoverMethod($object,$field,$prefix='remove')
	{
		return $this->searchMethod($object,$field,$prefix);
	}

	public function getSetterOrAdderMethod($object,$field)
	{
		$methodName = $this->getSetterMethod($object,$field);
		if ($methodName) {
			return $methodName;
		}
		$methodName = $this->getAdderMethod($object,$field);
		if ($methodName) {
			return $methodName;
		}
		return false;
	}

	public function getRemoverOrSetterMethod($object,$field)
	{
		$methodName = $this->getRemoverMethod($object,$field);
		if ($methodName) {
			return $methodName;
		}
		$methodName = $this->getSetterMethod($object,$field);
		if ($methodName) {
			return $methodName;
		}
		return false;
	}

	public function getGetters($map, $removeId = false)
	{
		$fields = $this->getFields($map, $removeId);
		$obj = $this->getNewItem($map);
		$getters = [];

		foreach ($fields as $field) {
			$getter = $this->getGetterMethod($obj, $field);
			if (!$getter) {
				return false;
			}
			$getters[$field] = $getter;
		}

		return $getters;
	}

	public function getSetters($map, $removeId = false)
	{
		$fields = $this->getFields($map, $removeId);
		$obj = $this->getNewItem($map);
		$setters = [];

		foreach ($fields as $field) {
			$setter = $this->getSetterMethod($obj, $field);
			if (!$setter) {
				return false;
			}
			$setters[$field] = $setter;
		}

		return $setters;
	}

	/*
		------------> Api Manager
	*/

	public function getListDataByParams($data)
	{
		if (!array_key_exists('section', $data) || !array_key_exists('entity', $data)) {
			return ['success' => false, 'error' => 'Bad Request.'];
		}
		$entity = $this->getEntity($data['section'], $data['entity']);
		if (!$entity) {
			return ['success' => false, 'error' => 'Entity "' . $entity . '" not Found.'];
		}
		return $this->getListData(
			$entity,
			array_key_exists('fields', $data) ? $data['fields'] : false,
			[
				'use_session' => false,
				'search' => array_key_exists('search', $data) ? $data['search'] : false,
				'trash' => array_key_exists('trash', $data) ? $data['trash'] : null,
				'filters' => array_key_exists('filters', $data) ? $data['filters'] : false,
				'order' => array_key_exists('order', $data) ? $data['order'] : []
			]
		);
	}

	public function getItemDataByParams($data)
	{
		if (!array_key_exists('section', $data) || !array_key_exists('entity', $data) || !array_key_exists('id', $data)) {
			return ['success' => false, 'error' => 'Bad Request.'];
		}
		$entity = $this->getEntity($data['section'], $data['entity']);
		if (!$entity) {
			return ['success' => false, 'error' => 'Entity "' . $data['entity'] . '" not Found.'];
		}
		$item = $this->getItem($entity, $data['id']);
		if (!$item) {
			return ['success' => false, 'error' => 'Item "' . $data['id'] . '" for entity "' . $entity['label'] . '" not Found.'];
		}
		return $this->getItemData($entity, $item);
	}

	public function newItemByParams($entity, $params)
	{
		$item = $this->getNewItem($entity);
		$this->setItem($entity, $item, $params);
		$this->om->persist($item);
		$this->om->flush();
		return $item;
	}

	public function newItemsByParams($data)
	{
		if (!is_array($data) || !array_key_exists('section', $data) ||
			!array_key_exists('entity', $data) || !array_key_exists('params', $data)
		) return ['success' => false, 'error' => 'Bad Request.'];
		$entity = $this->getEntity($data['section'], $data['entity']);
		if (!$entity) {
			return ['success' => false, 'error' => 'Entity "' . $data['entity'] . '" not Found.'];
		}
		if (!array_key_exists(0, $data['params']))
			return ['success' => true, 'id' => $this->getIdentifierValue($entity, $this->newItemByParams($entity, $data['params']))];
		$list = [];
		$items = [];
		for ($i=0; $i < count($data['params']); $i++)
		{ 
			$items[$i] = $this->newItemByParams($entity, $data['params'][$i]);
			$list[$i] = ['success' => true, 'id' => $this->getIdentifierValue($entity, $items[$i])];
		}
		$result = ['success' => true, 'list' => $list];
		if (!array_key_exists('relations', $data) || count($items) == 0) return $result;
		$associations = $this->getAssociations($entity);
		foreach ($data['relations'] as $field => $value)
		{
			$setter = $this->getSetterMethod($items[0], $field);
			if (!$setter || !in_array($field, $associations)) continue;
			$relation = $this->getRelation($entity, $field);
			if (!$relation) continue;
			$relList = $this->getRelationList($relation, $value);
			for ($i=0; $i < count($items); $i++)
				$this->addRelationItems($entity, $relation, $items[$i], $relList);
		}
		return $result;
	}

	public function editItemByParams($data)
	{
		if (!array_key_exists('section', $data) || !array_key_exists('entity', $data) ||
			!array_key_exists('id', $data) || !array_key_exists('data', $data)) {
			return ['success' => false, 'error' => 'Bad Request.'];
		}
		$entity = $this->getEntity($data['section'], $data['entity']);
		if (!$entity) {
			return ['success' => false, 'error' => 'Entity "' . $data['entity'] . '" not Found.'];
		}
		$item = $this->getItem($entity, $data['id']);
		if (!$item) {
			return ['success' => false, 'error' => 'Item "' . $data['id'] . '" for entity "' . $entity['label'] . '" not Found.'];
		}
		$this->setItem($entity, $item, $data['data']);
		$this->om->flush();
		return ['success' => true];
	}

	public function removeItemByParams($data)
	{
		if (!array_key_exists('section', $data) || !array_key_exists('entity', $data) || !array_key_exists('id', $data)) {
			return ['success' => false, 'error' => 'Bad Request.'];
		}
		$entity = $this->getEntity($data['section'], $data['entity']);
		if (!$entity) {
			return ['success' => false, 'error' => 'Entity "' . $data['entity'] . '" not Found.'];
		}
		$item = $this->getItem($entity, $data['id']);
		if (!$item) {
			return ['success' => false, 'error' => 'Item "' . $data['id'] . '" for entity "' . $entity['label'] . '" not Found.'];
		}
		$trash = array_key_exists('trash', $data) ? ($data['trash'] == "true") : false;
		if ($trash) $this->trashItems($entity, [$item]);
		else $this->removeItems($entity, [$item]);
		return ['success' => true];
	}

	public function addRelationItemsByParams($data)
	{
		if (!array_key_exists('section', $data) || !array_key_exists('entity', $data) || !array_key_exists('id', $data) || !array_key_exists('relation', $data) || !array_key_exists('ids', $data)) {
			return ['success' => false, 'error' => 'Bad Request.'];
		}
		$entity = $this->getEntity($data['section'], $data['entity']);
		if (!$entity) {
			return ['success' => false, 'error' => 'Entity "' . $data['entity'] . '" not Found.'];
		}
		$item = $this->getItem($entity, $data['id']);
		if (!$item) {
			return ['success' => false, 'error' => 'Item "' . $data['id'] . '" for entity "' . $data['entity'] . '" not Found.'];
		}
		$relation = $this->getRelation($entity, $data['relation']);
		if (!$relation) {
			return ['success' => false, 'error' => 'Relation "' . $data['relation'] . '" for entity "' . $data['entity'] . '" not Found.'];
		}
		$this->addRelationItems($entity, $relation, $item, $this->selectItems($relation, $this->getListForRelation($entity, $relation, $item), $data['ids']));
		$this->om->flush();
		return ['success' => true];
	}

	public function addFiltersByParams($data, $map = false, $action = false)
	{
		if (!$map)
		{
			if (!array_key_exists('section', $data) ||
				!array_key_exists('entity', $data) ||
				!array_key_exists('action', $data)
			) return ['success' => false, 'error' => 'Bad Request.'];
			$map = $this->getEntity($data['section'], $data['entity']);
			if (!$map)
				return ['success' => false, 'error' => 'Entity "' . $data['entity'] . '" not Found.'];
		}
		if (!array_key_exists('filters', $data) || !is_array($data['filters']))
			return ['success' => false, 'error' => 'No Filters.'];
		if (array_key_exists('relation', $data))
		{
			$relation = $this->getRelation($map, $data['relation']);
			if (!$relation)
				return ['success' => false, 'error' => 'Relation "' . $data['relation'] . '" for entity "' . $data['entity'] . '" not Found.'];
			if (array_key_exists('bridge', $data))
			{
				$bridge = $this->getBridge($relation, $data['bridge']);
				if (!$bridge)
					return ['success' => false, 'error' => 'Bridge "' . $data['bridge'] . '" for relation "' . $data['relation'] . '" not Found.'];
				$map = $bridge;
			}
			else $map = $relation;
		}
		$action = $action ? $action : $data['action'];
		foreach($data['filters'] as $filter)
		{
			if ($filter == "false") $this->unsetAllFilters($map, $action);
			else $this->addFilter($map, $action, $filter);
		}
		return ['success' => true];
	}

	/*
		------------> Queries
	*/

	public function addDefaultQueries($map, &$query, $opt = [])
	{
		if (!array_key_exists('action', $opt))
			$opt['action'] = false;
		if (!array_key_exists('use_session', $opt))
			$opt['use_session'] = is_string($opt['action']);
		$this->addSearchQuery($map, $query, $opt);
		$this->addFiltersQuery($map, $query, $opt);
		$this->addTrashQuery($map, $query, $opt);
		$this->addOrderByQuery($map, $query, $opt);
	}

	public function addSearchQuery($map, &$query, $opt)
	{
		$search = $this->getOpt($opt, 'search', null);
		if ($search === false) return;
		$search = $opt['use_session'] ? $this->getStoredSearchQuery($map, $opt['action']) : '';
		if (strlen($search))
		{
			$stringFields = $this->getFieldsByType($map);
			foreach ($stringFields as $field)
				$query->orWhere($query->getRootAlias() . '.' . $field . ' LIKE :search');
			$query->setParameter('search', "%$search%");
		}
	}

	public function addFiltersQuery($map, &$query, $opt)
	{
		$filters = $this->getOpt($opt, 'filters', null);
		if ($filters === false) return;
		if ($filters == null)
			$filters = $opt['use_session'] ? $this->getFilters($map, $opt['action']) : [];
		if (!count($filters)) return;
		$fields = $this->getFields($map, false);
		foreach ($filters as $key => $data)
		{
			$field = $data['field'];
			if (!in_array($field, $fields)) {
				continue;
			}
			if (is_array($data) && !array_key_exists(0, $data)) {
				if (!array_key_exists('value', $data)) $value = null;
				else $value = $data['value'];
				if (!array_key_exists('method', $data)) $method = '=';
				else $method = $data['method'];
				if (!array_key_exists('connector', $data)) $connector = 'and';
				else $connector = $data['connector'];
			}
			else
			{
				$connector = 'and';
				$value = $data;
				$method = '=';
			}
			$connector = $connector == 'or' ? 'orWhere' : 'andWhere';
			$query->$connector($query->getRootAlias() . '.' . $field . ' ' . $method . ' :filter_' . $key);
			$query->setParameter('filter_' . $key, $method == 'LIKE' ? "%$value%" : "$value");
		}
	}

	public function addTrashQuery($map, &$query, $opt)
	{
		$trashValue = $this->getOpt($opt, 'trash', null);
		if ($trashValue !== false && $trashValue !== true) return;
		if ($this->hasTrash($map)) {
			$fields = $this->getFields($map);
			$trashAttr = $this->getTrashField($map);
			if ( in_array($trashAttr, $fields) ) {
				$query->andWhere($query->getRootAlias() . '.' . $trashAttr . ' = :' . $trashAttr);
				$query->setParameter(':' . $trashAttr, $trashValue);
			}
		}
	}

	public function addOrderByQuery($map, &$query, $opt)
	{
		$order = $this->getOpt($opt, 'order', []);
		if (!is_array($order)) $order = [];
		$field = $opt['use_session'] ? $this->getPagerForm_OrderByField($map, $opt['action']) : false;
		if (array_key_exists('field', $order)) $field = $order['field'];
		if (!$field) $field = $this->getIdentifier($map);
		$sort = $opt['use_session'] ? $this->getPagerForm_OrderBySort($map, $opt['action']) : 'DESC';
		if (array_key_exists('sort', $order)) $sort = $order['sort'];
		$query->orderBy($query->getRootAlias() . '.' . $field, $sort);
	}

	/*
		------------> Session Parameters
	*/

	public function getSessionIdentifier($map, $action)
	{
		if (!$map) return $this->getCurrentSessionIdentifier();
		if (is_string($map)) return $map;
		return
			(array_key_exists('parent_entity', $map) ? $map['parent_entity'] . '_r_' : '') .
			(array_key_exists('parent_relation', $map) ? $map['parent_relation'] . '_b_' : '') .
			$map['name'] .
			($action ? '_a_' . $action : '')
		;
	}

	public function getCurrentSessionIdentifier()
	{
		$map = $this->getCurrentEntity();
		if (!$map) return null;
		return $this->getSessionIdentifier($this->getCurrentMap(), $this->getCurrentMapAction());
	}

	public function getStoredSearchQueryLabel($map, $action)
	{
		return 'mcm_sq_' . $this->getSessionIdentifier($map, $action);
	}

	public function getStoredSearchQuery($map, $action)
	{
		return $this->session->get($this->getStoredSearchQueryLabel($map, $action), '');
	}

	public function setStoredSearchQuery($map, $action, $value)
	{
		$this->session->set($this->getStoredSearchQueryLabel($map, $action), $value);
	}

	public function setStoredSearchQueryFromRequest($map, $action)
	{
		$get = array_key_exists('s', $_GET) ? $this->request->get('s') : false;
		$str = $this->getStoredSearchQuery($map, $action);

		if ($get === false)
		{
			if (strlen($str))
				return true;

			return false;
		}

		if ($get == $str)
			return false;

		$this->setStoredSearchQuery($map, $action, $get);
		return true;
	}

	public function getStoredPageLabel($map, $action)
	{
		return 'mcm_lp_' . $this->getSessionIdentifier($map, $action);
	}

	public function getStoredPage($map, $action)
	{
		return $this->session->get($this->getStoredPageLabel($map, $action), 1);
	}

	public function setStoredPage($map, $action, $value)
	{
		$this->session->set($this->getStoredPageLabel($map, $action), $value);
	}

	public function setStoredPageFromRequest($map, $action)
	{
		$get = array_key_exists('p', $_GET) ? $this->request->get('p') : false;
		$str = $this->getStoredPage($map, $action);

		if ($get === false)
		{
			if (1 < $str)
				return true;

			return false;
		}

		if ($get == $str)
			return false;

		$this->setStoredPage($map, $action, $get);
		return true;
	}

	public function setFiltersFromRequest($map, $action)
	{
		$data = $this->request->get('data');
		if ($this->request->isXmlHttpRequest() && is_array($data) &&
			array_key_exists('set_filters', $data)
		) {
			$this->session_response = $this->addFiltersByParams($data['set_filters'], $map, $action);
			return true;
		}
		return $this->handleFiltersForm($map, $action,
			$this->generateFiltersForm($map, $action)
		);
	}

	public function setSessionFromRequest($map, $action)
	{
		$this->session_response = false;
		return
			$this->setStoredSearchQueryFromRequest($map, $action) ||
			$this->setStoredPageFromRequest($map, $action) ||
			$this->setPagerOptions($map, $action) ||
			$this->setFiltersFromRequest($map, $action);
	}

	public function getSessionActionResponse()
	{
		return !$this->session_response ?
			$this->getCurrentRedirectParams() :
			$this->session_response
		;
	}

	/*
		------------> Session Parameters for Filters
	*/

	public function getGeneratedFilters($map, $action)
	{
		return $this->session->get('mcm_gf_' . $this->getSessionIdentifier($map, $action), []);
	}

	public function setGeneratedFilters($map, $action, $filters)
	{
		$this->session->set('mcm_gf_' . $this->getSessionIdentifier($map, $action), $filters);
	}

	public function getFilters($map, $action)
	{
		return $this->session->get('mcm_fd_' . $this->getSessionIdentifier($map, $action), []);
	}

	public function setFilters($map, $action, $filters)
	{
		$this->session->set('mcm_fd_' . $this->getSessionIdentifier($map, $action), $filters);
	}

	public function hasFilters($map, $action)
	{
		return !!count($this->getFilters($map, $action));
	}

	public function unsetAllFilters($map, $action)
	{
		$this->setFilters($map, $action, []);
	}

	public function addFilter($map, $action, $filter)
	{
		if (!$filter || !array_key_exists('field', $filter) ||
			!in_array($filter['field'], $this->getFields($map)) ||
			!array_key_exists('value', $filter)
		) return;
		if (!array_key_exists('method', $filter)) $filter['method'] = false;
		if (array_key_exists('unset', $filter) && $filter['unset']) $this->unsetFilter($map, $action, $filter);
		else $this->setFilter($map, $action, $filter);
	}

	public function setFilter($map, $action, $filter)
	{
		$filters = $this->getFilters($map, $action);
		array_push($filters, [
			'connector' => $filter['connector'],
			'field' => $filter['field'],
			'method' => $filter['method'],
			'value' => $filter['value']
		]);
		$this->setFilters($map, $action, $filters);
	}

	public function unsetFilter($map, $action, $filter)
	{
		$filters = $this->getFilters($map, $action);
		foreach ($filters as $key => $sf)
		{
			if ($sf['field'] == $filter['field'] &&
				$sf['method'] == $filter['method'] &&
				$sf['value'] == $filter['value']
			) array_splice($filters, $key);
		}
		$this->setFilters($map, $action, $filters);
	}

	/*
		------------> Static Utils
	*/

	public static function getArrayWithLabels(array $array)
	{
		$list = [];
		foreach ($array as $item) {
			$list[self::generateLabel($item)] = $item;
		}
		return $list;
	}

	public static function getCamel(string $str)
	{
		return Container::camelize($str);
	}

	public static function generateLabel(string $str)
	{
		return ucwords(str_replace('_', ' ', $str));
	}

	public static function getOpt(array $opt, string $key, $default = false)
	{
		return array_key_exists($key, $opt) ? $opt[$key] : $default;
	}

	/*
		------------> Dependencies Shorts
	*/

	public function createForm($type, $data = null, array $opt = [])
	{
		return $this->formFactory->create($type, $data, $opt);
	}

	public function createFormBuilder($data = null, array $opt = [])
	{
		return $this->formFactory->createBuilder(FormType::class, $data, $opt);
	}

	public function generateUrl($route, $parameters = [], $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
	{
		return $this->router->generate($route, $parameters, $referenceType);
	}
}
