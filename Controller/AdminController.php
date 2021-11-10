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
		if (!count($this->_auth_sections) || !count($this->_sections)) {
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
			'hasTrash' => $this->hasTrash($map),
			'list_fields' => $this->getListFields($map),
			'id' => $this->request->get('id'),
			'item' => $this->getCurrentItem(),
			'item_identifier' => $this->getIdentifier($map),
			'is_entity_uploadable' => $this->isUploadable($map),
			// 'list_filters_form' => $this->getFiltersForm($map)->createView(),
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
			'relation' => $relation['association'],
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
			// 'list_filters_form' => $this->getFiltersForm($relation),
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
		if ($relAction === 'bridge') {
			$relAction = ( $this->getRelationDefaultAction($map, $relation['association']) === 'show' ? 'set' : 'add' );
		}
		return array_merge($this->getDefaultRelationParams($map,$relation),array(
			'fields' => $this->getFields($bridge),
			'list_fields' => $this->getListFields($bridge),
			'relation_action_label' => ($this->generateLabel($relAction) . ' ' . $bridge['label']),
			'relation_action' => $relAction,
			'template' => $this->getTemplate($bridge,('relations_'.$relAction)),
			'uploader' => ($this->isUploadable($bridge) ? $this->generateUrl('maci_admin_view', array(
				'section'=>$map['section'],'entity'=>$map['name'],
				'action'=>'relations','id'=>$this->request->get('id'),
				'relation'=>$relation['association'],'relAction'=>'uploader',
				'bridge'=>$bridge['bridge']
			)) : false)
		));
	}

	public function getDefaultRedirectParams($opt = [])
	{
		return array(
			'redirect' => 'maci_admin_view',
			'redirect_params' => $opt
		);
	}

	public function getDefaultEntityRedirectParams($map, $action = 'list', $id = null, $opt = [])
	{
		if (in_array($action, $this->getSingleActions($map)) || ($this->hasTrash($map) && $action == 'trash')) {
			$id = $id === null ? $this->request->get('id', null) : $id;
		} else {
			$id = null;
		}
		$params = $this->getDefaultRedirectParams();
		$params['redirect_params'] = array_merge($params['redirect_params'], array(
			'section' => $map['section'],
			'entity' => $map['name'],
			'action' => $action,
			'id' => $id
		), $opt);
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
		$params['redirect_params'] = array_merge($params['redirect_params'], array(
			'relation' => $relation['association'],
			'relAction' => $action
		), $opt);
		return $params;
	}

	public function getDefaultBridgeRedirectParams($map, $relation, $bridge, $action = false, $id = null, $opt = [])
	{
		$id = $id === null ? $this->request->get('id', null) : $id;
		if ($id === null) {
			return $this->getDefaultEntityRedirectParams($map);
		}
		$params = $this->getDefaultRelationRedirectParams($map, $relation, 'bridge', $id);
		$params['redirect_params'] = array_merge($params['redirect_params'], array(
			'bridge' => $bridge['name']
		), $opt);
		return $params;
	}

	public function getDefaultUrl($opt = [])
	{
		$action_params = $this->getDefaultRedirectParams($opt);
		return $this->generateUrl($action_params['redirect'], $action_params['redirect_params']);
	}

	public function getEntityUrl($map, $action = 'list', $id = null, $opt = [])
	{
		$action_params = $this->getDefaultEntityRedirectParams($map, $action, $id, $opt);
		return $this->generateUrl($action_params['redirect'], $action_params['redirect_params']);
	}

	public function getRelationUrl($map, $relation, $action = false, $id = null, $opt = [])
	{
		$action_params = $this->getDefaultRelationRedirectParams($map, $relation, $action, $id, $opt);
		return $this->generateUrl($action_params['redirect'], $action_params['redirect_params']);
	}

	public function getBridgeUrl($map, $relation, $bridge, $action = false, $id = null, $opt = [])
	{
		$action_params = $this->getDefaultBridgeRedirectParams($map, $relation, $bridge, $action, $id, $opt);
		return $this->generateUrl($action_params['redirect'], $action_params['redirect_params']);
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

	public function getSettingsActions($entity)
	{
		$actions = [];
		if(in_array('list', $this->getMainActions($entity))) {
			// $actions[] = 'setListFilters';
			$actions[] = 'setPagerOptions';
		}
		return $actions;
	}

	public function getActions($entity)
	{
		return array_merge(
			$this->getMainActions($entity),
			$this->getSingleActions($entity),
			$this->getMultipleActions($entity),
			$this->getSettingsActions($entity)
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

	public function getAssociationMetadata($map, $association)
	{
		$metadata = $this->getMetadata($map);

		if (array_key_exists($association, $metadata->associationMappings)) {
			return $metadata->associationMappings[$association];
		}

		return false;
	}

	public function getBridge($relation, $association)
	{
		$bridge = $this->getRelation($relation, $association);
		if (!$bridge) return false;
		$bridge['bridge'] = $association;
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
		if (!$getter) {
			$this->session->getFlashBag()->add('error', 'Getter Method for ' . $field . ' in ' . get_class($object) . ' not found.');
			return false;
		}
		return call_user_func_array(array($object,$getter),[]);
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

	public function getFiltersForm($entity)
	{
		$form = $entity['name'];
		$object = $this->getNewItem($entity);
		$filters = $this->getEntityFilters($entity);
		foreach ($filters as $field => $filter) {
			$method = $this->getSetterMethod($object, $field);
			if ( $method ) {
				call_user_func_array(array($object, $method), array($filter));
			}
		}
		return $this->generateForm($entity, $object, true);
	}

	public function getForm($map, $object = false)
	{
		$form = $map['name'];
		if (array_key_exists('form', $map)) {
			$form = $map['form'];
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

	public function generateForm($map, $object = false, $isFilterForm = false)
	{
		if (!$object) {
			$object = $this->getNewItem($map);
		}

		$fields = $this->getFields($map);
		$form = $this->createFormBuilder($object);
		$id = $this->getIdentifierValue($map, $object);
		$isNew = !$id;

		// if ($isFilterForm) {
		//     $form->setAction($this->generateUrl('maci_admin_view', array(
		//         'section'=>$map['section'], 'entity'=>$map['name'], 'action'=>'set_filters'
		//     )));
		// }
		// else

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

			// if ($isFilterForm) {
			//     $form->add($field . '_checkbox', CheckboxType::class, array(
			//         'label' => 'Set Filter',
			//         'attr' => ['class' => 'setFilterCheckbox'],
			//         'required' => false,
			//         'mapped' => false
			//     ));
			// }

			// if ($isFilterForm && in_array(
			//     $this->getFieldType($map, $field),
			//     ['text', 'string', 'decimal', 'integer', 'boolean', 'datetime']
			// )) {
			//     $form->add($field . '_method', ChoiceType::class, array(
			//         'label' => 'Method',
			//         'choices' => array('Is' => 'IS', 'Like' => 'LIKE'),
			//         'mapped' => false
			//     ));
			// }
			// else { vvv }
			
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

		if ($isFilterForm) {
			$form->add('set_filters', SubmitType::class, array(
				'label'=>'Set Filters',
				'attr'=>array('class'=>'btn btn-primary')
			));
		} else if ($isNew) {
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

	public function setItem($map, $item, $data, $setters = false)
	{
		if (!$setters) $setters = $this->getSetters($map);
		foreach ($data as $field => $value) {
			$setter = $this->getSetterMethod($item, $field);
			if (!$setter) {
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
		$repo = $this->getRepository($map);
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

	public function getList($map, $trashValue = null, $filters = false)
	{
		$repo = $this->getRepository($map);
		$query = $repo->createQueryBuilder('e');
		$root = $query->getRootAlias();
		$this->addDefaultQueries($map, $query, $trashValue, $filters);
		$query = $query->getQuery();
		$list = $query->getResult();
		return $list;
	}

	public function getListForRelation($map, $relation, $item, $bridge = false)
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
		$index = 0;
		foreach ($relation_items as $obj) {
			if (is_object($obj)) {
				$parameter = ':id_' . $index;
				$query->andWhere($root . '.' . $inverseField . ' != ' . $parameter);
				$query->setParameter($parameter, call_user_func_array(array($obj, ('get'.ucfirst($inverseField))), []));
				$index++;
			}
		}
		// todo: an option fot this
		if ($this->getClass($map) === $this->getClass($relation) && !$bridge) {
			$query->andWhere($root . '.' . $inverseField . ' != :pid');
			$query->setParameter(':pid', call_user_func_array(array($item, ('get'.ucfirst($inverseField))), []));
		}
		$this->addDefaultQueries($mainMap, $query, false);
		$query = $query->getQuery();
		return $query->getResult();
	}

	public function getListData($map, $trashValue = false, $fields = false, $filters = false)
	{
		$list = $this->getList($map, $trashValue, $filters);
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
		if (is_object($getted)) {
			if (is_array($getted) || get_class($getted) === 'Doctrine\ORM\PersistentCollection') {
				return $getted;
			} else {
				return array($getted);
			}
		}
		return [];
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

	public function getRepository($map)
	{
		return $this->om->getRepository($map['class']);
	}

	public function isSortable($map)
	{
		return ( $this->getConfigKey($map, 'sortable') && in_array($this->getConfigKey($map, 'sort_field'), $this->getFields($map)) );
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

	public function getPager($map, $list, $opt = [])
	{
		$pager = new MaciPager($list, $this->request->get('page', 1), $this->getPager_PageLimit($map), $this->getPager_PageRange($map));
		$pager->setForm($this->getPagerForm($map, $pager, $opt));
		$pager->setIdentifiers($this->getListIdentifiers($map, $list));
		return $pager;
	}

	public function getPagerForm($map, $pager = false, $opt = [])
	{
		$options = array(
			'page' => $this->request->get('page', 1),
			'page_limit' => $this->getPager_PageLimit($map),
			'order_by_field' => $this->getPager_OrderByField($map),
			'order_by_sort' => $this->getPager_OrderBySort($map)
		);
		$page_attr = $pager ? array('max' => $pager->getMaxPages()) : [];
		return $this->createFormBuilder($options)
			->setAction($this->getEntityUrl($map, 'setPagerOptions', null, $opt))
			->add('page', IntegerType::class, array(
				'label' => 'Page:',
				'attr' => $page_attr
			))
			->add('page_limit', IntegerType::class, array(
				'label' => 'Items per Page:'
			))
			->add('order_by_field', ChoiceType::class, array(
				'label' => 'Order By:',
				'choices' => $this->getArrayWithLabels($this->getFields($map, false))
			))
			->add('order_by_sort', ChoiceType::class, array(
				'label' => 'Sort',
				'label_attr' => array(
					'class' => 'sr-only'
				),
				'choices' => array('Asc' => 'ASC', 'Desc' => 'DESC')
			))
			->add('set', SubmitType::class,  array(
				'attr' => array(
					'class' => 'btn btn-primary'
				)
			))
			->getForm()
		;
	}

	public function getPager_PageLimit($map)
	{
		return $this->session->get(('maci_admin.' . $map['section'] . '.' . $map['name'] . '.page_limit'), (array_key_exists('page_limit', $map) ? $map['page_limit'] : $this->_defaults['page_limit']));
	}

	public function setPager_PageLimit($map, $value)
	{
		return $this->session->set(('maci_admin.' . $map['section'] . '.' . $map['name'] . '.page_limit'), $value);
	}

	public function getPager_PageRange($map)
	{
		return $this->session->get(('maci_admin.' . $map['section'] . '.' . $map['name'] . '.page_range'), (array_key_exists('page_range', $map) ? $map['page_range'] : $this->_defaults['page_range']));
	}

	public function setPager_PageRange($map)
	{
		return $this->session->set(('maci_admin.' . $map['section'] . '.' . $map['name'] . '.page_range'), $value);
	}

	public function getPager_OrderByField($map)
	{
		return $this->session->get(('maci_admin.' . $map['section'] . '.' . $map['name'] . '.order_by_field'), $this->getIdentifier($map));
	}

	public function setPager_OrderByField($map, $value)
	{
		return $this->session->set(('maci_admin.' . $map['section'] . '.' . $map['name'] . '.order_by_field'), $value);
	}

	public function getPager_OrderBySort($map)
	{
		return $this->session->get(('maci_admin.' . $map['section'] . '.' . $map['name'] . '.order_by_sort'), 'DESC');
	}

	public function setPager_OrderBySort($map, $value)
	{
		return $this->session->set(('maci_admin.' . $map['section'] . '.' . $map['name'] . '.order_by_sort'), $value);
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
		call_user_func_array(array($item, $manager), array((strpos($managerMethod, 'Remover') !== false && !$this->getRemoverMethod($item, $field)) ? null : $obj));
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
			array_key_exists('trash', $data) ? $data['trash'] : false,
			array_key_exists('fields', $data) ? $data['fields'] : false,
			array_key_exists('filters', $data) ? $data['filters'] : false
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

	public function newItemByParams($data)
	{
		if (!array_key_exists('section', $data) || !array_key_exists('entity', $data) || !array_key_exists('data', $data)) {
			return ['success' => false, 'error' => 'Bad Request.'];
		}
		$entity = $this->getEntity($data['section'], $data['entity']);
		if (!$entity) {
			return ['success' => false, 'error' => 'Entity "' . $data['entity'] . '" not Found.'];
		}
		$item = $this->getNewItem($entity);
		if (!$item) {
			return ['success' => false, 'error' => 'Item "' . $data['id'] . '" for entity "' . $entity['label'] . '" not Found.'];
		}
		$this->setItem($entity, $item, $data['data']);
		$this->om->persist($item);
		$this->om->flush();
		return ['success' => true, 'id' => $this->getIdentifierValue($entity, $item)];
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
		$this->addRelationItems($entity, $relation, $item, $this->selectItems($relation,$this->getListForRelation($entity, $relation, $item),$data['ids']));
		$this->om->flush();
		return ['success' => true];
	}

	/*
		------------> Queries
	*/

	public function addDefaultQueries($map, &$query, $trashValue = null, $filters = false)
	{
		if ($filters) $query = $this->addFiltersQuery($map, $query, $filters);
		$query = $this->addSearchQuery($map, $query);
		$query = $this->addTrashQuery($map, $query, $trashValue);
		$query = $this->addOrderByQuery($map, $query);

		return $query;
	}

	public function addFiltersQuery($map, &$query, $filters)
	{
		$fields = $this->getFields($map);
		foreach ($filters as $key => $data) {
			if (!in_array($key, $fields)) {
				continue;
			}
			if (!is_array($data)) {
				$value = $data;
				$op = '=';
			} else {
				if(!array_key_exists('val', $data)) continue;
				if($data['val'] == "") $value = null;
				if(!array_key_exists('op', $data)) $op = 'LIKE';
			}
			$query->andWhere($query->getRootAlias() . '.' . $key . ' ' . $op . ' :' . $key);
			$query->setParameter(':' . $key, $value);
		}
		return $query;
	}

	public function addSearchQuery($map, &$query)
	{
		$search = $this->request->get('s', false);
		if ($search) {
			$stringFields = $this->getFieldsByType($map);
			foreach ($stringFields as $field) {
				$query->orWhere($query->getRootAlias() . '.' . $field . ' LIKE :search');
			}
			$query->setParameter('search', "%$search%");
		}
		return $query;
	}

	public function addTrashQuery($map, $query, $trashValue = null)
	{
		if ($trashValue !== false && $trashValue !== true) {
			return $query;
		}
		if ($this->hasTrash($map)) {
			$fields = $this->getFields($map);
			$trashAttr = $this->getTrashField($map);
			if ( in_array($trashAttr, $fields) ) {
				$query->andWhere($query->getRootAlias() . '.' . $trashAttr . ' = :' . $trashAttr);
				$query->setParameter(':' . $trashAttr, $trashValue);
			}
		}
		return $query;
	}

	public function addOrderByQuery($map, $query)
	{
		$query->orderBy($query->getRootAlias() . '.' . $this->getPager_OrderByField($map), $this->getPager_OrderBySort($map));
		return $query;
	}

	/*
		------------> Utils
	*/

	public function getArrayWithLabels($array)
	{
		$list = [];
		foreach ($array as $item) {
			$list[$this->generateLabel($item)] = $item;
		}
		return $list;
	}

	public function getCamel($str)
	{
		return Container::camelize($str);
	}

	public function generateLabel($str)
	{
		return ucwords(str_replace('_', ' ', $str));
	}

	public function createForm($type, $data = null, array $options = [])
	{
		return $this->formFactory->create($type, $data, $options);
	}

	public function createFormBuilder($data = null, array $options = [])
	{
		return $this->formFactory->createBuilder(FormType::class, $data, $options);
	}

	public function generateUrl($route, $parameters = [], $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
	{
		return $this->router->generate($route, $parameters, $referenceType);
	}

	/*
		------------> CODE IN PAUSE O_O ( Filters )
	*/

	public function getEntityFilters($entity)
	{
		return $this->session->get('admin_filters_'.$entity['name'], []);
	}

	public function hasEntityFilters($entity)
	{
		return !!count($this->getEntityFilters($entity));
	}

	public function setEntityFilters($entity, $filters)
	{
		$this->session->set('admin_filters_'.$entity['name'], $filters);
	}

	public function removeEntityFilters($entity)
	{
		$this->session->set('admin_filters_'.$entity['name'], []);
	}

	public function getEntityFilterFields($entity)
	{
		if (array_key_exists('filters', $entity) && count($entity['filters'])) {
			return $entity['filters'];
		}
		return [];
	}

	public function hasEntityFilterFields($entity)
	{
		if (array_key_exists('filters', $entity) && count($entity['filters'])) {
			return true;
		}
		return false;
	}

}
