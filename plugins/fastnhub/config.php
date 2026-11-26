<?php
// fastn Integrations plugin settings.
// Leave values empty to use environment variables instead (FASTN_API_KEY, FASTN_CUSTOMER_ID, ...).
// Paste your fastn API key below (Embed > API keys and MCP > Create key, "Embed backend" preset).
// Keep this file private: it contains a secret.
return [
    'api_key'       => '',               // e.g. fsk_test_...
    'customer_id'   => 'glpi-internal',  // your id for this GLPI in fastn
    'customer_name' => 'GLPI (internal)',
];
