<template>
  <RouterView />
</template>

<script setup lang="ts">
import { useErpPage } from '../../erp/useErpPage';

useErpPage('mfg', 'mf_nav');
</script>
