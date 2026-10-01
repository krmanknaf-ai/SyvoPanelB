<?php
declare(strict_types=1);
namespace Syvo\BusinessDirectory;
if (!defined('ABSPATH')) { exit; }

interface AIProviderInterface {
    public function isConfigured(): bool;
    public function generate(string $prompt,array $options=[]): string|\WP_Error;
    public function vision(string $dataUri,string $prompt,array $options=[]): string|\WP_Error;
}

final class WordPressAIProvider implements AIProviderInterface {
    public function isConfigured(): bool { return function_exists('wp_ai_client_prompt'); }
    public function generate(string $prompt,array $options=[]): string|\WP_Error {
        if(!$this->isConfigured())return new \WP_Error('ai_not_configured','WordPress AI Client یا provider فعال نیست.');
        try {
            $builder=wp_ai_client_prompt()->with_text($prompt);
            $result=$builder->generate_text();
            return is_wp_error($result)?$result:(string)$result;
        } catch(\Throwable $e){ return new \WP_Error('ai_request_failed','سرویس AI در دسترس نبود.'); }
    }
    public function vision(string $dataUri,string $prompt,array $options=[]): string|\WP_Error {
        if(!$this->isConfigured())return new \WP_Error('ai_not_configured','WordPress AI Client یا provider فعال نیست.');
        try {
            $builder=wp_ai_client_prompt()->with_text($prompt);
            if(method_exists($builder,'with_file'))$builder=$builder->with_file($dataUri);
            $result=$builder->generate_text();
            return is_wp_error($result)?$result:(string)$result;
        } catch(\Throwable $e){ return new \WP_Error('ai_vision_failed','تحلیل تصویر توسط AI انجام نشد.'); }
    }
}

