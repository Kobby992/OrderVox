<?php
/**
 * Minimal Meta Graph API client.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Sends authenticated requests to graph.facebook.com and turns failures into ApiException.
 */
final class GraphClient {

	/**
	 * Default Graph API version. Bump after testing each release; filterable with `ordervox_graph_version`.
	 */
	public const VERSION = 'v24.0';

	private const BASE_URL = 'https://graph.facebook.com/';

	/**
	 * Bearer token.
	 *
	 * @var string
	 */
	private string $token;

	/**
	 * Constructor.
	 *
	 * @param string $token Access token.
	 */
	public function __construct( string $token ) {
		$this->token = $token;
	}

	/**
	 * GET request.
	 *
	 * @param string               $path  Path after the version, e.g. `123/message_templates`.
	 * @param array<string, mixed> $query Query arguments.
	 * @return array<string, mixed>
	 */
	public function get( string $path, array $query = array() ): array {
		return $this->request( 'GET', $path, $query );
	}

	/**
	 * POST request with a JSON body.
	 *
	 * @param string               $path Path after the version.
	 * @param array<string, mixed> $body Request body.
	 * @return array<string, mixed>
	 */
	public function post( string $path, array $body = array() ): array {
		return $this->request( 'POST', $path, array(), $body );
	}

	/**
	 * Sends a request and returns the decoded JSON body.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $path   Path after the version.
	 * @param array<string, mixed> $query  Query arguments.
	 * @param array<string, mixed> $body   JSON body for POST.
	 * @return array<string, mixed>
	 * @throws ApiException On transport errors and non-2xx responses.
	 */
	private function request( string $method, string $path, array $query = array(), ?array $body = null ): array {
		if ( '' === $this->token ) {
			throw new ApiException( 'No access token is saved.', 190 );
		}

		/**
		 * Filters the Graph API version used for all requests.
		 *
		 * @param string $version Version such as `v24.0`.
		 */
		$version = (string) apply_filters( 'ordervox_graph_version', self::VERSION );
		$url     = self::BASE_URL . $version . '/' . ltrim( $path, '/' );
		if ( $query ) {
			$url = add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $query ) ), $url );
		}

		$args = array(
			'method'  => $method,
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->token,
				'Accept'        => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = $body ? (string) wp_json_encode( $body ) : '{}';
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			throw new ApiException( $response->get_error_message() );
		}

		$status  = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$decoded = is_array( $decoded ) ? $decoded : array();

		if ( $status < 200 || $status >= 300 || isset( $decoded['error'] ) ) {
			throw ApiException::from_response( $decoded, $status );
		}

		return $decoded;
	}
}
