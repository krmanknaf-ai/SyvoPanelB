<?php
defined('ABSPATH') || exit;
return [
    [
        'name' => 'خدمات',
        'slug' => 'services',
        'children' => [
            [
                'name' => 'فناوری',
                'slug' => 'technology',
                'children' => [
                    [
                        'name' => 'طراحی سایت',
                        'slug' => 'web-design',
                        'children' => [
                            ['name' => 'طراحی سایت شرکتی', 'slug' => 'corporate-websites', 'children' => []],
                            ['name' => 'فروشگاه اینترنتی', 'slug' => 'ecommerce-websites', 'children' => []],
                        ],
                    ],
                    ['name' => 'سئو', 'slug' => 'seo', 'children' => []],
                    ['name' => 'خدمات نرم افزار', 'slug' => 'software-services', 'children' => []],
                    ['name' => 'توسعه نرم افزار', 'slug' => 'software-development', 'children' => []],
                    ['name' => 'امنیت سایبری', 'slug' => 'cyber-security', 'children' => []],
                ],
            ],
        ],
    ],
    [
        'name' => 'ساختمان',
        'slug' => 'construction',
        'children' => [
            [
                'name' => 'معماری',
                'slug' => 'architecture',
                'children' => [
                    ['name' => 'طراحی معماری', 'slug' => 'architecture-design', 'children' => []],
                    ['name' => 'طراحی داخلی', 'slug' => 'interior-design', 'children' => []],
                ],
            ],
            ['name' => 'ساخت و ساز', 'slug' => 'construction-and-building', 'children' => []],
            ['name' => 'خدمات فنی ساختمان', 'slug' => 'building-technical-services', 'children' => []],
        ],
    ],
    [
        'name' => 'بازاریابی و تبلیغات',
        'slug' => 'marketing-advertising',
        'children' => [
            ['name' => 'دیجیتال مارکتینگ', 'slug' => 'digital-marketing', 'children' => []],
            ['name' => 'تبلیغات', 'slug' => 'advertising', 'children' => []],
            ['name' => 'تولید محتوا', 'slug' => 'content-production', 'children' => []],
            ['name' => 'چاپ', 'slug' => 'printing', 'children' => []],
            ['name' => 'عکاسی', 'slug' => 'photography', 'children' => []],
        ],
    ],
    [
        'name' => 'پزشکی و سلامت',
        'slug' => 'medical-health',
        'children' => [
            ['name' => 'پزشکی', 'slug' => 'medical', 'children' => []],
            ['name' => 'دندانپزشکی', 'slug' => 'dentistry', 'children' => []],
            ['name' => 'داروخانه', 'slug' => 'pharmacy', 'children' => []],
            ['name' => 'کلینیک', 'slug' => 'clinic', 'children' => []],
        ],
    ],
    [
        'name' => 'زیبایی',
        'slug' => 'beauty',
        'children' => [
            ['name' => 'آرایشگاه', 'slug' => 'hair-salon', 'children' => []],
            ['name' => 'خدمات زیبایی', 'slug' => 'beauty-services', 'children' => []],
            ['name' => 'پوست و مو', 'slug' => 'skin-hair', 'children' => []],
        ],
    ],
    [
        'name' => 'آموزش',
        'slug' => 'education',
        'children' => [
            ['name' => 'آموزش زبان', 'slug' => 'language-training', 'children' => []],
            ['name' => 'آموزش برنامه نویسی', 'slug' => 'programming-training', 'children' => []],
            ['name' => 'آموزشگاه', 'slug' => 'training-center', 'children' => []],
        ],
    ],
    [
        'name' => 'غذا و رستوران',
        'slug' => 'food-restaurant',
        'children' => [
            ['name' => 'رستوران', 'slug' => 'restaurant', 'children' => []],
            ['name' => 'کافه', 'slug' => 'cafe', 'children' => []],
            ['name' => 'فست فود', 'slug' => 'fast-food', 'children' => []],
            ['name' => 'تهیه غذا', 'slug' => 'catering', 'children' => []],
        ],
    ],
    [
        'name' => 'فروشگاه',
        'slug' => 'shop',
        'children' => [
            ['name' => 'فروشگاه پوشاک', 'slug' => 'clothing-store', 'children' => []],
            ['name' => 'فروشگاه لوازم خانگی', 'slug' => 'home-appliance-store', 'children' => []],
            ['name' => 'فروشگاه دیجیتال', 'slug' => 'digital-store', 'children' => []],
        ],
    ],
    [
        'name' => 'خودرو',
        'slug' => 'auto',
        'children' => [
            ['name' => 'تعمیرگاه', 'slug' => 'auto-repair', 'children' => []],
            ['name' => 'قطعات خودرو', 'slug' => 'auto-parts', 'children' => []],
            ['name' => 'خدمات خودرو', 'slug' => 'auto-services', 'children' => []],
        ],
    ],
    [
        'name' => 'املاک',
        'slug' => 'real-estate',
        'children' => [
            ['name' => 'مشاور املاک', 'slug' => 'real-estate-agent', 'children' => []],
            ['name' => 'خرید و فروش', 'slug' => 'buy-sell-real-estate', 'children' => []],
            ['name' => 'اجاره', 'slug' => 'rental-real-estate', 'children' => []],
        ],
    ],
    [
        'name' => 'حقوقی و مالی',
        'slug' => 'legal-finance',
        'children' => [
            ['name' => 'خدمات حقوقی', 'slug' => 'legal-services', 'children' => []],
            ['name' => 'وکالت', 'slug' => 'lawyer', 'children' => []],
            ['name' => 'حسابداری', 'slug' => 'accounting', 'children' => []],
            ['name' => 'خدمات مالی', 'slug' => 'financial-services', 'children' => []],
        ],
    ],
    [
        'name' => 'گردشگری و حمل و نقل',
        'slug' => 'tourism-transportation',
        'children' => [
            ['name' => 'آژانس مسافرتی', 'slug' => 'travel-agency', 'children' => []],
            ['name' => 'هتل', 'slug' => 'hotel', 'children' => []],
            ['name' => 'حمل و نقل', 'slug' => 'transportation', 'children' => []],
        ],
    ],
    ['name' => 'خدمات فنی', 'slug' => 'technical-services', 'children' => []],
    [
        'name' => 'خدمات منزل',
        'slug' => 'home-services',
        'children' => [
            ['name' => 'نظافت', 'slug' => 'cleaning', 'children' => []],
            ['name' => 'تعمیرات منزل', 'slug' => 'home-repair', 'children' => []],
            ['name' => 'اثاث‌کشی', 'slug' => 'moving', 'children' => []],
        ],
    ],
    [
        'name' => 'ورزش',
        'slug' => 'sports',
        'children' => [
            ['name' => 'باشگاه ورزشی', 'slug' => 'gym', 'children' => []],
            ['name' => 'آموزش ورزشی', 'slug' => 'sports-training', 'children' => []],
        ],
    ],
    ['name' => 'هنر', 'slug' => 'arts', 'children' => []],
    [
        'name' => 'تبلیغات',
        'slug' => 'advertising',
        'children' => [],
    ],
    [
        'name' => 'خدمات شرکتی',
        'slug' => 'corporate-services',
        'children' => [],
    ],
];
