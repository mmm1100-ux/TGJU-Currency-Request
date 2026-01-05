<?php
/**
 * Plugin Name: TGJU Currency Request
 * Description: Displays a simple currency converter form that uses the TGJU web service to calculate the value of a fiat currency in Iranian tomans.  Users can submit their request and an email will be sent to the site administrator for follow-up.  This plugin is intentionally lightweight and does not rely on WooCommerce.
 * Version: 1.2.1
 * Author: Meysam Delikhoun
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'TGJU_Currency_Request' ) ) {
  class TGJU_Currency_Request {

    public function __construct() {
      add_shortcode( 'tgju_currency_request', array( $this, 'render_form_shortcode' ) );
      add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

      add_action( 'wp_ajax_tgju_convert_currency', array( $this, 'handle_ajax_convert' ) );
      add_action( 'wp_ajax_nopriv_tgju_convert_currency', array( $this, 'handle_ajax_convert' ) );

      add_action( 'wp_ajax_tgju_submit_request', array( $this, 'handle_ajax_submit' ) );
      add_action( 'wp_ajax_nopriv_tgju_submit_request', array( $this, 'handle_ajax_submit' ) );

      add_action( 'init', array( $this, 'register_post_type' ) );
      add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
      add_filter( 'woocommerce_login_redirect', array( $this, 'tgju_wc_login_redirect' ), 10, 2 );
    }

    public function register_post_type() {
      $labels = array(
        'name'               => _x( 'درخواست‌ها', 'post type general name', 'tgju-currency-request' ),
        'singular_name'      => _x( 'درخواست', 'post type singular name', 'tgju-currency-request' ),
        'menu_name'          => _x( 'درخواست‌های TGJU', 'admin menu', 'tgju-currency-request' ),
        'name_admin_bar'     => _x( 'درخواست', 'add new on admin bar', 'tgju-currency-request' ),
        'add_new'            => _x( 'افزودن جدید', 'request', 'tgju-currency-request' ),
        'add_new_item'       => __( 'افزودن درخواست جدید', 'tgju-currency-request' ),
        'new_item'           => __( 'درخواست جدید', 'tgju-currency-request' ),
        'edit_item'          => __( 'ویرایش درخواست', 'tgju-currency-request' ),
        'view_item'          => __( 'مشاهده درخواست', 'tgju-currency-request' ),
        'all_items'          => __( 'تمام درخواست‌ها', 'tgju-currency-request' ),
        'search_items'       => __( 'جستجوی درخواست‌ها', 'tgju-currency-request' ),
        'not_found'          => __( 'درخواستی پیدا نشد.', 'tgju-currency-request' ),
        'not_found_in_trash' => __( 'درخواست یافت نشد در زباله‌دان.', 'tgju-currency-request' ),
      );

      $args = array(
        'labels'          => $labels,
        'public'          => false,
        'show_ui'         => false,
        'show_in_menu'    => false,
        'capability_type' => 'post',
        'supports'        => array( 'title' ),
        'has_archive'     => false,
      );
      register_post_type( 'tgju_request', $args );
    }

    public function register_admin_menu() {
      add_menu_page(
        __( 'TGJU درخواست‌ها', 'tgju-currency-request' ),
        __( 'TGJU درخواست‌ها', 'tgju-currency-request' ),
        'manage_options',
        'tgju-currency-request',
        array( $this, 'render_admin_page' ),
        'dashicons-feedback',
        81
      );
    }

    /** لینک ورود ووکامرس با پارامتر بازگشت */
    private function tgju_get_wc_login_url_with_redirect() {
      $my_account = function_exists('wc_get_page_permalink')
        ? wc_get_page_permalink('myaccount')
        : home_url('/my-account/');
      $current_url = isset($_SERVER['REQUEST_URI'])
        ? home_url($_SERVER['REQUEST_URI'])
        : get_permalink();
      return add_query_arg( array('redirect_to' => $current_url), $my_account );
    }

    /** احترام به redirect_to بعد از لاگین */
    public function tgju_wc_login_redirect( $redirect, $user ) {
      if ( isset($_REQUEST['redirect_to']) ) {
        $target = esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) );
        return wp_validate_redirect( $target, home_url('/') );
      }
      return $redirect;
    }

    /** صفحه ادمین (تنظیمات + لیست درخواست‌ها) */
    public function render_admin_page() {
      if ( isset( $_POST['tgju_email_submit'] ) && check_admin_referer( 'tgju_currency_request_save_email', 'tgju_currency_request_nonce_field' ) ) {
        $email = isset( $_POST['tgju_consultant_email'] ) ? sanitize_email( wp_unslash( $_POST['tgju_consultant_email'] ) ) : '';
        if ( $email ) {
          update_option( 'tgju_currency_request_email', $email );
          echo '<div class="updated notice"><p>' . esc_html__( 'ایمیل کارشناس ذخیره شد.', 'tgju-currency-request' ) . '</p></div>';
        } else {
          delete_option( 'tgju_currency_request_email' );
          echo '<div class="updated notice"><p>' . esc_html__( 'ایمیل کارشناس حذف شد.', 'tgju-currency-request' ) . '</p></div>';
        }
      }

      $consultant_email = get_option( 'tgju_currency_request_email', get_option( 'admin_email' ) ); ?>

      <div class="wrap">
        <h1><?php esc_html_e( 'تنظیمات پلاگین TGJU', 'tgju-currency-request' ); ?></h1>
        <h2 class="nav-tab-wrapper">
          <a href="#tgju-settings" class="nav-tab nav-tab-active" onclick="tgjuSwitchTab(event, 'tgju-settings')"><?php esc_html_e( 'تنظیمات', 'tgju-currency-request' ); ?></a>
          <a href="#tgju-requests" class="nav-tab" onclick="tgjuSwitchTab(event, 'tgju-requests')"><?php esc_html_e( 'درخواست‌ها', 'tgju-currency-request' ); ?></a>
        </h2>

        <div id="tgju-settings" class="tgju-tab-content" style="display:block;">
          <form method="post" action="">
            <?php wp_nonce_field( 'tgju_currency_request_save_email', 'tgju_currency_request_nonce_field' ); ?>
            <table class="form-table">
              <tr>
                <th scope="row"><label for="tgju_consultant_email"><?php esc_html_e( 'ایمیل کارشناس', 'tgju-currency-request' ); ?></label></th>
                <td>
                  <input type="email" class="regular-text" name="tgju_consultant_email" id="tgju_consultant_email" value="<?php echo esc_attr( $consultant_email ); ?>" />
                  <p class="description"><?php esc_html_e( 'آدرس ایمیلی که درخواست‌ها به آن ارسال می‌شود.', 'tgju-currency-request' ); ?></p>
                </td>
              </tr>
            </table>
            <p class="submit">
              <input type="submit" name="tgju_email_submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'ذخیره تغییرات', 'tgju-currency-request' ); ?>" />
            </p>
          </form>
        </div>

        <div id="tgju-requests" class="tgju-tab-content" style="display:none;">
          <h2><?php esc_html_e( 'لیست درخواست‌ها', 'tgju-currency-request' ); ?></h2>
          <?php
          $requests = get_posts( array(
            'post_type'   => 'tgju_request',
            'numberposts' => -1,
            'post_status' => 'publish',
            'orderby'     => 'date',
            'order'       => 'DESC',
          ) );

          if ( ! empty( $requests ) ) {
            echo '<table class="widefat striped"><thead><tr>';
            echo '<th>' . esc_html__( 'تاریخ', 'tgju-currency-request' ) . '</th>';
            echo '<th>' . esc_html__( 'مقصد', 'tgju-currency-request' ) . '</th>';
            echo '<th>' . esc_html__( 'ارز', 'tgju-currency-request' ) . '</th>';
            echo '<th>' . esc_html__( 'مقدار', 'tgju-currency-request' ) . '</th>';
            echo '<th>' . esc_html__( 'مبلغ (تومان)', 'tgju-currency-request' ) . '</th>';
            echo '<th>' . esc_html__( 'نام', 'tgju-currency-request' ) . '</th>';
            echo '<th>' . esc_html__( 'شماره تماس', 'tgju-currency-request' ) . '</th>';
            echo '<th>' . esc_html__( 'ایمیل', 'tgju-currency-request' ) . '</th>';
            echo '</tr></thead><tbody>';

            // نگاشت نام ارز برای نمایش فارسی
            $currency_names = array(
              'usd' => 'دلار آمریکا',
              'eur' => 'یورو',
              'aed' => 'درهم امارات',
              'gbp' => 'پوند انگلیس',
              'try' => 'لیر ترکیه',
              'cny' => 'یوآن چین',
              'cad' => 'دلار کانادا',
              'iqd' => 'دینار عراق',
              'kwd' => 'دینار کویت',
            );

            // نگاشت کشور: کد/نام انگلیسی → نام فارسی
            $country_names = array(
              'de' => 'آلمان', 'germany' => 'آلمان',
              'us' => 'آمریکا', 'usa' => 'آمریکا', 'united states' => 'آمریکا',
              'ca' => 'کانادا', 'canada' => 'کانادا',
              'tr' => 'ترکیه', 'turkey' => 'ترکیه',
              'kw' => 'کویت', 'kuwait' => 'کویت',
              'iq' => 'عراق', 'iraq' => 'عراق',
              'ir' => 'ایران', 'iran' => 'ایران',
              'dk' => 'دانمارک', 'denmark' => 'دانمارک',
              'ch' => 'سوئیس', 'switzerland' => 'سوئیس',
              'se' => 'سوئد', 'sweden' => 'سوئد',
              'ae' => 'امارات', 'uae' => 'امارات', 'united arab emirates' => 'امارات',
              'gb' => 'انگلیس', 'uk' => 'انگلیس', 'united kingdom' => 'انگلیس',
              'cn' => 'چین', 'china' => 'چین',
              // هر مورد جدیدی داشتید اینجا اضافه کنید
            );

            foreach ( $requests as $request ) {
              $country_raw  = get_post_meta( $request->ID, 'tgju_country', true );
              $currency     = get_post_meta( $request->ID, 'tgju_currency', true );
              $amount       = get_post_meta( $request->ID, 'tgju_amount', true );
              $converted    = get_post_meta( $request->ID, 'tgju_converted', true );
              $name         = get_post_meta( $request->ID, 'tgju_name', true );
              $phone        = get_post_meta( $request->ID, 'tgju_phone', true );
              $user_email   = get_post_meta( $request->ID, 'tgju_user_email', true );

              // نمایش فارسی کشور
              $country_key     = strtolower( trim( $country_raw ) );
              $country_display = isset( $country_names[ $country_key ] ) ? $country_names[ $country_key ] : $country_raw;

              // نمایش فارسی ارز
              $currency_display = isset( $currency_names[ $currency ] ) ? $currency_names[ $currency ] : $currency;

              echo '<tr>';
              echo '<td>' . esc_html( get_the_date( '', $request ) ) . '</td>';
              echo '<td>' . esc_html( $country_display ) . '</td>';
              echo '<td>' . esc_html( $currency_display ) . '</td>';
              echo '<td>' . esc_html( $amount ) . '</td>';
              echo '<td>' . esc_html( number_format_i18n( $converted ) ) . '</td>';
              echo '<td>' . esc_html( $name ) . '</td>';
              echo '<td>' . esc_html( $phone ) . '</td>';
              echo '<td>' . esc_html( $user_email ) . '</td>';
              echo '</tr>';
            }
            echo '</tbody></table>';
          } else {
            echo '<p>' . esc_html__( 'هیچ درخواستی ارسال نشده است.', 'tgju-currency-request' ) . '</p>';
          } ?>
        </div>
      </div>

      <script>
      function tgjuSwitchTab(event, tabId) {
        event.preventDefault();
        var tabs = document.querySelectorAll('.nav-tab');
        tabs.forEach(function(el){ el.classList.remove('nav-tab-active'); });
        event.target.classList.add('nav-tab-active');
        var contents = document.querySelectorAll('.tgju-tab-content');
        contents.forEach(function(c){ c.style.display = 'none'; });
        document.getElementById(tabId).style.display = 'block';
      }
      </script>
      <?php
    }

    /** بارگذاری CSS/JS فرانت‌اند */
    public function enqueue_assets() {
      if ( is_admin() ) return;

      wp_enqueue_style(
        'tgju-currency-request-css',
        plugin_dir_url( __FILE__ ) . 'assets/tgju-currency-request.css',
        array(),
        '1.1.0'
      );

      wp_enqueue_script(
        'tgju-currency-request-js',
        plugin_dir_url( __FILE__ ) . 'assets/tgju-currency-request.js',
        array( 'jquery' ),
        '1.1.0',
        true
      );

      wp_localize_script( 'tgju-currency-request-js', 'tgjuCurrencyRequest', array(
        'ajax_url'     => admin_url( 'admin-ajax.php' ),
        'nonce'        => wp_create_nonce( 'tgju_currency_request_nonce' ),
        'is_logged_in' => is_user_logged_in(),
        'login_url'    => $this->tgju_get_wc_login_url_with_redirect(),
      ) );
    }

    /** شورتکد: رندر فرم (هر نمونه با UID یکتا) */
    public function render_form_shortcode( $atts = [] ) {
      $atts = shortcode_atts([
        'country_label' => 'کشور مقصد',
      ], $atts, 'tgju_currency_request');

      static $instance = 0;
      $instance++;
      $uid = 'f' . $instance;

      ob_start(); ?>
      <div class="tgju-cr" id="tgju-cr-<?php echo esc_attr($uid); ?>" data-uid="<?php echo esc_attr($uid); ?>">
        <div class="tgju-card">
          <div id="tgju-currency-request-form-<?php echo esc_attr($uid); ?>" class="tgju-currency-request-form tgju-row" data-uid="<?php echo esc_attr($uid); ?>">
            <div class="tgju-field">
              <label for="tgju-country-select-<?php echo esc_attr($uid); ?>" class="tgju-label"><?php echo esc_html( $atts['country_label'] ); ?></label>
              <select id="tgju-country-select-<?php echo esc_attr($uid); ?>" class="tgju-input tgju-country-select">
                <option value="de">آلمان</option>
                <option value="us">آمریکا</option>
                <option value="ca">کانادا</option>
                <option value="tr">ترکیه</option>
                <option value="kw">کویت</option>
                <option value="iq">عراق</option>
              </select>
            </div>

            <div class="tgju-field">
              <label for="tgju-currency-select-<?php echo esc_attr($uid); ?>" class="tgju-label">ارز فیات</label>
              <select id="tgju-currency-select-<?php echo esc_attr($uid); ?>" class="tgju-input tgju-currency-select">
                <option value="usd">دلار آمریکا</option>
                <option value="eur">یورو</option>
                <option value="aed">درهم امارات</option>
                <option value="gbp">پوند انگلیس</option>
                <option value="try">لیر ترکیه</option>
                <option value="cny">یوآن چین</option>
                <option value="cad">دلار کانادا</option>
                <option value="iqd">دینار عراق</option>
                <option value="kwd">دینار کویت</option>
              </select>
            </div>

            <div class="tgju-field tgju-field--sm">
              <label for="tgju-amount-input-<?php echo esc_attr($uid); ?>" class="tgju-label">مقدار</label>
              <input type="number" id="tgju-amount-input-<?php echo esc_attr($uid); ?>" class="tgju-input tgju-amount-input" min="0" step="any" value="1" />
            </div>

            <div class="tgju-field tgju-field--result">
              <label class="tgju-label">مبلغ به تومان</label>
              <div id="tgju-converted-output-<?php echo esc_attr($uid); ?>" class="tgju-output tgju-converted-output">—</div>
            </div>

            <div class="tgju-field tgju-field--btn">
              <label class="tgju-label tgju-label--hidden">ثبت</label>
              <button id="tgju-submit-button-<?php echo esc_attr($uid); ?>" type="button" class="tgju-button tgju-submit-button">ثبت درخواست</button>
            </div>
          </div>
        </div>

        <!-- Modals (scoped to this form) -->
        <div id="tgju-login-modal-<?php echo esc_attr($uid); ?>" class="tgju-modal tgju-login-modal">
          <div class="tgju-modal-content">
            <h3>ورود لازم است</h3>
            <p>برای ثبت درخواست، ابتدا باید وارد حساب کاربری خود شوید.</p>
            <div class="tgju-modal-actions">
              <button id="tgju-login-close-<?php echo esc_attr($uid); ?>" class="tgju-modal-btn tgju-login-close">بستن</button>
              <button id="tgju-login-redirect-<?php echo esc_attr($uid); ?>" class="tgju-modal-btn tgju-modal-primary tgju-login-redirect">ورود</button>
            </div>
          </div>
        </div>

        <div id="tgju-success-modal-<?php echo esc_attr($uid); ?>" class="tgju-modal tgju-success-modal">
          <div class="tgju-modal-content">
            <h3>درخواست ثبت شد</h3>
            <p>درخواست شما با موفقیت ثبت شد. کارشناسان ما در اسرع وقت با شما تماس خواهند گرفت.</p>
            <div class="tgju-modal-actions">
              <button id="tgju-success-close-<?php echo esc_attr($uid); ?>" class="tgju-modal-btn tgju-modal-primary tgju-success-close">باشه</button>
            </div>
          </div>
        </div>

        <div id="tgju-details-modal-<?php echo esc_attr($uid); ?>" class="tgju-modal tgju-details-modal">
          <div class="tgju-modal-content">
            <h3>اطلاعات تکمیلی</h3>
            <p>لطفاً اطلاعات زیر را برای تماس وارد کنید:</p>
            <div class="tgju-modal-field">
              <label for="tgju-detail-name-<?php echo esc_attr($uid); ?>">نام و نام خانوادگی</label>
              <input type="text" id="tgju-detail-name-<?php echo esc_attr($uid); ?>" class="tgju-modal-input tgju-detail-name" />
            </div>
            <div class="tgju-modal-field">
              <label for="tgju-detail-phone-<?php echo esc_attr($uid); ?>">شماره تماس</label>
              <input type="text" id="tgju-detail-phone-<?php echo esc_attr($uid); ?>" class="tgju-modal-input tgju-detail-phone" />
            </div>
            <div class="tgju-modal-field">
              <label for="tgju-detail-email-<?php echo esc_attr($uid); ?>">ایمیل</label>
              <input type="email" id="tgju-detail-email-<?php echo esc_attr($uid); ?>" class="tgju-modal-input tgju-detail-email" placeholder="مثلاً name@example.com" inputmode="email" autocomplete="email" />
            </div>
            <div class="tgju-modal-actions">
              <button id="tgju-details-close-<?php echo esc_attr($uid); ?>" class="tgju-modal-btn tgju-details-close">بستن</button>
              <button id="tgju-details-submit-<?php echo esc_attr($uid); ?>" class="tgju-modal-btn tgju-modal-primary tgju-details-submit">ارسال</button>
            </div>
          </div>
        </div>
      </div>
      <?php
      return ob_get_clean();
    }

    /** AJAX: تبدیل نرخ */
    public function handle_ajax_convert() {
      check_ajax_referer( 'tgju_currency_request_nonce', 'nonce' );

      $currency = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : '';
      $amount   = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0.0;

      if ( empty( $currency ) || $amount <= 0 ) {
        wp_send_json_error( 'ورودی نامعتبر است.' );
      }

      $mapping = array(
        'usd' => 'price_dollar_rl',
        'eur' => 'price_eur',
        'aed' => 'price_aed',
        'gbp' => 'price_gbp',
        'try' => 'price_try',
        'cny' => 'price_cny',
        'cad' => 'price_cad',
        'iqd' => 'price_iqd',
        'kwd' => 'price_kwd',
      );

      if ( ! isset( $mapping[ $currency ] ) ) {
        wp_send_json_error( 'کد ارز پشتیبانی نمی‌شود.' );
      }

      $indicator = $mapping[ $currency ];
      $url = 'https://api.tgju.org/v1/widget/tmp?keys=' . rawurlencode( $indicator );

      $response = wp_remote_get( $url, array( 'timeout' => 15 ) );
      if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'ارتباط با سرور TGJU برقرار نشد.' );
      }

      $body = wp_remote_retrieve_body( $response );
      $data = json_decode( $body, true );

      if ( empty( $data['response']['indicators'] ) || ! is_array( $data['response']['indicators'] ) ) {
        wp_send_json_error( 'نرخ یافت نشد.' );
      }

      $indicator_data = $data['response']['indicators'][0];
      if ( ! isset( $indicator_data['p'] ) ) {
        wp_send_json_error( 'داده نرخ ناقص است.' );
      }

      $price_in_rials  = floatval( $indicator_data['p'] );
      $price_in_tomans = $price_in_rials / 10;
      $converted       = $price_in_tomans * $amount;

      wp_send_json_success( array( 'converted' => $converted ) );
    }

    /** AJAX: ارسال درخواست */
    public function handle_ajax_submit() {
      check_ajax_referer( 'tgju_currency_request_nonce', 'nonce' );

      $country    = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
      $currency   = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : '';
      $amount     = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0.0;
      $converted  = isset( $_POST['converted'] ) ? floatval( $_POST['converted'] ) : 0.0;
      $name       = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
      $phone      = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
      $user_email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

      if ( empty( $country ) || empty( $currency ) || $amount <= 0 || $converted <= 0 ) {
        wp_send_json_error( 'اطلاعات ورودی نامعتبر است.' );
      }
      if ( ! empty( $name ) || ! empty( $phone ) || ! empty( $user_email ) ) {
        if ( empty( $name ) || empty( $phone ) || empty( $user_email ) ) {
          wp_send_json_error( 'تمام اطلاعات تکمیلی باید وارد شوند.' );
        }
      }

      $consultant_email = get_option( 'tgju_currency_request_email', get_option( 'admin_email' ) );
      $subject = __( 'درخواست تبدیل ارز', 'tgju-currency-request' );

      $message  = "کاربر جدیدی درخواست تبدیل ارز ارسال کرده است:\n\n";
      $message .= "کشور مقصد: {$country}\n";
      $message .= "کد ارز: {$currency}\n";
      $message .= "مقدار: {$amount}\n";
      $message .= "مبلغ به تومان: {$converted}\n";
      if ( ! empty( $name ) )  { $message .= "نام و نام خانوادگی: {$name}\n"; }
      if ( ! empty( $phone ) ) { $message .= "شماره تماس: {$phone}\n"; }
      if ( ! empty( $user_email ) ) { $message .= "ایمیل کاربر: {$user_email}\n"; }
      $message .= "\nلطفاً با این کاربر تماس بگیرید.";

      wp_mail( $consultant_email, $subject, $message );

      $post_id = wp_insert_post( array(
        'post_type'   => 'tgju_request',
        'post_title'  => sprintf( '%s %s – %s', $amount, strtoupper( $currency ), $country ),
        'post_status' => 'publish',
      ) );
      if ( ! is_wp_error( $post_id ) ) {
        update_post_meta( $post_id, 'tgju_country', $country );
        update_post_meta( $post_id, 'tgju_currency', $currency );
        update_post_meta( $post_id, 'tgju_amount', $amount );
        update_post_meta( $post_id, 'tgju_converted', $converted );
        if ( ! empty( $name ) )       { update_post_meta( $post_id, 'tgju_name', $name ); }
        if ( ! empty( $phone ) )      { update_post_meta( $post_id, 'tgju_phone', $phone ); }
        if ( ! empty( $user_email ) ) { update_post_meta( $post_id, 'tgju_user_email', $user_email ); }
      }

      wp_send_json_success( __( 'درخواست شما با موفقیت ثبت شد.', 'tgju-currency-request' ) );
    }
  }

  new TGJU_Currency_Request();
}
