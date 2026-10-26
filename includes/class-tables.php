<?php
namespace PGCO_Sale;

defined( 'ABSPATH' ) || exit;

/** Reads the tables of the PGCO Tables plugin (JSON stored in _table_options) */
class Tables {

    private static array $cache = [];

    /** @return array<int,string> id => title */
    public static function choices(): array {
        $posts = get_posts( [
            'post_type'      => PGS_TABLE_CPT,
            'post_status'    => [ 'publish', 'draft', 'private' ],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ] );
        $out = [];
        foreach ( $posts as $p ) {
            $out[ $p->ID ] = get_the_title( $p ) ?: '#' . $p->ID;
        }
        return $out;
    }

    /**
     * @return array{head:string[],price_col:int,unit_col:?int,rows:array<int,array>}
     * each row: key, cells, title, price(int|null), price_raw
     */
    public static function get( int $id ): array {
        if ( isset( self::$cache[ $id ] ) ) {
            return self::$cache[ $id ];
        }
        $empty = [ 'head' => [], 'price_col' => 0, 'unit_col' => null, 'rows' => [] ];
        $opts  = get_post_meta( $id, '_table_options', true );
        $table = is_array( $opts ) && ! empty( $opts['table'] ) ? json_decode( (string) $opts['table'], true ) : null;
        if ( ! is_array( $table ) || empty( $table['body'] ) ) {
            return self::$cache[ $id ] = $empty;
        }

        $head = array_map( static fn( $c ) => trim( (string) $c ), array_values( (array) ( $table['head'][0] ?? [] ) ) );

        // Price column from the column title; if missing, the last column
        $price_col = null;
        $unit_col  = null;
        foreach ( $head as $i => $h ) {
            if ( mb_strpos( $h, 'قیمت' ) !== false ) {
                $price_col = $i;
            }
            if ( $unit_col === null && mb_strpos( $h, 'واحد' ) !== false ) {
                $unit_col = $i;
            }
        }

        $rows = [];
        foreach ( (array) $table['body'] as $r ) {
            $cells = array_map( static fn( $c ) => trim( (string) $c ), array_values( (array) $r ) );
            if ( ! $cells ) {
                continue;
            }
            $pc        = $price_col ?? ( count( $cells ) - 1 );
            $price_raw = $cells[ $pc ] ?? '';
            $digits    = preg_replace( '/\D/', '', Jalali::en_digits( $price_raw ) );
            $rest      = $cells;
            unset( $rest[ $pc ] );
            $rows[] = [
                'key'       => substr( md5( implode( '|', $rest ) ), 0, 12 ),
                'cells'     => $cells,
                'title'     => $cells[0],
                'price'     => $digits !== '' && (int) $digits > 0 && mb_strpos( $price_raw, 'تماس' ) === false ? (int) $digits : null,
                'price_raw' => $price_raw,
            ];
        }

        return self::$cache[ $id ] = [
            'head'      => $head,
            'price_col' => $price_col ?? ( count( $head ) ? count( $head ) - 1 : 0 ),
            'unit_col'  => $unit_col,
            'rows'      => $rows,
        ];
    }

    public static function find( int $id, string $key ): ?array {
        foreach ( self::get( $id )['rows'] as $r ) {
            if ( $r['key'] === $key ) {
                return $r;
            }
        }
        return null;
    }

    /** Suggested card title: title + the middle columns' specifications (e.g. 'size 2, thickness 2.5') */
    public static function suggest_title( array $table, array $row ): string {
        $parts = [];
        foreach ( $row['cells'] as $i => $c ) {
            if ( $i === 0 || $i === $table['price_col'] || $i === $table['unit_col'] || $c === '' ) {
                continue;
            }
            $h       = trim( preg_replace( '/\s*[\(（].*?[\)）]/u', '', $table['head'][ $i ] ?? '' ) );
            $parts[] = trim( $h . ' ' . $c );
        }
        return $parts ? $row['title'] . ' — ' . implode( '، ', $parts ) : $row['title'];
    }

    /** Suggested unit: 'Toman/per kg' from the 'unit' column, otherwise 'Toman' */
    public static function suggest_unit( array $table, array $row ): string {
        $u = $table['unit_col'] !== null ? ( $row['cells'][ $table['unit_col'] ] ?? '' ) : '';
        return $u !== '' ? 'تومان/هر ' . $u : 'تومان';
    }
}
