<?php
declare(strict_types=1);
namespace Syvo\BusinessDirectory;
if (!defined('ABSPATH')) { exit; }

final class Frontend {
    public static function hooks(): void {
        add_shortcode('syvo_business_register',[self::class,'registerShortcode']);
        add_shortcode('syvo_business_login',[self::class,'loginShortcode']);
        add_shortcode('syvo_business_dashboard',[self::class,'dashboardShortcode']);
        add_shortcode('syvo_business_search',[self::class,'searchShortcode']);
        add_shortcode('syvo_directory',[self::class,'directoryShortcode']);
        add_shortcode('syvo_business_profile',[self::class,'profileShortcode']);
        add_shortcode('syvo_directory_landing',[self::class,'landingShortcode']);
        add_filter('the_content',[self::class,'corePageContent'],1);
        add_filter('the_content',[self::class,'ensureAssetsInShortcode'],5);
    }

    public static function corePageContent(string $content): string {
        if (is_page('hiper')) return self::renderDirectoryHome();
        if (is_page('directory-search')) return self::searchShortcode();
        if (is_page('register-business')) return self::registerShortcode();
        if (is_page('business-login')) return self::loginShortcode();
        if (is_page('dashboard')) return self::dashboardShortcode();
        return $content;
    }

    public static function ensureAssetsInShortcode(string $content): string {
        if (str_contains($content,'[syvo_') || RewriteService::current_route()) FrontendAssets::enqueue();
        return $content;
    }

    public static function registerShortcode(): string {
        FrontendAssets::enqueue();
        if (!is_user_logged_in()) {
            return '<section class="syvo-bd-shell syvo-bd-account-page" data-syvo-bd-account>
                <div class="syvo-bd-account-grid">
                    <div class="syvo-bd-account-visual">
                        <span class="syvo-bd-eyebrow">SYVO / BUSINESS DIRECTORY</span>
                        <h1>کسب‌وکارتان را حرفه‌ای معرفی کنید.</h1>
                        <p>یک پروفایل واقعی، قابل جستجو و آماده دیده‌شدن در دایرکتوری سایوو بسازید.</p>
                        <div class="syvo-bd-account-points"><span>✓ نمایش در جستجو</span><span>✓ موقعیت و آدرس</span><span>✓ گالری و اطلاعات تماس</span></div>
                    </div>
                    <div class="syvo-bd-card syvo-bd-account-card">
                        <div class="syvo-bd-section-head"><span class="syvo-bd-kicker">CREATE ACCOUNT</span><h2>ساخت حساب مالک کسب‌وکار</h2><p class="syvo-bd-muted">بعد از ساخت حساب، بلافاصله وارد فرم ثبت کسب‌وکار می‌شوید.</p></div>
                        '.self::renderAccountStep().'
                    </div>
                </div>
            </section>';
        }
        return self::renderRegisterWizard();
    }

    public static function loginShortcode(): string {
        FrontendAssets::enqueue();
        ob_start();
        ?>
        <section class="syvo-bd-shell syvo-bd-account-page">
            <div class="syvo-bd-single-card syvo-bd-card">
                <div class="syvo-bd-section-head">
                    <span class="syvo-bd-kicker">BUSINESS LOGIN</span>
                    <h1>ورود به حساب کسب‌وکار</h1>
                    <p class="syvo-bd-muted">پروفایل و کسب‌وکارهای خود را مدیریت کنید.</p>
                </div>
                <div class="syvo-bd-login-form">
                    <?php wp_login_form(['echo'=>true,'redirect'=>CorePages::url('dashboard'),'remember'=>true,'label_username'=>'ایمیل یا نام کاربری','label_password'=>'رمز عبور','label_remember'=>'مرا به خاطر بسپار','label_log_in'=>'ورود به حساب']); ?>
                </div>
                <div class="syvo-bd-inline-link">هنوز حساب ندارید؟ <a href="<?php echo esc_url(CorePages::url('register-business')); ?>">ثبت کسب‌وکار</a></div>
            </div>
        </section>
        <?php
        return (string)ob_get_clean();
    }

