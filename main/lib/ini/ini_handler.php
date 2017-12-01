<?php
class INIParser {
    public static function parse_ini ( $filepath ) {
        $ini = file( $filepath );
        if ( count( $ini ) == 0 ) { return array(); }
        $sections = array();
        $values = array();
        $globals = array();
        $i = 0;
        foreach( $ini as $line ){
            $line = trim( $line );
            // Comments
            if ( $line == '' || $line{0} == ';' ) { continue; }
            // Sections
            if ( $line{0} == '[' ) {
                $sections[] = substr( $line, 1, -1 );
                $i++;
                continue;
            }
            // Key-value pair
            //list( $key, $value ) = explode('=', $line, 2);
            //$key = trim( $key );
            //$value = trim( $value );
            $parts = array_map('trim', explode("=", $line, 2));
            $key = $parts[0];
            $value = isset($parts[1]) ? $parts[1] : null;
            if ( $i == 0 ) {
                // Array values
                if ( substr( $line, -1, 2 ) == '[]' ) {
                    $globals[ $key ][] = $value;
                } else {
                    $globals[ $key ] = $value;
                }
            } else {
                // Array values
                if ( substr( $line, -1, 2 ) == '[]' ) {
                    $values[ $i - 1 ][ $key ][] = $value;
                } else {
                    $values[ $i - 1 ][ $key ] = $value;
                }
            }
        }
        for( $j=0; $j<$i; $j++ ) {
            $result[ $sections[ $j ] ] = $values[ $j ];
        }
        return $result + $globals;
    }
}

if (!function_exists('parse_ini')) {
    function parse_ini($filepath){
        return INIParser::parse_ini($filepath);
    }
}
?>