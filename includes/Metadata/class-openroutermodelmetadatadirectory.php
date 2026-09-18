<?php
/**
 * Model discovery for OpenRouter.
 *
 * @package Zactonz\AiConnectorForOpenRouter\Metadata
 */

declare( strict_types=1 );

namespace Zactonz\AiConnectorForOpenRouter\Metadata;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zactonz\AiConnectorForOpenRouter\Provider\OpenRouterProfile;
use Zactonz\AiConnectorForOpenRouter\Provider\OpenRouterProvider;
use Zactonz\AiConnectorForOpenRouter\Settings\OpenRouterSettings;
use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModelMetadataDirectory;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

/**
 * Discovers the models the configured credential can reach.
 *
 * The provider's own model listing is the source of truth. Capabilities are
 * read from whatever the provider reports and are only guessed from the model
 * identifier when the listing says nothing, which keeps the connector honest
 * about what each model can actually do.
 *
 * @since 1.0.0
 */
class OpenRouterModelMetadataDirectory extends AbstractApiBasedModelMetadataDirectory {

	/**
	 * Raw model entries from the last listing, keyed by model ID.
	 *
	 * @since 1.0.0
	 * @var array<string, array<string, mixed>>
	 */
	private $model_entries = array();

	/**
	 * Returns an administration-friendly descriptor for one model.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_id Model identifier.
	 * @return array<string, mixed> Model descriptor.
	 */
	public function getModelDescriptor( string $model_id ): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( empty( $this->model_entries ) ) {
			try {
				$this->listModelMetadata();
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}

		$entry    = isset( $this->model_entries[ $model_id ] ) ? $this->model_entries[ $model_id ] : array();
		$entry    = $this->normalize_entry( $entry );
		$features = $this->detect_features( $model_id, $entry );

		return array(
			'id'            => $model_id,
			'label'         => $this->extract_label( $model_id, $entry ),
			'features'      => $features,
			'contextLength' => $this->extract_context_length( $entry ),
			'ownedBy'       => isset( $entry['owned_by'] ) && is_string( $entry['owned_by'] ) ? $entry['owned_by'] : '',
		);
	}

	/**
	 * Returns the feature names reported for one model.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_id Model identifier.
	 * @return list<string> Feature names.
	 */
	public function getModelCapabilities( string $model_id ): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$descriptor = $this->getModelDescriptor( $model_id );
		$features   = isset( $descriptor['features'] ) && is_array( $descriptor['features'] ) ? $descriptor['features'] : array();