    public static function dashboardShortcode(): string {
        FrontendAssets::enqueue();
        if (!is_user_logged_in()) return self::loginShortcode();
        $posts=get_posts(['post_type'=>SYVO_BD_CPT,'post_status'=>['publish','draft','private','trash'],'author'=>get_current_user_id(),'posts_per_page'=>50,'orderby'=>'date','order'=>'DESC']);
        ob_start();
        echo '<section class="syvo-bd-shell syvo-bd-dashboard"><div class="syvo-bd-dashboard-head"><div><span class="syvo-bd-kicker">MY BUSINESSES</span><h1>پنل کسب‌وکار</h1><p class="syvo-bd-muted">پروفایل‌های خود را مدیریت و وضعیت انتشارشان را ببینید.</p></div><a class="syvo-bd-btn syvo-bd-btn-primary" href="'.esc_url(CorePages::url('register-business')).'">'.self::icon('plus').' افزودن کسب‌وکار</a></div>';
        echo '<div class="syvo-bd-business-list">';
        foreach($posts as $p){
            $status=$p->post_status==='publish'?'منتشرشده':($p->post_status==='draft'?'پیش‌نویس':'خصوصی');
            echo '<article class="syvo-bd-business-card"><div class="syvo-bd-business-main"><span class="syvo-bd-status">'.$status.'</span><h3>'.esc_html($p->post_title).'</h3><p>'.esc_html(mb_substr(wp_strip_all_tags($p->post_content),0,180)).'</p></div><div class="syvo-bd-card-actions">';
            if($p->post_status==='publish') echo '<a class="syvo-bd-btn syvo-bd-btn-secondary" href="'.esc_url(get_permalink($p->ID)).'">'.self::icon('eye').' مشاهده</a>';
            echo '<a class="syvo-bd-btn syvo-bd-btn-secondary" href="'.esc_url(home_url('/dashboard/?business_id='.$p->ID)).'">'.self::icon('edit').' ویرایش</a></div></article>';
        }
        if(!$posts) echo '<div class="syvo-bd-empty-state"><div class="syvo-bd-empty-icon">'.self::icon('store').'</div><h3>هنوز کسب‌وکاری ثبت نشده</h3><p>اولین پروفایل خود را بسازید تا در دایرکتوری نمایش داده شود.</p><a class="syvo-bd-btn syvo-bd-btn-primary" href="'.esc_url(CorePages::url('register-business')).'">'.self::icon('plus').' ثبت کسب‌وکار</a></div>';
        echo '</div></section>';
        return (string)ob_get_clean();
    }

    public static function searchShortcode(): string {
        FrontendAssets::enqueue();
        return '<section class="syvo-bd-shell syvo-bd-search-page" data-syvo-bd-search>
            <div class="syvo-bd-search-hero">
                <span class="syvo-bd-eyebrow">DISCOVER LOCAL BUSINESS</span>
                <h1>دنبال چه کسب‌وکاری هستید؟</h1>
                <p>نام کسب‌وکار، خدمت یا حوزه فعالیت را جستجو کنید و در ادامه موقعیت را محدود کنید.</p>
                <div class="syvo-bd-search-box"><span class="syvo-bd-search-icon">'.self::icon('search').'</span><input type="search" data-search-q placeholder="مثلاً طراحی سایت، رستوران، پزشک..."><button class="syvo-bd-btn syvo-bd-btn-primary" data-search-submit>'.self::icon('search').' جستجو</button></div>
            </div>
            <div class="syvo-bd-search-layout">
                <aside class="syvo-bd-filter-card syvo-bd-card">
                    <div class="syvo-bd-filter-head"><h3>فیلتر موقعیت</h3><span>اختیاری</span></div>
                    <label class="syvo-bd-field"><span>استان</span><select data-search-province><option value="">انتخاب استان</option></select></label>
                    <label class="syvo-bd-field"><span>شهرستان</span><select data-search-county disabled><option value="">ابتدا استان را انتخاب کنید</option></select></label>
                    <label class="syvo-bd-field"><span>شهر</span><select data-search-city disabled><option value="">ابتدا شهرستان را انتخاب کنید</option></select></label>
                    <button class="syvo-bd-btn syvo-bd-btn-secondary syvo-bd-full-btn" data-use-location>'.self::icon('pin').' استفاده از موقعیت من</button>
                </aside>
                <div class="syvo-bd-search-results-wrap"><div class="syvo-bd-results-head"><strong>نتایج</strong><span data-results-count>آماده جستجو</span></div><div class="syvo-bd-results" data-search-results><div class="syvo-bd-empty-state syvo-bd-empty-search"><div class="syvo-bd-empty-icon">'.self::icon('search').'</div><h3>هنوز جستجویی انجام نشده</h3><p>عبارت موردنظر را وارد کنید و جستجو را بزنید.</p></div></div></div>
            </div>
        </section>';
    }

