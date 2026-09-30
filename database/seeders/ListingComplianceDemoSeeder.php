<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\PropertyComplianceLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Demo data for Listing Approvals: gives every approved listing without a permit a sample DLD
 * permit (number, expiry, QR, Form A), then moves a few agency / agent listings into each of the
 * other review states. Safe to re-run: it only fills empty permit fields and only assigns states
 * while no listing is in them yet.
 *
 *   php artisan db:seed --class=ListingComplianceDemoSeeder
 *
 * The QR images are SAMPLE drawings (not scannable DLD codes) and the Form A is a sample PDF.
 */
class ListingComplianceDemoSeeder extends Seeder
{
    private const STATES = [
        Property::COMPLIANCE_PENDING => 5,
        Property::COMPLIANCE_DRAFT => 4,
        Property::COMPLIANCE_CHANGES_REQUESTED => 3,
        Property::COMPLIANCE_EXPIRED => 3,
    ];

    private const CHANGE_NOTES = [
        'The permit on DLD is for unit 1204, but the ad says unit 1402. Please check the unit number or upload the correct permit.',
        'The advertised price (AED 2,350,000) does not match the permit (AED 2,200,000). Update the price or renew the permit.',
        'Form A is not signed by the owner. Please upload the signed, RERA-registered Form A.',
    ];

    public function run(): void
    {
        mt_srand(20260930);
        $formA = $this->sampleFormA();
        $filled = 0;

        Property::query()->whereNull('permit_number')->orderBy('id')->chunkById(200, function ($properties) use ($formA, &$filled) {
            foreach ($properties as $property) {
                $permit = '71' . str_pad((string) (10000000 + $property->id * 7919 % 89999999), 8, '0', STR_PAD_LEFT);
                $property->forceFill([
                    'permit_number' => $permit,
                    'permit_expires_at' => today()->addDays(mt_rand(45, 330)),
                    'permit_qr' => $this->sampleQr($permit),
                    'authorization_type' => mt_rand(0, 3) ? 'exclusive' : 'non_exclusive',
                    'authorization_expires_at' => today()->addDays(mt_rand(120, 365)),
                    'authorization_document' => $formA,
                    'title_deed_no' => $property->completion_status === 'off-plan' ? 'OQ-' . mt_rand(100000, 999999) : 'TD-' . mt_rand(1000000, 9999999),
                    'compliance_note' => null,
                    'compliance_reviewed_at' => $property->compliance_reviewed_at ?? now()->subDays(mt_rand(3, 60)),
                    'compliance_submitted_at' => $property->compliance_submitted_at ?? now()->subDays(mt_rand(61, 90)),
                ])->saveQuietly();
                $filled++;
            }
        });
        $this->command?->info("Added sample DLD permits to {$filled} listing(s).");

        if (Property::whereIn('compliance_status', array_keys(self::STATES))->exists()) {
            $this->command?->warn('Some listings are already in a non-approved state — review states left as they are.');
            return;
        }

        // Agency / agent listings only (a house listing is approved on save), never sold ones or the
        // first few in display order, so the home page keeps its listings.
        $candidates = Property::query()->whereNotNull('portal_user_id')->whereNull('sold_at')->where('featured', false)
            ->orderByDesc('id')->limit(array_sum(self::STATES) * 3)->get()->shuffle()->values();

        $offset = 0;
        foreach (self::STATES as $state => $count) {
            foreach ($candidates->slice($offset, $count) as $i => $property) {
                $this->moveTo($property, $state, $i);
            }
            $offset += $count;
        }
        $this->command?->info('Moved ' . array_sum(self::STATES) . ' listing(s) into Pending / Draft / Changes requested / Permit expired.');
    }

