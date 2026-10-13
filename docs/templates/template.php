<?php
/** @var \Pronamic\WordPress\Documentor\Documentor $documentor */
if ( ! isset( $documentor ) ) {
	return;
}

$actions = $documentor->get_actions();
$filters = $documentor->get_filters();

$eol = "\n";

echo '# Hooks', $eol;

echo $eol;

echo '- [Actions](#actions)', $eol;
echo '- [Filters](#filters)', $eol;

echo $eol;

echo '## Actions', $eol;

echo $eol;

foreach ( $actions as $hook ) {
	include __DIR__  . '/hook.php';
	echo $eol, '---', $eol;
}

echo '## Filters', $eol;

echo $eol;

foreach ( $filters as $hook ) {
	include __DIR__  . '/hook.php';
	echo $eol, '---', $eol;
}

echo $eol;
