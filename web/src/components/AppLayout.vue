<script setup lang="ts">
import { computed, h, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import {
  NButton,
  NDropdown,
  NFlex,
  NIcon,
  NLayout,
  NLayoutContent,
  NLayoutFooter,
  NLayoutHeader,
  NPageHeader,
  NText
} from 'naive-ui'
import type { DropdownOption } from 'naive-ui'
import { HomeOutline, LogOutOutline, SettingsOutline } from '@vicons/ionicons5'
import Cookies from 'js-cookie'

const props = withDefaults(defineProps<{
  title: string
  subtitle?: string
  contentStyle?: string
  showHome?: boolean
  showSettings?: boolean
  showAccount?: boolean
}>(), {
  subtitle: '',
  contentStyle: 'padding: 24px;',
  showHome: false,
  showSettings: true,
  showAccount: true
})

const router = useRouter()
const username = ref(Cookies.get('username'))
const isLogin = computed(() => Boolean(username.value))

const accountOptions = computed<DropdownOption[]>(() => {
  const items: DropdownOption[] = []

  if (props.showSettings) {
    items.push({
      label: '系统设置',
      key: 'settings',
      props: {
        onClick: () => router.push('/admin/settings')
      },
      icon: () => h(NIcon, null, { default: () => h(SettingsOutline) })
    })
  }

  items.push({
    label: '退出登录',
    key: 'logout',
    props: {
      style: 'color: red;',
      onClick: logout
    },
    icon: () => h(NIcon, null, { default: () => h(LogOutOutline) })
  })

  return items
})

/**
 * 退出登录并清理本地登录状态
 */
async function logout() {
  try {
    await fetch('/api/admin/logout', {
      method: 'POST',
      credentials: 'include',
      headers: {
        Accept: 'application/json'
      }
    })
  } catch {
    // 本地退出即可。
  } finally {
    clearLogin()
  }
}

/**
 * 清理登录 Cookie 并跳转登录页
 */
function clearLogin() {
  Cookies.remove('token')
  Cookies.remove('username')
  username.value = undefined
  router.push('/login')
}
</script>

<template>
  <n-layout class="page">
    <n-layout-header bordered>
      <n-page-header :subtitle="subtitle" class="page-header">
        <template #title>{{ title }}</template>
        <template #extra>
          <n-flex align="center" :size="8">
            <RouterLink v-if="showHome" to="/">
              <n-button quaternary>
                <template #icon>
                  <n-icon>
                    <HomeOutline />
                  </n-icon>
                </template>
                首页
              </n-button>
            </RouterLink>
            <slot name="extra" />
            <n-dropdown v-if="showAccount && isLogin" :options="accountOptions" placement="bottom-start">
              <n-button :bordered="false">{{ username }}</n-button>
            </n-dropdown>
          </n-flex>
        </template>
      </n-page-header>
    </n-layout-header>

    <n-layout-content :content-style="contentStyle">
      <slot />
    </n-layout-content>

    <n-layout-footer class="footer">
      <n-text depth="3">@kdapk01</n-text>
    </n-layout-footer>
  </n-layout>
</template>

<style scoped>
.page {
  min-height: 100vh;
}

.page-header {
  padding: 16px 24px;
}

.footer {
  padding: 16px;
  text-align: center;
}
</style>
