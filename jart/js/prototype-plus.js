//by Gregory Hill
Function.prototype.bindAsEventListener = function(object) {
    var __method = this;
    return function(event) {
        return __method.call(object, new SuperEvent(event || window.event));
    }
}

var SuperEvent = Class.create();
Object.extend(SuperEvent.prototype, {
    initialize: function (event) {
    for (attr in event) {
        this[attr] = event[attr];
    }
        this.target     = Event.element(event);
        this.srcElement = Event.element(event);
        this.which      = event.which || event.button;
        this.button     = event.button || event.which;
        this.pageX      = Event.pointerX(event);
        this.pageY      = Event.pointerY(event);
        this.clientX    = this.pageX - (document.documentElement.scrollLeft
    || document.body.scrollLeft);
        this.clientY    = this.pageY - (document.documentElement.scrollTop
    || document.body.scrollTop);
        this.preventDefault   = Event.stop.bind(Event, event);
        this.stopPropagation  = Event.stop.bind(Event, event);
    }
} ); 

