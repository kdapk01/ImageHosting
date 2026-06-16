<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useMessage, NFlex, NCard, NForm, NFormItem, NInput, NButton } from 'naive-ui'
import type { FormInst, FormRules } from 'naive-ui'
import Cookies from 'js-cookie'

const message = useMessage()
const router = useRouter()

Cookies.remove('token')
if (Cookies.get('username') !== undefined) router.push('/')

const formRef = ref<FormInst | null>(null)
const isLoading = ref(false)

const rules = {
  username: [{ required: true, message: '请输入用户名' }],
  password: [{ required: true, message: '请输入密码' }]
} satisfies FormRules

const formModel = ref({
  username: '',
  password: ''
})

/**
 * 提交登录表单
 * @param e 鼠标事件
 */
async function login(e: MouseEvent) {
  e.preventDefault()
  if (isLoading.value) return

  await formRef.value?.validate()
  isLoading.value = true

  try {
    const response = await fetch('/api/admin/login', {
      method: 'POST',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(formModel.value)
    })
    const result = await response.json()

    if (!response.ok || result.code !== 0) {
      throw new Error(result.message || '登录失败')
    }

    Cookies.set('username', result.data.username, { expires: 30, path: '/' })
    Cookies.remove('token')
    message.success('登录成功')
    router.push('/')
  } catch (error) {
    message.error(error instanceof Error ? error.message : '登录失败')
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <n-flex justify="center" align="center" class="login-page">
    <n-card title="登录" class="login-card">
      <n-form ref="formRef" :model="formModel" :rules="rules" label-placement="top">
        <n-form-item label="用户名" path="username">
          <n-input
            v-model:value="formModel.username"
            placeholder="用户名"
            :input-props="{ autocomplete: 'username' }"
            clearable
          />
        </n-form-item>

        <n-form-item label="密码" path="password">
          <n-input
            v-model:value="formModel.password"
            placeholder="密码"
            type="password"
            show-password-on="mousedown"
            :input-props="{ autocomplete: 'current-password' }"
            clearable
          />
        </n-form-item>

        <n-form-item :show-label="false">
          <n-button type="primary" :loading="isLoading" @click="login">登录</n-button>
        </n-form-item>
      </n-form>
    </n-card>
  </n-flex>
</template>

<style scoped>
.login-page {
  min-height: 100vh;
  padding: 24px;
  background: #f6f8fb;
}

.login-card {
  width: min(420px, 100%);
}

.n-button {
  width: 100%;
}
</style>
