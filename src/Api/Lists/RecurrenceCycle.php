<?php

declare(strict_types=1);

namespace Contributte\GopayInline\Api\Lists;

class RecurrenceCycle
{

	// Daily recurring
	const DAY = 'DAY';

	// Weekly recurring
	const WEEK = 'WEEK';

	// Monthly recurring
	const MONTH = 'MONTH';

	// Set only at manual recurring payments
	const ON_DEMAND = 'ON_DEMAND';

}
