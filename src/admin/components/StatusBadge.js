import { __ } from '@wordpress/i18n';

const LABELS = {
	queued: __( 'Queued', 'ordervox' ),
	sending: __( 'Sending', 'ordervox' ),
	sent: __( 'Sent', 'ordervox' ),
	delivered: __( 'Delivered', 'ordervox' ),
	read: __( 'Read', 'ordervox' ),
	failed: __( 'Failed', 'ordervox' ),
	ok: __( 'OK', 'ordervox' ),
	warning: __( 'Needs attention', 'ordervox' ),
	error: __( 'Problem', 'ordervox' ),
};

/**
 * Coloured pill for a message or health status.
 *
 * @param {Object} props
 * @param {string} props.status Status key.
 */
export default function StatusBadge( { status } ) {
	return (
		<span className={ `ordervox-badge ordervox-badge--${ status }` }>
			{ LABELS[ status ] || status }
		</span>
	);
}
