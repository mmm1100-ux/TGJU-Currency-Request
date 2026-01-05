jQuery(document).ready(function ($) {
  // برای هر بلوک فرم به صورت مستقل اسکریپت را مقداردهی کن
  $('.tgju-cr').each(function () {
    var $wrap = $(this);
    var $form = $wrap.find('.tgju-currency-request-form');

    // عناصر داخل همین فرم
    var $country  = $form.find('.tgju-country-select');
    var $currency = $form.find('.tgju-currency-select');
    var $amount   = $form.find('.tgju-amount-input');
    var $output   = $form.find('.tgju-converted-output');
    var $submit   = $form.find('.tgju-submit-button');

    // مودال‌های مخصوص همین فرم
    var $loginModal   = $wrap.find('.tgju-login-modal');
    var $successModal = $wrap.find('.tgju-success-modal');
    var $detailsModal = $wrap.find('.tgju-details-modal');

    var current = {};

    function show($m){ $m.fadeIn(200); }
    function hide($m){ $m.fadeOut(200); }

    function updateConvertedValue() {
      var currency = $currency.val();
      var amount = parseFloat($amount.val());
      if (!currency || !amount || amount <= 0) {
        $output.text('—').data('converted', 0);
        return;
      }
      $.post(
        tgjuCurrencyRequest.ajax_url,
        {
          action: 'tgju_convert_currency',
          currency: currency,
          amount: amount,
          nonce: tgjuCurrencyRequest.nonce
        },
        function (response) {
          if (response && response.success) {
            var value = parseFloat(response.data.converted);
            $output.data('converted', value);
            var formatted = value.toLocaleString('fa-IR', { maximumFractionDigits: 1 });
            $output.text(formatted + ' تومان');
          } else {
            $output.data('converted', 0);
            $output.text('خطا در دریافت نرخ');
          }
        }
      );
    }

    // رویدادها فقط داخل همین فرم
    $currency.on('change', updateConvertedValue);
    $amount.on('input', updateConvertedValue);

    $wrap.find('.tgju-login-close').on('click', function(){ hide($loginModal); });
    $wrap.find('.tgju-success-close').on('click', function(){ hide($successModal); });
    $wrap.find('.tgju-login-redirect').on('click', function(){ window.location.href = tgjuCurrencyRequest.login_url; });

    $submit.on('click', function (e) {
      e.preventDefault();
      var country   = $country.val();
      var currency  = $currency.val();
      var amount    = parseFloat($amount.val());
      var converted = parseFloat($output.data('converted'));

      if (!country || !currency || !amount || isNaN(converted) || converted <= 0) {
        $successModal.find('.tgju-modal-content h3').text('خطا');
        $successModal.find('.tgju-modal-content p').text('لطفاً تمام فیلد‌ها را تکمیل کنید.');
        show($successModal);
        return;
      }
      if (!tgjuCurrencyRequest.is_logged_in) {
        show($loginModal);
        return;
      }

      current = { country, currency, amount, converted };
      $detailsModal.find('.tgju-detail-name').val('');
      $detailsModal.find('.tgju-detail-phone').val('');
      $detailsModal.find('.tgju-detail-email').val('');
      show($detailsModal);
    });

    $wrap.find('.tgju-details-close').on('click', function(){ hide($detailsModal); });

    $wrap.find('.tgju-details-submit').on('click', function (e) {
      e.preventDefault();
      var name  = $detailsModal.find('.tgju-detail-name').val().trim();
      var phone = $detailsModal.find('.tgju-detail-phone').val().trim();
      var email = $detailsModal.find('.tgju-detail-email').val().trim();
      if (!name || !phone || !email) {
        $successModal.find('.tgju-modal-content h3').text('خطا');
        $successModal.find('.tgju-modal-content p').text('لطفاً تمام اطلاعات را وارد کنید.');
        show($successModal);
        return;
      }
      $.post(
        tgjuCurrencyRequest.ajax_url,
        {
          action: 'tgju_submit_request',
          country:   current.country,
          currency:  current.currency,
          amount:    current.amount,
          converted: current.converted,
          name, phone, email,
          nonce: tgjuCurrencyRequest.nonce
        },
        function (response) {
          if (response && response.success) {
            hide($detailsModal);
            $successModal.find('.tgju-modal-content h3').text('موفقیت');
            $successModal.find('.tgju-modal-content p').text(response.data);
            show($successModal);
          } else {
            var errorMessage = (response && response.data) ? response.data : 'خطا در ثبت درخواست';
            $successModal.find('.tgju-modal-content h3').text('خطا');
            $successModal.find('.tgju-modal-content p').text(errorMessage);
            show($successModal);
          }
        }
      );
    });

    // بار اول
    updateConvertedValue();
  });
});
