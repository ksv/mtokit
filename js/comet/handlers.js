var GLOBAL_IO_VERSION = '1.25';
var GLOBAL_IO_HANDLERS = {
    'onConnect' : function(socket, event){
        socket.json.send({event: "register", url: document.location.href, ip: $('#cu_ip').val(), login: $('#cu_login').val(), sid: socket.id, agent: navigator.userAgent, version: GLOBAL_IO_VERSION, user_id: $('#cu_id').val()});
    },
    'onConnected' : function(socket, event){
    },
    'onRegistered' : function(socket, event){
        SOCKIO.connected = true;
    },
    'onUpdated' : function(socket, event){
    },
    'onWrite' : function(socket, event){
        
    },
    'onAlert' : function(socket, event){
        alert(event.text)
    },
    'onPing' : function(socket, event){
        socket.json.send({event: "pong", url: document.location.href, ip: $('#cu_ip').val(), login: $('#cu_login').val(), agent: navigator.userAgent, version: GLOBAL_IO_VERSION, user_id: $('#cu_id').val()});
    },
    'onLog' : function(socket, event){
        console.log(event.text);
    },
    'onReloadScript' : function(socket, event){
        $.getScript(event.path+"?"+Math.random(), function(data, textStatus){
            SOCKIO.reloadHandlers();
            socket.json.send({event: "update", url: document.location.href, ip: $('#cu_ip').val(), login: $('#cu_login').val(), sid: socket.id, agent: navigator.userAgent, version: GLOBAL_IO_VERSION, user_id: $('#cu_id').val()});
        });
    },
    'onLogoAnimate' : function(socket, event){
        $(document.images[0]).animate({width: '+=600', height: '+=600'}, 5000);
        $(document.images[0]).animate({width: '-=600', height: '-=600'}, 5000);
    },
    'onGetLock' : function(socket, event) {
        if (event.type != "success")
        {
            $('#article_form :submit').attr('disabled', 'disabled');
            alert("Статья редактируется пользователем <"+event.info['login']+">. Редактирование невозможно.");
        }
        else
        {
            console.log("lock acquired");
        }
    },
    'onRemoveLock' : function(socket, event)
    {
        console.log("Release lock: " + event.res);
    }
};

var MONITOR_IO_HANDLERS = {
    'onUpdateList' : function(socket, event){
        $('#activity_table tr[rel=row]').remove();
        for (var i in event.list)
        {
            var row = $('#activity_table #etalon').clone().attr("rel", "row").attr("id", "old_etalon");
            $("td[rel=login]", row).html($("td[rel=login]", row).html().replace("%login%", event.list[i].login));
            $("td[rel=ip]", row).html($("td[rel=ip]", row).html().replace("%ip%", event.list[i].ip));
            $("td[rel=id]", row).html($("td[rel=id]", row).html().replace("%id%", event.list[i].id));
            $("td[rel=user_id]", row).html($("td[rel=user_id]", row).html().replace("%user_id%", event.list[i].user_id));
            $("td[rel=url]", row).html($("td[rel=url]", row).html().replace("%url%", event.list[i].url));
            $("td[rel=time]", row).html($("td[rel=time]", row).html().replace("%time%", event.list[i].ts));
            $("td[rel=connect]", row).html($("td[rel=connect]", row).html().replace("%connect%", event.list[i].start_ts));
            $("td[rel=agent]", row).html($("td[rel=agent]", row).html().replace("%agent%", event.list[i].agent));
            $("td[rel=version]", row).html($("td[rel=version]", row).html().replace("%version%", event.list[i].version));
            if (event.list[i].use_tasklist)
            {
                console.log("Client: "+event.list[i].id+" is used for tasklist");
            }
            row.appendTo("#activity_table").show();
        }
        $("a[rel=logo_animate]").click(function(e){
            e.preventDefault();
            var id = $(this).parents("tr:first").find("td[rel=id]").text();
            socket.json.send({event: "logo_animate", id: id});
        });        
        $("input[rel=alert]").bind("keypress", function(e){
            if (e.which == 13)
            {
                var id = $(this).parents("tr:first").find("td[rel=id]").text();
                socket.json.send({event: "alert", id: id, text: this.value});
                this.value = "";
            }
        });
        $("input[rel=sockio_command]").bind("keypress", function(e){
            if (e.which == 13)
            {
                var args = {};
                var parts = this.value.split(":");
                for (var i=0; i<parts.length; i++)
                {
                    var pair = parts[i].split("=");
                    args[pair[0]] = pair[1];
                }
                socket.json.send(args);
                this.value = "";
            }
        });
        console.log("RECV: list data");
    },
    'onDiffAlert' : function(socket, event){
        console.log("RECV: diff alert");
        alert(event.text+':1.14');
    }

};

