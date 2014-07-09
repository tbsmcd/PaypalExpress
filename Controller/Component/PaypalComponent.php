<?php
App::uses('HttpSocket', 'Network/Http');
App::uses('SessionComponent', 'Controller/Component');

App::import('Paypal.Lib', 'Maps');
use PaypalPlugin\Maps as Maps;

class PaypalComponent extends Component {

	public $apiEndpoint;
	public $paypalUrl;
	public $sBNCode = 'PP-ECWizard';
	public $version = '64';
	public $components = ['Session'];

	// You must set following params in Controller::beforFilter() .
	public $username;
	public $password;
	public $signature;
	public $sandboxflag;
	public $currency;
	public $paymentType;
	public $returnUrl;
	public $cancelUrl;

	// maps
	protected $maps = [];

	public function startup(Controller $controller) {
		$this->maps = Maps::supplyMaps();
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
	//	$items = [
	//		[
	//			'name' => 'NAME0',
	//			'quantity' => 2,
	//			'amount' => 100,
	//			'description' => 'DESCRIPTION0',
	//		],
	//		[
	//			'name' => 'NAME1',
	//			'quantity' => 1,
	//			'amount' => 200,
	//		],
	//	];
	//	$charges = [
	//		'orderTotal' => '300',
	//		'shippingTotal' => '100',
	//	];
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
			$map = array_merge($this->maps['addressType'], $this->maps['paymentDetails'], ['EMAIL' => 'email']);
			$paymentResult = $this->_fixResults($token, $res, $map);
			$this->Session->write('Paypal.customer', $paymentResult);
			return $paymentResult;
		}
		return false;
	}

	public function finishCheckout() {
		$session = $this->Session->read('Paypal');
		$res = $this->confirmPayment($session/*amount, $token, $payerId*/);
		$ack = strtoupper($res['ACK']);
		if ($ack === 'SUCCESS' || $ack === 'SUCCESSWITHWARNING') {
			$paymentResult = $this->_fixResults($token, $res, $this->maps['paymentInfomation']);
			return $paymentResult;
		}
		return false;
	}

	public function callShortcutExpressCheckout ($paymentAmount, $products = null, $charges = null) {
		$nvp = [
			'PAYMENTREQUEST_0_AMT' => $paymentAmount,
			'PAYMENTREQUEST_0_PAYMENTACTION' => $this->paymentType,
			'RETURNURL' => $this->returnUrl,
			'CANCELURL' => $this->cancelUrl,
			'PAYMENTREQUEST_0_CURRENCYCODE' => $this->currency,
		];
		if (is_array($products)) {
			$i = 0;
			$itemTotal = 0;
			foreach ($products as $product) {
				$nvp = array_merge($nvp, $this->_convert2Nvp($product, $this->maps['products'], $i));
				$itemTotal += $product['amount'] * $product['quantity'];
				$i++;
			}
			$nvp['PAYMENTREQUEST_0_ITEMAMT'] = $itemTotal;
		}
		$nvp = array_merge($nvp, $this->_convert2Nvp($charges, $this->maps['charges']));
		return $this->_hashCall('setExpressCheckout', $nvp);
	}

	public function getShippingDetails($token) {
		$nvp = [
			'TOKEN' => $token,
		];
		$details = $this->_hashCall('GetExpressCheckoutDetails', $nvp);
		return $details;
	}

	public function confirmPayment($data) {
		$nvp = [
			'TOKEN' => $data['token'],
			'PAYERID' => $data['payerId'],
			'PAYMENTREQUEST_0_PAYMENTACTION' => $this->paymentType,
			'PAYMENTREQUEST_0_AMT' => $data['amount'],
			'PAYMENTREQUEST_0_CURRENCYCODE' => $this->currency,
			'IPADDRESS' => env('SERVER_NAME'),
		];
		return $this->_hashCall('DoExpressCheckoutPayment', $nvp);
	}

	protected function _hashCall($methodName, $additionalNvp) {
		$nvp = [
			'METHOD' => $methodName,
			'VERSION' => $this->version,
			'PWD' => $this->password,
			'USER' => $this->username,
			'SIGNATURE' => $this->signature,
			'BUTTONSOURCE' => $this->sBNCode,
		];
		$nvp = array_merge($nvp, $additionalNvp);
		$socket = new HttpSocket();
		$res = $socket->post($this->apiEndpoint, $nvp);
		parse_str($res->body, $body);
		return $body;
	}

	protected function _convert2Nvp($data, $map, $dataNumber = null) {
		$nvpArray = [];
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
		$fixed = [];
		$fixed['token'] = $token;
		foreach ($data as $key => $value) {
			if (isset($map[$key])) {
				$fixed[$map[$key]] = $value;
			}
		}
		return $fixed;
	}
}

