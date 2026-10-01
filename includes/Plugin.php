<?php
declare(strict_types=1);
namespace Syvo\BusinessDirectory;
if (!defined('ABSPATH')) { exit; }

final class Plugin {
    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) { return; }
        self::$booted = true;
        foreach (['Database.php','Domain.php','Services.php','AI.php','REST.php','Frontend.php','Admin.php','Compatibility.php','Privacy.php'] as $file) {
            require_once SYVO_BD_DIR . 'includes/' . $file;
        }
        Installer::hooks();
        Database::hooks();
        Services::hooks();
        AIService::hooks();
        Rest::hooks();
        Frontend::hooks();
        Admin::hooks();
        Compatibility::hooks();
        Privacy::hooks();
        if ((string) get_option('syvo_bd_db_version', '') !== SYVO_BD_DB_VERSION) {
            update_option('syvo_bd_pending_install', 1, false);
        }
    }

    public static function activate(): void {
        // Deliberately minimal. WordPress must be able to activate the plugin even
        // when database/schema/remote/location setup encounters an environment-specific issue.
        // The full installer runs on the next normal WordPress request and is guarded by
        // Installer::run(), which records structured diagnostics instead of generating a fatal.
        add_option('syvo_bd_pending_install', 1, '', false);
        add_option('syvo_bd_version', SYVO_BD_VERSION, '', false);
        delete_option('syvo_bd_activation_error');
    }

    public static function deactivate(): void {
        try {
            require_once SYVO_BD_DIR . 'includes/Services.php';
            Queue::unschedule_all();
            flush_rewrite_rules(false);
        } catch (\Throwable $e) {
            update_option('syvo_bd_deactivation_error', [
                'time' => gmdate('Y-m-d H:i:s'),
                'message' => sanitize_text_field($e->getMessage()),
            ], false);
        }
    }
}


final class Installer {
    private static bool $running = false;
    public static function hooks(): void { add_action('init', [self::class, 'run'], 1); }
    public static function run(): void {
        $pending = (bool) get_option('syvo_bd_pending_install', false);
        $db = (string) get_option('syvo_bd_db_version', '');
        if (self::$running || (!$pending && $db === SYVO_BD_DB_VERSION)) return;
        self::$running = true;
        try {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $dbResult = Database::install();
            if (is_wp_error($dbResult)) { self::fail('database_install', $dbResult->get_error_message()); return; }
            Register::post_types(); Register::taxonomies(); Capabilities::install(); Seed::categories();
            $pages = CorePages::ensure();
            if (is_wp_error($pages)) { self::fail('core_pages', $pages->get_error_message()); return; }
            $defaults = [
                'tile_url'=>'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                'tile_attribution'=>'© OpenStreetMap contributors',
                'geocoder_url'=>'https://nominatim.openstreetmap.org/reverse',
                'geocoder_user_agent'=>'Syvo Business Directory/'.SYVO_BD_VERSION.' (https://syvo.ir)',
                'geocoder_api_key'=>'',
            ];
            if (!get_option('syvo_bd_map_config', false)) add_option('syvo_bd_map_config', $defaults, '', false);
            if ((string)get_option('syvo_bd_location_dataset_version','') !== SYVO_BD_LOCATION_DATASET_VERSION) {
                $loc = LocationService::import();
                if (is_wp_error($loc)) { self::fail('location_import', $loc->get_error_message()); return; }
            }
            update_option('syvo_bd_location_import_status','complete',false);
            update_option('syvo_bd_schema_version',SYVO_BD_DB_VERSION,false);
            update_option('syvo_bd_db_version',SYVO_BD_DB_VERSION,false);
            update_option('syvo_bd_version',SYVO_BD_VERSION,false);
            update_option('syvo_bd_pending_install',0,false);
            Database::hooks(); Queue::schedule_recurring(); flush_rewrite_rules(false); delete_option('syvo_bd_install_error');
        } catch (\Throwable $ex) { self::fail('installer_exception',$ex->getMessage(),$ex); }
        finally { self::$running = false; }
    }
    private static function fail(string $code,string $message,?\Throwable $e=null): void {
        update_option('syvo_bd_pending_install',1,false);
        update_option('syvo_bd_install_error',['time'=>gmdate('Y-m-d H:i:s'),'code'=>$code,'message'=>sanitize_text_field($message),'file'=>$e?basename($e->getFile()):'','line'=>$e?(int)$e->getLine():0],false);
    }
}

