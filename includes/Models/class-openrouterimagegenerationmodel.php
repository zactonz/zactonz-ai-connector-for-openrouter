<?php
/**
 * Image-generation model for OpenRouter.
 *
 * @package Zactonz\AiConnectorForOpenRouter\Models
 */

declare( strict_types=1 );

namespace Zactonz\AiConnectorForOpenRouter\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zactonz\AiConnectorForOpenRouter\Models\Traits\OpenRouterRequestOptionsTrait;
use Zactonz\AiConnectorForOpenRouter\Provider\OpenRouterProvider;
use Zactonz\AiConnectorForOpenRouter\Settings\OpenRouterSettings;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleImageGenerationModel;

/**
 * Generates images with the provider's OpenAI-compatible images API.
 *
 * @since 1.0.0
 */
class OpenRouterImageGenerationModel extends AbstractOpenAiCompatibleImageGenerationModel {
	use OpenRouterRequestOptionsTrait;

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param HttpMethodEnum                     $method HTTP method.
	 * @param string                             $path Endpoint path.
	 * @param array<string, string|list<string>> $headers Request headers.
	 * @param mixed                              $data Request body data.
	 * @return Request The prepared request.
	 */
	protected function createRequest( // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		HttpMethodEnum $method,
		string $path,
		array $headers = array(),
		$data = null
	): Request {
		$options = $this->prepareRequestOptions( OpenRouterSettings::get_image_request_timeout(), 10.0 );

		if ( is_array( $data ) ) {
			$data = $this->stripTransportOptions( $data );
		}

		return new Request(
			$method,
			OpenRouterProvider::url( OpenRouterSettings::decorate_path( $path, $this->metadata()->getId() ) ),
			$headers,
			$data,
			$options
		);
	}
}
