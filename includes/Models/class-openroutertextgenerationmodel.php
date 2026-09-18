<?php
/**
 * Text-generation model for OpenRouter.
 *
 * @package Zactonz\AiConnectorForOpenRouter\Models
 */

declare( strict_types=1 );

namespace Zactonz\AiConnectorForOpenRouter\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zactonz\AiConnectorForOpenRouter\Models\Traits\OpenRouterRequestOptionsTrait;
use Zactonz\AiConnectorForOpenRouter\Provider\OpenRouterProfile;
use Zactonz\AiConnectorForOpenRouter\Provider\OpenRouterProvider;
use Zactonz\AiConnectorForOpenRouter\Settings\OpenRouterSettings;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;

/**
 * Generates text with the provider's OpenAI-compatible chat completions API.
 *
 * @since 1.0.0
 */
class OpenRouterTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {
	use OpenRouterRequestOptionsTrait;

	/**
	 * Prepares the structured output parameter.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $output_schema The output schema.
	 * @return array<string, mixed> The response format parameter.
	 */
	protected function prepareResponseFormatParam( ?array $output_schema ): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( is_array( $output_schema ) ) {
			return array(
				'type'        => 'json_schema',
				'json_schema' => array(
					'name'   => 'response_schema',
					'strict' => true,
					'schema' => $output_schema,
				),
			);
		}

