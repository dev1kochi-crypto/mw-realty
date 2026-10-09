import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
// Screen styles carried over from the Blade pages they replace (the rest is public/portal/css/portal.css).
import './styles/layout.css';
import './styles/help.css';
import './styles/dashboard.css';
import './styles/leads.css';
import './styles/lead-import.css';
import './styles/lead-table-fields.css';
import './styles/lead-assignment.css';
import './styles/lead-insights.css';
import './styles/visitor-insights.css';
import './styles/website-leads.css';
import './styles/integrations.css';
import './styles/property-options.css';
import './styles/properties.css';
import './styles/listing-permits.css';
import './styles/nearby-places.css';
import './styles/watermark.css';
import './styles/reports.css';
import './styles/contact.css';
import './styles/profile.css';
import './styles/security.css';
import './styles/plans.css';
import './styles/checkout.css';

createApp(App).use(router).mount('#crm-app');
