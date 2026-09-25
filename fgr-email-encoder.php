<?php
/**
 * Plugin Name:  FGR Email Encoder
 * Description:  Ein Plugin der Freien Gestalterischen Republik. Schützt E-Mail-Adressen auf deiner Website automatisch vor Spam-Bots. Unterstützt mehrere Verschlüsselungsmethoden, Shortcodes und ist vollständig über das WordPress-Backend konfigurierbar.
 * Version:      1.1.6
 * Author:       Freie Gestalterische Republik
 * Author URI:   https://fgr.design
 * License:      GPL-2.0-or-later
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Text Domain:  fgr-email-encoder
 */

defined( 'ABSPATH' ) || exit;

define( 'FGR_EE_VERSION', '1.1.5' );
define( 'FGR_EE_DIR',     plugin_dir_path( __FILE__ ) );
define( 'FGR_EE_URL',     plugin_dir_url( __FILE__ ) );

// ── Update-Checker: fragt die zentrale FGR-Update-API ab (nicht direkt GitHub) ──
require_once FGR_EE_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
$fgr_ee_updater = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://fgr-plugins-api.fgr.design/fgr-email-encoder.json',
    __FILE__,
    'fgr-email-encoder'
);

// Auto-Update: WordPress' täglicher Update-Cron installiert neue Versionen
// dieses Plugins automatisch, kein manueller Klick auf jeder Seite nötig.
add_filter( 'auto_update_plugin', function ( $update, $item ) {
    if ( isset( $item->slug ) && $item->slug === 'fgr-email-encoder' ) {
        return true;
    }
    return $update;
}, 10, 2 );

add_filter( 'plugin_row_meta', function ( array $links, string $plugin_file ): array {
    if ( plugin_basename( __FILE__ ) !== $plugin_file || ! current_user_can( 'update_plugins' ) ) {
        return $links;
    }
    $has_details = false;
    $has_check   = false;
    foreach ( $links as $link ) {
        if ( strpos( $link, 'open-plugin-details-modal' ) !== false ) $has_details = true;
        if ( strpos( $link, 'puc_check_for_updates' )     !== false ) $has_check   = true;
    }
    if ( ! $has_details ) {
        $url     = network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=fgr-email-encoder&TB_iframe=true&width=600&height=550' );
        $links[] = '<a href="' . esc_url( $url ) . '" class="thickbox open-plugin-details-modal">Details anzeigen</a>';
    }
    if ( ! $has_check ) {
        $url = wp_nonce_url(
            add_query_arg( [ 'puc_check_for_updates' => 1, 'puc_slug' => 'fgr-email-encoder' ], self_admin_url( 'plugins.php' ) ),
            'puc_check_for_updates'
        );
        $links[] = '<a href="' . esc_url( $url ) . '">Nach Update suchen</a>';
    }
    return $links;
}, 20, 2 );

// Warnung wenn Plugin im falschen Ordner installiert ist
if ( is_admin() && substr( untrailingslashit( FGR_EE_DIR ), -5 ) === '-main' ) {
    add_action( 'admin_notices', function () {
        $zip_url = 'https://github.com/FreieGestalterischeRepublik/fgr-email-encoder/releases/latest';
        echo '<div class="notice notice-error"><p>'
            . '<strong>FGR Email Encoder:</strong> Das Plugin ist im falschen Ordner installiert '
            . '(<code>' . esc_html( basename( FGR_EE_DIR ) ) . '</code>). '
            . 'Bitte das Plugin <strong>deaktivieren → löschen → neu installieren</strong>. '
            . 'Deine Einstellungen bleiben dabei erhalten. '
            . '<a href="' . esc_url( $zip_url ) . '" target="_blank">ZIP herunterladen →</a>'
            . '</p></div>';
    } );
}

