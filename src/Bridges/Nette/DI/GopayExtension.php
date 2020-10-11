<?php

namespace Contributte\GopayInline\Bridges\Nette\DI;


use Contributte\GopayInline\Client;
use Contributte\GopayInline\Config;
use Nette\DI\CompilerExtension;
use Nette\DI\Statement;
use Nette\Utils\Validators;

class GopayExtension extends CompilerExtension
{

	/** @var array */
	private $defaults = [
		'goId' => null,
		'clientId' => null,
		'clientSecret' => null,
		'test' => true,
	];


	/**
	 * Register services
	 *
	 * @return void
	 */
	public function loadConfiguration()
	{
		$config = $this->validateConfig($this->defaults);
		$builder = $this->getContainerBuilder();

		Validators::assertField($config, 'goId', 'string|number');
		Validators::assertField($config, 'clientId', 'string|number');
		Validators::assertField($config, 'clientSecret', 'string');
		Validators::assertField($config, 'test', 'bool');

		$builder->addDefinition($this->prefix('client'))
			->setFactory(Client::class, [
				new Statement(Config::class, [
					$config['goId'],
					$config['clientId'],
					$config['clientSecret'],
					$config['test'] !== false ? Config::TEST : Config::PROD,
				]),
			]);
	}

}
