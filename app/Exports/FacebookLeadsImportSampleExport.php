<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Sample of the CSV Meta's Leads Center / Ads Manager "Download leads" produces — the format the
 * "Facebook leads" import reads (LeadsImport::FORMAT_FACEBOOK). Columns between "platform" and
 * "email" are the lead form's own questions, so they differ per form; any number of them is fine.
 */
class FacebookLeadsImportSampleExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['id', 'created_time', 'ad_id', 'ad_name', 'adset_id', 'adset_name', 'campaign_id', 'campaign_name', 'form_id', 'form_name',
            'is_organic', 'platform', 'what_type_of_property_are_you_looking_for?', 'when_are_you_planning_to_buy?', 'email', 'full_name', 'phone_number'];
    }

    public function array(): array
    {
        return [
            ['l:1234567890123456', '2026-09-10T05:07:46+04:00', 'ag:120210000000000001', 'Marina Apartments', 'as:120210000000000002', 'Dubai Marina - Buyers',
                'c:120210000000000003', 'Marina Launch Sep 26', 'f:1234567890', 'Marina Enquiry Form', 'false', 'fb', 'apartment', 'within_3_months',
                'jane@example.com', 'Jane Doe', 'p:+971500000000'],
            ['l:1234567890123457', '2026-09-11T10:15:02+04:00', 'ag:120210000000000001', 'Marina Apartments', 'as:120210000000000002', 'Dubai Marina - Buyers',
                'c:120210000000000003', 'Marina Launch Sep 26', 'f:1234567890', 'Marina Enquiry Form', 'false', 'ig', 'villa', 'just_checking_options',
                'john@example.com', 'John Smith', 'p:+971550000000'],
        ];
    }
}
