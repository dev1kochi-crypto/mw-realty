/**
 * Properties and Commercial are one set of screens over the same table, split by `segment`
 * (PropertyController). Each menu's labels and route names.
 */
export const SECTIONS = {
    residential: {
        segment: 'residential',
        title: 'Properties',
        itemLabel: 'Property',
        nav: 'properties',
        routes: { index: 'properties.index', create: 'properties.create', show: 'properties.show', edit: 'properties.edit' },
    },
    commercial: {
        segment: 'commercial',
        title: 'Commercial',
        itemLabel: 'Commercial Property',
        nav: 'commercial',
        routes: { index: 'commercial.index', create: 'commercial.create', show: 'commercial.show', edit: 'commercial.edit' },
    },
};

export const sectionFor = (segment) => SECTIONS[segment === 'commercial' ? 'commercial' : 'residential'];

/** 1850000 → "1,850,000" (number_format). */
export const money = (value) => Number(value || 0).toLocaleString('en', { maximumFractionDigits: 0 });
