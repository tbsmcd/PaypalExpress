<?php
App::uses('HttpSocket', 'Network/Http');
App::uses('SessionComponent', 'Controller/Component');

class PaypalComponent extends Component {
	public $apiEndpoint;
	public $paypalUrl;
	public $sBNCode = 'PP-ECWizard';
	public $version = '64';
	public $components = array('Session');

	// You must set following params in Controller::beforFilter() .
	public $username;
	public $password;
	public $signature;
	public $sandboxflag;
	public $currency;
	public $paymentType;
	public $returnUrl;
	public $cancelUrl;

	// for error logging
	private $errors = array();

	// map of request params
	protected $productsMap = array(
		'name' => array(
			'field' => 'L_PAYMENTREQUEST_0_NAME',
		),
		'description' => array(
			'field' => 'L_PAYMENTREQUEST_0_DESC',
		),
		'number' => array(
			'field' => 'L_PAYMENTREQUEST_0_NUMBER',
			'rule' => '/^(0|[1-9][0-9]*)$/',
		),
		'amount' => array(
			'field' => 'L_PAYMENTREQUEST_0_AMT',
			'rule' => '/^[1-9][0-9]*$/',
		),
		'quantity' => array(
			'field' => 'L_PAYMENTREQUEST_0_QTY',
			'rule' => '/^[1-9][0-9]*$/',
		),
	);
	protected $chargesMap = array(
		'handlingTotal' => array(
			'field' => 'PAYMENTREQUEST_0_HANDLINGAMT',
			'rule' => '/^(0|[1-9][0-9]*)$/',
		),
		'shippingDiscount' => array(
			'field' => 'PAYMENTREQUEST_0_SHIPDISCAMT',
			'rule' => '/^\-[1-9][0-9]*$/',
		),
		'shippingTotal' => array(
			'field' => 'PAYMENTREQUEST_0_SHIPPINGAMT',
			'rule' => '/^(0|[1-9][0-9]*)$/',
		),
		'insuranceTotal' => array(
			'field' => 'PAYMENTREQUEST_0_INSURANCEAMT',
			'rule' => '/^(0|[1-9][0-9]*)$/',
		),
		'orderTotal' => array(
			'field' => 'PAYMENTREQUEST_0_AMT',
			'rule' => '/^(0|[1-9][0-9]*)$/',
		),
	);

	// map of response fields
	// https://www.x.com/developers/paypal/documentation-tools/api/getexpresscheckoutdetails-api-operation-nvp
	protected $addressTypeMap = array(
		'PAYMENTREQUEST_0_SHIPTONAME' => 'name',
		'PAYMENTREQUEST_0_SHIPTOSTREET' => 'street',
		'PAYMENTREQUEST_0_SHIPTOSTREET2' => 'street2',
		'PAYMENTREQUEST_0_SHIPTOCITY' => 'city',
		'PAYMENTREQUEST_0_SHIPTOSTATE' => 'state',
		'PAYMENTREQUEST_0_SHIPTOZIP' => 'zip',
		'PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE' => 'country',
		'PAYMENTREQUEST_0_SHIPTOPHONENUM' => 'phone',
		'PAYMENTREQUEST_0_ADDRESSSTATUS' => 'addressStatus',
	);
	protected $paymentDetailsMap = array(
		'PAYMENTREQUEST_0_AMT' => 'amt',
		'PAYMENTREQUEST_0_CURRENCYCODE' => 'currency',
		'PAYMENTREQUEST_0_ITEMAMT' => 'itemAmt',
		'PAYMENTREQUEST_0_SHIPPINGAMT' => 'shippingAmt',
		'PAYMENTREQUEST_0_INSURANCEAMT' => 'insuranceAmt',
		'PAYMENTREQUEST_0_SHIPDISCAMT' => 'shippingDiscountAmt',
		'PAYMENTREQUEST_0_INSURANCEOPTIONOFFERED' => 'insuranceOptionOffered',
		'PAYMENTREQUEST_0_HANDLINGAMT' => 'handlingAmt',
		'PAYMENTREQUEST_0_TAXAMT' => 'taxAmt',
		'PAYMENTREQUEST_0_DESC' => 'description',
		'PAYMENTREQUEST_0_CUSTOM' => 'custom',
		'PAYMENTREQUEST_0_INVNUM' => 'invNumber',
		'PAYMENTREQUEST_0_NOTIFYURL' => 'notifyUrl',
		'PAYMENTREQUEST_0_NOTETEXT' => 'noteText',
		'PAYMENTREQUEST_0_TRANSACTIONID' => 'transactionId',
		'PAYMENTREQUEST_0_ALLOWEDPAYMENTMETHOD' => 'allowPaymentMethod',
		'PAYMENTREQUEST_0_PAYMENTREQUESTID' => 'paymentRequestId',
	);

