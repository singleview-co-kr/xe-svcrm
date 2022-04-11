<?php
/**
 * @class  svcrmAdminModel
 * @author singleview(root@singleview.co.kr)
 * @brief  svcrm admin model 
 **/
class svcrmAdminModel extends svcrm
{
/**
 * @brief 
 **/
	public function init()
	{
	}
/**
 * @brief get module instance list
 **/
	public function getModInstList( $nPage = null ) 
	{
		$oArgs = new stdClass();
		$oArgs->sort_index = 'module_srl';
		$oArgs->page = $nPage;
		$oArgs->list_count = 20;
		$oArgs->page_count = 10;
		$oRst = executeQueryArray('svcrm.getModInstList', $oArgs);
		return $oRst->data;
	}
/**
 * @brief 
 **/
	public function getSvcrmAdminDeleteModInst() 
	{
		$oModuleModel = &getModel('module');
		$module_srl = Context::get('module_srl');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
		Context::set('module_info', $module_info);

		$oTemplate = &TemplateHandler::getInstance();
		$tpl = $oTemplate->compile($this->module_path.'tpl', 'form_delete_modinst');
		$this->add('tpl', str_replace("\n"," ",$tpl));
	}
/**
 * @brief 
 **/
	public function getModuleConfig()
	{
		$oModuleModel = &getModel('module');
		$config = $oModuleModel->getModuleConfig('svcrm');
		return $config;
	}
/**
 * @brief Read the privacy access level configure file 
 **/
	public function checkPrivacyAccessConfigurePermission()
	{
		$sFlagFile = FileHandler::getRealpath('./files/privacy_acceess_level.txt');
		if(file_exists($sFlagFile))
			return true;
		else
			return false;
	}
/**
 * @brief 주문별 CS 로그 가져오기
 * svorder.order.php에서 호출하기 때문에 svorder predefined constant가 로드된 상태
 **/
	public function getCsLogByOrderSrl($nSvOrderSrl)
	{
        $oArgs = new stdClass();
		$oArgs->order_srl = $nSvOrderSrl;
		$oCsRst = executeQueryArray('svcrm.getCsLogBySvOrderSrl', $oArgs);
		if(!$oCsRst->toBool())
			return $oCsRst;
		
		$oSvorderClass = &getClass('svorder');
		$oMemberModel = &getModel('member');
		$aRerievedMemberInfo = array();
		foreach( $oCsRst->data as $nIdx => $oVal )
		{
			if( $aMemberInfo[$oVal->admin_member_srl] )
				$oVal->admin_user_id = $aRerievedMemberInfo[$oVal->admin_member_srl];
			else
			{
				$oMemberInfo = $oMemberModel->getMemberInfoByMemberSrl($oVal->admin_member_srl);
				$oVal->nick_name = $oMemberInfo->nick_name;
				$aRerievedMemberInfo[$oVal->admin_member_srl] = $oMemberInfo->user_id;
			}
			if( $oVal->cart_srl == 0 ) // 주문 상세 관리자 화면에서 주문수준 CS에 0이 표시되지 않게 함
				$oVal->cart_srl = '';
			
			$oVal->is_allowed = $oVal->is_allowed == 1 ? '승인' : '<span class="ko_text red">거부</span>';

			switch( $oVal->order_status_dest )
			{
				case svorder::ORDER_STATE_DELIVERY_DELAYED:
					$sDispatchDelayReasonCode = array_search($oVal->related_claim_code, $oSvorderClass->g_aNpayDelayDeliveryReason);
					if( $sDispatchDelayReasonCode )
					{
						$aNpayDelayDeliveryReason = Context::getLang('arr_delivery_delay_reason_code');
						$oVal->sClaimReason = $aNpayDelayDeliveryReason[$sDispatchDelayReasonCode];
					}
					else
						$oVal->sClaimReason = 'N/A';
					break;
				case svorder::ORDER_STATE_WITHHOLD_EXCHANGE: // 교환 보류 요청
					$sExchangeWithholdReasonCode = array_search($oVal->related_claim_code, $oSvorderClass->g_aNpayExchangeWithholdReasonCode);
					if( $sExchangeWithholdReasonCode )
					{
						$aNpayExchangeWithholdReason = Context::getLang('arr_exchange_withhold_reason_code');
						$oVal->sClaimReason = $aNpayExchangeWithholdReason[$sExchangeWithholdReasonCode];
					}
					else
						$oVal->sClaimReason = 'N/A';

					break;
				case svorder::ORDER_STATE_EXCHANGE_REQUESTED:
				case svorder::ORDER_STATE_RETURNED:
				case svorder::ORDER_STATE_RETURN_REQUESTED:
					$sReturnReqReasonCode = array_search($oVal->related_claim_code, $oSvorderClass->g_aNpayCancelReturnReason);
					if( $sReturnReqReasonCode )
					{
						$aNpayReturnReqReason = Context::getLang('arr_npay_claim_cancel_return_reason');
						$oVal->sClaimReason = $aNpayReturnReqReason[$sReturnReqReasonCode];
					}
					else
						$oVal->sClaimReason = 'N/A';
					break;
				case svorder::ORDER_STATE_CANCEL_REQUESTED:
				case svorder::ORDER_STATE_CANCELLED: // svorder 관리자 UI에서 품목별 결제 취소 전송
					$sCancelReasonCode = array_search($oVal->related_claim_code, $oSvorderClass->g_aNpayCancelReturnReason);
					if( $sCancelReasonCode )
					{
						$aNpayCancelReason = Context::getLang('arr_npay_claim_cancel_return_reason');
						$oVal->sClaimReason = $aNpayCancelReason[$sCancelReasonCode];
					}
					else
						$oVal->sClaimReason = 'N/A';
					break;
				default:
					break;
			}
		}
		return $oCsRst;
	}
/**
 * @brief get communiation log by member_srl
 **/
	public function getCommunicationLog($nRecipientMemberSrl)
	{
		$oArgs = new stdClass();
		$oArgs->recepient_member_srl = $nRecipientMemberSrl;
		$output = executeQuery('svcrm.getCommLogByMemberSrl', $oArgs);

		$aMediumType = array(0=>'SMS');
		if( count( $output->data ) )
		{
			$oMemberModel = &getModel('member');
			//$columnList = array('member_srl', 'user_id', 'email_address', 'user_name', 'nick_name', 'regdate', 'last_login', 'extra_vars');
			$aMemberInfo = array();
			foreach( $output->data as $key=>$val )
			{
				if( !$aMemberInfo[$val->sender_member_srl] )
				{
					$oMemberInfo = $oMemberModel->getMemberInfoByMemberSrl($val->sender_member_srl);
					$aMemberInfo[$val->sender_member_srl] = $oMemberInfo;
				}
				else
					$oMemberInfo = $aMemberInfo[$val->sender_member_srl];
				
				$output->data[$key]->sender_id = $oMemberInfo->user_id;
				$val->medium_type = $aMediumType[$val->medium_type];// 'SMS';
			}
		}
		return $output;
	}
}
/* End of file svcrm.admin.model.php */
/* Location: ./modules/svcrm/svcrm.admin.model.php */