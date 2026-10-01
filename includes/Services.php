<?php
declare(strict_types=1);
namespace Syvo\BusinessDirectory;
if (!defined('ABSPATH')) { exit; }

final class Services {
    public static function hooks(): void {
        add_action('init',[RewriteService::class,'rules'],20);
        add_filter('query_vars',[RewriteService::class,'query_vars']);
        add_filter('template_include',[RewriteService::class,'template_include'],99);
        add_filter('pre_handle_404',[RewriteService::class,'preHandle404'],10,2);
        add_action('save_post_'.SYVO_BD_CPT,[BusinessService::class,'on_save'],20,3);
        add_action('before_delete_post',[BusinessService::class,'on_delete'],20);
        add_action('wp_insert_post',[BusinessService::class,'after_insert'],20,3);
        add_action('syvo_bd_process_queue',[Queue::class,'process'],10);
        add_action('syvo_bd_hourly_maintenance',[MaintenanceService::class,'run']);
        add_filter('pre_get_document_title',[SeoService::class,'title'],20);
        add_action('wp_head',[SeoService::class,'head'],5);
        add_filter('wp_robots',[SeoService::class,'robots'],20);
        add_action('template_redirect',[SeoService::class,'maybe_redirect_legacy'],1);
        add_action('wp_footer',[FrontendAssets::class,'footer_config'],99);
    }
}

final class RewriteService {
    public static function query_vars(array $vars): array {
        foreach(['syvo_bd_route','syvo_bd_category','syvo_bd_location','syvo_bd_parent_category'] as $v){if(!in_array($v,$vars,true))$vars[]=$v;}
        return $vars;
    }
    public static function rules(): void {
        add_rewrite_rule('^hiper/?$','index.php?syvo_bd_route=directory','top');
        add_rewrite_rule('^hiper/([^/]+)/([^/]+)/?$','index.php?syvo_bd_route=landing&syvo_bd_category=$matches[1]&syvo_bd_location=$matches[2]','top');
        add_rewrite_rule('^hiper/([^/]+)/([^/]+)/([^/]+)/?$','index.php?syvo_bd_route=subcategory&syvo_bd_parent_category=$matches[1]&syvo_bd_category=$matches[2]&syvo_bd_location=$matches[3]','top');
        add_rewrite_rule('^hiper/([^/]+)/county/([^/]+)/?$','index.php?syvo_bd_route=county&syvo_bd_category=$matches[1]&syvo_bd_location=$matches[2]','top');
        add_rewrite_rule('^hiper/([^/]+)/province/([^/]+)/?$','index.php?syvo_bd_route=province&syvo_bd_category=$matches[1]&syvo_bd_location=$matches[2]','top');
    }
    public static function preHandle404($preempt, $wp_query){ if(get_query_var('syvo_bd_route')){ status_header(200); return true; } return $preempt; }
    public static function template_include(string $template): string {
        if(get_query_var('syvo_bd_route')) return SYVO_BD_DIR.'templates/blank.php';
        if(is_singular(SYVO_BD_CPT)) return SYVO_BD_DIR.'templates/blank.php';
        return $template;
    }
    public static function current_route(): array {
        $route=(string)get_query_var('syvo_bd_route');
        if($route==='') return [];
        return ['route'=>$route,'category'=>sanitize_title((string)get_query_var('syvo_bd_category')),'parent_category'=>sanitize_title((string)get_query_var('syvo_bd_parent_category')),'location'=>sanitize_title((string)get_query_var('syvo_bd_location'))];
    }
}

final class SlugService {
    private const CATEGORY_MAP = [
        'طراحی سایت'=>'web-design','طراحی وب'=>'web-design','وب‌سایت'=>'web-design','وب سایت'=>'web-design','سئو'=>'seo','seo'=>'seo','دیجیتال مارکتینگ'=>'digital-marketing','مارکتینگ'=>'marketing-advertising','معماری'=>'architecture','طراحی معماری'=>'architecture-design','طراحی داخلی'=>'interior-design','فروشگاه اینترنتی'=>'ecommerce-websites','طراحی فروشگاه اینترنتی'=>'ecommerce-websites','توسعه نرم افزار'=>'software-development','خدمات نرم افزار'=>'software-services','برنامه نویسی'=>'software-development','پزشکی'=>'medical','دندانپزشکی'=>'dentistry','زیبایی'=>'beauty-services','رستوران'=>'restaurant','کافه'=>'cafe','فست فود'=>'fast-food','املاک'=>'real-estate','حسابداری'=>'accounting','وکالت'=>'lawyer','آموزش'=>'education','عکاسی'=>'photography','چاپ'=>'printing','خدمات منزل'=>'home-services','خودرو'=>'auto','تعمیرگاه'=>'auto-repair','ورزش'=>'sports','گردشگری'=>'tourism-transportation','حمل و نقل'=>'transportation',
    ];
    private const PROVINCE_MAP = ['کرمان'=>'kerman','تهران'=>'tehran','خراسان رضوی'=>'razavi-khorasan','اصفهان'=>'isfahan','فارس'=>'fars','آذربایجان شرقی'=>'east-azerbaijan','آذربایجان غربی'=>'west-azerbaijan','البرز'=>'alborz','قم'=>'qom','گیلان'=>'gilan','مازندران'=>'mazandaran','یزد'=>'yazd','هرمزگان'=>'hormozgan','مرکزی'=>'markazi','خوزستان'=>'khuzestan','بوشهر'=>'bushehr','همدان'=>'hamadan','کرمانشاه'=>'kermanshah','لرستان'=>'lorestan','زنجان'=>'zanjan','قزوین'=>'qazvin','کردستان'=>'kurdistan','گلستان'=>'golestan','اردبیل'=>'ardabil','سیستان و بلوچستان'=>'sistan-and-baluchestan','چهارمحال و بختیاری'=>'chaharmahal-and-bakhtiari','کهگیلویه و بویراحمد'=>'kohgiluyeh-and-boyerahmad','ایلام'=>'ilam','سمنان'=>'semnan','خراسان شمالی'=>'north-khorasan','خراسان جنوبی'=>'south-khorasan','بیرجند'=>'birjand'];
    public static function category(string $name,string $fallback=''): string { $n=Security::normalize_text($name); if(isset(self::CATEGORY_MAP[$n]))return self::CATEGORY_MAP[$n]; if($fallback!=='')return sanitize_title($fallback); return 'category-'.substr(hash('sha256',$n),0,12); }
    public static function location(array $row): string {
        $en=trim((string)($row['name_en']??'')); if($en!=='' && preg_match('/^[A-Za-z0-9][A-Za-z0-9 -]*$/',$en)) return sanitize_title($en);
        $name=Security::normalize_text((string)($row['name_fa']??'')); if(isset(self::PROVINCE_MAP[$name])) return self::PROVINCE_MAP[$name];
        $code=(string)($row['code']??$row['official_code']??$row['source_id']??$row['id']??'');
        $codeSlug=sanitize_title($code);
        return $codeSlug!=='' ? 'location-'.$codeSlug : 'location-'.(int)($row['id']??0);
    }
    public static function taxonomy(string $name,string $preferred=''): string { $slug=self::category($name,$preferred); return sanitize_title($slug); }
}

