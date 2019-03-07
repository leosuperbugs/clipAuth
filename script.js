jQuery( document ).ready(function($) {
    $(".paperclip__adminProcess form")
        .submit(
        function (ev) {
            console.log('into ajax');
            $.post('/lib/exe/ajax.php', $(this).serialize()).
            done(function () {swal("禁言成功", "该用户已经被禁止编辑条目和添加评论", "success")}).
            fail(function () {swal("操作失败", "请检查网络连接并重试", "error")});
            ev.preventDefault();
        });
    
    var form = $("#dw__editform");
    $("#edbtn__save").click(
        function (ev) {
            var res = '';
            $.ajax({
                url: '/lib/exe/ajax.php',
                async: false,
                dataType:'text',
                type:"POST",
                data: form.serialize(),
                success:function(msg){
                    var reg = /true/;
                    res = msg.match(reg);                    
                }
        });
        if(!res){ 
            swal('编辑失败', "评论含有敏感词", "error");
            return false; 
        }else{
            $("#dw__editform").submit();
        }
    });

    $("#dw__editform").css("display","block");
    $(".toolbar").css("display","block");


    $("#adsearchbox").find('p').css("margin-bottom","0.5rem");
    $("#adminsearch_form").find('input[type=radio]').change(function(){
        $("#adminsearch_form").submit();
    });
    
    $(".flatpickr" ).flatpickr({
        "locale": "zh",
        enableTime: true,
        dateFormat: "Y-m-d H:i",
    });

    //register form verification
    var regform = $("#dw__register");
    var errArr = new Array();
    regform.find("button[type='submit']").attr("disabled","disabled");
    //check submit button should be disabled
    var checkButton = function(){
        var valnum = 0;
        var isnull = false;
        $("#dw__register").find("div[class='form__wrapper']").find("input").each(function(){
            var val = $(this).val();
            if (!val) {
                isnull = true;  
                return;  
            }                            
        });
        if ($(".regErrmsg").length > 0)
            var haserr = true;
        else
            var haserr = false;
        return (isnull || haserr);
    };
    var regverify = function (regform, own, parent, iname, ivalue, data) {
        if (own.is("input[name="+iname+"]")) {
            $.post("/lib/exe/ajax.php",regform.serialize(),function(msg){
                var res = JSON.parse(msg.replace(/AJAX.*/,''));
                if (res[iname]) {
                    parent.find("."+iname).remove();
                    parent.append("<span class='regErrmsg "+iname+"'>" + res[iname] + "</span>");
                    $("input[name="+iname+"]").addClass("errorinput");
                } else {
                    parent.find("."+iname).remove();
                    $("input[name="+iname+"]").removeClass("errorinput");
                }
                //hide apply invitecode
                if ($(".regErrmsg").length > 0) {
                    regform.find("p").css("display","none");
                }
                //button should be disabled
                if (checkButton()) {
                    regform.find("button[type='submit']").attr("disabled","disabled");
                }else{
                    regform.find("button[type='submit']").removeAttr("disabled");
                }
            });
        }
    };
    // add error msg span dom
    $("#dw__register").find("div[class='form__wrapper']").find("input").each(function(){
        var inpname = $(this).attr("name");
        $(this).after('<span class="regErrmsg '+inpname+'" style=display:none;></span>');
    });
    // keyup event
    $("#dw__register").find("input").keyup(function(ev) {
        var parent = $(this).parent();
        var iname = $(this).attr("name");
        var ivalue = $(this).val();
        var own = $(this);
        var data = {"call": "reg_submit"};
        data[iname] = ivalue;
        regverify(regform, own, parent, iname, ivalue, data);
    });
    //blur event
    $("#dw__register").find("input").blur(function(ev) {
        var parent = $(this).parent();
        var iname = $(this).attr("name");
        var ivalue = $(this).val();
        var own = $(this);
        var data = {"call": "reg_submit"};
        data[iname] = ivalue;
        regverify(regform, own, parent, iname, ivalue, data);
    });
    $("#dw__register").find("input").click(function(){
        if ($(this).hasClass("errorinput")) {
            $(this).removeClass("errorinput");
            $("."+$(this).attr("name")).css("display","none");
        }
    });
    
    /**
     * textaera auto height
     * @param                {HTMLElement}        输入框元素
     * @param                {Number}                设置光标与输入框保持的距离(默认0)
     * @param                {Number}                设置最大高度(可选)
     */
    var autoTextarea = function (elem, extra, maxHeight) {
            extra = extra || 0;
            var isFirefox = !!document.getBoxObjectFor || 'mozInnerScreenX' in window,
            isOpera = !!window.opera && !!window.opera.toString().indexOf('Opera'),
                    addEvent = function (type, callback) {
                            elem.addEventListener ?
                                    elem.addEventListener(type, callback, false) :
                                    elem.attachEvent('on' + type, callback);
                    },
                    getStyle = elem.currentStyle ? function (name) {
                            var val = elem.currentStyle[name];
                            
                            if (name === 'height' && val.search(/px/i) !== 1) {
                                    var rect = elem.getBoundingClientRect();
                                    return rect.bottom - rect.top -
                                            parseFloat(getStyle('paddingTop')) -
                                            parseFloat(getStyle('paddingBottom')) + 'px';        
                            };
                            
                            return val;
                    } : function (name) {
                                    return getComputedStyle(elem, null)[name];
                    },
                    minHeight = parseFloat(getStyle('height'));
            
            
            elem.style.resize = 'none';
            
            var change = function () {
                    var scrollTop, height,
                            padding = 0,
                            style = elem.style;
                    
                    if (elem._length === elem.value.length) return;
                    elem._length = elem.value.length;
                    
                    if (!isFirefox && !isOpera) {
                            padding = parseInt(getStyle('paddingTop')) + parseInt(getStyle('paddingBottom'));
                    };
                    scrollTop = document.body.scrollTop || document.documentElement.scrollTop;
                    
                    elem.style.height = minHeight + 'px';
                    if (elem.scrollHeight > minHeight) {
                            if (maxHeight && elem.scrollHeight > maxHeight) {
                                    height = maxHeight - padding;
                                    style.overflowY = 'auto';
                            } else {
                                    height = elem.scrollHeight - padding;
                                    style.overflowY = 'hidden';
                            };
                            style.height = height + extra + 'px';
                            scrollTop += parseInt(style.height) - elem.currHeight;
                            document.body.scrollTop = scrollTop;
                            document.documentElement.scrollTop = scrollTop;
                            elem.currHeight = parseInt(style.height);
                    };
            };
            
            addEvent('propertychange', change);
            addEvent('input', change);
            addEvent('focus', change);
            change();
    };
    var textarea = document.getElementById('wiki__text');
    if (textarea){
        autoTextarea(textarea);
    }
    $(".mode_edit").find("p:first").css('color','#777777');
    $(".mode_preview").find("p:first").css('color','#777777');

});