// ── MU-Plugin-Sync ────────────────────────────────────────────────────────────
if ( ! function_exists( 'fgr_mu_sync' ) ) {
    function fgr_mu_sync(): void {
        // An den jeweils aktuellen GitHub-Release gepinnt statt an den beweglichen main-Branch:
        // ein Release ist ein bewusster, protokollierter Veröffentlichungsschritt, kein einzelner Push.
        $release = wp_remote_get(
            'https://api.github.com/repos/FreieGestalterischeRepublik/fgr-plugin-overview/releases/latest',
            [
                'timeout'    => 10,
                'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
            ]
        );
        if ( is_wp_error( $release ) || 200 !== wp_remote_retrieve_response_code( $release ) ) return;
        $release_data = json_decode( wp_remote_retrieve_body( $release ), true );
        $tag          = (string) ( $release_data['tag_name'] ?? '' );
        if ( '' === $tag || ! preg_match( '/^v?[\d.]+$/', $tag ) ) return;

        $url  = 'https://raw.githubusercontent.com/FreieGestalterischeRepublik/fgr-plugin-overview/' . rawurlencode( $tag ) . '/fgr-plugin-overview.php';
        $dest = WPMU_PLUGIN_DIR . '/fgr-plugin-overview.php';

        $response = wp_remote_get( $url, [
            'timeout'    => 15,
            'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
        ] );

        if ( is_wp_error( $response ) ) return;
        if ( 200 !== wp_remote_retrieve_response_code( $response ) ) return;

        $remote = wp_remote_retrieve_body( $response );
        if ( empty( $remote ) ) return;

        preg_match( '/\*\s+Version:\s+([\d.]+)/i', $remote, $m );
        $remote_v = $m[1] ?? '0';
        $local_v  = '0';

        if ( file_exists( $dest ) ) {
            preg_match( '/\*\s+Version:\s+([\d.]+)/i', file_get_contents( $dest ), $ml );
            $local_v = $ml[1] ?? '0';
        }

        if ( ! file_exists( $dest ) || version_compare( $remote_v, $local_v, '>' ) ) {
            if ( ! is_dir( WPMU_PLUGIN_DIR ) ) wp_mkdir_p( WPMU_PLUGIN_DIR );
            file_put_contents( $dest, $remote );
            delete_transient( 'fgr_mu_update_info' );
        }
    }
}

register_activation_hook( __FILE__, 'fgr_mu_sync' );

add_action( 'upgrader_process_complete', function ( $upgrader, array $hook_extra ): void {
    if ( ( $hook_extra['type'] ?? '' ) !== 'plugin' ) return;
    if ( ( $hook_extra['action'] ?? '' ) !== 'update' ) return;

    $fgr_plugins = [
        'fgr-mail-smtp/fgr-mail-smtp.php',
        'fgr-hide-login/fgr-hide-login.php',
        'fgr-maintenance/fgr-maintenance.php',
        'fgr-email-encoder/fgr-email-encoder.php',
        'fgr-duplicate-post/fgr-duplicate-post.php',
    ];

    $updated = array_merge(
        isset( $hook_extra['plugin'] )  ? (array) $hook_extra['plugin']  : [],
        isset( $hook_extra['plugins'] ) ? (array) $hook_extra['plugins'] : []
    );

    foreach ( $updated as $plugin_file ) {
        if ( in_array( $plugin_file, $fgr_plugins, true ) ) {
            fgr_mu_sync();
            return;
        }
    }
}, 10, 2 );

