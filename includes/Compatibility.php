<?php
declare(strict_types=1);
namespace Syvo\BusinessDirectory;
if (!defined('ABSPATH')) { exit; }

final class Compatibility {
    public static function hooks(): void {
        add_filter('rank_math/frontend/title',[self::class,'rankTitle']);
        add_filter('rank_math/frontend/description',[self::class,'rankDescription']);
        add_filter('rank_math/frontend/canonical',[self::class,'rankCanonical']);
        add_action('wp_enqueue_scripts',[self::class,'assets'],20);
    }
    public static function assets(): void { if(RewriteService::current_route()||is_singular(SYVO_BD_CPT)||is_page(array_values((array)get_option('syvo_bd_core_pages',[]))))FrontendAssets::enqueue(); }
    public static function rankTitle($title){$ctx=SeoService::context();if(!$ctx)return$title;if(isset($ctx['profile']))return$ctx['profile']['name'].' | معرفی کسب‌وکار در سایوو';return$ctx['category']->name.' در '.$ctx['location']['name_fa'].' | معرفی کسب‌وکارها';}
    public static function rankDescription($description){$ctx=SeoService::context();if(!$ctx)return$description;if(isset($ctx['profile']))returnwp_trim_words(wp_strip_all_tags($ctx['profile']['description']),28,'...');$pageId=PageService::findPageId((int)$ctx['category']->term_id,$ctx['type'],(int)$ctx['location']['id']);$page=$pageId?PageService::get($pageId):null;return$page?(string)$page['meta_description']:$description;}
    public static function rankCanonical($canonical){$ctx=SeoService::context();if(!$ctx)return$canonical;if(isset($ctx['profile']))return get_permalink($ctx['profile']['id']);return home_url($ctx['url']);}
    public static function sitemapProviders(array $providers): array { $providers['syvo_bd']=new DirectorySitemapProvider();return$providers; }
}

