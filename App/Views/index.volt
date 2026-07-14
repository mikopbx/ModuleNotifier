
<form class="ui large grey segment form" id="module-notifier-form">
    {{ form.render('id') }}

    <div class="ten wide field disability">
        <label>{{ t._('module_notifier_messengerType') }}</label>
        <div class="ui selection dropdown" id="messengerType-dropdown">
            <input type="hidden" name="messengerType" id="messengerType" value="{{ form.getValue('messengerType') }}">
            <i class="dropdown icon"></i>
            <div class="default text">{{ t._('module_notifier_messengerType') }}</div>
            <div class="menu">
                <div class="item{% if form.getValue('messengerType') != 'vk' %} active selected{% endif %}" data-value="telegram">Telegram</div>
                <div class="item{% if form.getValue('messengerType') == 'vk' %} active selected{% endif %}" data-value="vk">VKontakte</div>
            </div>
        </div>
    </div>

    <div id="telegram-fields">
        <div class="ten wide field disability">
            <label>{{ t._('module_notifierbotApiKey') }}</label>
            {{ form.render('botApiKey') }}
        </div>
        <div class="ten wide field disability">
            <label>{{ t._('module_notifierchatId') }}</label>
            {{ form.render('chatId') }}
        </div>
    </div>

    <div id="vk-fields" style="display:none;">
        <div class="ten wide field disability">
            <label>{{ t._('module_notifier_vkToken') }}</label>
            {{ form.render('vkToken') }}
        </div>
        <div class="ten wide field disability">
            <label>{{ t._('module_notifier_vkPeerId') }}</label>
            <div class="ui action input">
                {{ form.render('vkPeerId') }}
                <button class="ui basic button" type="button" id="btn-get-vk-chats">
                    <i class="search icon"></i> {{ t._('module_notifier_GetChats') }}
                </button>
            </div>
        </div>
        <div class="ten wide field disability" id="vk-chats-field" style="display:none;">
            <label>{{ t._('module_notifier_SelectChat') }}</label>
            <div class="ui selection dropdown" id="vk-chats-dropdown">
                <input type="hidden" id="vk-chat-selected">
                <i class="dropdown icon"></i>
                <div class="default text">{{ t._('module_notifier_SelectChat') }}</div>
                <div class="menu" id="vk-chats-menu">
                </div>
            </div>
        </div>
    </div>

    <div class="ui hidden divider"></div>

    <div class="ten wide field disability">
        <label>{{ t._('module_notifier_numberFilter') }}</label>
        {{ form.render('numberFilter') }}
        <div class="ui pointing label">{{ t._('module_notifier_numberFilter_help') }}</div>
    </div>

    <div class="ten wide field">
        <button class="ui labeled icon basic button" type="button" id="btn-send-test">
            <i class="paper plane icon"></i> {{ t._('module_notifier_SendTest') }}
        </button>
        <span id="test-message-result"></span>
    </div>

    {{ partial("partials/submitbutton",['indexurl':'pbx-extension-modules/index/']) }}
</form>
