<?php
namespace PGCO_Sale;

defined( 'ABSPATH' ) || exit;

/** Convert a stored item into display-ready data (the old price is always live from the table) */
class Offer {

    public static function format_price( int $n, string $sep ): string {
        return Jalali::fa_digits( number_format( $n, 0, '', $sep ) );
    }

    /** @return array|null null means the row is no longer in the table or has no numeric price */
    public static function resolve( array $item, array $s ): ?array {
        $row = Tables::find( (int) $item['table_id'], (string) $item['row_key'] );
        if ( ! $row || ! $row['price'] ) {
            return null;
        }
        $old = (int) $row['price'];

        if ( $item['mode'] === 'manual' && (int) $item['manual'] > 0 ) {
            $new = (int) $item['manual'];
        } else {
            $step = max( 1, (int) $s['rounding'] );
            $new  = (int) ( round( $old * ( 1 - (float) $item['percent'] / 100 ) / $step ) * $step );
        }
        $has_discount = $new > 0 && $new < $old;
        $percent      = $has_discount ? (int) round( ( 1 - $new / $old ) * 100 ) : 0;
        if ( ! $has_discount ) {
            $new = $old;
        }

        $title = $item['title'] !== '' ? $item['title'] : $row['title'];
        $price = self::format_price( $new, $s['sep'] );

        return [
            'title'    => $title,
            'image'    => (int) $item['image'],
            'old'      => $has_discount ? self::format_price( $old, $s['sep'] ) : '',
            'new'      => $price,
            'percent'  => $percent ? Jalali::fa_digits( $percent ) . '٪' : '',
            'unit'     => $item['unit'] !== '' ? $item['unit'] : 'تومان',
            'wa_href'  => self::wa_href( $item, $s, $title, $price ),
        ];
    }

    /** WhatsApp link; if the base URL has no text (text=) the message is added to it */
    private static function wa_href( array $item, array $s, string $title, string $price ): string {
        $url = $item['wa_url'] !== '' ? $item['wa_url'] : $s['wa_url'];
        if ( $url === '' ) {
            return '';
        }
        $msg = $item['wa_message'] !== '' ? $item['wa_message'] : $s['wa_message'];
        $msg = strtr( $msg, [ '{title}' => $title, '{price}' => $price ] );
        if ( $msg === '' || preg_match( '/[?&]text=/', $url ) ) {
            return $url;
        }
        return add_query_arg( 'text', rawurlencode( $msg ), $url );
    }
}
