define(["cfg",  "jquery"], function(_cfg, $){
    
    var _xhrs = [];
    var error_renderer = null;
    
    
    function call_crud(httpm, httpt, action, model, args, callback, failback)
    {
        args = args || {};
        //args = proxy.merge_model([model, args['model']], args, true);
        if (typeof(args) == "string")
        {
            args += '&model='+encodeURIComponent(proxy.merge_models(model))+'&scope='+_cfg._current_scope;
        }
        else if (args instanceof FormData)
        {
            args.append("model", proxy.merge_models(model));
        }
        else
        {
            args['model'] = proxy.merge_models(model);
            args['scope'] = _cfg._current_scope;
        }
        
        var fn = httpm == "post" ? $.post : $.get;
        var x = fn("/crud/model_"+action, args, function(res){
            $.ajaxSetup({
                contentType : "application/x-www-form-urlencoded; charset=UTF-8",
                processData: true
            });
            if (check_result(res))
            {
                callback && callback(res);
            }
            else
            {
                failback && failback(res);
            }
            
        }, httpt);
        _xhrs.push(x);
        //console.log('AAAA');
        //console.log(args);
        
    }
    
    function check_result(result)
    {
        if (typeof(result) == "string")
        {
            if (parseInt(result) < 0)
            {
                var parts = result.split(":");
                delete(parts[0]);
                render_error(parts.join(":"), parseInt(result));
                return false;
            }
        }
        else
        {
            if (!result.success)
            {
                render_error(result.error, result.error_code);
                return false;
            }
            else
            {
                if (error_renderer)
                {
                    error_renderer(1, "");
                }
            }
        }
        return true;
    }
    
    function render_error(error, code)
    {
        switch (code)
        {
            case -999:
                if ($('#require_access_token').length > 0)
                {
                    return;
                }
                location.reload();
            break;
            case -998:
                console.log("Not authorized call");
                alert('unauthorized call');
            break;
            case -997:
                console.log("Signature check failed");
                alert('wrong signature');
            break;
            default:
                if (error_renderer)
                {
                    if (code >= 0)
                    {
                        code = -1;
                    }
                    return error_renderer(code, error);
                }
                alert(error);
            break;
        }
    }
    
    
    var proxy = {
        call : function(model, args, callback, failback)
        {
            if (typeof(args) == "string" && args.indexOf("excl=1") >= 0)
            {
                for (var i=0; i<_xhrs.length; i++)
                {
                    _xhrs[i].abort();
                }
            }
            return call_crud("post", "json", "call", model, args, callback, failback);
        },
        save : function(model, args, callback, failback)
        {
            return call_crud("post", "json", "save", model, args, callback, failback);
        },
        render: function(model, args, callback, failback)
        {
            $.ajaxSetup({
                contentType : "application/x-www-form-urlencoded; charset=UTF-8",
                processData: true
            });
            return call_crud("get", "html", "render", model, args, callback, failback);
        },
        merge_models: function(models)
        {
            if (typeof(models) == "string")
            {
                return models;
            }
            var args = {};
            for (var i=0; i<models.length; i++)
            {
                if (models[i])
                {
                    var parts = models[i].split("/");
                    for (var j=0; j<parts.length; j++)
                    {
                        var part = parts[j].split(":");
                        args[part[0]] = part[1];
                    }
                }
            }
            var parts = [];
            for (var i in args)
            {
                parts.push(i+":"+args[i]);
            }
            return parts.join("/");
        },
        set_error_renderer: function(renderer)
        {
            error_renderer = renderer;
        },
        load: function(uri, args, callback, failback, type)
        {
            if (typeof(type) == "undefined")
            {
                type = "html";
            }
            $.get(uri, args, function(res){
                callback && callback(res);
            }, type);
        }
                
    };
    
    
    
    return proxy;
    
});