<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Controller;

use Zend\View\Model\ViewModel;
// Pagination
use Zend\Paginator\Paginator;
use Doctrine\ORM\Tools\Pagination\Paginator as docPaginator;
use DoctrineORMModule\Paginator\Adapter\DoctrinePaginator as Adapter;
use Zend\Http\Request;
use Zend\Mvc\Controller\ActionController;
use Zend\View\Model\JsonModel;

class IndexController extends AbstractController
{
    const NUMBER_OF_BLOG_ITEMS_PER_PAGE = 10;
    
    public function indexAction()
    {
        return new ViewModel();
    }
    
    public function blogAction()
    {
    	$searchWord = isset($_REQUEST['search']) ? $_REQUEST['search'] : null;
        $this->layout('layout/page');
        $em = $this->getEntityManager();
        
        $categories = $em->createQueryBuilder()
        ->select('bc')
        ->from('Admin\Entity\BlogCategories', 'bc')
        ->orderBy('bc.sort')
        ->getQuery()
        ->getResult(\Doctrine\ORM\Query::HYDRATE_OBJECT);
        
        if ($searchWord == null) {
        	$blog = $em
            	->getRepository("Admin\Entity\Blog")
            	->createQueryBuilder('c')
            	->select('c')
            	->where("c.active = 1")
            	->orderBy('c.createdAt','DESC');
        } else {
        	$blog = $em
        	->getRepository("Admin\Entity\Blog")
        	->createQueryBuilder('c')
        	->select('c')
        	->where("c.active = 1")
        	->where("c.title like :searchWord")
        	->orWhere("c.shortDescription like :searchWord ")
        	->orWhere("c.body like :searchWord")
        	->setParameter('searchWord', '%'.$searchWord.'%')
        	->orderBy('c.createdAt','DESC');
        }
        $docPaginator = new docPaginator($blog);
        $docAdapter = new Adapter($docPaginator);

        $count = $docAdapter->count();
        
        $paginator = new Paginator($docAdapter);
        $page = 1;
        if ($this->params()->fromRoute('page')) {
            $page = $this->params()->fromRoute('page');
        }
        $paginator->setCurrentPageNumber((int) $page)
            ->setItemCountPerPage(self::NUMBER_OF_BLOG_ITEMS_PER_PAGE);

        $tags = array();
        foreach ($paginator as $value) 
        	$tags[$value->getId()] = $em->createQueryBuilder()
    			->select('bc.title', 'bc.url')
    			->from('Admin\Entity\BlogsCategories', 'bsc')
    			->leftJoin('Admin\Entity\BlogCategories', 'bc', \Doctrine\ORM\Query\Expr\Join::WITH, 'bsc.categoryId = bc.id')
    			->where('bsc.blogId = :blogId AND bsc.sort > 0')
    			->setParameter('blogId', $page->getId())
    			->getQuery()
    			->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);
        
        $viewModel = new ViewModel(array(
            'paginator' => $paginator,
        	'tags' => $tags,
            'count' => $count,
        	'categories' => $categories,
        	'page' => $page
        ));

