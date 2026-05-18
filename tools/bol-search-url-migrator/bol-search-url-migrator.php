<?php
/**
 * Plugin Name: Bol Search URL Migrator
 * Description: Migreert ThirstyAffiliates affiliate links van het oude Bol.com zoek-URL formaat (/nl/nl/s/term/) naar het nieuwe formaat (?searchtext=term). Eenmalig gebruik — deactiveer en verwijder na afronding.
 * Version:     1.1.0
 * Author:      tuinenbalkon.nl
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BSM_BACKUP_KEY', 'bol_search_url_migration_backup' );
define( 'BSM_STATE_KEY',  'bol_search_url_migration_state' );

// ── Admin menu ────────────────────────────────────────────────────────────────

add_action( 'admin_menu', function () {
    add_management_page(
        'Bol Search URL Migrator',
        'Bol URL Migratie',
        'manage_options',
        'bol-search-url-migrator',
        'bsm_render_page'
    );
} );

// ── State ─────────────────────────────────────────────────────────────────────

function bsm_get_state(): array {
    $defaults = [ 'tests_passed' => null, 'tests_ran_at' => null, 'last_action' => null, 'last_at' => null ];
    return array_merge( $defaults, (array) get_option( BSM_STATE_KEY, [] ) );
}

function bsm_save_state( array $partial ): void {
    $state = array_merge( bsm_get_state(), $partial, [ 'last_at' => current_time( 'mysql' ) ] );
    update_option( BSM_STATE_KEY, $state, false );
}

// ── Core: URL-transformatie ───────────────────────────────────────────────────

function bsm_transform_url( string $raw_url ): ?array {
    $new_url = $search_term = null;

    if ( preg_match( '#%2Fnl%2Fnl%2Fs%2F([^&\s]*?)%2F#i', $raw_url, $m ) ) {
        $encoded_term = $m[1];
        $search_term  = urldecode( str_replace( '%2B', ' ', $encoded_term ) );
        $new_url      = preg_replace( '#%2Fnl%2Fnl%2Fs%2F([^&\s]*?)%2F#i',
            '%2Fnl%2Fnl%2Fs%2F%3Fsearchtext%3D' . $encoded_term, $raw_url );
    } elseif ( preg_match( '#bol\.com/nl/nl/s/([^/?&\s]+)/#i', $raw_url, $m ) ) {
        $search_term = urldecode( str_replace( '+', ' ', $m[1] ) );
        $new_url     = preg_replace( '#(bol\.com/nl/nl/s/)([^/?&\s]+)/#i', '$1?searchtext=$2', $raw_url );
    }

    if ( $new_url === null || $new_url === $raw_url ) return null;

    return [
        'new_url'         => $new_url,
        'search_term'     => $search_term,
        'old_decoded_bol' => urldecode( preg_replace( '#.*[?&]url=([^&]+).*#', '$1', $raw_url ) ),
        'new_decoded_bol' => urldecode( preg_replace( '#.*[?&]url=([^&]+).*#', '$1', $new_url ) ),
    ];
}

function bsm_detect_links(): array {
    global $wpdb;
    $posts = $wpdb->get_results(
        "SELECT ID, post_title FROM {$wpdb->posts}
         WHERE post_type='thirstylink' AND post_status='publish' ORDER BY post_title ASC"
    );
    $found = [];
    foreach ( $posts as $post ) {
        $id  = (int) $post->ID;
        $url = get_post_meta( $id, '_ta_destination_url', true );
        if ( ! $url ) continue;
        $r = bsm_transform_url( $url );
        if ( $r ) $found[] = array_merge( [ 'id' => $id, 'name' => $post->post_title, 'old_url' => $url ], $r );
    }
    return $found;
}

function bsm_ta_available(): bool {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    return is_plugin_active( 'thirstyaffiliates/thirstyaffiliates.php' )
        || post_type_exists( 'thirstylink' );
}

function bsm_get_status(): array {
    global $wpdb;
    $ta_ok  = bsm_ta_available();
    $total  = $ta_ok ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='thirstylink' AND post_status='publish'" ) : 0;
    $found  = $ta_ok ? bsm_detect_links() : [];
    $backup = get_option( BSM_BACKUP_KEY );
    $state  = bsm_get_state();

    $ta_active          = $ta_ok;
    $backup_all_updated = false;
    if ( $backup ) {
        $backup_all_updated = true;
        foreach ( $backup['links'] as $item ) {
            if ( get_post_meta( (int) $item['id'], '_ta_destination_url', true ) === $item['old_url'] ) {
                $backup_all_updated = false;
                break;
            }
        }
    }

    return [
        'ta_active'           => $ta_active,
        'total'               => $total,
        'needs_update'        => count( $found ),
        'backup_exists'       => (bool) $backup,
        'backup_date'         => $backup ? $backup['created_at'] : null,
        'backup_count'        => $backup ? count( $backup['links'] ) : 0,
        'backup_all_updated'  => $backup_all_updated,
        'tests_passed'        => $state['tests_passed'],
        'tests_ran_at'        => $state['tests_ran_at'],
        'last_action'         => $state['last_action'],
        'last_at'             => $state['last_at'],
    ];
}

// ── Acties ────────────────────────────────────────────────────────────────────

function bsm_handle_action( string $action ): array {
    // returns [ 'html' => string, 'success' => bool, 'stop' => bool ]
    ob_start();
    $success = true;
    $stop    = false;

    if ( $action === 'run_tests'     ) [ $success, $stop ] = bsm_action_run_tests();
    elseif ( $action === 'preview_links' ) bsm_action_preview_links();
    elseif ( $action === 'dry_run'       ) bsm_action_dry_run();
    elseif ( $action === 'backup_update' ) [ $success, $stop ] = bsm_action_backup_update();
    elseif ( $action === 'verify_backup' ) bsm_action_verify_backup();
    elseif ( $action === 'cleanup'       ) [ $success, $stop ] = bsm_action_cleanup();
    elseif ( $action === 'restore'       ) [ $success, $stop ] = bsm_action_restore();

    bsm_save_state( [ 'last_action' => $action ] );

    return [ 'html' => ob_get_clean(), 'success' => $success, 'stop' => $stop ];
}

// ── run_tests ─────────────────────────────────────────────────────────────────

function bsm_action_run_tests(): array {
    $pass = $fail = 0;
    $lines = [];

    $assert = function ( $label, $ok, $got = '', $exp = '' ) use ( &$pass, &$fail, &$lines ) {
        $ok ? $pass++ : $fail++;
        $lines[] = [ 'ok' => $ok, 'label' => $label, 'info' => $ok ? '' : "verwacht: $exp\ngekregen: $got" ];
    };

    $u1 = 'https://partner.bol.com/click/click?p=2&t=url&s=10048&f=TXL&url=https%3A%2F%2Fwww.bol.com%2Fnl%2Fnl%2Fs%2Fkunstgras%2F&name=Bol&subid=kunstgras';
    $e1 = 'https://partner.bol.com/click/click?p=2&t=url&s=10048&f=TXL&url=https%3A%2F%2Fwww.bol.com%2Fnl%2Fnl%2Fs%2F%3Fsearchtext%3Dkunstgras&name=Bol&subid=kunstgras';
    $r1 = bsm_transform_url( $u1 );
    $assert( 'T1: enkelvoudige term (kunstgras)',     $r1 && $r1['new_url'] === $e1, $r1['new_url'] ?? 'null', $e1 );
    $assert( 'T1b: search_term correct',              $r1 && $r1['search_term'] === 'kunstgras', $r1['search_term'] ?? 'null', 'kunstgras' );
    $assert( 'T1c: decoded oud-deel klopt',           $r1 && $r1['old_decoded_bol'] === 'https://www.bol.com/nl/nl/s/kunstgras/', $r1['old_decoded_bol'] ?? 'null', 'https://www.bol.com/nl/nl/s/kunstgras/' );
    $assert( 'T1d: decoded nieuw-deel klopt',         $r1 && $r1['new_decoded_bol'] === 'https://www.bol.com/nl/nl/s/?searchtext=kunstgras', $r1['new_decoded_bol'] ?? 'null', 'https://www.bol.com/nl/nl/s/?searchtext=kunstgras' );

    $u2 = 'https://partner.bol.com/click/click?p=2&t=url&s=10048&f=TXL&url=https%3A%2F%2Fwww.bol.com%2Fnl%2Fnl%2Fs%2Faloe%2Bvera%2Bplant%2F&name=Bol&subid=aloevera';
    $e2 = 'https://partner.bol.com/click/click?p=2&t=url&s=10048&f=TXL&url=https%3A%2F%2Fwww.bol.com%2Fnl%2Fnl%2Fs%2F%3Fsearchtext%3Daloe%2Bvera%2Bplant&name=Bol&subid=aloevera';
    $r2 = bsm_transform_url( $u2 );
    $assert( 'T2: meerdere woorden (aloe vera plant)', $r2 && $r2['new_url'] === $e2, $r2['new_url'] ?? 'null', $e2 );
    $assert( 'T2b: search_term met spaties',            $r2 && $r2['search_term'] === 'aloe vera plant', $r2['search_term'] ?? 'null', 'aloe vera plant' );

    $r3 = bsm_transform_url( 'https://www.bol.com/nl/nl/s/loopkamille/' );
    $assert( 'T3: directe bol.com URL', $r3 && $r3['new_url'] === 'https://www.bol.com/nl/nl/s/?searchtext=loopkamille', $r3['new_url'] ?? 'null', 'https://www.bol.com/nl/nl/s/?searchtext=loopkamille' );
    $assert( 'T4: nieuwe URL niet nogmaals transformeren', bsm_transform_url( $e1 ) === null, 'niet null', 'null' );
    $assert( 'T5: productpagina niet aanraken', bsm_transform_url( 'https://partner.bol.com/click/click?p=2&t=url&s=10048&f=TXL&url=https%3A%2F%2Fwww.bol.com%2Fnl%2Fnl%2Fp%2Fproduct%2F9300000001234%2F&name=Bol' ) === null, 'niet null', 'null' );

    $tk = 'bsm_test_' . wp_generate_password( 8, false );
    $td = [ 'created_at' => current_time( 'mysql' ), 'links' => [ [ 'id' => 99999, 'name' => 'Test', 'old_url' => 'https://example.com/oud' ] ] ];
    $saved = update_option( $tk, $td, false );
    $assert( 'B1: backup opslaan in wp_options', $saved !== false, $saved ? 'true' : 'false', 'true' );
    $rd = get_option( $tk );
    $assert( 'B2: backup terugkrijgen', $rd !== false, $rd ? 'ok' : 'false', 'array' );
    $assert( 'B3: created_at intact', isset( $rd['created_at'] ) && $rd['created_at'] === $td['created_at'], $rd['created_at'] ?? '?', $td['created_at'] );
    $assert( 'B4: links-array intact', isset( $rd['links'] ) && count( $rd['links'] ) === 1, (string) count( $rd['links'] ?? [] ), '1' );
    $assert( 'B5: link-id intact', isset( $rd['links'][0]['id'] ) && $rd['links'][0]['id'] === 99999, (string) ( $rd['links'][0]['id'] ?? '?' ), '99999' );
    $assert( 'B6: old_url intact', isset( $rd['links'][0]['old_url'] ) && $rd['links'][0]['old_url'] === 'https://example.com/oud', $rd['links'][0]['old_url'] ?? '?', 'https://example.com/oud' );
    $del = delete_option( $tk );
    $assert( 'B7: backup verwijderen', $del, $del ? 'true' : 'false', 'true' );
    $assert( 'B8: backup echt weg na delete', get_option( $tk, '__gone__' ) === '__gone__', get_option( $tk, '__gone__' ), '__gone__' );

    global $wpdb;
    $sid = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type='thirstylink' AND post_status='publish' LIMIT 1" );
    if ( $sid > 0 ) {
        $orig = get_post_meta( $sid, '_ta_destination_url', true );
        $sent = $orig . '__BSMTEST__';
        update_post_meta( $sid, '_ta_destination_url', $sent );
        $assert( 'R1: schrijven naar post_meta', get_post_meta( $sid, '_ta_destination_url', true ) === $sent, get_post_meta( $sid, '_ta_destination_url', true ), $sent );
        update_post_meta( $sid, '_ta_destination_url', $orig );
        $assert( 'R2: restore naar origineel', get_post_meta( $sid, '_ta_destination_url', true ) === $orig, get_post_meta( $sid, '_ta_destination_url', true ), $orig );
    } else {
        $assert( 'R1-R2: geen thirstylinks — overgeslagen', true );
    }

    $all_ok = $fail === 0;
    bsm_save_state( [ 'tests_passed' => $all_ok, 'tests_ran_at' => current_time( 'mysql' ) ] );

    echo '<table class="bsm-table"><tbody>';
    foreach ( $lines as $l ) {
        $icon = $l['ok'] ? '<span class="bsm-pass">&#10003;</span>' : '<span class="bsm-fail">&#10007;</span>';
        echo '<tr><td style="width:28px">' . $icon . '</td><td>' . esc_html( $l['label'] ) . '</td>';
        echo '<td>' . ( $l['info'] ? '<code class="bsm-fail-code">' . esc_html( $l['info'] ) . '</code>' : '' ) . '</td></tr>';
    }
    echo '</tbody></table>';

    return [ $all_ok, ! $all_ok ];
}

// ── preview_links ─────────────────────────────────────────────────────────────

function bsm_action_preview_links(): void {
    $found = bsm_detect_links();
    if ( empty( $found ) ) { echo '<p><strong>&#10003; Geen links gevonden.</strong></p>'; return; }

    echo '<p>Klik beide kolommen per rij aan. De affiliate-tracking zit in elke link verwerkt.</p>';
    echo '<table class="bsm-table"><thead><tr><th>#</th><th>Naam</th><th>Zoekterm</th><th>Oud (klik om te testen) &#8599;</th><th>Nieuw (klik om te testen) &#8599;</th><th>OK?</th></tr></thead><tbody>';
    $i = 1;
    foreach ( $found as $r ) {
        $edit = admin_url( 'post.php?post=' . $r['id'] . '&action=edit' );
        echo '<tr>';
        echo '<td>' . $i . '</td>';
        echo '<td><a href="' . esc_url( $edit ) . '" target="_blank">' . esc_html( $r['name'] ) . ' <small>#' . $r['id'] . '</small></a></td>';
        echo '<td>' . esc_html( $r['search_term'] ) . '</td>';
        echo '<td><a href="' . esc_url( $r['old_url'] ) . '" target="_blank" class="bsm-link-old">' . esc_html( $r['old_decoded_bol'] ) . '</a></td>';
        echo '<td><a href="' . esc_url( $r['new_url'] ) . '" target="_blank" class="bsm-link-new">' . esc_html( $r['new_decoded_bol'] ) . '</a></td>';
        echo '<td class="bsm-check">&#9744;</td>';
        echo '</tr>';
        $i++;
    }
    echo '</tbody></table>';
}

// ── dry_run ───────────────────────────────────────────────────────────────────

function bsm_action_dry_run(): void {
    $found = bsm_detect_links();
    if ( empty( $found ) ) { echo '<p><strong>&#10003; Geen links gevonden.</strong></p>'; return; }

    echo '<p>Dit zijn de <strong>exacte waarden</strong> die straks in <code>_ta_destination_url</code> worden opgeslagen. Controleer of de nieuwe URL\'s er goed uitzien.</p>';
    echo '<table class="bsm-table bsm-mono"><thead><tr><th>ID</th><th>Naam</th><th>Huidige waarde (wordt vervangen)</th><th>Nieuwe waarde (wordt opgeslagen)</th></tr></thead><tbody>';
    foreach ( $found as $r ) {
        echo '<tr>';
        echo '<td>' . $r['id'] . '</td>';
        echo '<td class="bsm-sans">' . esc_html( $r['name'] ) . '</td>';
        echo '<td class="bsm-url-old">' . esc_html( $r['old_url'] ) . '</td>';
        echo '<td class="bsm-url-new">' . esc_html( $r['new_url'] ) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

// ── backup_update ─────────────────────────────────────────────────────────────

function bsm_action_backup_update(): array {
    if ( get_option( BSM_BACKUP_KEY ) ) {
        echo '<p><strong>&#9888; Er bestaat al een backup.</strong> Gebruik Verify backup of Restore.</p>';
        return [ false, true ];
    }

    $found = bsm_detect_links();
    if ( empty( $found ) ) {
        echo '<p><strong>&#10003; Geen links gevonden — niets te doen.</strong></p>';
        return [ true, false ];
    }

    $backup = [ 'created_at' => current_time( 'mysql' ),
        'links' => array_map( fn( $r ) => [ 'id' => $r['id'], 'name' => $r['name'], 'old_url' => $r['old_url'] ], $found ) ];

    if ( ! update_option( BSM_BACKUP_KEY, $backup, false ) ) {
        echo '<p><strong>&#9888; FOUT: Backup opslaan mislukt. Geen links gewijzigd.</strong></p>';
        return [ false, true ];
    }
    $verify = get_option( BSM_BACKUP_KEY );
    if ( ! $verify || count( $verify['links'] ) !== count( $backup['links'] ) ) {
        delete_option( BSM_BACKUP_KEY );
        echo '<p><strong>&#9888; FOUT: Backup-verificatie mislukt direct na opslaan. Geen links gewijzigd.</strong></p>';
        return [ false, true ];
    }

    $updated = $errors = [];
    foreach ( $found as $r ) {
        update_post_meta( $r['id'], '_ta_destination_url', $r['new_url'] );
        $check = get_post_meta( $r['id'], '_ta_destination_url', true );
        if ( $check === $r['new_url'] ) $updated[] = $r['id'];
        else                            $errors[]   = $r['id'];
    }

    $all_ok = empty( $errors );
    echo '<p>Backup aangemaakt: <strong>' . esc_html( $backup['created_at'] ) . '</strong></p>';
    echo '<p><strong>' . count( $updated ) . ' / ' . count( $found ) . '</strong> links bijgewerkt en teruggelezen.</p>';
    if ( $errors ) echo '<p class="bsm-fail">&#9888; Mislukt voor ID\'s: ' . esc_html( implode( ', ', $errors ) ) . '</p>';

    echo '<table class="bsm-table bsm-mono"><thead><tr><th>ID</th><th>Naam</th><th>Nieuwe _ta_destination_url</th><th>Status</th></tr></thead><tbody>';
    foreach ( $found as $r ) {
        $ok = ! in_array( $r['id'], $errors, true );
        echo '<tr><td>' . $r['id'] . '</td><td class="bsm-sans">' . esc_html( $r['name'] ) . '</td>';
        echo '<td class="bsm-url-new">' . esc_html( $r['new_url'] ) . '</td>';
        echo '<td>' . ( $ok ? '<span class="bsm-pass">&#10003;</span>' : '<span class="bsm-fail">&#9888;</span>' ) . '</td></tr>';
    }
    echo '</tbody></table>';
    return [ $all_ok, ! $all_ok ];
}

// ── verify_backup ─────────────────────────────────────────────────────────────

function bsm_action_verify_backup(): void {
    $backup = get_option( BSM_BACKUP_KEY );
    if ( ! $backup ) { echo '<p>Geen backup gevonden.</p>'; return; }

    echo '<p>Backup van <strong>' . esc_html( $backup['created_at'] ) . '</strong>. Vergelijking van exacte <code>_ta_destination_url</code> waarden:</p>';
    echo '<table class="bsm-table bsm-mono"><thead><tr><th>ID</th><th>Naam</th><th>In backup (oud)</th><th>In database (huidig)</th><th>Status</th></tr></thead><tbody>';
    $all_changed = true;
    foreach ( $backup['links'] as $item ) {
        $cur     = get_post_meta( (int) $item['id'], '_ta_destination_url', true );
        $changed = $cur !== $item['old_url'];
        if ( ! $changed ) $all_changed = false;
        echo '<tr><td>' . esc_html( $item['id'] ) . '</td>';
        echo '<td class="bsm-sans">' . esc_html( $item['name'] ) . '</td>';
        echo '<td class="bsm-url-old">' . esc_html( $item['old_url'] ) . '</td>';
        echo '<td class="' . ( $changed ? 'bsm-url-new' : 'bsm-url-old' ) . '">' . esc_html( $cur ) . '</td>';
        echo '<td>' . ( $changed ? '<span class="bsm-pass">&#10003; bijgewerkt</span>' : '<span style="color:orange">&#9888; nog oud</span>' ) . '</td></tr>';
    }
    echo '</tbody></table>';
    if ( ! $all_changed ) echo '<p class="bsm-fail" style="margin-top:8px">&#9888; Niet alle links zijn bijgewerkt. Voer stap 4 opnieuw uit of gebruik Restore.</p>';
}

// ── cleanup ───────────────────────────────────────────────────────────────────

function bsm_action_cleanup(): array {
    $backup = get_option( BSM_BACKUP_KEY );
    if ( ! $backup ) {
        echo '<p class="bsm-pass"><strong>&#10003; Geen backup aanwezig.</strong> Alles is opgeruimd.</p>';
        return [ true, false ];
    }
    $not_done = [];
    foreach ( $backup['links'] as $item ) {
        if ( get_post_meta( (int) $item['id'], '_ta_destination_url', true ) === $item['old_url'] )
            $not_done[] = esc_html( $item['name'] ) . ' (#' . $item['id'] . ')';
    }
    if ( $not_done ) {
        echo '<p><strong>&#9888; Cleanup geblokkeerd.</strong> Deze links hebben nog de oude URL — de migratie is niet compleet:</p><ul>';
        foreach ( $not_done as $l ) echo '<li>' . $l . '</li>';
        echo '</ul>';
        return [ false, true ];
    }
    if ( delete_option( BSM_BACKUP_KEY ) ) {
        delete_option( BSM_STATE_KEY );
        echo '<p class="bsm-pass"><strong>&#10003; Backup verwijderd. Migratie volledig afgerond.</strong></p>';
        echo '<p>Je kunt deze plugin nu <a href="' . admin_url( 'plugins.php' ) . '">deactiveren en verwijderen</a>.</p>';
        return [ true, false ];
    }
    echo '<p class="bsm-fail">&#9888; Verwijderen mislukt. Verwijder optie <code>' . BSM_BACKUP_KEY . '</code> handmatig.</p>';
    return [ false, false ];
}

// ── restore ───────────────────────────────────────────────────────────────────

function bsm_action_restore(): array {
    $backup = get_option( BSM_BACKUP_KEY );
    if ( ! $backup ) {
        echo '<p><strong>Geen backup gevonden.</strong></p>';
        return [ false, false ];
    }
    $restored = $errors = [];
    foreach ( $backup['links'] as $item ) {
        update_post_meta( (int) $item['id'], '_ta_destination_url', $item['old_url'] );
        $check = get_post_meta( (int) $item['id'], '_ta_destination_url', true );
        if ( $check === $item['old_url'] ) $restored[] = $item['id'];
        else                               $errors[]    = $item['id'];
    }
    echo '<p><strong>' . count( $restored ) . ' / ' . count( $backup['links'] ) . '</strong> links hersteld.</p>';
    if ( $errors ) {
        echo '<p class="bsm-fail">&#9888; Mislukt voor ID\'s: ' . esc_html( implode( ', ', $errors ) ) . '. Backup bewaard — voer Restore opnieuw uit.</p>';
        return [ false, false ];
    }
    delete_option( BSM_BACKUP_KEY );
    echo '<p class="bsm-pass">&#10003; Restore volledig geslaagd. Backup verwijderd.</p>';
    return [ true, false ];
}

// ── Stap-definities ───────────────────────────────────────────────────────────

function bsm_get_steps( array $status ): array {
    $ta = $status['ta_active'];
    $tp = $status['tests_passed'];
    $bu = $status['backup_exists'];
    $nu = $status['needs_update'];
    $ta_block = $ta ? null : 'ThirstyAffiliates is niet actief — activeer de plugin eerst.';

    return [
        [
            'id'    => 'run_tests',
            'label' => 'Stap 1 — Zelftest',
            'what'  => 'Voert 19 geautomatiseerde tests uit: controleert de URL-transformatielogica met bekende invoerwaarden, de backup write/read/delete cyclus in wp_options, en een echte write+restore op post_meta.',
            'check' => 'Alle vinkjes moeten groen zijn. Eén rood vinkje = STOP, ga niet verder.',
            'btn'   => 'Voer tests uit',
            'class' => 'button-secondary',
            'block' => $ta_block,
            'state' => $tp === true ? 'done' : ( $tp === false ? 'failed' : 'pending' ),
        ],
        [
            'id'    => 'preview_links',
            'label' => 'Stap 2 — Handmatige link-controle',
            'what'  => 'Toont alle te migreren links in een tabel. Per rij zijn er twee klikbare affiliate-links: de huidige (rood) en de nieuwe (blauw). Beide gaan via partner.bol.com zodat de tracking wordt meegetest.',
            'check' => 'Klik elke "Nieuw" link aan en controleer of de zoekresultatenpagina klopt. Ziet een nieuwe URL er vreemd uit? Stop dan en rapporteer.',
            'btn'   => 'Toon links',
            'class' => 'button-secondary',
            'block' => $ta_block ?? ( $tp === false ? 'Zelftest mislukt — los eerst de testfouten op.' : ( $nu === 0 ? 'Geen links gevonden om te migreren.' : null ) ),
            'state' => 'pending',
        ],
        [
            'id'    => 'dry_run',
            'label' => 'Stap 3 — Dry-run',
            'what'  => 'Toont de exacte ruwe _ta_destination_url waarden die straks in de database worden geschreven — in monospace lettertype zodat je encoding-details kunt zien. Er wordt niets gewijzigd.',
            'check' => 'Controleer of de nieuwe URL\'s er technisch correct uitzien. Let op: %3F = ?, %3D = =, %2B = +. De nieuwe URLs bevatten %3Fsearchtext%3D gevolgd door de zoekterm.',
            'btn'   => 'Dry-run',
            'class' => 'button-secondary',
            'block' => $ta_block ?? ( $tp === false ? 'Zelftest mislukt — los eerst de testfouten op.' : ( $nu === 0 ? 'Geen links gevonden.' : null ) ),
            'state' => 'pending',
        ],
        [
            'id'    => 'backup_update',
            'label' => 'Stap 4 — Backup &amp; update',
            'what'  => 'Slaat eerst een volledige backup op in wp_options (alle originele destination URLs). Verifieert direct daarna dat de backup leesbaar is. Pas dan worden de nieuwe URLs geschreven. Na elke update leest het script de waarde terug om te bevestigen dat de schrijfactie geslaagd is.',
            'check' => 'Alle regels moeten een groen vinkje tonen. Bij een fout: gebruik Restore. Schakel daarna het snippet uit en rapporteer.',
            'btn'   => 'Backup &amp; update',
            'class' => 'button-primary',
            'block' => $ta_block ?? ( $bu ? 'Er bestaat al een backup — gebruik Verify backup of Restore.' : ( $tp === false ? 'Zelftest mislukt.' : ( $nu === 0 ? 'Geen links te migreren.' : null ) ) ),
            'state' => $bu ? 'done' : 'pending',
        ],
        [
            'id'    => 'verify_backup',
            'label' => 'Stap 5 — Verify backup',
            'what'  => 'Vergelijkt de backup-waarden (opgeslagen originelen) met de huidige database-waarden, regel voor regel. Dit bevestigt dat de update correct is doorgevoerd én dat de backup intact is voor een eventuele restore.',
            'check' => 'Alle rijen moeten "✓ bijgewerkt" tonen. Als een rij "nog oud" toont, is die link niet bijgewerkt — herhaal stap 4 of gebruik Restore.',
            'btn'   => 'Verificeer',
            'class' => 'button-secondary',
            'block' => ! $bu ? 'Geen backup aanwezig — voer eerst stap 4 uit.' : null,
            'state' => $bu ? ( $status['backup_all_updated'] ? 'done' : 'pending' ) : 'pending',
        ],
        [
            'id'    => 'cleanup',
            'label' => 'Stap 6 — Cleanup',
            'what'  => 'Controleert eerst of alle links in de backup daadwerkelijk zijn bijgewerkt. Pas als dat klopt wordt de backup verwijderd uit wp_options. Bij twijfel: voer eerst Verify backup uit.',
            'check' => 'Na een succesvolle cleanup is de backup weg en is de migratie afgerond. Je kunt de plugin daarna deactiveren.',
            'btn'   => 'Verwijder backup',
            'class' => 'button-secondary',
            'block' => ! $bu ? 'Geen backup aanwezig.' : ( ! $status['backup_all_updated'] ? 'Niet alle links zijn bijgewerkt — voer eerst stap 4 uit.' : null ),
            'state' => ! $bu ? 'done' : 'pending',
        ],
        [
            'id'    => 'restore',
            'label' => '&#9888; Restore (indien nodig)',
            'what'  => 'Zet alle links terug naar de originele waarden uit de backup. Leest na elke schrijfactie de waarde terug om te bevestigen dat het herstel geslaagd is. Idempotent: veilig om meerdere keren uit te voeren als het halverwege stopt.',
            'check' => 'Alle regels moeten "✓ hersteld" tonen. Na een succesvolle restore wordt de backup automatisch verwijderd.',
            'btn'   => 'Herstel (restore)',
            'class' => 'bsm-btn-danger',
            'block' => ! $bu ? 'Geen backup aanwezig — niets om te herstellen.' : null,
            'state' => 'pending',
        ],
    ];
}

// ── Pagina render ─────────────────────────────────────────────────────────────

function bsm_render_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $result = null;
    $ran    = '';

    if ( isset( $_POST['bsm_action'] ) && check_admin_referer( 'bsm_action' ) ) {
        $ran    = sanitize_key( $_POST['bsm_action'] );
        $result = bsm_handle_action( $ran );
    }

    $status = bsm_get_status();
    $steps  = bsm_get_steps( $status );

    $overall_done   = ! $status['backup_exists'] && $status['needs_update'] === 0 && $status['tests_passed'] === true;
    $migration_done = ! $status['backup_exists'] && $status['needs_update'] === 0;
    ?>
    <div class="wrap bsm-wrap">
    <h1>Bol Search URL Migrator</h1>
    <p>Migreert ThirstyAffiliates links van het oude <code>/nl/nl/s/&lt;term&gt;/</code> formaat naar <code>?searchtext=&lt;term&gt;</code>. <strong>Deadline: 30 juni 2026.</strong> Voer de stappen in volgorde uit.</p>

    <div class="bsm-status-bar">
        <div class="bsm-status-item">
            <span class="bsm-status-label">ThirstyAffiliates</span>
            <?php if ( $status['ta_active'] ) : ?>
                <strong class="bsm-pass">&#10003; actief</strong>
            <?php else : ?>
                <strong class="bsm-fail">&#10007; niet actief</strong>
            <?php endif; ?>
        </div>
        <div class="bsm-status-item">
            <span class="bsm-status-label">ThirstyAffiliates links</span>
            <strong><?php echo $status['total']; ?></strong>
        </div>
        <div class="bsm-status-item">
            <span class="bsm-status-label">Te migreren</span>
            <strong class="<?php echo $status['needs_update'] > 0 ? 'bsm-warn-text' : 'bsm-pass'; ?>">
                <?php echo $status['needs_update']; ?>
            </strong>
        </div>
        <div class="bsm-status-item">
            <span class="bsm-status-label">Zelftest</span>
            <?php if ( $status['tests_passed'] === true ) : ?>
                <strong class="bsm-pass">&#10003; geslaagd</strong>
            <?php elseif ( $status['tests_passed'] === false ) : ?>
                <strong class="bsm-fail">&#10007; mislukt</strong>
            <?php else : ?>
                <span style="color:#646970">nog niet uitgevoerd</span>
            <?php endif; ?>
        </div>
        <div class="bsm-status-item">
            <span class="bsm-status-label">Backup</span>
            <?php if ( $status['backup_exists'] ) : ?>
                <strong class="bsm-warn-text">aanwezig (<?php echo esc_html( $status['backup_date'] ); ?>)</strong>
            <?php else : ?>
                <span style="color:#646970">geen</span>
            <?php endif; ?>
        </div>
        <?php if ( $migration_done ) : ?>
        <div class="bsm-status-item">
            <strong class="bsm-pass">&#10003; Migratie afgerond</strong>
        </div>
        <?php endif; ?>
    </div>

    <?php if ( $result ) :
        $cls = $result['stop'] ? 'bsm-output bsm-output-stop' : ( $result['success'] ? 'bsm-output bsm-output-ok' : 'bsm-output' );
        $ran_label = '';
        foreach ( $steps as $s ) { if ( $s['id'] === $ran ) { $ran_label = strip_tags( $s['label'] ); break; } }
    ?>
    <div class="<?php echo $cls; ?>">
        <div class="bsm-output-header">
            <strong><?php echo esc_html( $ran_label ); ?></strong>
            <?php if ( $result['stop'] ) : ?>
                <span class="bsm-stop-badge">&#9888; STOP — ga niet verder, zie uitleg hieronder</span>
            <?php elseif ( $result['success'] ) : ?>
                <span class="bsm-ok-badge">&#10003; Geslaagd</span>
            <?php endif; ?>
        </div>
        <div class="bsm-output-body">
            <?php echo $result['html']; ?>
        </div>
        <?php if ( $result['stop'] ) : ?>
        <div class="bsm-stop-block">
            <strong>Wat nu?</strong> Voer de volgende stap NIET uit. Controleer de foutmelding hierboven.
            <?php if ( $status['backup_exists'] ) : ?>
            Als de links al deels zijn bijgewerkt: gebruik <em>Restore</em> om alles terug te draaien.
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="bsm-steps">
    <?php foreach ( $steps as $step ) :
        $is_ran    = $ran === $step['id'];
        $blocked   = ! empty( $step['block'] );
        $state_cls = match( $step['state'] ) { 'done' => 'bsm-step-done', 'failed' => 'bsm-step-failed', default => '' };
    ?>
    <div class="bsm-step <?php echo $is_ran ? 'bsm-step-active' : ''; ?> <?php echo $state_cls; ?> <?php echo $blocked ? 'bsm-step-blocked' : ''; ?>">
        <div class="bsm-step-left">
            <div class="bsm-step-title"><?php echo $step['label']; ?></div>
            <div class="bsm-step-what"><?php echo esc_html( $step['what'] ); ?></div>
            <div class="bsm-step-check"><strong>Waar op letten:</strong> <?php echo esc_html( $step['check'] ); ?></div>
            <?php if ( $blocked ) : ?>
            <div class="bsm-step-blockreason">&#128274; <?php echo esc_html( $step['block'] ); ?></div>
            <?php endif; ?>
        </div>
        <div class="bsm-step-right">
            <?php if ( $step['state'] === 'done' && $step['id'] !== 'restore' ) : ?>
                <span class="bsm-state-badge bsm-state-done">&#10003; Klaar</span>
            <?php elseif ( $step['state'] === 'failed' ) : ?>
                <span class="bsm-state-badge bsm-state-failed">&#10007; Mislukt</span>
            <?php endif; ?>
            <form method="post">
                <?php wp_nonce_field( 'bsm_action' ); ?>
                <input type="hidden" name="bsm_action" value="<?php echo esc_attr( $step['id'] ); ?>">
                <button type="submit"
                    class="button <?php echo esc_attr( $step['class'] ); ?>"
                    <?php echo $blocked ? 'disabled title="' . esc_attr( $step['block'] ) . '"' : ''; ?>>
                    <?php echo $step['btn']; ?>
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    </div>

    <style>
    .bsm-wrap { max-width:1200px; }
    .bsm-status-bar { display:flex; gap:0; background:#fff; border:1px solid #c3c4c7; border-radius:4px; margin-bottom:20px; overflow:hidden; flex-wrap:wrap; }
    .bsm-status-item { padding:10px 20px; border-right:1px solid #c3c4c7; display:flex; flex-direction:column; gap:2px; }
    .bsm-status-item:last-child { border-right:none; }
    .bsm-status-label { font-size:11px; text-transform:uppercase; color:#646970; letter-spacing:.4px; }
    .bsm-pass { color:#00a32a; }
    .bsm-fail { color:#d63638; font-weight:bold; }
    .bsm-warn-text { color:#8a6100; }

    .bsm-output { background:#fff; border:1px solid #c3c4c7; border-radius:4px; margin-bottom:20px; overflow:hidden; }
    .bsm-output-ok   { border-color:#00a32a; }
    .bsm-output-stop { border-color:#d63638; }
    .bsm-output-header { padding:10px 16px; background:#f6f7f7; border-bottom:1px solid #c3c4c7; display:flex; align-items:center; gap:12px; }
    .bsm-output-body { padding:14px 16px; }
    .bsm-ok-badge   { background:#edfaef; color:#00a32a; padding:2px 10px; border-radius:10px; font-size:12px; font-weight:bold; }
    .bsm-stop-badge { background:#fcf0f1; color:#d63638; padding:2px 10px; border-radius:10px; font-size:12px; font-weight:bold; }
    .bsm-stop-block { background:#fcf0f1; border-top:1px solid #f0bfc0; padding:10px 16px; font-size:13px; }

    .bsm-steps { display:flex; flex-direction:column; gap:8px; }
    .bsm-step { background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:14px 16px; display:flex; gap:16px; align-items:flex-start; }
    .bsm-step-active  { border-color:#2271b1; background:#f0f6fc; }
    .bsm-step-done    { border-left:4px solid #00a32a; }
    .bsm-step-failed  { border-left:4px solid #d63638; }
    .bsm-step-blocked { opacity:.65; }
    .bsm-step-left { flex:1; }
    .bsm-step-right { display:flex; flex-direction:column; align-items:flex-end; gap:8px; min-width:150px; }
    .bsm-step-title { font-weight:600; margin-bottom:4px; }
    .bsm-step-what  { font-size:12px; color:#3c434a; margin-bottom:4px; line-height:1.5; }
    .bsm-step-check { font-size:12px; color:#646970; background:#f6f7f7; border-left:3px solid #72aee6; padding:4px 8px; margin-top:4px; }
    .bsm-step-blockreason { font-size:12px; color:#8a6100; background:#fcf9e8; padding:4px 8px; border-radius:3px; margin-top:6px; }
    .bsm-state-badge { font-size:11px; padding:2px 8px; border-radius:10px; }
    .bsm-state-done   { background:#edfaef; color:#00a32a; }
    .bsm-state-failed { background:#fcf0f1; color:#d63638; }
    .bsm-btn-danger { background:#d63638 !important; color:#fff !important; border-color:#b32d2e !important; }

    .bsm-table { border-collapse:collapse; width:100%; font-size:12px; margin-top:6px; }
    .bsm-table th, .bsm-table td { border:1px solid #c3c4c7; padding:5px 8px; text-align:left; vertical-align:top; }
    .bsm-table thead tr { background:#f6f7f7; }
    .bsm-mono td, .bsm-mono th { font-family:monospace; font-size:11px; }
    .bsm-sans { font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif !important; }
    .bsm-url-old { color:#b00; word-break:break-all; }
    .bsm-url-new { color:#0073aa; word-break:break-all; }
    .bsm-link-old { color:#b00; word-break:break-all; }
    .bsm-link-new { color:#0073aa; word-break:break-all; }
    .bsm-check { text-align:center; font-size:18px; color:#ccc; }
    .bsm-fail-code { display:block; white-space:pre-wrap; font-size:11px; }
    </style>
    <?php
}
