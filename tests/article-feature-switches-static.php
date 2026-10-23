<?php
/**
 * "What to know" and FAQs each own one field group and one switch. When a
 * switch is off the group is not registered and nothing reaches the page.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
$read = static fn( string $path ): string => (string) file_get_contents( $root . "/" . $path );

$fields = $read( "src/Content/AcfFields.php" );
$types = $read( "src/Content/PublicationContentTypes.php" );
$shortcodes = $read( "src/Content/Shortcodes.php" );
$schema = $read( "src/StructuredData/SchemaManager.php" );
$styles = $read( "src/Content/ArticleStyles.php" );
$toc = $read( "src/Content/TableOfContents.php" );
$checklist = $read( "src/Content/GoingLiveChecklist.php" );

$checks = [
    "Summary has its own field group behind its switch." => str_contains( $fields, 'self::POST_SUMMARY_GROUP_KEY, "What to Know"' )
        && str_contains( $fields, 'if ( ! Settings::bool( "post_summary_acf_enabled" ) ) {' ),
    "FAQs have their own field group behind their switch." => str_contains( $fields, 'self::POST_FAQS_GROUP_KEY, "FAQs"' )
        && (bool) preg_match( '/function post_faqs_group\(\): array \{\s+if \( ! Settings::bool\( "post_faqs_acf_enabled" \) \) \{\s+return \[\];/', $fields ),
    "The shared Article Fields structure is gone." => ! str_contains( $types, "'article-fields'" ) && ! str_contains( $fields, "function article_fields_group" ),
    "What to Know keeps the legacy key so it replaces the site Post - Header group." => str_contains( $fields, "POST_SUMMARY_GROUP_KEY = 'group_64a7290b61191'" ) && str_contains( $fields, "POST_FAQS_GROUP_KEY = 'group_smpi_post_faqs'" ),
    "With the summary off the group stays registered but inactive and empty." => str_contains( $fields, 'return [ "active" => false ] + self::article_group( self::POST_SUMMARY_GROUP_KEY, "What to Know", [], 0 );' ),
    "Both groups are registered as separate field structures." => str_contains( $types, "'post-summary'" ) && str_contains( $types, "'post-faqs'" ),
    "Field keys are unchanged so saved values still load." => str_contains( $fields, '"field_65ab7ba0e849b"' ) && str_contains( $fields, '"field_smpi_post_faq_items"' ),
    "Summary shortcode is silent when the feature is off." => (bool) preg_match( '/"smp_post_summary" \);\s+if \( ! Settings::bool\( "post_summary_acf_enabled" \) \) \{\s+return "";/', $shortcodes ),
    "FAQ shortcode is silent when the feature is off." => (bool) preg_match( '/"smp_post_faqs" \);\s+if \( ! Settings::bool\( "post_faqs_acf_enabled" \) \) \{\s+return "";/', $shortcodes ),
    "Generic post field shortcode honors both switches." => str_contains( $shortcodes, 'Settings::bool( "post_summary" === $field ? "post_summary_acf_enabled" : "post_faqs_acf_enabled" )' ),
    "FAQ rows and FAQPage schema stop when FAQs are off." => (bool) preg_match( '/function faq_rows_for_post\([^)]*\): array \{.*?if \( ! Settings::bool\( "post_faqs_acf_enabled" \) \) \{\s+return \[\];/s', $schema ),
    "A summary of blank lines draws no block." => str_contains( $styles, 'if ( ! self::has_visible_text( $html ) )' ) && str_contains( $styles, '"\xC2\xA0"' ),
    "The contents list skips a blank summary." => str_contains( $toc, "ArticleStyles::has_visible_text( \$summary )" ),
    "The go-live checklist only asks for a summary when the feature is on." => str_contains( $checklist, 'if ( Settings::bool( "post_summary_acf_enabled" ) ) $items[] = [ "key" => "summary"' ),
];

$failed = 0;
foreach ( $checks as $label => $passed ) {
    echo ( $passed ? "PASS" : "FAIL" ) . " - " . $label . PHP_EOL;
    $failed += $passed ? 0 : 1;
}
exit( $failed ? 1 : 0 );