        return $viewModel;
    }
    
     
    public function blogViewAction()
    {
        $name = $this->params('name');
        $param = isset($_REQUEST['down'])? 'down' : '';
        $em = $this->getEntityManager();
        
        $categories = $em->createQueryBuilder()
        ->select('bc')
        ->from('Admin\Entity\BlogCategories', 'bc')
        ->orderBy('bc.sort')
        ->getQuery()
        ->getResult(\Doctrine\ORM\Query::HYDRATE_OBJECT);
        
        $form = new \Application\Form\BlogComments();
        $page = $em->getRepository('Admin\Entity\Blog')->findOneByUrl($name);
        
        $page->setShortDescription($this->_getImage($page->getShortDescription()));
        
        if (is_null($page)) {
            $this->getResponse()->setStatusCode(404);
            return;
        }

        $tags = $em->createQueryBuilder()
        	->select('bc.id', 'bc.title', 'bc.url')
        	->from('Admin\Entity\BlogsCategories', 'bsc')
        	->leftJoin('Admin\Entity\BlogCategories', 'bc', \Doctrine\ORM\Query\Expr\Join::WITH, 'bsc.categoryId = bc.id')
        	->where('bsc.blogId = :blogId')
        	->setParameter('blogId', $page->getId())
        	->getQuery()
        	->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);
        
        $tagStr = '0';
        foreach ($tags as $tag) $tagStr.=','.$tag['id'];
        
        $relatedPosts = $em->createQueryBuilder()
        	->select('DISTINCT b.title','b.url','b.shortDescription','b.createdAt')
	   		->from('Admin\Entity\Blog', 'b')
   	 		->leftJoin('Admin\Entity\BlogsCategories', 'bsc', \Doctrine\ORM\Query\Expr\Join::WITH, 'bsc.blogId = b.id')
    		->where('bsc.categoryId IN ('.$tagStr.') AND b.id != :id')
    		->orderBy('b.createdAt','DESC')
    		->setParameter('id', $page->getId())
    		->setMaxResults(4)
        	->getQuery()
        	->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

        $tags = $em->createQueryBuilder()
        ->select('bc.id', 'bc.title', 'bc.url')
        ->from('Admin\Entity\BlogsCategories', 'bsc')
        ->leftJoin('Admin\Entity\BlogCategories', 'bc', \Doctrine\ORM\Query\Expr\Join::WITH, 'bsc.categoryId = bc.id')
        ->where('bsc.blogId = :blogId AND bsc.sort > 0')
        ->setParameter('blogId', $page->getId())
        ->getQuery()
        ->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);
                
        $comments = $em->getRepository('Admin\Entity\BlogComments')->findBy(array('blogId' => $page->getId()));
        $this->layout('layout/page');
        $viewModel = new ViewModel(
        		array(	'page' => $page, 
        				'param' => $param, 
        				'tags' => $tags,
        				'comments' => $comments, 
        				'form' => $form, 
        				'categories' => $categories,
        				'relatedPosts'=>$relatedPosts));
        return $viewModel;
    }
    
    public function saveBlogCommentAction()
    {
    	$form = new \Application\Form\BlogComments();
    	$url = $this->getRequest()->getPost('url');
    	 
    	$request = $this->getRequest();
    	if ($request->isPost()) {
    
    		$data = $this->getRequest()->getPost();
    		$recaptcha = $this->getRequest()->getPost('g-recaptcha-response');
    
    		$myCurl = curl_init();
    		curl_setopt_array($myCurl, array(
    				CURLOPT_URL => 'https://www.google.com/recaptcha/api/siteverify',
    				CURLOPT_RETURNTRANSFER => true,
    				CURLOPT_POST => true,
    				CURLOPT_POSTFIELDS => http_build_query(array('response' => $recaptcha, 'secret' => '***'))
    		));
    		$response = json_decode(curl_exec($myCurl));
    		curl_close($myCurl);
    
    		unset($data['g-recaptcha-response']);
    		$form->setData($data);
    		session_start();
    
    		if ($form->isValid() && $response->success) {
    			 
    			$objectManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
    			 
    			$blogId = $this->getRequest()->getPost('blogId');
    			$userName = $this->getRequest()->getPost('userName');
    			if (trim($userName)=='') $userName = 'Anonymous';;
    			$userComment = strip_tags($this->getRequest()->getPost('userComment'));
    				
    			$commentMod = new \Admin\Entity\BlogComments();
    			$comment = $commentMod->setBlogId($blogId)
    			->setUserName($userName)
    			->setComment($userComment)
    			->setChecked(false)
    			->setUserIP($this->getUserIP());
    			 
    			$objectManager->persist($comment);
    			$objectManager->flush();
    			//$message = "Thank You! <br>Your comment has been queued for review by site administrator and will be published after approval!";
    		}
    	}
    	return $this->redirect()->toRoute("blogView/list", array('name'=>$url));
    }
    
    private function getUserIP()
    {
    	if (!empty($_SERVER['HTTP_CLIENT_IP']))
    	{
    		$ip = $_SERVER['HTTP_CLIENT_IP'];
    	}
    	elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
    	{
    		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    	}
    	else
    	{
    		$ip = $_SERVER['REMOTE_ADDR'];
    	}
    	return $ip;
    }
    
}
