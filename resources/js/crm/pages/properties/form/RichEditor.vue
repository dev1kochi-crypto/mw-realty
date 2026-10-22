<template>
    <textarea :id="id" ref="textarea" class="form-control tinymce-editor" rows="6" :value="modelValue"></textarea>
</template>

<script setup>
/**
 * The description editor — TinyMCE 6 (CDN), the same build and toolbar the Blade form used.
 * v-model is the HTML; a value set from outside (auto-translate) replaces the editor content.
 * `autoFilled` marks the editor as auto-translated (.is-auto-translated on its container).
 */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { loadScript } from '../../../utils/loadScript';

const TINYMCE = 'https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js';

const props = defineProps({
    modelValue: { type: String, default: '' },
    id: { type: String, required: true },
    autoFilled: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'user-input']);

const textarea = ref(null);
let editor = null;
let lastEmitted = props.modelValue;

onMounted(() => {
    loadScript(TINYMCE).then(() => {
        if (!textarea.value) return;
        window.tinymce.init({
            target: textarea.value,
            height: 260,
            plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount',
            toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link | removeformat | code | help',
            setup(ed) {
                editor = ed;
                ed.on('init', () => {
                    ed.setContent(props.modelValue || '');
                    ed.getContainer().classList.toggle('is-auto-translated', props.autoFilled);
                });
                // Lets the language auto-fill react to Description edits.
                ed.on('input change undo redo', () => {
                    lastEmitted = ed.getContent();
                    emit('update:modelValue', lastEmitted);
                    emit('user-input');
                });
            },
        });
    }).catch(() => {});
});

watch(() => props.modelValue, (value) => {
    if (!editor || value === lastEmitted) return;
    lastEmitted = value || '';
    editor.setContent(lastEmitted);
});

watch(() => props.autoFilled, (on) => editor?.getContainer()?.classList.toggle('is-auto-translated', on));

onBeforeUnmount(() => {
    editor?.remove();
    editor = null;
});
</script>
