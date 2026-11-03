/**
 * Small motion helpers that respect the user's reduced-motion setting.
 */
import { useEffect, useState } from '@wordpress/element';

/**
 * Whether the user asked the system to reduce motion.
 *
 * @return {boolean} True to skip animations.
 */
export const prefersReducedMotion = () =>
	typeof window !== 'undefined' &&
	!! window.matchMedia?.( '(prefers-reduced-motion: reduce)' ).matches;

/**
 * Counts up from 0 to a target number once, easing out over ~700ms.
 *
 * @param {number} target Final value.
 * @return {number} Current value to display.
 */
export function useCountUp( target ) {
	const [ value, setValue ] = useState( prefersReducedMotion() ? target : 0 );

	useEffect( () => {
		if ( prefersReducedMotion() || ! target ) {
			setValue( target );
			return;
		}
		let frame;
		const start = window.performance.now();
		const tick = ( now ) => {
			const progress = Math.min( 1, ( now - start ) / 700 );
			setValue(
				Math.round( target * ( 1 - Math.pow( 1 - progress, 3 ) ) )
			);
			if ( progress < 1 ) {
				frame = window.requestAnimationFrame( tick );
			}
		};
		frame = window.requestAnimationFrame( tick );
		return () => window.cancelAnimationFrame( frame );
	}, [ target ] );

	return value;
}
