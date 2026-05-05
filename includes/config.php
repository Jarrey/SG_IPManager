<?php
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__));
}
define('DATA_DIR',      ROOT_DIR . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR);
define('USERS_FILE',    DATA_DIR . 'users.json');
define('IPS_FILE',      DATA_DIR . 'ips.json');
define('SETTINGS_FILE', DATA_DIR . 'settings.json');
define('PING_CACHE_FILE',       DATA_DIR . 'ping_cache.json');
define('MAC_VENDOR_CACHE_FILE', DATA_DIR . 'mac_vendor_cache.json');
define('PORT_MAPPINGS_FILE',    DATA_DIR . 'port_mappings.json');

define('APP_NAME',    '索格 IPManager');
define('APP_VERSION', '1.0.0');

// Ensure data directory exists and is protected
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0750, true);
}
if (!file_exists(DATA_DIR . '.htaccess')) {
    file_put_contents(DATA_DIR . '.htaccess', "Require all denied\nDeny from all\n");
}
