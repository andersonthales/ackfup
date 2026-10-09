<?php
define('PLUGIN_ACKFUP_VERSION', '1.3.0');

function plugin_init_ackfup()
{
    global $PLUGIN_HOOKS;
    $PLUGIN_HOOKS['csrf_compliant']['ackfup'] = true;
    $PLUGIN_HOOKS['item_add']['ackfup'] = [
        'Ticket'        => 'plugin_ackfup_item_add',
        'Document_Item' => 'plugin_ackfup_document_added',
    ];
}

function plugin_version_ackfup()
{
    return [
        'name'         => 'Acompanhamento automatico de inscricao',
        'version'      => PLUGIN_ACKFUP_VERSION,
        'author'       => 'Anderson Thales',
        'license'      => 'GPLv2+',
        'homepage'     => 'https://github.com/andersonthales/ackfup',
        'requirements' => ['glpi' => ['min' => '10.0.0', 'max' => '11.1.99']],
    ];
}

function plugin_ackfup_check_prerequisites() { return true; }
function plugin_ackfup_check_config($verbose = false) { return true; }
