<?php
/**
 * Classe 2FA con Google Authenticator per 747 Disco CRM
 * 
 * @package Disco747_CRM
 * @version 1.0.0
 */

namespace Disco747_CRM\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe per gestione Two-Factor Authentication
 */
class Disco747_TwoFactor {

    /**
     * Meta key per secret OTP
     */
    const SECRET_META_KEY = 'disco747_otp_secret';
    
    /**
     * Meta key per abilitazione 2FA
     */
    const ENABLED_META_KEY = 'disco747_2fa_enabled';
    
    /**
     * Meta key per backup codes
     */
    const BACKUP_CODES_META_KEY = 'disco747_backup_codes';

    /**
     * Genera secret per Google Authenticator
     * 
     * @return string Secret in base32
     */
    public static function generate_secret() {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $secret;
    }

    /**
     * Genera URL per QR Code (Google Authenticator)
     * 
     * @param string $secret Secret base32
     * @param string $username Username utente
     * @param string $issuer Nome app (747 Disco CRM)
     * @return string URL per QR code
     */
    public static function get_qr_code_url($secret, $username, $issuer = '747 Disco CRM') {
        $url = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            rawurlencode($issuer),
            rawurlencode($username),
            $secret,
            rawurlencode($issuer)
        );
        
        // Usa Google Charts API per generare QR code
        return sprintf(
            'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=%s',
            urlencode($url)
        );
    }

    /**
     * Verifica codice OTP
     * 
     * @param string $secret Secret base32
     * @param string $code Codice a 6 cifre inserito dall'utente
     * @param int $discrepancy Tolleranza tempo (default 1 = ±30 secondi)
     * @return bool True se valido
     */
    public static function verify_code($secret, $code, $discrepancy = 1) {
        $time = floor(time() / 30);
        
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            if (self::get_code($secret, $time + $i) === $code) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Genera codice OTP per un dato timestamp
     * 
     * @param string $secret Secret base32
     * @param int $time Timestamp diviso per 30
     * @return string Codice a 6 cifre
     */
    private static function get_code($secret, $time) {
        $secret = self::base32_decode($secret);
        $time = pack('N*', 0) . pack('N*', $time);
        $hash = hash_hmac('sha1', $time, $secret, true);
        $offset = ord($hash[19]) & 0xf;
        
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Decodifica base32
     * 
     * @param string $secret Secret in base32
     * @return string Binary secret
     */
    private static function base32_decode($secret) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper($secret);
        $binary = '';
        
        for ($i = 0; $i < strlen($secret); $i++) {
            $char = $secret[$i];
            $pos = strpos($chars, $char);
            
            if ($pos === false) {
                continue;
            }
            
            $binary .= sprintf('%05b', $pos);
        }
        
        $bytes = '';
        for ($i = 0; $i < strlen($binary); $i += 8) {
            $byte = substr($binary, $i, 8);
            if (strlen($byte) == 8) {
                $bytes .= chr(bindec($byte));
            }
        }
        
        return $bytes;
    }

    /**
     * Abilita 2FA per un utente
     * 
     * @param int $user_id ID utente
     * @param string $secret Secret generato
     * @return bool
     */
    public static function enable_for_user($user_id, $secret = null) {
        if (!$secret) {
            $secret = self::generate_secret();
        }
        
        update_user_meta($user_id, self::SECRET_META_KEY, $secret);
        update_user_meta($user_id, self::ENABLED_META_KEY, '1');
        
        // Genera backup codes
        $backup_codes = self::generate_backup_codes();
        update_user_meta($user_id, self::BACKUP_CODES_META_KEY, $backup_codes);
        
        return true;
    }

    /**
     * Disabilita 2FA per un utente
     * 
     * @param int $user_id ID utente
     * @return bool
     */
    public static function disable_for_user($user_id) {
        delete_user_meta($user_id, self::SECRET_META_KEY);
        delete_user_meta($user_id, self::ENABLED_META_KEY);
        delete_user_meta($user_id, self::BACKUP_CODES_META_KEY);
        
        return true;
    }

    /**
     * Verifica se 2FA è abilitato per un utente
     * 
     * @param int $user_id ID utente
     * @return bool
     */
    public static function is_enabled_for_user($user_id) {
        return get_user_meta($user_id, self::ENABLED_META_KEY, true) === '1';
    }

    /**
     * Ottiene secret di un utente
     * 
     * @param int $user_id ID utente
     * @return string|false
     */
    public static function get_user_secret($user_id) {
        $secret = get_user_meta($user_id, self::SECRET_META_KEY, true);
        return !empty($secret) ? $secret : false;
    }

    /**
     * Genera codici di backup
     * 
     * @param int $count Numero codici (default 10)
     * @return array
     */
    public static function generate_backup_codes($count = 10) {
        $codes = array();
        
        for ($i = 0; $i < $count; $i++) {
            $codes[] = sprintf(
                '%04d-%04d-%04d',
                random_int(0, 9999),
                random_int(0, 9999),
                random_int(0, 9999)
            );
        }
        
        return $codes;
    }

    /**
     * Verifica backup code
     * 
     * @param int $user_id ID utente
     * @param string $code Codice backup
     * @return bool
     */
    public static function verify_backup_code($user_id, $code) {
        $backup_codes = get_user_meta($user_id, self::BACKUP_CODES_META_KEY, true);
        
        if (!is_array($backup_codes)) {
            return false;
        }
        
        $key = array_search($code, $backup_codes);
        
        if ($key !== false) {
            // Rimuovi codice usato
            unset($backup_codes[$key]);
            update_user_meta($user_id, self::BACKUP_CODES_META_KEY, array_values($backup_codes));
            return true;
        }
        
        return false;
    }

    /**
     * Ottiene backup codes di un utente
     * 
     * @param int $user_id ID utente
     * @return array
     */
    public static function get_backup_codes($user_id) {
        $codes = get_user_meta($user_id, self::BACKUP_CODES_META_KEY, true);
        return is_array($codes) ? $codes : array();
    }

    /**
     * Imposta cookie per ricordare dispositivo (30 giorni)
     * 
     * @param int $user_id ID utente
     * @return string Token dispositivo
     */
    public static function set_trusted_device($user_id) {
        $token = wp_generate_password(32, false);
        
        // Salva hash token
        $devices = get_user_meta($user_id, 'disco747_trusted_devices', true);
        if (!is_array($devices)) {
            $devices = array();
        }
        
        $devices[wp_hash($token)] = time();
        update_user_meta($user_id, 'disco747_trusted_devices', $devices);
        
        // Imposta cookie (30 giorni)
        setcookie(
            'disco747_trusted_device',
            $token,
            time() + (30 * DAY_IN_SECONDS),
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true // HttpOnly
        );
        
        return $token;
    }

    /**
     * Verifica se dispositivo è trusted
     * 
     * @param int $user_id ID utente
     * @return bool
     */
    public static function is_trusted_device($user_id) {
        if (!isset($_COOKIE['disco747_trusted_device'])) {
            return false;
        }
        
        $token = $_COOKIE['disco747_trusted_device'];
        $devices = get_user_meta($user_id, 'disco747_trusted_devices', true);
        
        if (!is_array($devices)) {
            return false;
        }
        
        $hash = wp_hash($token);
        
        if (isset($devices[$hash])) {
            // Verifica che non sia scaduto (30 giorni)
            if (time() - $devices[$hash] < (30 * DAY_IN_SECONDS)) {
                return true;
            } else {
                // Rimuovi dispositivo scaduto
                unset($devices[$hash]);
                update_user_meta($user_id, 'disco747_trusted_devices', $devices);
            }
        }
        
        return false;
    }

    /**
     * Rimuovi tutti i dispositivi trusted
     * 
     * @param int $user_id ID utente
     */
    public static function remove_all_trusted_devices($user_id) {
        delete_user_meta($user_id, 'disco747_trusted_devices');
    }
}