    public static function directoryShortcode(): string {
        FrontendAssets::enqueue();
        return self::renderDirectoryHome();
    }

    private static function renderDirectoryHome(): string {
        $cats=CategoryService::allTree();
        ob_start();
        echo '<section class="syvo-bd-shell syvo-bd-directory-home">
            <div class="syvo-bd-directory-hero">
                <div class="syvo-bd-hero-copy"><span class="syvo-bd-eyebrow">SYVO / HIPER</span><h1>پیدا کردن یک کسب‌وکار خوب، نباید سخت باشد.</h1><p>کسب‌وکارها را بر اساس حوزه فعالیت، استان، شهرستان و شهر پیدا کنید.</p>
                    <a href="'.esc_url(CorePages::url('register-business')).'" class="syvo-bd-btn syvo-bd-btn-primary">'.self::icon('store').' ثبت کسب‌وکار</a>
                </div>
                <div class="syvo-bd-hero-orbit"><span></span><span></span><span></span><div class="syvo-bd-orbit-card">'.self::icon('search').' <strong>جستجوی محلی</strong><small>دقیق، سریع، مرحله‌ای</small></div></div>
            </div>
            <div class="syvo-bd-directory-search syvo-bd-card"><div class="syvo-bd-mini-label">شروع سریع</div><div class="syvo-bd-search-row"><div class="syvo-bd-search-box compact"><span class="syvo-bd-search-icon">'.self::icon('search').'</span><input data-home-q type="search" placeholder="نام کسب‌وکار یا خدمت..."></div><label class="syvo-bd-select-inline"><span>استان</span><select data-home-province><option value="">همه استان‌ها</option></select></label><label class="syvo-bd-select-inline"><span>شهرستان</span><select data-home-county disabled><option value="">همه شهرستان‌ها</option></select></label><label class="syvo-bd-select-inline"><span>شهر</span><select data-home-city disabled><option value="">همه شهرها</option></select></label><button class="syvo-bd-btn syvo-bd-btn-primary" data-home-search>'.self::icon('arrow').' جستجو</button></div></div>
            <div class="syvo-bd-section-head syvo-bd-directory-heading"><div><span class="syvo-bd-kicker">CATEGORIES</span><h2>از اینجا شروع کنید</h2><p class="syvo-bd-muted">حوزه‌ای را که به دنبال آن هستید انتخاب کنید.</p></div><a class="syvo-bd-text-link" href="'.esc_url(CorePages::url('directory-search')).'">جستجوی پیشرفته '.self::icon('arrow').'</a></div>
            <div class="syvo-bd-category-grid">';
        foreach($cats as $node){
            echo '<article class="syvo-bd-category-card"><a href="'.esc_url(add_query_arg('category',sanitize_title($node['slug']),CorePages::url('directory-search'))).'" class="syvo-bd-category-main"><span class="syvo-bd-category-icon">'.self::icon('grid').'</span><span><strong>'.esc_html($node['name']).'</strong><small>'.count((array)($node['children']??[])).' زیرگروه</small></span><span class="syvo-bd-category-arrow">'.self::icon('chevron').'</span></a>';
            if(!empty($node['children'])){
                echo '<div class="syvo-bd-category-children">';
                foreach(array_slice((array)$node['children'],0,5) as $child){
                    echo '<a href="'.esc_url(add_query_arg('category',sanitize_title($child['slug']),CorePages::url('directory-search'))).'">'.esc_html($child['name']).'</a>';
                }
                if(count($node['children'])>5) echo '<a class="more" href="'.esc_url(add_query_arg('category',sanitize_title($node['slug']),CorePages::url('directory-search'))).'">مشاهده همه</a>';
                echo '</div>';
            }
            echo '</article>';
        }
        echo '</div></section>';
        return (string)ob_get_clean();
    }

