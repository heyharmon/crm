<script setup>
import AppNav from '@/components/AppNav.vue'
</script>

<template>
    <div class="min-h-screen bg-white">
        <AppNav />

        <main class="max-w-[1563px] mx-auto px-4">
            <slot />
        </main>
    </div>
</template>