	// https://www.x.com/developers/paypal/documentation-tools/api/doexpresscheckoutpayment-api-operation-nvp
	protected $paymentInfomationMap = array(
		'PAYMENTINFO_0_TRANSACTIONID' => 'transactionId',
		'PAYMENTINFO_0_TRANSACTIONTYPE' => 'transactionType',
		'PAYMENTINFO_0_PAYMENTTYPE' => 'paymentType',
		'PAYMENTINFO_0_ORDERTIME' => 'orderTime',
		'PAYMENTINFO_0_AMT' => 'amt',
		'PAYMENTINFO_0_CURRENCYCODE' => 'currencyCode',
		'PAYMENTINFO_0_FEEAMT' => 'feeAmt',
		'PAYMENTINFO_0_SETTLEAMT' => 'settleAmt',
		'PAYMENTINFO_0_TAXAMT' => 'taxAmt',
		'PAYMENTINFO_0_EXCHANGERATE' => 'exchangeRate',
		'PAYMENTINFO_0_PAYMENTSTATUS' => 'paymentStatus',
		'PAYMENTINFO_0_PENDINGREASON' => 'pendingReason',
		'PAYMENTINFO_0_REASONCODE' => 'reasonCode',
		'PAYMENTINFO_0_HOLDDECISION' => 'holdDecision',
		'PAYMENTINFO_0_PROTECTIONELIGIBILITY' => 'protectionEligibility',
		'PAYMENTINFO_0_PROTECTIONELIGIBILITYTYPE' => 'protectionEligibilityType',
		'STOREID' => 'storeId',
		'TERMINALID' => 'terminalId',
		'PAYMENTINFO_0_EBAYITEMAUCTIONTXNID' => 'ebayItemAuctionTxnId',
		'PAYMENTINFO_0_PAYMENTREQUESTID' => 'paymentRequestId',
	);

	public function startup($controller) {
		if ($this->sandboxFlag === true) {
			$this->apiEndpoint = 'https://api-3t.sandbox.paypal.com/nvp';
			$this->paypalUrl = 'https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&token=';
		} else {
			$this->apiEndpoint = 'https://api-3t.paypal.com/nvp';
			$this->paypalUrl = 'https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=';
		}
		if (false === CakeSession::started()) {
			CakeSession::start();
		}
	}

	public function getErrors() {
		return $this->errors;
	}

	/*
	//	example of orders.
	//
	//	$items = array(
	//		array(
	//			'name' => 'NAME0',
	//			'quantity' => 2,
	//			'amount' => 100,
	//			'description' => 'DESCRIPTION0',
	//		),
	//		array(
	//			'name' => 'NAME1',
	//			'quantity' => 1,
	//			'amount' => 200,
	//		),
	//	);
	//	$charges = array(
	//		'orderTotal' => '300',
	//		'shippingTotal' => '100',
	//	);
	//
	//	PAYMENTREQUEST_n_DESC along with any 2 parameters
	//	(L_PAYMENTREQUEST_n_NAMEm, L_PAYMENTREQUEST_n_NUMBERm),
	//	the order description value does not display.
	*/
	public function getToken($amount, $products = null, $charges = null) {
		if (!preg_match('/^[1-9][0-9]*$/', $amount)) {
			return false;
		}
		if (!isset($products[0]['name']) || $products[0]['name'] == '') {
			$products = null;
		}
		$this->Session->write('Paypal.amount', $amount);
		$res = $this->callShortcutExpressCheckout($amount, $products, $charges);
		$ack = strtoupper($res['ACK']);
		if ($ack === 'SUCCESS' || $ack === 'SUCCESSWITHWARNING') {
			return $res['TOKEN'];
		}
		return false;
	}

	public function paypalUrl($token) {
		return $this->paypalUrl . $token;
	}

