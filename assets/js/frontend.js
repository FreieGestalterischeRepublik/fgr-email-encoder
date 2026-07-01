/* FGR Email Encoder – Frontend */
jQuery( function ( $ ) {
    'use strict';

    // rot13-Dekodierung
    function rot13( s ) {
        return s.replace( /[a-zA-Z]/g, function ( c ) {
            return String.fromCharCode( ( c <= 'Z' ? 90 : 122 ) >= ( c = c.charCodeAt( 0 ) + 13 ) ? c : c - 26 );
        } );
    }

    // E-Mail aus data-enc-email-Attribut dekodieren
    function fetchEmail( el ) {
        var enc = el.getAttribute( 'data-enc-email' );
        if ( ! enc ) return null;
        return rot13( enc.replace( /\[at\]/g, '@' ) );
    }

    // Klick auf geschützten Link: mailto öffnen
    $( 'body' ).on( 'click', 'a[data-enc-email]', function () {
        var email = fetchEmail( this );
        if ( email ) window.location.href = 'mailto:' + email;
    } );

    // title-Attribut dekodieren ({{email}} ersetzen)
    $( 'a[data-enc-email]' ).each( function () {
        var title = this.getAttribute( 'title' );
        var email = fetchEmail( this );
        if ( title && email ) this.setAttribute( 'title', title.replace( '{{email}}', email ) );
    } );

    // Input-Felder: value-Attribut setzen
    $( 'input[data-enc-email]' ).each( function () {
        var email = fetchEmail( this );
        if ( email ) this.setAttribute( 'value', email );
    } );
} );
