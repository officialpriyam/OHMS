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
var bb = {
    post: function(url, params, jsonp) {
        $.ajax({
            type: "POST",
            url: bb.restUrl(url),
            data: params,
            dataType: 'json',
            error: function(jqXHR, textStatus, e) {
                bb.msg(e, 'error');
            },
            success: function(data) {
                if(data.error) {
                    bb.msg(data.error.message, 'error');
                } else {
                    if(typeof jsonp === 'function') {
                        return jsonp(data.result);
                    } else if(window.hasOwnProperty('console')) {
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
                bb.msg(e, 'error');
            },
            success: function(data) {
                if(data.error) {
                    bb.msg(data.error.message, 'error');
                } else {
                    if(typeof jsonp === 'function') {
                        return jsonp(data.result);
                    } else if(window.hasOwnProperty('console')) {
                        console.log(data.result);
                    }
                }
            }
        });
    },
    restUrl: function(url) {
        if(url.indexOf('http://') > -1 || url.indexOf('https://') > -1) {
            return url;
        }

        var base = $('meta[property="bb:url"]').attr("content");
        if (base && base.slice(-1) !== '/') {
            base += '/';
        }

        return base + 'index.php?_url=/api/' + url;
    },
    error: function(txt, code) {
        jAlert(txt, 'Error code: '+code);
    },
    msg: function(txt, type) {
        jAlert(txt, type);
    },
    redirect: function(url) {
        if(url === undefined) {
            this.reload();

        }
        window.location = url;
    },
    reload: function() {
        window.location.reload(true);
    },
    load: function(url, params) {
        var r = '';
        $.ajax({
            url: url,
            data: params,
            type: "GET",
            success: function(data){ r = data; },
            async: false
        });
        return r;
    },
    _afterComplete: function(obj, result) {
        var jsonp = obj.attr('data-api-jsonp');
        if(jsonp !== undefined && window.hasOwnProperty(jsonp)) {
            return window[jsonp](result);
        }
        if(obj.hasClass('bb-rm-tr')) {
            obj.closest('tr').addClass('highlight').fadeOut();
            return;
        }
        if(obj.attr('data-api-reload') !== undefined) {
            this.reload();
            return;
        }
        if(obj.attr('data-api-redirect') !== undefined) {
            return this.redirect(obj.attr('data-api-redirect'));
        }
        if(obj.attr('data-api-msg') !== undefined) {
            this.msg(obj.attr('data-api-msg'), 'success');
            return ;
        }
        if(result) {
            this.msg('Form updated', 'success');
            return ;
        }
    },
    apiForm: function() {
        $('form.api-form').bind('submit', function(e){
            e.preventDefault();
            var obj = $(this);
            bb.post(obj.attr('action'), obj.serialize(), function(result) {return bb._afterComplete(obj, result);});
        });
    },
    apiLink: function() {
        $("a.api-link").bind('click', function(e){
            e.preventDefault();
            var obj = $(this);
            if(obj.attr('data-api-confirm') !== undefined) {
                jConfirm(obj.attr('data-api-confirm'), 'Confirm your action', function(r) {
                    if(r) bb.get( obj.attr('href'), {}, function(result) {return bb._afterComplete(obj, result);} );
                });
            } else if(obj.attr('data-api-prompt') !== undefined) {
                jPrompt(obj.attr('data-api-prompt-text'), obj.attr('data-api-prompt-default'), obj.attr('data-api-prompt-title'), function(r) {
                    if(r) {
                        var p = {};
                        var name = obj.attr('data-api-prompt-key');
                        p[name] = r;
                        bb.get( obj.attr('href'), p, function(result) {return bb._afterComplete(obj, result);} );
                    }
                });
            } else {
                bb.get( obj.attr('href'), {}, function(result) {return bb._afterComplete(obj, result);} );
            }
        });
    },
    menuAutoActive: function() {
        var matches = $('ul#menu li a').filter(function() {
            return document.location.href == this.href;
        });
        matches.parents('li').addClass('active');
    },
    cookieCreate: function (name,value,days) {
        if (days) {
            var date = new Date();
            date.setTime(date.getTime()+(days*24*60*60*1000));
            var expires = "; expires="+date.toGMTString();
        }
        else var expires = "";
        document.cookie = name+"="+value+expires+"; path=/";
    },
    cookieRead: function (name) {
        var nameEQ = name + "=";
        var ca = document.cookie.split(';');
        for(var i=0;i < ca.length;i++) {
            var c = ca[i];
            while (c.charAt(0)==' ') c = c.substring(1,c.length);
            if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
        }
        return null;
    },
    insertToTextarea: function(areaId, text){
        var txtarea = document.getElementById(areaId);
        var scrollPos = txtarea.scrollTop;
        var strPos = 0;
        var br = ((txtarea.selectionStart || txtarea.selectionStart == '0') ?
            "ff" : (document.selection ? "ie" : false ) );
        if (br == "ie") {
            txtarea.focus();
            var range = document.selection.createRange();
            range.moveStart ('character', -txtarea.value.length);
            strPos = range.text.length;
        }
        else if (br == "ff") strPos = txtarea.selectionStart;

        var front = (txtarea.value).substring(0,strPos);
        var back = (txtarea.value).substring(strPos,txtarea.value.length);
        txtarea.value=front+text+back;
        strPos = strPos + text.length;
        if (br == "ie") {
            txtarea.focus();
            var range = document.selection.createRange();
            range.moveStart ('character', -txtarea.value.length);
            range.moveStart ('character', strPos);
            range.moveEnd ('character', 0);
            range.select();
        }
        else if (br == "ff") {
            txtarea.selectionStart = strPos;
            txtarea.selectionEnd = strPos;
            txtarea.focus();
        }
        txtarea.scrollTop = scrollPos;
        if ('undefined' !== typeof CKEDITOR ){
            CKEDITOR.instances[areaId].insertText(text);
        }

        return false
    }
}

//===== Left navigation submenu animation =====//	

$("ul.sub li a").hover(function() {
$(this).stop().animate({color: "#3a6fa5"}, 400);
},function() {
$(this).stop().animate({color: "#494949"}, 400);
});

	
//===== Tabs =====//
$.fn.simpleTabs = function(){
    var $container = $(this);

    //Default Action
    $container.find(".tab_content").hide(); //Hide all content
    $container.find("ul.tabs li:first").addClass("activeTab").show(); //Activate first tab
    $container.find(".tab_content:first").show(); //Show first tab content

    //On Click Event using delegation on the container
    $container.on('click', 'ul.tabs li a', function(e) {
        e.preventDefault();
        var $li = $(this).parent();
        $li.parent().parent().find("ul.tabs li").removeClass("activeTab"); //Remove any "active" class
        $li.addClass("activeTab"); //Add "active" class to selected tab
        $li.parent().parent().find(".tab_content").hide(); //Hide all tab content
        var activeTab = $(this).attr("href"); //Find the rel attribute value to identify the active tab + content
        $(activeTab).show(); //Fade in the active content
        
        // Prevent navigation even if base tag is present
        if (window.history.pushState) {
            window.history.pushState(null, null, activeTab);
        } else {
            window.location.hash = activeTab;
        }
        return false;
    });

    // select active tab from hash on load
    if($(document.location.hash).length) {
        $container.find('a[href="'+document.location.hash+'"]').click();
    }
};//end function

$(function() {

	//===== Global ajax methods =====//
    $('.loading').ajaxStart(function() {
        $(this).show();
    }).ajaxStop(function() {
        $(this).hide();
    });

	//===== Api forms and links =====//
    if($("form.api-form").length){bb.apiForm();}
    if($("a.api-link").length){bb.apiLink();}
    //if($("ul#menu").length){bb.menuAutoActive();}
    
    $().UItoTop();

    //===== Datepickers =====//
	$( ".datepicker" ).datepicker({
		defaultDate: +7,
		autoSize: true,
		//appendText: '(yyyy-mm-dd)',
		dateFormat: 'yy-mm-dd'
	});

	//===== Tooltip =====//

	$('.leftDir').tipsy({fade: true, gravity: 'e'});
	$('.rightDir').tipsy({fade: true, gravity: 'w'});
	$('.topDir').tipsy({fade: true, gravity: 's'});
	$('.botDir').tipsy({fade: true, gravity: 'n'});

	$('.dd').not('[data-admin-dropdown]').click(function () {
		if ($('body').hasClass('admin-shell-v2')) {
			return;
		}
		$('ul.menu_body', this).slideToggle(100);
	});
	
    //===== Form elements styling =====//
    try {
        $(".mainForm select, .mainForm input:checkbox, .mainForm input:radio, .mainForm input:file").uniform();
    } catch(e) { console.warn("Uniform plugin error:", e); }

	//===== Collapsible elements management =====//
	try {
        $('.exp').collapsible({
            defaultOpen: 'current',
            cookieName: 'navAct',
            cssOpen: 'active',
            cssClose: 'inactive',
            speed: 300
        });
    } catch(e) { console.warn("Collapsible plugin error:", e); }

    try {
        $("div.simpleTabs").simpleTabs();
    } catch(e) { console.warn("SimpleTabs error:", e); }



    $(document).delegate('div.msg span.close', 'click', function() {
        $(this).parent().slideUp(70);
        return false;
    });

	//===== Information Priyxes =====//
	$(".hideit").click(function() {
		$(this).fadeOut(400);
	});

    $("select.language_selector").bind('change', function(){
        bb.cookieCreate('BBLANG', $(this).val(), 7);
        bb.reload();
        return false;
    }).val(bb.cookieRead('BBLANG'));
});

