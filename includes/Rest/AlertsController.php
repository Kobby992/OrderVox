<?php
/**
 * REST routes for alerts and their templates.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Rest;

use Webbcode\Ordervox\Api\ApiException;
use Webbcode\Ordervox\Events\Context;
use Webbcode\Ordervox\Events\DailySummary;
use Webbcode\Ordervox\Placeholders\Registry;
use Webbcode\Ordervox\Settings\Settings;
use Webbcode\Ordervox\Templates\Compiler;
use Webbcode\Ordervox\Templates\Defaults;
use Webbcode\Ordervox\Templates\Language;
use Webbcode\Ordervox\Templates\TemplateManager;
use Webbcode\Ordervox\Templates\TemplateRepository;

defined( 'ABSPATH' ) || exit;

/**
 * `ordervox/v1/alerts…` routes used by the Alerts tab and template editor.
 */
final class AlertsController {

	private const NAMESPACE = 'ordervox/v1';
	private const ALERT     = '(?P<alert>[a-z_]+)';

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Stored templates.
	 *
	 * @var TemplateRepository
	 */
	private TemplateRepository $templates;

	/**
	 * Template manager.
	 *
	 * @var TemplateManager
	 */
	private TemplateManager $manager;

	/**
	 * Placeholders.
	 *
	 * @var Registry
	 */
	private Registry $placeholders;

	/**
	 * Daily summary, for preview figures.
	 *
	 * @var DailySummary
	 */
	private DailySummary $summary;

