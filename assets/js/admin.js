( function ( wp, config ) {
	'use strict';

	if ( ! wp || ! wp.element || ! wp.i18n || ! config ) {
		return;
	}

	const h = wp.element.createElement;
	const __ = wp.i18n.__;
	const text = function ( message ) {
		return __( message, 'wpessential-checkout-impact-regression' );
	};

	function StatusCard( props ) {
		return h(
			'section',
			{ className: 'wpecir-card', 'aria-labelledby': props.id },
			h( 'p', { className: 'wpecir-card__eyebrow' }, text( props.eyebrow ) ),
			h( 'h2', { id: props.id }, text( props.title ) ),
			h( 'p', { className: 'wpecir-card__body' }, text( props.body ) ),
			h( 'span', { className: 'wpecir-status wpecir-status--' + props.tone }, text( props.status ) )
		);
	}

	function App() {
		const isWooCommerceActive = Boolean( config.woocommerceActive );

		return h(
			'main',
			{ className: 'wpecir-app' },
			h(
				'header',
				{ className: 'wpecir-header' },
				h(
					'div',
					null,
					h( 'p', { className: 'wpecir-eyebrow' }, text( 'WPEssential' ) ),
					h( 'h1', null, text( 'Checkout Impact' ) ),
					h(
						'p',
						{ className: 'wpecir-intro' },
						text( 'Review the foundation for before-and-after checkout scenario comparisons.' )
					)
				),
				h( 'span', { className: 'wpecir-phase-badge' }, text( 'Development preview' ) )
			),
			h(
				'section',
				{
					className: 'wpecir-notice wpecir-notice--warning',
					role: 'status',
					'aria-live': 'polite',
				},
				h( 'span', { className: 'dashicons dashicons-warning', 'aria-hidden': 'true' } ),
				h(
					'div',
					null,
					h( 'h2', null, text( 'Automatic checkout checks are disabled' ) ),
					h(
						'p',
						null,
						text( 'No checkout, payment, shipping, coupon, or order requests are being run. Runtime support is not approved until an isolated, version-specific safety proof is complete.' )
					)
				)
			),
			! isWooCommerceActive
				? h(
					'div',
					{ className: 'notice notice-error inline' },
					h(
						'p',
						null,
						text( 'WooCommerce is not active. Activate WooCommerce to continue with WooCommerce-specific development.' )
					)
				)
				: null,
			h(
				'section',
				{ className: 'wpecir-section', 'aria-labelledby': 'wpecir-foundation-title' },
				h( 'h2', { id: 'wpecir-foundation-title' }, text( 'Implementation status' ) ),
				h(
					'div',
					{ className: 'wpecir-card-grid' },
					h( StatusCard, {
						id: 'wpecir-status-bootstrap',
						eyebrow: 'Platform',
						title: 'Admin shell',
						body: 'The plugin provides a capability-checked WordPress admin screen. Scenario storage and checkout behavior are not implemented.',
						status: 'Admin shell available; features pending',
						tone: 'neutral',
					} ),
					h( StatusCard, {
						id: 'wpecir-status-storage',
						eyebrow: 'Scenarios',
						title: 'Scenario storage',
						body: 'The persistence model is not implemented. No scenario data is being saved.',
						status: 'Not available',
						tone: 'muted',
					} ),
					h( StatusCard, {
						id: 'wpecir-status-runtime',
						eyebrow: 'Checkout runtime',
						title: 'Observation and replay',
						body: 'All checkout modes and integrations remain unsupported pending runtime proof.',
						status: 'Disabled',
						tone: 'warning',
					} )
				)
			),
			h(
				'section',
				{ className: 'wpecir-next-step', 'aria-labelledby': 'wpecir-next-title' },
				h( 'div', { className: 'wpecir-next-step__number', 'aria-hidden': 'true' }, '01' ),
				h(
					'div',
					null,
					h( 'h2', { id: 'wpecir-next-title' }, text( 'Next implementation slice' ) ),
					h(
						'p',
						null,
						text( 'Finalize privacy-first scenario storage and versioning before enabling the scenario builder or saving store data.' )
					)
				)
			)
		);
	}

	const rootElement = document.getElementById( 'wpecir-admin-root' );

	if ( rootElement ) {
		if ( wp.element.createRoot ) {
			wp.element.createRoot( rootElement ).render( h( App ) );
		} else if ( wp.element.render ) {
			wp.element.render( h( App ), rootElement );
		}
	}
} )( window.wp, window.WPECIRAdmin );
