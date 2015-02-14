
;(function(win){
    var STATE_IDLE = 0;
    var STATE_LOADING = 1;
    var STATE_WAITING = 2;
    var STATE_LOADED = 3;
    var STATE_COMPLETE = 10;
    var doc = win.document;
    var html = doc.documentElement;
    var conf = {};
    var queue = [];
    var state = STATE_IDLE;
    var enabled = true;
    var wait_idx = -1;
    var dom_ready = false;
    var waiters = [];
    var plugins = {};
    //var $ = win.jQuery;
    
    
    var api = win.mtoApp = function(){
        api.ready.apply(null, arguments);
    };
    
    api.plugin = function(fn)
    {
        $.extend(fn.prototype, api);
        var instance = new fn($);
        if (typeof(plugins[instance.getName()]) != "undefined")
        {
            alert("Plugin <"+instance.getName()+"> already loaded");
            return false;
        }
        plugins[instance.getName()] = {name: instance.getName(), instance: instance};
        if (dom_ready)
        {
            instance.start();
        }
        else
        {
            api.ready(instance.start);
        }
    };
    
    api.setCfg = function(name, value)
    {
        if (typeof(name) === "object")
        {
            conf = name;
            on_conf_load();
        }
        else if (typeof(name) === "string")
        {
            conf[name] = value;
        }
    }
    
    api.getCfg = function(name)
    {
        return typeof(conf[name]) === "undefined" ? null : conf[name];
    };
    
    api.load = function(uri, callback, wait, label)
    {
        if (typeof label === "undefined")
        {
            label = "all";
        }
        for (var i in queue)
        {
            if (queue[i]['uri'] == uri)
            {
                return api;
            }
        }
        var item = {loded: false, loading: false, callback: callback, uri: uri, wait: wait, label: label};
        queue.push(item);
        load_asset(item, queue.length-1);
        return api;
    };
    
    api.ready = function(label, fn)
    {
        var cb = null;
        var lb = null;
        if (isFunction(label))
        {
            cb = label;
            lb = "all";
        }
        else if (typeof label !== "string" || !isFunction(fn))
        {
            return api;
        }
        else
        {
            cb = fn;
            lb = label;
        }
        if (enabled)
        {
            waiters.push({label: lb, callback: cb});
        }
        else
        {
            win.jQuery(cb);
        }
        return api;
    };
    
    api.getName = function()
    {
        return this.name;
    }
    
    api.oninit = function(){};
    api.onload = function(){};
    api.start = function(){};
    api.win = win;
    api.doc = doc;
    
    function load_asset(item, idx)
    {
        if (state === STATE_WAITING)
        {
            return;
        }
        var script = doc.createElement("script");
        script.type = "text/javascript";
        script.src = item['uri'];
        var head = doc['head'] || doc.getElementsByTagName('head')[0];
        if (script.readyState)
        {
            script.onreadystatechange = process;
        }
        else
        {
            script.onload = process;
        }
        
        function process(e)
        {
            e = e || win.event;
            if (e.type === 'load' || (/loaded|complete/.test(script.readyState) && (!doc.documentMode || doc.documentMode < 11))) 
            {
                script.onload = script.onreadystatechange = null;
                head.removeChild(script);
                set_state(item['uri']);
                var fn = item['callback'] || dummy;
                fn();
            }        
        }
        
        function set_state(uri)
        {
            var idx = queue_idx(uri);
            queue[idx]['loaded'] = true;
            run_waiters(queue[idx]['label']);
            if (state === STATE_WAITING)
            {
                for (var i=0; i<=wait_idx; i++)
                {
                    if (!queue[i]['loaded'])
                    {
                        return;
                    }
                }
                state = STATE_LOADED;
            }
            var all_loaded = true;
            for (var i=0; i<queue.length; i++)
            {
                if (!queue[i]['loading'])
                {
                    load_asset(queue[i], i);
                }
                if (!queue[i]['loaded'])
                {
                    all_loaded = false;
                }
            }
            if (all_loaded)
            {
                state = STATE_COMPLETE;
                run_waiters();
            }
        }

        queue[idx]['loading'] = true;
        if (item['wait'])
        {
            state = STATE_WAITING;
            wait_idx = queue_idx(item['uri']);
        }
        else
        {
            state = STATE_LOADING;
        }
        head.appendChild(script);    
    }
    
    function dummy(){};
    
    function queue_idx(uri)
    {
        for (var i=0; i<queue.length; i++)
        {
            if (queue[i]['uri'] === uri)
            {
                return i;
            }
        }
        return -1;
    }
    
    function is(type, obj) 
    {
        var cls = Object.prototype.toString.call(obj).slice(8, -1);
        return obj !== undefined && obj !== null && cls === type;
    }

    function isFunction(item) 
    {
        return is("Function", item);
    }

    function isArray(item) 
    {
        return is("Array", item);
    }    

    function call_once(fn) 
    {
        fn = fn || dummy;

        if (fn._done) 
        {
            return;
        }

        fn();
        fn._done = 1;
    }
    
    function run_waiters(label)
    {
        if (typeof label !== "undefined" && label !== "all")
        {
            for (var i=0; i<waiters.length; i++)
            {
                if (waiters[i]['label'] === label)
                {
                    call_once(waiters[i]['callback']);
                }
            }
            return;
        }
        if ((state === STATE_COMPLETE || state === STATE_IDLE) && dom_ready)
        {
            for (var i=0; i<waiters.length; i++)
            {
                call_once(waiters[i]['callback']);
            }
        }
    }
    
    function on_dom_ready()
    {
        if (doc.addEventListener) 
        {
            doc.removeEventListener("DOMContentLoaded", on_dom_ready, false);
            win.removeEventListener("load", on_dom_ready, false);
        }
        else if (doc.readyState === "complete") 
        {
            doc.detachEvent("onreadystatechange", on_dom_ready);
            win.detachEvent("onload", on_dom_ready);
        }
        dom_ready = true;
        run_waiters();
    }
    
    if (doc.readyState === "complete") 
    {
        dom_ready = true;
        run_waiters();
    }
    else if (doc.addEventListener) 
    {
        doc.addEventListener("DOMContentLoaded", on_dom_ready, false);
        win.addEventListener("load", on_dom_ready, false);
    }
    else 
    {
        doc.attachEvent("onreadystatechange", on_dom_ready);
        win.attachEvent("onload", on_dom_ready);
    }
    
    function on_conf_load()
    {
        if (api.getCfg('kill_jquery'))
        {
            delete win.$;
            delete win.jQuery;
        }
    }
    
    var hash = document.getElementsByTagName("script")[0].getAttribute("data-hash");
    waiters.push({label: 'jquery', callback: function(){
        (function() {
              var _old = $.fn.attr;
              $.fn.attr = function() 
              {
                var a, aLength, attributes,map;
                if (this[0] && arguments.length === 0) 
                {
                    map = {};
                    attributes = this[0].attributes;
                    aLength = attributes.length;
                    for (a = 0; a < aLength; a++) 
                    {
                          map[attributes[a].name.toLowerCase()] = attributes[a].value;
                    }
                    return map;
                } 
                else 
                {
                      return _old.apply(this, arguments);
                }
            };
        }());
    }});
    waiters.push({label: 'all', callback: function(){
        $("*[feature]").each(function()
        {
            api.load("/js/features/"+$(this).attr("feature")+".feature.js?"+hash);
        });
    }});
    waiters.push({label: 'all', callback: function(){
        $("mkfeature").each(function(){
            var attrs = $(this).attr();
            api.load("/js/features/"+attrs['id']+".feature.js?"+hash);
            $(this).remove();
        });
    }});
    waiters.push({label: 'all', callback: function(){
        $("*[data-mto-plugin]").each(function(){
            api.load($(this).data('mto-plugin'));
        });
    }})
    
})(window);


if (typeof([].forEach) === "undefined")
{
    Array.prototype.forEach = function(fn, scope)
    {
        for (var i = 0, l = this.length; i < l; i++) 
        {
            fn.call(scope, this[i], i, this);
        }    
    };
}
if (!Function.prototype.bind)
{
    Function.prototype.bind = function(obj)
    {
        var self = this;
        var args = arguments;
        return function(){
            return self.apply(obj, args);
        };
    }
}