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
});
