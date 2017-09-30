<?php

namespace Maci\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class ViewController extends Controller
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
        $admin = $this->container->get('maci.admin');
        $sections = $admin->getAuthSections();
        if (!count($sections)) {
            return $this->redirect($this->generateUrl('homepage'));
        }
        $section = $admin->getCurrentSection();
        if (!$section) {
            return $this->redirect($this->generateUrl('maci_admin_view', array('section'=>$sections[0])));
        }
        if (!in_array($section, $sections)) {
            $request->getSession()->getFlashBag()->add('error', 'Section [' . $section . '] not Found.');
            return $this->redirect($this->generateUrl('maci_admin_view', array('section'=>$sections[0])));
        }
        $entity = $request->get('entity');
        if (!$entity || !$admin->hasEntity($section, $entity)) {
            if ($admin->hasSectionDashboard($section)) {
                return $this->render($admin->getSectionDashboard($section));
            }
            $entities = array_keys($admin->getEntities($section));
            return $this->redirect($this->generateUrl('maci_admin_view', array('section'=>$section,'entity'=>$entities[0],'action'=>'list')));
        }
        $_entity = $admin->getCurrentEntity();
        $entity = $_entity['name'];
        $action = $admin->getCurrentAction();
        if (!$action) {
            return $this->redirect($this->generateUrl('maci_admin_view', array('section'=>$section,'entity'=>$entity,'action'=>'list')));
        }
        if ($action === 'relations') {
            if (!$admin->getCurrentItem()) {
                return $this->redirect($this->generateUrl('maci_admin_view', array('section'=>$section,'entity'=>$entity,'action'=>'list')));
            }
            $relation = $request->get('relation');
            if (!$relation) {
                $relations = $admin->getAssociations($_entity);
                $relAction = $admin->getRelationDefaultAction($_entity, $relation[0]);
                return $this->redirect($this->generateUrl('maci_admin_view', array('section'=>$section,'entity'=>$entity,'action'=>$action,'id'=>$id,'relation'=>$relations[0],'relAction'=>$relAction)));
            }
            $relAction = $request->get('relAction');
            if (!$relAction || !in_array($relAction, $admin->getRelationActions($section,$entity,$relation))) {
                $_entity = $admin->getEntity($section, $entity);
                $relAction = $admin->getRelationDefaultAction($_entity, $relation);
                return $this->redirect($this->generateUrl('maci_admin_view', array('section'=>$section,'entity'=>$entity,'action'=>$action,'id'=>$id,'relation'=>$relation,'relAction'=>$relAction)));
            }
        }

        $controller = $this->container->get('maci.admin.default');

        $callAction = ( $action . 'Action' );
        if (!method_exists($controller, $callAction)) {
            $request->getSession()->getFlashBag()->add('error', 'View [' . $callAction . '] not found in [' . get_class($controller) . '].');
            return $this->redirect($this->generateUrl('maci_admin_not_found'));
        }

        $params = call_user_func_array(array($controller, $callAction), array());

        if ($params===false) {
            $request->getSession()->getFlashBag()->add('error', 'Something wrong. :(');
            return $this->redirect($this->generateUrl('maci_admin_not_found'));
        }

        if (array_key_exists('redirect', $params)) {
            return $this->redirect($this->generateUrl($params['redirect'],$params['redirect_params']));
        }

        if ($request->isXmlHttpRequest()) {
            $params['template'] = (array_key_exists('template',$params) ? $this->renderView($params['template'], array(
                'params' => $params
            )) : null);
            return new JsonResponse($params, 200);
        }

        return $this->render('MaciAdminBundle:Default:view.html.twig', array(
            'template' => $params['template'],
            'params' => $params
        ));
    }
}
