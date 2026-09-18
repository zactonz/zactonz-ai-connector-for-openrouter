<?php
/**
 * Provider registration for OpenRouter with the WordPress AI Client.
 *
 * @package Zactonz\AiConnectorForOpenRouter\Provider
 */

declare( strict_types=1 );

namespace Zactonz\AiConnectorForOpenRouter\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zactonz\AiConnectorForOpenRouter\Metadata\OpenRouterModelMetadataDirectory;
use Zactonz\AiConnectorForOpenRouter\Models\OpenRouterEmbeddingGenerationModel;
use Zactonz\AiConnectorForOpenRouter\Models\OpenRouterImageGenerationModel;
use Zactonz\AiConnectorForOpenRouter\Models\OpenRouterTextGenerationModel;
use Zactonz\AiConnectorForOpenRouter\Settings\OpenRouterSettings;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Class for the OpenRouter provider.
 *
 * @since 1.0.0
 */
class OpenRouterProvider extends AbstractApiProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function baseUrl(): string {
		return OpenRouterSettings::get_base_url();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param ModelMetadata    $model_metadata Model metadata.
	 * @param ProviderMetadata $provider_metadata Provider metadata.
	 * @return ModelInterface The concrete model instance.
	 * @throws RuntimeException When the model capabilities are unsupported.
	 */
	protected static function createModel(
		ModelMetadata $model_metadata,
		ProviderMetadata $provider_metadata
	): ModelInterface {
		$capabilities = $model_metadata->toArray()[ ModelMetadata::KEY_SUPPORTED_CAPABILITIES ];

		if ( in_array( 'embedding_generation', $capabilities, true ) ) {
			if ( ! interface_exists( 'WordPress\\AiClient\\Providers\\Models\\EmbeddingGeneration\\Contracts\\EmbeddingGenerationModelInterface' ) ) {
				throw new RuntimeException(
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
					'This WordPress release does not provide the AI Client embedding model contract.'
				);
			}

			return new OpenRouterEmbeddingGenerationModel( $model_metadata, $provider_metadata );
		}

		if ( in_array( 'image_generation', $capabilities, true ) ) {
			return new OpenRouterImageGenerationModel( $model_metadata, $provider_metadata );
		}

		if ( in_array( 'text_generation', $capabilities, true ) ) {
			return new OpenRouterTextGenerationModel( $model_metadata, $provider_metadata );
		}

		throw new RuntimeException(
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
			'Unsupported model capabilities: ' . implode( ', ', $capabilities )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		$provider_meta = array(
			OpenRouterProfile::id(),
			OpenRouterProfile::name(),
			ProviderTypeEnum::cloud(),
			OpenRouterProfile::api_key_url(),
			RequestAuthenticationMethod::apiKey(),
		);

		if ( version_compare( AiClient::VERSION, '1.2.0', '>=' ) ) {
			$provider_meta[] = function_exists( '__' )
				? __( 'Text generation, vision, and tool calling through OpenRouter, a single gateway in front of hundreds of models.', 'zactonz-ai-connector-openrouter' )
				: 'Text generation, vision, and tool calling through OpenRouter, a single gateway in front of hundreds of models.';
		}

		if ( version_compare( AiClient::VERSION, '1.3.0', '>=' ) ) {
			$provider_meta[] = defined( 'ZCTZ_OPENROUTER_PLUGIN_DIR' )
				? ZCTZ_OPENROUTER_PLUGIN_DIR . 'includes/Provider/logo.svg'
				: dirname( __DIR__, 2 ) . '/includes/Provider/logo.svg';
		}

		return new ProviderMetadata( ...$provider_meta );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		return new OpenRouterProviderAvailability();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new OpenRouterModelMetadataDirectory();
	}
}
