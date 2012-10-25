<?php

class CheckoutsController extends PaypalExpressAppController {
	public $uses = array();
	public $components = array('PaypalExpress.Paypal');

	public function bill() {
		// $amount = $this->request->data('Paypal.amount');
		$amount = '1800';
		if (!preg_match('/^[1-9][0-9]*$/', $amount)) {
			// error
		}
		$this->Session->write('Paypal.amount', $amount);
		$res = $this->Paypal->callShortcutExpressCheckout($amount);
		$ack = strtoupper($res['ACK']);
		if ($ack === 'SUCCESS' || $ack === 'SUCCESSWITHWARNING') {
			$this->redirect($this->Paypal->redirectUrl($res['TOKEN']));
		} else {
			// error

		}
	}

	public function review() {
		$token = $this->request->query('token');
		if (null === $token) {
			$this->redirect($this->Paypal->cancelUrl);
		} else {
			$this->Session->write('Paypal.token', $token);
		}
		$res = $this->Paypal->getShippingDetails($token);
		$ack = strtoupper($res['ACK']);
		if ($ack === 'SUCCESS' || $ack === 'SUCCESSWITHWARNING') {
			$this->Session->write('Paypal.customer', $this->Paypal->fixCustomerData($res, $token));
			$this->Session->write('Paypal.payerId', $res['PAYERID']);
			$reviewPage = array_merge(Configure::read('App.review'), array('token' => $token));
			$this->redirect($reviewPage);
		} else {
			$this->Session->delete('Paypal.payerId');
		}
	}

	public function confirm() {
		$amount = $this->Session->read('Paypal.amount');
		$payerId = $this->Session->read('Paypal.payerId');
		$token = $this->Session->read('Paypal.token');
		$res = $this->Paypal->confirmPayment($amount, $token, $payerId);
		$ack = strtoupper($res['ACK']);
		if ($ack === 'SUCCESS' || $ack === 'SUCCESSWITHWARNING') {
			$paymentResult = $this->Paypal->fixPaymentResult($res, $token);
			$this->Session->write('Paypal.paymentResult', $paymentResult);
			$successPage = array_merge(Configure::read('App.finish'), array('token' => $token));
			$this->redirect($successPage);
		} else {
			$this->Session->delete('Paypal.payerId');
		}

	}
}
