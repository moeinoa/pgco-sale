<?php
namespace PGCO_Sale;

defined( 'ABSPATH' ) || exit;

/** Reading, defaults and sanitizing of the settings */
class Settings {

    public static function defaults(): array {
        return [
            'title'          => 'فروش ویژه',
            'subtitle'       => 'قیمت‌ها تا تاریخ {date} معتبر است',
            'section_id'     => 'pgco-sale',
            'end_date'       => '',
            'end_time'       => '23:59',
            'end_ts'         => 0,
            'rounding'       => 1,
            'sep'            => '.',
            'autoplay'       => 1,
            'autoplay_sec'   => 4,
            'per_view'       => 4,
            'per_tab'        => 2.5,
            'per_mob'        => 1.4,
            'bg_image'       => 0,
            'decor'          => [],
            'btn_label'      => 'استعلام و ثبت سفارش',
            'wa_url'         => '',
            'wa_message'     => 'سلام، برای {title} استعلام قیمت می‌خواهم.',
            'bar_enabled'    => 0,
            'bar_inject'     => 1,
            'bar_badge'      => '%فروش ویژه%',
            'bar_text_1'     => 'لوله و پروفیل گالوانیزه',
            'bar_text_2'     => 'تا 20% تخفیف',
            'bar_text_3'     => 'تا 15 مهر',
            'bar_link_label' => 'مشاهده قیمت‌ها',
            'bar_link_url'   => '',
            'items'          => [],
        ];
    }

    public static function get(): array {
        $saved = get_option( PGS_OPTION, [] );
        return array_replace( self::defaults(), is_array( $saved ) ? $saved : [] );
    }

    public static function section_url( array $s ): string {
        return $s['bar_link_url'] !== '' ? $s['bar_link_url'] : '#' . $s['section_id'];
    }

    public static function sanitize( array $raw ): array {
        $d   = self::defaults();
        $txt = static fn( $k ) => sanitize_text_field( $raw[ $k ] ?? $d[ $k ] );
        $num = static fn( $k, $min, $max ) => max( $min, min( $max, (float) Jalali::en_digits( (string) ( $raw[ $k ] ?? $d[ $k ] ) ) ) );

        $out = [
            'title'          => $txt( 'title' ),
            'subtitle'       => $txt( 'subtitle' ),
            'section_id'     => sanitize_html_class( (string) ( $raw['section_id'] ?? '' ) ) ?: $d['section_id'],
            'end_date'       => Jalali::en_digits( $txt( 'end_date' ) ),
            'end_time'       => Jalali::en_digits( $txt( 'end_time' ) ) ?: $d['end_time'],
            'rounding'       => in_array( (int) ( $raw['rounding'] ?? 1 ), [ 1, 10, 100, 1000 ], true ) ? (int) ( $raw['rounding'] ?? 1 ) : 1,
            'sep'            => in_array( $raw['sep'] ?? '.', [ '.', ',', '٬' ], true ) ? ( $raw['sep'] ?? '.' ) : '.',
            'autoplay'       => empty( $raw['autoplay'] ) ? 0 : 1,
            'autoplay_sec'   => $num( 'autoplay_sec', 2, 30 ),
            'per_view'       => $num( 'per_view', 1, 8 ),
            'per_tab'        => $num( 'per_tab', 1, 6 ),
            'per_mob'        => $num( 'per_mob', 1, 3 ),
            'bg_image'       => absint( $raw['bg_image'] ?? 0 ),
            'btn_label'      => $txt( 'btn_label' ),
            'wa_url'         => esc_url_raw( (string) ( $raw['wa_url'] ?? '' ) ),
            'wa_message'     => sanitize_textarea_field( (string) ( $raw['wa_message'] ?? '' ) ),
            'bar_enabled'    => empty( $raw['bar_enabled'] ) ? 0 : 1,
            'bar_inject'     => empty( $raw['bar_inject'] ) ? 0 : 1,
            'bar_badge'      => $txt( 'bar_badge' ),
            'bar_text_1'     => $txt( 'bar_text_1' ),
            'bar_text_2'     => $txt( 'bar_text_2' ),
            'bar_text_3'     => $txt( 'bar_text_3' ),
            'bar_link_label' => $txt( 'bar_link_label' ),
            'bar_link_url'   => self::clean_link( (string) ( $raw['bar_link_url'] ?? '' ) ),
        ];

        $out['end_ts'] = $out['end_date'] !== '' ? Jalali::parse( $out['end_date'], $out['end_time'] ) : 0;

        $out['decor'] = [];
        foreach ( (array) ( $raw['decor'] ?? [] ) as $dc ) {
            $img = absint( $dc['image'] ?? 0 );
            if ( ! $img ) {
                continue;
            }
            $out['decor'][] = [
                'image' => $img,
                'left'  => max( -20, min( 120, (float) ( $dc['left'] ?? 0 ) ) ),
                'top'   => max( -20, min( 120, (float) ( $dc['top'] ?? 0 ) ) ),
                'width' => max( 20, min( 900, (int) ( $dc['width'] ?? 160 ) ) ),
            ];
        }

        $out['items'] = [];
        foreach ( (array) ( $raw['items'] ?? [] ) as $it ) {
            $table = absint( $it['table_id'] ?? 0 );
            $key   = preg_replace( '/[^a-f0-9]/', '', (string) ( $it['row_key'] ?? '' ) );
            if ( ! $table || $key === '' ) {
                continue;
            }
            $out['items'][] = [
                'table_id'   => $table,
                'row_key'    => $key,
                'source'     => sanitize_text_field( $it['source'] ?? '' ),
                'title'      => sanitize_text_field( $it['title'] ?? '' ),
                'image'      => absint( $it['image'] ?? 0 ),
                'mode'       => ( $it['mode'] ?? '' ) === 'manual' ? 'manual' : 'percent',
                'percent'    => max( 0, min( 95, (float) Jalali::en_digits( (string) ( $it['percent'] ?? 0 ) ) ) ),
                'manual'     => absint( preg_replace( '/\D/', '', Jalali::en_digits( (string) ( $it['manual'] ?? '' ) ) ) ),
                'unit'       => sanitize_text_field( $it['unit'] ?? '' ),
                'wa_url'     => esc_url_raw( (string) ( $it['wa_url'] ?? '' ) ),
                'wa_message' => sanitize_textarea_field( (string) ( $it['wa_message'] ?? '' ) ),
            ];
        }
        return $out;
    }

    /** Bar link: anchor (#id), full URL or relative path */
    private static function clean_link( string $v ): string {
        $v = trim( $v );
        if ( $v === '' ) {
            return '';
        }
        if ( $v[0] === '#' ) {
            return '#' . sanitize_html_class( ltrim( $v, '#' ) );
        }
        return esc_url_raw( $v );
    }
}
