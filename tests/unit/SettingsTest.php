<?php

declare( strict_types=1 );

namespace Zactonz\AiConnectorForOpenRouter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zactonz\AiConnectorForOpenRouter\Provider\OpenRouterProfile;
use Zactonz\AiConnectorForOpenRouter\Settings\OpenRouterSettings;

class SettingsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['zctz_test_options']         = array();
		$GLOBALS['zctz_test_settings_errors'] = array();
		$GLOBALS['zctz_test_http_responses']  = array();
		$GLOBALS['zctz_test_http_requests']   = array();
	}

	public function test_the_api_key_is_stored_outside_the_settings_array(): void {
		$settings  = new OpenRouterSettings();
		$sanitized = $settings->sanitize_settings( array( 'api_key' => 'secret-key' ) );

		$this->assertArrayNotHasKey( 'api_key', $sanitized );
		$this->assertSame( 'secret-key', OpenRouterSettings::get_saved_api_key() );
		$this->assertSame( 'secret-key', OpenRouterSettings::get_api_key() );
	}

	public function test_an_empty_api_key_keeps_the_stored_one(): void {
		$settings = new OpenRouterSettings();
		$settings->sanitize_settings( array( 'api_key' => 'secret-key' ) );
		$settings->sanitize_settings( array( 'api_key' => '' ) );

		$this->assertSame( 'secret-key', OpenRouterSettings::get_saved_api_key() );
	}

	public function test_the_key_can_be_cleared_on_request(): void {
		$settings = new OpenRouterSettings();
		$settings->sanitize_settings( array( 'api_key' => 'secret-key' ) );
		$settings->sanitize_settings( array( 'clear_api_key' => '1' ) );

		$this->assertSame( '', OpenRouterSettings::get_saved_api_key() );
		$this->assertFalse( OpenRouterSettings::has_credentials() );
	}

	public function test_an_environment_variable_overrides_the_stored_key(): void {
		$settings = new OpenRouterSettings();
		$settings->sanitize_settings( array( 'api_key' => 'stored-key' ) );

		putenv( OpenRouterProfile::api_key_constant() . '=environment-key' );
		$this->assertSame( 'environment-key', OpenRouterSettings::get_api_key() );
		$this->assertSame( 'environment-key', OpenRouterSettings::get_api_key_override() );

		putenv( OpenRouterProfile::api_key_constant() );
		$this->assertSame( 'stored-key', OpenRouterSettings::get_api_key() );
	}

	public function test_default_models_are_preserved_across_saves(): void {
		$settings  = new OpenRouterSettings();
		$sanitized = $settings->sanitize_settings( array( 'model_text' => 'first-model' ) );
		$GLOBALS['zctz_test_options']['zctz_openrouter_settings'] = $sanitized;

		$this->assertSame( 'first-model', OpenRouterSettings::get_preferred_model( 'text' ) );

		$sanitized = $settings->sanitize_settings( array( 'api_key' => 'secret-key' ) );
		$this->assertSame( 'first-model', $sanitized['model_text'] );
	}

	public function test_a_timeout_outside_the_allowed_range_is_rejected(): void {
		$settings  = new OpenRouterSettings();
		$sanitized = $settings->sanitize_settings( array( 'request_timeout' => '4000' ) );

		$this->assertSame( '', $sanitized['request_timeout'] );
		$this->assertNotEmpty( $GLOBALS['zctz_test_settings_errors'] );

		$GLOBALS['zctz_test_options']['zctz_openrouter_settings'] = $sanitized;
		$this->assertSame( OpenRouterProfile::default_timeout(), OpenRouterSettings::get_text_request_timeout() );
	}

	public function test_a_valid_timeout_is_kept(): void {
		$settings  = new OpenRouterSettings();
		$sanitized = $settings->sanitize_settings( array( 'request_timeout' => '240' ) );
		$GLOBALS['zctz_test_options']['zctz_openrouter_settings'] = $sanitized;

		$this->assertSame( 240.0, OpenRouterSettings::get_text_request_timeout() );
	}

	public function test_an_unknown_reasoning_mode_falls_back_to_the_model_default(): void {
		$settings  = new OpenRouterSettings();
		$sanitized = $settings->sanitize_settings( array( 'reasoning' => 'extreme' ) );

		$this->assertSame( 'default', $sanitized['reasoning'] );
	}

	public function test_the_base_url_gains_a_scheme_and_loses_a_trailing_slash(): void {
		$settings  = new OpenRouterSettings();
		$sanitized = $settings->sanitize_settings( array( 'base_url' => 'proxy.example.test/v1/' ) );

		$this->assertSame( 'https://proxy.example.test/v1', $sanitized['base_url'] );
	}

	public function test_the_default_base_url_is_used_when_none_is_stored(): void {
		if ( '' === OpenRouterProfile::default_base_url() ) {
			$this->markTestSkipped( 'This provider builds its base URL from its own connection fields.' );
		}

		$this->assertSame( OpenRouterProfile::default_base_url(), OpenRouterSettings::get_base_url() );
	}

	public function test_credentials_are_required_before_a_connection_check(): void {
		$result = OpenRouterSettings::verify_connection();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'missing_credentials', $result->get_error_code() );
	}

	public function test_a_rejected_credential_is_reported(): void {
		zctz_test_seed_settings();
		zctz_test_queue_http_response( array( 'error' => 'unauthorized' ), 401 );

		$result = OpenRouterSettings::verify_connection();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'invalid_credentials', $result->get_error_code() );
	}

	public function test_an_unreadable_model_list_is_reported(): void {
		zctz_test_seed_settings();
		zctz_test_queue_http_response( array( 'unexpected' => true ), 200 );

		$result = OpenRouterSettings::verify_connection();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'invalid_response', $result->get_error_code() );
	}

	public function test_a_valid_model_list_confirms_the_connection(): void {
		zctz_test_seed_settings();
		zctz_test_queue_http_response( json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/models.json' ), true ), 200 );

		$this->assertTrue( OpenRouterSettings::verify_connection() );

		$request = end( $GLOBALS['zctz_test_http_requests'] );
		$this->assertSame( OpenRouterSettings::get_models_url(), $request['url'] );
		$this->assertArrayHasKey( OpenRouterProfile::auth_header(), $request['args']['headers'] );
	}

	public function test_the_api_key_lives_in_the_option_wordpress_registers_for_the_connector(): void {
		$expected = 'connectors_ai_' . str_replace( '-', '_', OpenRouterProfile::id() ) . '_api_key';

		$this->assertSame( $expected, OpenRouterSettings::api_key_option() );
	}

	public function test_a_key_entered_on_the_core_connectors_screen_is_used(): void {
		// Seeds whatever else this provider needs, so the assertion below is about
		// the key being read from core's option and nothing else.
		zctz_test_seed_settings();
		$GLOBALS['zctz_test_options'][ OpenRouterSettings::api_key_option() ] = 'key-from-core-screen';

		$this->assertSame( 'key-from-core-screen', OpenRouterSettings::get_api_key() );
		$this->assertTrue( OpenRouterSettings::has_credentials() );
	}

	public function test_saving_on_the_connector_screen_writes_the_key_core_reads(): void {
		$settings = new OpenRouterSettings();
		$settings->sanitize_settings( array( 'api_key' => 'key-from-plugin-screen' ) );

		$this->assertSame(
			'key-from-plugin-screen',
			$GLOBALS['zctz_test_options'][ OpenRouterSettings::api_key_option() ]
		);
	}

	public function test_clearing_the_key_clears_the_option_core_reads(): void {
		$settings = new OpenRouterSettings();
		$settings->sanitize_settings( array( 'api_key' => 'key-to-remove' ) );
		$settings->sanitize_settings( array( 'clear_api_key' => '1' ) );

		$this->assertSame( '', OpenRouterSettings::get_api_key() );
		$this->assertFalse( OpenRouterSettings::has_credentials() );
	}

	public function test_no_second_copy_of_the_key_is_stored(): void {
		$settings = new OpenRouterSettings();
		$settings->sanitize_settings( array( 'api_key' => 'only-one-copy' ) );

		$holders = array();
		foreach ( $GLOBALS['zctz_test_options'] as $name => $value ) {
			if ( is_string( $value ) && 'only-one-copy' === $value ) {
				$holders[] = $name;
			}
		}

		$this->assertSame( array( OpenRouterSettings::api_key_option() ), $holders );
	}
}
