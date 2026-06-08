<?php
use \OCA\Ocsms\Lib\CountryCodes;

\OCP\Util::addStyle('ocsms', 'style');
\OCP\Util::addScript('ocsms', 'lodash.min');
\OCP\Util::addScript('ocsms', 'vue.min');
\OCP\Util::addScript('ocsms', 'twemoji/twemoji.min');
\OCP\Util::addScript('ocsms', 'anchorme.min');
// Production
\OCP\Util::addScript('ocsms', 'app.min');
\OCP\Util::addScript('ocsms', 'ocsms-search');
\OCP\Util::addScript('ocsms', 'compose');
?>

<script type="text/x-template" id="modal-template" xmlns:v-on="http://www.w3.org/1999/xhtml"
        xmlns:v-bind="http://www.w3.org/1999/xhtml">
    <transition name="modal" v-if="show">
        <div class="modal-mask">
            <div class="modal-wrapper">
                <div class="modal-container">
                    <div class="modal-body">{{ bodyMessage }}</div>
                    <div class="modal-footer">
                        <slot name="footer">
                            <button class="modal-default-button" @click="show = false">
                                <slot name="button-cancel"><?php p($l->t('Cancel'));?></slot>
                            </button>
                            <button class="modal-default-button modal-crit-button" @click="doYes">
                                <slot name="button-ok"><?php p($l->t('Confirm'));?></slot>
                            </button>
                        </slot>
                    </div>
                </div>
            </div>
        </div>
    </transition>
</script>

