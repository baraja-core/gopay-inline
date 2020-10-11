<?php

namespace Contributte\GopayInline\Api\Entity;


use Contributte\GopayInline\Api\Objects\Item;
use Contributte\GopayInline\Api\Objects\Parameter;
use Contributte\GopayInline\Exception\ValidationException;
use Contributte\GopayInline\Utils\Validator;

class RecurringPaymentFactory
{

	// Validator's types
	const V_SCHEME = 1;

	const V_PRICES = 2;

	/** @var array */
	public static $required = [
		'amount',
		'currency',
		'order_number',
		'order_description',
		'items',
	];

	/** @var array */
	public static $optional = [
		'additional_params',
	];

	/** @var array */
	public static $validators = [
		self::V_SCHEME => true,
		self::V_PRICES => true,
	];


	/**
	 * @param mixed $data
	 * @param array $validators
	 * @return RecurringPayment
	 */
	public static function create($data, $validators = [])
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
		if ($res !== true) {
			if ($validators[self::V_SCHEME] === true) {
				throw new ValidationException('Not allowed keys "' . (implode(', ', $res)) . '""');
			}
		}

		// CREATE RECURRENT PAYMENT ########################

		$recurringPayment = new RecurringPayment();

		// ### COMMON
		$recurringPayment->setAmount($data['amount']);
		$recurringPayment->setCurrency($data['currency']);
		$recurringPayment->setOrderNumber($data['order_number']);
		$recurringPayment->setOrderDescription($data['order_description']);

		// ### ITEMS
		foreach ($data['items'] as $param) {
			if (!isset($param['name']) || !$param['name']) {
				if ($validators[self::V_SCHEME] === true) {
					throw new ValidationException('Item\'s name can\'t be empty or null.');
				}
			}
			$item = new Item;
			self::map($item, [
				'name' => 'name',
				'amount' => 'amount',
				'count' => 'count',
				'vat_rate' => 'vatRate',
				'type' => 'type',
			], $param);
			$recurringPayment->addItem($item);
		}

		// ### ADDITIONAL PARAMETERS
		if (isset($data['additional_params'])) {
			foreach ($data['additional_params'] as $param) {
				$parameter = new Parameter;
				self::map($parameter, ['name' => 'name', 'value' => 'value'], $param);
				$recurringPayment->addParameter($parameter);
			}
		}

		// VALIDATION PRICE & ITEMS PRICE ########
		$itemsPrice = 0;
		$orderPrice = $recurringPayment->getAmount();
		foreach ($recurringPayment->getItems() as $item) {
			$itemsPrice += $item->amount * $item->count;
		}
		if ($itemsPrice !== $orderPrice) {
			if ($validators[self::V_PRICES] === true) {
				throw new ValidationException(sprintf('Payment price (%s) and items price (%s) do not match', $orderPrice, $itemsPrice));
			}
		}

		return $recurringPayment;
	}


	/**
	 * @param object $obj
	 * @param array $mapping
	 * @param array $data
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
