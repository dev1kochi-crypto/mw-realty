import './bootstrap';
import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import { installVisitorTracking } from './composables/useVisitorTracking';
import { vTrackImpression } from './composables/useListingImpressions';

installVisitorTracking(router);

createApp(App).use(router).directive('track-impression', vTrackImpression).mount('#app');
