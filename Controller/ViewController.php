<?php

namespace Maci\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

use MAdminController;

class ViewController extends AbstractController
{
	public function indexAction()
	{
		return $this->redirect($this->generateUrl('maci_admin_view'));
	}

	public function notFoundAction()
	{
		return $this->render('MaciAdminBundle:Default:not_found.html.twig');
	}

	public function accessDeniedAction()
	{
		return $this->render('MaciAdminBundle:Default:access_denied.html.twig');
	}

	public function viewAction(Request $request)
	{
		$admin = $this->container->get(AdminController::class);

		// --- Check Auth

		$auth = $admin->checkRoute();
		if ($auth !== true) {
			// Redirect
			return $this->redirect($auth);
		}

		// --- The Controller with the Actions is getted here

		$map = $admin->getControllerMap();
		if ($map == null) {
			return $this->redirect($this->generateUrl('maci_admin_access_denied'));
		}
		$controller = $this->container->get($admin->getController($map, $admin->getControllerAction()));

		// --- Check if the Action Exists

		$callAction = ($admin->getCurrentAction() . 'Action');
		if (!method_exists($controller, $callAction)) {
			$request->getSession()->getFlashBag()->add('error', 'View [' . $callAction . '] not found in [' . get_class($controller) . '].');
			return $this->redirect($this->generateUrl('maci_admin_not_found'));
		}

		// --- Call the Action that must return a $params array for the response

		$params = call_user_func_array(array($controller, $callAction), array());

		if ($params === false) {
			if ($request->isXmlHttpRequest()) {
				return ['success' => false, 'error' => 'Bad Request.'];
			}
			$request->getSession()->getFlashBag()->add('error', 'Something wrong. :(');
			return $this->redirect($this->generateUrl('maci_admin_not_found'));
		}

		// --- Return the Response for each case

		if ($request->isXmlHttpRequest()) {
			if (array_key_exists('template',$params)) {
				$params['template'] = $this->renderView($params['template'], array(
					'params' => $params
				));
			}
			return new JsonResponse($params, 200);
		}

		if (array_key_exists('redirect', $params)) {
			if (array_key_exists('redirect_params', $params)) {
				return $this->redirect($this->generateUrl($params['redirect'],$params['redirect_params']));
			}
			return $this->redirect($this->generateUrl($params['redirect']));
		}

		if (array_key_exists('redirect_url', $params)) {
			return $this->redirect($params['redirect_url']);
		}

		// --- Return the View Template that include the action template

		return $this->render('MaciAdminBundle:Default:view.html.twig', array(
			'template' => $params['template'],
			'params' => $params
		));
	}

	public function ajaxAction(Request $request)
	{
		// --- Check Request

		if (!$request->isXmlHttpRequest()) {
			return $this->redirect($this->generateUrl('homepage'));
		}

		if ($request->getMethod() !== 'POST') {
			return new JsonResponse(['success' => false, 'error' => 'Bad Request.'], 200);
		}

		// --- Check Auth

		$admin = $this->container->get(AdminController::class);
		if (!$admin->checkAuth()) {
			return new JsonResponse(['success' => false, 'error' => 'Not Authorized.'], 200);
		}

		// --- Check Data

		$data = $request->get('data');
		if (!is_array($data)) {
			return new JsonResponse(['success' => false, 'error' => 'No Data.'], 200);
		}

		// $om = $this->getDoctrine()->getManager();

		// --- List

		if (array_key_exists('list', $data)) {
			$data['list'] = $admin->getListDataByParams($data['list']);
		}

		// --- Item

		if (array_key_exists('item', $data)) {
			$data['item'] = $admin->getItemDataByParams($data['item']);
		}

		// --- New

		if (array_key_exists('new', $data)) {
			$data['new'] = $admin->newItemsByParams($data['new']);
		}

		// --- Edit

		if (array_key_exists('edit', $data)) {
			$data['edit'] = $admin->editItemByParams($data['edit']);
		}

		// --- Remove

		if (array_key_exists('remove', $data)) {
			$data['remove'] = $admin->removeItemByParams($data['remove']);
		}

		// --- Add Relation

		if (array_key_exists('add', $data)) {
			$data['add'] = $admin->addRelationItemsByParams($data['add']);
		}

		// --- Set Filters

		if (array_key_exists('set_filters', $data)) {
			$data['set_filters'] = $admin->addFiltersByParams($data['set_filters']);
		}

		return new JsonResponse($data, 200);
	}

	public function adminBarAction($entity, $item = false)
	{
		$admin = $this->container->get(AdminController::class);
		$sections = $admin->getAuthSections();

		$id = false;
		$section = false;
		$actions = false;

		foreach ($sections as $secname) {
			if ($admin->hasEntity($secname, $entity)) {
				$section = $secname;
				$_entity = $admin->getEntity($section, $entity);
				if ($item) {
					$actions = $admin->getArrayWithLabels($admin->getSingleActions($_entity));
					$id = $item->getId();
				} else {
					$actions = $admin->getArrayWithLabels($admin->getMainActions($_entity));
				}
				break;
			}
		}

		return $this->render('MaciAdminBundle:Default:admin_bar.html.twig', array(
			'id' => $id,
			'section' => $section,
			'entity' => $entity,
			'entity_label' => $admin->generateLabel($entity),
			'item' => $item,
			'actions' => $actions
		));
	}

}
