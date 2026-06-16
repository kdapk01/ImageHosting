<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
    NButton,
    NCard,
    NFlex,
    NForm,
    NFormItem,
    NIcon,
    NImage,
    NInput,
    NInputNumber,
    NSpin,
    NSwitch,
    NUpload,
    useMessage
} from 'naive-ui'
import type { FormInst, FormRules, UploadCustomRequestOptions } from 'naive-ui'
import { CloudUploadOutline } from '@vicons/ionicons5'
import Cookies from 'js-cookie'
import AppLayout from '../../components/AppLayout.vue'

interface SettingsData {
    site_title: string
    favicon_url: string
    upload_allowed_mimes: string
    upload_max_size: string
    upload_disk: string
    upload_path: string
    hotlink_enabled: boolean
    hotlink_allow_empty_referer: boolean
    hotlink_extensions: string
    hotlink_allowed_domains: string
    hotlink_deny_status: number
}

const message = useMessage()
const router = useRouter()

const formRef = ref<FormInst | null>(null)
const loading = ref(false)
const loadFailed = ref(false)
const saving = ref(false)
const faviconUploading = ref(false)
const faviconUrl = ref('/favicon.ico')
const configEditable = computed(() => !loading.value && !loadFailed.value)

const formModel = ref({
    site_title: 'ImageHosting',
    upload_allowed_mimes: '{"image/jpeg":"jpg","image/png":"png","image/gif":"gif","image/webp":"webp","image/svg+xml":"svg"}',
    upload_max_size: '1M',
    upload_disk: 'upload',
    upload_path: '',
    hotlink_enabled: false,
    hotlink_allow_empty_referer: true,
    hotlink_extensions: 'jpg,jpeg,png,gif,webp,svg',
    hotlink_allowed_domains: '',
    hotlink_deny_status: 403
})

const rules = {
    site_title: [
        { required: true, message: '请输入网站标题' },
        { max: 80, message: '网站标题不能超过 80 个字符' }
    ],
    upload_allowed_mimes: [
        { required: true, message: '请输入允许上传类型 JSON' },
        {
            validator: validateAllowedMimes,
            message: '请输入有效的 MIME 到扩展名 JSON 对象'
        }
    ],
    upload_max_size: [
        { required: true, message: '请输入上传大小限制' },
        {
            pattern: /^\d+(?:\.\d+)?[KMGT]?B?$/i,
            message: '格式示例：1M、512K、2MB'
        }
    ],
    upload_disk: [
        { required: true, message: '请输入上传磁盘名称' },
        {
            pattern: /^[a-z0-9_-]+$/i,
            message: '只能包含字母、数字、下划线和短横线'
        }
    ],
    hotlink_deny_status: [
        {
            validator: (_rule, value: number) => Number.isInteger(value) && value >= 400 && value <= 599,
            message: '请输入 400 到 599 之间的状态码'
        }
    ]
} satisfies FormRules

/**
 * 请求 API 的封装方法
 * @param url 请求地址
 * @param init Fetch 配置
 * @returns 响应 data 字段
 */
async function apiFetch(url: string, init: RequestInit = {}) {
    const response = await fetch(url, {
        ...init,
        credentials: 'include',
        headers: {
            Accept: 'application/json',
            ...(init.headers || {})
        }
    })
    const result = await response.json()

    if (!response.ok || result.code !== 0) {
        if (response.status === 401) clearLogin()
        throw new Error(result.message || '请求失败')
    }

    return result.data
}

/**
 * 获取系统配置并写入表单
 */
async function fetchSettings() {
    loading.value = true
    loadFailed.value = false
    try {
        const data = (await apiFetch('/api/admin/settings')) as SettingsData
        applySettingsData(data)
        faviconUrl.value = data.favicon_url || cacheFaviconUrl()
        applyDocumentMeta()
    } catch (error) {
        loadFailed.value = true
        message.error(error instanceof Error ? error.message : '加载设置失败')
    } finally {
        loading.value = false
    }
}

/**
 * 保存系统配置
 * @param e 鼠标事件
 */
