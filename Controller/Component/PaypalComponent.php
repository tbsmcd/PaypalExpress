<?php
App::uses('HttpSocket', 'Network/Http');
App::uses('SessionComponent', 'Controller/Component');

class PaypalComponent extends Component {
	public $apiEndpoint;
	public $paypalUrl;
	public $sBNCode = 'PP-ECWizard';
	public $version = '64';
	public $components = array('Session');

	// map of params
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
		'quanity' => array(
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

	// You must set following params in Controller::beforFilter() .
	public $username;
	public $password;
	public $signature;
	public $sandboxflag;
	public $currency;
	public $paymentType;
	public $returnUrl;
	public $cancelUrl;

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
		debug($res);
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
			$this->Session->write('Paypal.customer', $this->fixCustomerData($res, $token));
			return $this->fixCustomerData($res, $token);
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
			$paymentResult = $this->fixPaymentResult($res, $token);
			return $paymentResult;
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
/*
				$nvp['L_PAYMENTREQUEST_0_AMT' . $i] = $product['amount'];
				if (isset($product['name']) && $product['name'] !== '') {
					$nvp['L_PAYMENTREQUEST_0_NAME' . $i] = $product['name'];
				}
				if (isset($product['quanity']) && preg_match('/^[1-9][0-9]*$/', $product['quanity'])) {
					$nvp['L_PAYMENTREQUEST_0_QTY' . $i] = $product['quanity'];
				} else {
					$nvp['L_PAYMENTREQUEST_0_QTY' . $i] = 1;
				}
				$itemTotal += $nvp['L_PAYMENTREQUEST_0_AMT' . $i] * $nvp['L_PAYMENTREQUEST_0_QTY' . $i];
				if (isset($product['number']) && preg_match('/^[1-9][0-9]*$/', $product['number'])) {
					$nvp['L_PAYMENTREQUEST_0_NUMBER' . $i] = $product['number'];
				} elseif (!isset($nvp['L_PAYMENTREQUEST_0_NAME' . $i])) {
					break;
				}
				if (isset($product['description']) && $product['description'] !== '') {
					$nvp['L_PAYMENTREQUEST_0_DESC' . $i] = $product['description'];
				}
 */
				$i++;
			}
			$nvp['PAYMENTREQUEST_0_ITEMAMT'] = $itemTotal;
		}
		$nvp = array_merge($nvp, $this->_convert2Nvp($charges, $this->chargesMap));
/*
		if (isset($charges['shippingTotal']) && preg_match('/^[1-9][0-9]*$/', $charges['shippingTotal'])) {
			$nvp['PAYMENTREQUEST_0_SHIPPINGAMT'] = $charges['shippingTotal'];
		}
		if (isset($charges['handlingTotal']) && preg_match('/^[1-9][0-9]*$/', $charges['handlingTotal'])) {
			$nvp['PAYMENTREQUEST_0_HANDLINGAMT'] = $charges['handlingTotal'];
		}
		if (isset($charges['taxTotal']) && preg_match('/^[1-9][0-9]*$/', $charges['taxTotal'])) {
			$nvp['PAYMENTREQUEST_0_TAXAMT'] = $charges['taxTotal'];
		}
		if (isset($charges['shippingDisTotal']) && preg_match('/^\-[1-9][0-9]*$/', $charges['shippingDisTotal'])) {
			$nvp['PAYMENTREQUEST_0_SHIPPINGDISAMT'] = $charges['shippingDisTotal'];
		}
		if (isset($charges['insuranceTotal']) && preg_match('/^\-[1-9][0-9]*$/', $charges['insuranceTotal'])) {
			$nvp['PAYMENTREQUEST_0_INSURANCEAMT'] = $charges['insuranceTotal'];
		}
 */
		debug($nvp);
		return $this->hashCall('setExpressCheckout', $nvp);
	}

	public function getShippingDetails($token) {
		$nvp = array(
			'TOKEN' => $token,
		);
		$details = $this->hashCall('GetExpressCheckoutDetails', $nvp);
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
		return $this->hashCall('DoExpressCheckoutPayment', $nvp);
	}

	public function hashCall ($methodName, $additionalNvp) {
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

	public function	fixCustomerData($data, $token) {
		$customer = array(
			'token' => $token,
			'name' => $data['SHIPTONAME'],
			'email' => $data['EMAIL'],
			'street' => $data['SHIPTOSTREET'],
			'city' => $data['SHIPTOCITY'],
			'state' => $data['SHIPTOSTATE'],
			'zip' => $data['SHIPTOZIP'],
			'country' => $data['SHIPTOCOUNTRYNAME'],
			'currency' => $data['CURRENCYCODE'],
			'amt' => $data['AMT'],
			'shippingAmt' => $data['SHIPPINGAMT'],
			'taxAmt' => $data['TAXAMT'],
			'insuranceAmt' => $data['INSURANCEAMT'],
			'shiopdiscAmt' => $data['SHIPDISCAMT'],
		);
		return $customer;
	}

	public function fixPaymentResult($data, $token) {
		$paymentResult = array(
			'token' => $token,
			'transactionId' => $data['PAYMENTINFO_0_TRANSACTIONID'],
			'transactionType' => $data['PAYMENTINFO_0_TRANSACTIONTYPE'],
			'paymentType' => $data['PAYMENTINFO_0_PAYMENTTYPE'],
			'orderTime' => $data['PAYMENTINFO_0_ORDERTIME'],
			'amt' => $data['PAYMENTINFO_0_AMT'],
			'currencyCode' => $data['PAYMENTINFO_0_CURRENCYCODE'],
			'paymentStatus' => $data['PAYMENTINFO_0_PAYMENTSTATUS'],
			'pendingReason' => $data['PAYMENTINFO_0_PENDINGREASON'],
			'pandingReason' => $data['PAYMENTINFO_0_REASONCODE'],
		);
		if (isset($data['PAYMENTINFO_0_TAXAMT'])) {
			$paymentResult['taxAmt'] = $data['PAYMENTINFO_0_TAXAMT'];
		}
		if (isset($data['PAYMENTINFO_0_FEEAMT'])) {
			$paymentResult['feeAmt'] = $data['PAYMENTINFO_0_FEEAMT'];
		}
		if (isset($data['PAYMENTINFO_0_EXCHANGERATE'])) {
			$paymentResult['exchangeRate'] = $data['PAYMENTINFO_0_EXCHANGERATE'];
		}
		if (isset($data['PAYMENTINFO_0_SETTLEAMT'])) {
			$paymentResult['settleAmt'] = $data['PAYMENTINFO_0_SETTLEAMT'];
		}
		return $paymentResult;
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
}
