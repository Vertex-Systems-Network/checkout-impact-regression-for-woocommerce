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


	function ManualObservationField( props ) {
		const valueId = 'wpecir-' + props.side + '-' + props.field;
		return h(
			'div',
			{ className: 'wpecir-observation-field' },
			h( 'label', { htmlFor: valueId + '-state' }, props.label ),
			h(
				'select',
				{
					id: valueId + '-state',
					value: props.observation.state,
					onChange: function ( event ) { props.onChange( { state: event.target.value, value: props.observation.value } ); },
				},
				h( 'option', { value: 'not_observed' }, text( 'Not recorded' ) ),
				h( 'option', { value: 'observed' }, text( 'Observed' ) ),
				h( 'option', { value: 'missing' }, text( 'Confirmed missing' ) ),
				h( 'option', { value: 'unsupported' }, text( 'Unsupported' ) ),
				h( 'option', { value: 'collection_error' }, text( 'Could not collect' ) )
			),
			props.observation.state === 'observed' ? h( 'input', {
				id: valueId,
				type: 'text',
				inputMode: props.money ? 'decimal' : undefined,
				value: props.observation.value,
				maxLength: 191,
				placeholder: props.money ? text( 'Exact decimal amount' ) : text( 'Observed value' ),
				'aria-label': props.label + ' ' + text( 'observed value' ),
				onChange: function ( event ) { props.onChange( { state: 'observed', value: event.target.value } ); },
			} ) : h( 'small', null, props.observation.state === 'missing' ? text( 'Use only when you checked and confirmed this value is absent.' ) : text( 'This field will remain inconclusive unless you record an observation.' ) )
		);
	}

	const manualFields = [
		{ key: 'shipping.method', label: text( 'Shipping method' ), money: false },
		{ key: 'shipping.cost', label: text( 'Shipping cost' ), money: true },
		{ key: 'coupon.code', label: text( 'Coupon code' ), money: false },
		{ key: 'coupon.discount', label: text( 'Coupon discount' ), money: true },
	];
	const emptyManualObservations = function () {
		return manualFields.reduce( function ( values, field ) {
			values[ field.key ] = { state: 'not_observed', value: '' };
			return values;
		}, {} );
	};
	const toManualObservationMap = function ( values ) {
		return manualFields.reduce( function ( result, field ) {
			const observation = values[ field.key ];
			result[ field.key ] = observation.state === 'observed'
				? { state: 'observed', value: observation.value }
				: { state: observation.state };
			return result;
		}, {} );
	};
	function comparisonValue( value ) {
		if ( value && typeof value === 'object' && value.currency_code && value.amount !== undefined ) {
			return value.currency_code + ' ' + value.amount;
		}
		return value === undefined ? '' : String( value );
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
		const [ historyScenarioId, setHistoryScenarioId ] = wp.element.useState( null );
		const [ scenarioHistory, setScenarioHistory ] = wp.element.useState( [] );
		const [ baselineHistoryScenarioId, setBaselineHistoryScenarioId ] = wp.element.useState( null );
		const [ baselineHistory, setBaselineHistory ] = wp.element.useState( [] );
		const [ changeEvents, setChangeEvents ] = wp.element.useState( [] );
		const [ eventsLoading, setEventsLoading ] = wp.element.useState( true );

		const [ comparisonScenarioId, setComparisonScenarioId ] = wp.element.useState( '' );
		const [ beforeObservations, setBeforeObservations ] = wp.element.useState( emptyManualObservations );
		const [ afterObservations, setAfterObservations ] = wp.element.useState( emptyManualObservations );
		const [ comparisonBusy, setComparisonBusy ] = wp.element.useState( false );
		const [ comparisonResult, setComparisonResult ] = wp.element.useState( null );
		const [ comparisonError, setComparisonError ] = wp.element.useState( '' );

		const refresh = function () {
			setLoading( true );
			return wp.apiFetch( { path: '/wpecir/v1/scenarios?per_page=50' } )
				.then( function ( rows ) { setScenarios( Array.isArray( rows ) ? rows : [] ); setError( '' ); } )
				.catch( function ( requestError ) { setError( requestError.message || text( 'Could not load saved scenarios.' ) ); } )
				.finally( function () { setLoading( false ); } );
		};


		const refreshEvents = function () {
			setEventsLoading( true );
			return wp.apiFetch( { path: '/wpecir/v1/change-events?per_page=50' } )
				.then( function ( rows ) { setChangeEvents( Array.isArray( rows ) ? rows : [] ); } )
				.catch( function ( requestError ) { setError( requestError.message || text( 'Could not load detected change events.' ) ); } )
				.finally( function () { setEventsLoading( false ); } );
		};

		wp.element.useEffect( function () { refresh(); refreshEvents(); }, [] );


		const updateObservation = function (setter, field, value ) {
			setter( function (previous) { return Object.assign( {}, previous, { [ field ]: value } ); } );
			setComparisonResult( null );
		};

		const hasManualObservation = function () {
			return manualFields.some( function (field) {
				return beforeObservations[ field.key ].state !== 'not_observed' || afterObservations[ field.key ].state !== 'not_observed';
			} );
		};

		const previewManualComparison = function (event) {
			event.preventDefault();
			setComparisonBusy( true );
			setComparisonError( '' );
			setComparisonResult( null );
			wp.apiFetch( {
				path: '/wpecir/v1/comparisons/preview',
				method: 'POST',
				data: {
					scenario_id: Number( comparisonScenarioId ),
					before: toManualObservationMap( beforeObservations ),
					after: toManualObservationMap( afterObservations ),
				},
			} )
				.then( function (result) { setComparisonResult( result ); } )
				.catch( function (requestError) { setComparisonError( requestError.message || text( 'Could not compare the recorded values.' ) ); } )
				.finally( function () { setComparisonBusy( false ); } );
		};

		const comparisonStateLabels = {
			changed: text( 'Changed' ),
			missing: text( 'Missing after change' ),
			added: text( 'Newly observed' ),
			unchanged: text( 'Unchanged' ),
			not_comparable: text( 'Needs review' ),
		};
		const comparisonFieldLabels = {
			shipping_method: text( 'Shipping method' ),
			shipping_cost: text( 'Shipping cost' ),
			coupon_code: text( 'Coupon code' ),
			coupon_discount: text( 'Coupon discount' ),
		};
		const comparisonDetail = function (change) {
			if ( change.state === 'changed' ) {
				return text( 'Before: ' ) + comparisonValue( change.baseline ) + ' → ' + text( 'After: ' ) + comparisonValue( change.current );
			}
			if ( change.state === 'missing' ) {
				return text( 'Before: ' ) + comparisonValue( change.baseline ) + ' → ' + text( 'After: confirmed missing' );
			}
			if ( change.state === 'added' ) {
				return text( 'Before: not present → After: ' ) + comparisonValue( change.current );
			}
			if ( change.state === 'unchanged' ) {
				return text( 'Before and after: ' ) + comparisonValue( change.current !== undefined ? change.current : change.baseline );
			}
			return text( 'The values are incomplete, unsupported, or not from a comparable capture.' );
		};

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

		const toggleHistory = function ( id ) {
			if ( Number( historyScenarioId ) === Number( id ) ) {
				setHistoryScenarioId( null );
				setScenarioHistory( [] );
				return;
			}
			setHistoryScenarioId( Number( id ) );
			setScenarioHistory( [] );
			setError( '' );
			wp.apiFetch( { path: '/wpecir/v1/scenarios/' + id + '/versions' } )
				.then( function ( rows ) { setScenarioHistory( Array.isArray( rows ) ? rows : [] ); } )
				.catch( function ( requestError ) { setError( requestError.message || text( 'Could not load scenario history.' ) ); } );
		};


		const toggleBaselineHistory = function ( id ) {
			if ( Number( baselineHistoryScenarioId ) === Number( id ) ) {
				setBaselineHistoryScenarioId( null );
				setBaselineHistory( [] );
				return;
			}
			setBaselineHistoryScenarioId( Number( id ) );
			setBaselineHistory( [] );
			setError( '' );
			wp.apiFetch( { path: '/wpecir/v1/scenarios/' + id + '/baselines' } )
				.then( function ( rows ) { setBaselineHistory( Array.isArray( rows ) ? rows : [] ); } )
				.catch( function ( requestError ) { setError( requestError.message || text( 'Could not load approved baseline history.' ) ); } );
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

			h( 'section', { className: 'wpecir-overview', 'aria-labelledby': 'wpecir-overview-title' },
				h( 'div', { className: 'wpecir-overview__heading' },
					h( 'p', { className: 'wpecir-card__eyebrow' }, text( 'Impact overview' ) ),
					h( 'h2', { id: 'wpecir-overview-title' }, text( 'Change readiness' ) ),
					h( 'p', null, text( 'See what is configured today and what still needs to be built before checkout behavior can be compared.' ) )
				),
				h( 'div', { className: 'wpecir-card-grid' },
					h( 'article', { className: 'wpecir-card' },
						h( 'p', { className: 'wpecir-card__eyebrow' }, text( 'Saved scenarios' ) ),
						h( 'h2', null, loading ? '—' : String( scenarios.length ) ),
						h( 'p', { className: 'wpecir-card__body' }, loading ? text( 'Loading scenario records.' ) : scenarios.length ? text( 'Synthetic customer and cart expectations are saved.' ) : text( 'Create a synthetic scenario to define a customer journey.' ) )
					),
					h( 'article', { className: 'wpecir-card' },
						h( 'p', { className: 'wpecir-card__eyebrow' }, text( 'Before / after comparisons' ) ),
						h( 'p', { className: 'wpecir-status wpecir-status--warning' }, text( 'Not available yet' ) ),
						h( 'h2', null, text( 'No impact results' ) ),
						h( 'p', { className: 'wpecir-card__body' }, text( 'Approved baseline capture and safe checkout observation are not implemented. No regression is inferred from configuration alone.' ) )
					),
					h( 'article', { className: 'wpecir-card' },
						h( 'p', { className: 'wpecir-card__eyebrow' }, text( 'Change context' ) ),
						h( 'h2', null, eventsLoading ? '—' : String( changeEvents.length ) ),
						h( 'p', { className: 'wpecir-card__body' }, eventsLoading ? text( 'Loading recorded changes.' ) : changeEvents.length ? text( 'Recorded change fingerprints; impact has not been verified.' ) : text( 'No supported plugin or WooCommerce setting changes are recorded.' ) )
					)
				)
			),

			h( 'section', { className: 'wpecir-panel wpecir-events-panel', 'aria-labelledby': 'wpecir-events-title' },
				h( 'div', { className: 'wpecir-panel__heading wpecir-panel__heading--row' },
					h( 'div', null,
						h( 'p', { className: 'wpecir-card__eyebrow' }, text( 'Change context' ) ),
						h( 'h2', { id: 'wpecir-events-title' }, text( 'Detected plugin and settings changes' ) ),
						h( 'p', null, text( 'Redacted change fingerprints provide context only; they do not confirm checkout impact.' ) )
					),
					h( 'button', { type: 'button', className: 'button', onClick: refreshEvents, disabled: eventsLoading }, eventsLoading ? text( 'Loading…' ) : text( 'Refresh events' ) )
				),
				eventsLoading ? h( 'p', { role: 'status' }, text( 'Loading change events…' ) ) : changeEvents.length ? h( 'ol', { className: 'wpecir-scenario-list' }, changeEvents.map( function ( event ) {
					const sourceLabels = { plugin_update: text( 'Plugin update' ), woocommerce_setting: text( 'WooCommerce setting' ), plugin_setting: text( 'Plugin setting' ), manual: text( 'Manual event' ) };
					return h( 'li', { className: 'wpecir-scenario-row', key: event.id },
						h( 'div', null,
							h( 'h3', null, sourceLabels[ event.source ] || event.source ),
							h( 'p', null, event.object_type + ' · ' + event.occurred_at ),
							event.context_fingerprint ? h( 'small', null, text( 'Context fingerprint: ' ) + event.context_fingerprint.slice( 0, 12 ) + '…' ) : null
						)
					);
				} ) ) : h( 'p', { className: 'wpecir-empty' }, text( 'No supported plugin or WooCommerce settings changes have been recorded yet.' ) )
			),


			h( 'section', { className: 'wpecir-panel wpecir-manual-comparison', 'aria-labelledby': 'wpecir-manual-comparison-title' },
				h( 'div', { className: 'wpecir-panel__heading' },
					h( 'p', { className: 'wpecir-card__eyebrow' }, text( 'Manual comparison' ) ),
					h( 'h2', { id: 'wpecir-manual-comparison-title' }, text( 'Preview before and after observations' ) ),
					h( 'p', null, text( 'Enter values you observed yourself for the same saved scenario. This preview does not open checkout, and unrecorded values stay inconclusive.' ) )
				),
				scenarios.length ? h( 'form', { onSubmit: previewManualComparison, className: 'wpecir-manual-comparison__form' },
					h( 'label', { className: 'wpecir-field', htmlFor: 'wpecir-comparison-scenario' },
						h( 'span', null, text( 'Scenario context' ) ),
						h( 'select', {
							id: 'wpecir-comparison-scenario',
							value: comparisonScenarioId,
							required: true,
							onChange: function (event) { setComparisonScenarioId( event.target.value ); setComparisonResult( null ); },
						},
							h( 'option', { value: '' }, text( 'Choose a saved scenario' ) ),
							scenarios.map( function (scenario) {
								return h( 'option', { key: scenario.id, value: String( scenario.id ) }, scenario.title );
							} )
						)
					),
					h( 'div', { className: 'wpecir-observation-columns' },
						h( 'section', { className: 'wpecir-observation-column', 'aria-label': text( 'Before observations' ) },
							h( 'h3', null, text( 'Before' ) ),
							manualFields.map( function (field) {
								return h( ManualObservationField, { key: 'before-' + field.key, side: 'before', field: field.key, label: field.label, money: field.money, observation: beforeObservations[ field.key ], onChange: function (value) { updateObservation( setBeforeObservations, field.key, value ); } } );
							} )
						),
						h( 'section', { className: 'wpecir-observation-column', 'aria-label': text( 'After observations' ) },
							h( 'h3', null, text( 'After' ) ),
							manualFields.map( function (field) {
								return h( ManualObservationField, { key: 'after-' + field.key, side: 'after', field: field.key, label: field.label, money: field.money, observation: afterObservations[ field.key ], onChange: function (value) { updateObservation( setAfterObservations, field.key, value ); } );
							} )
						)
					),
					comparisonError ? h( 'p', { className: 'notice notice-error inline', role: 'alert' }, comparisonError ) : null,
					h( 'button', { type: 'submit', className: 'button button-primary', disabled: comparisonBusy || ! comparisonScenarioId || ! hasManualObservation() }, comparisonBusy ? text( 'Comparing…' ) : text( 'Preview comparison' ) ),
					comparisonResult ? h( 'div', { className: 'wpecir-comparison-results', role: 'status', 'aria-live': 'polite' },
						h( 'h3', null, text( 'Comparison preview' ) ),
						h( 'p', null, comparisonResult.scenario_title + ' · ' + text( 'Scenario version ' ) + comparisonResult.scenario_version + ' · ' + comparisonResult.currency ),
						h( 'ul', { className: 'wpecir-comparison-summary' },
							h( 'li', null, text( 'Changed: ' ) + comparisonResult.summary.changed ),
							h( 'li', null, text( 'Missing: ' ) + comparisonResult.summary.missing ),
							h( 'li', null, text( 'Added: ' ) + comparisonResult.summary.added ),
							h( 'li', null, text( 'Unchanged: ' ) + comparisonResult.summary.unchanged ),
							h( 'li', null, text( 'Needs review: ' ) + comparisonResult.summary.not_comparable )
						),
						h( 'ol', { className: 'wpecir-comparison-findings' }, Object.keys( comparisonResult.changes ).map( function (field) {
							const change = comparisonResult.changes[ field ];
							return h( 'li', { key: field },
								h( 'div', null, h( 'strong', null, comparisonFieldLabels[ field ] || field ), h( 'span', { className: 'wpecir-status wpecir-status--' + ( change.state === 'changed' || change.state === 'missing' ? 'warning' : change.state === 'not_comparable' ? 'muted' : 'neutral' ) }, comparisonStateLabels[ change.state ] || text( 'Needs review' ) ) ),
								h( 'p', null, comparisonDetail( change ) )
							);
						} ) )
					) : null
				) : h( 'p', { className: 'wpecir-empty' }, text( 'Create a synthetic scenario before previewing observed values.' ) )
			),

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
								h( 'button', { type: 'button', className: 'button', 'aria-expanded': Number( historyScenarioId ) === Number( scenario.id ), onClick: function () { toggleHistory( scenario.id ); } }, Number( historyScenarioId ) === Number( scenario.id ) ? text( 'Hide history' ) : text( 'View history' ) ),
								h( 'button', { type: 'button', className: 'button', 'aria-expanded': Number( baselineHistoryScenarioId ) === Number( scenario.id ), onClick: function () { toggleBaselineHistory( scenario.id ); } }, Number( baselineHistoryScenarioId ) === Number( scenario.id ) ? text( 'Hide baselines' ) : text( 'View baselines' ) ),
								h( 'button', { type: 'button', className: 'button button-link-delete', onClick: function () { removeScenario( scenario.id ); } }, text( 'Delete' ) )
							),
							Number( historyScenarioId ) === Number( scenario.id ) ? h( 'div', { className: 'wpecir-version-history', 'aria-live': 'polite' },
								h( 'h4', null, text( 'Saved versions' ) ),
								scenarioHistory.length ? h( 'ol', null, scenarioHistory.map( function ( version ) {
									const expectation = version.expectation || {};
									const summary = [ expectation.shipping_method, expectation.shipping_cost !== undefined ? text( 'Shipping: ' ) + expectation.shipping_cost : '', expectation.coupon_code ? text( 'Coupon: ' ) + expectation.coupon_code : '', expectation.coupon_discount !== undefined ? text( 'Discount: ' ) + expectation.coupon_discount : '' ].filter( Boolean ).join( ' · ' );
									return h( 'li', { key: version.id }, h( 'strong', null, text( 'Version ' ) + version.version ), ' · ' + version.created_at, summary ? h( 'p', null, summary ) : null );
								} ) ) : h( 'p', null, text( 'No saved versions were returned.' ) )
							) : null,
							Number( baselineHistoryScenarioId ) === Number( scenario.id ) ? h( 'div', { className: 'wpecir-version-history', 'aria-live': 'polite' },
								h( 'h4', null, text( 'Approved baseline revisions' ) ),
								baselineHistory.length ? h( 'ol', null, baselineHistory.map( function ( baseline ) {
									return h( 'li', { key: baseline.id },
										h( 'strong', null, text( 'Revision ' ) + baseline.revision ),
										' · ' + text( 'Scenario version ' ) + baseline.scenario_version_id + ' · ' + baseline.approved_at,
										baseline.context_fingerprint ? h( 'p', null, text( 'Context: ' ) + baseline.context_fingerprint ) : null,
										baseline.observation ? h( 'pre', null, JSON.stringify( baseline.observation, null, 2 ) ) : null
									);
								} ) ) : h( 'p', null, text( 'No approved baselines have been recorded for this scenario.' ) )
							) : null
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
