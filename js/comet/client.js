function ioSockClient(handlers)
{
    this.socket = null;
    this.handlers = {};
    this.loaded_handlers = [];
    this.connected = false;
    if (handlers)
    {
        for (var i in handlers)
        {
            this.handlers[i] = handlers[i];
        }
    }
}

ioSockClient.prototype.addHandlers = function(handlers, name)
{
    for (var i in handlers)
    {
        this.handlers[i] = handlers[i];
    }
    if (typeof(name) != "undefined")
    {
        this.loaded_handlers.push(name);
    }
}

ioSockClient.prototype.reloadHandlers = function()
{
    for (var i=0; i<this.loaded_handlers.length; i++)
    {
        this.addHandlers(IO_HANDLER_MAP[this.loaded_handlers[i]]);
    }
}

ioSockClient.prototype.connect = function()
{
    if (navigator.userAgent.toLowerCase().indexOf('chrome') != -1)
    {
        this.socket = io.connect("http://localhost:8080", {'transports': ['xhr-polling'], 'sync disconnect on unload' : true});
    }
    else
    {
        this.socket = io.connect("http://localhost:8080", {'sync disconnect on unload' : true});
    }
    var instance = this;
    this.socket.on("connect", function(){
        instance.socket.on("message", function(msg){
            var s = "on"+msg.event;
            console.log('RECV:' + msg.event);
            if (typeof(instance.handlers[s]) == "undefined")
            {
                console.log(s+" not found");
            }
            instance.handlers[s](instance.socket, msg);
        });
        instance.handlers['onConnect'](instance.socket);
    })
}

ioSockClient.prototype.disconnect = function()
{
    this.socket.disconnect();
}

ioSockClient.prototype.send = function(event, args)
{
    var instance = this;
    if (!instance.connected)
    {
        console.log("timeout");
        setTimeout(function(){
            console.log("SEND(try):" + event);
            instance.send(event, args);
        }, 1000);
        return;
    }
    if (typeof(args) == "undefined")
    {
        args = {};
    }
    args['event'] = event;
    this.socket.json.send(args);
    console.log("SEND(real):" + event);
}