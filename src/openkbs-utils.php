<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function openkbs_log($data, $prefix = 'Debug') {
    // Check if WP_DEBUG and WP_DEBUG_LOG are enabled
    if (!defined('WP_DEBUG') || !WP_DEBUG || !defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
        return;
    }

    // Get timestamp
    $timestamp = current_time('Y-m-d H:i:s');

    // Prepare the log message
    if (is_array($data) || is_object($data)) {
        $log_message = print_r($data, true);
    } else {
        $log_message = $data;
    }

    // Format the complete log entry
    $log_entry = "[{$timestamp}] [{$prefix}] {$log_message}\n";

    // Use WordPress native error logging
    error_log($log_entry);
}


function openkbs_load_svg($svg_path) {
    $icon_path = plugin_dir_path(__FILE__) . $svg_path;
    if (file_exists($icon_path)) {
        return 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($icon_path));
    }
    return '';
}

function openkbs_enqueue_polling_scripts() {
    $screen = get_current_screen();
    
    // Only enqueue on post edit screens
    if ($screen->base === 'post' || $screen->base === 'post-new') {
        $post_id = get_the_ID();
        
        // Check if we should start polling
        $polling_key = 'openkbs_polling_' . $post_id;

        $should_poll = get_transient($polling_key);
        
        if ($should_poll) {
            // Delete the transient immediately to prevent future polling
            delete_transient($polling_key);
            
            wp_enqueue_script(
                'openkbs-polling',
                plugins_url('js/openkbs-polling.js', __FILE__),
                array('jquery'),
                '1.0',
                true
            );
            
            wp_localize_script('openkbs-polling', 'openkbsPolling', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'post_id' => $post_id,
                'nonce' => wp_create_nonce('openkbs_polling_nonce'),
                'max_polls' => 60,
                'turtle_logo' => openkbs_load_svg('assets/icon.svg')
            ));
        }
    }
}

function openkbs_enqueue_scripts() {
    wp_enqueue_script(
        'openkbs-functions',
        plugins_url('js/openkbs-functions.js', __FILE__),
        array('jquery'),
        '1.0.0',
        true
    );

    wp_localize_script('openkbs-functions', 'openkbsVars', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('openkbs-functions-nonce'),
        'i18n' => array(
            'connectToOpenKBS' => __('Connect to OpenKBS', 'openkbs'),
            'requestingAccess' => __('OpenKBS is requesting access to your WordPress site.', 'openkbs'),
            'knowledgeBase' => __('Knowledge Base:', 'openkbs'),
            'cancel' => __('Cancel', 'openkbs'),
            'approveConnection' => __('Approve', 'openkbs')
        )
    ));
}

function openkbs_evp_kdf($password, $salt, $keySize, $ivSize) {
    $targetKeySize = $keySize + $ivSize;
    $derivedBytes = '';
    $block = '';
    while (strlen($derivedBytes) < $targetKeySize) {
        $block = md5($block . $password . $salt, true);
        $derivedBytes .= $block;
    }
    $key = substr($derivedBytes, 0, $keySize);
    $iv = substr($derivedBytes, $keySize, $ivSize);
    return array('key' => $key, 'iv' => $iv);
}

function openkbs_encrypt_kb_item($item, $passphrase) {
    $passphrase = mb_convert_encoding($passphrase, 'UTF-8');
    $item = mb_convert_encoding($item, 'UTF-8');

    $salt = openssl_random_pseudo_bytes(8);

    $keySize = 32;
    $ivSize = 16;
    $derived = openkbs_evp_kdf($passphrase, $salt, $keySize, $ivSize);
    $key = $derived['key'];
    $iv = $derived['iv'];
    $encrypted = openssl_encrypt($item, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    $encryptedData = 'Salted__' . $salt . $encrypted;
    return base64_encode($encryptedData);
}

function openkbs_store_secret($secret_name, $secret_value, $token) {
    $response = wp_remote_post('https://kb.openkbs.com/', array(
        'body' => json_encode(array(
            'token' => $token,
            'action' => 'createSecretWithKBToken',
            'secretName' => $secret_name,
            'secretValue' => $secret_value
        )),
        'headers' => array(
            'Content-Type' => 'application/json'
        )
    ));
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);
    
    return isset($result['success']) && $result['success'] === true;
}

function openkbs_modify_admin_footer_text() {
    return '';
}

function openkbs_remove_update_footer() {
    return '';
}

