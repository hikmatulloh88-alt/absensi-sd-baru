<?php
/**
 * Konfigurasi contoh Magic Edit AI.
 *
 * PENGATURAN:
 * 1. Salin file ini menjadi config/magic_edit.local.php.
 * 2. Isi api_key dengan API Key OpenAI milik sekolah/pengelola.
 * 3. Jangan unggah magic_edit.local.php ke GitHub.
 *
 * Alternatif: set environment variable OPENAI_API_KEY pada server.
 */
return [
    'api_key' => 'ISI_API_KEY_OPENAI_DI_SERVER',
    'model' => 'gpt-image-2.5-sunburst',
    'timeout' => 240,
];
