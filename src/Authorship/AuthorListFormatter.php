<?php
namespace smp_publication_integration\Authorship;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/** Formats a post's author view models as plain text, links, lines, or a list. */
final class AuthorListFormatter {
    /**
     * @param array<int,array<string,mixed>> $authors view models from AuthorRecord::to_array()
     * @param array{field?:string,format?:string,separator?:string,class?:string,link_class?:string} $args
     */
    public static function render( array $authors, array $args = [] ): string {
        $field = sanitize_key( (string) ( $args["field"] ?? "name" ) );
        $format = sanitize_key( (string) ( $args["format"] ?? "plain" ) );
        $separator = (string) ( $args["separator"] ?? ", " );
        $class = sanitize_html_class( (string) ( $args["class"] ?? "smpi-post-authors" ) );
        $link_class = sanitize_html_class( (string) ( $args["link_class"] ?? "" ) );

        $rows = [];
        foreach ( $authors as $author ) {
            $value = trim( self::value( $author, $field ) );
            if ( "" !== $value ) {
                $rows[] = [ $value, (string) ( $author["url"] ?? "" ) ];
            }
        }
        if ( empty( $rows ) ) {
            return "";
        }

        if ( "links" === $format ) {
            $links = array_map(
                static fn( array $row ): string => '<a' . ( "" !== $link_class ? ' class="' . esc_attr( $link_class ) . '"' : "" ) . ' href="' . esc_url( $row[1] ) . '">' . esc_html( $row[0] ) . "</a>",
                $rows
            );
            return implode( esc_html( $separator ), $links );
        }
        $values = array_column( $rows, 0 );
        if ( in_array( $format, [ "lines", "line" ], true ) ) {
            return implode( "<br>\n", array_map( "esc_html", $values ) );
        }
        if ( in_array( $format, [ "list", "ul" ], true ) ) {
            return '<ul class="' . esc_attr( $class ) . '">' . implode( "", array_map( static fn( string $v ): string => "<li>" . esc_html( $v ) . "</li>", $values ) ) . "</ul>";
        }
        return esc_html( implode( $separator, $values ) );
    }

    public static function value( array $author, string $field ): string {
        if ( in_array( $field, [ "", "name", "display_name" ], true ) ) {
            return (string) ( $author["name"] ?? "" );
        }
        if ( in_array( $field, [ "id", "ids", "user_id" ], true ) ) {
            return (string) ( $author["id"] ?? "" );
        }
        if ( in_array( $field, [ "url", "author_url" ], true ) ) {
            return (string) ( $author["url"] ?? "" );
        }
        if ( "email" === $field ) {
            return (string) ( $author["email"] ?? "" );
        }
        $fields = isset( $author["fields"] ) && is_array( $author["fields"] ) ? $author["fields"] : [];
        return wp_strip_all_tags( (string) ( $fields[ $field ] ?? "" ) );
    }
}
