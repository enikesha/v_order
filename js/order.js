window.pages = {
    reached_end: false,
    loading: false,
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
    window.addEventListener('scroll', onScroll, false);
    pollQueue(window.queues.mine, true);
    if (window.queues.common)
        pollQueue(window.queues.common, false);
}

function pollQueue(params, mine) {
    data = {act: 'a_check', key: params.key, ts: params.ts, id: window.queues.id, wait: 120};
    ajax.post('/im255', data, function(r){
        if (!r.events) {
            console.log(r);
            return;
        }

        for (var i = 0; i < r.events.length; i++)
            handleEvent(r.events[i], mine);

        params.ts = r.ts;
        setTimeout(function(){ pollQueue(params, mine) }, 10);
    });
}

function findOrder(localId) {
    var ul = document.getElementById('orders');
    for (var i=0; i<ul.childElementCount-1; i++) {
        var li = ul.children[i];
        if (li.getAttribute('data-id') == localId)
            return li;
    }
    return null;
}

function prependOrder(html) {
    var orders = document.getElementById('orders');
    orders.innerHTML = html + orders.innerHTML;
    document.getElementById('no-orders').className = 'hidden';
}

function removeOrder(li) {
    if (li && li.parentElement) {
        if (li.parentElement.childElementCount == 2)
            document.getElementById('no-orders').className = '';
        li.remove();
    }
}

function setBalance(balance) {
    Array.prototype.forEach.call(document.querySelectorAll('.balance'), function (el){
        el.innerText = balance;
    });
}

function handleEvent(e, mine) {
    if (e.balance)
        setBalance(e.balance);

    if (e.cancel) {
        removeOrder(findOrder(e.cancel));
    } else if (e.commit) {
        // Don't changes to our orders from mine queue on common page
        if (mine && window.queues.common)
            return;
        var li = findOrder(e.commit);
        if (li) {
            if (e.html) {
                li.outerHTML = e.html;
            } else {
                removeOrder(li);
            }
        }
    } else if (e.order) {
        if (findOrder(e.order.id))
            return;
        // Don't add new our orders from common queue
        if (!mine && e.order.uid == window.queues.id)
            return;
        prependOrder(e.html);
    }
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
            setBalance(r.balance);
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
        if (!r.error) {
            setBalance(r.balance);
            if (!findOrder(r.order.id))
                prependOrder(r.html);
            title.value = description.value = price.value = '';
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
            setBalance(r.balance);
            removeOrder(li);
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

function loadMore(e) {
    var orders = document.getElementById('orders');
    if (orders.childElementCount < 2)
        return;

    window.pages.loading = true;
    var progress = document.getElementById('loading');
    progress.className = 'progress';

    var lastId = orders.children[orders.childElementCount-2].getAttribute('data-id');
    ajax.get('', {o: lastId}, function(r) {
        if (r) {
            var empty = orders.children[orders.childElementCount-1];
            var emptyHTML = empty.outerHTML;
            empty.remove();
            orders.innerHTML += r + emptyHTML;
            //if (history && history.replaceState) {
            //    history.replaceState({}, document.title, '?o='+lastId);
            //}
        } else {
            window.pages.reached_end = true;
        }
        progress.className = 'progress hidden';
        window.pages.loading = false;
    });
}


function onScroll(e) {
    if (window.pages.reached_end || window.pages.loading)
        return;
    var doc = document.documentElement;
    var heightLeft = doc.scrollHeight - window.scrollY - doc.clientHeight;
    if (heightLeft < 150)
        loadMore();
}
