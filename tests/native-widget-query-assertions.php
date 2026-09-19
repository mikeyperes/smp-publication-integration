<?php

declare( strict_types=1 );

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
$GLOBALS['smpi_fixture_jet'] = (object) [ 'listings' => new SmpiFixtureJetListings() ];
$query_builder = new \Jet_Engine\Query_Builder\Manager();
$query_builder->listings = new SmpiFixtureJetQueryListings();
$query_builder->queries = [
    2 => new SmpiFixtureJetQuery( 2 ),
    3 => new SmpiFixtureJetQuery( 3, 'users' ),
    4 => new SmpiFixtureJetQuery( 4, 'posts', [ 'post_type' => 'runtime_macro' ] ),
];
\Jet_Engine\Query_Builder\Manager::$instance = $query_builder;
$original_jet_data = jet_engine()->listings->data;
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
$clamped = $native->collect( $node( 'loop-grid', [ 'fixture_hook_limit' => 100 ] ), 42 );
expect_manifest( 12 === $GLOBALS['smpi_last_native_query']->get( 'posts_per_page' ) && 'native_query_results_truncated' === $clamped['warning']['code'], 'A custom-hook oversized query must be bounded before execution and marked partial.' );
$before_directory_grid = $GLOBALS['smpi_native_executions'];
$directory_grid = $native->collect( $node( 'loop-grid', [ 'posts_per_page' => -1, 'post_query_post_type' => 'team-member' ] ), 42 );
expect_manifest( $directory_grid['resolved'] && [] === $directory_grid['category_ids'] && null === $directory_grid['warning'] && $before_directory_grid === $GLOBALS['smpi_native_executions'], 'A statically bound non-category directory grid must resolve empty before its unlimited display count can make the article manifest partial.' );
$failed = $native->collect( $node( 'loop-grid', [ 'fixture_throw' => true ] ), 42 );
expect_manifest( ! $failed['resolved'] && ! isset( $GLOBALS['smpi_manifest_actions']['pre_get_posts'] ) && $original_post === $GLOBALS['post'], 'Failed native queries must restore context and remove their guard.' );
$avoid = $native->collect( $node( 'loop-grid', [ 'post_query_avoid_duplicates' => 'yes' ] ), 42 );
expect_manifest( ! $avoid['resolved'] && 'elementor_previous_results_required' === $avoid['warning']['code'], 'Unreproduced prior-widget exclusions must not be declared complete.' );

$jet = $native->collect( $node( 'jet-listing-grid', [ 'lisitng_id' => 500, 'posts_num' => 1 ] ), 42 );
expect_manifest( [ 8 ] === $jet['category_ids'] && 'jet_engine' === $jet['provider'], 'Native Jet post results must yield category evidence.' );
expect_manifest( $original_jet_data === jet_engine()->listings->data && 987 === $original_jet_data->listing && $original_post === $GLOBALS['post'], 'Jet must restore the exact previous listing state and post.' );
$jet_query_builder = $native->collect( $node( 'jet-listing-grid', [ 'lisitng_id' => 501, 'custom_query' => 'yes', 'custom_query_id' => 2, 'posts_num' => 5 ] ), 42 );
expect_manifest( [ 8, 6271, 7548 ] === $jet_query_builder['category_ids'] && 'jet_engine_query_builder' === $jet_query_builder['provider'], 'A statically bound Query Builder posts query must yield bounded category evidence before the manifest policy layer removes reserved categories.' );
expect_manifest( 12 === $GLOBALS['smpi_last_native_query']->get( 'posts_per_page' ) && 'native_query_results_truncated' === $jet_query_builder['warning']['code'], 'Query Builder post results must be clamped through the exact-query guard and marked partial when oversized.' );
$before_non_post_query = $GLOBALS['smpi_native_executions'];
$jet_query_builder_users = $native->collect( $node( 'jet-listing-grid', [ 'lisitng_id' => 501, 'custom_query' => 'yes', 'custom_query_id' => 3 ] ), 42 );
expect_manifest( ! $jet_query_builder_users['resolved'] && 'jet_engine_query_builder_type_unsupported' === $jet_query_builder_users['warning']['code'] && $before_non_post_query === $GLOBALS['smpi_native_executions'], 'Non-post Query Builder sources must fail before execution.' );
$jet_query_builder_dynamic = $native->collect( $node( 'jet-listing-grid', [ 'lisitng_id' => 501, 'custom_query' => 'yes', 'custom_query_id' => 4 ] ), 42 );
expect_manifest( ! $jet_query_builder_dynamic['resolved'] && 'jet_engine_query_builder_dynamic_context_unsupported' === $jet_query_builder_dynamic['warning']['code'] && $before_non_post_query === $GLOBALS['smpi_native_executions'], 'Runtime-dependent Query Builder values must fail before execution.' );
$before_unsupported = $GLOBALS['smpi_native_executions'];
$jet_unsupported = $native->collect( $node( 'jet-listing-grid', [ 'lisitng_id' => 999 ] ), 42 );
expect_manifest( ! $jet_unsupported['resolved'] && $before_unsupported === $GLOBALS['smpi_native_executions'], 'Unsupported non-post Jet queries must not execute.' );

$budget = new NativeWidgetQueryCollector( null, 1 );
$budget->collect( $node( 'posts', [] ), 42 );
$before_budget = $GLOBALS['smpi_native_executions'];
$exhausted = $budget->collect( $node( 'posts', [] ), 42 );
expect_manifest( 'native_query_budget_exhausted' === $exhausted['warning']['code'] && $before_budget === $GLOBALS['smpi_native_executions'], 'Widget query budget must stop execution, not only truncate results afterwards.' );

$actual_homepage = ( new HomepageCollector() )->collect_from_elements( 42, [ $node( 'loop-grid', [ 'post_query_query_id' => 'homepage_custom_query' ] ) ] );
expect_manifest( 'complete' === $actual_homepage['collection_status'] && [] === $actual_homepage['collection_warnings'], 'Supported native results must resolve static custom-query warnings.' );
expect_manifest( [ 8, 6271 ] === array_column( $actual_homepage['campaign_categories'], 'id' ), 'Native homepage evidence must include returned categories and exclude reserved Digital Magazine.' );
expect_manifest( 'native_query_results' === $actual_homepage['query_widgets'][0]['category_source'], 'Native category evidence must declare its provenance.' );
