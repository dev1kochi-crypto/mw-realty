import { computed, reactive } from 'vue';
import http, { errorMessage } from '../../../api/http';

/**
 * State and behaviour of the property form (resources/views/portal/properties/_form) shared by its
 * tab components (provide / inject 'listingForm'): the values, which fields are locked (verified /
 * approved permit), the permit Validate flow, auto-translate between the content languages, the
 * required-field check and the multipart save.
 */

export const TRANSLATED_FIELDS = ['title', 'key_features', 'description', 'address', 'community', 'city', 'country'];
const REQUIRED_TRANSLATED = ['title', 'address', 'city', 'country'];
const META_FIELDS = ['meta_title', 'canonical_url', 'meta_description', 'meta_keywords', 'og_title', 'og_description', 'other_meta_tags'];
const SCALARS = [
    'reference_no', 'rera_id', 'slug', 'listing_type', 'completion_status', 'property_type', 'category', 'location', 'emirate', 'rental_period',
    'postal_code', 'latitude', 'longitude', 'bedrooms', 'bathrooms', 'sqft', 'price', 'currency', 'published_at', 'order_index',
    'permit_type', 'permit_city', 'permit_number', 'permit_expires_at', 'permit_verification_url', 'permit_token', 'agent_id',
    'developer', 'unit_number', 'owner_name', 'video_tour_url', 'cheques', 'year_built', 'floor', 'parking', 'garage', 'furnished',
    'direct_from_owner', 'security_deposit', 'virtual_tour_url', 'view',
];
export const ICON_COLUMNS = { amenity: 'amenities', easy_access: 'easy_access', property_attribute: 'property_attributes' };

/** Which tab (and language sub-tab, for per-language fields) holds a field — for errors and the required check. */
export function tabOf(field) {
    if (field.startsWith('translations.')) {
        const part = field.split('.')[2];
        return ['address', 'community', 'city', 'country'].includes(part) ? 'tab-location' : 'tab-specifications';
    }
    if (field.startsWith('metadata') || ['published_at', 'order_index'].includes(field)) return 'tab-publishing';
    if (field.startsWith('floor_plan')) return 'tab-floorplans';
    if (field.startsWith('images')) return 'tab-images';
    if (field === 'brochure') return 'tab-brochure';
    if (field.startsWith('nearby_places')) return 'tab-nearby';
    if (field === 'agent_id') return 'tab-agent';
    if (field.startsWith('amenities')) return 'tab-amenities';
    if (field.startsWith('easy_access')) return 'tab-easy-access';
    if (field.startsWith('property_attributes')) return 'tab-attributes';
    if (['postal_code', 'latitude', 'longitude'].includes(field)) return 'tab-location';
    if (['bedrooms', 'bathrooms', 'sqft', 'developer', 'unit_number', 'parking', 'garage', 'furnished', 'upgraded', 'year_built', 'floor', 'view',
        'owner_name', 'direct_from_owner', 'virtual_tour_url', 'video_tour_url', 'price', 'currency', 'cheques', 'security_deposit'].includes(field)) return 'tab-specifications';
    return 'tab-basic';
}

