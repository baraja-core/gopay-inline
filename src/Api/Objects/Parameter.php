<?php

declare(strict_types=1);

namespace Contributte\GopayInline\Api\Objects;

class Parameter extends AbstractObject
{

	/** @var string */
	public $name;

	/** @var mixed */
	public $value;

	/**
	 * ABSTRACT ****************************************************************
	 */

	/**
	 * @return array
	 */
	public function toArray()
	{
		return [
			'name' => $this->name,
			'value' => $this->value,
		];
	}

}
