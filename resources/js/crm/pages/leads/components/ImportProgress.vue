<template>
    <div class="limp">
        <template v-if="working">
            <div class="limp-icon is-working"><i class="fas fa-cloud-arrow-up"></i></div>
            <div class="limp-body">
                <div class="limp-title">{{ queued ? 'Starting import…' : `Importing leads… ${progress.percent}%` }}</div>
                <div class="limp-file">{{ progress.file }}</div>
                <div class="progress" :class="{ 'limp-progress-queued': queued }" role="progressbar" :aria-valuenow="progress.percent" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" :style="{ width: `${Math.max(progress.percent, 2)}%` }"></div>
                </div>
                <div class="limp-meta">
                    {{ queued ? `Waiting for the import worker — ${number(progress.total)} rows` : `${number(progress.processed)} of ${number(progress.total)} rows checked` }}
                </div>
                <div v-if="!queued" class="limp-stats">
                    <span class="limp-stat added">{{ number(progress.added) }} added</span>
                    <span class="limp-stat updated">{{ number(progress.updated) }} updated</span>
                    <span class="limp-stat skipped">{{ number(progress.skipped) }} skipped</span>
                </div>
            </div>
        </template>
        <template v-else-if="progress.status === 'failed'">
            <div class="limp-icon is-failed"><i class="fas fa-triangle-exclamation"></i></div>
            <div class="limp-body">
                <div class="limp-title">Import failed</div>
                <div class="limp-file">{{ progress.file }}</div>
                <div class="limp-meta mt-1">{{ progress.error }}</div>
            </div>
        </template>
        <template v-else>
            <div class="limp-icon is-done"><i class="fas fa-check"></i></div>
            <div class="limp-body">
                <div class="limp-title">Import finished — {{ number(progress.total) }} rows</div>
                <div class="limp-file">{{ progress.file }}</div>
                <div class="limp-stats">
                    <span class="limp-stat added">{{ number(progress.added) }} added</span>
                    <span class="limp-stat updated">{{ number(progress.updated) }} updated</span>
                    <span class="limp-stat skipped">{{ number(progress.skipped) }} skipped</span>
                </div>
                <div class="limp-meta mt-2">A summary email with this file and its Import Status column has been sent.</div>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <a v-if="progress.result_url" :href="`${apiBase}/leads/imports/${progress.id}/result`" class="btn btn-sm portal-btn-ghost"><i class="fas fa-file-arrow-down me-1"></i> Download status file</a>
                    <button type="button" class="btn btn-sm btn-portal-primary" @click="$emit('refresh')"><i class="fas fa-rotate me-1"></i> Refresh leads</button>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
/** One import's progress / result (the portal's LeadImportTracker markup — .limp-*, styles/lead-import.css). */
import { computed } from 'vue';
import { isWorking } from '../../../composables/useLeadImport';
import { number } from '../../../utils/format';

const props = defineProps({ progress: { type: Object, required: true } });
defineEmits(['refresh']);

const working = computed(() => isWorking(props.progress));
const queued = computed(() => props.progress.status === 'queued');
const apiBase = window.CrmConfig.apiBase;
</script>
