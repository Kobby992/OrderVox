import { useEffect, useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import {
	createMissingTemplates,
	errorMessage,
	getAlerts,
	saveSettings,
	syncTemplates,
} from '../api';
import StatusBadge from './StatusBadge';
import TemplateEditor from './TemplateEditor';

/**
 * Template status to show for an alert: approved, the submitted version's status, or not created.
 *
 * @param {Object} alert Alert from the API.
 * @return {string} Status key.
 */
/**
 * Colour and Dashicon per alert. Colours follow meaning: violet for new orders, teal for money
 * in and success, sky for progress, rose for problems, amber for stock and reviews.
 */
const LOOK = {
	order_new: { hue: 'violet', icon: 'cart' },
	payment_received: { hue: 'teal', icon: 'money-alt' },
	status_processing: { hue: 'sky', icon: 'update' },
	status_completed: { hue: 'teal', icon: 'yes-alt' },
	status_cancelled: { hue: 'rose', icon: 'dismiss' },
	status_refunded: { hue: 'coral', icon: 'undo' },
	payment_failed: { hue: 'rose', icon: 'warning' },
	stock_low: { hue: 'amber', icon: 'archive' },
	review_new: { hue: 'amber', icon: 'star-filled' },
	daily_summary: { hue: 'indigo', icon: 'chart-bar' },
	customer_order_confirmed: { hue: 'violet', icon: 'format-chat' },
	customer_order_completed: { hue: 'teal', icon: 'smiley' },
};

const templateStatus = ( alert ) => {
	if ( alert.active ) {
		return 'APPROVED';
	}
	return alert.submitted ? alert.submitted.status : 'none';
};

/**
 * Alert switches, template statuses and the template editor.
 */
export default function Alerts() {
	const [ data, setData ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );
	const [ editing, setEditing ] = useState( null );
	const [ summaryTime, setSummaryTime ] = useState( '08:00' );
	const [ optinLabel, setOptinLabel ] = useState( '' );

	const apply = ( response ) => {
		setData( response );
		setSummaryTime( response.daily_summary?.time || '08:00' );
		setOptinLabel( response.customer?.optin_label || '' );
	};

	const load = () =>
		getAlerts()
			.then( apply )
			.catch( ( e ) => setError( errorMessage( e ) ) );

	useEffect( () => {
		load();
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	const run = async ( action, message = '' ) => {
		setBusy( true );
		setError( '' );
		setNotice( '' );
		try {
			const response = await action();
			if ( response?.alerts ) {
				apply( response );
			} else {
				await load();
			}
			if ( response?.errors && Object.keys( response.errors ).length ) {
				setError( Object.values( response.errors ).join( ' ' ) );
			} else if ( message ) {
				setNotice( message );
			}
		} catch ( e ) {
			setError( errorMessage( e ) );
		} finally {
			setBusy( false );
		}
	};

	// Saves one setting by dot path, e.g. "customer.notify_confirmed".
	const saveSetting = ( path, value ) =>
		run( () =>
			saveSettings( {
				settings: path
					.split( '.' )
					.reduceRight(
						( inner, key ) => ( { [ key ]: inner } ),
						value
					),
			} )
		);

	const toggle = ( alert, enabled ) => saveSetting( alert.setting, enabled );

	if ( ! data ) {
		return error ? (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		) : (
			<Spinner />
		);
	}

	const missing = data.alerts.filter( ( a ) => ! a.submitted && ! a.active );
	const customer = data.customer || {};

	const renderAlert = ( alert, index ) => (
		<li
			key={ alert.key }
			className={
				`ovx-alert ovx-hue--${ LOOK[ alert.key ]?.hue || 'indigo' }` +
				( alert.enabled ? ' is-on' : '' )
			}
			style={ { '--ovx-delay': `${ index * 40 }ms` } }
		>
			<span
				className={ `ovx-alert__icon dashicons dashicons-${
					LOOK[ alert.key ]?.icon || 'bell'
				}` }
				aria-hidden="true"
			/>
			<ToggleControl
				label={ alert.label }
				help={ alert.description }
				checked={ alert.enabled }
				disabled={ busy }
				onChange={ ( enabled ) => toggle( alert, enabled ) }
				__nextHasNoMarginBottom
			/>
			<div className="ovx-alert__meta">
				<StatusBadge status={ templateStatus( alert ) } />
				{ alert.submitted && alert.changed && (
					<span className="description">
						{ __( 'Unsubmitted changes', 'ordervox' ) }
					</span>
				) }
				<Button
					variant="link"
					onClick={ () => setEditing( alert.key ) }
				>
					{ __( 'Edit message', 'ordervox' ) }
				</Button>
			</div>
			{ alert.key === 'daily_summary' && alert.enabled && (
				<div className="ovx-alert__time">
					<TextControl
						label={ __( 'Send at (site time)', 'ordervox' ) }
						type="time"
						value={ summaryTime }
						onChange={ setSummaryTime }
						onBlur={ () =>
							saveSetting( 'daily_summary.time', summaryTime )
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</div>
			) }
		</li>
	);

	return (
		<>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ notice && (
				<Notice status="success" isDismissible={ false }>
					{ notice }
				</Notice>
			) }

			<Card className="ovx-card ovx-card--indigo">
				<CardHeader>
					<h2>{ __( 'WhatsApp templates', 'ordervox' ) }</h2>
					<div className="ovx-actions">
						<Button
							variant="secondary"
							disabled={ busy }
							onClick={ () =>
								run(
									syncTemplates,
									__(
										'Approval statuses updated.',
										'ordervox'
									)
								)
							}
						>
							{ __( 'Refresh statuses', 'ordervox' ) }
						</Button>
						<Button
							variant="primary"
							isBusy={ busy }
							disabled={ busy || missing.length === 0 }
							onClick={ () =>
								run(
									createMissingTemplates,
									__(
										'Templates sent to Meta for review. Approval usually takes a few minutes.',
										'ordervox'
									)
								)
							}
						>
							{ missing.length
								? __(
										'Create templates in WhatsApp',
										'ordervox'
									)
								: __( 'All templates created', 'ordervox' ) }
						</Button>
					</div>
				</CardHeader>
				<CardBody>
					<p>
						{ __(
							'WhatsApp only delivers business messages that use a template Meta has approved. Ordervox creates one template per alert in your WhatsApp Business Account.',
							'ordervox'
						) }
					</p>
					<SelectControl
						label={ __( 'Template language', 'ordervox' ) }
						help={ __(
							'Changing the language needs new templates in that language.',
							'ordervox'
						) }
						value={ data.language }
						options={ data.languages.map( ( code ) => ( {
							value: code,
							label: code,
						} ) ) }
						onChange={ ( code ) =>
							run( () =>
								saveSettings( {
									settings: { template_language: code },
								} )
							)
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</CardBody>
			</Card>

			<Card className="ovx-card ovx-card--violet">
				<CardHeader>
					<h2>{ __( 'Store alerts', 'ordervox' ) }</h2>
				</CardHeader>
				<CardBody>
					<ul className="ovx-alert-grid">
						{ data.alerts
							.filter( ( a ) => a.audience === 'store' )
							.map( renderAlert ) }
					</ul>
				</CardBody>
			</Card>

			<Card className="ovx-card ovx-card--teal">
				<CardHeader>
					<h2>{ __( 'Customer messages', 'ordervox' ) }</h2>
				</CardHeader>
				<CardBody>
					<p>
						{ __(
							'Customers only get WhatsApp messages if they tick the opt-in box at checkout or in their account. Every message tells them they can reply STOP to unsubscribe.',
							'ordervox'
						) }
					</p>
					<ToggleControl
						label={ __(
							'Show the WhatsApp opt-in at checkout',
							'ordervox'
						) }
						help={ __(
							'Adds an unticked checkbox to the classic and block checkout, and to My Account.',
							'ordervox'
						) }
						checked={ !! customer.optin_enabled }
						disabled={ busy }
						onChange={ ( value ) =>
							saveSetting( 'customer.optin_enabled', value )
						}
						__nextHasNoMarginBottom
					/>
					{ customer.optin_enabled && (
						<div className="ovx-optin-settings">
							<TextControl
								label={ __( 'Checkbox text', 'ordervox' ) }
								placeholder={ __(
									'Send me order updates on WhatsApp',
									'ordervox'
								) }
								value={ optinLabel }
								onChange={ setOptinLabel }
								onBlur={ () =>
									saveSetting(
										'customer.optin_label',
										optinLabel
									)
								}
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
							<ToggleControl
								label={ __(
									'Also ask for consent to offers and reminders',
									'ordervox'
								) }
								help={ __(
									'A second, separate checkbox. Needed for abandoned cart and review reminders in Ordervox Pro.',
									'ordervox'
								) }
								checked={ !! customer.marketing_optin }
								disabled={ busy }
								onChange={ ( value ) =>
									saveSetting(
										'customer.marketing_optin',
										value
									)
								}
								__nextHasNoMarginBottom
							/>
						</div>
					) }
					<ul className="ovx-alert-grid">
						{ data.alerts
							.filter( ( a ) => a.audience === 'customer' )
							.map( renderAlert ) }
					</ul>
				</CardBody>
			</Card>

			{ editing && (
				<TemplateEditor
					alert={ editing }
					onClose={ () => {
						setEditing( null );
						load();
					} }
				/>
			) }
		</>
	);
}
