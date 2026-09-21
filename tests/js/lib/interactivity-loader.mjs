/** Node module hook: resolve the bare `@wordpress/interactivity` specifier to the stub. */
export async function resolve( specifier, context, nextResolve ) {
	if ( '@wordpress/interactivity' === specifier ) {
		return { url: new URL( './interactivity-stub.mjs', import.meta.url ).href, shortCircuit: true };
	}
	return nextResolve( specifier, context );
}
