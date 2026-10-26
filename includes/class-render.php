<?php
namespace PGCO_Sale;

defined( 'ABSPATH' ) || exit;

/** Output of the section ([pgco_sale]) and the header top bar ([pgco_sale_bar]) */
class Render {

    private static bool $bar_printed = false;

    public static function init(): void {
        add_shortcode( 'pgco_sale', [ __CLASS__, 'section' ] );
        add_shortcode( 'pgco_sale_bar', [ __CLASS__, 'bar_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'assets' ] );
        add_action( 'wp_body_open', [ __CLASS__, 'bar_auto' ], 1 );
    }

    public static function assets(): void {
        wp_register_style( 'pgco-sale', PGS_URL . 'assets/css/front.css', [], PGS_VERSION );
        wp_register_script( 'pgco-sale', PGS_URL . 'assets/js/front.js', [], PGS_VERSION, [ 'in_footer' => true, 'strategy' => 'defer' ] );

        // The style must come in <head> so the page does not jump
        $s    = Settings::get();
        $post = get_post();
        $need = ( $s['bar_enabled'] && $s['bar_inject'] && self::active( $s ) )
            || ( $post && ( has_shortcode( (string) $post->post_content, 'pgco_sale' ) || has_shortcode( (string) $post->post_content, 'pgco_sale_bar' ) ) )
            || is_front_page();
        if ( apply_filters( 'pgco_sale_enqueue_early', $need ) ) {
            wp_enqueue_style( 'pgco-sale' );
            wp_enqueue_script( 'pgco-sale' );
        }
    }

    /** Has the campaign not ended yet? (no end date = always active) */
    private static function active( array $s ): bool {
        return $s['end_ts'] === 0 || (int) $s['end_ts'] > time();
    }

    private static function timer( array $s, string $variant ): string {
        if ( ! $s['end_ts'] ) {
            return '';
        }
        ob_start();
        $p    = Jalali::site_parts( time() );
        $secs = $p['G'] * 3600 + $p['i'] * 60 + $p['s'];
        ?>
        <div class="pgs-timer pgs-timer--<?php echo esc_attr( $variant ); ?>" data-pgs-timer data-end="<?php echo (int) $s['end_ts']; ?>" data-now="<?php echo time(); ?>" role="timer" aria-label="زمان باقی‌مانده تا پایان فروش ویژه">
            <?php if ( $variant === 'section' ) : ?>
                <span class="pgs-clock" data-pgs-clock data-secs="<?php echo $secs; ?>" aria-hidden="true"><?php echo self::clock_svg(); ?></span>
            <?php endif; ?>
            <div class="pgs-timer__cells">
                <div class="pgs-cell"><strong class="pgs-cell__num" data-pgs-unit="d">۰۰</strong><span class="pgs-cell__label">روز</span></div>
                <div class="pgs-cell"><strong class="pgs-cell__num" data-pgs-unit="h">۰۰</strong><span class="pgs-cell__label">ساعت</span></div>
                <div class="pgs-cell"><strong class="pgs-cell__num" data-pgs-unit="m">۰۰</strong><span class="pgs-cell__label">دقیقه</span></div>
                <div class="pgs-cell"><strong class="pgs-cell__num" data-pgs-unit="s">۰۰</strong><span class="pgs-cell__label">ثانیه</span></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function clock_svg(): string {
        $ticks = '';
        for ( $i = 0; $i < 60; $i++ ) {
            $a   = deg2rad( $i * 6 );
            $big = $i % 5 === 0;
            $r1  = $big ? 76 : 83;
            $ticks .= sprintf(
                '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="%s" stroke-linecap="round"/>',
                round( 100 + 90 * sin( $a ), 2 ), round( 100 - 90 * cos( $a ), 2 ),
                round( 100 + $r1 * sin( $a ), 2 ), round( 100 - $r1 * cos( $a ), 2 ),
                $big ? '#1f1f1f' : '#8a8a8a', $big ? '3' : '1.2'
            );
        }
        return '<svg viewBox="0 0 200 200" width="100%" height="100%" focusable="false">'
            . '<defs><radialGradient id="pgs-face" cx="50%" cy="38%" r="70%"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#e9e9e9"/></radialGradient></defs>'
            . '<circle cx="100" cy="100" r="98" fill="#2b2b2b"/><circle cx="100" cy="100" r="94" fill="url(#pgs-face)"/>'
            . $ticks
            . '<line data-hand="h" x1="100" y1="106" x2="100" y2="56" stroke="#1f1f1f" stroke-width="6" stroke-linecap="round"/>'
            . '<line data-hand="m" x1="100" y1="110" x2="100" y2="34" stroke="#1f1f1f" stroke-width="4" stroke-linecap="round"/>'
            . '<g data-hand="s"><line x1="100" y1="120" x2="100" y2="22" stroke="#d71a20" stroke-width="2" stroke-linecap="round"/><circle cx="100" cy="100" r="6" fill="#d71a20"/></g>'
            . '<circle cx="100" cy="100" r="2.4" fill="#fff"/>'
            . '<path d="M30 70a76 76 0 0 1 140 0" fill="none" stroke="#fff" stroke-opacity=".55" stroke-width="3" stroke-linecap="round"/>'
            . '</svg>';
    }

    private static function arrow_svg( string $dir ): string {
        $d = $dir === 'prev' ? 'M9 5l7 7-7 7' : 'M15 5l-7 7 7 7';
        return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="' . $d . '" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }

    private static function wa_svg(): string {
        return '<svg class="pgs-btn__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2.5a9.5 9.5 0 0 0-8.2 14.3L2.6 21.4l4.7-1.2A9.5 9.5 0 1 0 12 2.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M16.2 14.3c-.3-.15-1.6-.8-1.9-.9-.25-.1-.45-.15-.6.15-.2.3-.7.9-.85 1.05-.15.2-.3.2-.55.05a6.1 6.1 0 0 1-3-2.65c-.2-.4.2-.35.65-1.2.1-.15.05-.3 0-.45l-.85-2c-.2-.5-.4-.45-.6-.45h-.5a1 1 0 0 0-.7.35 2.9 2.9 0 0 0-.9 2.15 5 5 0 0 0 1.05 2.65 11.4 11.4 0 0 0 4.4 3.9c1.6.7 2.25.75 3.05.6a2.6 2.6 0 0 0 1.7-1.2 2.1 2.1 0 0 0 .15-1.2c-.05-.1-.2-.15-.5-.3Z" fill="currentColor"/></svg>';
    }

    public static function section(): string {
        $s = Settings::get();
        if ( ! self::active( $s ) || ! $s['items'] ) {
            return current_user_can( 'manage_options' ) && ! $s['items'] ? '<p>[pgco_sale] هنوز محصولی انتخاب نشده است.</p>' : '';
        }
        $offers = [];
        foreach ( $s['items'] as $it ) {
            if ( $o = Offer::resolve( $it, $s ) ) {
                $offers[] = $o;
            }
        }
        if ( ! $offers ) {
            return '';
        }

        wp_enqueue_style( 'pgco-sale' );
        wp_enqueue_script( 'pgco-sale' );

        $bg       = $s['bg_image'] ? wp_get_attachment_image_url( $s['bg_image'], 'full' ) : '';
        $subtitle = $s['end_ts'] ? str_replace( '{date}', Jalali::format( (int) $s['end_ts'] ), $s['subtitle'] ) : str_replace( '{date}', '', $s['subtitle'] );
        $tag      = apply_filters( 'pgco_sale_heading_tag', 'h2' );

        ob_start(); ?>
        <section id="<?php echo esc_attr( $s['section_id'] ); ?>" class="pgs" dir="rtl" data-pgs-section data-pgs-hide-on-end aria-labelledby="pgs-title"
                 style="--pgs-per:<?php echo (float) $s['per_view']; ?>;--pgs-per-tab:<?php echo (float) $s['per_tab']; ?>;--pgs-per-mob:<?php echo (float) $s['per_mob']; ?>;<?php echo $bg ? '--pgs-bg:url(' . esc_url( $bg ) . ');' : ''; ?>">
            <?php foreach ( $s['decor'] as $dc ) :
                $u = wp_get_attachment_image_url( (int) $dc['image'], 'full' );
                if ( ! $u ) {
                    continue;
                } ?>
                <img class="pgs-decor" src="<?php echo esc_url( $u ); ?>" alt="" aria-hidden="true" loading="lazy" decoding="async"
                     style="left:<?php echo (float) $dc['left']; ?>%;top:<?php echo (float) $dc['top']; ?>%;width:<?php echo (int) $dc['width']; ?>px">
            <?php endforeach; ?>

            <div class="pgs__inner">
                <header class="pgs__head">
                    <div class="pgs__titles">
                        <<?php echo tag_escape( $tag ); ?> id="pgs-title" class="pgs__title"><?php echo esc_html( $s['title'] ); ?></<?php echo tag_escape( $tag ); ?>>
                        <?php if ( trim( $subtitle ) !== '' ) : ?><p class="pgs__subtitle"><?php echo esc_html( $subtitle ); ?></p><?php endif; ?>
                    </div>
                    <?php echo self::timer( $s, 'section' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </header>

                <div class="pgs-slider" data-pgs-slider data-autoplay="<?php echo $s['autoplay'] ? (int) ( $s['autoplay_sec'] * 1000 ) : 0; ?>">
                    <button type="button" class="pgs-nav pgs-nav--prev" data-pgs-prev aria-label="قبلی"><?php echo self::arrow_svg( 'prev' ); // phpcs:ignore ?></button>
                    <button type="button" class="pgs-nav pgs-nav--next" data-pgs-next aria-label="بعدی"><?php echo self::arrow_svg( 'next' ); // phpcs:ignore ?></button>
                    <div class="pgs-viewport" data-pgs-viewport tabindex="0" aria-label="محصولات فروش ویژه">
                        <ul class="pgs-track">
                            <?php foreach ( $offers as $i => $o ) : ?>
                                <li class="pgs-slide"><?php self::card( $o, $s, $i < 4 ); ?><span class="pgs-floor" aria-hidden="true"></span></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function card( array $o, array $s, bool $eager ): void {
        ?>
        <article class="pgs-card">
            <div class="pgs-card__media">
                <?php if ( $o['image'] ) {
                    echo wp_get_attachment_image( $o['image'], 'medium_large', false, [
                        'alt'      => $o['title'],
                        'sizes'    => '(max-width:640px) 70vw, 300px',
                        'loading'  => $eager ? 'eager' : 'lazy',
                        'decoding' => 'async',
                    ] );
                } ?>
                <?php if ( $o['percent'] ) : ?><span class="pgs-badge" aria-label="تخفیف <?php echo esc_attr( $o['percent'] ); ?>"><?php echo esc_html( $o['percent'] ); ?></span><?php endif; ?>
            </div>
            <div class="pgs-card__body">
                <h3 class="pgs-card__title"><?php echo esc_html( $o['title'] ); ?></h3>
                <div class="pgs-price">
                    <del class="pgs-price__old"><?php echo $o['old'] !== '' ? esc_html( $o['old'] ) : '&nbsp;'; ?></del>
                    <div class="pgs-price__row">
                        <strong class="pgs-price__new"><?php echo esc_html( $o['new'] ); ?></strong>
                        <span class="pgs-price__unit"><?php echo esc_html( $o['unit'] ); ?></span>
                    </div>
                </div>
                <?php if ( $o['wa_href'] !== '' ) : ?>
                    <a class="pgs-btn" href="<?php echo esc_url( $o['wa_href'] ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( $s['btn_label'] . ' ' . $o['title'] ); ?>">
                        <?php echo self::wa_svg(); // phpcs:ignore ?>
                        <span><?php echo esc_html( $s['btn_label'] ); ?></span>
                    </a>
                <?php endif; ?>
            </div>
        </article>
        <?php
    }

    public static function bar_auto(): void {
        $s = Settings::get();
        if ( $s['bar_enabled'] && $s['bar_inject'] ) {
            echo self::bar_html( $s ); // phpcs:ignore WordPress.Security.EscapeOutput
        }
    }

    public static function bar_shortcode(): string {
        return self::bar_html( Settings::get(), true );
    }

    private static function bar_html( array $s, bool $force = false ): string {
        if ( self::$bar_printed || ! self::active( $s ) || ( ! $force && ! $s['bar_enabled'] ) ) {
            return '';
        }
        self::$bar_printed = true;
        wp_enqueue_style( 'pgco-sale' );
        wp_enqueue_script( 'pgco-sale' );

        $id = 'pgs-bar';
        ob_start(); ?>
        <aside id="<?php echo $id; ?>" class="pgs-bar" dir="rtl" data-pgs-bar data-pgs-hide-on-end data-pgs-key="<?php echo (int) $s['end_ts']; ?>" aria-label="فروش ویژه">
            <script>try{if(sessionStorage.getItem('pgsBarClosed:<?php echo (int) $s['end_ts']; ?>'))document.getElementById('<?php echo $id; ?>').hidden=true}catch(e){}</script>
            <div class="pgs-bar__inner">
                <?php if ( $s['bar_badge'] !== '' ) : ?><div class="pgs-bar__badge"><?php echo esc_html( $s['bar_badge'] ); ?></div><?php endif; ?>
                <div class="pgs-bar__content">
                    <p class="pgs-bar__text">
                        <?php if ( $s['bar_text_1'] !== '' ) : ?><span><?php echo esc_html( $s['bar_text_1'] ); ?></span><?php endif; ?>
                        <?php if ( $s['bar_text_2'] !== '' ) : ?><strong><?php echo esc_html( $s['bar_text_2'] ); ?></strong><?php endif; ?>
                        <?php if ( $s['bar_text_3'] !== '' ) : ?><span><?php echo esc_html( $s['bar_text_3'] ); ?></span><?php endif; ?>
                    </p>
                    <?php echo self::timer( $s, 'bar' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </div>
                <?php if ( $s['bar_link_label'] !== '' ) : ?>
                    <a class="pgs-bar__link" href="<?php echo esc_url( Settings::section_url( $s ) ); ?>" data-pgs-goto>
                        <span><?php echo esc_html( $s['bar_link_label'] ); ?></span>
                        <?php echo self::arrow_svg( 'next' ); // phpcs:ignore ?>
                    </a>
                <?php endif; ?>
                <button type="button" class="pgs-bar__close" data-pgs-close aria-label="بستن نوار">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
            </div>
        </aside>
        <?php
        return ob_get_clean();
    }
}