final class AIService {
    public static function hooks(): void {
        add_action('add_attachment',static function(int $attachmentId): void { Queue::enqueue('generate_image_alt','attachment',$attachmentId,['attachment_id'=>$attachmentId],'alt:'.$attachmentId,3,10); },20);
    }
    private static function log(string $job,int $entity,string $status,string $code='',string $message=''): void { global $wpdb; $wpdb->insert(DB::table('ai_logs'),['request_id'=>wp_generate_uuid4(),'provider'=>'WordPress AI Client','job_type'=>$job,'entity_id'=>$entity,'status'=>$status,'error_code'=>sanitize_key($code),'safe_message'=>mb_substr(sanitize_text_field($message),0,500),'created_at'=>current_time('mysql',true)],['%s','%s','%s','%d','%s','%s','%s']); }
    public static function provider(): AIProviderInterface { static $provider; if(!$provider)$provider=new WordPressAIProvider();return$provider; }
    public static function status(): array { $p=self::provider();return ['provider'=>'WordPress AI Client','configured'=>$p->isConfigured()]; }
    public static function generatePageSeo(array $ctx,array $businesses): string|\WP_Error {
        $p=self::provider();if(!$p->isConfigured())return new \WP_Error('ai_not_configured','AI provider تنظیم نشده است.');
        $businessData=[];foreach(array_slice($businesses,0,10) as $b){$businessData[]=['name'=>$b['name'],'services'=>$b['services'],'city'=>$b['city']['name_fa']??'','province'=>$b['province']['name_fa']??'','description'=>wp_strip_all_tags((string)$b['description'])];}
        $prompt="تو نویسنده محتوای محلی برای دایرکتوری Syvo هستی. فقط بر اساس داده زیر یک متن فارسی طبیعی 180 تا 300 کلمه‌ای برای انتهای صفحه بنویس. هیچ ادعای جدید، رتبه، جایزه، مشتری، آمار یا ویژگی جغرافیایی نساز. نام شهر و دسته را دقیق نگه دار. از keyword stuffing خودداری کن. فقط HTML امن با پاراگراف و در صورت نیاز یک H2 کوتاه بده.\nدسته: ".$ctx['category']->name."\nمکان: ".$ctx['location']['name_fa']."\nکسب‌وکارهای واقعی: ".wp_json_encode($businessData,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        return self::provider()->generate($prompt,['job'=>'generate_page_seo']);
    }
    public static function classifyBusiness(int $businessId): true|\WP_Error {
        $post=get_post($businessId);if(!$post)return true;$text=Security::normalize_text($post->post_title.' '.$post->post_content.' '.(string)get_post_meta($businessId,BusinessMeta::SERVICES_RAW,true));
        $existing=CategoryService::categoryIdsFromInput([],preg_split('/[,،\n]+/u',$text)?:[]);if($existing){set_post_terms_checked($businessId,SYVO_BD_CATEGORY_TAX,$existing);PageService::markByBusiness($businessId);return true;}
        $p=self::provider();if(!$p->isConfigured())return true;
        $cats=get_terms(['taxonomy'=>SYVO_BD_CATEGORY_TAX,'hide_empty'=>false,'fields'=>'id=>name']);if(is_wp_error($cats))return true;
        $prompt="از فهرست دسته‌های زیر حداکثر سه دسته مرتبط انتخاب کن. فقط نام دقیق دسته‌ها را به صورت JSON array برگردان. دسته جدید نساز. متن کسب‌وکار:\n".$text."\nدسته‌ها:\n".wp_json_encode($cats,JSON_UNESCAPED_UNICODE);
        $result=$p->generate($prompt,['job'=>'classify_business']);if(is_wp_error($result))return true;$decoded=json_decode(trim((string)$result),true);if(!is_array($decoded))return true;$ids=CategoryService::categoryIdsFromInput([],array_slice($decoded,0,3));
        if(!$ids){
            $proposal=$p->generate('برای متن زیر فقط در صورتی که هیچ دسته مناسب از فهرست موجود وجود ندارد، یک دسته جدید پیشنهاد کن. JSON دقیق با name,parent,slug,confidence بده. confidence بین 0 و 1. دسته والد باید یکی از نام‌های زیر باشد: '.wp_json_encode($cats,JSON_UNESCAPED_UNICODE).'\nمتن: '.$text,['job'=>'propose_category']);
            $pro=json_decode(trim((string)$proposal),true);
            if(is_array($pro)&&isset($pro['name'],$pro['parent'],$pro['slug'],$pro['confidence'])&&(float)$pro['confidence']>=0.88){
                $name=sanitize_text_field((string)$pro['name']);$parentName=sanitize_text_field((string)$pro['parent']);$slug=SlugService::category($name,sanitize_title((string)$pro['slug']));$existing=CategoryService::ensureFromPhrase($name);
                if(is_wp_error($existing)){
                    $parent=CategoryService::ensureFromPhrase($parentName); if(!is_wp_error($parent)){ $term=wp_insert_term($name,SYVO_BD_CATEGORY_TAX,['slug'=>$slug,'parent'=>(int)$parent]); if(!is_wp_error($term))$ids[]=(int)$term['term_id']; }
                } else {$ids[]=(int)$existing;}
            }
        }
        if($ids){set_post_terms_checked($businessId,SYVO_BD_CATEGORY_TAX,$ids);PageService::markByBusiness($businessId);}return true;
    }
    public static function generateImageAlt(int $attachmentId,int $businessId=0): string|\WP_Error {
        $file=get_attached_file($attachmentId);if(!$file||!is_readable($file))return new \WP_Error('image_file_unavailable','فایل تصویر قابل خواندن نیست.');$mime=get_post_mime_type($attachmentId);$allowed=['image/jpeg','image/png','image/webp'];if(!in_array($mime,$allowed,true))return self::fallbackAlt($attachmentId,$businessId);$contents=file_get_contents($file);if($contents===false)return self::fallbackAlt($attachmentId,$businessId);$dataUri='data:'.$mime.';base64,'.base64_encode($contents);$business=$businessId?BusinessService::publicData($businessId):[];$context='';if($business){$context='نام کسب‌وکار: '.$business['name'].'؛ شهر: '.($business['city']['name_fa']??'').'؛ دسته: '.implode('،',array_map(static fn($c)=>$c['name'],$business['categories']));}$p=self::provider();if($p->isConfigured()){ $res=$p->vision($dataUri,"تصویر را فقط برای دسترسی‌پذیری در یک جمله کوتاه و دقیق توصیف کن. هیچ ویژگی‌ای که واقعاً در تصویر نمی‌بینی نساز. حداکثر 90 نویسه فارسی. $context");if(!is_wp_error($res)&&trim((string)$res)!=='')return sanitize_text_field(trim((string)$res)); }return self::fallbackAlt($attachmentId,$businessId);
    }
    private static function fallbackAlt(int $attachmentId,int $businessId): string { $title=get_the_title($attachmentId);if($businessId)$title=get_the_title($businessId).' - '.$title;return mb_substr(sanitize_text_field($title),0,125); }
}