final class LocationService {
    public static function import(): true|\WP_Error {
        $manifest=require SYVO_BD_DIR.'data/location-manifest.php';
        $response=wp_remote_get($manifest['source_file'],['timeout'=>30,'redirection'=>2,'headers'=>['Accept'=>'application/json','User-Agent'=>'Syvo Business Directory/'.SYVO_BD_VERSION.' (https://syvo.ir)']]);
        if(is_wp_error($response)) return $response;
        if((int)wp_remote_retrieve_response_code($response)!==200) return new \WP_Error('location_source_http','Location dataset source returned HTTP '.(int)wp_remote_retrieve_response_code($response));
        $data=json_decode(wp_remote_retrieve_body($response),true);
        if(!is_array($data)||count($data)<1)return new \WP_Error('location_source_invalid','Location dataset response is invalid.');
        global $wpdb; $table=DB::table('locations'); $now=current_time('mysql',true);
        $wpdb->query('START TRANSACTION');
        try {
            $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE dataset_version <> %s",$manifest['version']));
            $provinceIds=[]; $countyIds=[];
            foreach($data as $province){
                $provinceRow=self::upsertRow((array)$province,'province',null,$manifest['version'],$now); $provinceIds[(string)$province['id']]=$provinceRow;
                foreach((array)($province['cities']??[]) as $city){
                    $countyName=(string)($city['county']??''); $countyCode=(string)($city['county_code']??''); $countyKey=(string)$province['id'].':'.$countyCode;
                    if(!isset($countyIds[$countyKey])) $countyIds[$countyKey]=self::upsertRow(['id'=>$countyKey,'name'=>$countyName,'english_name'=>'','official_code'=>$countyCode],'county',$provinceRow,$manifest['version'],$now);
                    self::upsertRow($city,'city',$countyIds[$countyKey],$manifest['version'],$now);
                }
            }
            $wpdb->query('COMMIT'); update_option('syvo_bd_location_import_status','complete',false); update_option('syvo_bd_location_dataset_version',$manifest['version'],false); return true;
        } catch(\Throwable $e){ $wpdb->query('ROLLBACK'); update_option('syvo_bd_location_import_status','failed',false); return new \WP_Error('location_import_failed','Location dataset import failed: '.$e->getMessage()); }
    }
    private static function upsertRow(array $row,string $type,?int $parent,string $version,string $now): int {
        global $wpdb; $table=DB::table('locations');
        $sourceId=(string)($row['uid']??$row['official_code']??$row['id']??''); if($type==='county')$sourceId='county:'.$sourceId; if($sourceId==='')$sourceId=$type.':'.md5(wp_json_encode($row));
        $name=(string)($row['name']??$row['province']??''); $en=(string)($row['english_name']??''); $slug=SlugService::location(['name_fa'=>$name,'name_en'=>$en,'slug'=>'']); $code=(string)($row['official_code']??$row['county_code']??''); $norm=Security::normalize_text($name);
        $lat=isset($row['latitude'])&&is_numeric($row['latitude'])?(float)$row['latitude']:null; $lng=isset($row['longitude'])&&is_numeric($row['longitude'])?(float)$row['longitude']:null;
        $existing=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE source_id=%s AND location_type=%s",$sourceId,$type));
        $data=['source_id'=>$sourceId,'location_type'=>$type,'parent_id'=>$parent,'name_fa'=>$name,'name_en'=>$en,'normalized_name'=>$norm,'slug'=>$slug,'code'=>$code,'latitude'=>$lat,'longitude'=>$lng,'dataset_version'=>$version,'updated_at'=>$now];
        if($existing){$wpdb->update($table,$data,['id'=>(int)$existing],['%s','%s','%d','%s','%s','%s','%s','%s','%s','%f','%f','%s','%s'],['%d']);return (int)$existing;}
        $data['created_at']=$now; $wpdb->insert($table,$data,['%s','%s','%d','%s','%s','%s','%s','%s','%s','%f','%f','%s','%s','%s']); return (int)$wpdb->insert_id;
    }
    public static function children(string $type,int $parentId): array { global $wpdb;$table=DB::table('locations');return $wpdb->get_results($wpdb->prepare("SELECT id,name_fa,name_en,slug,latitude,longitude FROM {$table} WHERE location_type=%s AND parent_id=%d ORDER BY name_fa ASC",$type,$parentId),\ARRAY_A) ?: []; }
    public static function findBySlug(string $slug,string $type=''): ?array { global $wpdb;$table=DB::table('locations'); $sql="SELECT * FROM {$table} WHERE slug=%s";$params=[$slug];if($type!==''){ $sql.=" AND location_type=%s";$params[]=$type; }$row=$wpdb->get_row($wpdb->prepare($sql,...$params),\ARRAY_A);return $row?:null; }
    public static function find(int $id): ?array { global $wpdb;$r=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.DB::table('locations').' WHERE id=%d',$id),\ARRAY_A);return $r?:null; }
    public static function validateChain(int $province,int $county,int $city): bool { $p=self::find($province);$c=self::find($county);$x=self::find($city);return $p&&$c&&$x&&$p['location_type']==='province'&&$c['location_type']==='county'&&$x['location_type']==='city'&&(int)$c['parent_id']===$province&&(int)$x['parent_id']===$county; }
    public static function reverseGeocode(float $lat,float $lng): array|\WP_Error {
        if($lat<-90||$lat>90||$lng<-180||$lng>180)return new \WP_Error('invalid_coordinates','مختصات مکانی معتبر نیست.');
        $key='syvo_bd_geocode_'.md5(round($lat,5).','.round($lng,5));$cached=get_transient($key);if(is_array($cached))return $cached;
        $config=(array)get_option('syvo_bd_map_config',[]);$url=(string)($config['geocoder_url']??'');$ua=(string)($config['geocoder_user_agent']??'Syvo Business Directory');
        $last=(float)get_transient('syvo_bd_geocoder_last_request'); if($last>0 && microtime(true)-$last<1){usleep((int)((1-(microtime(true)-$last))*1000000));}
        $response=wp_remote_get(add_query_arg(['format'=>'jsonv2','lat'=>$lat,'lon'=>$lng,'addressdetails'=>1],$url),['timeout'=>12,'headers'=>['Accept'=>'application/json','User-Agent'=>$ua]]); set_transient('syvo_bd_geocoder_last_request',microtime(true),5);
        if(is_wp_error($response))return $response; if((int)wp_remote_retrieve_response_code($response)!==200)return new \WP_Error('geocoder_http','Reverse geocoder returned HTTP '.(int)wp_remote_retrieve_response_code($response));
        $json=json_decode(wp_remote_retrieve_body($response),true);if(!is_array($json))return new \WP_Error('geocoder_invalid','Reverse geocoder returned invalid data.');$a=(array)($json['address']??[]);
        $out=['province'=>(string)($a['state']??''),'county'=>(string)($a['county']??''),'city'=>(string)($a['city']??$a['town']??$a['village']??''),'neighborhood'=>(string)($a['neighbourhood']??$a['suburb']??''),'street'=>(string)($a['road']??''),'address'=>(string)($json['display_name']??'')]; set_transient($key,$out,DAY_IN_SECONDS);return $out;
    }
}