    public static function profileShortcode(): string {
        FrontendAssets::enqueue();
        $id=absint($_GET['business_id']??get_queried_object_id());
        return $id?self::render_business_profile($id):'<section class="syvo-bd-shell"><div class="syvo-bd-card syvo-bd-empty-state"><h2>کسب‌وکار پیدا نشد.</h2></div></section>';
    }

    public static function landingShortcode(): string {
        FrontendAssets::enqueue();
        $route=RewriteService::current_route();
        if(!$route)return self::renderDirectoryHome();
        $ctx=PageService::context($route['route'],$route['category'],$route['location'],$route['parent_category']);
        return $ctx?self::renderLanding($ctx):self::notFound();
    }

    public static function render_virtual_request(): string {
        FrontendAssets::enqueue();
        $route=RewriteService::current_route();
        if(!$route)return self::renderDirectoryHome();
        if($route['route']==='directory')return self::renderDirectoryHome();
        $ctx=PageService::context($route['route'],$route['category'],$route['location'],$route['parent_category']);
        return $ctx?self::renderLanding($ctx):self::notFound();
    }

    private static function notFound(): string {
        status_header(404);
        return '<section class="syvo-bd-shell"><div class="syvo-bd-card syvo-bd-empty-state"><div class="syvo-bd-empty-icon">'.self::icon('search').'</div><h2>این صفحه پیدا نشد</h2><p>ممکن است ترکیب دسته و موقعیت هنوز صفحه فعال نداشته باشد.</p><a class="syvo-bd-btn syvo-bd-btn-primary" href="'.esc_url(CorePages::url('hiper')).'">'.self::icon('arrow').' بازگشت به دایرکتوری</a></div></section>';
    }

    private static function renderLanding(array $ctx): string {
        $items=SearchService::search(['category_id'=>(int)$ctx['category']->term_id,'city_id'=>$ctx['type']==='city'?(int)$ctx['location']['id']:0,'county_id'=>$ctx['type']==='county'?(int)$ctx['location']['id']:0,'province_id'=>$ctx['type']==='province'?(int)$ctx['location']['id']:0,'per_page'=>50]);
        $pageId=PageService::findPageId((int)$ctx['category']->term_id,$ctx['type'],(int)$ctx['location']['id']);
        $page=$pageId?PageService::get($pageId):null;
        ob_start();
        echo '<section class="syvo-bd-shell syvo-bd-landing"><div class="syvo-bd-section-head"><div><span class="syvo-bd-kicker">SYVO / HIPER</span><h1>'.esc_html($ctx['category']->name).' در '.esc_html($ctx['location']['name_fa']).'</h1><p class="syvo-bd-muted">'.esc_html((string)($ctx['business_count'])).' کسب‌وکار منتشرشده</p></div><a class="syvo-bd-btn syvo-bd-btn-secondary" href="'.esc_url(CorePages::url('hiper')).'">'.self::icon('arrow').' دایرکتوری</a></div><div class="syvo-bd-landing-grid"><div class="syvo-bd-grid" data-business-results>';
        foreach($items as $item)self::businessCard($item);
        if(!$items)echo '<div class="syvo-bd-empty-state"><h3>هنوز کسب‌وکاری در این ترکیب ثبت نشده.</h3><p>کسب‌وکارتان را در این موقعیت ثبت کنید.</p><a class="syvo-bd-btn syvo-bd-btn-primary" href="'.esc_url(CorePages::url('register-business')).'">'.self::icon('plus').' ثبت کسب‌وکار</a></div>';
        echo '</div><aside class="syvo-bd-map-panel"><div class="syvo-bd-map" data-map data-lat="'.esc_attr($items[0]['lat']??$ctx['location']['latitude']??35.6892).'" data-lng="'.esc_attr($items[0]['lng']??$ctx['location']['longitude']??51.389).'"></div></aside></div>';
        if($page&&!empty($page['seo_content']))echo '<section class="syvo-bd-seo-content">'.wp_kses_post($page['seo_content']).'</section>';
        echo '</section>';
        return (string)ob_get_clean();
    }

