<?php
/**
 * Creates and syncs alert templates in the WhatsApp Business Account.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Templates;

use Webbcode\Ordervox\Api\ApiException;
use Webbcode\Ordervox\Api\WhatsAppApi;
use Webbcode\Ordervox\Placeholders\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Submits alert templates to Meta and keeps their approval status up to date.
 */
final class TemplateManager {

	/**
	 * Cloud API.
	 *
	 * @var WhatsAppApi
	 */
	private WhatsAppApi $api;

	/**
	 * Stored templates.
	 *
	 * @var TemplateRepository
	 */
	private TemplateRepository $templates;

	/**
	 * Placeholders.
	 *
	 * @var Registry
	 */
	private Registry $placeholders;

	/**
	 * Constructor.
	 *
	 * @param WhatsAppApi        $api          Cloud API.
	 * @param TemplateRepository $templates    Stored templates.
	 * @param Registry           $placeholders Placeholders.
	 */
	public function __construct( WhatsAppApi $api, TemplateRepository $templates, Registry $placeholders ) {
		$this->api          = $api;
		$this->templates    = $templates;
		$this->placeholders = $placeholders;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'ordervox_template_status_update', array( $this, 'on_status_update' ) );
	}

	/**
	 * Applies a template status webhook.
	 *
	 * @param array<string, mixed> $value Webhook change value.
	 */
	public function on_status_update( array $value ): void {
		$this->templates->apply_status(
			(string) ( $value['message_template_name'] ?? '' ),
			(string) ( $value['message_template_language'] ?? '' ),
			(string) ( $value['event'] ?? '' ),
			(string) ( $value['reason'] ?? '' )
		);
	}

	/**
	 * Placeholder names an alert may use.
	 *
	 * @param string $alert Alert key.
	 * @return string[]
	 */
	public function allowed_placeholders( string $alert ): array {
		return array_keys( $this->placeholders->for_groups( Defaults::groups( $alert ) ) );
	}

	/**
	 * Submits the current draft of an alert as a new template version.
	 *
	 * @param string $alert    Alert key.
	 * @param string $language Template language.
	 * @return array<string, mixed> The submitted version.
	 * @throws \InvalidArgumentException When the draft breaks Meta's rules.
	 * @throws ApiException When Meta rejects the request.
	 */
	public function submit( string $alert, string $language ): array {
		$entry   = $this->templates->get( $alert, $language );
		$allowed = $this->allowed_placeholders( $alert );
		$check   = Compiler::validate( $entry['body'], $entry['footer'], $allowed );
		if ( $check['errors'] ) {
			throw new \InvalidArgumentException( implode( ' ', $check['errors'] ) );
		}

		$compiled = Compiler::compile( $entry['body'], $allowed );
		$body     = array(
			'type' => 'BODY',
			'text' => $compiled['text'],
		);
		if ( $compiled['keys'] ) {
			$body['example'] = array(
				'body_text' => array(
					array_map( fn( string $key ) => Compiler::clean_value( $this->placeholders->sample( $key ) ), $compiled['keys'] ),
				),
			);
		}
		$components = array( $body );
		if ( '' !== trim( $entry['footer'] ) ) {
			$components[] = array(
				'type' => 'FOOTER',
				'text' => trim( $entry['footer'] ),
			);
		}

		$version = $this->templates->last_version( $alert, $language );
		for ( $attempt = 0; ; $attempt++ ) {
			++$version;
			$name = 'ordervox_' . $alert . '_v' . $version;
			try {
				$response = $this->api->create_template(
					array(
						'name'       => $name,
						'language'   => $language,
						'category'   => 'UTILITY',
						'components' => $components,
					)
				);
				break;
			} catch ( ApiException $e ) {
				// A deleted or foreign template may already hold this name; try the next version.
				if ( $attempt >= 2 || false === stripos( $e->getMessage(), 'already' ) ) {
					throw $e;
				}
			}
		}

		$submitted = array(
			'name'         => $name,
			'id'           => (string) ( $response['id'] ?? '' ),
			'language'     => $language,
			'text'         => $compiled['text'],
			'keys'         => $compiled['keys'],
			'body'         => $entry['body'],
			'footer'       => trim( $entry['footer'] ),
			'status'       => strtoupper( (string) ( $response['status'] ?? 'PENDING' ) ),
			'category'     => strtoupper( (string) ( $response['category'] ?? 'UTILITY' ) ),
			'reason'       => '',
			'version'      => $version,
			'submitted_at' => time(),
		);
		$this->templates->set_submitted( $alert, $language, $submitted );
		delete_transient( 'ordervox_templates' );

		return $submitted;
	}

	/**
	 * Submits every alert that has never been submitted in this language.
	 *
	 * @param string $language Template language.
	 * @return array<string, string> Error messages by alert; empty when all succeeded.
	 */
	public function submit_missing( string $language ): array {
		$errors = array();
		foreach ( Defaults::ALERTS as $alert ) {
			$entry = $this->templates->get( $alert, $language );
			if ( null !== $entry['submitted'] || null !== $entry['active'] ) {
				continue;
			}
			try {
				$this->submit( $alert, $language );
			} catch ( ApiException $e ) {
				$errors[ $alert ] = $e->hint();
			} catch ( \InvalidArgumentException $e ) {
				$errors[ $alert ] = $e->getMessage();
			}
		}
		return $errors;
	}

	/**
	 * Refreshes approval statuses from Meta.
	 *
	 * @return int Number of stored templates updated.
	 * @throws ApiException When Meta cannot be reached.
	 */
	public function sync(): int {
		$updated = 0;
		foreach ( $this->api->templates() as $template ) {
			$name = (string) ( $template['name'] ?? '' );
			if ( ! str_starts_with( $name, 'ordervox_' ) ) {
				continue;
			}
			if ( $this->templates->apply_status( $name, (string) ( $template['language'] ?? '' ), (string) ( $template['status'] ?? '' ), (string) ( $template['rejected_reason'] ?? '' ) ) ) {
				++$updated;
			}
		}
		return $updated;
	}
}
