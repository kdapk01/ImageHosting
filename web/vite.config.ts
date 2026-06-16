import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import { viteStaticCopy } from "vite-plugin-static-copy";

// https://vite.dev/config/
export default defineConfig({
  base: "/assets/web/",
  build: {
    assetsDir: "",
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (!id.includes("node_modules")) return;

          if (id.includes("node_modules/vue") || id.includes("node_modules/@vue")) {
            return "vendor-vue";
          }

          if (id.includes("node_modules/naive-ui")) {
            if (id.includes("node_modules/naive-ui/es/data-table")) {
              return "vendor-naive-table";
            }

            if (
              id.includes("node_modules/naive-ui/es/form")
              || id.includes("node_modules/naive-ui/es/input")
              || id.includes("node_modules/naive-ui/es/input-number")
              || id.includes("node_modules/naive-ui/es/date-picker")
              || id.includes("node_modules/naive-ui/es/upload")
              || id.includes("node_modules/naive-ui/es/switch")
            ) {
              return "vendor-naive-form";
            }

            if (
              id.includes("node_modules/naive-ui/es/modal")
              || id.includes("node_modules/naive-ui/es/dialog")
              || id.includes("node_modules/naive-ui/es/message")
              || id.includes("node_modules/naive-ui/es/dropdown")
              || id.includes("node_modules/naive-ui/es/image")
              || id.includes("node_modules/naive-ui/es/spin")
            ) {
              return "vendor-naive-feedback";
            }

            if (
              id.includes("node_modules/naive-ui/es/layout")
              || id.includes("node_modules/naive-ui/es/page-header")
              || id.includes("node_modules/naive-ui/es/card")
              || id.includes("node_modules/naive-ui/es/button")
              || id.includes("node_modules/naive-ui/es/flex")
              || id.includes("node_modules/naive-ui/es/icon")
              || id.includes("node_modules/naive-ui/es/text")
              || id.includes("node_modules/naive-ui/es/list")
            ) {
              return "vendor-naive-layout";
            }

            return "vendor-naive-core";
          }

          if (id.includes("node_modules/@vicons")) {
            return "vendor-icons";
          }

          return "vendor";
        },
      },
    },
  },
  plugins: [
    vue(),
    viteStaticCopy({
      targets: [
        // 复制打包后assets文件到 /public/assets/web/ 下
        {
          src: "dist/*.{js,css,ico,png,jpg,svg}",
          dest: "../../public/assets/web",
          rename: { stripBase: 1 },
        },
        // 复制index.html文件到
        {
          src: "dist/index.html",
          dest: "../../view",
          rename: { stripBase: 1 },
        },
      ],
    }),
  ],
});
