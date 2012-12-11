<?php

App::uses('Controller', 'Controller');
App::uses('Component', 'Controller');
App::uses('CakeRequest', 'Network');
App::uses('CakeResponse', 'Network');
App::uses('CakeSession', 'Model/Datasource');
App::uses('HttpSocket', 'Network/Http');
App::uses('SessionComponent', 'Controller/Component');
App::uses('ComponentCollection', 'Controller');
App::uses('PaypalComponent', 'PaypalExpress.Controller/Component');

class TestPaypalComponent extends PaypalComponent {
	public static function publicConvert2Nvp($data, $map, $dataNumber = null) {
		return parent::_convert2Nvp($data, $map, $dataNumber);
	}

	public static function publicFixResults($token, $data, $map) {
		return parent::_fixResults($token, $data, $map);
	}
}

class TestPaypalController extends Controller {
}


class PaypalComponentTestCase extends CakeTestCase {
	public $PaypalComponent = null;
	public $Controller = null;

	public function setUp() {
		parent::setUp();
		$Collection = new ComponentCollection();
		$this->PaypalComponent = new PaypalComponent($Collection);
		$CakeRequest = new CakeRequest();
		$CakeResponse = new CakeResponse();
		$this->Controller = new TestPaypalController($CakeRequest, $CakeResponse);
		$this->PaypalComponent->startup($this->Controller);
	}

	public function testPaypalUrlSandbox () {
		$this->PaypalComponent->sandboxFlag = true;
		$this->PaypalComponent->startup($this->Controller);
		$token = 'token';
		$result = $this->PaypalComponent->paypalUrl($token);
		$expected = 'https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&token=' . $token;
		$this->assertEquals($result, $expected);
	}

	public function testPaypalUrlPublic () {
		$this->PaypalComponent->sandboxFlag = false;
		$this->PaypalComponent->startup($this->Controller);
		$token = 'token';
		$result = $this->PaypalComponent->paypalUrl($token);
		$expected = 'https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=' . $token;
		$this->assertEquals($result, $expected);
	}

	public function testConvert2Nvp() {
		$data = array(
			'name' => 'NAME',
			'description' => 'DESCRIPTION',
			'number' => '3',
		);
		$result = TestPaypalComponent::publicConvert2Nvp($data, $this->PaypalComponent->productsMap, 0);
		$expected = array(
			'L_PAYMENTREQUEST_0_NAME0' => 'NAME',
			'L_PAYMENTREQUEST_0_DESC0' => 'DESCRIPTION',
			'L_PAYMENTREQUEST_0_NUMBER0' => '3',
		);
		$this->assertEquals($result, $expected);
	}

	public function testConvert2NvpWithoutNumber() {
		$data = array(
			'handlingTotal' => '3',
			'shippingDiscount' => '-50',
		);
		$result = TestPaypalComponent::publicConvert2Nvp($data, $this->PaypalComponent->chargesMap);
		$expected = array(
			'PAYMENTREQUEST_0_HANDLINGAMT' => '3',
			'PAYMENTREQUEST_0_SHIPDISCAMT' => '-50',
		);
		$this->assertEquals($result, $expected);
	}

	public function testFixResults() {
		$data = array(
			'PAYMENTREQUEST_0_SHIPTONAME' => 'NAME',
			'PAYMENTREQUEST_0_SHIPTOSTREET' => 'STREET',
		);
		$result = TestPaypalComponent::publicFixResults('TOKEN', $data, $this->PaypalComponent->addressTypeMap);
		$expected = array(
			'token' => 'TOKEN',
			'name' => 'NAME',
			'street' => 'STREET',
		);
		$this->assertEquals($result, $expected);
	}

}
