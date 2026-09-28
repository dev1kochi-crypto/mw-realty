<?php

namespace Database\Seeders;

/**
 * A second, more varied batch of Commercial-menu listings (segment = commercial) for the
 * /commercial page and its filters. It covers every purpose (sale / rent / off-plan), six property
 * types, a wide price and size range, and the amenities the filter panel offers. Uses
 * CommercialPropertySeeder's logic, and the COMM reference numbers carry on from the highest
 * existing one, so it can run on top of it.
 *
 *   php artisan db:seed --class=MoreCommercialPropertySeeder
 */
class MoreCommercialPropertySeeder extends CommercialPropertySeeder
{
    protected const LOCATIONS = [
        ['value' => 'business-bay', 'en' => 'Business Bay', 'ar' => 'الخليج التجاري'],
        ['value' => 'downtown-dubai', 'en' => 'Downtown Dubai', 'ar' => 'وسط مدينة دبي'],
        ['value' => 'dubai-marina', 'en' => 'Dubai Marina', 'ar' => 'مرسى دبي'],
        ['value' => 'al-furjan', 'en' => 'Al Furjan', 'ar' => 'الفرجان'],
        ['value' => 'dubai-hills-estate', 'en' => 'Dubai Hills Estate', 'ar' => 'دبي هيلز استيت'],
        ['value' => 'jumeirah-lake-towers', 'en' => 'Jumeirah Lake Towers', 'ar' => 'أبراج بحيرات جميرا'],
        ['value' => 'dubai-investments-park', 'en' => 'Dubai Investments Park', 'ar' => 'مجمع دبي للاستثمار'],
        ['value' => 'jumeirah-village-circle', 'en' => 'Jumeirah Village Circle', 'ar' => 'قرية جميرا الدائرية'],
    ];

    protected const PROPERTY_TYPES = [
        ['value' => 'office', 'en' => 'Office', 'ar' => 'مكتب'],
        ['value' => 'retail-shop', 'en' => 'Retail Shop', 'ar' => 'محل تجاري'],
        ['value' => 'warehouse', 'en' => 'Warehouse', 'ar' => 'مستودع'],
        ['value' => 'showroom', 'en' => 'Showroom', 'ar' => 'صالة عرض'],
        ['value' => 'co-working', 'en' => 'Co-working Space', 'ar' => 'مساحة عمل مشتركة'],
        ['value' => 'whole-building', 'en' => 'Whole Building', 'ar' => 'مبنى كامل'],
    ];

