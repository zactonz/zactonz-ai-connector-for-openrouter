<?php
/**
 * Plugin bootstrap.
 *
 * @package Zactonz\AiConnectorForOpenRouter
 */

declare( strict_types=1 );

namespace Zactonz\AiConnectorForOpenRouter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zactonz\AiConnectorForOpenRouter\Diagnostics\OpenRouterSiteHealth;
use Zactonz\AiConnectorForOpenRouter\Http\OpenRouterRequestAuthentication;
use Zactonz\AiConnectorForOpenRouter\Provider\OpenRouterProfile;
use Zactonz\AiConnectorForOpenRouter\Provider\OpenRouterProvider;
use Zactonz\AiConnectorForOpenRouter\Settings\OpenRouterSettings;
use WordPress\AiClient\AiClient;

/**
 * Wires the connector into WordPress.
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * Registers hooks.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_provider' ), 5 );
		add_action( 'init', array( $this, 'register_authentication' ), 21 );
		add_action( 'init', array( $this, 'initialize_settings' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( ZCTZ_OPENROUTER_PLUGIN_FILE ),
			array( $this, 'plugin_action_links' )
		);

		( new OpenRouterSiteHealth() )->init();
	}

	/**
	 * Registers the provider with the AI Client.
	 *
	 * @since 1.0.0
	 */
	public function register_provider(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();

		if ( $registry->hasProvider( OpenRouterProfile::id() ) ) {
			return;
		}

		$registry->registerProvider( OpenRouterProvider::class );
	}

	/**
	 * Applies the stored credential after core has wired the Connectors screen.
	 *
	 * Core wires Connector credentials at init priority 20, so this runs later
	 * and keeps the connector's own stored key authoritative.
	 *
	 * @since 1.0.0
	 */
	public function register_authentication(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( OpenRouterProfile::id() ) ) {
			return;
		}

		$registry->setProviderRequestAuthentication(
			OpenRouterProfile::id(),
			new OpenRouterRequestAuthentication( OpenRouterSettings::get_api_key() )
		);
	}

	/**
	 * Initializes the settings screen.
	 *
	 * @since 1.0.0
	 */
	public function initialize_settings(): void {
		( new OpenRouterSettings() )->init();
	}

	/**
	 * Adds a settings link to the plugin list table.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string> $links Existing action links.
	 * @return array<string> Action links including the settings link.
	 */
	public function plugin_action_links( array $links ): array {
		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				admin_url( 'options-general.php?page=zactonz-ai-connector-openrouter' ),
				esc_html__( 'Settings', 'zactonz-ai-connector-openrouter' )
			)
		);

		return $links;
	}
}
