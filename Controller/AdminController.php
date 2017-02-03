<?php

namespace Maci\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Bundle\TwigBundle\TwigEngine;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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

class AdminController extends Controller
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

    public function __construct(ObjectManager $objectManager, AuthorizationCheckerInterface $authorizationChecker, Session $session, RequestStack $requestStack, \AppKernel $kernel, FormFactory $formFactory, FormRegistry $formRegistry, Router $router, TwigEngine $templating, Array $config)
    {
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

        $this->initSections();
    }

/*
    ---> Sections Functions
*/

    // Init _sections and _auth_sections
    private function initSections()
    {
        $this->_sections = $this->config['sections'];
        $this->_auth_sections = [];
        // sections loop
        foreach ($this->_sections as $name => $section) {
            // section default values
            if (!array_key_exists('config', $this->_sections[$name])) {
                $this->_sections[$name]['config'] = [];
            }
            // section roles
            if (!array_key_exists('roles', $this->_sections[$name]['config'])) {
                $this->_sections[$name]['config']['roles'] = ['ROLE_ADMIN'];
            }
            foreach ($this->_sections[$name]['config']['roles'] as $role) {
                if ($this->authorizationChecker->isGranted($role)) {
                    $this->_auth_sections[] = $name;
                    break;
                }
            }
            //  section definitions
            $this->_sections[$name]['config']['name'] = $name;
            if (!array_key_exists('label', $this->_sections[$name]['config'])) {
                $this->_sections[$name]['config']['label'] = $this->generateLabel($name);
            }
            //  entities default values
            if (!array_key_exists('entities', $this->_sections[$name])) {
                $this->_sections[$name]['entities'] = [];
            }
            //  entities loop
            foreach ($this->_sections[$name]['entities'] as $entity_name => $entity) {
                //  entity definitions
                $this->_sections[$name]['entities'][$entity_name]['section'] = $name;
                $this->_sections[$name]['entities'][$entity_name]['name'] = $entity_name;
                $this->_sections[$name]['entities'][$entity_name]['class'] = $this->getClass($entity);
                if (!array_key_exists('label', $entity)) {
                    $this->_sections[$name]['entities'][$entity_name]['label'] = $this->generateLabel($entity_name);
                }
                if (!array_key_exists('templates', $entity)) {
                    $this->_sections[$name]['entities'][$entity_name]['templates'] = [];
                }
            }
        }
    }

    public function getAuthSections()
    {
        return $this->_auth_sections;
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
            return $this->_sections[$section]['config'];
        }
        return false;
    }

    public function getSectionLabel($section)
    {
        if (array_key_exists($section, $this->_sections)) {
            return $this->_sections[$section]['config']['label'];
        }
        return false;
    }

    public function getSectionDashboard($section)
    {
        if ($this->hasSectionDashboard($section)) {
            return $this->_sections[$section]['config']['dashboard'];
        }
        return false;
    }

    public function hasSectionDashboard($section)
    {
        if (array_key_exists($section, $this->_sections) && array_key_exists('dashboard', $this->_sections[$section]['config'])) {
            return $this->templating->exists($this->_sections[$section]['config']['dashboard']);
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
            return !!count($this->_sections[$section]['entities']);
        }
        return false;
    }

