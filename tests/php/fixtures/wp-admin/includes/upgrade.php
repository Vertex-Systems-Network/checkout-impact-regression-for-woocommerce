<?php
/**
 * dbDelta test double used only by schema-contract.php.
 */
if ( ! function_exists( 'dbDelta' ) ) {
	function dbDelta( $query ) {
		$GLOBALS['wpecir_schema_queries'][] = $query;
		return array();
	}
}
