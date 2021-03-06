<?php

/**
 * This function converts an array holding the form key values to a string.
 * The generated string represents the message to be signed by the MAC.
 *
 * @param $formKeyValues
 *
 * @return string
 */
function createMessage( $formKeyValues ) {
	$string = "";
	if ( is_array( $formKeyValues ) ) {
		ksort( $formKeyValues ); // Sort the posted values by alphanumeric
		foreach ( $formKeyValues as $key => $value ) {
			if ( $key != "MAC" ) { // Don't include the MAC in the calculation of the MAC.
				if ( strlen( $string ) > 0 ) {
					$string .= "&";
				}
				$string .= "$key=$value"; // create string representation
			}
		}

		return $string;
	} else {
		return "An array must be used as input!";
	}
}

/**
 * This function converts from a hexadecimal representation to a string representation.
 *
 * @param $hex
 *
 * @return string
 */
function hextostr( $hex ) {
	$string = "";
	foreach ( explode( "\n", trim( chunk_split( $hex, 2 ) ) ) as $h ) {
		$string .= chr( hexdec( $h ) );
	}

	return $string;
}

/**
 * This function calculates the MAC for an array holding the form key values.
 * The $logfile is optional.
 *
 * @param $formKeyValues
 * @param $HmacKey
 * @param null $logfile
 *
 * @return false|string
 */
function calculateMac( $formKeyValues, $HmacKey, $logfile = null ) {
	// Create the message to be signed.
	if ( is_array( $formKeyValues ) ) {
		$messageToBeSigned = createMessage( $formKeyValues );
		// Calculate the MAC.
		$MAC = hash_hmac( "sha256", $messageToBeSigned, hextostr( $HmacKey ) );

		// Following is only relevant if you wan't to log the calculated MAC to a log file.
		if ( $logfile ) {
			$fp = fopen( $logfile, 'a' ) or exit( "Can't open $logfile!" );
			fwrite( $fp, "messageToBeSigned: " . $messageToBeSigned . PHP_EOL . " HmacKey: " . $HmacKey . PHP_EOL . " generated MAC: " . $MAC . PHP_EOL );
			if ( isset( $formKeyValues["MAC"] ) && $formKeyValues["MAC"] != "" ) {
				fwrite( $fp, " posted MAC:		" . $formKeyValues["MAC"] . PHP_EOL );
			}
		}

		return $MAC;
	} else {
		die( "Form key values must be given as an array" );
	}
}