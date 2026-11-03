import { useEffect, useState } from '@wordpress/element';
import { Button, Notice, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { errorMessage, getHealth } from '../api';
import { useCountUp } from '../motion';
import StatusBadge from './StatusBadge';

const CHECKS = {
	credentials: {
		label: __( 'WhatsApp connection', 'ordervox' ),
		icon: 'admin-links',
	},
	token: { label: __( 'Access token', 'ordervox' ), icon: 'admin-network' },
	phone: { label: __( 'Business number', 'ordervox' ), icon: 'phone' },
	owner_phone: {
		label: __( 'Alert number', 'ordervox' ),
		icon: 'smartphone',
	},
	webhook: { label: __( 'Delivery updates', 'ordervox' ), icon: 'update' },
	queue: { label: __( 'Message queue', 'ordervox' ), icon: 'list-view' },
};

const STATS = [
	{ status: 'sent', label: __( 'Sent', 'ordervox' ), hue: 'sky' },
	{ status: 'delivered', label: __( 'Delivered', 'ordervox' ), hue: 'teal' },
	{ status: 'read', label: __( 'Read', 'ordervox' ), hue: 'violet' },
	{ status: 'queued', label: __( 'Waiting', 'ordervox' ), hue: 'amber' },
	{ status: 'failed', label: __( 'Failed', 'ordervox' ), hue: 'rose' },
];

const SUMMARY = {
	ok: __( 'Everything is working', 'ordervox' ),
	warning: __( 'Something needs your attention', 'ordervox' ),
	error: __( 'WhatsApp messages may not be sending', 'ordervox' ),
};

/**
 * One colour tile with a number that counts up.
 *
 * @param {Object} props
 * @param {string} props.label Tile label.
 * @param {number} props.value Number to show.
 * @param {string} props.hue   Colour name.
 */
function StatTile( { label, value, hue } ) {
	const shown = useCountUp( value );
	return (
		<div className={ `ovx-stat ovx-hue--${ hue }` }>
			<span className="ovx-stat__value">{ shown }</span>
			<span className="ovx-stat__label">{ label }</span>
		</div>
	);
}

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

	if ( ! health ) {
		return error ? (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		) : (
			<Spinner />
		);
	}

	const counts = health.counts || {};
	const total = STATS.reduce(
		( sum, s ) => sum + ( counts[ s.status ] || 0 ),
		0
	);

	return (
		<div className="ovx-dashboard">
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			<section className={ `ovx-health ovx-health--${ health.status }` }>
				<div className="ovx-health__head">
					<div>
						<h2 className="ovx-health__title">
							{ SUMMARY[ health.status ] }
						</h2>
						{ health.checked_at && (
							<p className="ovx-muted">
								{ sprintf(
									/* translators: %s: date and time. */
									__( 'Checked %s', 'ordervox' ),
									new Date(
										health.checked_at * 1000
									).toLocaleString()
								) }
							</p>
						) }
					</div>
					<div className="ovx-actions">
						{ health.checks.credentials?.status !== 'ok' && (
							<Button variant="primary" onClick={ onOpenSetup }>
								{ __( 'Connect WhatsApp', 'ordervox' ) }
							</Button>
						) }
						<Button
							variant="secondary"
							onClick={ () => load( true ) }
							isBusy={ loading }
							disabled={ loading }
						>
							{ __( 'Run checks now', 'ordervox' ) }
						</Button>
					</div>
				</div>

				<ul className="ovx-checks">
					{ Object.entries( health.checks ).map(
						( [ key, check ], index ) => (
							<li
								key={ key }
								className={ `ovx-check ovx-check--${ check.status }` }
								style={ { '--ovx-delay': `${ index * 50 }ms` } }
							>
								<span
									className={ `ovx-check__icon dashicons dashicons-${
										CHECKS[ key ]?.icon || 'yes'
									}` }
									aria-hidden="true"
								/>
								<span className="ovx-check__text">
									<strong>
										{ CHECKS[ key ]?.label || key }
									</strong>
									<span>{ check.message }</span>
								</span>
								<StatusBadge status={ check.status } />
							</li>
						)
					) }
				</ul>
			</section>

			<section className="ovx-section">
				<h2 className="ovx-section__title">
					{ __( 'Messages', 'ordervox' ) }
				</h2>
				{ total === 0 ? (
					<p className="ovx-empty">
						{ __(
							'No messages yet. Switch on alerts and they will be counted here.',
							'ordervox'
						) }
					</p>
				) : (
					<div className="ovx-stats">
						{ STATS.map( ( stat ) => (
							<StatTile
								key={ stat.status }
								label={ stat.label }
								value={ counts[ stat.status ] || 0 }
								hue={ stat.hue }
							/>
						) ) }
					</div>
				) }
			</section>
		</div>
	);
}
