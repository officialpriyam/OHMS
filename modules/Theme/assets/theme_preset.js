/**
 * OHMS.
 *
 * @copyright OHMS, Inc (https://www.OHMS.org)
 * @license   Apache-2.0
 *
 * Copyright OHMS, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 *
 * ---
 *
 * BoxBilling.
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */
$(function() {
    var themePresetData = window.themePresetData || {};

    $('#theme-preset-select').on('change', function() {
        bb.post('admin/theme/preset_select', {
            code: $(this).data('theme-code'),
            preset: $(this).val()
        }, function() {
            bb.reload();
        });
    });

    $('#theme-settings fieldset').each(function() {
        $('<h3 class="section-header collapsed"/>')
            .text($(this).find('legend').text())
            .insertBefore($(this));
    });

    $('#theme-settings h3.section-header').toggle(function() {
        $(this).removeClass('collapsed').next().show();
    }, function() {
        $(this).addClass('collapsed').next().hide();
    });

    $('#theme-settings h3.section-header:first').click();

    $("#theme-settings select.page").each(function() {
        var sel = $(this);
        sel.append('<option value="">None</option>');

        $.each(themePresetData.pagePairs || {}, function(index, value) {
            sel.append('<option value="' + index + '">' + value + '</option>');
        });
    });

    $("#theme-settings select.snippet").each(function() {
        var sel = $(this);
        sel.append('<option value="">None</option>');

        $.each(themePresetData.snippets || {}, function(index, value) {
            sel.append('<option value="' + index + '">' + value + '</option>');
        });
    });

    $("#theme-settings select.product").each(function() {
        var sel = $(this);
        sel.append('<option value="">None</option>');

        $.each(themePresetData.productPairs || {}, function(index, value) {
            sel.append('<option value="' + index + '">' + value + '</option>');
        });
    });

    $("#theme-settings select.orderform").each(function() {
        var sel = $(this);
        sel.append('<option value="">Use default</option>');

        $.each(themePresetData.orderformPairs || {}, function(index, value) {
            sel.append('<option value="' + index + '">' + value + '</option>');
        });
    });

    $.each(themePresetData.settings || {}, function(index, value) {
        var el = $('#theme-settings *[name="' + index + '"]');

        if (el.attr('type') == 'radio') {
            el.filter('[value="' + value + '"]').attr('checked', true);
        } else if (el.attr('type') == 'checkbox') {
            el.attr('checked', true);
        } else {
            el.val(value);
        }
    });

    $.each(themePresetData.uploaded || [], function(index, file) {
        var input = $('#theme-settings input[name="' + file.name + '"]');
        $('<div class="asset">')
            .html('<a href="' + file.url + '" target="_blank">' + file.name + '</a>')
            .insertAfter(input);
    });

    $("#theme-settings input.color").spectrum({
        showInput: true,
        showButtons: true,
        showAlpha: false,
        clickoutFiresChange: true,
        theme: "sp-OHMS",
        showInitial: true
    });
});

