<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

// Fails on parameters that are intentionally nullable, rendering this check pointless.
$cfg['suppress_issue_types'][] = 'PhanParamReqAfterOpt';

return $cfg;
