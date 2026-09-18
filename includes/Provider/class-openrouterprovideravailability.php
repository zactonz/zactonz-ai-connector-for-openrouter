<?php
/**
 * Availability check for the OpenRouter connector.
 *
 * @package Zactonz\AiConnectorForOpenRouter\Provider
 */

declare( strict_types=1 );

namespace Zactonz\AiConnectorForOpenRouter\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zactonz\AiConnectorForOpenRouter\Settings\OpenRouterSettings;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;

/**
 * Reports whether the connector holds usable credentials.
 *
 * @since 1.0.0
 */
class OpenRouterProviderAvailability implements ProviderAvailabilityInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function isConfigured(): bool {
		return OpenRouterSettings::has_credentials();
	}
}
