
<form class="ui large grey segment form" id="module-notifier-form">
    {{ form.render('id') }}
    <div class="ten wide field disability">
        <label >{{ t._('module_notifierbotApiKey') }}</label>
        {{ form.render('botApiKey') }}
    </div>
    <div class="ten wide field disability">
        <label >{{ t._('module_notifierchatId') }}</label>
        {{ form.render('chatId') }}
    </div>
<!--     <div class="ten wide field disability"> -->
<!--         <label >{{ t._('module_messageTemplate') }}</label> -->
<!--         {{ form.render('messageTemplate') }} -->
<!--     </div> -->
    {{ partial("partials/submitbutton",['indexurl':'pbx-extension-modules/index/']) }}
</form>