    private function moveTo(Property $property, string $state, int $i): void
    {
        $submitted = now()->subHours(mt_rand(2, 96));
        $changes = ['compliance_status' => $state, 'status' => false, 'compliance_reviewed_at' => null, 'compliance_note' => null];
        $logs = [];

        switch ($state) {
            case Property::COMPLIANCE_DRAFT:
                // Saved without the QR and Form A yet.
                $changes = array_merge($changes, ['permit_qr' => null, 'authorization_document' => null, 'compliance_submitted_at' => null]);
                $logs[] = [null, Property::COMPLIANCE_DRAFT, null, $submitted];
                break;
            case Property::COMPLIANCE_PENDING:
                $changes = array_merge($changes, ['compliance_submitted_at' => $submitted]);
                $logs[] = [null, Property::COMPLIANCE_DRAFT, null, $submitted->copy()->subDay()];
                $logs[] = [Property::COMPLIANCE_DRAFT, Property::COMPLIANCE_PENDING, null, $submitted];
                break;
            case Property::COMPLIANCE_CHANGES_REQUESTED:
                $note = self::CHANGE_NOTES[$i % count(self::CHANGE_NOTES)];
                $changes = array_merge($changes, ['compliance_submitted_at' => $submitted, 'compliance_reviewed_at' => $submitted->copy()->addHours(3), 'compliance_note' => $note]);
                $logs[] = [Property::COMPLIANCE_DRAFT, Property::COMPLIANCE_PENDING, null, $submitted];
                $logs[] = [Property::COMPLIANCE_PENDING, Property::COMPLIANCE_CHANGES_REQUESTED, $note, $submitted->copy()->addHours(3), 'admin'];
                break;
            case Property::COMPLIANCE_EXPIRED:
                $expiredOn = today()->subDays(mt_rand(1, 20));
                $note = 'DLD permit expired on ' . $expiredOn->format('d M Y') . '.';
                $changes = array_merge($changes, ['permit_expires_at' => $expiredOn, 'compliance_note' => $note, 'compliance_submitted_at' => $expiredOn->copy()->subMonths(6)]);
                $logs[] = [Property::COMPLIANCE_PENDING, Property::COMPLIANCE_APPROVED, null, $expiredOn->copy()->subMonths(6), 'admin'];
                $logs[] = [Property::COMPLIANCE_APPROVED, Property::COMPLIANCE_EXPIRED, $note, $expiredOn->copy()->addDay()->setTime(0, 5), 'system'];
                break;
        }

        $property->forceFill($changes)->saveQuietly();

        foreach ($logs as $log) {
            [$from, $to, $note, $at] = $log;
            $actor = $log[4] ?? ($property->owner?->isAgency() ? 'agency' : 'agent');
            PropertyComplianceLog::create([
                'property_id' => $property->id,
                'from_status' => $from,
                'to_status' => $to,
                'note' => $note,
                'actor_type' => $actor,
                'actor_id' => in_array($actor, ['agency', 'agent'], true) ? $property->portal_user_id : null,
                'created_at' => $at,
            ]);
        }
    }

    /** One shared sample Form A on the private disk. */
    private function sampleFormA(): string
    {
        $path = 'property-compliance/demo/sample-form-a.pdf';
        if (!Storage::disk('kyc')->exists($path)) {
            $html = '<h2 style="font-family:sans-serif">RERA Form A — SAMPLE</h2>'
                . '<p style="font-family:sans-serif">Agreement between Seller &amp; Broker. Demo document generated for testing Listing Approvals. Not a real contract.</p>';
            Storage::disk('kyc')->put($path, Pdf::loadHTML($html)->output());
        }

        return $path;
    }

    /**
     * A QR-looking SAMPLE image for a permit, drawn with GD (finder squares + a pattern from the
     * permit number, "SAMPLE" underneath). Stored on the public disk.
     */
    private function sampleQr(string $permit): string
    {
        $path = "properties/permits/demo/{$permit}.png";
        if (Storage::disk('public')->exists($path)) {
            return $path;
        }

        $modules = 25; $scale = 6; $margin = 12;
        $size = $modules * $scale + $margin * 2;
        $img = imagecreatetruecolor($size, $size + 16);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 20, 28, 45);
        $grey = imagecolorallocate($img, 120, 128, 150);
        imagefill($img, 0, 0, $white);

        $bits = hash('sha256', $permit) . hash('sha256', strrev($permit)) . hash('sha256', $permit . 'mw');
        $isFinder = fn ($x, $y) => ($x < 8 && $y < 8) || ($x >= $modules - 8 && $y < 8) || ($x < 8 && $y >= $modules - 8);
        $cell = fn ($x, $y) => imagefilledrectangle($img, $margin + $x * $scale, $margin + $y * $scale, $margin + ($x + 1) * $scale - 1, $margin + ($y + 1) * $scale - 1, $black);

        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if (!$isFinder($x, $y) && hexdec($bits[($y * $modules + $x) % strlen($bits)]) % 2) {
                    $cell($x, $y);
                }
            }
        }
        foreach ([[0, 0], [$modules - 7, 0], [0, $modules - 7]] as [$fx, $fy]) {
            for ($y = 0; $y < 7; $y++) {
                for ($x = 0; $x < 7; $x++) {
                    $ring = max(abs($x - 3), abs($y - 3));
                    if ($ring !== 2) {
                        $cell($fx + $x, $fy + $y);
                    }
                }
            }
        }
        imagestring($img, 3, (int) (($size - 6 * 7) / 2), $size - 4, 'SAMPLE', $grey);

        ob_start();
        imagepng($img);
        Storage::disk('public')->put($path, ob_get_clean());

        return $path;
    }
}
