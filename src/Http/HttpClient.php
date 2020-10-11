<?php

declare(strict_types=1);

namespace Contributte\GopayInline\Http;


use Contributte\GopayInline\Exception\HttpException;

class HttpClient implements Http
{

	/** @var Io */
	protected $io;


	/**
	 * @return Io
	 */
	public function getIo()
	{
		if ($this->io === null) {
			$this->io = new Curl();
		}

		return $this->io;
	}


	/**
	 * @param Io $io
	 * @return void
	 */
	public function setIo(Io $io)
	{
		$this->io = $io;
	}

	/**
	 * API *********************************************************************
	 */

	/**
	 * Take request and execute him
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function doRequest(Request $request)
	{
		$response = $this->getIo()->call($request);
		if ($response === false || $response->getError() !== null) {
			// cURL error
			throw new HttpException('Request failed');
		}

		if (isset($response->data['errors'])) {
			// GoPay errors
			$error = $response->data['errors'][0];
			throw new HttpException(HttpException::format($error), $error->error_code);
		}

		return $response;
	}

}
