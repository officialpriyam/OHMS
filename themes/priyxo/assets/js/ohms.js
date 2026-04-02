var bb = {
    post: function(url, params, jsonp) {
        $.ajax({
            type: "POST",
            url: bb.restUrl(url),
            data: params,
            dataType: 'json',
            error: function(jqXHR, textStatus, e) {
                var message = (jqXHR.responseJSON && jqXHR.responseJSON.error) ? jqXHR.responseJSON.error.message : e;
                OHMS.message(message, 'error');
            },
            success: function(data) {
                if (data.error) {
                    OHMS.message(data.error.message, 'error');
                } else {
                    if (typeof jsonp === 'function') {
                        return jsonp(data.result);
                    } else if (window.hasOwnProperty('console')) {
                        console.log(data.result);
                    }
                }
            }
        });
    },
    get: function(url, params, jsonp) {
        $.ajax({
            type: "GET",
            url: bb.restUrl(url),
            data: params,
            dataType: 'json',
            error: function(jqXHR, textStatus, e) {
                var message = (jqXHR.responseJSON && jqXHR.responseJSON.error) ? jqXHR.responseJSON.error.message : e;
                OHMS.message(message, 'error');
            },
            success: function(data) {
                if (data.error) {
                    OHMS.message(data.error.message, 'error');
                } else {
                    if (typeof jsonp === 'function') {
                        return jsonp(data.result);
                    } else if(window.hasOwnProperty('console')) {
                        console.log(data.result);
                    }
                }
            }
        });
    },
    restUrl: function(url) {
        if (url.indexOf('http://') > -1 || url.indexOf('https://') > -1) {
            return url;
        }

        var base = $('meta[property="bb:url"]').attr("content");
        if (base && base.slice(-1) !== '/') {
            base += '/';
        }

        // Remove leading slash if any
        url = url.replace(/^\/+/, '');
        
        // If url already contains 'api/', don't double it
        if (url.indexOf('api/') === 0) {
            return base + 'index.php?_url=/' + url;
        }

        return base + 'index.php?_url=/api/' + url;
    },
    reload: function() {
        window.location.href = window.location.href;
    },
    redirect: function(url) {
        if (url === undefined) {
            window.location = $('meta[property="bb:url"]').attr("content");
        } else {
            window.location = url;
        }
    },
    currency: function(price, rate, title, multiply) {
        price = parseFloat(price) * parseFloat(rate);
        if (multiply !== undefined) {
            price = price * multiply;
        }

        return price.toFixed(2) + " " + title;
    },
    /**
     * @deprecated Will be removed after testing. Use OHMS.message()
     */
    msg: function(txt, type) {
        bootPriyx.alert(txt);
    },
    apiForm: function() {
        $(document).on('submit', "form.api-form, form.api_form", function(e) {
            e.preventDefault();
            var redirect = $(this).attr('data-api-redirect');
            var jsonp = $(this).attr('data-api-jsonp');
            var msg = $(this).attr('data-api-msg');
            var reload = $(this).attr('data-api-reload');
			var tocreated = $(this).attr('data-api-tocreated');
            var url = $(this).attr('action');

            if ($(this).attr('data-api-url')) {
                url = $(this).attr('data-api-url');
            }

            bb.post(url, $(this).serialize(), function(result) {
                if (reload !== undefined) {
                    bb.reload();

                    return;
                }

                if (redirect !== undefined) {
                    bb.redirect(redirect);

                    return;
                }

				if (tocreated !== undefined) {
					bb.redirect(tocreated.replace(/['"]/g, '') + '/' + result);

					return;
				}

                if (msg !== undefined) {
                    OHMS.message(msg);

                    return;
                }

                if (jsonp !== undefined && window.hasOwnProperty(jsonp)) {
                    return window[jsonp](result);
                }
            });

            return false;
        });
    },
    apiLink: function() {
        $(document).on('click', "a.api, a.api-link", function(e) {
            e.preventDefault();
            var redirect = $(this).attr('data-api-redirect');
            var reload = $(this).attr('data-api-reload');
            var msg = $(this).attr('data-api-msg');
            var jsonp = $(this).attr('data-api-jsonp');
			var tocreated = $(this).attr('data-api-tocreated');

            bb.get($(this).attr('href'), {}, function(result) {
                if (msg !== undefined) {
                    OHMS.message(msg);

                    return;
                }

                if (reload !== undefined) {
                    bb.reload();

                    return;
                }

                if (jsonp !== undefined && window.hasOwnProperty(jsonp)) {
                    return window[jsonp](result);
                }

                if (redirect !== undefined) {
                    bb.redirect(redirect);

                    return;
                }

				if (tocreated !== undefined) {
					bb.redirect(tocreated.replace(/['"]/g, '') + '/' + result);

					return;
				}

                bb.redirect(redirect);
            });

            return false;
        });
    },
    MenuAutoActive: function() {
        var matches = $('ul.main li a').filter(function() {
            return document.location.href == this.href;
        });

        matches.parents('li').addClass('active');
    },
    cookieCreate: function (name,value,days) {
        if (days) {
            var date = new Date();

            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));

            var expires = "; expires=" + date.toGMTString();
        } else var expires = "";

        document.cookie = name + "=" + value+expires + "; path=/";
    },
    cookieRead: function (name) {
        var nameEQ = name + "=";
        var ca = document.cookie.split(';');

        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];

            while (c.charAt(0)==' ') c = c.substring(1, c.length);

            if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
        }

        return null;
    },
    CurrencySelector: function() {
        $("select.currency_selector").bind('change', function() {
            bb.post('guest/cart/set_currency', { currency: $(this).val() }, function(result) {
                bb.reload();
            });

            return false;
        });
    },
    LanguageSelector: function() {
        $("select.language_selector").bind('change', function() {
            bb.cookieCreate('BBLANG', $(this).val(), 7);
            bb.reload();

            return false;
        }).val(bb.cookieRead('BBLANG'));
    }
}

//===== Tabs =====//
// $.fn.simpleTabs = function() {
//     // Default Action
//     $(this).find(".tab_content").hide(); // Hide all content
//     $(this).find("ul.tabs li:first").addClass("activeTab").show(); // Activate first tab
//     $(this).find(".tab_content:first").show(); // Show first tab content

//     // On Click Event
//     $("ul.tabs li").click(function() {
//         $(this).parent().parent().find("ul.tabs li").removeClass("activeTab"); //Remove any "active" class
//         $(this).addClass("activeTab"); // Add "active" class to selected tab
//         $(this).parent().parent().find(".tab_content").hide(); // Hide all tab content

//         var activeTab = $(this).find("a").attr("href"); // Find the rel attribute value to identify the active tab + content

//         $(activeTab).show(); // Fade in the active content

//         return false;
//     });
// };

/**
 * Scroll to top
 */
$("a[href='#top']").click(function() {
	$("html, body").animate({ scrollTop: 0 }, "slow");

    return false;
});

//===== Placeholder for all browsers =====//
// $("input").each(
//     function() {
//         if ($(this).val() == "" && $(this).attr("placeholder") != "") {
//             $(this).val($(this).attr("placeholder"));

//             $(this).focus(function(){
//                 if ($(this).val() == $(this).attr("placeholder")) $(this).val("");
//             });

//             $(this).blur(function(){
//                 if ($(this).val() == "") $(this).val($(this).attr("placeholder"));
//             });
//         }
//     }
// );

jQuery(function ($) {
    $('.loading').ajaxStart(function() {
        $(this).show();
    }).ajaxStop(function() {
        $(this).hide();
    });

    // $("div.simpleTabs").simpleTabs();

    if ($("select.currency_selector").length) { bb.CurrencySelector(); }
    if ($("select.language_selector").length) { bb.LanguageSelector(); }
    if ($("ul.main").length){ bb.MenuAutoActive(); }
	if ($("a.api, a.api-link").length){ bb.apiLink(); }
	if ($("form.api_form, form.api-form").length){ bb.apiForm(); }

    // $('#login-form-link').bind('click', function() {
    //     $(this).fadeOut();
    //     $('#login-form').slideDown();

    //     return false;
    // });

    // if (jQuery().tipsy) {
    //     $('a.show-tip').tipsy({fade: true, delayIn: 500});
    // }
});

const OHMS = {
    message: (message, type = 'info') => {
        let color = 'primary';
        let title = 'System message';
        switch(type) {
            case 'error':
                color = 'danger';
                title = 'Error';
                break;
            case 'warning':
                color = 'warning';
                title = 'Warning';
                break;
            default:
                color = 'primary';
                title = 'System message';
        }

        const container = document.createElement('div');
        container.classList.add('position-fixed', 'bottom-0', 'start-50', 'translate-middle-x', 'p-3', 'gm-system-toast-wrap');
        container.style.zIndex = 1070;

        const element = document.createElement('div');
        element.classList.add('toast', 'gm-system-toast', 'border-0');
        element.setAttribute('role', 'alert');
        element.setAttribute('aria-live', 'assertive');
        element.setAttribute('aria-atomic', 'true');
        element.setAttribute('data-bs-delay', '5500');

        element.innerHTML = `
            <div class="toast-header">
                <span class="p-2 border border-light bg-${ color } rounded-circle me-2"></span>
                <strong class="me-auto">${ title }</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body"></div>
        `;
        element.querySelector('.toast-body').textContent = String(message ?? '');

        element.addEventListener('hidden.bs.toast', () => {
            container.remove();
        });

        const toast = new bootstrap.Toast(element, {
            autohide: true,
            delay: 5500
        });

        container.appendChild(element);

        document.querySelector('body').appendChild(container);

        toast.show();
    },
    // TODO: Make create Modal window by JS. HTML hardcoded at now.
    // window: () => {
    // }
};

// Registration page
$(function() {
    document.querySelector('.input-group-text.password-toggler')?.addEventListener('click', () => {
        ['inputRegistrationPassword', 'inputRegistrationPasswordRepeat'].forEach(id => {
            let element = document.getElementById(id);

            element.type = 'password' === element.type ? 'text' : 'password';
        });
    });
});
