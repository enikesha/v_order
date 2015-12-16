window.pages = {
    global: function() {
        document.getElementById('logout').addEventListener('click', authLogout, false);
    },
    deposit: function(){
        document.getElementById('deposit').addEventListener('click', deposit, false);
        document.getElementById('verify').addEventListener('click', verify, false);
    }
};

function authLogout(e) {
    e.preventDefault();

    document.cookie = "vk_app_" + VK_APP_ID + '=; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=/; domain=.' + window.location.hostname;
    document.location.reload();
}

function showError(input, text) {
    $(input).popover({content: text, placement: 'left', trigger: "manual"}).popover('show');
    input.value = '';
    setTimeout(function(){$(input).popover('destroy')}, 2000);
};

function deposit(e) {
    e.preventDefault();

    var input = document.getElementById('inputAmount');
    if (!input.value) {
        showError(input, 'Введите сумму для пополнения');
        return;
    }
    ajax.post("/deposit", {amount: input.value}, function(r){
        if (r.code) {
            document.getElementById('verify-code').innerText = r.code;
            document.getElementById('deposit-form').disabled = true;
            document.getElementById('verify-form').className = '';
            document.getElementById('inputVerify').focus();
        } else {
            var msg;
            switch (r.error) {
            case 'BAD_AMOUNT':
                msg = 'Неверная сумма';
                break;
            case 'EXISTING_DEPOSIT':
                msg = 'Пополнение в процессе';
            case 'DEPOSIT_ERROR':
            default:
                msg = "Ошибка пополнения";
            }
            showError(input, msg);
        }
    });
}

function verify(e) {
    e.preventDefault();

    var input = document.getElementById('inputVerify');
    if (!input.value) {
        showError(input, 'Введите код подтверждения');
        return;
    }

    ajax.post("/deposit", {verify: input.value}, function(r){
        if (r.raw) {
            $(".balance").text(r.balance);
            document.getElementById('inputAmount').value='';
            document.getElementById('inputVerify').value='';
            document.getElementById('verify-form').className = 'hidden';
            document.getElementById('verify-code').innerText = '';
            document.getElementById('deposit-form').disabled = false;
        } else {
            var msg;
            switch (r.error) {
            case 'BAD_VERIFY':
                msg = 'Неверный код';
                break;
            case 'NO_DEPOSIT':
                msg = 'Введите сумму пополнения';
            case 'DEPOSIT_ERROR':
            default:
                msg = "Ошибка пополнения";
            }
            showError(input, msg);
        }
    });
}
