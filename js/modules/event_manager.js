define(["cfg",
        "jquery", 
        "module.data_proxy",
        "module.toolkit",
        "module.popup"
       ], function(_cfg, $, _proxy, _tools, _popup){
    var listeners = {};
    var events = [];
    var impl = {};
    var util = {};
    var doc = document;
    var win = window;
    var _em;
    var windows = {};
    var winid;
    var con = console;
    
    
    
    
    function handle_event($elem, act, click_event)
    {
        if ($elem.attr('disabled') == 'disabled')
        {
            return false;
        } 
        
        var params = $.extend({}, $elem.data());
        var $frm = $elem.parents("*[data-form]:first");
        var $grid = $elem.parents("*[data-grid]:first");
        var $row = $elem.parents("tr[data-row_id]:first");

        var $scope = null;
        if ($frm.length > 0)
        {
            $scope = $frm;
        }
        else if ($grid.length > 0)
        {
            $scope = $grid;
        }
        if (typeof(params['force_row']) != "undefined")
        {
            $scope = $row;
        }
        
        switch (act)
        {
            case 'action':
                var action = params['action'];
                if (typeof(impl['action_' + action]) != "undefined")
                {
                    impl['action_' + action]($elem);
                }
                else
                {
                    alert("Unknown action: "+action);
                }
            break;
            case 'popup':
            case 'tblpopup':
            case 'frmpopup':
                var $popup = $('#'+params['popup']);
                
                

                var opener = _popup.getOpener({
                    params: params,
                    $frm: $frm,
                    $caller: $elem,
                    act: act
                });
                

                if ($popup.length <= 0)
                {
                    $("input", $frm).each(function(){
                        params[$(this).attr("name")] = $(this).val();
                    });
                    params['model'] = [];
                    if (act == "frmpopup")
                    {
                        params['model'].push($frm.data('form'));
                    }
                    if (act == "tblpopup")
                    {
                        params['model'].push($grid.data('grid')+"/rm:");
                        params['model'].push($row.length ? "i:"+$row.data('row_id') : "");
                    }
                    params['model'].push("b:"+ params['popup'] +"/m:");
                    params['model'].push($elem.data('model'));
                    _em.render(params['popup'], params, opener);
                }
                else
                {
                    opener();
                }
            break;
            case 'lookup':
                if (!$frm.length)
                {
                    return;
                }
                $frm.find("input[type=text]").each(function(){
                    params[$(this).attr('name')] = $(this).val();
                });
                _proxy.call([$frm.data("form"), $elem.data("model")], params, function(ret){
                    if (ret.found)
                    {
                        $('*[data-lookup_not_found]', $frm).hide();
                        $('*[data-lookup_found]', $frm).show();
                        for (var param in ret)
                        {
                            var f = $("*[data-ref="+ param.substr(4) + "]", $frm);
                            if (f.length <= 0)
                            {
                                continue;
                            }
                            switch(param.substr(0, 4))
                            {
                                case "has_":

                                    if (ret[param])
                                    {
                                        f.show();
                                    }
                                    else
                                    {
                                        f.hide();
                                    }
                                break;
                                case "fld_":
                                    f.val(ret[param]);
                                break;
                                case "txt_":
                                    f.text(ret[param]);
                                break;
                                case "src_":
                                    f.attr("src", ret[param]);
                                break;
                                default:
                                break;
                            }
                        }
                    }
                    else
                    {
                        $('*[data-lookup_not_found]', $frm).show();
                        $('*[data-lookup_found]', $frm).hide();
                    }
                });
            break;
            case 'save':
            case 'call':
                if ($scope.length)
                {
                    if (params['ref'] && params['ref'].indexOf(',') < 0)
                    {
                        $("input[name="+params['ref']+"]", $frm).val(params['val']);
                    }
                    
                    var meth = "onBeforeSubmit_" + $frm.prop("id");
                        
                    if (typeof(impl[meth]) != "undefined")
                    {
                        impl[meth]($elem, $scope, params);
                    }
                    
                    var validate = "validate_" + $frm.prop('id');
                    if (typeof(params['alive']) == "undefined" && typeof(impl[validate]) != "undefined")
                    {
                        if (!impl[validate]($scope))
                        {
                            return;
                        }
                    }
                    if (params['confirm'])
                    {
                        if (!confirm(params['confirm']))
                        {
                            return;
                        }
                    }
                    if (typeof(params['alive']) == "undefined")
                    {
                        if (typeof(params['single_close']) != "undefined")
                        {
                            _em.closePopup();
                        }
                        else
                        {
                            _em.closeAll();
                        }
                    }
                    
                    var content = _tools.serializeForm($("input,select,textarea", $scope), params);
                    if (!params['alive'])
                    {
                        $elem.prop('disabled', true);
                    }
                    if ($scope.data("submit_class"))
                    {
                        $scope.addClass($scope.data('submit_class'));
                    }
                    _proxy[act]([$frm.data("form"), $grid.data("grid"), (params['force_row'] ? "i:"+$row.data("row_id") : ""), $elem.data("model"), 'wm:'], content, function(res)
                    {
                        $elem.prop('disabled', false);          
                        if ($scope.data('submit_class'))
                        {
                            $scope.removeClass($scope.data('submit_class'));
                        }
                        $elem.removeAttr("disabled");
                        var meth = "onAfterSubmit_" + $frm.attr("id");                        
                        if (typeof(impl[meth]) != "undefined")
                        {
                            impl[meth]($elem, $scope);
                        }
                        
                        if ($elem.data('callback'))
                        {
                            meth = "callback_" + $elem.data("callback");
                            if (typeof(impl[meth]) != "undefined")
                            {
                                impl[meth](res, $elem);
                            }
                        }
                    
                        if (params['rerender'])
                        {

                            params['model'] = $frm.data("form");
                            if (typeof(params['rerender_scope']) != "undefined")
                            {
                                switch (params['rerender_scope'])
                                {
                                    case "root":
                                        var r = $frm.parents("*[data-form]:last");
                                        if (r.length)
                                        {
                                            params['model'] = r.data("form");
                                        }
                                    break;
                                    case "caller":
                                        var r = $frm[0]['sm-trigger'].o.caller.parents('*[data-form]:first');
                                        if (r.length)
                                        {
                                            params['model'] = r.data("form");
                                        }
                                    break;
                                }
                            }
                            //if (params['rerender_scope'] && params['rerender_scope'] == "parent" && $('#'+params['rerender']+':visible').length)
                            //{
                            //    params['model'] = $('#'+params['rerender']+':visible').parents("*[data-form]:first").data("form");
                            //}
                            //else if (params['rerender_scope'] && $(params['rerender_scope']+':visible').length && $('#'+params['rerender']+':visible').length)
                            //{
                            //    params['model'] = $('#'+params['rerender']+':visible', $(params['rerender_scope']+':visible').first()).parents("*[data-form]:first").data("form");
                            //}
                            _em.render(params['rerender'], params, function(dom){
                                _tools.init_combos(dom);
                            });
                        }    
                        if (params['rerender_row'])
                        {
                            if (params['rerender_row'].indexOf && params['rerender_row'].indexOf(':') > 0)
                            {
                                _em.trigger("grid.render_row", {gridref: params['rerender_row'], broadcast: 1});
                            }
                            else if ($frm[0] && $frm[0]['sm-trigger'])
                            {
                                _em.trigger("grid.render_row", {elem: $frm[0]['sm-trigger'].o.caller});
                            }
                            else if($elem.parents("tr[data-row_id]:first").length > 0)
                            {
                                _em.trigger("grid.render_row", {elem: $elem});
                            }
                        }
                        if (params['trigger_event'])
                        {
                            _em.trigger(params['trigger_event'], $.extend(params, res || {}));
                        }
                    }, function(res){
                        $elem.prop("disabled", false);
                    });
                }
            break;
            case "save_row":
                if ($grid.length && $row.length)
                {
                    _proxy.save([$grid.data("grid"), $elem.data("model"), "i:"+$row.data("row_id")], _tools,serializeForm($("input", $row)));
                }
            break;
            case "trigger":
                var c = $elem.parents("*[data-container]:first");
                if (c.length)
                {
                    $("input", c).each(function(){
                        var e = $(this);
                        params[e.attr("name")] = e.val();
                    });
                }
                params['evt'] = click_event;
                _em.trigger(params['event'], params);
            break;
            
            
            case "pjax":
                //win.location.assign($elem.attr('href'));
                if ( click_event.which > 1 || click_event.metaKey || click_event.ctrlKey || click_event.shiftKey || click_event.altKey )
                {    
                    return;
                }
                
                _em.trigger('location.change',{elem:$elem});
                
            break;
            
            
            

            default:
                //alert('Unknown action');
            break;
        }
    }
    
    
    
    
    _em = {
        closeAll : function(){
            _popup.closeAll();
        },
        closePopup: function(){
            _popup.closePopup();
        },
        bind: function(event, listener) {
            if (typeof(listeners[event]) == "undefined")
            {
                listeners[event] = [];
            }
            listeners[event].push(listener);
            if (typeof(events[event]) != "undefined")
            {
                listener(events[event]);
            }
            return this;
        },
        setWorker : function(w)
        {
            _popup.setWorker(w);
            _popup.setEm(_em);
        },
        trigger : function(event, data) 
        {
            
            if (typeof(data) == "undefined")
            {
                data = {};
            }
            if (!data['broadcast_only'])
            {
                if (typeof(listeners[event]) != "undefined")
                {
                    for (var i=0; i<listeners[event].length; i++)
                    {
                        listeners[event][i](data);
                    }
                }
            }
            if (isLocalStorageAvailable() && typeof(data['broadcast']) != "undefined")
            {
                data['pdbc_event'] = event;
                delete(data['broadcast']);
                if (typeof(data['broadcast_focus']))
                {
                    data['pdbc_docus'] = 1;
                    delete(data['broadcast_focus']);
                }
                if (typeof (data['broadcast_scope']) != "undefined")
                {
                    data['pdbc_scope'] = data['broadcast_scope'];
                    delete(data['broadcast_scope']);
                }
                if (typeof (data['broadcast_window']) != "undefined")
                {
                    data['pdbc_winid'] = data['broadcast_window'];
                    delete(data['broadcast_window']);
                }
                if (typeof(data['broadcast_only']) != "undefined")
                {
                    delete(data['broadcast_only']);
                }
                win.localStorage.removeItem('broadcast');
                win.localStorage.setItem('broadcast', _tools.serialize(data));
            }
            events[event] = data;
            return this;
        },
        impl : function (obj) {
            for (var i in obj)
            {
                impl[i] = obj[i]
            }
            return this;
        },
        getImpl: function(){
            return impl;
        },
        util : function(obj) {
            for (var i in obj)
            {
                util[i] = obj[i];
            }
            return this;
        },
        triggerPopup : function(id, model, args){
            args = args || {};
            var params = [];
            for (var i in args)
            {
                params.push("data-"+i+'="'+args[i]+'"');
            }
            var elm = $('<a href="" '+params.join(" ")+'  data-popup="'+id+'" data-model="'+model+'"></a>');
            handle_event(elm, "popup");
        },
        
//        positionPopup : function(popup){
//            position_popup(popup);
//        },
        
        bindCommon : function(){
            $(win).on("storage", function(e)
            {
                var evt = e.originalEvent;
                if (evt.newValue == null)
                {
                    return;
                }
                if (evt.key != "broadcast")
                {
                    return;
                }
                var data = _tools.unserialize(evt.newValue);
                if (typeof(data['pdbc_event']) != "undefined")
                {
                    if (data['pdbc_scope'] && data['pdbc_scope'] != _cfg._current_scope)
                    {
                        return;
                    }
                    if (data['pdbc_winid'] && data['pdbc_winid'] != winid)
                    {
                        return;
                    }
                    var event = data['pdbc_event'];
                    delete(data['pdbc_event']);
                    if (typeof(data['pdbc_focus']))
                    {
                        delete(data['pdbc_focus']);
                        win.focus();
                    }
                    _em.trigger(event, data);
                }
            });
            $(win).on("beforeunload", function(){
                _em.trigger("em.unload", {winid: winid, broadcast: 1});
            });
            $(doc).on("change", "*[data-change]", function(e){
                e.preventDefault();
                handle_event($(this), $(this).data("change"));
            });


            $(doc).on("dblclick", "*[data-click]", function(e){
                   e.preventDefault();
                   //alert('Один клик нада, насяйника');
                   //window.location.reload();
                   return false;
            });

            $(doc).on("click", "*[data-click]", function(e){
                if (!$(this).is(":checkbox") && !$(this).is(":radio"))
                {
                    e.preventDefault();
                }
                //e.stopPropagation();
                handle_event($(this), $(this).data('click'), e);
            });

            $(doc).keyup(function (event) 
            {
                if (event.keyCode == 27)
                {
                    _popup.onEscape();
                }
            });
//            $(doc).on("click", ".popup__x, .lnk_close", function (e) 
//            {
//                e.preventDefault();
//                //_em.closePopup();
//                _popup.closePopup();
//            });
            $('#overlay, #blanket').click(function () 
            {
                //_em.closePopup();
                _popup.closePopup();
            });
            


            $(doc).on("click", ".ww .lnk_close, .ww .lnk_cancel", function(e){
                e.preventDefault();
                //_em.closePopup();
                _popup.closePopup();
            });
            $(doc).on("submit", ".ww form", function(e){
                e.preventDefault();
                var btn = $("button:submit", $(this));
                if (btn.length)
                {
                    btn.click();
                }
            });
            
        },
        render : function(blocks, args, callback, model) {
            if (blocks instanceof $)
            {
                var tpls = [blocks];
            }
            else
            {
                var tpls = blocks.split(",");
            }
            var em = this;
            for (var i in tpls)
            {
                if (!tpls[i])
                {
                    continue;
                }
                var p = $("div[data-tab=1]:visible");
                (function(tpl){
                    if (!(tpl instanceof $))
                    {
                        if (typeof(model) != "undefined")
                        {
                            model += "/b:"+tpl;
                        }
                        else
                        {
                            args['block'] = tpl;
                        }
                    }
                    if ((tpl instanceof $) && tpl.length > 0 && tpl.data('load_class'))
                    {
                        tpl.addClass(tpl.data("load_class"));
                    }
                    else if(!(tpl instanceof $) && $('#'+tpl).length > 0 && $('#'+tpl).data('load_class'))
                    {
                        //alert($('#'+tpl).data('load_class'));
                        $('#'+tpl).addClass($('#'+tpl).data('load_class'));
                    }
                    if (args['uri'])
                    {
                        if (_popup.hasUriHandler())
                        {
                            callback && callback();
                            return;
                        }
                        _proxy.load(args['uri'], args, function(html){
                            update_rendered(html);
                        });
                    }
                    else
                    {
                        _proxy.render(typeof(model) != "undefined" ? model : args['model'], args, function(html) {
                            update_rendered(html);
                        });
                    }
                    function update_rendered(html)
                    {
                        var trigger = null;
                        var dom = $(html);
                        if (dom.data("load_class"))
                        {
                            dom.removeClass(dom.data("load_class"));
                        }
                        if (typeof(args['popup_base']) != "undefined")
                        {
                            dom.attr("id", args['popup']);
                        }
                        if (tpl instanceof $)
                        {
                            tpl.replaceWith(dom);
                        }
                        else if (p.length > 0 && $('#'+tpl, p).length > 0)
                        {
                            $('#'+tpl, p).replaceWith(dom);
                        }
                        else if ($('#'+tpl).length > 0)
                        {
                            if ($('#'+tpl)[0]['sm-trigger'])
                            {
                                trigger = $('#'+tpl)[0]['sm-trigger'];
                            }
                            $('#'+tpl).replaceWith(dom);
                        }
                        else if (args['append_to'])
                        {
                            dom.appendTo($(args['append_to']));
                        }
                        else
                        {
                            dom.appendTo(doc.body);
                        }
                        if (args['force_show'] && !(tpl instanceof $))
                        {
                            if (trigger)
                            {
                                $('#'+tpl)[0]['sm-trigger'] = trigger;
                            }
                            $('#'+tpl).show();
                        }
                        if (!(tpl instanceof $))
                        {
                            var meth = "render_callback_" + tpl;
                            if (typeof(impl[meth]) != "undefined")
                            {
                                impl[meth](dom);
                            }
                        }
                        callback && callback(dom);
                        em.trigger("load.block", {dom: dom});
                    }
                })(tpls[i]);
            }
        }
                
    };
    winid = Math.floor(Math.random()* 1000000);
        
    con.log("winid:"+winid);
    _em.bindCommon();
    _em.bind("em.load", function(data){
        windows[data['winid']] = data['scope'];
        if (typeof(data['pdbc_winid']) == "undefined")
        {
            _em.trigger("em.load", {winid: winid, scope: _cfg._current_scope, broadcast: 1, broadcast_window: data['winid'], broadcast_only: 1});
        }
        con.log(windows);
    });
    _em.bind("em.unload", function(data){
        delete(windows[data['winid']]);
        con.log(windows);
    });
    _em.trigger("em.load", {winid: winid, scope: _cfg._current_scope, broadcast: 1});
    return _em;
    
    function isLocalStorageAvailable() 
    {
        try 
        {
            return 'localStorage' in win && win['localStorage'] !== null;
        } 
        catch (e) 
        {
            return false;
        }
    }    
    
    
    
});