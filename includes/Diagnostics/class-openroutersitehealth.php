<?php
/**
 * WordPress Site Health integration.
 *
 * @package Zactonz\AiConnectorForOpenRouter\Diagnostics
 */

declare( strict_types=1 );

namespace Zactonz\AiConnectorForOpenRouter\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zactonz\AiConnectorForOpenRouter\Provider\OpenRouterProfile;
use Zactonz\AiConnectorForOpenRouter\Settings\OpenRouterSettings;

/**
 * Registers a status test and redacted debug information.
 *
 * @since 1.0.0
 */
class OpenRouterSiteHealth {

	/**
	 * Registers hooks.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_filter( 'site_status_tests', array( $this, 'register_tests' ) );
		add_filter( 'debug_information', array( $this, 'add_debug_information' ) );
	}

	/**
	 * Adds the connection test.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $tests Existing tests.
	 * @return array<string, mixed> Tests including this connector.
	 */
	public function register_tests( array $tests ): array {
		if ( ! OpenRouterSettings::has_credentials() ) {
			return $tests;
		}

		$tests['direct']['zctz_openrouter_connection'] = array(
			'label' => sprintf(
				/* translators: %s: Provider name. */
				__( '%s connection', 'zactonz-ai-provider-openrouter' ),
				OpenRouterProfile::name()
			),
			'test'  => array( $this, 'test_connection' ),
		);

		return $tests;
	}

	/**
	 * Runs the connection test.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Site Health test result.
	 */
	public function test_connection(): array {
		$report    = ( new OpenRouterDiagnostics() )->run();
		$connected = ! empty( $report['connected'] );

		return array(
			'label'       => $connected
				? sprintf(
					/* translators: %s: Provider name. */
					__( '%s is connected', 'zactonz-ai-provider-openrouter' ),
					OpenRouterProfile::name()
				)
				: sprintf(
					/* translators: %s: Provider name. */
					__( '%s is not reachable', 'zactonz-ai-provider-openrouter' ),
					OpenRouterProfile::name()
				),
			'status'      => $connected ? 'good' : 'recommended',
			'badge'       => array(
				'label' => __( 'AI', 'zactonz-ai-provider-openrouter' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html(
				$connected
					? sprintf(
						/* translators: 1: Number of models, 2: Request latency in milliseconds. */
						__( 'The connector discovered %1$d models in %2$d ms.', 'zactonz-ai-provider-openrouter' ),
						(int) $report['modelCount'],
						(int) $report['latencyMs']
					)
					: (string) $report['error']
			) . '</p>',
			'actions'     => sprintf(
				'<p><a href="%1$s">%2$s</a></p>',
				esc_url( admin_url( 'options-general.php?page=zactonz-ai-provider-openrouter' ) ),
				esc_html(
					sprintf(
						/* translators: %s: Provider name. */
						__( 'Review %s settings', 'zactonz-ai-provider-openrouter' ),
						OpenRouterProfile::name()
					)
				)
			),
			'test'        => 'zctz_openrouter_connection',
		);
	}

	/**
	 * Adds non-secret details to Site Health information.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $info Existing debug information.
	 * @return array<string, mixed> Debug information including this connector.
	 */
	public function add_debug_information( array $info ): array {
		$fields = array(
			'endpoint'    => array(
				'label' => __( 'Endpoint', 'zactonz-ai-provider-openrouter' ),
				'value' => OpenRouterSettings::get_base_url(),
			),
			'credentials' => array(
				'label' => __( 'Credentials stored', 'zactonz-ai-provider-openrouter' ),
				'value' => OpenRouterSettings::has_credentials()
					? __( 'Yes', 'zactonz-ai-provider-openrouter' )
					: __( 'No', 'zactonz-ai-provider-openrouter' ),
			),
			'timeout'     => array(
				'label' => __( 'Text timeout', 'zactonz-ai-provider-openrouter' ),
				'value' => OpenRouterSettings::get_text_request_timeout() . 's',
			),
		);

		foreach ( OpenRouterSettings::get_preferred_models() as $capability => $model_id ) {
			$fields[ 'default_' . $capability ] = array(
				'label' => sprintf(
					/* translators: %s: Capability name. */
					__( 'Default %s model', 'zactonz-ai-provider-openrouter' ),
					$capability
				),
				'value' => '' !== $model_id ? $model_id : __( 'Automatic', 'zactonz-ai-provider-openrouter' ),
			);
		}

		$info['zctz_openrouter'] = array(
			'label'  => 'Zactonz AI Connector: OpenRouter',
			'fields' => $fields,
		);

		return $info;
	}
}