<div id="app">
    <div id="ocsms-left">
        <div id="app-mailbox-peers">
            <div class="ocsms-searchbox">
                <span class="icon-search"></span>
                <input type="search" id="ocsms-contact-search"
                       placeholder="<?php p($l->t('Search a contact…'));?>" class="ocsms-search-input" />
            </div>
            <div id="app-contacts-loader" class="icon-loading" v-if="isContactsLoading"></div>
            <div class="contact-list-no-contact" v-if="orderedContacts.length === 0 && !isContactsLoading">
                <?php p($l->t('No contact found.'));?>
            </div>
            <ul class="contact-list" v-show="orderedContacts.length > 0 && !isContactsLoading">
                <li v-for="contact in orderedContacts" peer-label="{{ contact.label }}" v-on:click="loadConversation(contact);" href="#">
                    <img class="ocsms-plavatar" :src="contact.avatar" v-if="contact.avatar !== undefined" />
                    <div class="ocsms-plavatar" v-if="contact.avatar === undefined" v-bind:style="{'backgroundColor': getContactColor(contact.uid) }">{{ contact.label | firstCharacter }}</div>
                    <a class="ocsms-plname" style="{{ contact.unread > 0 ? 'font-weight:bold;' : ''}}" mailbox-label="{{ contact.label }}" mailbox-navigation="{{ contact.nav }}">{{ contact.label }}{{ contact.unread > 0 ? ' (' + contact.unread + ') ' : '' }}</a>
                </li>
            </ul>
        </div>
        <div id="app-settings">
            <div id="app-settings-header">
                <button name="app settings" class="settings-button" data-apps-slide-toggle="#app-settings-content">
                    <?php p($l->t('Settings'));?>
                </button>
            </div>
            <div id="app-settings-content">
                <div><label for="setting_msg_per_page"><?php p($l->t('Max messages to load per conversation'));?></label>
                    <input type="number" min="10" max="10000" name="setting_msg_per_page" v-model="messageLimit" v-on:change="sendMessageLimit()" to-int />
                    <span class="label-invalid-input" v-if="messageLimit == null || messageLimit === undefined"><?php p($l->t('Invalid message limit'));?></span>
                </div>
                <div><label for="intl_phone"><?php p($l->t('Default country code'));?></label>
                    <select name="intl_phone" id="sel_intl_phone" v-model="country">
                        <?php foreach (CountryCodes::$codes as $code => $cval) { ?>
                            <option><?php p($l->t($code)); ?></option>
                        <?php } ?>
                    </select>
                    <button class="new-button primary icon-checkmark-white" v-on:click="sendCountry();"></button>
                </div>
                <div>
                    <label for="setting_contact_order"><?php p($l->t('Contact ordering'));?></label>
                    <select name="setting_contact_order" v-model="contactOrderBy" v-on:change="sendContactOrder()">
                        <option value="lastmsg"><?php p($l->t('Last message'));?></option>
                        <option value="label"><?php p($l->t('Label'));?></option>
                    </select>
                    <label for="setting_contact_order_reverse"><?php p($l->t('Reverse ?'));?></label>
                    <input type="checkbox" v-model="reverseContactOrder" v-on:change="sendContactOrder()" />
                </div>
                <div>
                    <label for="setting_notif"><?php p($l->t('Notification settings'));?></label>
                    <select name="setting_notif" v-model="enableNotifications" v-on:change="sendNotificationFlag()">
                        <option value="1"><?php p($l->t('Enable'));?></option>
                        <option value="0"><?php p($l->t('Disable'));?></option>
                    </select>
                </div>
                <div>
                    <button class="crit-button primary"
                            v-confirm="['<?php p($l->t('Are you sure you want to wipe all your messages ?'));?>', wipeAllMessages]">
                        <?php p($l->t('Reset all messages'));?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="ocsms-right">
        <div id="ocsms-loader" class="icon-loading" v-if="isConvLoading"></div>
        <div id="ocsms-header" v-if="!isConvLoading && messages.length > 0" v-bind:style="{backgroundColor: getContactColor(selectedContact.uid) }">
            <div id="ocsms-contact-avatar">
                <img class="ocsms-plavatar-big" v-if="selectedContact.avatar !== undefined" :src="selectedContact.avatar" />
                <div class="ocsms-plavatar-big" v-if="selectedContact.avatar === undefined">{{ selectedContact.label | firstCharacter }}</div>
            </div>
            <div id="ocsms-contact-details">
                <div id="ocsms-phone-label">{{ selectedContact.label }}</div>
                <div id="ocsms-phone-opt-number">{{ selectedContact.opt_numbers }}</div>
                <div id="ocsms-phone-msg-nb"><?php p($l->t('%s messages shown of %s messages stored in database.', array( '{{ messages.length }}', '{{ totalMessageCount }}')));?></div>
            </div>
            <div id="ocsms-contact-actions">
                <button id="ocsms-conversation-removal" class="ocsms-delete-btn" v-on:click="removeConversation();"
                        title="<?php p($l->t('Delete conversation'));?>">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path d="M9 3v1H4v2h1v13a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6h1V4h-5V3H9zm0 5h2v9H9V8zm4 0h2v9h-2V8z"/>
                    </svg>
                </button>
            </div>
        </div>
        <div id="ocsms-msg-search" v-if="!isConvLoading && messages.length > 0">
            <span class="icon-search"></span>
            <input type="search" id="ocsms-msg-search-input"
                   placeholder="<?php p($l->t('Search in messages…'));?>" class="ocsms-search-input" />
            <span id="ocsms-search-count" class="ocsms-msg-count"></span>
            <button id="ocsms-search-prev" class="ocsms-nav-btn" title="<?php p($l->t('Previous'));?>" style="display:none">&#9650;</button>
            <button id="ocsms-search-next" class="ocsms-nav-btn" title="<?php p($l->t('Next'));?>" style="display:none">&#9660;</button>
            <span id="ocsms-search-none" class="ocsms-msg-none" style="display:none"><?php p($l->t('No results'));?></span>
        </div>
        <div id="ocsms-messages-wrap" v-if="!isConvLoading">
            <div v-if="messages.length === 0" id="ocsms-empty-conversation"><?php p($l->t('Please select a conversation from the list to load it.'));?></div>
            <div v-if="messages.length > 0" class="ocsms-messages-container">
                <div v-for="message in orderedMessages">
                    <div v-bind:class="['msg-'+ message.type]">
                        <div v-html="message.content"></div>
                        <button class="ocsms-delete-btn ocsms-msg-delete-btn" v-on:click="removeConversationMessage(message.id);"
                                title="<?php p($l->t('Delete message'));?>">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                <path d="M9 3v1H4v2h1v13a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6h1V4h-5V3H9zm0 5h2v9H9V8zm4 0h2v9h-2V8z"/>
                            </svg>
                        </button>
                        <div class="msg-date">{{ message.date | date:'medium' }}</div>
                    </div>
                    <div class="msg-spacer"></div>
                </div>
            </div>
        </div>
    </div>
</div>
