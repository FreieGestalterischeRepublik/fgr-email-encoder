<?php
defined( 'ABSPATH' ) || exit;

class FGR_Email_Encoder_Settings {

    public function __construct() {
        add_action( 'admin_menu',    [ $this, 'add_menu' ] );
        add_action( 'admin_init',    [ $this, 'handle_save' ] );
        add_action( 'admin_notices', [ $this, 'show_notices' ] );
    }

    public function add_menu(): void {
        add_submenu_page(
            'fgr-plugins',
            'FGR Email Encoder',
            'Email Encoder',
            'manage_options',
            'fgr-email-encoder',
            [ $this, 'render_page' ]
        );
    }

    public function handle_save(): void {
        if ( ! isset( $_POST['fgr_ee_save'] ) ) return;
        check_admin_referer( 'fgr_ee_save', 'fgr_ee_nonce' );

        $protection = absint( $_POST['protection'] ?? 1 );
        if ( ! in_array( $protection, [ 1, 2, 3 ], true ) ) $protection = 1;

        $protect_using = sanitize_key( $_POST['protect_using'] ?? 'with_javascript' );
        if ( ! in_array( $protect_using, [ 'with_javascript', 'without_javascript', 'char_encode', 'strong_method' ], true ) ) {
            $protect_using = 'with_javascript';
        }

        update_option( 'fgr_email_encoder', [
            'protection'      => $protection,
            'protect_using'   => $protect_using,
            'protection_text' => sanitize_text_field( $_POST['protection_text'] ?? '*geschützte E-Mail*' ),
            'show_check'      => isset( $_POST['show_check'] ) ? 1 : 0,
            'at_replacement'  => sanitize_text_field( $_POST['at_replacement'] ?? '' ),
        ] );

        set_transient( 'fgr_ee_notice', 'saved', 30 );
        wp_safe_redirect( admin_url( 'admin.php?page=fgr-email-encoder' ) );
        exit;
    }

