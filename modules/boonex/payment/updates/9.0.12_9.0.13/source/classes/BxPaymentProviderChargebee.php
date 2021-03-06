<?php defined('BX_DOL') or die('hack attempt');
/**
 * Copyright (c) UNA, Inc - https://una.io
 * MIT License - https://opensource.org/licenses/MIT
 *
 * @defgroup    Payment Payment
 * @ingroup     UnaModules
 *
 * @{
 */

require_once(BX_DIRECTORY_PATH_PLUGINS . 'chargebee/ChargeBee.php');

define('CBEE_MODE_LIVE', 1);
define('CBEE_MODE_TEST', 2);

class BxPaymentProviderChargebee extends BxBaseModPaymentProvider implements iBxBaseModPaymentProvider
{
	protected $_iMode;
	protected $_bCheckAmount;

    function __construct($aConfig)
    {
    	$this->MODULE = 'bx_payment';

        parent::__construct($aConfig);        
    }

    public function setOptions($aOptions)
    {
    	parent::setOptions($aOptions);

    	$this->_iMode = (int)$this->getOption('mode');
        $this->_bCheckAmount = $this->getOption('check_amount') == 'on';
        $this->_bUseSsl = $this->getOption('ssl') == 'on';
    }

    public function initializeCheckout($iPendingId, $aCartInfo)
    {
    	$aItem = array_shift($aCartInfo['items']);
    	if(empty($aItem) || !is_array($aItem))
    		return $this->_sLangsPrefix . 'err_empty_items';

		$aClient = $this->_oModule->getProfileInfo();
		$aVendor = $this->_oModule->getProfileInfo($aCartInfo['vendor_id']);

		$oPage = $this->createHostedPage($aItem, $aClient, $aVendor, $iPendingId);
		if($oPage === false)
			return $this->_sLangsPrefix . 'err_cannot_perform';

		return array(
			'code' => 0,
			'eval' => $this->_oModule->_oConfig->getJsObject('cart') . '.onSubscribeSubmit(oData);',
			'redirect' => $oPage->url
		);
    }

    public function finalizeCheckout(&$aData)
    {
    	$sPageId = bx_process_input($aData['id']);
		$iPendingId = bx_process_input($aData['pending_id'], BX_DATA_INT);

        if(empty($sPageId) || empty($iPendingId))
        	return array('code' => 1, 'message' => $this->_sLangsPrefix . 'err_wrong_data');

		$oPage = $this->retreiveHostedPage($sPageId);
		if($oPage === false)
			return $this->_sLangsPrefix . 'err_cannot_perform';
		
		$aPending = $this->_oModule->_oDb->getOrderPending(array('type' => 'id', 'id' => $iPendingId));
        if(!empty($aPending['order']) || !empty($aPending['error_code']) || !empty($aPending['error_msg']) || (int)$aPending['processed'] != 0)
            return array('code' => 3, 'message' => $this->_sLangsPrefix . 'err_already_processed');

		$oCustomer = $oPage->content()->customer();
		$oSubscription = $oPage->content()->subscription();

		$aResult = array(
			'code' => BX_PAYMENT_RESULT_SUCCESS,
        	'message' => $this->_sLangsPrefix . 'cbee_msg_subscribed',
			'pending_id' => $iPendingId,
			'customer_id' => $oCustomer->id,
		    'subscription_id' => $oSubscription->id,
			'client_name' => _t($this->_sLangsPrefix . 'txt_buyer_name_mask', $oCustomer->firstName, $oCustomer->lastName),
			'client_email' => $oCustomer->email,
			'paid' => false,
			'trial' => false,
		);

        //--- Update pending transaction ---//
        $this->_oModule->_oDb->updateOrderPending($iPendingId, array(
            'order' => $oSubscription->id,
            'error_code' => $aResult['code'],
            'error_msg' => _t($aResult['message'])
        ));

        return $aResult;
    }

    public function notify()
    {
        $iResult = $this->_processEvent();
        http_response_code($iResult);
    }

    public function cancelRecurring($iPendingId, $sCustomerId, $sSubscriptionId)
    {
        return $this->deleteSubscription($sSubscriptionId);
    }

	public function createHostedPage($aItem, $aClient, $aVendor = array(), $iPendingId = 0) {
		$oPage = false;

		try {
			$aPage = array(
                            'embed' => false, //--- Note. 'embed' should be disabled to allow payments via PayPal
                            'subscription' => array(
                                    'planId' => $aItem['name']
                            ), 
                            'customer' => array(
                                    'email' => $aClient['email'],
                                    'firstName' => $aClient['name']
                            ), 
			);

			if(!empty($aVendor) && is_array($aVendor) && !empty($aVendor))
                $aPage['redirectUrl'] = bx_append_url_params($this->getReturnDataUrl($aVendor['id']), array(
					'pending_id' => $iPendingId
				));

			ChargeBee_Environment::configure($this->_getSite(), $this->_getApiKey());
			$oResult = ChargeBee_HostedPage::checkoutNew($aPage);
			$oPage = $oResult->hostedPage();
		}
		catch (Exception $oException) {
			$iError = $oException->getCode();
			$sError = $oException->getMessage();

			$this->log('Create Hosted Page Error: ' . $sError . '(' . $iError . ')');

			return false;
		}

		return $oPage;
	}

