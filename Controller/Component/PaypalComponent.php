<?php
App::uses('HttpSocket', 'Network/Http');
Configure::load('PaypalExpress.keys');

class PaypalComponent extends Component {
	public $apiEndpoint;
	public $paypalUrl;
	public $sBNCode = 'PP-ECWizard';
	public $version = '64';
	public $returnUrl;
	public $cancelUrl;

	public function initialize($controller) {
		$this->returnUrl = Router::url(array(
			'controller' => 'checkouts',
			'action' => 'review',
		), true);
		$this->cancelUrl = Router::url(array(
			'controller' => 'checkouts',
			'action' => 'cancel',
		), true);

		$sandboxFlag = Configure::read('Api.sandboxFlag');
		if ($sandboxFlag === true) {
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

	public function callShortcutExpressCheckout ($paymentAmount) {
		$appConf = Configure::read('App');
		$nvp = array(
			'PAYMENTREQUEST_0_AMT' => $paymentAmount,
			'PAYMENTREQUEST_0_PAYMENTACTION' => $appConf['paymentType'],
			'RETURNURL' => $this->returnUrl,
			'CANCELURL' => $this->cancelUrl,
			'PAYMENTREQUEST_0_CURRENCYCODE' => $appConf['currency'],
		);

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
			'PAYMENTREQUEST_0_PAYMENTACTION' => Configure::read('App.paymentType'),
			'PAYMENTREQUEST_0_AMT' => $amount,
			'PAYMENTREQUEST_0_CURRENCYCODE' => Configure::read('App.currency'),
			'IPADDRESS' => env('SERVER_NAME'),
		);
		return $this->hashCall('DoExpressCheckoutPayment', $nvp);
	}

	public function hashCall ($methodName, $additionalNvp) {
		$apiConf = Configure::read('Api');
		$nvp = array(
			'METHOD' => $methodName,
			'VERSION' => $this->version,
			'PWD' => $apiConf['Password'],
			'USER' => $apiConf['UserName'],
			'SIGNATURE' => $apiConf['Signature'],
			'BUTTONSOURCE' => $this->sBNCode,
		);
		$nvp = array_merge($nvp, $additionalNvp);
		$socket = new HttpSocket();
		$res = $socket->post($this->apiEndpoint, $nvp);
		parse_str($res->body, $body);
		return $body;
	}

	public function redirectUrl($token) {
		return $this->paypalUrl . $token;
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


}