/*
    ---> Entity Functions
*/

    public function getEntity($section, $entity)
    {
        if ($this->hasEntity($section, $entity)) {
            return $this->_sections[$section]['entities'][$entity];
        }
        return false;
    }

    public function getEntityByClass($section, $className)
    {
        $entities = $this->getEntities($section);
        if (!$entities) {
            return false;
        }
        foreach ($entities as $name => $map) {
            if ($className === $this->getClass($map)) {
                return $map;
            }
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

    public function getEntityLabel($section, $entity)
    {
        if ($this->hasEntity($section, $entity)) {
            return $this->_sections[$section]['entities'][$entity]['label'];
        }
        return false;
    }

    public function getEntityTemplate($section, $entity, $action)
    {
        if ($this->hasEntityTemplate($section, $entity, $action)) {
            return $this->_sections[$section]['entities'][$entity]['templates'][$action]['template'];
        }
        return false;
    }

    public function hasEntityTemplate($section, $entity, $action)
    {
        if ($this->hasEntity($section, $entity) && array_key_exists($action, $this->_sections[$section]['entities'][$entity]['templates'])) {
            return $this->templating->exists($this->_sections[$section]['entities'][$entity]['templates'][$action]['template']);
        }
        return false;
    }

/*
    ---> Actions Functions
*/

    public function getMainActions($section, $entity)
    {
        $entity = $this->getEntity($section, $entity);
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

    public function getSingleActions($section, $entity)
    {
        return array('show', 'edit', 'relations', 'remove');
    }

    public function getMultipleActions($section, $entity)
    {
        return array('reorder');
    }

    public function getActions($section, $entity)
    {
        return array_merge(
            $this->getMainActions($section, $entity),
            $this->getSingleActions($section, $entity),
            $this->getMultipleActions($section, $entity)
        );
    }

    public function hasAction($section, $entity, $action)
    {
        return in_array($action, array_merge(
            $this->getMainActions($section, $entity),
            $this->getSingleActions($section, $entity),
            $this->getMultipleActions($section, $entity)
        ));
    }

    public function getRelationActions($section, $entity)
    {
        return array('list', 'show', 'add', 'set', 'bridge', 'remove', 'upload');
    }

/*
    ---> Generic Functions
*/

    public function getAssociations($map)
    {
        $metadata = $this->getMetadata($map);

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

    public function getBundle($map)
    {
        $name = split(':', $map['class']);
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
        if (class_exists($map['class'])) {
            return $map['class'];
        }
        $repo = $this->getRepository($map);
        if ($repo) {
            return $repo->getClassName();
        }
        return false;
    }

    public function getCurrentRelation($map)
    {
        return $this->getRelation($map, $this->request->get('relation'));
    }

    public function getCurrentBridge($map)
    {
        return $this->getBridge($map, $this->request->get('bridge'));
    }

    public function getDefaultMap()
    {
        $map = array();
        $map['list'] = array();
        $map['filters'] = array();
        $map['templates'] = array();
        $map['trash_attr'] = 'removed';
        return $map;
    }

    public function getDefaultParams($map)
    {
        $action = $this->request->get('action');
        return array(
            'section' => $map['section'],
            'section_label' => $this->getSectionLabel($map['section']),
            'section_has_dashboard' => $this->hasSectionDashboard($map['section']),
            'entity' => $map['name'],
            'entity_label' => $this->getEntityLabel($map['section'], $map['name']),
            'action' =>  $action,
            'action_label' => $this->generateLabel($action),
            'actions' => $this->arrayLabels($this->getActions($map['section'], $map['name'])),
            'fields' => $this->getListFields($map),
            'id' => $this->request->get('id'),
            'main_actions' => $this->arrayLabels($this->getMainActions($map['section'], $map['name'])),
            'multiple_actions' => $this->arrayLabels($this->getMultipleActions($map['section'], $map['name'])),
            'single_actions' => $this->arrayLabels($this->getSingleActions($map['section'], $map['name'])),
            'template' => $this->getTemplate($map,$action)
        );
    }

    public function getDefaultRelationParams($map, $relation, $item = false)
    {
        $relAction = $this->request->get('relAction');
        return array_merge($this->getDefaultParams($map), array(
            'fields' => $this->getListFields($relation),
            'relation' => $relation['association'],
            'relation_label' => $relation['label'],
            'relation_action' => $relAction,
            'relation_action_label' => $this->generateLabel($relAction),
            'bridges' => $this->getBridges($relation),
            'item' => $item,
            'template' => $this->getTemplate($map,('relations_'.$relAction))
        ));
    }

    public function getDefaultBridgeParams($map, $relation, $bridge, $item = false)
    {
        $relAction = $this->request->get('relAction');
        if ($relAction === 'bridge') {
            $relAction = ( $this->getRelationDefaultAction($map, $relation['association']) === 'show' ? 'set' : 'add' );
        }
        return array_merge($this->getDefaultRelationParams($map, $relation, $item), array(
            'fields' => $this->getListFields($bridge),
            'relation_action_label' => ($this->generateLabel($relAction) . ' ' . $bridge['label']),
            'relation_action' => $relAction,
            'template' => $this->getTemplate($bridge,('relations_'.$relAction))
        ));
    }

    public function getFields($map)
    {
        $metadata = $this->getMetadata($map);

        $fields = (array) $metadata->fieldNames;

        // Remove the primary key field if it's not managed manually
        if (!$metadata->isIdentifierNatural()) {
            $fields = array_diff($fields, $metadata->identifier);
        }

        return $fields;
    }

    public function getFieldValue($field,$object)
    {
        $getter = $this->getGetterMethod($object,$field);
        if (!$getter) {
            $this->session->getFlashBag()->add('error', 'Getter Method for ' . $field . ' in ' . get_class($object) . ' not found.');
            return false;
        }
        return call_user_method($getter,$object);
    }

    public function generateForm($map, $object = false)
    {
        if (!$object) {
            $object = $this->getNewItem($map);
        }

        $fields = $this->getFields($map);
        $form = $this->createFormBuilder($object);
        $isNew = (bool) $object->getId();

        if ($isNew) {
            $form->setAction($this->generateUrl('maci_admin_view', array('section'=>$map['section'], 'entity'=>$map['name'], 'action'=>'edit', 'id'=>$object->getId())));
        } else {
            $form->setAction($this->generateUrl('maci_admin_view', array('section'=>$map['section'], 'entity'=>$map['name'], 'action'=>'new')));
        }

        foreach ($fields as $field) {
            if (in_array($field, array('created','updated',$map['trash_attr']))) {
                continue;
            }
            $method = ('get' . ucfirst($field) . 'Array');
            if ( method_exists($object, $method) ) {
                $form->add($field, ChoiceType::class, array(
                    'empty_data' => '',
                    'choices' => call_user_method($method, $object)
                ));
            } else {
                $form->add($field);
            }
            // else if ($field === 'locale') {
            //     $form->add($field,null,array(
            //         'data' => $this->request->getLocale()
            //     ));
            // }
        }

        $form->add('reset', ResetType::class);

        $form->add(( $isNew ? 'save' : 'add'), SubmitType::class, array(
            'attr'=>array('class'=>'btn btn-primary')
        ));

        return $form->getForm();
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

    public function getListFields($map)
    {
        if (array_key_exists('list', $map) && count($map['list'])) {
            return array_merge(array('_id'), $map['list'], array('_actions'));
        }
        $fields = array_keys($this->getFields($map));
        $list = array('_id');
        foreach ($fields as $field) {
            if ($field == $map['trash_attr']) continue;
            $list[] = lcfirst($this->getCamel($field));
        }
        $list[] = '_actions';
        return $list;
    }

    public function getItemDetails($map, $object)
    {
        $fields = $this->getFields($map);

        $details = array();

        foreach ($fields as $field) {

            $value = null;

            $uf = ucfirst($field);

            if (method_exists($object, ('is'.$uf))) {
                $value = ( call_user_method(('is'.$uf), $object) ? 'True' : 'False' );
            } else if (method_exists($object, ('get'.$uf.'Label'))) {
                $value = call_user_method(('get'.$uf.'Label'), $object);
            } else if (method_exists($object, ('get'.$uf))) {
                $value = call_user_method(('get'.$uf), $object);
                if (is_object($value) && get_class($value) === 'DateTime') {
                    $value = $value->format("Y-m-d H:i:s");
                }
            } else if (method_exists($object, $field)) {
                $value = call_user_method($field, $object);
            }

            array_push($details, array(
                'label' => $this->generateLabel($field),
                'value' => $value
            ));

        }

        return $details;
    }

    public function getItems($map)
    {
        $repo = $this->getRepository($map);
        $query = $repo->createQueryBuilder('e');
        $root = $query->getRootAlias();
        $fields = $this->getFields($map);

        if ($this->hasTrash($map)) {
            $trashAttr = $map['trash_attr'];
            $trashValue = (bool) ( $this->request->get('action', '') === 'trash' );
            if ( in_array($trashAttr, $fields) ) {
                $query->andWhere($root . '.' . $trashAttr . ' = :' . $trashAttr);
                $query->setParameter(':' . $trashAttr, $trashValue);
            }
        }

        // $optf = $this->request->get('optf', array());
        // foreach ($optf as $key => $value) {
        //     if ($value !== '' && in_array($key, $fields)) {
        //         $query->andWhere('e.' . $key . ' LIKE :' . $key);
        //         $query->setParameter(':' . $key, "%$value%");
        //     }
        // }

        $query->orderBy('e.id', 'DESC');

        $query = $query->getQuery();
        $list = $query->getResult();

        return $list;
    }

    public function getItemsForRelation($entity, $relation, $item, $bridge = false)
    {
        $relation_items = $this->getRelationItems($relation, $item);
        $inverseField = $this->getRelationInverseField($entity, $relation);
        $repo = $relation;

        if ($bridge) {
            $bridge_items = array();
            foreach ($relation_items as $obj) {
                $brcall = call_user_method($this->getGetterMethod($obj, $bridge['bridge']), $obj);
                $brcall = is_array($brcall) ? $brcall : array($brcall);
                $bridge_items = array_merge($bridge_items, $brcall);
            }
            $relation_items = $bridge_items;
            // todo: same option as below
            $inverseField = $this->getRelationInverseField($relation, $bridge);
            $repo = $bridge;
        }

        $repo = $this->getRepository($repo);
        $query = $repo->createQueryBuilder('r');
        $root = $query->getRootAlias();

        $index = 0;
        foreach ($relation_items as $obj) {
            $parameter = ':id_' . $index;
            $query->andWhere($root . '.' . $inverseField . ' != ' . $parameter);
            $query->setParameter($parameter, call_user_method(('get'.ucfirst($inverseField)), $obj));
            $index++;
        }

        // todo: an option fot this
        if ($this->getClass($entity) === $this->getClass($relation) && !$bridge) {
            $query->andWhere($root . '.' . $inverseField . ' != :pid');
            $query->setParameter(':pid', call_user_method(('get'.ucfirst($inverseField)), $item));
        }

        $query->orderBy($root . '.' . $inverseField, 'DESC');

        $query = $query->getQuery();

        return $query->getResult();
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
            call_user_method('setLocale', $item, $this->request->getLocale());
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

        $metadata = $this->getAssociationMetadata($map, $association);
        if (!$metadata) {
            return false;
        }

        $relation = $this->getNewMap($metadata);

        $entity = $this->getEntityByClass($map['section'], $this->getClass($relation));
        if ($entity) {
            $relation = $entity;
        }

        $relation['association'] = $association;

        if (array_key_exists('relations', $map) && array_key_exists($association, $map['relations'])) {
            $relation = array_merge($relation, $map['relations'][$association]);
        }

        return $relation;
    }

    public function setRelation($managerMethod, $entity, $field, $item, $obj)
    {
        $manager = call_user_method($managerMethod, $this, $item, $field);
        if (!$manager) {
            $this->session->getFlashBag()->add('error', $managerMethod . ' Method for ' . $field . ' in ' . $this->getClass($entity) . ' not found.');
            return false;
        }
        call_user_method($manager, $item, ((strpos($managerMethod, 'Remover') && !$this->getRemoverMethod($item, $field)) ? null : $obj));
        return true;
    }

    public function getRelationDefaultAction($entity, $relation)
    {
        $relationMetadata = $this->getAssociationMetadata($entity, $relation);

        if (!$relationMetadata) {
            return false;
        }

        if ($relationMetadata['type'] === ClassMetadataInfo::ONE_TO_MANY || $relationMetadata['type'] === ClassMetadataInfo::MANY_TO_MANY) {
            return 'list';
        }

        return 'show';

    }

    public function isRelationEnable($map,$association)
    {
        if (array_key_exists('relations', $map) && array_key_exists($association, $map['relations'])) {
            return $map['relations'][$association]['enabled'];
        }
        return true;
    }

    public function getRelationInverseField($map, $relation)
    {
        $relationMetadata = $this->getAssociationMetadata($map, $relation['association']);

        $joinTable = false;
        $joinColumns = false;
        $inverseJoinColumns = false;
        if (array_key_exists('joinTable', $relationMetadata)) {
            $joinTable = $relationMetadata['joinTable'];
            if (array_key_exists('joinColumns', $joinTable)) {
                $joinColumns = $joinTable['joinColumns'];
                $inverseJoinColumns = $joinTable['inverseJoinColumns'];
            }
        } else if (array_key_exists('joinColumns', $relationMetadata)) {
            $joinColumns = $relationMetadata['joinColumns'];
        }

        $inverseField = false;

        if ($joinColumns) {
            $sourceField = $joinColumns[0]['referencedColumnName'];
        }

        if ($inverseJoinColumns) {
            $inverseField = $inverseJoinColumns[0]['referencedColumnName'];
        }

        if (!$inverseField) {
            $relationMetadataClass = $this->getMetadata($relation);
            $identifiers = $relationMetadataClass->getIdentifier();
            $inverseField = $identifiers[0];
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

    public function getRemoveForm($map,$item)
    {
        return $this->createFormBuilder($item)
            ->setAction($this->generateUrl('maci_admin_view', array(
                'section'=>$map['section'], 'entity'=>$map['name'], 'action'=>'remove', 'id'=>$item->getId()
            )))
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

    public function getTemplate($map,$action)
    {
        if ( array_key_exists($action, $map['templates']) && $this->templating->exists($map['templates'][$action]['template']) ) {
            return $map['templates'][$action]['template'];
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
        return 'MaciAdminBundle:Actions:' . $action .'.html.twig';
    }

    public function hasTrash($map)
    {
        if (in_array($map['trash_attr'], $this->getFields($map))) {
            return true;
        }
        return false;
    }

    public function isUploadable($map)
    {
        return (bool) $map['uploadable'];
    }

/*
    ---> Relations Manager
*/

    public function manageRelation($managerMethod, $entity, $relation, $item, $obj)
    {
        $managerMethod = 'get' . $managerMethod . 'Method';
        if (!$this->setRelation($managerMethod, $entity, $relation['association'], $item, $obj)) {
            return false;
        }
        $relationMetadata = $this->getAssociationMetadata($entity, $relation['association']);
        $isOwningSide = $relationMetadata['isOwningSide'];
        $mappedBy = $relationMetadata['mappedBy'];
        if (!$isOwningSide && 0<strlen($mappedBy) && !$this->setRelation($managerMethod, $relation, $mappedBy, $obj, $item)) {
            return false;
        }
        return true;
    }

    public function getManagerSuccessMessage($managerMethod, $entity, $relation, $item)
    {
        $message = 'Item [' . $item . '] ';
        if (strpos($managerMethod, 'Remover') !== false) {
            return $message . 'Removed.';
        } else if (strpos($managerMethod, 'Adder')) {
            return $message . ( $this->getRelationDefaultAction($entity, $relation['association']) === 'show' ? 'Setted.' : 'Added.' );
        } else {
            return $message . 'Setted.';
        }
    }

    public function relationItemsManager($entity, $relation, $item, $list, $ids, $managerMethod)
    {
        if (!count($ids)) {
            return false;
        }
        $repo = $this->getRepository($relation);
        foreach ($ids as $_id) {
            $_id = (int) $_id;
            if (!$_id || !in_array($_id, $this->getListIds($list))) {
                $this->session->getFlashBag()->add('error', 'Item [' . $_id . '] for ' . $this->getClass($relation) . ' not found.');
                continue;
            }
            $obj = $list[array_search($_id, $this->getListIds($list))];
            if ($this->manageRelation($managerMethod, $entity, $relation, $item, $obj)) {
                $this->session->getFlashBag()->add('success', $this->getManagerSuccessMessage($managerMethod, $entity, $relation, $_id));
            }
        }
        $this->om->flush();
        return true;
    }

    public function addRelationItems($entity, $relation, $item, $list, $ids)
    {
        $this->relationItemsManager($entity, $relation, $item, $list, $ids, 'SetterOrAdder');
    }

    public function removeRelationItems($entity, $relation, $item, $list, $ids)
    {
        $this->relationItemsManager($entity, $relation, $item, $list, $ids, 'RemoverOrSetter');
    }

    public function addRelationItemsFromRequestIds($entity, $relation, $item, $list)
    {
        $this->addRelationItems($entity, $relation, $item, $list, split(',', $this->request->get('ids', '')));
    }

    public function removeRelationItemsFromRequestIds($entity, $relation, $item, $list)
    {
        $this->removeRelationItems($entity, $relation, $item, $list, split(',', $this->request->get('ids', '')));
    }

    public function addRelationBridgedItemsFromRequestIds($entity, $relation, $bridge, $item, $list)
    {
        $ids = split(',', $this->request->get('ids', ''));
        if (!count($ids)) {
            return false;
        }
        $repo = $this->getRepository($bridge);
        foreach ($ids as $_id) {
            $_id = (int) $_id;
            if (!$_id || !in_array($_id, $this->getListIds($list))) {
                continue;
            }
            $newItem = $this->getNewItem($relation);
            $obj = $list[array_search($_id, $this->getListIds($list))];
            if ($this->manageRelation('SetterOrAdder', $entity, $relation, $item, $newItem) && $this->manageRelation('SetterOrAdder', $relation, $bridge, $newItem, $obj)) {
                $this->om->persist($newItem);
                $this->session->getFlashBag()->add('success', $this->getManagerSuccessMessage('SetterOrAdder', $entity, $relation, $_id));
            }
        }
        $this->om->flush();
        return true;
    }

/*
    ---> Pager
*/

    public function getPager($list)
    {
        $pageLimit = $this->config['options']['page_limit'];
        $pageRange = $this->config['options']['page_range'];

        $page = $this->request->get('page', 1);

        $pager = new MaciPager($list, $pageLimit, $page, $pageRange);

        return $pager;
    }

/*
    ---> Utils
*/

    public function getListIds($list)
    {
        $ids = array();
        foreach ($list as $item) {
            $ids[] = $item->getId();
        }
        return $ids;
    }

    public function getGetterMethod($object,$field)
    {
        $methodName = ( 'get' . ucfirst($field) );
        if (method_exists($object, $methodName)) {
            return $methodName;
        }
        $methodName = ( 'get' . ucfirst($field) . 's' );
        if (method_exists($object, $methodName)) {
            return $methodName;
        }
        return false;
    }

    public function getSetterMethod($object,$field)
    {
        $methodName = ( 'set' . ucfirst($field) );
        if (method_exists($object, $methodName)) {
            return $methodName;
        }
        if ($field === 'children') {
            $methodName = 'setChild';
            if (method_exists($object, $methodName)) {
                return $methodName;
            }
        }
        if (2<strlen($field)) {
            $_len = strlen($field) - 1;
            if ($field[$_len] === 's') {
                $_mpp = substr($field, 0, $_len);
                $methodName = ( 'set' . ucfirst($_mpp) );
                if (method_exists($object, $methodName)) {
                    return $methodName;
                }
            }
        }
        return false;
    }

    public function getAdderMethod($object,$field)
    {
        $methodName = ( 'add' . ucfirst($field) );
        if (method_exists($object, $methodName)) {
            return $methodName;
        }
        if ($field === 'children') {
            $methodName = 'addChild';
            if (method_exists($object, $methodName)) {
                return $methodName;
            }
        }
        if (2<strlen($field)) {
            $_len = strlen($field) - 1;
            if ($field[$_len] === 's') {
                $_mpp = substr($field, 0, $_len);
                $methodName = ( 'add' . ucfirst($_mpp) );
                if (method_exists($object, $methodName)) {
                    return $methodName;
                }
            }
        }
        return false;
    }

    public function getRemoverMethod($object,$field)
    {
        $methodName = ( 'remove' . ucfirst($field) );
        if (method_exists($object, $methodName)) {
            return $methodName;
        }
        if ($field === 'children') {
            $methodName = 'removeChild';
            if (method_exists($object, $methodName)) {
                return $methodName;
            }
        }
        if (2<strlen($field)) {
            $_len = strlen($field) - 1;
            if ($field[$_len] === 's') {
                $_mpp = substr($field, 0, $_len);
                $methodName = ( 'remove' . ucfirst($_mpp) );
                if (method_exists($object, $methodName)) {
                    return $methodName;
                }
            }
        }
        return false;
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

    public function getCamel($str)
    {
        return Container::camelize($str);
    }

    public function generateLabel($str)
    {
        return ucwords(str_replace('_', ' ', $str));
    }

    public function arrayLabels($array)
    {
        $list = array();
        foreach ($array as $item) {
            $list[$item] = $this->generateLabel($item);
        }
        return $list;
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
    ---> CODE IN PAUSE O_O ( Filters )
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

    public function getEntityFiltersForm($section, $entity)
    {
        $form = $entity['name'];
        $object = $this->getNewItem($entity);
        $filters = $this->getEntityFilters($entity);
        foreach ($filters as $field => $filter) {
            $method = ('set' . ucfirst($field));
            if ( method_exists($object, $method) ) {
                call_user_method_array($method, $object, array($filter));
            }
        }
        return $this->generateForm($entity, $object);
    }

/*
    ---> ???
*/

    // public function getAdminBundle()
    // {
    //     return $this->kernel->getBundle('MaciAdminBundle');
    // }

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
