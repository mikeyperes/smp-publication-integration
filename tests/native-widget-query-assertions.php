<?php

declare( strict_types=1 );

// Shared assertions included by publication-manifest-unit.php; run directly, delegate to it.
if ( ! class_exists( 'SmpiFixtureDocuments' ) ) {
    require __DIR__ . '/publication-manifest-unit.php';
    return;
}

use SMP\PublicationIntegration\PublicationManifest\HomepageCollector;
use SMP\PublicationIntegration\PublicationManifest\NativeWidgetQueryCollector;

$GLOBALS['smpi_native_executions'] = 0;
$GLOBALS['post'] = (object) [ 'ID' => 777 ];
$original_post = $GLOBALS['post'];
\Elementor\Plugin::$instance = (object) [
    'documents' => new SmpiFixtureDocuments(),
    'elements_manager' => new SmpiFixtureElements(),
    'db' => new SmpiFixtureElementorDb(),
];
$native = new NativeWidgetQueryCollector();
$node = static fn( string $type, array $settings ): array => [ 'id' => 'native-query', 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings ];

$one = $native->collect( $node( 'loop-grid', [ 'post_query_posts_per_page' => 1 ] ), 42 );
expect_manifest( [ 8 ] === $one['category_ids'] && 1 === $GLOBALS['smpi_last_native_query']->get( 'posts_per_page' ), 'A one-card widget must not expand to the manifest maximum.' );
expect_manifest( null === $one['warning'] && $GLOBALS['smpi_internal_lookup_unchanged'], 'Unrelated internal template queries must remain unchanged and must not falsely mark article evidence truncated.' );
expect_manifest( 'publish' === $GLOBALS['smpi_last_native_query']->get( 'post_status' ), 'Public manifest queries must request only published posts.' );
expect_manifest( $original_post === $GLOBALS['post'] && 'outer' === \Elementor\Plugin::$instance->documents->current, 'Elementor post and document context must be restored.' );
expect_manifest( [ 777 ] === \ElementorPro\Modules\QueryControl\Module::$displayed_ids && ! isset( $GLOBALS['smpi_manifest_actions']['pre_get_posts'] ), 'Elementor avoid list and query guard must be restored.' );
expect_manifest( [] === $GLOBALS['smpi_manifest_filters'], 'Provider query markers must be removed after collection.' );

$public = $native->collect( $node( 'posts', [ 'post_query_posts_per_page' => 6, 'fixture_posts' => [ 101, 103, 104, 105 ] ] ), 42 );
expect_manifest( [ 8 ] === $public['category_ids'], 'Draft/private posts and nonpublic post types must not contribute categories.' );
$clamped = $native->collect( $node( 'loop-grid', [ 'fixture_hook_limit' => 100, 'fixture_posts' => range( 1000, 1050 ) ] ), 42 );
expect_manifest( 51 === $GLOBALS['smpi_last_native_query']->get( 'posts_per_page' ) && 'native_query_results_truncated' === $clamped['warning']['code'], 'A custom-hook oversized query must use one bounded overflow sentinel and remain partial only when more than fifty results exist.' );
$before_directory_grid = $GLOBALS['smpi_native_executions'];
$directory_grid = $native->collect( $node( 'loop-grid', [ 'posts_per_page' => -1, 'post_query_post_type' => 'staff-profile' ] ), 42 );
expect_manifest( $directory_grid['resolved'] && [] === $directory_grid['category_ids'] && null === $directory_grid['warning'] && $before_directory_grid === $GLOBALS['smpi_native_executions'], 'A statically bound non-category directory grid must resolve empty before its unlimited display count can make the article manifest partial.' );
$GLOBALS['smpi_manifest_object_taxonomies']['team-member'] = [ 'category' ];
$category_directory = $native->collect( $node( 'loop-grid', [ 'posts_per_page' => -1, 'post_query_post_type' => 'team-member', 'fixture_posts' => range( 2000, 2023 ) ] ), 42 );
expect_manifest( $category_directory['resolved'] && 24 === $category_directory['post_count'] && null === $category_directory['warning'] && $before_directory_grid + 1 === $GLOBALS['smpi_native_executions'], 'An unlimited category-capable directory grid with fewer than fifty results must prove completeness through the bounded overflow probe.' );
unset( $GLOBALS['smpi_manifest_object_taxonomies']['team-member'] );
$failed = $native->collect( $node( 'loop-grid', [ 'fixture_throw' => true ] ), 42 );
expect_manifest( ! $failed['resolved'] && ! isset( $GLOBALS['smpi_manifest_actions']['pre_get_posts'] ) && $original_post === $GLOBALS['post'], 'Failed native queries must restore context and remove their guard.' );
$avoid = $native->collect( $node( 'loop-grid', [ 'post_query_avoid_duplicates' => 'yes' ] ), 42 );
expect_manifest( ! $avoid['resolved'] && 'elementor_previous_results_required' === $avoid['warning']['code'], 'Unreproduced prior-widget exclusions must not be declared complete.' );

$unsupported = $native->collect( $node( 'jet-listing-grid', [ 'lisitng_id' => 500 ] ), 42 );
expect_manifest( ! $unsupported['resolved'] && 'native_query_provider_unsupported' === $unsupported['warning']['code'], 'Widgets other than Elementor posts and loops (such as removed JetEngine listings) have no query adapter.' );

$budget = new NativeWidgetQueryCollector( null, 1 );
$budget->collect( $node( 'posts', [] ), 42 );
$before_budget = $GLOBALS['smpi_native_executions'];
$exhausted = $budget->collect( $node( 'posts', [] ), 42 );
expect_manifest( 'native_query_budget_exhausted' === $exhausted['warning']['code'] && $before_budget === $GLOBALS['smpi_native_executions'], 'Widget query budget must stop execution, not only truncate results afterwards.' );

$actual_homepage = ( new HomepageCollector() )->collect_from_elements( 42, [ $node( 'loop-grid', [ 'post_query_query_id' => 'homepage_custom_query' ] ) ] );
expect_manifest( 'complete' === $actual_homepage['collection_status'] && [] === $actual_homepage['collection_warnings'], 'Supported native results must resolve static custom-query warnings.' );
expect_manifest( [ 8, 6271 ] === array_column( $actual_homepage['campaign_categories'], 'id' ), 'Native homepage evidence must include returned categories and exclude reserved Digital Magazine.' );
expect_manifest( 'native_query_results' === $actual_homepage['query_widgets'][0]['category_source'], 'Native category evidence must declare its provenance.' );