final class CategoryService {
    public static function allTree(): array { $terms=get_terms(['taxonomy'=>SYVO_BD_CATEGORY_TAX,'hide_empty'=>false,'parent'=>0,'orderby'=>'name','order'=>'ASC']); if(is_wp_error($terms))return[]; return self::tree($terms); }
    private static function tree(array $terms): array { $out=[];foreach($terms as $term){$out[]=['id'=>(int)$term->term_id,'name'=>$term->name,'slug'=>$term->slug,'children'=>self::tree(get_terms(['taxonomy'=>SYVO_BD_CATEGORY_TAX,'hide_empty'=>false,'parent'=>(int)$term->term_id,'orderby'=>'name','order'=>'ASC']))];}return $out; }
    public static function ensureFromPhrase(string $phrase): int|\WP_Error {
        $norm=Security::normalize_text($phrase); if($norm==='')return new \WP_Error('empty_category','دسته‌بندی خالی است.');
        $all=get_terms(['taxonomy'=>SYVO_BD_CATEGORY_TAX,'hide_empty'=>false]); if(is_wp_error($all))return $all;
        foreach($all as $term){$termNorm=Security::normalize_text($term->name);if($termNorm===$norm || $term->slug===sanitize_title($norm))return (int)$term->term_id;$percent=0;similar_text($termNorm,$norm,$percent);if($percent>=90)return(int)$term->term_id;}
        $mapped=SlugService::category($phrase); foreach($all as $term)if($term->slug===$mapped)return(int)$term->term_id;
        foreach(['طراحی سایت'=>'web-design','سئو'=>'seo','seo'=>'seo','معماری'=>'architecture','رستوران'=>'restaurant','املاک'=>'real-estate'] as $needle=>$slug){if(str_contains($norm,$needle))foreach($all as $term)if($term->slug===$slug)return(int)$term->term_id;}
        return new \WP_Error('category_not_found','دسته مناسب موجود نبود. ایجاد خودکار این دسته فقط از طریق طبقه‌بندی تأییدشده یا AI مجاز است.');
    }
    public static function servicesFromText(array $services): array { $ids=[];foreach($services as $service){$name=sanitize_text_field((string)$service);if($name==='')continue;$term=term_exists($name,SYVO_BD_SERVICE_TAX);if(!$term)$term=wp_insert_term($name,SYVO_BD_SERVICE_TAX,['slug'=>sanitize_title($name)]);if(!is_wp_error($term))$ids[]=(int)(is_array($term)?$term['term_id']:$term);}return array_values(array_unique($ids)); }
    public static function categoryIdsFromInput(array $ids,array $phrases=[]): array { $out=[]; foreach($ids as $id){$id=absint($id);if($id&&get_term($id,SYVO_BD_CATEGORY_TAX))$out[]=$id;} foreach($phrases as $phrase){$id=self::ensureFromPhrase((string)$phrase);if(!is_wp_error($id))$out[]=(int)$id;} return array_values(array_unique($out)); }
}

