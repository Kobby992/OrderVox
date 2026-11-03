import { useEffect, useMemo, useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardFooter,
	CardHeader,
	ExternalLink,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import {
	errorMessage,
	getMessage,
	getSettings,
	getTemplates,
	saveSettings,
	sendTestMessage,
	subscribeWebhooks,
	verifyConnection,
} from '../api';
import CopyField from './CopyField';
import StatusBadge from './StatusBadge';

const STEPS = [
	__( 'Welcome', 'ordervox' ),
	__( 'Connect', 'ordervox' ),
	__( 'Webhook', 'ordervox' ),
	__( 'Alert number', 'ordervox' ),
	__( 'Test message', 'ordervox' ),
];

const CREDENTIAL_FIELDS = [
	{
		key: 'phone_number_id',
		label: __( 'Phone Number ID', 'ordervox' ),
		help: __( 'WhatsApp → API Setup in your Meta app.', 'ordervox' ),
	},
	{
		key: 'waba_id',
		label: __( 'WhatsApp Business Account ID', 'ordervox' ),
		help: __( 'WhatsApp → API Setup in your Meta app.', 'ordervox' ),
	},
	{
		key: 'access_token',
		label: __( 'Permanent access token', 'ordervox' ),
		help: __(
			'Business Settings → System users → Generate token, with whatsapp_business_messaging and whatsapp_business_management. The temporary token on the API Setup page expires after 24 hours.',
			'ordervox'
		),
		secret: true,
	},
	{
		key: 'app_id',
		label: __( 'App ID', 'ordervox' ),
		help: __( 'App settings → Basic.', 'ordervox' ),
	},
	{
		key: 'app_secret',
		label: __( 'App Secret', 'ordervox' ),
		help: __(
			'App settings → Basic. Used to check that webhook calls really come from Meta.',
			'ordervox'
		),
		secret: true,
	},
];

const FINAL_STATUSES = [ 'delivered', 'read', 'failed' ];

/**
 * Space-separated class names whose value is true.
 *
 * @param {Object<string, boolean>} map Class name → enabled.
 * @return {string} Class attribute.
 */
const classnames = ( map ) =>
	Object.keys( map )
		.filter( ( name ) => map[ name ] )
		.join( ' ' );

/**
 * Step-by-step connection setup (spec §9.1).
 *
 * @param {Object}     props
 * @param {() => void} props.onDone Called when the user finishes.
 */
export default function SetupWizard( { onDone } ) {
	const [ step, setStep ] = useState( 0 );
	const [ data, setData ] = useState( null );
	const [ credentials, setCredentials ] = useState( {} );
	const [ ownerPhone, setOwnerPhone ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );
	const [ phoneInfo, setPhoneInfo ] = useState( null );
	const [ warnings, setWarnings ] = useState( [] );

	const [ templates, setTemplates ] = useState( null );
	const [ templateKey, setTemplateKey ] = useState( '' );
	const [ variables, setVariables ] = useState( [] );
	const [ testRow, setTestRow ] = useState( null );

	const applySettings = ( response ) => {
		setData( response );
		setCredentials( response.credentials );
		setOwnerPhone( response.settings.owner_phone || '' );
	};

	useEffect( () => {
		getSettings()
			.then( applySettings )
			.catch( ( e ) => setError( errorMessage( e ) ) );
	}, [] );

	const run = async ( action ) => {
		setBusy( true );
		setError( '' );
		setNotice( '' );
		try {
			await action();
		} catch ( e ) {
			setError( errorMessage( e ) );
		} finally {
			setBusy( false );
		}
	};

	const go = ( next ) => {
		setError( '' );
		setNotice( '' );
		setStep( next );
	};

	// Step 1: save credentials, then check them against Meta.
	const saveAndVerify = () =>
		run( async () => {
			applySettings( await saveSettings( { credentials } ) );
			const result = await verifyConnection();
			setPhoneInfo( result.phone );
			setWarnings( result.warnings || [] );
		} );

	// Step 2: subscribe the app and re-read when Meta last called.
	const subscribe = () =>
		run( async () => {
			await subscribeWebhooks();
			setNotice(
				__(
					'Your app is subscribed to this WhatsApp Business Account.',
					'ordervox'
				)
			);
		} );

	const refreshWebhook = () =>
		run( async () => applySettings( await getSettings() ) );

	// Step 3: save the alert number.
	const saveOwnerPhone = () =>
		run( async () => {
			applySettings(
				await saveSettings( { settings: { owner_phone: ownerPhone } } )
			);
			go( 4 );
		} );

	// Step 4: load approved templates for the test message.
	useEffect( () => {
		if ( step !== 4 || templates ) {
			return;
		}
		run( async () => {
			const list = ( await getTemplates() ).filter(
				( t ) => t.status === 'APPROVED'
			);
			setTemplates( list );
			const hello = list.find( ( t ) => t.name === 'hello_world' );
			const first = hello || list[ 0 ];
			if ( first ) {
				setTemplateKey( `${ first.name }|${ first.language }` );
			}
		} );
	}, [ step ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const selectedTemplate = useMemo(
		() =>
			( templates || [] ).find(
				( t ) => `${ t.name }|${ t.language }` === templateKey
			),
		[ templates, templateKey ]
	);

	useEffect( () => {
		setVariables( Array( selectedTemplate?.variables || 0 ).fill( '' ) );
	}, [ selectedTemplate ] );

	const sendTest = () =>
		run( async () => {
			setTestRow( null );
			const row = await sendTestMessage( {
				template: selectedTemplate.name,
				language: selectedTemplate.language,
				variables,
			} );
			setTestRow( row );
		} );

	// Poll the log row until Meta reports delivery, for up to a minute.
	useEffect( () => {
		if ( ! testRow || FINAL_STATUSES.includes( testRow.status ) ) {
			return;
		}
		let polls = 0;
		const timer = setInterval( () => {
			polls++;
			getMessage( testRow.id )
				.then( ( row ) => {
					setTestRow( row );
					if (
						FINAL_STATUSES.includes( row.status ) ||
						polls >= 20
					) {
						clearInterval( timer );
					}
				} )
				.catch( () => clearInterval( timer ) );
		}, 3000 );
		return () => clearInterval( timer );
	}, [ testRow?.id ] ); // eslint-disable-line react-hooks/exhaustive-deps

	if ( ! data ) {
		return error ? (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		) : (
			<Spinner />
		);
	}

	const lastReceived = data.webhook.last_received;

	return (
		<Card className="ordervox-wizard ovx-card ovx-card--indigo">
			<CardHeader>
				<ol className="ordervox-steps">
					{ STEPS.map( ( label, index ) => (
						<li
							key={ label }
							className={ classnames( {
								'is-current': index === step,
								'is-done': index < step,
							} ) }
							aria-current={ index === step ? 'step' : undefined }
						>
							{ label }
						</li>
					) ) }
				</ol>
				<div className="ovx-progress" aria-hidden="true">
					<span
						style={ {
							transform: `scaleX(${ step / ( STEPS.length - 1 ) })`,
						} }
					/>
				</div>
			</CardHeader>

			<CardBody key={ step } className="ovx-step">
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

				{ step === 0 && (
					<>
						<h2>
							{ __(
								'Send store alerts to WhatsApp',
								'ordervox'
							) }
						</h2>
						<p>
							{ __(
								'Ordervox uses the official WhatsApp Cloud API from Meta. Before you start, you need:',
								'ordervox'
							) }
						</p>
						<ul className="ordervox-list">
							<li>
								{ __(
									'A Meta Business portfolio and a Meta developer app with the WhatsApp product added.',
									'ordervox'
								) }
							</li>
							<li>
								{ __(
									'A phone number registered with the Cloud API (Meta’s free test number works while you set up).',
									'ordervox'
								) }
							</li>
							<li>
								{ __(
									'A permanent System User access token.',
									'ordervox'
								) }
							</li>
						</ul>
						<ExternalLink href="https://developers.facebook.com/docs/whatsapp/cloud-api/get-started">
							{ __(
								'Meta’s Cloud API getting-started guide',
								'ordervox'
							) }
						</ExternalLink>
					</>
				) }

				{ step === 1 && (
					<>
						<h2>{ __( 'Connect your Meta app', 'ordervox' ) }</h2>
						{ CREDENTIAL_FIELDS.map( ( field ) => (
							<TextControl
								key={ field.key }
								label={ field.label }
								help={ field.help }
								type={ field.secret ? 'password' : 'text' }
								autoComplete="off"
								value={ credentials[ field.key ] || '' }
								onChange={ ( value ) =>
									setCredentials( {
										...credentials,
										[ field.key ]: value,
									} )
								}
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
						) ) }
						{ phoneInfo && (
							<Notice status="success" isDismissible={ false }>
								{ sprintf(
									/* translators: 1: business name, 2: phone number. */
									__(
										'Connected as %1$s (%2$s).',
										'ordervox'
									),
									phoneInfo.verified_name || '',
									phoneInfo.display_phone_number || ''
								) }
							</Notice>
						) }
						{ warnings.map( ( warning ) => (
							<Notice
								key={ warning }
								status="warning"
								isDismissible={ false }
							>
								{ warning }
							</Notice>
						) ) }
					</>
				) }

				{ step === 2 && (
					<>
						<h2>
							{ __( 'Receive delivery updates', 'ordervox' ) }
						</h2>
						<p>
							{ __(
								'In your Meta app, open WhatsApp → Configuration → Webhook, click Edit, and paste these values. Then subscribe to the "messages" and "message_template_status_update" fields.',
								'ordervox'
							) }
						</p>
						<CopyField
							label={ __( 'Callback URL', 'ordervox' ) }
							value={ data.webhook.url }
						/>
						<CopyField
							label={ __( 'Verify token', 'ordervox' ) }
							value={ data.webhook.verify_token }
						/>
						<p>
							<Button
								variant="secondary"
								onClick={ subscribe }
								disabled={ busy }
							>
								{ __(
									'Subscribe app to this account',
									'ordervox'
								) }
							</Button>{ ' ' }
							<Button
								variant="tertiary"
								onClick={ refreshWebhook }
								disabled={ busy }
							>
								{ __( 'Check again', 'ordervox' ) }
							</Button>
						</p>
						<p>
							{ lastReceived ? (
								<>
									<StatusBadge status="ok" />{ ' ' }
									{ sprintf(
										/* translators: %s: date and time. */
										__(
											'Meta last reached your site on %s.',
											'ordervox'
										),
										new Date(
											lastReceived * 1000
										).toLocaleString()
									) }
								</>
							) : (
								<>
									<StatusBadge status="warning" />{ ' ' }
									{ __(
										'Meta has not reached your site yet. Your site must be on public HTTPS.',
										'ordervox'
									) }
								</>
							) }
						</p>
					</>
				) }

				{ step === 3 && (
					<>
						<h2>{ __( 'Where should alerts go?', 'ordervox' ) }</h2>
						<TextControl
							label={ __( 'Your WhatsApp number', 'ordervox' ) }
							help={ sprintf(
								/* translators: %s: country calling code. */
								__(
									'Use international format, e.g. +14155550123. Numbers without a country code are treated as +%s. This must be a different number from your business number.',
									'ordervox'
								),
								data.calling_code || '…'
							) }
							type="tel"
							value={ ownerPhone }
							onChange={ setOwnerPhone }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					</>
				) }

				{ step === 4 && (
					<>
						<h2>{ __( 'Send a test message', 'ordervox' ) }</h2>
						{ templates && templates.length === 0 && (
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'No approved templates found in this WhatsApp Business Account. New accounts include "hello_world". Create the Ordervox alert templates on the Alerts tab.',
									'ordervox'
								) }
							</Notice>
						) }
						{ templates && templates.length > 0 && (
							<>
								<SelectControl
									label={ __( 'Template', 'ordervox' ) }
									value={ templateKey }
									options={ templates.map( ( t ) => ( {
										value: `${ t.name }|${ t.language }`,
										label: `${ t.name } (${ t.language })`,
									} ) ) }
									onChange={ setTemplateKey }
									__nextHasNoMarginBottom
									__next40pxDefaultSize
								/>
								{ selectedTemplate?.body && (
									<p className="ordervox-preview">
										{ selectedTemplate.body }
									</p>
								) }
								{ variables.map( ( value, index ) => (
									<TextControl
										key={ index }
										label={ sprintf(
											/* translators: %d: variable number. */
											__(
												'Sample value for {{%d}}',
												'ordervox'
											),
											index + 1
										) }
										value={ value }
										onChange={ ( text ) =>
											setVariables(
												variables.map( ( v, i ) =>
													i === index ? text : v
												)
											)
										}
										__nextHasNoMarginBottom
										__next40pxDefaultSize
									/>
								) ) }
								<p>
									{ sprintf(
										/* translators: %s: phone number. */
										__(
											'It will be sent to %s.',
											'ordervox'
										),
										data.settings.owner_phone
									) }
								</p>
								<Button
									variant="primary"
									onClick={ sendTest }
									isBusy={ busy }
									disabled={
										busy ||
										! selectedTemplate ||
										variables.some( ( v ) => ! v )
									}
								>
									{ __( 'Send test message', 'ordervox' ) }
								</Button>
							</>
						) }
						{ ! templates && busy && <Spinner /> }
						{ testRow && (
							<p
								className="ordervox-test-status"
								aria-live="polite"
							>
								<StatusBadge status={ testRow.status } />{ ' ' }
								{ testRow.status === 'read' ||
								testRow.status === 'delivered'
									? __(
											'Delivered. Check your WhatsApp.',
											'ordervox'
										)
									: testRow.error_message ||
										__(
											'Sent to WhatsApp. Waiting for the delivery report…',
											'ordervox'
										) }
							</p>
						) }
					</>
				) }
			</CardBody>

			<CardFooter>
				<Button
					variant="tertiary"
					onClick={ () => go( step - 1 ) }
					disabled={ step === 0 || busy }
				>
					{ __( 'Back', 'ordervox' ) }
				</Button>

				{ step === 0 && (
					<Button variant="primary" onClick={ () => go( 1 ) }>
						{ __( 'Start', 'ordervox' ) }
					</Button>
				) }
				{ step === 1 && ! phoneInfo && (
					<Button
						variant="primary"
						onClick={ saveAndVerify }
						isBusy={ busy }
						disabled={ busy }
					>
						{ __( 'Save and verify', 'ordervox' ) }
					</Button>
				) }
				{ step === 1 && phoneInfo && (
					<Button variant="primary" onClick={ () => go( 2 ) }>
						{ __( 'Next', 'ordervox' ) }
					</Button>
				) }
				{ step === 2 && (
					<Button variant="primary" onClick={ () => go( 3 ) }>
						{ __( 'Next', 'ordervox' ) }
					</Button>
				) }
				{ step === 3 && (
					<Button
						variant="primary"
						onClick={ saveOwnerPhone }
						isBusy={ busy }
						disabled={ busy || ! ownerPhone }
					>
						{ __( 'Save and continue', 'ordervox' ) }
					</Button>
				) }
				{ step === 4 && (
					<Button variant="primary" onClick={ onDone }>
						{ __( 'Finish', 'ordervox' ) }
					</Button>
				) }
			</CardFooter>
		</Card>
	);
}
