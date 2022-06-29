<?php
/**
* @class  svcrmAdminController
* @author singleview(root@singleview.co.kr)
* @brief  svcrm admin Controller class
**/ 
class svcrmAdminController extends svcrm 
{
 /**
 * @brief 초기화
 **/
	public function init()
	{
	}
/**
 * @brief 
 **/
	public function procSvcrmAdminInsertConfig()
	{
		$sSmsSenderNo = trim(Context::get('sender_no'));
		$sSmsSenderNo = preg_replace("/[^0-9]*/s", '', $sSmsSenderNo);
		$nCouponSize = (int)Context::get('coupon_digit_number');
		$oArgs = new stdClass();
		if( $nCouponSize > 3 )
			$oArgs->coupon_digit_number = $nCouponSize;
		$oArgs->sender_no = $sSmsSenderNo;
		$oArgs->ignore_member_srls = trim(Context::get('ignore_member_srls'));
		$oArgs->visitor_document_log_activation = trim(Context::get('visitor_document_log_activation'));
		$oArgs->visitor_comment_log_activation = trim(Context::get('visitor_comment_log_activation'));
		$oArgs->visitor_comment_log_doc_srls = trim(Context::get('visitor_comment_log_doc_srls'));
		
		$output = $this->_saveModuleConfig($oArgs);
		if(!$output->toBool())
			$this->setMessage( 'error_occured' );
		else
			$this->setMessage( 'success_updated' );

		$this->setRedirectUrl(getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvcrmAdminConfig' ));
	}
/**
 * @brief 모듈 생성
 **/
	public function procSvcrmAdminInsertModInst() 
	{
		// module 모듈의 model/controller 객체 생성
		$oModuleController = &getController('module');
		$oModuleModel = &getModel('module');

		// 게시판 모듈의 정보 설정
		$args = Context::getRequestVars();
		$args->module = 'svcrm';
		// module_srl이 넘어오면 원 모듈이 있는지 확인
		if($args->module_srl) 
		{
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($args->module_srl);
			if($module_info->module_srl != $args->module_srl)
				unset($args->module_srl);
			foreach( $args as $key=>$val)
				$module_info->{$key} = $val;
		}
		// module_srl의 값에 따라 insert/update
		if(!$args->module_srl) 
		{
			$output = $oModuleController->insertModule($args);
			$msg_code = 'success_registed';
		}
		else
		{
			$output = $oModuleController->updateModule($args);
			$msg_code = 'success_updated';
		}
		if(!$output->toBool())
			return $output;

		$this->add('module_srl',$output->get('module_srl'));
		$this->setMessage($msg_code);
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) 
		{
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvcrmAdminModInstList','module_srl',$output->get('module_srl'));
			$this->setRedirectUrl($returnUrl);
			return;
		}
	}
/**
 * @brief 모듈 삭제
 **/
	public function procSvcrmAdminDeleteModInst()
	{
		$module_srl = Context::get('module_srl');
		$oModuleController = &getController('module');
		$output = $oModuleController->deleteModule($module_srl);
		if(!$output->toBool())
			return $output;
		$this->add('module', 'svcrm');
		$this->add('page', Context::get('page'));
		$this->setMessage('success_deleted');
		$returnUrl = getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvcrmAdminModInstList');
		$this->setRedirectUrl($returnUrl);
	}
/**
 * @brief consumer privacy information access leve configure
 * configure sample -> $aPrivacyAccess[$member_srl]->allow_list=array('auth_date','user_name','birthday', 'gender','nationality','mobile','ISP'); 
 **/
	public function procSvcrmAdminUpdatePrivacyAccess()
	{
		$oSvcrmAdminModel = &getAdminModel('svcrm');
		if( !$oSvcrmAdminModel->checkPrivacyAccessConfigurePermission() )
			return new BaseObject(-1, 'msg_not_permitted');

		$oTempArgs = Context::getRequestVars();
		foreach( $oTempArgs as $key=>$val)
		{
			switch( $key )
			{
				case 'autdate_access':
					foreach( $val as $key1=>$val1 )
						$aPrivacyAccess[$val1]->allow_list[] = 'auth_date';
					break;
				case 'username_access':
					foreach( $val as $key2=>$val2 )
						$aPrivacyAccess[$val2]->allow_list[] = 'user_name';
					break;
				case 'birthday_access':
					foreach( $val as $key3=>$val3 )
						$aPrivacyAccess[$val3]->allow_list[] = 'birthday';
					break;
				case 'gender_access':
					foreach( $val as $key4=>$val4 )
						$aPrivacyAccess[$val4]->allow_list[] = 'gender';
					break;
				case 'nationality_access':
					foreach( $val as $key5=>$val5 )
						$aPrivacyAccess[$val5]->allow_list[] = 'nationality';
					break;
				case 'mobile_access':
					foreach( $val as $key6=>$val6 )
						$aPrivacyAccess[$val6]->allow_list[] = 'mobile';
					break;
				case 'isp_access':
					foreach( $val as $key7=>$val7 )
						$aPrivacyAccess[$val7]->allow_list[] = 'ISP';
					break;
			}
		}
		$oArgs->privacy_access_policy = $aPrivacyAccess;
		$output = $this->_saveModuleConfig($oArgs);
		if(!$output->toBool())
			$this->setMessage( 'error_occured' );
		else
			$this->setMessage( 'success_updated' );
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvcrmAdminPrivacyAccessConfig' ));
	}
/**
 * @brief crm admin can send SMS to a member via this method
 **/
	public function procSvcrmAdminSendSmsToMember()
	{
		$oLoggedInfo = Context::get('logged_info');
		if($oLoggedInfo->is_admin != 'Y' )
			return new BaseObject(-1, 'msg_error_no_privileges');

		$sConents = trim( Context::get('something_to_say') );
		$sConents = strip_tags($sConents);
		if(!strlen( $sConents ))
			return new BaseObject(-1, 'msg_error_nothing_to_say');

		$nMemberSrl = (int)Context::get('member_srl');
		// SMS 번호를 확인하기 위해서 임시로 개인정보 접근 권한
		$aTempPrivacyAccess[$oLoggedInfo->member_srl]->allow_list[] = 'mobile';
		
		$oSvauthAdminModel = &getAdminModel('svauth');
		$oData = $oSvauthAdminModel->getMemberAuthInfo($nMemberSrl,$aTempPrivacyAccess);
		$oArgs->recipient_no = $oData->인증핸드폰;
		$oArgs->sender_member_srl = $oLoggedInfo->member_srl;
		$oArgs->recepient_member_srl = $nMemberSrl;
		$oArgs->content = $sConents;
		$oSvcrmController = &getController('svcrm');
		$output = $oSvcrmController->sendSms($oArgs);

		if(!$output->toBool()) 
			return $output;
		
		$this->setMessage( 'success_transmitted' );
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvcrmAdminConsumerInterest', 'member_srl', $nMemberSrl ));
	}
/**
 * @brief crm admin can send SMS to a guest via this method
 **/
	public function procSvcrmAdminSendSmsToGuest()
	{
		$oLoggedInfo = Context::get('logged_info');
		if($oLoggedInfo->is_admin != 'Y' )
			return new BaseObject(-1, 'msg_error_no_privileges');

		$sConents = trim( Context::get('something_to_say') );
		$sConents = strip_tags($sConents);
		if(!strlen( $sConents ))
			return new BaseObject(-1, 'msg_error_nothing_to_say');
		
		$oArgs = new stdClass();
		$sGuestPhoneNumber =  preg_replace("/[^0-9]*/s", '', strip_tags(trim(Context::get('guest_phone_number'))));
		$oArgs->recipient_no = $sGuestPhoneNumber;
		$oArgs->sender_member_srl = $oLoggedInfo->member_srl;
		$oArgs->recepient_member_srl = 0; // 0 is guest
		$oArgs->content = $sConents;
		$oSvcrmController = &getController('svcrm');
		$output = $oSvcrmController->sendSms($oArgs);

		if(!$output->toBool()) 
			return $output;
		
		$this->setMessage( 'success_transmitted' );
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvcrmAdminGuestSms' ));
	}
/**
 * @brief insert inquiry registration SMS inform
 **/
	public function procSvcrmAdminUpdateInquiryInformMgmt()
	{
		$oTempArgs = Context::getRequestVars();
		if( isset( $oTempArgs->except_member_group  ) )
		{
			$aTemp = array();
			foreach( $oTempArgs->except_member_group as $key=>$val)
				$aTemp[$val] = 'Y';
			$oTempArgs->except_member_group = $aTemp;
		}

		foreach( $oTempArgs as $key=>$val)
		{
			if($key == 'monitor_board')
			{
				foreach( $val as $rev_key=>$rev_val)
					$aMonitorBoard[$rev_val] = 'on';
			}
			if( $key == 'except_member_group' )
				$aExceptGrp = $val;
		}
		$oArgs = new stdClass();
		$oArgs->monitor_board = $aMonitorBoard;
		$oArgs->except_member_group = $aExceptGrp;
		$oArgs->crm_responsible_number = str_replace('-', '', $oTempArgs->crm_responsible_number);
		$oArgs->crm_slack_api_token = trim($oTempArgs->crm_slack_api_token);
		$oArgs->crm_slack_web_hook_url = trim($oTempArgs->crm_slack_web_hook_url);
		$oArgs->crm_slack_ch = str_replace('#', '', trim($oTempArgs->crm_slack_ch));
		
		$sSlackUserName = trim(strip_tags($oTempArgs->crm_slack_username));
		preg_match('/[Ss][Ll][Aa][Cc][Kk]/', $sSlackUserName, $matches, PREG_OFFSET_CAPTURE, 0); // slack API rejects slack-wise word
		if( array_key_exists( 0, $matches) )
			return new BaseObject(-1, 'msg_error_forbidden_slack_username');

		$oArgs->crm_slack_username = $sSlackUserName;
		$output = $this->_saveModuleConfig($oArgs);
		if(!$output->toBool())
			$this->setMessage( 'error_occured' );
		else
			$this->setMessage( 'success_updated' );
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvcrmAdminInquiryInformMgmt' ));
	}
/**
 * @brief insert registration promotion
 **/
	public function procSvcrmAdminUpdateRegistrationMgmt()
	{
		$nCouponPromotionSrl = (int)Context::get('registration_coupon_promotion_srl');
		$nRegistrationCouponSerialLength = (int)Context::get('registration_coupon_serial_length');
		$sSmsWelcomeMsgWithCoupon = trim(Context::get('sms_welcome_msg_with_coupon'));
		$sSmsWelcomeMsgDefault = trim(Context::get('sms_welcome_msg_default'));	
		
		if( is_null( getClass('textmessage') ) )
			$sSmsWelcomeActivate = 'off';
		else
			$sSmsWelcomeActivate = Context::get('sms_welcome_msg_yn');

		$oArgs->welcome_coupon_yn = Context::get('welcome_coupon_yn');
		$oArgs->sms_welcome_msg_activate = $sSmsWelcomeActivate;
		$oArgs->registration_coupon_promotion_srl = $nCouponPromotionSrl;
		$oArgs->registration_coupon_serial_length = $nRegistrationCouponSerialLength;
		$oArgs->sms_welcome_msg_with_coupon = $sSmsWelcomeMsgWithCoupon;
		$oArgs->sms_welcome_msg_default = $sSmsWelcomeMsgDefault;
		$oMemberModel = &getModel('member');
		$oMemberJoinFormList = $oMemberModel->getUsedJoinFormList();
		foreach( $oMemberJoinFormList as $key => $val )
		{
			$sValue = Context::get($val->column_name);
			if( !is_null( $sValue ) )
			{
				$val->default_value = unserialize( $val->default_value );
				$bCorrectValue = false;
				foreach( $val->default_value as $key1=>$val1)
				{
					if( $val1 == $sValue || $sValue == 'ignore_this_value' )
					{
						$bCorrectValue = true;
						$oArgs->{$val->column_name} = $sValue;
						break;
					}
				}
				if( !$bCorrectValue )
					return new BaseObject( -1, 'msg_invalid_registration_value' );
			}
		}
		$output = $this->_saveModuleConfig($oArgs);
		if(!$output->toBool())
			$this->setMessage( 'error_occured' );
		else
			$this->setMessage( 'success_updated' );
		
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvcrmAdminRegistrationMgmt' ));
	}
/**
 * @brief insert registration promotion
 **/
	public function procSvcrmAdminUpdateSmsOrderStatusUpdateMgmt()
	{
		$sWaitForDepostMsg = trim(Context::get('sms_order_status_wait_for_deposit'));
		$sWaitForDepostMsgActivate = Context::get('sms_order_status_wait_for_deposit_yn');
		$sDepostConfirmedMsg = trim(Context::get('sms_order_status_deposit_confirmed'));
		$sDepostConfirmedMsgActivate = Context::get('sms_order_status_deposit_confirmed_yn');
		$sDeliveryPreparationMsg = trim(Context::get('sms_order_status_delivery_preparation'));
		$sDeliveryPreparationMsgActivate = Context::get('sms_order_status_delivery_preparation_yn');
		$sOnDeliveryMsg = trim(Context::get('sms_order_status_on_delivery'));
		$sOnDeliveryMsgActivate = Context::get('sms_order_status_on_delivery_yn');
		$sCancelRequestMsg = trim(Context::get('sms_order_status_cancel_request'));
		$sCancelRequestMsgActivate = Context::get('sms_order_status_cancel_request_yn');
		$sCancelCompletedMsg = trim(Context::get('sms_order_status_cancel_completed'));
		$sCancelCompletedMsgActivate = Context::get('sms_order_status_cancel_completed_yn');

		if( is_null( getClass('textmessage') ) )
		{
			$sWaitForDepostMsgActivate = 'off';
			$sDepostConfirmedMsgActivate = 'off';
			$sDeliveryPreparationMsgActivate = 'off';
			$sOnDeliveryMsgActivate = 'off';
			$sCancelRequestMsgActivate = 'off';
			$sCancelCompletedMsgActivate = 'off';
		}
		else
		{
			if( strlen( $sWaitForDepostMsg ) == 0 || is_null( $sWaitForDepostMsg ) )
				$sWaitForDepostMsgActivate = 'off';
			if( strlen( $sDepostConfirmedMsg ) == 0 || is_null( $sDepostConfirmedMsg ) )
				$sDepostConfirmedMsgActivate = 'off';
			if( strlen( $sDeliveryPreparationMsg ) == 0 || is_null( $sDeliveryPreparationMsg ) )
				$sDeliveryPreparationMsgActivate = 'off';
			if( strlen( $sOnDeliveryMsg ) == 0 || is_null( $sOnDeliveryMsg ) )
				$sOnDeliveryMsgActivate = 'off';
			if( strlen( $sCancelRequestMsg ) == 0 || is_null( $sCancelRequestMsg ) )
				$sCancelRequestMsgActivate = 'off';
			if( strlen( $sCancelCompletedMsg ) == 0 || is_null( $sCancelCompletedMsg ) )
				$sCancelCompletedMsgActivate = 'off';
		}
		$oArgs = new stdClass();
		$oArgs->sms_order_status_wait_for_deposit = $sWaitForDepostMsg;
		$oArgs->sms_order_status_wait_for_deposit_yn = $sWaitForDepostMsgActivate;
		$oArgs->sms_order_status_deposit_confirmed = $sDepostConfirmedMsg;
		$oArgs->sms_order_status_deposit_confirmed_yn = $sDepostConfirmedMsgActivate;
		$oArgs->sms_order_status_delivery_preparation = $sDeliveryPreparationMsg;
		$oArgs->sms_order_status_delivery_preparation_yn = $sDeliveryPreparationMsgActivate;
		$oArgs->sms_order_status_on_delivery = $sOnDeliveryMsg;
		$oArgs->sms_order_status_on_delivery_yn = $sOnDeliveryMsgActivate;
		$oArgs->sms_order_status_cancel_request = $sCancelRequestMsg;
		$oArgs->sms_order_status_cancel_request_yn = $sCancelRequestMsgActivate;
		$oArgs->sms_order_status_cancel_completed = $sCancelCompletedMsg;
		$oArgs->sms_order_status_cancel_completed_yn = $sCancelCompletedMsgActivate;

		$output = $this->_saveModuleConfig($oArgs);
		if(!$output->toBool())
			$this->setMessage( 'error_occured' );
		else
			$this->setMessage( 'success_updated' );
		
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvcrmAdminOrderInformMgmt' ));
	}
/**
 * @brief member info + auth info csv download
 **/
	public function procSvcrmAdminCSVDownloadByMember() 
	{
		if( Context::get( 'search_key' ) )
		{
			$search_key = Context::get( 'search_key' );
			$search_value = Context::get( 'search_value' );
			if( $search_key == 'nick_name' && $search_value == '비회원' )
			{
				$search_key = 'member_srl';
				$search_value = 0;
			}
			$args->{ $search_key } = $search_value;
		}

		$oSvauthAdminModel = &getAdminModel( 'svauth' );
		$output = executeQueryArray('svcrm.getAuthMemberList', $args);
		
		if( count( $output->data ) == 0 )
			return null;

		header( 'Content-Type: Application/octet-stream; charset=UTF-8' );
		header( "Content-Disposition: attachment; filename=\"auth_member_data-".date('Ymd').".csv\"");
		echo chr( hexdec( 'EF' ) );
		echo chr( hexdec( 'BB' ) );
		echo chr( hexdec( 'BF' ) );
		//$oSvorderAdminModel = &getAdminModel( 'svcrm' );
		//$oDataConfig = $oSvorderAdminModel->getDataFormatConfig( $this->module_info->module_srl );
		//$nFieldCnt = count( $oDataConfig ) - 1;
		//$nIdx = 0;

		//foreach( $oDataConfig as $key => $val )
		//{
		//	echo $val->name;
		//	if( $nFieldCnt > $nIdx )
		//		echo ',';
		//	$nIdx++;
		//}

		// CSV 다운로드 요청자의 개인정보 열람 권한 획득
		$oSvcrmAdminModel = &getAdminModel( 'svcrm' );
		$oConfig = $oSvcrmAdminModel->getModuleConfig();

		// 기본 컬럼 제목 설정
		echo 'member_srl,user_id,email_address,auth_name,auth_birthdate,auth_gender,auth_nationality,auth_cell_no,auth_date';
		
		// extra_vars의 컬럼 제목 설정
		$oMemberModel = getModel('member');
		$oMemberExtendFormList = $oMemberModel->getCombineJoinForm($memberInfo);

		foreach( $oMemberExtendFormList as $key => $val)
			echo ','.$val->column_title;//.'<BR>';

		echo "\r\n";

		foreach( $output->data as $key => $val)
		{
			echo $val->member_srl.','.$val->user_id.','.$val->email_address.',';

			$oData = $oSvauthAdminModel->getMemberAuthInfo((int)$val->member_srl,$oConfig->privacy_access_policy);
			echo $oData->인증실명.',';

			if( $oData->인증생일 )
				echo date( "Y-m-d", strtotime($oData->인증생일) ).',';
			else
				echo ',';
			
			echo $oData->인증성별.','.$oData->인증국적.',';
			echo preg_replace("/(^02.{0}|^01.{1}|[0-9]{3})([0-9]+)([0-9]{4})/", "$1-$2-$3", $oData->인증핸드폰).',';
			if( $oData->인증일시 )
				echo date( "Y-m-d H:i:s", strtotime($oData->인증일시) );
			else
				echo ' ';

			$aMemeberExtraVar = unserialize( $val->extra_vars );
			foreach( $oMemberExtendFormList as $key1 => $val1)
			{
				echo ','.$aMemeberExtraVar->{$val1->column_name};
			}
			echo "\r\n";
		}
		Context::setResponseMethod('JSON'); // display class 작동 정지
	}
/**
 * @brief 
 **/
	public function procSvcrmAdminUpdateGmailMgmt()
	{
		$sGmailActivation = trim(Context::get('gmail_activation'));
		$sGmailAccount = trim(Context::get('gmail_account'));
		$sGmailPasswd = trim(Context::get('gmail_passwd'));
		$sGmailSenderName = trim(Context::get('gmail_sender_name'));
		$sGmailCommonFooter = trim(Context::get('gmail_common_footer'));
		$sGmailCommonFooter = preg_replace(array('/<(\?|\%)\=?(php)?/', '/(\%|\?)>/'), array('',''), $sGmailCommonFooter);
		$oArgs = new stdClass();
		$oArgs->gmail_activation = $sGmailActivation;
		$oArgs->gmail_account = $sGmailAccount;
		$oArgs->gmail_passwd = $sGmailPasswd;
		$oArgs->gmail_sender_name = $sGmailSenderName;
		$oArgs->gmail_common_footer = $sGmailCommonFooter;
		$output = $this->_saveModuleConfig($oArgs);
		unset($oArgs);
		if(!$output->toBool())
			$this->setMessage( 'error_occured' );
		else
			$this->setMessage( 'success_updated' );
		
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvcrmAdminGmailConfig' ));
	}
/**
 * @brief 
 **/
	public function procSvcrmAdminTestSendGmail()
	{
		$sTestReceiverAddr = Context::get( 'test_receiver_addr' );
		$aTestReceiverAddr = explode( ';', $sTestReceiverAddr );
		$oGmailParam->aReceiverInfo = array();

		foreach( $aTestReceiverAddr as $nIdx => $sEmail )
		{
			//if(preg_match("/^[_\.0-9a-zA-Z-]+@([0-9a-zA-Z][0-9a-zA-Z-]+\.)+[a-zA-Z]{2,6}$/i", $sEmail) == false)
			//	continue;// array(false, "올바른 이메일 주소를 입력해주세요.");
			$sReceiverTitle = 'receive'.$nIdx;
			$aTemp = array( 'receiver_addr'=>$sEmail, 'receiver_title'=>$sReceiverTitle );
			array_push($oGmailParam->aReceiverInfo, $aTemp );
		}
		
		if( count( $oGmailParam->aReceiverInfo ) )
		{
			$oGmailParam->aAttachmentFilePath = array( _XE_PATH_.'modules/svcrm/tpl/gmail_test/singleview_logo_512x512.png' );
			$oGmailParam->bHTML = true;
			$oGmailParam->sSubject = 'svcrm 발송 테스트 입니다.';
			$oGmailParam->sBody = 'svcrm 테스트 메일이 <b>성공</b>적으로 도착했습니다.';
			$bTestMode = true;
			$oRst = $this->sendGmail( $oGmailParam, $bTestMode );
		}
		else
			$oRst = new BaseObject(-1, 'msg_no_valid_recepient');
		return $oRst;
	}
/**
 * @brief gmail API를 이용해서 주문 관리 안내 발송
 * https://support.google.com/mail/?p=InvalidSecondFactor
 * https://support.google.com/mail/?p=BadCredentials
 **/
	public function sendGmail( $oParam, $bTestMode=false )
	{
		foreach( $oParam->aReceiverInfo as $nIdx => $aReceiverInfo ) // 받는 메일
		{
			if(preg_match("/^[_\.0-9a-zA-Z-]+@([0-9a-zA-Z][0-9a-zA-Z-]+\.)+[a-zA-Z]{2,6}$/i", $aReceiverInfo['receiver_addr']) == false)
				unset( $oParam->aReceiverInfo[$nIdx] );
		}

		if( count( $oParam->aReceiverInfo ) == 0 )
			return new BaseObject(-1, 'msg_no_valid_recepient');
		
		if( !$oParam->sSubject || !$oParam->sBody )
			return new BaseObject(-1, 'msg_incomplete_mail_contents');

		$oSvcrmAdminModel = &getAdminModel('svcrm');
		$oConfig = $oSvcrmAdminModel->getModuleConfig();
		if( $bTestMode == false && $oConfig->gmail_activation == 'off' )
			return new BaseObject(-1, 'msg_gmail_is_disallowed');

		if( !$oConfig->gmail_account || !$oConfig->gmail_passwd || !$oConfig->gmail_sender_name )
			return new BaseObject(-1, 'msg_invalid_gmail_configuration');

		if( is_null( $oParam->bHTML ) )
			$oParam->bHTML = true;

		require_once(_XE_PATH_.'modules/svcrm/ext_class/PHPMailer-6.0.7/src/PHPMailer.php');
		require_once(_XE_PATH_.'modules/svcrm/ext_class/PHPMailer-6.0.7/src/SMTP.php');
		require_once(_XE_PATH_.'modules/svcrm/ext_class/PHPMailer-6.0.7/src/Exception.php');
		$oMail = new PHPMailer\PHPMailer\PHPMailer(true);
		try
		{
			// 서버세팅
			$oMail->SMTPDebug = 0;//2 - 디버깅 설정, 0 - No output
			$oMail->isSMTP(); // SMTP 사용 설정
			$oMail->Host = 'smtp.gmail.com'; // email 보낼때 사용할 서버를 지정
			$oMail->SMTPAuth = true; // SMTP 인증을 사용함
			$oMail->Username = $oConfig->gmail_account; // 메일 계정
			$oMail->Password = $oConfig->gmail_passwd; // 메일 비밀번호
			$oMail->SMTPSecure = 'ssl'; // SSL을 사용함
			$oMail->Port = 465; // email 보낼때 사용할 포트를 지정
			$oMail->CharSet = 'utf-8'; // 문자셋 인코딩
			$oMail->setFrom($oConfig->gmail_account, $oConfig->gmail_sender_name); // 보내는 메일
			
			foreach( $oParam->aReceiverInfo as $nIdx => $aReceiverInfo ) // 받는 메일
				$oMail->addAddress($aReceiverInfo['receiver_addr'], $aReceiverInfo['receiver_title']); 
			
			foreach( $oParam->aAttachmentFilePath as $nIdx => $sFilePath ) // 첨부파일
				$oMail->addAttachment($sFilePath);

			// 메일 내용
			$oMail->isHTML($oParam->bHTML); // HTML 태그 사용 여부
			$oMail->Subject = $oParam->sSubject; // 메일 제목
///////////////////
			$oMail->Body = $oParam->sBody.'<BR><BR>'.$oConfig->gmail_common_footer;    // 메일 내용
///////////////////
			// Gmail로 메일을 발송하기 위해서는 CA인증이 필요하다.
			// CA 인증을 받지 못한 경우에는 아래 설정하여 인증체크를 해지하여야 한다.
			$oMail->SMTPOptions = array(
				'ssl' => array(
					'verify_peer' => false,
					'verify_peer_name' => false,
					'allow_self_signed' => true
				)
			);
			$bRst = $oMail->send();
			if( $bRst )
				return new BaseObject('msg_transmission_succeed');
			else
				return new BaseObject(-1, 'error_occured');
		} 
		catch (Exception $e) 
		{
			$sErr = 'Message could not be sent. Mailer Error : '.$oMail->ErrorInfo;
			return new BaseObject(-1, $sErr );
		}
	}
/**
 * @brief arrange and save module config
 **/
	private function _saveModuleConfig($oArgs)
	{
		$oSvcrmAdminModel = &getAdminModel('svcrm');
		$oConfig = $oSvcrmAdminModel->getModuleConfig();
		if(is_null($oConfig))
			$oConfig = new stdClass();
		foreach( $oArgs as $key=>$val)
			$oConfig->{$key} = $val;

		$oModuleControll = getController('module');
		$output = $oModuleControll->insertModuleConfig('svcrm', $oConfig);
		return $output;
	}
}
?>