    private static function businessCard(array $item): void {
        echo '<article class="syvo-bd-business-card"><div class="syvo-bd-business-main"><div class="syvo-bd-card-top"><span class="syvo-bd-status">'.($item['verified']?'تأییدشده':'پروفایل کسب‌وکار').'</span><span class="syvo-bd-score">'.esc_html((string)$item['completeness']).'% تکمیل</span></div><h3><a href="'.esc_url(get_permalink($item['id'])).'">'.esc_html($item['name']).'</a></h3><p>'.esc_html(mb_substr(wp_strip_all_tags((string)$item['description']),0,170)).'</p><div class="syvo-bd-tags">';
        foreach(array_slice((array)$item['services'],0,4) as $service)echo '<span>'.esc_html($service).'</span>';
        echo '</div></div><div class="syvo-bd-business-side"><span>'.esc_html((string)($item['city']['name_fa']??'')) .'</span><a class="syvo-bd-btn syvo-bd-btn-secondary syvo-bd-icon-btn" href="'.esc_url(get_permalink($item['id'])).'">'.self::icon('arrow').'</a></div></article>';
    }

    public static function render_business_profile(int $id): string {
        if(!BusinessService::canViewPublic($id))return self::notFound();
        $p=BusinessService::publicData($id);
        ob_start();
        echo '<section class="syvo-bd-shell syvo-bd-profile"><div class="syvo-bd-profile-head"><div><span class="syvo-bd-kicker">BUSINESS PROFILE</span><h1>'.esc_html($p['name']).'</h1><div class="syvo-bd-profile-location">'.esc_html($p['province']['name_fa']??'').' · '.esc_html($p['city']['name_fa']??'').'</div></div><a class="syvo-bd-btn syvo-bd-btn-secondary" href="'.esc_url(CorePages::url('hiper')).'">'.self::icon('arrow').' دایرکتوری</a></div><div class="syvo-bd-profile-grid"><main class="syvo-bd-card"><div class="syvo-bd-profile-main">';
        if($p['logo_id']){ $url=wp_get_attachment_image_url((int)$p['logo_id'],'medium'); if($url)echo '<img class="syvo-bd-profile-logo" src="'.esc_url($url).'" alt="'.esc_attr($p['name']).'">'; }
        if($p['brand'])echo '<div class="syvo-bd-brand">'.esc_html($p['brand']).'</div>';
        echo '<p class="syvo-bd-profile-desc">'.wp_kses_post(wpautop($p['description'])).'</p><div class="syvo-bd-tags">';
        foreach($p['categories'] as $cat)echo '<span>'.esc_html($cat['name']).'</span>';
        foreach($p['services'] as $service)echo '<span>'.esc_html($service).'</span>';
        echo '</div><div class="syvo-bd-contact-grid">';
        if($p['mobile'])echo '<a href="tel:'.esc_attr($p['mobile']).'">'.self::icon('phone').' '.esc_html($p['mobile']).'</a>';
        if($p['website'])echo '<a href="'.esc_url($p['website']).'" rel="nofollow">'.self::icon('globe').' وب‌سایت</a>';
        if($p['instagram'])echo '<a href="'.esc_url($p['instagram']).'" rel="nofollow">'.self::icon('instagram').' اینستاگرام</a>';
        echo '</div><div class="syvo-bd-address-grid"><div><small>استان</small><strong>'.esc_html($p['province']['name_fa']??'—').'</strong></div><div><small>شهرستان</small><strong>'.esc_html($p['county']['name_fa']??'—').'</strong></div><div><small>شهر</small><strong>'.esc_html($p['city']['name_fa']??'—').'</strong></div><div class="full"><small>آدرس</small><strong>'.esc_html($p['address']?:'ثبت نشده').'</strong></div></div></div></main><aside class="syvo-bd-map-panel"><div class="syvo-bd-map" data-map data-lat="'.esc_attr($p['lat']).'" data-lng="'.esc_attr($p['lng']).'"></div></aside></div></section>';
        return (string)ob_get_clean();
    }

