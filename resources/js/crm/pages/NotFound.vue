<template>
    <div class="text-center py-5 text-muted">
        <h1 class="h4 fw-bold text-dark">Page not found</h1>
        <p>This screen isn't in the new CRM yet.</p>
        <RouterLink :to="{ name: 'leads.index' }" class="btn btn-primary">Go to Leads</RouterLink>
    </div>
</template>
