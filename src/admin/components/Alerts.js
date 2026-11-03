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

	const apply = ( response ) => {
		setData( response );
		setSummaryTime( response.daily_summary?.time || '08:00' );
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

	const toggle = ( alert, enabled ) =>
		run( () =>
			saveSettings( {
				settings:
					alert.key === 'daily_summary'
						? { daily_summary: { enabled } }
						: { events: { [ alert.key ]: enabled } },
			} )
		);

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

			<Card>
				<CardHeader>
					<h2>{ __( 'WhatsApp templates', 'ordervox' ) }</h2>
					<div className="ordervox-actions">
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

			<Card>
				<CardHeader>
					<h2>{ __( 'Alerts', 'ordervox' ) }</h2>
				</CardHeader>
				<CardBody>
					<ul className="ordervox-alerts">
						{ data.alerts.map( ( alert ) => (
							<li key={ alert.key }>
								<ToggleControl
									label={ alert.label }
									help={ alert.description }
									checked={ alert.enabled }
									disabled={ busy }
									onChange={ ( enabled ) =>
										toggle( alert, enabled )
									}
									__nextHasNoMarginBottom
								/>
								<div className="ordervox-alerts__meta">
									<StatusBadge
										status={ templateStatus( alert ) }
									/>
									{ alert.submitted && alert.changed && (
										<span className="description">
											{ __(
												'Unsubmitted changes',
												'ordervox'
											) }
										</span>
									) }
									<Button
										variant="link"
										onClick={ () =>
											setEditing( alert.key )
										}
									>
										{ __( 'Edit message', 'ordervox' ) }
									</Button>
								</div>
								{ alert.key === 'daily_summary' &&
									alert.enabled && (
										<div className="ordervox-alerts__time">
											<TextControl
												label={ __(
													'Send at (site time)',
													'ordervox'
												) }
												type="time"
												value={ summaryTime }
												onChange={ setSummaryTime }
												onBlur={ () =>
													run( () =>
														saveSettings( {
															settings: {
																daily_summary: {
																	time: summaryTime,
																},
															},
														} )
													)
												}
												__nextHasNoMarginBottom
												__next40pxDefaultSize
											/>
										</div>
									) }
							</li>
						) ) }
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
