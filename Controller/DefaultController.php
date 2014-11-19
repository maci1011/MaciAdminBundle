<?php

namespace Maci\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

use A2lix\I18nDoctrineBundle\Annotation\I18nDoctrine;

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

    public function navAction()
    {
        return $this->render('MaciAdminBundle:Default:_nav.html.twig', array(
            'entities' => $this->getEntities()
        ));
    }

    public function showAction(Request $request, $entity, $id)
    {
        $entity = $this->getEntity($entity);
        if (!$entity) {
            return $this->redirect($this->generateUrl('maci_admin', array('error' => 'error.notfound')));
        }

        $item = $this->getEntityRepository($entity)->findOneById($id);

        if (!$item) {
            return $this->redirect($this->generateUrl('maci_admin', array('error' => 'error.notfound')));
        }

        return $this->renderTemplate($request, $entity, 'show', array(
            'entity' => $entity,
            'item' => $item,
            'details' => $this->getItemDetails($entity, $item)
        ));
    }

    /**
     * @I18nDoctrine
     */
    public function formAction(Request $request, $entity, $id)
    {
        $entity = $this->getEntity($entity);
        if (!$entity) {
            return $this->redirect($this->generateUrl('maci_admin', array('error' => 'error.entitynotfound')));
        }

        $item = $this->getEntityNewObj($entity);

        if ($id) {
            $item = $this->getEntityRepository($entity)->findOneById($id);

            if (!$item) {
                return $this->redirect($this->generateUrl('maci_admin', array('error' => 'error.notfound')));
            }
        }

        $form = $this->getEntityForm($entity, $item);

        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($item);
            $em->flush();
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(array('success' => true, 'id' => $item->getId()), 200);
            }
            // else {
            //     return $this->redirect($this->generateUrl('maci_admin_entity_list', array(
            //         'entity' => $entity['name'],
            //         'message' => 'form.add'
            //     )));
            // }
        }

        return $this->renderTemplate($request, $entity, 'form', array(
            'entity' => $entity,
            'item' => $item,
            'form' => $form->createView()
        ));
    }

    public function listAction(Request $request, $entity)
    {
        $entity = $this->getEntity($entity);
        if (!$entity) {
            return $this->redirect($this->generateUrl('maci_admin', array('error' => 'error.notfound')));
        }

        $repo = $this->getEntityRepository($entity);

        $list = $repo->findAll();

        return $this->renderTemplate($request, $entity, 'list', array(
            'entity' => $entity,
            'list' => $list
        ));
    }

    public function objectAction(Request $request, $entity, $id)
    {
        $entity = $this->getEntity($entity);
        if (!$entity || !$request->isXmlHttpRequest()) {
            return new JsonResponse(array('error' => 'error.noadmin'), 200);
        }

        $em = $this->getDoctrine()->getManager();

        $item = false;

        if ($id) {
            $item = $em->getRepository($entity['repository'])->findOneById($id);
            if (!$item) {
                return new JsonResponse(array('error' => 'error.noitem'), 200);
            }
        } else {
            $item = new $entity['new'];
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
                            call_user_method($mth, $item, $value);
                            $save = true;
                        }
                    } else if ($rel = $this->getEntity($type)) {
                        $mth = (
                            method_exists($item, ('set' . ucfirst($key))) || method_exists($item, ('add' . ucfirst($key))) ? (
                                method_exists($item, ('set' . ucfirst($key))) ?
                                'set' . ucfirst($key) :
                                'add' . ucfirst($key)
                            ) : false
                        );
                        if ($mth) {
                            $rob = $em->getRepository($rel['repository'])->findOneById(intval($value));
                            if ($rob) {
                                call_user_method($mth, $item, $rob);
                                $save = true;
                            }
                        }
                    }
                }
            }
        }

        if ($save) {
			if (!$item->getId() && method_exists($item, 'getTranslations')) {
			    $locs = $this->container->getParameter('a2lix_translation_form.locales');
			    foreach ($locs as $loc) {
			        $clnm = $entity['new'].'Translation';
			        $tran = new $clnm;
			        $tran->setLocale($loc);
			        $item->addTranslation($tran);
			        $em->persist($tran);
			    }
			}

            $em->persist($item);
            $em->flush();

            return new JsonResponse(array('success' => true, 'id' => $item->getId()), 200);
        }

        return new JsonResponse(array('error' => 'error.nothingdone'), 200);
    }

    public function removeAction(Request $request, $entity, $id)
    {
        $entity = $this->getEntity($entity);
        if (!$entity || !$request->isXmlHttpRequest()) {
            return new JsonResponse(array('error' => 'error.noadmin'), 200);
        }

        $em = $this->getDoctrine()->getManager();
        $item = $em->getRepository($entity['repository'])->findOneById($id);

        if (!$item) {
            return new JsonResponse(array('error' => 'error.not-found'), 200);
        }

        if (method_exists($item, 'setRemoved')) {
            $item->setRemoved(true);
        } else {
            $em->remove($item);
        }

        $em->flush();

        return new JsonResponse(array('success' => true), 200);
    }

    public function reorderAction(Request $request, $entity)
    {
        $entity = $this->getEntity($entity);
        if (!$entity) {
            return $this->redirect($this->generateUrl('maci_admin', array('error' => 'error.notfound')));
        }

        $ids = $this->getRequest()->get('ids');

        $this->getDoctrine()->getRepository($entity['repository'])->reorder($ids);

        return new JsonResponse(array('success' => true), 200);
    }

    public function fileUploadAction(Request $request, $entity, $id)
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->redirect($this->generateUrl('maci_admin', array('error' => 'error.action_denied')));
        }

        $entity = $this->getEntity($entity);

        if (!$entity) {
            return new JsonResponse(array('success' => false, 'error' => 'error.noadmin'), 200);
        }

        if (!count($request->files)) {
            return $this->renderTemplate($request, $entity, 'uploader', array(
                'entity' => $entity
            ));
        }

        $em = $this->getDoctrine()->getManager();

        $repo = $em->getRepository($entity['repository']);

        $item = new $entity['new'];

        if ($id) {
            $item = $repo->findOneById($id);
            if (!$item) {
                return new JsonResponse(array('success' => false, 'error' => 'error.notfound'), 200);
            }
            $name = $request->files->keys()[0];
            $file = $request->files->get($name);
		    if($file->isValid()) {
		        $item->setFile($file);
		        $em->persist($item);
		    } else {
                return new JsonResponse(array('success' => false, 'error' => 'error.upload'), 200);
            }
        } else {
            $name = $request->files->keys()[0];
            $file = $request->files->get($name);
            if($file->isValid()) {
                if (method_exists($item, 'getTranslations')) {
                    $locs = $this->container->getParameter('a2lix_translation_form.locales');
                    $date = date('m/d/Y h:i:s');
                    foreach ($locs as $loc) {
                        $clnm = $entity['new'].'Translation';
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
                return new JsonResponse(array('success' => false, 'error' => 'error.upload'), 200);
            }
        }

        $em->flush();

        return $this->renderTemplate($request, $entity, 'item', array(
            'entity' => $entity,
            'item' => $item
        ));
    }
/*
    public function pagerAction()
    {
        $entityLimitPerPage = $this->getBlogConfig('admin_limit_per_page');
        $repo = $this->getDoctrine()->getEntityManager()->getRepository('Eight\BlogBundle\Entity\Post');
        $page = $this->getRequest()->get('page', 1);

        $pager = new PostPager($repo, $entityLimitPerPage, $page, 5);

        return $this->render('EightBlogBundle:Admin:list.html.twig', array(
            'posts' => $this->container->get('eight.posts')->getPaged($entityLimitPerPage, $page),
            'pager' => $pager
        ));
    }
*/
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
                'entity' => $entity,
                'params' => $params,
                'template' => $template
            ));
        }
    }

    public function generateLabel($name)
    {
        $str = str_replace('_', ' ', $name);
        return ucwords($str);
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

    public function getEntityRepository($entity)
    {
        return $this->getDoctrine()->getRepository($entity['class']);
    }

    public function getEntityClass($entity)
    {
        $repo = $this->getEntityRepository($entity);
        return $repo->getClassName();
    }

    public function getEntityMetadata($entity)
    {
        return $this->getDoctrine()->getManager()
            ->getClassMetadata( $this->getEntityClass($entity) );
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

    public function getEntityNewObj($entity)
    {
        $class = $this->getEntityClass($entity);
        return new $class;
    }

    public function getItemDetails($entity, $object)
    {
        $fields = $this->getEntityFields($entity);

        $details = array();

        foreach ($fields as $field) {

            $value = null;

            $uf = ucfirst($field);

            if (method_exists($object, ('is'.$uf))) {
                $value = ( call_user_method(('is'.$uf), $object) ? 'True' : 'False' );
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

    public function generateEntityForm($entity, $object)
    {
        $fields = $this->getEntityFields($entity);

        $form = $this->createFormBuilder($object);

        foreach ($fields as $field) {

            $form->add($field);

        }

        $form
            ->add('reset', 'reset')
            ->add('save', 'submit')
        ;

        return $form->getForm();
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
        return $this->generateEntityForm($entity, $object);
    }
}