    protected const LISTINGS = [
        ['type' => 'office', 'listing' => 'rent', 'location' => 'jumeirah-lake-towers', 'sqft' => 1450, 'price' => 165000, 'floor' => 18, 'furnished' => true, 'bathrooms' => 2, 'parking' => 2, 'featured' => true,
            'amenities' => ['internet', 'pantry', 'meeting-rooms', 'metro', 'security', 'central-ac'],
            'title_en' => 'Furnished Lake-View Office in JLT Cluster X', 'title_ar' => 'مكتب مفروش بإطلالة على البحيرة في أبراج بحيرات جميرا'],
        ['type' => 'office', 'listing' => 'sale', 'completion' => 'off_plan', 'location' => 'business-bay', 'sqft' => 2600, 'price' => 5400000, 'floor' => 32, 'furnished' => false, 'bathrooms' => 2, 'parking' => 3, 'featured' => false,
            'amenities' => ['reception', 'gym', 'concierge', 'security', 'metro'],
            'title_en' => 'Off-Plan Canal-View Office in Business Bay', 'title_ar' => 'مكتب على الخارطة بإطلالة على القناة في الخليج التجاري'],
        ['type' => 'co-working', 'listing' => 'rent', 'location' => 'downtown-dubai', 'sqft' => 400, 'price' => 60000, 'floor' => 9, 'furnished' => true, 'bathrooms' => 1, 'parking' => 0, 'featured' => false,
            'amenities' => ['internet', 'pantry', 'meeting-rooms', 'reception', 'metro'],
            'title_en' => 'Private Co-working Suite near Dubai Mall', 'title_ar' => 'جناح عمل مشترك خاص قرب دبي مول'],
        ['type' => 'retail-shop', 'listing' => 'rent', 'location' => 'dubai-marina', 'sqft' => 1100, 'price' => 295000, 'floor' => 0, 'furnished' => false, 'bathrooms' => 1, 'parking' => 2, 'featured' => true,
            'amenities' => ['security', 'central-ac', 'metro'],
            'title_en' => 'Waterfront F&B Retail Unit on Marina Promenade', 'title_ar' => 'وحدة تجارية للمطاعم على واجهة مرسى دبي'],
        ['type' => 'retail-shop', 'listing' => 'sale', 'completion' => 'off_plan', 'location' => 'jumeirah-village-circle', 'sqft' => 720, 'price' => 1150000, 'floor' => 0, 'furnished' => false, 'bathrooms' => 1, 'parking' => 1, 'featured' => false,
            'amenities' => ['security', 'central-ac'],
            'title_en' => 'Off-Plan Community Retail Shop in JVC', 'title_ar' => 'محل تجاري على الخارطة في قرية جميرا الدائرية'],
        ['type' => 'warehouse', 'listing' => 'rent', 'location' => 'dubai-investments-park', 'sqft' => 12000, 'price' => 540000, 'floor' => 0, 'furnished' => false, 'bathrooms' => 2, 'parking' => 6, 'featured' => false,
            'amenities' => ['loading-bay', 'security'],
            'title_en' => 'Grade A Warehouse with 3 Loading Docks in DIP', 'title_ar' => 'مستودع فئة أولى مع ثلاثة أرصفة تحميل في مجمع دبي للاستثمار'],
        ['type' => 'warehouse', 'listing' => 'sale', 'location' => 'dubai-investments-park', 'sqft' => 20000, 'price' => 9800000, 'floor' => 0, 'furnished' => false, 'bathrooms' => 3, 'parking' => 10, 'featured' => false,
            'amenities' => ['loading-bay', 'security', 'internet'],
            'title_en' => 'Freehold Industrial Warehouse and Office Block', 'title_ar' => 'مستودع صناعي تملك حر مع مبنى مكاتب'],
        ['type' => 'showroom', 'listing' => 'sale', 'location' => 'al-furjan', 'sqft' => 3200, 'price' => 4250000, 'floor' => 0, 'furnished' => false, 'bathrooms' => 2, 'parking' => 8, 'featured' => false,
            'amenities' => ['security', 'central-ac', 'reception'],
            'title_en' => 'Double-Height Main Road Showroom in Al Furjan', 'title_ar' => 'صالة عرض مزدوجة الارتفاع على الطريق الرئيسي في الفرجان'],
        ['type' => 'showroom', 'listing' => 'rent', 'location' => 'business-bay', 'sqft' => 2800, 'price' => 510000, 'floor' => 0, 'furnished' => true, 'bathrooms' => 2, 'parking' => 4, 'featured' => false,
            'amenities' => ['security', 'central-ac', 'metro', 'reception'],
            'title_en' => 'Fitted Furniture Showroom on Al Khail Road', 'title_ar' => 'صالة عرض أثاث مجهزة على شارع الخيل'],
        ['type' => 'whole-building', 'listing' => 'sale', 'location' => 'jumeirah-village-circle', 'sqft' => 28000, 'price' => 29500000, 'floor' => 0, 'furnished' => false, 'bathrooms' => 12, 'parking' => 40, 'featured' => true,
            'amenities' => ['security', 'gym', 'central-ac', 'concierge'],
            'title_en' => 'Income-Generating Mixed-Use Building in JVC', 'title_ar' => 'مبنى متعدد الاستخدامات مدر للدخل في قرية جميرا الدائرية'],
        ['type' => 'office', 'listing' => 'rent', 'location' => 'dubai-hills-estate', 'sqft' => 700, 'price' => 98000, 'floor' => 3, 'furnished' => false, 'bathrooms' => 1, 'parking' => 1, 'featured' => false,
            'amenities' => ['pantry', 'security', 'central-ac'],
            'title_en' => 'Shell and Core Office in Dubai Hills Business Park', 'title_ar' => 'مكتب غير مجهز في مجمع أعمال دبي هيلز'],
        ['type' => 'co-working', 'listing' => 'rent', 'location' => 'dubai-marina', 'sqft' => 250, 'price' => 38000, 'floor' => 14, 'furnished' => true, 'bathrooms' => 1, 'parking' => 0, 'featured' => false,
            'amenities' => ['internet', 'pantry', 'meeting-rooms', 'gym'],
            'title_en' => 'Dedicated Desk Office with Marina View', 'title_ar' => 'مكتب مخصص بإطلالة على مرسى دبي'],
    ];
}
