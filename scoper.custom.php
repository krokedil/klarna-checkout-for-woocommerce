<?php // phpcs:disable
function customize_php_scoper_config( array $config ): array {
    // Ignore the abspath constant when scoping.
	$config['exclude-constants'][] = 'ABSPATH';
	$config['exclude-constants'][] = 'WP_INSTALLING';
	$config['exclude-constants'][] = 'WP_INSTALLING_NETWORK';
	$config['exclude-namespaces'][] = 'Automattic';

	return $config;
}