<?php

/**
 * @class  svcrmEngageLogTrigger
 * @author singleview(root@singleview.co.kr)
 * @brief  svcrmEngageLogTrigger class
 */
class svcrmEngageLogTrigger
{
	private $_g_oRst = NULL;
/**
 * @brief 생성자 
 * 조건을 만족하면 방문자의 새글 댓글 작성 로그 등록
 * 빠른 작동과 완결을 위해서 log trigger는 최소 기능으로 구현
 */
	public function __construct($oParam, $oConfig) //$sVisitorEngageLogDocSrls=null)
	{
		$sIgnoreMemberSrls = $oConfig->ignore_member_srls;
		if(strlen($sIgnoreMemberSrls) > 0)  // if exceptional member requested
		{
			$aIgnoreMemberSrls = explode(',', $sIgnoreMemberSrls);
			if(in_array((int)$oParam->member_srl, $aIgnoreMemberSrls))
				return;
		}

		if($oParam->comment_srl) // if new comment requested
		{
			$sVisitorEngageLogDocSrls = $oConfig->visitor_comment_log_doc_srls;
			if(strlen($sVisitorEngageLogDocSrls) > 0)
			{
				$aSessionMonitorDocSrls = explode(',', $sVisitorEngageLogDocSrls);
				if(!in_array((int)$oParam->document_srl, $aSessionMonitorDocSrls))
					return;
			}

		}
		
		// refer to ./session/session.model.php::sessionModel::read()
		$oArgs = new stdClass();
		$oArgs->session_key = session_id();
		$aColumnList = array('session_key', 'cur_mid', 'val', 'last_update');
		$oSessionRst = executeQuery('session.getSession', $oArgs, $aColumnList);
		unset($oArgs);
		if($oSessionRst->data)
		{
			$nLastTimestamp = strtotime($oSessionRst->data->last_update); // last session update timestamp
			$nCurTimestamp = (int)microtime(TRUE);
			$oArgs->sSssionKey = $oSessionRst->data->session_key;
			$oArgs->nMemberSrl = (int)$oParam->member_srl;
			$oArgs->nDocSrl = (int)$oParam->document_srl;
			$oArgs->nCommentSrl = (int)$oParam->comment_srl;
			$oArgs->nElapsedSecLastIntreact = $nCurTimestamp - $nLastTimestamp; // duration sec
			$oArgs->sVal = $oSessionRst->data->val;
			$oArgs->sCurMid = $oSessionRst->data->cur_mid;
			$oArgs->sUa = $_COOKIE['mobile'] == 'false' ? 'P' : 'M';
			$oInesrtRst = executeQuery('svcrm.insertEngageLog', $oArgs);
			unset($oArgs);
			unset($oInesrtRst);
		}
		unset($oSessionRst);
	}
}

/**
 * @class  svcrmOrderCsLogTrigger
 * @author singleview(root@singleview.co.kr)
 * @brief  svcrmOrderCsLogTrigger class
 * svorder의 predefine constant가 필요해서 svorder 상속
 */
