<?php

namespace Maci\AdminBundle\Controller;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\JsonResponse;

class DefaultController extends Controller
{
    private $om;

    private $session;

    private $request;

    private $mcm;

    public function __construct(ObjectManager $objectManager, Session $session, RequestStack $requestStack, $mcm)
    {
        $this->om = $objectManager;
        $this->session = $session;
        $this->request = $requestStack->getCurrentRequest();
        $this->mcm = $mcm;
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

    public function newAction($section, $entity, $id)
    {
        return ( $id ? false : $this->mcmForm($section, $entity, $id) );
    }

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
    ---> Generic Actions
*/

    public function mcmDashboard($section, $entity, $id)
    {
        $entity = $this->mcm->getEntity($section, $entity);

        return $this->mcm->getDefaultEntityParams($entity);
    }

    public function mcmList($section, $entity, $id)
    {
        $entity = $this->mcm->getEntity($section, $entity);

        $list = $this->mcm->getItems($entity);

        $pager = $this->mcm->getPager($list);

        if (!$pager) {
            return false;
        }

        return array_merge($this->mcm->getDefaultEntityParams($entity), array(
            'pager' => $pager
        ));
    }

    public function mcmShow($section, $entity, $id)
    {
        $entity = $this->mcm->getEntity($section, $entity);

        if (!$id) {
            $this->session->getFlashBag()->add('error', 'Missing Id.');
            return false;
        }

        $item = $this->mcm->getRepository($entity)->findOneById($id);

        if (!$item) {
            return false;
        }

        return array_merge($this->mcm->getDefaultEntityParams($entity), array(
            'item' => $item,
            'details' => $this->mcm->getItemDetails($entity, $item)
        ));
    }

    public function mcmForm($section, $entity, $id)
    {
        $entity = $this->mcm->getEntity($section, $entity);

        $item = null;

        // $clone = $this->request->get('clone', false);

        if ($id) {
            $item = $this->mcm->getRepository($entity)->findOneById($id);
            if (!$item) {
                $this->session->getFlashBag()->add('error', 'Item [' . $id . '] for ' . $this->mcm->getClass($entity) . ' not found.');
                return false;
            }
            // if ($clone) {
            //     $item = $this->cloneItem($entity, $item);
            // } else {
            //     $item = $item;
            // }
        } else {
            $item = $this->mcm->getNewItem($entity);
        }

        $form = $this->mcm->getForm($entity, $item);
        $form->handleRequest($this->request);

        $params = $this->mcm->getDefaultEntityParams($entity);

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
        $entity = $this->mcm->getEntity($section, $entity);

        if (!$id) {
            $this->session->getFlashBag()->add('error', 'Missing Id.');
            return false;
        }

        $item = $this->mcm->getRepository($entity)->findOneById($id);
        if (!$item) {
            return false;
        }

        $form = $this->mcm->getRemoveForm($entity,$item);

        $form->handleRequest($this->request);

        $params = $this->mcm->getDefaultEntityParams($entity);

        if ($form->isValid()) {

            if (method_exists($item, 'setRemoved')) {
                $item->setRemoved(true);
            } else {
                $this->om->remove($item);
            }

            $this->om->flush();

            $this->session->getFlashBag()->add('success', 'Item [' . $id . '] for ' . $this->mcm->getClass($entity) . ' removed.');

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

    public function mcmUploader($section, $entity, $id)
    {
        $entity = $this->mcm->getEntity($section, $entity);

        $params = $this->mcm->getDefaultEntityParams($entity);

        if (!$this->request->isXmlHttpRequest() || $this->request->getMethod() !== 'POST') {
            return $params;
        }

        if (!count($this->request->files)) {
            return array_merge($params,array('success' => false, 'error' => 'No file(s).'));
        }

        $name = $this->request->files->keys()[0];
        $file = $this->request->files->get($name);

        if(!$file->isValid()) {
            return array_merge($params,array('success' => false, 'error' => 'Upload failed.'));
        }

        // if ($id) {
        //     $item = $this->mcm->getRepository($entity)->findOneById($id);
        //     if (!$item) {
        //         return array_merge($params,array('success' => false, 'error' => 'Item not found.'));
        //     }
        //     $item->setFile($file);
        // } else {}

        $item = $this->mcm->getNewItem($entity);
        $item->setFile($file);
        $this->om->persist($item);

        $this->om->flush();

        return array('success' => true);
    }

/*
    ---> Generic Relations Actions
*/

    public function mcmRelations($section, $entity, $id)
    {
        $relAction = $this->request->get('relAction');

        if ($relAction === 'list' || $relAction === 'show') {
            return $this->mcmRelationsList($section, $entity, $id);

        } else if ($relAction === 'add' || $relAction === 'set') {
            return $this->mcmRelationsAdd($section, $entity, $id);

        } else if ($relAction === 'bridge') {
            return $this->mcmBridge($section, $entity, $id);

        } else if ($relAction === 'uploader') {
            if ($this->request->get('bridge')) {
                return $this->mcmBridgeUploader($section, $entity, $id);
            }
            return $this->mcmRelationsUploader($section, $entity, $id);

        } else if ($relAction === 'remove') {
            return $this->mcmRelationsRemove($section, $entity, $id);

        } else if ($relAction === 'reorder') {
            return $this->mcmRelationsReorder($section, $entity, $id);
        }

        return false;
    }

    public function mcmRelationsList($section, $entity, $id)
    {
        $entity = $this->mcm->getEntity($section, $entity);

        $item = $this->mcm->getRepository($entity)->findOneById($id);
        if (!$item) {
            $this->session->getFlashBag()->add('error', 'Item [' . $_id . '] for ' . $this->mcm->getClass($entity) . ' not found.');
            return false;
        }

        $relation = $this->mcm->getCurrentRelation($entity);
        if (!$relation) {
            $this->session->getFlashBag()->add('error', 'Relation ' . $relationName . ' in ' . $this->mcm->getClass($entity) . ' not found.');
            return false;
        }

        $list = $this->mcm->getRelationItems($relation, $item);

        $pager = $this->mcm->getPager($list);

        if (!$pager) {
            return false;
        }

        $params = $this->mcm->getDefaultRelationParams($entity, $relation, $item);

        $params['pager'] = $pager;

        return $params;
    }

    public function mcmRelationsAdd($section, $entity, $id)
    {
        $entity = $this->mcm->getEntity($section, $entity);

        $item = $this->mcm->getRepository($entity)->findOneById($id);
        if (!$item) {
            $this->session->getFlashBag()->add('error', 'Item [' . $_id . '] for ' . $this->mcm->getClass($entity) . ' not found.');
            return false;
        }

        $relation = $this->mcm->getCurrentRelation($entity);
        if (!$relation) {
            $this->session->getFlashBag()->add('error', 'Relation ' . $relationName . ' in ' . $this->mcm->getClass($entity) . ' not found.');
            return false;
        }

        $list = $this->mcm->getItemsForRelation($entity, $relation, $item);

        $params = $this->mcm->getDefaultRelationParams($entity, $relation, $item);

        if ($this->request->getMethod() === 'POST') {

            $this->mcm->addRelationItemsFromRequestIds($entity, $relation, $item, $list);

            $params['redirect'] = 'maci_admin_view';
            $params['redirect_params'] = array(
                'section' => $section,
                'entity' => $entity['name'],
                'action'=>'relations',
                'id' => $id,
                'relation' => $relation['association'],
                'relAction' => $this->mcm->getRelationDefaultAction($entity, $relation['association'])
            );

        }

        $pager = $this->mcm->getPager($list);

        if (!$pager) {
            return false;
        }

        $params['pager'] = $pager;

        return $params;
    }

    public function mcmBridge($section, $entity, $id)
    {
        $entity = $this->mcm->getEntity($section, $entity);

        $item = $this->mcm->getRepository($entity)->findOneById($id);
        if (!$item) {
            $this->session->getFlashBag()->add('error', 'Item [' . $_id . '] for ' . $this->mcm->getClass($entity) . ' not found.');
            return false;
        }

        $relation = $this->mcm->getCurrentRelation($entity);
        if (!$relation) {
            $this->session->getFlashBag()->add('error', 'Relation ' . $this->request->get('relation') . ' in ' . $this->mcm->getClass($entity) . ' not found.');
            return false;
        }

        $bridge = $this->mcm->getCurrentBridge($relation);
        if (!$bridge) {
            $this->session->getFlashBag()->add('error', 'Relation ' . $this->request->get('bridge') . ' in ' . $this->mcm->getClass($relation) . ' not found.');
            return false;
        }

        $list = $this->mcm->getItemsForRelation($entity, $relation, $item, $bridge);

        $params = $this->mcm->getDefaultBridgeParams($entity, $relation, $bridge, $item);

        if ($this->request->getMethod() === 'POST') {

            $this->mcm->addRelationBridgedItemsFromRequestIds($entity, $relation, $bridge, $item, $list);

            $params['redirect'] = 'maci_admin_view';
            $params['redirect_params'] = array(
                'section' => $section,
                'entity' => $entity['name'],
                'action'=>'relations',
                'id' => $id,
                'relation' => $relation['association'],
                'relAction' => $this->mcm->getRelationDefaultAction($entity, $relation['association'])
            );

        }

        $pager = $this->mcm->getPager($list);

        if (!$pager) {
            return false;
        }

        $params['pager'] = $pager;

        return $params;
    }

    public function mcmRelationsUploader($section, $entity, $id)
    {
        $entity = $this->mcm->getEntity($section, $entity);

        $item = $this->mcm->getRepository($entity)->findOneById($id);
        if (!$item) {
            $this->session->getFlashBag()->add('error', 'Item [' . $_id . '] for ' . $this->mcm->getClass($entity) . ' not found.');
            return false;
        }

        $relation = $this->mcm->getCurrentRelation($entity);
        if (!$relation) {
            $this->session->getFlashBag()->add('error', 'Relation ' . $this->request->get('relation') . ' in ' . $this->mcm->getClass($entity) . ' not found.');
            return false;
        }

        $params = $this->mcm->getDefaultRelationParams($entity, $relation, $item);

        if (!$this->request->isXmlHttpRequest() || $this->request->getMethod() !== 'POST') {
            return $params;
        }

        if (!count($this->request->files)) {
            return array_merge($params,array('success' => false, 'error' => 'No file(s).'));
        }

        $name = $this->request->files->keys()[0];
        $file = $this->request->files->get($name);

        if(!$file->isValid()) {
            return array_merge($params,array('success' => false, 'error' => 'Upload failed.'));
        }

        $new = $this->mcm->getNewItem($relation);
        $new->setFile($file);
        $this->om->persist($new);

        $this->mcm->addRelationItems($entity, $relation, $item, array($new));

        $this->om->flush();

        return array('success' => true);
    }

    public function mcmBridgeUploader($section, $entity, $id)
    {
        $entity = $this->mcm->getEntity($section, $entity);

        $item = $this->mcm->getRepository($entity)->findOneById($id);
        if (!$item) {
            $this->session->getFlashBag()->add('error', 'Item [' . $_id . '] for ' . $this->mcm->getClass($entity) . ' not found.');
            return false;
        }

        $relation = $this->mcm->getCurrentRelation($entity);
        if (!$relation) {
            $this->session->getFlashBag()->add('error', 'Relation ' . $this->request->get('relation') . ' in ' . $this->mcm->getClass($entity) . ' not found.');
            return false;
        }

        $bridge = $this->mcm->getCurrentBridge($relation);
        if (!$bridge) {
            $this->session->getFlashBag()->add('error', 'Relation ' . $this->request->get('bridge') . ' in ' . $this->mcm->getClass($relation) . ' not found.');
            return false;
        }

        $params = $this->mcm->getDefaultBridgeParams($entity, $relation, $bridge, $item);

        if (!$this->request->isXmlHttpRequest() || $this->request->getMethod() !== 'POST') {
            return $params;
        }

        if (!count($this->request->files)) {
            return array_merge($params,array('success' => false, 'error' => 'No file(s).'));
        }

        $name = $this->request->files->keys()[0];
        $file = $this->request->files->get($name);

        if(!$file->isValid()) {
            return array_merge($params,array('success' => false, 'error' => 'Upload failed.'));
        }

        $new = $this->mcm->getNewItem($bridge);
        $new->setFile($file);
        $this->om->persist($new);

        $this->mcm->addBridgeItems($entity, $relation, $bridge, $item, array($new));

        $this->om->flush();

        return array('success' => true);
    }

    public function mcmRelationsRemove($section, $entity, $id)
    {
        $entity = $this->mcm->getEntity($section, $entity);

        $item = $this->mcm->getRepository($entity)->findOneById($id);
        if (!$item) {
            $this->session->getFlashBag()->add('error', 'Item [' . $_id . '] for ' . $this->mcm->getClass($entity) . ' not found.');
            return false;
        }

        $relation = $this->mcm->getCurrentRelation($entity);
        if (!$relation) {
            $this->session->getFlashBag()->add('error', 'Relation ' . $relationName . ' in ' . $this->mcm->getClass($entity) . ' not found.');
            return false;
        }

        $list = $this->mcm->getRelationItems($relation, $item);

        $params = $this->mcm->getDefaultRelationParams($entity, $relation, $item);

        if ($this->request->getMethod() === 'POST') {

            $this->mcm->removeRelationItemsFromRequestIds($entity, $relation, $item, $list);

            $params['redirect'] = 'maci_admin_view';
            $params['redirect_params'] = array(
                'section' => $section,
                'entity' => $entity['name'],
                'action'=>'relations',
                'id' => $id,
                'relation' => $relation['association'],
                'relAction' => $this->mcm->getRelationDefaultAction($entity, $relation['association'])
            );

        }

        return $params;
    }

    public function mcmRelationsReorder($section, $entity, $id)
    {
        if (!$this->request->isXmlHttpRequest() || $this->request->getMethod() !== 'POST') {
            return false;
        }

        $ids = $this->request->get('ids', array());

        if (count($ids)<2) {
            return array('success' => false, 'error' => 'Reorder: No ids.');
        }

        $entity = $this->mcm->getEntity($section, $entity);

        $item = $this->mcm->getRepository($entity)->findOneById($id);
        if (!$item) {
            $this->session->getFlashBag()->add('error', 'Item [' . $_id . '] for ' . $this->mcm->getClass($entity) . ' not found.');
            return false;
        }

        $relation = $this->mcm->getCurrentRelation($entity);
        if (!$relation) {
            $this->session->getFlashBag()->add('error', 'Relation ' . $this->request->get('relation') . ' in ' . $this->mcm->getClass($entity) . ' not found.');
            return false;
        }

        // $params = $this->mcm->getDefaultRelationParams($entity, $relation, $item);

        if ( !method_exists($this->mcm->getNewItem($relation), 'setPosition') ) {
            return array('success' => false, 'error' => 'Reorder: Method not found.');
        }

        $repo = $this->mcm->getRepository($relation);

        $counter = 0;

        foreach ($ids as $id) {
            $item = $repo->findOneById($id);
            if ( $item ) {
                $item->setPosition($counter);
            }
            $counter++;
        }

        $this->om->flush();

        return array('success' => true);
    }

}
