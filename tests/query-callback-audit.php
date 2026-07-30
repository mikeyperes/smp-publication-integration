<?php

declare(strict_types=1);

$root = dirname( __DIR__ );
$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator( $root . "/src", FilesystemIterator::SKIP_DOTS )
);

foreach ( $iterator as $file ) {
    if ( $file instanceof SplFileInfo && "php" === strtolower( $file->getExtension() ) ) {
        $files[] = $file->getPathname();
    }
}

sort( $files );
$registrations = [];

foreach ( $files as $file ) {
    $source = (string) file_get_contents( $file );
    foreach ( query_audit_registered_callbacks( $source, $file ) as $registration ) {
        $registrations[] = [
            "file" => $file,
            "hook" => $registration["hook"],
            "callback" => $registration["callback"],
            "source" => $source,
        ];
    }
}

function query_audit_fail( string $message ): never {
    fwrite( STDERR, "FAIL: {$message}\n" );
    exit( 1 );
}

function query_audit_next_code_index( array $tokens, int $index ): int {
    $count = count( $tokens );
    for ( ; $index < $count; $index++ ) {
        $token = $tokens[ $index ];
        if ( is_array( $token ) && in_array( $token[0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) ) {
            continue;
        }
        return $index;
    }
    return $count;
}

function query_audit_literal_value( array|string $token ): ?string {
    if ( ! is_array( $token ) || T_CONSTANT_ENCAPSED_STRING !== $token[0] ) {
        return null;
    }

    $quote = $token[1][0] ?? "";
    $value = substr( $token[1], 1, -1 );

    return "\"" === $quote ? stripcslashes( $value ) : str_replace( [ "\\\\", "\\'" ], [ "\\", "'" ], $value );
}

function query_audit_registered_callbacks( string $source, string $file ): array {
    $tokens = token_get_all( $source );
    $count = count( $tokens );
    $callbacks = [];

    for ( $index = 0; $index < $count; $index++ ) {
        $token = $tokens[ $index ];
        if ( ! is_array( $token )
            || ! in_array( $token[0], [ T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE ], true )
            || ! in_array( strtolower( ltrim( $token[1], "\\" ) ), [ "add_action", "add_filter" ], true )
        ) {
            continue;
        }
        $previous = query_audit_previous_code_token( $tokens, $index );
        if ( is_array( $previous ) && in_array( $previous[0], [ T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON ], true ) ) {
            continue;
        }

        $open_index = query_audit_next_code_index( $tokens, $index + 1 );
        if ( $open_index >= $count || "(" !== $tokens[ $open_index ] ) {
            continue;
        }
        $hook_index = query_audit_next_code_index( $tokens, $open_index + 1 );
        $hook = $hook_index < $count ? query_audit_literal_value( $tokens[ $hook_index ] ) : null;
        if ( ! is_string( $hook ) || ( "pre_get_posts" !== $hook && ! str_starts_with( $hook, "posts_" ) ) ) {
            continue;
        }

        $comma_index = query_audit_next_code_index( $tokens, $hook_index + 1 );
        $array_index = query_audit_next_code_index( $tokens, $comma_index + 1 );
        $object_index = query_audit_next_code_index( $tokens, $array_index + 1 );
        $callback_comma_index = query_audit_next_code_index( $tokens, $object_index + 1 );
        $method_index = query_audit_next_code_index( $tokens, $callback_comma_index + 1 );
        $close_index = query_audit_next_code_index( $tokens, $method_index + 1 );
        $method = $method_index < $count ? query_audit_literal_value( $tokens[ $method_index ] ) : null;
        $auditable = $comma_index < $count && "," === $tokens[ $comma_index ]
            && $array_index < $count && "[" === $tokens[ $array_index ]
            && $object_index < $count && is_array( $tokens[ $object_index ] ) && T_VARIABLE === $tokens[ $object_index ][0] && '$this' === $tokens[ $object_index ][1]
            && $callback_comma_index < $count && "," === $tokens[ $callback_comma_index ]
            && is_string( $method ) && "" !== $method
            && $close_index < $count && "]" === $tokens[ $close_index ];

        if ( ! $auditable ) {
            query_audit_fail( str_replace( dirname( __DIR__ ) . "/", "", $file ) . " contains an unauditable {$hook} callback." );
        }

        $callbacks[] = [ "hook" => $hook, "callback" => $method ];
    }

    return $callbacks;
}

function query_audit_method_tokens( string $source, string $method ): array {
    $tokens = token_get_all( $source );
    $count = count( $tokens );

    for ( $index = 0; $index < $count; $index++ ) {
        if ( ! is_array( $tokens[ $index ] ) || T_FUNCTION !== $tokens[ $index ][0] ) {
            continue;
        }

        $name_index = $index + 1;
        while ( $name_index < $count ) {
            $candidate = $tokens[ $name_index ];
            if ( ( is_array( $candidate ) && T_WHITESPACE === $candidate[0] ) || "&" === $candidate ) {
                $name_index++;
                continue;
            }
            break;
        }
        if ( $name_index >= $count || ! is_array( $tokens[ $name_index ] ) || T_STRING !== $tokens[ $name_index ][0] || $method !== $tokens[ $name_index ][1] ) {
            continue;
        }

        while ( $name_index < $count && "{" !== $tokens[ $name_index ] ) {
            $name_index++;
        }
        if ( $name_index >= $count ) {
            return [];
        }

        $body = [];
        $depth = 0;
        for ( $body_index = $name_index; $body_index < $count; $body_index++ ) {
            $token = $tokens[ $body_index ];
            if ( "{" === $token ) {
                $depth++;
            } elseif ( "}" === $token ) {
                $depth--;
            }
            $body[] = $token;
            if ( 0 === $depth ) {
                return $body;
            }
        }
    }

    return [];
}

function query_audit_token_text( array $tokens ): string {
    $text = "";
    foreach ( $tokens as $token ) {
        $text .= is_array( $token ) ? $token[1] : $token;
    }
    return $text;
}

function query_audit_previous_code_token( array $tokens, int $index ) {
    for ( $index--; $index >= 0; $index-- ) {
        $token = $tokens[ $index ];
        if ( is_array( $token ) && in_array( $token[0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) ) {
            continue;
        }
        return $token;
    }
    return null;
}

$expected = [
    "pre_get_posts Authorship/AuthorQueryIntegration.php::prepare_author_query",
    "posts_clauses Authorship/AuthorQueryIntegration.php::filter_author_clauses",
    "pre_get_posts Content/FeaturedImageRequirements.php::filter_home_queries",
    "posts_where Content/FeaturedImageRequirements.php::filter_thumbnail_where",
    "pre_get_posts Content/Visibility.php::filter_queries",
    "posts_where Content/Visibility.php::filter_press_release_where",
];
$inventory = [];
$conditional_tags = [
    "is_admin",
    "is_archive",
    "is_author",
    "is_category",
    "is_feed",
    "is_front_page",
    "is_home",
    "is_page",
    "is_search",
    "is_singular",
    "is_tag",
];

foreach ( $registrations as $registration ) {
    $relative = str_replace( $root . "/src/", "", $registration["file"] );
    $identity = $registration["hook"] . " " . $relative . "::" . $registration["callback"];
    $inventory[] = $identity;
    $body_tokens = query_audit_method_tokens( $registration["source"], $registration["callback"] );
    if ( [] === $body_tokens ) {
        query_audit_fail( "Could not inspect {$identity}." );
    }

    $body = query_audit_token_text( $body_tokens );
    if ( ! str_contains( $body, "QueryEligibility::allows_" ) ) {
        query_audit_fail( "{$identity} does not use the shared QueryEligibility guard." );
    }
    if ( str_starts_with( $registration["hook"], "posts_" ) && ! str_contains( $body, "->get(" ) ) {
        query_audit_fail( "{$identity} does not independently inspect a query marker before changing SQL." );
    }

    foreach ( $body_tokens as $index => $token ) {
        if ( ! is_array( $token ) || T_STRING !== $token[0] || ! in_array( strtolower( $token[1] ), $conditional_tags, true ) ) {
            continue;
        }
        $previous = query_audit_previous_code_token( $body_tokens, $index );
        $object_call = is_array( $previous ) && in_array( $previous[0], [ T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON ], true );
        if ( ! $object_call ) {
            query_audit_fail( "{$identity} calls global {$token[1]}() instead of using WP_Query flags or the shared guard." );
        }
    }
}

sort( $inventory );
sort( $expected );
foreach ( $expected as $identity ) {
    if ( ! in_array( $identity, $inventory, true ) ) {
        query_audit_fail( "Expected query callback {$identity} is no longer registered." );
    }
}

echo "PASS: Audited pre_get_posts and posts_* callbacks: " . implode( ", ", $inventory ) . ".\n";
