<?php

require_once __DIR__ . '/../config/config.php';   
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/stats.php';
require_once __DIR__ . '/../includes/icons.php';

// Asset version from file times: browsers fetch CSS/JS again after every change instead of keeping stale copies
if (!defined('ASSET_VER')) define('ASSET_VER', max(filemtime(__DIR__ . '/../assets/css/style.css'), filemtime(__DIR__ . '/../assets/js/dialog.js'), filemtime(__DIR__ . '/../assets/css/mobile.css')));
