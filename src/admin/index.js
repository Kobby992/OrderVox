/**
 * Ordervox admin app: dashboard, alerts, setup wizard and message log.
 */
import {
	createRoot,
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { getSettings } from './api';
import Alerts from './components/Alerts';
import BrandMark from './components/BrandMark';
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

/**
 * Segmented tab bar with a highlight that slides to the active tab.
 *
 * @param {Object}                 props
 * @param {string}                 props.active   Active tab name.
 * @param {(name: string) => void} props.onSelect Called with the chosen tab.
 */
function TabBar( { active, onSelect } ) {
	const refs = useRef( {} );
	const [ indicator, setIndicator ] = useState( null );

	useLayoutEffect( () => {
		const measure = () => {
			const el = refs.current[ active ];
			if ( el ) {
				setIndicator( { left: el.offsetLeft, width: el.offsetWidth } );
			}
		};
		measure();
		window.addEventListener( 'resize', measure );
		return () => window.removeEventListener( 'resize', measure );
	}, [ active ] );

	// Arrow keys move between tabs, as the ARIA tabs pattern expects.
	const onKeyDown = ( event ) => {
		const step = { ArrowRight: 1, ArrowLeft: -1 }[ event.key ];
		if ( ! step ) {
			return;
		}
		event.preventDefault();
		const index = TABS.findIndex( ( t ) => t.name === active );
		const next = TABS[ ( index + step + TABS.length ) % TABS.length ].name;
		onSelect( next );
		refs.current[ next ]?.focus();
	};

	return (
		<div
			className="ovx-tabs"
			role="tablist"
			aria-label={ __( 'Ordervox sections', 'ordervox' ) }
		>
			{ indicator && (
				<span
					className="ovx-tabs__indicator"
					style={ {
						transform: `translateX(${ indicator.left }px)`,
						width: indicator.width,
					} }
					aria-hidden="true"
				/>
			) }
			{ TABS.map( ( tab ) => (
				<button
					key={ tab.name }
					ref={ ( el ) => ( refs.current[ tab.name ] = el ) }
					type="button"
					role="tab"
					id={ `ovx-tab-${ tab.name }` }
					aria-selected={ active === tab.name }
					aria-controls="ovx-panel"
					tabIndex={ active === tab.name ? 0 : -1 }
					className={
						'ovx-tabs__tab' +
						( active === tab.name ? ' is-active' : '' )
					}
					onClick={ () => onSelect( tab.name ) }
					onKeyDown={ onKeyDown }
				>
					{ tab.title }
				</button>
			) ) }
		</div>
	);
}

function App() {
	const [ tab, setTab ] = useState( null );
	const [ direction, setDirection ] = useState( 'forward' );
	const [ connected, setConnected ] = useState( null );

	useEffect( () => {
		getSettings()
			.then( ( data ) => {
				setConnected( !! data.configured );
				setTab( data.configured ? 'dashboard' : 'setup' );
			} )
			.catch( () => setTab( 'dashboard' ) );
	}, [] );

	// Pages slide in from the side the user navigated towards.
	const openTab = ( name ) => {
		if ( name === tab ) {
			return;
		}
		const from = TABS.findIndex( ( t ) => t.name === tab );
		const to = TABS.findIndex( ( t ) => t.name === name );
		setDirection( to >= from ? 'forward' : 'back' );
		setTab( name );
	};

	return (
		<div className="ordervox-admin">
			<header className="ovx-header">
				<div className="ovx-header__brand">
					<BrandMark />
					<div>
						<h1 className="ovx-header__title">Ordervox</h1>
						<p className="ovx-header__subtitle">
							{ __(
								'Store alerts and customer updates on WhatsApp',
								'ordervox'
							) }
						</p>
					</div>
				</div>
				{ null !== connected && (
					<span
						className={
							'ovx-connection' +
							( connected ? ' is-connected' : '' )
						}
					>
						<span
							className="ovx-connection__dot"
							aria-hidden="true"
						/>
						{ connected
							? __( 'WhatsApp connected', 'ordervox' )
							: __( 'Not connected yet', 'ordervox' ) }
					</span>
				) }
				{ tab && <TabBar active={ tab } onSelect={ openTab } /> }
			</header>

			{ ! tab ? (
				<Spinner />
			) : (
				<div
					key={ tab }
					id="ovx-panel"
					role="tabpanel"
					aria-labelledby={ `ovx-tab-${ tab }` }
					className={ `ovx-page ovx-page--${ direction }` }
				>
					{ tab === 'dashboard' && (
						<Dashboard onOpenSetup={ () => openTab( 'setup' ) } />
					) }
					{ tab === 'setup' && (
						<SetupWizard
							onDone={ () => {
								setConnected( true );
								openTab( 'dashboard' );
							} }
						/>
					) }
					{ tab === 'alerts' && <Alerts /> }
					{ tab === 'log' && <MessageLog /> }
				</div>
			) }
		</div>
	);
}

const root = document.getElementById( 'ordervox-admin-root' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
