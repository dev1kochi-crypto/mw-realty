<template>
    <div v-if="!data" class="text-center py-5"><span class="spinner-border text-primary"></span></div>
    <div v-else class="vi-panel">
        <!-- Engagement strip -->
        <div class="portal-card vi-strip">
            <div class="portal-lp-stats">
                <div class="portal-lp-stat">
                    <span class="portal-lp-stat-icon"><i class="fas fa-clock"></i></span>
                    <div class="min-w-0"><div class="portal-lp-stat-label">Time on site</div><div class="portal-lp-stat-value">{{ duration(stats.site_seconds) }}</div><div class="vi-sub">{{ stats.page_views }} {{ plural('page', stats.page_views) }} viewed</div></div>
                </div>
                <div class="portal-lp-stat">
                    <span class="portal-lp-stat-icon is-accent"><i class="fas fa-house"></i></span>
                    <div class="min-w-0"><div class="portal-lp-stat-label">Property views</div><div class="portal-lp-stat-value">{{ stats.property_views }}</div><div class="vi-sub">{{ stats.properties_viewed }} {{ plural('listing', stats.properties_viewed) }} · {{ duration(stats.property_seconds) }}</div></div>
                </div>
                <div class="portal-lp-stat">
                    <span class="portal-lp-stat-icon is-warning"><i class="fas fa-magnifying-glass"></i></span>
                    <div class="min-w-0"><div class="portal-lp-stat-label">Searches</div><div class="portal-lp-stat-value">{{ stats.searches }}</div><div class="vi-sub">{{ stats.saved_searches }} saved</div></div>
                </div>
                <div class="portal-lp-stat">
                    <span class="portal-lp-stat-icon is-accent"><i class="fas fa-heart"></i></span>
                    <div class="min-w-0"><div class="portal-lp-stat-label">Favorites</div><div class="portal-lp-stat-value">{{ stats.favorites }}</div><div class="vi-sub">{{ data.has_account ? 'customer account' : 'not signed in' }}</div></div>
                </div>
                <div class="portal-lp-stat">
                    <span class="portal-lp-stat-icon"><i class="fas fa-robot"></i></span>
                    <div class="min-w-0"><div class="portal-lp-stat-label">AI chats</div><div class="portal-lp-stat-value">{{ stats.chats }}</div><div class="vi-sub">{{ number(stats.chat_messages) }} messages</div></div>
                </div>
                <div class="portal-lp-stat">
                    <span class="portal-lp-stat-icon is-success"><i class="fas fa-envelope-open-text"></i></span>
                    <div class="min-w-0"><div class="portal-lp-stat-label">Form enquiries</div><div class="portal-lp-stat-value">{{ stats.enquiries }}</div><div class="vi-sub">website forms</div></div>
                </div>
            </div>
            <div class="vi-strip-foot">
                <span class="vi-engagement" :class="`tone-${data.engagement.tone}`" title="From views, time on site, chats, enquiries and saves"><i class="fas fa-fire-flame-curved m-0"></i>{{ data.engagement.label }} lead</span>
                <span><i class="far fa-calendar"></i>First seen {{ formatDateTime(stats.first_seen) }}</span>
                <span><i class="fas fa-clock-rotate-left"></i>Last active {{ stats.last_seen ? timeAgo(stats.last_seen) : '—' }}</span>
                <span><i class="fas fa-arrow-right-to-bracket"></i>Came in via {{ data.came_in_via }}</span>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-7">
                <section class="portal-lp-card">
                    <header class="portal-lp-card-head"><h2><span class="portal-lp-card-icon is-accent"><i class="fas fa-fire"></i></span>Most Interested Properties <span v-if="data.top_properties.length > 1" class="portal-lp-count">{{ data.top_properties.length }}</span></h2></header>
                    <div class="portal-lp-card-body">
                        <div v-for="(row, i) in data.top_properties" :key="row.property.id" class="vi-prop">
                            <span class="vi-prop-rank">{{ i + 1 }}</span>
                            <div class="min-w-0 flex-grow-1">
                                <a :href="row.property.url" target="_blank" rel="noopener" class="vi-prop-title" :title="row.property.title">{{ row.property.title }}</a>
                                <div class="vi-prop-meta">{{ row.owner }} · last viewed {{ timeAgo(row.last_viewed_at) }}</div>
                                <div class="vi-bar"><span :style="{ width: `${Math.max(4, Math.round((100 * row.seconds) / maxPropertySeconds))}%` }"></span></div>
                            </div>
                            <div class="vi-prop-nums"><strong>{{ duration(row.seconds) }}</strong><span class="text-muted">{{ row.views }} {{ plural('view', row.views) }}</span></div>
                        </div>
                        <div v-if="!data.top_properties.length" class="portal-lp-empty">No property detail pages viewed yet.</div>
                    </div>
                </section>

                <!-- Activity timeline — newest first, 20 more on each scroll; plain page views only under "All activity". -->
                <section class="portal-lp-card">
                    <header class="portal-lp-card-head">
                        <h2><span class="portal-lp-card-icon"><i class="fas fa-stream"></i></span>Activity Timeline <span class="portal-lp-count">{{ number(data.activity_counts[timelineFilter]) }}</span></h2>
                        <div v-if="data.activity_counts.all" class="vi-toggle" role="group" aria-label="Show">
                            <button type="button" :class="{ 'is-active': timelineFilter === 'key' }" @click="setTimelineFilter('key')">Key events</button>
                            <button type="button" :class="{ 'is-active': timelineFilter === 'all' }" @click="setTimelineFilter('all')">All activity</button>
                        </div>
                    </header>
                    <div class="portal-lp-card-body">
                        <div v-if="!data.activity_counts.all" class="portal-lp-empty">No activity tracked yet.</div>
                        <div v-else class="vi-timeline-wrap" @scroll="onScroll($event, 'timeline')">
                            <template v-for="(event, i) in lists.timeline.items" :key="event.id">
                                <div v-if="dayOf(event) !== dayOf(lists.timeline.items[i - 1])" class="vi-day">{{ dayLabel(event.at) }}</div>
                                <div class="vi-act" :title="event.hint || null">
                                    <span class="vi-act-icon" :class="`tone-${event.tone}`"><i class="fas" :class="event.icon" aria-hidden="true"></i></span>
                                    <span class="vi-act-text">
                                        <strong>{{ event.label }}</strong>
                                        <a v-if="event.property" :href="event.property.url" target="_blank" rel="noopener">{{ event.property.title }}</a>
                                        <span v-if="event.detail" class="text-muted">{{ event.property ? '→ ' : '' }}{{ event.detail }}</span>
                                    </span>
                                    <span class="vi-act-time"><template v-if="event.duration_seconds > 0"><i class="far fa-clock"></i>{{ duration(event.duration_seconds) }} · </template>{{ formatTime(event.at) }}</span>
                                </div>
                            </template>
                            <div v-if="lists.timeline.loading" class="vi-feed-more"><i class="fas fa-spinner fa-spin"></i></div>
                            <div v-else-if="!lists.timeline.items.length" class="portal-lp-empty">No key events yet — switch to All activity to see page views.</div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-xl-5">
                <section class="portal-lp-card">
                    <header class="portal-lp-card-head"><h2><span class="portal-lp-card-icon"><i class="fas fa-robot"></i></span>AI Chat History <span v-if="stats.chats" class="portal-lp-count">{{ number(stats.chats) }}</span></h2></header>
                    <div class="portal-lp-card-body vi-chat-list" @scroll="onScroll($event, 'chats')">
                        <div v-if="!lists.chats.items.length" class="portal-lp-empty">No AI chat conversations.</div>
                        <details v-for="conversation in lists.chats.items" :key="conversation.id" class="vi-chat" @toggle="openChat(conversation, $event)">
                            <summary>
                                <i class="fas fa-comments text-muted"></i>
                                <span><strong>{{ formatDateTime(conversation.last_message_at) }}</strong> <span class="text-muted">· {{ conversation.message_count }} messages</span></span>
                                <i class="fas fa-chevron-down"></i>
                            </summary>
                            <div class="vi-chat-body" @scroll="onChatScroll($event, conversation)">
                                <template v-for="message in chats[conversation.id]?.items ?? []" :key="message.id">
                                    <div class="vi-msg" :class="{ 'is-user': message.role === 'user' }">
                                        <span class="vi-msg-avatar"><template v-if="message.role === 'user'">{{ initials(leadName, 1) }}</template><i v-else class="fas fa-robot"></i></span>
                                        <div class="vi-msg-text">{{ message.text }}</div>
                                    </div>
                                    <div v-if="message.properties.length" class="vi-chips vi-msg-props">
                                        <a v-for="card in message.properties" :key="card.url" class="vi-chip" :href="card.url" target="_blank" rel="noopener"><i class="fas fa-house"></i>{{ card.name }}</a>
                                    </div>
                                </template>
                                <div v-if="chats[conversation.id]?.loading" class="vi-feed-more"><i class="fas fa-spinner fa-spin"></i></div>
                            </div>
                        </details>
                        <div v-if="lists.chats.loading" class="vi-feed-more"><i class="fas fa-spinner fa-spin"></i></div>
                    </div>
                </section>

                <section class="portal-lp-card">
                    <header class="portal-lp-card-head"><h2><span class="portal-lp-card-icon is-warning"><i class="fas fa-magnifying-glass"></i></span>What They Search For</h2></header>
                    <div class="portal-lp-card-body">
                        <div v-if="!data.search_interests.length" class="portal-lp-empty">No filtered searches yet.</div>
                        <div v-else class="vi-chips">
                            <span v-for="interest in data.search_interests" :key="interest.label" class="vi-chip">{{ interest.label }} <b v-if="interest.count > 1">×{{ interest.count }}</b></span>
                        </div>
                    </div>
                </section>

                <section class="portal-lp-card">
                    <header class="portal-lp-card-head">
                        <h2><span class="portal-lp-card-icon is-accent"><i class="fas fa-heart"></i></span>Saved by Lead</h2>
                        <div v-if="lists.favorites.items.length || lists.searches.items.length" class="vi-toggle" role="tablist">
                            <button type="button" :class="{ 'is-active': savedTab === 'favorites' }" @click="savedTab = 'favorites'"><i class="fas fa-heart me-1"></i>Favorites <span class="vi-tab-count">{{ number(stats.favorites) }}</span></button>
                            <button type="button" :class="{ 'is-active': savedTab === 'searches' }" @click="savedTab = 'searches'"><i class="fas fa-bookmark me-1"></i>Searches <span class="vi-tab-count">{{ number(stats.saved_searches) }}</span></button>
                        </div>
                    </header>
                    <div class="portal-lp-card-body">
                        <div v-if="!lists.favorites.items.length && !lists.searches.items.length" class="portal-lp-empty">
                            {{ data.has_account ? 'Nothing saved to their account yet.' : 'Needs a customer account — this visitor hasn\'t signed in.' }}
                        </div>
                        <div v-else-if="savedTab === 'favorites'" class="vi-saved-list" @scroll="onScroll($event, 'favorites')">
                            <a v-for="fav in lists.favorites.items" :key="fav.id" :href="fav.property.url" target="_blank" rel="noopener" class="vi-saved">
                                <span class="vi-saved-thumb"><img v-if="fav.property.image" :src="fav.property.image" alt="" loading="lazy"><i v-else class="fas fa-house"></i></span>
                                <span class="min-w-0 flex-grow-1">
                                    <span class="vi-saved-title">{{ fav.property.title }}</span>
                                    <span class="vi-saved-meta">{{ propertyMeta(fav.property) }}</span>
                                </span>
                                <span class="vi-saved-time">{{ fav.saved_at ? timeAgo(fav.saved_at, true) : '' }}</span>
                            </a>
                            <div v-if="!lists.favorites.items.length" class="portal-lp-empty">No favorite properties.</div>
                        </div>
                        <div v-else class="vi-saved-list" @scroll="onScroll($event, 'searches')">
                            <a v-for="search in lists.searches.items" :key="search.id" :href="search.url" target="_blank" rel="noopener" class="vi-saved" title="Open these results on the website">
                                <span class="vi-saved-thumb is-search"><i class="fas fa-bookmark"></i></span>
                                <span class="min-w-0 flex-grow-1">
                                    <span class="vi-saved-title">{{ search.title }}</span>
                                    <span class="vi-saved-meta">{{ search.criteria.join(' · ') || 'All listings' }}</span>
                                </span>
                                <span class="vi-saved-time">{{ search.created_at ? timeAgo(search.created_at, true) : '' }}</span>
                            </a>
                            <div v-if="!lists.searches.items.length" class="portal-lp-empty">No saved searches.</div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<script setup>