final class BusinessService {
    public static function create(int $owner,array $input): int|\WP_Error {
        if(!$owner || !get_user_by('id',$owner))return new \WP_Error('invalid_owner','مالک نامعتبر است.');
        if(self::duplicateScore($input,$owner)>=90)return new \WP_Error('duplicate_business','یک کسب‌وکار بسیار مشابه قبلاً ثبت شده است.');
        $title=sanitize_text_field((string)($input['name']??''));if($title==='')return new \WP_Error('missing_name','نام کسب‌وکار الزامی است.');
        $postId=wp_insert_post(['post_type'=>SYVO_BD_CPT,'post_status'=>'publish','post_title'=>$title,'post_content'=>wp_kses_post((string)($input['description']??'')),'post_author'=>$owner],true);if(is_wp_error($postId))return $postId;$id=(int)$postId;
        $result=self::saveMeta($id,$input,$owner);if(is_wp_error($result)){wp_trash_post($id);return $result;}
        update_post_meta($id,BusinessMeta::PLAN,'free'); update_post_meta($id,BusinessMeta::VERIFIED,0);
        return $id;
    }
    public static function update(int $id,int $owner,array $input): true|\WP_Error { if(!self::canEdit($id,$owner))return new \WP_Error('forbidden','دسترسی ویرایش این کسب‌وکار را ندارید.'); $post=get_post($id);if(!$post)return new \WP_Error('not_found','کسب‌وکار یافت نشد.');$oldTitle=$post->post_title;$newTitle=sanitize_text_field((string)($input['name']??$oldTitle));$content=array_key_exists('description',$input)?wp_kses_post((string)$input['description']):$post->post_content;wp_update_post(['ID'=>$id,'post_title'=>$newTitle,'post_content'=>$content]);$result=self::saveMeta($id,$input,$owner);return $result; }
    public static function canEdit(int $id,int $userId): bool { $post=get_post($id);return $post&&$post->post_type===SYVO_BD_CPT && ((int)$post->post_author===$userId||current_user_can('edit_others_syvo_businesses')); }
    private static function saveMeta(int $id,array $input,int $owner): true|\WP_Error {
        $oldLocation=[(int)get_post_meta($id,BusinessMeta::PROVINCE_ID,true),(int)get_post_meta($id,BusinessMeta::COUNTY_ID,true),(int)get_post_meta($id,BusinessMeta::CITY_ID,true)];
        $province=absint($input['province_id']??0);$county=absint($input['county_id']??0);$city=absint($input['city_id']??0);if(!$province||!$county||!$city||!LocationService::validateChain($province,$county,$city))return new \WP_Error('invalid_location','استان، شهرستان و شهر انتخاب‌شده با هم سازگار نیستند.');
        $mobile=Security::normalize_mobile((string)($input['business_mobile']??$input['mobile']??'')); if(is_wp_error($mobile))return $mobile;
        $lat=array_key_exists('lat',$input)&&$input['lat']!==''?(float)$input['lat']:null;$lng=array_key_exists('lng',$input)&&$input['lng']!==''?(float)$input['lng']:null;if(($lat!==null&&($lat<-90||$lat>90))||($lng!==null&&($lng<-180||$lng>180)))return new \WP_Error('invalid_coordinates','مختصات واردشده نامعتبر است.');
        $meta=[BusinessMeta::MOBILE=>$mobile,BusinessMeta::PHONE=>sanitize_text_field((string)($input['phone']??'')),BusinessMeta::EMAIL=>Security::email((string)($input['business_email']??$input['email']??'')),BusinessMeta::WEBSITE=>Security::url((string)($input['website']??'')),BusinessMeta::INSTAGRAM=>Security::url((string)($input['instagram']??'')),BusinessMeta::WHATSAPP=>Security::normalize_digits((string)($input['whatsapp']??'')),BusinessMeta::DESCRIPTION=>wp_kses_post((string)($input['description']??'')),BusinessMeta::HOURS=>wp_json_encode((array)($input['hours']??[]),JSON_UNESCAPED_UNICODE),BusinessMeta::PROVINCE_ID=>$province,BusinessMeta::COUNTY_ID=>$county,BusinessMeta::CITY_ID=>$city,BusinessMeta::NEIGHBORHOOD=>sanitize_text_field((string)($input['neighborhood']??'')),BusinessMeta::STREET=>sanitize_text_field((string)($input['street']??'')),BusinessMeta::ALLEY=>sanitize_text_field((string)($input['alley']??'')),BusinessMeta::PLAQUE=>sanitize_text_field((string)($input['plaque']??'')),BusinessMeta::UNIT=>sanitize_text_field((string)($input['unit']??'')),BusinessMeta::FLOOR=>sanitize_text_field((string)($input['floor']??'')),BusinessMeta::POSTAL=>Security::normalize_digits(sanitize_text_field((string)($input['postal_code']??''))),BusinessMeta::FULL_ADDRESS=>sanitize_textarea_field((string)($input['address']??$input['full_address']??'')),BusinessMeta::LAT=>$lat===null?'':(string)$lat,BusinessMeta::LNG=>$lng===null?'':(string)$lng,BusinessMeta::BRAND=>sanitize_text_field((string)($input['brand']??'')),BusinessMeta::SERVICES_RAW=>wp_json_encode(array_values(array_map('sanitize_text_field',(array)($input['services']??[]))),JSON_UNESCAPED_UNICODE)];
        foreach($meta as $key=>$value) update_post_meta($id,$key,$value);
        $cats=CategoryService::categoryIdsFromInput((array)($input['category_ids']??[]),(array)($input['category_phrases']??[])); if(!$cats)return new \WP_Error('missing_category','حداقل یک دسته‌بندی الزامی است.'); set_post_terms_checked($id,SYVO_BD_CATEGORY_TAX,$cats); $postal=$meta[BusinessMeta::POSTAL]; if($postal!==''&&!preg_match('/^\d{10}$/',$postal))return new \WP_Error('invalid_postal_code','کدپستی باید 10 رقم باشد.'); $services=CategoryService::servicesFromText((array)($input['services']??[])); if($services)set_post_terms_checked($id,SYVO_BD_SERVICE_TAX,$services);
        $logo = absint($input['logo_id'] ?? 0);
        $featured = absint($input['featured_id'] ?? 0);
        $gallery = array_values(array_filter(array_map('absint', (array) ($input['gallery_ids'] ?? []))));
        if ($logo && get_post_type($logo) === 'attachment') { update_post_meta($id, BusinessMeta::LOGO_ID, $logo); wp_update_post(['ID'=>$logo,'post_parent'=>$id]); }
        if ($featured && get_post_type($featured) === 'attachment') { set_post_thumbnail($id,$featured); wp_update_post(['ID'=>$featured,'post_parent'=>$id]); }
        if ($gallery) { update_post_meta($id, BusinessMeta::GALLERY_IDS, $gallery); foreach ($gallery as $aid) { wp_update_post(['ID'=>$aid,'post_parent'=>$id]); } }
        if($oldLocation!==[$province,$county,$city]) self::markOldLocationDirty($oldLocation,$id);
        update_post_meta($id,BusinessMeta::LOCATION_SNAPSHOT,wp_json_encode([$province,$county,$city])); update_post_meta($id,BusinessMeta::CATEGORY_SNAPSHOT,wp_json_encode(wp_get_object_terms($id,SYVO_BD_CATEGORY_TAX,['fields'=>'ids'])));
        self::reindex($id);$score=self::duplicateScore($input,$owner);update_post_meta($id,BusinessMeta::DUPLICATE_SCORE,$score);update_post_meta($id,BusinessMeta::PROFILE_COMPLETENESS,self::completeness($id));Queue::enqueue('classify_business',SYVO_BD_CPT,$id,['business_id'=>$id],'business:classify:'.$id,10);Queue::enqueue('refresh_business_index',SYVO_BD_CPT,$id,['business_id'=>$id],'business:index:'.$id,10);self::touchLandings($id);
        return true;
    }
    public static function on_save(int $postId,\WP_Post $post,bool $update): void { if(wp_is_post_revision($postId)||$post->post_type!==SYVO_BD_CPT||defined('DOING_AUTOSAVE'))return;if(!current_user_can('edit_post',$postId))return;self::reindex($postId);self::touchLandings($postId); }
    public static function after_insert(int $postId,\WP_Post $post,bool $update): void { if($post->post_type===SYVO_BD_CPT && !$update && get_post_meta($postId,BusinessMeta::PLAN,true)==='')update_post_meta($postId,BusinessMeta::PLAN,'free'); }
    public static function on_delete(int $postId): void { if(get_post_type($postId)!==SYVO_BD_CPT)return;PageService::markByBusiness($postId);global $wpdb;$wpdb->delete(DB::table('business_index'),['business_id'=>$postId],['%d']); }
    public static function canViewPublic(int $id): bool { $p=get_post($id);return $p&&$p->post_type===SYVO_BD_CPT&&$p->post_status==='publish'; }
    public static function completeness(int $id): int { $fields=[get_the_title($id),get_post_meta($id,BusinessMeta::MOBILE,true),get_post_meta($id,BusinessMeta::EMAIL,true),get_post_meta($id,BusinessMeta::DESCRIPTION,true),get_post_meta($id,BusinessMeta::CITY_ID,true),get_post_meta($id,BusinessMeta::FULL_ADDRESS,true),get_post_meta($id,BusinessMeta::LAT,true),get_post_meta($id,BusinessMeta::LNG,true),get_post_thumbnail_id($id),wp_get_object_terms($id,SYVO_BD_CATEGORY_TAX)];$filled=0;foreach($fields as $f){if(!empty($f))$filled++;}return (int)round($filled/count($fields)*100); }
    public static function reindex(int $id): void { global $wpdb;$p=get_post($id);if(!$p||$p->post_type!==SYVO_BD_CPT)return;$cats=wp_get_post_terms($id,SYVO_BD_CATEGORY_TAX,['fields'=>'names']);$services=wp_get_post_terms($id,SYVO_BD_SERVICE_TAX,['fields'=>'names']);$parts=array_merge([$p->post_title,$p->post_content],$cats,$services,[(string)get_post_meta($id,BusinessMeta::MOBILE,true),(string)get_post_meta($id,BusinessMeta::PHONE,true),(string)get_post_meta($id,BusinessMeta::WEBSITE,true),(string)get_post_meta($id,BusinessMeta::NEIGHBORHOOD,true),(string)get_post_meta($id,BusinessMeta::STREET,true),(string)get_post_meta($id,BusinessMeta::ALLEY,true)]);$data=['business_id'=>$id,'owner_id'=>(int)$p->post_author,'province_id'=>absint(get_post_meta($id,BusinessMeta::PROVINCE_ID,true))?:null,'county_id'=>absint(get_post_meta($id,BusinessMeta::COUNTY_ID,true))?:null,'city_id'=>absint(get_post_meta($id,BusinessMeta::CITY_ID,true))?:null,'lat'=>get_post_meta($id,BusinessMeta::LAT,true)!== ''?(float)get_post_meta($id,BusinessMeta::LAT,true):null,'lng'=>get_post_meta($id,BusinessMeta::LNG,true)!== ''?(float)get_post_meta($id,BusinessMeta::LNG,true):null,'normalized_name'=>Security::normalize_text($p->post_title),'search_text'=>Security::normalize_text(implode(' ',array_filter($parts))),'completeness_score'=>self::completeness($id),'verified'=>(int)get_post_meta($id,BusinessMeta::VERIFIED,true),'status'=>$p->post_status,'updated_at'=>current_time('mysql',true)];$wpdb->replace(DB::table('business_index'),$data,['%d','%d','%d','%d','%d','%f','%f','%s','%s','%f','%d','%s','%s']); }
    public static function duplicateScore(array $input,int $owner): int { global $wpdb;$title=Security::normalize_text((string)($input['name']??''));$website=Security::url((string)($input['website']??''));$phone=Security::normalize_digits((string)($input['business_mobile']??''));$score=0;$table=DB::table('business_index');if($phone!==''){$score+=(int)$wpdb->get_var($wpdb->prepare("SELECT CASE WHEN COUNT(*)>0 THEN 60 ELSE 0 END FROM {$table} WHERE search_text LIKE %s AND status='publish'",'%'.$wpdb->esc_like($phone).'%'));}if($website!==''){$score+=(int)$wpdb->get_var($wpdb->prepare("SELECT CASE WHEN COUNT(*)>0 THEN 80 ELSE 0 END FROM {$table} WHERE search_text LIKE %s AND status='publish'",'%'.$wpdb->esc_like(Security::normalize_text($website)).'%'));}if($title!==''){$score+=(int)$wpdb->get_var($wpdb->prepare("SELECT CASE WHEN COUNT(*)>0 THEN 40 ELSE 0 END FROM {$table} WHERE normalized_name=%s AND status='publish'",$title));}if($owner>0){$same=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE owner_id=%d AND status='publish'",$owner));if($same>0)$score=min(100,$score+10);}return min(100,$score); }
    private static function markOldLocationDirty(array $old,int $id): void { if(count($old)!==3)return; PageService::markByCoordinates($old); }
    public static function touchLandings(int $id): void { PageService::markByBusiness($id); }
    public static function publicData(int $id): array { $p=get_post($id); if(!$p)return[];$city=LocationService::find(absint(get_post_meta($id,BusinessMeta::CITY_ID,true)));$county=LocationService::find(absint(get_post_meta($id,BusinessMeta::COUNTY_ID,true)));$province=LocationService::find(absint(get_post_meta($id,BusinessMeta::PROVINCE_ID,true)));return ['id'=>$id,'url'=>get_permalink($id),'name'=>get_the_title($id),'brand'=>(string)get_post_meta($id,BusinessMeta::BRAND,true),'description'=>wp_kses_post($p->post_content),'mobile'=>(string)get_post_meta($id,BusinessMeta::MOBILE,true),'phone'=>(string)get_post_meta($id,BusinessMeta::PHONE,true),'email'=>(string)get_post_meta($id,BusinessMeta::EMAIL,true),'website'=>(string)get_post_meta($id,BusinessMeta::WEBSITE,true),'instagram'=>(string)get_post_meta($id,BusinessMeta::INSTAGRAM,true),'whatsapp'=>(string)get_post_meta($id,BusinessMeta::WHATSAPP,true),'hours'=>json_decode((string)get_post_meta($id,BusinessMeta::HOURS,true),true)?:[],'services'=>array_values(array_map(static fn($t)=>$t->name,wp_get_post_terms($id,SYVO_BD_SERVICE_TAX))),'categories'=>array_values(array_map(static fn($t)=>['id'=>$t->term_id,'name'=>$t->name,'slug'=>$t->slug],wp_get_post_terms($id,SYVO_BD_CATEGORY_TAX))),'city'=>$city,'county'=>$county,'province'=>$province,'neighborhood'=>(string)get_post_meta($id,BusinessMeta::NEIGHBORHOOD,true),'street'=>(string)get_post_meta($id,BusinessMeta::STREET,true),'address'=>(string)get_post_meta($id,BusinessMeta::FULL_ADDRESS,true),'lat'=>(float)get_post_meta($id,BusinessMeta::LAT,true),'lng'=>(float)get_post_meta($id,BusinessMeta::LNG,true),'logo_id'=>absint(get_post_meta($id,BusinessMeta::LOGO_ID,true)),'gallery_ids'=>array_values(array_map('absint',(array)get_post_meta($id,BusinessMeta::GALLERY_IDS,true))),'verified'=>(bool)get_post_meta($id,BusinessMeta::VERIFIED,true),'completeness'=>BusinessService::completeness($id)]; }
}

function set_post_terms_checked(int $postId,string $taxonomy,array $terms): void { wp_set_post_terms($postId,array_values(array_unique(array_map('absint',$terms))),$taxonomy,false); }

final class SearchService {
    public static function search(array $args): array {
        global $wpdb;
        $index=DB::table('business_index');
        $q=Security::normalize_text((string)($args['q']??''));
        $city=absint($args['city_id']??0); $county=absint($args['county_id']??0); $province=absint($args['province_id']??0); $category=absint($args['category_id']??0);
        $lat=is_numeric($args['lat']??null)?(float)$args['lat']:null; $lng=is_numeric($args['lng']??null)?(float)$args['lng']:null;
        $where=["i.status='publish'"]; $selectParams=[]; $whereParams=[]; $orderParams=[]; $distanceSql='';
        if($lat!==null && $lng!==null){
            $distanceSql=', (6371 * ACOS(LEAST(1,GREATEST(-1,COS(RADIANS(%f))*COS(RADIANS(i.lat))*COS(RADIANS(i.lng)-RADIANS(%f))+SIN(RADIANS(%f))*SIN(RADIANS(i.lat)))))) AS distance_km';
            $selectParams=[$lat,$lng,$lat];
        }
        if($q!==''){ $like='%'.$wpdb->esc_like($q).'%';$where[]='(i.normalized_name LIKE %s OR i.search_text LIKE %s)';$whereParams[]=$like;$whereParams[]=$like; }
        if($city){$where[]='i.city_id=%d';$whereParams[]=$city;} elseif($county){$where[]='i.county_id=%d';$whereParams[]=$county;} elseif($province){$where[]='i.province_id=%d';$whereParams[]=$province;}
        if($category){$where[]='EXISTS (SELECT 1 FROM '.$wpdb->term_relationships.' tr JOIN '.$wpdb->term_taxonomy.' tt ON tt.term_taxonomy_id=tr.term_taxonomy_id WHERE tr.object_id=i.business_id AND tt.taxonomy=%s AND tt.term_id=%d)';$whereParams[]=SYVO_BD_CATEGORY_TAX;$whereParams[]=$category;}
        if($q!==''){ $order=' ORDER BY CASE WHEN i.normalized_name=%s THEN 100 ELSE 0 END DESC';$orderParams[]=$q; } else {$order=' ORDER BY 0 DESC';}
        if($lat!==null&&$lng!==null){$order.=($q!==''?', ':' ORDER BY ').'distance_km ASC';}
        $order.=', i.completeness_score DESC, i.business_id DESC';
        $limit=max(1,min(50,absint($args['per_page']??20)));
        $params=array_merge($selectParams,$whereParams,$orderParams,[$limit]);
        $sql='SELECT i.*'.$distanceSql.' FROM '.$index.' i WHERE '.implode(' AND ',$where).$order.' LIMIT %d';
        $rows=$wpdb->get_results($wpdb->prepare($sql,...$params),\ARRAY_A)?:[];
        return array_map(static fn($row)=>BusinessService::publicData((int)$row['business_id']),$rows);
    }
}

final class PageService {
    public static function fingerprint(int $categoryId,string $locationType,int $locationId): string { return hash('sha256',$categoryId.'|'.$locationType.'|'.$locationId); }
    public static function ensureForBusiness(int $businessId): void { $post=get_post($businessId);if(!$post||$post->post_status!=='publish')return;$cats=wp_get_post_terms($businessId,SYVO_BD_CATEGORY_TAX,['fields'=>'ids']);$city=absint(get_post_meta($businessId,BusinessMeta::CITY_ID,true));$county=absint(get_post_meta($businessId,BusinessMeta::COUNTY_ID,true));$province=absint(get_post_meta($businessId,BusinessMeta::PROVINCE_ID,true));foreach($cats as $catId){foreach([['city',$city],['county',$county],['province',$province]] as [$type,$location])if($location)self::ensure((int)$catId,$type,(int)$location);} }
    public static function ensure(int $categoryId,string $locationType,int $locationId): ?int { global $wpdb;if(self::countBusinesses($categoryId,$locationType,$locationId)<1)return null;$finger=self::fingerprint($categoryId,$locationType,$locationId);$table=DB::table('directory_pages');$existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE fingerprint=%s",$finger),\ARRAY_A);$cat=get_term($categoryId,SYVO_BD_CATEGORY_TAX);$loc=LocationService::find($locationId);if(!$cat||!$loc)return null;$url=self::url($cat,$loc,$locationType);$h1=$cat->name.' در '.$loc['name_fa'];$seoTitle=$h1.' | معرفی کسب‌وکارها';$now=current_time('mysql',true);$data=['fingerprint'=>$finger,'page_type'=>$locationType,'category_term_id'=>$categoryId,'location_id'=>$locationId,'location_type'=>$locationType,'status'=>'active','url'=>$url,'h1'=>$h1,'seo_title'=>$seoTitle,'meta_description'=>'معرفی کسب‌وکارهای '.mb_strtolower($cat->name).' در '.(string)$loc['name_fa'].' با اطلاعات واقعی و قابل جستجو.','canonical_url'=>home_url($url),'business_count'=>self::countBusinesses($categoryId,$locationType,$locationId),'updated_at'=>$now];if($existing){$wpdb->update($table,$data,['id'=>(int)$existing['id'],],array_fill(0,count($data),'%s'),['%d']);if((int)$existing['dirty']===1)Queue::enqueue('generate_page_seo','directory_page',(int)$existing['id'],['page_id'=>(int)$existing['id']],'page:seo:'.$existing['fingerprint'],20);return(int)$existing['id'];}$data['created_at']=$now;$data['dirty']=1;$inserted=$wpdb->insert($table,$data);if(!$inserted){$id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE fingerprint=%s",$finger));if($id){$wpdb->update($table,['status'=>'active','business_count'=>self::countBusinesses($categoryId,$locationType,$locationId),'dirty'=>1,'updated_at'=>$now],['id'=>$id],['%s','%d','%d','%s'],['%d']);}else{return null;}}else{$id=(int)$wpdb->insert_id;}Queue::enqueue('generate_page_seo','directory_page',$id,['page_id'=>$id],'page:seo:'.$finger,20);return$id; }
    public static function url(\WP_Term $cat,array $loc,string $type): string { $catSlug=$cat->slug;$locSlug=SlugService::location($loc);if($type==='city')return '/hiper/'.$catSlug.'/'.$locSlug.'/';if($type==='county')return '/hiper/'.$catSlug.'/county/'.$locSlug.'/';return '/hiper/'.$catSlug.'/province/'.$locSlug.'/'; }
    public static function markByBusiness(int $businessId): void { $old=json_decode((string)get_post_meta($businessId,BusinessMeta::LOCATION_SNAPSHOT,true),true); if(is_array($old)) self::markByCoordinates($old); self::ensureForBusiness($businessId); self::refreshBusinessCounts($businessId); }
    public static function markByCoordinates(array $old): void { global $wpdb; $map=[['city',(int)($old[2]??0)],['county',(int)($old[1]??0)],['province',(int)($old[0]??0)]]; foreach($map as [$type,$locationId]){ if(!$locationId)continue; $sql="UPDATE ".DB::table('directory_pages')." SET dirty=1,status=CASE WHEN business_count>0 THEN status ELSE 'empty' END,updated_at=%s WHERE location_type=%s AND location_id=%d"; $wpdb->query($wpdb->prepare($sql,current_time('mysql',true),$type,$locationId)); } }
    public static function refreshBusinessCounts(int $businessId): void { global $wpdb; $pages=$wpdb->get_results($wpdb->prepare("SELECT * FROM ".DB::table('directory_pages')." WHERE category_term_id IN (SELECT term_id FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id WHERE tr.object_id=%d AND tt.taxonomy=%s)",$businessId,SYVO_BD_CATEGORY_TAX),\ARRAY_A)?:[];foreach($pages as $page){$count=self::countBusinesses((int)$page['category_term_id'],(string)$page['location_type'],(int)$page['location_id']);$wpdb->update(DB::table('directory_pages'),['business_count'=>$count,'status'=>$count>0?'active':'empty','dirty'=>1],['id'=>(int)$page['id']],['%d','%s','%d'],['%d']);Queue::enqueue('generate_page_seo','directory_page',(int)$page['id'],['page_id'=>(int)$page['id']],'page:seo:'.$page['fingerprint'],20);}}
    public static function countBusinesses(int $categoryId,string $locationType,int $locationId): int { global $wpdb;$column=$locationType==='city'?'city_id':($locationType==='county'?'county_id':'province_id');$sql="SELECT COUNT(DISTINCT bi.business_id) FROM ".DB::table('business_index')." bi JOIN {$wpdb->term_relationships} tr ON tr.object_id=bi.business_id JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id WHERE bi.status='publish' AND bi.{$column}=%d AND tt.taxonomy=%s AND tt.term_id=%d";return(int)$wpdb->get_var($wpdb->prepare($sql,$locationId,SYVO_BD_CATEGORY_TAX,$categoryId)); }
    public static function context(string $route,string $categorySlug,string $locationSlug,string $parent=''): ?array {if($route==='directory')return['type'=>'directory'];$cat=get_term_by('slug',$categorySlug,SYVO_BD_CATEGORY_TAX);if(!$cat)return null;$type=$route==='county'?'county':($route==='province'?'province':($route==='subcategory'?'city':'city'));$loc=LocationService::findBySlug($locationSlug,$type);if(!$loc)return null;if($route==='subcategory'){ $parentTerm=get_term_by('slug',$parent,SYVO_BD_CATEGORY_TAX);if(!$parentTerm||((int)$cat->parent!==(int)$parentTerm->term_id))return null;}return['type'=>$type,'category'=>$cat,'location'=>$loc,'url'=>self::url($cat,$loc,$type),'business_count'=>self::countBusinesses((int)$cat->term_id,$type,(int)$loc['id'])]; }
    public static function related(array $ctx): array { global $wpdb;$cat=(int)$ctx['category']->term_id;$loc=(int)$ctx['location']['id'];$type=$ctx['type'];$rows=[];$siblings=get_terms(['taxonomy'=>SYVO_BD_CATEGORY_TAX,'parent'=>(int)$ctx['category']->parent,'hide_empty'=>false]);if(!is_wp_error($siblings))foreach($siblings as $term){if((int)$term->term_id===$cat)continue;$id=self::findPageId((int)$term->term_id,$type,$loc);if($id)$rows[]=['title'=>$term->name,'url'=>home_url((string)$wpdb->get_var($wpdb->prepare('SELECT url FROM '.DB::table('directory_pages').' WHERE id=%d',$id)))];}return $rows; }
    public static function findPageId(int $cat,string $type,int $loc): ?int {global $wpdb;$id=$wpdb->get_var($wpdb->prepare('SELECT id FROM '.DB::table('directory_pages').' WHERE category_term_id=%d AND location_type=%s AND location_id=%d LIMIT 1',$cat,$type,$loc));return$id?(int)$id:null;}
    public static function get(int $id): ?array {global $wpdb;$r=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.DB::table('directory_pages').' WHERE id=%d',$id),\ARRAY_A);return$r?:null;}
    public static function generateContent(int $id): true|\WP_Error { $page=self::get($id);if(!$page)return new \WP_Error('page_not_found','صفحه دایرکتوری پیدا نشد.');$ctx=self::contextFromPage($page);if(!$ctx)return new \WP_Error('page_context_invalid','زمینه صفحه دایرکتوری معتبر نیست.');$businesses=SearchService::search(['category_id'=>(int)$ctx['category']->term_id,'city_id'=>$ctx['type']==='city'?(int)$ctx['location']['id']:0,'county_id'=>$ctx['type']==='county'?(int)$ctx['location']['id']:0,'province_id'=>$ctx['type']==='province'?(int)$ctx['location']['id']:0,'per_page'=>10]);$result=AIService::generatePageSeo($ctx,$businesses);if(is_wp_error($result))return$result;global $wpdb;$wpdb->update(DB::table('directory_pages'),['seo_content'=>$result,'seo_content_hash'=>hash('sha256',$result),'dirty'=>0,'last_generated_at'=>current_time('mysql',true),'business_count'=>self::countBusinesses((int)$page['category_term_id'],(string)$page['location_type'],(int)$page['location_id']),'updated_at'=>current_time('mysql',true)],['id'=>$id],['%s','%s','%d','%s','%d','%s'],['%d']);return true; }
    private static function contextFromPage(array $page): ?array { $cat=get_term((int)$page['category_term_id'],SYVO_BD_CATEGORY_TAX);$loc=LocationService::find((int)$page['location_id']);if(!$cat||!$loc)return null;return ['type'=>$page['location_type'],'category'=>$cat,'location'=>$loc,'url'=>$page['url'],'business_count'=>(int)$page['business_count']]; }
}

