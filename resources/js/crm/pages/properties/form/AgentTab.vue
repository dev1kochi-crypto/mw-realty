<template>
    <div class="property-tab-pane-head">
        <div class="property-tab-pane-title">Agent &amp; Agency</div>
        <div class="property-tab-pane-hint">Who this listing is published under</div>
    </div>

    <div class="row g-3">
        <!-- Logged in as the agent themself — both sides are fixed, nothing to pick. -->
        <template v-if="agents.locked_agent">
            <div class="col-md-6">
                <label class="form-label fw-bold">Agent</label>
                <input type="text" class="form-control" :value="agents.locked_agent" disabled>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Agency</label>
                <input type="text" class="form-control" :value="agents.locked_agency" disabled>
            </div>
        </template>
        <!-- Logged in as the agency — agency is fixed, pick one of our own agents. -->
        <template v-else-if="agents.locked_agency">
            <div class="col-md-6">
                <label class="form-label fw-bold">Agency</label>
                <input type="text" class="form-control" :value="agents.locked_agency" disabled>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Assigned Agent <span class="text-muted fw-normal">(optional)</span></label>
                <select v-model="form.agent_id" class="form-select" :class="{ 'is-invalid': f.fieldError('agent_id') }">
                    <option value="">— No agent (enquiries round-robin across your agents) —</option>
                    <option v-for="agent in agents.agent_options" :key="agent.id" :value="String(agent.id)">{{ agent.name }}</option>
                </select>
                <div v-if="f.fieldError('agent_id')" class="invalid-feedback">{{ f.fieldError('agent_id') }}</div>
                <div v-if="!agents.agent_options.length" class="form-text">No active agents yet — that's fine, the listing publishes normally and enquiries come to your agency. <a :href="links.agents" target="_blank">Manage agents</a>.</div>
            </div>
        </template>
        <!-- Super Admin — separate Agency and Agent pickers; choosing an agency narrows the agent list. -->
        <template v-else>
            <div class="col-md-6">
                <label class="form-label fw-bold">Agency</label>
                <select v-model="agency" class="form-select" @change="onAgency">
                    <option value="">— All agencies —</option>
                    <option v-for="item in agents.agency_options" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                </select>
                <div class="form-text">Filters the agent list below — a listing is still saved against the agent, not the agency.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Agent</label>
                <select v-model="form.agent_id" class="form-select" :class="{ 'is-invalid': f.fieldError('agent_id') }">
                    <option value="">— No agent assigned —</option>
                    <option v-for="agent in visibleAgents" :key="agent.id" :value="String(agent.id)">
                        {{ agent.name }}<template v-if="agent.company"> &mdash; {{ agent.company }}</template>
                    </option>
                </select>
                <div v-if="f.fieldError('agent_id')" class="invalid-feedback">{{ f.fieldError('agent_id') }}</div>
            </div>
        </template>
    </div>
</template>

<script setup>
/** Property form › Agent & Agency. */
import { computed, inject, ref } from 'vue';

const f = inject('listingForm');
const form = f.form;
const agents = f.state.options.agents;
const links = f.state.options.links;
const agency = ref(f.state.property?.agent_company_id ? String(f.state.property.agent_company_id) : '');

const visibleAgents = computed(() => (agency.value ? agents.agent_options.filter((a) => String(a.company_id) === agency.value) : agents.agent_options));

function onAgency() {
    if (form.agent_id && !visibleAgents.value.some((a) => String(a.id) === String(form.agent_id))) form.agent_id = '';
}
</script>