// Sign valid payment transaction to OpenKBS service
function openkbs_sign_payload($payload, $accountId, $publicKey, $walletPrivateKey, $expiresInSeconds = 60) {
    try {
        // Check if publicKey hash matches accountId
        $hashHex = hash('sha256', $publicKey);
        if (substr($hashHex, 0, 32) !== $accountId) {
            throw new Exception("Public key does not belong to this accountId $accountId");
        }

        // Define JWT header
        $header = [
            'alg' => 'ES256',
            'typ' => 'JWT'
        ];

        // Add 'exp' to payload
        $payload['exp'] = time() + $expiresInSeconds;

        // Encode header and payload to Base64Url
        $encodedHeader = openkbs_base64_url_encode(json_encode($header));
        $encodedPayload = openkbs_base64_url_encode(json_encode($payload));

        // Prepare data to sign
        $dataToSign = $encodedHeader . '.' . $encodedPayload;

        // Decode base64 private key from 'walletPrivateKey'
        $privateKeyDer = base64_decode($walletPrivateKey);

        // Convert DER to PEM format
        $privateKeyPem = openkbs_der_to_pem($privateKeyDer, 'PRIVATE KEY');

        // Sign the data
        $signature = openkbs_create_signature($dataToSign, $privateKeyPem);

        // Base64Url encode the signature
        $encodedSignature = openkbs_base64_url_encode($signature);

        // Construct the JWT
        return $dataToSign . '.' . $encodedSignature;

    } catch (Exception $e) {
        error_log('JWT Signing Error: ' . $e->getMessage());
        return null;
    }
}

function openkbs_create_signature($data, $privateKeyPem) {
    // Sign the data using OpenSSL with ECDSA using SHA256
    $signature = '';
    $success = openssl_sign($data, $signature, $privateKeyPem, OPENSSL_ALGO_SHA256);

    if (!$success) {
        throw new Exception('Failed to sign data');
    }

    // Convert DER signature to R and S components
    $rs = openkbs_der_to_rs($signature);

    return $rs;
}

function openkbs_der_to_rs($der_signature) {
    $offset = 0;
    if (ord($der_signature[$offset++]) != 0x30) {
        throw new Exception('Invalid DER signature (expected sequence)');
    }

    // Skip length
    if (ord($der_signature[$offset]) & 0x80) {
        $lengthBytes = ord($der_signature[$offset++]) & 0x7F;
        $offset += $lengthBytes;
    } else {
        $offset++;
    }

    // INTEGER for R
    if (ord($der_signature[$offset++]) != 0x02) {
        throw new Exception('Invalid DER signature (expected integer for R)');
    }

    $rLength = ord($der_signature[$offset++]);
    if ($rLength & 0x80) {
        $lengthBytes = $rLength & 0x7F;
        $rLength = 0;
        for ($i = 0; $i < $lengthBytes; $i++) {
            $rLength = ($rLength << 8) + ord($der_signature[$offset++]);
        }
    }

    $r = substr($der_signature, $offset, $rLength);
    $offset += $rLength;

    // INTEGER for S
    if (ord($der_signature[$offset++]) != 0x02) {
        throw new Exception('Invalid DER signature (expected integer for S)');
    }

    $sLength = ord($der_signature[$offset++]);
    if ($sLength & 0x80) {
        $lengthBytes = $sLength & 0x7F;
        $sLength = 0;
        for ($i = 0; $i < $lengthBytes; $i++) {
            $sLength = ($sLength << 8) + ord($der_signature[$offset++]);
        }
    }

    $s = substr($der_signature, $offset, $sLength);

    // Pad R and S to 32 bytes
    $r = openkbs_pad_zero(ltrim($r, "\x00"), 32);
    $s = openkbs_pad_zero(ltrim($s, "\x00"), 32);

    return $r . $s;
}

function openkbs_pad_zero($data, $size) {
    return str_pad($data, $size, "\x00", STR_PAD_LEFT);
}

function openkbs_der_to_pem($der_data, $label) {
    $pem = chunk_split(base64_encode($der_data), 64, "\n");
    return "-----BEGIN $label-----\n$pem-----END $label-----\n";
}

function openkbs_base64_url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function openkbs_generate_txn_id() {
    return sprintf('%d-%d',
        round(microtime(true) * 1000),
        rand(100000, 999999)
    );
}

function openkbs_create_account_id($publicKeyBase64) {
    return substr(hash('sha256', $publicKeyBase64), 0, 32);
}

function openkbs_get_apps() {
    static $cached_apps = null;

    if ($cached_apps === null) {
        $cached_apps = get_option('openkbs_apps', array());
    }

    return $cached_apps;
}

function openkbs_get_embedding_models() {
    return [
        'text-embedding-3-large' => [
            'accountId' => 'e08661d0b2fad0873b63be1f122c92a1',
            'name' => 'OpenAI Text Embedding v3 large',
            'context' => 8191,
            'default_dimension' => 1536,
            'max_dimension' => 3072,
        ],
        'text-embedding-3-small' => [
            'accountId' => '1c4e67b5351f79272f7ebe20f0495557',
            'name' => 'OpenAI Text Embedding v3 small',
            'default_dimension' => 1536,
            'max_dimension' => 1536,
            'context' => 8191
        ]
    ];
}

// Helper function to calculate cosine similarity
function openkbs_cosine_similarity($vec1, $vec2) {
    $dot_product = 0;
    $mag1 = 0;
    $mag2 = 0;

    foreach ($vec1 as $i => $val1) {
        $dot_product += $val1 * $vec2[$i];
        $mag1 += $val1 * $val1;
        $mag2 += $vec2[$i] * $vec2[$i];
    }

    $mag1 = sqrt($mag1);
    $mag2 = sqrt($mag2);

    if ($mag1 == 0 || $mag2 == 0) {
        return 0;
    }

    return $dot_product / ($mag1 * $mag2);
}