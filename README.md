# ModuleNotifier - Call Notifications for MikoPBX

*Read this in other languages: [English](README.md), [Русский](readme.ru.md).*

Module for sending call notifications to **Telegram** or **VKontakte** group chats. Tracks incoming/outgoing calls and sends formatted messages with call recordings as voice messages.

## Features

- Real-time call notifications (incoming, outgoing, missed)
- Call recording delivery as voice messages (OGG)
- Message editing with call status updates
- Support for Telegram and VKontakte
- VK chat discovery via API
- Test message sending from web UI
- Phone number formatting (+7XXXXXXXXXX)

## Setup - Telegram

1. Create a bot via [@BotFather](https://t.me/BotFather)
2. Copy the bot token
3. Add the bot to a group chat and get the chat ID
4. In MikoPBX module settings: select **Telegram**, enter token and chat ID
5. Click **Send test message** to verify

## Setup - VKontakte

### 1. Create a VK community

![Create community](docs/img/01-create-community.png)

Set the community type:

![Community type](docs/img/02-community-type.png)

### 2. Create an API access key

Go to **Management** > **Settings** > **API Usage** > **Access Keys** > **Create Key**

![API keys](docs/img/03-api-keys.png)

Enable **Community messages** permission:

![Create key](docs/img/04-create-key.png)

Copy the generated token:

![Copy token](docs/img/05-copy-token.png)

### 3. Enable community messages

Go to **Management** > **Messages** > enable **Community messages**

![Enable messages](docs/img/06-enable-messages.png)

Without this, the API will return an error when trying to send messages.

### 4. Allow adding the community to chats

Go to **Community messages** > **Bot settings** > enable **Allow adding to chats**

![Bot settings](docs/img/07-bot-settings.png)

### 5. Create a community chat

Use the **Create chat** action:

![Create chat](docs/img/08-create-chat.png)

![New chat](docs/img/09-new-chat.png)

Click on the chat and join it. You can add members in the chat settings.

### 6. Configure the MikoPBX module

- Select notification channel **VKontakte**
- Enter the token
- Click **Get chats**
- Select a chat from the list
- Click **Send test message**
- Save settings

![Module settings](docs/img/10-module-settings.png)

## Questions

Join our developer Telegram channel [@mikopbx_dev](https://t.me/joinchat/AAPn5xSqZIpQnNnCAa3bBw)
