<?php
/**
 * PHPUnit bootstrap providing the small WordPress surface the connector uses.
 */

declare( strict_types=1 );

require dirname( __DIR__ ) . '/vendor/autoload.php';

$zctz_ai_client_path = getenv( 'ZCTZ_AI_CLIENT_PATH' );
if ( is_string( $zctz_ai_client_path ) && is_dir( $zctz_ai_client_path . '/src' ) ) {
	spl_autoload_register(
		static function ( $class_name ) use ( $zctz_ai_client_path ) {
			$prefix = 'WordPress\\AiClient\\';
			if ( 0 !== strpos( $class_name, $prefix ) ) {
				return;
			}

			$relative = substr( $class_name, strlen( $prefix ) );
			$file     = $zctz_ai_client_path . '/src/' . str_replace( '\\', '/', $relative ) . '.php';

			if ( is_readable( $file ) ) {
				require_once $file;
			}
		},
		true,
		true
	);
}

function zctz_test_supports_embeddings(): bool {
	return interface_exists( 'WordPress\\AiClient\\Providers\\Models\\EmbeddingGeneration\\Contracts\\EmbeddingGenerationModelInterface' )
		&& class_exists( 'WordPress\\AiClient\\Results\\DTO\\EmbeddingResult' );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

$GLOBALS['zctz_test_options']        = array();
$GLOBALS['zctz_test_hooks']          = array();
$GLOBALS['zctz_test_http_responses'] = array();
$GLOBALS['zctz_test_http_requests']  = array();
$GLOBALS['zctz_test_settings_errors'] = array();
$GLOBALS['zctz_test_enqueued']       = array();
$GLOBALS['zctz_test_localized']      = array();

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( $code = '', $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}

function __( $text, $domain = null ) {
	unset( $domain );
	return $text;
}

function esc_html__( $text, $domain = null ) {
	return __( $text, $domain );
}

function esc_attr__( $text, $domain = null ) {
	return __( $text, $domain );
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
	return esc_html( $text );
}

function esc_url( $url ) {
	return (string) $url;
}

function esc_url_raw( $url ) {
	return (string) filter_var( (string) $url, FILTER_SANITIZE_URL );
}

function wp_kses_post( $value ) {
	return $value;
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function sanitize_key( $value ) {
	return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_unslash( $value ) {
	return $value;
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
	return json_encode( $value, $flags, $depth );
}

function get_option( $name, $default_value = false ) {
	return array_key_exists( $name, $GLOBALS['zctz_test_options'] )
		? $GLOBALS['zctz_test_options'][ $name ]
		: $default_value;
}

function update_option( $name, $value, $autoload = null ) {
	unset( $autoload );
	$GLOBALS['zctz_test_options'][ $name ] = $value;
	return true;
}

function delete_option( $name ) {
	unset( $GLOBALS['zctz_test_options'][ $name ] );
	return true;
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['zctz_test_hooks'][ $hook ][] = array( $callback, $priority, $accepted_args );
	return true;
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	return add_action( $hook, $callback, $priority, $accepted_args );
}

function apply_filters( $hook, $value ) {
	$arguments = array_slice( func_get_args(), 2 );

	foreach ( $GLOBALS['zctz_test_hooks'][ $hook ] ?? array() as $registered ) {
		$value = call_user_func_array( $registered[0], array_merge( array( $value ), $arguments ) );
	}

	return $value;
}

function plugin_dir_path( $file ) {
	return rtrim( dirname( $file ), '/\\' ) . '/';
}

function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}

function plugins_url( $path = '', $plugin = '' ) {
	unset( $plugin );
	return 'https://example.test/wp-content/plugins/zactonz-ai-provider-openrouter/' . ltrim( (string) $path, '/' );
}

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
}

function home_url( $path = '' ) {
	return 'https://example.test/' . ltrim( (string) $path, '/' );
}

function get_bloginfo( $show = '' ) {
	unset( $show );
	return 'Example Site';
}

function is_admin() {
	return true;
}

function is_wp_version_compatible( $version ) {
	unset( $version );
	return true;
}

function current_user_can( $capability ) {
	unset( $capability );
	return true;
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function __return_empty_string() {
	return '';
}

function register_setting( $group, $name, $args = array() ) {
	$GLOBALS['zctz_test_hooks']['registered_settings'][] = array( $group, $name, $args );
	return true;
}

function add_settings_section( $id, $title, $callback, $page ) {
	$GLOBALS['zctz_test_hooks']['settings_sections'][] = array( $id, $title, $callback, $page );
	return true;
}

function add_settings_field( $id, $title, $callback, $page, $section = 'default', $args = array() ) {
	$GLOBALS['zctz_test_hooks']['settings_fields'][] = array( $id, $title, $callback, $page, $section, $args );
	return true;
}

function add_settings_error( $setting, $code, $message, $type = 'error' ) {
	$GLOBALS['zctz_test_settings_errors'][] = compact( 'setting', 'code', 'message', 'type' );
	return true;
}

function add_options_page( $page_title, $menu_title, $capability, $slug, $callback = null ) {
	$GLOBALS['zctz_test_hooks']['options_pages'][] = compact( 'page_title', 'menu_title', 'capability', 'slug', 'callback' );
	return $slug;
}

function get_admin_page_title() {
	return 'Connector Settings';
}

function settings_fields( $group ) {
	unset( $group );
}

function do_settings_sections( $page ) {
	unset( $page );
}

function submit_button() {
}

function checked( $checked, $current = true, $display = true ) {
	$result = (string) $checked === (string) $current ? ' checked="checked"' : '';
	if ( $display ) {
		echo $result;
	}
	return $result;
}

function selected( $selected, $current = true, $display = true ) {
	$result = (string) $selected === (string) $current ? ' selected="selected"' : '';
	if ( $display ) {
		echo $result;
	}
	return $result;
}

function disabled( $disabled, $current = true, $display = true ) {
	$result = (string) $disabled === (string) $current ? ' disabled="disabled"' : '';
	if ( $display ) {
		echo $result;
	}
	return $result;
}

function wp_enqueue_script( $handle, $src = '', $deps = array(), $version = false, $in_footer = false ) {
	$GLOBALS['zctz_test_enqueued']['scripts'][ $handle ] = compact( 'src', 'deps', 'version', 'in_footer' );
}

function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false, $media = 'all' ) {
	$GLOBALS['zctz_test_enqueued']['styles'][ $handle ] = compact( 'src', 'deps', 'version', 'media' );
}

function wp_style_add_data( $handle, $key, $value ) {
	unset( $handle, $key, $value );
	return true;
}

function wp_localize_script( $handle, $name, $data ) {
	$GLOBALS['zctz_test_localized'][ $name ] = $data;
	return true;
}

function wp_create_nonce( $action = -1 ) {
	return 'nonce-' . md5( (string) $action );
}

function add_query_arg( $args, $url = '' ) {
	$separator = false === strpos( $url, '?' ) ? '?' : '&';
	return $url . $separator . http_build_query( $args );
}

function check_ajax_referer( $action = -1, $query_arg = false, $stop = true ) {
	unset( $action, $query_arg, $stop );
	return 1;
}

function wp_send_json_success( $data = null, $status_code = null ) {
	unset( $status_code );
	throw new \RuntimeException( (string) wp_json_encode( array( 'success' => true, 'data' => $data ) ) );
}

function wp_send_json_error( $data = null, $status_code = null ) {
	unset( $status_code );
	throw new \RuntimeException( (string) wp_json_encode( array( 'success' => false, 'data' => $data ) ) );
}

function zctz_test_queue_http_response( $body, $code = 200 ) {
	$GLOBALS['zctz_test_http_responses'][] = array(
		'response' => array( 'code' => $code ),
		'body'     => is_string( $body ) ? $body : (string) json_encode( $body ),
	);
}

function wp_remote_request( $url, $args = array() ) {
	$GLOBALS['zctz_test_http_requests'][] = array( 'url' => $url, 'args' => $args );

	if ( empty( $GLOBALS['zctz_test_http_responses'] ) ) {
		return new WP_Error( 'no_response', 'No test HTTP response was queued.' );
	}

	return array_shift( $GLOBALS['zctz_test_http_responses'] );
}

function wp_remote_get( $url, $args = array() ) {
	$args['method'] = 'GET';
	return wp_remote_request( $url, $args );
}

function wp_remote_post( $url, $args = array() ) {
	$args['method'] = 'POST';
	return wp_remote_request( $url, $args );
}

function wp_remote_retrieve_response_code( $response ) {
	return is_array( $response ) && isset( $response['response']['code'] ) ? $response['response']['code'] : 0;
}

function wp_remote_retrieve_body( $response ) {
	return is_array( $response ) && isset( $response['body'] ) ? $response['body'] : '';
}

function zctz_test_seed_settings( array $extra = array() ) {
	$settings = new \Zactonz\AiConnectorForOpenRouter\Settings\OpenRouterSettings();
	$values   = array( 'api_key' => 'zctz-test-key' );

	foreach ( \Zactonz\AiConnectorForOpenRouter\Provider\OpenRouterEndpoint::connection_fields() as $field ) {
		$values[ (string) $field['key'] ] = (string) ( $field['test_value'] ?? $field['default'] ?? 'test-value' );
	}

	$GLOBALS['zctz_test_options']['zctz_openrouter_settings'] = array_merge( $settings->sanitize_settings( $values ), $extra );

	return $GLOBALS['zctz_test_options']['zctz_openrouter_settings'];
}

require dirname( __DIR__ ) . '/igniter.php';
\Zactonz\AiConnectorForOpenRouter\zctz_load();

require __DIR__ . '/support/FakeHttp.php';
