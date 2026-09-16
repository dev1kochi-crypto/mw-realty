<script setup>
const navTabs = [
    { filter: 'overview', icon: 'icon-home.svg', label: 'Overview', active: true },
    { filter: 'wishlist', icon: 'heart.svg', label: 'Wishlist' },
    { filter: 'saved-searches', icon: 'search.svg', label: 'Saved Searches' },
    { filter: 'enquiries', icon: 'email.svg', label: 'My Enquiries' },
    { filter: 'settings', icon: 'user.svg', label: 'Account Settings' },
];

const stats = [
    { value: 3, label: 'Saved Properties' },
    { value: 3, label: 'Saved Searches' },
    { value: 4, label: 'Enquiries Sent' },
];

const activityItems = [
    { icon: 'heart.svg', before: 'You saved ', strong: 'Elegant Apartment in Mirdif', after: ' to your wishlist.', time: '2 days ago' },
    { icon: 'email.svg', before: 'You sent an enquiry for ', strong: 'Spacious Villa in Al Barsha', after: '.', time: '4 days ago' },
    { icon: 'search.svg', before: 'You saved a new search: ', strong: '3 Bedroom Apartments in Dubai Marina', after: '.', time: '1 week ago' },
    { icon: 'user.svg', before: 'You updated your profile phone number.', strong: '', after: '', time: '2 weeks ago' },
];

const wishlistProperties = [
    {
        image: '/frontend/assets/images/home/project-card-1.jpg',
        title: 'Elegant Apartment in Mirdif',
        purpose: 'Buy | Off Plan',
        type: 'Apartment',
        price: 'AED 2,292,913',
        amenity: 'Furnished',
        location: 'Mirdif, Dubai',
        beds: 4,
        baths: 3,
        area: '5,952 sq.ft',
    },
    {
        image: '/frontend/assets/images/home/project-card-3.jpg',
        title: 'Spacious Villa in Al Barsha',
        purpose: 'Buy',
        type: 'Villa',
        price: 'AED 4,850,000',
        amenity: 'Semi-furnished',
        location: 'Al Barsha, Dubai',
        beds: 5,
        baths: 6,
        area: '6,200 sq.ft',
    },
    {
        image: '/frontend/assets/images/home/project-card-5.jpg',
        title: 'Sea View Penthouse in Dubai Marina',
        purpose: 'Buy',
        type: 'Penthouse',
        price: 'AED 9,750,000',
        amenity: 'Furnished',
        location: 'Dubai Marina, Dubai',
        beds: 4,
        baths: 5,
        area: '3,800 sq.ft',
    },
];

const savedSearches = [
    { title: '3 Bedroom Apartments in Dubai Marina', meta: 'AED 1.5M – 2.5M · Ready · Furnished' },
    { title: 'Villas in Arabian Ranches', meta: 'AED 3M – 6M · 4+ Bedrooms' },
    { title: 'Off-Plan Studios in Business Bay', meta: 'Under AED 900K · Off Plan' },
];

const enquiries = [
    { property: 'Elegant Apartment in Mirdif', sentTo: 'Kaal Real Estate Agency', date: 'Mar 10, 2026', status: 'Replied', statusClass: 'mw-dashboard__status--replied' },
    { property: 'Spacious Villa in Al Barsha', sentTo: 'Elite Homes Realty', date: 'Mar 06, 2026', status: 'Pending', statusClass: 'mw-dashboard__status--pending' },
    { property: 'Modern Townhouse in Arabian Ranches', sentTo: 'Skyline Properties', date: 'Feb 27, 2026', status: 'Replied', statusClass: 'mw-dashboard__status--replied' },
    { property: 'Sea View Penthouse in Dubai Marina', sentTo: 'ABC Real Estate', date: 'Feb 18, 2026', status: 'Closed', statusClass: 'mw-dashboard__status--closed' },
];

const settingsFieldsPrimary = [
    { id: 'settings-name', label: 'Full Name', type: 'text', name: 'full_name', value: 'Anna Whitfield', autocomplete: 'name' },
    { id: 'settings-email', label: 'Email Address', type: 'email', name: 'email', value: 'anna.whitfield@email.com', autocomplete: 'email' },
    { id: 'settings-phone', label: 'Phone Number', type: 'tel', name: 'phone', value: '+971 55 123 4567', autocomplete: 'tel' },
    { id: 'settings-location', label: 'Location', type: 'text', name: 'location', value: 'Dubai, UAE', autocomplete: 'address-level2' },
];

