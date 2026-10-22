<?php

namespace App\Support\ApiDocs;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\Strategy;

/**
 * Mobile app API docs (config/scribe_app_*.php): files each endpoint under the app tab / screen
 * that calls it instead of its controller's @group, and adds the endpoints a tab also calls but
 * that are documented under another tab ("connected").
 *
 * The screen map is the config's own `app_screens` key. Each tab:
 *   'group' => tab name, 'description' => markdown,
 *   'screens' => [screen name => ['METHOD /api/uri', ...]],   // documented here
 *   'also' => ['METHOD /api/uri', ...],                         // documented under another tab
 */
class AppScreens extends Strategy
{
    public function __invoke(ExtractedEndpointData $endpointData, array $settings = []): ?array
    {
        $tabs = $this->config->get('app_screens', []);

        foreach ($endpointData->httpMethods as $method) {
            // The route's own uri: Scribe renames bound parameters ({property} → {property_id}).
            $key = $method . ' /' . $endpointData->route->uri();

            foreach ($tabs as $tab) {
                foreach ($tab['screens'] as $screen => $endpoints) {
                    if (in_array($key, $endpoints, true)) {
                        return [
                            'groupName' => $tab['group'],
                            'groupDescription' => self::describe($tab, $tabs),
                            'subgroup' => $screen,
                            'subgroupDescription' => '',
                        ];
                    }
                }
            }
        }

        return null;
    }

    private static function describe(array $tab, array $tabs): string
    {
        $text = trim($tab['description'] ?? '');
        if (empty($tab['also'])) {
            return $text;
        }

        $lines = collect($tab['also'])->map(function ($endpoint) use ($tabs) {
            $home = collect($tabs)->first(fn ($t) => in_array($endpoint, collect($t['screens'])->flatten()->all(), true));

            return '* `' . $endpoint . '`' . ($home ? ' — see **' . $home['group'] . '**' : '');
        });

        return $text . "\n\n**Also uses (connected):**\n\n" . $lines->implode("\n");
    }
}
