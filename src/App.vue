<template>
  <q-layout>
    <Header />
    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script>
import Header from "@/components/TheHeader.vue";
import { useMeta } from "quasar";
import { useI18n } from "vue-i18n";

export default {
  components: { Header },
  beforeCreate() {
    const { t } = useI18n();
    useMeta(() => ({
      title: "Livrero",
      meta: {
        // Primary Meta Tags
        description: { name: "description", content: t("meta.description") },
        keywords: { name: "keywords", content: t("meta.keywords") },
        author: { name: "author", content: "Arnon Rodrigues" },
        // Open Graph / Facebook
        ogType: { name: "og:type", content: "website" },
        ogTitle: { name: "og:title", content: "Livrero" },
        ogDescription: { name: "og:description", content: t("meta.description") },
        ogImage: { name: "og:image", content: "https://livrero.vercel.app/main.jpg" },
        ogUrl: { name: "og:url", content: "https://livrero.vercel.app/" },
        ogProperty: { name: "og:image:alt", content: t('meta.image-alt') },

        // Twitter
        twitterCard: { name: "twitter:card", content: "summary_large_image" },
        twitterTitle: { name: "twitter:title", content: "Livrero" },
        twitterDescription: { name: "twitter:description", content: t("meta.description") },
        twitterImage: { name: "twitter:image", content: "https://livrero.vercel.app/main.jpg" },
        twitterUrl: { name: "twitter:url", content: "https://livrero.vercel.app/" },
      },
    }));
  },
};
</script>