		return array_keys( array_filter( $features ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, ModelMetadata> Discovered model metadata.
	 * @throws ResponseException When the provider response cannot be read.
	 */
	protected function sendListModelsRequest(): array {
		$entries = $this->fetch_model_entries( OpenRouterProfile::models_path() );

		if ( empty( $entries ) && '' !== OpenRouterProfile::models_fallback_path() ) {
			$entries = $this->fetch_model_entries( OpenRouterProfile::models_fallback_path() );
		}

		$this->model_entries = array();
		$models_map          = array();

		foreach ( $entries as $entry ) {
			$model_id = $this->extract_model_id( $entry );
			if ( '' === $model_id || ! $this->is_usable( $entry ) ) {
				continue;
			}

			$metadata = $this->build_model_metadata( $model_id, $entry );
			if ( null === $metadata ) {
				continue;
			}

			$this->model_entries[ $model_id ] = $entry;
			$models_map[ $model_id ]          = $metadata;
		}

		return $this->sort_models_map( $models_map );
	}

	/**
	 * Reports whether the provider considers one listed model usable.
	 *
	 * Providers that publish deployment or lifecycle status use it to mark
	 * entries that exist but cannot serve traffic.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $entry Raw model entry.
	 * @return bool True when the entry can serve requests.
	 */
	private function is_usable( array $entry ): bool {
		$healthy  = array( 'succeeded', 'active', 'live', 'ready', 'available', 'online' );
		$statuses = array();

		if ( isset( $entry['status'] ) && is_string( $entry['status'] ) ) {
			$statuses[] = $entry['status'];
		}
		if ( isset( $entry['modelLifecycle']['status'] ) && is_string( $entry['modelLifecycle']['status'] ) ) {
			$statuses[] = $entry['modelLifecycle']['status'];
		}

		foreach ( $statuses as $status ) {
			if ( ! in_array( strtolower( $status ), $healthy, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Requests one model listing endpoint and returns its entries.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Endpoint path relative to the base URL.
	 * @return list<array<string, mixed>> Raw model entries.
	 * @throws ResponseException When the response has no recognizable model list.
	 */
	private function fetch_model_entries( string $path ): array {
		$request  = $this->create_request( HttpMethodEnum::GET(), $path );
		$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response = $this->getHttpTransporter()->send( $request );

		ResponseUtil::throwIfNotSuccessful( $response );

		return $this->extract_entries( $response );
	}

	/**
	 * Reads the model entries out of a listing response.
	 *
	 * @since 1.0.0
	 *
	 * @param Response $response Listing response.
	 * @return list<array<string, mixed>> Raw model entries.
	 * @throws ResponseException When no model list is present.
	 */
	private function extract_entries( Response $response ): array {
		$data = $response->getData();
		if ( ! is_array( $data ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Provider exception data is not output directly.
			throw ResponseException::fromMissingData( OpenRouterProfile::name(), 'data' );
		}

		foreach ( array( 'data', 'models', 'modelSummaries' ) as $key ) {
			if ( isset( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
				return array_values( array_filter( $data[ $key ], 'is_array' ) );
			}
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Provider exception data is not output directly.
		throw ResponseException::fromMissingData( OpenRouterProfile::name(), 'data' );
	}

	/**
	 * Reads the model identifier out of one listing entry.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $entry Raw model entry.
	 * @return string Model identifier, or an empty string.
	 */
	private function extract_model_id( array $entry ): string {
		foreach ( array( 'id', 'model', 'name', 'modelId' ) as $key ) {
			if ( isset( $entry[ $key ] ) && is_string( $entry[ $key ] ) && '' !== $entry[ $key ] ) {
				return $entry[ $key ];
			}
		}

		return '';
	}

	/**
	 * Reads a human readable label for one model.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $model_id Model identifier.
	 * @param array<string, mixed> $entry Raw model entry.
	 * @return string Model label.
	 */
	private function extract_label( string $model_id, array $entry ): string {
		foreach ( array( 'name', 'display_name', 'modelName' ) as $key ) {
			if ( isset( $entry[ $key ] ) && is_string( $entry[ $key ] ) && '' !== $entry[ $key ] ) {
				return $entry[ $key ];
			}
		}

		return $model_id;
	}

	/**
	 * Reads the context window size out of one listing entry.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $entry Raw model entry.
	 * @return int|null Context window size when reported.
	 */
	private function extract_context_length( array $entry ): ?int {
		foreach ( array( 'context_length', 'context_window', 'max_context_length', 'max_model_len', 'contextLength' ) as $key ) {
			if ( isset( $entry[ $key ] ) && is_numeric( $entry[ $key ] ) ) {
				return (int) $entry[ $key ];
			}
		}

		if ( isset( $entry['top_provider']['context_length'] ) && is_numeric( $entry['top_provider']['context_length'] ) ) {
			return (int) $entry['top_provider']['context_length'];
		}

		return null;
	}

	/**
	 * Reads a list of modality names out of one listing entry.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $entry Raw model entry.
	 * @param string               $direction Either input or output.
	 * @return list<string> Lowercase modality names.
	 */
	private function extract_modalities( array $entry, string $direction ): array {
		$keys       = array( $direction . '_modalities', $direction . 'Modalities' );
		$candidates = array();

		foreach ( $keys as $key ) {
			if ( isset( $entry['architecture'][ $key ] ) && is_array( $entry['architecture'][ $key ] ) ) {
				$candidates = $entry['architecture'][ $key ];
				break;
			}
			if ( isset( $entry[ $key ] ) && is_array( $entry[ $key ] ) ) {
				$candidates = $entry[ $key ];
				break;
			}
		}

		if ( array() === $candidates && isset( $entry['architecture']['modality'] ) && is_string( $entry['architecture']['modality'] ) ) {
			$parts      = explode( '->', $entry['architecture']['modality'] );
			$index      = 'input' === $direction ? 0 : 1;
			$candidates = isset( $parts[ $index ] ) ? explode( '+', $parts[ $index ] ) : array();
		}

		$modalities = array();
		foreach ( $candidates as $candidate ) {
			if ( is_string( $candidate ) && '' !== $candidate ) {
				$modalities[] = strtolower( trim( $candidate ) );
			}
		}

		return array_values( array_unique( $modalities ) );
	}

	/**
	 * Reads the list of supported request parameters out of one listing entry.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $entry Raw model entry.
	 * @return list<string> Parameter names.
	 */
	private function extract_supported_parameters( array $entry ): array {
		if ( ! isset( $entry['supported_parameters'] ) || ! is_array( $entry['supported_parameters'] ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$entry['supported_parameters'],
				static function ( $parameter ): bool {
					return is_string( $parameter ) && '' !== $parameter;
				}
			)
		);
	}

	/**
	 * Merges routed provider details into a listing entry.
	 *
	 * Some providers put the facts about a model inside a nested list of the
	 * inference partners that serve it. The first entry that is live wins.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $entry Raw model entry.
	 * @return array<string, mixed> Entry with routed details merged in.
	 */
	private function normalize_entry( array $entry ): array {
		if ( ! isset( $entry['providers'] ) || ! is_array( $entry['providers'] ) ) {
			return $entry;
		}

		foreach ( $entry['providers'] as $routed ) {
			if ( ! is_array( $routed ) ) {
				continue;
			}
			if ( isset( $routed['status'] ) && 'live' !== $routed['status'] ) {
				continue;
			}

			if ( ! isset( $entry['context_length'] ) && isset( $routed['context_length'] ) ) {
				$entry['context_length'] = $routed['context_length'];
			}
			if ( isset( $routed['supports_tools'] ) ) {
				$entry['capabilities']['function_calling'] = (bool) $routed['supports_tools'];
			}
			if ( isset( $routed['supports_structured_output'] ) ) {
				$entry['capabilities']['structured_output'] = (bool) $routed['supports_structured_output'];
			}

			break;
		}

		return $entry;
	}

	/**
	 * Returns the text used for identifier heuristics.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $model_id Model identifier.
	 * @param array<string, mixed> $entry Raw model entry.
	 * @return string Lowercase identifier text.
	 */
	private function identifier_text( string $model_id, array $entry ): string {
		$identifier = strtolower( $model_id );

		foreach ( array( 'model', 'modelName', 'name' ) as $key ) {
			if ( isset( $entry[ $key ] ) && is_string( $entry[ $key ] ) ) {
				$identifier .= ' ' . strtolower( $entry[ $key ] );
			}
		}

		return $identifier;
	}

	/**
	 * Reports whether a model serves a task the AI Client cannot represent.
	 *
	 * @since 1.0.0
	 *
	 * @param string $identifier Identifier text.
	 * @return bool True when the model should be skipped entirely.
	 */
	private function is_unsupported_task( string $identifier ): bool {
		return 1 === preg_match(
			'/whisper|transcribe|(^|[-_\/])tts(-|$)|text-to-speech|speech-to-text|guard|moderation|rerank|-ocr(-|$)|safety/',
			$identifier
		);
	}

	/**
	 * Determines what one model can do.
	 *
	 * Provider-reported data always wins. Identifier heuristics are used only
	 * for the listing shapes that carry no capability data at all, so the
	 * connector never claims a capability the provider has denied.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $model_id Model identifier.
	 * @param array<string, mixed> $entry Raw model entry.
	 * @return array<string, bool> Feature flags.
	 */
	private function detect_features( string $model_id, array $entry ): array {
		$entry            = $this->normalize_entry( $entry );
		$reported         = isset( $entry['capabilities'] ) && is_array( $entry['capabilities'] ) ? $entry['capabilities'] : array();
		$parameters       = $this->extract_supported_parameters( $entry );
		$input_modalities = $this->extract_modalities( $entry, 'input' );
		$output_modality  = $this->extract_modalities( $entry, 'output' );
		$identifier       = $this->identifier_text( $model_id, $entry );
		$no_parameters    = array() === $parameters;

		$is_embedding = OpenRouterProfile::supports( 'embedding' ) && (
			! empty( $reported['embeddings'] ) ||
			in_array( 'embedding', $output_modality, true ) ||
			( isset( $reported['completion_chat'] ) && ! $reported['completion_chat'] && 1 === preg_match( '/embed/', $identifier ) ) ||
			( array() === $reported && 1 === preg_match( '/embed|(^|[-_\/])bge|(^|[-_\/])gte-|(^|[-_\/])e5-|minilm|text-similarity/', $identifier ) )
		);

		$is_image = OpenRouterProfile::supports( 'image' ) && (
			in_array( 'image', $output_modality, true ) ||
			(
				array() === $output_modality &&
				1 === preg_match( '/(^|[-_\/])(dall-e|dalle|image|imagen|flux|stable-diffusion|sdxl|canvas|titan-image)/', $identifier )
			)
		);

		if ( $is_image ) {
			$is_embedding = false;
		}

		$is_text = ! $is_embedding && ! $is_image && ! $this->is_unsupported_task( $identifier );
		if ( isset( $reported['completion_chat'] ) ) {
			$is_text = (bool) $reported['completion_chat'] && ! $is_embedding && ! $is_image;
		}

		$has_vision = $is_text && OpenRouterProfile::supports( 'vision' ) && (
			in_array( 'image', $input_modalities, true ) ||
			! empty( $reported['vision'] ) ||
			(
				array() === $input_modalities &&
				! isset( $reported['vision'] ) &&
				1 === preg_match( '/vision|llava|pixtral|multimodal|(^|[-_\/])vl(-|$)|-vl-|llama-4|scout|maverick|gemma-3|gpt-4o|omni/', $identifier )
			)
		);

		$has_tools = $is_text && OpenRouterProfile::supports( 'tools' ) && (
			in_array( 'tools', $parameters, true ) ||
			in_array( 'tool_choice', $parameters, true ) ||
			! empty( $reported['function_calling'] ) ||
			( $no_parameters && ! isset( $reported['function_calling'] ) )
		);

		$has_structured = $is_text && OpenRouterProfile::supports( 'structured' ) && (
			in_array( 'structured_outputs', $parameters, true ) ||
			in_array( 'response_format', $parameters, true ) ||
			! empty( $reported['structured_output'] ) ||
			! empty( $reported['json_mode'] ) ||
			( $no_parameters && ! isset( $reported['structured_output'] ) && ! isset( $reported['json_mode'] ) )
		);

		$reasoning_pattern = (string) OpenRouterProfile::get( 'reasoning_pattern', '' );
		$has_reasoning     = $is_text && OpenRouterProfile::supports( 'reasoning' ) && (
			in_array( 'reasoning', $parameters, true ) ||
			in_array( 'include_reasoning', $parameters, true ) ||
			in_array( 'reasoning_effort', $parameters, true ) ||
			! empty( $reported['reasoning'] ) ||
			1 === preg_match( '/reason|thinking|(^|[-_\/])r1(-|$)|(^|[-_\/])o[13](-|$)|qwq/', $identifier ) ||
			( '' !== $reasoning_pattern && 1 === preg_match( '/' . $reasoning_pattern . '/', $identifier ) )
		);

		return array(
			'text'       => $is_text,
			'vision'     => $has_vision,
			'tools'      => $has_tools,
			'structured' => $has_structured,
			'reasoning'  => $has_reasoning,
			'embedding'  => $is_embedding,
			'image'      => $is_image,
		);
	}

	/**
	 * Reports whether the installed AI Client can represent an embedding model.
	 *
	 * WordPress 7.1 defines the embedding capability but does not yet ship the
	 * embedding model contract, so the capability constant alone is not enough
	 * to decide. Offering an embedding model without the contract would be a
	 * fatal error the moment the model was used.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True when embedding models can be offered.
	 */
	private static function supports_embedding_models(): bool {
		return defined( CapabilityEnum::class . '::EMBEDDING_GENERATION' )
			&& interface_exists( 'WordPress\\AiClient\\Providers\\Models\\EmbeddingGeneration\\Contracts\\EmbeddingGenerationModelInterface' )
			&& class_exists( 'WordPress\\AiClient\\Results\\DTO\\EmbeddingResult' );
	}

	/**
	 * Builds the AI Client metadata for one model.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $model_id Model identifier.
	 * @param array<string, mixed> $entry Raw model entry.
	 * @return ModelMetadata|null Metadata, or null when the model cannot be represented.
	 */
	private function build_model_metadata( string $model_id, array $entry ): ?ModelMetadata {
		$features = $this->detect_features( $model_id, $entry );
		$label    = $this->extract_label( $model_id, $entry );

		if ( $features['embedding'] ) {
			if ( ! self::supports_embedding_models() ) {
				return null;
			}

			$options = array(
				new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
				new SupportedOption( OptionEnum::customOptions() ),
			);
			if ( defined( ModelConfig::class . '::KEY_DIMENSIONS' ) ) {
				$options[] = new SupportedOption( OptionEnum::dimensions() );
			}

			return new ModelMetadata(
				$model_id,
				$label,
				array( CapabilityEnum::embeddingGeneration() ),
				$options
			);
		}

		if ( $features['image'] ) {
			return new ModelMetadata(
				$model_id,
				$label,
				array( CapabilityEnum::imageGeneration() ),
				array(
					new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
					new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::image() ) ) ),
					new SupportedOption( OptionEnum::candidateCount() ),
					new SupportedOption( OptionEnum::outputMimeType(), array( 'image/png' ) ),
					new SupportedOption( OptionEnum::outputFileType(), array( FileTypeEnum::inline() ) ),
					new SupportedOption( OptionEnum::customOptions() ),
				)
			);
		}

		if ( ! $features['text'] ) {
			return null;
		}

		$input_modalities = $features['vision']
			? array( array( ModalityEnum::text() ), array( ModalityEnum::text(), ModalityEnum::image() ) )
			: array( array( ModalityEnum::text() ) );

		$options = array(
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			new SupportedOption( OptionEnum::frequencyPenalty() ),
			new SupportedOption( OptionEnum::presencePenalty() ),
			new SupportedOption( OptionEnum::customOptions() ),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
			new SupportedOption( OptionEnum::inputModalities(), $input_modalities ),
		);

		if ( $features['structured'] ) {
			$options[] = new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain', 'application/json' ) );
			$options[] = new SupportedOption( OptionEnum::outputSchema() );
		} else {
			$options[] = new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain' ) );
		}

		if ( $features['tools'] ) {
			$options[] = new SupportedOption( OptionEnum::functionDeclarations() );
		}

		return new ModelMetadata(
			$model_id,
			$label,
			array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
			$options
		);
	}

	/**
	 * Sorts the discovered models and moves the configured default first.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, ModelMetadata> $models_map Models keyed by identifier.
	 * @return array<string, ModelMetadata> Sorted models.
	 */
	private function sort_models_map( array $models_map ): array {
		ksort( $models_map );

		$preferred = OpenRouterSettings::get_preferred_model( 'text' );
		if ( '' !== $preferred && isset( $models_map[ $preferred ] ) ) {
			$models_map = array( $preferred => $models_map[ $preferred ] ) + array_diff_key(
				$models_map,
				array( $preferred => true )
			);
		}

		return $models_map;
	}

	/**
	 * Creates a request against the provider API.
	 *
	 * @since 1.0.0
	 *
	 * @param HttpMethodEnum                     $method HTTP method.
	 * @param string                             $path Endpoint path relative to the base URL.
	 * @param array<string, string|list<string>> $headers Request headers.
	 * @param string|array<string, mixed>|null   $data Request body data.
	 * @return Request The request object.
	 */
	private function create_request( HttpMethodEnum $method, string $path, array $headers = array(), $data = null ): Request {
		$options = new RequestOptions();
		$options->setTimeout( 20.0 );
		$options->setConnectTimeout( 10.0 );

		$headers['Accept'] = 'application/json';

		return new Request(
			$method,
			OpenRouterProvider::url( OpenRouterSettings::decorate_path( $path ) ),
			$headers,
			$data,
			$options
		);
	}
}
