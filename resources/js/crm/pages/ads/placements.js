/** Icon for each website page an ad can run on (Ad::PLACEMENTS keys). */
const ICONS = {
    home: 'fa-house', commercial: 'fa-store', agents: 'fa-user-tie', 'agent-details': 'fa-id-card', agencies: 'fa-building-user',
    'agency-details': 'fa-building', blogs: 'fa-newspaper', 'blog-details': 'fa-file-lines', 'properties-dubai': 'fa-list',
    'premium-properties': 'fa-star', 'marketing-properties': 'fa-bullhorn', 'property-details': 'fa-house-chimney',
    'market-insights': 'fa-chart-line', 'terms-and-conditions': 'fa-scale-balanced',
};

export const placementIcon = (key) => ICONS[key] ?? 'fa-window-maximize';
