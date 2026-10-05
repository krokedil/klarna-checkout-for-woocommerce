<?php
/** @var \Pronamic\WordPress\Documentor\Hook $hook */
/** @var \Pronamic\WordPress\Documentor\Documentor $documentor */

echo '### `', $hook->get_tag()->get_name(), '`', $eol;
echo $eol;

$summary = $hook->get_summary();

if ( ! empty( $summary ) ) {
	echo '*', $summary, '*', $eol;
	echo $eol;
}

$description = $hook->get_description();

if ( ! empty( $description ) ) {
	echo $description, $eol;
	echo $eol;
}

/**
 * Arguments.
 *
 * The argument table is built from the `@param` tags on the hook's doc block
 * rather than from the call arguments. Documentor can only resolve the type and
 * description of a call argument when a plain variable is passed (it matches the
 * variable name to a `@param` tag). When a hook is fired with an expression,
 * such as `do_action( 'foo', $order->get_id() )`, that match fails and the type
 * and description columns end up empty. Reading the doc block directly lets us
 * document those arguments using the names we choose in the `@param` tags.
 *
 * @var \phpDocumentor\Reflection\DocBlock|null $doc_block
 */
$doc_block = $hook->get_doc_block();

$param_tags = ( null === $doc_block ) ? array() : $doc_block->getTagsByName( 'param' );

$argument_rows = array();

foreach ( $param_tags as $param_tag ) {
	// Skip malformed @param tags that could not be parsed into a Param object.
	if ( ! ( $param_tag instanceof \phpDocumentor\Reflection\DocBlock\Tags\Param ) ) {
		continue;
	}

	$name = $param_tag->getVariableName();

	if ( empty( $name ) ) {
		continue;
	}

	$type        = \strval( $param_tag->getType() );
	$description = \strval( $param_tag->getDescription() );

	$argument_rows[] = \sprintf(
		'%s | %s | %s',
		\sprintf( '`$%s`', $name ),
		( '' === $type ) ? '' : \sprintf( '`%s`', \addcslashes( $type, '|' ) ),
		\strtr(
			\addcslashes( $description, '|' ),
			array(
				"\r\n" => '<br>',
				"\r"   => '<br>',
				"\n"   => '<br>',
			)
		)
	);
}

if ( \count( $argument_rows ) > 0 ) {
	echo '**Arguments**', $eol;

	echo $eol;

	echo 'Argument | Type | Description', $eol;
	echo '-------- | ---- | -----------', $eol;

	foreach ( $argument_rows as $argument_row ) {
		echo $argument_row, $eol;
	}
}

echo $eol;

/**
 * Changelog.
 *
 * @link https://developer.wordpress.org/reference/hooks/activated_plugin/#changelog
 * @link https://github.com/phpDocumentor/ReflectionDocBlock/blob/5.2.2/src/DocBlock/Tags/Since.php
 */
$changelog = $hook->get_changelog();

if ( null !== $changelog && \count( $changelog ) > 0 ) {
	echo '**Changelog**', $eol;

	echo $eol;

	echo 'Version | Description', $eol;
	echo '------- | -----------', $eol;

	foreach ( $changelog as $item ) {
		\printf(
			'%s | %s',
			\sprintf( '`%s`', $item->get_version() ),
			$item->get_description()
		);

		echo $eol;
	}

	echo $eol;
}

$link_tags = ( null === $doc_block ) ? array() : $doc_block->getTagsByName( 'link' );
if ( ! empty( $link_tags ) ) {
	echo 'Examples: ', $eol;

	for($i = 0; $i < count($link_tags); $i++) {
		$link_tag = $link_tags[$i];
		// Skip malformed @link tags that could not be parsed into a Link object.
		if ( ! ( $link_tag instanceof \phpDocumentor\Reflection\DocBlock\Tags\Link ) ) {
			continue;
		}
		$description = $link_tag->getDescription();

		// If the description is empty, default to $i + 1 instead.
		if ( empty( $description ) ) {
			$description = (string) '#' . ($i + 1);
		}

		echo sprintf( '- [%s](%s)', $description, $link_tag->getLink() );
		if ($i < count($link_tags) - 1) {
			echo $eol;
		}
	}

	echo $eol, $eol;
}
printf(
	'Source: %s, %s',
	\sprintf(
		'[%s](%s)',
		$hook->get_file()->getPathname(),
		$documentor->relative( $hook->get_file() )
	),
	\sprintf(
		'[line %s](%s)',
		$hook->get_start_line(),
		\sprintf(
			'%s#L%d-L%d',
			$documentor->relative( $hook->get_file() ),
			$hook->get_start_line(),
			$hook->get_end_line()
		)
	)
);

echo $eol;
echo $eol;
