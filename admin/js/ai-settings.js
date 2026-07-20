jQuery(document).ready(function($) {
    'use strict';

    $('#vmp-ai-settings-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $notice = $('#vmp-ai-settings-notice');
        var formData = $form.serializeArray();
        var data = {
            action: 'vmp_admin_save_ai_settings'
        };

        // استخراج البيانات من النموذج
        $.each(formData, function(i, field) {
            if (field.name.startsWith('vmp_ai_settings[')) {
                var key = field.name.replace(/vmp_ai_settings\[([^\]]+)\]/, '$1');
                if (!data.vmp_ai_settings) data.vmp_ai_settings = {};
                data.vmp_ai_settings[key] = field.value;
            } else if (field.name === 'nonce') {
                data.nonce = field.value;
            }
        });

        // معالجة الحقول غير المحددة (checkbox)
        if (data.vmp_ai_settings) {
            if (data.vmp_ai_settings.cache_enabled === undefined) data.vmp_ai_settings.cache_enabled = '0';
            if (data.vmp_ai_settings.require_human_review === undefined) data.vmp_ai_settings.require_human_review = '0';
        }

        $btn.prop('disabled', true).text('جاري الحفظ...');
        $notice.hide();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            dataType: 'json'
        })
        .done(function(response) {
            if (response.success) {
                $notice.show().addClass('notice-success').html('<p>' + response.data.message + '</p>');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                $notice.show().addClass('notice-error').html('<p>' + response.data.message + '</p>');
            }
        })
        .fail(function(xhr) {
            console.error('AJAX Error:', xhr.responseText);
            $notice.show().addClass('notice-error').html('<p>حدث خطأ في الاتصال بالخادم. (Status: ' + xhr.status + ')</p>');
        })
        .always(function() {
            $btn.prop('disabled', false).text('حفظ الإعدادات');
        });
    });
});