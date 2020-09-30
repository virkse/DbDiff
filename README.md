# DbDiff
Get the mysql schema changes from two different databases with different hosts, Also able to sync to production automatically.

Open up the DbDiff.php it has a $config where two databases's credentials are not to be change, first one is dev and other one is prod. Mostly developer needs to be change on dev database, sometime they forget to push to prod, by this script you will see the changes done and also can sync to production server automatically.
