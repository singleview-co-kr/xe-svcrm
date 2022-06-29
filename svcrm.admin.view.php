<?php
/**
* @class  svcrm Admin View
* @author singleview(root@singleview.co.kr)
* @brief  svcrm admin View class
**/ 
class svcrmAdminView extends svcrm 
{
/**
 * @brief 초기화
 **/
	public function init()
	{
		// module이 svshopmaster일때 관리자 레이아웃으로
		if(Context::get('module') == 'svshopmaster')
		{
			$sClassPath = _XE_PATH_ . 'modules/svshopmaster/svshopmaster.class.php';
			if(file_exists($sClassPath))
			{
				require_once($sClassPath);
				$oSvshopmaster = new svshopmaster;
				$oSvshopmaster->init($this);
			}
		}
		
		// module_srl이 있으면 미리 체크하여 존재하는 모듈이면 module_info 세팅
		$module_srl = Context::get('module_srl');
		if( !$module_srl && $this->module_srl )
		{
			$module_srl = $this->module_srl;
			Context::set( 'module_srl', $module_srl );
		}

		$oModuleModel = &getModel('module');
		// module_srl이 넘어오면 해당 모듈의 정보를 미리 구해 놓음
		if( $module_srl ) 
		{
			$module_info = $oModuleModel->getModuleInfoByModuleSrl( $module_srl );
			if( !$module_info )
			{
				Context::set('module_srl','');
				$this->act = 'list';
			}
			else
			{
				ModuleModel::syncModuleToSite($module_info);
				$this->module_info = $module_info;
				Context::set('module_info',$module_info);
			}
		}
		if($module_info && !in_array($module_info->module, array('svcrm')))
			return $this->stop("msg_invalid_request");

		//if(Context::get('module')=='svshopmaster')
		//{
		//	$this->setLayoutPath('');
		//	$this->setLayoutFile('common_layout');
		//}
		
		// set template file
		$tpl_path = $this->module_path.'tpl';
		$this->setTemplatePath($tpl_path);
		$this->setTemplateFile('index');
		Context::set('tpl_path', $tpl_path);

		//모듈설정은 항상 미리세팅
		$config = $oModuleModel->getModuleConfig('svcrm');
		Context::set('config',$config);
		
		$oSvcrmAdminModel = &getAdminModel('svcrm');
		if( $oSvcrmAdminModel->checkPrivacyAccessConfigurePermission() )
			Context::set('privacy_access_configure', true);	
	}
/**
 * @brief 모듈 목록 화면
 **/
	public function dispSvcrmAdminModInstList() 
	{
		$oSvcrmAdminModel = &getAdminModel('svcrm');
		$aList = $oSvcrmAdminModel->getModInstList(Context::get('page'));
		Context::set('list', $aList);
		
		$oModuleModel = &getModel('module');
		$module_category = $oModuleModel->getModuleCategories();
		Context::set('module_category', $module_category);
		$this->setTemplateFile('modinstlist');
	}
/**
 * @brief 모듈 생성 화면
 **/
	public function dispSvcrmAdminInsertModInst() 
	{
		if(!getClass('svpg'))
			return new BaseObject(-1, 'msg_error_svpg_not_exsited');

		// 스킨 목록을 구해옴
		$oModuleModel = &getModel('module');
		$skin_list = $oModuleModel->getSkins($this->module_path);
		Context::set('skin_list',$skin_list);
		$mskin_list = $oModuleModel->getSkins($this->module_path, "m.skins");
		Context::set('mskin_list', $mskin_list);
		// 레이아웃 목록을 구해옴
		$oLayoutModel = &getModel('layout');
		$layout_list = $oLayoutModel->getLayoutList();
		Context::set('layout_list', $layout_list);
		$mobile_layout_list = $oLayoutModel->getLayoutList(0,"M");
		Context::set('mlayout_list', $mobile_layout_list);
		$module_category = $oModuleModel->getModuleCategories();
		Context::set('module_category', $module_category);
		// svpg plugin list
		$oSvpgModel = &getModel('svpg');
		$oSvPgModules = $oSvpgModel->getSvpgList();
		Context::set('svpg_modules', $oSvPgModules);
		$oSvorderAdminModel = &getAdminModel('svorder');
		$sExtScript = $oSvorderAdminModel->getExtScript($this->module_info->module_srl, 'ordercomplete');
		Context::set('ext_script', htmlspecialchars($sExtScript) );
		$this->setTemplateFile('insertmodinst');
	}
/**
 * @brief 
 **/
	public function dispSvcrmAdminGmailConfig()
	{
		$oSvcrmAdminModel = &getAdminModel('svcrm');
		$oConfig = $oSvcrmAdminModel->getModuleConfig();
		Context::set('config', $oConfig);

		$this->setTemplateFile('gmail_mgmt');
	}
/**
 * @brief 개인정보 열람 권한 설정, 허용상태일때만 모든 작동이 가능함
 **/
	public function dispSvcrmAdminPrivacyAccessConfig()
	{
		$oSvcrmAdminModel = &getAdminModel('svcrm');
		if( !$oSvcrmAdminModel->checkPrivacyAccessConfigurePermission() )
			return new BaseObject(-1, 'msg_not_permitted');

		$config = $oSvcrmAdminModel->getModuleConfig();
		
		$oMemberAdminModel = getAdminModel('member');
		Context::set('filter_type', 'super_admin');
		$output = $oMemberAdminModel->getMemberList();
		Context::set('admin_list', $output->data);
		Context::set('filter_type', '');
		
		$aPrivacyConfig = array();
		foreach( $config->privacy_access_policy as $key=>$val)
		{
			foreach( $val->allow_list as $key1=>$val1 )
				$aPrivacyConfig[$key][$val1] = 'Y';
		}
		Context::set('config', $aPrivacyConfig);
		Context::set('board_list', $aBoard);
		$this->setTemplateFile('privacy_access_mgmt');
	}
/**
 * @brief 글등록 SMS 알림 관리 화면
 **/
	public function dispSvcrmAdminInquiryInformMgmt()
	{
		$oSvcrmAdminModel = &getAdminModel('svcrm');
		$config = $oSvcrmAdminModel->getModuleConfig();
		$output = executeQueryArray('board.getBoardList', $args);
		ModuleModel::syncModuleToSite($output->data);
		
		$nIdx = 0;
		$aBoard = array();
		foreach( $output->data as $key=>$val)
		{
			if(is_null($aBoard[$nIdx]))
				$aBoard[$nIdx] = new stdClass();
			$aBoard[$nIdx]->module_srl =  $val->module_srl;
			$aBoard[$nIdx++]->mid =  $val->mid;
		}
		Context::set('config', $config);
		Context::set('board_list', $aBoard);

		$oMemberModel = getModel('member');
		$oGroups = $oMemberModel->getGroups();
		$aGroupList = array();
		foreach( $oGroups as $key=>$val )
		{
			if(is_null($aGroupList[$key]))
				$aGroupList[$key] = new stdClass();
			$aGroupList[$key]->group_srl = $val->group_srl;
			$aGroupList[$key]->title = $val->title;
		}
		Context::set('group_list', $aGroupList);
		$this->setTemplateFile('board_inform_mgmt');
	}
/**
 * @brief 기본 관리 화면
 **/
	public function dispSvcrmAdminIndex()
	{
		$this->setTemplateFile('index');
	}
/**
 * @brief 고객 관계 관리
 **/
	public function dispSvcrmAdminCustomerList()
	{
		//if( getClass('svauth') )
		{
			$oMemberAdminModel = getAdminModel('member');
			$output = $oMemberAdminModel->getMemberList();

			$filter = Context::get('filter_type');
			global $lang;
			switch($filter)
			{
				case 'super_admin' : Context::set('filter_type_title', $lang->cmd_show_super_admin_member);break;
				case 'site_admin' : Context::set('filter_type_title', $lang->cmd_show_site_admin_member);break;
				default : Context::set('filter_type_title', $lang->cmd_show_all_member);break;
			}
			// retrieve list of groups for each member
			if($output->data)
			{
				$oMemberModel = getModel('member');
				$oSvauthAdminModel = getAdminModel('svauth');
				foreach($output->data as $key => $member)
				{
					$output->data[$key]->group_list = $oMemberModel->getMemberGroups($member->member_srl,0);
					$output->data[$key]->auth = $oSvauthAdminModel->getMemberAuthCheck($member->member_srl);
				}
			}
			$config = $this->memberConfig;
			$memberIdentifiers = array('user_id'=>'user_id', 'user_name'=>'user_name', 'nick_name'=>'nick_name');
			$usedIdentifiers = array();	

			if(is_array($config->signupForm))
			{
				foreach($config->signupForm as $signupItem)
				{
					if(!count($memberIdentifiers)) break;
					if(in_array($signupItem->name, $memberIdentifiers) && ($signupItem->required || $signupItem->isUse))
					{
						unset($memberIdentifiers[$signupItem->name]);
						$usedIdentifiers[$signupItem->name] = $lang->{$signupItem->name};
					}
				}
			}
			Context::set('total_count', $output->total_count);
			Context::set('total_page', $output->total_page);
			Context::set('page', $output->page);
			Context::set('member_list', $output->data);
			Context::set('usedIdentifiers', $usedIdentifiers);
			Context::set('page_navigation', $output->page_navigation);

			$security = new Security();
			$security->encodeHTML('member_list..user_name', 'member_list..nick_name', 'member_list..group_list..');
			$security->encodeHTML('search_target', 'search_keyword');
			$this->setTemplateFile('consumer_list');
		}
		//return new BaseObject(-1, 'msg_error_svauth_not_exsited');
	}
/**
 * @brief 사이트내 회원 정보 팝업으로 접근하는 구매 이력 화면
 **/
	public function dispSvcrmAdminConsumerInterest()
	{
		$oMemberModel = &getModel('member');
		$member_config = $oMemberModel->getMemberConfig();
		$nMemberSrl = Context::get('member_srl');
		$site_module_info = Context::get('site_module_info');
		$columnList = array('member_srl', 'user_id', 'email_address', 'user_name', 'nick_name', 'regdate', 'last_login', 'extra_vars');
		$member_info = $oMemberModel->getMemberInfoByMemberSrl($nMemberSrl, $site_module_info->site_srl, $columnList);
		unset($member_info->password);
		unset($member_info->email_id);
		unset($member_info->email_host);

		Context::set('memberInfo', get_object_vars($member_info));
		$extendForm = $oMemberModel->getCombineJoinForm($member_info);
		unset($extendForm->find_member_account);
		unset($extendForm->find_member_answer);
		Context::set('extend_form_list', $extendForm);
		$oMemberView = &getView('member');
		$oMemberView->_getDisplayedMemberInfo($member_info, $extendForm, $member_config);

		$oSvcrmAdminModel = &getAdminModel('svcrm');
		$oConfig = $oSvcrmAdminModel->getModuleConfig();

		$oComLog = $oSvcrmAdminModel->getCommunicationLog($nMemberSrl);
		Context::set('total_count', $oComLog->total_count);
		Context::set('total_page', $oComLog->total_page);
		Context::set('page', $oComLog->page);
		Context::set('comm_log', $oComLog->data);

		$oSvauthAdminModel = &getAdminModel('svauth');
		$oData = $oSvauthAdminModel->getMemberAuthInfo($nMemberSrl,$oConfig->privacy_access_policy);
		Context::set('auth_form_list', $oData);

		$oSvorderAdminModel = &getAdminModel('svorder');
		$order_list = $oSvorderAdminModel->getOrdersByMemberSrl($nMemberSrl);
		Context::set('order_list', $order_list);
		//Context::set('order_status', $this->getOrderStatus());
		//Context::set('delivery_inquiry_urls', $this->delivery_inquiry_urls);
		$this->setTemplateFile('consumer_history');
	}
/**
 * @brief display the member registration management
 **/
	public function dispSvcrmAdminRegistrationMgmt()
	{
		if( getClass('svpromotion') )
		{
			$oSvcrmAdminModel = &getAdminModel('svcrm');
			$config = $oSvcrmAdminModel->getModuleConfig();
			if( is_null( getClass('textmessage') ) )
				$config->sms_welcome_msg_activate = 'off';
			Context::set('config',$config);
			$oMemberModel = &getModel('member');
			$oMemberJoinFormList = $oMemberModel->getUsedJoinFormList();
			foreach( $oMemberJoinFormList as $key => $val )
			{
				$val->default_value = unserialize( $val->default_value );
				$val->current_opt =  $config->{$val->column_name};
			}
			Context::set('member_joinfrom_list',$oMemberJoinFormList);
			$oSvpromotionModel = &getModel('svpromotion');
			$oCouponPromotionList = $oSvpromotionModel->getCouponPromotionList();
			Context::set('coupon_promotion_list', $oCouponPromotionList->data);
			$this->setTemplateFile('member_registration_mgmt');
		}
		else
			return new BaseObject(-1, 'msg_error_svpromotion_not_exsited');
	}
/**
 * @brief display the order stauts update inform management
 **/
	public function dispSvcrmAdminOrderInformMgmt()
	{
		if( getClass('svorder') )
			$this->setTemplateFile('order_status_inform_mgmt');
		else
			return new BaseObject(-1, 'msg_error_svorder_not_exsited');
	}
/**
 * @brief 
 **/
	public function dispSvcrmAdminGuestSms()
	{
		if( !getClass('textmessage') )
			return new BaseObject(-1, 'msg_error_textmessage_not_exsited');
		
		$oSvcrmAdminModel = &getAdminModel('svcrm');
		$oComLog = $oSvcrmAdminModel->getCommunicationLog(0);
		Context::set('total_count', $oComLog->total_count);
		Context::set('total_page', $oComLog->total_page);
		Context::set('page', $oComLog->page);
		Context::set('comm_log', $oComLog->data);
		$this->setTemplateFile('guest_sms_mgmt');			
	}
/**
 * @brief display default setting screen
 **/
	public function dispSvcrmAdminConfig()
	{
		$oSvcrmAdminModel = &getAdminModel('svcrm');
		$config = $oSvcrmAdminModel->getModuleConfig();
		Context::set('config',$config);
		$this->setTemplateFile('default_setting');
	}
}
?>