const settingsFieldsPassword = [
    { id: 'settings-password', label: 'New Password', type: 'password', name: 'password', placeholder: 'Leave blank to keep current password', autocomplete: 'new-password' },
    { id: 'settings-password-confirm', label: 'Confirm New Password', type: 'password', name: 'confirm_password', placeholder: 'Re-enter new password', autocomplete: 'new-password' },
];
</script>

<template>
    <main>
        <section class="mw-dashboard">
            <div class="container-ctn">
                <div class="mw-dashboard__layout">

                    <aside class="mw-dashboard__sidebar" data-sticky-sidebar>
                        <div class="mw-dashboard__profile">
                            <span class="mw-dashboard__avatar">
                                <img src="/frontend/assets/images/home/testimonial-anna.jpg" alt="">
                            </span>
                            <p class="mw-dashboard__name">Anna Whitfield</p>
                            <p class="mw-dashboard__email">anna.whitfield@email.com</p>
                        </div>

                        <nav class="mw-dashboard__nav" data-tab-group data-filter-target="#dashboard-panels" aria-label="Account dashboard">
                            <button v-for="tab in navTabs" :key="tab.filter" type="button" :class="{ 'is-active': tab.active }" data-tab :data-filter="tab.filter">
                                <img :src="`/frontend/assets/images/icons/${tab.icon}`" alt="">
                                {{ tab.label }}
                            </button>
                        </nav>

                        <router-link to="/" class="mw-dashboard__logout">
                            <img src="/frontend/assets/images/icons/arrow-up-right.svg" alt="">
                            Log Out
                        </router-link>
                    </aside>

                    <div class="mw-dashboard__content" id="dashboard-panels">

                        <div class="mw-dashboard__panel" data-category="overview">
                            <h1 class="mw-dashboard__panel-title">Welcome back, Anna</h1>
                            <p class="mw-dashboard__panel-subtitle">Here's what's happening with your account.</p>

                            <div class="mw-dashboard__stats">
                                <div v-for="stat in stats" :key="stat.label" class="mw-dashboard__stat">
                                    <span class="mw-dashboard__stat-value">{{ stat.value }}</span>
                                    <span class="mw-dashboard__stat-label">{{ stat.label }}</span>
                                </div>
                            </div>

                            <div class="mw-dashboard__activity">
                                <h3>Recent Activity</h3>
                                <div v-for="(item, index) in activityItems" :key="index" class="mw-dashboard__activity-item">
                                    <span class="mw-dashboard__activity-item-icon"><img :src="`/frontend/assets/images/icons/${item.icon}`" alt=""></span>
                                    <span class="mw-dashboard__activity-item-text">
                                        {{ item.before }}<strong v-if="item.strong">{{ item.strong }}</strong>{{ item.after }}
                                        <span class="mw-dashboard__activity-item-time">{{ item.time }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mw-dashboard__panel" data-category="wishlist" hidden>
                            <h1 class="mw-dashboard__panel-title">Wishlist</h1>
                            <p class="mw-dashboard__panel-subtitle">Properties you've saved for later. Click the heart to remove one.</p>

                            <div class="mw-dashboard__wishlist-grid">

                                <article v-for="property in wishlistProperties" :key="property.title" class="mw-dubai-card">
                                    <div class="mw-projects__media">
                                        <router-link to="/property-details"><img :src="property.image" :alt="property.title" class="mw-projects__photo"></router-link>
                                        <span class="mw-dubai-card__purpose">{{ property.purpose }}</span>
                                        <button type="button" class="mw-dubai-card__fav" data-remove-item aria-label="Remove from wishlist">
                                            <img src="/frontend/assets/images/icons/heart.svg" alt="" width="18" height="18">
                                        </button>
                                        <span class="mw-dubai-card__type">{{ property.type }}</span>
                                    </div>
                                    <div class="mw-dubai-card__body">
                                        <div class="mw-dubai-card__price-row">
                                            <span class="mw-dubai-card__price">{{ property.price }}</span>
                                            <span class="mw-dubai-card__amenity">{{ property.amenity }}</span>
                                        </div>
                                        <h3 class="mw-dubai-card__title"><router-link to="/property-details">{{ property.title }}</router-link></h3>
                                        <p class="mw-dubai-card__location">
                                            <img src="/frontend/assets/images/icons/location.svg" alt="" width="16" height="16">
                                            {{ property.location }}
                                        </p>
                                        <div class="mw-dubai-card__stats">
                                            <span class="mw-dubai-card__stat"><img src="/frontend/assets/images/icons/bed.svg" alt="" width="16" height="16">{{ property.beds }}</span>
                                            <span class="mw-dubai-card__stat"><img src="/frontend/assets/images/icons/bathroom.svg" alt="" width="16" height="16">{{ property.baths }}</span>
                                            <span class="mw-dubai-card__stat"><img src="/frontend/assets/images/icons/area.svg" alt="" width="16" height="16">{{ property.area }}</span>
                                        </div>
                                        <div class="mw-dubai-card__divider"></div>
                                        <div class="mw-dubai-card__contacts">
                                            <a href="mailto:info@mightywarnersrealty.com" class="mw-dubai-card__contact-btn">
                                                <img src="/frontend/assets/images/icons/email.svg" alt="" width="16" height="16">
                                                Email
                                            </a>
                                            <a href="tel:+971585899990" class="mw-dubai-card__contact-btn">
                                                <img src="/frontend/assets/images/icons/phone.svg" alt="" width="16" height="16">
                                                Call Us
                                            </a>
                                            <a href="https://wa.me/971585899990" class="mw-dubai-card__contact-btn">
                                                <img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="16" height="16">
                                                WhatsApp
                                            </a>
                                        </div>
                                    </div>
                                </article>

                            </div>
                        </div>

                        <div class="mw-dashboard__panel" data-category="saved-searches" hidden>
                            <h1 class="mw-dashboard__panel-title">Saved Searches</h1>
                            <p class="mw-dashboard__panel-subtitle">Get notified when new listings match these criteria.</p>

                            <div class="mw-dashboard__searches">
                                <div v-for="search in savedSearches" :key="search.title" class="mw-dashboard__search-row">
                                    <div class="mw-dashboard__search-row-info">
                                        <p class="mw-dashboard__search-row-title">{{ search.title }}</p>
                                        <p class="mw-dashboard__search-row-meta">{{ search.meta }}</p>
                                    </div>
                                    <div class="mw-dashboard__search-row-actions">
                                        <router-link to="/properties-dubai" class="mw-dashboard__link-btn">View Results</router-link>
                                        <button type="button" class="mw-dashboard__icon-btn" data-remove-item aria-label="Delete saved search">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mw-dashboard__panel" data-category="enquiries" hidden>
                            <h1 class="mw-dashboard__panel-title">My Enquiries</h1>
                            <p class="mw-dashboard__panel-subtitle">Messages you've sent to agents and agencies.</p>

                            <div class="mw-dashboard__table-wrap">
                                <table class="mw-dashboard__table">
                                    <thead>
                                        <tr>
                                            <th>Property</th>
                                            <th>Sent To</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(enquiry, index) in enquiries" :key="index">
                                            <td>{{ enquiry.property }}</td>
                                            <td>{{ enquiry.sentTo }}</td>
                                            <td>{{ enquiry.date }}</td>
                                            <td><span class="mw-dashboard__status" :class="enquiry.statusClass">{{ enquiry.status }}</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="mw-dashboard__panel" data-category="settings" hidden>
                            <h1 class="mw-dashboard__panel-title">Account Settings</h1>
                            <p class="mw-dashboard__panel-subtitle">Update your personal information and password.</p>

                            <form class="mw-dashboard__settings-form" action="#" method="post" @submit.prevent>
                                <div class="mw-dashboard__settings-grid">
                                    <div v-for="field in settingsFieldsPrimary" :key="field.id" class="mw-login-form__field">
                                        <label :for="field.id">{{ field.label }}</label>
                                        <input :type="field.type" :id="field.id" :name="field.name" :value="field.value" :autocomplete="field.autocomplete">
                                    </div>
                                </div>

                                <div class="mw-dashboard__settings-grid">
                                    <div v-for="field in settingsFieldsPassword" :key="field.id" class="mw-login-form__field">
                                        <label :for="field.id">{{ field.label }}</label>
                                        <input :type="field.type" :id="field.id" :name="field.name" :placeholder="field.placeholder" :autocomplete="field.autocomplete">
                                    </div>
                                </div>

                                <button type="submit" class="mw-login-form__submit">Save Changes</button>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
