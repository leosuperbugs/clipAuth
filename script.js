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
});