class svcrmOrderCsLogTrigger extends svorder
{
	private $_g_oRst = NULL;
/**
 * @brief 생성자 
 * 조건을 만족하면 CS 로그 등록
 * 빠른 작동과 완결을 위해서 log trigger는 최소 기능으로 구현
 * ./ext_class/npay/npay_order.class.php::_registerCsLog()와 통일성 유지
 * usage:
 * $oCsArg->nOrderSrl = $nOrderSrl;
 * $oCsArg->sOriginStatus = svorder::ORDER_STATE_ON_DEPOSIT;
 * $oCsArg->sTgtStatus = svorder::ORDER_STATE_RETURNED;
 * $oCsArg->nCartSrl = 3;
 * $oCsArg->nbuyerMemberSrl = 3;
 * $oCsArg->sClaimStatus = svorder::ORDER_STATE_ON_DEPOSIT;
 * $o = new svorderCsLogTrigger($oCsArg);
 */
	public function __construct($oInArgs)
	{
		if( !$oInArgs->nOrderSrl || is_null( $oInArgs->sOriginStatus ) || !$oInArgs->sTgtStatus )
		{
			$this->_g_oRst = new BaseObject( -1, 'msg_invalid_param');
			return;
		}

		if( is_null( $this->_g_aOrderStatus[$oInArgs->sOriginStatus] ) )
		{
			$this->_g_oRst = new BaseObject( -1, 'invalid order status origin:'.$oInArgs->sOriginStatus);
			return;
		}
		if( is_null( $this->_g_aOrderStatus[$oInArgs->sTgtStatus] ) )
		{
			$this->_g_oRst = new BaseObject( -1, 'invalid order status target:'.$oInArgs->sTgtStatus);
			return;
		}
        $oArgs = new stdClass();
		// add simple cs memo
		if( $oInArgs->sOriginStatus == $oInArgs->sTgtStatus )
			$oArgs->memo = strip_tags(trim($oInArgs->sQuickCsMemo)); 
		else // update order status
		{
			if( $oInArgs->oCsParam )
			{
				$oTmpCsRst = $this->_parseTranslateParam($oInArgs->sTgtStatus, $oInArgs->oCsParam);
				if (!$oTmpCsRst->toBool())
				{
					$this->_g_oRst = $oTmpCsRst;
					return;
				}
				$oTmpCsParam = $oTmpCsRst->get( 'oParam' );
				if( $oTmpCsParam->sRelatedClaimCode )
					$oArgs->related_claim_code = $oTmpCsParam->sRelatedClaimCode;
				if( $oTmpCsParam->nAmnt )
					$oArgs->amount = $oTmpCsParam->nAmnt;
				if( $oTmpCsParam->sAmntType ) // +는 판매자의 채권 -는 판매자의 채무
					$oArgs->amount_type = $oTmpCsParam->sAmntType;
				if( $oTmpCsParam->sCsDueDate )
					$oArgs->duedate = $oTmpCsParam->sCsDueDate;
				if( $oTmpCsParam->sMemo )
					$oArgs->memo = $oTmpCsParam->sMemo;
			}
			else
				$oArgs->memo = $oInArgs->sQuickCsMemo;
		}
		
		if( $oInArgs->nSvCartSrl )
			$oArgs->cart_srl = $oInArgs->nSvCartSrl;

		if( $oInArgs->nItemSrl)
			$oArgs->item_srl = $oInArgs->nItemSrl;
		
		if( $oInArgs->bAllowed)
			$oArgs->is_allowed = $oInArgs->bAllowed;
		else
			$oArgs->is_allowed = 0;

		$oLoggedInfo = Context::get('logged_info');
		if( $oLoggedInfo )
			$oArgs->admin_member_srl = $oLoggedInfo->member_srl;
		else
			$oArgs->admin_member_srl = 0;
		
		$oArgs->order_status_source = $oInArgs->sOriginStatus;
		$oArgs->order_status_dest = $oInArgs->sTgtStatus;
		$oArgs->order_srl = $oInArgs->nOrderSrl;
		$oArgs->buyer_member_srl = $oInArgs->nbuyerMemberSrl;

		// https://stackoverflow.com/questions/2110732/how-to-get-name-of-calling-function-method-in-php
		$aDbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,3);
		array_shift( $aDbt ); // $aDbt[0] must be svcrmOrderCsLogTrigger
		array_shift( $aDbt ); // $aDbt[1] must be svorder.order::_registerCsLog()
		$oArgs->caller_info = $aDbt[0]['class'].'::'.$aDbt[0]['function'].'@'.$aDbt[0]['line'];
		$this->_g_oRst = executeQuery('svcrm.insertCsLog', $oArgs);
		return;
	}
/**
 * @brief imitate Object Class
 */
	public function getRst()
	{
		return $this->_g_oRst;
	}
/**
 * @brief imitate Object Class
 */
	public function toBool()
	{
		return $this->_g_oRst->toBool();
	}
/**
 * @brief imitate Object Class
 */
	function getMessage()
	{
		return $this->_g_oRst->getMessage();
	}