// ── Gemeinsamer FGR-Admin-Menüpunkt ──────────────────────────────────────────
if ( ! function_exists( 'fgr_register_admin_menu' ) ) {

    function fgr_register_admin_menu(): void {
        add_menu_page(
            'FGR Plugins',
            'FGR Plugins',
            'manage_options',
            'fgr-plugins',
            'fgr_render_plugins_overview',
            'dashicons-shield',
            65
        );
        add_submenu_page(
            'fgr-plugins',
            'FGR Plugins',
            'Übersicht',
            'manage_options',
            'fgr-plugins',
            'fgr_render_plugins_overview'
        );
    }
    add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', 'fgr_register_admin_menu', 5 );

    function fgr_render_plugins_overview(): void {
        $plugins = [
            [
                'slug' => 'fgr-mail-smtp',
                'file' => 'fgr-mail-smtp/fgr-mail-smtp.php',
                'name' => 'FGR Mail SMTP',
                'desc' => 'E-Mails über SMTP oder Microsoft 365 versenden',
                'page' => 'fgr-mail-smtp',
            ],
            [
                'slug' => 'fgr-hide-login',
                'file' => 'fgr-hide-login/fgr-hide-login.php',
                'name' => 'FGR Hide Login',
                'desc' => 'Login-URL individuell anpassen und schützen',
                'page' => 'fgr-hide-login',
            ],
            [
                'slug' => 'fgr-maintenance',
                'file' => 'fgr-maintenance/fgr-maintenance.php',
                'name' => 'FGR Maintenance',
                'desc' => 'Under-Construction- oder Wartungsseite anzeigen',
                'page' => 'fgr-maintenance',
            ],
            [
                'slug' => 'fgr-email-encoder',
                'file' => 'fgr-email-encoder/fgr-email-encoder.php',
                'name' => 'FGR Email Encoder',
                'desc' => 'E-Mail-Adressen vor Spam-Bots schützen',
                'page' => 'fgr-email-encoder',
            ],
        ];
        ?>
        <div class="wrap">
            <h1>FGR Plugins</h1>
            <p style="color:#888;margin-top:-8px">von der <em>Freien Gestalterischen Republik</em></p>
            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:20px">
            <?php foreach ( $plugins as $p ) :
                $active    = is_plugin_active( $p['file'] );
                $installed = file_exists( WP_PLUGIN_DIR . '/' . $p['file'] );
                if ( $active ) {
                    $badge = '<span style="color:#46b450;font-size:12px">&#9679; Aktiv</span>';
                } elseif ( $installed ) {
                    $badge = '<span style="color:#888;font-size:12px">&#9679; Inaktiv</span>';
                } else {
                    $badge = '<span style="color:#dc3545;font-size:12px">&#9679; Nicht installiert</span>';
                }
            ?>
                <div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:20px 24px;min-width:240px;max-width:320px">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px">
                        <h2 style="margin:0"><?php echo esc_html( $p['name'] ); ?></h2>
                        <?php echo $badge; ?>
                    </div>
                    <p style="color:#555;margin-bottom:16px"><?php echo esc_html( $p['desc'] ); ?></p>
                    <?php if ( $active ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $p['page'] ) ); ?>"
                           class="button button-primary">Einstellungen</a>
                    <?php elseif ( $installed ) : ?>
                        <a href="<?php echo esc_url( wp_nonce_url(
                            admin_url( 'plugins.php?action=activate&plugin=' . urlencode( $p['file'] ) ),
                            'activate-plugin_' . $p['file']
                        ) ); ?>" class="button button-primary">Aktivieren</a>
                    <?php else : ?>
                        <button type="button" class="button button-primary fgr-install-btn"
                                data-slug="<?php echo esc_attr( $p['slug'] ); ?>">
                            Installieren
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <script>
        document.querySelectorAll( '.fgr-install-btn' ).forEach( function ( btn ) {
            btn.addEventListener( 'click', function () {
                var self = this;
                self.disabled    = true;
                self.textContent = 'Installiere…';
                fetch( ajaxurl, {
                    method: 'POST',
                    body:   new URLSearchParams( {
                        action:      'fgr_install_plugin',
                        slug:        self.dataset.slug,
                        _ajax_nonce: '<?php echo wp_create_nonce( 'fgr_install_plugin' ); ?>'
                    } )
                } )
                .then( function ( r ) { return r.json(); } )
                .then( function ( data ) {
                    if ( data.success ) {
                        location.reload();
                    } else {
                        alert( 'Fehler: ' + ( data.data || 'Unbekannter Fehler' ) );
                        self.disabled    = false;
                        self.textContent = 'Installieren';
                    }
                } )
                .catch( function () {
                    alert( 'Verbindungsfehler.' );
                    self.disabled    = false;
                    self.textContent = 'Installieren';
                } );
            } );
        } );
        </script>
        <?php
    }

    add_action( 'wp_ajax_fgr_install_plugin', 'fgr_install_plugin_handler' );

    function fgr_install_plugin_handler(): void {
        check_ajax_referer( 'fgr_install_plugin' );

        if ( ! current_user_can( 'install_plugins' ) ) {
            wp_send_json_error( 'Keine Berechtigung.' );
        }

        $slug    = sanitize_key( $_POST['slug'] ?? '' );
        $allowed = [ 'fgr-mail-smtp', 'fgr-hide-login', 'fgr-maintenance', 'fgr-email-encoder' ];

        if ( ! in_array( $slug, $allowed, true ) ) {
            wp_send_json_error( 'Unbekanntes Plugin.' );
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        // An den aktuellen Release-Tag gepinnt statt an den beweglichen main-Branch
        // (siehe fgr_mu_sync() oben) — ein Release ist ein bewusster Veröffentlichungsschritt.
        $release = wp_remote_get(
            "https://api.github.com/repos/FreieGestalterischeRepublik/{$slug}/releases/latest",
            [ 'timeout' => 10, 'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url() ]
        );
        if ( is_wp_error( $release ) || 200 !== wp_remote_retrieve_response_code( $release ) ) {
            wp_send_json_error( 'Release-Information konnte nicht geladen werden.' );
        }
        $tag = (string) ( json_decode( wp_remote_retrieve_body( $release ), true )['tag_name'] ?? '' );
        if ( '' === $tag || ! preg_match( '/^v?[\d.]+$/', $tag ) ) {
            wp_send_json_error( 'Ungültige Release-Version.' );
        }

        $zip_url  = "https://github.com/FreieGestalterischeRepublik/{$slug}/archive/refs/tags/{$tag}.zip";
        $skin     = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader( $skin );
        $result   = $upgrader->install( $zip_url );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        if ( false === $result ) {
            wp_send_json_error( 'Installation fehlgeschlagen. Bitte Dateisystem-Berechtigungen prüfen.' );
        }

        // GitHub-Tag-Archive entpacken sich als "{slug}-{tag}" (z.B. "fgr-email-encoder-v1.1.3")
        // statt "{slug}" — auf den erwarteten Ordnernamen umbenennen.
        $correct_dir = WP_PLUGIN_DIR . '/' . $slug;
        if ( ! is_dir( $correct_dir ) ) {
            foreach ( glob( WP_PLUGIN_DIR . '/' . $slug . '-*', GLOB_ONLYDIR ) as $wrong_dir ) {
                rename( $wrong_dir, $correct_dir );
                break;
            }
        }

        wp_send_json_success( [ 'message' => 'Plugin erfolgreich installiert.' ] );
    }
}

// ── Admin-Einstellungsseite ───────────────────────────────────────────────────
add_action( 'plugins_loaded', function () {
    if ( is_admin() ) {
        require_once FGR_EE_DIR . 'includes/class-fgr-email-encoder-settings.php';
        new FGR_Email_Encoder_Settings();
    }
} );

// ── Einstellungen laden ───────────────────────────────────────────────────────
function fgr_ee_options(): array {
    return get_option( 'fgr_email_encoder', [
        'protection'      => 1,
        'protect_using'   => 'with_javascript',
        'protection_text' => '*geschützte E-Mail*',
        'show_check'      => 1,
        'at_replacement'  => '',
    ] );
}

// @ im Anzeigetext ersetzen – href/mailto bleibt unverändert
function fgr_ee_at_display( string $text ): string {
    $r = fgr_ee_options()['at_replacement'] ?? '';
    return $r !== '' ? str_replace( '@', $r, $text ) : $text;
}

// ── Kodierungs-Funktionen ─────────────────────────────────────────────────────

// ASCII-Methode: randomisierte Zeichentabelle + Indizes (sicherste JS-Methode)
function fgr_ee_encode_ascii( string $value, string $fallback ): string {
    $letters = '';
    for ( $i = 0; $i < strlen( $value ); $i++ ) {
        $c = substr( $value, $i, 1 );
        if ( strpos( $letters, $c ) === false ) {
            $p       = wp_rand( 0, strlen( $letters ) );
            $letters = substr( $letters, 0, $p ) . $c . substr( $letters, $p );
        }
    }
    $le = str_replace( [ '\\', '"' ], [ '\\\\', '\\"' ], $letters );

    $indices = '';
    for ( $i = 0; $i < strlen( $value ); $i++ ) {
        $indices .= chr( (int) strpos( $letters, substr( $value, $i, 1 ) ) + 48 );
    }
    $ie = str_replace( [ '\\', '"' ], [ '\\\\', '\\"' ], $indices );

    $id = 'fgr-' . wp_rand( 0, 999999 ) . '-' . wp_rand( 0, 999999 );

    return '<span id="' . $id . '"></span>'
         . '<script type="text/javascript">(function(){'
         . 'var ml="' . $le . '",mi="' . $ie . '",o="";'
         . 'for(var j=0,l=mi.length;j<l;j++){o+=ml.charAt(mi.charCodeAt(j)-48);}'
         . 'document.getElementById("' . $id . '").innerHTML=decodeURIComponent(o);'
         . '}());</script>'
         . '<noscript>' . esc_html( $fallback ) . '</noscript>';
}

// Escape-Methode: jedes Zeichen als URL-Hex-Code
function fgr_ee_encode_escape( string $value, string $fallback ): string {
    $id    = 'fgr-' . wp_rand( 0, 999999 ) . '-' . wp_rand( 0, 999999 );
    $chars = preg_split( '//u', preg_replace( '/\s+/', ' ', $value ) ) ?: [];
    $enc   = '';
    foreach ( $chars as $c ) {
        if ( $c !== '' ) $enc .= '%' . dechex( ord( $c ) );
    }
    return '<span id="' . esc_attr( $id ) . '"></span>'
         . '<script type="text/javascript">'
         . 'document.getElementById("' . $id . '").innerHTML=decodeURIComponent("' . $enc . '");'
         . '</script>'
         . '<noscript>' . esc_html( $fallback ) . '</noscript>';
}

// CSS-Methode: Text wird umgekehrt und per CSS (direction:rtl) korrekt angezeigt
function fgr_ee_encode_css( string $value ): string {
    // Erst Entities dekodieren, dann Tags entfernen — nicht umgekehrt: sonst kann
    // codiert eingeschleuster Schadcode (z.B. &lt;img onerror=…&gt;) die Tag-Entfernung
    // umgehen und würde erst beim Dekodieren zu echtem, unverschlüsselt ausgegebenem HTML.
    $plain    = wp_strip_all_tags( html_entity_decode( $value ) );
    $length   = strlen( $plain );
    $interval = (int) ceil( min( 5, max( 1, $length / 2 ) ) );
    $offset   = 0;
    $dummy    = time();
    $inner    = '';
    $rev      = strrev( $plain );

    while ( $offset < $length ) {
        $inner  .= '<span class="eeb-sd">' . antispambot( substr( $rev, $offset, $interval ) ) . '</span>';
        $inner  .= '<span class="eeb-nodis" style="display:none">' . $dummy . '</span>';
        $offset += $interval;
    }

    return '<span class="eeb eeb-rtl" style="unicode-bidi:bidi-override;direction:rtl">' . $inner . '</span>';
}

// JS-Methode: zufällig ASCII oder Escape auswählen
function fgr_ee_encode_js( string $value, string $fallback ): string {
    // ASCII braucht rawurlencode als Vorbereitung (JS macht decodeURIComponent)
    // Escape kodiert jeden Char selbst als %xx – kein Vorencoding nötig
    return ( wp_rand( 0, 1 ) === 0 )
        ? fgr_ee_encode_ascii( rawurlencode( $value ), $fallback )
        : fgr_ee_encode_escape( $value, $fallback );
}

// Admin-Check-Icon (nur für eingeloggte Admins sichtbar)
function fgr_ee_check_icon(): string {
    $opt = fgr_ee_options();
    if ( empty( $opt['show_check'] ) || ! current_user_can( 'manage_options' ) ) return '';
    return '<i class="eeb-encoded dashicons-before dashicons-lock" title="E-Mail geschützt" style="color:green"></i>';
}

// Einen mailto-Link komplett schützen
function fgr_ee_protect_mailto( string $email, string $display, string $method, string $fallback ): string {
    $enc_email = str_replace( '@', '[at]', str_rot13( $email ) );
    $display   = fgr_ee_at_display( $display ?: $email );

    switch ( $method ) {
        case 'without_javascript':
            return '<a href="mailto:' . antispambot( $email ) . '" class="fgr-email">'
                 . fgr_ee_encode_css( $display ) . '</a>' . fgr_ee_check_icon();

        case 'char_encode':
            return '<a href="mailto:' . antispambot( $email ) . '" class="fgr-email">'
                 . antispambot( $display ) . '</a>' . fgr_ee_check_icon();

        case 'strong_method':
            return esc_html( $fallback ) . fgr_ee_check_icon();

        case 'with_javascript':
        default:
            return '<a href="javascript:;" data-enc-email="' . esc_attr( $enc_email ) . '" class="fgr-email">'
                 . fgr_ee_encode_js( $display, $fallback ) . '</a>' . fgr_ee_check_icon();
    }
}

// Eine plain E-Mail-Adresse schützen (ohne mailto-Link)
function fgr_ee_protect_plain( string $email, string $method, string $fallback ): string {
    $display = fgr_ee_at_display( $email );

    switch ( $method ) {
        case 'without_javascript':
            return fgr_ee_encode_css( $display ) . fgr_ee_check_icon();
        case 'char_encode':
            return antispambot( $display ) . fgr_ee_check_icon();
        case 'strong_method':
            return esc_html( $fallback ) . fgr_ee_check_icon();
        case 'with_javascript':
        default:
            return fgr_ee_encode_js( $display, $fallback ) . fgr_ee_check_icon();
    }
}

// Seiteninhalt filtern: alle E-Mails verschlüsseln
function fgr_ee_filter_content( string $content, string $method = '', string $fallback = '' ): string {
    if ( ! $method ) {
        $opt      = fgr_ee_options();
        $method   = $opt['protect_using']   ?? 'with_javascript';
        $fallback = $opt['protection_text'] ?? '*geschützte E-Mail*';
    }

    // Script- und Style-Tags sichern (nicht anfassen)
    $stash   = [];
    $content = preg_replace_callback(
        '/<(script|style)\b[^>]*>.*?<\/\1\s*>/is',
        function ( array $m ) use ( &$stash ): string {
            $key            = "\x00FGR_EE_STASH_" . count( $stash ) . "\x00";
            $stash[ $key ]  = $m[0];
            return $key;
        },
        $content
    ) ?? $content;

    // mailto-Links finden und schützen
    $content = preg_replace_callback(
        '/<a\s([^>]*href=["\']mailto:([^"\'>\s]+)["\'][^>]*)>(.*?)<\/a\s*>/is',
        function ( array $m ) use ( $method, $fallback ): string {
            $attrs_str  = $m[1];
            $email      = sanitize_email( $m[2] );
            $inner_html = $m[3];
            $display    = wp_strip_all_tags( $inner_html );

            if ( ! $email ) return $m[0];
            // Kein sichtbarer Text (z.B. Icon-only-Link wie Elementor Icon Box)
            if ( trim( $display ) === '' ) return $m[0];

            // Originale class-Attribute lesen und fgr-email hinzufügen
            $orig_class = '';
            if ( preg_match( '/\bclass=["\']([^"\']*)["\']/', $attrs_str, $cls ) ) {
                $orig_class = trim( $cls[1] );
            }
            $merged_class = $orig_class ? $orig_class . ' fgr-email' : 'fgr-email';

            // href und class aus den Original-Attributen entfernen, Rest beibehalten
            $extra = preg_replace( '/\s*href=["\'][^"\']*["\']/', '', $attrs_str );
            $extra = preg_replace( '/\s*class=["\'][^"\']*["\']/', '', $extra );
            $extra = trim( $extra );
            $extra = $extra ? ' ' . $extra : '';

            // Enthält das innere HTML Elemente (z.B. Elementor-Button-Struktur)?
            // → Struktur beibehalten, nur href schützen.
            // Enthält es nur Text (z.B. <a href="mailto:x">x@y.z</a>)?
            // → Anzeigetext ebenfalls verschlüsseln.
            $inner_is_html = strpos( $inner_html, '<' ) !== false;

            $enc_email = str_replace( '@', '[at]', str_rot13( $email ) );
            $txt       = fgr_ee_at_display( $display ?: $email );

            switch ( $method ) {
                case 'without_javascript':
                    $body = $inner_is_html ? $inner_html : fgr_ee_encode_css( $txt );
                    return '<a href="mailto:' . antispambot( $email ) . '" class="' . esc_attr( $merged_class ) . '"' . $extra . '>'
                         . $body . '</a>' . fgr_ee_check_icon();

                case 'char_encode':
                    $body = $inner_is_html ? $inner_html : antispambot( $txt );
                    return '<a href="mailto:' . antispambot( $email ) . '" class="' . esc_attr( $merged_class ) . '"' . $extra . '>'
                         . $body . '</a>' . fgr_ee_check_icon();

                case 'strong_method':
                    return esc_html( $fallback ) . fgr_ee_check_icon();

                case 'with_javascript':
                default:
                    $body = $inner_is_html ? $inner_html : fgr_ee_encode_js( $txt, $fallback );
                    return '<a href="javascript:;" data-enc-email="' . esc_attr( $enc_email ) . '" class="' . esc_attr( $merged_class ) . '"' . $extra . '>'
                         . $body . '</a>' . fgr_ee_check_icon();
            }
        },
        $content
    ) ?? $content;

    // Nur Text zwischen HTML-Tags durchsuchen (keine Attribute anfassen)
    $content = preg_replace_callback(
        '/(?<=>)([^<]+)(?=<)/s',
        function ( array $m ) use ( $method, $fallback ): string {
            return preg_replace_callback(
                '/([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})(?!\.(?:jpg|jpeg|png|gif|svg|webp|bmp|tiff|avif))/i',
                function ( array $em ) use ( $method, $fallback ): string {
                    return fgr_ee_protect_plain( $em[1], $method, $fallback );
                },
                $m[1]
            ) ?? $m[1];
        },
        $content
    ) ?? $content;

    // Stash wiederherstellen
    foreach ( $stash as $key => $original ) {
        $content = str_replace( $key, $original, $content );
    }

    return $content;
}

// ── Schutz aktivieren ─────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function () {
    $opt        = fgr_ee_options();
    $protection = (int) ( $opt['protection'] ?? 1 );
    $method     = $opt['protect_using']   ?? 'with_javascript';
    $fallback   = $opt['protection_text'] ?? '*geschützte E-Mail*';

    if ( $protection === 3 ) return;

    if ( $protection === 1 ) {
        // Vollschutz: gesamte Seite über Output-Buffer scannen
        add_action( 'init', function () use ( $method, $fallback ) {
            if ( defined( 'WP_CLI' ) || defined( 'DOING_CRON' ) || wp_doing_ajax() || is_admin() ) return;
            // REST-API-Antworten sind i.d.R. JSON, kein HTML — der Regex-Filter würde sie sonst korrumpieren.
            if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return;
            ob_start( function ( string $content ) use ( $method, $fallback ): string {
                return fgr_ee_filter_content( $content, $method, $fallback );
            } );
        }, 1000 );
    } else {
        // Standardschutz: nur WordPress Content-Filter
        add_filter( 'the_content', function ( string $c ) use ( $method, $fallback ): string {
            return fgr_ee_filter_content( $c, $method, $fallback );
        }, 20 );
        add_filter( 'widget_text', function ( string $c ) use ( $method, $fallback ): string {
            return fgr_ee_filter_content( $c, $method, $fallback );
        }, 20 );
        add_filter( 'the_excerpt', function ( string $c ) use ( $method, $fallback ): string {
            return fgr_ee_filter_content( $c, $method, $fallback );
        }, 20 );
    }

    // Frontend-Assets einbinden
    add_action( 'wp_enqueue_scripts', function () use ( $opt ) {
        $method = $opt['protect_using'] ?? 'with_javascript';

        if ( in_array( $method, [ 'with_javascript', 'without_javascript' ], true ) ) {
            wp_enqueue_style( 'fgr-email-encoder', FGR_EE_URL . 'assets/css/frontend.css', [], FGR_EE_VERSION );
        }

        if ( $method === 'with_javascript' ) {
            wp_enqueue_script( 'fgr-email-encoder', FGR_EE_URL . 'assets/js/frontend.js', [ 'jquery' ], FGR_EE_VERSION, true );
        }

        if ( ! empty( $opt['show_check'] ) ) {
            wp_enqueue_style( 'dashicons' );
        }
    } );
} );

