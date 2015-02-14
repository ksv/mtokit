(function($){

	$.widget("pd.scrollist", {
		options: {
                    url: null,
                    total_items: 0,
                    renderer: null,
                    next_btn: null,                                                        
                    max_portions:3,               
                    limit_per_page: 40,
                    render_limit: 12,
                    clone_elem:null
                    
		    
		},
                
                last_rendered: null,
                last_loaded: null,
                lock: false,
                render_lock:  false,
                load_index:  0,
                render_index:  0,
                edata: [],
                num_portions:1,
                clone_render:null,
                
		_create: function()
                {
                    
                    var total = parseInt(this.option('total_items'));
                    this.last_loaded = parseInt(this.option('loaded'));
                    
                    if (total>this.last_loaded)
                    {    
                    
                        this.last_rendered = parseInt(this.option('loaded'));
                        for (var i=0; i<this.last_loaded; i++)
                        {
                            this.edata.push({});
                        }
                       // window.setInterval(this.fire_load_evt, 200);
                        window.setInterval(this.fire_render_evt, 80);

                       // console.log(this);

                        var tgt = this;

                        this.load_next();
                        this.option('next_btn').on('click', function () 
                        {
                            $(this).hide();
                            tgt.option('next_btn_loading').show();
                            tgt.num_portions = 0;
                            tgt.handle_load();
                        })	


                        //console.log(this.element);
                        $(document).bind('sl_load', function (){
                            console.log('got load event');
                            //console.log(tgt);
                           tgt.handle_load();     
                        });

                        $(document).bind('sl_render', function (){
                            //console.log('got render event');
                            //console.log(tgt);
                           tgt.handle_render();     
                        });

                       // this.load_next();
                        this.clone_render = $(this.option('clone_elem')).clone().attr("src", "/templates/images/tmp_loading.gif");

                         $(window).bind("scroll", function(event) {

                            if (tgt.option('next_btn_loading').is(":in-viewport"))
                            {
                                console.log('fire_load_evt');
                                tgt.fire_load_evt();
                            }
                        }
                        );
                   } else
                   {
                        this.option('next_btn').hide();
                        this.option('next_btn_loading').hide();
                   }    
                   
                    
		},
                
                fire_load_evt: function ()
                {
                    $(document).trigger('sl_load');
                    //console.log('fire load evt');
                },
                
                fire_render_evt: function ()
                {
                    $(document).trigger('sl_render');
                },
                
                
                handle_render: function ()
                {
                    //console.log(this.edata.length);
                    //console.log(this.last_rendered);
                    //console.log(this.render_lock);
                    
                    if (this.edata.length >= this.last_rendered && !this.render_lock)
                    {
                        this.render_next();
                    }
                    
                },
                handle_load: function ()
                {
                    //console.log('HANDLE LOAD');

                    if (this.num_portions<=this.option('max_portions') && !this.lock)
                    {
                        this.load_next();        
                        
                    } else if (this.num_portions>this.option('max_portions'))
                    {
                       // this.option('next_btn').show();
                       // this.option('next_btn_loading').hide();
                    }    
                },
                
            load_next: function ()
            {
                
                    
                if (this.last_loaded >= this.option('total_items'))
                {
                     this.option('next_btn').hide();
                     this.option('next_btn_loading').hide();
                     return;
                }
                
                if (this.lock)
                {
                    return;
                }    
                
                this.num_portions++;
               // console.log("Load#" + load_index + " started. Current loaded: " + last_loaded);
                this.lock = true;
                
                var tgt = this;
                $.get(this.option('url'), {
                    bg: 1,
                    bg_start: this.last_loaded,
                    bg_limit: this.option('limit_per_page')
                    
                }, function(res)
                {
                    //console.log('Load result came back');
                    if (tgt.num_portions>tgt.option('max_portions'))
                    {
                        tgt.option('next_btn').show();
                        tgt.option('next_btn_loading').hide();
                    }
                    for (var i=0; i<res.length; i++)
                    {
                        tgt.edata.push(res[i]);
                        //console.log("Load: " + res[i]['id']);
                        tgt.last_loaded++;
                    }
                   // console.log("Load#" + load_index + " eneded. Loaded " +res.length + " entries. Current loaded: " + last_loaded);
                    tgt.load_index++;
                    tgt.lock = false;
                }, "json");
            },

            render_next: function ()
            {
               // console.log('Last rendered ='+ this.last_rendered +" length"+ this.edata.length);
                if (this.last_rendered >= this.edata.length)
                {
                    return;
                }
                this.render_lock = true;
                //item.clone().css("background-color", "red").appendTo(cont);
                //console.log("Render#" + render_index + " started. Current rendered: " + last_rendered);
                for (var i=0; i<this.option('render_limit'); i++)
                {
                    if (typeof(this.edata[this.last_rendered]) == "undefined" || typeof(this.edata[this.last_rendered]['id']) == "undefined")
                    {
                        this.render_lock = false;
                        return;
                    }
                    //console.log("Rendering item: " + (last_rendered) + ":" + data[last_rendered]['id']);
                    this.render_item(this.edata[this.last_rendered]);
                    this.last_rendered++;
                }
               // console.log("Render#" + render_index + " ended. Current rendered: " + last_rendered);
                this.render_index++;
                this.render_lock = false;
            },

            render_item: function (info)
            {
                var element = this.option('renderer')(this.clone_render,info);
                this.element.append(element);
                element.trigger('scroll_render');
            }
});


})(jQuery);
