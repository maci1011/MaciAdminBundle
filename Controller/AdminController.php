<?php

namespace Maci\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Bundle\TwigBundle\TwigEngine;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

use Maci\AdminBundle\MaciPager;

class AdminController
{
    private $_sections;

    private $_list;

    private $_section_list;

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
        \AppKernel $kernel,
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

        $this->initConfig();
    }

/*
    ------------> Config Functions
*/

    // Init _sections and _auth_sections
    private function initConfig()
    {
        // $this->_auth_sections = $this->session->get('maci_admin._auth_sections');
        // $this->_sections = $this->session->get('maci_admin._sections');
        // $this->_defaults = $this->session->get('maci_admin._defaults');
        // if (is_array($this->_auth_sections)) return;

        // Authorized Sections
        $this->_auth_sections = [];
        // Sections
        $this->_sections = [];

        if (!array_key_exists('sections', $this->config)) return;

        $this->_defaults = [
            'actions'       => [
                'list' => ['template' => 'MaciAdminBundle:Actions:list.html.twig'],
                'new' => ['template' => 'MaciAdminBundle:Actions:new.html.twig'],
                'trash' => ['template' => 'MaciAdminBundle:Actions:trash.html.twig'],
                'uploader' => ['template' => 'MaciAdminBundle:Actions:uploader.html.twig'],
                'show' => ['template' => 'MaciAdminBundle:Actions:show.html.twig'],
                'edit' => ['template' => 'MaciAdminBundle:Actions:edit.html.twig'],
                'remove' => ['template' => 'MaciAdminBundle:Actions:remove.html.twig'],
                'relations' => ['template' => 'MaciAdminBundle:Actions:relations.html.twig'],
                'relations_add' => ['template' => 'MaciAdminBundle:Actions:relations_add.html.twig'],
                'relations_list' => ['template' => 'MaciAdminBundle:Actions:relations_list.html.twig'],
                'relations_remove' => ['template' => 'MaciAdminBundle:Actions:relations_remove.html.twig'],
                'relations_set' => ['template' => 'MaciAdminBundle:Actions:relations_set.html.twig'],
                'relations_show' => ['template' => 'MaciAdminBundle:Actions:relations_show.html.twig'],
                'relations_uploader' => ['template' => 'MaciAdminBundle:Actions:relations_uploader.html.twig']
            ],
            'controller'    => 'maci.admin.default',
            'enabled'       => true,
            'page_limit'    => 100,
            'page_range'    => 2,
            'roles'         => ['ROLE_ADMIN'],
            'sortable'      => false,
            'sort_field'    => 'position',
            'trash'         => true,
            'trash_field'   => 'removed',
            'uploadable'    => true,
            'upload_field'  => 'file',
            'upload_path_field'  => 'path'
        ];

        $this->_defaults = $this->getDefaultConfig(['config' => (array_key_exists('config', $this->config) ? $this->config['config'] : [])], $this->_defaults);

        foreach ($this->config['sections'] as $name => $section) {
            if (!array_key_exists('entities', $section) || !count($section['entities'])) continue;
            // Section Settings
            $section_config = $this->getDefaultConfig($section, $this->_defaults);
            $section['authorized'] = false;
            foreach ($section_config['roles'] as $role) {
                if ($this->authorizationChecker->isGranted($role)) {
                    $section['authorized'] = true;
                    break;
                }
            }
            // Section Entities
            $entities = []; 
            foreach ($section['entities'] as $entity_name => $entity) {
                $entity_config = $this->getDefaultConfig($entity, $section_config);
                $entity['authorized'] = false;
                foreach ($entity_config['roles'] as $role) {
                    if ($this->authorizationChecker->isGranted($role)) {
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
                if (!array_key_exists('label', $entity)) {
                    $entity['label'] = $this->generateLabel($entity_name);
                }
                // Add Entity
                $entities[$entity_name] = $entity;
            }
            if (!$section['authorized'] || !count($entities)) continue;
            //  section properties
            $section['name'] = $name;
            $section['entities'] = $entities;
            if (!array_key_exists('label', $section)) {
                $section['label'] = $this->generateLabel($name);
            }
            // Add Section
            $this->_auth_sections[] = $section['name'];
            $this->_sections[$name] = $section;
        }
        $this->session->set('maci_admin._auth_sections', $this->_auth_sections);
        $this->session->set('maci_admin._sections', $this->_sections);
        $this->session->set('maci_admin._defaults', $this->_defaults);
    }

    public function getDefaultConfig($map, $defaults)
    {
        if (array_key_exists('config', $map)) {
            $config = array_merge($defaults, $map['config']);
            if (!count($config['roles'])) $config['roles'] = $defaults['roles'];
            if (!count($config['actions'])) $config['actions'] = $defaults['actions'];
            return $config;
        }
        return $defaults;
    }

    public function getConfig($map)
    {
        $config = $this->_defaults;
        if (!$map) {
            return $config;
        } else if (array_key_exists('entity_root', $map)) {
            $config = $this->getConfig($this->getEntity($map['entity_root'], $map['entity_root_section']));
        } else if (array_key_exists('section', $map)) {
            $config = $this->getSectionConfig($map['section']);
        }
        if (array_key_exists('config', $map)) {
            return $this->getDefaultConfig($map, $config);
        }
        return $config;
    }

    public function getConfigKey($map, $key)
    {
        return $this->getConfig($map)[$key];
    }

    public function getController($map, $action)
    {
        if (array_key_exists('config', $map) &&
            array_key_exists('actions', $map['config']['actions']) &&
            array_key_exists($action, $map['config']['actions']) &&
            $this->templating->exists($map['config']['actions'][$action]['controller']) ) {
            return $map['config']['actions'][$action]['controller'];
        }
        if (array_key_exists($action, $this->_defaults['actions']) &&
            array_key_exists('controller', $this->_defaults['actions'][$action]) &&
            $this->templating->exists($this->_defaults['actions'][$action]['controller']) ) {
            return $this->_defaults['actions'][$action]['controller'];
        }
        return $this->_defaults['controller'];
    }

    public function getTemplate($map, $action)
    {
        if (array_key_exists('config', $map) &&
            array_key_exists('actions', $map['config']['actions']) &&
            array_key_exists($action, $map['config']['actions']) &&
            $this->templating->exists($map['config']['actions'][$action]['template']) ) {
            return $map['config']['actions'][$action]['template'];
        }
        $bundleName = $this->getBundleName($map);
        $template = $bundleName . ':Mcm' . $this->getCamel($map['name']) . ':_' . $action . '.html.twig';
        if ( $this->templating->exists($template) ) {
            return $template;
        }
        $template = $bundleName . ':Mcm:_' . $action . '.html.twig';
        if ( $this->templating->exists($template) ) {
            return $template;
        }
        if (array_key_exists($action, $this->_defaults['actions']) &&
            $this->templating->exists($this->_defaults['actions'][$action]['template']) ) {
            return $this->_defaults['actions'][$action]['template'];
        }
        return 'MaciAdminBundle:Actions:template_not_found.html.twig';
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
        return in_array($section, $this->getAuthSections());
    }

    public function getSection($section)
    {
        if ($this->hasSection($section)) {
            return $this->_sections[$section];
        }
        return false;
    }

    public function hasSection($section)
    {
        return array_key_exists($section, $this->_sections);
    }

    public function getSectionConfig($section)
    {
        if (array_key_exists($section, $this->_sections)) {
            return $this->getDefaultConfig($this->_sections[$section], $this->_defaults);
        }
        return false;
    }

    public function getSectionLabel($section)
    {
        if (array_key_exists($section, $this->_sections)) {
            return $this->_sections[$section]['label'];
        }
        return false;
    }

    public function getSectionDashboard($section)
    {
        if ($this->hasSectionDashboard($section)) {
            return $this->_sections[$section]['dashboard'];
        }
        return false;
    }

    public function hasSectionDashboard($section)
    {
        if (array_key_exists($section, $this->_sections) && array_key_exists('dashboard', $this->_sections[$section])) {
            return $this->templating->exists($this->_sections[$section]['dashboard']);
        }
        return false;
    }

    public function getEntities($section)
    {
        if ($this->hasEntities($section)) {
            return $this->_sections[$section]['entities'];
        }
        return false;
    }

    public function hasEntities($section)
    {
        if (array_key_exists($section, $this->_sections)) {
            return (bool) count($this->_sections[$section]['entities']);
        }
        return false;
    }

    public function getEntity($section, $entity)
    {
        if ($this->hasEntity($section, $entity)) {
            return $this->_sections[$section]['entities'][$entity];
        }
        return false;
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
            $sections = array_merge(array($pref_section), $sections);
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

/*
    ------------> Defaults Map, Params and Urls
*/

    public function getDefaultMap()
    {
        $map = array();
        // $map['class'] = null;
        // $map['label'] = null;
        // $map['name'] = null;
        // $map['section'] = null;
        $map['list'] = array();
        $map['filters'] = array();
        $map['config'] = $this->_defaults;
        return $map;
    }

    public function getDefaultParams($section = false)
    {
        if (!$section) {
            $section = $this->getCurrentSection();
        }
        return array(
            'section' => $section,
            'section_label' => $this->getSectionLabel($section),
            'section_has_dashboard' => $this->hasSectionDashboard($section)
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
            'fields' => $this->getListFields($map),
            'id' => $this->request->get('id'),
            'item' => $this->getCurrentItem(),
            'is_entity_uploadable' => $this->isUploadable($map),
            'list_filters_form' => $this->getFiltersForm($map)->createView(),
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
            'fields' => $this->getListFields($relation),
            'relation' => $relation['association'],
            'relation_label' => $relation['label'],
            'relation_section' => $relation['section'],
            'relation_entity' => $relation['name'],
            'relation_action' => $relAction,
            'relation_action_label' => $this->generateLabel($relAction),
            'bridges' => $this->getBridges($relation),
            'uploadable_bridges' => $this->getUpladableBridges($relation),
            'is_relation_uploadable' => $this->isUploadable($relation),
            'list_filters_form' => $this->getFiltersForm($relation),
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
            'fields' => $this->getListFields($bridge),
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

    public function getDefaultRedirectParams($opt = array())
    {
        return array(
            'redirect' => 'maci_admin_view',
            'redirect_params' => $opt
        );
    }

    public function getDefaultEntityRedirectParams($map, $action = 'list', $id = null, $opt = array())
    {
        if (in_array($action, $this->getSingleActions($map))) {
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

    public function getDefaultRelationRedirectParams($map, $relation, $action = false, $id = null, $opt = array())
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

    public function getDefaultBridgeRedirectParams($map, $relation, $bridge, $action = false, $id = null, $opt = array())
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

    public function getDefaultUrl($opt = array())
    {
        $action_params = $this->getDefaultRedirectParams($opt);
        return $this->generateUrl($action_params['redirect'], $action_params['redirect_params']);
    }

    public function getEntityUrl($map, $action = 'list', $id = null, $opt = array())
    {
        $action_params = $this->getDefaultEntityRedirectParams($map, $action, $id, $opt);
        return $this->generateUrl($action_params['redirect'], $action_params['redirect_params']);
    }

    public function getRelationUrl($map, $relation, $action = false, $id = null, $opt = array())
    {
        $action_params = $this->getDefaultRelationRedirectParams($map, $relation, $action, $id, $opt);
        return $this->generateUrl($action_params['redirect'], $action_params['redirect_params']);
    }

    public function getBridgeUrl($map, $relation, $bridge, $action = false, $id = null, $opt = array())
    {
        $action_params = $this->getDefaultBridgeRedirectParams($map, $relation, $bridge, $action, $id, $opt);
        return $this->generateUrl($action_params['redirect'], $action_params['redirect_params']);
    }

/*
    ------------> Current Route Getters
*/

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

    public function getCurrentEntity()
    {
        if (isset($this->current_entity)) return $this->current_entity;
        $section = $this->getCurrentSection();
        if (!$section) return false;
        $_entity = $this->request->get('entity');
        if (!$_entity) return false;
        $entity = $this->getEntity($section, $_entity);
        if ($entity) {
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
        $id = (int) $this->request->get('id');
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

    public function getCurrentRelation()
    {
        if (isset($this->current_relation)) return $this->current_relation;
        $entity = $this->getCurrentEntity();
        if (!$entity) return false;
        $_relation = $this->request->get('relation');
        if (!$_relation) return false;
        $relation = $this->getRelation($entity, $_relation);
        if ($relation) {
            $this->current_relation = $relation;
            return $relation;
        }
        $this->current_relation = false;
        $this->session->getFlashBag()->add('error', 'Relation [' . $_relation . '] for [' . $entity['label'] . '] not found.');
        return false;
    }

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
        return array('show', 'edit', 'relations', 'remove');
    }

    public function getMultipleActions($entity)
    {
        return array('reorder');
    }

    public function getSettingsActions($entity)
    {
        $actions = array();
        if(in_array('list', $this->getMainActions($entity))) {
            $actions[] = 'setListFilters';
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
        return array('list', 'show', 'add', 'set', 'bridge', 'remove', 'uploader', 'reorder');
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

        $associations = array();

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
        $bridge['bridge'] = $association;
        return $bridge;
    }

    public function getBridges($map)
    {
        if (!array_key_exists('bridges', $map)) {
            return array();
        }
        return $map['bridges'];
    }

    public function getUpladableBridges($map)
    {
        if (!array_key_exists('bridges', $map)) {
            return array();
        }
        $upb = array();
        foreach ($map['bridges'] as $bridge) {
            $bm = $this->getBridge($map,$bridge);
            if ($this->isUploadable($bm)) {
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
        return $this->getBundle($map)->getName();
    }

    public function getBundleNamespace($map)
    {
        return $this->getBundle($map)->getNamespace();
    }

    public function getClass($map)
    {
        if (class_exists($map['class'])) return $map['class'];
        $repo = $this->getRepository($map);
        if ($repo) return $repo->getClassName();
        return false;
    }

    public function getFields($map, $ri = true)
    {
        $metadata = $this->getMetadata($map);
        $fields = (array) $metadata->fieldNames;
        // Remove the primary key field if it's not managed manually
        if ($ri && !$metadata->isIdentifierNatural()) {
            $fields = array_diff($fields, $metadata->identifier);
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
        return call_user_func_array(array($object,$getter),array());
    }

    public function getListFields($map)
    {
        $object = $this->getNewItem($map);
        $list = [$this->getIdentifier($map)];
        if (array_key_exists('list', $map) && count($map['list'])) {
            $list = array_merge($list, $map['list']);
            $field = $this->getConfigKey($map, 'sort_field');
            if ($this->isSortable($map) && !in_array($field, $list)) $list[] = $field;
            return array_unique($list, SORT_REGULAR);
        }
        if (method_exists($object, 'getPreview')) {
            $list[] = '_preview';
        }
        $fields = array_keys($this->getFields($map));
        foreach ($fields as $field) {
            if ($field == $map['trash_field']) continue;
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
            return $this->createForm(new $form, $object);
        }
        $form = ( $this->getBundleNamespace($map) . "\\Form\\Mcm" . $this->getCamel($map['section']) . "\\" . $this->getCamel($map['name']) . "Type" );
        if (class_exists($form)) {
            return $this->createForm($form, $object);
        }
        $form = ( $this->getBundleNamespace($map) . "\\Form\\Mcm\\" . $this->getCamel($map['name']) . "Type" );
        if (class_exists($form)) {
            return $this->createForm($form, $object);
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
        $isNew = !$object->getId();

        if ($isFilterForm) {
            $form->setAction($this->generateUrl('maci_admin_view', array(
                'section'=>$map['section'], 'entity'=>$map['name'], 'action'=>'set_filters'
            )));
        }
        else if ($isNew) {
            $form->setAction($this->generateUrl('maci_admin_view', array(
                'section'=>$map['section'], 'entity'=>$map['name'], 'action'=>'new'
            )));
        } else {
            $form->setAction($this->generateUrl('maci_admin_view', array(
                'section'=>$map['section'], 'entity'=>$map['name'], 'action'=>'edit', 'id'=>$object->getId()
            )));
        }

        $isUploadable = $this->isUploadable($map);
        $upload_path_field = $this->getConfigKey($map,'upload_path_field');

        foreach ($fields as $field) {
            if ($this->hasTrash($map) && $field === $this->getConfigKey($map,'trash_field')) {
                continue;
            }
            if ($isFilterForm) {
                $form->add($field . '_checkbox', CheckboxType::class, array(
                    'label' => 'Set Filter',
                    'attr' => ['class' => 'setFilterCheckbox'],
                    'required' => false,
                    'mapped' => false
                ));
            }
            $method = ('get' . ucfirst($field) . 'Array');
            if ( method_exists($object, $method) ) {
                $form->add($field, ChoiceType::class, array(
                    'empty_data' => '',
                    'choices' => call_user_func_array(array($object, $method), array())
                ));
            } else if ( $isUploadable && $field === $upload_path_field ) {
                $form->add('file', FileType::class, array('required' => false));
            } else {
                if ($isFilterForm && in_array($this->getFieldType($map, $field), ['string', 'text'])) {
                    $form->add($field . '_method', ChoiceType::class, array(
                        'label' => 'Method',
                        'choices' => array('Is' => 'IS', 'Like' => 'LIKE'),
                        'mapped' => false
                    ));
                }
                $form->add($field);
            }
        }

        $form->add('reset', ResetType::class, array(
            'label'=>'Reset Form'
        ));

        if ($isFilterForm) {
            $form->add('set_filters', SubmitType::class, array(
                'label'=>'Set Filters',
                'attr'=>array('class'=>'btn btn-primary')
            ));
        } else if ($isNew) {
            $form->add('save', SubmitType::class, array(
                'label'=>'Save & Edit Item',
                'attr'=>array('class'=>'btn btn-success')
            ));
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
        }

        return $form->getForm();
    }

    public function getIdentifier($map)
    {
        $identifiers = $this->getMetadata($map)->getIdentifier();
        return $identifiers[0];
    }

    public function getItem($map, $id)
    {
        return $this->getRepository($map)->findOneById($id);
    }

    public function removeItems($map, $list)
    {
        if (!count($list)) return false;
        foreach ($list as $item) {
            if (method_exists($item, 'setRemoved')) {
                $item->setRemoved(true);
                $this->session->getFlashBag()->add('success', 'Item [' . $item . '] for [' . $map['label'] . '] moved to trash.');
            } else {
                $this->om->remove($item);
                $this->session->getFlashBag()->add('success', 'Item [' . $item . '] for [' . $map['label'] . '] removed.');
            }
        }
        $this->om->flush();
        return true;
    }

    public function getItems($map, $trashValue = false)
    {
        $repo = $this->getRepository($map);
        $query = $repo->createQueryBuilder('e');
        $root = $query->getRootAlias();
        $fields = $this->getFields($map);
        $query = $this->addDefaultQueries($map, $query, $trashValue);
        $query = $query->getQuery();
        $list = $query->getResult();
        return $list;
    }

    public function getItemsForRelation($map, $relation, $item, $bridge = false)
    {
        $relation_items = $this->getRelationItems($relation, $item);
        $inverseField = $this->getRelationInverseField($map, $relation);
        $mainMap = $relation;
        if ($bridge) {
            $bridge_items = array();
            foreach ($relation_items as $obj) {
                $brcall = call_user_func_array(array($obj, $this->getGetterMethod($obj, $bridge['bridge'])), array());
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
            $parameter = ':id_' . $index;
            $query->andWhere($root . '.' . $inverseField . ' != ' . $parameter);
            $query->setParameter($parameter, call_user_func_array(array($obj, ('get'.ucfirst($inverseField))), array()));
            $index++;
        }
        // todo: an option fot this
        if ($this->getClass($map) === $this->getClass($relation) && !$bridge) {
            $query->andWhere($root . '.' . $inverseField . ' != :pid');
            $query->setParameter(':pid', call_user_func_array(array($item, ('get'.ucfirst($inverseField))), array()));
        }
        $query = $this->addDefaultQueries($mainMap, $query);
        $query = $query->getQuery();
        return $query->getResult();
    }

    public function selectItemsFromRequestIds($map, $list)
    {
        $ids = explode(',', $this->request->get('ids', ''));
        if (!count($ids)) {
            return false;
        }
        $obj_list = array();
        $ids_list = $this->getListIds($list);
        $repo = $this->getRepository($map);
        foreach ($ids as $_id) {
            $_id = (int) $_id;
            if (!$_id) {
                continue;
            }
            if (!in_array($_id, $ids_list)) {
                $this->session->getFlashBag()->add('error', 'Item [' . $_id . '] for ' . $this->getClass($map) . ' not found.');
                continue;
            }
            $obj_list[] = $list[array_search($_id, $ids_list)];
        }
        return $obj_list;
    }

    public function getMetadata($map)
    {
        return $this->om->getClassMetadata( $this->getClass($map) );
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
        $map['label'] = $this->generateLabel( $metadata['fieldName'] );
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
        $relation = $this->getNewMap($metadata);
        if (array_key_exists('relations', $map) && array_key_exists($association, $map['relations'])) {
            $relation = array_merge($relation, $map['relations'][$association]);
        }
        $entity = $this->getEntityByClass($this->getClass($relation), $map['section']);
        if ($entity) {
            $relation = array_merge($entity, $relation);
            foreach ($relation as $key => $value) {
                if (is_array($relation[$key]) && !count($relation[$key])) $relation[$key] = $entity[$key];
            }
            if (!$this->isAuthorized($relation)) {
                return false;
            }
        }
        array_unique($relation, SORT_REGULAR);
        $relation['entity_root_section'] = $entity['section'];
        $relation['entity_root'] = $entity['name'];
        $relation['association'] = $association;
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
        return array();
    }

    public function getRemoveForm($map, $item, $opt = array())
    {
        return $this->createFormBuilder($item)
            ->setAction($this->getEntityUrl($map, 'remove', $item->getId(), $opt))
            ->add('remove', SubmitType::class, array(
                'attr' => array('class' => 'btn-danger')
            ))
            ->getForm()
        ;
    }

    public function getRelationRemoveForm($map, $relation, $item, $opt = array())
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
        if ($this->getConfigKey($map, 'trash') && in_array($this->getConfigKey($map, 'trash_field'), $this->getFields($map))) {
            return true;
        }
        return false;
    }

    public function isUploadable($map)
    {
        if ($this->getConfigKey($map, 'uploadable') && $this->getSetterMethod($this->getNewItem($map), $this->getConfigKey($map, 'upload_field'))) {
            return true;
        }
        return false;
    }

/*
    ------------> Pager
*/

    public function getPager($map, $list, $opt = array())
    {
        $pager = new MaciPager($list, $this->request->get('page', 1), $this->getPager_PageLimit($map), $this->getPager_PageRange($map));
        $pager->setForm($this->getPagerForm($map, $pager, $opt));
        return $pager;
    }

    public function getPagerForm($map, $pager = false, $opt = array())
    {
        $options = array(
            'page' => $this->request->get('page', 1),
            'page_limit' => $this->getPager_PageLimit($map),
            'order_by_field' => $this->getPager_OrderByField($map),
            'order_by_sort' => $this->getPager_OrderBySort($map)
        );
        $page_attr = $pager ? array('max' => $pager->getMaxPages()) : array();
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

    public function manageRelation($managerMethod, $entity, $field, $item, $obj)
    {
        $managerMethod = 'get' . $managerMethod . 'Method';
        $manager = call_user_func_array(array($this, $managerMethod), array($item, $field));
        if (!$manager) {
            $this->session->getFlashBag()->add('error', $managerMethod . ' Method for ' . $field . ' in ' . $this->getClass($entity) . ' not found.');
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
        $methodName = ( $prefix . ucfirst($field) );
        if (method_exists($object, $methodName)) {
            return $methodName;
        }
        $methodName = ( $prefix . ucfirst($field) . 's' );
        if (method_exists($object, $methodName)) {
            return $methodName;
        }
        if ($field === 'children') {
            $methodName = $prefix . 'Child';
            if (method_exists($object, $methodName)) {
                return $methodName;
            }
        }
        if (2<strlen($field)) {
            $_len = strlen($field) - 1;
            if ($field[$_len] === 's') {
                $_mpp = substr($field, 0, $_len);
                $methodName = ( $prefix . ucfirst($_mpp) );
                if (method_exists($object, $methodName)) {
                    return $methodName;
                }
            }
        }
        return false;
    }

    public function getGetterMethod($object,$field)
    {
        return $this->searchMethod($object,$field,'get');
    }

    public function getIsMethod($object,$field)
    {
        return $this->searchMethod($object,$field,'is');
    }

    public function getSetterMethod($object,$field)
    {
        return $this->searchMethod($object,$field,'set');
    }

    public function getAdderMethod($object,$field)
    {
        return $this->searchMethod($object,$field,'add');
    }

    public function getRemoverMethod($object,$field)
    {
        return $this->searchMethod($object,$field,'remove');
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

/*
    ------------> Queries
*/

    public function addDefaultQueries($map, $query, $trashValue = false)
    {
        // $query = $this->addFiltersQuery($map, $query);
        $query = $this->addSearchQuery($map, $query);
        $query = $this->addTrashQuery($map, $query, $trashValue);
        $query = $this->addOrderByQuery($map, $query);

        return $query;
    }

    public function addFiltersQuery($map, $query)
    {
        $optf = $this->request->get('optf', array());
        foreach ($optf as $key => $value) {
            if ($value !== '' && in_array($key, $fields)) {
                $query->andWhere($query->getRootAlias() . '.' . $key . ' LIKE :' . $key);
                $query->setParameter(':' . $key, "%$value%");
            }
        }
        return $query;
    }

    public function addSearchQuery($map, $query)
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

    public function addTrashQuery($map, $query, $trashValue = false)
    {
        $hasTrash = $this->hasTrash($map);
        if ($hasTrash) {
            $fields = $this->getFields($map);
            $trashAttr = $this->getConfigKey($map, 'trash_field');
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

    public function getListIds($list)
    {
        $ids = array();
        foreach ($list as $item) {
            $ids[] = $item->getId();
        }
        return $ids;
    }

    public function getArrayWithLabels($array)
    {
        $list = array();
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

    public function createForm($type, $data = null, array $options = array())
    {
        return $this->formFactory->create($type, $data, $options);
    }

    public function createFormBuilder($data = null, array $options = array())
    {
        return $this->formFactory->createBuilder(FormType::class, $data, $options);
    }

    public function generateUrl($route, $parameters = array(), $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        return $this->router->generate($route, $parameters, $referenceType);
    }

/*
    ------------> CODE IN PAUSE O_O ( Filters )
*/

    public function getEntityFilters($entity)
    {
        return $this->session->get('admin_filters_'.$entity['name'], array());
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
        $this->session->set('admin_filters_'.$entity['name'], array());
    }

    public function getEntityFilterFields($entity)
    {
        if (array_key_exists('filters', $entity) && count($entity['filters'])) {
            return $entity['filters'];
        }
        return array();
    }

    public function hasEntityFilterFields($entity)
    {
        if (array_key_exists('filters', $entity) && count($entity['filters'])) {
            return true;
        }
        return false;
    }

    // public function getEntitiesClassList()
    // {
    //     if (!is_array($this->_list)) {
    //         $list = $this->om->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
    //         foreach ($list as $key => $value) {
    //             $reflcl = new \ReflectionClass($value);
    //             if ($reflcl->isAbstract()) {
    //                 unset($list[$key]);
    //             }
    //         }
    //         $this->_list = array_values($list);
    //     }
    //     return $this->_list;
    // }

}