export function useListingForm(options, property, segment) {
    const languages = options.languages;
    const langs = languages.map((l) => l.code);
    const values = property?.values ?? {};

    const blankTranslation = () => Object.fromEntries(TRANSLATED_FIELDS.map((f) => [f, '']));
    const translations = Object.fromEntries(langs.map((code) => [code, { ...blankTranslation(), ...(values.translations?.[code] ?? {}) }]));
    Object.values(translations).forEach((t) => TRANSLATED_FIELDS.forEach((f) => { t[f] = t[f] ?? ''; }));

    const form = reactive({
        ...Object.fromEntries(SCALARS.map((key) => [key, values[key] ?? ''])),
        translations,
        status: property ? !!values.status : true,
        upgraded: !!values.upgraded,
        availability: (values.available_dates?.length ? 'from_date' : 'immediately'),
        available_dates: [...(values.available_dates ?? [])],
        amenities: [...(values.amenities ?? [])],
        easy_access: [...(values.easy_access ?? [])],
        property_attributes: [...(values.property_attributes ?? [])],
        metadata: Object.fromEntries(META_FIELDS.map((f) => [f, values.metadata?.[f] ?? ''])),
    });
    if (!property) {
        Object.assign(form, { reference_no: options.reference_no ?? '', emirate: 'dubai', rental_period: 'yearly', currency: 'AED', permit_type: 'rera' });
    }
    ['bedrooms', 'bathrooms', 'sqft', 'order_index', 'cheques', 'year_built', 'parking', 'garage', 'security_deposit', 'agent_id'].forEach((key) => {
        form[key] = form[key] === null || form[key] === undefined ? '' : String(form[key]);
    });
    form.permit_token = '';

    const state = reactive({
        isEdit: !!property,
        segment,
        options,
        property,
        languages,
        langs,
        source: langs[0],
        // The content language picked in the language bar / Description / Location tabs; the dropdowns follow it.
        lang: langs[0],
        tab: 'tab-basic',
        form,
        files: { brochure: null, floor_plan_file: null, metadata_og_image: null, permit_qr: null },
        // Gallery: saved photos ({ number, url }) and newly picked ones ({ file, url }), in display order.
        gallery: (property?.gallery ?? []).map((img) => ({ key: `s${img.number}`, saved: true, number: img.number, url: img.url })),
        floorPlans: (values.floor_plans?.length ? values.floor_plans : (property ? [] : [{}, {}, {}])).map((fp, i) => ({
            key: `fp${i}`, label: fp.label ?? '', size_from: fp.size_from ?? '', size_to: fp.size_to ?? '',
            existing_image: fp.existing_image ?? '', preview: fp.image_url ?? null, file: null,
        })),
        nearby: [...(values.nearby_places ?? [])],
        // Fields the server locked (verified / approved permit) and those locked here after a successful Validate.
        serverLocked: new Set(options.locked_fields ?? []),
        permitLocked: new Set(),
        permitStatus: property?.permit_status ? { state: property.permit_status[0], title: property.permit_status[1], text: property.permit_status[2] } : { state: 'idle', title: 'Ready to validate', text: 'Enter the permit number above.' },
        permitVerified: !!property?.permit_verified,
        permitChecking: false,
        // A verified number is read-only until something that changes the permit resets it.
        permitNumberReadonly: false,
        // Auto-translate: the value each target field was last filled with (a field is "linked" while unchanged).
        autoValues: Object.fromEntries(langs.map((code) => [code, {}])),
        autoFilled: Object.fromEntries(langs.map((code) => [code, {}])),
        autoTranslate: !!options.auto_translate,
        translateStatus: options.auto_translate ? null : { html: '<i class="fas fa-circle-info me-1"></i>Auto-translate isn\'t set up yet — type each language by hand (or ask the site admin to add a Google Translate key).', error: false },
        errors: {},
        // The required field the check just jumped to (red until it's filled in).
        invalid: null,
        banner: '',
        saving: false,
        slugTouched: !!form.slug,
    });

    const isLocked = (field) => state.serverLocked.has(field) || state.permitLocked.has(field);
    const lockTitle = (field) => (state.permitLocked.has(field) ? 'Filled in from the verified permit' : 'Matches the approved DLD permit');
    const isRent = computed(() => form.listing_type === 'rent');
    const fieldError = (field) => state.errors[field]?.[0] ?? null;

    /* ------------------------------------------------------------------ permit */

    // Which permit the emirate needs (PermitRules::resolve).
    const permitType = computed(() => {
        switch (form.emirate) {
            case 'dubai': return form.permit_type || 'rera';
            case 'abu_dhabi': return 'adrec';
            case 'northern_emirates': return form.permit_city === 'al_ain' ? 'adrec' : (form.permit_city === 'other' ? 'not_required' : null);
            default: return null;
        }
    });
    const permitInfo = computed(() => options.permit.types[permitType.value] ?? {});
    const permitShow = computed(() => {
        const type = permitType.value;
        return {
            dubai: form.emirate === 'dubai',
            northern: form.emirate === 'northern_emirates',
            licensed: type === 'rera' || type === 'adrec',
            numbered: ['rera', 'dtcm', 'adrec'].includes(type),
            qr: type === 'rera' || type === 'adrec',
            validates: !!permitInfo.value.validates,
            approval: !!permitInfo.value.needs_approval,
            'no-permit': type === 'none' || type === 'not_required',
        };
    });
    const license = computed(() => options.permit.licenses[permitType.value] ?? null);
    const licenseMissing = computed(() => (options.permit.licenses.missing ?? {})[permitType.value] ?? {});
    // DTCM permits are for holiday homes — rent only.
    const rentOnly = computed(() => permitType.value === 'dtcm');

    function setPermitStatus(stateName, title, text) {
        state.permitStatus = { state: stateName, title, text };
    }

    /** Anything that changes which permit is checked throws away an earlier, unsaved result. */
    function resetVerification() {
        if (!form.permit_token) return;
        form.permit_token = '';
        state.permitVerified = false;
        setPermitStatus('idle', 'Ready to validate', 'Enter the permit number above.');
    }

    function onPermitInputsChanged() {
        resetVerification();
        if (rentOnly.value && !isLocked('listing_type') && form.listing_type === 'sale') form.listing_type = 'rent';
    }

    /** Fill a listing field from the verified permit and lock it (its value is still posted). */
    function lockFromPermit(field, value) {
        if (value === undefined || value === null || value === '') return;
        const select = options.selects[field];
        if (select && !select.some((o) => o.value === String(value))) return; // the permit's value isn't an option here
        form[field] = String(value);
        state.permitLocked.add(field);
    }

    async function validatePermit() {
        const number = String(form.permit_number || '').trim();
        if (!number) {
            state.errors = { ...state.errors, permit_number: ['Enter the permit number.'] };
            return;
        }
        state.permitChecking = true;
        setPermitStatus('checking', 'Checking…', `Asking ${permitType.value === 'adrec' ? 'ADREC' : 'DLD'} about this permit.`);
        try {
            const res = await http.post('/properties/permit/validate', {
                emirate: form.emirate, permit_type: form.permit_type, permit_city: form.permit_city, permit_number: number, property_id: property?.id ?? null,
            });
            const data = res.data;
            if (data.status === 'verified') {
                form.permit_token = data.token || '';
                state.permitVerified = true;
                setPermitStatus('verified', 'Verification successful', data.message || 'Permit verified successfully.');
                const f = data.fields || {};
                ['category', 'listing_type', 'property_type', 'location', 'bedrooms', 'sqft'].forEach((name) => lockFromPermit(name, f[name]));
                if (f.expires_at) form.permit_expires_at = f.expires_at;
                state.permitNumberReadonly = true;
            } else if (data.status === 'invalid') {
                setPermitStatus('error', 'Verification failed', data.message);
            } else {
                setPermitStatus('pending', 'Not verified online', data.message);
            }
        } catch (e) {
            if (e.response?.status === 422) setPermitStatus('error', 'Check the permit number', errorMessage(e, 'This permit number is not valid.'));
            else if (e.response) setPermitStatus('error', 'Could not validate', 'Something went wrong. Please try again.');
            else setPermitStatus('error', 'Could not validate', 'Check your connection and try again.');
        } finally {
            state.permitChecking = false;
        }
    }

    /* ------------------------------------------------------------------ slug */

    function slugify(text) {
        return String(text).trim().toLowerCase().replace(/[^\w\s-]/g, '').replace(/[\s_-]+/g, '-').replace(/^-+|-+$/g, '');
    }
    /** Slug auto-fills from the first language's title as you type — until it's edited by hand. */
    function onTitleInput(code) {
        if (code === langs[0] && !state.slugTouched) form.slug = slugify(form.translations[code].title);
    }

    /* ------------------------------------------------------------------ auto-translate */

    const targets = langs.slice(1);
    let timer = null;
    let inFlight = false;
    let queued = false;
    const sourceName = languages[0]?.name ?? '';

    const valueOf = (code, field) => String(form.translations[code][field] ?? '').trim();
    // Still "linked" to the source: empty, or unchanged since we last filled it.
    const isLinked = (code, field) => {
        const v = valueOf(code, field);
        return v === '' || (state.autoValues[code][field] !== undefined && v === state.autoValues[code][field]);
    };

    /** Per-language completeness dot: done / partial / empty. */
    function langState(code) {
        const filled = REQUIRED_TRANSLATED.filter((f) => valueOf(code, f) !== '').length;
        return filled === REQUIRED_TRANSLATED.length ? 'done' : (filled ? 'partial' : '');
    }

    async function translate(force) {
        if (!options.auto_translate) {
            state.translateStatus = { html: '<i class="fas fa-circle-info me-1"></i>Auto-translate isn\'t set up yet — type each language by hand (or ask the site admin to add a Google Translate key).', error: true };
            return;
        }
        if (inFlight) { queued = true; return; }

        // Per target: the source fields whose target is still linked (or everything, when forced).
        const sourceValues = {};
        TRANSLATED_FIELDS.forEach((f) => { if (valueOf(state.source, f) !== '') sourceValues[f] = valueOf(state.source, f); });
        const wanted = {};
        targets.forEach((t) => Object.keys(sourceValues).forEach((f) => { if (force || isLinked(t, f)) (wanted[f] = wanted[f] || []).push(t); }));
        const fields = Object.fromEntries(Object.keys(wanted).map((f) => [f, sourceValues[f]]));
        if (!Object.keys(fields).length) {
            if (force) state.translateStatus = { html: `<i class="fas fa-circle-check text-success me-1"></i>Nothing to translate yet — fill in the ${sourceName} fields first.`, error: false };
            return;
        }

        inFlight = true;
        state.translateStatus = { html: `<i class="fas fa-spinner fa-spin me-1"></i>Translating from ${sourceName}…`, error: false };
        try {
            const res = await http.post('/properties/translate', { source: state.source, targets, fields });
            let count = 0;
            Object.keys(wanted).forEach((f) => wanted[f].forEach((t) => {
                const value = res.data.translations?.[t]?.[f];
                // Skip if the user typed into it while the request was running.
                if (value && (force || isLinked(t, f))) {
                    form.translations[t][f] = value;
                    state.autoValues[t][f] = String(value).trim();
                    state.autoFilled[t][f] = true;
                    if (t === langs[0] && f === 'title') onTitleInput(t);
                    count++;
                }
            }));
            const names = targets.map((t) => languages.find((l) => l.code === t)?.name).join(', ');
            state.translateStatus = count ? { html: `<i class="fas fa-circle-check text-success me-1"></i>Auto-filled ${names} from ${sourceName}. You can edit any field by hand — edited fields won't be overwritten.`, error: false } : null;
        } catch (e) {
            state.translateStatus = { html: `<i class="fas fa-triangle-exclamation me-1"></i>${errorMessage(e, 'Translation failed — please try again.')}`, error: true };
        } finally {
            inFlight = false;
            if (queued) { queued = false; schedule(); }
        }
    }

    function schedule() {
        if (!state.autoTranslate) return;
        clearTimeout(timer);
        timer = setTimeout(() => translate(false), 1200);
    }

    /** A translated field was typed in: by hand on the source → translate; on a target → it's no longer auto-filled. */
    function onTranslatedInput(code, field) {
        if (code === state.source) schedule();
        else state.autoFilled[code][field] = false;
        if (field === 'title') onTitleInput(code);
    }

    /** Some target already edited by hand (Translate now asks whether to overwrite those). */
    const anyManualTargets = () => targets.some((t) => TRANSLATED_FIELDS.some((f) => valueOf(state.source, f) !== '' && !isLinked(t, f)));

    /* ------------------------------------------------------------------ required fields */

    // Native `required` can't work across hidden tabs, so the form checks these itself and jumps to
    // the tab (and language sub-tab) holding the first missing value.
    const GLOBAL_REQUIRED = [
        ['emirate', 'tab-basic', 'Emirate', 'select'],
        ['slug', 'tab-basic', 'Slug'],
        ['property_type', 'tab-basic', 'Property Type', 'select'],
        ['category', 'tab-basic', 'Category', 'radio'],
        ['listing_type', 'tab-basic', 'Offering type', 'radio'],
        ['location', 'tab-basic', 'Property location', 'select'],
        ['price', 'tab-specifications', 'Price'],
        ['currency', 'tab-specifications', 'Currency'],
        ['latitude', 'tab-location', 'Latitude'],
        ['longitude', 'tab-location', 'Longitude'],
    ];
    const LANG_REQUIRED = [['title', 'tab-specifications', 'Title'], ['address', 'tab-location', 'Address'], ['city', 'tab-location', 'City'], ['country', 'tab-location', 'Country']];

    function missingRequired() {
        const missing = [];
        GLOBAL_REQUIRED.forEach(([name, tab, label, kind]) => {
            // A dropdown with no options configured is a free-text input in the Blade form; only check what's shown.
            if (String(form[name] ?? '').trim() === '') missing.push({ name, tab, label, kind });
        });
        langs.forEach((code) => LANG_REQUIRED.forEach(([field, tab, label]) => {
            if (valueOf(code, field) === '') missing.push({ name: `translations.${code}.${field}`, tab, label: `${label} (${code.toUpperCase()})`, lang: code });
        }));
        return missing;
    }

    /* ------------------------------------------------------------------ save */

    function formData() {
        const body = new FormData();
        const put = (key, value) => body.append(key, value === null || value === undefined ? '' : value);
        SCALARS.forEach((key) => put(key, form[key]));
        put('status', form.status ? 1 : 0);
        put('upgraded', form.upgraded ? 1 : 0);
        put('availability', form.availability);
        if (form.availability === 'from_date') form.available_dates.forEach((d) => put('available_dates[]', d));
        langs.forEach((code) => TRANSLATED_FIELDS.forEach((f) => put(`translations[${code}][${f}]`, form.translations[code][f])));
        Object.values(ICON_COLUMNS).forEach((column) => form[column].forEach((v) => put(`${column}[]`, v)));
        META_FIELDS.forEach((f) => put(`metadata[${f}]`, form.metadata[f]));
        state.nearby.forEach((place) => put('nearby_places[]', place.id));
        state.floorPlans.forEach((row, i) => {
            put(`floor_plans[${i}][label]`, row.label);
            put(`floor_plans[${i}][size_from]`, row.size_from);
            put(`floor_plans[${i}][size_to]`, row.size_to);
            put(`floor_plans[${i}][existing_image]`, row.existing_image);
            if (row.file) body.append(`floor_plans[${i}][image]`, row.file);
        });
        state.gallery.filter((img) => !img.saved).forEach((img) => body.append('images[]', img.file));
        Object.entries({ brochure: 'brochure', floor_plan_file: 'floor_plan_file', metadata_og_image: 'metadata_og_image', permit_qr: 'permit_qr' })
            .forEach(([key, name]) => { if (state.files[key]) body.append(name, state.files[key]); });
        if (property) put('_method', 'PUT');
        else put('segment', segment);
        return body;
    }

    function save() {
        state.saving = true;
        state.errors = {};
        return http.post(property ? `/properties/${property.id}` : '/properties', formData())
            .then((res) => res.data)
            .catch((e) => {
                if (e.response?.status === 422 && e.response.data?.errors) {
                    state.errors = e.response.data.errors;
                }
                throw e;
            })
            .finally(() => {
                state.saving = false;
            });
    }

    return {
        state, form, isLocked, lockTitle, isRent, fieldError,
        permitType, permitInfo, permitShow, license, licenseMissing, rentOnly, validatePermit, onPermitInputsChanged, resetVerification,
        onTitleInput, onTranslatedInput, translate, schedule, langState, anyManualTargets,
        missingRequired, save,
    };
}
