<?php
/**
* @class  svcrmController
* @author singleview(root@singleview.co.kr)
* @brief  svcrm module Controller class
**/ 
class svcrmController extends svcrm 
{
/**
 * @brief initialization
 **/
	public function init() 
	{
	}
/**
 * @brief 게시판 글 등록 후 트리거
 **/
	public function triggerInsertDocument(&$obj) 
	{
		$oMemberModel = &getModel('member');
		if( $obj->member_srl )
		{
			$oWriterInfo = $oMemberModel->getMemberInfoByMemberSrl((int)$obj->member_srl);
			if( $oWriterInfo->is_admin == 'Y' )
				return;
			
			foreach( $oWriterInfo->group_list as $key=>$val)
			{
				if( $config->except_member_group[$key] == 'Y' )
					return;
			}
		}

		$oSvcrmAdminModel = &getAdminModel('svcrm');
		$config = $oSvcrmAdminModel->getModuleConfig();
		if( $oWriterInfo->is_admin != 'Y' && $config->visitor_document_log_activation == 'on')
		{
			require_once(_XE_PATH_.'modules/svcrm/svcrm.log_trigger.php');
			$oEnageLog = new svcrmEngageLogTrigger($obj, $config);
			unset($oEnageLog);
		}
		$nModuleSrl = (int)$obj->module_srl;
		if( strlen( $config->crm_responsible_number ) )
		{
			$aRecipientNo = explode( ';', $config->crm_responsible_number );
			
			$args->type = 'SMS';
			$args->country_code = 82;
			$args->sender_no = $config->sender_no;
			$args->content = $_SERVER[SERVER_NAME].'/'.$obj->mid.'/'.$obj->document_srl.' 글 등록';
			$controller = &getController('textmessage');
			
			foreach( $config->monitor_board as $key=>$val)
			{
				if( $key == $nModuleSrl )
				{
					foreach( $aRecipientNo as $rekey=>$reval )
					{
						$args->recipient_no =  $reval;
						$controller->sendMessage($args);
					}
					continue;
				}
			}
		}
		
		if($config->crm_slack_web_hook_url && $config->crm_slack_ch && $config->crm_slack_username)
		{
			foreach( $config->monitor_board as $key=>$val)
			{
				if( $key == $nModuleSrl.'_doc' )
				{
					$this->_sendSlackMessageDocument($obj, $config, 'document');
					continue;
				}
			}
		}
	}
/**
 * @brief Slack Messenger에 메세지 전송 - Board module이 아닐 때
 **/
	public function sendSlackMessage($sMid, $sDocType)
	{
		$oSvcrmAdminModel = &getAdminModel('svcrm');
		$oConfig = $oSvcrmAdminModel->getModuleConfig();
		if($oConfig->crm_slack_web_hook_url && $oConfig->crm_slack_ch && $oConfig->crm_slack_username)
		{
			require_once(_XE_PATH_.'modules/svcrm/slack.class.php');
			$oSlack = new SlackIncomingWebHook();
			$oSlack->setWebHookUrl($oConfig->crm_slack_web_hook_url);
			$oSlack->setChannel('#'.$oConfig->crm_slack_ch);
			$oSlack->setUserName($oConfig->crm_slack_username);
			//$oSlack->setIconEmoji(':loudspeaker:');
			$oSlack->setIconUrl('https://a.slack-edge.com/41b0a/img/plugins/app/service_36.png');
			$sConents .= 'new '.$sDocType.' registered on '.date('Y-m-d H:i:s').PHP_EOL;
			$oSlack->setMessage($sConents);
			//$slack->setLink('https://yc5.codepub.net', '클릭>');
			$oResult = $oSlack->send();
			unset( $sConents );
			unset( $oSlack );
		}
		unset( $oConfig );
		unset( $oSvcrmAdminModel );
		return;
	}
/**
 * @brief 게시판 글/댓글 등록 전 관리자 닉네임 사칭 등 검사
 **/
	public function validateContentsBefore(&$obj) 
	{
		$oLoggedInfo = Context::get('logged_info');
		if( $oLoggedInfo->is_admin != 'Y' )
		{
			$sCommenterNickName = trim($obj->nick_name);
			$oRst = executeQueryArray('svcrm.getMemberAdminNickNameList');
			foreach($oRst->data as $nIdx => $oRec)
			{
				if( $sCommenterNickName == $oRec->nick_name )
					return new BaseObject(-1, 'msg_error_forbidden_nickname');
			}
		}
	}
/**
 * @brief 게시판 댓글 등록 후 트리거
 **/
	public function triggerInsertComment(&$obj)
	{
		$oMemberModel = &getModel('member');
		if( $obj->member_srl )
		{
			$oWriterInfo = $oMemberModel->getMemberInfoByMemberSrl((int)$obj->member_srl);
			if( $oWriterInfo->is_admin == 'Y' )
				return;
			
			foreach( $oWriterInfo->group_list as $key=>$val)
			{
				if( $config->except_member_group[$key] == 'Y' )
					return;
			}
		}
		$oSvcrmAdminModel = &getAdminModel('svcrm');
		$oConfig = $oSvcrmAdminModel->getModuleConfig();
		if( $oWriterInfo->is_admin != 'Y' && $oConfig->visitor_comment_log_activation == 'on')
		{
			require_once(_XE_PATH_.'modules/svcrm/svcrm.log_trigger.php');
			$oEnageLog = new svcrmEngageLogTrigger($obj, $oConfig); //->visitor_comment_log_doc_srls);
			unset($oEnageLog);		
		}
		$nModuleSrl = (int)$obj->module_srl;
		if($oConfig->crm_slack_web_hook_url && $oConfig->crm_slack_ch && $oConfig->crm_slack_username)
		{
			foreach( $oConfig->monitor_board as $key=>$val)
			{
				if( $key == $nModuleSrl.'_com' )
				{
					$this->_sendSlackMessageDocument($obj, $oConfig, 'comment');
					continue;
				}
			}
		}
	}
/**
 * @brief Slack Messenger에 메세지 전송
 **/
	private function _sendSlackMessageDocument($obj, $oConfig, $sDocType)
	{
		$sConents = $_SERVER['SERVER_NAME'].'/'.$obj->mid.'/'.$obj->document_srl.PHP_EOL;
		$sConents .= 'new '.$sDocType.' registered on '.date('Y-m-d H:i:s').PHP_EOL;
		
		require_once(_XE_PATH_.'modules/svcrm/slack.class.php');
		$oSlack = new SlackIncomingWebHook();
		$oSlack->setWebHookUrl($oConfig->crm_slack_web_hook_url);
		$oSlack->setChannel('#'.$oConfig->crm_slack_ch);
		$oSlack->setUserName($oConfig->crm_slack_username);
		//$oSlack->setIconEmoji(':loudspeaker:');
		$oSlack->setIconUrl('https://a.slack-edge.com/41b0a/img/plugins/app/service_36.png');
		$sConents .= '----------- msg begins ---------------'.PHP_EOL;
		$sConents .= strip_tags($obj->content);
		$sConents .= PHP_EOL.'----------- msg ends ---------------'.PHP_EOL;
		$oSlack->setMessage($sConents);
		//$slack->setLink('https://yc5.codepub.net', '클릭>');
		$oResult = $oSlack->send();
		unset( $sConents );
		unset( $oSlack );
		return;
	}
/**
 * @brief 회원 DB 삭제 전 트리거 
 **/
	public function triggerDeleteMemberBefore(&$obj) 
	{
	}
/**
 * @brief 회원 DB 추가후 관련 프로모션 조건을 충족하면 실행
 **/
	public function triggerInsertMemberAfter(&$obj) 
	{
		$oSvcrmAdminModel = &getAdminModel('svcrm');
		$config = $oSvcrmAdminModel->getModuleConfig();
		$nPromotionSrl = (int)$config->registration_coupon_promotion_srl;
		if( $nPromotionSrl )
		{
			$oSvpromotionAdminModel = &getAdminModel('svpromotion');
			$oCouponPromotionInfo = $oSvpromotionAdminModel->getCouponPromotionSetupInfo( $nPromotionSrl );
			$sDiscType = $oCouponPromotionInfo->data[0]->descount_type;
			switch( $sDiscType )
			{
				case 'amount':
					$sDiscountInfo = $oCouponPromotionInfo->data[0]->descount_amount_policy.'원';
					break;
				case 'rate':
					$sDiscountInfo = $oCouponPromotionInfo->data[0]->descount_rate_policy.'%';
					break;
				default:
					$sDiscountInfo = '';
					break;
			}
			$bSatisfied = true;
			$oMemberRegistrationExtraVars = unserialize( $obj->extra_vars );
			$oMemberModel = &getModel('member');
			$oMemberJoinFormList = $oMemberModel->getUsedJoinFormList();

			foreach( $oMemberJoinFormList as $key => $val )
			{
				if( $config->{$val->column_name} && $config->{$val->column_name} != 'ignore_this_value' && 
					$oMemberRegistrationExtraVars->{$val->column_name} != $config->{$val->column_name} )
					$bSatisfied = false;
			}
			if( $bSatisfied  )
			{
				if( $config->welcome_coupon_yn == 'on' )
				{
					$sCouponSerial = $oSvpromotionAdminModel->getCouponSerial(6);
					$oSvpromotionAdminController = getAdminController('svpromotion');
					$oSvpromotionAdminController->insertCoupon($nPromotionSrl, $sCouponSerial, $obj->member_srl);
					$sContents = str_replace("%i%", $obj->user_id, $config->sms_welcome_msg_with_coupon);
					$sContents = str_replace("%un%", $obj->user_name, $config->sms_welcome_msg_with_coupon);
					$sContents = str_replace("%nn%", $obj->nick_name, $config->sms_welcome_msg_with_coupon);
					$sContents = str_replace("%p%", $sDiscountInfo, $sContents);
					$sContents = str_replace("%c%", $sCouponSerial, $sContents);
				}
			}
			else
			{
				$sContents = str_replace("%i%", $obj->user_id, $config->sms_welcome_msg_default);
				$sContents = str_replace("%un%", $obj->user_name, $config->sms_welcome_msg_default);
				$sContents = str_replace("%nn%", $obj->nick_name, $config->sms_welcome_msg_default);
			}
		}
		else
		{
			$sContents = str_replace("%i%", $obj->user_id, $config->sms_welcome_msg_default);
			$sContents = str_replace("%un%", $obj->user_name, $config->sms_welcome_msg_default);
			$sContents = str_replace("%nn%", $obj->nick_name, $config->sms_welcome_msg_default);
		}

		if( $config->sms_welcome_msg_activate == 'off' || is_null( $config->sms_welcome_msg_activate ) || is_null( getClass('textmessage') ))
			return new BaseObject();
		
		if( strlen( $config->sender_no ) >= 8 )
		{
			$nMmemberSrl = $obj->member_srl;
			$oSvauthModel = &getModel('svauth');
			$oMemberAuthInfo = $oSvauthModel->getMemberAuthInfo($nMmemberSrl);
			$args->type = 'LMS';
			$args->country_code = 82;
			$args->recipient_no =  $oMemberAuthInfo->mobile;
			$args->sender_no = $config->sender_no;
			$args->content = $sContents;
			$controller = &getController('textmessage');
			$output = $controller->sendMessage($args);
			if(!$output->toBool()) 
				return $output;
		}
		if($output->get('error_code'))
			return new BaseObject(-1, 'error');

		$this->setMessage('회원가입 환영 SMS를 발송하였습니다.');
		return new BaseObject();
	}
/**
 * @brief 주문자에게 주문상태 변경 SMS 통지
 **/
	public function notifyOrderStatusUpdate($oArgs) 
	{
		$oSvcrmAdminModel = &getAdminModel('svcrm');
		$config = $oSvcrmAdminModel->getModuleConfig();
		if( is_null( $config->sender_no ) )
			return;

		if( is_null( $oArgs->order_status ) )
			return;
		$sRecipientNo = preg_replace("/[^0-9]*/s", '', $oArgs->purchaser_cellphone );
		$nLen = strlen( $sRecipientNo );
		if( $nLen == 0 || $nLen < 10 || $nLen > 11 )
			return;

		if( is_null( $oArgs->purchaser_name ) || strlen( $oArgs->purchaser_name ) == 0 )
			$oArgs->purchaser_name = '주문자';
		$oSmsController = &getController('textmessage');
		if( !defined(svorder::ORDER_STATE_ON_DEPOSIT) ) // to load svorder global defined
			getClass('svorder');
		//$oSvorderModel = &getClass('svorder'); 
		if($oSmsController)
		{
			$sContents = 'not_selected';
			switch( $oArgs->order_status )
			{
				case svorder::ORDER_STATE_ON_DEPOSIT://1: //입금대기
					if( $config->sms_order_status_wait_for_deposit_yn == 'on' )
						$sContents = 'sms_order_status_wait_for_deposit';
					break;
				case svorder::ORDER_STATE_PAID://2: //입금완료
					if( $config->sms_order_status_deposit_confirmed_yn == 'on' )
						$sContents = 'sms_order_status_deposit_confirmed';
					break;
				case svorder::ORDER_STATE_PREPARE_DELIVERY://3: //배송준비
					if( $config->sms_order_status_delivery_preparation_yn == 'on' )
						$sContents = 'sms_order_status_delivery_preparation';
					break;
				case svorder::ORDER_STATE_ON_DELIVERY://4: //배송중
					if( $config->sms_order_status_on_delivery_yn == 'on' )
						$sContents = 'sms_order_status_on_delivery';
					break;
				case svorder::ORDER_STATE_CANCEL_REQUESTED://E: //취소 요청
					if( $config->sms_order_status_cancel_request_yn == 'on' )
						$sContents = 'sms_order_status_cancel_request';
					break;
				case svorder::ORDER_STATE_CANCELLED://A: //취소 완료
					if( $config->sms_order_status_cancel_completed_yn == 'on' )
						$sContents = 'sms_order_status_cancel_completed';
					break;
				default:
					break;
			}
			if( $sContents != 'not_selected' && !is_null($config->{$sContents}) )
			{
				$oArgs->country_code = 82;//$country_code;
				$oArgs->recipient_no = $sRecipientNo;
				$oArgs->sender_no = $config->sender_no;
				$sContents = str_replace("%n%", $oArgs->purchaser_name, $config->{$sContents});
				$sContents = str_replace("%oid%", $oArgs->order_srl, $sContents);
				$oArgs->content = $sContents;
				$output = $oSmsController->sendMessage($oArgs);
			}
		}
	}
/**
 * @brief 관리자에게 SMS 통지
 **/
	public function sendSmsToAdmin($oArgs) 
	{
		if( is_null( getClass('textmessage') ) )
			return;
		$oSmsController = &getController('textmessage');
		if($oSmsController)
		{
		}
	}
/**
 * @brief 개별 고객에게 SMS 통지
 * 90바이트까지 SMS, 그 이상은 LMS
 **/
	public function sendSms($oInArgs) 
	{
		if( is_null( $oInArgs->recipient_no ) || !$oInArgs->recipient_no || 
			is_null( $oInArgs->content ) || !$oInArgs->content )
			return new BaseObject(-1, 'msg_error_no_recipient_info');

		if( is_null( getClass('textmessage') ) )
			return new BaseObject(-1, 'msg_error_no_sms_module');
		$oSmsController = &getController('textmessage');
		$oArgs = new stdClass();
		if($oSmsController)
		{
			$oSvcrmAdminModel = &getAdminModel('svcrm');
			$config = $oSvcrmAdminModel->getModuleConfig();
			if( is_null( $config->sender_no ) )
				return new BaseObject(-1, 'msg_error_blank_sender_CID');
			
			$nBytes = (int)mb_strlen($oInArgs->content, 'UTF-8');
			if( $nBytes <= 48 )
				$oArgs->type = 'SMS';
			else
				$oArgs->type = 'LMS';

			$oArgs->country_code = 82;
			$oArgs->sender_no = $config->sender_no;
			$oArgs->recipient_no = $oInArgs->recipient_no;
			$oArgs->content = $oInArgs->content;
			$output = $oSmsController->sendMessage($oArgs);
			if(!$output->toBool()) 
				return $output;

			unset( $oArgs->country_code );
			unset( $oArgs->sender_no );
			$oArgs->sender_member_srl = $oInArgs->sender_member_srl;
			$oArgs->recepient_member_srl = $oInArgs->recepient_member_srl;
			$oArgs->recipient_no = $oInArgs->recipient_no;
			$oArgs->medium_type = 0; //SMS
			$output = executeQuery('svcrm.insertCommLog', $oArgs);
			if(!$output->toBool()) 
				return $output;
			return new BaseObject();
		}
		return new BaseObject(-1, 'msg_error_unknown');
	}
}
?>