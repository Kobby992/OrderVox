import { useEffect, useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	Spinner,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { errorMessage, getHealth } from '../api';
import StatusBadge from './StatusBadge';

const CHECK_LABELS = {
	credentials: __( 'WhatsApp connection', 'ordervox' ),
	token: __( 'Access token', 'ordervox' ),
	phone: __( 'Business number', 'ordervox' ),
	owner_phone: __( 'Alert number', 'ordervox' ),
	webhook: __( 'Webhook', 'ordervox' ),
	queue: __( 'Message queue', 'ordervox' ),
};

const COUNT_LABELS = [ 'sent', 'delivered', 'read', 'failed', 'queued' ];

/**
 * Connection health and message totals.
 *
 * @param {Object}     props
 * @param {() => void} props.onOpenSetup Switches to the setup tab.
 */
export default function Dashboard( { onOpenSetup } ) {
	const [ health, setHealth ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );

	const load = ( refresh = false ) => {
		setLoading( true );
		setError( '' );
		getHealth( refresh )
			.then( setHealth )
			.catch( ( e ) => setError( errorMessage( e ) ) )
			.finally( () => setLoading( false ) );
	};

	useEffect( () => load(), [] );

	return (
		<div className="ordervox-dashboard">
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			<Card>
				<CardHeader>
					<h2>{ __( 'Connection health', 'ordervox' ) }</h2>
					<div className="ordervox-actions">
						{ health && <StatusBadge status={ health.status } /> }
						<Button
							variant="secondary"
							onClick={ () => load( true ) }
							isBusy={ loading }
							disabled={ loading }
						>
							{ __( 'Run checks now', 'ordervox' ) }
						</Button>
					</div>
				</CardHeader>
				<CardBody>
					{ ! health && loading && <Spinner /> }
					{ health && (
						<ul className="ordervox-checks">
							{ Object.entries( health.checks ).map(
								( [ key, check ] ) => (
									<li key={ key }>
										<StatusBadge status={ check.status } />
										<strong>
											{ CHECK_LABELS[ key ] || key }
										</strong>
										<span>{ check.message }</span>
									</li>
								)
							) }
						</ul>
					) }
					{ health && health.checks.credentials?.status !== 'ok' && (
						<Button variant="primary" onClick={ onOpenSetup }>
							{ __( 'Connect WhatsApp', 'ordervox' ) }
						</Button>
					) }
					{ health?.checked_at && (
						<p className="description">
							{ sprintf(
								/* translators: %s: date and time. */
								__( 'Last checked: %s', 'ordervox' ),
								new Date(
									health.checked_at * 1000
								).toLocaleString()
							) }
						</p>
					) }
				</CardBody>
			</Card>

			{ health?.counts && (
				<Card>
					<CardHeader>
						<h2>{ __( 'Messages', 'ordervox' ) }</h2>
					</CardHeader>
					<CardBody>
						<div className="ordervox-stats">
							{ COUNT_LABELS.map( ( status ) => (
								<div key={ status } className="ordervox-stat">
									<span className="ordervox-stat__value">
										{ health.counts[ status ] || 0 }
									</span>
									<StatusBadge status={ status } />
								</div>
							) ) }
						</div>
					</CardBody>
				</Card>
			) }
		</div>
	);
}
