<?php

require_once 'connection/amo_auth.php';

$tokens = getAccessToken();

print_r($tokens);
