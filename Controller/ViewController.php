<?php

namespace Maci\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

use MAdminController;

class ViewController extends AbstractController
{
	public function indexAction(Request $request)
	{
		return $this->redirect($this->generateUrl('maci_admin_view'));
	}

	public function notFoundAction(Request $request)
	{
		return $this->render('MaciAdminBundle:Default:not_found.html.twig');
	}

	public function viewAction(Request $request)
	{
		$admin = $this->container->get(AdminController::class);

		// --- Check the Auths and the Route

		$auth = $admin->checkRoute();
		if ($auth !== true) {
			// Redirect
			return $this->redirect($auth);
		}

		// --- The Controller with the Actions is getted here

		$controller = $this->container->get($admin->getController($admin->getControllerMap(), $admin->getControllerAction()));

		// --- Check if the Action Exists

		$callAction = ($admin->getCurrentAction() . 'Action');
		if (!method_exists($controller, $callAction)) {
			$request->getSession()->getFlashBag()->add('error', 'View [' . $callAction . '] not found in [' . get_class($controller) . '].');
			return $this->redirect($this->generateUrl('maci_admin_not_found'));
		}

		// --- Call the Action that must return a $params array for the response

		$params = call_user_func_array(array($controller, $callAction), array());

		if ($params===false) {
			$request->getSession()->getFlashBag()->add('error', 'Something wrong. :(');
			return $this->redirect($this->generateUrl('maci_admin_not_found'));
		}

		// --- Return the Response for each case

		if (array_key_exists('redirect', $params)) {
			if (array_key_exists('redirect_params', $params)) {
				return $this->redirect($this->generateUrl($params['redirect'],$params['redirect_params']));
			}
			return $this->redirect($this->generateUrl($params['redirect']));
		}

		if (array_key_exists('redirect_url', $params)) {
			return $this->redirect($params['redirect_url']);
		}

		if ($request->isXmlHttpRequest()) {
			$params['template'] = (array_key_exists('template',$params) ? $this->renderView($params['template'], array(
				'params' => $params
			)) : null);
			return new JsonResponse($params, 200);
		}

		// --- Return the View Template that include the action template

		return $this->render('MaciAdminBundle:Default:view.html.twig', array(
			'template' => $params['template'],
			'params' => $params
		));
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
