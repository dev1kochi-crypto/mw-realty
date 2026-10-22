<?php

namespace Database\Seeders;

use App\Models\CalculatorPage;
use App\Models\CmsKit\Faq;
use App\Models\CmsKit\Metadata;
use Illuminate\Database\Seeder;

/**
 * Starting content (English + Arabic) for Admin › Calculators: page copy, enquiry-form copy,
 * FAQs, refinance settings and the pages' SEO rows. Safe to re-run — it only fills in what is
 * missing and never overwrites what an admin has edited.
 */
class CalculatorPageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $order => $page) {
            $calculator = CalculatorPage::firstOrCreate(['key' => $page['key']], [
                'translations' => $page['translations'],
                'settings' => $page['settings'] ?? null,
                'order_index' => $order + 1,
                'status' => true,
            ]);

            if (!$calculator->faqs()->exists()) {
                foreach ($page['faqs'] as $i => [$en, $ar]) {
                    $calculator->faqs()->create([
                        'translations' => [
                            'en' => ['question' => $en[0], 'answer' => $en[1]],
                            'ar' => ['question' => $ar[0], 'answer' => $ar[1]],
                        ],
                        'order_index' => $i + 1,
                        'status' => true,
                    ]);
                }
            }

            $seo = config("seo-defaults.{$calculator->page_key}", []);
            Metadata::firstOrCreate(['page_key' => $calculator->page_key], [
                'page_name' => ['en' => $page['translations']['en']['title'], 'ar' => $page['translations']['ar']['title']],
                'meta_title' => ['en' => $seo['meta_title'] ?? null, 'ar' => $page['seo_ar'][0]],
                'meta_description' => ['en' => $seo['meta_description'] ?? null, 'ar' => $page['seo_ar'][1]],
            ]);
        }
    }

    public static function lenders(): array
    {
        return [
            'Abu Dhabi Commercial Bank (ADCB)', 'Abu Dhabi Islamic Bank (ADIB)', 'Ajman Bank', 'Al Hilal Bank', 'Arab Bank',
            'Commercial Bank of Dubai (CBD)', 'Dubai Islamic Bank (DIB)', 'Emirates Islamic', 'Emirates NBD',
            'First Abu Dhabi Bank (FAB)', 'FAB Islamic', 'HSBC', 'Mashreq', 'National Bank of Fujairah (NBF)', 'RAKBANK', 'Sharjah Islamic Bank',
            'Standard Chartered', 'United Arab Bank', 'Other',
        ];
    }

    private function pages(): array
    {
        $points = "Rates compared across leading UAE banks\nHelp with pre-approval and paperwork\nNo cost and no obligation";
        $pointsAr = "مقارنة الأسعار لدى أبرز البنوك في الإمارات\nمساعدة في الموافقة المبدئية والأوراق المطلوبة\nبدون أي تكلفة أو التزام";
        $formText = 'Share your details and one of our mortgage advisors will compare offers from UAE banks for you — your calculation is sent along, so there is nothing to re-enter.';
        $formTextAr = 'شارك بياناتك وسيقارن لك أحد مستشاري التمويل العقاري لدينا عروض البنوك في الإمارات — نرسل حساباتك معها، فلا داعي لإدخالها مرة أخرى.';

        return [
            [
                'key' => 'mortgage',
                'translations' => [
                    'en' => [
                        'title' => 'Mortgage Calculator',
                        'heading' => 'Estimate Your Monthly Payments',
                        'lead' => 'Adjust the property price, down payment, interest rate and loan term to see what your home loan could cost each month, plus the upfront fees to budget for in the UAE.',
                        'form_eyebrow' => 'Free mortgage advice', 'form_title' => 'Get a mortgage quote', 'form_text' => $formText, 'form_points' => $points,
                        'faq_title' => 'Mortgage calculator FAQs',
                    ],
                    'ar' => [
                        'title' => 'حاسبة التمويل العقاري',
                        'heading' => 'احسب أقساطك الشهرية',
                        'lead' => 'غيّر سعر العقار والدفعة الأولى وسعر الفائدة ومدة القرض لترى تكلفة تمويلك العقاري شهرياً، بالإضافة إلى الرسوم المقدمة التي يجب أن تحسب حسابها في الإمارات.',
                        'form_eyebrow' => 'استشارة تمويل مجانية', 'form_title' => 'احصل على عرض تمويل عقاري', 'form_text' => $formTextAr, 'form_points' => $pointsAr,
                        'faq_title' => 'الأسئلة الشائعة عن حاسبة التمويل العقاري',
                    ],
                ],
                'seo_ar' => ['حاسبة التمويل العقاري في الإمارات | MW Realty', 'احسب أقساط تمويلك العقاري الشهرية والتكاليف المقدمة في الإمارات — غيّر سعر العقار والدفعة الأولى والفائدة ومدة القرض.'],
                'faqs' => [
                    [['How is my monthly payment calculated?', 'We use the standard loan formula: the loan amount (property price minus your down payment), the interest rate and the loan term give a fixed monthly instalment of principal and interest.'],
                     ['كيف يتم حساب قسطي الشهري؟', 'نستخدم معادلة القرض المعتادة: مبلغ القرض (سعر العقار ناقص الدفعة الأولى) وسعر الفائدة ومدة القرض تعطي قسطاً شهرياً ثابتاً من أصل القرض والفائدة.']],
                    [['What is the minimum down payment in the UAE?', 'For a first home up to AED 5 million, UAE nationals need 15%, residents 20% and non-residents 40%. Above AED 5 million, or for a second home, the minimum is higher.'],
                     ['ما هي الدفعة الأولى الدنيا في الإمارات؟', 'للمسكن الأول حتى 5 ملايين درهم: 15% للمواطنين و20% للمقيمين و40% لغير المقيمين. وتكون النسبة أعلى للعقارات فوق 5 ملايين درهم أو للمسكن الثاني.']],
                    [['What upfront costs should I budget for?', 'Besides the down payment, plan for the 4% Dubai Land Department fee, the agency fee (usually 2% + VAT), mortgage registration (0.25% of the loan), bank and valuation fees, and the trustee fee.'],
                     ['ما التكاليف المقدمة التي يجب أن أحسب حسابها؟', 'إلى جانب الدفعة الأولى، احسب رسوم دائرة الأراضي والأملاك 4%، وعمولة الوسيط (عادة 2% + ضريبة القيمة المضافة)، وتسجيل الرهن (0.25% من القرض)، ورسوم البنك والتقييم، ورسوم أمين التسجيل.']],
                    [['How long can a UAE mortgage be?', 'The UAE Central Bank caps home loans at 25 years, and the loan must usually end by age 65 for salaried and 70 for self-employed borrowers.'],
                     ['ما أقصى مدة للتمويل العقاري في الإمارات؟', 'يحدد المصرف المركزي مدة التمويل العقاري بحد أقصى 25 سنة، ويجب عادة أن ينتهي القرض قبل سن 65 للموظفين و70 لأصحاب الأعمال.']],
                    [['Is the result a loan offer?', 'No. The figures are estimates to help you plan. Your actual rate and fees depend on the bank and your profile — our advisors can get you real offers.'],
                     ['هل النتيجة عرض تمويل؟', 'لا. الأرقام تقديرية لمساعدتك في التخطيط. يعتمد سعر الفائدة والرسوم الفعلية على البنك وملفك — ويمكن لمستشارينا الحصول لك على عروض فعلية.']],
                ],
            ],
            [
                'key' => 'eligibility',
                'translations' => [
                    'en' => [
                        'title' => 'Eligibility Calculator',
                        'heading' => 'How Much Can You Borrow?',
                        'lead' => 'Find the most a UAE bank is likely to lend you and the highest property price you can aim for, based on your income, existing debts and the Central Bank’s 50% debt burden rule.',
                        'form_eyebrow' => 'Free mortgage advice', 'form_title' => 'Get pre-approved for a mortgage', 'form_text' => $formText, 'form_points' => $points,
                        'faq_title' => 'Eligibility calculator FAQs',
                    ],
                    'ar' => [
                        'title' => 'حاسبة الأهلية',
                        'heading' => 'كم يمكنك أن تقترض؟',
                        'lead' => 'اعرف أقصى مبلغ يُرجّح أن يقرضك إياه بنك في الإمارات وأعلى سعر عقار يمكنك استهدافه، بناءً على دخلك والتزاماتك الحالية وقاعدة عبء الدين 50% للمصرف المركزي.',
                        'form_eyebrow' => 'استشارة تمويل مجانية', 'form_title' => 'احصل على موافقة مبدئية للتمويل', 'form_text' => $formTextAr, 'form_points' => $pointsAr,
                        'faq_title' => 'الأسئلة الشائعة عن حاسبة الأهلية',
                    ],
                ],
                'seo_ar' => ['حاسبة أهلية التمويل العقاري في الإمارات | MW Realty', 'اعرف كم يمكن أن يقرضك بنك في الإمارات وسعر العقار الذي يمكنك تحمله، بناءً على دخلك والتزاماتك وإقامتك.'],
                'faqs' => [
                    [['What is the debt burden ratio (DBR)?', 'UAE banks may not let all your monthly debt repayments — including the new mortgage — exceed 50% of your monthly income. This is the main limit on how much you can borrow.'],
                     ['ما هي نسبة عبء الدين؟', 'لا يسمح للبنوك في الإمارات بأن تتجاوز جميع أقساطك الشهرية — بما فيها التمويل الجديد — 50% من دخلك الشهري. وهذا هو الحد الأساسي لمبلغ الاقتراض.']],
                    [['Why do my credit cards count even if I do not use them?', 'Banks treat 5% of every card limit as a monthly debt, whether or not you have a balance. Lowering unused limits can increase what you can borrow.'],
                     ['لماذا تُحتسب بطاقاتي الائتمانية حتى إن لم أستخدمها؟', 'تعتبر البنوك 5% من حد كل بطاقة ديناً شهرياً سواء كان عليها رصيد أم لا. تخفيض الحدود غير المستخدمة قد يزيد المبلغ الذي يمكنك اقتراضه.']],
                    [['Does residency affect my eligibility?', 'Yes. It changes the minimum down payment (15% nationals, 20% residents, 40% non-residents for a first home up to AED 5M) and so the highest property price you can buy.'],
                     ['هل تؤثر الإقامة على أهليتي؟', 'نعم. فهي تغير الدفعة الأولى الدنيا (15% للمواطنين و20% للمقيمين و40% لغير المقيمين للمسكن الأول حتى 5 ملايين درهم) وبالتالي أعلى سعر عقار يمكنك شراؤه.']],
                    [['What is a pre-approval?', 'A pre-approval is a bank’s written estimate of how much it will lend you, usually valid for 60–90 days. It shows sellers you are a serious buyer.'],
                     ['ما هي الموافقة المبدئية؟', 'الموافقة المبدئية تقدير مكتوب من البنك للمبلغ الذي سيقرضك إياه، وتكون صالحة عادة من 60 إلى 90 يوماً، وتُظهر للبائعين أنك مشترٍ جاد.']],
                ],
            ],
            [
                'key' => 'rent-vs-buy',
                'translations' => [
                    'en' => [
                        'title' => 'Rent vs Buy Calculator',
                        'heading' => 'Should You Rent or Buy?',
                        'lead' => 'Compare the real cost of renting against buying over the years you plan to stay — including fees, insurance, service charges and what the home is worth when you sell.',
                        'form_eyebrow' => 'Free mortgage advice', 'form_title' => 'Ready to buy? Talk to an advisor', 'form_text' => $formText, 'form_points' => $points,
                        'faq_title' => 'Rent vs buy FAQs',
                    ],
                    'ar' => [
                        'title' => 'حاسبة الإيجار أم الشراء',
                        'heading' => 'هل تستأجر أم تشتري؟',
                        'lead' => 'قارن التكلفة الحقيقية للإيجار مقابل الشراء خلال السنوات التي تنوي البقاء فيها — بما في ذلك الرسوم والتأمين ورسوم الخدمات وقيمة المنزل عند البيع.',
                        'form_eyebrow' => 'استشارة تمويل مجانية', 'form_title' => 'مستعد للشراء؟ تحدث إلى مستشار', 'form_text' => $formTextAr, 'form_points' => $pointsAr,
                        'faq_title' => 'الأسئلة الشائعة عن الإيجار أم الشراء',
                    ],
                ],
                'seo_ar' => ['حاسبة الإيجار أم الشراء في الإمارات | MW Realty', 'قارن التكلفة الحقيقية للإيجار مقابل شراء منزل في الإمارات — الإيجار ورسوم الوسيط وإيجاري مقابل التمويل ورسوم الشراء والتأمين ورسوم الخدمات وسعر البيع — واعرف متى يصبح الشراء أفضل.'],
                'faqs' => [
                    [['What does "Saved if buying" mean?', 'It is the net cost of renting minus the net cost of buying over the loan period. Buying’s net cost counts every payment and fee, minus what you get back when you sell the home.'],
                     ['ماذا يعني "التوفير عند الشراء"؟', 'هو صافي تكلفة الإيجار ناقص صافي تكلفة الشراء خلال مدة القرض. ويحتسب صافي تكلفة الشراء كل الدفعات والرسوم ناقص ما تسترده عند بيع المنزل.']],
                    [['Which renting costs are included?', 'Rent, the agent commission each time you move (5% of the yearly rent + VAT, assuming a move every 3 years), and the yearly Ejari registration and cancellation fees.'],
                     ['ما تكاليف الإيجار المحتسبة؟', 'الإيجار، وعمولة الوسيط عند كل انتقال (5% من الإيجار السنوي + ضريبة القيمة المضافة، بافتراض الانتقال كل 3 سنوات)، ورسوم تسجيل وإلغاء إيجاري السنوية.']],
                    [['Which buying costs are included?', 'Down payment, Land Department and trustee fees, agency fee, bank fees, mortgage payments, life and property insurance, community service charges, and the selling fees at the end.'],
                     ['ما تكاليف الشراء المحتسبة؟', 'الدفعة الأولى، ورسوم دائرة الأراضي وأمين التسجيل، وعمولة الوسيط، ورسوم البنك، وأقساط التمويل، وتأمين الحياة والعقار، ورسوم الخدمات، ورسوم البيع في النهاية.']],
                    [['When does buying become better than renting?', 'The break-even year is the first year in which selling the home would leave you better off than if you had rented. The longer you stay, the more buying usually pays off.'],
                     ['متى يصبح الشراء أفضل من الإيجار؟', 'سنة التعادل هي أول سنة يتركك فيها بيع المنزل في وضع أفضل مما لو كنت استأجرت. وكلما طالت مدة بقائك، زادت عادة فائدة الشراء.']],
                ],
            ],
            [
                'key' => 'refinance',
                'translations' => [
                    'en' => [
                        'title' => 'Refinance Calculator',
                        'heading' => 'Refinance Your Mortgage',
                        'lead' => 'Refinance now to lower your monthly payments and lock in better rates. Tell us about your current loan, your job and what you need — we will show how much you could save.',
                        'form_eyebrow' => 'Free refinance advice', 'form_title' => 'Find a better mortgage rate', 'form_text' => $formText, 'form_points' => $points,
                        'faq_title' => 'Refinancing FAQs',
                    ],
                    'ar' => [
                        'title' => 'حاسبة إعادة التمويل',
                        'heading' => 'أعد تمويل قرضك العقاري',
                        'lead' => 'أعد التمويل الآن لتخفيض أقساطك الشهرية والحصول على أسعار أفضل. أخبرنا عن قرضك الحالي وعملك وما تحتاجه — وسنريك كم يمكنك أن توفر.',
                        'form_eyebrow' => 'استشارة إعادة تمويل مجانية', 'form_title' => 'احصل على سعر تمويل أفضل', 'form_text' => $formTextAr, 'form_points' => $pointsAr,
                        'faq_title' => 'الأسئلة الشائعة عن إعادة التمويل',
                    ],
                ],
                'settings' => ['lenders' => self::lenders()],
                'seo_ar' => ['حاسبة إعادة التمويل العقاري في الإمارات | MW Realty', 'أعد تمويل قرضك العقاري في الإمارات: أدخل بيانات قرضك الحالي وعملك والمبلغ الإضافي المطلوب لترى سعرك الجديد وقسطك الشهري وكم يمكنك أن توفر شهرياً وطوال مدة القرض.'],
                'faqs' => [
                    [['What is mortgage refinancing?', 'Refinancing means moving your existing home loan to a new loan — usually with another bank — to get a lower rate, a lower monthly payment, or to release some of your home’s equity as cash.'],
                     ['ما هي إعادة التمويل العقاري؟', 'إعادة التمويل تعني نقل قرضك العقاري الحالي إلى قرض جديد — عادة لدى بنك آخر — للحصول على سعر أقل أو قسط شهري أقل أو للإفراج عن جزء من قيمة منزلك نقداً.']],
                    [['What does it cost to refinance in the UAE?', 'Your current bank can charge an early settlement fee of up to 1% of the balance, capped at AED 10,000. You also pay the new mortgage registration fee (0.25% of the loan) and a property valuation fee.'],
                     ['ما تكلفة إعادة التمويل في الإمارات؟', 'قد يفرض بنكك الحالي رسوم تسوية مبكرة تصل إلى 1% من الرصيد بحد أقصى 10,000 درهم. وتدفع أيضاً رسوم تسجيل الرهن الجديد (0.25% من القرض) ورسوم تقييم العقار.']],
                    [['What is equity release?', 'If your home is worth more than you owe, you can borrow extra against it when you refinance. Banks usually lend up to 80% of the current property value in total.'],
                     ['ما هو الإفراج عن حقوق الملكية؟', 'إذا كانت قيمة منزلك أكبر مما تدين به، يمكنك اقتراض مبلغ إضافي بضمانه عند إعادة التمويل. وتقرض البنوك عادة حتى 80% من القيمة الحالية للعقار إجمالاً.']],
                    [['When is a good time to refinance?', 'Often when your fixed-rate period ends and the rate reverts to a higher variable rate, or when market rates drop well below what you are paying.'],
                     ['متى يكون الوقت مناسباً لإعادة التمويل؟', 'غالباً عند انتهاء فترة السعر الثابت وتحول السعر إلى سعر متغير أعلى، أو عندما تنخفض أسعار السوق كثيراً عما تدفعه.']],
                    [['Why do you ask for my age and employment?', 'Banks require the loan to end by age 65 for salaried and 70 for self-employed borrowers, and your income sets the most you can borrow under the 50% debt burden rule.'],
                     ['لماذا تسألون عن عمري وعملي؟', 'تشترط البنوك أن ينتهي القرض قبل سن 65 للموظفين و70 لأصحاب الأعمال، ويحدد دخلك أقصى مبلغ يمكنك اقتراضه وفق قاعدة عبء الدين 50%.']],
                ],
            ],
        ];
    }
}
