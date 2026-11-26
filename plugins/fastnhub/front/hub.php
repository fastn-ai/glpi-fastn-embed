<?php
// GLPI 11 boots the session itself for plugin front files (no include of inc/includes.php).
use GlpiPlugin\Fastnhub\Hub;

Session::checkRight('config', READ);
Html::header(Hub::getTypeName(), $_SERVER['PHP_SELF'], 'config', Hub::class);

try {
    $token = Hub::embedToken();
    $src   = Hub::settings()['api_base'] . '/api/v1/embed/iframe?token=' . rawurlencode($token);
    echo '<div class="card"><div class="card-body p-0">';
    echo '<iframe src="' . htmlspecialchars($src, ENT_QUOTES) . '" allow="clipboard-write"'
       . ' style="width:100%;height:calc(100vh - 160px);min-height:600px;border:none"></iframe>';
    echo '</div></div>';
} catch (\Throwable $e) {
    echo '<div class="alert alert-danger m-3">' . htmlspecialchars($e->getMessage()) . '</div>';
}

Html::footer();
