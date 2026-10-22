<template>
    <div v-if="!ticket" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
    </div>
    <div v-else>
        <div class="st-head">
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="st-ref">{{ ticket.reference }}</span>
                    <span class="st-badge" :class="`st-badge--${ticket.status_tone}`">{{ ticket.status_label }}</span>
                </div>
                <h1 class="st-head__title mt-1">{{ ticket.subject }}</h1>
            </div>
            <div class="st-head__actions">
                <RouterLink :to="{ name: 'contact.index' }" class="btn btn-portal-light btn-sm"><i class="fas fa-arrow-left me-1"></i>My Tickets</RouterLink>
                <button v-if="!ticket.is_solved" type="button" class="btn btn-success btn-sm" :disabled="busy" @click="resolve"><i class="fas fa-check me-1"></i>Mark as Solved</button>
                <button v-else-if="ticket.status === 'resolved'" type="button" class="btn btn-portal-light btn-sm" :disabled="busy" @click="act('reopen')"><i class="fas fa-redo me-1"></i>Reopen</button>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="portal-card p-3 p-md-4">
                    <div class="portal-section-title">Conversation</div>
                    <div class="st-thread">
                        <template v-for="message in ticket.messages" :key="message.id">
                            <div v-if="message.system" class="st-event"><i class="fas fa-history me-1"></i>{{ message.body }} &middot; {{ when(message.created_at) }}</div>
                            <div v-else class="st-msg" :class="message.mine ? 'st-msg--mine' : 'st-msg--support'">
                                <span class="st-msg__avatar"><i class="fas" :class="message.mine ? 'fa-user' : 'fa-headset'"></i></span>
                                <div class="st-msg__bubble">
                                    <div class="st-msg__who">
                                        {{ message.mine ? 'You' : message.author }}
                                        <span class="st-msg__when">{{ when(message.created_at) }}</span>
                                    </div>
                                    <div class="st-msg__body">{{ message.body }}</div>
                                    <a v-if="message.attachment_name" href="#" class="st-attach" @click.prevent="downloadAttachment(message)"><i class="fas fa-paperclip"></i>{{ message.attachment_name }}</a>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div v-if="ticket.is_closed" class="st-empty mt-3 py-4">
                    <div class="fw-bold mb-1">This ticket is closed</div>
                    <div class="small">Still need help? <RouterLink :to="{ name: 'contact.create' }">Raise a new ticket</RouterLink>.</div>
                </div>
                <div v-else class="portal-card p-3 p-md-4 mt-3">
                    <div class="portal-section-title">{{ ticket.is_solved ? 'Not solved after all? Reply to reopen' : 'Reply' }}</div>
                    <form @submit.prevent="reply">
                        <textarea v-model="replyText" class="form-control mb-2" :class="{ 'is-invalid': errors.message }" rows="4" required maxlength="5000" placeholder="Write your reply…"></textarea>
                        <div v-if="errors.message" class="invalid-feedback d-block mb-2">{{ errors.message[0] }}</div>
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <input ref="fileInput" type="file" class="form-control form-control-sm" :class="{ 'is-invalid': errors.attachment_document }" accept=".jpg,.jpeg,.png,.pdf" style="max-width: 280px;">
                                <div v-if="errors.attachment_document" class="invalid-feedback d-block">{{ errors.attachment_document[0] }}</div>
                            </div>
                            <button type="submit" class="btn btn-portal-primary" :disabled="busy"><i class="fas fa-paper-plane me-1"></i> Send Reply</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="portal-card p-4">
                    <div class="portal-section-title">Ticket Details</div>
                    <dl class="st-detail mb-0">
                        <dt>Ticket No.</dt><dd class="st-ref">{{ ticket.reference }}</dd>
                        <dt>Status</dt><dd><span class="st-badge" :class="`st-badge--${ticket.status_tone}`">{{ ticket.status_label }}</span></dd>
                        <dt>Issue Type</dt><dd>{{ ticket.category_label }}</dd>
                        <dt>Priority</dt><dd :class="`st-prio--${ticket.priority}`">{{ ticket.priority_label }}</dd>
                        <dt>Raised</dt><dd>{{ when(ticket.created_at) }}</dd>
                        <dt>Last Update</dt><dd>{{ timeAgo(ticket.updated_at) }}</dd>
                        <template v-if="ticket.resolved_at">
                            <dt>Solved</dt><dd class="mb-0">{{ when(ticket.resolved_at) }}</dd>
                        </template>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
/** One support ticket (resources/views/portal/contact/show) — the thread, reply, solve / reopen. */
import { ref, watch } from 'vue';
import http, { errorMessage } from '../../api/http';
import { useConfirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { download } from '../../utils/download';
import { formatDate, timeAgo } from '../../utils/format';

const props = defineProps({ id: { type: Number, required: true } });

const { confirm } = useConfirm();
const { success, error: toastError } = useToast();

const ticket = ref(null);
const loadError = ref('');
const replyText = ref('');
const fileInput = ref(null);
const errors = ref({});
const busy = ref(false);

/** "09 Oct 2026, 02:20 PM" — the Blade page's d M Y, h:i A. */
const when = (iso) => (iso ? `${formatDate(iso)}, ${new Date(iso).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })}` : '—');

function load() {
    loadError.value = '';
    return http.get(`/support/tickets/${props.id}`)
        .then((res) => { ticket.value = res.data; })
        .catch((e) => { loadError.value = errorMessage(e); });
}

function settle(request) {
    busy.value = true;
    errors.value = {};
    return request
        .then((res) => {
            ticket.value = res.data.ticket;
            success(res.data.message);
            return true;
        })
        .catch((e) => {
            if (e.response?.status === 422 && e.response.data.errors) errors.value = e.response.data.errors;
            toastError(errorMessage(e));
            return false;
        })
        .finally(() => { busy.value = false; });
}

function act(action) {
    return settle(http.post(`/support/tickets/${props.id}/${action}`));
}

async function resolve() {
    if (!(await confirm({ title: 'Resolve support ticket?', message: 'Mark this ticket as solved? You can reply later to reopen it.', confirmText: 'Mark as Solved', tone: 'primary' }))) return;
    act('resolve');
}

function reply() {
    const body = new FormData();
    body.append('message', replyText.value);
    if (fileInput.value?.files[0]) body.append('attachment_document', fileInput.value.files[0]);
    settle(http.post(`/support/tickets/${props.id}/reply`, body)).then((ok) => {
        if (!ok) return;
        replyText.value = '';
        if (fileInput.value) fileInput.value.value = '';
    });
}

function downloadAttachment(message) {
    download('get', `/support/tickets/${props.id}/attachments/${message.id}`)
        .catch(() => toastError('Could not download the attachment. Please try again.'));
}

watch(() => props.id, load, { immediate: true });
</script>