    private static function renderRegisterWizard(): string {
        ob_start();
        echo '<section class="syvo-bd-shell syvo-bd-register" data-syvo-bd-wizard>
            <div class="syvo-bd-register-head"><div><span class="syvo-bd-eyebrow">CREATE BUSINESS</span><h1>ساخت پروفایل کسب‌وکار</h1><p>اطلاعات را مرحله‌به‌مرحله کامل کنید؛ تا مرحله فعلی کامل نشود، مرحله بعد باز نمی‌شود.</p></div><div class="syvo-bd-progress-pill"><strong data-step-label>مرحله ۱ از ۷</strong><span>ثبت حرفه‌ای</span></div></div>
            <div class="syvo-bd-card syvo-bd-wizard-card"><div class="syvo-bd-stepper" data-stepper></div><form data-business-form novalidate>';
        echo self::renderWizardStep(1,'اطلاعات کسب‌وکار',self::renderBusinessFields());
        echo self::renderWizardStep(2,'دسته‌بندی و خدمات',self::renderCategoryFields());
        echo self::renderWizardStep(3,'موقعیت مکانی',self::renderLocationFields());
        echo self::renderWizardStep(4,'آدرس',self::renderAddressFields());
        echo self::renderWizardStep(5,'تصاویر',self::renderMediaFields());
        echo self::renderWizardStep(6,'معرفی کسب‌وکار',self::renderDescriptionFields());
        echo self::renderWizardStep(7,'بازبینی و ثبت','<div class="syvo-bd-review-grid" data-review></div><div class="syvo-bd-final-note">پس از ثبت، اطلاعات شما آماده انتشار در دایرکتوری خواهد بود.</div>');
        echo '<div class="syvo-bd-wizard-footer"><div class="syvo-bd-step-message" data-step-message></div><div class="syvo-bd-wizard-actions"><button type="button" class="syvo-bd-btn syvo-bd-btn-secondary" data-prev>'.self::icon('chevron-back').' قبلی</button><button type="button" class="syvo-bd-btn syvo-bd-btn-primary" data-next>ادامه '.self::icon('arrow').'</button><button type="submit" class="syvo-bd-btn syvo-bd-btn-primary" data-submit hidden>'.self::icon('check').' ثبت نهایی</button><button type="button" class="syvo-bd-text-btn" data-draft>ذخیره پیش‌نویس</button></div></div></form></div></section>';
        return (string)ob_get_clean();
    }

    private static function renderAccountStep(): string {
        return '<form class="syvo-bd-form" data-register-account><div class="syvo-bd-form-grid"><label class="syvo-bd-field"><span>نام</span><input name="name" autocomplete="given-name" required></label><label class="syvo-bd-field"><span>نام خانوادگی</span><input name="last_name" autocomplete="family-name" required></label><label class="syvo-bd-field"><span>شماره موبایل</span><input name="mobile" inputmode="numeric" autocomplete="tel" placeholder="09..." required></label><label class="syvo-bd-field"><span>ایمیل</span><input type="email" name="email" autocomplete="email" required></label><label class="syvo-bd-field full"><span>کد ملی</span><input name="national_id" inputmode="numeric" required></label><label class="syvo-bd-field"><span>رمز عبور</span><input type="password" name="password" autocomplete="new-password" required><small>حداقل ۱۰ کاراکتر</small></label><label class="syvo-bd-field"><span>تکرار رمز عبور</span><input type="password" name="password_confirm" autocomplete="new-password" required></label><div class="syvo-bd-honeypot"><input name="website_url" tabindex="-1" autocomplete="off"></div></div><div data-account-message></div><button class="syvo-bd-btn syvo-bd-btn-primary syvo-bd-full-btn" type="submit">'.self::icon('user-plus').' ساخت حساب و ادامه</button></form>';
    }

