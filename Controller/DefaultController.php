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

    public function dashboardAction()
    {
        return $this->mcmDashboard();
    }

    public function listAction()
    {
        return $this->mcmList();
    }

    public function trashAction()
    {
        return $this->mcmList();
    }

    public function showAction()
    {
        return $this->mcmShow();
    }

    public function newAction()
    {
        return ( $this->request->get('id') ? false : $this->mcmForm() );
    }

    public function editAction()
    {
        return ( $this->request->get('id') ? $this->mcmForm() : false );
    }

    public function removeAction()
    {
        return $this->mcmRemove();
    }

    public function relationsAction()
    {
        return $this->mcmRelations();
    }

    public function uploaderAction()
    {
        return $this->mcmUploader();
    }

/*
    ---> Generic Actions
*/

    public function mcmDashboard()
    {
        return $this->mcm->getDefaultParams();
    }

    public function mcmList()
    {
        $entity = $this->mcm->getCurrentEntity();
        if (!$entity) return false;

        $list = $this->mcm->getItems($entity);

        $pager = $this->mcm->getPager($list);

        if (!$pager) {
            return false;
        }

        return array_merge($this->mcm->getDefaultEntityParams($entity), array(
            'pager' => $pager
        ));
    }

    public function mcmShow()
    {
        $entity = $this->mcm->getCurrentEntity();
        if (!$entity) return false;

        $item = $this->mcm->getCurrentItem();
        if (!$item) return false;

        return array_merge($this->mcm->getDefaultEntityParams($entity), array(
            'item' => $item
        ));
    }

    public function mcmForm()
    {
        $entity = $this->mcm->getCurrentEntity();
        if (!$entity) return false;

        $item = null;

        // $clone = $this->request->get('clone', false);

        if ((int) $this->request->get('id', 0)) {
            $item = $this->mcm->getCurrentItem();
            if (!$item) return false;
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

            if ($this->request->isXmlHttpRequest())
                return array('success' => true);
            else {
                if ($form->has('save_and_add') && $form->get('save_and_add')->isClicked())
                    return $this->mcm->getDefaultEntityRedirectParams($entity, 'new');
                else if ($form->has('save_and_list') && $form->get('save_and_list')->isClicked())
                    return $this->mcm->getDefaultEntityRedirectParams($entity, 'list');
                else
                    return $this->mcm->getDefaultEntityRedirectParams($entity, 'edit', $item->getId());
            }

        }

        return array_merge($this->mcm->getDefaultEntityParams($entity), array(
            'item' => $item,
            'form' => $form->createView()
        ));
    }

    public function mcmRemove()
    {
        $entity = $this->mcm->getCurrentEntity();
        if (!$entity) return false;

        $list = $this->mcm->getItems($entity);

        $item = $this->mcm->getCurrentItem();
        if (!$item) {
            if ( $this->request->isXmlHttpRequest() && $this->request->getMethod() === 'POST') {
                $this->mcm->removeItemsFromRequestIds($entity, $list);
                return array('success' => true);
            }
            return false;
        }

        if (!in_array($item, $list)) {
            return false;
        }

        $form = $this->mcm->getRemoveForm($entity, $item);

        $form->handleRequest($this->request);

        $params = $this->mcm->getDefaultEntityParams($entity);

        if ($form->isSubmitted() && $form->isValid()) {

            $this->mcm->removeItems($entity, array($item));

            if ($this->request->isXmlHttpRequest())
                return array('success' => true);
            else
                return $this->mcm->getDefaultEntityRedirectParams($entity);

        }

        $params['item'] = $item;
        $params['form'] = $form->createView();

        return $params;
    }

    public function mcmUploader()
    {
        $entity = $this->mcm->getCurrentEntity();
        if (!$entity) return false;

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

        if (method_exists($item, 'setLocale')) $item->setLocale($this->request->getLocale());

        $item->setFile($file);

        $this->om->persist($item);

        $this->om->flush();

        return array('success' => true);
    }

