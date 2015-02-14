var cfg = {
    debug: true,
    uri_base: '',
    uri_cdn: '',
    uri_comet: '',
    domain: '',
    other_setting: 'value'
};

if (window.mtoApp)
{
    mtoApp.setCfg(cfg);
}
if (window.require && typeof(window.require) == "function") require.config(({
    "baseUrl": "/scripts",
    "waitSeconds" : "15",
    "paths": {
        "requirejs" : cfg['uri_cdn'] + "/js/require",
        "cfg" : "empty:",
        "jquery": cfg['uri_cdn'] + "/js/jquery19",
        "jqueryui" : cfg['uri_cdn'] + "/js/jquery-ui",
        "domReady" : cfg['uri_cdn'] + "/js/domReady"


    },
    "shim": {
        "plugin.bootstrap" : ["jquery"]

    }
}));


(function()
{
    var script = document.getElementsByTagName("script")[0];
    var ctl = script.getAttribute('data-ctl');
    var act = script.getAttribute('data-act');
    var skip = script.getAttribute('data-skip_ctl');
    var uid = script.getAttribute('data-uid');
    var uri_parts = location.pathname.split("/");
    var is_front = false;

    if (!ctl)
    {
        ctl = uri_parts[1];
    }
    if (!act)
    {
        act = uri_parts[2];
    }
    if (location.host.indexOf("admin") === 0)
    {
        ctl = "admin_" + ctl;
    }
    else
    {
        is_front= true;
    }
    if (typeof(SKIP_CTL) != "undefined")
    {
        skip = 1;
    }
    cfg._current_controller = ctl;
    cfg._current_action = act;
    cfg._current_user = uid;
    cfg._current_scope = cfg._current_controller + "/" + cfg._current_action;
    cfg._is_front = is_front;
    if (skip)
    {
        cfg._current_controller = is_front ? 'front_default' : "legacy";
    }

})();
if (window.require && typeof(window.require) == "function")
{
    define('cfg', function(){
        return cfg;
    });


    if (cfg._current_controller)
    {
        require(["controller." + cfg._current_controller]);
    }
}
