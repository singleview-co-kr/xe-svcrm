<?php
/**
* @class  svcrm
* @author singleview(root@singleview.co.kr)
* @brief  svcrm module high class
**/ 
//$oDB = &DB::getInstance();
class svcrm extends ModuleObject 
{
/**
 * @brief install the module
 **/
	function moduleInstall() 
	{
		return new BaseObject();
	}
/**
 * @brief check module method
 **/
	function checkUpdate() 
	{
		$oModuleModel = &getModel('module');
		if(!$oModuleModel->getTrigger('member.insertMember', 'svcrm', 'controller', 'triggerInsertMemberAfter', 'after'))
			return true;
		if(!$oModuleModel->getTrigger('member.deleteMember', 'svcrm', 'controller', 'triggerDeleteMemberBefore', 'before'))
			return true;
		if(!$oModuleModel->getTrigger('document.insertDocument', 'svcrm', 'controller', 'validateContentsBefore', 'before'))
			return true;
		if(!$oModuleModel->getTrigger('comment.insertComment', 'svcrm', 'controller', 'validateContentsBefore', 'before'))
			return true;
		if(!$oModuleModel->getTrigger('document.insertDocument', 'svcrm', 'controller', 'triggerInsertDocument', 'after'))
			return true;
		if(!$oModuleModel->getTrigger('comment.insertComment', 'svcrm', 'controller', 'triggerInsertComment', 'after'))
			return true;

		if(!$oModuleModel->getTrigger('member.getMemberMenu', 'svcrm', 'model', 'triggerMemberMenu', 'before'))
		{
			$oModuleController = &getController('module');
			$oModuleController->inser1tTrigger('member.getMemberMenu', 'svcrm', 'model', 'triggerMemberMenu', 'before');
		}
		return false;
	}
/**
 * @brief update module
 **/
	function moduleUpdate() 
	{
		// 회원가입 트리거
		$oModuleController = &getController('module');
		$oModuleController->insertTrigger('member.insertMember', 'svcrm', 'controller', 'triggerInsertMemberAfter', 'after');
		// 회원탈퇴 트리거
		$oModuleController->insertTrigger('member.deleteMember', 'svcrm', 'controller', 'triggerDeleteMemberBefore', 'before');
		$oModuleController->insertTrigger('member.getMemberMenu', 'svcrm', 'model', 'triggerMemberMenu', 'before');
		// 게시판 글 등록 트리거
		$oModuleController->insertTrigger('document.insertDocument', 'svcrm', 'controller', 'validateContentsBefore', 'before');
		$oModuleController->insertTrigger('comment.insertComment', 'svcrm', 'controller', 'validateContentsBefore', 'before');
		$oModuleController->insertTrigger('document.insertDocument', 'svcrm', 'controller', 'triggerInsertDocument', 'after');
		$oModuleController->insertTrigger('comment.insertComment', 'svcrm', 'controller', 'triggerInsertComment', 'after');
		return new BaseObject(0, 'success_updated');
	}
}
?>