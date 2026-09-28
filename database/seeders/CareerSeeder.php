<?php

namespace Database\Seeders;

use App\Models\CmsKit\Career;
use App\Models\CmsKit\CareerDepartment;
use App\Models\CmsKit\SectionLabel;
use Illuminate\Database\Seeder;

/**
 * Starter Careers content (English + Arabic): the /careers section header, departments and open
 * vacancies. Safe to re-run: departments match by English title, vacancies by slug, and a
 * section header the admin has already edited is left alone.
 */
class CareerSeeder extends Seeder
{
    public function run(): void
    {
        // The admin's section screen auto-creates an empty row on first visit, so "already exists"
        // isn't enough — only fill it while it still has no content.
        $section = SectionLabel::firstOrNew(['section_key' => 'careers']);
        if (empty($section->translations)) $section->fill([
            'translations' => [
                'en' => [
                    'title' => 'Careers',
                    'description' => 'Join a team of property specialists helping people buy, rent and invest across the UAE. We look for curious, driven people who put clients first — and give them the tools, training and support to grow.',
                    'extra_fields' => [],
                ],
                'ar' => [
                    'title' => 'الوظائف',
                    'description' => 'انضم إلى فريق من المتخصصين العقاريين الذين يساعدون الناس على الشراء والإيجار والاستثمار في الإمارات. نبحث عن أشخاص طموحين يضعون العملاء أولاً، ونمنحهم الأدوات والتدريب والدعم للنمو.',
                    'extra_fields' => [],
                ],
            ],
            'status' => true,
        ])->save();

        $departments = [
            ['en' => 'Sales & Leasing', 'ar' => 'المبيعات والتأجير'],
            ['en' => 'Marketing', 'ar' => 'التسويق'],
            ['en' => 'Operations', 'ar' => 'العمليات'],
        ];

        foreach ($departments as $index => $dept) {
            $existing = CareerDepartment::all()->first(fn ($d) => ($d->translations['en']['title'] ?? null) === $dept['en']);
            ($existing ?? new CareerDepartment())->fill([
                'translations' => [
                    'en' => ['title' => $dept['en'], 'extra_fields' => []],
                    'ar' => ['title' => $dept['ar'], 'extra_fields' => []],
                ],
                'order_index' => $index + 1,
                'status' => true,
            ])->save();
        }

        foreach ($this->vacancies() as $index => $job) {
            Career::updateOrCreate(['slug' => $job['slug']], [
                'job_type' => $job['job_type'],
                'department' => $job['department'],
                'location' => $job['location'],
                'country' => 'UAE',
                'base' => $job['base'],
                'published_date' => $job['published_date'],
                'order_index' => $index + 1,
                'status' => true,
                'translations' => $job['translations'],
            ]);
        }
    }

    private function list(array $items): string
    {
        return '<ul>' . implode('', array_map(fn ($item) => "<li>{$item}</li>", $items)) . '</ul>';
    }

