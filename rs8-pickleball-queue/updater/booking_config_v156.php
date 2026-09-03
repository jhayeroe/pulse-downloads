<?php
if(!defined('RS8_DREA_MESSENGER_THREAD_ID')) define('RS8_DREA_MESSENGER_THREAD_ID','7347444785310560');
if(!defined('RS8_DREA_MESSENGER_URL')) define('RS8_DREA_MESSENGER_URL','https://www.messenger.com/t/'.RS8_DREA_MESSENGER_THREAD_ID);
function rs8DreaMessengerUrl(): string { return RS8_DREA_MESSENGER_URL; }
function rs8DreaMessengerThreadId(): string { return RS8_DREA_MESSENGER_THREAD_ID; }
