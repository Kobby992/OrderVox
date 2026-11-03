import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Read-only value with a copy button.
 *
 * @param {Object} props
 * @param {string} props.label Field label.
 * @param {string} props.value Value to show and copy.
 */
export default function CopyField( { label, value } ) {
	const [ copied, setCopied ] = useState( false );

	const copy = async () => {
		try {
			await window.navigator.clipboard.writeText( value );
			setCopied( true );
			setTimeout( () => setCopied( false ), 2000 );
		} catch {
			setCopied( false );
		}
	};

	return (
		<div className="ordervox-copy">
			<span className="ordervox-copy__label">{ label }</span>
			<code className="ordervox-copy__value">{ value }</code>
			<Button variant="secondary" size="compact" onClick={ copy }>
				{ copied
					? __( 'Copied', 'ordervox' )
					: __( 'Copy', 'ordervox' ) }
			</Button>
		</div>
	);
}
