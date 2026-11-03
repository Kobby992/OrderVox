import { useEffect, useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	Notice,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { errorMessage, getMessages, resendMessage } from '../api';
import StatusBadge from './StatusBadge';

const PER_PAGE = 20;

const STATUS_OPTIONS = [
	{ value: '', label: __( 'All statuses', 'ordervox' ) },
	{ value: 'queued', label: __( 'Queued', 'ordervox' ) },
	{ value: 'sent', label: __( 'Sent', 'ordervox' ) },
	{ value: 'delivered', label: __( 'Delivered', 'ordervox' ) },
	{ value: 'read', label: __( 'Read', 'ordervox' ) },
	{ value: 'failed', label: __( 'Failed', 'ordervox' ) },
];

/**
 * Paged list of sent messages with their delivery status.
 */
export default function MessageLog() {
	const [ status, setStatus ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ result, setResult ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );

	const load = () => {
		setLoading( true );
		setError( '' );
		getMessages( { status, page, per_page: PER_PAGE } )
			.then( setResult )
			.catch( ( e ) => setError( errorMessage( e ) ) )
			.finally( () => setLoading( false ) );
	};

	useEffect( load, [ status, page ] );

	const resend = ( id ) =>
		resendMessage( id )
			.then( load )
			.catch( ( e ) => setError( errorMessage( e ) ) );

	const pages = result
		? Math.max( 1, Math.ceil( result.total / PER_PAGE ) )
		: 1;

	return (
		<Card>
			<CardBody>
				<div className="ordervox-toolbar">
					<SelectControl
						label={ __( 'Status', 'ordervox' ) }
						hideLabelFromVision
						value={ status }
						options={ STATUS_OPTIONS }
						onChange={ ( value ) => {
							setStatus( value );
							setPage( 1 );
						} }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<Button
						variant="secondary"
						onClick={ load }
						disabled={ loading }
					>
						{ __( 'Refresh', 'ordervox' ) }
					</Button>
				</div>

				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				{ loading && ! result && <Spinner /> }

				{ result && result.items.length === 0 && (
					<p>{ __( 'No messages yet.', 'ordervox' ) }</p>
				) }

				{ result && result.items.length > 0 && (
					<table className="widefat striped ordervox-log">
						<thead>
							<tr>
								<th>{ __( 'Time', 'ordervox' ) }</th>
								<th>{ __( 'Event', 'ordervox' ) }</th>
								<th>{ __( 'To', 'ordervox' ) }</th>
								<th>{ __( 'Template', 'ordervox' ) }</th>
								<th>{ __( 'Status', 'ordervox' ) }</th>
								<th>
									<span className="screen-reader-text">
										{ __( 'Actions', 'ordervox' ) }
									</span>
								</th>
							</tr>
						</thead>
						<tbody>
							{ result.items.map( ( row ) => (
								<tr key={ row.id }>
									<td>
										{ new Date(
											row.created_at
										).toLocaleString() }
									</td>
									<td>
										{ row.event }
										{ row.object_id > 0 &&
											` #${ row.object_id }` }
									</td>
									<td>{ row.recipient }</td>
									<td>
										{ row.template }
										<span className="description">
											{ ' ' }
											{ row.language }
										</span>
									</td>
									<td>
										<StatusBadge status={ row.status } />
										{ row.error_message && (
											<div className="ordervox-log__error">
												{ row.error_message }
											</div>
										) }
									</td>
									<td>
										{ row.status === 'failed' && (
											<Button
												variant="link"
												onClick={ () =>
													resend( row.id )
												}
											>
												{ __( 'Resend', 'ordervox' ) }
											</Button>
										) }
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				) }

				{ result && pages > 1 && (
					<div className="ordervox-pagination">
						<Button
							variant="secondary"
							disabled={ page <= 1 }
							onClick={ () => setPage( page - 1 ) }
						>
							{ __( 'Previous', 'ordervox' ) }
						</Button>
						<span>
							{ sprintf(
								/* translators: 1: current page, 2: total pages. */
								__( 'Page %1$d of %2$d', 'ordervox' ),
								page,
								pages
							) }
						</span>
						<Button
							variant="secondary"
							disabled={ page >= pages }
							onClick={ () => setPage( page + 1 ) }
						>
							{ __( 'Next', 'ordervox' ) }
						</Button>
					</div>
				) }
			</CardBody>
		</Card>
	);
}
