/**
 * Ordervox admin app: dashboard, setup wizard and message log.
 */
import { createRoot, useEffect, useState } from '@wordpress/element';
import { Spinner, TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { getSettings } from './api';
import Alerts from './components/Alerts';
import Dashboard from './components/Dashboard';
import MessageLog from './components/MessageLog';
import SetupWizard from './components/SetupWizard';

import './admin.scss';

const TABS = [
	{ name: 'dashboard', title: __( 'Dashboard', 'ordervox' ) },
	{ name: 'alerts', title: __( 'Alerts', 'ordervox' ) },
	{ name: 'setup', title: __( 'Setup', 'ordervox' ) },
	{ name: 'log', title: __( 'Message log', 'ordervox' ) },
];

function App() {
	const [ initialTab, setInitialTab ] = useState( null );
	// Changing the key remounts TabPanel so it opens on initialTab.
	const [ panelKey, setPanelKey ] = useState( 0 );

	useEffect( () => {
		getSettings()
			.then( ( data ) =>
				setInitialTab( data.configured ? 'dashboard' : 'setup' )
			)
			.catch( () => setInitialTab( 'dashboard' ) );
	}, [] );

	const openTab = ( name ) => {
		setInitialTab( name );
		setPanelKey( panelKey + 1 );
	};

	return (
		<div className="ordervox-admin">
			<h1>{ __( 'Ordervox', 'ordervox' ) }</h1>
			{ ! initialTab ? (
				<Spinner />
			) : (
				<TabPanel
					key={ panelKey }
					tabs={ TABS }
					initialTabName={ initialTab }
				>
					{ ( tab ) => (
						<div className="ordervox-tab">
							{ tab.name === 'dashboard' && (
								<Dashboard
									onOpenSetup={ () => openTab( 'setup' ) }
								/>
							) }
							{ tab.name === 'setup' && (
								<SetupWizard
									onDone={ () => openTab( 'dashboard' ) }
								/>
							) }
							{ tab.name === 'alerts' && <Alerts /> }
							{ tab.name === 'log' && <MessageLog /> }
						</div>
					) }
				</TabPanel>
			) }
		</div>
	);
}

const root = document.getElementById( 'ordervox-admin-root' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
