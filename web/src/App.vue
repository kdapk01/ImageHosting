<script setup lang="ts">
import { NConfigProvider, NDialogProvider, NMessageProvider, NModalProvider, zhCN, dateZhCN } from 'naive-ui'

</script>

<template>
  <n-config-provider :locale="zhCN" :date-locale="dateZhCN">
    <n-dialog-provider>
      <n-message-provider>
        <n-modal-provider>
          <RouterView />
        </n-modal-provider>
      </n-message-provider>
    </n-dialog-provider>
  </n-config-provider>
</template>
