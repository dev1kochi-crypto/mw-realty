import { computed, onBeforeUnmount, reactive } from 'vue';

/**
 * The banner / phone image of an ad form: picked files get an object URL for the preview, the
 * saved ad's images show until replaced. `form` holds image, mobile_image, remove_mobile_image;
 * `existing` is a ref to the saved ad (image_url, mobile_image_url) or null.
 */
export function useAdImages(form, existing) {
    const objectUrls = reactive({ image: null, mobile_image: null });

    function pick(event, field) {
        const file = event.target.files?.[0];
        if (!file) return;
        if (objectUrls[field]) URL.revokeObjectURL(objectUrls[field]);
        objectUrls[field] = URL.createObjectURL(file);
        form[field] = file;
        if (field === 'mobile_image') form.remove_mobile_image = false;
        event.target.value = '';
    }

    function removeMobile() {
        if (objectUrls.mobile_image) URL.revokeObjectURL(objectUrls.mobile_image);
        objectUrls.mobile_image = null;
        form.mobile_image = null;
        form.remove_mobile_image = true;
    }

    onBeforeUnmount(() => Object.values(objectUrls).forEach((url) => url && URL.revokeObjectURL(url)));

    const imageUrl = computed(() => objectUrls.image || existing.value?.image_url || null);
    const mobileUrl = computed(() => objectUrls.mobile_image || (form.remove_mobile_image ? null : existing.value?.mobile_image_url) || null);

    return { pick, removeMobile, imageUrl, mobileUrl };
}

/** Appends the banner fields shared by both ad forms to a FormData. */
export function appendBanner(data, form) {
    data.append('name', form.name);
    data.append('image_alt', form.image_alt ?? '');
    Object.entries(form.translations).forEach(([code, fields]) => {
        Object.entries(fields).forEach(([field, value]) => data.append(`translations[${code}][${field}]`, value ?? ''));
    });
    if (form.image) data.append('image', form.image);
    if (form.mobile_image) data.append('mobile_image', form.mobile_image);
}
