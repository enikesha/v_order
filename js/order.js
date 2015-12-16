window.pages = {
    global: function() {
        document.getElementById('logout').addEventListener('click', authLogout, false);
    },
    deposit: function(){
        document.getElementById('deposit').addEventListener('click', deposit, false);
    }
};

function authLogout(e) {
    e.preventDefault();

    document.cookie = "vk_app_" + VK_APP_ID + '=; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=/; domain=.' + window.location.hostname;
    document.location.reload();
}

function deposit(e) {
    e.preventDefault();
    var input = document.getElementById('inputAmount');
    if (!input.value) {
        $(input).popover({content: 'Введите сумму для пополнения',
                          placement: 'left',
                          trigger: "manual"
                         })
            .popover('show');
        input.value = '';
        setTimeout(function(){$(input).popover('hide')}, 2000);
        return;
    }
    ajax.post("/deposit", {amount: input.value}, function(r){
        //document.location.reload();
    });
}