	public function retreiveHostedPage($sPageId) {
		$oPage = null;

		try {
			ChargeBee_Environment::configure($this->_getSite(), $this->_getApiKey());
			$oResult = ChargeBee_HostedPage::retrieve($sPageId);

			$oPage = $oResult->hostedPage();
		}
		catch (Exception $oException) {
			$iError = $oException->getCode();
			$sError = $oException->getMessage();

			$this->log('Retrieve Hosted Page Error: ' . $sError . '(' . $iError . ')');

			return false;
		}

		return $oPage;
	}

	public function retrieveSubscription($sSubscriptionId)
	{
		$oSubscription = null;

		try {
			ChargeBee_Environment::configure($this->_getSite(), $this->_getApiKey());
			$oResult = ChargeBee_Subscription::retrieve($sSubscriptionId);

			$oSubscription = $oResult->subscription();
			if($oSubscription->id != $sSubscriptionId)
				return false;
		}
		catch (Exception $oException) {
			$iError = $oException->getCode();
			$sError = $oException->getMessage();

			$this->log('Retrieve Subscription Error: ' . $sError . '(' . $iError . ')');

			return false;
		}

		return $oSubscription;
	}

	public function deleteSubscription($sSubscriptionId)
	{
            try {
                ChargeBee_Environment::configure($this->_getSite(), $this->_getApiKey());
                $oResult = ChargeBee_Subscription::cancel($sSubscriptionId);

                $oSubscription = $oResult->subscription();
                if($oSubscription->status != 'cancelled')
                    return false;
            }
            catch (Exception $oException) {
                $aError = $oException->getJsonBody();

                $this->log('Delete Subscription Error: ' . $aError['error']['message']);
                $this->log($aError);

                return false;
            }

            bx_alert($this->_oModule->_oConfig->getName(), $this->_sName . '_cancel_subscription', 0, false, array(
                'subscription_id' => $sSubscriptionId,
                'subscription_object' => &$oSubscription
            ));

            return true;
	}

    public function retrieveCustomer($sCustomerId)
	{
		$oCustomer = null;

		try {
			ChargeBee_Environment::configure($this->_getSite(), $this->_getApiKey());
			$oResult = ChargeBee_Customer::retrieve($sCustomerId);

			$oCustomer = $oResult->customer();
			if($oCustomer->id != $sCustomerId)
				return false;
		}
		catch (Exception $oException) {
			$iError = $oException->getCode();
			$sError = $oException->getMessage();

			$this->log('Retrieve Customer Error: ' . $sError . '(' . $iError . ')');

			return false;
		}

		return $oCustomer;
	}

	protected function _getSite()
	{
		return $this->_iMode == CBEE_MODE_LIVE ? $this->getOption('live_site') : $this->getOption('test_site');
	}

	protected function _getApiKey()
	{
		return $this->_iMode == CBEE_MODE_LIVE ? $this->getOption('live_api_key') : $this->getOption('test_api_key');
	}

	protected function _processEvent()
	{
    	$sInput = @file_get_contents("php://input");
		$aEvent = json_decode($sInput, true);
		if(empty($aEvent) || !is_array($aEvent)) 
			return 404;

		$sType = $aEvent['event_type'];
		if(!in_array($sType, array('payment_succeeded', ' payment_refunded', 'subscription_cancelled')))
			return 200;

		$this->log('Webhooks: ' . (!empty($sType) ? $sType : ''));
		$this->log($aEvent);

		$sMethod = '_processEvent' . bx_gen_method_name($sType, array('.', '_', '-'));
    	if(!method_exists($this, $sMethod))
    		return 200;

    	return $this->$sMethod($aEvent) ? 200 : 403;
    }

	protected function _processEventPaymentSucceeded(&$aEvent)
	{
		$mixedResult = $this->_getData($aEvent, true);
		if($mixedResult === false)
			return false;

		list($aPending, $aTransaction) = $mixedResult;

		$fTransactionAmount = (float)$aTransaction['amount'] / 100;
		$sTransactionCurrency = strtoupper($aTransaction['currency_code']);
		if($this->_bCheckAmount && ((float)$aPending['amount'] != $fTransactionAmount || strcasecmp($this->_oModule->_oConfig->getDefaultCurrencyCode(), $sTransactionCurrency) !== 0))
			return false;

        if($aPending['type'] == BX_PAYMENT_TYPE_RECURRING)
            $this->_oModule->updateSubscription($aPending, array(
                'paid' => 1
            ));

		return $this->_oModule->registerPayment($aPending);
	}

	protected function _processEventPaymentRefunded(&$aEvent)
	{
		$mixedResult = $this->_getData($aEvent);
		if($mixedResult === false)
			return false;

		list($aPending) = $mixedResult;
		return $this->_oModule->refundPayment($aPending);
	}

	protected function _processEventSubscriptionCancelled(&$aEvent)
	{
		$mixedResult = $this->_getData($aEvent);
		if($mixedResult === false)
			return false;

		list($aPending) = $mixedResult;
		return $this->_oModule->cancelSubscription($aPending);
	}

	protected function _getData(&$aEvent, $bWithStatusCheck = false)
	{
		$aTransaction = $aEvent['content']['transaction'];
		if(empty($aTransaction) || ($bWithStatusCheck && $aTransaction['status'] != 'success'))
			return false;

		$aPending = $this->_oModule->_oDb->getOrderPending(array('type' => 'order', 'order' => $aTransaction['subscription_id']));
		if(empty($aPending) || !is_array($aPending))
			return false;

		return array($aPending, $aTransaction);
	}
}

/** @} */
