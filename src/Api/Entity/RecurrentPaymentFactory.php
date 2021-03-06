<?php

declare(strict_types=1);

namespace Contributte\GopayInline\Api\Entity;


use Contributte\GopayInline\Api\Objects\Contact;
use Contributte\GopayInline\Api\Objects\Eet;
use Contributte\GopayInline\Api\Objects\Item;
use Contributte\GopayInline\Api\Objects\Parameter;
use Contributte\GopayInline\Api\Objects\Payer;
use Contributte\GopayInline\Api\Objects\Recurrence;
use Contributte\GopayInline\Api\Objects\Target;
use Contributte\GopayInline\Exception\ValidationException;
use Contributte\GopayInline\Utils\Validator;

final class RecurrentPaymentFactory
{
	public const V_SCHEME = 1;

	public const V_PRICES = 2;

	/** @var string[] */
	public static $required = [
		// 'target', # see at AbstractPaymentService
		'amount',
		'currency',
		'order_number',
		'order_description',
		'items',
		'recurrence',
		'return_url',
		'notify_url',
	];

	/** @var string[] */
	public static $optional = [
		'target',
		'payer',
		'additional_params',
		'lang',
		'eet',
	];

	/** @var true[] (int => true) */
	public static $validators = [
		self::V_SCHEME => true,
		self::V_PRICES => true,
	];


	/**
	 * @param mixed $data
	 * @param mixed[] $validators
	 * @return RecurrentPayment
	 */
	public static function create($data, $validators = []): RecurrentPayment
	{
		// Convert to array
		$data = (array) $data;
		$validators = $validators + self::$validators;

		// CHECK REQUIRED DATA ###################

		$res = Validator::validateRequired($data, self::$required);
		if ($res !== true) {
			throw new ValidationException('Missing keys "' . (implode(', ', $res)) . '""');
		}

		// CHECK SCHEME DATA #####################

		$res = Validator::validateOptional($data, array_merge(self::$required, self::$optional));
		if ($res !== true && $validators[self::V_SCHEME] === true) {
			throw new ValidationException('Not allowed keys "' . (implode(', ', $res)) . '""');
		}

		// CREATE RECURRENT PAYMENT ########################

		$recurrentPayment = new RecurrentPayment();

		// ### PAYER
		if (isset($data['payer'])) {
			$payer = new Payer;
			self::map($payer, [
				'allowed_payment_instruments' => 'allowedPaymentInstruments',
				'default_payment_instrument' => 'defaultPaymentInstrument',
				'allowed_swifts' => 'allowedSwifts',
				'default_swift' => 'defaultSwift',
			], $data['payer']);
			$recurrentPayment->setPayer($payer);

			if (isset($data['payer']['contact'])) {
				$contact = new Contact;
				self::map($contact, [
					'first_name' => 'firstname',
					'last_name' => 'lastname',
					'email' => 'email',
					'phone_number' => 'phone',
					'city' => 'city',
					'street' => 'street',
					'postal_code' => 'zip',
					'country_code' => 'country',
				], $data['payer']['contact']);
				$payer->contact = $contact;
			}
		}

		// ### TARGET
		if (isset($data['target'])) {
			$target = new Target;
			self::map($target, ['type' => 'type', 'goid' => 'goid'], $data['target']);
			$recurrentPayment->setTarget($target);
		}

		// ### COMMON
		$recurrentPayment->setAmount($data['amount']);
		$recurrentPayment->setCurrency($data['currency']);
		$recurrentPayment->setOrderNumber($data['order_number']);
		$recurrentPayment->setOrderDescription($data['order_description']);
		$recurrentPayment->setReturnUrl($data['return_url']);
		$recurrentPayment->setNotifyUrl($data['notify_url']);

		// ### ITEMS
		foreach ($data['items'] as $param) {
			if ((!isset($param['name']) || !$param['name']) && $validators[self::V_SCHEME] === true) {
				throw new ValidationException('Item\'s name can\'t be empty or null.');
			}
			$item = new Item;
			self::map($item, [
				'name' => 'name',
				'amount' => 'amount',
				'count' => 'count',
				'vat_rate' => 'vatRate',
				'type' => 'type',
			], $param);
			$recurrentPayment->addItem($item);
		}

		// ### RECURRENCE
		if (isset($data['recurrence'])) {
			$recurrence = new Recurrence();
			self::map($recurrence, ['recurrence_cycle' => 'cycle', 'recurrence_period' => 'period', 'recurrence_date_to' => 'dateTo'], $data['recurrence']);
			$recurrentPayment->setRecurrence($recurrence);
		}

		// ### ADDITIONAL PARAMETERS
		if (isset($data['additional_params'])) {
			foreach ($data['additional_params'] as $param) {
				$parameter = new Parameter;
				self::map($parameter, ['name' => 'name', 'value' => 'value'], $param);
				$recurrentPayment->addParameter($parameter);
			}
		}

		// ### LANG
		if (isset($data['lang'])) {
			$recurrentPayment->setLang($data['lang']);
		}

		// VALIDATION PRICE & ITEMS PRICE ########
		$itemsPrice = 0;
		$orderPrice = $recurrentPayment->getAmount();
		foreach ($recurrentPayment->getItems() as $item) {
			$itemsPrice += $item->amount * $item->count;
		}
		if ($itemsPrice !== $orderPrice && $validators[self::V_PRICES] === true) {
			throw new ValidationException(sprintf('Payment price (%s) and items price (%s) do not match', $orderPrice, $itemsPrice));
		}

		// ### EET
		if (isset($data['eet'])) {
			$eet = new Eet();
			self::map($eet, [
				'mena' => 'currency',
				'celk_trzba' => 'sum',
				'zakl_dan1' => 'taxBase',
				'zakl_nepodl_dph' => 'taxBaseNoVat',
				'dan1' => 'tax',
				'zakl_dan2' => 'taxBaseReducedRateFirst',
				'dan2' => 'taxReducedRateFirst',
				'zakl_dan3' => 'taxBaseReducedRateSecond',
				'dan3' => 'taxReducedRateSecond',
			], $data['eet']);

			$eetSum = $eet->getSum();
			$eetTotal = $eet->getTax()
				+ $eet->getTaxBaseNoVat()
				+ $eet->getTaxBase()
				+ $eet->getTaxBaseReducedRateFirst()
				+ $eet->getTaxReducedRateFirst()
				+ $eet->getTaxBaseReducedRateSecond()
				+ $eet->getTaxReducedRateSecond();

			if ($validators[self::V_PRICES] === true) {
				if (number_format($eetSum, 8) !== number_format($eetTotal, 8)) {
					throw new ValidationException(sprintf('EET sum (%s) and EET tax sum (%s) do not match', $eetSum, $eetTotal));
				}
				if (number_format($eetSum, 8) !== number_format($orderPrice, 8)) {
					throw new ValidationException(sprintf('EET sum (%s) and order sum (%s) do not match', $eetSum, $orderPrice));
				}
			}

			$recurrentPayment->setEet($eet);
		}

		return $recurrentPayment;
	}


	/**
	 * @param object $obj
	 * @param mixed[] $mapping
	 * @param mixed[] $data
	 * @return object
	 */
	public static function map($obj, array $mapping, array $data)
	{
		foreach ($mapping as $from => $to) {
			if (isset($data[$from])) {
				$obj->{$to} = $data[$from];
			}
		}

		return $obj;
	}
}
