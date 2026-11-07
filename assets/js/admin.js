( function ( wp, config ) {
	'use strict';

	if ( ! wp || ! wp.element || ! wp.i18n || ! wp.apiFetch || ! config ) {
		return;
	}

	wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( config.restNonce ) );

	const h = wp.element.createElement;
	const __ = wp.i18n.__;
	const text = function ( message ) {
		return __( message, 'wpessential-checkout-impact-regression' );
	};

	function Field( props ) {
		return h(
			'label',
			{ className: 'wpecir-field' },
			h( 'span', null, props.label ),
			h( 'input', {
				type: props.type || 'text',
				value: props.value,
				required: Boolean( props.required ),
				min: props.min,
				maxLength: props.maxLength,
				step: props.step,
				onChange: function ( event ) { props.onChange( event.target.value ); },
				'aria-describedby': props.help ? props.id + '-help' : undefined,
				id: props.id,
			} ),
			props.help ? h( 'small', { id: props.id + '-help' }, props.help ) : null
		);
	}

	function App() {
		const isWooCommerceActive = Boolean( config.woocommerceActive );
		const [ scenarios, setScenarios ] = wp.element.useState( [] );
		const [ title, setTitle ] = wp.element.useState( '' );
		const [ country, setCountry ] = wp.element.useState( 'DE' );
		const [ productId, setProductId ] = wp.element.useState( '' );
		const [ quantity, setQuantity ] = wp.element.useState( '1' );
		const [ currency, setCurrency ] = wp.element.useState( 'EUR' );
		const [ shippingMethod, setShippingMethod ] = wp.element.useState( '' );
		const [ shippingCost, setShippingCost ] = wp.element.useState( '' );
		const [ coupon, setCoupon ] = wp.element.useState( '' );
		const [ expectedDiscount, setExpectedDiscount ] = wp.element.useState( '' );
		const [ loading, setLoading ] = wp.element.useState( true );
		const [ saving, setSaving ] = wp.element.useState( false );
		const [ notice, setNotice ] = wp.element.useState( '' );
		const [ error, setError ] = wp.element.useState( '' );
		const [ editingId, setEditingId ] = wp.element.useState( null );

		const refresh = function () {
			setLoading( true );
			return wp.apiFetch( { path: '/wpecir/v1/scenarios?per_page=50' } )
				.then( function ( rows ) { setScenarios( Array.isArray( rows ) ? rows : [] ); setError( '' ); } )
				.catch( function ( requestError ) { setError( requestError.message || text( 'Could not load saved scenarios.' ) ); } )
				.finally( function () { setLoading( false ); } );
		};

		wp.element.useEffect( function () { refresh(); }, [] );

		const resetForm = function () {
			setEditingId( null );
			setTitle( '' );
			setCountry( 'DE' );
			setProductId( '' );
			setQuantity( '1' );
			setCurrency( 'EUR' );
			setShippingMethod( '' );
			setShippingCost( '' );
			setCoupon( '' );
			setExpectedDiscount( '' );
		};

		const editScenario = function ( id ) {
			setError( '' );
			setNotice( '' );
			wp.apiFetch( { path: '/wpecir/v1/scenarios/' + id } )
				.then( function ( scenario ) {
					const version = scenario.latest_version;
					if ( ! version || ! version.input || ! version.expectation ) {
						throw new Error( text( 'The latest scenario version could not be loaded.' ) );
					}
					const input = version.input;
					const expected = version.expectation;
					const item = input.cart && input.cart.items && input.cart.items[ 0 ] ? input.cart.items[ 0 ] : {};
					setEditingId( Number( scenario.id ) );
					setTitle( scenario.title || '' );
					setCountry( input.market && input.market.country ? input.market.country : 'DE' );
					setProductId( item.product_id ? String( item.product_id ) : '' );
					setQuantity( item.quantity ? String( item.quantity ) : '1' );
					setCurrency( input.currency || 'EUR' );
					setShippingMethod( expected.shipping_method || '' );
					setShippingCost( expected.shipping_cost !== undefined ? String( expected.shipping_cost ) : '' );
					setCoupon( expected.coupon_code || '' );
					setExpectedDiscount( expected.coupon_discount !== undefined ? String( expected.coupon_discount ) : '' );
					setNotice( text( 'Editing creates a new immutable scenario version.' ) );
				} )
				.catch( function ( requestError ) { setError( requestError.message || text( 'Could not load this scenario.' ) ); } );
		};

		const saveScenario = function ( event ) {
			event.preventDefault();
			setSaving( true );
			setError( '' );
			setNotice( '' );

			const expected = {};
			const isValidMoney = function ( value ) {
				return /^(0|[1-9][0-9]{0,15})(?:\.[0-9]{1,6})?$/.test( String( value ).trim() );
			};
			if ( shippingMethod.trim() ) {
				expected.shipping_method = shippingMethod.trim();
			}
			if ( shippingCost !== '' ) {
				if ( ! isValidMoney( shippingCost ) ) {
					setError( text( 'Enter a valid non-negative shipping amount.' ) );
					setSaving( false );
					return;
				}
				expected.shipping_cost = shippingCost.trim();
			}
			if ( coupon.trim() ) {
				expected.coupon_code = coupon.trim();
				if ( expectedDiscount !== '' ) {
					if ( ! isValidMoney( expectedDiscount ) ) {
						setError( text( 'Enter a valid non-negative coupon discount.' ) );
						setSaving( false );
						return;
					}
					expected.coupon_discount = expectedDiscount.trim();
				}
			}

			const input = {
				market: { country: country.trim().toUpperCase() },
				cart: { items: [ { product_id: Number( productId ), quantity: Number( quantity ) } ] },
				currency: currency.trim().toUpperCase(),
			};
			wp.apiFetch( {
				path: editingId ? '/wpecir/v1/scenarios/' + editingId : '/wpecir/v1/scenarios',
				method: editingId ? 'PUT' : 'POST',
				data: { title: title.trim(), input: input, expectation: expected },
			} )
				.then( function () {
					const wasEditing = Boolean( editingId );
					resetForm();
					setNotice( wasEditing ? text( 'New scenario version saved. Checkout execution is still disabled.' ) : text( 'Scenario saved. Checkout execution is still disabled.' ) );
					return refresh();
				} )
				.catch( function ( requestError ) { setError( requestError.message || text( 'Could not save this scenario.' ) ); } )
				.finally( function () { setSaving( false ); } );
		};

		const removeScenario = function ( id ) {
			if ( ! window.confirm( text( 'Delete this saved scenario and its versions?' ) ) ) {
				return;
			}
			wp.apiFetch( { path: '/wpecir/v1/scenarios/' + id, method: 'DELETE' } )
				.then( function () { setNotice( text( 'Scenario deleted.' ) ); refresh(); } )
				.catch( function ( requestError ) { setError( requestError.message || text( 'Could not delete this scenario.' ) ); } );
		};

		return h(
			'main',
			{ className: 'wpecir-app' },
			h(
				'header',
				{ className: 'wpecir-header' },
				h( 'div', null,
					h( 'p', { className: 'wpecir-eyebrow' }, text( 'WPEssential' ) ),
					h( 'h1', null, text( 'Checkout Impact' ) ),
					h( 'p', { className: 'wpecir-intro' }, text( 'Save synthetic customer and cart scenarios for future before-and-after comparisons.' ) )
				),
				h( 'span', { className: 'wpecir-phase-badge' }, text( 'Development preview' ) )
			),
			h( 'section', { className: 'wpecir-notice wpecir-notice--warning', role: 'status', 'aria-live': 'polite' },
				h( 'span', { className: 'dashicons dashicons-warning', 'aria-hidden': 'true' } ),
				h( 'div', null,
					h( 'h2', null, text( 'Automatic checkout checks are disabled' ) ),
					h( 'p', null, text( 'Saved scenarios are configuration records only. No checkout, payment, shipping, coupon, or order request is run.' ) )
				)
			),
			! isWooCommerceActive ? h( 'div', { className: 'notice notice-error inline' }, h( 'p', null, text( 'WooCommerce is not active. Activate WooCommerce to continue with WooCommerce-specific development.' ) ) ) : null,
			error ? h( 'div', { className: 'notice notice-error inline', role: 'alert' }, h( 'p', null, error ) ) : null,
			notice ? h( 'div', { className: 'notice notice-success inline', role: 'status', 'aria-live': 'polite' }, h( 'p', null, notice ) ) : null,
			h( 'div', { className: 'wpecir-workspace' },
				h( 'section', { className: 'wpecir-panel', 'aria-labelledby': 'wpecir-builder-title' },
					h( 'div', { className: 'wpecir-panel__heading' }, h( 'p', { className: 'wpecir-card__eyebrow' }, text( 'Scenario setup' ) ), h( 'h2', { id: 'wpecir-builder-title' }, editingId ? text( 'Edit a synthetic scenario' ) : text( 'Create a synthetic scenario' ) ), h( 'p', null, text( 'Use test product IDs and non-customer data. Saving does not visit checkout.' ) ) ),
					h( 'form', { className: 'wpecir-form', onSubmit: saveScenario },
						h( Field, { id: 'wpecir-title', label: text( 'Scenario name' ), value: title, onChange: setTitle, required: true, maxLength: 191 } ),
						h( 'div', { className: 'wpecir-form-grid' },
							h( Field, { id: 'wpecir-country', label: text( 'Destination country' ), value: country, onChange: setCountry, required: true, maxLength: 2, help: text( 'Two-letter country code, for example DE.' ) } ),
							h( Field, { id: 'wpecir-currency', label: text( 'Currency' ), value: currency, onChange: setCurrency, required: true, maxLength: 3 } ),
							h( Field, { id: 'wpecir-product', label: text( 'Test product ID' ), type: 'number', value: productId, onChange: setProductId, required: true, min: 1 } ),
							h( Field, { id: 'wpecir-quantity', label: text( 'Quantity' ), type: 'number', value: quantity, onChange: setQuantity, required: true, min: 1 } ),
							h( Field, { id: 'wpecir-shipping-method', label: text( 'Expected shipping method (optional)' ), value: shippingMethod, onChange: setShippingMethod, maxLength: 100 } ),
							h( Field, { id: 'wpecir-shipping-cost', label: text( 'Expected shipping cost (optional)' ), type: 'number', step: 'any', value: shippingCost, onChange: setShippingCost, min: 0 } ),
						h( Field, { id: 'wpecir-coupon', label: text( 'Coupon code (optional)' ), value: coupon, onChange: setCoupon, maxLength: 100 } ),
							h( Field, { id: 'wpecir-discount', label: text( 'Expected coupon discount (optional)' ), type: 'number', step: 'any', value: expectedDiscount, onChange: setExpectedDiscount, min: 0 } )
						),
						h( 'button', { className: 'button button-primary', type: 'submit', disabled: saving || ! isWooCommerceActive }, saving ? text( 'Saving…' ) : editingId ? text( 'Save new version' ) : text( 'Save scenario' ) ),
						editingId ? h( 'button', { className: 'button', type: 'button', onClick: resetForm, disabled: saving }, text( 'Cancel edit' ) ) : null
					)
				),
				h( 'section', { className: 'wpecir-panel', 'aria-labelledby': 'wpecir-list-title' },
					h( 'div', { className: 'wpecir-panel__heading wpecir-panel__heading--row' }, h( 'div', null, h( 'p', { className: 'wpecir-card__eyebrow' }, text( 'Scenario library' ) ), h( 'h2', { id: 'wpecir-list-title' }, text( 'Saved scenarios' ) ) ), h( 'button', { type: 'button', className: 'button', onClick: refresh, disabled: loading }, text( 'Refresh' ) ) ),
					loading ? h( 'p', { role: 'status' }, text( 'Loading saved scenarios…' ) ) : scenarios.length ? h( 'ul', { className: 'wpecir-scenario-list' }, scenarios.map( function ( scenario ) {
						return h( 'li', { className: 'wpecir-scenario-row', key: scenario.id },
							h( 'div', null, h( 'h3', null, scenario.title ), h( 'p', null, text( 'Updated ' ) + scenario.updated_at + ' · ' + text( 'Status: ' ) + scenario.status ) ),
							h( 'div', { className: 'wpecir-scenario-actions' },
								h( 'button', { type: 'button', className: 'button', onClick: function () { editScenario( scenario.id ); } }, text( 'Edit' ) ),
								h( 'button', { type: 'button', className: 'button button-link-delete', onClick: function () { removeScenario( scenario.id ); } }, text( 'Delete' ) )
							)
						);
					} ) ) : h( 'p', { className: 'wpecir-empty' }, text( 'No scenarios saved yet. Create one using synthetic values above.' ) )
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
