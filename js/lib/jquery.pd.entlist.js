(function($){

	$.widget("pd.entlist", {
		options: {
		    model: null,
                    ctrl: null,
                    load_url: null,
                    init_params: '',
                    offset: 0,
                    user: 0,
                    limit: 2,
                    total_num:0,
                    total_pages:1,
                    show_first:false,
                    show_prev:true,
                    show_last:false,
                    show_next:true,
                    pager_container_class: 'pager',
                    filter_form_id: 'filter_form',
                    list_total_id: null,
                    get_method:null,
                    set_params_method:null,
                    per_page_container: 'per_page',
                    page_tpl: "<li><a href='#' rel='[PAGE_NUM]' class='btn'>[PAGE_NUM]</a></li>",
                    active_page_tpl: "<li><a href='#' class='btn disabled'>[PAGE_NUM]</a></li>",
                    first_tpl: "",
                    page_wrap_around:2,
                    pager_separator_tpl: "<li class='sep'>...</li>",
                    prev_tpl: "<li class='prev'><a href='#' class='btn' rel='[PAGE_NUM]'>&lsaquo;</a></li>",
                    prev_disabled_tpl: "<li class='prev'><a href='#' class='btn disabled'>&lsaquo;</a></li>",
                    next_tpl: "<li class='next'><a href='#' class='btn' rel='[PAGE_NUM]'>&rsaquo;</a></li>",
                    next_disabled_tpl: "<li class='next'><a href='#' class='btn disabled' rel='[PAGE_NUM]'>&rsaquo;</a></li>",
                    last_tpl: "",
                    list_item_tpl_id: 'list_item_tpl',
                    callback:null,
                    paging_method: 'usual', //cab be 'usual|infinite'
                    //for infinite paging method  
                    next_btn: null,                                                        
                    next_btn_loading: null,
                    max_portions:3,                                   
                    loaded:0
		},
                
                //for infinite paging
                last_rendered: 0,
                last_loaded: 0,
                lock: false,                
                load_index:  0,                
                num_portions:0,
                current_offset:0,
                
		_create: function()
                {
                    //var total = parseInt(this.option('total_num'));
                    if (this.option('paging_method') == 'infinite')
                    {                            
                        
                         
                            var tgt = this;

                            this.load_next();
                            this.option('next_btn').on('click', function () 
                            {
                                $(this).hide();
                                tgt.option('next_btn_loading').show();
                                tgt.num_portions = 0;
                                tgt.handle_load();
                            })	


                            $(document).bind('sl_load', function ()
                                {
                                    console.log('got load event');
                                    tgt.handle_load();     
                                }
                            );

                                                    
                           this.bind_scroll();
                                
                        //this.handle_load();        
                        this.reload();
                          
                    }  else
                    {                        
                        
                        //USUAL PAGING
                        $('.'+this.option('per_page_container')).bind('change keyup', function (evt)
                        {
                            $('#'+ $(this).attr('rel')).entlist('option','items_per_page',$(this).val());
                        }); 
                        
                        this.load();                        
                    }    
			
                    
                    
                    //console.log('CREATED = '+this.inited)
		},

		_init: function()
                {                    
                    
			this.inited = true;
		},
                
                bind_scroll: function ()
                {
                   var tgt = this;
                   this.element.parent().bind("scroll", function(event) {

                               // console.log(tgt.option('next_btn_loading')[0].offsetTop);
                               //console.log(tgt.option('next_btn_loading').position());
                               if($(this).scrollTop() +   $(this).innerHeight() >= $(this)[0].scrollHeight)
                                {
                                    
                                    //    console.log('fire_load_evt');
                                        tgt.fire_load_evt();
                                
                                }
                                }
                            );  
                },
                
                unbind_scroll: function ()
                {
                   this.element.parent().unbind("scroll");
                },
                load: function()
                {       

                    if (this.option('paging_method') == 'infinite')
                    { 
                        this.handle_load();
                       
                   } else
                   {
                        this._trigger('loadstart');
                        this.element.html('<br><br><br><br><br><br>');
                        this.element.mask('Загружаем данные...');
                        var $self = this;
                        this.process_get_data(1, 1);

                        this._trigger('loadcomplete');
                   }    

                },
                
                process_get_data: function (replace, draw_pager, cb)
                {
                    var $self = this;
                    
                    if (this.option('load_url'))
                    {
                        $.get(this.option('load_url'), {
                            offset: this.option('offset'),
                            limit: this.option('limit'),
                            init_params: this.option('init_params')
                        }, function(ret)
                        {
                                                        
                            if (ret.list.length>0)
                            {    
                                if (replace)
                                {    
                                    $self.element.html(tmpl($self.option('list_item_tpl_id'), ret));
                                    $self.element.unmask(); 

                                } else
                                {
                                    if (!$self.option('next_btn'))
                                    {    
                                        $self.element.html($self.element.html() + tmpl($self.option('list_item_tpl_id'), ret));
                                    } else
                                    {

                                        //$(tmpl($self.option('list_item_tpl_id'), ret)).insertBefore($self.option('next_btn').parent());
                                        $self.element.html($self.element.html() + tmpl($self.option('list_item_tpl_id'), ret));
                                        
                                    }    
                                }    
                            }

                            if (draw_pager)
                            {    
                                $self.draw_pager(ret);
                            }     

                            $('.'+$self.option('per_page_container')).each( function (){
                                    $(this).val(ret.limit);
                                });  

                            if (cb)    
                            {
                                cb(ret);
                            }
                                
                            if ($self.option("callback"))
                            {
                                $self.option("callback")(ret);
                            }
                        }, "json");
                    } else 
                    {    
                    
                        $.post("/ajax/call", {json:1, service: this.option('ctrl'), method: this.option('get_method'), args: {offset: this.option('offset'), limit: this.option('limit'), init_params:this.option('init_params')}}, 
                            function(d)
                            {                                

                                var ret = eval('(' +d+')');   
                                this.option('total_num', ret.total)

                                if (replace)
                                {    
                                    $self.element.html(tmpl($self.option('list_item_tpl_id'), ret));
                                    $self.element.unmask(); 
                                } else
                                {
                                    //$self.element.html(tmpl($self.option('list_item_tpl_id'), ret)+$self.element.html());
                                    if (!$self.option('next_btn'))
                                    {    
                                        $self.element.html($self.element.html() + tmpl($self.option('list_item_tpl_id'), ret));
                                    } else
                                    {
                                        //$(tmpl($self.option('list_item_tpl_id'), ret)).insertBefore($self.option('next_btn').parent());
                                        $self.element.html($self.element.html() + tmpl($self.option('list_item_tpl_id'), ret));
                                    } 
                                }    
                                
                                if (draw_pager)
                                {    
                                    $self.draw_pager(ret);
                                }     

                                $('.'+$self.option('per_page_container')).each( function (){
                                        $(this).val(ret.limit);
                                    });  

                                if (cb)    
                                {
                                    cb(ret);
                                }
                                
                                if ($self.option('callback'))
                                {
                                    var fn = $self.option('callback');
                                    fn(ret);
                                }    
                        });
                    }    
                },
                
                apply_filters:  function()
                {
                    var $self = this;

                    $.post("/ajax/call", {service: this.option('ctrl'), method: 'apply_filters', args: $('#filter_form').serializeArray()}, function(d)
                    {                    
                        $self.load();    
                        $('#filters').dialog('close');
                    });
                 },
                 
                /*set_options: function (data)
                 {
                     if (typeof(data) == 'object')
                     {    
                        for (var i in data)
                        {
                            console.log(i);
                            this.options[i] = data[i];
                            switch(i)
                            {
                               case 'items_per_page':
                                   this.options['limit'] = data[i];
                               break;    
                            }
                            
                            data['offset'] = 0;
                            var $self = this;

                        }
                        if (this.option('set_params_url'))
                        {
                            if (this.option('paging_method') != 'infinite')
                            {
                                $.get(this.option('set_params_url'), data, function(){
                                    $self.load();
                                });
                           }     
                        }
                        else
                        {
                            if (this.option('paging_method') != 'infinite')
                            {
                                $.post("/ajax/call", {service: this.option('ctrl'), method: this.option('set_params_method'), 'args': data}, function(d)
                                {                    
                                    $self.load();    
                                });
                            }    
                        }
                     }
                 },
                */

             

               draw_pager: function (data)
               {
                   var current_page = parseInt(data.current_page);
                   var total_pages = parseInt(data.pages);
                   var pages_to_show = parseInt(this.option('page_wrap_around'))*2+1;
                   var wrap_around = parseInt(this.option('page_wrap_around'));
                   
                   var re =  new RegExp("\\[PAGE_NUM\\]","g");
                   $('.'+this.option('pager_container_class')).html('');

                   if (current_page > 1)
                   {

                       //show first
                       //if (this.option('show_first'))
                       //{
                       //    $('.'+this.option('pager_container_class')).append(this.option('first_tpl').replace(re,1));
                       //}    
                       //show prev
                       if (this.option('show_prev'))
                       {
                           $('.'+this.option('pager_container_class')).append(this.option('prev_tpl').replace(re,(current_page-1)));
                       }

                   } else
                   {
                       //show prev
                       if (this.option('show_prev'))
                       {
                           $('.'+this.option('pager_container_class')).append(this.option('prev_disabled_tpl'));
                       }
                   }    

                  // console.log(data);

                   //build navigation
                   
                   if (total_pages<=pages_to_show)
                   {    
                       for (var i=1; i<=total_pages; i++)
                       {
                           if (i != current_page)
                           {    
                               $('.'+this.option('pager_container_class')).append(this.option('page_tpl').replace(re,i));
                           } else
                           {
                              // console.log(data.listOpts);
                              $('.'+this.option('pager_container_class')).append(this.option('active_page_tpl').replace(re,i));  
                           }    
                       }
                   } else
                   {
                       //first
                       if (1 != current_page)
                       {    
                           $('.'+this.option('pager_container_class')).append(this.option('page_tpl').replace(re,1));
                       } else
                       {
                          // console.log(data.listOpts);
                          $('.'+this.option('pager_container_class')).append(this.option('active_page_tpl').replace(re,1));  
                       }  
                       
                       var start_pg =  current_page-wrap_around;
                       if (start_pg <2)
                       {
                           start_pg = 2;
                       }    
                       
                       var end_pg;
                       if (current_page>=pages_to_show)
                       {    
                            end_pg = current_page+wrap_around;
                            
                       } else
                       {
                            end_pg = pages_to_show;
                       }    
                       
                       if (end_pg>=total_pages)
                       {
                           end_pg = total_pages-1;
                       }                         
                       
                       //first  separator
                       if (start_pg> 2)
                       {
                           $('.'+this.option('pager_container_class')).append(this.option('pager_separator_tpl'));
                       } 
                           
                        for (var i=start_pg; i<=end_pg; i++)
                       {
                           if (i != current_page)
                           {    
                               $('.'+this.option('pager_container_class')).append(this.option('page_tpl').replace(re,i));
                           } else
                           {
                              // console.log(data.listOpts);
                              $('.'+this.option('pager_container_class')).append(this.option('active_page_tpl').replace(re,i));  
                           }    
                       }   
                           
                           
                       //pre last separator
                       if (total_pages-1 > end_pg)
                       {
                           $('.'+this.option('pager_container_class')).append(this.option('pager_separator_tpl'));
                       }    
                           
                       //last
                       if (total_pages != current_page)
                       {    
                           $('.'+this.option('pager_container_class')).append(this.option('page_tpl').replace(re,total_pages));
                       } else
                       {
                          // console.log(data.listOpts);
                          $('.'+this.option('pager_container_class')).append(this.option('active_page_tpl').replace(re,total_pages));  
                       }
                       
                   }    

                   if (current_page < total_pages)
                   {

                       //show next
                       if (this.option('show_next'))
                       {
                           $('.'+this.option('pager_container_class')).append(this.option('next_tpl').replace(re,(current_page+1)));
                       }

                       //show last
                       //if (this.option('show_last'))
                       //{
                       //    $('.'+this.option('pager_container_class')).append(this.option('next_tpl').replace(re,total_pages));
                       //}
                   } else
                   {
                       //show next
                       if (this.option('show_next'))
                       {
                           $('.'+this.option('pager_container_class')).append(this.option('next_disabled_tpl'));
                       }
                   }    

                   var $self = this;

                    $('a','.'+this.option('pager_container_class')).bind('click' , function(e){
                       e.preventDefault();
                       if ($(this).attr('rel'))
                       {    
                            var go_page = parseInt($(this).attr('rel'));
                            $self.option('offset', (go_page-1) * $self.option('limit'));               
                            $self.load();
                       }     

                    });


               },

		
		/*_setOption: function(key, value)
                {
			this.options[key] = value;
                        switch(key)
                        {
                            case 'items_per_page':
                                this.options['limit'] = value;
                            break;    
                        }
                        var args = new Object();
                        args[key] = value;
                        args['offset'] = 0;
                        var $self = this;
                        
                        if (this.option('set_params_url'))
                        {
                           
                            $.get(this.option('set_params_url'), args, function(){
                                //$self.load();
                            });
                                
                        }
                        else
                        {
                            
                            $.post("/ajax/call", {service: this.option('ctrl'), method: this.option('set_params_method'), 'args': args}, function(d)
                            {                    
                                //$self.option();
                                //$self.load();    

                            });
                                
                        }
		},
                */
               
               set_param: function (key,value)
               {
                   this.options[key] = value;
                   var args = new Object();
                    args[key] = value;
                    args['offset'] = 0;
                    var $self = this;
                    console.log('KEY = ' + key);
                    console.log('VAl = ' + value);
                    console.log('surl = ' + this.option('set_params_url'));
                    
                    if (this.option('set_params_url'))
                    {
                           
                            $.post(this.option('set_params_url'), args, function(){
                                $self.reload();
                            });
                                
                    }
                    else
                    {
                            
                            $.post("/ajax/call", {service: this.option('ctrl'), method: this.option('set_params_method'), 'args': args}, function(d)
                            {                    
                                
                                $self.reload();    

                            });
                                
                    }
                    
               },
                
                fire_load_evt: function ()
                {
                    $(document).trigger('sl_load');                    
                },
                
                reload: function ()
                {
                    if (this.option('paging_method') != 'infinite')
                    {
                        this.load();
                    } else
                    {
                        this.num_portions = 0;
                        this.last_loaded = 0;
                        this.load_index = 0;
                        this.option('offset', 0);
                        this.element.find('div.file').remove();
                        this.handle_load();
                    }    
                },
            
                handle_load: function ()
                {
                    console.log('HANDLE LOAD');

                    if (this.num_portions<=this.option('max_portions') && !this.lock)
                    {
                        console.log('load_next');
                        this.load_next();        
                        
                    } else if (this.num_portions>this.option('max_portions'))
                    {
                       // this.option('next_btn').show();
                       // this.option('next_btn_loading').hide();
                    }    
                },
                
                load_next: function ()
                {


                   
                    
                    if (this.load_index>0 && this.last_loaded >= this.option('total_num'))
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
                    
                    this.process_get_data(false, false, function (res) 
                    {
                        
                        
                        tgt.last_loaded += res.list.length;
                       // console.log('LASt LOADED IND' + tgt.last_loaded);
                        tgt.option('offset',tgt.last_loaded);
                        tgt.option('total_num',res.total_num);
                        
                        if (!res.list.length)
                        {
                            tgt.option('next_btn').hide();
                            tgt.option('next_btn_loading').hide();
                        } 
                        console.log(res.total_num);
                        console.log(tgt.last_loaded);
                        
                        if (res.total_num<=tgt.last_loaded)                        
                        {
                            tgt.option('next_btn_loading').hide();
                        } else
                        {
                            tgt.option('next_btn_loading').show();
                        }    
                        
                        if (tgt.num_portions>tgt.option('max_portions'))
                        {
                            tgt.option('next_btn').show();
                            tgt.option('next_btn_loading').hide();
                        }
                        
                        tgt.load_index++;
                        tgt.lock = false;
                    });
                    
                },

              
                 destroy: function() 
                 {                       
                    // In jQuery UI 1.8, you must invoke the destroy method from the base widget
                    $.Widget.prototype.destroy.call(this);
                    // In jQuery UI 1.9 and above, you would define _destroy instead of destroy and not call the base method
               }     
                
	});


})(jQuery);

function elementInViewport(el) {
    var rect = el.getBoundingClientRect()

    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= window.innerHeight &&
        rect.right <= window.innerWidth 
        )
}