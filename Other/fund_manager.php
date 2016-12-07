<?php

require_once('fund.php');

class fund_manager extends fund
{
	public static $error = FALSE;

	function __construct($db, $auth, $template, $message)
	{
		$this->db = $db;
		$this->auth = $auth;
		$this->template = $template;
		$this->message = $message;
		
		if (!$this->auth->getIsLoggedIn() && isset($_REQUEST['share_code'])) {
			$this->setRedirect($this->getUrl('index/index/?fguest='.$_REQUEST['q'].'&share_code='.$_REQUEST['share_code']));
			return;
		}
		
		if (!array_key_exists('q', $_REQUEST)) {
			$this->writeLog('manager', 'manager-error-not-specified');
			$this->message->setError("This fund does not exist, or you do not have the sufficient permissions to view it.", "/");
			return false;
		}
		
		$this->fund = $this->getFund('url_key', $_REQUEST['q']);
		$this->template->fund = $this->fund;
				
		if (!$this->fund) {
			$this->writeLog('manager', 'manager-error-fund-not-exists');
			$this->message->setError("This fund does not exist, or you do not have the sufficient permissions to view it.", "/");
			return false;
		}
		
		$_REQUEST['fund_id'] = $this->fund['fund_id'];
		
		if (!$this->auth->isAdmin() && !$this->isManager()) {
			$this->writeLog('manager', 'manager-error-not-manager');
			$this->message->setError("Access violation.", "/");
		}
		
		if (array_key_exists('is_ajax', $_REQUEST) && $_REQUEST['is_ajax'] == '1') {
			ob_clean();
		} else {
			if ($this->auth->isAdmin())
				$this->template->load("admin/header_admin");
			else {
				$this->template->unread_notification = $this->getUnreadNotification();
				$this->template->manage_users = $this->getManageUsers();
				$this->template->load("fund_manager/header_fund_manager");
			}
		}
	}
	
	public function index()
	{
		$this->template->load("fund_manager/index");	
	}
	