/**
 * A website lead's tracked activity (resources/views/visitor-insights/_panel) — GET {base}/summary,
 * then each list loads 20 more on scroll from {base}?section=… (VisitorInsights::feedJson()).
 */
import { computed, onMounted, reactive, ref } from 'vue';
import http from '../api/http';
import { duration, formatDateTime, formatTime, initials, number, plural, timeAgo } from '../utils/format';

const props = defineProps({
    baseUrl: { type: String, required: true }, // e.g. /leads/34/insights
    leadName: { type: String, default: '' },
});

const data = ref(null);
const lists = reactive({});
const chats = reactive({});
const timelineFilter = ref('key');
const savedTab = ref('favorites');

const stats = computed(() => data.value.stats);
const maxPropertySeconds = computed(() => Math.max(1, ...data.value.top_properties.map((row) => row.seconds)));

const dayOf = (event) => (event ? new Date(event.at).toDateString() : null);
function dayLabel(iso) {
    const date = new Date(iso);
    const today = new Date();
    const yesterday = new Date(Date.now() - 86400000);
    if (date.toDateString() === today.toDateString()) return 'Today';
    if (date.toDateString() === yesterday.toDateString()) return 'Yesterday';
    return date.toLocaleDateString('en-GB', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
}
const propertyMeta = (p) => [p.bedrooms ? `${p.bedrooms} BR` : null, p.price ? `${p.currency} ${number(p.price)}` : null, p.reference_no].filter(Boolean).join(' · ');

function loadMore(section, extra = {}) {
    const list = lists[section];
    if (!list.next || list.loading) return;
    list.loading = true;
    http.get(props.baseUrl, { params: { section, after: list.next, ...extra } })
        .then((res) => {
            list.items.push(...res.data.items);
            list.next = res.data.next;
        })
        .finally(() => {
            list.loading = false;
        });
}

function onScroll(event, section) {
    const el = event.target;
    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 40) {
        loadMore(section, section === 'timeline' ? { filter: timelineFilter.value } : {});
    }
}