    private function vacancies(): array
    {
        $join = [
            'en' => '<p>Join a fast-growing agency in the heart of Business Bay. You will get a steady flow of qualified leads, our in-house CRM, marketing support for every listing and a transparent, competitive commission structure — plus mentoring from some of the most experienced consultants in the market.</p>',
            'ar' => '<p>انضم إلى وكالة سريعة النمو في قلب الخليج التجاري، مع تدفق مستمر من العملاء المحتملين ونظام CRM داخلي ودعم تسويقي وهيكل عمولات شفاف وتنافسي، إضافة إلى الإرشاد من أكثر المستشارين خبرة.</p>',
        ];

        return [
            [
                'slug' => 'senior-property-consultant',
                'job_type' => 'full_time', 'base' => 'permanent', 'department' => 'Sales & Leasing', 'location' => 'Dubai',
                'published_date' => '2026-09-24',
                'translations' => [
                    'en' => [
                        'title' => 'Senior Property Consultant',
                        'short_description' => 'Advise buyers and investors on premium residential property across Dubai and close high-value deals.',
                        'about' => '<p>We are looking for an experienced property consultant to join our residential sales team. You will manage a portfolio of premium listings and work with local and international clients from first viewing through to transfer.</p>',
                        'responsibilities' => $this->list(['Advise buyers and investors on ready and off-plan residential property', 'Manage listings end to end — valuation, marketing, viewings and negotiation', 'Build and nurture a client pipeline through the MW Realty CRM', 'Coordinate with developers, banks and the Dubai Land Department on transactions']),
                        'requirements' => $this->list(['3+ years of real estate sales experience in the UAE', 'Valid RERA certificate (or ability to obtain one quickly)', 'Strong knowledge of Dubai communities and pricing', 'Excellent English; Arabic, Russian or Hindi is a plus', 'UAE driving licence']),
                        'join_the_team' => $join['en'],
                    ],
                    'ar' => [
                        'title' => 'مستشار عقاري أول',
                        'short_description' => 'تقديم المشورة للمشترين والمستثمرين حول العقارات السكنية الفاخرة في دبي وإتمام صفقات عالية القيمة.',
                        'about' => '<p>نبحث عن مستشار عقاري ذي خبرة للانضمام إلى فريق المبيعات السكنية وإدارة محفظة من العقارات المميزة.</p>',
                        'responsibilities' => $this->list(['تقديم المشورة حول العقارات الجاهزة وعلى الخارطة', 'إدارة العقارات من التقييم حتى التفاوض', 'بناء قاعدة عملاء عبر نظام CRM']),
                        'requirements' => $this->list(['خبرة 3 سنوات أو أكثر في مبيعات العقارات بالإمارات', 'شهادة ريرا سارية', 'إجادة اللغة الإنجليزية']),
                        'join_the_team' => $join['ar'],
                    ],
                ],
            ],
            [
                'slug' => 'leasing-consultant',
                'job_type' => 'full_time', 'base' => 'permanent', 'department' => 'Sales & Leasing', 'location' => 'Dubai',
                'published_date' => '2026-09-18',
                'translations' => [
                    'en' => [
                        'title' => 'Leasing Consultant',
                        'short_description' => 'Match tenants with the right homes and help landlords keep their properties rented.',
                        'about' => '<p>Our leasing team handles a large portfolio of apartments and villas across Dubai. You will be the first point of contact for tenants and landlords, making every move smooth and quick.</p>',
                        'responsibilities' => $this->list(['List rental properties and conduct viewings', 'Negotiate rental terms and prepare tenancy contracts', 'Register contracts through Ejari', 'Handle renewals and maintain strong landlord relationships']),
                        'requirements' => $this->list(['1+ year of leasing experience in the UAE', 'RERA certificate preferred', 'Organised, responsive and client-focused', 'UAE driving licence']),
                        'join_the_team' => $join['en'],
                    ],
                    'ar' => [
                        'title' => 'مستشار تأجير',
                        'short_description' => 'مساعدة المستأجرين في إيجاد المنزل المناسب ومساعدة الملاك في إبقاء عقاراتهم مؤجرة.',
                        'about' => '<p>يدير فريق التأجير لدينا محفظة كبيرة من الشقق والفلل في دبي.</p>',
                        'responsibilities' => $this->list(['عرض العقارات المتاحة للإيجار وتنظيم المعاينات', 'التفاوض على شروط الإيجار وإعداد العقود', 'تسجيل العقود عبر إيجاري']),
                        'requirements' => $this->list(['خبرة سنة على الأقل في التأجير بالإمارات', 'رخصة قيادة إماراتية']),
                        'join_the_team' => $join['ar'],
                    ],
                ],
            ],
            [
                'slug' => 'off-plan-sales-specialist',
                'job_type' => 'full_time', 'base' => 'permanent', 'department' => 'Sales & Leasing', 'location' => 'Abu Dhabi',
                'published_date' => '2026-09-12',
                'translations' => [
                    'en' => [
                        'title' => 'Off-Plan Sales Specialist',
                        'short_description' => 'Represent leading developers\' off-plan launches in Abu Dhabi and guide investors through payment plans.',
                        'about' => '<p>As we expand in Abu Dhabi, we are hiring a specialist to lead off-plan sales on Saadiyat, Yas and Al Reem islands, working directly with top developers on new launches.</p>',
                        'responsibilities' => $this->list(['Present new launches to investors and end-users', 'Explain payment plans, escrow protection and handover timelines', 'Attend launch events and roadshows', 'Maintain relationships with developer sales teams']),
                        'requirements' => $this->list(['2+ years of off-plan sales experience in the UAE', 'Knowledge of the Abu Dhabi market', 'Confident presenter with a strong network', 'ADREC registration or willingness to obtain it']),
                        'join_the_team' => $join['en'],
                    ],
                    'ar' => [
                        'title' => 'أخصائي مبيعات العقارات على الخارطة',
                        'short_description' => 'تمثيل مشاريع كبار المطورين على الخارطة في أبوظبي وإرشاد المستثمرين حول خطط السداد.',
                        'about' => '<p>مع توسعنا في أبوظبي، نبحث عن أخصائي لقيادة مبيعات المشاريع الجديدة في السعديات وياس والريم.</p>',
                        'responsibilities' => $this->list(['عرض المشاريع الجديدة على المستثمرين', 'شرح خطط السداد وحماية حساب الضمان', 'حضور فعاليات الإطلاق']),
                        'requirements' => $this->list(['خبرة سنتين في مبيعات المشاريع على الخارطة', 'معرفة بسوق أبوظبي']),
                        'join_the_team' => $join['ar'],
                    ],
                ],
            ],
            [
                'slug' => 'digital-marketing-executive',
                'job_type' => 'full_time', 'base' => 'permanent', 'department' => 'Marketing', 'location' => 'Dubai',
                'published_date' => '2026-09-08',
                'translations' => [
                    'en' => [
                        'title' => 'Digital Marketing Executive',
                        'short_description' => 'Plan and run paid and organic campaigns that bring qualified property leads to our consultants.',
                        'about' => '<p>You will own MW Realty\'s performance marketing — from Google and Meta campaigns to SEO and email — and work closely with the sales team to turn listings into leads.</p>',
                        'responsibilities' => $this->list(['Plan, launch and optimise Google Ads and Meta campaigns', 'Track lead quality and cost per lead with the sales team', 'Improve website SEO and landing page conversion', 'Report weekly on performance and budget']),
                        'requirements' => $this->list(['2+ years in performance marketing, ideally real estate', 'Hands-on Google Ads, Meta Ads and GA4 experience', 'Strong analytical and copywriting skills']),
                        'join_the_team' => '<p>Work with a supportive, ambitious team, a real marketing budget and the freedom to test new ideas.</p>',
                    ],
                    'ar' => [
                        'title' => 'مسؤول تسويق رقمي',
                        'short_description' => 'تخطيط وتنفيذ الحملات المدفوعة والعضوية التي تجلب عملاء محتملين مؤهلين.',
                        'about' => '<p>ستتولى التسويق الرقمي لـ MW Realty من حملات جوجل وميتا إلى تحسين محركات البحث.</p>',
                        'responsibilities' => $this->list(['إدارة حملات جوجل وميتا وتحسينها', 'متابعة جودة العملاء المحتملين وتكلفتهم', 'تحسين الموقع لمحركات البحث']),
                        'requirements' => $this->list(['خبرة سنتين في التسويق الرقمي', 'خبرة عملية في Google Ads وMeta Ads']),
                        'join_the_team' => '<p>اعمل مع فريق طموح وميزانية تسويق حقيقية وحرية لتجربة أفكار جديدة.</p>',
                    ],
                ],
            ],
            [
                'slug' => 'content-and-social-media-specialist',
                'job_type' => 'part_time', 'base' => 'contract', 'department' => 'Marketing', 'location' => 'Dubai',
                'published_date' => '2026-08-30',
                'translations' => [
                    'en' => [
                        'title' => 'Content & Social Media Specialist',
                        'short_description' => 'Create property videos, reels and posts that showcase our listings and Dubai lifestyle.',
                        'about' => '<p>A part-time contract role for a creative storyteller who can shoot, edit and publish engaging content across Instagram, TikTok, LinkedIn and YouTube.</p>',
                        'responsibilities' => $this->list(['Shoot and edit property tours and short-form video', 'Manage the content calendar across social channels', 'Write captions and community-guide posts', 'Engage with followers and track growth']),
                        'requirements' => $this->list(['A portfolio of social content (real estate or lifestyle)', 'Confident with mobile video and editing tools', 'Available at least three days a week']),
                        'join_the_team' => '<p>Flexible hours, creative freedom and the chance to shape how MW Realty shows up online.</p>',
                    ],
                    'ar' => [
                        'title' => 'أخصائي محتوى ووسائل تواصل اجتماعي',
                        'short_description' => 'إنشاء فيديوهات ومنشورات تعرض عقاراتنا وأسلوب الحياة في دبي.',
                        'about' => '<p>وظيفة بدوام جزئي لصانع محتوى مبدع يستطيع التصوير والمونتاج والنشر عبر المنصات الاجتماعية.</p>',
                        'responsibilities' => $this->list(['تصوير جولات العقارات ومونتاجها', 'إدارة خطة المحتوى', 'كتابة المنشورات والتفاعل مع المتابعين']),
                        'requirements' => $this->list(['معرض أعمال لمحتوى اجتماعي', 'التوفر ثلاثة أيام أسبوعياً على الأقل']),
                        'join_the_team' => '<p>ساعات مرنة وحرية إبداعية.</p>',
                    ],
                ],
            ],
            [
                'slug' => 'property-management-coordinator',
                'job_type' => 'full_time', 'base' => 'permanent', 'department' => 'Operations', 'location' => 'Dubai',
                'published_date' => '2026-08-20',
                'translations' => [
                    'en' => [
                        'title' => 'Property Management Coordinator',
                        'short_description' => 'Keep our managed properties running smoothly for landlords and tenants.',
                        'about' => '<p>Our property management team looks after homes for landlords who live in the UAE and abroad. You will coordinate maintenance, inspections, rent collection and tenant communication.</p>',
                        'responsibilities' => $this->list(['Coordinate maintenance requests with approved contractors', 'Carry out move-in and move-out inspections', 'Track rent payments, cheques and renewals', 'Send monthly landlord statements']),
                        'requirements' => $this->list(['1–3 years in property management or facilities', 'Strong organisation and follow-up', 'Comfortable with spreadsheets and CRM systems', 'UAE driving licence preferred']),
                        'join_the_team' => '<p>A stable, salaried role with clear processes, a friendly team and room to grow into a portfolio manager.</p>',
                    ],
                    'ar' => [
                        'title' => 'منسق إدارة عقارات',
                        'short_description' => 'ضمان سير العقارات المُدارة بسلاسة للملاك والمستأجرين.',
                        'about' => '<p>ستنسق أعمال الصيانة والمعاينات وتحصيل الإيجارات والتواصل مع المستأجرين.</p>',
                        'responsibilities' => $this->list(['تنسيق طلبات الصيانة', 'إجراء معاينات الاستلام والتسليم', 'متابعة الإيجارات والتجديدات']),
                        'requirements' => $this->list(['خبرة 1–3 سنوات في إدارة العقارات', 'مهارات تنظيمية قوية']),
                        'join_the_team' => '<p>وظيفة مستقرة براتب ثابت وفرصة للتطور إلى مدير محفظة.</p>',
                    ],
                ],
            ],
        ];
    }
}
