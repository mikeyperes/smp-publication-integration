<?php
namespace smp_publication_integration\Content\Generation;

use Hexa\PluginCore\Fields\Field;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * Where each generated value lives on a post, and the one place that reads,
 * checks and saves it: excerpt is the native excerpt, summary the
 * post_summary field, FAQs the post_faq_items repeater (with FAQ schema on).
 */
final class GeneratedValueStore {
    public const TARGETS = [ "excerpt", "summary", "faqs" ];

    public static function is_target( string $target ): bool {
        return in_array( $target, self::TARGETS, true );
    }

    public static function label( string $target ): string {
        return [ "excerpt" => "Excerpt", "summary" => "Summary", "faqs" => "FAQs" ][ $target ] ?? ucfirst( $target );
    }

    /** Pulls the target's value out of a completed job's data. */
    public function extract( $data, string $target ) {
        if ( ! is_array( $data ) ) {
            return null;
        }
        if ( "faqs" === $target ) {
            return $this->normalize_faq_rows( $data );
        }
        $value = $data[ $target ] ?? ( "summary" === $target ? ( $data["post_summary"] ?? null ) : null );
        return is_string( $value ) ? trim( $value ) : null;
    }

    /**
     * Saves a generated value and returns what was stored.
     *
     * @return mixed|\WP_Error
     */
    public function save( int $post_id, string $target, $value ) {
        if ( "excerpt" === $target ) {
            $excerpt = sanitize_textarea_field( (string) $value );
            if ( "" === $excerpt ) {
                return new \WP_Error( "smpi_content_empty", "Publish returned an empty excerpt." );
            }
            $updated = wp_update_post( [ "ID" => $post_id, "post_excerpt" => $excerpt ], true );
            return is_wp_error( $updated ) ? $updated : $excerpt;
        }
        if ( "summary" === $target ) {
            $summary = wp_kses_post( (string) $value );
            if ( "" === trim( wp_strip_all_tags( $summary ) ) ) {
                return new \WP_Error( "smpi_content_empty", "Publish returned an empty summary." );
            }
            $this->update_field( "post_summary", "post_summary", $summary, $post_id );
            return $summary;
        }
        if ( "faqs" === $target ) {
            $rows = $this->normalize_faq_rows( $value );
            if ( empty( $rows ) ) {
                return new \WP_Error( "smpi_content_empty", "Publish returned no FAQ questions and answers." );
            }
            $this->update_field( "field_smpi_post_faq_items", "post_faq_items", $rows, $post_id );
            $this->update_field( "field_smpi_post_faq_schema_enabled", "post_faq_schema_enabled", 1, $post_id );
            return $rows;
        }
        return new \WP_Error( "smpi_content_target", "Unsupported generation target." );
    }

    /** The value currently saved for a target, in the shape the editor paints. */
    public function value( int $post_id, string $target ) {
        if ( "excerpt" === $target ) {
            return (string) get_post_field( "post_excerpt", $post_id );
        }
        if ( "summary" === $target ) {
            $value = self::field_value( "post_summary", $post_id );
            return is_string( $value ) ? $value : "";
        }
        if ( "faqs" === $target ) {
            return $this->faq_rows( $post_id );
        }
        return null;
    }

    public function is_filled( int $post_id, string $target ): bool {
        $value = $this->value( $post_id, $target );
        return is_array( $value ) ? ! empty( $value ) : "" !== trim( wp_strip_all_tags( (string) $value ) );
    }

    /** Complete FAQ rows (both question and answer) saved on a post. */
    public function faq_rows( int $post_id ): array {
        return $this->normalize_faq_rows( self::field_value( "post_faq_items", $post_id ) );
    }

    /** Reads a field through the Hexa field layer, falling back to post meta. */
    public static function field_value( string $field, int $post_id ) {
        if ( Field::available() ) {
            $value = Field::get( $field, $post_id );
            if ( null !== $value && false !== $value && "" !== $value ) {
                return $value;
            }
        }
        return get_post_meta( $post_id, $field, true );
    }

    private function update_field( string $field_key, string $meta_key, $value, int $post_id ): void {
        if ( Field::available() ) {
            Field::update( $field_key, $value, $post_id );
            return;
        }
        update_post_meta( $post_id, $meta_key, $value );
    }

    private function normalize_faq_rows( $value ): array {
        foreach ( [ "faqs", "post_faq_items", "faq_items" ] as $key ) {
            if ( is_array( $value ) && isset( $value[ $key ] ) && is_array( $value[ $key ] ) ) {
                $value = $value[ $key ];
                break;
            }
        }
        if ( ! is_array( $value ) ) {
            return [];
        }
        $rows = [];
        foreach ( $value as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $question = isset( $row["question"] ) ? sanitize_text_field( (string) $row["question"] ) : "";
            $answer = isset( $row["answer"] ) ? wp_kses_post( (string) $row["answer"] ) : "";
            if ( "" !== $question && "" !== trim( wp_strip_all_tags( $answer ) ) ) {
                $rows[] = [ "question" => $question, "answer" => $answer ];
            }
        }
        return $rows;
    }
}
