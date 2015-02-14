define(["jquery", 
        "module.event_manager",
        "module.toolkit",
        "plugin.mousewheel",
        "plugin.scrollbar"
            ], function($, _em, _tools){

        var _scrolls = {};
        var _index = 0;
        

        
        _em.bind("all.start", function(data){
            $(window).on("resize", function(e){
                for (var i in _scrolls)
                {
                    resize(_scrolls[i]);
                    update(_scrolls[i]);
                }
            });
        });
        _em.bind("scroll.update", function(data){
            if (data['elem'])
            {
                var id = data['elem'].data("scrollable_id");
                if (_scrolls[id])
                {
                    update(_scrolls[id], data['scrollTo']);
                }
            }
            else
            {
                for (var i in _scrolls)
                {
                    update(_scrolls[i]);
                }
            }
        });
        _em.bind("scroll.init", function(data){
            data = data || {};
            if (typeof(data.elem) == "undefined")
            {
                return;
            }
            add(data.elem);
        });
        
        function update(elem, scrollTo)
        {
            resize(elem);
            switch (elem.worker)
            {
                case "mCustomScrollbar":
                default:
                    elem.target.mCustomScrollbar("update");
                    if (scrollTo)
                    {
                        if (scrollTo == "save")
                        {
                            scrollTo = parseInt(elem['offset']);
                        }
                        elem.target.mCustomScrollbar("scrollTo", scrollTo);
                    }
                break;
                case "native":
                    if (scrollTo == "save")
                    {
                        elem.target.scrollTop(parseInt(elem['offset']));
                    }
                    if (scrollTo == "top")
                    {
                        elem.target.scrollTop(0);
                    }
                break;
            }
        }
        
        function add(elem)
        {
            if (typeof(elem.data('scrollable')) == "undefined")
            {
                return;
            }
            var options = _tools.unserialize(elem.data("scrollable"));
            if (typeof(options['id']) == "undefined")
            {
                options['id'] = "pdscr_" + (++_index);
                elem.data("scrollable_id", options['id']).attr("data-scrollable_id", options['id']);
            }
            options['worker'] = options['worker'] || "mCustomScrollbar";
            if (options['body'])
            {
                options['target'] = $("tbody", elem);
            }
            else if (options['self'])
            {
                options['target'] = elem;
            }
            else if (options['grid'])
            {
                options['target'] = elem.parents("*[data-grid_scroll]:first");
            }
            else
            {
                options['target'] = elem.parent();
            }
            resize(options);
            switch(options['worker'])
            {
                case "mCustomScrollbar":
                default:
                    options.target.mCustomScrollbar({
                        autoHideScrollbar: false,
                        scrollInertia: 0,
                        callbacks: {
                            onScroll : function(){
                                //console.log(mcs);
                                options['offset'] = Math.abs(mcs.top);
                            },
                        },
                        advanced: {
                            autoScrollOnFocus: false,
                            updateOnBrowserResize: false,
                            updateOnContentResize: false
                        }
                    });
                break;
                case "native":
                    options.target.css("overflow", "auto");
                    options.target.on("scroll", function(){
                        //console.log(options.target.scrollTop());
                        options['offset'] = options.target.scrollTop();
                    });
                break;
            }
            _scrolls[options['id']] = options;
        }
        
        function resize(scroll)
        {
            if (!scroll.asize)
            {
                return;
            }
            if (scroll.h)
            {
                var h = 0;
                var blck_bot = $("#hlp_blck_bot");
                var h_bot = 0;
                switch (scroll.h)
                {
                    case 'full':

                        h = $(window).height() - $('#header').outerHeight();
                        if (blck_bot.length == 1 && blck_bot.is(":visible"))
                        {
                          h_bot = blck_bot.outerHeight();
                        }
                        h -= h_bot;
                    break;
                }
                if (h)
                {
//                    var hinc = $("*[data-hinc]:visible");
//                    if (hinc.length)
//                    {
//                        hinc.each(function(){
//                            //alert($(this).outerHeight(true));
//                            h -= $(this).outerHeight(true);
//                        });
//                    }
                    h -= $(scroll.target).offset().top - $('#header').outerHeight();
                    //h -= h_bot;
                    if (scroll['hoffset'])
                    {
                        h -= scroll['hoffset'];
                    }
                    scroll.target.css({height: h, minHeight: h, maxHeight: h});
                }
            }
            
        }
            
    
    
    return {
                
    };
    
    
    
    
});