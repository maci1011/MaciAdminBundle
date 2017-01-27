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
                $this->_sections[$name]['entities'][$entity_name]['class'] = $this->getEntityClass($entity);
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
        if (array_key_exists($section, $this->_sections)) {
            return $this->_sections[$section];
        }
        return false;
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
            return $this->_sections[$section]['entities'][$entity]['templates'][$action];
        }
        return false;
    }

    public function hasEntityTemplate($section, $entity, $action)
    {
        if ($this->hasEntity($section, $entity) && array_key_exists($action, $this->_sections[$section]['entities'][$entity]['templates'])) {
            return $this->templating->exists($this->_sections[$section]['entities'][$entity]['templates'][$action]);
        }
        return false;
    }

    public function getMainActions($section, $entity)
    {
        $entity = $this->getEntity($section, $entity);
        $actions = array('list');
        if ($this->hasEntityTrash($entity)) {
            $actions[] = 'trash';
        }
        $actions[] = 'new';
        if ($this->isEntityUploadable($entity)) {
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

    // ????
    // public function getAdminBundle()
    // {
    //     return $this->kernel->getBundle('MaciAdminBundle');
    // }

    public function getEntityAssociations($entity)
    {
        $metadata = $this->getEntityMetadata($entity);

        foreach ($metadata->associationMappings as $fieldName => $relation) {
            // if ($relation['type'] !== ClassMetadataInfo::ONE_TO_MANY) {
            //     $associations[] = $fieldName;
            // }
            $associations[] = $fieldName;
        }

        return $associations;
    }

    public function getEntityAssociationMetadata($entity, $relation)
    {
        $metadata = $this->getEntityMetadata($entity);

        if (array_key_exists($relation, $metadata->associationMappings)) {
            return $metadata->associationMappings[$relation];
        }

        return false;
    }

    public function getEntityBundle($entity)
    {
        $name = split(':', $entity['class']);
        if (1 < count($name)) {
            return $this->kernel->getBundle($name[0]);
        }
        $namespace = substr($entity['class'], 0, strpos($entity['class'], 'Bundle') + 6);
        $bundles = $this->kernel->getBundles();
        foreach ($bundles as $bundle) {
            if ($bundle->getNamespace() === $namespace) {
                return $bundle;
            }
        }
        return false;
    }

    public function getEntityBundleName($entity)
    {
        return $this->getEntityBundle($entity)->getName();
    }

    public function getEntityBundleNamespace($entity)
    {
        return $this->getEntityBundle($entity)->getNamespace();
    }

    public function getEntityClass($entity)
    {
        if (class_exists($entity['class'])) {
            return $entity['class'];
        }
        $repo = $this->getEntityRepository($entity);
        if ($repo) {
            return $repo->getClassName();
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

    public function getEntityDetails($entity, $object)
    {
        $fields = $this->getEntityFields($entity);

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

    public function getEntityFields($entity)
    {
        $metadata = $this->getEntityMetadata($entity);

        $fields = (array) $metadata->fieldNames;

        // Remove the primary key field if it's not managed manually
        if (!$metadata->isIdentifierNatural()) {
            $fields = array_diff($fields, $metadata->identifier);
        }

        return $fields;
    }

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
        $object = $this->getEntityNewObj($entity);
        $filters = $this->getEntityFilters($entity);
        foreach ($filters as $field => $filter) {
            $method = ('set' . ucfirst($field));
            if ( method_exists($object, $method) ) {
                call_user_method_array($method, $object, array($filter));
            }
        }
        return $this->generateEntityForm($section, $entity, $object);
    }

    public function getEntityForm($section, $entity, $object = false)
    {
        $form = $entity['name'];
        if (!$object) {
            $object = $this->getEntityNewObj($entity);
        }
        if (array_key_exists('form', $entity)) {
            $form = $entity['form'];
        }
        if (class_exists($form)) {
            return $this->createForm(new $form, $object);
        }
        $form = ( $this->getEntityBundleNamespace($entity) . "\\Form\\Mcm" . $this->getCamel($section) . "\\" . $this->getCamel($entity['name']) . "Type" );
        if (class_exists($form)) {
            return $this->createForm($form, $object);
        }
        $form = ( $this->getEntityBundleNamespace($entity) . "\\Form\\Mcm\\" . $this->getCamel($entity['name']) . "Type" );
        if (class_exists($form)) {
            return $this->createForm($form, $object);
        }
        return $this->generateEntityForm($section, $entity, $object);
    }

    public function generateEntityForm($section, $entity, $object)
    {
        $fields = $this->getEntityFields($entity);
        $form = $this->createFormBuilder($object);
        $isNew = (bool) $object->getId();

        if ($isNew) {
            $form->setAction($this->generateUrl('maci_admin_view', array('section'=>$section, 'entity'=>$entity['name'], 'action'=>'edit', 'id'=>$object->getId())));
        } else {
            $form->setAction($this->generateUrl('maci_admin_view', array('section'=>$section, 'entity'=>$entity['name'], 'action'=>'new')));
        }

        foreach ($fields as $field) {
            if (in_array($field, array('created','updated',$entity['trash_attr']))) {
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

    public function getEntityItems($section, $entity)
    {
        $repo = $this->getEntityRepository($entity);
        $query = $repo->createQueryBuilder('e');
        $root = $query->getRootAlias();
        $fields = $this->getEntityFields($entity);

        if ($this->hasEntityTrash($entity)) {
            $trashAttr = $entity['trash_attr'];
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

    public function getEntityListFields($entity)
    {
        if (array_key_exists('list', $entity) && count($entity['list'])) {
            return array_merge(array('_id'), $entity['list'], array('_actions'));
        }
        $fields = array_keys($this->getEntityFields($entity));
        $list = array('_id');
        foreach ($fields as $field) {
            if ($field == $entity['trash_attr']) continue;
            $list[] = lcfirst($this->getCamel($field));
        }
        $list[] = '_actions';
        return $list;
    }

    public function getEntityMetadata($entity)
    {
        return $this->om->getClassMetadata( $this->getEntityClass($entity) );
    }

    public function getEntityNewObj($entity)
    {
        $class = $this->getEntityClass($entity);
        $item = new $class;
        if (method_exists($item, 'setLocale')) {
            call_user_method('setLocale', $item, $this->request->getLocale());
        }
        return $item;
    }

    public function getEntityRepository($entity)
    {
        return $this->om->getRepository($entity['class']);
    }

    public function hasEntityTrash($entity)
    {
        if (in_array($entity['trash_attr'], $this->getEntityFields($entity))) {
            return true;
        }
        return false;
    }

    public function isEntityUploadable($entity)
    {
        return (bool) $entity['uploadable'];
    }

/*
    ---> Relations Functions
*/

    public function getMapForRelation($relationMetadata)
    {
        $map = $this->getDefaultMap();
        $map['class'] = $relationMetadata['targetEntity'];
        $map['name'] = $relationMetadata['fieldName'];
        $map['label'] = $this->generateLabel( $relationMetadata['fieldName'] );
        return $map;
    }

    public function getRelation($entity, $relationName)
    {
        $relationMetadata = $this->getEntityAssociationMetadata($entity, $relationName);
        if (!$relationMetadata) {
            return false;
        }
        return $this->getMapForRelation($relationMetadata);
    }

    public function getRelationBridges($entity, $relation)
    {
        if (!array_key_exists('relations', $entity) || 
            !array_key_exists($relation['name'], $entity['relations']) ||
            !array_key_exists('bridges', $entity['relations'][$relation['name']])) {
            return array();
        }
        return $entity['relations'][$relation['name']]['bridges'];
    }

    public function getCurrentRelation($entity)
    {
        return $this->getRelation($entity, $this->request->get('relation'));
    }

    public function getRelationActions($section, $entity)
    {
        return array('list', 'show', 'add', 'set', 'bridge', 'remove');
    }

    public function getRelationDefaultAction($entity, $relation)
    {
        $relationMetadata = $this->getEntityAssociationMetadata($entity, $relation);

        if (!$relationMetadata) {
            return false;
        }

        if ($relationMetadata['type'] === ClassMetadataInfo::ONE_TO_MANY || $relationMetadata['type'] === ClassMetadataInfo::MANY_TO_MANY) {
            return 'list';
        }

        return 'show';

    }

    public function getRelationInverseField($entity, $relation)
    {
        $relationMetadata = $this->getEntityAssociationMetadata($entity, $relation['name']);

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
            $relationMetadataClass = $this->getEntityMetadata($relation);
            $identifiers = $relationMetadataClass->getIdentifier();
            $inverseField = $identifiers[0];
        }

        return $inverseField;
    }

    public function getRelationItems($relation, $object)
    {
        $getter = $this->getGetterMethod($object, $relation['name']);
        if (!$getter) {
            $this->session->getFlashBag()->add('error', 'Getter Method for ' . $relation['name'] . ' in ' . get_class($object) . ' not found.');
            return array();
        }

        $getted = call_user_method($getter, $object);

        if (is_object($getted)) {
            if (is_array($getted) || get_class($getted) === 'Doctrine\ORM\PersistentCollection') {
                return $getted;
            } else {
                return array($getted);
            }
        }

        return array();
    }

    public function getRelationParams($relation, $item = false)
    {
        $relAction = $this->request->get('relAction');
        return array(
            'relation' => $relation['name'],
            'relation_label' => $relation['label'],
            'relation_action' => $relAction,
            'relation_action_label' => $this->generateLabel($relAction),
            'item' => $item
        );
    }

    public function getItemsForRelation($entity, $relation, $item, $bridge = false)
    {
        $relation_items = $this->getRelationItems($relation, $item);
        $inverseField = $this->getRelationInverseField($entity, $relation);
        $repo = $relation;

        if ($bridge) {
            $bridge_items = array();
            foreach ($relation_items as $obj) {
                $brcall = call_user_method($this->getGetterMethod($obj, $bridge['name']), $obj);
                $brcall = is_array($brcall) ? $brcall : array($brcall);
                $bridge_items = array_merge($bridge_items, $brcall);
            }
            $relation_items = $bridge_items;
            // todo: same option as below
            $inverseField = $this->getRelationInverseField($relation, $bridge);
            $repo = $bridge;
        }

        $repo = $this->getEntityRepository($repo);
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
        if ($this->getEntityClass($entity) === $this->getEntityClass($relation) && !$bridge) {
            $query->andWhere($root . '.' . $inverseField . ' != :pid');
            $query->setParameter(':pid', call_user_method(('get'.ucfirst($inverseField)), $item));
        }

        $query = $query->getQuery();

        return $query->getResult();
    }

    public function setRelation($managerMethod, $entity, $field, $item, $obj)
    {
        $manager = call_user_method($managerMethod, $this, $item, $field);
        if (!$manager) {
            $this->session->getFlashBag()->add('error', $managerMethod . ' Method for ' . $field . ' in ' . $this->getEntityClass($entity) . ' not found.');
            return false;
        }
        call_user_method($manager, $item, ((strpos($managerMethod, 'Remover') && !$this->getRemoverMethod($item, $field)) ? null : $obj));
        return true;
    }

    public function manageRelation($managerMethod, $entity, $relation, $item, $obj)
    {
        $managerMethod = 'get' . $managerMethod . 'Method';
        if (!$this->setRelation($managerMethod, $entity, $relation['name'], $item, $obj)) {
            return false;
        }
        $relationMetadata = $this->getEntityAssociationMetadata($entity, $relation['name']);
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
        if (strpos($managerMethod, 'Remover')) {
            return $message . 'Removed.';
        } else if (strpos($managerMethod, 'Adder')) {
            return $message . ( $this->getRelationDefaultAction($entity, $relation['name']) === 'show' ? 'Setted.' : 'Added.' );
        } else {
            return $message . 'Setted.';
        }
    }

    public function relationItemsManager($entity, $relation, $item, $list, $ids, $managerMethod)
    {
        if (!count($ids)) {
            return false;
        }
        $repo = $this->getEntityRepository($relation);
        foreach ($ids as $_id) {
            $_id = (int) $_id;
            if (!$_id || !in_array($_id, $this->getListIds($list))) {
                $this->session->getFlashBag()->add('error', 'Item [' . $_id . '] for ' . $this->getEntityClass($relation) . ' not found.');
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
        $repo = $this->getEntityRepository($bridge);
        foreach ($ids as $_id) {
            $_id = (int) $_id;
            if (!$_id || !in_array($_id, $this->getListIds($list))) {
                continue;
            }
            $newItem = $this->getEntityNewObj($relation);
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
    ---> Actions Parameters
*/

    public function getDefaultParams($section, $entity, $action, $id)
    {
        return array(
            'section' => $section,
            'section_label' => $this->getSectionLabel($section),
            'entity' => $entity,
            'entity_label' => $this->getEntityLabel($section, $entity),
            'action' => $action,
            'action_label' => $this->generateLabel($action),
            'actions' => $this->arrayLabels($this->getActions($section, $entity)),
            'main_actions' => $this->arrayLabels($this->getMainActions($section, $entity)),
            'single_actions' => $this->arrayLabels($this->getSingleActions($section, $entity)),
            'multiple_actions' => $this->arrayLabels($this->getMultipleActions($section, $entity)),
            'id' => $id
        );
    }

    public function getTemplate($section,$entity,$action)
    {
        if (is_string($entity)) {
            $entity = $this->getEntity($section,$entity);
            if (!$entity) { return false; }
        }
        $template = $this->getEntityTemplate($section,$entity['name'],$action);
        if ( $template ) {
            return $template;
        }
        $bundleName = $this->getEntityBundleName($entity);
        $template = $bundleName . ':Mcm' . $this->getCamel($entity['name']) . ':_' . $action . '.html.twig';
        if ( $this->templating->exists($template) ) {
            return $template;
        }
        $template = $bundleName . ':Mcm:_' . $action . '.html.twig';
        if ( $this->templating->exists($template) ) {
            return $template;
        }
        return 'MaciAdminBundle:Actions:' . $action .'.html.twig';
    }

/*
    ---> Pager
*/

    public function getPager($section, $entity, $result)
    {
        if (is_string($entity)) {
            $entity = $this->getEntity($entity);
            if (!$entity) {
                return false;
            }
        }

        $pageLimit = $this->config['options']['page_limit'];
        $pageRange = $this->config['options']['page_range'];

        $page = $this->request->get('page', 1);

        $pager = new MaciPager($result, $pageLimit, $page, $pageRange);

        $fields_list = $this->getEntityListFields($entity);

        $pager->setListFields($fields_list);

        return $pager;
    }

/*
    ---> Utils
*/

    public function getDefaultMap()
    {
        $map = array();
        $map['class'] = false;
        $map['name'] = false;
        $map['label'] = false;
        $map['list'] = array();
        $map['filters'] = array();
        $map['templates'] = array();
        $map['trash_attr'] = 'removed';
        return $map;
    }

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

}
