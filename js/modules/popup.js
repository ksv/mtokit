define([
    "jquery", 
    "module.data_proxy",
    "module.toolkit"
], function($, _proxy, _tools) {
    var use_stack = true;
    var quiet_close = false;
    var pstack = [];
    var worker = "simplemodal";
    var doc = document;
    var win = window;
    var _em;
    var _close_timer = null;
    


    return {
        setWorker: function(w)
        {
            worker = w;
        },
        setEm : function(em)
        {
            _em = em;
        },
        closeAll: function()
        {
            switch(worker)
            {
                case "css":
                    $('#overlay').css('display', 'none');
                    $('.popup').each(function(){
                        var p = $(this);
                        if (p.data("autoremove") == 1)
                        {
                            p.remove()
                        }
                        else
                        {
                            p.hide();
                        }
                        var meth = "onClose_" + p.attr('id');
                        if (typeof(_em.getImpl()[meth]) != "undefined")
                        {
                            _em.getImpl()[meth](p);
                        } 
                    });
                break;
                case "blanket":
                    $(".blanket:visible").each(function(){
                        var p = $(this);
                        remove_blanket(p);
                        if (p.data("autoremove") == 1)
                        {
                            p.remove();
                        }    
                    });
                break;
                case "simplemodal":
                    pstack = [];
                    $.modal.close();
                break;
                case "fancybox":
                    pstack = [];
                    $.fancybox.close();
                break;    
                
            }
        },
        closePopup: function(){
            if ($.inArray(worker, ['css', 'blanket', 'fancybox']) >= 0)
            {
                this.closeAll();
            }
            else
            {
                $.modal.close();
            }
        },
        onEscape: function(){
            this.closePopup();
        },
        hasUriHandler: function()
        {
            if (worker == "fancybox")
            {
                return true;
            }
            return false;
        },
        getOpener: function(args)
        {
            switch (worker)
            {
                case "css":
                    var opener = open_css;
                break;
                case "blanket":
                    var opener = open_blanket;
                break;
                case "fancybox":
                    var opener = open_fancybox;
                break;
                case "simplemodal":
                default:
                    var opener = open_simplemodal;
                break;
            }
            return opener;
            
            function open_simplemodal()
            {
                var $popup = $('#'+args.params['popup']);
                if (use_stack)
                {
                    if (pstack.length)
                    {
                        quiet_close = true;
                        $.modal.close();
                        quiet_close = false;
                    }
                    pstack.push(opener);
                }
                var w = $popup;
                if (args['act'] == "frmpopup")
                {
                    var m = _proxy.merge_models([w.data("form"), args['$frm'].data("form")]);
                    w.data("form", m).attr("data-form", m);
                }
                w.modal({
                    opacity: 67,
                    overlayClose: (w.data("overlay_close") != "undefined" && w.data("overlay_close")) ? true : false,
                    escClose: w.data("supermodal") ? false : true,
                    caller: args['$caller'],
                    overlayCss: {background: "#000"},
                    onShow: function(dialog) {
                        w[0]['sm-trigger'] = this;
                        prepare_popup(w, args.params);
                        var meth = "onShow_" + args.params['popup'];

                        if (typeof (_em.getImpl()[meth]) != "undefined")
                        {
                            _em.getImpl()[meth](dialog, this);
                        }
                        if (typeof (args.params['popup_base']) != "undefined")
                        {
                            meth = "onShow_" + args.params['popup_base'];
                            if (typeof (_em.getImpl()[meth]) != "undefined")
                            {
                                _em.getImpl()[meth](dialog, this);
                            }
                        }
                        _tools.init_combos(dialog.data);
                        var f = $("*[data-autofocus]:first", dialog.data);
                        if (f.length)
                        {
                            win.setTimeout(function(){f.focus()}, 50);
                        }
                    },
                    onClose: function(dialog) {
                        if (pstack.length && !quiet_close && use_stack)
                        {
                            pstack.pop();
                        }
                        $.modal.close();
                        if (!quiet_close)
                        {
                            if (dialog.data.data('autoremove'))
                            {
                                dialog.data.remove();
                            }
                        }
                        if (pstack.length && !quiet_close && use_stack)
                        {
                            pstack.pop()();
                        }
                        
                        var meth = "onClose_" + args.params['popup'];
                        if (typeof(_em.getImpl()[meth]) != "undefined")
                        {
                            _em.getImpl()[meth]($popup);
                        }
                    }
                });
                if (args.params["trigger_event"])
                {
                    _em.trigger(args.params['trigger_event'], {cont: $popup});
                }
            }
            
            function open_css() {
                var $popup = $('#'+args.params['popup']);
                $('#overlay').show();
                $popup.css('top', ($(doc).scrollTop() + 50));
                $popup.show();
                var meth = "onShow_" + args.params['popup'];

                if (typeof (_em.getImpl()[meth]) != "undefined")
                {
                    _em.getImpl()[meth]($popup, this);
                }
                if (typeof (args.params['popup_base']) != "undefined")
                {
                    meth = "onShow_" + args.params['popup_base'];
                    if (typeof (_em.getImpl()[meth]) != "undefined")
                    {
                        _em.getImpl()[meth]($popup, this);
                    }
                }
                $('select', $popup).select2();
            }

            function open_blanket() {
                var $popup = $('#'+args.params['popup']);
                cast_blanket($popup);
                var meth = "onShow_" + args.params['popup'];

                if (typeof (_em.getImpl()[meth]) != "undefined")
                {
                    _em.getImpl()[meth]($popup, {o: {caller: args['$caller']}});
                }
            }
            
            function open_fancybox() {
                var $popup = $('#'+args.params['popup']);
                prepare_popup($popup, args.params);
                if (use_stack)
                {
                    pstack.push(opener);
                    quiet_close = true;
                }
                if (args['act'] == "frmpopup")
                {
                    var m = _proxy.merge_models([$popup.data("form"), args['$frm'].data("form")]);
                    $popup.data("form", m).attr("data-form", m);
                }
                var options = {
                    afterClose: function(dialog)
                    {
                        var meth = "onClose_" + args.params['popup'];
                        if (typeof(_em.getImpl()[meth]) != "undefined")
                        {
                            _em.getImpl()[meth]($popup);
                        }
                        if (pstack.length && !quiet_close && use_stack)
                        {
                            pstack.pop();
                        }
                        if (dialog.content instanceof $)
                        {
                            if (!quiet_close)
                            {
                                if (dialog.content.data("autoremove"))
                                {
                                    dialog.content.remove();
                                }
                            }
                        }
                        if (_close_timer)
                        {
                            win.clearTimeout(_close_timer);
                        }
                        if (pstack.length && !quiet_close && use_stack)
                        {
                            win.setTimeout(function(){pstack.pop()()}, 100);
                        }
                    },
                    afterShow: function(dialog)
                    {
                        var meth = "onShow_" + args.params['popup'];
                        if (typeof(_em.getImpl()[meth]) != "undefined")
                        {
                            _em.getImpl()[meth]($popup, {o: {caller: args['$caller']}});
                        }
                        quiet_close = false;
                        if ($popup.data("autoclose"))
                        {
                            if (_close_timer)
                            {
                                win.clearTimeout(_close_timer);
                            }
                            _close_timer = win.setTimeout(function(){_em.closePopup()}, $popup.data("autoclose"));
                        }
                    },
                    closeEffect: 'none',
                    openEffect: 'none',
                    nextEffect: 'none',
                    prevEffect: 'none',
                    openSpeed: 0,
                    openEasing: 'none',
                    openOpacity: false,
                    helpers: {
                        overlay: {locked: true, speedOut: 50}
                    }
                    
                };
                var params = _tools.unserialize(args.params['popup_options']);
                for (var key in params)
                {
                    if (params[key] === "false")
                    {
                        params[key] = false;
                    }
                    //console.log(params);
                    if (key == "padding" && params[key].indexOf('[') === 0)
                    {
                        options[key] = params[key].replace('[', '').replace(']', '').split(',');
                    }
                    else if (key.indexOf('overlay_') === 0)
                    {
                        options['helpers']['overlay'][key.replace('overlay_', '')] = params[key];
                    }
                    else
                    {
                        options[key] = params[key];
                    }
                }
                
                //options = $.extend(options, _tools.unserialize(args.params['popup_options']));
                if (args.params['uri'])
                {
                    /*options['height'] = "100%";
                    options['autoSize'] = false;
                    options['autoWidth'] = true;*/
                    options['type'] = "ajax";
                    options['href'] = args.params['uri'];
                }
                //console.dir(options);
                if (args.params['uri'])
                {
                    $.fancybox.open(options);
                }
                else
                {
                    $.fancybox($popup, options);
                }
            }
            
            
        }
        
    };





    function cast_blanket(popup, time, props)
    {
        var document_h = $(doc).height();
        var document_w = $(win).width();
        var bg_c = '#000';
        var opac = .5;
        if (popup.data("popup_bg_c"))
        {
            bg_c = popup.data("popup_bg_c");
        }
        if (popup.data("popup_opac"))
        {
            opac = popup.data("popup_opac");
        }
        $("<div></div>").attr({id: "blanket"}).css({background: bg_c, height: document_h, left: 0, opacity: opac, position: "absolute", top: 0, width: document_w, zIndex: 10100}).appendTo($("body"));
        if ($.browser.msie)
        {
            $("<iframe></iframe>").attr({id: "blanket_iframe"}).css({border: 0, height: document_h, left: 0, opacity: 0, position: "absolute", top: 0, width: document_w, zIndex: 10099}).appendTo($("body"));
        }
        position_popup(popup);
        $(win).on("resize", function()
        {
            change_blanket();
            position_popup(popup);
        }
        );
        if (time != 0)
        {
            popup.fadeIn(time);
        }
        else
        {
            popup.show();
        }
        return;
    }

    function change_blanket()
    {
        var document_h = $(doc).height();
        var document_w = $(win).width();
        $("#blanket").css({height: document_h, width: document_w});
        $("#blanket_iframe").css({height: document_h, width: document_w});
    }

    function remove_blanket(popup, time)
    {
        if (time != 0)
        {
            popup.fadeOut(time);
            setTimeout(function() {
                $("#blanket").css({opacity: 0});
            }, time);
            setTimeout(function() {
                $("#blanket_iframe").remove();
            }, time);
            setTimeout(function() {
                $("#blanket").remove();
            }, time);
        }
        else
        {
            popup.hide();
            $("#blanket").css({opacity: 0});
            $("#blanket_iframe").remove();
            $("#blanket").remove();
        }
        
        var meth = "onClose_" + popup.attr('id');
        if (typeof(_em.getImpl()[meth]) != "undefined")
        {
            _em.getImpl()[meth](popup);
        }
                        
        return;
    }

    function position_popup(popup)
    {
        var view_width = $(win).width();
        var view_height = $(win).height();
        var popup_width = popup.width();
        var popup_height = popup.height();
        var popup_loading_left = Math.ceil((view_width - popup_width) / 2) + $(doc).scrollLeft();
        var popup_loading_top = Math.ceil((view_height - popup_height) / 2) + $(doc).scrollTop();
        if (view_width - popup_width < 0)
        {
            popup_loading_left = $(doc).scrollLeft() + 10;
        }
        if (view_height - popup_height < 0)
        {
            popup_loading_top = $(doc).scrollTop() + 10;
        }
        if (popup.css("position") == 'fixed')
        {
            var popup_loading_left = Math.ceil((view_width - popup_width) / 2);
            var popup_loading_top = Math.ceil((view_height - popup_height) / 2);
            if (view_width - popup_width < 0)
            {
                popup_loading_left = 10;
            }
            if (view_height - popup_height < 0)
            {
                popup_loading_top = 10;
            }
        }
        popup.css({left: popup_loading_left, top: popup_loading_top});
        return;
    }

    function prepare_popup(p, args)
    {
        if (p.data('lookup'))
        {
            $("*[data-lookup_found]", p).hide();
            $("*[data-lookup_not_found]", p).hide();
            $("form input[type=text]", p).val("");
        }
        if (p.data("supermodal"))
        {
            p.parents(".simplemodal-container:first").find('.simplemodal-close').addClass("disabled").unbind("click");
        }
        for (var i in args)
        {
            if (i.indexOf('ref') === 0)
            {
                var tgt = i.substr(3, 3);
                var ref = i.substr(7).toLowerCase();
                switch (tgt)
                {
                    case 'val':
                        $('*[data-ref='+ref+']', p).val(args[i]);
                    break;
                    case 'src':
                        $('*[data-ref='+ref+']', p).attr("src", args[i]);
                    break;
                    case 'hrf':
                        $('*[data-ref='+ref+']', p).attr("href", args[i]);
                    break;
                    case 'txt':
                        $('*[data-ref='+ref+']', p).text(args[i]);
                    break;
                    case 'dat':
                        $('*[data-ref*='+ref+']', p).data(ref, args[i]).attr('data-'+ref, args[i]);
                    break;
                }
            }
        }

    }



})