final class Upgrade {
    public static function maybe(): void {
        try {
            $db = (string) get_option('syvo_bd_db_version', '');
            if ($db !== SYVO_BD_DB_VERSION) {
                $result = Database::install();
                if (!is_wp_error($result)) {
                    Register::post_types();
                    Register::taxonomies();
                    Capabilities::install();
                    Seed::categories();
                    update_option('syvo_bd_db_version', SYVO_BD_DB_VERSION, false);
                    update_option('syvo_bd_version', SYVO_BD_VERSION, false);
                }
            }
            Queue::schedule_recurring();
            if ((string) get_option('syvo_bd_location_dataset_version', '') !== SYVO_BD_LOCATION_DATASET_VERSION) {
                Queue::enqueue('import_locations', 'system', 0, [], 'locations:' . SYVO_BD_LOCATION_DATASET_VERSION, 1);
            }
        } catch (\Throwable $e) {
            update_option('syvo_bd_upgrade_error', [
                'time' => gmdate('Y-m-d H:i:s'),
                'message' => sanitize_text_field($e->getMessage()),
                'file' => basename($e->getFile()),
                'line' => (int) $e->getLine(),
            ], false);
        }
    }
}

final class Activation {
    public static function run(): bool|\WP_Error {
        $db = Database::install();
        if (is_wp_error($db)) { return $db; }
        Register::post_types();
        Register::taxonomies();
        Capabilities::install();
        Seed::categories();
        CorePages::ensure();
        $defaults = [
            'tile_url' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            'tile_attribution' => '© OpenStreetMap contributors',
            'geocoder_url' => 'https://nominatim.openstreetmap.org/reverse',
            'geocoder_user_agent' => 'Syvo Business Directory/' . SYVO_BD_VERSION . ' (https://syvo.ir)',
            'geocoder_api_key' => '',
        ];
        add_option('syvo_bd_map_config', $defaults, '', false);
        foreach ([
            'syvo_bd_delete_data_on_uninstall' => false,
            'syvo_bd_location_import_status' => 'queued',
            'syvo_bd_schema_version' => SYVO_BD_DB_VERSION,
            'syvo_bd_version' => SYVO_BD_VERSION,
            'syvo_bd_location_dataset_version' => SYVO_BD_LOCATION_DATASET_VERSION,
        ] as $key => $value) {
            add_option($key, $value, '', false);
        }
        update_option('syvo_bd_version', SYVO_BD_VERSION, false);
        update_option('syvo_bd_db_version', SYVO_BD_DB_VERSION, false);
        Queue::schedule_recurring();
        Queue::enqueue('import_locations', 'system', 0, [], 'locations:' . SYVO_BD_LOCATION_DATASET_VERSION, 1);
        flush_rewrite_rules(false);
        delete_option('syvo_bd_activation_error');
        return true;
    }
}

final class Register {
    public static function post_types(): void {
        register_post_type(SYVO_BD_CPT, [
            'labels' => [
                'name' => 'کسب‌وکارها', 'singular_name' => 'کسب‌وکار', 'add_new' => 'افزودن کسب‌وکار',
                'add_new_item' => 'افزودن کسب‌وکار', 'edit_item' => 'ویرایش کسب‌وکار', 'new_item' => 'کسب‌وکار جدید',
                'view_item' => 'مشاهده کسب‌وکار', 'search_items' => 'جستجوی کسب‌وکارها', 'not_found' => 'کسب‌وکاری پیدا نشد',
                'menu_name' => 'کسب‌وکارها',
            ],
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_rest' => true,
            'rest_base' => 'businesses',
            'menu_icon' => 'dashicons-store',
            'supports' => ['title','editor','thumbnail','author','custom-fields'],
            'has_archive' => false,
            'rewrite' => ['slug' => 'business', 'with_front' => false, 'feeds' => false],
            'capability_type' => ['syvo_business','syvo_businesses'],
            'map_meta_cap' => true,
            'delete_with_user' => false,
            'show_in_nav_menus' => false,
        ]);
    }

    public static function taxonomies(): void {
        register_taxonomy(SYVO_BD_CATEGORY_TAX, [SYVO_BD_CPT], [
            'labels' => ['name'=>'دسته‌بندی‌ها','singular_name'=>'دسته‌بندی','menu_name'=>'دسته‌بندی‌ها','add_new_item'=>'افزودن دسته‌بندی'],
            'public' => true, 'hierarchical' => true, 'show_ui' => true, 'show_in_rest' => true,
            'rest_base' => 'business-categories', 'rewrite' => ['slug'=>'business-category','with_front'=>false],
        ]);
        register_taxonomy(SYVO_BD_SERVICE_TAX, [SYVO_BD_CPT], [
            'labels' => ['name'=>'خدمات','singular_name'=>'خدمت','menu_name'=>'خدمات'],
            'public' => false, 'hierarchical' => false, 'show_ui' => true, 'show_in_rest' => true,
            'rest_base' => 'business-services',
        ]);
    }
}

