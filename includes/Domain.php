<?php
declare(strict_types=1);
namespace Syvo\BusinessDirectory;
if (!defined('ABSPATH')) { exit; }

final class Security {
    public static function normalize_digits(string $value): string {
        return strtr($value, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
    }
    public static function normalize_text(string $value): string {
        $value = trim(wp_strip_all_tags($value));
        $value = strtr($value, ['ي'=>'ی','ى'=>'ی','ك'=>'ک','ة'=>'ه','ۀ'=>'ه','ؤ'=>'و','إ'=>'ا','أ'=>'ا']);
        $value = preg_replace('/\s+/u', ' ', $value) ?: $value;
        return mb_strtolower($value, 'UTF-8');
    }
    public static function normalize_mobile(string $value): string|\WP_Error {
        $v = preg_replace('/[^0-9+]/', '', self::normalize_digits($value)) ?: '';
        $v = preg_replace('/^\+98/', '0', $v) ?: $v;
        $v = preg_replace('/^0098/', '0', $v) ?: $v;
        if (!preg_match('/^09\d{9}$/', $v)) return new \WP_Error('invalid_mobile','شماره موبایل واردشده معتبر نیست.');
        return $v;
    }
    public static function validate_national_id(string $value): bool {
        $id = self::normalize_digits($value);
        if (!preg_match('/^\d{10}$/',$id) || preg_match('/^(\d)\1{9}$/',$id)) return false;
        $sum=0; for($i=0;$i<9;$i++) $sum+=(int)$id[$i]*(10-$i);
        $r=$sum%11; $check=(int)$id[9]; return $r<2 ? $check===$r : $check===11-$r;
    }
    public static function encrypt_sensitive(string $plain): string|\WP_Error {
        if ($plain==='') return '';
        $key = hash('sha256', wp_salt('auth'), true);
        if (function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            try {
                $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
                $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain,'',$nonce,$key);
                return 's1:'.base64_encode($nonce.$cipher);
            } catch (\Throwable $e) { return new \WP_Error('encryption_failed','ذخیره امن اطلاعات حساس ممکن نشد.'); }
        }
        try {
            $iv=random_bytes(12); $tag=''; $cipher=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
            if($cipher===false) return new \WP_Error('encryption_failed','ذخیره امن اطلاعات حساس ممکن نشد.');
            return 'g1:'.base64_encode($iv.$tag.$cipher);
        } catch (\Throwable $e) { return new \WP_Error('encryption_failed','ذخیره امن اطلاعات حساس ممکن نشد.'); }
    }
    public static function decrypt_sensitive(string $value): string|\WP_Error {
        if ($value==='') return '';
        [$version,$payload]=array_pad(explode(':',$value,2),2,'');
        $raw=base64_decode($payload,true); if($raw===false) return new \WP_Error('decrypt_failed','داده حساس نامعتبر است.');
        $key=hash('sha256',wp_salt('auth'),true);
        if($version==='s1' && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt')) {
            $n=SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES; $out=sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($raw,$n),'',substr($raw,0,$n),$key);
            return $out===false?new \WP_Error('decrypt_failed','رمزگشایی ناموفق بود.'):$out;
        }
        if($version==='g1') { $iv=substr($raw,0,12); $tag=substr($raw,12,16); $out=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag); return $out===false?new \WP_Error('decrypt_failed','رمزگشایی ناموفق بود.'):$out; }
        return new \WP_Error('decrypt_failed','نسخه داده حساس شناخته نشد.');
    }
    public static function fingerprint(string $value): string { return hash_hmac('sha256',self::normalize_text($value),wp_salt('secure_auth')); }
    public static function url(string $value): string { $v=trim($value); return preg_match('#^https?://#i',$v)?esc_url_raw($v):''; }
    public static function email(string $value): string { $v=sanitize_email(trim($value)); return is_email($v)?$v:''; }
    public static function rate_limit(string $bucket,string $key,int $max,int $window): bool {
        global $wpdb; $table=DB::table('rate_limits'); $hash=hash_hmac('sha256',$bucket.'|'.$key,wp_salt('auth')); $now=time();
        $row=$wpdb->get_row($wpdb->prepare("SELECT hits, reset_at FROM {$table} WHERE key_hash=%s AND bucket=%s",$hash,$bucket),\ARRAY_A);
        if(!$row || strtotime((string)$row['reset_at'])<=$now){$wpdb->replace($table,['key_hash'=>$hash,'bucket'=>$bucket,'hits'=>1,'reset_at'=>gmdate('Y-m-d H:i:s',$now+$window)],['%s','%s','%d','%s']);return true;}
        if((int)$row['hits']>=$max)return false; $wpdb->query($wpdb->prepare("UPDATE {$table} SET hits=hits+1 WHERE key_hash=%s AND bucket=%s",$hash,$bucket)); return true;
    }
}

final class Request {
    public static function ip(): string { $candidate=trim((string)($_SERVER['REMOTE_ADDR']??'')); return filter_var($candidate,FILTER_VALIDATE_IP)?$candidate:'0.0.0.0'; }
    public static function json(\WP_REST_Request $request): array { $body=$request->get_json_params(); return is_array($body)?$body:[]; }
    public static function nonce_ok(\WP_REST_Request $request,string $action='wp_rest'): bool { $nonce=$request->get_header('X-WP-Nonce') ?: $request->get_param('_wpnonce'); return is_string($nonce)&&wp_verify_nonce($nonce,$action)!==false; }
}

final class BusinessMeta {
    public const MOBILE='_syvo_bd_mobile'; public const PHONE='_syvo_bd_phone'; public const EMAIL='_syvo_bd_email'; public const WEBSITE='_syvo_bd_website'; public const INSTAGRAM='_syvo_bd_instagram'; public const WHATSAPP='_syvo_bd_whatsapp';
    public const DESCRIPTION='_syvo_bd_description'; public const HOURS='_syvo_bd_hours'; public const PROVINCE_ID='_syvo_bd_province_id'; public const COUNTY_ID='_syvo_bd_county_id'; public const CITY_ID='_syvo_bd_city_id';
    public const NEIGHBORHOOD='_syvo_bd_neighborhood'; public const STREET='_syvo_bd_street'; public const ALLEY='_syvo_bd_alley'; public const PLAQUE='_syvo_bd_plaque'; public const UNIT='_syvo_bd_unit'; public const FLOOR='_syvo_bd_floor'; public const POSTAL='_syvo_bd_postal'; public const FULL_ADDRESS='_syvo_bd_full_address';
    public const LAT='_syvo_bd_lat'; public const LNG='_syvo_bd_lng'; public const LOGO_ID='_syvo_bd_logo_id'; public const GALLERY_IDS='_syvo_bd_gallery_ids'; public const SERVICES_RAW='_syvo_bd_services_raw';
    public const DUPLICATE_SCORE='_syvo_bd_duplicate_score'; public const PLAN='_syvo_bd_plan'; public const VERIFIED='_syvo_bd_verified'; public const PROFILE_COMPLETENESS='_syvo_bd_completeness'; public const SLUG_SOURCE='_syvo_bd_slug_source';
    public const NATIONAL_ID='_syvo_bd_national_id'; public const NATIONAL_ID_FP='_syvo_bd_national_id_fp'; public const BRAND='_syvo_bd_brand'; public const LOCATION_SNAPSHOT='_syvo_bd_location_snapshot'; public const CATEGORY_SNAPSHOT='_syvo_bd_category_snapshot'; public const DRAFT='_syvo_bd_draft';
}
