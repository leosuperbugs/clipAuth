jQuery( document ).ready(function($) {
    $(".paperclip__adminProcess form").submit(
        function () {
            console.log('into ajax');
            $.post('/lib/exe/ajax.php', $(this).serialize())
                .done(function () {
            alert('success');
        })
        }
        );

});
