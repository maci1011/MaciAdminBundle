<?php

namespace Maci\AdminBundle\Controller;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;

class DefaultController extends AbstractController
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
		$item = $this->mcm->getCurrentItem();
		if (!$item && count($this->request->get('ids')) === 0) return $this->mcmList(true);
		return $this->mcmRemove(true);
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

	public function uploaderAction()
	{
		return $this->mcmUploader();
	}

	public function reorderAction()
	{
		return $this->mcmReorder();
	}

	public function relationsAction()
	{
		return $this->mcmRelations();
	}

	/*
		---> Generic Actions
	*/

	public function mcmDashboard()
	{
		return $this->mcm->getDefaultParams();
	}

	public function mcmList($trash = false)
	{
		$entity = $this->mcm->getCurrentEntity();
		if (!$entity) return false;

		$action = $this->mcm->getCurrentAction();
		if ($this->mcm->setSessionFromRequest($entity, $action))
			return $this->mcm->getSessionActionResponse();

		$list = $this->mcm->getList($entity, ['action' => $action, 'trash' => $trash]);
		$pager = $this->mcm->getPager($entity, $action, $list);

		$params = $this->mcm->getDefaultEntityParams($entity);
		$params['pager'] = $pager;

		return $params;
	}

	public function mcmShow()
	{
		$entity = $this->mcm->getCurrentEntity();
		if (!$entity) return false;

		$item = $this->mcm->getCurrentItem();
		if (!$item) return false;

		if ($this->request->isXmlHttpRequest()) return [
			'item' => $this->mcm->getItemData($entity, $item),
			'success' => true
		];

		return array_merge($this->mcm->getDefaultEntityParams($entity), [
			'identifier' => $this->mcm->getIdentifierValue($entity, $item),
			'item' => $item
		]);
	}

	public function mcmForm()
	{
		$entity = $this->mcm->getCurrentEntity();
		if (!$entity) return false;

		$item = null;
		// $clone = $this->request->get('clone', false);

		if ($this->request->get('id', 0))
		{
			$item = $this->mcm->getCurrentItem();
			if (!$item) return false;
			// if ($clone) {
			//     $item = $this->cloneItem($entity, $item);
			// } else {
			//     $item = $item;
			// }
		}
		else $item = $this->mcm->getNewItem($entity);

		$form = $this->mcm->getForm($entity, $item);
		$form->handleRequest($this->request);

		if ($form->isSubmitted() && $form->isValid())
		{
			$isNew = (bool)($this->mcm->getIdentifierValue($entity, $item) === null);
			if ($isNew) $this->om->persist($item);

			// if ($clone) {
			//     $this->cloneItemChildren($entity, $item, $item);
			// }

			$this->om->flush();

			if ($isNew) $this->session->getFlashBag()->add('success', 'Item Added.');
			else $this->session->getFlashBag()->add('success', 'Item Edited.');

			if ($this->request->isXmlHttpRequest()) return ['success' => true];
			if ($form->has('save_and_add') && $form->get('save_and_add')->isClicked())
				return $this->mcm->getDefaultEntityRedirectParams($entity, 'new');
			if ($form->has('save_and_list') && $form->get('save_and_list')->isClicked())
				return $this->mcm->getDefaultEntityRedirectParams($entity, 'list');
			return $this->mcm->getDefaultEntityRedirectParams($entity, 'edit', $this->mcm->getIdentifierValue($entity, $item));
		}

		return array_merge($this->mcm->getDefaultEntityParams($entity), [
			'identifier' => $this->mcm->getIdentifierValue($entity, $item),
			'item' => $item,
			'form' => $form->createView()
		]);
	}

	public function mcmRemove($trash = false)
	{
		$entity = $this->mcm->getCurrentEntity();
		if (!$entity) return false;

		$list = $this->mcm->getList($entity, ['trash' => null]);
		$item = $this->mcm->getCurrentItem();

		if ($this->request->isXmlHttpRequest() && $this->request->getMethod() === 'POST')
		{
			if ($item)
			{
				if ($trash) $this->mcm->trashItems($entity, [$item]);
				else $this->mcm->removeItems($entity, [$item]);
			}
			else
			{
				if ($trash) $this->mcm->trashItemsFromRequestIds($entity, $list);
				else $this->mcm->removeItemsFromRequestIds($entity, $list);
			}
			return ['success' => true];
		}

		if (!$item || !in_array($item, $list)) return false;

		$form = $this->mcm->getRemoveForm($entity, $item, $trash);
		$form->handleRequest($this->request);
		$params = $this->mcm->getDefaultEntityParams($entity);

		if ($form->isSubmitted() && $form->isValid())
		{
			if ($trash) $this->mcm->trashItems($entity, [$item]);
			else $this->mcm->removeItems($entity, [$item]);
			return $this->mcm->getDefaultEntityRedirectParams($entity);
		}

		$params['identifier'] = $this->mcm->getIdentifierValue($entity, $item);
		$params['item'] = $item;
		$params['form'] = $form->createView();
		$params['template'] = 'MaciAdminBundle:Actions:remove.html.twig';
		$params['trashing'] = $trash;
		$params['trashed'] = $this->mcm->isItemTrashed($entity, $item);

		return $params;
	}

	public function mcmUploader()
	{
		$entity = $this->mcm->getCurrentEntity();
		if (!$entity) return false;

		$params = $this->mcm->getDefaultEntityParams($entity);

		if (!$this->request->isXmlHttpRequest() || $this->request->getMethod() !== 'POST')
			return $params;

		if (!count($this->request->files))
			return array_merge($params, ['success' => false, 'error' => 'No file(s).']);

		$name = $this->request->files->keys()[0];
		$file = $this->request->files->get($name);

		if(!$file->isValid())
			return array_merge($params, ['success' => false, 'error' => 'Upload failed.']);

		// if ($id) {
		//     $item = $this->mcm->getRepository($entity)->findOneById($id);
		//     if (!$item) {
		//         return array_merge($params, ['success' => false, 'error' => 'Item not found.']);
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

	public function mcmReorder()
	{
		if (!$this->request->isXmlHttpRequest() || $this->request->getMethod() !== 'POST')
			return false;

		$ids = $this->request->get('ids', array());

		if (count($ids)<2)
			return array('success' => false, 'error' => 'Reorder: No ids.');

		$entity = $this->mcm->getCurrentEntity();
		if (!$entity) return false;

		$item = $this->mcm->getCurrentItem();
		if (!$item) return false;

		if (!$this->mcm->isSortable($entity))
			return array('success' => false, 'error' => 'Reorder: Entity [' . $entity['label'] . '] is not Sortable.');

		$id_method = $this->mcm->getGetterMethod($this->mcm->getNewItem($entity), $this->mcm->getIdentifier($entity));
		if (!$id_method)
			return array('success' => false, 'error' => 'Reorder: Identifier Getter Method not found.');

		$sort_method = $this->mcm->getSetterMethod($this->mcm->getNewItem($entity), $this->mcm->getConfigKey($entity, 'sort_field'));
		if (!$sort_method)
			return array('success' => false, 'error' => 'Reorder: Sort Setter Method not found.');

		$list = $this->mcm->getList($entity);

		foreach ($list as $el)
		{
			$id = call_user_func_array(array($el, $id_method), array());
			if (in_array($id, $ids)) {
				call_user_func_array(array($el, $sort_method), array(array_search($id, $ids)));
			}
		}

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

		} else if ($relAction === 'new') {
			return $this->mcmRelationsNew();

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

		$relAction = $this->mcm->getCurrentRelationAction();
		if ($this->mcm->setSessionFromRequest($relation, $relAction))
			return $this->mcm->getCurrentRedirectParams();

		$list = $this->mcm->getRelationItems($entity, $relation, $item, ['action' => $relAction]);
		$params = $this->mcm->getDefaultRelationParams($entity, $relation, $item);

		if ($params['association_type'] == 2)
		{
			$params['form_search'] = false;
			$params['form_filters'] = false;
			$params['list'] = $list;
			return $params;
		}

		$pager = $this->mcm->getPager($relation, $relAction, $list, [
			'form_action' => $this->mcm->getRelationUrl($entity, $relation, $relAction)
		]);
		$params['pager'] = $pager;

		return $params;
	}

	public function mcmRelationsNew()
	{
		$entity = $this->mcm->getCurrentEntity();
		if (!$entity) return false;

		$item = $this->mcm->getCurrentItem();
		if (!$item) return false;

		$relation = $this->mcm->getCurrentRelation();
		if (!$relation) return false;

		$rel = null;
		if ($this->request->get('relId', 0))
		{
			$rel = $this->mcm->getCurrentRelatedItem();
			if (!$rel) return false;
		}
		else $rel = $this->mcm->getNewItem($relation);

		$form = $this->mcm->getForm($relation, $rel);
		$form->handleRequest($this->request);

		if ($form->isSubmitted() && $form->isValid())
		{
			$isNew = ($this->mcm->getIdentifierValue($relation, $rel) === null);
			if ($isNew) $this->om->persist($rel);

			$this->mcm->addRelationItems($entity, $relation, $item, [$rel]);
			$this->om->flush();

			if ($isNew)
				$this->session->getFlashBag()->add('success', 'Item Added.');
			else
				$this->session->getFlashBag()->add('success', 'Item Edited.');

			if ($this->request->isXmlHttpRequest())
				return ['success' => true];
			if ($form->has('save_and_add') && $form->get('save_and_add')->isClicked())
				return $this->mcm->getDefaultRelationRedirectParams($entity, $relation, 'new');
			return $this->mcm->getDefaultRelationRedirectParams($entity, $relation, 'list');
		}

		return array_merge($this->mcm->getDefaultRelationParams($entity, $relation, $item), [
			'identifier' => $this->mcm->getIdentifierValue($relation, $rel),
			'item' => $rel,
			'form' => $form->createView()
		]);

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

		$relAction = $this->mcm->getCurrentRelationAction();
		if ($this->mcm->setSessionFromRequest($relation, $relAction))
			return $this->mcm->getCurrentRedirectParams();

		$list = $this->mcm->getListForRelation($entity, $relation, $item, false, ['action' => $relAction]);

		if ($this->request->getMethod() === 'POST')
		{
			$this->mcm->addRelationItemsFromRequestIds($entity, $relation, $item, $list);
			if ($this->request->isXmlHttpRequest()) return ['success' => true];
			return $this->mcm->getDefaultRelationRedirectParams($entity, $relation, $relAction);
		}

		$pager = $this->mcm->getPager($relation, $relAction, $list, [
			'form_action' => $this->mcm->getRelationUrl($entity, $relation, $relAction)
		]);

		$params = $this->mcm->getDefaultRelationParams($entity, $relation, $item);
		$params['pager'] = $pager;
		// $params['form_filters'] = $this->mcm->generateFiltersForm($relation, $relAction)->createView();
		// $params['has_filters'] = $this->mcm->hasFilters($relation, $relAction);
		// $params['filters_list'] = $this->mcm->getGeneratedFilters($relation, $relAction);
		// $params['form_search'] = true;
		// $params['search_query'] = $this->mcm->getStoredSearchQuery($relation, $relAction);

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

		if ($this->mcm->setSessionFromRequest($bridge, $bridge['association']))
			return $this->mcm->getCurrentRedirectParams();

		$list = $this->mcm->getListForRelation($entity, $relation, $item, $bridge, ['action' => $bridge['association']]);

		if ($this->request->getMethod() === 'POST')
		{
			$this->mcm->addRelationBridgedItemsFromRequestIds($entity, $relation, $bridge, $item, $list);
			if ($this->request->isXmlHttpRequest()) return ['success' => true];
			return $this->mcm->getDefaultBridgeRedirectParams($entity, $relation, $bridge, $this->mcm->getCurrentRelationAction());
		}

		$pager = $this->mcm->getPager($bridge, $bridge['association'], $list, [
			'form_action' => $this->mcm->getBridgeUrl($entity, $relation, $bridge, $bridge['association'])
		]);

		$params = $this->mcm->getDefaultBridgeParams($entity, $relation, $bridge, $item);
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

		if (!$this->request->isXmlHttpRequest() || $this->request->getMethod() !== 'POST')
			return $params;

		if (!count($this->request->files))
			return array_merge($params,array('success' => false, 'error' => 'No file(s).'));

		$name = $this->request->files->keys()[0];
		$file = $this->request->files->get($name);

		if(!$file->isValid())
			return array_merge($params,array('success' => false, 'error' => 'Upload failed.'));

		$new = $this->mcm->getNewItem($relation);
		$new->setFile($file);
		$this->om->persist($new);
		$this->mcm->addRelationItems($entity, $relation, $item, array($new));

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

		if (!$this->request->isXmlHttpRequest() || $this->request->getMethod() !== 'POST')
			return $params;

		if (!count($this->request->files))
			return array_merge($params,array('success' => false, 'error' => 'No file(s).'));

		$name = $this->request->files->keys()[0];
		$file = $this->request->files->get($name);

		if(!$file->isValid())
			return array_merge($params,array('success' => false, 'error' => 'Upload failed.'));

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

		$list = $this->mcm->getRelationItems($entity, $relation, $item);

		$rid = $this->request->get('rid', false);
		$ids_list = $this->mcm->getListIdentifiers($relation, $list);

		$relItem = false;
		if (-1 < $index = array_search($rid, $ids_list)) {
			$relItem = $list[$index];
		}

		if (!$relItem)
		{
			if ($this->request->getMethod() === 'POST' && $this->request->get('ids', false))
			{
				if ($this->request->get('rm', '') === 'item')
					$this->mcm->removeItemsFromRequestIds($relation, $list);
				else
					$this->mcm->removeRelationItemsFromRequestIds($entity, $relation, $item, $list);
				if ($this->request->isXmlHttpRequest()) return ['success' => true];
				return $this->mcm->getDefaultRelationRedirectParams($entity, $relation);
			}
			return false;
		}

		$form = $this->mcm->getRelationRemoveForm($entity, $relation, $relItem, array(
			'rid' => $this->mcm->getIdentifierValue($relation, $relItem),
			'rm' => $this->request->get('rm', 'association')
		));

		$form->handleRequest($this->request);

		if ($form->isSubmitted() && $form->isValid())
		{
			if ($this->request->get('rm', '') === 'item') $this->mcm->removeItems($relation, array($relItem));
			else $this->mcm->removeRelationItems($entity, $relation, $item, array($relItem));
			if ($this->request->isXmlHttpRequest()) return array('success' => true);
			return $this->mcm->getDefaultRelationRedirectParams($entity, $relation);
		}

		$params = $this->mcm->getDefaultRelationParams($entity, $relation, $item);
		$params['identifier'] = $this->mcm->getIdentifierValue($relation, $item);
		$params['item'] = $relItem;
		$params['form'] = $form->createView();
		$params['rid'] = $this->mcm->getIdentifierValue($relation, $relItem);
		$params['rm'] = $this->request->get('rm', 'association');
		$params['trashing'] = false;
		$params['trashed'] = $this->mcm->isItemTrashed($entity, $item);

		return $params;
	}

	public function mcmRelationsReorder()
	{
		if (!$this->request->isXmlHttpRequest() || $this->request->getMethod() !== 'POST')
			return false;

		$ids = $this->request->get('ids', array());

		if (count($ids)<2)
			return array('success' => false, 'error' => 'Reorder: No ids.');

		$entity = $this->mcm->getCurrentEntity();
		if (!$entity) return false;

		$item = $this->mcm->getCurrentItem();
		if (!$item) return false;

		$relation = $this->mcm->getCurrentRelation();
		if (!$relation) return false;

		if (!$this->mcm->isSortable($relation))
			return array('success' => false, 'error' => 'Reorder: Relation [' . $relation['association'] . '] for entity [' . $entity['label'] . '] is not Sortable.');

		$id_method = $this->mcm->getGetterMethod($this->mcm->getNewItem($relation), $this->mcm->getIdentifier($relation));
		if (!$id_method)
			return array('success' => false, 'error' => 'Reorder: Identifier Getter Method not found.');

		$sort_method = $this->mcm->getSetterMethod($this->mcm->getNewItem($relation), $this->mcm->getConfigKey($relation, 'sort_field'));
		if (!$sort_method)
			return array('success' => false, 'error' => 'Reorder: Sort Setter Method not found.');

		$list = $this->mcm->getRelationItems($entity, $relation, $item);

		if (!$list)
			return ['success' => false];

		foreach ($list as $el)
		{
			$id = call_user_func_array(array($el, $id_method), array());
			if (in_array($id, $ids)) {
				call_user_func_array(array($el, $sort_method), array(array_search($id, $ids)));
			}
		}

		$this->om->flush();

		return ['success' => true];
	}

}