	public function returns_save()
	{
		if ($this->validateData(array('return', 'year', 'unit'), array('return', 'year', 'unit'))) {
			$_SESSION['is_edit'] = $_REQUEST['is_edit'];
			unset($_REQUEST['is_edit']);
			$document_id = null;
				
			if ($_FILES['document']['size'] > 0) {
					$_REQUEST['category_id'] = 3;
					$this->documents_save(true); // upload the document, and set request params
	
			$category_id = $this->getDocumentCategoryReturnsLetter($this->fund['frequency']);
	
			$title = $_REQUEST['unit'].'-'.$_REQUEST['year'].' update';
	
			$document_date = $_REQUEST['year'] .'-'. $_REQUEST['unit'].'-01';
	
			$document_insert_q = $this->db->prepare("INSERT INTO fund_documents
					(document_code, fund_id, category_id, title, name_storage, name_actual, mime_type, size, public, document_date) VALUES
					(:document_code, :fund_id, :category_id, :title, :name_storage, :name_actual, :mime_type, :size, 2, :document_date);");
			$document_insert_q->bindParam('document_code', $_REQUEST['document_code']);
			$document_insert_q->bindParam('fund_id', $this->fund['fund_id']);
			$document_insert_q->bindParam('category_id', $category_id);
			$document_insert_q->bindParam('title', $title);
			$document_insert_q->bindParam('name_storage', $_REQUEST['name_storage']);
			$document_insert_q->bindParam('name_actual', $_REQUEST['name_actual']);
			$document_insert_q->bindParam('mime_type', $_REQUEST['mime_type']);
			$document_insert_q->bindParam('size', $_REQUEST['size']);
			$document_insert_q->bindParam('document_date', $document_date);
			$document_insert_q->execute();
	
			$document_id = $this->db->lastInsertId();
		}
			
		if ($document_id) {
			$returns_update_q = $this->db->prepare("INSERT INTO fund_returns (`fund_id`, `unit`, `year`, `return`, `document_id`) VALUES (:fund_id, :unit, :year, :return, :document_id) ON DUPLICATE KEY UPDATE `return` = :return, `document_id` = :document_id");
			$returns_update_q->bindParam(':document_id', $document_id);
		} else {
			$returns_update_q = $this->db->prepare("INSERT INTO fund_returns (`fund_id`, `unit`, `year`, `return`) VALUES (:fund_id, :unit, :year, :return) ON DUPLICATE KEY UPDATE `return` = :return");
		}
		
		$returns_update_q->bindParam(':fund_id', $this->fund['fund_id']);
		$returns_update_q->bindParam(':unit', $_REQUEST['unit']);
		$returns_update_q->bindParam(':year', $_REQUEST['year']);
		$returns_update_q->bindParam(':return', $_REQUEST['return']);
		$returns_update_q->execute();
	
		// Trigger recalculation
							
		if (!$document_id) {
			require("calculator.php");
		
			$c = new calculator($this->db, $this->fund, $this->getBenchmarks(), $this->getRfBenchmarks());
			$c->processFund();
		}
			
		$this->writeLog('manager', 'returns-save');
		if (array_key_exists('subact', $_REQUEST) && $_REQUEST['subact'] == 'email' && $document_id) {
			$url = $this->getUrl('document/view_frame/'.$_REQUEST['document_code']);
	
			$attachment_file = 'cs-document-'.time().'.html';
			$attachment_content = '<html><head><meta http-equiv="refresh" content="0; url='.$url.'"></head><body><a href="'.$url.'">Click here to proceed</a></body></html>';
	
			$this->sendEmail('attachment', $this->auth->getEmail(), 'ClaritySpring Document', array(), $attachment_file, $attachment_content);
	
			$this->message->setSuccess("Return data has been updated &amp; and the attachment has been emailed to you.", '/fund/returns_edit/'.$_REQUEST['q']);
		} else if (array_key_exists('subact', $_REQUEST) && $_REQUEST['subact'] == 'email' && !$document_id) {
					$this->message->setError("Return data has been updated, but no document was uploaded.", '/fund/returns_edit/'.$_REQUEST['q']);
				} else {
					$this->message->setSuccess("Return data has been updated.", '/fund/returns_edit/'.$_REQUEST['q']);
				}
		}
	}
	
	
	public function returns_import()
	{
		if (!$this->auth->isAdmin()) {
			$this->writeLog('manager', 'returns-import-error-not-admin');
			$this->message->setError("Access violation.", '/fund/returns_edit/'.$_REQUEST['q']);
		}
	
		if (array_key_exists('file', $_FILES)) {
			if (($handle = fopen($_FILES['file']['tmp_name'], "r")) !== FALSE) {
				$i = 0;
				while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
					if ($i == 0) {
						$i++;
						continue;
					}
	
					$date = explode('/', $data[0]);
					if ($this->fund['frequency'] == 'monthly') {
						$unit = $date[0];
						$year = $date[2];
					} else {
						$unit = $this->getQuarterFromDate($data[0]);
						$year = $date[2];
					}
						
					$returns = $data[1];
						
					$returns_update_q = $this->db->prepare("INSERT INTO fund_returns (`fund_id`, `unit`, `year`, `return`) VALUES (:fund_id, :unit, :year, :return) ON DUPLICATE KEY UPDATE `return` = :return");
						
					$returns_update_q->bindParam(':fund_id', $this->fund['fund_id']);
					$returns_update_q->bindParam(':unit', $unit);
					$returns_update_q->bindParam(':year', $year);
					$returns_update_q->bindParam(':return', $returns);
					$returns_update_q->execute();
				}
			}
		}
	
		// Trigger recalculation
		require("calculator.php");
			
		$c = new calculator($this->db, $this->fund, $this->getBenchmarks(), $this->getRfBenchmarks());
		$c->processFund();
			
		$this->writeLog('manager', 'returns-import');
			
		$this->message->setSuccess("Return data has been updated.", '/fund/returns_edit/'.$_REQUEST['q']);
	}
	
}