<script setup lang="ts">
import { computed, h, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import {
  NButton,
  NDataTable,
  NDatePicker,
  NDropdown,
  NFlex,
  NIcon,
  NImage,
  NInput,
  NLayout,
  NLayoutContent,
  NLayoutFooter,
  NLayoutHeader,
  NList,
  NListItem,
  NModal,
  NPageHeader,
  NText,
  NUpload,
  useDialog,
  useMessage
} from 'naive-ui'
import type { DataTableColumns, UploadCustomRequestOptions } from 'naive-ui'
import { ClipboardOutline, CloudUploadOutline, SearchOutline, TrashOutline } from '@vicons/ionicons5'
import Cookies from 'js-cookie'

interface ImageItem {
  id: number
  uid: string
  original_name: string
  name: string
  extension: string
  mime: string
  size_kb: number
  width: number | null
  height: number | null
  year: string
  month: string
  url: string
  created_at: string
}

const message = useMessage()
const dialog = useDialog()
const router = useRouter()

const username = ref(Cookies.get('username'))
const isLogin = computed(() => Boolean(username.value))

const loading = ref(false)
const is_upload_loading = ref(false)
const tableData = ref<ImageItem[]>([])
const selectedImage = ref<ImageItem | null>(null)
const isInfoModalVisible = ref(false)
const keyword = ref('')
const archiveMonth = ref<number | null>(null)

/**
 * DataTable 分页信息
 */
const pagination = reactive({
  page: 1,
  pageSize: 10,
  itemCount: 0,
  showSizePicker: true,
  pageSizes: [10, 20, 30, 50],
  onChange: (page: number) => {
    pagination.page = page
    fetchImages()
  },
  onUpdatePageSize: (pageSize: number) => {
    pagination.pageSize = pageSize
    pagination.page = 1
    fetchImages()
  }
})

/**
 * 账户按钮下拉选项
 */
const accountOptions = [
  {
    label: '退出登录',
    key: 'logout',
    props: {
      style: 'color: red;',
      onClick: logout
    }
  }
]

/**
 * DataTable 表头信息
 */
const tableColumns: DataTableColumns<ImageItem> = [
  { title: 'ID', key: 'id', width: '5vw' },
  {
    title: '图像',
    key: 'url',
    width: '15vw',
    render(row) {
      return h(NImage, {
        height: '100px',
        width: '100%',
        objectFit: 'contain',
        lazy: true,
        src: thumbUrl(row.url),
        previewSrc: row.url,
        alt: row.original_name
      })
    }
  },
  {
    title: '原始名称',
    key: 'original_name',
    width: '20vw',
    ellipsis: { tooltip: true }
  },
  {
    title: '尺寸',
    key: 'dimensions',
    width: '10vw',
    render(row) {
      return row.width && row.height ? `${row.width} x ${row.height}` : '-'
    }
  },
  {
    title: '大小',
    key: 'size_kb',
    width: '10vw',
    render(row) {
      return `${row.size_kb} KB`
    }
  },
  {
    title: '链接',
    key: 'url',
    width: '10vw',
    ellipsis: { tooltip: true }
  },
  {
    title: '上传时间',
    key: 'created_at',
    width: '10vw'
  },
  {
    title: '操作',
    key: 'actions',
    width: '20vw',
    render(row) {
      return h(NFlex, { size: 8 }, () => [
        h(
          NButton,
          {
            tertiary: true,
            size: 'small',
            onClick: () => copyUrl(row.url)
          },
          { icon: () => h(NIcon, null, { default: () => h(ClipboardOutline) }), default: () => '复制' }
        ),
        h(
          NButton,
          {
            tertiary: true,
            size: 'small',
            onClick: () => openInfo(row)
          },
          { default: () => '详情' }
        ),
        h(
          NButton,
          {
            tertiary: true,
            type: 'error',
            size: 'small',
            onClick: () => confirmDelete(row)
          },
          { icon: () => h(NIcon, null, { default: () => h(TrashOutline) }), default: () => '删除' }
        )
      ])
    }
  }
]

/**
 * 请求 API 的封装方法
 * @param url
 * @param init
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

function archiveParams(): Record<string, string> {
  if (!archiveMonth.value) return {}
  const date = new Date(archiveMonth.value)
  return {
    year: String(date.getFullYear()),
    month: String(date.getMonth() + 1).padStart(2, '0')
  }
}

/**
 * 请求图片
 */
async function fetchImages() {
  if (!isLogin.value) return

  loading.value = true
  try {
    const params = new URLSearchParams({
      page: String(pagination.page),
      page_size: String(pagination.pageSize),
      keyword: keyword.value,
      ...archiveParams()
    })
    const data = await apiFetch(`/api/images?${params.toString()}`)
    tableData.value = data.items
    pagination.itemCount = data.total
  } catch (error) {
    message.error(error instanceof Error ? error.message : '加载图片失败')
  } finally {
    loading.value = false
  }
}

/**
 * 上传图片
 * @param options
 */
async function uploadImage(options: UploadCustomRequestOptions) {
  is_upload_loading.value = true

  const form = new FormData()
  form.append('file', options.file.file as File)

  try {
    const response = await fetch('/api/images', {
      method: 'POST',
      credentials: 'include',
      headers: {
        Accept: 'application/json'
      },
      body: form
    })
    const result = await response.json()

    if (!response.ok || result.code !== 0) {
      throw new Error(result.message || '上传失败')
    }

    options.onFinish()
    message.success('上传成功')
    pagination.page = 1
    fetchImages()
  } catch (error) {
    options.onError()
    message.error(error instanceof Error ? error.message : '上传失败')
  } finally {
    is_upload_loading.value = false
  }
}

/**
 * 确认删除的弹窗
 * @param row 行数据
 */
function confirmDelete(row: ImageItem) {
  blurActiveElement()
  dialog.warning({
    title: '删除图片',
    content: `确定删除 ${row.original_name} 吗？该操作不可恢复。`,
    positiveText: '删除',
    negativeText: '取消',
    onPositiveClick: async () => {
      await apiFetch(`/api/images/${row.id}`, { method: 'DELETE' })
      message.success('删除成功')
      fetchImages()
    }
  })
}

/**
 * 复制图片的全链接
 * @param url 网址
 */
async function copyUrl(url: string) {
  const fullUrl = new URL(url, window.location.origin).href
  await writeClipboard(fullUrl)
  message.success('已复制链接')
}

/**
 * 打开图片信息模态框
 * @param row 行数据
 */
function openInfo(row: ImageItem) {
  blurActiveElement()
  selectedImage.value = row
  isInfoModalVisible.value = true
}

/**
 * 拼接缩略图链接
 * @param url 原始图片的链接
 */
function thumbUrl(url: string) {
  return `${url}/thumb`
}

/**
 * 登出
 */
async function logout() {
  try {
    await apiFetch('/api/admin/logout', { method: 'POST' })
  } catch {
    // 本地退出即可。
  } finally {
    clearLogin()
  }
}

/**
 * 登出的操作
 */
function clearLogin() {
  Cookies.remove('token')
  Cookies.remove('username')
  username.value = undefined
  router.push('/login')
}

/**
 * DataTable 搜索回调
 */
function search() {
  pagination.page = 1
  fetchImages()
}

async function writeClipboard(text: string) {
  if (navigator.clipboard?.writeText && window.isSecureContext) {
    await navigator.clipboard.writeText(text)
    return
  }

  const textarea = document.createElement('textarea')
  textarea.value = text
  textarea.setAttribute('readonly', 'readonly')
  textarea.style.position = 'fixed'
  textarea.style.left = '-9999px'
  document.body.appendChild(textarea)
  textarea.select()

  try {
    document.execCommand('copy')
  } finally {
    document.body.removeChild(textarea)
  }
}

function blurActiveElement() {
  const active = document.activeElement
  if (active instanceof HTMLElement) active.blur()
}

onMounted(fetchImages)
</script>

<template>
  <n-layout class="page">
    <n-layout-header bordered>
      <n-page-header subtitle="图床" class="page-header">
        <template #title>ImageHosting</template>
        <template #extra>
          <template v-if="isLogin">
            <n-dropdown :options="accountOptions" placement="bottom-start">
              <n-button :bordered="false">{{ username }}</n-button>
            </n-dropdown>
          </template>
          <template v-else>
            <RouterLink to="/login">
              <n-button>登录</n-button>
            </RouterLink>
          </template>
        </template>
      </n-page-header>
    </n-layout-header>

    <n-layout-content content-style="padding: 24px;">
      <template v-if="isLogin">
        <!-- 工具栏 -->
        <n-flex justify="end" class="toolbar">

          <!-- 搜索 -->
          <n-flex>
            <n-input v-model:value="keyword" clearable placeholder="搜索原始名称、UID" class="search-input"
              @keyup.enter="search" />
            <n-date-picker v-model:value="archiveMonth" type="month" clearable placeholder="按月份筛选"
              @update:value="search" />
            <n-button secondary @click="search">
              <template #icon>
                <n-icon>
                  <SearchOutline />
                </n-icon>
              </template>
              搜索
            </n-button>
          </n-flex>

          <!-- 上传按钮 -->
          <n-upload :show-file-list="false" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml"
            :custom-request="uploadImage" style="width: auto;">
            <n-button type="primary" :loading="is_upload_loading">
              <template #icon>
                <n-icon>
                  <CloudUploadOutline />
                </n-icon>
              </template>
              上传
            </n-button>
          </n-upload>
        </n-flex>

        <n-data-table remote :columns="tableColumns" :data="tableData" :pagination="pagination" :loading="loading"
          class="image-table" />
      </template>
    </n-layout-content>

    <n-layout-footer class="footer">
      <n-text depth="3">@kdapk01</n-text>
    </n-layout-footer>
  </n-layout>

  <n-modal v-model:show="isInfoModalVisible" preset="card" title="详细信息" :style="{ width: '80vw', maxWidth: '960px' }"
    :bordered="false">
    <n-list v-if="selectedImage">
      <n-list-item>UID：{{ selectedImage.uid }}</n-list-item>
      <n-list-item>原始名称：{{ selectedImage.original_name }}</n-list-item>
      <n-list-item>保存文件名：{{ selectedImage.name }}</n-list-item>
      <n-list-item>尺寸：{{ selectedImage.width || '-' }} x {{ selectedImage.height || '-' }}</n-list-item>
      <n-list-item>图片大小：{{ selectedImage.size_kb }} KB</n-list-item>
      <n-list-item>MIME：{{ selectedImage.mime }}</n-list-item>
      <n-list-item>归档：{{ selectedImage.year }}/{{ selectedImage.month }}</n-list-item>
      <n-list-item>上传时间：{{ selectedImage.created_at }}</n-list-item>
      <n-list-item>访问链接：{{ selectedImage.url }}</n-list-item>
    </n-list>
  </n-modal>
</template>

<style scoped>
.page {
  min-height: 100vh;
  background: #f6f8fb;
}

.page-header {
  padding: 16px 24px;
  background: #fff;
}

.toolbar {
  margin-bottom: 12px;
}

.search-input {
  width: 280px;
}

.image-table {
  background: #fff;
}

.footer {
  padding: 16px;
  text-align: center;
}
</style>
