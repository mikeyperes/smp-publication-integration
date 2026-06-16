<?php
namespace smp_publication_integration\Admin;

use smp_publication_integration\Config;
use smp_publication_integration\Content\AuthorShortcodes;
use smp_publication_integration\Content\Schema;
use smp_publication_integration\Content\Shortcodes;
use smp_publication_integration\Support\Dependencies;
use smp_publication_integration\Support\PluginRegistry;
use smp_publication_integration\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Dashboard {
    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
    }

    public function add_settings_page(): void {
        add_options_page( Config::$settings_page_name, Config::$settings_page_name, Config::$settings_page_capability, Config::$settings_page_slug, [ $this, 'render' ] );
    }

    public function render(): void {
        if ( ! current_user_can( Config::$settings_page_capability ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'smp-publication-integration' ) );
        }
        $tabs = $this->tabs();
        $active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
        $active = isset( $tabs[ $active ] ) ? $active : 'overview';
        if ( "publication_options" === $active && Dependencies::acf_active() && function_exists( "acf_form_head" ) ) {
            acf_form_head();
        }
        ?>
        <div class="wrap smpi-dashboard">
            <h1><?php echo esc_html( Config::$settings_page_display_title ); ?></h1>
            <?php $this->styles(); ?>
            <div class="smpi-tabs-nav"><?php foreach ( $tabs as $id => $label ) : ?><a class="smpi-tab-btn<?php echo $id === $active ? " active" : ""; ?>" href="<?php echo esc_url( admin_url( "options-general.php?page=" . Config::$settings_page_slug . "&tab=" . $id ) ); ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?></div>
            <section id="smpi-tab-<?php echo esc_attr( $active ); ?>" class="smpi-tab-content active"><?php $this->tab( $active ); ?></section>
        </div>
        <?php $this->scripts(); ?>
        <?php
    }

    private function tabs(): array {
        return [
            'overview' => 'Overview',
            'publication_options' => 'Publication Options',
            'profiles' => 'Publication Profiles',
            'brand' => 'Brand',
            'shortcodes' => 'Shortcodes',
            'schema' => 'Schema',
            'reports' => 'Reports',
            'features' => 'Features',
            'optimization' => 'Optimization',
            'pages' => 'Pages',
            'verified_profiles' => 'Verified Profiles',
            'integrations' => 'Integrations',
            'quick_run' => 'Quick Run',
        ];
    }

    private function tab( string $id ): void {
        if ( 'publication_options' === $id ) { $this->publication_options(); return; }
        if ( 'profiles' === $id ) { $this->profiles(); return; }
        if ( 'brand' === $id ) { $this->brand(); return; }
        if ( 'shortcodes' === $id ) { $this->shortcodes(); return; }
        if ( 'schema' === $id ) { $this->schema(); return; }
        if ( 'reports' === $id ) { $this->reports(); return; }
        if ( 'features' === $id ) { $this->features(); return; }
        if ( 'optimization' === $id ) { $this->optimization(); return; }
        if ( 'pages' === $id ) { $this->pages(); return; }
        if ( 'verified_profiles' === $id ) { $this->verified_profiles(); return; }
        if ( 'integrations' === $id ) { $this->integrations(); return; }
        if ( 'quick_run' === $id ) { echo '<div class="smpi-panel"><h2>Quick Run</h2><p>Reserved for safe setup scripts once the exact setup sequence is supplied.</p></div>'; return; }
        $this->overview();
    }

    private function overview(): void {
        $settings = Settings::all();
        ?>
        <div class="smpi-hero">
            <p class="smpi-kicker">Publication OS</p>
            <h2>Publication profiles, schema, dependency checks, page assignments &amp; performance reporting.</h2>
            <p>Front-end code runs only where needed &mdash; archive filters, optional time formatting, and single/author social cleanup.</p>
        </div>
        <div class="smpi-panel smpi-system">
            <h2>System</h2>
            <dl class="smpi-defs">
                <div class="smpi-def"><dt>Namespace</dt><dd><code>smp_publication_integration</code></dd></div>
                <div class="smpi-def"><dt>Plugin slug</dt><dd><code>smp-publication-integration</code></dd></div>
                <div class="smpi-def"><dt>GitHub</dt><dd><code>mikeyperes/smp-publication-integration</code></dd></div>
                <div class="smpi-def"><dt>Debug URL</dt><dd><code><?php echo esc_html( rest_url( 'smpi/v1/debug' ) ); ?></code></dd></div>
                <div class="smpi-def smpi-def-block"><dt>HWS masked admin</dt><dd><?php echo $this->hws_masked_login_report_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd></div>
            </dl>
        </div>
        <?php $this->publication_mapping_panel( $settings ); ?>
        <div class="smpi-panel"><h2>Core Settings</h2><table class="widefat striped"><tbody>
            <?php $this->toggle( 'founders_enabled', 'Show founder marketing', $settings ); ?>
            <?php $this->toggle( 'shadow_press_releases', 'Shadow all press releases from home/category/tag', $settings ); ?>
            <?php $this->toggle( 'author_social_cleanup', 'Hide empty author social icons', $settings ); ?>
            <?php $this->toggle( 'public_debug_enabled', 'Public safe debug endpoint', $settings ); ?>
            <tr><th>Post time display</th><td><select class="smpi-setting" data-key="post_time_mode"><option value="native" <?php selected( $settings['post_time_mode'], 'native' ); ?>>Native WordPress output</option><option value="relative_then_date" <?php selected( $settings['post_time_mode'], 'relative_then_date' ); ?>>5 min ago, then friendly date after 24 hours</option><option value="friendly_date" <?php selected( $settings['post_time_mode'], 'friendly_date' ); ?>>Always friendly date</option></select><span class="spinner"></span><span class="smpi-save-state"></span></td></tr>
        </tbody></table></div>
        <?php
    }


    private function publication_mapping_panel( array $settings ): void {
        $user_id = isset( $settings["system_publication_user_id"] ) ? absint( $settings["system_publication_user_id"] ) : 0;

        echo "<div class=\"smpi-panel smpi-publication-map smpi-publication-author-panel\">";
        echo "<div class=\"smpi-overview-section\"><p class=\"smpi-kicker\">Current Publication</p><h2>Select Main Publication Profile</h2><p>Choose the WordPress profile that represents this publication on the front end. Search by publication name, username, or email; the selected profile saves automatically.</p></div>";
        echo "<div class=\"smpi-author-binding-layout\"><div class=\"smpi-author-search-card\">";
        echo "<label for=\"smpi-publication-user-search\"><strong>Main publication profile</strong></label><p class=\"smpi-muted\">Search by publication name, username, or email. Select the profile that represents this publication.</p>";
        echo "<div class=\"smpi-user-picker\" data-selected-user=\"" . esc_attr( (string) $user_id ) . "\">";
        echo "<input id=\"smpi-publication-user-search\" type=\"search\" class=\"regular-text smpi-user-search\" placeholder=\"Name, username, or email\" value=\"" . esc_attr( $this->selected_user_label( $user_id ) ) . "\" autocomplete=\"off\">";
        echo "<input type=\"hidden\" class=\"smpi-setting smpi-publication-user-setting\" data-key=\"system_publication_user_id\" value=\"" . esc_attr( (string) $user_id ) . "\"> ";
        echo "<button type=\"button\" class=\"button smpi-clear-user\">Clear selection</button><span class=\"spinner\"></span><span class=\"smpi-save-state\"></span><div class=\"smpi-user-results\" aria-live=\"polite\"></div></div></div>";
        echo "<div class=\"smpi-current-user-summary\">" . $this->publication_user_card_html( $user_id ) . "</div></div>";
        $this->founder_profiles_panel();
        echo "</div>";
    }

    private function founder_profiles_panel(): void {
        echo "<div class=\"smpi-founder-profile-panel\"><div class=\"smpi-founder-header\"><h3>Founder Profiles</h3><p class=\"smpi-muted\">Requires the Verified Profiles integration. Select founder records from the profile post type.</p></div>";

        $readiness = Dependencies::verified_profiles_readiness();
        if ( empty( $readiness["plugin_active"] ) || empty( $readiness["profile_cpt"] ) || empty( $readiness["profile_acf"] ) ) {
            echo "<div class=smpi-alert><strong>Verified Profiles setup required.</strong><p>Founder selection appears after the Verified Profiles plugin is active, the Profile content type is active, and Profile ACF fields are enabled.</p><ul><li>Plugin: " . ( ! empty( $readiness["plugin_active"] ) ? "GREEN CHECK" : "RED X" ) . "</li><li>Profile content type: " . ( ! empty( $readiness["profile_cpt"] ) ? "GREEN CHECK" : "RED X" ) . "</li><li>Profile ACF fields: " . ( ! empty( $readiness["profile_acf"] ) ? "GREEN CHECK" : "RED X" ) . "</li></ul><p><a class=button target=_blank rel=noopener href=" . esc_url( $readiness["settings_url"] ) . ">Open Verified Profiles settings</a></p></div></div>";
            return;
        }

        $ids = $this->founder_profile_ids();
        echo "<div class=\"smpi-profile-picker\"><label for=\"smpi-founder-profile-search\"><strong>Add founder profile</strong></label><p class=\"smpi-muted\">Search verified profile posts by name, then add the founder profile to this publication.</p>";
        echo "<input id=\"smpi-founder-profile-search\" type=\"search\" class=\"regular-text smpi-profile-search\" placeholder=\"Search founder profile records\" autocomplete=\"off\"><span class=\"spinner\"></span><span class=\"smpi-save-state\"></span><div class=\"smpi-profile-results\" aria-live=\"polite\"></div>";
        echo "<div class=\"smpi-founder-selected\">";
        if ( empty( $ids ) ) {
            echo $this->empty_founder_profiles_html();
        } else {
            foreach ( $ids as $id ) {
                echo $this->founder_profile_card_html( $id );
            }
        }
        echo "</div></div></div>";
    }

    private function founder_profile_ids(): array {
        $ids = [];
        $rows = function_exists( "get_field" ) ? get_field( "smpi_founder_profiles", "option" ) : [];
        if ( is_array( $rows ) ) {
            foreach ( $rows as $row ) {
                $value = is_array( $row ) && isset( $row["profile"] ) ? $row["profile"] : $row;
                if ( is_object( $value ) && isset( $value->ID ) ) {
                    $value = $value->ID;
                }
                $id = absint( $value );
                if ( $id && "profile" === get_post_type( $id ) ) {
                    $ids[] = $id;
                }
            }
        }

        if ( empty( $ids ) && function_exists( "get_field" ) ) {
            $legacy = get_field( "smpi_founders", "option" );
            $legacy = is_array( $legacy ) ? $legacy : [ $legacy ];
            foreach ( $legacy as $value ) {
                if ( is_object( $value ) && isset( $value->ID ) ) {
                    $value = $value->ID;
                }
                $id = absint( $value );
                if ( $id && "profile" === get_post_type( $id ) ) {
                    $ids[] = $id;
                }
            }
        }

        $fallback = get_option( "smpi_founder_profile_ids", [] );
        if ( empty( $ids ) && is_array( $fallback ) ) {
            foreach ( $fallback as $value ) {
                $id = absint( $value );
                if ( $id && "profile" === get_post_type( $id ) ) {
                    $ids[] = $id;
                }
            }
        }

        return array_values( array_unique( $ids ) );
    }

    private function founder_profile_card_html( int $profile_id ): string {
        $post = get_post( $profile_id );
        if ( ! $post || "profile" !== get_post_type( $post ) ) {
            return "";
        }
        $thumb = get_the_post_thumbnail_url( $post, "thumbnail" );
        $media = $thumb ? "<img src=\"" . esc_url( $thumb ) . "\" alt=\"\">" : "<span class=\"dashicons dashicons-id-alt\"></span>";
        return "<div class=\"smpi-founder-profile-card\" data-profile-id=\"" . esc_attr( (string) $profile_id ) . "\"><div class=\"smpi-founder-thumb\">" . $media . "</div><div class=\"smpi-founder-info\"><strong>" . esc_html( get_the_title( $post ) ) . "</strong><p class=\"smpi-muted\">Profile #" . esc_html( (string) $profile_id ) . "</p><p><a class=\"button button-secondary\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( get_edit_post_link( $profile_id, "raw" ) ) . "\">Edit Profile</a> <a class=\"button button-secondary\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( get_permalink( $profile_id ) ) . "\">View Profile</a> <button type=\"button\" class=\"button smpi-remove-founder-profile\">Remove</button></p></div></div>";
    }

    private function empty_founder_profiles_html(): string {
        return "<div class=\"smpi-empty-state smpi-empty-founder-profiles\"><strong>No founder profiles selected.</strong><p>Use the search above to add founder records from Verified Profiles.</p></div>";
    }

    private function selected_user_label( int $user_id ): string {
        $user = $user_id ? get_user_by( "id", $user_id ) : false;
        return $user ? $user->display_name : "";
    }

    private function publication_user_card_html( int $user_id ): string {
        $user = $user_id ? get_user_by( "id", $user_id ) : false;
        if ( ! $user ) {
            return "<div class=\"smpi-empty-state\"><strong>No main publication profile selected.</strong><p>Search by publication name, username, or email and choose the profile that represents this publication.</p></div>";
        }

        $title = $this->acf_user_value( $user_id, "title" );
        $bio_short = $this->acf_user_value( $user_id, "biography_short" );
        $bio = $this->acf_user_value( $user_id, "biography" );
        $mission = $this->acf_user_value( $user_id, "mission_statement" );
        $urls = $this->acf_user_value( $user_id, "urls" );
        $roles = is_array( $user->roles ) ? implode( ", ", $user->roles ) : "";
        $html = "<div class=\"smpi-profile-card\"><div class=\"smpi-profile-avatar\"><img src=\"" . esc_url( get_avatar_url( $user_id, [ "size" => 128 ] ) ) . "\" alt=\"" . esc_attr( $user->display_name ) . "\"></div><div class=\"smpi-profile-info\"><h3>" . esc_html( $user->display_name ) . "</h3>";
        if ( $title ) {
            $html .= "<p class=\"smpi-muted\">" . esc_html( $this->format_preview_value( $title ) ) . "</p>";
        }
        $html .= "<p><span class=\"dashicons dashicons-email\"></span> " . esc_html( $user->user_email ) . "</p>";
        if ( $roles ) {
            $html .= "<p class=\"smpi-muted\">Roles: " . esc_html( $roles ) . "</p>";
        }
        $html .= "<p><a class=\"button button-secondary\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( get_edit_user_link( $user_id ) ) . "\">Edit Profile</a> <a class=\"button button-secondary\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( get_author_posts_url( $user_id ) ) . "\">View Author Page</a></p></div></div>";

        $rows = [
            [ "Title", "title", $title, "[smp_publication_user field=\"title\"]" ],
            [ "Short Bio", "biography_short", $bio_short, "[author_bio_short]" ],
            [ "Biography", "biography", $bio, "[author_bio]" ],
            [ "Mission Statement", "mission_statement", $mission, "[smp_publication_field field=\"mission_statement\"]" ],
            [ "Social URLs", "urls", $urls, "[author_facebook] [author_instagram] [author_x] [author_youtube]" ],
        ];

        $html .= "<div class=\"smpi-profile-fields\">";
        foreach ( $rows as $row ) {
            $preview = $this->format_preview_value( $row[2] );
            if ( "" === $preview ) {
                continue;
            }
            $html .= "<div class=\"smpi-field-preview\"><div><strong>" . esc_html( $row[0] ) . "</strong> <code>" . esc_html( $row[3] ) . "</code></div><p>" . esc_html( wp_trim_words( wp_strip_all_tags( $preview ), 36, "..." ) ) . "</p></div>";
        }
        $html .= "</div>";
        return $html;
    }

    private function acf_user_value( int $user_id, string $field ) {
        if ( ! function_exists( "get_field" ) ) {
            return "";
        }
        return get_field( $field, "user_" . $user_id );
    }

    private function format_preview_value( $value ): string {
        if ( null === $value || false === $value || "" === $value ) {
            return "";
        }
        if ( is_array( $value ) ) {
            $flat = [];
            array_walk_recursive( $value, function ( $item ) use ( &$flat ): void {
                if ( is_scalar( $item ) && "" !== trim( (string) $item ) ) {
                    $flat[] = trim( (string) $item );
                }
            } );
            return implode( ", ", array_slice( array_unique( $flat ), 0, 8 ) );
        }
        if ( is_object( $value ) ) {
            return wp_json_encode( $value );
        }
        return trim( (string) $value );
    }


    private function publication_options(): void {
        echo "<div class=smpi-hero><p class=smpi-kicker>Theme Options</p><h2>Publication Options</h2><p>These fields describe the current publication for this website. They are rendered inside SMP settings, not on a separate ACF settings page.</p></div>";
        if ( ! Dependencies::acf_active() || ! function_exists( "acf_form" ) ) {
            echo "<div class=smpi-panel><h2>Advanced Custom Fields</h2><p><span class=smpi-warn>YELLOW !</span> ACF is recommended but not active. SMP still runs, but publication option fields are unavailable until ACF is active.</p><p><a class=button href=" . esc_url( admin_url( "options-general.php?page=smp-publication-integration&tab=integrations" ) ) . ">Open Integrations</a></p></div>";
            return;
        }
        echo "<div class=smpi-panel><h2>Current Publication Fields</h2>";
        acf_form( [ "post_id" => "option", "field_groups" => [ "group_smpi_publication_profile" ], "form" => true, "submit_value" => "Save Publication Options", "updated_message" => "Publication options saved.", "return" => admin_url( "options-general.php?page=smp-publication-integration&tab=publication_options&updated=1" ) ] );
        echo "</div>";
    }

    private function brand(): void {
        $enabled = (bool) get_option( "hws_brand_highlight_enabled", "1" );
        $background = sanitize_hex_color( (string) get_option( "hws_brand_highlight_background_color", "#facc15" ) ) ?: "#facc15";
        $text = sanitize_hex_color( (string) get_option( "hws_brand_highlight_text_color", "#111827" ) ) ?: "#111827";
        $edit = admin_url( "options-general.php?page=hws-core-tools&tab=brand-assets" );
        echo "<div class=smpi-hero><p class=smpi-kicker>Brand</p><h2>HWS Brand Assets</h2><p>SMP reports shared HWS brand values here. Editing remains owned by HWS Base Tools.</p></div>";
        echo "<div class=smpi-grid><div class=smpi-card><h3>Highlight override</h3><p>" . ( $enabled ? "GREEN CHECK Enabled" : "YELLOW ! Disabled" ) . "</p></div><div class=smpi-card><h3>Highlight background</h3><p><span class=smpi-color-swatch style=background:" . esc_attr( $background ) . "></span> <code>" . esc_html( $background ) . "</code></p></div><div class=smpi-card><h3>Highlight text</h3><p><span class=smpi-color-swatch style=background:" . esc_attr( $text ) . "></span> <code>" . esc_html( $text ) . "</code></p></div></div>";
        echo "<div class=smpi-panel><h2>Edit Source</h2><p>These settings come from HWS Base Tools Brand Assets.</p><p><a class=button target=_blank rel=noopener href=" . esc_url( $edit ) . ">Open HWS Brand Assets</a></p></div>";
    }

    private function profiles(): void {
        echo "<div class=\"smpi-panel\"><h2>Publication Field Structure</h2><p>Publication details live on the site options page for the current publication. Author and founder fields connect those options to existing WordPress author profiles.</p><table class=\"widefat striped\"><tbody>";
        foreach ( [ "smpi_publication_user" => "Selected publication author", "smpi_founder_users" => "Founder author accounts", "smpi_founder_profiles" => "Founder profile repeater from Verified Profiles", "smpi_founders" => "Legacy verified profile relationship", "smpi_headquarters" => "Headquarters", "smpi_founding_date" => "Founding Date", "smpi_mission_statement" => "Mission Statement", "smpi_contact_email" => "Public contact email", "smpi_google_news_url" => "Google News URL", "_smpi_shadow_home" => "Hide from home query", "_smpi_shadow_archives" => "Hide from category/tag query" ] as $field => $label ) {
            echo "<tr><th><code>" . esc_html( $field ) . "</code></th><td>" . esc_html( $label ) . "</td></tr>";
        }
        echo "</tbody></table></div>";
    }

    private function shortcodes(): void {
        $publication_sample = 0;
        $post_sample = $this->latest_post_id();
        echo "<div class=\"smpi-panel\"><h2>Shortcodes</h2><p>Author shortcodes resolve the current post author inside single.php loops. Pass post_id or user_id when debugging outside the loop.</p><table class=\"widefat striped\"><thead><tr><th>Group</th><th>Shortcode</th><th>Current Value</th></tr></thead><tbody>";
        foreach ( Shortcodes::shortcodes() as $tag => $callback ) {
            $code = "[" . $tag . ( $publication_sample ? " id=\"" . $publication_sample . "\"" : "" ) . "]";
            echo "<tr><td>Publication</td><td><code>" . esc_html( $code ) . "</code></td><td><code>" . esc_html( wp_trim_words( wp_strip_all_tags( do_shortcode( $code ) ), 18 ) ) . "</code></td></tr>";
        }
        foreach ( AuthorShortcodes::shortcodes() as $tag => $callback ) {
            $code = "[" . $tag . ( $post_sample ? " post_id=\"" . $post_sample . "\"" : "" );
            if ( "author_image" === $tag ) {
                $code .= " size=\"thumbnail\"";
            }
            $code .= "]";
            $value_code = "author_image" === $tag ? str_replace( "]", " output=\"url\"]", $code ) : $code;
            echo "<tr><td>Single.php author</td><td><code>" . esc_html( $code ) . "</code></td><td><code>" . esc_html( wp_trim_words( wp_strip_all_tags( do_shortcode( $value_code ) ), 18 ) ) . "</code></td></tr>";
        }
        echo "<tr><td>Publication</td><td><code>[smp_publication_page type=\"privacy\"]</code></td><td>Assigned page link.</td></tr></tbody></table></div>";
    }

    private function schema(): void {
        $schema = ( new Schema() )->generate_schema_json();
        $rank = Dependencies::plugin_active( 'seo-by-rank-math-pro/rank-math-pro.php' );
        echo '<div class="smpi-grid">';
        $this->status_card( 'Rank Math Pro', $rank, $rank ? 'Rank Math Pro active.' : 'Rank Math Pro is recommended for schema edits.' );
        $this->status_card( 'Verified Profiles', Dependencies::sfpf_active(), Dependencies::sfpf_active() ? 'Founder profile schema source active.' : 'Founder profile schema source inactive.' );
        $this->status_card( 'Homepage Schema', (bool) $this->extract_schema_types( home_url( '/' ) ), 'Homepage JSON-LD fetch checked.' );
        $this->status_card( 'Recent Posts', $this->recent_posts_schema_count() >= 8, $this->recent_posts_schema_count() . ' of 10 recent posts returned JSON-LD.' );
        echo '</div><div class="smpi-panel"><h2>Refresh Publication Schema</h2><button id="smpi-reprocess-schema" type="button" class="button button-primary">Refresh publication schema</button><div id="smpi-schema-report" class="smpi-code-panel"></div></div>';
        if ( $schema ) {
            echo '<div class="smpi-panel"><h2>Publication Schema Preview</h2><pre class="smpi-code">' . esc_html( $schema ) . '</pre></div>';
        }
    }

    private function reports(): void {
        echo '<div class="smpi-grid">';
        foreach ( $this->counts() as $type => $count ) {
            $this->card( ucwords( str_replace( '-', ' ', $type ) ), '<strong>' . esc_html( (string) $count ) . '</strong>' );
        }
        echo '</div><div class="smpi-panel"><h2>Author ACF Field Sources</h2><table class="widefat striped"><tbody>';
        foreach ( [ 'urls' => 'HWS/SFPF/HPR user ACF social URL group', 'facebook_url' => 'SFPF legacy user field', 'instagram_url' => 'SFPF legacy user field', 'twitter_url' => 'SFPF legacy user field', 'linkedin_url' => 'SFPF legacy user field', 'biography' => 'HWS/SFPF user field', 'title' => 'HWS/SFPF user field' ] as $field => $source ) {
            echo '<tr><th><code>' . esc_html( $field ) . '</code></th><td>' . esc_html( $source ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }




    private function features(): void {
        $settings = Settings::all();
        echo "<div class=\"smpi-hero\"><p class=\"smpi-kicker\">Features</p><h2>Feature controls, implementation notes, code examples, live test reports, and activity logs.</h2><p>Each feature is isolated behind settings so it can be enabled, tested, or removed without mixing concerns.</p></div>";
        $this->feature_card( "Elementor CSS cache busting", "elementor_css_cache_busting", "No custom ACF fields needed.", "Adds filemtime mv_css query args only to Elementor upload CSS files under /wp-content/uploads/elementor/css/. This prevents stale Elementor CSS after rebuilds without touching global assets.", "add_filter(\"style_loader_src\",function(\$src){if(false===strpos(\$src,\"/wp-content/uploads/elementor/css/\"))return \$src;\$path=wp_parse_url(\$src,PHP_URL_PATH);\$file=ABSPATH.ltrim(\$path,\"/\");return is_readable(\$file)?add_query_arg(\"mv_css\",filemtime(\$file),\$src):\$src;},9999,1);", $this->elementor_css_report_html(), $this->activity_log_html() );
        $author_muckrack_controls = $this->context_select_html( "muckrack_verified_contexts", [ "single_author" => "single.php header author mention", "single_footer" => "single.php footer mention", "home" => "Home page author mention", "author" => "author.php author mention" ], $settings, "Placement contexts" ) . $this->select_setting_html( "muckrack_verified_style", [ "tooltip" => [ "label" => "Tooltip icon", "description" => "Small badge beside the author name. Hover or focus explains the verification without adding sentence-length text.", "preview" => $this->author_tooltip_preview_html( $settings ) ], "text" => [ "label" => "Inline text", "description" => "Writes the full verification sentence directly into the author area.", "preview" => $this->author_inline_preview_html( $settings ) ] ], $settings, "Display style" ) . $this->icon_style_setting_html( $settings ) . $this->color_setting_html( "muckrack_icon_color", "Icon color", $settings ) . $this->author_muckrack_examples_html( $settings ) . $this->inline_toggle_setting_html( "muckrack_author_always_show", "Always show for every author" );
        $this->feature_card( "MuckRack verified authors", "muckrack_verified_enabled", "Registers user ACF fields: muckrack_verified, muckrack_url, what_best_describe_you.", "Provides [acf_author_field] and [muckrack_verified]. Auto placement can be enabled for single header author mentions, single footer mentions, homepage author mentions, and author.php. Tooltip style keeps the badge small; text style renders the verification sentence. The override can force the effective author badge for every author even when the individual ACF checkbox is empty.", "[muckrack_verified type=\"icon\" user_id=\"54\"]\n[muckrack_verified type=\"text\" user_id=\"54\"]\n[acf_author_field field=\"muckrack_url\" user_id=\"54\"]", $this->muckrack_report_html(), $this->activity_log_html(), $author_muckrack_controls );
        $publication_muckrack_controls = $this->select_setting_html( "publication_muckrack_text_mode", [ "news_outlet" => [ "label" => "News outlet verified by MuckRack editorial team", "description" => "Generic wording when you do not want the site name in the sentence." ], "publication_name" => [ "label" => get_bloginfo( "name" ) . " verified by MuckRack editorial team", "description" => "Uses the current publication name in the verification sentence." ] ], $settings, "Text option" ) . $this->select_setting_html( "publication_muckrack_style", [ "block" => [ "label" => "Editorial block", "description" => "Current article-footer style with a left accent bar.", "preview" => $this->publication_preview_sample_html( "block", $settings ) ], "compact" => [ "label" => "Compact pill", "description" => "Small inline badge for tight author or header layouts.", "preview" => $this->publication_preview_sample_html( "compact", $settings ) ], "minimalist" => [ "label" => "Minimalist text", "description" => "Plain text treatment that blends into existing article copy.", "preview" => $this->publication_preview_sample_html( "minimalist", $settings ) ] ], $settings, "Display style" ) . $this->color_setting_html( "publication_muckrack_color", "Accent color", $settings ) . $this->publication_muckrack_preview_html( $settings ) . $this->context_select_html( "publication_muckrack_placements", [ "below_author" => "Below author", "bottom_article" => "Bottom of article" ], $settings );
        $this->feature_card( "MuckRack verified publication", "publication_muckrack_verified_enabled", "Registers site option ACF fields on Publication Theme Options: smpi_publication_muckrack_verified and smpi_publication_muckrack_url.", "Displays publication-level MuckRack verification text separately from journalist verification. Use this for the site/news-outlet claim, not the author badge.", "[smp_publication_muckrack_verified]", $this->publication_muckrack_report_html(), $this->activity_log_html(), $publication_muckrack_controls );
        $this->feature_card( "Press-release inclusion controls", "press_release_include_enabled", "Uses existing press-release CPT and _smpi_pr_shadow_override meta. ACF/local fields are registered for force include or force exclude.", "Includes Hexa PR Wire press-release posts in selected blog-like loops: home, category/tag, author.php, and single.php recent article secondary queries. Force exclude is honored through the press-release visibility meta box.", "add_action(\"pre_get_posts\", function (WP_Query \$q) { /* SMP uses the same main-query guard pattern and selected contexts. */ });", $this->press_release_report_html(), $this->activity_log_html(), $this->context_select_html( "press_release_include_contexts", [ "home" => "Home page", "category_tag" => "Category and tag pages", "author" => "author.php", "single_recent" => "single.php recent article queries" ], $settings ) );
        $this->feature_card( "Estimated read time", "estimated_read_time_enabled", "No custom ACF fields needed. Reads the selected post content directly.", "Calculates reading time from post_content after stripping HTML and shortcodes. The shortcode returns a plain numeric value in minutes by default or seconds when unit=seconds is passed.", "[smp_estimated_read_time]\n[smp_estimated_read_time unit=\"seconds\"]\n[smp_estimated_read_time post_id=\"123\" unit=\"minutes\"]", $this->estimated_read_time_report_html(), $this->activity_log_html() );
        $toc_controls = $this->inline_toggle_setting_html( "table_of_contents_auto_single", "Automatically show above single.php content" );
        $this->feature_card( "Table of contents", "table_of_contents_enabled", "No ACF changes. Parses post headings from post_content.", "Adds [smp_table_of_contents] and optional automatic display above single.php content. The output uses a boxed, Elementor-width nav block.", "[smp_table_of_contents]\n[smp_table_of_contents post_id=\"123\" title=\"In this article\"]", $this->table_of_contents_report_html(), $this->activity_log_html(), $toc_controls );
        $post_acf_controls = $this->inline_toggle_setting_html( "post_summary_acf_enabled", "Register Post Summary on posts" ) . $this->inline_toggle_setting_html( "post_faqs_acf_enabled", "Register Post FAQs on posts" );
        $this->feature_card( "Post ACF add-ons", "", "Optional WYSIWYG post fields: <code>post_summary</code> and <code>post_faqs</code>. Fields are registered only when their toggles are enabled.", "Adds the supplied Post - Header ACF field group to posts and imported-news items. Use Post Summary for HTML/list summaries and Post FAQs for Q/A content.", "acf_add_local_field_group([\n  \"key\" => \"group_64a7290b61191\",\n  \"title\" => \"Post - Header\",\n  \"fields\" => [\"post_summary\", \"post_faqs\"],\n  \"location\" => [[post], [imported-news]],\n]);", $this->post_acf_addons_report_html(), $this->activity_log_html(), $post_acf_controls );
        $this->feature_card( "Author social icons", "author_social_cleanup", "No ACF changes. Reads rendered Elementor/social-icon anchors.", "Runs only on single posts and author archives. Empty social anchors are hidden when href is missing, blank, hash, or javascript. Fully empty Elementor social wrappers are collapsed.", "No shortcode needed. Toggle this feature on and inspect single.php or author.php social widgets.", $this->simple_status_html( Settings::bool( "author_social_cleanup" ), "Cleanup script active on single posts and author archives only." ), $this->activity_log_html() );
        $this->feature_card( "Publication social icons", "publication_social_cleanup", "No dedicated ACF change. Reads rendered Elementor/social-icon anchors in global publication areas.", "Runs the same empty-social cleanup safely across frontend pages for publication-level header and footer social widgets. Empty href, #, and javascript anchors are hidden without touching valid social links.", "No shortcode needed. Toggle this on and inspect header/footer publication social widgets.", $this->simple_status_html( Settings::bool( "publication_social_cleanup" ), "Publication social cleanup script active on frontend pages." ), $this->activity_log_html() );
        $this->feature_card( "Rank Math breadcrumb check", "rank_math_breadcrumb_check_enabled", "No ACF changes.", "Reports Rank Math breadcrumb status from rank-math-options-general. Mutation filters should only be added after exact breadcrumb rules are supplied.", "add_filter(\"rank_math/frontend/breadcrumb/items\", function(\$crumbs){ return \$crumbs; }, 10, 1);", $this->rank_math_breadcrumb_report_html(), $this->activity_log_html() );
        $this->feature_card( "HWS masked admin URL", "hws_masked_admin_report_enabled", "HWS Base Tools owns this feature. SMP only reports status and links to it.", "Confirms whether HWS Base Tools masked login is enabled and exposes the masked URL in the Overview and Features tabs.", "HWS option: hws_login_mask_options with slug hexa-admin.", $this->hws_masked_login_report_html(), $this->activity_log_html() );
    }


    private function feature_card( string $title, string $toggle_key, string $acf, string $description, string $code, string $test_report, string $activity_log, string $extra_controls = "" ): void {
        $log_key = $toggle_key ?: sanitize_key( $title );
        echo "<article class=smpi-feature-card><div class=smpi-feature-head><div><p class=smpi-kicker>Feature</p><h2>" . esc_html( $title ) . "</h2></div><div class=smpi-feature-toggle>" . ( $toggle_key ? $this->inline_toggle_html( $toggle_key ) : "<span class=smpi-warn>Report only</span>" ) . "</div></div>";
        if ( "" !== $extra_controls ) {
            echo "<div class=smpi-feature-controls>" . $extra_controls . "</div>";
        }
        echo "<div class=smpi-feature-grid><section><h3>Custom ACF adjustments</h3><p>" . wp_kses_post( $acf ) . "</p></section><section><h3>Description / use instructions</h3><p>" . esc_html( $description ) . "</p></section><section><h3>Code example</h3><pre class=smpi-code>" . esc_html( $code ) . "</pre></section><section class=smpi-feature-report><h3>Test report, active and proof working</h3>" . wp_kses_post( $test_report ) . "</section><section class=smpi-feature-activity><h3>Activity log</h3>" . wp_kses_post( $this->activity_log_html( $log_key ) ) . "</section></div></article>";
    }

    private function inline_toggle_html( string $key ): string {
        $enabled = Settings::bool( $key );
        return "<label class=smpi-switch><input class=smpi-setting type=checkbox data-key=" . esc_attr( $key ) . " value=1 " . checked( $enabled, true, false ) . "><span></span><strong>" . ( $enabled ? "Enabled" : "Disabled" ) . "</strong></label><span class=spinner></span><span class=smpi-save-state></span>";
    }

    private function inline_toggle_setting_html( string $key, string $label ): string {
        $enabled = Settings::bool( $key );
        return "<div class=smpi-control-row><label class=smpi-switch><input class=smpi-setting type=checkbox data-key=" . esc_attr( $key ) . " value=1 " . checked( $enabled, true, false ) . "><span></span><strong>" . esc_html( $label ) . "</strong></label><span class=spinner></span><span class=smpi-save-state></span></div>";
    }

    private function context_select_html( string $key, array $options, array $settings, string $label = "Placement contexts" ): string {
        $selected = isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ? $settings[ $key ] : [];
        $html = "<div class=\"smpi-control-group smpi-context-control\"><h3>" . esc_html( $label ) . "</h3><div class=smpi-choice-list>";
        foreach ( $options as $value => $option_label ) {
            $choice = $this->choice_data( $option_label, $this->context_description( $key, (string) $value ) );
            $is_selected = in_array( $value, $selected, true );
            $html .= "<label class=\"smpi-choice-card smpi-context-choice" . ( $is_selected ? " is-selected" : "" ) . "\"><input class=smpi-setting-array type=checkbox data-key=" . esc_attr( $key ) . " value=" . esc_attr( $value ) . " " . checked( $is_selected, true, false ) . "><span class=smpi-choice-body><strong>" . esc_html( $choice["label"] ) . "</strong><small>" . esc_html( $choice["description"] ) . "</small></span>" . ( $is_selected ? "<span class=smpi-selected-pill>Selected</span>" : "" ) . "</label>";
        }
        return $html . "</div><span class=spinner></span><span class=smpi-save-state></span></div>";
    }

    private function select_setting_html( string $key, array $options, array $settings, string $label = "Style" ): string {
        $current = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : "";
        $html = "<div class=smpi-control-group><h3>" . esc_html( $label ) . "</h3><div class=smpi-choice-grid>";
        foreach ( $options as $value => $option_label ) {
            $choice = $this->choice_data( $option_label );
            $is_selected = $current === (string) $value;
            $html .= "<label class=\"smpi-choice-card" . ( $is_selected ? " is-selected" : "" ) . "\"><input class=smpi-setting type=radio name=" . esc_attr( "smpi_" . $key ) . " data-key=" . esc_attr( $key ) . " value=" . esc_attr( $value ) . " " . checked( $is_selected, true, false ) . "><span class=smpi-choice-body><strong>" . esc_html( $choice["label"] ) . "</strong>" . ( "" !== $choice["description"] ? "<small>" . esc_html( $choice["description"] ) . "</small>" : "" ) . ( "" !== $choice["preview"] ? "<span class=smpi-choice-preview>" . wp_kses_post( $choice["preview"] ) . "</span>" : "" ) . "</span>" . ( $is_selected ? "<span class=smpi-selected-pill>Selected</span>" : "" ) . "</label>";
        }
        return $html . "</div><span class=spinner></span><span class=smpi-save-state></span></div>";
    }

    private function color_setting_html( string $key, string $label, array $settings ): string {
        $default = "#2d5277";
        $color = sanitize_hex_color( (string) ( $settings[ $key ] ?? $default ) ) ?: $default;
        return "<div class=smpi-control-group><h3>" . esc_html( $label ) . "</h3><label class=smpi-color-control><input class=smpi-setting type=color data-key=" . esc_attr( $key ) . " value=" . esc_attr( $color ) . "><code>" . esc_html( $color ) . "</code><span class=smpi-color-swatch style=\"background:" . esc_attr( $color ) . "\"></span></label><span class=spinner></span><span class=smpi-save-state></span></div>";
    }

    private function icon_style_setting_html( array $settings ): string {
        $color = sanitize_hex_color( (string) ( $settings["muckrack_icon_color"] ?? "#2d5277" ) ) ?: "#2d5277";
        return $this->select_setting_html( "muckrack_icon_style", [ "circle_check" => [ "label" => "Circle with check", "description" => "A contained verification icon that reads clearly beside an author name.", "preview" => "<span class=\"smpi-muckrack-preview-circle\" style=\"color:" . esc_attr( $color ) . "\">&#10003;</span>" ], "check" => [ "label" => "Just check", "description" => "The lightest option: a plain checkmark with no circle.", "preview" => "<span class=\"smpi-muckrack-preview-check\" style=\"color:" . esc_attr( $color ) . "\">&#10003;</span>" ] ], $settings, "Icon style" );
    }

    private function choice_data( $choice, string $fallback_description = "" ): array {
        if ( is_array( $choice ) ) {
            return [
                "label" => (string) ( $choice["label"] ?? "" ),
                "description" => (string) ( $choice["description"] ?? $fallback_description ),
                "preview" => (string) ( $choice["preview"] ?? "" ),
            ];
        }
        return [ "label" => (string) $choice, "description" => $fallback_description, "preview" => "" ];
    }

    private function context_description( string $key, string $value ): string {
        $descriptions = [
            "muckrack_verified_contexts" => [
                "single_author" => "Adds the author verification badge beside the byline on single article templates.",
                "single_footer" => "Adds the full author verification sentence below the article content.",
                "home" => "Applies the author badge when author names render in home page article lists.",
                "author" => "Applies the author badge on author archive/profile pages.",
            ],
            "publication_muckrack_placements" => [
                "below_author" => "Places the publication-level verification near the byline, under the author area.",
                "bottom_article" => "Places the publication-level verification after article content and before the tag/footer area.",
            ],
            "press_release_include_contexts" => [
                "home" => "Allows press releases to appear in the main home page feed.",
                "category_tag" => "Allows press releases in category and tag archive queries.",
                "author" => "Allows press releases to appear on author archive pages.",
                "single_recent" => "Allows press releases in recent-article modules shown on single posts.",
            ],
        ];
        return $descriptions[ $key ][ $value ] ?? "Controls where this feature is allowed to run on the front end.";
    }

    private function author_tooltip_preview_html( array $settings ): string {
        $color = sanitize_hex_color( (string) ( $settings["muckrack_icon_color"] ?? "#2d5277" ) ) ?: "#2d5277";
        $icon = "check" === (string) ( $settings["muckrack_icon_style"] ?? "circle_check" ) ? "smpi-muckrack-preview-check" : "smpi-muckrack-preview-circle";
        return "<span class=smpi-tooltip-demo><strong>Jane Reporter</strong> <span class=\"" . esc_attr( $icon ) . "\" style=\"color:" . esc_attr( $color ) . "\">&#10003;</span><span class=smpi-tooltip-bubble>Verified by MuckRack editorial team</span></span>";
    }

    private function author_inline_preview_html( array $settings ): string {
        $color = sanitize_hex_color( (string) ( $settings["muckrack_icon_color"] ?? "#2d5277" ) ) ?: "#2d5277";
        return "<span class=smpi-author-inline-demo>Journalist verified by <strong style=\"color:" . esc_attr( $color ) . "\">MuckRack</strong> editorial team <a href=#>(learn more)</a></span>";
    }

    private function author_muckrack_examples_html( array $settings ): string {
        return "<div class=\"smpi-control-group smpi-preview-group\"><h3>Visual examples</h3><div class=smpi-preview-stack><div class=smpi-preview-demo><strong>Tooltip / hover badge</strong><p>" . $this->author_tooltip_preview_html( $settings ) . "</p></div><div class=smpi-preview-demo><strong>Inline text</strong><p>" . $this->author_inline_preview_html( $settings ) . "</p></div></div></div>";
    }

    private function publication_preview_sample_html( string $style, array $settings ): string {
        $color = sanitize_hex_color( (string) ( $settings["publication_muckrack_color"] ?? "#2d5277" ) ) ?: "#2d5277";
        $label = "publication_name" === (string) ( $settings["publication_muckrack_text_mode"] ?? "news_outlet" ) ? get_bloginfo( "name" ) : "News outlet";
        $class = "smpi-publication-preview-" . sanitize_html_class( $style );
        return "<span class=\"" . esc_attr( $class ) . "\" style=\"--smpi-muckrack-color:" . esc_attr( $color ) . "\">" . esc_html( $label ) . " verified by <strong class=smpi-publication-preview-brand>MuckRack</strong> editorial team <a href=#>(learn more)</a></span>";
    }

    private function publication_muckrack_preview_html( array $settings ): string {
        $html = "<div class=\"smpi-control-group smpi-preview-group\"><h3>Visual examples</h3><div class=smpi-publication-preview-list>";
        foreach ( [ "block" => "Editorial block", "compact" => "Compact pill", "minimalist" => "Minimalist text" ] as $style => $label ) {
            $html .= "<div class=smpi-publication-preview-item><strong>" . esc_html( $label ) . "</strong><p>" . $this->publication_preview_sample_html( $style, $settings ) . "</p></div>";
        }
        return $html . "</div></div>";
    }

    private function simple_status_html( bool $ok, string $message ): string {
        return "<p><span class=\"" . ( $ok ? "smpi-ok" : "smpi-warn" ) . "\">" . ( $ok ? "GREEN CHECK" : "YELLOW !" ) . "</span> " . esc_html( $message ) . "</p>";
    }


    private function post_acf_addons_report_html(): string {
        $summary_enabled = Settings::bool( "post_summary_acf_enabled" );
        $faqs_enabled = Settings::bool( "post_faqs_acf_enabled" );
        $summary_registered = function_exists( "acf_get_field" ) && (bool) acf_get_field( "field_65ab7ba0e849b" );
        $faqs_registered = function_exists( "acf_get_field" ) && (bool) acf_get_field( "field_65ab7bc1e849c" );
        $ok = ( ! $summary_enabled || $summary_registered ) && ( ! $faqs_enabled || $faqs_registered );
        $html = $this->simple_status_html( $ok, "Post Summary enabled: " . ( $summary_enabled ? "yes" : "no" ) . ". Post FAQs enabled: " . ( $faqs_enabled ? "yes" : "no" ) . "." );
        $html .= "<table class=\"widefat striped\"><thead><tr><th>Field</th><th>Setting</th><th>ACF runtime</th><th>Key</th><th>Locations</th></tr></thead><tbody>";
        $html .= "<tr><td>Post Summary</td><td>" . ( $summary_enabled ? "Enabled" : "Disabled" ) . "</td><td>" . ( $summary_registered ? "GREEN CHECK" : "YELLOW !" ) . "</td><td><code>field_65ab7ba0e849b</code></td><td><code>post</code>, <code>imported-news</code></td></tr>";
        $html .= "<tr><td>Post FAQs</td><td>" . ( $faqs_enabled ? "Enabled" : "Disabled" ) . "</td><td>" . ( $faqs_registered ? "GREEN CHECK" : "YELLOW !" ) . "</td><td><code>field_65ab7bc1e849c</code></td><td><code>post</code>, <code>imported-news</code></td></tr>";
        return $html . "</tbody></table>";
    }


    private function table_of_contents_report_html(): string {
        $report = \smp_publication_integration\Content\TableOfContents::integrity_report();
        $html = $this->simple_status_html( ! empty( $report["enabled"] ), "Shortcode active: " . (string) $report["shortcode"] . ". Auto single.php placement: " . ( ! empty( $report["auto_single"] ) ? "on" : "off" ) . "." );
        $html .= "<table class=widefat><tbody><tr><th>Sample post</th><td>#" . esc_html( (string) $report["sample_post_id"] ) . "</td></tr><tr><th>Headings found</th><td>" . esc_html( (string) $report["heading_count"] ) . "</td></tr></tbody></table>";
        return $html;
    }

    private function estimated_read_time_report_html(): string {
        $report = \smp_publication_integration\Content\EstimatedReadTime::integrity_report( 5 );
        $html = $this->simple_status_html( ! empty( $report["enabled"] ), "Shortcode active: " . (string) $report["shortcode"] . ". Default output is minutes; use unit=seconds for seconds." );
        $html .= "<table class=\"widefat striped\"><thead><tr><th>Recent post</th><th>Words</th><th>Seconds</th><th>Minutes</th></tr></thead><tbody>";
        foreach ( $report["posts"] as $row ) {
            $html .= "<tr><td>" . esc_html( $row["title"] ) . " (#" . esc_html( (string) $row["post_id"] ) . ")</td><td>" . esc_html( (string) $row["words"] ) . "</td><td>" . esc_html( (string) $row["seconds"] ) . "</td><td>" . esc_html( (string) $row["minutes"] ) . "</td></tr>";
        }
        return $html . "</tbody></table>";
    }

    private function elementor_css_report_html(): string {
        $report = \smp_publication_integration\Content\ElementorCssCacheBusting::test_report();
        $html = $this->simple_status_html( ! empty( $report["enabled"] ) && (int) $report["css_files"] > 0, "Elementor CSS files found: " . (int) $report["css_files"] );
        $html .= "<table class=\"widefat striped\"><thead><tr><th>File</th><th>Readable</th><th>Cache query</th></tr></thead><tbody>";
        foreach ( $report["sample_files"] as $file ) {
            $html .= "<tr><td><code>" . esc_html( $file["file"] ) . "</code></td><td>" . ( $file["readable"] ? "GREEN CHECK" : "RED X" ) . "</td><td><code>" . esc_html( $file["query"] ) . "</code></td></tr>";
        }
        return $html . "</tbody></table>";
    }

    private function muckrack_report_html(): string {
        $rows = \smp_publication_integration\Content\MuckRackVerification::integrity_report( 10 );
        $forced = Settings::bool( "muckrack_author_always_show" );
        $html = $this->simple_status_html( Settings::bool( "muckrack_verified_enabled" ), "Top 10 authors by published posts checked for MuckRack ACF/user fields. Always-show override: " . ( $forced ? "on" : "off" ) . "." );
        $html .= "<table class=\"widefat striped\"><thead><tr><th>User</th><th>Posts</th><th>ACF verified</th><th>Effective</th><th>Forced</th><th>URL</th><th>Description</th></tr></thead><tbody>";
        foreach ( $rows as $row ) {
            $html .= "<tr><td>" . esc_html( $row["display_name"] ) . " (#" . esc_html( (string) $row["user_id"] ) . ")</td><td>" . esc_html( (string) $row["posts"] ) . "</td><td>" . ( $row["acf_verified"] ? "GREEN CHECK" : "YELLOW !" ) . "</td><td>" . ( $row["verified"] ? "GREEN CHECK" : "YELLOW !" ) . "</td><td>" . ( $row["forced"] ? "YES" : "NO" ) . "</td><td>" . ( $row["has_url"] ? "GREEN CHECK" : "YELLOW !" ) . "</td><td>" . ( $row["has_description"] ? "GREEN CHECK" : "YELLOW !" ) . "</td></tr>";
        }
        return $html . "</tbody></table>";
    }

    private function publication_muckrack_report_html(): string {
        $report = \smp_publication_integration\Content\MuckRackVerification::publication_report();
        $placements = ! empty( $report["placements"] ) ? implode( ", ", array_map( "sanitize_key", (array) $report["placements"] ) ) : "none selected";
        $html = $this->simple_status_html( ! empty( $report["effective"] ), "Feature toggle: " . ( ! empty( $report["enabled"] ) ? "on" : "off" ) . ". Publication ACF verified: " . ( ! empty( $report["acf_verified"] ) ? "yes" : "no" ) . "." );
        $html .= "<table class=\"widefat striped\"><tbody>";
        $html .= "<tr><th>Text option</th><td><code>" . esc_html( (string) $report["text_mode"] ) . "</code></td></tr>";
        $html .= "<tr><th>Display style</th><td><code>" . esc_html( (string) $report["style"] ) . "</code></td></tr>";
        $html .= "<tr><th>Accent color</th><td><span class=smpi-color-swatch style=\"background:" . esc_attr( (string) $report["color"] ) . "\"></span> <code>" . esc_html( (string) $report["color"] ) . "</code></td></tr>";
        $html .= "<tr><th>Placement</th><td>" . esc_html( $placements ) . "</td></tr>";
        $html .= "<tr><th>MuckRack URL</th><td>" . ( "" !== $report["url"] ? esc_html( (string) $report["url"] ) : "YELLOW ! Missing optional URL." ) . "</td></tr>";
        $html .= "<tr><th>Shortcode</th><td><code>" . esc_html( (string) $report["shortcode"] ) . "</code></td></tr>";
        $html .= "<tr><th>Actual preview</th><td>" . ( "" !== $report["preview_html"] ? wp_kses_post( (string) $report["preview_html"] ) : "YELLOW ! Enable feature and verify publication ACF to render the live shortcode preview. Visual examples are shown above." ) . "</td></tr>";
        return $html . "</tbody></table>";
    }

    private function press_release_report_html(): string {
        $rows = \smp_publication_integration\Content\Visibility::author_report( 10 );
        $hpr = Dependencies::hpr_active();
        $html = $this->simple_status_html( $hpr && Settings::bool( "press_release_include_enabled" ), "Hexa PR Wire active: " . ( $hpr ? "yes" : "no" ) . ". Press-release CPT exists: " . ( post_type_exists( "press-release" ) ? "yes" : "no" ) . "." );
        $html .= "<table class=\"widefat striped\"><thead><tr><th>Recent author</th><th>Posts</th><th>Press releases</th><th>Expected on author.php</th><th>Consistent</th></tr></thead><tbody>";
        foreach ( $rows as $row ) {
            $html .= "<tr><td>" . esc_html( $row["display_name"] ) . " (#" . esc_html( (string) $row["user_id"] ) . ")</td><td>" . esc_html( (string) $row["posts"] ) . "</td><td>" . esc_html( (string) $row["press_releases"] ) . "</td><td>" . ( $row["expected"] ? "YES" : "NO" ) . "</td><td>" . ( $row["consistent"] ? "GREEN CHECK" : "RED X" ) . "</td></tr>";
        }
        return $html . "</tbody></table>";
    }

    private function rank_math_breadcrumb_report_html(): string {
        $rank = get_option( "rank-math-options-general", [] );
        $on = is_array( $rank ) && ( $rank["breadcrumbs"] ?? "" ) === "on";
        return $this->simple_status_html( $on, $on ? "Rank Math breadcrumbs are enabled." : "Rank Math breadcrumbs are disabled or missing." );
    }

    private function hws_masked_login_status(): array {
        $opts = get_option( "hws_login_mask_options", [] );
        $slug = is_array( $opts ) && ! empty( $opts["slug"] ) ? sanitize_title( (string) $opts["slug"] ) : "hexa-admin";
        $enabled = is_array( $opts ) && ! empty( $opts["enabled"] ) && Dependencies::hws_base_tools_active();
        return [ "enabled" => $enabled, "slug" => $slug, "url" => home_url( "/" . $slug . "/" ), "hws_active" => Dependencies::hws_base_tools_active() ];
    }

    private function hws_masked_login_report_html(): string {
        $status = $this->hws_masked_login_status();
        return $this->simple_status_html( ! empty( $status["enabled"] ), "Masked login URL: " . $status["url"] ) . "<p><a class=\"button\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( $status["url"] ) . "\">Open masked admin URL</a></p><p><small>Owned by HWS Base Tools. SMP only reports this.</small></p>";
    }

    private function activity_log_html( string $feature_key = "" ): string {
        $log = Settings::activity_log();
        if ( "" !== $feature_key ) {
            $needle = sanitize_key( $feature_key );
            $log = array_values( array_filter( $log, static function ( array $item ) use ( $needle ): bool {
                return false !== strpos( sanitize_key( (string) ( $item["message"] ?? "" ) ), $needle );
            } ) );
        }
        if ( empty( $log ) ) {
            return "<p>No activity logged for this feature yet.</p>";
        }
        $html = "<ul>";
        foreach ( array_slice( $log, 0, 5 ) as $item ) {
            $html .= "<li><code>" . esc_html( (string) ( $item["time"] ?? "" ) ) . "</code> " . esc_html( (string) ( $item["message"] ?? "" ) ) . "</li>";
        }
        return $html . "</ul>";
    }

    private function optimization(): void {
        echo '<div class="smpi-panel"><h2>Optimization</h2><p>Settings rerooting is intentionally pending until target values are supplied. LiteSpeed checks report current concrete values.</p><button id="smpi-refresh-optimization" class="button button-primary" type="button">Refresh Optimization Report</button><span class="spinner"></span></div><div id="smpi-optimization-report">' . self::render_optimization_report_html() . '</div>';
    }

    public static function render_optimization_report_html(): string {
        $rank = get_option( 'rank-math-options-general', [] );
        $plugin = PluginRegistry::info( 'litespeed-cache/litespeed-cache.php' );
        $checks = [
            [ 'LiteSpeed installed', $plugin['installed'], $plugin['installed'] ? 'LiteSpeed Cache installed.' : 'LiteSpeed Cache missing.' ],
            [ 'LiteSpeed active', $plugin['active'], $plugin['active'] ? 'Version ' . $plugin['version'] : 'Plugin inactive.' ],
            [ 'LiteSpeed update', ! $plugin['update_available'], $plugin['update_available'] ? 'Update available: ' . $plugin['update_version'] : 'No update reported.' ],
            [ 'RankMath breadcrumbs', is_array( $rank ) && ( $rank['breadcrumbs'] ?? '' ) === 'on', is_array( $rank ) && ( $rank['breadcrumbs'] ?? '' ) === 'on' ? 'Breadcrumbs enabled.' : 'Breadcrumbs disabled.' ],
            [ 'Site favicon', (bool) get_site_icon_url(), get_site_icon_url( 32 ) ?: 'No site icon configured.' ],
        ];
        foreach ( [ 'litespeed.conf._version', 'litespeed.conf.cache', 'litespeed.conf.cache-browser', 'litespeed.conf.cache-mobile', 'litespeed.conf.cache-favicon', 'litespeed.conf.optm-css_min', 'litespeed.conf.optm-js_min', 'litespeed.conf.optm-html_min', 'litespeed.conf.media-lazy', 'litespeed.conf.img_optm-auto' ] as $key ) {
            $value = get_option( $key, '__missing__' );
            $checks[] = [ $key, '__missing__' !== $value && '' !== $value, '<code>' . esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ) . '</code>' ];
        }
        ob_start();
        echo '<div class="smpi-grid">';
        foreach ( $checks as $check ) {
            echo '<div class="smpi-card"><h3>' . esc_html( $check[0] ) . '</h3><p><span class="' . ( $check[1] ? 'smpi-ok' : 'smpi-warn' ) . '">' . ( $check[1] ? 'GREEN CHECK' : 'YELLOW !' ) . '</span> ' . wp_kses_post( $check[2] ) . '</p></div>';
        }
        echo '</div>';
        return (string) ob_get_clean();
    }

    private function pages(): void {
        $settings = Settings::all();
        $pages = get_pages( [ 'sort_column' => 'post_title', 'sort_order' => 'ASC', 'post_status' => [ 'publish', 'draft', 'private' ] ] );
        echo '<div class="smpi-panel"><h2>Publication Pages</h2><p>Assign canonical pages for dynamic retrieval and launch integrity checks.</p></div>';
        foreach ( Settings::page_types() as $type => $config ) {
            $page_id = isset( $settings['page_assignments'][ $type ] ) ? (int) $settings['page_assignments'][ $type ] : 0;
            echo '<div class="smpi-panel smpi-page-row" data-page-type="' . esc_attr( $type ) . '"><h2>' . ( $page_id ? '<span class="smpi-ok">●</span> ' : '<span class="smpi-bad">●</span> ' ) . esc_html( $config['label'] ) . '</h2><p>' . esc_html( $config['description'] ) . '</p><select class="smpi-page-select"><option value="0">Not assigned</option>';
            foreach ( $pages as $page ) {
                echo '<option value="' . esc_attr( (string) $page->ID ) . '"' . selected( $page_id, $page->ID, false ) . '>' . esc_html( $page->post_title . ' (#' . $page->ID . ')' ) . '</option>';
            }
            echo '</select>';
            if ( ! empty( $config['template'] ) ) {
                echo '<p><label><strong>Starter/template text</strong></label></p><textarea class="large-text smpi-page-template" rows="5">' . esc_textarea( $settings['page_templates'][ $type ] ?? '' ) . '</textarea>';
            }
            echo "<p><button class=\"button button-primary smpi-save-page\" type=\"button\">Save Page Assignment</button> <button class=\"button smpi-create-page\" type=\"button\">Create New Page</button><span class=\"spinner\"></span><span class=\"smpi-save-state\"></span></p></div>";
        }
    }

    private function verified_profiles(): void {
        echo '<div class="smpi-panel"><h2>Verified Profiles Integration</h2><p>Recommended for founder/person bindings. SMP still runs without it when founder marketing is disabled.</p>';
        $this->plugin_table( [ 'smp-verified-profiles/initialization.php' => PluginRegistry::info( 'smp-verified-profiles/initialization.php' ) ] );
        echo '</div>';
    }

    private function integrations(): void {
        echo '<div class="smpi-panel"><h2>Dependency and Plugin Registry</h2><p>Required dependencies block boot when missing. Recommended dependencies are reported when inactive.</p>';
        $this->plugin_table( PluginRegistry::all() );
        echo '</div>';
    }

    private function plugin_table( array $plugins ): void {
        echo '<table class="widefat striped"><thead><tr><th>Plugin</th><th>Requirement</th><th>Status</th><th>Version</th><th>GitHub</th><th>Actions</th></tr></thead><tbody>';
        foreach ( $plugins as $file => $info ) {
            echo '<tr data-plugin-file="' . esc_attr( $file ) . '"><td><strong>' . esc_html( $info['label'] ) . '</strong><br><code>' . esc_html( $file ) . '</code></td><td>' . esc_html( $info['type'] ) . '</td><td>' . ( $info['active'] ? '<span class="smpi-ok">Active</span>' : ( $info['installed'] ? '<span class="smpi-warn">Installed inactive</span>' : '<span class="smpi-bad">Missing</span>' ) ) . '</td><td>' . esc_html( $info['version'] ?: 'n/a' ) . ( $info['github_version'] ? '<br><small>GitHub: ' . esc_html( $info['github_version'] ) . '</small>' : '' ) . '</td><td>' . ( $info['github_repo'] ? '<code>' . esc_html( "https://github.com/" . $info['github_repo'] ) . '</code>' : 'n/a' ) . '</td><td><button class="button smpi-plugin-action" data-operation="update">Update</button> <button class="button smpi-plugin-action" data-operation="activate">Activate</button> <button class="button smpi-plugin-action" data-operation="deactivate">Deactivate</button> <button class="button smpi-plugin-action" data-operation="delete">Delete</button><span class="spinner"></span><span class="smpi-save-state"></span></td></tr>';
        }
        echo '</tbody></table>';
    }

    private function counts(): array {
        $out = [];
        foreach ( [ 'post', 'page', 'profile', 'press-release' ] as $type ) {
            $count = wp_count_posts( $type );
            $out[ $type ] = $count && isset( $count->publish ) ? (int) $count->publish : 0;
        }
        return $out;
    }

    private function latest_post_id(): int {
        $query = new \WP_Query( [ "post_type" => "post", "post_status" => "publish", "posts_per_page" => 1, "fields" => "ids", "no_found_rows" => true ] );
        return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
    }

    private function extract_schema_types( string $url ): array {
        $response = wp_remote_get( $url, [ 'timeout' => 8 ] );
        if ( is_wp_error( $response ) || ! preg_match_all( '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', wp_remote_retrieve_body( $response ), $matches ) ) {
            return [];
        }
        return $matches[1];
    }

    private function recent_posts_schema_count(): int {
        $count = 0;
        foreach ( get_posts( [ 'post_type' => 'post', 'posts_per_page' => 10, 'post_status' => 'publish', 'fields' => 'ids' ] ) as $post_id ) {
            $count += $this->extract_schema_types( get_permalink( $post_id ) ) ? 1 : 0;
        }
        return $count;
    }

    private function toggle( string $key, string $label, array $settings ): void {
        echo '<tr><th>' . esc_html( $label ) . '</th><td><label><input class="smpi-setting" type="checkbox" data-key="' . esc_attr( $key ) . '" value="1" ' . checked( ! empty( $settings[ $key ] ), true, false ) . '> Enabled</label><span class="spinner"></span><span class="smpi-save-state"></span></td></tr>';
    }

    private function card( string $title, string $content ): void {
        echo '<div class="smpi-card"><h3>' . esc_html( $title ) . '</h3><p>' . wp_kses_post( $content ) . '</p></div>';
    }

    private function status_card( string $title, bool $ok, string $message ): void {
        $this->card( $title, ( $ok ? '<span class="smpi-ok">GREEN CHECK</span> ' : '<span class="smpi-warn">YELLOW !</span> ' ) . esc_html( $message ) );
    }



    private function scripts(): void { ?>
        <script>
        window.smpiAdmin={ajaxUrl:ajaxurl,nonce:<?php echo wp_json_encode( Ajax::nonce() ); ?>};
        jQuery(function($){
            function saveSetting(e, done){var k=e.data(`key`),d={action:`smpi_save_settings`,nonce:smpiAdmin.nonce},v;if(e.hasClass(`smpi-setting-array`)){var g=$(`.smpi-setting-array[data-key="${k}"]`);d[k+`_present`]=1;d[k]=g.filter(`:checked`).map(function(){return $(this).val()}).get()}else{v=e.is(`:checkbox`)?(e.is(`:checked`)?1:0):e.val();d[k]=v}var r=e.closest(`.smpi-feature-card,.smpi-control-group,td,.smpi-user-picker`);r.find(`.spinner`).addClass(`is-active`);$.post(smpiAdmin.ajaxUrl,d).done(function(x){r.find(`.smpi-save-state`).text(x.success?` Saved`:` Error`);if(done)done(x)}).always(function(){r.find(`.spinner`).removeClass(`is-active`)})}
            $(document).on(`change`,`.smpi-setting,.smpi-setting-array`,function(){saveSetting($(this))});
            var userTimer=null;
            $(document).on(`input`,`.smpi-user-search`,function(){var input=$(this),picker=input.closest(`.smpi-user-picker`),box=picker.find(`.smpi-user-results`),term=input.val();clearTimeout(userTimer);if(term.length<2){box.empty();return}userTimer=setTimeout(function(){picker.find(`.spinner`).addClass(`is-active`);$.post(smpiAdmin.ajaxUrl,{action:`smpi_search_users`,nonce:smpiAdmin.nonce,term:term}).done(function(x){box.empty();if(!x.success||!x.data.users.length){box.html(`<p class="smpi-muted">No matching users.</p>`);return}$.each(x.data.users,function(i,u){var b=$(`<button type="button" class="button smpi-user-result"></button>`).text(u.label+` - `+u.email).data(`user`,u);box.append(b)})}).always(function(){picker.find(`.spinner`).removeClass(`is-active`)})},250)});
            $(document).on(`click`,`.smpi-user-result`,function(){var u=$(this).data(`user`),picker=$(this).closest(`.smpi-user-picker`);picker.find(`.smpi-user-search`).val(u.label);picker.find(`.smpi-publication-user-setting`).val(u.id);picker.find(`.smpi-user-results`).html(`<p><span class="smpi-ok">GREEN CHECK</span> Selected main publication profile: ${u.label}. Saved by AJAX.</p>`);$(`.smpi-current-user-summary`).html(`<div class="smpi-profile-card"><div class="smpi-profile-avatar"><img src="${u.avatar}" alt=""></div><div class="smpi-profile-info"><h3>${u.name}</h3><p>${u.email}</p><p><a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="${u.edit_url}">Edit Profile</a> <a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="${u.view_url}">View Author Page</a></p></div></div>`);saveSetting(picker.find(`.smpi-publication-user-setting`))});
            $(document).on(`click`,`.smpi-clear-user`,function(){var picker=$(this).closest(`.smpi-user-picker`);picker.find(`.smpi-user-search`).val(``);picker.find(`.smpi-publication-user-setting`).val(0);picker.find(`.smpi-user-results`).empty();$(`.smpi-current-user-summary`).html(`<div class="smpi-empty-state"><strong>No main publication profile selected.</strong><p>Search by publication name, username, or email and choose the profile that represents this publication.</p></div>`);saveSetting(picker.find(`.smpi-publication-user-setting`))});
            function founderIds(panel){return panel.find(`.smpi-founder-profile-card`).map(function(){return $(this).data(`profile-id`)}).get()}
            function founderEmptyHtml(){return `<div class="smpi-empty-state smpi-empty-founder-profiles"><strong>No founder profiles selected.</strong><p>Use the search above to add founder records from Verified Profiles.</p></div>`}
            function profileCard(p){var media=p.thumbnail?`<img src="${p.thumbnail}" alt="">`:`<span class="dashicons dashicons-id-alt"></span>`;return `<div class="smpi-founder-profile-card" data-profile-id="${p.id}"><div class="smpi-founder-thumb">${media}</div><div class="smpi-founder-info"><strong>${p.label}</strong><p class="smpi-muted">Profile #${p.id}</p><p><a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="${p.edit_url}">Edit Profile</a> <a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="${p.view_url}">View Profile</a> <button type="button" class="button smpi-remove-founder-profile">Remove</button></p></div></div>`}
            function saveFounderProfiles(panel){var ids=founderIds(panel),wrap=panel.closest(`.smpi-profile-picker`);wrap.find(`.spinner`).addClass(`is-active`);$.post(smpiAdmin.ajaxUrl,{action:`smpi_save_founder_profiles`,nonce:smpiAdmin.nonce,founder_profile_ids:ids}).done(function(x){wrap.find(`.smpi-save-state`).text(x.success?` Saved`:` Error`)}).always(function(){wrap.find(`.spinner`).removeClass(`is-active`)})}
            var profileTimer=null;
            $(document).on(`input`,`.smpi-profile-search`,function(){var input=$(this),wrap=input.closest(`.smpi-profile-picker`),box=wrap.find(`.smpi-profile-results`),term=input.val();clearTimeout(profileTimer);if(term.length<2){box.empty();return}profileTimer=setTimeout(function(){wrap.find(`.spinner`).addClass(`is-active`);$.post(smpiAdmin.ajaxUrl,{action:`smpi_search_profiles`,nonce:smpiAdmin.nonce,term:term}).done(function(x){box.empty();if(!x.success||!x.data.profiles.length){box.html(`<p class="smpi-muted">No matching profiles.</p>`);return}$.each(x.data.profiles,function(i,p){var b=$(`<button type="button" class="button smpi-profile-result"></button>`).text(p.label+` (#`+p.id+`)`).data(`profile`,p);box.append(b)})}).always(function(){wrap.find(`.spinner`).removeClass(`is-active`)})},250)});
            $(document).on(`click`,`.smpi-profile-result`,function(){var p=$(this).data(`profile`),wrap=$(this).closest(`.smpi-profile-picker`),selected=wrap.find(`.smpi-founder-selected`);if(!selected.find(`.smpi-founder-profile-card[data-profile-id="${p.id}"]`).length){selected.find(`.smpi-empty-founder-profiles`).remove();selected.append(profileCard(p));saveFounderProfiles(selected)}wrap.find(`.smpi-profile-search`).val(``);wrap.find(`.smpi-profile-results`).empty()});
            $(document).on(`click`,`.smpi-remove-founder-profile`,function(){var selected=$(this).closest(`.smpi-founder-selected`);$(this).closest(`.smpi-founder-profile-card`).remove();if(!selected.find(`.smpi-founder-profile-card`).length){selected.html(founderEmptyHtml())}saveFounderProfiles(selected)});
            $(document).on(`click`,`.smpi-save-page`,function(){var r=$(this).closest(`.smpi-page-row`),d={action:`smpi_save_page_assignment`,nonce:smpiAdmin.nonce,page_type:r.data(`page-type`),page_id:r.find(`.smpi-page-select`).val(),template:r.find(`.smpi-page-template`).val()||``};r.find(`.spinner`).addClass(`is-active`);$.post(smpiAdmin.ajaxUrl,d).done(function(x){r.find(`.smpi-save-state`).text(x.success?` Saved`:` Error`)}).always(function(){r.find(`.spinner`).removeClass(`is-active`)})});
            $(document).on(`click`,`.smpi-create-page`,function(){var r=$(this).closest(`.smpi-page-row`),d={action:`smpi_create_page_assignment`,nonce:smpiAdmin.nonce,page_type:r.data(`page-type`)};r.find(`.spinner`).addClass(`is-active`);$.post(smpiAdmin.ajaxUrl,d).done(function(x){if(x.success&&x.data.page){var p=x.data.page,sel=r.find(`.smpi-page-select`);if(!sel.find(`option[value=`+p.id+`]`).length){sel.append($(`<option></option>`).attr(`value`,p.id).text(p.title+` (#`+p.id+`)`))}sel.val(p.id);r.find(`.smpi-save-state`).html(` Created draft page. <a target="_blank" rel="noopener noreferrer" href="${p.edit_url}">Edit</a>`)}else{r.find(`.smpi-save-state`).text(` Error`)}}).always(function(){r.find(`.spinner`).removeClass(`is-active`)})});
            $(`#smpi-refresh-optimization`).on(`click`,function(){var s=$(this).next(`.spinner`);s.addClass(`is-active`);$.post(smpiAdmin.ajaxUrl,{action:`smpi_refresh_optimization`,nonce:smpiAdmin.nonce}).done(function(x){if(x.success)$(`#smpi-optimization-report`).html(x.data.html)}).always(function(){s.removeClass(`is-active`)})});
            $(document).on(`click`,`.smpi-plugin-action`,function(){var b=$(this),r=b.closest(`tr`);if(b.data(`operation`)===`delete`&&!confirm(`Delete this plugin?`))return;r.find(`.spinner`).addClass(`is-active`);$.post(smpiAdmin.ajaxUrl,{action:`smpi_plugin_action`,nonce:smpiAdmin.nonce,plugin_file:r.data(`plugin-file`),operation:b.data(`operation`)}).done(function(x){r.find(`.smpi-save-state`).text(x.success?` Done`:` Error`)}).always(function(){r.find(`.spinner`).removeClass(`is-active`)})});
            var o=0,bs=20,total=0;$(`#smpi-reprocess-schema`).on(`click`,function(){o=0;total=0;$(this).prop(`disabled`,true);$(`#smpi-schema-report`).empty().append(`<p>Starting...</p>`);p()});function p(){$.post(ajaxurl,{action:`smpi_reprocess_schema`,nonce:smpiAdmin.nonce,offset:o,batch_size:bs}).done(function(x){if(!x||!x.success){$(`#smpi-reprocess-schema`).prop(`disabled`,false);return}total=x.data.total||0;$.each(x.data.items||[],function(i,it){$(`#smpi-schema-report`).append(`<pre class="smpi-code">${$(`<div>`).text(it.schema||``).html()}</pre>`)});o+=bs;if(o<total)p();else $(`#smpi-reprocess-schema`).prop(`disabled`,false)})}
        });</script>
    <?php }

    private function styles(): void { ?>
        <style>.smpi-dashboard{max-width:1280px}.smpi-tabs-nav{display:flex;flex-wrap:wrap;gap:8px;margin:18px 0;border-bottom:1px solid #dcdcde}.smpi-tab-btn{border:1px solid #dcdcde;border-bottom:none;background:#f6f7f7;padding:10px 14px;border-radius:8px 8px 0 0;cursor:pointer;text-decoration:none;color:#1d2327;display:inline-block}.smpi-tab-btn.active{background:#fff;color:#2271b1;font-weight:700}.smpi-tab-content{display:none}.smpi-tab-content.active{display:block}.smpi-hero{margin:18px 0;padding:28px 30px;border:1px solid #dcdcde;border-radius:14px;background:linear-gradient(135deg,#fff 0%,#eef6fb 100%);box-shadow:0 10px 28px rgba(0,0,0,.05)}.smpi-kicker{margin:0 0 8px;color:#2271b1;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.smpi-hero h2{margin:0 0 10px;font-size:22px;line-height:1.3;max-width:54ch}.smpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px;margin:18px 0}.smpi-card,.smpi-panel{padding:18px;border:1px solid #dcdcde;border-radius:12px;background:#fff;margin:16px 0}.smpi-card h3,.smpi-panel h2{margin-top:0}.smpi-panel h2{font-size:16px;margin-bottom:14px}.smpi-ok{color:#008a20;font-weight:700}.smpi-warn{color:#996800;font-weight:700}.smpi-bad{color:#b32d2e;font-weight:700}.smpi-code,.smpi-code-panel{white-space:pre-wrap;background:#101517;color:#e6edf3;border:1px solid #1f2933;border-radius:10px;padding:14px;max-height:520px;overflow:auto}.smpi-page-select,.smpi-mapping-select{min-width:320px}.smpi-save-state{margin-left:8px;font-weight:700}.smpi-user-picker{padding:14px;border:1px solid #dcdcde;border-radius:10px;background:#f9fafb;margin:12px 0}.smpi-user-results{display:grid;gap:6px;margin-top:10px;max-width:720px}.smpi-user-result{text-align:left;justify-content:flex-start}.smpi-profile-card{display:flex;gap:18px;align-items:center;padding:18px;border:1px solid #e5e7eb;border-radius:12px;background:#f9fafb;margin:14px 0}.smpi-profile-avatar img{width:96px;height:96px;border-radius:999px;background:#fff;object-fit:cover;box-shadow:0 2px 10px rgba(0,0,0,.08)}.smpi-profile-info h3{margin:0 0 6px}.smpi-profile-fields{display:grid;gap:10px;margin-top:12px}.smpi-field-preview{padding:11px 14px;border:1px solid #e5e7eb;border-radius:8px;background:#fff}.smpi-field-preview p{margin:6px 0 0}.smpi-muted{color:#646970}.smpi-alert{padding:12px 14px;border-radius:8px;border:1px solid #f0c36d;background:#fff8e5}.smpi-alert-warning{color:#664d03}.smpi-publication-author-panel{padding:0;overflow:hidden}.smpi-publication-author-panel .smpi-overview-section{padding:24px 28px;background:linear-gradient(135deg,#111827 0%,#1f3a5f 100%);color:#fff}.smpi-publication-author-panel .smpi-overview-section .smpi-kicker{color:#9bd4ff}.smpi-publication-author-panel .smpi-overview-section h2{font-size:30px;margin:0 0 8px}.smpi-publication-author-panel .smpi-overview-section p:last-child{max-width:760px;font-size:15px;color:#e5edf7}.smpi-author-binding-layout{display:grid;grid-template-columns:minmax(320px,520px) 1fr;gap:22px;padding:24px 28px}.smpi-author-search-card{padding:18px;border:1px solid #d8dee8;border-radius:14px;background:#f8fafc}.smpi-user-picker{padding:0;border:0;background:transparent;margin:12px 0 0}.smpi-user-search{width:min(100%,420px);font-size:16px;padding:8px 12px}.smpi-empty-state{padding:22px;border:1px dashed #b9c2cf;border-radius:14px;background:#fbfcfe;color:#334155}.smpi-empty-state p{margin:8px 0 0}.smpi-advanced-map{display:none}.smpi-founder-profile-panel{border-top:1px solid #e5e7eb;padding:22px 28px}.smpi-founder-header h3{margin:0 0 6px}.smpi-profile-picker{padding:18px;border:1px solid #d8dee8;border-radius:14px;background:#f8fafc}.smpi-profile-search{width:min(100%,420px);font-size:16px;padding:8px 12px}.smpi-profile-results{display:grid;gap:6px;margin-top:10px;max-width:720px}.smpi-profile-result{text-align:left;justify-content:flex-start}.smpi-founder-selected{display:grid;gap:12px;margin-top:16px}.smpi-founder-profile-card{display:flex;gap:14px;align-items:center;padding:14px;border:1px solid #e5e7eb;border-radius:12px;background:#fff}.smpi-founder-thumb img,.smpi-founder-thumb span{width:58px;height:58px;border-radius:999px;display:flex;align-items:center;justify-content:center;background:#eef2f7;object-fit:cover}.smpi-founder-info p{margin:6px 0 0}.smpi-system{margin-top:18px}.smpi-defs{margin:0}.smpi-def{display:grid;grid-template-columns:190px 1fr;gap:18px;align-items:center;padding:13px 0;border-top:1px solid #eef0f2}.smpi-def:first-child{border-top:0;padding-top:2px}.smpi-def-block{align-items:start}.smpi-defs dt{margin:0;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#646970}.smpi-defs dd{margin:0}.smpi-defs code{background:#f0f1f3;border-radius:6px;padding:4px 9px;font-size:13px;color:#1d2327;word-break:break-all}@media(max-width:782px){.smpi-def{grid-template-columns:1fr;gap:6px}}.smpi-feature-card{padding:22px;border:1px solid #d8dee8;border-radius:18px;background:#fff;margin:18px 0;box-shadow:0 10px 28px rgba(15,23,42,.05)}.smpi-feature-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;border-bottom:1px solid #eef0f2;padding-bottom:16px;margin-bottom:16px}.smpi-feature-head h2{font-size:22px;margin:0}.smpi-feature-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px}.smpi-feature-grid section,.smpi-feature-controls{border:1px solid #eef0f2;border-radius:14px;background:#f8fafc;padding:14px}.smpi-feature-report,.smpi-feature-activity{grid-column:1/-1;overflow-x:auto}.smpi-feature-report table{min-width:720px;max-width:100%}.smpi-feature-report .widefat{width:100%}.smpi-feature-grid h3,.smpi-control-group h3{margin:0 0 9px;font-size:13px;text-transform:uppercase;letter-spacing:.05em;color:#475569}.smpi-choice-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:10px}.smpi-choice-list{display:grid;grid-template-columns:1fr;gap:10px}.smpi-choice-card{display:flex;gap:12px;align-items:flex-start;position:relative;padding:14px 16px;border:1px solid #d8dee8;border-radius:14px;background:#fff;cursor:pointer;box-shadow:inset 0 0 0 1px transparent}.smpi-choice-card input{margin-top:3px}.smpi-choice-card strong{display:block;color:#1f2937}.smpi-choice-card small{display:block;margin-top:3px;color:#64748b;line-height:1.35}.smpi-choice-body{min-width:0;flex:1}.smpi-selected-pill{margin-left:auto;align-self:flex-start;background:#2271b1;color:#fff;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}.smpi-choice-card.is-selected,.smpi-choice-card:has(input:checked){border-color:#2271b1;background:#eef6fb;box-shadow:inset 0 0 0 1px #2271b1}.smpi-choice-preview{display:block;margin-top:10px;padding:12px;border:1px solid #e2e8f0;border-radius:10px;background:#fff}.smpi-preview-stack,.smpi-publication-preview-list{display:grid;gap:12px}.smpi-preview-demo,.smpi-publication-preview-item{padding:12px;border:1px solid #d8dee8;border-radius:12px;background:#fff}.smpi-tooltip-demo{display:flex;align-items:center;gap:6px;flex-wrap:wrap}.smpi-tooltip-bubble{background:#111827;color:#fff;border-radius:8px;padding:6px 8px;font-size:12px;white-space:nowrap}.smpi-author-inline-demo{display:inline-block}.smpi-publication-preview-block{display:block;border-left:3px solid var(--smpi-muckrack-color,#2d5277);background:#f5f8fb;padding:12px 14px}.smpi-publication-preview-compact{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--smpi-muckrack-color,#2d5277);border-radius:999px;padding:6px 10px;background:#fff}.smpi-publication-preview-minimalist{display:inline;color:#334155}.smpi-publication-preview-brand{color:var(--smpi-muckrack-color,#2d5277);font-weight:700}.smpi-switch{display:inline-flex;gap:10px;align-items:center}.smpi-switch input{position:absolute;opacity:0}.smpi-switch span{width:42px;height:24px;border-radius:999px;background:#cbd5e1;position:relative;display:inline-block}.smpi-switch span:before{content:"";width:18px;height:18px;border-radius:999px;background:#fff;position:absolute;left:3px;top:3px;transition:.18s}.smpi-switch input:checked+span{background:#2271b1}.smpi-switch input:checked+span:before{transform:translateX(18px)}.smpi-control-row{margin:10px 0}.smpi-color-control{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.smpi-color-swatch{display:inline-block;width:32px;height:32px;border-radius:8px;border:1px solid #cbd5e1;vertical-align:middle}.smpi-icon-preview{display:flex;gap:10px;margin-top:8px}.smpi-muckrack-preview-circle,.smpi-muckrack-preview-check{display:inline-flex;align-items:center;justify-content:center;color:#2d5277;font-weight:700}.smpi-muckrack-preview-circle{width:26px;height:26px;border:1px solid currentColor;border-radius:999px}</style>
    <?php }
}