/**
 * @brief imitate Object Class
 */
	function get($key)
	{
		return $this->_g_oRst->get($key);
	}
/**
 * @brief 
 * claim_status == sTgtStatus
 */
	private function _parseTranslateParam($sTgtStatus, $oCsParam)
	{
		//$oTmp->sRelatedClaimCode = null;
		//$oTmp->nAmnt = -1; // 고객 응대 과정에서 발생한 비용
		//$oTmp->sAmntType = -1; // +는 판매자의 채권 -는 판매자의 채무
		//$oTmp->sCsDueDate = -1; // 약속한 처리 완료일
		//$oTmp->sMemo = -1; // CS 비정형 메모
		$oTmp = new stdClass();
        switch( $sTgtStatus )
		{
			case svorder::ORDER_STATE_ON_DEPOSIT: // PG 완료 후 입금대기
				$oTmp->sMemo = $oCsParam->sSystemMemo;
			case svorder::ORDER_STATE_PAID: // PG 입금완료
				$oTmp->sMemo = $oCsParam->sDetailReason;
				break;
			case svorder::ORDER_STATE_DELIVERY_DELAYED:
				$sDispatchDelayReasonCode = array_search($oCsParam->sDispatchDelayReasonCode, $this->g_aNpayDelayDeliveryReason);
				if( !$sDispatchDelayReasonCode )
					return new BaseObject( -1, 'invalid_dispatch_delay_reason_code' );
				
				$oTmp->sRelatedClaimCode = $oCsParam->sDispatchDelayReasonCode;
				$oTmp->sMemo = $oCsParam->sDetailReason;
				$oTmp->sCsDueDate = $oCsParam->sDispatchDueDate.'235959'; // YYYYMMDD235959
				break;
			case svorder::ORDER_STATE_DELIVERED: // 반품 요청 후 반품 요청 취소 시
				if( strlen($oCsParam->sDetailReason) ) 
					$oTmp->sMemo = $oCsParam->sDetailReason;
				break;
			case svorder::ORDER_STATE_PREPARE_DELIVERY:
			case svorder::ORDER_STATE_ON_DELIVERY: // 송장 등록 실패시 로그; ORDER_STATE_REDELIVERY_EXCHANGE는 svorder.order.php::_updateCartItemStatusBySvCartSrl()에서 svorder::ORDER_STATE_ON_DELIVERY로 전환됨
			case svorder::ORDER_STATE_RETURN_REJECTED:
			case svorder::ORDER_STATE_EXCHANGE_REJECTED:
				$oTmp->sMemo = $oCsParam->sDetailReason;
				break;
			case svorder::ORDER_STATE_WITHHOLD_EXCHANGE: // 교환 보류 요청
				$sExchangeWithholdReasonCode = array_search($oCsParam->sExchangeWithholdReasonCode, $this->g_aNpayExchangeWithholdReasonCode);
				if( !$sExchangeWithholdReasonCode )
					return new BaseObject( -1, 'invalid_exchange_withhold_reason_code' );

				$oTmp->sRelatedClaimCode = $oCsParam->sExchangeWithholdReasonCode;
				$oTmp->nAmnt = $oCsParam->nExchangeWithholdFee;
				$oTmp->sAmntType = '+';
				$oTmp->sMemo = $oCsParam->sDetailReason;
				break;
			case svorder::ORDER_STATE_COLLECTED_RETURN_APPROVED: // 반품실물 수령확인
				$oTmp->nAmnt = $oCsParam->sReturnFee;
				$oTmp->sAmntType = '+';
				$oTmp->sMemo = $oCsParam->sDetailReason;
				break;
			case svorder::ORDER_STATE_EXCHANGE_REQUESTED:
			case svorder::ORDER_STATE_EXCHANGED:
				$sExchangeReasonCode = array_search($oCsParam->sExchangeReasonCode, $this->g_aNpayCancelReturnReason);
				if( !$sExchangeReasonCode )
					return new BaseObject( -1, 'invalid_exchange_reason_code' );
				$oTmp->sRelatedClaimCode = $oCsParam->sExchangeReasonCode;
				$oTmp->sMemo = $oCsParam->sDetailReason;
				break;
			case svorder::ORDER_STATE_RETURNED:
				$sReturnReasonCode = array_search($oCsParam->sReturnReasonCode, $this->g_aNpayCancelReturnReason); // npay 취소사유는 반환사유 코드인 경우도 있음
				if( !$sReturnReasonCode )
					return new BaseObject( -1, 'invalid_return_reason_code' );
				$oTmp->sRelatedClaimCode = $oCsParam->sReturnReasonCode;
				$oTmp->nAmnt = $oCsParam->nEtcFeeDemandAmount;
				$oTmp->sAmntType = '+';
				$oTmp->sMemo = $oCsParam->sDetailReason;
				break;
			case svorder::ORDER_STATE_RETURN_REQUESTED:
				$sReturnReasonCode = array_search($oCsParam->sReturnReasonCode, $this->g_aNpayCancelReturnReason);
				if( !$sReturnReasonCode )
					return new BaseObject( -1, 'invalid_return_reason_code' );
				$sCollectDeliveryMethodCode = array_search($oCsParam->sDeliveryMethodCode, $this->g_aNpayCollectDeliveryMethodCode);
				$aNpayReturnMethod = Context::getLang('arr_collect_delivery_method_code');
				$sReturnMethod = $aNpayReturnMethod[$sCollectDeliveryMethodCode];
				unset( $aNpayReturnMethod );

				$sDeliveryCompanyTitle = $this->delivery_companies[$oCsParam->sCartExpressId];
				$oTmp->sRelatedClaimCode = $oCsParam->sReturnReasonCode;
				$oTmp->sMemo = '"'.$oCsParam->sDetailReason.'"의 이유로 "'.$sReturnMethod.'"의 방법으로 '.$sDeliveryCompanyTitle.'를 이용하여 '.$oCsParam->sCartInvoiceNo.' 송장을 발송함';
				break;
			case svorder::ORDER_STATE_CANCEL_REQUESTED: // svorder 관리자 UI에서 품목별 결제 취소 요청
				$sCancelReasonCode = array_search($oCsParam->sCancelReqReasonCode, $this->g_aNpayCancelReturnReason);
				if( !$sCancelReasonCode )
					return new BaseObject( -1, 'invalid_cancel_reason_code' );
				$oTmp->sRelatedClaimCode = $oCsParam->sCancelReqReasonCode;
				if( $oCsParam->nEtcFeeDemandAmount > 0 )
				{
					$oTmp->nAmnt = $oCsParam->nEtcFeeDemandAmount;
					$oTmp->sAmntType = '+';
				}
				$oTmp->sMemo = $oCsParam->sDetailReason;
				break;
			case svorder::ORDER_STATE_CANCELLED: // svorder 관리자 UI에서 품목별 결제 취소 확정
				$sCancelReasonCode = array_search($oCsParam->sCancelReasonCode, $this->g_aNpayCancelReturnReason); // npay 취소사유는 반환사유 코드인 경우도 있음
				if( !$sCancelReasonCode )
					return new BaseObject( -1, 'invalid_cancel_reason_code' );
				$oTmp->sRelatedClaimCode = $oCsParam->sCancelReasonCode;
				$oTmp->sMemo = $oCsParam->sDetailReason;
				break;
			case svorder::ORDER_STATE_CANCEL_APPROVED: // npay api에서 수집된 품목별 결제 취소 요청 승인
				$oTmp->nAmnt = $oCsParam->nEtcFeeDemandAmount;
				$oTmp->sAmntType = '+';
				$oTmp->sMemo = $oCsParam->sDetailReason;
				break;
			default:
				break;
		}
		if( $oTmp->sMemo )
			$oTmp->sMemo = trim(strip_tags($oTmp->sMemo));

		$oFinalRst = new BaseObject();
		$oFinalRst->add( 'oParam', $oTmp );
		return $oFinalRst;
	}
}
/* End of file svcrm.log_trigger.php */
/* Location: ./modules/svcrm/svcrm.log_trigger.php */