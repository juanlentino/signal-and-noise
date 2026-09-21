/**
 * A stand-in for `@wordpress/interactivity` just wide enough to run the
 * theme's stores under node: store() merges into a per-namespace object and
 * hands it back, getters stay getters (the client library copies property
 * descriptors the same way), and nothing is reactive. The DOM binding the
 * real runtime performs from directives is core's job and is pinned
 * server-side in tests/theme-toggle-directives.php with core's own
 * processor; here the unit is the store's state and actions.
 */
const stores = new Map();

function merge( target, source ) {
	for ( const key of Object.keys( source ) ) {
		const desc = Object.getOwnPropertyDescriptor( source, key );
		if ( desc.get || desc.set ) {
			Object.defineProperty( target, key, desc );
		} else if ( desc.value && typeof desc.value === 'object' && ! Array.isArray( desc.value ) ) {
			target[ key ] = merge( target[ key ] && typeof target[ key ] === 'object' ? target[ key ] : {}, desc.value );
		} else {
			target[ key ] = desc.value;
		}
	}
	return target;
}

/** What the server serialised into `wp-interactivity-data` for a namespace. */
export function __setServerState( namespace, state ) {
	stores.set( namespace, { state: merge( {}, state ) } );
}

export function __reset() {
	stores.clear();
}

export function store( namespace, block = {} ) {
	const merged = merge( stores.get( namespace ) || {}, block );
	stores.set( namespace, merged );
	return merged;
}

export function getContext() {
	return {};
}

export function getElement() {
	return { ref: null, attributes: {} };
}
