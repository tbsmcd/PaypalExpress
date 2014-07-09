<?php

/* To use
// App::import('Paypal.Lib', 'Maps');
// user PaypalPlugin\Maps as foo;
 */

namespace PaypalPlugin;

class Maps {

	// map of request params
	protected static $productsMap = [
		'name' => [
			'field' => 'L_PAYMENTREQUEST_0_NAME',
		],
		'description' => [
			'field' => 'L_PAYMENTREQUEST_0_DESC',
		],
		'number' => [
			'field' => 'L_PAYMENTREQUEST_0_NUMBER',
			'rule' => '/^(0|[1-9][0-9]*)$/',
		],
		'amount' => [
			'field' => 'L_PAYMENTREQUEST_0_AMT',
			'rule' => '/^[1-9][0-9]*$/',
		],
		'quantity' => [
			'field' => 'L_PAYMENTREQUEST_0_QTY',
			'rule' => '/^[1-9][0-9]*$/',
		],
	];

	protected static $chargesMap = [
		'handlingTotal' => [
			'field' => 'PAYMENTREQUEST_0_HANDLINGAMT',
			'rule' => '/^(0|[1-9][0-9]*)$/',
		],
		'shippingDiscount' => [
			'field' => 'PAYMENTREQUEST_0_SHIPDISCAMT',
			'rule' => '/^\-[1-9][0-9]*$/',
		],
		'shippingTotal' => [
			'field' => 'PAYMENTREQUEST_0_SHIPPINGAMT',
			'rule' => '/^(0|[1-9][0-9]*)$/',
		],
		'insuranceTotal' => [
			'field' => 'PAYMENTREQUEST_0_INSURANCEAMT',
			'rule' => '/^(0|[1-9][0-9]*)$/',
		],
		'orderTotal' => [
			'field' => 'PAYMENTREQUEST_0_AMT',
			'rule' => '/^(0|[1-9][0-9]*)$/',
		],
	];

	// map of response fields
	// https://www.x.com/developers/paypal/documentation-tools/api/getexpresscheckoutdetails-api-operation-nvp
	 protected static $addressTypeMap = [
		'PAYMENTREQUEST_0_SHIPTONAME' => 'name',
		'PAYMENTREQUEST_0_SHIPTOSTREET' => 'street',
		'PAYMENTREQUEST_0_SHIPTOSTREET2' => 'street2',
		'PAYMENTREQUEST_0_SHIPTOCITY' => 'city',
		'PAYMENTREQUEST_0_SHIPTOSTATE' => 'state',
		'PAYMENTREQUEST_0_SHIPTOZIP' => 'zip',
		'PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE' => 'country',
		'PAYMENTREQUEST_0_SHIPTOPHONENUM' => 'phone',
		'PAYMENTREQUEST_0_ADDRESSSTATUS' => 'addressStatus',
	];

	protected static $paymentDetailsMap = [
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
	];

	// https://www.x.com/developers/paypal/documentation-tools/api/doexpresscheckoutpayment-api-operation-nvp
	protected static $paymentInfomationMap = [
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
	];

	public static function supplyMaps() {
		return [
			'products' => self::$productsMap,
			'charges' => self::$chargesMap,
			'addressType' => self::$addressTypeMap,
			'paymentDetails' => self::$paymentDetailsMap,
			'paymentInfomation' => self::$paymentInfomationMap,
		];
	}

}

