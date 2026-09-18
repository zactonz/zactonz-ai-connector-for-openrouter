<?php

declare( strict_types=1 );

namespace Zactonz\AiConnectorForOpenRouter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zactonz\AiConnectorForOpenRouter\Provider\OpenRouterProfile;
use Zactonz\AiConnectorForOpenRouter\Provider\OpenRouterProvider;
use Zactonz\AiConnectorForOpenRouter\Settings\OpenRouterSettings;

class ProviderTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['zctz_test_options'] = array();
	}

	public function test_the_provider_reports_its_identity(): void {
		$metadata = OpenRouterProvider::metadata();

		$this->assertSame( OpenRouterProfile::id(), $metadata->getId() );
		$this->assertSame( OpenRouterProfile::name(), $metadata->getName() );
		$this->assertNotSame( '', OpenRouterProfile::api_key_url() );
	}

	public function test_the_bundled_logo_exists(): void {
		$this->assertFileExists( dirname( __DIR__, 2 ) . '/includes/Provider/logo.svg' );
	}

	public function test_urls_are_built_from_the_configured_base_url(): void {
		$this->assertSame( OpenRouterSettings::get_base_url(), OpenRouterProvider::url() );
		$this->assertSame( OpenRouterSettings::get_base_url() . '/models', OpenRouterProvider::url( '/models' ) );
		$this->assertSame( OpenRouterSettings::get_base_url() . '/models', OpenRouterProvider::url( 'models' ) );
	}

	public function test_availability_follows_the_stored_credentials(): void {
		$this->assertFalse( OpenRouterProvider::availability()->isConfigured() );

		zctz_test_seed_settings();

		$this->assertTrue( OpenRouterProvider::availability()->isConfigured() );
	}

	public function test_the_advertised_capabilities_match_the_profile(): void {
		$this->assertTrue( OpenRouterProfile::supports( 'text' ) );

		foreach ( array( 'vision', 'tools', 'structured', 'embedding', 'image', 'reasoning' ) as $capability ) {
			$this->assertIsBool( OpenRouterProfile::supports( $capability ) );
		}
	}
}
