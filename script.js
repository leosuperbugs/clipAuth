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
    
});
