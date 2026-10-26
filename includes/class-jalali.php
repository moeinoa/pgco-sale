<?php
namespace PGCO_Sale;

defined( 'ABSPATH' ) || exit;

/** Jalali/Gregorian date conversion and Persian digit helpers (no external dependency) */
class Jalali {

    private const MONTHS = [ 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' ];

    public static function fa_digits( string|int $s ): string {
        return strtr( (string) $s, [ '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ] );
    }

    public static function en_digits( string $s ): string {
        return strtr( $s, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ] );
    }

    /** @return int[] [jy, jm, jd] */
    public static function to_jalali( int $gy, int $gm, int $gd ): array {
        $g_d_m = [ 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 ];
        $gy2   = $gm > 2 ? $gy + 1 : $gy;
        $days  = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $gd + $g_d_m[ $gm - 1 ];
        $jy    = -1595 + ( 33 * intdiv( $days, 12053 ) );
        $days %= 12053;
        $jy   += 4 * intdiv( $days, 1461 );
        $days %= 1461;
        if ( $days > 365 ) {
            $jy  += intdiv( $days - 1, 365 );
            $days = ( $days - 1 ) % 365;
        }
        if ( $days < 186 ) {
            $jm = 1 + intdiv( $days, 31 );
            $jd = 1 + ( $days % 31 );
        } else {
            $jm = 7 + intdiv( $days - 186, 30 );
            $jd = 1 + ( ( $days - 186 ) % 30 );
        }
        return [ $jy, $jm, $jd ];
    }

    /** @return int[] [gy, gm, gd] */
    public static function to_gregorian( int $jy, int $jm, int $jd ): array {
        $jy  += 1595;
        $days = -355668 + ( 365 * $jy ) + ( intdiv( $jy, 33 ) * 8 ) + intdiv( ( $jy % 33 ) + 3, 4 ) + $jd + ( $jm < 7 ? ( $jm - 1 ) * 31 : ( ( $jm - 7 ) * 30 ) + 186 );
        $gy   = 400 * intdiv( $days, 146097 );
        $days %= 146097;
        if ( $days > 36524 ) {
            $days--;
            $gy  += 100 * intdiv( $days, 36524 );
            $days %= 36524;
            if ( $days >= 365 ) {
                $days++;
            }
        }
        $gy  += 4 * intdiv( $days, 1461 );
        $days %= 1461;
        if ( $days > 365 ) {
            $gy  += intdiv( $days - 1, 365 );
            $days = ( $days - 1 ) % 365;
        }
        $gd    = $days + 1;
        $leap  = ( $gy % 4 === 0 && $gy % 100 !== 0 ) || $gy % 400 === 0;
        $sal_a = [ 0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ];
        $gm    = 0;
        while ( $gm < 13 && $gd > $sal_a[ $gm ] ) {
            $gd -= $sal_a[ $gm ];
            $gm++;
        }
        return [ $gy, $gm, $gd ];
    }

    /** '1405/07/15' + '23:59' (Jalali, in site time) -> timestamp; 0 for invalid input */
    public static function parse( string $date, string $time = '23:59' ): int {
        $date = self::en_digits( trim( $date ) );
        $time = self::en_digits( trim( $time ) );
        if ( ! preg_match( '#^(\d{4})\D(\d{1,2})\D(\d{1,2})$#', $date, $d ) ) {
            return 0;
        }
        [ $jy, $jm, $jd ] = [ (int) $d[1], (int) $d[2], (int) $d[3] ];
        if ( $jm < 1 || $jm > 12 || $jd < 1 || $jd > 31 ) {
            return 0;
        }
        $h = 23;
        $i = 59;
        if ( preg_match( '#^(\d{1,2}):(\d{2})$#', $time, $t ) && (int) $t[1] < 24 && (int) $t[2] < 60 ) {
            $h = (int) $t[1];
            $i = (int) $t[2];
        }
        [ $gy, $gm, $gd ] = self::to_gregorian( $jy, $jm, $jd );
        try {
            $dt = new \DateTimeImmutable( sprintf( '%04d-%02d-%02d %02d:%02d:59', $gy, $gm, $gd, $h, $i ), wp_timezone() );
        } catch ( \Exception $e ) {
            return 0;
        }
        return $dt->getTimestamp();
    }

    /**
     * Gregorian parts of the time in site time. Deliberately without wp_date(): Jalali calendar plugins (like wp-parsidate)
     * turn its output into Jalali/Persian and break the calculation.
     * @return array{Y:int,n:int,j:int,G:int,i:int,s:int}
     */
    public static function site_parts( int $ts ): array {
        $d = ( new \DateTimeImmutable( '@' . $ts ) )->setTimezone( wp_timezone() );
        return [ 'Y' => (int) $d->format( 'Y' ), 'n' => (int) $d->format( 'n' ), 'j' => (int) $d->format( 'j' ), 'G' => (int) $d->format( 'G' ), 'i' => (int) $d->format( 'i' ), 's' => (int) $d->format( 's' ) ];
    }

    /** timestamp -> '15 Mehr 1405' */
    public static function format( int $ts ): string {
        $p = self::site_parts( $ts );
        [ $jy, $jm, $jd ] = self::to_jalali( $p['Y'], $p['n'], $p['j'] );
        return self::fa_digits( $jd ) . ' ' . self::MONTHS[ $jm - 1 ] . ' ' . self::fa_digits( $jy );
    }
}
