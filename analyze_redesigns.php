<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Organization;
use Carbon\Carbon;

// Get organizations with large discrepancies
$orgs = Organization::whereNotNull('last_major_redesign_at_actual')
    ->whereNotNull('last_major_redesign_at')
    ->get();

$largeDiscrepancies = $orgs->filter(function ($org) {
    $predicted = Carbon::parse($org->last_major_redesign_at);
    $actual = Carbon::parse($org->last_major_redesign_at_actual);
    return abs($predicted->diffInDays($actual)) > 365;
});

echo "Organizations with >1 year discrepancy: " . $largeDiscrepancies->count() . " out of " . $orgs->count() . PHP_EOL . PHP_EOL;

// Analyze specific examples
$exampleIds = [87, 262, 1195, 436];

foreach ($exampleIds as $id) {
    $org = Organization::find($id);
    if (!$org) continue;

    $redesign = $org->websiteRedesigns()->first();
    if (!$redesign) continue;

    echo "Organization ID: " . $org->id . PHP_EOL;
    echo "Name: " . $org->name . PHP_EOL;
    echo "Predicted: " . $org->last_major_redesign_at . PHP_EOL;
    echo "Actual: " . $org->last_major_redesign_at_actual . PHP_EOL;
    echo "Composite Score: " . round($redesign->composite_score, 4) . PHP_EOL;
    echo "Threshold: " . round($redesign->statistical_threshold, 4) . PHP_EOL;
    echo "Tag Score: " . round($redesign->tag_difference_score, 4) . PHP_EOL;
    echo "Class Score: " . round($redesign->class_difference_score, 4) . PHP_EOL;
    echo "Asset Score: " . round($redesign->asset_difference_score, 4) . PHP_EOL;
    echo "Before Classes: " . $redesign->before_html_class_count . PHP_EOL;
    echo "After Classes: " . $redesign->after_html_class_count . PHP_EOL;
    echo "Before Assets: " . $redesign->before_head_asset_count . PHP_EOL;
    echo "After Assets: " . $redesign->after_head_asset_count . PHP_EOL;
    echo "---" . PHP_EOL . PHP_EOL;
}
