<?php
/**
 * fastn Integrations for GLPI 11
 * Adds Setup > fastn Integrations, a page that embeds the fastn integrations widget.
 */
define('PLUGIN_FASTNHUB_VERSION', '1.0.0');

function plugin_version_fastnhub()
{
    return [
        'name'         => 'fastn Integrations',
        'version'      => PLUGIN_FASTNHUB_VERSION,
        'author'       => 'fastn',
        'license'      => 'MIT',
        'homepage'     => 'https://fastn.ai',
        'requirements' => ['glpi' => ['min' => '11.0.0']],
    ];
}

function plugin_init_fastnhub()
{
    global $PLUGIN_HOOKS;
    $PLUGIN_HOOKS['menu_toadd']['fastnhub'] = ['config' => \GlpiPlugin\Fastnhub\Hub::class];
}

function plugin_fastnhub_check_prerequisites()
{
    return function_exists('curl_init');
}

function plugin_fastnhub_check_config($verbose = false)
{
    return true;
}
