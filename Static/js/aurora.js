/**
    $(document).ready(function(){
        $(function () {
            Aurora.init();
        });
    });
 */
var Aurora = function () {
    'use strict';

    // Private methods
    var handleInit = function () {
        console.log("Aurora:handleInit()");
    };

    // Public methods
    return {
        // Aurora.init();
        init: function () {
            handleInit();
        }
    };
}();