function setTimelineFilter(filter) {
    if (timelineFilter.value === filter) return;
    timelineFilter.value = filter;
    lists.timeline = { items: [], next: null, loading: true };
    http.get(props.baseUrl, { params: { section: 'timeline', filter } })
        .then((res) => {
            lists.timeline = { items: res.data.items, next: res.data.next, loading: false };
        })
        .catch(() => {
            lists.timeline.loading = false;
        });
}

/** A conversation's messages load when it is opened, 20 at a time as it scrolls. */
function loadChat(conversation) {
    const chat = chats[conversation.id] ??= { items: [], next: 0, loading: false };
    if (chat.next === null || chat.loading) return;
    chat.loading = true;
    http.get(props.baseUrl, { params: { section: 'messages', conversation: conversation.id, after: chat.next || undefined } })
        .then((res) => {
            chat.items.push(...res.data.items);
            chat.next = res.data.next;
        })
        .finally(() => {
            chat.loading = false;
        });
}

function openChat(conversation, event) {
    if (event.target.open && !chats[conversation.id]) loadChat(conversation);
}

function onChatScroll(event, conversation) {
    const el = event.target;
    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 30) loadChat(conversation);
}

onMounted(() => {
    http.get(`${props.baseUrl}/summary`).then((res) => {
        data.value = res.data;
        ['timeline', 'favorites', 'searches', 'chats'].forEach((section) => {
            lists[section] = { ...res.data[section], loading: false };
        });
        savedTab.value = !res.data.favorites.items.length && res.data.searches.items.length ? 'searches' : 'favorites';
    });
});
</script>
