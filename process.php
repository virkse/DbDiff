<?php
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);

include 'DbDiff.php';

## initiate
$should_sync_with_prod = 0; ## Syncing will not perform
if(
    isset($_REQUEST['should_sync_with_prod']) && 
    $_REQUEST['should_sync_with_prod'] == 1
) {
    $should_sync_with_prod = 1; ## Syncing will perform
}

$DbDiff = new DbDiff();
$migrations = $DbDiff->migrate($should_sync_with_prod);
echo "(". count($migrations) .") records are found to be update on production server.<br>";
if(count($migrations) > 0) {
    if($should_sync_with_prod) {
        echo "Following schema is also synced to production server.<br>";
    } else {
        echo "Schema is pending to to sync.<br>";
    }
}

foreach($migrations as $query) {
    echo '<code>'. $query .'</code><br>';
}