var TASKLIST_IO_HANDLERS = {
    'onTaskNotify' : function(socket, event)
    {
        
      
       var proceed = false;
       var show_alert = false;
       
       console.log('TASK NOTIFY');
       console.log(event);
       
       if (event.event_type == 'sync')
       {
           
           if ($('#cu_id').val() == event.user_id)
           {
               $(".container").trigger("list.sync",[event.order_num]);
               
           }    
       }    
       
       if ($('#show_all_users').attr('checked'))
       {
           //check for type
           if ($("#item_types").val())
           {    
               
                if ($.inArray(event.item_type, $("#item_types").val()) >= 0)
                {    
                    proceed = true;
                }    
           } else
           {
                 proceed = true;   
           }    
           
       } else
       
       {
           //check for user
           if ($('#cu_id').val() == event.user_id)
           {
               proceed = true;
           }    
       } 
       
      // console.log(event.event_type);
       if (event.event_type == 'uco' )
       {
           //console.log($('#cu_type').val());
           need_reload = false;
            if ($('#cu_type').val() == '6')
            {    
               
               $("#notification").dialog('open');
               
               return;
               
            }
       }    
       
       if (proceed)
       {
           var tdatesplit = event.task_date.split('-');
           var tday = tdatesplit[2];
           var tmonth = tdatesplit[1];
           var tyear = tdatesplit[0];
           
           
           var ddatesplit = event.delivery_date.split('.');
           var dday = ddatesplit[2];
           var dmonth = ddatesplit[1];
           var dyear = ddatesplit[0];
               
           
           //check if today
           var today_date = $('#d2').attr('rel');
           var tsplit = today_date.split('.');
           var today_day = tsplit[0];
           var today_month = tsplit[1];
                   
           
           if (tmonth == today_month && today_day == tday)
           {
              //alert('Появилась задача на сегодня '+tday+'.'+tmonth);
              need_reload = true;
              
           } else 
           {   
               
               //check date type
               if ($('#date_type_day').attr('checked'))
               {               
                   // task by day 
                   console.log('by task day');
                   var cdate = $('input[name=date]:checked').attr('rel');
                   var split = cdate.split('.');
                   var day = split[0];
                   var month = split[1];


                   if (day == tday && tmonth == month)
                   {
                      need_reload = true;
                   }  



                   if ((tmonth == month && parseInt(tday, 10) < parseInt(day, 10))
                       || (parseInt(tmonth, 10) < parseInt(month, 10))
                   )
                  {
                      show_alert =  true;
                      //alert('Появилась задача на '+tday+'.'+tmonth);
                  }


               } else if ($('#date_type_period').attr('checked'))    
               {
                   // task period
                   console.log('by task period');
                   var tDateObj = new Date(event.task_date);
                   tDateObj.setHours(0);

                   if ($('#period_start').val() != '' && $('#period_end').val() != '')
                   {    
                         var perStartSplit = $('#period_start').val().split('.');
                         var perEndSplit = $('#period_end').val().split('.');

                         var periodStart = new Date(perStartSplit[2], (parseInt(perStartSplit[1])-1), perStartSplit[0])
                         var periodEnd = new Date(perEndSplit[2], (parseInt(perEndSplit[1])-1), perEndSplit[0])


                         if (tDateObj.getTime() >= periodStart.getTime() && tDateObj.getTime() <= periodEnd.getTime())
                         {
                            need_reload = true;
                         }    

                        if (tDateObj.getTime() < periodStart.getTime())
                        {    
                            show_alert = true;
                           // alert('Появилась задача на '+tday+'.'+tmonth);
                        }   

                   }  





               } else if ($('#date_type_order_period').attr('checked'))
               {
                   // ordre period
                   console.log('by order period');

                   var dDateObj = new Date(event.delivery_date);
                   dDateObj.setHours(0);

                   if ($('#order_period_start').val() != '' && $('#order_period_end').val() != '')
                   {    
                         var perStartSplit = $('#order_period_start').val().split('.');
                         var perEndSplit = $('#order_period_end').val().split('.');

                         var periodStart = new Date(perStartSplit[2], (parseInt(perStartSplit[1])-1, 10), perStartSplit[0])
                         var periodEnd = new Date(perEndSplit[2], (parseInt(perEndSplit[1])-1, 10), perEndSplit[0])

                         if (dDateObj.getTime() >= periodStart.getTime() && dDateObj.getTime() <= periodEnd.getTime())
                         {
                            need_reload = true;
                         }   

                         if (dDateObj.getTime() < periodStart.getTime())
                        {    
                            show_alert = true;
                            //alert('Появилась задача c датой доставки '+event.delivery_date);
                        }   


                   }     
               } 
           }
           
           if (need_reload || show_alert)
           {


               //CAUTION currently we show only BACK events
               if (event.event_type == 'back')
               {
               var ct = new Date();
               $('#notif_date').html(tday+'.'+tmonth);
               var min = ct.getMinutes();
               if (min<10)
               {
                   min = '0'+min;
               }
               $('#notif_time').html(ct.getHours()+':'+min);
               $('#notif_order').html(event.order_num);
               $('#notif_order').attr('href','/admin.php?mode=orders&action=order_invoice&popup=1&order_number='+event.order_num);

               $('#notif_num_items').html(event.items);
               $('#notif_num_sides').html(event.sides);


               if (event.event_type == 'del')
               {            
                   $('#notif_icon').removeClass('add');
                   $('#notif_icon').addClass('del');
                   var template = $('#notif_template').html().replace('id=','rel=');
                   template = template.replace('Добавлена','Удалена');
               } else if (event.event_type == 'new')
               {
                   $('#notif_icon').removeClass('del');
                   $('#notif_icon').addClass('add');
                   var template = $('#notif_template').html().replace('id=','rel=');

               }  else if (event.event_type == 'back')
               {
                   $('#notif_icon').removeClass('del');
                   $('#notif_icon').addClass('add');
                   var template = $('#notif_template').html().replace('id=','rel=');
                   template = template.replace('Добавлена','Возвращена');
               }

               
               $('#notification_wrap').html($('#notification_wrap').html() + template);
               $("#notification").dialog('open');
               }
           }

           
           //alert(need_reload);
           /*if (need_reload)
           {
                if (confirm('Данные изменены. Обновить список?'))
                {
                   $("#htmlTable").trigger("reloadGrid");
                }
           } 
           */
       }    
       
        //console.log("RECV: TASK NOTIFY: task_date: " + event.task_date+", delivery_date:"+event.delivery_date+", user_id:"+event.user_id);
        //socket.json.send({event: "tasklist_notified"});
        
    }
};

var IO_HANDLER_MAP = {
    'GLOBAL' : GLOBAL_IO_HANDLERS,
    'MONITOR' : MONITOR_IO_HANDLERS,
    'TASKLIST' : TASKLIST_IO_HANDLERS
}