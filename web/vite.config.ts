import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import { viteStaticCopy } from "vite-plugin-static-copy";

// https://vite.dev/config/
export default defineConfig({
  base: "/assets/web/",
  build: {
    assetsDir: "",
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
