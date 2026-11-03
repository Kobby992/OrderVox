<?php
/**
 * Base class for tests that fake WordPress HTTP calls.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Tests\Unit;

use Brain\Monkey\Functions;
use Webbcode\Ordervox\Settings\Credentials;
use Webbcode\Ordervox\Support\Crypto;

/**
 * Stubs the WordPress HTTP API and records each request.
 */
abstract class HttpTestCase extends TestCase {

	/**
	 * Requests made: url and args.
	 *
	 * @var array<int, array{url: string, args: array<string, mixed>}>
	 */
	protected array $requests = array();

	/**
	 * Responses returned in order: [status, body array] or a WP_Error stand-in (object).
	 *
	 * @var array<int, mixed>
	 */
	protected array $responses = array();

	protected function set_up(): void {
		parent::set_up();
		$this->requests  = array();
		$this->responses = array();

		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'add_query_arg' )->alias(
			static fn( array $args, string $url ) => $url . '?' . implode(
				'&',
				array_map( static fn( $k, $v ) => $k . '=' . $v, array_keys( $args ), $args )
			)
		);
		Functions\when( 'is_wp_error' )->alias( static fn( $thing ) => $thing instanceof \stdClass && isset( $thing->wp_error ) );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( static fn( $r ) => $r['response']['code'] );
		Functions\when( 'wp_remote_retrieve_body' )->alias( static fn( $r ) => $r['body'] );
		Functions\when( 'wp_remote_request' )->alias(
			function ( string $url, array $args ) {
				$this->requests[] = array(
					'url'  => $url,
					'args' => $args,
				);
				$next = array_shift( $this->responses );
				if ( is_object( $next ) ) {
					return $next;
				}
				return array(
					'response' => array( 'code' => $next[0] ),
					'body'     => json_encode( $next[1] ),
				);
			}
		);
	}

	/**
	 * A stand-in for WP_Error with a message.
	 *
	 * @param string $message Error message.
	 */
	protected function wp_error( string $message ): object {
		return new class( $message ) extends \stdClass {
			public bool $wp_error = true;

			public function __construct( private string $message ) {}

			public function get_error_message(): string {
				return $this->message;
			}
		};
	}

	/**
	 * Credentials backed by a stubbed option, with the given plain values.
	 *
	 * @param array<string, string> $values Field values.
	 */
	protected function credentials( array $values ): Credentials {
		$crypto = new Crypto( str_repeat( 'k', 32 ) );
		$stored = array();
		foreach ( $values as $field => $value ) {
			$stored[ $field ] = Credentials::FIELDS[ $field ] ? $crypto->encrypt( $value ) : $value;
		}
		Functions\when( 'get_option' )->alias(
			static fn( $name, $fallback = false ) => Credentials::OPTION === $name ? $stored : $fallback
		);
		return new Credentials( $crypto );
	}
}