    private static function renderWizardStep(int $n,string $title,string $body): string {
        return '<section class="syvo-bd-step" data-step="'.esc_attr((string)$n).'"><div class="syvo-bd-step-heading"><span class="syvo-bd-step-number">'.esc_html((string)$n).'</span><div><span class="syvo-bd-kicker">STEP '.esc_html((string)$n).'</span><h2>'.esc_html($title).'</h2></div></div>'.$body.'</section>';
    }

    private static function renderBusinessFields(): string {
        return '<div class="syvo-bd-form-grid"><label class="syvo-bd-field full"><span>نام کسب‌وکار <b>*</b></span><input name="name" required placeholder="مثلاً شرکت ..."></label><label class="syvo-bd-field"><span>نام برند</span><input name="brand" placeholder="در صورت تفاوت"></label><label class="syvo-bd-field"><span>موبایل کسب‌وکار <b>*</b></span><input name="business_mobile" inputmode="tel" required></label><label class="syvo-bd-field"><span>تلفن ثابت</span><input name="phone" inputmode="tel"></label><label class="syvo-bd-field"><span>ایمیل کسب‌وکار</span><input type="email" name="business_email"></label><label class="syvo-bd-field"><span>وب‌سایت</span><input type="url" name="website" placeholder="https://"></label><label class="syvo-bd-field full"><span>اینستاگرام</span><input type="url" name="instagram" placeholder="https://instagram.com/..."></label></div>';
    }

    private static function renderCategoryFields(): string {
        return '<div class="syvo-bd-helper-line">حداقل یک دسته‌بندی انتخاب کنید. برای انتخاب دقیق‌تر، زیرگروه‌ها را هم ببینید.</div><div class="syvo-bd-category-picker" data-category-tree></div><label class="syvo-bd-field full"><span>خدمات</span><textarea name="services_text" rows="5" placeholder="هر خدمت را با ویرگول جدا کنید"></textarea></label>';
    }

    private static function renderLocationFields(): string {
        return '<div class="syvo-bd-location-grid"><label class="syvo-bd-field"><span>استان <b>*</b></span><select name="province_id" data-province required><option value="">انتخاب استان</option></select></label><label class="syvo-bd-field"><span>شهرستان <b>*</b></span><select name="county_id" data-county required disabled><option value="">ابتدا استان را انتخاب کنید</option></select></label><label class="syvo-bd-field"><span>شهر <b>*</b></span><select name="city_id" data-city required disabled><option value="">ابتدا شهرستان را انتخاب کنید</option></select></label></div><div class="syvo-bd-location-actions"><button type="button" class="syvo-bd-btn syvo-bd-btn-secondary" data-current-location>'.self::icon('pin').' استفاده از موقعیت فعلی</button></div><div class="syvo-bd-map" data-form-map data-lat="" data-lng=""></div><input type="hidden" name="lat"><input type="hidden" name="lng">';
    }

    private static function renderAddressFields(): string {
        return '<div class="syvo-bd-form-grid"><label class="syvo-bd-field"><span>محله</span><input name="neighborhood"></label><label class="syvo-bd-field"><span>خیابان</span><input name="street"></label><label class="syvo-bd-field"><span>کوچه</span><input name="alley"></label><label class="syvo-bd-field"><span>پلاک</span><input name="plaque"></label><label class="syvo-bd-field"><span>واحد</span><input name="unit"></label><label class="syvo-bd-field"><span>کدپستی</span><input name="postal_code" inputmode="numeric"></label><label class="syvo-bd-field full"><span>آدرس کامل <b>*</b></span><textarea name="address" rows="5" required placeholder="آدرس کامل و قابل استفاده برای مشتری..."></textarea></label></div>';
    }