final class Queue {
    public static function schedule_recurring(): void { if(!wp_next_scheduled('syvo_bd_process_queue'))wp_schedule_event(time()+30,'syvo_every_minute','syvo_bd_process_queue');if(!wp_next_scheduled('syvo_bd_hourly_maintenance'))wp_schedule_event(time()+120,'hourly','syvo_bd_hourly_maintenance'); }
    public static function unschedule_all(): void { foreach(['syvo_bd_process_queue','syvo_bd_hourly_maintenance'] as $hook){while(($ts=wp_next_scheduled($hook))!==false)wp_unschedule_event($ts,$hook);} }
    public static function enqueue(string $type,string $entityType,int $entityId,array $payload,string $uniqueHash,int $maxAttempts=4,int $delay=0): int { global $wpdb;$table=DB::table('jobs');$now=current_time('mysql',true);$existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE job_type=%s AND unique_hash=%s",$type,$uniqueHash),\ARRAY_A);$data=['job_type'=>$type,'unique_hash'=>$uniqueHash,'entity_type'=>$entityType,'entity_id'=>$entityId,'payload'=>wp_json_encode($payload),'status'=>'pending','run_after'=>gmdate('Y-m-d H:i:s',time()+$delay),'max_attempts'=>$maxAttempts,'updated_at'=>$now];if($existing){$data['attempts']=0;$data['locked_at']=null;$data['last_error_code']='';$data['last_error_message']='';$wpdb->update($table,$data,['id'=>(int)$existing['id']],['%s','%s','%s','%d','%s','%s','%s','%d','%s','%d','%s','%s','%s'],['%d']);return(int)$existing['id'];}$data['created_at']=$now;$wpdb->insert($table,$data,['%s','%s','%s','%d','%s','%s','%s','%d','%s','%s']);return(int)$wpdb->insert_id; }
    public static function process(): void { for($i=0;$i<5;$i++){if(!self::runOne())break;} }
    private static function runOne(): bool { global $wpdb;$table=DB::table('jobs');$wpdb->query("UPDATE {$table} SET status='pending',locked_at=NULL WHERE status='running' AND locked_at < UTC_TIMESTAMP() - INTERVAL 10 MINUTE");$job=$wpdb->get_row("SELECT * FROM {$table} WHERE status='pending' AND run_after<=UTC_TIMESTAMP() AND attempts<max_attempts ORDER BY id ASC LIMIT 1",\ARRAY_A);if(!$job)return false;$claimed=$wpdb->query($wpdb->prepare("UPDATE {$table} SET status='running',locked_at=UTC_TIMESTAMP(),attempts=attempts+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND status='pending'",(int)$job['id']));if(!$claimed)return true;$ok=true;$error=null;try{$payload=json_decode((string)$job['payload'],true)?:[];switch($job['job_type']){case'import_locations':$error=LocationService::import();break;case'generate_page_seo':$error=PageService::generateContent((int)$job['entity_id']);break;case'classify_business':$error=AIService::classifyBusiness((int)$job['entity_id']);break;case'generate_image_alt':$error=MediaService::generateAlt((int)$job['entity_id']);break;case'refresh_business_index':BusinessService::reindex((int)$job['entity_id']);$error=true;break;case'create_directory_page':PageService::ensureForBusiness((int)$job['entity_id']);$error=true;break;default:$error=true;}}catch(\Throwable $e){$ok=false;$error=new \WP_Error('job_exception',$e->getMessage());}if(is_wp_error($error)){$ok=false;$wpdb->update($table,['status'=>((int)$job['attempts']>=(int)$job['max_attempts'])?'failed':'pending','run_after'=>gmdate('Y-m-d H:i:s',time()+min(900,30*(int)$job['attempts'])),'last_error_code'=>$error->get_error_code(),'last_error_message'=>mb_substr($error->get_error_message(),0,1000),'locked_at'=>null,'updated_at'=>gmdate('Y-m-d H:i:s')],['id'=>(int)$job['id']],['%s','%s','%s','%s','%s','%s'],['%d']);}else{$wpdb->update($table,['status'=>'done','locked_at'=>null,'last_error_code'=>'','last_error_message'=>'','updated_at'=>gmdate('Y-m-d H:i:s')],['id'=>(int)$job['id']],['%s','%s','%s','%s','%s'],['%d']);}return true; }
}

final class MaintenanceService {
    public static function run(): void { global $wpdb;$wpdb->query('DELETE FROM '.DB::table('rate_limits').' WHERE reset_at < UTC_TIMESTAMP() - INTERVAL 1 DAY'); }
}

final class MediaService {
    public static function generateAlt(int $attachmentId): true|\WP_Error { $attachment=get_post($attachmentId);if(!$attachment||$attachment->post_type!=='attachment')return new \WP_Error('media_not_found','رسانه یافت نشد.');$meta=(string)get_post_meta($attachmentId,'_wp_attachment_image_alt',true);if($meta!=='' && get_post_meta($attachmentId,'_syvo_bd_generated_alt',true)!=='yes')return true;$parent=(int)$attachment->post_parent;$business=$parent&&get_post_type($parent)===SYVO_BD_CPT?$parent:0;$text=AIService::generateImageAlt($attachmentId,$business);if(is_wp_error($text))return$text;if($text!==''){update_post_meta($attachmentId,'_wp_attachment_image_alt',sanitize_text_field($text));update_post_meta($attachmentId,'_syvo_bd_generated_alt','yes');}return true; }
}

final class SeoService {
    public static function context(): ?array { $route=RewriteService::current_route();if($route)return PageService::context($route['route'],$route['category'],$route['location'],$route['parent_category']);if(is_singular(SYVO_BD_CPT)&&BusinessService::canViewPublic((int)get_queried_object_id(),0))return['profile'=>BusinessService::publicData((int)get_queried_object_id())];return null; }
    public static function title(string $title): string { $ctx=self::context();if(!$ctx)return$title;if(($ctx['type']??'')==='directory')return 'دایرکتوری کسب‌وکارها | سایوو';if(isset($ctx['profile']))return$ctx['profile']['name'].' | معرفی کسب‌وکار در سایوو';return(string)$ctx['category']->name.' در '.(string)$ctx['location']['name_fa'].' | معرفی کسب‌وکارها'; }
    public static function head(): void { if(is_admin())return;$ctx=self::context();if(!$ctx)return;if(($ctx['type']??'')==='directory'){echo '<link rel="canonical" href="'.esc_url(home_url('/hiper/')).'" />';return;}if(isset($ctx['profile'])){self::profileSchema($ctx['profile']);return;}self::landingSchema($ctx);$pageId=PageService::findPageId((int)$ctx['category']->term_id,$ctx['type'],(int)$ctx['location']['id']);$page= $pageId?PageService::get($pageId):null;if($page){echo '<link rel="canonical" href="'.esc_url(home_url($page['url'])).'" />';echo '<meta name="description" content="'.esc_attr($page['meta_description']).'" />';}}
    public static function robots(array $robots): array { $ctx=self::context();if($ctx&&($ctx['type']??'')==='directory')return$robots;if($ctx&&!isset($ctx['profile'])&&(!isset($ctx['business_count'])||(int)$ctx['business_count']<1)){$robots['noindex']=true;$robots['nofollow']=false;}return$robots; }
    private static function landingSchema(array $ctx): void { $items=SearchService::search(['category_id'=>$ctx['category']->term_id,'city_id'=>$ctx['type']==='city'?$ctx['location']['id']:0,'county_id'=>$ctx['type']==='county'?$ctx['location']['id']:0,'province_id'=>$ctx['type']==='province'?$ctx['location']['id']:0,'per_page'=>20]);$list=[];$pos=1;foreach($items as $item){$list[]=['@type'=>'ListItem','position'=>$pos++,'url'=>get_permalink($item['id']),'name'=>$item['name']];}$graph=[['@type'=>'WebPage','name'=>$ctx['category']->name.' در '.$ctx['location']['name_fa'],'url'=>home_url($ctx['url'])],['@type'=>'BreadcrumbList','itemListElement'=>[['@type'=>'ListItem','position'=>1,'name'=>'خانه','item'=>home_url('/')],['@type'=>'ListItem','position'=>2,'name'=>$ctx['category']->name,'item'=>home_url('/hiper/'.esc_attr($ctx['category']->slug).'/')],['@type'=>'ListItem','position'=>3,'name'=>$ctx['location']['name_fa'],'item'=>home_url($ctx['url'])]],],['@type'=>'ItemList','itemListElement'=>$list]];echo'<script type="application/ld+json">'.wp_json_encode(['@context'=>'https://schema.org','@graph'=>$graph],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>';}
    private static function profileSchema(array $p): void { $address=['@type'=>'PostalAddress','addressCountry'=>'IR','addressLocality'=>(string)($p['city']['name_fa']??''),'addressRegion'=>(string)($p['province']['name_fa']??''),'streetAddress'=>(string)$p['address']];$schema=['@context'=>'https://schema.org','@type'=>'LocalBusiness','name'=>$p['name'],'url'=>get_permalink($p['id']),'description'=>wp_strip_all_tags($p['description']),'telephone'=>$p['mobile']?:$p['phone'],'email'=>$p['email'],'image'=>self::imageUrls($p['gallery_ids'],$p['logo_id']),'address'=>$address];if($p['lat']&&$p['lng'])$schema['geo']=['@type'=>'GeoCoordinates','latitude'=>$p['lat'],'longitude'=>$p['lng']];echo'<script type="application/ld+json">'.wp_json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>';}
    private static function imageUrls(array $gallery,int $logo): array {$ids=$gallery;if($logo)$ids[]=$logo;$out=[];foreach(array_unique($ids) as $id){$u=wp_get_attachment_image_url((int)$id,'large');if($u)$out[]=$u;}return$out;}
    public static function maybe_redirect_legacy(): void { if(!is_404())return; }
}

final class FrontendAssets {
    public static function enqueue(): void { wp_enqueue_style('syvo-bd',SYVO_BD_URL.'assets/css/app.css',[],SYVO_BD_VERSION);wp_enqueue_script('syvo-bd',SYVO_BD_URL.'assets/js/app.js',[],SYVO_BD_VERSION,true);wp_localize_script('syvo-bd','SyvoBDConfig',['rest'=>esc_url_raw(rest_url(SYVO_BD_REST_NAMESPACE.'/')),'nonce'=>wp_create_nonce('wp_rest'),'home'=>esc_url_raw(home_url('/')),'tile_url'=>get_option('syvo_bd_map_config')['tile_url']??'']); }
    public static function footer_config(): void {}
}
