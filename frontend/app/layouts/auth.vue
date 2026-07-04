<script setup lang="ts">
const { appName } = useRuntimeConfig().public
</script>

<template>
  <v-main class="auth-layout">
    <!-- Ambient background accents -->
    <div
      aria-hidden="true"
      class="auth-glow auth-glow--top"
    />
    <div
      aria-hidden="true"
      class="auth-glow auth-glow--bottom"
    />

    <div class="auth-content">
      <div class="d-flex flex-column align-center text-center mb-8">
        <v-avatar
          color="primary"
          rounded="lg"
          size="48"
        >
          <v-icon
            icon="mdi-hexagon"
            size="24"
          />
        </v-avatar>
        <span class="mt-3 text-title-large font-weight-bold">
          {{ appName }}
        </span>
      </div>

      <slot />

      <p class="mt-8 text-center text-body-small text-medium-emphasis">
        &copy; {{ new Date().getFullYear() }} {{ appName }}. All rights reserved.
      </p>
    </div>
  </v-main>
</template>

<style scoped>
.auth-layout {
  position: relative;
  display: flex;
  min-height: 100dvh;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  padding: 2.5rem 1rem;
}

.auth-content {
  position: relative;
  width: 100%;
  max-width: 30rem;
}

.auth-glow {
  position: absolute;
  height: 18rem;
  width: 18rem;
  border-radius: 9999px;
  filter: blur(64px);
  pointer-events: none;
  background: rgb(var(--v-theme-primary));
}

.auth-glow--top {
  top: -6rem;
  left: -6rem;
  opacity: 0.2;
}

.auth-glow--bottom {
  right: -6rem;
  bottom: -6rem;
  opacity: 0.1;
}
</style>
