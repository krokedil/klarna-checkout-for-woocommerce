<?php // phpcs:disable
function customize_php_scoper_config( array $config ): array {
    // Ignore the abspath constant when scoping.
	$config['exclude-constants'][] = 'ABSPATH';
	$config['exclude-constants'][] = 'WP_INSTALLING';
	$config['exclude-constants'][] = 'WP_INSTALLING_NETWORK';
	$config['exclude-namespaces'][] = 'Automattic';

	$config['exclude-classes'][] = 'WC_Subscriptions_Cart';
	$config['exclude-classes'][] = 'WC_Subscriptions_Product';
	$config['exclude-classes'][] = 'WCS_ATT_Cart';
	$config['exclude-classes'][] = 'WC_GC_Gift_Cards';
	$config['exclude-classes'][] = 'YITH_YWGC_Gift_Card';

	return $config;
}