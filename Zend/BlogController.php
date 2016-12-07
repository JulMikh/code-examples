<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Admin\Controller;

use DoctrineORMModule\Stdlib\Hydrator\DoctrineEntity;

use Zend\View\Model\ViewModel;

class BlogController extends AbstractController
{
    public function createAction()
    {
        $error = false;
        $bCat = array();
        $blogCatSort = array();
        $page = new \Admin\Entity\Blog;
        $em = $this->getEntityManager();
        if ($this->params('id') > 0) {
            $page = $em->getRepository('Admin\Entity\Blog')->find($this->params('id'));
       		$id = $this->params('id');
       		$blogCat = $em->createQueryBuilder()
        		->select('bc.categoryId','bc.sort')
        		->from('Admin\Entity\BlogsCategories', 'bc')
        		->where('bc.blogId = :blogId')
        		->setParameter('blogId', $this->params('id'))
        		->getQuery()
        		->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);
       			foreach ($blogCat as $value) {
       				array_push($bCat,$value['categoryId']);
       				array_push($blogCatSort,$value['sort']);
       			}
            
            if (is_null($page)) {
                $this->getResponse()->setStatusCode(404);
                return;
            }
        }
        $em = $this->getEntityManager();
        $categories = $em->getRepository('Admin\Entity\BlogCategories')->findAll();
        
        $form = new \Admin\Form\Blog();
        $form->setHydrator(new DoctrineEntity($em, 'Admin\Entity\Blog'));

        $form->bind($page);
        $request = $this->getRequest();
        
        if ($request->isPost()) {       	
            $form->setData($request->getPost());
            if ($form->isValid()) {
                $url = $this->getRequest()->getPost('url');
                if (trim($url)=='') $url = $this->_transliterate($page->getTitle());
               
                // check the url in the database
                $blog = $em->getRepository('Admin\Entity\Blog')->findOneByUrl($url);
                
                if (!is_null($blog) && $blog->getId() != $page->getId()) {
                	$this->flashMessenger()->addErrorMessage('Wrong url: '.$url);
                } else {
                 	$page->setUrl($url)
                 	->setActive('1');
               		$em->persist($page);
                	$em->flush();
                	
                	// change categories
                	$cat = $em->getRepository('Admin\Entity\BlogsCategories')->findBy(array('blogId' => $page->getId()));
                	if (count($cat))
                    foreach ($cat as $bCategory) {
   	 					$em->remove($bCategory);
        			}
                	$em->flush();
                	
                	$catVList = $this->getRequest()->getPost('vCategories');
                	$catInvList = $this->getRequest()->getPost('invCategories');
                	 
                	if (count($catVList))
                	foreach ($catVList as $bCategory) {
                		$addcategory = new \Admin\Entity\BlogsCategories();
                		$addcategory->setBlogId($page->getId())->setCategoryId($bCategory)->setSort(1);
                		$em->persist($addcategory);
                		$em->flush();
                	}
                	if (count($catInvList))
                     	foreach ($catInvList as $bCategory) {
                		$addcategory = new \Admin\Entity\BlogsCategories();
                		$addcategory->setBlogId($page->getId())->setCategoryId($bCategory)->setSort(0);
                		$em->persist($addcategory);
                		$em->flush();
                	}
                	$this->flashMessenger()->addSuccessMessage('Item Saved');
                }
                return $this->redirect()->toRoute('admin-blog/edit',array('id' => $page->getId()));
            } else {
                $error = true;
            }
        } //POST

        $viewModel = new ViewModel(array(
            'error' => $error,
            'form' => $form,
        	'blogCat' => $bCat,
        	'blogCatSort' => $blogCatSort,
        	'categories' => $categories,
        ));

        return $viewModel;
    }
    
    public function editAction()
    {
        $viewModel = $this->createAction();
        return $viewModel;
    }
    
    public function listAction()
    {
        $em = $this->getEntityManager();
        $pages = array();
        $pages = $em->getRepository('Admin\Entity\Blog')->findAll();
        
        $viewModel = new ViewModel(array(
            'pages' => $pages,
        ));
        
        return $viewModel;
    }
    
    public function deleteAction()
    {
        $em = $this->getEntityManager();
        if ($this->params('id') > 0) {
            $page = $em->getRepository('Admin\Entity\Blog')->findOneById($this->params('id'));
            $page->setActive('0');
            //$em->remove($page);
            $em->flush();
        }

        return $this->redirect()->toRoute('admin-blog/list');
    }

}
