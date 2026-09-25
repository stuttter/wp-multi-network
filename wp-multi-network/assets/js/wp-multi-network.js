/**
 * WP Multi Network Admin Script
 */

/* global wpmnRootSiteSearch */

jQuery( document ).ready( function ( $ ) {
	$( '.if-js-closed' ).removeClass( 'if-js-closed' ).addClass( 'closed' );

	$( '.postbox' )
		.children( 'h3' )
		.click( function () {
			if ( $( this.parentNode ).hasClass( 'closed' ) ) {
				$( this.parentNode ).removeClass( 'closed' );
			} else {
				$( this.parentNode ).addClass( 'closed' );
			}
		} );

	/* Toggle the root-site panels while retaining native radio semantics. */
	$( 'input[name=root_site_option]' )
		.on( 'change', function () {
			if (
				$( 'input[name=root_site_option]:checked' ).val() === 'existing'
			) {
				$( '.root-site-new' ).hide();
				$( '.root-site-existing' ).show();
				$( '#wpmn-edit-network-details' ).hide();
			} else {
				$( '.root-site-new' ).show();
				$( '.root-site-existing' ).hide();
				$( '#wpmn-edit-network-details' ).show();
			}
		} )
		.filter( ':checked' )
		.trigger( 'change' );

	/* Search site URLs on demand; the server verifies the selected ID again. */
	const $siteSearch = $( '#existing_site_search' );
	const $siteId = $( '#existing_site_id' );
	const $siteDomain = $( '#existing_site_domain' );
	const $sitePath = $( '#existing_site_path' );
	const $siteName = $( '#existing_site_name' );
	const $siteNameStatus = $( '#existing_site_name_status' );
	const $siteSpinner = $( '#existing_site_search_spinner' );
	let siteSearchRequest;
	let siteNameRequest;
	let siteNameSequence = 0;
	let siteSearchSpinnerTimer;
	let siteSearchPendingResponse;
	let siteSearchSequence = 0;
	const setSearchBusy = function ( busy ) {
		$siteSpinner.toggleClass( 'is-active', busy );
		$siteSearch.attr( 'aria-busy', busy ? 'true' : 'false' );
	};
	const isExpiredNonce = function ( xhr ) {
		return xhr.status === 403 && String( xhr.responseText ).trim() === '-1';
	};
	const cancelSiteSearch = function () {
		siteSearchSequence++;
		window.clearTimeout( siteSearchSpinnerTimer );
		if ( siteSearchPendingResponse ) {
			siteSearchPendingResponse( [] );
		}
		if ( siteSearchRequest ) {
			siteSearchRequest.abort();
		}
		siteSearchRequest = null;
		setSearchBusy( false );
	};
	const cancelSiteNameLookup = function () {
		siteNameSequence++;
		if ( siteNameRequest ) {
			siteNameRequest.abort();
		}
		siteNameRequest = null;
		$siteName
			.val( '' )
			.attr( 'placeholder', '' )
			.attr( 'aria-busy', 'false' );
		$siteNameStatus.text( '' );
	};

	if ( $siteSearch.length ) {
		$siteSearch.autocomplete( {
			minLength: 3,
			delay: 350,
			appendTo: '#wpmn-edit-network-new-site',
			classes: { 'ui-autocomplete': 'wpmn-site-search-menu' },
			source( request, response ) {
				cancelSiteSearch();
				const errorItem = {
					label: wpmnRootSiteSearch.searchError,
					value: '',
					disabled: true,
				};
				const searchSequence = siteSearchSequence;
				const startedAt = Date.now();
				let responseSent = false;
				const sendResponse = function ( items ) {
					if ( responseSent ) {
						return;
					}
					responseSent = true;
					if ( siteSearchPendingResponse === sendResponse ) {
						siteSearchPendingResponse = null;
					}
					response( items );
				};
				const finishSearch = function ( items ) {
					if ( searchSequence !== siteSearchSequence ) {
						sendResponse( [] );
						return;
					}

					siteSearchSpinnerTimer = window.setTimeout(
						function () {
							if ( searchSequence === siteSearchSequence ) {
								setSearchBusy( false );
								sendResponse( items );
							}
						},
						Math.max( 0, 300 - ( Date.now() - startedAt ) )
					);
				};

				siteSearchPendingResponse = sendResponse;
				setSearchBusy( true );
				const searchSites = function ( retried ) {
					siteSearchRequest = $.ajax( {
						url: wpmnRootSiteSearch.ajaxUrl,
						dataType: 'json',
						data: {
							action: 'wpmn_search_root_sites',
							nonce: wpmnRootSiteSearch.nonce,
							term: request.term,
						},
					} )
						.done( function ( result ) {
							if ( ! result.success ) {
								finishSearch( [ errorItem ] );
								return;
							}

							const sites = result.data.sites;
							const items = $.map( sites, function ( site ) {
								return {
									label: site.url,
									value: site.url,
									id: site.id,
									domain: site.domain,
									path: site.path,
								};
							} );

							if ( ! items.length ) {
								items.push( {
									label: wpmnRootSiteSearch.noResults,
									value: '',
									disabled: true,
								} );
							}

							finishSearch( items );
						} )
						.fail( function ( xhr, status ) {
							if ( status === 'abort' ) {
								sendResponse( [] );
							} else if ( ! retried && isExpiredNonce( xhr ) ) {
								siteSearchRequest = $.ajax( {
									url: wpmnRootSiteSearch.ajaxUrl,
									type: 'POST',
									dataType: 'json',
									data: {
										action: 'wpmn_refresh_root_site_nonce',
									},
								} )
									.done( function ( result ) {
										if (
											searchSequence !==
											siteSearchSequence
										) {
											return;
										}
										if (
											result.success &&
											result.data.nonce
										) {
											wpmnRootSiteSearch.nonce =
												result.data.nonce;
											searchSites( true );
										} else {
											finishSearch( [ errorItem ] );
										}
									} )
									.fail(
										function ( refreshXhr, refreshStatus ) {
											if ( refreshStatus === 'abort' ) {
												sendResponse( [] );
											} else {
												finishSearch( [ errorItem ] );
											}
										}
									);
							} else {
								finishSearch( [ errorItem ] );
							}
						} );
				};
				searchSites( false );
			},
			focus( event, ui ) {
				if ( ui.item.disabled ) {
					return false;
				}
			},
			select( event, ui ) {
				if ( ui.item.disabled ) {
					return false;
				}

				$siteSearch.val( ui.item.value );
				$siteId.val( ui.item.id );
				$siteDomain.val( ui.item.domain );
				$sitePath.val( ui.item.path );
				cancelSiteNameLookup();
				const selectedId = String( ui.item.id );
				const nameSequence = siteNameSequence;
				$siteName
					.attr( 'placeholder', wpmnRootSiteSearch.loadingName )
					.attr( 'aria-busy', 'true' );
				siteNameRequest = $.ajax( {
					url: wpmnRootSiteSearch.ajaxUrl,
					dataType: 'json',
					data: {
						action: 'wpmn_get_root_site_name',
						nonce: wpmnRootSiteSearch.nonce,
						site_id: selectedId,
					},
				} )
					.done( function ( result ) {
						if (
							nameSequence !== siteNameSequence ||
							$siteId.val() !== selectedId
						) {
							return;
						}
						if ( result.success ) {
							$siteName.val( result.data.name );
						} else {
							$siteNameStatus.text(
								wpmnRootSiteSearch.nameError
							);
						}
					} )
					.fail( function ( xhr, status ) {
						if (
							status !== 'abort' &&
							nameSequence === siteNameSequence
						) {
							$siteNameStatus.text(
								wpmnRootSiteSearch.nameError
							);
						}
					} )
					.always( function () {
						if ( nameSequence === siteNameSequence ) {
							$siteName
								.attr( 'placeholder', '' )
								.attr( 'aria-busy', 'false' );
							siteNameRequest = null;
						}
					} );
				$siteSearch[ 0 ].setCustomValidity( '' );
				return false;
			},
		} );

		$siteSearch.autocomplete( 'instance' )._renderItem = function (
			ul,
			item
		) {
			const $item = $( '<li>' ).append( $( '<div>' ).text( item.label ) );

			if ( item.disabled ) {
				$item.addClass( 'ui-state-disabled' );
			}

			return $item.appendTo( ul );
		};

		$siteSearch.on( 'input', function () {
			$siteId.val( '' );
			$siteDomain.val( '' );
			$sitePath.val( '' );
			cancelSiteNameLookup();
			cancelSiteSearch();
			this.setCustomValidity( '' );
		} );
	}

	/* Require a destination only when moving selected sites out. */
	$( 'input[name="move_sites[]"]' ).on( 'change', function () {
		$( '#move-to-network' ).prop(
			'required',
			$( 'input[name="move_sites[]"]:checked' ).length > 0
		);
	} );

	$( '#edit-network-form' ).submit( function () {
		const $destination = $( '#move-to-network' );
		if (
			$destination.length &&
			$( 'input[name="move_sites[]"]:checked' ).length &&
			! $destination.val()
		) {
			$destination.prop( 'required', true );
			$destination[ 0 ].reportValidity();
			return false;
		}
		$destination.prop( 'required', false );

		if (
			$siteSearch.length &&
			$( 'input[name=root_site_option]:checked' ).val() === 'existing' &&
			! $siteId.val()
		) {
			$siteSearch[ 0 ].setCustomValidity(
				wpmnRootSiteSearch.selectResult
			);
			$siteSearch[ 0 ].reportValidity();
			return false;
		}
	} );
} );
