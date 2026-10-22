/**
 * Master › Stage / Tag / Source — what differs per type (fields, reorder, paging, wording);
 * MasterIndex.vue is the one screen for all three (Crm\Masters\*Controller).
 */
export const MASTER_TYPES = {
    stages: {
        title: 'Stages',
        singular: 'stage',
        intro: 'Build your lead pipeline — drag to reorder, pick a color per stage, and mark which ones count as closed.',
        hasColor: true,
        hasClosed: true,
        hasDefault: true,
        reorderable: true,
        paged: false,
        responseKey: 'stage',
    },
    tags: {
        title: 'Tags',
        singular: 'tag',
        intro: 'Label leads with tags (VIP, Investor, Cash buyer…) to filter and find them quickly.',
        hasColor: true,
        hasClosed: false,
        hasDefault: false,
        reorderable: false,
        paged: true,
        responseKey: 'tag',
    },
    sources: {
        title: 'Sources',
        singular: 'source',
        intro: 'Where your leads come from — drag to set the order they appear in when you pick one.',
        hasColor: false,
        hasClosed: false,
        hasDefault: false,
        reorderable: true,
        paged: false,
        responseKey: 'source',
    },
};

export const COLOR_PRESETS = ['#4f46e5', '#0ea5e9', '#14b8a6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#64748b'];
