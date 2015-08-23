<?php

namespace Maci\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

use A2lix\I18nDoctrineBundle\Annotation\I18nDoctrine;
use Maci\CoreBundle\Entity\MaciPager;

class DefaultController extends Controller
{
    private $_entities;

    public function indexAction()
    {
        $entities = $this->getEntities();

        return $this->render('MaciAdminBundle:Default:index.html.twig', array(
            'entities' => $entities
        ));
    }

    public function showAction(Request $request, $entity, $id)
    {
        $entity = $this->getEntity($entity);
        if (!$entity) {
            return $this->returnError($request, 'entity-not-found');
        }

        $item = $this->getEntityRepository($entity)->findOneById($id);

        if (!$item) {
            return $this->returnError($request, 'item-not-found');
        }

        return $this->renderTemplate($request, $entity, 'show', array(
            'entity' => $entity['name'],
            'item' => $item,
            'details' => $this->getEntityDetails($entity, $item)
        ));
    }

    /**
     * @I18nDoctrine
     */
    public function formAction(Request $request, $entity, $id)
    {
        $entity = $this->getEntity($entity);
        if (!$entity) {
            return $this->returnError($request, 'entity-not-found');
        }

        $item = $this->getEntityNewObj($entity);

        $clone = $request->get('clone', false);

        if ($id) {
            $result = $this->getEntityRepository($entity)->findOneById($id);

            if (!$result) {
                return $this->returnError($request, 'item-not-found');
            }

            if ($clone) {
                $item = $this->cloneItem($entity, $result);
            } else {
                $item = $result;
            }
        } else {
            $result = $item;
        }

        $form = $this->getEntityForm($entity, $item);

        $form->handleRequest($request);

        if ($form->isValid()) {

            $em = $this->getDoctrine()->getManager();
            $em->persist($item);

            if ($clone) {
                $this->cloneItemChildren($entity, $item, $result);
            }

            $em->flush();

            if ($request->isXmlHttpRequest()) {
                return $this->renderTemplate($request, $entity, 'show', array(
                    'entity' => $entity['name'],
                    'item' => $item,
                    'details' => $this->getEntityDetails($entity, $item)
                ));
            }
            else {
                return $this->redirect($this->generateUrl('maci_admin_entity_show', array(
                    'entity' => $entity['name'],
                    'id' => $item->getId(),
                    'message' => 'form.add'
                )));
            }
        }

        return $this->renderTemplate($request, $entity, 'form', array(
            'entity' => $entity['name'],
            'item' => $result,
            'form' => $form->createView()
        ));
    }

    public function listAction(Request $request, $entity, $trash = false)
    {
        $entity = $this->getEntity($entity);
        if (!$entity) {
            return $this->returnError($request, 'entity-not-found');
        }

        $filters_fields = $this->getEntityFilterFields($entity);
        $form = false;
        if ( count($filters_fields) ) {
            $form = $this->getEntityFiltersForm($entity);
            $form = $form->createView();
        }

        $item = $this->getEntityNewObj($entity);
        $filters = $this->getEntityFilters($entity);
        $repo = $this->getEntityRepository($entity);
        if ( method_exists($item, 'getRemoved') ) {
            $filters['removed'] = $trash;
        }
        $optf = $request->get('optf', array());
        foreach ($optf as $key => $value) {
            if (method_exists($item, ('get'.ucfirst($key)))) {
                $filters[$key] = ( strlen($value) ? $value : null );
            }
        }
        if ( $this->hasEntityFilters($entity) ) {
            $query = $repo->createQueryBuilder('e');
            foreach ($filters as $filter => $value) {
                $query->orWhere('e.' . $filter . ' LIKE :' . $filter);
                $query->setParameter(':' . $filter, "%$value%");
            }
            $query = $query->getQuery();
            $list = $query->getResult();
        } else {
            $list = $repo->findBy($filters);
        }

        $pageLimit = $this->container->getParameter('maci.admin.page_limit');
        $pageRange = $this->container->getParameter('maci.admin.page_range');
        $page = $request->get('page', 1);
        if ($request->get('modal')) {
            $pageLimit = 0;
        }
        $pager = new MaciPager($list, $pageLimit, $page, $pageRange);

        $fields = $this->getEntityListFields($entity);

        return $this->renderTemplate($request, $entity, 'list', array(
            'fields' => $fields,
            'entity' => $entity['name'],
            'pager' => $pager,
            'form' => $form
        ));
    }

