<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\Fields;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class Shortcodes {
    private const POST_ACF_FIELDS = [ "post_summary", "post_faq_items" ];

    public function register(): void {
        add_action( "init", [ $this, "register_shortcodes" ] );
    }

    public function register_shortcodes(): void {
        foreach ( self::shortcodes() as $tag => $callback ) {
            add_shortcode( $tag, [ $this, $callback ] );
        }
    }

    public static function shortcodes(): array {
        return [
            "smp_publication_field" => "render_field",
            "smp_publication_mission_statement" => "render_mission_statement",
            "smp_publication_founders" => "render_founders",
            "smp_publication_user" => "render_publication_user",
            "smp_publication_profile" => "render_profile",
            "smp_publication_validate_schema" => "render_validate_schema",
            "smp_publication_page" => "render_page_assignment",
            "smp_publication_page_template" => "render_page_template",
            "smp_page_template" => "render_page_template",
            "smp_publication_debug_url" => "render_debug_url",
            "smp_post_acf" => "render_post_acf",
            "smp_post_summary" => "render_post_summary",
            "smp_post_faqs" => "render_post_faqs",
        ];
    }

    public function render_field( array $atts = [] ): string {
        $atts = shortcode_atts(
            [
                "field" => "",
                "format" => "html",
                "row" => "",
                "index" => "",
                "sub_field" => "",
                "separator" => ", ",
                "fallback" => "",
            ],
            $atts,
            "smp_publication_field"
        );
        $field = sanitize_key( (string) $atts["field"] );
        if ( "" === $field ) {
            return "";
        }

        $value = Fields::option( $field );
        $value = $this->extract_indexed_value( $value, (string) $atts["row"], (string) $atts["index"] );
        $value = $this->extract_sub_field( $value, sanitize_key( (string) $atts["sub_field"] ) );
        if ( $this->is_empty_value( $value ) && "" !== (string) $atts["fallback"] ) {
            $value = $this->fallback_value( sanitize_key( (string) $atts["fallback"] ) );
        }
        return $this->format_value( $value, sanitize_key( (string) $atts["format"] ), (string) $atts["separator"] );
    }

    public function render_post_acf( array $atts = [] ): string {
        $atts = shortcode_atts(
            [
                "field" => "",
                "post_id" => 0,
                "format" => "html",
                "separator" => ", ",
            ],
            $atts,
            "smp_post_acf"
        );
        $field = sanitize_key( (string) $atts["field"] );
        if ( ! in_array( $field, self::POST_ACF_FIELDS, true ) ) {
            return "";
        }
        $post_id = $this->resolve_post_id( (int) $atts["post_id"] );
        if ( ! $post_id ) {
            return "";
        }
        return $this->format_value( Fields::get( $post_id, $field ), sanitize_key( (string) $atts["format"] ), (string) $atts["separator"] );
    }

    public function render_post_summary( array $atts = [] ): string {
        $atts = shortcode_atts( [ "post_id" => 0, "format" => "html", "style" => "" ], $atts, "smp_post_summary" );
        $atts["field"] = "post_summary";
        $html = $this->render_post_acf( $atts );
        return "html" === sanitize_key( (string) $atts["format"] ) ? ArticleStyles::wrap_post_summary( $html, sanitize_key( (string) $atts["style"] ) ) : $html;
    }

    public function render_post_faqs( array $atts = [] ): string {
        $atts = shortcode_atts( [ "post_id" => 0, "format" => "html", "style" => "" ], $atts, "smp_post_faqs" );
        $post_id = $this->resolve_post_id( (int) $atts["post_id"] );
        if ( ! $post_id ) {
            return "";
        }

        $rows = Schema::faq_rows_for_post( $post_id, false );
        if ( ! $rows ) {
            return "";
        }

        $items = $this->normalize_post_faq_items( $rows );
        if ( empty( $items ) ) {
            return "";
        }

        if ( "text" === sanitize_key( (string) $atts["format"] ) ) {
            $text = [];
            foreach ( $items as $item ) {
                $text[] = "Q: " . $item["question"] . " A: " . wp_strip_all_tags( (string) $item["answer"] );
            }
            return esc_html( implode( " ", $text ) );
        }

        return ArticleStyles::wrap_post_faqs( $this->render_post_faq_items( $items ), sanitize_key( (string) $atts["style"] ) );
    }

    private function normalize_post_faq_items( array $rows ): array {
        $items = [];
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $question = trim( wp_strip_all_tags( (string) ( $row["question"] ?? "" ) ) );
            $answer = $this->sanitize_post_faq_answer( (string) ( $row["answer"] ?? "" ) );
            if ( "" === $question || "" === trim( wp_strip_all_tags( $answer ) ) ) {
                continue;
            }
            $items[] = [ "question" => $question, "answer" => $answer ];
        }
        return $items;
    }

    private function render_post_faq_items( array $items ): string {
        $html = "<ul class=\"smpi-post-faq-list\">";
        foreach ( $items as $item ) {
            $html .= "<li class=\"smpi-post-faq-item\"><h3 class=\"smpi-post-faq-question\">" . esc_html( (string) $item["question"] ) . "</h3><div class=\"smpi-post-faq-answer\">" . $item["answer"] . "</div></li>";
        }
        return $html . "</ul>";
    }

    private function sanitize_post_faq_answer( string $answer ): string {
        $answer = preg_replace( "#<style[^>]*>.*?</style>#is", "", $answer ) ?? $answer;
        $answer = preg_replace( "#<script[^>]*>.*?</script>#is", "", $answer ) ?? $answer;
        return wp_kses_post( wpautop( $answer ) );
    }

    public function render_mission_statement( array $atts = [] ): string {
        $mission = Fields::option( "mission_statement" );
        return $mission ? "<div class=\"smpi-publication-mission\">" . wp_kses_post( wpautop( (string) $mission ) ) . "</div>" : "";
    }

    public function render_founders( array $atts = [] ): string {
        if ( ! Settings::bool( "founders_enabled" ) ) {
            return "";
        }

        $items = array_merge(
            $this->founder_profile_items( Fields::option( "founders", [] ) ),
            $this->founder_user_items( Fields::option( "founder_users", [] ) )
        );

        return $items ? "<ul class=\"smpi-publication-founders\">" . implode( "", $items ) . "</ul>" : "";
    }

    public function render_publication_user( array $atts = [] ): string {
        $user_id = (int) Fields::option( "publication_user", Settings::get( "system_publication_user_id", 0 ) );
        $user = $user_id ? get_userdata( $user_id ) : false;
        return $user ? "<span class=\"smpi-publication-user\">" . esc_html( $user->display_name ) . "</span>" : "";
    }

    public function render_profile( array $atts = [] ): string {
        $website = Fields::option( "website", home_url( "/" ) );
        $summary = Fields::option( "summary" );
        $mission = Fields::option( "mission_statement" );
        $founders = $this->render_founders();

        ob_start();
        ?>
        <article class="smpi-publication-profile">
            <h2 class="smpi-publication-profile__title"><?php echo esc_html( get_bloginfo( "name" ) ); ?></h2>
            <?php if ( $website ) : ?><p><a href="<?php echo esc_url( (string) $website ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) $website ); ?></a></p><?php endif; ?>
            <?php if ( $summary ) : ?><div><?php echo wp_kses_post( wpautop( (string) $summary ) ); ?></div><?php endif; ?>
            <?php if ( $mission ) : ?><h3>Mission Statement</h3><div><?php echo wp_kses_post( wpautop( (string) $mission ) ); ?></div><?php endif; ?>
            <?php if ( $founders ) : ?><h3>Founders</h3><?php echo $founders; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
        </article>
        <?php
        return trim( (string) ob_get_clean() );
    }

    public function render_validate_schema( array $atts = [] ): string {
        return sprintf( "<a class=\"smpi-publication-schema-validator\" href=\"%s\" target=\"_blank\" rel=\"noopener noreferrer\">%s</a>", esc_url( "https://validator.schema.org/#url=" . rawurlencode( home_url( "/" ) ) ), esc_html( "Validate homepage publication schema" ) );
    }

    public function render_page_assignment( array $atts = [] ): string {
        $atts = shortcode_atts( [ "type" => "", "mode" => "link" ], $atts, "smp_publication_page" );
        $type = sanitize_key( (string) $atts["type"] );
        $page_id = Settings::page_assignment_id( $type );
        if ( ! $page_id ) {
            return "";
        }
        $mode = sanitize_key( (string) $atts["mode"] );
        if ( "content" === $mode ) {
            $post = get_post( $page_id );
            return $post ? apply_filters( "the_content", $post->post_content ) : "";
        }
        if ( "id" === $mode ) {
            return esc_html( (string) $page_id );
        }
        if ( "title" === $mode ) {
            return esc_html( get_the_title( $page_id ) );
        }
        $url = Settings::page_slug_url( $page_id );
        if ( "url" === $mode ) {
            return esc_url( $url );
        }
        return sprintf( "<a href=\"%s\">%s</a>", esc_url( $url ), esc_html( get_the_title( $page_id ) ) );
    }


    public function render_page_template( array $atts = [] ): string {
        $atts = shortcode_atts( [ "type" => "" ], $atts, "smp_publication_page_template" );
        $type = sanitize_key( (string) $atts["type"] );
        if ( "" === $type ) {
            return "";
        }
        $definition = $this->publication_page_template_definition( $type );
        if ( empty( $definition ) ) {
            return "";
        }
        return wp_kses_post( do_shortcode( $this->publication_page_template_html( $type, $definition ) ) );
    }

    private function publication_page_template_definition( string $type ): array {
        $types = Settings::page_types();
        $label = isset( $types[ $type ]["label"] ) ? (string) $types[ $type ]["label"] : ucwords( str_replace( "_", " ", $type ) );
        $description = isset( $types[ $type ]["description"] ) ? (string) $types[ $type ]["description"] : "Public information page for this publication.";
        $sections = [
            "Publication Details" => [
                "Publication: [smp_publication_field field=legal_name format=text fallback=site_name]",
                "Website: [smp_publication_field field=website format=url fallback=home_url]",
                "Contact: [smp_publication_field field=contact_email format=email fallback=admin_email]",
            ],
            "Page Purpose" => [
                $description,
                "Use shortcode-backed publication fields for names, URLs, contacts, policies, founders, and profile links instead of hardcoded values.",
                "Review this page before publishing and keep it aligned with the live site structure.",
            ],
        ];

        if ( in_array( $type, [ "about_publication", "mission_statement", "mission_coverage_priorities_policy" ], true ) ) {
            $sections["Reader Promise"] = [
                "Mission: [smp_publication_field field=mission_statement format=html]",
                "Coverage focus: [smp_publication_field field=knows_about format=text]",
                "Explain who the outlet serves, what it covers, and how editorial decisions support that mission.",
            ];
        } elseif ( in_array( $type, [ "founder_about", "founders", "founding_date" ], true ) ) {
            $sections["Founder And History"] = [
                "Founding date: [smp_publication_field field=founding_date format=text]",
                "Founder records: [smp_publication_founders]",
                "Use approved profile records and verified public facts; do not expose private personal information.",
            ];
        } elseif ( in_array( $type, [ "writers", "contributors", "staff", "executive_team", "team", "masthead" ], true ) ) {
            $sections["People And Accountability"] = [
                "Group public people by role, responsibility, and profile link where available.",
                "Separate editorial accountability from advertising, sales, operations, and ownership roles.",
                "Route profile corrections through [smp_publication_page type=contact mode=link].",
            ];
        } elseif ( "become_contributor" === $type ) {
            $sections["Contributor Guidelines"] = [
                "A [smp_publication_field field=legal_name format=text fallback=site_name] piece should be original, authentic, useful, and aligned with the publication mission.",
                "Writers should disclose conflicts, prior publication, paid relationships, embargoes, and time-sensitive context.",
                "We welcome informed voices from varied backgrounds when the work fits our audience and editorial standards.",
            ];
            $sections["A Strong Pitch Should Include"] = [
                "A concise summary of the article idea and why it matters now.",
                "The expertise, reporting access, or lived experience behind the pitch.",
                "What readers will gain, a rough structure, and the proposed beginning and ending.",
                "Prior writing links when available and a clear subject line.",
            ];
            $sections["How To Pitch"] = [
                "Email pitches to [smp_publication_field field=contact_email format=email fallback=admin_email].",
                "If a draft exists, paste it in the message body instead of relying only on attachments.",
                "Submissions are reviewed for fit, originality, clarity, sourcing, and disclosure before publication.",
            ];
        } elseif ( in_array( $type, [ "submit_press_release", "press_releases" ], true ) ) {
            $sections["Press Release Standards"] = [
                "Include release text, company boilerplate, contact person, date, location, source links, and embargo details.",
                "Disclose paid relationships, affiliate relationships, sponsored placement requests, and material conflicts.",
                "Press releases may be edited for clarity, formatting, disclosure, and compliance; publication is not guaranteed.",
            ];
        } elseif ( "brand_assets" === $type ) {
            $sections["Brand Asset Rules"] = [
                "Use approved logos, marks, screenshots, and gallery assets without alteration unless written permission is granted.",
                "Do not imply endorsement, sponsorship, partnership, or editorial approval without written approval.",
                "Media and brand requests should use the approved publication contact path.",
            ];
        } elseif ( "dmca" === $type ) {
            $sections["Notice Requirements"] = [
                "Identify the copyrighted work and the exact URL or material at issue.",
                "Provide contact information, a good-faith statement, an accuracy statement, and a physical or electronic signature.",
                "Send notices through [smp_publication_page type=contact mode=link] or the approved publication email.",
            ];
        } elseif ( in_array( $type, [ "terms", "privacy", "contact", "faqs", "headquarters" ], true ) ) {
            $sections["Site Information"] = [
                "Use the HWS Base Tools page assignment for this shared site page when available.",
                "Keep legal, privacy, location, support, and contact details aligned with actual site operations.",
                "Do not publish private staff data, private addresses, or internal-only workflows.",
            ];
        } elseif ( in_array( $type, [ "editorial_guidelines", "editorial_policy", "publishing_principles", "verification_fact_checking_policy", "corrections_policy", "ethics_policy", "diversity_policy", "diversity_staffing_report", "no_bylines_policy", "unnamed_sources_policy", "actionable_feedback_policy", "ownership_funding", "parent_organization" ], true ) ) {
            $sections["Trust And Editorial Standards"] = [
                "Explain accuracy, sourcing, labels, corrections, conflicts, diversity, feedback, and editorial independence in plain language.",
                "Keep sponsored, affiliate, contributed, and press release material clearly labeled and separate from editorial decisions.",
                "Use this page as the canonical policy URL for connected NewsMediaOrganization schema fields.",
            ];
        } elseif ( in_array( $type, [ "advertise", "advertise_with_us" ], true ) ) {
            $sections["Advertising And Partnerships"] = [
                "Describe placements, sponsorships, newsletters, branded content, events, or partnership options without hardcoded pricing.",
                "Require clear disclosure for sponsored, native, affiliate, or partner content.",
                "State that paid placements do not control independent editorial decisions.",
            ];
        } elseif ( "accessibility" === $type ) {
            $sections["Accessibility Commitment"] = [
                "Aim for content that is perceivable, operable, understandable, and robust for a broad range of readers.",
                "Identify known limitations and the remediation process as the site evolves.",
                "Report accessibility barriers through [smp_publication_page type=contact mode=link].",
            ];
        }

        return [ "title" => $label, "intro" => $description, "sections" => $sections ];
    }

    private function publication_page_template_html( string $type, array $definition ): string {
        $html = "<article>";
        $html .= "<h2>" . esc_html( (string) $definition["title"] ) . "</h2>";
        $html .= "<p>" . esc_html( (string) $definition["intro"] ) . "</p>";
        foreach ( (array) $definition["sections"] as $heading => $items ) {
            $html .= "<h3>" . esc_html( (string) $heading ) . "</h3><ul>";
            foreach ( (array) $items as $item ) {
                $html .= "<li>" . wp_kses_post( (string) $item ) . "</li>";
            }
            $html .= "</ul>";
        }
        if ( "brand_assets" === $type ) {
            $html .= "<h3>Approved Brand Assets</h3>[smp_publication_field field=brand_assets format=gallery]";
        }
        $html .= "</article>";
        return $html;
    }

    private function is_empty_value( $value ): bool {
        return null === $value || false === $value || "" === $value || ( is_array( $value ) && empty( $value ) );
    }

    private function fallback_value( string $fallback ) {
        if ( "site_name" === $fallback || "publication_name" === $fallback ) {
            return get_bloginfo( "name" );
        }
        if ( "home_url" === $fallback ) {
            return home_url( "/" );
        }
        if ( "site_url" === $fallback ) {
            return site_url( "/" );
        }
        if ( "admin_email" === $fallback || "contact_email" === $fallback ) {
            return get_option( "admin_email" );
        }
        return "";
    }

    private function format_gallery( $value ): string {
        $items = is_array( $value ) ? $value : [ $value ];
        $html = "";
        $quote = chr( 34 );
        foreach ( $items as $item ) {
            $id = 0;
            $url = "";
            $alt = "";
            if ( is_array( $item ) ) {
                $id = absint( $item["ID"] ?? $item["id"] ?? 0 );
                $url = isset( $item["url"] ) ? (string) $item["url"] : "";
                $alt = isset( $item["alt"] ) ? (string) $item["alt"] : (string) ( $item["title"] ?? "" );
            } elseif ( is_object( $item ) ) {
                $id = absint( $item->ID ?? $item->id ?? 0 );
                $url = isset( $item->url ) ? (string) $item->url : "";
                $alt = isset( $item->alt ) ? (string) $item->alt : (string) ( $item->title ?? "" );
            } elseif ( is_numeric( $item ) ) {
                $id = absint( $item );
            } elseif ( is_string( $item ) ) {
                $url = $item;
            }
            if ( $id ) {
                $image = wp_get_attachment_image( $id, "medium", false, [ "loading" => "lazy" ] );
                if ( $image ) {
                    $html .= "<figure>" . $image . "</figure>";
                    continue;
                }
            }
            if ( "" !== $url ) {
                $html .= "<figure><img src=" . $quote . esc_url( $url ) . $quote . " alt=" . $quote . esc_attr( $alt ) . $quote . " loading=" . $quote . "lazy" . $quote . "></figure>";
            }
        }
        return "" !== $html ? "<div>" . $html . "</div>" : "";
    }

    public function render_debug_url(): string {
        return esc_url( rest_url( "smpi/v1/debug" ) );
    }


    private function resolve_post_id( int $explicit_post_id = 0 ): int {
        if ( $explicit_post_id > 0 && get_post( $explicit_post_id ) ) {
            return $explicit_post_id;
        }
        $post = get_post();
        return $post ? (int) $post->ID : 0;
    }

    private function extract_indexed_value( $value, string $row, string $index ) {
        if ( ! is_array( $value ) ) {
            return $value;
        }
        $offset = null;
        if ( "" !== $row && is_numeric( $row ) ) {
            $offset = max( 0, (int) $row - 1 );
        } elseif ( "" !== $index && is_numeric( $index ) ) {
            $offset = max( 0, (int) $index );
        }
        if ( null === $offset ) {
            return $value;
        }
        $values = array_values( $value );
        return $values[ $offset ] ?? "";
    }

    private function extract_sub_field( $value, string $sub_field ) {
        if ( "" === $sub_field || ! is_array( $value ) ) {
            return $value;
        }
        return array_key_exists( $sub_field, $value ) ? $value[ $sub_field ] : "";
    }

    private function format_value( $value, string $format = "html", string $separator = ", " ): string {
        if ( $this->is_empty_value( $value ) ) {
            return "";
        }
        if ( "json" === $format ) {
            return esc_html( wp_json_encode( $value ) );
        }
        if ( "gallery" === $format ) {
            return $this->format_gallery( $value );
        }
        if ( is_array( $value ) || is_object( $value ) ) {
            $value = $this->flatten_value( $value, $separator );
        }
        if ( is_bool( $value ) ) {
            return $value ? "1" : "";
        }
        $value = (string) $value;
        if ( "url" === $format ) {
            return esc_url( $value );
        }
        if ( "email" === $format ) {
            return esc_html( sanitize_email( $value ) );
        }
        if ( "text" === $format ) {
            return esc_html( wp_strip_all_tags( $value ) );
        }
        return wp_kses_post( $value );
    }

    private function flatten_value( $value, string $separator ): string {
        if ( is_object( $value ) ) {
            $value = get_object_vars( $value );
        }
        if ( ! is_array( $value ) ) {
            return is_scalar( $value ) ? trim( (string) $value ) : "";
        }
        foreach ( [ "url", "value", "label", "title", "name", "ID" ] as $key ) {
            if ( isset( $value[ $key ] ) && is_scalar( $value[ $key ] ) ) {
                return trim( (string) $value[ $key ] );
            }
        }
        $flat = [];
        array_walk_recursive(
            $value,
            static function ( $item ) use ( &$flat ): void {
                if ( is_scalar( $item ) && "" !== trim( (string) $item ) ) {
                    $flat[] = trim( (string) $item );
                }
            }
        );
        return implode( $separator, array_slice( array_unique( $flat ), 0, 20 ) );
    }

    private function founder_profile_items( $founders ): array {
        $founders = is_array( $founders ) ? $founders : [ $founders ];
        $items = [];
        foreach ( $founders as $founder ) {
            $founder_id = $this->founder_profile_id( $founder );
            if ( $founder_id && get_the_title( $founder_id ) ) {
                $items[] = sprintf( "<li><a href=\"%s\">%s</a></li>", esc_url( get_permalink( $founder_id ) ), esc_html( get_the_title( $founder_id ) ) );
            }
        }
        return $items;
    }

    private function founder_profile_id( $founder ): int {
        if ( is_array( $founder ) && isset( $founder["profile"] ) ) {
            $founder = $founder["profile"];
        }
        if ( is_object( $founder ) && isset( $founder->ID ) ) {
            return (int) $founder->ID;
        }
        return is_numeric( $founder ) ? (int) $founder : 0;
    }

    private function founder_user_items( $users ): array {
        $users = is_array( $users ) ? $users : [ $users ];
        $items = [];
        foreach ( $users as $user_id ) {
            $user_id = is_object( $user_id ) && isset( $user_id->ID ) ? (int) $user_id->ID : (int) $user_id;
            $user = $user_id ? get_user_by( "id", $user_id ) : false;
            if ( $user ) {
                $items[] = sprintf( "<li><a href=\"%s\">%s</a></li>", esc_url( get_author_posts_url( $user_id ) ), esc_html( $user->display_name ) );
            }
        }
        return $items;
    }
}