final class CorePages {
    private static array $definitions = [
        'register-business' => ['title'=>'ثبت کسب‌وکار','shortcode'=>'[syvo_business_register]'],
        'business-login' => ['title'=>'ورود کسب‌وکار','shortcode'=>'[syvo_business_login]'],
        'dashboard' => ['title'=>'پنل کسب‌وکار','shortcode'=>'[syvo_business_dashboard]'],
        'directory-search' => ['title'=>'جستجوی کسب‌وکارها','shortcode'=>'[syvo_business_search]'],
        'hiper' => ['title'=>'دایرکتوری کسب‌وکارها','shortcode'=>'[syvo_directory]'],
    ];

    public static function ensure(): array|\WP_Error {
        $ids = (array) get_option('syvo_bd_core_pages', []);
        foreach (self::$definitions as $key => $def) {
            if (!empty($ids[$key]) && get_post((int)$ids[$key]) && get_post_meta((int)$ids[$key], '_syvo_bd_core_page', true) === $key) { continue; }
            $slug = self::resolve_slug($key);
            $existing = wp_insert_post([
                'post_type'=>'page','post_status'=>'publish','post_title'=>$def['title'],'post_name'=>$slug,'post_content'=>$def['shortcode'],
            ], true);
            if (is_wp_error($existing)) { return $existing; }
            $id = (int)$existing;
            update_post_meta($id, '_syvo_bd_core_page', $key);
            $ids[$key] = $id;
        }
        update_option('syvo_bd_core_pages', $ids, false);
        return $ids;
    }

    public static function url(string $key): string {
        $ids = (array) get_option('syvo_bd_core_pages', []);
        $id = absint($ids[$key] ?? 0);
        return $id ? (string) get_permalink($id) : home_url('/');
    }

    private static function resolve_slug(string $requested): string {
        $candidate = $requested;
        $n = 2;
        while (($page = get_page_by_path($candidate)) !== null) {
            if (get_post_meta((int)$page->ID, '_syvo_bd_core_page', true) !== '') { return $candidate; }
            $candidate = $requested . '-' . $n++;
        }
        return $candidate;
    }
}

final class Capabilities {
    private const OWNER = ['read_syvo_business','edit_syvo_business','edit_syvo_businesses','publish_syvo_businesses','edit_published_syvo_businesses','delete_syvo_business','delete_syvo_businesses','delete_published_syvo_businesses'];
    private const ADMIN = ['read_syvo_business','read_private_syvo_businesses','edit_syvo_business','edit_syvo_businesses','edit_others_syvo_businesses','edit_published_syvo_businesses','publish_syvo_businesses','delete_syvo_business','delete_syvo_businesses','delete_others_syvo_businesses','delete_private_syvo_businesses','delete_published_syvo_businesses'];
    public static function install(): void {
        if (!get_role('syvo_business_owner')) { add_role('syvo_business_owner','مالک کسب‌وکار',['read'=>true]); }
        $owner = get_role('syvo_business_owner');
        if ($owner) foreach (self::OWNER as $cap) $owner->add_cap($cap);
        if ($owner) $owner->add_cap('upload_files');
        $admin = get_role('administrator');
        if ($admin) foreach (array_unique(array_merge(self::OWNER,self::ADMIN)) as $cap) $admin->add_cap($cap);
    }
}

final class Seed {
    public static function categories(): void {
        $tree = require SYVO_BD_DIR . 'data/categories.php';
        $upsert = function(array $node, int $parent = 0) use (&$upsert): void {
            $name = sanitize_text_field((string)($node['name'] ?? ''));
            $slug = sanitize_title((string)($node['slug'] ?? ''));
            if ($name === '' || $slug === '') return;
            $term = term_exists($slug, SYVO_BD_CATEGORY_TAX) ?: term_exists($name, SYVO_BD_CATEGORY_TAX);
            if (!$term) $term = wp_insert_term($name, SYVO_BD_CATEGORY_TAX, ['slug'=>$slug,'parent'=>$parent]);
            if (is_wp_error($term)) return;
            $term_id = (int)(is_array($term) ? $term['term_id'] : $term);
            $current_parent = (int)get_term($term_id,SYVO_BD_CATEGORY_TAX)->parent;
            if ($current_parent !== $parent) wp_update_term($term_id,SYVO_BD_CATEGORY_TAX,['parent'=>$parent]);
            update_term_meta($term_id,'_syvo_bd_canonical_slug',$slug);
            foreach ((array)($node['children'] ?? []) as $child) $upsert($child,$term_id);
        };
        foreach ($tree as $node) $upsert($node);
    }
}
