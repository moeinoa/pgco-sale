<?php
namespace PGCO_Sale;

defined( 'ABSPATH' ) || exit;

/** 'Special sale' settings page in the admin panel */
class Admin {

    private const SLUG = 'pgco-sale';

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
        add_action( 'admin_post_pgco_sale_save', [ __CLASS__, 'save' ] );
        add_action( 'wp_ajax_pgco_sale_rows', [ __CLASS__, 'ajax_rows' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'assets' ] );
    }

    public static function menu(): void {
        add_menu_page( 'فروش ویژه', 'فروش ویژه', 'manage_options', self::SLUG, [ __CLASS__, 'page' ], 'dashicons-tag', 58 );
    }

    public static function assets( string $hook ): void {
        if ( $hook !== 'toplevel_page_' . self::SLUG ) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_style( 'pgco-sale-admin', PGS_URL . 'assets/css/admin.css', [], PGS_VERSION );
        wp_enqueue_script( 'pgco-sale-admin', PGS_URL . 'assets/js/admin.js', [], PGS_VERSION, true );
        wp_localize_script( 'pgco-sale-admin', 'PGS_ADMIN', [
            'ajax'  => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'pgco_sale_rows' ),
        ] );
    }

    public static function save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'دسترسی ندارید.', 403 );
        }
        check_admin_referer( 'pgco_sale_save' );
        $raw = isset( $_POST['pgco_sale'] ) ? (array) wp_unslash( $_POST['pgco_sale'] ) : [];
        update_option( PGS_OPTION, Settings::sanitize( $raw ), false );
        wp_safe_redirect( add_query_arg( [ 'page' => self::SLUG, 'saved' => 1 ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /** Rows of a table for the picker */
    public static function ajax_rows(): void {
        check_ajax_referer( 'pgco_sale_rows', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( null, 403 );
        }
        $id    = absint( $_GET['table'] ?? 0 );
        $table = Tables::get( $id );
        $rows  = [];
        foreach ( $table['rows'] as $r ) {
            $rows[] = [
                'key'      => $r['key'],
                'label'    => implode( ' | ', array_filter( $r['cells'], 'strlen' ) ),
                'price'    => $r['price'] ? Offer::format_price( $r['price'], ',' ) : '',
                'title'    => Tables::suggest_title( $table, $r ),
                'unit'     => Tables::suggest_unit( $table, $r ),
                'disabled' => ! $r['price'],
            ];
        }
        wp_send_json_success( [ 'name' => get_the_title( $id ), 'rows' => $rows ] );
    }

    /* ───────── form ───────── */

    private static function field( string $name, string $label, string $value, string $hint = '', string $type = 'text', string $extra = '' ): void {
        printf(
            '<div class="pgs-f"><label>%s<input type="%s" name="pgco_sale[%s]" value="%s" %s></label>%s</div>',
            esc_html( $label ), esc_attr( $type ), esc_attr( $name ), esc_attr( $value ), $extra, // phpcs:ignore WordPress.Security.EscapeOutput
            $hint !== '' ? '<p class="description">' . esc_html( $hint ) . '</p>' : ''
        );
    }

    private static function media( string $input_name, int $id, string $label ): void {
        $url = $id ? wp_get_attachment_image_url( $id, 'thumbnail' ) : '';
        ?>
        <div class="pgs-media" data-pgs-media>
            <input type="hidden" name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo (int) $id; ?>">
            <span class="pgs-media__thumb"><?php if ( $url ) : ?><img src="<?php echo esc_url( $url ); ?>" alt=""><?php endif; ?></span>
            <button type="button" class="button" data-pgs-pick><?php echo esc_html( $label ); ?></button>
            <button type="button" class="button-link pgs-media__clear" data-pgs-clear <?php echo $id ? '' : 'hidden'; ?>>حذف</button>
        </div>
        <?php
    }

    private static function item_row( string|int $i, array $it, array $s ): void {
        $offer   = $it['row_key'] !== '' ? Offer::resolve( $it, $s ) : null;
        $missing = $it['row_key'] !== '' && ! $offer;
        $n       = "pgco_sale[items][$i]";
        ?>
        <li class="pgs-item<?php echo $missing ? ' is-missing' : ''; ?>" data-pgs-item draggable="false">
            <div class="pgs-item__bar">
                <span class="pgs-item__handle" data-pgs-handle title="کشیدن برای جابه‌جایی">⠿</span>
                <strong class="pgs-item__name" data-pgs-name><?php echo esc_html( $it['title'] ?: $it['source'] ); ?></strong>
                <span class="pgs-item__live" data-pgs-live>
                    <?php if ( $offer ) : ?>
                        قیمت زنده: <?php echo esc_html( $offer['old'] ?: $offer['new'] ); ?><?php echo $offer['old'] ? ' ← ' . esc_html( $offer['new'] ) . ' (' . esc_html( $offer['percent'] ) . ')' : ''; ?>
                    <?php elseif ( $missing ) : ?>
                        ⚠ ردیف در جدول پیدا نشد یا قیمت عددی ندارد؛ در سایت نمایش داده نمی‌شود.
                    <?php endif; ?>
                </span>
                <button type="button" class="button-link" data-pgs-up title="بالا">▲</button>
                <button type="button" class="button-link" data-pgs-down title="پایین">▼</button>
                <button type="button" class="button-link button-link-delete" data-pgs-remove>حذف</button>
            </div>
            <div class="pgs-item__grid">
                <input type="hidden" name="<?php echo $n; ?>[table_id]" value="<?php echo (int) $it['table_id']; ?>">
                <input type="hidden" name="<?php echo $n; ?>[row_key]" value="<?php echo esc_attr( $it['row_key'] ); ?>">
                <input type="hidden" name="<?php echo $n; ?>[source]" value="<?php echo esc_attr( $it['source'] ); ?>">
                <label class="pgs-span2">عنوان کارت<input type="text" name="<?php echo $n; ?>[title]" value="<?php echo esc_attr( $it['title'] ); ?>" data-pgs-title></label>
                <div><span class="pgs-lbl">عکس</span><?php self::media( $n . '[image]', (int) $it['image'], 'انتخاب' ); ?></div>
                <label>واحد قیمت<input type="text" name="<?php echo $n; ?>[unit]" value="<?php echo esc_attr( $it['unit'] ); ?>"></label>
                <div class="pgs-mode">
                    <span class="pgs-lbl">تخفیف</span>
                    <label><input type="radio" name="<?php echo $n; ?>[mode]" value="percent" <?php checked( $it['mode'], 'percent' ); ?> data-pgs-mode> درصدی</label>
                    <label><input type="radio" name="<?php echo $n; ?>[mode]" value="manual" <?php checked( $it['mode'], 'manual' ); ?> data-pgs-mode> قیمت دستی</label>
                </div>
                <label data-pgs-when="percent">درصد تخفیف<input type="number" min="0" max="95" step="0.5" name="<?php echo $n; ?>[percent]" value="<?php echo esc_attr( (string) $it['percent'] ); ?>"></label>
                <label data-pgs-when="manual">قیمت جدید (تومان)<input type="text" inputmode="numeric" name="<?php echo $n; ?>[manual]" value="<?php echo $it['manual'] ? esc_attr( (string) $it['manual'] ) : ''; ?>"></label>
                <details class="pgs-span2">
                    <summary>واتساپ این محصول (اختیاری؛ خالی = تنظیم کلی)</summary>
                    <label>آدرس لینک<input type="url" dir="ltr" name="<?php echo $n; ?>[wa_url]" value="<?php echo esc_attr( $it['wa_url'] ); ?>" placeholder="https://wa.me/98912..."></label>
                    <label>متن پیام<textarea rows="2" name="<?php echo $n; ?>[wa_message]"><?php echo esc_textarea( $it['wa_message'] ); ?></textarea></label>
                </details>
            </div>
        </li>
        <?php
    }

    public static function page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $s    = Settings::get();
        $new  = [ 'table_id' => 0, 'row_key' => '', 'source' => '', 'title' => '', 'image' => 0, 'mode' => 'percent', 'percent' => 20, 'manual' => 0, 'unit' => 'تومان', 'wa_url' => '', 'wa_message' => '' ];
        ?>
        <div class="wrap pgs-admin" dir="rtl">
            <h1>فروش ویژه</h1>
            <?php if ( isset( $_GET['saved'] ) ) : ?><div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div><?php endif; ?>
            <p>شورتکد سکشن: <code>[pgco_sale]</code> — شورتکد نوار: <code>[pgco_sale_bar]</code> (اگر «نمایش خودکار بالای سایت» روشن باشد نیازی به آن نیست).</p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="pgco_sale_save">
                <?php wp_nonce_field( 'pgco_sale_save' ); ?>

                <div class="pgs-card-box">
                    <h2>محصولات</h2>
                    <div class="pgs-picker">
                        <div class="pgs-picker__top">
                            <select id="pgs-table">
                                <option value="">— انتخاب جدول —</option>
                                <?php foreach ( Tables::choices() as $id => $t ) : ?><option value="<?php echo (int) $id; ?>"><?php echo esc_html( $t ); ?></option><?php endforeach; ?>
                            </select>
                            <button type="button" class="button" id="pgs-load">بارگذاری ردیف‌ها</button>
                        </div>
                        <div id="pgs-rows-wrap" hidden>
                            <div class="pgs-picker__tools">
                                <input type="search" id="pgs-filter" placeholder="جستجو در ردیف‌ها…">
                                <label><input type="checkbox" id="pgs-all"> انتخاب همهٔ ردیف‌های نمایان</label>
                                <span id="pgs-count"></span>
                            </div>
                            <ul id="pgs-rows"></ul>
                            <div class="pgs-picker__defaults">
                                <strong>مقدار پیش‌فرض برای موارد افزوده‌شده:</strong>
                                <label>درصد تخفیف <input type="number" id="pgs-def-percent" value="20" min="0" max="95" step="0.5"></label>
                                <div data-pgs-media id="pgs-def-image-wrap" class="pgs-media">
                                    <input type="hidden" id="pgs-def-image" value="0">
                                    <span class="pgs-media__thumb"></span>
                                    <button type="button" class="button" data-pgs-pick>عکس پیش‌فرض</button>
                                    <button type="button" class="button-link pgs-media__clear" data-pgs-clear hidden>حذف</button>
                                </div>
                                <button type="button" class="button button-primary" id="pgs-add">افزودن انتخاب‌شده‌ها</button>
                            </div>
                        </div>
                    </div>

                    <ul id="pgs-items" class="pgs-items">
                        <?php foreach ( $s['items'] as $i => $it ) { self::item_row( $i, $it + $new, $s ); } ?>
                    </ul>
                    <p id="pgs-empty" class="description" <?php echo $s['items'] ? 'hidden' : ''; ?>>هنوز محصولی انتخاب نشده است.</p>
                    <template id="pgs-item-tpl"><?php self::item_row( '__i__', $new, $s ); ?></template>
                </div>

                <div class="pgs-card-box">
                    <h2>سکشن</h2>
                    <div class="pgs-grid">
                        <?php
                        self::field( 'title', 'عنوان', $s['title'] );
                        self::field( 'subtitle', 'زیرعنوان', $s['subtitle'], '{date} با تاریخ پایان (شمسی) جایگزین می‌شود.' );
                        self::field( 'section_id', 'شناسهٔ سکشن (لنگر)', $s['section_id'], 'لینک نوار به #' . $s['section_id'] . ' می‌رود.', 'text', 'dir="ltr"' );
                        self::field( 'end_date', 'تاریخ پایان (شمسی)', $s['end_date'], 'مثال: 1405/07/15 — خالی = بدون تایمر', 'text', 'dir="ltr" placeholder="1405/07/15"' );
                        self::field( 'end_time', 'ساعت پایان', $s['end_time'], 'مثال: 23:59', 'text', 'dir="ltr" placeholder="23:59"' );
                        ?>
                        <div class="pgs-f"><label>گرد کردن قیمت جدید (درصدی)
                            <select name="pgco_sale[rounding]"><?php foreach ( [ 1 => 'بدون گرد کردن', 10 => 'به ۱۰ تومان', 100 => 'به ۱۰۰ تومان', 1000 => 'به ۱۰۰۰ تومان' ] as $v => $l ) { printf( '<option value="%d" %s>%s</option>', $v, selected( (int) $s['rounding'], $v, false ), esc_html( $l ) ); } ?></select></label></div>
                        <div class="pgs-f"><label>جداکنندهٔ هزارگان
                            <select name="pgco_sale[sep]"><?php foreach ( [ '.' => 'نقطه (۱۲۳.۴۵۶)', ',' => 'ویرگول انگلیسی (۱۲۳,۴۵۶)', '٬' => 'جداکنندهٔ فارسی (۱۲۳٬۴۵۶)' ] as $v => $l ) { printf( '<option value="%s" %s>%s</option>', esc_attr( $v ), selected( $s['sep'], $v, false ), esc_html( $l ) ); } ?></select></label></div>
                        <?php
                        ?>
                        <div class="pgs-f"><label class="pgs-check"><input type="checkbox" name="pgco_sale[autoplay]" value="1" <?php checked( $s['autoplay'] ); ?>> حرکت خودکار اسلایدر</label></div>
                        <?php
                        self::field( 'autoplay_sec', 'فاصلهٔ حرکت خودکار (ثانیه)', (string) $s['autoplay_sec'], 'با نگه‌داشتن ماوس، فوکوس یا لمس متوقف می‌شود.', 'number', 'min="2" max="30" step="0.5"' );
                        self::field( 'per_view', 'کارت در هر نما — دسکتاپ', (string) $s['per_view'], '', 'number', 'min="1" max="8" step="0.1"' );
                        self::field( 'per_tab', 'کارت در هر نما — تبلت', (string) $s['per_tab'], 'عدد اعشاری = نمایش بخشی از کارت بعدی', 'number', 'min="1" max="6" step="0.1"' );
                        self::field( 'per_mob', 'کارت در هر نما — موبایل', (string) $s['per_mob'], '', 'number', 'min="1" max="3" step="0.1"' );
                        ?>
                    </div>
                    <div class="pgs-grid">
                        <div class="pgs-f"><span class="pgs-lbl">تصویر پس‌زمینه (اختیاری)</span><?php self::media( 'pgco_sale[bg_image]', (int) $s['bg_image'], 'انتخاب' ); ?></div>
                    </div>
                    <h3>تصاویر تزئینی</h3>
                    <p class="description">موقعیت بر حسب درصد از چپ/بالای سکشن؛ عرض بر حسب پیکسل. (آیتم‌ها پشت کارت‌ها قرار می‌گیرند.)</p>
                    <ul id="pgs-decor" class="pgs-decor">
                        <?php foreach ( $s['decor'] as $i => $dc ) { self::decor_row( $i, $dc ); } ?>
                    </ul>
                    <button type="button" class="button" id="pgs-decor-add">افزودن تصویر تزئینی</button>
                    <template id="pgs-decor-tpl"><?php self::decor_row( '__i__', [ 'image' => 0, 'left' => 5, 'top' => 10, 'width' => 160 ] ); ?></template>
                </div>

                <div class="pgs-card-box">
                    <h2>دکمهٔ واتساپ (تنظیم کلی)</h2>
                    <div class="pgs-grid">
                        <?php
                        self::field( 'btn_label', 'متن دکمه', $s['btn_label'] );
                        self::field( 'wa_url', 'آدرس لینک', $s['wa_url'], 'مثال: https://wa.me/989121234567 — اگر خالی باشد و برای محصول هم لینکی نباشد، دکمه نمایش داده نمی‌شود.', 'url', 'dir="ltr"' );
                        ?>
                        <div class="pgs-f pgs-span2"><label>متن پیام
                            <textarea name="pgco_sale[wa_message]" rows="2"><?php echo esc_textarea( $s['wa_message'] ); ?></textarea></label>
                            <p class="description">متغیرها: <code>{title}</code> عنوان کارت، <code>{price}</code> قیمت جدید. اگر آدرس بالا خودش <code>text=</code> داشته باشد، این متن اضافه نمی‌شود.</p></div>
                    </div>
                </div>

                <div class="pgs-card-box">
                    <h2>نوار بالای هدر</h2>
                    <div class="pgs-grid">
                        <label class="pgs-check"><input type="checkbox" name="pgco_sale[bar_enabled]" value="1" <?php checked( $s['bar_enabled'] ); ?>> فعال</label>
                        <label class="pgs-check"><input type="checkbox" name="pgco_sale[bar_inject]" value="1" <?php checked( $s['bar_inject'] ); ?>> نمایش خودکار بالای سایت (قلاب <code>wp_body_open</code>)</label>
                        <?php
                        self::field( 'bar_badge', 'متن بج قرمز', $s['bar_badge'] );
                        self::field( 'bar_text_1', 'متن اول', $s['bar_text_1'] );
                        self::field( 'bar_text_2', 'متن پررنگ', $s['bar_text_2'] );
                        self::field( 'bar_text_3', 'متن سوم', $s['bar_text_3'] );
                        self::field( 'bar_link_label', 'متن لینک', $s['bar_link_label'] );
                        self::field( 'bar_link_url', 'آدرس لینک', $s['bar_link_url'], 'خالی = رفتن نرم به سکشن همین صفحه (#' . $s['section_id'] . '). اگر سکشن در صفحهٔ دیگری است، آدرس کامل صفحه + #' . $s['section_id'] . ' بنویسید.', 'text', 'dir="ltr"' );
                        ?>
                    </div>
                </div>

                <?php submit_button( 'ذخیرهٔ تنظیمات' ); ?>
            </form>
        </div>
        <?php
    }

    private static function decor_row( string|int $i, array $dc ): void {
        $n = "pgco_sale[decor][$i]";
        ?>
        <li class="pgs-decor__row" data-pgs-decor-row>
            <?php self::media( $n . '[image]', (int) $dc['image'], 'تصویر' ); ?>
            <label>چپ٪<input type="number" step="1" name="<?php echo $n; ?>[left]" value="<?php echo esc_attr( (string) $dc['left'] ); ?>"></label>
            <label>بالا٪<input type="number" step="1" name="<?php echo $n; ?>[top]" value="<?php echo esc_attr( (string) $dc['top'] ); ?>"></label>
            <label>عرض (px)<input type="number" step="10" name="<?php echo $n; ?>[width]" value="<?php echo esc_attr( (string) $dc['width'] ); ?>"></label>
            <button type="button" class="button-link button-link-delete" data-pgs-decor-remove>حذف</button>
        </li>
        <?php
    }
}