async function saveSettings(e: MouseEvent) {
    e.preventDefault()
    if (saving.value || !configEditable.value) return

    await formRef.value?.validate()
    saving.value = true

    try {
        const data = (await apiFetch('/api/admin/settings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formModel.value)
        })) as SettingsData
        applySettingsData(data)
        faviconUrl.value = data.favicon_url
        applyDocumentMeta()
        message.success('保存成功')
    } catch (error) {
        message.error(error instanceof Error ? error.message : '保存失败')
    } finally {
        saving.value = false
    }
}

/**
 * 上传并替换 favicon.ico
 * @param options Naive UI 上传请求参数
 */
async function uploadFavicon(options: UploadCustomRequestOptions) {
    if (!configEditable.value) {
        options.onError()
        return
    }

    faviconUploading.value = true

    const form = new FormData()
    form.append('file', options.file.file as File)

    try {
        const response = await fetch('/api/admin/favicon', {
            method: 'POST',
            credentials: 'include',
            headers: {
                Accept: 'application/json'
            },
            body: form
        })
        const result = await response.json()

        if (!response.ok || result.code !== 0) {
            if (response.status === 401) clearLogin()
            throw new Error(result.message || '上传失败')
        }

        faviconUrl.value = result.data.favicon_url || cacheFaviconUrl()
        refreshFaviconLink()
        options.onFinish()
        message.success('ico 图标已更新')
    } catch (error) {
        options.onError()
        message.error(error instanceof Error ? error.message : '上传失败')
    } finally {
        faviconUploading.value = false
    }
}

/**
 * 清理登录 Cookie 并跳转登录页
 */
function clearLogin() {
    Cookies.remove('token')
    Cookies.remove('username')
    router.push('/login')
}

/**
 * 应用页面标题和 favicon
 */
function applyDocumentMeta() {
    document.title = `设置-${formModel.value.site_title || 'ImageHosting'}`
    refreshFaviconLink()
}

/**
 * 刷新页面 favicon 链接
 */
function refreshFaviconLink() {
    let link = document.querySelector<HTMLLinkElement>('link[rel="icon"]')
    if (!link) {
        link = document.createElement('link')
        link.rel = 'icon'
        document.head.appendChild(link)
    }
    link.type = 'image/x-icon'
    link.href = faviconUrl.value || cacheFaviconUrl()
}

/**
 * 生成带缓存版本的 favicon 地址
 * @returns favicon 地址
 */
function cacheFaviconUrl() {
    return `/favicon.ico?v=${Date.now()}`
}

/**
 * 将接口返回的设置数据同步到表单
 * @param data 设置数据
 */
function applySettingsData(data: SettingsData) {
    formModel.value = {
        site_title: data.site_title || 'ImageHosting',
        upload_allowed_mimes: data.upload_allowed_mimes || formModel.value.upload_allowed_mimes,
        upload_max_size: data.upload_max_size || '1M',
        upload_disk: data.upload_disk || 'upload',
        upload_path: data.upload_path || '',
        hotlink_enabled: Boolean(data.hotlink_enabled),
        hotlink_allow_empty_referer: Boolean(data.hotlink_allow_empty_referer),
        hotlink_extensions: data.hotlink_extensions || '',
        hotlink_allowed_domains: data.hotlink_allowed_domains || '',
        hotlink_deny_status: Number(data.hotlink_deny_status || 403)
    }
}

/**
 * 校验允许上传 MIME JSON
 * @param _rule Naive UI 表单校验规则
 * @param value 输入值
 * @returns 是否校验通过
 */
function validateAllowedMimes(_rule: unknown, value: string) {
    try {
        const parsed = JSON.parse(value)
        return (
            parsed &&
            typeof parsed === 'object' &&
            !Array.isArray(parsed) &&
            Object.keys(parsed).length > 0 &&
            Object.entries(parsed).every(([mime, extension]) => {
                return /^[a-z0-9.+-]+\/[a-z0-9.+-]+$/i.test(mime) && /^[a-z0-9]+$/i.test(String(extension))
            })
        )
    } catch {
        return false
    }
}

onMounted(() => {
    document.title = `设置-${formModel.value.site_title}`
    fetchSettings()
})
</script>

