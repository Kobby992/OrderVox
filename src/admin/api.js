/**
 * Thin wrappers around the ordervox/v1 REST routes.
 */
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

const base = '/ordervox/v1';

export const getSettings = () => apiFetch( { path: `${ base }/settings` } );

export const saveSettings = ( data ) =>
	apiFetch( { path: `${ base }/settings`, method: 'POST', data } );

export const verifyConnection = () =>
	apiFetch( { path: `${ base }/connection/verify`, method: 'POST' } );

export const subscribeWebhooks = () =>
	apiFetch( { path: `${ base }/connection/subscribe`, method: 'POST' } );

export const sendTestMessage = ( data ) =>
	apiFetch( {
		path: `${ base }/connection/test-message`,
		method: 'POST',
		data,
	} );

export const getTemplates = ( refresh = false ) =>
	apiFetch( {
		path: addQueryArgs(
			`${ base }/templates`,
			refresh ? { refresh: 1 } : {}
		),
	} );

export const getHealth = ( refresh = false ) =>
	apiFetch( {
		path: addQueryArgs( `${ base }/health`, refresh ? { refresh: 1 } : {} ),
	} );

export const getMessages = ( query ) =>
	apiFetch( { path: addQueryArgs( `${ base }/messages`, query ) } );

export const getMessage = ( id ) =>
	apiFetch( { path: `${ base }/messages/${ id }` } );

export const resendMessage = ( id ) =>
	apiFetch( { path: `${ base }/messages/${ id }/resend`, method: 'POST' } );

/**
 * Turns a REST error into a readable sentence.
 *
 * @param {Object} error Error thrown by apiFetch.
 * @return {string} Message.
 */
export const errorMessage = ( error ) =>
	error?.message || 'Something went wrong. Please try again.';
