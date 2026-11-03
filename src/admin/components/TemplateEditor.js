import { useEffect, useRef, useState } from '@wordpress/element';
import {
	Button,
	Modal,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import {
	errorMessage,
	getAlertTemplate,
	previewAlertTemplate,
	resetAlertTemplate,
	saveAlertTemplate,
	submitAlertTemplate,
} from '../api';
import StatusBadge from './StatusBadge';

const GROUP_LABELS = {
	order: __( 'Order', 'ordervox' ),
	refund: __( 'Refund', 'ordervox' ),
	customer: __( 'Customer', 'ordervox' ),
	product: __( 'Product', 'ordervox' ),
	review: __( 'Review', 'ordervox' ),
	summary: __( 'Summary', 'ordervox' ),
	store: __( 'Store', 'ordervox' ),
};

/**
 * Status line for the submitted and active versions.
 *
 * @param {Object} props
 * @param {Object} props.template Template state from the API.
 */
function VersionStatus( { template } ) {
	const { active, submitted } = template;
	return (
		<div className="ordervox-versions">
			{ active ? (
				<p>
					<StatusBadge status="APPROVED" />{ ' ' }
					{ sprintf(
						/* translators: %d: version number. */
						__(
							'Version %d is approved and used for alerts.',
							'ordervox'
						),
						active.version
					) }
				</p>
			) : (
				<p>
					<StatusBadge status="none" />{ ' ' }
					{ __(
						'No approved version yet, so this alert cannot be sent.',
						'ordervox'
					) }
				</p>
			) }
			{ submitted &&
				( ! active || submitted.version !== active.version ) && (
					<p>
						<StatusBadge status={ submitted.status } />{ ' ' }
						{ sprintf(
							/* translators: %d: version number. */
							__( 'Version %d was sent to Meta.', 'ordervox' ),
							submitted.version
						) }
						{ submitted.reason && ` ${ submitted.reason }` }
					</p>
				) }
		</div>
	);
}

/**
 * Editor for one alert's message, with placeholder picker and live preview.
 *
 * @param {Object}     props
 * @param {string}     props.alert   Alert key.
 * @param {() => void} props.onClose Closes the editor.
 */
export default function TemplateEditor( { alert, onClose } ) {
	const [ template, setTemplate ] = useState( null );
	const [ body, setBody ] = useState( '' );
	const [ footer, setFooter ] = useState( '' );
	const [ preview, setPreview ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );
	const textarea = useRef( null );

	const apply = ( data ) => {
		setTemplate( data );
		setBody( data.body );
		setFooter( data.footer );
	};

	useEffect( () => {
		getAlertTemplate( alert )
			.then( apply )
			.catch( ( e ) => setError( errorMessage( e ) ) );
	}, [ alert ] );

	// Live preview, half a second after typing stops.
	useEffect( () => {
		if ( ! template ) {
			return;
		}
		const timer = setTimeout( () => {
			previewAlertTemplate( alert, { body, footer } )
				.then( setPreview )
				.catch( () => {} );
		}, 500 );
		return () => clearTimeout( timer );
	}, [ alert, body, footer, template ] );

	const run = async ( action, message ) => {
		setBusy( true );
		setError( '' );
		setNotice( '' );
		try {
			apply( await action() );
			setNotice( message );
		} catch ( e ) {
			setError( errorMessage( e ) );
		} finally {
			setBusy( false );
		}
	};

	const insert = ( name ) => {
		if ( ! name ) {
			return;
		}
		const token = `{${ name }}`;
		const el = textarea.current;
		if ( ! el ) {
			setBody( body + token );
			return;
		}
		const start = el.selectionStart ?? body.length;
		const end = el.selectionEnd ?? body.length;
		setBody( body.slice( 0, start ) + token + body.slice( end ) );
		setTimeout( () => {
			el.focus();
			el.selectionStart = el.selectionEnd = start + token.length;
		} );
	};

	const placeholderOptions = template
		? [
				{ value: '', label: __( 'Insert a placeholder…', 'ordervox' ) },
				...template.placeholders.map( ( p ) => ( {
					value: p.name,
					label: `${ GROUP_LABELS[ p.group ] || p.group }: ${
						p.label
					} {${ p.name }}`,
				} ) ),
			]
		: [];

	const errors = preview?.errors ?? template?.errors ?? [];
	const warnings = preview?.warnings ?? template?.warnings ?? [];

	return (
		<Modal
			title={
				template
					? sprintf(
							/* translators: %s: alert name. */
							__( 'Edit message: %s', 'ordervox' ),
							template.label
						)
					: __( 'Edit message', 'ordervox' )
			}
			onRequestClose={ onClose }
			size="large"
			className="ordervox-editor"
		>
			{ ! template && ! error && <Spinner /> }
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

			{ template && (
				<>
					<VersionStatus template={ template } />

					<div className="ordervox-editor__columns">
						<div>
							<SelectControl
								label={ __( 'Placeholders', 'ordervox' ) }
								hideLabelFromVision
								value=""
								options={ placeholderOptions }
								onChange={ insert }
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
							<TextareaControl
								ref={ textarea }
								label={ __( 'Message', 'ordervox' ) }
								value={ body }
								onChange={ setBody }
								rows={ 12 }
								__nextHasNoMarginBottom
							/>
							<TextControl
								label={ __( 'Footer (optional)', 'ordervox' ) }
								help={ __(
									'Up to 60 characters, no placeholders.',
									'ordervox'
								) }
								value={ footer }
								onChange={ setFooter }
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
						</div>

						<div>
							<p className="ordervox-editor__label">
								{ preview?.uses_sample
									? __(
											'Preview with sample data',
											'ordervox'
										)
									: __(
											'Preview with your latest data',
											'ordervox'
										) }
							</p>
							<div className="ordervox-bubble">
								<div className="ordervox-bubble__text">
									{ preview ? preview.text : body }
								</div>
								{ footer && (
									<div className="ordervox-bubble__footer">
										{ footer }
									</div>
								) }
							</div>
						</div>
					</div>

					{ errors.map( ( message ) => (
						<Notice
							key={ message }
							status="error"
							isDismissible={ false }
						>
							{ message }
						</Notice>
					) ) }
					{ warnings.map( ( message ) => (
						<Notice
							key={ message }
							status="warning"
							isDismissible={ false }
						>
							{ message }
						</Notice>
					) ) }

					<p className="description">
						{ __(
							'WhatsApp reviews every change. Your edited message is used once Meta approves it; until then the approved version keeps sending.',
							'ordervox'
						) }
					</p>

					<div className="ordervox-editor__actions">
						<Button
							variant="tertiary"
							isDestructive
							disabled={ busy }
							onClick={ () =>
								run(
									() => resetAlertTemplate( alert ),
									__( 'Default text restored.', 'ordervox' )
								)
							}
						>
							{ __( 'Restore default', 'ordervox' ) }
						</Button>
						<Button
							variant="secondary"
							disabled={ busy }
							onClick={ () =>
								run(
									() =>
										saveAlertTemplate( alert, {
											body,
											footer,
										} ),
									__( 'Draft saved.', 'ordervox' )
								)
							}
						>
							{ __( 'Save draft', 'ordervox' ) }
						</Button>
						<Button
							variant="primary"
							isBusy={ busy }
							disabled={ busy || errors.length > 0 }
							onClick={ () =>
								run(
									() =>
										submitAlertTemplate( alert, {
											body,
											footer,
										} ),
									__(
										'Sent to Meta for review. Approval usually takes a few minutes.',
										'ordervox'
									)
								)
							}
						>
							{ __( 'Save and submit to WhatsApp', 'ordervox' ) }
						</Button>
					</div>
				</>
			) }
		</Modal>
	);
}