<template>
    <AppLayout
        :title="formModel.site_title"
        subtitle="系统设置"
        content-style="padding: 16px 24px;"
        show-home
        :show-settings="false"
    >
        <n-spin :show="loading">
            <n-form ref="formRef" :model="formModel" :rules="rules" label-placement="top"
                :disabled="!configEditable" class="settings-wrap">
                <n-flex vertical :size="10">
                    <n-card title="基础设置" :bordered="false">
                        <n-form-item label="网站标题" path="site_title">
                            <n-input v-model:value="formModel.site_title" clearable maxlength="80" show-count />
                        </n-form-item>
                    </n-card>

                    <n-card title="网页 ico 图标" :bordered="false">
                        <n-flex :size=10>
                            <n-image :src="faviconUrl" width="48" height="48" object-fit="contain" preview-disabled
                                class="favicon-preview" />
                            <n-upload :show-file-list="false" accept=".ico,image/x-icon,image/vnd.microsoft.icon"
                                :custom-request="uploadFavicon" :disabled="!configEditable">
                                <n-button secondary :loading="faviconUploading" :disabled="!configEditable">
                                    <template #icon>
                                        <n-icon>
                                            <CloudUploadOutline />
                                        </n-icon>
                                    </template>
                                    上传 ico
                                </n-button>
                            </n-upload>
                        </n-flex>
                    </n-card>

                    <n-card title="上传配置" :bordered="false">
                        <n-form-item label="允许上传 MIME JSON" path="upload_allowed_mimes">
                            <n-input v-model:value="formModel.upload_allowed_mimes" type="textarea"
                                :autosize="{ minRows: 4, maxRows: 8 }" />
                        </n-form-item>
                        <n-flex :size="16" class="form-grid">
                            <n-form-item label="单文件大小限制" path="upload_max_size">
                                <n-input v-model:value="formModel.upload_max_size" placeholder="1M" />
                            </n-form-item>
                            <n-form-item label="Filesystem 磁盘" path="upload_disk">
                                <n-input v-model:value="formModel.upload_disk" placeholder="upload" />
                            </n-form-item>
                        </n-flex>
                        <n-form-item label="存储路径前缀" path="upload_path">
                            <n-input v-model:value="formModel.upload_path" clearable placeholder="留空表示磁盘根目录" />
                        </n-form-item>
                    </n-card>

                    <n-card title="防盗链配置" :bordered="false">
                        <n-flex :size="24" class="switch-row">
                            <n-form-item label="开启防盗链" path="hotlink_enabled">
                                <n-switch v-model:value="formModel.hotlink_enabled" />
                            </n-form-item>
                            <n-form-item label="允许空 Referer" path="hotlink_allow_empty_referer">
                                <n-switch v-model:value="formModel.hotlink_allow_empty_referer" />
                            </n-form-item>
                        </n-flex>
                        <n-form-item label="生效后缀" path="hotlink_extensions">
                            <n-input v-model:value="formModel.hotlink_extensions" type="textarea"
                                :autosize="{ minRows: 2, maxRows: 5 }" placeholder="jpg,jpeg,png,gif,webp,svg" />
                        </n-form-item>
                        <n-form-item label="许可域名" path="hotlink_allowed_domains">
                            <n-input v-model:value="formModel.hotlink_allowed_domains" type="textarea"
                                :autosize="{ minRows: 2, maxRows: 6 }" placeholder="example.com,*.example.com" />
                        </n-form-item>
                        <n-form-item label="拒绝状态码" path="hotlink_deny_status">
                            <n-input-number v-model:value="formModel.hotlink_deny_status" :min="400" :max="599" />
                        </n-form-item>
                    </n-card>

                    <n-flex justify="center">
                        <n-button type="primary" size="large" :loading="saving"
                            :disabled="!configEditable || saving" @click="saveSettings">
                            保存全部配置
                        </n-button>
                    </n-flex>
                </n-flex>
            </n-form>
        </n-spin>
    </AppLayout>
</template>

<style scoped>
.settings-wrap {
    width: min(760px, 100%);
    margin: 0 auto;
}

.form-grid> :deep(.n-form-item) {
    flex: 1 1 260px;
}

.switch-row {
    flex-wrap: wrap;
}

:deep(.n-card-header) {
    padding: 14px 18px 8px;
}

:deep(.n-card__content) {
    padding: 12px 18px 16px;
}

:deep(.n-form-item) {
    --n-blank-height: 18px;
}

.favicon-preview {
    padding: 8px;
    background: #f6f8fb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
}

</style>
