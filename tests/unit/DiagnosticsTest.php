<?php

declare( strict_types=1 );

namespace Zactonz\AiConnectorForOpenRouter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zactonz\AiConnectorForOpenRouter\Diagnostics\OpenRouterDiagnostics;
use Zactonz\AiConnectorForOpenRouter\Diagnostics\OpenRouterSiteHealth;
use Zactonz\AiConnectorForOpenRouter\Settings\OpenRouterSettings;

class DiagnosticsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['zctz_test_options']        = array();
		$GLOBALS['zctz_test_http_responses'] = array();
		$GLOBALS['zctz_test_http_requests']  = array();
	}

	public function test_missing_credentials_are_reported_without_a_request(): void {
		$report = ( new OpenRouterDiagnostics() )->run();

		$this->assertFalse( $report['connected'] );
		$this->assertNotSame( '', $report['error'] );
		$this->assertEmpty( $GLOBALS['zctz_test_http_requests'] );
	}

	public function test_a_successful_listing_is_summarized(): void {
		zctz_test_seed_settings();
		zctz_test_queue_http_response( json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/models.json' ), true ), 200 );

		$report = ( new OpenRouterDiagnostics() )->run();

		$this->assertTrue( $report['connected'] );
		$this->assertGreaterThan( 0, $report['modelCount'] );
		$this->assertSame( 200, $report['httpStatus'] );
		$this->assertSame( '', $report['error'] );
	}

	public function test_the_report_never_contains_the_credential(): void {
		zctz_test_seed_settings();
		zctz_test_queue_http_response( json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/models.json' ), true ), 200 );

		$report = ( new OpenRouterDiagnostics() )->run();

		$this->assertStringNotContainsString( 'zctz-test-key', (string) wp_json_encode( $report ) );
	}

	public function test_a_default_missing_from_the_listing_is_flagged(): void {
		zctz_test_seed_settings();
		$settings                                                           = OpenRouterSettings::get_settings();
		$settings['model_text']                                             = 'model-that-was-removed';
		$GLOBALS['zctz_test_options']['zctz_openrouter_settings']               = $settings;
		zctz_test_queue_http_response( json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/models.json' ), true ), 200 );

		$report = ( new OpenRouterDiagnostics() )->run();

		$this->assertArrayHasKey( 'text', $report['missingDefaults'] );
		$this->assertSame( 'model-that-was-removed', $report['missingDefaults']['text'] );
	}

	public function test_an_http_failure_is_reported(): void {
		zctz_test_seed_settings();
		zctz_test_queue_http_response( array( 'error' => 'server error' ), 500 );

		$report = ( new OpenRouterDiagnostics() )->run();

		$this->assertFalse( $report['connected'] );
		$this->assertSame( 500, $report['httpStatus'] );
	}

	public function test_site_health_only_registers_when_credentials_exist(): void {
		$health = new OpenRouterSiteHealth();

		$this->assertArrayNotHasKey( 'zctz_openrouter_connection', $health->register_tests( array() )['direct'] ?? array() );

		zctz_test_seed_settings();

		$this->assertArrayHasKey( 'zctz_openrouter_connection', $health->register_tests( array() )['direct'] );
	}

	public function test_site_health_debug_information_hides_the_credential(): void {
		zctz_test_seed_settings();

		$info = ( new OpenRouterSiteHealth() )->add_debug_information( array() );

		$this->assertArrayHasKey( 'zctz_openrouter', $info );
		$this->assertStringNotContainsString( 'zctz-test-key', (string) wp_json_encode( $info ) );
	}
}