    private static function renderMediaFields(): string {
        return '<div class="syvo-bd-upload-grid"><label class="syvo-bd-upload"><span>'.self::icon('image').' لوگو</span><small>JPG / PNG / WebP تا ۵MB</small><input type="file" accept="image/jpeg,image/png,image/webp" data-upload-logo></label><label class="syvo-bd-upload"><span>'.self::icon('image').' تصویر اصلی</span><small>نمای اصلی پروفایل</small><input type="file" accept="image/jpeg,image/png,image/webp" data-upload-featured></label><label class="syvo-bd-upload"><span>'.self::icon('gallery').' گالری</span><small>چند تصویر از محیط یا نمونه کار</small><input type="file" accept="image/jpeg,image/png,image/webp" multiple data-upload-gallery></label></div><div class="syvo-bd-preview" data-media-preview></div>';
    }

    private static function renderDescriptionFields(): string {
        return '<label class="syvo-bd-field full"><span>معرفی کسب‌وکار <b>*</b></span><textarea name="description" rows="11" required placeholder="چه کاری انجام می‌دهید؟ چه خدماتی دارید؟ چرا مشتری باید شما را انتخاب کند؟"></textarea></label>';
    }

    public static function renderVirtualRequest(): string { FrontendAssets::enqueue(); $route=RewriteService::current_route(); if(!$route||$route['route']==='directory')return self::renderDirectoryHome(); $ctx=PageService::context($route['route'],$route['category'],$route['location'],$route['parent_category']); return $ctx?self::renderLanding($ctx):self::notFound(); }

    private static function input(string $name,string $label,string $type='text'): string { return '<label class="syvo-bd-field"><span>'.esc_html($label).'</span><input type="'.esc_attr($type).'" name="'.esc_attr($name).'"></label>'; }

    private static function icon(string $name): string {
        $icons=[
            'search'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>',
            'arrow'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"></path><path d="m13 6 6 6-6 6"></path></svg>',
            'chevron'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>',
            'chevron-back'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>',
            'check'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"></path></svg>',
            'plus'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg>',
            'grid'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"></rect><rect x="14" y="4" width="6" height="6" rx="1"></rect><rect x="4" y="14" width="6" height="6" rx="1"></rect><rect x="14" y="14" width="6" height="6" rx="1"></rect></svg>',
            'pin'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.2 7-12A7 7 0 0 0 5 9c0 5.8 7 12 7 12Z"></path><circle cx="12" cy="9" r="2.5"></circle></svg>',
            'store'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10h16l-1 10H5L4 10Z"></path><path d="M6 10 7 5h10l1 5"></path><path d="M8 14v6M16 14v6"></path></svg>',
            'user-plus'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"></circle><path d="M3 20c0-3.2 2.5-5 6-5s6 1.8 6 5"></path><path d="M18 8v6M15 11h6"></path></svg>',
            'phone'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h3l1.4 4-2 1.8a15 15 0 0 0 6.8 6.8l1.8-2 4 1.4v3c0 .9-.7 1.7-1.6 1.8C11 20.9 3.1 13 3.2 4.6 3.3 3.7 4.1 3 5 3h1Z"></path></svg>',
            'globe'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"></circle><path d="M4 12h16M12 4c2 2.3 3 4.8 3 8s-1 5.7-3 8c-2-2.3-3-4.8-3-8s1-5.7 3-8Z"></path></svg>',
            'instagram'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="4"></rect><circle cx="12" cy="12" r="3.5"></circle><circle cx="17.3" cy="6.8" r="1"></circle></svg>',
            'image'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="2"></rect><circle cx="9" cy="10" r="1.5"></circle><path d="m6 17 4-4 3 3 2-2 3 3"></path></svg>',
            'gallery'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="2"></rect><path d="m6 16 4-4 2.5 2.5 2-2L19 16"></path></svg>',
            'edit'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 19 1-4L16 5a2.1 2.1 0 0 1 3 3L9 18l-4 1Z"></path><path d="m14 7 3 3"></path></svg>',
            'eye'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12s3.5-6 9-6 9 6 9 6-3.5 6-9 6-9-6-9-6Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>',
        ];
        return $icons[$name]??'';
    }
}
