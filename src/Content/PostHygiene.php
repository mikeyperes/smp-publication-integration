<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Admin\Ajax;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class PostHygiene {
    private const TAB = "post_hygiene";

    public function register(): void {
        add_filter( "smpi_dashboard_tabs", [ $this, "tabs" ] );
        add_filter( "smpi_render_dashboard_tab", [ $this, "render_tab" ], 10, 2 );
        add_filter( "wp_insert_post_data", [ $this, "sanitize_post_data" ], 20, 2 );
        add_action( "wp_ajax_smpi_post_hygiene_preview", [ $this, "preview" ] );
        add_action( "admin_footer", [ $this, "admin_footer_script" ] );
    }

    public function tabs( array $tabs ): array {
        $tabs[ self::TAB ] = "Post Hygiene";
        return $tabs;
    }

    public function render_tab( bool $rendered, string $id ): bool {
        if ( self::TAB !== $id ) {
            return $rendered;
        }

        $settings = Settings::all();
        $allowed_post_types = Settings::array( "post_hygiene_allowed_post_types" );
        $post_types = get_post_types( [ "public" => true ], "objects" );
        if ( ! isset( $post_types["post"] ) ) {
            $post_types["post"] = get_post_type_object( "post" );
        }
        ?>
        <section class="smpi-section smpi-post-hygiene-tab">
            <div class="smpi-feature-card">
                <div class="smpi-feature-head">
                    <div><span class="smpi-kicker">Sanitizer</span><h2>Post Hygiene</h2></div>
                    <?php echo $this->switch_control( "post_hygiene_enabled", ! empty( $settings["post_hygiene_enabled"] ) ); ?>
                </div>
                <p>Runs on save and import for selected post types. It removes imported inline formatting that fights the Elementor template while preserving article structure, links, images, lists, captions, and tables.</p>
                <p><strong>Not retroactive:</strong> old posts are changed only when resaved or cleaned by a future explicit migration tool.</p>
            </div>

            <div class="smpi-card-grid smpi-card-grid--three">
                <?php echo $this->rule_card( "post_hygiene_strip_inline_styles", "Strip inline styles", "Remove style attributes such as font-weight:400." ); ?>
                <?php echo $this->rule_card( "post_hygiene_unwrap_spans", "Unwrap spans", "Remove span wrappers while preserving their text and children." ); ?>
                <?php echo $this->rule_card( "post_hygiene_remove_font_tags", "Remove font tags", "Remove legacy font markup from imported content." ); ?>
                <?php echo $this->rule_card( "post_hygiene_strip_classes_ids", "Strip classes and IDs", "Optional. Off by default to avoid removing useful content hooks." ); ?>
                <?php echo $this->rule_card( "post_hygiene_strip_empty_tags", "Remove empty tags", "Clean empty paragraphs, headings, list items, and wrappers." ); ?>
                <?php echo $this->rule_card( "post_hygiene_clean_heading_children", "Clean heading children", "Flatten spans, font tags, and basic wrappers inside headings." ); ?>
            </div>

            <div class="smpi-feature-card">
                <h3>Allowed Post Types</h3>
                <p>Cleanup is intentionally limited. It skips Elementor library items, revisions, nav menu items, ACF field records, and posts with Elementor builder data.</p>
                <div class="smpi-choice-grid smpi-choice-grid--one">
                    <?php foreach ( $post_types as $type => $object ) : ?>
                        <?php if ( ! $object ) { continue; } ?>
                        <label class="smpi-choice-card <?php echo in_array( $type, $allowed_post_types, true ) ? "is-selected" : ""; ?>">
                            <input class="smpi-setting-array" type="checkbox" data-key="post_hygiene_allowed_post_types" value="<?php echo esc_attr( $type ); ?>" <?php checked( in_array( $type, $allowed_post_types, true ) ); ?>>
                            <span class="smpi-choice-body"><strong><?php echo esc_html( $object->labels->singular_name ?? $type ); ?></strong><small><?php echo esc_html( $type ); ?></small></span>
                            <?php if ( in_array( $type, $allowed_post_types, true ) ) : ?><span class="smpi-selected-pill">Selected</span><?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="smpi-feature-card">
                <h3>Dry-run Preview</h3>
                <p>Paste imported HTML here to see the save-time output before changing a post.</p>
                <textarea class="large-text code" rows="7" data-smpi-hygiene-input><h2><span style="font-weight: 400;">From teaching to sales training</span></h2></textarea>
                <p><button type="button" class="button button-primary" data-smpi-post-hygiene-preview>Preview cleanup</button> <span class="spinner"></span> <span data-smpi-hygiene-status></span></p>
                <pre class="smpi-code-block" data-smpi-hygiene-output></pre>
            </div>
        </section>
        <?php
        return true;
    }

    public function sanitize_post_data( array $data, array $postarr ): array {
        if ( empty( $data["post_content"] ) || ! Settings::bool( "post_hygiene_enabled" ) ) {
            return $data;
        }

        $post_type = isset( $data["post_type"] ) ? sanitize_key( (string) $data["post_type"] ) : "";
        if ( ! $this->should_clean_post_type( $post_type ) ) {
            return $data;
        }

        $post_id = isset( $postarr["ID"] ) ? absint( $postarr["ID"] ) : 0;
        if ( $this->has_elementor_data( $post_id, $postarr ) ) {
            return $data;
        }

        $data["post_content"] = self::sanitize_html( (string) $data["post_content"] );
        return $data;
    }

    public static function sanitize_html( string $html, ?array $settings = null ): string {
        $settings = $settings ?: Settings::all();
        $comments = [];
        $html = preg_replace_callback(
            "/<!--.*?-->/s",
            static function( array $match ) use ( &$comments ): string {
                $token = "%%SMPI_COMMENT_" . count( $comments ) . "%%";
                $comments[ $token ] = $match[0];
                return $token;
            },
            $html
        );

        if ( ! empty( $settings["post_hygiene_remove_font_tags"] ) ) {
            $html = preg_replace( "/<\/?font\b[^>]*>/i", "", $html );
        }

        if ( ! empty( $settings["post_hygiene_clean_heading_children"] ) ) {
            $html = preg_replace_callback( "/<(h[1-6])\b([^>]*)>(.*?)<\/\\1>/is", static function( array $match ): string {
                $inner = preg_replace( "/<\/?(?:span|font)\b[^>]*>/i", "", $match[3] );
                return "<" . $match[1] . $match[2] . ">" . $inner . "</" . $match[1] . ">";
            }, $html );
        }

        if ( ! empty( $settings["post_hygiene_unwrap_spans"] ) ) {
            $html = preg_replace( "/<span\b[^>]*>/i", "", $html );
            $html = preg_replace( "/<\/span>/i", "", $html );
        }

        $html = preg_replace( "/\sdata-[a-z0-9_:-]+\s*=\s*(\"[^\"]*\"|[^\s>]+)/i", "", $html );

        if ( ! empty( $settings["post_hygiene_strip_inline_styles"] ) ) {
            $html = preg_replace( "/\sstyle\s*=\s*(\"[^\"]*\"|[^\s>]+)/i", "", $html );
        }

        if ( ! empty( $settings["post_hygiene_strip_classes_ids"] ) ) {
            $html = preg_replace( "/\s(?:class|id)\s*=\s*(\"[^\"]*\"|[^\s>]+)/i", "", $html );
        }

        $html = wp_kses( $html, self::allowed_html( $settings ) );

        if ( ! empty( $settings["post_hygiene_strip_empty_tags"] ) ) {
            $html = self::strip_empty_tags( $html );
        }

        foreach ( $comments as $token => $comment ) {
            $html = str_replace( $token, $comment, $html );
        }

        return trim( $html );
    }

    public function preview(): void {
        if ( ! current_user_can( "manage_options" ) || ! check_ajax_referer( Ajax::NONCE, "nonce", false ) ) {
            wp_send_json_error( [ "message" => "Not allowed." ], 403 );
        }
        $html = isset( $_POST["html"] ) ? wp_unslash( (string) $_POST["html"] ) : "";
        wp_send_json_success( [ "html" => self::sanitize_html( $html ) ] );
    }

    public function admin_footer_script(): void {
        $screen = function_exists( "get_current_screen" ) ? get_current_screen() : null;
        if ( ! $screen || "settings_page_smp-publication-integration" !== $screen->id ) {
            return;
        }
        $tab = isset( $_GET["tab"] ) ? sanitize_key( wp_unslash( (string) $_GET["tab"] ) ) : "overview";
        if ( self::TAB !== $tab ) {
            return;
        }
        ?>
        <script>
        jQuery(function($){
            $(document).on("click", "[data-smpi-post-hygiene-preview]", function(){
                const button = $(this);
                const root = button.closest(".smpi-feature-card");
                const status = root.find("[data-smpi-hygiene-status]");
                const spinner = root.find(".spinner");
                spinner.addClass("is-active");
                status.text("Previewing...");
                $.post(window.ajaxurl, {
                    action: "smpi_post_hygiene_preview",
                    nonce: window.smpiAdmin ? window.smpiAdmin.nonce : "",
                    html: root.find("[data-smpi-hygiene-input]").val()
                }).done(function(response){
                    if (response && response.success) {
                        root.find("[data-smpi-hygiene-output]").text(response.data.html || "");
                        status.text("Preview ready.");
                    } else {
                        status.text((response && response.data && response.data.message) || "Preview failed.");
                    }
                }).fail(function(xhr){
                    status.text("Preview failed: HTTP " + (xhr.status || 0));
                }).always(function(){ spinner.removeClass("is-active"); });
            });
        });
        </script>
        <?php
    }

    private function rule_card( string $key, string $label, string $description ): string {
        return "<div class=\"smpi-feature-card\"><h3>" . esc_html( $label ) . "</h3><p>" . esc_html( $description ) . "</p>" . $this->switch_control( $key, Settings::bool( $key ) ) . "</div>";
    }

    private function switch_control( string $key, bool $enabled ): string {
        return "<label class=\"smpi-switch\"><input class=\"smpi-setting\" type=\"checkbox\" data-key=\"" . esc_attr( $key ) . "\" value=\"1\" " . checked( $enabled, true, false ) . "><span></span><strong>" . ( $enabled ? "Enabled" : "Disabled" ) . "</strong></label><span class=\"spinner\"></span><span class=\"smpi-save-state\"></span>";
    }

    private function should_clean_post_type( string $post_type ): bool {
        if ( in_array( $post_type, [ "elementor_library", "revision", "nav_menu_item", "acf-field", "acf-field-group" ], true ) ) {
            return false;
        }
        return in_array( $post_type, Settings::array( "post_hygiene_allowed_post_types" ), true );
    }

    private function has_elementor_data( int $post_id, array $postarr ): bool {
        if ( isset( $postarr["meta_input"] ) && is_array( $postarr["meta_input"] ) && ! empty( $postarr["meta_input"]["_elementor_data"] ) ) {
            return true;
        }
        return $post_id > 0 && "" !== (string) get_post_meta( $post_id, "_elementor_data", true );
    }

    private static function allowed_html( array $settings ): array {
        $common = ! empty( $settings["post_hygiene_strip_classes_ids"] ) ? [] : [ "class" => true, "id" => true ];
        return [
            "p" => $common, "h1" => $common, "h2" => $common, "h3" => $common, "h4" => $common, "h5" => $common, "h6" => $common,
            "ul" => $common, "ol" => $common, "li" => $common, "blockquote" => $common, "strong" => $common, "b" => $common, "em" => $common, "i" => $common, "br" => [],
            "a" => array_merge( $common, [ "href" => true, "target" => true, "rel" => true, "title" => true ] ),
            "img" => array_merge( $common, [ "src" => true, "alt" => true, "width" => true, "height" => true, "loading" => true, "decoding" => true, "srcset" => true, "sizes" => true ] ),
            "figure" => $common, "figcaption" => $common,
            "table" => $common, "thead" => $common, "tbody" => $common, "tr" => $common, "th" => array_merge( $common, [ "scope" => true, "colspan" => true, "rowspan" => true ] ), "td" => array_merge( $common, [ "colspan" => true, "rowspan" => true ] ),
        ];
    }

    private static function strip_empty_tags( string $html ): string {
        $pattern = "/<(p|h[1-6]|li|blockquote|figcaption|th|td)>\s*(?:&nbsp;|\s)*<\/\\1>/i";
        do {
            $before = $html;
            $html = preg_replace( $pattern, "", $html );
        } while ( $before !== $html );
        return $html;
    }
}
