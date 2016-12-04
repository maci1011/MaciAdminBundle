<?php

namespace Maci\AdminBundle\Controller;

use A2lix\I18nDoctrineBundle\Annotation\I18nDoctrine;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\SecurityContext;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

use Maci\AdminBundle\MaciPager;

class AdminController extends Controller
{
    private $_sections;

    private $_list;

    private $_section_list;

    private $config;

    private $em;

    private $last;

    private $request;

    private $kernel;

    private $formFactory;

    private $formRegistry;

    private $router;

    public function __construct(EntityManager $em, SecurityContext $securityContext, Session $session, RequestStack $requestStack, \AppKernel $kernel, FormFactory $formFactory, FormRegistry $formRegistry, Router $router, Array $config)
    {
        $this->em = $em;
        $this->securityContext = $securityContext;
        $this->user = $securityContext->getToken()->getUser();
        $this->session = $session;
        $this->request = $requestStack->getCurrentRequest();
        $this->kernel = $kernel;
        $this->formFactory = $formFactory;
        $this->formRegistry = $formRegistry;
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
                    if ($this->securityContext->isGranted($role)) {
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

    public function getEntityBundle($entity)
    {
        return $this->kernel->getBundle(split(':', $entity['class'])[0]);
    }

    public function getEntityClass($entity)
    {
        $repo = $this->getEntityRepository($entity);
        return $repo->getClassName();
    }

    public function getEntityClassList()
    {
        if (!is_array($this->_list)) {
            $list = $this->em->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
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

    public function getEntityTranslationFields($entity)
    {
        $metadata = $this->getDoctrine()->getManager()
            ->getClassMetadata( $entity );

        $fields = (array) $metadata->fieldNames;

        // Remove the primary key field if it's not managed manually
        if (!$metadata->isIdentifierNatural()) {
            $fields = array_diff($fields, $metadata->identifier);
        }

        foreach ($metadata->associationMappings as $fieldName => $relation) {
            if ($relation['type'] !== ClassMetadataInfo::ONE_TO_MANY) {
                $fields[] = $fieldName;
            }
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

    public function getEntityFiltersForm($entity)
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
        return $this->generateEntityForm($entity, $object);
    }

    public function getEntityForm($entity, $object = false)
    {
        $form = $entity['name'];
        if (!$object) {
            $object = $this->getEntityNewObj($entity);
        }
        if (array_key_exists('form', $entity)) {
            $form = $entity['form'];
        }
        if (strpos($form,'\\') && class_exists($form)) {
            return $this->createForm(new $form, $object);
        } else if ($this->formRegistry->hasType($form)) {
            return $this->createForm($form, $object);
        }
        return $this->generateEntityForm($entity, $object);
    }

    public function generateEntityForm($entity, $object)
    {
        $fields = $this->getEntityFields($entity);
        $form = $this->createFormBuilder($object);

        foreach ($fields as $field) {
            $method = ('get' . ucfirst($field) . 'Array');
            if ( method_exists($object, $method) ) {
                $form->add($field, 'choice', array(
                    'empty_value' => '',
                    'choices' => call_user_method($method, $object)
                ));
            } else {
                $form->add($field);
            }
        }

        $form->add('reset', 'reset');

        if ( $this->request->get('action') === 'new' ) {
            $form->add('add', 'submit');
        } else {
            $form->add('save', 'submit');
        }

        return $form->getForm();
    }

    public function getEntityFromRelation($relation)
    {
        $entity = array();
        $entity['class'] = $relation['targetEntity'];
        $entity['name'] = $relation['fieldName'];
        $entity['label'] = $this->generateLabel( $relation['fieldName'] );
        $entity['list'] = array();
        $entity['filters'] = array();
        $entity['template'] = array();
        return $entity;
    }

    public function getEntityItems($section, $entity)
    {
        $repo = $this->getEntityRepository($entity);
        $query = $repo->createQueryBuilder('e');
        $root = $query->getRootAlias();
        $fields = $this->getEntityFields($entity);

        if ($this->hasEntityTrash($entity)) {
            $trashAttr = $entity['trash_attr'];
            $trashValue = ( $this->request->get('action') === 'trash' ? true : false );
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
        return array('_id', '_string', '_actions');
    }

    public function getEntityMetadata($entity)
    {
        return $this->em->getClassMetadata( $this->getEntityClass($entity) );
    }

    public function getEntityNewObj($entity)
    {
        $class = $this->getEntityClass($entity);
        return new $class;
    }

    public function getEntityRepository($entity)
    {
        return $this->em->getRepository($entity['class']);
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

    public function getCurrentRelation($entity)
    {
        $relationName = $this->request->get('relation');
        $relationMetadata = $this->getEntityAssociationMetadata($entity, $relationName);
        if (!$relationMetadata) {
            return false;
        }
        return $this->getEntityFromRelation($relationMetadata);
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
            $this->session->getFlashBag()->add('error', 'Getter Method for ' . $relation['name'] . ' in ' . get_class($item) . ' not found.');
            return array();
        }

        $getted = call_user_method($getter, $object);

        if (is_object($getted)) {
            if (get_class($getted) === $relation['class']) {
                return array($getted);
            } else {
                return $getted;
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

    public function getPager($entity, $result)
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
            $form = $this->getEntityFiltersForm($entity);
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
        $methodName = $this->trySetterMethod($object, $name);
        if ($methodName) {
            return $methodName;
        }
        if ($name === 'children') {
            $methodName = $this->trySetterMethod($object, 'Child');
            if ($methodName) {
                return $methodName;
            }
        }
        if (2<strlen($name)) {
            $_len = strlen($name) - 1;
            if ($name[$_len] === 's') {
                $_mpp = substr($name, 0, $_len);
                $methodName = $this->trySetterMethod($object, $_mpp);
                if ($methodName) {
                    return $methodName;
                }
            }
        }
        return false;
    }

    public function trySetterMethod($object, $name)
    {
        $methodName = ( 'set' . ucfirst($name) );
        if (method_exists($object, $methodName)) {
            return $methodName;
        }
        $methodName = ( 'add' . ucfirst($name) );
        if (method_exists($object, $methodName)) {
            return $methodName;
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
        return $this->formFactory->createBuilder('form', $data, $options);
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

        $pager = $this->getPager($entity, $list);

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

        $item = $this->getEntityNewObj($entity);

        $clone = $this->request->get('clone', false);

        if ($id) {
            $result = $this->getEntityRepository($entity)->findOneById($id);

            if (!$result) {
                return $this->returnError($this->request, 'item-not-found');
            }

            if ($clone) {
                $item = $this->cloneItem($entity, $result);
            } else {
                $item = $result;
            }
        } else {
            $result = $item;
        }

        $params = array();
        $form = $this->getEntityForm($entity, $item);
        $form->handleRequest($this->request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ($item->getId() === null) {
                $this->em->persist($item);
            }

            if ($clone) {
                $this->cloneItemChildren($entity, $item, $result);
            }

            $this->em->flush();

            $params['success'] = true;
            if ($this->request->get('action') === 'edit') {
                $this->session->getFlashBag()->add('success', 'Item Edited.');
            } else {
                $this->session->getFlashBag()->add('success', 'Item Added.');
            }
        }

        $params['item'] = $result;
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
            ->add('remove', 'submit', array(
                'attr' => array(
                    'class' => 'btn-danger'
                )
            ))
            ->getForm()
        ;

        $form->handleRequest($this->request);

        $params = array();

        if ($form->isValid()) {

            if (method_exists($item, 'setRemoved')) {
                $item->setRemoved(true);
            } else {
                $this->em->remove($item);
            }

            $this->em->flush();

            $this->session->getFlashBag()->add('success', 'Item [' . $id . '] for ' . $entity['class'] . ' removed.');

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
            $this->session->getFlashBag()->add('error', 'Item [' . $_id . '] for ' . $entity['class'] . ' not found.');
            return false;
        }

        $relation = $this->getCurrentRelation($entity);
        if (!$relation) {
            $this->session->getFlashBag()->add('error', 'Relation ' . $relationName . ' in ' . $entity['class'] . ' not found.');
            return false;
        }

        $list = $this->getRelationItems($relation, $item);

        $pager = $this->getPager($entity, $list);

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
            $this->session->getFlashBag()->add('error', 'Item [' . $_id . '] for ' . $entity['class'] . ' not found.');
            return false;
        }

        $relation = $this->getCurrentRelation($entity);
        if (!$relation) {
            $this->session->getFlashBag()->add('error', 'Relation ' . $relationName . ' in ' . $entity['class'] . ' not found.');
            return false;
        }

        $inverseField = $this->getRelationInverseField($entity, $relation);

        $repo = $this->getEntityRepository($relation);
        $query = $repo->createQueryBuilder('r');
        $root = $query->getRootAlias();

        $relation_items = $this->getRelationItems($relation, $item);

        foreach ($relation_items as $key => $value) {
            $parameter = ':id_' . $key;
            $query->andWhere($root . '.' . $inverseField . ' != ' . $parameter);
            $query->setParameter($parameter, call_user_method(('get'.ucfirst($inverseField)), $value));
        }

        if ($this->getEntityClass($entity) === $relation['class']) {
            $query->andWhere($root . '.' . $inverseField . ' != :pid');
            $query->setParameter(':pid', call_user_method(('get'.ucfirst($inverseField)), $item));
        }

        $query = $query->getQuery();

        $list = $query->getResult();

        $params = $this->getRelationParams($relation, $item);

        if ($this->request->getMethod() === 'POST') {
            $ids = split(',', $this->request->get('ids', ''));
            if (!count($ids)) {
                return false;
            }
            $repo = $this->getEntityRepository($relation);
            foreach ($ids as $_id) {
                if (!$_id or !in_array($_id, $this->getListIds($list))) {
                    $this->session->getFlashBag()->add('error', 'Item [' . $_id . '] for ' . $relation['class'] . ' not found.');
                    continue;
                }
                $setEntity = $list[array_search($_id, $this->getListIds($list))];
                $setter = $this->getSetterMethod($item,$relation['name']);
                if ($setter) {
                    call_user_method($setter, $item, $setEntity);
                } else {
                    $this->session->getFlashBag()->add('error', 'Setter Method for ' . $relation['name'] . ' in ' . $entity['class'] . ' not found.');
                    continue;
                }
                $relationMetadata = $this->getEntityAssociationMetadata($entity, $relation['name']);
                $isOwningSide = $relationMetadata['isOwningSide'];
                $mappedBy = $relationMetadata['mappedBy'];
                if (!$isOwningSide && 0<strlen($mappedBy)) {
                    $setter = $this->getSetterMethod($setEntity,$mappedBy);
                    if ($setter) {
                        call_user_method($setter, $setEntity, $item);
                    } else {
                        $this->session->getFlashBag()->add('error', 'Setter Method for ' . $mappedBy . ' in ' . $relation['class'] . ' not found.');
                        continue;
                    }
                }
                if ($relAction === 'set') {
                    $this->session->getFlashBag()->add('success', 'Item [' . $_id . '] Setted.');
                } else {
                    $this->session->getFlashBag()->add('success', 'Item [' . $_id . '] Added.');
                }
            }
            $this->em->flush();
            $params['redirect'] = 'maci_admin_view_relations';
            $params['redirect_params'] = array(
                'section' => $section,
                'entity' => $entity['name'],
                'id' => $id,
                'relation' => $relation['name'],
                'relAction' => $this->getRelationDefaultAction($entity, $relation['name'])
            );
        }

        $pager = $this->getPager($entity, $list);

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
            $this->session->getFlashBag()->add('error', 'Item [' . $_id . '] for ' . $entity['class'] . ' not found.');
            return false;
        }

        $relation = $this->getCurrentRelation($entity);
        if (!$relation) {
            $this->session->getFlashBag()->add('error', 'Relation ' . $relationName . ' in ' . $entity['class'] . ' not found.');
            return false;
        }

        $list = $this->getRelationItems($relation, $item);

        $params = $this->getRelationParams($relation, $item);

        if ($this->request->getMethod() === 'POST') {
            $ids = split(',', $this->request->get('ids', ''));
            if (!count($ids)) {
                return false;
            }
            $repo = $this->getEntityRepository($relation);
            foreach ($ids as $_id) {
                if (!$_id or !in_array($_id, $this->getListIds($list))) {
                    $this->session->getFlashBag()->add('error', 'Item [' . $_id . '] for ' . $relation['class'] . ' not found.');
                    continue;
                }
                $removeEntity = $list[array_search($_id, $this->getListIds($list))];
                $remover = $this->getRemoverMethod($item,$relation['name']);
                if ($remover) {
                    call_user_method($remover, $item, $removeEntity);
                } else {
                    $setter = $this->getSetterMethod($item,$relation['name']);
                    if ($setter) {
                        call_user_method($setter, $item, null);
                    } else {
                        $this->session->getFlashBag()->add('error', 'Remover/Setter Method for ' . $relation['name'] . ' in ' . $entity['class'] . ' not found.');
                        continue;
                    }
                }
                $relationMetadata = $this->getEntityAssociationMetadata($entity, $relation['name']);
                $isOwningSide = $relationMetadata['isOwningSide'];
                $mappedBy = $relationMetadata['mappedBy'];
                if (!$isOwningSide && 0<strlen($mappedBy)) {
                    $remover = $this->getRemoverMethod($removeEntity,$mappedBy);
                    if ($remover) {
                        call_user_method($remover, $removeEntity, $item);
                    } else {
                        $setter = $this->getSetterMethod($removeEntity,$mappedBy);
                        if ($setter) {
                            call_user_method($setter, $removeEntity, null);
                        } else {
                            $this->session->getFlashBag()->add('error', 'Remover/Setter Method for ' . $mappedBy . ' in ' . $relation['class'] . ' not found.');
                            continue;
                        }
                    }
                }
                $this->session->getFlashBag()->add('success', 'Item [' . $_id . '] Removed.');
            }
            $this->em->flush();
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
            $this->em->persist($item);
        }

        $this->em->flush();

        return array('success' => true);
    }

}
