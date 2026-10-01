<?php
declare(strict_types=1);
namespace Syvo\BusinessDirectory;

if (!defined('ABSPATH')) { exit; }

final class DB {
    public static function table(string $suffix): string {
        global $wpdb;
        return $wpdb->prefix . 'syvo_' . $suffix;
    }

    public static function tables(): array {
        return [
            'locations' => self::table('locations'),
            'directory_pages' => self::table('directory_pages'),
            'jobs' => self::table('jobs'),
            'business_index' => self::table('business_index'),
            'ai_logs' => self::table('ai_logs'),
            'rate_limits' => self::table('rate_limits'),
        ];
    }
}

final class Database {
    public static function hooks(): void {
        add_action('init', [Register::class, 'post_types'], 5);
        add_action('init', [Register::class, 'taxonomies'], 6);
        add_filter('cron_schedules', [self::class, 'cron_schedules']);
    }

    public static function cron_schedules(array $schedules): array {
        if (!isset($schedules['syvo_every_minute'])) {
            $schedules['syvo_every_minute'] = [
                'interval' => MINUTE_IN_SECONDS,
                'display' => 'Syvo every minute',
            ];
        }
        return $schedules;
    }

    public static function install(): bool|\WP_Error {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $t = DB::tables();

        $sql = [];
        $sql[] = "CREATE TABLE {$t['locations']} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            source_id VARCHAR(128) NOT NULL,
            location_type VARCHAR(16) NOT NULL,
            parent_id BIGINT(20) UNSIGNED NULL,
            name_fa VARCHAR(190) NOT NULL,
            name_en VARCHAR(190) NOT NULL DEFAULT '',
            normalized_name VARCHAR(190) NOT NULL,
            slug VARCHAR(190) NOT NULL,
            code VARCHAR(128) NOT NULL DEFAULT '',
            latitude DECIMAL(10,7) NULL,
            longitude DECIMAL(10,7) NULL,
            dataset_version VARCHAR(128) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY source_unique (source_id, location_type),
            UNIQUE KEY slug_unique (location_type, parent_id, slug),
            KEY parent_idx (parent_id),
            KEY normalized_idx (normalized_name),
            KEY type_slug_idx (location_type, slug)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$t['directory_pages']} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            fingerprint CHAR(64) NOT NULL,
            page_type VARCHAR(20) NOT NULL DEFAULT 'city',
            category_term_id BIGINT(20) UNSIGNED NULL,
            location_id BIGINT(20) UNSIGNED NULL,
            location_type VARCHAR(16) NOT NULL DEFAULT 'city',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            url VARCHAR(2048) NOT NULL,
            h1 VARCHAR(255) NOT NULL DEFAULT '',
            seo_title VARCHAR(255) NOT NULL DEFAULT '',
            meta_description TEXT NULL,
            canonical_url VARCHAR(2048) NOT NULL DEFAULT '',
            seo_content LONGTEXT NULL,
            seo_content_hash CHAR(64) NOT NULL DEFAULT '',
            dirty TINYINT(1) NOT NULL DEFAULT 1,
            business_count BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            last_generated_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY fingerprint_unique (fingerprint),
            KEY category_location (category_term_id, location_id, location_type, status),
            KEY dirty_idx (dirty, status),
            KEY url_idx (url(191))
        ) {$charset};";

        $sql[] = "CREATE TABLE {$t['jobs']} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            job_type VARCHAR(64) NOT NULL,
            unique_hash CHAR(64) NOT NULL,
            entity_type VARCHAR(32) NOT NULL DEFAULT '',
            entity_id BIGINT(20) UNSIGNED NULL,
            payload LONGTEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
            max_attempts TINYINT(3) UNSIGNED NOT NULL DEFAULT 4,
            run_after DATETIME NOT NULL,
            locked_at DATETIME NULL,
            last_error_code VARCHAR(64) NOT NULL DEFAULT '',
            last_error_message TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_job (job_type, unique_hash),
            KEY queue_idx (status, run_after),
            KEY entity_idx (entity_type, entity_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$t['business_index']} (
            business_id BIGINT(20) UNSIGNED NOT NULL,
            owner_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            province_id BIGINT(20) UNSIGNED NULL,
            county_id BIGINT(20) UNSIGNED NULL,
            city_id BIGINT(20) UNSIGNED NULL,
            lat DECIMAL(10,7) NULL,
            lng DECIMAL(10,7) NULL,
            normalized_name VARCHAR(190) NOT NULL DEFAULT '',
            search_text LONGTEXT NOT NULL,
            completeness_score DECIMAL(5,2) NOT NULL DEFAULT 0,
            verified TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'publish',
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (business_id),
            KEY geo_idx (city_id, county_id, province_id, status),
            KEY owner_idx (owner_id, status),
            KEY completeness_idx (completeness_score, status),
            KEY status_idx (status),
            FULLTEXT KEY search_fulltext (search_text)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$t['ai_logs']} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id CHAR(36) NOT NULL,
            provider VARCHAR(64) NOT NULL DEFAULT '',
            job_type VARCHAR(64) NOT NULL DEFAULT '',
            entity_id BIGINT(20) UNSIGNED NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'success',
            error_code VARCHAR(64) NOT NULL DEFAULT '',
            safe_message TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY(id),
            KEY entity_idx (job_type, entity_id, created_at),
            KEY request_idx (request_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$t['rate_limits']} (
            key_hash CHAR(64) NOT NULL,
            bucket VARCHAR(32) NOT NULL,
            hits INT(10) UNSIGNED NOT NULL DEFAULT 0,
            reset_at DATETIME NOT NULL,
            PRIMARY KEY(key_hash, bucket),
            KEY reset_idx (reset_at)
        ) {$charset};";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        update_option('syvo_bd_schema_version', SYVO_BD_DB_VERSION, false);
        return true;
    }
}
