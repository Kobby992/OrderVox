/**
 * Ordervox admin app entry. The setup wizard and settings screens arrive in Phase 1.
 */
import { createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Card, CardBody, Notice } from '@wordpress/components';

import './admin.scss';

function App() {
	return (
		<div className="ordervox-admin">
			<h1>{ __( 'Ordervox', 'ordervox' ) }</h1>
			<Card>
				<CardBody>
					<Notice status="info" isDismissible={ false }>
						{ __(
							'Ordervox is installed. The WhatsApp connection wizard is coming soon.',
							'ordervox'
						) }
					</Notice>
				</CardBody>
			</Card>
		</div>
	);
}

const root = document.getElementById( 'ordervox-admin-root' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
