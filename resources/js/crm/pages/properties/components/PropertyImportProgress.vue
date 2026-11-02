<template>
    <div class="limp">
        <template v-if="working">
            <div class="limp-icon is-working"><i class="fas fa-file-import"></i></div>
            <div class="limp-body">
                <div class="limp-title">{{ queued ? 'Starting import…' : `Importing listings… ${progress.percent}%` }}</div>
                <div class="limp-file">{{ progress.file }}</div>
                <div class="progress" :class="{ 'limp-progress-queued': queued }" role="progressbar" :aria-valuenow="progress.percent" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" :style="{ width: `${Math.max(progress.percent, 2)}%` }"></div>
                </div>
                <div class="limp-meta">
                    {{ queued ? `Waiting for the import worker — ${number(progress.total)} rows` : `${number(progress.processed)} of ${number(progress.total)} rows checked` }}
                </div>
                <div v-if="!queued" class="limp-stats">
                    <span class="limp-stat added">{{ number(progress.added) }} added</span>
                    <span class="limp-stat updated">{{ number(progress.duplicates) }} duplicates</span>
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
                <div class="limp-file">{{ progress.file }}<template v-if="progress.duration_seconds !== null"> · {{ duration }}</template></div>
                <div class="limp-stats">
                    <span class="limp-stat added">{{ number(progress.complete) }} ready</span>
                    <span v-if="progress.needs_info" class="limp-stat needs-info">{{ number(progress.needs_info) }} need details</span>
                    <span class="limp-stat updated">{{ number(progress.duplicates) }} duplicates</span>
                    <span class="limp-stat skipped">{{ number(progress.skipped) }} skipped</span>
                </div>
                <div class="limp-meta mt-2">
                    {{ progress.automated_percent }}% of rows imported with nothing left to fill in
                    <template v-if="progress.ai_written"> · {{ number(progress.ai_written) }} descriptions written by AI</template>
                    <template v-if="progress.translated"> · {{ number(progress.translated) }} translated</template>.
                    New listings stay off the website until their permit is validated.
                </div>
                <div v-if="progress.error" class="limp-meta mt-1 text-danger">{{ progress.error }}</div>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <a v-if="progress.has_result" :href="`${apiBase}/properties/imports/${progress.id}/result`" class="btn btn-sm portal-btn-ghost"><i class="fas fa-file-arrow-down me-1"></i> Download status file</a>
                    <button type="button" class="btn btn-sm btn-portal-primary" @click="$emit('refresh')"><i class="fas fa-rotate me-1"></i> Refresh listings</button>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
/** One listing import's progress / result — the lead import's .limp-* markup (styles/lead-import.css). */
import { computed } from 'vue';
import { isWorking } from '../../../composables/useLeadImport';
import { number } from '../../../utils/format';

const props = defineProps({ progress: { type: Object, required: true } });
defineEmits(['refresh']);

const working = computed(() => isWorking(props.progress));
const queued = computed(() => props.progress.status === 'queued');
const duration = computed(() => {
    const s = props.progress.duration_seconds ?? 0;
    return s < 60 ? `${s}s` : `${Math.floor(s / 60)} min ${s % 60}s`;
});
const apiBase = window.CrmConfig.apiBase;
</script>
