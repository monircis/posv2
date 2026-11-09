<template>
  <div>
    <RouterView />
  </div>
</template>

<script setup lang="ts">
// Payment accounts: the list (AccountList) and one account (AccountDetails), read from /api/payment-accounts.
import { useErpPage } from '../../erp/useErpPage';

useErpPage('accounts', 'pa_nav');
</script>
