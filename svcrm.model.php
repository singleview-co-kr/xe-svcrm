<?php
/**
* @class  svcrmModel
* @author singleview(root@singleview.co.kr)
* @brief  svcrm module Model class
**/ 
class svcrmModel extends svcrm 
{
/**
 * @brief initialization
 **/
	function init() 
	{
	}
/**
 * @brief
 */
	public function getAuthLog($sDi)
	{
		$args->di = $sDi;
		$output = executeQuery('svcrm.getLog', $args);

		if(!$output->data) 
			return null;

		if( strlen( $output->data->di ) > 0 )
			return unserialize( $output->data->auth_info);
		else
			return null;
	}
/**
 * @brief 게시판 회원 정보 관리 팝업 메뉴 등록
 **/
	public function triggerMemberMenu($in_args)
	{
		$logged_info = Context::get('logged_info');
		if($logged_info && $logged_info->is_admin=='Y')
		{	
			$url = getUrl('','module','admin','act','dispSvcrmAdminConsumerInterest','member_srl',Context::get('target_srl'));
			$oMemberController = &getController('member');
			$oMemberController->addMemberPopupMenu($url, Context::getLang('cmd_customer_management'), '', 'popup');
		}
	}
}
?>