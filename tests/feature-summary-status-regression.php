<?php

declare(strict_types=1);

$dashboard = (string) file_get_contents( dirname( __DIR__ ) . '/src/Admin/Dashboard/DashboardController.php' );

function assert_feature_summary_status( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

assert_feature_summary_status(
    str_contains( $dashboard, 'smpi-setting smpi-feature-primary-toggle' ),
    'Every primary feature toggle must be identifiable separately from nested settings.'
);
assert_feature_summary_status(
    str_contains( $dashboard, 'function syncFeatureSummaryState(input,settings)' )
        && str_contains( $dashboard, 'details.smpi-feature-filter-item' )
        && str_contains( $dashboard, '.hpc-section-summary-side .hpc-pill' ),
    'Primary toggles must synchronize their enclosing Core summary pill.'
);
assert_feature_summary_status(
    str_contains( $dashboard, 'Object.prototype.hasOwnProperty.call(settings,key)' )
        && str_contains( $dashboard, 'if(ok)syncFeatureSummaryState(e,x.data&&x.data.settings?x.data.settings:null)' ),
    'The summary pill must use the successfully persisted AJAX response.'
);
assert_feature_summary_status(
    str_contains( $dashboard, 'pill.removeClass(`success warning dark`).addClass(enabled?`success`:`warning`)' )
        && str_contains( $dashboard, '.text(enabled?`Enabled`:`Disabled`)' ),
    'The summary pill tone and label must represent the saved feature state.'
);

echo 'PASS: Feature summary pills synchronize with persisted AJAX toggle state.' . PHP_EOL;
