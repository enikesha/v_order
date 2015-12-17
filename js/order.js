window.pages = {
    global: function() {
        document.getElementById('logout').addEventListener('click', authLogout, false);
    },
    index: function() {
        listenOrders();
    },
    mine: function() {
        listenOrders();
    },
    deposit: function(){
        document.getElementById('deposit').addEventListener('click', deposit, false);
        document.getElementById('verify').addEventListener('click', verify, false);
    }
};

function listenOrders() {
    document.getElementById('add-order').addEventListener('click', addOrder, false);
}

function authLogout(e) {
    e.preventDefault();

    document.cookie = "vk_app_" + VK_APP_ID + '=; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=/; domain=.' + window.location.hostname;
    document.location.reload();
}

function showError(input, text) {
    $(input).popover({content: text, placement: 'top', trigger: "manual"}).popover('show');
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
        if (r.balance) {
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

function addOrder(e) {
    e.preventDefault();

    var title = document.getElementById('inputTitle');
    if (!title.value) {
        showError(title, 'Введите заголовок');
        return;
    }
    var description = document.getElementById('inputDescription');
    if (!description.value) {
        showError(description, 'Введите описание');
        return;
    }
    var price = document.getElementById('inputPrice');
    if (!price.value) {
        showError(price, 'Введите цену');
        return;
    }

    ajax.post("/order", {title: title.value,
                         description: description.value,
                         price:price.value}, function(r){
        if (r.html) {
            $(".balance").text(r.balance);
            var orders = document.getElementById('orders');
            orders.innerHTML = r.html + orders.innerHTML;
        } else {
            var msg;
            switch (r.error) {
            case 'BAD_ORDER':
                msg = 'Ошибка в объявлении';
                break;
            case 'INSUFFICIENT_FUNDS':
                msg = 'Недостаточно средств на балансе';
                break;
            case 'ORDER_ERROR':
            default:
                msg = "Ошибка добавления объявления";
            }
            showError(price, msg);
        }
    });
}

function orderAct(e, act) {
    e = e || window.event;
    var targ = e.target || e.srcElement;
    if (targ.nodeType == 3) targ = targ.parentNode; // defeat Safari bug

    e.preventDefault();

    var li = targ.parentElement;
    while (li.tagName != "LI") li = li.parentElement;
    var localId = li.getAttribute('data-id');

    ajax.post("/order/" + localId, {act: act}, function(r){
        if (r.ok) {
            console.log(r);
            $(".balance").text(r.balance);
            li.remove();
        } else {
            var msg;
            switch (r.error) {
            case 'ORDER_CANCELLED':
                msg = 'Заказ уже отменен';
                break
            case 'ORDER_COMMITTED':
                msg = 'Заказ уже исполнен';
                break;
            case 'START_TRANS':
            case 'CANCEL_ORDER':
            case 'COMMIT_ORDER':
            default:
                msg = "Ошибка " + (act == 'cancel' ? "отмены" : "выполнения") + " заказа";
            }
            showError(targ, msg);
        }
    });
}