		return array( 'type' => 'json_object' );
	}

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
		$options = $this->prepareRequestOptions( OpenRouterSettings::get_text_request_timeout(), 10.0 );

		if ( is_array( $data ) ) {
			$data = $this->stripTransportOptions( $data );
			$data = $this->applyReasoningPreference( $data );
		}

		return new Request(
			$method,
			OpenRouterProvider::url( OpenRouterSettings::decorate_path( $path, $this->metadata()->getId() ) ),
			$headers,
			$data,
			$options
		);
	}

	/**
	 * Applies the administrator's reasoning preference to a request payload.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data Request payload.
	 * @return array<string, mixed> Payload including the reasoning preference.
	 */
	protected function applyReasoningPreference( array $data ): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( ! OpenRouterProfile::supports( 'reasoning' ) ) {
			return $data;
		}

		if ( isset( $data['reasoning_effort'] ) || isset( $data['reasoning'] ) ) {
			return $data;
		}

		$model_id = isset( $data['model'] ) && is_string( $data['model'] ) ? $data['model'] : '';
		$effort   = OpenRouterSettings::get_reasoning_effort_for_model( $model_id );

		if ( in_array( $effort, array( 'none', 'minimal', 'low', 'medium', 'high' ), true ) ) {
			$data['reasoning_effort'] = $effort;
		}

		return $data;
	}

	/**
	 * Streams a text response and returns the aggregated result.
	 *
	 * The callback receives arrays with a type of content_delta, thinking_delta,
	 * tool_call_delta, or done. Returning false from the callback cancels the
	 * transfer and returns the partial result.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, Message> $prompt Prompt messages.
	 * @param callable            $on_event Receives each normalized stream event.
	 * @return GenerativeAiResult Aggregated result.
	 * @throws RuntimeException When streaming is unavailable or the transfer fails.
	 * @throws ResponseException When the provider returns an unsuccessful response.
	 */
	public function generateStreamResult( array $prompt, callable $on_event ): GenerativeAiResult { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( ! function_exists( 'curl_init' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is not rendered directly.
			throw new RuntimeException( 'The PHP cURL extension is required for streaming.' );
		}

		$params           = $this->stripTransportOptions( $this->prepareGenerateTextParams( $prompt ) );
		$params           = $this->applyReasoningPreference( $params );
		$params['stream'] = true;

		$url     = OpenRouterProvider::url( OpenRouterSettings::decorate_path( 'chat/completions', $this->metadata()->getId() ) );
		$headers = array( 'Content-Type: application/json', 'Accept: text/event-stream' );
		foreach ( OpenRouterSettings::get_request_headers() as $name => $value ) {
			$headers[] = $name . ': ' . $value;
		}

		$state       = array(
			'content'       => '',
			'thinking'      => '',
			'finish_reason' => null,
			'id'            => '',
			'usage'         => array(),
			'tool_calls'    => array(),
			'cancelled'     => false,
			'error'         => null,
		);
		$buffer      = '';
		$raw_body    = '';
		$status_code = 0;

		$process = function ( string $data ) use ( &$state, $on_event ): bool {
			if ( '[DONE]' === trim( $data ) ) {
				return true;
			}

			$chunk = json_decode( $data, true );
			if ( ! is_array( $chunk ) ) {
				return true;
			}
			if ( isset( $chunk['id'] ) && is_string( $chunk['id'] ) ) {
				$state['id'] = $chunk['id'];
			}
			if ( isset( $chunk['usage'] ) && is_array( $chunk['usage'] ) ) {
				$state['usage'] = $chunk['usage'];
			}

			$choice = isset( $chunk['choices'][0] ) && is_array( $chunk['choices'][0] ) ? $chunk['choices'][0] : array();
			$delta  = isset( $choice['delta'] ) && is_array( $choice['delta'] ) ? $choice['delta'] : array();
			$events = array();

			if ( isset( $delta['content'] ) && is_string( $delta['content'] ) && '' !== $delta['content'] ) {
				$state['content'] .= $delta['content'];
				$events[]          = array(
					'type'  => 'content_delta',
					'delta' => $delta['content'],
					'raw'   => $chunk,
				);
			}

			$thinking = '';
			foreach ( array( 'reasoning_content', 'reasoning' ) as $key ) {
				if ( isset( $delta[ $key ] ) && is_string( $delta[ $key ] ) && '' !== $delta[ $key ] ) {
					$thinking = $delta[ $key ];
					break;
				}
			}
			if ( '' !== $thinking ) {
				$state['thinking'] .= $thinking;
				$events[]           = array(
					'type'  => 'thinking_delta',
					'delta' => $thinking,
					'raw'   => $chunk,
				);
			}

			if ( isset( $delta['tool_calls'] ) && is_array( $delta['tool_calls'] ) ) {
				foreach ( $delta['tool_calls'] as $tool_delta ) {
					if ( ! is_array( $tool_delta ) ) {
						continue;
					}
					$index = isset( $tool_delta['index'] ) ? (int) $tool_delta['index'] : count( $state['tool_calls'] );
					if ( ! isset( $state['tool_calls'][ $index ] ) ) {
						$state['tool_calls'][ $index ] = array(
							'id'       => '',
							'type'     => 'function',
							'function' => array(
								'name'      => '',
								'arguments' => '',
							),
						);
					}
					if ( isset( $tool_delta['id'] ) && is_string( $tool_delta['id'] ) ) {
						$state['tool_calls'][ $index ]['id'] = $tool_delta['id'];
					}
					if ( isset( $tool_delta['function']['name'] ) && is_string( $tool_delta['function']['name'] ) ) {
						$state['tool_calls'][ $index ]['function']['name'] .= $tool_delta['function']['name'];
					}
					if ( isset( $tool_delta['function']['arguments'] ) && is_string( $tool_delta['function']['arguments'] ) ) {
						$state['tool_calls'][ $index ]['function']['arguments'] .= $tool_delta['function']['arguments'];
					}
					$events[] = array(
						'type'  => 'tool_call_delta',
						'index' => $index,
						'delta' => $tool_delta,
						'raw'   => $chunk,
					);
				}
			}

			if ( isset( $choice['finish_reason'] ) && is_string( $choice['finish_reason'] ) ) {
				$state['finish_reason'] = $choice['finish_reason'];
			}

			foreach ( $events as $event ) {
				try {
					if ( false === $on_event( $event ) ) {
						$state['cancelled'] = true;
						return false;
					}
				} catch ( \Throwable $e ) {
					$state['error'] = $e;
					return false;
				}
			}

			return true;
		};

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init -- WordPress HTTP does not expose chunk callbacks.
		$curl = curl_init( $url );
		if ( false === $curl ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is not rendered directly.
			throw new RuntimeException( 'Could not initialize cURL for streaming.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array -- Required for true incremental streaming.
		curl_setopt_array(
			$curl,
			array(
				CURLOPT_CONNECTTIMEOUT => 10,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_HTTPHEADER     => $headers,
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => wp_json_encode( $params ),
				CURLOPT_TIMEOUT        => (int) ceil( OpenRouterSettings::get_text_request_timeout() ),
				CURLOPT_WRITEFUNCTION  => function ( $handle, string $chunk ) use ( &$buffer, &$raw_body, &$state, $process ): int {
					unset( $handle );
					$raw_body .= $chunk;
					$buffer   .= str_replace( "\r\n", "\n", $chunk );
					while ( true ) {
						$separator = strpos( $buffer, "\n\n" );
						if ( false === $separator ) {
							break;
						}
						$block  = substr( $buffer, 0, $separator );
						$buffer = substr( $buffer, $separator + 2 );
						$lines  = array();
						foreach ( explode( "\n", $block ) as $line ) {
							if ( 0 === strpos( $line, 'data:' ) ) {
								$lines[] = ltrim( substr( $line, 5 ) );
							}
						}
						if ( ! empty( $lines ) && ! $process( implode( "\n", $lines ) ) ) {
							return 0;
						}
					}
					return ( $state['cancelled'] || null !== $state['error'] ) ? 0 : strlen( $chunk );
				},
				CURLOPT_HEADERFUNCTION => function ( $handle, string $header ) use ( &$status_code ): int {
					unset( $handle );
					if ( preg_match( '#^HTTP/\S+\s+(\d{3})#', $header, $matches ) ) {
						$status_code = (int) $matches[1];
					}
					return strlen( $header );
				},
			)
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec -- WordPress HTTP buffers responses and cannot stream chunks.
		$success = curl_exec( $curl );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error -- Paired with the streaming request above.
		$curl_error = curl_error( $curl );

		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is not rendered directly.
		if ( $state['error'] instanceof \Throwable ) {
			throw new RuntimeException( 'The stream callback failed: ' . $state['error']->getMessage(), 0, $state['error'] );
		}
		if ( false === $success && ! $state['cancelled'] ) {
			throw new RuntimeException( 'The streaming request failed: ' . $curl_error );
		}
		if ( ! $state['cancelled'] && ( $status_code < 200 || $status_code >= 300 ) ) {
			$decoded = json_decode( $raw_body, true );
			$message = is_array( $decoded ) && isset( $decoded['error']['message'] )
				? (string) $decoded['error']['message']
				: trim( $raw_body );

			throw ResponseException::fromInvalidData(
				$this->providerMetadata()->getName(),
				'stream',
				'' !== $message ? $message : 'Unexpected HTTP status ' . $status_code . '.'
			);
		}
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped

		if ( '' !== trim( $buffer ) ) {
			$trailing = preg_replace( '/^data:\s*/m', '', trim( $buffer ) );
			if ( is_string( $trailing ) ) {
				$process( $trailing );
			}
		}

		ksort( $state['tool_calls'] );
		$message = array(
			'role'    => 'assistant',
			'content' => $state['content'],
		);
		if ( '' !== $state['thinking'] ) {
			$message['reasoning_content'] = $state['thinking'];
		}
		if ( ! empty( $state['tool_calls'] ) ) {
			$message['tool_calls'] = array_values( $state['tool_calls'] );
		}

		$finish_reason = null !== $state['finish_reason']
			? $state['finish_reason']
			: ( ! empty( $state['tool_calls'] ) ? 'tool_calls' : 'stop' );

		$result = $this->parseResponseToGenerativeAiResult(
			new Response(
				200,
				array( 'Content-Type' => 'application/json' ),
				(string) wp_json_encode(
					array(
						'id'        => $state['id'],
						'choices'   => array(
							array(
								'message'       => $message,
								'finish_reason' => $finish_reason,
							),
						),
						'usage'     => $state['usage'],
						'cancelled' => $state['cancelled'],
					)
				)
			)
		);

		$on_event(
			array(
				'type'         => 'done',
				'cancelled'    => $state['cancelled'],
				'finishReason' => $finish_reason,
				'usage'        => $state['usage'],
			)
		);

		return $result;
	}
}
