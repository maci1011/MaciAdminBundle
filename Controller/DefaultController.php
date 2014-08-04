<?php

namespace Maci\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DefaultController extends Controller
{
    public function indexAction()
    {
        $admin = $this->getConfig();

        return $this->render('MaciAdminBundle:Default:index.html.twig', array(
            'admin' => $admin
        ));
    }

    public function navAction()
    {
        $list = $this->getConfig();

        return $this->render('MaciAdminBundle:Default:_nav.html.twig', array(
            'list' => $list
        ));
    }

    public function entityAction($entity)
    {
    	$admin = $this->getAdmin($entity);

        return $this->redirect($this->generateUrl('maci_admin_entity_list', array('entity' => $admin['name'])));
    }

    public function createAction(Request $request, $entity)
    {
        $admin = $this->getAdmin($entity);
        if (!$admin || !$request->isXmlHttpRequest()) {
            return new JsonResponse(array('error' => 'error.noadmin'), 501);
        }

        $id = $request->get('id');
        $fields = $request->get('fields');

        $new = $admin['new'];

        $save = false;

        if (is_array($fields) && count($fields)) {
            foreach ($fields as $key => $value) {
		        if (method_exists($new, $mth = ('set' . ucfirst($key)) )) {
		        	if ($rel = $this->getAdmin($key)) {
				        $rob = $this->getDoctrine()->getManager()
				            ->getRepository($rel['repository'])->findOneById($value);
				        if ($rob) {
		            		call_user_method($mth, $new, $rob);
                            $save = true;
				        }
		        	} else {
		            	call_user_method($mth, $new, $value);
                        $save = true;
		        	}
		        }
            }
        }

        if ($save) {
            $em = $this->getDoctrine()->getEntityManager();
            $em->persist($new);
            $em->flush();
        } else {
            return new JsonResponse(array('error' => 'error.nosave'), 501);
        }

        return $this->renderTemplate($request, $admin, 'item', array(
            'admin' => $admin,
            'item' => $new
        ));
    }

    public function removeAction(Request $request, $entity, $id)
    {
        $admin = $this->getAdmin($entity);
        if (!$admin || !$request->isXmlHttpRequest()) {
            return new JsonResponse(array('error' => 'error.noadmin'), 501);
        }

        $item = $this->getDoctrine()->getManager()
            ->getRepository($admin['repository'])->findOneById($id);

        if (!$item) {
            return new JsonResponse(array('error' => 'error.not-found'), 501);
        }

        $em = $this->getDoctrine()->getEntityManager();
        $em->remove($item);
        $em->flush();

        return new JsonResponse(array('result' => true), 200);
    }

    public function formAction(Request $request, $entity, $id)
    {
        $admin = $this->getAdmin($entity);
        if (!$admin) {
            return $this->redirect($this->generateUrl('maci_admin_homepage', array('error' => 'error.notfound')));
        }

        $item = $admin['new'];
        if ($id) {
            $item = $this->getDoctrine()->getManager()
                ->getRepository($admin['repository'])->findOneById($id);

            if (!$item) {
                return $this->redirect($this->generateUrl('maci_admin_homepage', array('error' => 'error.notfound')));
            }
        }

        $form = $this->createForm($admin['form'], $item);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getEntityManager();
            $em->persist($item);
            $em->flush();
            if ($request->isXmlHttpRequest()) {
                return $this->renderTemplate($request, $admin, 'item', array(
                    'admin' => $admin,
                    'item' => $item
                ));
            } else {
                return $this->redirect($this->generateUrl('maci_admin_entity_list', array(
                    'entity' => $admin['name'],
                    'message' => 'form.add'
                )));
            }
        }

