/**
 * Checkout Impact admin screen.
 *
 * Uses the React runtime bundled with WordPress (wp.element), so the plugin
 * ships without a build step. All checks run on the server; this file only
 * renders the interface and calls the plugin's own REST routes.
 */
( function ( wp, config ) {
	'use strict';

	if ( ! wp || ! wp.element || ! wp.i18n || ! wp.apiFetch || ! config ) {
		return;
	}

	wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( config.restNonce ) );

	const h = wp.element.createElement;
	const useState = wp.element.useState;
	const useEffect = wp.element.useEffect;
	const Fragment = wp.element.Fragment;
	const __ = wp.i18n.__;
	const sprintf = wp.i18n.sprintf;
	const hooks = wp.hooks;
	const store = config.store || {};
	const API = '/wpecir/v1';

	/* ------------------------------------------------------------------ */
	/* Field definitions                                                   */
	/* ------------------------------------------------------------------ */

	const FIELDS = [
		{ key: 'shipping.method', change: 'shipping_method', label: __( 'Shipping method', 'checkout-impact-regression-for-woocommerce' ), kind: 'shipping' },
		{ key: 'shipping.cost', change: 'shipping_cost', label: __( 'Shipping cost', 'checkout-impact-regression-for-woocommerce' ), kind: 'money' },
		{ key: 'coupon.code', change: 'coupon_code', label: __( 'Coupon code', 'checkout-impact-regression-for-woocommerce' ), kind: 'text' },
		{ key: 'coupon.discount', change: 'coupon_discount', label: __( 'Coupon discount', 'checkout-impact-regression-for-woocommerce' ), kind: 'money' },
		{ key: 'payment.methods', change: 'payment_methods', label: __( 'Available payment methods', 'checkout-impact-regression-for-woocommerce' ), kind: 'payment' },
	];
	const FIELD_LABELS = FIELDS.reduce( function ( all, field ) {
		all[ field.change ] = field.label;
		all[ field.key ] = field.label;
		return all;
	}, {} );

	const STATE_OPTIONS = [
		{ value: 'not_observed', label: __( 'Not recorded', 'checkout-impact-regression-for-woocommerce' ) },
		{ value: 'observed', label: __( 'Observed', 'checkout-impact-regression-for-woocommerce' ) },
		{ value: 'missing', label: __( 'Confirmed missing', 'checkout-impact-regression-for-woocommerce' ) },
		{ value: 'unsupported', label: __( 'Not supported here', 'checkout-impact-regression-for-woocommerce' ) },
		{ value: 'collection_error', label: __( 'Could not check', 'checkout-impact-regression-for-woocommerce' ) },
	];

	const RESULT_LABELS = {
		changed: __( 'Changed', 'checkout-impact-regression-for-woocommerce' ),
		missing: __( 'Missing after change', 'checkout-impact-regression-for-woocommerce' ),
		added: __( 'Newly present', 'checkout-impact-regression-for-woocommerce' ),
		unchanged: __( 'Unchanged', 'checkout-impact-regression-for-woocommerce' ),
		not_comparable: __( 'Needs review', 'checkout-impact-regression-for-woocommerce' ),
	};
	const RESULT_TONE = { changed: 'warning', missing: 'critical', added: 'info', unchanged: 'success', not_comparable: 'muted' };

	const emptyObservations = function () {
		return FIELDS.reduce( function ( all, field ) {
			all[ field.key ] = { state: 'not_observed', value: '' };
			return all;
		}, {} );
	};
	const toPayload = function ( values ) {
		return FIELDS.reduce( function ( all, field ) {
			const item = values[ field.key ];
			all[ field.key ] = item.state === 'observed' ? { state: 'observed', value: String( item.value ).trim() } : { state: item.state };
			return all;
		}, {} );
	};
	const isComplete = function ( values ) {
		return FIELDS.every( function ( field ) {
			const item = values[ field.key ];
			return item.state === 'missing' || ( item.state === 'observed' && String( item.value ).trim() !== '' );
		} );
	};
	const display = function ( value ) {
		if ( value === undefined || value === null || value === '' ) {
			return '—';
		}
		if ( Array.isArray( value ) ) {
			return value.length ? value.join( ', ' ) : '—';
		}
		if ( typeof value === 'object' && value.amount !== undefined ) {
			return ( value.currency_code ? value.currency_code + ' ' : '' ) + value.amount;
		}
		return String( value );
	};
	const isMoney = function ( value ) {
		return /^(0|[1-9][0-9]{0,15})(?:\.[0-9]{1,6})?$/.test( String( value ).trim() );
	};
	const formatDate = function ( mysqlUtc ) {
		if ( ! mysqlUtc ) {
			return '';
		}
		const date = new Date( String( mysqlUtc ).replace( ' ', 'T' ) + 'Z' );
		return isNaN( date.getTime() ) ? mysqlUtc : date.toLocaleString();
	};
	const errorMessage = function ( error, fallback ) {
		return ( error && error.message ) ? error.message : fallback;
	};

	/* ------------------------------------------------------------------ */
	/* Small UI building blocks                                            */
	/* ------------------------------------------------------------------ */

	function Notice( props ) {
		if ( ! props.message ) {
			return null;
		}
		return h( 'div', { className: 'wpecir-notice wpecir-notice--' + ( props.tone || 'info' ), role: props.tone === 'error' ? 'alert' : 'status' },
			h( 'p', null, props.message ),
			props.onDismiss ? h( 'button', { type: 'button', className: 'wpecir-notice__dismiss', onClick: props.onDismiss, 'aria-label': __( 'Dismiss message', 'checkout-impact-regression-for-woocommerce' ) }, '×' ) : null
		);
	}

	function Card( props ) {
		return h( 'section', { className: 'wpecir-card ' + ( props.className || '' ), 'aria-labelledby': props.id },
			( props.title || props.actions ) ? h( 'header', { className: 'wpecir-card__header' },
				h( 'div', null,
					props.eyebrow ? h( 'p', { className: 'wpecir-eyebrow' }, props.eyebrow ) : null,
					props.title ? h( 'h2', { id: props.id, className: 'wpecir-card__title' }, props.title ) : null,
					props.description ? h( 'p', { className: 'wpecir-card__description' }, props.description ) : null
				),
				props.actions ? h( 'div', { className: 'wpecir-card__actions' }, props.actions ) : null
			) : null,
			props.children
		);
	}

	function Badge( props ) {
		return h( 'span', { className: 'wpecir-badge wpecir-badge--' + ( props.tone || 'muted' ) }, props.children );
	}

	function Empty( props ) {
		return h( 'div', { className: 'wpecir-empty' },
			h( 'p', { className: 'wpecir-empty__title' }, props.title ),
			props.text ? h( 'p', null, props.text ) : null,
			props.action || null
		);
	}

	function Field( props ) {
		const helpId = props.help ? props.id + '-help' : undefined;
		const control = props.options ? h( 'select', {
			id: props.id,
			value: props.value,
			required: Boolean( props.required ),
			'aria-describedby': helpId,
			onChange: function ( event ) { props.onChange( event.target.value ); },
		}, props.placeholder ? h( 'option', { value: '' }, props.placeholder ) : null, props.options.map( function ( option ) {
			return h( 'option', { key: option.value, value: option.value }, option.label );
		} ) ) : h( 'input', {
			id: props.id,
			type: props.type || 'text',
			value: props.value,
			required: Boolean( props.required ),
			min: props.min,
			max: props.max,
			maxLength: props.maxLength,
			inputMode: props.inputMode,
			placeholder: props.placeholder,
			list: props.list,
			'aria-describedby': helpId,
			onChange: function ( event ) { props.onChange( event.target.value ); },
		} );
		return h( 'div', { className: 'wpecir-field' + ( props.wide ? ' wpecir-field--wide' : '' ) },
			h( 'label', { htmlFor: props.id }, props.label, props.optional ? h( 'span', { className: 'wpecir-optional' }, ' ' + __( '(optional)', 'checkout-impact-regression-for-woocommerce' ) ) : null ),
			control,
			props.help ? h( 'p', { id: helpId, className: 'wpecir-help' }, props.help ) : null
		);
	}

	function DataLists() {
		return h( Fragment, null,
			h( 'datalist', { id: 'wpecir-shipping-methods' }, ( store.shippingMethods || [] ).map( function ( option ) {
				return h( 'option', { key: option.value, value: option.value, label: option.label } );
			} ) )
		);
	}

	function PaymentPicker( props ) {
		const selected = String( props.value || '' ).split( ',' ).map( function ( item ) { return item.trim(); } ).filter( Boolean );
		const known = ( store.paymentMethods || [] ).map( function ( option ) { return option.value; } );
		const custom = selected.filter( function ( item ) { return known.indexOf( item ) === -1; } ).join( ', ' );
		const update = function ( list, customText ) {
			const extra = String( customText ).split( ',' ).map( function ( item ) { return item.trim(); } ).filter( Boolean );
			props.onChange( list.concat( extra ).filter( function ( item, index, all ) { return all.indexOf( item ) === index; } ).join( ',' ) );
		};
		const knownSelected = selected.filter( function ( item ) { return known.indexOf( item ) !== -1; } );
		return h( 'fieldset', { className: 'wpecir-payment-picker' },
			h( 'legend', { className: 'screen-reader-text' }, props.label ),
			( store.paymentMethods || [] ).map( function ( option ) {
				const checked = knownSelected.indexOf( option.value ) !== -1;
				return h( 'label', { key: option.value, className: 'wpecir-check' },
					h( 'input', { type: 'checkbox', checked: checked, onChange: function () {
						update( checked ? knownSelected.filter( function ( item ) { return item !== option.value; } ) : knownSelected.concat( [ option.value ] ), custom );
					} } ),
					h( 'span', null, option.label, h( 'code', null, option.value ) )
				);
			} ),
			h( 'input', { type: 'text', className: 'wpecir-payment-picker__custom', value: custom, placeholder: __( 'Other gateway IDs, comma-separated', 'checkout-impact-regression-for-woocommerce' ), 'aria-label': __( 'Other payment method IDs', 'checkout-impact-regression-for-woocommerce' ), onChange: function ( event ) { update( knownSelected, event.target.value ); } } )
		);
	}

	function ObservationField( props ) {
		const id = 'wpecir-' + props.side + '-' + props.field.key.replace( '.', '-' );
		const item = props.value;
		let input = null;
		if ( item.state === 'observed' ) {
			if ( props.field.kind === 'payment' ) {
				input = h( PaymentPicker, { value: item.value, label: props.field.label, onChange: function ( value ) { props.onChange( { state: 'observed', value: value } ); } } );
			} else {
				input = h( 'input', {
					id: id + '-value',
					type: 'text',
					value: item.value,
					maxLength: 191,
					inputMode: props.field.kind === 'money' ? 'decimal' : undefined,
					list: props.field.kind === 'shipping' ? 'wpecir-shipping-methods' : undefined,
					placeholder: props.field.kind === 'money' ? __( 'e.g. 7.50', 'checkout-impact-regression-for-woocommerce' ) : props.field.kind === 'shipping' ? __( 'e.g. flat_rate:3', 'checkout-impact-regression-for-woocommerce' ) : __( 'e.g. WELCOME10', 'checkout-impact-regression-for-woocommerce' ),
					'aria-label': sprintf( /* translators: %s: field name. */ __( '%s value', 'checkout-impact-regression-for-woocommerce' ), props.field.label ),
					'aria-invalid': props.field.kind === 'money' && item.value !== '' && ! isMoney( item.value ) ? 'true' : undefined,
					onChange: function ( event ) { props.onChange( { state: 'observed', value: event.target.value } ); },
				} );
			}
		}
		return h( 'div', { className: 'wpecir-observation' + ( props.readOnly ? ' is-readonly' : '' ) },
			h( 'label', { htmlFor: id + '-state', className: 'wpecir-observation__label' }, props.field.label ),
			h( 'select', {
				id: id + '-state',
				value: item.state,
				disabled: props.readOnly,
				onChange: function ( event ) { props.onChange( { state: event.target.value, value: item.value } ); },
			}, STATE_OPTIONS.map( function ( option ) { return h( 'option', { key: option.value, value: option.value }, option.label ); } ) ),
			props.readOnly && item.state === 'observed' ? h( 'p', { className: 'wpecir-observation__readonly' }, display( item.value ) ) : input
		);
	}

	function ResultTable( props ) {
		const keys = Object.keys( props.changes || {} );
		if ( ! keys.length ) {
			return h( 'p', { className: 'wpecir-help' }, __( 'No fields were compared.', 'checkout-impact-regression-for-woocommerce' ) );
		}
		return h( 'div', { className: 'wpecir-table-wrap' },
			h( 'table', { className: 'wpecir-table' },
				h( 'thead', null, h( 'tr', null,
					h( 'th', { scope: 'col' }, __( 'Field', 'checkout-impact-regression-for-woocommerce' ) ),
					h( 'th', { scope: 'col' }, __( 'Before', 'checkout-impact-regression-for-woocommerce' ) ),
					h( 'th', { scope: 'col' }, __( 'After', 'checkout-impact-regression-for-woocommerce' ) ),
					h( 'th', { scope: 'col' }, __( 'Result', 'checkout-impact-regression-for-woocommerce' ) )
				) ),
				h( 'tbody', null, keys.map( function ( key ) {
					const change = props.changes[ key ];
					return h( 'tr', { key: key, className: 'is-' + change.state },
						h( 'th', { scope: 'row' }, FIELD_LABELS[ key ] || key ),
						h( 'td', null, change.state === 'added' ? __( 'Not present', 'checkout-impact-regression-for-woocommerce' ) : display( change.baseline ) ),
						h( 'td', null, change.state === 'missing' ? __( 'Confirmed missing', 'checkout-impact-regression-for-woocommerce' ) : display( change.current !== undefined ? change.current : ( change.state === 'unchanged' ? change.baseline : undefined ) ) ),
						h( 'td', null, h( Badge, { tone: RESULT_TONE[ change.state ] }, RESULT_LABELS[ change.state ] || RESULT_LABELS.not_comparable ) )
					);
				} ) )
			)
		);
	}

	function Summary( props ) {
		const summary = props.summary || {};
		return h( 'ul', { className: 'wpecir-summary', 'aria-label': __( 'Comparison summary', 'checkout-impact-regression-for-woocommerce' ) },
			[ 'changed', 'missing', 'added', 'unchanged', 'not_comparable' ].map( function ( key ) {
				return h( 'li', { key: key, className: 'wpecir-summary__item wpecir-summary__item--' + RESULT_TONE[ key ] },
					h( 'strong', null, String( summary[ key ] || 0 ) ),
					h( 'span', null, RESULT_LABELS[ key ] )
				);
			} )
		);
	}

	/* ------------------------------------------------------------------ */
	/* Tabs                                                                */
	/* ------------------------------------------------------------------ */

	function OverviewTab( props ) {
		const steps = [
			{ title: __( 'Save a scenario', 'checkout-impact-regression-for-woocommerce' ), text: __( 'Pick a destination country, currency, test product and what you expect at checkout.', 'checkout-impact-regression-for-woocommerce' ), tab: 'scenarios' },
			{ title: __( 'Approve a baseline', 'checkout-impact-regression-for-woocommerce' ), text: __( 'Record what checkout shows today and approve it as the known-good state.', 'checkout-impact-regression-for-woocommerce' ), tab: 'compare' },
			{ title: __( 'Compare after a change', 'checkout-impact-regression-for-woocommerce' ), text: __( 'After an update or settings change, record checkout again and see exactly what changed.', 'checkout-impact-regression-for-woocommerce' ), tab: 'compare' },
		];
		const recent = props.events.slice( 0, 5 );
		return h( 'div', { className: 'wpecir-stack' },
			h( 'div', { className: 'wpecir-stats' },
				h( 'div', { className: 'wpecir-stat' }, h( 'span', { className: 'wpecir-stat__label' }, __( 'Saved scenarios', 'checkout-impact-regression-for-woocommerce' ) ), h( 'strong', { className: 'wpecir-stat__value' }, props.loading ? '—' : String( props.scenarios.length ) ) ),
				h( 'div', { className: 'wpecir-stat' }, h( 'span', { className: 'wpecir-stat__label' }, __( 'Recorded changes', 'checkout-impact-regression-for-woocommerce' ) ), h( 'strong', { className: 'wpecir-stat__value' }, props.eventsLoading ? '—' : String( props.events.length ) ) ),
				h( 'div', { className: 'wpecir-stat' }, h( 'span', { className: 'wpecir-stat__label' }, __( 'Report retention', 'checkout-impact-regression-for-woocommerce' ) ), h( 'strong', { className: 'wpecir-stat__value' }, props.settings ? sprintf( /* translators: %d: number of days. */ __( '%d days', 'checkout-impact-regression-for-woocommerce' ), props.settings.retention_days ) : '—' ) ),
				h( 'div', { className: 'wpecir-stat' }, h( 'span', { className: 'wpecir-stat__label' }, __( 'Store currency', 'checkout-impact-regression-for-woocommerce' ) ), h( 'strong', { className: 'wpecir-stat__value' }, store.currency || '—' ) )
			),
			h( 'div', { className: 'wpecir-grid wpecir-grid--2-1' },
				h( Card, { id: 'wpecir-how', eyebrow: __( 'How it works', 'checkout-impact-regression-for-woocommerce' ), title: __( 'Catch checkout regressions in three steps', 'checkout-impact-regression-for-woocommerce' ) },
					h( 'ol', { className: 'wpecir-steps' }, steps.map( function ( step, index ) {
						return h( 'li', { key: index, className: 'wpecir-step' },
							h( 'span', { className: 'wpecir-step__number', 'aria-hidden': 'true' }, String( index + 1 ) ),
							h( 'div', null, h( 'h3', null, step.title ), h( 'p', null, step.text ) ),
							h( 'button', { type: 'button', className: 'button', onClick: function () { props.go( step.tab ); } }, __( 'Open', 'checkout-impact-regression-for-woocommerce' ) )
						);
					} ) )
				),
				h( Card, { id: 'wpecir-recent', eyebrow: __( 'Change log', 'checkout-impact-regression-for-woocommerce' ), title: __( 'Recent changes', 'checkout-impact-regression-for-woocommerce' ), actions: h( 'button', { type: 'button', className: 'button-link', onClick: function () { props.go( 'changes' ); } }, __( 'View all', 'checkout-impact-regression-for-woocommerce' ) ) },
					props.eventsLoading ? h( 'p', { role: 'status' }, __( 'Loading…', 'checkout-impact-regression-for-woocommerce' ) ) : recent.length ? h( 'ul', { className: 'wpecir-mini-list' }, recent.map( function ( event ) {
						return h( 'li', { key: event.id }, h( 'strong', null, event.object_label || event.object_type ), h( 'span', null, formatDate( event.occurred_at ) ) );
					} ) ) : h( Empty, { title: __( 'No changes recorded yet', 'checkout-impact-regression-for-woocommerce' ), text: __( 'Plugin updates and WooCommerce payment or shipping setting changes will appear here.', 'checkout-impact-regression-for-woocommerce' ) } ),
					recent.length ? h( 'p', { className: 'wpecir-help' }, __( 'A change was recorded. Compare your scenarios to see whether checkout was affected.', 'checkout-impact-regression-for-woocommerce' ) ) : null
				)
			),
			h( Card, { id: 'wpecir-scope', className: 'wpecir-card--quiet', eyebrow: __( 'Good to know', 'checkout-impact-regression-for-woocommerce' ), title: __( 'This plugin never places orders', 'checkout-impact-regression-for-woocommerce' ) },
				h( 'p', null, __( 'You record what checkout shows for each scenario (for example on a staging copy or with a test product). Checkout Impact stores and compares those values. It does not run checkout, create orders, charge payments or send emails.', 'checkout-impact-regression-for-woocommerce' ) )
			)
		);
	}

	function ScenariosTab( props ) {
		const blank = { title: '', country: store.baseCountry || 'DE', currency: store.currency || 'EUR', productId: '', quantity: '1', shippingMethod: '', shippingCost: '', coupon: '', discount: '' };
		const [ form, setForm ] = useState( blank );
		const [ editingId, setEditingId ] = useState( null );
		const [ busy, setBusy ] = useState( false );
		const [ message, setMessage ] = useState( null );
		const [ openPanel, setOpenPanel ] = useState( null );
		const [ panelRows, setPanelRows ] = useState( [] );
		const set = function ( key ) { return function ( value ) { setForm( Object.assign( {}, form, { [ key ]: value } ) ); }; };

		const reset = function () { setForm( blank ); setEditingId( null ); };

		const edit = function ( id ) {
			setMessage( null );
			wp.apiFetch( { path: API + '/scenarios/' + id } ).then( function ( scenario ) {
				const version = scenario.latest_version || {};
				const input = version.input || {};
				const expected = version.expectation || {};
				const item = input.cart && input.cart.items && input.cart.items[ 0 ] ? input.cart.items[ 0 ] : {};
				setEditingId( Number( scenario.id ) );
				setForm( {
					title: scenario.title || '',
					country: input.market && input.market.country ? input.market.country : blank.country,
					currency: input.currency || blank.currency,
					productId: item.product_id ? String( item.product_id ) : '',
					quantity: item.quantity ? String( item.quantity ) : '1',
					shippingMethod: expected.shipping_method || '',
					shippingCost: expected.shipping_cost !== undefined ? String( expected.shipping_cost ) : '',
					coupon: expected.coupon_code || '',
					discount: expected.coupon_discount !== undefined ? String( expected.coupon_discount ) : '',
				} );
				window.scrollTo( { top: 0, behavior: 'smooth' } );
			} ).catch( function ( error ) { setMessage( { tone: 'error', text: errorMessage( error, __( 'Could not load this scenario.', 'checkout-impact-regression-for-woocommerce' ) ) } ); } );
		};

		const save = function ( event ) {
			event.preventDefault();
			setMessage( null );
			const expectation = {};
			if ( form.shippingMethod.trim() ) {
				expectation.shipping_method = form.shippingMethod.trim();
			}
			if ( form.shippingCost.trim() !== '' ) {
				if ( ! isMoney( form.shippingCost ) ) {
					setMessage( { tone: 'error', text: __( 'Enter the shipping cost as a plain number, for example 7.50.', 'checkout-impact-regression-for-woocommerce' ) } );
					return;
				}
				expectation.shipping_cost = form.shippingCost.trim();
			}
			if ( form.coupon.trim() ) {
				expectation.coupon_code = form.coupon.trim();
			}
			if ( form.discount.trim() !== '' ) {
				if ( ! form.coupon.trim() ) {
					setMessage( { tone: 'error', text: __( 'Add the coupon code that gives this discount.', 'checkout-impact-regression-for-woocommerce' ) } );
					return;
				}
				if ( ! isMoney( form.discount ) ) {
					setMessage( { tone: 'error', text: __( 'Enter the coupon discount as a plain number, for example 5.', 'checkout-impact-regression-for-woocommerce' ) } );
					return;
				}
				expectation.coupon_discount = form.discount.trim();
			}
			setBusy( true );
			wp.apiFetch( {
				path: editingId ? API + '/scenarios/' + editingId : API + '/scenarios',
				method: editingId ? 'PUT' : 'POST',
				data: {
					title: form.title.trim(),
					input: {
						market: { country: form.country.trim().toUpperCase() },
						cart: { items: [ { product_id: Number( form.productId ), quantity: Number( form.quantity ) } ] },
						currency: form.currency.trim().toUpperCase(),
					},
					expectation: expectation,
				},
			} ).then( function () {
				setMessage( { tone: 'success', text: editingId ? __( 'Saved as a new scenario version. Approve a new baseline before comparing.', 'checkout-impact-regression-for-woocommerce' ) : __( 'Scenario saved. Next, record and approve its baseline in Compare.', 'checkout-impact-regression-for-woocommerce' ) } );
				reset();
				return props.refresh();
			} ).catch( function ( error ) {
				setMessage( { tone: 'error', text: errorMessage( error, __( 'Could not save this scenario.', 'checkout-impact-regression-for-woocommerce' ) ) } );
			} ).finally( function () { setBusy( false ); } );
		};

		const remove = function ( scenario ) {
			/* translators: %s: scenario name. */
			if ( ! window.confirm( sprintf( __( 'Delete “%s”, its versions, baselines and reports? This cannot be undone.', 'checkout-impact-regression-for-woocommerce' ), scenario.title ) ) ) {
				return;
			}
			wp.apiFetch( { path: API + '/scenarios/' + scenario.id, method: 'DELETE' } )
				.then( function () { setMessage( { tone: 'success', text: __( 'Scenario deleted.', 'checkout-impact-regression-for-woocommerce' ) } ); if ( Number( editingId ) === Number( scenario.id ) ) { reset(); } return props.refresh(); } )
				.catch( function ( error ) { setMessage( { tone: 'error', text: errorMessage( error, __( 'Could not delete this scenario.', 'checkout-impact-regression-for-woocommerce' ) ) } ); } );
		};

		const togglePanel = function ( id, kind ) {
			const key = kind + ':' + id;
			if ( openPanel === key ) {
				setOpenPanel( null );
				return;
			}
			setOpenPanel( key );
			setPanelRows( null );
			wp.apiFetch( { path: API + '/scenarios/' + id + '/' + kind } )
				.then( function ( rows ) { setPanelRows( Array.isArray( rows ) ? rows : [] ); } )
				.catch( function ( error ) { setPanelRows( [] ); setMessage( { tone: 'error', text: errorMessage( error, __( 'Could not load history.', 'checkout-impact-regression-for-woocommerce' ) ) } ); } );
		};

		const productField = ( store.products || [] ).length ? h( Field, { id: 'wpecir-product', label: __( 'Test product', 'checkout-impact-regression-for-woocommerce' ), value: form.productId, onChange: set( 'productId' ), required: true, placeholder: __( 'Choose a product', 'checkout-impact-regression-for-woocommerce' ), options: store.products, help: __( 'Use a product you can safely add to a test cart.', 'checkout-impact-regression-for-woocommerce' ) } )
			: h( Field, { id: 'wpecir-product', label: __( 'Test product ID', 'checkout-impact-regression-for-woocommerce' ), type: 'number', min: 1, value: form.productId, onChange: set( 'productId' ), required: true } );

		return h( 'div', { className: 'wpecir-grid wpecir-grid--1-1' },
			h( Card, { id: 'wpecir-builder', eyebrow: editingId ? __( 'Editing', 'checkout-impact-regression-for-woocommerce' ) : __( 'New scenario', 'checkout-impact-regression-for-woocommerce' ), title: editingId ? form.title || __( 'Edit scenario', 'checkout-impact-regression-for-woocommerce' ) : __( 'Create a scenario', 'checkout-impact-regression-for-woocommerce' ), description: __( 'A scenario is one customer journey: where they ship to, what is in the cart and what checkout should show.', 'checkout-impact-regression-for-woocommerce' ) },
				h( Notice, { tone: message && message.tone, message: message && message.text, onDismiss: function () { setMessage( null ); } } ),
				h( 'form', { className: 'wpecir-form', onSubmit: save },
					h( Field, { id: 'wpecir-title', wide: true, label: __( 'Scenario name', 'checkout-impact-regression-for-woocommerce' ), value: form.title, onChange: set( 'title' ), required: true, maxLength: 191, placeholder: __( 'e.g. Germany — 1 × T-shirt — WELCOME10', 'checkout-impact-regression-for-woocommerce' ) } ),
					h( 'fieldset', { className: 'wpecir-fieldset' },
						h( 'legend', null, __( 'Customer and cart', 'checkout-impact-regression-for-woocommerce' ) ),
						h( 'div', { className: 'wpecir-form-grid' },
							( store.countries || [] ).length ? h( Field, { id: 'wpecir-country', label: __( 'Shipping country', 'checkout-impact-regression-for-woocommerce' ), value: form.country, onChange: set( 'country' ), required: true, options: store.countries } )
								: h( Field, { id: 'wpecir-country', label: __( 'Shipping country code', 'checkout-impact-regression-for-woocommerce' ), value: form.country, onChange: set( 'country' ), required: true, maxLength: 2, help: __( 'Two letters, for example DE.', 'checkout-impact-regression-for-woocommerce' ) } ),
							( store.currencies || [] ).length ? h( Field, { id: 'wpecir-currency', label: __( 'Currency', 'checkout-impact-regression-for-woocommerce' ), value: form.currency, onChange: set( 'currency' ), required: true, options: store.currencies } )
								: h( Field, { id: 'wpecir-currency', label: __( 'Currency code', 'checkout-impact-regression-for-woocommerce' ), value: form.currency, onChange: set( 'currency' ), required: true, maxLength: 3 } ),
							productField,
							h( Field, { id: 'wpecir-quantity', label: __( 'Quantity', 'checkout-impact-regression-for-woocommerce' ), type: 'number', min: 1, max: 100, value: form.quantity, onChange: set( 'quantity' ), required: true } )
						)
					),
					h( 'fieldset', { className: 'wpecir-fieldset' },
						h( 'legend', null, __( 'What checkout should show', 'checkout-impact-regression-for-woocommerce' ) ),
						h( 'div', { className: 'wpecir-form-grid' },
							h( Field, { id: 'wpecir-shipping-method', label: __( 'Shipping method', 'checkout-impact-regression-for-woocommerce' ), optional: true, value: form.shippingMethod, onChange: set( 'shippingMethod' ), maxLength: 100, list: 'wpecir-shipping-methods', placeholder: 'flat_rate:3', help: __( 'Method ID and instance, as WooCommerce uses it.', 'checkout-impact-regression-for-woocommerce' ) } ),
							h( Field, { id: 'wpecir-shipping-cost', label: __( 'Shipping cost', 'checkout-impact-regression-for-woocommerce' ), optional: true, inputMode: 'decimal', value: form.shippingCost, onChange: set( 'shippingCost' ), placeholder: '7.50' } ),
							h( Field, { id: 'wpecir-coupon', label: __( 'Coupon code', 'checkout-impact-regression-for-woocommerce' ), optional: true, value: form.coupon, onChange: set( 'coupon' ), maxLength: 100, placeholder: 'WELCOME10' } ),
							h( Field, { id: 'wpecir-discount', label: __( 'Coupon discount', 'checkout-impact-regression-for-woocommerce' ), optional: true, inputMode: 'decimal', value: form.discount, onChange: set( 'discount' ), placeholder: '5.00' } )
						)
					),
					h( 'div', { className: 'wpecir-form-actions' },
						h( 'button', { type: 'submit', className: 'button button-primary', disabled: busy || ! config.woocommerceActive }, busy ? __( 'Saving…', 'checkout-impact-regression-for-woocommerce' ) : editingId ? __( 'Save new version', 'checkout-impact-regression-for-woocommerce' ) : __( 'Save scenario', 'checkout-impact-regression-for-woocommerce' ) ),
						editingId ? h( 'button', { type: 'button', className: 'button', onClick: reset, disabled: busy }, __( 'Cancel', 'checkout-impact-regression-for-woocommerce' ) ) : null
					)
				)
			),
			h( Card, { id: 'wpecir-library', eyebrow: __( 'Library', 'checkout-impact-regression-for-woocommerce' ), title: __( 'Saved scenarios', 'checkout-impact-regression-for-woocommerce' ), actions: h( 'button', { type: 'button', className: 'button', onClick: props.refresh, disabled: props.loading }, __( 'Refresh', 'checkout-impact-regression-for-woocommerce' ) ) },
				props.loading ? h( 'p', { role: 'status' }, __( 'Loading scenarios…', 'checkout-impact-regression-for-woocommerce' ) ) : props.scenarios.length ? h( 'ul', { className: 'wpecir-list' }, props.scenarios.map( function ( scenario ) {
					const versionsOpen = openPanel === 'versions:' + scenario.id;
					const baselinesOpen = openPanel === 'baselines:' + scenario.id;
					return h( 'li', { key: scenario.id, className: 'wpecir-list__item' + ( Number( editingId ) === Number( scenario.id ) ? ' is-active' : '' ) },
						h( 'div', { className: 'wpecir-list__main' },
							h( 'h3', null, scenario.title ),
							h( 'p', null, sprintf( /* translators: %s: date. */ __( 'Updated %s', 'checkout-impact-regression-for-woocommerce' ), formatDate( scenario.updated_at ) ) )
						),
						h( 'div', { className: 'wpecir-list__actions' },
							h( 'button', { type: 'button', className: 'button button-primary button-small', onClick: function () { props.compare( scenario.id ); } }, __( 'Compare', 'checkout-impact-regression-for-woocommerce' ) ),
							h( 'button', { type: 'button', className: 'button button-small', onClick: function () { edit( scenario.id ); } }, __( 'Edit', 'checkout-impact-regression-for-woocommerce' ) ),
							h( 'button', { type: 'button', className: 'button button-small', 'aria-expanded': versionsOpen, onClick: function () { togglePanel( scenario.id, 'versions' ); } }, __( 'Versions', 'checkout-impact-regression-for-woocommerce' ) ),
							h( 'button', { type: 'button', className: 'button button-small', 'aria-expanded': baselinesOpen, onClick: function () { togglePanel( scenario.id, 'baselines' ); } }, __( 'Baselines', 'checkout-impact-regression-for-woocommerce' ) ),
							h( 'button', { type: 'button', className: 'button-link button-link-delete', onClick: function () { remove( scenario ); } }, __( 'Delete', 'checkout-impact-regression-for-woocommerce' ) )
						),
						( versionsOpen || baselinesOpen ) ? h( 'div', { className: 'wpecir-list__panel', 'aria-live': 'polite' },
							panelRows === null ? h( 'p', null, __( 'Loading…', 'checkout-impact-regression-for-woocommerce' ) ) : ! panelRows.length ? h( 'p', null, versionsOpen ? __( 'No versions found.', 'checkout-impact-regression-for-woocommerce' ) : __( 'No approved baseline yet. Approve one in Compare.', 'checkout-impact-regression-for-woocommerce' ) ) :
								h( 'ol', { className: 'wpecir-timeline' }, panelRows.map( function ( row ) {
									if ( versionsOpen ) {
										const e = row.expectation || {};
										const parts = [ e.shipping_method, e.shipping_cost !== undefined ? __( 'Shipping', 'checkout-impact-regression-for-woocommerce' ) + ' ' + e.shipping_cost : '', e.coupon_code, e.coupon_discount !== undefined ? __( 'Discount', 'checkout-impact-regression-for-woocommerce' ) + ' ' + e.coupon_discount : '' ].filter( Boolean );
										return h( 'li', { key: row.id }, h( 'strong', null, sprintf( /* translators: %d: version number. */ __( 'Version %d', 'checkout-impact-regression-for-woocommerce' ), row.version ) ), h( 'span', null, formatDate( row.created_at ) ), parts.length ? h( 'span', null, parts.join( ' · ' ) ) : null );
									}
									return h( 'li', { key: row.id }, h( 'strong', null, sprintf( /* translators: %d: revision number. */ __( 'Baseline revision %d', 'checkout-impact-regression-for-woocommerce' ), row.revision ) ), h( 'span', null, formatDate( row.approved_at ) ),
										h( 'span', null, FIELDS.map( function ( field ) { const item = row.observation && row.observation[ field.key ]; return field.label + ': ' + ( item ? ( item.state === 'observed' ? display( item.value ) : __( 'missing', 'checkout-impact-regression-for-woocommerce' ) ) : '—' ); } ).join( ' · ' ) ) );
								} ) )
						) : null
					);
				} ) ) : h( Empty, { title: __( 'No scenarios yet', 'checkout-impact-regression-for-woocommerce' ), text: __( 'Start with your most important customer journey, for example your biggest shipping country with your best-selling product.', 'checkout-impact-regression-for-woocommerce' ) } )
			)
		);
	}

	function CompareTab( props ) {
		const [ scenarioId, setScenarioId ] = useState( props.initialScenario ? String( props.initialScenario ) : '' );
		const [ before, setBefore ] = useState( emptyObservations );
		const [ after, setAfter ] = useState( emptyObservations );
		const [ baseline, setBaseline ] = useState( null );
		const [ baselineState, setBaselineState ] = useState( 'idle' );
		const [ result, setResult ] = useState( null );
		const [ busy, setBusy ] = useState( false );
		const [ message, setMessage ] = useState( null );
		const [ savedId, setSavedId ] = useState( null );

		useEffect( function () {
			if ( props.initialScenario ) {
				setScenarioId( String( props.initialScenario ) );
			}
		}, [ props.initialScenario ] );

		useEffect( function () {
			if ( props.onSelect ) {
				props.onSelect( scenarioId );
			}
			setBefore( emptyObservations() );
			setAfter( emptyObservations() );
			setBaseline( null );
			setResult( null );
			setSavedId( null );
			setMessage( null );
			if ( ! scenarioId ) {
				setBaselineState( 'idle' );
				return;
			}
			const requested = Number( scenarioId );
			setBaselineState( 'loading' );
			Promise.all( [
				wp.apiFetch( { path: API + '/scenarios/' + requested } ),
				wp.apiFetch( { path: API + '/scenarios/' + requested + '/baselines' } ),
			] ).then( function ( results ) {
				const version = results[ 0 ] && results[ 0 ].latest_version;
				const rows = Array.isArray( results[ 1 ] ) ? results[ 1 ] : [];
				const approved = rows.find( function ( row ) { return row && row.state === 'approved'; } );
				if ( ! approved ) {
					setBaselineState( 'none' );
					return;
				}
				if ( ! version || Number( approved.scenario_version_id ) !== Number( version.id ) || approved.context_fingerprint !== version.context_fingerprint ) {
					setBaselineState( 'stale' );
					return;
				}
				const values = emptyObservations();
				FIELDS.forEach( function ( field ) {
					const saved = approved.observation && approved.observation[ field.key ];
					if ( saved && saved.state ) {
						let value = saved.value;
						if ( value && typeof value === 'object' && value.amount !== undefined ) {
							value = value.amount;
						}
						if ( Array.isArray( value ) ) {
							value = value.join( ',' );
						}
						values[ field.key ] = { state: saved.state, value: value === undefined ? '' : String( value ) };
					}
				} );
				setBefore( values );
				setBaseline( approved );
				setBaselineState( 'ready' );
			} ).catch( function ( error ) {
				setBaselineState( 'none' );
				setMessage( { tone: 'error', text: errorMessage( error, __( 'Could not load the scenario.', 'checkout-impact-regression-for-woocommerce' ) ) } );
			} );
		}, [ scenarioId ] );

		const touch = function () { setResult( null ); setSavedId( null ); };
		const changeAfter = function ( key, value ) { setAfter( Object.assign( {}, after, { [ key ]: value } ) ); touch(); };
		const changeBefore = function ( key, value ) { setBefore( Object.assign( {}, before, { [ key ]: value } ) ); touch(); };
		const useBaseline = Boolean( baseline );

		const payload = function () {
			const data = { scenario_id: Number( scenarioId ), after: toPayload( after ) };
			if ( useBaseline ) {
				data.baseline_id = Number( baseline.id );
			} else {
				data.before = toPayload( before );
			}
			return data;
		};
		const anyRecorded = FIELDS.some( function ( field ) { return after[ field.key ].state !== 'not_observed'; } );

		const preview = function ( event ) {
			event.preventDefault();
			setBusy( true );
			setMessage( null );
			wp.apiFetch( { path: API + '/comparisons/preview', method: 'POST', data: payload() } )
				.then( function ( response ) { setResult( response ); } )
				.catch( function ( error ) { setMessage( { tone: 'error', text: errorMessage( error, __( 'Could not compare these values.', 'checkout-impact-regression-for-woocommerce' ) ) } ); } )
				.finally( function () { setBusy( false ); } );
		};

		const saveReport = function () {
			setBusy( true );
			setMessage( null );
			wp.apiFetch( { path: API + '/comparisons', method: 'POST', data: payload() } )
				.then( function ( response ) {
					setResult( response );
					setSavedId( response.report && response.report.id ? response.report.id : null );
					setMessage( { tone: 'success', text: __( 'Report saved. You can find it under Reports.', 'checkout-impact-regression-for-woocommerce' ) } );
				} )
				.catch( function ( error ) { setMessage( { tone: 'error', text: errorMessage( error, __( 'Could not save the report.', 'checkout-impact-regression-for-woocommerce' ) ) } ); } )
				.finally( function () { setBusy( false ); } );
		};

		const approve = function () {
			/* translators: confirmation before approving a baseline. */
			if ( ! window.confirm( __( 'Approve these After values as the new known-good baseline for this scenario?', 'checkout-impact-regression-for-woocommerce' ) ) ) {
				return;
			}
			setBusy( true );
			setMessage( null );
			wp.apiFetch( { path: API + '/scenarios/' + Number( scenarioId ) + '/baselines', method: 'POST', data: { observations: toPayload( after ) } } )
				.then( function ( response ) {
					const approved = response.baseline || {};
					setMessage( { tone: 'success', text: sprintf( /* translators: %d: revision number. */ __( 'Baseline revision %d approved. Future comparisons start from these values.', 'checkout-impact-regression-for-woocommerce' ), approved.revision || 1 ) } );
					setBefore( after );
					setBaseline( Object.assign( { id: approved.id, revision: approved.revision }, approved ) );
					setBaselineState( 'ready' );
					setAfter( emptyObservations() );
					setResult( null );
				} )
				.catch( function ( error ) { setMessage( { tone: 'error', text: errorMessage( error, __( 'Could not approve this baseline.', 'checkout-impact-regression-for-woocommerce' ) ) } ); } )
				.finally( function () { setBusy( false ); } );
		};

		if ( ! props.scenarios.length && ! props.loading ) {
			return h( Card, { id: 'wpecir-compare-empty' }, h( Empty, { title: __( 'Create a scenario first', 'checkout-impact-regression-for-woocommerce' ), text: __( 'Comparisons always belong to a saved scenario.', 'checkout-impact-regression-for-woocommerce' ), action: h( 'button', { type: 'button', className: 'button button-primary', onClick: function () { props.go( 'scenarios' ); } }, __( 'Create a scenario', 'checkout-impact-regression-for-woocommerce' ) ) } ) );
		}

		const baselineInfo = {
			idle: null,
			loading: h( Notice, { tone: 'info', message: __( 'Loading the approved baseline…', 'checkout-impact-regression-for-woocommerce' ) } ),
			none: h( Notice, { tone: 'info', message: __( 'This scenario has no approved baseline yet. Record what checkout shows today in After, then approve it as the baseline. You can also type Before values by hand for a one-off comparison.', 'checkout-impact-regression-for-woocommerce' ) } ),
			stale: h( Notice, { tone: 'warning', message: __( 'The scenario was edited after its baseline was approved. Record and approve a new baseline for the current version.', 'checkout-impact-regression-for-woocommerce' ) } ),
			ready: baseline ? h( Notice, { tone: 'success', message: sprintf( /* translators: 1: revision, 2: date. */ __( 'Comparing against approved baseline revision %1$d from %2$s.', 'checkout-impact-regression-for-woocommerce' ), baseline.revision, formatDate( baseline.approved_at ) ) } ) : null,
		}[ baselineState ];

		return h( 'div', { className: 'wpecir-stack' },
			h( Card, { id: 'wpecir-compare', eyebrow: __( 'Compare', 'checkout-impact-regression-for-woocommerce' ), title: __( 'Before and after a change', 'checkout-impact-regression-for-woocommerce' ), description: __( 'Open checkout for this scenario yourself (ideally on a staging copy), then record what you see under After. Fields you leave as “Not recorded” are reported as needing review, never as unchanged.', 'checkout-impact-regression-for-woocommerce' ) },
				h( 'form', { onSubmit: preview, className: 'wpecir-form' },
					h( 'div', { className: 'wpecir-form-grid' },
						h( Field, { id: 'wpecir-compare-scenario', label: __( 'Scenario', 'checkout-impact-regression-for-woocommerce' ), value: scenarioId, onChange: setScenarioId, required: true, placeholder: __( 'Choose a scenario', 'checkout-impact-regression-for-woocommerce' ), options: props.scenarios.map( function ( s ) { return { value: String( s.id ), label: s.title }; } ) } )
					),
					baselineInfo,
					h( Notice, { tone: message && message.tone, message: message && message.text, onDismiss: function () { setMessage( null ); } } ),
					scenarioId ? h( 'div', { className: 'wpecir-columns' },
						h( 'section', { className: 'wpecir-column', 'aria-labelledby': 'wpecir-before-title' },
							h( 'header', { className: 'wpecir-column__header' }, h( 'h3', { id: 'wpecir-before-title' }, __( 'Before', 'checkout-impact-regression-for-woocommerce' ) ), useBaseline ? h( Badge, { tone: 'success' }, __( 'Approved baseline', 'checkout-impact-regression-for-woocommerce' ) ) : h( Badge, null, __( 'Typed by hand', 'checkout-impact-regression-for-woocommerce' ) ) ),
							FIELDS.map( function ( field ) { return h( ObservationField, { key: field.key, side: 'before', field: field, value: before[ field.key ], readOnly: useBaseline, onChange: function ( value ) { changeBefore( field.key, value ); } } ); } )
						),
						h( 'section', { className: 'wpecir-column wpecir-column--after', 'aria-labelledby': 'wpecir-after-title' },
							h( 'header', { className: 'wpecir-column__header' }, h( 'h3', { id: 'wpecir-after-title' }, __( 'After', 'checkout-impact-regression-for-woocommerce' ) ), h( Badge, { tone: 'info' }, __( 'What checkout shows now', 'checkout-impact-regression-for-woocommerce' ) ) ),
							FIELDS.map( function ( field ) { return h( ObservationField, { key: field.key, side: 'after', field: field, value: after[ field.key ], onChange: function ( value ) { changeAfter( field.key, value ); } } ); } )
						)
					) : null,
					scenarioId ? h( 'div', { className: 'wpecir-form-actions' },
						h( 'button', { type: 'submit', className: 'button button-primary', disabled: busy || ! anyRecorded }, busy ? __( 'Working…', 'checkout-impact-regression-for-woocommerce' ) : __( 'Compare', 'checkout-impact-regression-for-woocommerce' ) ),
						h( 'button', { type: 'button', className: 'button', disabled: busy || ! isComplete( after ), onClick: approve, title: isComplete( after ) ? '' : __( 'Record every After field as Observed or Confirmed missing first.', 'checkout-impact-regression-for-woocommerce' ) }, __( 'Approve After as baseline', 'checkout-impact-regression-for-woocommerce' ) ),
						! isComplete( after ) ? h( 'span', { className: 'wpecir-help' }, __( 'To approve a baseline, record every After field as Observed or Confirmed missing.', 'checkout-impact-regression-for-woocommerce' ) ) : null
					) : null
				)
			),
			result ? h( Card, { id: 'wpecir-result', eyebrow: __( 'Result', 'checkout-impact-regression-for-woocommerce' ), title: result.scenario_title, description: sprintf( /* translators: 1: version number, 2: currency code. */ __( 'Scenario version %1$d · %2$s', 'checkout-impact-regression-for-woocommerce' ), result.scenario_version, result.currency ), actions: h( 'button', { type: 'button', className: 'button button-primary', disabled: busy || Boolean( savedId ), onClick: saveReport }, savedId ? __( 'Report saved', 'checkout-impact-regression-for-woocommerce' ) : __( 'Save report', 'checkout-impact-regression-for-woocommerce' ) ) },
				h( Summary, { summary: result.summary } ),
				h( ResultTable, { changes: result.changes } )
			) : null
		);
	}

	function ReportsTab( props ) {
		const [ scenarioId, setScenarioId ] = useState( props.focusId ? String( props.focusId ) : ( props.scenarios[ 0 ] ? String( props.scenarios[ 0 ].id ) : '' ) );
		const [ reports, setReports ] = useState( null );
		const [ error, setError ] = useState( '' );

		useEffect( function () {
			if ( ! scenarioId && props.scenarios[ 0 ] ) {
				setScenarioId( String( props.scenarios[ 0 ].id ) );
			}
		}, [ props.scenarios ] );

		useEffect( function () {
			if ( ! scenarioId ) {
				setReports( [] );
				return;
			}
			setReports( null );
			setError( '' );
			wp.apiFetch( { path: API + '/scenarios/' + Number( scenarioId ) + '/comparisons' } )
				.then( function ( response ) { setReports( Array.isArray( response.reports ) ? response.reports : [] ); } )
				.catch( function ( requestError ) { setReports( [] ); setError( errorMessage( requestError, __( 'Could not load reports.', 'checkout-impact-regression-for-woocommerce' ) ) ); } );
		}, [ scenarioId ] );

		const extra = hooks ? hooks.applyFilters( 'wpecir.reportActions', [], { scenarioId: scenarioId, reports: reports || [] } ) : [];

		return h( Card, { id: 'wpecir-reports', eyebrow: __( 'Reports', 'checkout-impact-regression-for-woocommerce' ), title: __( 'Saved comparison reports', 'checkout-impact-regression-for-woocommerce' ), description: props.settings ? sprintf( /* translators: %d: number of days. */ __( 'Reports are kept for %d days, then deleted automatically. Change this under Settings.', 'checkout-impact-regression-for-woocommerce' ), props.settings.retention_days ) : null, actions: extra.length ? h( Fragment, null, extra ) : null },
			props.scenarios.length ? h( 'div', { className: 'wpecir-form-grid' }, h( Field, { id: 'wpecir-report-scenario', label: __( 'Scenario', 'checkout-impact-regression-for-woocommerce' ), value: scenarioId, onChange: setScenarioId, options: props.scenarios.map( function ( s ) { return { value: String( s.id ), label: s.title }; } ) } ) ) : null,
			h( Notice, { tone: 'error', message: error } ),
			reports === null ? h( 'p', { role: 'status' }, __( 'Loading reports…', 'checkout-impact-regression-for-woocommerce' ) ) : reports.length ? h( 'ul', { className: 'wpecir-reports' }, reports.map( function ( report ) {
				return h( 'li', { key: report.id, className: 'wpecir-report' },
					h( 'details', null,
						h( 'summary', null,
							h( 'span', { className: 'wpecir-report__title' }, sprintf( /* translators: %d: report number. */ __( 'Report #%d', 'checkout-impact-regression-for-woocommerce' ), report.id ) ),
							h( 'span', { className: 'wpecir-report__meta' }, formatDate( report.created_at ) ),
							report.baseline_id ? h( Badge, { tone: 'success' }, __( 'Against approved baseline', 'checkout-impact-regression-for-woocommerce' ) ) : h( Badge, null, __( 'Manual before values', 'checkout-impact-regression-for-woocommerce' ) ),
							h( 'span', { className: 'wpecir-report__counts' },
								report.summary.changed ? h( Badge, { tone: 'warning' }, sprintf( /* translators: %d: count. */ __( '%d changed', 'checkout-impact-regression-for-woocommerce' ), report.summary.changed ) ) : null,
								report.summary.missing ? h( Badge, { tone: 'critical' }, sprintf( /* translators: %d: count. */ __( '%d missing', 'checkout-impact-regression-for-woocommerce' ), report.summary.missing ) ) : null,
								report.summary.not_comparable ? h( Badge, null, sprintf( /* translators: %d: count. */ __( '%d need review', 'checkout-impact-regression-for-woocommerce' ), report.summary.not_comparable ) ) : null,
								! report.summary.changed && ! report.summary.missing && ! report.summary.not_comparable ? h( Badge, { tone: 'success' }, __( 'No differences', 'checkout-impact-regression-for-woocommerce' ) ) : null
							)
						),
						h( 'div', { className: 'wpecir-report__body' },
							h( ResultTable, { changes: report.changes } ),
							h( 'p', { className: 'wpecir-help' }, sprintf( /* translators: %s: date. */ __( 'Deleted automatically after %s.', 'checkout-impact-regression-for-woocommerce' ), formatDate( report.expires_at ) ) )
						)
					)
				);
			} ) ) : h( Empty, { title: __( 'No saved reports', 'checkout-impact-regression-for-woocommerce' ), text: __( 'Run a comparison and choose “Save report” to keep it here.', 'checkout-impact-regression-for-woocommerce' ) } )
		);
	}

	function ChangesTab( props ) {
		const labels = {
			plugin_update: __( 'Plugin updated', 'checkout-impact-regression-for-woocommerce' ),
			woocommerce_setting: __( 'WooCommerce setting changed', 'checkout-impact-regression-for-woocommerce' ),
			plugin_setting: __( 'Plugin setting changed', 'checkout-impact-regression-for-woocommerce' ),
			manual: __( 'Manual note', 'checkout-impact-regression-for-woocommerce' ),
		};
		return h( Card, { id: 'wpecir-changes', eyebrow: __( 'Change log', 'checkout-impact-regression-for-woocommerce' ), title: __( 'Plugin updates and settings changes', 'checkout-impact-regression-for-woocommerce' ), description: __( 'Recorded automatically so you know when to compare. Setting values are never stored, only a fingerprint showing that something changed.', 'checkout-impact-regression-for-woocommerce' ), actions: h( 'button', { type: 'button', className: 'button', onClick: props.refresh, disabled: props.loading }, __( 'Refresh', 'checkout-impact-regression-for-woocommerce' ) ) },
			props.loading ? h( 'p', { role: 'status' }, __( 'Loading…', 'checkout-impact-regression-for-woocommerce' ) ) : props.events.length ? h( 'div', { className: 'wpecir-table-wrap' }, h( 'table', { className: 'wpecir-table' },
				h( 'thead', null, h( 'tr', null,
					h( 'th', { scope: 'col' }, __( 'When', 'checkout-impact-regression-for-woocommerce' ) ),
					h( 'th', { scope: 'col' }, __( 'Type', 'checkout-impact-regression-for-woocommerce' ) ),
					h( 'th', { scope: 'col' }, __( 'What', 'checkout-impact-regression-for-woocommerce' ) ),
					h( 'th', { scope: 'col' }, __( 'Fingerprint', 'checkout-impact-regression-for-woocommerce' ) )
				) ),
				h( 'tbody', null, props.events.map( function ( event ) {
					return h( 'tr', { key: event.id },
						h( 'td', null, formatDate( event.occurred_at ) ),
						h( 'td', null, h( Badge, { tone: event.source === 'plugin_update' ? 'info' : 'warning' }, labels[ event.source ] || event.source ) ),
						h( 'td', null, h( 'code', null, event.object_label || event.object_type ) ),
						h( 'td', null, h( 'code', { title: event.context_fingerprint }, String( event.context_fingerprint || '' ).slice( 0, 12 ) ) )
					);
				} ) )
			) ) : h( Empty, { title: __( 'Nothing recorded yet', 'checkout-impact-regression-for-woocommerce' ), text: __( 'Updates to plugins and changes to WooCommerce payment and shipping settings are listed here.', 'checkout-impact-regression-for-woocommerce' ) } )
		);
	}

	function SettingsTab( props ) {
		const [ days, setDays ] = useState( props.settings ? String( props.settings.retention_days ) : '30' );
		const [ erase, setErase ] = useState( props.settings ? Boolean( props.settings.erase_on_uninstall ) : false );
		const [ busy, setBusy ] = useState( false );
		const [ message, setMessage ] = useState( null );
		useEffect( function () {
			if ( props.settings ) {
				setDays( String( props.settings.retention_days ) );
				setErase( Boolean( props.settings.erase_on_uninstall ) );
			}
		}, [ props.settings ] );
		const limits = props.settings && props.settings.limits ? props.settings.limits : { min_days: 7, max_days: 365 };
		const save = function ( event ) {
			event.preventDefault();
			setBusy( true );
			setMessage( null );
			wp.apiFetch( { path: API + '/settings', method: 'POST', data: { retention_days: String( days ), erase_on_uninstall: erase } } )
				.then( function ( response ) { props.onSaved( response ); setMessage( { tone: 'success', text: __( 'Settings saved.', 'checkout-impact-regression-for-woocommerce' ) } ); } )
				.catch( function ( error ) { setMessage( { tone: 'error', text: errorMessage( error, __( 'Could not save settings.', 'checkout-impact-regression-for-woocommerce' ) ) } ); } )
				.finally( function () { setBusy( false ); } );
		};
		return h( Card, { id: 'wpecir-settings', eyebrow: __( 'Settings', 'checkout-impact-regression-for-woocommerce' ), title: __( 'Data and privacy', 'checkout-impact-regression-for-woocommerce' ) },
			h( Notice, { tone: message && message.tone, message: message && message.text, onDismiss: function () { setMessage( null ); } } ),
			h( 'form', { className: 'wpecir-form wpecir-form--narrow', onSubmit: save },
				h( Field, { id: 'wpecir-retention', type: 'number', min: limits.min_days, max: limits.max_days, label: __( 'Keep saved reports for (days)', 'checkout-impact-regression-for-woocommerce' ), value: days, onChange: setDays, required: true, help: sprintf( /* translators: 1: minimum, 2: maximum. */ __( 'Between %1$d and %2$d days. Applies to reports saved from now on. Scenarios and baselines are kept until you delete them.', 'checkout-impact-regression-for-woocommerce' ), limits.min_days, limits.max_days ) } ),
				h( 'div', { className: 'wpecir-field' },
					h( 'label', { className: 'wpecir-check wpecir-check--danger' },
						h( 'input', { type: 'checkbox', checked: erase, onChange: function () { setErase( ! erase ); } } ),
						h( 'span', null, __( 'Delete all Checkout Impact data when the plugin is deleted', 'checkout-impact-regression-for-woocommerce' ) )
					),
					h( 'p', { className: 'wpecir-help' }, __( 'Off by default, so reinstalling keeps your scenarios. Deactivating the plugin never deletes data.', 'checkout-impact-regression-for-woocommerce' ) )
				),
				h( 'div', { className: 'wpecir-form-actions' }, h( 'button', { type: 'submit', className: 'button button-primary', disabled: busy }, busy ? __( 'Saving…', 'checkout-impact-regression-for-woocommerce' ) : __( 'Save settings', 'checkout-impact-regression-for-woocommerce' ) ) )
			)
		);
	}

	function DocsTab( props ) {
		const sections = [
			{ id: 'start', title: __( 'Getting started', 'checkout-impact-regression-for-woocommerce' ), body: [
				__( '1. Go to Scenarios and create a scenario for an important customer journey: shipping country, currency, one test product and quantity. Add what checkout should show (shipping method and cost, coupon and discount) if you know it.', 'checkout-impact-regression-for-woocommerce' ),
				__( '2. Open checkout yourself for that journey (a staging copy of your store is best). In Compare, choose the scenario and record what you see in the After column. Mark each field Observed or Confirmed missing, then click “Approve After as baseline”.', 'checkout-impact-regression-for-woocommerce' ),
				__( '3. After you update a plugin or change a WooCommerce setting, open checkout again, record the new values in After and click Compare. Save the report if you want to keep it.', 'checkout-impact-regression-for-woocommerce' ),
			] },
			{ id: 'concepts', title: __( 'Key terms', 'checkout-impact-regression-for-woocommerce' ), body: [
				__( 'Scenario: one customer journey. Editing it creates a new version; old versions are kept.', 'checkout-impact-regression-for-woocommerce' ),
				__( 'Baseline: the approved, known-good checkout values for the current scenario version. Approving again creates a new revision.', 'checkout-impact-regression-for-woocommerce' ),
				__( 'Report: a saved comparison of Before and After values. Reports are deleted automatically after the retention period.', 'checkout-impact-regression-for-woocommerce' ),
			] },
			{ id: 'states', title: __( 'Recording values', 'checkout-impact-regression-for-woocommerce' ), body: [
				__( 'Observed: you saw this value at checkout. Money uses plain numbers such as 7.50; shipping uses the WooCommerce rate ID such as flat_rate:3; payment methods are gateway IDs such as bacs or stripe.', 'checkout-impact-regression-for-woocommerce' ),
				__( 'Confirmed missing: you checked and the option is not offered (for example a shipping method disappeared).', 'checkout-impact-regression-for-woocommerce' ),
				__( 'Not recorded, Not supported here, Could not check: the field is reported as Needs review. It is never treated as unchanged.', 'checkout-impact-regression-for-woocommerce' ),
			] },
			{ id: 'results', title: __( 'Reading results', 'checkout-impact-regression-for-woocommerce' ), body: [
				__( 'Changed: the value differs from Before. Missing after change: it existed before and is gone now. Newly present: it was missing before and appears now. Unchanged: identical. Needs review: one side was not recorded or cannot be compared.', 'checkout-impact-regression-for-woocommerce' ),
			] },
			{ id: 'changes', title: __( 'Change log', 'checkout-impact-regression-for-woocommerce' ), body: [
				__( 'Plugin updates and changes to WooCommerce settings stored as “woocommerce_…_settings” (payment gateways, shipping method instances and similar) are recorded automatically. Values are not stored; secrets, emails and phone numbers are removed before a fingerprint is calculated.', 'checkout-impact-regression-for-woocommerce' ),
				__( 'Developers can track more options with the wpecir_tracked_option_names filter.', 'checkout-impact-regression-for-woocommerce' ),
			] },
			{ id: 'privacy', title: __( 'Privacy and data', 'checkout-impact-regression-for-woocommerce' ), body: [
				__( 'Use test products and made-up values. Values that look like email addresses, passwords, tokens or API keys are rejected.', 'checkout-impact-regression-for-woocommerce' ),
				__( 'The plugin stores data in its own database tables, sends nothing to external services and supports the WordPress personal data export and erase tools for the user IDs it records.', 'checkout-impact-regression-for-woocommerce' ),
				__( 'Deleting the plugin keeps your data unless you turn on the delete option in Settings.', 'checkout-impact-regression-for-woocommerce' ),
			] },
			{ id: 'limits', title: __( 'Current limits', 'checkout-impact-regression-for-woocommerce' ), body: [
				__( 'Checkout values are recorded by you; the plugin does not open checkout automatically. Each scenario has one product line. Only users who can manage WooCommerce can use this screen.', 'checkout-impact-regression-for-woocommerce' ),
			] },
		];
		return h( 'div', { className: 'wpecir-docs' },
			h( 'nav', { className: 'wpecir-docs__nav', 'aria-label': __( 'Documentation sections', 'checkout-impact-regression-for-woocommerce' ) },
				h( 'ul', null, sections.map( function ( section ) { return h( 'li', { key: section.id }, h( 'a', { href: '#docs-' + section.id, onClick: function ( event ) { event.preventDefault(); const target = document.getElementById( 'wpecir-docs-' + section.id ); if ( target ) { target.scrollIntoView( { behavior: 'smooth' } ); target.focus(); } } }, section.title ) ); } ) ),
				h( 'a', { className: 'button wpecir-docs__external', href: config.pluginUrl, target: '_blank', rel: 'noopener noreferrer' }, __( 'Plugin page', 'checkout-impact-regression-for-woocommerce' ), h( 'span', { className: 'screen-reader-text' }, ' ' + __( '(opens in a new tab)', 'checkout-impact-regression-for-woocommerce' ) ) )
			),
			h( 'div', { className: 'wpecir-docs__content' }, sections.map( function ( section ) {
				return h( Card, { key: section.id, id: 'wpecir-docs-' + section.id + '-title' },
					h( 'h2', { id: 'wpecir-docs-' + section.id, tabIndex: -1, className: 'wpecir-card__title' }, section.title ),
					section.body.map( function ( paragraph, index ) { return h( 'p', { key: index }, paragraph ); } ),
					section.id === 'start' ? h( 'p', null, h( 'button', { type: 'button', className: 'button button-primary', onClick: function () { props.go( 'scenarios' ); } }, __( 'Create your first scenario', 'checkout-impact-regression-for-woocommerce' ) ) ) : null
				);
			} ) )
		);
	}

	/* ------------------------------------------------------------------ */
	/* App shell                                                           */
	/* ------------------------------------------------------------------ */

	function currentTab() {
		const hash = String( window.location.hash || '' ).replace( '#', '' );
		return hash.split( '-' )[ 0 ] || 'overview';
	}

	function App() {
		const [ tab, setTab ] = useState( currentTab );
		const [ scenarios, setScenarios ] = useState( [] );
		const [ loading, setLoading ] = useState( true );
		const [ events, setEvents ] = useState( [] );
		const [ eventsLoading, setEventsLoading ] = useState( true );
		const [ settings, setSettings ] = useState( null );
		const [ error, setError ] = useState( '' );
		const [ compareId, setCompareId ] = useState( null );
		const [ focusId, setFocusId ] = useState( null );

		const refresh = function () {
			setLoading( true );
			return wp.apiFetch( { path: API + '/scenarios' } )
				.then( function ( rows ) { setScenarios( Array.isArray( rows ) ? rows : [] ); } )
				.catch( function ( requestError ) { setError( errorMessage( requestError, __( 'Could not load scenarios.', 'checkout-impact-regression-for-woocommerce' ) ) ); } )
				.finally( function () { setLoading( false ); } );
		};
		const refreshEvents = function () {
			setEventsLoading( true );
			return wp.apiFetch( { path: API + '/change-events?per_page=100' } )
				.then( function ( rows ) { setEvents( Array.isArray( rows ) ? rows : [] ); } )
				.catch( function ( requestError ) { setError( errorMessage( requestError, __( 'Could not load the change log.', 'checkout-impact-regression-for-woocommerce' ) ) ); } )
				.finally( function () { setEventsLoading( false ); } );
		};

		useEffect( function () {
			refresh();
			refreshEvents();
			wp.apiFetch( { path: API + '/settings' } ).then( setSettings ).catch( function () {} );
			const onHash = function () { setTab( currentTab() ); };
			window.addEventListener( 'hashchange', onHash );
			return function () { window.removeEventListener( 'hashchange', onHash ); };
		}, [] );

		const go = function ( key ) {
			if ( window.history && window.history.replaceState ) {
				window.history.replaceState( null, '', '#' + key );
			}
			setTab( key );
			window.scrollTo( { top: 0 } );
		};
		const compare = function ( id ) { setCompareId( id ); go( 'compare' ); };

		const ctx = { h: h, apiFetch: wp.apiFetch, scenarios: scenarios, store: store, settings: settings, go: go };
		let tabs = [
			{ key: 'overview', label: __( 'Overview', 'checkout-impact-regression-for-woocommerce' ), render: function () { return h( OverviewTab, { scenarios: scenarios, loading: loading, events: events, eventsLoading: eventsLoading, settings: settings, go: go } ); } },
			{ key: 'scenarios', label: __( 'Scenarios', 'checkout-impact-regression-for-woocommerce' ), count: scenarios.length, render: function () { return h( ScenariosTab, { scenarios: scenarios, loading: loading, refresh: refresh, compare: compare } ); } },
			{ key: 'compare', label: __( 'Compare', 'checkout-impact-regression-for-woocommerce' ), render: function () { return h( CompareTab, { scenarios: scenarios, loading: loading, initialScenario: compareId, go: go, onSelect: function ( id ) { if ( id ) { setFocusId( id ); } } } ); } },
			{ key: 'reports', label: __( 'Reports', 'checkout-impact-regression-for-woocommerce' ), render: function () { return h( ReportsTab, { scenarios: scenarios, settings: settings, focusId: focusId } ); } },
			{ key: 'changes', label: __( 'Change log', 'checkout-impact-regression-for-woocommerce' ), count: events.length, render: function () { return h( ChangesTab, { events: events, loading: eventsLoading, refresh: refreshEvents } ); } },
			{ key: 'settings', label: __( 'Settings', 'checkout-impact-regression-for-woocommerce' ), render: function () { return h( SettingsTab, { settings: settings, onSaved: setSettings } ); } },
			{ key: 'docs', label: __( 'Docs', 'checkout-impact-regression-for-woocommerce' ), render: function () { return h( DocsTab, { go: go } ); } },
		];
		if ( hooks ) {
			/**
			 * Lets add-ons register extra tabs: { key, label, render( ctx ) }.
			 */
			tabs = hooks.applyFilters( 'wpecir.adminTabs', tabs, ctx );
		}
		const active = tabs.find( function ( item ) { return item.key === tab; } ) || tabs[ 0 ];

		const onTabKey = function ( event, index ) {
			let next = null;
			if ( event.key === 'ArrowRight' ) { next = ( index + 1 ) % tabs.length; }
			if ( event.key === 'ArrowLeft' ) { next = ( index - 1 + tabs.length ) % tabs.length; }
			if ( event.key === 'Home' ) { next = 0; }
			if ( event.key === 'End' ) { next = tabs.length - 1; }
			if ( next !== null ) {
				event.preventDefault();
				go( tabs[ next ].key );
				const button = document.getElementById( 'wpecir-tab-' + tabs[ next ].key );
				if ( button ) { button.focus(); }
			}
		};

		return h( 'div', { className: 'wpecir-app' },
			h( DataLists ),
			h( 'header', { className: 'wpecir-topbar' },
				h( 'div', { className: 'wpecir-topbar__brand' },
					h( 'span', { className: 'wpecir-logo', 'aria-hidden': 'true' }, h( 'span', { className: 'dashicons dashicons-chart-line' } ) ),
					h( 'div', null,
						h( 'h1', null, __( 'Checkout Impact', 'checkout-impact-regression-for-woocommerce' ) ),
						h( 'p', null, __( 'See what changed at checkout after an update or settings change.', 'checkout-impact-regression-for-woocommerce' ) )
					)
				),
				h( 'div', { className: 'wpecir-topbar__meta' },
					config.proActive ? h( Badge, { tone: 'info' }, __( 'Pro', 'checkout-impact-regression-for-woocommerce' ) ) : null,
					h( 'span', { className: 'wpecir-version' }, 'v' + config.version ),
					h( 'button', { type: 'button', className: 'button', onClick: function () { go( 'docs' ); } }, __( 'Help', 'checkout-impact-regression-for-woocommerce' ) )
				)
			),
			h( 'nav', { className: 'wpecir-tabs', role: 'tablist', 'aria-label': __( 'Checkout Impact sections', 'checkout-impact-regression-for-woocommerce' ) }, tabs.map( function ( item, index ) {
				const selected = item.key === active.key;
				return h( 'button', {
					key: item.key,
					id: 'wpecir-tab-' + item.key,
					type: 'button',
					role: 'tab',
					className: 'wpecir-tab' + ( selected ? ' is-active' : '' ),
					'aria-selected': selected,
					'aria-controls': 'wpecir-panel',
					tabIndex: selected ? 0 : -1,
					onClick: function () { go( item.key ); },
					onKeyDown: function ( event ) { onTabKey( event, index ); },
				}, item.label, item.count ? h( 'span', { className: 'wpecir-tab__count' }, String( item.count ) ) : null );
			} ) ),
			h( 'div', { className: 'wpecir-body' },
				! config.woocommerceActive ? h( Notice, { tone: 'error', message: __( 'WooCommerce is not active. Activate WooCommerce to create scenarios.', 'checkout-impact-regression-for-woocommerce' ) } ) : null,
				h( Notice, { tone: 'error', message: error, onDismiss: function () { setError( '' ); } } ),
				h( 'div', { id: 'wpecir-panel', role: 'tabpanel', 'aria-labelledby': 'wpecir-tab-' + active.key, tabIndex: 0, className: 'wpecir-panel' }, active.render( ctx ) )
			)
		);
	}

	const root = document.getElementById( 'wpecir-admin-root' );
	if ( root ) {
		if ( wp.element.createRoot ) {
			wp.element.createRoot( root ).render( h( App ) );
		} else {
			wp.element.render( h( App ), root );
		}
	}
} )( window.wp, window.WPECIRAdmin );