	/**
	 * Constructor.
	 *
	 * @param Settings           $settings     Settings.
	 * @param TemplateRepository $templates    Stored templates.
	 * @param TemplateManager    $manager      Template manager.
	 * @param Registry           $placeholders Placeholders.
	 * @param DailySummary       $summary      Daily summary.
	 */
	public function __construct( Settings $settings, TemplateRepository $templates, TemplateManager $manager, Registry $placeholders, DailySummary $summary ) {
		$this->settings     = $settings;
		$this->templates    = $templates;
		$this->manager      = $manager;
		$this->placeholders = $placeholders;
		$this->summary      = $summary;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers routes.
	 */
	public function register_routes(): void {
		$template = '/alerts/' . self::ALERT . '/template';

		$this->route( '/alerts', 'GET', 'list_alerts' );
		$this->route( '/alerts/templates/create-missing', 'POST', 'create_missing' );
		$this->route( '/alerts/templates/sync', 'POST', 'sync' );
		$this->route( $template, 'GET', 'get_template' );
		$this->route( $template, 'POST', 'save_template' );
		$this->route( $template . '/submit', 'POST', 'submit_template' );
		$this->route( $template . '/reset', 'POST', 'reset_template' );
		$this->route( $template . '/preview', 'POST', 'preview_template' );
	}

	/**
	 * Registers one admin-only endpoint.
	 *
	 * @param string $route    Route pattern.
	 * @param string $method   HTTP method.
	 * @param string $callback Method name on this class.
	 */
	private function route( string $route, string $method, string $callback ): void {
		register_rest_route(
			self::NAMESPACE,
			$route,
			array(
				'methods'             => $method,
				'callback'            => array( $this, $callback ),
				'permission_callback' => static fn() => current_user_can( 'manage_woocommerce' ),
			)
		);
	}

	/**
	 * Every alert with its switch and template status.
	 */
	public function list_alerts(): \WP_REST_Response {
		$language = $this->language();
		$alerts   = array();
		foreach ( Defaults::ALERTS as $alert ) {
			$alerts[] = array_merge(
				array(
					'key'     => $alert,
					'enabled' => 'daily_summary' === $alert
						? (bool) $this->settings->get( 'daily_summary.enabled', false )
						: $this->settings->is_event_enabled( $alert ),
				),
				Defaults::describe( $alert ),
				$this->template_state( $alert, $language )
			);
		}

		return new \WP_REST_Response(
			array(
				'language'      => $language,
				'languages'     => Language::codes(),
				'daily_summary' => $this->settings->get( 'daily_summary' ),
				'alerts'        => $alerts,
			)
		);
	}

	/**
	 * One alert's template, its allowed placeholders, and validation results.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_template( \WP_REST_Request $request ) {
		$alert = $this->alert( $request );
		if ( is_wp_error( $alert ) ) {
			return $alert;
		}

		$language = $this->language();
		$entry    = $this->templates->get( $alert, $language );
		$allowed  = $this->placeholders->for_groups( Defaults::groups( $alert ) );

		$placeholders = array();
		foreach ( $allowed as $name => $definition ) {
			$placeholders[] = array(
				'name'  => $name,
				'label' => $definition['label'],
				'group' => $definition['group'],
			);
		}

		return new \WP_REST_Response(
			array_merge(
				array(
					'key'          => $alert,
					'body'         => $entry['body'],
					'footer'       => $entry['footer'],
					'default_body' => Defaults::body( $alert ),
					'placeholders' => $placeholders,
				),
				Defaults::describe( $alert ),
				Compiler::validate( $entry['body'], $entry['footer'], array_keys( $allowed ) ),
				$this->template_state( $alert, $language )
			)
		);
	}

	/**
	 * Saves the draft text.
	 *
	 * @param \WP_REST_Request $request Request with body and footer.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_template( \WP_REST_Request $request ) {
		$alert = $this->alert( $request );
		if ( is_wp_error( $alert ) ) {
			return $alert;
		}
		$this->templates->save_draft(
			$alert,
			$this->language(),
			sanitize_textarea_field( (string) $request->get_param( 'body' ) ),
			sanitize_text_field( (string) $request->get_param( 'footer' ) )
		);
		return $this->get_template( $request );
	}

	/**
	 * Saves the draft (when given) and submits it to Meta as a new version.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function submit_template( \WP_REST_Request $request ) {
		$alert = $this->alert( $request );
		if ( is_wp_error( $alert ) ) {
			return $alert;
		}
		if ( null !== $request->get_param( 'body' ) ) {
			$saved = $this->save_template( $request );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		try {
			$this->manager->submit( $alert, $this->language() );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_Error( 'ordervox_invalid_template', $e->getMessage(), array( 'status' => 400 ) );
		} catch ( ApiException $e ) {
			return new \WP_Error( 'ordervox_api_error', $e->hint(), array( 'status' => 502 ) );
		}
		return $this->get_template( $request );
	}

	/**
	 * Restores the default text.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function reset_template( \WP_REST_Request $request ) {
		$alert = $this->alert( $request );
		if ( is_wp_error( $alert ) ) {
			return $alert;
		}
		$this->templates->reset( $alert, $this->language() );
		return $this->get_template( $request );
	}

	/**
	 * Renders unsaved text with the store's latest real data, falling back to sample values.
	 *
	 * @param \WP_REST_Request $request Request with body and footer.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function preview_template( \WP_REST_Request $request ) {
		$alert = $this->alert( $request );
		if ( is_wp_error( $alert ) ) {
			return $alert;
		}

		$body    = sanitize_textarea_field( (string) $request->get_param( 'body' ) );
		$footer  = sanitize_text_field( (string) $request->get_param( 'footer' ) );
		$allowed = array_keys( $this->placeholders->for_groups( Defaults::groups( $alert ) ) );
		$context = $this->latest_context( $alert );

		$values = array();
		foreach ( $allowed as $name ) {
			$values[ $name ] = null !== $context ? $this->placeholders->resolve( $name, $context ) : $this->placeholders->sample( $name );
		}

		return new \WP_REST_Response(
			array_merge(
				array(
					'text'        => Compiler::render( $body, $values ),
					'footer'      => $footer,
					'uses_sample' => null === $context,
				),
				Compiler::validate( $body, $footer, $allowed )
			)
		);
	}

	/**
	 * Creates every template not yet in the WhatsApp Business Account.
	 *
	 * @return \WP_REST_Response
	 */
	public function create_missing(): \WP_REST_Response {
		$errors = $this->manager->submit_missing( $this->language() );
		$list   = $this->list_alerts()->get_data();

		$list['errors'] = $errors;
		return new \WP_REST_Response( $list );
	}

	/**
	 * Refreshes approval statuses from Meta.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function sync() {
		try {
			$this->manager->sync();
		} catch ( ApiException $e ) {
			return new \WP_Error( 'ordervox_api_error', $e->hint(), array( 'status' => 502 ) );
		}
		return $this->list_alerts();
	}

	/**
	 * Submitted and active versions, and whether the draft has unsubmitted changes.
	 *
	 * @param string $alert    Alert key.
	 * @param string $language Template language.
	 * @return array<string, mixed>
	 */
	private function template_state( string $alert, string $language ): array {
		$entry     = $this->templates->get( $alert, $language );
		$submitted = $entry['submitted'];
		$active    = $entry['active'];

		$summarise = static fn( ?array $version ) => null === $version ? null : array(
			'name'         => (string) $version['name'],
			'status'       => (string) $version['status'],
			'reason'       => (string) ( $version['reason'] ?? '' ),
			'category'     => (string) ( $version['category'] ?? '' ),
			'version'      => (int) $version['version'],
			'submitted_at' => (int) ( $version['submitted_at'] ?? 0 ),
		);

		return array(
			'submitted' => $summarise( $submitted ),
			'active'    => $summarise( null !== $active && 'APPROVED' === $active['status'] ? $active : null ),
			'changed'   => null === $submitted
				|| (string) ( $submitted['body'] ?? '' ) !== $entry['body']
				|| (string) ( $submitted['footer'] ?? '' ) !== trim( $entry['footer'] ),
		);
	}

	/**
	 * Most recent real data for previews, or null when the store has none yet.
	 *
	 * @param string $alert Alert key.
	 */
	private function latest_context( string $alert ): ?Context {
		$groups = Defaults::groups( $alert );

		if ( in_array( 'order', $groups, true ) ) {
			$orders = wc_get_orders(
				array(
					'limit'   => 1,
					'type'    => 'shop_order',
					'orderby' => 'date',
					'order'   => 'DESC',
				)
			);
			if ( ! $orders ) {
				return null;
			}
			$refund = null;
			if ( in_array( 'refund', $groups, true ) ) {
				$refunds = $orders[0]->get_refunds();
				$refund  = $refunds ? $refunds[0] : null;
			}
			return null === $refund && in_array( 'refund', $groups, true ) ? null : new Context( $orders[0], null, null, $refund );
		}

		if ( in_array( 'product', $groups, true ) ) {
			$products = wc_get_products(
				array(
					'limit'        => 1,
					'manage_stock' => true,
					'orderby'      => 'date',
					'order'        => 'DESC',
				)
			);
			return $products ? new Context( null, $products[0] ) : null;
		}

		if ( in_array( 'review', $groups, true ) ) {
			$reviews = get_comments(
				array(
					'type'   => 'review',
					'number' => 1,
					'status' => 'approve',
				)
			);
			return $reviews ? new Context( null, null, $reviews[0] ) : null;
		}

		if ( in_array( 'summary', $groups, true ) ) {
			return new Context( null, null, null, null, $this->summary->figures( time() ) );
		}

		return null;
	}

	/**
	 * Validated alert key from the route.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return string|\WP_Error
	 */
	private function alert( \WP_REST_Request $request ) {
		$alert = (string) $request['alert'];
		if ( ! in_array( $alert, Defaults::ALERTS, true ) ) {
			return new \WP_Error( 'ordervox_unknown_alert', __( 'Unknown alert.', 'ordervox' ), array( 'status' => 404 ) );
		}
		return $alert;
	}

	/**
	 * Current template language.
	 */
	private function language(): string {
		return Language::current( $this->settings );
	}
}
