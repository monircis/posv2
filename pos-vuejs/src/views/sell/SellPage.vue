<template>
  <div>
    <RouterView />
  </div>
</template>

<script setup lang="ts">
import { useErpPage } from '../../erp/useErpPage';

useErpPage('sell', 'sellNav');
</script>
