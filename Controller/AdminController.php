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
    }

/*
    ---> Default Actions
*/

    public function dashboardAction($section, $entity, $id)
    {
        return $this->mcmDashboard($section, $entity, $id);
    }

    public function listAction($section, $entity, $id)
    {
        return $this->mcmList($section, $entity, $id);
    }

    public function trashAction($section, $entity, $id)
    {
        return $this->mcmList($section, $entity, $id);
    }

    public function showAction($section, $entity, $id)
    {
        return $this->mcmShow($section, $entity, $id);
    }

    /**
     * @I18nDoctrine
     */
    public function newAction($section, $entity, $id)
    {
        return ( $id ? false : $this->mcmForm($section, $entity, $id) );
    }

    /**
     * @I18nDoctrine
     */
    public function editAction($section, $entity, $id)
    {
        return ( $id ? $this->mcmForm($section, $entity, $id) : false );
    }

    public function removeAction($section, $entity, $id)
    {
        return $this->mcmRemove($section, $entity, $id);
    }

    public function relationsAction($section, $entity, $id)
    {
        return $this->mcmRelations($section, $entity, $id);
    }

    public function uploaderAction($section, $entity, $id)
    {
        return $this->mcmUploader($section, $entity, $id);
    }

/*
    ---> Generic Functions
*/

    public function getSections()
    {
        if (!is_array($this->_section_list)) {
            $sections = array();
            foreach ($this->config['sections'] as $name => $section) {
                $roles = $section['config']['roles'];
                foreach ($roles as $role) {
                    if ($this->authorizationChecker->isGranted($role)) {
                        $sections[] = $name;
                        break;
                    }
                }
            }
            $this->_section_list = $sections;
        }
        return $this->_section_list;
    }

    public function getSection($section = false)
    {
        if (!$section) {
            $list = $this->getSections();
            $section = $list[0];
        }
        if (!is_array($this->_sections)) {
            $this->_sections = $this->config['sections'];
            foreach ($this->_sections as $name => $sct) {
                foreach ($sct['entities'] as $entity_name => $entity) {
                    if (!array_key_exists('label', $entity)) {
                        $this->_sections[$name]['entities'][$entity_name]['label'] = $this->generateLabel($entity_name);
                    }
                    $this->_sections[$name]['entities'][$entity_name]['name'] = $entity_name;
                }
                if (!array_key_exists('label', $sct['config'])) {
                    $this->_sections[$name]['config']['label'] = $this->generateLabel($name);
                }
                $this->_sections[$name]['config']['name'] = $name;
            }
        }
        if (array_key_exists($section, $this->_sections)) {
            return $this->_sections[$section];
        }
        return false;
    }

    public function getSectionLabel($section)
    {
        $section = $this->getSection($section);
        if ($section) {
            return $section['config']['label'];
        }
        return false;
    }

    public function getEntities($section)
    {
        $section = $this->getSection($section);
        if ($section) {
            return $section['entities'];
        }
        return false;
    }

    public function getConfig($section)
    {
        $section = $this->getSection($section);
        if ($section) {
            return $section['config'];
        }
        return false;
    }

    public function getEntity($section, $entity)
    {
        $entities = $this->getEntities($section);
        if (array_key_exists($entity, $entities)) {
            return $entities[$entity];
        }
        return false;
    }

    public function hasEntity($section, $entity)
    {
        if ($this->getEntity($section, $entity)) {
            return true;
        }
        return false;
    }

    public function getEntityLabel($section, $entity)
    {
        $entities = $this->getEntities($section);
        if (array_key_exists($entity, $entities)) {
            return $entities[$entity]['label'];
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

    public function getAdminBundle()
    {
        return $this->kernel->getBundle('MaciAdminBundle');
    }

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

    public function getEntityBridge($entity, $bridge)
    {
        if (!array_key_exists('bridges', $entity)) {
            return false;
        }
        if (array_key_exists($bridge, $entity['bridges'])) {
            return $entity['bridges'][$bridge]['relation'];
        }
        return false;
    }

    public function getEntityBundle($entity)
    {
        return $this->kernel->getBundle(split(':', $entity['class'])[0]);
    }

    public function getEntityBundleName($entity)
    {
        $bundle = get_class($this->getEntityBundle($entity));
        $bundle = split("\\\\", $bundle);
        return $bundle[(count($bundle)-1)];
    }

    public function getEntityBundleNamespace($entity)
    {
        $bundle = get_class($this->getEntityBundle($entity));
        $bundle = split("\\\\", $bundle);
        unset($bundle[(count($bundle)-1)]);
        $bundle = join("\\", $bundle);
        return ("\\" . $bundle);
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

    public function getEntityClassList()
    {
        if (!is_array($this->_list)) {
            $list = $this->om->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
            foreach ($list as $key => $value) {
                $reflcl = new \ReflectionClass($value);
                if ($reflcl->isAbstract()) {
                    unset($list[$key]);
                }
            }
            $this->_list = array_values($list);
        }
        return $this->_list;
    }

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
            } else if ($field === 'locale') {
                $form->add($field,null,array(
                    'data' => $this->request->getLocale()
                ));
            } else {
                $form->add($field);
            }
        }

        $form->add('reset', ResetType::class);

        $form->add(( $isNew ? 'add' : 'save'), SubmitType::class, array(
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
        return new $class;
    }

    public function getEntityRepository($entity)
    {
        return $this->om->getRepository($entity['class']);
    }

    public function getEntityTemplate($section,$entity,$action)
    {
        if (is_string($entity)) {
            $entity = $this->getEntity($section,$entity);
            if (!$entity) { return false; }
        }
        if ( array_key_exists('templates', $entity) && array_key_exists($action, $entity['templates']) && $this->templating->exists($entity['templates'][$action]) ) {
            return $entity['templates'][$action];
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

    public function getMapForRelation($relation)
    {
        $map = array();
        $map['class'] = $relation['targetEntity'];
        $map['name'] = $relation['fieldName'];
        $map['label'] = $this->generateLabel( $relation['fieldName'] );
        $map['list'] = array();
        $map['filters'] = array();
        $map['templates'] = array();
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

    public function getCurrentRelation($entity)
    {
        return $this->getRelation($entity, $this->request->get('relation'));
    }

    public function getRelationActions($section, $entity)
    {
        return array('list', 'show', 'add', 'set', 'remove');
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

    public function getItemsForRelation($entity, $relation, $item)
    {
        $relation_items = $this->getRelationItems($relation, $item);
        $inverseField = $this->getRelationInverseField($entity, $relation);
        $relation_query = $relation;

        $bridgeName = $this->getEntityBridge($entity, $relation['name']);
        if ($bridgeName) {
            $bridgeMetadata = $this->getEntityAssociationMetadata($relation, $bridgeName);
            if (!$bridgeMetadata) {
                return false;
            }
            $bridgeRelation = $this->getMapForRelation($bridgeMetadata);
            // var_dump($bridgeRelation); die();
        }

        $repo = $this->getEntityRepository($relation);
        $query = $repo->createQueryBuilder('r');
        $root = $query->getRootAlias();

        $index = 0;
        foreach ($relation_items as $obj) {
            $parameter = ':id_' . $index;
            $query->andWhere($root . '.' . $inverseField . ' != ' . $parameter);
            $query->setParameter($parameter, call_user_method(('get'.ucfirst($inverseField)), $obj));
            $index++;
        }

        if ($this->getEntityClass($entity) === $this->getEntityClass($relation)) {
            $query->andWhere($root . '.' . $inverseField . ' != :pid');
            $query->setParameter(':pid', call_user_method(('get'.ucfirst($inverseField)), $item));
        }

        $query = $query->getQuery();

        return $query->getResult();
    }

    public function relationItemsManager($entity, $relation, $item, $list, $ids, $action)
    {
        if ($action === 'add') {
            $managerMethod = 'getSetterOrAdderMethod';
            if ($this->getRelationDefaultAction($entity, $relation['name']) === 'show') {
                $successMessage = 'Setted';
            } else {
                $successMessage = 'Added';
            }
            $errorMessage = 'Setter or Adder';
        } else if ($action === 'remove') {
            $managerMethod = 'getRemoverOrSetterMethod';
            $successMessage = 'Removed';
            $errorMessage = 'Remover or Setter';
        } else {
            return false;
        }
        if (!count($ids)) {
            return false;
        }
        $repo = $this->getEntityRepository($relation);
        foreach ($ids as $_id) {
            if (!$_id or !in_array($_id, $this->getListIds($list))) {
                $this->session->getFlashBag()->add('error', 'Item [' . $_id . '] for ' . $this->getEntityClass($relation) . ' not found.');
                continue;
            }
            $obj = $list[array_search($_id, $this->getListIds($list))];
            $manager = call_user_method($managerMethod, $this, $item, $relation['name']);
            if ($manager) {
                if ($action === 'remove' && !$this->getRemoverMethod($item, $relation['name'])) {
                    call_user_method($manager, $item, null);
                } else {
                    call_user_method($manager, $item, $obj);
                }
            } else {
                $this->session->getFlashBag()->add('error', $errorMessage . ' Method for ' . $relation['name'] . ' in ' . $this->getEntityClass($entity) . ' not found.');
                continue;
            }
            $relationMetadata = $this->getEntityAssociationMetadata($entity, $relation['name']);
            $isOwningSide = $relationMetadata['isOwningSide'];
            $mappedBy = $relationMetadata['mappedBy'];
            if (!$isOwningSide && 0<strlen($mappedBy)) {
                $manager = call_user_method($managerMethod, $this, $obj, $mappedBy);
                if ($manager) {
                    if ($action === 'remove' && !$this->getRemoverMethod($item, $relation['name'])) {
                        call_user_method($manager, $obj, null);
                    } else {
                        call_user_method($manager, $obj, $item);
                    }
                } else {
                    $this->session->getFlashBag()->add('error', $errorMessage . ' Method for ' . $mappedBy . ' in ' . $this->getEntityClass($relation) . ' not found.');
                    continue;
                }
            }
            $this->session->getFlashBag()->add('success', 'Item [' . $_id . '] ' . $successMessage . '.');
        }
        $this->om->flush();
    }

    public function addRelationItems($entity, $relation, $item, $list, $ids)
    {
        $this->relationItemsManager($entity, $relation, $item, $list, $ids, 'add');
    }

    public function removeRelationItems($entity, $relation, $item, $list, $ids)
    {
        $this->relationItemsManager($entity, $relation, $item, $list, $ids, 'remove');
    }

    public function addRelationItemsFromRequestIds($entity, $relation, $item, $list)
    {
        $this->relationItemsManager($entity, $relation, $item, $list, split(',', $this->request->get('ids', '')), 'add');
    }

    public function removeRelationItemsFromRequestIds($entity, $relation, $item, $list)
    {
        $this->relationItemsManager($entity, $relation, $item, $list, split(',', $this->request->get('ids', '')), 'remove');
    }

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

    public function getListIds($list)
    {
        $ids = array();
        foreach ($list as $item) {
            $ids[] = $item->getId();
        }
        return $ids;
    }

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

        if ($this->request->get('modal') || $this->request->get('nolimit')) {
            $pageLimit = 0;
        }

        $pager = new MaciPager($result, $pageLimit, $page, $pageRange);

        $form = false;

        if ($this->hasEntityFilterFields($entity)) {
            $form = $this->getEntityFiltersForm($section, $entity);
            $form = $form->createView();
        }

        $fields_list = $this->getEntityListFields($entity);

        $pager->setListFields($fields_list);
        $pager->setFiltersForm($form);

        return $pager;
    }

    public function getGetterMethod($object,$name)
    {
        $methodName = ( 'get' . ucfirst($name) );
        if (method_exists($object, $methodName)) {
            return $methodName;
        }
        $methodName = ( 'get' . ucfirst($name) . 's' );
        if (method_exists($object, $methodName)) {
            return $methodName;
        }
        return false;
    }

    public function getSetterMethod($object,$name)
    {
        $methodName = ( 'set' . ucfirst($name) );
        if (method_exists($object, $methodName)) {
            return $methodName;
        }
        if ($name === 'children') {
            $methodName = 'setChild';
            if (method_exists($object, $methodName)) {
                return $methodName;
            }
        }
        if (2<strlen($name)) {
            $_len = strlen($name) - 1;
            if ($name[$_len] === 's') {
                $_mpp = substr($name, 0, $_len);
                $methodName = ( 'set' . ucfirst($_mpp) );
                if (method_exists($object, $methodName)) {
                    return $methodName;
                }
            }
        }
        return false;
    }

    public function getAdderMethod($object,$name)
    {
        $methodName = ( 'add' . ucfirst($name) );
        if (method_exists($object, $methodName)) {
            return $methodName;
        }
        if ($name === 'children') {
            $methodName = 'addChild';
            if (method_exists($object, $methodName)) {
                return $methodName;
            }
        }
        if (2<strlen($name)) {
            $_len = strlen($name) - 1;
            if ($name[$_len] === 's') {
                $_mpp = substr($name, 0, $_len);
                $methodName = ( 'add' . ucfirst($_mpp) );
                if (method_exists($object, $methodName)) {
                    return $methodName;
                }
            }
        }
        return false;
    }

    public function getRemoverMethod($object,$name)
    {
        $methodName = ( 'remove' . ucfirst($name) );
        if (method_exists($object, $methodName)) {
            return $methodName;
        }
        if ($name === 'children') {
            $methodName = 'removeChild';
            if (method_exists($object, $methodName)) {
                return $methodName;
            }
        }
        if (2<strlen($name)) {
            $_len = strlen($name) - 1;
            if ($name[$_len] === 's') {
                $_mpp = substr($name, 0, $_len);
                $methodName = ( 'remove' . ucfirst($_mpp) );
                if (method_exists($object, $methodName)) {
                    return $methodName;
                }
            }
        }
        return false;
    }

    public function getSetterOrAdderMethod($object,$name)
    {
        $methodName = $this->getSetterMethod($object,$name);
        if ($methodName) {
            return $methodName;
        }
        $methodName = $this->getAdderMethod($object,$name);
        if ($methodName) {
            return $methodName;
        }
        return false;
    }

    public function getRemoverOrSetterMethod($object,$name)
    {
        $methodName = $this->getRemoverMethod($object,$name);
        if ($methodName) {
            return $methodName;
        }
        $methodName = $this->getSetterMethod($object,$name);
        if ($methodName) {
            return $methodName;
        }
        return false;
    }

    public function getCamel($str)
    {
        return Container::camelize($str);
    }

    public function generateLabel($name)
    {
        $str = str_replace('_', ' ', $name);
        return ucwords($str);
    }

    public function arrayLabels($array)
    {
        $items = array();
        foreach ($array as $item) {
            $items[$item] = $this->generateLabel($item);
        }
        return $items;
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
    ---> Generic Actions
*/

    public function mcmDashboard($section, $entity, $id)
    {
        return array();
    }

    public function mcmList($section, $entity, $id)
    {
        $entity = $this->getEntity($section, $entity);

        $list = $this->getEntityItems($section, $entity);

        $pager = $this->getPager($section, $entity, $list);

        if (!$pager) {
            return false;
        }

        return array(
            'pager' => $pager,
            'fields' => $pager->getListFields(),
            'form' => $pager->getFiltersForm()
        );
    }

    public function mcmShow($section, $entity, $id)
    {
        $entity = $this->getEntity($section, $entity);

        if (!$id) {
            $this->session->getFlashBag()->add('error', 'Missing Id.');
            return false;
        }

        $item = $this->getEntityRepository($entity)->findOneById($id);

        if (!$item) {
            return false;
        }

        return array(
            'item' => $item,
            'details' => $this->getEntityDetails($entity, $item)
        );
    }

    public function mcmForm($section, $entity, $id)
    {
        $entity = $this->getEntity($section, $entity);

        $item = null;

        // $clone = $this->request->get('clone', false);

        if ($id) {
            $item = $this->getEntityRepository($entity)->findOneById($id);
            if (!$item) {
                $this->session->getFlashBag()->add('error', 'Item [' . $id . '] for ' . $this->getEntityClass($entity) . ' not found.');
                return false;
            }
            // if ($clone) {
            //     $item = $this->cloneItem($entity, $item);
            // } else {
            //     $item = $item;
            // }
        } else {
            $item = $this->getEntityNewObj($entity);
        }

        $form = $this->getEntityForm($section, $entity, $item);
        $form->handleRequest($this->request);

        $params = array();

        if ($form->isSubmitted() && $form->isValid()) {

            $isNew = (bool) ( $item->getId() === null );
            if ($isNew) {
                $this->om->persist($item);
            }

            // if ($clone) {
            //     $this->cloneItemChildren($entity, $item, $item);
            // }

            $this->om->flush();

            if ($isNew) {
                $this->session->getFlashBag()->add('success', 'Item Added.');
            } else {
                $this->session->getFlashBag()->add('success', 'Item Edited.');
            }

            $params['redirect'] = 'maci_admin_view';
            $params['redirect_params'] = array(
                'section' => $section,
                'entity' => $entity['name'],
                'action' => 'list'
            );

        }

        $params['item'] = $item;
        $params['form'] = $form->createView();

        return $params;
    }

    public function mcmRemove($section, $entity, $id)
    {
        $entity = $this->getEntity($section, $entity);

        if (!$id) {
            $this->session->getFlashBag()->add('error', 'Missing Id.');
            return false;
        }

        $item = $this->getEntityRepository($entity)->findOneById($id);
        if (!$item) {
            return false;
        }

        $redirect = $this->request->get('redirect');

        $form = $this->createFormBuilder($item)
            ->setAction($this->generateUrl('maci_admin_view', array(
                'section'=>$section, 'entity'=>$entity['name'], 'action'=>'remove', 'id'=>$item->getId(), 'redirect'=>$redirect
            )))
            ->add('remove', SubmitType::class, array(
                'attr' => array('class' => 'btn-danger')
            ))
            ->getForm()
        ;

        $form->handleRequest($this->request);

        $params = array();

        if ($form->isValid()) {

            if (method_exists($item, 'setRemoved')) {
                $item->setRemoved(true);
            } else {
                $this->om->remove($item);
            }

            $this->om->flush();

            $this->session->getFlashBag()->add('success', 'Item [' . $id . '] for ' . $this->getEntityClass($entity) . ' removed.');

            $params['redirect'] = 'maci_admin_view';
            $params['redirect_params'] = array(
                'section' => $section,
                'entity' => $entity['name'],
                'action' => 'list'
            );

        } else {

            $params['item'] = $item;
            $params['form'] = $form->createView();

        }

        return $params;
    }

    public function mcmRelations($section, $entity, $id)
    {
        $relAction = $this->request->get('relAction');

        if ($relAction === 'list' || $relAction === 'show') {
            return $this->mcmRelationsList($section, $entity, $id);

        } else if ($relAction === 'add' || $relAction === 'set') {
            return $this->mcmRelationsAdd($section, $entity, $id);

        } else if ($relAction === 'remove') {
            return $this->mcmRelationsRemove($section, $entity, $id);
        }

        return false;
    }

    public function mcmRelationsList($section, $entity, $id)
    {
        $entity = $this->getEntity($section, $entity);

        $item = $this->getEntityRepository($entity)->findOneById($id);
        if (!$item) {
            $this->session->getFlashBag()->add('error', 'Item [' . $_id . '] for ' . $this->getEntityClass($entity) . ' not found.');
            return false;
        }

        $relation = $this->getCurrentRelation($entity);
        if (!$relation) {
            $this->session->getFlashBag()->add('error', 'Relation ' . $relationName . ' in ' . $this->getEntityClass($entity) . ' not found.');
            return false;
        }

        $list = $this->getRelationItems($relation, $item);

        $pager = $this->getPager($section, $entity, $list);

        if (!$pager) {
            return false;
        }

        $params = $this->getRelationParams($relation, $item);

        $params['pager'] = $pager;
        $params['fields'] = $pager->getListFields();
        $params['form'] = $pager->getFiltersForm();

        return $params;
    }

    public function mcmRelationsAdd($section, $entity, $id)
    {
        $entity = $this->getEntity($section, $entity);

        $item = $this->getEntityRepository($entity)->findOneById($id);
        if (!$item) {
            $this->session->getFlashBag()->add('error', 'Item [' . $_id . '] for ' . $this->getEntityClass($entity) . ' not found.');
            return false;
        }

        $relation = $this->getCurrentRelation($entity);
        if (!$relation) {
            $this->session->getFlashBag()->add('error', 'Relation ' . $relationName . ' in ' . $this->getEntityClass($entity) . ' not found.');
            return false;
        }

        $list = $this->getItemsForRelation($entity, $relation, $item);

        $params = $this->getRelationParams($relation, $item);

        if ($this->request->getMethod() === 'POST') {

            $this->addRelationItemsFromRequestIds($entity, $relation, $item, $list);

            $params['redirect'] = 'maci_admin_view_relations';
            $params['redirect_params'] = array(
                'section' => $section,
                'entity' => $entity['name'],
                'id' => $id,
                'relation' => $relation['name'],
                'relAction' => $this->getRelationDefaultAction($entity, $relation['name'])
            );

        }

        $pager = $this->getPager($section, $entity, $list);

        if (!$pager) {
            return false;
        }

        $params['pager'] = $pager;
        $params['fields'] = $pager->getListFields();
        $params['form'] = $pager->getFiltersForm();

        return $params;
    }

    public function mcmRelationsRemove($section, $entity, $id)
    {
        $entity = $this->getEntity($section, $entity);

        $item = $this->getEntityRepository($entity)->findOneById($id);
        if (!$item) {
            $this->session->getFlashBag()->add('error', 'Item [' . $_id . '] for ' . $this->getEntityClass($entity) . ' not found.');
            return false;
        }

        $relation = $this->getCurrentRelation($entity);
        if (!$relation) {
            $this->session->getFlashBag()->add('error', 'Relation ' . $relationName . ' in ' . $this->getEntityClass($entity) . ' not found.');
            return false;
        }

        $list = $this->getRelationItems($relation, $item);

        $params = $this->getRelationParams($relation, $item);

        if ($this->request->getMethod() === 'POST') {

            $this->removeRelationItemsFromRequestIds($entity, $relation, $item, $list);

            $params['redirect'] = 'maci_admin_view_relations';
            $params['redirect_params'] = array(
                'section' => $section,
                'entity' => $entity['name'],
                'id' => $id,
                'relation' => $relation['name'],
                'relAction' => $this->getRelationDefaultAction($entity, $relation['name'])
            );

        }

        return $params;
    }

    public function mcmUploader($section, $entity, $id)
    {
        if (!$this->request->isXmlHttpRequest() || $this->request->getMethod() !== 'POST') {
            return array();
        }

        $entity = $this->getEntity($section, $entity);

        if (!count($this->request->files)) {
            return array('success' => false, 'error' => 'No file(s).');
        }

        $repo = $this->getEntityRepository($entity);

        $name = $this->request->files->keys()[0];
        $file = $this->request->files->get($name);

        if(!$file->isValid()) {
            return array('success' => false, 'error' => 'Upload failed.');
        }

        if ($id) {
            $item = $repo->findOneById($id);
            if (!$item) {
                return array('success' => false, 'error' => 'Item not found.');
            }
            $item->setFile($file);
        } else {
            $item = $this->getEntityNewObj($entity);
            $item->setFile($file);
            $this->om->persist($item);
        }

        $this->om->flush();

        return array('success' => true);
    }

}