    public function show_notices(): void {
        if ( ( $_GET['page'] ?? '' ) !== 'fgr-email-encoder' ) return;

        $n = get_transient( 'fgr_ee_notice' );
        if ( ! $n ) return;
        delete_transient( 'fgr_ee_notice' );

        if ( 'saved' === $n ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Einstellungen gespeichert.</strong></p></div>';
        }
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $opt            = fgr_ee_options();
        $protection     = (int) ( $opt['protection']    ?? 1 );
        $protect_using  = $opt['protect_using']         ?? 'with_javascript';
        $protect_text   = $opt['protection_text']       ?? '*geschützte E-Mail*';
        $show_check     = ! empty( $opt['show_check'] );
        $at_replacement = $opt['at_replacement']        ?? '';
        ?>
        <div class="wrap">
            <h1>FGR Email Encoder</h1>
            <p style="color:#888;margin-top:-8px">aus der <em>Freien Gestalterischen Republik</em></p>

            <form method="post">
                <?php wp_nonce_field( 'fgr_ee_save', 'fgr_ee_nonce' ); ?>

                <table class="form-table" role="presentation">

                    <tr>
                        <th scope="row">Schutzmodus</th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="protection" value="1" <?php checked( $protection, 1 ); ?>>
                                    <strong>Vollschutz</strong> <span style="color:#888">(empfohlen)</span>
                                </label>
                                <p class="description" style="margin-left:24px;margin-bottom:12px">Scannt die gesamte Seite und schützt jede E-Mail-Adresse – auch in Widgets, Seitenleisten und Theme-Bereichen.</p>

                                <label>
                                    <input type="radio" name="protection" value="2" <?php checked( $protection, 2 ); ?>>
                                    <strong>Standardschutz</strong>
                                </label>
                                <p class="description" style="margin-left:24px;margin-bottom:12px">Schützt nur E-Mails im Beitragsinhalt, Widgets und Auszügen. Kann E-Mails in Custom-Templates übersehen.</p>

                                <label>
                                    <input type="radio" name="protection" value="3" <?php checked( $protection, 3 ); ?>>
                                    <strong>Aus</strong>
                                </label>
                                <p class="description" style="margin-left:24px">Kein automatischer Schutz. Shortcodes funktionieren weiterhin.</p>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Verschlüsselungsmethode</th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="protect_using" value="with_javascript" <?php checked( $protect_using, 'with_javascript' ); ?>>
                                    <strong>Automatisch mit JavaScript</strong> <span style="color:#888">(empfohlen)</span>
                                </label>
                                <p class="description" style="margin-left:24px;margin-bottom:12px">Stärkste Methode. E-Mail wird per JavaScript dekodiert – für den Besucher sichtbar, für Bots unleserlich.</p>

                                <label>
                                    <input type="radio" name="protect_using" value="without_javascript" <?php checked( $protect_using, 'without_javascript' ); ?>>
                                    <strong>CSS-Trick (ohne JavaScript)</strong>
                                </label>
                                <p class="description" style="margin-left:24px;margin-bottom:12px">Kehrt den Text per CSS um. Funktioniert auch ohne JavaScript, ist aber etwas schwächer.</p>

                                <label>
                                    <input type="radio" name="protect_using" value="char_encode" <?php checked( $protect_using, 'char_encode' ); ?>>
                                    <strong>HTML-Zeichenkodierung</strong>
                                </label>
                                <p class="description" style="margin-left:24px;margin-bottom:12px">Einfache Kodierung der Zeichen als HTML-Entities. Leichtgewichtig, aber leichter für Bots zu umgehen.</p>

                                <label>
                                    <input type="radio" name="protect_using" value="strong_method" <?php checked( $protect_using, 'strong_method' ); ?>>
                                    <strong>Ausblenden</strong>
                                </label>
                                <p class="description" style="margin-left:24px">Ersetzt jede E-Mail durch den Schutztext. Der Besucher sieht die Adresse nicht mehr.</p>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="protection_text">Schutztext</label></th>
                        <td>
                            <input type="text" id="protection_text" name="protection_text" class="regular-text"
                                   value="<?php echo esc_attr( $protect_text ); ?>"
                                   placeholder="*geschützte E-Mail*">
                            <p class="description">Wird angezeigt, wenn JavaScript deaktiviert ist oder bei der Methode "Ausblenden".</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="at_replacement">@-Zeichen im Anzeigetext</label></th>
                        <td>
                            <input type="text" id="at_replacement" name="at_replacement" class="regular-text"
                                   value="<?php echo esc_attr( $at_replacement ); ?>"
                                   placeholder="Leer lassen = @ normal anzeigen">
                            <p class="description">
                                Leer lassen, um das <code>@</code> wie gewohnt anzuzeigen.<br>
                                Oder einen Ersatztext eingeben, z.&nbsp;B. <code>(at)</code> oder <code>[at]</code> – dieser wird im <strong>sichtbaren Linktext</strong> gezeigt. Der eigentliche mailto-Link bleibt unverändert.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Sicherheitscheck</th>
                        <td>
                            <label>
                                <input type="checkbox" name="show_check" value="1" <?php checked( $show_check ); ?>>
                                Geschützte E-Mails für Admins mit einem <span class="dashicons dashicons-lock" style="color:green;font-size:16px;vertical-align:middle"></span> kennzeichnen
                            </label>
                            <p class="description">Nur für eingeloggte Administratoren sichtbar – für normale Besucher unsichtbar.</p>
                        </td>
                    </tr>

                </table>

                <p class="submit">
                    <button type="submit" name="fgr_ee_save" class="button button-primary">
                        Einstellungen speichern
                    </button>
                </p>
            </form>

            <hr>

            <!-- Shortcode-Übersicht -->
            <h2>Shortcodes</h2>
            <p>Du kannst E-Mails auch manuell im Beitragseditor schützen:</p>

            <table class="widefat" style="max-width:800px">
                <thead>
                    <tr>
                        <th>Shortcode</th>
                        <th>Beschreibung</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>[fgr_mailto email="info@example.com"]</code></td>
                        <td>Geschützter mailto-Link mit der E-Mail als Linktext</td>
                    </tr>
                    <tr>
                        <td><code>[fgr_mailto email="info@example.com" display="Kontakt"]</code></td>
                        <td>Geschützter mailto-Link mit eigenem Linktext</td>
                    </tr>
                    <tr>
                        <td><code>[fgr_mailto email="info@example.com" method="char_encode"]</code></td>
                        <td>Spezifische Methode für diesen Link (<code>with_javascript</code>, <code>without_javascript</code>, <code>char_encode</code>, <code>strong_method</code>)</td>
                    </tr>
                    <tr>
                        <td><code>[fgr_protect_content]Hier steht info@example.com[/fgr_protect_content]</code></td>
                        <td>Schützt alle E-Mails im eingeschlossenen Inhalt</td>
                    </tr>
                </tbody>
            </table>

            <hr>

            <!-- FAQ -->
            <h2>Häufige Fragen (FAQ)</h2>
            <div style="max-width:800px">

                <details style="margin-bottom:16px;border:1px solid #ccd0d4;border-radius:4px;padding:12px 16px">
                    <summary style="cursor:pointer;font-weight:600;font-size:14px">
                        Wie funktioniert das Plugin?
                    </summary>
                    <p style="margin-top:12px">Das Plugin scannt deine Webseite nach E-Mail-Adressen und verschlüsselt diese automatisch, bevor sie an den Browser ausgeliefert werden. Normale Besucher sehen die Adresse wie gewohnt – Spam-Bots können sie jedoch nicht auslesen, weil der Quelltext der Seite statt einer echten Adresse nur unleserlichen Code enthält.</p>
                </details>

                <details style="margin-bottom:16px;border:1px solid #ccd0d4;border-radius:4px;padding:12px 16px">
                    <summary style="cursor:pointer;font-weight:600;font-size:14px">
                        Welche Verschlüsselungsmethode sollte ich wählen?
                    </summary>
                    <p style="margin-top:12px">Die Methode <strong>Automatisch mit JavaScript</strong> ist die sicherste und für die meisten Websites die richtige Wahl. Sie verschlüsselt die E-Mail mit einer dynamischen JavaScript-Routine, die von Bots nahezu nicht dekodiert werden kann.</p>
                    <p>Falls du kein JavaScript auf deiner Website verwenden möchtest, wähle <strong>CSS-Trick</strong>. Diese Methode ist schwächer, aber immer noch deutlich besser als keine Verschlüsselung.</p>
                    <p>Die <strong>HTML-Zeichenkodierung</strong> ist sehr leichtgewichtig, bietet aber nur grundlegenden Schutz. <strong>Ausblenden</strong> ist die radikalste Option – E-Mails sind für Besucher unsichtbar.</p>
                </details>

                <details style="margin-bottom:16px;border:1px solid #ccd0d4;border-radius:4px;padding:12px 16px">
                    <summary style="cursor:pointer;font-weight:600;font-size:14px">
                        Was ist der Unterschied zwischen Vollschutz und Standardschutz?
                    </summary>
                    <p style="margin-top:12px">Der <strong>Vollschutz</strong> puffert die gesamte Seitenausgabe und scannt alles – also auch Inhalte aus Theme-Templates, Sidebars, Footern und Drittanbieter-Plugins. Das ist der sicherste Modus.</p>
                    <p>Der <strong>Standardschutz</strong> hängt sich nur in bestimmte WordPress-Filter ein (<code>the_content</code>, <code>widget_text</code>, <code>the_excerpt</code>). Er ist etwas schlanker, kann aber E-Mails in benutzerdefinierten Templates übersehen.</p>
                </details>

                <details style="margin-bottom:16px;border:1px solid #ccd0d4;border-radius:4px;padding:12px 16px">
                    <summary style="cursor:pointer;font-weight:600;font-size:14px">
                        Funktioniert das Plugin auch ohne JavaScript?
                    </summary>
                    <p style="margin-top:12px">Ja, mit dem Schutzmodus <strong>CSS-Trick</strong> oder <strong>HTML-Zeichenkodierung</strong> werden E-Mails ohne JavaScript geschützt und angezeigt.</p>
                    <p>Wenn du die Standard-Methode <strong>Automatisch mit JavaScript</strong> nutzt, wird stattdessen der <strong>Schutztext</strong> (z. B. <em>*geschützte E-Mail*</em>) innerhalb eines <code>&lt;noscript&gt;</code>-Tags angezeigt. Besucher ohne JavaScript sehen dann diesen Platzhalter.</p>
                </details>

                <details style="margin-bottom:16px;border:1px solid #ccd0d4;border-radius:4px;padding:12px 16px">
                    <summary style="cursor:pointer;font-weight:600;font-size:14px">
                        Was ist der Schutztext?
                    </summary>
                    <p style="margin-top:12px">Der Schutztext ist ein Platzhalter-Text, der in zwei Situationen erscheint:</p>
                    <ol>
                        <li>Bei der Methode <strong>Ausblenden</strong>: Jede E-Mail wird durch diesen Text ersetzt.</li>
                        <li>Bei der Methode <strong>Automatisch mit JavaScript</strong>: Innerhalb des <code>&lt;noscript&gt;</code>-Tags als Fallback für Besucher ohne JavaScript.</li>
                    </ol>
                    <p>Standard ist <em>*geschützte E-Mail*</em>. Du kannst ihn nach Belieben anpassen, z. B. in "Bitte JavaScript aktivieren" oder "E-Mail auf Anfrage".</p>
                </details>

                <details style="margin-bottom:16px;border:1px solid #ccd0d4;border-radius:4px;padding:12px 16px">
                    <summary style="cursor:pointer;font-weight:600;font-size:14px">
                        Was bedeutet das Schloss-Symbol neben E-Mails?
                    </summary>
                    <p style="margin-top:12px">Das grüne Schloss-Symbol <span class="dashicons dashicons-lock" style="color:green;font-size:14px;vertical-align:middle"></span> wird nur für eingeloggte Administratoren angezeigt. Es bestätigt, dass die E-Mail-Adresse erfolgreich geschützt wurde. Normale Besucher sehen dieses Symbol nicht.</p>
                    <p>Du kannst diese Anzeige unter <strong>Sicherheitscheck</strong> oben deaktivieren.</p>
                </details>

                <details style="margin-bottom:16px;border:1px solid #ccd0d4;border-radius:4px;padding:12px 16px">
                    <summary style="cursor:pointer;font-weight:600;font-size:14px">
                        Wie nutze ich den Shortcode <code>[fgr_mailto]</code>?
                    </summary>
                    <p style="margin-top:12px">Füge den Shortcode direkt im Beitragseditor ein:</p>
                    <pre style="background:#f0f0f0;padding:10px;border-radius:4px">[fgr_mailto email="info@example.com"]</pre>
                    <p>Das erzeugt einen geschützten mailto-Link. Optional kannst du einen eigenen Linktext angeben:</p>
                    <pre style="background:#f0f0f0;padding:10px;border-radius:4px">[fgr_mailto email="info@example.com" display="Kontakt aufnehmen"]</pre>
                    <p>Und die Methode pro Link überschreiben:</p>
                    <pre style="background:#f0f0f0;padding:10px;border-radius:4px">[fgr_mailto email="info@example.com" method="char_encode"]</pre>
                </details>

                <details style="margin-bottom:16px;border:1px solid #ccd0d4;border-radius:4px;padding:12px 16px">
                    <summary style="cursor:pointer;font-weight:600;font-size:14px">
                        Wie nutze ich den Shortcode <code>[fgr_protect_content]</code>?
                    </summary>
                    <p style="margin-top:12px">Mit diesem Shortcode kannst du beliebigen Inhalt schützen – alle darin enthaltenen E-Mail-Adressen werden automatisch verschlüsselt:</p>
                    <pre style="background:#f0f0f0;padding:10px;border-radius:4px">[fgr_protect_content]
Schreib uns: redaktion@beispiel.de oder info@beispiel.de
[/fgr_protect_content]</pre>
                    <p>Auch hier lässt sich die Methode optional angeben: <code>[fgr_protect_content method="char_encode"]</code></p>
                </details>

                <details style="margin-bottom:16px;border:1px solid #ccd0d4;border-radius:4px;padding:12px 16px">
                    <summary style="cursor:pointer;font-weight:600;font-size:14px">
                        Werden E-Mails in JavaScript-Code oder style-Tags verschlüsselt?
                    </summary>
                    <p style="margin-top:12px">Nein. Das Plugin schützt ausdrücklich keine E-Mails, die sich innerhalb von <code>&lt;script&gt;</code>- oder <code>&lt;style&gt;</code>-Tags befinden. Das würde die Funktionsfähigkeit deiner Website beeinträchtigen. Nur Inhalte, die tatsächlich im sichtbaren HTML-Text erscheinen, werden verschlüsselt.</p>
                </details>

                <details style="margin-bottom:16px;border:1px solid #ccd0d4;border-radius:4px;padding:12px 16px">
                    <summary style="cursor:pointer;font-weight:600;font-size:14px">
                        Kann ich das @-Zeichen im Anzeigetext ersetzen?
                    </summary>
                    <p style="margin-top:12px">Ja. Im Feld <strong>@-Zeichen im Anzeigetext</strong> kannst du einen Ersatztext eintragen – zum Beispiel <code>(at)</code> oder <code>[at]</code>. Das Plugin zeigt dann statt <em>info@example.com</em> den Text <em>info(at)example.com</em> an.</p>
                    <p>Der eigentliche mailto-Link bleibt dabei vollständig erhalten – ein Klick auf die Adresse öffnet weiterhin das E-Mail-Programm mit der korrekten Adresse. Nur der sichtbare Linktext wird geändert.</p>
                    <p>Lässt du das Feld leer, wird das <code>@</code> normal angezeigt.</p>
                </details>

                <details style="margin-bottom:16px;border:1px solid #ccd0d4;border-radius:4px;padding:12px 16px">
                    <summary style="cursor:pointer;font-weight:600;font-size:14px">
                        Bleiben meine Einstellungen erhalten, wenn ich das Plugin deaktiviere?
                    </summary>
                    <p style="margin-top:12px">Ja. Wenn du das Plugin nur deaktivierst, bleiben alle Einstellungen in der Datenbank erhalten. Erst wenn du das Plugin vollständig <strong>deinstallierst</strong> (Löschen in der Plugin-Liste), werden die gespeicherten Einstellungen aus der Datenbank entfernt.</p>
                </details>

            </div>

        </div>

        <style>
        details summary::-webkit-details-marker { color: #2271b1; }
        details[open] { background: #fafafa; }
        details[open] summary { margin-bottom:0; }
        </style>
        <?php
    }
}