    public function setFiltersAction(Request $request, $entity)
    {
        $entity = $this->getEntity($entity);
        if (!$entity) {
            return $this->returnError($request, 'entity-not-found');
        }

        $filters = array();
        $filters_fields = $this->getEntityFilterFields($entity);

        if ( !count($filters_fields) ) {
            return $this->returnError($request, 'no-filters');
        }

        $form = $this->getEntityFiltersForm($entity);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $new = $this->getEntityNewObj($entity);
            foreach ($filters_fields as $filter) {
                $value = $form[$filter]->getData();
                $method = false;
                if (method_exists($new, 'get'.ucfirst($filter))) {
                    $method = 'get'.ucfirst($filter);
                } else if (method_exists($new, 'get'.ucfirst($filter))) {
                    $method = 'get'.ucfirst($filter);
                }
                if ($method) {
                    $default = call_user_func($method, $new);
                    if ( $value !== $default) {
                        $filters[$filter] = $value;
                    }
                }
            }
            $this->setEntityFilters($entity, $filters);
        }

        return $this->redirect($this->generateUrl('maci_admin_entity', array(
            'entity' => $entity['name']
        )));
    }

    public function removeFiltersAction(Request $request, $entity)
    {
        $entity = $this->getEntity($entity);
        if (!$entity) {
            return $this->returnError($request, 'entity-not-found');
        }

        $this->removeEntityFilters($entity);

        return $this->redirect($this->generateUrl('maci_admin_entity', array(
            'entity' => $entity['name']
        )));
    }

    public function objectAction(Request $request, $entity, $id)
    {
        $entity = $this->getEntity($entity);
        if (!$entity) {
            return $this->returnError($request, 'entity-not-found');
        }

        $item = $this->getEntityNewObj($entity);
        $clone = $request->get('clone');

        if ($id) {
            $item = $this->getEntityRepository($entity)->findOneById($id);
            if (!$item) {
                return $this->returnError($request, 'item-not-found');
            }
        } elseif ($clone) {
            $result = $this->getEntityRepository($entity)->findOneById($id = $clone);
            if (!$result) {
                return $this->returnError($request, 'item-not-found');
            }
            $item = $this->cloneItem($entity, $result);
        }

        $save = false;

        $setfields = $request->get('setfields');

        if (is_array($setfields) && count($setfields)) {
            foreach ($setfields as $set) {
                if ($set) {
                    $key = $set['set'];
                    $type = false;
                    $value = $set['val'];
                    if (array_key_exists('type', $set)) {
                        $type = $set['type'];
                    }
                    if (!$type || $type == 'default') {
                        $mth = ( method_exists($item, $key) ?  $key : false );
                        if ($mth) {
                            call_user_func($mth, $item, $value);
                            $save = true;
                        }
                    } else if ($rel = $this->getEntity($type)) {
                        $mth = false;
                        if ( method_exists($item, $key) ) { $mth = $key; }
                        else if ( method_exists($item, ('set' . ucfirst($key))) ) { $mth = 'set' . ucfirst($key); }
                        else if ( method_exists($item, ('add' . ucfirst($key))) ) { $mth = 'add' . ucfirst($key); }
                        if ($mth) {
                            $rob = $this->getEntityRepository($rel)->findOneById(intval($value));
                            if ($rob) {
                                call_user_func($mth, $item, $rob);
                                $save = true;
                            }
                        }
                    }
                }
            }
        }

        if ($save) {
            $em = $this->getDoctrine()->getManager();

            if ($clone) {
                $this->cloneItemChildren($entity, $item, $result);
            }

			if (!$item->getId() && method_exists($item, 'getTranslations') && !count($item->getTranslations())) {
			    $locs = $this->container->getParameter('a2lix_translation_form.locales');
			    foreach ($locs as $loc) {
			        $clnm = $this->getEntityClass($entity).'Translation';
			        $tran = new $clnm;
			        $tran->setLocale($loc);
			        $item->addTranslation($tran);
			        $em->persist($tran);
			    }
			}

            $em->persist($item);
            $em->flush();

            return $this->renderTemplate($request, $entity, 'show', array(
                'entity' => $entity['name'],
                'item' => $item,
                'details' => $this->getEntityDetails($entity, $item)
            ));
        }

        return $this->returnError($request, 'nothing-done');
    }

    public function removeAction(Request $request, $entity, $id)
    {
        $entity = $this->getEntity($entity);
        if (!$entity) {
            return $this->returnError($request, 'entity-not-found');
        }

        $item = $this->getEntityRepository($entity)->findOneById($id);

        if (!$item) {
            return $this->returnError($request, 'item-not-found');
        }

        $form = $this->createFormBuilder($item)
            ->add('remove', 'submit', array(
                'attr' => array(
                    'class' => 'btn-danger'
                )
            ))
            ->getForm()
        ;

        $form->handleRequest($request);

        if ($form->isValid()) {

            $em = $this->getDoctrine()->getManager();

            if (method_exists($item, 'setRemoved')) {
                $item->setRemoved(true);
            } else {
                $em->remove($item);
            }

            $em->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(array('success' => true), 200);
            }
            else {
                return $this->redirect($this->generateUrl('maci_admin_entity', array(
                    'entity' => $entity['name'],
                    'message' => 'form.removed'
                )));
            }

        }

        return $this->renderTemplate($request, $entity, 'remove', array(
            'entity' => $entity['name'],
            'item' => $item,
            'form' => $form->createView()
        ));
    }

    public function reorderAction(Request $request, $entity)
    {
        $entity = $this->getEntity($entity);
        if (!$entity) {
            return $this->returnError($request, 'entity-not-found');
        }

        $ids = $this->getRequest()->get('ids');

        if (count($ids) == 0) {
            return $this->returnError($request, 'ids-not-found');
        }

        if ( !method_exists($this->getEntityNewObj($entity), 'setPosition') ) {
            return $this->returnError($request, 'method-not-found');
        }

        $repo = $this->getEntityRepository($entity);

        $counter = 0;

        foreach ($ids as $id) {
            $item = $repo->findOneById($id);
            if ( $item ) {
                $item->setPosition($counter);
            }
            $counter++;
        }

        $this->getDoctrine()->getManager()->flush();

        return new JsonResponse(array('success' => true), 200);
    }

    public function fileUploadAction(Request $request, $entity, $id)
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->returnError($request, 'action-denied');
        }

        $entity = $this->getEntity($entity);

        if (!$entity) {
            return $this->returnError($request, 'entity-not-found');
        }

        if (!count($request->files)) {
            return $this->renderTemplate($request, $entity, 'uploader', array(
                'entity' => $entity['name']
            ));
        }

        $em = $this->getDoctrine()->getManager();

        $repo = $this->getEntityRepository($entity);

        $item = $this->getEntityNewObj($entity);

        if ($id) {
            $item = $repo->findOneById($id);
            if (!$item) {
                return $this->returnError($request, 'item-not-found');
            }
            $name = $request->files->keys()[0];
            $file = $request->files->get($name);
		    if($file->isValid()) {
		        $item->setFile($file);
		        $em->persist($item);
		    } else {
                return $this->returnError($request, 'on-upload');
            }
        } else {
            $name = $request->files->keys()[0];
            $file = $request->files->get($name);
            if($file->isValid()) {
                if (method_exists($item, 'getTranslations')) {
                    $locs = $this->container->getParameter('a2lix_translation_form.locales');
                    $date = date('m-d-Y h:i:s');
                    foreach ($locs as $loc) {
                        $clnm = $this->getEntityClass($entity).'Translation';
                        $tran = new $clnm;
                        $tran->setLocale($loc);
                        $traname = $entity['label'].' [' . $loc . '] ' . $date;
                        $tran->setName($traname);
                        $item->addTranslation($tran);
                        $em->persist($tran);
                    }
                }
                $item->setFile($file);
                $em->persist($item);
            } else {
                return $this->returnError($request, 'on-upload');
            }
        }

        $em->flush();

        return $this->renderTemplate($request, $entity, 'show', array(
            'entity' => $entity['name'],
            'item' => $item,
            'details' => $this->getEntityDetails($entity, $item)
        ));
    }

    public function renderTemplate(Request $request, $entity, $action, $params)
    {
        if ( array_key_exists('templates', $entity) && array_key_exists($action, $entity['templates'])) {
            $template = $entity['templates'][$action];
        } else {
            $template = 'MaciAdminBundle:Default:_' . $action .'.html.twig';
        }

        if ($request->isXmlHttpRequest()) {
            $id = 0;
            if (array_key_exists('item', $params)) {
                $item = $params['item'];
                $id = $item->getId();
            }
            if ($request->get('modal')) {
                return new JsonResponse(array(
                    'success' => true,
                    'id' => $id,
                    'entity' => $entity['name'],
                    'template' => $this->renderView('MaciAdminBundle:Default:async.html.twig', array(
                        'params' => $params,
                        'template' => $template
                    ))
                ), 200);
            } else {
                return new JsonResponse(array(
                    'success' => true,
                    'id' => $id,
                    'entity' => $entity['name'],
                    'template' => $this->renderView($template, $params)
                ), 200);
            }
        } else {
            return $this->render('MaciAdminBundle:Default:' . $action .'.html.twig', array(
                'entity_label' => $entity['label'],
                'entity' => $entity['name'],
                'params' => $params,
                'template' => $template
            ));
        }
    }

    public function returnError($request, $error)
    {
        $message = ( 'error.' . $error );
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(array('success' => false, 'error' => $message), 200);
        } else {
            return $this->redirect($this->generateUrl('maci_admin', array('error' => $message)));
        }
    }

    public function cloneItem($entity, $result)
    {
        $cnm = $this->getEntityClass($entity);
        $item = new $cnm;
        $fields = $this->getEntityFields($entity);
        foreach ($fields as $field) {
            if (method_exists($item, 'set'.ucfirst($field))) {
                call_user_func('set'.ucfirst($field), $item, call_user_func('get'.ucfirst($field), $result));
            }
        }
        if (method_exists($item, 'getTranslations')) {
            $translatons = $result->getTranslations();
            $fields = $this->getEntityTranslationFields($this->getEntityClass($entity) . 'Translation');
            $tcname = $item->getTranslationEntityClass();
            foreach ($translatons as $translaton) {
                $tc = new $tcname;
                foreach ($fields as $field) {
                    if (method_exists($tc, 'set'.ucfirst($field))) {
                        call_user_func('set'.ucfirst($field), $tc, call_user_func('get'.ucfirst($field), $translaton));
                    }
                }
                $item->addTranslation($tc);
                $tc->setTranslatable($item);
            }
        }
        return $item;
    }

    public function cloneItemChildren($entity, $item, $result)
    {
        $em = $this->getDoctrine()->getManager();
        if (method_exists($item, 'getChildren')) {
            $children = $result->getChildren();
            foreach ($children as $child) {
                $cc = clone $child;
                $item->addChild($cc);
                $cc->setParent($item);
                $em->persist($cc);
                if (method_exists($cc, 'getTranslations')) {
                    $translatons = $child->getTranslations();
                    $fields = $this->getEntityTranslationFields(get_class($cc) . 'Translation');
                    $tcname = $cc->getTranslationEntityClass();
                    foreach ($translatons as $translaton) {
                        $tc = new $tcname;
                        foreach ($fields as $field) {
                            if (method_exists($tc, 'set'.ucfirst($field))) {
                                call_user_func('set'.ucfirst($field), $tc, call_user_func('get'.ucfirst($field), $translaton));
                            }
                        }
                        $cc->addTranslation($tc);
                        $tc->setTranslatable($cc);
                        $em->persist($tc);
                    }
                }
            }
        }
    }

    public function getEntities()
    {
        if ( !is_array($this->_entities) ) {
            $this->_entities = $this->container->getParameter('maci.admin.entities');
            foreach ($this->_entities as $name => $entity) {
                if (!array_key_exists('label', $entity)) {
                    $this->_entities[$name]['label'] = $this->generateLabel($name);
                }
                $this->_entities[$name]['name'] = $name;
            }
        }

        return $this->_entities;
    }

    public function getEntity($name)
    {
        $entities = $this->getEntities();
        if (array_key_exists($name, $entities)) {
            return $entities[$name];
        }
        return false;
    }

    public function getAdminBundle()
    {
        return $this->get('kernel')->getBundle('MaciAdminBundle');
    }

    public function getEntityBundle($entity)
    {
        return $this->get('kernel')->getBundle(split(':', $entity['class'])[0]);
    }

    public function getEntityClass($entity)
    {
        $repo = $this->getEntityRepository($entity);
        return $repo->getClassName();
    }

    public function getEntityDetails($entity, $object)
    {
        $fields = $this->getEntityFields($entity);

        $details = array();

        foreach ($fields as $field) {

            $value = null;

            $uf = ucfirst($field);

            if (method_exists($object, ('is'.$uf))) {
                $value = ( call_user_func(('is'.$uf), $object) ? 'True' : 'False' );
            } else if (method_exists($object, ('get'.$uf.'Label'))) {
                $value = call_user_func(('get'.$uf.'Label'), $object);
            } else if (method_exists($object, ('get'.$uf))) {
                $value = call_user_func(('get'.$uf), $object);
                if (is_object($value) && get_class($value) === 'DateTime') {
                    $value = $value->format("Y-m-d H:i:s");
                }
            } else if (method_exists($object, $field)) {
                $value = call_user_func($field, $object);
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

        foreach ($metadata->associationMappings as $fieldName => $relation) {
            if ($relation['type'] !== ClassMetadataInfo::ONE_TO_MANY) {
                $fields[] = $fieldName;
            }
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
        $session = $this->get('session');
        return $session->get('admin_filters_'.$entity['name'], array());
    }

    public function hasEntityFilters($entity)
    {
        return !!count($this->getEntityFilters($entity));
    }

    public function setEntityFilters($entity, $filters)
    {
        $session = $this->get('session');
        $session->set('admin_filters_'.$entity['name'], $filters);
    }

    public function removeEntityFilters($entity)
    {
        $session = $this->get('session');
        $session->set('admin_filters_'.$entity['name'], array());
    }

    public function getEntityFilterFields($entity)
    {
        if (array_key_exists('filters', $entity) && count($entity['filters'])) {
            return $entity['filters'];
        }
        return array();
    }

    public function getEntityFiltersForm($entity)
    {
        $form = $entity['name'];
        $object = $this->getEntityNewObj($entity);
        $filters = $this->getEntityFilters($entity);
        foreach ($filters as $field => $filter) {
            $method = ('set' . ucfirst($field));
            if ( method_exists($object, $method) ) {
                call_user_func_array($method, $object, array($filter));
            }
        }
        $fields = $this->getEntityFilterFields($entity);
        return $this->generateEntityForm($fields, $entity, $object);
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
        if (strpos('\\', $form) && class_exists($form)) {
            return $this->createForm(new $form, $object);
        } else if ($this->container->get('form.registry')->hasType($form)) {
            return $this->createForm($form, $object);
        }
        $fields = $this->getEntityFields($entity);
        return $this->generateEntityForm($fields, $entity, $object);
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
        return $this->getDoctrine()->getManager()
            ->getClassMetadata( $this->getEntityClass($entity) );
    }

    public function getEntityNewObj($entity)
    {
        $class = $this->getEntityClass($entity);
        return new $class;
    }

    public function getEntityRepository($entity)
    {
        return $this->getDoctrine()->getRepository($entity['class']);
    }

    public function generateLabel($name)
    {
        $str = str_replace('_', ' ', $name);
        return ucwords($str);
    }

    public function generateEntityForm($fields, $entity, $object)
    {
        $form = $this->createFormBuilder($object);

        foreach ($fields as $field) {
            $method = ('get' . ucfirst($field) . 'Array');
            if ( method_exists($object, $method) ) {
                $form->add($field, 'choice', array(
                    'empty_value' => '',
                    'choices' => call_user_func($method, $object)
                ));
            } else {
                $form->add($field);
            }
        }

        $form
            ->add('reset', 'reset')
            ->add('save', 'submit')
        ;

        return $form->getForm();
    }
}