// ── Shortcodes ────────────────────────────────────────────────────────────────
add_action( 'init', function () {
    add_shortcode( 'fgr_mailto',          'fgr_ee_shortcode_mailto' );
    add_shortcode( 'fgr_protect_content', 'fgr_ee_shortcode_protect_content' );
} );

// [fgr_mailto email="x@y.z" display="Kontakt" method="with_javascript" noscript="..."]
function fgr_ee_shortcode_mailto( array $atts ): string {
    $opt  = fgr_ee_options();
    $atts = shortcode_atts( [
        'email'    => '',
        'display'  => '',
        'method'   => $opt['protect_using']   ?? 'with_javascript',
        'noscript' => $opt['protection_text'] ?? '*geschützte E-Mail*',
    ], $atts, 'fgr_mailto' );

    $email = sanitize_email( $atts['email'] );
    if ( ! $email ) return '';

    return fgr_ee_protect_mailto(
        $email,
        sanitize_text_field( $atts['display'] ),
        sanitize_key( $atts['method'] ),
        wp_kses_post( $atts['noscript'] )
    );
}

// [fgr_protect_content method="with_javascript"]Beliebiger Inhalt mit E-Mails[/fgr_protect_content]
function fgr_ee_shortcode_protect_content( array $atts, string $content = '' ): string {
    $opt  = fgr_ee_options();
    $atts = shortcode_atts( [
        'method'   => $opt['protect_using']   ?? 'with_javascript',
        'noscript' => $opt['protection_text'] ?? '*geschützte E-Mail*',
    ], $atts, 'fgr_protect_content' );

    return fgr_ee_filter_content(
        do_shortcode( $content ),
        sanitize_key( $atts['method'] ),
        wp_kses_post( $atts['noscript'] )
    );
}
