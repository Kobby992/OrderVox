<?php
/**
 * Stored alert templates.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Templates;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps, per alert and language, the editable draft, the version last submitted to Meta, and the
 * approved version used for sending. A new version is only used once Meta approves it, so editing
 * a template never interrupts alerts.
 */
class TemplateRepository {

	public const OPTION = 'ordervox_templates';

	/**
	 * Stored data: [alert][language] => entry.
	 *
	 * @var array<string, array<string, array<string, mixed>>>|null
	 */
	private ?array $data = null;

	/**
	 * Entry for an alert: body, footer, submitted (array|null), active (array|null).
	 *
	 * @param string $alert    Alert key.
	 * @param string $language Template language.
	 * @return array{body: string, footer: string, submitted: array<string, mixed>|null, active: array<string, mixed>|null}
	 */
	public function get( string $alert, string $language ): array {
		$entry = $this->load()[ $alert ][ $language ] ?? array();
		return array(
			'body'      => (string) ( $entry['body'] ?? Defaults::body( $alert ) ),
			'footer'    => (string) ( $entry['footer'] ?? Defaults::footer() ),
			'submitted' => is_array( $entry['submitted'] ?? null ) ? $entry['submitted'] : null,
			'active'    => is_array( $entry['active'] ?? null ) ? $entry['active'] : null,
		);
	}

	/**
	 * Approved Meta template used for sending, or null if none is approved yet.
	 *
	 * @param string $alert    Alert key.
	 * @param string $language Template language.
	 * @return array<string, mixed>|null
	 */
	public function active( string $alert, string $language ): ?array {
		$active = $this->get( $alert, $language )['active'];
		return null !== $active && 'APPROVED' === ( $active['status'] ?? '' ) ? $active : null;
	}

	/**
	 * Saves the editable text.
	 *
	 * @param string $alert    Alert key.
	 * @param string $language Template language.
	 * @param string $body     Body with placeholders.
	 * @param string $footer   Footer.
	 */
	public function save_draft( string $alert, string $language, string $body, string $footer ): void {
		$this->patch(
			$alert,
			$language,
			array(
				'body'   => $body,
				'footer' => $footer,
			)
		);
	}

	/**
	 * Restores the default text. Submitted and active versions are kept.
	 *
	 * @param string $alert    Alert key.
	 * @param string $language Template language.
	 */
	public function reset( string $alert, string $language ): void {
		$this->save_draft( $alert, $language, Defaults::body( $alert ), Defaults::footer() );
	}

	/**
	 * Records a version sent to Meta. Promotes it straight away when Meta approved it immediately.
	 *
	 * @param string               $alert     Alert key.
	 * @param string               $language  Template language.
	 * @param array<string, mixed> $submitted name, id, text, keys, footer, status, version, category.
	 */
	public function set_submitted( string $alert, string $language, array $submitted ): void {
		$changes = array( 'submitted' => $submitted );
		if ( 'APPROVED' === ( $submitted['status'] ?? '' ) ) {
			$changes['active'] = $submitted;
		}
		$this->patch( $alert, $language, $changes );
	}

	/**
	 * Applies a status from Meta (webhook or sync) to whichever version has this name.
	 * Approval promotes the submitted version to active.
	 *
	 * @param string $name     Meta template name.
	 * @param string $language Template language.
	 * @param string $status   APPROVED, REJECTED, PENDING, PAUSED, DISABLED…
	 * @param string $reason   Rejection reason, if any.
	 * @return bool Whether a stored template matched.
	 */
	public function apply_status( string $name, string $language, string $status, string $reason = '' ): bool {
		$status = strtoupper( $status );
		$reason = 'NONE' === strtoupper( $reason ) ? '' : $reason;

		foreach ( Defaults::ALERTS as $alert ) {
			$entry   = $this->get( $alert, $language );
			$changes = array();

			if ( null !== $entry['submitted'] && $entry['submitted']['name'] === $name ) {
				$changes['submitted'] = array_merge(
					$entry['submitted'],
					array(
						'status' => $status,
						'reason' => $reason,
					)
				);
				if ( 'APPROVED' === $status ) {
					$changes['active'] = $changes['submitted'];
				}
			}
			if ( null !== $entry['active'] && $entry['active']['name'] === $name && ! isset( $changes['active'] ) ) {
				$changes['active'] = array_merge( $entry['active'], array( 'status' => $status ) );
			}

			if ( $changes ) {
				$this->patch( $alert, $language, $changes );
				return true;
			}
		}
		return false;
	}

	/**
	 * Highest version number used for an alert, so new submissions get a fresh template name.
	 *
	 * @param string $alert    Alert key.
	 * @param string $language Template language.
	 */
	public function last_version( string $alert, string $language ): int {
		$entry = $this->get( $alert, $language );
		return max( (int) ( $entry['submitted']['version'] ?? 0 ), (int) ( $entry['active']['version'] ?? 0 ) );
	}

	/**
	 * Merges changes into one entry and saves.
	 *
	 * @param string               $alert    Alert key.
	 * @param string               $language Template language.
	 * @param array<string, mixed> $changes  Fields to set.
	 */
	private function patch( string $alert, string $language, array $changes ): void {
		$data                        = $this->load();
		$current                     = $data[ $alert ][ $language ] ?? array();
		$data[ $alert ][ $language ] = array_merge( $current, $changes );
		$this->data                  = $data;
		update_option( self::OPTION, $data, false );
	}

	/**
	 * Loads the option once.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	private function load(): array {
		if ( null === $this->data ) {
			$stored     = get_option( self::OPTION, array() );
			$this->data = is_array( $stored ) ? $stored : array();
		}
		return $this->data;
	}
}