/*
    ---> Generic Relations Actions
*/

    public function mcmRelations()
    {
        $relAction = $this->request->get('relAction');

        if ($relAction === 'list' || $relAction === 'show') {
            return $this->mcmRelationsList();

        } else if ($relAction === 'add' || $relAction === 'set') {
            return $this->mcmRelationsAdd();

        } else if ($relAction === 'bridge') {
            return $this->mcmBridgeAdd();

        } else if ($relAction === 'uploader') {
            if ($this->request->get('bridge')) {
                return $this->mcmBridgeUploader();
            }
            return $this->mcmRelationsUploader();

        } else if ($relAction === 'remove') {
            return $this->mcmRelationsRemove();

        } else if ($relAction === 'reorder') {
            return $this->mcmRelationsReorder();
        }

        return false;
    }

    public function mcmRelationsList()
    {
        $entity = $this->mcm->getCurrentEntity();
        if (!$entity) return false;

        $item = $this->mcm->getCurrentItem();
        if (!$item) return false;

        $relation = $this->mcm->getCurrentRelation();
        if (!$relation) return false;

        $list = $this->mcm->getRelationItems($relation, $item);

        $pager = $this->mcm->getPager($list);

        if (!$pager) {
            return false;
        }

        $params = $this->mcm->getDefaultRelationParams($entity, $relation, $item);

        $params['pager'] = $pager;

        return $params;
    }

    public function mcmRelationsAdd()
    {
        $entity = $this->mcm->getCurrentEntity();
        if (!$entity) return false;

        $item = $this->mcm->getCurrentItem();
        if (!$item) return false;

        $relation = $this->mcm->getCurrentRelation();
        if (!$relation) return false;

        $list = $this->mcm->getItemsForRelation($entity, $relation, $item);

        $params = $this->mcm->getDefaultRelationParams($entity, $relation, $item);

        if ( $this->request->getMethod() === 'POST') {

            $this->mcm->addRelationItemsFromRequestIds($entity, $relation, $item, $list);

            if ($this->request->isXmlHttpRequest())
                return array('success' => true);
            else
                return $params = $this->mcm->getDefaultRelationRedirectParams($entity, $relation);

        }

        $pager = $this->mcm->getPager($list);

        if (!$pager) {
            return false;
        }

        $params['pager'] = $pager;

        return $params;
    }

    public function mcmBridgeAdd()
    {
        $entity = $this->mcm->getCurrentEntity();
        if (!$entity) return false;

        $item = $this->mcm->getCurrentItem();
        if (!$item) return false;

        $relation = $this->mcm->getCurrentRelation();
        if (!$relation) return false;

        $bridge = $this->mcm->getCurrentBridge();
        if (!$bridge) return false;

        $list = $this->mcm->getItemsForRelation($entity, $relation, $item, $bridge);

        $params = $this->mcm->getDefaultBridgeParams($entity, $relation, $bridge, $item);

        if ($this->request->getMethod() === 'POST') {

            $this->mcm->addRelationBridgedItemsFromRequestIds($entity, $relation, $bridge, $item, $list);

            return array('success' => true);

        }

        $pager = $this->mcm->getPager($list);

        if (!$pager) {
            return false;
        }

        $params['pager'] = $pager;

        return $params;
    }

    public function mcmRelationsUploader()
    {
        $entity = $this->mcm->getCurrentEntity();
        if (!$entity) return false;

        $item = $this->mcm->getCurrentItem();
        if (!$item) return false;

        $relation = $this->mcm->getCurrentRelation();
        if (!$relation) return false;

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
        $this->mcm->addRelationItems($entity, $relation, $item, array($new));
        $new->setFile($file);
        $this->om->persist($new);
        $this->om->flush();

        return array('success' => true);
    }

    public function mcmBridgeUploader()
    {
        $entity = $this->mcm->getCurrentEntity();
        if (!$entity) return false;

        $item = $this->mcm->getCurrentItem();
        if (!$item) return false;

        $relation = $this->mcm->getCurrentRelation();
        if (!$relation) return false;

        $bridge = $this->mcm->getCurrentBridge();
        if (!$bridge) return false;

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

    public function mcmRelationsRemove()
    {
        $entity = $this->mcm->getCurrentEntity();
        if (!$entity) return false;

        $item = $this->mcm->getCurrentItem();
        if (!$item) return false;

        $relation = $this->mcm->getCurrentRelation();
        if (!$relation) return false;

        $list = $this->mcm->getRelationItems($relation, $item);

        $rid = $this->request->get('rid', false);
        $ids_list = $this->mcm->getListIds($list);

        $relItem = false;
        if (-1 < $index = array_search($rid, $ids_list)) {
            $relItem = $list[$index];
        }

        if (!$relItem) {
            if ($this->request->getMethod() === 'POST' && $this->request->get('ids', false)) {
                if ($this->request->get('rm', '') === 'item') {
                    $this->mcm->removeItemsFromRequestIds($relation, $list);
                } else {
                    $this->mcm->removeRelationItemsFromRequestIds($entity, $relation, $item, $list);
                }
                if ($this->request->isXmlHttpRequest())
                    return array('success' => true);
                else
                    return $this->mcm->getDefaultRelationRedirectParams($entity, $relation);
            }
            return false;
        }

        $form = $this->mcm->getRelationRemoveForm($entity, $relation, $relItem, array(
            'rid' => $relItem->getId(),
            'rm' => $this->request->get('rm', 'association')
        ));

        $form->handleRequest($this->request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ($this->request->get('rm', '') === 'item') {
                $this->mcm->removeItems($relation, array($relItem));
            } else {
                $this->mcm->removeRelationItems($entity, $relation, $item, array($relItem));
            }

            if ($this->request->isXmlHttpRequest())
                return array('success' => true);
            else
                return $this->mcm->getDefaultRelationRedirectParams($entity, $relation);

        }

        $params = $this->mcm->getDefaultRelationParams($entity, $relation, $item);
        $params['item'] = $relItem;
        $params['form'] = $form->createView();
        $params['rid'] = $relItem->getId();
        $params['rm'] = $this->request->get('rm', 'association');

        return $params;
    }

    public function mcmRelationsReorder()
    {
        if (!$this->request->isXmlHttpRequest() || $this->request->getMethod() !== 'POST') {
            return false;
        }

        $ids = $this->request->get('ids', array());

        if (count($ids)<2) {
            return array('success' => false, 'error' => 'Reorder: No ids.');
        }

        $entity = $this->mcm->getCurrentEntity();
        if (!$entity) return false;

        $item = $this->mcm->getCurrentItem();
        if (!$item) return false;

        $relation = $this->mcm->getCurrentRelation();
        if (!$relation) return false;

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
