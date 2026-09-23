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

	/* Handle clicks to add/remove sites to/from selected list */
	$( 'input[name=assign]' ).click( function () {
		move( 'from', 'to' );
	} );

	$( 'input[name=unassign]' ).click( function () {
		move( 'to', 'from' );
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
	const $siteStatus = $( '#existing_site_search_status' );
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
				const finishSearch = function ( items, failed ) {
					if ( searchSequence !== siteSearchSequence ) {
						sendResponse( [] );
						return;
					}

					siteSearchSpinnerTimer = window.setTimeout(
						function () {
							if ( searchSequence === siteSearchSequence ) {
								setSearchBusy( false );
								if ( failed ) {
									$siteStatus.text(
										wpmnRootSiteSearch.searchError
									);
								}
								sendResponse( items );
							}
						},
						Math.max( 0, 300 - ( Date.now() - startedAt ) )
					);
				};

				siteSearchPendingResponse = sendResponse;
				$siteStatus.text( '' );
				setSearchBusy( true );
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
						const sites = result.success ? result.data.sites : [];
						const items = $.map( sites, function ( site ) {
							return {
								label: site.url,
								value: site.url,
								id: site.id,
								domain: site.domain,
								path: site.path,
							};
						} );

						if ( result.success && ! items.length ) {
							items.push( {
								label: wpmnRootSiteSearch.noResults,
								value: '',
								disabled: true,
							} );
						}

						finishSearch( items, false );
					} )
					.fail( function ( xhr, status ) {
						if ( status === 'abort' ) {
							sendResponse( [] );
						} else {
							finishSearch( [], true );
						}
					} );
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
				$siteStatus.text( '' );
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
			$siteStatus.text( '' );
		} );
	}

	/* Select all sites in "selected" box when submitting */
	$( '#edit-network-form' ).submit( function () {
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

		$( '#to' ).children( 'option:enabled' ).attr( 'selected', true );
		$( '#from' ).children( 'option:enabled' ).attr( 'selected', true );
	} );

	function move( from, to ) {
		jQuery( '#' + from )
			.children( 'option:selected' )
			.each( function () {
				jQuery( '#' + to ).append( jQuery( this ).clone() );
				jQuery( this ).remove();
			} );
	}
} );