	public function review($token = null) {
		if (isset($token)) {
			$this->Session->write('Paypal.token', $token);
		} else {
			return false;
		}
		$res = $this->getShippingDetails($token);
		$ack = strtoupper($res['ACK']);
		if ($ack === 'SUCCESS' || $ack === 'SUCCESSWITHWARNING') {
			$this->Session->write('Paypal.payerId', $res['PAYERID']);
			$map = array_merge($this->addressTypeMap, $this->paymentDetailsMap, array('EMAIL' => 'email'));
			$paymentResult = $this->_fixResults($token, $res, $map);
			$this->Session->write('Paypal.customer', $paymentResult);
			return $paymentResult;
		}
		return false;
	}

	public function finishCheckout() {
		$amount = $this->Session->read('Paypal.amount');
		$payerId = $this->Session->read('Paypal.payerId');
		$token = $this->Session->read('Paypal.token');
		$res = $this->confirmPayment($amount, $token, $payerId);
		$ack = strtoupper($res['ACK']);
		if ($ack === 'SUCCESS' || $ack === 'SUCCESSWITHWARNING') {
			$paymentResult = $this->_fixResults($token, $res, $this->paymentInfomationMap);
			return $paymentResult;
		} else {
			$this->errors['payer_id'] = $payerId;
			$this->errors['finish_checkout'] = $res;
		}
		return false;
	}

	public function callShortcutExpressCheckout ($paymentAmount, $products = null, $charges = null) {
		$nvp = array(
			'PAYMENTREQUEST_0_AMT' => $paymentAmount,
			'PAYMENTREQUEST_0_PAYMENTACTION' => $this->paymentType,
			'RETURNURL' => $this->returnUrl,
			'CANCELURL' => $this->cancelUrl,
			'PAYMENTREQUEST_0_CURRENCYCODE' => $this->currency,
		);
		if (is_array($products)) {
			$i = 0;
			$itemTotal = 0;
			foreach ($products as $product) {
				$nvp = array_merge($nvp, $this->_convert2Nvp($product, $this->productsMap, $i));
				$itemTotal += $product['amount'] * $product['quantity'];
				$i++;
			}
			$nvp['PAYMENTREQUEST_0_ITEMAMT'] = $itemTotal;
		}
		$nvp = array_merge($nvp, $this->_convert2Nvp($charges, $this->chargesMap));
		return $this->_hashCall('setExpressCheckout', $nvp);
	}

	public function getShippingDetails($token) {
		$nvp = array(
			'TOKEN' => $token,
		);
		$details = $this->_hashCall('GetExpressCheckoutDetails', $nvp);
		return $details;
	}

	public function confirmPayment($amount, $token, $payerId) {
		$nvp = array(
			'TOKEN' => $token,
			'PAYERID' => $payerId,
			'PAYMENTREQUEST_0_PAYMENTACTION' => $this->paymentType,
			'PAYMENTREQUEST_0_AMT' => $amount,
			'PAYMENTREQUEST_0_CURRENCYCODE' => $this->currency,
			'IPADDRESS' => env('SERVER_NAME'),
		);
		return $this->_hashCall('DoExpressCheckoutPayment', $nvp);
	}

	protected function _hashCall($methodName, $additionalNvp) {
		$nvp = array(
			'METHOD' => $methodName,
			'VERSION' => $this->version,
			'PWD' => $this->password,
			'USER' => $this->username,
			'SIGNATURE' => $this->signature,
			'BUTTONSOURCE' => $this->sBNCode,
		);
		$nvp = array_merge($nvp, $additionalNvp);
		$socket = new HttpSocket();
		$res = $socket->post($this->apiEndpoint, $nvp);
		parse_str($res->body, $body);
		return $body;
	}

	protected function _convert2Nvp($data, $map, $dataNumber = null) {
		$nvpArray = array();
		if (!isset($dataNumber) || !preg_match('/^(0|[1-9][0-9]*)$/', $dataNumber)) {
			$dataNumber = '';
		}
		foreach ($data as $key => $value) {
			if (isset($map[$key])) {
				if (!isset($map[$key]['rule']) || (isset($map[$key]['rule']) && preg_match($map[$key]['rule'], $value))) {
					$nvpArray[$map[$key]['field'] . $dataNumber] = $value;
				}
			}
		}
		return $nvpArray;
	}

	protected function _fixResults($token, $data, $map) {
		$fixed = array();
		$fixed['token'] = $token;
		foreach ($data as $key => $value) {
			if (isset($map[$key])) {
				$fixed[$map[$key]] = $value;
			}
		}
		return $fixed;
	}
}