        return $this->renderTemplate($request, $admin, 'form', array(
            'admin' => $admin,
            'item' => $item,
            'form' => $form->createView()
        ));
    }

    public function itemAction(Request $request, $entity, $id)
    {
        $admin = $this->getAdmin($entity);
        if (!$admin) {
            return $this->redirect($this->generateUrl('maci_admin_homepage', array('error' => 'error.notfound')));
        }

        $item = $this->getDoctrine()->getManager()
            ->getRepository($admin['repository'])->findOneById($id);

        if (!$item) {
            return $this->redirect($this->generateUrl('maci_admin_homepage', array('error' => 'error.notfound')));
        }

        return $this->renderTemplate($request, $admin, 'item', array(
            'admin' => $admin,
            'item' => $item
        ));
    }

    public function listAction(Request $request, $entity)
    {
        $admin = $this->getAdmin($entity);
        if (!$admin) {
            return $this->redirect($this->generateUrl('maci_admin_homepage', array('error' => 'error.notfound')));
        }

        $repo = $this->getDoctrine()->getManager()->getRepository($admin['repository']);

        if ($filters = $request->get('filters')) {
            $list = $repo->findBy($filters, (
                method_exists($admin['new'], 'setPosition') ?
                array('position' => 'ASC') :
                false
            ));
        } else {
            $list = $repo->findAll();
        }

        return $this->renderTemplate($request, $admin, 'list', array(
            'admin' => $admin,
            'list' => $list
        ));
    }

    public function reorderAction(Request $request, $entity)
    {
        $admin = $this->getAdmin($entity);
        if (!$admin) {
            return $this->redirect($this->generateUrl('maci_admin_homepage', array('error' => 'error.notfound')));
        }

        $ids = $this->getRequest()->get('ids');

        $this->getDoctrine()->getRepository($admin['repository'])->reorder($ids);

        return new JsonResponse(array('result' => true), 200);
    }

    public function fileUploadAction(Request $request, $entity, $id)
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->redirect($this->generateUrl('maci_admin_homepage', array('error' => 'error.action_denied')));
        }

        $admin = $this->getAdmin($entity);
        if (!$admin) {
            return new JsonResponse(array('error' => 'error.noadmin'), 501);
        }

        $item = $admin['new'];
        if ($id) {
            $item = $this->getDoctrine()->getManager()
                ->getRepository($admin['repository'])->findOneById($id);
            if (!$item) {
                return new JsonResponse(array('error' => 'error.notfound'), 501);
            }
        }

        foreach ($_FILES as $file) {

            if($file['error'] == UPLOAD_ERR_OK and is_uploaded_file($file['tmp_name'])) {

            $item->importFile( $file );

            $em = $this->getDoctrine()->getEntityManager();
            $em->persist($item);
            $em->flush();

            }
        }

        return new JsonResponse(array('result' => true, 'id' => $item->getId()), 200);
    }

    public function pagerAction()
    {
        $adminLimitPerPage = $this->getBlogConfig('admin_limit_per_page');
        $repo = $this->getDoctrine()->getEntityManager()->getRepository('Eight\BlogBundle\Entity\Post');
        $page = $this->getRequest()->get('page', 1);

        $pager = new PostPager($repo, $adminLimitPerPage, $page, 5);

        return $this->render('EightBlogBundle:Admin:list.html.twig', array(
            'posts' => $this->container->get('eight.posts')->getPaged($adminLimitPerPage, $page),
            'pager' => $pager
        ));
    }

    public function renderTemplate(Request $request, $admin, $action, $params, $modal = true)
    {
        if ( array_key_exists('templates', $admin) && array_key_exists($action, $admin['templates'])) {
            $template = $admin['templates'][$action];
        } else {
            $template = 'MaciAdminBundle:Default:_' . $action .'.html.twig';
        }

        if ($request->isXmlHttpRequest()) {
            if ($request->get('modal')) {
                return $this->render('MaciAdminBundle:Default:async.html.twig', array(
                    'params' => $params,
                    'template' => $template
                ));
            } else {
                return $this->render($template, $params);
            }
        } else {
            return $this->render('MaciAdminBundle:Default:' . $action .'.html.twig', array(
                'admin' => $admin,
                'params' => $params,
                'template' => $template
            ));
        }
    }

    public function getAdmin($entity = false)
    {
        $config = $this->getConfig();
        if ($entity && array_key_exists($entity, $config)) {
            $admin = $config[$entity];
            $admin['name'] = $entity;
            return $admin;
        }
        return false;
    }

    public function getConfig()
    {
        return array(
            'album' => array(
                'label' => 'Album',
                'repository' => 'MaciMediaBundle:Album',
                'new' => new \Maci\MediaBundle\Entity\Album(),
                'form' => 'media_album',
                'menu' => true,
                'templates' => array(
                    'form' => 'MaciMediaBundle:Admin:album_form.html.twig'
                )
            ),
            'media_item' => array(
                'label' => 'Album Item',
                'repository' => 'MaciMediaBundle:Item',
                'new' => new \Maci\MediaBundle\Entity\Item(),
                'form' => 'media_item'
            ),
            'media' => array(
                'label' => 'Media',
                'repository' => 'MaciMediaBundle:Media',
                'menu' => true,
                'new' => new \Maci\MediaBundle\Entity\Media(),
                'form' => 'media'
            )
        );
    }
}
